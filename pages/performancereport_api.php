<?php
/**
 * performancereport_api.php - API Endpoint untuk Performance Report
 * Analytical Base Dataset Approach
 * 
 * KPI yang dihitung:
 * 1. Total Pasien (Rawat Jalan + Rawat Inap)
 * 2. Rata-rata Pasien/Hari
 * 3. Total Jasa Medis (dummy)
 * 4. Total Aktivitas (dummy)
 */

error_reporting(0);
ini_set('display_errors', 0);
ob_start();

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    require_once(__DIR__ . "/../conf/conf.php");
    
    ob_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    if (!isset($_SESSION['ses_dokter'])) {
        throw new Exception("Session expired");
    }
    
    if (!function_exists('encrypt_decrypt') || !function_exists('bukaquery')) {
        throw new Exception("Required functions not found");
    }
    
    $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    
    if (empty($kd_dokter)) {
        throw new Exception("Invalid doctor code");
    }
    
    // Get parameters
    $action = $_GET['action'] ?? $_POST['action'] ?? 'get_kpi';
    $period = $_GET['period'] ?? $_POST['period'] ?? 'month';
    $start_date = $_GET['start_date'] ?? $_POST['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? $_POST['end_date'] ?? null;
    
    // Calculate date range based on period
    $date_range = calculateDateRange($period, $start_date, $end_date);
    $current_start = $date_range['current_start'];
    $current_end = $date_range['current_end'];
    $previous_start = $date_range['previous_start'];
    $previous_end = $date_range['previous_end'];
    $total_days = $date_range['total_days'];
    
    switch ($action) {
        case 'get_kpi':
            $result = getKPIData($kd_dokter, $current_start, $current_end, $previous_start, $previous_end, $total_days);
            break;
        case 'get_doctor_info':
            $result = getDoctorInfo($kd_dokter);
            break;
        case 'get_trend_pasien':
            $result = getTrendPasien($kd_dokter, $current_start, $current_end);
            break;
        case 'get_lama_pelayanan':
            $result = getLamaPelayananPoli($kd_dokter, $current_start, $current_end);
            break;
        case 'get_top_diagnosa':
            $result = getTopDiagnosa($kd_dokter, $current_start, $current_end);
            break;
        case 'get_tren_rawat_inap':
            $result = getTrenRawatInap($kd_dokter, $current_start, $current_end, $period);
            break;
        case 'get_top_obat':
            $result = getTopObat($kd_dokter, $current_start, $current_end);
            break;
        case 'get_top_tindakan':
            $result = getTopTindakan($kd_dokter, $current_start, $current_end);
            break;
        case 'get_pendapatan_jasa':
            $result = getPendapatanJasa($kd_dokter, $current_start, $current_end);
            break;
        default:
            throw new Exception("Invalid action");
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $result,
        'period' => [
            'current' => ['start' => $current_start, 'end' => $current_end],
            'previous' => ['start' => $previous_start, 'end' => $previous_end],
            'total_days' => $total_days
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Calculate date range based on period filter
 */
function calculateDateRange($period, $start_date = null, $end_date = null) {
    $today = date('Y-m-d');
    
    switch ($period) {
        case 'today':
            $current_start = $today;
            $current_end = $today;
            $previous_start = date('Y-m-d', strtotime('-1 day'));
            $previous_end = date('Y-m-d', strtotime('-1 day'));
            $total_days = 1;
            break;
            
        case 'week':
            // Minggu ini (Senin - hari ini)
            $current_start = date('Y-m-d', strtotime('monday this week'));
            $current_end = $today;
            // Minggu lalu
            $previous_start = date('Y-m-d', strtotime('monday last week'));
            $previous_end = date('Y-m-d', strtotime('sunday last week'));
            $total_days = (strtotime($current_end) - strtotime($current_start)) / 86400 + 1;
            break;
            
        case 'month':
            // Bulan ini
            $current_start = date('Y-m-01');
            $current_end = $today;
            // Bulan lalu (full month)
            $previous_start = date('Y-m-01', strtotime('first day of last month'));
            $previous_end = date('Y-m-t', strtotime('last day of last month'));
            $total_days = (strtotime($current_end) - strtotime($current_start)) / 86400 + 1;
            break;
            
        case 'quarter':
            // 3 bulan terakhir
            $current_start = date('Y-m-d', strtotime('-3 months'));
            $current_end = $today;
            // 3 bulan sebelumnya
            $previous_start = date('Y-m-d', strtotime('-6 months'));
            $previous_end = date('Y-m-d', strtotime('-3 months -1 day'));
            $total_days = (strtotime($current_end) - strtotime($current_start)) / 86400 + 1;
            break;
            
        case 'year':
            // Tahun ini
            $current_start = date('Y-01-01');
            $current_end = $today;
            // Tahun lalu (same period)
            $previous_start = date('Y-01-01', strtotime('-1 year'));
            $previous_end = date('Y-m-d', strtotime('-1 year'));
            $total_days = (strtotime($current_end) - strtotime($current_start)) / 86400 + 1;
            break;
            
        case 'custom':
            if ($start_date && $end_date) {
                $current_start = $start_date;
                $current_end = $end_date;
                $diff_days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
                $previous_start = date('Y-m-d', strtotime($start_date . ' -' . $diff_days . ' days'));
                $previous_end = date('Y-m-d', strtotime($start_date . ' -1 day'));
                $total_days = $diff_days;
            } else {
                // Fallback ke bulan ini
                $current_start = date('Y-m-01');
                $current_end = $today;
                $previous_start = date('Y-m-01', strtotime('first day of last month'));
                $previous_end = date('Y-m-t', strtotime('last day of last month'));
                $total_days = (strtotime($current_end) - strtotime($current_start)) / 86400 + 1;
            }
            break;
            
        default:
            $current_start = date('Y-m-01');
            $current_end = $today;
            $previous_start = date('Y-m-01', strtotime('first day of last month'));
            $previous_end = date('Y-m-t', strtotime('last day of last month'));
            $total_days = (strtotime($current_end) - strtotime($current_start)) / 86400 + 1;
    }
    
    return [
        'current_start' => $current_start,
        'current_end' => $current_end,
        'previous_start' => $previous_start,
        'previous_end' => $previous_end,
        'total_days' => max(1, $total_days) // Minimal 1 hari
    ];
}

/**
 * Get KPI Data using Analytical Base Dataset approach
 * Query terpisah untuk Rawat Jalan dan Rawat Inap agar bisa ditampilkan rinciannya
 */
function getKPIData($kd_dokter, $current_start, $current_end, $previous_start, $previous_end, $total_days) {
    
    // Escape parameters untuk keamanan
    $kd_dokter_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $kd_dokter);
    $current_start_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $current_start);
    $current_end_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $current_end);
    $previous_start_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $previous_start);
    $previous_end_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $previous_end);
    
    // =========================================================
    // CURRENT PERIOD - RAWAT JALAN
    // =========================================================
    $sql_rajal_current = "
        SELECT COUNT(DISTINCT no_rawat) as total
        FROM reg_periksa
        WHERE kd_dokter = '$kd_dokter_safe'
            AND tgl_registrasi BETWEEN '$current_start_safe' AND '$current_end_safe'
    ";
    $result = bukaquery($sql_rajal_current);
    $row = mysqli_fetch_assoc($result);
    $rajal_current = (int)($row['total'] ?? 0);
    
    // =========================================================
    // CURRENT PERIOD - RAWAT INAP
    // =========================================================
    $sql_ranap_current = "
        SELECT COUNT(DISTINCT ki.no_rawat) as total
        FROM kamar_inap ki
        INNER JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
        WHERE dr.kd_dokter = '$kd_dokter_safe'
            AND DATE(ki.tgl_masuk) BETWEEN '$current_start_safe' AND '$current_end_safe'
    ";
    $result = bukaquery($sql_ranap_current);
    $row = mysqli_fetch_assoc($result);
    $ranap_current = (int)($row['total'] ?? 0);
    
    // Total pasien current
    $total_pasien_current = $rajal_current + $ranap_current;
    
    // =========================================================
    // PREVIOUS PERIOD - RAWAT JALAN
    // =========================================================
    $sql_rajal_previous = "
        SELECT COUNT(DISTINCT no_rawat) as total
        FROM reg_periksa
        WHERE kd_dokter = '$kd_dokter_safe'
            AND tgl_registrasi BETWEEN '$previous_start_safe' AND '$previous_end_safe'
    ";
    $result = bukaquery($sql_rajal_previous);
    $row = mysqli_fetch_assoc($result);
    $rajal_previous = (int)($row['total'] ?? 0);
    
    // =========================================================
    // PREVIOUS PERIOD - RAWAT INAP
    // =========================================================
    $sql_ranap_previous = "
        SELECT COUNT(DISTINCT ki.no_rawat) as total
        FROM kamar_inap ki
        INNER JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
        WHERE dr.kd_dokter = '$kd_dokter_safe'
            AND DATE(ki.tgl_masuk) BETWEEN '$previous_start_safe' AND '$previous_end_safe'
    ";
    $result = bukaquery($sql_ranap_previous);
    $row = mysqli_fetch_assoc($result);
    $ranap_previous = (int)($row['total'] ?? 0);
    
    // Total pasien previous
    $total_pasien_previous = $rajal_previous + $ranap_previous;
    
    // =========================================================
    // HITUNG KPI
    // =========================================================
    
    // 1. Total Pasien
    $pasien_percentage = calculatePercentageChange($total_pasien_current, $total_pasien_previous);
    
    // 2. Rata-rata Pasien/Hari
    // Gunakan total_days dari filter period (bulatkan karena pasien tidak mungkin desimal)
    $rata_rata_current = $total_days > 0 ? round($total_pasien_current / $total_days) : 0;
    $rata_rata_rajal_current = $total_days > 0 ? round($rajal_current / $total_days) : 0;
    $rata_rata_ranap_current = $total_days > 0 ? round($ranap_current / $total_days) : 0;
    
    // Untuk previous, hitung berdasarkan jumlah hari di periode sebelumnya
    $previous_days = (strtotime($previous_end) - strtotime($previous_start)) / 86400 + 1;
    $rata_rata_previous = $previous_days > 0 ? round($total_pasien_previous / $previous_days) : 0;
    
    $rata_rata_percentage = calculatePercentageChange($rata_rata_current, $rata_rata_previous);
    
    // =========================================================
    // 3. TOTAL OPERASI
    // =========================================================
    
    // Current period - Operasi (operator1, operator2, operator3, dokter_anak, dokter_anestesi, dokter_pjanak, dokter_umum)
    $sql_operasi_current = "
        SELECT COUNT(DISTINCT no_rawat) as total
        FROM operasi
        WHERE (operator1 = '$kd_dokter_safe' 
            OR operator2 = '$kd_dokter_safe' 
            OR operator3 = '$kd_dokter_safe'
            OR dokter_anak = '$kd_dokter_safe'
            OR dokter_anestesi = '$kd_dokter_safe'
            OR dokter_pjanak = '$kd_dokter_safe'
            OR dokter_umum = '$kd_dokter_safe')
            AND tgl_operasi BETWEEN '$current_start_safe' AND '$current_end_safe'
    ";
    $result = bukaquery($sql_operasi_current);
    $row = mysqli_fetch_assoc($result);
    $operasi_current = (int)($row['total'] ?? 0);
    
    // Previous period - Operasi
    $sql_operasi_previous = "
        SELECT COUNT(DISTINCT no_rawat) as total
        FROM operasi
        WHERE (operator1 = '$kd_dokter_safe' 
            OR operator2 = '$kd_dokter_safe' 
            OR operator3 = '$kd_dokter_safe'
            OR dokter_anak = '$kd_dokter_safe'
            OR dokter_anestesi = '$kd_dokter_safe'
            OR dokter_pjanak = '$kd_dokter_safe'
            OR dokter_umum = '$kd_dokter_safe')
            AND tgl_operasi BETWEEN '$previous_start_safe' AND '$previous_end_safe'
    ";
    $result = bukaquery($sql_operasi_previous);
    $row = mysqli_fetch_assoc($result);
    $operasi_previous = (int)($row['total'] ?? 0);
    
    $operasi_percentage = calculatePercentageChange($operasi_current, $operasi_previous);
    
    // =========================================================
    // 4. TOTAL PENUNJANG (LAB + RADIOLOGI)
    // =========================================================
    
    // Current period - Lab
    $sql_lab_current = "
        SELECT COUNT(DISTINCT no_rawat) as total
        FROM periksa_lab
        WHERE dokter_perujuk = '$kd_dokter_safe'
            AND tgl_periksa BETWEEN '$current_start_safe' AND '$current_end_safe'
    ";
    $result = bukaquery($sql_lab_current);
    $row = mysqli_fetch_assoc($result);
    $lab_current = (int)($row['total'] ?? 0);
    
    // Current period - Radiologi
    $sql_rad_current = "
        SELECT COUNT(DISTINCT no_rawat) as total
        FROM periksa_radiologi
        WHERE dokter_perujuk = '$kd_dokter_safe'
            AND tgl_periksa BETWEEN '$current_start_safe' AND '$current_end_safe'
    ";
    $result = bukaquery($sql_rad_current);
    $row = mysqli_fetch_assoc($result);
    $rad_current = (int)($row['total'] ?? 0);
    
    $penunjang_current = $lab_current + $rad_current;
    
    // Previous period - Lab
    $sql_lab_previous = "
        SELECT COUNT(DISTINCT no_rawat) as total
        FROM periksa_lab
        WHERE dokter_perujuk = '$kd_dokter_safe'
            AND tgl_periksa BETWEEN '$previous_start_safe' AND '$previous_end_safe'
    ";
    $result = bukaquery($sql_lab_previous);
    $row = mysqli_fetch_assoc($result);
    $lab_previous = (int)($row['total'] ?? 0);
    
    // Previous period - Radiologi
    $sql_rad_previous = "
        SELECT COUNT(DISTINCT no_rawat) as total
        FROM periksa_radiologi
        WHERE dokter_perujuk = '$kd_dokter_safe'
            AND tgl_periksa BETWEEN '$previous_start_safe' AND '$previous_end_safe'
    ";
    $result = bukaquery($sql_rad_previous);
    $row = mysqli_fetch_assoc($result);
    $rad_previous = (int)($row['total'] ?? 0);
    
    $penunjang_previous = $lab_previous + $rad_previous;
    
    $penunjang_percentage = calculatePercentageChange($penunjang_current, $penunjang_previous);
    
    return [
        'total_pasien' => [
            'current' => $total_pasien_current,
            'previous' => $total_pasien_previous,
            'percentage' => $pasien_percentage,
            'trend' => $pasien_percentage >= 0 ? 'up' : 'down',
            'rajal' => $rajal_current,
            'ranap' => $ranap_current
        ],
        'rata_rata_pasien' => [
            'current' => $rata_rata_current,
            'previous' => $rata_rata_previous,
            'percentage' => $rata_rata_percentage,
            'trend' => $rata_rata_percentage >= 0 ? 'up' : 'down',
            'rajal' => $rata_rata_rajal_current,
            'ranap' => $rata_rata_ranap_current
        ],
        'total_operasi' => [
            'current' => $operasi_current,
            'previous' => $operasi_previous,
            'percentage' => $operasi_percentage,
            'trend' => $operasi_percentage >= 0 ? 'up' : 'down'
        ],
        'total_penunjang' => [
            'current' => $penunjang_current,
            'previous' => $penunjang_previous,
            'percentage' => $penunjang_percentage,
            'trend' => $penunjang_percentage >= 0 ? 'up' : 'down',
            'lab' => $lab_current,
            'rad' => $rad_current
        ],
        'meta' => [
            'total_days' => $total_days
        ]
    ];
}

