<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once('../conf/conf.php');
require_once('jurnal.php'); 

// Set timezone Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

// Matikan display error agar tidak merusak JSON
ini_set('display_errors', 0);
error_reporting(0);

// Set header JSON
header('Content-Type: application/json');

// Validasi session
if (!isset($_SESSION["ses_dokter"])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Session expired atau belum login'
    ]);
    exit();
}

// Ambil aksi dari request
$aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

// ========================================
// FUNGSI TRACKING (FORMAT RINGKAS + CLEAN)
// ========================================
function insertTracker($full_query) {
    $user = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    
    // Gunakan waktu server SEKARANG dengan timezone yang sudah diset
    $tanggal = date('Y-m-d H:i:s');
    
    $query_clean = $full_query;
    
    // Untuk INSERT: ambil "insert into nama_tabel VALUES (...)"
    if (stripos($query_clean, 'INSERT INTO') !== false) {
        preg_match('/INSERT INTO\s+(\w+)\s*\(/i', $query_clean, $matches);
        $table_name = isset($matches[1]) ? $matches[1] : '';
        
        $values_pos = stripos($query_clean, 'VALUES');
        if ($values_pos !== false && !empty($table_name)) {
            $values_part = trim(substr($query_clean, $values_pos));
            $query_clean = "E-Dokter insert into {$table_name} {$values_part}";
        }
    }
    // Untuk UPDATE: "update nama_tabel set ..."
    elseif (stripos($query_clean, 'UPDATE') !== false) {
        $query_clean = "E-Dokter " . trim($query_clean);
        // Ubah jadi lowercase
        $query_clean = preg_replace('/UPDATE/i', 'update', $query_clean);
        $query_clean = preg_replace('/SET/i', 'set', $query_clean);
        $query_clean = preg_replace('/WHERE/i', 'where', $query_clean);
    }
    // Untuk DELETE: "delete from nama_tabel where ..."
    elseif (stripos($query_clean, 'DELETE') !== false) {
        $query_clean = trim($query_clean);
        // Ubah jadi lowercase
        $query_clean = preg_replace('/DELETE/i', 'delete', $query_clean);
        $query_clean = preg_replace('/FROM/i', 'from', $query_clean);
        $query_clean = preg_replace('/WHERE/i', 'where', $query_clean);
        // Tambah prefix E-Dokter
        $query_clean = "E-Dokter " . $query_clean;
    }
    
    // Hapus spasi berlebih
    $query_clean = preg_replace('/\s+/', ' ', $query_clean);
    $query_clean = preg_replace('/\s*\(\s*/', '(', $query_clean);
    $query_clean = preg_replace('/\s*\)\s*/', ')', $query_clean);
    $query_clean = preg_replace('/\s*,\s*/', ',', $query_clean);
    $query_clean = trim($query_clean);
    
    // Escape single quote
    $query_escaped = str_replace("'", "''", $query_clean);
    
    // Insert ke trackersql
    $query = "INSERT INTO trackersql (tanggal, sqle, usere) 
              VALUES ('$tanggal', '$query_escaped', '$user')";
    
    bukaquery($query);
}

