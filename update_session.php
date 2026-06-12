<?php
session_start();

// Set header JSON
header('Content-Type: application/json');

// Cek apakah user sudah login
if (isset($_SESSION['ses_dokter'])) {
    
    // Update waktu aktivitas terakhir
    $_SESSION['last_activity'] = time();
    
    // Return status updated
    echo json_encode([
        'status' => 'updated',
        'timestamp' => $_SESSION['last_activity'],
        'time' => date('Y-m-d H:i:s', $_SESSION['last_activity'])
    ]);
    
} else {
    // Jika belum login
    echo json_encode([
        'status' => 'not_logged_in',
        'message' => 'User belum login'
    ]);
}
?>