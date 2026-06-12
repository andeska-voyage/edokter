<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

$no_rawat = isset($_GET['id']) ? trim($_GET['id']) : '';
$no_rm = isset($_GET['no_rm']) ? trim($_GET['no_rm']) : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">No. Rawat tidak ditemukan</div>';
    exit;
}

// Query detail registrasi
$query = "SELECT 
    r.no_rawat,
    r.no_rkm_medis,
    DATE_FORMAT(r.tgl_registrasi, '%d-%m-%Y') as tgl_reg,
    r.jam_reg,
    d.nm_dokter,
    p.nm_poli,
    r.status_lanjut,
    r.umurdaftar,
    r.sttsumur,
    ps.nm_pasien
FROM reg_periksa r
LEFT JOIN pasien ps ON r.no_rkm_medis = ps.no_rkm_medis
LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
LEFT JOIN poliklinik p ON r.kd_poli = p.kd_poli
WHERE r.no_rawat = '$no_rawat'
LIMIT 1";

$result = bukaquery($query);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo '<div class="alert alert-danger m-3">Data registrasi tidak ditemukan</div>';
    exit;
}
?>

<style>
.detail-registrasi-wrapper {
    padding: 20px 30px;
    background: #ffffff;
}

.registrasi-title {
    font-size: 15px;
    font-weight: 700;
    color: #dc3545;
    margin-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Info Table - Borderless & Clean */
.info-table-clean {
    width: 100%;
    font-size: 14px;
}

.info-table-clean tr {
    border-bottom: 1px solid #f1f5f9;
}

.info-table-clean tr:last-child {
    border-bottom: none;
}

.info-table-clean td {
    padding: 4px 0;
    vertical-align: middle;
    text-align: left;
}

.info-table-clean td:first-child {
    width: 150px;
    text-align: left;
    font-weight: 600;
    color: #64748b;
}

.info-table-clean td:nth-child(2) {
    color: #1e293b;
}

.badge-status-custom {
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 12px;
    font-weight: 600;
}

.bg-ralan {
    background: #28a745;
    color: white;
}

.bg-ranap {
    background: #ffc107;
    color: #2d3748;
}
</style>

<div class="detail-registrasi-wrapper">
    <h6 class="registrasi-title">
        Informasi Registrasi
    </h6>
    
    <div class="row">
        <!-- Kolom 1 -->
        <div class="col-md-4">
            <table class="info-table-clean">
                <tr>
                    <td>No. Rawat</td>
                    <td>: <strong><?= htmlspecialchars($data['no_rawat']) ?></strong></td>
                </tr>
                <tr>
                    <td>No. RM</td>
                    <td>: <?= htmlspecialchars($data['no_rkm_medis']) ?></td>
                </tr>
                <tr>
                    <td>Nama Pasien</td>
                    <td>: <?= htmlspecialchars($data['nm_pasien']) ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Kolom 2 -->
        <div class="col-md-4">
            <table class="info-table-clean">
                <tr>
                    <td>Tanggal Registrasi</td>
                    <td>: <?= htmlspecialchars($data['tgl_reg']) ?></td>
                </tr>
                <tr>
                    <td>Jam Registrasi</td>
                    <td>: <?= htmlspecialchars($data['jam_reg']) ?> WIB</td>
                </tr>
                <tr>
                    <td>Dokter</td>
                    <td>: <?= htmlspecialchars($data['nm_dokter'] ?: '-') ?></td>
                </tr>
            </table>
        </div>
        
        <!-- Kolom 3 -->
        <div class="col-md-4">
            <table class="info-table-clean">
                <tr>
                    <td>Poliklinik</td>
                    <td>: <?= htmlspecialchars($data['nm_poli'] ?: '-') ?></td>
                </tr>
                <tr>
                    <td>Status Lanjut</td>
                    <td>: <span class="badge badge-status-custom <?= $data['status_lanjut'] == 'Ranap' ? 'bg-ranap' : 'bg-ralan' ?>">
                        <?= htmlspecialchars($data['status_lanjut']) ?>
                    </span></td>
                </tr>
                <tr>
                    <td>Umur Saat Daftar</td>
                    <td>: <?= htmlspecialchars($data['umurdaftar']) ?> <?= htmlspecialchars($data['sttsumur']) ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>