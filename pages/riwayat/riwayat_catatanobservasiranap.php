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

// Query untuk ambil data catatan observasi ranap
$query = "
    SELECT 
        cor.tgl_perawatan,
        cor.jam_rawat,
        cor.gcs,
        cor.td,
        cor.hr,
        cor.rr,
        cor.suhu,
        cor.spo2,
        cor.nip,
        p.nama AS nama_petugas
    FROM catatan_observasi_ranap cor
    LEFT JOIN petugas p ON cor.nip = p.nip
    WHERE cor.no_rawat = '$no_rawat'
    ORDER BY cor.tgl_perawatan DESC, cor.jam_rawat DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data catatan observasi rawat inap tidak ditemukan</div>';
    exit;
}

// Function untuk badge GCS
function getBadgeGCS($gcs) {
    $gcs_num = intval($gcs);
    if ($gcs_num >= 14) {
        $color = '#28a745';  // hijau - normal
    } elseif ($gcs_num >= 9) {
        $color = '#ffc107';  // kuning - moderate
    } elseif ($gcs_num >= 4) {
        $color = '#dc3545';  // merah - severe
    } else {
        $color = '#6c757d';  // abu-abu
    }
    return "<span style='background-color: {$color}; color: white; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: bold; display: inline-block;'>{$gcs}</span>";
}

// Function untuk badge SpO2
function getBadgeSpO2($spo2) {
    $spo2_num = intval($spo2);
    if ($spo2_num >= 95) {
        $color = '#28a745';  // hijau - normal
    } elseif ($spo2_num >= 90) {
        $color = '#ffc107';  // kuning - moderate
    } elseif ($spo2_num > 0) {
        $color = '#dc3545';  // merah - severe
    } else {
        $color = '#6c757d';  // abu-abu
    }
    return "<span style='background-color: {$color}; color: white; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: bold; display: inline-block;'>{$spo2}%</span>";
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.tabel-observasi {
    width: 100%;
    border-collapse: collapse;
}
.tabel-observasi td {
    padding: 10px;
    vertical-align: top;
}
.tabel-observasi .col-waktu {
    width: 15%;
    font-weight: bold;
}
.tabel-observasi .col-ttv {
    width: 85%;
}
.card-observasi {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.card-observasi .ttv-grid {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 10px !important;
    margin-bottom: 0 !important;
}
.card-observasi .ttv-item {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
}
.card-observasi .ttv-item label,
.card-observasi .ttv-label {
    font-weight: bold !important;
    min-width: 80px !important;
    font-size: 13px !important;
    color: #333 !important;
    margin-bottom: 0 !important;
}
.card-observasi .ttv-value {
    flex: 1;
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
<div class="card-observasi">
    <table class="tabel-observasi">
        <tr>
            <!-- Kolom 1: Waktu & Petugas -->
            <td class="col-waktu">
                <div><?= date('d/m/Y', strtotime($row['tgl_perawatan'])) ?></div>
                <div><?= htmlspecialchars($row['jam_rawat']) ?></div>
                <div class="petugas-name"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
            </td>
            
            <!-- Kolom 2: Tanda-Tanda Vital -->
            <td class="col-ttv">
                <div class="ttv-grid">
                    <div class="ttv-item">
                        <span class="ttv-label">GCS:</span>
                        <span class="ttv-value"><?= !empty($row['gcs']) ? getBadgeGCS($row['gcs']) : '-' ?></span>
                    </div>
                    
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
                        <span class="ttv-label">SpO2:</span>
                        <span class="ttv-value"><?= !empty($row['spo2']) ? getBadgeSpO2($row['spo2']) : '-' ?></span>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>