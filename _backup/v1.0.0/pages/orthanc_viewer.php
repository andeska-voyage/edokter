<?php
/**
 * orthanc_viewer.php
 * Proxy: cari Study UID di Orthanc by PatientID (no_rkm_medis) + StudyDate (tgl_periksa)
 * lalu redirect ke Stone Web Viewer, atau return JSON untuk AJAX
 *
 * Usage (AJAX/JSON): orthanc_viewer.php?norm=191870&tgl=2026-04-06&json=1
 *
 * viewer_url yang dikembalikan mengarah ke dicom_proxy.php yang meng-inject
 * Basic Auth Orthanc otomatis dari conf.php — browser tidak pernah minta login.
 */

session_start();
require_once('../conf/conf.php');

if (!isset($_SESSION["ses_dokter"])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Session expired']));
}

header('Content-Type: application/json');

$norm = isset($_GET['norm']) ? validTeks4($_GET['norm'], 20) : '';
$tgl  = isset($_GET['tgl'])  ? $_GET['tgl'] : '';  // format: YYYY-MM-DD

if (empty($norm) || empty($tgl)) {
    echo json_encode(['success' => false, 'error' => 'Parameter norm dan tgl wajib diisi']);
    exit;
}

// Konversi tgl dari YYYY-MM-DD → YYYYMMDD (format DICOM)
$tgl_dicom = str_replace('-', '', $tgl); // 2026-04-06 → 20260406

// Validasi format tanggal
if (!preg_match('/^\d{8}$/', $tgl_dicom)) {
    echo json_encode(['success' => false, 'error' => 'Format tanggal tidak valid']);
    exit;
}

// ============================================
// Query Orthanc REST API: /tools/find
// ============================================
$orthanc_base = ORTHANC_URL . ':' . ORTHANC_PORT;
$find_url     = $orthanc_base . '/tools/find';

$payload = json_encode([
    'Level'  => 'Study',
    'Query'  => [
        'PatientID' => $norm,
        'StudyDate' => $tgl_dicom
    ],
    'Limit'  => 10
]);

$ch = curl_init($find_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_USERPWD        => ORTHANC_USER . ':' . ORTHANC_PASS,
    CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    echo json_encode(['success' => false, 'error' => 'Tidak dapat terhubung ke Orthanc: ' . $curl_err]);
    exit;
}

if ($http_code !== 200) {
    echo json_encode(['success' => false, 'error' => 'Orthanc HTTP error: ' . $http_code]);
    exit;
}

$orthanc_ids = json_decode($response, true);

if (empty($orthanc_ids) || !is_array($orthanc_ids)) {
    echo json_encode(['success' => false, 'error' => 'Data DICOM tidak ditemukan untuk pasien ini pada tanggal tersebut']);
    exit;
}

// ============================================
// Ambil Study Instance UID dari tiap Orthanc Study ID
// ============================================
$studies = [];

foreach ($orthanc_ids as $orthanc_id) {
    $study_url = $orthanc_base . '/studies/' . $orthanc_id;
    
    $ch2 = curl_init($study_url);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_USERPWD        => ORTHANC_USER . ':' . ORTHANC_PASS,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
    ]);
    $study_json = curl_exec($ch2);
    curl_close($ch2);
    
    $study_data = json_decode($study_json, true);
    
    if (!$study_data) continue;
    
    $study_uid  = $study_data['MainDicomTags']['StudyInstanceUID']  ?? '';
    $study_desc = $study_data['MainDicomTags']['StudyDescription']  ?? 'Study DICOM';
    $study_date = $study_data['MainDicomTags']['StudyDate']         ?? '';
    $study_time = $study_data['MainDicomTags']['StudyTime']         ?? '';
    $accession  = $study_data['MainDicomTags']['AccessionNumber']   ?? '';
    $series_count = count($study_data['Series'] ?? []);
    
    if (empty($study_uid)) continue;
    
    // Build dua URL:
    // 1. gate_url  → stone_gate.php untuk pre-auth (popup kecil)
    // 2. viewer_url → URL Orthanc langsung untuk dipakai di iframe setelah auth
    $proxy_base  = rtrim(APP_BASE_URL, '/') . '/pages/stone_gate.php';
    $gate_url    = $proxy_base . '?study=' . urlencode($study_uid);
    $viewer_url  = $orthanc_base . '/stone-webviewer/index.html?study=' . urlencode($study_uid);
    
    $studies[] = [
        'orthanc_id'   => $orthanc_id,
        'study_uid'    => $study_uid,
        'study_desc'   => $study_desc,
        'study_date'   => $study_date,
        'study_time'   => substr($study_time, 0, 6),
        'accession'    => $accession,
        'series_count' => $series_count,
        'gate_url'     => $gate_url,    // untuk popup pre-auth
        'viewer_url'   => $viewer_url   // URL Orthanc langsung untuk iframe
    ];
}

if (empty($studies)) {
    echo json_encode(['success' => false, 'error' => 'Study Instance UID tidak ditemukan di Orthanc']);
    exit;
}

echo json_encode([
    'success' => true,
    'count'   => count($studies),
    'studies' => $studies
]);