<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

// Get parameters
$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

// Function untuk badge status pemeriksaan Normal/Abnormal/Tidak Diperiksa
function getBadgeStatus($value) {
    if (empty($value)) {
        return '-';
    }
    $value_lower = strtolower(trim($value));
    if ($value_lower == 'normal') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">Normal</span>';
    } elseif ($value_lower == 'abnormal') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #ef4444; color: #fff;">Abnormal</span>';
    } elseif ($value_lower == 'tidak diperiksa') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #fbbf24; color: #1e293b;">Tidak Diperiksa</span>';
    } else {
        return htmlspecialchars($value);
    }
}

// Function untuk badge Ada/Tidak
function getBadgeAdaTidak($value) {
    if (empty($value)) {
        return '-';
    }
    $value_lower = strtolower(trim($value));
    if ($value_lower == 'ada') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #3b82f6; color: #fff;">Ada</span>';
    } elseif ($value_lower == 'tidak') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #94a3b8; color: #fff;">Tidak</span>';
    } else {
        return htmlspecialchars($value);
    }
}

// Query data pengkajian medis Kandungan (ralan)
$query_medis = "
    SELECT 
        p.*,
        d.nm_dokter
    FROM penilaian_medis_ralan_kandungan p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data pengkajian medis Kandungan tidak ditemukan</div>';
    exit;
}

// Loop data
while ($data = mysqli_fetch_assoc($result_medis)):
    $bulan = array(
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d', $tanggal_obj) . ' ' .
                     $bulan[date('n', $tanggal_obj)] . ' ' .
                     date('Y, H:i', $tanggal_obj);
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<div class="card mb-3 shadow-sm">    
    <div class="card-body">

        <!-- I. RIWAYAT KESEHATAN -->
        <div class="section-title">
            <i class="fa fa-notes-medical"></i> I. Riwayat Kesehatan
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <span class="info-label">Cara Anamnesis:</span>
                <span class="info-value"><?= htmlspecialchars($data['anamnesis']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Hubungan dengan Pasien:</span>
                <span class="info-value"><?= htmlspecialchars($data['hubungan']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Keluhan Utama:</span>
                <span class="info-value"><?= htmlspecialchars($data['keluhan_utama']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penyakit Sekarang:</span>
                <span class="info-value"><?= htmlspecialchars($data['rps']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penyakit Dahulu:</span>
                <span class="info-value"><?= htmlspecialchars($data['rpd']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penyakit Keluarga:</span>
                <span class="info-value"><?= htmlspecialchars($data['rpk']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penggunaan Obat:</span>
                <span class="info-value"><?= htmlspecialchars($data['rpo']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Alergi:</span>
                <span class="info-value"><?= htmlspecialchars($data['alergi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. PEMERIKSAAN FISIK -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> II. Pemeriksaan Fisik
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Keadaan:</span>
                <span class="info-value"><?= htmlspecialchars($data['keadaan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">GCS:</span>
                <span class="info-value"><?= htmlspecialchars($data['gcs']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kesadaran:</span>
                <span class="info-value"><?= htmlspecialchars($data['kesadaran']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TD:</span>
                <span class="info-value"><?= htmlspecialchars($data['td']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nadi:</span>
                <span class="info-value"><?= htmlspecialchars($data['nadi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">RR:</span>
                <span class="info-value"><?= htmlspecialchars($data['rr']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Suhu:</span>
                <span class="info-value"><?= htmlspecialchars($data['suhu']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">SpO2:</span>
                <span class="info-value"><?= htmlspecialchars($data['spo']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">BB:</span>
                <span class="info-value"><?= htmlspecialchars($data['bb']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TB:</span>
                <span class="info-value"><?= htmlspecialchars($data['tb']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Pemeriksaan Organ -->
        <div class="info-grid mt-2">
            <div class="info-item">
                <span class="info-label">Kepala:</span>
                <span class="info-value"><?= getBadgeStatus($data['kepala']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Mata:</span>
                <span class="info-value"><?= getBadgeStatus($data['mata']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Gigi:</span>
                <span class="info-value"><?= getBadgeStatus($data['gigi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">THT:</span>
                <span class="info-value"><?= getBadgeStatus($data['tht']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Thoraks:</span>
                <span class="info-value"><?= getBadgeStatus($data['thoraks']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Abdomen:</span>
                <span class="info-value"><?= getBadgeStatus($data['abdomen']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Genital:</span>
                <span class="info-value"><?= getBadgeStatus($data['genital']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ekstremitas:</span>
                <span class="info-value"><?= getBadgeStatus($data['ekstremitas']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kulit:</span>
                <span class="info-value"><?= getBadgeStatus($data['kulit']) ?></span>
            </div>
        </div>
        <?php if (!empty($data['ket_fisik'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Keterangan Fisik:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['ket_fisik'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- III. PEMERIKSAAN OBSTETRI / GINEKOLOGI -->
        <div class="section-title">
            <i class="fa fa-baby"></i> III. Pemeriksaan Obstetri / Ginekologi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">TFU:</span>
                <span class="info-value"><?= htmlspecialchars($data['tfu']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TBJ:</span>
                <span class="info-value"><?= htmlspecialchars($data['tbj']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">His:</span>
                <span class="info-value"><?= htmlspecialchars($data['his']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kontraksi:</span>
                <span class="info-value"><?= getBadgeAdaTidak($data['kontraksi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">DJJ:</span>
                <span class="info-value"><?= htmlspecialchars($data['djj']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-2">
            <div class="info-item-vertical">
                <span class="info-label">Inspeksi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['inspeksi'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Inspekulo:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['inspekulo'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">VT:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['vt'])) ?: '-' ?></span>
            </div>
        </div>
        <?php if (!empty($data['rt'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-label">RT:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rt'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- IV. PEMERIKSAAN PENUNJANG -->
        <div class="section-title">
            <i class="fa fa-vial"></i> IV. Pemeriksaan Penunjang
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">Ultrasonografi (USG):</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['ultra'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Kardiotokografi (CTG):</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['kardio'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Laboratorium:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['lab'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- V. DIAGNOSIS/ASESMEN -->
        <div class="section-title">
            <i class="fa fa-file-medical"></i> V. Diagnosis/Asesmen
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['diagnosis'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VI. TATALAKSANA -->
        <div class="section-title">
            <i class="fa fa-procedures"></i> VI. Tatalaksana
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['tata'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VII. KONSULTASI -->
        <?php if (!empty($data['konsul'])): ?>
        <div class="section-title">
            <i class="fa fa-user-md"></i> VII. Konsultasi
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['konsul'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>