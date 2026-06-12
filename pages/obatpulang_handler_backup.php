<?php
// CRITICAL: No output before this point!
ob_start();

session_start();
require_once('../conf/conf.php');

// Clear buffer
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // Validasi session
    if(!isset($_SESSION["ses_dokter"])){
        echo json_encode(['status' => 'error', 'message' => 'Session expired']);
        exit();
    }

    $aksi = isset($_POST['aksi']) ? validTeks4($_POST['aksi'], 50) : '';

    // ============================================
    // SEARCH OBAT NON RACIKAN
    // ============================================
    if($aksi == 'search_obat') {
        $keyword = isset($_POST['keyword']) ? validTeks4($_POST['keyword'], 100) : '';
        
        if(strlen($keyword) < 2) {
            echo json_encode(['status' => 'error', 'message' => 'Keyword minimal 2 karakter']);
            exit();
        }
        
        // Default depo
        $kd_bangsal = 'AP';
        $query_lokasi = bukaquery("SELECT kd_bangsal FROM set_lokasi LIMIT 1");
        if($row_lokasi = mysqli_fetch_array($query_lokasi)){
            if(!empty($row_lokasi['kd_bangsal'])){
                $kd_bangsal = trim($row_lokasi['kd_bangsal']);
            }
        }
        
        // Query sesuai obat.php
        $query = "SELECT 
                    gb.kode_brng, 
                    db.nama_brng, 
                    FLOOR(gb.stok) AS stok, 
                    db.ralan,
                    db.kapasitas,
                    db.kode_sat,
                    db.letak_barang,
                    gb.kd_bangsal
                  FROM gudangbarang gb
                  INNER JOIN databarang db ON gb.kode_brng = db.kode_brng
                  WHERE TRIM(gb.kd_bangsal) = '$kd_bangsal'
                    AND db.status = '1'
                    AND (gb.no_batch = '' OR gb.no_batch IS NULL)
                    AND (gb.no_faktur = '' OR gb.no_faktur IS NULL)
                    AND (db.nama_brng LIKE '%$keyword%' OR db.kode_brng LIKE '%$keyword%' OR db.letak_barang LIKE '%$keyword%')
                    AND gb.stok > 0
                  ORDER BY db.nama_brng ASC
                  LIMIT 20";
        
        $result = bukaquery($query);
        $data = [];
        
        if($result && mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    'kd_brng' => $row['kode_brng'],
                    'nama' => $row['nama_brng'],
                    'harga' => floatval($row['ralan']), // ralan = harga jual
                    'stok' => intval($row['stok']),
                    'satuan' => $row['kode_sat'] ?? '',
                    'kandungan' => $row['letak_barang'] ?? ''
                ];
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ============================================
    // SEARCH METODE RACIKAN
    // ============================================
    if($aksi == 'search_metode') {
        $keyword = isset($_POST['keyword']) ? validTeks4($_POST['keyword'], 100) : '';
        
        if(strlen($keyword) < 2) {
            echo json_encode(['status' => 'error', 'message' => 'Keyword minimal 2 karakter']);
            exit();
        }
        
        $query = "SELECT 
                    kd_racik,
                    nm_racik
                  FROM metode_racik
                  WHERE nm_racik LIKE '%$keyword%'
                  LIMIT 10";
        
        $result = bukaquery($query);
        $data = [];
        
        if($result && mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    'kd_racik' => $row['kd_racik'],
                    'metode' => $row['nm_racik']
                ];
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // ============================================
    // SEARCH OBAT RACIKAN (untuk komposisi)
    // ============================================
    if($aksi == 'search_obat_racikan') {
        $keyword = isset($_POST['keyword']) ? validTeks4($_POST['keyword'], 100) : '';
        
        if(strlen($keyword) < 2) {
            echo json_encode(['status' => 'error', 'message' => 'Keyword minimal 2 karakter']);
            exit();
        }
        
        // Default depo
        $kd_bangsal = 'AP';
        $query_lokasi = bukaquery("SELECT kd_bangsal FROM set_lokasi LIMIT 1");
        if($row_lokasi = mysqli_fetch_array($query_lokasi)){
            if(!empty($row_lokasi['kd_bangsal'])){
                $kd_bangsal = trim($row_lokasi['kd_bangsal']);
            }
        }
        
        // Query sesuai obat.php
        $query = "SELECT 
                    gb.kode_brng, 
                    db.nama_brng, 
                    FLOOR(gb.stok) AS stok, 
                    db.ralan,
                    db.kapasitas,
                    db.letak_barang
                  FROM gudangbarang gb
                  INNER JOIN databarang db ON gb.kode_brng = db.kode_brng
                  WHERE TRIM(gb.kd_bangsal) = '$kd_bangsal'
                    AND db.status = '1'
                    AND (gb.no_batch = '' OR gb.no_batch IS NULL)
                    AND (gb.no_faktur = '' OR gb.no_faktur IS NULL)
                    AND (db.nama_brng LIKE '%$keyword%' OR db.kode_brng LIKE '%$keyword%' OR db.letak_barang LIKE '%$keyword%')
                    AND gb.stok > 0
                  ORDER BY db.nama_brng ASC
                  LIMIT 20";
        
        $result = bukaquery($query);
        $data = [];
        
        if($result && mysqli_num_rows($result) > 0) {
            while($row = mysqli_fetch_assoc($result)) {
                $data[] = [
                    'kd_brng' => $row['kode_brng'],
                    'nama' => $row['nama_brng'],
                    'harga' => floatval($row['ralan']),
                    'stok' => intval($row['stok']),
                    'kapasitas' => $row['kapasitas'] ?? '',
                    'kandungan' => $row['letak_barang'] ?? ''
                ];
            }
        }
        
        echo json_encode(['status' => 'success', 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit();
    }

// ============================================
// SIMPAN OBAT PULANG
// ============================================
if($aksi == 'simpan_obat_pulang') {
    try {
        // Ambil data dari POST
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $obat_pulang = isset($_POST['obat_pulang']) ? $_POST['obat_pulang'] : '';
        
        // Decode JSON
        $obat_array = json_decode($obat_pulang, true);
        
        // Validasi input
        if (empty($norawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        if (empty($obat_array) || !is_array($obat_array)) {
            throw new Exception('Tidak ada obat yang akan disimpan. Silakan tambahkan minimal 1 obat.');
        }
        
        // Ambil kode dokter dari session
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        // === GENERATE NO_PERMINTAAN OTOMATIS: RP + YYYYMMDD + 0001 ===
        $tanggal_now = date('Ymd'); // Format: 20260116
        $prefix = "RP{$tanggal_now}";
        
        // Cari nomor urut terakhir hari ini
        $query_last = "SELECT no_permintaan FROM permintaan_resep_pulang 
                       WHERE no_permintaan LIKE '{$prefix}%' 
                       ORDER BY no_permintaan DESC LIMIT 1";
        $result_last = bukaquery($query_last);
        
        if (mysqli_num_rows($result_last) > 0) {
            $row = mysqli_fetch_assoc($result_last);
            $last_nopermintaan = $row['no_permintaan'];
            $last_number = intval(substr($last_nopermintaan, -4)); // Ambil 4 digit terakhir
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        $no_permintaan = $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
        
        // === TANGGAL DAN JAM SEKARANG ===
        $tgl_permintaan = date('Y-m-d');
        $jam = date('H:i:s');
        
        // === STATUS DEFAULT ===
        $status = 'Belum'; // Status validasi farmasi
        $tgl_validasi = '0000-00-00';
        $jam_validasi = '00:00:00';
        
        // === INSERT KE TABEL permintaan_resep_pulang (HEADER) ===
        $query_insert_header = "INSERT INTO permintaan_resep_pulang 
            (no_permintaan, tgl_permintaan, jam, no_rawat, kd_dokter, 
             status, tgl_validasi, jam_validasi) 
            VALUES 
            ('{$no_permintaan}', '{$tgl_permintaan}', '{$jam}', '{$norawat}', '{$kd_dokter}', 
             '{$status}', '{$tgl_validasi}', '{$jam_validasi}')";
        
        $result_header = bukaquery($query_insert_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menyimpan permintaan obat pulang');
        }
        
        // TRACKING: Simpan query header (jika ada function insertTracker)
        if(function_exists('insertTracker')) {
            insertTracker($query_insert_header);
        }
        
        // === SIMPAN DETAIL OBAT ===
        $count_obat = 0;
        
        foreach ($obat_array as $obat) {
            $kode_brng = isset($obat['kode_brng']) ? validTeks4($obat['kode_brng'], 15) : '';
            $jml = isset($obat['jml']) ? floatval($obat['jml']) : 0;
            $dosis = isset($obat['aturan_pakai']) ? validTeks4($obat['aturan_pakai'], 150) : '';
            
            // Validasi
            if (empty($kode_brng) || $jml <= 0) {
                continue; // Skip jika data tidak valid
            }
            
            // INSERT ke detail_permintaan_resep_pulang
            $query_insert_detail = "INSERT INTO detail_permintaan_resep_pulang 
                (no_permintaan, kode_brng, jml, dosis) 
                VALUES 
                ('{$no_permintaan}', '{$kode_brng}', '{$jml}', '{$dosis}')";
            
            $result_detail = bukaquery($query_insert_detail);
            
            if (!$result_detail) {
                throw new Exception('Gagal menyimpan detail obat: ' . $kode_brng);
            }
            
            // TRACKING
            if(function_exists('insertTracker')) {
                insertTracker($query_insert_detail);
            }
            
            $count_obat++;
        }
        
        if ($count_obat == 0) {
            throw new Exception('Tidak ada obat yang berhasil disimpan');
        }
        
        // === RESPONSE SUKSES ===
        echo json_encode([
            'status' => 'success',
            'message' => "Berhasil menyimpan {$count_obat} obat pulang",
            'no_permintaan' => $no_permintaan,
            'count' => $count_obat
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
    exit();
}

    // Default response
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak valid: ' . $aksi]);
    exit();

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
}
?>
