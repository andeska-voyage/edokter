<?php
/**
 * versi.php — diupdate otomatis oleh apply_update.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode(['status' => 'ok', 'versi' => '1.1.9']);