/**
 * Get Doctor Information
 */
function getDoctorInfo($kd_dokter) {
    $kd_dokter_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $kd_dokter);
    
    $sql = "SELECT d.nm_dokter, s.nm_sps 
            FROM dokter d 
            LEFT JOIN spesialis s ON d.kd_sps = s.kd_sps 
            WHERE d.kd_dokter = '$kd_dokter_safe'";
    
    $result = bukaquery($sql);
    $row = mysqli_fetch_assoc($result);
    
    if ($row) {
        $nama = $row['nm_dokter'];
        $spesialis = $row['nm_sps'] ?? 'Dokter Umum';
        
        // Generate initials
        $words = explode(' ', $nama);
        $initials = '';
        foreach ($words as $word) {
            if (strlen($word) > 0 && ctype_alpha($word[0])) {
                $initials .= strtoupper($word[0]);
            }
            if (strlen($initials) >= 2) break;
        }
        
        return [
            'nama' => $nama,
            'spesialis' => $spesialis,
            'initials' => $initials ?: 'DR'
        ];
    }
    
    return [
        'nama' => 'Dokter',
        'spesialis' => '-',
        'initials' => 'DR'
    ];
}

/**
 * Calculate percentage change
 */
function calculatePercentageChange($current, $previous) {
    if ($previous == 0) {
        return $current > 0 ? 100 : 0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

/**
 * Format Rupiah
 */
function formatRupiah($angka) {
    if ($angka >= 1000000000) {
        return round($angka / 1000000000, 1) . 'M';
    } elseif ($angka >= 1000000) {
        return round($angka / 1000000, 1) . 'Jt';
    } elseif ($angka >= 1000) {
        return round($angka / 1000, 1) . 'Rb';
    }
    return number_format($angka, 0, ',', '.');
}

/**
 * Get Trend Pasien Harian (IGD, Rawat Jalan, Rawat Inap)
 */
function getTrendPasien($kd_dokter, $start_date, $end_date) {
    $kd_dokter_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $kd_dokter);
    $start_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $start_date);
    $end_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $end_date);
    
    $trend_data = [];
    
    // Generate semua tanggal dalam range
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while ($current <= $end) {
        $tgl = date('Y-m-d', $current);
        $trend_data[$tgl] = [
            'tanggal' => $tgl,
            'igd' => 0,
            'rajal' => 0,
            'ranap' => 0
        ];
        $current = strtotime('+1 day', $current);
    }
    
    // =========================================================
    // 1. IGD - reg_periksa dengan kd_poli='IGDK' dan status_lanjut='Ralan'
    // =========================================================
    $sql_igd = "
        SELECT tgl_registrasi as tanggal, COUNT(DISTINCT no_rawat) as total
        FROM reg_periksa
        WHERE kd_dokter = '$kd_dokter_safe'
            AND kd_poli = 'IGDK'
            AND status_lanjut = 'Ralan'
            AND tgl_registrasi BETWEEN '$start_safe' AND '$end_safe'
        GROUP BY tgl_registrasi
        ORDER BY tgl_registrasi
    ";
    $result = bukaquery($sql_igd);
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($trend_data[$row['tanggal']])) {
            $trend_data[$row['tanggal']]['igd'] = (int)$row['total'];
        }
    }
    
    // =========================================================
    // 2. Rawat Jalan - reg_periksa dengan kd_poli != 'IGDK' dan status_lanjut='Ralan'
    // =========================================================
    $sql_rajal = "
        SELECT tgl_registrasi as tanggal, COUNT(DISTINCT no_rawat) as total
        FROM reg_periksa
        WHERE kd_dokter = '$kd_dokter_safe'
            AND kd_poli != 'IGDK'
            AND status_lanjut = 'Ralan'
            AND tgl_registrasi BETWEEN '$start_safe' AND '$end_safe'
        GROUP BY tgl_registrasi
        ORDER BY tgl_registrasi
    ";
    $result = bukaquery($sql_rajal);
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($trend_data[$row['tanggal']])) {
            $trend_data[$row['tanggal']]['rajal'] = (int)$row['total'];
        }
    }
    
    // =========================================================
    // 3. Rawat Inap - kamar_inap JOIN dpjp_ranap
    // =========================================================
    $sql_ranap = "
        SELECT DATE(ki.tgl_masuk) as tanggal, COUNT(DISTINCT ki.no_rawat) as total
        FROM kamar_inap ki
        INNER JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
        WHERE dr.kd_dokter = '$kd_dokter_safe'
            AND DATE(ki.tgl_masuk) BETWEEN '$start_safe' AND '$end_safe'
        GROUP BY DATE(ki.tgl_masuk)
        ORDER BY DATE(ki.tgl_masuk)
    ";
    $result = bukaquery($sql_ranap);
    while ($row = mysqli_fetch_assoc($result)) {
        if (isset($trend_data[$row['tanggal']])) {
            $trend_data[$row['tanggal']]['ranap'] = (int)$row['total'];
        }
    }
    
    // Convert to indexed array dan hitung total per hari
    $result_array = [];
    $total_igd = 0;
    $total_rajal = 0;
    $total_ranap = 0;
    
    foreach ($trend_data as $data) {
        $data['total'] = $data['igd'] + $data['rajal'] + $data['ranap'];
        $result_array[] = $data;
        
        $total_igd += $data['igd'];
        $total_rajal += $data['rajal'];
        $total_ranap += $data['ranap'];
    }
    
    return [
        'trend' => $result_array,
        'summary' => [
            'total_igd' => $total_igd,
            'total_rajal' => $total_rajal,
            'total_ranap' => $total_ranap,
            'grand_total' => $total_igd + $total_rajal + $total_ranap
        ]
    ];
}

