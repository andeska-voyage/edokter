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

// Query hasil Endoskopi Faring-Laring
$query_endo = "
    SELECT u.*, d.nm_dokter
    FROM hasil_endoskopi_faring_laring u
    LEFT JOIN dokter d ON u.kd_dokter = d.kd_dokter
    WHERE u.no_rawat = '$no_rawat'
    ORDER BY u.tanggal DESC
";
$result_endo = bukaquery($query_endo);

if (mysqli_num_rows($result_endo) == 0) {
    echo '<div class="alert alert-warning m-3">Data hasil Endoskopi Faring-Laring tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_endo)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl_obj).' '.$bulan[date('n',$tgl_obj)].' '.date('Y, H:i',$tgl_obj);

    // Query gambar
    $query_gambar = "
        SELECT photo
        FROM hasil_endoskopi_faring_laring_gambar
        WHERE no_rawat = '$no_rawat'
    ";
    $result_gambar = bukaquery($query_gambar);
    $gambar_list = [];
    while ($g = mysqli_fetch_assoc($result_gambar)) {
        if (!empty($g['photo'])) $gambar_list[] = $g['photo'];
    }

    // Data faring
    $faring = [
        ['label' => 'Uvula',              'field' => 'faring_uvula'],
        ['label' => 'Arkus Faring',       'field' => 'faring_arkus_faring'],
        ['label' => 'Dinding Posterior',  'field' => 'faring_dinding_posterior'],
        ['label' => 'Tonsil',             'field' => 'faring_tonsil'],
    ];

    // Data laring
    $laring = [
        ['label' => 'Tonsil Lingual',      'field' => 'laring_tonsil_lingual'],
        ['label' => 'Valekula',            'field' => 'laring_valekula'],
        ['label' => 'Sinus Piriformis',    'field' => 'laring_sinus_piriformis'],
        ['label' => 'Epiglotis',           'field' => 'laring_epiglotis'],
        ['label' => 'Arytenoid',           'field' => 'laring_arytenoid'],
        ['label' => 'Plika Ventrikularis', 'field' => 'laring_plika_ventrikularis'],
        ['label' => 'Pita Suara',          'field' => 'laring_pita_suara'],
        ['label' => 'Rima Vocalis',        'field' => 'laring_rima_vocalis'],
    ];
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<style>
    .endo-section-sub {
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 5px 10px;
        background: #f1f5f9;
        border-left: 3px solid #dc2626;
        border-radius: 0 4px 4px 0;
        margin: 10px 0 6px 0;
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

        <!-- II. HASIL PEMERIKSAAN FARING -->
        <div class="section-title">
            <i class="fa fa-stethoscope"></i> II. Hasil Pemeriksaan Faring &amp; Laring
        </div>

        <!-- Sub: Faring -->
        <div class="endo-section-sub">Faring</div>
        <div class="info-grid">
            <?php foreach ($faring as $item): ?>
            <div class="info-item">
                <span class="info-label"><?= $item['label'] ?>:</span>
                <span class="info-value"><?= htmlspecialchars($data[$item['field']]) ?: '-' ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Sub: Laring -->
        <div class="endo-section-sub">Laring</div>
        <div class="info-grid">
            <?php foreach ($laring as $item): ?>
            <div class="info-item">
                <span class="info-label"><?= $item['label'] ?>:</span>
                <span class="info-value"><?= htmlspecialchars($data[$item['field']]) ?: '-' ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($data['laring_lainnya'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Lainnya:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['laring_lainnya'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- III. KESAN & SARAN -->
        <div class="section-title">
            <i class="fa fa-check-circle"></i> III. Kesan &amp; Saran
        </div>
        <?php if (!empty($data['kesan'])): ?>
        <div style="margin-bottom:6px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px;">Kesan:</div>
            <div class="kesan-block"><?= nl2br(htmlspecialchars($data['kesan'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['saran'])): ?>
        <div style="margin-bottom:6px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px;">Saran:</div>
            <div class="saran-block"><?= nl2br(htmlspecialchars($data['saran'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (empty($data['kesan']) && empty($data['saran'])): ?>
        <div class="empty-state">Tidak ada kesan/saran.</div>
        <?php endif; ?>

        <!-- IV. FOTO ENDOSKOPI -->
        <div class="section-title" style="margin-top:16px;">
            <i class="fa fa-images"></i> IV. Foto Endoskopi
        </div>
        <?php if (!empty($gambar_list)): ?>
        <div class="usg-foto-grid">
            <?php foreach ($gambar_list as $idx => $photo): ?>
            <div class="usg-foto-item">
                <img src="<?= ENDOSKOPI_FARING_LARING_BASE_URL . $photo ?>"
                     alt="Foto Endoskopi <?= $idx + 1 ?>"
                     onclick="window.open('<?= ENDOSKOPI_FARING_LARING_BASE_URL . $photo ?>', '_blank')"
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