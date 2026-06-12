<?php
/**
 * notif.php - Query Notifikasi untuk Dashboard Dokter
 * 
 * File ini berisi semua query notifikasi dengan file-based cache
 * untuk tracking read/unread tanpa menambah beban database
 */

// Pastikan session dan koneksi sudah ada
if(!isset($_SESSION['ses_dokter'])) {
    return;
}

// Include cache class
require_once(__DIR__ . '/notif_cache.php');

$kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
$tanggal_hari_ini = date('Y-m-d');

// Initialize cache
$notifCache = new NotifCache($kd_dokter);

// =============================================
// NOTIFIKASI HASIL LAB KELUAR HARI INI
// =============================================

$notif_hasil_lab = [];
$notif_lab_ids = [];        // Semua ID notifikasi
$notif_lab_unread = [];     // Notifikasi yang belum dibaca
$jumlah_notif_lab = 0;
$jumlah_notif_lab_unread = 0;

$queryNotifLab = bukaquery("SELECT 
                                pl.no_rawat,
                                pl.tgl_periksa,
                                pl.jam,
                                pl.kd_jenis_prw,
                                pl.dokter_perujuk,
                                rp.no_rkm_medis,
                                rp.status_lanjut,
                                p.nm_pasien,
                                COALESCE(jpl.nm_perawatan, 'Pemeriksaan Lab') as nama_pemeriksaan
                            FROM periksa_lab pl
                            INNER JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
                            INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                            LEFT JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw = jpl.kd_jenis_prw
                            WHERE pl.dokter_perujuk = '$kd_dokter'
                            AND pl.tgl_periksa = '$tanggal_hari_ini'
                            AND p.nm_pasien IS NOT NULL
                            AND p.nm_pasien != ''
                            ORDER BY pl.jam DESC");

if($queryNotifLab && mysqli_num_rows($queryNotifLab) > 0) {
    while($row = mysqli_fetch_array($queryNotifLab)) {
        // Skip jika nama pasien kosong
        if(empty(trim($row['nm_pasien']))) {
            continue;
        }
        
        // Generate unique ID untuk notifikasi ini
        $notif_id = $row['no_rawat'] . '_' . $row['tgl_periksa'] . '_' . $row['jam'] . '_' . $row['kd_jenis_prw'];
        
        // Cek apakah sudah dibaca dari cache
        $is_read = $notifCache->isLabRead($notif_id);
        
        $notif_data = [
            'id' => $notif_id,
            'no_rawat' => $row['no_rawat'],
            'no_rkm_medis' => $row['no_rkm_medis'],
            'nm_pasien' => $row['nm_pasien'],
            'nama_pemeriksaan' => $row['nama_pemeriksaan'] ?: 'Pemeriksaan Lab',
            'tgl_periksa' => $row['tgl_periksa'],
            'jam' => $row['jam'],
            'waktu' => date('H:i', strtotime($row['jam'])),
            'status_lanjut' => $row['status_lanjut'], // Ralan atau Ranap
            'is_read' => $is_read
        ];
        
        $notif_hasil_lab[] = $notif_data;
        $notif_lab_ids[] = $notif_id;
        
        if (!$is_read) {
            $notif_lab_unread[] = $notif_data;
        }
    }
    
    $jumlah_notif_lab = count($notif_hasil_lab);
    $jumlah_notif_lab_unread = count($notif_lab_unread);
}

// =============================================
// TOTAL SEMUA NOTIFIKASI (UNREAD)
// =============================================
$total_notifikasi = $jumlah_notif_lab;           // Total semua
$total_notifikasi_unread = $jumlah_notif_lab_unread; // Total belum dibaca

// Export untuk digunakan di AJAX handler
$GLOBALS['notifCache'] = $notifCache;
$GLOBALS['notif_lab_ids'] = $notif_lab_ids;

// =============================================
// NOTIFIKASI HASIL BACAAN RADIOLOGI HARI INI
// =============================================

$notif_rad_hasil = [];
$notif_rad_hasil_ids = [];
$notif_rad_hasil_unread = [];
$jumlah_notif_rad_hasil = 0;
$jumlah_notif_rad_hasil_unread = 0;

// Query: Ambil dari periksa_radiologi yang punya hasil di hasil_radiologi
$queryRadHasil = bukaquery("SELECT 
                                pr.no_rawat,
                                pr.tgl_periksa,
                                pr.jam,
                                pr.kd_jenis_prw,
                                pr.dokter_perujuk,
                                rp.no_rkm_medis,
                                rp.status_lanjut,
                                p.nm_pasien,
                                COALESCE(jpr.nm_perawatan, 'Pemeriksaan Radiologi') as nama_pemeriksaan
                            FROM periksa_radiologi pr
                            INNER JOIN hasil_radiologi hr ON pr.no_rawat = hr.no_rawat 
                                AND pr.tgl_periksa = hr.tgl_periksa 
                                AND pr.jam = hr.jam
                            INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                            INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                            LEFT JOIN jns_perawatan_radiologi jpr ON pr.kd_jenis_prw = jpr.kd_jenis_prw
                            WHERE pr.dokter_perujuk = '$kd_dokter'
                            AND pr.tgl_periksa = '$tanggal_hari_ini'
                            AND hr.hasil IS NOT NULL
                            AND TRIM(hr.hasil) != ''
                            AND CHAR_LENGTH(TRIM(hr.hasil)) > 0
                            AND p.nm_pasien IS NOT NULL
                            AND p.nm_pasien != ''
                            ORDER BY pr.jam DESC");

if($queryRadHasil && mysqli_num_rows($queryRadHasil) > 0) {
    while($row = mysqli_fetch_array($queryRadHasil)) {
        if(empty(trim($row['nm_pasien']))) continue;
        
        $notif_id = 'RAD_HASIL_' . $row['no_rawat'] . '_' . $row['tgl_periksa'] . '_' . $row['jam'];
        $is_read = $notifCache->isRadHasilRead($notif_id);
        
        $notif_data = [
            'id' => $notif_id,
            'no_rawat' => $row['no_rawat'],
            'no_rkm_medis' => $row['no_rkm_medis'],
            'nm_pasien' => $row['nm_pasien'],
            'nama_pemeriksaan' => $row['nama_pemeriksaan'] ?: 'Pemeriksaan Radiologi',
            'tgl_periksa' => $row['tgl_periksa'],
            'jam' => $row['jam'],
            'waktu' => date('H:i', strtotime($row['jam'])),
            'status_lanjut' => $row['status_lanjut'],
            'is_read' => $is_read
        ];
        
        $notif_rad_hasil[] = $notif_data;
        $notif_rad_hasil_ids[] = $notif_id;
        
        if (!$is_read) {
            $notif_rad_hasil_unread[] = $notif_data;
        }
    }
    
    $jumlah_notif_rad_hasil = count($notif_rad_hasil);
    $jumlah_notif_rad_hasil_unread = count($notif_rad_hasil_unread);
}

// =============================================
// NOTIFIKASI GAMBAR RADIOLOGI HARI INI
// =============================================

$notif_rad_gambar = [];
$notif_rad_gambar_ids = [];
$notif_rad_gambar_unread = [];
$jumlah_notif_rad_gambar = 0;
$jumlah_notif_rad_gambar_unread = 0;

// Query: Ambil dari periksa_radiologi yang punya gambar di gambar_radiologi
$queryRadGambar = bukaquery("SELECT 
                                pr.no_rawat,
                                pr.tgl_periksa,
                                pr.jam,
                                pr.kd_jenis_prw,
                                pr.dokter_perujuk,
                                rp.no_rkm_medis,
                                rp.status_lanjut,
                                p.nm_pasien,
                                COALESCE(jpr.nm_perawatan, 'Pemeriksaan Radiologi') as nama_pemeriksaan
                            FROM periksa_radiologi pr
                            INNER JOIN gambar_radiologi gr ON pr.no_rawat = gr.no_rawat
                            INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                            INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                            LEFT JOIN jns_perawatan_radiologi jpr ON pr.kd_jenis_prw = jpr.kd_jenis_prw
                            WHERE pr.dokter_perujuk = '$kd_dokter'
                            AND pr.tgl_periksa = '$tanggal_hari_ini'
                            AND gr.lokasi_gambar IS NOT NULL
                            AND gr.lokasi_gambar != ''
                            AND p.nm_pasien IS NOT NULL
                            AND p.nm_pasien != ''
                            ORDER BY pr.jam DESC");

if($queryRadGambar && mysqli_num_rows($queryRadGambar) > 0) {
    while($row = mysqli_fetch_array($queryRadGambar)) {
        if(empty(trim($row['nm_pasien']))) continue;
        
        $notif_id = 'RAD_GAMBAR_' . $row['no_rawat'] . '_' . $row['tgl_periksa'] . '_' . $row['jam'];
        $is_read = $notifCache->isRadGambarRead($notif_id);
        
        $notif_data = [
            'id' => $notif_id,
            'no_rawat' => $row['no_rawat'],
            'no_rkm_medis' => $row['no_rkm_medis'],
            'nm_pasien' => $row['nm_pasien'],
            'nama_pemeriksaan' => $row['nama_pemeriksaan'] ?: 'Pemeriksaan Radiologi',
            'tgl_periksa' => $row['tgl_periksa'],
            'jam' => $row['jam'],
            'waktu' => date('H:i', strtotime($row['jam'])),
            'status_lanjut' => $row['status_lanjut'],
            'is_read' => $is_read
        ];
        
        $notif_rad_gambar[] = $notif_data;
        $notif_rad_gambar_ids[] = $notif_id;
        
        if (!$is_read) {
            $notif_rad_gambar_unread[] = $notif_data;
        }
    }
    
    $jumlah_notif_rad_gambar = count($notif_rad_gambar);
    $jumlah_notif_rad_gambar_unread = count($notif_rad_gambar_unread);
}

// =============================================
// UPDATE TOTAL NOTIFIKASI (INCLUDE RADIOLOGI)
// =============================================
$total_notifikasi = $jumlah_notif_lab + $jumlah_notif_rad_hasil + $jumlah_notif_rad_gambar;
$total_notifikasi_unread = $jumlah_notif_lab_unread + $jumlah_notif_rad_hasil_unread + $jumlah_notif_rad_gambar_unread;

// Export untuk digunakan di AJAX handler
$GLOBALS['notif_rad_hasil_ids'] = $notif_rad_hasil_ids;
$GLOBALS['notif_rad_gambar_ids'] = $notif_rad_gambar_ids;

?>
