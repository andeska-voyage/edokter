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

// Query untuk ambil data pemantauan PEWS anak
$query = "
    SELECT 
        ppa.tanggal,
        ppa.parameter_perilaku,
        ppa.skor_perilaku,
        ppa.parameter_crt_atau_warna_kulit,
        ppa.skor_crt_atau_warna_kulit,
        ppa.parameter_perespirasi,
        ppa.skor_perespirasi,
        ppa.skor_total,
        ppa.parameter_total,
        ppa.nip,
        p.nama AS nama_petugas
    FROM pemantauan_pews_anak ppa
    LEFT JOIN petugas p ON ppa.nip = p.nip
    WHERE ppa.no_rawat = '$no_rawat'
    ORDER BY ppa.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data pemantauan PEWS anak tidak ditemukan</div>';
    exit;
}

// Function untuk badge skor individual
function getBadgeSkor($skor) {
    $skor_num = intval($skor);
    if ($skor_num == 0) {
        $color = '#28a745';  // hijau
    } elseif ($skor_num == 1) {
        $color = '#ffc107';  // kuning
    } elseif ($skor_num == 2) {
        $color = '#fd7e14';  // orange
    } elseif ($skor_num == 3) {
        $color = '#dc3545';  // merah
    } elseif ($skor_num == 4) {
        $color = '#6f42c1';  // ungu
    } else {
        $color = '#6c757d';  // abu-abu
    }
    return "<span style='background-color: {$color}; color: white; padding: 3px 10px; border-radius: 3px; font-size: 12px; font-weight: bold; display: inline-block; min-width: 30px; text-align: center;'>{$skor}</span>";
}

// Function untuk badge total skor
function getBadgeTotalSkor($total, $parameter) {
    $total_num = intval($total);
    if ($total_num == 0) {
        $color = '#28a745';  // hijau - normal
        $status = 'Normal';
    } elseif ($total_num >= 1 && $total_num <= 4) {
        $color = '#ffc107';  // kuning - peningkatan pemantauan
        $status = 'Tingkatkan Pemantauan';
    } elseif ($total_num >= 5 && $total_num <= 6) {
        $color = '#fd7e14';  // orange - peningkatan mendesak
        $status = 'Peningkatan Mendesak';
    } elseif ($total_num >= 7) {
        $color = '#dc3545';  // merah - emergency
        $status = 'Emergency';
    } else {
        $color = '#6c757d';  // abu-abu
        $status = '';
    }
    
    $badge = "<span style='background-color: {$color}; color: white; padding: 6px 16px; border-radius: 4px; font-size: 18px; font-weight: bold; display: inline-block;'>{$total}</span>";
    if ($status) {
        $badge .= " <span style='color: {$color}; font-weight: bold; margin-left: 8px; font-size: 14px;'>({$status})</span>";
    }
    if (!empty($parameter)) {
        $badge .= "<div style='margin-top: 8px; font-size: 13px; color: #495057;'>" . nl2br(htmlspecialchars($parameter)) . "</div>";
    }
    return $badge;
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.tabel-pews {
    width: 100%;
    border-collapse: collapse;
}
.tabel-pews td {
    padding: 10px;
    vertical-align: top;
}
.tabel-pews .col-waktu {
    width: 12%;
    font-weight: bold;
}
.tabel-pews .col-parameter {
    width: 58%;
}
.tabel-pews .col-total {
    width: 30%;
}
.card-pews {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.parameter-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}
.parameter-item {
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #dee2e6;
}
.parameter-label {
    font-weight: bold;
    font-size: 13px;
    color: #495057;
    display: block;
    margin-bottom: 6px;
}
.parameter-detail {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
}
.parameter-value {
    font-size: 13px;
    color: #212529;
    flex: 1;
}
.total-section {
    padding: 15px;
    background-color: #e7f3ff;
    border-left: 4px solid #2196F3;
    border-radius: 4px;
}
.total-label {
    font-weight: bold;
    font-size: 14px;
    color: #2c3e50;
    display: block;
    margin-bottom: 10px;
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
<div class="card-pews">
    <table class="tabel-pews">
        <tr>
            <!-- Kolom 1: Waktu & Petugas -->
            <td class="col-waktu">
                <div><?= date('d/m/Y', strtotime($row['tanggal'])) ?></div>
                <div><?= date('H:i', strtotime($row['tanggal'])) ?></div>
                <div class="petugas-name"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
            </td>
            
            <!-- Kolom 2: Parameter PEWS Anak -->
            <td class="col-parameter">
                <div class="parameter-grid">
                    <div class="parameter-item">
                        <span class="parameter-label">Perilaku</span>
                        <div class="parameter-detail">
                            <span class="parameter-value"><?= htmlspecialchars($row['parameter_perilaku']) ?></span>
                            <?= getBadgeSkor($row['skor_perilaku']) ?>
                        </div>
                    </div>
                    
                    <div class="parameter-item">
                        <span class="parameter-label">CRT atau Warna Kulit</span>
                        <div class="parameter-detail">
                            <span class="parameter-value"><?= htmlspecialchars($row['parameter_crt_atau_warna_kulit']) ?></span>
                            <?= getBadgeSkor($row['skor_crt_atau_warna_kulit']) ?>
                        </div>
                    </div>
                    
                    <div class="parameter-item">
                        <span class="parameter-label">Respirasi</span>
                        <div class="parameter-detail">
                            <span class="parameter-value"><?= htmlspecialchars($row['parameter_perespirasi']) ?></span>
                            <?= getBadgeSkor($row['skor_perespirasi']) ?>
                        </div>
                    </div>
                </div>
            </td>
            
            <!-- Kolom 3: Total Skor -->
            <td class="col-total">
                <div class="total-section">
                    <span class="total-label">Total Skor PEWS:</span>
                    <?= getBadgeTotalSkor($row['skor_total'], $row['parameter_total']) ?>
                </div>
            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>