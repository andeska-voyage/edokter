<?php
// ============================================================
// GET TEMPLATE OBAT DOKTER
// ============================================================
// File: pages/get_template_obat.php
// Fungsi: Mengambil data template obat dokter beserta detail resep

header('Content-Type: application/json');
require_once '../conf/conf.php';

// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Matikan di production
ini_set('log_errors', 1);

try {
    // Ambil parameter kd_dokter dan no_template
    $kd_dokter = isset($_GET['kd_dokter']) ? validTeks4($_GET['kd_dokter'], 20) : '';
    $no_template = isset($_GET['no_template']) ? validTeks4($_GET['no_template'], 20) : '';
    
    // Validasi input
    if (empty($kd_dokter)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Kode dokter tidak boleh kosong'
        ]);
        exit;
    }
    
    // ============================================================
    // QUERY 1: Ambil data template utama
    // ============================================================
    $where_clause = "WHERE kd_dokter = '$kd_dokter'";
    
    // Jika no_template diisi, tambahkan filter
    if (!empty($no_template)) {
        $where_clause .= " AND no_template = '$no_template'";
    }
    
    $query_template = "
        SELECT 
            no_template,
            kd_dokter,
            keluhan,
            penilaian as nama_template
        FROM template_pemeriksaan_dokter
        $where_clause
        ORDER BY no_template DESC
    ";
    
    $result_template = bukaquery($query_template);
    
    if (!$result_template) {
        throw new Exception('Query template gagal dijalankan');
    }
    
    $templates = [];
    
    // Loop setiap template
    while ($template = mysqli_fetch_assoc($result_template)) {
        $no_template = $template['no_template'];
        
        // ============================================================
        // QUERY 2: Ambil obat NON RACIKAN untuk template ini
        // ============================================================
        $query_non_racikan = "
            SELECT 
                r.kode_brng,
                d.nama_brng,
                r.jml,
                r.aturan_pakai,
                COALESCE(d.ralan, 0)     as ralan,
                COALESCE(d.kelas1, 0)    as kelas1,
                COALESCE(d.kelas2, 0)    as kelas2,
                COALESCE(d.kelas3, 0)    as kelas3,
                COALESCE(d.utama, 0)     as utama,
                COALESCE(d.vip, 0)       as vip,
                COALESCE(d.vvip, 0)      as vvip,
                COALESCE(d.beliluar, 0)  as beliluar,
                COALESCE(d.jualbebas, 0) as jualbebas,
                COALESCE(d.karyawan, 0)  as karyawan
            FROM template_pemeriksaan_dokter_resep r
            LEFT JOIN databarang d ON r.kode_brng = d.kode_brng
            WHERE r.no_template = '$no_template'
            ORDER BY r.kode_brng
        ";
        
        $result_non_racikan = bukaquery($query_non_racikan);
        $obat_non_racikan = [];
        
        if ($result_non_racikan) {
            while ($obat = mysqli_fetch_assoc($result_non_racikan)) {
                $obat_non_racikan[] = [
                    'kode_brng'   => $obat['kode_brng'],
                    'nama_brng'   => $obat['nama_brng'] ?? 'Obat tidak ditemukan',
                    'jml'         => $obat['jml'],
                    'aturan_pakai'=> $obat['aturan_pakai'],
                    'harga'       => floatval($obat['ralan']),
                    'tarif'       => [
                        'ralan'     => floatval($obat['ralan']),
                        'kelas1'    => floatval($obat['kelas1']),
                        'kelas2'    => floatval($obat['kelas2']),
                        'kelas3'    => floatval($obat['kelas3']),
                        'utama'     => floatval($obat['utama']),
                        'vip'       => floatval($obat['vip']),
                        'vvip'      => floatval($obat['vvip']),
                        'beliluar'  => floatval($obat['beliluar']),
                        'jualbebas' => floatval($obat['jualbebas']),
                        'karyawan'  => floatval($obat['karyawan']),
                    ]
                ];
            }
        }
        
        // ============================================================
        // QUERY 3: Ambil RACIKAN untuk template ini
        // ============================================================
        $query_racikan = "
            SELECT 
                r.no_racik,
                r.nama_racik,
                r.kd_racik,
                m.nm_racik as metode_racik,
                r.jml_dr,
                r.aturan_pakai,
                r.keterangan
            FROM template_pemeriksaan_dokter_resep_racikan r
            LEFT JOIN metode_racik m ON r.kd_racik = m.kd_racik
            WHERE r.no_template = '$no_template'
            ORDER BY r.no_racik
        ";
        
        $result_racikan = bukaquery($query_racikan);
        $obat_racikan = [];
        
        if ($result_racikan) {
            while ($racikan = mysqli_fetch_assoc($result_racikan)) {
                $no_racik = $racikan['no_racik'];
                
                // ============================================================
                // QUERY 4: Ambil DETAIL KOMPOSISI RACIKAN
                // ============================================================
                $query_detail = "
                    SELECT 
                        rd.kode_brng,
                        d.nama_brng,
                        COALESCE(d.kapasitas, 0) as kapasitas,
                        rd.kandungan as dosis,
                        rd.jml,
                        COALESCE(d.ralan, 0)     as ralan,
                        COALESCE(d.kelas1, 0)    as kelas1,
                        COALESCE(d.kelas2, 0)    as kelas2,
                        COALESCE(d.kelas3, 0)    as kelas3,
                        COALESCE(d.utama, 0)     as utama,
                        COALESCE(d.vip, 0)       as vip,
                        COALESCE(d.vvip, 0)      as vvip,
                        COALESCE(d.beliluar, 0)  as beliluar,
                        COALESCE(d.jualbebas, 0) as jualbebas,
                        COALESCE(d.karyawan, 0)  as karyawan
                    FROM template_pemeriksaan_dokter_resep_racikan_detail rd
                    LEFT JOIN databarang d ON rd.kode_brng = d.kode_brng
                    WHERE rd.no_template = '$no_template'
                    AND rd.no_racik = '$no_racik'
                    ORDER BY rd.kode_brng
                ";
                
                $result_detail = bukaquery($query_detail);
                $komposisi = [];
                
                if ($result_detail) {
                    while ($detail = mysqli_fetch_assoc($result_detail)) {
                        $komposisi[] = [
                            'kode_brng'   => $detail['kode_brng'],
                            'nama_brng'   => $detail['nama_brng'] ?? 'Obat tidak ditemukan',
                            'dosis_obat'  => floatval($detail['kapasitas']),
                            'dosis_diberi'=> floatval($detail['dosis']),
                            'jml_racikan' => floatval($detail['jml']),
                            'dosis'       => floatval($detail['dosis']),
                            'jml'         => floatval($detail['jml']),
                            'harga'       => floatval($detail['ralan']),
                            'tarif'       => [
                                'ralan'     => floatval($detail['ralan']),
                                'kelas1'    => floatval($detail['kelas1']),
                                'kelas2'    => floatval($detail['kelas2']),
                                'kelas3'    => floatval($detail['kelas3']),
                                'utama'     => floatval($detail['utama']),
                                'vip'       => floatval($detail['vip']),
                                'vvip'      => floatval($detail['vvip']),
                                'beliluar'  => floatval($detail['beliluar']),
                                'jualbebas' => floatval($detail['jualbebas']),
                                'karyawan'  => floatval($detail['karyawan']),
                            ]
                        ];
                    }
                }
                
                // Masukkan racikan dengan komposisinya
                $obat_racikan[] = [
                    'no_racik' => $racikan['no_racik'],
                    'nama_racik' => $racikan['nama_racik'],
                    'kd_racik' => $racikan['kd_racik'],
                    'metode_racik' => $racikan['metode_racik'] ?? 'Metode tidak ditemukan',
                    'jml_dr' => $racikan['jml_dr'],
                    'aturan_pakai' => $racikan['aturan_pakai'],
                    'keterangan' => $racikan['keterangan'],
                    'komposisi' => $komposisi
                ];
            }
        }
        
        // ============================================================
        // Tentukan jenis template berdasarkan isi
        // ============================================================
        $jenis_template = 'Tidak diketahui';
        $count_non_racikan = count($obat_non_racikan);
        $count_racikan = count($obat_racikan);
        
        if ($count_non_racikan > 0 && $count_racikan > 0) {
            $jenis_template = 'Non Racikan & Racikan';
        } elseif ($count_non_racikan > 0) {
            $jenis_template = 'Non Racikan Saja';
        } elseif ($count_racikan > 0) {
            $jenis_template = 'Racikan Saja';
        } else {
            $jenis_template = 'Kosong (Tidak ada obat)';
        }
        
        // ============================================================
        // SKIP template kosong (tidak ada obat sama sekali)
        // ============================================================
        if ($count_non_racikan == 0 && $count_racikan == 0) {
            continue; // Skip template ini, jangan tampilkan
        }
        
        // Susun data template lengkap
        $templates[] = [
            'no_template' => $template['no_template'],
            'kd_dokter' => $template['kd_dokter'],
            'nama_template' => $template['nama_template'],
            'keluhan_asli' => $template['keluhan'], // Keluhan asli dari database
            'jenis_template' => $jenis_template, // Jenis template berdasarkan isi
            'jumlah_non_racikan' => $count_non_racikan,
            'jumlah_racikan' => $count_racikan,
            'obat_non_racikan' => $obat_non_racikan,
            'obat_racikan' => $obat_racikan
        ];
    }
    
    // ============================================================
    // Response JSON
    // ============================================================
    echo json_encode([
        'status' => 'success',
        'message' => 'Data template obat berhasil dimuat',
        'kd_dokter' => $kd_dokter,
        'total_template' => count($templates),
        'data' => $templates
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Handle error
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>