<?php
session_start();
require_once('../conf/conf.php');

header('Content-Type: application/json');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit();
}

$norm = isset($_GET['norm']) ? validTeks4($_GET['norm'], 20) : '';
$filterNoRawat = isset($_GET['filter_no_rawat']) ? validTeks4($_GET['filter_no_rawat'], 20) : '';

if(empty($norm)){
    echo json_encode(['success' => false, 'message' => 'Parameter NO RM tidak valid']);
    exit();
}

// Build filter SQL untuk no_rawat
$noRawatFilterItem = "";
if(!empty($filterNoRawat)){
    $noRawatFilterItem = " AND dpl.no_rawat = '$filterNoRawat' ";
}

// Query untuk ambil semua item lab unik yang pernah diperiksa pasien
$query = "
    SELECT DISTINCT 
        dpl.id_template,
        tl.Pemeriksaan,
        tl.satuan,
        COUNT(DISTINCT CONCAT(dpl.tgl_periksa, '-', dpl.jam)) as jumlah_pemeriksaan
    FROM detail_periksa_lab dpl
    LEFT JOIN template_laboratorium tl ON dpl.id_template = tl.id_template
    LEFT JOIN periksa_lab pl ON dpl.no_rawat = pl.no_rawat 
        AND dpl.kd_jenis_prw = pl.kd_jenis_prw
        AND dpl.tgl_periksa = pl.tgl_periksa
        AND dpl.jam = pl.jam
    LEFT JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm'
    AND dpl.nilai IS NOT NULL 
    AND dpl.nilai != ''
    AND dpl.nilai != '-'
    $noRawatFilterItem
    GROUP BY dpl.id_template
    HAVING jumlah_pemeriksaan >= 2
    ORDER BY jumlah_pemeriksaan DESC, tl.Pemeriksaan ASC
";

$result = bukaquery($query);
$items = [];

while($row = mysqli_fetch_assoc($result)){
    $items[] = [
        'id_template' => $row['id_template'],
        'nama' => $row['Pemeriksaan'],
        'satuan' => $row['satuan'] ?? '',
        'jumlah' => intval($row['jumlah_pemeriksaan'])
    ];
}

// Query untuk ambil daftar no_rawat yang punya data lab
$queryNoRawat = "
    SELECT DISTINCT 
        pl.no_rawat,
        rp.tgl_registrasi,
        rp.status_lanjut
    FROM periksa_lab pl
    LEFT JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm'
    ORDER BY pl.no_rawat DESC
";

$resultNoRawat = bukaquery($queryNoRawat);
$noRawatList = [];

while($row = mysqli_fetch_assoc($resultNoRawat)){
    $noRawatList[] = [
        'no_rawat' => $row['no_rawat'],
        'tgl_registrasi' => $row['tgl_registrasi'],
        'status_lanjut' => $row['status_lanjut']
    ];
}

echo json_encode([
    'success' => true,
    'data' => $items,
    'no_rawat_list' => $noRawatList
]);