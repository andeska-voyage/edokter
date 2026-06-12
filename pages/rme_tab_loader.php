<?php
/**
 * RME Tab Loader (Global - Rajal & Ranap)
 * Memuat halaman RME (Rekam Medis Elektronik) untuk ditampilkan di dalam tab
 * pada halaman pemeriksaan.php (Rajal) dan pemeriksaaninap.php (Ranap)
 * 
 * Dipanggil via AJAX dari RME Tab Manager
 */

// Load session dan konfigurasi
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once(__DIR__ . '/../conf/command.php');
require_once(__DIR__ . '/../conf/conf.php');

// Cek session login
if (!isset($_SESSION['ses_dokter']) || empty($_SESSION['ses_dokter'])) {
    echo '<div class="alert alert-danger" style="margin:20px;"><strong>Akses ditolak!</strong> Silakan login terlebih dahulu.</div>';
    exit();
}

// Validasi parameter
if (!isset($_GET['act']) || empty($_GET['act'])) {
    echo '<div class="alert alert-warning" style="margin:20px;">Parameter tidak valid.</div>';
    exit();
}

$act = $_GET['act'];

// Mapping act ke file PHP (Global: Rajal + Ranap)
$pageMapping = [
    // ========== RAJAL (Rawat Jalan) ==========
    // Gawat Darurat
    'Triaseigd'             => 'triaseigd.php',
    'Awalmedisigd'          => 'awalmedisigd.php',
    
    // Penilaian Awal Medis - Rajal
    'Awalmedisumum'                      => 'awalmedisumum.php',
    'Awalmedisanak'                      => 'awalmedisanak.php',
    'Awalmedistht'                       => 'awalmedistht.php',
    'Awalmedispsikiatri'                 => 'awalmedispsikiatri.php',
    'Awalmedispenyakitdalam'             => 'awalmedispenyakitdalam.php',
    'Awalmedismata'                      => 'awalmedismata.php',
    'Awalmedisneurologi'                 => 'awalmedisneurologi.php',
    'Awalmedisorthopedi'                 => 'awalmedisorthopedi.php',
    'Awalmedisbedah'                     => 'awalmedisbedah.php',
    'Awalmedisgeriatri'                  => 'awalmedisgeriatri.php',
    'Awalmedisbedahmulut'                => 'awalmedisbedahmulut.php',
    'Awalmediskulitkelamin'              => 'awalmediskulitkelamin.php',
    'Awalmedisparu'                      => 'awalmedisparu.php',
    'Awalmedisfisikrehabilitasi'         => 'awalmedisfisikrehabilitasi.php',
    'Awalmedishemodialisa'               => 'awalmedishemodialisa.php',
    'Awalmedisjantung'                   => 'awalmedisjantung.php',
    
    // Perawatan Intensif
    'Checklistkriteriamasukhcu' => 'checklistkriteriamasukhcu.php',
    'Checklistkriteriamasukicu' => 'checklistkriteriamasukicu.php',
    'Kriteriamasuknicu'     => 'kriteriamasuknicu.php',
    'Kriteriamasukpicu'     => 'kriteriamasukpicu.php',
    'Checklistkriteriakeluarhcu' => 'checklistkriteriakeluarhcu.php',
    'Checklistkriteriakeluaricu' => 'checklistkriteriakeluaricu.php',
    'Kriteriakeluarnicu' => 'kriteriakeluarnicu.php',
    'Kriteriakeluarpicu' => 'kriteriakeluarpicu.php',

    // Rehab Medik
    'Ujifungsikfr'          => 'ujifungsikfr.php',
    'Layanankedokteranfisik'=> 'layanankedokteranfisik.php',
    
    // Resume Rajal
    'ResumeMedis'           => 'resumemedis.php',
    
    // ========== RANAP (Rawat Inap) ==========
    // Penilaian Awal Medis - Ranap
    'Awalmedisranap'        => 'awalmedisranap.php',
    'Awalmedisneonatus'     => 'awalmedisneonatus.php',
    'Awalmedisjantunginap'  => 'awalmedisjantunginap.php',
    'Penilaianbayibarulahir'=> 'penilaianbayibarulahir.php',
    
    // Konsultasi
    'Konsultasimedik'       => 'konsulmedik.php',
    
    // Clinical Pathway
    'ClinicalPathway'       => 'clinicalpathway.php',
    
    // Obat Pulang
    'Obatpulang'            => 'obatpulang.php',
    
    // Resume Ranap
    'ResumeMedisInap'       => 'resumemedisinap.php',
    
    // ========== SHARED (Rajal & Ranap) ==========
    // Penilaian Awal Medis - Shared
    'Awalmediskebidanan'    => 'awalmediskebidanan.php',
    'Awalmediskebidananralan' => 'awalmediskebidananralan.php',
    
    // USG
    'Pemeriksaanusgkandungan' => 'pemeriksaanusgkandungan.php',
    'Pemeriksaanusgurologi'   => 'pemeriksaanusgurologi.php',
    'Pemeriksaanusgneonatus'  => 'pemeriksaanusgneonatus.php',
    'Pemeriksaanusggynecologi'=> 'pemeriksaanusggynecologi.php',
    'Pemeriksaanusgurologi'   => 'pemeriksaanusgurologi.php',
    'Pemeriksaanusgpayudara'  => 'pemeriksaanusgpayudara.php',
    
    // Endoskopi
    'Pemeriksaanendoskopifaringlaring' => 'pemeriksaanendoskopifaringlaring.php',
    'Pemeriksaanendoskopihidung'        => 'pemeriksaanendoskopihidung.php',
    'Pemeriksaanendoskopitelinga'       => 'pemeriksaanendoskopitelinga.php',

    // hasil pemeriksaan
    'Pemeriksaanekg'             => 'pemeriksaanekg.php',
    'Pemeriksaanecho'            => 'pemeriksaanecho.php',
    'Pemeriksaanechopediatrik'   => 'pemeriksaanechopediatrik.php',
    'Pemeriksaanslitlamp'        => 'pemeriksaanslitlamp.php',
    'Pemeriksaanoct'             => 'pemeriksaanoct.php',
    'Pemeriksaantreadmill'       => 'pemeriksaantreadmill.php',
    
    // Operasi
    'Penilaianpreinduksi'   => 'penilaianpreinduksi.php',
    'Penilaianpreoperasi'   => 'penilaianpreoperasi.php',
    'Penilaianpreanestesi'  => 'penilaianpreanestesi.php',
];

// Cek apakah act ada di mapping
if (!isset($pageMapping[$act])) {
    echo '<div class="alert alert-warning" style="margin:20px;">';
    echo '<strong>Halaman tidak ditemukan!</strong><br>';
    echo 'Modul <code>' . htmlspecialchars($act) . '</code> belum terdaftar di tab loader.';
    echo '</div>';
    exit();
}

$pageFile = __DIR__ . '/' . $pageMapping[$act];

// Cek file exists
if (!file_exists($pageFile)) {
    echo '<div class="alert alert-warning" style="margin:20px;">';
    echo '<strong>File tidak ditemukan!</strong><br>';
    echo 'File <code>' . htmlspecialchars($pageMapping[$act]) . '</code> tidak ada di server.';
    echo '</div>';
    exit();
}

// Load halaman
try {
    include($pageFile);
} catch (Exception $e) {
    echo '<div class="alert alert-danger" style="margin:20px;">';
    echo '<strong>Error!</strong> ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}
