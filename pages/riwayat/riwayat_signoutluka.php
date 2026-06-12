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

// Function untuk badge kelengkapan
function getBadgeKelengkapan($value) {
    if ($value == 'Lengkap') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">✓ Lengkap</span>';
    } elseif ($value == 'Tidak Lengkap') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #ef4444; color: #fff;">✗ Tidak Lengkap</span>';
    } elseif ($value == 'Tidak Ada Pemeriksaan') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #6b7280; color: #fff;">N/A</span>';
    } else {
        return htmlspecialchars($value);
    }
}

// Query untuk ambil data signout sebelum menutup luka
$query = "
    SELECT 
        ssml.*,
        d1.nm_dokter AS nm_dokter_bedah,
        d2.nm_dokter AS nm_dokter_anestesi,
        p.nama AS nm_perawat_ok
    FROM signout_sebelum_menutup_luka ssml
    LEFT JOIN dokter d1 ON ssml.kd_dokter_bedah = d1.kd_dokter
    LEFT JOIN dokter d2 ON ssml.kd_dokter_anestesi = d2.kd_dokter
    LEFT JOIN petugas p ON ssml.nip_perawat_ok = p.nip
    WHERE ssml.no_rawat = '$no_rawat'
    ORDER BY ssml.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data sign out sebelum menutup luka tidak ditemukan</div>';
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
            <i class="fa fa-clipboard-check"></i> Sign Out Sebelum Menutup Luka
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

        <!-- KONFIRMASI VERBAL -->
        <div class="section-title">
            <i class="fa fa-comments"></i> Konfirmasi Verbal
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Konfirmasi Tindakan:</span>
                <span class="info-value"><?= getBadgeChecklist($data['verbal_tindakan']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kelengkapan Kasa:</span>
                <span class="info-value"><?= getBadgeChecklist($data['verbal_kelengkapan_kasa']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kelengkapan Instrumen:</span>
                <span class="info-value"><?= getBadgeChecklist($data['verbal_instrumen']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kelengkapan Alat Tajam:</span>
                <span class="info-value"><?= getBadgeChecklist($data['verbal_alat_tajam']) ?></span>
            </div>
        </div>

        <!-- KELENGKAPAN SPECIMEN -->
        <div class="section-title">
            <i class="fa fa-flask"></i> Kelengkapan Specimen
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Label Specimen:</span>
                <span class="info-value"><?= getBadgeKelengkapan($data['kelengkapan_specimen_label']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Formulir Specimen:</span>
                <span class="info-value"><?= getBadgeKelengkapan($data['kelengkapan_specimen_formulir']) ?></span>
            </div>
        </div>

        <!-- PENINJAUAN KEGIATAN -->
        <div class="section-title">
            <i class="fa fa-eye"></i> Peninjauan Kegiatan
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Peninjauan Dokter Bedah:</span>
                <span class="info-value"><?= getBadgeChecklist($data['peninjauan_kegiatan_dokter_bedah']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Peninjauan Dokter Anestesi:</span>
                <span class="info-value"><?= getBadgeChecklist($data['peninjauan_kegiatan_dokter_anestesi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Peninjauan Perawat/Kamar OK:</span>
                <span class="info-value"><?= getBadgeChecklist($data['peninjauan_kegiatan_perawat_kamar_ok']) ?></span>
            </div>
        </div>

        <!-- PERHATIAN UTAMA FASE PEMULIHAN -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> Perhatian Utama Fase Pemulihan
        </div>
        <div class="info-grid">
            <?php if (!empty($data['perhatian_utama_fase_pemulihan'])): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Perhatian Utama:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['perhatian_utama_fase_pemulihan'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php endwhile; ?>