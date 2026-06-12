<?php
include "../../conf/conf.php";

// Parameter
$no_rawat = $_REQUEST['id'];
$no_rm = $_REQUEST['no_rm'];

// Query catatan keperawatan
$query_catatan = "
    SELECT 
        c.no_rawat,
        c.tanggal,
        c.jam,
        c.uraian,
        c.nip,
        p.nama as nm_petugas
    FROM catatan_keperawatan_ralan c
    LEFT JOIN petugas p ON c.nip = p.nip
    WHERE c.no_rawat = '$no_rawat'
    ORDER BY c.tanggal DESC, c.jam DESC
";

$result_catatan = bukaquery($query_catatan);

if (mysqli_num_rows($result_catatan) == 0) {
    echo '<div class="alert alert-warning m-3">Data catatan keperawatan tidak ditemukan</div>';
    exit;
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<?php
// Array bulan Indonesia
$bulan = array(
    1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
    'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
);

// Loop data
while ($data = mysqli_fetch_assoc($result_catatan)):
    // Format tanggal Indonesia
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d', $tanggal_obj) . ' ' . 
                     $bulan[date('n', $tanggal_obj)] . ' ' . 
                     date('Y', $tanggal_obj);
?>

<div class="card mb-3 shadow-sm">
    <div class="card-body">
        <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Tanggal/Jam:</span>
                <span class="info-value"><?= $tanggal_format ?> <?= htmlspecialchars($data['jam']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Uraian:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['uraian'])) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Petugas:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_petugas']) ?: '-' ?></span>
            </div>
        </div>
    </div>
</div>

<?php endwhile; ?>