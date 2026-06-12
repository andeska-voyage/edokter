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

// Function untuk badge status checklist
function getBadgeChecklist($value) {
    if ($value == 'Ya') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">✓ Ya</span>';
    } elseif ($value == 'Tidak') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #ef4444; color: #fff;">✗ Tidak</span>';
    } else {
        return '-';
    }
}

// Function untuk badge ada/tidak ada
function getBadgeAdaTidak($value) {
    if ($value == 'Ada') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">✓ Ada</span>';
    } elseif ($value == 'Tidak Ada') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #ef4444; color: #fff;">✗ Tidak Ada</span>';
    } elseif ($value == 'Tidak Diperlukan') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #6b7280; color: #fff;">N/A</span>';
    } else {
        return '-';
    }
}

// Function untuk badge kesiapan alat
function getBadgeKesiapan($value) {
    if ($value == 'Lengkap') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">✓ Lengkap</span>';
    } elseif ($value == 'Pulsa Oximetri') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #3b82f6; color: #fff;">Pulsa Oximetri</span>';
    } elseif ($value == 'Tidak Lengkap') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #ef4444; color: #fff;">✗ Tidak Lengkap</span>';
    } else {
        return htmlspecialchars($value);
    }
}

// Query untuk ambil data signin sebelum anestesi
$query = "
    SELECT 
        ssa.*,
        d1.nm_dokter AS nm_dokter_bedah,
        d2.nm_dokter AS nm_dokter_anestesi,
        p.nama AS nm_perawat_ok
    FROM signin_sebelum_anestesi ssa
    LEFT JOIN dokter d1 ON ssa.kd_dokter_bedah = d1.kd_dokter
    LEFT JOIN dokter d2 ON ssa.kd_dokter_anestesi = d2.kd_dokter
    LEFT JOIN petugas p ON ssa.nip_perawat_ok = p.nip
    WHERE ssa.no_rawat = '$no_rawat'
    ORDER BY ssa.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data sign in sebelum anestesi tidak ditemukan</div>';
    exit;
}

// Loop data
while ($data = mysqli_fetch_assoc($result)):
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<div class="card mb-3 shadow-sm">    
    <div class="card-body">
        <!-- HEADER INFO -->
        <div class="section-title">
            <i class="fa fa-clipboard-check"></i> Sign In Sebelum Anestesi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Tanggal:</span>
                <span class="info-value"><?= date('d/m/Y H:i', strtotime($data['tanggal'])) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">SNCN:</span>
                <span class="info-value"><?= htmlspecialchars($data['sncn']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tindakan:</span>
                <span class="info-value"><?= htmlspecialchars($data['tindakan']) ?: '-' ?></span>
            </div>
        </div>

        <!-- TIM MEDIS -->
        <div class="section-title">
            <i class="fa fa-user-md"></i> Tim Medis
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Dokter Bedah:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_dokter_bedah']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Dokter Anestesi:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_dokter_anestesi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Perawat OK:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_perawat_ok']) ?: '-' ?></span>
            </div>
        </div>

        <!-- IDENTITAS & VERIFIKASI -->
        <div class="section-title">
            <i class="fa fa-id-card"></i> Identitas & Verifikasi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Identitas Pasien:</span>
                <span class="info-value"><?= getBadgeChecklist($data['identitas']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Penandaan Area Operasi:</span>
                <span class="info-value"><?= getBadgeAdaTidak($data['penandaan_area_operasi']) ?></span>
            </div>
        </div>

        <!-- ALERGI -->
        <div class="section-title">
            <i class="fa fa-exclamation-triangle"></i> Alergi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Alergi:</span>
                <span class="info-value"><?= htmlspecialchars($data['alergi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- RESIKO ASPIRASI -->
        <div class="section-title">
            <i class="fa fa-lungs"></i> Resiko Aspirasi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Resiko Aspirasi:</span>
                <span class="info-value"><?= getBadgeAdaTidak($data['resiko_aspirasi']) ?></span>
            </div>
            <?php if (!empty($data['resiko_aspirasi_rencana_antisipasi'])): ?>
            <div class="info-item">
                <span class="info-label">Rencana Antisipasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['resiko_aspirasi_rencana_antisipasi']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- RESIKO KEHILANGAN DARAH -->
        <div class="section-title">
            <i class="fa fa-tint"></i> Resiko Kehilangan Darah
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Resiko Kehilangan Darah:</span>
                <span class="info-value"><?= getBadgeAdaTidak($data['resiko_kehilangan_darah']) ?></span>
            </div>
            <?php if (!empty($data['resiko_kehilangan_darah_line'])): ?>
            <div class="info-item">
                <span class="info-label">IV Line:</span>
                <span class="info-value"><?= htmlspecialchars($data['resiko_kehilangan_darah_line']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['resiko_kehilangan_darah_rencana_antisipasi'])): ?>
            <div class="info-item">
                <span class="info-label">Rencana Antisipasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['resiko_kehilangan_darah_rencana_antisipasi']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- KESIAPAN ALAT OBAT ANESTESI -->
        <div class="section-title">
            <i class="fa fa-briefcase-medical"></i> Kesiapan Alat & Obat Anestesi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Kesiapan Alat:</span>
                <span class="info-value"><?= getBadgeKesiapan($data['kesiapan_alat_obat_anestesi']) ?></span>
            </div>
            <?php if (!empty($data['kesiapan_alat_obat_anestesi_rencana_antisipasi'])): ?>
            <div class="info-item">
                <span class="info-label">Rencana Antisipasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['kesiapan_alat_obat_anestesi_rencana_antisipasi']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endwhile; ?>