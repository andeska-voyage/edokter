<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once('../conf/conf.php');
require_once('api_orthanc.php'); // ← LOAD AT TOP!

date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

if (!isset($_SESSION["ses_dokter"])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Session expired atau belum login'
    ]);
    exit();
}

$aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';

// ========================================
// FUNGSI TRACKING
// ========================================
function insertTracker($full_query) {
    $user = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    $tanggal = date('Y-m-d H:i:s');
    $query_clean = $full_query;
    
    if (stripos($query_clean, 'INSERT INTO') !== false) {
        preg_match('/INSERT INTO\s+(\w+)\s*\(/i', $query_clean, $matches);
        $table_name = isset($matches[1]) ? $matches[1] : '';
        
        $values_pos = stripos($query_clean, 'VALUES');
        if ($values_pos !== false && !empty($table_name)) {
            $values_part = trim(substr($query_clean, $values_pos));
            $query_clean = "E-Dokter insert into {$table_name} {$values_part}";
        }
    }
    elseif (stripos($query_clean, 'UPDATE') !== false) {
        $query_clean = "E-Dokter " . trim($query_clean);
        $query_clean = preg_replace('/UPDATE/i', 'update', $query_clean);
        $query_clean = preg_replace('/SET/i', 'set', $query_clean);
        $query_clean = preg_replace('/WHERE/i', 'where', $query_clean);
    }
    elseif (stripos($query_clean, 'DELETE') !== false) {
        $query_clean = trim($query_clean);
        $query_clean = preg_replace('/DELETE/i', 'delete', $query_clean);
        $query_clean = preg_replace('/FROM/i', 'from', $query_clean);
        $query_clean = preg_replace('/WHERE/i', 'where', $query_clean);
        $query_clean = "E-Dokter " . $query_clean;
    }
    
    $query_clean = preg_replace('/\s+/', ' ', $query_clean);
    $query_clean = preg_replace('/\s*\(\s*/', '(', $query_clean);
    $query_clean = preg_replace('/\s*\)\s*/', ')', $query_clean);
    $query_clean = preg_replace('/\s*,\s*/', ',', $query_clean);
    $query_clean = trim($query_clean);
    
    $query_escaped = str_replace("'", "''", $query_clean);
    
    $query = "INSERT INTO trackersql (tanggal, sqle, usere) 
              VALUES ('$tanggal', '$query_escaped', '$user')";
    
    bukaquery($query);
}

// ========================================
// SIMPAN PENILAIAN AWAL MEDIS IGD
// ========================================
if ($aksi === 'simpan_awalmedisigd') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Tab 0: Riwayat
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $anamnesis = isset($_POST['anamnesis']) ? validTeks4($_POST['anamnesis'], 20) : 'Autoanamnesis';
        $hubungan = isset($_POST['hubungan']) ? validTeks4($_POST['hubungan'], 100) : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps = isset($_POST['rps']) ? validTeks4($_POST['rps'], 2000) : '';
        $rpd = isset($_POST['rpd']) ? validTeks4($_POST['rpd'], 1000) : '';
        $rpk = isset($_POST['rpk']) ? validTeks4($_POST['rpk'], 1000) : '';
        $rpo = isset($_POST['rpo']) ? validTeks4($_POST['rpo'], 1000) : '';
        $alergi = isset($_POST['alergi']) ? validTeks4($_POST['alergi'], 100) : '';
        
        // Tab 1: Pemeriksaan Fisik
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
        $kepala = isset($_POST['kepala']) ? validTeks4($_POST['kepala'], 20) : 'Normal';
        $mata = isset($_POST['mata']) ? validTeks4($_POST['mata'], 20) : 'Normal';
        $gigi = isset($_POST['gigi']) ? validTeks4($_POST['gigi'], 20) : 'Normal';
        $leher = isset($_POST['leher']) ? validTeks4($_POST['leher'], 20) : 'Normal';
        $thoraks = isset($_POST['thoraks']) ? validTeks4($_POST['thoraks'], 20) : 'Normal';
        $abdomen = isset($_POST['abdomen']) ? validTeks4($_POST['abdomen'], 20) : 'Normal';
        $genital = isset($_POST['genital']) ? validTeks4($_POST['genital'], 20) : 'Normal';
        $ekstremitas = isset($_POST['ekstremitas']) ? validTeks4($_POST['ekstremitas'], 20) : 'Normal';
        $ket_fisik = isset($_POST['ket_fisik']) ? validTeks4($_POST['ket_fisik'], 5000) : '';
        
        // Tab 2: Status Lokalis
        $ket_lokalis = isset($_POST['ket_lokalis']) ? validTeks4($_POST['ket_lokalis'], 5000) : '';
        
        // Tab 3: Penunjang
        $ekg = isset($_POST['ekg']) ? validTeks4($_POST['ekg'], 5000) : '';
        $rad = isset($_POST['rad']) ? validTeks4($_POST['rad'], 5000) : '';
        $lab = isset($_POST['lab']) ? validTeks4($_POST['lab'], 5000) : '';
        
        // Tab 4: Diagnosis
        $diagnosis = isset($_POST['diagnosis']) ? validTeks4($_POST['diagnosis'], 500) : '';
        
        // Tab 5: Tatalaksana
        $tata = isset($_POST['tata']) ? validTeks4($_POST['tata'], 5000) : '';
        
        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }
        
        $query_check = "SELECT no_rawat FROM penilaian_medis_igd WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_medis_igd SET 
                        tanggal = '$tanggal', kd_dokter = '$kd_dokter', anamnesis = '$anamnesis',
                        hubungan = '$hubungan', keluhan_utama = '$keluhan_utama', rps = '$rps',
                        rpd = '$rpd', rpk = '$rpk', rpo = '$rpo', alergi = '$alergi',
                        keadaan = '$keadaan', gcs = '$gcs', kesadaran = '$kesadaran',
                        td = '$td', nadi = '$nadi', rr = '$rr', suhu = '$suhu', spo = '$spo',
                        bb = '$bb', tb = '$tb', kepala = '$kepala', mata = '$mata',
                        gigi = '$gigi', leher = '$leher', thoraks = '$thoraks',
                        abdomen = '$abdomen', genital = '$genital', ekstremitas = '$ekstremitas',
                        ket_fisik = '$ket_fisik', ket_lokalis = '$ket_lokalis',
                        ekg = '$ekg', rad = '$rad', lab = '$lab',
                        diagnosis = '$diagnosis', tata = '$tata'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal mengupdate data penilaian medis IGD');
            }
            
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis IGD berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            // INSERT
            $query = "INSERT INTO penilaian_medis_igd (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, rpk, rpo, alergi,
                        keadaan, gcs, kesadaran, td, nadi, rr, suhu, spo, bb, tb,
                        kepala, mata, gigi, leher, thoraks, abdomen, genital, ekstremitas,
                        ket_fisik, ket_lokalis, ekg, rad, lab, diagnosis, tata
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$rpk', '$rpo', '$alergi',
                        '$keadaan', '$gcs', '$kesadaran', '$td', '$nadi', '$rr', '$suhu', '$spo', '$bb', '$tb',
                        '$kepala', '$mata', '$gigi', '$leher', '$thoraks', '$abdomen', '$genital', '$ekstremitas',
                        '$ket_fisik', '$ket_lokalis', '$ekg', '$rad', '$lab', '$diagnosis', '$tata'
                      )";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal menyimpan data penilaian medis IGD');
            }
            
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis IGD berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action' => 'insert',
                'images_downloaded' => $images_downloaded,
                'images_source' => $images_source
            ]);
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
// HAPUS PENILAIAN AWAL MEDIS IGD
// ========================================
if ($aksi === 'hapus_awalmedisigd') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        $query_cek = "SELECT no_rawat FROM penilaian_medis_igd 
                      WHERE no_rawat = '$no_rawat' 
                      AND kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }
        
        $query_delete = "DELETE FROM penilaian_medis_igd WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis IGD');
        }
        
        insertTracker($query_delete);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data penilaian medis IGD berhasil dihapus',
            'no_rawat' => $no_rawat
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
// SIMPAN PENILAIAN AWAL MEDIS UMUM (RAWAT JALAN)
// ========================================
if ($aksi === 'simpan_awalmedisumum') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Tab 0: Riwayat
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $anamnesis = isset($_POST['anamnesis']) ? validTeks4($_POST['anamnesis'], 20) : 'Autoanamnesis';
        $hubungan = isset($_POST['hubungan']) ? validTeks4($_POST['hubungan'], 30) : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps = isset($_POST['rps']) ? validTeks4($_POST['rps'], 2000) : '';
        $rpd = isset($_POST['rpd']) ? validTeks4($_POST['rpd'], 1000) : '';
        $rpk = isset($_POST['rpk']) ? validTeks4($_POST['rpk'], 1000) : '';
        $rpo = isset($_POST['rpo']) ? validTeks4($_POST['rpo'], 1000) : '';
        $alergi = isset($_POST['alergi']) ? validTeks4($_POST['alergi'], 50) : '';
        
        // Tab 1: Pemeriksaan Fisik
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
        $kepala = isset($_POST['kepala']) ? validTeks4($_POST['kepala'], 20) : 'Normal';
        $gigi = isset($_POST['gigi']) ? validTeks4($_POST['gigi'], 20) : 'Normal';
        $tht = isset($_POST['tht']) ? validTeks4($_POST['tht'], 20) : 'Normal';
        $thoraks = isset($_POST['thoraks']) ? validTeks4($_POST['thoraks'], 20) : 'Normal';
        $abdomen = isset($_POST['abdomen']) ? validTeks4($_POST['abdomen'], 20) : 'Normal';
        $genital = isset($_POST['genital']) ? validTeks4($_POST['genital'], 20) : 'Normal';
        $ekstremitas = isset($_POST['ekstremitas']) ? validTeks4($_POST['ekstremitas'], 20) : 'Normal';
        $kulit = isset($_POST['kulit']) ? validTeks4($_POST['kulit'], 20) : 'Normal';
        $ket_fisik = isset($_POST['ket_fisik']) ? validTeks4($_POST['ket_fisik'], 5000) : '';
        
        // Tab 2: Status Lokalis
        $ket_lokalis = isset($_POST['ket_lokalis']) ? validTeks4($_POST['ket_lokalis'], 5000) : '';
        
        // Tab 3: Penunjang
        $penunjang = isset($_POST['penunjang']) ? validTeks4($_POST['penunjang'], 5000) : '';
        
        // Tab 4: Diagnosis
        $diagnosis = isset($_POST['diagnosis']) ? validTeks4($_POST['diagnosis'], 500) : '';
        
        // Tab 5: Tatalaksana
        $tata = isset($_POST['tata']) ? validTeks4($_POST['tata'], 5000) : '';
        $konsulrujuk = isset($_POST['konsulrujuk']) ? validTeks4($_POST['konsulrujuk'], 1000) : '';
        
        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }
        
        $query_check = "SELECT no_rawat FROM penilaian_medis_ralan WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_medis_ralan SET 
                        tanggal = '$tanggal', kd_dokter = '$kd_dokter', anamnesis = '$anamnesis',
                        hubungan = '$hubungan', keluhan_utama = '$keluhan_utama', rps = '$rps',
                        rpd = '$rpd', rpk = '$rpk', rpo = '$rpo', alergi = '$alergi',
                        keadaan = '$keadaan', gcs = '$gcs', kesadaran = '$kesadaran',
                        td = '$td', nadi = '$nadi', rr = '$rr', suhu = '$suhu', spo = '$spo',
                        bb = '$bb', tb = '$tb', kepala = '$kepala', gigi = '$gigi',
                        tht = '$tht', thoraks = '$thoraks', abdomen = '$abdomen',
                        genital = '$genital', ekstremitas = '$ekstremitas', kulit = '$kulit',
                        ket_fisik = '$ket_fisik', ket_lokalis = '$ket_lokalis',
                        penunjang = '$penunjang', diagnosis = '$diagnosis',
                        tata = '$tata', konsulrujuk = '$konsulrujuk'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal mengupdate data penilaian medis ralan');
            }
            
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis rawat jalan berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            // INSERT
            $query = "INSERT INTO penilaian_medis_ralan (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, rpk, rpo, alergi,
                        keadaan, gcs, kesadaran, td, nadi, rr, suhu, spo, bb, tb,
                        kepala, gigi, tht, thoraks, abdomen, genital, ekstremitas, kulit,
                        ket_fisik, ket_lokalis, penunjang, diagnosis, tata, konsulrujuk
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$rpk', '$rpo', '$alergi',
                        '$keadaan', '$gcs', '$kesadaran', '$td', '$nadi', '$rr', '$suhu', '$spo', '$bb', '$tb',
                        '$kepala', '$gigi', '$tht', '$thoraks', '$abdomen', '$genital', '$ekstremitas', '$kulit',
                        '$ket_fisik', '$ket_lokalis', '$penunjang', '$diagnosis', '$tata', '$konsulrujuk'
                      )";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal menyimpan data penilaian medis ralan');
            }
            
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis rawat jalan berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action' => 'insert',
                'images_downloaded' => $images_downloaded,
                'images_source' => $images_source
            ]);
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
// HAPUS PENILAIAN AWAL MEDIS UMUM
// ========================================
if ($aksi === 'hapus_awalmedisumum') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan 
                      WHERE no_rawat = '$no_rawat' 
                      AND kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }
        
        $query_delete = "DELETE FROM penilaian_medis_ralan WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis ralan');
        }
        
        insertTracker($query_delete);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data penilaian medis rawat jalan berhasil dihapus',
            'no_rawat' => $no_rawat
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
// SIMPAN PENILAIAN AWAL MEDIS THT
// ========================================
if ($aksi === 'simpan_awalmedistht') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Riwayat Kesehatan
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if(strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis = isset($_POST['anamnesis']) ? validTeks4($_POST['anamnesis'], 20) : 'Autoanamnesis';
        $hubungan = isset($_POST['hubungan']) ? validTeks4($_POST['hubungan'], 30) : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps = isset($_POST['rps']) ? validTeks4($_POST['rps'], 2000) : '';
        $rpd = isset($_POST['rpd']) ? validTeks4($_POST['rpd'], 1000) : '';
        $rpo = isset($_POST['rpo']) ? validTeks4($_POST['rpo'], 1000) : '';
        $alergi = isset($_POST['alergi']) ? validTeks4($_POST['alergi'], 50) : '';
        
        // Pemeriksaan Fisik
        $td = isset($_POST['td']) ? validTeks4($_POST['td'], 8) : '';
        $tb = isset($_POST['tb']) ? validTeks4($_POST['tb'], 5) : '';
        $bb = isset($_POST['bb']) ? validTeks4($_POST['bb'], 5) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $rr = isset($_POST['rr']) ? validTeks4($_POST['rr'], 5) : '';
        $nyeri = isset($_POST['nyeri']) ? validTeks4($_POST['nyeri'], 50) : '';
        $status_nutrisi = isset($_POST['status_nutrisi']) ? validTeks4($_POST['status_nutrisi'], 50) : '';
        $kondisi = isset($_POST['kondisi']) ? validTeks4($_POST['kondisi'], 5000) : '';
        
        // Status Lokalis
        $ket_lokalis = isset($_POST['ket_lokalis']) ? validTeks4($_POST['ket_lokalis'], 5000) : '';
        
        // Pemeriksaan Penunjang
        $lab = isset($_POST['lab']) ? validTeks4($_POST['lab'], 5000) : '';
        $rad = isset($_POST['rad']) ? validTeks4($_POST['rad'], 5000) : '';
        $tes_pendengaran = isset($_POST['tes_pendengaran']) ? validTeks4($_POST['tes_pendengaran'], 5000) : '';
        $penunjang = isset($_POST['penunjang']) ? validTeks4($_POST['penunjang'], 5000) : '';
        
        // Diagnosis
        $diagnosis = isset($_POST['diagnosis']) ? validTeks4($_POST['diagnosis'], 500) : '';
        $diagnosisbanding = isset($_POST['diagnosisbanding']) ? validTeks4($_POST['diagnosisbanding'], 500) : '';
        
        // Permasalahan & Tatalaksana
        $permasalahan = isset($_POST['permasalahan']) ? validTeks4($_POST['permasalahan'], 5000) : '';
        $terapi = isset($_POST['terapi']) ? validTeks4($_POST['terapi'], 5000) : '';
        $tindakan = isset($_POST['tindakan']) ? validTeks4($_POST['tindakan'], 5000) : '';
        $tatalaksana = isset($_POST['tatalaksana']) ? validTeks4($_POST['tatalaksana'], 5000) : '';
        
        // Edukasi
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 1000) : '';
        
        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }
        
        $query_check = "SELECT no_rawat FROM penilaian_medis_ralan_tht WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $query = "UPDATE penilaian_medis_ralan_tht SET 
                        tanggal = '$tanggal', kd_dokter = '$kd_dokter', anamnesis = '$anamnesis',
                        hubungan = '$hubungan', keluhan_utama = '$keluhan_utama', rps = '$rps',
                        rpd = '$rpd', rpo = '$rpo', alergi = '$alergi',
                        td = '$td', tb = '$tb', bb = '$bb', suhu = '$suhu', nadi = '$nadi', rr = '$rr',
                        nyeri = '$nyeri', status_nutrisi = '$status_nutrisi', kondisi = '$kondisi',
                        ket_lokalis = '$ket_lokalis',
                        lab = '$lab', rad = '$rad', tes_pendengaran = '$tes_pendengaran', penunjang = '$penunjang',
                        diagnosis = '$diagnosis', diagnosisbanding = '$diagnosisbanding',
                        permasalahan = '$permasalahan', terapi = '$terapi', tindakan = '$tindakan', tatalaksana = '$tatalaksana',
                        edukasi = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis THT');
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis THT berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            $query = "INSERT INTO penilaian_medis_ralan_tht (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, rpo, alergi,
                        td, tb, bb, suhu, nadi, rr,
                        nyeri, status_nutrisi, kondisi, ket_lokalis,
                        lab, rad, tes_pendengaran, penunjang,
                        diagnosis, diagnosisbanding,
                        permasalahan, terapi, tindakan, tatalaksana,
                        edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$rpo', '$alergi',
                        '$td', '$tb', '$bb', '$suhu', '$nadi', '$rr',
                        '$nyeri', '$status_nutrisi', '$kondisi', '$ket_lokalis',
                        '$lab', '$rad', '$tes_pendengaran', '$penunjang',
                        '$diagnosis', '$diagnosisbanding',
                        '$permasalahan', '$terapi', '$tindakan', '$tatalaksana',
                        '$edukasi'
                      )";
            
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis THT');
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis THT berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action' => 'insert'
            ]);
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
// HAPUS PENILAIAN AWAL MEDIS THT
// ========================================
if ($aksi === 'hapus_awalmedistht') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_tht 
                      WHERE no_rawat = '$no_rawat' 
                      AND kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }
        
        $query_delete = "DELETE FROM penilaian_medis_ralan_tht WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis THT');
        }
        
        insertTracker($query_delete);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data penilaian medis THT berhasil dihapus',
            'no_rawat' => $no_rawat
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
// SIMPAN PEMERIKSAAN USG KANDUNGAN
// ========================================
if ($aksi === 'simpan_usg') {
    
    error_log("=== USG HANDLER START === aksi: $aksi");
    
    try {
        error_log("USG DEBUG - Entered try block");
        
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        error_log("USG DEBUG - no_rawat: $no_rawat, kd_dokter: $kd_dokter");
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $kiriman_dari = isset($_POST['kiriman_dari']) ? validTeks4($_POST['kiriman_dari'], 50) : '';
        $diagnosa_klinis = isset($_POST['diagnosa_klinis']) ? validTeks4($_POST['diagnosa_klinis'], 50) : '';
        $hta = isset($_POST['hta']) ? validTeks4($_POST['hta'], 40) : '';
        $jenis_prestasi = isset($_POST['jenis_prestasi']) ? validTeks4($_POST['jenis_prestasi'], 30) : '';
        $kantong_gestasi = isset($_POST['kantong_gestasi']) ? validTeks4($_POST['kantong_gestasi'], 6) : '';
        $ukuran_bokongkepala = isset($_POST['ukuran_bokongkepala']) ? validTeks4($_POST['ukuran_bokongkepala'], 6) : '';
        $diameter_biparietal = isset($_POST['diameter_biparietal']) ? validTeks4($_POST['diameter_biparietal'], 6) : '';
        $panjang_femur = isset($_POST['panjang_femur']) ? validTeks4($_POST['panjang_femur'], 6) : '';
        $lingkar_abdomen = isset($_POST['lingkar_abdomen']) ? validTeks4($_POST['lingkar_abdomen'], 6) : '';
        $tafsiran_berat_janin = isset($_POST['tafsiran_berat_janin']) ? validTeks4($_POST['tafsiran_berat_janin'], 6) : '';
        $usia_kehamilan = isset($_POST['usia_kehamilan']) ? validTeks4($_POST['usia_kehamilan'], 15) : '';
        $plasenta_berimplatansi = isset($_POST['plasenta_berimplatansi']) ? validTeks4($_POST['plasenta_berimplatansi'], 50) : '';
        $derajat_maturitas = isset($_POST['derajat_maturitas']) ? validTeks4($_POST['derajat_maturitas'], 5) : '';
        $jumlah_air_ketuban = isset($_POST['jumlah_air_ketuban']) ? validTeks4($_POST['jumlah_air_ketuban'], 20) : '';
        $peluang_sex = isset($_POST['peluang_sex']) ? validTeks4($_POST['peluang_sex'], 15) : '-';
        $indek_cairan_ketuban = isset($_POST['indek_cairan_ketuban']) ? validTeks4($_POST['indek_cairan_ketuban'], 40) : '';
        $kelainan_kongenital = isset($_POST['kelainan_kongenital']) ? validTeks4($_POST['kelainan_kongenital'], 60) : '';
        $kesimpulan = isset($_POST['kesimpulan']) ? validTeks4($_POST['kesimpulan'], 200) : '';
        
        $query_check = "SELECT no_rawat FROM hasil_pemeriksaan_usg WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE hasil_pemeriksaan_usg SET 
                        tanggal = '$tanggal',
                        kd_dokter = '$kd_dokter',
                        kiriman_dari = '$kiriman_dari',
                        diagnosa_klinis = '$diagnosa_klinis',
                        hta = '$hta',
                        jenis_prestasi = '$jenis_prestasi',
                        kantong_gestasi = '$kantong_gestasi',
                        ukuran_bokongkepala = '$ukuran_bokongkepala',
                        diameter_biparietal = '$diameter_biparietal',
                        panjang_femur = '$panjang_femur',
                        lingkar_abdomen = '$lingkar_abdomen',
                        tafsiran_berat_janin = '$tafsiran_berat_janin',
                        usia_kehamilan = '$usia_kehamilan',
                        plasenta_berimplatansi = '$plasenta_berimplatansi',
                        derajat_maturitas = '$derajat_maturitas',
                        jumlah_air_ketuban = '$jumlah_air_ketuban',
                        peluang_sex = '$peluang_sex',
                        indek_cairan_ketuban = '$indek_cairan_ketuban',
                        kelainan_kongenital = '$kelainan_kongenital',
                        kesimpulan = '$kesimpulan'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal mengupdate data USG');
            }
            
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data USG Kandungan berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            // INSERT
            $query = "INSERT INTO hasil_pemeriksaan_usg (
                        no_rawat, tanggal, kd_dokter, kiriman_dari, diagnosa_klinis,
                        hta, jenis_prestasi, kantong_gestasi, ukuran_bokongkepala,
                        diameter_biparietal, panjang_femur, lingkar_abdomen,
                        tafsiran_berat_janin, usia_kehamilan, plasenta_berimplatansi,
                        derajat_maturitas, jumlah_air_ketuban, peluang_sex,
                        indek_cairan_ketuban, kelainan_kongenital, kesimpulan
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$kiriman_dari', '$diagnosa_klinis',
                        '$hta', '$jenis_prestasi', '$kantong_gestasi', '$ukuran_bokongkepala',
                        '$diameter_biparietal', '$panjang_femur', '$lingkar_abdomen',
                        '$tafsiran_berat_janin', '$usia_kehamilan', '$plasenta_berimplatansi',
                        '$derajat_maturitas', '$jumlah_air_ketuban', '$peluang_sex',
                        '$indek_cairan_ketuban', '$kelainan_kongenital', '$kesimpulan'
                      )";
            
            error_log("USG DEBUG - About to execute query: " . substr($query, 0, 100));
            
            $result = bukaquery($query);
            
            if (!$result) {
                error_log("USG DEBUG - Query FAILED!");
                throw new Exception('Gagal menyimpan data USG');
            }
            
            error_log("USG DEBUG - Query SUCCESS! Now tracking...");
            
            insertTracker($query);
            
            // ========================================
            // AUTO DOWNLOAD & SAVE IMAGES FROM ORTHANC
            // ========================================
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_usg_base = defined('USG_BASE_URL') ? USG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusg/';
                                $_usg_parsed = parse_url($_usg_base); $_usg_host = isset($_usg_parsed['host']) ? $_usg_parsed['host'] : 'localhost';
                                $_usg_is_local = in_array($_usg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_usg_is_local) { $_usg_path = isset($_usg_parsed['path']) ? rtrim($_usg_parsed['path'], '/') : '/webapps/hasilpemeriksaanusg'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_usg_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/usg_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_usg_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_usg_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_pemeriksaan_usg_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode([
                'status' => 'success',
                'message' => 'Data USG Kandungan berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action' => 'insert',
                'images_downloaded' => $images_downloaded,
                'images_source' => $images_source
            ]);
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
// HAPUS PEMERIKSAAN USG KANDUNGAN
// ========================================
if ($aksi === 'hapus_usg') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        $query_cek = "SELECT no_rawat FROM hasil_pemeriksaan_usg 
                      WHERE no_rawat = '$no_rawat' 
                      AND kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }
        
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_usg_base = defined('USG_BASE_URL') ? USG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusg/';
            $_usg_parsed = parse_url($_usg_base); $_usg_host = isset($_usg_parsed['host']) ? $_usg_parsed['host'] : 'localhost';
            $_usg_is_local = in_array($_usg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_usg_is_local) {
                    $_usg_path = isset($_usg_parsed['path']) ? rtrim($_usg_parsed['path'], '/') : '/webapps/hasilpemeriksaanusg';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_usg_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_usg_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_pemeriksaan_usg WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data USG Kandungan berhasil dihapus','files_deleted'=>$fd]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ========================================
// DOWNLOAD ORTHANC - USG KANDUNGAN
// ========================================
if ($aksi === 'download_orthanc_images') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_usg h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_usg_base = defined('USG_BASE_URL') ? USG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusg/';
        $_usg_parsed = parse_url($_usg_base); $_usg_host = isset($_usg_parsed['host']) ? $_usg_parsed['host'] : 'localhost';
        $_usg_is_local = in_array($_usg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_usg_is_local) { $_usg_path = isset($_usg_parsed['path']) ? rtrim($_usg_parsed['path'], '/') : '/webapps/hasilpemeriksaanusg'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_usg_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/usg_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_usg_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_usg_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_usg_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// UPLOAD MANUAL - USG KANDUNGAN
// ========================================
if ($aksi === 'upload_manual_usg') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_usg WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_usg h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_usg_base = defined('USG_BASE_URL') ? USG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusg/';
        $_usg_parsed = parse_url($_usg_base); $_usg_host = isset($_usg_parsed['host']) ? $_usg_parsed['host'] : 'localhost';
        $_usg_is_local = in_array($_usg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_usg_is_local) { $_usg_path = isset($_usg_parsed['path']) ? rtrim($_usg_parsed['path'], '/') : '/webapps/hasilpemeriksaanusg'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_usg_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/usg_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_usg_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_usg_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[USG-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[USG-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_usg_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// HAPUS SATU GAMBAR - USG KANDUNGAN
// ========================================
if ($aksi === 'hapus_gambar_usg') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo    = isset($_POST['photo'])    ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_usg WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_usg_base = defined('USG_BASE_URL') ? USG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusg/';
        $_usg_parsed = parse_url($_usg_base); $_usg_host = isset($_usg_parsed['host']) ? $_usg_parsed['host'] : 'localhost';
        $_usg_is_local = in_array($_usg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_usg_is_local) { $_usg_path = isset($_usg_parsed['path']) ? rtrim($_usg_parsed['path'], '/') : '/webapps/hasilpemeriksaanusg'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_usg_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_usg_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// SIMPAN PEMERIKSAAN USG GYNECOLOGI
// ========================================
if ($aksi === 'simpan_usg_gynecologi') {
    
    error_log("=== USG GYNECOLOGI HANDLER START === aksi: $aksi");
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $kiriman_dari = isset($_POST['kiriman_dari']) ? validTeks4($_POST['kiriman_dari'], 50) : '';
        $diagnosa_klinis = isset($_POST['diagnosa_klinis']) ? validTeks4($_POST['diagnosa_klinis'], 50) : '';
        $uterus = isset($_POST['uterus']) ? validTeks4($_POST['uterus'], 200) : '';
        $parametrium = isset($_POST['parametrium']) ? validTeks4($_POST['parametrium'], 200) : '';
        $ovarium = isset($_POST['ovarium']) ? validTeks4($_POST['ovarium'], 200) : '';
        $doppler = isset($_POST['doppler']) ? validTeks4($_POST['doppler'], 200) : '';
        $kesimpulan = isset($_POST['kesimpulan']) ? validTeks4($_POST['kesimpulan'], 300) : '';
        
        $query_check = "SELECT no_rawat FROM hasil_pemeriksaan_usg_gynecologi WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE hasil_pemeriksaan_usg_gynecologi SET 
                        tanggal = '$tanggal',
                        kd_dokter = '$kd_dokter',
                        kiriman_dari = '$kiriman_dari',
                        diagnosa_klinis = '$diagnosa_klinis',
                        uterus = '$uterus',
                        parametrium = '$parametrium',
                        ovarium = '$ovarium',
                        doppler = '$doppler',
                        kesimpulan = '$kesimpulan'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal mengupdate data USG Gynecologi');
            }
            
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data USG Gynecologi berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            // INSERT
            $query = "INSERT INTO hasil_pemeriksaan_usg_gynecologi (
                        no_rawat, tanggal, kd_dokter, kiriman_dari, diagnosa_klinis,
                        uterus, parametrium, ovarium, doppler, kesimpulan
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$kiriman_dari', '$diagnosa_klinis',
                        '$uterus', '$parametrium', '$ovarium', '$doppler', '$kesimpulan'
                      )";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal menyimpan data USG Gynecologi');
            }
            
            insertTracker($query);
            
            // AUTO DOWNLOAD & SAVE IMAGES FROM ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_usg_gynecologi_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_gyn_base = defined('USG_GYNECOLOGI_BASE_URL') ? USG_GYNECOLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusggynecologi/';
                                $_gyn_parsed = parse_url($_gyn_base); $_gyn_host = isset($_gyn_parsed['host']) ? $_gyn_parsed['host'] : 'localhost';
                                $_gyn_is_local = in_array($_gyn_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_gyn_is_local) { $_gyn_path = isset($_gyn_parsed['path']) ? rtrim($_gyn_parsed['path'], '/') : '/webapps/hasilpemeriksaanusggynecologi'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_gyn_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/usggyn_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_gyn_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_gyn_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_pemeriksaan_usg_gynecologi_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode(['status'=>'success','message'=>'Data USG Gynecologi berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert','images_downloaded'=>$images_downloaded]);
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
// HAPUS PEMERIKSAAN USG GYNECOLOGI
// ========================================
if ($aksi === 'hapus_usg_gynecologi') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        $query_cek = "SELECT no_rawat FROM hasil_pemeriksaan_usg_gynecologi 
                      WHERE no_rawat = '$no_rawat' 
                      AND kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }
        
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_usg_gynecologi_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_gyn_base = defined('USG_GYNECOLOGI_BASE_URL') ? USG_GYNECOLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusggynecologi/';
            $_gyn_parsed = parse_url($_gyn_base); $_gyn_host = isset($_gyn_parsed['host']) ? $_gyn_parsed['host'] : 'localhost';
            $_gyn_is_local = in_array($_gyn_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_gyn_is_local) {
                    $_gyn_path = isset($_gyn_parsed['path']) ? rtrim($_gyn_parsed['path'], '/') : '/webapps/hasilpemeriksaanusggynecologi';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_gyn_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_gyn_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_usg_gynecologi_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_pemeriksaan_usg_gynecologi WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data USG Gynecologi berhasil dihapus','files_deleted'=>$fd]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ========================================
// DOWNLOAD ORTHANC - USG GYNECOLOGI
// ========================================
if ($aksi === 'download_orthanc_images_gynecologi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_usg_gynecologi h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_gyn_base = defined('USG_GYNECOLOGI_BASE_URL') ? USG_GYNECOLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusggynecologi/';
        $_gyn_parsed = parse_url($_gyn_base); $_gyn_host = isset($_gyn_parsed['host']) ? $_gyn_parsed['host'] : 'localhost';
        $_gyn_is_local = in_array($_gyn_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_gyn_is_local) { $_gyn_path = isset($_gyn_parsed['path']) ? rtrim($_gyn_parsed['path'], '/') : '/webapps/hasilpemeriksaanusggynecologi'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_gyn_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/usggyn_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_gyn_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_gyn_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_usg_gynecologi_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// UPLOAD MANUAL - USG GYNECOLOGI
// ========================================
if ($aksi === 'upload_manual_usg_gynecologi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_usg_gynecologi WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_usg_gynecologi h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_gyn_base = defined('USG_GYNECOLOGI_BASE_URL') ? USG_GYNECOLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusggynecologi/';
        $_gyn_parsed = parse_url($_gyn_base); $_gyn_host = isset($_gyn_parsed['host']) ? $_gyn_parsed['host'] : 'localhost';
        $_gyn_is_local = in_array($_gyn_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_gyn_is_local) { $_gyn_path = isset($_gyn_parsed['path']) ? rtrim($_gyn_parsed['path'], '/') : '/webapps/hasilpemeriksaanusggynecologi'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_gyn_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/usggyn_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_usg_gynecologi_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_gyn_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_gyn_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[USG-GYN-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[USG-GYN-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_usg_gynecologi_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// HAPUS SATU GAMBAR - USG GYNECOLOGI
// ========================================
if ($aksi === 'hapus_gambar_usg_gynecologi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo    = isset($_POST['photo'])    ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_usg_gynecologi WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_gyn_base = defined('USG_GYNECOLOGI_BASE_URL') ? USG_GYNECOLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusggynecologi/';
        $_gyn_parsed = parse_url($_gyn_base); $_gyn_host = isset($_gyn_parsed['host']) ? $_gyn_parsed['host'] : 'localhost';
        $_gyn_is_local = in_array($_gyn_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_gyn_is_local) { $_gyn_path = isset($_gyn_parsed['path']) ? rtrim($_gyn_parsed['path'], '/') : '/webapps/hasilpemeriksaanusggynecologi'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_gyn_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_gyn_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_pemeriksaan_usg_gynecologi_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// SIMPAN PEMERIKSAAN USG UROLOGI
// ========================================
if ($aksi === 'simpan_usg_urologi') {
    
    error_log("=== USG UROLOGI HANDLER START === aksi: $aksi");
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $kiriman_dari = isset($_POST['kiriman_dari']) ? validTeks4($_POST['kiriman_dari'], 50) : '';
        $diagnosa_klinis = isset($_POST['diagnosa_klinis']) ? validTeks4($_POST['diagnosa_klinis'], 50) : '';
        $ginjal_kanan = isset($_POST['ginjal_kanan']) ? validTeks4($_POST['ginjal_kanan'], 200) : '';
        $ginjal_kiri = isset($_POST['ginjal_kiri']) ? validTeks4($_POST['ginjal_kiri'], 200) : '';
        $vesica_urinaria = isset($_POST['vesica_urinaria']) ? validTeks4($_POST['vesica_urinaria'], 200) : '';
        $tambahan = isset($_POST['tambahan']) ? validTeks4($_POST['tambahan'], 300) : '';
        
        $query_check = "SELECT no_rawat FROM hasil_pemeriksaan_usg_urologi WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $query = "UPDATE hasil_pemeriksaan_usg_urologi SET 
                        tanggal = '$tanggal',
                        kd_dokter = '$kd_dokter',
                        kiriman_dari = '$kiriman_dari',
                        diagnosa_klinis = '$diagnosa_klinis',
                        ginjal_kanan = '$ginjal_kanan',
                        ginjal_kiri = '$ginjal_kiri',
                        vesica_urinaria = '$vesica_urinaria',
                        tambahan = '$tambahan'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data USG Urologi');
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data USG Urologi berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            $query = "INSERT INTO hasil_pemeriksaan_usg_urologi (
                        no_rawat, tanggal, kd_dokter, kiriman_dari, diagnosa_klinis,
                        ginjal_kanan, ginjal_kiri, vesica_urinaria, tambahan
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$kiriman_dari', '$diagnosa_klinis',
                        '$ginjal_kanan', '$ginjal_kiri', '$vesica_urinaria', '$tambahan'
                      )";
            
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data USG Urologi');
            insertTracker($query);
            
            // AUTO DOWNLOAD IMAGES FROM ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_usg_urologi_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_uro_base = defined('USG_UROLOGI_BASE_URL') ? USG_UROLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgurologi/';
                                $_uro_parsed = parse_url($_uro_base); $_uro_host = isset($_uro_parsed['host']) ? $_uro_parsed['host'] : 'localhost';
                                $_uro_is_local = in_array($_uro_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_uro_is_local) { $_uro_path = isset($_uro_parsed['path']) ? rtrim($_uro_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgurologi'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_uro_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/usguro_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_uro_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_uro_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_pemeriksaan_usg_urologi_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode(['status'=>'success','message'=>'Data USG Urologi berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert','images_downloaded'=>$images_downloaded]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS PEMERIKSAAN USG UROLOGI
// ========================================
if ($aksi === 'hapus_usg_urologi') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        $query_cek = "SELECT no_rawat FROM hasil_pemeriksaan_usg_urologi 
                      WHERE no_rawat = '$no_rawat' AND kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }
        
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_usg_urologi_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_uro_base = defined('USG_UROLOGI_BASE_URL') ? USG_UROLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgurologi/';
            $_uro_parsed = parse_url($_uro_base); $_uro_host = isset($_uro_parsed['host']) ? $_uro_parsed['host'] : 'localhost';
            $_uro_is_local = in_array($_uro_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_uro_is_local) {
                    $_uro_path = isset($_uro_parsed['path']) ? rtrim($_uro_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgurologi';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_uro_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_uro_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_usg_urologi_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_pemeriksaan_usg_urologi WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data USG Urologi berhasil dihapus','files_deleted'=>$fd]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// DOWNLOAD ORTHANC - USG UROLOGI
// ========================================
if ($aksi === 'download_orthanc_images_urologi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_usg_urologi h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_uro_base = defined('USG_UROLOGI_BASE_URL') ? USG_UROLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgurologi/';
        $_uro_parsed = parse_url($_uro_base); $_uro_host = isset($_uro_parsed['host']) ? $_uro_parsed['host'] : 'localhost';
        $_uro_is_local = in_array($_uro_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_uro_is_local) { $_uro_path = isset($_uro_parsed['path']) ? rtrim($_uro_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgurologi'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_uro_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/usguro_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_uro_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_uro_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_usg_urologi_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// UPLOAD MANUAL - USG UROLOGI
// ========================================
if ($aksi === 'upload_manual_usg_urologi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_usg_urologi WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_usg_urologi h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_uro_base = defined('USG_UROLOGI_BASE_URL') ? USG_UROLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgurologi/';
        $_uro_parsed = parse_url($_uro_base); $_uro_host = isset($_uro_parsed['host']) ? $_uro_parsed['host'] : 'localhost';
        $_uro_is_local = in_array($_uro_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_uro_is_local) { $_uro_path = isset($_uro_parsed['path']) ? rtrim($_uro_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgurologi'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_uro_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/usguro_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_usg_urologi_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_uro_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_uro_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[USG-URO-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[USG-URO-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_usg_urologi_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// HAPUS SATU GAMBAR - USG UROLOGI
// ========================================
if ($aksi === 'hapus_gambar_usg_urologi') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo    = isset($_POST['photo'])    ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_usg_urologi WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_uro_base = defined('USG_UROLOGI_BASE_URL') ? USG_UROLOGI_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgurologi/';
        $_uro_parsed = parse_url($_uro_base); $_uro_host = isset($_uro_parsed['host']) ? $_uro_parsed['host'] : 'localhost';
        $_uro_is_local = in_array($_uro_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_uro_is_local) { $_uro_path = isset($_uro_parsed['path']) ? rtrim($_uro_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgurologi'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_uro_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_uro_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_pemeriksaan_usg_urologi_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// SIMPAN PEMERIKSAAN USG NEONATUS
// ========================================
if ($aksi === 'simpan_usg_neonatus') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $kiriman_dari = isset($_POST['kiriman_dari']) ? validTeks4($_POST['kiriman_dari'], 50) : '';
        $diagnosa_klinis = isset($_POST['diagnosa_klinis']) ? validTeks4($_POST['diagnosa_klinis'], 50) : '';
        $ventrikal_sinistra = isset($_POST['ventrikal_sinistra']) ? validTeks4($_POST['ventrikal_sinistra'], 200) : '';
        $ventrikal_dextra = isset($_POST['ventrikal_dextra']) ? validTeks4($_POST['ventrikal_dextra'], 200) : '';
        $kesan = isset($_POST['kesan']) ? validTeks4($_POST['kesan'], 200) : '';
        $kesimpulan = isset($_POST['kesimpulan']) ? validTeks4($_POST['kesimpulan'], 300) : '';
        $saran = isset($_POST['saran']) ? validTeks4($_POST['saran'], 200) : '';
        
        $query_check = "SELECT no_rawat FROM hasil_pemeriksaan_usg_neonatus WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $query = "UPDATE hasil_pemeriksaan_usg_neonatus SET 
                        tanggal = '$tanggal', kd_dokter = '$kd_dokter',
                        kiriman_dari = '$kiriman_dari', diagnosa_klinis = '$diagnosa_klinis',
                        ventrikal_sinistra = '$ventrikal_sinistra', ventrikal_dextra = '$ventrikal_dextra',
                        kesan = '$kesan', kesimpulan = '$kesimpulan', saran = '$saran'
                      WHERE no_rawat = '$no_rawat'";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data USG Neonatus');
            insertTracker($query);
            echo json_encode(['status'=>'success','message'=>'Data USG Neonatus berhasil diupdate','no_rawat'=>$no_rawat,'action'=>'update']);
            
        } else {
            $query = "INSERT INTO hasil_pemeriksaan_usg_neonatus (
                        no_rawat, tanggal, kd_dokter, kiriman_dari, diagnosa_klinis,
                        ventrikal_sinistra, ventrikal_dextra, kesan, kesimpulan, saran
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$kiriman_dari', '$diagnosa_klinis',
                        '$ventrikal_sinistra', '$ventrikal_dextra', '$kesan', '$kesimpulan', '$saran'
                      )";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data USG Neonatus');
            insertTracker($query);
            
            // AUTO DOWNLOAD IMAGES FROM ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_usg_neonatus_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_neo_base = defined('USG_NEONATUS_BASE_URL') ? USG_NEONATUS_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgneonatus/';
                                $_neo_parsed = parse_url($_neo_base); $_neo_host = isset($_neo_parsed['host']) ? $_neo_parsed['host'] : 'localhost';
                                $_neo_is_local = in_array($_neo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_neo_is_local) { $_neo_path = isset($_neo_parsed['path']) ? rtrim($_neo_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgneonatus'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_neo_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/usgneo_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_neo_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_neo_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_pemeriksaan_usg_neonatus_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode(['status'=>'success','message'=>'Data USG Neonatus berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert','images_downloaded'=>$images_downloaded]);
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS PEMERIKSAAN USG NEONATUS
// ========================================
if ($aksi === 'hapus_usg_neonatus') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_usg_neonatus WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_usg_neonatus_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_neo_base = defined('USG_NEONATUS_BASE_URL') ? USG_NEONATUS_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgneonatus/';
            $_neo_parsed = parse_url($_neo_base); $_neo_host = isset($_neo_parsed['host']) ? $_neo_parsed['host'] : 'localhost';
            $_neo_is_local = in_array($_neo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_neo_is_local) {
                    $_neo_path = isset($_neo_parsed['path']) ? rtrim($_neo_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgneonatus';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_neo_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_neo_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_usg_neonatus_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_pemeriksaan_usg_neonatus WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data USG Neonatus berhasil dihapus','files_deleted'=>$fd]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// DOWNLOAD ORTHANC - USG NEONATUS
// ========================================
if ($aksi === 'download_orthanc_images_neonatus') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_usg_neonatus h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_neo_base = defined('USG_NEONATUS_BASE_URL') ? USG_NEONATUS_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgneonatus/';
        $_neo_parsed = parse_url($_neo_base); $_neo_host = isset($_neo_parsed['host']) ? $_neo_parsed['host'] : 'localhost';
        $_neo_is_local = in_array($_neo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_neo_is_local) { $_neo_path = isset($_neo_parsed['path']) ? rtrim($_neo_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgneonatus'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_neo_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/usgneo_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_neo_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_neo_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_usg_neonatus_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// UPLOAD MANUAL - USG NEONATUS
// ========================================
if ($aksi === 'upload_manual_usg_neonatus') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_usg_neonatus WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_usg_neonatus h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_neo_base = defined('USG_NEONATUS_BASE_URL') ? USG_NEONATUS_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgneonatus/';
        $_neo_parsed = parse_url($_neo_base); $_neo_host = isset($_neo_parsed['host']) ? $_neo_parsed['host'] : 'localhost';
        $_neo_is_local = in_array($_neo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_neo_is_local) { $_neo_path = isset($_neo_parsed['path']) ? rtrim($_neo_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgneonatus'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_neo_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/usgneo_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_usg_neonatus_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_neo_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_neo_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[USG-NEO-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[USG-NEO-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_usg_neonatus_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// HAPUS SATU GAMBAR - USG NEONATUS
// ========================================
if ($aksi === 'hapus_gambar_usg_neonatus') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo    = isset($_POST['photo'])    ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_usg_neonatus WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_neo_base = defined('USG_NEONATUS_BASE_URL') ? USG_NEONATUS_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanusgneonatus/';
        $_neo_parsed = parse_url($_neo_base); $_neo_host = isset($_neo_parsed['host']) ? $_neo_parsed['host'] : 'localhost';
        $_neo_is_local = in_array($_neo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_neo_is_local) { $_neo_path = isset($_neo_parsed['path']) ? rtrim($_neo_parsed['path'], '/') : '/webapps/hasilpemeriksaanusgneonatus'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_neo_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_neo_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_pemeriksaan_usg_neonatus_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// SIMPAN ENDOSKOPI FARING LARING
// ========================================
if ($aksi === 'simpan_endoskopi_faring_laring') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $diagnosa_klinis = isset($_POST['diagnosa_klinis']) ? validTeks4($_POST['diagnosa_klinis'], 50) : '';
        $kiriman_dari = isset($_POST['kiriman_dari']) ? validTeks4($_POST['kiriman_dari'], 50) : '';
        $faring_uvula = isset($_POST['faring_uvula']) ? validTeks4($_POST['faring_uvula'], 50) : '';
        $faring_arkus_faring = isset($_POST['faring_arkus_faring']) ? validTeks4($_POST['faring_arkus_faring'], 50) : '';
        $faring_dinding_posterior = isset($_POST['faring_dinding_posterior']) ? validTeks4($_POST['faring_dinding_posterior'], 50) : '';
        $faring_tonsil = isset($_POST['faring_tonsil']) ? validTeks4($_POST['faring_tonsil'], 50) : '';
        $laring_tonsil_lingual = isset($_POST['laring_tonsil_lingual']) ? validTeks4($_POST['laring_tonsil_lingual'], 50) : '';
        $laring_valekula = isset($_POST['laring_valekula']) ? validTeks4($_POST['laring_valekula'], 50) : '';
        $laring_sinus_piriformis = isset($_POST['laring_sinus_piriformis']) ? validTeks4($_POST['laring_sinus_piriformis'], 50) : '';
        $laring_epiglotis = isset($_POST['laring_epiglotis']) ? validTeks4($_POST['laring_epiglotis'], 50) : '';
        $laring_arytenoid = isset($_POST['laring_arytenoid']) ? validTeks4($_POST['laring_arytenoid'], 50) : '';
        $laring_plika_ventrikularis = isset($_POST['laring_plika_ventrikularis']) ? validTeks4($_POST['laring_plika_ventrikularis'], 50) : '';
        $laring_pita_suara = isset($_POST['laring_pita_suara']) ? validTeks4($_POST['laring_pita_suara'], 50) : '';
        $laring_rima_vocalis = isset($_POST['laring_rima_vocalis']) ? validTeks4($_POST['laring_rima_vocalis'], 50) : '';
        $laring_lainlain = isset($_POST['laring_lainlain']) ? validTeks4($_POST['laring_lainlain'], 100) : '';
        $kesan = isset($_POST['kesan']) ? validTeks4($_POST['kesan'], 300) : '';
        $saran = isset($_POST['saran']) ? validTeks4($_POST['saran'], 300) : '';
        
        $query_check = "SELECT no_rawat FROM hasil_endoskopi_faring_laring WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $query = "UPDATE hasil_endoskopi_faring_laring SET 
                        tanggal='$tanggal', kd_dokter='$kd_dokter', diagnosa_klinis='$diagnosa_klinis',
                        kiriman_dari='$kiriman_dari', faring_uvula='$faring_uvula',
                        faring_arkus_faring='$faring_arkus_faring', faring_dinding_posterior='$faring_dinding_posterior',
                        faring_tonsil='$faring_tonsil', laring_tonsil_lingual='$laring_tonsil_lingual',
                        laring_valekula='$laring_valekula', laring_sinus_piriformis='$laring_sinus_piriformis',
                        laring_epiglotis='$laring_epiglotis', laring_arytenoid='$laring_arytenoid',
                        laring_plika_ventrikularis='$laring_plika_ventrikularis', laring_pita_suara='$laring_pita_suara',
                        laring_rima_vocalis='$laring_rima_vocalis', laring_lainlain='$laring_lainlain',
                        kesan='$kesan', saran='$saran'
                      WHERE no_rawat = '$no_rawat'";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data Endoskopi Faring Laring');
            insertTracker($query);
            echo json_encode(['status'=>'success','message'=>'Data Endoskopi Faring Laring berhasil diupdate','no_rawat'=>$no_rawat,'action'=>'update']);
        } else {
            $query = "INSERT INTO hasil_endoskopi_faring_laring (
                        no_rawat, tanggal, kd_dokter, diagnosa_klinis, kiriman_dari,
                        faring_uvula, faring_arkus_faring, faring_dinding_posterior, faring_tonsil,
                        laring_tonsil_lingual, laring_valekula, laring_sinus_piriformis, laring_epiglotis,
                        laring_arytenoid, laring_plika_ventrikularis, laring_pita_suara, laring_rima_vocalis,
                        laring_lainlain, kesan, saran
                      ) VALUES (
                        '$no_rawat','$tanggal','$kd_dokter','$diagnosa_klinis','$kiriman_dari',
                        '$faring_uvula','$faring_arkus_faring','$faring_dinding_posterior','$faring_tonsil',
                        '$laring_tonsil_lingual','$laring_valekula','$laring_sinus_piriformis','$laring_epiglotis',
                        '$laring_arytenoid','$laring_plika_ventrikularis','$laring_pita_suara','$laring_rima_vocalis',
                        '$laring_lainlain','$kesan','$saran'
                      )";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data Endoskopi Faring Laring');
            insertTracker($query);
            
            // AUTO DOWNLOAD ORTHANC
            $images_downloaded = 0; $images_source = 'none';
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_endoskopi_faring_laring_gambar WHERE no_rawat='$no_rawat'");
                $rci = mysqli_fetch_assoc($qci);
                if ($rci['total'] > 0) { $images_downloaded = $rci['total']; $images_source = 'database_existing'; }
                else {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $pt = mysqli_fetch_assoc($qp); $no_rkm_medis = $pt['no_rkm_medis'];
                        $study_date = date('Ymd', strtotime($tanggal));
                        $ou = defined('ORTHANC_URL') ? ORTHANC_URL : 'http://192.168.88.52';
                        $op = defined('ORTHANC_PORT') ? ORTHANC_PORT : '8042';
                        $oa = @fsockopen($ou, $op, $errno, $errstr, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig();
                            $thumbnails = $orthanc->getThumbnails($no_rkm_medis, $study_date, 20);
                        if (!empty($thumbnails)) {
                            $_efl_base = defined('ENDOSKOPI_FARING_LARING_BASE_URL') ? ENDOSKOPI_FARING_LARING_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopifaringlaring/';
                            $_efl_parsed = parse_url($_efl_base); $_efl_host = isset($_efl_parsed['host']) ? $_efl_parsed['host'] : 'localhost';
                            $_efl_is_local = in_array($_efl_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                            if ($_efl_is_local) { $_efl_path = isset($_efl_parsed['path']) ? rtrim($_efl_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopifaringlaring'; $upload_dir = $_SERVER['DOCUMENT_ROOT'] . $_efl_path . '/pages/upload/'; }
                            else { $upload_dir = sys_get_temp_dir() . '/endofl_upload/'; }
                            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                            foreach ($thumbnails as $idx => $thumb) {
                                $fn = $no_rkm_medis.'_'.$study_date.'_'.$images_downloaded.'.jpeg';
                                if (!isset($thumb['base64'])) continue;
                                $img = base64_decode($thumb['base64']);
                                if ($img !== false) {
                                    $bw = file_put_contents($upload_dir.$fn, $img);
                                    if ($bw === false) continue;
                                    $pp = 'pages/upload/'.$fn;
                                    if (!$_efl_is_local) {
                                        $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_efl_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($upload_dir.$fn, 'image/jpeg', $fn)]]);
                                        $cr = curl_exec($ch); curl_close($ch); if (file_exists($upload_dir.$fn)) unlink($upload_dir.$fn);
                                        $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                    }
                                    $qi = "INSERT INTO hasil_endoskopi_faring_laring_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')";
                                    bukaquery($qi); insertTracker($qi); $images_downloaded++;
                                }
                            }
                            $images_source = 'orthanc_downloaded';
                        } else { $images_source = 'orthanc_not_found'; }
                        } else { $images_source = 'orthanc_unavailable'; }
                    }
                }
            } catch (Exception $e) { error_log('ENDO-FL Orthanc error: '.$e->getMessage()); $images_source = 'error'; }
            echo json_encode(['status'=>'success','message'=>'Data Endoskopi Faring Laring berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert','images_downloaded'=>$images_downloaded,'images_source'=>$images_source]);
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS ENDOSKOPI FARING LARING
// ========================================
if ($aksi === 'hapus_endoskopi_faring_laring') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_faring_laring WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_endoskopi_faring_laring_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_efl_base = defined('ENDOSKOPI_FARING_LARING_BASE_URL') ? ENDOSKOPI_FARING_LARING_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopifaringlaring/';
            $_efl_parsed = parse_url($_efl_base); $_efl_host = isset($_efl_parsed['host']) ? $_efl_parsed['host'] : 'localhost';
            $_efl_is_local = in_array($_efl_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_efl_is_local) {
                    $_efl_path = isset($_efl_parsed['path']) ? rtrim($_efl_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopifaringlaring';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_efl_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_efl_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            $qdi = "DELETE FROM hasil_endoskopi_faring_laring_gambar WHERE no_rawat='$no_rawat'";
            bukaquery($qdi); insertTracker($qdi);
        }
        $qd = "DELETE FROM hasil_endoskopi_faring_laring WHERE no_rawat='$no_rawat'";
        $result = bukaquery($qd);
        if (!$result) throw new Exception('Gagal menghapus data Endoskopi Faring Laring');
        insertTracker($qd);
        echo json_encode(['status'=>'success','message'=>'Data Endoskopi Faring Laring berhasil dihapus','no_rawat'=>$no_rawat,'files_deleted'=>$fd]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// UPLOAD MANUAL GAMBAR - ENDOSKOPI FL
// ========================================
if ($aksi === 'upload_manual_endoskopi_fl') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        
        // Cek data pemeriksaan harus sudah ada
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_faring_laring WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu sebelum upload gambar');
        
        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
            throw new Exception('Tidak ada file yang diupload');
        }
        
        // Get no_rkm_medis for filename
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_endoskopi_faring_laring h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp);
        $no_rkm_medis = $pt['no_rkm_medis'];
        $study_date = date('Ymd', strtotime($pt['tanggal']));
        
        // Detect lokal vs remote dari ENDOSKOPI_FARING_LARING_BASE_URL
        $endofl_base = defined('ENDOSKOPI_FARING_LARING_BASE_URL') ? ENDOSKOPI_FARING_LARING_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopifaringlaring/';
        $endofl_parsed = parse_url($endofl_base);
        $endofl_host = isset($endofl_parsed['host']) ? $endofl_parsed['host'] : 'localhost';
        $endofl_is_local = in_array($endofl_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        
        if ($endofl_is_local) {
            // MODE LOKAL: simpan langsung ke filesystem
            $endofl_path = isset($endofl_parsed['path']) ? rtrim($endofl_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopifaringlaring';
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . $endofl_path . '/pages/upload/';
        } else {
            // MODE REMOTE: simpan ke tmp dulu, lalu upload via cURL
            $upload_dir = sys_get_temp_dir() . '/endofl_upload/';
        }
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $uploaded = 0;
        $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        
        // Get next index
        $qc = bukaquery("SELECT COUNT(*) as total FROM hasil_endoskopi_faring_laring_gambar WHERE no_rawat='$no_rawat'");
        $idx = mysqli_fetch_assoc($qc)['total'];
        
        foreach ($_FILES['images']['name'] as $i => $name) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($_FILES['images']['type'][$i], $allowed)) continue;
            if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) continue;
            
            $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'jpeg';
            $fn = $no_rkm_medis . '_' . $study_date . '_manual_' . ($idx + $uploaded) . '.' . $ext;
            $fp = $upload_dir . $fn;
            
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $fp)) {
                $pp = 'pages/upload/' . $fn;
                
                if (!$endofl_is_local) {
                    // Upload via cURL ke server remote
                    $upload_url = rtrim($endofl_base, '/') . '/receive_upload.php';
                    $secret = defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '';
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $upload_url,
                        CURLOPT_POST => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_POSTFIELDS => [
                            'secret' => $secret,
                            'dest_path' => $pp,
                            'file' => new CURLFile($fp, $_FILES['images']['type'][$i], $fn)
                        ]
                    ]);
                    $curl_response = curl_exec($ch);
                    $curl_error = curl_error($ch);
                    curl_close($ch);
                    
                    // Hapus file tmp
                    if (file_exists($fp)) unlink($fp);
                    
                    if ($curl_error) {
                        error_log("[ENDO-FL-UPLOAD] cURL error: $curl_error");
                        continue;
                    }
                    $curl_result = json_decode($curl_response, true);
                    if (!isset($curl_result['status']) || $curl_result['status'] !== 'success') {
                        error_log("[ENDO-FL-UPLOAD] Remote upload gagal: $curl_response");
                        continue;
                    }
                }
                
                $qi = "INSERT INTO hasil_endoskopi_faring_laring_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')";
                bukaquery($qi); insertTracker($qi);
                $uploaded++;
            }
        }
        
        if ($uploaded === 0) throw new Exception('Tidak ada gambar yang berhasil diupload');
        
        echo json_encode(['status'=>'success','message'=>$uploaded.' gambar berhasil diupload','images_uploaded'=>$uploaded,'no_rawat'=>$no_rawat]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS SATU GAMBAR - ENDOSKOPI FL
// ========================================
if ($aksi === 'hapus_gambar_endoskopi_fl') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo = isset($_POST['photo']) ? $_POST['photo'] : '';
        if (empty($no_rawat) || empty($photo)) throw new Exception('Parameter tidak valid');
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_faring_laring WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        
        // Detect lokal vs remote dari ENDOSKOPI_FARING_LARING_BASE_URL
        $endofl_base = defined('ENDOSKOPI_FARING_LARING_BASE_URL') ? ENDOSKOPI_FARING_LARING_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopifaringlaring/';
        $endofl_parsed = parse_url($endofl_base);
        $endofl_host = isset($endofl_parsed['host']) ? $endofl_parsed['host'] : 'localhost';
        $endofl_is_local = in_array($endofl_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        
        if ($endofl_is_local) {
            $endofl_path = isset($endofl_parsed['path']) ? rtrim($endofl_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopifaringlaring';
            $fp = $_SERVER['DOCUMENT_ROOT'] . $endofl_path . '/' . $photo;
            if (file_exists($fp)) unlink($fp);
        } else {
            // Hapus via cURL ke server remote
            $delete_url = rtrim($endofl_base, '/') . '/receive_upload.php';
            $secret = defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '';
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $delete_url,
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_POSTFIELDS => [
                    'secret' => $secret,
                    'action' => 'delete',
                    'dest_path' => $photo
                ]
            ]);
            $curl_response = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
            if ($curl_error) error_log("[ENDO-FL-DELETE] cURL error: $curl_error");
        }
        
        $photo_safe = addslashes($photo);
        $qd = "DELETE FROM hasil_endoskopi_faring_laring_gambar WHERE no_rawat='$no_rawat' AND photo='$photo_safe'";
        bukaquery($qd); insertTracker($qd);
        
        echo json_encode(['status'=>'success','message'=>'Gambar berhasil dihapus']);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// DOWNLOAD GAMBAR ORTHANC - ENDOSKOPI FL
// ========================================
if ($aksi === 'download_orthanc_images_endoskopi_fl') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices']) ? $_POST['selected_indices'] : '[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar yang dipilih');
        
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_endoskopi_faring_laring h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp || mysqli_num_rows($rp) === 0) throw new Exception('Data pemeriksaan tidak ditemukan');
        $pt = mysqli_fetch_assoc($rp);
        $no_rkm_medis = $pt['no_rkm_medis']; $sd = date('Ymd', strtotime($pt['tanggal']));
        
        $orthanc = ApiOrthanc::fromConfig();
        $thumbnails = $orthanc->getThumbnails($no_rkm_medis, $sd, 20);
        if (empty($thumbnails)) throw new Exception('Tidak ada gambar di Orthanc');
        
        // Detect lokal vs remote dari ENDOSKOPI_FARING_LARING_BASE_URL
        $endofl_base = defined('ENDOSKOPI_FARING_LARING_BASE_URL') ? ENDOSKOPI_FARING_LARING_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopifaringlaring/';
        $endofl_parsed = parse_url($endofl_base);
        $endofl_host = isset($endofl_parsed['host']) ? $endofl_parsed['host'] : 'localhost';
        $endofl_is_local = in_array($endofl_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        
        if ($endofl_is_local) {
            $endofl_path = isset($endofl_parsed['path']) ? rtrim($endofl_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopifaringlaring';
            $ud = $_SERVER['DOCUMENT_ROOT'] . $endofl_path . '/pages/upload/';
        } else {
            $ud = sys_get_temp_dir() . '/endofl_upload/';
        }
        if (!is_dir($ud)) mkdir($ud, 0755, true);
        $dl = 0;
        
        foreach ($thumbnails as $index => $thumb) {
            if (!in_array($index, $si)) continue;
            $fn = $no_rkm_medis.'_'.$sd.'_'.$index.'.jpeg';
            $img = base64_decode($thumb['base64']);
            if ($img !== false) {
                $bw = file_put_contents($ud.$fn, $img);
                if ($bw !== false) {
                    $pp = 'pages/upload/'.$fn;
                    
                    if (!$endofl_is_local) {
                        // Upload via cURL ke server remote
                        $upload_url = rtrim($endofl_base, '/') . '/receive_upload.php';
                        $secret = defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '';
                        $ch = curl_init();
                        curl_setopt_array($ch, [
                            CURLOPT_URL => $upload_url,
                            CURLOPT_POST => true,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_POSTFIELDS => [
                                'secret' => $secret,
                                'dest_path' => $pp,
                                'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)
                            ]
                        ]);
                        $curl_response = curl_exec($ch);
                        $curl_error = curl_error($ch);
                        curl_close($ch);
                        if (file_exists($ud.$fn)) unlink($ud.$fn);
                        if ($curl_error) { error_log("[ENDO-FL-ORTHANC] cURL error: $curl_error"); continue; }
                        $curl_result = json_decode($curl_response, true);
                        if (!isset($curl_result['status']) || $curl_result['status'] !== 'success') { error_log("[ENDO-FL-ORTHANC] Remote upload gagal: $curl_response"); continue; }
                    }
                    
                    $qi = "INSERT INTO hasil_endoskopi_faring_laring_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')";
                    bukaquery($qi); insertTracker($qi); $dl++;
                }
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar berhasil disimpan','images_downloaded'=>$dl,'no_rawat'=>$no_rawat]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN ENDOSKOPI HIDUNG
// ========================================
if ($aksi === 'simpan_endoskopi_hidung') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $diagnosa_klinis = isset($_POST['diagnosa_klinis']) ? validTeks4($_POST['diagnosa_klinis'], 50) : '';
        $kiriman_dari = isset($_POST['kiriman_dari']) ? validTeks4($_POST['kiriman_dari'], 50) : '';
        $kondisi_hidung_kanan = isset($_POST['kondisi_hidung_kanan']) ? validTeks4($_POST['kondisi_hidung_kanan'], 50) : '';
        $kondisi_hidung_kiri = isset($_POST['kondisi_hidung_kiri']) ? validTeks4($_POST['kondisi_hidung_kiri'], 50) : '';
        $kavum_nasi_kanan = isset($_POST['kavum_nasi_kanan']) ? validTeks4($_POST['kavum_nasi_kanan'], 50) : '';
        $kavum_nasi_kiri = isset($_POST['kavum_nasi_kiri']) ? validTeks4($_POST['kavum_nasi_kiri'], 50) : '';
        $konka_inferior_kanan = isset($_POST['konka_inferior_kanan']) ? validTeks4($_POST['konka_inferior_kanan'], 50) : '';
        $konka_inferior_kiri = isset($_POST['konka_inferior_kiri']) ? validTeks4($_POST['konka_inferior_kiri'], 50) : '';
        $meatus_medius_kanan = isset($_POST['meatus_medius_kanan']) ? validTeks4($_POST['meatus_medius_kanan'], 50) : '';
        $meatus_medius_kiri = isset($_POST['meatus_medius_kiri']) ? validTeks4($_POST['meatus_medius_kiri'], 50) : '';
        $septum_kanan = isset($_POST['septum_kanan']) ? validTeks4($_POST['septum_kanan'], 50) : '';
        $septum_kiri = isset($_POST['septum_kiri']) ? validTeks4($_POST['septum_kiri'], 50) : '';
        $nasofaring_kanan = isset($_POST['nasofaring_kanan']) ? validTeks4($_POST['nasofaring_kanan'], 50) : '';
        $nasofaring_kiri = isset($_POST['nasofaring_kiri']) ? validTeks4($_POST['nasofaring_kiri'], 50) : '';
        $lainlain_kanan = isset($_POST['lainlain_kanan']) ? validTeks4($_POST['lainlain_kanan'], 100) : '';
        $lainlain_kiri = isset($_POST['lainlain_kiri']) ? validTeks4($_POST['lainlain_kiri'], 100) : '';
        $kesimpulan = isset($_POST['kesimpulan']) ? validTeks4($_POST['kesimpulan'], 300) : '';
        
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_hidung WHERE no_rawat='$no_rawat'");
        
        if (mysqli_num_rows($rc) > 0) {
            $query = "UPDATE hasil_endoskopi_hidung SET 
                tanggal='$tanggal', kd_dokter='$kd_dokter', diagnosa_klinis='$diagnosa_klinis', kiriman_dari='$kiriman_dari',
                kondisi_hidung_kanan='$kondisi_hidung_kanan', kondisi_hidung_kiri='$kondisi_hidung_kiri',
                kavum_nasi_kanan='$kavum_nasi_kanan', kavum_nasi_kiri='$kavum_nasi_kiri',
                konka_inferior_kanan='$konka_inferior_kanan', konka_inferior_kiri='$konka_inferior_kiri',
                meatus_medius_kanan='$meatus_medius_kanan', meatus_medius_kiri='$meatus_medius_kiri',
                septum_kanan='$septum_kanan', septum_kiri='$septum_kiri',
                nasofaring_kanan='$nasofaring_kanan', nasofaring_kiri='$nasofaring_kiri',
                lainlain_kanan='$lainlain_kanan', lainlain_kiri='$lainlain_kiri',
                kesimpulan='$kesimpulan'
              WHERE no_rawat='$no_rawat'";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data');
            insertTracker($query);
            echo json_encode(['status'=>'success','message'=>'Data Endoskopi Hidung berhasil diupdate','action'=>'update']);
        } else {
            $query = "INSERT INTO hasil_endoskopi_hidung (
                no_rawat, tanggal, kd_dokter, diagnosa_klinis, kiriman_dari,
                kondisi_hidung_kanan, kondisi_hidung_kiri, kavum_nasi_kanan, kavum_nasi_kiri,
                konka_inferior_kanan, konka_inferior_kiri, meatus_medius_kanan, meatus_medius_kiri,
                septum_kanan, septum_kiri, nasofaring_kanan, nasofaring_kiri,
                lainlain_kanan, lainlain_kiri, kesimpulan
              ) VALUES (
                '$no_rawat','$tanggal','$kd_dokter','$diagnosa_klinis','$kiriman_dari',
                '$kondisi_hidung_kanan','$kondisi_hidung_kiri','$kavum_nasi_kanan','$kavum_nasi_kiri',
                '$konka_inferior_kanan','$konka_inferior_kiri','$meatus_medius_kanan','$meatus_medius_kiri',
                '$septum_kanan','$septum_kiri','$nasofaring_kanan','$nasofaring_kiri',
                '$lainlain_kanan','$lainlain_kiri','$kesimpulan'
              )";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data');
            insertTracker($query);
            
            // AUTO DOWNLOAD ORTHANC
            $images_downloaded = 0; $images_source = 'none';
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_endoskopi_hidung_gambar WHERE no_rawat='$no_rawat'");
                $rci = mysqli_fetch_assoc($qci);
                if ($rci['total'] > 0) { $images_downloaded = $rci['total']; $images_source = 'database_existing'; }
                else {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $pt = mysqli_fetch_assoc($qp); $norm = $pt['no_rkm_medis'];
                        $sd = date('Ymd', strtotime($tanggal));
                        $ou = defined('ORTHANC_URL') ? ORTHANC_URL : 'http://192.168.88.52';
                        $op = defined('ORTHANC_PORT') ? ORTHANC_PORT : '8042';
                        $oa = @fsockopen($ou, $op, $errno, $errstr, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig();
                            $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                        if (!empty($thumbs)) {
                            // Detect lokal vs remote dari ENDOSKOPI_HIDUNG_BASE_URL
                            $_eh_base = defined('ENDOSKOPI_HIDUNG_BASE_URL') ? ENDOSKOPI_HIDUNG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopihidung/';
                            $_eh_parsed = parse_url($_eh_base);
                            $_eh_host = isset($_eh_parsed['host']) ? $_eh_parsed['host'] : 'localhost';
                            $_eh_is_local = in_array($_eh_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                            if ($_eh_is_local) {
                                $_eh_path = isset($_eh_parsed['path']) ? rtrim($_eh_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopihidung';
                                $ud = $_SERVER['DOCUMENT_ROOT'] . $_eh_path . '/pages/upload/';
                            } else {
                                $ud = sys_get_temp_dir() . '/endoh_upload/';
                            }
                            if (!is_dir($ud)) mkdir($ud, 0755, true);
                            foreach ($thumbs as $thumb) {
                                $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                if (!isset($thumb['base64'])) continue;
                                $img = base64_decode($thumb['base64']);
                                if ($img !== false && file_put_contents($ud.$fn, $img) !== false) {
                                    $pp = 'pages/upload/'.$fn;
                                    if (!$_eh_is_local) {
                                        $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_eh_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                        $cr = curl_exec($ch); curl_close($ch);
                                        if (file_exists($ud.$fn)) unlink($ud.$fn);
                                        $cj = json_decode($cr, true);
                                        if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                    }
                                    $qi = "INSERT INTO hasil_endoskopi_hidung_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')";
                                    bukaquery($qi); insertTracker($qi); $images_downloaded++;
                                }
                            }
                            $images_source = 'orthanc_downloaded';
                        } else { $images_source = 'orthanc_not_found'; }
                        } else { $images_source = 'orthanc_unavailable'; }
                    }
                }
            } catch (Exception $e) { $images_source = 'error'; }
            echo json_encode(['status'=>'success','message'=>'Data Endoskopi Hidung berhasil disimpan','action'=>'insert','images_downloaded'=>$images_downloaded]);
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS ENDOSKOPI HIDUNG
// ========================================
if ($aksi === 'hapus_endoskopi_hidung') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_hidung WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_endoskopi_hidung_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            // Detect lokal vs remote dari ENDOSKOPI_HIDUNG_BASE_URL
            $_eh_base = defined('ENDOSKOPI_HIDUNG_BASE_URL') ? ENDOSKOPI_HIDUNG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopihidung/';
            $_eh_parsed = parse_url($_eh_base);
            $_eh_host = isset($_eh_parsed['host']) ? $_eh_parsed['host'] : 'localhost';
            $_eh_is_local = in_array($_eh_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_eh_is_local) {
                    $_eh_path = isset($_eh_parsed['path']) ? rtrim($_eh_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopihidung';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_eh_path . '/' . $row['photo'];
                    if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_eh_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]);
                    curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_endoskopi_hidung_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_endoskopi_hidung WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data Endoskopi Hidung berhasil dihapus','files_deleted'=>$fd]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// UPLOAD MANUAL - ENDOSKOPI HIDUNG
// ========================================
if ($aksi === 'upload_manual_endoskopi_h') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_hidung WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_endoskopi_hidung h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm = $pt['no_rkm_medis']; $sd = date('Ymd', strtotime($pt['tanggal']));
        
        // Detect lokal vs remote dari ENDOSKOPI_HIDUNG_BASE_URL
        $_eh_base = defined('ENDOSKOPI_HIDUNG_BASE_URL') ? ENDOSKOPI_HIDUNG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopihidung/';
        $_eh_parsed = parse_url($_eh_base);
        $_eh_host = isset($_eh_parsed['host']) ? $_eh_parsed['host'] : 'localhost';
        $_eh_is_local = in_array($_eh_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_eh_is_local) {
            $_eh_path = isset($_eh_parsed['path']) ? rtrim($_eh_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopihidung';
            $ud = $_SERVER['DOCUMENT_ROOT'] . $_eh_path . '/pages/upload/';
        } else {
            $ud = sys_get_temp_dir() . '/endoh_upload/';
        }
        if (!is_dir($ud)) mkdir($ud, 0755, true);
        $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $qc = bukaquery("SELECT COUNT(*) as total FROM hasil_endoskopi_hidung_gambar WHERE no_rawat='$no_rawat'");
        $idx = mysqli_fetch_assoc($qc)['total']; $uploaded = 0;
        foreach ($_FILES['images']['name'] as $i => $name) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($_FILES['images']['type'][$i], $allowed)) continue;
            if ($_FILES['images']['size'][$i] > 5*1024*1024) continue;
            $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'jpeg';
            $fn = $norm.'_'.$sd.'_manual_'.($idx+$uploaded).'.'.$ext;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $ud.$fn)) {
                $pp = 'pages/upload/'.$fn;
                if (!$_eh_is_local) {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_eh_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, $_FILES['images']['type'][$i], $fn)]]);
                    $cr = curl_exec($ch); $ce = curl_error($ch); curl_close($ch);
                    if (file_exists($ud.$fn)) unlink($ud.$fn);
                    if ($ce) { error_log("[ENDO-H-UPLOAD] cURL: $ce"); continue; }
                    $cj = json_decode($cr, true);
                    if (!isset($cj['status']) || $cj['status'] !== 'success') { error_log("[ENDO-H-UPLOAD] Remote gagal: $cr"); continue; }
                }
                bukaquery("INSERT INTO hasil_endoskopi_hidung_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");
                $uploaded++;
            }
        }
        if ($uploaded === 0) throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$uploaded.' gambar diupload','images_uploaded'=>$uploaded]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS SATU GAMBAR - ENDOSKOPI HIDUNG
// ========================================
if ($aksi === 'hapus_gambar_endoskopi_h') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo = isset($_POST['photo']) ? $_POST['photo'] : '';
        if (empty($no_rawat) || empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_hidung WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        
        // Detect lokal vs remote dari ENDOSKOPI_HIDUNG_BASE_URL
        $_eh_base = defined('ENDOSKOPI_HIDUNG_BASE_URL') ? ENDOSKOPI_HIDUNG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopihidung/';
        $_eh_parsed = parse_url($_eh_base);
        $_eh_host = isset($_eh_parsed['host']) ? $_eh_parsed['host'] : 'localhost';
        $_eh_is_local = in_array($_eh_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_eh_is_local) {
            $_eh_path = isset($_eh_parsed['path']) ? rtrim($_eh_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopihidung';
            $fp = $_SERVER['DOCUMENT_ROOT'] . $_eh_path . '/' . $photo;
            if (file_exists($fp)) unlink($fp);
        } else {
            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_eh_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $photo]]);
            curl_exec($ch); curl_close($ch);
        }
        bukaquery("DELETE FROM hasil_endoskopi_hidung_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// DOWNLOAD ORTHANC - ENDOSKOPI HIDUNG
// ========================================
if ($aksi === 'download_orthanc_images_endoskopi_h') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices']) ? $_POST['selected_indices'] : '[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_endoskopi_hidung h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp || mysqli_num_rows($rp) === 0) throw new Exception('Data tidak ditemukan');
        $pt = mysqli_fetch_assoc($rp); $norm = $pt['no_rkm_medis']; $sd = date('Ymd', strtotime($pt['tanggal']));
        $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
        if (empty($thumbs)) throw new Exception('Tidak ada gambar di Orthanc');
        
        // Detect lokal vs remote dari ENDOSKOPI_HIDUNG_BASE_URL
        $_eh_base = defined('ENDOSKOPI_HIDUNG_BASE_URL') ? ENDOSKOPI_HIDUNG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopihidung/';
        $_eh_parsed = parse_url($_eh_base);
        $_eh_host = isset($_eh_parsed['host']) ? $_eh_parsed['host'] : 'localhost';
        $_eh_is_local = in_array($_eh_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_eh_is_local) {
            $_eh_path = isset($_eh_parsed['path']) ? rtrim($_eh_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopihidung';
            $ud = $_SERVER['DOCUMENT_ROOT'] . $_eh_path . '/pages/upload/';
        } else {
            $ud = sys_get_temp_dir() . '/endoh_upload/';
        }
        if (!is_dir($ud)) mkdir($ud, 0755, true); $dl = 0;
        foreach ($thumbs as $index => $thumb) {
            if (!in_array($index, $si)) continue;
            $fn = $norm.'_'.$sd.'_'.$index.'.jpeg'; $img = base64_decode($thumb['base64']);
            if ($img !== false && file_put_contents($ud.$fn, $img) !== false) {
                $pp = 'pages/upload/'.$fn;
                if (!$_eh_is_local) {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_eh_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                    $cr = curl_exec($ch); curl_close($ch);
                    if (file_exists($ud.$fn)) unlink($ud.$fn);
                    $cj = json_decode($cr, true);
                    if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                }
                bukaquery("INSERT INTO hasil_endoskopi_hidung_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN ENDOSKOPI TELINGA
// ========================================
if ($aksi === 'simpan_endoskopi_telinga') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $f = []; // collect all field values
        $text_fields_50 = ['diagnosa_klinis','kiriman_dari','bentuk_liang_telinga_kanan','bentuk_liang_telinga_kiri',
            'kondisi_liang_telinga_kanan','kondisi_liang_telinga_kiri',
            'membran_timpani_intak_kanan','membran_timpani_intak_kiri',
            'membran_timpani_perforasi_kanan','membran_timpani_perforasi_kiri'];
        $text_fields_30 = ['keterangan_kondisi_liang_telinga_kanan','keterangan_kondisi_liang_telinga_kiri',
            'keterangan_membran_timpani_perforasi_kanan','keterangan_membran_timpani_perforasi_kiri'];
        $text_fields_40 = ['kavum_timpani_mukosa_kanan','kavum_timpani_mukosa_kiri',
            'kavum_timpani_osikel_kanan','kavum_timpani_osikel_kiri',
            'kavum_timpani_isthmus_kanan','kavum_timpani_isthmus_kiri',
            'kavum_timpani_anterior_kanan','kavum_timpani_anterior_kiri',
            'kavum_timpani_posterior_kanan','kavum_timpani_posterior_kiri'];
        $text_fields_100 = ['lainlain_kanan','lainlain_kiri'];
        $text_fields_300 = ['kesimpulan','anjuran'];
        
        foreach ($text_fields_50 as $k) $f[$k] = isset($_POST[$k]) ? validTeks4($_POST[$k], 50) : '';
        foreach ($text_fields_30 as $k) $f[$k] = isset($_POST[$k]) ? validTeks4($_POST[$k], 30) : '';
        foreach ($text_fields_40 as $k) $f[$k] = isset($_POST[$k]) ? validTeks4($_POST[$k], 40) : '';
        foreach ($text_fields_100 as $k) $f[$k] = isset($_POST[$k]) ? validTeks4($_POST[$k], 100) : '';
        foreach ($text_fields_300 as $k) $f[$k] = isset($_POST[$k]) ? validTeks4($_POST[$k], 300) : '';
        
        $all_fields = array_merge($text_fields_50, $text_fields_30, $text_fields_40, $text_fields_100, $text_fields_300);
        
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_telinga WHERE no_rawat='$no_rawat'");
        
        if (mysqli_num_rows($rc) > 0) {
            $sets = "tanggal='$tanggal', kd_dokter='$kd_dokter'";
            foreach ($all_fields as $k) $sets .= ", $k='".$f[$k]."'";
            $query = "UPDATE hasil_endoskopi_telinga SET $sets WHERE no_rawat='$no_rawat'";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data');
            insertTracker($query);
            echo json_encode(['status'=>'success','message'=>'Data Endoskopi Telinga berhasil diupdate','action'=>'update']);
        } else {
            $cols = "no_rawat, tanggal, kd_dokter, " . implode(', ', $all_fields);
            $vals = "'$no_rawat','$tanggal','$kd_dokter'";
            foreach ($all_fields as $k) $vals .= ",'".$f[$k]."'";
            $query = "INSERT INTO hasil_endoskopi_telinga ($cols) VALUES ($vals)";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data');
            insertTracker($query);
            
            // AUTO DOWNLOAD ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_endoskopi_telinga_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_et_base = defined('ENDOSKOPI_TELINGA_BASE_URL') ? ENDOSKOPI_TELINGA_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopitelinga/';
                                $_et_parsed = parse_url($_et_base); $_et_host = isset($_et_parsed['host']) ? $_et_parsed['host'] : 'localhost';
                                $_et_is_local = in_array($_et_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_et_is_local) { $_et_path = isset($_et_parsed['path']) ? rtrim($_et_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopitelinga'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_et_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/endot_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_et_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_et_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_endoskopi_telinga_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode(['status'=>'success','message'=>'Data Endoskopi Telinga berhasil disimpan','action'=>'insert','images_downloaded'=>$images_downloaded]);
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS ENDOSKOPI TELINGA
// ========================================
if ($aksi === 'hapus_endoskopi_telinga') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_telinga WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_endoskopi_telinga_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_et_base = defined('ENDOSKOPI_TELINGA_BASE_URL') ? ENDOSKOPI_TELINGA_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopitelinga/';
            $_et_parsed = parse_url($_et_base); $_et_host = isset($_et_parsed['host']) ? $_et_parsed['host'] : 'localhost';
            $_et_is_local = in_array($_et_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_et_is_local) {
                    $_et_path = isset($_et_parsed['path']) ? rtrim($_et_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopitelinga';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_et_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_et_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_endoskopi_telinga_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_endoskopi_telinga WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data berhasil dihapus','files_deleted'=>$fd]);
    } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
    exit();
}

// ========================================
// UPLOAD MANUAL - ENDOSKOPI TELINGA
// ========================================
if ($aksi === 'upload_manual_endoskopi_t') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_telinga WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_endoskopi_telinga h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_et_base = defined('ENDOSKOPI_TELINGA_BASE_URL') ? ENDOSKOPI_TELINGA_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopitelinga/';
        $_et_parsed = parse_url($_et_base); $_et_host = isset($_et_parsed['host']) ? $_et_parsed['host'] : 'localhost';
        $_et_is_local = in_array($_et_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_et_is_local) { $_et_path = isset($_et_parsed['path']) ? rtrim($_et_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopitelinga'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_et_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/endot_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_endoskopi_telinga_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_et_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_et_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[ENDO-T-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[ENDO-T-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_endoskopi_telinga_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// HAPUS SATU GAMBAR - ENDOSKOPI TELINGA
// ========================================
if ($aksi === 'hapus_gambar_endoskopi_t') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo = isset($_POST['photo']) ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_endoskopi_telinga WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_et_base = defined('ENDOSKOPI_TELINGA_BASE_URL') ? ENDOSKOPI_TELINGA_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopitelinga/';
        $_et_parsed = parse_url($_et_base); $_et_host = isset($_et_parsed['host']) ? $_et_parsed['host'] : 'localhost';
        $_et_is_local = in_array($_et_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_et_is_local) { $_et_path = isset($_et_parsed['path']) ? rtrim($_et_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopitelinga'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_et_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_et_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_endoskopi_telinga_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// DOWNLOAD ORTHANC - ENDOSKOPI TELINGA
// ========================================
if ($aksi === 'download_orthanc_images_endoskopi_t') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_endoskopi_telinga h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_et_base = defined('ENDOSKOPI_TELINGA_BASE_URL') ? ENDOSKOPI_TELINGA_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanendoskopitelinga/';
        $_et_parsed = parse_url($_et_base); $_et_host = isset($_et_parsed['host']) ? $_et_parsed['host'] : 'localhost';
        $_et_is_local = in_array($_et_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_et_is_local) { $_et_path = isset($_et_parsed['path']) ? rtrim($_et_parsed['path'], '/') : '/webapps/hasilpemeriksaanendoskopitelinga'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_et_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/endot_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_et_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_et_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_endoskopi_telinga_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// SIMPAN PEMERIKSAAN EKG
// ========================================
if ($aksi === 'simpan_ekg') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Get form data
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        $diagnosa_klinis = isset($_POST['diagnosa_klinis']) ? validTeks4($_POST['diagnosa_klinis'], 500) : '';
        $kiriman_dari = isset($_POST['kiriman_dari']) ? validTeks4($_POST['kiriman_dari'], 50) : '';
        $irama = isset($_POST['irama']) ? validTeks4($_POST['irama'], 40) : '';
        $laju_jantung = isset($_POST['laju_jantung']) ? validTeks4($_POST['laju_jantung'], 40) : '';
        $gelombangp = isset($_POST['gelombangp']) ? validTeks4($_POST['gelombangp'], 40) : '';
        $intervalpr = isset($_POST['intervalpr']) ? validTeks4($_POST['intervalpr'], 40) : '';
        $axis = isset($_POST['axis']) ? validTeks4($_POST['axis'], 40) : '';
        $kompleksqrs = isset($_POST['kompleksqrs']) ? validTeks4($_POST['kompleksqrs'], 40) : '';
        $segmenst = isset($_POST['segmenst']) ? validTeks4($_POST['segmenst'], 40) : 'Normal';
        $gelombangt = isset($_POST['gelombangt']) ? validTeks4($_POST['gelombangt'], 40) : 'Normal';
        $kesimpulan = isset($_POST['kesimpulan']) ? validTeks4($_POST['kesimpulan'], 2000) : '';
        
        // Check if data exists
        $query_check = "SELECT no_rawat FROM hasil_pemeriksaan_ekg WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE hasil_pemeriksaan_ekg SET 
                        tanggal = '$tanggal',
                        kd_dokter = '$kd_dokter',
                        diagnosa_klinis = '$diagnosa_klinis',
                        kiriman_dari = '$kiriman_dari',
                        irama = '$irama',
                        laju_jantung = '$laju_jantung',
                        gelombangp = '$gelombangp',
                        intervalpr = '$intervalpr',
                        axis = '$axis',
                        kompleksqrs = '$kompleksqrs',
                        segmenst = '$segmenst',
                        gelombangt = '$gelombangt',
                        kesimpulan = '$kesimpulan'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal mengupdate data pemeriksaan EKG');
            }
            
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data pemeriksaan EKG berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            // INSERT
            $query = "INSERT INTO hasil_pemeriksaan_ekg (
                        no_rawat, tanggal, kd_dokter, diagnosa_klinis, kiriman_dari,
                        irama, laju_jantung, gelombangp, intervalpr, axis,
                        kompleksqrs, segmenst, gelombangt, kesimpulan
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$diagnosa_klinis', '$kiriman_dari',
                        '$irama', '$laju_jantung', '$gelombangp', '$intervalpr', '$axis',
                        '$kompleksqrs', '$segmenst', '$gelombangt', '$kesimpulan'
                      )";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal menyimpan data pemeriksaan EKG');
            }
            
            insertTracker($query);
            
            // AUTO DOWNLOAD IMAGES FROM ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_ekg_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_ekg_base = defined('EKG_BASE_URL') ? EKG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanekg/';
                                $_ekg_parsed = parse_url($_ekg_base); $_ekg_host = isset($_ekg_parsed['host']) ? $_ekg_parsed['host'] : 'localhost';
                                $_ekg_is_local = in_array($_ekg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_ekg_is_local) { $_ekg_path = isset($_ekg_parsed['path']) ? rtrim($_ekg_parsed['path'], '/') : '/webapps/hasilpemeriksaanekg'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_ekg_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/ekg_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_ekg_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_ekg_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_pemeriksaan_ekg_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode(['status'=>'success','message'=>'Data pemeriksaan EKG berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert','images_downloaded'=>$images_downloaded]);
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
// HAPUS PEMERIKSAAN EKG
// ========================================
if ($aksi === 'hapus_ekg') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Check if data exists
        $query_check = "SELECT no_rawat FROM hasil_pemeriksaan_ekg WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) === 0) {
            throw new Exception('Data pemeriksaan EKG tidak ditemukan');
        }
        
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_ekg_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_ekg_base = defined('EKG_BASE_URL') ? EKG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanekg/';
            $_ekg_parsed = parse_url($_ekg_base); $_ekg_host = isset($_ekg_parsed['host']) ? $_ekg_parsed['host'] : 'localhost';
            $_ekg_is_local = in_array($_ekg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_ekg_is_local) {
                    $_ekg_path = isset($_ekg_parsed['path']) ? rtrim($_ekg_parsed['path'], '/') : '/webapps/hasilpemeriksaanekg';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_ekg_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_ekg_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_ekg_gambar WHERE no_rawat='$no_rawat'");
        }
        
        // ========================================
        // DELETE EKG RECORD
        // ========================================
        $query_delete = "DELETE FROM hasil_pemeriksaan_ekg WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data pemeriksaan EKG');
        }
        
        insertTracker($query_delete);
        
        echo json_encode(['status'=>'success','message'=>'Data pemeriksaan EKG berhasil dihapus','files_deleted'=>$fd]);
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// DOWNLOAD ORTHANC - EKG
// ========================================
if ($aksi === 'download_orthanc_images_ekg') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_ekg h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_ekg_base = defined('EKG_BASE_URL') ? EKG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanekg/';
        $_ekg_parsed = parse_url($_ekg_base); $_ekg_host = isset($_ekg_parsed['host']) ? $_ekg_parsed['host'] : 'localhost';
        $_ekg_is_local = in_array($_ekg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_ekg_is_local) { $_ekg_path = isset($_ekg_parsed['path']) ? rtrim($_ekg_parsed['path'], '/') : '/webapps/hasilpemeriksaanekg'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_ekg_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/ekg_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_ekg_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_ekg_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_ekg_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// UPLOAD MANUAL - EKG
// ========================================
if ($aksi === 'upload_manual_ekg') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_ekg WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_ekg h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_ekg_base = defined('EKG_BASE_URL') ? EKG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanekg/';
        $_ekg_parsed = parse_url($_ekg_base); $_ekg_host = isset($_ekg_parsed['host']) ? $_ekg_parsed['host'] : 'localhost';
        $_ekg_is_local = in_array($_ekg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_ekg_is_local) { $_ekg_path = isset($_ekg_parsed['path']) ? rtrim($_ekg_parsed['path'], '/') : '/webapps/hasilpemeriksaanekg'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_ekg_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/ekg_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_ekg_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_ekg_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_ekg_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[EKG-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[EKG-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_ekg_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// HAPUS SATU GAMBAR - EKG
// ========================================
if ($aksi === 'hapus_gambar_ekg') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo    = isset($_POST['photo'])    ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_ekg WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_ekg_base = defined('EKG_BASE_URL') ? EKG_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanekg/';
        $_ekg_parsed = parse_url($_ekg_base); $_ekg_host = isset($_ekg_parsed['host']) ? $_ekg_parsed['host'] : 'localhost';
        $_ekg_is_local = in_array($_ekg_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_ekg_is_local) { $_ekg_path = isset($_ekg_parsed['path']) ? rtrim($_ekg_parsed['path'], '/') : '/webapps/hasilpemeriksaanekg'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_ekg_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_ekg_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_pemeriksaan_ekg_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// SIMPAN TRIASE IGD
// ========================================
if ($aksi === 'simpan_triaseigd') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $triase_type = isset($_POST['triase_type']) ? validTeks4($_POST['triase_type'], 10) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        if (empty($triase_type)) {
            throw new Exception('Tipe triase harus dipilih (primer atau sekunder)');
        }
        
        // ========================================
        // TAB 0: DATA TRIASE IGD (TABEL UTAMA)
        // ========================================
        $tgl_kunjungan = isset($_POST['tgl_kunjungan']) ? $_POST['tgl_kunjungan'] : date('Y-m-d H:i:s');
        $cara_masuk = isset($_POST['cara_masuk']) ? validTeks4($_POST['cara_masuk'], 20) : 'Jalan';
        $alat_transportasi = isset($_POST['alat_transportasi']) ? validTeks4($_POST['alat_transportasi'], 20) : '-';
        $alasan_kedatangan = isset($_POST['alasan_kedatangan']) ? validTeks4($_POST['alasan_kedatangan'], 50) : 'Datang Sendiri';
        $keterangan_kedatangan = isset($_POST['keterangan_kedatangan']) ? validTeks4($_POST['keterangan_kedatangan'], 100) : '';
        $kode_kasus = isset($_POST['kode_kasus']) ? validTeks4($_POST['kode_kasus'], 3) : '';
        $tekanan_darah = isset($_POST['tekanan_darah']) ? validTeks4($_POST['tekanan_darah'], 8) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 3) : '';
        $pernapasan = isset($_POST['pernapasan']) ? validTeks4($_POST['pernapasan'], 3) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $saturasi_o2 = isset($_POST['saturasi_o2']) ? validTeks4($_POST['saturasi_o2'], 3) : '';
        $nyeri = isset($_POST['nyeri']) ? validTeks4($_POST['nyeri'], 5) : '';
        
        // Check if data_triase_igd exists
        $query_check_main = "SELECT no_rawat FROM data_triase_igd WHERE no_rawat = '$no_rawat'";
        $result_check_main = bukaquery($query_check_main);
        
        if (mysqli_num_rows($result_check_main) > 0) {
            // UPDATE data_triase_igd
            $query_main = "UPDATE data_triase_igd SET 
                            tgl_kunjungan = '$tgl_kunjungan',
                            cara_masuk = '$cara_masuk',
                            alat_transportasi = '$alat_transportasi',
                            alasan_kedatangan = '$alasan_kedatangan',
                            keterangan_kedatangan = '$keterangan_kedatangan',
                            kode_kasus = '$kode_kasus',
                            tekanan_darah = '$tekanan_darah',
                            nadi = '$nadi',
                            pernapasan = '$pernapasan',
                            suhu = '$suhu',
                            saturasi_o2 = '$saturasi_o2',
                            nyeri = '$nyeri'
                          WHERE no_rawat = '$no_rawat'";
        } else {
            // INSERT data_triase_igd
            $query_main = "INSERT INTO data_triase_igd (
                            no_rawat, tgl_kunjungan, cara_masuk, alat_transportasi,
                            alasan_kedatangan, keterangan_kedatangan, kode_kasus,
                            tekanan_darah, nadi, pernapasan, suhu, saturasi_o2, nyeri
                          ) VALUES (
                            '$no_rawat', '$tgl_kunjungan', '$cara_masuk', '$alat_transportasi',
                            '$alasan_kedatangan', '$keterangan_kedatangan', '$kode_kasus',
                            '$tekanan_darah', '$nadi', '$pernapasan', '$suhu', '$saturasi_o2', '$nyeri'
                          )";
        }
        
        $result_main = bukaquery($query_main);
        if (!$result_main) {
            throw new Exception('Gagal menyimpan data triase IGD utama');
        }
        insertTracker($query_main);
        
        // ========================================
        // HANDLE TRIASE PRIMER
        // ========================================
        if ($triase_type === 'primer') {
            
            $keluhan_utama = isset($_POST['keluhan_utama_primer']) ? validTeks4($_POST['keluhan_utama_primer'], 400) : '';
            $kebutuhan_khusus = isset($_POST['kebutuhan_khusus']) ? validTeks4($_POST['kebutuhan_khusus'], 30) : '-';
            $catatan = isset($_POST['catatan_primer']) ? validTeks4($_POST['catatan_primer'], 100) : '';
            $plan = isset($_POST['plan_primer']) ? validTeks4($_POST['plan_primer'], 50) : 'Ruang Resusitasi';
            $tanggaltriase = isset($_POST['tanggaltriase_primer']) ? $_POST['tanggaltriase_primer'] : date('Y-m-d H:i:s');
            $nik = isset($_POST['nik_primer']) ? validTeks4($_POST['nik_primer'], 20) : '';
            
            // Check if data_triase_igdprimer exists
            $query_check_primer = "SELECT no_rawat FROM data_triase_igdprimer WHERE no_rawat = '$no_rawat'";
            $result_check_primer = bukaquery($query_check_primer);
            
            if (mysqli_num_rows($result_check_primer) > 0) {
                // UPDATE
                $query_primer = "UPDATE data_triase_igdprimer SET 
                                keluhan_utama = '$keluhan_utama',
                                kebutuhan_khusus = '$kebutuhan_khusus',
                                catatan = '$catatan',
                                plan = '$plan',
                                tanggaltriase = '$tanggaltriase',
                                nik = '$nik'
                              WHERE no_rawat = '$no_rawat'";
            } else {
                // INSERT
                $query_primer = "INSERT INTO data_triase_igdprimer (
                                no_rawat, keluhan_utama, kebutuhan_khusus, catatan,
                                plan, tanggaltriase, nik
                              ) VALUES (
                                '$no_rawat', '$keluhan_utama', '$kebutuhan_khusus', '$catatan',
                                '$plan', '$tanggaltriase', '$nik'
                              )";
            }
            
            $result_primer = bukaquery($query_primer);
            if (!$result_primer) {
                throw new Exception('Gagal menyimpan data triase primer');
            }
            insertTracker($query_primer);
            
            // ========================================
            // DELETE OLD SKALA 1 & 2 DETAILS
            // ========================================
            $query_delete_skala1 = "DELETE FROM data_triase_igddetail_skala1 WHERE no_rawat = '$no_rawat'";
            bukaquery($query_delete_skala1);
            insertTracker($query_delete_skala1);
            
            $query_delete_skala2 = "DELETE FROM data_triase_igddetail_skala2 WHERE no_rawat = '$no_rawat'";
            bukaquery($query_delete_skala2);
            insertTracker($query_delete_skala2);
            
            // ========================================
            // INSERT SKALA 1 DETAILS
            // ========================================
            $skala1_count = 0;
            if (isset($_POST['skala1_check']) && is_array($_POST['skala1_check'])) {
                foreach ($_POST['skala1_check'] as $kode_skala1) {
                    $kode_skala1 = validTeks4($kode_skala1, 3);
                    
                    $query_insert_skala1 = "INSERT INTO data_triase_igddetail_skala1 (no_rawat, kode_skala1) 
                                           VALUES ('$no_rawat', '$kode_skala1')";
                    bukaquery($query_insert_skala1);
                    insertTracker($query_insert_skala1);
                    $skala1_count++;
                }
            }
            
            // ========================================
            // INSERT SKALA 2 DETAILS
            // ========================================
            $skala2_count = 0;
            if (isset($_POST['skala2_check']) && is_array($_POST['skala2_check'])) {
                foreach ($_POST['skala2_check'] as $kode_skala2) {
                    $kode_skala2 = validTeks4($kode_skala2, 3);
                    
                    $query_insert_skala2 = "INSERT INTO data_triase_igddetail_skala2 (no_rawat, kode_skala2) 
                                           VALUES ('$no_rawat', '$kode_skala2')";
                    bukaquery($query_insert_skala2);
                    insertTracker($query_insert_skala2);
                    $skala2_count++;
                }
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data Triase Primer berhasil disimpan',
                'no_rawat' => $no_rawat,
                'triase_type' => 'primer',
                'skala1_items' => $skala1_count,
                'skala2_items' => $skala2_count
            ]);
        }
        
        // ========================================
        // HANDLE TRIASE SEKUNDER
        // ========================================
        else if ($triase_type === 'sekunder') {
            
            $anamnesa_singkat = isset($_POST['anamnesa_singkat']) ? validTeks4($_POST['anamnesa_singkat'], 400) : '';
            $catatan = isset($_POST['catatan_sekunder']) ? validTeks4($_POST['catatan_sekunder'], 100) : '';
            $plan = isset($_POST['plan_sekunder']) ? validTeks4($_POST['plan_sekunder'], 20) : 'Zona Kuning';
            $tanggaltriase = isset($_POST['tanggaltriase_sekunder']) ? $_POST['tanggaltriase_sekunder'] : date('Y-m-d H:i:s');
            $nik = isset($_POST['nik_sekunder']) ? validTeks4($_POST['nik_sekunder'], 20) : '';
            
            // Check if data_triase_igdsekunder exists
            $query_check_sekunder = "SELECT no_rawat FROM data_triase_igdsekunder WHERE no_rawat = '$no_rawat'";
            $result_check_sekunder = bukaquery($query_check_sekunder);
            
            if (mysqli_num_rows($result_check_sekunder) > 0) {
                // UPDATE
                $query_sekunder = "UPDATE data_triase_igdsekunder SET 
                                  anamnesa_singkat = '$anamnesa_singkat',
                                  catatan = '$catatan',
                                  plan = '$plan',
                                  tanggaltriase = '$tanggaltriase',
                                  nik = '$nik'
                                WHERE no_rawat = '$no_rawat'";
            } else {
                // INSERT
                $query_sekunder = "INSERT INTO data_triase_igdsekunder (
                                  no_rawat, anamnesa_singkat, catatan, plan,
                                  tanggaltriase, nik
                                ) VALUES (
                                  '$no_rawat', '$anamnesa_singkat', '$catatan', '$plan',
                                  '$tanggaltriase', '$nik'
                                )";
            }
            
            $result_sekunder = bukaquery($query_sekunder);
            if (!$result_sekunder) {
                throw new Exception('Gagal menyimpan data triase sekunder');
            }
            insertTracker($query_sekunder);
            
            // ========================================
            // DELETE OLD SKALA 3, 4, 5 DETAILS
            // ========================================
            $query_delete_skala3 = "DELETE FROM data_triase_igddetail_skala3 WHERE no_rawat = '$no_rawat'";
            bukaquery($query_delete_skala3);
            insertTracker($query_delete_skala3);
            
            $query_delete_skala4 = "DELETE FROM data_triase_igddetail_skala4 WHERE no_rawat = '$no_rawat'";
            bukaquery($query_delete_skala4);
            insertTracker($query_delete_skala4);
            
            $query_delete_skala5 = "DELETE FROM data_triase_igddetail_skala5 WHERE no_rawat = '$no_rawat'";
            bukaquery($query_delete_skala5);
            insertTracker($query_delete_skala5);
            
            // ========================================
            // INSERT SKALA 3 DETAILS
            // ========================================
            $skala3_count = 0;
            if (isset($_POST['skala3_check']) && is_array($_POST['skala3_check'])) {
                foreach ($_POST['skala3_check'] as $kode_skala3) {
                    $kode_skala3 = validTeks4($kode_skala3, 3);
                    
                    $query_insert_skala3 = "INSERT INTO data_triase_igddetail_skala3 (no_rawat, kode_skala3) 
                                           VALUES ('$no_rawat', '$kode_skala3')";
                    bukaquery($query_insert_skala3);
                    insertTracker($query_insert_skala3);
                    $skala3_count++;
                }
            }
            
            // ========================================
            // INSERT SKALA 4 DETAILS
            // ========================================
            $skala4_count = 0;
            if (isset($_POST['skala4_check']) && is_array($_POST['skala4_check'])) {
                foreach ($_POST['skala4_check'] as $kode_skala4) {
                    $kode_skala4 = validTeks4($kode_skala4, 3);
                    
                    $query_insert_skala4 = "INSERT INTO data_triase_igddetail_skala4 (no_rawat, kode_skala4) 
                                           VALUES ('$no_rawat', '$kode_skala4')";
                    bukaquery($query_insert_skala4);
                    insertTracker($query_insert_skala4);
                    $skala4_count++;
                }
            }
            
            // ========================================
            // INSERT SKALA 5 DETAILS
            // ========================================
            $skala5_count = 0;
            if (isset($_POST['skala5_check']) && is_array($_POST['skala5_check'])) {
                foreach ($_POST['skala5_check'] as $kode_skala5) {
                    $kode_skala5 = validTeks4($kode_skala5, 3);
                    
                    $query_insert_skala5 = "INSERT INTO data_triase_igddetail_skala5 (no_rawat, kode_skala5) 
                                           VALUES ('$no_rawat', '$kode_skala5')";
                    bukaquery($query_insert_skala5);
                    insertTracker($query_insert_skala5);
                    $skala5_count++;
                }
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data Triase Sekunder berhasil disimpan',
                'no_rawat' => $no_rawat,
                'triase_type' => 'sekunder',
                'skala3_items' => $skala3_count,
                'skala4_items' => $skala4_count,
                'skala5_items' => $skala5_count
            ]);
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
// HAPUS TRIASE IGD
// ========================================
if ($aksi === 'hapus_triaseigd') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // ========================================
        // DELETE SKALA DETAILS (PRIMER)
        // ========================================
        $query_delete_skala1 = "DELETE FROM data_triase_igddetail_skala1 WHERE no_rawat = '$no_rawat'";
        bukaquery($query_delete_skala1);
        insertTracker($query_delete_skala1);
        
        $query_delete_skala2 = "DELETE FROM data_triase_igddetail_skala2 WHERE no_rawat = '$no_rawat'";
        bukaquery($query_delete_skala2);
        insertTracker($query_delete_skala2);
        
        // ========================================
        // DELETE SKALA DETAILS (SEKUNDER)
        // ========================================
        $query_delete_skala3 = "DELETE FROM data_triase_igddetail_skala3 WHERE no_rawat = '$no_rawat'";
        bukaquery($query_delete_skala3);
        insertTracker($query_delete_skala3);
        
        $query_delete_skala4 = "DELETE FROM data_triase_igddetail_skala4 WHERE no_rawat = '$no_rawat'";
        bukaquery($query_delete_skala4);
        insertTracker($query_delete_skala4);
        
        $query_delete_skala5 = "DELETE FROM data_triase_igddetail_skala5 WHERE no_rawat = '$no_rawat'";
        bukaquery($query_delete_skala5);
        insertTracker($query_delete_skala5);
        
        // ========================================
        // DELETE TRIASE PRIMER
        // ========================================
        $query_delete_primer = "DELETE FROM data_triase_igdprimer WHERE no_rawat = '$no_rawat'";
        bukaquery($query_delete_primer);
        insertTracker($query_delete_primer);
        
        // ========================================
        // DELETE TRIASE SEKUNDER
        // ========================================
        $query_delete_sekunder = "DELETE FROM data_triase_igdsekunder WHERE no_rawat = '$no_rawat'";
        bukaquery($query_delete_sekunder);
        insertTracker($query_delete_sekunder);
        
        // ========================================
        // DELETE TRIASE IGD UTAMA
        // ========================================
        $query_delete_main = "DELETE FROM data_triase_igd WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete_main);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data triase IGD');
        }
        
        insertTracker($query_delete_main);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data Triase IGD berhasil dihapus',
            'no_rawat' => $no_rawat
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
// GET DATA IGD UNTUK RANAP
// ========================================
if ($aksi === 'get_data_igd') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Query data dari penilaian_medis_igd
        $query = "SELECT * FROM penilaian_medis_igd WHERE no_rawat = '$no_rawat' LIMIT 1";
        $result = bukaquery($query);
        $data = mysqli_fetch_assoc($result);
        
        if ($data) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Data IGD ditemukan',
                'data' => $data
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Data Asesmen Medis IGD tidak ditemukan untuk pasien ini'
            ]);
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
// SEARCH DOKTER (untuk Konsul Medik)
// ========================================
if ($aksi === 'search_dokter') {
    
    try {
        $query_search = isset($_POST['query']) ? validTeks4($_POST['query'], 100) : '';
        
        if (empty($query_search) || strlen($query_search) < 2) {
            throw new Exception('Masukkan minimal 2 karakter untuk pencarian');
        }
        
        // Search by kd_dokter or nm_dokter
        $query = "SELECT kd_dokter, nm_dokter 
                  FROM dokter 
                  WHERE (kd_dokter LIKE '%$query_search%' OR nm_dokter LIKE '%$query_search%')
                  AND status = '1'
                  ORDER BY nm_dokter ASC
                  LIMIT 20";
        
        $result = bukaquery($query);
        $data = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data dokter ditemukan',
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
// GET RIWAYAT KONSUL MEDIK
// ========================================
if ($aksi === 'get_riwayat_konsul') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Query riwayat konsultasi dari tabel konsultasi_medik
        $query = "SELECT 
                    k.no_permintaan,
                    k.no_rawat,
                    k.tanggal,
                    k.jenis_permintaan as permintaan,
                    k.kd_dokter,
                    d1.nm_dokter as nm_dokter_konsul,
                    k.kd_dokter_dikonsuli,
                    d2.nm_dokter as nm_dokter_tujuan,
                    k.diagnosa_kerja as diagnosa,
                    k.uraian_konsultasi as uraian
                  FROM konsultasi_medik k
                  LEFT JOIN dokter d1 ON k.kd_dokter = d1.kd_dokter
                  LEFT JOIN dokter d2 ON k.kd_dokter_dikonsuli = d2.kd_dokter
                  WHERE k.no_rawat = '$no_rawat'
                  ORDER BY k.tanggal DESC";
        
        $result = bukaquery($query);
        $data = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                // Format tanggal
                if (!empty($row['tanggal'])) {
                    $row['tanggal'] = date('d-m-Y H:i', strtotime($row['tanggal']));
                }
                $data[] = $row;
            }
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => count($data) > 0 ? 'Data ditemukan' : 'Tidak ada riwayat konsultasi',
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
// GET DETAIL KONSUL MEDIK
// ========================================
if ($aksi === 'get_detail_konsul') {
    
    try {
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20) : '';
        
        if (empty($no_permintaan)) {
            throw new Exception('No. Permintaan tidak valid');
        }
        
        $query = "SELECT 
                    k.no_permintaan,
                    k.no_rawat,
                    k.tanggal,
                    k.jenis_permintaan,
                    k.kd_dokter,
                    d1.nm_dokter as nm_dokter_konsul,
                    k.kd_dokter_dikonsuli,
                    d2.nm_dokter as nm_dokter_tujuan,
                    k.diagnosa_kerja,
                    k.uraian_konsultasi
                  FROM konsultasi_medik k
                  LEFT JOIN dokter d1 ON k.kd_dokter = d1.kd_dokter
                  LEFT JOIN dokter d2 ON k.kd_dokter_dikonsuli = d2.kd_dokter
                  WHERE k.no_permintaan = '$no_permintaan'
                  LIMIT 1";
        
        $result = bukaquery($query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);
            echo json_encode([
                'status' => 'success',
                'data' => $data
            ]);
        } else {
            throw new Exception('Data tidak ditemukan');
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
// GET KONSUL MASUK (ditujukan ke dokter login)
// ========================================
if ($aksi === 'get_konsul_masuk') {
    
    try {
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $filter = isset($_POST['filter']) ? $_POST['filter'] : 'belum';
        
        $whereFilter = '';
        if ($filter === 'belum') {
            $whereFilter = ' AND j.no_permintaan IS NULL';
        } elseif ($filter === 'sudah') {
            $whereFilter = ' AND j.no_permintaan IS NOT NULL';
        }
        
        $query = "SELECT 
                    k.no_permintaan,
                    k.no_rawat,
                    k.tanggal,
                    k.jenis_permintaan,
                    k.kd_dokter,
                    d1.nm_dokter as nm_dokter_konsul,
                    k.diagnosa_kerja,
                    k.uraian_konsultasi,
                    rp.no_rkm_medis,
                    p.nm_pasien,
                    CASE WHEN j.no_permintaan IS NOT NULL THEN 1 ELSE 0 END as sudah_dijawab
                  FROM konsultasi_medik k
                  LEFT JOIN dokter d1 ON k.kd_dokter = d1.kd_dokter
                  LEFT JOIN jawaban_konsultasi_medik j ON k.no_permintaan = j.no_permintaan
                  LEFT JOIN reg_periksa rp ON k.no_rawat = rp.no_rawat
                  LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                  WHERE k.kd_dokter_dikonsuli = '$kd_dokter_login'
                  $whereFilter
                  ORDER BY k.tanggal DESC";
        
        $result = bukaquery($query);
        $data = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (!empty($row['tanggal'])) {
                    $row['tanggal'] = date('d-m-Y H:i', strtotime($row['tanggal']));
                }
                $row['sudah_dijawab'] = (bool)$row['sudah_dijawab'];
                $data[] = $row;
            }
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
// GET KONSUL KELUAR (dibuat oleh dokter login)
// ========================================
if ($aksi === 'get_konsul_keluar') {
    
    try {
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $filter = isset($_POST['filter']) ? $_POST['filter'] : 'belum';
        
        $whereFilter = '';
        if ($filter === 'belum') {
            $whereFilter = ' AND j.no_permintaan IS NULL';
        } elseif ($filter === 'sudah') {
            $whereFilter = ' AND j.no_permintaan IS NOT NULL';
        }
        
        $query = "SELECT 
                    k.no_permintaan,
                    k.no_rawat,
                    k.tanggal,
                    k.jenis_permintaan,
                    k.kd_dokter_dikonsuli,
                    d2.nm_dokter as nm_dokter_dikonsuli,
                    k.diagnosa_kerja,
                    k.uraian_konsultasi,
                    rp.no_rkm_medis,
                    p.nm_pasien,
                    CASE WHEN j.no_permintaan IS NOT NULL THEN 1 ELSE 0 END as sudah_dijawab
                  FROM konsultasi_medik k
                  LEFT JOIN dokter d2 ON k.kd_dokter_dikonsuli = d2.kd_dokter
                  LEFT JOIN jawaban_konsultasi_medik j ON k.no_permintaan = j.no_permintaan
                  LEFT JOIN reg_periksa rp ON k.no_rawat = rp.no_rawat
                  LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                  WHERE k.kd_dokter = '$kd_dokter_login'
                  $whereFilter
                  ORDER BY k.tanggal DESC";
        
        $result = bukaquery($query);
        $data = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (!empty($row['tanggal'])) {
                    $row['tanggal'] = date('d-m-Y H:i', strtotime($row['tanggal']));
                }
                $row['sudah_dijawab'] = (bool)$row['sudah_dijawab'];
                $data[] = $row;
            }
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
// GET KONSUL UNTUK DIJAWAB (by dokter dikonsuli) - LEGACY
// ========================================
if ($aksi === 'get_konsul_untuk_dijawab') {
    
    try {
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $filter = isset($_POST['filter']) ? $_POST['filter'] : 'semua';
        
        $whereFilter = '';
        if ($filter === 'belum') {
            $whereFilter = ' AND j.no_permintaan IS NULL';
        } elseif ($filter === 'sudah') {
            $whereFilter = ' AND j.no_permintaan IS NOT NULL';
        }
        
        $query = "SELECT 
                    k.no_permintaan,
                    k.no_rawat,
                    k.tanggal,
                    k.jenis_permintaan,
                    k.kd_dokter,
                    d1.nm_dokter as nm_dokter_konsul,
                    k.diagnosa_kerja,
                    k.uraian_konsultasi,
                    rp.no_rkm_medis,
                    p.nm_pasien,
                    CASE WHEN j.no_permintaan IS NOT NULL THEN 1 ELSE 0 END as sudah_dijawab
                  FROM konsultasi_medik k
                  LEFT JOIN dokter d1 ON k.kd_dokter = d1.kd_dokter
                  LEFT JOIN jawaban_konsultasi_medik j ON k.no_permintaan = j.no_permintaan
                  LEFT JOIN reg_periksa rp ON k.no_rawat = rp.no_rawat
                  LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                  WHERE k.kd_dokter_dikonsuli = '$kd_dokter_login'
                  $whereFilter
                  ORDER BY k.tanggal DESC";
        
        $result = bukaquery($query);
        $data = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (!empty($row['tanggal'])) {
                    $row['tanggal'] = date('d-m-Y H:i', strtotime($row['tanggal']));
                }
                $row['sudah_dijawab'] = (bool)$row['sudah_dijawab'];
                $data[] = $row;
            }
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
// GET DETAIL KONSUL UNTUK JAWAB
// ========================================
if ($aksi === 'get_detail_konsul_jawab') {
    
    try {
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20) : '';
        
        if (empty($no_permintaan)) {
            throw new Exception('No. Permintaan tidak valid');
        }
        
        // Get konsultasi data
        $query = "SELECT 
                    k.no_permintaan,
                    k.no_rawat,
                    k.tanggal,
                    k.jenis_permintaan,
                    k.kd_dokter,
                    d1.nm_dokter as nm_dokter_konsul,
                    k.kd_dokter_dikonsuli,
                    d2.nm_dokter as nm_dokter_dikonsuli,
                    k.diagnosa_kerja,
                    k.uraian_konsultasi,
                    rp.no_rkm_medis,
                    p.nm_pasien
                  FROM konsultasi_medik k
                  LEFT JOIN dokter d1 ON k.kd_dokter = d1.kd_dokter
                  LEFT JOIN dokter d2 ON k.kd_dokter_dikonsuli = d2.kd_dokter
                  LEFT JOIN reg_periksa rp ON k.no_rawat = rp.no_rawat
                  LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                  WHERE k.no_permintaan = '$no_permintaan'
                  LIMIT 1";
        
        $result = bukaquery($query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            throw new Exception('Data tidak ditemukan');
        }
        
        $data = mysqli_fetch_assoc($result);
        
        // Format tanggal
        if (!empty($data['tanggal'])) {
            $data['tanggal'] = date('d-m-Y H:i', strtotime($data['tanggal']));
        }
        
        // Get jawaban if exists
        $queryJawaban = "SELECT * FROM jawaban_konsultasi_medik WHERE no_permintaan = '$no_permintaan' LIMIT 1";
        $resultJawaban = bukaquery($queryJawaban);
        
        if ($resultJawaban && mysqli_num_rows($resultJawaban) > 0) {
            $jawaban = mysqli_fetch_assoc($resultJawaban);
            $data['jawaban'] = $jawaban;
            if (!empty($jawaban['tanggal'])) {
                $data['tanggal_jawaban'] = date('d-m-Y H:i', strtotime($jawaban['tanggal']));
            }
        } else {
            $data['jawaban'] = null;
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
// SIMPAN SURAT KETERANGAN SEHAT
// ========================================
if ($aksi === 'simpan_surat_keterangan_sehat') {
    
    try {
        $no_surat = isset($_POST['no_surat']) ? validTeks4($_POST['no_surat'], 17) : '';
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        $tanggalsurat = isset($_POST['tanggalsurat']) ? validTeks4($_POST['tanggalsurat'], 10) : date('Y-m-d');
        $berat = isset($_POST['berat']) ? validTeks4($_POST['berat'], 3) : '';
        $tinggi = isset($_POST['tinggi']) ? validTeks4($_POST['tinggi'], 3) : '';
        $tensi = isset($_POST['tensi']) ? validTeks4($_POST['tensi'], 8) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 4) : '';
        $butawarna = isset($_POST['butawarna']) ? validTeks4($_POST['butawarna'], 5) : 'Tidak';
        $keperluan = isset($_POST['keperluan']) ? validTeks4($_POST['keperluan'], 100) : '';
        $kesimpulan = isset($_POST['kesimpulan']) ? validTeks4($_POST['kesimpulan'], 12) : 'Sehat';
        
        if (empty($no_surat) || empty($no_rawat)) {
            throw new Exception('No. Surat dan No. Rawat tidak boleh kosong');
        }
        
        $cekExist = bukaquery("SELECT no_surat FROM surat_keterangan_sehat WHERE no_rawat = '$no_rawat'");
        
        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            $query = "UPDATE surat_keterangan_sehat SET 
                no_surat = '$no_surat',
                tanggalsurat = '$tanggalsurat',
                berat = '$berat',
                tinggi = '$tinggi',
                tensi = '$tensi',
                suhu = '$suhu',
                butawarna = '$butawarna',
                keperluan = '$keperluan',
                kesimpulan = '$kesimpulan'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Surat keterangan sehat berhasil diperbarui';
        } else {
            $query = "INSERT INTO surat_keterangan_sehat (
                no_surat, no_rawat, tanggalsurat, berat, tinggi, tensi, suhu, butawarna, keperluan, kesimpulan
            ) VALUES (
                '$no_surat', '$no_rawat', '$tanggalsurat', '$berat', '$tinggi', '$tensi', '$suhu', '$butawarna', '$keperluan', '$kesimpulan'
            )";
            $msg = 'Surat keterangan sehat berhasil disimpan';
        }
        
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan surat keterangan sehat');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS SURAT KETERANGAN SEHAT
// ========================================
if ($aksi === 'hapus_surat_keterangan_sehat') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $cekData = bukaquery("SELECT no_surat FROM surat_keterangan_sehat WHERE no_rawat = '$no_rawat'");
        
        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }
        
        $query = "DELETE FROM surat_keterangan_sehat WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Surat keterangan sehat berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// SIMPAN SURAT BUTA WARNA
// ========================================
if ($aksi === 'simpan_surat_buta_warna') {
    
    try {
        $no_surat = isset($_POST['no_surat']) ? validTeks4($_POST['no_surat'], 20) : '';
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        $tanggalperiksa = isset($_POST['tanggalperiksa']) ? validTeks4($_POST['tanggalperiksa'], 10) : date('Y-m-d');
        $hasilperiksa = isset($_POST['hasilperiksa']) ? validTeks4($_POST['hasilperiksa'], 20) : 'Tidak Buta Warna';
        
        if (empty($no_surat) || empty($no_rawat)) {
            throw new Exception('No. Surat dan No. Rawat tidak boleh kosong');
        }
        
        // Cek apakah sudah ada data
        $cekExist = bukaquery("SELECT no_surat FROM surat_buta_warna WHERE no_rawat = '$no_rawat'");
        
        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            // UPDATE
            $query = "UPDATE surat_buta_warna SET 
                no_surat = '$no_surat',
                tanggalperiksa = '$tanggalperiksa',
                hasilperiksa = '$hasilperiksa'
                WHERE no_rawat = '$no_rawat'";
            $msg = 'Surat keterangan buta warna berhasil diperbarui';
        } else {
            // INSERT
            $query = "INSERT INTO surat_buta_warna (
                no_surat, no_rawat, tanggalperiksa, hasilperiksa
            ) VALUES (
                '$no_surat', '$no_rawat', '$tanggalperiksa', '$hasilperiksa'
            )";
            $msg = 'Surat keterangan buta warna berhasil disimpan';
        }
        
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan surat keterangan buta warna');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// HAPUS SURAT BUTA WARNA
// ========================================
if ($aksi === 'hapus_surat_buta_warna') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $cekData = bukaquery("SELECT no_surat FROM surat_buta_warna WHERE no_rawat = '$no_rawat'");
        
        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }
        
        $query = "DELETE FROM surat_buta_warna WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);
        
        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Surat keterangan buta warna berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit();
}

// ========================================
// SIMPAN RESUME MEDIS
// ========================================
if ($aksi === 'simpan_resume') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Data dari form Resume Medis
        // Anamnesis & Riwayat Perawatan
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 5000) : '';
        $jalannya_penyakit = isset($_POST['jalannya_penyakit']) ? validTeks4($_POST['jalannya_penyakit'], 5000) : '';
        $pemeriksaan_penunjang = isset($_POST['pemeriksaan_penunjang']) ? validTeks4($_POST['pemeriksaan_penunjang'], 5000) : '';
        $hasil_laborat = isset($_POST['hasil_laborat']) ? validTeks4($_POST['hasil_laborat'], 5000) : '';
        
        // Diagnosis
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
        
        // Prosedur / Tindakan
        $prosedur_utama = isset($_POST['prosedur_utama']) ? validTeks4($_POST['prosedur_utama'], 80) : '';
        $kd_prosedur_utama = isset($_POST['kd_prosedur_utama']) ? validTeks4($_POST['kd_prosedur_utama'], 8) : '';
        
        $prosedur_sekunder = isset($_POST['prosedur_sekunder']) ? validTeks4($_POST['prosedur_sekunder'], 80) : '';
        $kd_prosedur_sekunder = isset($_POST['kd_prosedur_sekunder']) ? validTeks4($_POST['kd_prosedur_sekunder'], 8) : '';
        
        $prosedur_sekunder2 = isset($_POST['prosedur_sekunder2']) ? validTeks4($_POST['prosedur_sekunder2'], 80) : '';
        $kd_prosedur_sekunder2 = isset($_POST['kd_prosedur_sekunder2']) ? validTeks4($_POST['kd_prosedur_sekunder2'], 8) : '';
        
        $prosedur_sekunder3 = isset($_POST['prosedur_sekunder3']) ? validTeks4($_POST['prosedur_sekunder3'], 80) : '';
        $kd_prosedur_sekunder3 = isset($_POST['kd_prosedur_sekunder3']) ? validTeks4($_POST['kd_prosedur_sekunder3'], 8) : '';
        
        // Tindak Lanjut & Kondisi Pulang
        $kondisi_pulang = isset($_POST['kondisi_pulang']) ? validTeks4($_POST['kondisi_pulang'], 20) : '';
        $obat_pulang = isset($_POST['obat_pulang']) ? validTeks4($_POST['obat_pulang'], 5000) : '';
        
        // Validasi: Tidak ada field wajib (semua optional untuk Resume Medis)
        // Tapi minimal salah satu field harus diisi
        if (empty($keluhan_utama) && empty($jalannya_penyakit) && empty($diagnosa_utama)) {
            throw new Exception('Minimal isi salah satu field (Keluhan Utama, Jalannya Penyakit, atau Diagnosa Utama)');
        }
        
        // Cek apakah data sudah ada
        $query_check = "SELECT no_rawat FROM resume_pasien WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE resume_pasien SET 
                        kd_dokter = '$kd_dokter',
                        keluhan_utama = '$keluhan_utama',
                        jalannya_penyakit = '$jalannya_penyakit',
                        pemeriksaan_penunjang = '$pemeriksaan_penunjang',
                        hasil_laborat = '$hasil_laborat',
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
                        kondisi_pulang = '$kondisi_pulang',
                        obat_pulang = '$obat_pulang'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal mengupdate data Resume Medis');
            }
            
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data Resume Medis berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            // INSERT
            $query = "INSERT INTO resume_pasien (
                        no_rawat, kd_dokter, 
                        keluhan_utama, jalannya_penyakit, 
                        pemeriksaan_penunjang, hasil_laborat,
                        diagnosa_utama, kd_diagnosa_utama,
                        diagnosa_sekunder, kd_diagnosa_sekunder,
                        diagnosa_sekunder2, kd_diagnosa_sekunder2,
                        diagnosa_sekunder3, kd_diagnosa_sekunder3,
                        diagnosa_sekunder4, kd_diagnosa_sekunder4,
                        prosedur_utama, kd_prosedur_utama,
                        prosedur_sekunder, kd_prosedur_sekunder,
                        prosedur_sekunder2, kd_prosedur_sekunder2,
                        prosedur_sekunder3, kd_prosedur_sekunder3,
                        kondisi_pulang, obat_pulang
                      ) VALUES (
                        '$no_rawat', '$kd_dokter',
                        '$keluhan_utama', '$jalannya_penyakit',
                        '$pemeriksaan_penunjang', '$hasil_laborat',
                        '$diagnosa_utama', '$kd_diagnosa_utama',
                        '$diagnosa_sekunder', '$kd_diagnosa_sekunder',
                        '$diagnosa_sekunder2', '$kd_diagnosa_sekunder2',
                        '$diagnosa_sekunder3', '$kd_diagnosa_sekunder3',
                        '$diagnosa_sekunder4', '$kd_diagnosa_sekunder4',
                        '$prosedur_utama', '$kd_prosedur_utama',
                        '$prosedur_sekunder', '$kd_prosedur_sekunder',
                        '$prosedur_sekunder2', '$kd_prosedur_sekunder2',
                        '$prosedur_sekunder3', '$kd_prosedur_sekunder3',
                        '$kondisi_pulang', '$obat_pulang'
                      )";
            
            $result = bukaquery($query);
            
            if (!$result) {
                throw new Exception('Gagal menyimpan data Resume Medis');
            }
            
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data Resume Medis berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action' => 'insert'
            ]);
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
// HAPUS RESUME MEDIS
// ========================================
if ($aksi === 'hapus_resume') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        // Cek apakah data ada dan milik dokter yang login
        $query_cek = "SELECT no_rawat FROM resume_pasien 
                      WHERE no_rawat = '$no_rawat' 
                      AND kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }
        
        // Hapus data
        $query_delete = "DELETE FROM resume_pasien WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data Resume Medis');
        }
        
        insertTracker($query_delete);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data Resume Medis berhasil dihapus',
            'no_rawat' => $no_rawat
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}

// ============================================================
// SIMPAN SURAT SAKIT
// ============================================================
if($aksi === 'simpan_surat_sakit') {
    try {
        $no_rawat     = isset($_POST['no_rawat'])     ? trim($_POST['no_rawat'])     : '';
        $no_surat     = isset($_POST['no_surat'])     ? trim($_POST['no_surat'])     : '';
        $tanggalawal  = isset($_POST['tanggalawal'])  ? trim($_POST['tanggalawal'])  : '';
        $tanggalakhir = isset($_POST['tanggalakhir']) ? trim($_POST['tanggalakhir']) : '';
        $lamasakit    = isset($_POST['lamasakit'])    ? trim($_POST['lamasakit'])    : '';
        $kd_dokter    = isset($_POST['kd_dokter'])    ? trim($_POST['kd_dokter'])    : '';

        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        if(empty($no_surat)) throw new Exception('No. Surat tidak boleh kosong');

        // Cek apakah sudah ada
        $cek = bukaquery("SELECT no_surat FROM suratsakit WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            // UPDATE
            $q = "UPDATE suratsakit SET 
                    no_surat     = '$no_surat',
                    tanggalawal  = '$tanggalawal',
                    tanggalakhir = '$tanggalakhir',
                    lamasakit    = '$lamasakit'
                  WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Surat Sakit berhasil diperbarui';
        } else {
            // INSERT
            $q = "INSERT INTO suratsakit (no_surat, no_rawat, tanggalawal, tanggalakhir, lamasakit)
                  VALUES ('$no_surat', '$no_rawat', '$tanggalawal', '$tanggalakhir', '$lamasakit')";
            $msg = 'Data Surat Sakit berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS SURAT SAKIT
// ============================================================
if($aksi === 'hapus_surat_sakit') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_surat FROM suratsakit WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM suratsakit WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => 'Data Surat Sakit berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// SIMPAN CHECKLIST KRITERIA MASUK ICU
// ============================================================
if($aksi === 'simpan_checklist_kriteria_masuk_icu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        $nik      = isset($_POST['nik'])      ? trim($_POST['nik'])      : '';

        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Daftar semua kolom enum
        $enumFields = [
            'prioritas1_1','prioritas1_2','prioritas1_3','prioritas1_4','prioritas1_5','prioritas1_6',
            'prioritas2_1','prioritas2_2','prioritas2_3','prioritas2_4','prioritas2_5','prioritas2_6','prioritas2_7','prioritas2_8',
            'prioritas3_1','prioritas3_2','prioritas3_3','prioritas3_4',
            'kriteria_fisiologis_tanda_vital_1','kriteria_fisiologis_tanda_vital_2','kriteria_fisiologis_tanda_vital_3','kriteria_fisiologis_tanda_vital_4','kriteria_fisiologis_tanda_vital_5',
            'kriteria_fisiologis_laborat_1','kriteria_fisiologis_laborat_2','kriteria_fisiologis_laborat_3','kriteria_fisiologis_laborat_4','kriteria_fisiologis_laborat_5','kriteria_fisiologis_laborat_6',
            'kriteria_fisiologis_radiologi_1','kriteria_fisiologis_radiologi_2',
            'kriteria_fisiologis_klinis_1','kriteria_fisiologis_klinis_2','kriteria_fisiologis_klinis_3','kriteria_fisiologis_klinis_4','kriteria_fisiologis_klinis_5','kriteria_fisiologis_klinis_6','kriteria_fisiologis_klinis_7','kriteria_fisiologis_klinis_8'
        ];

        // Ambil nilai dari POST, default 'Tidak'
        $values = [];
        foreach($enumFields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : 'Tidak';
            $values[$field] = ($val === 'Ya') ? 'Ya' : 'Tidak';
        }

        // Cek apakah sudah ada
        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_masuk_icu WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            // UPDATE
            $setParts = [];
            foreach($values as $col => $val) {
                $setParts[] = "$col = '$val'";
            }
            $setParts[] = "tanggal = NOW()";
            $setParts[] = "nik = '$nik'";
            $q = "UPDATE checklist_kriteria_masuk_icu SET " . implode(', ', $setParts) . " WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Checklist Kriteria Masuk ICU berhasil diperbarui';
        } else {
            // INSERT
            $cols = ['no_rawat', 'tanggal', 'nik'];
            $vals = ["'$no_rawat'", 'NOW()', "'$nik'"];
            foreach($values as $col => $val) {
                $cols[] = $col;
                $vals[] = "'$val'";
            }
            $q = "INSERT INTO checklist_kriteria_masuk_icu (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $msg = 'Data Checklist Kriteria Masuk ICU berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS CHECKLIST KRITERIA MASUK ICU
// ============================================================
if($aksi === 'hapus_checklist_kriteria_masuk_icu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_masuk_icu WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM checklist_kriteria_masuk_icu WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => 'Data Checklist Kriteria Masuk ICU berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// SIMPAN CHECKLIST KRITERIA MASUK HCU
// ============================================================
if($aksi === 'simpan_checklist_kriteria_masuk_hcu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        $nik      = isset($_POST['nik'])      ? trim($_POST['nik'])      : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $enumFields = [
            'kardiologi1','kardiologi2','kardiologi3','kardiologi4','kardiologi5','kardiologi6',
            'pernapasan1','pernapasan2','pernapasan3',
            'syaraf1','syaraf2','syaraf3','syaraf4',
            'pencernaan1','pencernaan2','pencernaan3','pencernaan4',
            'pembedahan1','pembedahan2',
            'hematologi1','hematologi2',
            'infeksi'
        ];

        $values = [];
        foreach($enumFields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : 'Tidak';
            $values[$field] = ($val === 'Ya') ? 'Ya' : 'Tidak';
        }

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_masuk_hcu WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            $setParts = [];
            foreach($values as $col => $val) { $setParts[] = "$col = '$val'"; }
            $setParts[] = "tanggal = NOW()";
            $setParts[] = "nik = '$nik'";
            $q = "UPDATE checklist_kriteria_masuk_hcu SET " . implode(', ', $setParts) . " WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Checklist Kriteria Masuk HCU berhasil diperbarui';
        } else {
            $cols = ['no_rawat', 'tanggal', 'nik'];
            $vals = ["'$no_rawat'", 'NOW()', "'$nik'"];
            foreach($values as $col => $val) { $cols[] = $col; $vals[] = "'$val'"; }
            $q = "INSERT INTO checklist_kriteria_masuk_hcu (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $msg = 'Data Checklist Kriteria Masuk HCU berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');
        insertTracker($q);
        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS CHECKLIST KRITERIA MASUK HCU
// ============================================================
if($aksi === 'hapus_checklist_kriteria_masuk_hcu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_masuk_hcu WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM checklist_kriteria_masuk_hcu WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');
        insertTracker($q);
        echo json_encode(['status' => 'success', 'message' => 'Data Checklist Kriteria Masuk HCU berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// SIMPAN PENILAIAN MEDIS RALAN ANAK
// ============================================================
if($aksi === 'simpan_awalmedisanak') {
    try {
        $no_rawat      = isset($_POST['no_rawat'])      ? trim($_POST['no_rawat'])      : '';
        $kd_dokter     = isset($_POST['kd_dokter'])     ? trim($_POST['kd_dokter'])     : '';
        $tanggal       = isset($_POST['tanggal'])       ? trim($_POST['tanggal'])       : date('Y-m-d H:i:s');
        $anamnesis     = isset($_POST['anamnesis'])     ? trim($_POST['anamnesis'])     : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? trim($_POST['hubungan'])      : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? trim($_POST['keluhan_utama']) : '';
        $rps           = isset($_POST['rps'])           ? trim($_POST['rps'])           : '';
        $rpd           = isset($_POST['rpd'])           ? trim($_POST['rpd'])           : '';
        $rpk           = isset($_POST['rpk'])           ? trim($_POST['rpk'])           : '';
        $rpo           = isset($_POST['rpo'])           ? trim($_POST['rpo'])           : '';
        $alergi        = isset($_POST['alergi'])        ? trim($_POST['alergi'])        : '';
        $keadaan       = isset($_POST['keadaan'])       ? trim($_POST['keadaan'])       : 'Sehat';
        $gcs           = isset($_POST['gcs'])           ? trim($_POST['gcs'])           : '';
        $kesadaran     = isset($_POST['kesadaran'])     ? trim($_POST['kesadaran'])     : 'Compos Mentis';
        $td            = isset($_POST['td'])            ? trim($_POST['td'])            : '';
        $nadi          = isset($_POST['nadi'])          ? trim($_POST['nadi'])          : '';
        $rr            = isset($_POST['rr'])            ? trim($_POST['rr'])            : '';
        $suhu          = isset($_POST['suhu'])          ? trim($_POST['suhu'])          : '';
        $spo           = isset($_POST['spo'])           ? trim($_POST['spo'])           : '';
        $bb            = isset($_POST['bb'])            ? trim($_POST['bb'])            : '';
        $tb            = isset($_POST['tb'])            ? trim($_POST['tb'])            : '';
        $kepala        = isset($_POST['kepala'])        ? trim($_POST['kepala'])        : 'Normal';
        $mata          = isset($_POST['mata'])          ? trim($_POST['mata'])          : 'Normal';
        $gigi          = isset($_POST['gigi'])          ? trim($_POST['gigi'])          : 'Normal';
        $tht           = isset($_POST['tht'])           ? trim($_POST['tht'])           : 'Normal';
        $thoraks       = isset($_POST['thoraks'])       ? trim($_POST['thoraks'])       : 'Normal';
        $abdomen       = isset($_POST['abdomen'])       ? trim($_POST['abdomen'])       : 'Normal';
        $genital       = isset($_POST['genital'])       ? trim($_POST['genital'])       : 'Normal';
        $ekstremitas   = isset($_POST['ekstremitas'])   ? trim($_POST['ekstremitas'])   : 'Normal';
        $kulit         = isset($_POST['kulit'])         ? trim($_POST['kulit'])         : 'Normal';
        $ket_fisik     = isset($_POST['ket_fisik'])     ? trim($_POST['ket_fisik'])     : '';
        $ket_lokalis   = isset($_POST['ket_lokalis'])   ? trim($_POST['ket_lokalis'])   : '';
        $penunjang     = isset($_POST['penunjang'])     ? trim($_POST['penunjang'])     : '';
        $diagnosis     = isset($_POST['diagnosis'])     ? trim($_POST['diagnosis'])     : '';
        $tata          = isset($_POST['tata'])          ? trim($_POST['tata'])          : '';
        $konsul        = isset($_POST['konsul'])        ? trim($_POST['konsul'])        : '';

        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Format tanggal
        if(strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';

        $cek = bukaquery("SELECT no_rawat FROM penilaian_medis_ralan_anak WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            $q = "UPDATE penilaian_medis_ralan_anak SET 
                    tanggal='$tanggal', kd_dokter='$kd_dokter', anamnesis='$anamnesis', hubungan='$hubungan',
                    keluhan_utama='$keluhan_utama', rps='$rps', rpd='$rpd', rpk='$rpk', rpo='$rpo', alergi='$alergi',
                    keadaan='$keadaan', gcs='$gcs', kesadaran='$kesadaran',
                    td='$td', nadi='$nadi', rr='$rr', suhu='$suhu', spo='$spo', bb='$bb', tb='$tb',
                    kepala='$kepala', mata='$mata', gigi='$gigi', tht='$tht', thoraks='$thoraks',
                    abdomen='$abdomen', genital='$genital', ekstremitas='$ekstremitas', kulit='$kulit',
                    ket_fisik='$ket_fisik', ket_lokalis='$ket_lokalis', penunjang='$penunjang',
                    diagnosis='$diagnosis', tata='$tata', konsul='$konsul'
                  WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Penilaian Medis Anak berhasil diperbarui';
        } else {
            $q = "INSERT INTO penilaian_medis_ralan_anak 
                    (no_rawat, tanggal, kd_dokter, anamnesis, hubungan, keluhan_utama, rps, rpd, rpk, rpo, alergi,
                     keadaan, gcs, kesadaran, td, nadi, rr, suhu, spo, bb, tb,
                     kepala, mata, gigi, tht, thoraks, abdomen, genital, ekstremitas, kulit,
                     ket_fisik, ket_lokalis, penunjang, diagnosis, tata, konsul)
                  VALUES 
                    ('$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan', '$keluhan_utama', '$rps', '$rpd', '$rpk', '$rpo', '$alergi',
                     '$keadaan', '$gcs', '$kesadaran', '$td', '$nadi', '$rr', '$suhu', '$spo', '$bb', '$tb',
                     '$kepala', '$mata', '$gigi', '$tht', '$thoraks', '$abdomen', '$genital', '$ekstremitas', '$kulit',
                     '$ket_fisik', '$ket_lokalis', '$penunjang', '$diagnosis', '$tata', '$konsul')";
            $msg = 'Data Penilaian Medis Anak berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');
        insertTracker($q);
        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS PENILAIAN MEDIS RALAN ANAK
// ============================================================
if($aksi === 'hapus_awalmedisanak') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_rawat FROM penilaian_medis_ralan_anak WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM penilaian_medis_ralan_anak WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');
        insertTracker($q);
        echo json_encode(['status' => 'success', 'message' => 'Data Penilaian Medis Anak berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// SIMPAN CHECKLIST KRITERIA KELUAR ICU
// ============================================================
if($aksi === 'simpan_checklist_kriteria_keluar_icu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        $nik      = isset($_POST['nik'])      ? trim($_POST['nik'])      : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $enumFields = ['kriteria1','kriteria2','kriteria3','kriteria4','kriteria5','kriteria6','kriteria7','kriteria8','kriteria9','kriteria10','kriteria11'];

        $values = [];
        foreach($enumFields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : 'Tidak';
            $values[$field] = ($val === 'Ya') ? 'Ya' : 'Tidak';
        }

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_keluar_icu WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            $setParts = [];
            foreach($values as $col => $val) { $setParts[] = "$col = '$val'"; }
            $setParts[] = "tanggal = NOW()";
            $setParts[] = "nik = '$nik'";
            $q = "UPDATE checklist_kriteria_keluar_icu SET " . implode(', ', $setParts) . " WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Checklist Kriteria Keluar ICU berhasil diperbarui';
        } else {
            $cols = ['no_rawat', 'tanggal', 'nik'];
            $vals = ["'$no_rawat'", 'NOW()', "'$nik'"];
            foreach($values as $col => $val) { $cols[] = $col; $vals[] = "'$val'"; }
            $q = "INSERT INTO checklist_kriteria_keluar_icu (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $msg = 'Data Checklist Kriteria Keluar ICU berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');
        insertTracker($q);
        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS CHECKLIST KRITERIA KELUAR ICU
// ============================================================
if($aksi === 'hapus_checklist_kriteria_keluar_icu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_keluar_icu WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM checklist_kriteria_keluar_icu WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');
        insertTracker($q);
        echo json_encode(['status' => 'success', 'message' => 'Data Checklist Kriteria Keluar ICU berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// SIMPAN CHECKLIST KRITERIA KELUAR HCU
// ============================================================
if($aksi === 'simpan_checklist_kriteria_keluar_hcu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        $nik      = isset($_POST['nik'])      ? trim($_POST['nik'])      : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $enumFields = ['kriteria1','kriteria2','kriteria3','kriteria4','kriteria5','kriteria6','kriteria7','kriteria8','kriteria9','kriteria10','kriteria11','kriteria12'];

        $values = [];
        foreach($enumFields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : 'Tidak';
            $values[$field] = ($val === 'Ya') ? 'Ya' : 'Tidak';
        }

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_keluar_hcu WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            $setParts = [];
            foreach($values as $col => $val) { $setParts[] = "$col = '$val'"; }
            $setParts[] = "tanggal = NOW()";
            $setParts[] = "nik = '$nik'";
            $q = "UPDATE checklist_kriteria_keluar_hcu SET " . implode(', ', $setParts) . " WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Checklist Kriteria Keluar HCU berhasil diperbarui';
        } else {
            $cols = ['no_rawat', 'tanggal', 'nik'];
            $vals = ["'$no_rawat'", 'NOW()', "'$nik'"];
            foreach($values as $col => $val) { $cols[] = $col; $vals[] = "'$val'"; }
            $q = "INSERT INTO checklist_kriteria_keluar_hcu (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $msg = 'Data Checklist Kriteria Keluar HCU berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');
        insertTracker($q);
        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS CHECKLIST KRITERIA KELUAR HCU
// ============================================================
if($aksi === 'hapus_checklist_kriteria_keluar_hcu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_keluar_hcu WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM checklist_kriteria_keluar_hcu WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');
        insertTracker($q);
        echo json_encode(['status' => 'success', 'message' => 'Data Checklist Kriteria Keluar HCU berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN PENILAIAN AWAL MEDIS PARU
// ========================================
if ($aksi === 'simpan_awalmedisparu') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Riwayat Kesehatan
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if(strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis = isset($_POST['anamnesis']) ? validTeks4($_POST['anamnesis'], 20) : 'Autoanamnesis';
        $hubungan = isset($_POST['hubungan']) ? validTeks4($_POST['hubungan'], 30) : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps = isset($_POST['rps']) ? validTeks4($_POST['rps'], 2000) : '';
        $rpd = isset($_POST['rpd']) ? validTeks4($_POST['rpd'], 1000) : '';
        $rpo = isset($_POST['rpo']) ? validTeks4($_POST['rpo'], 1000) : '';
        $alergi = isset($_POST['alergi']) ? validTeks4($_POST['alergi'], 50) : '';
        
        // Pemeriksaan Fisik
        $kesadaran = isset($_POST['kesadaran']) ? validTeks4($_POST['kesadaran'], 20) : 'Compos Mentis';
        $status = isset($_POST['status']) ? validTeks4($_POST['status'], 50) : '';
        $td = isset($_POST['td']) ? validTeks4($_POST['td'], 8) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $rr = isset($_POST['rr']) ? validTeks4($_POST['rr'], 5) : '';
        $bb = isset($_POST['bb']) ? validTeks4($_POST['bb'], 5) : '';
        $nyeri = isset($_POST['nyeri']) ? validTeks4($_POST['nyeri'], 50) : '';
        $gcs = isset($_POST['gcs']) ? validTeks4($_POST['gcs'], 10) : '';
        $kepala = isset($_POST['kepala']) ? validTeks4($_POST['kepala'], 20) : 'Normal';
        $thoraks = isset($_POST['thoraks']) ? validTeks4($_POST['thoraks'], 20) : 'Normal';
        $abdomen = isset($_POST['abdomen']) ? validTeks4($_POST['abdomen'], 20) : 'Normal';
        $muskulos = isset($_POST['muskulos']) ? validTeks4($_POST['muskulos'], 20) : 'Normal';
        $lainnya = isset($_POST['lainnya']) ? validTeks4($_POST['lainnya'], 1000) : '';
        
        // Status Lokalis
        $ket_lokalis = isset($_POST['ket_lokalis']) ? validTeks4($_POST['ket_lokalis'], 5000) : '';
        
        // Pemeriksaan Penunjang
        $lab = isset($_POST['lab']) ? validTeks4($_POST['lab'], 500) : '';
        $rad = isset($_POST['rad']) ? validTeks4($_POST['rad'], 500) : '';
        $pemeriksaan = isset($_POST['pemeriksaan']) ? validTeks4($_POST['pemeriksaan'], 500) : '';
        
        // Diagnosis
        $diagnosis = isset($_POST['diagnosis']) ? validTeks4($_POST['diagnosis'], 500) : '';
        $diagnosis2 = isset($_POST['diagnosis2']) ? validTeks4($_POST['diagnosis2'], 500) : '';
        
        // Permasalahan & Tatalaksana
        $permasalahan = isset($_POST['permasalahan']) ? validTeks4($_POST['permasalahan'], 500) : '';
        $terapi = isset($_POST['terapi']) ? validTeks4($_POST['terapi'], 500) : '';
        $tindakan = isset($_POST['tindakan']) ? validTeks4($_POST['tindakan'], 500) : '';
        
        // Edukasi
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 500) : '';
        
        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }
        
        $query_check = "SELECT no_rawat FROM penilaian_medis_ralan_paru WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $query = "UPDATE penilaian_medis_ralan_paru SET 
                        tanggal = '$tanggal', kd_dokter = '$kd_dokter', anamnesis = '$anamnesis',
                        hubungan = '$hubungan', keluhan_utama = '$keluhan_utama', rps = '$rps',
                        rpd = '$rpd', rpo = '$rpo', alergi = '$alergi',
                        kesadaran = '$kesadaran', status = '$status',
                        td = '$td', nadi = '$nadi', suhu = '$suhu', rr = '$rr', bb = '$bb',
                        nyeri = '$nyeri', gcs = '$gcs',
                        kepala = '$kepala', thoraks = '$thoraks', abdomen = '$abdomen', muskulos = '$muskulos',
                        lainnya = '$lainnya', ket_lokalis = '$ket_lokalis',
                        lab = '$lab', rad = '$rad', pemeriksaan = '$pemeriksaan',
                        diagnosis = '$diagnosis', diagnosis2 = '$diagnosis2',
                        permasalahan = '$permasalahan', terapi = '$terapi', tindakan = '$tindakan',
                        edukasi = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Paru');
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis Paru berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            $query = "INSERT INTO penilaian_medis_ralan_paru (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, rpo, alergi,
                        kesadaran, status, td, nadi, suhu, rr, bb,
                        nyeri, gcs, kepala, thoraks, abdomen, muskulos,
                        lainnya, ket_lokalis,
                        lab, rad, pemeriksaan,
                        diagnosis, diagnosis2,
                        permasalahan, terapi, tindakan,
                        edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$rpo', '$alergi',
                        '$kesadaran', '$status', '$td', '$nadi', '$suhu', '$rr', '$bb',
                        '$nyeri', '$gcs', '$kepala', '$thoraks', '$abdomen', '$muskulos',
                        '$lainnya', '$ket_lokalis',
                        '$lab', '$rad', '$pemeriksaan',
                        '$diagnosis', '$diagnosis2',
                        '$permasalahan', '$terapi', '$tindakan',
                        '$edukasi'
                      )";
            
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Paru');
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis Paru berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action' => 'insert'
            ]);
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
// HAPUS PENILAIAN AWAL MEDIS PARU
// ========================================
if ($aksi === 'hapus_awalmedisparu') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_paru 
                      WHERE no_rawat = '$no_rawat' 
                      AND kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }
        
        $query_delete = "DELETE FROM penilaian_medis_ralan_paru WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis Paru');
        }
        
        insertTracker($query_delete);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data penilaian medis Paru berhasil dihapus',
            'no_rawat' => $no_rawat
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
// SIMPAN PENILAIAN AWAL MEDIS KULIT & KELAMIN
// ========================================
if ($aksi === 'simpan_awalmediskulitkelamin') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        // Riwayat Kesehatan
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if(strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis = isset($_POST['anamnesis']) ? validTeks4($_POST['anamnesis'], 20) : 'Autoanamnesis';
        $hubungan = isset($_POST['hubungan']) ? validTeks4($_POST['hubungan'], 30) : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps = isset($_POST['rps']) ? validTeks4($_POST['rps'], 2000) : '';
        $rpd = isset($_POST['rpd']) ? validTeks4($_POST['rpd'], 1000) : '';
        $rpo = isset($_POST['rpo']) ? validTeks4($_POST['rpo'], 1000) : '';
        $rpk = isset($_POST['rpk']) ? validTeks4($_POST['rpk'], 50) : '';
        
        // Pemeriksaan Fisik
        $kesadaran = isset($_POST['kesadaran']) ? validTeks4($_POST['kesadaran'], 20) : 'Compos Mentis';
        $status = isset($_POST['status']) ? validTeks4($_POST['status'], 15) : '';
        $td = isset($_POST['td']) ? validTeks4($_POST['td'], 8) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $rr = isset($_POST['rr']) ? validTeks4($_POST['rr'], 5) : '';
        $bb = isset($_POST['bb']) ? validTeks4($_POST['bb'], 5) : '';
        $nyeri = isset($_POST['nyeri']) ? validTeks4($_POST['nyeri'], 50) : '';
        $gcs = isset($_POST['gcs']) ? validTeks4($_POST['gcs'], 10) : '';
        
        // Status Lokalis
        $statusderma = isset($_POST['statusderma']) ? validTeks4($_POST['statusderma'], 1000) : '';
        
        // Pemeriksaan Penunjang
        $pemeriksaan = isset($_POST['pemeriksaan']) ? validTeks4($_POST['pemeriksaan'], 100) : '';
        
        // Diagnosis
        $diagnosis = isset($_POST['diagnosis']) ? validTeks4($_POST['diagnosis'], 500) : '';
        $diagnosis2 = isset($_POST['diagnosis2']) ? validTeks4($_POST['diagnosis2'], 500) : '';
        
        // Permasalahan & Tatalaksana
        $permasalahan = isset($_POST['permasalahan']) ? validTeks4($_POST['permasalahan'], 500) : '';
        $terapi = isset($_POST['terapi']) ? validTeks4($_POST['terapi'], 500) : '';
        $tindakan = isset($_POST['tindakan']) ? validTeks4($_POST['tindakan'], 100) : '';
        
        // Edukasi
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 500) : '';
        
        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }
        
        $query_check = "SELECT no_rawat FROM penilaian_medis_ralan_kulitdankelamin WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);
        
        if (mysqli_num_rows($result_check) > 0) {
            $query = "UPDATE penilaian_medis_ralan_kulitdankelamin SET 
                        tanggal = '$tanggal', kd_dokter = '$kd_dokter', anamnesis = '$anamnesis',
                        hubungan = '$hubungan', keluhan_utama = '$keluhan_utama', rps = '$rps',
                        rpd = '$rpd', rpo = '$rpo', rpk = '$rpk',
                        kesadaran = '$kesadaran', status = '$status',
                        td = '$td', nadi = '$nadi', suhu = '$suhu', rr = '$rr', bb = '$bb',
                        nyeri = '$nyeri', gcs = '$gcs',
                        statusderma = '$statusderma', pemeriksaan = '$pemeriksaan',
                        diagnosis = '$diagnosis', diagnosis2 = '$diagnosis2',
                        permasalahan = '$permasalahan', terapi = '$terapi', tindakan = '$tindakan',
                        edukasi = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";
            
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Kulit & Kelamin');
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis Kulit & Kelamin berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action' => 'update'
            ]);
            
        } else {
            $query = "INSERT INTO penilaian_medis_ralan_kulitdankelamin (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, rpo, rpk,
                        kesadaran, status, td, nadi, suhu, rr, bb,
                        nyeri, gcs,
                        statusderma, pemeriksaan,
                        diagnosis, diagnosis2,
                        permasalahan, terapi, tindakan,
                        edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$rpo', '$rpk',
                        '$kesadaran', '$status', '$td', '$nadi', '$suhu', '$rr', '$bb',
                        '$nyeri', '$gcs',
                        '$statusderma', '$pemeriksaan',
                        '$diagnosis', '$diagnosis2',
                        '$permasalahan', '$terapi', '$tindakan',
                        '$edukasi'
                      )";
            
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Kulit & Kelamin');
            insertTracker($query);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Data penilaian medis Kulit & Kelamin berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action' => 'insert'
            ]);
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
// HAPUS PENILAIAN AWAL MEDIS KULIT & KELAMIN
// ========================================
if ($aksi === 'hapus_awalmediskulitkelamin') {
    
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        
        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }
        
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_kulitdankelamin 
                      WHERE no_rawat = '$no_rawat' 
                      AND kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        
        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }
        
        $query_delete = "DELETE FROM penilaian_medis_ralan_kulitdankelamin WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query_delete);
        
        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis Kulit & Kelamin');
        }
        
        insertTracker($query_delete);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Data penilaian medis Kulit & Kelamin berhasil dihapus',
            'no_rawat' => $no_rawat
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
// SIMPAN PENILAIAN AWAL MEDIS PENYAKIT DALAM
// ========================================
if ($aksi === 'simpan_awalmedispenyakitdalam') {

    try {
        $no_rawat  = isset($_POST['no_rawat'])  ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        // Riwayat Kesehatan
        $tanggal       = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis     = isset($_POST['anamnesis'])     ? validTeks4($_POST['anamnesis'], 20)   : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? validTeks4($_POST['hubungan'], 30)    : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps           = isset($_POST['rps'])           ? validTeks4($_POST['rps'], 2000)       : '';
        $rpd           = isset($_POST['rpd'])           ? validTeks4($_POST['rpd'], 1000)       : '';
        $rpo           = isset($_POST['rpo'])           ? validTeks4($_POST['rpo'], 1000)       : '';
        $alergi        = isset($_POST['alergi'])        ? validTeks4($_POST['alergi'], 50)      : '';

        // Pemeriksaan Fisik
        $kondisi       = isset($_POST['kondisi'])       ? validTeks4($_POST['kondisi'], 500)    : '';
        $status        = isset($_POST['status'])        ? validTeks4($_POST['status'], 100)     : '';
        $td            = isset($_POST['td'])            ? validTeks4($_POST['td'], 8)           : '';
        $nadi          = isset($_POST['nadi'])          ? validTeks4($_POST['nadi'], 5)         : '';
        $suhu          = isset($_POST['suhu'])          ? validTeks4($_POST['suhu'], 5)         : '';
        $rr            = isset($_POST['rr'])            ? validTeks4($_POST['rr'], 5)           : '';
        $bb            = isset($_POST['bb'])            ? validTeks4($_POST['bb'], 5)           : '';
        $nyeri         = isset($_POST['nyeri'])         ? validTeks4($_POST['nyeri'], 50)       : '';
        $gcs           = isset($_POST['gcs'])           ? validTeks4($_POST['gcs'], 10)         : '';

        // Status Kelainan
        $kepala                 = isset($_POST['kepala'])                 ? validTeks4($_POST['kepala'], 20)                  : 'Normal';
        $keterangan_kepala      = isset($_POST['keterangan_kepala'])      ? validTeks4($_POST['keterangan_kepala'], 30)       : '';
        $thoraks                = isset($_POST['thoraks'])                ? validTeks4($_POST['thoraks'], 20)                 : 'Normal';
        $keterangan_thorak      = isset($_POST['keterangan_thorak'])      ? validTeks4($_POST['keterangan_thorak'], 30)      : '';
        $abdomen                = isset($_POST['abdomen'])                ? validTeks4($_POST['abdomen'], 20)                 : 'Normal';
        $keterangan_abdomen     = isset($_POST['keterangan_abdomen'])     ? validTeks4($_POST['keterangan_abdomen'], 30)     : '';
        $ekstremitas            = isset($_POST['ekstremitas'])            ? validTeks4($_POST['ekstremitas'], 20)             : 'Normal';
        $keterangan_ekstremitas = isset($_POST['keterangan_ekstremitas']) ? validTeks4($_POST['keterangan_ekstremitas'], 30) : '';
        $lainnya                = isset($_POST['lainnya'])                ? validTeks4($_POST['lainnya'], 1000)               : '';

        // Pemeriksaan Penunjang
        $lab           = isset($_POST['lab'])           ? validTeks4($_POST['lab'], 1000)       : '';
        $rad           = isset($_POST['rad'])           ? validTeks4($_POST['rad'], 1000)       : '';
        $penunjanglain = isset($_POST['penunjanglain']) ? validTeks4($_POST['penunjanglain'], 1000) : '';

        // Diagnosis
        $diagnosis     = isset($_POST['diagnosis'])     ? validTeks4($_POST['diagnosis'], 500)  : '';
        $diagnosis2    = isset($_POST['diagnosis2'])    ? validTeks4($_POST['diagnosis2'], 500) : '';

        // Permasalahan & Tatalaksana
        $permasalahan  = isset($_POST['permasalahan'])  ? validTeks4($_POST['permasalahan'], 500) : '';
        $terapi        = isset($_POST['terapi'])        ? validTeks4($_POST['terapi'], 500)      : '';
        $tindakan      = isset($_POST['tindakan'])      ? validTeks4($_POST['tindakan'], 200)    : '';

        // Edukasi
        $edukasi       = isset($_POST['edukasi'])       ? validTeks4($_POST['edukasi'], 500)    : '';

        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }

        $query_check  = "SELECT no_rawat FROM penilaian_medis_ralan_penyakit_dalam WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // ---- UPDATE ----
            $query = "UPDATE penilaian_medis_ralan_penyakit_dalam SET
                        tanggal                 = '$tanggal',
                        kd_dokter               = '$kd_dokter',
                        anamnesis               = '$anamnesis',
                        hubungan                = '$hubungan',
                        keluhan_utama           = '$keluhan_utama',
                        rps                     = '$rps',
                        rpd                     = '$rpd',
                        rpo                     = '$rpo',
                        alergi                  = '$alergi',
                        kondisi                 = '$kondisi',
                        status                  = '$status',
                        td                      = '$td',
                        nadi                    = '$nadi',
                        suhu                    = '$suhu',
                        rr                      = '$rr',
                        bb                      = '$bb',
                        nyeri                   = '$nyeri',
                        gcs                     = '$gcs',
                        kepala                  = '$kepala',
                        keterangan_kepala       = '$keterangan_kepala',
                        thoraks                 = '$thoraks',
                        keterangan_thorak       = '$keterangan_thorak',
                        abdomen                 = '$abdomen',
                        keterangan_abdomen      = '$keterangan_abdomen',
                        ekstremitas             = '$ekstremitas',
                        keterangan_ekstremitas  = '$keterangan_ekstremitas',
                        lainnya                 = '$lainnya',
                        lab                     = '$lab',
                        rad                     = '$rad',
                        penunjanglain           = '$penunjanglain',
                        diagnosis               = '$diagnosis',
                        diagnosis2              = '$diagnosis2',
                        permasalahan            = '$permasalahan',
                        terapi                  = '$terapi',
                        tindakan                = '$tindakan',
                        edukasi                 = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Penyakit Dalam');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Penyakit Dalam berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action'   => 'update'
            ]);

        } else {
            // ---- INSERT ----
            $query = "INSERT INTO penilaian_medis_ralan_penyakit_dalam (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, rpo, alergi,
                        kondisi, status, td, nadi, suhu, rr, bb, nyeri, gcs,
                        kepala, keterangan_kepala,
                        thoraks, keterangan_thorak,
                        abdomen, keterangan_abdomen,
                        ekstremitas, keterangan_ekstremitas,
                        lainnya,
                        lab, rad, penunjanglain,
                        diagnosis, diagnosis2,
                        permasalahan, terapi, tindakan,
                        edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$rpo', '$alergi',
                        '$kondisi', '$status', '$td', '$nadi', '$suhu', '$rr', '$bb', '$nyeri', '$gcs',
                        '$kepala', '$keterangan_kepala',
                        '$thoraks', '$keterangan_thorak',
                        '$abdomen', '$keterangan_abdomen',
                        '$ekstremitas', '$keterangan_ekstremitas',
                        '$lainnya',
                        '$lab', '$rad', '$penunjanglain',
                        '$diagnosis', '$diagnosis2',
                        '$permasalahan', '$terapi', '$tindakan',
                        '$edukasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Penyakit Dalam');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Penyakit Dalam berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action'   => 'insert'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// ========================================
// HAPUS PENILAIAN AWAL MEDIS PENYAKIT DALAM
// ========================================
if ($aksi === 'hapus_awalmedispenyakitdalam') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_penyakit_dalam
                      WHERE no_rawat   = '$no_rawat'
                      AND   kd_dokter  = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);

        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }

        $query_delete = "DELETE FROM penilaian_medis_ralan_penyakit_dalam WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);

        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis Penyakit Dalam');
        }

        insertTracker($query_delete);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Data penilaian medis Penyakit Dalam berhasil dihapus',
            'no_rawat' => $no_rawat
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// ========================================
// SIMPAN PENILAIAN AWAL MEDIS MATA
// ========================================
if ($aksi === 'simpan_awalmedismata') {

    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        // --- Riwayat Kesehatan ---
        $tanggal       = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis     = isset($_POST['anamnesis'])     ? validTeks4($_POST['anamnesis'],     20)   : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? validTeks4($_POST['hubungan'],      30)   : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps           = isset($_POST['rps'])           ? validTeks4($_POST['rps'],           2000) : '';
        $rpd           = isset($_POST['rpd'])           ? validTeks4($_POST['rpd'],           1000) : '';
        $rpo           = isset($_POST['rpo'])           ? validTeks4($_POST['rpo'],           1000) : '';
        $alergi        = isset($_POST['alergi'])        ? validTeks4($_POST['alergi'],        50)   : '';

        // --- Pemeriksaan Fisik ---
        $status = isset($_POST['status']) ? validTeks4($_POST['status'], 50)  : '';
        $td     = isset($_POST['td'])     ? validTeks4($_POST['td'],     8)   : '';
        $nadi   = isset($_POST['nadi'])   ? validTeks4($_POST['nadi'],   5)   : '';
        $rr     = isset($_POST['rr'])     ? validTeks4($_POST['rr'],     5)   : '';
        $suhu   = isset($_POST['suhu'])   ? validTeks4($_POST['suhu'],   5)   : '';
        $nyeri  = isset($_POST['nyeri'])  ? validTeks4($_POST['nyeri'],  50)  : '';
        $bb     = isset($_POST['bb'])     ? validTeks4($_POST['bb'],     5)   : '';

        // --- Status Oftalmologis ---
        $visuskanan   = isset($_POST['visuskanan'])   ? validTeks4($_POST['visuskanan'],   100) : '';
        $visuskiri    = isset($_POST['visuskiri'])    ? validTeks4($_POST['visuskiri'],    100) : '';
        $cckanan      = isset($_POST['cckanan'])      ? validTeks4($_POST['cckanan'],      100) : '';
        $cckiri       = isset($_POST['cckiri'])       ? validTeks4($_POST['cckiri'],       100) : '';
        $palkanan     = isset($_POST['palkanan'])     ? validTeks4($_POST['palkanan'],     100) : '';
        $palkiri      = isset($_POST['palkiri'])      ? validTeks4($_POST['palkiri'],      100) : '';
        $conkanan     = isset($_POST['conkanan'])     ? validTeks4($_POST['conkanan'],     100) : '';
        $conkiri      = isset($_POST['conkiri'])      ? validTeks4($_POST['conkiri'],      100) : '';
        $corneakanan  = isset($_POST['corneakanan'])  ? validTeks4($_POST['corneakanan'],  100) : '';
        $corneakiri   = isset($_POST['corneakiri'])   ? validTeks4($_POST['corneakiri'],   100) : '';
        $coakanan     = isset($_POST['coakanan'])     ? validTeks4($_POST['coakanan'],     100) : '';
        $coakiri      = isset($_POST['coakiri'])      ? validTeks4($_POST['coakiri'],      100) : '';
        $pupilkanan   = isset($_POST['pupilkanan'])   ? validTeks4($_POST['pupilkanan'],   100) : '';
        $pupilkiri    = isset($_POST['pupilkiri'])    ? validTeks4($_POST['pupilkiri'],    100) : '';
        $lensakanan   = isset($_POST['lensakanan'])   ? validTeks4($_POST['lensakanan'],   100) : '';
        $lensakiri    = isset($_POST['lensakiri'])    ? validTeks4($_POST['lensakiri'],    100) : '';
        $funduskanan  = isset($_POST['funduskanan'])  ? validTeks4($_POST['funduskanan'],  100) : '';
        $funduskiri   = isset($_POST['funduskiri'])   ? validTeks4($_POST['funduskiri'],   100) : '';
        $papilkanan   = isset($_POST['papilkanan'])   ? validTeks4($_POST['papilkanan'],   100) : '';
        $papilkiri    = isset($_POST['papilkiri'])    ? validTeks4($_POST['papilkiri'],    100) : '';
        $retinakanan  = isset($_POST['retinakanan'])  ? validTeks4($_POST['retinakanan'],  100) : '';
        $retinakiri   = isset($_POST['retinakiri'])   ? validTeks4($_POST['retinakiri'],   100) : '';
        $makulakanan  = isset($_POST['makulakanan'])  ? validTeks4($_POST['makulakanan'],  100) : '';
        $makulakiri   = isset($_POST['makulakiri'])   ? validTeks4($_POST['makulakiri'],   100) : '';
        $tiokanan     = isset($_POST['tiokanan'])     ? validTeks4($_POST['tiokanan'],     100) : '';
        $tiokiri      = isset($_POST['tiokiri'])      ? validTeks4($_POST['tiokiri'],      100) : '';
        $mbokanan     = isset($_POST['mbokanan'])     ? validTeks4($_POST['mbokanan'],     100) : '';
        $mbokiri      = isset($_POST['mbokiri'])      ? validTeks4($_POST['mbokiri'],      100) : '';

        // --- Pemeriksaan Penunjang ---
        $lab          = isset($_POST['lab'])          ? validTeks4($_POST['lab'],          2000) : '';
        $rad          = isset($_POST['rad'])          ? validTeks4($_POST['rad'],          2000) : '';
        $penunjang    = isset($_POST['penunjang'])    ? validTeks4($_POST['penunjang'],    2000) : '';
        $tes          = isset($_POST['tes'])          ? validTeks4($_POST['tes'],          2000) : '';
        $pemeriksaan  = isset($_POST['pemeriksaan'])  ? validTeks4($_POST['pemeriksaan'],  2000) : '';

        // --- Diagnosis ---
        $diagnosis    = isset($_POST['diagnosis'])    ? validTeks4($_POST['diagnosis'],    500) : '';
        $diagnosisbdg = isset($_POST['diagnosisbdg']) ? validTeks4($_POST['diagnosisbdg'], 500) : '';

        // --- Tatalaksana ---
        $permasalahan = isset($_POST['permasalahan']) ? validTeks4($_POST['permasalahan'], 2000) : '';
        $terapi       = isset($_POST['terapi'])       ? validTeks4($_POST['terapi'],       2000) : '';
        $tindakan     = isset($_POST['tindakan'])     ? validTeks4($_POST['tindakan'],     2000) : '';

        // --- Edukasi ---
        $edukasi      = isset($_POST['edukasi'])      ? validTeks4($_POST['edukasi'],      1000) : '';

        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }

        // Cek data existing
        $query_check  = "SELECT no_rawat FROM penilaian_medis_ralan_mata WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // ---- UPDATE ----
            $query = "UPDATE penilaian_medis_ralan_mata SET
                        tanggal       = '$tanggal',
                        kd_dokter     = '$kd_dokter',
                        anamnesis     = '$anamnesis',
                        hubungan      = '$hubungan',
                        keluhan_utama = '$keluhan_utama',
                        rps           = '$rps',
                        rpd           = '$rpd',
                        rpo           = '$rpo',
                        alergi        = '$alergi',
                        status        = '$status',
                        td            = '$td',
                        nadi          = '$nadi',
                        rr            = '$rr',
                        suhu          = '$suhu',
                        nyeri         = '$nyeri',
                        bb            = '$bb',
                        visuskanan    = '$visuskanan',
                        visuskiri     = '$visuskiri',
                        cckanan       = '$cckanan',
                        cckiri        = '$cckiri',
                        palkanan      = '$palkanan',
                        palkiri       = '$palkiri',
                        conkanan      = '$conkanan',
                        conkiri       = '$conkiri',
                        corneakanan   = '$corneakanan',
                        corneakiri    = '$corneakiri',
                        coakanan      = '$coakanan',
                        coakiri       = '$coakiri',
                        pupilkanan    = '$pupilkanan',
                        pupilkiri     = '$pupilkiri',
                        lensakanan    = '$lensakanan',
                        lensakiri     = '$lensakiri',
                        funduskanan   = '$funduskanan',
                        funduskiri    = '$funduskiri',
                        papilkanan    = '$papilkanan',
                        papilkiri     = '$papilkiri',
                        retinakanan   = '$retinakanan',
                        retinakiri    = '$retinakiri',
                        makulakanan   = '$makulakanan',
                        makulakiri    = '$makulakiri',
                        tiokanan      = '$tiokanan',
                        tiokiri       = '$tiokiri',
                        mbokanan      = '$mbokanan',
                        mbokiri       = '$mbokiri',
                        lab           = '$lab',
                        rad           = '$rad',
                        penunjang     = '$penunjang',
                        tes           = '$tes',
                        pemeriksaan   = '$pemeriksaan',
                        diagnosis     = '$diagnosis',
                        diagnosisbdg  = '$diagnosisbdg',
                        permasalahan  = '$permasalahan',
                        terapi        = '$terapi',
                        tindakan      = '$tindakan',
                        edukasi       = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Mata');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Mata berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action'   => 'update'
            ]);

        } else {
            // ---- INSERT ----
            $query = "INSERT INTO penilaian_medis_ralan_mata (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, rpo, alergi,
                        status, td, nadi, rr, suhu, nyeri, bb,
                        visuskanan, visuskiri,
                        cckanan,    cckiri,
                        palkanan,   palkiri,
                        conkanan,   conkiri,
                        corneakanan, corneakiri,
                        coakanan,   coakiri,
                        pupilkanan, pupilkiri,
                        lensakanan, lensakiri,
                        funduskanan, funduskiri,
                        papilkanan, papilkiri,
                        retinakanan, retinakiri,
                        makulakanan, makulakiri,
                        tiokanan,   tiokiri,
                        mbokanan,   mbokiri,
                        lab, rad, penunjang, tes, pemeriksaan,
                        diagnosis, diagnosisbdg,
                        permasalahan, terapi, tindakan,
                        edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$rpo', '$alergi',
                        '$status', '$td', '$nadi', '$rr', '$suhu', '$nyeri', '$bb',
                        '$visuskanan', '$visuskiri',
                        '$cckanan',    '$cckiri',
                        '$palkanan',   '$palkiri',
                        '$conkanan',   '$conkiri',
                        '$corneakanan','$corneakiri',
                        '$coakanan',   '$coakiri',
                        '$pupilkanan', '$pupilkiri',
                        '$lensakanan', '$lensakiri',
                        '$funduskanan','$funduskiri',
                        '$papilkanan', '$papilkiri',
                        '$retinakanan','$retinakiri',
                        '$makulakanan','$makulakiri',
                        '$tiokanan',   '$tiokiri',
                        '$mbokanan',   '$mbokiri',
                        '$lab', '$rad', '$penunjang', '$tes', '$pemeriksaan',
                        '$diagnosis', '$diagnosisbdg',
                        '$permasalahan', '$terapi', '$tindakan',
                        '$edukasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Mata');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Mata berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action'   => 'insert'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// ========================================
// HAPUS PENILAIAN AWAL MEDIS MATA
// ========================================
if ($aksi === 'hapus_awalmedismata') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        // Cek kepemilikan: hanya dokter pengisi yang boleh hapus
        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_mata
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);

        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }

        $query_delete = "DELETE FROM penilaian_medis_ralan_mata WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);

        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis Mata');
        }

        insertTracker($query_delete);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Data penilaian medis Mata berhasil dihapus',
            'no_rawat' => $no_rawat
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// ========================================
// SIMPAN PENILAIAN AWAL MEDIS BEDAH
// ========================================
if ($aksi === 'simpan_awalmedisbedah') {

    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        // Riwayat Kesehatan
        $tanggal       = isset($_POST['tanggal'])       ? $_POST['tanggal']                         : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal   = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis     = isset($_POST['anamnesis'])     ? validTeks4($_POST['anamnesis'],    20)    : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? validTeks4($_POST['hubungan'],     30)    : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'],2000)  : '';
        $rps           = isset($_POST['rps'])           ? validTeks4($_POST['rps'],          2000)  : '';
        $rpd           = isset($_POST['rpd'])           ? validTeks4($_POST['rpd'],          1000)  : '';
        $rpo           = isset($_POST['rpo'])           ? validTeks4($_POST['rpo'],          1000)  : '';
        $alergi        = isset($_POST['alergi'])        ? validTeks4($_POST['alergi'],       50)    : '';

        // Pemeriksaan Fisik
        $kesadaran  = isset($_POST['kesadaran'])  ? validTeks4($_POST['kesadaran'], 20)  : 'Compos Mentis';
        $status     = isset($_POST['status'])     ? validTeks4($_POST['status'],    50)  : '';
        $td         = isset($_POST['td'])         ? validTeks4($_POST['td'],         8)  : '';
        $nadi       = isset($_POST['nadi'])       ? validTeks4($_POST['nadi'],       5)  : '';
        $suhu       = isset($_POST['suhu'])       ? validTeks4($_POST['suhu'],       5)  : '';
        $rr         = isset($_POST['rr'])         ? validTeks4($_POST['rr'],         5)  : '';
        $bb         = isset($_POST['bb'])         ? validTeks4($_POST['bb'],         5)  : '';
        $nyeri      = isset($_POST['nyeri'])      ? validTeks4($_POST['nyeri'],      5)  : '';
        $gcs        = isset($_POST['gcs'])        ? validTeks4($_POST['gcs'],       10)  : '';

        // Status organ — enum('Normal','Abnormal','Tidak Diperiksa')
        $allowedOrgan = ['Normal', 'Abnormal', 'Tidak Diperiksa'];
        $kepala       = in_array($_POST['kepala']    ?? '', $allowedOrgan) ? $_POST['kepala']      : 'Normal';
        $thoraks      = in_array($_POST['thoraks']   ?? '', $allowedOrgan) ? $_POST['thoraks']     : 'Normal';
        $abdomen      = in_array($_POST['abdomen']   ?? '', $allowedOrgan) ? $_POST['abdomen']     : 'Normal';
        $ekstremitas  = in_array($_POST['ekstremitas']?? '',$allowedOrgan) ? $_POST['ekstremitas'] : 'Normal';
        $genetalia    = in_array($_POST['genetalia'] ?? '', $allowedOrgan) ? $_POST['genetalia']   : 'Normal';
        $columna      = in_array($_POST['columna']   ?? '', $allowedOrgan) ? $_POST['columna']     : 'Normal';
        $muskulos     = in_array($_POST['muskulos']  ?? '', $allowedOrgan) ? $_POST['muskulos']    : 'Normal';
        $lainnya      = isset($_POST['lainnya'])      ? validTeks4($_POST['lainnya'],     1000) : '';

        // Status Lokalis
        $ket_lokalis  = isset($_POST['ket_lokalis'])  ? validTeks4($_POST['ket_lokalis'], 5000) : '';

        // Pemeriksaan Penunjang
        $lab          = isset($_POST['lab'])          ? validTeks4($_POST['lab'],          500) : '';
        $rad          = isset($_POST['rad'])          ? validTeks4($_POST['rad'],          500) : '';
        $pemeriksaan  = isset($_POST['pemeriksaan'])  ? validTeks4($_POST['pemeriksaan'],  500) : '';

        // Diagnosis
        $diagnosis    = isset($_POST['diagnosis'])    ? validTeks4($_POST['diagnosis'],    500) : '';
        $diagnosis2   = isset($_POST['diagnosis2'])   ? validTeks4($_POST['diagnosis2'],   500) : '';

        // Permasalahan & Tatalaksana
        $permasalahan = isset($_POST['permasalahan']) ? validTeks4($_POST['permasalahan'], 500) : '';
        $terapi       = isset($_POST['terapi'])       ? validTeks4($_POST['terapi'],       500) : '';
        $tindakan     = isset($_POST['tindakan'])     ? validTeks4($_POST['tindakan'],     500) : '';

        // Edukasi
        $edukasi      = isset($_POST['edukasi'])      ? validTeks4($_POST['edukasi'],      500) : '';

        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }

        $query_check  = "SELECT no_rawat FROM penilaian_medis_ralan_bedah WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // ---- UPDATE ----
            $query = "UPDATE penilaian_medis_ralan_bedah SET
                        tanggal      = '$tanggal',      kd_dokter    = '$kd_dokter',
                        anamnesis    = '$anamnesis',    hubungan     = '$hubungan',
                        keluhan_utama= '$keluhan_utama',rps          = '$rps',
                        rpd          = '$rpd',          rpo          = '$rpo',
                        alergi       = '$alergi',
                        kesadaran    = '$kesadaran',    status       = '$status',
                        td           = '$td',           nadi         = '$nadi',
                        suhu         = '$suhu',         rr           = '$rr',
                        bb           = '$bb',           nyeri        = '$nyeri',
                        gcs          = '$gcs',
                        kepala       = '$kepala',       thoraks      = '$thoraks',
                        abdomen      = '$abdomen',      ekstremitas  = '$ekstremitas',
                        genetalia    = '$genetalia',    columna      = '$columna',
                        muskulos     = '$muskulos',     lainnya      = '$lainnya',
                        ket_lokalis  = '$ket_lokalis',
                        lab          = '$lab',          rad          = '$rad',
                        pemeriksaan  = '$pemeriksaan',
                        diagnosis    = '$diagnosis',    diagnosis2   = '$diagnosis2',
                        permasalahan = '$permasalahan', terapi       = '$terapi',
                        tindakan     = '$tindakan',     edukasi      = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Bedah');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Bedah berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action'   => 'update'
            ]);

        } else {
            // ---- INSERT ----
            $query = "INSERT INTO penilaian_medis_ralan_bedah (
                        no_rawat,      tanggal,       kd_dokter,    anamnesis,    hubungan,
                        keluhan_utama, rps,           rpd,          rpo,          alergi,
                        kesadaran,     status,        td,           nadi,         suhu,
                        rr,            bb,            nyeri,        gcs,
                        kepala,        thoraks,       abdomen,      ekstremitas,
                        genetalia,     columna,       muskulos,     lainnya,
                        ket_lokalis,
                        lab,           rad,           pemeriksaan,
                        diagnosis,     diagnosis2,
                        permasalahan,  terapi,        tindakan,     edukasi
                      ) VALUES (
                        '$no_rawat',    '$tanggal',     '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama','$rps',        '$rpd',       '$rpo',       '$alergi',
                        '$kesadaran',   '$status',      '$td',        '$nadi',      '$suhu',
                        '$rr',          '$bb',          '$nyeri',     '$gcs',
                        '$kepala',      '$thoraks',     '$abdomen',   '$ekstremitas',
                        '$genetalia',   '$columna',     '$muskulos',  '$lainnya',
                        '$ket_lokalis',
                        '$lab',         '$rad',         '$pemeriksaan',
                        '$diagnosis',   '$diagnosis2',
                        '$permasalahan','$terapi',      '$tindakan',  '$edukasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Bedah');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Bedah berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action'   => 'insert'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// ========================================
// HAPUS PENILAIAN AWAL MEDIS BEDAH
// ========================================
if ($aksi === 'hapus_awalmedisbedah') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_bedah
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);

        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }

        $query_delete = "DELETE FROM penilaian_medis_ralan_bedah WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);

        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis Bedah');
        }

        insertTracker($query_delete);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Data penilaian medis Bedah berhasil dihapus',
            'no_rawat' => $no_rawat
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}


// ========================================
// SIMPAN PENILAIAN AWAL MEDIS BEDAH MULUT
// ========================================
if ($aksi === 'simpan_awalmedisbedahmulut') {

    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        // ----- I. Riwayat Kesehatan -----
        $tanggal       = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis     = isset($_POST['anamnesis'])     ? validTeks4($_POST['anamnesis'],     20)   : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? validTeks4($_POST['hubungan'],      30)   : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps           = isset($_POST['rps'])           ? validTeks4($_POST['rps'],           2000) : '';
        $rpk           = isset($_POST['rpk'])           ? validTeks4($_POST['rpk'],           1000) : '';
        $alergi        = isset($_POST['alergi'])        ? validTeks4($_POST['alergi'],        50)   : '';

        // ----- II. Pemeriksaan Fisik -----
        $keadaan        = isset($_POST['keadaan'])        ? validTeks4($_POST['keadaan'],        10)  : 'Baik';
        $kesadaran      = isset($_POST['kesadaran'])      ? validTeks4($_POST['kesadaran'],      20)  : 'Compos Mentis';
        $nyeri          = isset($_POST['nyeri'])          ? validTeks4($_POST['nyeri'],          20)  : 'Tidak Nyeri';
        $td             = isset($_POST['td'])             ? validTeks4($_POST['td'],             8)   : '';
        $nadi           = isset($_POST['nadi'])           ? validTeks4($_POST['nadi'],           5)   : '';
        $suhu           = isset($_POST['suhu'])           ? validTeks4($_POST['suhu'],           5)   : '';
        $rr             = isset($_POST['rr'])             ? validTeks4($_POST['rr'],             5)   : '';
        $bb             = isset($_POST['bb'])             ? validTeks4($_POST['bb'],             5)   : '';
        $tb             = isset($_POST['tb'])             ? validTeks4($_POST['tb'],             5)   : '';
        $status_nutrisi = isset($_POST['status_nutrisi']) ? validTeks4($_POST['status_nutrisi'], 50)  : '';

        // ----- III. Status Kelainan -----
        $kulit               = isset($_POST['kulit'])               ? validTeks4($_POST['kulit'],               5)  : 'Tidak';
        $keterangan_kulit    = isset($_POST['keterangan_kulit'])    ? validTeks4($_POST['keterangan_kulit'],    30) : '';
        $kepala              = isset($_POST['kepala'])              ? validTeks4($_POST['kepala'],              5)  : 'Tidak';
        $keterangan_kepala   = isset($_POST['keterangan_kepala'])   ? validTeks4($_POST['keterangan_kepala'],   30) : '';
        $mata                = isset($_POST['mata'])                ? validTeks4($_POST['mata'],                5)  : 'Tidak';
        $keterangan_mata     = isset($_POST['keterangan_mata'])     ? validTeks4($_POST['keterangan_mata'],     30) : '';
        $leher               = isset($_POST['leher'])               ? validTeks4($_POST['leher'],               5)  : 'Tidak';
        $keterangan_leher    = isset($_POST['keterangan_leher'])    ? validTeks4($_POST['keterangan_leher'],    30) : '';
        $kelenjar            = isset($_POST['kelenjar'])            ? validTeks4($_POST['kelenjar'],            5)  : 'Tidak';
        $keterangan_kelenjar = isset($_POST['keterangan_kelenjar']) ? validTeks4($_POST['keterangan_kelenjar'], 30) : '';
        $dada                = isset($_POST['dada'])                ? validTeks4($_POST['dada'],                5)  : 'Tidak';
        $keterangan_dada     = isset($_POST['keterangan_dada'])     ? validTeks4($_POST['keterangan_dada'],     30) : '';
        $perut               = isset($_POST['perut'])               ? validTeks4($_POST['perut'],               5)  : 'Tidak';
        $keterangan_perut    = isset($_POST['keterangan_perut'])    ? validTeks4($_POST['keterangan_perut'],    30) : '';
        $ekstremitas             = isset($_POST['ekstremitas'])             ? validTeks4($_POST['ekstremitas'],             5)  : 'Tidak';
        $keterangan_ekstremitas  = isset($_POST['keterangan_ekstremitas'])  ? validTeks4($_POST['keterangan_ekstremitas'],  30) : '';

        // ----- IV. Status Lokalisata -----
        $wajah      = isset($_POST['wajah'])      ? validTeks4($_POST['wajah'],      1000) : '';
        $intra      = isset($_POST['intra'])      ? validTeks4($_POST['intra'],      1000) : '';
        $gigigeligi = isset($_POST['gigigeligi']) ? validTeks4($_POST['gigigeligi'], 1000) : '';

        // ----- V. Pemeriksaan Penunjang -----
        $lab      = isset($_POST['lab'])      ? validTeks4($_POST['lab'],      300) : '';
        $rad      = isset($_POST['rad'])      ? validTeks4($_POST['rad'],      300) : '';
        $penunjang = isset($_POST['penunjang']) ? validTeks4($_POST['penunjang'], 300) : '';

        // ----- VI. Diagnosis -----
        $diagnosis  = isset($_POST['diagnosis'])  ? validTeks4($_POST['diagnosis'],  500) : '';
        $diagnosis2 = isset($_POST['diagnosis2']) ? validTeks4($_POST['diagnosis2'], 500) : '';

        // ----- VII. Permasalahan & Tatalaksana -----
        $permasalahan = isset($_POST['permasalahan']) ? validTeks4($_POST['permasalahan'], 1000) : '';
        $terapi       = isset($_POST['terapi'])       ? validTeks4($_POST['terapi'],       1000) : '';
        $tindakan     = isset($_POST['tindakan'])     ? validTeks4($_POST['tindakan'],     1000) : '';

        // ----- VIII. Edukasi -----
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 1000) : '';

        // Validasi wajib
        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }
        if (empty($diagnosis)) {
            throw new Exception('Asesmen kerja (diagnosis) harus diisi');
        }

        // Cek existing
        $query_check  = "SELECT no_rawat FROM penilaian_medis_ralan_bedah_mulut WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // ---- UPDATE ----
            $query = "UPDATE penilaian_medis_ralan_bedah_mulut SET
                        tanggal              = '$tanggal',
                        kd_dokter            = '$kd_dokter',
                        anamnesis            = '$anamnesis',
                        hubungan             = '$hubungan',
                        keluhan_utama        = '$keluhan_utama',
                        rps                  = '$rps',
                        rpk                  = '$rpk',
                        alergi               = '$alergi',
                        keadaan              = '$keadaan',
                        kesadaran            = '$kesadaran',
                        nyeri                = '$nyeri',
                        td                   = '$td',
                        nadi                 = '$nadi',
                        suhu                 = '$suhu',
                        rr                   = '$rr',
                        bb                   = '$bb',
                        tb                   = '$tb',
                        status_nutrisi       = '$status_nutrisi',
                        kulit                = '$kulit',
                        keterangan_kulit     = '$keterangan_kulit',
                        kepala               = '$kepala',
                        keterangan_kepala    = '$keterangan_kepala',
                        mata                 = '$mata',
                        keterangan_mata      = '$keterangan_mata',
                        leher                = '$leher',
                        keterangan_leher     = '$keterangan_leher',
                        kelenjar             = '$kelenjar',
                        keterangan_kelenjar  = '$keterangan_kelenjar',
                        dada                 = '$dada',
                        keterangan_dada      = '$keterangan_dada',
                        perut                = '$perut',
                        keterangan_perut     = '$keterangan_perut',
                        ekstremitas          = '$ekstremitas',
                        keterangan_ekstremitas = '$keterangan_ekstremitas',
                        wajah                = '$wajah',
                        intra                = '$intra',
                        gigigeligi           = '$gigigeligi',
                        lab                  = '$lab',
                        rad                  = '$rad',
                        penunjang            = '$penunjang',
                        diagnosis            = '$diagnosis',
                        diagnosis2           = '$diagnosis2',
                        permasalahan         = '$permasalahan',
                        terapi               = '$terapi',
                        tindakan             = '$tindakan',
                        edukasi              = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Bedah Mulut');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Bedah Mulut berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action'   => 'update'
            ]);

        } else {
            // ---- INSERT ----
            $query = "INSERT INTO penilaian_medis_ralan_bedah_mulut (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpk, alergi,
                        keadaan, kesadaran, nyeri,
                        td, nadi, suhu, rr, bb, tb, status_nutrisi,
                        kulit, keterangan_kulit,
                        kepala, keterangan_kepala,
                        mata, keterangan_mata,
                        leher, keterangan_leher,
                        kelenjar, keterangan_kelenjar,
                        dada, keterangan_dada,
                        perut, keterangan_perut,
                        ekstremitas, keterangan_ekstremitas,
                        wajah, intra, gigigeligi,
                        lab, rad, penunjang,
                        diagnosis, diagnosis2,
                        permasalahan, terapi, tindakan,
                        edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpk', '$alergi',
                        '$keadaan', '$kesadaran', '$nyeri',
                        '$td', '$nadi', '$suhu', '$rr', '$bb', '$tb', '$status_nutrisi',
                        '$kulit', '$keterangan_kulit',
                        '$kepala', '$keterangan_kepala',
                        '$mata', '$keterangan_mata',
                        '$leher', '$keterangan_leher',
                        '$kelenjar', '$keterangan_kelenjar',
                        '$dada', '$keterangan_dada',
                        '$perut', '$keterangan_perut',
                        '$ekstremitas', '$keterangan_ekstremitas',
                        '$wajah', '$intra', '$gigigeligi',
                        '$lab', '$rad', '$penunjang',
                        '$diagnosis', '$diagnosis2',
                        '$permasalahan', '$terapi', '$tindakan',
                        '$edukasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Bedah Mulut');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Bedah Mulut berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action'   => 'insert'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// ========================================
// HAPUS PENILAIAN AWAL MEDIS BEDAH MULUT
// ========================================
if ($aksi === 'hapus_awalmedisbedahmulut') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        // Cek kepemilikan data
        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_bedah_mulut
                      WHERE no_rawat = '$no_rawat'
                      AND kd_dokter  = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);

        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }

        $query_delete = "DELETE FROM penilaian_medis_ralan_bedah_mulut WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);

        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis Bedah Mulut');
        }

        insertTracker($query_delete);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Data penilaian medis Bedah Mulut berhasil dihapus',
            'no_rawat' => $no_rawat
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// ========================================
// SIMPAN PENILAIAN AWAL MEDIS KEBIDANAN & KANDUNGAN
// ========================================
if ($aksi === 'simpan_awalmediskebidananralan') {

    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        // ── Riwayat Kesehatan ──────────────────────────────────
        $tanggal       = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis     = isset($_POST['anamnesis'])     ? validTeks4($_POST['anamnesis'],     20)   : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? validTeks4($_POST['hubungan'],      100)  : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps           = isset($_POST['rps'])           ? validTeks4($_POST['rps'],           2000) : '';
        $rpd           = isset($_POST['rpd'])           ? validTeks4($_POST['rpd'],           1000) : '';
        $rpk           = isset($_POST['rpk'])           ? validTeks4($_POST['rpk'],           1000) : '';
        $rpo           = isset($_POST['rpo'])           ? validTeks4($_POST['rpo'],           1000) : '';
        $alergi        = isset($_POST['alergi'])        ? validTeks4($_POST['alergi'],        100)  : '';

        // ── Pemeriksaan Fisik ──────────────────────────────────
        $keadaan   = isset($_POST['keadaan'])   ? validTeks4($_POST['keadaan'],   20)  : 'Sehat';
        $kesadaran = isset($_POST['kesadaran']) ? validTeks4($_POST['kesadaran'], 20)  : 'Compos Mentis';
        $gcs       = isset($_POST['gcs'])       ? validTeks4($_POST['gcs'],       10)  : '';
        $tb        = isset($_POST['tb'])        ? validTeks4($_POST['tb'],        5)   : '';
        $bb        = isset($_POST['bb'])        ? validTeks4($_POST['bb'],        5)   : '';
        $td        = isset($_POST['td'])        ? validTeks4($_POST['td'],        8)   : '';
        $nadi      = isset($_POST['nadi'])      ? validTeks4($_POST['nadi'],      5)   : '';
        $rr        = isset($_POST['rr'])        ? validTeks4($_POST['rr'],        5)   : '';
        $suhu      = isset($_POST['suhu'])      ? validTeks4($_POST['suhu'],      5)   : '';
        $spo       = isset($_POST['spo'])       ? validTeks4($_POST['spo'],       5)   : '';

        // ── Pemeriksaan Fisik Sistematis ──────────────────────
        $kepala      = isset($_POST['kepala'])      ? validTeks4($_POST['kepala'],      20) : 'Normal';
        $mata        = isset($_POST['mata'])        ? validTeks4($_POST['mata'],        20) : 'Normal';
        $gigi        = isset($_POST['gigi'])        ? validTeks4($_POST['gigi'],        20) : 'Normal';
        $tht         = isset($_POST['tht'])         ? validTeks4($_POST['tht'],         20) : 'Normal';
        $thoraks     = isset($_POST['thoraks'])     ? validTeks4($_POST['thoraks'],     20) : 'Normal';
        $abdomen     = isset($_POST['abdomen'])     ? validTeks4($_POST['abdomen'],     20) : 'Normal';
        $genital     = isset($_POST['genital'])     ? validTeks4($_POST['genital'],     20) : 'Normal';
        $ekstremitas = isset($_POST['ekstremitas']) ? validTeks4($_POST['ekstremitas'], 20) : 'Normal';
        $kulit       = isset($_POST['kulit'])       ? validTeks4($_POST['kulit'],       20) : 'Normal';
        $ket_fisik   = isset($_POST['ket_fisik'])   ? validTeks4($_POST['ket_fisik'],   2000) : '';

        // ── Status Obstetri / Ginekologi ──────────────────────
        $tfu       = isset($_POST['tfu'])       ? validTeks4($_POST['tfu'],       10)   : '';
        $tbj       = isset($_POST['tbj'])       ? validTeks4($_POST['tbj'],       10)   : '';
        $his       = isset($_POST['his'])       ? validTeks4($_POST['his'],       10)   : '';
        $kontraksi = isset($_POST['kontraksi']) ? validTeks4($_POST['kontraksi'], 10)   : 'Ada';
        $djj       = isset($_POST['djj'])       ? validTeks4($_POST['djj'],       10)   : '';
        $inspeksi  = isset($_POST['inspeksi'])  ? validTeks4($_POST['inspeksi'],  2000) : '';
        $inspekulo = isset($_POST['inspekulo']) ? validTeks4($_POST['inspekulo'], 2000) : '';
        $vt        = isset($_POST['vt'])        ? validTeks4($_POST['vt'],        2000) : '';
        $rt        = isset($_POST['rt'])        ? validTeks4($_POST['rt'],        2000) : '';

        // ── Pemeriksaan Penunjang ──────────────────────────────
        $ultra  = isset($_POST['ultra'])  ? validTeks4($_POST['ultra'],  2000) : '';
        $kardio = isset($_POST['kardio']) ? validTeks4($_POST['kardio'], 2000) : '';
        $lab    = isset($_POST['lab'])    ? validTeks4($_POST['lab'],    2000) : '';

        // ── Diagnosis & Tatalaksana ───────────────────────────
        $diagnosis = isset($_POST['diagnosis']) ? validTeks4($_POST['diagnosis'], 500)  : '';
        $tata      = isset($_POST['tata'])      ? validTeks4($_POST['tata'],      2000) : '';
        $konsul    = isset($_POST['konsul'])    ? validTeks4($_POST['konsul'],    1000) : '';

        // ── Validasi wajib ────────────────────────────────────
        if (empty($keluhan_utama)) {
            throw new Exception('Keluhan utama harus diisi');
        }
        if (empty($diagnosis)) {
            throw new Exception('Diagnosis / Asesmen harus diisi');
        }

        // ── Cek existing data ─────────────────────────────────
        $query_check  = "SELECT no_rawat FROM penilaian_medis_ralan_kandungan WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // ── UPDATE ────────────────────────────────────────
            $query = "UPDATE penilaian_medis_ralan_kandungan SET
                        tanggal    = '$tanggal',
                        kd_dokter  = '$kd_dokter',
                        anamnesis  = '$anamnesis',
                        hubungan   = '$hubungan',
                        keluhan_utama = '$keluhan_utama',
                        rps        = '$rps',
                        rpd        = '$rpd',
                        rpk        = '$rpk',
                        rpo        = '$rpo',
                        alergi     = '$alergi',
                        keadaan    = '$keadaan',
                        kesadaran  = '$kesadaran',
                        gcs        = '$gcs',
                        tb         = '$tb',
                        bb         = '$bb',
                        td         = '$td',
                        nadi       = '$nadi',
                        rr         = '$rr',
                        suhu       = '$suhu',
                        spo        = '$spo',
                        kepala     = '$kepala',
                        mata       = '$mata',
                        gigi       = '$gigi',
                        tht        = '$tht',
                        thoraks    = '$thoraks',
                        abdomen    = '$abdomen',
                        genital    = '$genital',
                        ekstremitas= '$ekstremitas',
                        kulit      = '$kulit',
                        ket_fisik  = '$ket_fisik',
                        tfu        = '$tfu',
                        tbj        = '$tbj',
                        his        = '$his',
                        kontraksi  = '$kontraksi',
                        djj        = '$djj',
                        inspeksi   = '$inspeksi',
                        inspekulo  = '$inspekulo',
                        vt         = '$vt',
                        rt         = '$rt',
                        ultra      = '$ultra',
                        kardio     = '$kardio',
                        lab        = '$lab',
                        diagnosis  = '$diagnosis',
                        tata       = '$tata',
                        konsul     = '$konsul'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Kebidanan & Kandungan');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Kebidanan & Kandungan berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action'   => 'update'
            ]);

        } else {
            // ── INSERT ────────────────────────────────────────
            $query = "INSERT INTO penilaian_medis_ralan_kandungan (
                        no_rawat, tanggal, kd_dokter,
                        anamnesis, hubungan, keluhan_utama,
                        rps, rpd, rpk, rpo, alergi,
                        keadaan, kesadaran, gcs,
                        tb, bb, td, nadi, rr, suhu, spo,
                        kepala, mata, gigi, tht, thoraks,
                        abdomen, genital, ekstremitas, kulit, ket_fisik,
                        tfu, tbj, his, kontraksi, djj,
                        inspeksi, inspekulo, vt, rt,
                        ultra, kardio, lab,
                        diagnosis, tata, konsul
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter',
                        '$anamnesis', '$hubungan', '$keluhan_utama',
                        '$rps', '$rpd', '$rpk', '$rpo', '$alergi',
                        '$keadaan', '$kesadaran', '$gcs',
                        '$tb', '$bb', '$td', '$nadi', '$rr', '$suhu', '$spo',
                        '$kepala', '$mata', '$gigi', '$tht', '$thoraks',
                        '$abdomen', '$genital', '$ekstremitas', '$kulit', '$ket_fisik',
                        '$tfu', '$tbj', '$his', '$kontraksi', '$djj',
                        '$inspeksi', '$inspekulo', '$vt', '$rt',
                        '$ultra', '$kardio', '$lab',
                        '$diagnosis', '$tata', '$konsul'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Kebidanan & Kandungan');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data penilaian medis Kebidanan & Kandungan berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action'   => 'insert'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// ========================================
// HAPUS PENILAIAN AWAL MEDIS KEBIDANAN & KANDUNGAN
// ========================================
if ($aksi === 'hapus_awalmediskebidananralan') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        // Cek ownership
        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_kandungan
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);

        if (mysqli_num_rows($result_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }

        $query_delete = "DELETE FROM penilaian_medis_ralan_kandungan WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);

        if (!$result) {
            throw new Exception('Gagal menghapus data penilaian medis Kebidanan & Kandungan');
        }

        insertTracker($query_delete);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Data penilaian medis Kebidanan & Kandungan berhasil dihapus',
            'no_rawat' => $no_rawat
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }

    exit();
}

// ========================================
// SIMPAN PENILAIAN AWAL MEDIS ORTHOPEDI
// ========================================
if ($aksi === 'simpan_awalmedisorthopedi') {

    try {
        $no_rawat  = isset($_POST['no_rawat'])  ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Riwayat Kesehatan
        $tanggal       = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis     = isset($_POST['anamnesis'])     ? validTeks4($_POST['anamnesis'], 20)      : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? validTeks4($_POST['hubungan'], 30)       : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000): '';
        $rps           = isset($_POST['rps'])           ? validTeks4($_POST['rps'], 2000)          : '';
        $rpd           = isset($_POST['rpd'])           ? validTeks4($_POST['rpd'], 1000)          : '';
        $rpo           = isset($_POST['rpo'])           ? validTeks4($_POST['rpo'], 1000)          : '';
        $alergi        = isset($_POST['alergi'])        ? validTeks4($_POST['alergi'], 50)         : '';

        if (empty($keluhan_utama)) throw new Exception('Keluhan utama harus diisi');

        // Pemeriksaan Fisik
        $kesadaran = isset($_POST['kesadaran']) ? validTeks4($_POST['kesadaran'], 20) : 'Compos Mentis';
        $status    = isset($_POST['status'])    ? validTeks4($_POST['status'], 50)    : '';
        $td        = isset($_POST['td'])        ? validTeks4($_POST['td'], 8)         : '';
        $nadi      = isset($_POST['nadi'])      ? validTeks4($_POST['nadi'], 5)       : '';
        $suhu      = isset($_POST['suhu'])      ? validTeks4($_POST['suhu'], 5)       : '';
        $rr        = isset($_POST['rr'])        ? validTeks4($_POST['rr'], 5)         : '';
        $bb        = isset($_POST['bb'])        ? validTeks4($_POST['bb'], 5)         : '';
        $nyeri     = isset($_POST['nyeri'])     ? validTeks4($_POST['nyeri'], 5)      : '';
        $gcs       = isset($_POST['gcs'])       ? validTeks4($_POST['gcs'], 10)       : '';

        // Status Organ
        $kepala      = isset($_POST['kepala'])      ? validTeks4($_POST['kepala'], 20)      : 'Normal';
        $thoraks     = isset($_POST['thoraks'])     ? validTeks4($_POST['thoraks'], 20)     : 'Normal';
        $abdomen     = isset($_POST['abdomen'])     ? validTeks4($_POST['abdomen'], 20)     : 'Normal';
        $ekstremitas = isset($_POST['ekstremitas']) ? validTeks4($_POST['ekstremitas'], 20) : 'Normal';
        $genetalia   = isset($_POST['genetalia'])   ? validTeks4($_POST['genetalia'], 20)   : 'Normal';
        $columna     = isset($_POST['columna'])     ? validTeks4($_POST['columna'], 20)     : 'Normal';
        $muskulos    = isset($_POST['muskulos'])    ? validTeks4($_POST['muskulos'], 20)    : 'Normal';
        $lainnya     = isset($_POST['lainnya'])     ? validTeks4($_POST['lainnya'], 1000)   : '';

        // Status Lokalis
        $ket_lokalis = isset($_POST['ket_lokalis']) ? validTeks4($_POST['ket_lokalis'], 5000) : '';

        // Pemeriksaan Penunjang
        $lab         = isset($_POST['lab'])         ? validTeks4($_POST['lab'], 500)         : '';
        $rad         = isset($_POST['rad'])         ? validTeks4($_POST['rad'], 500)         : '';
        $pemeriksaan = isset($_POST['pemeriksaan']) ? validTeks4($_POST['pemeriksaan'], 500) : '';

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
        $query_check  = "SELECT no_rawat FROM penilaian_medis_ralan_orthopedi WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // ---- UPDATE ----
            $query = "UPDATE penilaian_medis_ralan_orthopedi SET
                        tanggal      = '$tanggal',
                        kd_dokter    = '$kd_dokter',
                        anamnesis    = '$anamnesis',
                        hubungan     = '$hubungan',
                        keluhan_utama= '$keluhan_utama',
                        rps          = '$rps',
                        rpd          = '$rpd',
                        rpo          = '$rpo',
                        alergi       = '$alergi',
                        kesadaran    = '$kesadaran',
                        status       = '$status',
                        td           = '$td',
                        nadi         = '$nadi',
                        suhu         = '$suhu',
                        rr           = '$rr',
                        bb           = '$bb',
                        nyeri        = '$nyeri',
                        gcs          = '$gcs',
                        kepala       = '$kepala',
                        thoraks      = '$thoraks',
                        abdomen      = '$abdomen',
                        ekstremitas  = '$ekstremitas',
                        genetalia    = '$genetalia',
                        columna      = '$columna',
                        muskulos     = '$muskulos',
                        lainnya      = '$lainnya',
                        ket_lokalis  = '$ket_lokalis',
                        lab          = '$lab',
                        rad          = '$rad',
                        pemeriksaan  = '$pemeriksaan',
                        diagnosis    = '$diagnosis',
                        diagnosis2   = '$diagnosis2',
                        permasalahan = '$permasalahan',
                        terapi       = '$terapi',
                        tindakan     = '$tindakan',
                        edukasi      = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Orthopedi');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data penilaian medis Orthopedi berhasil diupdate','no_rawat'=>$no_rawat,'action'=>'update']);

        } else {
            // ---- INSERT ----
            $query = "INSERT INTO penilaian_medis_ralan_orthopedi (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, rpo, alergi,
                        kesadaran, status, td, nadi, suhu, rr, bb, nyeri, gcs,
                        kepala, thoraks, abdomen, ekstremitas, genetalia, columna, muskulos,
                        lainnya, ket_lokalis,
                        lab, rad, pemeriksaan,
                        diagnosis, diagnosis2,
                        permasalahan, terapi, tindakan, edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$rpo', '$alergi',
                        '$kesadaran', '$status', '$td', '$nadi', '$suhu', '$rr', '$bb', '$nyeri', '$gcs',
                        '$kepala', '$thoraks', '$abdomen', '$ekstremitas', '$genetalia', '$columna', '$muskulos',
                        '$lainnya', '$ket_lokalis',
                        '$lab', '$rad', '$pemeriksaan',
                        '$diagnosis', '$diagnosis2',
                        '$permasalahan', '$terapi', '$tindakan', '$edukasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Orthopedi');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data penilaian medis Orthopedi berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert']);
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================
// HAPUS PENILAIAN AWAL MEDIS ORTHOPEDI
// ========================================
if ($aksi === 'hapus_awalmedisorthopedi') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_orthopedi
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);

        if (mysqli_num_rows($result_cek) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');

        $query_delete = "DELETE FROM penilaian_medis_ralan_orthopedi WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);

        if (!$result) throw new Exception('Gagal menghapus data penilaian medis Orthopedi');

        insertTracker($query_delete);

        echo json_encode(['status'=>'success','message'=>'Data penilaian medis Orthopedi berhasil dihapus','no_rawat'=>$no_rawat]);

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================
// SIMPAN PENILAIAN AWAL MEDIS NEUROLOGI
// ========================================
if ($aksi === 'simpan_awalmedisneurologi') {

    try {
        $no_rawat  = isset($_POST['no_rawat'])  ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Riwayat Kesehatan
        $tanggal       = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis     = isset($_POST['anamnesis'])     ? validTeks4($_POST['anamnesis'], 20)      : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? validTeks4($_POST['hubungan'], 30)       : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000): '';
        $rps           = isset($_POST['rps'])           ? validTeks4($_POST['rps'], 2000)          : '';
        $rpd           = isset($_POST['rpd'])           ? validTeks4($_POST['rpd'], 1000)          : '';
        $rpo           = isset($_POST['rpo'])           ? validTeks4($_POST['rpo'], 1000)          : '';
        $alergi        = isset($_POST['alergi'])        ? validTeks4($_POST['alergi'], 50)         : '';

        if (empty($keluhan_utama)) throw new Exception('Keluhan utama harus diisi');

        // Pemeriksaan Fisik
        $kesadaran = isset($_POST['kesadaran']) ? validTeks4($_POST['kesadaran'], 20)  : 'Compos Mentis';
        $status_raw = isset($_POST['status']) ? trim($_POST['status']) : '';
        $status     = in_array($status_raw, ['Skor < 2', 'Skor >= 2']) ? $status_raw : 'Skor < 2';
        $td        = isset($_POST['td'])        ? validTeks4($_POST['td'], 8)          : '';
        $nadi      = isset($_POST['nadi'])      ? validTeks4($_POST['nadi'], 5)        : '';
        $suhu      = isset($_POST['suhu'])      ? validTeks4($_POST['suhu'], 5)        : '';
        $rr        = isset($_POST['rr'])        ? validTeks4($_POST['rr'], 5)          : '';
        $bb        = isset($_POST['bb'])        ? validTeks4($_POST['bb'], 5)          : '';
        $nyeri     = isset($_POST['nyeri'])     ? validTeks4($_POST['nyeri'], 50)      : '';
        $gcs       = isset($_POST['gcs'])       ? validTeks4($_POST['gcs'], 10)        : '';

        // Status Kelainan
        $kepala                 = isset($_POST['kepala'])                 ? validTeks4($_POST['kepala'], 20)                  : 'Normal';
        $keterangan_kepala      = isset($_POST['keterangan_kepala'])      ? validTeks4($_POST['keterangan_kepala'], 30)       : '';
        $thoraks                = isset($_POST['thoraks'])                ? validTeks4($_POST['thoraks'], 20)                 : 'Normal';
        $keterangan_thoraks     = isset($_POST['keterangan_thoraks'])     ? validTeks4($_POST['keterangan_thoraks'], 30)     : '';
        $abdomen                = isset($_POST['abdomen'])                ? validTeks4($_POST['abdomen'], 20)                 : 'Normal';
        $keterangan_abdomen     = isset($_POST['keterangan_abdomen'])     ? validTeks4($_POST['keterangan_abdomen'], 30)     : '';
        $ekstremitas            = isset($_POST['ekstremitas'])            ? validTeks4($_POST['ekstremitas'], 20)             : 'Normal';
        $keterangan_ekstremitas = isset($_POST['keterangan_ekstremitas']) ? validTeks4($_POST['keterangan_ekstremitas'], 30) : '';
        $columna                = isset($_POST['columna'])                ? validTeks4($_POST['columna'], 20)                 : 'Normal';
        $keterangan_columna     = isset($_POST['keterangan_columna'])     ? validTeks4($_POST['keterangan_columna'], 30)     : '';
        $muskulos               = isset($_POST['muskulos'])               ? validTeks4($_POST['muskulos'], 20)                : 'Normal';
        $keterangan_muskulos    = isset($_POST['keterangan_muskulos'])    ? validTeks4($_POST['keterangan_muskulos'], 30)    : '';
        $lainnya                = isset($_POST['lainnya'])                ? validTeks4($_POST['lainnya'], 1000)               : '';

        // Pemeriksaan Penunjang
        $lab           = isset($_POST['lab'])           ? validTeks4($_POST['lab'], 500)          : '';
        $rad           = isset($_POST['rad'])           ? validTeks4($_POST['rad'], 500)          : '';
        $penunjanglain = isset($_POST['penunjanglain']) ? validTeks4($_POST['penunjanglain'], 500) : '';

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
        $query_check  = "SELECT no_rawat FROM penilaian_medis_ralan_neurologi WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // ---- UPDATE ----
            $query = "UPDATE penilaian_medis_ralan_neurologi SET
                        tanggal                 = '$tanggal',
                        kd_dokter               = '$kd_dokter',
                        anamnesis               = '$anamnesis',
                        hubungan                = '$hubungan',
                        keluhan_utama           = '$keluhan_utama',
                        rps                     = '$rps',
                        rpd                     = '$rpd',
                        rpo                     = '$rpo',
                        alergi                  = '$alergi',
                        kesadaran               = '$kesadaran',
                        status                  = '$status',
                        td                      = '$td',
                        nadi                    = '$nadi',
                        suhu                    = '$suhu',
                        rr                      = '$rr',
                        bb                      = '$bb',
                        nyeri                   = '$nyeri',
                        gcs                     = '$gcs',
                        kepala                  = '$kepala',
                        keterangan_kepala       = '$keterangan_kepala',
                        thoraks                 = '$thoraks',
                        keterangan_thoraks      = '$keterangan_thoraks',
                        abdomen                 = '$abdomen',
                        keterangan_abdomen      = '$keterangan_abdomen',
                        ekstremitas             = '$ekstremitas',
                        keterangan_ekstremitas  = '$keterangan_ekstremitas',
                        columna                 = '$columna',
                        keterangan_columna      = '$keterangan_columna',
                        muskulos                = '$muskulos',
                        keterangan_muskulos     = '$keterangan_muskulos',
                        lainnya                 = '$lainnya',
                        lab                     = '$lab',
                        rad                     = '$rad',
                        penunjanglain           = '$penunjanglain',
                        diagnosis               = '$diagnosis',
                        diagnosis2              = '$diagnosis2',
                        permasalahan            = '$permasalahan',
                        terapi                  = '$terapi',
                        tindakan                = '$tindakan',
                        edukasi                 = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Neurologi');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data penilaian medis Neurologi berhasil diupdate','no_rawat'=>$no_rawat,'action'=>'update']);

        } else {
            // ---- INSERT ----
            $query = "INSERT INTO penilaian_medis_ralan_neurologi (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, rpo, alergi,
                        kesadaran, status, td, nadi, suhu, rr, bb, nyeri, gcs,
                        kepala, keterangan_kepala,
                        thoraks, keterangan_thoraks,
                        abdomen, keterangan_abdomen,
                        ekstremitas, keterangan_ekstremitas,
                        columna, keterangan_columna,
                        muskulos, keterangan_muskulos,
                        lainnya,
                        lab, rad, penunjanglain,
                        diagnosis, diagnosis2,
                        permasalahan, terapi, tindakan, edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$rpo', '$alergi',
                        '$kesadaran', '$status', '$td', '$nadi', '$suhu', '$rr', '$bb', '$nyeri', '$gcs',
                        '$kepala', '$keterangan_kepala',
                        '$thoraks', '$keterangan_thoraks',
                        '$abdomen', '$keterangan_abdomen',
                        '$ekstremitas', '$keterangan_ekstremitas',
                        '$columna', '$keterangan_columna',
                        '$muskulos', '$keterangan_muskulos',
                        '$lainnya',
                        '$lab', '$rad', '$penunjanglain',
                        '$diagnosis', '$diagnosis2',
                        '$permasalahan', '$terapi', '$tindakan', '$edukasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Neurologi');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data penilaian medis Neurologi berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert']);
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================
// HAPUS PENILAIAN AWAL MEDIS NEUROLOGI
// ========================================
if ($aksi === 'hapus_awalmedisneurologi') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_neurologi
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);

        if (mysqli_num_rows($result_cek) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');

        $query_delete = "DELETE FROM penilaian_medis_ralan_neurologi WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);

        if (!$result) throw new Exception('Gagal menghapus data penilaian medis Neurologi');

        insertTracker($query_delete);

        echo json_encode(['status'=>'success','message'=>'Data penilaian medis Neurologi berhasil dihapus','no_rawat'=>$no_rawat]);

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================================
// SIMPAN PENILAIAN AWAL MEDIS FISIK & REHABILITASI
// ========================================================
if ($aksi === 'simpan_awalmedisfisikrehabilitasi') {

    try {
        $no_rawat  = isset($_POST['no_rawat'])  ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // ---- Riwayat Kesehatan ----
        $tanggal       = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';
        $anamnesis     = isset($_POST['anamnesis'])     ? validTeks4($_POST['anamnesis'], 20)       : 'Autoanamnesis';
        $hubungan      = isset($_POST['hubungan'])      ? validTeks4($_POST['hubungan'], 30)        : '';
        $keluhan_utama = isset($_POST['keluhan_utama']) ? validTeks4($_POST['keluhan_utama'], 2000) : '';
        $rps           = isset($_POST['rps'])           ? validTeks4($_POST['rps'], 2000)           : '';
        $rpd           = isset($_POST['rpd'])           ? validTeks4($_POST['rpd'], 1000)           : '';
        $alergi        = isset($_POST['alergi'])        ? validTeks4($_POST['alergi'], 50)          : '';

        if (empty($keluhan_utama)) throw new Exception('Keluhan utama harus diisi');

        // ---- Pemeriksaan Fisik ----
        // Enum: pakai whitelist agar karakter tidak di-strip
        $kesadaran_raw = isset($_POST['kesadaran']) ? trim($_POST['kesadaran']) : 'Compos Mentis';
        $kesadaran     = in_array($kesadaran_raw, ['Compos Mentis','Apatis','Delirium']) ? $kesadaran_raw : 'Compos Mentis';

        $nyeri_raw = isset($_POST['nyeri']) ? trim($_POST['nyeri']) : 'Tidak Nyeri';
        $nyeri     = in_array($nyeri_raw, ['Tidak Nyeri','Nyeri Ringan','Nyeri Sedang','Nyeri Sangat Sedang','Nyeri Berat']) ? $nyeri_raw : 'Tidak Nyeri';

        $skala_nyeri_raw = isset($_POST['skala_nyeri']) ? trim($_POST['skala_nyeri']) : '0';
        $skala_nyeri     = in_array($skala_nyeri_raw, ['0','1','2','3','4','5','6','7','8','9','10']) ? $skala_nyeri_raw : '0';

        $td   = isset($_POST['td'])   ? validTeks4($_POST['td'], 8)   : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $rr   = isset($_POST['rr'])   ? validTeks4($_POST['rr'], 5)   : '';
        $bb   = isset($_POST['bb'])   ? validTeks4($_POST['bb'], 5)   : '';

        // ---- Status Kelainan ----
        $enumOrgan = ['Normal','Abnormal','Tidak Diperiksa'];

        $kepala_raw  = isset($_POST['kepala'])  ? trim($_POST['kepala'])  : 'Tidak Diperiksa';
        $kepala      = in_array($kepala_raw, $enumOrgan)  ? $kepala_raw  : 'Tidak Diperiksa';
        $keterangan_kepala      = isset($_POST['keterangan_kepala'])      ? validTeks4($_POST['keterangan_kepala'], 30)      : '';

        $thoraks_raw = isset($_POST['thoraks']) ? trim($_POST['thoraks']) : 'Tidak Diperiksa';
        $thoraks     = in_array($thoraks_raw, $enumOrgan) ? $thoraks_raw : 'Tidak Diperiksa';
        $keterangan_thoraks     = isset($_POST['keterangan_thoraks'])     ? validTeks4($_POST['keterangan_thoraks'], 30)     : '';

        $abdomen_raw = isset($_POST['abdomen']) ? trim($_POST['abdomen']) : 'Tidak Diperiksa';
        $abdomen     = in_array($abdomen_raw, $enumOrgan) ? $abdomen_raw : 'Tidak Diperiksa';
        $keterangan_abdomen     = isset($_POST['keterangan_abdomen'])     ? validTeks4($_POST['keterangan_abdomen'], 30)     : '';

        $ekstremitas_raw = isset($_POST['ekstremitas']) ? trim($_POST['ekstremitas']) : 'Tidak Diperiksa';
        $ekstremitas     = in_array($ekstremitas_raw, $enumOrgan) ? $ekstremitas_raw : 'Tidak Diperiksa';
        $keterangan_ekstremitas = isset($_POST['keterangan_ekstremitas']) ? validTeks4($_POST['keterangan_ekstremitas'], 30) : '';

        $columna_raw = isset($_POST['columna']) ? trim($_POST['columna']) : 'Tidak Diperiksa';
        $columna     = in_array($columna_raw, $enumOrgan) ? $columna_raw : 'Tidak Diperiksa';
        $keterangan_columna     = isset($_POST['keterangan_columna'])     ? validTeks4($_POST['keterangan_columna'], 30)     : '';

        $muskulos_raw = isset($_POST['muskulos']) ? trim($_POST['muskulos']) : 'Tidak Diperiksa';
        $muskulos     = in_array($muskulos_raw, $enumOrgan) ? $muskulos_raw : 'Tidak Diperiksa';
        $keterangan_muskulos    = isset($_POST['keterangan_muskulos'])    ? validTeks4($_POST['keterangan_muskulos'], 30)    : '';

        $lainnya = isset($_POST['lainnya']) ? validTeks4($_POST['lainnya'], 1000) : '';

        // Enum risiko — whitelist
        $rj_raw = isset($_POST['resiko_jatuh']) ? trim($_POST['resiko_jatuh']) : 'Tidak Berisiko';
        $resiko_jatuh = in_array($rj_raw, ['Tidak Berisiko','Berisiko Sedang','Berisiko Tinggi']) ? $rj_raw : 'Tidak Berisiko';

        $rn_raw = isset($_POST['resiko_nutrisional']) ? trim($_POST['resiko_nutrisional']) : 'Tidak Berisiko Malnutrisi';
        $resiko_nutrisional = in_array($rn_raw, ['Tidak Berisiko Malnutrisi','Berisiko Malnutrisi','Malnutrisi']) ? $rn_raw : 'Tidak Berisiko Malnutrisi';

        $kf_raw = isset($_POST['kebutuhan_fungsional']) ? trim($_POST['kebutuhan_fungsional']) : 'Tidak Perlu Bantuan';
        $kebutuhan_fungsional = in_array($kf_raw, ['Tidak Perlu Bantuan','Perlu Bantuan','Perlu Bantuan Penuh']) ? $kf_raw : 'Tidak Perlu Bantuan';

        // ---- Pemeriksaan Fisik & Uji Fungsi ----
        $diagnosa_medis  = isset($_POST['diagnosa_medis'])  ? validTeks4($_POST['diagnosa_medis'], 500)  : '';
        $diagnosa_fungsi = isset($_POST['diagnosa_fungsi']) ? validTeks4($_POST['diagnosa_fungsi'], 500) : '';
        $penunjang_lain  = isset($_POST['penunjang_lain'])  ? validTeks4($_POST['penunjang_lain'], 500)  : '';

        // ---- Tatalaksana KFR ----
        $fisio    = isset($_POST['fisio'])    ? validTeks4($_POST['fisio'], 100)    : '';
        $okupasi  = isset($_POST['okupasi'])  ? validTeks4($_POST['okupasi'], 100)  : '';
        $wicara   = isset($_POST['wicara'])   ? validTeks4($_POST['wicara'], 100)   : '';
        $akupuntur= isset($_POST['akupuntur'])? validTeks4($_POST['akupuntur'], 100): '';
        $tatalain = isset($_POST['tatalain']) ? validTeks4($_POST['tatalain'], 100) : '';
        $frekuensi_terapi = isset($_POST['frekuensi_terapi']) ? validTeks4($_POST['frekuensi_terapi'], 40) : '';

        // Tanggal terapi — date field, NULL jika kosong
        function tglTerapi($val) {
            if (empty($val) || $val == '0000-00-00') return 'NULL';
            return "'" . date('Y-m-d', strtotime($val)) . "'";
        }
        $fisioterapi    = tglTerapi(isset($_POST['fisioterapi'])    ? $_POST['fisioterapi']    : '');
        $terapi_okupasi = tglTerapi(isset($_POST['terapi_okupasi']) ? $_POST['terapi_okupasi'] : '');
        $terapi_wicara  = tglTerapi(isset($_POST['terapi_wicara'])  ? $_POST['terapi_wicara']  : '');
        $terapi_akupuntur = tglTerapi(isset($_POST['terapi_akupuntur']) ? $_POST['terapi_akupuntur'] : '');
        $terapi_lainnya = tglTerapi(isset($_POST['terapi_lainnya']) ? $_POST['terapi_lainnya'] : '');

        // ---- Edukasi ----
        $edukasi = isset($_POST['edukasi']) ? validTeks4($_POST['edukasi'], 500) : '';

        // ---- Cek INSERT atau UPDATE ----
        $query_check  = "SELECT no_rawat FROM penilaian_medis_ralan_rehab_medik WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_medis_ralan_rehab_medik SET
                        tanggal                 = '$tanggal',
                        kd_dokter               = '$kd_dokter',
                        anamnesis               = '$anamnesis',
                        hubungan                = '$hubungan',
                        keluhan_utama           = '$keluhan_utama',
                        rps                     = '$rps',
                        rpd                     = '$rpd',
                        alergi                  = '$alergi',
                        kesadaran               = '$kesadaran',
                        nyeri                   = '$nyeri',
                        skala_nyeri             = '$skala_nyeri',
                        td                      = '$td',
                        nadi                    = '$nadi',
                        suhu                    = '$suhu',
                        rr                      = '$rr',
                        bb                      = '$bb',
                        kepala                  = '$kepala',
                        keterangan_kepala       = '$keterangan_kepala',
                        thoraks                 = '$thoraks',
                        keterangan_thoraks      = '$keterangan_thoraks',
                        abdomen                 = '$abdomen',
                        keterangan_abdomen      = '$keterangan_abdomen',
                        ekstremitas             = '$ekstremitas',
                        keterangan_ekstremitas  = '$keterangan_ekstremitas',
                        columna                 = '$columna',
                        keterangan_columna      = '$keterangan_columna',
                        muskulos                = '$muskulos',
                        keterangan_muskulos     = '$keterangan_muskulos',
                        lainnya                 = '$lainnya',
                        resiko_jatuh            = '$resiko_jatuh',
                        resiko_nutrisional      = '$resiko_nutrisional',
                        kebutuhan_fungsional    = '$kebutuhan_fungsional',
                        diagnosa_medis          = '$diagnosa_medis',
                        diagnosa_fungsi         = '$diagnosa_fungsi',
                        penunjang_lain          = '$penunjang_lain',
                        fisio                   = '$fisio',
                        okupasi                 = '$okupasi',
                        wicara                  = '$wicara',
                        akupuntur               = '$akupuntur',
                        tatalain                = '$tatalain',
                        frekuensi_terapi        = '$frekuensi_terapi',
                        fisioterapi             = $fisioterapi,
                        terapi_okupasi          = $terapi_okupasi,
                        terapi_wicara           = $terapi_wicara,
                        terapi_akupuntur        = $terapi_akupuntur,
                        terapi_lainnya          = $terapi_lainnya,
                        edukasi                 = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Fisik & Rehabilitasi');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data penilaian medis Fisik & Rehabilitasi berhasil diupdate','no_rawat'=>$no_rawat,'action'=>'update']);

        } else {
            // INSERT
            $query = "INSERT INTO penilaian_medis_ralan_rehab_medik (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        keluhan_utama, rps, rpd, alergi,
                        kesadaran, nyeri, skala_nyeri,
                        td, nadi, suhu, rr, bb,
                        kepala, keterangan_kepala,
                        thoraks, keterangan_thoraks,
                        abdomen, keterangan_abdomen,
                        ekstremitas, keterangan_ekstremitas,
                        columna, keterangan_columna,
                        muskulos, keterangan_muskulos,
                        lainnya,
                        resiko_jatuh, resiko_nutrisional, kebutuhan_fungsional,
                        diagnosa_medis, diagnosa_fungsi, penunjang_lain,
                        fisio, okupasi, wicara, akupuntur, tatalain, frekuensi_terapi,
                        fisioterapi, terapi_okupasi, terapi_wicara, terapi_akupuntur, terapi_lainnya,
                        edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$keluhan_utama', '$rps', '$rpd', '$alergi',
                        '$kesadaran', '$nyeri', '$skala_nyeri',
                        '$td', '$nadi', '$suhu', '$rr', '$bb',
                        '$kepala', '$keterangan_kepala',
                        '$thoraks', '$keterangan_thoraks',
                        '$abdomen', '$keterangan_abdomen',
                        '$ekstremitas', '$keterangan_ekstremitas',
                        '$columna', '$keterangan_columna',
                        '$muskulos', '$keterangan_muskulos',
                        '$lainnya',
                        '$resiko_jatuh', '$resiko_nutrisional', '$kebutuhan_fungsional',
                        '$diagnosa_medis', '$diagnosa_fungsi', '$penunjang_lain',
                        '$fisio', '$okupasi', '$wicara', '$akupuntur', '$tatalain', '$frekuensi_terapi',
                        $fisioterapi, $terapi_okupasi, $terapi_wicara, $terapi_akupuntur, $terapi_lainnya,
                        '$edukasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Fisik & Rehabilitasi');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data penilaian medis Fisik & Rehabilitasi berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert']);
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================================
// HAPUS PENILAIAN AWAL MEDIS FISIK & REHABILITASI
// ========================================================
if ($aksi === 'hapus_awalmedisfisikrehabilitasi') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_rehab_medik
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        if (mysqli_num_rows($result_cek) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');

        $query_delete = "DELETE FROM penilaian_medis_ralan_rehab_medik WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);
        if (!$result) throw new Exception('Gagal menghapus data penilaian medis Fisik & Rehabilitasi');

        insertTracker($query_delete);

        echo json_encode(['status'=>'success','message'=>'Data penilaian medis Fisik & Rehabilitasi berhasil dihapus','no_rawat'=>$no_rawat]);

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================================
// SIMPAN LAYANAN KEDOKTERAN FISIK & REHABILITASI
// ========================================================
if ($aksi === 'simpan_layanankedokteranfisik') {

    try {
        $no_rawat  = isset($_POST['no_rawat'])  ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Tanggal
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';

        // Pendamping — enum whitelist
        $pendamping_raw = isset($_POST['pendamping']) ? trim($_POST['pendamping']) : 'Tidak';
        $pendamping     = in_array($pendamping_raw, ['Tidak','Suami','Istri','Anak','Keluarga']) ? $pendamping_raw : 'Tidak';
        $keterangan_pendamping = ($pendamping !== 'Tidak' && isset($_POST['keterangan_pendamping']))
                                 ? validTeks4($_POST['keterangan_pendamping'], 30) : '';

        // Field teks utama
        $anamnesa        = isset($_POST['anamnesa'])        ? validTeks4($_POST['anamnesa'], 500)        : '';
        $pemeriksaan_fisik = isset($_POST['pemeriksaan_fisik']) ? validTeks4($_POST['pemeriksaan_fisik'], 1500) : '';
        $diagnosa_medis  = isset($_POST['diagnosa_medis'])  ? validTeks4($_POST['diagnosa_medis'], 200)  : '';
        $diagnosa_fungsi = isset($_POST['diagnosa_fungsi']) ? validTeks4($_POST['diagnosa_fungsi'], 200) : '';
        $tatalaksana     = isset($_POST['tatalaksana'])     ? validTeks4($_POST['tatalaksana'], 2000)    : '';
        $anjuran         = isset($_POST['anjuran'])         ? validTeks4($_POST['anjuran'], 500)         : '';
        $evaluasi        = isset($_POST['evaluasi'])        ? validTeks4($_POST['evaluasi'], 500)        : '';

        if (empty($anamnesa)) throw new Exception('Anamnesa harus diisi');

        // Suspek Penyakit Akibat Kerja — enum whitelist
        $suspek_raw = isset($_POST['suspek_penyakit_kerja']) ? trim($_POST['suspek_penyakit_kerja']) : 'Tidak';
        $suspek_penyakit_kerja = in_array($suspek_raw, ['Tidak','Ya']) ? $suspek_raw : 'Tidak';
        $keterangan_suspek = ($suspek_penyakit_kerja === 'Ya' && isset($_POST['keterangan_suspek_penyakit_kerja']))
                             ? validTeks4($_POST['keterangan_suspek_penyakit_kerja'], 70) : '';

        // Status Program — enum whitelist
        $status_raw = isset($_POST['status_program']) ? trim($_POST['status_program']) : 'Belum Selesai';
        $status_program = in_array($status_raw, ['Belum Selesai','Sudah Selesai']) ? $status_raw : 'Belum Selesai';

        // Cek INSERT atau UPDATE
        $query_check  = "SELECT no_rawat FROM layanan_kedokteran_fisik_rehabilitasi WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE layanan_kedokteran_fisik_rehabilitasi SET
                        tanggal                         = '$tanggal',
                        kd_dokter                       = '$kd_dokter',
                        pendamping                      = '$pendamping',
                        keterangan_pendamping           = '$keterangan_pendamping',
                        anamnesa                        = '$anamnesa',
                        pemeriksaan_fisik               = '$pemeriksaan_fisik',
                        diagnosa_medis                  = '$diagnosa_medis',
                        diagnosa_fungsi                 = '$diagnosa_fungsi',
                        tatalaksana                     = '$tatalaksana',
                        anjuran                         = '$anjuran',
                        evaluasi                        = '$evaluasi',
                        suspek_penyakit_kerja           = '$suspek_penyakit_kerja',
                        keterangan_suspek_penyakit_kerja= '$keterangan_suspek',
                        status_program                  = '$status_program'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data Layanan Kedokteran Fisik & Rehabilitasi');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data Layanan Kedokteran Fisik & Rehabilitasi berhasil diupdate','no_rawat'=>$no_rawat,'action'=>'update']);

        } else {
            // INSERT
            $query = "INSERT INTO layanan_kedokteran_fisik_rehabilitasi (
                        no_rawat, tanggal, kd_dokter,
                        pendamping, keterangan_pendamping,
                        anamnesa, pemeriksaan_fisik,
                        diagnosa_medis, diagnosa_fungsi,
                        tatalaksana, anjuran, evaluasi,
                        suspek_penyakit_kerja, keterangan_suspek_penyakit_kerja,
                        status_program
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter',
                        '$pendamping', '$keterangan_pendamping',
                        '$anamnesa', '$pemeriksaan_fisik',
                        '$diagnosa_medis', '$diagnosa_fungsi',
                        '$tatalaksana', '$anjuran', '$evaluasi',
                        '$suspek_penyakit_kerja', '$keterangan_suspek',
                        '$status_program'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data Layanan Kedokteran Fisik & Rehabilitasi');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data Layanan Kedokteran Fisik & Rehabilitasi berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert']);
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================================
// HAPUS LAYANAN KEDOKTERAN FISIK & REHABILITASI
// ========================================================
if ($aksi === 'hapus_layanankedokteranfisik') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $query_cek = "SELECT no_rawat FROM layanan_kedokteran_fisik_rehabilitasi
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        if (mysqli_num_rows($result_cek) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');

        $query_delete = "DELETE FROM layanan_kedokteran_fisik_rehabilitasi WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);
        if (!$result) throw new Exception('Gagal menghapus data Layanan Kedokteran Fisik & Rehabilitasi');

        insertTracker($query_delete);

        echo json_encode(['status'=>'success','message'=>'Data Layanan Kedokteran Fisik & Rehabilitasi berhasil dihapus','no_rawat'=>$no_rawat]);

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================================
// SIMPAN UJI FUNGSI KFR
// ========================================================
if ($aksi === 'simpan_ujifungsikfr') {

    try {
        $no_rawat  = isset($_POST['no_rawat'])  ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Tanggal
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';

        // Field utama
        $diagnosis_fungsional = isset($_POST['diagnosis_fungsional']) ? validTeks4($_POST['diagnosis_fungsional'], 50)  : '';
        $diagnosis_medis      = isset($_POST['diagnosis_medis'])      ? validTeks4($_POST['diagnosis_medis'], 50)       : '';
        $hasil_didapat        = isset($_POST['hasil_didapat'])        ? validTeks4($_POST['hasil_didapat'], 100)        : '';
        $kesimpulan           = isset($_POST['kesimpulan'])           ? validTeks4($_POST['kesimpulan'], 100)           : '';
        $rekomedasi           = isset($_POST['rekomedasi'])           ? validTeks4($_POST['rekomedasi'], 100)           : '';

        if (empty($diagnosis_fungsional)) throw new Exception('Diagnosis Fungsional harus diisi');

        // Cek INSERT atau UPDATE
        $query_check  = "SELECT no_rawat FROM uji_fungsi_kfr WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE uji_fungsi_kfr SET
                        tanggal               = '$tanggal',
                        kd_dokter             = '$kd_dokter',
                        diagnosis_fungsional  = '$diagnosis_fungsional',
                        diagnosis_medis       = '$diagnosis_medis',
                        hasil_didapat         = '$hasil_didapat',
                        kesimpulan            = '$kesimpulan',
                        rekomedasi            = '$rekomedasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data Uji Fungsi KFR');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data Uji Fungsi KFR berhasil diupdate','no_rawat'=>$no_rawat,'action'=>'update']);

        } else {
            // INSERT
            $query = "INSERT INTO uji_fungsi_kfr (
                        no_rawat, tanggal, kd_dokter,
                        diagnosis_fungsional, diagnosis_medis,
                        hasil_didapat, kesimpulan, rekomedasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter',
                        '$diagnosis_fungsional', '$diagnosis_medis',
                        '$hasil_didapat', '$kesimpulan', '$rekomedasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data Uji Fungsi KFR');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data Uji Fungsi KFR berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert']);
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================================
// HAPUS UJI FUNGSI KFR
// ========================================================
if ($aksi === 'hapus_ujifungsikfr') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $query_cek = "SELECT no_rawat FROM uji_fungsi_kfr
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        if (mysqli_num_rows($result_cek) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');

        $query_delete = "DELETE FROM uji_fungsi_kfr WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);
        if (!$result) throw new Exception('Gagal menghapus data Uji Fungsi KFR');

        insertTracker($query_delete);

        echo json_encode(['status'=>'success','message'=>'Data Uji Fungsi KFR berhasil dihapus','no_rawat'=>$no_rawat]);

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================
// SIMPAN PENILAIAN AWAL MEDIS HEMODIALISA
// ========================================
if ($aksi === 'simpan_awalmedishemodialisa') {

    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // ── Helper: tangkap POST ──────────────────────────────────────────
        function vt($key, $len = 50)  { return isset($_POST[$key]) ? validTeks4($_POST[$key], $len) : ''; }
        // vdate: kembalikan tanggal 'Y-m-d' string atau string kosong jika tidak valid
        // String kosong → sqlDate() akan tulis NULL (kolom date nullable)
        function vdate($key) {
            $v = isset($_POST[$key]) ? trim($_POST[$key]) : '';
            if (strlen($v) < 8) return '';
            $ts = strtotime($v);
            return ($ts !== false) ? date('Y-m-d', $ts) : '';
        }
        function venum($key, $default = 'Tidak') {
            $allowed = ['Tidak','Ya','Non Reaktif','Reaktif','Sehat','Sakit Ringan','Sakit Sedang','Sakit Berat',
                        'Compos Mentis','Apatis','Somnolen','Sopor','Koma','Normal','Meningkat',
                        'Autoanamnesis','Alloanamnesis',
                        'Tidak Nyeri','Nyeri Ringan','Nyeri Sedang','Nyeri Berat','Nyeri Sangat Berat'];
            $v = isset($_POST[$key]) ? trim($_POST[$key]) : $default;
            return in_array($v, $allowed) ? $v : $default;
        }

        // ── Header ────────────────────────────────────────────────────────
        $tanggal = isset($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');
        if (strpos($tanggal, 'T') !== false) $tanggal = str_replace('T', ' ', $tanggal) . ':00';

        $anamnesis      = venum('anamnesis', 'Autoanamnesis');
        $hubungan       = vt('hubungan', 30);
        $ruangan        = vt('ruangan', 50);
        $alergi         = vt('alergi', 100);
        $nyeri          = venum('nyeri', 'Tidak Nyeri');
        $status_nutrisi = vt('status_nutrisi', 100);

        // ── I. Riwayat Penyakit ──────────────────────────────────────────
        $hipertensi                        = venum('hipertensi');
        $keterangan_hipertensi             = vt('keterangan_hipertensi', 30);
        $diabetes                          = venum('diabetes');
        $keterangan_diabetes               = vt('keterangan_diabetes', 30);
        $batu_saluran_kemih                = venum('batu_saluran_kemih');
        $keterangan_batu_saluran_kemih     = vt('keterangan_batu_saluran_kemih', 30);
        $operasi_saluran_kemih             = venum('operasi_saluran_kemih');
        $keterangan_operasi_saluran_kemih  = vt('keterangan_operasi_saluran_kemih', 30);
        $infeksi_saluran_kemih             = venum('infeksi_saluran_kemih');
        $keterangan_infeksi_saluran_kemih  = vt('keterangan_infeksi_saluran_kemih', 30);
        $bengkak_seluruh_tubuh             = venum('bengkak_seluruh_tubuh');
        $keterangan_bengkak_seluruh_tubuh  = vt('keterangan_bengkak_seluruh_tubuh', 30);
        $urin_berdarah                     = venum('urin_berdarah');
        $keterangan_urin_berdarah          = vt('keterangan_urin_berdarah', 30);
        $penyakit_ginjal_laom              = venum('penyakit_ginjal_laom');
        $keterangan_penyakit_ginjal_laom   = vt('keterangan_penyakit_ginjal_laom', 30);
        $penyakit_lain                     = venum('penyakit_lain');
        $keterangan_penyakit_lain          = vt('keterangan_penyakit_lain', 30);
        $konsumsi_obat_nefro               = venum('konsumsi_obat_nefro');
        $keterangan_konsumsi_obat_nefro    = vt('keterangan_konsumsi_obat_nefro', 30);

        // ── II. Riwayat Dialisis / Transplantasi ─────────────────────────
        $dialisis_pertama     = vdate('dialisis_pertama');
        $pernah_cpad          = venum('pernah_cpad');
        $tanggal_cpad         = vdate('tanggal_cpad');
        $pernah_transplantasi = venum('pernah_transplantasi');
        $tanggal_transplantasi= vdate('tanggal_transplantasi');

        // ── III. Pemeriksaan Fisik ────────────────────────────────────────
        $keadaan_umum = venum('keadaan_umum', 'Sehat');
        $kesadaran    = venum('kesadaran',    'Compos Mentis');
        $nadi         = vt('nadi', 5);
        $bb           = vt('bb',   5);
        $td           = vt('td',   8);
        $suhu         = vt('suhu', 5);
        $napas        = vt('napas',5);
        $tb           = vt('tb',   5);

        $hepatomegali = venum('hepatomegali');
        $splenomegali = venum('splenomegali');
        $ascites      = venum('ascites');
        $edema        = venum('edema');
        $whezzing     = venum('whezzing');
        $ronchi       = venum('ronchi');
        $ikterik      = venum('ikterik');
        $tekanan_vena = venum('tekanan_vena', 'Normal');
        $anemia       = venum('anemia');
        $kardiomegali = venum('kardiomegali');
        $bising       = venum('bising');

        // ── IV. Penunjang (checkbox) ──────────────────────────────────────
        $thorax        = venum('thorax');       $tanggal_thorax        = vdate('tanggal_thorax');
        $ekg           = venum('ekg');          $tanggal_ekg           = vdate('tanggal_ekg');
        $bno           = venum('bno');          $tanggal_bno           = vdate('tanggal_bno');
        $usg           = venum('usg');          $tanggal_usg           = vdate('tanggal_usg');
        $renogram      = venum('renogram');     $tanggal_renogram      = vdate('tanggal_renogram');
        $biopsi        = venum('biopsi');       $tanggal_biopsi        = vdate('tanggal_biopsi');
        $ctscan        = venum('ctscan');       $tanggal_ctscan        = vdate('tanggal_ctscan');
        $arteriografi  = venum('arteriografi'); $tanggal_arteriografi  = vdate('tanggal_arteriografi');
        $kultur_urin   = venum('kultur_urin');  $tanggal_kultur_urin   = vdate('tanggal_kultur_urin');
        $laborat       = venum('laborat');      $tanggal_laborat       = vdate('tanggal_laborat');

        // ── Hasil Lab ─────────────────────────────────────────────────────
        $hematokrit  = vt('hematokrit',  30);
        $hemoglobin  = vt('hemoglobin',  30);
        $leukosit    = vt('leukosit',    30);
        $trombosit   = vt('trombosit',   30);
        $hitung_jenis= vt('hitung_jenis',30);
        $ureum       = vt('ureum',       30);
        $urin_lengkap= vt('urin_lengkap',30);
        $kreatinin   = vt('kreatinin',   30);
        $cct         = vt('cct',         30);
        $sgot        = vt('sgot',        30);
        $sgpt        = vt('sgpt',        30);
        $ct          = vt('ct',          30);
        $asam_urat   = vt('asam_urat',   30);
        $hbsag       = venum('hbsag',    'Non Reaktif');
        $anti_hcv    = venum('anti_hcv', 'Non Reaktif');

        // ── V. Edukasi ────────────────────────────────────────────────────
        $edukasi = vt('edukasi', 1000);

        // ── Helper tanggal SQL ────────────────────────────────────────────
        // sqlDate: tulis 'Y-m-d' jika ada nilai, NULL jika kosong
        function sqlDate($v) { return (!empty($v)) ? "'$v'" : 'NULL'; }

        // ── Cek existing ──────────────────────────────────────────────────
        $q_check = bukaquery("SELECT no_rawat FROM penilaian_medis_hemodialisa WHERE no_rawat = '$no_rawat'");

        if (mysqli_num_rows($q_check) > 0) {
            // ── UPDATE ────────────────────────────────────────────────────
            $query = "UPDATE penilaian_medis_hemodialisa SET
                        tanggal                           = '$tanggal',
                        kd_dokter                         = '$kd_dokter',
                        anamnesis                         = '$anamnesis',
                        hubungan                          = '$hubungan',
                        ruangan                           = '$ruangan',
                        alergi                            = '$alergi',
                        nyeri                             = '$nyeri',
                        status_nutrisi                    = '$status_nutrisi',
                        hipertensi                        = '$hipertensi',
                        keterangan_hipertensi             = '$keterangan_hipertensi',
                        diabetes                          = '$diabetes',
                        keterangan_diabetes               = '$keterangan_diabetes',
                        batu_saluran_kemih                = '$batu_saluran_kemih',
                        keterangan_batu_saluran_kemih     = '$keterangan_batu_saluran_kemih',
                        operasi_saluran_kemih             = '$operasi_saluran_kemih',
                        keterangan_operasi_saluran_kemih  = '$keterangan_operasi_saluran_kemih',
                        infeksi_saluran_kemih             = '$infeksi_saluran_kemih',
                        keterangan_infeksi_saluran_kemih  = '$keterangan_infeksi_saluran_kemih',
                        bengkak_seluruh_tubuh             = '$bengkak_seluruh_tubuh',
                        keterangan_bengkak_seluruh_tubuh  = '$keterangan_bengkak_seluruh_tubuh',
                        urin_berdarah                     = '$urin_berdarah',
                        keterangan_urin_berdarah          = '$keterangan_urin_berdarah',
                        penyakit_ginjal_laom              = '$penyakit_ginjal_laom',
                        keterangan_penyakit_ginjal_laom   = '$keterangan_penyakit_ginjal_laom',
                        penyakit_lain                     = '$penyakit_lain',
                        keterangan_penyakit_lain          = '$keterangan_penyakit_lain',
                        konsumsi_obat_nefro               = '$konsumsi_obat_nefro',
                        keterangan_konsumsi_obat_nefro    = '$keterangan_konsumsi_obat_nefro',
                        dialisis_pertama                  = " . sqlDate($dialisis_pertama) . ",
                        pernah_cpad                       = '$pernah_cpad',
                        tanggal_cpad                      = " . sqlDate($tanggal_cpad) . ",
                        pernah_transplantasi              = '$pernah_transplantasi',
                        tanggal_transplantasi             = " . sqlDate($tanggal_transplantasi) . ",
                        keadaan_umum                      = '$keadaan_umum',
                        kesadaran                         = '$kesadaran',
                        nadi                              = '$nadi',
                        bb                                = '$bb',
                        td                                = '$td',
                        suhu                              = '$suhu',
                        napas                             = '$napas',
                        tb                                = '$tb',
                        hepatomegali                      = '$hepatomegali',
                        splenomegali                      = '$splenomegali',
                        ascites                           = '$ascites',
                        edema                             = '$edema',
                        whezzing                          = '$whezzing',
                        ronchi                            = '$ronchi',
                        ikterik                           = '$ikterik',
                        tekanan_vena                      = '$tekanan_vena',
                        anemia                            = '$anemia',
                        kardiomegali                      = '$kardiomegali',
                        bising                            = '$bising',
                        thorax                            = '$thorax',
                        tanggal_thorax                    = " . sqlDate($tanggal_thorax) . ",
                        ekg                               = '$ekg',
                        tanggal_ekg                       = " . sqlDate($tanggal_ekg) . ",
                        bno                               = '$bno',
                        tanggal_bno                       = " . sqlDate($tanggal_bno) . ",
                        usg                               = '$usg',
                        tanggal_usg                       = " . sqlDate($tanggal_usg) . ",
                        renogram                          = '$renogram',
                        tanggal_renogram                  = " . sqlDate($tanggal_renogram) . ",
                        biopsi                            = '$biopsi',
                        tanggal_biopsi                    = " . sqlDate($tanggal_biopsi) . ",
                        ctscan                            = '$ctscan',
                        tanggal_ctscan                    = " . sqlDate($tanggal_ctscan) . ",
                        arteriografi                      = '$arteriografi',
                        tanggal_arteriografi              = " . sqlDate($tanggal_arteriografi) . ",
                        kultur_urin                       = '$kultur_urin',
                        tanggal_kultur_urin               = " . sqlDate($tanggal_kultur_urin) . ",
                        laborat                           = '$laborat',
                        tanggal_laborat                   = " . sqlDate($tanggal_laborat) . ",
                        hematokrit                        = '$hematokrit',
                        hemoglobin                        = '$hemoglobin',
                        leukosit                          = '$leukosit',
                        trombosit                         = '$trombosit',
                        hitung_jenis                      = '$hitung_jenis',
                        ureum                             = '$ureum',
                        urin_lengkap                      = '$urin_lengkap',
                        kreatinin                         = '$kreatinin',
                        cct                               = '$cct',
                        sgot                              = '$sgot',
                        sgpt                              = '$sgpt',
                        ct                                = '$ct',
                        asam_urat                         = '$asam_urat',
                        hbsag                             = '$hbsag',
                        anti_hcv                          = '$anti_hcv',
                        edukasi                           = '$edukasi'
                      WHERE no_rawat = '$no_rawat'";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data Penilaian Awal Medis Hemodialisa');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data Penilaian Awal Medis Hemodialisa berhasil diupdate',
                'no_rawat' => $no_rawat,
                'action'   => 'update'
            ]);

        } else {
            // ── INSERT ────────────────────────────────────────────────────
            $query = "INSERT INTO penilaian_medis_hemodialisa (
                        no_rawat, tanggal, kd_dokter, anamnesis, hubungan,
                        ruangan, alergi, nyeri, status_nutrisi,
                        hipertensi, keterangan_hipertensi,
                        diabetes, keterangan_diabetes,
                        batu_saluran_kemih, keterangan_batu_saluran_kemih,
                        operasi_saluran_kemih, keterangan_operasi_saluran_kemih,
                        infeksi_saluran_kemih, keterangan_infeksi_saluran_kemih,
                        bengkak_seluruh_tubuh, keterangan_bengkak_seluruh_tubuh,
                        urin_berdarah, keterangan_urin_berdarah,
                        penyakit_ginjal_laom, keterangan_penyakit_ginjal_laom,
                        penyakit_lain, keterangan_penyakit_lain,
                        konsumsi_obat_nefro, keterangan_konsumsi_obat_nefro,
                        dialisis_pertama, pernah_cpad, tanggal_cpad,
                        pernah_transplantasi, tanggal_transplantasi,
                        keadaan_umum, kesadaran, nadi, bb, td, suhu, napas, tb,
                        hepatomegali, splenomegali, ascites, edema,
                        whezzing, ronchi, ikterik, tekanan_vena,
                        anemia, kardiomegali, bising,
                        thorax, tanggal_thorax,
                        ekg, tanggal_ekg,
                        bno, tanggal_bno,
                        usg, tanggal_usg,
                        renogram, tanggal_renogram,
                        biopsi, tanggal_biopsi,
                        ctscan, tanggal_ctscan,
                        arteriografi, tanggal_arteriografi,
                        kultur_urin, tanggal_kultur_urin,
                        laborat, tanggal_laborat,
                        hematokrit, hemoglobin, leukosit, trombosit,
                        hitung_jenis, ureum, urin_lengkap, kreatinin,
                        cct, sgot, sgpt, ct, asam_urat,
                        hbsag, anti_hcv,
                        edukasi
                      ) VALUES (
                        '$no_rawat', '$tanggal', '$kd_dokter', '$anamnesis', '$hubungan',
                        '$ruangan', '$alergi', '$nyeri', '$status_nutrisi',
                        '$hipertensi', '$keterangan_hipertensi',
                        '$diabetes', '$keterangan_diabetes',
                        '$batu_saluran_kemih', '$keterangan_batu_saluran_kemih',
                        '$operasi_saluran_kemih', '$keterangan_operasi_saluran_kemih',
                        '$infeksi_saluran_kemih', '$keterangan_infeksi_saluran_kemih',
                        '$bengkak_seluruh_tubuh', '$keterangan_bengkak_seluruh_tubuh',
                        '$urin_berdarah', '$keterangan_urin_berdarah',
                        '$penyakit_ginjal_laom', '$keterangan_penyakit_ginjal_laom',
                        '$penyakit_lain', '$keterangan_penyakit_lain',
                        '$konsumsi_obat_nefro', '$keterangan_konsumsi_obat_nefro',
                        " . sqlDate($dialisis_pertama) . ", '$pernah_cpad', " . sqlDate($tanggal_cpad) . ",
                        '$pernah_transplantasi', " . sqlDate($tanggal_transplantasi) . ",
                        '$keadaan_umum', '$kesadaran', '$nadi', '$bb', '$td', '$suhu', '$napas', '$tb',
                        '$hepatomegali', '$splenomegali', '$ascites', '$edema',
                        '$whezzing', '$ronchi', '$ikterik', '$tekanan_vena',
                        '$anemia', '$kardiomegali', '$bising',
                        '$thorax', " . sqlDate($tanggal_thorax) . ",
                        '$ekg', " . sqlDate($tanggal_ekg) . ",
                        '$bno', " . sqlDate($tanggal_bno) . ",
                        '$usg', " . sqlDate($tanggal_usg) . ",
                        '$renogram', " . sqlDate($tanggal_renogram) . ",
                        '$biopsi', " . sqlDate($tanggal_biopsi) . ",
                        '$ctscan', " . sqlDate($tanggal_ctscan) . ",
                        '$arteriografi', " . sqlDate($tanggal_arteriografi) . ",
                        '$kultur_urin', " . sqlDate($tanggal_kultur_urin) . ",
                        '$laborat', " . sqlDate($tanggal_laborat) . ",
                        '$hematokrit', '$hemoglobin', '$leukosit', '$trombosit',
                        '$hitung_jenis', '$ureum', '$urin_lengkap', '$kreatinin',
                        '$cct', '$sgot', '$sgpt', '$ct', '$asam_urat',
                        '$hbsag', '$anti_hcv',
                        '$edukasi'
                      )";

            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data Penilaian Awal Medis Hemodialisa');
            insertTracker($query);

            echo json_encode([
                'status'   => 'success',
                'message'  => 'Data Penilaian Awal Medis Hemodialisa berhasil disimpan',
                'no_rawat' => $no_rawat,
                'action'   => 'insert'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// HAPUS PENILAIAN AWAL MEDIS HEMODIALISA
// ========================================
if ($aksi === 'hapus_awalmedishemodialisa') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';

        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        // Cek ownership
        $q_cek = bukaquery("SELECT no_rawat FROM penilaian_medis_hemodialisa
                            WHERE no_rawat  = '$no_rawat'
                            AND   kd_dokter = '$kd_dokter'");

        if (mysqli_num_rows($q_cek) === 0) {
            throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        }

        $q_del = "DELETE FROM penilaian_medis_hemodialisa WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q_del);

        if (!$result) throw new Exception('Gagal menghapus data Penilaian Awal Medis Hemodialisa');

        insertTracker($q_del);

        echo json_encode([
            'status'   => 'success',
            'message'  => 'Data Penilaian Awal Medis Hemodialisa berhasil dihapus',
            'no_rawat' => $no_rawat
        ]);

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================================
// SIMPAN PENILAIAN AWAL MEDIS JANTUNG
// ========================================================
if ($aksi === 'simpan_awalmedisjantung') {

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

        // Pemeriksaan Fisik
        $td   = isset($_POST['td'])   ? validTeks4($_POST['td'], 8)   : '';
        $bb   = isset($_POST['bb'])   ? validTeks4($_POST['bb'], 5)   : '';
        $tb   = isset($_POST['tb'])   ? validTeks4($_POST['tb'], 5)   : '';
        $suhu = isset($_POST['suhu']) ? validTeks4($_POST['suhu'], 5) : '';
        $nadi = isset($_POST['nadi']) ? validTeks4($_POST['nadi'], 5) : '';
        $rr   = isset($_POST['rr'])   ? validTeks4($_POST['rr'], 5)   : '';

        // Enum keadaan_umum — whitelist
        $ku_raw       = isset($_POST['keadaan_umum']) ? trim($_POST['keadaan_umum']) : 'Sehat';
        $keadaan_umum = in_array($ku_raw, ['Sehat','Sakit Ringan','Sakit Sedang','Sakit Berat']) ? $ku_raw : 'Sehat';

        $nyeri         = isset($_POST['nyeri'])         ? validTeks4($_POST['nyeri'], 50)         : '';
        $status_nutrisi= isset($_POST['status_nutrisi'])? validTeks4($_POST['status_nutrisi'], 50): '';

        // Status Kelainan — enum whitelist
        $enumOrgan = ['Normal','Abnormal','Tidak Diperiksa'];

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
        $lab           = isset($_POST['lab'])           ? validTeks4($_POST['lab'], 500)           : '';
        $ekg           = isset($_POST['ekg'])           ? validTeks4($_POST['ekg'], 500)           : '';
        $penunjang_lain= isset($_POST['penunjang_lain'])? validTeks4($_POST['penunjang_lain'], 500): '';

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
        $query_check  = "SELECT no_rawat FROM penilaian_medis_ralan_jantung WHERE no_rawat = '$no_rawat'";
        $result_check = bukaquery($query_check);

        if (mysqli_num_rows($result_check) > 0) {
            // UPDATE
            $query = "UPDATE penilaian_medis_ralan_jantung SET
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
            if (!$result) throw new Exception('Gagal mengupdate data penilaian medis Jantung');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data penilaian medis Jantung berhasil diupdate','no_rawat'=>$no_rawat,'action'=>'update']);

        } else {
            // INSERT
            $query = "INSERT INTO penilaian_medis_ralan_jantung (
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
            if (!$result) throw new Exception('Gagal menyimpan data penilaian medis Jantung');
            insertTracker($query);

            echo json_encode(['status'=>'success','message'=>'Data penilaian medis Jantung berhasil disimpan','no_rawat'=>$no_rawat,'action'=>'insert']);
        }

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ========================================================
// HAPUS PENILAIAN AWAL MEDIS JANTUNG
// ========================================================
if ($aksi === 'hapus_awalmedisjantung') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

        $query_cek = "SELECT no_rawat FROM penilaian_medis_ralan_jantung
                      WHERE no_rawat  = '$no_rawat'
                      AND   kd_dokter = '$kd_dokter'";
        $result_cek = bukaquery($query_cek);
        if (mysqli_num_rows($result_cek) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');

        $query_delete = "DELETE FROM penilaian_medis_ralan_jantung WHERE no_rawat = '$no_rawat'";
        $result       = bukaquery($query_delete);
        if (!$result) throw new Exception('Gagal menghapus data penilaian medis Jantung');

        insertTracker($query_delete);

        echo json_encode(['status'=>'success','message'=>'Data penilaian medis Jantung berhasil dihapus','no_rawat'=>$no_rawat]);

    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }

    exit();
}

// ============================================================
// LOAD CHECKLIST KRITERIA MASUK NICU
// ============================================================
if($aksi === 'load_checklist_kriteria_masuk_nicu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $q = "SELECT * FROM checklist_kriteria_masuk_nicu WHERE no_rawat = '$no_rawat' LIMIT 1";
        $result = bukaquery($q);

        if(mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'empty', 'message' => 'Data tidak ditemukan']);
        }

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// SIMPAN CHECKLIST KRITERIA MASUK NICU
// ============================================================
if($aksi === 'simpan_checklist_kriteria_masuk_nicu') {
    try {
        $no_rawat   = isset($_POST['no_rawat'])   ? trim($_POST['no_rawat'])   : '';
        $nik        = isset($_POST['nik'])        ? trim($_POST['nik'])        : '';
        $keputusan  = isset($_POST['keputusan'])  ? trim($_POST['keputusan'])  : 'Diterima Di NICU';
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';

        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Validasi keputusan
        $allowedKeputusan = ['Diterima Di NICU', 'Tidak Diterima', 'Dirawat Di Ruang Perawatan Biasa'];
        if(!in_array($keputusan, $allowedKeputusan)) {
            $keputusan = 'Diterima Di NICU';
        }

        // Daftar semua kolom enum
        $enumFields = [
            'respirasi1','respirasi2','respirasi3','respirasi4',
            'prematur1','prematur2','prematur3',
            'kardio1','kardio2','kardio3',
            'neuro1','neuro2','neuro3',
            'metabolik1','metabolik2','metabolik3',
            'kondisilain1','kondisilain2','kondisilain3','kondisilain4'
        ];

        // Ambil nilai dari POST, default 'Tidak'
        $values = [];
        foreach($enumFields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : 'Tidak';
            $values[$field] = ($val === 'Ya') ? 'Ya' : 'Tidak';
        }

        // Cek apakah sudah ada
        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_masuk_nicu WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            // UPDATE
            $setParts = [];
            foreach($values as $col => $val) {
                $setParts[] = "$col = '$val'";
            }
            $setParts[] = "tanggal = NOW()";
            $setParts[] = "nik = '$nik'";
            $setParts[] = "keputusan = '$keputusan'";
            $setParts[] = "keterangan = '$keterangan'";
            $q = "UPDATE checklist_kriteria_masuk_nicu SET " . implode(', ', $setParts) . " WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Checklist Kriteria Masuk NICU berhasil diperbarui';
        } else {
            // INSERT
            $cols = ['no_rawat', 'tanggal', 'nik', 'keputusan', 'keterangan'];
            $vals = ["'$no_rawat'", 'NOW()', "'$nik'", "'$keputusan'", "'$keterangan'"];
            foreach($values as $col => $val) {
                $cols[] = $col;
                $vals[] = "'$val'";
            }
            $q = "INSERT INTO checklist_kriteria_masuk_nicu (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $msg = 'Data Checklist Kriteria Masuk NICU berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS CHECKLIST KRITERIA MASUK NICU
// ============================================================
if($aksi === 'hapus_checklist_kriteria_masuk_nicu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_masuk_nicu WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM checklist_kriteria_masuk_nicu WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => 'Data Checklist Kriteria Masuk NICU berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// LOAD CHECKLIST KRITERIA MASUK PICU
// ============================================================
if($aksi === 'load_checklist_kriteria_masuk_picu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $q = "SELECT * FROM checklist_kriteria_masuk_picu WHERE no_rawat = '$no_rawat' LIMIT 1";
        $result = bukaquery($q);

        if(mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'empty', 'message' => 'Data tidak ditemukan']);
        }

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// SIMPAN CHECKLIST KRITERIA MASUK PICU
// ============================================================
if($aksi === 'simpan_checklist_kriteria_masuk_picu') {
    try {
        $no_rawat   = isset($_POST['no_rawat'])   ? trim($_POST['no_rawat'])   : '';
        $nik        = isset($_POST['nik'])        ? trim($_POST['nik'])        : '';
        $keputusan  = isset($_POST['keputusan'])  ? trim($_POST['keputusan'])  : 'Diterima Di PICU';
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';

        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Validasi keputusan
        $allowedKeputusan = ['Diterima Di PICU', 'Tidak Diterima', 'Dirawat Di Ruang Perawatan Biasa'];
        if(!in_array($keputusan, $allowedKeputusan)) {
            $keputusan = 'Diterima Di PICU';
        }

        // Daftar semua kolom enum
        $enumFields = [
            'kriteriaumum1','kriteriaumum2','kriteriaumum3',
            'respirasi1','respirasi2','respirasi3','respirasi4',
            'kardio1','kardio2','kardio3','kardio4',
            'neuro1','neuro2','neuro3','neuro4',
            'bedah1','bedah2','bedah3',
            'kondisilain1','kondisilain2','kondisilain3'
        ];

        // Ambil nilai dari POST, default 'Tidak'
        $values = [];
        foreach($enumFields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : 'Tidak';
            $values[$field] = ($val === 'Ya') ? 'Ya' : 'Tidak';
        }

        // Cek apakah sudah ada
        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_masuk_picu WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            // UPDATE
            $setParts = [];
            foreach($values as $col => $val) {
                $setParts[] = "$col = '$val'";
            }
            $setParts[] = "tanggal = NOW()";
            $setParts[] = "nik = '$nik'";
            $setParts[] = "keputusan = '$keputusan'";
            $setParts[] = "keterangan = '$keterangan'";
            $q = "UPDATE checklist_kriteria_masuk_picu SET " . implode(', ', $setParts) . " WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Checklist Kriteria Masuk PICU berhasil diperbarui';
        } else {
            // INSERT
            $cols = ['no_rawat', 'tanggal', 'nik', 'keputusan', 'keterangan'];
            $vals = ["'$no_rawat'", 'NOW()', "'$nik'", "'$keputusan'", "'$keterangan'"];
            foreach($values as $col => $val) {
                $cols[] = $col;
                $vals[] = "'$val'";
            }
            $q = "INSERT INTO checklist_kriteria_masuk_picu (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $msg = 'Data Checklist Kriteria Masuk PICU berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS CHECKLIST KRITERIA MASUK PICU
// ============================================================
if($aksi === 'hapus_checklist_kriteria_masuk_picu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_masuk_picu WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM checklist_kriteria_masuk_picu WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => 'Data Checklist Kriteria Masuk PICU berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// LOAD CHECKLIST KRITERIA KELUAR NICU
// ============================================================
if($aksi === 'load_checklist_kriteria_keluar_nicu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $q = "SELECT * FROM checklist_kriteria_keluar_nicu WHERE no_rawat = '$no_rawat' LIMIT 1";
        $result = bukaquery($q);

        if(mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'empty', 'message' => 'Data tidak ditemukan']);
        }

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// SIMPAN CHECKLIST KRITERIA KELUAR NICU
// ============================================================
if($aksi === 'simpan_checklist_kriteria_keluar_nicu') {
    try {
        $no_rawat   = isset($_POST['no_rawat'])   ? trim($_POST['no_rawat'])   : '';
        $nik        = isset($_POST['nik'])        ? trim($_POST['nik'])        : '';
        $keputusan  = isset($_POST['keputusan'])  ? trim($_POST['keputusan'])  : 'Layak Dipindahkan Ke Ruang Rawat Bayi/Rawat Gabung';
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';

        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        // Validasi keputusan
        $allowedKeputusan = ['Layak Dipindahkan Ke Ruang Rawat Bayi/Rawat Gabung', 'Belum Layak Keluar', 'Dirujuk Ke RS Lain'];
        if(!in_array($keputusan, $allowedKeputusan)) {
            $keputusan = 'Layak Dipindahkan Ke Ruang Rawat Bayi/Rawat Gabung';
        }

        // Daftar semua kolom enum
        $enumFields = [
            'respirasi1','respirasi2','respirasi3',
            'kardio1','kardio2',
            'nutrisi1','nutrisi2','nutrisi3',
            'suhutubuh1','suhutubuh2',
            'infeksi1','infeksi2','infeksi3'
        ];

        // Ambil nilai dari POST, default 'Tidak'
        $values = [];
        foreach($enumFields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : 'Tidak';
            $values[$field] = ($val === 'Ya') ? 'Ya' : 'Tidak';
        }

        // Cek apakah sudah ada
        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_keluar_nicu WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            // UPDATE
            $setParts = [];
            foreach($values as $col => $val) {
                $setParts[] = "$col = '$val'";
            }
            $setParts[] = "tanggal = NOW()";
            $setParts[] = "nik = '$nik'";
            $setParts[] = "keputusan = '$keputusan'";
            $setParts[] = "keterangan = '$keterangan'";
            $q = "UPDATE checklist_kriteria_keluar_nicu SET " . implode(', ', $setParts) . " WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Checklist Kriteria Keluar NICU berhasil diperbarui';
        } else {
            // INSERT
            $cols = ['no_rawat', 'tanggal', 'nik', 'keputusan', 'keterangan'];
            $vals = ["'$no_rawat'", 'NOW()', "'$nik'", "'$keputusan'", "'$keterangan'"];
            foreach($values as $col => $val) {
                $cols[] = $col;
                $vals[] = "'$val'";
            }
            $q = "INSERT INTO checklist_kriteria_keluar_nicu (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $msg = 'Data Checklist Kriteria Keluar NICU berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS CHECKLIST KRITERIA KELUAR NICU
// ============================================================
if($aksi === 'hapus_checklist_kriteria_keluar_nicu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_keluar_nicu WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM checklist_kriteria_keluar_nicu WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => 'Data Checklist Kriteria Keluar NICU berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// SIMPAN CHECKLIST KRITERIA KELUAR PICU
// ============================================================
if($aksi === 'simpan_checklist_kriteria_keluar_picu') {
    try {
        $no_rawat   = isset($_POST['no_rawat'])   ? trim($_POST['no_rawat'])   : '';
        $nik        = isset($_POST['nik'])        ? trim($_POST['nik'])        : '';
        $keputusan  = isset($_POST['keputusan'])  ? trim($_POST['keputusan'])  : 'Layak Keluar Dari PICU/Pindah Ke Ruang Rawat Biasa';
        $keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';

        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $allowedKeputusan = ['Layak Keluar Dari PICU/Pindah Ke Ruang Rawat Biasa', 'Belum Layak Keluar', 'Dirujuk Ke RS Lain'];
        if(!in_array($keputusan, $allowedKeputusan)) {
            $keputusan = 'Layak Keluar Dari PICU/Pindah Ke Ruang Rawat Biasa';
        }

        $enumFields = [
            'kondisiklinis1','kondisiklinis2','kondisiklinis3','kondisiklinis4','kondisiklinis5','kondisiklinis6',
            'kebutuhanperawatan1','kebutuhanperawatan2','kebutuhanperawatan3','kebutuhanperawatan4',
            'tindaklanjut1','tindaklanjut2','tindaklanjut3','tindaklanjut4'
        ];

        $values = [];
        foreach($enumFields as $field) {
            $val = isset($_POST[$field]) ? trim($_POST[$field]) : 'Tidak';
            $values[$field] = ($val === 'Ya') ? 'Ya' : 'Tidak';
        }

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_keluar_picu WHERE no_rawat = '$no_rawat'");

        if(mysqli_num_rows($cek) > 0) {
            $setParts = [];
            foreach($values as $col => $val) {
                $setParts[] = "$col = '$val'";
            }
            $setParts[] = "tanggal = NOW()";
            $setParts[] = "nik = '$nik'";
            $setParts[] = "keputusan = '$keputusan'";
            $setParts[] = "keterangan = '$keterangan'";
            $q = "UPDATE checklist_kriteria_keluar_picu SET " . implode(', ', $setParts) . " WHERE no_rawat = '$no_rawat'";
            $msg = 'Data Checklist Kriteria Keluar PICU berhasil diperbarui';
        } else {
            $cols = ['no_rawat', 'tanggal', 'nik', 'keputusan', 'keterangan'];
            $vals = ["'$no_rawat'", 'NOW()', "'$nik'", "'$keputusan'", "'$keterangan'"];
            foreach($values as $col => $val) {
                $cols[] = $col;
                $vals[] = "'$val'";
            }
            $q = "INSERT INTO checklist_kriteria_keluar_picu (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $msg = 'Data Checklist Kriteria Keluar PICU berhasil disimpan';
        }

        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menyimpan data ke database');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => $msg]);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================
// HAPUS CHECKLIST KRITERIA KELUAR PICU
// ============================================================
if($aksi === 'hapus_checklist_kriteria_keluar_picu') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? trim($_POST['no_rawat']) : '';
        if(empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $cek = bukaquery("SELECT no_rawat FROM checklist_kriteria_keluar_picu WHERE no_rawat = '$no_rawat'");
        if(mysqli_num_rows($cek) === 0) throw new Exception('Data tidak ditemukan');

        $q = "DELETE FROM checklist_kriteria_keluar_picu WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($q);
        if(!$result) throw new Exception('Gagal menghapus data');

        insertTracker($q);

        echo json_encode(['status' => 'success', 'message' => 'Data Checklist Kriteria Keluar PICU berhasil dihapus']);

    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN ECHO
// ========================================
if ($aksi === 'simpan_echo') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal          = isset($_POST['tanggal'])          ? $_POST['tanggal']                        : date('Y-m-d H:i:s');
        $sistolik         = isset($_POST['sistolik'])         ? validTeks4($_POST['sistolik'],        30) : '';
        $diastolic        = isset($_POST['diastolic'])        ? validTeks4($_POST['diastolic'],       30) : '';
        $kontraktilitas   = isset($_POST['kontraktilitas'])   ? validTeks4($_POST['kontraktilitas'],  30) : '';
        $dimensi_ruang    = isset($_POST['dimensi_ruang'])    ? validTeks4($_POST['dimensi_ruang'],   50) : '';
        $katup            = isset($_POST['katup'])            ? validTeks4($_POST['katup'],           50) : '';
        $analisa_segmental= isset($_POST['analisa_segmental'])? validTeks4($_POST['analisa_segmental'],100): '';
        $erap             = isset($_POST['erap'])             ? validTeks4($_POST['erap'],            15) : '';
        $lain_lain        = isset($_POST['lain_lain'])        ? validTeks4($_POST['lain_lain'],      100) : '';
        $kesimpulan       = isset($_POST['kesimpulan'])       ? validTeks4($_POST['kesimpulan'],     200) : '';

        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_echo WHERE no_rawat='$no_rawat'");

        if (mysqli_num_rows($rc) > 0) {
            // UPDATE
            $query = "UPDATE hasil_pemeriksaan_echo SET
                        tanggal='$tanggal', kd_dokter='$kd_dokter',
                        sistolik='$sistolik', diastolic='$diastolic',
                        kontraktilitas='$kontraktilitas', dimensi_ruang='$dimensi_ruang',
                        katup='$katup', analisa_segmental='$analisa_segmental',
                        erap='$erap', lain_lain='$lain_lain', kesimpulan='$kesimpulan'
                      WHERE no_rawat='$no_rawat'";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data');
            insertTracker($query);
            echo json_encode(['status'=>'success','message'=>'Data Echocardiography berhasil diupdate','action'=>'update']);
        } else {
            // INSERT
            $query = "INSERT INTO hasil_pemeriksaan_echo
                        (no_rawat, tanggal, kd_dokter, sistolik, diastolic, kontraktilitas,
                         dimensi_ruang, katup, analisa_segmental, erap, lain_lain, kesimpulan)
                      VALUES
                        ('$no_rawat','$tanggal','$kd_dokter','$sistolik','$diastolic','$kontraktilitas',
                         '$dimensi_ruang','$katup','$analisa_segmental','$erap','$lain_lain','$kesimpulan')";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data');
            insertTracker($query);

            // AUTO DOWNLOAD ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_echo_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_echo_base = defined('PEMERIKSAAN_ECHO_BASE_URL') ? PEMERIKSAAN_ECHO_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanecho/';
                                $_echo_parsed = parse_url($_echo_base); $_echo_host = isset($_echo_parsed['host']) ? $_echo_parsed['host'] : 'localhost';
                                $_echo_is_local = in_array($_echo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_echo_is_local) { $_echo_path = isset($_echo_parsed['path']) ? rtrim($_echo_parsed['path'], '/') : '/webapps/hasilpemeriksaanecho'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_echo_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/echo_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_echo_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_echo_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_pemeriksaan_echo_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode(['status'=>'success','message'=>'Data Echocardiography berhasil disimpan','action'=>'insert','images_downloaded'=>$images_downloaded]);
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS ECHO
// ========================================
if ($aksi === 'hapus_echo') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_echo WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_echo_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_echo_base = defined('PEMERIKSAAN_ECHO_BASE_URL') ? PEMERIKSAAN_ECHO_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanecho/';
            $_echo_parsed = parse_url($_echo_base); $_echo_host = isset($_echo_parsed['host']) ? $_echo_parsed['host'] : 'localhost';
            $_echo_is_local = in_array($_echo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_echo_is_local) {
                    $_echo_path = isset($_echo_parsed['path']) ? rtrim($_echo_parsed['path'], '/') : '/webapps/hasilpemeriksaanecho';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_echo_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_echo_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_echo_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_pemeriksaan_echo WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data berhasil dihapus','files_deleted'=>$fd]);
    } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
    exit();
}

// ========================================
// UPLOAD MANUAL - ECHO
// ========================================
if ($aksi === 'upload_manual_echo') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_echo WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_echo h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_echo_base = defined('PEMERIKSAAN_ECHO_BASE_URL') ? PEMERIKSAAN_ECHO_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanecho/';
        $_echo_parsed = parse_url($_echo_base); $_echo_host = isset($_echo_parsed['host']) ? $_echo_parsed['host'] : 'localhost';
        $_echo_is_local = in_array($_echo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_echo_is_local) { $_echo_path = isset($_echo_parsed['path']) ? rtrim($_echo_parsed['path'], '/') : '/webapps/hasilpemeriksaanecho'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_echo_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/echo_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_echo_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_echo_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_echo_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[ECHO-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[ECHO-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_echo_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// HAPUS SATU GAMBAR - ECHO
// ========================================
if ($aksi === 'hapus_gambar_echo') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo    = isset($_POST['photo'])    ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_echo WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_echo_base = defined('PEMERIKSAAN_ECHO_BASE_URL') ? PEMERIKSAAN_ECHO_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanecho/';
        $_echo_parsed = parse_url($_echo_base); $_echo_host = isset($_echo_parsed['host']) ? $_echo_parsed['host'] : 'localhost';
        $_echo_is_local = in_array($_echo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_echo_is_local) { $_echo_path = isset($_echo_parsed['path']) ? rtrim($_echo_parsed['path'], '/') : '/webapps/hasilpemeriksaanecho'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_echo_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_echo_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_pemeriksaan_echo_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// DOWNLOAD ORTHANC - ECHO
// ========================================
if ($aksi === 'download_orthanc_images_echo') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_echo h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_echo_base = defined('PEMERIKSAAN_ECHO_BASE_URL') ? PEMERIKSAAN_ECHO_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanecho/';
        $_echo_parsed = parse_url($_echo_base); $_echo_host = isset($_echo_parsed['host']) ? $_echo_parsed['host'] : 'localhost';
        $_echo_is_local = in_array($_echo_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_echo_is_local) { $_echo_path = isset($_echo_parsed['path']) ? rtrim($_echo_parsed['path'], '/') : '/webapps/hasilpemeriksaanecho'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_echo_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/echo_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_echo_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_echo_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_echo_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// SIMPAN ECHO PEDIATRIK
// ========================================
if ($aksi === 'simpan_echo_pediatrik') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');

        $tanggal                 = isset($_POST['tanggal'])                 ? $_POST['tanggal']                                       : date('Y-m-d H:i:s');
        $diagnosa_klinis         = isset($_POST['diagnosa_klinis'])         ? validTeks4($_POST['diagnosa_klinis'],          50)       : '';
        $kiriman_dari            = isset($_POST['kiriman_dari'])            ? validTeks4($_POST['kiriman_dari'],             50)       : '';
        $situs                   = isset($_POST['situs'])                   ? validTeks4($_POST['situs'],                   100)      : '';
        $av_va                   = isset($_POST['av_va'])                   ? validTeks4($_POST['av_va'],                   100)      : '';
        $drainase_vena_pulmonalis= isset($_POST['drainase_vena_pulmonalis'])? validTeks4($_POST['drainase_vena_pulmonalis'], 100)      : '';
        $katup_mitral            = isset($_POST['katup_mitral'])            ? validTeks4($_POST['katup_mitral'],             100)      : '';
        $katup_aorta             = isset($_POST['katup_aorta'])             ? validTeks4($_POST['katup_aorta'],              100)      : '';
        $katup_tricuspid         = isset($_POST['katup_tricuspid'])         ? validTeks4($_POST['katup_tricuspid'],          100)      : '';
        $katup_pulmonal          = isset($_POST['katup_pulmonal'])          ? validTeks4($_POST['katup_pulmonal'],           100)      : '';
        $katup_septum_atrium     = isset($_POST['katup_septum_atrium'])     ? validTeks4($_POST['katup_septum_atrium'],      100)      : '';
        $katup_septum_ventrikal  = isset($_POST['katup_septum_ventrikal'])  ? validTeks4($_POST['katup_septum_ventrikal'],   100)      : '';
        $katup_arkus_aorta       = isset($_POST['katup_arkus_aorta'])       ? validTeks4($_POST['katup_arkus_aorta'],        100)      : '';
        $katup_keterangan_lainnya= isset($_POST['katup_keterangan_lainnya'])? validTeks4($_POST['katup_keterangan_lainnya'], 100)      : '';
        $ruang_jantung           = isset($_POST['ruang_jantung'])           ? validTeks4($_POST['ruang_jantung'],            100)      : '';
        $mode_ivds               = isset($_POST['mode_ivds'])               ? validTeks4($_POST['mode_ivds'],                20)       : '';
        $mode_ivss               = isset($_POST['mode_ivss'])               ? validTeks4($_POST['mode_ivss'],                20)       : '';
        $mode_lvid_dextra        = isset($_POST['mode_lvid_dextra'])        ? validTeks4($_POST['mode_lvid_dextra'],         20)       : '';
        $mode_lvid_sinistra      = isset($_POST['mode_lvid_sinistra'])      ? validTeks4($_POST['mode_lvid_sinistra'],       20)       : '';
        $mode_lvpw_dextra        = isset($_POST['mode_lvpw_dextra'])        ? validTeks4($_POST['mode_lvpw_dextra'],         20)       : '';
        $mode_lvpw_sinistra      = isset($_POST['mode_lvpw_sinistra'])      ? validTeks4($_POST['mode_lvpw_sinistra'],       20)       : '';
        $mode_ejection_fraction  = isset($_POST['mode_ejection_fraction'])  ? validTeks4($_POST['mode_ejection_fraction'],   20)       : '';
        $mode_fraction_shotening = isset($_POST['mode_fraction_shotening']) ? validTeks4($_POST['mode_fraction_shotening'],  20)       : '';
        $doppler                 = isset($_POST['doppler'])                  ? validTeks4($_POST['doppler'],                 100)      : '';
        $kesimpulan              = isset($_POST['kesimpulan'])               ? validTeks4($_POST['kesimpulan'],              250)      : '';
        $saran                   = isset($_POST['saran'])                    ? validTeks4($_POST['saran'],                   100)      : '';

        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_echo_pediatrik WHERE no_rawat='$no_rawat'");

        if (mysqli_num_rows($rc) > 0) {
            // UPDATE
            $query = "UPDATE hasil_pemeriksaan_echo_pediatrik SET
                        tanggal='$tanggal', kd_dokter='$kd_dokter',
                        diagnosa_klinis='$diagnosa_klinis', kiriman_dari='$kiriman_dari',
                        situs='$situs', av_va='$av_va', drainase_vena_pulmonalis='$drainase_vena_pulmonalis',
                        katup_mitral='$katup_mitral', katup_aorta='$katup_aorta',
                        katup_tricuspid='$katup_tricuspid', katup_pulmonal='$katup_pulmonal',
                        katup_septum_atrium='$katup_septum_atrium', katup_septum_ventrikal='$katup_septum_ventrikal',
                        katup_arkus_aorta='$katup_arkus_aorta', katup_keterangan_lainnya='$katup_keterangan_lainnya',
                        ruang_jantung='$ruang_jantung',
                        mode_ivds='$mode_ivds', mode_ivss='$mode_ivss',
                        mode_lvid_dextra='$mode_lvid_dextra', mode_lvid_sinistra='$mode_lvid_sinistra',
                        mode_lvpw_dextra='$mode_lvpw_dextra', mode_lvpw_sinistra='$mode_lvpw_sinistra',
                        mode_ejection_fraction='$mode_ejection_fraction', mode_fraction_shotening='$mode_fraction_shotening',
                        doppler='$doppler', kesimpulan='$kesimpulan', saran='$saran'
                      WHERE no_rawat='$no_rawat'";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data');
            insertTracker($query);
            echo json_encode(['status'=>'success','message'=>'Data Echocardiography Pediatrik berhasil diupdate','action'=>'update']);
        } else {
            // INSERT
            $query = "INSERT INTO hasil_pemeriksaan_echo_pediatrik
                        (no_rawat, tanggal, kd_dokter,
                         diagnosa_klinis, kiriman_dari, situs, av_va, drainase_vena_pulmonalis,
                         katup_mitral, katup_aorta, katup_tricuspid, katup_pulmonal,
                         katup_septum_atrium, katup_septum_ventrikal, katup_arkus_aorta, katup_keterangan_lainnya,
                         ruang_jantung,
                         mode_ivds, mode_ivss, mode_lvid_dextra, mode_lvid_sinistra,
                         mode_lvpw_dextra, mode_lvpw_sinistra, mode_ejection_fraction, mode_fraction_shotening,
                         doppler, kesimpulan, saran)
                      VALUES
                        ('$no_rawat','$tanggal','$kd_dokter',
                         '$diagnosa_klinis','$kiriman_dari','$situs','$av_va','$drainase_vena_pulmonalis',
                         '$katup_mitral','$katup_aorta','$katup_tricuspid','$katup_pulmonal',
                         '$katup_septum_atrium','$katup_septum_ventrikal','$katup_arkus_aorta','$katup_keterangan_lainnya',
                         '$ruang_jantung',
                         '$mode_ivds','$mode_ivss','$mode_lvid_dextra','$mode_lvid_sinistra',
                         '$mode_lvpw_dextra','$mode_lvpw_sinistra','$mode_ejection_fraction','$mode_fraction_shotening',
                         '$doppler','$kesimpulan','$saran')";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data');
            insertTracker($query);

            // AUTO DOWNLOAD ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_echo_pediatrik_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_eped_base = defined('PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL') ? PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanechopediatrik/';
                                $_eped_parsed = parse_url($_eped_base); $_eped_host = isset($_eped_parsed['host']) ? $_eped_parsed['host'] : 'localhost';
                                $_eped_is_local = in_array($_eped_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_eped_is_local) { $_eped_path = isset($_eped_parsed['path']) ? rtrim($_eped_parsed['path'], '/') : '/webapps/hasilpemeriksaanechopediatrik'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_eped_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/echoped_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_eped_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_eped_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_pemeriksaan_echo_pediatrik_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode(['status'=>'success','message'=>'Data Echocardiography Pediatrik berhasil disimpan','action'=>'insert','images_downloaded'=>$images_downloaded]);
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}

// ========================================
// HAPUS ECHO PEDIATRIK
// ========================================
if ($aksi === 'hapus_echo_pediatrik') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_echo_pediatrik WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_echo_pediatrik_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_eped_base = defined('PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL') ? PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanechopediatrik/';
            $_eped_parsed = parse_url($_eped_base); $_eped_host = isset($_eped_parsed['host']) ? $_eped_parsed['host'] : 'localhost';
            $_eped_is_local = in_array($_eped_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_eped_is_local) {
                    $_eped_path = isset($_eped_parsed['path']) ? rtrim($_eped_parsed['path'], '/') : '/webapps/hasilpemeriksaanechopediatrik';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_eped_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_eped_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_echo_pediatrik_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_pemeriksaan_echo_pediatrik WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data berhasil dihapus','files_deleted'=>$fd]);
    } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
    exit();
}

// ========================================
// UPLOAD MANUAL - ECHO PEDIATRIK
// ========================================
if ($aksi === 'upload_manual_echo_pediatrik') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_echo_pediatrik WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_echo_pediatrik h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_eped_base = defined('PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL') ? PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanechopediatrik/';
        $_eped_parsed = parse_url($_eped_base); $_eped_host = isset($_eped_parsed['host']) ? $_eped_parsed['host'] : 'localhost';
        $_eped_is_local = in_array($_eped_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_eped_is_local) { $_eped_path = isset($_eped_parsed['path']) ? rtrim($_eped_parsed['path'], '/') : '/webapps/hasilpemeriksaanechopediatrik'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_eped_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/echoped_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_echo_pediatrik_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_eped_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_eped_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[ECHO-PED-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[ECHO-PED-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_echo_pediatrik_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// HAPUS SATU GAMBAR - ECHO PEDIATRIK
// ========================================
if ($aksi === 'hapus_gambar_echo_pediatrik') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo    = isset($_POST['photo'])    ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_echo_pediatrik WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_eped_base = defined('PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL') ? PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanechopediatrik/';
        $_eped_parsed = parse_url($_eped_base); $_eped_host = isset($_eped_parsed['host']) ? $_eped_parsed['host'] : 'localhost';
        $_eped_is_local = in_array($_eped_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_eped_is_local) { $_eped_path = isset($_eped_parsed['path']) ? rtrim($_eped_parsed['path'], '/') : '/webapps/hasilpemeriksaanechopediatrik'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_eped_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_eped_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_pemeriksaan_echo_pediatrik_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// DOWNLOAD ORTHANC - ECHO PEDIATRIK
// ========================================
if ($aksi === 'download_orthanc_images_echo_pediatrik') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_echo_pediatrik h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_eped_base = defined('PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL') ? PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanechopediatrik/';
        $_eped_parsed = parse_url($_eped_base); $_eped_host = isset($_eped_parsed['host']) ? $_eped_parsed['host'] : 'localhost';
        $_eped_is_local = in_array($_eped_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_eped_is_local) { $_eped_path = isset($_eped_parsed['path']) ? rtrim($_eped_parsed['path'], '/') : '/webapps/hasilpemeriksaanechopediatrik'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_eped_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/echoped_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_eped_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_eped_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_echo_pediatrik_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ============================================================
// HANDLER PEMERIKSAAN SLIT LAMP
// Menggunakan PEMERIKSAAN_SLIT_LAMP_BASE_URL (local vs remote)
// ============================================================
 
// Helper: resolusi path & mode (local vs remote) - reusable di semua aksi
function _slitlamp_storage_config() {
    $_sl_base     = defined('PEMERIKSAAN_SLIT_LAMP_BASE_URL')
                        ? PEMERIKSAAN_SLIT_LAMP_BASE_URL
                        : 'http://localhost/webapps/hasilpemeriksaanslitlamp/';
    $_sl_parsed   = parse_url($_sl_base);
    $_sl_host     = isset($_sl_parsed['host']) ? $_sl_parsed['host'] : 'localhost';
    $_sl_is_local = in_array($_sl_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
 
    if ($_sl_is_local) {
        $_sl_path = isset($_sl_parsed['path']) ? rtrim($_sl_parsed['path'], '/') : '/webapps/hasilpemeriksaanslitlamp';
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . $_sl_path . '/pages/upload/';
    } else {
        $upload_dir = sys_get_temp_dir() . '/slitlamp_upload/';
    }
 
    return [
        'base'     => $_sl_base,
        'is_local' => $_sl_is_local,
        'upload_dir'=> $upload_dir,
    ];
}
 
// Helper: kirim file ke remote via cURL ke receive_upload.php
function _slitlamp_remote_upload($base, $local_tmp_path, $dest_path, $mime = 'image/jpeg', $fname = '') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => rtrim($base, '/') . '/receive_upload.php',
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POSTFIELDS     => [
            'secret'    => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '',
            'dest_path' => $dest_path,
            'file'      => new CURLFile($local_tmp_path, $mime, $fname ?: basename($local_tmp_path)),
        ],
    ]);
    $response = curl_exec($ch);
    $curl_err  = curl_error($ch);
    curl_close($ch);
    if (file_exists($local_tmp_path)) unlink($local_tmp_path);
    if ($curl_err) return false;
    $json = json_decode($response, true);
    return isset($json['status']) && $json['status'] === 'success';
}
 
// Helper: hapus file di remote via cURL
function _slitlamp_remote_delete($base, $dest_path) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => rtrim($base, '/') . '/receive_upload.php',
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => [
            'secret'    => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '',
            'action'    => 'delete',
            'dest_path' => $dest_path,
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}
 
 
// ========================================
// 1. SIMPAN SLIT LAMP
// ========================================
if ($aksi == 'simpan_slit_lamp') {
    try {
        $no_rawat          = isset($_POST['no_rawat'])          ? validTeks4($_POST['no_rawat'],          20)  : '';
        $kd_dokter         = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
 
        $tanggal           = isset($_POST['tanggal'])           ? $_POST['tanggal']                            : date('Y-m-d H:i:s');
        $diagnosa_klinis   = isset($_POST['diagnosa_klinis'])   ? validTeks4($_POST['diagnosa_klinis'],   100) : '';
        $kiriman_dari      = isset($_POST['kiriman_dari'])      ? validTeks4($_POST['kiriman_dari'],       50) : '';
        $hasil_pemeriksaan = isset($_POST['hasil_pemeriksaan']) ? validTeks4($_POST['hasil_pemeriksaan'], 500) : '';
 
        $cek = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_slit_lamp WHERE no_rawat='$no_rawat'");
 
        if (mysqli_num_rows($cek) > 0) {
            // UPDATE
            $query = "UPDATE hasil_pemeriksaan_slit_lamp SET
                          tanggal='$tanggal', kd_dokter='$kd_dokter',
                          diagnosa_klinis='$diagnosa_klinis', kiriman_dari='$kiriman_dari',
                          hasil_pemeriksaan='$hasil_pemeriksaan'
                      WHERE no_rawat='$no_rawat'";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data');
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Data Slit Lamp berhasil diperbarui', 'action' => 'update']);
 
        } else {
            // INSERT
            $query = "INSERT INTO hasil_pemeriksaan_slit_lamp
                          (no_rawat, tanggal, kd_dokter, diagnosa_klinis, kiriman_dari, hasil_pemeriksaan)
                      VALUES
                          ('$no_rawat','$tanggal','$kd_dokter','$diagnosa_klinis','$kiriman_dari','$hasil_pemeriksaan')";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data');
            insertTracker($query);
 
            // AUTO DOWNLOAD ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_slit_lamp_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis'];
                        $sd   = date('Ymd', strtotime($tanggal));
 
                        // Cek koneksi Orthanc
                        $oa = @fsockopen(
                            defined('ORTHANC_URL')  ? ORTHANC_URL  : '192.168.88.52',
                            defined('ORTHANC_PORT') ? ORTHANC_PORT : '8042',
                            $en, $es, 1
                        );
                        if ($oa !== false) {
                            fclose($oa);
                            $cfg    = _slitlamp_storage_config();
                            $ud     = $cfg['upload_dir'];
                            if (!is_dir($ud)) mkdir($ud, 0755, true);
 
                            $orthanc = ApiOrthanc::fromConfig();
                            $thumbs  = $orthanc->getThumbnails($norm, $sd, 20);
 
                            if (!empty($thumbs)) {
                                foreach ($thumbs as $t) {
                                    $fn  = $norm . '_' . $sd . '_' . $images_downloaded . '.jpeg';
                                    $pp  = 'pages/upload/' . $fn;
                                    $img = base64_decode($t['base64']);
                                    if ($img === false) continue;
 
                                    if ($cfg['is_local']) {
                                        if (file_put_contents($ud . $fn, $img) === false) continue;
                                    } else {
                                        if (file_put_contents($ud . $fn, $img) === false) continue;
                                        if (!_slitlamp_remote_upload($cfg['base'], $ud . $fn, $pp, 'image/jpeg', $fn)) continue;
                                    }
 
                                    bukaquery("INSERT INTO hasil_pemeriksaan_slit_lamp_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");
                                    $images_downloaded++;
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) { /* Orthanc opsional, lanjut */ }
 
            echo json_encode([
                'status'           => 'success',
                'message'          => 'Data Slit Lamp berhasil disimpan',
                'action'           => 'insert',
                'images_downloaded'=> $images_downloaded,
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
 
 
// ========================================
// 2. HAPUS SLIT LAMP
// ========================================
if ($aksi == 'hapus_slit_lamp') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
 
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_slit_lamp WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');
 
        $cfg = _slitlamp_storage_config();
        $fd  = 0;
 
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_slit_lamp_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($cfg['is_local']) {
                    $_sl_parsed = parse_url($cfg['base']);
                    $_sl_path   = isset($_sl_parsed['path']) ? rtrim($_sl_parsed['path'], '/') : '/webapps/hasilpemeriksaanslitlamp';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_sl_path . '/' . $row['photo'];
                    if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    _slitlamp_remote_delete($cfg['base'], $row['photo']);
                    $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_slit_lamp_gambar WHERE no_rawat='$no_rawat'");
        }
 
        bukaquery("DELETE FROM hasil_pemeriksaan_slit_lamp WHERE no_rawat='$no_rawat'");
        echo json_encode(['status' => 'success', 'message' => 'Data Slit Lamp berhasil dihapus', 'files_deleted' => $fd]);
 
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
 
 
// ========================================
// 3. UPLOAD MANUAL GAMBAR SLIT LAMP
// ========================================
if ($aksi == 'upload_manual_slit_lamp') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
 
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_slit_lamp WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
 
        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file yang diupload');
 
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal
                         FROM hasil_pemeriksaan_slit_lamp h
                         INNER JOIN reg_periksa r ON h.no_rawat = r.no_rawat
                         WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt   = mysqli_fetch_assoc($qp);
        $norm = $pt['no_rkm_medis'];
        $sd   = date('Ymd', strtotime($pt['tanggal']));
 
        $cfg = _slitlamp_storage_config();
        $ud  = $cfg['upload_dir'];
        if (!is_dir($ud)) mkdir($ud, 0755, true);
 
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        $idx     = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_slit_lamp_gambar WHERE no_rawat='$no_rawat'"))['t'];
        $up      = 0;
        $errors  = [];
 
        foreach ($_FILES['images']['name'] as $i => $name) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $mime = mime_content_type($_FILES['images']['tmp_name'][$i]);
            if (!in_array($mime, $allowed)) { $errors[] = $name . ' bukan gambar valid'; continue; }
            if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) { $errors[] = $name . ' melebihi 5MB'; continue; }
 
            $ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'jpeg';
            $fn  = $norm . '_' . $sd . '_manual_' . ($idx + $up) . '.' . $ext;
            $pp  = 'pages/upload/' . $fn;
 
            if ($cfg['is_local']) {
                if (!move_uploaded_file($_FILES['images']['tmp_name'][$i], $ud . $fn)) {
                    $errors[] = 'Gagal upload ' . $name; continue;
                }
            } else {
                // Simpan sementara lalu kirim remote
                if (!move_uploaded_file($_FILES['images']['tmp_name'][$i], $ud . $fn)) {
                    $errors[] = 'Gagal upload ' . $name; continue;
                }
                if (!_slitlamp_remote_upload($cfg['base'], $ud . $fn, $pp, $mime, $fn)) {
                    $errors[] = 'Gagal kirim remote ' . $name; continue;
                }
            }
 
            bukaquery("INSERT INTO hasil_pemeriksaan_slit_lamp_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");
            $up++;
        }
 
        if ($up === 0) throw new Exception('Tidak ada gambar berhasil diupload. ' . implode(', ', $errors));
 
        $msg = $up . ' gambar berhasil diupload';
        if (!empty($errors)) $msg .= '. Gagal: ' . implode(', ', $errors);
        echo json_encode(['status' => 'success', 'message' => $msg, 'images_uploaded' => $up]);
 
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
 
 
// ========================================
// 4. HAPUS SATU GAMBAR SLIT LAMP
// ========================================
if ($aksi == 'hapus_gambar_slit_lamp') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo     = isset($_POST['photo'])    ? $_POST['photo']                    : '';
        if (empty($no_rawat) || empty($photo)) throw new Exception('Parameter tidak valid');
 
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_slit_lamp WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
 
        $cfg = _slitlamp_storage_config();
 
        if ($cfg['is_local']) {
            $_sl_parsed = parse_url($cfg['base']);
            $_sl_path   = isset($_sl_parsed['path']) ? rtrim($_sl_parsed['path'], '/') : '/webapps/hasilpemeriksaanslitlamp';
            $fp = $_SERVER['DOCUMENT_ROOT'] . $_sl_path . '/' . $photo;
            if (file_exists($fp)) unlink($fp);
        } else {
            _slitlamp_remote_delete($cfg['base'], $photo);
        }
 
        bukaquery("DELETE FROM hasil_pemeriksaan_slit_lamp_gambar WHERE no_rawat='$no_rawat' AND photo='" . addslashes($photo) . "'");
        echo json_encode(['status' => 'success', 'message' => 'Gambar berhasil dihapus']);
 
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
 
 
// ========================================
// 5. DOWNLOAD ORTHANC IMAGES SLIT LAMP
// ========================================
if ($aksi == 'download_orthanc_images_slit_lamp') {
    try {
        $no_rawat  = isset($_POST['no_rawat'])         ? validTeks4($_POST['no_rawat'], 20) : '';
        $si        = json_decode(isset($_POST['selected_instances']) ? $_POST['selected_instances'] : '[]', true);
 
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        if (empty($si))        throw new Exception('Pilih minimal 1 gambar');
 
        $rp = bukaquery("SELECT h.tanggal, r.no_rkm_medis
                         FROM hasil_pemeriksaan_slit_lamp h
                         INNER JOIN reg_periksa r ON h.no_rawat = r.no_rawat
                         WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp || mysqli_num_rows($rp) === 0) throw new Exception('Data pemeriksaan tidak ditemukan');
 
        $pt   = mysqli_fetch_assoc($rp);
        $norm = $pt['no_rkm_medis'];
        $sd   = date('Ymd', strtotime($pt['tanggal']));
 
        $orthanc = ApiOrthanc::fromConfig();
        $thumbs  = $orthanc->getThumbnails($norm, $sd, 20);
        if (empty($thumbs)) throw new Exception('Tidak ada gambar di Orthanc');
 
        $cfg = _slitlamp_storage_config();
        $ud  = $cfg['upload_dir'];
        if (!is_dir($ud)) mkdir($ud, 0755, true);
 
        $dl = 0;
        foreach ($thumbs as $index => $t) {
            if (!in_array($index, $si)) continue;
 
            $fn  = $norm . '_' . $sd . '_' . $index . '.jpeg';
            $pp  = 'pages/upload/' . $fn;
            $img = base64_decode($t['base64']);
            if ($img === false) continue;
 
            if ($cfg['is_local']) {
                if (file_put_contents($ud . $fn, $img) === false) continue;
            } else {
                if (file_put_contents($ud . $fn, $img) === false) continue;
                if (!_slitlamp_remote_upload($cfg['base'], $ud . $fn, $pp, 'image/jpeg', $fn)) continue;
            }
 
            bukaquery("INSERT INTO hasil_pemeriksaan_slit_lamp_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");
            $dl++;
        }
 
        if ($dl === 0) throw new Exception('Gagal mengunduh gambar dari Orthanc');
        echo json_encode(['status' => 'success', 'message' => $dl . ' gambar berhasil disimpan', 'images_downloaded' => $dl]);
 
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ========================================
// SIMPAN OCT
// ========================================
if ($aksi === 'simpan_oct') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
 
        $tanggal           = isset($_POST['tanggal'])           ? $_POST['tanggal']                              : date('Y-m-d H:i:s');
        $diagnosa_klinis   = isset($_POST['diagnosa_klinis'])   ? validTeks4($_POST['diagnosa_klinis'],   50)    : '';
        $kiriman_dari      = isset($_POST['kiriman_dari'])      ? validTeks4($_POST['kiriman_dari'],      50)    : '';
        $hasil_pemeriksaan = isset($_POST['hasil_pemeriksaan']) ? validTeks4($_POST['hasil_pemeriksaan'], 1000)  : '';
 
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_oct WHERE no_rawat='$no_rawat'");
 
        if (mysqli_num_rows($rc) > 0) {
            // UPDATE
            $query = "UPDATE hasil_pemeriksaan_oct SET
                        tanggal='$tanggal', kd_dokter='$kd_dokter',
                        diagnosa_klinis='$diagnosa_klinis', kiriman_dari='$kiriman_dari',
                        hasil_pemeriksaan='$hasil_pemeriksaan'
                      WHERE no_rawat='$no_rawat'";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data');
            insertTracker($query);
            echo json_encode(['status'=>'success','message'=>'Data Pemeriksaan OCT berhasil diupdate','action'=>'update']);
        } else {
            // INSERT
            $query = "INSERT INTO hasil_pemeriksaan_oct
                        (no_rawat, tanggal, kd_dokter,
                         diagnosa_klinis, kiriman_dari, hasil_pemeriksaan)
                      VALUES
                        ('$no_rawat','$tanggal','$kd_dokter',
                         '$diagnosa_klinis','$kiriman_dari','$hasil_pemeriksaan')";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data');
            insertTracker($query);
 
            // AUTO DOWNLOAD ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_oct_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_oct_base = defined('PEMERIKSAAN_OCT_BASE_URL') ? PEMERIKSAAN_OCT_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanoct/';
                                $_oct_parsed = parse_url($_oct_base); $_oct_host = isset($_oct_parsed['host']) ? $_oct_parsed['host'] : 'localhost';
                                $_oct_is_local = in_array($_oct_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_oct_is_local) { $_oct_path = isset($_oct_parsed['path']) ? rtrim($_oct_parsed['path'], '/') : '/webapps/hasilpemeriksaanoct'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_oct_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/oct_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_oct_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_oct_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_pemeriksaan_oct_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode(['status'=>'success','message'=>'Data Pemeriksaan OCT berhasil disimpan','action'=>'insert','images_downloaded'=>$images_downloaded]);
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}
 
// ========================================
// HAPUS OCT
// ========================================
if ($aksi === 'hapus_oct') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_oct WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_oct_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_oct_base = defined('PEMERIKSAAN_OCT_BASE_URL') ? PEMERIKSAAN_OCT_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanoct/';
            $_oct_parsed = parse_url($_oct_base); $_oct_host = isset($_oct_parsed['host']) ? $_oct_parsed['host'] : 'localhost';
            $_oct_is_local = in_array($_oct_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_oct_is_local) {
                    $_oct_path = isset($_oct_parsed['path']) ? rtrim($_oct_parsed['path'], '/') : '/webapps/hasilpemeriksaanoct';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_oct_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_oct_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_oct_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_pemeriksaan_oct WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data berhasil dihapus','files_deleted'=>$fd]);
    } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
    exit();
}
 
// ========================================
// UPLOAD MANUAL - OCT
// ========================================
if ($aksi === 'upload_manual_oct') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_oct WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_oct h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_oct_base = defined('PEMERIKSAAN_OCT_BASE_URL') ? PEMERIKSAAN_OCT_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanoct/';
        $_oct_parsed = parse_url($_oct_base); $_oct_host = isset($_oct_parsed['host']) ? $_oct_parsed['host'] : 'localhost';
        $_oct_is_local = in_array($_oct_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_oct_is_local) { $_oct_path = isset($_oct_parsed['path']) ? rtrim($_oct_parsed['path'], '/') : '/webapps/hasilpemeriksaanoct'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_oct_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/oct_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_oct_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_oct_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_oct_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[OCT-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[OCT-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_oct_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// HAPUS SATU GAMBAR - OCT
// ========================================
if ($aksi === 'hapus_gambar_oct') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo    = isset($_POST['photo'])    ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_oct WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_oct_base = defined('PEMERIKSAAN_OCT_BASE_URL') ? PEMERIKSAAN_OCT_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanoct/';
        $_oct_parsed = parse_url($_oct_base); $_oct_host = isset($_oct_parsed['host']) ? $_oct_parsed['host'] : 'localhost';
        $_oct_is_local = in_array($_oct_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_oct_is_local) { $_oct_path = isset($_oct_parsed['path']) ? rtrim($_oct_parsed['path'], '/') : '/webapps/hasilpemeriksaanoct'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_oct_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_oct_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_pemeriksaan_oct_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// DOWNLOAD ORTHANC - OCT
// ========================================
if ($aksi === 'download_orthanc_images_oct') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_oct h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_oct_base = defined('PEMERIKSAAN_OCT_BASE_URL') ? PEMERIKSAAN_OCT_BASE_URL : 'http://localhost/webapps/hasilpemeriksaanoct/';
        $_oct_parsed = parse_url($_oct_base); $_oct_host = isset($_oct_parsed['host']) ? $_oct_parsed['host'] : 'localhost';
        $_oct_is_local = in_array($_oct_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_oct_is_local) { $_oct_path = isset($_oct_parsed['path']) ? rtrim($_oct_parsed['path'], '/') : '/webapps/hasilpemeriksaanoct'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_oct_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/oct_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_oct_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_oct_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_oct_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// SIMPAN TREADMILL
// ========================================
if ($aksi === 'simpan_treadmill') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
 
        $tanggal                = isset($_POST['tanggal'])                ? $_POST['tanggal']                                        : date('Y-m-d H:i:s');
        $kiriman_dari           = isset($_POST['kiriman_dari'])           ? validTeks4($_POST['kiriman_dari'],            50)         : '';
        $diagnosa_klinis        = isset($_POST['diagnosa_klinis'])        ? validTeks4($_POST['diagnosa_klinis'],         50)         : '';
        $protokol               = isset($_POST['protokol'])               ? validTeks4($_POST['protokol'],                30)         : '';
        $keterangan_protokol    = isset($_POST['keterangan_protokol'])    ? validTeks4($_POST['keterangan_protokol'],     30)         : '';
        $td_awal                = isset($_POST['td_awal'])                ? validTeks4($_POST['td_awal'],                 8)          : '';
        $nadi_awal              = isset($_POST['nadi_awal'])              ? validTeks4($_POST['nadi_awal'],               5)          : '';
        $denyut_jantung_maksimal= isset($_POST['denyut_jantung_maksimal'])? validTeks4($_POST['denyut_jantung_maksimal'], 5)          : '';
        $hasil_pemeriksaan      = isset($_POST['hasil_pemeriksaan'])      ? validTeks4($_POST['hasil_pemeriksaan'],       1000)       : '';
        $temuan_ekg             = isset($_POST['temuan_ekg'])             ? validTeks4($_POST['temuan_ekg'],              200)        : '';
        $kapasitas_fungsional   = isset($_POST['kapasitas_fungsional'])   ? validTeks4($_POST['kapasitas_fungsional'],    200)        : '';
        $interpretasi           = isset($_POST['interpretasi'])           ? validTeks4($_POST['interpretasi'],            200)        : '';
        $kesimpulan             = isset($_POST['kesimpulan'])             ? validTeks4($_POST['kesimpulan'],              300)        : '';
 
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_treadmill WHERE no_rawat='$no_rawat'");
 
        if (mysqli_num_rows($rc) > 0) {
            // UPDATE
            $query = "UPDATE hasil_pemeriksaan_treadmill SET
                        tanggal='$tanggal', kd_dokter='$kd_dokter',
                        kiriman_dari='$kiriman_dari', diagnosa_klinis='$diagnosa_klinis',
                        protokol='$protokol', keterangan_protokol='$keterangan_protokol',
                        td_awal='$td_awal', nadi_awal='$nadi_awal',
                        denyut_jantung_maksimal='$denyut_jantung_maksimal',
                        hasil_pemeriksaan='$hasil_pemeriksaan', temuan_ekg='$temuan_ekg',
                        kapasitas_fungsional='$kapasitas_fungsional',
                        interpretasi='$interpretasi', kesimpulan='$kesimpulan'
                      WHERE no_rawat='$no_rawat'";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal mengupdate data');
            insertTracker($query);
            echo json_encode(['status'=>'success','message'=>'Data Pemeriksaan Treadmill berhasil diupdate','action'=>'update']);
        } else {
            // INSERT
            $query = "INSERT INTO hasil_pemeriksaan_treadmill
                        (no_rawat, tanggal, kd_dokter,
                         kiriman_dari, diagnosa_klinis,
                         protokol, keterangan_protokol,
                         td_awal, nadi_awal, denyut_jantung_maksimal,
                         hasil_pemeriksaan, temuan_ekg,
                         kapasitas_fungsional, interpretasi, kesimpulan)
                      VALUES
                        ('$no_rawat','$tanggal','$kd_dokter',
                         '$kiriman_dari','$diagnosa_klinis',
                         '$protokol','$keterangan_protokol',
                         '$td_awal','$nadi_awal','$denyut_jantung_maksimal',
                         '$hasil_pemeriksaan','$temuan_ekg',
                         '$kapasitas_fungsional','$interpretasi','$kesimpulan')";
            $result = bukaquery($query);
            if (!$result) throw new Exception('Gagal menyimpan data');
            insertTracker($query);
 
            // AUTO DOWNLOAD ORTHANC
            $images_downloaded = 0;
            try {
                $qci = bukaquery("SELECT COUNT(*) as total FROM hasil_pemeriksaan_treadmill_gambar WHERE no_rawat='$no_rawat'");
                if (mysqli_fetch_assoc($qci)['total'] == 0) {
                    $qp = bukaquery("SELECT r.no_rkm_medis FROM reg_periksa r WHERE r.no_rawat='$no_rawat' LIMIT 1");
                    if ($qp && mysqli_num_rows($qp) > 0) {
                        $norm = mysqli_fetch_assoc($qp)['no_rkm_medis']; $sd = date('Ymd', strtotime($tanggal));
                        $oa = @fsockopen(defined('ORTHANC_URL')?ORTHANC_URL:'http://192.168.88.52', defined('ORTHANC_PORT')?ORTHANC_PORT:'8042', $en, $es, 1);
                        if ($oa !== false) {
                            fclose($oa); $orthanc = ApiOrthanc::fromConfig(); $thumbs = $orthanc->getThumbnails($norm, $sd, 20);
                            if (!empty($thumbs)) {
                                $_treadmill_base = defined('PEMERIKSAAN_TREADMILL_BASE_URL') ? PEMERIKSAAN_TREADMILL_BASE_URL : 'http://localhost/webapps/hasilpemeriksaantreadmill/';
                                $_treadmill_parsed = parse_url($_treadmill_base); $_treadmill_host = isset($_treadmill_parsed['host']) ? $_treadmill_parsed['host'] : 'localhost';
                                $_treadmill_is_local = in_array($_treadmill_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
                                if ($_treadmill_is_local) { $_treadmill_path = isset($_treadmill_parsed['path']) ? rtrim($_treadmill_parsed['path'], '/') : '/webapps/hasilpemeriksaantreadmill'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_treadmill_path . '/pages/upload/'; }
                                else { $ud = sys_get_temp_dir() . '/treadmill_upload/'; }
                                if (!is_dir($ud)) mkdir($ud, 0755, true);
                                foreach ($thumbs as $t) {
                                    $fn = $norm.'_'.$sd.'_'.$images_downloaded.'.jpeg';
                                    if (isset($t['base64']) && ($img = base64_decode($t['base64'])) !== false && file_put_contents($ud.$fn, $img) !== false) {
                                        $pp = 'pages/upload/'.$fn;
                                        if (!$_treadmill_is_local) {
                                            $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_treadmill_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'dest_path' => $pp, 'file' => new CURLFile($ud.$fn, 'image/jpeg', $fn)]]);
                                            $cr = curl_exec($ch); curl_close($ch); if (file_exists($ud.$fn)) unlink($ud.$fn);
                                            $cj = json_decode($cr, true); if (!isset($cj['status']) || $cj['status'] !== 'success') continue;
                                        }
                                        bukaquery("INSERT INTO hasil_pemeriksaan_treadmill_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')"); $images_downloaded++;
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {}
            echo json_encode(['status'=>'success','message'=>'Data Pemeriksaan Treadmill berhasil disimpan','action'=>'insert','images_downloaded'=>$images_downloaded]);
        }
    } catch (Exception $e) {
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit();
}
 
// ========================================
// HAPUS TREADMILL
// ========================================
if ($aksi === 'hapus_treadmill') {
    try {
        $no_rawat  = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_treadmill WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data tidak ditemukan atau bukan milik Anda');
        $fd = 0;
        $ri = bukaquery("SELECT photo FROM hasil_pemeriksaan_treadmill_gambar WHERE no_rawat='$no_rawat'");
        if ($ri && mysqli_num_rows($ri) > 0) {
            $_treadmill_base = defined('PEMERIKSAAN_TREADMILL_BASE_URL') ? PEMERIKSAAN_TREADMILL_BASE_URL : 'http://localhost/webapps/hasilpemeriksaantreadmill/';
            $_treadmill_parsed = parse_url($_treadmill_base); $_treadmill_host = isset($_treadmill_parsed['host']) ? $_treadmill_parsed['host'] : 'localhost';
            $_treadmill_is_local = in_array($_treadmill_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
            while ($row = mysqli_fetch_assoc($ri)) {
                if ($_treadmill_is_local) {
                    $_treadmill_path = isset($_treadmill_parsed['path']) ? rtrim($_treadmill_parsed['path'], '/') : '/webapps/hasilpemeriksaantreadmill';
                    $fp = $_SERVER['DOCUMENT_ROOT'] . $_treadmill_path . '/' . $row['photo']; if (file_exists($fp) && unlink($fp)) $fd++;
                } else {
                    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => rtrim($_treadmill_base, '/') . '/receive_upload.php', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POSTFIELDS => ['secret' => defined('BERKAS_UPLOAD_SECRET') ? BERKAS_UPLOAD_SECRET : '', 'action' => 'delete', 'dest_path' => $row['photo']]]); curl_exec($ch); curl_close($ch); $fd++;
                }
            }
            bukaquery("DELETE FROM hasil_pemeriksaan_treadmill_gambar WHERE no_rawat='$no_rawat'");
        }
        bukaquery("DELETE FROM hasil_pemeriksaan_treadmill WHERE no_rawat='$no_rawat'");
        echo json_encode(['status'=>'success','message'=>'Data berhasil dihapus','files_deleted'=>$fd]);
    } catch (Exception $e) { echo json_encode(['status'=>'error','message'=>$e->getMessage()]); }
    exit();
}
 
// ========================================
// UPLOAD MANUAL - TREADMILL
// ========================================
if ($aksi === 'upload_manual_treadmill') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_treadmill WHERE no_rawat='$no_rawat'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Simpan data form terlebih dahulu');
        if (!isset($_FILES['images'])||empty($_FILES['images']['name'][0])) throw new Exception('Tidak ada file');
        $qp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_treadmill h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        $pt = mysqli_fetch_assoc($qp); $norm=$pt['no_rkm_medis']; $sd=date('Ymd',strtotime($pt['tanggal']));
        $_treadmill_base = defined('PEMERIKSAAN_TREADMILL_BASE_URL') ? PEMERIKSAAN_TREADMILL_BASE_URL : 'http://localhost/webapps/hasilpemeriksaantreadmill/';
        $_treadmill_parsed = parse_url($_treadmill_base); $_treadmill_host = isset($_treadmill_parsed['host']) ? $_treadmill_parsed['host'] : 'localhost';
        $_treadmill_is_local = in_array($_treadmill_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_treadmill_is_local) { $_treadmill_path = isset($_treadmill_parsed['path']) ? rtrim($_treadmill_parsed['path'], '/') : '/webapps/hasilpemeriksaantreadmill'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_treadmill_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/treadmill_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);
        $allowed=['image/jpeg','image/png','image/gif','image/webp','image/bmp'];
        $idx = mysqli_fetch_assoc(bukaquery("SELECT COUNT(*) as t FROM hasil_pemeriksaan_treadmill_gambar WHERE no_rawat='$no_rawat'"))['t']; $up=0;
        foreach($_FILES['images']['name'] as $i=>$name){
            if($_FILES['images']['error'][$i]!==UPLOAD_ERR_OK)continue;if(!in_array($_FILES['images']['type'][$i],$allowed))continue;if($_FILES['images']['size'][$i]>5*1024*1024)continue;
            $ext=pathinfo($name,PATHINFO_EXTENSION)?:'jpeg';$fn=$norm.'_'.$sd.'_manual_'.($idx+$up).'.'.$ext;
            if(move_uploaded_file($_FILES['images']['tmp_name'][$i],$ud.$fn)){
                $pp='pages/upload/'.$fn;
                if(!$_treadmill_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_treadmill_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,$_FILES['images']['type'][$i],$fn)]]);
                    $cr=curl_exec($ch);$ce=curl_error($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    if($ce){error_log("[TREADMILL-UPLOAD] cURL: $ce");continue;}
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success'){error_log("[TREADMILL-UPLOAD] Remote gagal: $cr");continue;}
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_treadmill_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$up++;
            }
        }
        if($up===0)throw new Exception('Tidak ada gambar berhasil diupload');
        echo json_encode(['status'=>'success','message'=>$up.' gambar diupload','images_uploaded'=>$up]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// HAPUS SATU GAMBAR - TREADMILL
// ========================================
if ($aksi === 'hapus_gambar_treadmill') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        $photo    = isset($_POST['photo'])    ? $_POST['photo'] : '';
        if (empty($no_rawat)||empty($photo)) throw new Exception('Parameter tidak valid');
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        $rc = bukaquery("SELECT no_rawat FROM hasil_pemeriksaan_treadmill WHERE no_rawat='$no_rawat' AND kd_dokter='$kd_dokter'");
        if (mysqli_num_rows($rc) === 0) throw new Exception('Data bukan milik Anda');
        $_treadmill_base = defined('PEMERIKSAAN_TREADMILL_BASE_URL') ? PEMERIKSAAN_TREADMILL_BASE_URL : 'http://localhost/webapps/hasilpemeriksaantreadmill/';
        $_treadmill_parsed = parse_url($_treadmill_base); $_treadmill_host = isset($_treadmill_parsed['host']) ? $_treadmill_parsed['host'] : 'localhost';
        $_treadmill_is_local = in_array($_treadmill_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_treadmill_is_local) { $_treadmill_path = isset($_treadmill_parsed['path']) ? rtrim($_treadmill_parsed['path'], '/') : '/webapps/hasilpemeriksaantreadmill'; $fp = $_SERVER['DOCUMENT_ROOT'] . $_treadmill_path . '/' . $photo; if(file_exists($fp))unlink($fp); }
        else { $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_treadmill_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','action'=>'delete','dest_path'=>$photo]]);curl_exec($ch);curl_close($ch); }
        bukaquery("DELETE FROM hasil_pemeriksaan_treadmill_gambar WHERE no_rawat='$no_rawat' AND photo='".addslashes($photo)."'");
        echo json_encode(['status'=>'success','message'=>'Gambar dihapus']);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}
 
// ========================================
// DOWNLOAD ORTHANC - TREADMILL
// ========================================
if ($aksi === 'download_orthanc_images_treadmill') {
    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 20) : '';
        if (empty($no_rawat)) throw new Exception('No. Rawat tidak valid');
        $si = json_decode(isset($_POST['selected_indices'])?$_POST['selected_indices']:'[]', true);
        if (empty($si)) throw new Exception('Tidak ada gambar dipilih');
        $rp = bukaquery("SELECT r.no_rkm_medis, h.tanggal FROM hasil_pemeriksaan_treadmill h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat' LIMIT 1");
        if (!$rp||mysqli_num_rows($rp)===0) throw new Exception('Data tidak ditemukan');
        $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));
        $orthanc=ApiOrthanc::fromConfig();$thumbs=$orthanc->getThumbnails($norm,$sd,20);
        if(empty($thumbs))throw new Exception('Tidak ada gambar di Orthanc');
        $_treadmill_base = defined('PEMERIKSAAN_TREADMILL_BASE_URL') ? PEMERIKSAAN_TREADMILL_BASE_URL : 'http://localhost/webapps/hasilpemeriksaantreadmill/';
        $_treadmill_parsed = parse_url($_treadmill_base); $_treadmill_host = isset($_treadmill_parsed['host']) ? $_treadmill_parsed['host'] : 'localhost';
        $_treadmill_is_local = in_array($_treadmill_host, ['localhost', '127.0.0.1', $_SERVER['SERVER_ADDR'] ?? '']);
        if ($_treadmill_is_local) { $_treadmill_path = isset($_treadmill_parsed['path']) ? rtrim($_treadmill_parsed['path'], '/') : '/webapps/hasilpemeriksaantreadmill'; $ud = $_SERVER['DOCUMENT_ROOT'] . $_treadmill_path . '/pages/upload/'; }
        else { $ud = sys_get_temp_dir() . '/treadmill_upload/'; }
        if(!is_dir($ud))mkdir($ud,0755,true);$dl=0;
        foreach($thumbs as $index=>$t){
            if(!in_array($index,$si))continue;$fn=$norm.'_'.$sd.'_'.$index.'.jpeg';$img=base64_decode($t['base64']);
            if($img!==false&&file_put_contents($ud.$fn,$img)!==false){
                $pp='pages/upload/'.$fn;
                if(!$_treadmill_is_local){
                    $ch=curl_init();curl_setopt_array($ch,[CURLOPT_URL=>rtrim($_treadmill_base,'/').'/receive_upload.php',CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>['secret'=>defined('BERKAS_UPLOAD_SECRET')?BERKAS_UPLOAD_SECRET:'','dest_path'=>$pp,'file'=>new CURLFile($ud.$fn,'image/jpeg',$fn)]]);
                    $cr=curl_exec($ch);curl_close($ch);if(file_exists($ud.$fn))unlink($ud.$fn);
                    $cj=json_decode($cr,true);if(!isset($cj['status'])||$cj['status']!=='success')continue;
                }
                bukaquery("INSERT INTO hasil_pemeriksaan_treadmill_gambar (no_rawat, photo) VALUES ('$no_rawat','$pp')");$dl++;
            }
        }
        echo json_encode(['status'=>'success','message'=>'Gambar disimpan','images_downloaded'=>$dl]);
    } catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
    exit();
}

// ========================================
// SIMPAN SURAT KETERANGAN BEBAS NARKOBA
// ========================================
if ($aksi === 'simpan_surat_keterangan_bebas_narkoba') {

    try {
        $no_surat       = isset($_POST['no_surat'])       ? validTeks4($_POST['no_surat'], 25)       : '';
        $no_rawat       = isset($_POST['no_rawat'])       ? validTeks4($_POST['no_rawat'], 17)       : '';
        $tanggalsurat   = isset($_POST['tanggalsurat'])   ? validTeks4($_POST['tanggalsurat'], 10)   : date('Y-m-d');
        $kategori       = isset($_POST['kategori'])       ? validTeks4($_POST['kategori'], 5)        : 'UMUM';
        $kd_dokter      = isset($_POST['kd_dokter'])      ? validTeks4($_POST['kd_dokter'], 20)      : '';
        $keperluan      = isset($_POST['keperluan'])      ? validTeks4($_POST['keperluan'], 300)     : '';
        $opiat          = isset($_POST['opiat'])          ? validTeks4($_POST['opiat'], 7)           : 'NEGATIF';
        $ganja          = isset($_POST['ganja'])          ? validTeks4($_POST['ganja'], 7)           : 'NEGATIF';
        $amphetamin     = isset($_POST['amphetamin'])     ? validTeks4($_POST['amphetamin'], 7)      : 'NEGATIF';
        $methamphetamin = isset($_POST['methamphetamin']) ? validTeks4($_POST['methamphetamin'], 7)  : 'NEGATIF';
        $benzodiazepin  = isset($_POST['benzodiazepin'])  ? validTeks4($_POST['benzodiazepin'], 7)   : 'NEGATIF';
        $cocain         = isset($_POST['cocain'])         ? validTeks4($_POST['cocain'], 7)          : 'NEGATIF';

        if (empty($no_surat) || empty($no_rawat)) {
            throw new Exception('No. Surat dan No. Rawat tidak boleh kosong');
        }

        $cekExist = bukaquery("SELECT no_surat FROM surat_skbn WHERE no_rawat = '$no_rawat'");

        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            $query = "UPDATE surat_skbn SET
                        no_surat       = '$no_surat',
                        tanggalsurat   = '$tanggalsurat',
                        kategori       = '$kategori',
                        kd_dokter      = '$kd_dokter',
                        keperluan      = '$keperluan',
                        opiat          = '$opiat',
                        ganja          = '$ganja',
                        amphetamin     = '$amphetamin',
                        methamphetamin = '$methamphetamin',
                        benzodiazepin  = '$benzodiazepin',
                        cocain         = '$cocain'
                      WHERE no_rawat = '$no_rawat'";
            $msg = 'Surat keterangan bebas narkoba berhasil diperbarui';
        } else {
            $query = "INSERT INTO surat_skbn (
                        no_surat, no_rawat, tanggalsurat, kategori, kd_dokter,
                        keperluan, opiat, ganja, amphetamin, methamphetamin, benzodiazepin, cocain
                      ) VALUES (
                        '$no_surat', '$no_rawat', '$tanggalsurat', '$kategori', '$kd_dokter',
                        '$keperluan', '$opiat', '$ganja', '$amphetamin', '$methamphetamin', '$benzodiazepin', '$cocain'
                      )";
            $msg = 'Surat keterangan bebas narkoba berhasil disimpan';
        }

        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan surat keterangan bebas narkoba');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// HAPUS SURAT KETERANGAN BEBAS NARKOBA
// ========================================
if ($aksi === 'hapus_surat_keterangan_bebas_narkoba') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $cekData = bukaquery("SELECT no_surat FROM surat_skbn WHERE no_rawat = '$no_rawat'");

        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }

        $query  = "DELETE FROM surat_skbn WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Surat keterangan bebas narkoba berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// SIMPAN SURAT KETERANGAN BEBAS TBC
// ========================================
if ($aksi === 'simpan_surat_keterangan_bebas_tbc') {

    try {
        $no_surat     = isset($_POST['no_surat'])     ? validTeks4($_POST['no_surat'], 25)     : '';
        $no_rawat     = isset($_POST['no_rawat'])     ? validTeks4($_POST['no_rawat'], 17)     : '';
        $tanggalsurat = isset($_POST['tanggalsurat']) ? validTeks4($_POST['tanggalsurat'], 10) : date('Y-m-d');
        $kd_dokter    = isset($_POST['kd_dokter'])    ? validTeks4($_POST['kd_dokter'], 20)    : '';
        $keperluan    = isset($_POST['keperluan'])    ? validTeks4($_POST['keperluan'], 50)    : '';

        if (empty($no_surat) || empty($no_rawat)) {
            throw new Exception('No. Surat dan No. Rawat tidak boleh kosong');
        }

        $cekExist = bukaquery("SELECT no_surat FROM surat_bebas_tbc WHERE no_rawat = '$no_rawat'");

        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            $query = "UPDATE surat_bebas_tbc SET
                        no_surat     = '$no_surat',
                        tanggalsurat = '$tanggalsurat',
                        kd_dokter    = '$kd_dokter',
                        keperluan    = '$keperluan'
                      WHERE no_rawat = '$no_rawat'";
            $msg = 'Surat keterangan bebas TBC berhasil diperbarui';
        } else {
            $query = "INSERT INTO surat_bebas_tbc (
                        no_surat, no_rawat, tanggalsurat, kd_dokter, keperluan
                      ) VALUES (
                        '$no_surat', '$no_rawat', '$tanggalsurat', '$kd_dokter', '$keperluan'
                      )";
            $msg = 'Surat keterangan bebas TBC berhasil disimpan';
        }

        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan surat keterangan bebas TBC');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// HAPUS SURAT KETERANGAN BEBAS TBC
// ========================================
if ($aksi === 'hapus_surat_keterangan_bebas_tbc') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $cekData = bukaquery("SELECT no_surat FROM surat_bebas_tbc WHERE no_rawat = '$no_rawat'");

        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }

        $query  = "DELETE FROM surat_bebas_tbc WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Surat keterangan bebas TBC berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// SIMPAN SURAT KETERANGAN BEBAS TATO
// ========================================
if ($aksi === 'simpan_surat_keterangan_bebas_tato') {

    try {
        $no_surat       = isset($_POST['no_surat'])       ? validTeks4($_POST['no_surat'], 20)       : '';
        $no_rawat       = isset($_POST['no_rawat'])       ? validTeks4($_POST['no_rawat'], 17)       : '';
        $tanggalperiksa = isset($_POST['tanggalperiksa']) ? validTeks4($_POST['tanggalperiksa'], 10) : date('Y-m-d');
        $hasilperiksa   = isset($_POST['hasilperiksa'])   ? validTeks4($_POST['hasilperiksa'], 10)   : 'Bebas Tato';
        $keperluan      = isset($_POST['keperluan'])      ? validTeks4($_POST['keperluan'], 50)      : '';

        if (empty($no_surat) || empty($no_rawat)) {
            throw new Exception('No. Surat dan No. Rawat tidak boleh kosong');
        }

        $cekExist = bukaquery("SELECT no_surat FROM surat_bebas_tato WHERE no_rawat = '$no_rawat'");

        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            $query = "UPDATE surat_bebas_tato SET
                        no_surat       = '$no_surat',
                        tanggalperiksa = '$tanggalperiksa',
                        hasilperiksa   = '$hasilperiksa',
                        keperluan      = '$keperluan'
                      WHERE no_rawat = '$no_rawat'";
            $msg = 'Surat keterangan bebas tato berhasil diperbarui';
        } else {
            $query = "INSERT INTO surat_bebas_tato (
                        no_surat, no_rawat, tanggalperiksa, hasilperiksa, keperluan
                      ) VALUES (
                        '$no_surat', '$no_rawat', '$tanggalperiksa', '$hasilperiksa', '$keperluan'
                      )";
            $msg = 'Surat keterangan bebas tato berhasil disimpan';
        }

        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan surat keterangan bebas tato');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// HAPUS SURAT KETERANGAN BEBAS TATO
// ========================================
if ($aksi === 'hapus_surat_keterangan_bebas_tato') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $cekData = bukaquery("SELECT no_surat FROM surat_bebas_tato WHERE no_rawat = '$no_rawat'");

        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }

        $query  = "DELETE FROM surat_bebas_tato WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Surat keterangan bebas tato berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// SIMPAN SURAT CUTI HAMIL
// ========================================
if ($aksi === 'simpan_surat_cuti_hamil') {

    try {
        $no_rawat         = isset($_POST['no_rawat'])         ? validTeks4($_POST['no_rawat'], 17)         : '';
        $no_surat         = isset($_POST['no_surat'])         ? validTeks4($_POST['no_surat'], 20)         : '';
        $keterangan_hamil = isset($_POST['keterangan_hamil']) ? validTeks4($_POST['keterangan_hamil'], 25) : '';
        $terhitung_mulai  = isset($_POST['terhitung_mulai'])  ? validTeks4($_POST['terhitung_mulai'], 10)  : date('Y-m-d');
        $perkiraan_lahir  = isset($_POST['perkiraan_lahir'])  ? validTeks4($_POST['perkiraan_lahir'], 10)  : date('Y-m-d');

        if (empty($no_rawat) || empty($no_surat)) {
            throw new Exception('No. Rawat dan No. Surat tidak boleh kosong');
        }

        $cekExist = bukaquery("SELECT no_rawat FROM surat_cuti_hamil WHERE no_rawat = '$no_rawat'");

        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            $query = "UPDATE surat_cuti_hamil SET
                        no_surat         = '$no_surat',
                        keterangan_hamil = '$keterangan_hamil',
                        terhitung_mulai  = '$terhitung_mulai',
                        perkiraan_lahir  = '$perkiraan_lahir'
                      WHERE no_rawat = '$no_rawat'";
            $msg = 'Surat cuti hamil berhasil diperbarui';
        } else {
            $query = "INSERT INTO surat_cuti_hamil (
                        no_rawat, no_surat, keterangan_hamil, terhitung_mulai, perkiraan_lahir
                      ) VALUES (
                        '$no_rawat', '$no_surat', '$keterangan_hamil', '$terhitung_mulai', '$perkiraan_lahir'
                      )";
            $msg = 'Surat cuti hamil berhasil disimpan';
        }

        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan surat cuti hamil');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// HAPUS SURAT CUTI HAMIL
// ========================================
if ($aksi === 'hapus_surat_cuti_hamil') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $cekData = bukaquery("SELECT no_rawat FROM surat_cuti_hamil WHERE no_rawat = '$no_rawat'");

        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }

        $query  = "DELETE FROM surat_cuti_hamil WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Surat cuti hamil berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// SIMPAN SURAT KETERANGAN LAYAK TERBANG
// ========================================
if ($aksi === 'simpan_surat_keterangan_layak_terbang') {

    try {
        $no_surat        = isset($_POST['no_surat'])        ? validTeks4($_POST['no_surat'], 17)        : '';
        $no_rawat        = isset($_POST['no_rawat'])        ? validTeks4($_POST['no_rawat'], 17)        : '';
        $tanggal_periksa = isset($_POST['tanggal_periksa']) ? validTeks4($_POST['tanggal_periksa'], 10) : date('Y-m-d');
        $kehamilan       = isset($_POST['kehamilan'])       ? validTeks4($_POST['kehamilan'], 4)        : '';

        if (empty($no_surat) || empty($no_rawat)) {
            throw new Exception('No. Surat dan No. Rawat tidak boleh kosong');
        }

        $cekExist = bukaquery("SELECT no_surat FROM surat_keterangan_layak_terbang WHERE no_rawat = '$no_rawat'");

        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            $query = "UPDATE surat_keterangan_layak_terbang SET
                        no_surat        = '$no_surat',
                        tanggal_periksa = '$tanggal_periksa',
                        kehamilan       = '$kehamilan'
                      WHERE no_rawat = '$no_rawat'";
            $msg = 'Surat keterangan layak terbang berhasil diperbarui';
        } else {
            $query = "INSERT INTO surat_keterangan_layak_terbang (
                        no_surat, no_rawat, tanggal_periksa, kehamilan
                      ) VALUES (
                        '$no_surat', '$no_rawat', '$tanggal_periksa', '$kehamilan'
                      )";
            $msg = 'Surat keterangan layak terbang berhasil disimpan';
        }

        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan surat keterangan layak terbang');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// HAPUS SURAT KETERANGAN LAYAK TERBANG
// ========================================
if ($aksi === 'hapus_surat_keterangan_layak_terbang') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $cekData = bukaquery("SELECT no_surat FROM surat_keterangan_layak_terbang WHERE no_rawat = '$no_rawat'");

        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }

        $query  = "DELETE FROM surat_keterangan_layak_terbang WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Surat keterangan layak terbang berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus data');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// SIMPAN SURAT KETERANGAN RAWAT INAP
// ========================================
if ($aksi === 'simpan_surat_keterangan_rawat_inap') {

    try {
        $no_surat     = isset($_POST['no_surat'])     ? validTeks4($_POST['no_surat'], 17)     : '';
        $no_rawat     = isset($_POST['no_rawat'])     ? validTeks4($_POST['no_rawat'], 17)     : '';
        $tanggalawal  = isset($_POST['tanggalawal'])  ? validTeks4($_POST['tanggalawal'], 10)  : date('Y-m-d');
        $tanggalakhir = isset($_POST['tanggalakhir']) ? validTeks4($_POST['tanggalakhir'], 10) : date('Y-m-d');

        if (empty($no_surat) || empty($no_rawat)) {
            throw new Exception('No. Surat dan No. Rawat tidak boleh kosong');
        }

        if ($tanggalakhir < $tanggalawal) {
            throw new Exception('Sampai Tanggal tidak boleh sebelum Dari Tanggal');
        }

        $cekExist = bukaquery("SELECT no_surat FROM surat_keterangan_rawat_inap WHERE no_rawat = '$no_rawat'");

        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            $query = "UPDATE surat_keterangan_rawat_inap SET
                        no_surat     = '$no_surat',
                        tanggalawal  = '$tanggalawal',
                        tanggalakhir = '$tanggalakhir'
                      WHERE no_rawat = '$no_rawat'";
            $msg = 'Surat keterangan rawat inap berhasil diperbarui';
        } else {
            $query = "INSERT INTO surat_keterangan_rawat_inap (
                        no_surat, no_rawat, tanggalawal, tanggalakhir
                      ) VALUES (
                        '$no_surat', '$no_rawat', '$tanggalawal', '$tanggalakhir'
                      )";
            $msg = 'Surat keterangan rawat inap berhasil disimpan';
        }

        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan surat keterangan rawat inap');
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit();
}

// ========================================
// HAPUS SURAT KETERANGAN RAWAT INAP
// ========================================
if ($aksi === 'hapus_surat_keterangan_rawat_inap') {

    try {
        $no_rawat = isset($_POST['no_rawat']) ? validTeks4($_POST['no_rawat'], 17) : '';

        if (empty($no_rawat)) {
            throw new Exception('No. Rawat tidak valid');
        }

        $cekData = bukaquery("SELECT no_surat FROM surat_keterangan_rawat_inap WHERE no_rawat = '$no_rawat'");

        if (!$cekData || mysqli_num_rows($cekData) == 0) {
            throw new Exception('Data tidak ditemukan');
        }

        $query  = "DELETE FROM surat_keterangan_rawat_inap WHERE no_rawat = '$no_rawat'";
        $result = bukaquery($query);

        if ($result) {
            insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Surat keterangan rawat inap berhasil dihapus']);
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