/**
 * Get Lama Pelayanan Poli dari tabel mutasi_berkas
 * Menghitung selisih waktu antara kolom diterima dan kembali
 * Filter: kd_dokter dari reg_periksa, tanggal sesuai filter
 */
function getLamaPelayananPoli($kd_dokter, $start_date, $end_date) {
    $kd_dokter_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $kd_dokter);
    $start_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $start_date);
    $end_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $end_date);
    
    $trend_data = [];
    
    // Generate semua tanggal dalam range
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while ($current <= $end) {
        $tgl = date('Y-m-d', $current);
        $trend_data[$tgl] = [
            'tanggal' => $tgl,
            'rata_rata_menit' => 0,
            'total_pasien' => 0,
            'tercepat' => 0,
            'terlama' => 0
        ];
        $current = strtotime('+1 day', $current);
    }
    
    // Query lama pelayanan per hari
    // Hitung selisih jam antara diterima dan kembali
    // Filter: kembali tidak NULL dan tidak '0000-00-00 00:00:00'
    $sql = "
        SELECT 
            DATE(mb.diterima) as tanggal,
            COUNT(*) as total_pasien,
            AVG(TIMESTAMPDIFF(MINUTE, mb.diterima, mb.kembali)) as rata_rata_menit,
            MIN(TIMESTAMPDIFF(MINUTE, mb.diterima, mb.kembali)) as tercepat,
            MAX(TIMESTAMPDIFF(MINUTE, mb.diterima, mb.kembali)) as terlama
        FROM mutasi_berkas mb
        INNER JOIN reg_periksa rp ON mb.no_rawat = rp.no_rawat
        WHERE rp.kd_dokter = '$kd_dokter_safe'
            AND DATE(mb.diterima) BETWEEN '$start_safe' AND '$end_safe'
            AND mb.kembali IS NOT NULL
            AND mb.kembali != '0000-00-00 00:00:00'
            AND mb.kembali > mb.diterima
        GROUP BY DATE(mb.diterima)
        ORDER BY DATE(mb.diterima)
    ";
    
    $result = bukaquery($sql);
    
    $total_menit = 0;
    $total_pasien_all = 0;
    $all_tercepat = PHP_INT_MAX;
    $all_terlama = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $tgl = $row['tanggal'];
        if (isset($trend_data[$tgl])) {
            $rata_rata = round((float)$row['rata_rata_menit']);
            $tercepat = (int)$row['tercepat'];
            $terlama = (int)$row['terlama'];
            $pasien = (int)$row['total_pasien'];
            
            $trend_data[$tgl]['rata_rata_menit'] = $rata_rata;
            $trend_data[$tgl]['total_pasien'] = $pasien;
            $trend_data[$tgl]['tercepat'] = $tercepat;
            $trend_data[$tgl]['terlama'] = $terlama;
            
            // Akumulasi untuk summary
            $total_menit += $rata_rata * $pasien;
            $total_pasien_all += $pasien;
            
            if ($tercepat > 0 && $tercepat < $all_tercepat) {
                $all_tercepat = $tercepat;
            }
            if ($terlama > $all_terlama) {
                $all_terlama = $terlama;
            }
        }
    }
    
    // Hitung rata-rata keseluruhan
    $avg_keseluruhan = $total_pasien_all > 0 ? round($total_menit / $total_pasien_all) : 0;
    
    // Reset tercepat jika tidak ada data
    if ($all_tercepat == PHP_INT_MAX) {
        $all_tercepat = 0;
    }
    
    // Convert to indexed array
    $result_array = array_values($trend_data);
    
    return [
        'trend' => $result_array,
        'summary' => [
            'total_pasien' => $total_pasien_all,
            'rata_rata' => $avg_keseluruhan,
            'tercepat' => $all_tercepat,
            'terlama' => $all_terlama
        ]
    ];
}

