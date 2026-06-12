<?php
// Matikan semua output buffer dan error display
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Jangan tampilkan error di output

header('Content-Type: application/json; charset=utf-8');

session_start();
require_once('../conf/conf.php');

try {
    // Validasi session - aktifkan kembali setelah testing
    if(!isset($_SESSION["ses_dokter"])){
        throw new Exception('Session expired atau belum login');
    }
    
// Query database - hanya tampilkan yang status aktif dan kategori PK (Patologi Klinis)
$query = "SELECT kd_jenis_prw, nm_perawatan, kategori 
          FROM jns_perawatan_lab 
          WHERE status='1' 
          ORDER BY nm_perawatan ASC";
    $result = bukaquery($query);
    
    if (!$result) {
        throw new Exception('Query gagal: ' . mysqli_error($GLOBALS['koneksi']));
    }
    
    $data = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = array(
            'kd_jenis_prw' => $row['kd_jenis_prw'],
            'nm_perawatan' => $row['nm_perawatan'],
            'kategori' => $row['kategori']
        );
    }
    
    // Bersihkan output buffer sebelum output JSON
    ob_end_clean();
    
    // Output JSON
    echo json_encode(array(
        'status' => 'success',
        'count' => count($data),
        'data' => $data
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Bersihkan output buffer
    ob_end_clean();
    
    // Log error ke file
    error_log("get_pemeriksaan_lab.php ERROR: " . $e->getMessage());
    
    echo json_encode(array(
        'status' => 'error',
        'message' => $e->getMessage(),
        'count' => 0,
        'data' => array()
    ), JSON_UNESCAPED_UNICODE);
}

exit();
?>