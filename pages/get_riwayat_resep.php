<?php
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

// Support GET dan POST untuk testing
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Debug mode - tampilkan parameter yang diterima
if(isset($_GET['debug'])) {
    echo "<pre>";
    echo "GET Parameters:\n";
    print_r($_GET);
    echo "\nPOST Parameters:\n";
    print_r($_POST);
    echo "\nREQUEST Parameters:\n";
    print_r($_REQUEST);
    echo "</pre>";
}

// ============================================================
// GET RIWAYAT RESEP DENGAN DETAIL LENGKAP
// ============================================================
if($action === 'get_riwayat_resep') {
    // Validasi no_rkm_medis - untuk menampilkan semua riwayat resep pasien
    $no_rkm_medis = isset($_REQUEST['no_rkm_medis']) ? $_REQUEST['no_rkm_medis'] : '';
    
    if(empty($no_rkm_medis)) {
        echo json_encode(['status' => 'error', 'message' => 'No RM tidak valid']);
        exit();
    }
    
    // Escape untuk keamanan SQL injection
    $no_rkm_medis = str_replace("'", "\'", $no_rkm_medis);
    
    try {
        // Query untuk hitung total resep ralan DAN ranap (untuk pagination yang akurat)
        $queryCount = "
            SELECT COUNT(DISTINCT ro.no_resep) as total
            FROM resep_obat ro
            INNER JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
            WHERE rp.no_rkm_medis = '$no_rkm_medis'
            AND ro.status IN ('ralan', 'ranap')
        ";
        
        $resultCount = bukaquery($queryCount);
        $rowCount = mysqli_fetch_assoc($resultCount);
        $totalResep = $rowCount['total'];
        
        // Query utama untuk data resep berdasarkan no_rkm_medis (ralan + ranap)
        // FIXED: Hapus DISTINCT dan GROUP BY, tambah status ranap
$queryResep = "
    SELECT 
        ro.no_resep,
        ro.tgl_peresepan,
        ro.jam_peresepan,
        ro.no_rawat,
        ro.kd_dokter,
        ro.tgl_perawatan,
        ro.jam,
        ro.status,
        rp.no_rkm_medis,
        p.nm_pasien,
        d.nm_dokter,
        CASE 
            WHEN ro.tgl_perawatan != '0000-00-00' THEN 'Sudah Terlayani'
            ELSE 'Belum Terlayani'
        END as status_layanan
    FROM resep_obat ro
    INNER JOIN reg_periksa rp ON ro.no_rawat = rp.no_rawat
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
    WHERE rp.no_rkm_medis = '$no_rkm_medis'
    AND ro.status IN ('ralan', 'ranap')
    ORDER BY ro.tgl_peresepan DESC, ro.jam_peresepan DESC
";

        
        $resultResep = bukaquery($queryResep);
        $dataResep = array();
        
        while($rowResep = mysqli_fetch_assoc($resultResep)) {
            $no_resep = $rowResep['no_resep'];
            
            // Query untuk obat non racikan
            $queryNonRacikan = "
                SELECT 
                    rd.no_resep,
                    rd.kode_brng,
                    db.nama_brng,
                    db.kode_sat as satuan,
                    rd.jml,
                    rd.aturan_pakai
                FROM resep_dokter rd
                LEFT JOIN databarang db ON rd.kode_brng = db.kode_brng
                WHERE rd.no_resep = '$no_resep'
                ORDER BY rd.kode_brng
            ";
            
            $resultNonRacikan = bukaquery($queryNonRacikan);
            $obatNonRacikan = array();
            
            while($rowNonRacikan = mysqli_fetch_assoc($resultNonRacikan)) {
                $obatNonRacikan[] = array(
                    'kode_brng' => $rowNonRacikan['kode_brng'],
                    'nama_brng' => $rowNonRacikan['nama_brng'],
                    'satuan' => $rowNonRacikan['satuan'],
                    'jml' => $rowNonRacikan['jml'],
                    'aturan_pakai' => $rowNonRacikan['aturan_pakai']
                );
            }
            
            // Query untuk racikan (group by no_racik untuk ambil unique)
            $queryRacikan = "
                SELECT DISTINCT
                    rdr.no_resep,
                    rdr.no_racik,
                    rdr.nama_racik,
                    rdr.kd_racik,
                    mr.nm_racik as metode_racik,
                    rdr.jml_dr,
                    rdr.aturan_pakai,
                    rdr.keterangan
                FROM resep_dokter_racikan rdr
                LEFT JOIN metode_racik mr ON rdr.kd_racik = mr.kd_racik
                WHERE rdr.no_resep = '$no_resep'
                GROUP BY rdr.no_racik
                ORDER BY rdr.no_racik
            ";
            
            $resultRacikan = bukaquery($queryRacikan);
            $obatRacikan = array();
            
            while($rowRacikan = mysqli_fetch_assoc($resultRacikan)) {
                $no_racik = $rowRacikan['no_racik'];
                
                // Query untuk detail komposisi racikan
                $queryDetailRacikan = "
                    SELECT 
                        rdrd.no_resep,
                        rdrd.no_racik,
                        rdrd.kode_brng,
                        db.nama_brng,
                        db.kode_sat as satuan,
                        rdrd.p1,
                        rdrd.p2,
                        rdrd.jml
                    FROM resep_dokter_racikan_detail rdrd
                    LEFT JOIN databarang db ON rdrd.kode_brng = db.kode_brng
                    WHERE rdrd.no_resep = '$no_resep' 
                    AND rdrd.no_racik = '$no_racik'
                    ORDER BY rdrd.kode_brng
                ";
                
                $resultDetailRacikan = bukaquery($queryDetailRacikan);
                $komposisiRacikan = array();
                
                while($rowDetail = mysqli_fetch_assoc($resultDetailRacikan)) {
                    $komposisiRacikan[] = array(
                        'kode_brng' => $rowDetail['kode_brng'],
                        'nama_brng' => $rowDetail['nama_brng'],
                        'satuan' => $rowDetail['satuan'],
                        'p1' => $rowDetail['p1'],
                        'p2' => $rowDetail['p2'],
                        'jml' => $rowDetail['jml']
                    );
                }
                
                $obatRacikan[] = array(
                    'no_racik' => $rowRacikan['no_racik'],
                    'nama_racik' => $rowRacikan['nama_racik'],
                    'kd_racik' => $rowRacikan['kd_racik'],
                    'metode_racik' => $rowRacikan['metode_racik'],
                    'jml_dr' => $rowRacikan['jml_dr'],
                    'aturan_pakai' => $rowRacikan['aturan_pakai'],
                    'keterangan' => $rowRacikan['keterangan'],
                    'komposisi' => $komposisiRacikan
                );
            }
            
            $dataResep[] = array(
                'no_resep' => $rowResep['no_resep'],
                'tgl_peresepan' => $rowResep['tgl_peresepan'],
                'jam_peresepan' => $rowResep['jam_peresepan'],
                'no_rawat' => $rowResep['no_rawat'],
                'kd_dokter' => $rowResep['kd_dokter'],
                'nm_dokter' => $rowResep['nm_dokter'],
                'no_rkm_medis' => $rowResep['no_rkm_medis'],
                'nm_pasien' => $rowResep['nm_pasien'],
                'tgl_perawatan' => $rowResep['tgl_perawatan'],
                'status' => $rowResep['status'],  // PENTING: field untuk badge ralan/ranap
                'status_layanan' => $rowResep['status_layanan'],
                'obat_non_racikan' => $obatNonRacikan,
                'obat_racikan' => $obatRacikan
            );
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $dataResep,
            'count' => $totalResep  // Gunakan count dari query, bukan count($dataResep)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Terjadi kesalahan: ' . $e->getMessage()
        ]);
    }
}

