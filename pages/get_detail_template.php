<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once('../conf/conf.php');

try {
    // Validasi session
    if (!isset($_SESSION["ses_dokter"])) {
        throw new Exception('Session expired atau belum login');
    }
    
    // Ambil parameter
    $kode = isset($_GET['kode']) ? validTeks4($_GET['kode'], 20) : '';
    
    if (empty($kode)) {
        throw new Exception('Kode pemeriksaan tidak boleh kosong');
    }
    
    // Query untuk mendapatkan info template dan detail pemeriksaan
    $query = "SELECT 
                jpl.kd_jenis_prw,
                jpl.nm_perawatan AS nama_template,
                tl.Pemeriksaan,
                tl.satuan,
                tl.nilai_rujukan_ld,
                tl.nilai_rujukan_la,
                tl.nilai_rujukan_pd,
                tl.nilai_rujukan_pa
              FROM jns_perawatan_lab jpl
              LEFT JOIN template_laboratorium tl ON jpl.kd_jenis_prw = tl.kd_jenis_prw
              WHERE jpl.kd_jenis_prw = '$kode'
              ORDER BY tl.Pemeriksaan ASC";
    
    $result = bukaquery($query);
    
    if (!$result) {
        throw new Exception('Query gagal: ' . mysqli_error($GLOBALS['koneksi']));
    }
    
    $template_info = null;
    $data = array();
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Simpan info template
        if ($template_info === null) {
            $template_info = array(
                'kd_jenis_prw' => $row['kd_jenis_prw'],
                'nama_template' => $row['nama_template']
            );
        }
        
        // Simpan detail pemeriksaan
        if (!empty($row['Pemeriksaan'])) {
            $data[] = array(
                'pemeriksaan' => $row['Pemeriksaan'],
                'satuan' => $row['satuan'],
                'nilai_rujukan_ld' => $row['nilai_rujukan_ld'],
                'nilai_rujukan_la' => $row['nilai_rujukan_la'],
                'nilai_rujukan_pd' => $row['nilai_rujukan_pd'],
                'nilai_rujukan_pa' => $row['nilai_rujukan_pa']
            );
        }
    }
    
    ob_end_clean();
    
    echo json_encode(array(
        'status' => 'success',
        'template_info' => $template_info,
        'data' => $data,
        'count' => count($data)
    ), JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    error_log("get_detail_template.php ERROR: " . $e->getMessage());
    
    echo json_encode(array(
        'status' => 'error',
        'message' => $e->getMessage()
    ), JSON_UNESCAPED_UNICODE);
}

exit();
?>