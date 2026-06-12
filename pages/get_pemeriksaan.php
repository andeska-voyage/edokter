<?php
require_once('../conf/conf.php');
header('Content-Type: application/json; charset=utf-8');

// Query ambil semua data dari tabel jns_perawatan_lab dengan status aktif
$sql = "SELECT kd_jenis_prw, nm_perawatan 
        FROM jns_perawatan_lab 
        WHERE status='1' 
        ORDER BY nm_perawatan ASC";

$result = bukaquery($sql);
$data = [];

// Loop hasil query
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = [
            'kd_jenis_prw' => $row['kd_jenis_prw'],
            'nm_perawatan' => $row['nm_perawatan']
        ];
    }

    // Kirim dalam format JSON DataTables
    echo json_encode([
        'status' => 'success',
        'count' => count($data),
        'data' => $data
    ]);
} else {
    // Jika query gagal
    echo json_encode([
        'status' => 'error',
        'message' => 'Query gagal dijalankan'
    ]);
}
?>
