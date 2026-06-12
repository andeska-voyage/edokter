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

// Function untuk badge Ada/Tidak
function getBadgeAdaTidak($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'ada') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">Ada</span>';
    } elseif ($v == 'tidak') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #94a3b8; color: #fff;">Tidak</span>';
    }
    return htmlspecialchars($value);
}

// Query data penilaian medis ranap kandungan
$query_medis = "
    SELECT 
        p.*,
        d.nm_dokter
    FROM penilaian_medis_ranap_kandungan p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data pengkajian medis ranap kandungan tidak ditemukan</div>';
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
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<div class="card mb-3 shadow-sm">
    <div class="card-body">

        <!-- HEADER INFO -->
        <div class="info-grid mb-2">
            <div class="info-item">
                <span class="info-label">Tanggal:</span>
                <span class="info-value"><?= $tanggal_format ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Dokter:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_dokter']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Anamnesis:</span>
                <span class="info-value"><?= htmlspecialchars($data['anamnesis']) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['hubungan'])): ?>
            <div class="info-item">
                <span class="info-label">Hubungan:</span>
                <span class="info-value"><?= htmlspecialchars($data['hubungan']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- I. RIWAYAT KESEHATAN -->
        <div class="section-title">
            <i class="fa fa-notes-medical"></i> I. Riwayat Kesehatan
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <span class="info-label">Keluhan Utama:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['keluhan_utama'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penyakit Sekarang:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rps'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penyakit Dahulu:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rpd'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penyakit Keluarga:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rpk'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penggunaan Obat:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rpo'])) ?: '-' ?></span>
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
                <span class="info-label">Keadaan:</span>
                <span class="info-value"><?= htmlspecialchars($data['keadaan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">GCS:</span>
                <span class="info-value"><?= htmlspecialchars($data['gcs']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kesadaran:</span>
                <span class="info-value"><?= htmlspecialchars($data['kesadaran']) ?: '-' ?></span>
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
                <span class="info-label">SpO2:</span>
                <span class="info-value"><?= htmlspecialchars($data['spo']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">BB:</span>
                <span class="info-value"><?= htmlspecialchars($data['bb']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TB:</span>
                <span class="info-value"><?= htmlspecialchars($data['tb']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Pemeriksaan Per Organ -->
        <div class="info-grid" style="margin-top: 6px;">
            <?php
            $organs = [
                'kepala'      => 'Kepala',
                'mata'        => 'Mata',
                'gigi'        => 'Gigi',
                'tht'         => 'THT',
                'thoraks'     => 'Thoraks',
                'jantung'     => 'Jantung',
                'paru'        => 'Paru',
                'abdomen'     => 'Abdomen',
                'genital'     => 'Genital',
                'ekstremitas' => 'Ekstremitas',
                'kulit'       => 'Kulit',
            ];
            foreach ($organs as $col => $label):
            ?>
            <div class="info-item">
                <span class="info-label"><?= $label ?>:</span>
                <span class="info-value"><?= getBadgeStatus($data[$col]) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($data['ket_fisik'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Keterangan Fisik:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['ket_fisik'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- III. PEMERIKSAAN OBSTETRI -->
        <div class="section-title">
            <i class="fa fa-baby"></i> III. Pemeriksaan Obstetri
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">TFU:</span>
                <span class="info-value"><?= htmlspecialchars($data['tfu']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TBJ:</span>
                <span class="info-value"><?= htmlspecialchars($data['tbj']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">HIS:</span>
                <span class="info-value"><?= htmlspecialchars($data['his']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kontraksi:</span>
                <span class="info-value"><?= getBadgeAdaTidak($data['kontraksi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">DJJ:</span>
                <span class="info-value"><?= htmlspecialchars($data['djj']) ?: '-' ?></span>
            </div>
        </div>

        <?php
        // Tampilkan blok obstetri detail hanya jika ada isinya
        $obs_fields = [
            'inspeksi'  => ['label' => 'Inspeksi',   'icon' => 'fa-eye'],
            'inspekulo' => ['label' => 'Inspekulo',   'icon' => 'fa-search'],
            'vt'        => ['label' => 'VT (Vaginal Toucher)', 'icon' => 'fa-hand-paper'],
            'rt'        => ['label' => 'RT (Rectal Toucher)',  'icon' => 'fa-hand-paper'],
        ];
        foreach ($obs_fields as $col => $meta):
            if (!empty($data[$col])):
        ?>
        <div class="info-grid-vertical mt-2">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-label"><i class="fa <?= $meta['icon'] ?>"></i> <?= $meta['label'] ?>:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data[$col])) ?></span>
            </div>
        </div>
        <?php
            endif;
        endforeach;
        ?>

        <!-- IV. PEMERIKSAAN PENUNJANG -->
        <div class="section-title">
            <i class="fa fa-vial"></i> IV. Pemeriksaan Penunjang
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <span class="info-label">USG:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['ultra'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Kardiotokografi (KTG):</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['kardio'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Laboratorium:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['lab'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- V. DIAGNOSIS/ASESMEN -->
        <div class="section-title">
            <i class="fa fa-file-medical"></i> V. Diagnosis/Asesmen
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['diagnosis'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VI. TATALAKSANA -->
        <div class="section-title">
            <i class="fa fa-procedures"></i> VI. Tatalaksana
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['tata'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VII. EDUKASI -->
        <?php if (!empty($data['edukasi'])): ?>
        <div class="section-title">
            <i class="fa fa-chalkboard-teacher"></i> VII. Edukasi
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['edukasi'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>