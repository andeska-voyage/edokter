<?php
// Timeout 1 jam (3600 detik)
$timeout_duration = 3600;

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
            
            // ✅ Redirect ke login di pages/
            echo "<script>
                    alert('Session Anda telah habis karena tidak ada aktivitas selama 1 jam. Silahkan login kembali.');
                    window.location.href='pages/login.php';
                  </script>";
            exit;
        }
    }
    
    // Update waktu aktivitas terakhir
    $_SESSION['last_activity'] = time();
    
} else {
    // ✅ Jika belum login, redirect ke pages/login.php
    header('Location: pages/login.php');
    exit;
}
?>