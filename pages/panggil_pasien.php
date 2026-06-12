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
    
    // Untuk INSERT: ambil "insert into nama_tabel VALUES (...)"
    if (stripos($query_clean, 'INSERT INTO') !== false) {
        preg_match('/INSERT INTO\s+(\w+)\s*\(/i', $query_clean, $matches);
        $table_name = isset($matches[1]) ? $matches[1] : '';
        
        $values_pos = stripos($query_clean, 'VALUES');
        if ($values_pos !== false && !empty($table_name)) {
            $values_part = trim(substr($query_clean, $values_pos));
            $query_clean = "E-Dokter insert into {$table_name} {$values_part}";
        }
    }
    // Untuk UPDATE: "update nama_tabel set ..."
    elseif (stripos($query_clean, 'UPDATE') !== false) {
        $query_clean = "E-Dokter " . trim($query_clean);
        // Ubah jadi lowercase
        $query_clean = preg_replace('/UPDATE/i', 'update', $query_clean);
        $query_clean = preg_replace('/SET/i', 'set', $query_clean);
        $query_clean = preg_replace('/WHERE/i', 'where', $query_clean);
    }
    // Untuk DELETE: "delete from nama_tabel where ..."
    elseif (stripos($query_clean, 'DELETE') !== false) {
        $query_clean = trim($query_clean);
        // Ubah jadi lowercase
        $query_clean = preg_replace('/DELETE/i', 'delete', $query_clean);
        $query_clean = preg_replace('/FROM/i', 'from', $query_clean);
        $query_clean = preg_replace('/WHERE/i', 'where', $query_clean);
        // Tambah prefix E-Dokter
        $query_clean = "E-Dokter " . $query_clean;
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
    
    // Query kd_poli dari reg_periksa
    $query_poli = "SELECT kd_poli FROM reg_periksa WHERE no_rawat = '$no_rawat' LIMIT 1";
    $result_poli = bukaquery($query_poli);
    
    if (!$result_poli) {
        throw new Exception('Query error: ' . mysqli_error($koneksi));
    }
    
    if (mysqli_num_rows($result_poli) == 0) {
        throw new Exception('Data registrasi tidak ditemukan untuk no_rawat: ' . $no_rawat);
    }
    
    $rs_poli = mysqli_fetch_array($result_poli);
    $kd_poli = $rs_poli['kd_poli'];
    
    // DELETE data lama dari antripoli (hanya untuk dokter dan poli yang sama)
    $query_delete = "DELETE FROM antripoli WHERE kd_dokter = '$kd_dokter' AND kd_poli = '$kd_poli'";
    $result_delete = bukaquery($query_delete);
    
    if (!$result_delete) {
        throw new Exception('Gagal menghapus data lama: ' . mysqli_error($koneksi));
    }
    
    // TRACKING: Simpan query delete
    insertTracker($query_delete);
    
    // INSERT data baru ke antripoli
    $status = '1';
    $query_insert = "INSERT INTO antripoli (kd_dokter, kd_poli, status, no_rawat) 
                     VALUES ('$kd_dokter', '$kd_poli', '$status', '$no_rawat')";
    $result_insert = bukaquery($query_insert);
    
    if (!$result_insert) {
        throw new Exception('Gagal insert data: ' . mysqli_error($koneksi));
    }
    
    // TRACKING: Simpan query insert
    insertTracker($query_insert);
    
    // Response sukses
    echo json_encode([
        'status' => 'success', 
        'message' => 'Pasien berhasil dipanggil',
        'data' => [
            'kd_dokter' => $kd_dokter,
            'kd_poli' => $kd_poli,
            'no_rawat' => $no_rawat
        ]
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('Error panggil_pasien.php: ' . $e->getMessage());
    
    // Response error
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}

exit();
?>