<?php	
     session_start();
     
     // Hapus session dokter
     $_SESSION["ses_dokter"] = null;
     unset($_SESSION["ses_dokter"]);
     
     // ✅ Hapus session hak akses
     $_SESSION["hak_akses"] = null;
     unset($_SESSION["hak_akses"]);
     
     // ✅ Hapus session timeout
     $_SESSION["last_activity"] = null;
     unset($_SESSION["last_activity"]);
     
     // Atau bisa langsung hapus semua session dengan:
     // session_unset(); // Hapus semua variabel session
     
     // Hancurkan session
     session_destroy();
     
     // Redirect ke index (yang akan redirect ke login)
     exit(header("Location:../index.php"));
?>