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

// Query untuk ambil data catatan cek GDS
$query = "
    SELECT 
        ccg.tgl_perawatan,
        ccg.jam_rawat,
        ccg.gdp,
        ccg.insulin,
        ccg.obat_gula,
        ccg.nip,
        p.nama AS nama_petugas
    FROM catatan_cek_gds ccg
    LEFT JOIN petugas p ON ccg.nip = p.nip
    WHERE ccg.no_rawat = '$no_rawat'
    ORDER BY ccg.tgl_perawatan DESC, ccg.jam_rawat DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data catatan cek GDS tidak ditemukan</div>';
    exit;
}

// Function untuk badge GDS
function getBadgeGDS($gdp) {
    $gdp_num = intval($gdp);
    if ($gdp_num < 70) {
        $color = '#dc3545';  // merah - hipoglikemia
        $status = 'Rendah';
    } elseif ($gdp_num >= 70 && $gdp_num <= 140) {
        $color = '#28a745';  // hijau - normal
        $status = 'Normal';
    } elseif ($gdp_num > 140 && $gdp_num <= 200) {
        $color = '#ffc107';  // kuning - pre-diabetes
        $status = 'Tinggi';
    } elseif ($gdp_num > 200) {
        $color = '#dc3545';  // merah - diabetes
        $status = 'Sangat Tinggi';
    } else {
        $color = '#6c757d';  // abu-abu
        $status = '';
    }
    
    $badge = "<span style='background-color: {$color}; color: white; padding: 4px 12px; border-radius: 4px; font-size: 14px; font-weight: bold; display: inline-block;'>{$gdp} mg/dL</span>";
    if ($status) {
        $badge .= " <span style='color: {$color}; font-weight: bold; margin-left: 5px;'>({$status})</span>";
    }
    return $badge;
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.tabel-gds {
    width: 100%;
    border-collapse: collapse;
}
.tabel-gds td {
    padding: 10px;
    vertical-align: top;
}
.tabel-gds .col-waktu {
    width: 15%;
    font-weight: bold;
}
.tabel-gds .col-data {
    width: 85%;
}
.card-gds {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.gds-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.gds-item {
    display: flex;
    align-items: center;
}
.gds-label {
    font-weight: bold;
    min-width: 150px;
}
.gds-value {
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
<div class="card-gds">
    <table class="tabel-gds">
        <tr>
            <!-- Kolom 1: Waktu & Petugas -->
            <td class="col-waktu">
                <div><?= date('d/m/Y', strtotime($row['tgl_perawatan'])) ?></div>
                <div><?= htmlspecialchars($row['jam_rawat']) ?></div>
                <div class="petugas-name"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
            </td>
            
            <!-- Kolom 2: Data GDS -->
            <td class="col-data">
                <div class="gds-info">
                    <div class="gds-item">
                        <span class="gds-label">Gula Darah Puasa (GDP):</span>
                        <span class="gds-value"><?= !empty($row['gdp']) ? getBadgeGDS($row['gdp']) : '-' ?></span>
                    </div>
                    
                    <?php if (!empty($row['insulin'])): ?>
                    <div class="gds-item">
                        <span class="gds-label">Insulin:</span>
                        <span class="gds-value"><?= htmlspecialchars($row['insulin']) ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($row['obat_gula'])): ?>
                    <div class="gds-item">
                        <span class="gds-label">Obat Gula:</span>
                        <span class="gds-value"><?= htmlspecialchars($row['obat_gula']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>