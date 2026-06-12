<?php
/**
 * cari_dokter.php
 * AJAX endpoint untuk autocomplete nama dokter (rujukan dalam RS).
 * Dipakai di SOAPIE Plan → input "Tujuan rujukan".
 *
 * Input  : GET keyword (min 2 karakter)
 * Output : JSON { status, data: [{nm_dokter}, ...] }
 *
 * Filter:
 *   - status = '1' (dokter aktif)
 *   - LIMIT 15 (UI dropdown nyaman)
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

    $keyword = isset($_GET['keyword']) ? validTeks4($_GET['keyword'], 100) : '';
    if (strlen($keyword) < 2) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Minimal 2 karakter', 'data' => []]);
        exit;
    }

    $kw = mysqli_real_escape_string($GLOBALS['db_conn'], $keyword);

    $query = "SELECT kd_dokter, nm_dokter
              FROM dokter
              WHERE nm_dokter LIKE '%$kw%'
                AND status = '1'
              ORDER BY nm_dokter ASC
              LIMIT 15";

    $result = bukaquery($query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'kd_dokter' => $row['kd_dokter'],
                'nm_dokter' => $row['nm_dokter']
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
