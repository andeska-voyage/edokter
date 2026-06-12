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

// Parameter tambahan untuk filter rawat inap
$kd_bangsal = isset($_GET['kd_bangsal']) ? validTeks4($_GET['kd_bangsal'], 5) : '';
$kelas = isset($_GET['kelas']) ? validTeks4($_GET['kelas'], 10) : '';

if(strlen($keyword) < 2){
    echo json_encode(['status' => 'error', 'message' => 'Minimal 2 karakter']);
    exit();
}

$data = [];

// ==================== SEARCH TINDAKAN RAWAT INAP ====================
if($action === 'search_tindakan'){
    
    // Build WHERE clause
    $where = "(kd_jenis_prw LIKE '%$keyword%' OR nm_perawatan LIKE '%$keyword%')
              AND status = '1'";
    
    // Filter by bangsal jika ada
    if(!empty($kd_bangsal)){
        $where .= " AND kd_bangsal = '$kd_bangsal'";
    }
    
    // Filter by kelas jika ada
    if(!empty($kelas)){
        $where .= " AND kelas = '$kelas'";
    }
    
    $query = bukaquery("
        SELECT 
            kd_jenis_prw AS kode, 
            nm_perawatan AS nama, 
            kd_kategori,
            material,
            bhp,
            tarif_tindakandr,
            tarif_tindakanpr,
            kso,
            menejemen,
            total_byrdr AS tarif,
            total_byrpr,
            total_byrdrpr,
            kd_pj,
            kd_bangsal,
            kelas
        FROM jns_perawatan_inap
        WHERE $where
        ORDER BY nm_perawatan ASC
        LIMIT 30
    ");
    
    while($row = mysqli_fetch_assoc($query)){
        $data[] = [
            'kode' => $row['kode'],
            'nama' => $row['nama'],
            'tarif' => $row['tarif'],
            'kd_kategori' => $row['kd_kategori'],
            'material' => $row['material'],
            'bhp' => $row['bhp'],
            'tarif_tindakandr' => $row['tarif_tindakandr'],
            'tarif_tindakanpr' => $row['tarif_tindakanpr'],
            'kso' => $row['kso'],
            'menejemen' => $row['menejemen'],
            'total_byrdr' => $row['tarif'],
            'total_byrpr' => $row['total_byrpr'],
            'total_byrdrpr' => $row['total_byrdrpr'],
            'kd_pj' => $row['kd_pj'],
            'kd_bangsal' => $row['kd_bangsal'],
            'kelas' => $row['kelas']
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
