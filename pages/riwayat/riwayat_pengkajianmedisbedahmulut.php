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

// Function untuk badge status pemeriksaan (Tidak/Ya)
function getBadgeStatus($value) {
    if (empty($value)) {
        return '-';
    }
    $value_lower = strtolower(trim($value));
    if ($value_lower == 'ya') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #ef4444; color: #fff;">Ya</span>';
    } elseif ($value_lower == 'tidak') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">Tidak</span>';
    } else {
        return htmlspecialchars($value);
    }
}

// Query data pengkajian medis Bedah Mulut (ralan)
$query_medis = "
    SELECT 
        p.*,
        d.nm_dokter
    FROM penilaian_medis_ralan_bedah_mulut p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data pengkajian medis Bedah Mulut tidak ditemukan</div>';
    exit;
}

// Loop data
while ($data = mysqli_fetch_assoc($result_medis)):
    $bulan = array(
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d', $tanggal_obj) . ' ' .
                     $bulan[date('n', $tanggal_obj)] . ' ' .
                     date('Y, H:i', $tanggal_obj);

    // Data organ dengan keterangan
    $organs = [
        ['label' => 'Kulit',        'field' => 'kulit',        'ket' => 'keterangan_kulit'],
        ['label' => 'Kepala',       'field' => 'kepala',       'ket' => 'keterangan_kepala'],
        ['label' => 'Mata',         'field' => 'mata',         'ket' => 'keterangan_mata'],
        ['label' => 'Leher',        'field' => 'leher',        'ket' => 'keterangan_leher'],
        ['label' => 'Kelenjar',     'field' => 'kelenjar',     'ket' => 'keterangan_kelenjar'],
        ['label' => 'Dada',         'field' => 'dada',         'ket' => 'keterangan_dada'],
        ['label' => 'Perut',        'field' => 'perut',        'ket' => 'keterangan_perut'],
        ['label' => 'Ekstremitas',  'field' => 'ekstremitas',  'ket' => 'keterangan_ekstremitas'],
    ];
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<style>
    .organ-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin: 8px 0;
    }
    .organ-table thead tr {
        background-color: #f1f5f9;
    }
    .organ-table thead th {
        padding: 7px 10px;
        text-align: left;
        font-weight: 600;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        border-bottom: 2px solid #e2e8f0;
    }
    .organ-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
    }
    .organ-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .organ-table tbody td {
        padding: 6px 10px;
        font-size: 12px;
        color: #1e293b;
        vertical-align: middle;
    }
    .organ-table tbody td.col-label {
        font-weight: 600;
        color: #475569;
        width: 18%;
    }
    .organ-table tbody td.col-status {
        width: 15%;
    }
    .organ-table tbody td.col-ket {
        color: #64748b;
        font-size: 12px;
    }
    .lokalis-block {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 16px;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px dashed #e2e8f0;
    }
    .lokalis-block:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    .lokalis-block .lokalis-label {
        font-size: 12px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 6px;
    }
    .lokalis-block .lokalis-text {
        font-size: 13px;
        color: #1e293b;
        line-height: 1.6;
    }
    .lokalis-block img {
        width: 100%;
        height: auto;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
    .lokalis-img-caption {
        font-size: 11px;
        color: #94a3b8;
        text-align: center;
        margin-top: 4px;
        font-style: italic;
    }
    @media (max-width: 768px) {
        .lokalis-block {
            grid-template-columns: 1fr;
        }
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
                <span class="info-label">Riwayat Penyakit Keluarga:</span>
                <span class="info-value"><?= htmlspecialchars($data['rpk']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Alergi:</span>
                <span class="info-value"><?= htmlspecialchars($data['alergi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. PEMERIKSAAN FISIK -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> II. Pemeriksaan Fisik
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Keadaan:</span>
                <span class="info-value"><?= htmlspecialchars($data['keadaan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kesadaran:</span>
                <span class="info-value"><?= htmlspecialchars($data['kesadaran']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nyeri:</span>
                <span class="info-value"><?= htmlspecialchars($data['nyeri']) ?: '-' ?></span>
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
                <span class="info-label">TB:</span>
                <span class="info-value"><?= htmlspecialchars($data['tb']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Status Nutrisi:</span>
                <span class="info-value"><?= htmlspecialchars($data['status_nutrisi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Tabel Pemeriksaan Organ (Tidak/Ya + Keterangan) -->
        <table class="organ-table mt-2">
            <thead>
                <tr>
                    <th class="col-label">Organ</th>
                    <th class="col-status">Kelainan</th>
                    <th class="col-ket">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($organs as $org): ?>
                <tr>
                    <td class="col-label"><?= $org['label'] ?></td>
                    <td class="col-status"><?= getBadgeStatus($data[$org['field']]) ?></td>
                    <td class="col-ket"><?= htmlspecialchars($data[$org['ket']]) ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- III. PEMERIKSAAN PENUNJANG -->
        <div class="section-title">
            <i class="fa fa-vial"></i> III. Pemeriksaan Penunjang
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

        <!-- IV. STATUS LOKALISATA -->
        <div class="section-title">
            <i class="fa fa-map-marker-alt"></i> IV. Status Lokalisata
        </div>

        <!-- Wajah -->
        <div class="lokalis-block">
            <div>
                <div class="lokalis-label">Wajah :</div>
                <div class="lokalis-text"><?= nl2br(htmlspecialchars($data['wajah'])) ?: '-' ?></div>
            </div>
            <div>
                <img src="<?= APP_BASE_URL ?>/images/wajah1.png" alt="Ilustrasi Wajah (R &amp; L)">
                <div class="lokalis-img-caption">Ilustrasi Wajah (R &amp; L)</div>
            </div>
        </div>

        <!-- Intra Oral -->
        <div class="lokalis-block">
            <div>
                <div class="lokalis-label">Intra Oral :</div>
                <div class="lokalis-text"><?= nl2br(htmlspecialchars($data['intra'])) ?: '-' ?></div>
            </div>
            <div>
                <img src="<?= APP_BASE_URL ?>/images/wajah2.png" alt="Ilustrasi Intra Oral">
                <div class="lokalis-img-caption">Ilustrasi Intra Oral</div>
            </div>
        </div>

        <!-- Gigi Geligi -->
        <div class="lokalis-block">
            <div>
                <div class="lokalis-label">Gigi Geligi :</div>
                <div class="lokalis-text"><?= nl2br(htmlspecialchars($data['gigigeligi'])) ?: '-' ?></div>
            </div>
            <div>
                <img src="<?= APP_BASE_URL ?>/images/gigigeligi.png" alt="Ilustrasi Gigi Geligi">
                <div class="lokalis-img-caption">Ilustrasi Gigi Geligi</div>
            </div>
        </div>

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
                <span class="info-value"><?= nl2br(htmlspecialchars($data['diagnosis2'])) ?: '-' ?></span>
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