/**
 * Get Top 5 Diagnosa dari tabel diagnosa_pasien
 * Terpisah antara Rajal dan Ranap dengan ranking masing-masing
 * 
 * Rajal: JOIN reg_periksa -> filter kd_dokter & tgl_registrasi, status='Ralan'
 * Ranap: JOIN dpjp_ranap -> filter kd_dokter, status='Ranap'
 */
function getTopDiagnosa($kd_dokter, $start_date, $end_date) {
    $kd_dokter_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $kd_dokter);
    $start_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $start_date);
    $end_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $end_date);
    
    // =========================================================
    // TOP 5 DIAGNOSA RAJAL (status = 'Ralan')
    // =========================================================
    $sql_rajal = "
        SELECT 
            dp.kd_penyakit,
            p.nm_penyakit,
            COUNT(*) as jumlah
        FROM diagnosa_pasien dp
        INNER JOIN reg_periksa rp ON dp.no_rawat = rp.no_rawat
        INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
        WHERE rp.kd_dokter = '$kd_dokter_safe'
            AND rp.tgl_registrasi BETWEEN '$start_safe' AND '$end_safe'
            AND dp.status = 'Ralan'
        GROUP BY dp.kd_penyakit, p.nm_penyakit
        ORDER BY jumlah DESC
        LIMIT 5
    ";
    
    $result_rajal = bukaquery($sql_rajal);
    $rajal_data = [];
    $rajal_max = 0;
    
    while ($row = mysqli_fetch_assoc($result_rajal)) {
        $jumlah = (int)$row['jumlah'];
        if ($jumlah > $rajal_max) $rajal_max = $jumlah;
        
        $rajal_data[] = [
            'kd_penyakit' => $row['kd_penyakit'],
            'nm_penyakit' => $row['nm_penyakit'],
            'jumlah' => $jumlah
        ];
    }
    
    // Tambahkan persentase untuk bar width
    foreach ($rajal_data as &$item) {
        $item['percent'] = $rajal_max > 0 ? round(($item['jumlah'] / $rajal_max) * 100) : 0;
    }
    
    // =========================================================
    // TOP 5 DIAGNOSA RANAP (status = 'Ranap')
    // =========================================================
    $sql_ranap = "
        SELECT 
            dp.kd_penyakit,
            p.nm_penyakit,
            COUNT(*) as jumlah
        FROM diagnosa_pasien dp
        INNER JOIN dpjp_ranap dr ON dp.no_rawat = dr.no_rawat
        INNER JOIN kamar_inap ki ON dp.no_rawat = ki.no_rawat
        INNER JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit
        WHERE dr.kd_dokter = '$kd_dokter_safe'
            AND DATE(ki.tgl_masuk) BETWEEN '$start_safe' AND '$end_safe'
            AND dp.status = 'Ranap'
        GROUP BY dp.kd_penyakit, p.nm_penyakit
        ORDER BY jumlah DESC
        LIMIT 5
    ";
    
    $result_ranap = bukaquery($sql_ranap);
    $ranap_data = [];
    $ranap_max = 0;
    
    while ($row = mysqli_fetch_assoc($result_ranap)) {
        $jumlah = (int)$row['jumlah'];
        if ($jumlah > $ranap_max) $ranap_max = $jumlah;
        
        $ranap_data[] = [
            'kd_penyakit' => $row['kd_penyakit'],
            'nm_penyakit' => $row['nm_penyakit'],
            'jumlah' => $jumlah
        ];
    }
    
    // Tambahkan persentase untuk bar width
    foreach ($ranap_data as &$item) {
        $item['percent'] = $ranap_max > 0 ? round(($item['jumlah'] / $ranap_max) * 100) : 0;
    }
    
    return [
        'rajal' => $rajal_data,
        'ranap' => $ranap_data
    ];
}

