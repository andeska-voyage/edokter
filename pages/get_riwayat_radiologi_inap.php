<?php
session_start();
require_once('../conf/conf.php');

// Set header JSON
header('Content-Type: application/json');

// Validasi session
if (!isset($_SESSION["ses_dokter"])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Session expired'
    ]);
    exit();
}

// Ambil parameter
$norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';

if (empty($norawat)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Parameter tidak valid'
    ]);
    exit();
}

try {
    // Query riwayat dengan JOIN
    $query_riwayat = "SELECT 
                        pr.noorder,
                        pr.tgl_permintaan,
                        pr.jam_permintaan,
                        pr.tgl_sampel,
                        pr.tgl_hasil,
                        pr.status,
                        d.nm_dokter,
                        GROUP_CONCAT(jp.nm_perawatan SEPARATOR ', ') as pemeriksaan,
                        SUM(jp.total_byr) as total_biaya
                      FROM permintaan_radiologi pr
                      LEFT JOIN permintaan_pemeriksaan_radiologi ppr ON pr.noorder = ppr.noorder
                      LEFT JOIN jns_perawatan_radiologi jp ON ppr.kd_jenis_prw = jp.kd_jenis_prw
                      LEFT JOIN dokter d ON pr.dokter_perujuk = d.kd_dokter
                      WHERE pr.no_rawat = '$norawat'
                      GROUP BY pr.noorder
                      ORDER BY pr.tgl_permintaan DESC, pr.jam_permintaan DESC";
    
    $result_riwayat = bukaquery($query_riwayat);
    
    $data = [];
    
    if (mysqli_num_rows($result_riwayat) > 0) {
        while ($row = mysqli_fetch_array($result_riwayat)) {
            // Cek status sampel dan hasil
            $sudah_sampel = ($row['tgl_sampel'] != '0000-00-00' && !empty($row['tgl_sampel']));
            $sudah_hasil = ($row['tgl_hasil'] != '0000-00-00' && !empty($row['tgl_hasil']));
            
            $data[] = [
                'noorder' => $row['noorder'],
                'tanggal' => konversiTanggal($row['tgl_permintaan']) . ' ' . $row['jam_permintaan'],
                'pemeriksaan' => $row['pemeriksaan'],
                'nm_dokter' => $row['nm_dokter'],
                'total_biaya' => $row['total_biaya'],
                'total_biaya_formatted' => number_format($row['total_biaya'], 0, ',', '.'),
                'sudah_sampel' => $sudah_sampel,
                'sudah_hasil' => $sudah_hasil
            ];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>