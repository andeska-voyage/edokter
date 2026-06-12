<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

// Get parameters
$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm    = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

// Badge Normal / Tidak Normal
function getBadgeNormal($value) {
    if (empty($value)) return '-';
    $v = trim($value);
    if ($v == 'Normal')       return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#10b981;color:#fff;">Normal</span>';
    if ($v == 'Tidak Normal') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#ef4444;color:#fff;">Tidak Normal</span>';
    return htmlspecialchars($value);
}

// Query hasil EKG
$query_ekg = "
    SELECT u.*, d.nm_dokter
    FROM hasil_pemeriksaan_ekg u
    LEFT JOIN dokter d ON u.kd_dokter = d.kd_dokter
    WHERE u.no_rawat = '$no_rawat'
    ORDER BY u.tanggal DESC
";
$result_ekg = bukaquery($query_ekg);

if (mysqli_num_rows($result_ekg) == 0) {
    echo '<div class="alert alert-warning m-3">Data hasil EKG tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_ekg)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl_obj).' '.$bulan[date('n',$tgl_obj)].' '.date('Y, H:i',$tgl_obj);

    // Query gambar
    $query_gambar = "SELECT photo FROM hasil_pemeriksaan_ekg_gambar WHERE no_rawat = '$no_rawat'";
    $result_gambar = bukaquery($query_gambar);
    $gambar_list = [];
    while ($g = mysqli_fetch_assoc($result_gambar)) {
        if (!empty($g['photo'])) $gambar_list[] = $g['photo'];
    }
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<style>
    .usg-foto-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 10px;
        margin-top: 8px;
    }
    .usg-foto-item {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
        background: #f8fafc;
        text-align: center;
    }
    .usg-foto-item img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        display: block;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    .usg-foto-item img:hover { opacity: 0.85; }
    .usg-foto-item .foto-caption {
        font-size: 10px;
        color: #94a3b8;
        padding: 4px 6px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .kesimpulan-block {
        padding: 10px 14px;
        background: #f0fdf4;
        border-left: 4px solid #10b981;
        border-radius: 0 6px 6px 0;
        font-size: 13px;
        color: #1e293b;
        line-height: 1.6;
    }
</style>
<div class="card mb-3 shadow-sm">
    <div class="card-body">

        <!-- I. INFORMASI PEMERIKSAAN -->
        <div class="section-title">
            <i class="fa fa-info-circle"></i> I. Informasi Pemeriksaan
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Tanggal:</span>
                <span class="info-value"><?= $tanggal_format ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Dokter:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_dokter']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Diagnosa Klinis:</span>
                <span class="info-value"><?= htmlspecialchars($data['diagnosa_klinis']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kiriman Dari:</span>
                <span class="info-value"><?= htmlspecialchars($data['kiriman_dari']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. HASIL PEMERIKSAAN EKG -->
        <div class="section-title">
            <i class="fa fa-wave-square"></i> II. Hasil Pemeriksaan EKG
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Irama:</span>
                <span class="info-value"><?= htmlspecialchars($data['irama']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Laju Jantung:</span>
                <span class="info-value"><?= htmlspecialchars($data['laju_jantung']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Gelombang P:</span>
                <span class="info-value"><?= htmlspecialchars($data['gelombangp']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Interval PR:</span>
                <span class="info-value"><?= htmlspecialchars($data['intervalpr']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Axis:</span>
                <span class="info-value"><?= htmlspecialchars($data['axis']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kompleks QRS:</span>
                <span class="info-value"><?= htmlspecialchars($data['kompleksqrs']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Segmen ST:</span>
                <span class="info-value"><?= getBadgeNormal($data['segmenst']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Gelombang T:</span>
                <span class="info-value"><?= getBadgeNormal($data['gelombangt']) ?></span>
            </div>
        </div>

        <!-- III. KESIMPULAN -->
        <div class="section-title">
            <i class="fa fa-check-circle"></i> III. Kesimpulan
        </div>
        <div class="kesimpulan-block">
            <?= nl2br(htmlspecialchars($data['kesimpulan'])) ?: '-' ?>
        </div>

        <!-- IV. REKAMAN EKG -->
        <div class="section-title" style="margin-top:16px;">
            <i class="fa fa-images"></i> IV. Rekaman EKG
        </div>
        <?php if (!empty($gambar_list)): ?>
        <div class="usg-foto-grid">
            <?php foreach ($gambar_list as $idx => $photo): ?>
            <div class="usg-foto-item">
                <img src="<?= EKG_BASE_URL . $photo ?>"
                     alt="Rekaman EKG <?= $idx + 1 ?>"
                     onclick="window.open('<?= EKG_BASE_URL . $photo ?>', '_blank')"
                     title="Klik untuk perbesar">
                <div class="foto-caption">Rekaman <?= $idx + 1 ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Tidak ada rekaman EKG tersedia.</div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>