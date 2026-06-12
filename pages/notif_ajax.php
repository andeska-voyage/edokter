<?php
/**
 * notif_ajax.php - AJAX Handler untuk Notifikasi
 * UNIFIED VERSION - Support mark all untuk semua tipe sekaligus
 */

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

ob_start();

session_start();
require_once('../conf/conf.php');
require_once('notif_cache.php');

ob_clean();

if(!isset($_SESSION['ses_dokter'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
$notifCache = new NotifCache($kd_dokter);

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch($action) {
    
    // Mark single notifikasi sebagai dibaca
    case 'mark_read':
        $notif_id = isset($_POST['notif_id']) ? $_POST['notif_id'] : '';
        $notif_type = isset($_POST['notif_type']) ? $_POST['notif_type'] : 'lab';
        
        if (!empty($notif_id)) {
            switch($notif_type) {
                case 'rad_hasil':
                    $notifCache->markRadHasilAsRead($notif_id);
                    break;
                case 'rad_gambar':
                    $notifCache->markRadGambarAsRead($notif_id);
                    break;
                default:
                    $notifCache->markLabAsRead($notif_id);
            }
            echo json_encode(['status' => 'success', 'message' => 'Marked as read']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid notif_id']);
        }
        break;
    
    // Mark semua notifikasi sebagai dibaca (per tipe)
    case 'mark_all_read':
        $notif_ids = isset($_POST['notif_ids']) ? json_decode($_POST['notif_ids'], true) : [];
        $notif_type = isset($_POST['notif_type']) ? $_POST['notif_type'] : 'lab';
        
        if (!empty($notif_ids) && is_array($notif_ids)) {
            switch($notif_type) {
                case 'rad_hasil':
                    $notifCache->markAllRadHasilAsRead($notif_ids);
                    break;
                case 'rad_gambar':
                    $notifCache->markAllRadGambarAsRead($notif_ids);
                    break;
                default:
                    $notifCache->markAllLabAsRead($notif_ids);
            }
            echo json_encode(['status' => 'success', 'message' => 'All marked as read']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid notif_ids']);
        }
        break;
    
    // ✅ NEW: Mark ALL notifikasi (semua tipe) sebagai dibaca
    case 'mark_all_read_unified':
        $all_ids = isset($_POST['all_ids']) ? json_decode($_POST['all_ids'], true) : [];
        
        if (!empty($all_ids) && is_array($all_ids)) {
            $lab_ids = [];
            $rad_hasil_ids = [];
            $rad_gambar_ids = [];
            
            // Pisahkan berdasarkan tipe
            foreach ($all_ids as $item) {
                $id = is_array($item) ? $item['id'] : $item;
                $type = is_array($item) ? ($item['type'] ?? 'lab') : 'lab';
                
                switch($type) {
                    case 'rad_hasil':
                        $rad_hasil_ids[] = $id;
                        break;
                    case 'rad_gambar':
                        $rad_gambar_ids[] = $id;
                        break;
                    default:
                        $lab_ids[] = $id;
                }
            }
            
            // Mark semua
            if (!empty($lab_ids)) {
                $notifCache->markAllLabAsRead($lab_ids);
            }
            if (!empty($rad_hasil_ids)) {
                $notifCache->markAllRadHasilAsRead($rad_hasil_ids);
            }
            if (!empty($rad_gambar_ids)) {
                $notifCache->markAllRadGambarAsRead($rad_gambar_ids);
            }
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'All notifications marked as read',
                'marked' => [
                    'lab' => count($lab_ids),
                    'rad_hasil' => count($rad_hasil_ids),
                    'rad_gambar' => count($rad_gambar_ids)
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid all_ids']);
        }
        break;
    
    // Update last seen
    case 'update_last_seen':
        $notifCache->updateLastSeen();
        echo json_encode(['status' => 'success', 'last_seen' => $notifCache->getLastSeen()]);
        break;
    
    // Dismiss semua notifikasi
    case 'dismiss_all':
        $notifCache->dismissAll();
        echo json_encode(['status' => 'success', 'message' => 'All dismissed']);
        break;
    
    // Get cache status
    case 'get_status':
        echo json_encode([
            'status' => 'success',
            'cache' => $notifCache->getCacheData()
        ]);
        break;
    
    // Get unread count
    case 'get_unread_count':
        $tanggal_hari_ini = date('Y-m-d');
        
        // Query LAB
        $queryCountLab = bukaquery("SELECT COUNT(*) as total
                                FROM periksa_lab pl
                                INNER JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
                                INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                WHERE pl.dokter_perujuk = '$kd_dokter'
                                AND pl.tgl_periksa = '$tanggal_hari_ini'
                                AND p.nm_pasien IS NOT NULL
                                AND p.nm_pasien != ''");
        
        $total_lab = 0;
        if($queryCountLab && $row = mysqli_fetch_array($queryCountLab)) {
            $total_lab = (int)$row['total'];
        }
        
        // Query RADIOLOGI HASIL
        $queryCountRadHasil = bukaquery("SELECT COUNT(*) as total
                                FROM periksa_radiologi pr
                                INNER JOIN hasil_radiologi hr ON pr.no_rawat = hr.no_rawat
                                INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                                INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                WHERE pr.dokter_perujuk = '$kd_dokter'
                                AND pr.tgl_periksa = '$tanggal_hari_ini'
                                AND hr.hasil IS NOT NULL AND hr.hasil != ''
                                AND p.nm_pasien IS NOT NULL AND p.nm_pasien != ''");
        
        $total_rad_hasil = 0;
        if($queryCountRadHasil && $row = mysqli_fetch_array($queryCountRadHasil)) {
            $total_rad_hasil = (int)$row['total'];
        }
        
        // Query RADIOLOGI GAMBAR
        $queryCountRadGambar = bukaquery("SELECT COUNT(*) as total
                                FROM periksa_radiologi pr
                                INNER JOIN gambar_radiologi gr ON pr.no_rawat = gr.no_rawat
                                INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                                INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                WHERE pr.dokter_perujuk = '$kd_dokter'
                                AND pr.tgl_periksa = '$tanggal_hari_ini'
                                AND gr.lokasi_gambar IS NOT NULL AND gr.lokasi_gambar != ''
                                AND p.nm_pasien IS NOT NULL AND p.nm_pasien != ''");
        
        $total_rad_gambar = 0;
        if($queryCountRadGambar && $row = mysqli_fetch_array($queryCountRadGambar)) {
            $total_rad_gambar = (int)$row['total'];
        }
        
        $total_count = $total_lab + $total_rad_hasil + $total_rad_gambar;
        
        $read_lab = count($notifCache->getReadLabIds());
        $read_rad_hasil = count($notifCache->getReadRadHasilIds());
        $read_rad_gambar = count($notifCache->getReadRadGambarIds());
        
        $unread_lab = max(0, $total_lab - $read_lab);
        $unread_rad_hasil = max(0, $total_rad_hasil - $read_rad_hasil);
        $unread_rad_gambar = max(0, $total_rad_gambar - $read_rad_gambar);
        $unread_count = $unread_lab + $unread_rad_hasil + $unread_rad_gambar;
        
        echo json_encode([
            'status' => 'success',
            'unread_count' => $unread_count,
            'total_count' => $total_count,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;
    
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

ob_end_flush();
exit();
