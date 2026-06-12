<?php
/**
 * cari_icd9.php
 * AJAX endpoint untuk autocomplete prosedur ICD-9-CM di SOAPIE Intervention.
 *
 * Input  : GET keyword (min 2 karakter)
 * Output : JSON { status, data: [{kode, deskripsi}, ...] }
 *
 * Filter:
 *   - validcode = '1'
 *   - accpdx    = 'Y'
 *   - LIMIT 20
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

    // Prioritaskan match prefix di kode, lalu nama
    $query = "SELECT kode, deskripsi_panjang
              FROM icd9
              WHERE (kode LIKE '$kw%' OR deskripsi_panjang LIKE '%$kw%')
                AND validcode = '1'
                AND accpdx = 'Y'
              ORDER BY
                CASE WHEN kode LIKE '$kw%' THEN 0 ELSE 1 END,
                LENGTH(deskripsi_panjang) ASC
              LIMIT 20";

    $result = bukaquery($query);
    $data = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'kode'      => $row['kode'],
                'deskripsi' => $row['deskripsi_panjang']
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
