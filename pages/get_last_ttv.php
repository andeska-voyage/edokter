<?php
session_start();
require_once "../conf/conf.php";

header('Content-Type: application/json');

$no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
$no_rkm_medis = isset($_POST['no_rkm_medis']) ? validTeks4($_POST['no_rkm_medis'], 20) : '';
$tipe = isset($_POST['tipe']) ? $_POST['tipe'] : 'ralan'; // default ralan, bisa 'ranap'

if(empty($no_rawat) || empty($no_rkm_medis)) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak valid']);
    exit;
}

// Deteksi apakah pasien rawat inap atau rawat jalan
$cek_ranap = bukaquery("SELECT COUNT(*) as jml FROM kamar_inap WHERE no_rawat = '$no_rawat'");
$is_ranap = mysqli_fetch_assoc($cek_ranap)['jml'] > 0;

if($is_ranap) {
    // Query: Ambil TTV dari pemeriksaan_ranap (rawat inap)
    $sql = "SELECT 
                pr.no_rawat,
                pr.tgl_perawatan,
                pr.jam_rawat,
                pr.suhu_tubuh,
                pr.tensi,
                pr.nadi,
                pr.respirasi,
                pr.tinggi,
                pr.berat,
                pr.spo2,
                pr.gcs,
                pr.kesadaran,
                pr.alergi
            FROM pemeriksaan_ranap pr
            WHERE pr.no_rawat = '$no_rawat'
            AND (
                (pr.tensi IS NOT NULL AND pr.tensi != '' AND pr.tensi != '-' AND LENGTH(pr.tensi) > 0) 
                OR (pr.nadi IS NOT NULL AND pr.nadi != '' AND pr.nadi != '-' AND LENGTH(pr.nadi) > 0)
                OR (pr.suhu_tubuh IS NOT NULL AND pr.suhu_tubuh != '' AND pr.suhu_tubuh != '-' AND LENGTH(pr.suhu_tubuh) > 0)
            )
            ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
            LIMIT 1";
} else {
    // Query: Ambil TTV dari pemeriksaan_ralan (rawat jalan)
    $sql = "SELECT 
                pr.no_rawat,
                pr.tgl_perawatan,
                pr.jam_rawat,
                pr.suhu_tubuh,
                pr.tensi,
                pr.nadi,
                pr.respirasi,
                pr.tinggi,
                pr.berat,
                pr.spo2,
                pr.gcs,
                pr.kesadaran,
                pr.alergi,
                pr.lingkar_perut
            FROM pemeriksaan_ralan pr
            WHERE pr.no_rawat = '$no_rawat'
            AND (
                (pr.tensi IS NOT NULL AND pr.tensi != '' AND pr.tensi != '-' AND LENGTH(pr.tensi) > 0) 
                OR (pr.nadi IS NOT NULL AND pr.nadi != '' AND pr.nadi != '-' AND LENGTH(pr.nadi) > 0)
                OR (pr.suhu_tubuh IS NOT NULL AND pr.suhu_tubuh != '' AND pr.suhu_tubuh != '-' AND LENGTH(pr.suhu_tubuh) > 0)
            )
            ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
            LIMIT 1";
}

$query = bukaquery($sql);
$data = mysqli_fetch_assoc($query);

if($data) {
    echo json_encode([
        'success' => true,
        'data' => [
            'tensi' => (!empty($data['tensi']) && $data['tensi'] != '-') ? $data['tensi'] : '',
            'suhu' => (!empty($data['suhu_tubuh']) && $data['suhu_tubuh'] != '-') ? $data['suhu_tubuh'] : '',
            'nadi' => (!empty($data['nadi']) && $data['nadi'] != '-') ? $data['nadi'] : '',
            'respirasi' => (!empty($data['respirasi']) && $data['respirasi'] != '-') ? $data['respirasi'] : '',
            'tinggi' => (!empty($data['tinggi']) && $data['tinggi'] != '-') ? $data['tinggi'] : '',
            'berat' => (!empty($data['berat']) && $data['berat'] != '-') ? $data['berat'] : '',
            'spo2' => (!empty($data['spo2']) && $data['spo2'] != '-') ? $data['spo2'] : '',
            'gcs' => (!empty($data['gcs']) && $data['gcs'] != '-') ? $data['gcs'] : '',
            'kesadaran' => (!empty($data['kesadaran']) && $data['kesadaran'] != '-') ? $data['kesadaran'] : '',
            'alergi' => (!empty($data['alergi']) && $data['alergi'] != '-') ? $data['alergi'] : '',
            'lingkar_perut' => (!empty($data['lingkar_perut']) && $data['lingkar_perut'] != '-') ? $data['lingkar_perut'] : ''
        ],
        'debug' => [
            'no_rawat_sumber' => $data['no_rawat'],
            'tgl_perawatan' => $data['tgl_perawatan'],
            'jam_rawat' => $data['jam_rawat'],
            'tipe_rawat' => $is_ranap ? 'ranap' : 'ralan'
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'TTV belum diisi oleh perawat untuk kunjungan ini'
    ]);
}
?>