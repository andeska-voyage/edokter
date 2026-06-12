<?php
/**
 * Activity Report
 * E-Dokter - Laporan Aktivitas Harian Dokter
 * 
 * Menggunakan Analytical Base Table (ABT) untuk performa optimal
 * Log Aktivitas: SOAP, E-Resep, Lab, Radiologi, Tindakan, Awal Medis (IGD, Ralan, Ranap, Neonatus, Kandungan), EKG
 */

// Handle AJAX requests
if (isset($_POST['action']) || isset($_GET['action'])) {
    // Suppress PHP errors in output
    error_reporting(0);
    ini_set('display_errors', 0);
    ob_start();
    
    try {
        session_start();
        
        $basePath = __DIR__ . '/../conf/';
        
        require_once $basePath . 'conf.php';
        
        // Clear any output buffer
        ob_clean();
        
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        if (!isset($_SESSION['ses_dokter'])) {
            echo json_encode(['success' => false, 'message' => 'Session expired']);
            exit;
        }
        
        $kd_dokter_encrypted = $_SESSION['ses_dokter'];
        $kd_dokter = encrypt_decrypt($kd_dokter_encrypted, 'd');
        $action = $_POST['action'] ?? $_GET['action'];
        
        switch ($action) {
            case 'getData':
                $result = getActivityData($kd_dokter);
                $result['debug'] = ['kd_dokter' => $kd_dokter, 'tanggal' => $_POST['tanggal'] ?? date('Y-m-d')];
                echo json_encode($result);
                break;
            case 'export':
                exportToExcel($kd_dokter);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Get Activity Data menggunakan Analytical Base Table (ABT)
 * Semua aktivitas dokter dalam 1 query UNION ALL
 */
function getActivityData($kd_dokter) {
    $conn = $GLOBALS['db_conn'];
    $tanggal = isset($_POST['tanggal']) ? mysqli_real_escape_string($conn, $_POST['tanggal']) : date('Y-m-d');
    
    // Initialize stats with defaults
    $stats = [
        'soap' => 0,
        'resep' => 0,
        'lab' => 0,
        'radiologi' => 0,
        'tindakan' => 0,
        'awal_medis' => 0,
        'ekg' => 0,
        'usg' => 0,
        'total' => 0
    ];
    
    $summary = [
        'soap_ralan' => 0,
        'soap_ranap' => 0,
        'resep' => 0,
        'lab' => 0,
        'radiologi' => 0,
        'tindakan_ralan' => 0,
        'tindakan_ranap' => 0,
        'awal_medis_igd' => 0,
        'awal_medis_ralan' => 0,
        'awal_medis_ranap' => 0,
        'awal_medis_neonatus' => 0,
        'awal_medis_kandungan' => 0,
        'ekg' => 0,
        'usg' => 0
    ];
    
    $activities = [];
    $hourlyData = [];
    
    // Helper function untuk cek tabel dan execute query
    $safeQuery = function($sql) use ($conn) {
        $result = @mysqli_query($conn, $sql);
        return $result;
    };
    
    // Helper untuk count single table
    $getCount = function($table, $where) use ($conn, $safeQuery) {
        $sql = "SELECT COUNT(*) as cnt FROM $table WHERE $where";
        $result = $safeQuery($sql);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return (int)($row['cnt'] ?? 0);
        }
        return 0;
    };
    
    // ✅ Helper untuk cek apakah tabel exist di database
    $tableExists = function($tableName) use ($conn) {
        $dbName = $GLOBALS['db_name'] ?? '';
        if (empty($dbName)) {
            // Coba ambil dari koneksi
            $result = @mysqli_query($conn, "SELECT DATABASE()");
            if ($result) {
                $row = mysqli_fetch_row($result);
                $dbName = $row[0] ?? '';
            }
        }
        
        $sql = "SELECT COUNT(*) as cnt FROM information_schema.tables 
                WHERE table_schema = '$dbName' AND table_name = '$tableName'";
        $result = @mysqli_query($conn, $sql);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return ((int)($row['cnt'] ?? 0)) > 0;
        }
        return false;
    };
    
    // ✅ Daftar tabel opsional yang mungkin tidak ada di semua instalasi
    $optionalTables = [
        'penilaian_medis_ranap_neonatus',
        'penilaian_medis_ranap_kandungan', 
        'hasil_pemeriksaan_usg',
        'hasil_pemeriksaan_ekg'
    ];
    
    // ✅ Cek tabel mana yang exist
    $existingTables = [];
    foreach ($optionalTables as $tbl) {
        $existingTables[$tbl] = $tableExists($tbl);
    }
    
    // =============================================
    // ABT Query - UNION ALL untuk SEMUA log aktivitas
    // =============================================
    $sqlABT = "
        -- 1. SOAP RAWAT JALAN (pemeriksaan_ralan)
        SELECT 
            'soap_ralan' as source,
            pr.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            pr.jam_rawat as jam,
            'soap' as jenis,
            'SOAP Ralan' as jenis_label,
            pol.nm_poli as lokasi,
            'Pemeriksaan SOAP' as tindakan
        FROM pemeriksaan_ralan pr
        INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
        WHERE pr.nip = '$kd_dokter'
        AND pr.tgl_perawatan = '$tanggal'
        
        UNION ALL
        
        -- 2. SOAP RAWAT INAP (pemeriksaan_ranap)
        SELECT 
            'soap_ranap' as source,
            pr.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            pr.jam_rawat as jam,
            'soap' as jenis,
            'SOAP Ranap' as jenis_label,
            CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, '')) as lokasi,
            'Pemeriksaan SOAP' as tindakan
        FROM pemeriksaan_ranap pr
        INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE pr.nip = '$kd_dokter'
        AND pr.tgl_perawatan = '$tanggal'
        
        UNION ALL
        
        -- 3. E-RESEP RAWAT JALAN (resep_obat status=ralan)
        SELECT 
            'resep_ralan' as source,
            ro.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            ro.jam_peresepan as jam,
            'resep' as jenis,
            'E-Resep Ralan' as jenis_label,
            pol.nm_poli as lokasi,
            'Penulisan Resep' as tindakan
        FROM resep_obat ro
        INNER JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
        WHERE ro.kd_dokter = '$kd_dokter'
        AND ro.tgl_peresepan = '$tanggal'
        AND ro.status = 'ralan'
        
        UNION ALL
        
        -- 4. E-RESEP RAWAT INAP (resep_obat status=ranap)
        SELECT 
            'resep_ranap' as source,
            ro.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            ro.jam_peresepan as jam,
            'resep' as jenis,
            'E-Resep Ranap' as jenis_label,
            CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, '')) as lokasi,
            'Penulisan Resep' as tindakan
        FROM resep_obat ro
        INNER JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE ro.kd_dokter = '$kd_dokter'
        AND ro.tgl_peresepan = '$tanggal'
        AND ro.status = 'ranap'
        
        UNION ALL
        
        -- 5. LABORATORIUM RALAN (permintaan_lab status=ralan)
        SELECT 
            'lab_ralan' as source,
            pl.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            pl.jam_permintaan as jam,
            'lab' as jenis,
            'Lab Ralan' as jenis_label,
            pol.nm_poli as lokasi,
            'Permintaan Lab' as tindakan
        FROM permintaan_lab pl
        INNER JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
        WHERE pl.dokter_perujuk = '$kd_dokter'
        AND pl.tgl_permintaan = '$tanggal'
        AND pl.status = 'ralan'
        
        UNION ALL
        
        -- 6. LABORATORIUM RANAP (permintaan_lab status=ranap)
        SELECT 
            'lab_ranap' as source,
            pl.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            pl.jam_permintaan as jam,
            'lab' as jenis,
            'Lab Ranap' as jenis_label,
            CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, '')) as lokasi,
            'Permintaan Lab' as tindakan
        FROM permintaan_lab pl
        INNER JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE pl.dokter_perujuk = '$kd_dokter'
        AND pl.tgl_permintaan = '$tanggal'
        AND pl.status = 'ranap'
        
        UNION ALL
        
        -- 7. RADIOLOGI RALAN (permintaan_radiologi status=ralan)
        SELECT 
            'rad_ralan' as source,
            pr.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            pr.jam_permintaan as jam,
            'radiologi' as jenis,
            'Radiologi Ralan' as jenis_label,
            pol.nm_poli as lokasi,
            'Permintaan Radiologi' as tindakan
        FROM permintaan_radiologi pr
        INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
        WHERE pr.dokter_perujuk = '$kd_dokter'
        AND pr.tgl_permintaan = '$tanggal'
        AND pr.status = 'ralan'
        
        UNION ALL
        
        -- 8. RADIOLOGI RANAP (permintaan_radiologi status=ranap)
        SELECT 
            'rad_ranap' as source,
            pr.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            pr.jam_permintaan as jam,
            'radiologi' as jenis,
            'Radiologi Ranap' as jenis_label,
            CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, '')) as lokasi,
            'Permintaan Radiologi' as tindakan
        FROM permintaan_radiologi pr
        INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE pr.dokter_perujuk = '$kd_dokter'
        AND pr.tgl_permintaan = '$tanggal'
        AND pr.status = 'ranap'
        
        UNION ALL
        
        -- 9. TINDAKAN RAWAT JALAN (rawat_jl_dr)
        SELECT 
            'tindakan_ralan' as source,
            rj.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            rj.jam_rawat as jam,
            'tindakan' as jenis,
            'Tindakan Ralan' as jenis_label,
            pol.nm_poli as lokasi,
            'Tindakan Medis' as tindakan
        FROM rawat_jl_dr rj
        INNER JOIN reg_periksa rp ON rj.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
        WHERE rj.kd_dokter = '$kd_dokter'
        AND rj.tgl_perawatan = '$tanggal'
        
        UNION ALL
        
        -- 10. TINDAKAN RAWAT INAP (rawat_inap_dr)
        SELECT 
            'tindakan_ranap' as source,
            ri.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            ri.jam_rawat as jam,
            'tindakan' as jenis,
            'Tindakan Ranap' as jenis_label,
            CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, '')) as lokasi,
            'Tindakan Medis' as tindakan
        FROM rawat_inap_dr ri
        INNER JOIN reg_periksa rp ON ri.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE ri.kd_dokter = '$kd_dokter'
        AND ri.tgl_perawatan = '$tanggal'
        
        UNION ALL
        
        -- 11. AWAL MEDIS IGD (penilaian_medis_igd)
        SELECT 
            'awal_medis_igd' as source,
            pm.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            TIME(pm.tanggal) as jam,
            'awal_medis' as jenis,
            'Awal Medis IGD' as jenis_label,
            'Unit IGD' as lokasi,
            'Penilaian Awal Medis' as tindakan
        FROM penilaian_medis_igd pm
        INNER JOIN reg_periksa rp ON pm.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        WHERE pm.kd_dokter = '$kd_dokter'
        AND DATE(pm.tanggal) = '$tanggal'
        
        UNION ALL
        
        -- 12. AWAL MEDIS UMUM RALAN (penilaian_medis_ralan)
        SELECT 
            'awal_medis_ralan' as source,
            pm.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            TIME(pm.tanggal) as jam,
            'awal_medis' as jenis,
            'Awal Medis Ralan' as jenis_label,
            pol.nm_poli as lokasi,
            'Penilaian Awal Medis' as tindakan
        FROM penilaian_medis_ralan pm
        INNER JOIN reg_periksa rp ON pm.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
        WHERE pm.kd_dokter = '$kd_dokter'
        AND DATE(pm.tanggal) = '$tanggal'
        
        UNION ALL
        
        -- 13. AWAL MEDIS UMUM RANAP (penilaian_medis_ranap)
        SELECT 
            'awal_medis_ranap' as source,
            pm.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            TIME(pm.tanggal) as jam,
            'awal_medis' as jenis,
            'Awal Medis Ranap' as jenis_label,
            CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, '')) as lokasi,
            'Penilaian Awal Medis' as tindakan
        FROM penilaian_medis_ranap pm
        INNER JOIN reg_periksa rp ON pm.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE pm.kd_dokter = '$kd_dokter'
        AND DATE(pm.tanggal) = '$tanggal'
        
        " . ($existingTables['hasil_pemeriksaan_ekg'] ? "
        UNION ALL
        
        -- 14. EKG (hasil_pemeriksaan_ekg)
        SELECT 
            'ekg' as source,
            pm.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            TIME(pm.tanggal) as jam,
            'ekg' as jenis,
            'EKG' as jenis_label,
            CASE 
                WHEN rp.status_lanjut = 'Ralan' THEN pol.nm_poli
                ELSE CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, ''))
            END as lokasi,
            'Pemeriksaan EKG' as tindakan
        FROM hasil_pemeriksaan_ekg pm
        INNER JOIN reg_periksa rp ON pm.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE pm.kd_dokter = '$kd_dokter'
        AND DATE(pm.tanggal) = '$tanggal'
        " : "") . "
        
        " . ($existingTables['penilaian_medis_ranap_neonatus'] ? "
        UNION ALL
        
        -- 15. AWAL MEDIS NEONATUS RANAP (penilaian_medis_ranap_neonatus)
        SELECT 
            'awal_medis_neonatus' as source,
            pm.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            TIME(pm.tanggal) as jam,
            'awal_medis' as jenis,
            'Awal Medis Neonatus' as jenis_label,
            CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, '')) as lokasi,
            'Penilaian Awal Medis Neonatus' as tindakan
        FROM penilaian_medis_ranap_neonatus pm
        INNER JOIN reg_periksa rp ON pm.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE pm.kd_dokter = '$kd_dokter'
        AND DATE(pm.tanggal) = '$tanggal'
        " : "") . "
        
        " . ($existingTables['penilaian_medis_ranap_kandungan'] ? "UNION ALL
        
        -- 16. AWAL MEDIS KEBIDANAN & KANDUNGAN (penilaian_medis_ranap_kandungan)
        SELECT 
            'awal_medis_kandungan' as source,
            pm.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            TIME(pm.tanggal) as jam,
            'awal_medis' as jenis,
            'Awal Medis Kandungan' as jenis_label,
            CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, '')) as lokasi,
            'Penilaian Awal Medis Kebidanan' as tindakan
        FROM penilaian_medis_ranap_kandungan pm
        INNER JOIN reg_periksa rp ON pm.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE pm.kd_dokter = '$kd_dokter'
        AND DATE(pm.tanggal) = '$tanggal'
        " : "") . "
        
        " . ($existingTables['hasil_pemeriksaan_usg'] ? "UNION ALL
        
        -- 17. USG KANDUNGAN (hasil_pemeriksaan_usg)
        SELECT 
            'usg_kandungan' as source,
            usg.no_rawat,
            rp.no_rkm_medis,
            p.nm_pasien,
            TIME(usg.tanggal) as jam,
            'usg' as jenis,
            'USG Kandungan' as jenis_label,
            CASE 
                WHEN rp.status_lanjut = 'Ralan' THEN pol.nm_poli
                ELSE CONCAT(COALESCE(bg.nm_bangsal, ''), ' - ', COALESCE(kb.kd_kamar, ''))
            END as lokasi,
            'Pemeriksaan USG Kandungan' as tindakan
        FROM hasil_pemeriksaan_usg usg
        INNER JOIN reg_periksa rp ON usg.no_rawat = rp.no_rawat
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
        LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat
        LEFT JOIN kamar kb ON ki.kd_kamar = kb.kd_kamar
        LEFT JOIN bangsal bg ON kb.kd_bangsal = bg.kd_bangsal
        WHERE usg.kd_dokter = '$kd_dokter'
        AND DATE(usg.tanggal) = '$tanggal'
        " : "") . "
        
        ORDER BY jam DESC
    ";
    
    // =============================================
    // Stats Query - Individual queries untuk safety
    // =============================================
    
    // SOAP Ralan
    $summary['soap_ralan'] = $getCount('pemeriksaan_ralan', "nip = '$kd_dokter' AND tgl_perawatan = '$tanggal'");
    
    // SOAP Ranap
    $summary['soap_ranap'] = $getCount('pemeriksaan_ranap', "nip = '$kd_dokter' AND tgl_perawatan = '$tanggal'");
    
    // E-Resep
    $resResult = $safeQuery("SELECT COUNT(DISTINCT no_resep) as cnt FROM resep_obat WHERE kd_dokter = '$kd_dokter' AND tgl_peresepan = '$tanggal'");
    $summary['resep'] = $resResult ? (int)(mysqli_fetch_assoc($resResult)['cnt'] ?? 0) : 0;
    
    // Lab
    $summary['lab'] = $getCount('permintaan_lab', "dokter_perujuk = '$kd_dokter' AND tgl_permintaan = '$tanggal'");
    
    // Radiologi
    $summary['radiologi'] = $getCount('permintaan_radiologi', "dokter_perujuk = '$kd_dokter' AND tgl_permintaan = '$tanggal'");
    
    // Tindakan Ralan
    $summary['tindakan_ralan'] = $getCount('rawat_jl_dr', "kd_dokter = '$kd_dokter' AND tgl_perawatan = '$tanggal'");
    
    // Tindakan Ranap
    $summary['tindakan_ranap'] = $getCount('rawat_inap_dr', "kd_dokter = '$kd_dokter' AND tgl_perawatan = '$tanggal'");
    
    // Awal Medis IGD
    $summary['awal_medis_igd'] = $getCount('penilaian_medis_igd', "kd_dokter = '$kd_dokter' AND DATE(tanggal) = '$tanggal'");
    
    // Awal Medis Ralan
    $summary['awal_medis_ralan'] = $getCount('penilaian_medis_ralan', "kd_dokter = '$kd_dokter' AND DATE(tanggal) = '$tanggal'");
    
    // Awal Medis Ranap
    $summary['awal_medis_ranap'] = $getCount('penilaian_medis_ranap', "kd_dokter = '$kd_dokter' AND DATE(tanggal) = '$tanggal'");
    
    // Awal Medis Neonatus (cek tabel exist dulu)
    $summary['awal_medis_neonatus'] = $existingTables['penilaian_medis_ranap_neonatus'] 
        ? $getCount('penilaian_medis_ranap_neonatus', "kd_dokter = '$kd_dokter' AND DATE(tanggal) = '$tanggal'") 
        : 0;
    
    // Awal Medis Kandungan (cek tabel exist dulu)
    $summary['awal_medis_kandungan'] = $existingTables['penilaian_medis_ranap_kandungan'] 
        ? $getCount('penilaian_medis_ranap_kandungan', "kd_dokter = '$kd_dokter' AND DATE(tanggal) = '$tanggal'") 
        : 0;
    
    // EKG (cek tabel exist dulu)
    $summary['ekg'] = $existingTables['hasil_pemeriksaan_ekg'] 
        ? $getCount('hasil_pemeriksaan_ekg', "kd_dokter = '$kd_dokter' AND DATE(tanggal) = '$tanggal'") 
        : 0;
    
    // USG (cek tabel exist dulu)
    $summary['usg'] = $existingTables['hasil_pemeriksaan_usg'] 
        ? $getCount('hasil_pemeriksaan_usg', "kd_dokter = '$kd_dokter' AND DATE(tanggal) = '$tanggal'") 
        : 0;
    
    // Calculate totals
    $soapTotal = $summary['soap_ralan'] + $summary['soap_ranap'];
    $tindakanTotal = $summary['tindakan_ralan'] + $summary['tindakan_ranap'];
    $awalMedisTotal = $summary['awal_medis_igd'] + $summary['awal_medis_ralan'] + $summary['awal_medis_ranap'] 
                    + $summary['awal_medis_neonatus'] + $summary['awal_medis_kandungan'];
    
    $stats = [
        'soap' => $soapTotal,
        'resep' => $summary['resep'],
        'lab' => $summary['lab'],
        'radiologi' => $summary['radiologi'],
        'tindakan' => $tindakanTotal,
        'awal_medis' => $awalMedisTotal,
        'ekg' => $summary['ekg'],
        'usg' => $summary['usg'],
        'total' => $soapTotal + $summary['resep'] + $summary['lab'] + $summary['radiologi'] 
                 + $tindakanTotal + $awalMedisTotal + $summary['ekg'] + $summary['usg']
    ];
    
    // Execute ABT Query (gunakan safe query)
    $resultABT = $safeQuery($sqlABT);
    
    if ($resultABT) {
        while ($row = mysqli_fetch_assoc($resultABT)) {
            $jam = substr($row['jam'] ?? '00:00', 0, 5);
            $hour = (int)substr($row['jam'] ?? '00', 0, 2);
            
            $activities[] = [
                'source' => $row['source'],
                'no_rawat' => $row['no_rawat'],
                'no_rkm_medis' => $row['no_rkm_medis'],
                'nm_pasien' => $row['nm_pasien'],
                'jam' => $jam,
                'hour' => $hour,
                'jenis' => $row['jenis'],
                'jenis_label' => $row['jenis_label'],
                'lokasi' => $row['lokasi'] ?? '-',
                'tindakan' => $row['tindakan']
            ];
            
            if (!isset($hourlyData[$hour])) $hourlyData[$hour] = 0;
            $hourlyData[$hour]++;
        }
    }
    
    // Prepare hourly chart
    $hourlyChart = [];
    ksort($hourlyData);
    foreach ($hourlyData as $hour => $count) {
        $hourlyChart[] = ['hour' => str_pad($hour, 2, '0', STR_PAD_LEFT), 'count' => $count];
    }
    
    $timeline = array_slice($activities, 0, 5);
    
    return [
        'success' => true,
        'tanggal' => $tanggal,
        'stats' => $stats,
        'activities' => $activities,
        'summary' => $summary,
        'hourly' => $hourlyChart,
        'timeline' => $timeline
    ];
}

