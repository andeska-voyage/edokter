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

// Query hasil Endoskopi Hidung
$query_endo = "
    SELECT u.*, d.nm_dokter
    FROM hasil_endoskopi_hidung u
    LEFT JOIN dokter d ON u.kd_dokter = d.kd_dokter
    WHERE u.no_rawat = '$no_rawat'
    ORDER BY u.tanggal DESC
";
$result_endo = bukaquery($query_endo);

if (mysqli_num_rows($result_endo) == 0) {
    echo '<div class="alert alert-warning m-3">Data hasil Endoskopi Hidung tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_endo)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl_obj).' '.$bulan[date('n',$tgl_obj)].' '.date('Y, H:i',$tgl_obj);

    // Query gambar
    $query_gambar = "
        SELECT photo
        FROM hasil_endoskopi_hidung_gambar
        WHERE no_rawat = '$no_rawat'
    ";
    $result_gambar = bukaquery($query_gambar);
    $gambar_list = [];
    while ($g = mysqli_fetch_assoc($result_gambar)) {
        if (!empty($g['photo'])) $gambar_list[] = $g['photo'];
    }

    // Data berpasangan Kanan/Kiri
    $pemeriksaan = [
        ['label' => 'Kondisi Hidung',  'kanan' => 'kondisi_hidung_kanan',  'kiri' => 'kondisi_hidung_kiri'],
        ['label' => 'Kavum Nasi',      'kanan' => 'kavum_nasi_kanan',      'kiri' => 'kavum_nasi_kiri'],
        ['label' => 'Konka Inferior',  'kanan' => 'konka_inferior_kanan',  'kiri' => 'konka_inferior_kiri'],
        ['label' => 'Meatus Medius',   'kanan' => 'meatus_medius_kanan',   'kiri' => 'meatus_medius_kiri'],
        ['label' => 'Septum',          'kanan' => 'septum_kanan',          'kiri' => 'septum_kiri'],
        ['label' => 'Nasofaring',      'kanan' => 'nasofaring_kanan',      'kiri' => 'nasofaring_kiri'],
        ['label' => 'Lain-lain',       'kanan' => 'lainlain_kanan',        'kiri' => 'lainlain_kiri'],
    ];
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<style>
    .endo-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin: 6px 0 10px 0;
    }
    .endo-table thead tr {
        background-color: #1d4ed8;
        color: #fff;
    }
    .endo-table thead th {
        padding: 8px 12px;
        font-weight: 600;
        font-size: 12px;
    }
    .endo-table thead th.col-label { text-align: center; width: 26%; }
    .endo-table thead th.col-kanan { text-align: left; width: 37%; }
    .endo-table thead th.col-kiri  { text-align: left; width: 37%; }
    .endo-table tbody tr { border-bottom: 1px solid #e2e8f0; }
    .endo-table tbody tr:nth-child(even) { background-color: #f8fafc; }
    .endo-table tbody tr:hover { background-color: #eff6ff; }
    .endo-table tbody td {
        padding: 7px 12px;
        font-size: 12px;
        color: #1e293b;
        vertical-align: middle;
    }
    .endo-table tbody td.col-label {
        text-align: center;
        font-weight: 600;
        color: #334155;
        background-color: #f1f5f9;
    }
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
            <div class="info-item">
                <span class="info-label">Diagnosa Klinis:</span>
                <span class="info-value"><?= htmlspecialchars($data['diagnosa_klinis']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kiriman Dari:</span>
                <span class="info-value"><?= htmlspecialchars($data['kiriman_dari']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. HASIL PEMERIKSAAN ENDOSKOPI HIDUNG -->
        <div class="section-title">
            <i class="fa fa-stethoscope"></i> II. Hasil Pemeriksaan Endoskopi Hidung
        </div>
        <table class="endo-table">
            <thead>
                <tr>
                    <th class="col-kanan">Kanan</th>
                    <th class="col-label">Pemeriksaan</th>
                    <th class="col-kiri">Kiri</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pemeriksaan as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($data[$item['kanan']]) ?: '-' ?></td>
                    <td class="col-label"><?= $item['label'] ?></td>
                    <td><?= htmlspecialchars($data[$item['kiri']]) ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- III. KESIMPULAN -->
        <div class="section-title">
            <i class="fa fa-check-circle"></i> III. Kesimpulan
        </div>
        <div class="kesimpulan-block">
            <?= nl2br(htmlspecialchars($data['kesimpulan'])) ?: '-' ?>
        </div>

        <!-- IV. FOTO ENDOSKOPI -->
        <div class="section-title" style="margin-top:16px;">
            <i class="fa fa-images"></i> IV. Foto Endoskopi
        </div>
        <?php if (!empty($gambar_list)): ?>
        <div class="usg-foto-grid">
            <?php foreach ($gambar_list as $idx => $photo): ?>
            <div class="usg-foto-item">
                <img src="<?= ENDOSKOPI_HIDUNG_BASE_URL . $photo ?>"
                     alt="Foto Endoskopi <?= $idx + 1 ?>"
                     onclick="window.open('<?= ENDOSKOPI_HIDUNG_BASE_URL . $photo ?>', '_blank')"
                     title="Klik untuk perbesar">
                <div class="foto-caption">Foto <?= $idx + 1 ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Tidak ada foto endoskopi tersedia.</div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>