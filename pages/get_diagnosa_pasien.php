<?php
/**
 * get_diagnosa_pasien.php
 * Ambil daftar diagnosa pasien (dari tabel diagnosa_pasien) untuk no_rawat tertentu.
 * Dipakai saat init SOAPIE Assessment untuk populate chip diagnosa.
 *
 * Input  : GET norawat, status (default 'Ralan')
 * Output : JSON { status, data: [{kd_penyakit, nm_penyakit, prioritas, status_penyakit}, ...] }
 */

ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

session_start();
require_once('../conf/conf.php');

try {
    if (!isset($_SESSION["ses_dokter"])) {
        throw new Exception('Session expired');
    }

    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
    $stts    = isset($_GET['status'])  ? validTeks4($_GET['status'], 10)  : 'Ralan';
    if (empty($norawat)) {
        throw new Exception('No. Rawat tidak valid');
    }
    if (!in_array($stts, ['Ralan', 'Ranap'])) $stts = 'Ralan';

    $query = "SELECT dp.kd_penyakit, dp.prioritas, dp.status_penyakit, py.nm_penyakit
              FROM diagnosa_pasien dp
              LEFT JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
              WHERE dp.no_rawat = '$norawat' AND dp.status = '$stts'
              ORDER BY dp.prioritas ASC";

    $result = bukaquery($query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'kd_penyakit'     => $row['kd_penyakit'],
                'nm_penyakit'     => $row['nm_penyakit'] ?? '(nama tidak ditemukan)',
                'prioritas'       => (int)$row['prioritas'],
                'status_penyakit' => $row['status_penyakit'] ?? 'Baru'
            ];
        }
    }

    ob_end_clean();
    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'data' => []]);
}

exit();
?>
