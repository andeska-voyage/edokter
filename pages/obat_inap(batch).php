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
// NEW ACTION: SEARCH OBAT BY KODE (untuk copy template/resep)
// ============================================================
if($action == 'search_obat_by_kode'){
    $kode_brng = isset($_GET['kode_brng']) ? validTeks4($_GET['kode_brng'], 20) : '';
    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
    
    if(strlen($kode_brng) < 1){
        echo json_encode(['status' => 'error', 'message' => 'Kode obat tidak valid']);
        exit();
    }
    
    // Default kd_bangsal (depo obat)
    $kd_bangsal = 'AP';
    $sumber_depo = 'default';
    
    // STEP 1: Ambil default dari set_lokasi
    $query_lokasi = bukaquery("SELECT kd_bangsal FROM set_lokasi LIMIT 1");
    if($row_lokasi = mysqli_fetch_array($query_lokasi)){
        if(!empty($row_lokasi['kd_bangsal'])){
            $kd_bangsal = trim($row_lokasi['kd_bangsal']);
            $sumber_depo = 'set_lokasi';
        }
    }
    
    // STEP 2: Jika norawat dikirim, cari depo spesifik untuk rawat inap
    $kd_bangsal_pasien = '';
    if(!empty($norawat)){
        // Ambil kd_bangsal dari kamar_inap pasien
        $query_kamar = bukaquery("
            SELECT k.kd_bangsal, b.nm_bangsal
            FROM kamar_inap ki
            INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
            INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
            WHERE ki.no_rawat = '$norawat' 
              AND ki.stts_pulang = '-'
            ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
            LIMIT 1
        ");
        
        if($row_kamar = mysqli_fetch_array($query_kamar)){
            $kd_bangsal_pasien = trim($row_kamar['kd_bangsal']);
            
            // Cek di set_depo_ranap - AMBIL kd_depo, bukan kd_bangsal!
            $query_depo = bukaquery("
                SELECT kd_depo FROM set_depo_ranap 
                WHERE kd_bangsal = '$kd_bangsal_pasien' 
                LIMIT 1
            ");
            
            if($row_depo = mysqli_fetch_array($query_depo)){
                // Ada mapping di set_depo_ranap
                $kd_bangsal = trim($row_depo['kd_depo']); // Ambil kd_depo
                $sumber_depo = 'set_depo_ranap';
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
                'kode_brng' => $kode_brng,
                'bangsal_pasien' => $kd_bangsal_pasien
            ]
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Obat tidak ditemukan di depo ' . $kd_bangsal,
            'debug_info' => [
                'kd_bangsal' => $kd_bangsal,
                'sumber_depo' => $sumber_depo,
                'kode_brng' => $kode_brng,
                'bangsal_pasien' => $kd_bangsal_pasien
            ]
        ]);
    }
    exit();
}

// Autocomplete obat untuk RAWAT INAP
if($action == 'search_obat'){
    $keyword = isset($_GET['keyword']) ? validTeks4($_GET['keyword'], 50) : '';
    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
    
    if(strlen($keyword) < 2){
        echo json_encode(['status' => 'error', 'message' => 'Minimal 2 karakter']);
        exit();
    }
    
    // Default kd_bangsal (depo obat)
    $kd_bangsal = 'AP';
    $sumber_depo = 'default';
    $debug_steps = [];
    
    // STEP 1: Ambil default dari set_lokasi
    $query_lokasi = bukaquery("SELECT kd_bangsal FROM set_lokasi LIMIT 1");
    if($row_lokasi = mysqli_fetch_array($query_lokasi)){
        if(!empty($row_lokasi['kd_bangsal'])){
            $kd_bangsal = trim($row_lokasi['kd_bangsal']);
            $sumber_depo = 'set_lokasi';
            $debug_steps[] = "set_lokasi: " . $kd_bangsal;
        }
    }
    
    // STEP 2: Jika norawat dikirim, cari depo spesifik untuk rawat inap
    $kd_bangsal_pasien = '';
    if(!empty($norawat)){
        // Ambil kd_bangsal dari kamar_inap pasien
        $query_kamar = bukaquery("
            SELECT k.kd_bangsal, b.nm_bangsal
            FROM kamar_inap ki
            INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
            INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
            WHERE ki.no_rawat = '$norawat' 
              AND ki.stts_pulang = '-'
            ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
            LIMIT 1
        ");
        
        if($row_kamar = mysqli_fetch_array($query_kamar)){
            $kd_bangsal_pasien = trim($row_kamar['kd_bangsal']);
            $debug_steps[] = "kamar_inap bangsal: " . $kd_bangsal_pasien . " (" . $row_kamar['nm_bangsal'] . ")";
            
            // Cek di set_depo_ranap - AMBIL kd_depo, bukan kd_bangsal!
            $query_depo = bukaquery("
                SELECT kd_depo FROM set_depo_ranap 
                WHERE kd_bangsal = '$kd_bangsal_pasien' 
                LIMIT 1
            ");
            
            if($row_depo = mysqli_fetch_array($query_depo)){
                // Ada mapping di set_depo_ranap
                $kd_bangsal = trim($row_depo['kd_depo']); // FIX: ambil kd_depo
                $sumber_depo = 'set_depo_ranap';
                $debug_steps[] = "set_depo_ranap: bangsal " . $kd_bangsal_pasien . " -> depo " . $kd_bangsal;
            } else {
                $debug_steps[] = "set_depo_ranap NOT found for: " . $kd_bangsal_pasien;
            }
        } else {
            $debug_steps[] = "kamar_inap not found for norawat: " . $norawat;
        }
    }
    
    $debug_steps[] = "Final depo: " . $kd_bangsal;
    
    // Query obat dengan stok
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
            'depo_obat' => $kd_bangsal,
            'sumber_depo' => $sumber_depo,
            'bangsal_pasien' => $kd_bangsal_pasien,
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

// Debug: Cek tabel set_depo_ranap
if($action == 'debug_depo'){
    $result = [];
    
    // Cek set_lokasi
    $q1 = bukaquery("SELECT * FROM set_lokasi LIMIT 1");
    $result['set_lokasi'] = mysqli_fetch_assoc($q1);
    
    // Cek set_depo_ranap
    $q2 = bukaquery("SELECT * FROM set_depo_ranap LIMIT 10");
    $result['set_depo_ranap'] = [];
    while($r = mysqli_fetch_assoc($q2)){
        $result['set_depo_ranap'][] = $r;
    }
    
    // Cek gudangbarang untuk AP
    $q3 = bukaquery("SELECT kd_bangsal, COUNT(*) as jml, SUM(stok) as total_stok FROM gudangbarang WHERE stok > 0 GROUP BY kd_bangsal LIMIT 10");
    $result['gudangbarang_summary'] = [];
    while($r = mysqli_fetch_assoc($q3)){
        $result['gudangbarang_summary'][] = $r;
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
}
?>