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

// Query hasil Slit Lamp
$query_sl = "
    SELECT u.*, d.nm_dokter
    FROM hasil_pemeriksaan_slit_lamp u
    LEFT JOIN dokter d ON u.kd_dokter = d.kd_dokter
    WHERE u.no_rawat = '$no_rawat'
    ORDER BY u.tanggal DESC
";
$result_sl = bukaquery($query_sl);

if (mysqli_num_rows($result_sl) == 0) {
    echo '<div class="alert alert-warning m-3">Data hasil Slit Lamp tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_sl)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl_obj).' '.$bulan[date('n',$tgl_obj)].' '.date('Y, H:i',$tgl_obj);

    // Query gambar
    $query_gambar = "SELECT photo FROM hasil_pemeriksaan_slit_lamp_gambar WHERE no_rawat = '$no_rawat'";
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
    .hasil-block {
        padding: 10px 14px;
        background: #f8fafc;
        border-left: 4px solid #dc2626;
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

        <!-- II. HASIL PEMERIKSAAN SLIT LAMP -->
        <div class="section-title">
            <i class="fa fa-eye"></i> II. Hasil Pemeriksaan Slit Lamp
        </div>
        <div class="hasil-block">
            <?= nl2br(htmlspecialchars($data['hasil_pemeriksaan'])) ?: '-' ?>
        </div>

        <!-- III. FOTO SLIT LAMP -->
        <div class="section-title" style="margin-top:16px;">
            <i class="fa fa-images"></i> III. Foto Slit Lamp
        </div>
        <?php if (!empty($gambar_list)): ?>
        <div class="usg-foto-grid">
            <?php foreach ($gambar_list as $idx => $photo): ?>
            <div class="usg-foto-item">
                <img src="<?= PEMERIKSAAN_SLIT_LAMP_BASE_URL . $photo ?>"
                     alt="Foto Slit Lamp <?= $idx + 1 ?>"
                     onclick="window.open('<?= PEMERIKSAAN_SLIT_LAMP_BASE_URL . $photo ?>', '_blank')"
                     title="Klik untuk perbesar">
                <div class="foto-caption">Foto <?= $idx + 1 ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Tidak ada foto Slit Lamp tersedia.</div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>