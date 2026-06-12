<?php
session_start();
require_once('../conf/conf.php');
header("Content-Type: application/json; charset=utf-8");

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit();
}

$action  = isset($_GET['action'])  ? $_GET['action']                       : '';
$keyword = isset($_GET['keyword']) ? validTeks4($_GET['keyword'], 50)      : '';

if(strlen($keyword) < 2){
    echo json_encode(['status' => 'error', 'message' => 'Minimal 2 karakter']);
    exit();
}

$data = [];

// ==================== SEARCH TINDAKAN RAWAT INAP ====================
if($action === 'search_tindakan'){

    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';

    // Ambil config set_tarif untuk filter ranap
    $set = mysqli_fetch_assoc(bukaquery("
        SELECT ruang_ranap, cara_bayar_ranap, kelas_ranap
        FROM set_tarif LIMIT 1
    "));

    // Ambil data pasien ranap (kd_bangsal, kelas dari kamar; kd_pj dari reg_periksa)
    // Pasien bisa pindah kamar → ambil kamar terakhir
    $reg = null;
    if (!empty($norawat)) {
        $reg = mysqli_fetch_assoc(bukaquery("
            SELECT k.kd_bangsal, k.kelas, rp.kd_pj
            FROM kamar_inap ki
            INNER JOIN kamar k       ON ki.kd_kamar = k.kd_kamar
            INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
            WHERE ki.no_rawat = '$norawat'
            ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
            LIMIT 1
        "));
    }

    // Build filter kondisional sesuai set_tarif
    $filter_bangsal = '';
    $filter_bayar   = '';
    $filter_kelas   = '';

    if ($set && $reg) {
        if ($set['ruang_ranap'] === 'Yes' && !empty($reg['kd_bangsal'])) {
            $kd_bangsal = validTeks4($reg['kd_bangsal'], 5);
            $filter_bangsal = "AND j.kd_bangsal = '$kd_bangsal'";
        }
        if ($set['cara_bayar_ranap'] === 'Yes' && !empty($reg['kd_pj'])) {
            $kd_pj = validTeks4($reg['kd_pj'], 3);
            $filter_bayar = "AND j.kd_pj = '$kd_pj'";
        }
        if ($set['kelas_ranap'] === 'Yes' && !empty($reg['kelas'])) {
            $kelas = validTeks4($reg['kelas'], 15);
            $filter_kelas = "AND j.kelas = '$kelas'";
        }
    }

    $query = bukaquery("
        SELECT
            kd_jenis_prw     AS kode,
            nm_perawatan     AS nama,
            kd_kategori,
            material,
            bhp,
            tarif_tindakandr,
            tarif_tindakanpr,
            kso,
            menejemen,
            total_byrdr      AS tarif,
            total_byrpr,
            total_byrdrpr,
            kd_pj,
            kd_bangsal,
            kelas
        FROM jns_perawatan_inap j
        WHERE (j.kd_jenis_prw LIKE '%$keyword%' OR j.nm_perawatan LIKE '%$keyword%')
          AND j.status = '1'
          AND j.total_byrdr IS NOT NULL
          AND j.total_byrdr > 0
          $filter_bangsal
          $filter_bayar
          $filter_kelas
        ORDER BY j.nm_perawatan ASC
        LIMIT 30
    ");

    while ($row = mysqli_fetch_assoc($query)) {
        $data[] = [
            'kode'             => $row['kode'],
            'nama'             => $row['nama'],
            'tarif'            => $row['tarif'],
            'kd_kategori'      => $row['kd_kategori'],
            'material'         => $row['material'],
            'bhp'              => $row['bhp'],
            'tarif_tindakandr' => $row['tarif_tindakandr'],
            'tarif_tindakanpr' => $row['tarif_tindakanpr'],
            'kso'              => $row['kso'],
            'menejemen'        => $row['menejemen'],
            'total_byrdr'      => $row['tarif'],
            'total_byrpr'      => $row['total_byrpr'],
            'total_byrdrpr'    => $row['total_byrdrpr'],
            'kd_pj'            => $row['kd_pj'],
            'kd_bangsal'       => $row['kd_bangsal'],
            'kelas'            => $row['kelas']
        ];
    }

    echo json_encode([
        'status'  => 'success',
        'data'    => $data,
        'count'   => count($data),
        'keyword' => $keyword
    ]);
    exit();
}

// ==================== DEFAULT ====================
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit();
?>