/**
 * Export to Excel
 */
function exportToExcel($kd_dokter) {
    $conn = bukakoneksi();
    $tanggal = isset($_GET['tanggal']) ? mysqli_real_escape_string($conn, $_GET['tanggal']) : date('Y-m-d');
    
    $sqlDokter = "SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter'";
    $resultDokter = mysqli_query($conn, $sqlDokter);
    $rowDokter = mysqli_fetch_assoc($resultDokter);
    $nm_dokter = $rowDokter['nm_dokter'] ?? 'Dokter';
    
    $_POST['tanggal'] = $tanggal;
    $data = getActivityData($kd_dokter);
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="Activity_Report_' . $tanggal . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>';
    echo '<h2>LAPORAN AKTIVITAS DOKTER</h2>';
    echo '<p>Dokter: ' . htmlspecialchars($nm_dokter) . '</p>';
    echo '<p>Tanggal: ' . date('d/m/Y', strtotime($tanggal)) . '</p><br>';
    
    echo '<h3>RINGKASAN</h3><table border="1">';
    echo '<tr><th>Keterangan</th><th>Jumlah</th></tr>';
    echo '<tr><td>Total Aktivitas</td><td>' . $data['stats']['total'] . '</td></tr>';
    echo '<tr><td>SOAP</td><td>' . $data['stats']['soap'] . '</td></tr>';
    echo '<tr><td>E-Resep</td><td>' . $data['stats']['resep'] . '</td></tr>';
    echo '<tr><td>Laboratorium</td><td>' . $data['stats']['lab'] . '</td></tr>';
    echo '<tr><td>Radiologi</td><td>' . $data['stats']['radiologi'] . '</td></tr>';
    echo '<tr><td>Tindakan</td><td>' . $data['stats']['tindakan'] . '</td></tr>';
    echo '<tr><td>Awal Medis</td><td>' . $data['stats']['awal_medis'] . '</td></tr>';
    echo '<tr><td>EKG</td><td>' . $data['stats']['ekg'] . '</td></tr>';
    echo '<tr><td>USG Kandungan</td><td>' . $data['stats']['usg'] . '</td></tr>';
    echo '</table><br>';
    
    echo '<h3>DETAIL AKTIVITAS</h3><table border="1">';
    echo '<tr><th>Jam</th><th>No. RM</th><th>Nama Pasien</th><th>Jenis Aktivitas</th><th>Lokasi</th><th>Keterangan</th></tr>';
    
    foreach ($data['activities'] as $act) {
        echo '<tr><td>' . htmlspecialchars($act['jam']) . '</td><td>' . htmlspecialchars($act['no_rkm_medis']) . '</td><td>' . htmlspecialchars($act['nm_pasien']) . '</td><td>' . htmlspecialchars($act['jenis_label']) . '</td><td>' . htmlspecialchars($act['lokasi']) . '</td><td>' . htmlspecialchars($act['tindakan']) . '</td></tr>';
    }
    
    echo '</table></body></html>';
    exit;
}
?>

