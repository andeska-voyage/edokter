<?php
session_start();

// Timeout 1 jam (3600 detik)
$timeout_duration = 3600;

// Set header JSON
header('Content-Type: application/json');

// Cek apakah user sudah login
if (isset($_SESSION['ses_dokter'])) {
    
    // Cek apakah ada waktu terakhir aktivitas
    if (isset($_SESSION['last_activity'])) {
        
        // Hitung waktu idle
        $elapsed_time = time() - $_SESSION['last_activity'];
        
        // Jika idle lebih dari 1 jam, logout
        if ($elapsed_time > $timeout_duration) {
            // Hapus semua session
            session_unset();
            session_destroy();
            
            // Return status timeout
            echo json_encode([
                'status' => 'timeout',
                'message' => 'Session habis karena tidak ada aktivitas selama 1 jam'
            ]);
            exit;
        }
        
        // Hitung sisa waktu
        $remaining = $timeout_duration - $elapsed_time;
        
        // Return status active
        echo json_encode([
            'status' => 'active',
            'remaining' => $remaining,
            'remaining_minutes' => round($remaining / 60)
        ]);
        
    } else {
        // Jika tidak ada last_activity, set sekarang
        $_SESSION['last_activity'] = time();
        
        echo json_encode([
            'status' => 'active',
            'remaining' => $timeout_duration,
            'remaining_minutes' => 60
        ]);
    }
    
} else {
    // Jika belum login
    echo json_encode([
        'status' => 'not_logged_in',
        'message' => 'User belum login'
    ]);
}
?>