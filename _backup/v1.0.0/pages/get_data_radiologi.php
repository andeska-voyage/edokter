<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

session_start();
require_once('../conf/conf.php');

try {
    if(!isset($_SESSION["ses_dokter"])){
        throw new Exception('Session expired atau belum login');
    }

    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';

    // Ambil config set_tarif — kolom radiologi
    $set = mysqli_fetch_assoc(bukaquery("SELECT cara_bayar_radiologi FROM set_tarif LIMIT 1"));

    // Build filter kondisional
    $filter_bayar = '';

    if($set && !empty($norawat)){
        if($set['cara_bayar_radiologi'] === 'Yes'){
            // Ambil kd_pj pasien dari reg_periksa
            $reg = mysqli_fetch_assoc(bukaquery("
                SELECT kd_pj FROM reg_periksa WHERE no_rawat = '$norawat' LIMIT 1
            "));
            if($reg && !empty($reg['kd_pj'])){
                $kd_pj = validTeks4($reg['kd_pj'], 20);
                $filter_bayar = "AND kd_pj = '$kd_pj'";
            }
        }
    }

    $query = "SELECT kd_jenis_prw, nm_perawatan, total_byr 
              FROM jns_perawatan_radiologi 
              WHERE status='1'
              $filter_bayar
              ORDER BY nm_perawatan ASC";

    $result = bukaquery($query);

    if(!$result){
        throw new Exception('Query gagal: ' . mysqli_error($GLOBALS['koneksi']));
    }

    $data = array();
    while($row = mysqli_fetch_assoc($result)){
        $data[] = array(
            'kd_jenis_prw' => $row['kd_jenis_prw'],
            'nm_perawatan' => $row['nm_perawatan'],
            'total_byr'    => $row['total_byr']
        );
    }

    ob_end_clean();

    echo json_encode(array(
        'status' => 'success',
        'count'  => count($data),
        'data'   => $data,
        'total'  => count($data)
    ), JSON_UNESCAPED_UNICODE);

} catch(Exception $e){
    ob_end_clean();
    error_log("get_data_radiologi.php ERROR: " . $e->getMessage());
    echo json_encode(array(
        'status'  => 'error',
        'message' => $e->getMessage(),
        'count'   => 0,
        'data'    => array()
    ), JSON_UNESCAPED_UNICODE);
}

exit();
?>