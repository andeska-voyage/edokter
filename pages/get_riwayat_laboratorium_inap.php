<?php
session_start();
require_once('../conf/conf.php');

header('Content-Type: application/json');

// Validasi session
if (!isset($_SESSION["ses_dokter"])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit();
}

// Ambil parameter
$norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
$kategori = isset($_GET['kategori']) ? strtoupper($_GET['kategori']) : 'PK';

if (empty($norawat)) {
    echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid']);
    exit();
}

// Validasi kategori
if (!in_array($kategori, ['PK', 'PA', 'MB'])) {
    $kategori = 'PK';
}

try {
    $data = [];
    
    if ($kategori === 'PA') {
        // Query untuk PA - tabel permintaan_labpa + permintaan_pemeriksaan_labpa
        $query_riwayat = "SELECT 
                            pl.noorder,
                            pl.no_rawat,
                            pl.tgl_permintaan,
                            pl.jam_permintaan,
                            pl.tgl_sampel,
                            pl.informasi_tambahan,
                            pl.diagnosa_klinis,
                            pl.dokter_perujuk,
                            IFNULL(d.nm_dokter, '-') as nm_dokter,
                            pl.pengambilan_bahan,
                            pl.diperoleh_dengan,
                            pl.lokasi_jaringan,
                            pl.diawetkan_dengan,
                            pl.pernah_dilakukan_di,
                            pl.tanggal_pa_sebelumnya,
                            pl.nomor_pa_sebelumnya,
                            pl.diagnosa_pa_sebelumnya,
                            ppl.kd_jenis_prw,
                            jpl.nm_perawatan as nm_template
                          FROM permintaan_labpa pl
                          LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
                          INNER JOIN permintaan_pemeriksaan_labpa ppl ON pl.noorder = ppl.noorder
                          INNER JOIN jns_perawatan_lab jpl ON ppl.kd_jenis_prw = jpl.kd_jenis_prw
                          WHERE pl.no_rawat = '$norawat'
                          ORDER BY pl.tgl_permintaan DESC, pl.jam_permintaan DESC, ppl.kd_jenis_prw";
        
        $result_riwayat = bukaquery($query_riwayat);
        
        if ($result_riwayat && mysqli_num_rows($result_riwayat) > 0) {
            while ($row = mysqli_fetch_array($result_riwayat)) {
                $tgl_sampel = trim($row['tgl_sampel']);
                $sudah_sampel = (!empty($tgl_sampel) && $tgl_sampel != '0000-00-00');
                
                $data[] = [
                    'noorder' => $row['noorder'],
                    'no_rawat' => $row['no_rawat'],
                    'tgl_permintaan' => $row['tgl_permintaan'],
                    'jam_permintaan' => $row['jam_permintaan'],
                    'tgl_sampel' => $tgl_sampel,
                    'dokter_perujuk' => $row['dokter_perujuk'],
                    'nm_dokter' => $row['nm_dokter'],
                    'kd_jenis_prw' => $row['kd_jenis_prw'],
                    'nm_template' => $row['nm_template'],
                    'diagnosa_klinis' => !empty($row['diagnosa_klinis']) ? $row['diagnosa_klinis'] : '-',
                    'informasi_tambahan' => !empty($row['informasi_tambahan']) ? $row['informasi_tambahan'] : '-',
                    'pengambilan_bahan' => $row['pengambilan_bahan'] ?? '-',
                    'diperoleh_dengan' => $row['diperoleh_dengan'] ?? '-',
                    'lokasi_jaringan' => $row['lokasi_jaringan'] ?? '-',
                    'diawetkan_dengan' => $row['diawetkan_dengan'] ?? '-',
                    'pernah_dilakukan_di' => $row['pernah_dilakukan_di'] ?? '-',
                    'tanggal_pa_sebelumnya' => $row['tanggal_pa_sebelumnya'] ?? '',
                    'nomor_pa_sebelumnya' => $row['nomor_pa_sebelumnya'] ?? '-',
                    'diagnosa_pa_sebelumnya' => $row['diagnosa_pa_sebelumnya'] ?? '-',
                    'status' => $sudah_sampel ? 'Sudah Diambil' : 'Belum Diambil',
                    'disable_hapus' => $sudah_sampel
                ];
            }
        }
        
    } elseif ($kategori === 'MB') {
        // Query untuk MB - tabel permintaan_labmb + permintaan_detail_permintaan_labmb
        // Sama seperti PK, gabungkan data dari template dengan detail DAN template tanpa detail
        
        // Query 1: Permintaan MB yang punya detail (dari permintaan_detail_permintaan_labmb)
        $query_dengan_detail = "SELECT 
                            pl.noorder,
                            pl.no_rawat,
                            pl.tgl_permintaan,
                            pl.jam_permintaan,
                            pl.tgl_sampel,
                            pl.informasi_tambahan,
                            pl.diagnosa_klinis,
                            pl.dokter_perujuk,
                            IFNULL(d.nm_dokter, '-') as nm_dokter,
                            pld.kd_jenis_prw,
                            jpl.nm_perawatan as nm_template,
                            pld.id_template,
                            tl.Pemeriksaan as nama_pemeriksaan,
                            tl.satuan,
                            tl.nilai_rujukan_ld,
                            tl.nilai_rujukan_la,
                            tl.nilai_rujukan_pd,
                            tl.nilai_rujukan_pa
                          FROM permintaan_labmb pl
                          LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
                          INNER JOIN permintaan_detail_permintaan_labmb pld ON pl.noorder = pld.noorder
                          INNER JOIN jns_perawatan_lab jpl ON pld.kd_jenis_prw = jpl.kd_jenis_prw
                          INNER JOIN template_laboratorium tl ON pld.id_template = tl.id_template
                          WHERE pl.no_rawat = '$norawat'
                          ORDER BY pl.tgl_permintaan DESC, pl.jam_permintaan DESC, pld.kd_jenis_prw, tl.Pemeriksaan";
        
        $result_dengan_detail = bukaquery($query_dengan_detail);
        
        // Kumpulkan noorder yang sudah punya detail
        $noorder_dengan_detail = [];
        
        if ($result_dengan_detail && mysqli_num_rows($result_dengan_detail) > 0) {
            while ($row = mysqli_fetch_array($result_dengan_detail)) {
                $noorder_dengan_detail[$row['noorder']] = true;
                
                $nilai_parts = [];
                if (!empty($row['nilai_rujukan_ld'])) $nilai_parts[] = 'LD:' . $row['nilai_rujukan_ld'];
                if (!empty($row['nilai_rujukan_la'])) $nilai_parts[] = 'LA:' . $row['nilai_rujukan_la'];
                if (!empty($row['nilai_rujukan_pd'])) $nilai_parts[] = 'PD:' . $row['nilai_rujukan_pd'];
                if (!empty($row['nilai_rujukan_pa'])) $nilai_parts[] = 'PA:' . $row['nilai_rujukan_pa'];
                
                $tgl_sampel = trim($row['tgl_sampel']);
                $sudah_sampel = (!empty($tgl_sampel) && $tgl_sampel != '0000-00-00');
                
                $data[] = [
                    'noorder' => $row['noorder'],
                    'no_rawat' => $row['no_rawat'],
                    'tgl_permintaan' => $row['tgl_permintaan'],
                    'jam_permintaan' => $row['jam_permintaan'],
                    'tgl_sampel' => $tgl_sampel,
                    'dokter_perujuk' => $row['dokter_perujuk'],
                    'nm_dokter' => $row['nm_dokter'],
                    'kd_jenis_prw' => $row['kd_jenis_prw'],
                    'nm_template' => $row['nm_template'],
                    'id_template' => $row['id_template'],
                    'pemeriksaan' => $row['nama_pemeriksaan'],
                    'satuan' => !empty($row['satuan']) ? $row['satuan'] : '-',
                    'nilai_rujukan' => !empty($nilai_parts) ? implode(', ', $nilai_parts) : '-',
                    'diagnosa_klinis' => !empty($row['diagnosa_klinis']) ? $row['diagnosa_klinis'] : '-',
                    'informasi_tambahan' => !empty($row['informasi_tambahan']) ? $row['informasi_tambahan'] : '-',
                    'status' => $sudah_sampel ? 'Sudah Diambil' : 'Belum Diambil',
                    'disable_hapus' => $sudah_sampel
                ];
            }
        }
        
        // Query 2: Permintaan MB tanpa detail (hanya dari permintaan_pemeriksaan_labmb)
        $query_tanpa_detail = "SELECT 
                            pl.noorder,
                            pl.no_rawat,
                            pl.tgl_permintaan,
                            pl.jam_permintaan,
                            pl.tgl_sampel,
                            pl.informasi_tambahan,
                            pl.diagnosa_klinis,
                            pl.dokter_perujuk,
                            IFNULL(d.nm_dokter, '-') as nm_dokter,
                            ppl.kd_jenis_prw,
                            jpl.nm_perawatan as nm_template
                          FROM permintaan_labmb pl
                          LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
                          INNER JOIN permintaan_pemeriksaan_labmb ppl ON pl.noorder = ppl.noorder
                          INNER JOIN jns_perawatan_lab jpl ON ppl.kd_jenis_prw = jpl.kd_jenis_prw
                          WHERE pl.no_rawat = '$norawat'
                          ORDER BY pl.tgl_permintaan DESC, pl.jam_permintaan DESC, ppl.kd_jenis_prw";
        
        $result_tanpa_detail = bukaquery($query_tanpa_detail);
        
        if ($result_tanpa_detail && mysqli_num_rows($result_tanpa_detail) > 0) {
            while ($row = mysqli_fetch_array($result_tanpa_detail)) {
                // Skip jika noorder ini sudah ada di data dengan detail
                if (isset($noorder_dengan_detail[$row['noorder']])) {
                    continue;
                }
                
                $tgl_sampel = trim($row['tgl_sampel']);
                $sudah_sampel = (!empty($tgl_sampel) && $tgl_sampel != '0000-00-00');
                
                $data[] = [
                    'noorder' => $row['noorder'],
                    'no_rawat' => $row['no_rawat'],
                    'tgl_permintaan' => $row['tgl_permintaan'],
                    'jam_permintaan' => $row['jam_permintaan'],
                    'tgl_sampel' => $tgl_sampel,
                    'dokter_perujuk' => $row['dokter_perujuk'],
                    'nm_dokter' => $row['nm_dokter'],
                    'kd_jenis_prw' => $row['kd_jenis_prw'],
                    'nm_template' => $row['nm_template'],
                    'id_template' => null,
                    'pemeriksaan' => $row['nm_template'],
                    'satuan' => '-',
                    'nilai_rujukan' => '-',
                    'diagnosa_klinis' => !empty($row['diagnosa_klinis']) ? $row['diagnosa_klinis'] : '-',
                    'informasi_tambahan' => !empty($row['informasi_tambahan']) ? $row['informasi_tambahan'] : '-',
                    'status' => $sudah_sampel ? 'Sudah Diambil' : 'Belum Diambil',
                    'disable_hapus' => $sudah_sampel
                ];
            }
        }
        
        // Sort data berdasarkan tanggal terbaru
        usort($data, function($a, $b) {
            $dateA = $a['tgl_permintaan'] . ' ' . $a['jam_permintaan'];
            $dateB = $b['tgl_permintaan'] . ' ' . $b['jam_permintaan'];
            return strcmp($dateB, $dateA); // DESC
        });
        
    } else {
        // Query untuk PK - tabel permintaan_lab + permintaan_pemeriksaan_lab
        // Gabungkan data dari template dengan detail DAN template tanpa detail
        
        // Query 1: Permintaan PK yang punya detail (dari permintaan_detail_permintaan_lab)
        $query_dengan_detail = "SELECT 
                            pl.noorder,
                            pl.no_rawat,
                            pl.tgl_permintaan,
                            pl.jam_permintaan,
                            pl.tgl_sampel,
                            pl.informasi_tambahan,
                            pl.diagnosa_klinis,
                            pl.dokter_perujuk,
                            IFNULL(d.nm_dokter, '-') as nm_dokter,
                            pld.kd_jenis_prw,
                            jpl.nm_perawatan as nm_template,
                            pld.id_template,
                            tl.Pemeriksaan as nama_pemeriksaan,
                            tl.satuan,
                            tl.nilai_rujukan_ld,
                            tl.nilai_rujukan_la,
                            tl.nilai_rujukan_pd,
                            tl.nilai_rujukan_pa
                          FROM permintaan_lab pl
                          LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
                          INNER JOIN permintaan_detail_permintaan_lab pld ON pl.noorder = pld.noorder
                          INNER JOIN jns_perawatan_lab jpl ON pld.kd_jenis_prw = jpl.kd_jenis_prw
                          INNER JOIN template_laboratorium tl ON pld.id_template = tl.id_template
                          WHERE pl.no_rawat = '$norawat'
                          ORDER BY pl.tgl_permintaan DESC, pl.jam_permintaan DESC, pld.kd_jenis_prw, tl.Pemeriksaan";
        
        $result_dengan_detail = bukaquery($query_dengan_detail);
        
        // Kumpulkan noorder yang sudah punya detail
        $noorder_dengan_detail = [];
        
        if ($result_dengan_detail && mysqli_num_rows($result_dengan_detail) > 0) {
            while ($row = mysqli_fetch_array($result_dengan_detail)) {
                $noorder_dengan_detail[$row['noorder']] = true;
                
                $nilai_parts = [];
                if (!empty($row['nilai_rujukan_ld'])) $nilai_parts[] = 'LD:' . $row['nilai_rujukan_ld'];
                if (!empty($row['nilai_rujukan_la'])) $nilai_parts[] = 'LA:' . $row['nilai_rujukan_la'];
                if (!empty($row['nilai_rujukan_pd'])) $nilai_parts[] = 'PD:' . $row['nilai_rujukan_pd'];
                if (!empty($row['nilai_rujukan_pa'])) $nilai_parts[] = 'PA:' . $row['nilai_rujukan_pa'];
                
                $tgl_sampel = trim($row['tgl_sampel']);
                $sudah_sampel = (!empty($tgl_sampel) && $tgl_sampel != '0000-00-00');
                
                $data[] = [
                    'noorder' => $row['noorder'],
                    'no_rawat' => $row['no_rawat'],
                    'tgl_permintaan' => $row['tgl_permintaan'],
                    'jam_permintaan' => $row['jam_permintaan'],
                    'tgl_sampel' => $tgl_sampel,
                    'dokter_perujuk' => $row['dokter_perujuk'],
                    'nm_dokter' => $row['nm_dokter'],
                    'kd_jenis_prw' => $row['kd_jenis_prw'],
                    'nm_template' => $row['nm_template'],
                    'id_template' => $row['id_template'],
                    'pemeriksaan' => $row['nama_pemeriksaan'],
                    'satuan' => !empty($row['satuan']) ? $row['satuan'] : '-',
                    'nilai_rujukan' => !empty($nilai_parts) ? implode(', ', $nilai_parts) : '-',
                    'diagnosa_klinis' => !empty($row['diagnosa_klinis']) ? $row['diagnosa_klinis'] : '-',
                    'informasi_tambahan' => !empty($row['informasi_tambahan']) ? $row['informasi_tambahan'] : '-',
                    'status' => $sudah_sampel ? 'Sudah Diambil' : 'Belum Diambil',
                    'disable_hapus' => $sudah_sampel
                ];
            }
        }
        
        // Query 2: Permintaan PK tanpa detail (hanya dari permintaan_pemeriksaan_lab)
        // Ini untuk template yang tidak punya detail items
        $query_tanpa_detail = "SELECT 
                            pl.noorder,
                            pl.no_rawat,
                            pl.tgl_permintaan,
                            pl.jam_permintaan,
                            pl.tgl_sampel,
                            pl.informasi_tambahan,
                            pl.diagnosa_klinis,
                            pl.dokter_perujuk,
                            IFNULL(d.nm_dokter, '-') as nm_dokter,
                            ppl.kd_jenis_prw,
                            jpl.nm_perawatan as nm_template
                          FROM permintaan_lab pl
                          LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
                          INNER JOIN permintaan_pemeriksaan_lab ppl ON pl.noorder = ppl.noorder
                          INNER JOIN jns_perawatan_lab jpl ON ppl.kd_jenis_prw = jpl.kd_jenis_prw
                          WHERE pl.no_rawat = '$norawat'
                          ORDER BY pl.tgl_permintaan DESC, pl.jam_permintaan DESC, ppl.kd_jenis_prw";
        
        $result_tanpa_detail = bukaquery($query_tanpa_detail);
        
        if ($result_tanpa_detail && mysqli_num_rows($result_tanpa_detail) > 0) {
            while ($row = mysqli_fetch_array($result_tanpa_detail)) {
                // Skip jika noorder ini sudah ada di data dengan detail
                // (untuk menghindari duplikasi)
                if (isset($noorder_dengan_detail[$row['noorder']])) {
                    continue;
                }
                
                $tgl_sampel = trim($row['tgl_sampel']);
                $sudah_sampel = (!empty($tgl_sampel) && $tgl_sampel != '0000-00-00');
                
                $data[] = [
                    'noorder' => $row['noorder'],
                    'no_rawat' => $row['no_rawat'],
                    'tgl_permintaan' => $row['tgl_permintaan'],
                    'jam_permintaan' => $row['jam_permintaan'],
                    'tgl_sampel' => $tgl_sampel,
                    'dokter_perujuk' => $row['dokter_perujuk'],
                    'nm_dokter' => $row['nm_dokter'],
                    'kd_jenis_prw' => $row['kd_jenis_prw'],
                    'nm_template' => $row['nm_template'],
                    'id_template' => null,
                    'pemeriksaan' => $row['nm_template'], // Gunakan nama template sebagai nama pemeriksaan
                    'satuan' => '-',
                    'nilai_rujukan' => '-',
                    'diagnosa_klinis' => !empty($row['diagnosa_klinis']) ? $row['diagnosa_klinis'] : '-',
                    'informasi_tambahan' => !empty($row['informasi_tambahan']) ? $row['informasi_tambahan'] : '-',
                    'status' => $sudah_sampel ? 'Sudah Diambil' : 'Belum Diambil',
                    'disable_hapus' => $sudah_sampel
                ];
            }
        }
        
        // Sort data berdasarkan tanggal terbaru
        usort($data, function($a, $b) {
            $dateA = $a['tgl_permintaan'] . ' ' . $a['jam_permintaan'];
            $dateB = $b['tgl_permintaan'] . ' ' . $b['jam_permintaan'];
            return strcmp($dateB, $dateA); // DESC
        });
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $data,
        'total' => count($data),
        'kategori' => $kategori
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit();
?>
