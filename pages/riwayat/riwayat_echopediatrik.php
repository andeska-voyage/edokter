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

// Query data echo pediatrik
$query_echo = "
    SELECT 
        p.*,
        d.nm_dokter
    FROM hasil_pemeriksaan_echo_pediatrik p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_echo = bukaquery($query_echo);

if (mysqli_num_rows($result_echo) == 0) {
    echo '<div class="alert alert-warning m-3">Data pemeriksaan echo pediatrik tidak ditemukan</div>';
    exit;
}

// Loop data
while ($data = mysqli_fetch_assoc($result_echo)):
    // Format tanggal Indonesia
    $bulan = array(
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d', $tanggal_obj) . ' ' . 
                     $bulan[date('n', $tanggal_obj)] . ' ' . 
                     date('Y, H:i', $tanggal_obj);

    // Query gambar echo untuk no_rawat ini
    $query_gambar = "
        SELECT photo
        FROM hasil_pemeriksaan_echo_pediatrik_gambar
        WHERE no_rawat = '$no_rawat'
    ";
    $result_gambar = bukaquery($query_gambar);
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
                <span class="info-label">Kiriman Dari:</span>
                <span class="info-value"><?= htmlspecialchars($data['kiriman_dari']) ?: '-' ?></span>
            </div>
        </div>

        <!-- I. INFORMASI UMUM -->
        <div class="section-title">
            <i class="fa fa-info-circle"></i> I. Informasi Umum
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Diagnosa Klinis:</span>
                <span class="info-value"><?= htmlspecialchars($data['diagnosa_klinis']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Situs:</span>
                <span class="info-value"><?= htmlspecialchars($data['situs']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">AV/VA:</span>
                <span class="info-value"><?= htmlspecialchars($data['av_va']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Drainase Vena Pulmonalis:</span>
                <span class="info-value"><?= htmlspecialchars($data['drainase_vena_pulmonalis']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. PEMERIKSAAN KATUP -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> II. Pemeriksaan Katup
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Katup Mitral:</span>
                <span class="info-value"><?= htmlspecialchars($data['katup_mitral']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Katup Aorta:</span>
                <span class="info-value"><?= htmlspecialchars($data['katup_aorta']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Katup Tricuspid:</span>
                <span class="info-value"><?= htmlspecialchars($data['katup_tricuspid']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Katup Pulmonal:</span>
                <span class="info-value"><?= htmlspecialchars($data['katup_pulmonal']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Katup Septum Atrium:</span>
                <span class="info-value"><?= htmlspecialchars($data['katup_septum_atrium']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Katup Septum Ventrikal:</span>
                <span class="info-value"><?= htmlspecialchars($data['katup_septum_ventrikal']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Katup Arkus Aorta:</span>
                <span class="info-value"><?= htmlspecialchars($data['katup_arkus_aorta']) ?: '-' ?></span>
            </div>
        </div>
        <?php if (!empty($data['katup_keterangan_lainnya'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Keterangan Lainnya:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['katup_keterangan_lainnya'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- III. RUANG JANTUNG -->
        <div class="section-title">
            <i class="fa fa-procedures"></i> III. Ruang Jantung
        </div>
        <div class="info-grid">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Ruang Jantung:</span>
                <span class="info-value"><?= htmlspecialchars($data['ruang_jantung']) ?: '-' ?></span>
            </div>
        </div>

        <!-- IV. MODE M -->
        <div class="section-title">
            <i class="fa fa-chart-line"></i> IV. Mode M
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">IVS:</span>
                <span class="info-value"><?= htmlspecialchars($data['mode_ivds']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">IVSS:</span>
                <span class="info-value"><?= htmlspecialchars($data['mode_ivss']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">LVID Dextra:</span>
                <span class="info-value"><?= htmlspecialchars($data['mode_lvid_dextra']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">LVID Sinistra:</span>
                <span class="info-value"><?= htmlspecialchars($data['mode_lvid_sinistra']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">LVPW Dextra:</span>
                <span class="info-value"><?= htmlspecialchars($data['mode_lvpw_dextra']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">LVPW Sinistra:</span>
                <span class="info-value"><?= htmlspecialchars($data['mode_lvpw_sinistra']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ejection Fraction:</span>
                <span class="info-value"><?= htmlspecialchars($data['mode_ejection_fraction']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Fraction Shortening:</span>
                <span class="info-value"><?= htmlspecialchars($data['mode_fraction_shotening']) ?: '-' ?></span>
            </div>
        </div>

        <!-- V. DOPPLER -->
        <div class="section-title">
            <i class="fa fa-wave-square"></i> V. Doppler
        </div>
        <div class="info-grid">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['doppler'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VI. KESIMPULAN & SARAN -->
        <div class="section-title">
            <i class="fa fa-file-medical"></i> VI. Kesimpulan &amp; Saran
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-label">Kesimpulan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['kesimpulan'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-label">Saran:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['saran'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VII. GAMBAR USG ECHO -->
        <?php if (mysqli_num_rows($result_gambar) > 0): ?>
        <div class="section-title">
            <i class="fa fa-images"></i> VII. Gambar USG Echo
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px;">
            <?php while ($gambar = mysqli_fetch_assoc($result_gambar)): 
                $img_url = PEMERIKSAAN_ECHO_PEDIATRIK_BASE_URL . '/' . $gambar['photo'];
            ?>
            <div style="flex: 0 0 auto;">
                <a href="<?= $img_url ?>" target="_blank" title="Klik untuk perbesar">
                    <img src="<?= $img_url ?>" 
                        alt="Gambar Echo Pediatrik" 
                        style="width: 180px; height: 140px; object-fit: cover; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; transition: transform 0.2s ease, box-shadow 0.2s ease;"
                        onmouseover="this.style.transform='scale(1.04)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.18)';"
                        onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                </a>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>