/**
 * Get Pendapatan Jasa Medis Dokter
 * 
 * Sumber data:
 * 1. Rawat Jalan: rawat_jl_dr.tarif_tindakandr + rawat_jl_drpr.tarif_tindakandr
 * 2. Rawat Inap: rawat_inap_dr.tarif_tindakandr + rawat_inap_drpr.tarif_tindakandr
 * 3. Operasi: biaya sesuai posisi dokter (operator1/2/3, dokter_anak, dokter_anestesi, dokter_pjanak, dokter_umum)
 */
function getPendapatanJasa($kd_dokter, $start_date, $end_date) {
    $kd_dokter_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $kd_dokter);
    $start_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $start_date);
    $end_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $end_date);
    
    // =========================================================
    // 1. RAWAT JALAN (rawat_jl_dr + rawat_jl_drpr)
    // =========================================================
    $sql_rajal = "
        SELECT COALESCE(SUM(total), 0) as total FROM (
            SELECT SUM(tarif_tindakandr) as total
            FROM rawat_jl_dr
            WHERE kd_dokter = '$kd_dokter_safe'
                AND tgl_perawatan BETWEEN '$start_safe' AND '$end_safe'
            
            UNION ALL
            
            SELECT SUM(tarif_tindakandr) as total
            FROM rawat_jl_drpr
            WHERE kd_dokter = '$kd_dokter_safe'
                AND tgl_perawatan BETWEEN '$start_safe' AND '$end_safe'
        ) as combined
    ";
    $result = bukaquery($sql_rajal);
    $row = mysqli_fetch_assoc($result);
    $jasa_rajal = (float)($row['total'] ?? 0);
    
    // =========================================================
    // 2. RAWAT INAP (rawat_inap_dr + rawat_inap_drpr)
    // =========================================================
    $sql_ranap = "
        SELECT COALESCE(SUM(total), 0) as total FROM (
            SELECT SUM(tarif_tindakandr) as total
            FROM rawat_inap_dr
            WHERE kd_dokter = '$kd_dokter_safe'
                AND tgl_perawatan BETWEEN '$start_safe' AND '$end_safe'
            
            UNION ALL
            
            SELECT SUM(tarif_tindakandr) as total
            FROM rawat_inap_drpr
            WHERE kd_dokter = '$kd_dokter_safe'
                AND tgl_perawatan BETWEEN '$start_safe' AND '$end_safe'
        ) as combined
    ";
    $result = bukaquery($sql_ranap);
    $row = mysqli_fetch_assoc($result);
    $jasa_ranap = (float)($row['total'] ?? 0);
    
    // =========================================================
    // 3. OPERASI - biaya sesuai posisi dokter
    // Dokter bisa dapat multiple biaya jika ada di beberapa posisi
    // =========================================================
    $sql_operasi = "
        SELECT 
            SUM(
                CASE WHEN operator1 = '$kd_dokter_safe' THEN biayaoperator1 ELSE 0 END +
                CASE WHEN operator2 = '$kd_dokter_safe' THEN biayaoperator2 ELSE 0 END +
                CASE WHEN operator3 = '$kd_dokter_safe' THEN biayaoperator3 ELSE 0 END +
                CASE WHEN dokter_anak = '$kd_dokter_safe' THEN biayadokter_anak ELSE 0 END +
                CASE WHEN dokter_anestesi = '$kd_dokter_safe' THEN biayadokter_anestesi ELSE 0 END +
                CASE WHEN dokter_pjanak = '$kd_dokter_safe' THEN biaya_dokter_pjanak ELSE 0 END +
                CASE WHEN dokter_umum = '$kd_dokter_safe' THEN biaya_dokter_umum ELSE 0 END
            ) as total
        FROM operasi
        WHERE (operator1 = '$kd_dokter_safe' 
            OR operator2 = '$kd_dokter_safe' 
            OR operator3 = '$kd_dokter_safe'
            OR dokter_anak = '$kd_dokter_safe'
            OR dokter_anestesi = '$kd_dokter_safe'
            OR dokter_pjanak = '$kd_dokter_safe'
            OR dokter_umum = '$kd_dokter_safe')
            AND tgl_operasi BETWEEN '$start_safe' AND '$end_safe'
    ";
    $result = bukaquery($sql_operasi);
    $row = mysqli_fetch_assoc($result);
    $jasa_operasi = (float)($row['total'] ?? 0);
    
    // =========================================================
    // HITUNG TOTAL DAN PERSENTASE
    // =========================================================
    $grand_total = $jasa_rajal + $jasa_ranap + $jasa_operasi;
    
    // Buat array breakdown (hanya yang > 0)
    $breakdown = [];
    
    if ($jasa_rajal > 0) {
        $breakdown[] = [
            'label' => 'Rawat Jalan',
            'value' => $jasa_rajal,
            'percent' => $grand_total > 0 ? round(($jasa_rajal / $grand_total) * 100) : 0,
            'color' => '#3b82f6' // blue
        ];
    }
    
    if ($jasa_ranap > 0) {
        $breakdown[] = [
            'label' => 'Rawat Inap',
            'value' => $jasa_ranap,
            'percent' => $grand_total > 0 ? round(($jasa_ranap / $grand_total) * 100) : 0,
            'color' => '#10b981' // green
        ];
    }
    
    if ($jasa_operasi > 0) {
        $breakdown[] = [
            'label' => 'Operasi',
            'value' => $jasa_operasi,
            'percent' => $grand_total > 0 ? round(($jasa_operasi / $grand_total) * 100) : 0,
            'color' => '#f59e0b' // amber
        ];
    }
    
    return [
        'grand_total' => $grand_total,
        'grand_total_formatted' => formatRupiahShort($grand_total),
        'breakdown' => $breakdown,
        'detail' => [
            'rajal' => $jasa_rajal,
            'ranap' => $jasa_ranap,
            'operasi' => $jasa_operasi
        ]
    ];
}