// ============================================================
// DELETE RESEP (hanya bisa jika belum terlayani)
// ============================================================
else if($action === 'delete_resep') {
    $no_resep = isset($_POST['no_resep']) ? $_POST['no_resep'] : '';
    
    if(empty($no_resep)) {
        echo json_encode(['status' => 'error', 'message' => 'No resep tidak valid']);
        exit();
    }
    
    // Escape untuk keamanan
    $no_resep = str_replace("'", "\'", $no_resep);
    
    try {
        // Cek apakah sudah terlayani
        $queryCek = "SELECT tgl_perawatan FROM resep_obat WHERE no_resep = '$no_resep'";
        $resultCek = bukaquery($queryCek);
        $rowCek = mysqli_fetch_assoc($resultCek);
        
        if($rowCek['tgl_perawatan'] != '0000-00-00') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Resep sudah terlayani, tidak bisa dihapus!'
            ]);
            exit();
        }
        
        // Hapus detail racikan
        $queryDelDetailRacikan = "DELETE FROM resep_dokter_racikan_detail WHERE no_resep = '$no_resep'";
        bukaquery($queryDelDetailRacikan);
        
        // Hapus racikan
        $queryDelRacikan = "DELETE FROM resep_dokter_racikan WHERE no_resep = '$no_resep'";
        bukaquery($queryDelRacikan);
        
        // Hapus non racikan
        $queryDelNonRacikan = "DELETE FROM resep_dokter WHERE no_resep = '$no_resep'";
        bukaquery($queryDelNonRacikan);
        
        // Hapus resep utama
        $queryDelResep = "DELETE FROM resep_obat WHERE no_resep = '$no_resep'";
        bukaquery($queryDelResep);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Resep berhasil dihapus'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menghapus resep: ' . $e->getMessage()
        ]);
    }
}

