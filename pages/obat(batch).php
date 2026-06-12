<?php
session_start();
require_once('../conf/conf.php');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================================
// NEW ACTION: SEARCH OBAT BY KODE (untuk copy template)
// ============================================================
if($action == 'search_obat_by_kode'){
    $kode_brng = isset($_GET['kode_brng']) ? validTeks4($_GET['kode_brng'], 20) : '';
    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
    
    if(strlen($kode_brng) < 1){
        echo json_encode(['status' => 'error', 'message' => 'Kode obat tidak valid']);
        exit();
    }
    
    // Default kd_bangsal dari set_lokasi (global)
    $kd_bangsal = 'AP';
    $sumber_depo = 'default';
    
    // Ambil default dari set_lokasi
    $query_lokasi = bukaquery("SELECT kd_bangsal FROM set_lokasi LIMIT 1");
    if($row_lokasi = mysqli_fetch_array($query_lokasi)){
        if(!empty($row_lokasi['kd_bangsal'])){
            $kd_bangsal = trim($row_lokasi['kd_bangsal']);
            $sumber_depo = 'set_lokasi';
        }
    }
    
    // Jika norawat dikirim, cek set_depo_ralan berdasarkan kd_poli
    if(!empty($norawat)){
        $query_poli = bukaquery("
            SELECT kd_poli FROM reg_periksa 
            WHERE no_rawat = '$norawat' 
            LIMIT 1
        ");
        
        if($row_poli = mysqli_fetch_array($query_poli)){
            $kd_poli = trim($row_poli['kd_poli']);
            
            // Cek di set_depo_ralan
            $query_depo = bukaquery("
                SELECT kd_bangsal FROM set_depo_ralan 
                WHERE kd_poli = '$kd_poli' 
                LIMIT 1
            ");
            
            if($row_depo = mysqli_fetch_array($query_depo)){
                if(!empty($row_depo['kd_bangsal'])){
                    $kd_bangsal = trim($row_depo['kd_bangsal']);
                    $sumber_depo = 'set_depo_ralan';
                }
            }
        }
    }
    
    // Query obat berdasarkan KODE EXACT MATCH
    // SUM stok HANYA dari row yang punya batch & faktur (row NULL diabaikan)
    $query = bukaquery("
        SELECT 
            gb.kode_brng, 
            db.nama_brng, 
            FLOOR(SUM(gb.stok)) AS stok, 
            db.ralan, 
            TRIM(gb.kd_bangsal) AS kd_bangsal, 
            db.status, 
            db.kapasitas,
            db.letak_barang
        FROM gudangbarang gb
        INNER JOIN databarang db ON gb.kode_brng = db.kode_brng
        WHERE gb.kode_brng = '$kode_brng'
          AND TRIM(gb.kd_bangsal) = '$kd_bangsal'
          AND db.status = '1'
          AND gb.no_batch != '' AND gb.no_batch IS NOT NULL
          AND gb.no_faktur != '' AND gb.no_faktur IS NOT NULL
        GROUP BY gb.kode_brng, db.nama_brng, db.ralan, gb.kd_bangsal, db.status, db.kapasitas, db.letak_barang
        HAVING SUM(gb.stok) > 0
        LIMIT 1
    ");
    
    $result = null;
    if($row = mysqli_fetch_array($query)){
        $result = array(
            'kd_brng'   => $row['kode_brng'],
            'nama_brng' => $row['nama_brng'],
            'stok'      => (int)$row['stok'],
            'harga'     => $row['ralan'] ? $row['ralan'] : 0,
            'kd_bangsal'=> $row['kd_bangsal'],
            'status'    => $row['status'],
            'kapasitas' => $row['kapasitas'],
            'kandungan' => $row['letak_barang']
        );
    }
    
    if($result){
        echo json_encode([
            'status' => 'success',
            'data' => $result,
            'debug_info' => [
                'kd_bangsal' => $kd_bangsal,
                'sumber_depo' => $sumber_depo,
                'kode_brng' => $kode_brng
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Obat tidak ditemukan di depo ' . $kd_bangsal,
            'debug_info' => [
                'kd_bangsal' => $kd_bangsal,
                'sumber_depo' => $sumber_depo,
                'kode_brng' => $kode_brng
            ]
        ]);
    }
    exit();
}

// ============================================================
// ORIGINAL: Autocomplete obat by nama/kandungan
// ============================================================
if($action == 'search_obat'){
    $keyword = isset($_GET['keyword']) ? validTeks4($_GET['keyword'], 50) : '';
    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
    
    if(strlen($keyword) < 2){
        echo json_encode(['status' => 'error', 'message' => 'Minimal 2 karakter']);
        exit();
    }
    
    // Default kd_bangsal dari set_lokasi (global)
    $kd_bangsal = 'AP';
    $sumber_depo = 'default';
    $debug_steps = [];
    
    // Ambil default dari set_lokasi
    $query_lokasi = bukaquery("SELECT kd_bangsal FROM set_lokasi LIMIT 1");
    if($row_lokasi = mysqli_fetch_array($query_lokasi)){
        if(!empty($row_lokasi['kd_bangsal'])){
            $kd_bangsal = trim($row_lokasi['kd_bangsal']);
            $sumber_depo = 'set_lokasi';
            $debug_steps[] = "set_lokasi: " . $kd_bangsal;
        }
    }
    
    // Jika norawat dikirim, cek set_depo_ralan berdasarkan kd_poli
    $kd_poli = '';
    if(!empty($norawat)){
        // Ambil kd_poli dari registrasi
        $query_poli = bukaquery("
            SELECT kd_poli FROM reg_periksa 
            WHERE no_rawat = '$norawat' 
            LIMIT 1
        ");
        
        if($row_poli = mysqli_fetch_array($query_poli)){
            $kd_poli = trim($row_poli['kd_poli']);
            $debug_steps[] = "kd_poli: " . $kd_poli;
            
            // Cek di set_depo_ralan
            $query_depo = bukaquery("
                SELECT kd_bangsal FROM set_depo_ralan 
                WHERE kd_poli = '$kd_poli' 
                LIMIT 1
            ");
            
            if($row_depo = mysqli_fetch_array($query_depo)){
                if(!empty($row_depo['kd_bangsal'])){
                    $kd_bangsal = trim($row_depo['kd_bangsal']);
                    $sumber_depo = 'set_depo_ralan';
                    $debug_steps[] = "set_depo_ralan: poli " . $kd_poli . " -> depo " . $kd_bangsal;
                }
            } else {
                $debug_steps[] = "set_depo_ralan NOT found for poli: " . $kd_poli;
            }
        }
    }
    
    $debug_steps[] = "Final depo: " . $kd_bangsal;
    
    // Query obat + kapasitas + kandungan
    // SUM stok HANYA dari row yang punya batch & faktur (row NULL diabaikan)
    $query = bukaquery("
        SELECT 
            gb.kode_brng, 
            db.nama_brng, 
            FLOOR(SUM(gb.stok)) AS stok, 
            db.ralan, 
            TRIM(gb.kd_bangsal) AS kd_bangsal, 
            db.status, 
            db.kapasitas,
            db.letak_barang
        FROM gudangbarang gb
        INNER JOIN databarang db ON gb.kode_brng = db.kode_brng
        WHERE TRIM(gb.kd_bangsal) = '$kd_bangsal'
          AND db.status = '1'
          AND gb.no_batch != '' AND gb.no_batch IS NOT NULL
          AND gb.no_faktur != '' AND gb.no_faktur IS NOT NULL
          AND (db.nama_brng LIKE '%$keyword%' OR db.letak_barang LIKE '%$keyword%')
        GROUP BY gb.kode_brng, db.nama_brng, db.ralan, gb.kd_bangsal, db.status, db.kapasitas, db.letak_barang
        HAVING SUM(gb.stok) > 0
        ORDER BY db.nama_brng ASC
        LIMIT 20
    ");
    
    $results = array();
    while($row = mysqli_fetch_array($query)){
        $results[] = array(
            'kd_brng'   => $row['kode_brng'],
            'nama_brng' => $row['nama_brng'],
            'stok'      => (int)$row['stok'],
            'harga'     => $row['ralan'] ? $row['ralan'] : 0,
            'kd_bangsal'=> $row['kd_bangsal'],
            'status'    => $row['status'],
            'kapasitas' => $row['kapasitas'],
            'kandungan' => $row['letak_barang']
        );
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $results,
        'count' => count($results),
        'debug_info' => [
            'kd_bangsal' => $kd_bangsal,
            'sumber_depo' => $sumber_depo,
            'kd_poli' => $kd_poli,
            'norawat' => $norawat,
            'keyword' => $keyword,
            'steps' => $debug_steps
        ]
    ]);
}

// ============================================================
// Autocomplete Aturan Pakai dari master_aturan_pakai
// ============================================================
if($action == 'search_aturan_pakai'){
    $keyword = isset($_GET['keyword']) ? validTeks4($_GET['keyword'], 100) : '';
    
    if(strlen($keyword) < 1){
        echo json_encode(['status' => 'error', 'message' => 'Minimal 1 karakter']);
        exit();
    }
    
    $query = bukaquery("
        SELECT aturan 
        FROM master_aturan_pakai 
        WHERE aturan LIKE '%$keyword%'
        ORDER BY aturan ASC
        LIMIT 15
    ");
    
    $results = array();
    while($row = mysqli_fetch_array($query)){
        $results[] = array(
            'aturan' => $row['aturan']
        );
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $results,
        'count' => count($results)
    ]);
    exit();
}
?>