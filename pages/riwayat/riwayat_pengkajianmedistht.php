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

// Query data pengkajian medis THT (ralan)
$query_medis = "
    SELECT 
        p.*,
        d.nm_dokter
    FROM penilaian_medis_ralan_tht p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data pengkajian medis THT tidak ditemukan</div>';
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
                <span class="info-label">BB:</span>
                <span class="info-value"><?= htmlspecialchars($data['bb']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TB:</span>
                <span class="info-value"><?= htmlspecialchars($data['tb']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nyeri:</span>
                <span class="info-value"><?= htmlspecialchars($data['nyeri']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Status Nutrisi:</span>
                <span class="info-value"><?= htmlspecialchars($data['status_nutrisi']) ?: '-' ?></span>
            </div>
        </div>
        <?php if (!empty($data['kondisi'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Kondisi Umum:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['kondisi'])) ?></span>
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
                        <img src="<?= APP_BASE_URL ?>/images/tht.png" 
                            alt="Gambar Lokalis THT" 
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
                <span class="info-label">Laboratorium:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['lab'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Radiologi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rad'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Tes Pendengaran:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['tes_pendengaran'])) ?: '-' ?></span>
            </div>
        </div>
        <?php if (!empty($data['penunjang'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-label">Penunjang Lainnya:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['penunjang'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- V. DIAGNOSIS/ASESMEN -->
        <div class="section-title">
            <i class="fa fa-file-medical"></i> V. Diagnosis/Asesmen
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">Diagnosis:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['diagnosis'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Diagnosis Banding:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['diagnosisbanding'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Permasalahan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['permasalahan'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VI. TATALAKSANA -->
        <div class="section-title">
            <i class="fa fa-procedures"></i> VI. Tatalaksana
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">Terapi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['terapi'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Tindakan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['tindakan'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Tatalaksana:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['tatalaksana'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VII. EDUKASI -->
        <?php if (!empty($data['edukasi'])): ?>
        <div class="section-title">
            <i class="fa fa-chalkboard-teacher"></i> VII. Edukasi
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['edukasi'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>