/**
 * Format Rupiah Short (untuk display)
 */
function formatRupiahShort($angka) {
    if ($angka >= 1000000000) {
        return 'Rp ' . number_format($angka / 1000000000, 1, ',', '.') . 'M';
    } elseif ($angka >= 1000000) {
        return 'Rp ' . number_format($angka / 1000000, 1, ',', '.') . 'Jt';
    } elseif ($angka >= 1000) {
        return 'Rp ' . number_format($angka / 1000, 1, ',', '.') . 'Rb';
    }
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Get Tren Rawat Inap per Bangsal
 * Line chart dengan multiple series (per bangsal)
 * 
 * 2 sumber data:
 * 1. DPJP Ranap: kamar_inap -> dpjp_ranap (kd_dokter) -> kamar -> bangsal
 * 2. Dokter IGD: kamar_inap -> reg_periksa (kd_dokter, kd_poli='IGDK', status_lanjut='Ranap') -> kamar -> bangsal
 * 
 * Filter: tgl_masuk dalam range, stts_pulang IN ('-', 'Pindah Kamar')
 */
function getTrenRawatInap($kd_dokter, $start_date, $end_date, $period = 'month') {
    $kd_dokter_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $kd_dokter);
    $start_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $start_date);
    $end_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $end_date);
    
    // Tentukan format group berdasarkan period
    // today/week/month -> per hari
    // quarter/year -> per bulan
    $group_by_month = in_array($period, ['quarter', 'year']);
    
    if ($group_by_month) {
        $date_format = '%Y-%m';
        $php_format = 'Y-m';
    } else {
        $date_format = '%Y-%m-%d';
        $php_format = 'Y-m-d';
    }
    
    // =========================================================
    // QUERY 1: DATA RAWAT INAP DARI DPJP RANAP
    // =========================================================
    $sql_dpjp = "
        SELECT 
            DATE_FORMAT(ki.tgl_masuk, '$date_format') as periode,
            b.nm_bangsal,
            ki.no_rawat,
            ki.stts_pulang
        FROM kamar_inap ki
        INNER JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
        INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
        INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
        WHERE dr.kd_dokter = '$kd_dokter_safe'
            AND DATE(ki.tgl_masuk) BETWEEN '$start_safe' AND '$end_safe'
            AND ki.stts_pulang IN ('-', 'Pindah Kamar')
    ";
    
    // =========================================================
    // QUERY 2: DATA RAWAT INAP DARI DOKTER IGD (status_lanjut='Ranap')
    // Dokter umum IGD yang pasiennya lanjut rawat inap
    // =========================================================
    $sql_igd = "
        SELECT 
            DATE_FORMAT(ki.tgl_masuk, '$date_format') as periode,
            b.nm_bangsal,
            ki.no_rawat,
            ki.stts_pulang
        FROM kamar_inap ki
        INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
        INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
        INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
        WHERE rp.kd_dokter = '$kd_dokter_safe'
            AND rp.kd_poli = 'IGDK'
            AND rp.status_lanjut = 'Ranap'
            AND DATE(ki.tgl_masuk) BETWEEN '$start_safe' AND '$end_safe'
            AND ki.stts_pulang IN ('-', 'Pindah Kamar')
    ";
    
    // =========================================================
    // GABUNGKAN DENGAN UNION (DISTINCT no_rawat)
    // =========================================================
    $sql = "
        SELECT 
            periode,
            nm_bangsal,
            COUNT(DISTINCT no_rawat) as jumlah,
            SUM(CASE WHEN stts_pulang = '-' THEN 1 ELSE 0 END) as masih_dirawat
        FROM (
            ($sql_dpjp)
            UNION
            ($sql_igd)
        ) as combined
        GROUP BY periode, nm_bangsal
        ORDER BY periode, nm_bangsal
    ";
    
    $result = bukaquery($sql);
    
    // Kumpulkan semua data
    $raw_data = [];
    $all_bangsal = [];
    $all_periods = [];
    $total_masih_dirawat = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $periode = $row['periode'];
        $bangsal = $row['nm_bangsal'];
        $jumlah = (int)$row['jumlah'];
        $masih = (int)$row['masih_dirawat'];
        
        if (!isset($raw_data[$periode])) {
            $raw_data[$periode] = [];
        }
        $raw_data[$periode][$bangsal] = $jumlah;
        
        if (!in_array($bangsal, $all_bangsal)) {
            $all_bangsal[] = $bangsal;
        }
        if (!in_array($periode, $all_periods)) {
            $all_periods[] = $periode;
        }
        
        $total_masih_dirawat += $masih;
    }
    
    // Generate semua periode dalam range
    $generated_periods = [];
    $current = strtotime($start_date);
    $end = strtotime($end_date);
    
    while ($current <= $end) {
        $p = date($php_format, $current);
        if (!in_array($p, $generated_periods)) {
            $generated_periods[] = $p;
        }
        if ($group_by_month) {
            $current = strtotime('+1 month', $current);
        } else {
            $current = strtotime('+1 day', $current);
        }
    }
    
    // Sort bangsal alphabetically
    sort($all_bangsal);
    
    // Buat datasets untuk chart
    $datasets = [];
    $bangsal_totals = [];
    $grand_total = 0;
    
    // Warna untuk setiap bangsal
    $colors = [
        '#3b82f6', // blue
        '#10b981', // green
        '#f59e0b', // amber
        '#ef4444', // red
        '#8b5cf6', // purple
        '#ec4899', // pink
        '#06b6d4', // cyan
        '#84cc16', // lime
        '#f97316', // orange
        '#6366f1', // indigo
    ];
    
    foreach ($all_bangsal as $idx => $bangsal) {
        $data_points = [];
        $bangsal_total = 0;
        
        foreach ($generated_periods as $periode) {
            $val = isset($raw_data[$periode][$bangsal]) ? $raw_data[$periode][$bangsal] : 0;
            $data_points[] = $val;
            $bangsal_total += $val;
        }
        
        $color = $colors[$idx % count($colors)];
        
        $datasets[] = [
            'label' => $bangsal,
            'data' => $data_points,
            'borderColor' => $color,
            'backgroundColor' => $color . '20', // 20 = 12% opacity
            'fill' => false,
            'tension' => 0.3,
            'pointRadius' => 4,
            'pointBackgroundColor' => $color,
            'borderWidth' => 2
        ];
        
        $bangsal_totals[$bangsal] = $bangsal_total;
        $grand_total += $bangsal_total;
    }
    
    // Cari bangsal terbanyak
    $bangsal_terbanyak = '-';
    $max_count = 0;
    foreach ($bangsal_totals as $bangsal => $total) {
        if ($total > $max_count) {
            $max_count = $total;
            $bangsal_terbanyak = $bangsal;
        }
    }
    
    // Format labels
    $labels = [];
    foreach ($generated_periods as $periode) {
        if ($group_by_month) {
            // Format: Jan, Feb, etc
            $labels[] = date('M', strtotime($periode . '-01'));
        } else {
            // Format: hanya tanggal
            $labels[] = date('j', strtotime($periode));
        }
    }
    
    return [
        'labels' => $labels,
        'datasets' => $datasets,
        'summary' => [
            'total_pasien' => $grand_total,
            'bangsal_terbanyak' => $bangsal_terbanyak,
            'bangsal_terbanyak_count' => $max_count,
            'masih_dirawat' => $total_masih_dirawat,
            'jumlah_bangsal' => count($all_bangsal)
        ],
        'bangsal_totals' => $bangsal_totals
    ];
}

