<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm    = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

$query = "
    SELECT rpr.*, d.nm_dokter
    FROM resume_pasien_ranap rpr
    LEFT JOIN dokter d ON rpr.kd_dokter = d.kd_dokter
    WHERE rpr.no_rawat = '$no_rawat'
    ORDER BY rpr.kontrol DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data resume pasien rawat inap tidak ditemukan</div>';
    exit;
}

// Badge cara keluar
function getBadgeCaraKeluar($cara) {
    if (empty($cara)) return '-';
    $colors = [
        'Atas Izin Dokter'               => '#10b981',
        'Pindah RS'                      => '#3b82f6',
        'Pulang Atas Permintaan Sendiri' => '#f59e0b',
        'Meninggal'                      => '#ef4444',
    ];
    $bg = isset($colors[$cara]) ? $colors[$cara] : '#64748b';
    return "<span style='display:inline-block;padding:3px 10px;font-size:11px;font-weight:600;border-radius:3px;background:{$bg};color:#fff;'>" . htmlspecialchars($cara) . "</span>";
}

// Badge keadaan
function getBadgeKeadaan($keadaan) {
    if (empty($keadaan)) return '-';
    $colors = [
        'Membaik'             => '#10b981',
        'Sembuh'              => '#10b981',
        'Keadaan Khusus'      => '#f59e0b',
        'Meninggal < 48 Jam'  => '#ef4444',
        'Meninggal > 48 Jam'  => '#ef4444',
    ];
    $bg = isset($colors[$keadaan]) ? $colors[$keadaan] : '#64748b';
    return "<span style='display:inline-block;padding:3px 10px;font-size:11px;font-weight:600;border-radius:3px;background:{$bg};color:#fff;'>" . htmlspecialchars($keadaan) . "</span>";
}

// Badge dilanjutkan
function getBadgeDilanjutkan($val) {
    if (empty($val)) return '-';
    $colors = [
        'Kembali Ke RS' => '#3b82f6',
        'RS Lain'       => '#8b5cf6',
        'Dokter Luar'   => '#f97316',
        'Puskesmas'     => '#06b6d4',
    ];
    $bg = isset($colors[$val]) ? $colors[$val] : '#64748b';
    return "<span style='display:inline-block;padding:3px 10px;font-size:11px;font-weight:600;border-radius:3px;background:{$bg};color:#fff;'>" . htmlspecialchars($val) . "</span>";
}

// Badge ICD kode diagnosa/prosedur
function getBadgeKode($kode) {
    if (empty($kode)) return '';
    return "<span style='display:inline-block;padding:2px 8px;font-size:11px;font-weight:700;border-radius:3px;background:#dc2626;color:#fff;margin-right:6px;'>" . htmlspecialchars($kode) . "</span>";
}

$bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<?php while ($row = mysqli_fetch_assoc($result)):
    $tgl = strtotime($row['kontrol']);
    $kontrol_format = date('d', $tgl) . ' ' . $bulan[date('n', $tgl)] . ' ' . date('Y, H:i', $tgl);
