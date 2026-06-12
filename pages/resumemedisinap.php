<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
define('BASE_URL', APP_BASE_URL);

// Decrypt parameter dari URL
$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';

$no_rawat = '';
$no_rkm_medis = '';

if(!empty($encrypted_norawat)) {
    $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
}

if(!empty($encrypted_norm)) {
    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');
}

// Ambil data pasien rawat inap
$queryPasien = bukaquery("SELECT 
                            rp.no_rawat,
                            rp.no_rkm_medis,
                            rp.tgl_registrasi,
                            rp.jam_reg,
                            rp.kd_pj,
                            p.nm_pasien,
                            p.jk,
                            p.tmp_lahir,
                            p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                            d.nm_dokter,
                            d.kd_dokter,
                            pn.png_jawab
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                        LEFT JOIN penjab pn ON rp.kd_pj = pn.kd_pj
                        WHERE rp.no_rawat = '$no_rawat'");

$rsPasien = mysqli_fetch_array($queryPasien);

if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.location.href='?act=Pasien';</script>";
    exit;
}

// Ambil data kamar rawat inap (masuk pertama)
$queryKamar = bukaquery("SELECT 
                            ki.tgl_masuk, ki.jam_masuk,
                            ki.tgl_keluar, ki.jam_keluar,
                            ki.diagnosa_awal, ki.diagnosa_akhir
                         FROM kamar_inap ki
                         WHERE ki.no_rawat = '$no_rawat'
                         ORDER BY ki.tgl_masuk ASC, ki.jam_masuk ASC
                         LIMIT 1");
$rsKamar = mysqli_fetch_assoc($queryKamar);

// Ambil tanggal keluar terakhir
$queryKamarKeluar = bukaquery("SELECT ki.tgl_keluar, ki.jam_keluar
                               FROM kamar_inap ki
                               WHERE ki.no_rawat = '$no_rawat' AND ki.tgl_keluar != '0000-00-00'
                               ORDER BY ki.tgl_keluar DESC, ki.jam_keluar DESC
                               LIMIT 1");
$rsKamarKeluar = mysqli_fetch_assoc($queryKamarKeluar);

// Ambil keluhan utama dari penilaian medis IGD
$queryMedisIGD = bukaquery_safe("SELECT keluhan_utama, rps FROM penilaian_medis_igd WHERE no_rawat = '$no_rawat' LIMIT 1");
$rsMedisIGD = mysqli_fetch_assoc($queryMedisIGD);

// Ambil keluhan utama dari penilaian medis Ranap
$queryMedisRanap = bukaquery_safe("SELECT keluhan_utama, rps, rpd, rpk,
                                    keadaan, gcs, kesadaran, td, nadi, rr, suhu, spo, bb, tb,
                                    kepala, mata, gigi, tht, thoraks, jantung, paru, abdomen, genital, ekstremitas, kulit, ket_fisik
                             FROM penilaian_medis_ranap WHERE no_rawat = '$no_rawat' LIMIT 1");
$rsMedisRanap = mysqli_fetch_assoc($queryMedisRanap);

// ✅ Ambil data diagnosa dari diagnosa_pasien (order by prioritas)
$queryDiagnosa = bukaquery_safe("SELECT 
                                dp.kd_penyakit,
                                dp.prioritas,
                                dp.status,
                                dp.status_penyakit,
                                COALESCE(p.nm_penyakit, dp.kd_penyakit) as nm_penyakit
                            FROM diagnosa_pasien dp
                            LEFT JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
                            WHERE dp.no_rawat = '$no_rawat'
                            AND dp.status = 'Ranap'
                            ORDER BY dp.prioritas ASC");

$dataDiagnosa = array();
while($rsDiagnosa = mysqli_fetch_assoc($queryDiagnosa)) {
    $dataDiagnosa[] = $rsDiagnosa;
}

// Debug: Log jumlah data diagnosa
error_log("DEBUG Resume: Found " . count($dataDiagnosa) . " diagnosa for no_rawat: $no_rawat");

// Helper: cek kolom yang ada di tabel, return string SELECT aman
function getExistingColumns($table, $columns, $alias = '') {
    $prefix = $alias ? $alias . '.' : '';
    $existing = [];
    foreach($columns as $col) {
        $cek = @bukaquery_safe("SELECT COUNT(*) as ada FROM information_schema.COLUMNS 
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$col'");
        if($cek) {
            $row = mysqli_fetch_assoc($cek);
            if($row && isset($row['ada']) && $row['ada'] > 0) {
                $existing[] = $prefix . $col;
            }
        }
    }
    return $existing;
}

// ✅ Ambil data prosedur dari prosedur_pasien (order by prioritas)
// Kolom yang mungkin tidak ada di DB lama
$prosedurExtraCols = getExistingColumns('prosedur_pasien', ['jumlah'], 'pp');
$prosedurExtraSelect = count($prosedurExtraCols) > 0 ? ', ' . implode(', ', $prosedurExtraCols) : '';

$queryProsedur = bukaquery_safe("SELECT 
                                pp.kode,
                                pp.prioritas,
                                pp.status
                                $prosedurExtraSelect,
                                COALESCE(icd.deskripsi_panjang, icd.deskripsi_pendek, pp.kode) as nama_prosedur
                            FROM prosedur_pasien pp
                            LEFT JOIN icd9 icd ON pp.kode = icd.kode
                            WHERE pp.no_rawat = '$no_rawat'
                            AND pp.status = 'Ranap'
                            ORDER BY pp.prioritas ASC");

$dataProsedur = array();
while($rsProsedur = mysqli_fetch_assoc($queryProsedur)) {
    $dataProsedur[] = $rsProsedur;
}

// Debug: Log jumlah data prosedur
error_log("DEBUG Resume: Found " . count($dataProsedur) . " prosedur for no_rawat: $no_rawat");

// Cek apakah ada data
$adaDataIGD = ($rsMedisIGD) ? true : false;
$adaDataRanap = ($rsMedisRanap) ? true : false;

// Siapkan data sumber untuk checkbox
$sumberKeluhan = array(
    'igd' => array(
        'label' => 'Medis IGD',
        'data' => ($rsMedisIGD && !empty($rsMedisIGD['keluhan_utama'])) ? $rsMedisIGD['keluhan_utama'] : ''
    ),
    'ranap' => array(
        'label' => 'Medis Ranap (Keluhan Utama)',
        'data' => ($rsMedisRanap && !empty($rsMedisRanap['keluhan_utama'])) ? $rsMedisRanap['keluhan_utama'] : ''
    ),
    'ranap_rps' => array(
        'label' => 'Medis Ranap (RPS)',
        'data' => ($rsMedisRanap && !empty($rsMedisRanap['rps'])) ? $rsMedisRanap['rps'] : ''
    )
);

// Fungsi untuk format pemeriksaan fisik dari Medis Ranap
function formatPemeriksaanFisikRanap($data) {
    if (!$data) return '';
    
    $parts = array();
    
    // Keadaan Umum
    $keadaan = array();
    if (!empty($data['keadaan'])) $keadaan[] = "Keadaan: " . $data['keadaan'];
    if (!empty($data['kesadaran'])) $keadaan[] = "Kesadaran: " . $data['kesadaran'];
    if (!empty($data['gcs'])) $keadaan[] = "GCS: " . $data['gcs'];
    if (count($keadaan) > 0) {
        $parts[] = "Keadaan Umum:\n" . implode(" | ", $keadaan);
    }
    
    // Tanda Vital
    $ttv = array();
    if (!empty($data['td'])) $ttv[] = "TD: " . $data['td'] . " mmHg";
    if (!empty($data['nadi'])) $ttv[] = "Nadi: " . $data['nadi'] . "x/menit";
    if (!empty($data['rr'])) $ttv[] = "RR: " . $data['rr'] . "x/menit";
    if (!empty($data['suhu'])) $ttv[] = "Suhu: " . $data['suhu'] . "°C";
    if (!empty($data['spo'])) $ttv[] = "SpO2: " . $data['spo'] . "%";
    if (!empty($data['bb'])) $ttv[] = "BB: " . $data['bb'] . " kg";
    if (!empty($data['tb'])) $ttv[] = "TB: " . $data['tb'] . " cm";
    if (count($ttv) > 0) {
        $parts[] = "Tanda Vital:\n" . implode(" | ", $ttv);
    }
    
    // Status Lokalis
    $lokalis = array();
    if (!empty($data['kepala']) && $data['kepala'] != 'Tidak Diperiksa') $lokalis[] = "Kepala: " . $data['kepala'];
    if (!empty($data['mata']) && $data['mata'] != 'Tidak Diperiksa') $lokalis[] = "Mata: " . $data['mata'];
    if (!empty($data['gigi']) && $data['gigi'] != 'Tidak Diperiksa') $lokalis[] = "Gigi: " . $data['gigi'];
    if (!empty($data['tht']) && $data['tht'] != 'Tidak Diperiksa') $lokalis[] = "THT: " . $data['tht'];
    if (!empty($data['thoraks']) && $data['thoraks'] != 'Tidak Diperiksa') $lokalis[] = "Thoraks: " . $data['thoraks'];
    if (!empty($data['jantung']) && $data['jantung'] != 'Tidak Diperiksa') $lokalis[] = "Jantung: " . $data['jantung'];
    if (!empty($data['paru']) && $data['paru'] != 'Tidak Diperiksa') $lokalis[] = "Paru: " . $data['paru'];
    if (!empty($data['abdomen']) && $data['abdomen'] != 'Tidak Diperiksa') $lokalis[] = "Abdomen: " . $data['abdomen'];
    if (!empty($data['genital']) && $data['genital'] != 'Tidak Diperiksa') $lokalis[] = "Genital: " . $data['genital'];
    if (!empty($data['ekstremitas']) && $data['ekstremitas'] != 'Tidak Diperiksa') $lokalis[] = "Ekstremitas: " . $data['ekstremitas'];
    if (!empty($data['kulit']) && $data['kulit'] != 'Tidak Diperiksa') $lokalis[] = "Kulit: " . $data['kulit'];
    if (count($lokalis) > 0) {
        $parts[] = "Status Lokalis:\n" . implode(" | ", $lokalis);
    }
    
    if (!empty($data['ket_fisik'])) {
        $parts[] = "Keterangan:\n" . $data['ket_fisik'];
    }
    
    return implode("\n\n", $parts);
}

// Fungsi untuk format pemeriksaan fisik dari SOAP
function formatPemeriksaanFisikSOAP($soap) {
    if (!$soap) return '';
    
    $parts = array();
    
    // Keadaan Umum
    $keadaan = array();
    if (!empty($soap['kesadaran'])) $keadaan[] = "Kesadaran: " . $soap['kesadaran'];
    if (!empty($soap['gcs'])) $keadaan[] = "GCS: " . $soap['gcs'];
    if (count($keadaan) > 0) {
        $parts[] = "Keadaan Umum:\n" . implode(" | ", $keadaan);
    }
    
    // Tanda Vital
    $ttv = array();
    if (!empty($soap['tensi'])) $ttv[] = "TD: " . $soap['tensi'] . " mmHg";
    if (!empty($soap['nadi'])) $ttv[] = "Nadi: " . $soap['nadi'] . "x/menit";
    if (!empty($soap['respirasi'])) $ttv[] = "RR: " . $soap['respirasi'] . "x/menit";
    if (!empty($soap['suhu_tubuh'])) $ttv[] = "Suhu: " . $soap['suhu_tubuh'] . "°C";
    if (!empty($soap['spo2'])) $ttv[] = "SpO2: " . $soap['spo2'] . "%";
    if (!empty($soap['berat'])) $ttv[] = "BB: " . $soap['berat'] . " kg";
    if (!empty($soap['tinggi'])) $ttv[] = "TB: " . $soap['tinggi'] . " cm";
    if (count($ttv) > 0) {
        $parts[] = "Tanda Vital:\n" . implode(" | ", $ttv);
    }
    
    return implode("\n\n", $parts);
}

// Fungsi untuk format Jalannya Penyakit dari SOAP (narasi kronologis - hanya pertama dan terakhir)
function formatJalannyaPenyakitSOAP($dataSOAP, $tgl_masuk) {
    if (!$dataSOAP || count($dataSOAP) == 0) return '';
    
    // Urutkan dari yang terlama ke terbaru untuk narasi kronologis
    $sortedSOAP = $dataSOAP;
    usort($sortedSOAP, function($a, $b) {
        $dateA = strtotime($a['tgl_perawatan'] . ' ' . $a['jam_rawat']);
        $dateB = strtotime($b['tgl_perawatan'] . ' ' . $b['jam_rawat']);
        return $dateA - $dateB;
    });
    
    // Ambil hanya data pertama dan terakhir
    $selectedSOAP = array();
    if (count($sortedSOAP) == 1) {
        // Jika hanya 1 data, ambil itu saja
        $selectedSOAP[] = $sortedSOAP[0];
    } else {
        // Ambil pertama dan terakhir
        $selectedSOAP[] = $sortedSOAP[0]; // Pertama (awal masuk)
        $selectedSOAP[] = $sortedSOAP[count($sortedSOAP) - 1]; // Terakhir (sebelum pulang)
    }
    
    $tglMasuk = strtotime($tgl_masuk);
    $parts = array();
    
    foreach ($selectedSOAP as $idx => $soap) {
        $tglPerawatan = strtotime($soap['tgl_perawatan']);
        $hariKe = floor(($tglPerawatan - $tglMasuk) / 86400) + 1;
        $tglFormatted = date('d-m-Y', $tglPerawatan);
        
        $narasi = array();
        
        // Hari ke berapa
        if ($idx == 0) {
            $narasiHeader = "Pasien dirawat sejak tanggal " . $tglFormatted;
        } else {
            $narasiHeader = "Hari ke-" . $hariKe . " perawatan (" . $tglFormatted . ")";
        }
        
        // Keluhan (Subjective)
        if (!empty($soap['keluhan'])) {
            if ($idx == 0) {
                $narasi[] = "dengan keluhan " . strtolower(trim($soap['keluhan']));
            } else {
                $narasi[] = "keluhan: " . strtolower(trim($soap['keluhan']));
            }
        }
        
        // Pemeriksaan (Objective)
        if (!empty($soap['pemeriksaan'])) {
            $narasi[] = "Pemeriksaan: " . trim($soap['pemeriksaan']);
        }
        
        // Penilaian (Assessment)
        if (!empty($soap['penilaian'])) {
            $narasi[] = "Penilaian: " . trim($soap['penilaian']);
        }
        
        // RTL/Planning
        if (!empty($soap['rtl'])) {
            $narasi[] = "Rencana: " . trim($soap['rtl']);
        }
        
        // Instruksi
        if (!empty($soap['instruksi'])) {
            $narasi[] = "Instruksi: " . trim($soap['instruksi']);
        }
        
        // Evaluasi
        if (!empty($soap['evaluasi'])) {
            $narasi[] = "Evaluasi: " . trim($soap['evaluasi']);
        }
        
        if (count($narasi) > 0) {
            $parts[] = $narasiHeader . " " . implode(". ", $narasi) . ".";
        }
    }
    
    return implode("\n\n", $parts);
}

// Format hasil radiologi
function formatHasilRadiologi($dataRadiologi) {
    if (!$dataRadiologi || count($dataRadiologi) == 0) return '';
    
    $parts = array();
    foreach ($dataRadiologi as $rad) {
        $tglFormatted = date('d-m-Y', strtotime($rad['tgl_periksa']));
        $hasil = !empty($rad['hasil']) ? trim($rad['hasil']) : '-';
        
        $parts[] = "Radiologi (" . $tglFormatted . "):\n" . $hasil;
    }
    
    return implode("\n\n", $parts);
}

// Format hasil lab (kelompokkan berdasarkan tanggal)
function formatHasilLab($dataLab, $kritisOnly = false) {
    if (!$dataLab || count($dataLab) == 0) return '';
    
    // Filter jika kritis only
    if ($kritisOnly) {
        $dataLab = array_filter($dataLab, function($lab) {
            $ket = strtoupper(trim($lab['keterangan']));
            return ($ket == 'H' || $ket == 'L');
        });
        if (count($dataLab) == 0) return '';
    }
    
    // Kelompokkan berdasarkan tanggal
    $grouped = array();
    foreach ($dataLab as $lab) {
        $tgl = $lab['tgl_periksa'];
        if (!isset($grouped[$tgl])) {
            $grouped[$tgl] = array();
        }
        $grouped[$tgl][] = $lab;
    }
    
    // Format output
    $parts = array();
    foreach ($grouped as $tgl => $items) {
        $tglFormatted = date('d-m-Y', strtotime($tgl));
        $header = $kritisOnly ? "Hasil Lab Kritis (" . $tglFormatted . "):" : "(" . $tglFormatted . "):";
        
        $labItems = array();
        foreach ($items as $item) {
            $nmPemeriksaan = !empty($item['nm_pemeriksaan']) ? $item['nm_pemeriksaan'] : '-';
            $nilai = !empty($item['nilai']) ? $item['nilai'] : '-';
            $satuan = !empty($item['satuan']) ? $item['satuan'] : '';
            $ket = strtoupper(trim($item['keterangan']));
            
            // Tambahkan indikator kritis
            $kritisLabel = '';
            if ($ket == 'H') {
                $kritisLabel = ' [TINGGI]';
            } else if ($ket == 'L') {
                $kritisLabel = ' [RENDAH]';
            }
            
            $labItems[] = $nmPemeriksaan . ": " . $nilai . ($satuan ? " " . $satuan : "") . $kritisLabel;
        }
        
        $parts[] = $header . " " . implode(", ", $labItems);
    }
    
    return implode("\n\n", $parts);
}

$pemeriksaanFisikRanap = formatPemeriksaanFisikRanap($rsMedisRanap);

// Ambil kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Ambil data SOAP dari pemeriksaan_ranap (hanya milik dokter login)
$querySOAP = bukaquery_safe("SELECT pr.tgl_perawatan, pr.jam_rawat, pr.keluhan, pr.pemeriksaan, pr.penilaian, pr.rtl, pr.instruksi, pr.evaluasi,
                               pr.suhu_tubuh, pr.tensi, pr.nadi, pr.respirasi, pr.tinggi, pr.berat, pr.spo2, pr.gcs, pr.kesadaran
                        FROM pemeriksaan_ranap pr
                        WHERE pr.no_rawat = '$no_rawat' AND pr.nip = '$kd_dokter_login'
                        ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC");
$dataSOAP = array();
while($rsSOAP = mysqli_fetch_assoc($querySOAP)) {
    $dataSOAP[] = $rsSOAP;
}
$adaDataSOAP = (count($dataSOAP) > 0) ? true : false;

// Data Jalannya Penyakit dari SOAP (narasi kronologis)
$tgl_masuk_untuk_narasi = $rsKamar ? $rsKamar['tgl_masuk'] : $rsPasien['tgl_registrasi'];
$jalannyaPenyakitSOAP = formatJalannyaPenyakitSOAP($dataSOAP, $tgl_masuk_untuk_narasi);

// Ambil data hasil radiologi
$queryRadiologi = bukaquery_safe("SELECT tgl_periksa, jam, hasil
                             FROM hasil_radiologi
                             WHERE no_rawat = '$no_rawat'
                             ORDER BY tgl_periksa DESC, jam DESC");
$dataRadiologi = array();
while($rsRadiologi = mysqli_fetch_assoc($queryRadiologi)) {
    $dataRadiologi[] = $rsRadiologi;
}
$adaDataRadiologi = (count($dataRadiologi) > 0) ? true : false;
$hasilRadiologiFormatted = formatHasilRadiologi($dataRadiologi);

// Ambil data hasil laboratorium
$queryLab = bukaquery_safe("SELECT dpl.tgl_periksa, dpl.jam, dpl.id_template, dpl.nilai, dpl.keterangan,
                              tl.Pemeriksaan as nm_pemeriksaan, tl.satuan
                       FROM detail_periksa_lab dpl
                       LEFT JOIN template_laboratorium tl ON dpl.id_template = tl.id_template
                       WHERE dpl.no_rawat = '$no_rawat'
                       ORDER BY dpl.tgl_periksa DESC, dpl.jam DESC, tl.Pemeriksaan ASC");
$dataLab = array();
$dataLabKritis = array();
while($rsLab = mysqli_fetch_assoc($queryLab)) {
    $dataLab[] = $rsLab;
    // Cek jika kritis (H, h, L, l)
    $ket = strtoupper(trim($rsLab['keterangan']));
    if ($ket == 'H' || $ket == 'L') {
        $dataLabKritis[] = $rsLab;
    }
}
$adaDataLab = (count($dataLab) > 0) ? true : false;
$adaDataLabKritis = (count($dataLabKritis) > 0) ? true : false;
$hasilLabFormatted = formatHasilLab($dataLab, false);
$hasilLabKritisFormatted = formatHasilLab($dataLab, true);

// Ambil data tindakan dari rawat_inap_dr, rawat_inap_drpr, dan rawat_jl_dr (semua dokter)
$queryTindakan = bukaquery_safe("SELECT DISTINCT kd_jenis_prw, nm_perawatan FROM (
                                SELECT rid.kd_jenis_prw, jpi.nm_perawatan
                                FROM rawat_inap_dr rid
                                LEFT JOIN jns_perawatan_inap jpi ON rid.kd_jenis_prw = jpi.kd_jenis_prw
                                WHERE rid.no_rawat = '$no_rawat'
                                UNION
                                SELECT ridp.kd_jenis_prw, jpi.nm_perawatan
                                FROM rawat_inap_drpr ridp
                                LEFT JOIN jns_perawatan_inap jpi ON ridp.kd_jenis_prw = jpi.kd_jenis_prw
                                WHERE ridp.no_rawat = '$no_rawat'
                                UNION
                                SELECT rjd.kd_jenis_prw, jp.nm_perawatan
                                FROM rawat_jl_dr rjd
                                LEFT JOIN jns_perawatan jp ON rjd.kd_jenis_prw = jp.kd_jenis_prw
                                WHERE rjd.no_rawat = '$no_rawat'
                            ) as tindakan_gabungan
                            WHERE nm_perawatan IS NOT NULL AND nm_perawatan != ''
                            ORDER BY nm_perawatan ASC");
$dataTindakan = array();
while($rsTindakan = mysqli_fetch_assoc($queryTindakan)) {
    if (!empty($rsTindakan['nm_perawatan'])) {
        $dataTindakan[] = $rsTindakan['nm_perawatan'];
    }
}
$adaDataTindakan = (count($dataTindakan) > 0) ? true : false;
$tindakanFormatted = (count($dataTindakan) > 0) ? "Tindakan: " . implode(", ", $dataTindakan) : '';

// Ambil data operasi
$queryOperasi = bukaquery_safe("SELECT o.tgl_operasi, o.kode_paket, po.nm_perawatan
                           FROM operasi o
                           LEFT JOIN paket_operasi po ON o.kode_paket = po.kode_paket
                           WHERE o.no_rawat = '$no_rawat'
                           ORDER BY o.tgl_operasi DESC");
$dataOperasi = array();
while($rsOperasi = mysqli_fetch_assoc($queryOperasi)) {
    $dataOperasi[] = $rsOperasi;
}
$adaDataOperasi = (count($dataOperasi) > 0) ? true : false;

// Ambil data obat dari detail_pemberian_obat (distinct kode_brng)
$queryObat = bukaquery_safe("SELECT DISTINCT dpo.kode_brng, db.nama_brng
                        FROM detail_pemberian_obat dpo
                        LEFT JOIN databarang db ON dpo.kode_brng = db.kode_brng
                        WHERE dpo.no_rawat = '$no_rawat'
                        ORDER BY db.nama_brng ASC");
$dataObat = array();
while($rsObat = mysqli_fetch_assoc($queryObat)) {
    if (!empty($rsObat['nama_brng'])) {
        $dataObat[] = $rsObat['nama_brng'];
    }
}
$adaDataObat = (count($dataObat) > 0) ? true : false;
$obatFormatted = (count($dataObat) > 0) ? implode(", ", $dataObat) : '';

// Ambil data diet dari detail_beri_diet (distinct kd_diet)
$queryDiet = bukaquery_safe("SELECT DISTINCT dbd.kd_diet, d.nama_diet
                        FROM detail_beri_diet dbd
                        LEFT JOIN diet d ON dbd.kd_diet = d.kd_diet
                        WHERE dbd.no_rawat = '$no_rawat'
                        ORDER BY d.nama_diet ASC");
$dataDiet = array();
while($rsDiet = mysqli_fetch_assoc($queryDiet)) {
    if (!empty($rsDiet['nama_diet'])) {
        $dataDiet[] = $rsDiet['nama_diet'];
    }
}
$adaDataDiet = (count($dataDiet) > 0) ? true : false;
$dietFormatted = (count($dataDiet) > 0) ? implode(", ", $dataDiet) : '';

// Ambil data obat pulang dari resep_pulang
$queryObatPulang = bukaquery_safe("SELECT rp.kode_brng, rp.jml_barang, rp.dosis, db.nama_brng
                              FROM resep_pulang rp
                              LEFT JOIN databarang db ON rp.kode_brng = db.kode_brng
                              WHERE rp.no_rawat = '$no_rawat'
                              ORDER BY db.nama_brng ASC");
$dataObatPulang = array();
while($rsObatPulang = mysqli_fetch_assoc($queryObatPulang)) {
    if (!empty($rsObatPulang['nama_brng'])) {
        $dataObatPulang[] = $rsObatPulang;
    }
}
$adaDataObatPulang = (count($dataObatPulang) > 0) ? true : false;

// Format obat pulang
$obatPulangFormatted = '';
if (count($dataObatPulang) > 0) {
    $obatItems = array();
    foreach ($dataObatPulang as $op) {
        $namaObat = $op['nama_brng'];
        $jumlah = $op['jml_barang'];
        $dosis = !empty($op['dosis']) ? $op['dosis'] : '';
        
        $item = $namaObat . " (" . $jumlah . ")";
        if (!empty($dosis)) {
            $item .= " - " . $dosis;
        }
        $obatItems[] = $item;
    }
    $obatPulangFormatted = implode("\n", $obatItems);
}

// Cek apakah sudah ada data resume
// Cek tabel pegawai dulu — di server live mungkin tidak ada atau kolom 'nik' beda
$adaTabelPegawai = in_array('pegawai', $GLOBALS['existing_tables']);
$joinPegawai = $adaTabelPegawai ? "LEFT JOIN pegawai p ON rpr.kd_dokter = p.nik" : "";
$selectPegawai = $adaTabelPegawai ? ", p.nama as nama_dokter_resume" : ", '' as nama_dokter_resume";

$queryCheck = bukaquery_safe("SELECT rpr.* $selectPegawai
                         FROM resume_pasien_ranap rpr
                         $joinPegawai
                         WHERE rpr.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_assoc($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_dokter_resume = ($rsCheck && !empty($rsCheck['nama_dokter_resume'])) ? $rsCheck['nama_dokter_resume'] : '';

// Data default
$data = array(
    'no_rawat' => $no_rawat,
    'kd_dokter' => $kd_dokter_login,
    'diagnosa_awal' => $rsKamar['diagnosa_awal'] ?? '-',
    'alasan' => '',
    'keluhan_utama' => '',
    'pemeriksaan_fisik' => '',
    'jalannya_penyakit' => '',
    'pemeriksaan_penunjang' => '',
    'hasil_laborat' => '',
    'tindakan_dan_operasi' => '',
    'obat_di_rs' => '',
    // ✅ Auto-populate diagnosa dari diagnosa_pasien (jika belum ada data tersimpan)
    'diagnosa_utama' => isset($dataDiagnosa[0]) ? $dataDiagnosa[0]['nm_penyakit'] : '',
    'kd_diagnosa_utama' => isset($dataDiagnosa[0]) ? $dataDiagnosa[0]['kd_penyakit'] : '',
    'diagnosa_sekunder' => isset($dataDiagnosa[1]) ? $dataDiagnosa[1]['nm_penyakit'] : '',
    'kd_diagnosa_sekunder' => isset($dataDiagnosa[1]) ? $dataDiagnosa[1]['kd_penyakit'] : '',
    'diagnosa_sekunder2' => isset($dataDiagnosa[2]) ? $dataDiagnosa[2]['nm_penyakit'] : '',
    'kd_diagnosa_sekunder2' => isset($dataDiagnosa[2]) ? $dataDiagnosa[2]['kd_penyakit'] : '',
    'diagnosa_sekunder3' => isset($dataDiagnosa[3]) ? $dataDiagnosa[3]['nm_penyakit'] : '',
    'kd_diagnosa_sekunder3' => isset($dataDiagnosa[3]) ? $dataDiagnosa[3]['kd_penyakit'] : '',
    'diagnosa_sekunder4' => isset($dataDiagnosa[4]) ? $dataDiagnosa[4]['nm_penyakit'] : '',
    'kd_diagnosa_sekunder4' => isset($dataDiagnosa[4]) ? $dataDiagnosa[4]['kd_penyakit'] : '',
    // ✅ Auto-populate prosedur dari prosedur_pasien (jika belum ada data tersimpan)
    'prosedur_utama' => isset($dataProsedur[0]) ? $dataProsedur[0]['nama_prosedur'] : '',
    'kd_prosedur_utama' => isset($dataProsedur[0]) ? $dataProsedur[0]['kode'] : '',
    'prosedur_sekunder' => isset($dataProsedur[1]) ? $dataProsedur[1]['nama_prosedur'] : '',
    'kd_prosedur_sekunder' => isset($dataProsedur[1]) ? $dataProsedur[1]['kode'] : '',
    'prosedur_sekunder2' => isset($dataProsedur[2]) ? $dataProsedur[2]['nama_prosedur'] : '',
    'kd_prosedur_sekunder2' => isset($dataProsedur[2]) ? $dataProsedur[2]['kode'] : '',
    'prosedur_sekunder3' => isset($dataProsedur[3]) ? $dataProsedur[3]['nama_prosedur'] : '',
    'kd_prosedur_sekunder3' => isset($dataProsedur[3]) ? $dataProsedur[3]['kode'] : '',
    'alergi' => '',
    'diet' => '',
    'lab_belum' => '',
    'edukasi' => '',
    'cara_keluar' => 'Atas Izin Dokter',
    'ket_keluar' => '',
    'keadaan' => 'Membaik',
    'ket_keadaan' => '',
    'dilanjutkan' => 'Kembali Ke RS',
    'ket_dilanjutkan' => '',
    'kontrol' => date('Y-m-d H:i:s', strtotime('+7 days')),
    'obat_pulang' => ''
);

// Debug: tampilkan data sebelum merge
error_log("DEBUG Resume BEFORE merge - diagnosa_utama: " . $data['diagnosa_utama']);
error_log("DEBUG Resume BEFORE merge - kd_diagnosa_utama: " . $data['kd_diagnosa_utama']);
error_log("DEBUG Resume BEFORE merge - prosedur_utama: " . $data['prosedur_utama']);

if($isEdit) {
    // ✅ Smart merge: jika field kosong di DB, gunakan auto-populate
    foreach($rsCheck as $key => $value) {
        // Jika value kosong/null DAN ada auto-populate, skip merge (biarkan auto-populate)
        if(empty($value) && !empty($data[$key])) {
            continue; // Skip, gunakan nilai auto-populate
        }
        // Otherwise, gunakan nilai dari DB
        $data[$key] = $value;
    }
    error_log("DEBUG Resume AFTER merge (isEdit=true) - diagnosa_utama: " . $data['diagnosa_utama']);
} else {
    error_log("DEBUG Resume: Mode NEW, using auto-populate");
}

// Debug: final values
error_log("DEBUG Resume FINAL - diagnosa_utama: " . $data['diagnosa_utama']);
error_log("DEBUG Resume FINAL - kd_diagnosa_utama: " . $data['kd_diagnosa_utama']);
error_log("DEBUG Resume FINAL - prosedur_utama: " . $data['prosedur_utama']);

$tgl_masuk = $rsKamar ? $rsKamar['tgl_masuk'] : $rsPasien['tgl_registrasi'];
$jam_masuk = $rsKamar ? $rsKamar['jam_masuk'] : $rsPasien['jam_reg'];
$tgl_keluar = $rsKamarKeluar ? $rsKamarKeluar['tgl_keluar'] : date('Y-m-d');
$jam_keluar = $rsKamarKeluar ? $rsKamarKeluar['jam_keluar'] : date('H:i:s');
?>

<!-- Load CSS -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template3.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Patient Header with Action Buttons -->
    <div class="patient-header">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i class="material-icons">assignment</i>
                        RESUME MEDIS RAWAT INAP
                        <?php if($isEdit): ?>
                        <span class="mode-badge mode-edit">✏️ EDIT</span>
                        <?php else: ?>
                        <span class="mode-badge mode-add">➕ NEW</span>
                        <?php endif; ?>
                    </h1>
                    
                    <!-- Compact Progress Bar -->
                    <div style="display: flex; align-items: center; gap: 10px; background: #f8f9fa; border-radius: 8px; padding: 8px 12px;">
                        <i class="material-icons" style="font-size: 18px; color: #6c757d;">assessment</i>
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <span style="font-size: 11px; color: #6c757d; font-weight: 500;">Kelengkapan</span>
                                <span id="progress-text" style="font-weight: bold; font-size: 14px; color: #6c757d;">0%</span>
                            </div>
                            <div style="width: 150px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                                <div id="progress-bar" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                            </div>
                        </div>
                        <span id="progress-status" style="font-size: 10px; color: #6c757d; white-space: nowrap;">(0/0)</span>
                    </div>
                </div>
                <div class="patient-info">
                    <div class="info-item">
                        <i class="material-icons">folder</i>
                        <strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?>
                    </div>
                    <div class="info-item">
                        <i class="material-icons">badge</i>
                        <strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?>
                    </div>
                    <div class="info-item">
                        <i class="material-icons">person</i>
                        <strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?>
                    </div>
                    <div class="info-item">
                        <i class="material-icons">cake</i>
                        <strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)
                    </div>
                    <?php if($isEdit && !empty($nama_dokter_resume)): ?>
                    <div class="info-item">
                        <i class="material-icons">person</i>
                        <strong>Diisi oleh:</strong> <?php echo $nama_dokter_resume; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- DEBUG INFO (hapus setelah fix) -->
            <!-- <?php if(count($dataDiagnosa) > 0 || count($dataProsedur) > 0): ?>
            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin-top: 10px; border-radius: 4px; font-size: 12px;">
                <strong>🔍 Debug Info:</strong><br>
                - Diagnosa found: <?php echo count($dataDiagnosa); ?><br>
                - Prosedur found: <?php echo count($dataProsedur); ?><br>
                - isEdit: <?php echo $isEdit ? 'TRUE' : 'FALSE'; ?><br>
                <?php if(count($dataDiagnosa) > 0): ?>
                    <br><strong>Diagnosa dari DB:</strong><br>
                    <?php foreach($dataDiagnosa as $idx => $d): ?>
                        [<?php echo $idx+1; ?>] Prioritas: <?php echo $d['prioritas']; ?>, Kode: <?php echo $d['kd_penyakit']; ?>, Nama: <?php echo $d['nm_penyakit']; ?><br>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if(count($dataProsedur) > 0): ?>
                    <br><strong>Prosedur dari DB:</strong><br>
                    <?php foreach($dataProsedur as $idx => $p): ?>
                        [<?php echo $idx+1; ?>] Prioritas: <?php echo $p['prioritas']; ?>, Kode: <?php echo $p['kode']; ?>, Nama: <?php echo $p['nama_prosedur']; ?><br>
                    <?php endforeach; ?>
                <?php endif; ?>
                <br><strong>Final $data values:</strong><br>
                - diagnosa_utama: "<?php echo htmlspecialchars($data['diagnosa_utama']); ?>"<br>
                - kd_diagnosa_utama: "<?php echo htmlspecialchars($data['kd_diagnosa_utama']); ?>"<br>
                - prosedur_utama: "<?php echo htmlspecialchars($data['prosedur_utama']); ?>"<br>
                - kd_prosedur_utama: "<?php echo htmlspecialchars($data['kd_prosedur_utama']); ?>"<br>
            </div>
            <?php endif; ?> -->
            
            <!-- Action Buttons di Header -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" class="btn-auto-fill" title="Isi otomatis semua data">
                    <i class="material-icons">flash_on</i> Auto
                </button>
                <button type="button" class="btn-kosongkan" title="Kosongkan field auto fill">
                    <i class="material-icons">backspace</i> Kosongkan
                </button>
                <button type="button" class="btn-rapikan-ai" title="Rapikan dengan AI">
                    <i class="material-icons">auto_fix_high</i> Rapikan AI
                </button>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formResume" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $data['kd_dokter']; ?>">
                
                <!-- I. DATA PERAWATAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">local_hospital</i>
                        <h2>I. DATA PERAWATAN</h2>
                    </div>

                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Tanggal Masuk</label>
                            <input type="text" value="<?php echo date('d/m/Y', strtotime($tgl_masuk)); ?>" readonly 
                                   style="background: #f5f5f5; cursor: not-allowed;">
                        </div>
                        <div class="form-group">
                            <label>Jam Masuk</label>
                            <input type="text" value="<?php echo $jam_masuk; ?>" readonly
                                   style="background: #f5f5f5; cursor: not-allowed;">
                        </div>
                        <div class="form-group">
                            <label>Diagnosa Awal Masuk</label>
                            <input type="text" name="diagnosa_awal"
                                   value="<?php echo htmlspecialchars($data['diagnosa_awal']); ?>">
                        </div>
                    </div>

                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Tanggal Keluar</label>
                            <input type="text" value="<?php echo date('d/m/Y', strtotime($tgl_keluar)); ?>" readonly
                                   style="background: #f5f5f5; cursor: not-allowed;">
                        </div>
                        <div class="form-group">
                            <label>Jam Keluar</label>
                            <input type="text" value="<?php echo $jam_keluar; ?>" readonly
                                   style="background: #f5f5f5; cursor: not-allowed;">
                        </div>
                        <div class="form-group">
                            <label>Alasan Masuk Dirawat</label>
                            <input type="text" name="alasan"
                                   value="<?php echo htmlspecialchars($data['alasan']); ?>">
                        </div>
                    </div>

                    <!-- Keluhan Utama dengan Ambil Data -->
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 15px; margin-top: 15px; align-items: start;">
                        <div class="form-group" style="margin: 0;">
                            <label class="required">Keluhan Utama Riwayat Penyakit</label>
                            <textarea name="keluhan_utama" id="keluhan_utama" rows="3" required
                                      placeholder="Keluhan utama dan riwayat penyakit..."><?php echo htmlspecialchars($data['keluhan_utama']); ?></textarea>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div class="label-with-action" style="margin-bottom: 6px;">
                                <label style="margin-bottom: 0;">Ambil Data</label>
                                <div class="dropdown-ambil-data">
                                    <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-keluhan')">
                                        <i class="material-icons">add</i>
                                    </button>
                                    <div class="dropdown-content dropdown-simple" id="dropdown-keluhan">
                                        <?php if($adaDataIGD): ?>
                                        <label class="dropdown-item" onclick="showSumberData('keluhan', 'igd')">
                                            <span>Medis IGD</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if($adaDataRanap): ?>
                                        <label class="dropdown-item" onclick="showSumberData('keluhan', 'ranap')">
                                            <span>Medis Ranap</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if($adaDataSOAP): ?>
                                        <label class="dropdown-item" onclick="showSumberData('keluhan', 'soap')">
                                            <span>SOAP Ranap</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if(!$adaDataIGD && !$adaDataRanap && !$adaDataSOAP): ?>
                                        <div class="dropdown-item disabled">Tidak ada data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ambil-data-compact" id="ambil-box-keluhan">
                                <div class="ambil-data-empty">Pilih sumber data</div>
                            </div>
                            <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('keluhan', 'keluhan_utama')">
                                <i class="material-icons">check</i> Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Pemeriksaan Fisik dengan Ambil Data -->
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 15px; margin-top: 15px; align-items: start;">
                        <div class="form-group" style="margin: 0;">
                            <label>Pemeriksaan Fisik</label>
                            <textarea name="pemeriksaan_fisik" id="pemeriksaan_fisik" rows="3"
                                      placeholder="Hasil pemeriksaan fisik..."><?php echo htmlspecialchars($data['pemeriksaan_fisik']); ?></textarea>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div class="label-with-action" style="margin-bottom: 6px;">
                                <label style="margin-bottom: 0;">Ambil Data</label>
                                <div class="dropdown-ambil-data">
                                    <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-fisik')">
                                        <i class="material-icons">add</i>
                                    </button>
                                    <div class="dropdown-content dropdown-simple" id="dropdown-fisik">
                                        <?php if($adaDataRanap): ?>
                                        <label class="dropdown-item" onclick="showSumberData('fisik', 'ranap')">
                                            <span>Medis Ranap</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if($adaDataSOAP): ?>
                                        <label class="dropdown-item" onclick="showSumberData('fisik', 'soap')">
                                            <span>SOAP Ranap</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if(!$adaDataRanap && !$adaDataSOAP): ?>
                                        <div class="dropdown-item disabled">Tidak ada data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ambil-data-compact" id="ambil-box-fisik">
                                <div class="ambil-data-empty">Pilih sumber data</div>
                            </div>
                            <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('fisik', 'pemeriksaan_fisik')">
                                <i class="material-icons">check</i> Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Jalannya Penyakit dengan Ambil Data -->
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 15px; margin-top: 15px; align-items: start;">
                        <div class="form-group" style="margin: 0;">
                            <label>Jalannya Penyakit Selama Perawatan</label>
                            <textarea name="jalannya_penyakit" id="jalannya_penyakit" rows="4"
                                      placeholder="Perjalanan penyakit selama perawatan..."><?php echo htmlspecialchars($data['jalannya_penyakit']); ?></textarea>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div class="label-with-action" style="margin-bottom: 6px;">
                                <label style="margin-bottom: 0;">Ambil Data</label>
                                <div class="dropdown-ambil-data">
                                    <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-jalannya')">
                                        <i class="material-icons">add</i>
                                    </button>
                                    <div class="dropdown-content dropdown-simple" id="dropdown-jalannya">
                                        <?php if($adaDataSOAP): ?>
                                        <label class="dropdown-item" onclick="showSumberData('jalannya', 'soap')">
                                            <span>SOAP Ranap (Narasi)</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if(!$adaDataSOAP): ?>
                                        <div class="dropdown-item disabled">Tidak ada data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ambil-data-compact" id="ambil-box-jalannya">
                                <div class="ambil-data-empty">Pilih sumber data</div>
                            </div>
                            <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('jalannya', 'jalannya_penyakit')">
                                <i class="material-icons">check</i> Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Pemeriksaan Penunjang dengan Ambil Data -->
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 15px; margin-top: 15px; align-items: start;">
                        <div class="form-group" style="margin: 0;">
                            <label>Pemeriksaan Penunjang Rad Terpenting</label>
                            <textarea name="pemeriksaan_penunjang" id="pemeriksaan_penunjang" rows="3"
                                      placeholder="Hasil pemeriksaan radiologi terpenting..."><?php echo htmlspecialchars($data['pemeriksaan_penunjang']); ?></textarea>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div class="label-with-action" style="margin-bottom: 6px;">
                                <label style="margin-bottom: 0;">Ambil Data</label>
                                <div class="dropdown-ambil-data">
                                    <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-penunjang')">
                                        <i class="material-icons">add</i>
                                    </button>
                                    <div class="dropdown-content dropdown-simple" id="dropdown-penunjang">
                                        <?php if($adaDataRadiologi): ?>
                                        <label class="dropdown-item" onclick="showSumberData('penunjang', 'radiologi')">
                                            <span>Hasil Radiologi</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if(!$adaDataRadiologi): ?>
                                        <div class="dropdown-item disabled">Tidak ada data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ambil-data-compact" id="ambil-box-penunjang">
                                <div class="ambil-data-empty">Pilih sumber data</div>
                            </div>
                            <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('penunjang', 'pemeriksaan_penunjang')">
                                <i class="material-icons">check</i> Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Hasil Lab dengan Ambil Data -->
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 15px; margin-top: 15px; align-items: start;">
                        <div class="form-group" style="margin: 0;">
                            <label>Pemeriksaan Penunjang Lab Terpenting</label>
                            <textarea name="hasil_laborat" id="hasil_laborat" rows="3"
                                      placeholder="Hasil laboratorium terpenting..."><?php echo htmlspecialchars($data['hasil_laborat']); ?></textarea>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div class="label-with-action" style="margin-bottom: 6px;">
                                <label style="margin-bottom: 0;">Ambil Data</label>
                                <div class="dropdown-ambil-data">
                                    <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-lab')">
                                        <i class="material-icons">add</i>
                                    </button>
                                    <div class="dropdown-content dropdown-simple" id="dropdown-lab">
                                        <?php if($adaDataLab): ?>
                                        <label class="dropdown-item" onclick="showSumberData('lab', 'semua')">
                                            <span>Semua Hasil Lab</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if($adaDataLabKritis): ?>
                                        <label class="dropdown-item" onclick="showSumberData('lab', 'kritis')">
                                            <span>Hasil Lab Kritis</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if(!$adaDataLab): ?>
                                        <div class="dropdown-item disabled">Tidak ada data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ambil-data-compact" id="ambil-box-lab">
                                <div class="ambil-data-empty">Pilih sumber data</div>
                            </div>
                            <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('lab', 'hasil_laborat')">
                                <i class="material-icons">check</i> Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Tindakan dan Operasi dengan Ambil Data -->
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 15px; margin-top: 15px; align-items: start;">
                        <div class="form-group" style="margin: 0;">
                            <label>Tindakan/Operasi Selama Perawatan</label>
                            <textarea name="tindakan_dan_operasi" id="tindakan_dan_operasi" rows="3"
                                      placeholder="Tindakan atau operasi yang dilakukan..."><?php echo htmlspecialchars($data['tindakan_dan_operasi']); ?></textarea>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div class="label-with-action" style="margin-bottom: 6px;">
                                <label style="margin-bottom: 0;">Ambil Data</label>
                                <div class="dropdown-ambil-data">
                                    <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-tindakan')">
                                        <i class="material-icons">add</i>
                                    </button>
                                    <div class="dropdown-content dropdown-simple" id="dropdown-tindakan">
                                        <?php if($adaDataTindakan): ?>
                                        <label class="dropdown-item" onclick="showSumberData('tindakan', 'tindakan')">
                                            <span>Tindakan</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if($adaDataOperasi): ?>
                                        <label class="dropdown-item" onclick="showSumberData('tindakan', 'operasi')">
                                            <span>Operasi</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if(!$adaDataTindakan && !$adaDataOperasi): ?>
                                        <div class="dropdown-item disabled">Tidak ada data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ambil-data-compact" id="ambil-box-tindakan">
                                <div class="ambil-data-empty">Pilih sumber data</div>
                            </div>
                            <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('tindakan', 'tindakan_dan_operasi')">
                                <i class="material-icons">check</i> Terapkan
                            </button>
                        </div>
                    </div>

                    <!-- Obat di RS dengan Ambil Data -->
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 15px; margin-top: 15px; align-items: start;">
                        <div class="form-group" style="margin: 0;">
                            <label>Obat-obatan Selama Perawatan</label>
                            <textarea name="obat_di_rs" id="obat_di_rs" rows="3"
                                      placeholder="Obat-obatan yang diberikan selama perawatan..."><?php echo htmlspecialchars($data['obat_di_rs']); ?></textarea>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div class="label-with-action" style="margin-bottom: 6px;">
                                <label style="margin-bottom: 0;">Ambil Data</label>
                                <div class="dropdown-ambil-data">
                                    <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-obat')">
                                        <i class="material-icons">add</i>
                                    </button>
                                    <div class="dropdown-content dropdown-simple" id="dropdown-obat">
                                        <?php if($adaDataObat): ?>
                                        <label class="dropdown-item" onclick="showSumberData('obat', 'obat')">
                                            <span>Semua Obat</span>
                                        </label>
                                        <label class="dropdown-item" onclick="showSumberData('obat', 'manual')">
                                            <span>Pilih Manual</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if(!$adaDataObat): ?>
                                        <div class="dropdown-item disabled">Tidak ada data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ambil-data-compact" id="ambil-box-obat">
                                <div class="ambil-data-empty">Pilih sumber data</div>
                            </div>
                            <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('obat', 'obat_di_rs')">
                                <i class="material-icons">check</i> Terapkan
                            </button>
                        </div>
                    </div>
                </div>

                <!-- II. DIAGNOSA AKHIR -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>II. DIAGNOSA AKHIR</h2>
                    </div>

                    <div class="section-subtitle">Diagnosa</div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Diagnosa Utama</label>
                            <input type="text" name="diagnosa_utama" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_utama']); ?>" 
                                   placeholder="Nama diagnosa utama">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD</label>
                            <input type="text" name="kd_diagnosa_utama" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_utama']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Diagnosa Sekunder 1</label>
                            <input type="text" name="diagnosa_sekunder" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_sekunder']); ?>" 
                                   placeholder="Diagnosa sekunder 1">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD</label>
                            <input type="text" name="kd_diagnosa_sekunder" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Diagnosa Sekunder 2</label>
                            <input type="text" name="diagnosa_sekunder2" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_sekunder2']); ?>" 
                                   placeholder="Diagnosa sekunder 2">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD</label>
                            <input type="text" name="kd_diagnosa_sekunder2" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder2']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Diagnosa Sekunder 3</label>
                            <input type="text" name="diagnosa_sekunder3" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_sekunder3']); ?>" 
                                   placeholder="Diagnosa sekunder 3">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD</label>
                            <input type="text" name="kd_diagnosa_sekunder3" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder3']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Diagnosa Sekunder 4</label>
                            <input type="text" name="diagnosa_sekunder4" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_sekunder4']); ?>" 
                                   placeholder="Diagnosa sekunder 4">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD</label>
                            <input type="text" name="kd_diagnosa_sekunder4" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder4']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>

                    <div class="section-subtitle">Prosedur</div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Prosedur Utama</label>
                            <input type="text" name="prosedur_utama" 
                                   value="<?php echo htmlspecialchars($data['prosedur_utama']); ?>" 
                                   placeholder="Nama prosedur utama">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD</label>
                            <input type="text" name="kd_prosedur_utama" 
                                   value="<?php echo htmlspecialchars($data['kd_prosedur_utama']); ?>" 
                                   placeholder="Kode">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Prosedur Sekunder 1</label>
                            <input type="text" name="prosedur_sekunder" 
                                   value="<?php echo htmlspecialchars($data['prosedur_sekunder']); ?>" 
                                   placeholder="Prosedur sekunder 1">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD</label>
                            <input type="text" name="kd_prosedur_sekunder" 
                                   value="<?php echo htmlspecialchars($data['kd_prosedur_sekunder']); ?>" 
                                   placeholder="Kode">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Prosedur Sekunder 2</label>
                            <input type="text" name="prosedur_sekunder2" 
                                   value="<?php echo htmlspecialchars($data['prosedur_sekunder2']); ?>" 
                                   placeholder="Prosedur sekunder 2">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD</label>
                            <input type="text" name="kd_prosedur_sekunder2" 
                                   value="<?php echo htmlspecialchars($data['kd_prosedur_sekunder2']); ?>" 
                                   placeholder="Kode">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Prosedur Sekunder 3</label>
                            <input type="text" name="prosedur_sekunder3" 
                                   value="<?php echo htmlspecialchars($data['prosedur_sekunder3']); ?>" 
                                   placeholder="Prosedur sekunder 3">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD</label>
                            <input type="text" name="kd_prosedur_sekunder3" 
                                   value="<?php echo htmlspecialchars($data['kd_prosedur_sekunder3']); ?>" 
                                   placeholder="Kode">
                        </div>
                    </div>
                </div>

                <!-- III. KEPULANGAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">exit_to_app</i>
                        <h2>III. KEPULANGAN</h2>
                    </div>

                    <div class="form-group">
                        <label>Alergi Obat</label>
                        <input type="text" name="alergi" 
                               value="<?php echo htmlspecialchars($data['alergi']); ?>" 
                               placeholder="Alergi obat pasien...">
                    </div>

                    <!-- Diet dengan Ambil Data -->
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 15px; margin-top: 10px; align-items: start;">
                        <div class="form-group" style="margin: 0;">
                            <label>Diet</label>
                            <textarea name="diet" id="diet" rows="2"
                                      placeholder="Diet yang dianjurkan..."><?php echo htmlspecialchars($data['diet']); ?></textarea>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div class="label-with-action" style="margin-bottom: 6px;">
                                <label style="margin-bottom: 0;">Ambil Data</label>
                                <div class="dropdown-ambil-data">
                                    <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-diet')">
                                        <i class="material-icons">add</i>
                                    </button>
                                    <div class="dropdown-content dropdown-simple" id="dropdown-diet">
                                        <?php if($adaDataDiet): ?>
                                        <label class="dropdown-item" onclick="showSumberData('diet', 'diet')">
                                            <span>Diet Perawatan</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if(!$adaDataDiet): ?>
                                        <div class="dropdown-item disabled">Tidak ada data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ambil-data-compact" id="ambil-box-diet">
                                <div class="ambil-data-empty">Pilih sumber data</div>
                            </div>
                            <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('diet', 'diet')">
                                <i class="material-icons">check</i> Terapkan
                            </button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Hasil Lab Yang Belum Selesai (Pending)</label>
                        <textarea name="lab_belum" rows="2" 
                                  placeholder="Hasil laboratorium yang masih pending..."><?php echo htmlspecialchars($data['lab_belum']); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Instruksi/Anjuran Dan Edukasi (Follow Up)</label>
                        <textarea name="edukasi" rows="3" 
                                  placeholder="Instruksi dan edukasi untuk pasien..."><?php echo htmlspecialchars($data['edukasi']); ?></textarea>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Keadaan Pulang</label>
                            <select name="keadaan">
                                <option value="Membaik" <?php echo ($data['keadaan'] == 'Membaik') ? 'selected' : ''; ?>>Membaik</option>
                                <option value="Sembuh" <?php echo ($data['keadaan'] == 'Sembuh') ? 'selected' : ''; ?>>Sembuh</option>
                                <option value="Keadaan Khusus" <?php echo ($data['keadaan'] == 'Keadaan Khusus') ? 'selected' : ''; ?>>Keadaan Khusus</option>
                                <option value="Meninggal" <?php echo ($data['keadaan'] == 'Meninggal') ? 'selected' : ''; ?>>Meninggal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Keterangan Keadaan</label>
                            <input type="text" name="ket_keadaan" 
                                   value="<?php echo htmlspecialchars($data['ket_keadaan']); ?>" 
                                   placeholder="Keterangan...">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Cara Keluar</label>
                            <select name="cara_keluar">
                                <option value="Atas Izin Dokter" <?php echo ($data['cara_keluar'] == 'Atas Izin Dokter') ? 'selected' : ''; ?>>Atas Izin Dokter</option>
                                <option value="Pindah RS" <?php echo ($data['cara_keluar'] == 'Pindah RS') ? 'selected' : ''; ?>>Pindah RS</option>
                                <option value="Pulang Atas Permintaan Sendiri" <?php echo ($data['cara_keluar'] == 'Pulang Atas Permintaan Sendiri') ? 'selected' : ''; ?>>Pulang Atas Permintaan Sendiri</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Keterangan Cara Keluar</label>
                            <input type="text" name="ket_keluar" 
                                   value="<?php echo htmlspecialchars($data['ket_keluar']); ?>" 
                                   placeholder="Keterangan...">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Dilanjutkan</label>
                            <select name="dilanjutkan">
                                <option value="Kembali Ke RS" <?php echo ($data['dilanjutkan'] == 'Kembali Ke RS') ? 'selected' : ''; ?>>Kembali Ke RS</option>
                                <option value="RS Lain" <?php echo ($data['dilanjutkan'] == 'RS Lain') ? 'selected' : ''; ?>>RS Lain</option>
                                <option value="Dokter Luar" <?php echo ($data['dilanjutkan'] == 'Dokter Luar') ? 'selected' : ''; ?>>Dokter Luar</option>
                                <option value="Puskesmas" <?php echo ($data['dilanjutkan'] == 'Puskesmas') ? 'selected' : ''; ?>>Puskesmas</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Keterangan Dilanjutkan</label>
                            <input type="text" name="ket_dilanjutkan" 
                                   value="<?php echo htmlspecialchars($data['ket_dilanjutkan']); ?>" 
                                   placeholder="Keterangan...">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Tanggal & Jam Kontrol</label>
                        <input type="datetime-local" name="kontrol" 
                               value="<?php echo $data['kontrol'] ? date('Y-m-d\TH:i', strtotime($data['kontrol'])) : ''; ?>">
                    </div>

                    <!-- Obat Pulang dengan Ambil Data -->
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 15px; margin-top: 10px; align-items: start;">
                        <div class="form-group" style="margin: 0;">
                            <label>Obat Pulang</label>
                            <textarea name="obat_pulang" id="obat_pulang" rows="4"
                                      placeholder="Obat-obatan yang diberikan saat pulang..."><?php echo htmlspecialchars($data['obat_pulang']); ?></textarea>
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <div class="label-with-action" style="margin-bottom: 6px;">
                                <label style="margin-bottom: 0;">Ambil Data</label>
                                <div class="dropdown-ambil-data">
                                    <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-obatpulang')">
                                        <i class="material-icons">add</i>
                                    </button>
                                    <div class="dropdown-content dropdown-simple" id="dropdown-obatpulang">
                                        <?php if($adaDataObatPulang): ?>
                                        <label class="dropdown-item" onclick="showSumberData('obatpulang', 'resep')">
                                            <span>Resep Pulang</span>
                                        </label>
                                        <?php endif; ?>
                                        <?php if(!$adaDataObatPulang): ?>
                                        <div class="dropdown-item disabled">Tidak ada data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ambil-data-compact" id="ambil-box-obatpulang">
                                <div class="ambil-data-empty">Pilih sumber data</div>
                            </div>
                            <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('obatpulang', 'obat_pulang')">
                                <i class="material-icons">check</i> Terapkan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <!-- Navigation buttons for tab system (hidden by default) -->
            <button type="button" id="btn-prev" class="btn btn-secondary" onclick="previousTab();" style="display: none;">
                <i class="material-icons">arrow_back</i>
                PREV
            </button>
            <button type="button" id="btn-next" class="btn btn-secondary" onclick="nextTab();" style="display: none;">
                <i class="material-icons">arrow_forward</i>
                NEXT
            </button>
            
            <!-- Main action buttons -->
            <button type="button" class="btn btn-secondary" onclick="kembaliResumeMedisRanap()">
                <i class="material-icons">arrow_back</i>
                KEMBALI
            </button>
            <?php 
            // Cek apakah boleh hapus: data ada DAN dokter login = dokter pengisi
            $bolehHapus = false;
            if($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if($kd_dokter_login === $kd_dokter_data) {
                    $bolehHapus = true;
                }
            }
            
            if($bolehHapus): 
            ?>
            <button type="button" id="btn-delete-ranap" class="btn btn-danger" onclick="confirmDeleteRanap()">
                <i class="material-icons">delete</i>
                HAPUS DATA
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-ranap" form="formResume" class="btn btn-primary">
                <i class="material-icons">save</i>
                SIMPAN DATA
            </button>
        </div>
    </div>
</div>

<!-- Hidden form for delete -->
<?php if($isEdit && !$bolehHapus): ?>
        <!-- Info untuk dokter lain -->
    <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 15px; display: flex; align-items: center; gap: 10px;">
        <i class="material-icons" style="color: #856404;">info</i>
            <span style="color: #856404; font-size: 14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_resume; ?></strong>. 
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>

<!-- Pass PHP variables to JavaScript -->
<script>
// Define as window properties to ensure global scope
window.APP_BASE_URL = '<?php echo APP_BASE_URL; ?>';

// Data sumber dari PHP untuk JavaScript
window.dataSumber = {
    keluhan: {
        igd: {
            label: 'Medis IGD',
            items: [
                { key: 'keluhan_utama', label: 'Keluhan Utama', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $sumberKeluhan['igd']['data'])); ?>` }
            ]
        },
        ranap: {
            label: 'Medis Ranap',
            items: [
                { key: 'keluhan_utama', label: 'Keluhan Utama', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $sumberKeluhan['ranap']['data'])); ?>` },
                { key: 'rps', label: 'RPS', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $sumberKeluhan['ranap_rps']['data'])); ?>` }
            ]
        },
        soap: {
            label: 'SOAP Ranap',
            items: [
                <?php foreach($dataSOAP as $idx => $soap): ?>
                { 
                    key: 'soap_<?php echo $idx; ?>', 
                    label: '<?php echo date("d/m/Y H:i", strtotime($soap["tgl_perawatan"] . " " . $soap["jam_rawat"])); ?>', 
                    data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $soap['keluhan'])); ?>` 
                }<?php echo ($idx < count($dataSOAP) - 1) ? ',' : ''; ?>
                <?php endforeach; ?>
            ]
        }
    },
    fisik: {
        ranap: {
            label: 'Medis Ranap',
            items: [
                { key: 'pemeriksaan', label: 'Pemeriksaan Fisik', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $pemeriksaanFisikRanap)); ?>` }
            ]
        },
        soap: {
            label: 'SOAP Ranap',
            items: [
                <?php foreach($dataSOAP as $idx => $soap): 
                    $fisikSOAP = formatPemeriksaanFisikSOAP($soap);
                    if (!empty($fisikSOAP)):
                ?>
                { 
                    key: 'soap_<?php echo $idx; ?>', 
                    label: '<?php echo date("d/m/Y H:i", strtotime($soap["tgl_perawatan"] . " " . $soap["jam_rawat"])); ?>', 
                    data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $fisikSOAP)); ?>` 
                },
                <?php endif; endforeach; ?>
            ]
        }
    },
    jalannya: {
        soap: {
            label: 'SOAP Ranap',
            items: [
                { 
                    key: 'narasi_lengkap', 
                    label: 'Narasi Kronologis', 
                    data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $jalannyaPenyakitSOAP)); ?>` 
                }
            ]
        }
    },
    penunjang: {
        radiologi: {
            label: 'Hasil Radiologi',
            items: [
                <?php foreach($dataRadiologi as $idx => $rad): 
                    $tglRad = date('d/m/Y', strtotime($rad['tgl_periksa']));
                    $hasilRad = !empty($rad['hasil']) ? $rad['hasil'] : '';
                    if (!empty($hasilRad)):
                ?>
                { 
                    key: 'rad_<?php echo $idx; ?>', 
                    label: '<?php echo addslashes("Radiologi (" . $tglRad . ")"); ?>', 
                    data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', "Radiologi (" . $tglRad . "):\n" . $hasilRad)); ?>` 
                },
                <?php endif; endforeach; ?>
            ]
        }
    },
    lab: {
        semua: {
            label: 'Hasil Lab (Semua)',
            items: [
                { 
                    key: 'lab_semua', 
                    label: 'Semua Hasil Lab', 
                    data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $hasilLabFormatted)); ?>` 
                }
            ]
        },
        kritis: {
            label: 'Hasil Lab Kritis',
            items: [
                { 
                    key: 'lab_kritis', 
                    label: 'Hasil Lab Kritis', 
                    data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $hasilLabKritisFormatted)); ?>` 
                }
            ]
        }
    },
    tindakan: {
        tindakan: {
            label: 'Tindakan',
            items: [
                <?php if($adaDataTindakan): ?>
                { 
                    key: 'tindakan_all', 
                    label: 'Semua Tindakan', 
                    data: `<?php echo addslashes($tindakanFormatted); ?>` 
                },
                <?php endif; ?>
            ]
        },
        operasi: {
            label: 'Operasi',
            items: [
                <?php foreach($dataOperasi as $idx => $op): 
                    $tglOp = date('d/m/Y', strtotime($op['tgl_operasi']));
                    $nmOp = !empty($op['nm_perawatan']) ? $op['nm_perawatan'] : '-';
                    $dataOp = "Operasi (" . date('d-m-Y', strtotime($op['tgl_operasi'])) . "): " . $nmOp;
                ?>
                { 
                    key: 'op_<?php echo $idx; ?>', 
                    label: '<?php echo addslashes($nmOp . " (" . $tglOp . ")"); ?>', 
                    data: `<?php echo addslashes($dataOp); ?>` 
                },
                <?php endforeach; ?>
            ]
        }
    },
    obat: {
        obat: {
            label: 'Obat Selama Perawatan',
            items: [
                <?php if($adaDataObat): ?>
                { 
                    key: 'obat_all', 
                    label: 'Semua Obat', 
                    data: `<?php echo addslashes($obatFormatted); ?>` 
                },
                <?php endif; ?>
            ]
        },
        manual: {
            label: 'Pilih Obat Manual',
            items: [
                <?php foreach($dataObat as $idx => $namaObat): ?>
                { 
                    key: 'obat_<?php echo $idx; ?>', 
                    label: `<?php echo addslashes($namaObat); ?>`, 
                    data: `<?php echo addslashes($namaObat); ?>` 
                },
                <?php endforeach; ?>
            ]
        }
    },
    diet: {
        diet: {
            label: 'Diet Selama Perawatan',
            items: [
                <?php if($adaDataDiet): ?>
                { 
                    key: 'diet_all', 
                    label: 'Semua Diet', 
                    data: `<?php echo addslashes($dietFormatted); ?>` 
                },
                <?php endif; ?>
            ]
        }
    },
    obatpulang: {
        resep: {
            label: 'Resep Pulang',
            items: [
                <?php if($adaDataObatPulang): ?>
                { 
                    key: 'resep_all', 
                    label: 'Semua Obat Pulang', 
                    data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $obatPulangFormatted)); ?>` 
                },
                <?php endif; ?>
            ]
        }
    }
};

// toggleDropdown dan showSumberData di-handle di resumemedisinap.js
// confirmDeleteRanap dan kembaliResumeMedisRanap di-handle di resumemedisinap.js
</script>

<!-- Load External JavaScript -->
<script src="<?php echo BASE_URL; ?>/js/resumemedisinap.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>