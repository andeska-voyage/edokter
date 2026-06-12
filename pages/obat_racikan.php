<?php
session_start();
require_once('../conf/conf.php');

header('Content-Type: application/json');

if(isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if($action == 'search_metode_racikan') {
        try {
            // Ambil keyword
            $keyword = isset($_POST['keyword']) ? trim($_POST['keyword']) : '';
            
            // Buka koneksi
            $koneksi = bukakoneksi();
            
            // Escape string untuk keamanan
            $keyword_escaped = mysqli_real_escape_string($koneksi, $keyword);
            
            // Query
            $query = "SELECT kd_racik, nm_racik FROM metode_racik 
                      WHERE nm_racik LIKE '%$keyword_escaped%' 
                      OR kd_racik LIKE '%$keyword_escaped%'
                      ORDER BY nm_racik ASC 
                      LIMIT 10";
            
            // Eksekusi query
            $result = mysqli_query($koneksi, $query);
            
            if(!$result) {
                mysqli_close($koneksi);
                echo json_encode(array(
                    'status' => 'error',
                    'message' => 'Query error: ' . mysqli_error($koneksi)
                ));
                exit;
            }
            
            // Cek hasil
            if(mysqli_num_rows($result) > 0) {
                $data = array();
                while($row = mysqli_fetch_assoc($result)) {
                    $data[] = array(
                        'kd_racik' => $row['kd_racik'],
                        'nm_racik' => $row['nm_racik']
                    );
                }
                
                mysqli_close($koneksi);
                
                echo json_encode(array(
                    'status' => 'success',
                    'data' => $data
                ));
            } else {
                mysqli_close($koneksi);
                
                echo json_encode(array(
                    'status' => 'error',
                    'message' => 'Data metode racikan tidak ditemukan',
                    'keyword' => $keyword
                ));
            }
        } catch (Exception $e) {
            echo json_encode(array(
                'status' => 'error',
                'message' => $e->getMessage()
            ));
        }
    } else {
        echo json_encode(array(
            'status' => 'error',
            'message' => 'Action tidak dikenali'
        ));
    }
} else {
    echo json_encode(array(
        'status' => 'error',
        'message' => 'Action tidak ditemukan'
    ));
}
?>