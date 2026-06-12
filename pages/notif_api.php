<?php
/**
 * notif_api.php - API Endpoint untuk Notifikasi Real-time
 * UNIFIED VERSION - Semua notifikasi digabung, sort by waktu terbaru
 * + Deep Link ke Tab Lab/Radiologi dengan filter no_rawat
 */

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once(__DIR__ . "/../conf/conf.php");
    require_once(__DIR__ . "/notif_cache.php");
    
    ob_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    if(!isset($_SESSION['ses_dokter'])) {
        throw new Exception("Session expired");
    }
    
    if (!function_exists('encrypt_decrypt') || !function_exists('bukaquery')) {
        throw new Exception("Required functions not found");
    }
    
    $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    
    if (empty($kd_dokter)) {
        throw new Exception("Invalid doctor code");
    }
    
    $notifCache = new NotifCache($kd_dokter);
    $tanggal_hari_ini = date('Y-m-d');
    
    /**
     * Tentukan act= berdasarkan status_lanjut dan status_bayar:
     *
     * | status_lanjut | status_bayar  | act=                  |
     * |---------------|---------------|-----------------------|
     * | Ralan         | Belum Bayar   | Pemeriksaan           |
     * | Ralan         | Sudah Bayar   | Pemeriksaanriwayat    |
     * | Ranap         | Belum Bayar   | PemeriksaanInap       |
     * | Ranap         | Sudah Bayar   | Pemeriksaanriwayat    |
     */
    $buildNotifLink = function($no_rawat, $no_rkm_medis, $status_lanjut, $status_bayar, $tab) {
        if ($status_bayar == 'Sudah Bayar') {
            $act_page = 'Pemeriksaanriwayat';
        } elseif ($status_lanjut == 'Ranap') {
            $act_page = 'PemeriksaanInap';
        } else {
            $act_page = 'Pemeriksaan';
        }
        return 'index.php?act=' . $act_page
            . '&rnw=' . urlencode(encrypt_decrypt($no_rawat, 'e'))
            . '&rm='  . urlencode(encrypt_decrypt($no_rkm_medis, 'e'))
            . '&tab=' . $tab
            . '&filter_norawat=' . urlencode($no_rawat);
    };

    // Array untuk menampung SEMUA notifikasi
    $all_notifications = [];
    $all_ids = [];
    $total_unread = 0;
    
    // =============================================
    // 1. NOTIFIKASI HASIL LAB
    // =============================================
    
    $queryNotifLab = bukaquery("SELECT 
                                    pl.no_rawat,
                                    pl.tgl_periksa,
                                    pl.jam,
                                    pl.kd_jenis_prw,
                                    rp.no_rkm_medis,
                                    rp.status_lanjut,
                                    rp.status_bayar,
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
                                ORDER BY pl.jam DESC
                                LIMIT 50");
    
    if($queryNotifLab && mysqli_num_rows($queryNotifLab) > 0) {
        while($row = mysqli_fetch_array($queryNotifLab)) {
            if(empty(trim($row['nm_pasien']))) continue;
            
            $notif_id = $row['no_rawat'] . '_' . $row['tgl_periksa'] . '_' . $row['jam'] . '_' . $row['kd_jenis_prw'];
            $is_read = $notifCache->isLabRead($notif_id);
            
            if (!$is_read) $total_unread++;
            
            $link = $buildNotifLink(
                $row['no_rawat'],
                $row['no_rkm_medis'],
                $row['status_lanjut'],
                $row['status_bayar'],
                'lab'
            );
            
            $all_notifications[] = [
                'id' => $notif_id,
                'type' => 'lab',
                'type_label' => 'Hasil Lab',
                'icon' => 'biotech',
                'icon_class' => 'notif-icon-lab',
                'no_rawat' => $row['no_rawat'],
                'no_rkm_medis' => $row['no_rkm_medis'],
                'nm_pasien' => $row['nm_pasien'],
                'nama_pemeriksaan' => $row['nama_pemeriksaan'],
                'tgl_periksa' => $row['tgl_periksa'],
                'jam' => $row['jam'],
                'timestamp' => strtotime($row['tgl_periksa'] . ' ' . $row['jam']),
                'waktu' => date('H:i', strtotime($row['jam'])),
                'status_lanjut' => $row['status_lanjut'],
                'status_bayar' => $row['status_bayar'],
                'is_read' => $is_read,
                'link' => $link
            ];
            
            $all_ids[] = ['id' => $notif_id, 'type' => 'lab'];
        }
    }
    
    // =============================================
    // 2. NOTIFIKASI HASIL BACAAN RADIOLOGI
    // =============================================
    
    $queryRadHasil = bukaquery("SELECT 
                                    pr.no_rawat,
                                    pr.tgl_periksa,
                                    pr.jam,
                                    pr.kd_jenis_prw,
                                    rp.no_rkm_medis,
                                    rp.status_lanjut,
                                    rp.status_bayar,
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
            
            if (!$is_read) $total_unread++;
            
            $link = $buildNotifLink(
                $row['no_rawat'],
                $row['no_rkm_medis'],
                $row['status_lanjut'],
                $row['status_bayar'],
                'rad'
            );
            
            $all_notifications[] = [
                'id' => $notif_id,
                'type' => 'rad_hasil',
                'type_label' => 'Hasil Radiologi',
                'icon' => 'article',
                'icon_class' => 'notif-icon-rad-hasil',
                'no_rawat' => $row['no_rawat'],
                'no_rkm_medis' => $row['no_rkm_medis'],
                'nm_pasien' => $row['nm_pasien'],
                'nama_pemeriksaan' => $row['nama_pemeriksaan'],
                'tgl_periksa' => $row['tgl_periksa'],
                'jam' => $row['jam'],
                'timestamp' => strtotime($row['tgl_periksa'] . ' ' . $row['jam']),
                'waktu' => date('H:i', strtotime($row['jam'])),
                'status_lanjut' => $row['status_lanjut'],
                'status_bayar' => $row['status_bayar'],
                'is_read' => $is_read,
                'link' => $link
            ];
            
            $all_ids[] = ['id' => $notif_id, 'type' => 'rad_hasil'];
        }
    }
    
    // =============================================
    // 3. NOTIFIKASI GAMBAR RADIOLOGI
    // =============================================
    
    $queryRadGambar = bukaquery("SELECT 
                                    pr.no_rawat,
                                    pr.tgl_periksa,
                                    pr.jam,
                                    pr.kd_jenis_prw,
                                    rp.no_rkm_medis,
                                    rp.status_lanjut,
                                    rp.status_bayar,
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
                                ORDER BY pr.jam DESC
                                LIMIT 50");
    
    if($queryRadGambar && mysqli_num_rows($queryRadGambar) > 0) {
        while($row = mysqli_fetch_array($queryRadGambar)) {
            if(empty(trim($row['nm_pasien']))) continue;
            
            $notif_id = 'RAD_GAMBAR_' . $row['no_rawat'] . '_' . $row['tgl_periksa'] . '_' . $row['jam'];
            $is_read = $notifCache->isRadGambarRead($notif_id);
            
            if (!$is_read) $total_unread++;
            
            $link = $buildNotifLink(
                $row['no_rawat'],
                $row['no_rkm_medis'],
                $row['status_lanjut'],
                $row['status_bayar'],
                'rad'
            );
            
            $all_notifications[] = [
                'id' => $notif_id,
                'type' => 'rad_gambar',
                'type_label' => 'Gambar Radiologi',
                'icon' => 'perm_media',
                'icon_class' => 'notif-icon-rad-gambar',
                'no_rawat' => $row['no_rawat'],
                'no_rkm_medis' => $row['no_rkm_medis'],
                'nm_pasien' => $row['nm_pasien'],
                'nama_pemeriksaan' => $row['nama_pemeriksaan'],
                'tgl_periksa' => $row['tgl_periksa'],
                'jam' => $row['jam'],
                'timestamp' => strtotime($row['tgl_periksa'] . ' ' . $row['jam']),
                'waktu' => date('H:i', strtotime($row['jam'])),
                'status_lanjut' => $row['status_lanjut'],
                'status_bayar' => $row['status_bayar'],
                'is_read' => $is_read,
                'link' => $link
            ];
            
            $all_ids[] = ['id' => $notif_id, 'type' => 'rad_gambar'];
        }
    }
    
    // =============================================
    // SORT BY TIMESTAMP DESC (Terbaru di atas)
    // =============================================
    usort($all_notifications, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    // =============================================
    // BUILD RESPONSE
    // =============================================
    $response = [
        'success' => true,
        'timestamp' => time(),
        'total' => count($all_notifications),
        'total_unread' => $total_unread,
        'items' => $all_notifications,
        'all_ids' => $all_ids
    ];
    
    ob_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'API_ERROR',
        'timestamp' => time()
    ]);
}

ob_end_flush();
exit;
?>