// ========================================
// PROSES SIMPAN RADIOLOGI
// ========================================
if ($aksi === 'simpan_radiologi') {
    
    try {
        // Ambil data dari POST
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $norm = isset($_POST['norm']) ? validTeks4($_POST['norm'], 20) : '';
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : '';
        $jam = isset($_POST['jam']) ? $_POST['jam'] : '';
        $indikasi = isset($_POST['indikasi']) ? validTeks4($_POST['indikasi'], 80) : '';
        $info_tambahan = isset($_POST['info_tambahan']) ? validTeks4($_POST['info_tambahan'], 60) : '';
        $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
        
        // Validasi input
        if (empty($norawat) || empty($tanggal) || empty($jam) || empty($items)) {
            throw new Exception('Data tidak lengkap. Pastikan semua field terisi.');
        }
        
        // Ambil kode dokter dari session
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        // Generate noorder otomatis: PR(tahun)(bulan)(tanggal)(nomor urut)
        $tahun = date('Y', strtotime($tanggal));
        $bulan = date('m', strtotime($tanggal));
        $tanggal_only = date('d', strtotime($tanggal));
        $prefix = "PR{$tahun}{$bulan}{$tanggal_only}";
        
        // Cari nomor urut terakhir hari ini
        $query_last = "SELECT noorder FROM permintaan_radiologi 
                       WHERE noorder LIKE '{$prefix}%' 
                       ORDER BY noorder DESC LIMIT 1";
        $result_last = bukaquery($query_last);
        
        if (mysqli_num_rows($result_last) > 0) {
            $row = mysqli_fetch_assoc($result_last);
            $last_noorder = $row['noorder'];
            $last_number = intval(substr($last_noorder, -4));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        $noorder = $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
        
        // INSERT HEADER
        $query_insert_header = "INSERT INTO permintaan_radiologi 
            (noorder, no_rawat, tgl_permintaan, jam_permintaan, 
             tgl_sampel, jam_sampel, tgl_hasil, jam_hasil, 
             dokter_perujuk, status, informasi_tambahan, diagnosa_klinis) 
            VALUES 
            ('{$noorder}', '{$norawat}', '{$tanggal}', '{$jam}', 
             '0000-00-00', '00:00:00', '0000-00-00', '00:00:00', 
             '{$kd_dokter}', 'ralan', '{$info_tambahan}', '{$indikasi}')";
        
        $result_header = bukaquery($query_insert_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menyimpan permintaan radiologi');
        }
        
        // TRACKING: Simpan query header
        insertTracker($query_insert_header);
        
        // INSERT DETAIL - Loop untuk setiap pemeriksaan
        $success_count = 0;
        foreach ($items as $item) {
            $kd_jenis_prw = $item['kode'];
            
            $query_insert_detail = "INSERT INTO permintaan_pemeriksaan_radiologi 
                (noorder, kd_jenis_prw, stts_bayar) 
                VALUES 
                ('{$noorder}', '{$kd_jenis_prw}', 'Belum')";
            
            $result_detail = bukaquery($query_insert_detail);
            
            if (!$result_detail) {
                throw new Exception('Gagal menyimpan detail pemeriksaan');
            }
            
            // TRACKING: Simpan setiap query detail
            insertTracker($query_insert_detail);
            
            $success_count++;
        }
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => "Berhasil menyimpan {$success_count} permintaan radiologi",
            'noorder' => $noorder,
            'count' => $success_count
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
// PROSES HAPUS RADIOLOGI
// ========================================
if ($aksi === 'hapus_radiologi') {
    
    try {
        // Ambil noorder
        $noorder = isset($_POST['noorder']) ? validTeks4($_POST['noorder'], 20) : '';
        
        // Validasi input
        if (empty($noorder)) {
            throw new Exception('No. Order tidak valid');
        }
        
        // Cek apakah sudah diambil sampel
        $query_cek = "SELECT tgl_sampel FROM permintaan_radiologi WHERE noorder = '$noorder' LIMIT 1";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) > 0) {
            $row = mysqli_fetch_assoc($result_cek);
            if ($row['tgl_sampel'] != '0000-00-00' && !empty($row['tgl_sampel'])) {
                throw new Exception('Tidak bisa dihapus! Sampel sudah diambil.');
            }
        } else {
            throw new Exception('Data tidak ditemukan');
        }
        
        // Hapus dari tabel detail dulu
        $query_hapus_detail = "DELETE FROM permintaan_pemeriksaan_radiologi WHERE noorder = '$noorder'";
        $result_detail = bukaquery($query_hapus_detail);
        
        if (!$result_detail) {
            throw new Exception('Gagal menghapus detail pemeriksaan');
        }
        
        // TRACKING: Simpan query delete detail
        insertTracker($query_hapus_detail);
        
        // Hapus dari tabel header
        $query_hapus_header = "DELETE FROM permintaan_radiologi WHERE noorder = '$noorder'";
        $result_header = bukaquery($query_hapus_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menghapus permintaan radiologi');
        }
        
        // TRACKING: Simpan query delete header
        insertTracker($query_hapus_header);
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil menghapus permintaan radiologi'
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
// PROSES SIMPAN LABORATORIUM (MULTI-KATEGORI: PK, PA, MB)
// ========================================
if ($aksi === 'simpan_laboratorium') {
    
    try {
        // Ambil data dari POST
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $kategori = isset($_POST['kategori']) ? strtoupper($_POST['kategori']) : 'PK';
        $tanggal = isset($_POST['tgl_permintaan']) ? $_POST['tgl_permintaan'] : '';
        $jam = isset($_POST['jam_permintaan']) ? $_POST['jam_permintaan'] : '';
        $diagnosa_klinis = isset($_POST['diagnosa_klinis']) ? validTeks4($_POST['diagnosa_klinis'], 200) : '';
        $informasi_tambahan = isset($_POST['informasi_tambahan']) ? validTeks4($_POST['informasi_tambahan'], 200) : '';
        $pemeriksaan = isset($_POST['pemeriksaan']) ? json_decode($_POST['pemeriksaan'], true) : [];
        
        // Data khusus PA
        $pengambilan_bahan = isset($_POST['pengambilan_bahan']) ? $_POST['pengambilan_bahan'] : '';
        $diperoleh_dengan = isset($_POST['diperoleh_dengan']) ? validTeks4($_POST['diperoleh_dengan'], 40) : '';
        $lokasi_jaringan = isset($_POST['lokasi_jaringan']) ? validTeks4($_POST['lokasi_jaringan'], 40) : '';
        $diawetkan_dengan = isset($_POST['diawetkan_dengan']) ? validTeks4($_POST['diawetkan_dengan'], 40) : '';
        $pernah_dilakukan_di = isset($_POST['pernah_dilakukan_di']) ? validTeks4($_POST['pernah_dilakukan_di'], 100) : '';
        $tanggal_pa_sebelumnya = isset($_POST['tanggal_pa_sebelumnya']) ? $_POST['tanggal_pa_sebelumnya'] : '';
        $nomor_pa_sebelumnya = isset($_POST['nomor_pa_sebelumnya']) ? validTeks4($_POST['nomor_pa_sebelumnya'], 20) : '';
        $diagnosa_pa_sebelumnya = isset($_POST['diagnosa_pa_sebelumnya']) ? validTeks4($_POST['diagnosa_pa_sebelumnya'], 100) : '';
        
        // Validasi input
        if (empty($norawat) || empty($tanggal) || empty($jam) || empty($pemeriksaan)) {
            throw new Exception('Data tidak lengkap. Pastikan semua field terisi.');
        }
        
        // Validasi kategori
        if (!in_array($kategori, ['PK', 'PA', 'MB'])) {
            $kategori = 'PK';
        }
        
        // Ambil kode dokter dari session
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        // Generate noorder berdasarkan kategori
        $tahun = date('Y', strtotime($tanggal));
        $bulan = date('m', strtotime($tanggal));
        $tanggal_only = date('d', strtotime($tanggal));
        $prefix = "{$kategori}{$tahun}{$bulan}{$tanggal_only}";
        
        // Tentukan tabel berdasarkan kategori
        $tabel_header = 'permintaan_lab';
        if ($kategori === 'PA') $tabel_header = 'permintaan_labpa';
        elseif ($kategori === 'MB') $tabel_header = 'permintaan_labmb';
        
        // Cari nomor urut terakhir hari ini
        $query_last = "SELECT noorder FROM {$tabel_header} 
                       WHERE noorder LIKE '{$prefix}%' 
                       ORDER BY noorder DESC LIMIT 1";
        $result_last = bukaquery($query_last);
        
        if ($result_last && mysqli_num_rows($result_last) > 0) {
            $row = mysqli_fetch_assoc($result_last);
            $last_noorder = $row['noorder'];
            $last_number = intval(substr($last_noorder, -4));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        $noorder = $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
        
        // INSERT BERDASARKAN KATEGORI
        if ($kategori === 'PA') {
            // INSERT ke tabel permintaan_labpa
            $query_insert_header = "INSERT INTO permintaan_labpa 
                (noorder, no_rawat, tgl_permintaan, jam_permintaan, 
                 tgl_sampel, jam_sampel, tgl_hasil, jam_hasil, 
                 dokter_perujuk, status, informasi_tambahan, diagnosa_klinis,
                 pengambilan_bahan, diperoleh_dengan, lokasi_jaringan, diawetkan_dengan,
                 pernah_dilakukan_di, tanggal_pa_sebelumnya, nomor_pa_sebelumnya, diagnosa_pa_sebelumnya) 
                VALUES 
                ('{$noorder}', '{$norawat}', '{$tanggal}', '{$jam}', 
                 '0000-00-00', '00:00:00', '0000-00-00', '00:00:00', 
                 '{$kd_dokter}', 'ralan', '{$informasi_tambahan}', '{$diagnosa_klinis}',
                 " . (!empty($pengambilan_bahan) ? "'{$pengambilan_bahan}'" : "NULL") . ", 
                 '{$diperoleh_dengan}', '{$lokasi_jaringan}', '{$diawetkan_dengan}',
                 '{$pernah_dilakukan_di}', 
                 " . (!empty($tanggal_pa_sebelumnya) ? "'{$tanggal_pa_sebelumnya}'" : "NULL") . ",
                 '{$nomor_pa_sebelumnya}', '{$diagnosa_pa_sebelumnya}')";
        } elseif ($kategori === 'MB') {
            // INSERT ke tabel permintaan_labmb
            $query_insert_header = "INSERT INTO permintaan_labmb 
                (noorder, no_rawat, tgl_permintaan, jam_permintaan, 
                 tgl_sampel, jam_sampel, tgl_hasil, jam_hasil, 
                 dokter_perujuk, status, informasi_tambahan, diagnosa_klinis) 
                VALUES 
                ('{$noorder}', '{$norawat}', '{$tanggal}', '{$jam}', 
                 '0000-00-00', '00:00:00', '0000-00-00', '00:00:00', 
                 '{$kd_dokter}', 'ralan', '{$informasi_tambahan}', '{$diagnosa_klinis}')";
        } else {
            // INSERT ke tabel permintaan_lab (PK - default)
            $query_insert_header = "INSERT INTO permintaan_lab 
                (noorder, no_rawat, tgl_permintaan, jam_permintaan, 
                 tgl_sampel, jam_sampel, tgl_hasil, jam_hasil, 
                 dokter_perujuk, status, informasi_tambahan, diagnosa_klinis) 
                VALUES 
                ('{$noorder}', '{$norawat}', '{$tanggal}', '{$jam}', 
                 '0000-00-00', '00:00:00', '0000-00-00', '00:00:00', 
                 '{$kd_dokter}', 'ralan', '{$informasi_tambahan}', '{$diagnosa_klinis}')";
        }
        
        $result_header = bukaquery($query_insert_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menyimpan permintaan laboratorium ' . $kategori);
        }
        
        insertTracker($query_insert_header);
        
        // Tentukan tabel pemeriksaan berdasarkan kategori
        $tabel_pemeriksaan = 'permintaan_pemeriksaan_lab';
        if ($kategori === 'PA') $tabel_pemeriksaan = 'permintaan_pemeriksaan_labpa';
        elseif ($kategori === 'MB') $tabel_pemeriksaan = 'permintaan_pemeriksaan_labmb';
        
        // Group pemeriksaan
        $grouped_kode = [];
        foreach ($pemeriksaan as $item) {
            $kd_jenis_prw = $item['kode'];
            if (!in_array($kd_jenis_prw, $grouped_kode)) {
                $grouped_kode[] = $kd_jenis_prw;
            }
        }
        
        // INSERT ke tabel permintaan_pemeriksaan sesuai kategori
        $success_count = 0;
        foreach ($grouped_kode as $kd_jenis_prw) {
            $query_insert_pemeriksaan = "INSERT INTO {$tabel_pemeriksaan} 
                (noorder, kd_jenis_prw, stts_bayar) 
                VALUES 
                ('{$noorder}', '{$kd_jenis_prw}', 'Belum')";
            
            $result_pemeriksaan = bukaquery($query_insert_pemeriksaan);
            if ($result_pemeriksaan) {
                insertTracker($query_insert_pemeriksaan);
                $success_count++;
            }
        }
        
        if ($success_count == 0) {
            throw new Exception('Tidak ada pemeriksaan yang berhasil disimpan');
        }
        
        // ====================================================
        // INSERT DETAIL PERMINTAAN LAB PK ke permintaan_detail_permintaan_lab
        // ====================================================
        if ($kategori === 'PK') {
            foreach ($pemeriksaan as $item) {
                $kd_jenis_prw = addslashes($item['kode']);
                $nama_pemeriksaan = isset($item['pemeriksaan']) ? addslashes($item['pemeriksaan']) : '';
                
                if (!empty($nama_pemeriksaan)) {
                    // Cari id_template dari template_laboratorium berdasarkan kd_jenis_prw dan nama pemeriksaan
                    $query_cari_template = "SELECT id_template FROM template_laboratorium 
                                            WHERE kd_jenis_prw = '{$kd_jenis_prw}' 
                                            AND Pemeriksaan = '{$nama_pemeriksaan}' 
                                            LIMIT 1";
                    $result_template = bukaquery($query_cari_template);
                    
                    if ($result_template && mysqli_num_rows($result_template) > 0) {
                        $row_template = mysqli_fetch_assoc($result_template);
                        $id_template = $row_template['id_template'];
                        
                        // Insert ke permintaan_detail_permintaan_lab
                        $query_insert_detail = "INSERT INTO permintaan_detail_permintaan_lab 
                            (noorder, kd_jenis_prw, id_template, stts_bayar) 
                            VALUES 
                            ('{$noorder}', '{$kd_jenis_prw}', '{$id_template}', 'Belum')";
                        
                        $result_detail = bukaquery($query_insert_detail);
                        if ($result_detail) {
                            insertTracker($query_insert_detail);
                        }
                    }
                }
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => "Berhasil menyimpan {$success_count} permintaan laboratorium {$kategori}",
            'noorder' => $noorder,
            'count' => $success_count,
            'kategori' => $kategori
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
// PROSES HAPUS LABORATORIUM (MULTI-KATEGORI: PK, PA, MB)
// ========================================
if ($aksi === 'hapus_laboratorium') {
    
    try {
        // Ambil noorder dan kategori
        $noorder = isset($_POST['noorder']) ? validTeks4($_POST['noorder'], 20) : '';
        $kategori = isset($_POST['kategori']) ? strtoupper($_POST['kategori']) : 'PK';
        
        // Validasi input
        if (empty($noorder)) {
            throw new Exception('No. Order tidak valid');
        }
        
        // Validasi kategori
        if (!in_array($kategori, ['PK', 'PA', 'MB'])) {
            $kategori = 'PK';
        }
        
        // Tentukan tabel berdasarkan kategori
        $tabel_header = 'permintaan_lab';
        if ($kategori === 'PA') $tabel_header = 'permintaan_labpa';
        elseif ($kategori === 'MB') $tabel_header = 'permintaan_labmb';
        
        // Cek apakah sudah diambil sampel
        $query_cek = "SELECT tgl_sampel FROM {$tabel_header} WHERE noorder = '$noorder' LIMIT 1";
        $result_cek = bukaquery($query_cek);
        
        if ($result_cek && mysqli_num_rows($result_cek) > 0) {
            $row = mysqli_fetch_assoc($result_cek);
            if ($row['tgl_sampel'] != '0000-00-00' && !empty($row['tgl_sampel'])) {
                throw new Exception('Tidak bisa dihapus! Sampel sudah diambil.');
            }
        } else {
            throw new Exception('Data tidak ditemukan');
        }
        
        // Tentukan tabel pemeriksaan berdasarkan kategori
        $tabel_pemeriksaan = 'permintaan_pemeriksaan_lab';
        if ($kategori === 'PA') $tabel_pemeriksaan = 'permintaan_pemeriksaan_labpa';
        elseif ($kategori === 'MB') $tabel_pemeriksaan = 'permintaan_pemeriksaan_labmb';
        
        // Hapus dari tabel pemeriksaan sesuai kategori
        $query_hapus_pemeriksaan = "DELETE FROM {$tabel_pemeriksaan} WHERE noorder = '$noorder'";
        $result_pemeriksaan = bukaquery($query_hapus_pemeriksaan);
        if ($result_pemeriksaan) insertTracker($query_hapus_pemeriksaan);
        
        // Hapus dari tabel detail permintaan lab (untuk PK)
        if ($kategori === 'PK') {
            $query_hapus_detail = "DELETE FROM permintaan_detail_permintaan_lab WHERE noorder = '$noorder'";
            $result_hapus_detail = bukaquery($query_hapus_detail);
            if ($result_hapus_detail) insertTracker($query_hapus_detail);
        }
        
        // Hapus dari tabel header sesuai kategori
        $query_hapus_header = "DELETE FROM {$tabel_header} WHERE noorder = '$noorder'";
        $result_header = bukaquery($query_hapus_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menghapus permintaan laboratorium');
        }
        
        insertTracker($query_hapus_header);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil menghapus permintaan laboratorium ' . $kategori
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
// PROSES SIMPAN DIAGNOSA
// ========================================
if ($aksi === 'simpan_diagnosa') {
    
    try {
        // Ambil data dari POST
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $kd_penyakit = isset($_POST['kd_penyakit']) ? validTeks4($_POST['kd_penyakit'], 10) : '';
        $status = isset($_POST['status']) ? validTeks4($_POST['status'], 10) : '';
        $prioritas = isset($_POST['prioritas']) ? intval($_POST['prioritas']) : 0;
        
        // Validasi input
        if (empty($norawat) || empty($kd_penyakit) || empty($status) || $prioritas < 1) {
            throw new Exception('Data tidak lengkap');
        }
        
        // Validasi status harus Ralan atau Ranap
        if (!in_array($status, ['Ralan', 'Ranap'])) {
            throw new Exception('Status tidak valid');
        }
        
        // Cek apakah diagnosa sudah pernah ada (untuk status_penyakit)
        $query_cek = "SELECT kd_penyakit FROM diagnosa_pasien 
                      WHERE no_rawat = '$norawat' 
                      AND kd_penyakit = '$kd_penyakit' 
                      LIMIT 1";
        $result_cek = bukaquery($query_cek);
        
        $status_penyakit = 'Baru';
        if (mysqli_num_rows($result_cek) > 0) {
            $status_penyakit = 'Lama';
        }
        
        // Insert diagnosa
        $query_insert = "INSERT INTO diagnosa_pasien 
            (no_rawat, kd_penyakit, status, prioritas, status_penyakit) 
            VALUES 
            ('$norawat', '$kd_penyakit', '$status', '$prioritas', '$status_penyakit')";
        
        $result = bukaquery($query_insert);
        
        if (!$result) {
            throw new Exception('Gagal menyimpan diagnosa');
        }
        
        // TRACKING: Simpan query
        insertTracker($query_insert);
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil menyimpan diagnosa',
            'status_penyakit' => $status_penyakit
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
// PROSES HAPUS DIAGNOSA
// ========================================
if ($aksi === 'hapus_diagnosa') {
    
    try {
        // Ambil data dari POST
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $kd_penyakit = isset($_POST['kd_penyakit']) ? validTeks4($_POST['kd_penyakit'], 10) : '';
        $prioritas = isset($_POST['prioritas']) ? intval($_POST['prioritas']) : 0;
        
        // Validasi input
        if (empty($norawat) || empty($kd_penyakit)) {
            throw new Exception('Data tidak lengkap');
        }
        
        // Hapus diagnosa
        $query_delete = "DELETE FROM diagnosa_pasien 
                        WHERE no_rawat = '$norawat' 
                        AND kd_penyakit = '$kd_penyakit' 
                        AND prioritas = '$prioritas' 
                        LIMIT 1";
        
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus diagnosa');
        }
        
        // TRACKING: Simpan query
        insertTracker($query_delete);
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil menghapus diagnosa'
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
// PROSES SIMPAN PROSEDUR
// ========================================
if ($aksi === 'simpan_prosedur') {
    
    try {
        // Ambil data dari POST
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $kode = isset($_POST['kode']) ? validTeks4($_POST['kode'], 8) : '';
        $status = isset($_POST['status']) ? validTeks4($_POST['status'], 10) : '';
        $prioritas = isset($_POST['prioritas']) ? intval($_POST['prioritas']) : 0;
        $jumlah = isset($_POST['jumlah']) ? validTeks4($_POST['jumlah'], 3) : '1'; // default 1
        
        // Validasi input
        if (empty($norawat) || empty($kode) || empty($status) || $prioritas < 1) {
            throw new Exception('Data tidak lengkap');
        }
        
        // Validasi status harus Ralan atau Ranap
        if (!in_array($status, ['Ralan', 'Ranap'])) {
            throw new Exception('Status tidak valid');
        }
        
        // ========================================
        // CEK APAKAH KOLOM 'jumlah' ADA DI TABEL
        // ========================================
        $kolom_jumlah_ada = false;
        $query_cek_kolom = "SHOW COLUMNS FROM prosedur_pasien LIKE 'jumlah'";
        $result_cek = bukaquery($query_cek_kolom);
        if (mysqli_num_rows($result_cek) > 0) {
            $kolom_jumlah_ada = true;
        }
        
        // Insert prosedur ke tabel prosedur_pasien (DINAMIS)
        if ($kolom_jumlah_ada) {
            // Jika kolom jumlah ada
            $query_insert = "INSERT INTO prosedur_pasien 
                (no_rawat, kode, status, prioritas, jumlah) 
                VALUES 
                ('$norawat', '$kode', '$status', '$prioritas', '$jumlah')";
        } else {
            // Jika kolom jumlah belum ada
            $query_insert = "INSERT INTO prosedur_pasien 
                (no_rawat, kode, status, prioritas) 
                VALUES 
                ('$norawat', '$kode', '$status', '$prioritas')";
        }
        
        $result = bukaquery($query_insert);
        
        if (!$result) {
            throw new Exception('Gagal menyimpan prosedur');
        }
        
        // TRACKING: Simpan query
        insertTracker($query_insert);
        
        // ========================================
        // UPDATE RESUME_PASIEN_RANAP (JIKA ADA)
        // ========================================
        $resume_updated = false;
        
        // Cek apakah resume sudah ada untuk no_rawat ini
        $query_cek_resume = "SELECT no_rawat FROM resume_pasien_ranap WHERE no_rawat = '$norawat' LIMIT 1";
        $result_cek_resume = bukaquery($query_cek_resume);
        
        if (mysqli_num_rows($result_cek_resume) > 0) {
            // Ambil nama prosedur dari tabel icd9
            $query_nama = "SELECT deskripsi_panjang FROM icd9 WHERE kode = '$kode' LIMIT 1";
            $result_nama = bukaquery($query_nama);
            $nm_prosedur = '';
            if (mysqli_num_rows($result_nama) > 0) {
                $row_nama = mysqli_fetch_assoc($result_nama);
                $nm_prosedur = addslashes($row_nama['deskripsi_panjang']);
            }
            
            // Tentukan kolom berdasarkan prioritas
            $kolom_prosedur = '';
            $kolom_kode = '';
            
            if ($prioritas == 1) {
                $kolom_prosedur = 'prosedur_utama';
                $kolom_kode = 'kd_prosedur_utama';
            } elseif ($prioritas == 2) {
                $kolom_prosedur = 'prosedur_sekunder';
                $kolom_kode = 'kd_prosedur_sekunder';
            } elseif ($prioritas == 3) {
                $kolom_prosedur = 'prosedur_sekunder2';
                $kolom_kode = 'kd_prosedur_sekunder2';
            } elseif ($prioritas == 4) {
                $kolom_prosedur = 'prosedur_sekunder3';
                $kolom_kode = 'kd_prosedur_sekunder3';
            }
            
            if (!empty($kolom_prosedur)) {
                $query_update_resume = "UPDATE resume_pasien_ranap SET 
                    $kolom_prosedur = '$nm_prosedur',
                    $kolom_kode = '$kode'
                    WHERE no_rawat = '$norawat'";
                
                $result_update = bukaquery($query_update_resume);
                if ($result_update) {
                    insertTracker($query_update_resume);
                    $resume_updated = true;
                }
            }
        }
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil menyimpan prosedur',
            'resume_updated' => $resume_updated,
            'kolom_jumlah_tersedia' => $kolom_jumlah_ada
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
// PROSES HAPUS PROSEDUR
// ========================================
if ($aksi === 'hapus_prosedur') {
    
    try {
        // Ambil data dari POST
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $kode = isset($_POST['kode']) ? validTeks4($_POST['kode'], 8) : '';
        $prioritas = isset($_POST['prioritas']) ? intval($_POST['prioritas']) : 0;
        
        // Validasi input
        if (empty($norawat) || empty($kode)) {
            throw new Exception('Data tidak lengkap');
        }
        
        // Hapus prosedur (tidak perlu cek kolom untuk DELETE)
        $query_delete = "DELETE FROM prosedur_pasien 
                        WHERE no_rawat = '$norawat' 
                        AND kode = '$kode' 
                        AND prioritas = '$prioritas' 
                        LIMIT 1";
        
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus prosedur');
        }
        
        // TRACKING: Simpan query
        insertTracker($query_delete);
        
        // ========================================
        // UPDATE RESUME_PASIEN_RANAP (KOSONGKAN KOLOM JIKA ADA)
        // ========================================
        $resume_updated = false;
        
        // Cek apakah resume sudah ada untuk no_rawat ini
        $query_cek_resume = "SELECT no_rawat FROM resume_pasien_ranap WHERE no_rawat = '$norawat' LIMIT 1";
        $result_cek_resume = bukaquery($query_cek_resume);
        
        if (mysqli_num_rows($result_cek_resume) > 0) {
            // Tentukan kolom berdasarkan prioritas
            $kolom_prosedur = '';
            $kolom_kode = '';
            
            if ($prioritas == 1) {
                $kolom_prosedur = 'prosedur_utama';
                $kolom_kode = 'kd_prosedur_utama';
            } elseif ($prioritas == 2) {
                $kolom_prosedur = 'prosedur_sekunder';
                $kolom_kode = 'kd_prosedur_sekunder';
            } elseif ($prioritas == 3) {
                $kolom_prosedur = 'prosedur_sekunder2';
                $kolom_kode = 'kd_prosedur_sekunder2';
            } elseif ($prioritas == 4) {
                $kolom_prosedur = 'prosedur_sekunder3';
                $kolom_kode = 'kd_prosedur_sekunder3';
            }
            
            if (!empty($kolom_prosedur)) {
                // Kosongkan kolom yang sesuai
                $query_update_resume = "UPDATE resume_pasien_ranap SET 
                    $kolom_prosedur = '',
                    $kolom_kode = ''
                    WHERE no_rawat = '$norawat'";
                
                $result_update = bukaquery($query_update_resume);
                if ($result_update) {
                    insertTracker($query_update_resume);
                    $resume_updated = true;
                }
            }
        }
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil menghapus prosedur',
            'resume_updated' => $resume_updated
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
// PROSES SIMPAN E-RESEP (LENGKAP)
// ========================================
if ($aksi === 'simpan_eresep') {
    
    try {
        // Ambil data dari POST
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $obat_non_racikan = isset($_POST['obat_non_racikan']) ? json_decode($_POST['obat_non_racikan'], true) : [];
        $obat_racikan = isset($_POST['obat_racikan']) ? json_decode($_POST['obat_racikan'], true) : [];
        
        // Validasi input
        if (empty($norawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Validasi minimal ada 1 obat (non racikan atau racikan)
        if (empty($obat_non_racikan) && empty($obat_racikan)) {
            throw new Exception('Tidak ada obat yang akan disimpan. Silakan tambahkan minimal 1 obat.');
        }
        
        // Ambil kode dokter dari session
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        // === GENERATE NO_RESEP OTOMATIS ===
        // Format: YYYYMMDD9999 (contoh: 202510280001)
        $tanggal_now = date('Ymd'); // Format: 20251028
        $prefix = $tanggal_now;
        
        // Cari nomor urut terakhir hari ini
        $query_last = "SELECT no_resep FROM resep_obat 
                       WHERE no_resep LIKE '{$prefix}%' 
                       ORDER BY no_resep DESC LIMIT 1";
        $result_last = bukaquery($query_last);
        
        if (mysqli_num_rows($result_last) > 0) {
            $row = mysqli_fetch_assoc($result_last);
            $last_noresep = $row['no_resep'];
            $last_number = intval(substr($last_noresep, -4)); // Ambil 4 digit terakhir
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        $no_resep = $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
        
        // === TANGGAL DAN JAM SEKARANG ===
        $tgl_peresepan = date('Y-m-d'); // Format: 2025-10-28
        $jam_peresepan = date('H:i:s'); // Format: 14:30:45
        
        // === INSERT KE TABEL resep_obat (HEADER) ===
        $query_insert_header = "INSERT INTO resep_obat 
            (no_resep, tgl_perawatan, jam, no_rawat, kd_dokter, 
             tgl_peresepan, jam_peresepan, status, tgl_penyerahan, jam_penyerahan) 
            VALUES 
            ('{$no_resep}', '0000-00-00', '00:00:00', '{$norawat}', '{$kd_dokter}', 
             '{$tgl_peresepan}', '{$jam_peresepan}', 'ralan', '0000-00-00', '00:00:00')";
        
        $result_header = bukaquery($query_insert_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menyimpan header resep');
        }
        
        // TRACKING: Simpan query header
        insertTracker($query_insert_header);
        
        // === SIMPAN OBAT NON RACIKAN ===
        $count_non_racikan = 0;
        
        if (!empty($obat_non_racikan)) {
            foreach ($obat_non_racikan as $obat) {
                $kode_brng = isset($obat['kode_brng']) ? validTeks4($obat['kode_brng'], 15) : '';
                $jml = isset($obat['jml']) ? floatval($obat['jml']) : 0;
                $aturan_pakai = isset($obat['aturan_pakai']) ? validTeks4($obat['aturan_pakai'], 150) : '';
                
                // Validasi
                if (empty($kode_brng) || $jml <= 0) {
                    continue; // Skip jika data tidak valid
                }
                
                // INSERT ke resep_dokter
                $query_insert_obat = "INSERT INTO resep_dokter 
                    (no_resep, kode_brng, jml, aturan_pakai) 
                    VALUES 
                    ('{$no_resep}', '{$kode_brng}', '{$jml}', '{$aturan_pakai}')";
                
                $result_obat = bukaquery($query_insert_obat);
                
                if (!$result_obat) {
                    throw new Exception('Gagal menyimpan obat non racikan: ' . $kode_brng);
                }
                
                // TRACKING
                insertTracker($query_insert_obat);
                
                $count_non_racikan++;
            }
        }
        
        // === SIMPAN OBAT RACIKAN ===
        $count_racikan = 0;
        
        if (!empty($obat_racikan)) {
            // Urutkan berdasarkan no_racik (untuk memastikan urutan 1, 2, 3, ...)
            usort($obat_racikan, function($a, $b) {
                return ($a['no_racik'] ?? 0) - ($b['no_racik'] ?? 0);
            });
            
            foreach ($obat_racikan as $racikan) {
                $no_racik = isset($racikan['no_racik']) ? intval($racikan['no_racik']) : 0;
                $nama_racik = isset($racikan['nama_racikan']) ? validTeks4($racikan['nama_racikan'], 100) : '';
                $kd_racik = isset($racikan['kd_racik']) ? validTeks4($racikan['kd_racik'], 3) : '';
                $jml_dr = isset($racikan['jumlah_racikan']) ? floatval($racikan['jumlah_racikan']) : 0;
                $aturan_pakai = isset($racikan['aturan_pakai']) ? validTeks4($racikan['aturan_pakai'], 150) : '';
                $keterangan = isset($racikan['keterangan']) ? validTeks4($racikan['keterangan'], 50) : '';
                $komposisi = isset($racikan['komposisi']) ? $racikan['komposisi'] : [];
                
                // Validasi
                if ($no_racik <= 0 || empty($nama_racik) || empty($kd_racik) || $jml_dr <= 0) {
                    continue; // Skip jika data tidak valid
                }
                
                // === INSERT KE resep_dokter_racikan (HEADER RACIKAN) ===
                $query_insert_racikan_header = "INSERT INTO resep_dokter_racikan 
                    (no_resep, no_racik, nama_racik, kd_racik, jml_dr, aturan_pakai, keterangan) 
                    VALUES 
                    ('{$no_resep}', '{$no_racik}', '{$nama_racik}', '{$kd_racik}', 
                     '{$jml_dr}', '{$aturan_pakai}', '{$keterangan}')";
                
                $result_racikan_header = bukaquery($query_insert_racikan_header);
                
                if (!$result_racikan_header) {
                    throw new Exception('Gagal menyimpan header racikan: ' . $nama_racik);
                }
                
                // TRACKING
                insertTracker($query_insert_racikan_header);
                
                // === INSERT KOMPOSISI RACIKAN (DETAIL) ===
                if (!empty($komposisi)) {
                    foreach ($komposisi as $komp) {
                        $kode_brng = isset($komp['kd_brng']) ? validTeks4($komp['kd_brng'], 15) : '';
                        $kandungan = isset($komp['dosis_diberi']) ? validTeks4($komp['dosis_diberi'], 20) : '0';
                        $jml_komposisi = isset($komp['jml_racikan']) ? floatval($komp['jml_racikan']) : 0;
                        
                        // Validasi
                        if (empty($kode_brng)) {
                            continue;
                        }
                        
                        // INSERT ke resep_dokter_racikan_detail
                        $query_insert_komposisi = "INSERT INTO resep_dokter_racikan_detail 
                            (no_resep, no_racik, kode_brng, p1, p2, kandungan, jml) 
                            VALUES 
                            ('{$no_resep}', '{$no_racik}', '{$kode_brng}', '1', '1', 
                             '{$kandungan}', '{$jml_komposisi}')";
                        
                        $result_komposisi = bukaquery($query_insert_komposisi);
                        
                        if (!$result_komposisi) {
                            throw new Exception('Gagal menyimpan komposisi racikan: ' . $kode_brng);
                        }
                        
                        // TRACKING
                        insertTracker($query_insert_komposisi);
                    }
                }
                
                $count_racikan++;
            }
        }
        
// === UPDATE KOLOM RTL DI TABEL pemeriksaan_ralan ===
// Cek apakah data pemeriksaan ada dengan no_rawat dan nip (kd_dokter) yang sama
$query_cek_periksa = "SELECT no_rawat, rtl FROM pemeriksaan_ralan 
                      WHERE no_rawat = '{$norawat}' 
                      AND nip = '{$kd_dokter}'";
$result_cek = bukaquery($query_cek_periksa);

if (mysqli_num_rows($result_cek) > 0) {
    // 🔥 AMBIL RTL YANG SUDAH ADA
    $row_periksa = mysqli_fetch_assoc($result_cek);
    $rtl_existing = isset($row_periksa['rtl']) ? trim($row_periksa['rtl']) : '';
    
    // Buat format RTL (Resep)
    $rtl_text = "Resep :\n";
    
    // Tambahkan obat non racikan
    if (!empty($obat_non_racikan)) {
        foreach ($obat_non_racikan as $obat) {
            $kode_brng = isset($obat['kode_brng']) ? validTeks4($obat['kode_brng'], 15) : '';
            $jml = isset($obat['jml']) ? floatval($obat['jml']) : 0;
            $aturan_pakai = isset($obat['aturan_pakai']) ? validTeks4($obat['aturan_pakai'], 150) : '';
            
            if (empty($kode_brng) || $jml <= 0) {
                continue;
            }
            
            // Ambil nama obat dari database
            $query_nama_obat = "SELECT nama_brng FROM databarang WHERE kode_brng = '{$kode_brng}'";
            $result_nama = bukaquery($query_nama_obat);
            
            if ($result_nama && mysqli_num_rows($result_nama) > 0) {
                $row_obat = mysqli_fetch_assoc($result_nama);
                $nama_obat = $row_obat['nama_brng'];
                
                $rtl_text .= "{$nama_obat} Jumlah {$jml} Aturan Pakai {$aturan_pakai}\n";
            }
        }
    }
    
    // Tambahkan obat racikan
    if (!empty($obat_racikan)) {
        foreach ($obat_racikan as $racikan) {
            $no_racik = isset($racikan['no_racik']) ? intval($racikan['no_racik']) : 0;
            $nama_racik = isset($racikan['nama_racikan']) ? validTeks4($racikan['nama_racikan'], 100) : '';
            $jml_dr = isset($racikan['jumlah_racikan']) ? floatval($racikan['jumlah_racikan']) : 0;
            $aturan_pakai = isset($racikan['aturan_pakai']) ? validTeks4($racikan['aturan_pakai'], 150) : '';
            $kd_racik = isset($racikan['kd_racik']) ? validTeks4($racikan['kd_racik'], 3) : '';
            $komposisi = isset($racikan['komposisi']) ? $racikan['komposisi'] : [];
            
            if ($no_racik <= 0 || empty($nama_racik) || $jml_dr <= 0) {
                continue;
            }
            
            // Ambil nama jenis racikan
            $query_jenis = "SELECT nm_racik FROM metode_racik WHERE kd_racik = '{$kd_racik}'";
            $result_jenis = bukaquery($query_jenis);
            $jenis_racikan = "Puyer"; // default
            
            if ($result_jenis && mysqli_num_rows($result_jenis) > 0) {
                $row_jenis = mysqli_fetch_assoc($result_jenis);
                $jenis_racikan = $row_jenis['nm_racik'];
            }
            
            $rtl_text .= "{$no_racik}. {$nama_racik} Jumlah {$jml_dr} {$jenis_racikan} Aturan Pakai {$aturan_pakai}\n";
            
            // Tambahkan komposisi racikan
            if (!empty($komposisi)) {
                foreach ($komposisi as $komp) {
                    $kode_brng = isset($komp['kd_brng']) ? validTeks4($komp['kd_brng'], 15) : '';
                    $jml_komposisi = isset($komp['jml_racikan']) ? floatval($komp['jml_racikan']) : 0;
                    
                    if (empty($kode_brng)) {
                        continue;
                    }
                    
                    // Ambil nama obat komposisi
                    $query_nama_komp = "SELECT nama_brng FROM databarang WHERE kode_brng = '{$kode_brng}'";
                    $result_komp = bukaquery($query_nama_komp);
                    
                    if ($result_komp && mysqli_num_rows($result_komp) > 0) {
                        $row_komp = mysqli_fetch_assoc($result_komp);
                        $nama_komp = $row_komp['nama_brng'];
                        
                        $rtl_text .= "-- {$nama_komp} {$jml_komposisi}\n";
                    }
                }
            }
        }
    }
    
    // 🔥 GABUNGKAN RTL LAMA + RESEP BARU (APPEND MODE)
    if (!empty($rtl_existing)) {
        // Jika RTL sudah ada isinya, tambahkan resep baru di bawahnya
        $rtl_combined = $rtl_existing . "\n" . $rtl_text;
    } else {
        // Jika RTL kosong, langsung isi dengan resep
        $rtl_combined = $rtl_text;
    }
    
    // Escape string untuk query - menggunakan addslashes
    $rtl_escaped = addslashes($rtl_combined);
    
    // Update kolom rtl dengan RTL GABUNGAN
    $query_update_rtl = "UPDATE pemeriksaan_ralan 
                         SET rtl = '{$rtl_escaped}' 
                         WHERE no_rawat = '{$norawat}' 
                         AND nip = '{$kd_dokter}'";
    
    $result_update = bukaquery($query_update_rtl);
    
    if ($result_update) {
        // TRACKING
        insertTracker($query_update_rtl);
    }
}

        // === SIMPAN ITERASI (JIKA ADA) ===
        if(defined('FITUR_ITERASI_RESEP') && FITUR_ITERASI_RESEP === true){
            $iterasi = isset($_POST['iterasi']) ? trim($_POST['iterasi']) : '';
            if(!empty($iterasi) && !empty($no_resep)){
                $allowed_iterasi = ['1. Iterasi 1x', '2. Iterasi 2x'];
                if(in_array($iterasi, $allowed_iterasi)){
                    $iterasi_escaped = mysqli_real_escape_string($GLOBALS['db_conn'], $iterasi);
                    $noresep_escaped = mysqli_real_escape_string($GLOBALS['db_conn'], $no_resep);
                    $query_iterasi = "INSERT INTO antrianiterasi (no_resep, status) VALUES ('{$noresep_escaped}', '{$iterasi_escaped}')";
                    bukaquery($query_iterasi);
                    insertTracker($query_iterasi);
                }
            }
        }

        // === RESPONSE SUKSES ===
        $message_parts = [];
        if ($count_non_racikan > 0) {
            $message_parts[] = "{$count_non_racikan} obat non racikan";
        }
        if ($count_racikan > 0) {
            $message_parts[] = "{$count_racikan} racikan";
        }
        
        $message = 'Berhasil menyimpan ' . implode(' dan ', $message_parts);
        
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'no_resep' => $no_resep,
            'count_non_racikan' => $count_non_racikan,
            'count_racikan' => $count_racikan
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
// PROSES HAPUS E-RESEP
// ========================================
if ($aksi === 'hapus_eresep') {
    
    try {
        // Ambil no_resep dari POST
        $no_resep = isset($_POST['no_resep']) ? validTeks4($_POST['no_resep'], 20) : '';
        
        // Validasi input
        if (empty($no_resep)) {
            throw new Exception('Nomor resep tidak valid');
        }
        
        // Ambil kode dokter dari session
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        // Cek apakah resep ada dan milik dokter yang login
        $query_cek = "SELECT no_resep, no_rawat FROM resep_obat 
                      WHERE no_resep = '{$no_resep}' 
                      AND kd_dokter = '{$kd_dokter}'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Resep tidak ditemukan atau bukan milik Anda');
        }
        
        $row_resep = mysqli_fetch_assoc($result_cek);
        $no_rawat = $row_resep['no_rawat'];
        
        // === HAPUS DATA RESEP (URUTAN PENTING: DETAIL DULU, BARU HEADER) ===
        
        // 1. Hapus detail racikan
        $query_delete_detail = "DELETE FROM resep_dokter_racikan_detail 
                                WHERE no_resep = '{$no_resep}'";
        $result_detail = bukaquery($query_delete_detail);
        insertTracker($query_delete_detail);
        
        // 2. Hapus header racikan
        $query_delete_racikan = "DELETE FROM resep_dokter_racikan 
                                 WHERE no_resep = '{$no_resep}'";
        $result_racikan = bukaquery($query_delete_racikan);
        insertTracker($query_delete_racikan);
        
        // 3. Hapus obat non racikan
        $query_delete_obat = "DELETE FROM resep_dokter 
                              WHERE no_resep = '{$no_resep}'";
        $result_obat = bukaquery($query_delete_obat);
        insertTracker($query_delete_obat);
        
        // 4. Hapus header resep
        $query_delete_header = "DELETE FROM resep_obat 
                                WHERE no_resep = '{$no_resep}'";
        $result_header = bukaquery($query_delete_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menghapus resep');
        }
        
        insertTracker($query_delete_header);
        
        // === UPDATE KOLOM RTL DI pemeriksaan_ralan (KOSONGKAN) ===
        // Cek apakah masih ada resep lain untuk no_rawat dan dokter yang sama
        $query_cek_resep_lain = "SELECT COUNT(*) as total FROM resep_obat 
                                 WHERE no_rawat = '{$no_rawat}' 
                                 AND kd_dokter = '{$kd_dokter}'";
        $result_cek_lain = bukaquery($query_cek_resep_lain);
        $row_total = mysqli_fetch_assoc($result_cek_lain);
        
        // Jika tidak ada resep lain, kosongkan kolom rtl
        if ($row_total['total'] == 0) {
            $query_update_rtl = "UPDATE pemeriksaan_ralan 
                                 SET rtl = '' 
                                 WHERE no_rawat = '{$no_rawat}' 
                                 AND nip = '{$kd_dokter}'";
            $result_update = bukaquery($query_update_rtl);
            
            if ($result_update) {
                insertTracker($query_update_rtl);
            }
        }
        
        // === RESPONSE SUKSES ===
        echo json_encode([
            'status' => 'success',
            'message' => 'Resep berhasil dihapus',
            'no_resep' => $no_resep
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

if ($aksi === 'simpan_tindakan') {
    
    try {
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $kd_jenis_prw = isset($_POST['kd_jenis_prw']) ? validTeks4($_POST['kd_jenis_prw'], 15) : '';
        $kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"], "d"), 20);
        
        if (empty($norawat) || empty($kd_jenis_prw)) {
            throw new Exception('Data tidak lengkap');
        }
        
        $tgl_perawatan = date('Y-m-d');
        $jam_rawat = date('H:i:s');
        
        // CEK DUPLICATE
        $query_cek = "SELECT kd_jenis_prw, jam_rawat 
                      FROM rawat_jl_dr 
                      WHERE no_rawat = '$norawat' 
                      AND kd_jenis_prw = '$kd_jenis_prw' 
                      AND kd_dokter = '$kd_dokter' 
                      AND tgl_perawatan = '$tgl_perawatan' 
                      LIMIT 1";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) > 0) {
            $row_cek = mysqli_fetch_assoc($result_cek);
            $jam_sebelumnya = $row_cek['jam_rawat'];
            throw new Exception("Tindakan ini sudah pernah diinput hari ini pada jam $jam_sebelumnya.");
        }
        
        // AMBIL TARIF
        $query_tarif = "SELECT material, bhp, tarif_tindakandr, kso, menejemen, total_byrdr 
                        FROM jns_perawatan 
                        WHERE kd_jenis_prw = '$kd_jenis_prw' 
                        LIMIT 1";
        $result_tarif = bukaquery($query_tarif);
        
        if (mysqli_num_rows($result_tarif) == 0) {
            throw new Exception('Kode tindakan tidak ditemukan');
        }
        
        $data_tarif = mysqli_fetch_assoc($result_tarif);
        
        $material = $data_tarif['material'] ?? 0;
        $bhp = $data_tarif['bhp'] ?? 0;
        $tarif_tindakandr = $data_tarif['tarif_tindakandr'] ?? 0;
        $kso = $data_tarif['kso'] ?? 0;
        $menejemen = $data_tarif['menejemen'] ?? 0;
        $biaya_rawat = $data_tarif['total_byrdr'] ?? 0;
        
        // INSERT TINDAKAN
        $query_insert = "INSERT INTO rawat_jl_dr 
            (no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat, 
             material, bhp, tarif_tindakandr, kso, menejemen, biaya_rawat, stts_bayar) 
            VALUES 
            ('$norawat', '$kd_jenis_prw', '$kd_dokter', '$tgl_perawatan', '$jam_rawat', 
             '$material', '$bhp', '$tarif_tindakandr', '$kso', '$menejemen', '$biaya_rawat', 'Belum')";
        
        $result = bukaquery($query_insert);
        
        if (!$result) {
            throw new Exception('Gagal menyimpan tindakan');
        }
        
        // TRACKING
        insertTracker($query_insert);
        
        // ===================================================
        // JURNAL OTOMATIS - TAMBAHKAN INI!
        // ===================================================
        $jurnal = new Jurnal();
        $jurnal_sukses = $jurnal->simpanJurnalTindakan($norawat, $kd_jenis_prw, $kd_dokter, 'U');
        
        if (!$jurnal_sukses) {
            // Log error tapi tetap sukses simpan tindakan
            error_log("Warning: Jurnal gagal dibuat - " . $jurnal->last_error);
        }
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil menyimpan tindakan',
            'biaya' => $biaya_rawat,
            'jam_rawat' => $jam_rawat,
            'jurnal' => $jurnal_sukses ? 'Jurnal dibuat: ' . $jurnal->last_no_jurnal : 'Tindakan tersimpan (jurnal skip: ' . $jurnal->last_error . ')'
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
// PROSES HAPUS TINDAKAN (WITH JURNAL PEMBALIK) - WITH DOKTER NAME
// ========================================
if ($aksi === 'hapus_tindakan') {
    
    try {
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $kd_jenis_prw = isset($_POST['kd_jenis_prw']) ? validTeks4($_POST['kd_jenis_prw'], 15) : '';
        $tgl_perawatan = isset($_POST['tgl_perawatan']) ? $_POST['tgl_perawatan'] : '';
        $jam_rawat = isset($_POST['jam_rawat']) ? $_POST['jam_rawat'] : '';
        $kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"], "d"), 20);
        
        if (empty($norawat) || empty($kd_jenis_prw) || empty($tgl_perawatan) || empty($jam_rawat)) {
            throw new Exception('Data tidak lengkap');
        }
        
        // CEK DATA MASIH ADA (SEBELUM DELETE)
        $query_cek = "SELECT r.*, j.nm_perawatan, p.nm_pasien, rp.no_rkm_medis, d.nm_dokter
                      FROM rawat_jl_dr r
                      INNER JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw
                      INNER JOIN reg_periksa rp ON r.no_rawat = rp.no_rawat
                      INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                      INNER JOIN dokter d ON r.kd_dokter = d.kd_dokter
                      WHERE r.no_rawat = '$norawat' 
                      AND r.kd_jenis_prw = '$kd_jenis_prw' 
                      AND r.kd_dokter = '$kd_dokter' 
                      AND r.tgl_perawatan = '$tgl_perawatan' 
                      AND r.jam_rawat = '$jam_rawat' 
                      LIMIT 1";
        
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) == 0) {
            throw new Exception('Data tindakan tidak ditemukan atau sudah dihapus');
        }
        
        $data_tindakan = mysqli_fetch_assoc($result_cek);
        
        // BUAT JURNAL PEMBALIK MANUAL (SEBELUM DELETE)
        $ttl_pendapatan = $data_tindakan['biaya_rawat'] ?? 0;
        $ttl_jm_dokter = $data_tindakan['tarif_tindakandr'] ?? 0;
        $ttl_kso = $data_tindakan['kso'] ?? 0;
        $ttl_menejemen = $data_tindakan['menejemen'] ?? 0;
        $ttl_jasa_sarana = $data_tindakan['material'] ?? 0;
        $ttl_bhp = $data_tindakan['bhp'] ?? 0;
        
        // Clear tampjurnal
        bukaquery("DELETE FROM tampjurnal");
        
        // Load kode rekening
        $query_rek = "SELECT 
                        Suspen_Piutang_Tindakan_Ralan,
                        Tindakan_Ralan,
                        Beban_Jasa_Medik_Dokter_Tindakan_Ralan,
                        Utang_Jasa_Medik_Dokter_Tindakan_Ralan,
                        Beban_KSO_Tindakan_Ralan,
                        Utang_KSO_Tindakan_Ralan,
                        Beban_Jasa_Sarana_Tindakan_Ralan,
                        Utang_Jasa_Sarana_Tindakan_Ralan,
                        Beban_Jasa_Menejemen_Tindakan_Ralan,
                        Utang_Jasa_Menejemen_Tindakan_Ralan,
                        HPP_BHP_Tindakan_Ralan,
                        Persediaan_BHP_Tindakan_Ralan
                      FROM set_akun_ralan LIMIT 1";
        $result_rek = bukaquery($query_rek);
        $rek = mysqli_fetch_assoc($result_rek);
        
        // Insert ke tampjurnal (POSISI DIBALIK)
        $jurnal_items = [];
        
        // Pendapatan Tindakan - DIBALIK BENAR (kebalikan dari insert)
        if ($ttl_pendapatan > 0) {
            $jurnal_items[] = "('$rek[Tindakan_Ralan]', 'Pendapatan Tindakan Rawat Jalan', $ttl_pendapatan, 0)";  // DIBALIK: Debet (tadinya Kredit)
            $jurnal_items[] = "('$rek[Suspen_Piutang_Tindakan_Ralan]', 'Suspen Piutang Tindakan Ralan', 0, $ttl_pendapatan)";  // DIBALIK: Kredit (tadinya Debet)
        }

        // Jasa Medik Dokter - DIBALIK BENAR
        if ($ttl_jm_dokter > 0) {
            $jurnal_items[] = "('$rek[Utang_Jasa_Medik_Dokter_Tindakan_Ralan]', 'Utang Jasa Medik Dokter Tindakan Ralan', $ttl_jm_dokter, 0)";  // DIBALIK: Debet
            $jurnal_items[] = "('$rek[Beban_Jasa_Medik_Dokter_Tindakan_Ralan]', 'Beban Jasa Medik Dokter Tindakan Ralan', 0, $ttl_jm_dokter)";  // DIBALIK: Kredit
        }

        // KSO - DIBALIK BENAR
        if ($ttl_kso > 0) {
            $jurnal_items[] = "('$rek[Utang_KSO_Tindakan_Ralan]', 'Utang KSO Tindakan Ralan', $ttl_kso, 0)";
            $jurnal_items[] = "('$rek[Beban_KSO_Tindakan_Ralan]', 'Beban KSO Tindakan Ralan', 0, $ttl_kso)";
        }

        // Menejemen - DIBALIK BENAR
        if ($ttl_menejemen > 0) {
            $jurnal_items[] = "('$rek[Utang_Jasa_Menejemen_Tindakan_Ralan]', 'Utang Jasa Menejemen Tindakan Ralan', $ttl_menejemen, 0)";
            $jurnal_items[] = "('$rek[Beban_Jasa_Menejemen_Tindakan_Ralan]', 'Beban Jasa Menejemen Tindakan Ralan', 0, $ttl_menejemen)";
        }

        // Jasa Sarana - DIBALIK BENAR
        if ($ttl_jasa_sarana > 0) {
            $jurnal_items[] = "('$rek[Utang_Jasa_Sarana_Tindakan_Ralan]', 'Utang Jasa Sarana Tindakan Ralan', $ttl_jasa_sarana, 0)";
            $jurnal_items[] = "('$rek[Beban_Jasa_Sarana_Tindakan_Ralan]', 'Beban Jasa Sarana Tindakan Ralan', 0, $ttl_jasa_sarana)";
        }

        // BHP - DIBALIK BENAR
        if ($ttl_bhp > 0) {
            $jurnal_items[] = "('$rek[Persediaan_BHP_Tindakan_Ralan]', 'Persediaan BHP Tindakan Ralan', $ttl_bhp, 0)";
            $jurnal_items[] = "('$rek[HPP_BHP_Tindakan_Ralan]', 'HPP BHP Tindakan Ralan', 0, $ttl_bhp)";
        }
        
        // ===================================================
        // TAMBAHAN: INSERT KE TAMPJURNAL (INI YANG KURANG!)
        // ===================================================
        if (!empty($jurnal_items)) {
            $query_insert_tampjurnal = "INSERT INTO tampjurnal (kd_rek, nm_rek, debet, kredit) VALUES " . implode(',', $jurnal_items);
            $result_tampjurnal = bukaquery($query_insert_tampjurnal);
            
            if (!$result_tampjurnal) {
                throw new Exception('Gagal insert ke tampjurnal');
            }
        }
        
        // Generate no jurnal
        $tanggal = date('Y-m-d');
        $jam = date('H:i:s');
        $prefix = "JR" . str_replace('-', '', $tanggal);
        
        $query_max = "SELECT IFNULL(MAX(CONVERT(RIGHT(no_jurnal, 6), SIGNED)), 0) as max_no
                      FROM jurnal WHERE tgl_jurnal = '$tanggal'";
        $result_max = bukaquery($query_max);
        $row_max = mysqli_fetch_assoc($result_max);
        $urut = $row_max['max_no'] + 1;
        $no_jurnal = $prefix . str_pad($urut, 6, '0', STR_PAD_LEFT);
        
        // KETERANGAN DENGAN NAMA DOKTER
        $nm_dokter = $data_tindakan['nm_dokter'];
        $keterangan = "PEMBATALAN TINDAKAN RAWAT JALAN PASIEN {$data_tindakan['no_rkm_medis']} {$data_tindakan['nm_pasien']}, DIPOSTING OLEH $nm_dokter - EDOKTER";
        
        // INSERT JURNAL
        $query_jurnal = "INSERT INTO jurnal (no_jurnal, no_bukti, tgl_jurnal, jam_jurnal, jenis, keterangan)
                         VALUES ('$no_jurnal', '$norawat', '$tanggal', '$jam', 'U', '$keterangan')";
        $result_jurnal = bukaquery($query_jurnal);
        
        if (!$result_jurnal) {
            throw new Exception('Gagal insert ke tabel jurnal');
        }
        
        // Insert detailjurnal
        $query_detail = "INSERT INTO detailjurnal (no_jurnal, kd_rek, debet, kredit)
                         SELECT '$no_jurnal', kd_rek, debet, kredit FROM tampjurnal";
        $result_detail = bukaquery($query_detail);
        
        if (!$result_detail) {
            throw new Exception('Gagal insert ke tabel detailjurnal');
        }
        
        // Clear tampjurnal
        bukaquery("DELETE FROM tampjurnal");
        
        // HAPUS TINDAKAN
        $query_delete = "DELETE FROM rawat_jl_dr 
                        WHERE no_rawat = '$norawat' 
                        AND kd_jenis_prw = '$kd_jenis_prw' 
                        AND kd_dokter = '$kd_dokter' 
                        AND tgl_perawatan = '$tgl_perawatan' 
                        AND jam_rawat = '$jam_rawat' 
                        LIMIT 1";
        
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus tindakan');
        }
        
        // TRACKING
        insertTracker($query_delete);
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil menghapus tindakan',
            'jurnal' => "Jurnal pembatalan dibuat: $no_jurnal"
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
// SIMPAN PEMERIKSAAN RALAN
// ========================================
if(isset($_POST['simpan_pemeriksaan_ralan'])) {
    $no_rawat = validTeks4($_POST['no_rawat'], 20);
    $tgl_perawatan = date('Y-m-d');
    $jam_rawat = date('H:i:s');
    
    // TTV Data
    $suhu_tubuh = validTeks4($_POST['suhu'] ?? '', 5);
    $tensi = validTeks4($_POST['tensi'] ?? '', 8);
    $nadi = validTeks4($_POST['nadi'] ?? '', 3);
    $respirasi = validTeks4($_POST['respiratory_rate'] ?? '', 3);
    $tinggi = validTeks4($_POST['tinggi'] ?? '', 5);
    $berat = validTeks4($_POST['berat'] ?? '', 5);
    $spo2 = validTeks4($_POST['spo2'] ?? '', 3);
    $gcs = validTeks4($_POST['gcs'] ?? '', 10);
    $kesadaran = validTeks4($_POST['kesadaran'] ?? '', 50);
    $alergi = validTeks4($_POST['alergi'] ?? '', 80);
    $lingkar_perut = validTeks4($_POST['lingkar_perut'] ?? '', 5);
    
    // SOAPIE Data
    $keluhan = validTeks4($_POST['subjective'] ?? '', 2000);
    $pemeriksaan = validTeks4($_POST['objective'] ?? '', 2000);
    $penilaian = validTeks4($_POST['assessment'] ?? '', 2000);
    $rtl = validTeks4($_POST['plan'] ?? '', 2000);
    $instruksi = validTeks4($_POST['intervention'] ?? '', 2000);
    $evaluasi = validTeks4($_POST['evaluation'] ?? '', 2000);
    
    // Get kode dokter dari session
    $kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"], "d"), 20);
    
    // NIP petugas (opsional, bisa diisi dengan NIP dokter atau kosong)
    $nip = $kd_dokter;
    
    // Cek apakah sudah ada data untuk no_rawat, tgl_perawatan, jam_rawat ini
    $cek_query = "SELECT * FROM pemeriksaan_ralan 
                  WHERE no_rawat = '$no_rawat' 
                  AND tgl_perawatan = '$tgl_perawatan' 
                  AND jam_rawat = '$jam_rawat'";
    $cek = bukaquery($cek_query);
    
    if(mysqli_num_rows($cek) > 0) {
        // UPDATE existing record
        $query = "UPDATE pemeriksaan_ralan SET
                    suhu_tubuh = '$suhu_tubuh',
                    tensi = '$tensi',
                    nadi = '$nadi',
                    respirasi = '$respirasi',
                    tinggi = '$tinggi',
                    berat = '$berat',
                    spo2 = '$spo2',
                    gcs = '$gcs',
                    kesadaran = '$kesadaran',
                    keluhan = '$keluhan',
                    pemeriksaan = '$pemeriksaan',
                    alergi = '$alergi',
                    lingkar_perut = '$lingkar_perut',
                    penilaian = '$penilaian',
                    rtl = '$rtl',
                    instruksi = '$instruksi',
                    evaluasi = '$evaluasi'
                  WHERE no_rawat = '$no_rawat' 
                  AND tgl_perawatan = '$tgl_perawatan' 
                  AND jam_rawat = '$jam_rawat'";
    } else {
        // INSERT new record
        $query = "INSERT INTO pemeriksaan_ralan (
                    no_rawat, tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, 
                    respirasi, tinggi, berat, spo2, gcs, kesadaran, keluhan, 
                    pemeriksaan, alergi, lingkar_perut, penilaian, rtl, 
                    instruksi, evaluasi, nip
                  ) VALUES (
                    '$no_rawat', '$tgl_perawatan', '$jam_rawat', '$suhu_tubuh', 
                    '$tensi', '$nadi', '$respirasi', '$tinggi', '$berat', 
                    '$spo2', '$gcs', '$kesadaran', '$keluhan', '$pemeriksaan', 
                    '$alergi', '$lingkar_perut', '$penilaian', '$rtl', 
                    '$instruksi', '$evaluasi', '$nip'
                  )";
    }
    
    $result = bukaquery($query);
    
    if($result) {
        echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Data pemeriksaan berhasil disimpan',
                    timer: 1500,
                    showConfirmButton: false
                }).then(function() {
                    // Reload riwayat SOAPIE
                    if(typeof SOAPIEModule !== 'undefined') {
                        SOAPIEModule.reloadRalan();
                    }
                });
              </script>";
    } else {
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Gagal menyimpan data pemeriksaan',
                    confirmButtonText: 'OK'
                });
              </script>";
    }
}

// ========================================
// HAPUS PEMERIKSAAN RALAN
// ========================================
if(isset($_POST['hapus_pemeriksaan_ralan'])) {
    $no_rawat = validTeks4($_POST['no_rawat'], 20);
    $tgl_perawatan = validTeks4($_POST['tgl_perawatan'], 10);
    $jam_rawat = validTeks4($_POST['jam_rawat'], 8);
    
    $query = "DELETE FROM pemeriksaan_ralan 
              WHERE no_rawat = '$no_rawat' 
              AND tgl_perawatan = '$tgl_perawatan' 
              AND jam_rawat = '$jam_rawat'";
    
    $result = bukaquery($query);
    
    if($result) {
        echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Data pemeriksaan berhasil dihapus',
                    timer: 1500,
                    showConfirmButton: false
                }).then(function() {
                    // Reload riwayat SOAPIE
                    if(typeof SOAPIEModule !== 'undefined') {
                        SOAPIEModule.reloadRalan();
                    }
                    // Clear form
                    document.getElementById('formPemeriksaan').reset();
                    document.getElementById('btnHapusSOAPIE').style.display = 'none';
                });
              </script>";
    } else {
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    text: 'Gagal menghapus data pemeriksaan',
                    confirmButtonText: 'OK'
                });
              </script>";
    }
}

// ========================================
// HAPUS PEMERIKSAAN RALAN (AJAX JSON)
// ========================================
if ($aksi === 'hapus_pemeriksaan') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $tgl_perawatan = isset($_POST['tgl_perawatan']) ? validTeks4($_POST['tgl_perawatan'], 10) : '';
        $jam_rawat = isset($_POST['jam_rawat']) ? validTeks4($_POST['jam_rawat'], 8) : '';
        
        if (empty($no_rawat) || empty($tgl_perawatan) || empty($jam_rawat)) {
            throw new Exception('Data tidak lengkap');
        }
        
        $query = "DELETE FROM pemeriksaan_ralan 
                  WHERE no_rawat = '$no_rawat' 
                  AND tgl_perawatan = '$tgl_perawatan' 
                  AND jam_rawat = '$jam_rawat'";
        
        $result = bukaquery($query);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data pemeriksaan');
        }
        
        // TRACKING
        insertTracker($query);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data pemeriksaan berhasil dihapus'
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
// UPDATE PEMERIKSAAN RALAN (EDIT)
// ========================================
if ($aksi === 'update_pemeriksaan') {
    try {
        // Parameter identifikasi (untuk WHERE clause)
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $tgl_perawatan_lama = isset($_POST['tgl_perawatan_lama']) ? validTeks4($_POST['tgl_perawatan_lama'], 10) : '';
        $jam_rawat_lama = isset($_POST['jam_rawat_lama']) ? validTeks4($_POST['jam_rawat_lama'], 8) : '';
        
        if (empty($no_rawat) || empty($tgl_perawatan_lama) || empty($jam_rawat_lama)) {
            throw new Exception('Data identifikasi tidak lengkap');
        }
        
        // TTV Data
        $suhu_tubuh = validTeks4($_POST['suhu'] ?? '', 5);
        $tensi = validTeks4($_POST['tensi'] ?? '', 8);
        $nadi = validTeks4($_POST['nadi'] ?? '', 3);
        $respirasi = validTeks4($_POST['respiratory_rate'] ?? '', 3);
        $tinggi = validTeks4($_POST['tinggi'] ?? '', 5);
        $berat = validTeks4($_POST['berat'] ?? '', 5);
        $spo2 = validTeks4($_POST['spo2'] ?? '', 3);
        $gcs = validTeks4($_POST['gcs'] ?? '', 10);
        $kesadaran = validTeks4($_POST['kesadaran'] ?? '', 50);
        $alergi = validTeks4($_POST['alergi'] ?? '', 80);
        $lingkar_perut = validTeks4($_POST['lingkar_perut'] ?? '', 5);
        
        // SOAPIE Data
        $keluhan = validTeks4($_POST['subjective'] ?? '', 2000);
        $pemeriksaan_fisik = validTeks4($_POST['objective'] ?? '', 2000);
        $penilaian = validTeks4($_POST['assessment'] ?? '', 2000);
        $rtl = validTeks4($_POST['plan'] ?? '', 2000);
        $instruksi = validTeks4($_POST['intervention'] ?? '', 2000);
        $evaluasi = validTeks4($_POST['evaluation'] ?? '', 2000);
        
        // Query UPDATE
        $query = "UPDATE pemeriksaan_ralan SET
                    suhu_tubuh = '$suhu_tubuh',
                    tensi = '$tensi',
                    nadi = '$nadi',
                    respirasi = '$respirasi',
                    tinggi = '$tinggi',
                    berat = '$berat',
                    spo2 = '$spo2',
                    gcs = '$gcs',
                    kesadaran = '$kesadaran',
                    keluhan = '$keluhan',
                    pemeriksaan = '$pemeriksaan_fisik',
                    alergi = '$alergi',
                    lingkar_perut = '$lingkar_perut',
                    penilaian = '$penilaian',
                    rtl = '$rtl',
                    instruksi = '$instruksi',
                    evaluasi = '$evaluasi'
                  WHERE no_rawat = '$no_rawat' 
                  AND tgl_perawatan = '$tgl_perawatan_lama' 
                  AND jam_rawat = '$jam_rawat_lama'";
        
        $result = bukaquery($query);
        
        if (!$result) {
            throw new Exception('Gagal mengupdate data pemeriksaan');
        }
        
        // TRACKING
        insertTracker($query);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data pemeriksaan berhasil diupdate'
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
// SIMPAN E-RESEP RAWAT JALAN (RALAN)
// ========================================
if ($aksi === 'simpan_eresep') {
    try {
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $obat_non_racikan = isset($_POST['obat_non_racikan']) ? json_decode($_POST['obat_non_racikan'], true) : [];
        $obat_racikan = isset($_POST['obat_racikan']) ? json_decode($_POST['obat_racikan'], true) : [];
        
        if (empty($norawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        if (empty($obat_non_racikan) && empty($obat_racikan)) {
            throw new Exception('Tidak ada obat yang akan disimpan');
        }
        
        // Get dokter info
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $tgl_peresepan = date('Y-m-d');
        $jam_peresepan = date('H:i:s');
        
        // Generate no_resep: tanggal + urutan
        $prefix = date('Ymd');
        $query_last = "SELECT no_resep FROM resep_obat WHERE no_resep LIKE '{$prefix}%' ORDER BY no_resep DESC LIMIT 1";
        $result_last = bukaquery($query_last);
        
        if (mysqli_num_rows($result_last) > 0) {
            $row = mysqli_fetch_assoc($result_last);
            $last_no = (int)substr($row['no_resep'], -6);
            $new_no = $last_no + 1;
        } else {
            $new_no = 1;
        }
        $no_resep = $prefix . str_pad($new_no, 6, '0', STR_PAD_LEFT);
        
        $count_non_racikan = 0;
        $count_racikan = 0;
        
        // Insert header ke resep_obat
        $query_header = "INSERT INTO resep_obat (no_resep, no_rawat, kd_dokter, tgl_peresepan, jam_peresepan, jam, status)
                          VALUES ('$no_resep', '$norawat', '$kd_dokter', '$tgl_peresepan', '$jam_peresepan', '$jam_peresepan', 'ralan')";
        $result_header = bukaquery($query_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menyimpan header resep');
        }
        insertTracker($query_header);
        
        // Simpan obat non racikan ke resep_dokter
        if (!empty($obat_non_racikan)) {
            foreach ($obat_non_racikan as $obat) {
                $kode_brng = validTeks4($obat['kode_brng'], 15);
                $jml = floatval($obat['jml']);
                $aturan_pakai = validTeks4($obat['aturan_pakai'], 100);
                
                $query_insert = "INSERT INTO resep_dokter (no_resep, kode_brng, jml, aturan_pakai)
                                  VALUES ('$no_resep', '$kode_brng', '$jml', '$aturan_pakai')";
                $result = bukaquery($query_insert);
                if ($result) {
                    insertTracker($query_insert);
                    $count_non_racikan++;
                }
            }
        }
        
        // Simpan obat racikan
        if (!empty($obat_racikan)) {
            foreach ($obat_racikan as $racikan) {
                $no_racik = intval($racikan['no_racik']);
                $nama_racikan = validTeks4($racikan['nama_racikan'], 100);
                $kd_racik = validTeks4($racikan['kd_racik'], 5);
                $jumlah_racikan = floatval($racikan['jumlah_racikan']);
                $aturan_pakai = validTeks4($racikan['aturan_pakai'], 100);
                $keterangan = validTeks4($racikan['keterangan'] ?? '', 200);
                
                // Insert header racikan ke resep_dokter_racikan
                $query_racikan = "INSERT INTO resep_dokter_racikan (no_resep, no_racik, nama_racik, kd_racik, jml_dr, aturan_pakai, keterangan)
                                  VALUES ('$no_resep', '$no_racik', '$nama_racikan', '$kd_racik', '$jumlah_racikan', '$aturan_pakai', '$keterangan')";
                $result_racikan = bukaquery($query_racikan);
                
                if ($result_racikan) {
                    insertTracker($query_racikan);
                    
                    // Insert komposisi racikan ke resep_dokter_racikan_detail
                    if (!empty($racikan['komposisi'])) {
                        foreach ($racikan['komposisi'] as $komp) {
                            $kd_brng = validTeks4($komp['kd_brng'], 15);
                            $p1 = floatval($komp['dosis_obat'] ?? 0);
                            $p2 = floatval($komp['dosis_diberi'] ?? 0);
                            $jml_komp = floatval($komp['jml_racikan'] ?? 0);
                            
                            $query_detail = "INSERT INTO resep_dokter_racikan_detail (no_resep, no_racik, kode_brng, p1, p2, jml)
                                              VALUES ('$no_resep', '$no_racik', '$kd_brng', '$p1', '$p2', '$jml_komp')";
                            bukaquery($query_detail);
                            insertTracker($query_detail);
                        }
                    }
                    $count_racikan++;
                }
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Resep berhasil disimpan',
            'no_resep' => $no_resep,
            'count_non_racikan' => $count_non_racikan,
            'count_racikan' => $count_racikan
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
// SIMPAN E-RESEP RAWAT INAP (RANAP) - DIPINDAHKAN KE proses3.php
// ========================================
// Handler simpan_eresep_ranap sudah dipindahkan ke proses3.php
// untuk konsistensi dengan modul rawat inap lainnya

// ========================================
// HAPUS E-RESEP RAWAT JALAN (RALAN)
// ========================================
if ($aksi === 'hapus_eresep') {
    try {
        $no_resep = isset($_POST['no_resep']) ? validTeks4($_POST['no_resep'], 20) : '';
        
        if (empty($no_resep)) {
            throw new Exception('No. Resep tidak valid');
        }
        
        // Cek apakah resep sudah terlayani
        $query_cek = "SELECT tgl_penyerahan FROM resep_obat WHERE no_resep = '$no_resep' AND tgl_penyerahan != '0000-00-00' LIMIT 1";
        $result_cek = bukaquery($query_cek);
        if (mysqli_num_rows($result_cek) > 0) {
            throw new Exception('Resep sudah terlayani, tidak dapat dihapus');
        }
        
        // Hapus detail racikan
        $query_del_detail = "DELETE FROM resep_dokter_racikan_detail WHERE no_resep = '$no_resep'";
        bukaquery($query_del_detail);
        insertTracker($query_del_detail);
        
        // Hapus header racikan
        $query_del_racikan = "DELETE FROM resep_dokter_racikan WHERE no_resep = '$no_resep'";
        bukaquery($query_del_racikan);
        insertTracker($query_del_racikan);
        
        // Hapus resep obat
        $query_del_resep = "DELETE FROM resep_obat WHERE no_resep = '$no_resep'";
        $result = bukaquery($query_del_resep);
        insertTracker($query_del_resep);
        
        if ($result) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Resep berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus resep');
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
// HAPUS E-RESEP RAWAT INAP (RANAP) - DIPINDAHKAN KE proses3.php
// ========================================
// Handler hapus_eresep_ranap sudah dipindahkan ke proses3.php
// untuk konsistensi dengan modul rawat inap lainnya

// ========================================
// GANTI PASSWORD
// ========================================
if($aksi == 'ganti_password') {
    try {
        $old_password = isset($_POST['old_password']) ? validTeks4($_POST['old_password'], 40) : '';
        $new_password = isset($_POST['new_password']) ? validTeks4($_POST['new_password'], 40) : '';

        if(empty($old_password) || empty($new_password)) {
            throw new Exception('Password lama dan baru harus diisi');
        }

        if(strlen($new_password) < 4) {
            throw new Exception('Password baru minimal 4 karakter');
        }

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        // Verifikasi password lama: cocokkan dengan enkripsi yang sama di login
        // Login: id_user = AES_ENCRYPT(username, 'nur'), password = AES_ENCRYPT(password, 'windi')
        $cek = getOne2("SELECT COUNT(*) FROM user 
                        WHERE id_user = AES_ENCRYPT('$kd_dokter','nur') 
                        AND password = AES_ENCRYPT('$old_password','windi')");

        if($cek == 0) {
            throw new Exception('Password lama tidak sesuai');
        }

        // Update password baru
        $qUpdate = bukaquery("UPDATE user 
                              SET password = AES_ENCRYPT('$new_password','windi') 
                              WHERE id_user = AES_ENCRYPT('$kd_dokter','nur')");

        if($qUpdate) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Password berhasil diubah'
            ]);
        } else {
            throw new Exception('Gagal mengubah password');
        }

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
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