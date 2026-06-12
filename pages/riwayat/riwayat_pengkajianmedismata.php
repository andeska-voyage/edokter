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

// Function untuk badge status pemeriksaan
function getBadgeStatus($value) {
    if (empty($value)) {
        return '-';
    }
    
    $value_lower = strtolower(trim($value));
    
    if ($value_lower == 'normal') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">Normal</span>';
    } elseif ($value_lower == 'abnormal') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #ef4444; color: #fff;">Abnormal</span>';
    } elseif ($value_lower == 'tidak diperiksa') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #fbbf24; color: #1e293b;">Tidak Diperiksa</span>';
    } else {
        return htmlspecialchars($value);
    }
}

// Query data pengkajian medis Mata (ralan)
$query_medis = "
    SELECT 
        p.*,
        d.nm_dokter
    FROM penilaian_medis_ralan_mata p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data pengkajian medis Mata tidak ditemukan</div>';
    exit;
}

// Loop data
while ($data = mysqli_fetch_assoc($result_medis)):
    // Format tanggal Indonesia
    $bulan = array(
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d', $tanggal_obj) . ' ' . 
                     $bulan[date('n', $tanggal_obj)] . ' ' . 
                     date('Y, H:i', $tanggal_obj);

    // Data oftalmologis OD/OS
    $oftalmologis = [
        ['label' => 'Visus SC',      'od' => $data['visuskanan'],   'os' => $data['visuskiri']],
        ['label' => 'CC',            'od' => $data['cckanan'],      'os' => $data['cckiri']],
        ['label' => 'Palpebra',      'od' => $data['palkanan'],     'os' => $data['palkiri']],
        ['label' => 'Conjungtiva',   'od' => $data['conkanan'],     'os' => $data['conkiri']],
        ['label' => 'Cornea',        'od' => $data['corneakanan'],  'os' => $data['corneakiri']],
        ['label' => 'COA',           'od' => $data['coakanan'],     'os' => $data['coakiri']],
        ['label' => 'Pupil',         'od' => $data['pupilkanan'],   'os' => $data['pupilkiri']],
        ['label' => 'Lensa',         'od' => $data['lensakanan'],   'os' => $data['lensakiri']],
        ['label' => 'Fundus Media',  'od' => $data['funduskanan'],  'os' => $data['funduskiri']],
        ['label' => 'Papil',         'od' => $data['papilkanan'],   'os' => $data['papilkiri']],
        ['label' => 'Retina',        'od' => $data['retinakanan'],  'os' => $data['retinakiri']],
        ['label' => 'Makula',        'od' => $data['makulakanan'],  'os' => $data['makulakiri']],
        ['label' => 'TIO',           'od' => $data['tiokanan'],     'os' => $data['tiokiri']],
        ['label' => 'MBO',           'od' => $data['mbokanan'],     'os' => $data['mbokiri']],
    ];
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<style>
    .oftal-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin-top: 8px;
    }
    .oftal-table thead tr {
        background-color: #1d4ed8;
        color: #fff;
    }
    .oftal-table thead th {
        padding: 8px 12px;
        text-align: center;
        font-weight: 600;
        font-size: 12px;
    }
    .oftal-table thead th.col-od { text-align: left; }
    .oftal-table thead th.col-os { text-align: left; }
    .oftal-table tbody tr {
        border-bottom: 1px solid #e2e8f0;
    }
    .oftal-table tbody tr:nth-child(even) {
        background-color: #f8fafc;
    }
    .oftal-table tbody tr:hover {
        background-color: #eff6ff;
    }
    .oftal-table tbody td {
        padding: 6px 12px;
        font-size: 12px;
        color: #1e293b;
        vertical-align: middle;
    }
    .oftal-table tbody td.col-label {
        text-align: center;
        font-weight: 600;
        color: #334155;
        background-color: #f1f5f9;
        width: 26%;
    }
    .oftal-table tbody td.col-od,
    .oftal-table tbody td.col-os {
        width: 37%;
        color: #374151;
    }
    .oftal-mata-img {
        display: flex;
        gap: 12px;
        margin-bottom: 12px;
    }
    .oftal-mata-img .mata-box {
        flex: 1;
        background: #f0f4ff;
        border: 1px solid #c7d2fe;
        border-radius: 8px;
        padding: 10px;
        text-align: center;
    }
    .oftal-mata-img .mata-box .mata-title {
        font-size: 12px;
        font-weight: 700;
        color: #1d4ed8;
        margin-bottom: 6px;
    }
    .oftal-mata-img .mata-box img {
        width: 100%;
        max-width: 260px;
        height: auto;
    }
