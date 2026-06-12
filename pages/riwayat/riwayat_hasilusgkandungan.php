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

// Badge derajat maturitas plasenta
function getBadgeDerajat($value) {
    if ($value === null || $value === '') return '-';
    $colors = [
        '0' => ['#64748b', '#fff'],
        '1' => ['#3b82f6', '#fff'],
        '2' => ['#f59e0b', '#1e293b'],
        '3' => ['#ef4444', '#fff'],
    ];
    $v = trim($value);
    if (isset($colors[$v])) {
        return '<span style="display:inline-block;padding:3px 10px;font-size:11px;font-weight:600;border-radius:3px;background:'
            . $colors[$v][0] . ';color:' . $colors[$v][1] . ';">Grade ' . $v . '</span>';
    }
    return htmlspecialchars($value);
}

// Badge jumlah air ketuban
function getBadgeAirKetuban($value) {
    if (empty($value)) return '-';
    $v = trim($value);
    if ($v == 'Cukup')     return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#10b981;color:#fff;">Cukup</span>';
    if ($v == 'Berkurang') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#f59e0b;color:#1e293b;">Berkurang</span>';
    return htmlspecialchars($value);
}

// Badge peluang sex
function getBadgeSex($value) {
    if (empty($value) || $value == '-') return '-';
    $v = trim($value);
    if ($v == 'Laki-laki')  return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#3b82f6;color:#fff;">&#9794; Laki-laki</span>';
    if ($v == 'Perempuan')  return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#ec4899;color:#fff;">&#9792; Perempuan</span>';
    return htmlspecialchars($value);
}

// Query hasil USG
$query_usg = "
    SELECT u.*, d.nm_dokter
    FROM hasil_pemeriksaan_usg u
    LEFT JOIN dokter d ON u.kd_dokter = d.kd_dokter
    WHERE u.no_rawat = '$no_rawat'
    ORDER BY u.tanggal DESC
";
$result_usg = bukaquery($query_usg);

if (mysqli_num_rows($result_usg) == 0) {
    echo '<div class="alert alert-warning m-3">Data hasil USG tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_usg)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl_obj).' '.$bulan[date('n',$tgl_obj)].' '.date('Y, H:i',$tgl_obj);

    // Query gambar USG untuk record ini (berdasarkan no_rawat + tanggal jika perlu, atau semua)
    $query_gambar = "
        SELECT photo
        FROM hasil_pemeriksaan_usg_gambar
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
    .usg-foto-item img:hover {
        opacity: 0.85;
    }
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
            <div class="info-item">
                <span class="info-label">HTA:</span>
                <span class="info-value"><?= htmlspecialchars($data['hta']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. HASIL BIOMETRI JANIN -->
        <div class="section-title">
            <i class="fa fa-baby"></i> II. Biometri Janin
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Kantong Gestasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['kantong_gestasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ukuran Bokong-Kepala:</span>
                <span class="info-value"><?= htmlspecialchars($data['ukuran_bokongkepala']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Jenis Presentasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['jenis_prestasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Diameter Biparietal (BPD):</span>
                <span class="info-value"><?= htmlspecialchars($data['diameter_biparietal']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Panjang Femur (FL):</span>
                <span class="info-value"><?= htmlspecialchars($data['panjang_femur']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Lingkar Abdomen (AC):</span>
                <span class="info-value"><?= htmlspecialchars($data['lingkar_abdomen']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tafsiran Berat Janin:</span>
                <span class="info-value"><?= htmlspecialchars($data['tafsiran_berat_janin']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Usia Kehamilan:</span>
                <span class="info-value"><?= htmlspecialchars($data['usia_kehamilan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kelainan Kongenital:</span>
                <span class="info-value"><?= htmlspecialchars($data['kelainan_kongenital']) ?: '-' ?></span>
            </div>
        </div>

        <!-- III. PLASENTA & CAIRAN KETUBAN -->
        <div class="section-title">
            <i class="fa fa-tint"></i> III. Plasenta &amp; Cairan Ketuban
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Plasenta Berimplantasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['plasenta_berimplatansi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Derajat Maturitas:</span>
                <span class="info-value"><?= getBadgeDerajat($data['derajat_maturitas']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Jumlah Air Ketuban:</span>
                <span class="info-value"><?= getBadgeAirKetuban($data['jumlah_air_ketuban']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Indeks Cairan Ketuban:</span>
                <span class="info-value"><?= htmlspecialchars($data['indek_cairan_ketuban']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Peluang Jenis Kelamin:</span>
                <span class="info-value"><?= getBadgeSex($data['peluang_sex']) ?></span>
            </div>
        </div>

        <!-- IV. KESIMPULAN -->
        <div class="section-title">
            <i class="fa fa-check-circle"></i> IV. Kesimpulan
        </div>
        <div class="kesimpulan-block">
            <?= nl2br(htmlspecialchars($data['kesimpulan'])) ?: '-' ?>
        </div>

        <!-- V. FOTO USG -->
        <?php if (!empty($gambar_list)): ?>
        <div class="section-title" style="margin-top:16px;">
            <i class="fa fa-images"></i> V. Foto USG
        </div>
        <div class="usg-foto-grid">
            <?php foreach ($gambar_list as $idx => $photo): ?>
            <div class="usg-foto-item">
                <img src="<?= USG_BASE_URL . $photo ?>"
                     alt="Foto USG <?= $idx + 1 ?>"
                     onclick="window.open('<?= USG_BASE_URL . $photo ?>', '_blank')"
                     title="Klik untuk perbesar">
                <div class="foto-caption">Foto <?= $idx + 1 ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="section-title" style="margin-top:16px;">
            <i class="fa fa-images"></i> V. Foto USG
        </div>
        <div class="empty-state">Tidak ada foto USG tersedia.</div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>