?>
<div class="card mb-3 shadow-sm">
    <div class="card-body">

        <!-- HEADER INFO -->
        <div class="info-grid mb-2">
            <div class="info-item">
                <span class="info-label">Dokter:</span>
                <span class="info-value"><?= htmlspecialchars($row['nm_dokter']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tanggal Kontrol:</span>
                <span class="info-value"><?= $kontrol_format ?></span>
            </div>
        </div>

        <!-- I. DIAGNOSIS -->
        <div class="section-title">
            <i class="fa fa-file-medical"></i> I. Diagnosis
        </div>

        <!-- Diagnosis Awal & Alasan -->
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <span class="info-label">Diagnosis Awal:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['diagnosa_awal'])) ?: '-' ?></span>
            </div>
            <?php if (!empty($row['alasan'])): ?>
            <div class="info-item-vertical">
                <span class="info-label">Alasan Dirawat:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['alasan'])) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Diagnosis Utama & Sekunder -->
        <?php
        $diagnosa_list = [
            'Utama'       => [$row['kd_diagnosa_utama'],    $row['diagnosa_utama']],
            'Sekunder 1'  => [$row['kd_diagnosa_sekunder'],  $row['diagnosa_sekunder']],
            'Sekunder 2'  => [$row['kd_diagnosa_sekunder2'], $row['diagnosa_sekunder2']],
            'Sekunder 3'  => [$row['kd_diagnosa_sekunder3'], $row['diagnosa_sekunder3']],
            'Sekunder 4'  => [$row['kd_diagnosa_sekunder4'], $row['diagnosa_sekunder4']],
        ];
        $has_diagnosa = false;
        foreach ($diagnosa_list as $lbl => [$kd, $nm]) {
            if (!empty($nm)) { $has_diagnosa = true; break; }
        }
        if ($has_diagnosa):
        ?>
        <div style="margin-top:6px;">
            <?php foreach ($diagnosa_list as $lbl => [$kd, $nm]):
                if (empty($nm)) continue; ?>
            <div class="info-item" style="padding:5px 0;border-bottom:1px solid #f1f5f9;align-items:center;">
                <span class="info-label" style="min-width:110px;">Diagnosis <?= $lbl ?>:</span>
                <span class="info-value">
                    <?= getBadgeKode($kd) ?><?= htmlspecialchars($nm) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- II. RIWAYAT PEMERIKSAAN & PENGOBATAN -->
        <div class="section-title">
            <i class="fa fa-stethoscope"></i> II. Riwayat Pemeriksaan &amp; Pengobatan
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical" style="grid-column:1/-1;">
                <span class="info-label">Keluhan Utama:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['keluhan_utama'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical" style="grid-column:1/-1;">
                <span class="info-label">Pemeriksaan Fisik:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['pemeriksaan_fisik'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical" style="grid-column:1/-1;">
                <span class="info-label">Jalannya Penyakit:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['jalannya_penyakit'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical" style="grid-column:1/-1;">
                <span class="info-label">Pemeriksaan Penunjang:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['pemeriksaan_penunjang'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical" style="grid-column:1/-1;">
                <span class="info-label">Hasil Laboratorium:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['hasil_laborat'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- III. PROSEDUR & TINDAKAN -->
        <?php
        $prosedur_list = [
            'Utama'       => [$row['kd_prosedur_utama'],    $row['prosedur_utama']],
            'Sekunder 1'  => [$row['kd_prosedur_sekunder'],  $row['prosedur_sekunder']],
            'Sekunder 2'  => [$row['kd_prosedur_sekunder2'], $row['prosedur_sekunder2']],
            'Sekunder 3'  => [$row['kd_prosedur_sekunder3'], $row['prosedur_sekunder3']],
        ];
        $has_prosedur = !empty($row['tindakan_dan_operasi']);
        foreach ($prosedur_list as [$kd, $nm]) {
            if (!empty($nm)) { $has_prosedur = true; break; }
        }
        if ($has_prosedur):
        ?>
        <div class="section-title">
            <i class="fa fa-procedures"></i> III. Prosedur &amp; Tindakan
        </div>
        <?php foreach ($prosedur_list as $lbl => [$kd, $nm]):
            if (empty($nm)) continue; ?>
        <div class="info-item" style="padding:5px 0;border-bottom:1px solid #f1f5f9;align-items:center;">
            <span class="info-label" style="min-width:110px;">Prosedur <?= $lbl ?>:</span>
            <span class="info-value">
                <?= getBadgeKode($kd) ?><?= htmlspecialchars($nm) ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php if (!empty($row['tindakan_dan_operasi'])): ?>
        <div class="info-item-vertical mt-2" style="grid-column:1/-1;">
            <span class="info-label">Tindakan &amp; Operasi:</span>
            <span class="info-value"><?= nl2br(htmlspecialchars($row['tindakan_dan_operasi'])) ?></span>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- IV. KONDISI KELUAR & TINDAK LANJUT -->
        <div class="section-title">
            <i class="fa fa-sign-out-alt"></i> IV. Kondisi Keluar &amp; Tindak Lanjut
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Cara Keluar:</span>
                <span class="info-value"><?= getBadgeCaraKeluar($row['cara_keluar']) ?></span>
            </div>
            <?php if (!empty($row['ket_keluar'])): ?>
            <div class="info-item">
                <span class="info-label">Ket. Keluar:</span>
                <span class="info-value"><?= htmlspecialchars($row['ket_keluar']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Keadaan:</span>
                <span class="info-value"><?= getBadgeKeadaan($row['keadaan']) ?></span>
            </div>
            <?php if (!empty($row['ket_keadaan'])): ?>
            <div class="info-item">
                <span class="info-label">Ket. Keadaan:</span>
                <span class="info-value"><?= htmlspecialchars($row['ket_keadaan']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($row['dilanjutkan'])): ?>
            <div class="info-item">
                <span class="info-label">Dilanjutkan:</span>
                <span class="info-value"><?= getBadgeDilanjutkan($row['dilanjutkan']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($row['ket_dilanjutkan'])): ?>
            <div class="info-item">
                <span class="info-label">Ket. Dilanjutkan:</span>
                <span class="info-value"><?= htmlspecialchars($row['ket_dilanjutkan']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- V. INFORMASI LAINNYA -->
        <?php
        $has_info = !empty($row['alergi']) || !empty($row['diet']) || !empty($row['lab_belum'])
                 || !empty($row['edukasi']) || !empty($row['obat_pulang']);
        if ($has_info):
        ?>
        <div class="section-title">
            <i class="fa fa-info-circle"></i> V. Informasi Lainnya
        </div>
        <div class="info-grid-vertical">
            <?php if (!empty($row['alergi'])): ?>
            <div class="info-item-vertical" style="grid-column:1/-1;">
                <span class="info-label">Alergi:</span>
                <span class="info-value" style="border-left:3px solid #f59e0b;padding-left:8px;background:#fffbeb;">
                    <?= nl2br(htmlspecialchars($row['alergi'])) ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if (!empty($row['diet'])): ?>
            <div class="info-item-vertical">
                <span class="info-label">Diet:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['diet'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($row['lab_belum'])): ?>
            <div class="info-item-vertical">
                <span class="info-label">Lab Belum Selesai:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['lab_belum'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($row['edukasi'])): ?>
            <div class="info-item-vertical">
                <span class="info-label">Edukasi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['edukasi'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($row['obat_pulang'])): ?>
            <div class="info-item-vertical" style="grid-column:1/-1;">
                <span class="info-label">Obat Pulang:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($row['obat_pulang'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>