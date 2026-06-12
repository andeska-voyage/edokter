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

// Function untuk badge status pemeriksaan
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
// Query data penilaian medis IGD
$query_medis = "
    SELECT 
        p.*,
        d.nm_dokter
    FROM penilaian_medis_igd p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian medis IGD tidak ditemukan</div>';
    exit;
}

// Loop data
while ($data = mysqli_fetch_assoc($result_medis)):
    // Format tanggal Indonesia
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
        <span class="info-label">Leher:</span>
        <span class="info-value"><?= getBadgeStatus($data['leher']) ?></span>
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
</div>
<?php if (!empty($data['ket_fisik'])): ?>
<div class="info-grid mt-2">
    <div class="info-item" style="grid-column: 1 / -1;">
        <span class="info-label">Keterangan Fisik:</span>
        <span class="info-value"><?= nl2br(htmlspecialchars($data['ket_fisik'])) ?></span>
    </div>
</div>
<?php endif; ?>

        <!-- III. STATUS LOKALIS -->
        <div class="section-title">
            <i class="fa fa-map-marker-alt"></i> III. Status Lokalis
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="info-item-vertical">
                    <span class="info-label">Keterangan Lokalis:</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($data['ket_lokalis'])) ?: '-' ?></span>
                </div>
            </div>
            <div class="col-md-8">
                <div class="info-item-vertical">
                    <span class="info-label">Gambar:</span>
                    <span class="info-value">
                        <img src="<?= APP_BASE_URL ?>/images/semua.png" 
                            alt="Gambar Lokalis" 
                            class="img-fluid" 
                            style="width: 100%; max-width: 600px; height: auto; border: 1px solid #e2e8f0; border-radius: 4px;">
                    </span>
                </div>
            </div>
        </div>

        <!-- IV. PEMERIKSAAN PENUNJANG -->
        <div class="section-title">
            <i class="fa fa-vial"></i> IV. Pemeriksaan Penunjang
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">EKG:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['ekg'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Radiologi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rad'])) ?: '-' ?></span>
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
    </div>
</div>

<?php endwhile; ?>