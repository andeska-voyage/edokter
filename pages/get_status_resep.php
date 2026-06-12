<?php
// ========================================
// GET STATUS RESEP (RINGAN - TANPA JOIN)
// ========================================

// Output buffering
ob_start();

// Cek apakah session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../conf/conf.php');

// Bersihkan buffer
ob_end_clean();

// Set header JSON
header('Content-Type: application/json');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit();
}

// Support GET dan POST
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// ============================================================
// GET STATUS RESEP SAJA (QUERY RINGAN)
// ============================================================
if($action === 'get_status_resep') {
    $no_resep = isset($_REQUEST['no_resep']) ? $_REQUEST['no_resep'] : '';
    
    if(empty($no_resep)) {
        echo json_encode(['status' => 'error', 'message' => 'No resep tidak valid']);
        exit();
    }
    
    // Escape untuk keamanan SQL injection
    $no_resep = str_replace("'", "\'", $no_resep);
    
    try {
        // Query ringan: hanya ambil status dari 1 tabel
        $query = "SELECT 
                    no_resep,
                    tgl_perawatan,
                    jam,
                    CASE 
                        WHEN tgl_perawatan != '0000-00-00' THEN 'Sudah Terlayani'
                        ELSE 'Belum Terlayani'
                    END as status_layanan
                  FROM resep_obat 
                  WHERE no_resep = '{$no_resep}'";
        
        $result = bukaquery($query);
        
        if(mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'no_resep' => $row['no_resep'],
                    'status_layanan' => $row['status_layanan'],
                    'tgl_perawatan' => $row['tgl_perawatan'],
                    'jam' => $row['jam']
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Resep tidak ditemukan'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

// ============================================================
// GET STATUS MULTIPLE RESEP (BATCH)
// ============================================================
elseif($action === 'get_status_batch') {
    $no_resep_list = isset($_REQUEST['no_resep_list']) ? $_REQUEST['no_resep_list'] : '';
    
    if(empty($no_resep_list)) {
        echo json_encode(['status' => 'error', 'message' => 'No resep list tidak valid']);
        exit();
    }
    
    // Parse JSON array
    $resep_array = json_decode($no_resep_list, true);
    
    if(!is_array($resep_array) || count($resep_array) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Format no resep list tidak valid']);
        exit();
    }
    
    try {
        // Escape setiap no_resep
        $escaped_resep = array_map(function($nr) {
            return "'" . str_replace("'", "\'", $nr) . "'";
        }, $resep_array);
        
        $resep_in = implode(',', $escaped_resep);
        
        // Query batch untuk semua resep sekaligus (LEBIH EFISIEN)
        $query = "SELECT 
                    no_resep,
                    tgl_perawatan,
                    jam,
                    CASE 
                        WHEN tgl_perawatan != '0000-00-00' THEN 'Sudah Terlayani'
                        ELSE 'Belum Terlayani'
                    END as status_layanan
                  FROM resep_obat 
                  WHERE no_resep IN ({$resep_in})";
        
        $result = bukaquery($query);
        $data = array();
        
        while($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'no_resep' => $row['no_resep'],
                'status_layanan' => $row['status_layanan'],
                'tgl_perawatan' => $row['tgl_perawatan'],
                'jam' => $row['jam']
            ];
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'count' => count($data)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

else {
    echo json_encode(['status' => 'error', 'message' => 'Action tidak valid']);
}
?>