</style>
<div class="card mb-3 shadow-sm">    
    <div class="card-body">

        <!-- I. RIWAYAT KESEHATAN -->
        <div class="section-title">
            <i class="fa fa-notes-medical"></i> I. Riwayat Kesehatan
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <span class="info-label">Cara Anamnesis:</span>
                <span class="info-value"><?= htmlspecialchars($data['anamnesis']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Hubungan dengan Pasien:</span>
                <span class="info-value"><?= htmlspecialchars($data['hubungan']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Keluhan Utama:</span>
                <span class="info-value"><?= htmlspecialchars($data['keluhan_utama']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penyakit Sekarang:</span>
                <span class="info-value"><?= htmlspecialchars($data['rps']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penyakit Dahulu:</span>
                <span class="info-value"><?= htmlspecialchars($data['rpd']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penggunaan Obat:</span>
                <span class="info-value"><?= htmlspecialchars($data['rpo']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Alergi:</span>
                <span class="info-value"><?= htmlspecialchars($data['alergi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. PEMERIKSAAN FISIK UMUM -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> II. Pemeriksaan Fisik Umum
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Status:</span>
                <span class="info-value"><?= htmlspecialchars($data['status']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TD:</span>
                <span class="info-value"><?= htmlspecialchars($data['td']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nadi:</span>
                <span class="info-value"><?= htmlspecialchars($data['nadi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">RR:</span>
                <span class="info-value"><?= htmlspecialchars($data['rr']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Suhu:</span>
                <span class="info-value"><?= htmlspecialchars($data['suhu']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">BB:</span>
                <span class="info-value"><?= htmlspecialchars($data['bb']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nyeri:</span>
                <span class="info-value"><?= htmlspecialchars($data['nyeri']) ?: '-' ?></span>
            </div>
        </div>

        <!-- III. STATUS OFTALMOLOGIS -->
        <div class="section-title">
            <i class="fa fa-eye"></i> III. Status Oftalmologis
        </div>

        <!-- Gambar Mata OD dan OS -->
        <div class="oftal-mata-img">
            <div class="mata-box">
                <div class="mata-title">OD : Mata Kanan</div>
                <img src="<?= APP_BASE_URL ?>/images/mata.png" alt="Mata Kanan (OD)">
            </div>
            <div class="mata-box">
                <div class="mata-title">OS : Mata Kiri</div>
                <img src="<?= APP_BASE_URL ?>/images/mata.png" alt="Mata Kiri (OS)">
            </div>
        </div>

        <!-- Tabel Oftalmologis OD vs OS -->
        <table class="oftal-table">
            <thead>
                <tr>
                    <th class="col-od">OD (Kanan)</th>
                    <th style="text-align:center; width:26%;">Pemeriksaan</th>
                    <th class="col-os">OS (Kiri)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($oftalmologis as $row): ?>
                <tr>
                    <td class="col-od"><?= htmlspecialchars($row['od']) ?: '-' ?></td>
                    <td class="col-label"><?= htmlspecialchars($row['label']) ?></td>
                    <td class="col-os"><?= htmlspecialchars($row['os']) ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- IV. PEMERIKSAAN PENUNJANG -->
        <div class="section-title">
            <i class="fa fa-vial"></i> IV. Pemeriksaan Penunjang
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">Laboratorium:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['lab'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Radiologi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rad'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Penunjang Lainnya:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['penunjang'])) ?: '-' ?></span>
            </div>
        </div>
        <?php if (!empty($data['tes']) || !empty($data['pemeriksaan'])): ?>
        <div class="info-grid mt-2">
            <?php if (!empty($data['tes'])): ?>
            <div class="info-item-vertical">
                <span class="info-label">Tes:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['tes'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['pemeriksaan'])): ?>
            <div class="info-item-vertical">
                <span class="info-label">Pemeriksaan Tambahan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['pemeriksaan'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- V. DIAGNOSIS/ASESMEN -->
        <div class="section-title">
            <i class="fa fa-file-medical"></i> V. Diagnosis/Asesmen
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">Diagnosis:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['diagnosis'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Diagnosis Banding:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['diagnosisbdg'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Permasalahan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['permasalahan'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VI. TATALAKSANA -->
        <div class="section-title">
            <i class="fa fa-procedures"></i> VI. Tatalaksana
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">Terapi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['terapi'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Tindakan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['tindakan'])) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['edukasi'])): ?>
            <div class="info-item-vertical">
                <span class="info-label">Edukasi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['edukasi'])) ?></span>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php endwhile; ?>