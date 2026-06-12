<?php
session_start();
require_once('../conf/conf.php');
header("Content-Type: application/json; charset=utf-8");

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit();
}

$action  = isset($_GET['action']) ? $_GET['action'] : '';
$keyword = isset($_GET['keyword']) ? validTeks4($_GET['keyword'], 50) : '';

if(strlen($keyword) < 2){
    echo json_encode(['status' => 'error', 'message' => 'Minimal 2 karakter']);
    exit();
}

$data = [];

// ==================== ICD-10 (Diagnosa) ====================
if($action === 'search_icd10'){
    $query = bukaquery("
        SELECT kd_penyakit AS code, nm_penyakit AS name, ciri_ciri AS ciri
        FROM penyakit
        WHERE kd_penyakit LIKE '%$keyword%' 
           OR nm_penyakit LIKE '%$keyword%'
        ORDER BY nm_penyakit ASC
        LIMIT 30
    ");
    while($row = mysqli_fetch_assoc($query)){
        $data[] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'ciri' => $row['ciri']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'count' => count($data),
        'keyword' => $keyword
    ]);
    exit();
}

// ==================== ICD-9 (Prosedur) ====================
if($action === 'search_icd9'){
    $query = bukaquery("
        SELECT kode AS code, deskripsi_panjang AS name, deskripsi_pendek AS pendek
        FROM icd9
        WHERE kode LIKE '%$keyword%' 
           OR deskripsi_panjang LIKE '%$keyword%'
        ORDER BY deskripsi_panjang ASC
        LIMIT 30
    ");
    while($row = mysqli_fetch_assoc($query)){
        $data[] = [
            'code' => $row['code'],
            'name' => $row['name'],
            'pendek' => $row['pendek']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'count' => count($data),
        'keyword' => $keyword
    ]);
    exit();
}

// ==================== DEFAULT ====================
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit();
?>
