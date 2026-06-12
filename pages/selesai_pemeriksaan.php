<?php
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
// FUNGSI TRACKING (FORMAT RINGKAS + CLEAN)
// ========================================
function insertTracker($full_query) {
    $user = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    
    // Gunakan waktu server SEKARANG dengan timezone yang sudah diset
    $tanggal = date('Y-m-d H:i:s');
    
    $query_clean = $full_query;
    
    // Untuk UPDATE: "update nama_tabel set ..."
    if (stripos($query_clean, 'UPDATE') !== false) {
        $query_clean = "E-Dokter " . trim($query_clean);
        // Ubah jadi lowercase
        $query_clean = preg_replace('/UPDATE/i', 'update', $query_clean);
        $query_clean = preg_replace('/SET/i', 'set', $query_clean);
        $query_clean = preg_replace('/WHERE/i', 'where', $query_clean);
    }
    
    // Hapus spasi berlebih
    $query_clean = preg_replace('/\s+/', ' ', $query_clean);
    $query_clean = preg_replace('/\s*\(\s*/', '(', $query_clean);
    $query_clean = preg_replace('/\s*\)\s*/', ')', $query_clean);
    $query_clean = preg_replace('/\s*,\s*/', ',', $query_clean);
    $query_clean = trim($query_clean);
    
    // Escape single quote
    $query_escaped = str_replace("'", "''", $query_clean);
    
    // Insert ke trackersql
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
        throw new Exception('No. Rawat tidak valid (kosong)');
    }
    
    // Ambil kode dokter dari session
    $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    
    if (empty($kd_dokter)) {
        throw new Exception('Session dokter tidak valid. Silakan login kembali.');
    }
    
    // Validasi: Cek apakah no_rawat ini memang milik dokter yang login
    $query_cek = "SELECT no_rawat FROM reg_periksa 
                  WHERE no_rawat = '$no_rawat' 
                  AND kd_dokter = '$kd_dokter' 
                  LIMIT 1";
    $result_cek = bukaquery($query_cek);
    
    if (mysqli_num_rows($result_cek) == 0) {
        throw new Exception('Data registrasi tidak ditemukan atau bukan milik Anda');
    }
    
    // ========================================
    // 1. UPDATE REG_PERIKSA - STATUS SUDAH
    // ========================================
    $query_update_reg = "UPDATE reg_periksa 
                         SET stts = 'Sudah' 
                         WHERE no_rawat = '$no_rawat'";
    
    $result_update_reg = bukaquery($query_update_reg);
    
    if (!$result_update_reg) {
        throw new Exception('Gagal update status registrasi: ' . mysqli_error($koneksi));
    }
    
    // TRACKING: Simpan query update reg_periksa
    insertTracker($query_update_reg);
    
    // ========================================
    // 2. UPDATE MUTASI BERKAS - SUDAH KEMBALI
    // ========================================
    $tanggal_now = date('Y-m-d H:i:s');
    
    $query_update_mutasi = "UPDATE mutasi_berkas 
                            SET status = 'Sudah Kembali', 
                                kembali = '$tanggal_now' 
                            WHERE no_rawat = '$no_rawat'";
    
    $result_update_mutasi = bukaquery($query_update_mutasi);
    
    if (!$result_update_mutasi) {
        throw new Exception('Gagal update status berkas: ' . mysqli_error($koneksi));
    }
    
    // TRACKING: Simpan query update mutasi_berkas
    insertTracker($query_update_mutasi);
    
    // Response sukses
    echo json_encode([
        'status' => 'success', 
        'message' => 'Pemeriksaan selesai. Berkas sudah dikembalikan.',
        'data' => [
            'no_rawat' => $no_rawat,
            'reg_status' => 'Sudah',
            'berkas_status' => 'Sudah Kembali',
            'kembali' => $tanggal_now
        ]
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Error selesai_pemeriksaan.php: ' . $e->getMessage());
    
    // Response error
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}

exit();
?>