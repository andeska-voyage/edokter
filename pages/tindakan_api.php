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

// ==================== SEARCH TINDAKAN ====================
if($action === 'search_tindakan'){

    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';

    // Ambil config set_tarif
    $set = mysqli_fetch_assoc(bukaquery("SELECT poli_ralan, cara_bayar_ralan FROM set_tarif LIMIT 1"));

    // Ambil data pasien dari reg_periksa
    $reg = !empty($norawat) ? mysqli_fetch_assoc(bukaquery("
        SELECT kd_poli, kd_pj FROM reg_periksa WHERE no_rawat = '$norawat' LIMIT 1
    ")) : null;

    // Build filter kondisional
    $filter_poli  = '';
    $filter_bayar = '';

    if($set && $reg){
        if($set['poli_ralan'] === 'Yes' && !empty($reg['kd_poli'])){
            $kd_poli = validTeks4($reg['kd_poli'], 20);
            $filter_poli = "AND j.kd_poli = '$kd_poli'";
        }
        if($set['cara_bayar_ralan'] === 'Yes' && !empty($reg['kd_pj'])){
            $kd_pj = validTeks4($reg['kd_pj'], 20);
            $filter_bayar = "AND j.kd_pj = '$kd_pj'";
        }
    }

    $query = bukaquery("
        SELECT 
            kd_jenis_prw AS kode, 
            nm_perawatan AS nama, 
            total_byrdr AS tarif
        FROM jns_perawatan j
        WHERE (j.kd_jenis_prw LIKE '%$keyword%' OR j.nm_perawatan LIKE '%$keyword%')
          AND j.total_byrdr IS NOT NULL 
          AND j.total_byrdr > 0
          $filter_poli
          $filter_bayar
        ORDER BY j.nm_perawatan ASC
        LIMIT 30
    ");
    
    while($row = mysqli_fetch_assoc($query)){
        $data[] = [
            'kode'  => $row['kode'],
            'nama'  => $row['nama'],
            'tarif' => $row['tarif']
        ];
    }

    echo json_encode([
        'status'   => 'success',
        'data'     => $data,
        'count'    => count($data),
        'keyword'  => $keyword,
        // DEBUG — hapus setelah konfirmasi filter bekerja
        '_debug'   => [
            'norawat'           => $norawat,
            'set_poli_ralan'    => $set['poli_ralan'] ?? null,
            'set_cara_bayar'    => $set['cara_bayar_ralan'] ?? null,
            'reg_kd_poli'       => $reg['kd_poli'] ?? null,
            'reg_kd_pj'         => $reg['kd_pj'] ?? null,
            'filter_poli'       => $filter_poli,
            'filter_bayar'      => $filter_bayar,
        ]
    ]);
    exit();
}

// ==================== DEFAULT ====================
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit();
?>