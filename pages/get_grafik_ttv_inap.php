<?php
/**
 * API untuk mengambil data grafik TTV & Balance Cairan Rawat Inap
 * Mengembalikan data dalam format JSON untuk Chart.js
 * 
 * TTV dari: pemeriksaan_ranap (atau sumber lain)
 * Balance Cairan dari: catatan_keseimbangan_cairan
 */
session_start();
require_once('../conf/conf.php');

header('Content-Type: application/json');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit();
}

// Ambil parameter
$norm = isset($_POST['norm']) ? validTeks4($_POST['norm'], 20) : '';
$ttv_params = isset($_POST['ttv_params']) ? $_POST['ttv_params'] : [];
$cairan_params = isset($_POST['cairan_params']) ? $_POST['cairan_params'] : [];
$ventilator_params = isset($_POST['ventilator_params']) ? $_POST['ventilator_params'] : [];
$chbp_params = isset($_POST['chbp_params']) ? $_POST['chbp_params'] : [];
$filter_norawat = isset($_POST['filter_norawat']) ? validTeks4($_POST['filter_norawat'], 20) : '';
$sumber_data = isset($_POST['sumber_data']) ? validTeks4($_POST['sumber_data'], 50) : 'pemeriksaan_ranap';
$rentang_waktu = isset($_POST['rentang_waktu']) ? validTeks4($_POST['rentang_waktu'], 10) : 'all';
$mode_tampilan = isset($_POST['mode_tampilan']) ? validTeks4($_POST['mode_tampilan'], 20) : 'gabung';

if(empty($norm)){
    echo json_encode(['success' => false, 'message' => 'Parameter NO RM tidak valid']);
    exit();
}

// Validasi: minimal ada 1 parameter
if(empty($ttv_params) && empty($cairan_params) && empty($ventilator_params) && empty($chbp_params)){
    echo json_encode(['success' => false, 'message' => 'Pilih minimal 1 parameter untuk membuat grafik']);
    exit();
}

// Validasi params TTV yang diizinkan
$allowed_ttv = ['tensi', 'nadi', 'suhu', 'respirasi', 'spo2', 'gcs'];
$ttv_params = is_array($ttv_params) ? array_intersect($ttv_params, $allowed_ttv) : [];

// Validasi params Cairan yang diizinkan
$allowed_cairan = ['infus', 'tranfusi', 'minum', 'urine', 'drain', 'ngt', 'iwl', 'keseimbangan'];
$cairan_params = is_array($cairan_params) ? array_intersect($cairan_params, $allowed_cairan) : [];

// Validasi params Ventilator yang diizinkan
$allowed_ventilator = ['vt', 'rr_vent', 'peep', 'fio2'];
$ventilator_params = is_array($ventilator_params) ? array_intersect($ventilator_params, $allowed_ventilator) : [];

// Validasi params CHBP yang diizinkan
$allowed_chbp = ['td_chbp', 'hr_chbp', 'suhu_chbp', 'djj'];
$chbp_params = is_array($chbp_params) ? array_intersect($chbp_params, $allowed_chbp) : [];

// Sumber data info
$sumber_labels = [
    'pemeriksaan_ranap' => 'Pemeriksaan Ranap (SOAPIE)',
    'observasi_ranap' => 'Observasi Ranap'
];
$sumber_info = isset($sumber_labels[$sumber_data]) ? $sumber_labels[$sumber_data] : 'Pemeriksaan Ranap';

// Hitung tanggal filter berdasarkan rentang waktu
$date_filter = '';
$datetime_filter = ''; // Untuk filter dengan jam
$rentang_label = '24 Jam Terakhir';

// Hitung waktu sekarang dan filter
$now_timestamp = time();

