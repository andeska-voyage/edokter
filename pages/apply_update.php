<?php
/**
 * apply_update.php — Handler penerima file update E-Dokter
 * Dipanggil via POST dari tentangaplikasi.js
 *
 * Perubahan sistem backup:
 * - Backup disimpan ke folder _backup/v{versi_lama}/ (bukan .bak_timestamp)
 * - Satu file hanya punya 1 backup per versi → folder bersih
 */

if (session_status() == PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'pesan' => 'Method tidak diizinkan.']);
    exit;
}

header('Content-Type: application/json');

// ── Validasi token ────────────────────────────────────────────
$token = $_POST['token'] ?? '';
if ($token !== 'YangjualsialselamanyA') {
    echo json_encode(['status' => 'error', 'pesan' => 'Token tidak valid.']);
    exit;
}

// ── Ambil parameter ───────────────────────────────────────────
$target         = $_POST['target']         ?? '';
$konten         = $_POST['konten']         ?? '';
$versi_baru     = $_POST['versi_baru']     ?? '';
$versi_lama     = $_POST['versi_lama']     ?? '';   // ← baru: dikirim dari JS
$update_selesai = $_POST['update_selesai'] ?? '0';

if (empty($target)) {
    echo json_encode(['status' => 'error', 'pesan' => 'Parameter target kosong.']);
    exit;
}
if (!isset($_POST['konten'])) {
    echo json_encode(['status' => 'error', 'pesan' => 'Parameter konten tidak ada.']);
    exit;
}

// ── Keamanan: cegah path traversal, wajib diawali edokter/ ───
$target = str_replace(['..', '\\'], ['', '/'], $target);
$target = ltrim($target, '/');
if (strpos($target, 'edokter/') !== 0) {
    echo json_encode(['status' => 'error', 'pesan' => 'Target path tidak diizinkan.']);
    exit;
}

// ── Decode konten base64 ──────────────────────────────────────
$isi = base64_decode($konten, true);
if ($isi === false) {
    echo json_encode(['status' => 'error', 'pesan' => 'Konten tidak valid (bukan base64).']);
    exit;
}

// ── Path fisik ────────────────────────────────────────────────
// pages/apply_update.php → 3x dirname → htdocs
$htdocs    = dirname(dirname(dirname(__FILE__)));
$pathFisik = $htdocs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);

// ── Buat folder tujuan jika belum ada ────────────────────────
$dir = dirname($pathFisik);
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
        echo json_encode(['status' => 'error', 'pesan' => 'Gagal membuat folder: ' . $dir]);
        exit;
    }
}

// ── Backup ke _backup/v{versi_lama}/ (sekali per versi) ──────
if (file_exists($pathFisik) && !empty($versi_lama)) {
    // Folder backup: htdocs/edokter/_backup/v1.0.0/pages/riwayat/
    $relPath    = substr($target, strlen('edokter/')); // strip "edokter/"
    $backupDir  = $htdocs . DIRECTORY_SEPARATOR . 'edokter' . DIRECTORY_SEPARATOR
                . '_backup' . DIRECTORY_SEPARATOR . 'v' . $versi_lama
                . DIRECTORY_SEPARATOR . dirname($relPath);
    $backupFile = $backupDir . DIRECTORY_SEPARATOR . basename($pathFisik);

    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    // Backup hanya jika belum ada (hindari overwrite backup versi sama)
    if (!file_exists($backupFile)) {
        copy($pathFisik, $backupFile);
    }
}

// ── Tulis file baru ───────────────────────────────────────────
$hasil = file_put_contents($pathFisik, $isi);
if ($hasil === false) {
    echo json_encode(['status' => 'error', 'pesan' => 'Gagal menulis file. Cek permission folder htdocs.']);
    exit;
}

$responseData = [
    'status' => 'ok',
    'pesan'  => 'Berhasil: ' . $target . ' (' . $hasil . ' bytes)',
];

// ── Update versi.php di request terakhir ─────────────────────
if ($update_selesai === '1' && !empty($versi_baru) && preg_match('/^\d+\.\d+\.\d+$/', $versi_baru)) {
    $versiPhpPath = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'versi.php';
    $versiKonten  = '<?php' . "\n"
        . '/**' . "\n"
        . ' * versi.php — diupdate otomatis oleh apply_update.php' . "\n"
        . ' */' . "\n"
        . 'header(\'Content-Type: application/json\');' . "\n"
        . 'header(\'Access-Control-Allow-Origin: *\');' . "\n"
        . 'header(\'Cache-Control: no-store, no-cache, must-revalidate\');' . "\n"
        . 'echo json_encode([\'status\' => \'ok\', \'versi\' => \'' . $versi_baru . '\']);' . "\n";

    if (file_put_contents($versiPhpPath, $versiKonten) !== false) {
        $responseData['versi_diperbarui'] = $versi_baru;
    } else {
        $responseData['warning'] = 'File berhasil diupdate tapi versi.php gagal diperbarui.';
    }
}

echo json_encode($responseData);
