<?php
/**
 * detail_inap.php
 * File untuk menampilkan detail pasien rawat inap
 * Dipanggil via AJAX dari listpasieninap.php
 */

// Start session jika belum
if(session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include konfigurasi
include_once('../conf/conf.php');

/**
 * Helper function: Cek apakah tabel exists
 */
function tableExists($tableName) {
    return isset($GLOBALS['existing_tables']) && in_array($tableName, $GLOBALS['existing_tables']);
}

/**
 * Helper function: Safe count query - return 0 jika tabel tidak ada
 */
function safeCountQuery($tableName, $no_rawat, $dateColumn, $today, $useDateFunction = false) {
    if(!tableExists($tableName)) {
        return 0;
    }
    
    if($useDateFunction) {
        $query = bukaquery("SELECT COUNT(*) as jumlah FROM $tableName WHERE no_rawat = '$no_rawat' AND DATE($dateColumn) = '$today'");
    } else {
        $query = bukaquery("SELECT COUNT(*) as jumlah FROM $tableName WHERE no_rawat = '$no_rawat' AND $dateColumn = '$today'");
    }
    
    if($query) {
        $result = mysqli_fetch_array($query);
        return (int)$result['jumlah'];
    }
    return 0;
}

/**
 * Helper function: Safe query untuk Lab/Rad (dengan cek tgl_sampel)
 */
function safeLabRadQuery($tableName, $no_rawat, $today) {
    if(!tableExists($tableName)) {
        return ['sudah' => 0, 'belum' => 0, 'total' => 0];
    }
    
    $sudah = 0;
    $belum = 0;
    $query = bukaquery("SELECT tgl_sampel FROM $tableName WHERE no_rawat = '$no_rawat' AND tgl_permintaan = '$today'");
    
    if($query) {
        while($row = mysqli_fetch_array($query)) {
            if($row['tgl_sampel'] == '0000-00-00' || empty($row['tgl_sampel'])) {
                $belum++;
            } else {
                $sudah++;
            }
        }
    }
    
    return ['sudah' => $sudah, 'belum' => $belum, 'total' => $sudah + $belum];
}

/**
 * Helper function: Safe query untuk E-Resep (dengan cek tgl_perawatan)
 */
function safeResepQuery($tableName, $no_rawat, $today) {
    if(!tableExists($tableName)) {
        return ['sudah' => 0, 'belum' => 0, 'total' => 0];
    }
    
    $sudah = 0;
    $belum = 0;
    $query = bukaquery("SELECT tgl_perawatan FROM $tableName WHERE no_rawat = '$no_rawat' AND tgl_peresepan = '$today' ORDER BY jam ASC");
    
    if($query) {
        while($row = mysqli_fetch_array($query)) {
            if($row['tgl_perawatan'] == '0000-00-00' || empty($row['tgl_perawatan'])) {
                $belum++;
            } else {
                $sudah++;
            }
        }
    }
    
    return ['sudah' => $sudah, 'belum' => $belum, 'total' => $sudah + $belum];
}

/**
 * Helper function: Safe query untuk Rekonsiliasi (cek ada atau tidak)
 */
function safeExistsQuery($tableName, $no_rawat, $dateColumn, $today, $useDateFunction = false) {
    if(!tableExists($tableName)) {
        return false;
    }
    
    if($useDateFunction) {
        $query = bukaquery("SELECT no_rawat FROM $tableName WHERE no_rawat = '$no_rawat' AND DATE($dateColumn) = '$today' LIMIT 1");
    } else {
        $query = bukaquery("SELECT no_rawat FROM $tableName WHERE no_rawat = '$no_rawat' AND $dateColumn = '$today' LIMIT 1");
    }
    
    return ($query && mysqli_num_rows($query) > 0);
}

// Cek apakah dipanggil via AJAX
if(!isset($_GET['no_rawat']) || !isset($_GET['no_rm'])) {
    echo '<div class="alert alert-danger" style="margin: 15px;">
            <i class="material-icons" style="vertical-align: middle;">error</i>
            Parameter tidak lengkap!
          </div>';
    exit;
}

// Decrypt parameter
$no_rawat = encrypt_decrypt(urldecode($_GET['no_rawat']), 'd');
$no_rm = encrypt_decrypt(urldecode($_GET['no_rm']), 'd');

// Validasi
if(empty($no_rawat) || empty($no_rm)) {
    echo '<div class="alert alert-danger" style="margin: 15px;">
            <i class="material-icons" style="vertical-align: middle;">error</i>
            Data tidak valid!
          </div>';
    exit;
}

// Query data pasien
$queryPasien = bukaquery("SELECT 
                            p.nm_pasien,
                            p.no_ktp,
                            p.jk,
                            p.tmp_lahir,
                            p.tgl_lahir,
                            p.alamat,
                            p.no_tlp,
                            rp.no_rawat,
                            rp.no_rkm_medis,
                            rp.tgl_registrasi,
                            rp.jam_reg,
                            ki.tgl_masuk,
                            ki.jam_masuk,
                            ki.diagnosa_awal,
                            ki.kd_kamar,
                            b.nm_bangsal,
                            pj.png_jawab
                         FROM reg_periksa rp
                         INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                         LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
                         LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                         LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                         LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                         WHERE rp.no_rawat = '$no_rawat'
                         AND rp.no_rkm_medis = '$no_rm'
                         LIMIT 1");

$dataPasien = mysqli_fetch_array($queryPasien);

if(!$dataPasien) {
    echo '<div class="alert alert-warning" style="margin: 15px;">
            <i class="material-icons" style="vertical-align: middle;">warning</i>
            Data pasien tidak ditemukan!
          </div>';
    exit;
}

$today = date('Y-m-d');

// =============================================
// SOAP
// =============================================
$jumlah_soapie = safeCountQuery('pemeriksaan_ranap', $no_rawat, 'tgl_perawatan', $today);

// =============================================
// E-Resep
// =============================================
$resep = safeResepQuery('resep_obat', $no_rawat, $today);
$jumlah_resep = $resep['total'];
$resep_sudah_dilayani = $resep['sudah'];
$resep_belum_dilayani = $resep['belum'];

// =============================================
// Lab PK, MB, PA
// =============================================
$lab_pk = safeLabRadQuery('permintaan_lab', $no_rawat, $today);
$lab_pk_sudah = $lab_pk['sudah'];
$lab_pk_belum = $lab_pk['belum'];
$jumlah_lab_pk = $lab_pk['total'];

$lab_mb = safeLabRadQuery('permintaan_labmb', $no_rawat, $today);
$lab_mb_sudah = $lab_mb['sudah'];
$lab_mb_belum = $lab_mb['belum'];
$jumlah_lab_mb = $lab_mb['total'];

$lab_pa = safeLabRadQuery('permintaan_labpa', $no_rawat, $today);
$lab_pa_sudah = $lab_pa['sudah'];
$lab_pa_belum = $lab_pa['belum'];
$jumlah_lab_pa = $lab_pa['total'];

$jumlah_lab_total = $jumlah_lab_pk + $jumlah_lab_mb + $jumlah_lab_pa;

// =============================================
// Radiologi
// =============================================
$rad = safeLabRadQuery('permintaan_radiologi', $no_rawat, $today);
$rad_sudah = $rad['sudah'];
$rad_belum = $rad['belum'];
$jumlah_rad = $rad['total'];

// =============================================
// Rekonsiliasi Obat
// =============================================
$ada_rekonsiliasi = safeExistsQuery('rekonsiliasi_obat', $no_rawat, 'tanggal_wawancara', $today, true);

// =============================================
// Cek GDS
// =============================================
$jumlah_gds = safeCountQuery('catatan_cek_gds', $no_rawat, 'tgl_perawatan', $today);

// =============================================
// EWS (4 jenis)
// =============================================
$jumlah_ews_anak = safeCountQuery('pemantauan_pews_anak', $no_rawat, 'tanggal', $today, true);
$jumlah_ews_dewasa = safeCountQuery('pemantauan_pews_dewasa', $no_rawat, 'tanggal', $today, true);
$jumlah_ews_obstetri = safeCountQuery('pemantauan_meows_obstetri', $no_rawat, 'tanggal', $today, true);
$jumlah_ews_neonatus = safeCountQuery('pemantauan_ews_neonatus', $no_rawat, 'tanggal', $today, true);
$jumlah_ews_total = $jumlah_ews_anak + $jumlah_ews_dewasa + $jumlah_ews_obstetri + $jumlah_ews_neonatus;

// =============================================
// Observasi (9 jenis)
// =============================================
$jumlah_obs_ranap = safeCountQuery('catatan_observasi_ranap', $no_rawat, 'tgl_perawatan', $today);
$jumlah_obs_kebidanan = safeCountQuery('catatan_observasi_ranap_kebidanan', $no_rawat, 'tgl_perawatan', $today);
$jumlah_obs_postpartum = safeCountQuery('catatan_observasi_ranap_postpartum', $no_rawat, 'tgl_perawatan', $today);
$jumlah_obs_chbp = safeCountQuery('catatan_observasi_chbp', $no_rawat, 'tgl_perawatan', $today);
$jumlah_obs_induksi = safeCountQuery('catatan_observasi_induksi_persalinan', $no_rawat, 'tgl_perawatan', $today);
$jumlah_obs_bayi = safeCountQuery('catatan_observasi_bayi', $no_rawat, 'tgl_perawatan', $today);
$jumlah_obs_restrain = safeCountQuery('catatan_observasi_restrain_nonfarma', $no_rawat, 'tgl_perawatan', $today);
$jumlah_obs_ventilator = safeCountQuery('catatan_observasi_ventilator', $no_rawat, 'tgl_perawatan', $today);
$jumlah_obs_hemodialisa = safeCountQuery('catatan_observasi_hemodialisa', $no_rawat, 'tgl_perawatan', $today);
$jumlah_obs_total = $jumlah_obs_ranap + $jumlah_obs_kebidanan + $jumlah_obs_postpartum + $jumlah_obs_chbp + $jumlah_obs_induksi + $jumlah_obs_bayi + $jumlah_obs_restrain + $jumlah_obs_ventilator + $jumlah_obs_hemodialisa;

// =============================================
// Balance Cairan
// =============================================
$jumlah_balance_cairan = safeCountQuery('catatan_keseimbangan_cairan', $no_rawat, 'tgl_perawatan', $today);

// =============================================
// Tindakan & Pemeriksaan Hari Ini
// =============================================
$tindakan_dokter = [];
$tindakan_dokter_perawat = [];
$tindakan_perawat = [];

// 1. Tindakan Dokter (rawat_inap_dr) - Group by kd_jenis_prw
if(tableExists('rawat_inap_dr')) {
    $queryTindakanDr = bukaquery("SELECT jp.nm_perawatan, COUNT(*) as jumlah,
                                         GROUP_CONCAT(DISTINCT d.nm_dokter SEPARATOR ', ') as dokter_list
                                  FROM rawat_inap_dr rid
                                  LEFT JOIN dokter d ON rid.kd_dokter = d.kd_dokter
                                  LEFT JOIN jns_perawatan_inap jp ON rid.kd_jenis_prw = jp.kd_jenis_prw
                                  WHERE rid.no_rawat = '$no_rawat'
                                  AND rid.tgl_perawatan = '$today'
                                  GROUP BY rid.kd_jenis_prw, jp.nm_perawatan
                                  ORDER BY jumlah DESC");
    if($queryTindakanDr) {
        while($row = mysqli_fetch_array($queryTindakanDr)) {
            $tindakan_dokter[] = [
                'tindakan' => $row['nm_perawatan'],
                'jumlah' => (int)$row['jumlah'],
                'pelaksana' => $row['dokter_list']
            ];
        }
    }
}

// 2. Tindakan Dokter & Perawat (rawat_inap_drpr) - Group by kd_jenis_prw
if(tableExists('rawat_inap_drpr')) {
    $queryTindakanDrPr = bukaquery("SELECT jp.nm_perawatan, COUNT(*) as jumlah,
                                          GROUP_CONCAT(DISTINCT d.nm_dokter SEPARATOR ', ') as dokter_list,
                                          GROUP_CONCAT(DISTINCT pt.nama SEPARATOR ', ') as perawat_list
                                   FROM rawat_inap_drpr ridp
                                   LEFT JOIN dokter d ON ridp.kd_dokter = d.kd_dokter
                                   LEFT JOIN petugas pt ON ridp.nip = pt.nip
                                   LEFT JOIN jns_perawatan_inap jp ON ridp.kd_jenis_prw = jp.kd_jenis_prw
                                   WHERE ridp.no_rawat = '$no_rawat'
                                   AND ridp.tgl_perawatan = '$today'
                                   GROUP BY ridp.kd_jenis_prw, jp.nm_perawatan
                                   ORDER BY jumlah DESC");
    if($queryTindakanDrPr) {
        while($row = mysqli_fetch_array($queryTindakanDrPr)) {
            $tindakan_dokter_perawat[] = [
                'tindakan' => $row['nm_perawatan'],
                'jumlah' => (int)$row['jumlah'],
                'dokter' => $row['dokter_list'],
                'perawat' => $row['perawat_list']
            ];
        }
    }
}

// 3. Tindakan Perawat (rawat_inap_pr) - Group by kd_jenis_prw
if(tableExists('rawat_inap_pr')) {
    $queryTindakanPr = bukaquery("SELECT jp.nm_perawatan, COUNT(*) as jumlah,
                                        GROUP_CONCAT(DISTINCT pt.nama SEPARATOR ', ') as perawat_list
                                 FROM rawat_inap_pr rip
                                 LEFT JOIN petugas pt ON rip.nip = pt.nip
                                 LEFT JOIN jns_perawatan_inap jp ON rip.kd_jenis_prw = jp.kd_jenis_prw
                                 WHERE rip.no_rawat = '$no_rawat'
                                 AND rip.tgl_perawatan = '$today'
                                 GROUP BY rip.kd_jenis_prw, jp.nm_perawatan
                                 ORDER BY jumlah DESC");
    if($queryTindakanPr) {
        while($row = mysqli_fetch_array($queryTindakanPr)) {
            $tindakan_perawat[] = [
                'tindakan' => $row['nm_perawatan'],
                'jumlah' => (int)$row['jumlah'],
                'pelaksana' => $row['perawat_list']
            ];
        }
    }
}

$total_tindakan = count($tindakan_dokter) + count($tindakan_dokter_perawat) + count($tindakan_perawat);

// =============================================
// Obat & BHP Hari Ini
// =============================================
$obat_bhp = [];
if(tableExists('detail_pemberian_obat') && tableExists('databarang')) {
    $queryObatBhp = bukaquery("SELECT 
                                    dpo.kode_brng,
                                    db.nama_brng,
                                    dpo.jml,
                                    dpo.jam,
                                    dpo.status
                               FROM detail_pemberian_obat dpo
                               LEFT JOIN databarang db ON dpo.kode_brng = db.kode_brng
                               WHERE dpo.no_rawat = '$no_rawat'
                               AND dpo.tgl_perawatan = '$today'
                               ORDER BY dpo.jam DESC, dpo.kode_brng ASC");
    
    if($queryObatBhp) {
        while($row = mysqli_fetch_array($queryObatBhp)) {
            // Ambil aturan pakai yang paling akhir
            $aturan = 'BMHP';
            if(tableExists('aturan_pakai')) {
                $queryAturan = bukaquery("SELECT aturan FROM aturan_pakai 
                                          WHERE no_rawat = '$no_rawat' 
                                          AND kode_brng = '{$row['kode_brng']}' 
                                          ORDER BY tgl_perawatan DESC, jam DESC
                                          LIMIT 1");
                if($queryAturan && mysqli_num_rows($queryAturan) > 0) {
                    $rowAturan = mysqli_fetch_array($queryAturan);
                    $aturan = !empty($rowAturan['aturan']) ? $rowAturan['aturan'] : 'BMHP';
                }
            }
            
            $obat_bhp[] = [
                'kode_brng' => $row['kode_brng'],
                'nama_brng' => $row['nama_brng'],
                'jumlah' => (float)$row['jml'],
                'aturan' => $aturan,
                'jam' => $row['jam'],
                'status' => $row['status']
            ];
        }
    }
}

// =============================================
// Lab PK Hari Ini
// =============================================
$lab_pk_hari_ini = [];
if(tableExists('detail_periksa_lab') && tableExists('template_laboratorium')) {
    $queryLabPK = bukaquery("SELECT dpl.id_template, dpl.nilai, dpl.keterangan, dpl.jam,
                                   tl.Pemeriksaan, tl.satuan
                            FROM detail_periksa_lab dpl
                            LEFT JOIN template_laboratorium tl ON dpl.id_template = tl.id_template
                            WHERE dpl.no_rawat = '$no_rawat' 
                            AND dpl.tgl_periksa = '$today'
                            ORDER BY dpl.jam DESC");
    
    if($queryLabPK) {
        while($row = mysqli_fetch_array($queryLabPK)) {
            // Cek apakah nilai kosong
            $nilai = trim($row['nilai']);
            $keterangan = strtoupper($row['keterangan']);
            
            if(empty($nilai) || $nilai == '' || $nilai == '-') {
                $status = 'pending'; // Hasil belum keluar
            } elseif($keterangan == 'H') {
                $status = 'high'; // Tinggi (Kritis)
            } elseif($keterangan == 'L') {
                $status = 'low'; // Rendah (Kritis)
            } else {
                $status = 'normal'; // Normal
            }
            
            $lab_pk_hari_ini[] = [
                'pemeriksaan' => $row['Pemeriksaan'],
                'nilai' => $nilai,
                'satuan' => $row['satuan'],
                'status' => $status,
                'keterangan' => $keterangan,
                'jam' => $row['jam']
            ];
        }
    }
}

// =============================================
// Hasil Lab Kritis (H/L) - Semua pemeriksaan pada no_rawat ini
// Tabel: detail_periksa_lab
// Filter: keterangan = H, h, L, l
// =============================================
$lab_kritis = [];
if(tableExists('detail_periksa_lab')) {
    $queryLabKritis = bukaquery("SELECT dpl.tgl_periksa, dpl.jam, dpl.id_template, dpl.nilai, dpl.keterangan,
                                        t.Pemeriksaan as nm_pemeriksaan, t.satuan
                                 FROM detail_periksa_lab dpl
                                 LEFT JOIN template_laboratorium t ON dpl.id_template = t.id_template
                                 WHERE dpl.no_rawat = '$no_rawat'
                                 AND (dpl.keterangan = 'H' OR dpl.keterangan = 'h' OR dpl.keterangan = 'L' OR dpl.keterangan = 'l')
                                 ORDER BY dpl.tgl_periksa DESC, dpl.jam DESC");
    if($queryLabKritis) {
        while($row = mysqli_fetch_array($queryLabKritis)) {
            $lab_kritis[] = [
                'tanggal' => $row['tgl_periksa'],
                'jam' => $row['jam'],
                'pemeriksaan' => $row['nm_pemeriksaan'],
                'nilai' => $row['nilai'],
                'satuan' => $row['satuan'],
                'keterangan' => strtoupper($row['keterangan']) // H atau L
            ];
        }
    }
}

// =============================================
// Diagnosa ICD-10 (dari tabel diagnosa_pasien)
// Filter: status = 'Ranap'
// Prioritas: 1 = Primer, 2+ = Sekunder
// =============================================
$diagnosa_icd10 = [];
if(tableExists('diagnosa_pasien') && tableExists('penyakit')) {
    $queryIcd10 = bukaquery("SELECT dp.kd_penyakit, dp.prioritas, p.nm_penyakit
                             FROM diagnosa_pasien dp
                             LEFT JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
                             WHERE dp.no_rawat = '$no_rawat'
                             AND dp.status = 'Ranap'
                             ORDER BY dp.prioritas ASC");
    if($queryIcd10) {
        while($row = mysqli_fetch_array($queryIcd10)) {
            $diagnosa_icd10[] = [
                'kode' => $row['kd_penyakit'],
                'nama' => $row['nm_penyakit'],
                'prioritas' => (int)$row['prioritas']
            ];
        }
    }
}

// =============================================
// Prosedur ICD-9 (dari tabel prosedur_pasien)
// =============================================
$prosedur_icd9 = [];
if(tableExists('prosedur_pasien') && tableExists('icd9')) {
    $queryIcd9 = bukaquery("SELECT pp.kode, i.deskripsi_panjang
                            FROM prosedur_pasien pp
                            LEFT JOIN icd9 i ON pp.kode = i.kode
                            WHERE pp.no_rawat = '$no_rawat'
                            ORDER BY pp.prioritas ASC");
    if($queryIcd9) {
        while($row = mysqli_fetch_array($queryIcd9)) {
            $prosedur_icd9[] = [
                'kode' => $row['kode'],
                'deskripsi' => $row['deskripsi_panjang']
            ];
        }
    }
}

// =============================================
// Operasi/VK (dari tabel operasi)
// =============================================
$data_operasi = [];
if(tableExists('operasi') && tableExists('paket_operasi')) {
    $queryOperasi = bukaquery("SELECT o.tgl_operasi, po.nm_perawatan
                               FROM operasi o
                               LEFT JOIN paket_operasi po ON o.kode_paket = po.kode_paket
                               WHERE o.no_rawat = '$no_rawat'
                               ORDER BY o.tgl_operasi DESC");
    if($queryOperasi) {
        while($row = mysqli_fetch_array($queryOperasi)) {
            $data_operasi[] = [
                'tanggal' => $row['tgl_operasi'],
                'tindakan' => $row['nm_perawatan']
            ];
        }
    }
}

// =============================================
// Risiko Jatuh (dari 4 tabel)
// Ambil data terbaru dari masing-masing tabel
// =============================================
$risiko_jatuh = null;

// Cek dari 4 tabel, ambil yang ada datanya
$tabel_risiko_jatuh = [
    ['tabel' => 'penilaian_lanjutan_resiko_jatuh_anak', 'label' => 'Anak'],
    ['tabel' => 'penilaian_lanjutan_resiko_jatuh_dewasa', 'label' => 'Dewasa'],
    ['tabel' => 'penilaian_lanjutan_resiko_jatuh_geriatri', 'label' => 'Geriatri'],
    ['tabel' => 'penilaian_lanjutan_resiko_jatuh_lansia', 'label' => 'Lansia']
];

foreach($tabel_risiko_jatuh as $trj) {
    if(tableExists($trj['tabel'])) {
        $queryRisiko = bukaquery("SELECT r.hasil_skrining, r.saran, p.nama as nm_petugas
                                  FROM {$trj['tabel']} r
                                  LEFT JOIN petugas p ON r.nip = p.nip
                                  WHERE r.no_rawat = '$no_rawat'
                                  LIMIT 1");
        if($queryRisiko && mysqli_num_rows($queryRisiko) > 0) {
            $row = mysqli_fetch_array($queryRisiko);
            $risiko_jatuh = [
                'kategori' => $trj['label'],
                'hasil' => $row['hasil_skrining'],
                'saran' => $row['saran'],
                'petugas' => $row['nm_petugas']
            ];
            break; // Ambil yang pertama ditemukan
        }
    }
} 

// =============================================
// Diet Hari Ini
// =============================================
$diet_hari_ini = [];
if(tableExists('detail_beri_diet') && tableExists('diet')) {
    $queryDiet = bukaquery("SELECT dbd.waktu, d.nama_diet
                            FROM detail_beri_diet dbd
                            LEFT JOIN diet d ON dbd.kd_diet = d.kd_diet
                            WHERE dbd.no_rawat = '$no_rawat'
                            AND dbd.tanggal = '$today'
                            ORDER BY dbd.waktu ASC");
    if($queryDiet) {
        while($row = mysqli_fetch_array($queryDiet)) {
            $diet_hari_ini[] = [
                'waktu'     => $row['waktu'],
                'nama_diet' => $row['nama_diet']
            ];
        }
    }
}

// =============================================
// Data GDS untuk Grafik (semua data pasien ini)
// =============================================
$data_gds_grafik = [];
if(tableExists('catatan_cek_gds')) {
    $queryGdsGrafik = bukaquery("SELECT tgl_perawatan, jam_rawat, gdp 
                                 FROM catatan_cek_gds 
                                 WHERE no_rawat = '$no_rawat' 
                                 ORDER BY tgl_perawatan ASC, jam_rawat ASC");
    if($queryGdsGrafik) {
        while($row = mysqli_fetch_array($queryGdsGrafik)) {
            $data_gds_grafik[] = [
                'tanggal' => $row['tgl_perawatan'],
                'jam' => $row['jam_rawat'],
                'gdp' => (float)$row['gdp']
            ];
        }
    }
}
?>
<!-- Detail Pasien Rawat Inap -->
<div class="detail-inap-container" style="padding: 15px;">
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <!-- Detail Pemeriksaan Hari Ini -->
        <div style="flex: 1; min-width: 280px; background: white; padding: 12px 15px; border-radius: 8px; border: 1px solid #e8e8e8;">
            <div style="font-size: 11px; font-weight: 600; color: #667eea; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid #667eea; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 4px;">person</i>Detail Pemeriksaan Hari Ini
            </div>
            
            <?php if($jumlah_soapie > 0 || $jumlah_resep > 0 || $jumlah_lab_total > 0 || $jumlah_rad > 0 || $ada_rekonsiliasi || $jumlah_gds > 0 || $jumlah_ews_total > 0 || $jumlah_obs_total > 0 || $jumlah_balance_cairan > 0): ?>
            <!-- Status RME -->
            <div style="margin-bottom: 10px;">
                <div style="font-size: 11px; color: #888; margin-bottom: 6px;">Status RME :</div>
                <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                    <?php if($jumlah_soapie > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        SOAP ke-<?php echo $jumlah_soapie; ?>
                    </span>
                    <?php endif; ?>
                    
                    <?php if($jumlah_resep > 0): ?>
                        <?php if($resep_sudah_dilayani > 0): ?>
                        <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $resep_sudah_dilayani; ?> resep sudah dilayani farmasi">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                            E-Resep : <?php echo $resep_sudah_dilayani; ?>
                        </span>
                        <?php endif; ?>
                        <?php if($resep_belum_dilayani > 0): ?>
                        <span style="background: #f44336; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $resep_belum_dilayani; ?> resep belum dilayani farmasi">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">pending</i>
                            E-Resep : <?php echo $resep_belum_dilayani; ?>
                        </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Lab PK -->
                    <?php if($jumlah_lab_pk > 0): ?>
                        <?php if($lab_pk_sudah > 0): ?>
                        <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $lab_pk_sudah; ?> Lab PK sudah diambil sampel">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                            Lab PK : <?php echo $lab_pk_sudah; ?>
                        </span>
                        <?php endif; ?>
                        <?php if($lab_pk_belum > 0): ?>
                        <span style="background: #f44336; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $lab_pk_belum; ?> Lab PK belum diambil sampel">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">pending</i>
                            Lab PK : <?php echo $lab_pk_belum; ?>
                        </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Lab MB -->
                    <?php if($jumlah_lab_mb > 0): ?>
                        <?php if($lab_mb_sudah > 0): ?>
                        <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $lab_mb_sudah; ?> Lab MB sudah diambil sampel">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                            Lab MB : <?php echo $lab_mb_sudah; ?>
                        </span>
                        <?php endif; ?>
                        <?php if($lab_mb_belum > 0): ?>
                        <span style="background: #f44336; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $lab_mb_belum; ?> Lab MB belum diambil sampel">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">pending</i>
                            Lab MB : <?php echo $lab_mb_belum; ?>
                        </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Lab PA -->
                    <?php if($jumlah_lab_pa > 0): ?>
                        <?php if($lab_pa_sudah > 0): ?>
                        <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $lab_pa_sudah; ?> Lab PA sudah diambil sampel">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                            Lab PA : <?php echo $lab_pa_sudah; ?>
                        </span>
                        <?php endif; ?>
                        <?php if($lab_pa_belum > 0): ?>
                        <span style="background: #f44336; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $lab_pa_belum; ?> Lab PA belum diambil sampel">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">pending</i>
                            Lab PA : <?php echo $lab_pa_belum; ?>
                        </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Radiologi -->
                    <?php if($jumlah_rad > 0): ?>
                        <?php if($rad_sudah > 0): ?>
                        <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $rad_sudah; ?> Radiologi sudah dilayani">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                            Rad : <?php echo $rad_sudah; ?>
                        </span>
                        <?php endif; ?>
                        <?php if($rad_belum > 0): ?>
                        <span style="background: #f44336; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $rad_belum; ?> Radiologi belum dilayani">
                            <i class="material-icons" style="font-size: 12px; margin-right: 3px;">pending</i>
                            Rad : <?php echo $rad_belum; ?>
                        </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Rekonsiliasi Obat -->
                    <?php if($ada_rekonsiliasi): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="Rekonsiliasi obat sudah dilakukan hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Rekonsiliasi Obat
                    </span>
                    <?php endif; ?>
                    
                    <!-- Cek GDS -->
                    <?php if($jumlah_gds > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_gds; ?>x cek GDS hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        GDS ke-<?php echo $jumlah_gds; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- EWS Anak -->
                    <?php if($jumlah_ews_anak > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_ews_anak; ?>x EWS Anak hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        EWS Anak ke-<?php echo $jumlah_ews_anak; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- EWS Dewasa -->
                    <?php if($jumlah_ews_dewasa > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_ews_dewasa; ?>x EWS Dewasa hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        EWS Dewasa ke-<?php echo $jumlah_ews_dewasa; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- EWS Obstetri -->
                    <?php if($jumlah_ews_obstetri > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_ews_obstetri; ?>x EWS Obstetri hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        EWS Obstetri ke-<?php echo $jumlah_ews_obstetri; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- EWS Neonatus -->
                    <?php if($jumlah_ews_neonatus > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_ews_neonatus; ?>x EWS Neonatus hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        EWS Neonatus ke-<?php echo $jumlah_ews_neonatus; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Obs Ranap -->
                    <?php if($jumlah_obs_ranap > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_obs_ranap; ?>x Observasi Ranap hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Obs Ranap ke-<?php echo $jumlah_obs_ranap; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Obs Ranap Kebidanan -->
                    <?php if($jumlah_obs_kebidanan > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_obs_kebidanan; ?>x Observasi Ranap Kebidanan hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Obs Ranap Kebidanan ke-<?php echo $jumlah_obs_kebidanan; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Obs Ranap Post Partum -->
                    <?php if($jumlah_obs_postpartum > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_obs_postpartum; ?>x Observasi Ranap Post Partum hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Obs Ranap Post Partum ke-<?php echo $jumlah_obs_postpartum; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Obs CHBP -->
                    <?php if($jumlah_obs_chbp > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_obs_chbp; ?>x Observasi CHBP hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Obs CHBP ke-<?php echo $jumlah_obs_chbp; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Obs Induksi Persalinan -->
                    <?php if($jumlah_obs_induksi > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_obs_induksi; ?>x Observasi Induksi Persalinan hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Obs Induksi Persalinan ke-<?php echo $jumlah_obs_induksi; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Obs Bayi -->
                    <?php if($jumlah_obs_bayi > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_obs_bayi; ?>x Observasi Bayi hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Obs Bayi ke-<?php echo $jumlah_obs_bayi; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Obs Restrain Nonfarma -->
                    <?php if($jumlah_obs_restrain > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_obs_restrain; ?>x Observasi Restrain Nonfarma hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Obs Restrain Nonfarma ke-<?php echo $jumlah_obs_restrain; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Obs Ventilatori -->
                    <?php if($jumlah_obs_ventilator > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_obs_ventilator; ?>x Observasi Ventilatori hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Obs Ventilatori ke-<?php echo $jumlah_obs_ventilator; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Obs Hemodialisa -->
                    <?php if($jumlah_obs_hemodialisa > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_obs_hemodialisa; ?>x Observasi Hemodialisa hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Obs Hemodialisa ke-<?php echo $jumlah_obs_hemodialisa; ?>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Balance Cairan -->
                    <?php if($jumlah_balance_cairan > 0): ?>
                    <span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center;" title="<?php echo $jumlah_balance_cairan; ?>x Balance Cairan hari ini">
                        <i class="material-icons" style="font-size: 12px; margin-right: 3px;">check_circle</i>
                        Balance Cairan ke-<?php echo $jumlah_balance_cairan; ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($total_tindakan > 0): ?>
            <!-- Tindakan & Pemeriksaan -->
            <div style="margin-bottom: 10px;">
                <div style="font-size: 11px; color: #888; margin-bottom: 6px;">Tindakan & Pemeriksaan :</div>
                
                <?php if(count($tindakan_dokter) > 0): ?>
                <!-- Tindakan Dokter -->
                <div style="margin-bottom: 8px;">
                    <div style="font-size: 10px; color: #667eea; font-weight: 600; margin-bottom: 4px;">
                        <i class="material-icons" style="font-size: 11px; vertical-align: middle;">medical_services</i> Dokter
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                        <?php foreach($tindakan_dokter as $idx => $td): 
                            $unique_id_dr = 'info-dokter-' . uniqid() . '-' . $idx;
                        ?>
                        <div style="position: relative; display: inline-block;">
                            <span style="background: #e3f2fd; color: #1565c0; padding: 3px 8px; border-radius: 8px; font-size: 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;" 
                                onmouseover="document.getElementById('<?php echo $unique_id_dr; ?>').style.display='block'" 
                                onmouseout="document.getElementById('<?php echo $unique_id_dr; ?>').style.display='none'">
                                <?php echo $td['tindakan']; ?><?php echo $td['jumlah'] > 1 ? ' <b>('.$td['jumlah'].'x)</b>' : ''; ?>
                                <i class="material-icons" style="font-size: 12px; color: #1565c0;">info</i>
                            </span>
                            <div id="<?php echo $unique_id_dr; ?>" style="display: none; position: absolute; background: #fff; border: 1px solid #1565c0; border-radius: 6px; padding: 8px 10px; font-size: 10px; color: #333; z-index: 9999; min-width: 200px; max-width: 300px; margin-top: 2px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); left: 0; white-space: normal; line-height: 1.4;">
                                <div style="font-weight: 600; color: #1565c0; margin-bottom: 5px; padding-bottom: 4px; border-bottom: 1px solid #e3f2fd;">👨‍⚕️ Dokter:</div>
                                <div><?php echo nl2br(htmlspecialchars($td['pelaksana'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if(count($tindakan_dokter_perawat) > 0): ?>
                <!-- Tindakan Dokter & Perawat -->
                <div style="margin-bottom: 8px;">
                    <div style="font-size: 10px; color: #00897b; font-weight: 600; margin-bottom: 4px;">
                        <i class="material-icons" style="font-size: 11px; vertical-align: middle;">group</i> Dokter & Perawat
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                        <?php foreach($tindakan_dokter_perawat as $idx => $tdp): 
                            $unique_id_drpr = 'info-dokter-perawat-' . uniqid() . '-' . $idx;
                        ?>
                        <div style="position: relative; display: inline-block;">
                            <span style="background: #e0f2f1; color: #00695c; padding: 3px 8px; border-radius: 8px; font-size: 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;" 
                                onmouseover="document.getElementById('<?php echo $unique_id_drpr; ?>').style.display='block'" 
                                onmouseout="document.getElementById('<?php echo $unique_id_drpr; ?>').style.display='none'">
                                <?php echo $tdp['tindakan']; ?><?php echo $tdp['jumlah'] > 1 ? ' <b>('.$tdp['jumlah'].'x)</b>' : ''; ?>
                                <i class="material-icons" style="font-size: 12px; color: #00695c;">info</i>
                            </span>
                            <div id="<?php echo $unique_id_drpr; ?>" style="display: none; position: absolute; background: #fff; border: 1px solid #00897b; border-radius: 6px; padding: 8px 10px; font-size: 10px; color: #333; z-index: 9999; min-width: 200px; max-width: 300px; margin-top: 2px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); left: 0; white-space: normal; line-height: 1.4;">
                                <div style="font-weight: 600; color: #00897b; margin-bottom: 5px; padding-bottom: 4px; border-bottom: 1px solid #e0f2f1;">👨‍⚕️ Dokter:</div>
                                <div style="margin-bottom: 6px;"><?php echo nl2br(htmlspecialchars($tdp['dokter'])); ?></div>
                                <div style="font-weight: 600; color: #00897b; margin-bottom: 5px; padding-bottom: 4px; border-bottom: 1px solid #e0f2f1;">👨‍⚕️ Perawat:</div>
                                <div><?php echo nl2br(htmlspecialchars($tdp['perawat'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if(count($tindakan_perawat) > 0): ?>
                <!-- Tindakan Perawat -->
                <div style="margin-bottom: 8px;">
                    <div style="font-size: 10px; color: #e65100; font-weight: 600; margin-bottom: 4px;">
                        <i class="material-icons" style="font-size: 11px; vertical-align: middle;">person</i> Perawat
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                        <?php foreach($tindakan_perawat as $idx => $tp): 
                            $unique_id_pr = 'info-perawat-' . uniqid() . '-' . $idx;
                        ?>
                        <div style="position: relative; display: inline-block;">
                            <span style="background: #fff3e0; color: #e65100; padding: 3px 8px; border-radius: 8px; font-size: 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer;" 
                                onmouseover="document.getElementById('<?php echo $unique_id_pr; ?>').style.display='block'" 
                                onmouseout="document.getElementById('<?php echo $unique_id_pr; ?>').style.display='none'">
                                <?php echo $tp['tindakan']; ?><?php echo $tp['jumlah'] > 1 ? ' <b>('.$tp['jumlah'].'x)</b>' : ''; ?>
                                <i class="material-icons" style="font-size: 12px; color: #e65100;">info</i>
                            </span>
                            <div id="<?php echo $unique_id_pr; ?>" style="display: none; position: absolute; background: #fff; border: 1px solid #e65100; border-radius: 6px; padding: 8px 10px; font-size: 10px; color: #333; z-index: 9999; min-width: 200px; max-width: 300px; margin-top: 2px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); left: 0; white-space: normal; line-height: 1.4;">
                                <div style="font-weight: 600; color: #e65100; margin-bottom: 5px; padding-bottom: 4px; border-bottom: 1px solid #fff3e0;">👨‍⚕️ Perawat:</div>
                                <div><?php echo nl2br(htmlspecialchars($tp['pelaksana'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if(count($obat_bhp) > 0): ?>
            <!-- Obat & BHP -->
            <div style="margin-bottom: 10px;">
                <div style="font-size: 11px; color: #888; margin-bottom: 6px;">Obat & BHP :</div>
                
                <div style="display: flex; gap: 8px;">
                    <!-- Kolom Kiri: BMHP -->
                    <div style="flex: 1; border: 1px solid #e0e0e0; border-radius: 6px; padding: 6px; background: #fafafa;">
                        <div style="font-size: 10px; font-weight: 600; color: #757575; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #e0e0e0;">
                            <i class="material-icons" style="font-size: 11px; vertical-align: middle;">inventory</i> BMHP/Obat Racikan
                        </div>
                        <div style="max-height: 150px; overflow-y: auto;">
                            <?php 
                            $ada_bmhp = false;
                            foreach($obat_bhp as $ob): 
                                if($ob['aturan'] == 'BMHP'):
                                    $ada_bmhp = true;
                                    $is_ralan = ($ob['status'] == 'Ralan');
                            ?>
                            <div style="display: flex; align-items: flex-start; gap: 5px; margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px dashed <?php echo $is_ralan ? '#ffcdd2' : '#e0e0e0'; ?>;<?php echo $is_ralan ? ' background: #ffebee; margin: 0 -4px 4px -4px; padding: 4px;' : ''; ?>" title="<?php echo $is_ralan ? 'Dari IGD/Rawat Jalan' : 'Ranap'; ?>">
                                <span style="background: <?php echo $is_ralan ? '#f44336' : '#e0e0e0'; ?>; color: <?php echo $is_ralan ? 'white' : '#757575'; ?>; padding: 2px 5px; border-radius: 3px; font-size: 9px; font-weight: 600; white-space: nowrap; min-width: 28px; text-align: center;">
                                    <?php echo $ob['jumlah']; ?>x
                                </span>
                                <span style="font-size: 10px; color: <?php echo $is_ralan ? '#c62828' : '#333'; ?>; line-height: 1.3; flex: 1;">
                                    <?php echo $ob['nama_brng']; ?><?php echo $is_ralan ? ' <span style="font-size:8px;background:#f44336;color:white;padding:1px 4px;border-radius:3px;">IGD/RJ</span>' : ''; ?>
                                </span>
                            </div>
                            <?php 
                                endif;
                            endforeach; 
                            if(!$ada_bmhp):
                            ?>
                            <div style="text-align: center; color: #999; font-size: 10px; padding: 10px;">Tidak ada BMHP</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Kolom Kanan: Obat yang Diresep -->
                    <div style="flex: 1; border: 1px solid #e8f5e9; border-radius: 6px; padding: 6px; background: #f1f8f4;">
                        <div style="font-size: 10px; font-weight: 600; color: #2e7d32; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #c8e6c9;">
                            <i class="material-icons" style="font-size: 11px; vertical-align: middle;">inventory</i> Obat yang Diresep
                        </div>
                        <div style="max-height: 150px; overflow-y: auto;">
                            <?php 
                            $ada_obat = false;
                            foreach($obat_bhp as $ob): 
                                if($ob['aturan'] != 'BMHP'):
                                    $ada_obat = true;
                                    $is_ralan_obat = ($ob['status'] == 'Ralan');
                            ?>
                            <div style="display: flex; align-items: flex-start; gap: 5px; margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px dashed <?php echo $is_ralan_obat ? '#ffcdd2' : '#c8e6c9'; ?>;<?php echo $is_ralan_obat ? ' background: #ffebee; margin: 0 -4px 4px -4px; padding: 4px; border-radius: 4px;' : ''; ?>" title="<?php echo $is_ralan_obat ? 'Dari IGD/Rawat Jalan' : 'Ranap'; ?>">
                                <span style="background: <?php echo $is_ralan_obat ? '#f44336' : '#c8e6c9'; ?>; color: <?php echo $is_ralan_obat ? 'white' : '#2e7d32'; ?>; padding: 2px 5px; border-radius: 3px; font-size: 9px; font-weight: 600; white-space: nowrap; min-width: 28px; text-align: center;">
                                    <?php echo $ob['jumlah']; ?>x
                                </span>
                                <span style="font-size: 10px; color: <?php echo $is_ralan_obat ? '#c62828' : '#333'; ?>; line-height: 1.3; flex: 1;">
                                    <b><?php echo $ob['nama_brng']; ?></b><?php echo $is_ralan_obat ? ' <span style="font-size:8px;background:#f44336;color:white;padding:1px 4px;border-radius:3px;">IGD/RJ</span>' : ''; ?>
                                    <br><span style="color: <?php echo $is_ralan_obat ? '#c62828' : '#666'; ?>; font-size: 9px;">📋 <?php echo $ob['aturan']; ?></span>
                                </span>
                            </div>
                            <?php 
                                endif;
                            endforeach; 
                            if(!$ada_obat):
                            ?>
                            <div style="text-align: center; color: #999; font-size: 10px; padding: 10px;">Tidak ada obat</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php if(count($lab_pk_hari_ini) > 0): ?>
            <!-- Laboratorium Hari Ini -->
            <div style="margin-bottom: 10px;">
                <div style="font-size: 11px; color: #888; margin-bottom: 6px;">Laboratorium Hari Ini :</div>
                
                <!-- Kolom Lab PK dengan Hasil Kritis di Dalamnya -->
                <div style="border: 1px solid #e8f5e9; border-radius: 6px; padding: 8px; background: #f1f8f4;">
                    <div style="font-size: 10px; font-weight: 600; color: #2e7d32; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #c8e6c9; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="material-icons" style="font-size: 11px; vertical-align: middle;">science</i> LABORATORIUM PK</span>
                        <?php 
                        $total_lab = count($lab_pk_hari_ini);
                        $lab_kritis_count = count(array_filter($lab_pk_hari_ini, function($l) {
                            return $l['status'] == 'high' || $l['status'] == 'low';
                        }));
                        ?>
                        <span style="font-size: 9px; color: #666;">
                            <?php echo $total_lab; ?> pemeriksaan
                            <?php if($lab_kritis_count > 0): ?>
                            <span style="background: #f44336; color: white; padding: 2px 6px; border-radius: 8px; margin-left: 4px;">
                                <i class="material-icons" style="font-size: 9px; vertical-align: middle;">warning</i> <?php echo $lab_kritis_count; ?> kritis
                            </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php foreach($lab_pk_hari_ini as $lab): 
                            // Tentukan style berdasarkan status
                            if($lab['status'] == 'pending') {
                                $bg_color = '#fff3e0';
                                $border_color = '#ff9800';
                                $text_color = '#e65100';
                                $icon = 'hourglass_empty';
                                $nilai_display = '<i>Menunggu hasil...</i>';
                            } elseif($lab['status'] == 'high') {
                                $bg_color = '#ffebee';
                                $border_color = '#f44336';
                                $text_color = '#c62828';
                                $icon = 'arrow_upward';
                                $nilai_display = '<b>'.$lab['nilai'].'</b>'.(!empty($lab['satuan']) ? ' <span style="color:#888;font-size:9px;">'.$lab['satuan'].'</span>' : '');
                            } elseif($lab['status'] == 'low') {
                                $bg_color = '#e3f2fd';
                                $border_color = '#2196f3';
                                $text_color = '#1565c0';
                                $icon = 'arrow_downward';
                                $nilai_display = '<b>'.$lab['nilai'].'</b>'.(!empty($lab['satuan']) ? ' <span style="color:#888;font-size:9px;">'.$lab['satuan'].'</span>' : '');
                            } else {
                                $bg_color = '#fff';
                                $border_color = '#e0e0e0';
                                $text_color = '#333';
                                $icon = 'check';
                                $nilai_display = $lab['nilai'].(!empty($lab['satuan']) ? ' <span style="color:#888;font-size:9px;">'.$lab['satuan'].'</span>' : '');
                            }
                        ?>
                        <div style="display: flex; align-items: center; gap: 8px; padding: 6px 8px; margin-bottom: 4px; background: <?php echo $bg_color; ?>; border-left: 3px solid <?php echo $border_color; ?>; border-radius: 4px;">
                            <span style="flex-shrink: 0; width: 24px; height: 24px; background: <?php echo $border_color; ?>; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                <i class="material-icons" style="font-size: 14px;"><?php echo $icon; ?></i>
                            </span>
                            <span style="flex: 1; font-size: 10px; color: <?php echo $text_color; ?>; font-weight: 500;">
                                <?php echo $lab['pemeriksaan']; ?>
                            </span>
                            <span style="flex-shrink: 0; font-size: 10px; color: <?php echo $text_color; ?>; font-weight: 600; min-width: 80px; text-align: right;">
                                <?php echo $nilai_display; ?>
                                <?php if($lab['status'] == 'high'): ?>
                                <span style="color: #c62828; font-size: 12px; margin-left: 2px;">↑</span>
                                <?php elseif($lab['status'] == 'low'): ?>
                                <span style="color: #1565c0; font-size: 12px; margin-left: 2px;">↓</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if($lab_kritis_count > 0): ?>
                    <!-- Peringatan Hasil Kritis -->
                    <div style="margin-top: 8px; padding: 6px 8px; background: #fff3e0; border-left: 3px solid #ff9800; border-radius: 4px;">
                        <div style="font-size: 9px; color: #e65100; font-weight: 600; margin-bottom: 2px;">
                            <i class="material-icons" style="font-size: 10px; vertical-align: middle;">warning</i> PERHATIAN
                        </div>
                        <div style="font-size: 9px; color: #666; line-height: 1.4;">
                            Terdapat <?php echo $lab_kritis_count; ?> hasil laboratorium dengan nilai kritis. Mohon perhatikan dan tindak lanjuti sesuai protokol.
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if(count($diet_hari_ini) > 0): ?>
            <!-- Diet Hari Ini -->
            <div style="margin-bottom: 10px;">
                <div style="font-size: 11px; color: #888; margin-bottom: 6px;">Diet Hari Ini :</div>
                <div style="border: 1px solid #e8f5e9; border-radius: 6px; padding: 8px; background: #f9fbe7;">
                    <div style="font-size: 10px; font-weight: 600; color: #558b2f; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #dcedc8; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="material-icons" style="font-size: 11px; vertical-align: middle;">restaurant</i> DIET PASIEN</span>
                        <span style="font-size: 9px; color: #666;"><?php echo count($diet_hari_ini); ?> pemberian</span>
                    </div>
                    <div style="max-height: 160px; overflow-y: auto;">
                        <?php foreach($diet_hari_ini as $diet):
                            // Tentukan ikon & warna berdasarkan waktu
                            $waktu_lower = strtolower($diet['waktu']);
                            if(strpos($waktu_lower, 'pagi') !== false) {
                                $icon_waktu = '🌅'; $bg_waktu = '#fff9c4'; $color_waktu = '#f57f17';
                            } elseif(strpos($waktu_lower, 'siang') !== false) {
                                $icon_waktu = '☀️'; $bg_waktu = '#fff3e0'; $color_waktu = '#e65100';
                            } elseif(strpos($waktu_lower, 'malam') !== false) {
                                $icon_waktu = '🌙'; $bg_waktu = '#e8eaf6'; $color_waktu = '#283593';
                            } else {
                                $icon_waktu = '🍽️'; $bg_waktu = '#f3e5f5'; $color_waktu = '#6a1b9a';
                            }
                        ?>
                        <div style="display: flex; align-items: center; gap: 8px; padding: 5px 8px; margin-bottom: 4px; background: #fff; border-radius: 6px; border: 1px solid #dcedc8;">
                            <span style="background: <?php echo $bg_waktu; ?>; color: <?php echo $color_waktu; ?>; padding: 3px 8px; border-radius: 8px; font-size: 9px; font-weight: 700; white-space: nowrap; min-width: 50px; text-align: center;">
                                <?php echo $icon_waktu; ?> <?php echo ucfirst($diet['waktu']); ?>
                            </span>
                            <span style="font-size: 10px; color: #333; flex: 1; line-height: 1.3;">
                                <?php echo htmlspecialchars($diet['nama_diet'] ?: '-'); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        
        <!-- Ringkasan Klinis -->
        <div style="flex: 1; min-width: 280px; background: white; padding: 12px 15px; border-radius: 8px; border: 1px solid #e8e8e8;">
            <div style="font-size: 11px; font-weight: 600; color: #e91e63; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid #e91e63; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 4px;">assignment</i>Ringkasan Klinis
            </div>
            
            <!-- Info Pasien -->
            <div style="margin-bottom: 10px;">
                <table style="width: 100%; font-size: 11px; line-height: 1.5;">
                    <!-- <tr>
                        <td style="color: #888; width: 85px; padding: 2px 0; vertical-align: top;">Tgl Masuk</td>
                        <td style="color: #333; font-weight: 600; padding: 2px 0;">: <?php echo date('d/m/Y H:i', strtotime($dataPasien['tgl_masuk'].' '.$dataPasien['jam_masuk'])); ?></td>
                    </tr>
                    <tr>
                        <td style="color: #888; padding: 2px 0; vertical-align: top;">Ruangan</td>
                        <td style="color: #333; padding: 2px 0;">: <?php echo $dataPasien['nm_bangsal']; ?> (<?php echo $dataPasien['kd_kamar']; ?>)</td>
                    </tr>
                    <tr>
                        <td style="color: #888; padding: 2px 0; vertical-align: top;">Cara Bayar</td>
                        <td style="padding: 2px 0;">: <span style="background: #4caf50; color: white; padding: 1px 6px; border-radius: 6px; font-size: 9px; font-weight: 600;"><?php echo strtoupper($dataPasien['png_jawab']); ?></span></td>
                    </tr> -->
                    <tr>
                        <td style="color: #888; padding: 2px 0; vertical-align: top;">Diagnosa Awal Masuk</td>
                        <td style="color: #333; padding: 2px 0;">: <?php echo $dataPasien['diagnosa_awal'] ?: '-'; ?></td>
                    </tr>
                </table>
            </div>
            
            <?php if(count($lab_kritis) > 0): ?>
            <!-- Hasil Lab Kritis -->
            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #ddd;">
                <div style="font-size: 10px; color: #d32f2f; font-weight: 600; margin-bottom: 6px;">
                    <i class="material-icons" style="font-size: 11px; vertical-align: middle;">warning</i> Hasil Lab Kritis
                </div>
                <div style="max-height: 120px; overflow-y: auto;">
                    <?php 
                    $current_date = '';
                    foreach($lab_kritis as $lk): 
                        // Group by tanggal
                        if($current_date != $lk['tanggal']):
                            $current_date = $lk['tanggal'];
                    ?>
                    <div style="font-size: 9px; color: #666; margin-top: 6px; margin-bottom: 3px; font-weight: 600;">
                        📅 <?php echo date('d/m/Y', strtotime($lk['tanggal'])); ?>
                    </div>
                    <?php endif; ?>
                    <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 3px;">
                        <span style="background: <?php echo $lk['keterangan'] == 'H' ? '#ffebee' : '#e3f2fd'; ?>; 
                                     color: <?php echo $lk['keterangan'] == 'H' ? '#c62828' : '#1565c0'; ?>; 
                                     padding: 2px 6px; border-radius: 6px; font-size: 9px; font-weight: 600; min-width: 18px; text-align: center;">
                            <?php echo $lk['keterangan'] == 'H' ? '↑' : '↓'; ?>
                        </span>
                        <span style="font-size: 10px; color: #333;">
                            <?php echo $lk['pemeriksaan']; ?>: <b><?php echo $lk['nilai']; ?></b><?php echo !empty($lk['satuan']) ? ' <span style="color:#888;font-size:9px;">'.$lk['satuan'].'</span>' : ''; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
<?php if(count($data_gds_grafik) > 0): ?>
<!-- Grafik GDS -->
<div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #ddd;">
    <div style="font-size: 10px; color: #1976d2; font-weight: 600; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
        <span>
            <i class="material-icons" style="font-size: 11px; vertical-align: middle;">show_chart</i> Grafik GDS (Gula Darah Sewaktu)
        </span>
        <span style="font-size: 9px; color: #888; font-weight: normal;">
            <?php echo count($data_gds_grafik); ?> data
        </span>
    </div>
    
<!-- Canvas untuk grafik -->
<div style="position: relative; height: 180px; margin-bottom: 8px; overflow: hidden; background: #fafafa; border-radius: 4px; padding: 5px;">
    <canvas id="gdsChart_<?php echo md5($no_rawat); ?>" 
            data-gds='<?php echo json_encode($data_gds_grafik); ?>'
            style="max-width: 100%; height: 170px; display: block;"></canvas>
</div>
    
    <!-- Legend/Keterangan -->
    <div style="display: flex; gap: 10px; font-size: 9px; color: #666; justify-content: center; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 3px;">
            <div style="width: 10px; height: 10px; background: #4caf50; border-radius: 2px;"></div>
            <span>Normal (&lt;140)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 3px;">
            <div style="width: 10px; height: 10px; background: #ff9800; border-radius: 2px;"></div>
            <span>Waspada (140-199)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 3px;">
            <div style="width: 10px; height: 10px; background: #f44336; border-radius: 2px;"></div>
            <span>Tinggi (≥200)</span>
        </div>
    </div>
    
    <!-- Data terakhir -->
    <?php 
    $gds_terakhir = end($data_gds_grafik);
    $status_gds = 'Normal';
    $bg_status = '#e8f5e9';
    $text_status = '#2e7d32';
    if($gds_terakhir['gdp'] >= 200) {
        $status_gds = 'Tinggi';
        $bg_status = '#ffebee';
        $text_status = '#c62828';
    } elseif($gds_terakhir['gdp'] >= 140) {
        $status_gds = 'Waspada';
        $bg_status = '#fff3e0';
        $text_status = '#e65100';
    }
    ?>
    <div style="background: <?php echo $bg_status; ?>; padding: 6px 8px; border-radius: 4px; margin-top: 6px; border-left: 3px solid <?php echo $text_status; ?>;">
        <div style="font-size: 9px; color: #666; margin-bottom: 2px;">
            Terakhir: <?php echo date('d/m/Y H:i', strtotime($gds_terakhir['tanggal'].' '.$gds_terakhir['jam'])); ?>
        </div>
        <div style="font-size: 12px; font-weight: 600; color: <?php echo $text_status; ?>;">
            <?php echo $gds_terakhir['gdp']; ?> mg/dL <span style="font-size: 9px; font-weight: 500;">(<?php echo $status_gds; ?>)</span>
        </div>
    </div>
</div>
<?php endif; ?>
            
            <?php if(count($diagnosa_icd10) > 0): ?>
            <!-- Diagnosa ICD-10 -->
            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #ddd;">
                <div style="font-size: 10px; color: #7b1fa2; font-weight: 600; margin-bottom: 6px;">
                    <i class="material-icons" style="font-size: 11px; vertical-align: middle;">local_hospital</i> Diagnosa (ICD-10)
                </div>
                <div style="max-height: 100px; overflow-y: auto;">
                    <?php foreach($diagnosa_icd10 as $idx => $dx): ?>
                    <div style="display: flex; align-items: flex-start; gap: 5px; margin-bottom: 4px;">
                        <span style="background: <?php echo $dx['prioritas'] == 1 ? '#7b1fa2' : '#9c27b0'; ?>; 
                                     color: white; 
                                     padding: 1px 5px; border-radius: 4px; font-size: 8px; font-weight: 600; white-space: nowrap;">
                            <?php echo $dx['prioritas'] == 1 ? 'P' : 'S'.($dx['prioritas']-1); ?>
                        </span>
                        <span style="font-size: 10px; color: #333; line-height: 1.3;">
                            <b style="color: #7b1fa2;"><?php echo $dx['kode']; ?></b> - <?php echo $dx['nama']; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if(count($prosedur_icd9) > 0): ?>
            <!-- Prosedur ICD-9 -->
            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #ddd;">
                <div style="font-size: 10px; color: #00838f; font-weight: 600; margin-bottom: 6px;">
                    <i class="material-icons" style="font-size: 11px; vertical-align: middle;">healing</i> Prosedur (ICD-9)
                </div>
                <div style="max-height: 100px; overflow-y: auto;">
                    <?php foreach($prosedur_icd9 as $pr): ?>
                    <div style="display: flex; align-items: flex-start; gap: 5px; margin-bottom: 4px;">
                        <span style="background: #00838f; color: white; padding: 1px 5px; border-radius: 4px; font-size: 8px; font-weight: 600; white-space: nowrap;">
                            <?php echo $pr['kode']; ?>
                        </span>
                        <span style="font-size: 10px; color: #333; line-height: 1.3;">
                            <?php echo $pr['deskripsi']; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if(count($data_operasi) > 0): ?>
            <!-- Operasi/VK -->
            <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #ddd;">
                <div style="font-size: 10px; color: #ad1457; font-weight: 600; margin-bottom: 6px;">
                    <i class="material-icons" style="font-size: 11px; vertical-align: middle;">content_cut</i> Operasi/VK
                </div>
                <div style="max-height: 100px; overflow-y: auto;">
                    <?php foreach($data_operasi as $op): ?>
                    <div style="display: flex; align-items: flex-start; gap: 5px; margin-bottom: 4px;">
                        <span style="background: #ad1457; color: white; padding: 1px 5px; border-radius: 4px; font-size: 8px; font-weight: 600; white-space: nowrap;">
                            <?php echo date('d/m/Y', strtotime($op['tanggal'])); ?>
                        </span>
                        <span style="font-size: 10px; color: #333; line-height: 1.3;">
                            <?php echo $op['tindakan']; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Risiko Jatuh -->
    <?php if($risiko_jatuh): 
        // Tentukan warna berdasarkan hasil skrining
        $hasil_lower = strtolower($risiko_jatuh['hasil']);
        if(strpos($hasil_lower, 'tinggi') !== false || strpos($hasil_lower, 'high') !== false) {
            $bg_color = '#ffebee'; $border_color = '#f44336'; $text_color = '#c62828'; $icon = 'error';
        } elseif(strpos($hasil_lower, 'sedang') !== false || strpos($hasil_lower, 'medium') !== false) {
            $bg_color = '#fff3e0'; $border_color = '#ff9800'; $text_color = '#e65100'; $icon = 'warning';
        } else {
            $bg_color = '#e8f5e9'; $border_color = '#4caf50'; $text_color = '#2e7d32'; $icon = 'check_circle';
        }
    ?>
    <div style="background: <?php echo $bg_color; ?>; border-left: 3px solid <?php echo $border_color; ?>; padding: 8px 12px; border-radius: 0 6px 6px 0; margin-top: 12px;">
        <div style="display: flex; align-items: flex-start;">
            <i class="material-icons" style="color: <?php echo $border_color; ?>; font-size: 18px; margin-right: 8px;"><?php echo $icon; ?></i>
            <div style="flex: 1;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                    <span style="color: <?php echo $text_color; ?>; font-size: 11px; font-weight: 600;">Risiko Jatuh (<?php echo $risiko_jatuh['kategori']; ?>):</span>
                    <span style="background: <?php echo $border_color; ?>; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;">
                        <?php echo $risiko_jatuh['hasil']; ?>
                    </span>
                </div>
                <?php if(!empty($risiko_jatuh['saran'])): ?>
                <div style="color: #555; font-size: 10px; line-height: 1.4;">
                    <strong>Saran:</strong> <?php echo $risiko_jatuh['saran']; ?>
                </div>
                <?php endif; ?>
                <div style="color: #888; font-size: 9px; margin-top: 3px;">
                    Dinilai oleh: <?php echo $risiko_jatuh['petugas']; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>