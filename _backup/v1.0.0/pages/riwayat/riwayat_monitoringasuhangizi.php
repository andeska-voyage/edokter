<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm    = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

$query = "
    SELECT 
        mag.tanggal,
        mag.monitoring,
        mag.evaluasi,
        mag.nip,
        p.nama AS nama_petugas
    FROM monitoring_asuhan_gizi mag
    LEFT JOIN petugas p ON mag.nip = p.nip
    WHERE mag.no_rawat = '$no_rawat'
    ORDER BY mag.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data monitoring asuhan gizi tidak ditemukan</div>';
    exit;
}
?>

<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.tabel-mag {
    width: 100%;
    border-collapse: collapse;
}
.tabel-mag td {
    padding: 10px;
    vertical-align: top;
}
.tabel-mag .col-waktu {
    width: 12%;
    font-weight: bold;
    white-space: nowrap;
}
.tabel-mag .col-isi {
    width: 88%;
}
.card-mag {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.petugas-name {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #e9ecef;
    font-size: 12px;
    color: #6c757d;
    font-weight: normal;
}

/* Section title */
.mag-section {
    font-size: 11px;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 2px;
    margin: 8px 0 4px 0;
}
.mag-section:first-child { margin-top: 0; }

/* Teks konten */
.mag-text {
    font-size: 12px;
    color: #334155;
    line-height: 1.5;
    padding: 4px 8px;
    background: #f8fafc;
    border-left: 3px solid #dee2e6;
    border-radius: 3px;
    white-space: pre-wrap;
    word-break: break-word;
}
.mag-text.blue   { border-left-color: #3b82f6; }
.mag-text.green  { border-left-color: #10b981; }
</style>

<?php while ($row = mysqli_fetch_assoc($result)): ?>
<div class="card-mag">
    <table class="tabel-mag">
        <tr>
            <!-- Kolom Kiri: Waktu & Petugas -->
            <td class="col-waktu">
                <div><?= date('d/m/Y', strtotime($row['tanggal'])) ?></div>
                <div><?= date('H:i', strtotime($row['tanggal'])) ?></div>
                <div class="petugas-name"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
            </td>

            <!-- Kolom Kanan: Monitoring & Evaluasi -->
            <td class="col-isi">

                <?php if (!empty($row['monitoring'])): ?>
                <div class="mag-section">Monitoring</div>
                <div class="mag-text blue"><?= nl2br(htmlspecialchars($row['monitoring'])) ?></div>
                <?php endif; ?>

                <?php if (!empty($row['evaluasi'])): ?>
                <div class="mag-section">Evaluasi</div>
                <div class="mag-text green"><?= nl2br(htmlspecialchars($row['evaluasi'])) ?></div>
                <?php endif; ?>

            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>
