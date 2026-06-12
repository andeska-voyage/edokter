<?php
/**
 * get_riwayat_persalinan.php
 * API untuk mengambil dan mengelola data riwayat persalinan pasien
 * Tabel: riwayat_persalinan_pasien
 * 
 * VERSION: Fixed - Tidak require kolom 'id'
 */

session_start();
require_once(__DIR__ . "/../conf/conf.php");

// Matikan display error agar tidak merusak JSON
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

// Ambil koneksi dari GLOBALS
$koneksi = $GLOBALS['db_conn'];

// Cek session
if (!isset($_SESSION['ses_dokter'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session tidak valid']);
    exit();
}

$aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

// DEBUG: Log untuk troubleshooting
error_log('=== get_riwayat_persalinan.php ===');
error_log('aksi: ' . $aksi);
error_log('POST: ' . print_r($_POST, true));

// ========================================
// AUTO-DETECT FIELD NAMES
// ========================================
function getTableFieldNames($koneksi) {
    static $fieldNames = null;
    
    if ($fieldNames !== null) {
        return $fieldNames;
    }
    
    // Default names (tanpa underscore)
    $fieldNames = [
        'usiahamil' => 'usiahamil',
        'bbpb' => 'bbpb',
        'has_id' => false
    ];
    
    // Cek struktur tabel untuk auto-detect
    $query = "SHOW COLUMNS FROM riwayat_persalinan_pasien";
    $result = mysqli_query($koneksi, $query);
    
    if ($result) {
        $columns = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = $row['Field'];
        }
        
        error_log('Table columns: ' . json_encode($columns));
        
        // Auto-detect: gunakan nama yang ada di tabel
        if (in_array('usia_hamil', $columns)) {
            $fieldNames['usiahamil'] = 'usia_hamil';
        }
        if (in_array('bb_pb', $columns)) {
            $fieldNames['bbpb'] = 'bb_pb';
        }
        if (in_array('id', $columns)) {
            $fieldNames['has_id'] = true;
        }
        
        error_log('Field names detected: ' . json_encode($fieldNames));
    }
    
    return $fieldNames;
}

// ========================================
// SEARCH PASIEN IBU (AUTOCOMPLETE)
// ========================================
if ($aksi === 'search_pasien_ibu') {
    
    try {
        $keyword = isset($_POST['keyword']) ? mysqli_real_escape_string($koneksi, $_POST['keyword']) : '';
        
        if (empty($keyword) || strlen($keyword) < 2) {
            throw new Exception('Keyword terlalu pendek');
        }
        
        // Cari pasien berdasarkan no_rkm_medis atau nm_pasien
        $query = "SELECT no_rkm_medis, nm_pasien, tgl_lahir 
                  FROM pasien 
                  WHERE (no_rkm_medis LIKE '%{$keyword}%' OR nm_pasien LIKE '%{$keyword}%')
                  AND jk = 'P'
                  ORDER BY nm_pasien ASC
                  LIMIT 15";
        
        $result = bukaquery($query);
        $data = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'no_rkm_medis' => $row['no_rkm_medis'],
                'nm_pasien'    => $row['nm_pasien'],
                'tgl_lahir'    => $row['tgl_lahir']
            ];
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
    
    exit();
}

// ========================================
// GET RIWAYAT PERSALINAN IBU
// ========================================
if ($aksi === 'get_riwayat_persalinan') {
    
    try {
        $no_rkm_medis_ibu = isset($_POST['no_rkm_medis_ibu']) ? mysqli_real_escape_string($koneksi, $_POST['no_rkm_medis_ibu']) : '';
        
        error_log('GET_RIWAYAT: no_rkm_medis_ibu = ' . $no_rkm_medis_ibu);
        
        if (empty($no_rkm_medis_ibu)) {
            throw new Exception('No. RM tidak valid');
        }
        
        // Auto-detect field names
        $fields = getTableFieldNames($koneksi);
        
        // Build query - with or without id
        $selectFields = "tgl_thn, tempat_persalinan, {$fields['usiahamil']} as usiahamil, jenis_persalinan, 
                         penolong, penyulit, jk, {$fields['bbpb']} as bbpb, keadaan";
        
        // Jika ada kolom id, include
        if ($fields['has_id']) {
            $selectFields = "id, " . $selectFields;
        }
        
        // Ambil data dari tabel riwayat_persalinan_pasien
        $query = "SELECT {$selectFields}, no_rkm_medis
                  FROM riwayat_persalinan_pasien 
                  WHERE no_rkm_medis = '{$no_rkm_medis_ibu}'
                  ORDER BY tgl_thn DESC";
        
        error_log('GET_RIWAYAT QUERY: ' . $query);
        
        $result = bukaquery($query);
        
        if (!$result) {
            error_log('GET_RIWAYAT ERROR: ' . mysqli_error($koneksi));
            throw new Exception('Query error: ' . mysqli_error($koneksi));
        }
        
        $data = [];
        $index = 0;
        
        while ($row = mysqli_fetch_assoc($result)) {
            $index++;
            
            // Gunakan kombinasi no_rkm_medis + tgl_thn + index sebagai unique identifier
            $uniqueId = $row['no_rkm_medis'] . '_' . $row['tgl_thn'] . '_' . $index;
            
            $data[] = [
                'id' => $fields['has_id'] && isset($row['id']) ? $row['id'] : $uniqueId,
                'tgl_thn' => $row['tgl_thn'],
                'tempat_persalinan' => $row['tempat_persalinan'],
                'usiahamil' => $row['usiahamil'],
                'jenis_persalinan' => $row['jenis_persalinan'],
                'penolong' => $row['penolong'],
                'penyulit' => $row['penyulit'],
                'jk' => $row['jk'],
                'bbpb' => $row['bbpb'],
                'keadaan' => $row['keadaan'],
                'no_rkm_medis' => $row['no_rkm_medis']
            ];
        }
        
        error_log('GET_RIWAYAT RESULT: ' . count($data) . ' records found');
        
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
        
    } catch (Exception $e) {
        error_log('GET_RIWAYAT ERROR: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ========================================
// SIMPAN RIWAYAT PERSALINAN IBU
// ========================================
if ($aksi === 'simpan_riwayat_persalinan') {
    
    try {
        $no_rkm_medis = isset($_POST['no_rkm_medis_ibu']) ? mysqli_real_escape_string($koneksi, $_POST['no_rkm_medis_ibu']) : '';
        
        if (empty($no_rkm_medis)) {
            throw new Exception('No. RM Ibu harus dipilih terlebih dahulu');
        }
        
        // Ambil data dari POST
        $tgl_thn = isset($_POST['tgl_thn']) ? mysqli_real_escape_string($koneksi, $_POST['tgl_thn']) : '';
        $tempat_persalinan = isset($_POST['tempat_persalinan']) ? mysqli_real_escape_string($koneksi, $_POST['tempat_persalinan']) : '';
        $usiahamil = isset($_POST['usiahamil']) ? mysqli_real_escape_string($koneksi, $_POST['usiahamil']) : '';
        $jenis_persalinan = isset($_POST['jenis_persalinan']) ? mysqli_real_escape_string($koneksi, $_POST['jenis_persalinan']) : '';
        $penolong = isset($_POST['penolong']) ? mysqli_real_escape_string($koneksi, $_POST['penolong']) : '';
        $penyulit = isset($_POST['penyulit']) ? mysqli_real_escape_string($koneksi, $_POST['penyulit']) : '';
        $jk = isset($_POST['jk']) ? mysqli_real_escape_string($koneksi, $_POST['jk']) : '';
        $bbpb = isset($_POST['bbpb']) ? mysqli_real_escape_string($koneksi, $_POST['bbpb']) : '';
        $keadaan = isset($_POST['keadaan']) ? mysqli_real_escape_string($koneksi, $_POST['keadaan']) : '';
        
        // Auto-detect field names
        $fields = getTableFieldNames($koneksi);
        
        // Insert ke tabel riwayat_persalinan_pasien
        $query = "INSERT INTO riwayat_persalinan_pasien 
                  (no_rkm_medis, tgl_thn, tempat_persalinan, {$fields['usiahamil']}, jenis_persalinan, 
                   penolong, penyulit, jk, {$fields['bbpb']}, keadaan) 
                  VALUES 
                  ('{$no_rkm_medis}', '{$tgl_thn}', '{$tempat_persalinan}', '{$usiahamil}', '{$jenis_persalinan}',
                   '{$penolong}', '{$penyulit}', '{$jk}', '{$bbpb}', '{$keadaan}')";
        
        error_log('SIMPAN QUERY: ' . $query);
        
        $result = bukaquery($query);
        
        if ($result) {
            if (function_exists('insertTracker')) {
                insertTracker($query);
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Riwayat persalinan berhasil disimpan'
            ]);
        } else {
            throw new Exception('Gagal menyimpan data: ' . mysqli_error($koneksi));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ========================================
// HAPUS RIWAYAT PERSALINAN IBU
// ========================================
if ($aksi === 'hapus_riwayat_persalinan') {
    
    try {
        $fields = getTableFieldNames($koneksi);
        
        if ($fields['has_id']) {
            // Jika ada kolom id, gunakan id
            $id = isset($_POST['id']) ? mysqli_real_escape_string($koneksi, $_POST['id']) : '';
            
            if (empty($id)) {
                throw new Exception('ID tidak valid untuk dihapus');
            }
            
            $query = "DELETE FROM riwayat_persalinan_pasien 
                      WHERE id = '{$id}'
                      LIMIT 1";
        } else {
            // Jika tidak ada id, gunakan composite key
            $no_rkm_medis = isset($_POST['no_rkm_medis']) ? mysqli_real_escape_string($koneksi, $_POST['no_rkm_medis']) : '';
            $tgl_thn = isset($_POST['tgl_thn']) ? mysqli_real_escape_string($koneksi, $_POST['tgl_thn']) : '';
            
            if (empty($no_rkm_medis) || empty($tgl_thn)) {
                throw new Exception('Data tidak lengkap untuk dihapus');
            }
            
            $query = "DELETE FROM riwayat_persalinan_pasien 
                      WHERE no_rkm_medis = '{$no_rkm_medis}' 
                      AND tgl_thn = '{$tgl_thn}'
                      LIMIT 1";
        }
        
        error_log('HAPUS QUERY: ' . $query);
        
        $result = bukaquery($query);
        
        if ($result) {
            if (function_exists('insertTracker')) {
                insertTracker($query);
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Riwayat persalinan berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus data: ' . mysqli_error($koneksi));
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ========================================
// GET TGL LAHIR PASIEN (fallback untuk autocomplete)
// ========================================
if ($aksi === 'get_tgl_lahir_pasien') {
    try {
        $no_rkm_medis = isset($_POST['no_rkm_medis']) ? mysqli_real_escape_string($koneksi, $_POST['no_rkm_medis']) : '';
        if (empty($no_rkm_medis)) throw new Exception('No. RM kosong');

        $query  = "SELECT tgl_lahir FROM pasien WHERE no_rkm_medis = '{$no_rkm_medis}' LIMIT 1";
        $result = bukaquery($query);
        $row    = mysqli_fetch_assoc($result);

        if ($row && !empty($row['tgl_lahir'])) {
            echo json_encode(['status' => 'success', 'tgl_lahir' => $row['tgl_lahir']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// Jika aksi tidak dikenali
echo json_encode([
    'status' => 'error',
    'message' => 'Aksi tidak valid'
]);
exit();
?>