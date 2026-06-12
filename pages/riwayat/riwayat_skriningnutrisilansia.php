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

// Query untuk ambil data skrining nutrisi lansia
$query = "
    SELECT 
        snl.tanggal,
        snl.td,
        snl.hr,
        snl.rr,
        snl.suhu,
        snl.bb,
        snl.tbpb,
        snl.spo2,
        snl.alergi,
        snl.sg1,
        snl.nilai1,
        snl.sg2,
        snl.nilai2,
        snl.sg3,
        snl.nilai3,
        snl.sg4,
        snl.nilai4,
        snl.sg5,
        snl.nilai5,
        snl.sg6,
        snl.nilai6,
        snl.total_hasil,
        snl.skor_nutrisi,
        snl.nip,
        p.nama AS nama_petugas
    FROM skrining_nutrisi_lansia snl
    LEFT JOIN petugas p ON snl.nip = p.nip
    WHERE snl.no_rawat = '$no_rawat'
    ORDER BY snl.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data skrining nutrisi lansia tidak ditemukan</div>';
    exit;
}

// Function untuk badge total hasil
function getBadgeTotalHasil($total) {
    $total_num = intval($total);
    if ($total_num >= 12) {
        $color = '#28a745';  // hijau - status gizi normal
        $status = 'Status Gizi Normal';
    } elseif ($total_num >= 8 && $total_num <= 11) {
        $color = '#ffc107';  // kuning - beresiko malnutrisi
        $status = 'Beresiko Malnutrisi';
    } elseif ($total_num >= 0 && $total_num <= 7) {
        $color = '#dc3545';  // merah - malnutrisi
        $status = 'Malnutrisi';
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
        $color = '#dc3545';  // merah
    } elseif ($nilai_num == 1) {
        $color = '#ffc107';  // kuning
    } elseif ($nilai_num >= 2) {
        $color = '#28a745';  // hijau
    } else {
        $color = '#6c757d';  // abu-abu
    }
    return "<span style='background-color: {$color}; color: white; padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: bold; display: inline-block; min-width: 30px; text-align: center;'>{$nilai}</span>";
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
    width: 35%;
}
.tabel-nutrisi .col-skrining {
    width: 53%;
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
            
            <!-- Kolom 3: Skrining Gizi Lansia (MNA) -->
            <td class="col-skrining">
                <div class="skrining-title">Skrining Gizi Lansia (Mini Nutritional Assessment):</div>
                
                <div class="skrining-section">
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>1. Asupan Makan</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg1']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai1']) ?>
                    </div>
                    
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>2. Penurunan Berat Badan</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg2']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai2']) ?>
                    </div>
                    
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>3. Mobilitas</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg3']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai3']) ?>
                    </div>
                    
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>4. Stres Psikologis/Penyakit Akut</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg4']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai4']) ?>
                    </div>
                    
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>5. Masalah Neuropsikologi</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg5']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai5']) ?>
                    </div>
                    
                    <div class="skrining-item">
                        <span class="skrining-label">
                            <strong>6. Indeks Massa Tubuh (IMT)</strong><br>
                            <span style="font-size: 11px; color: #666;"><?= htmlspecialchars($row['sg6']) ?></span>
                        </span>
                        <?= getBadgeNilai($row['nilai6']) ?>
                    </div>
                </div>
                
                <div class="total-section">
                    <span class="total-label">Total Skor MNA:</span>
                    <?= getBadgeTotalHasil($row['total_hasil']) ?>
                    
                    <?php if (!empty($row['skor_nutrisi'])): ?>
                    <div style="margin-top: 10px; font-size: 13px; color: #495057;">
                        <strong>Kategori:</strong> <?= htmlspecialchars($row['skor_nutrisi']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>