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

// Query untuk ambil data skrining nutrisi anak
$query = "
    SELECT 
        sna.tanggal,
        sna.td,
        sna.hr,
        sna.rr,
        sna.suhu,
        sna.bb,
        sna.tbpb,
        sna.spo2,
        sna.alergi,
        sna.sg1,
        sna.nilai1,
        sna.sg2,
        sna.nilai2,
        sna.sg3,
        sna.nilai3,
        sna.sg4,
        sna.nilai4,
        sna.total_hasil,
        sna.skor_nutrisi,
        sna.diketahui_dietisien,
        sna.keterangan_diketahui_dietisien,
        sna.nip,
        p.nama AS nama_petugas
    FROM skrining_nutrisi_anak sna
    LEFT JOIN petugas p ON sna.nip = p.nip
    WHERE sna.no_rawat = '$no_rawat'
    ORDER BY sna.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data skrining nutrisi anak tidak ditemukan</div>';
    exit;
}

// Function untuk badge total hasil
function getBadgeTotalHasil($total) {
    $total_num = intval($total);
    if ($total_num == 0) {
        $color = '#28a745';  // hijau - risiko rendah
        $status = 'Risiko Rendah';
    } elseif ($total_num >= 1 && $total_num <= 3) {
        $color = '#ffc107';  // kuning - risiko sedang
        $status = 'Risiko Sedang';
    } elseif ($total_num >= 4) {
        $color = '#dc3545';  // merah - risiko tinggi
        $status = 'Risiko Tinggi';
    } else {
        $color = '#6c757d';  // abu-abu
        $status = '';
    }
    
    $badge = "<span style='background-color: {$color}; color: white; padding: 6px 16px; border-radius: 4px; font-size: 16px; font-weight: bold; display: inline-block;'>{$total}</span>";
    if ($status) {
        $badge .= " <span style='color: {$color}; font-weight: bold; margin-left: 8px; font-size: 14px;'>({$status})</span>";
    }
    return $badge;
}

// Function untuk badge nilai skor
function getBadgeNilai($nilai) {
    $nilai_num = intval($nilai);
    if ($nilai_num == 0) {
        $color = '#28a745';  // hijau
    } elseif ($nilai_num == 1) {
        $color = '#ffc107';  // kuning
    } elseif ($nilai_num >= 2) {
        $color = '#dc3545';  // merah
    } else {
        $color = '#6c757d';  // abu-abu
    }
    return "<span style='background-color: {$color}; color: white; padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: bold; display: inline-block; min-width: 30px; text-align: center;'>{$nilai}</span>";
}

// Function untuk badge skor nutrisi
function getBadgeSkorNutrisi($skor) {
    $colors = [
        'Risiko Berat' => '#dc3545',      // merah
        'Risiko Sedang' => '#ffc107',     // kuning
        'Risiko Rendah' => '#28a745'      // hijau
    ];
    $color = isset($colors[$skor]) ? $colors[$skor] : '#6c757d';
    return "<span style='background-color: {$color}; color: white; padding: 5px 12px; border-radius: 3px; font-size: 13px; font-weight: bold; display: inline-block;'>{$skor}</span>";
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.tabel-nutrisi {
    width: 100%;
    border-collapse: collapse;
}
.tabel-nutrisi td {
    padding: 10px;
    vertical-align: top;
}
.tabel-nutrisi .col-waktu {
    width: 12%;
    font-weight: bold;
}
.tabel-nutrisi .col-ttv {
    width: 40%;
}
.tabel-nutrisi .col-skrining {
    width: 48%;
}
.card-nutrisi {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.ttv-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}
.ttv-item {
    display: flex;
    align-items: center;
    padding: 4px;
    background-color: #f8f9fa;
    border-radius: 3px;
}
.ttv-label {
    font-weight: bold;
    min-width: 70px;
    font-size: 12px;
}
.ttv-value {
    flex: 1;
    font-size: 13px;
}
.skrining-section {
    margin-bottom: 12px;
}
.skrining-title {
    font-weight: bold;
    font-size: 13px;
    color: #2c3e50;
    margin-bottom: 8px;
}
.skrining-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 8px;
    background-color: #f8f9fa;
    border-radius: 3px;
    margin-bottom: 6px;
}
.skrining-label {
    flex: 1;
    font-size: 12px;
}
.total-section {
    padding: 12px;
    background-color: #e7f3ff;
    border-left: 4px solid #2196F3;
    border-radius: 4px;
    margin-top: 12px;
}
.total-label {
    font-weight: bold;
    font-size: 13px;
    color: #2c3e50;
    display: block;
    margin-bottom: 8px;
}
.dietisien-section {
    margin-top: 12px;
    padding: 10px;
    background-color: #fff3cd;
    border-left: 4px solid #ffc107;
    border-radius: 4px;
}
.petugas-name {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #e9ecef;
    font-size: 12px;
    color: #6c757d;
}
</style>

