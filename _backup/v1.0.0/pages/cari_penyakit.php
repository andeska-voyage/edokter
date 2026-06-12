<?php
/**
 * cari_penyakit.php
 * AJAX endpoint untuk autocomplete diagnosa ICD-10 di SOAPIE Assessment.
 *
 * Input  : GET keyword (min 2 karakter)
 * Output : JSON { status, data: [{kd_penyakit, nm_penyakit}, ...] }
 *
 * Filter:
 *   - status validcode = '1' (kode valid)
 *   - accpdx = 'Y' (boleh dipakai sebagai diagnosa)
 *   - LIMIT 20 (UI dropdown tetap nyaman)
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

    $keyword = isset($_GET['keyword']) ? validTeks4($_GET['keyword'], 50) : '';
    if (strlen($keyword) < 2) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Minimal 2 karakter', 'data' => []]);
        exit;
    }

    $kw = mysqli_real_escape_string($GLOBALS['db_conn'], $keyword);

    // Prioritaskan match di kd_penyakit (ICD code) dulu, lalu nm_penyakit
    $query = "SELECT kd_penyakit, nm_penyakit
              FROM penyakit
              WHERE (kd_penyakit LIKE '$kw%' OR nm_penyakit LIKE '%$kw%')
                AND validcode = '1'
                AND accpdx = 'Y'
              ORDER BY
                CASE WHEN kd_penyakit LIKE '$kw%' THEN 0 ELSE 1 END,
                LENGTH(nm_penyakit) ASC
              LIMIT 20";

    $result = bukaquery($query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'kd_penyakit' => $row['kd_penyakit'],
                'nm_penyakit' => $row['nm_penyakit']
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