switch($rentang_waktu) {
    case '3h':
        $filter_timestamp = strtotime('-3 hours');
        $rentang_label = '3 Jam Terakhir';
        break;
    case '6h':
        $filter_timestamp = strtotime('-6 hours');
        $rentang_label = '6 Jam Terakhir';
        break;
    case '12h':
        $filter_timestamp = strtotime('-12 hours');
        $rentang_label = '12 Jam Terakhir';
        break;
    case '24h':
        $filter_timestamp = strtotime('-24 hours');
        $rentang_label = '24 Jam Terakhir';
        break;
    case '3d':
        $filter_timestamp = strtotime('-3 days');
        $rentang_label = '3 Hari Terakhir';
        break;
    case '7d':
        $filter_timestamp = strtotime('-7 days');
        $rentang_label = '7 Hari Terakhir';
        break;
    default:
        $filter_timestamp = 0;
        $rentang_label = 'Semua Data';
        break;
}

// Format untuk SQL - pisahkan tanggal dan jam
if($filter_timestamp > 0) {
    $filter_date = date('Y-m-d', $filter_timestamp);
    $filter_time = date('H:i:s', $filter_timestamp);
    $datetime_filter = date('Y-m-d H:i:s', $filter_timestamp);
} else {
    $filter_date = '';
    $filter_time = '';
    $datetime_filter = '';
}

// DEBUG: Simpan info filter untuk response
$debug_filter = [
    'server_time' => date('Y-m-d H:i:s'),
    'filter_timestamp' => $filter_timestamp,
    'datetime_filter' => $datetime_filter,
    'filter_date' => $filter_date,
    'filter_time' => $filter_time,
    'rentang_waktu' => $rentang_waktu
];

// Untuk backward compatibility
$date_filter = $datetime_filter;

// Helper: Hitung filter relatif berdasarkan data terakhir
function getRelativeFilter($last_datetime, $rentang_waktu) {
    if(empty($last_datetime) || $rentang_waktu == 'all') {
        return null;
    }
    
    $hours_map = [
        '3h' => 3,
        '6h' => 6,
        '12h' => 12,
        '24h' => 24,
        '3d' => 72,
        '7d' => 168
    ];
    
    if(!isset($hours_map[$rentang_waktu])) {
        return null;
    }
    
    $hours = $hours_map[$rentang_waktu];
    $last_ts = strtotime($last_datetime);
    $filter_ts = $last_ts - ($hours * 3600);
    
    return [
        'date' => date('Y-m-d', $filter_ts),
        'time' => date('H:i:s', $filter_ts),
        'datetime' => date('Y-m-d H:i:s', $filter_ts)
    ];
}

// Initialize
$datasets = [];
$latest = [];
$all_timestamps = [];

// Label mapping
$param_labels = [
    'tensi' => 'Tekanan Darah',
    'sistolik' => 'Sistolik',
    'diastolik' => 'Diastolik',
    'nadi' => 'Nadi',
    'suhu' => 'Suhu',
    'respirasi' => 'RR',
    'spo2' => 'SpO₂',
    'gcs' => 'GCS',
    'infus' => 'Infus',
    'tranfusi' => 'Tranfusi',
    'minum' => 'Minum',
    'urine' => 'Urine',
    'drain' => 'Drain',
    'ngt' => 'NGT',
    'iwl' => 'IWL',
    'keseimbangan' => 'Balance',
    // Ventilator
    'vt' => 'Tidal Volume',
    'rr_vent' => 'RR Ventilator',
    'peep' => 'PEEP/PS',
    'fio2' => 'FiO₂/EE',
    // CHBP
    'sistolik_chbp' => 'Sistolik (CHBP)',
    'diastolik_chbp' => 'Diastolik (CHBP)',
    'hr_chbp' => 'Heart Rate (CHBP)',
    'suhu_chbp' => 'Suhu (CHBP)',
    'djj' => 'DJJ'
];

// Normal ranges untuk evaluasi status TTV
$normal_ranges = [
    'sistolik' => ['min' => 90, 'max' => 140],
    'diastolik' => ['min' => 60, 'max' => 90],
    'nadi' => ['min' => 60, 'max' => 100],
    'suhu' => ['min' => 36.0, 'max' => 37.5],
    'respirasi' => ['min' => 12, 'max' => 20],
    'spo2' => ['min' => 95, 'max' => 100],
    'gcs' => ['min' => 13, 'max' => 15]
];

