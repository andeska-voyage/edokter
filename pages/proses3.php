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

// ========================================
// FUNGSI VALIDASI TEKS (jika belum ada)
// ========================================
if (!function_exists('validTeks4')) {
    function validTeks4($str, $max_length = 255) {
        global $koneksi;
        if (empty($str)) return '';
        $str = trim($str);
        $str = substr($str, 0, $max_length);
        if (isset($koneksi)) {
            $str = mysqli_real_escape_string($koneksi, $str);
        } else {
            $str = addslashes($str);
        }
        return $str;
    }
}

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
             '{$kd_dokter}', 'ranap', '{$info_tambahan}', '{$indikasi}')";
        
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
                 '{$kd_dokter}', 'ranap', '{$informasi_tambahan}', '{$diagnosa_klinis}',
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
                 '{$kd_dokter}', 'ranap', '{$informasi_tambahan}', '{$diagnosa_klinis}')";
        } else {
            // INSERT ke tabel permintaan_lab (PK - default)
            $query_insert_header = "INSERT INTO permintaan_lab 
                (noorder, no_rawat, tgl_permintaan, jam_permintaan, 
                 tgl_sampel, jam_sampel, tgl_hasil, jam_hasil, 
                 dokter_perujuk, status, informasi_tambahan, diagnosa_klinis) 
                VALUES 
                ('{$noorder}', '{$norawat}', '{$tanggal}', '{$jam}', 
                 '0000-00-00', '00:00:00', '0000-00-00', '00:00:00', 
                 '{$kd_dokter}', 'ranap', '{$informasi_tambahan}', '{$diagnosa_klinis}')";
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
        
        // ========================================
        // UPDATE RESUME_PASIEN_RANAP (JIKA ADA)
        // ========================================
        $resume_updated = false;
        
        // Cek apakah resume sudah ada untuk no_rawat ini
        $query_cek_resume = "SELECT no_rawat FROM resume_pasien_ranap WHERE no_rawat = '$norawat' LIMIT 1";
        $result_cek_resume = bukaquery($query_cek_resume);
        
        if (mysqli_num_rows($result_cek_resume) > 0) {
            // Ambil nama penyakit
            $query_nama = "SELECT nm_penyakit FROM penyakit WHERE kd_penyakit = '$kd_penyakit' LIMIT 1";
            $result_nama = bukaquery($query_nama);
            $nm_penyakit = '';
            if (mysqli_num_rows($result_nama) > 0) {
                $row_nama = mysqli_fetch_assoc($result_nama);
                $nm_penyakit = addslashes($row_nama['nm_penyakit']);
            }
            
            // Tentukan kolom berdasarkan prioritas
            $kolom_diagnosa = '';
            $kolom_kode = '';
            
            if ($prioritas == 1) {
                $kolom_diagnosa = 'diagnosa_utama';
                $kolom_kode = 'kd_diagnosa_utama';
            } elseif ($prioritas == 2) {
                $kolom_diagnosa = 'diagnosa_sekunder';
                $kolom_kode = 'kd_diagnosa_sekunder';
            } elseif ($prioritas == 3) {
                $kolom_diagnosa = 'diagnosa_sekunder2';
                $kolom_kode = 'kd_diagnosa_sekunder2';
            } elseif ($prioritas == 4) {
                $kolom_diagnosa = 'diagnosa_sekunder3';
                $kolom_kode = 'kd_diagnosa_sekunder3';
            } elseif ($prioritas == 5) {
                $kolom_diagnosa = 'diagnosa_sekunder4';
                $kolom_kode = 'kd_diagnosa_sekunder4';
            }
            
            if (!empty($kolom_diagnosa)) {
                $query_update_resume = "UPDATE resume_pasien_ranap SET 
                    $kolom_diagnosa = '$nm_penyakit',
                    $kolom_kode = '$kd_penyakit'
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
            'message' => 'Berhasil menyimpan diagnosa',
            'status_penyakit' => $status_penyakit,
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
        
        // ========================================
        // UPDATE RESUME_PASIEN_RANAP (KOSONGKAN KOLOM JIKA ADA)
        // ========================================
        $resume_updated = false;
        
        // Cek apakah resume sudah ada untuk no_rawat ini
        $query_cek_resume = "SELECT no_rawat FROM resume_pasien_ranap WHERE no_rawat = '$norawat' LIMIT 1";
        $result_cek_resume = bukaquery($query_cek_resume);
        
        if (mysqli_num_rows($result_cek_resume) > 0) {
            // Tentukan kolom berdasarkan prioritas
            $kolom_diagnosa = '';
            $kolom_kode = '';
            
            if ($prioritas == 1) {
                $kolom_diagnosa = 'diagnosa_utama';
                $kolom_kode = 'kd_diagnosa_utama';
            } elseif ($prioritas == 2) {
                $kolom_diagnosa = 'diagnosa_sekunder';
                $kolom_kode = 'kd_diagnosa_sekunder';
            } elseif ($prioritas == 3) {
                $kolom_diagnosa = 'diagnosa_sekunder2';
                $kolom_kode = 'kd_diagnosa_sekunder2';
            } elseif ($prioritas == 4) {
                $kolom_diagnosa = 'diagnosa_sekunder3';
                $kolom_kode = 'kd_diagnosa_sekunder3';
            } elseif ($prioritas == 5) {
                $kolom_diagnosa = 'diagnosa_sekunder4';
                $kolom_kode = 'kd_diagnosa_sekunder4';
            }
            
            if (!empty($kolom_diagnosa)) {
                // Kosongkan kolom yang sesuai
                $query_update_resume = "UPDATE resume_pasien_ranap SET 
                    $kolom_diagnosa = '',
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
            'message' => 'Berhasil menghapus diagnosa',
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
             '{$tgl_peresepan}', '{$jam_peresepan}', 'ranap', '0000-00-00', '00:00:00')";
        
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
        $query_cek = "SELECT no_resep, no_rawat 
                      FROM resep_obat 
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
        bukaquery($query_delete_detail);
        insertTracker($query_delete_detail);
        
        // 2. Hapus header racikan
        $query_delete_racikan = "DELETE FROM resep_dokter_racikan 
                                 WHERE no_resep = '{$no_resep}'";
        bukaquery($query_delete_racikan);
        insertTracker($query_delete_racikan);
        
        // 3. Hapus obat non racikan
        $query_delete_obat = "DELETE FROM resep_dokter 
                              WHERE no_resep = '{$no_resep}'";
        bukaquery($query_delete_obat);
        insertTracker($query_delete_obat);
        
        // 4. Hapus header resep
        $query_delete_header = "DELETE FROM resep_obat 
                                WHERE no_resep = '{$no_resep}'";
        $result_header = bukaquery($query_delete_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menghapus resep');
        }
        
        insertTracker($query_delete_header);
        
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
// SIMPAN PEMERIKSAAN RANAP (RAWAT INAP)
// ========================================
if(isset($_POST['simpan_pemeriksaan_ranap'])) {
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

    // Default aman untuk kolom NOT NULL yang harus terisi (SQL strict mode)
    if ($tensi === '')     $tensi = '-';
    if ($spo2 === '')      $spo2 = '-';
    if ($kesadaran === '') $kesadaran = 'Compos Mentis';

    // SOAPIE Data
    $keluhan = validTeks4($_POST['subjective'] ?? '', 2000);
    $pemeriksaan = validTeks4($_POST['objective'] ?? '', 2000);
    $penilaian = validTeks4($_POST['assessment'] ?? '', 2000);
    $rtl = validTeks4($_POST['plan'] ?? '', 2000);
    $instruksi = validTeks4($_POST['intervention'] ?? '', 2000);
    $evaluasi = validTeks4($_POST['evaluation'] ?? '', 2000);
    
    // Get kode dokter dari session
    $kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"], "d"), 20);
    $nip = $kd_dokter;
    
    // Cek apakah sudah ada data untuk no_rawat, tgl_perawatan, jam_rawat ini
    $cek_query = "SELECT * FROM pemeriksaan_ranap 
                  WHERE no_rawat = '$no_rawat' 
                  AND tgl_perawatan = '$tgl_perawatan' 
                  AND jam_rawat = '$jam_rawat'";
    $cek = bukaquery($cek_query);
    
    if(mysqli_num_rows($cek) > 0) {
        // UPDATE existing record
        $query = "UPDATE pemeriksaan_ranap SET
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
                    penilaian = '$penilaian',
                    rtl = '$rtl',
                    instruksi = '$instruksi',
                    evaluasi = '$evaluasi'
                  WHERE no_rawat = '$no_rawat' 
                  AND tgl_perawatan = '$tgl_perawatan' 
                  AND jam_rawat = '$jam_rawat'";
    } else {
        // INSERT new record
        $query = "INSERT INTO pemeriksaan_ranap (
                    no_rawat, tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, 
                    respirasi, tinggi, berat, spo2, gcs, kesadaran, keluhan, 
                    pemeriksaan, alergi, penilaian, rtl, instruksi, evaluasi, nip
                  ) VALUES (
                    '$no_rawat', '$tgl_perawatan', '$jam_rawat', '$suhu_tubuh', 
                    '$tensi', '$nadi', '$respirasi', '$tinggi', '$berat', 
                    '$spo2', '$gcs', '$kesadaran', '$keluhan', '$pemeriksaan', 
                    '$alergi', '$penilaian', '$rtl', '$instruksi', '$evaluasi', '$nip'
                  )";
    }
    
    $result = bukaquery($query);
    
    if($result) {
        // TRACKING
        insertTracker($query);
        
        echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'Data pemeriksaan rawat inap berhasil disimpan',
                    timer: 1500,
                    showConfirmButton: false
                }).then(function() {
                    if(typeof SOAPIEModule !== 'undefined') {
                        SOAPIEModule.reloadRanap();
                    }
                    if(typeof PemeriksaanModule !== 'undefined') {
                        PemeriksaanModule.reloadPemeriksaan();
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
    exit();
}

// ========================================
// UPDATE PEMERIKSAAN RANAP (EDIT)
// ========================================
if ($aksi === 'update_pemeriksaan_ranap') {
    try {
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

        // Default aman untuk kolom NOT NULL yang harus terisi (SQL strict mode)
        if ($tensi === '')     $tensi = '-';
        if ($spo2 === '')      $spo2 = '-';
        if ($kesadaran === '') $kesadaran = 'Compos Mentis';

        // SOAPIE Data
        $keluhan = validTeks4($_POST['subjective'] ?? '', 2000);
        $pemeriksaan_fisik = validTeks4($_POST['objective'] ?? '', 2000);
        $penilaian = validTeks4($_POST['assessment'] ?? '', 2000);
        $rtl = validTeks4($_POST['plan'] ?? '', 2000);
        $instruksi = validTeks4($_POST['intervention'] ?? '', 2000);
        $evaluasi = validTeks4($_POST['evaluation'] ?? '', 2000);
        
        $query = "UPDATE pemeriksaan_ranap SET
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
// HAPUS PEMERIKSAAN RANAP (AJAX JSON)
// ========================================
if ($aksi === 'hapus_pemeriksaan_ranap') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $tgl_perawatan = isset($_POST['tgl_perawatan']) ? validTeks4($_POST['tgl_perawatan'], 10) : '';
        $jam_rawat = isset($_POST['jam_rawat']) ? validTeks4($_POST['jam_rawat'], 8) : '';
        
        if (empty($no_rawat) || empty($tgl_perawatan) || empty($jam_rawat)) {
            throw new Exception('Data tidak lengkap');
        }
        
        $query = "DELETE FROM pemeriksaan_ranap 
                  WHERE no_rawat = '$no_rawat' 
                  AND tgl_perawatan = '$tgl_perawatan' 
                  AND jam_rawat = '$jam_rawat'";
        
        $result = bukaquery($query);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data pemeriksaan');
        }
        
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
// SIMPAN E-RESEP RAWAT INAP (RANAP)
// ========================================
if ($aksi === 'simpan_eresep_ranap') {
    
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
        
        // === INSERT KE TABEL resep_obat (HEADER) dengan status='ranap' ===
        $query_insert_header = "INSERT INTO resep_obat 
            (no_resep, tgl_perawatan, jam, no_rawat, kd_dokter, 
             tgl_peresepan, jam_peresepan, status, tgl_penyerahan, jam_penyerahan) 
            VALUES 
            ('{$no_resep}', '0000-00-00', '00:00:00', '{$norawat}', '{$kd_dokter}', 
             '{$tgl_peresepan}', '{$jam_peresepan}', 'ranap', '0000-00-00', '00:00:00')";
        
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
// HAPUS E-RESEP RAWAT INAP (RANAP)
// ========================================
if ($aksi === 'hapus_eresep_ranap') {
    
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
        $query_cek = "SELECT no_resep, no_rawat 
                      FROM resep_obat 
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
        bukaquery($query_delete_detail);
        insertTracker($query_delete_detail);
        
        // 2. Hapus header racikan
        $query_delete_racikan = "DELETE FROM resep_dokter_racikan 
                                 WHERE no_resep = '{$no_resep}'";
        bukaquery($query_delete_racikan);
        insertTracker($query_delete_racikan);
        
        // 3. Hapus obat non racikan
        $query_delete_obat = "DELETE FROM resep_dokter 
                              WHERE no_resep = '{$no_resep}'";
        bukaquery($query_delete_obat);
        insertTracker($query_delete_obat);
        
        // 4. Hapus header resep
        $query_delete_header = "DELETE FROM resep_obat 
                                WHERE no_resep = '{$no_resep}'";
        $result_header = bukaquery($query_delete_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menghapus resep');
        }
        
        insertTracker($query_delete_header);
        
        // === RESPONSE SUKSES ===
        echo json_encode([
            'status' => 'success',
            'message' => 'Resep rawat inap berhasil dihapus',
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

// ========================================
// PROSES SIMPAN TINDAKAN RAWAT INAP (WITH JURNAL + TRACKER)
// ========================================
if ($aksi === 'simpan_tindakan_inap') {
    
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
                      FROM rawat_inap_dr 
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
        
        // AMBIL TARIF dari jns_perawatan_inap
        $query_tarif = "SELECT material, bhp, tarif_tindakandr, tarif_tindakanpr, kso, menejemen, 
                               total_byrdr, total_byrpr, total_byrdrpr 
                        FROM jns_perawatan_inap 
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
        $tarif_tindakanpr = $data_tarif['tarif_tindakanpr'] ?? 0;
        $kso = $data_tarif['kso'] ?? 0;
        $menejemen = $data_tarif['menejemen'] ?? 0;
        $biaya_rawat = $data_tarif['total_byrdr'] ?? 0;
        
        // INSERT TINDAKAN ke rawat_inap_dr
        $query_insert = "INSERT INTO rawat_inap_dr 
            (no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat, 
             material, bhp, tarif_tindakandr, kso, menejemen, biaya_rawat) 
            VALUES 
            ('$norawat', '$kd_jenis_prw', '$kd_dokter', '$tgl_perawatan', '$jam_rawat', 
             '$material', '$bhp', '$tarif_tindakandr', '$kso', '$menejemen', '$biaya_rawat')";
        
        $result = bukaquery($query_insert);
        
        if (!$result) {
            throw new Exception('Gagal menyimpan tindakan');
        }
        
        // TRACKING
        insertTracker($query_insert);
        
        // ===================================================
        // JURNAL OTOMATIS RAWAT INAP
        // ===================================================
        $jurnal_msg = '';
        $no_jurnal = '';
        
        // Load kode rekening dari set_akun_ranap
        $query_rek = "SELECT 
                        Suspen_Piutang_Tindakan_Ranap,
                        Tindakan_Ranap,
                        Beban_Jasa_Medik_Dokter_Tindakan_Ranap,
                        Utang_Jasa_Medik_Dokter_Tindakan_Ranap,
                        Beban_KSO_Tindakan_Ranap,
                        Utang_KSO_Tindakan_Ranap,
                        Beban_Jasa_Sarana_Tindakan_Ranap,
                        Utang_Jasa_Sarana_Tindakan_Ranap,
                        Beban_Jasa_Menejemen_Tindakan_Ranap,
                        Utang_Jasa_Menejemen_Tindakan_Ranap,
                        HPP_BHP_Tindakan_Ranap,
                        Persediaan_BHP_Tindakan_Ranap
                      FROM set_akun_ranap LIMIT 1";
        $result_rek = bukaquery($query_rek);
        
        if ($result_rek && mysqli_num_rows($result_rek) > 0) {
            $rek = mysqli_fetch_assoc($result_rek);
            
            // Clear tampjurnal
            $query_clear = "DELETE FROM tampjurnal";
            bukaquery($query_clear);
            insertTracker($query_clear);
            
            // Hitung total
            $ttl_pendapatan = $biaya_rawat;
            $ttl_jm_dokter = $tarif_tindakandr;
            $ttl_kso = $kso;
            $ttl_menejemen = $menejemen;
            $ttl_jasa_sarana = $material;
            $ttl_bhp = $bhp;
            
            // Build jurnal items array
            $jurnal_items = [];
            
            // === DEBET ===
            if ($ttl_pendapatan > 0) {
                $jurnal_items[] = "('{$rek['Suspen_Piutang_Tindakan_Ranap']}', 'Suspen Piutang Tindakan Ranap', $ttl_pendapatan, 0)";
            }
            if ($ttl_jm_dokter > 0) {
                $jurnal_items[] = "('{$rek['Beban_Jasa_Medik_Dokter_Tindakan_Ranap']}', 'Beban Jasa Medik Dokter Tindakan Ranap', $ttl_jm_dokter, 0)";
            }
            if ($ttl_jasa_sarana > 0) {
                $jurnal_items[] = "('{$rek['Beban_Jasa_Sarana_Tindakan_Ranap']}', 'Beban Jasa Sarana Tindakan Ranap', $ttl_jasa_sarana, 0)";
            }
            if ($ttl_kso > 0) {
                $jurnal_items[] = "('{$rek['Beban_KSO_Tindakan_Ranap']}', 'Beban KSO Tindakan Ranap', $ttl_kso, 0)";
            }
            if ($ttl_menejemen > 0) {
                $jurnal_items[] = "('{$rek['Beban_Jasa_Menejemen_Tindakan_Ranap']}', 'Beban Jasa Menejemen Tindakan Ranap', $ttl_menejemen, 0)";
            }
            if ($ttl_bhp > 0) {
                $jurnal_items[] = "('{$rek['HPP_BHP_Tindakan_Ranap']}', 'HPP BHP Tindakan Ranap', $ttl_bhp, 0)";
            }
            
            // === KREDIT ===
            if ($ttl_menejemen > 0) {
                $jurnal_items[] = "('{$rek['Utang_Jasa_Menejemen_Tindakan_Ranap']}', 'Utang Jasa Menejemen Tindakan Ranap', 0, $ttl_menejemen)";
            }
            if ($ttl_kso > 0) {
                $jurnal_items[] = "('{$rek['Utang_KSO_Tindakan_Ranap']}', 'Utang KSO Tindakan Ranap', 0, $ttl_kso)";
            }
            if ($ttl_jasa_sarana > 0) {
                $jurnal_items[] = "('{$rek['Utang_Jasa_Sarana_Tindakan_Ranap']}', 'Utang Jasa Sarana Tindakan Ranap', 0, $ttl_jasa_sarana)";
            }
            if ($ttl_pendapatan > 0) {
                $jurnal_items[] = "('{$rek['Tindakan_Ranap']}', 'Pendapatan Tindakan Rawat Inap', 0, $ttl_pendapatan)";
            }
            if ($ttl_bhp > 0) {
                $jurnal_items[] = "('{$rek['Persediaan_BHP_Tindakan_Ranap']}', 'Persediaan BHP Tindakan Ranap', 0, $ttl_bhp)";
            }
            if ($ttl_jm_dokter > 0) {
                $jurnal_items[] = "('{$rek['Utang_Jasa_Medik_Dokter_Tindakan_Ranap']}', 'Utang Jasa Medik Dokter Tindakan Ranap', 0, $ttl_jm_dokter)";
            }
            
            // Insert ke tampjurnal
            if (!empty($jurnal_items)) {
                $query_tampjurnal = "INSERT INTO tampjurnal (kd_rek, nm_rek, debet, kredit) VALUES " . implode(',', $jurnal_items);
                bukaquery($query_tampjurnal);
                insertTracker($query_tampjurnal);
                
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
                
                // Ambil data pasien dan dokter untuk keterangan
                $query_pasien = "SELECT p.nm_pasien, rp.no_rkm_medis, d.nm_dokter
                                 FROM reg_periksa rp
                                 INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                 INNER JOIN dokter d ON d.kd_dokter = '$kd_dokter'
                                 WHERE rp.no_rawat = '$norawat'
                                 LIMIT 1";
                $result_pasien = bukaquery($query_pasien);
                $data_pasien = mysqli_fetch_assoc($result_pasien);
                
                $keterangan = "TINDAKAN RAWAT INAP PASIEN {$data_pasien['no_rkm_medis']} {$data_pasien['nm_pasien']}, DIPOSTING OLEH {$data_pasien['nm_dokter']} - EDOKTER";
                
                // Insert ke jurnal
                $query_jurnal = "INSERT INTO jurnal (no_jurnal, no_bukti, tgl_jurnal, jam_jurnal, jenis, keterangan)
                                 VALUES ('$no_jurnal', '$norawat', '$tanggal', '$jam', 'U', '$keterangan')";
                $result_jurnal = bukaquery($query_jurnal);
                
                if ($result_jurnal) {
                    // Insert ke detailjurnal
                    $query_detail = "INSERT INTO detailjurnal (no_jurnal, kd_rek, debet, kredit)
                                     SELECT '$no_jurnal', kd_rek, debet, kredit FROM tampjurnal";
                    bukaquery($query_detail);
                    
                    $jurnal_msg = "Jurnal dibuat: $no_jurnal";
                } else {
                    $jurnal_msg = "Tindakan tersimpan (jurnal gagal)";
                }
                
                // Clear tampjurnal
                $query_clear2 = "DELETE FROM tampjurnal";
                bukaquery($query_clear2);
                insertTracker($query_clear2);
            }
        } else {
            $jurnal_msg = "Tindakan tersimpan (set_akun_ranap tidak ditemukan)";
        }
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => 'Berhasil menyimpan tindakan rawat inap',
            'biaya' => $biaya_rawat,
            'jam_rawat' => $jam_rawat,
            'jurnal' => $jurnal_msg
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
// PROSES HAPUS TINDAKAN RAWAT INAP (WITH JURNAL PEMBALIK + TRACKER)
// ========================================
if ($aksi === 'hapus_tindakan_inap') {
    
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
                      FROM rawat_inap_dr r
                      INNER JOIN jns_perawatan_inap j ON r.kd_jenis_prw = j.kd_jenis_prw
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
        
        // ===================================================
        // JURNAL PEMBALIK RAWAT INAP
        // ===================================================
        $jurnal_msg = '';
        
        // Load kode rekening dari set_akun_ranap
        $query_rek = "SELECT 
                        Suspen_Piutang_Tindakan_Ranap,
                        Tindakan_Ranap,
                        Beban_Jasa_Medik_Dokter_Tindakan_Ranap,
                        Utang_Jasa_Medik_Dokter_Tindakan_Ranap,
                        Beban_KSO_Tindakan_Ranap,
                        Utang_KSO_Tindakan_Ranap,
                        Beban_Jasa_Sarana_Tindakan_Ranap,
                        Utang_Jasa_Sarana_Tindakan_Ranap,
                        Beban_Jasa_Menejemen_Tindakan_Ranap,
                        Utang_Jasa_Menejemen_Tindakan_Ranap,
                        HPP_BHP_Tindakan_Ranap,
                        Persediaan_BHP_Tindakan_Ranap
                      FROM set_akun_ranap LIMIT 1";
        $result_rek = bukaquery($query_rek);
        
        if ($result_rek && mysqli_num_rows($result_rek) > 0) {
            $rek = mysqli_fetch_assoc($result_rek);
            
            // Ambil nilai dari data tindakan
            $ttl_pendapatan = $data_tindakan['biaya_rawat'] ?? 0;
            $ttl_jm_dokter = $data_tindakan['tarif_tindakandr'] ?? 0;
            $ttl_kso = $data_tindakan['kso'] ?? 0;
            $ttl_menejemen = $data_tindakan['menejemen'] ?? 0;
            $ttl_jasa_sarana = $data_tindakan['material'] ?? 0;
            $ttl_bhp = $data_tindakan['bhp'] ?? 0;
            
            // Clear tampjurnal
            $query_clear = "DELETE FROM tampjurnal";
            bukaquery($query_clear);
            insertTracker($query_clear);
            
            // Build jurnal items array (POSISI DIBALIK untuk pembatalan)
            $jurnal_items = [];
            
            // Pendapatan Tindakan - DIBALIK
            if ($ttl_pendapatan > 0) {
                $jurnal_items[] = "('{$rek['Tindakan_Ranap']}', 'Pendapatan Tindakan Rawat Inap', $ttl_pendapatan, 0)";
                $jurnal_items[] = "('{$rek['Suspen_Piutang_Tindakan_Ranap']}', 'Suspen Piutang Tindakan Ranap', 0, $ttl_pendapatan)";
            }
            if ($ttl_jm_dokter > 0) {
                $jurnal_items[] = "('{$rek['Utang_Jasa_Medik_Dokter_Tindakan_Ranap']}', 'Utang Jasa Medik Dokter Tindakan Ranap', $ttl_jm_dokter, 0)";
                $jurnal_items[] = "('{$rek['Beban_Jasa_Medik_Dokter_Tindakan_Ranap']}', 'Beban Jasa Medik Dokter Tindakan Ranap', 0, $ttl_jm_dokter)";
            }
            if ($ttl_kso > 0) {
                $jurnal_items[] = "('{$rek['Utang_KSO_Tindakan_Ranap']}', 'Utang KSO Tindakan Ranap', $ttl_kso, 0)";
                $jurnal_items[] = "('{$rek['Beban_KSO_Tindakan_Ranap']}', 'Beban KSO Tindakan Ranap', 0, $ttl_kso)";
            }
            if ($ttl_menejemen > 0) {
                $jurnal_items[] = "('{$rek['Utang_Jasa_Menejemen_Tindakan_Ranap']}', 'Utang Jasa Menejemen Tindakan Ranap', $ttl_menejemen, 0)";
                $jurnal_items[] = "('{$rek['Beban_Jasa_Menejemen_Tindakan_Ranap']}', 'Beban Jasa Menejemen Tindakan Ranap', 0, $ttl_menejemen)";
            }
            if ($ttl_jasa_sarana > 0) {
                $jurnal_items[] = "('{$rek['Utang_Jasa_Sarana_Tindakan_Ranap']}', 'Utang Jasa Sarana Tindakan Ranap', $ttl_jasa_sarana, 0)";
                $jurnal_items[] = "('{$rek['Beban_Jasa_Sarana_Tindakan_Ranap']}', 'Beban Jasa Sarana Tindakan Ranap', 0, $ttl_jasa_sarana)";
            }
            if ($ttl_bhp > 0) {
                $jurnal_items[] = "('{$rek['Persediaan_BHP_Tindakan_Ranap']}', 'Persediaan BHP Tindakan Ranap', $ttl_bhp, 0)";
                $jurnal_items[] = "('{$rek['HPP_BHP_Tindakan_Ranap']}', 'HPP BHP Tindakan Ranap', 0, $ttl_bhp)";
            }
            
            // Insert ke tampjurnal
            if (!empty($jurnal_items)) {
                $query_tampjurnal = "INSERT INTO tampjurnal (kd_rek, nm_rek, debet, kredit) VALUES " . implode(',', $jurnal_items);
                bukaquery($query_tampjurnal);
                insertTracker($query_tampjurnal);
                
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
                
                $keterangan = "PEMBATALAN TINDAKAN RAWAT INAP PASIEN {$data_tindakan['no_rkm_medis']} {$data_tindakan['nm_pasien']}, DIPOSTING OLEH {$data_tindakan['nm_dokter']} - EDOKTER";
                
                // Insert ke jurnal
                $query_jurnal = "INSERT INTO jurnal (no_jurnal, no_bukti, tgl_jurnal, jam_jurnal, jenis, keterangan)
                                 VALUES ('$no_jurnal', '$norawat', '$tanggal', '$jam', 'U', '$keterangan')";
                $result_jurnal = bukaquery($query_jurnal);
                
                if ($result_jurnal) {
                    // Insert ke detailjurnal
                    $query_detail = "INSERT INTO detailjurnal (no_jurnal, kd_rek, debet, kredit)
                                     SELECT '$no_jurnal', kd_rek, debet, kredit FROM tampjurnal";
                    bukaquery($query_detail);
                    
                    $jurnal_msg = "Jurnal pembatalan dibuat: $no_jurnal";
                }
                
                // Clear tampjurnal
                $query_clear2 = "DELETE FROM tampjurnal";
                bukaquery($query_clear2);
                insertTracker($query_clear2);
            }
        }
        
        // HAPUS TINDAKAN
        $query_delete = "DELETE FROM rawat_inap_dr 
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
            'message' => 'Berhasil menghapus tindakan rawat inap',
            'jurnal' => $jurnal_msg
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ===================================================
// AKSI: simpan_resume_ranap - Simpan/Update Resume Pasien Ranap
// ===================================================
if(isset($_POST['aksi']) && $_POST['aksi'] == 'simpan_resume_ranap') {
    header('Content-Type: application/json');
    
    try {
        // Ambil kd_dokter login
        $kd_dokter_login = '';
        if(isset($_SESSION['ses_dokter']) && !empty($_SESSION['ses_dokter'])) {
            $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        }
        
        if(empty($kd_dokter_login)) {
            throw new Exception('Session dokter tidak valid');
        }
        
        // Ambil data dari POST
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if(empty($no_rawat)) {
            throw new Exception('No rawat tidak valid');
        }
        
        // ✅ GUNAKAN validTeks4() UNTUK SEMUA FIELD - KONSISTEN!
        $diagnosa_awal = isset($_POST['diagnosa_awal']) ? validTeks4($_POST['diagnosa_awal'], 100) : '';
        $alasan = isset($_POST['alasan']) ? validTeks4($_POST['alasan'], 100) : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 5000) : '';
        $pemeriksaan_fisik = isset($_POST['pemeriksaan_fisik']) ? validTeks4($_POST['pemeriksaan_fisik'], 5000) : '';
        $jalannya_penyakit = isset($_POST['jalannya_penyakit']) ? validTeks4($_POST['jalannya_penyakit'], 5000) : '';
        $pemeriksaan_penunjang = isset($_POST['pemeriksaan_penunjang']) ? validTeks4($_POST['pemeriksaan_penunjang'], 5000) : '';
        $hasil_laborat = isset($_POST['hasil_laborat']) ? validTeks4($_POST['hasil_laborat'], 5000) : '';
        $tindakan_dan_operasi = isset($_POST['tindakan_dan_operasi']) ? validTeks4($_POST['tindakan_dan_operasi'], 5000) : '';
        $obat_di_rs = isset($_POST['obat_di_rs']) ? validTeks4($_POST['obat_di_rs'], 5000) : '';
        
        // Diagnosa
        $diagnosa_utama = isset($_POST['diagnosa_utama']) ? validTeks4($_POST['diagnosa_utama'], 80) : '';
        $kd_diagnosa_utama = isset($_POST['kd_diagnosa_utama']) ? validTeks4($_POST['kd_diagnosa_utama'], 10) : '';
        $diagnosa_sekunder = isset($_POST['diagnosa_sekunder']) ? validTeks4($_POST['diagnosa_sekunder'], 80) : '';
        $kd_diagnosa_sekunder = isset($_POST['kd_diagnosa_sekunder']) ? validTeks4($_POST['kd_diagnosa_sekunder'], 10) : '';
        $diagnosa_sekunder2 = isset($_POST['diagnosa_sekunder2']) ? validTeks4($_POST['diagnosa_sekunder2'], 80) : '';
        $kd_diagnosa_sekunder2 = isset($_POST['kd_diagnosa_sekunder2']) ? validTeks4($_POST['kd_diagnosa_sekunder2'], 10) : '';
        $diagnosa_sekunder3 = isset($_POST['diagnosa_sekunder3']) ? validTeks4($_POST['diagnosa_sekunder3'], 80) : '';
        $kd_diagnosa_sekunder3 = isset($_POST['kd_diagnosa_sekunder3']) ? validTeks4($_POST['kd_diagnosa_sekunder3'], 10) : '';
        $diagnosa_sekunder4 = isset($_POST['diagnosa_sekunder4']) ? validTeks4($_POST['diagnosa_sekunder4'], 80) : '';
        $kd_diagnosa_sekunder4 = isset($_POST['kd_diagnosa_sekunder4']) ? validTeks4($_POST['kd_diagnosa_sekunder4'], 10) : '';
        
        // Prosedur
        $prosedur_utama = isset($_POST['prosedur_utama']) ? validTeks4($_POST['prosedur_utama'], 80) : '';
        $kd_prosedur_utama = isset($_POST['kd_prosedur_utama']) ? validTeks4($_POST['kd_prosedur_utama'], 8) : '';
        $prosedur_sekunder = isset($_POST['prosedur_sekunder']) ? validTeks4($_POST['prosedur_sekunder'], 80) : '';
        $kd_prosedur_sekunder = isset($_POST['kd_prosedur_sekunder']) ? validTeks4($_POST['kd_prosedur_sekunder'], 8) : '';
        $prosedur_sekunder2 = isset($_POST['prosedur_sekunder2']) ? validTeks4($_POST['prosedur_sekunder2'], 80) : '';
        $kd_prosedur_sekunder2 = isset($_POST['kd_prosedur_sekunder2']) ? validTeks4($_POST['kd_prosedur_sekunder2'], 8) : '';
        $prosedur_sekunder3 = isset($_POST['prosedur_sekunder3']) ? validTeks4($_POST['prosedur_sekunder3'], 80) : '';
        $kd_prosedur_sekunder3 = isset($_POST['kd_prosedur_sekunder3']) ? validTeks4($_POST['kd_prosedur_sekunder3'], 8) : '';
        
        // Kepulangan
        $alergi = isset($_POST['alergi']) ? validTeks4($_POST['alergi'], 100) : '';
        $diet = isset($_POST['diet']) ? validTeks4($_POST['diet'], 5000) : '';
        $lab_belum = isset($_POST['lab_belum']) ? validTeks4($_POST['lab_belum'], 5000) : '';
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 5000) : '';
        $cara_keluar = isset($_POST['cara_keluar']) ? validTeks4($_POST['cara_keluar'], 30) : 'Atas Izin Dokter';
        $ket_keluar = isset($_POST['ket_keluar']) ? validTeks4($_POST['ket_keluar'], 50) : '';
        $keadaan = isset($_POST['keadaan']) ? validTeks4($_POST['keadaan'], 20) : 'Membaik';
        $ket_keadaan = isset($_POST['ket_keadaan']) ? validTeks4($_POST['ket_keadaan'], 50) : '';
        $dilanjutkan = isset($_POST['dilanjutkan']) ? validTeks4($_POST['dilanjutkan'], 20) : 'Kembali Ke RS';
        $ket_dilanjutkan = isset($_POST['ket_dilanjutkan']) ? validTeks4($_POST['ket_dilanjutkan'], 50) : '';
        $kontrol = isset($_POST['kontrol']) && !empty($_POST['kontrol']) ? $_POST['kontrol'] : date('Y-m-d H:i:s', strtotime('+7 days'));
        $obat_pulang = isset($_POST['obat_pulang']) ? validTeks4($_POST['obat_pulang'], 5000) : '';
        
        // Cek apakah sudah ada data resume untuk no_rawat ini
        $query_check = bukaquery("SELECT no_rawat, kd_dokter FROM resume_pasien_ranap WHERE no_rawat = '$no_rawat'");
        $existing = mysqli_fetch_assoc($query_check);
        
        if($existing) {
            // DATA SUDAH ADA - MODE UPDATE
            // Aturan: Dokter lain boleh update data, tapi TIDAK BOLEH mengubah kd_dokter
            // kd_dokter tetap milik dokter yang pertama kali insert
            
            $query_update = "UPDATE resume_pasien_ranap SET 
                diagnosa_awal = '$diagnosa_awal',
                alasan = '$alasan',
                keluhan_utama = '$keluhan_utama',
                pemeriksaan_fisik = '$pemeriksaan_fisik',
                jalannya_penyakit = '$jalannya_penyakit',
                pemeriksaan_penunjang = '$pemeriksaan_penunjang',
                hasil_laborat = '$hasil_laborat',
                tindakan_dan_operasi = '$tindakan_dan_operasi',
                obat_di_rs = '$obat_di_rs',
                diagnosa_utama = '$diagnosa_utama',
                kd_diagnosa_utama = '$kd_diagnosa_utama',
                diagnosa_sekunder = '$diagnosa_sekunder',
                kd_diagnosa_sekunder = '$kd_diagnosa_sekunder',
                diagnosa_sekunder2 = '$diagnosa_sekunder2',
                kd_diagnosa_sekunder2 = '$kd_diagnosa_sekunder2',
                diagnosa_sekunder3 = '$diagnosa_sekunder3',
                kd_diagnosa_sekunder3 = '$kd_diagnosa_sekunder3',
                diagnosa_sekunder4 = '$diagnosa_sekunder4',
                kd_diagnosa_sekunder4 = '$kd_diagnosa_sekunder4',
                prosedur_utama = '$prosedur_utama',
                kd_prosedur_utama = '$kd_prosedur_utama',
                prosedur_sekunder = '$prosedur_sekunder',
                kd_prosedur_sekunder = '$kd_prosedur_sekunder',
                prosedur_sekunder2 = '$prosedur_sekunder2',
                kd_prosedur_sekunder2 = '$kd_prosedur_sekunder2',
                prosedur_sekunder3 = '$prosedur_sekunder3',
                kd_prosedur_sekunder3 = '$kd_prosedur_sekunder3',
                alergi = '$alergi',
                diet = '$diet',
                lab_belum = '$lab_belum',
                edukasi = '$edukasi',
                cara_keluar = '$cara_keluar',
                ket_keluar = '$ket_keluar',
                keadaan = '$keadaan',
                ket_keadaan = '$ket_keadaan',
                dilanjutkan = '$dilanjutkan',
                ket_dilanjutkan = '$ket_dilanjutkan',
                kontrol = '$kontrol',
                obat_pulang = '$obat_pulang'
            WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query_update);
            
            if($result) {
                insertTracker($query_update);
                
                // Info dokter pemilik data
                $owner_dokter = $existing['kd_dokter'];
                $is_owner = ($owner_dokter == $kd_dokter_login);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Data resume pasien berhasil diupdate',
                    'mode' => 'update',
                    'is_owner' => $is_owner,
                    'owner_dokter' => $owner_dokter
                ]);
            } else {
                throw new Exception('Gagal mengupdate data resume pasien');
            }
            
        } else {
            // DATA BELUM ADA - MODE INSERT
            // Dokter siapa saja bisa insert, kd_dokter = dokter yang login
            
            $query_insert = "INSERT INTO resume_pasien_ranap (
                no_rawat, kd_dokter, diagnosa_awal, alasan, keluhan_utama, pemeriksaan_fisik,
                jalannya_penyakit, pemeriksaan_penunjang, hasil_laborat, tindakan_dan_operasi, obat_di_rs,
                diagnosa_utama, kd_diagnosa_utama, diagnosa_sekunder, kd_diagnosa_sekunder,
                diagnosa_sekunder2, kd_diagnosa_sekunder2, diagnosa_sekunder3, kd_diagnosa_sekunder3,
                diagnosa_sekunder4, kd_diagnosa_sekunder4,
                prosedur_utama, kd_prosedur_utama, prosedur_sekunder, kd_prosedur_sekunder,
                prosedur_sekunder2, kd_prosedur_sekunder2, prosedur_sekunder3, kd_prosedur_sekunder3,
                alergi, diet, lab_belum, edukasi, cara_keluar, ket_keluar,
                keadaan, ket_keadaan, dilanjutkan, ket_dilanjutkan, kontrol, obat_pulang
            ) VALUES (
                '$no_rawat', '$kd_dokter_login', '$diagnosa_awal', '$alasan', '$keluhan_utama', '$pemeriksaan_fisik',
                '$jalannya_penyakit', '$pemeriksaan_penunjang', '$hasil_laborat', '$tindakan_dan_operasi', '$obat_di_rs',
                '$diagnosa_utama', '$kd_diagnosa_utama', '$diagnosa_sekunder', '$kd_diagnosa_sekunder',
                '$diagnosa_sekunder2', '$kd_diagnosa_sekunder2', '$diagnosa_sekunder3', '$kd_diagnosa_sekunder3',
                '$diagnosa_sekunder4', '$kd_diagnosa_sekunder4',
                '$prosedur_utama', '$kd_prosedur_utama', '$prosedur_sekunder', '$kd_prosedur_sekunder',
                '$prosedur_sekunder2', '$kd_prosedur_sekunder2', '$prosedur_sekunder3', '$kd_prosedur_sekunder3',
                '$alergi', '$diet', '$lab_belum', '$edukasi', '$cara_keluar', '$ket_keluar',
                '$keadaan', '$ket_keadaan', '$dilanjutkan', '$ket_dilanjutkan', '$kontrol', '$obat_pulang'
            )";
            
            $result = bukaquery($query_insert);
            
            if($result) {
                insertTracker($query_insert);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Data resume pasien berhasil disimpan',
                    'mode' => 'insert',
                    'kd_dokter' => $kd_dokter_login
                ]);
            } else {
                throw new Exception('Gagal menyimpan data resume pasien');
            }
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ===================================================
// AKSI: hapus_resume_ranap - Hapus Resume Pasien Ranap
// ===================================================
if(isset($_POST['aksi']) && $_POST['aksi'] == 'hapus_resume_ranap') {
    header('Content-Type: application/json');
    
    try {
        // Ambil kd_dokter login
        $kd_dokter_login = '';
        if(isset($_SESSION['ses_dokter']) && !empty($_SESSION['ses_dokter'])) {
            $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        }
        
        if(empty($kd_dokter_login)) {
            throw new Exception('Session dokter tidak valid');
        }
        
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if(empty($no_rawat)) {
            throw new Exception('No rawat tidak valid');
        }
        
        // Cek apakah data ada dan milik dokter yang login
        $query_check = bukaquery("SELECT kd_dokter FROM resume_pasien_ranap WHERE no_rawat = '$no_rawat'");
        $existing = mysqli_fetch_assoc($query_check);
        
        if(!$existing) {
            throw new Exception('Data resume tidak ditemukan');
        }
        
        // Hanya dokter pemilik yang bisa hapus
        if($existing['kd_dokter'] != $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus data ini. Hanya dokter yang membuat resume yang dapat menghapus.');
        }
        
        // Hapus data
        $query_delete = "DELETE FROM resume_pasien_ranap WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete);
        
        if($result) {
            insertTracker($query_delete);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data resume pasien berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus data resume pasien');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ===================================================
// AKSI: simpan_penilaian_medis_ranap - Simpan/Update Penilaian Awal Medis Ranap
// ===================================================
if(isset($_POST['aksi']) && $_POST['aksi'] == 'simpan_penilaian_medis_ranap') {
    header('Content-Type: application/json');
    
    try {
        // Ambil kd_dokter login
        $kd_dokter_login = '';
        if(isset($_SESSION['ses_dokter']) && !empty($_SESSION['ses_dokter'])) {
            $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        }
        
        if(empty($kd_dokter_login)) {
            throw new Exception('Session dokter tidak valid');
        }
        
        // Ambil data dari POST
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if(empty($no_rawat)) {
            throw new Exception('No rawat tidak valid');
        }
        
        // Data form
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $anamnesis = isset($_POST['anamnesis']) ? validTeks4($_POST['anamnesis'], 20) : 'Autoanamnesis';
        $hubungan = isset($_POST['hubungan']) ? validTeks4($_POST['hubungan'], 100) : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps = isset($_POST['rps']) ? validTeks4($_POST['rps'], 2000) : '';
        $rpd = isset($_POST['rpd']) ? validTeks4($_POST['rpd'], 1000) : '';
        $rpk = isset($_POST['rpk']) ? validTeks4($_POST['rpk'], 1000) : '';
        $rpo = isset($_POST['rpo']) ? validTeks4($_POST['rpo'], 1000) : '';
        $alergi = isset($_POST['alergi']) ? validTeks4($_POST['alergi'], 100) : '';
        
        // Pemeriksaan fisik
        $keadaan = isset($_POST['keadaan']) ? validTeks4($_POST['keadaan'], 20) : 'Sehat';
        $gcs = isset($_POST['gcs']) ? validTeks4($_POST['gcs'], 10) : '';
        $kesadaran = isset($_POST['kesadaran']) ? validTeks4($_POST['kesadaran'], 20) : 'Compos Mentis';
        $td = isset($_POST['td']) ? validTeks4($_POST['td'], 8) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $rr = isset($_POST['rr']) ? validTeks4($_POST['rr'], 5) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $spo = isset($_POST['spo']) ? validTeks4($_POST['spo'], 5) : '';
        $bb = isset($_POST['bb']) ? validTeks4($_POST['bb'], 5) : '';
        $tb = isset($_POST['tb']) ? validTeks4($_POST['tb'], 5) : '';
        
        // Status organ
        $kepala = isset($_POST['kepala']) ? validTeks4($_POST['kepala'], 20) : 'Tidak Diperiksa';
        $mata = isset($_POST['mata']) ? validTeks4($_POST['mata'], 20) : 'Tidak Diperiksa';
        $gigi = isset($_POST['gigi']) ? validTeks4($_POST['gigi'], 20) : 'Tidak Diperiksa';
        $tht = isset($_POST['tht']) ? validTeks4($_POST['tht'], 20) : 'Tidak Diperiksa';
        $thoraks = isset($_POST['thoraks']) ? validTeks4($_POST['thoraks'], 20) : 'Tidak Diperiksa';
        $jantung = isset($_POST['jantung']) ? validTeks4($_POST['jantung'], 20) : 'Tidak Diperiksa';
        $paru = isset($_POST['paru']) ? validTeks4($_POST['paru'], 20) : 'Tidak Diperiksa';
        $abdomen = isset($_POST['abdomen']) ? validTeks4($_POST['abdomen'], 20) : 'Tidak Diperiksa';
        $genital = isset($_POST['genital']) ? validTeks4($_POST['genital'], 20) : 'Tidak Diperiksa';
        $ekstremitas = isset($_POST['ekstremitas']) ? validTeks4($_POST['ekstremitas'], 20) : 'Tidak Diperiksa';
        $kulit = isset($_POST['kulit']) ? validTeks4($_POST['kulit'], 20) : 'Tidak Diperiksa';
        $ket_fisik = isset($_POST['ket_fisik']) ? validTeks4($_POST['ket_fisik'], 5000) : '';
        
        // Status lokalis
        $ket_lokalis = isset($_POST['ket_lokalis']) ? validTeks4($_POST['ket_lokalis'], 5000) : '';
        
        // Penunjang
        $lab = isset($_POST['lab']) ? validTeks4($_POST['lab'], 5000) : '';
        $rad = isset($_POST['rad']) ? validTeks4($_POST['rad'], 5000) : '';
        $penunjang = isset($_POST['penunjang']) ? validTeks4($_POST['penunjang'], 5000) : '';
        
        // Diagnosis & Tatalaksana
        $diagnosis = isset($_POST['diagnosis']) ? validTeks4($_POST['diagnosis'], 500) : '';
        $tata = isset($_POST['tata']) ? validTeks4($_POST['tata'], 5000) : '';
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 1000) : '';
        
        // Cek apakah sudah ada data untuk no_rawat ini
        $query_check = bukaquery("SELECT no_rawat, kd_dokter FROM penilaian_medis_ranap WHERE no_rawat = '$no_rawat'");
        $existing = mysqli_fetch_assoc($query_check);
        
        if($existing) {
            // DATA SUDAH ADA - MODE UPDATE
            $query_update = "UPDATE penilaian_medis_ranap SET 
                tanggal = '$tanggal',
                anamnesis = '$anamnesis',
                hubungan = '$hubungan',
                keluhan_utama = '$keluhan_utama',
                rps = '$rps',
                rpd = '$rpd',
                rpk = '$rpk',
                rpo = '$rpo',
                alergi = '$alergi',
                keadaan = '$keadaan',
                gcs = '$gcs',
                kesadaran = '$kesadaran',
                td = '$td',
                nadi = '$nadi',
                rr = '$rr',
                suhu = '$suhu',
                spo = '$spo',
                bb = '$bb',
                tb = '$tb',
                kepala = '$kepala',
                mata = '$mata',
                gigi = '$gigi',
                tht = '$tht',
                thoraks = '$thoraks',
                jantung = '$jantung',
                paru = '$paru',
                abdomen = '$abdomen',
                genital = '$genital',
                ekstremitas = '$ekstremitas',
                kulit = '$kulit',
                ket_fisik = '$ket_fisik',
                ket_lokalis = '$ket_lokalis',
                lab = '$lab',
                rad = '$rad',
                penunjang = '$penunjang',
                diagnosis = '$diagnosis',
                tata = '$tata',
                edukasi = '$edukasi'
            WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query_update);
            
            if($result) {
                insertTracker($query_update);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Data penilaian medis ranap berhasil diupdate',
                    'mode' => 'update'
                ]);
            } else {
                throw new Exception('Gagal mengupdate data penilaian medis ranap');
            }
            
        } else {
            // DATA BELUM ADA - MODE INSERT
            $query_insert = "INSERT INTO penilaian_medis_ranap (
                no_rawat, tanggal, kd_dokter, anamnesis, hubungan, keluhan_utama, rps, rpd, rpk, rpo, alergi,
                keadaan, gcs, kesadaran, td, nadi, rr, suhu, spo, bb, tb,
                kepala, mata, gigi, tht, thoraks, jantung, paru, abdomen, genital, ekstremitas, kulit, ket_fisik,
                ket_lokalis, lab, rad, penunjang, diagnosis, tata, edukasi
            ) VALUES (
                '$no_rawat', '$tanggal', '$kd_dokter_login', '$anamnesis', '$hubungan', '$keluhan_utama', '$rps', '$rpd', '$rpk', '$rpo', '$alergi',
                '$keadaan', '$gcs', '$kesadaran', '$td', '$nadi', '$rr', '$suhu', '$spo', '$bb', '$tb',
                '$kepala', '$mata', '$gigi', '$tht', '$thoraks', '$jantung', '$paru', '$abdomen', '$genital', '$ekstremitas', '$kulit', '$ket_fisik',
                '$ket_lokalis', '$lab', '$rad', '$penunjang', '$diagnosis', '$tata', '$edukasi'
            )";
            
            $result = bukaquery($query_insert);
            
            if($result) {
                insertTracker($query_insert);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Data penilaian medis ranap berhasil disimpan',
                    'mode' => 'insert'
                ]);
            } else {
                throw new Exception('Gagal menyimpan data penilaian medis ranap');
            }
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ===================================================
// AKSI: hapus_penilaian_medis_ranap - Hapus Penilaian Awal Medis Ranap
// ===================================================
if(isset($_POST['aksi']) && $_POST['aksi'] == 'hapus_penilaian_medis_ranap') {
    header('Content-Type: application/json');
    
    try {
        // Ambil kd_dokter login
        $kd_dokter_login = '';
        if(isset($_SESSION['ses_dokter']) && !empty($_SESSION['ses_dokter'])) {
            $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        }
        
        if(empty($kd_dokter_login)) {
            throw new Exception('Session dokter tidak valid');
        }
        
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if(empty($no_rawat)) {
            throw new Exception('No rawat tidak valid');
        }
        
        // Cek apakah data ada
        $query_check = bukaquery("SELECT kd_dokter FROM penilaian_medis_ranap WHERE no_rawat = '$no_rawat'");
        $existing = mysqli_fetch_assoc($query_check);
        
        if(!$existing) {
            throw new Exception('Data penilaian medis ranap tidak ditemukan');
        }
        
        // Opsional: Hanya dokter pemilik yang bisa hapus
        // Uncomment jika ingin membatasi hanya dokter yang input yang bisa hapus
        // if($existing['kd_dokter'] != $kd_dokter_login) {
        //     throw new Exception('Anda tidak memiliki akses untuk menghapus data ini.');
        // }
        
        // Hapus data
        $query_delete = "DELETE FROM penilaian_medis_ranap WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete);
        
        if($result) {
            insertTracker($query_delete);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis ranap berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus data penilaian medis ranap');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ===================================================
// AKSI: get_riwayat_persalinan - Ambil Data Riwayat Persalinan Ibu Bayi
// ===================================================
if(isset($_POST['aksi']) && $_POST['aksi'] == 'get_riwayat_persalinan') {
    header('Content-Type: application/json');
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if(empty($no_rawat)) {
            throw new Exception('No rawat tidak valid');
        }
        
        $query = bukaquery("SELECT * FROM riwayat_persalinan_ibu WHERE no_rawat = '$no_rawat' ORDER BY no ASC");
        
        $data = [];
        while($row = mysqli_fetch_assoc($query)) {
            $data[] = $row;
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

// ===================================================
// AKSI: simpan_riwayat_persalinan - Simpan Riwayat Persalinan Ibu Bayi
// ===================================================
if(isset($_POST['aksi']) && $_POST['aksi'] == 'simpan_riwayat_persalinan') {
    header('Content-Type: application/json');
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if(empty($no_rawat)) {
            throw new Exception('No rawat tidak valid');
        }
        
        $tempat = isset($_POST['tempat']) ? validTeks4($_POST['tempat'], 100) : '';
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
        $jenis_persalinan = isset($_POST['jenis_persalinan']) ? validTeks4($_POST['jenis_persalinan'], 100) : '';
        $usia_hamil = isset($_POST['usia_hamil']) ? validTeks4($_POST['usia_hamil'], 10) : '';
        $penolong = isset($_POST['penolong']) ? validTeks4($_POST['penolong'], 100) : '';
        $jk = isset($_POST['jk']) ? validTeks4($_POST['jk'], 20) : 'Laki-Laki';
        $penyulit = isset($_POST['penyulit']) ? validTeks4($_POST['penyulit'], 200) : '';
        $bb_pb = isset($_POST['bb_pb']) ? validTeks4($_POST['bb_pb'], 50) : '';
        $keadaan = isset($_POST['keadaan']) ? validTeks4($_POST['keadaan'], 100) : '';
        
        // Generate auto increment no
        $query_max = bukaquery("SELECT COALESCE(MAX(no), 0) + 1 as next_no FROM riwayat_persalinan_ibu WHERE no_rawat = '$no_rawat'");
        $row_max = mysqli_fetch_assoc($query_max);
        $next_no = $row_max['next_no'];
        
        $query_insert = "INSERT INTO riwayat_persalinan_ibu (
            no_rawat, no, tempat, tanggal, jenis_persalinan, usia_hamil, penolong, jk, penyulit, bb_pb, keadaan
        ) VALUES (
            '$no_rawat', '$next_no', '$tempat', '$tanggal', '$jenis_persalinan', '$usia_hamil', '$penolong', '$jk', '$penyulit', '$bb_pb', '$keadaan'
        )";
        
        $result = bukaquery($query_insert);
        
        if($result) {
            insertTracker($query_insert);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data riwayat persalinan berhasil disimpan'
            ]);
        } else {
            throw new Exception('Gagal menyimpan data riwayat persalinan');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ===================================================
// AKSI: hapus_riwayat_persalinan - Hapus Riwayat Persalinan Ibu Bayi
// ===================================================
if(isset($_POST['aksi']) && $_POST['aksi'] == 'hapus_riwayat_persalinan') {
    header('Content-Type: application/json');
    
    try {
        $no = isset($_POST['no']) ? intval($_POST['no']) : 0;
        
        if(empty($no)) {
            throw new Exception('ID tidak valid');
        }
        
        // Get no_rawat for the record
        $query_check = bukaquery("SELECT no_rawat FROM riwayat_persalinan_ibu WHERE no = '$no'");
        $existing = mysqli_fetch_assoc($query_check);
        
        if(!$existing) {
            throw new Exception('Data tidak ditemukan');
        }
        
        $query_delete = "DELETE FROM riwayat_persalinan_ibu WHERE no = '$no'";
        $result = bukaquery($query_delete);
        
        if($result) {
            insertTracker($query_delete);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data riwayat persalinan berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus data riwayat persalinan');
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
// PROSES SIMPAN OBAT PULANG (FIXED 100% - FOLLOW EXACT PATTERN)
// ========================================
if ($aksi === 'simpan_obat_pulang') {
    
    try {
        // Ambil data dari POST
        $norawat = isset($_POST['norawat']) ? validTeks4($_POST['norawat'], 20) : '';
        $obat_pulang = isset($_POST['obat_pulang']) ? json_decode($_POST['obat_pulang'], true) : [];
        
        // Validasi input
        if (empty($norawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        if (empty($obat_pulang)) {
            throw new Exception('Tidak ada obat yang akan disimpan. Silakan tambahkan minimal 1 obat.');
        }
        
        // Ambil kode dokter dari session
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        // === GENERATE NO_PERMINTAAN OTOMATIS: RP + YYYYMMDD + 0001 ===
        $tanggal_now = date('Ymd');
        $prefix = "RP{$tanggal_now}";
        
        // Cari nomor urut terakhir hari ini
        $query_last = "SELECT no_permintaan FROM permintaan_resep_pulang 
                       WHERE no_permintaan LIKE '{$prefix}%' 
                       ORDER BY no_permintaan DESC LIMIT 1";
        $result_last = bukaquery($query_last);
        
        if (mysqli_num_rows($result_last) > 0) {
            $row = mysqli_fetch_assoc($result_last);
            $last_nopermintaan = $row['no_permintaan'];
            $last_number = intval(substr($last_nopermintaan, -4));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }
        
        $no_permintaan = $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
        
        // === TANGGAL DAN JAM SEKARANG ===
        $tgl_permintaan = date('Y-m-d');
        $jam = date('H:i:s');
        
        // === INSERT KE TABEL permintaan_resep_pulang (HEADER) ===
        $query_insert_header = "INSERT INTO permintaan_resep_pulang 
            (no_permintaan, tgl_permintaan, jam, no_rawat, kd_dokter, 
             status, tgl_validasi, jam_validasi) 
            VALUES 
            ('{$no_permintaan}', '{$tgl_permintaan}', '{$jam}', '{$norawat}', '{$kd_dokter}', 
             'Belum', '0000-00-00', '00:00:00')";
        
        $result_header = bukaquery($query_insert_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menyimpan permintaan obat pulang');
        }
        
        // ✅ TRACKING: Simpan query header (LANGSUNG tanpa if - seperti fungsi lain)
        insertTracker($query_insert_header);
        
        // === SIMPAN DETAIL OBAT ===
        $count_obat = 0;
        
        foreach ($obat_pulang as $obat) {
            $kode_brng = isset($obat['kode_brng']) ? validTeks4($obat['kode_brng'], 15) : '';
            $jml = isset($obat['jml']) ? floatval($obat['jml']) : 0;
            $dosis = isset($obat['aturan_pakai']) ? validTeks4($obat['aturan_pakai'], 150) : '';
            
            // Validasi
            if (empty($kode_brng) || $jml <= 0) {
                continue;
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
            
            // ✅ TRACKING: Simpan setiap query detail (LANGSUNG tanpa if - seperti fungsi lain)
            insertTracker($query_insert_detail);
            
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
// PROSES HAPUS OBAT PULANG (FIXED 100% - FOLLOW EXACT PATTERN)
// ========================================
if ($aksi === 'hapus_obat_pulang') {
    
    try {
        // Ambil no_permintaan dari POST
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20) : '';
        
        // Validasi input
        if (empty($no_permintaan)) {
            throw new Exception('Nomor permintaan tidak valid');
        }
        
        // Ambil kode dokter dari session
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        // Cek apakah permintaan ada dan milik dokter yang login
        $query_cek = "SELECT no_permintaan, no_rawat, status FROM permintaan_resep_pulang 
                      WHERE no_permintaan = '{$no_permintaan}' 
                      AND kd_dokter = '{$kd_dokter}'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Permintaan tidak ditemukan atau bukan milik Anda');
        }
        
        $row_permintaan = mysqli_fetch_assoc($result_cek);
        
        // Cek apakah sudah divalidasi farmasi
        if ($row_permintaan['status'] != 'Belum') {
            throw new Exception('Tidak bisa dihapus! Permintaan sudah divalidasi oleh farmasi.');
        }
        
        // === HAPUS DATA (URUTAN PENTING: DETAIL DULU, BARU HEADER) ===
        
        // 1. Hapus detail obat
        $query_delete_detail = "DELETE FROM detail_permintaan_resep_pulang 
                                WHERE no_permintaan = '{$no_permintaan}'";
        $result_detail = bukaquery($query_delete_detail);
        
        if (!$result_detail) {
            throw new Exception('Gagal menghapus detail obat pulang');
        }
        
        // ✅ TRACKING: Simpan query delete detail (LANGSUNG tanpa if - seperti fungsi lain)
        insertTracker($query_delete_detail);
        
        // 2. Hapus header permintaan
        $query_delete_header = "DELETE FROM permintaan_resep_pulang 
                                WHERE no_permintaan = '{$no_permintaan}'";
        $result_header = bukaquery($query_delete_header);
        
        if (!$result_header) {
            throw new Exception('Gagal menghapus permintaan obat pulang');
        }
        
        // ✅ TRACKING: Simpan query delete header (LANGSUNG tanpa if - seperti fungsi lain)
        insertTracker($query_delete_header);
        
        // === RESPONSE SUKSES ===
        echo json_encode([
            'status' => 'success',
            'message' => 'Permintaan obat pulang berhasil dihapus',
            'no_permintaan' => $no_permintaan
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
// SEARCH PASIEN IBU (AUTOCOMPLETE NEONATUS)
// ========================================
if ($aksi === 'search_pasien_ibu') {
    
    try {
        $keyword = isset($_POST['keyword']) ? mysqli_real_escape_string($koneksi, $_POST['keyword']) : '';
        
        if (empty($keyword) || strlen($keyword) < 2) {
            throw new Exception('Keyword terlalu pendek');
        }
        
        // Cari pasien berdasarkan no_rkm_medis atau nm_pasien (hanya perempuan untuk ibu)
        $query = "SELECT no_rkm_medis, nm_pasien, tgl_lahir, alamat 
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
                'nm_pasien' => $row['nm_pasien'],
                'tgl_lahir' => $row['tgl_lahir'],
                'alamat' => $row['alamat']
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
// GET RIWAYAT PERSALINAN IBU (DARI TABEL riwayat_persalinan_pasien)
// ========================================
if ($aksi === 'get_riwayat_persalinan_ibu') {
    
    try {
        $no_rkm_medis = isset($_POST['no_rkm_medis']) ? mysqli_real_escape_string($koneksi, $_POST['no_rkm_medis']) : '';
        
        if (empty($no_rkm_medis)) {
            throw new Exception('No. RM tidak valid');
        }
        
        // Ambil data dari tabel riwayat_persalinan_pasien
        $query = "SELECT tgl_thn, tempat_persalinan, usia_hamil, jenis_persalinan, 
                         penolong, penyulit, jk, bbpb, keadaan
                  FROM riwayat_persalinan_pasien 
                  WHERE no_rkm_medis = '{$no_rkm_medis}'
                  ORDER BY tgl_thn DESC";
        
        $result = bukaquery($query);
        $data = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'tgl_thn' => $row['tgl_thn'],
                'tempat_persalinan' => $row['tempat_persalinan'],
                'usia_hamil' => $row['usia_hamil'],
                'jenis_persalinan' => $row['jenis_persalinan'],
                'penolong' => $row['penolong'],
                'penyulit' => $row['penyulit'],
                'jk' => $row['jk'],
                'bbpb' => $row['bbpb'],
                'keadaan' => $row['keadaan']
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
// SIMPAN RIWAYAT PERSALINAN IBU (NEONATUS) - TABEL riwayat_persalinan_pasien
// ========================================
if ($aksi === 'simpan_riwayat_persalinan_pasien') {
    
    try {
        $no_rkm_medis = isset($_POST['no_rkm_medis']) ? validTeks4($_POST['no_rkm_medis'], 15) : '';
        
        if (empty($no_rkm_medis)) {
            throw new Exception('No. RM Ibu harus dipilih terlebih dahulu');
        }
        
        // Ambil data dari POST
        $tgl_thn = isset($_POST['tgl_thn']) ? validTeks4($_POST['tgl_thn'], 12) : '';
        $tempat_persalinan = isset($_POST['tempat_persalinan']) ? validTeks4($_POST['tempat_persalinan'], 30) : '';
        $usia_hamil = isset($_POST['usia_hamil']) ? validTeks4($_POST['usia_hamil'], 20) : '';
        $jenis_persalinan = isset($_POST['jenis_persalinan']) ? validTeks4($_POST['jenis_persalinan'], 20) : '';
        $penolong = isset($_POST['penolong']) ? validTeks4($_POST['penolong'], 30) : '';
        $penyulit = isset($_POST['penyulit']) ? validTeks4($_POST['penyulit'], 40) : '';
        $jk = isset($_POST['jk']) ? validTeks4($_POST['jk'], 1) : '';
        $bbpb = isset($_POST['bbpb']) ? validTeks4($_POST['bbpb'], 10) : '';
        $keadaan = isset($_POST['keadaan']) ? validTeks4($_POST['keadaan'], 40) : '';
        
        // Insert ke tabel riwayat_persalinan_pasien
        $query = "INSERT INTO riwayat_persalinan_pasien 
                  (no_rkm_medis, tgl_thn, tempat_persalinan, usia_hamil, jenis_persalinan, 
                   penolong, penyulit, jk, bbpb, keadaan) 
                  VALUES 
                  ('{$no_rkm_medis}', '{$tgl_thn}', '{$tempat_persalinan}', '{$usia_hamil}', '{$jenis_persalinan}',
                   '{$penolong}', '{$penyulit}', '{$jk}', '{$bbpb}', '{$keadaan}')";
        
        $result = bukaquery($query);
        
        if ($result) {
            // Insert tracker
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Riwayat persalinan berhasil disimpan'
            ]);
        } else {
            throw new Exception('Gagal menyimpan data riwayat persalinan');
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
// HAPUS RIWAYAT PERSALINAN IBU (NEONATUS) - TABEL riwayat_persalinan_pasien
// ========================================
if ($aksi === 'hapus_riwayat_persalinan_pasien') {
    
    try {
        $no_rkm_medis = isset($_POST['no_rkm_medis']) ? validTeks4($_POST['no_rkm_medis'], 15) : '';
        $tgl_thn = isset($_POST['tgl_thn']) ? validTeks4($_POST['tgl_thn'], 12) : '';
        
        if (empty($no_rkm_medis) || empty($tgl_thn)) {
            throw new Exception('Data tidak valid untuk dihapus');
        }
        
        // Hapus dari tabel riwayat_persalinan_pasien
        $query = "DELETE FROM riwayat_persalinan_pasien 
                  WHERE no_rkm_medis = '{$no_rkm_medis}' 
                  AND tgl_thn = '{$tgl_thn}'
                  LIMIT 1";
        
        $result = bukaquery($query);
        
        if ($result) {
            // Insert tracker
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Riwayat persalinan berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus data riwayat persalinan');
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
// SIMPAN PENILAIAN MEDIS RANAP NEONATUS
// ========================================
if ($aksi === 'simpan_penilaian_medis_neonatus') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Ambil kd_dokter dari session
        $kd_dokter = '';
        if(isset($_SESSION['ses_dokter']) && !empty($_SESSION['ses_dokter'])) {
            $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        }
        
        // Ambil semua field dari POST
        $tanggal = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 20) : date('Y-m-d H:i:s');
        $no_rkm_medis_ibu = isset($_POST['no_rkm_medis_ibu']) ? validTeks4($_POST['no_rkm_medis_ibu'], 15) : '';
        $g = isset($_POST['g']) ? validTeks4($_POST['g'], 10) : '';
        $p = isset($_POST['p']) ? validTeks4($_POST['p'], 10) : '';
        $a = isset($_POST['a']) ? validTeks4($_POST['a'], 10) : '';
        $hidup = isset($_POST['hidup']) ? validTeks4($_POST['hidup'], 10) : '';
        $usiahamil = isset($_POST['usiahamil']) ? validTeks4($_POST['usiahamil'], 10) : '';
        // Field ENUM - gunakan nilai langsung tanpa escape berlebih
        $hbsag_raw = isset($_POST['hbsag']) ? trim($_POST['hbsag']) : 'Tidak Ada Keterangan';
        $hiv_raw = isset($_POST['hiv']) ? trim($_POST['hiv']) : 'Tidak Ada Keterangan';
        $syphilis_raw = isset($_POST['syphilis']) ? trim($_POST['syphilis']) : 'Tidak Ada Keterangan';
        
        // Validasi ENUM values
        $valid_skrining = ['Negatif (-)', 'Positif (+)', 'Tidak Ada Keterangan'];
        $hbsag = in_array($hbsag_raw, $valid_skrining) ? $hbsag_raw : 'Tidak Ada Keterangan';
        $hiv = in_array($hiv_raw, $valid_skrining) ? $hiv_raw : 'Tidak Ada Keterangan';
        $syphilis = in_array($syphilis_raw, $valid_skrining) ? $syphilis_raw : 'Tidak Ada Keterangan';
        $riwayat_obstetri_ibu = isset($_POST['riwayat_obstetri_ibu']) ? validTeks4($_POST['riwayat_obstetri_ibu'], 50) : '';
        $keterangan_riwayat_obstetri_ibu = isset($_POST['keterangan_riwayat_obstetri_ibu']) ? validTeks4($_POST['keterangan_riwayat_obstetri_ibu'], 70) : '';
        $faktor_risiko_neonatal = isset($_POST['faktor_risiko_neonatal']) ? validTeks4($_POST['faktor_risiko_neonatal'], 50) : '';
        $keterangan_faktor_risiko_neonatal = isset($_POST['keterangan_faktor_risiko_neonatal']) ? validTeks4($_POST['keterangan_faktor_risiko_neonatal'], 70) : '';
        $tanggal_persalinan = isset($_POST['tanggal_persalinan']) ? validTeks4($_POST['tanggal_persalinan'], 20) : '';
        $bersalin_di = isset($_POST['bersalin_di']) ? validTeks4($_POST['bersalin_di'], 70) : '';
        $inisiasi_menyusui = isset($_POST['inisiasi_menyusui']) ? validTeks4($_POST['inisiasi_menyusui'], 10) : '';
        $jenis_persalinan = isset($_POST['jenis_persalinan']) ? validTeks4($_POST['jenis_persalinan'], 30) : '';
        $indikasi = isset($_POST['indikasi']) ? validTeks4($_POST['indikasi'], 70) : '';
        $aterm = isset($_POST['aterm']) ? validTeks4($_POST['aterm'], 10) : '';
        $bernafas = isset($_POST['bernafas']) ? validTeks4($_POST['bernafas'], 10) : '';
        $tanus_otot = isset($_POST['tanus_otot']) ? validTeks4($_POST['tanus_otot'], 10) : '';
        $cairan_amnion = isset($_POST['cairan_amnion']) ? validTeks4($_POST['cairan_amnion'], 10) : '';
        
        // APGAR Score
        $f1 = isset($_POST['f1']) ? validTeks4($_POST['f1'], 1) : '';
        $u1 = isset($_POST['u1']) ? validTeks4($_POST['u1'], 1) : '';
        $t1 = isset($_POST['t1']) ? validTeks4($_POST['t1'], 1) : '';
        $r1 = isset($_POST['r1']) ? validTeks4($_POST['r1'], 1) : '';
        $w1 = isset($_POST['w1']) ? validTeks4($_POST['w1'], 1) : '';
        $n1 = isset($_POST['n1']) ? validTeks4($_POST['n1'], 2) : '';
        $f5 = isset($_POST['f5']) ? validTeks4($_POST['f5'], 1) : '';
        $u5 = isset($_POST['u5']) ? validTeks4($_POST['u5'], 1) : '';
        $t5 = isset($_POST['t5']) ? validTeks4($_POST['t5'], 1) : '';
        $r5 = isset($_POST['r5']) ? validTeks4($_POST['r5'], 1) : '';
        $w5 = isset($_POST['w5']) ? validTeks4($_POST['w5'], 1) : '';
        $n5 = isset($_POST['n5']) ? validTeks4($_POST['n5'], 2) : '';
        $f10 = isset($_POST['f10']) ? validTeks4($_POST['f10'], 1) : '';
        $u10 = isset($_POST['u10']) ? validTeks4($_POST['u10'], 1) : '';
        $t10 = isset($_POST['t10']) ? validTeks4($_POST['t10'], 1) : '';
        $r10 = isset($_POST['r10']) ? validTeks4($_POST['r10'], 1) : '';
        $w10 = isset($_POST['w10']) ? validTeks4($_POST['w10'], 1) : '';
        $n10 = isset($_POST['n10']) ? validTeks4($_POST['n10'], 2) : '';
        
        // Down Score
        // Down Score - Field ENUM
        $frekuensi_napas_raw = isset($_POST['frekuensi_napas']) ? trim($_POST['frekuensi_napas']) : '< 60';
        $valid_frekuensi = ['< 60', '60 - 80', '> 80'];
        $frekuensi_napas = in_array($frekuensi_napas_raw, $valid_frekuensi) ? $frekuensi_napas_raw : '< 60';
        $nilai_frekuensi_napas = isset($_POST['nilai_frekuensi_napas']) ? intval($_POST['nilai_frekuensi_napas']) : 0;
        
        $retraksi_raw = isset($_POST['retraksi']) ? trim($_POST['retraksi']) : 'Tidak Ada';
        $valid_retraksi = ['Tidak Ada', 'Retraksi Ringan', 'Retraksi Berat'];
        $retraksi = in_array($retraksi_raw, $valid_retraksi) ? $retraksi_raw : 'Tidak Ada';
        $nilai_retraksi = isset($_POST['nilai_retraksi']) ? intval($_POST['nilai_retraksi']) : 0;
        
        $sianosis_raw = isset($_POST['sianosis']) ? trim($_POST['sianosis']) : 'Tidak Ada';
        $valid_sianosis = ['Tidak Ada', 'Hilang Dengan O2', 'Tidak Hilang Dengan O2'];
        $sianosis = in_array($sianosis_raw, $valid_sianosis) ? $sianosis_raw : 'Tidak Ada';
        $nilai_sianosis = isset($_POST['nilai_sianosis']) ? intval($_POST['nilai_sianosis']) : 0;
        
        $jalan_masuk_udara_raw = isset($_POST['jalan_masuk_udara']) ? trim($_POST['jalan_masuk_udara']) : 'Baik';
        $valid_jalan = ['Baik', 'Penurunan Ringan Udara Masuk', 'Tidak Ada Udara Masuk'];
        $jalan_masuk_udara = in_array($jalan_masuk_udara_raw, $valid_jalan) ? $jalan_masuk_udara_raw : 'Baik';
        $nilai_jalan_masuk_udara = isset($_POST['nilai_jalan_masuk_udara']) ? intval($_POST['nilai_jalan_masuk_udara']) : 0;
        
        $grunting_raw = isset($_POST['grunting']) ? trim($_POST['grunting']) : 'Tidak Ada';
        $valid_grunting = ['Tidak Ada', 'Dapat Didengar Dengan Stetoskop', 'Dapat Didengar Tanpa Stetoskop'];
        $grunting = in_array($grunting_raw, $valid_grunting) ? $grunting_raw : 'Tidak Ada';
        $nilai_grunting = isset($_POST['nilai_grunting']) ? intval($_POST['nilai_grunting']) : 0;
        $total_down_score = isset($_POST['total_down_score']) ? intval($_POST['total_down_score']) : 0;
        $keterangan_down_Score = isset($_POST['keterangan_down_Score']) ? validTeks4($_POST['keterangan_down_Score'], 40) : '';
        
        // Vital Signs
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $rr = isset($_POST['rr']) ? validTeks4($_POST['rr'], 5) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $saturasi = isset($_POST['saturasi']) ? validTeks4($_POST['saturasi'], 5) : '';
        $bb = isset($_POST['bb']) ? validTeks4($_POST['bb'], 5) : '';
        $pb = isset($_POST['pb']) ? validTeks4($_POST['pb'], 5) : '';
        $lk = isset($_POST['lk']) ? validTeks4($_POST['lk'], 5) : '';
        $ld = isset($_POST['ld']) ? validTeks4($_POST['ld'], 5) : '';
        
        // Pemeriksaan Fisik
        $keadaan_umum = isset($_POST['keadaan_umum']) ? validTeks4($_POST['keadaan_umum'], 20) : '';
        $keterangan_keadaan_umum = isset($_POST['keterangan_keadaan_umum']) ? validTeks4($_POST['keterangan_keadaan_umum'], 50) : '';
        $kulit = isset($_POST['kulit']) ? validTeks4($_POST['kulit'], 20) : '';
        $keterangan_kulit = isset($_POST['keterangan_kulit']) ? validTeks4($_POST['keterangan_kulit'], 50) : '';
        $kepala = isset($_POST['kepala']) ? validTeks4($_POST['kepala'], 20) : '';
        $keterangan_kepala = isset($_POST['keterangan_kepala']) ? validTeks4($_POST['keterangan_kepala'], 50) : '';
        $mata = isset($_POST['mata']) ? validTeks4($_POST['mata'], 20) : '';
        $keterangan_mata = isset($_POST['keterangan_mata']) ? validTeks4($_POST['keterangan_mata'], 50) : '';
        $telinga = isset($_POST['telinga']) ? validTeks4($_POST['telinga'], 20) : '';
        $keterangan_telinga = isset($_POST['keterangan_telinga']) ? validTeks4($_POST['keterangan_telinga'], 50) : '';
        $hidung = isset($_POST['hidung']) ? validTeks4($_POST['hidung'], 20) : '';
        $keterangan_hidung = isset($_POST['keterangan_hidung']) ? validTeks4($_POST['keterangan_hidung'], 50) : '';
        $mulut = isset($_POST['mulut']) ? validTeks4($_POST['mulut'], 20) : '';
        $keterangan_mulut = isset($_POST['keterangan_mulut']) ? validTeks4($_POST['keterangan_mulut'], 50) : '';
        $tenggorokan = isset($_POST['tenggorokan']) ? validTeks4($_POST['tenggorokan'], 20) : '';
        $keterangan_tenggorokan = isset($_POST['keterangan_tenggorokan']) ? validTeks4($_POST['keterangan_tenggorokan'], 50) : '';
        $leher = isset($_POST['leher']) ? validTeks4($_POST['leher'], 20) : '';
        $keterangan_leher = isset($_POST['keterangan_leher']) ? validTeks4($_POST['keterangan_leher'], 50) : '';
        $thorax = isset($_POST['thorax']) ? validTeks4($_POST['thorax'], 20) : '';
        $keterangan_thorax = isset($_POST['keterangan_thorax']) ? validTeks4($_POST['keterangan_thorax'], 50) : '';
        $abdomen = isset($_POST['abdomen']) ? validTeks4($_POST['abdomen'], 20) : '';
        $keterangan_abdomen = isset($_POST['keterangan_abdomen']) ? validTeks4($_POST['keterangan_abdomen'], 50) : '';
        $genitalia = isset($_POST['genitalia']) ? validTeks4($_POST['genitalia'], 20) : '';
        $keterangan_genitalia = isset($_POST['keterangan_genitalia']) ? validTeks4($_POST['keterangan_genitalia'], 50) : '';
        $anus = isset($_POST['anus']) ? validTeks4($_POST['anus'], 20) : '';
        $keterangan_anus = isset($_POST['keterangan_anus']) ? validTeks4($_POST['keterangan_anus'], 50) : '';
        $muskulos = isset($_POST['muskulos']) ? validTeks4($_POST['muskulos'], 20) : '';
        $keterangan_muskulos = isset($_POST['keterangan_muskulos']) ? validTeks4($_POST['keterangan_muskulos'], 50) : '';
        $ekstrimitas = isset($_POST['ekstrimitas']) ? validTeks4($_POST['ekstrimitas'], 20) : '';
        $keterangan_ekstrimitas = isset($_POST['keterangan_ekstrimitas']) ? validTeks4($_POST['keterangan_ekstrimitas'], 50) : '';
        $paru = isset($_POST['paru']) ? validTeks4($_POST['paru'], 20) : '';
        $keterangan_paru = isset($_POST['keterangan_paru']) ? validTeks4($_POST['keterangan_paru'], 50) : '';
        $refleks = isset($_POST['refleks']) ? validTeks4($_POST['refleks'], 20) : '';
        $keterangan_refleks = isset($_POST['keterangan_refleks']) ? validTeks4($_POST['keterangan_refleks'], 50) : '';
        $kelainan_lainnya = isset($_POST['kelainan_lainnya']) ? validTeks4($_POST['kelainan_lainnya'], 80) : '';
        
        // Pemeriksaan Penunjang
        $pemeriksaan_regional = isset($_POST['pemeriksaan_regional']) ? validTeks4($_POST['pemeriksaan_regional'], 500) : '';
        $lab = isset($_POST['lab']) ? validTeks4($_POST['lab'], 500) : '';
        $radiologi = isset($_POST['radiologi']) ? validTeks4($_POST['radiologi'], 500) : '';
        $penunjanglainnya = isset($_POST['penunjanglainnya']) ? validTeks4($_POST['penunjanglainnya'], 500) : '';
        
        // Diagnosis & Tatalaksana
        $diagnosis = isset($_POST['diagnosis']) ? validTeks4($_POST['diagnosis'], 500) : '';
        $tata = isset($_POST['tata']) ? validTeks4($_POST['tata'], 2000) : '';
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 1000) : '';
        
        // Cek apakah data sudah ada
        $cek = bukaquery("SELECT no_rawat FROM penilaian_medis_ranap_neonatus WHERE no_rawat = '$no_rawat'");
        
        if(mysqli_num_rows($cek) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_medis_ranap_neonatus SET 
                tanggal = '$tanggal',
                kd_dokter = '$kd_dokter',
                no_rkm_medis_ibu = '$no_rkm_medis_ibu',
                g = '$g',
                p = '$p',
                a = '$a',
                hidup = '$hidup',
                usiahamil = '$usiahamil',
                hbsag = '$hbsag',
                hiv = '$hiv',
                syphilis = '$syphilis',
                riwayat_obstetri_ibu = '$riwayat_obstetri_ibu',
                keterangan_riwayat_obstetri_ibu = '$keterangan_riwayat_obstetri_ibu',
                faktor_risiko_neonatal = '$faktor_risiko_neonatal',
                keterangan_faktor_risiko_neonatal = '$keterangan_faktor_risiko_neonatal',
                tanggal_persalinan = '$tanggal_persalinan',
                bersalin_di = '$bersalin_di',
                inisiasi_menyusui = '$inisiasi_menyusui',
                jenis_persalinan = '$jenis_persalinan',
                indikasi = '$indikasi',
                aterm = '$aterm',
                bernafas = '$bernafas',
                tanus_otot = '$tanus_otot',
                cairan_amnion = '$cairan_amnion',
                f1 = '$f1', u1 = '$u1', t1 = '$t1', r1 = '$r1', w1 = '$w1', n1 = '$n1',
                f5 = '$f5', u5 = '$u5', t5 = '$t5', r5 = '$r5', w5 = '$w5', n5 = '$n5',
                f10 = '$f10', u10 = '$u10', t10 = '$t10', r10 = '$r10', w10 = '$w10', n10 = '$n10',
                frekuensi_napas = '$frekuensi_napas',
                nilai_frekuensi_napas = '$nilai_frekuensi_napas',
                retraksi = '$retraksi',
                nilai_retraksi = '$nilai_retraksi',
                sianosis = '$sianosis',
                nilai_sianosis = '$nilai_sianosis',
                jalan_masuk_udara = '$jalan_masuk_udara',
                nilai_jalan_masuk_udara = '$nilai_jalan_masuk_udara',
                grunting = '$grunting',
                nilai_grunting = '$nilai_grunting',
                total_down_score = '$total_down_score',
                keterangan_down_Score = '$keterangan_down_Score',
                nadi = '$nadi',
                rr = '$rr',
                suhu = '$suhu',
                saturasi = '$saturasi',
                bb = '$bb',
                pb = '$pb',
                lk = '$lk',
                ld = '$ld',
                keadaan_umum = '$keadaan_umum',
                keterangan_keadaan_umum = '$keterangan_keadaan_umum',
                kulit = '$kulit',
                keterangan_kulit = '$keterangan_kulit',
                kepala = '$kepala',
                keterangan_kepala = '$keterangan_kepala',
                mata = '$mata',
                keterangan_mata = '$keterangan_mata',
                telinga = '$telinga',
                keterangan_telinga = '$keterangan_telinga',
                hidung = '$hidung',
                keterangan_hidung = '$keterangan_hidung',
                mulut = '$mulut',
                keterangan_mulut = '$keterangan_mulut',
                tenggorokan = '$tenggorokan',
                keterangan_tenggorokan = '$keterangan_tenggorokan',
                leher = '$leher',
                keterangan_leher = '$keterangan_leher',
                thorax = '$thorax',
                keterangan_thorax = '$keterangan_thorax',
                abdomen = '$abdomen',
                keterangan_abdomen = '$keterangan_abdomen',
                genitalia = '$genitalia',
                keterangan_genitalia = '$keterangan_genitalia',
                anus = '$anus',
                keterangan_anus = '$keterangan_anus',
                muskulos = '$muskulos',
                keterangan_muskulos = '$keterangan_muskulos',
                ekstrimitas = '$ekstrimitas',
                keterangan_ekstrimitas = '$keterangan_ekstrimitas',
                paru = '$paru',
                keterangan_paru = '$keterangan_paru',
                refleks = '$refleks',
                keterangan_refleks = '$keterangan_refleks',
                kelainan_lainnya = '$kelainan_lainnya',
                pemeriksaan_regional = '$pemeriksaan_regional',
                lab = '$lab',
                radiologi = '$radiologi',
                penunjanglainnya = '$penunjanglainnya',
                diagnosis = '$diagnosis',
                tata = '$tata',
                edukasi = '$edukasi'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data berhasil diupdate';
        } else {
            // INSERT
            $query = "INSERT INTO penilaian_medis_ranap_neonatus (
                no_rawat, tanggal, kd_dokter, no_rkm_medis_ibu,
                g, p, a, hidup, usiahamil,
                hbsag, hiv, syphilis,
                riwayat_obstetri_ibu, keterangan_riwayat_obstetri_ibu,
                faktor_risiko_neonatal, keterangan_faktor_risiko_neonatal,
                tanggal_persalinan, bersalin_di, inisiasi_menyusui, jenis_persalinan, indikasi,
                aterm, bernafas, tanus_otot, cairan_amnion,
                f1, u1, t1, r1, w1, n1,
                f5, u5, t5, r5, w5, n5,
                f10, u10, t10, r10, w10, n10,
                frekuensi_napas, nilai_frekuensi_napas,
                retraksi, nilai_retraksi,
                sianosis, nilai_sianosis,
                jalan_masuk_udara, nilai_jalan_masuk_udara,
                grunting, nilai_grunting,
                total_down_score, keterangan_down_Score,
                nadi, rr, suhu, saturasi, bb, pb, lk, ld,
                keadaan_umum, keterangan_keadaan_umum,
                kulit, keterangan_kulit,
                kepala, keterangan_kepala,
                mata, keterangan_mata,
                telinga, keterangan_telinga,
                hidung, keterangan_hidung,
                mulut, keterangan_mulut,
                tenggorokan, keterangan_tenggorokan,
                leher, keterangan_leher,
                thorax, keterangan_thorax,
                abdomen, keterangan_abdomen,
                genitalia, keterangan_genitalia,
                anus, keterangan_anus,
                muskulos, keterangan_muskulos,
                ekstrimitas, keterangan_ekstrimitas,
                paru, keterangan_paru,
                refleks, keterangan_refleks,
                kelainan_lainnya,
                pemeriksaan_regional, lab, radiologi, penunjanglainnya,
                diagnosis, tata, edukasi
            ) VALUES (
                '$no_rawat', '$tanggal', '$kd_dokter', '$no_rkm_medis_ibu',
                '$g', '$p', '$a', '$hidup', '$usiahamil',
                '$hbsag', '$hiv', '$syphilis',
                '$riwayat_obstetri_ibu', '$keterangan_riwayat_obstetri_ibu',
                '$faktor_risiko_neonatal', '$keterangan_faktor_risiko_neonatal',
                '$tanggal_persalinan', '$bersalin_di', '$inisiasi_menyusui', '$jenis_persalinan', '$indikasi',
                '$aterm', '$bernafas', '$tanus_otot', '$cairan_amnion',
                '$f1', '$u1', '$t1', '$r1', '$w1', '$n1',
                '$f5', '$u5', '$t5', '$r5', '$w5', '$n5',
                '$f10', '$u10', '$t10', '$r10', '$w10', '$n10',
                '$frekuensi_napas', '$nilai_frekuensi_napas',
                '$retraksi', '$nilai_retraksi',
                '$sianosis', '$nilai_sianosis',
                '$jalan_masuk_udara', '$nilai_jalan_masuk_udara',
                '$grunting', '$nilai_grunting',
                '$total_down_score', '$keterangan_down_Score',
                '$nadi', '$rr', '$suhu', '$saturasi', '$bb', '$pb', '$lk', '$ld',
                '$keadaan_umum', '$keterangan_keadaan_umum',
                '$kulit', '$keterangan_kulit',
                '$kepala', '$keterangan_kepala',
                '$mata', '$keterangan_mata',
                '$telinga', '$keterangan_telinga',
                '$hidung', '$keterangan_hidung',
                '$mulut', '$keterangan_mulut',
                '$tenggorokan', '$keterangan_tenggorokan',
                '$leher', '$keterangan_leher',
                '$thorax', '$keterangan_thorax',
                '$abdomen', '$keterangan_abdomen',
                '$genitalia', '$keterangan_genitalia',
                '$anus', '$keterangan_anus',
                '$muskulos', '$keterangan_muskulos',
                '$ekstrimitas', '$keterangan_ekstrimitas',
                '$paru', '$keterangan_paru',
                '$refleks', '$keterangan_refleks',
                '$kelainan_lainnya',
                '$pemeriksaan_regional', '$lab', '$radiologi', '$penunjanglainnya',
                '$diagnosis', '$tata', '$edukasi'
            )";
            $msg = 'Data berhasil disimpan';
        }
        
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data: ' . mysqli_error($GLOBALS['db_conn']));
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS PENILAIAN MEDIS RANAP NEONATUS
// ========================================
if ($aksi === 'hapus_penilaian_medis_neonatus') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $query = "DELETE FROM penilaian_medis_ranap_neonatus WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// SIMPAN PENILAIAN MEDIS RANAP KEBIDANAN
// ========================================
if ($aksi === 'simpan_penilaian_medis_kebidanan') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Ambil kd_dokter dari session
        $kd_dokter = '';
        if(isset($_SESSION['ses_dokter']) && !empty($_SESSION['ses_dokter'])) {
            $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        }
        
        // Ambil semua field dari POST
        $tanggal = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 20) : date('Y-m-d H:i:s');
        $anamnesis = isset($_POST['anamnesis']) ? validTeks4($_POST['anamnesis'], 20) : 'Autoanamnesis';
        $hubungan = isset($_POST['hubungan']) ? validTeks4($_POST['hubungan'], 100) : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps = isset($_POST['rps']) ? validTeks4($_POST['rps'], 2000) : '';
        $rpd = isset($_POST['rpd']) ? validTeks4($_POST['rpd'], 1000) : '';
        $rpk = isset($_POST['rpk']) ? validTeks4($_POST['rpk'], 1000) : '';
        $rpo = isset($_POST['rpo']) ? validTeks4($_POST['rpo'], 1000) : '';
        $alergi = isset($_POST['alergi']) ? validTeks4($_POST['alergi'], 100) : '';
        
        // Pemeriksaan fisik
        $keadaan = isset($_POST['keadaan']) ? validTeks4($_POST['keadaan'], 20) : 'Sehat';
        $gcs = isset($_POST['gcs']) ? validTeks4($_POST['gcs'], 10) : '';
        $kesadaran = isset($_POST['kesadaran']) ? validTeks4($_POST['kesadaran'], 20) : 'Compos Mentis';
        $td = isset($_POST['td']) ? validTeks4($_POST['td'], 8) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $rr = isset($_POST['rr']) ? validTeks4($_POST['rr'], 5) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $spo = isset($_POST['spo']) ? validTeks4($_POST['spo'], 5) : '';
        $bb = isset($_POST['bb']) ? validTeks4($_POST['bb'], 5) : '';
        $tb = isset($_POST['tb']) ? validTeks4($_POST['tb'], 5) : '';
        
        // Status organ
        $kepala = isset($_POST['kepala']) ? validTeks4($_POST['kepala'], 20) : 'Normal';
        $mata = isset($_POST['mata']) ? validTeks4($_POST['mata'], 20) : 'Normal';
        $gigi = isset($_POST['gigi']) ? validTeks4($_POST['gigi'], 20) : 'Normal';
        $tht = isset($_POST['tht']) ? validTeks4($_POST['tht'], 20) : 'Normal';
        $thoraks = isset($_POST['thoraks']) ? validTeks4($_POST['thoraks'], 20) : 'Normal';
        $jantung = isset($_POST['jantung']) ? validTeks4($_POST['jantung'], 20) : 'Normal';
        $paru = isset($_POST['paru']) ? validTeks4($_POST['paru'], 20) : 'Normal';
        $abdomen = isset($_POST['abdomen']) ? validTeks4($_POST['abdomen'], 20) : 'Normal';
        $genital = isset($_POST['genital']) ? validTeks4($_POST['genital'], 20) : 'Normal';
        $ekstremitas = isset($_POST['ekstremitas']) ? validTeks4($_POST['ekstremitas'], 20) : 'Normal';
        $kulit = isset($_POST['kulit']) ? validTeks4($_POST['kulit'], 20) : 'Normal';
        $ket_fisik = isset($_POST['ket_fisik']) ? validTeks4($_POST['ket_fisik'], 5000) : '';
        
        // Status Obstetri/Ginekologi
        $tfu = isset($_POST['tfu']) ? validTeks4($_POST['tfu'], 10) : '';
        $tbj = isset($_POST['tbj']) ? validTeks4($_POST['tbj'], 10) : '';
        $his = isset($_POST['his']) ? validTeks4($_POST['his'], 10) : '';
        $kontraksi = isset($_POST['kontraksi']) ? validTeks4($_POST['kontraksi'], 10) : 'Ada';
        $djj = isset($_POST['djj']) ? validTeks4($_POST['djj'], 10) : '';
        $inspeksi = isset($_POST['inspeksi']) ? validTeks4($_POST['inspeksi'], 5000) : '';
        $inspekulo = isset($_POST['inspekulo']) ? validTeks4($_POST['inspekulo'], 5000) : '';
        $vt = isset($_POST['vt']) ? validTeks4($_POST['vt'], 5000) : '';
        $rt = isset($_POST['rt']) ? validTeks4($_POST['rt'], 5000) : '';
        
        // Pemeriksaan Penunjang
        $ultra = isset($_POST['ultra']) ? validTeks4($_POST['ultra'], 5000) : '';
        $kardio = isset($_POST['kardio']) ? validTeks4($_POST['kardio'], 5000) : '';
        $lab = isset($_POST['lab']) ? validTeks4($_POST['lab'], 5000) : '';
        
        // Diagnosis & Tatalaksana
        $diagnosis = isset($_POST['diagnosis']) ? validTeks4($_POST['diagnosis'], 500) : '';
        $tata = isset($_POST['tata']) ? validTeks4($_POST['tata'], 5000) : '';
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 1000) : '';
        
        // Cek apakah data sudah ada
        $cek = bukaquery("SELECT no_rawat FROM penilaian_medis_ranap_kandungan WHERE no_rawat = '$no_rawat'");
        
        if(mysqli_num_rows($cek) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_medis_ranap_kandungan SET 
                tanggal = '$tanggal',
                kd_dokter = '$kd_dokter',
                anamnesis = '$anamnesis',
                hubungan = '$hubungan',
                keluhan_utama = '$keluhan_utama',
                rps = '$rps',
                rpd = '$rpd',
                rpk = '$rpk',
                rpo = '$rpo',
                alergi = '$alergi',
                keadaan = '$keadaan',
                gcs = '$gcs',
                kesadaran = '$kesadaran',
                td = '$td',
                nadi = '$nadi',
                rr = '$rr',
                suhu = '$suhu',
                spo = '$spo',
                bb = '$bb',
                tb = '$tb',
                kepala = '$kepala',
                mata = '$mata',
                gigi = '$gigi',
                tht = '$tht',
                thoraks = '$thoraks',
                jantung = '$jantung',
                paru = '$paru',
                abdomen = '$abdomen',
                genital = '$genital',
                ekstremitas = '$ekstremitas',
                kulit = '$kulit',
                ket_fisik = '$ket_fisik',
                tfu = '$tfu',
                tbj = '$tbj',
                his = '$his',
                kontraksi = '$kontraksi',
                djj = '$djj',
                inspeksi = '$inspeksi',
                inspekulo = '$inspekulo',
                vt = '$vt',
                rt = '$rt',
                ultra = '$ultra',
                kardio = '$kardio',
                lab = '$lab',
                diagnosis = '$diagnosis',
                tata = '$tata',
                edukasi = '$edukasi'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data berhasil diupdate';
        } else {
            // INSERT
            $query = "INSERT INTO penilaian_medis_ranap_kandungan (
                no_rawat, tanggal, kd_dokter, anamnesis, hubungan, keluhan_utama, rps, rpd, rpk, rpo, alergi,
                keadaan, gcs, kesadaran, td, nadi, rr, suhu, spo, bb, tb,
                kepala, mata, gigi, tht, thoraks, jantung, paru, abdomen, genital, ekstremitas, kulit, ket_fisik,
                tfu, tbj, his, kontraksi, djj, inspeksi, inspekulo, vt, rt,
                ultra, kardio, lab, diagnosis, tata, edukasi
            ) VALUES (
                '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan', '$keluhan_utama', '$rps', '$rpd', '$rpk', '$rpo', '$alergi',
                '$keadaan', '$gcs', '$kesadaran', '$td', '$nadi', '$rr', '$suhu', '$spo', '$bb', '$tb',
                '$kepala', '$mata', '$gigi', '$tht', '$thoraks', '$jantung', '$paru', '$abdomen', '$genital', '$ekstremitas', '$kulit', '$ket_fisik',
                '$tfu', '$tbj', '$his', '$kontraksi', '$djj', '$inspeksi', '$inspekulo', '$vt', '$rt',
                '$ultra', '$kardio', '$lab', '$diagnosis', '$tata', '$edukasi'
            )";
            $msg = 'Data berhasil disimpan';
        }
        
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data: ' . mysqli_error($GLOBALS['db_conn']));
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS PENILAIAN MEDIS RANAP KEBIDANAN
// ========================================
if ($aksi === 'hapus_penilaian_medis_kebidanan') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $query = "DELETE FROM penilaian_medis_ranap_kandungan WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// SIMPAN LAPORAN OPERASI
// ========================================
if ($aksi === 'simpan_laporan_operasi') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // =============================================
        // DELETE + INSERT TABEL LAPORAN_OPERASI
        // =============================================
        $cekTabelLaporan = bukaquery("SHOW TABLES LIKE 'laporan_operasi'");
        
        if($cekTabelLaporan && mysqli_num_rows($cekTabelLaporan) > 0) {
            
            // Ambil data dari POST
            $tanggal_baru = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 19) : '';
            $diagnosa_preop = isset($_POST['diagnosa_preop']) ? validTeks4($_POST['diagnosa_preop'], 100) : '';
            $diagnosa_postop = isset($_POST['diagnosa_postop']) ? validTeks4($_POST['diagnosa_postop'], 100) : '';
            $jaringan_dieksekusi = isset($_POST['jaringan_dieksekusi']) ? validTeks4($_POST['jaringan_dieksekusi'], 100) : '';
            $selesaioperasi = isset($_POST['selesaioperasi']) ? validTeks4($_POST['selesaioperasi'], 19) : '';
            $permintaan_pa = isset($_POST['permintaan_pa']) ? validTeks4($_POST['permintaan_pa'], 5) : 'Tidak';
            $nomor_implan = isset($_POST['nomor_implan']) ? validTeks4($_POST['nomor_implan'], 50) : '';
            $laporan_operasi_text = isset($_POST['laporan_operasi']) ? validTeks4($_POST['laporan_operasi'], 5000) : '';
            
            // 1. DELETE data lama (jika ada)
            $cekLaporan = bukaquery("SELECT no_rawat, tanggal FROM laporan_operasi WHERE no_rawat = '$no_rawat' LIMIT 1");
            if ($cekLaporan && mysqli_num_rows($cekLaporan) > 0) {
                $dataLaporan = mysqli_fetch_array($cekLaporan);
                $tanggal_existing = $dataLaporan['tanggal'];
                
                $queryDelete = "DELETE FROM laporan_operasi WHERE no_rawat = '$no_rawat' AND tanggal = '$tanggal_existing'";
                $resultDelete = bukaquery($queryDelete);
                if ($resultDelete) {
                    insertTracker($queryDelete);
                }
            }
            
            // 2. INSERT data baru
            // Handle empty datetime → default ke sekarang untuk tanggal
            $tanggal_insert = !empty($tanggal_baru) ? $tanggal_baru : date('Y-m-d H:i:s');
            $selesai_insert = !empty($selesaioperasi) ? $selesaioperasi : '';
            
            // Cek kolom yang ada di tabel
            $kolomAda = [];
            $cekKolom = bukaquery("SHOW COLUMNS FROM laporan_operasi");
            if($cekKolom) {
                while($kolom = mysqli_fetch_array($cekKolom)) {
                    $kolomAda[] = $kolom['Field'];
                }
            }
            
            // Build INSERT query
            $kolom_list = "no_rawat, tanggal, diagnosa_preop, diagnosa_postop, jaringan_dieksekusi, selesaioperasi, permintaan_pa, laporan_operasi";
            $value_list = "'$no_rawat', '$tanggal_insert', '$diagnosa_preop', '$diagnosa_postop', '$jaringan_dieksekusi', '$selesai_insert', '$permintaan_pa', '$laporan_operasi_text'";
            
            // Tambahkan nomor_implan jika kolom ada
            if(in_array('nomor_implan', $kolomAda)) {
                $kolom_list .= ", nomor_implan";
                $value_list .= ", '$nomor_implan'";
            }
            
            $queryInsert = "INSERT INTO laporan_operasi ($kolom_list) VALUES ($value_list)";
            $resultInsert = bukaquery($queryInsert);
            
            if (!$resultInsert) {
                throw new Exception('Gagal menyimpan laporan operasi');
            }
            
            insertTracker($queryInsert);
            
        } else {
            throw new Exception('Tabel laporan_operasi belum tersedia di database');
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Laporan operasi berhasil disimpan']);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS LAPORAN OPERASI
// ========================================
if ($aksi === 'hapus_laporan_operasi') {
    
    try {
        // Cek apakah tabel ada
        $cekTabel = bukaquery("SHOW TABLES LIKE 'laporan_operasi'");
        if(!$cekTabel || mysqli_num_rows($cekTabel) == 0) {
            throw new Exception('Tabel laporan_operasi belum tersedia di database');
        }
        
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $query = "DELETE FROM laporan_operasi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data laporan operasi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// SIMPAN KONSULTASI MEDIK
// ========================================
if ($aksi === 'simpan_konsultasi_medik') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20) : '';
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $jenis_permintaan = isset($_POST['jenis_permintaan']) ? validTeks4($_POST['jenis_permintaan'], 20) : 'Konsultasi';
        $kd_dokter = isset($_POST['kd_dokter']) ? validTeks4($_POST['kd_dokter'], 20) : '';
        $kd_dokter_dikonsuli = isset($_POST['kd_dokter_dikonsuli']) ? validTeks4($_POST['kd_dokter_dikonsuli'], 20) : '';
        $diagnosa_kerja = isset($_POST['diagnosa_kerja']) ? validTeks4($_POST['diagnosa_kerja'], 200) : '';
        $uraian_konsultasi = isset($_POST['uraian_konsultasi']) ? validTeks4($_POST['uraian_konsultasi'], 800) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        if (empty($no_permintaan)) {
            throw new Exception('No. Permintaan tidak valid');
        }
        
        if (empty($kd_dokter_dikonsuli)) {
            throw new Exception('Dokter yang dikonsuli harus dipilih');
        }
        
        // Format tanggal dari datetime-local ke MySQL datetime
        if (strpos($tanggal, 'T') !== false) {
            $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        }
        
        // Cek apakah data sudah ada (berdasarkan no_permintaan)
        $cekData = bukaquery("SELECT no_permintaan FROM konsultasi_medik WHERE no_permintaan = '$no_permintaan'");
        
        if ($cekData && mysqli_num_rows($cekData) > 0) {
            // UPDATE
            $query = "UPDATE konsultasi_medik SET 
                        no_rawat = '$no_rawat',
                        tanggal = '$tanggal',
                        jenis_permintaan = '$jenis_permintaan',
                        kd_dokter = '$kd_dokter',
                        kd_dokter_dikonsuli = '$kd_dokter_dikonsuli',
                        diagnosa_kerja = '$diagnosa_kerja',
                        uraian_konsultasi = '$uraian_konsultasi'
                      WHERE no_permintaan = '$no_permintaan'";
            
            $result = bukaquery($query);
            
            if ($result) {
                insertTracker($query);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Data konsultasi medik berhasil diupdate',
                    'no_permintaan' => $no_permintaan
                ]);
            } else {
                throw new Exception('Gagal mengupdate data konsultasi medik');
            }
            
        } else {
            // INSERT
            $query = "INSERT INTO konsultasi_medik (
                        no_permintaan,
                        no_rawat,
                        tanggal,
                        jenis_permintaan,
                        kd_dokter,
                        kd_dokter_dikonsuli,
                        diagnosa_kerja,
                        uraian_konsultasi
                      ) VALUES (
                        '$no_permintaan',
                        '$no_rawat',
                        '$tanggal',
                        '$jenis_permintaan',
                        '$kd_dokter',
                        '$kd_dokter_dikonsuli',
                        '$diagnosa_kerja',
                        '$uraian_konsultasi'
                      )";
            
            $result = bukaquery($query);
            
            if ($result) {
                insertTracker($query);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Data konsultasi medik berhasil disimpan',
                    'no_permintaan' => $no_permintaan
                ]);
            } else {
                throw new Exception('Gagal menyimpan data konsultasi medik');
            }
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
// HAPUS KONSULTASI MEDIK
// ========================================
if ($aksi === 'hapus_konsultasi_medik') {
    
    try {
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20) : '';
        
        if (empty($no_permintaan)) {
            throw new Exception('No. Permintaan tidak valid');
        }
        
        // Cek apakah data ada
        $cekData = bukaquery("SELECT no_permintaan, kd_dokter FROM konsultasi_medik WHERE no_permintaan = '$no_permintaan'");
        
        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data konsultasi medik tidak ditemukan');
        }
        
        // Cek apakah dokter yang menghapus adalah dokter yang membuat
        $dataKonsul = mysqli_fetch_assoc($cekData);
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if ($dataKonsul['kd_dokter'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus data ini. Hanya dokter yang membuat yang dapat menghapus.');
        }
        
        $query = "DELETE FROM konsultasi_medik WHERE no_permintaan = '$no_permintaan'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode([
                'status' => 'success',
                'message' => 'Data konsultasi medik berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus data konsultasi medik');
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
// SIMPAN JAWABAN KONSULTASI MEDIK
// ========================================
if ($aksi === 'simpan_jawaban_konsul') {
    
    try {
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20) : '';
        $diagnosa_kerja = isset($_POST['diagnosa_kerja']) ? validTeks4($_POST['diagnosa_kerja'], 200) : '';
        $uraian_jawaban = isset($_POST['uraian_jawaban']) ? validTeks4($_POST['uraian_jawaban'], 800) : '';
        $tanggal = date('Y-m-d H:i:s');
        
        if (empty($no_permintaan)) {
            throw new Exception('No. Permintaan tidak valid');
        }
        
        if (empty($uraian_jawaban)) {
            throw new Exception('Uraian jawaban harus diisi');
        }
        
        // Cek apakah konsultasi ada dan ditujukan ke dokter login
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $cekKonsul = bukaquery("SELECT no_permintaan FROM konsultasi_medik WHERE no_permintaan = '$no_permintaan' AND kd_dokter_dikonsuli = '$kd_dokter_login'");
        
        if (!$cekKonsul || mysqli_num_rows($cekKonsul) == 0) {
            throw new Exception('Anda tidak memiliki akses untuk menjawab konsultasi ini');
        }
        
        // Cek apakah jawaban sudah ada
        $cekJawaban = bukaquery("SELECT no_permintaan FROM jawaban_konsultasi_medik WHERE no_permintaan = '$no_permintaan'");
        
        if ($cekJawaban && mysqli_num_rows($cekJawaban) > 0) {
            // UPDATE
            $query = "UPDATE jawaban_konsultasi_medik SET 
                        tanggal = '$tanggal',
                        diagnosa_kerja = '$diagnosa_kerja',
                        uraian_jawaban = '$uraian_jawaban'
                      WHERE no_permintaan = '$no_permintaan'";
            
            $result = bukaquery($query);
            
            if ($result) {
                insertTracker($query);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Jawaban konsultasi berhasil diupdate'
                ]);
            } else {
                throw new Exception('Gagal mengupdate jawaban');
            }
        } else {
            // INSERT
            $query = "INSERT INTO jawaban_konsultasi_medik (
                        no_permintaan,
                        tanggal,
                        diagnosa_kerja,
                        uraian_jawaban
                      ) VALUES (
                        '$no_permintaan',
                        '$tanggal',
                        '$diagnosa_kerja',
                        '$uraian_jawaban'
                      )";
            
            $result = bukaquery($query);
            
            if ($result) {
                insertTracker($query);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Jawaban konsultasi berhasil disimpan'
                ]);
            } else {
                throw new Exception('Gagal menyimpan jawaban');
            }
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
// HAPUS JAWABAN KONSULTASI MEDIK
// ========================================
if ($aksi === 'hapus_jawaban_konsul') {
    
    try {
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20) : '';
        
        if (empty($no_permintaan)) {
            throw new Exception('No. Permintaan tidak valid');
        }
        
        // Cek apakah konsultasi ditujukan ke dokter login
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $cekKonsul = bukaquery("SELECT no_permintaan FROM konsultasi_medik WHERE no_permintaan = '$no_permintaan' AND kd_dokter_dikonsuli = '$kd_dokter_login'");
        
        if (!$cekKonsul || mysqli_num_rows($cekKonsul) == 0) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus jawaban ini');
        }
        
        $query = "DELETE FROM jawaban_konsultasi_medik WHERE no_permintaan = '$no_permintaan'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode([
                'status' => 'success',
                'message' => 'Jawaban konsultasi berhasil dihapus'
            ]);
        } else {
            throw new Exception('Gagal menghapus jawaban');
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
// SIMPAN RESEP SEBAGAI TEMPLATE
// ========================================
if ($aksi === 'simpan_resep_sebagai_template') {
    
    try {
        // Validasi input
        $no_resep = isset($_POST['no_resep']) ? validTeks4($_POST['no_resep'], 20) : '';
        $nama_template = isset($_POST['nama_template']) ? validTeks4($_POST['nama_template'], 100) : '';
        
        if (empty($no_resep)) {
            throw new Exception('No. Resep tidak valid');
        }
        
        if (empty($nama_template)) {
            throw new Exception('Nama template harus diisi');
        }
        
        // Ambil kd_dokter dari session
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($kd_dokter)) {
            throw new Exception('Session dokter tidak valid');
        }
        
        // Generate no_template otomatis
        // Format: TPL + YYYYMMDD + 5 digit urutan
        $tanggal = date('Ymd');
        
        // Cari nomor urut terakhir hari ini
        $queryLastNo = "SELECT no_template FROM template_pemeriksaan_dokter 
                        WHERE no_template LIKE 'TPL{$tanggal}%' 
                        ORDER BY no_template DESC LIMIT 1";
        $resultLastNo = bukaquery($queryLastNo);
        
        if ($resultLastNo && mysqli_num_rows($resultLastNo) > 0) {
            $rowLast = mysqli_fetch_assoc($resultLastNo);
            $lastNo = $rowLast['no_template'];
            $lastUrut = (int)substr($lastNo, -5);
            $newUrut = $lastUrut + 1;
        } else {
            $newUrut = 1;
        }
        
        $no_template = 'TPL' . $tanggal . str_pad($newUrut, 5, '0', STR_PAD_LEFT);
        
        // Cek duplikat
        $cekExist = bukaquery("SELECT no_template FROM template_pemeriksaan_dokter WHERE no_template = '$no_template'");
        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            $no_template = 'TPL' . date('YmdHis') . rand(10, 99);
        }
        
        // ========================================
        // AMBIL DATA RESEP NON RACIKAN
        // ========================================
        $queryObatNR = "SELECT rd.kode_brng, rd.jml, rd.aturan_pakai
                        FROM resep_dokter rd
                        WHERE rd.no_resep = '$no_resep'";
        $resultObatNR = bukaquery($queryObatNR);
        
        $obatNonRacikan = [];
        if ($resultObatNR && mysqli_num_rows($resultObatNR) > 0) {
            while ($row = mysqli_fetch_assoc($resultObatNR)) {
                $obatNonRacikan[] = $row;
            }
        }
        
        // ========================================
        // AMBIL DATA RESEP RACIKAN (HEADER + DETAIL)
        // ========================================
        $queryRacikan = "SELECT rdr.no_resep, rdr.no_racik, rdr.nama_racik, rdr.kd_racik, 
                                rdr.jml_dr, rdr.aturan_pakai, rdr.keterangan
                         FROM resep_dokter_racikan rdr
                         WHERE rdr.no_resep = '$no_resep'";
        $resultRacikan = bukaquery($queryRacikan);
        
        $obatRacikan = [];
        if ($resultRacikan && mysqli_num_rows($resultRacikan) > 0) {
            while ($rowRacikan = mysqli_fetch_assoc($resultRacikan)) {
                $noRacik = $rowRacikan['no_racik'];
                $queryDetail = "SELECT rdrd.kode_brng, rdrd.p1, rdrd.p2, rdrd.kandungan, rdrd.jml
                                FROM resep_dokter_racikan_detail rdrd
                                WHERE rdrd.no_resep = '$no_resep' AND rdrd.no_racik = '$noRacik'";
                $resultDetail = bukaquery($queryDetail);
                
                $komposisi = [];
                if ($resultDetail && mysqli_num_rows($resultDetail) > 0) {
                    while ($rowDetail = mysqli_fetch_assoc($resultDetail)) {
                        $komposisi[] = $rowDetail;
                    }
                }
                
                $rowRacikan['komposisi'] = $komposisi;
                $obatRacikan[] = $rowRacikan;
            }
        }
        
        // Validasi minimal 1 obat
        if (count($obatNonRacikan) === 0 && count($obatRacikan) === 0) {
            throw new Exception('Tidak ada obat yang ditemukan di resep ini');
        }
        
        // ========================================
        // INSERT HEADER TEMPLATE
        // Struktur: no_template, kd_dokter, keluhan, pemeriksaan, penilaian, rencana, instruksi, evaluasi
        // ========================================
        $queryInsertHeader = "INSERT INTO template_pemeriksaan_dokter 
                              (no_template, kd_dokter, keluhan, pemeriksaan, penilaian, rencana, instruksi, evaluasi) 
                              VALUES 
                              ('$no_template', '$kd_dokter', '-', '', '$nama_template', '', '', '')";
        
        $resultHeader = bukaquery($queryInsertHeader);
        
        if (!$resultHeader) {
            throw new Exception('Gagal menyimpan header template');
        }
        
        insertTracker($queryInsertHeader);
        
        // ========================================
        // INSERT DETAIL TEMPLATE - OBAT NON RACIKAN
        // Struktur: no_template, kode_brng, jml, aturan_pakai
        // ========================================
        $countObatNonRacik = 0; // ✅ DIUBAH dari $countObat
        
        foreach ($obatNonRacikan as $obat) {
            $kode_brng = $obat['kode_brng'];
            $jml = $obat['jml'];
            $aturan_pakai = $obat['aturan_pakai'];
            
            $queryInsertDetail = "INSERT INTO template_pemeriksaan_dokter_resep 
                                  (no_template, kode_brng, jml, aturan_pakai) 
                                  VALUES 
                                  ('$no_template', '$kode_brng', '$jml', '$aturan_pakai')";
            
            $resultDetail = bukaquery($queryInsertDetail);
            
            if ($resultDetail) {
                insertTracker($queryInsertDetail);
                $countObatNonRacik++; // ✅ DIUBAH dari $countObat
            }
        }
        
        // ========================================
        // ✅ PERBAIKAN: INSERT TEMPLATE RACIKAN (2 TABEL)
        // 1. Insert header racikan ke template_pemeriksaan_dokter_resep_racikan
        // 2. Insert detail komposisi ke template_pemeriksaan_dokter_resep_racikan_detail
        // ========================================
        $countRacikan = 0;
        $countKomposisiRacikan = 0;
        
        foreach ($obatRacikan as $racikan) {
            $no_racik = $racikan['no_racik'];
            $nama_racik = $racikan['nama_racik'];
            $kd_racik = $racikan['kd_racik'];
            $jml_dr = $racikan['jml_dr'];
            $aturan_pakai_racik = $racikan['aturan_pakai'];
            $keterangan = isset($racikan['keterangan']) ? $racikan['keterangan'] : '';
            
            // ========================================
            // 1. INSERT HEADER RACIKAN
            // ========================================
            $queryInsertHeaderRacikan = "INSERT INTO template_pemeriksaan_dokter_resep_racikan 
                                         (no_template, no_racik, nama_racik, kd_racik, jml_dr, aturan_pakai, keterangan) 
                                         VALUES 
                                         ('$no_template', '$no_racik', '$nama_racik', '$kd_racik', '$jml_dr', '$aturan_pakai_racik', '$keterangan')";
            
            $resultHeaderRacikan = bukaquery($queryInsertHeaderRacikan);
            
            if ($resultHeaderRacikan) {
                insertTracker($queryInsertHeaderRacikan);
                $countRacikan++;
                
                // ========================================
                // 2. INSERT DETAIL KOMPOSISI RACIKAN
                // ========================================
                foreach ($racikan['komposisi'] as $komp) {
                    $kode_brng = $komp['kode_brng'];
                    $jml_komp = $komp['jml'];
                    $p1 = isset($komp['p1']) ? $komp['p1'] : '';
                    $p2 = isset($komp['p2']) ? $komp['p2'] : '';
                    $kandungan = isset($komp['kandungan']) ? $komp['kandungan'] : '';
                    
                    $queryInsertDetailRacikan = "INSERT INTO template_pemeriksaan_dokter_resep_racikan_detail 
                                                 (no_template, no_racik, kode_brng, p1, p2, kandungan, jml) 
                                                 VALUES 
                                                 ('$no_template', '$no_racik', '$kode_brng', '$p1', '$p2', '$kandungan', '$jml_komp')";
                    
                    $resultDetailRacikan = bukaquery($queryInsertDetailRacikan);
                    
                    if ($resultDetailRacikan) {
                        insertTracker($queryInsertDetailRacikan);
                        $countKomposisiRacikan++;
                    }
                }
            }
        }
        
        // ========================================
        // RESPONSE
        // ========================================
        $countObatTotal = $countObatNonRacik + $countKomposisiRacikan;
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Template berhasil disimpan',
            'no_template' => $no_template,
            'nama_template' => $nama_template,
            'count_obat' => $countObatTotal,
            'count_non_racikan' => $countObatNonRacik,
            'count_racikan' => $countRacikan,
            'count_komposisi_racikan' => $countKomposisiRacikan
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
// HAPUS TEMPLATE OBAT
// ========================================
if ($aksi === 'hapus_template_obat') {
    
    try {
        $no_template = isset($_POST['no_template']) ? validTeks4($_POST['no_template'], 30) : '';
        
        if (empty($no_template)) {
            throw new Exception('No. Template tidak valid');
        }
        
        // Cek apakah template exists
        $cekTemplate = bukaquery("SELECT no_template, kd_dokter FROM template_pemeriksaan_dokter WHERE no_template = '$no_template'");
        
        if (!$cekTemplate || mysqli_num_rows($cekTemplate) === 0) {
            throw new Exception('Template tidak ditemukan');
        }
        
        // Validasi kepemilikan template (opsional - cek kd_dokter)
        $rowTemplate = mysqli_fetch_assoc($cekTemplate);
        $kd_dokter_session = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if ($rowTemplate['kd_dokter'] !== $kd_dokter_session) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus template ini');
        }
        
        // Hapus detail template dulu (foreign key)
        $queryHapusDetail = "DELETE FROM template_pemeriksaan_dokter_resep WHERE no_template = '$no_template'";
        $resultDetail = bukaquery($queryHapusDetail);
        
        if ($resultDetail) {
            insertTracker($queryHapusDetail);
        }
        
        // Hapus header template
        $queryHapusHeader = "DELETE FROM template_pemeriksaan_dokter WHERE no_template = '$no_template'";
        $resultHeader = bukaquery($queryHapusHeader);
        
        if (!$resultHeader) {
            throw new Exception('Gagal menghapus template');
        }
        
        insertTracker($queryHapusHeader);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Template berhasil dihapus',
            'no_template' => $no_template
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
// SIMPAN PENILAIAN PRE INDUKSI
// ========================================
if ($aksi === 'simpan_penilaian_pre_induksi') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $kd_dokter = '';
        if(isset($_SESSION['ses_dokter']) && !empty($_SESSION['ses_dokter'])) {
            $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        }
        
        // Ambil semua field dari POST
        $tanggal = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 20) : date('Y-m-d H:i:s');
        // Format tanggal dari datetime-local
        if (strpos($tanggal, 'T') !== false) {
            $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        }
        
        $tensi = isset($_POST['tensi']) ? validTeks4($_POST['tensi'], 8) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $rr = isset($_POST['rr']) ? validTeks4($_POST['rr'], 5) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $ekg = isset($_POST['ekg']) ? validTeks4($_POST['ekg'], 50) : '';
        $lain_lain = isset($_POST['lain_lain']) ? validTeks4($_POST['lain_lain'], 50) : '';
        $asesmen = isset($_POST['asesmen']) ? validTeks4($_POST['asesmen'], 50) : 'Sesuai Asesmen Pre Sedasi/Anestesi';
        $perencanaan = isset($_POST['perencanaan']) ? validTeks4($_POST['perencanaan'], 300) : '';
        $infus_perifer = isset($_POST['infus_perifer']) ? validTeks4($_POST['infus_perifer'], 300) : '';
        $cvc = isset($_POST['cvc']) ? validTeks4($_POST['cvc'], 70) : '';
        $posisi = isset($_POST['posisi']) ? validTeks4($_POST['posisi'], 20) : 'Supine';
        $premedikasi = isset($_POST['premedikasi']) ? validTeks4($_POST['premedikasi'], 5) : 'Oral';
        $premedikasi_keterangan = isset($_POST['premedikasi_keterangan']) ? validTeks4($_POST['premedikasi_keterangan'], 50) : '';
        $induksi = isset($_POST['induksi']) ? validTeks4($_POST['induksi'], 10) : 'Intravena';
        $induksi_keterangan = isset($_POST['induksi_keterangan']) ? validTeks4($_POST['induksi_keterangan'], 70) : '';
        $face_mask_no = isset($_POST['face_mask_no']) ? validTeks4($_POST['face_mask_no'], 20) : '';
        $nasopharing_no = isset($_POST['nasopharing_no']) ? validTeks4($_POST['nasopharing_no'], 20) : '';
        $ett_no = isset($_POST['ett_no']) ? validTeks4($_POST['ett_no'], 20) : '';
        $ett_jenis = isset($_POST['ett_jenis']) ? validTeks4($_POST['ett_jenis'], 20) : '';
        $ett_viksasi = isset($_POST['ett_viksasi']) ? validTeks4($_POST['ett_viksasi'], 25) : '';
        $lma_no = isset($_POST['lma_no']) ? validTeks4($_POST['lma_no'], 20) : '';
        $lma_jenis = isset($_POST['lma_jenis']) ? validTeks4($_POST['lma_jenis'], 20) : '';
        $tracheostomi = isset($_POST['tracheostomi']) ? validTeks4($_POST['tracheostomi'], 60) : '';
        $bronchoscopi_fiberoptik = isset($_POST['bronchoscopi_fiberoptik']) ? validTeks4($_POST['bronchoscopi_fiberoptik'], 60) : '';
        $glidescopi = isset($_POST['glidescopi']) ? validTeks4($_POST['glidescopi'], 60) : '';
        $lain_lain_tatalaksana = isset($_POST['lain_lain_tatalaksana']) ? validTeks4($_POST['lain_lain_tatalaksana'], 100) : '';
        $intubasi_sesudah_tidur = isset($_POST['intubasi_sesudah_tidur']) ? validTeks4($_POST['intubasi_sesudah_tidur'], 5) : 'Tidak';
        $intubasi_oral = isset($_POST['intubasi_oral']) ? validTeks4($_POST['intubasi_oral'], 5) : 'Tidak';
        $intubasi_tracheostomi = isset($_POST['intubasi_tracheostomi']) ? validTeks4($_POST['intubasi_tracheostomi'], 5) : 'Tidak';
        $intubasi_keterangan = isset($_POST['intubasi_keterangan']) ? validTeks4($_POST['intubasi_keterangan'], 200) : '';
        $sulit_ventilasi = isset($_POST['sulit_ventilasi']) ? validTeks4($_POST['sulit_ventilasi'], 100) : '';
        $sulit_intubasi = isset($_POST['sulit_intubasi']) ? validTeks4($_POST['sulit_intubasi'], 100) : '';
        $ventilasi = isset($_POST['ventilasi']) ? validTeks4($_POST['ventilasi'], 100) : '';
        $teknik_regional_jenis = isset($_POST['teknik_regional_jenis']) ? validTeks4($_POST['teknik_regional_jenis'], 100) : '';
        $teknik_regional_lokasi = isset($_POST['teknik_regional_lokasi']) ? validTeks4($_POST['teknik_regional_lokasi'], 40) : '';
        $teknik_regional_jenis_jarum = isset($_POST['teknik_regional_jenis_jarum']) ? validTeks4($_POST['teknik_regional_jenis_jarum'], 30) : '';
        $teknik_regional_kateter = isset($_POST['teknik_regional_kateter']) ? validTeks4($_POST['teknik_regional_kateter'], 5) : 'Tidak';
        $teknik_regional_kateter_viksasi = isset($_POST['teknik_regional_kateter_viksasi']) ? validTeks4($_POST['teknik_regional_kateter_viksasi'], 40) : '';
        $teknik_regional_obat_obatan = isset($_POST['teknik_regional_obat_obatan']) ? validTeks4($_POST['teknik_regional_obat_obatan'], 400) : '';
        $teknik_regional_komplikasi = isset($_POST['teknik_regional_komplikasi']) ? validTeks4($_POST['teknik_regional_komplikasi'], 200) : '';
        $teknik_regional_hasil = isset($_POST['teknik_regional_hasil']) ? validTeks4($_POST['teknik_regional_hasil'], 100) : '';
        
        // Cek apakah data sudah ada
        $cek = bukaquery("SELECT no_rawat FROM penilaian_pre_induksi WHERE no_rawat = '$no_rawat'");
        
        if($cek && mysqli_num_rows($cek) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_pre_induksi SET 
                tanggal = '$tanggal',
                tensi = '$tensi',
                nadi = '$nadi',
                rr = '$rr',
                suhu = '$suhu',
                ekg = '$ekg',
                lain_lain = '$lain_lain',
                asesmen = '$asesmen',
                perencanaan = '$perencanaan',
                infus_perifier = '$infus_perifer',
                cvc = '$cvc',
                posisi = '$posisi',
                premedikasi = '$premedikasi',
                premedikasi_keterangan = '$premedikasi_keterangan',
                induksi = '$induksi',
                induksi_keterangan = '$induksi_keterangan',
                face_mask_no = '$face_mask_no',
                nasopharing_no = '$nasopharing_no',
                ett_no = '$ett_no',
                ett_jenis = '$ett_jenis',
                ett_viksasi = '$ett_viksasi',
                lma_no = '$lma_no',
                lma_jenis = '$lma_jenis',
                tracheostomi = '$tracheostomi',
                bronchoscopi_fiberoptik = '$bronchoscopi_fiberoptik',
                glidescopi = '$glidescopi',
                lain_lain_tatalaksana = '$lain_lain_tatalaksana',
                intubasi_sesudah_tidur = '$intubasi_sesudah_tidur',
                intubasi_oral = '$intubasi_oral',
                intubasi_tracheostomi = '$intubasi_tracheostomi',
                intubasi_keterangan = '$intubasi_keterangan',
                sulit_ventilasi = '$sulit_ventilasi',
                sulit_intubasi = '$sulit_intubasi',
                ventilasi = '$ventilasi',
                teknik_regional_jenis = '$teknik_regional_jenis',
                teknik_regional_lokasi = '$teknik_regional_lokasi',
                teknik_regional_jenis_jarum = '$teknik_regional_jenis_jarum',
                teknik_regional_kateter = '$teknik_regional_kateter',
                teknik_regional_kateter_viksasi = '$teknik_regional_kateter_viksasi',
                teknik_regional_obat_obatan = '$teknik_regional_obat_obatan',
                teknik_regional_komplikasi = '$teknik_regional_komplikasi',
                teknik_regional_hasil = '$teknik_regional_hasil'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data penilaian pre induksi berhasil diupdate';
        } else {
            // INSERT
            $query = "INSERT INTO penilaian_pre_induksi (
                no_rawat, tanggal, kd_dokter,
                tensi, nadi, rr, suhu, ekg, lain_lain,
                asesmen, perencanaan, infus_perifier, cvc,
                posisi, premedikasi, premedikasi_keterangan,
                induksi, induksi_keterangan,
                face_mask_no, nasopharing_no,
                ett_no, ett_jenis, ett_viksasi,
                lma_no, lma_jenis,
                tracheostomi, bronchoscopi_fiberoptik, glidescopi, lain_lain_tatalaksana,
                intubasi_sesudah_tidur, intubasi_oral, intubasi_tracheostomi, intubasi_keterangan,
                sulit_ventilasi, sulit_intubasi, ventilasi,
                teknik_regional_jenis, teknik_regional_lokasi, teknik_regional_jenis_jarum,
                teknik_regional_kateter, teknik_regional_kateter_viksasi,
                teknik_regional_obat_obatan, teknik_regional_komplikasi, teknik_regional_hasil
            ) VALUES (
                '$no_rawat', '$tanggal', '$kd_dokter',
                '$tensi', '$nadi', '$rr', '$suhu', '$ekg', '$lain_lain',
                '$asesmen', '$perencanaan', '$infus_perifier', '$cvc',
                '$posisi', '$premedikasi', '$premedikasi_keterangan',
                '$induksi', '$induksi_keterangan',
                '$face_mask_no', '$nasopharing_no',
                '$ett_no', '$ett_jenis', '$ett_viksasi',
                '$lma_no', '$lma_jenis',
                '$tracheostomi', '$bronchoscopi_fiberoptik', '$glidescopi', '$lain_lain_tatalaksana',
                '$intubasi_sesudah_tidur', '$intubasi_oral', '$intubasi_tracheostomi', '$intubasi_keterangan',
                '$sulit_ventilasi', '$sulit_intubasi', '$ventilasi',
                '$teknik_regional_jenis', '$teknik_regional_lokasi', '$teknik_regional_jenis_jarum',
                '$teknik_regional_kateter', '$teknik_regional_kateter_viksasi',
                '$teknik_regional_obat_obatan', '$teknik_regional_komplikasi', '$teknik_regional_hasil'
            )";
            $msg = 'Data penilaian pre induksi berhasil disimpan';
        }
        
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data penilaian pre induksi');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS PENILAIAN PRE INDUKSI
// ========================================
if ($aksi === 'hapus_penilaian_pre_induksi') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Cek kepemilikan
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $cekData = bukaquery("SELECT kd_dokter FROM penilaian_pre_induksi WHERE no_rawat = '$no_rawat'");
        
        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }
        
        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus data ini. Hanya dokter pengisi yang dapat menghapus.');
        }
        
        $query = "DELETE FROM penilaian_pre_induksi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data penilaian pre induksi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// SIMPAN PENILAIAN PRE OPERASI
// ========================================
if ($aksi === 'simpan_penilaian_pre_operasi') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        $kd_dokter = isset($_POST['kd_dokter']) ? validTeks4($_POST['kd_dokter'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Ambil semua field dari POST
        $tanggal = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 20) : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) {
            $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        }
        
        $ringkasan_klinik = isset($_POST['ringkasan_klinik']) ? validTeks4($_POST['ringkasan_klinik'], 500) : '';
        $pemeriksaan_fisik = isset($_POST['pemeriksaan_fisik']) ? validTeks4($_POST['pemeriksaan_fisik'], 500) : '';
        $pemeriksaan_diagnostik = isset($_POST['pemeriksaan_diagnostik']) ? validTeks4($_POST['pemeriksaan_diagnostik'], 500) : '';
        $diagnosa_pre_operasi = isset($_POST['diagnosa_pre_operasi']) ? validTeks4($_POST['diagnosa_pre_operasi'], 500) : '';
        $rencana_tindakan_bedah = isset($_POST['rencana_tindakan_bedah']) ? validTeks4($_POST['rencana_tindakan_bedah'], 500) : '';
        $hal_hal_yang_perludi_persiapkan = isset($_POST['hal_hal_yang_perludi_persiapkan']) ? validTeks4($_POST['hal_hal_yang_perludi_persiapkan'], 500) : '';
        $terapi_pre_operasi = isset($_POST['terapi_pre_operasi']) ? validTeks4($_POST['terapi_pre_operasi'], 500) : '';
        
        // Cek apakah data sudah ada
        $cek = bukaquery("SELECT no_rawat FROM penilaian_pre_operasi WHERE no_rawat = '$no_rawat'");
        
        if($cek && mysqli_num_rows($cek) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_pre_operasi SET 
                tanggal = '$tanggal',
                ringkasan_klinik = '$ringkasan_klinik',
                pemeriksaan_fisik = '$pemeriksaan_fisik',
                pemeriksaan_diagnostik = '$pemeriksaan_diagnostik',
                diagnosa_pre_operasi = '$diagnosa_pre_operasi',
                rencana_tindakan_bedah = '$rencana_tindakan_bedah',
                hal_hal_yang_perludi_persiapkan = '$hal_hal_yang_perludi_persiapkan',
                terapi_pre_operasi = '$terapi_pre_operasi'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data penilaian pre operasi berhasil diupdate';
        } else {
            // INSERT
            $query = "INSERT INTO penilaian_pre_operasi (
                no_rawat, tanggal, kd_dokter,
                ringkasan_klinik, pemeriksaan_fisik, pemeriksaan_diagnostik,
                diagnosa_pre_operasi, rencana_tindakan_bedah,
                hal_hal_yang_perludi_persiapkan, terapi_pre_operasi
            ) VALUES (
                '$no_rawat', '$tanggal', '$kd_dokter',
                '$ringkasan_klinik', '$pemeriksaan_fisik', '$pemeriksaan_diagnostik',
                '$diagnosa_pre_operasi', '$rencana_tindakan_bedah',
                '$hal_hal_yang_perludi_persiapkan', '$terapi_pre_operasi'
            )";
            $msg = 'Data penilaian pre operasi berhasil disimpan';
        }
        
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data penilaian pre operasi');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS PENILAIAN PRE OPERASI
// ========================================
if ($aksi === 'hapus_penilaian_pre_operasi') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Cek kepemilikan
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $cekData = bukaquery("SELECT kd_dokter FROM penilaian_pre_operasi WHERE no_rawat = '$no_rawat'");
        
        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }
        
        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus data ini. Hanya dokter pengisi yang dapat menghapus.');
        }
        
        $query = "DELETE FROM penilaian_pre_operasi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data penilaian pre operasi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// SIMPAN PENILAIAN PRE ANESTESI
// ========================================
if ($aksi === 'simpan_penilaian_pre_anestesi') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        $kd_dokter = isset($_POST['kd_dokter']) ? validTeks4($_POST['kd_dokter'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $tanggal = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 20) : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) {
            $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        }
        
        $tanggal_operasi = isset($_POST['tanggal_operasi']) ? validTeks4($_POST['tanggal_operasi'], 20) : date('Y-m-d H:i:s');
        if (strpos($tanggal_operasi, 'T') !== false) {
            $tanggal_operasi = str_replace('T', ' ', $tanggal_operasi) . ':00';
        }
        
        $puasa = isset($_POST['puasa']) ? validTeks4($_POST['puasa'], 20) : date('Y-m-d H:i:s');
        if (strpos($puasa, 'T') !== false) {
            $puasa = str_replace('T', ' ', $puasa) . ':00';
        }
        
        $diagnosa = isset($_POST['diagnosa']) ? validTeks4($_POST['diagnosa'], 100) : '';
        $rencana_tindakan = isset($_POST['rencana_tindakan']) ? validTeks4($_POST['rencana_tindakan'], 100) : '';
        $tb = isset($_POST['tb']) ? validTeks4($_POST['tb'], 5) : '';
        $bb = isset($_POST['bb']) ? validTeks4($_POST['bb'], 5) : '';
        $td = isset($_POST['td']) ? validTeks4($_POST['td'], 8) : '';
        $io2 = isset($_POST['io2']) ? validTeks4($_POST['io2'], 5) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $pernapasan = isset($_POST['pernapasan']) ? validTeks4($_POST['pernapasan'], 5) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $fisik_cardiovasculer = isset($_POST['fisik_cardiovasculer']) ? validTeks4($_POST['fisik_cardiovasculer'], 100) : '';
        $fisik_paru = isset($_POST['fisik_paru']) ? validTeks4($_POST['fisik_paru'], 100) : '';
        $fisik_abdomen = isset($_POST['fisik_abdomen']) ? validTeks4($_POST['fisik_abdomen'], 100) : '';
        $fisik_extrimitas = isset($_POST['fisik_extrimitas']) ? validTeks4($_POST['fisik_extrimitas'], 100) : '';
        $fisik_endokrin = isset($_POST['fisik_endokrin']) ? validTeks4($_POST['fisik_endokrin'], 100) : '';
        $fisik_ginjal = isset($_POST['fisik_ginjal']) ? validTeks4($_POST['fisik_ginjal'], 100) : '';
        $fisik_obatobatan = isset($_POST['fisik_obatobatan']) ? validTeks4($_POST['fisik_obatobatan'], 100) : '';
        $fisik_laborat = isset($_POST['fisik_laborat']) ? validTeks4($_POST['fisik_laborat'], 100) : '';
        $fisik_penunjang = isset($_POST['fisik_penunjang']) ? validTeks4($_POST['fisik_penunjang'], 100) : '';
        $riwayat_penyakit_alergiobat = isset($_POST['riwayat_penyakit_alergiobat']) ? validTeks4($_POST['riwayat_penyakit_alergiobat'], 50) : '';
        $riwayat_penyakit_alergilainnya = isset($_POST['riwayat_penyakit_alergilainnya']) ? validTeks4($_POST['riwayat_penyakit_alergilainnya'], 50) : '';
        $riwayat_penyakit_terapi = isset($_POST['riwayat_penyakit_terapi']) ? validTeks4($_POST['riwayat_penyakit_terapi'], 100) : '';
        $riwayat_kebiasaan_merokok = isset($_POST['riwayat_kebiasaan_merokok']) ? validTeks4($_POST['riwayat_kebiasaan_merokok'], 5) : 'Tidak';
        $riwayat_kebiasaan_ket_merokok = isset($_POST['riwayat_kebiasaan_ket_merokok']) ? validTeks4($_POST['riwayat_kebiasaan_ket_merokok'], 5) : '';
        $riwayat_kebiasaan_alkohol = isset($_POST['riwayat_kebiasaan_alkohol']) ? validTeks4($_POST['riwayat_kebiasaan_alkohol'], 5) : 'Tidak';
        $riwayat_kebiasaan_ket_alkohol = isset($_POST['riwayat_kebiasaan_ket_alkohol']) ? validTeks4($_POST['riwayat_kebiasaan_ket_alkohol'], 5) : '';
        $riwayat_kebiasaan_obat = isset($_POST['riwayat_kebiasaan_obat']) ? validTeks4($_POST['riwayat_kebiasaan_obat'], 20) : '-';
        $riwayat_kebiasaan_ket_obat = isset($_POST['riwayat_kebiasaan_ket_obat']) ? validTeks4($_POST['riwayat_kebiasaan_ket_obat'], 100) : '';
        $riwayat_medis_cardiovasculer = isset($_POST['riwayat_medis_cardiovasculer']) ? validTeks4($_POST['riwayat_medis_cardiovasculer'], 100) : '';
        $riwayat_medis_respiratory = isset($_POST['riwayat_medis_respiratory']) ? validTeks4($_POST['riwayat_medis_respiratory'], 100) : '';
        $riwayat_medis_endocrine = isset($_POST['riwayat_medis_endocrine']) ? validTeks4($_POST['riwayat_medis_endocrine'], 100) : '';
        $riwayat_medis_lainnya = isset($_POST['riwayat_medis_lainnya']) ? validTeks4($_POST['riwayat_medis_lainnya'], 100) : '';
        $asa = isset($_POST['asa']) ? validTeks4($_POST['asa'], 1) : '1';
        $rencana_anestesi = isset($_POST['rencana_anestesi']) ? validTeks4($_POST['rencana_anestesi'], 20) : 'GA';
        $rencana_perawatan = isset($_POST['rencana_perawatan']) ? validTeks4($_POST['rencana_perawatan'], 40) : '';
        $catatan_khusus = isset($_POST['catatan_khusus']) ? validTeks4($_POST['catatan_khusus'], 100) : '';
        
        // Cek apakah data sudah ada
        $cek = bukaquery("SELECT no_rawat FROM penilaian_pre_anestesi WHERE no_rawat = '$no_rawat'");
        
        if($cek && mysqli_num_rows($cek) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_pre_anestesi SET 
                tanggal = '$tanggal',
                tanggal_operasi = '$tanggal_operasi',
                diagnosa = '$diagnosa',
                rencana_tindakan = '$rencana_tindakan',
                tb = '$tb', bb = '$bb', td = '$td', io2 = '$io2',
                nadi = '$nadi', pernapasan = '$pernapasan', suhu = '$suhu',
                fisik_cardiovasculer = '$fisik_cardiovasculer',
                fisik_paru = '$fisik_paru',
                fisik_abdomen = '$fisik_abdomen',
                fisik_extrimitas = '$fisik_extrimitas',
                fisik_endokrin = '$fisik_endokrin',
                fisik_ginjal = '$fisik_ginjal',
                fisik_obatobatan = '$fisik_obatobatan',
                fisik_laborat = '$fisik_laborat',
                fisik_penunjang = '$fisik_penunjang',
                riwayat_penyakit_alergiobat = '$riwayat_penyakit_alergiobat',
                riwayat_penyakit_alergilainnya = '$riwayat_penyakit_alergilainnya',
                riwayat_penyakit_terapi = '$riwayat_penyakit_terapi',
                riwayat_kebiasaan_merokok = '$riwayat_kebiasaan_merokok',
                riwayat_kebiasaan_ket_merokok = '$riwayat_kebiasaan_ket_merokok',
                riwayat_kebiasaan_alkohol = '$riwayat_kebiasaan_alkohol',
                riwayat_kebiasaan_ket_alkohol = '$riwayat_kebiasaan_ket_alkohol',
                riwayat_kebiasaan_obat = '$riwayat_kebiasaan_obat',
                riwayat_kebiasaan_ket_obat = '$riwayat_kebiasaan_ket_obat',
                riwayat_medis_cardiovasculer = '$riwayat_medis_cardiovasculer',
                riwayat_medis_respiratory = '$riwayat_medis_respiratory',
                riwayat_medis_endocrine = '$riwayat_medis_endocrine',
                riwayat_medis_lainnya = '$riwayat_medis_lainnya',
                asa = '$asa',
                puasa = '$puasa',
                rencana_anestesi = '$rencana_anestesi',
                rencana_perawatan = '$rencana_perawatan',
                catatan_khusus = '$catatan_khusus'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data penilaian pre anestesi berhasil diupdate';
        } else {
            // INSERT
            $query = "INSERT INTO penilaian_pre_anestesi (
                no_rawat, tanggal, kd_dokter, tanggal_operasi,
                diagnosa, rencana_tindakan,
                tb, bb, td, io2, nadi, pernapasan, suhu,
                fisik_cardiovasculer, fisik_paru, fisik_abdomen,
                fisik_extrimitas, fisik_endokrin, fisik_ginjal,
                fisik_obatobatan, fisik_laborat, fisik_penunjang,
                riwayat_penyakit_alergiobat, riwayat_penyakit_alergilainnya,
                riwayat_penyakit_terapi,
                riwayat_kebiasaan_merokok, riwayat_kebiasaan_ket_merokok,
                riwayat_kebiasaan_alkohol, riwayat_kebiasaan_ket_alkohol,
                riwayat_kebiasaan_obat, riwayat_kebiasaan_ket_obat,
                riwayat_medis_cardiovasculer, riwayat_medis_respiratory,
                riwayat_medis_endocrine, riwayat_medis_lainnya,
                asa, puasa, rencana_anestesi,
                rencana_perawatan, catatan_khusus
            ) VALUES (
                '$no_rawat', '$tanggal', '$kd_dokter', '$tanggal_operasi',
                '$diagnosa', '$rencana_tindakan',
                '$tb', '$bb', '$td', '$io2', '$nadi', '$pernapasan', '$suhu',
                '$fisik_cardiovasculer', '$fisik_paru', '$fisik_abdomen',
                '$fisik_extrimitas', '$fisik_endokrin', '$fisik_ginjal',
                '$fisik_obatobatan', '$fisik_laborat', '$fisik_penunjang',
                '$riwayat_penyakit_alergiobat', '$riwayat_penyakit_alergilainnya',
                '$riwayat_penyakit_terapi',
                '$riwayat_kebiasaan_merokok', '$riwayat_kebiasaan_ket_merokok',
                '$riwayat_kebiasaan_alkohol', '$riwayat_kebiasaan_ket_alkohol',
                '$riwayat_kebiasaan_obat', '$riwayat_kebiasaan_ket_obat',
                '$riwayat_medis_cardiovasculer', '$riwayat_medis_respiratory',
                '$riwayat_medis_endocrine', '$riwayat_medis_lainnya',
                '$asa', '$puasa', '$rencana_anestesi',
                '$rencana_perawatan', '$catatan_khusus'
            )";
            $msg = 'Data penilaian pre anestesi berhasil disimpan';
        }
        
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data penilaian pre anestesi');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS PENILAIAN PRE ANESTESI
// ========================================
if ($aksi === 'hapus_penilaian_pre_anestesi') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Cek kepemilikan
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $cekData = bukaquery("SELECT kd_dokter FROM penilaian_pre_anestesi WHERE no_rawat = '$no_rawat'");
        
        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }
        
        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus data ini. Hanya dokter pengisi yang dapat menghapus.');
        }
        
        $query = "DELETE FROM penilaian_pre_anestesi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data penilaian pre anestesi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// SIMPAN PASIEN MENINGGAL
// ========================================
if ($aksi === 'simpan_pasien_meninggal') {
    
    try {
        $no_rkm_medis = isset($_POST['no_rkm_medis']) ? validTeks4($_POST['no_rkm_medis'], 15) : '';
        $tanggal = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 10) : date('Y-m-d');
        $jam = isset($_POST['jam']) ? validTeks4($_POST['jam'], 8) : '00:00:00';
        $keterangan = isset($_POST['keterangan']) ? validTeks4($_POST['keterangan'], 100) : '';
        $temp_meninggal = isset($_POST['temp_meninggal']) ? validTeks4($_POST['temp_meninggal'], 20) : '-';
        $icd1 = isset($_POST['icd1']) ? validTeks4($_POST['icd1'], 20) : '';
        $icd2 = isset($_POST['icd2']) ? validTeks4($_POST['icd2'], 20) : '';
        $icd3 = isset($_POST['icd3']) ? validTeks4($_POST['icd3'], 20) : '';
        $icd4 = isset($_POST['icd4']) ? validTeks4($_POST['icd4'], 20) : '';
        $kd_dokter = isset($_POST['kd_dokter']) ? validTeks4($_POST['kd_dokter'], 20) : '';
        
        if (empty($no_rkm_medis)) {
            throw new Exception('No. Rekam Medis tidak valid');
        }
        
        // Cek apakah sudah ada data
        $cekExist = bukaquery("SELECT no_rkm_medis FROM pasien_mati WHERE no_rkm_medis = '$no_rkm_medis'");
        
        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            // UPDATE
            $query = "UPDATE pasien_mati SET 
                tanggal = '$tanggal',
                jam = '$jam',
                keterangan = '$keterangan',
                temp_meninggal = '$temp_meninggal',
                icd1 = '$icd1',
                icd2 = '$icd2',
                icd3 = '$icd3',
                icd4 = '$icd4',
                kd_dokter = '$kd_dokter'
                WHERE no_rkm_medis = '$no_rkm_medis'";
            $msg = 'Data pasien meninggal berhasil diperbarui';
        } else {
            // INSERT
            $query = "INSERT INTO pasien_mati (
                tanggal, jam, no_rkm_medis, keterangan, temp_meninggal,
                icd1, icd2, icd3, icd4, kd_dokter
            ) VALUES (
                '$tanggal', '$jam', '$no_rkm_medis', '$keterangan', '$temp_meninggal',
                '$icd1', '$icd2', '$icd3', '$icd4', '$kd_dokter'
            )";
            $msg = 'Data pasien meninggal berhasil disimpan';
        }
        
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data pasien meninggal');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS PASIEN MENINGGAL
// ========================================
if ($aksi === 'hapus_pasien_meninggal') {
    
    try {
        $no_rkm_medis = isset($_POST['no_rkm_medis']) ? validTeks4($_POST['no_rkm_medis'], 15) : '';
        
        if (empty($no_rkm_medis)) {
            throw new Exception('No. Rekam Medis tidak valid');
        }
        
        // Cek kepemilikan
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $cekData = bukaquery("SELECT kd_dokter FROM pasien_mati WHERE no_rkm_medis = '$no_rkm_medis'");
        
        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }
        
        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus data ini. Hanya DPJP pengisi yang dapat menghapus.');
        }
        
        $query = "DELETE FROM pasien_mati WHERE no_rkm_medis = '$no_rkm_medis'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data pasien meninggal berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// LOAD TABEL PASIEN MENINGGAL (PAGINATION)
// ========================================
if ($aksi === 'load_tabel_pasien_meninggal') {
    
    try {
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, min(50, intval($_POST['per_page']))) : 10;
        $offset = ($page - 1) * $per_page;
        
        // Count total
        $qCount = bukaquery("SELECT COUNT(*) as total FROM pasien_mati");
        $total = 0;
        if ($qCount) {
            $rCount = mysqli_fetch_assoc($qCount);
            $total = intval($rCount['total']);
        }
        $total_pages = ($total > 0) ? ceil($total / $per_page) : 1;
        
        // Fetch data
        $query = bukaquery("SELECT pm.*, p.nm_pasien, d.nm_dokter 
                           FROM pasien_mati pm 
                           LEFT JOIN pasien p ON pm.no_rkm_medis = p.no_rkm_medis 
                           LEFT JOIN dokter d ON pm.kd_dokter = d.kd_dokter 
                           ORDER BY pm.tanggal DESC, pm.jam DESC 
                           LIMIT $offset, $per_page");
        
        $data = [];
        if ($query) {
            while ($row = mysqli_fetch_assoc($query)) {
                $data[] = [
                    'tanggal' => date('d-m-Y', strtotime($row['tanggal'])),
                    'jam' => $row['jam'],
                    'no_rkm_medis' => $row['no_rkm_medis'],
                    'nm_pasien' => $row['nm_pasien'] ?? '-',
                    'temp_meninggal' => $row['temp_meninggal'],
                    'icd1' => $row['icd1'],
                    'icd2' => $row['icd2'],
                    'icd3' => $row['icd3'],
                    'icd4' => $row['icd4'],
                    'keterangan' => $row['keterangan'],
                    'nm_dokter' => $row['nm_dokter'] ?? '-',
                    'kd_dokter' => $row['kd_dokter']
                ];
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// SIMPAN PENILAIAN BAYI BARU LAHIR
// ========================================
if ($aksi === 'simpan_penilaian_bayi_baru_lahir') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter = '';
        if(isset($_SESSION['ses_dokter']) && !empty($_SESSION['ses_dokter'])) {
            $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        }

        // ---- Ambil semua field dari POST ----
        $tanggal                            = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 20) : date('Y-m-d H:i:s');
        $no_rkm_medis_ibu                   = isset($_POST['no_rkm_medis_ibu'])  ? validTeks4($_POST['no_rkm_medis_ibu'], 15) : '';

        // Riwayat Maternal
        $penyakit_diderita_ibu_raw = isset($_POST['penyakit_diderita_ibu']) ? trim($_POST['penyakit_diderita_ibu']) : 'Tidak Ada';
        $valid_penyakit = ['Tidak Ada', 'Ada'];
        $penyakit_diderita_ibu = in_array($penyakit_diderita_ibu_raw, $valid_penyakit) ? $penyakit_diderita_ibu_raw : 'Tidak Ada';
        $keterangan_penyakit_diderita_ibu   = isset($_POST['keterangan_penyakit_diderita_ibu'])  ? validTeks4($_POST['keterangan_penyakit_diderita_ibu'], 70) : '';
        $obat_dikonsumsi_selama_kehamilan    = isset($_POST['obat_dikonsumsi_selama_kehamilan'])  ? validTeks4($_POST['obat_dikonsumsi_selama_kehamilan'], 150) : '';

        $perawatan_antenatal_raw = isset($_POST['perawatan_antenatal']) ? trim($_POST['perawatan_antenatal']) : 'Ya';
        $perawatan_antenatal = in_array($perawatan_antenatal_raw, ['Ya','Tidak']) ? $perawatan_antenatal_raw : 'Ya';
        $keterangan_perawatan_antenatal     = isset($_POST['keterangan_perawatan_antenatal'])    ? validTeks4($_POST['keterangan_perawatan_antenatal'], 40) : '';

        $terdaftar_ekohort_raw = isset($_POST['terdaftar_ekohort']) ? trim($_POST['terdaftar_ekohort']) : 'Ya';
        $terdaftar_ekohort = in_array($terdaftar_ekohort_raw, ['Ya','Tidak']) ? $terdaftar_ekohort_raw : 'Ya';
        $keterangan_terdaftar_ekohort       = isset($_POST['keterangan_terdaftar_ekohort'])      ? validTeks4($_POST['keterangan_terdaftar_ekohort'], 40) : '';

        $valid_penyulit_kehamilan = ['Tidak Ada','Hiperemesis','CPD','Kelainan Letak','Preeklampsia','Lain-lain'];
        $penyulit_kehamilan_raw = isset($_POST['penyulit_kehamilan']) ? trim($_POST['penyulit_kehamilan']) : 'Tidak Ada';
        $penyulit_kehamilan = in_array($penyulit_kehamilan_raw, $valid_penyulit_kehamilan) ? $penyulit_kehamilan_raw : 'Tidak Ada';
        $keterangan_penyulit_kehamilan      = isset($_POST['keterangan_penyulit_kehamilan'])     ? validTeks4($_POST['keterangan_penyulit_kehamilan'], 60) : '';
        $alergi                             = isset($_POST['alergi'])                            ? validTeks4($_POST['alergi'], 60) : '';
        $keterangan_lainnya_riwayat_maternal= isset($_POST['keterangan_lainnya_riwayat_maternal']) ? validTeks4($_POST['keterangan_lainnya_riwayat_maternal'], 150) : '';

        // Riwayat Persalinan
        $umur_kehamilan                     = isset($_POST['umur_kehamilan'])                    ? validTeks4($_POST['umur_kehamilan'], 30) : '';
        $valid_kehamilan = ['Tunggal','Kembar'];
        $kehamilan_raw = isset($_POST['kehamilan']) ? trim($_POST['kehamilan']) : 'Tunggal';
        $kehamilan = in_array($kehamilan_raw, $valid_kehamilan) ? $kehamilan_raw : 'Tunggal';
        $keterangan_kehamilan               = isset($_POST['keterangan_kehamilan'])              ? validTeks4($_POST['keterangan_kehamilan'], 30) : '';
        $urutan_kehamilan                   = isset($_POST['urutan_kehamilan'])                  ? validTeks4($_POST['urutan_kehamilan'], 4) : '';
        $jam_ketuban_pecah                  = isset($_POST['jam_ketuban_pecah'])                 ? validTeks4($_POST['jam_ketuban_pecah'], 4) : '';
        $menit_ketuban_pecah                = isset($_POST['menit_ketuban_pecah'])               ? validTeks4($_POST['menit_ketuban_pecah'], 4) : '';
        $jumlah_air_ketuban                 = isset($_POST['jumlah_air_ketuban'])                ? validTeks4($_POST['jumlah_air_ketuban'], 20) : '';
        $warna_air_ketuban                  = isset($_POST['warna_air_ketuban'])                 ? validTeks4($_POST['warna_air_ketuban'], 20) : '';
        $bau_air_ketuban                    = isset($_POST['bau_air_ketuban'])                   ? validTeks4($_POST['bau_air_ketuban'], 20) : '';
        $letak_bayi                         = isset($_POST['letak_bayi'])                        ? validTeks4($_POST['letak_bayi'], 70) : '';

        $valid_macam = ['Spontan','Porceps','Vacum','Sectio Caesarea'];
        $macam_persalinan_raw = isset($_POST['macam_persalinan']) ? trim($_POST['macam_persalinan']) : 'Spontan';
        $macam_persalinan = in_array($macam_persalinan_raw, $valid_macam) ? $macam_persalinan_raw : 'Spontan';
        $keterangan_macam_persalinan        = isset($_POST['keterangan_macam_persalinan'])       ? validTeks4($_POST['keterangan_macam_persalinan'], 40) : '';

        $valid_indikasi = ['Tidak Ada','Gawat Janin','SC Sebelumnya','Malpresentasi','Lain-lain'];
        $indikasi_raw = isset($_POST['indikasi_persalinan_operatif']) ? trim($_POST['indikasi_persalinan_operatif']) : 'Tidak Ada';
        $indikasi_persalinan_operatif = in_array($indikasi_raw, $valid_indikasi) ? $indikasi_raw : 'Tidak Ada';
        $keterangan_indikasi_persalinan_operatif = isset($_POST['keterangan_indikasi_persalinan_operatif']) ? validTeks4($_POST['keterangan_indikasi_persalinan_operatif'], 50) : '';
        $lama_gawat_janin                   = isset($_POST['lama_gawat_janin'])                  ? validTeks4($_POST['lama_gawat_janin'], 4) : '';
        $obat_selama_persalinan             = isset($_POST['obat_selama_persalinan'])             ? validTeks4($_POST['obat_selama_persalinan'], 150) : '';
        $berat_placenta                     = isset($_POST['berat_placenta'])                    ? validTeks4($_POST['berat_placenta'], 4) : '';
        $kelainan_placenta                  = isset($_POST['kelainan_placenta'])                  ? validTeks4($_POST['kelainan_placenta'], 70) : '';
        $keterangan_lainnya_riwayat_persalinan = isset($_POST['keterangan_lainnya_riwayat_persalinan']) ? validTeks4($_POST['keterangan_lainnya_riwayat_persalinan'], 150) : '';

        // APGAR Score
        $f1  = isset($_POST['f1'])  ? validTeks4($_POST['f1'],  1) : '';
        $u1  = isset($_POST['u1'])  ? validTeks4($_POST['u1'],  1) : '';
        $t1  = isset($_POST['t1'])  ? validTeks4($_POST['t1'],  1) : '';
        $r1  = isset($_POST['r1'])  ? validTeks4($_POST['r1'],  1) : '';
        $w1  = isset($_POST['w1'])  ? validTeks4($_POST['w1'],  1) : '';
        $n1  = isset($_POST['n1'])  ? validTeks4($_POST['n1'],  2) : '';
        $f5  = isset($_POST['f5'])  ? validTeks4($_POST['f5'],  1) : '';
        $u5  = isset($_POST['u5'])  ? validTeks4($_POST['u5'],  1) : '';
        $t5  = isset($_POST['t5'])  ? validTeks4($_POST['t5'],  1) : '';
        $r5  = isset($_POST['r5'])  ? validTeks4($_POST['r5'],  1) : '';
        $w5  = isset($_POST['w5'])  ? validTeks4($_POST['w5'],  1) : '';
        $n5  = isset($_POST['n5'])  ? validTeks4($_POST['n5'],  2) : '';
        $f10 = isset($_POST['f10']) ? validTeks4($_POST['f10'], 1) : '';
        $u10 = isset($_POST['u10']) ? validTeks4($_POST['u10'], 1) : '';
        $t10 = isset($_POST['t10']) ? validTeks4($_POST['t10'], 1) : '';
        $r10 = isset($_POST['r10']) ? validTeks4($_POST['r10'], 1) : '';
        $w10 = isset($_POST['w10']) ? validTeks4($_POST['w10'], 1) : '';
        $n10 = isset($_POST['n10']) ? validTeks4($_POST['n10'], 2) : '';

        // Antropometri bayi
        $bblahir        = isset($_POST['bblahir'])        ? validTeks4($_POST['bblahir'], 5) : '';
        $panjang_badan  = isset($_POST['panjang_badan'])  ? validTeks4($_POST['panjang_badan'], 5) : '';
        $lingkar_kepala = isset($_POST['lingkar_kepala']) ? validTeks4($_POST['lingkar_kepala'], 5) : '';
        $lingkar_dada   = isset($_POST['lingkar_dada'])   ? validTeks4($_POST['lingkar_dada'], 5) : '';

        $valid_resus = ['Tidak','Rangsang Taktil','O2','Ventilasi','Kompresi Dada','Intubasi'];
        $resus_raw = isset($_POST['resusitasi_saat_lahir']) ? trim($_POST['resusitasi_saat_lahir']) : 'Tidak';
        $resusitasi_saat_lahir = in_array($resus_raw, $valid_resus) ? $resus_raw : 'Tidak';
        $keterangan_resusitasi_saat_lahir   = isset($_POST['keterangan_resusitasi_saat_lahir']) ? validTeks4($_POST['keterangan_resusitasi_saat_lahir'], 70) : '';
        $obat_diberikan_saat_lahir          = isset($_POST['obat_diberikan_saat_lahir'])        ? validTeks4($_POST['obat_diberikan_saat_lahir'], 150) : '';
        $keterangan_lainnya_keadaan_bayi    = isset($_POST['keterangan_lainnya_keadaan_bayi'])  ? validTeks4($_POST['keterangan_lainnya_keadaan_bayi'], 150) : '';

        // Pemeriksaan Fisik - semua enum('Normal','Abnormal','Tidak Diperiksa')
        $valid_fisik = ['Normal','Abnormal','Tidak Diperiksa'];
        $fisikFields = ['kondisi_umum','kulit','kepala','leher','mata','hidung','telinga',
                        'dada','paru','jantung','perut','tali_pusat','alat_kelamin',
                        'ruas_tulang_belakang','extrimitas','anus','refleks','denyut_femoral'];
        $fisikValues = [];
        foreach($fisikFields as $ff) {
            $raw = isset($_POST[$ff]) ? trim($_POST[$ff]) : 'Normal';
            $fisikValues[$ff] = in_array($raw, $valid_fisik) ? $raw : 'Normal';
            $ket_key = 'keterangan_' . $ff;
            $fisikValues[$ket_key] = isset($_POST[$ket_key]) ? validTeks4($_POST[$ket_key], 40) : '';
        }
        $pemeriksaan_fisik_lainnya = isset($_POST['pemeriksaan_fisik_lainnya']) ? validTeks4($_POST['pemeriksaan_fisik_lainnya'], 300) : '';
        $pemeriksaan_penunjang     = isset($_POST['pemeriksaan_penunjang'])     ? validTeks4($_POST['pemeriksaan_penunjang'], 500) : '';
        $diagnosa                  = isset($_POST['diagnosa'])                  ? validTeks4($_POST['diagnosa'], 300) : '';
        $tatalaksana               = isset($_POST['tatalaksana'])               ? validTeks4($_POST['tatalaksana'], 1000) : '';

        // Cek INSERT atau UPDATE
        $cek = bukaquery("SELECT no_rawat FROM penilaian_bayi_baru_lahir WHERE no_rawat = '$no_rawat'");

        if (mysqli_num_rows($cek) > 0) {
            // --- UPDATE ---
            $query = "UPDATE penilaian_bayi_baru_lahir SET
                tanggal = '$tanggal',
                kd_dokter = '$kd_dokter',
                no_rkm_medis_ibu = '$no_rkm_medis_ibu',
                penyakit_diderita_ibu = '$penyakit_diderita_ibu',
                keterangan_penyakit_diderita_ibu = '$keterangan_penyakit_diderita_ibu',
                obat_dikonsumsi_selama_kehamilan = '$obat_dikonsumsi_selama_kehamilan',
                perawatan_antenatal = '$perawatan_antenatal',
                keterangan_perawatan_antenatal = '$keterangan_perawatan_antenatal',
                terdaftar_ekohort = '$terdaftar_ekohort',
                keterangan_terdaftar_ekohort = '$keterangan_terdaftar_ekohort',
                penyulit_kehamilan = '$penyulit_kehamilan',
                keterangan_penyulit_kehamilan = '$keterangan_penyulit_kehamilan',
                alergi = '$alergi',
                keterangan_lainnya_riwayat_maternal = '$keterangan_lainnya_riwayat_maternal',
                umur_kehamilan = '$umur_kehamilan',
                kehamilan = '$kehamilan',
                keterangan_kehamilan = '$keterangan_kehamilan',
                urutan_kehamilan = '$urutan_kehamilan',
                jam_ketuban_pecah = '$jam_ketuban_pecah',
                menit_ketuban_pecah = '$menit_ketuban_pecah',
                jumlah_air_ketuban = '$jumlah_air_ketuban',
                warna_air_ketuban = '$warna_air_ketuban',
                bau_air_ketuban = '$bau_air_ketuban',
                letak_bayi = '$letak_bayi',
                macam_persalinan = '$macam_persalinan',
                keterangan_macam_persalinan = '$keterangan_macam_persalinan',
                indikasi_persalinan_operatif = '$indikasi_persalinan_operatif',
                keterangan_indikasi_persalinan_operatif = '$keterangan_indikasi_persalinan_operatif',
                lama_gawat_janin = '$lama_gawat_janin',
                obat_selama_persalinan = '$obat_selama_persalinan',
                berat_placenta = '$berat_placenta',
                kelainan_placenta = '$kelainan_placenta',
                keterangan_lainnya_riwayat_persalinan = '$keterangan_lainnya_riwayat_persalinan',
                f1='$f1', u1='$u1', t1='$t1', r1='$r1', w1='$w1', n1='$n1',
                f5='$f5', u5='$u5', t5='$t5', r5='$r5', w5='$w5', n5='$n5',
                f10='$f10', u10='$u10', t10='$t10', r10='$r10', w10='$w10', n10='$n10',
                bblahir = '$bblahir',
                panjang_badan = '$panjang_badan',
                lingkar_kepala = '$lingkar_kepala',
                lingkar_dada = '$lingkar_dada',
                resusitasi_saat_lahir = '$resusitasi_saat_lahir',
                keterangan_resusitasi_saat_lahir = '$keterangan_resusitasi_saat_lahir',
                obat_diberikan_saat_lahir = '$obat_diberikan_saat_lahir',
                keterangan_lainnya_keadaan_bayi = '$keterangan_lainnya_keadaan_bayi',
                kondisi_umum = '{$fisikValues['kondisi_umum']}',
                keterangan_kondisi_umum = '{$fisikValues['keterangan_kondisi_umum']}',
                kulit = '{$fisikValues['kulit']}',
                keterangan_kulit = '{$fisikValues['keterangan_kulit']}',
                kepala = '{$fisikValues['kepala']}',
                keterangan_kepala = '{$fisikValues['keterangan_kepala']}',
                leher = '{$fisikValues['leher']}',
                keterangan_leher = '{$fisikValues['keterangan_leher']}',
                mata = '{$fisikValues['mata']}',
                keterangan_mata = '{$fisikValues['keterangan_mata']}',
                hidung = '{$fisikValues['hidung']}',
                keterangan_hidung = '{$fisikValues['keterangan_hidung']}',
                telinga = '{$fisikValues['telinga']}',
                keterangan_telinga = '{$fisikValues['keterangan_telinga']}',
                dada = '{$fisikValues['dada']}',
                keterangan_dada = '{$fisikValues['keterangan_dada']}',
                paru = '{$fisikValues['paru']}',
                keterangan_paru = '{$fisikValues['keterangan_paru']}',
                jantung = '{$fisikValues['jantung']}',
                keterangan_jantung = '{$fisikValues['keterangan_jantung']}',
                perut = '{$fisikValues['perut']}',
                keterangan_perut = '{$fisikValues['keterangan_perut']}',
                tali_pusat = '{$fisikValues['tali_pusat']}',
                keterangan_tali_pusat = '{$fisikValues['keterangan_tali_pusat']}',
                alat_kelamin = '{$fisikValues['alat_kelamin']}',
                keterangan_alat_kelamin = '{$fisikValues['keterangan_alat_kelamin']}',
                ruas_tulang_belakang = '{$fisikValues['ruas_tulang_belakang']}',
                keterangan_ruas_tulang_belakang = '{$fisikValues['keterangan_ruas_tulang_belakang']}',
                extrimitas = '{$fisikValues['extrimitas']}',
                keterangan_extrimitas = '{$fisikValues['keterangan_extrimitas']}',
                anus = '{$fisikValues['anus']}',
                keterangan_anus = '{$fisikValues['keterangan_anus']}',
                refleks = '{$fisikValues['refleks']}',
                keterangan_refleks = '{$fisikValues['keterangan_refleks']}',
                denyut_femoral = '{$fisikValues['denyut_femoral']}',
                keterangan_denyut_femoral = '{$fisikValues['keterangan_denyut_femoral']}',
                pemeriksaan_fisik_lainnya = '$pemeriksaan_fisik_lainnya',
                pemeriksaan_penunjang = '$pemeriksaan_penunjang',
                diagnosa = '$diagnosa',
                tatalaksana = '$tatalaksana'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data berhasil diupdate';
        } else {
            // --- INSERT ---
            $query = "INSERT INTO penilaian_bayi_baru_lahir (
                no_rawat, tanggal, kd_dokter, no_rkm_medis_ibu,
                penyakit_diderita_ibu, keterangan_penyakit_diderita_ibu, obat_dikonsumsi_selama_kehamilan,
                perawatan_antenatal, keterangan_perawatan_antenatal,
                terdaftar_ekohort, keterangan_terdaftar_ekohort,
                penyulit_kehamilan, keterangan_penyulit_kehamilan,
                alergi, keterangan_lainnya_riwayat_maternal,
                umur_kehamilan, kehamilan, keterangan_kehamilan, urutan_kehamilan,
                jam_ketuban_pecah, menit_ketuban_pecah,
                jumlah_air_ketuban, warna_air_ketuban, bau_air_ketuban, letak_bayi,
                macam_persalinan, keterangan_macam_persalinan,
                indikasi_persalinan_operatif, keterangan_indikasi_persalinan_operatif,
                lama_gawat_janin, obat_selama_persalinan,
                berat_placenta, kelainan_placenta, keterangan_lainnya_riwayat_persalinan,
                f1, u1, t1, r1, w1, n1,
                f5, u5, t5, r5, w5, n5,
                f10, u10, t10, r10, w10, n10,
                bblahir, panjang_badan, lingkar_kepala, lingkar_dada,
                resusitasi_saat_lahir, keterangan_resusitasi_saat_lahir,
                obat_diberikan_saat_lahir, keterangan_lainnya_keadaan_bayi,
                kondisi_umum, keterangan_kondisi_umum,
                kulit, keterangan_kulit,
                kepala, keterangan_kepala,
                leher, keterangan_leher,
                mata, keterangan_mata,
                hidung, keterangan_hidung,
                telinga, keterangan_telinga,
                dada, keterangan_dada,
                paru, keterangan_paru,
                jantung, keterangan_jantung,
                perut, keterangan_perut,
                tali_pusat, keterangan_tali_pusat,
                alat_kelamin, keterangan_alat_kelamin,
                ruas_tulang_belakang, keterangan_ruas_tulang_belakang,
                extrimitas, keterangan_extrimitas,
                anus, keterangan_anus,
                refleks, keterangan_refleks,
                denyut_femoral, keterangan_denyut_femoral,
                pemeriksaan_fisik_lainnya, pemeriksaan_penunjang,
                diagnosa, tatalaksana
            ) VALUES (
                '$no_rawat', '$tanggal', '$kd_dokter', '$no_rkm_medis_ibu',
                '$penyakit_diderita_ibu', '$keterangan_penyakit_diderita_ibu', '$obat_dikonsumsi_selama_kehamilan',
                '$perawatan_antenatal', '$keterangan_perawatan_antenatal',
                '$terdaftar_ekohort', '$keterangan_terdaftar_ekohort',
                '$penyulit_kehamilan', '$keterangan_penyulit_kehamilan',
                '$alergi', '$keterangan_lainnya_riwayat_maternal',
                '$umur_kehamilan', '$kehamilan', '$keterangan_kehamilan', '$urutan_kehamilan',
                '$jam_ketuban_pecah', '$menit_ketuban_pecah',
                '$jumlah_air_ketuban', '$warna_air_ketuban', '$bau_air_ketuban', '$letak_bayi',
                '$macam_persalinan', '$keterangan_macam_persalinan',
                '$indikasi_persalinan_operatif', '$keterangan_indikasi_persalinan_operatif',
                '$lama_gawat_janin', '$obat_selama_persalinan',
                '$berat_placenta', '$kelainan_placenta', '$keterangan_lainnya_riwayat_persalinan',
                '$f1', '$u1', '$t1', '$r1', '$w1', '$n1',
                '$f5', '$u5', '$t5', '$r5', '$w5', '$n5',
                '$f10', '$u10', '$t10', '$r10', '$w10', '$n10',
                '$bblahir', '$panjang_badan', '$lingkar_kepala', '$lingkar_dada',
                '$resusitasi_saat_lahir', '$keterangan_resusitasi_saat_lahir',
                '$obat_diberikan_saat_lahir', '$keterangan_lainnya_keadaan_bayi',
                '{$fisikValues['kondisi_umum']}', '{$fisikValues['keterangan_kondisi_umum']}',
                '{$fisikValues['kulit']}', '{$fisikValues['keterangan_kulit']}',
                '{$fisikValues['kepala']}', '{$fisikValues['keterangan_kepala']}',
                '{$fisikValues['leher']}', '{$fisikValues['keterangan_leher']}',
                '{$fisikValues['mata']}', '{$fisikValues['keterangan_mata']}',
                '{$fisikValues['hidung']}', '{$fisikValues['keterangan_hidung']}',
                '{$fisikValues['telinga']}', '{$fisikValues['keterangan_telinga']}',
                '{$fisikValues['dada']}', '{$fisikValues['keterangan_dada']}',
                '{$fisikValues['paru']}', '{$fisikValues['keterangan_paru']}',
                '{$fisikValues['jantung']}', '{$fisikValues['keterangan_jantung']}',
                '{$fisikValues['perut']}', '{$fisikValues['keterangan_perut']}',
                '{$fisikValues['tali_pusat']}', '{$fisikValues['keterangan_tali_pusat']}',
                '{$fisikValues['alat_kelamin']}', '{$fisikValues['keterangan_alat_kelamin']}',
                '{$fisikValues['ruas_tulang_belakang']}', '{$fisikValues['keterangan_ruas_tulang_belakang']}',
                '{$fisikValues['extrimitas']}', '{$fisikValues['keterangan_extrimitas']}',
                '{$fisikValues['anus']}', '{$fisikValues['keterangan_anus']}',
                '{$fisikValues['refleks']}', '{$fisikValues['keterangan_refleks']}',
                '{$fisikValues['denyut_femoral']}', '{$fisikValues['keterangan_denyut_femoral']}',
                '$pemeriksaan_fisik_lainnya', '$pemeriksaan_penunjang',
                '$diagnosa', '$tatalaksana'
            )";
            $msg = 'Data berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data: ' . mysqli_error($GLOBALS['db_conn']));
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS PENILAIAN BAYI BARU LAHIR
// ========================================
if ($aksi === 'hapus_penilaian_bayi_baru_lahir') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $query  = "DELETE FROM penilaian_bayi_baru_lahir WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================================
// SIMPAN PENILAIAN AWAL MEDIS RAWAT INAP JANTUNG
// ========================================================
if ($aksi === 'simpan_awalmedisjantunginap') {

    try {
        $no_rawat  = isset($_POST['no_rawat'])  ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Tanggal
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';

        // Riwayat Kesehatan
        $anamnesis     = isset($_POST['anamnesis'])     ? validTeks4($_POST['anamnesis'], 20)       : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? validTeks4($_POST['hubungan'], 30)        : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps           = isset($_POST['rps'])           ? validTeks4($_POST['rps'], 2000)           : '';
        $rpk           = isset($_POST['rpk'])           ? validTeks4($_POST['rpk'], 1000)           : '';
        $rpd           = isset($_POST['rpd'])           ? validTeks4($_POST['rpd'], 1000)           : '';
        $rpo           = isset($_POST['rpo'])           ? validTeks4($_POST['rpo'], 1000)           : '';
        $alergi        = isset($_POST['alergi'])        ? validTeks4($_POST['alergi'], 50)          : '';

        if (empty($keluhan_utama)) throw new Exception('Keluhan utama harus diisi');

        // Validasi enum anamnesis — whitelist
        if (!in_array($anamnesis, ['Autoanamnesis', 'Alloanamnesis'])) $anamnesis = 'Autoanamnesis';

        // Pemeriksaan Fisik
        $td   = isset($_POST['td'])   ? validTeks4($_POST['td'], 8)   : '';
        $bb   = isset($_POST['bb'])   ? validTeks4($_POST['bb'], 5)   : '';
        $tb   = isset($_POST['tb'])   ? validTeks4($_POST['tb'], 5)   : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $rr   = isset($_POST['rr'])   ? validTeks4($_POST['rr'], 5)   : '';

        // Enum keadaan_umum — whitelist
        $ku_raw       = isset($_POST['keadaan_umum']) ? trim($_POST['keadaan_umum']) : 'Sehat';
        $keadaan_umum = in_array($ku_raw, ['Sehat', 'Sakit Ringan', 'Sakit Sedang', 'Sakit Berat']) ? $ku_raw : 'Sehat';

        $nyeri          = isset($_POST['nyeri'])          ? validTeks4($_POST['nyeri'], 50)          : '';
        $status_nutrisi = isset($_POST['status_nutrisi']) ? validTeks4($_POST['status_nutrisi'], 50) : '';

        // Status Kelainan — enum whitelist
        $enumOrgan = ['Normal', 'Abnormal', 'Tidak Diperiksa'];

        $jantung_raw = isset($_POST['jantung']) ? trim($_POST['jantung']) : 'Tidak Diperiksa';
        $jantung     = in_array($jantung_raw, $enumOrgan) ? $jantung_raw : 'Tidak Diperiksa';
        $keterangan_jantung = isset($_POST['keterangan_jantung']) ? validTeks4($_POST['keterangan_jantung'], 50) : '';

        $paru_raw = isset($_POST['paru']) ? trim($_POST['paru']) : 'Tidak Diperiksa';
        $paru     = in_array($paru_raw, $enumOrgan) ? $paru_raw : 'Tidak Diperiksa';
        $keterangan_paru = isset($_POST['keterangan_paru']) ? validTeks4($_POST['keterangan_paru'], 50) : '';

        $ekstrimitas_raw = isset($_POST['ekstrimitas']) ? trim($_POST['ekstrimitas']) : 'Tidak Diperiksa';
        $ekstrimitas     = in_array($ekstrimitas_raw, $enumOrgan) ? $ekstrimitas_raw : 'Tidak Diperiksa';
        $keterangan_ekstrimitas = isset($_POST['keterangan_ekstrimitas']) ? validTeks4($_POST['keterangan_ekstrimitas'], 50) : '';

        $lainnya = isset($_POST['lainnya']) ? validTeks4($_POST['lainnya'], 1000) : '';

        // Pemeriksaan Penunjang
        $lab            = isset($_POST['lab'])            ? validTeks4($_POST['lab'], 500)            : '';
        $ekg            = isset($_POST['ekg'])            ? validTeks4($_POST['ekg'], 500)            : '';
        $penunjang_lain = isset($_POST['penunjang_lain']) ? validTeks4($_POST['penunjang_lain'], 500) : '';

        // Diagnosis
        $diagnosis  = isset($_POST['diagnosis'])  ? validTeks4($_POST['diagnosis'], 500)  : '';
        $diagnosis2 = isset($_POST['diagnosis2']) ? validTeks4($_POST['diagnosis2'], 500) : '';

        // Permasalahan & Tatalaksana
        $permasalahan = isset($_POST['permasalahan']) ? validTeks4($_POST['permasalahan'], 500) : '';
        $terapi       = isset($_POST['terapi'])       ? validTeks4($_POST['terapi'], 500)       : '';
        $tindakan     = isset($_POST['tindakan'])     ? validTeks4($_POST['tindakan'], 500)     : '';

        // Edukasi
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 500) : '';

        // Cek INSERT atau UPDATE
        $query_check  = "SELECT no_rawat FROM penilaian_medis_ranap_jantung WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_medis_ranap_jantung SET
                        tanggal                 = '$tanggal',
                        kd_dokter               = '$kd_dokter',
                        anamnesis               = '$anamnesis',
                        hubungan                = '$hubungan',
                        keluhan_utama           = '$keluhan_utama',
                        rps                     = '$rps',
                        rpk                     = '$rpk',
                        rpd                     = '$rpd',
                        rpo                     = '$rpo',
                        alergi                  = '$alergi',
                        td                      = '$td',
                        bb                      = '$bb',
                        tb                      = '$tb',
                        suhu                    = '$suhu',
                        nadi                    = '$nadi',
                        rr                      = '$rr',
                        keadaan_umum            = '$keadaan_umum',
                        nyeri                   = '$nyeri',
                        status_nutrisi          = '$status_nutrisi',
                        jantung                 = '$jantung',
                        keterangan_jantung      = '$keterangan_jantung',
                        paru                    = '$paru',
                        keterangan_paru         = '$keterangan_paru',
                        ekstrimitas             = '$ekstrimitas',
                        keterangan_ekstrimitas  = '$keterangan_ekstrimitas',
                        lainnya                 = '$lainnya',
                        lab                     = '$lab',
                        ekg                     = '$ekg',
                        penunjang_lain          = '$penunjang_lain',
                        diagnosis               = '$diagnosis',
                        diagnosis2              = '$diagnosis2',
                        permasalahan            = '$permasalahan',
                        terapi                  = '$terapi',
                        tindakan                = '$tindakan',
                        edukasi                 = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Rawat Inap Jantung');
            insertTracker($query);

            echo json_encode(['status' => 'success', 'message' => 'Data penilaian medis Rawat Inap Jantung berhasil diupdate', 'no_rawat' => $no_rawat, 'action' => 'update']);

        } else {
            // INSERT
            $query = "INSERT INTO penilaian_medis_ranap_jantung (
                        no_rawat, tanggal, kd_dokter,
                        anamnesis, hubungan,
                        keluhan_utama, rps, rpk, rpd, rpo, alergi,
                        td, bb, tb, suhu, nadi, rr,
                        keadaan_umum, nyeri, status_nutrisi,
                        jantung, keterangan_jantung,
                        paru, keterangan_paru,
                        ekstrimitas, keterangan_ekstrimitas,
                        lainnya,
                        lab, ekg, penunjang_lain,
                        diagnosis, diagnosis2,
                        permasalahan, terapi, tindakan,
                        edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter',
                        '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpk', '$rpd', '$rpo', '$alergi',
                        '$td', '$bb', '$tb', '$suhu', '$nadi', '$rr',
                        '$keadaan_umum', '$nyeri', '$status_nutrisi',
                        '$jantung', '$keterangan_jantung',
                        '$paru', '$keterangan_paru',
                        '$ekstrimitas', '$keterangan_ekstrimitas',
                        '$lainnya',
                        '$lab', '$ekg', '$penunjang_lain',
                        '$diagnosis', '$diagnosis2',
                        '$permasalahan', '$terapi', '$tindakan',
                        '$edukasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Rawat Inap Jantung');
            insertTracker($query);

            echo json_encode(['status' => 'success', 'message' => 'Data penilaian medis Rawat Inap Jantung berhasil disimpan', 'no_rawat' => $no_rawat, 'action' => 'insert']);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================================
// HAPUS PENILAIAN AWAL MEDIS RAWAT INAP JANTUNG
// ========================================================
if ($aksi === 'hapus_awalmedisjantunginap') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        // Verifikasi kepemilikan data
        $query_cek = "SELECT no_rawat FROM penilaian_medis_ranap_jantung
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        if (mysqli_num_rows($result_cek) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');

        $query_delete = "DELETE FROM penilaian_medis_ranap_jantung WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);
        if (!$result) throw new Exception('Gagal menghapus data penilaian medis Rawat Inap Jantung');

        insertTracker($query_delete);

        echo json_encode(['status' => 'success', 'message' => 'Data penilaian medis Rawat Inap Jantung berhasil dihapus', 'no_rawat' => $no_rawat]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ================================================================
// SIMPAN PENANDAAN OPERASI (PDF ke berkasrawat + INSERT berkas_digital_perawatan)
// ================================================================
if($aksi == 'simpan_penandaan_operasi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? $_POST['no_rawat'] : '';
        $pdf_data = isset($_POST['pdf_data']) ? $_POST['pdf_data'] : '';
        // TAMBAH INI UNTUK DEBUG
        error_log("no_rawat: " . $no_rawat);
        error_log("pdf_data length: " . strlen($pdf_data));
        error_log("pdf_binary length: " . strlen(base64_decode($pdf_data)));
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak boleh kosong');
        if(empty($pdf_data)) throw new Exception('Data PDF tidak boleh kosong');

        // Decode base64 PDF
        $pdf_binary = base64_decode($pdf_data);
        if($pdf_binary === false) throw new Exception('Gagal decode data PDF');

        // 1. Simpan PDF ke direktori lokal edokter/pdf/ terlebih dahulu
        $local_pdf_dir = defined('PDF_LOCAL_DIR') ? PDF_LOCAL_DIR : $_SERVER['DOCUMENT_ROOT'] . APP_BASE_URL . 'pdf/';
        if(!is_dir($local_pdf_dir)) {
            if(!mkdir($local_pdf_dir, 0755, true)) {
                throw new Exception('Gagal membuat direktori lokal: ' . $local_pdf_dir);
            }
        }

        $no_rawat_safe = str_replace('/', '_', $no_rawat);
        $pdf_filename = 'penandaan_operasi_' . $no_rawat_safe . '.pdf';
        $local_pdf_path = $local_pdf_dir . $pdf_filename;

        $bytes_written = file_put_contents($local_pdf_path, $pdf_binary);
        if($bytes_written === false) throw new Exception('Gagal menulis file PDF ke lokal');

        // 2. Upload dari lokal ke server BERKAS_DIGITAL_BASE_URL (lokal atau remote)
        $remote_relative = 'pages/upload/' . $pdf_filename;
        $upload_result = uploadBerkasDigital($local_pdf_path, $remote_relative);
        if(!$upload_result['success']) {
            // Hapus file lokal jika upload gagal
            @unlink($local_pdf_path);
            throw new Exception($upload_result['message']);
        }

        // 3. Hapus file lokal sementara (sudah terupload)
        @unlink($local_pdf_path);

        // lokasi_file sesuai pattern di tabel: pages/upload/namafile
        $lokasi_file = 'pages/upload/' . $pdf_filename;

        // Kode berkas dari conf.php (join ke master_berkas_digital)
        $kd_berkas = defined('KD_BERKAS_PENANDAAN_OPERASI') ? KD_BERKAS_PENANDAAN_OPERASI : '005';

        // Cek apakah sudah ada record penandaan operasi untuk no_rawat ini
        $cek_existing = bukaquery("SELECT no_rawat FROM berkas_digital_perawatan 
                                   WHERE no_rawat = '$no_rawat' 
                                   AND kode = '$kd_berkas'
                                   AND lokasi_file LIKE '%penandaan_operasi%'");
        
        if(mysqli_num_rows($cek_existing) > 0) {
            // UPDATE jika sudah ada
            $query_update = "UPDATE berkas_digital_perawatan 
                             SET lokasi_file = '$lokasi_file' 
                             WHERE no_rawat = '$no_rawat' 
                             AND kode = '$kd_berkas'
                             AND lokasi_file LIKE '%penandaan_operasi%'";
            $result = bukaquery($query_update);
            if(!$result) throw new Exception('Gagal update data berkas digital');
        } else {
            // INSERT baru
            $query_insert = "INSERT INTO berkas_digital_perawatan (no_rawat, kode, lokasi_file) 
                             VALUES ('$no_rawat', '$kd_berkas', '$lokasi_file')";
            $result = bukaquery($query_insert);
            if(!$result) throw new Exception('Gagal insert data berkas digital');
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Penandaan operasi berhasil disimpan',
            'filename' => $pdf_filename,
            'path' => $lokasi_file,
            'size' => $bytes_written
        ]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ================================================================
// HAPUS PENANDAAN OPERASI (hapus PDF + hapus record berkas_digital_perawatan)
// ================================================================
if($aksi == 'hapus_penandaan_operasi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? $_POST['no_rawat'] : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak boleh kosong');

        $kd_berkas = defined('KD_BERKAS_PENANDAAN_OPERASI') ? KD_BERKAS_PENANDAAN_OPERASI : '005';

        // Cari record di berkas_digital_perawatan
        $cek = bukaquery("SELECT lokasi_file FROM berkas_digital_perawatan 
                          WHERE no_rawat = '$no_rawat' 
                          AND kode = '$kd_berkas'
                          AND lokasi_file LIKE '%penandaan_operasi%'");
        $rs = mysqli_fetch_array($cek);

        if(!$rs) throw new Exception('Data penandaan operasi tidak ditemukan');

        // Hapus file fisik di server berkasrawat (lokal atau remote)
        $lokasi_file = $rs['lokasi_file'];
        $hapus_result = hapusBerkasDigital($lokasi_file);
        if(!$hapus_result['success']) {
            throw new Exception($hapus_result['message']);
        }

        // Hapus record dari database
        $query_delete = "DELETE FROM berkas_digital_perawatan 
                         WHERE no_rawat = '$no_rawat' 
                         AND kode = '$kd_berkas'
                         AND lokasi_file LIKE '%penandaan_operasi%'";
        $result = bukaquery($query_delete);
        if(!$result) throw new Exception('Gagal menghapus data dari database');

        echo json_encode([
            'status' => 'success',
            'message' => 'Penandaan operasi berhasil dihapus'
        ]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SEARCH PETUGAS (autocomplete dari tabel petugas)
// ========================================
if ($aksi === 'search_petugas') {
    try {
        $query_search = isset($_POST['query']) ? validTeks4($_POST['query'], 100) : '';
        if (empty($query_search) || strlen($query_search) < 2) {
            throw new Exception('Masukkan minimal 2 karakter');
        }

        $query = "SELECT nip, nama FROM petugas 
                  WHERE (nip LIKE '%$query_search%' OR nama LIKE '%$query_search%')
                  ORDER BY nama ASC LIMIT 20";
        $result = bukaquery($query);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        echo json_encode(['status' => 'success', 'message' => 'Data petugas ditemukan', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN CHECKLIST PRE OPERASI
// ========================================
if ($aksi === 'simpan_checklist_pre_operasi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Tanggal
        $tanggal_raw = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        // Jika format dd-mm-yyyy HH:ii:ss, convert
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})\s(.+)$/', $tanggal_raw, $m)) {
            $tanggal = $m[3] . '-' . $m[2] . '-' . $m[1] . ' ' . $m[4];
        } else {
            $tanggal = $tanggal_raw;
        }
        $tanggal = validTeks4($tanggal, 25);

        $sncn                    = validTeks4(isset($_POST['sncn']) ? $_POST['sncn'] : '', 25);
        $tindakan                = validTeks4(isset($_POST['tindakan']) ? $_POST['tindakan'] : '', 50);
        $kd_dokter_bedah         = validTeks4(isset($_POST['kd_dokter_bedah']) ? $_POST['kd_dokter_bedah'] : '', 20);
        $kd_dokter_anestesi      = validTeks4(isset($_POST['kd_dokter_anestesi']) ? $_POST['kd_dokter_anestesi'] : '', 20);
        $identitas               = validTeks4(isset($_POST['identitas']) ? $_POST['identitas'] : 'Ya', 10);
        $surat_ijin_bedah        = validTeks4(isset($_POST['surat_ijin_bedah']) ? $_POST['surat_ijin_bedah'] : 'Ada', 20);
        $surat_ijin_anestesi     = validTeks4(isset($_POST['surat_ijin_anestesi']) ? $_POST['surat_ijin_anestesi'] : 'Ada', 20);
        $surat_ijin_transfusi    = validTeks4(isset($_POST['surat_ijin_transfusi']) ? $_POST['surat_ijin_transfusi'] : 'Ada', 20);
        $penandaan_area_operasi  = validTeks4(isset($_POST['penandaan_area_operasi']) ? $_POST['penandaan_area_operasi'] : 'Ada', 20);
        $keadaan_umum            = validTeks4(isset($_POST['keadaan_umum']) ? $_POST['keadaan_umum'] : 'Baik', 10);

        $pemeriksaan_penunjang_rontgen        = validTeks4(isset($_POST['pemeriksaan_penunjang_rontgen']) ? $_POST['pemeriksaan_penunjang_rontgen'] : 'Ada', 20);
        $keterangan_pemeriksaan_penunjang_rontgen = validTeks4(isset($_POST['keterangan_pemeriksaan_penunjang_rontgen']) ? $_POST['keterangan_pemeriksaan_penunjang_rontgen'] : '', 20);
        $pemeriksaan_penunjang_ekg            = validTeks4(isset($_POST['pemeriksaan_penunjang_ekg']) ? $_POST['pemeriksaan_penunjang_ekg'] : 'Ada', 20);
        $keterangan_pemeriksaan_penunjang_ekg = validTeks4(isset($_POST['keterangan_pemeriksaan_penunjang_ekg']) ? $_POST['keterangan_pemeriksaan_penunjang_ekg'] : '', 20);
        $pemeriksaan_penunjang_usg            = validTeks4(isset($_POST['pemeriksaan_penunjang_usg']) ? $_POST['pemeriksaan_penunjang_usg'] : 'Ada', 20);
        $keterangan_pemeriksaan_penunjang_usg = validTeks4(isset($_POST['keterangan_pemeriksaan_penunjang_usg']) ? $_POST['keterangan_pemeriksaan_penunjang_usg'] : '', 20);
        $pemeriksaan_penunjang_ctscan         = validTeks4(isset($_POST['pemeriksaan_penunjang_ctscan']) ? $_POST['pemeriksaan_penunjang_ctscan'] : 'Ada', 20);
        $keterangan_pemeriksaan_penunjang_ctscan = validTeks4(isset($_POST['keterangan_pemeriksaan_penunjang_ctscan']) ? $_POST['keterangan_pemeriksaan_penunjang_ctscan'] : '', 20);
        $pemeriksaan_penunjang_mri            = validTeks4(isset($_POST['pemeriksaan_penunjang_mri']) ? $_POST['pemeriksaan_penunjang_mri'] : 'Ada', 20);
        $keterangan_pemeriksaan_penunjang_mri = validTeks4(isset($_POST['keterangan_pemeriksaan_penunjang_mri']) ? $_POST['keterangan_pemeriksaan_penunjang_mri'] : '', 20);

        $persiapan_darah             = validTeks4(isset($_POST['persiapan_darah']) ? $_POST['persiapan_darah'] : 'Ada', 20);
        $keterangan_persiapan_darah  = validTeks4(isset($_POST['keterangan_persiapan_darah']) ? $_POST['keterangan_persiapan_darah'] : '', 20);
        $perlengkapan_khusus         = validTeks4(isset($_POST['perlengkapan_khusus']) ? $_POST['perlengkapan_khusus'] : 'Ada', 20);
        $nip_petugas_ruangan         = validTeks4(isset($_POST['nip_petugas_ruangan']) ? $_POST['nip_petugas_ruangan'] : '', 20);
        $nip_perawat_ok              = validTeks4(isset($_POST['nip_perawat_ok']) ? $_POST['nip_perawat_ok'] : '', 20);

        // Cek existing
        $cek = bukaquery("SELECT no_rawat FROM checklist_pre_operasi WHERE no_rawat = '$no_rawat'");

        if ($cek && mysqli_num_rows($cek) > 0) {
            $query = "UPDATE checklist_pre_operasi SET
                tanggal = '$tanggal',
                sncn = '$sncn',
                tindakan = '$tindakan',
                kd_dokter_bedah = '$kd_dokter_bedah',
                kd_dokter_anestesi = '$kd_dokter_anestesi',
                identitas = '$identitas',
                surat_ijin_bedah = '$surat_ijin_bedah',
                surat_ijin_anestesi = '$surat_ijin_anestesi',
                surat_ijin_transfusi = '$surat_ijin_transfusi',
                penandaan_area_operasi = '$penandaan_area_operasi',
                keadaan_umum = '$keadaan_umum',
                pemeriksaan_penunjang_rontgen = '$pemeriksaan_penunjang_rontgen',
                keterangan_pemeriksaan_penunjang_rontgen = '$keterangan_pemeriksaan_penunjang_rontgen',
                pemeriksaan_penunjang_ekg = '$pemeriksaan_penunjang_ekg',
                keterangan_pemeriksaan_penunjang_ekg = '$keterangan_pemeriksaan_penunjang_ekg',
                pemeriksaan_penunjang_usg = '$pemeriksaan_penunjang_usg',
                keterangan_pemeriksaan_penunjang_usg = '$keterangan_pemeriksaan_penunjang_usg',
                pemeriksaan_penunjang_ctscan = '$pemeriksaan_penunjang_ctscan',
                keterangan_pemeriksaan_penunjang_ctscan = '$keterangan_pemeriksaan_penunjang_ctscan',
                pemeriksaan_penunjang_mri = '$pemeriksaan_penunjang_mri',
                keterangan_pemeriksaan_penunjang_mri = '$keterangan_pemeriksaan_penunjang_mri',
                persiapan_darah = '$persiapan_darah',
                keterangan_persiapan_darah = '$keterangan_persiapan_darah',
                perlengkapan_khusus = '$perlengkapan_khusus',
                nip_petugas_ruangan = '$nip_petugas_ruangan',
                nip_perawat_ok = '$nip_perawat_ok'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data checklist pre operasi berhasil diupdate';
        } else {
            $query = "INSERT INTO checklist_pre_operasi (
                no_rawat, tanggal, sncn, tindakan,
                kd_dokter_bedah, kd_dokter_anestesi,
                identitas, surat_ijin_bedah, surat_ijin_anestesi, surat_ijin_transfusi,
                penandaan_area_operasi, keadaan_umum,
                pemeriksaan_penunjang_rontgen, keterangan_pemeriksaan_penunjang_rontgen,
                pemeriksaan_penunjang_ekg, keterangan_pemeriksaan_penunjang_ekg,
                pemeriksaan_penunjang_usg, keterangan_pemeriksaan_penunjang_usg,
                pemeriksaan_penunjang_ctscan, keterangan_pemeriksaan_penunjang_ctscan,
                pemeriksaan_penunjang_mri, keterangan_pemeriksaan_penunjang_mri,
                persiapan_darah, keterangan_persiapan_darah,
                perlengkapan_khusus,
                nip_petugas_ruangan, nip_perawat_ok
            ) VALUES (
                '$no_rawat', '$tanggal', '$sncn', '$tindakan',
                '$kd_dokter_bedah', '$kd_dokter_anestesi',
                '$identitas', '$surat_ijin_bedah', '$surat_ijin_anestesi', '$surat_ijin_transfusi',
                '$penandaan_area_operasi', '$keadaan_umum',
                '$pemeriksaan_penunjang_rontgen', '$keterangan_pemeriksaan_penunjang_rontgen',
                '$pemeriksaan_penunjang_ekg', '$keterangan_pemeriksaan_penunjang_ekg',
                '$pemeriksaan_penunjang_usg', '$keterangan_pemeriksaan_penunjang_usg',
                '$pemeriksaan_penunjang_ctscan', '$keterangan_pemeriksaan_penunjang_ctscan',
                '$pemeriksaan_penunjang_mri', '$keterangan_pemeriksaan_penunjang_mri',
                '$persiapan_darah', '$keterangan_persiapan_darah',
                '$perlengkapan_khusus',
                '$nip_petugas_ruangan', '$nip_perawat_ok'
            )";
            $msg = 'Data checklist pre operasi berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data checklist pre operasi');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS CHECKLIST PRE OPERASI
// ========================================
if ($aksi === 'hapus_checklist_pre_operasi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter_bedah, kd_dokter_anestesi FROM checklist_pre_operasi WHERE no_rawat = '$no_rawat'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);

        // Hanya dokter bedah atau dokter anestesi yang boleh hapus
        if ($existing['kd_dokter_bedah'] !== $kd_dokter_login && $existing['kd_dokter_anestesi'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya dokter bedah atau dokter anestesi yang dapat menghapus.');
        }

        $query = "DELETE FROM checklist_pre_operasi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data checklist pre operasi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN SIGN IN SEBELUM ANESTESI
// ========================================
if ($aksi === 'simpan_signin_sebelum_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal_raw = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})\s(.+)$/', $tanggal_raw, $m)) {
            $tanggal = $m[3] . '-' . $m[2] . '-' . $m[1] . ' ' . $m[4];
        } else {
            $tanggal = $tanggal_raw;
        }
        $tanggal = validTeks4($tanggal, 25);

        $sncn                    = validTeks4(isset($_POST['sncn']) ? $_POST['sncn'] : '', 25);
        $tindakan                = validTeks4(isset($_POST['tindakan']) ? $_POST['tindakan'] : '', 50);
        $kd_dokter_bedah         = validTeks4(isset($_POST['kd_dokter_bedah']) ? $_POST['kd_dokter_bedah'] : '', 20);
        $kd_dokter_anestesi      = validTeks4(isset($_POST['kd_dokter_anestesi']) ? $_POST['kd_dokter_anestesi'] : '', 20);
        $identitas               = validTeks4(isset($_POST['identitas']) ? $_POST['identitas'] : 'Ya', 10);
        $penandaan_area_operasi  = validTeks4(isset($_POST['penandaan_area_operasi']) ? $_POST['penandaan_area_operasi'] : 'Ada', 20);
        $alergi                  = validTeks4(isset($_POST['alergi']) ? $_POST['alergi'] : '', 30);
        $resiko_aspirasi         = validTeks4(isset($_POST['resiko_aspirasi']) ? $_POST['resiko_aspirasi'] : 'Ada', 15);
        $resiko_aspirasi_rencana_antisipasi = validTeks4(isset($_POST['resiko_aspirasi_rencana_antisipasi']) ? $_POST['resiko_aspirasi_rencana_antisipasi'] : '', 50);
        $resiko_kehilangan_darah = validTeks4(isset($_POST['resiko_kehilangan_darah']) ? $_POST['resiko_kehilangan_darah'] : 'Tidak Ada', 15);
        $resiko_kehilangan_darah_line = validTeks4(isset($_POST['resiko_kehilangan_darah_line']) ? $_POST['resiko_kehilangan_darah_line'] : '', 30);
        $resiko_kehilangan_darah_rencana_antisipasi = validTeks4(isset($_POST['resiko_kehilangan_darah_rencana_antisipasi']) ? $_POST['resiko_kehilangan_darah_rencana_antisipasi'] : '', 50);
        $kesiapan_alat_obat_anestesi = validTeks4(isset($_POST['kesiapan_alat_obat_anestesi']) ? $_POST['kesiapan_alat_obat_anestesi'] : 'Lengkap', 20);
        $kesiapan_alat_obat_anestesi_rencana_antisipasi = validTeks4(isset($_POST['kesiapan_alat_obat_anestesi_rencana_antisipasi']) ? $_POST['kesiapan_alat_obat_anestesi_rencana_antisipasi'] : '', 50);
        $nip_perawat_ok          = validTeks4(isset($_POST['nip_perawat_ok']) ? $_POST['nip_perawat_ok'] : '', 20);

        $cek = bukaquery("SELECT no_rawat FROM signin_sebelum_anestesi WHERE no_rawat = '$no_rawat'");

        if ($cek && mysqli_num_rows($cek) > 0) {
            $query = "UPDATE signin_sebelum_anestesi SET
                tanggal = '$tanggal', sncn = '$sncn', tindakan = '$tindakan',
                kd_dokter_bedah = '$kd_dokter_bedah', kd_dokter_anestesi = '$kd_dokter_anestesi',
                identitas = '$identitas', penandaan_area_operasi = '$penandaan_area_operasi',
                alergi = '$alergi',
                resiko_aspirasi = '$resiko_aspirasi',
                resiko_aspirasi_rencana_antisipasi = '$resiko_aspirasi_rencana_antisipasi',
                resiko_kehilangan_darah = '$resiko_kehilangan_darah',
                resiko_kehilangan_darah_line = '$resiko_kehilangan_darah_line',
                resiko_kehilangan_darah_rencana_antisipasi = '$resiko_kehilangan_darah_rencana_antisipasi',
                kesiapan_alat_obat_anestesi = '$kesiapan_alat_obat_anestesi',
                kesiapan_alat_obat_anestesi_rencana_antisipasi = '$kesiapan_alat_obat_anestesi_rencana_antisipasi',
                nip_perawat_ok = '$nip_perawat_ok'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data sign in sebelum anestesi berhasil diupdate';
        } else {
            $query = "INSERT INTO signin_sebelum_anestesi (
                no_rawat, tanggal, sncn, tindakan,
                kd_dokter_bedah, kd_dokter_anestesi,
                identitas, penandaan_area_operasi, alergi,
                resiko_aspirasi, resiko_aspirasi_rencana_antisipasi,
                resiko_kehilangan_darah, resiko_kehilangan_darah_line,
                resiko_kehilangan_darah_rencana_antisipasi,
                kesiapan_alat_obat_anestesi, kesiapan_alat_obat_anestesi_rencana_antisipasi,
                nip_perawat_ok
            ) VALUES (
                '$no_rawat', '$tanggal', '$sncn', '$tindakan',
                '$kd_dokter_bedah', '$kd_dokter_anestesi',
                '$identitas', '$penandaan_area_operasi', '$alergi',
                '$resiko_aspirasi', '$resiko_aspirasi_rencana_antisipasi',
                '$resiko_kehilangan_darah', '$resiko_kehilangan_darah_line',
                '$resiko_kehilangan_darah_rencana_antisipasi',
                '$kesiapan_alat_obat_anestesi', '$kesiapan_alat_obat_anestesi_rencana_antisipasi',
                '$nip_perawat_ok'
            )";
            $msg = 'Data sign in sebelum anestesi berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data sign in sebelum anestesi');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS SIGN IN SEBELUM ANESTESI
// ========================================
if ($aksi === 'hapus_signin_sebelum_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter_bedah, kd_dokter_anestesi FROM signin_sebelum_anestesi WHERE no_rawat = '$no_rawat'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter_bedah'] !== $kd_dokter_login && $existing['kd_dokter_anestesi'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya dokter bedah atau dokter anestesi yang dapat menghapus.');
        }

        $query = "DELETE FROM signin_sebelum_anestesi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data sign in sebelum anestesi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN SIGN OUT SEBELUM MENUTUP LUKA
// ========================================
if ($aksi === 'simpan_signout_sebelum_menutup_luka') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal_raw = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})\s(.+)$/', $tanggal_raw, $m)) {
            $tanggal = $m[3] . '-' . $m[2] . '-' . $m[1] . ' ' . $m[4];
        } else {
            $tanggal = $tanggal_raw;
        }
        $tanggal = validTeks4($tanggal, 25);

        $sncn                    = validTeks4(isset($_POST['sncn'])                    ? $_POST['sncn']                    : '', 25);
        $tindakan                = validTeks4(isset($_POST['tindakan'])                ? $_POST['tindakan']                : '', 50);
        $kd_dokter_bedah         = validTeks4(isset($_POST['kd_dokter_bedah'])         ? $_POST['kd_dokter_bedah']         : '', 20);
        $kd_dokter_anestesi      = validTeks4(isset($_POST['kd_dokter_anestesi'])      ? $_POST['kd_dokter_anestesi']      : '', 20);

        // Verbal konfirmasi
        $verbal_tindakan         = validTeks4(isset($_POST['verbal_tindakan'])         ? $_POST['verbal_tindakan']         : 'Ya', 10);
        $verbal_kelengkapan_kasa = validTeks4(isset($_POST['verbal_kelengkapan_kasa']) ? $_POST['verbal_kelengkapan_kasa'] : 'Ya', 10);
        $verbal_instrumen        = validTeks4(isset($_POST['verbal_instrumen'])        ? $_POST['verbal_instrumen']        : 'Ya', 10);
        $verbal_alat_tajam       = validTeks4(isset($_POST['verbal_alat_tajam'])       ? $_POST['verbal_alat_tajam']       : 'Ya', 10);

        // Kelengkapan spesimen
        $kelengkapan_specimen_label    = validTeks4(isset($_POST['kelengkapan_specimen_label'])    ? $_POST['kelengkapan_specimen_label']    : 'Lengkap', 30);
        $kelengkapan_specimen_formulir = validTeks4(isset($_POST['kelengkapan_specimen_formulir']) ? $_POST['kelengkapan_specimen_formulir'] : 'Lengkap', 30);

        // Peninjauan kegiatan
        $peninjauan_kegiatan_dokter_bedah      = validTeks4(isset($_POST['peninjauan_kegiatan_dokter_bedah'])      ? $_POST['peninjauan_kegiatan_dokter_bedah']      : 'Ya', 10);
        $peninjauan_kegiatan_dokter_anestesi   = validTeks4(isset($_POST['peninjauan_kegiatan_dokter_anestesi'])   ? $_POST['peninjauan_kegiatan_dokter_anestesi']   : 'Ya', 10);
        $peninjauan_kegiatan_perawat_kamar_ok  = validTeks4(isset($_POST['peninjauan_kegiatan_perawat_kamar_ok'])  ? $_POST['peninjauan_kegiatan_perawat_kamar_ok']  : 'Ya', 10);

        // Perhatian utama fase pemulihan & perawat OK
        $perhatian_utama_fase_pemulihan = validTeks4(isset($_POST['perhatian_utama_fase_pemulihan']) ? $_POST['perhatian_utama_fase_pemulihan'] : '', 100);
        $nip_perawat_ok                 = validTeks4(isset($_POST['nip_perawat_ok'])                 ? $_POST['nip_perawat_ok']                 : '', 20);

        $cek = bukaquery("SELECT no_rawat FROM signout_sebelum_menutup_luka WHERE no_rawat = '$no_rawat'");

        if ($cek && mysqli_num_rows($cek) > 0) {
            $query = "UPDATE signout_sebelum_menutup_luka SET
                tanggal                                = '$tanggal',
                sncn                                   = '$sncn',
                tindakan                               = '$tindakan',
                kd_dokter_bedah                        = '$kd_dokter_bedah',
                kd_dokter_anestesi                     = '$kd_dokter_anestesi',
                verbal_tindakan                        = '$verbal_tindakan',
                verbal_kelengkapan_kasa                = '$verbal_kelengkapan_kasa',
                verbal_instrumen                       = '$verbal_instrumen',
                verbal_alat_tajam                      = '$verbal_alat_tajam',
                kelengkapan_specimen_label             = '$kelengkapan_specimen_label',
                kelengkapan_specimen_formulir          = '$kelengkapan_specimen_formulir',
                peninjauan_kegiatan_dokter_bedah       = '$peninjauan_kegiatan_dokter_bedah',
                peninjauan_kegiatan_dokter_anestesi    = '$peninjauan_kegiatan_dokter_anestesi',
                peninjauan_kegiatan_perawat_kamar_ok   = '$peninjauan_kegiatan_perawat_kamar_ok',
                perhatian_utama_fase_pemulihan         = '$perhatian_utama_fase_pemulihan',
                nip_perawat_ok                         = '$nip_perawat_ok'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data sign out sebelum menutup luka berhasil diupdate';
        } else {
            $query = "INSERT INTO signout_sebelum_menutup_luka (
                no_rawat, tanggal, sncn, tindakan,
                kd_dokter_bedah, kd_dokter_anestesi,
                verbal_tindakan, verbal_kelengkapan_kasa,
                verbal_instrumen, verbal_alat_tajam,
                kelengkapan_specimen_label, kelengkapan_specimen_formulir,
                peninjauan_kegiatan_dokter_bedah, peninjauan_kegiatan_dokter_anestesi,
                peninjauan_kegiatan_perawat_kamar_ok,
                perhatian_utama_fase_pemulihan, nip_perawat_ok
            ) VALUES (
                '$no_rawat', '$tanggal', '$sncn', '$tindakan',
                '$kd_dokter_bedah', '$kd_dokter_anestesi',
                '$verbal_tindakan', '$verbal_kelengkapan_kasa',
                '$verbal_instrumen', '$verbal_alat_tajam',
                '$kelengkapan_specimen_label', '$kelengkapan_specimen_formulir',
                '$peninjauan_kegiatan_dokter_bedah', '$peninjauan_kegiatan_dokter_anestesi',
                '$peninjauan_kegiatan_perawat_kamar_ok',
                '$perhatian_utama_fase_pemulihan', '$nip_perawat_ok'
            )";
            $msg = 'Data sign out sebelum menutup luka berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data sign out sebelum menutup luka');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS SIGN OUT SEBELUM MENUTUP LUKA
// ========================================
if ($aksi === 'hapus_signout_sebelum_menutup_luka') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter_bedah, kd_dokter_anestesi FROM signout_sebelum_menutup_luka WHERE no_rawat = '$no_rawat'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter_bedah'] !== $kd_dokter_login && $existing['kd_dokter_anestesi'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya dokter bedah atau dokter anestesi yang dapat menghapus.');
        }

        $query = "DELETE FROM signout_sebelum_menutup_luka WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data sign out sebelum menutup luka berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN CATATAN ANESTESI SEDASI
// ========================================
if ($aksi === 'simpan_catatan_anestesi_sedasi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal_raw = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})\s(.+)$/', $tanggal_raw, $m)) {
            $tanggal = $m[3] . '-' . $m[2] . '-' . $m[1] . ' ' . $m[4];
        } else {
            $tanggal = $tanggal_raw;
        }
        $tanggal = validTeks4($tanggal, 25);

        // Data Umum
        $kd_dokter_bedah          = validTeks4(isset($_POST['kd_dokter_bedah'])          ? $_POST['kd_dokter_bedah']          : '', 20);
        $kd_dokter_anestesi       = validTeks4(isset($_POST['kd_dokter_anestesi'])       ? $_POST['kd_dokter_anestesi']       : '', 20);
        $diagnosa_pre_bedah       = validTeks4(isset($_POST['diagnosa_pre_bedah'])       ? $_POST['diagnosa_pre_bedah']       : '', 50);
        $tindakan_jenis_pembedahan= validTeks4(isset($_POST['tindakan_jenis_pembedahan'])? $_POST['tindakan_jenis_pembedahan']: '', 50);
        $diagnosa_pasca_bedah     = validTeks4(isset($_POST['diagnosa_pasca_bedah'])     ? $_POST['diagnosa_pasca_bedah']     : '', 50);

        // Pra Induksi
        $pre_induksi_jam          = validTeks4(isset($_POST['pre_induksi_jam'])          ? $_POST['pre_induksi_jam']          : '', 10);
        $pre_induksi_kesadaran    = validTeks4(isset($_POST['pre_induksi_kesadaran'])    ? $_POST['pre_induksi_kesadaran']    : 'Compos Mentis', 20);
        $pre_induksi_td           = validTeks4(isset($_POST['pre_induksi_td'])           ? $_POST['pre_induksi_td']           : '', 8);
        $pre_induksi_nadi         = validTeks4(isset($_POST['pre_induksi_nadi'])         ? $_POST['pre_induksi_nadi']         : '', 5);
        $pre_induksi_rr           = validTeks4(isset($_POST['pre_induksi_rr'])           ? $_POST['pre_induksi_rr']           : '', 5);
        $pre_induksi_suhu         = validTeks4(isset($_POST['pre_induksi_suhu'])         ? $_POST['pre_induksi_suhu']         : '', 5);
        $pre_induksi_o2           = validTeks4(isset($_POST['pre_induksi_o2'])           ? $_POST['pre_induksi_o2']           : '', 5);
        $pre_induksi_tb           = validTeks4(isset($_POST['pre_induksi_tb'])           ? $_POST['pre_induksi_tb']           : '', 5);
        $pre_induksi_bb           = validTeks4(isset($_POST['pre_induksi_bb'])           ? $_POST['pre_induksi_bb']           : '', 5);
        $pre_induksi_rhesus       = validTeks4(isset($_POST['pre_induksi_rhesus'])       ? $_POST['pre_induksi_rhesus']       : '+', 2);
        $pre_induksi_hb           = validTeks4(isset($_POST['pre_induksi_hb'])           ? $_POST['pre_induksi_hb']           : '', 5);
        $pre_induksi_ht           = validTeks4(isset($_POST['pre_induksi_ht'])           ? $_POST['pre_induksi_ht']           : '', 5);
        $pre_induksi_leko         = validTeks4(isset($_POST['pre_induksi_leko'])         ? $_POST['pre_induksi_leko']         : '', 5);
        $pre_induksi_trombo       = validTeks4(isset($_POST['pre_induksi_trombo'])       ? $_POST['pre_induksi_trombo']       : '', 5);
        $pre_induksi_btct         = validTeks4(isset($_POST['pre_induksi_btct'])         ? $_POST['pre_induksi_btct']         : '', 5);
        $pre_induksi_gds          = validTeks4(isset($_POST['pre_induksi_gds'])          ? $_POST['pre_induksi_gds']          : '', 5);
        $pre_induksi_lainlain     = validTeks4(isset($_POST['pre_induksi_lainlain'])     ? $_POST['pre_induksi_lainlain']     : '', 30);

        // Teknik & Alat Khusus
        $teknik_alat_tci              = validTeks4(isset($_POST['teknik_alat_tci'])              ? $_POST['teknik_alat_tci']              : 'Tidak', 5);
        $teknik_alat_glidescopi       = validTeks4(isset($_POST['teknik_alat_glidescopi'])       ? $_POST['teknik_alat_glidescopi']       : 'Tidak', 5);
        $teknik_alat_stimulator_saraf = validTeks4(isset($_POST['teknik_alat_stimulator_saraf']) ? $_POST['teknik_alat_stimulator_saraf'] : 'Tidak', 5);
        $teknik_alat_cpb              = validTeks4(isset($_POST['teknik_alat_cpb'])              ? $_POST['teknik_alat_cpb']              : 'Tidak', 5);
        $teknik_alat_usg              = validTeks4(isset($_POST['teknik_alat_usg'])              ? $_POST['teknik_alat_usg']              : 'Tidak', 5);
        $teknik_alat_ventilasi        = validTeks4(isset($_POST['teknik_alat_ventilasi'])        ? $_POST['teknik_alat_ventilasi']        : 'Tidak', 5);
        $teknik_alat_broncoskopy      = validTeks4(isset($_POST['teknik_alat_broncoskopy'])      ? $_POST['teknik_alat_broncoskopy']      : 'Tidak', 5);
        $teknik_alat_hiopotensi       = validTeks4(isset($_POST['teknik_alat_hiopotensi'])       ? $_POST['teknik_alat_hiopotensi']       : 'Tidak', 5);
        $teknik_alat_lainlain         = validTeks4(isset($_POST['teknik_alat_lainlain'])         ? $_POST['teknik_alat_lainlain']         : '', 100);

        // Monitoring
        $monitoring_ekg               = validTeks4(isset($_POST['monitoring_ekg'])               ? $_POST['monitoring_ekg']               : 'Tidak', 5);
        $monitoring_ekg_keterangan    = validTeks4(isset($_POST['monitoring_ekg_keterangan'])    ? $_POST['monitoring_ekg_keterangan']    : '', 50);
        $monitoring_arteri            = validTeks4(isset($_POST['monitoring_arteri'])            ? $_POST['monitoring_arteri']            : 'Tidak', 5);
        $monitoring_arteri_keterangan = validTeks4(isset($_POST['monitoring_arteri_keterangan']) ? $_POST['monitoring_arteri_keterangan'] : '', 50);
        $monitoring_cvp               = validTeks4(isset($_POST['monitoring_cvp'])               ? $_POST['monitoring_cvp']               : 'Tidak', 5);
        $monitoring_cvp_keterangan    = validTeks4(isset($_POST['monitoring_cvp_keterangan'])    ? $_POST['monitoring_cvp_keterangan']    : '', 50);
        $monitoring_etco              = validTeks4(isset($_POST['monitoring_etco'])              ? $_POST['monitoring_etco']              : 'Tidak', 5);
        $monitoring_stetoskop         = validTeks4(isset($_POST['monitoring_stetoskop'])         ? $_POST['monitoring_stetoskop']         : 'Tidak', 5);
        $monitoring_nibp              = validTeks4(isset($_POST['monitoring_nibp'])              ? $_POST['monitoring_nibp']              : 'Tidak', 5);
        $monitoring_ngt               = validTeks4(isset($_POST['monitoring_ngt'])               ? $_POST['monitoring_ngt']               : 'Tidak', 5);
        $monitoring_bis               = validTeks4(isset($_POST['monitoring_bis'])               ? $_POST['monitoring_bis']               : 'Tidak', 5);
        $monitoring_cath_a_pulmo      = validTeks4(isset($_POST['monitoring_cath_a_pulmo'])      ? $_POST['monitoring_cath_a_pulmo']      : 'Tidak', 5);
        $monitoring_spo2              = validTeks4(isset($_POST['monitoring_spo2'])              ? $_POST['monitoring_spo2']              : 'Tidak', 5);
        $monitoring_kateter           = validTeks4(isset($_POST['monitoring_kateter'])           ? $_POST['monitoring_kateter']           : 'Tidak', 5);
        $monitoring_temp              = validTeks4(isset($_POST['monitoring_temp'])              ? $_POST['monitoring_temp']              : 'Tidak', 5);
        $monitoring_lainlain          = validTeks4(isset($_POST['monitoring_lainlain'])          ? $_POST['monitoring_lainlain']          : '', 100);

        // Status Fisik
        $status_fisik_asa                  = validTeks4(isset($_POST['status_fisik_asa'])                  ? $_POST['status_fisik_asa']                  : '1',     5);
        $status_fisik_alergi               = validTeks4(isset($_POST['status_fisik_alergi'])               ? $_POST['status_fisik_alergi']               : 'Tidak', 5);
        $status_fisik_alergi_keterangan    = validTeks4(isset($_POST['status_fisik_alergi_keterangan'])    ? $_POST['status_fisik_alergi_keterangan']    : '',       50);
        $status_fisik_penyulit_sedasi      = validTeks4(isset($_POST['status_fisik_penyulit_sedasi'])      ? $_POST['status_fisik_penyulit_sedasi']      : '',       150);

        // Perencanaan
        $perencanaan_lanjut                         = validTeks4(isset($_POST['perencanaan_lanjut'])                         ? $_POST['perencanaan_lanjut']                         : 'Ya',    5);
        $perencanaan_lanjut_sedasi                  = validTeks4(isset($_POST['perencanaan_lanjut_sedasi'])                  ? $_POST['perencanaan_lanjut_sedasi']                  : 'Tidak', 10);
        $perencanaan_lanjut_sedasi_keterangan       = validTeks4(isset($_POST['perencanaan_lanjut_sedasi_keterangan'])       ? $_POST['perencanaan_lanjut_sedasi_keterangan']       : '',       30);
        $perencanaan_lanjut_spinal                  = validTeks4(isset($_POST['perencanaan_lanjut_spinal'])                  ? $_POST['perencanaan_lanjut_spinal']                  : 'Tidak', 5);
        $perencanaan_lanjut_anestesi_umum           = validTeks4(isset($_POST['perencanaan_lanjut_anestesi_umum'])           ? $_POST['perencanaan_lanjut_anestesi_umum']           : 'Tidak', 5);
        $perencanaan_lanjut_anestesi_umum_keterangan= validTeks4(isset($_POST['perencanaan_lanjut_anestesi_umum_keterangan'])? $_POST['perencanaan_lanjut_anestesi_umum_keterangan']: '',       30);
        $perencanaan_lanjut_blok_perifer            = validTeks4(isset($_POST['perencanaan_lanjut_blok_perifer'])            ? $_POST['perencanaan_lanjut_blok_perifer']            : 'Tidak', 5);
        $perencanaan_lanjut_blok_perifer_keterangan = validTeks4(isset($_POST['perencanaan_lanjut_blok_perifer_keterangan']) ? $_POST['perencanaan_lanjut_blok_perifer_keterangan'] : '',       30);
        $perencanaan_lanjut_epidural                = validTeks4(isset($_POST['perencanaan_lanjut_epidural'])                ? $_POST['perencanaan_lanjut_epidural']                : 'Tidak', 5);
        $perencanaan_batal                          = validTeks4(isset($_POST['perencanaan_batal'])                          ? $_POST['perencanaan_batal']                          : 'Tidak', 5);
        $perencanaan_batal_alasan                   = validTeks4(isset($_POST['perencanaan_batal_alasan'])                   ? $_POST['perencanaan_batal_alasan']                   : '',       150);

        // Petugas
        $nip_perawat_ok       = validTeks4(isset($_POST['nip_perawat_ok'])       ? $_POST['nip_perawat_ok']       : '', 20);
        $nip_perawat_anestesi = validTeks4(isset($_POST['nip_perawat_anestesi']) ? $_POST['nip_perawat_anestesi'] : '', 20);

        // Cek existing
        $cek = bukaquery("SELECT no_rawat FROM catatan_anestesi_sedasi WHERE no_rawat = '$no_rawat'");

        if ($cek && mysqli_num_rows($cek) > 0) {
            $query = "UPDATE catatan_anestesi_sedasi SET
                tanggal                                      = '$tanggal',
                kd_dokter_bedah                              = '$kd_dokter_bedah',
                kd_dokter_anestesi                           = '$kd_dokter_anestesi',
                diagnosa_pre_bedah                           = '$diagnosa_pre_bedah',
                tindakan_jenis_pembedahan                    = '$tindakan_jenis_pembedahan',
                diagnosa_pasca_bedah                         = '$diagnosa_pasca_bedah',
                pre_induksi_jam                              = '$pre_induksi_jam',
                pre_induksi_kesadaran                        = '$pre_induksi_kesadaran',
                pre_induksi_td                               = '$pre_induksi_td',
                pre_induksi_nadi                             = '$pre_induksi_nadi',
                pre_induksi_rr                               = '$pre_induksi_rr',
                pre_induksi_suhu                             = '$pre_induksi_suhu',
                pre_induksi_o2                               = '$pre_induksi_o2',
                pre_induksi_tb                               = '$pre_induksi_tb',
                pre_induksi_bb                               = '$pre_induksi_bb',
                pre_induksi_rhesus                           = '$pre_induksi_rhesus',
                pre_induksi_hb                               = '$pre_induksi_hb',
                pre_induksi_ht                               = '$pre_induksi_ht',
                pre_induksi_leko                             = '$pre_induksi_leko',
                pre_induksi_trombo                           = '$pre_induksi_trombo',
                pre_induksi_btct                             = '$pre_induksi_btct',
                pre_induksi_gds                              = '$pre_induksi_gds',
                pre_induksi_lainlain                         = '$pre_induksi_lainlain',
                teknik_alat_tci                              = '$teknik_alat_tci',
                teknik_alat_glidescopi                       = '$teknik_alat_glidescopi',
                teknik_alat_stimulator_saraf                 = '$teknik_alat_stimulator_saraf',
                teknik_alat_cpb                              = '$teknik_alat_cpb',
                teknik_alat_usg                              = '$teknik_alat_usg',
                teknik_alat_ventilasi                        = '$teknik_alat_ventilasi',
                teknik_alat_broncoskopy                      = '$teknik_alat_broncoskopy',
                teknik_alat_hiopotensi                       = '$teknik_alat_hiopotensi',
                teknik_alat_lainlain                         = '$teknik_alat_lainlain',
                monitoring_ekg                               = '$monitoring_ekg',
                monitoring_ekg_keterangan                    = '$monitoring_ekg_keterangan',
                monitoring_arteri                            = '$monitoring_arteri',
                monitoring_arteri_keterangan                 = '$monitoring_arteri_keterangan',
                monitoring_cvp                               = '$monitoring_cvp',
                monitoring_cvp_keterangan                    = '$monitoring_cvp_keterangan',
                monitoring_etco                              = '$monitoring_etco',
                monitoring_stetoskop                         = '$monitoring_stetoskop',
                monitoring_nibp                              = '$monitoring_nibp',
                monitoring_ngt                               = '$monitoring_ngt',
                monitoring_bis                               = '$monitoring_bis',
                monitoring_cath_a_pulmo                      = '$monitoring_cath_a_pulmo',
                monitoring_spo2                              = '$monitoring_spo2',
                monitoring_kateter                           = '$monitoring_kateter',
                monitoring_temp                              = '$monitoring_temp',
                monitoring_lainlain                          = '$monitoring_lainlain',
                status_fisik_asa                             = '$status_fisik_asa',
                status_fisik_alergi                          = '$status_fisik_alergi',
                status_fisik_alergi_keterangan               = '$status_fisik_alergi_keterangan',
                status_fisik_penyulit_sedasi                 = '$status_fisik_penyulit_sedasi',
                perencanaan_lanjut                           = '$perencanaan_lanjut',
                perencanaan_lanjut_sedasi                    = '$perencanaan_lanjut_sedasi',
                perencanaan_lanjut_sedasi_keterangan         = '$perencanaan_lanjut_sedasi_keterangan',
                perencanaan_lanjut_spinal                    = '$perencanaan_lanjut_spinal',
                perencanaan_lanjut_anestesi_umum             = '$perencanaan_lanjut_anestesi_umum',
                perencanaan_lanjut_anestesi_umum_keterangan  = '$perencanaan_lanjut_anestesi_umum_keterangan',
                perencanaan_lanjut_blok_perifer              = '$perencanaan_lanjut_blok_perifer',
                perencanaan_lanjut_blok_perifer_keterangan   = '$perencanaan_lanjut_blok_perifer_keterangan',
                perencanaan_lanjut_epidural                  = '$perencanaan_lanjut_epidural',
                perencanaan_batal                            = '$perencanaan_batal',
                perencanaan_batal_alasan                     = '$perencanaan_batal_alasan',
                nip_perawat_ok                               = '$nip_perawat_ok',
                nip_perawat_anestesi                         = '$nip_perawat_anestesi'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data catatan anestesi sedasi berhasil diupdate';
        } else {
            $query = "INSERT INTO catatan_anestesi_sedasi (
                no_rawat, tanggal,
                kd_dokter_bedah, kd_dokter_anestesi,
                diagnosa_pre_bedah, tindakan_jenis_pembedahan, diagnosa_pasca_bedah,
                pre_induksi_jam, pre_induksi_kesadaran, pre_induksi_td, pre_induksi_nadi,
                pre_induksi_rr, pre_induksi_suhu, pre_induksi_o2, pre_induksi_tb, pre_induksi_bb,
                pre_induksi_rhesus, pre_induksi_hb, pre_induksi_ht, pre_induksi_leko,
                pre_induksi_trombo, pre_induksi_btct, pre_induksi_gds, pre_induksi_lainlain,
                teknik_alat_tci, teknik_alat_glidescopi, teknik_alat_stimulator_saraf,
                teknik_alat_cpb, teknik_alat_usg, teknik_alat_ventilasi,
                teknik_alat_broncoskopy, teknik_alat_hiopotensi, teknik_alat_lainlain,
                monitoring_ekg, monitoring_ekg_keterangan,
                monitoring_arteri, monitoring_arteri_keterangan,
                monitoring_cvp, monitoring_cvp_keterangan,
                monitoring_etco, monitoring_stetoskop, monitoring_nibp, monitoring_ngt,
                monitoring_bis, monitoring_cath_a_pulmo, monitoring_spo2,
                monitoring_kateter, monitoring_temp, monitoring_lainlain,
                status_fisik_asa, status_fisik_alergi, status_fisik_alergi_keterangan,
                status_fisik_penyulit_sedasi,
                perencanaan_lanjut,
                perencanaan_lanjut_sedasi, perencanaan_lanjut_sedasi_keterangan,
                perencanaan_lanjut_spinal,
                perencanaan_lanjut_anestesi_umum, perencanaan_lanjut_anestesi_umum_keterangan,
                perencanaan_lanjut_blok_perifer, perencanaan_lanjut_blok_perifer_keterangan,
                perencanaan_lanjut_epidural,
                perencanaan_batal, perencanaan_batal_alasan,
                nip_perawat_ok, nip_perawat_anestesi
            ) VALUES (
                '$no_rawat', '$tanggal',
                '$kd_dokter_bedah', '$kd_dokter_anestesi',
                '$diagnosa_pre_bedah', '$tindakan_jenis_pembedahan', '$diagnosa_pasca_bedah',
                '$pre_induksi_jam', '$pre_induksi_kesadaran', '$pre_induksi_td', '$pre_induksi_nadi',
                '$pre_induksi_rr', '$pre_induksi_suhu', '$pre_induksi_o2', '$pre_induksi_tb', '$pre_induksi_bb',
                '$pre_induksi_rhesus', '$pre_induksi_hb', '$pre_induksi_ht', '$pre_induksi_leko',
                '$pre_induksi_trombo', '$pre_induksi_btct', '$pre_induksi_gds', '$pre_induksi_lainlain',
                '$teknik_alat_tci', '$teknik_alat_glidescopi', '$teknik_alat_stimulator_saraf',
                '$teknik_alat_cpb', '$teknik_alat_usg', '$teknik_alat_ventilasi',
                '$teknik_alat_broncoskopy', '$teknik_alat_hiopotensi', '$teknik_alat_lainlain',
                '$monitoring_ekg', '$monitoring_ekg_keterangan',
                '$monitoring_arteri', '$monitoring_arteri_keterangan',
                '$monitoring_cvp', '$monitoring_cvp_keterangan',
                '$monitoring_etco', '$monitoring_stetoskop', '$monitoring_nibp', '$monitoring_ngt',
                '$monitoring_bis', '$monitoring_cath_a_pulmo', '$monitoring_spo2',
                '$monitoring_kateter', '$monitoring_temp', '$monitoring_lainlain',
                '$status_fisik_asa', '$status_fisik_alergi', '$status_fisik_alergi_keterangan',
                '$status_fisik_penyulit_sedasi',
                '$perencanaan_lanjut',
                '$perencanaan_lanjut_sedasi', '$perencanaan_lanjut_sedasi_keterangan',
                '$perencanaan_lanjut_spinal',
                '$perencanaan_lanjut_anestesi_umum', '$perencanaan_lanjut_anestesi_umum_keterangan',
                '$perencanaan_lanjut_blok_perifer', '$perencanaan_lanjut_blok_perifer_keterangan',
                '$perencanaan_lanjut_epidural',
                '$perencanaan_batal', '$perencanaan_batal_alasan',
                '$nip_perawat_ok', '$nip_perawat_anestesi'
            )";
            $msg = 'Data catatan anestesi sedasi berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data catatan anestesi sedasi');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS CATATAN ANESTESI SEDASI
// ========================================
if ($aksi === 'hapus_catatan_anestesi_sedasi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter_bedah, kd_dokter_anestesi FROM catatan_anestesi_sedasi WHERE no_rawat = '$no_rawat'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter_bedah'] !== $kd_dokter_login && $existing['kd_dokter_anestesi'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya dokter bedah atau dokter anestesi yang dapat menghapus.');
        }

        $query = "DELETE FROM catatan_anestesi_sedasi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data catatan anestesi sedasi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN CHECKLIST KESIAPAN ANESTESI
// ========================================
if ($aksi === 'simpan_checklist_kesiapan_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal           = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 50) : date('Y-m-d H:i:s');
        $nip               = isset($_POST['nip']) ? validTeks4($_POST['nip'], 20) : '';
        $kd_dokter         = isset($_POST['kd_dokter']) ? validTeks4($_POST['kd_dokter'], 20) : '';
        $tindakan          = isset($_POST['tindakan']) ? validTeks4($_POST['tindakan'], 100) : '';
        $teknik_anestesi   = isset($_POST['teknik_anestesi']) ? validTeks4($_POST['teknik_anestesi'], 30) : '';

        // Enum fields
        $enumFields = [
            'listrik1','listrik2','listrik3','listrik4',
            'gasmedis1','gasmedis2','gasmedis3','gasmedis4','gasmedis5','gasmedis6',
            'mesinanes1','mesinanes2','mesinanes3','mesinanes4','mesinanes5',
            'jalannapas1','jalannapas2','jalannapas3','jalannapas4','jalannapas5','jalannapas6','jalannapas7','jalannapas8','jalannapas9',
            'lainlain1','lainlain2','lainlain3','lainlain4','lainlain5','lainlain6','lainlain7','lainlain8',
            'obatobat1','obatobat2','obatobat3','obatobat4','obatobat5','obatobat6'
        ];
        $vals = [];
        foreach ($enumFields as $f) {
            $v = isset($_POST[$f]) ? $_POST[$f] : 'Tidak';
            $vals[$f] = ($v === 'Ya') ? 'Ya' : 'Tidak';
        }

        $keterangan_lainnya = isset($_POST['keterangan_lainnya']) ? validTeks4($_POST['keterangan_lainnya'], 1000) : '';

        // Cek existing
        $cek = bukaquery("SELECT no_rawat FROM checklist_kesiapan_anestesi WHERE no_rawat = '$no_rawat'");
        if ($cek && mysqli_num_rows($cek) > 0) {
            // UPDATE
            $setParts = "tanggal='$tanggal', nip='$nip', kd_dokter='$kd_dokter', tindakan='$tindakan', teknik_anestesi='$teknik_anestesi'";
            foreach ($enumFields as $f) {
                $setParts .= ", $f='" . $vals[$f] . "'";
            }
            $setParts .= ", keterangan_lainnya='$keterangan_lainnya'";
            $query = "UPDATE checklist_kesiapan_anestesi SET $setParts WHERE no_rawat = '$no_rawat'";
            $msg = 'Data checklist kesiapan anestesi berhasil diupdate';
        } else {
            // INSERT
            $cols = 'no_rawat, tanggal, nip, kd_dokter, tindakan, teknik_anestesi';
            $valStr = "'$no_rawat', '$tanggal', '$nip', '$kd_dokter', '$tindakan', '$teknik_anestesi'";
            foreach ($enumFields as $f) {
                $cols .= ", $f";
                $valStr .= ", '" . $vals[$f] . "'";
            }
            $cols .= ', keterangan_lainnya';
            $valStr .= ", '$keterangan_lainnya'";
            $query = "INSERT INTO checklist_kesiapan_anestesi ($cols) VALUES ($valStr)";
            $msg = 'Data checklist kesiapan anestesi berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data checklist kesiapan anestesi');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS CHECKLIST KESIAPAN ANESTESI
// ========================================
if ($aksi === 'hapus_checklist_kesiapan_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter FROM checklist_kesiapan_anestesi WHERE no_rawat = '$no_rawat'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya dokter anestesi pengisi yang dapat menghapus.');
        }

        $query = "DELETE FROM checklist_kesiapan_anestesi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data checklist kesiapan anestesi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN TIMEOUT SEBELUM INSISI
// ========================================
if ($aksi === 'simpan_timeout_sebelum_insisi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal              = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 50) : date('Y-m-d H:i:s');
        $sncn                 = isset($_POST['sncn']) ? validTeks4($_POST['sncn'], 25) : '';
        $tindakan             = isset($_POST['tindakan']) ? validTeks4($_POST['tindakan'], 50) : '';
        $kd_dokter_bedah      = isset($_POST['kd_dokter_bedah']) ? validTeks4($_POST['kd_dokter_bedah'], 20) : '';
        $kd_dokter_anestesi   = isset($_POST['kd_dokter_anestesi']) ? validTeks4($_POST['kd_dokter_anestesi'], 20) : '';

        $verbal_identitas     = (isset($_POST['verbal_identitas']) && $_POST['verbal_identitas'] === 'Ya') ? 'Ya' : 'Tidak';
        $verbal_tindakan      = (isset($_POST['verbal_tindakan']) && $_POST['verbal_tindakan'] === 'Ya') ? 'Ya' : 'Tidak';
        $verbal_area_insisi   = (isset($_POST['verbal_area_insisi']) && $_POST['verbal_area_insisi'] === 'Ya') ? 'Ya' : 'Tidak';

        $penandaan_vals = ['Ada','Tidak Ada','Tidak Diperlukan'];
        $penandaan_area_operasi = (isset($_POST['penandaan_area_operasi']) && in_array($_POST['penandaan_area_operasi'], $penandaan_vals)) ? $_POST['penandaan_area_operasi'] : 'Ada';

        $lama_operasi = isset($_POST['lama_operasi']) ? validTeks4($_POST['lama_operasi'], 10) : '';

        $penayangan_vals = ['Ditayangkan','Benar','Tidak Diperlukan'];
        $penayangan_radiologi = (isset($_POST['penayangan_radiologi']) && in_array($_POST['penayangan_radiologi'], $penayangan_vals)) ? $_POST['penayangan_radiologi'] : 'Ditayangkan';
        $penayangan_ctscan    = (isset($_POST['penayangan_ctscan']) && in_array($_POST['penayangan_ctscan'], $penayangan_vals)) ? $_POST['penayangan_ctscan'] : 'Ditayangkan';
        $penayangan_mri       = (isset($_POST['penayangan_mri']) && in_array($_POST['penayangan_mri'], $penayangan_vals)) ? $_POST['penayangan_mri'] : 'Ditayangkan';

        $antibiotik_profilaks = (isset($_POST['antibiotik_profilaks']) && $_POST['antibiotik_profilaks'] === 'Ya') ? 'Ya' : 'Tidak';
        $nama_antibiotik      = isset($_POST['nama_antibiotik']) ? validTeks4($_POST['nama_antibiotik'], 50) : '';
        $jam_pemberian        = isset($_POST['jam_pemberian']) ? validTeks4($_POST['jam_pemberian'], 10) : '';

        $antisipasi_kehilangan_darah = isset($_POST['antisipasi_kehilangan_darah']) ? validTeks4($_POST['antisipasi_kehilangan_darah'], 50) : '';

        $hal_khusus_vals = ['Ada','Tidak Ada'];
        $hal_khusus = (isset($_POST['hal_khusus']) && in_array($_POST['hal_khusus'], $hal_khusus_vals)) ? $_POST['hal_khusus'] : 'Ada';
        $hal_khusus_diperhatikan = isset($_POST['hal_khusus_diperhatikan']) ? validTeks4($_POST['hal_khusus_diperhatikan'], 100) : '';

        $tanggal_steril       = isset($_POST['tanggal_steril']) ? validTeks4($_POST['tanggal_steril'], 10) : date('Y-m-d');
        $petujuk_sterilisasi  = (isset($_POST['petujuk_sterilisasi']) && $_POST['petujuk_sterilisasi'] === 'Ya') ? 'Ya' : 'Tidak';
        $verifikasi_preoperatif = (isset($_POST['verifikasi_preoperatif']) && $_POST['verifikasi_preoperatif'] === 'Ya') ? 'Ya' : 'Tidak';

        $nip_perawat_ok = isset($_POST['nip_perawat_ok']) ? validTeks4($_POST['nip_perawat_ok'], 20) : '';

        // Cek existing
        $cek = bukaquery("SELECT no_rawat FROM timeout_sebelum_insisi WHERE no_rawat = '$no_rawat'");
        if ($cek && mysqli_num_rows($cek) > 0) {
            $query = "UPDATE timeout_sebelum_insisi SET
                tanggal='$tanggal', sncn='$sncn', tindakan='$tindakan',
                kd_dokter_bedah='$kd_dokter_bedah', kd_dokter_anestesi='$kd_dokter_anestesi',
                verbal_identitas='$verbal_identitas', verbal_tindakan='$verbal_tindakan', verbal_area_insisi='$verbal_area_insisi',
                penandaan_area_operasi='$penandaan_area_operasi', lama_operasi='$lama_operasi',
                penayangan_radiologi='$penayangan_radiologi', penayangan_ctscan='$penayangan_ctscan', penayangan_mri='$penayangan_mri',
                antibiotik_profilaks='$antibiotik_profilaks', nama_antibiotik='$nama_antibiotik', jam_pemberian='$jam_pemberian',
                antisipasi_kehilangan_darah='$antisipasi_kehilangan_darah',
                hal_khusus='$hal_khusus', hal_khusus_diperhatikan='$hal_khusus_diperhatikan',
                tanggal_steril='$tanggal_steril', petujuk_sterilisasi='$petujuk_sterilisasi', verifikasi_preoperatif='$verifikasi_preoperatif',
                nip_perawat_ok='$nip_perawat_ok'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data timeout sebelum insisi berhasil diupdate';
        } else {
            $query = "INSERT INTO timeout_sebelum_insisi (
                no_rawat, tanggal, sncn, tindakan, kd_dokter_bedah, kd_dokter_anestesi,
                verbal_identitas, verbal_tindakan, verbal_area_insisi,
                penandaan_area_operasi, lama_operasi,
                penayangan_radiologi, penayangan_ctscan, penayangan_mri,
                antibiotik_profilaks, nama_antibiotik, jam_pemberian,
                antisipasi_kehilangan_darah,
                hal_khusus, hal_khusus_diperhatikan,
                tanggal_steril, petujuk_sterilisasi, verifikasi_preoperatif,
                nip_perawat_ok
            ) VALUES (
                '$no_rawat', '$tanggal', '$sncn', '$tindakan', '$kd_dokter_bedah', '$kd_dokter_anestesi',
                '$verbal_identitas', '$verbal_tindakan', '$verbal_area_insisi',
                '$penandaan_area_operasi', '$lama_operasi',
                '$penayangan_radiologi', '$penayangan_ctscan', '$penayangan_mri',
                '$antibiotik_profilaks', '$nama_antibiotik', '$jam_pemberian',
                '$antisipasi_kehilangan_darah',
                '$hal_khusus', '$hal_khusus_diperhatikan',
                '$tanggal_steril', '$petujuk_sterilisasi', '$verifikasi_preoperatif',
                '$nip_perawat_ok'
            )";
            $msg = 'Data timeout sebelum insisi berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data timeout sebelum insisi');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS TIMEOUT SEBELUM INSISI
// ========================================
if ($aksi === 'hapus_timeout_sebelum_insisi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter_bedah, kd_dokter_anestesi FROM timeout_sebelum_insisi WHERE no_rawat = '$no_rawat'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter_bedah'] !== $kd_dokter_login && $existing['kd_dokter_anestesi'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya dokter bedah atau dokter anestesi yang dapat menghapus.');
        }

        $query = "DELETE FROM timeout_sebelum_insisi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data timeout sebelum insisi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN SKOR ALDRETTE PASCA ANESTESI
// ========================================
if ($aksi === 'simpan_skor_aldrette_pasca_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal    = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 50) : date('Y-m-d H:i:s');
        $nip        = isset($_POST['nip']) ? validTeks4($_POST['nip'], 20) : '';
        $kd_dokter  = isset($_POST['kd_dokter']) ? validTeks4($_POST['kd_dokter'], 20) : '';

        $penilaian_skala1 = isset($_POST['penilaian_skala1']) ? validTeks4($_POST['penilaian_skala1'], 100) : '';
        $penilaian_nilai1 = isset($_POST['penilaian_nilai1']) ? intval($_POST['penilaian_nilai1']) : 0;
        $penilaian_skala2 = isset($_POST['penilaian_skala2']) ? validTeks4($_POST['penilaian_skala2'], 100) : '';
        $penilaian_nilai2 = isset($_POST['penilaian_nilai2']) ? intval($_POST['penilaian_nilai2']) : 0;
        $penilaian_skala3 = isset($_POST['penilaian_skala3']) ? validTeks4($_POST['penilaian_skala3'], 100) : '';
        $penilaian_nilai3 = isset($_POST['penilaian_nilai3']) ? intval($_POST['penilaian_nilai3']) : 0;
        $penilaian_skala4 = isset($_POST['penilaian_skala4']) ? validTeks4($_POST['penilaian_skala4'], 100) : '';
        $penilaian_nilai4 = isset($_POST['penilaian_nilai4']) ? intval($_POST['penilaian_nilai4']) : 0;
        $penilaian_skala5 = isset($_POST['penilaian_skala5']) ? validTeks4($_POST['penilaian_skala5'], 100) : '';
        $penilaian_nilai5 = isset($_POST['penilaian_nilai5']) ? intval($_POST['penilaian_nilai5']) : 0;

        $penilaian_totalnilai = $penilaian_nilai1 + $penilaian_nilai2 + $penilaian_nilai3 + $penilaian_nilai4 + $penilaian_nilai5;

        $keluar    = isset($_POST['keluar']) ? validTeks4($_POST['keluar'], 200) : '';
        $instruksi = isset($_POST['instruksi']) ? validTeks4($_POST['instruksi'], 250) : '';

        // Cek existing
        $cek = bukaquery("SELECT no_rawat FROM skor_aldrette_pasca_anestesi WHERE no_rawat = '$no_rawat'");
        if ($cek && mysqli_num_rows($cek) > 0) {
            $query = "UPDATE skor_aldrette_pasca_anestesi SET
                tanggal='$tanggal', nip='$nip', kd_dokter='$kd_dokter',
                penilaian_skala1='$penilaian_skala1', penilaian_nilai1='$penilaian_nilai1',
                penilaian_skala2='$penilaian_skala2', penilaian_nilai2='$penilaian_nilai2',
                penilaian_skala3='$penilaian_skala3', penilaian_nilai3='$penilaian_nilai3',
                penilaian_skala4='$penilaian_skala4', penilaian_nilai4='$penilaian_nilai4',
                penilaian_skala5='$penilaian_skala5', penilaian_nilai5='$penilaian_nilai5',
                penilaian_totalnilai='$penilaian_totalnilai',
                keluar='$keluar', instruksi='$instruksi'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data skor aldrette pasca anestesi berhasil diupdate';
        } else {
            $query = "INSERT INTO skor_aldrette_pasca_anestesi (
                no_rawat, tanggal, penilaian_skala1, penilaian_nilai1,
                penilaian_skala2, penilaian_nilai2, penilaian_skala3, penilaian_nilai3,
                penilaian_skala4, penilaian_nilai4, penilaian_skala5, penilaian_nilai5,
                penilaian_totalnilai, keluar, instruksi, kd_dokter, nip
            ) VALUES (
                '$no_rawat', '$tanggal', '$penilaian_skala1', '$penilaian_nilai1',
                '$penilaian_skala2', '$penilaian_nilai2', '$penilaian_skala3', '$penilaian_nilai3',
                '$penilaian_skala4', '$penilaian_nilai4', '$penilaian_skala5', '$penilaian_nilai5',
                '$penilaian_totalnilai', '$keluar', '$instruksi', '$kd_dokter', '$nip'
            )";
            $msg = 'Data skor aldrette pasca anestesi berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data skor aldrette pasca anestesi');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS SKOR ALDRETTE PASCA ANESTESI
// ========================================
if ($aksi === 'hapus_skor_aldrette_pasca_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter FROM skor_aldrette_pasca_anestesi WHERE no_rawat = '$no_rawat'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya dokter anestesi pengisi yang dapat menghapus.');
        }

        $query = "DELETE FROM skor_aldrette_pasca_anestesi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data skor aldrette pasca anestesi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN SKOR STEWARD PASCA ANESTESI
// ========================================
if ($aksi === 'simpan_skor_steward_pasca_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal    = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 50) : date('Y-m-d H:i:s');
        $nip        = isset($_POST['nip']) ? validTeks4($_POST['nip'], 20) : '';
        $kd_dokter  = isset($_POST['kd_dokter']) ? validTeks4($_POST['kd_dokter'], 20) : '';

        $penilaian_skala1 = isset($_POST['penilaian_skala1']) ? validTeks4($_POST['penilaian_skala1'], 100) : '';
        $penilaian_nilai1 = isset($_POST['penilaian_nilai1']) ? intval($_POST['penilaian_nilai1']) : 0;
        $penilaian_skala2 = isset($_POST['penilaian_skala2']) ? validTeks4($_POST['penilaian_skala2'], 100) : '';
        $penilaian_nilai2 = isset($_POST['penilaian_nilai2']) ? intval($_POST['penilaian_nilai2']) : 0;
        $penilaian_skala3 = isset($_POST['penilaian_skala3']) ? validTeks4($_POST['penilaian_skala3'], 100) : '';
        $penilaian_nilai3 = isset($_POST['penilaian_nilai3']) ? intval($_POST['penilaian_nilai3']) : 0;

        $penilaian_totalnilai = $penilaian_nilai1 + $penilaian_nilai2 + $penilaian_nilai3;

        $keluar    = isset($_POST['keluar']) ? validTeks4($_POST['keluar'], 200) : '';
        $instruksi = isset($_POST['instruksi']) ? validTeks4($_POST['instruksi'], 200) : '';

        $cek = bukaquery("SELECT no_rawat FROM skor_steward_pasca_anestesi WHERE no_rawat = '$no_rawat'");
        if ($cek && mysqli_num_rows($cek) > 0) {
            $query = "UPDATE skor_steward_pasca_anestesi SET
                tanggal='$tanggal', nip='$nip', kd_dokter='$kd_dokter',
                penilaian_skala1='$penilaian_skala1', penilaian_nilai1='$penilaian_nilai1',
                penilaian_skala2='$penilaian_skala2', penilaian_nilai2='$penilaian_nilai2',
                penilaian_skala3='$penilaian_skala3', penilaian_nilai3='$penilaian_nilai3',
                penilaian_totalnilai='$penilaian_totalnilai',
                keluar='$keluar', instruksi='$instruksi'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data skor steward pasca anestesi berhasil diupdate';
        } else {
            $query = "INSERT INTO skor_steward_pasca_anestesi (
                no_rawat, tanggal, penilaian_skala1, penilaian_nilai1,
                penilaian_skala2, penilaian_nilai2, penilaian_skala3, penilaian_nilai3,
                penilaian_totalnilai, keluar, instruksi, kd_dokter, nip
            ) VALUES (
                '$no_rawat', '$tanggal', '$penilaian_skala1', '$penilaian_nilai1',
                '$penilaian_skala2', '$penilaian_nilai2', '$penilaian_skala3', '$penilaian_nilai3',
                '$penilaian_totalnilai', '$keluar', '$instruksi', '$kd_dokter', '$nip'
            )";
            $msg = 'Data skor steward pasca anestesi berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data skor steward pasca anestesi');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS SKOR STEWARD PASCA ANESTESI
// ========================================
if ($aksi === 'hapus_skor_steward_pasca_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter FROM skor_steward_pasca_anestesi WHERE no_rawat = '$no_rawat'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya dokter anestesi pengisi yang dapat menghapus.');
        }

        $query = "DELETE FROM skor_steward_pasca_anestesi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data skor steward pasca anestesi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN SKOR BROMAGE PASCA ANESTESI (always INSERT)
// ========================================
if ($aksi === 'simpan_skor_bromage_pasca_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal          = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 50) : date('Y-m-d H:i:s');
        $nip              = isset($_POST['nip']) ? validTeks4($_POST['nip'], 20) : '';
        $kd_dokter        = isset($_POST['kd_dokter']) ? validTeks4($_POST['kd_dokter'], 20) : '';
        $penilaian_skala1 = isset($_POST['penilaian_skala1']) ? validTeks4($_POST['penilaian_skala1'], 100) : '';
        $penilaian_nilai1 = isset($_POST['penilaian_nilai1']) ? intval($_POST['penilaian_nilai1']) : 0;
        $keluar           = isset($_POST['keluar']) ? validTeks4($_POST['keluar'], 200) : '';
        $instruksi        = isset($_POST['instruksi']) ? validTeks4($_POST['instruksi'], 200) : '';

        $mode = isset($_POST['mode']) ? $_POST['mode'] : 'add';
        $tanggal_edit = isset($_POST['tanggal_edit']) ? validTeks4($_POST['tanggal_edit'], 50) : '';

        if ($mode === 'edit' && !empty($tanggal_edit)) {
            // UPDATE existing row
            $query = "UPDATE skor_bromage_pasca_anestesi SET
                tanggal='$tanggal', penilaian_skala1='$penilaian_skala1', penilaian_nilai1='$penilaian_nilai1',
                keluar='$keluar', instruksi='$instruksi', kd_dokter='$kd_dokter', nip='$nip'
                WHERE no_rawat = '$no_rawat' AND tanggal = '$tanggal_edit'";
        } else {
            // INSERT new row
            $query = "INSERT INTO skor_bromage_pasca_anestesi (
                no_rawat, tanggal, penilaian_skala1, penilaian_nilai1,
                keluar, instruksi, kd_dokter, nip
            ) VALUES (
                '$no_rawat', '$tanggal', '$penilaian_skala1', '$penilaian_nilai1',
                '$keluar', '$instruksi', '$kd_dokter', '$nip'
            )";
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data skor bromage pasca anestesi berhasil disimpan']);
        } else {
            throw new Exception('Gagal menyimpan data skor bromage pasca anestesi');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS SKOR BROMAGE PASCA ANESTESI (by no_rawat + tanggal)
// ========================================
if ($aksi === 'hapus_skor_bromage_pasca_anestesi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        $tanggal  = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 50) : '';
        if (empty($no_rawat) || empty($tanggal)) throw new Exception('Parameter tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter, nip FROM skor_bromage_pasca_anestesi WHERE no_rawat = '$no_rawat' AND tanggal = '$tanggal'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);
        $nip_login = isset($_SESSION['ses_nip']) ? encrypt_decrypt($_SESSION['ses_nip'], 'd') : '';
        $bolehHapusBromage = false;
        if (!empty($kd_dokter_login) && $existing['kd_dokter'] === $kd_dokter_login) $bolehHapusBromage = true;
        if (!empty($nip_login) && $existing['nip'] === $nip_login) $bolehHapusBromage = true;
        if (!$bolehHapusBromage) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya pengisi data yang dapat menghapus.');
        }

        $query = "DELETE FROM skor_bromage_pasca_anestesi WHERE no_rawat = '$no_rawat' AND tanggal = '$tanggal'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data skor bromage pasca anestesi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN CATATAN PENGKAJIAN PASKA OPERASI
// ========================================
if ($aksi === 'simpan_catatan_pengkajian_paska_operasi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal             = isset($_POST['tanggal']) ? validTeks4($_POST['tanggal'], 50) : date('Y-m-d H:i:s');
        $kd_dokter           = isset($_POST['kd_dokter']) ? validTeks4($_POST['kd_dokter'], 20) : '';
        $rawat_paska_operasi = isset($_POST['rawat_paska_operasi']) ? validTeks4($_POST['rawat_paska_operasi'], 250) : '';
        $cairan              = isset($_POST['cairan']) ? validTeks4($_POST['cairan'], 500) : '';
        $antibiotika         = isset($_POST['antibiotika']) ? validTeks4($_POST['antibiotika'], 500) : '';
        $analgetika          = isset($_POST['analgetika']) ? validTeks4($_POST['analgetika'], 500) : '';
        $medikamentosa_lain  = isset($_POST['medikamentosa_lain']) ? validTeks4($_POST['medikamentosa_lain'], 500) : '';
        $diet                = isset($_POST['diet']) ? validTeks4($_POST['diet'], 500) : '';
        $pemeriksaan_laborat = isset($_POST['pemeriksaan_laborat']) ? validTeks4($_POST['pemeriksaan_laborat'], 500) : '';
        $tranfusi            = isset($_POST['tranfusi']) ? validTeks4($_POST['tranfusi'], 250) : '';
        $lainlain            = isset($_POST['lainlain']) ? validTeks4($_POST['lainlain'], 500) : '';

        $cek = bukaquery("SELECT no_rawat FROM catatan_pengkajian_paska_operasi WHERE no_rawat = '$no_rawat'");
        if ($cek && mysqli_num_rows($cek) > 0) {
            $query = "UPDATE catatan_pengkajian_paska_operasi SET
                tanggal='$tanggal', kd_dokter='$kd_dokter',
                rawat_paska_operasi='$rawat_paska_operasi', cairan='$cairan', antibiotika='$antibiotika',
                analgetika='$analgetika', medikamentosa_lain='$medikamentosa_lain', diet='$diet',
                pemeriksaan_laborat='$pemeriksaan_laborat', tranfusi='$tranfusi', lainlain='$lainlain'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Data catatan pengkajian paska operasi berhasil diupdate';
        } else {
            $query = "INSERT INTO catatan_pengkajian_paska_operasi (
                no_rawat, tanggal, kd_dokter, rawat_paska_operasi, cairan, antibiotika,
                analgetika, medikamentosa_lain, diet, pemeriksaan_laborat, tranfusi, lainlain
            ) VALUES (
                '$no_rawat', '$tanggal', '$kd_dokter', '$rawat_paska_operasi', '$cairan', '$antibiotika',
                '$analgetika', '$medikamentosa_lain', '$diet', '$pemeriksaan_laborat', '$tranfusi', '$lainlain'
            )";
            $msg = 'Data catatan pengkajian paska operasi berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan data catatan pengkajian paska operasi');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS CATATAN PENGKAJIAN PASKA OPERASI
// ========================================
if ($aksi === 'hapus_catatan_pengkajian_paska_operasi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $cekData = bukaquery("SELECT kd_dokter FROM catatan_pengkajian_paska_operasi WHERE no_rawat = '$no_rawat'");
        if (!$cekData || mysqli_num_rows($cekData) == 0) throw new Exception('Data tidak ditemukan');

        $existing = mysqli_fetch_assoc($cekData);
        if ($existing['kd_dokter'] !== $kd_dokter_login) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus. Hanya dokter pengisi yang dapat menghapus.');
        }

        $query = "DELETE FROM catatan_pengkajian_paska_operasi WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data catatan pengkajian paska operasi berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
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