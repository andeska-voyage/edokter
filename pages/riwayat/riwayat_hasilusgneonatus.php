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

// Query hasil USG Neonatus
$query_usg = "
    SELECT u.*, d.nm_dokter
    FROM hasil_pemeriksaan_usg_neonatus u
    LEFT JOIN dokter d ON u.kd_dokter = d.kd_dokter
    WHERE u.no_rawat = '$no_rawat'
    ORDER BY u.tanggal DESC
";
$result_usg = bukaquery($query_usg);

if (mysqli_num_rows($result_usg) == 0) {
    echo '<div class="alert alert-warning m-3">Data hasil USG Neonatus tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_usg)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl_obj).' '.$bulan[date('n',$tgl_obj)].' '.date('Y, H:i',$tgl_obj);

    // Query gambar USG Neonatus
    $query_gambar = "
        SELECT photo
        FROM hasil_pemeriksaan_usg_neonatus_gambar
        WHERE no_rawat = '$no_rawat'
    ";
    $result_gambar = bukaquery($query_gambar);
    $gambar_list = [];
    while ($g = mysqli_fetch_assoc($result_gambar)) {
        if (!empty($g['photo'])) {
            $gambar_list[] = $g['photo'];
        }
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
    .kesan-block {
        padding: 10px 14px;
        background: #eff6ff;
        border-left: 4px solid #3b82f6;
        border-radius: 0 6px 6px 0;
        font-size: 13px;
        color: #1e293b;
        line-height: 1.6;
        margin-bottom: 8px;
    }
    .kesimpulan-block {
        padding: 10px 14px;
        background: #f0fdf4;
        border-left: 4px solid #10b981;
        border-radius: 0 6px 6px 0;
        font-size: 13px;
        color: #1e293b;
        line-height: 1.6;
        margin-bottom: 8px;
    }
    .saran-block {
        padding: 10px 14px;
        background: #fefce8;
        border-left: 4px solid #f59e0b;
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

        <!-- II. HASIL PEMERIKSAAN NEONATUS -->
        <div class="section-title">
            <i class="fa fa-baby"></i> II. Hasil Pemeriksaan Neonatus
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <span class="info-label">Ventrikal Sinistra (Kiri):</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['ventrikal_sinistra'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Ventrikal Dextra (Kanan):</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['ventrikal_dextra'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- III. KESAN, KESIMPULAN & SARAN -->
        <div class="section-title">
            <i class="fa fa-check-circle"></i> III. Kesan, Kesimpulan &amp; Saran
        </div>
        <?php if (!empty($data['kesan'])): ?>
        <div style="margin-bottom:6px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px;">Kesan:</div>
            <div class="kesan-block"><?= nl2br(htmlspecialchars($data['kesan'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['kesimpulan'])): ?>
        <div style="margin-bottom:6px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px;">Kesimpulan:</div>
            <div class="kesimpulan-block"><?= nl2br(htmlspecialchars($data['kesimpulan'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['saran'])): ?>
        <div style="margin-bottom:6px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px;">Saran:</div>
            <div class="saran-block"><?= nl2br(htmlspecialchars($data['saran'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (empty($data['kesan']) && empty($data['kesimpulan']) && empty($data['saran'])): ?>
        <div class="empty-state">Tidak ada kesan/kesimpulan/saran.</div>
        <?php endif; ?>

        <!-- IV. FOTO USG NEONATUS -->
        <div class="section-title" style="margin-top:16px;">
            <i class="fa fa-images"></i> IV. Foto USG Neonatus
        </div>
        <?php if (!empty($gambar_list)): ?>
        <div class="usg-foto-grid">
            <?php foreach ($gambar_list as $idx => $photo): ?>
            <div class="usg-foto-item">
                <img src="<?= USG_NEONATUS_BASE_URL . $photo ?>"
                     alt="Foto USG Neonatus <?= $idx + 1 ?>"
                     onclick="window.open('<?= USG_NEONATUS_BASE_URL . $photo ?>', '_blank')"
                     title="Klik untuk perbesar">
                <div class="foto-caption">Foto <?= $idx + 1 ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Tidak ada foto USG tersedia.</div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>