/**
 * Get Top 5 Obat Terbanyak
 * Data dari detail_pemberian_obat yang sudah divalidasi apotek
 * JOIN ke resep_obat untuk filter kd_dokter
 * JOIN ke databarang untuk nama obat
 */
function getTopObat($kd_dokter, $start_date, $end_date) {
    $kd_dokter_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $kd_dokter);
    $start_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $start_date);
    $end_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $end_date);
    
    // Query Top 5 Obat berdasarkan total quantity
    // JOIN detail_pemberian_obat -> resep_obat (filter kd_dokter) -> databarang (nama obat)
    $sql = "
        SELECT 
            dpo.kode_brng,
            db.nama_brng,
            SUM(dpo.jml) as total_qty,
            COUNT(DISTINCT dpo.no_rawat) as jumlah_pasien
        FROM detail_pemberian_obat dpo
        INNER JOIN resep_obat ro ON dpo.no_rawat = ro.no_rawat 
            AND dpo.tgl_perawatan = ro.tgl_perawatan
        INNER JOIN databarang db ON dpo.kode_brng = db.kode_brng
        WHERE ro.kd_dokter = '$kd_dokter_safe'
            AND dpo.tgl_perawatan BETWEEN '$start_safe' AND '$end_safe'
            AND ro.status IN ('ralan', 'ranap')
        GROUP BY dpo.kode_brng, db.nama_brng
        ORDER BY total_qty DESC
        LIMIT 5
    ";
    
    $result = bukaquery($sql);
    $items = [];
    $grand_total = 0;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $total_qty = (int)$row['total_qty'];
        $grand_total += $total_qty;
        
        $items[] = [
            'kode_brng' => $row['kode_brng'],
            'nama_brng' => $row['nama_brng'],
            'total_qty' => $total_qty,
            'jumlah_pasien' => (int)$row['jumlah_pasien']
        ];
    }
    
    // Hitung persentase untuk setiap obat
    foreach ($items as &$item) {
        $item['percent'] = $grand_total > 0 ? round(($item['total_qty'] / $grand_total) * 100) : 0;
    }
    
    return [
        'items' => $items,
        'grand_total' => $grand_total
    ];
}

