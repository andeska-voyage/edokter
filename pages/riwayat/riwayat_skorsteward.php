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
        $color = '#dc3545';  // merah
    } elseif ($skor_num == 1) {
        $color = '#ffc107';  // kuning
    } elseif ($skor_num == 2) {
        $color = '#28a745';  // hijau
    } else {
        $color = '#6c757d';  // abu-abu
    }
    return "<span style='display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 700; border-radius: 3px; background-color: {$color}; color: #fff; min-width: 30px; text-align: center;'>{$skor}</span>";
}

// Function untuk badge total
function getBadgeTotal($total) {
    $total_num = intval($total);
    if ($total_num >= 5) {
        $color = '#28a745';  // hijau - siap keluar
        $status = 'Siap Keluar PACU';
    } elseif ($total_num >= 3) {
        $color = '#ffc107';  // kuning - observasi
        $status = 'Perlu Observasi';
    } elseif ($total_num >= 0) {
        $color = '#dc3545';  // merah - belum siap
        $status = 'Belum Siap';
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

// Query untuk ambil data skor steward
$query = "
    SELECT 
        sspa.*,
        d.nm_dokter,
        p.nama AS nama_petugas
    FROM skor_steward_pasca_anestesi sspa
    LEFT JOIN dokter d ON sspa.kd_dokter = d.kd_dokter
    LEFT JOIN petugas p ON sspa.nip = p.nip
    WHERE sspa.no_rawat = '$no_rawat'
    ORDER BY sspa.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data skor steward pasca anestesi tidak ditemukan</div>';
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
            <i class="fa fa-baby"></i> Skor Steward Pasca Anestesi (Pediatrik)
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

        <!-- PENILAIAN STEWARD SCORE -->
        <div class="section-title">
            <i class="fa fa-clipboard-list"></i> Penilaian Steward Score
        </div>
        
        <!-- 1. KESADARAN -->
        <div style="margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border-left: 4px solid #667eea; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 13px; color: #2c3e50; margin-bottom: 4px;">1. Kesadaran</div>
                    <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($data['penilaian_skala1']) ?></div>
                </div>
                <div><?= getBadgeSkor($data['penilaian_nilai1']) ?></div>
            </div>
        </div>

        <!-- 2. JALAN NAPAS -->
        <div style="margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border-left: 4px solid #667eea; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 13px; color: #2c3e50; margin-bottom: 4px;">2. Jalan Napas</div>
                    <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($data['penilaian_skala2']) ?></div>
                </div>
                <div><?= getBadgeSkor($data['penilaian_nilai2']) ?></div>
            </div>
        </div>

        <!-- 3. PERGERAKAN -->
        <div style="margin-bottom: 15px; padding: 10px; background-color: #f8f9fa; border-left: 4px solid #667eea; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 13px; color: #2c3e50; margin-bottom: 4px;">3. Pergerakan</div>
                    <div style="font-size: 12px; color: #666;"><?= htmlspecialchars($data['penilaian_skala3']) ?></div>
                </div>
                <div><?= getBadgeSkor($data['penilaian_nilai3']) ?></div>
            </div>
        </div>

        <!-- INTERPRETASI SKOR -->
        <div style="margin-top: 15px; padding: 12px; background-color: #f1f5f9; border-radius: 4px; border: 1px solid #e2e8f0;">
            <div style="font-size: 11px; color: #64748b; margin-bottom: 8px; font-weight: 600;">PANDUAN PENILAIAN:</div>
            <div style="font-size: 11px; color: #475569; line-height: 1.6;">
                <strong>Kesadaran:</strong> 0 = Tidak ada respons | 1 = Respons terhadap rangsangan | 2 = Sadar penuh<br>
                <strong>Jalan Napas:</strong> 0 = Memerlukan bantuan | 1 = Perlu penyangga jalan napas oral/nasal | 2 = Patensi jalan napas baik<br>
                <strong>Pergerakan:</strong> 0 = Tidak bergerak | 1 = Gerakan tanpa tujuan | 2 = Gerakan bertujuan/atas perintah
            </div>
        </div>

        <!-- TOTAL SKOR -->
        <div style="margin-top: 20px; padding: 15px; background-color: #e7f3ff; border-left: 4px solid #2196F3; border-radius: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="font-weight: 700; font-size: 16px; color: #2c3e50;">Total Skor Steward:</div>
                <div><?= getBadgeTotal($data['penilaian_totalnilai']) ?></div>
            </div>
            <div style="margin-top: 8px; font-size: 11px; color: #64748b;">
                <strong>Kriteria keluar PACU:</strong> Skor ≥ 5 atau kembali ke kondisi pra-anestesi
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