<?php while ($row = mysqli_fetch_assoc($result)): ?>
<div class="card-nutrisi">
    <table class="tabel-nutrisi">
        <tr>
            <!-- Kolom 1: Waktu & Petugas -->
            <td class="col-waktu">
                <div><?= date('d/m/Y', strtotime($row['tanggal'])) ?></div>
                <div><?= date('H:i', strtotime($row['tanggal'])) ?></div>
                <div class="petugas-name"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
            </td>
            
            <!-- Kolom 2: Tanda-Tanda Vital & Alergi -->
            <td class="col-ttv">
                <div class="skrining-title">Tanda-Tanda Vital:</div>
                <div class="ttv-grid">
                    <div class="ttv-item">
                        <span class="ttv-label">TD:</span>
                        <span class="ttv-value"><?= htmlspecialchars($row['td']) ? htmlspecialchars($row['td']) . ' mmHg' : '-' ?></span>
                    </div>
                    <div class="ttv-item">
                        <span class="ttv-label">HR:</span>
                        <span class="ttv-value"><?= htmlspecialchars($row['hr']) ? htmlspecialchars($row['hr']) . ' x/menit' : '-' ?></span>
                    </div>
                    <div class="ttv-item">
                        <span class="ttv-label">RR:</span>
                        <span class="ttv-value"><?= htmlspecialchars($row['rr']) ? htmlspecialchars($row['rr']) . ' x/menit' : '-' ?></span>
                    </div>
                    <div class="ttv-item">
                        <span class="ttv-label">Suhu:</span>
                        <span class="ttv-value"><?= htmlspecialchars($row['suhu']) ? htmlspecialchars($row['suhu']) . ' °C' : '-' ?></span>
                    </div>
                    <div class="ttv-item">
                        <span class="ttv-label">BB:</span>
                        <span class="ttv-value"><?= htmlspecialchars($row['bb']) ? htmlspecialchars($row['bb']) . ' kg' : '-' ?></span>
                    </div>
                    <div class="ttv-item">
                        <span class="ttv-label">TB/PB:</span>
                        <span class="ttv-value"><?= htmlspecialchars($row['tbpb']) ? htmlspecialchars($row['tbpb']) . ' cm' : '-' ?></span>
                    </div>
                    <div class="ttv-item">
                        <span class="ttv-label">SpO2:</span>
                        <span class="ttv-value"><?= htmlspecialchars($row['spo2']) ? htmlspecialchars($row['spo2']) . ' %' : '-' ?></span>
                    </div>
                </div>
                
                <?php if (!empty($row['alergi'])): ?>
                <div style="margin-top: 10px;">
                    <div class="skrining-title">Alergi:</div>
                    <div style="padding: 6px 8px; background-color: #fff3cd; border-left: 3px solid #ffc107; border-radius: 3px; font-size: 13px;">
                        <?= nl2br(htmlspecialchars($row['alergi'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </td>
            
            <!-- Kolom 3: Skrining Gizi Anak (Strong Kids) -->
            <td class="col-skrining">
                <div class="skrining-title">Skrining Gizi Anak (Strong Kids):</div>
                
                <div class="skrining-section">
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>1. Apakah pasien tampak kurus?</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg1']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai1']) ?>
                    </div>
                    
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>2. Apakah terdapat penurunan BB selama 1 bulan?</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg2']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai2']) ?>
                    </div>
                    
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>3. Apakah ada salah satu kondisi berikut?</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg3']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai3']) ?>
                    </div>
                    
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>4. Apakah ada penyakit/keadaan yg berisiko malnutrisi?</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg4']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai4']) ?>
                    </div>
                </div>
                
                <div class="total-section">
                    <span class="total-label">Total Skor:</span>
                    <?= getBadgeTotalHasil($row['total_hasil']) ?>
                    
                    <div style="margin-top: 10px;">
                        <span style="font-weight: bold; font-size: 12px;">Kategori:</span>
                        <?= getBadgeSkorNutrisi($row['skor_nutrisi']) ?>
                    </div>
                </div>
                
                <!-- Diketahui Dietisien -->
                <?php if ($row['diketahui_dietisien'] == 'Ya'): ?>
                <div class="dietisien-section">
                    <div style="font-weight: bold; font-size: 12px; margin-bottom: 5px;">
                        ✓ Diketahui Dietisien
                    </div>
                    <?php if (!empty($row['keterangan_diketahui_dietisien'])): ?>
                    <div style="font-size: 12px; color: #856404;">
                        Keterangan: <?= htmlspecialchars($row['keterangan_diketahui_dietisien']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>