/**
 * Get Top 5 Tindakan (Rawat Jalan + Rawat Inap)
 * 
 * Rawat Jalan: rawat_jl_dr + rawat_jl_drpr -> jns_perawatan
 * Rawat Inap: rawat_inap_dr + rawat_inap_drpr -> jns_perawatan_inap
 */
function getTopTindakan($kd_dokter, $start_date, $end_date) {
    $kd_dokter_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $kd_dokter);
    $start_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $start_date);
    $end_safe = mysqli_real_escape_string($GLOBALS['db_conn'], $end_date);
    
    // =========================================================
    // TOP 5 TINDAKAN RAWAT JALAN
    // Gabungan: rawat_jl_dr + rawat_jl_drpr -> jns_perawatan
    // =========================================================
    $sql_rajal = "
        SELECT 
            kd_jenis_prw,
            nm_perawatan,
            SUM(jumlah) as jumlah
        FROM (
            -- Tindakan dokter (rawat_jl_dr)
            SELECT 
                rjd.kd_jenis_prw,
                jp.nm_perawatan,
                COUNT(*) as jumlah
            FROM rawat_jl_dr rjd
            INNER JOIN jns_perawatan jp ON rjd.kd_jenis_prw = jp.kd_jenis_prw
            WHERE rjd.kd_dokter = '$kd_dokter_safe'
                AND rjd.tgl_perawatan BETWEEN '$start_safe' AND '$end_safe'
            GROUP BY rjd.kd_jenis_prw, jp.nm_perawatan
            
            UNION ALL
            
            -- Tindakan dokter + perawat (rawat_jl_drpr)
            SELECT 
                rjdp.kd_jenis_prw,
                jp.nm_perawatan,
                COUNT(*) as jumlah
            FROM rawat_jl_drpr rjdp
            INNER JOIN jns_perawatan jp ON rjdp.kd_jenis_prw = jp.kd_jenis_prw
            WHERE rjdp.kd_dokter = '$kd_dokter_safe'
                AND rjdp.tgl_perawatan BETWEEN '$start_safe' AND '$end_safe'
            GROUP BY rjdp.kd_jenis_prw, jp.nm_perawatan
        ) as combined_rajal
        GROUP BY kd_jenis_prw, nm_perawatan
        ORDER BY jumlah DESC
        LIMIT 5
    ";
    
    $result_rajal = bukaquery($sql_rajal);
    $rajal_data = [];
    $rajal_max = 0;
    
    while ($row = mysqli_fetch_assoc($result_rajal)) {
        $jumlah = (int)$row['jumlah'];
        if ($jumlah > $rajal_max) $rajal_max = $jumlah;
        
        $rajal_data[] = [
            'kd_jenis_prw' => $row['kd_jenis_prw'],
            'nm_perawatan' => $row['nm_perawatan'],
            'jumlah' => $jumlah
        ];
    }
    
    // Tambahkan persentase untuk bar width
    foreach ($rajal_data as &$item) {
        $item['percent'] = $rajal_max > 0 ? round(($item['jumlah'] / $rajal_max) * 100) : 0;
    }
    
    // =========================================================
    // TOP 5 TINDAKAN RAWAT INAP
    // Gabungan: rawat_inap_dr + rawat_inap_drpr -> jns_perawatan_inap
    // =========================================================
    $sql_ranap = "
        SELECT 
            kd_jenis_prw,
            nm_perawatan,
            SUM(jumlah) as jumlah
        FROM (
            -- Tindakan dokter (rawat_inap_dr)
            SELECT 
                rid.kd_jenis_prw,
                jpi.nm_perawatan,
                COUNT(*) as jumlah
            FROM rawat_inap_dr rid
            INNER JOIN jns_perawatan_inap jpi ON rid.kd_jenis_prw = jpi.kd_jenis_prw
            WHERE rid.kd_dokter = '$kd_dokter_safe'
                AND rid.tgl_perawatan BETWEEN '$start_safe' AND '$end_safe'
            GROUP BY rid.kd_jenis_prw, jpi.nm_perawatan
            
            UNION ALL
            
            -- Tindakan dokter + perawat (rawat_inap_drpr)
            SELECT 
                ridp.kd_jenis_prw,
                jpi.nm_perawatan,
                COUNT(*) as jumlah
            FROM rawat_inap_drpr ridp
            INNER JOIN jns_perawatan_inap jpi ON ridp.kd_jenis_prw = jpi.kd_jenis_prw
            WHERE ridp.kd_dokter = '$kd_dokter_safe'
                AND ridp.tgl_perawatan BETWEEN '$start_safe' AND '$end_safe'
            GROUP BY ridp.kd_jenis_prw, jpi.nm_perawatan
        ) as combined_ranap
        GROUP BY kd_jenis_prw, nm_perawatan
        ORDER BY jumlah DESC
        LIMIT 5
    ";
    
    $result_ranap = bukaquery($sql_ranap);
    $ranap_data = [];
    $ranap_max = 0;
    
    while ($row = mysqli_fetch_assoc($result_ranap)) {
        $jumlah = (int)$row['jumlah'];
        if ($jumlah > $ranap_max) $ranap_max = $jumlah;
        
        $ranap_data[] = [
            'kd_jenis_prw' => $row['kd_jenis_prw'],
            'nm_perawatan' => $row['nm_perawatan'],
            'jumlah' => $jumlah
        ];
    }
    
    // Tambahkan persentase untuk bar width
    foreach ($ranap_data as &$item) {
        $item['percent'] = $ranap_max > 0 ? round(($item['jumlah'] / $ranap_max) * 100) : 0;
    }
    
    return [
        'rajal' => $rajal_data,
        'ranap' => $ranap_data
    ];
}
