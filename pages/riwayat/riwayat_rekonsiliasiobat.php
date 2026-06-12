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

// Query untuk ambil data rekonsiliasi obat
$query = "
    SELECT 
        ro.no_rekonsiliasi,
        ro.no_rawat,
        ro.tanggal_wawancara,
        ro.rekonsiliasi_obat_saat,
        ro.alergi_obat,
        ro.manifestasi_alergi,
        ro.dampak_alergi,
        ro.nip,
        p.nama AS nama_petugas
    FROM rekonsiliasi_obat ro
    LEFT JOIN petugas p ON ro.nip = p.nip
    WHERE ro.no_rawat = '$no_rawat'
    ORDER BY ro.tanggal_wawancara DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data rekonsiliasi obat tidak ditemukan</div>';
    exit;
}

// Function untuk badge dampak alergi
function getBadgeDampakAlergi($dampak) {
    $colors = [
        '-' => '#6c757d',          // abu-abu
        'Ringan' => '#28a745',     // hijau
        'Sedang' => '#ffc107',     // kuning
        'Berat' => '#dc3545'       // merah
    ];
    $color = isset($colors[$dampak]) ? $colors[$dampak] : '#6c757d';
    return "<span style='background-color: {$color}; color: white; padding: 4px 10px; border-radius: 3px; font-size: 12px; font-weight: bold; display: inline-block;'>{$dampak}</span>";
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.card-rekonsiliasi {
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.header-rekonsiliasi {
    background-color: #f8f9fa;
    padding: 12px 15px;
    border-bottom: 2px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header-left {
    flex: 1;
}
.header-title {
    font-weight: bold;
    font-size: 15px;
    color: #2c3e50;
}
.header-subtitle {
    font-size: 12px;
    color: #6c757d;
    margin-top: 2px;
}
.content-rekonsiliasi {
    padding: 15px;
}
.section-title {
    font-weight: bold;
    font-size: 14px;
    color: #2c3e50;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 2px solid #e9ecef;
}
.info-item {
    display: flex;
    margin-bottom: 8px;
}
.info-label {
    font-weight: bold;
    min-width: 180px;
    color: #495057;
    font-size: 13px;
}
.info-value {
    flex: 1;
    font-size: 13px;
}
.obat-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 13px;
}
.obat-table th {
    background-color: #e9ecef;
    padding: 8px;
    text-align: left;
    border: 1px solid #dee2e6;
    font-weight: bold;
    font-size: 12px;
}
.obat-table td {
    padding: 8px;
    border: 1px solid #dee2e6;
}
.obat-table tr:hover {
    background-color: #f8f9fa;
}
.konfirmasi-section {
    margin-top: 15px;
    padding: 12px;
    background-color: #e7f3ff;
    border-left: 4px solid #2196F3;
    border-radius: 4px;
}
.konfirmasi-title {
    font-weight: bold;
    font-size: 13px;
    color: #2c3e50;
    margin-bottom: 8px;
}
.konfirmasi-item {
    display: flex;
    margin-bottom: 6px;
}
.konfirmasi-label {
    font-weight: bold;
    min-width: 150px;
    font-size: 12px;
}
.konfirmasi-value {
    flex: 1;
    font-size: 12px;
}
</style>

<?php while ($row = mysqli_fetch_assoc($result)): 
    $no_rekonsiliasi = $row['no_rekonsiliasi'];
    
    // Query detail obat
    $query_detail = "
        SELECT 
            nama_obat,
            dosis_obat,
            frekuensi,
            cara_pemberian,
            waktu_pemberian_terakhir,
            tindak_lanjut,
            perubahan_aturan_pakai
        FROM rekonsiliasi_obat_detail_obat
        WHERE no_rekonsiliasi = '$no_rekonsiliasi'
        ORDER BY nama_obat
    ";
    $result_detail = bukaquery($query_detail);
    
    // Query konfirmasi
    $query_konfirmasi = "
        SELECT 
            diterima_farmasi,
            dikonfirmasi_apoteker,
            nip,
            diserahkan_pasien
        FROM rekonsiliasi_obat_konfirmasi
        WHERE no_rekonsiliasi = '$no_rekonsiliasi'
        LIMIT 1
    ";
    $result_konfirmasi = bukaquery($query_konfirmasi);
    $konfirmasi = mysqli_fetch_assoc($result_konfirmasi);
?>
<div class="card-rekonsiliasi">
    <div class="header-rekonsiliasi">
        <div class="header-left">
            <div class="header-title">YANG MELAKUKAN WAWANCARA</div>
            <div class="header-subtitle">
                Petugas Wawancara: <?= htmlspecialchars($row['nip']) ?> <?= htmlspecialchars($row['nama_petugas'] ?: '-') ?>
            </div>
        </div>
    </div>
    
    <div class="content-rekonsiliasi">
        <!-- Info Rekonsiliasi -->
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 4px; margin-bottom: 15px;">
            <div class="info-item" style="display: block;">
                <span class="info-label">No.Rekonsiliasi:</span>
                <span class="info-value"><?= htmlspecialchars($row['no_rekonsiliasi']) ?></span>
            </div>
            <div class="info-item" style="display: block;">
                <span class="info-label">Tgl.Wawancara:</span>
                <span class="info-value"><?= date('Y-m-d H:i:s', strtotime($row['tanggal_wawancara'])) ?></span>
            </div>
            <div class="info-item" style="display: block;">
                <span class="info-label" style="background-color: #fff59d; padding: 2px 6px; border-radius: 3px;">Rekonsiliasi Saat:</span>
                <span class="info-value"><?= htmlspecialchars($row['rekonsiliasi_obat_saat']) ?></span>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; padding: 10px; background-color: #f8f9fa; border-radius: 4px; margin-bottom: 15px;">
            <div class="info-item" style="display: block;">
                <span class="info-label">Alergi Obat:</span>
                <span class="info-value"><?= htmlspecialchars($row['alergi_obat']) ?: '-' ?></span>
            </div>
            <div class="info-item" style="display: block;">
                <span class="info-label">Manifestasi Alergi:</span>
                <span class="info-value"><?= htmlspecialchars($row['manifestasi_alergi']) ?: '-' ?></span>
            </div>
            <div class="info-item" style="display: block;">
                <span class="info-label">Dampak Alergi:</span>
                <span class="info-value"><?= getBadgeDampakAlergi($row['dampak_alergi']) ?></span>
            </div>
        </div>
        
        <!-- Detail Obat -->
        <?php if (mysqli_num_rows($result_detail) > 0): ?>
        <div class="section-title" style="margin-top: 20px;">Detail Obat yang Digunakan</div>
        <table class="obat-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 20%;">Nama Obat</th>
                    <th style="width: 10%;">Dosis</th>
                    <th style="width: 10%;">Frekuensi</th>
                    <th style="width: 15%;">Cara Pemberian</th>
                    <th style="width: 15%;">Waktu Pemberian Terakhir</th>
                    <th style="width: 10%;">Tindak Lanjut</th>
                    <th style="width: 15%;">Perubahan Aturan Pakai</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                while ($detail = mysqli_fetch_assoc($result_detail)): 
                ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($detail['nama_obat']) ?></td>
                    <td><?= htmlspecialchars($detail['dosis_obat']) ?></td>
                    <td><?= htmlspecialchars($detail['frekuensi']) ?></td>
                    <td><?= htmlspecialchars($detail['cara_pemberian']) ?></td>
                    <td><?= htmlspecialchars($detail['waktu_pemberian_terakhir']) ?></td>
                    <td style="text-align: center;"><?= htmlspecialchars($detail['tindak_lanjut']) ?></td>
                    <td><?= htmlspecialchars($detail['perubahan_aturan_pakai']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="padding: 10px; background-color: #f8f9fa; border-radius: 4px; font-size: 13px; color: #6c757d; margin-top: 10px;">
            Tidak ada detail obat yang tercatat
        </div>
        <?php endif; ?>
        
        <!-- Konfirmasi -->
        <?php if ($konfirmasi): ?>
        <div class="konfirmasi-section">
            <div class="konfirmasi-title">Konfirmasi Rekonsiliasi</div>
            <div class="konfirmasi-item">
                <span class="konfirmasi-label">Diterima Farmasi:</span>
                <span class="konfirmasi-value"><?= date('d/m/Y H:i', strtotime($konfirmasi['diterima_farmasi'])) ?></span>
            </div>
            <div class="konfirmasi-item">
                <span class="konfirmasi-label">Dikonfirmasi Apoteker:</span>
                <span class="konfirmasi-value"><?= date('d/m/Y H:i', strtotime($konfirmasi['dikonfirmasi_apoteker'])) ?></span>
            </div>
            <div class="konfirmasi-item">
                <span class="konfirmasi-label">Diserahkan ke Pasien:</span>
                <span class="konfirmasi-value"><?= date('d/m/Y H:i', strtotime($konfirmasi['diserahkan_pasien'])) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>