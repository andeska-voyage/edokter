<?php
/**
 * Reset Mulai Periksa
 * Menghapus waktu diterima (jam mulai) dari mutasi_berkas
 * Mengembalikan status ke "Sudah Dikirim"
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once('../conf/conf.php');

// Set timezone Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

// Matikan display error agar tidak merusak JSON
ini_set('display_errors', 0);
error_reporting(0);

// Set header JSON
header('Content-Type: application/json');

// Validasi session
if (!isset($_SESSION["ses_dokter"])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Session expired atau belum login'
    ]);
    exit();
}

// ========================================
// FUNGSI TRACKING
// ========================================
function insertTracker($full_query) {
    $user = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    $tanggal = date('Y-m-d H:i:s');
    
    $query_clean = $full_query;
    
    if (stripos($query_clean, 'UPDATE') !== false) {
        $query_clean = "E-Dokter " . trim($query_clean);
        $query_clean = preg_replace('/UPDATE/i', 'update', $query_clean);
        $query_clean = preg_replace('/SET/i', 'set', $query_clean);
        $query_clean = preg_replace('/WHERE/i', 'where', $query_clean);
    }
    
    $query_clean = preg_replace('/\s+/', ' ', $query_clean);
    $query_clean = trim($query_clean);
    $query_escaped = str_replace("'", "''", $query_clean);
    
    $query = "INSERT INTO trackersql (tanggal, sqle, usere) 
              VALUES ('$tanggal', '$query_escaped', '$user')";
    
    bukaquery($query);
}

// Validasi request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit();
}

try {
    // Ambil POST data
    $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
    
    if (empty($no_rawat)) {
        throw new Exception('No. Rawat tidak valid');
    }
    
    // Ambil kode dokter dari session
    $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    
    if (empty($kd_dokter)) {
        throw new Exception('Session dokter tidak valid');
    }
    
    // Validasi: Cek apakah no_rawat ini milik dokter yang login
    $query_cek = "SELECT no_rawat FROM reg_periksa 
                  WHERE no_rawat = '$no_rawat' 
                  AND kd_dokter = '$kd_dokter' 
                  LIMIT 1";
    $result_cek = bukaquery($query_cek);
    
    if (mysqli_num_rows($result_cek) == 0) {
        throw new Exception('Data registrasi tidak ditemukan atau bukan milik Anda');
    }
    
    // ========================================
    // RESET MUTASI BERKAS - Hapus waktu diterima
    // ========================================
    $query_reset = "UPDATE mutasi_berkas 
                    SET status = 'Sudah Dikirim', 
                        diterima = NULL,
                        kembali = NULL
                    WHERE no_rawat = '$no_rawat'";
    
    $result_reset = bukaquery($query_reset);
    
    if (!$result_reset) {
        throw new Exception('Gagal reset waktu pemeriksaan');
    }
    
    // TRACKING
    insertTracker($query_reset);
    
    // Response sukses
    echo json_encode([
        'status' => 'success', 
        'message' => 'Waktu pemeriksaan berhasil direset',
        'data' => [
            'no_rawat' => $no_rawat,
            'status' => 'Sudah Dikirim'
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error reset_mulai_periksa.php: ' . $e->getMessage());
    
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}

exit();
?>