// ============================================================
// COPY RESEP (duplikasi resep ke nomor baru)
// ============================================================
else if($action === 'copy_resep') {
    $no_resep_lama = isset($_POST['no_resep']) ? $_POST['no_resep'] : '';
    $no_rawat = isset($_POST['no_rawat']) ? $_POST['no_rawat'] : '';
    
    if(empty($no_resep_lama) || empty($no_rawat)) {
        echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap']);
        exit();
    }
    
    // Escape untuk keamanan
    $no_resep_lama = str_replace("'", "\'", $no_resep_lama);
    $no_rawat = str_replace("'", "\'", $no_rawat);
    
    try {
        // Generate nomor resep baru
        $tgl_sekarang = date('Y-m-d');
        $jam_sekarang = date('H:i:s');
        
        $queryMaxResep = "SELECT IFNULL(MAX(CAST(SUBSTRING(no_resep, 9) AS UNSIGNED)), 0) + 1 as next_no 
                         FROM resep_obat 
                         WHERE tgl_peresepan = '$tgl_sekarang'";
        $resultMax = bukaquery($queryMaxResep);
        $rowMax = mysqli_fetch_assoc($resultMax);
        $next_no = str_pad($rowMax['next_no'], 5, '0', STR_PAD_LEFT);
        $no_resep_baru = date('Ymd') . $next_no;
        
        // Ambil data resep lama
        $queryResepLama = "SELECT * FROM resep_obat WHERE no_resep = '$no_resep_lama'";
        $resultResepLama = bukaquery($queryResepLama);
        $rowResepLama = mysqli_fetch_assoc($resultResepLama);
        
        // Insert resep baru
        $kd_dokter = $rowResepLama['kd_dokter'];
        $queryInsertResep = "
            INSERT INTO resep_obat (no_resep, tgl_peresepan, jam_peresepan, no_rawat, kd_dokter, tgl_perawatan, jam, status)
            VALUES ('$no_resep_baru', '$tgl_sekarang', '$jam_sekarang', '$no_rawat', '$kd_dokter', '0000-00-00', '00:00:00', 'ralan')
        ";
        bukaquery($queryInsertResep);
        
        // Copy obat non racikan
        $queryNonRacikan = "SELECT * FROM resep_dokter WHERE no_resep = '$no_resep_lama'";
        $resultNonRacikan = bukaquery($queryNonRacikan);
        
        while($rowNR = mysqli_fetch_assoc($resultNonRacikan)) {
            $kode_brng = $rowNR['kode_brng'];
            $jml = $rowNR['jml'];
            $aturan_pakai = str_replace("'", "\'", $rowNR['aturan_pakai']);
            
            $queryInsertNR = "
                INSERT INTO resep_dokter (no_resep, kode_brng, jml, aturan_pakai)
                VALUES ('$no_resep_baru', '$kode_brng', '$jml', '$aturan_pakai')
            ";
            bukaquery($queryInsertNR);
        }
        
        // Copy racikan
        $queryRacikan = "SELECT DISTINCT no_racik FROM resep_dokter_racikan WHERE no_resep = '$no_resep_lama'";
        $resultRacikan = bukaquery($queryRacikan);
        
        while($rowR = mysqli_fetch_assoc($resultRacikan)) {
            $no_racik_lama = $rowR['no_racik'];
            
            // Ambil data racikan
            $queryDataRacikan = "SELECT * FROM resep_dokter_racikan WHERE no_resep = '$no_resep_lama' AND no_racik = '$no_racik_lama' LIMIT 1";
            $resultDataRacikan = bukaquery($queryDataRacikan);
            $rowDR = mysqli_fetch_assoc($resultDataRacikan);
            
            $nama_racik = str_replace("'", "\'", $rowDR['nama_racik']);
            $kd_racik = $rowDR['kd_racik'];
            $jml_dr = $rowDR['jml_dr'];
            $aturan_pakai = str_replace("'", "\'", $rowDR['aturan_pakai']);
            $keterangan = str_replace("'", "\'", $rowDR['keterangan']);
            
            $queryInsertRacikan = "
                INSERT INTO resep_dokter_racikan (no_resep, no_racik, nama_racik, kd_racik, jml_dr, aturan_pakai, keterangan)
                VALUES ('$no_resep_baru', '$no_racik_lama', '$nama_racik', '$kd_racik', '$jml_dr', '$aturan_pakai', '$keterangan')
            ";
            bukaquery($queryInsertRacikan);
            
            // Copy detail racikan
            $queryDetailRacikan = "SELECT * FROM resep_dokter_racikan_detail WHERE no_resep = '$no_resep_lama' AND no_racik = '$no_racik_lama'";
            $resultDetailRacikan = bukaquery($queryDetailRacikan);
            
            while($rowDRD = mysqli_fetch_assoc($resultDetailRacikan)) {
                $kode_brng = $rowDRD['kode_brng'];
                $p1 = $rowDRD['p1'];
                $p2 = $rowDRD['p2'];
                $jml = $rowDRD['jml'];
                
                $queryInsertDetail = "
                    INSERT INTO resep_dokter_racikan_detail (no_resep, no_racik, kode_brng, p1, p2, jml)
                    VALUES ('$no_resep_baru', '$no_racik_lama', '$kode_brng', '$p1', '$p2', '$jml')
                ";
                bukaquery($queryInsertDetail);
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Resep berhasil dicopy',
            'no_resep_baru' => $no_resep_baru
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal copy resep: ' . $e->getMessage()
        ]);
    }
}