// Function untuk evaluasi status
function evaluateStatus($param, $value, $ranges) {
    if(!isset($ranges[$param]) || $value === null || $value === '') {
        return 'normal';
    }
    
    $range = $ranges[$param];
    $numValue = floatval($value);
    
    if($numValue < $range['min']) {
        return 'low';
    } else if($numValue > $range['max']) {
        return 'high';
    }
    
    return 'normal';
}

// ========================================
// QUERY DATA TTV (jika ada param TTV)
// ========================================
if(!empty($ttv_params)) {
    // Query data TTV berdasarkan sumber data
    switch($sumber_data) {
        case 'observasi_ranap':
            // STEP 1: Ambil waktu data terakhir
            $where_obs_base = "rp.no_rkm_medis = '$norm'";
            if(!empty($filter_norawat)){
                $where_obs_base .= " AND cor.no_rawat = '$filter_norawat'";
            }
            
            $query_last_obs = bukaquery("
                SELECT MAX(CONCAT(cor.tgl_perawatan, ' ', cor.jam_rawat)) as last_datetime
                FROM catatan_observasi_ranap cor
                INNER JOIN reg_periksa rp ON cor.no_rawat = rp.no_rawat
                WHERE $where_obs_base
            ");
            $last_obs = mysqli_fetch_assoc($query_last_obs);
            $rel_filter = getRelativeFilter($last_obs['last_datetime'], $rentang_waktu);
            
            // STEP 2: Build WHERE dengan filter relatif
            $where_obs = $where_obs_base;
            if($rel_filter){
                $where_obs .= " AND (cor.tgl_perawatan > '{$rel_filter['date']}' OR (cor.tgl_perawatan = '{$rel_filter['date']}' AND cor.jam_rawat >= '{$rel_filter['time']}'))";
            }
            
            $query_ttv = bukaquery("
                SELECT 
                    cor.no_rawat,
                    cor.tgl_perawatan,
                    cor.jam_rawat,
                    cor.td as tensi,
                    cor.hr as nadi,
                    cor.suhu,
                    cor.rr as respirasi,
                    cor.spo2,
                    cor.gcs,
                    pg.nama as nama_petugas
                FROM catatan_observasi_ranap cor
                INNER JOIN reg_periksa rp ON cor.no_rawat = rp.no_rawat
                LEFT JOIN pegawai pg ON cor.nip = pg.nik
                WHERE $where_obs
                ORDER BY cor.tgl_perawatan ASC, cor.jam_rawat ASC
            ");
            break;
            
        case 'pemeriksaan_ranap':
        default:
            // STEP 1: Ambil waktu data terakhir
            $where_ttv_base = "rp.no_rkm_medis = '$norm'";
            if(!empty($filter_norawat)){
                $where_ttv_base .= " AND pr.no_rawat = '$filter_norawat'";
            }
            
            $query_last_ttv = bukaquery("
                SELECT MAX(CONCAT(pr.tgl_perawatan, ' ', pr.jam_rawat)) as last_datetime
                FROM pemeriksaan_ranap pr
                INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                WHERE $where_ttv_base
            ");
            $last_ttv = mysqli_fetch_assoc($query_last_ttv);
            $rel_filter = getRelativeFilter($last_ttv['last_datetime'], $rentang_waktu);
            
            // STEP 2: Build WHERE dengan filter relatif
            $where_ttv = $where_ttv_base;
            if($rel_filter){
                $where_ttv .= " AND (pr.tgl_perawatan > '{$rel_filter['date']}' OR (pr.tgl_perawatan = '{$rel_filter['date']}' AND pr.jam_rawat >= '{$rel_filter['time']}'))";
            }
            
            $query_ttv = bukaquery("
                SELECT 
                    pr.no_rawat,
                    pr.tgl_perawatan,
                    pr.jam_rawat,
                    pr.tensi,
                    pr.nadi,
                    pr.suhu_tubuh as suhu,
                    pr.respirasi,
                    pr.spo2,
                    pr.gcs,
                    pg.nama as nama_petugas
                FROM pemeriksaan_ranap pr
                INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                LEFT JOIN pegawai pg ON pr.nip = pg.nik
                WHERE $where_ttv
                ORDER BY pr.tgl_perawatan ASC, pr.jam_rawat ASC
            ");
            break;
    }
    
    $ttv_data = [];
    while($row = mysqli_fetch_assoc($query_ttv)){
        $ttv_data[] = $row;
    }
    
    // Process TTV data
    if(count($ttv_data) >= 2) {
        foreach($ttv_params as $param) {
            if($param === 'tensi') {
                // Tensi perlu di-split jadi sistolik dan diastolik
                $sistolik_data = [];
                $diastolik_data = [];
                
                foreach($ttv_data as $row) {
                    $tensi = $row['tensi'];
                    $datetime = date('d/m H:i', strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']));
                    $timestamp = strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']);
                    
                    $parts = explode('/', $tensi);
                    
                    if(count($parts) == 2) {
                        $sis = intval($parts[0]);
                        $dia = intval($parts[1]);
                        
                        if($sis > 0) {
                            $sistolik_data[] = [
                                'x' => $datetime,
                                'y' => $sis,
                                'timestamp' => $timestamp,
                                'status' => evaluateStatus('sistolik', $sis, $normal_ranges),
                                'petugas' => $row['nama_petugas'],
                                'no_rawat' => $row['no_rawat']
                            ];
                            $all_timestamps[$timestamp] = $datetime;
                        }
                        
                        if($dia > 0) {
                            $diastolik_data[] = [
                                'x' => $datetime,
                                'y' => $dia,
                                'timestamp' => $timestamp,
                                'status' => evaluateStatus('diastolik', $dia, $normal_ranges),
                                'petugas' => $row['nama_petugas'],
                                'no_rawat' => $row['no_rawat']
                            ];
                        }
                    }
                }
                
                if(!empty($sistolik_data)) {
                    $datasets[] = [
                        'key' => 'sistolik',
                        'label' => 'Sistolik (mmHg)',
                        'data' => $sistolik_data
                    ];
                    $lastSis = end($sistolik_data);
                    $latest['sistolik'] = $lastSis['y'];
                }
                
                if(!empty($diastolik_data)) {
                    $datasets[] = [
                        'key' => 'diastolik',
                        'label' => 'Diastolik (mmHg)',
                        'data' => $diastolik_data
                    ];
                    $lastDia = end($diastolik_data);
                    $latest['diastolik'] = $lastDia['y'];
                }
                
            } else {
                // Parameter TTV lain
                $param_data = [];
                
                foreach($ttv_data as $row) {
                    $value = $row[$param];
                    $datetime = date('d/m H:i', strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']));
                    $timestamp = strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']);
                    
                    if($value !== null && $value !== '' && floatval($value) > 0) {
                        $numValue = floatval($value);
                        
                        $param_data[] = [
                            'x' => $datetime,
                            'y' => $numValue,
                            'timestamp' => $timestamp,
                            'status' => evaluateStatus($param, $numValue, $normal_ranges),
                            'petugas' => $row['nama_petugas'],
                            'no_rawat' => $row['no_rawat']
                        ];
                        $all_timestamps[$timestamp] = $datetime;
                    }
                }
                
                if(!empty($param_data)) {
                    $units = [
                        'nadi' => 'x/mnt',
                        'suhu' => '°C',
                        'respirasi' => 'x/mnt',
                        'spo2' => '%',
                        'gcs' => ''
                    ];
                    $unit = isset($units[$param]) ? ' ('.$units[$param].')' : '';
                    
                    $datasets[] = [
                        'key' => $param,
                        'label' => $param_labels[$param] . $unit,
                        'data' => $param_data
                    ];
                    
                    $lastVal = end($param_data);
                    $latest[$param] = $lastVal['y'];
                }
            }
        }
    }
}

// ========================================
// QUERY DATA BALANCE CAIRAN (jika ada param cairan)
// ========================================
if(!empty($cairan_params)) {
    // STEP 1: Ambil waktu data terakhir
    $where_cairan_base = "rp.no_rkm_medis = '$norm'";
    if(!empty($filter_norawat)){
        $where_cairan_base .= " AND ckc.no_rawat = '$filter_norawat'";
    }
    
    $query_last_cairan = bukaquery("
        SELECT MAX(CONCAT(ckc.tgl_perawatan, ' ', ckc.jam_rawat)) as last_datetime
        FROM catatan_keseimbangan_cairan ckc
        INNER JOIN reg_periksa rp ON ckc.no_rawat = rp.no_rawat
        WHERE $where_cairan_base
    ");
    $last_cairan = mysqli_fetch_assoc($query_last_cairan);
    $rel_filter = getRelativeFilter($last_cairan['last_datetime'], $rentang_waktu);
    
    // STEP 2: Build WHERE dengan filter relatif
    $where_cairan = $where_cairan_base;
    if($rel_filter){
        $where_cairan .= " AND (ckc.tgl_perawatan > '{$rel_filter['date']}' OR (ckc.tgl_perawatan = '{$rel_filter['date']}' AND ckc.jam_rawat >= '{$rel_filter['time']}'))";
    }
    
    $query_cairan = bukaquery("
        SELECT 
            ckc.no_rawat,
            ckc.tgl_perawatan,
            ckc.jam_rawat,
            ckc.infus,
            ckc.tranfusi,
            ckc.minum,
            ckc.urine,
            ckc.drain,
            ckc.ngt,
            ckc.iwl,
            ckc.keseimbangan,
            ckc.keterangan,
            pg.nama as nama_petugas
        FROM catatan_keseimbangan_cairan ckc
        INNER JOIN reg_periksa rp ON ckc.no_rawat = rp.no_rawat
        LEFT JOIN pegawai pg ON ckc.nip = pg.nik
        WHERE $where_cairan
        ORDER BY ckc.tgl_perawatan ASC, ckc.jam_rawat ASC
    ");
    
    $cairan_data = [];
    while($row = mysqli_fetch_assoc($query_cairan)){
        $cairan_data[] = $row;
    }
    
    // Process Cairan data
    if(count($cairan_data) >= 1) { // Minimal 1 data untuk cairan
        foreach($cairan_params as $param) {
            $param_data = [];
            
            foreach($cairan_data as $row) {
                $value = $row[$param];
                $datetime = date('d/m H:i', strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']));
                $timestamp = strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']);
                
                // Cairan bisa bernilai 0 atau kosong, tapi kita tetap tampilkan jika ada record
                $numValue = ($value !== null && $value !== '') ? floatval($value) : 0;
                
                $param_data[] = [
                    'x' => $datetime,
                    'y' => $numValue,
                    'timestamp' => $timestamp,
                    'status' => 'normal', // Cairan tidak ada normal range
                    'petugas' => $row['nama_petugas'],
                    'no_rawat' => $row['no_rawat'],
                    'keterangan' => $row['keterangan']
                ];
                $all_timestamps[$timestamp] = $datetime;
            }
            
            if(!empty($param_data)) {
                // Tentukan label berdasarkan tipe (input/output/balance)
                $type_suffix = '';
                if(in_array($param, ['infus', 'tranfusi', 'minum'])) {
                    $type_suffix = ' [Input]';
                } else if(in_array($param, ['urine', 'drain', 'ngt', 'iwl'])) {
                    $type_suffix = ' [Output]';
                } else if($param === 'keseimbangan') {
                    $type_suffix = ' [Balance]';
                }
                
                $datasets[] = [
                    'key' => $param,
                    'label' => $param_labels[$param] . ' (cc)' . $type_suffix,
                    'data' => $param_data
                ];
                
                $lastVal = end($param_data);
                $latest[$param] = $lastVal['y'];
            }
        }
    }
}

// ========================================
// QUERY DATA VENTILATOR (jika ada param ventilator)
// ========================================
if(!empty($ventilator_params)) {
    // STEP 1: Ambil waktu data terakhir
    $where_vent_base = "rp.no_rkm_medis = '$norm'";
    if(!empty($filter_norawat)){
        $where_vent_base .= " AND cov.no_rawat = '$filter_norawat'";
    }
    
    $query_last_vent = bukaquery("
        SELECT MAX(CONCAT(cov.tgl_perawatan, ' ', cov.jam_rawat)) as last_datetime
        FROM catatan_observasi_ventilator cov
        INNER JOIN reg_periksa rp ON cov.no_rawat = rp.no_rawat
        WHERE $where_vent_base
    ");
    $last_vent = mysqli_fetch_assoc($query_last_vent);
    $rel_filter = getRelativeFilter($last_vent['last_datetime'], $rentang_waktu);
    
    // STEP 2: Build WHERE dengan filter relatif
    $where_vent = $where_vent_base;
    if($rel_filter){
        $where_vent .= " AND (cov.tgl_perawatan > '{$rel_filter['date']}' OR (cov.tgl_perawatan = '{$rel_filter['date']}' AND cov.jam_rawat >= '{$rel_filter['time']}'))";
    }
    
    $query_vent = bukaquery("
        SELECT 
            cov.no_rawat,
            cov.tgl_perawatan,
            cov.jam_rawat,
            cov.mode,
            cov.vt,
            cov.rr,
            cov.reefps,
            cov.ee,
            pg.nama as nama_petugas
        FROM catatan_observasi_ventilator cov
        INNER JOIN reg_periksa rp ON cov.no_rawat = rp.no_rawat
        LEFT JOIN pegawai pg ON cov.nip = pg.nik
        WHERE $where_vent
        ORDER BY cov.tgl_perawatan ASC, cov.jam_rawat ASC
    ");
    
    $vent_data = [];
    while($row = mysqli_fetch_assoc($query_vent)){
        $vent_data[] = $row;
    }
    
    // Process Ventilator data
    if(count($vent_data) >= 1) {
        // Mapping field database ke param
        $vent_field_map = [
            'vt' => 'vt',           // Tidal Volume
            'rr_vent' => 'rr',      // RR Ventilator
            'peep' => 'reefps',     // PEEP/PS
            'fio2' => 'ee'          // FiO2/EE
        ];
        
        $vent_units = [
            'vt' => 'ml',
            'rr_vent' => 'x/mnt',
            'peep' => 'cmH₂O',
            'fio2' => '%'
        ];
        
        foreach($ventilator_params as $param) {
            $db_field = isset($vent_field_map[$param]) ? $vent_field_map[$param] : $param;
            $param_data = [];
            
            foreach($vent_data as $row) {
                $value = $row[$db_field];
                $datetime = date('d/m H:i', strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']));
                $timestamp = strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']);
                
                // Ventilator bisa bernilai 0 atau kosong
                if($value !== null && $value !== '' && floatval($value) > 0) {
                    $numValue = floatval($value);
                    
                    $param_data[] = [
                        'x' => $datetime,
                        'y' => $numValue,
                        'timestamp' => $timestamp,
                        'status' => 'normal',
                        'petugas' => $row['nama_petugas'],
                        'no_rawat' => $row['no_rawat'],
                        'mode' => $row['mode']
                    ];
                    $all_timestamps[$timestamp] = $datetime;
                }
            }
            
            if(!empty($param_data)) {
                $unit = isset($vent_units[$param]) ? ' ('.$vent_units[$param].')' : '';
                
                $datasets[] = [
                    'key' => $param,
                    'label' => $param_labels[$param] . $unit,
                    'data' => $param_data
                ];
                
                $lastVal = end($param_data);
                $latest[$param] = $lastVal['y'];
            }
        }
    }
}

// ========================================
// QUERY DATA CHBP (jika ada param chbp)
// ========================================
if(!empty($chbp_params)) {
    // STEP 1: Ambil waktu data terakhir untuk filter relatif
    $where_chbp_base = "rp.no_rkm_medis = '$norm'";
    if(!empty($filter_norawat)){
        $where_chbp_base .= " AND coc.no_rawat = '$filter_norawat'";
    }
    
    $query_last_chbp = bukaquery("
        SELECT MAX(CONCAT(coc.tgl_perawatan, ' ', coc.jam_rawat)) as last_datetime
        FROM catatan_observasi_chbp coc
        INNER JOIN reg_periksa rp ON coc.no_rawat = rp.no_rawat
        WHERE $where_chbp_base
    ");
    $last_chbp = mysqli_fetch_assoc($query_last_chbp);
    $rel_filter = getRelativeFilter($last_chbp['last_datetime'], $rentang_waktu);
    
    // STEP 2: Build WHERE dengan filter relatif
    $where_chbp = $where_chbp_base;
    if($rel_filter){
        $where_chbp .= " AND (coc.tgl_perawatan > '{$rel_filter['date']}' OR (coc.tgl_perawatan = '{$rel_filter['date']}' AND coc.jam_rawat >= '{$rel_filter['time']}'))";
    }
    
    $query_chbp = bukaquery("
        SELECT 
            coc.no_rawat,
            coc.tgl_perawatan,
            coc.jam_rawat,
            coc.td,
            coc.hr,
            coc.suhu,
            coc.djj,
            pg.nama as nama_petugas
        FROM catatan_observasi_chbp coc
        INNER JOIN reg_periksa rp ON coc.no_rawat = rp.no_rawat
        LEFT JOIN pegawai pg ON coc.nip = pg.nik
        WHERE $where_chbp
        ORDER BY coc.tgl_perawatan ASC, coc.jam_rawat ASC
    ");
    
    $chbp_data = [];
    while($row = mysqli_fetch_assoc($query_chbp)){
        $chbp_data[] = $row;
    }
    
    // Process CHBP data
    if(count($chbp_data) >= 1) {
        foreach($chbp_params as $param) {
            if($param === 'td_chbp') {
                // TD perlu di-split jadi sistolik dan diastolik
                $sistolik_data = [];
                $diastolik_data = [];
                
                foreach($chbp_data as $row) {
                    $td = $row['td'];
                    $datetime = date('d/m H:i', strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']));
                    $timestamp = strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']);
                    
                    $parts = explode('/', $td);
                    
                    if(count($parts) == 2) {
                        $sis = intval($parts[0]);
                        $dia = intval($parts[1]);
                        
                        if($sis > 0) {
                            $sistolik_data[] = [
                                'x' => $datetime,
                                'y' => $sis,
                                'timestamp' => $timestamp,
                                'status' => evaluateStatus('sistolik', $sis, $normal_ranges),
                                'petugas' => $row['nama_petugas'],
                                'no_rawat' => $row['no_rawat']
                            ];
                            $all_timestamps[$timestamp] = $datetime;
                        }
                        
                        if($dia > 0) {
                            $diastolik_data[] = [
                                'x' => $datetime,
                                'y' => $dia,
                                'timestamp' => $timestamp,
                                'status' => evaluateStatus('diastolik', $dia, $normal_ranges),
                                'petugas' => $row['nama_petugas'],
                                'no_rawat' => $row['no_rawat']
                            ];
                        }
                    }
                }
                
                if(!empty($sistolik_data)) {
                    $datasets[] = [
                        'key' => 'sistolik_chbp',
                        'label' => 'Sistolik CHBP (mmHg)',
                        'data' => $sistolik_data
                    ];
                    $lastSis = end($sistolik_data);
                    $latest['sistolik_chbp'] = $lastSis['y'];
                }
                
                if(!empty($diastolik_data)) {
                    $datasets[] = [
                        'key' => 'diastolik_chbp',
                        'label' => 'Diastolik CHBP (mmHg)',
                        'data' => $diastolik_data
                    ];
                    $lastDia = end($diastolik_data);
                    $latest['diastolik_chbp'] = $lastDia['y'];
                }
                
            } else {
                // Parameter CHBP lain: hr_chbp, suhu_chbp, djj
                $chbp_field_map = [
                    'hr_chbp' => 'hr',
                    'suhu_chbp' => 'suhu',
                    'djj' => 'djj'
                ];
                
                $chbp_units = [
                    'hr_chbp' => 'x/mnt',
                    'suhu_chbp' => '°C',
                    'djj' => 'x/mnt'
                ];
                
                $db_field = isset($chbp_field_map[$param]) ? $chbp_field_map[$param] : $param;
                $param_data = [];
                
                foreach($chbp_data as $row) {
                    $value = $row[$db_field];
                    $datetime = date('d/m H:i', strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']));
                    $timestamp = strtotime($row['tgl_perawatan'] . ' ' . $row['jam_rawat']);
                    
                    if($value !== null && $value !== '' && floatval($value) > 0) {
                        $numValue = floatval($value);
                        
                        $param_data[] = [
                            'x' => $datetime,
                            'y' => $numValue,
                            'timestamp' => $timestamp,
                            'status' => 'normal',
                            'petugas' => $row['nama_petugas'],
                            'no_rawat' => $row['no_rawat']
                        ];
                        $all_timestamps[$timestamp] = $datetime;
                    }
                }
                
                if(!empty($param_data)) {
                    $unit = isset($chbp_units[$param]) ? ' ('.$chbp_units[$param].')' : '';
                    
                    $datasets[] = [
                        'key' => $param,
                        'label' => $param_labels[$param] . $unit,
                        'data' => $param_data
                    ];
                    
                    $lastVal = end($param_data);
                    $latest[$param] = $lastVal['y'];
                }
            }
        }
    }
}

// Cek apakah ada data
if(empty($datasets)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Tidak ada data valid untuk parameter yang dipilih'
    ]);
    exit();
}

// Pisahkan datasets untuk mode kategori
$datasets_ttv = [];
$datasets_cairan = [];
$datasets_ventilator = [];
$datasets_chbp = [];
$ttv_keys = ['sistolik', 'diastolik', 'nadi', 'suhu', 'respirasi', 'spo2', 'gcs'];
$cairan_keys = ['infus', 'tranfusi', 'minum', 'urine', 'drain', 'ngt', 'iwl', 'keseimbangan'];
$ventilator_keys = ['vt', 'rr_vent', 'peep', 'fio2'];
$chbp_keys = ['sistolik_chbp', 'diastolik_chbp', 'hr_chbp', 'suhu_chbp', 'djj'];

foreach($datasets as $ds) {
    if(in_array($ds['key'], $ttv_keys)) {
        $datasets_ttv[] = $ds;
    } else if(in_array($ds['key'], $cairan_keys)) {
        $datasets_cairan[] = $ds;
    } else if(in_array($ds['key'], $ventilator_keys)) {
        $datasets_ventilator[] = $ds;
    } else if(in_array($ds['key'], $chbp_keys)) {
        $datasets_chbp[] = $ds;
    }
}

// Sort all datasets by timestamp to ensure proper alignment
ksort($all_timestamps);

echo json_encode([
    'success' => true,
    'datasets' => $datasets,
    'datasets_ttv' => $datasets_ttv,
    'datasets_cairan' => $datasets_cairan,
    'datasets_ventilator' => $datasets_ventilator,
    'datasets_chbp' => $datasets_chbp,
    'filter_info' => $filter_norawat ?: 'Semua No. Rawat',
    'sumber_info' => $sumber_info,
    'rentang_info' => $rentang_label,
    'latest' => $latest,
    'diagnosa' => getDiagnosa($filter_norawat),
    'total_ttv' => isset($ttv_data) ? count($ttv_data) : 0,
    'total_cairan' => isset($cairan_data) ? count($cairan_data) : 0,
    'total_ventilator' => isset($vent_data) ? count($vent_data) : 0,
    'total_chbp' => isset($chbp_data) ? count($chbp_data) : 0,
    'debug_filter' => $debug_filter
]);

/**
 * Function untuk mengambil diagnosa pasien berdasarkan no_rawat
 */
function getDiagnosa($no_rawat) {
    if(empty($no_rawat)) {
        return [];
    }
    
    $query = bukaquery("
        SELECT 
            dp.kd_penyakit,
            dp.status,
            dp.prioritas,
            dp.status_penyakit,
            p.nm_penyakit
        FROM diagnosa_pasien dp
        LEFT JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
        WHERE dp.no_rawat = '$no_rawat'
        ORDER BY dp.prioritas ASC
    ");
    
    $diagnosa = [];
    while($row = mysqli_fetch_assoc($query)) {
        $diagnosa[] = [
            'kode' => $row['kd_penyakit'],
            'nama' => $row['nm_penyakit'] ?: $row['kd_penyakit'],
            'status' => $row['status'],
            'prioritas' => $row['prioritas'],
            'status_penyakit' => $row['status_penyakit']
        ];
    }
    
    return $diagnosa;
}
