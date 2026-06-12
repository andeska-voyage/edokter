<?php
/**
 * receive_upload.php
 * Endpoint penerima upload file dari E-Dokter ke server berkasrawat
 * 
 * TARUH FILE INI DI SERVER BERKASRAWAT:
 * http://[IP_SERVER]/webapps/berkasrawat/receive_upload.php
 * 
 * Contoh: http://192.168.88.202/webapps/berkasrawat/receive_upload.php
 * 
 * Mendukung 2 aksi:
 * 1. Upload file (POST: secret, dest_path, file)
 * 2. Hapus file  (POST: secret, action=delete, dest_path)
 */

header('Content-Type: application/json; charset=utf-8');

// ============================================
// SECRET KEY - HARUS SAMA DENGAN DI conf.php
// ============================================
$SECRET_KEY = 'edokter_berkas_2026';

// ============================================
// VALIDASI SECRET
// ============================================
$secret = isset($_POST['secret']) ? $_POST['secret'] : '';
if ($secret !== $SECRET_KEY) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid secret key']);
    exit;
}

// Base directory (root folder berkasrawat)
$base_dir = __DIR__ . '/';

// ============================================
// AKSI: HAPUS FILE
// ============================================
$action = isset($_POST['action']) ? $_POST['action'] : 'upload';

if ($action === 'delete') {
    $dest_path = isset($_POST['dest_path']) ? $_POST['dest_path'] : '';
    
    if (empty($dest_path)) {
        echo json_encode(['status' => 'error', 'message' => 'dest_path tidak boleh kosong']);
        exit;
    }
    
    // Sanitasi path (cegah directory traversal)
    $dest_path = str_replace(['..', '\\'], ['', '/'], $dest_path);
    $full_path = $base_dir . $dest_path;
    
    // Pastikan masih di dalam base_dir
    $real_base = realpath($base_dir);
    $real_dir = realpath(dirname($full_path));
    if ($real_dir === false || strpos($real_dir, $real_base) !== 0) {
        echo json_encode(['status' => 'error', 'message' => 'Path tidak valid']);
        exit;
    }
    
    if (file_exists($full_path)) {
        if (unlink($full_path)) {
            echo json_encode(['status' => 'success', 'message' => 'File berhasil dihapus']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus file']);
        }
    } else {
        echo json_encode(['status' => 'success', 'message' => 'File sudah tidak ada']);
    }
    exit;
}

// ============================================
// AKSI: UPLOAD FILE
// ============================================
$dest_path = isset($_POST['dest_path']) ? $_POST['dest_path'] : '';

if (empty($dest_path)) {
    echo json_encode(['status' => 'error', 'message' => 'dest_path tidak boleh kosong']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $err_code = isset($_FILES['file']) ? $_FILES['file']['error'] : 'no file';
    echo json_encode(['status' => 'error', 'message' => 'File upload gagal, error code: ' . $err_code]);
    exit;
}

// Sanitasi path
$dest_path = str_replace(['..', '\\'], ['', '/'], $dest_path);
$full_path = $base_dir . $dest_path;

// Buat direktori tujuan jika belum ada
$dest_dir = dirname($full_path);
if (!is_dir($dest_dir)) {
    if (!mkdir($dest_dir, 0755, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membuat direktori: ' . $dest_dir]);
        exit;
    }
}

// Pastikan masih di dalam base_dir
$real_base = realpath($base_dir);
$real_dir = realpath($dest_dir);
if ($real_dir === false || strpos($real_dir, $real_base) !== 0) {
    echo json_encode(['status' => 'error', 'message' => 'Path tidak valid (directory traversal blocked)']);
    exit;
}

// Pindahkan file
if (move_uploaded_file($_FILES['file']['tmp_name'], $full_path)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'File berhasil diupload',
        'path' => $dest_path,
        'size' => filesize($full_path)
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan file ke: ' . $full_path]);
}
