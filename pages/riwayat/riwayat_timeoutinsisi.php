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

// Function untuk badge penayangan
function getBadgePenayangan($value) {
    if ($value == 'Ditayangkan') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">✓ Ditayangkan</span>';
    } elseif ($value == 'Benar') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">✓ Benar</span>';
    } elseif ($value == 'Tidak Diperlukan') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #6b7280; color: #fff;">N/A</span>';
    } else {
        return htmlspecialchars($value);
    }
}

// Query untuk ambil data timeout sebelum insisi
$query = "
    SELECT 
        tsi.*,
        d1.nm_dokter AS nm_dokter_bedah,
        d2.nm_dokter AS nm_dokter_anestesi,
        p.nama AS nm_perawat_ok
    FROM timeout_sebelum_insisi tsi
    LEFT JOIN dokter d1 ON tsi.kd_dokter_bedah = d1.kd_dokter
    LEFT JOIN dokter d2 ON tsi.kd_dokter_anestesi = d2.kd_dokter
    LEFT JOIN petugas p ON tsi.nip_perawat_ok = p.nip
    WHERE tsi.no_rawat = '$no_rawat'
    ORDER BY tsi.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data time out sebelum insisi tidak ditemukan</div>';
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
            <i class="fa fa-clock"></i> Time Out Sebelum Insisi
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

        <!-- KONFIRMASI VERBAL TIM -->
        <div class="section-title">
            <i class="fa fa-comments"></i> Konfirmasi Verbal Tim
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Konfirmasi Identitas:</span>
                <span class="info-value"><?= getBadgeChecklist($data['verbal_identitas']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Konfirmasi Tindakan:</span>
                <span class="info-value"><?= getBadgeChecklist($data['verbal_tindakan']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Konfirmasi Area Insisi:</span>
                <span class="info-value"><?= getBadgeChecklist($data['verbal_area_insisi']) ?></span>
            </div>
        </div>

        <!-- PENANDAAN & LAMA OPERASI -->
        <div class="section-title">
            <i class="fa fa-map-marker-alt"></i> Penandaan & Durasi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Penandaan Area Operasi:</span>
                <span class="info-value"><?= getBadgeAdaTidak($data['penandaan_area_operasi']) ?></span>
            </div>
            <?php if (!empty($data['lama_operasi'])): ?>
            <div class="info-item">
                <span class="info-label">Lama Operasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['lama_operasi']) ?> menit</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- PENAYANGAN HASIL PENUNJANG -->
        <div class="section-title">
            <i class="fa fa-images"></i> Penayangan Hasil Penunjang
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Radiologi:</span>
                <span class="info-value"><?= getBadgePenayangan($data['penayangan_radiologi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">CT Scan:</span>
                <span class="info-value"><?= getBadgePenayangan($data['penayangan_ctscan']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">MRI:</span>
                <span class="info-value"><?= getBadgePenayangan($data['penayangan_mri']) ?></span>
            </div>
        </div>

        <!-- ANTIBIOTIK PROFILAKSIS -->
        <div class="section-title">
            <i class="fa fa-pills"></i> Antibiotik Profilaksis
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Antibiotik Profilaksis:</span>
                <span class="info-value"><?= getBadgeChecklist($data['antibiotik_profilaks']) ?></span>
            </div>
            <?php if (!empty($data['nama_antibiotik'])): ?>
            <div class="info-item">
                <span class="info-label">Nama Antibiotik:</span>
                <span class="info-value"><?= htmlspecialchars($data['nama_antibiotik']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['jam_pemberian'])): ?>
            <div class="info-item">
                <span class="info-label">Jam Pemberian:</span>
                <span class="info-value"><?= htmlspecialchars($data['jam_pemberian']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- ANTISIPASI KEHILANGAN DARAH -->
        <div class="section-title">
            <i class="fa fa-tint"></i> Antisipasi Kehilangan Darah
        </div>
        <div class="info-grid">
            <?php if (!empty($data['antisipasi_kehilangan_darah'])): ?>
            <div class="info-item">
                <span class="info-label">Antisipasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['antisipasi_kehilangan_darah']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- HAL KHUSUS -->
        <div class="section-title">
            <i class="fa fa-exclamation-circle"></i> Hal Khusus
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Hal Khusus:</span>
                <span class="info-value"><?= getBadgeAdaTidak($data['hal_khusus']) ?></span>
            </div>
            <?php if (!empty($data['hal_khusus_diperhatikan'])): ?>
            <div class="info-item">
                <span class="info-label">Yang Diperhatikan:</span>
                <span class="info-value"><?= htmlspecialchars($data['hal_khusus_diperhatikan']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- STERILISASI -->
        <div class="section-title">
            <i class="fa fa-shield-virus"></i> Sterilisasi
        </div>
        <div class="info-grid">
            <?php if (!empty($data['tanggal_steril'])): ?>
            <div class="info-item">
                <span class="info-label">Tanggal Steril:</span>
                <span class="info-value"><?= date('d/m/Y', strtotime($data['tanggal_steril'])) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Petujuk Sterilisasi:</span>
                <span class="info-value"><?= getBadgeChecklist($data['petujuk_sterilisasi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Verifikasi Preoperatif:</span>
                <span class="info-value"><?= getBadgeChecklist($data['verifikasi_preoperatif']) ?></span>
            </div>
        </div>
    </div>
</div>

<?php endwhile; ?>