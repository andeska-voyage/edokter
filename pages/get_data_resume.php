<?php
/**
 * get_data_resume.php
 * Handler AJAX untuk mengambil data resume medis dari berbagai sumber:
 *   1. penilaian_medis_igd       (IGD)
 *   2. penilaian_medis_ralan*    (18 tabel poli rawat jalan)
 *   3. pemeriksaan_ralan         (SOAP harian, multi-row)
 *
 * Logika fallback per field (prioritas dari atas ke bawah):
 *   - keluhan_utama       : IGD → Ralan → SOAP
 *   - jalannya_penyakit   : SOAP (kronologis) → IGD/Ralan (ket_lokalis jika ada)
 *   - hasil_laborat       : IGD (lab) → Ralan (lab)
 *   - pemeriksaan_penunjang : IGD (rad+ekg) → Ralan (rad+ekg+penunjang+penunjanglain)
 *   - obat_pulang         : SOAP (rtl terakhir) → IGD (tata) → Ralan (tata/terapi/tindakan)
 *
 * Input  : POST { aksi=get_data_resume, no_rawat=<...> }
 * Output : JSON { status, data: {...}, sources: {field: source_name} }
 */

error_reporting(0);
ini_set('display_errors', 0);
session_start();

require_once('../conf/conf.php');
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

if (!isset($_SESSION["ses_dokter"])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired atau belum login']);
    exit();
}

$aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

if ($aksi !== 'get_data_resume') {
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenali']);
    exit();
}