// ============================================================
// GET DETAIL RESEP (untuk Copy Resep)
// ============================================================
elseif($action === 'get_detail_resep') {
    $no_resep = isset($_REQUEST['no_resep']) ? $_REQUEST['no_resep'] : '';
    
    if(empty($no_resep)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No resep tidak boleh kosong'
        ]);
        exit();
    }
    
    // Buka koneksi database menggunakan function dari conf.php
    $db_connection = bukakoneksi();
    
    if(!$db_connection) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Koneksi database gagal'
        ]);
        exit();
    }
    
    try {
        // ============================================================
        // QUERY OBAT NON RACIKAN
        // ============================================================
        $queryNonRacikan = "
            SELECT 
                d.kode_brng,
                d.nama_brng,
                d.jml,
                d.embalase,
                d.tuslah,
                d.total,
                ky.satuan,
                COALESCE(rp.aturan_pakai, '') as aturan_pakai
            FROM resep_obat r
            INNER JOIN detail_pemberian_obat d ON r.no_rawat = d.no_rawat 
                AND r.tgl_peresepan = d.tgl_perawatan 
                AND r.jam_peresepan = d.jam
            LEFT JOIN kodesatuan ky ON d.kode_sat = ky.kode_sat
            LEFT JOIN aturan_pakai rp ON d.no_rawat = rp.no_rawat 
                AND d.tgl_perawatan = rp.tgl_perawatan 
                AND d.jam = rp.jam 
                AND d.kode_brng = rp.kode_brng
            WHERE r.no_resep = ?
            AND d.status = 'Ralan'
            ORDER BY d.tgl_perawatan, d.jam
        ";
        
        $stmtNonRacikan = $db_connection->prepare($queryNonRacikan);
        if (!$stmtNonRacikan) {
            throw new Exception('Prepare statement non racikan gagal: ' . $db_connection->error);
        }
        
        $stmtNonRacikan->bind_param('s', $no_resep);
        $stmtNonRacikan->execute();
        $resultNonRacikan = $stmtNonRacikan->get_result();
        
        $obatNonRacikan = [];
        while ($row = $resultNonRacikan->fetch_assoc()) {
            $obatNonRacikan[] = [
                'kode_brng' => $row['kode_brng'],
                'nama_brng' => $row['nama_brng'],
                'jml' => $row['jml'],
                'satuan' => $row['satuan'] ? $row['satuan'] : 'TAB',
                'aturan_pakai' => $row['aturan_pakai'] ? $row['aturan_pakai'] : '-',
                'embalase' => $row['embalase'],
                'tuslah' => $row['tuslah'],
                'total' => $row['total']
            ];
        }
        $stmtNonRacikan->close();
        
        // ============================================================
        // QUERY OBAT RACIKAN
        // ============================================================
        $queryRacikan = "
            SELECT 
                r.no_racik,
                r.nama_racik,
                r.kd_racik,
                r.jml_dr,
                COALESCE(r.aturan_pakai, '') as aturan_pakai,
                COALESCE(r.keterangan, '') as keterangan,
                COALESCE(mr.metode_racik, 'Puyer') as metode_racik
            FROM resep_obat ro
            INNER JOIN resep_dokter r ON ro.no_rawat = r.no_rawat 
                AND ro.tgl_peresepan = r.tgl_perawatan 
                AND ro.jam_peresepan = r.jam
            LEFT JOIN metode_racik mr ON r.kd_racik = mr.kd_racik
            WHERE ro.no_resep = ?
            GROUP BY r.no_racik
            ORDER BY r.no_racik
        ";
        
        $stmtRacikan = $db_connection->prepare($queryRacikan);
        if (!$stmtRacikan) {
            throw new Exception('Prepare statement racikan gagal: ' . $db_connection->error);
        }
        
        $stmtRacikan->bind_param('s', $no_resep);
        $stmtRacikan->execute();
        $resultRacikan = $stmtRacikan->get_result();
        
        $obatRacikan = [];
        while ($row = $resultRacikan->fetch_assoc()) {
            // ============================================================
            // QUERY KOMPOSISI RACIKAN
            // ============================================================
            $queryKomposisi = "
                SELECT 
                    rd.kode_brng,
                    rd.nama_brng,
                    rd.jml,
                    COALESCE(rd.kandungan, 0) as dosis_obat,
                    COALESCE(rd.jml_dr, 0) as dosis_diberi,
                    COALESCE(ky.satuan, 'TAB') as satuan
                FROM resep_obat ro
                INNER JOIN resep_dokter_racikan rd ON ro.no_rawat = rd.no_rawat 
                    AND ro.tgl_peresepan = rd.tgl_perawatan 
                    AND ro.jam_peresepan = rd.jam 
                    AND rd.no_racik = ?
                LEFT JOIN kodesatuan ky ON rd.kode_sat = ky.kode_sat
                WHERE ro.no_resep = ?
                ORDER BY rd.urutan
            ";
            
            $stmtKomp = $db_connection->prepare($queryKomposisi);
            if (!$stmtKomp) {
                throw new Exception('Prepare statement komposisi gagal: ' . $db_connection->error);
            }
            
            $stmtKomp->bind_param('is', $row['no_racik'], $no_resep);
            $stmtKomp->execute();
            $resultKomp = $stmtKomp->get_result();
            
            $komposisi = [];
            while ($komp = $resultKomp->fetch_assoc()) {
                // Hitung jml_racikan: (dosis_diberi / dosis_obat) * jumlah_racikan
                $jml_racikan = 0;
                if ($komp['dosis_obat'] > 0) {
                    $jml_racikan = ($komp['dosis_diberi'] / $komp['dosis_obat']) * $row['jml_dr'];
                }
                
                $komposisi[] = [
                    'kode_brng' => $komp['kode_brng'],
                    'nama_brng' => $komp['nama_brng'],
                    'dosis_obat' => floatval($komp['dosis_obat']),
                    'dosis_diberi' => floatval($komp['dosis_diberi']),
                    'jml' => $komp['jml'],
                    'jml_racikan' => number_format($jml_racikan, 2, '.', ''),
                    'satuan' => $komp['satuan']
                ];
            }
            $stmtKomp->close();
            
            // Tambahkan racikan dengan komposisinya
            $obatRacikan[] = [
                'no_racik' => $row['no_racik'],
                'nama_racik' => $row['nama_racik'],
                'metode_racik' => $row['metode_racik'],
                'jml_dr' => $row['jml_dr'],
                'aturan_pakai' => $row['aturan_pakai'],
                'keterangan' => $row['keterangan'],
                'komposisi' => $komposisi
            ];
        }
        $stmtRacikan->close();
        
        // ============================================================
        // RESPONSE
        // ============================================================
        echo json_encode([
            'status' => 'success',
            'data' => [
                'no_resep' => $no_resep,
                'obat_non_racikan' => $obatNonRacikan,
                'obat_racikan' => $obatRacikan
            ],
            'count' => [
                'non_racikan' => count($obatNonRacikan),
                'racikan' => count($obatRacikan)
            ]
        ]);
        
        // Tutup koneksi
        mysqli_close($db_connection);
        
    } catch (Exception $e) {
        // Tutup koneksi jika error
        if(isset($db_connection) && $db_connection) {
            mysqli_close($db_connection);
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Error get detail resep: ' . $e->getMessage()
        ]);
    }
}


else {
    echo json_encode(['status' => 'error', 'message' => 'Action tidak valid']);
}
?>