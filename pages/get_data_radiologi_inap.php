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

    // Ambil config set_tarif — dua kolom radiologi yang relevan untuk ranap
    $set = mysqli_fetch_assoc(bukaquery("
        SELECT cara_bayar_radiologi, kelas_radiologi
        FROM set_tarif LIMIT 1
    "));

    // Ambil data pasien ranap:
    //   kd_pj  ← reg_periksa
    //   kelas  ← kamar (via kamar_inap.kd_kamar; pasien bisa pindah → ambil kamar terakhir)
    $reg = null;
    if (!empty($norawat)) {
        $reg = mysqli_fetch_assoc(bukaquery("
            SELECT k.kelas, rp.kd_pj
            FROM kamar_inap ki
            INNER JOIN kamar k        ON ki.kd_kamar = k.kd_kamar
            INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
            WHERE ki.no_rawat = '$norawat'
            ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
            LIMIT 1
        "));

        // Fallback: kalau pasien belum di kamar_inap (mis. baru daftar ranap),
        // tetap coba ambil kd_pj dari reg_periksa supaya filter cara bayar tetap jalan
        if (!$reg) {
            $reg = mysqli_fetch_assoc(bukaquery("
                SELECT NULL AS kelas, kd_pj FROM reg_periksa
                WHERE no_rawat = '$norawat' LIMIT 1
            "));
        }
    }

    // Build filter kondisional sesuai set_tarif
    $filter_bayar = '';
    $filter_kelas = '';

    if ($set && $reg) {
        if ($set['cara_bayar_radiologi'] === 'Yes' && !empty($reg['kd_pj'])) {
            $kd_pj = validTeks4($reg['kd_pj'], 20);
            $filter_bayar = "AND kd_pj = '$kd_pj'";
        }
        if ($set['kelas_radiologi'] === 'Yes' && !empty($reg['kelas'])) {
            $kelas = validTeks4($reg['kelas'], 15);
            $filter_kelas = "AND kelas = '$kelas'";
        }
    }

    $query = "SELECT kd_jenis_prw, nm_perawatan, total_byr
              FROM jns_perawatan_radiologi
              WHERE status = '1'
              $filter_bayar
              $filter_kelas
              ORDER BY nm_perawatan ASC";

    $result = bukaquery($query);

    if (!$result) {
        throw new Exception('Query gagal: ' . mysqli_error($GLOBALS['koneksi']));
    }

    $data = array();
    while ($row = mysqli_fetch_assoc($result)) {
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

} catch (Exception $e) {
    ob_end_clean();
    error_log("get_data_radiologi_inap.php ERROR: " . $e->getMessage());
    echo json_encode(array(
        'status'  => 'error',
        'message' => $e->getMessage(),
        'count'   => 0,
        'data'    => array()
    ), JSON_UNESCAPED_UNICODE);
}

exit();
?>
