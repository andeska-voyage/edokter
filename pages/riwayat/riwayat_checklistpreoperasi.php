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

// Function untuk badge keadaan umum
function getBadgeKeadaan($value) {
    $colors = [
        'Baik' => '#10b981',
        'Sedang' => '#fbbf24',
        'Lemah' => '#ef4444'
    ];
    $color = isset($colors[$value]) ? $colors[$value] : '#6b7280';
    return "<span style='display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: {$color}; color: #fff;'>{$value}</span>";
}

// Query untuk ambil data checklist pre operasi
$query = "
    SELECT 
        cpo.*,
        d1.nm_dokter AS nm_dokter_bedah,
        d2.nm_dokter AS nm_dokter_anestesi,
        p1.nama AS nm_petugas_ruangan,
        p2.nama AS nm_perawat_ok
    FROM checklist_pre_operasi cpo
    LEFT JOIN dokter d1 ON cpo.kd_dokter_bedah = d1.kd_dokter
    LEFT JOIN dokter d2 ON cpo.kd_dokter_anestesi = d2.kd_dokter
    LEFT JOIN petugas p1 ON cpo.nip_petugas_ruangan = p1.nip
    LEFT JOIN petugas p2 ON cpo.nip_perawat_ok = p2.nip
    WHERE cpo.no_rawat = '$no_rawat'
    ORDER BY cpo.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data checklist pre operasi tidak ditemukan</div>';
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
            <i class="fa fa-clipboard-check"></i> Checklist Pre Operasi
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
            <div class="info-item">
                <span class="info-label">Identitas Pasien:</span>
                <span class="info-value"><?= getBadgeChecklist($data['identitas']) ?></span>
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
                <span class="info-label">Petugas Ruangan:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_petugas_ruangan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Perawat OK:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_perawat_ok']) ?: '-' ?></span>
            </div>
        </div>

        <!-- SURAT IJIN & DOKUMEN -->
        <div class="section-title">
            <i class="fa fa-file-signature"></i> Surat Ijin & Dokumen
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Surat Ijin Bedah:</span>
                <span class="info-value"><?= getBadgeChecklist($data['surat_ijin_bedah']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Surat Ijin Anestesi:</span>
                <span class="info-value"><?= getBadgeChecklist($data['surat_ijin_anestesi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Surat Ijin Transfusi:</span>
                <span class="info-value"><?= getBadgeChecklist($data['surat_ijin_transfusi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Penandaan Area Operasi:</span>
                <span class="info-value"><?= getBadgeChecklist($data['penandaan_area_operasi']) ?></span>
            </div>
        </div>

        <!-- KONDISI PASIEN -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> Kondisi Pasien
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Keadaan Umum:</span>
                <span class="info-value"><?= getBadgeKeadaan($data['keadaan_umum']) ?></span>
            </div>
        </div>

        <!-- PEMERIKSAAN PENUNJANG -->
        <div class="section-title">
            <i class="fa fa-vial"></i> Pemeriksaan Penunjang
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Rontgen:</span>
                <span class="info-value"><?= getBadgeChecklist($data['pemeriksaan_penunjang_rontgen']) ?></span>
            </div>
            <?php if ($data['pemeriksaan_penunjang_rontgen'] != 'Tidak Diperlukan' && !empty($data['keterangan_pemeriksaan_penunjang_rontgen'])): ?>
            <div class="info-item">
                <span class="info-label">Keterangan Rontgen:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_pemeriksaan_penunjang_rontgen']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">EKG:</span>
                <span class="info-value"><?= getBadgeChecklist($data['pemeriksaan_penunjang_ekg']) ?></span>
            </div>
            <?php if ($data['pemeriksaan_penunjang_ekg'] != 'Tidak Diperlukan' && !empty($data['keterangan_pemeriksaan_penunjang_ekg'])): ?>
            <div class="info-item">
                <span class="info-label">Keterangan EKG:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_pemeriksaan_penunjang_ekg']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">USG:</span>
                <span class="info-value"><?= getBadgeChecklist($data['pemeriksaan_penunjang_usg']) ?></span>
            </div>
            <?php if ($data['pemeriksaan_penunjang_usg'] != 'Tidak Diperlukan' && !empty($data['keterangan_pemeriksaan_penunjang_usg'])): ?>
            <div class="info-item">
                <span class="info-label">Keterangan USG:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_pemeriksaan_penunjang_usg']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">CT Scan:</span>
                <span class="info-value"><?= getBadgeChecklist($data['pemeriksaan_penunjang_ctscan']) ?></span>
            </div>
            <?php if ($data['pemeriksaan_penunjang_ctscan'] != 'Tidak Diperlukan' && !empty($data['keterangan_pemeriksaan_penunjang_ctscan'])): ?>
            <div class="info-item">
                <span class="info-label">Keterangan CT Scan:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_pemeriksaan_penunjang_ctscan']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">MRI:</span>
                <span class="info-value"><?= getBadgeChecklist($data['pemeriksaan_penunjang_mri']) ?></span>
            </div>
            <?php if ($data['pemeriksaan_penunjang_mri'] != 'Tidak Diperlukan' && !empty($data['keterangan_pemeriksaan_penunjang_mri'])): ?>
            <div class="info-item">
                <span class="info-label">Keterangan MRI:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_pemeriksaan_penunjang_mri']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- PERSIAPAN PASIEN -->
        <div class="section-title">
            <i class="fa fa-tasks"></i> Persiapan Pasien
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Persiapan Darah:</span>
                <span class="info-value"><?= getBadgeChecklist($data['persiapan_darah']) ?></span>
            </div>
            <?php if (!empty($data['keterangan_persiapan_darah'])): ?>
            <div class="info-item">
                <span class="info-label">Keterangan Darah:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_persiapan_darah']) ?></span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">Perlengkapan Khusus:</span>
                <span class="info-value"><?= getBadgeChecklist($data['perlengkapan_khusus']) ?></span>
            </div>
        </div>
    </div>
</div>

<?php endwhile; ?>