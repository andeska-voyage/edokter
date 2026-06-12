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

// Query hasil Echokardiografi
$query_echo = "
    SELECT u.*, d.nm_dokter
    FROM hasil_pemeriksaan_echo u
    LEFT JOIN dokter d ON u.kd_dokter = d.kd_dokter
    WHERE u.no_rawat = '$no_rawat'
    ORDER BY u.tanggal DESC
";
$result_echo = bukaquery($query_echo);

if (mysqli_num_rows($result_echo) == 0) {
    echo '<div class="alert alert-warning m-3">Data hasil Echokardiografi tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_echo)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl_obj).' '.$bulan[date('n',$tgl_obj)].' '.date('Y, H:i',$tgl_obj);

    // Query gambar
    $query_gambar = "SELECT photo FROM hasil_pemeriksaan_echo_gambar WHERE no_rawat = '$no_rawat'";
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
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
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
        </div>

        <!-- II. HASIL ECHOKARDIOGRAFI -->
        <div class="section-title">
            <i class="fa fa-heart"></i> II. Hasil Echokardiografi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Sistolik:</span>
                <span class="info-value"><?= htmlspecialchars($data['sistolik']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Diastolik:</span>
                <span class="info-value"><?= htmlspecialchars($data['diastolic']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kontraktilitas:</span>
                <span class="info-value"><?= htmlspecialchars($data['kontraktilitas']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Dimensi Ruang:</span>
                <span class="info-value"><?= htmlspecialchars($data['dimensi_ruang']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Katup:</span>
                <span class="info-value"><?= htmlspecialchars($data['katup']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">ERAP:</span>
                <span class="info-value"><?= htmlspecialchars($data['erap']) ?: '-' ?></span>
            </div>
        </div>
        <?php if (!empty($data['analisa_segmental'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Analisa Segmental:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['analisa_segmental'])) ?></span>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['lain_lain'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Lain-lain:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['lain_lain'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- III. KESIMPULAN -->
        <div class="section-title">
            <i class="fa fa-check-circle"></i> III. Kesimpulan
        </div>
        <div class="kesimpulan-block">
            <?= nl2br(htmlspecialchars($data['kesimpulan'])) ?: '-' ?>
        </div>

        <!-- IV. FOTO ECHO -->
        <div class="section-title" style="margin-top:16px;">
            <i class="fa fa-images"></i> IV. Foto Echokardiografi
        </div>
        <?php if (!empty($gambar_list)): ?>
        <div class="usg-foto-grid">
            <?php foreach ($gambar_list as $idx => $photo): ?>
            <div class="usg-foto-item">
                <img src="<?= PEMERIKSAAN_ECHO_BASE_URL . $photo ?>"
                     alt="Foto Echo <?= $idx + 1 ?>"
                     onclick="window.open('<?= PEMERIKSAAN_ECHO_BASE_URL . $photo ?>', '_blank')"
                     title="Klik untuk perbesar">
                <div class="foto-caption">Foto <?= $idx + 1 ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Tidak ada foto echokardiografi tersedia.</div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>