<link rel="stylesheet" href="css/activityreport.css">

<div class="activity-container">
    <div class="activity-header">
        <div class="activity-title">
            <i class="fas fa-chart-line"></i>
            <div>
                <h1>Activity Report</h1>
                <p>Laporan Aktivitas Harian Dokter</p>
            </div>
        </div>

        <div class="activity-filter">
            <div class="filter-group">
                <label><i class="fas fa-calendar-day"></i></label>
                <input type="date" id="tanggal" value="<?= date('Y-m-d') ?>">
            </div>
            <button class="btn-filter" id="btnFilter">
                <i class="fas fa-search"></i> Tampilkan
            </button>
            <button class="btn-export" id="btnExport">
                <i class="fas fa-file-excel"></i> Export
            </button>
        </div>

        <div class="doctor-badge">
            <?php
            $kd_dokter_encrypted = $_SESSION['ses_dokter'] ?? '';
            $kd_dokter = '';
            $nm_dokter = 'Dokter';
            $spesialis = '';
            
            if ($kd_dokter_encrypted) {
                $kd_dokter = encrypt_decrypt($kd_dokter_encrypted, 'd');
            }
            
            if ($kd_dokter) {
                $conn = bukakoneksi();
                $sqlDoc = "SELECT d.nm_dokter, d.kd_sps, s.nm_sps FROM dokter d LEFT JOIN spesialis s ON d.kd_sps = s.kd_sps WHERE d.kd_dokter = '$kd_dokter'";
                $resDoc = mysqli_query($conn, $sqlDoc);
                if ($resDoc && $rowDoc = mysqli_fetch_assoc($resDoc)) {
                    $nm_dokter = $rowDoc['nm_dokter'];
                    $spesialis = $rowDoc['nm_sps'] ?? 'Dokter Umum';
                }
            }
            
            $words = explode(' ', $nm_dokter);
            $initials = count($words) >= 2 ? strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1)) : strtoupper(substr($nm_dokter, 0, 2));
            ?>
            <div class="doctor-avatar"><?= $initials ?></div>
            <div class="doctor-info-text">
                <div class="name"><?= htmlspecialchars($nm_dokter) ?></div>
                <div class="role"><?= htmlspecialchars($spesialis) ?></div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-info"><h3 id="statTotal">0</h3><p>Total Aktivitas</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-notes-medical"></i></div>
            <div class="stat-info"><h3 id="statSoap">0</h3><p>SOAP</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-prescription"></i></div>
            <div class="stat-info"><h3 id="statResep">0</h3><p>E-Resep</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-flask"></i></div>
            <div class="stat-info"><h3 id="statLab">0</h3><p>Laboratorium</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon pink"><i class="fas fa-x-ray"></i></div>
            <div class="stat-info"><h3 id="statRadiologi">0</h3><p>Radiologi</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-syringe"></i></div>
            <div class="stat-info"><h3 id="statTindakan">0</h3><p>Tindakan</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-heartbeat"></i></div>
            <div class="stat-info"><h3 id="statEkg">0</h3><p>EKG</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon cyan"><i class="fas fa-baby"></i></div>
            <div class="stat-info"><h3 id="statUsg">0</h3><p>USG Kandungan</p></div>
        </div>
    </div>

    <div class="content-grid">
        <div class="activity-section">
            <div class="section-header">
                <h2><i class="fas fa-list-alt"></i> Log Aktivitas</h2>
                <span class="badge-count" id="badgeCount">0 Aktivitas</span>
            </div>
            <div class="table-container">
                <table class="activity-table">
                    <thead>
                        <tr><th width="70">Jam</th><th>Pasien</th><th>Jenis Aktivitas</th><th>Lokasi</th><th>Keterangan</th></tr>
                    </thead>
                    <tbody id="activityTableBody">
                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i><h3>Memuat Data...</h3><p>Mohon tunggu sebentar</p></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="sidebar-section">
            <div class="summary-card">
                <div class="summary-header"><h3><i class="fas fa-chart-pie"></i> Ringkasan Aktivitas</h3></div>
                <div class="summary-body">
                    <div class="activity-type-list">
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="fas fa-notes-medical"></i></div>
                                <span class="activity-type-name">SOAP Ralan</span>
                            </div>
                            <span class="activity-type-count" id="summarySoapRalan">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#fef3c7;color:#b45309;"><i class="fas fa-notes-medical"></i></div>
                                <span class="activity-type-name">SOAP Ranap</span>
                            </div>
                            <span class="activity-type-count" id="summarySoapRanap">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#fed7aa;color:#ea580c;"><i class="fas fa-prescription"></i></div>
                                <span class="activity-type-name">E-Resep</span>
                            </div>
                            <span class="activity-type-count" id="summaryResep">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#e9d5ff;color:#9333ea;"><i class="fas fa-flask"></i></div>
                                <span class="activity-type-name">Laboratorium</span>
                            </div>
                            <span class="activity-type-count" id="summaryLab">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#fce7f3;color:#db2777;"><i class="fas fa-x-ray"></i></div>
                                <span class="activity-type-name">Radiologi</span>
                            </div>
                            <span class="activity-type-count" id="summaryRadiologi">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#ccfbf1;color:#0d9488;"><i class="fas fa-syringe"></i></div>
                                <span class="activity-type-name">Tindakan</span>
                            </div>
                            <span class="activity-type-count" id="summaryTindakan">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-ambulance"></i></div>
                                <span class="activity-type-name">Awal Medis IGD</span>
                            </div>
                            <span class="activity-type-count" id="summaryAwalMedisIgd">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-user-md"></i></div>
                                <span class="activity-type-name">Awal Medis Ralan</span>
                            </div>
                            <span class="activity-type-count" id="summaryAwalMedisRalan">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-user-md"></i></div>
                                <span class="activity-type-name">Awal Medis Ranap</span>
                            </div>
                            <span class="activity-type-count" id="summaryAwalMedisRanap">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#fce7f3;color:#be185d;"><i class="fas fa-baby"></i></div>
                                <span class="activity-type-name">Awal Medis Neonatus</span>
                            </div>
                            <span class="activity-type-count" id="summaryAwalMedisNeonatus">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#fbcfe8;color:#db2777;"><i class="fas fa-female"></i></div>
                                <span class="activity-type-name">Awal Medis Kandungan</span>
                            </div>
                            <span class="activity-type-count" id="summaryAwalMedisKandungan">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#fecaca;color:#dc2626;"><i class="fas fa-heartbeat"></i></div>
                                <span class="activity-type-name">EKG</span>
                            </div>
                            <span class="activity-type-count" id="summaryEkg">0</span>
                        </div>
                        <div class="activity-type-item">
                            <div class="activity-type-info">
                                <div class="activity-type-icon" style="background:#cffafe;color:#0891b2;"><i class="fas fa-baby"></i></div>
                                <span class="activity-type-name">USG Kandungan</span>
                            </div>
                            <span class="activity-type-count" id="summaryUsg">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-header"><h3><i class="fas fa-clock"></i> Distribusi per Jam</h3></div>
                <div class="summary-body"><div class="hourly-chart" id="hourlyChart"><p style="text-align:center;color:#94a3b8;font-size:12px;">Memuat data...</p></div></div>
            </div>

            <div class="summary-card">
                <div class="summary-header"><h3><i class="fas fa-history"></i> Aktivitas Terakhir</h3></div>
                <div class="summary-body"><div class="timeline" id="timelineContainer"><p style="text-align:center;color:#94a3b8;font-size:12px;">Memuat data...</p></div></div>
            </div>
        </div>
    </div>
</div>

<div class="loading-overlay" id="loadingOverlay"><div class="loading-spinner"></div></div>

<script src="js/activityreport.js"></script>