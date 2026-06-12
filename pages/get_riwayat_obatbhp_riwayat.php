<?php
// Output buffering
ob_start();

// Cek session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../conf/conf.php');

// Bersihkan buffer
ob_end_clean();

// Set header JSON
header('Content-Type: application/json; charset=utf-8');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode(['status' => 'error', 'message' => 'Session expired'], JSON_UNESCAPED_UNICODE);
    exit();
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if($action === 'getriwayatobatbhp') {

    $no_rawat = isset($_REQUEST['no_rawat']) ? validTeks($_REQUEST['no_rawat']) : '';

    if(empty($no_rawat)) {
        echo json_encode(['status' => 'error', 'message' => 'Parameter tidak lengkap'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    try {
        $query = "
            SELECT 
                dpo.tgl_perawatan,
                dpo.jam,
                dpo.no_rawat,
                dpo.kode_brng,
                dpo.jml,
                dpo.status,
                db.nama_brng,
                rp.no_rkm_medis,
                p.nm_pasien,
                COALESCE(ap.aturan, '-') as aturan_pakai,
                orx.no_racik,
                orx.nama_racik,
                orx.jml_dr,
                orx.aturan_pakai as racik_aturan,
                orx.keterangan,
                dor.no_racik as is_racik_detail
            FROM detail_pemberian_obat dpo
            INNER JOIN reg_periksa rp ON dpo.no_rawat = rp.no_rawat
            INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN databarang db ON dpo.kode_brng = db.kode_brng
            LEFT JOIN aturan_pakai ap ON dpo.no_rawat = ap.no_rawat 
                AND dpo.tgl_perawatan = ap.tgl_perawatan 
                AND dpo.jam = ap.jam 
                AND dpo.kode_brng = ap.kode_brng
            LEFT JOIN detail_obat_racikan dor ON dpo.no_rawat = dor.no_rawat 
                AND dpo.tgl_perawatan = dor.tgl_perawatan 
                AND dpo.jam = dor.jam
                AND dpo.kode_brng = dor.kode_brng
            LEFT JOIN obat_racikan orx ON dor.no_rawat = orx.no_rawat 
                AND dor.tgl_perawatan = orx.tgl_perawatan 
                AND dor.jam = orx.jam
                AND dor.no_racik = orx.no_racik
            WHERE dpo.no_rawat = '$no_rawat'
            ORDER BY dpo.tgl_perawatan DESC, dpo.jam DESC
        ";
        
        $result = bukaquery($query);
        $data = [];
        $racikan_temp = [];
        
        while($row = mysqli_fetch_assoc($result)) {
            $is_racikan = !empty($row['is_racik_detail']);
            
            if($is_racikan) {
                $racikan_key = $row['no_rawat'] . '|' . $row['tgl_perawatan'] . '|' . $row['jam'] . '|' . $row['no_racik'];
                
                if(!isset($racikan_temp[$racikan_key])) {
                    $racikan_temp[$racikan_key] = [
                        'tipe'          => 'racikan',
                        'tgl_perawatan' => $row['tgl_perawatan'],
                        'jam'           => $row['jam'],
                        'no_rawat'      => $row['no_rawat'],
                        'no_rkm_medis'  => $row['no_rkm_medis'],
                        'nm_pasien'     => $row['nm_pasien'],
                        'no_racik'      => $row['no_racik'],
                        'nama_racik'    => $row['nama_racik'] ?: 'Racikan',
                        'jml_dr'        => $row['jml_dr'] ?: '0',
                        'aturan_pakai'  => $row['racik_aturan'] ?: '-',
                        'keterangan'    => $row['keterangan'] ?: '',
                        'status'        => $row['status'],
                        'komposisi'     => []
                    ];
                }
                
                $racikan_temp[$racikan_key]['komposisi'][] = [
                    'kode_brng' => $row['kode_brng'],
                    'nama_brng' => $row['nama_brng'],
                    'jml'       => $row['jml']
                ];
                
            } else {
                $data[] = [
                    'tipe'          => 'non_racik',
                    'tgl_perawatan' => $row['tgl_perawatan'],
                    'jam'           => $row['jam'],
                    'no_rawat'      => $row['no_rawat'],
                    'no_rkm_medis'  => $row['no_rkm_medis'],
                    'nm_pasien'     => $row['nm_pasien'],
                    'kode_brng'     => $row['kode_brng'],
                    'nama_brng'     => $row['nama_brng'],
                    'jml'           => $row['jml'],
                    'aturan_pakai'  => $row['aturan_pakai'] ?: '-',
                    'status'        => $row['status']
                ];
            }
        }
        
        // Merge racikan ke data
        foreach($racikan_temp as $racikan) {
            $data[] = $racikan;
        }
        
        // Sort by tanggal, jam
        usort($data, function($a, $b) {
            $date_cmp = strcmp($b['tgl_perawatan'], $a['tgl_perawatan']);
            if($date_cmp !== 0) return $date_cmp;
            return strcmp($b['jam'], $a['jam']);
        });
        
        echo json_encode([
            'status' => 'success',
            'data'   => $data,
            'total'  => count($data)
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Action tidak valid'
    ], JSON_UNESCAPED_UNICODE);
}
?>