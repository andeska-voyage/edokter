<?php
    // Set timezone ke WIB (Indonesia)
    date_default_timezone_set('Asia/Jakarta');

    $db_hostname            = "localhost"; 
    $db_username            = "sik";
    $db_password            = "xxx"; 
    $db_name                = "sik";

    define('APP_BASE_URL', '/edokter/');
    define('PHOTO_BASE_URL', 'http://192.168.0.2:4000/webapps2026/penggajian/');
    define('RADIOLOGI_BASE_URL', 'http://192.168.0.2:4000/webapps2026/radiologi/');
    define('USG_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanusg/');
    define('USG_GYNECOLOGI_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanusggynecologi/');
    define('USG_UROLOGI_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanusgurologi/');
    define('USG_NEONATUS_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanusgneonatus/');
    define('ENDOSKOPI_FARING_LARING_BASE_URL', 'http://localhost2/webapps/hasilpemeriksaanendoskopifaringlaring/');
    define('ENDOSKOPI_HIDUNG_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanendoskopihidung/');
    define('ENDOSKOPI_TELINGA_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanendoskopitelinga/');
    define('PEMERIKSAAN_ECHO_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanecho/');
    define('PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanechopediatrik/');
    define('PEMERIKSAAN_SLIT_LAMP_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanslitlamp/');
    define('PEMERIKSAAN_OCT_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanoct/');
    define('PEMERIKSAAN_TREADMILL_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaantreadmill/');
    define('EKG_BASE_URL', 'http://192.168.0.2:4000/webapps2026/hasilpemeriksaanekg/');
    define('BERKAS_DIGITAL_BASE_URL', 'http://192.168.0.2:4000/webapps2026/berkasrawat/');

    // Orthanc PACS Configuration
    define('ORTHANC_URL', 'http://192.168.0.23');
    define('ORTHANC_PORT', '8042');
    define('ORTHANC_USER', 'admin');
    define('ORTHANC_PASS', 'admin123');
    

    // KODE VISITE INAP DOKTER UMUM JIKA ADA
    define('KD_DOKTER_UMUM', 'S0001');
    define('KD_DOKTER_ANESTESI', 'S0008');

    // KODE BERKAS DIGITAL (sesuaikan dengan master_berkas_digital di RS masing-masing)
    define('KD_BERKAS_PENANDAAN_OPERASI', '015'); // 005 = BERKAS DIGITAL di master_berkas_digital

    // PATH LOKAL UNTUK GENERATE PDF SEMENTARA (di dalam direktori edokter)
    define('PDF_LOCAL_DIR', $_SERVER['DOCUMENT_ROOT'] . APP_BASE_URL . 'pdf/');

    // SECRET KEY untuk upload ke server berkasrawat (samakan di receive_upload.php)
    define('BERKAS_UPLOAD_SECRET', 'edokter_berkas_2026');

    // ============================================
    // KONFIGURASI API iCare BPJS
    // ============================================
    define('ICARE_API_URL', 'https://apijkn.bpjs-kesehatan.go.id/wsihs/api/rs');
    define('ICARE_CONS_ID', '29513');
    define('ICARE_SECRET_KEY', '7gA350D99B');
    define('ICARE_USER_KEY', '88f50cf542182200a5244c14197fb23e');

    // FITUR ITERASI RESEP (BPJS) Set true untuk mengaktifkan, false untuk menonaktifkan
    define('FITUR_ITERASI_RESEP', true);


    // ✅ BUAT KONEKSI GLOBAL SEKALI SAJA
    if (!isset($GLOBALS['db_conn']) || !$GLOBALS['db_conn']) {
        $GLOBALS['db_conn'] = mysqli_connect($db_hostname, $db_username, $db_password, $db_name);
        
        if (!$GLOBALS['db_conn']) {
            die("<font color=red><h3>Not Connected ..!!</h3></font>");
        }
        
        mysqli_set_charset($GLOBALS['db_conn'], 'utf8mb4');
        
        // ✅ CACHE DAFTAR TABEL SEKALI DI AWAL
        $GLOBALS['existing_tables'] = [];
        $tables_result = mysqli_query($GLOBALS['db_conn'], "SHOW TABLES");
        while ($row = mysqli_fetch_array($tables_result)) {
            $GLOBALS['existing_tables'][] = $row[0];
        }
        mysqli_free_result($tables_result);
    }
    
    function host(){
        global $db_hostname;
        return $db_hostname;
    }
    
    // ✅ 1. bukakoneksi - Return koneksi global
    function bukakoneksi(){
        return $GLOBALS['db_conn'];
    }
     
    // ✅ 2. cleankar - Gunakan koneksi global, hapus close
    function cleankar($dirty){
        $konektor = $GLOBALS['db_conn'];
        
        if (get_magic_quotes_gpc()) {
            $clean = mysqli_real_escape_string($konektor, stripslashes($dirty));	 
        } else {
            $clean = mysqli_real_escape_string($konektor, $dirty);	
        } 
        
        return preg_replace('/[^a-zA-Z0-9\s_,@. ]/', '', $clean);
    }
    
    // ✅ 3. cleankar2 - Gunakan koneksi global, hapus close
    function cleankar2($dirty){
        $konektor = $GLOBALS['db_conn'];
        
        if (get_magic_quotes_gpc()) {
            $clean = mysqli_real_escape_string($konektor, stripslashes($dirty));	 
        } else {
            $clean = mysqli_real_escape_string($konektor, $dirty);	
        } 
        
        return $clean;
    }
    
    function antisqlinjection(){
       if(!get_magic_quotes_gpc()){
            $_GET = array_map('mysqli_real_escape_string', $_GET); 
            $_POST = array_map('mysqli_real_escape_string', $_POST); 
            $_COOKIE = array_map('mysqli_real_escape_string', $_COOKIE);
        }else{  
            $_GET = array_map('stripslashes', $_GET); 
            $_POST = array_map('stripslashes', $_POST); 
            $_COOKIE = array_map('stripslashes', $_COOKIE);
            $_GET = array_map('mysqli_real_escape_string', $_GET); 
            $_POST = array_map('mysqli_real_escape_string', $_POST); 
            $_COOKIE = array_map('mysqli_real_escape_string', $_COOKIE);
        }
        if (strlen($_SERVER['REQUEST_URI']) > 255 || strpos($_SERVER['REQUEST_URI'], "concat") || 
                strpos($_SERVER['REQUEST_URI'], "union") || strpos($_SERVER['REQUEST_URI'], "base64") || 
                strpos($_SERVER['REQUEST_URI'], "'")||strpos($_SERVER['REQUEST_URI'], "/")||
                strpos($_SERVER['REQUEST_URI'], "*")||strpos($_SERVER['REQUEST_URI'], ";")||
                strpos($_SERVER['REQUEST_URI'], "/*")||strpos($_SERVER['REQUEST_URI'], "\\")||
                strpos($_SERVER['REQUEST_URI'], "}")||strpos($_SERVER['REQUEST_URI'], "$")||
                strpos($_SERVER['REQUEST_URI'], "{")||strpos($_SERVER['REQUEST_URI'], "@")||
                strpos($_SERVER['REQUEST_URI'], "[")||strpos($_SERVER['REQUEST_URI'], "]")||
                strpos($_SERVER['REQUEST_URI'], "(")||strpos($_SERVER['REQUEST_URI'], ")")||
                strpos($_SERVER['REQUEST_URI'], "|")||strpos($_SERVER['REQUEST_URI'], ",")||
                strpos($_SERVER['REQUEST_URI'], "<")||strpos($_SERVER['REQUEST_URI'], ">")||
                strpos($_SERVER['REQUEST_URI'], "`")||strpos($_SERVER['REQUEST_URI'], ":")||
                strpos($_SERVER['REQUEST_URI'], "+")||strpos($_SERVER['REQUEST_URI'], "-")||
                strpos($_SERVER['REQUEST_URI'], "^")||strpos($_SERVER['REQUEST_URI'], "#")||
                strpos($_SERVER['REQUEST_URI'], "!")||strpos($_SERVER['REQUEST_URI'], "-")||
                strpos($_SERVER['REQUEST_URI'], "='")||strpos($_SERVER['REQUEST_URI'], "=/")) {
            echo "<b>Harus disetujui : <br/>
            Dilarang keras melakukan hacking/membajak Software/Web ini dengan cara apapun.<br/>
            Bagi yang sengaja melakukan hacking/membajak softaware ini,<br/>
            kami sumpahi sial 1000 turunan,miskin sampai 500 turunan.<br/>
            Selalu mendapat kecelakaan sampai 400 turunan. Anak pertama<br/>
            nya cacat tidak punya kaki sampai 300 turunan. Susah cari jodoh<br/>
            sampai umur 50 tahun sampai 200 turunan. Ya Alloh maafkan kami <br/>
            karena telah berdoa buruk, semua ini kami lakukan karena kami ti<br/>
            dak pernah rela karya kami dihack/dibajak..</b> ";
            Zet($hal);
            @header("HTTP/1.1 414 Request-URI Too Long");
            @header("Status: 414 Request-URI Too Long");
            @header("Connection: Close");
            @exit;
        }
    }
    
    // ✅ 4. mysql_safe_query - Gunakan koneksi global, hapus close
    function mysql_safe_query($format) {
        $konektor = $GLOBALS['db_conn'];
        $args = array_slice(func_get_args(), 1);
        $args = array_map('mysql_safe_string', $args);
        $query = vsprintf($format, $args);
        return mysqli_query($konektor, $query);
    }
    
    function validUrl($url){
        $format="/^(http|https):\/\/[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,5}(([0-9]{1,5})?\/.*)?$/";
        $url=strtolower($url);
        if(preg_match($format,$url)) return true; else return false;
    }
    
    function validTeks($data){
        $save=str_replace("'","",$data);
        $save=str_replace("\\","",$save);
        $save=str_replace(";","",$save);
        $save=str_replace("`","",$save);
        $save=str_replace("--","",$save);
        $save=str_replace("/*","",$save);
        $save=str_replace("*/","",$save);
        $save=str_replace("text/html","",$save);
        $save=str_replace("<script>","",$save);
        $save=str_replace("</script>","",$save);
        $save=str_replace("<noscript>","",$save);
        $save=str_replace("</noscript>","",$save);
        $save=str_replace("<img","",$save);
        $save=str_replace("document","",$save);
        $save=str_replace(" from ","",$save);
        $save=str_replace("concat","",$save);
        $save=str_replace("union","",$save);
        $save=str_replace("base64","",$save);
        $save=str_replace("//","",$save);
        $save=str_replace("*","",$save);
        $save=str_replace("}","",$save);
        $save=str_replace("$","",$save);
        $save=str_replace("{","",$save);
        $save=str_replace("@","",$save);
        $save=str_replace("[","",$save);
        $save=str_replace("]","",$save);
        $save=str_replace("(","",$save);
        $save=str_replace(")","",$save);
        $save=str_replace("|","",$save);
        $save=str_replace(",","",$save);
        $save=str_replace("<","",$save);
        $save=str_replace(">","",$save);
        $save=str_replace(":","",$save);
        $save=str_replace("+","",$save);
        $save=str_replace("^","",$save);
        $save=str_replace("#","",$save);
        $save=str_replace("!","",$save);
        $save=str_replace("='","",$save);
        $save=str_replace("=/","",$save);
        $save=str_replace("=","",$save);
        $save=str_replace("//","",$save);
        $save=str_replace("password","",$save);
        $save=str_replace("submit","",$save);
        $save=str_replace("input","",$save);
        $save=str_replace("meta","",$save);
        $save=str_replace("md5","",$save);
        $save=str_replace("pass","",$save);
        $save=str_replace("SESSION","",$save);
        $save=str_replace("login_shell","",$save);
        $save=str_replace("value","",$save);
        return $save;
    }
    
    function validTeks3($data,$panjang){
        $save="";
        if(strlen($data)>$panjang){
            header('Location: https://www.google.com');
        }else{
            $save=str_replace("'","",$data);
            $save=str_replace("\\","",$save);
            $save=str_replace(";","",$save);
            $save=str_replace("`","",$save);
            $save=str_replace("--","",$save);
            $save=str_replace("/*","",$save);
            $save=str_replace("*/","",$save);
            $save=str_replace("text/html","",$save);
            $save=str_replace("<script>","",$save);
            $save=str_replace("</script>","",$save);
            $save=str_replace("<noscript>","",$save);
            $save=str_replace("</noscript>","",$save);
            $save=str_replace("<img","",$save);
            $save=str_replace("document","",$save);
            $save=str_replace(" from ","",$save);
            $save=str_replace("concat","",$save);
            $save=str_replace("union","",$save);
            $save=str_replace("base64","",$save);
            $save=str_replace("//","",$save);
            $save=str_replace("*","",$save);
            $save=str_replace("}","",$save);
            $save=str_replace("$","",$save);
            $save=str_replace("{","",$save);
            $save=str_replace("@","",$save);
            $save=str_replace("[","",$save);
            $save=str_replace("]","",$save);
            $save=str_replace("(","",$save);
            $save=str_replace(")","",$save);
            $save=str_replace("|","",$save);
            $save=str_replace(",","",$save);
            $save=str_replace("<","",$save);
            $save=str_replace(">","",$save);
            $save=str_replace(":","",$save);
            $save=str_replace("+","",$save);
            $save=str_replace("^","",$save);
            $save=str_replace("#","",$save);
            $save=str_replace("!","",$save);
            $save=str_replace("='","",$save);
            $save=str_replace("=/","",$save);
            $save=str_replace("=","",$save);
            $save=str_replace("password","",$save);
            $save=str_replace("submit","",$save);
            $save=str_replace("input","",$save);
            $save=str_replace("meta","",$save);
            $save=str_replace("md5","",$save);
            $save=str_replace("pass","",$save);
            $save=str_replace("SESSION","",$save);
            $save=str_replace("login_shell","",$save);
            $save=str_replace("value","",$save);
        }
        return $save;
    }
    
    function validTeks4($data,$panjang){
        $save="";
        if(strlen($data)>$panjang){
            header('Location: https://www.google.com');
        }else{
            $save=str_replace("'","",$data);
            $save=str_replace("\\","",$save);
            $save=str_replace(";","",$save);
            $save=str_replace("`","",$save);
            $save=str_replace("--","",$save);
            $save=str_replace("/*","",$save);
            $save=str_replace("*/","",$save);
            $save=str_replace("text/html","",$save);
            $save=str_replace("<script>","",$save);
            $save=str_replace("</script>","",$save);
            $save=str_replace("<noscript>","",$save);
            $save=str_replace("</noscript>","",$save);
            $save=str_replace("<img","",$save);
            $save=str_replace("document","",$save);
            $save=str_replace(" from ","",$save);
            $save=str_replace("concat","",$save);
            $save=str_replace("union","",$save);
            $save=str_replace("base64","",$save);
            $save=str_replace("//","",$save);
            $save=str_replace("*","",$save);
            $save=str_replace("}","",$save);
            $save=str_replace("$","",$save);
            $save=str_replace("{","",$save);
            $save=str_replace("@","",$save);
            $save=str_replace("[","",$save);
            $save=str_replace("]","",$save);
            $save=str_replace("(","",$save);
            $save=str_replace(")","",$save);
            $save=str_replace("|","",$save);
            $save=str_replace(",","",$save);
            $save=str_replace("<","",$save);
            $save=str_replace(">","",$save);
            $save=str_replace("+","",$save);
            $save=str_replace("^","",$save);
            $save=str_replace("#","",$save);
            $save=str_replace("!","",$save);
            $save=str_replace("='","",$save);
            $save=str_replace("=/","",$save);
            $save=str_replace("=","",$save);
            $save=str_replace("password","",$save);
            $save=str_replace("submit","",$save);
            $save=str_replace("input","",$save);
            $save=str_replace("meta","",$save);
            $save=str_replace("md5","",$save);
            $save=str_replace("pass","",$save);
            $save=str_replace("SESSION","",$save);
            $save=str_replace("login_shell","",$save);
            $save=str_replace("value","",$save);
        }
        return $save;
    }
    
    function validangka($angka){
        if(!is_numeric($angka)) {
            return 0;
        }else{
            return $angka;
        }
    }

    // ✅ 5. tutupkoneksi - Jangan tutup koneksi global
    function tutupkoneksi(){
        // Koneksi global tidak ditutup manual
        // Akan otomatis tertutup saat script selesai
    }

    // ✅ 6. bukaquery - Gunakan koneksi global, hapus close
    function bukaquery($sql){    
        $konektor = $GLOBALS['db_conn'];
        $result = mysqli_query($konektor, $sql)
        or die("<br/><font color=red><b>Terjadi Kesalahan</b></font>".JSRedirect2("index.php?act=Home",4));
        return $result;
    }

    // ✅ 7. bukaquery_safe - Optimasi dengan cache tabel
    function bukaquery_safe($sql){
        $konektor = $GLOBALS['db_conn'];

        // Extract nama tabel
        if (preg_match('/FROM\s+(?:(?:sik|simrs|rsud)\.)?([a-zA-Z0-9_]+)/i', $sql, $m)) {
            $table = $m[1];

            // ✅ Cek dari cache, tidak query SHOW TABLES lagi!
            if (!in_array($table, $GLOBALS['existing_tables'])) {
                // Tabel tidak ada → return empty result
                return mysqli_query($konektor, "SELECT 1 WHERE 0");
            }
        }

        // Jalankan query
        $result = mysqli_query($konektor, $sql);

        // Jika query error
        if (!$result) {
            error_log("Query Error: " . mysqli_error($konektor) . " | SQL: " . $sql);
            return mysqli_query($konektor, "SELECT 1 WHERE 0");
        }

        return $result;
    }
     
    // ✅ 8. bukaquery2 - Gunakan koneksi global, hapus close
    function bukaquery2($sql){
        $konektor = $GLOBALS['db_conn'];
        $result = mysqli_query($konektor, $sql);
        return $result;
    }

    // ✅ 9. bukainput - Gunakan koneksi global, hapus close
    function bukainput($sql){
        $konektor = $GLOBALS['db_conn'];
        $result = mysqli_query($konektor, $sql)
        or die("<br/><font color=red><b>Gagal</b>");
        return $result;
    }
    
    // ✅ 10. bukainput2 - Gunakan koneksi global, hapus close
    function bukainput2($sql){
        $konektor = $GLOBALS['db_conn'];
        $result = mysqli_query($konektor, $sql);
        return $result;
    }

    // ✅ 11. hapusinput - Gunakan koneksi global, hapus close
    function hapusinput($sql){
        $konektor = $GLOBALS['db_conn'];
        $result = mysqli_query($konektor, $sql)
        or die("<font color=red><b>Gagal</b>, Data masih dipakai di tabel lain !");
        return $result;
    }
    
    function TO_DAYS($date) {
	if (is_numeric($date)) {
            $res = 719528 + (int) ($date / 86400);
	} else {
            $TZ = date_default_timezone_get();
            date_default_timezone_set('UTC');
            $res = 719528 + (int) (strtotime($date) / 86400);
            date_default_timezone_set($TZ);
	}
	return $res;
    }

    function Tambah($tabelname,$attrib,$pesan) {
        $command = bukainput("INSERT INTO ".$tabelname." VALUES (".$attrib.")");
        echo  "<img src='images/simpan.gif' />&nbsp;&nbsp; Data $pesan berhasil disimpan";
        return $command;
    }
     
    function Tambah2($tabelname,$attrib,$pesan) {
        $command = bukainput("INSERT INTO ".$tabelname." VALUES (".$attrib.")");
        echo  "<img src='images/simpan.gif' />&nbsp;&nbsp; <font size='9'>Data $pesan berhasil disimpan</font>";
        return $command;
    }
     
    function Tambah3($tabelname,$attrib) {
        $command = bukainput("INSERT INTO ".$tabelname." VALUES (".$attrib.")");
        return $command;
    }
    
    function Tambah4($tabelname,$attrib) {
        $command = bukainput2("INSERT INTO ".$tabelname." VALUES (".$attrib.")");
        return $command;
    }

    function InsertData($tabelname,$attrib) {
        $command = bukaquery("INSERT INTO ".$tabelname." VALUES (".$attrib.")");
        return $command;
    }
     
    function InsertData2($tabelname,$attrib) {
        $command = bukaquery2("INSERT INTO ".$tabelname." VALUES (".$attrib.")");
        return $command;
    }
     
    function EditData($tabelname,$attrib) {
        $command = bukaquery("UPDATE ".$tabelname." SET ".$attrib." ");
        return $command;
    }

    function Ubah($tabelname,$attrib,$pesan) {
        $command = bukaquery("UPDATE ".$tabelname." SET ".$attrib." ");
        echo  "<img src='images/simpan.gif' />&nbsp;&nbsp; Data $pesan berhasil diubah";
        return $command;
    }
     
    function Ubah2($tabelname,$attrib) {
        $command = bukaquery("UPDATE ".$tabelname." SET ".$attrib." ");
        return $command;
    }

    function Hapus($tabelname,$param,$hal) {
        $sql ="DELETE FROM ".$tabelname." WHERE ".$param." ";
        $command = hapusinput($sql);
        Zet($hal);
        return $command;
    }
     
    function Hapus2($tabelname,$param) {
        $sql ="DELETE FROM ".$tabelname." WHERE ".$param." ";
        $command = hapusinput($sql);
        return $command;
    }

    function HapusAll($tabelname) {
        $sql ="DELETE FROM ".$tabelname;
        $command = bukaquery($sql);
        return $command;
    }
     
    function deletegb($sql){
        $_sql         = $sql;
        $hasil        = bukaquery($_sql);
        $baris        = mysqli_fetch_row($hasil);
        $gb           = $baris[0];
        $hapus        = unlink($gb);
    }

    function JSRedirect($url){
        echo"<html><head><title></title><meta http-equiv='refresh' content='1;URL=$url'></head><body></body></html>";
    }
    
    function JSRedirect2($url,$time){
        echo"<html><head><title></title><meta http-equiv='refresh' content='$time;URL=$url'></head><body></body></html>";
    }

    // redirect to another page
    function redirect($location) {
        return header("Location: {$location}");
    }

    function Zet($url){
        echo"<html><head><title></title><meta http-equiv='refresh' content='0;URL=$url'></head><body></body></html>";
    }


    function JurusKibasNaga(){
        $id	= $_SERVER['REMOTE_ADDR'];
        $sql      = bukaquery("DELETE FROM tmp WHERE ID='$id'");
        return $sql;
    }


    function konversiTgl($tanggal){
        list($thn,$bln,$tgl)    = explode('-',$tanggal);
        $tmp                    = $tgl."-".$bln."-".$thn;
        return $tmp;
    }

    function konversiBulan($bln) {
        switch($bln) {
            case "01": $bulan="Januari"; break;
            case "02": $bulan="Februari"; break;
            case "03": $bulan="Maret"; break;
            case "04": $bulan="April"; break;
            case "05": $bulan="Mei"; break;
            case "06": $bulan="Juni"; break;
            case "07": $bulan="Juli"; break;
            case "08": $bulan="Agustus"; break;
            case "09": $bulan="September"; break;
            case "10": $bulan="Oktober"; break;
            case "11": $bulan="Nopember"; break;
            case "12": $bulan="Desember"; break;
            default  : $bulan="Tidak Boleh";
        }
        return $bulan;
    }

    function konversiHari($hari){
        switch($hari) {
            case "Sunday"       : $namahari="Akhad"; break;
            case "Monday"       : $namahari="Senin"; break;
            case "Tuesday"      : $namahari="Salasa"; break;
            case "Wednesday"    : $namahari="Rabu"; break;
            case "Thursday"     : $namahari="Kamis"; break;
            case "Friday"       : $namahari="Jumat"; break;
            case "Saturday"     : $namahari="Sabtu"; break;
            default             : $namahari="Tidak Boleh";
        }
        return $namahari;
    }
    
    function konversiTanggal($tanggal){
        list($thn,$bln,$tgl)=explode('-',$tanggal);
        $tmp = $tgl." ".konversiBulan($bln)." ".$thn;
        return $tmp;
    }

    function formatDuit($duit){
         return "Rp. ".number_format($duit,0,",",".").",-";
    }
        
    function formatDuit2($duit){
        return @number_format($duit,0,",",".")."";
    }
        
    function formatDec($duit){
        return round($duit);
    }
        
    function formatRound($duit){
        return str_replace(".",",",round($duit,5));
    }

    function JumlahBaris($result) {
        return mysqli_num_rows($result);
    }

    function getOne($sql) {
        $hasil=bukaquery($sql);
        list($result) =mysqli_fetch_array($hasil);
        return $result;
    }
    
    function getOne2($sql) {
        $hasil = bukaquery2($sql);
        list($result) = mysqli_fetch_array($hasil);
        return $result;
    }

    function cekKosong($sql) {
        $jum = mysqli_num_rows($sql);
        if ($jum==0) return true;
        else return false;
    }

    function loadTgl(){
        echo "<option>-&nbsp</option>";
        for($tgl=1; $tgl<=31; $tgl++){
            $tgl_leng=strlen($tgl);
            if ($tgl_leng==1)
            $i="0".$tgl;
            else
            $i=$tgl;                        
            echo "<option value=$i>$i</option>";
        }
    }

    function loadTglnow(){
        $tglsekarang=date('d');
        echo "<option>".$tglsekarang."</option>";
        for($tgl=1; $tgl<=31; $tgl++){
            $tgl_leng=strlen($tgl);
            if ($tgl_leng==1)
            $i="0".$tgl;
            else
            $i=$tgl;                        
            echo "<option value=$i>$i</option>";
        }
    }


    function loadTgl2(){
        for($tgl=1; $tgl<=31; $tgl++){
            $tgl_leng=strlen($tgl);
            if ($tgl_leng==1)
            $i="0".$tgl;
            else
            $i=$tgl;
            echo "<option value=$i>$i</option>";
        }
    }

    function loadBln(){
        echo "<option>-&nbsp</option>";
        for($bln=1; $bln<=12; $bln++){
            $bln_leng=strlen($bln);
            if ($bln_leng==1)
            $i="0".$bln;
            else
            $i=$bln;                        
            echo "<option value=$i>$i</option>";
        }
    }

    function loadBlnnow(){
        $blnsekarang=date('m');
        echo "<option>$blnsekarang</option>";
        for($bln=1; $bln<=12; $bln++){
            $bln_leng=strlen($bln);
            if ($bln_leng==1)
            $i="0".$bln;
            else
            $i=$bln;                        
            echo "<option value=$i>$i</option>";
        }
    }

    function loadBln2(){
        for($bln=1; $bln<=12; $bln++){
            $bln_leng=strlen($bln);
            if ($bln_leng==1)
            $i="0".$bln;
            else
            $i=$bln;                        
            echo "<option value=$i>$i</option>";
        }
    }

    function loadThn(){
        $thnini=date('Y');
        echo "<option>-&nbsp</option>";
        for($thn=$thnini; $thn>=1960; $thn--){
            $thn_leng=strlen($thn);
            if ($thn_leng==1)
            $i="0".$thn;
            else
            $i=$thn;                        
            echo "<option value=$i>$i</option>";
        }
    }


    function loadThnnow(){
        $thnini=date('Y');
        //echo "<option>-&nbsp</option>";
        for($thn=$thnini; $thn>=1960; $thn--){
            $thn_leng=strlen($thn);
            if ($thn_leng==1)
            $i="0".$thn;
            else
            $i=$thn;                        
            echo "<option value=$i>$i</option>";
        }
    }

    function loadThn2(){
        $thnini=date('Y');
        echo "<option>-&nbsp</option>";
        for($thn=$thnini+30; $thn>=1960; $thn--){
            $thn_leng=strlen($thn);
            if ($thn_leng==1)
            $i="0".$thn;
            else
            $i=$thn;
            echo "<option value=$i>$i</option>";
        }
    }
    function loadThn3(){
        $thnini=date('Y');
        for($thn=$thnini+30; $thn>=1960; $thn--){
            $thn_leng=strlen($thn);
            if ($thn_leng==1)
            $i="0".$thn;
            else
            $i=$thn;
            echo "<option value=$i>$i</option>";
        }
    }

    function loadThn4(){
        $thnini=date('Y');
        for($thn=$thnini+4; $thn>=$thnini; $thn--){
            $thn_leng=strlen($thn);
            if ($thn_leng==1)
            $i="0".$thn;
            else
            $i=$thn;                        
            echo "<option value=$i>$i</option>";
        }
    }

    function loadJam(){
        //echo "<option selected>-----&nbsp</option>";
        for($jam=0; $jam<=23; $jam++){
            $jam_leng=strlen($jam);
            if ($jam_leng==1)
            $i="0".$jam;
            else
            $i=$jam;                        
            echo "<option value=$i>$i</option>";
        }
    }

    function loadMenit(){
        //echo "<option selected>-----&nbsp</option>";
        for($menit=0; $menit<=60; $menit++){
            $menit_leng=strlen($menit);
            if ($menit_leng==1)
            $i="0".$menit;
            else
            $i=$menit;                        
            echo "<option value=$i>$i</option>";
        }
    }

    function autonomer($table,$strawal,$pnj){
        $hasil        = bukaquery($table);
        $s            = mysqli_num_rows($hasil)+1;
        $j            = strlen($s);         
        $s1           = "";
        for($i=1;$i<=$pnj-$j;$i++){
            $s1=$s1+"0";
        }

        return $strawal."".$s1."".$s;
    }

    function Terbilang($x){
        $abil = array("", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas");
        if ($x < 12)
          return " " . $abil[$x];
        elseif ($x < 20)
          return Terbilang($x - 10) . "belas";
        elseif ($x < 100)
          return Terbilang($x / 10) . " puluh" . Terbilang($x % 10);
        elseif ($x < 200)
          return " seratus" . Terbilang($x - 100);
        elseif ($x < 1000)
          return Terbilang($x / 100) . " ratus" . Terbilang($x % 100);
        elseif ($x < 2000)
          return " seribu" . Terbilang($x - 1000);
        elseif ($x < 1000000)
          return Terbilang($x / 1000) . " ribu" . Terbilang($x % 1000);
        elseif ($x < 1000000000)
          return Terbilang($x / 1000000) . " juta" . Terbilang($x % 1000000);
    }
    
    /**
     * Upload file ke server BERKAS_DIGITAL_BASE_URL via cURL
     * Jika server lokal (localhost) → langsung copy file
     * Jika server remote (IP lain) → upload via HTTP POST ke receive_upload.php
     * 
     * @param string $local_file_path  Path file lokal yang akan diupload
     * @param string $remote_relative  Path relatif tujuan (contoh: 'pages/upload/namafile.pdf')
     * @return array ['success' => bool, 'message' => string]
     */
    function uploadBerkasDigital($local_file_path, $remote_relative) {
        $base_url = defined('BERKAS_DIGITAL_BASE_URL') ? BERKAS_DIGITAL_BASE_URL : 'http://192.168.0.2:4000/webapps2026/berkasrawat/';
        $parsed = parse_url($base_url);
        $host = isset($parsed['host']) ? $parsed['host'] : 'localhost';
        
        // Cek apakah server lokal atau remote
        $is_local = in_array($host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        
        if ($is_local) {
            // === MODE LOKAL: langsung copy file ===
            $dest_dir = getBerkasDigitalLocalPath() . dirname($remote_relative);
            if (!is_dir($dest_dir)) {
                if (!mkdir($dest_dir, 0755, true)) {
                    return ['success' => false, 'message' => 'Gagal membuat direktori: ' . $dest_dir];
                }
            }
            $dest_path = getBerkasDigitalLocalPath() . $remote_relative;
            if (copy($local_file_path, $dest_path)) {
                return ['success' => true, 'message' => 'File berhasil disalin ke lokal'];
            } else {
                return ['success' => false, 'message' => 'Gagal menyalin file ke: ' . $dest_path];
            }
        } else {
            // === MODE REMOTE: upload via cURL ke receive_upload.php ===
            $upload_url = rtrim($base_url, '/') . '/receive_upload.php';
            $secret = defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '';
            
            if (!function_exists('curl_init')) {
                return ['success' => false, 'message' => 'cURL extension tidak tersedia'];
            }
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $upload_url,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POSTFIELDS => [
                    'secret' => $secret,
                    'dest_path' => $remote_relative,
                    'file' => new CURLFile($local_file_path, 'application/pdf', basename($local_file_path))
                ]
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                return ['success' => false, 'message' => 'cURL error: ' . $curl_error];
            }
            
            $result = json_decode($response, true);
            if ($http_code == 200 && isset($result['status']) && $result['status'] == 'success') {
                return ['success' => true, 'message' => $result['message'] ?? 'Upload berhasil'];
            } else {
                $err_msg = isset($result['message']) ? $result['message'] : 'HTTP ' . $http_code . ' - ' . substr($response, 0, 200);
                return ['success' => false, 'message' => 'Upload gagal: ' . $err_msg];
            }
        }
    }

    /**
     * Hapus file di server BERKAS_DIGITAL_BASE_URL
     * Jika lokal → unlink langsung
     * Jika remote → kirim request hapus via HTTP
     * 
     * @param string $remote_relative  Path relatif file (contoh: 'pages/upload/namafile.pdf')
     * @return array ['success' => bool, 'message' => string]
     */
    function hapusBerkasDigital($remote_relative) {
        $base_url = defined('BERKAS_DIGITAL_BASE_URL') ? BERKAS_DIGITAL_BASE_URL : 'http://192.168.0.2:4000/webapps2026/berkasrawat/';
        $parsed = parse_url($base_url);
        $host = isset($parsed['host']) ? $parsed['host'] : 'localhost';
        
        $is_local = in_array($host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        
        if ($is_local) {
            // === MODE LOKAL ===
            $file_path = getBerkasDigitalLocalPath() . $remote_relative;
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    return ['success' => true, 'message' => 'File berhasil dihapus'];
                } else {
                    return ['success' => false, 'message' => 'Gagal menghapus file'];
                }
            }
            return ['success' => true, 'message' => 'File tidak ditemukan (sudah terhapus)'];
        } else {
            // === MODE REMOTE ===
            $delete_url = rtrim($base_url, '/') . '/receive_upload.php';
            $secret = defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '';
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $delete_url,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_POSTFIELDS => [
                    'secret' => $secret,
                    'action' => 'delete',
                    'dest_path' => $remote_relative
                ]
            ]);
            
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                return ['success' => false, 'message' => 'cURL error: ' . $curl_error];
            }
            
            $result = json_decode($response, true);
            if (isset($result['status']) && $result['status'] == 'success') {
                return ['success' => true, 'message' => $result['message'] ?? 'File berhasil dihapus'];
            } else {
                return ['success' => false, 'message' => 'Hapus gagal: ' . ($result['message'] ?? $response)];
            }
        }
    }

    /**
     * Konversi BERKAS_DIGITAL_BASE_URL ke local filesystem path
     * Contoh: 'http://192.168.88.202/webapps/berkasrawat/' → 'C:/xampp/htdocs/webapps/berkasrawat/'
     * atau  : 'http://192.168.0.2:4000/webapps2026/berkasrawat/'      → 'C:/xampp/htdocs/webapps/berkasrawat/'
     */
    function getBerkasDigitalLocalPath() {
        $url = defined('BERKAS_DIGITAL_BASE_URL') ? BERKAS_DIGITAL_BASE_URL : 'http://192.168.0.2:4000/webapps2026/berkasrawat/';
        $parsed = parse_url($url);
        $path = isset($parsed['path']) ? $parsed['path'] : '/webapps/berkasrawat/';
        // Hapus trailing slash lalu tambahkan
        $path = rtrim($path, '/');
        return $_SERVER['DOCUMENT_ROOT'] . $path . '/';
    }

    function encrypt_decrypt($string,$action){
        $secret_key     = 'Bar12345Bar12345'; 
        $secret_iv      = 'sayangsamakhanza';
        $output         = FALSE;
        $encrypt_method = "AES-256-CBC";
        $key            = hash('sha256', $secret_key);
        $iv             = substr(hash('sha256', $secret_iv), 0, 16);
 
        switch ($action){
             case "e":
                $output = base64_encode(openssl_encrypt($string, $encrypt_method, $key, 0, $iv));
                break;
             case "d":
                $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
                break;
        }
        
        return $output;
    }
        
?>
