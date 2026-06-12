<?php
/**
 * get_prosedur_pasien.php
 * Ambil daftar prosedur pasien (dari tabel prosedur_pasien) untuk no_rawat tertentu.
 * Dipakai saat init SOAPIE Intervention untuk populate chip prosedur ICD-9.
 *
 * Input  : GET norawat, status (default 'Ralan')
 * Output : JSON { status, data: [{kode, deskripsi, prioritas, jumlah}, ...] }
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

    $query = "SELECT pp.kode, pp.prioritas, pp.jumlah, ic.deskripsi_panjang
              FROM prosedur_pasien pp
              LEFT JOIN icd9 ic ON pp.kode = ic.kode
              WHERE pp.no_rawat = '$norawat' AND pp.status = '$stts'
              ORDER BY pp.prioritas ASC";

    $result = bukaquery($query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'kode'      => $row['kode'],
                'deskripsi' => $row['deskripsi_panjang'] ?? '(deskripsi tidak ditemukan)',
                'prioritas' => (int)$row['prioritas'],
                'jumlah'    => $row['jumlah'] ?? '1'
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
