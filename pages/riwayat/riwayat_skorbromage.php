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

// Function untuk badge skor
function getBadgeSkor($skor) {
    $skor_num = intval($skor);
    if ($skor_num == 0) {
        $color = '#28a745';  // hijau - tidak ada blok
    } elseif ($skor_num == 1) {
        $color = '#3b82f6';  // biru - blok parsial
    } elseif ($skor_num == 2) {
        $color = '#ffc107';  // kuning - blok hampir lengkap
    } elseif ($skor_num == 3) {
        $color = '#dc3545';  // merah - blok lengkap
    } else {
        $color = '#6c757d';  // abu-abu
    }
    return "<span style='display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 700; border-radius: 3px; background-color: {$color}; color: #fff; min-width: 30px; text-align: center;'>{$skor}</span>";
}

// Function untuk badge total dengan interpretasi
function getBadgeTotal($total) {
    $total_num = intval($total);
    if ($total_num == 0) {
        $color = '#28a745';  // hijau
        $status = 'Tidak Ada Blok Motorik';
    } elseif ($total_num == 1) {
        $color = '#3b82f6';  // biru
        $status = 'Blok Parsial';
    } elseif ($total_num == 2) {
        $color = '#ffc107';  // kuning
        $status = 'Blok Hampir Lengkap';
    } elseif ($total_num == 3) {
        $color = '#dc3545';  // merah
        $status = 'Blok Lengkap';
    } else {
        $color = '#6c757d';
        $status = '';
    }
    
    $badge = "<span style='display: inline-block; padding: 6px 16px; font-size: 18px; font-weight: 700; border-radius: 4px; background-color: {$color}; color: #fff;'>{$total}</span>";
    if ($status) {
        $badge .= " <span style='color: {$color}; font-weight: 600; margin-left: 8px; font-size: 14px;'>({$status})</span>";
    }
    return $badge;
}

// Query untuk ambil data skor bromage
$query = "
    SELECT 
        sbpa.*,
        d.nm_dokter,
        p.nama AS nama_petugas
    FROM skor_bromage_pasca_anestesi sbpa
    LEFT JOIN dokter d ON sbpa.kd_dokter = d.kd_dokter
    LEFT JOIN petugas p ON sbpa.nip = p.nip
    WHERE sbpa.no_rawat = '$no_rawat'
    ORDER BY sbpa.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data skor bromage pasca anestesi tidak ditemukan</div>';
    exit;
}

// Loop data
while ($data = mysqli_fetch_assoc($result)):
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<div class="card mb-3 shadow-sm">    
    <div class="card-body">
        <!-- HEADER INFO -->
        <div class="section-title">
            <i class="fa fa-walking"></i> Skor Bromage Pasca Anestesi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Tanggal:</span>
                <span class="info-value"><?= date('d/m/Y H:i', strtotime($data['tanggal'])) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Dokter:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_dokter']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Petugas:</span>
                <span class="info-value"><?= htmlspecialchars($data['nama_petugas']) ?: '-' ?></span>
            </div>
        </div>

        <!-- PENILAIAN BROMAGE SCORE -->
        <div class="section-title">
            <i class="fa fa-clipboard-list"></i> Penilaian Bromage Score (Blok Motorik)
        </div>
        
        <!-- PENILAIAN GERAKAN -->
        <div style="margin-bottom: 15px; padding: 12px; background-color: #f8f9fa; border-left: 4px solid #667eea; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 13px; color: #2c3e50; margin-bottom: 6px;">Gerakan Ekstremitas Bawah</div>
                    <div style="font-size: 12px; color: #666; line-height: 1.5;"><?= htmlspecialchars($data['penilaian_skala1']) ?></div>
                </div>
                <div style="margin-left: 15px;"><?= getBadgeSkor($data['penilaian_nilai1']) ?></div>
            </div>
        </div>

        <!-- INTERPRETASI SKOR -->
        <div style="margin-top: 15px; padding: 12px; background-color: #f1f5f9; border-radius: 4px; border: 1px solid #e2e8f0;">
            <div style="font-size: 11px; color: #64748b; margin-bottom: 8px; font-weight: 600;">PANDUAN INTERPRETASI SKOR BROMAGE:</div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; font-size: 11px;">
                <div style="display: flex; align-items: center;">
                    <?= getBadgeSkor(0) ?>
                    <span style="margin-left: 8px; color: #475569;">Gerakan penuh dari tungkai</span>
                </div>
                <div style="display: flex; align-items: center;">
                    <?= getBadgeSkor(1) ?>
                    <span style="margin-left: 8px; color: #475569;">Tidak mampu ekstensi, fleksi lutut</span>
                </div>
                <div style="display: flex; align-items: center;">
                    <?= getBadgeSkor(2) ?>
                    <span style="margin-left: 8px; color: #475569;">Tidak mampu fleksi lutut</span>
                </div>
                <div style="display: flex; align-items: center;">
                    <?= getBadgeSkor(3) ?>
                    <span style="margin-left: 8px; color: #475569;">Tidak ada gerakan sama sekali</span>
                </div>
            </div>
        </div>

        <!-- TOTAL SKOR -->
        <div style="margin-top: 20px; padding: 15px; background-color: #e7f3ff; border-left: 4px solid #2196F3; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="font-weight: 700; font-size: 16px; color: #2c3e50;">Total Skor Bromage:</div>
                <div><?= getBadgeTotal($data['penilaian_nilai1']) ?></div>
            </div>
        </div>

        <!-- KONDISI KELUAR & INSTRUKSI -->
        <?php if (!empty($data['keluar']) || !empty($data['instruksi'])): ?>
        <div class="section-title">
            <i class="fa fa-sign-out-alt"></i> Kondisi Keluar & Instruksi
        </div>
        <div class="info-grid">
            <?php if (!empty($data['keluar'])): ?>
            <div class="info-item">
                <span class="info-label">Keluar PACU:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['keluar'])) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['instruksi'])): ?>
            <div class="info-item">
                <span class="info-label">Instruksi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['instruksi'])) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endwhile; ?>