try {
    $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
    if (empty($no_rawat)) {
        throw new Exception('No. Rawat tidak valid');
    }

    // Decrypt kd_dokter dari session — dipakai untuk filter SOAP
    // (SOAP bisa diisi banyak user pada no_rawat yang sama, ambil milik dokter login saja)
    $kd_dokter_login = encrypt_decrypt($_SESSION["ses_dokter"], 'd');
    if (empty($kd_dokter_login)) {
        throw new Exception('Kode dokter login tidak valid');
    }

    // ============================================================
    // 1. AMBIL DATA DARI 3 SUMBER
    // ============================================================
    $igd   = ambilDariIGD($no_rawat);
    $ralan = ambilDariRalan($no_rawat);
    $soap  = ambilDariSOAP($no_rawat, $kd_dokter_login);

    // ============================================================
    // 2. KOMPOSISI PER FIELD RESUME
    // ============================================================
    $data    = ['keluhan_utama'=>'','jalannya_penyakit'=>'','pemeriksaan_penunjang'=>'','hasil_laborat'=>'','obat_pulang'=>''];
    $sources = [];

    // ---- keluhan_utama: IGD → Ralan → SOAP ----
    if (!empty($igd['keluhan_utama']) || !empty($igd['rps']) || !empty($igd['rpd'])) {
        $data['keluhan_utama'] = formatKeluhan($igd['keluhan_utama'] ?? '', $igd['rps'] ?? '', $igd['rpd'] ?? '');
        $sources['keluhan_utama'] = 'IGD';
    } elseif (!empty($ralan['keluhan_utama']) || !empty($ralan['rps']) || !empty($ralan['rpd'])) {
        $data['keluhan_utama'] = formatKeluhan($ralan['keluhan_utama'] ?? '', $ralan['rps'] ?? '', $ralan['rpd'] ?? '');
        $sources['keluhan_utama'] = labelRalan($ralan['_source']);
    } elseif (!empty($soap['keluhan_kronologis'])) {
        $data['keluhan_utama'] = $soap['keluhan_kronologis'];
        $sources['keluhan_utama'] = 'SOAP';
    }

    // ---- jalannya_penyakit: SOAP kronologis (paling cocok) → fallback ket_lokalis ----
    if (!empty($soap['jalannya_kronologis'])) {
        $data['jalannya_penyakit'] = $soap['jalannya_kronologis'];
        $sources['jalannya_penyakit'] = 'SOAP';
    } elseif (!empty($igd['ket_lokalis'])) {
        $data['jalannya_penyakit'] = $igd['ket_lokalis'];
        $sources['jalannya_penyakit'] = 'IGD';
    } elseif (!empty($ralan['ket_lokalis'])) {
        $data['jalannya_penyakit'] = $ralan['ket_lokalis'];
        $sources['jalannya_penyakit'] = labelRalan($ralan['_source']);
    }

    // ---- hasil_laborat: detail_periksa_lab (terstruktur per item) ----
    $labResult = ambilDariLab($no_rawat);
    if (!empty($labResult)) {
        $data['hasil_laborat'] = $labResult;
        $sources['hasil_laborat'] = 'Lab (detail_periksa_lab)';
    }

    // ---- pemeriksaan_penunjang: hasil_radiologi (narasi per tanggal periksa) ----
    $radResult = ambilDariRadiologi($no_rawat);
    if (!empty($radResult)) {
        $data['pemeriksaan_penunjang'] = $radResult;
        $sources['pemeriksaan_penunjang'] = 'Radiologi (hasil_radiologi)';
    }

    // ---- obat_pulang: SOAP (rtl terakhir) → IGD (tata) → Ralan (tata/terapi/tindakan) ----
    if (!empty($soap['rtl_terakhir'])) {
        $data['obat_pulang'] = $soap['rtl_terakhir'];
        $sources['obat_pulang'] = 'SOAP (RTL)';
    } elseif (!empty($igd['tata'])) {
        $data['obat_pulang'] = trim($igd['tata']);
        $sources['obat_pulang'] = 'IGD';
    } elseif (!empty($ralan['tata']) || !empty($ralan['terapi']) || !empty($ralan['tindakan'])) {
        $obat = [];
        if (!empty($ralan['tata']))     $obat[] = trim($ralan['tata']);
        if (!empty($ralan['terapi']))   $obat[] = trim($ralan['terapi']);
        if (!empty($ralan['tindakan'])) $obat[] = "Tindakan: " . trim($ralan['tindakan']);
        $data['obat_pulang'] = implode("\n\n", $obat);
        $sources['obat_pulang'] = labelRalan($ralan['_source']);
    }

    // ============================================================
    // 3. RETURN
    // ============================================================
    $totalFilled = count(array_filter($data));
    if ($totalFilled === 0) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Tidak ditemukan data di IGD, Awal Medis Ralan, maupun SOAP untuk pasien ini.'
        ]);
        exit();
    }

    echo json_encode([
        'status'  => 'success',
        'message' => 'Data berhasil diambil dari ' . $totalFilled . ' field.',
        'data'    => $data,
        'sources' => $sources
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

exit();


// ================================================================
// HELPERS
// ================================================================

/**
 * Ambil data IGD (1 query).
 * Return: associative array atau []
 */
function ambilDariIGD($no_rawat) {
    $q = bukaquery("SELECT keluhan_utama, rps, rpd, ket_lokalis, lab, rad, ekg, tata
                    FROM penilaian_medis_igd
                    WHERE no_rawat = '$no_rawat'
                    LIMIT 1");
    $row = mysqli_fetch_assoc($q);
    return $row ?: [];
}

/**
 * Ambil data dari 18 tabel awal medis ralan.
 * Loop sampai ketemu yang ada datanya. Tabel yang field-nya tidak ada di-skip dengan ?? ''.
 */
function ambilDariRalan($no_rawat) {
    $tabelRalan = [
        'penilaian_medis_ralan',
        'penilaian_medis_ralan_anak',
        'penilaian_medis_ralan_bedah',
        'penilaian_medis_ralan_bedah_mulut',
        'penilaian_medis_ralan_gawat_darurat_psikiatri',
        'penilaian_medis_ralan_geriatri',
        'penilaian_medis_ralan_jantung',
        'penilaian_medis_ralan_kandungan',
        'penilaian_medis_ralan_kulitdankelamin',
        'penilaian_medis_ralan_mata',
        'penilaian_medis_ralan_neurologi',
        'penilaian_medis_ralan_orthopedi',
        'penilaian_medis_ralan_paru',
        'penilaian_medis_ralan_penyakit_dalam',
        'penilaian_medis_ralan_psikiatrik',
        'penilaian_medis_ralan_rehab_medik',
        'penilaian_medis_ralan_tht',
        'penilaian_medis_ralan_urologi'
    ];

    foreach ($tabelRalan as $tbl) {
        $q = bukaquery("SELECT * FROM $tbl WHERE no_rawat = '$no_rawat' LIMIT 1");
        if (!$q) continue;
        $row = mysqli_fetch_assoc($q);
        if ($row) {
            $row['_source'] = $tbl;
            return $row;
        }
    }
    return [];
}

/**
 * Ambil semua row SOAP untuk no_rawat ini, lalu compose:
 *   - keluhan_kronologis  : daftar keluhan (S) per tanggal
 *   - jalannya_kronologis : pemeriksaan (O) + evaluasi (E) per tanggal
 *   - rtl_terakhir        : RTL (P) dari kunjungan terakhir
 *
 * Filter berdasarkan kolom `nip` = kd_dokter login, karena 1 no_rawat
 * bisa diisi banyak dokter/perawat — hanya ambil milik user yg login.
 */
function ambilDariSOAP($no_rawat, $kd_dokter) {
    $q = bukaquery("SELECT tgl_perawatan, jam_rawat, keluhan, pemeriksaan, penilaian, rtl, instruksi, evaluasi
                    FROM pemeriksaan_ralan
                    WHERE no_rawat = '$no_rawat' AND nip = '$kd_dokter'
                    ORDER BY tgl_perawatan ASC, jam_rawat ASC");

    $result = ['keluhan_kronologis' => '', 'jalannya_kronologis' => '', 'rtl_terakhir' => ''];
    if (!$q) return $result;

    $keluhanList  = [];
    $jalannyaList = [];
    $rtlTerakhir  = '';

    while ($row = mysqli_fetch_assoc($q)) {
        $tgl = date('d-m-Y', strtotime($row['tgl_perawatan'])) . ' ' . substr($row['jam_rawat'], 0, 5);

        if (!empty(trim($row['keluhan'] ?? ''))) {
            $keluhanList[] = "[$tgl]\n" . trim($row['keluhan']);
        }

        $jalannyaParts = [];
        if (!empty(trim($row['pemeriksaan'] ?? ''))) $jalannyaParts[] = "Pemeriksaan: " . trim($row['pemeriksaan']);
        if (!empty(trim($row['evaluasi'] ?? '')))    $jalannyaParts[] = "Evaluasi: "    . trim($row['evaluasi']);
        if (!empty($jalannyaParts)) {
            $jalannyaList[] = "[$tgl]\n" . implode("\n", $jalannyaParts);
        }

        if (!empty(trim($row['rtl'] ?? ''))) {
            $rtlTerakhir = trim($row['rtl']);
        }
    }

    if (!empty($keluhanList))  $result['keluhan_kronologis']  = implode("\n\n", $keluhanList);
    if (!empty($jalannyaList)) $result['jalannya_kronologis'] = implode("\n\n", $jalannyaList);
    $result['rtl_terakhir'] = $rtlTerakhir;

    return $result;
}

/**
 * Ambil hasil radiologi dari tabel hasil_radiologi.
 * Multi-row per no_rawat (per pemeriksaan).
 * Format: per tanggal periksa, isi field `hasil` (narasi).
 */
function ambilDariRadiologi($no_rawat) {
    $q = bukaquery("SELECT tgl_periksa, jam, hasil
                    FROM hasil_radiologi
                    WHERE no_rawat = '$no_rawat' AND TRIM(hasil) != ''
                    ORDER BY tgl_periksa ASC, jam ASC");
    if (!$q) return '';

    $list = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $tgl = date('d-m-Y', strtotime($row['tgl_periksa'])) . ' ' . substr($row['jam'], 0, 5);
        $list[] = "[$tgl]\n" . trim($row['hasil']);
    }
    return implode("\n\n", $list);
}

/**
 * Ambil hasil lab dari tabel detail_periksa_lab + JOIN template_laboratorium.
 * Multi-row per no_rawat (per item lab).
 * Format: per tanggal periksa, daftar item dengan nilai + rujukan + keterangan.
 */
function ambilDariLab($no_rawat) {
    $q = bukaquery("SELECT dpl.tgl_periksa, dpl.jam, dpl.nilai, dpl.nilai_rujukan, dpl.keterangan,
                           tl.Pemeriksaan, tl.satuan
                    FROM detail_periksa_lab dpl
                    LEFT JOIN template_laboratorium tl ON dpl.id_template = tl.id_template
                    WHERE dpl.no_rawat = '$no_rawat'
                    ORDER BY dpl.tgl_periksa ASC, dpl.jam ASC, tl.urut ASC");
    if (!$q) return '';

    // Group by tanggal+jam
    $grouped = [];
    while ($row = mysqli_fetch_assoc($q)) {
        $key = $row['tgl_periksa'] . ' ' . substr($row['jam'], 0, 5);
        if (!isset($grouped[$key])) $grouped[$key] = [];

        $nama   = trim($row['Pemeriksaan'] ?? '') ?: '(Tanpa nama)';
        $nilai  = trim($row['nilai'] ?? '');
        $satuan = trim($row['satuan'] ?? '');
        $ruj    = trim($row['nilai_rujukan'] ?? '');
        $ket    = trim($row['keterangan'] ?? '');

        $line = "- $nama: $nilai" . ($satuan ? " $satuan" : '');
        if ($ruj) $line .= " (rujukan: $ruj)";
        if ($ket) $line .= " [$ket]";

        $grouped[$key][] = $line;
    }

    $list = [];
    foreach ($grouped as $key => $items) {
        $tgl = date('d-m-Y', strtotime(substr($key, 0, 10))) . ' ' . substr($key, 11);
        $list[] = "[$tgl]\n" . implode("\n", $items);
    }
    return implode("\n\n", $list);
}

/**
 * Format keluhan utama dengan label section.
 */
function formatKeluhan($keluhan_utama, $rps, $rpd) {
    $parts = [];
    if (!empty(trim($keluhan_utama))) $parts[] = "Keluhan Utama:\n" . trim($keluhan_utama);
    if (!empty(trim($rps)))           $parts[] = "Riwayat Penyakit Sekarang:\n" . trim($rps);
    if (!empty(trim($rpd)))           $parts[] = "Riwayat Penyakit Dahulu:\n" . trim($rpd);
    return implode("\n\n", $parts);
}

/**
 * Konversi nama tabel ralan ke label yang user-friendly.
 */
function labelRalan($tbl) {
    $map = [
        'penilaian_medis_ralan'                          => 'Poli Umum',
        'penilaian_medis_ralan_anak'                     => 'Poli Anak',
        'penilaian_medis_ralan_bedah'                    => 'Poli Bedah',
        'penilaian_medis_ralan_bedah_mulut'              => 'Poli Bedah Mulut',
        'penilaian_medis_ralan_gawat_darurat_psikiatri'  => 'Poli Gadar Psikiatri',
        'penilaian_medis_ralan_geriatri'                 => 'Poli Geriatri',
        'penilaian_medis_ralan_jantung'                  => 'Poli Jantung',
        'penilaian_medis_ralan_kandungan'                => 'Poli Kandungan',
        'penilaian_medis_ralan_kulitdankelamin'          => 'Poli Kulit & Kelamin',
        'penilaian_medis_ralan_mata'                     => 'Poli Mata',
        'penilaian_medis_ralan_neurologi'                => 'Poli Neurologi',
        'penilaian_medis_ralan_orthopedi'                => 'Poli Orthopedi',
        'penilaian_medis_ralan_paru'                     => 'Poli Paru',
        'penilaian_medis_ralan_penyakit_dalam'           => 'Poli Penyakit Dalam',
        'penilaian_medis_ralan_psikiatrik'               => 'Poli Psikiatri',
        'penilaian_medis_ralan_rehab_medik'              => 'Poli Rehab Medik',
        'penilaian_medis_ralan_tht'                      => 'Poli THT',
        'penilaian_medis_ralan_urologi'                  => 'Poli Urologi'
    ];
    return $map[$tbl] ?? $tbl;
}
