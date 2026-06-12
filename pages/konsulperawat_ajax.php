<?php
/**
 * konsulperawat_ajax.php
 * Handler AJAX untuk fitur Konsul Perawat (SBAR) → Dokter
 *
 * Tabel:
 *   - konsultasi_perawat       : permintaan SBAR dari perawat (kd_dokter_dikonsuli)
 *   - jawaban_konsultasi_perawat : jawaban dokter (respon, instruksi, rencana)
 *
 * Aksi yang ditangani:
 *   - get_konsul_perawat_masuk        : list SBAR untuk dokter login
 *   - get_detail_konsul_perawat       : detail 1 permintaan + jawaban (jika ada)
 *   - simpan_jawaban_konsul_perawat   : insert/update jawaban
 *   - hapus_jawaban_konsul_perawat    : delete jawaban
 */

error_reporting(0);
ini_set('display_errors', 0);
session_start();

require_once('../conf/conf.php');
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');

if (!isset($_SESSION["ses_dokter"])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired atau belum login']);
    exit();
}

$aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';
$kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

if (empty($kd_dokter_login)) {
    echo json_encode(['status' => 'error', 'message' => 'Kode dokter login tidak valid']);
    exit();
}

// ================================================================
// GET LIST KONSUL SBAR YANG DITUJUKAN KE DOKTER LOGIN
// ================================================================
if ($aksi === 'get_konsul_perawat_masuk') {
    try {
        $filter = isset($_POST['filter']) ? $_POST['filter'] : 'belum';

        $whereFilter = '';
        if ($filter === 'belum')      $whereFilter = ' AND j.no_permintaan IS NULL';
        elseif ($filter === 'sudah')  $whereFilter = ' AND j.no_permintaan IS NOT NULL';

        $query = "SELECT
                    k.no_permintaan,
                    k.no_rawat,
                    k.tanggal,
                    k.nip,
                    pg.nama AS nm_perawat,
                    k.situation,
                    k.background,
                    k.assessment,
                    k.recomendation,
                    rp.no_rkm_medis,
                    p.nm_pasien,
                    CASE WHEN j.no_permintaan IS NOT NULL THEN 1 ELSE 0 END AS sudah_dijawab
                  FROM konsultasi_perawat k
                  LEFT JOIN pegawai pg                ON k.nip = pg.nik
                  LEFT JOIN jawaban_konsultasi_perawat j ON k.no_permintaan = j.no_permintaan
                  LEFT JOIN reg_periksa rp            ON k.no_rawat = rp.no_rawat
                  LEFT JOIN pasien p                  ON rp.no_rkm_medis = p.no_rkm_medis
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

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ================================================================
// GET DETAIL KONSUL SBAR + JAWABAN (jika ada)
// ================================================================
if ($aksi === 'get_detail_konsul_perawat') {
    try {
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20) : '';
        if (empty($no_permintaan)) {
            throw new Exception('No. Permintaan tidak valid');
        }

        // Pastikan permintaan ditujukan ke dokter login (proteksi akses)
        $query = "SELECT
                    k.no_permintaan,
                    k.no_rawat,
                    k.tanggal,
                    k.nip,
                    pg.nama          AS nm_perawat,
                    pg.jbtn          AS jbtn_perawat,
                    k.kd_dokter_dikonsuli,
                    d.nm_dokter      AS nm_dokter_dikonsuli,
                    k.situation,
                    k.background,
                    k.assessment,
                    k.recomendation,
                    rp.no_rkm_medis,
                    p.nm_pasien,
                    p.tgl_lahir,
                    p.jk
                  FROM konsultasi_perawat k
                  LEFT JOIN pegawai pg     ON k.nip = pg.nik
                  LEFT JOIN dokter d       ON k.kd_dokter_dikonsuli = d.kd_dokter
                  LEFT JOIN reg_periksa rp ON k.no_rawat = rp.no_rawat
                  LEFT JOIN pasien p       ON rp.no_rkm_medis = p.no_rkm_medis
                  WHERE k.no_permintaan = '$no_permintaan'
                    AND k.kd_dokter_dikonsuli = '$kd_dokter_login'
                  LIMIT 1";

        $result = bukaquery($query);
        if (!$result || mysqli_num_rows($result) == 0) {
            throw new Exception('Data tidak ditemukan atau Anda tidak memiliki akses');
        }

        $data = mysqli_fetch_assoc($result);
        if (!empty($data['tanggal'])) {
            $data['tanggal'] = date('d-m-Y H:i', strtotime($data['tanggal']));
        }

        // Ambil jawaban jika ada
        $qJawab = bukaquery("SELECT * FROM jawaban_konsultasi_perawat WHERE no_permintaan = '$no_permintaan' LIMIT 1");
        if ($qJawab && mysqli_num_rows($qJawab) > 0) {
            $jawaban = mysqli_fetch_assoc($qJawab);
            $data['jawaban'] = $jawaban;
            if (!empty($jawaban['tanggal'])) {
                $data['tanggal_jawaban'] = date('d-m-Y H:i', strtotime($jawaban['tanggal']));
            }
        } else {
            $data['jawaban'] = null;
        }

        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ================================================================
// SIMPAN JAWABAN (insert atau update)
// ================================================================
if ($aksi === 'simpan_jawaban_konsul_perawat') {
    try {
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20)  : '';
        $respon        = isset($_POST['respon'])        ? validTeks4($_POST['respon'], 80)        : '';
        $instruksi     = isset($_POST['instruksi'])     ? validTeks4($_POST['instruksi'], 500)    : '';
        $rencana       = isset($_POST['rencana'])       ? validTeks4($_POST['rencana'], 500)      : '';
        $tanggal       = date('Y-m-d H:i:s');

        if (empty($no_permintaan)) {
            throw new Exception('No. Permintaan tidak valid');
        }
        if (empty($instruksi) && empty($rencana)) {
            throw new Exception('Minimal Instruksi atau Rencana harus diisi');
        }

        // Cek akses: konsultasi harus ditujukan ke dokter login
        $cek = bukaquery("SELECT no_permintaan FROM konsultasi_perawat
                          WHERE no_permintaan = '$no_permintaan'
                            AND kd_dokter_dikonsuli = '$kd_dokter_login'");
        if (!$cek || mysqli_num_rows($cek) == 0) {
            throw new Exception('Anda tidak memiliki akses untuk menjawab konsultasi ini');
        }

        // Cek apakah jawaban sudah ada → UPDATE atau INSERT
        $cekJawab = bukaquery("SELECT no_permintaan FROM jawaban_konsultasi_perawat
                               WHERE no_permintaan = '$no_permintaan'");

        if ($cekJawab && mysqli_num_rows($cekJawab) > 0) {
            $query = "UPDATE jawaban_konsultasi_perawat SET
                        tanggal   = '$tanggal',
                        respon    = '$respon',
                        instruksi = '$instruksi',
                        rencana   = '$rencana'
                      WHERE no_permintaan = '$no_permintaan'";
            $msg = 'Jawaban konsultasi perawat berhasil diupdate';
        } else {
            $query = "INSERT INTO jawaban_konsultasi_perawat
                        (no_permintaan, tanggal, respon, instruksi, rencana)
                      VALUES
                        ('$no_permintaan', '$tanggal', '$respon', '$instruksi', '$rencana')";
            $msg = 'Jawaban konsultasi perawat berhasil disimpan';
        }

        $result = bukaquery($query);
        if ($result) {
            if (function_exists('insertTracker')) insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } else {
            throw new Exception('Gagal menyimpan jawaban: ' . mysqli_error($GLOBALS['db_conn']));
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// ================================================================
// HAPUS JAWABAN
// ================================================================
if ($aksi === 'hapus_jawaban_konsul_perawat') {
    try {
        $no_permintaan = isset($_POST['no_permintaan']) ? validTeks4($_POST['no_permintaan'], 20) : '';
        if (empty($no_permintaan)) {
            throw new Exception('No. Permintaan tidak valid');
        }

        // Cek akses: hanya dokter yang dituju yang bisa hapus jawaban
        $cek = bukaquery("SELECT no_permintaan FROM konsultasi_perawat
                          WHERE no_permintaan = '$no_permintaan'
                            AND kd_dokter_dikonsuli = '$kd_dokter_login'");
        if (!$cek || mysqli_num_rows($cek) == 0) {
            throw new Exception('Anda tidak memiliki akses untuk menghapus jawaban ini');
        }

        $query = "DELETE FROM jawaban_konsultasi_perawat WHERE no_permintaan = '$no_permintaan'";
        $result = bukaquery($query);

        if ($result) {
            if (function_exists('insertTracker')) insertTracker($query);
            echo json_encode(['status' => 'success', 'message' => 'Jawaban konsultasi perawat berhasil dihapus']);
        } else {
            throw new Exception('Gagal menghapus jawaban');
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

// Default: aksi tidak dikenali
echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenali: ' . htmlspecialchars($aksi)]);
exit();
