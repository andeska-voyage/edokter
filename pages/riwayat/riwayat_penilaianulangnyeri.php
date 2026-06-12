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

// Query untuk ambil data penilaian ulang nyeri
$query = "
    SELECT 
        pun.tanggal,
        pun.nyeri,
        pun.provokes,
        pun.ket_provokes,
        pun.quality,
        pun.ket_quality,
        pun.lokasi,
        pun.menyebar,
        pun.skala_nyeri,
        pun.durasi,
        pun.nyeri_hilang,
        pun.ket_nyeri,
        pun.nip,
        p.nama AS nama_petugas
    FROM penilaian_ulang_nyeri pun
    LEFT JOIN petugas p ON pun.nip = p.nip
    WHERE pun.no_rawat = '$no_rawat'
    ORDER BY pun.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian ulang nyeri tidak ditemukan</div>';
    exit;
}

// Function untuk badge Tingkat Nyeri
function getBadgeNyeri($nyeri) {
    $colors = [
        'Tidak Ada Nyeri' => '#28a745',  // hijau
        'Nyeri Akut' => '#ffc107',       // kuning
        'Nyeri Kronis' => '#dc3545'      // merah
    ];
    $color = isset($colors[$nyeri]) ? $colors[$nyeri] : '#6c757d';
    return "<span style='background-color: {$color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; display: inline-block; white-space: nowrap;'>{$nyeri}</span>";
}

// Function untuk badge Provokes
function getBadgeProvokes($provokes) {
    $colors = [
        'Proses Penyakit' => '#dc3545',  // merah
        'Benturan' => '#ffc107',         // kuning
        'Lain-lain' => '#17a2b8',        // biru
        '-' => '#6c757d'                 // abu-abu
    ];
    $color = isset($colors[$provokes]) ? $colors[$provokes] : '#6c757d';
    return "<span style='background-color: {$color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; display: inline-block; white-space: nowrap;'>{$provokes}</span>";
}

// Function untuk badge Quality
function getBadgeQuality($quality) {
    $colors = [
        'Seperti Tertusuk' => '#dc3545',  // merah
        'Berdenyut' => '#ffc107',         // kuning
        'Teriris' => '#dc3545',           // merah
        'Tertindih' => '#ffc107',         // kuning
        'Tertiban' => '#17a2b8',          // biru
        'Lain-lain' => '#17a2b8',         // biru
        '-' => '#6c757d'                  // abu-abu
    ];
    $color = isset($colors[$quality]) ? $colors[$quality] : '#6c757d';
    return "<span style='background-color: {$color}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; display: inline-block; white-space: nowrap;'>{$quality}</span>";
}

// Function untuk badge Skala Nyeri
function getBadgeSkalaNyeri($skala) {
    if ($skala == '0') {
        $color = '#28a745';  // hijau
    } elseif ($skala >= 1 && $skala <= 3) {
        $color = '#17a2b8';  // biru
    } elseif ($skala >= 4 && $skala <= 6) {
        $color = '#ffc107';  // kuning
    } elseif ($skala >= 7 && $skala <= 10) {
        $color = '#dc3545';  // merah
    } else {
        $color = '#6c757d';  // abu-abu
    }
    return "<span style='background-color: {$color}; color: white; padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: bold; display: inline-block;'>{$skala}</span>";
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.tabel-nyeri {
    width: 100%;
    border-collapse: collapse;
}
.tabel-nyeri td {
    padding: 10px;
    vertical-align: top;
}
.tabel-nyeri .col-tanggal {
    width: 15%;
    font-weight: bold;
}
.tabel-nyeri .col-konten {
    width: 42.5%;
}
.tabel-nyeri .col-wilayah {
    width: 42.5%;
}
.card-nyeri {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.info-line {
    display: flex;
    margin-bottom: 5px;
    align-items: flex-start;
}
.info-label {
    display: inline-block;
    min-width: 130px;
    font-weight: bold;
    flex-shrink: 0;
}
.info-label-indent {
    display: inline-block;
    min-width: 130px;
    font-weight: bold;
    padding-left: 20px;
    flex-shrink: 0;
}
.info-value {
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
<div class="card-nyeri">
    <table class="tabel-nyeri">
        <tr>
            <!-- Kolom 1: Tanggal & Petugas -->
            <td class="col-tanggal">
                <div><?= date('Y-m-d', strtotime($row['tanggal'])) ?></div>
                <div><?= date('H:i:s', strtotime($row['tanggal'])) ?></div>
                <div class="petugas-name"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
            </td>
            
            <!-- Kolom 2: Data Assessment -->
            <td class="col-konten">
                <div class="info-line">
                    <span class="info-label">Tingkat Nyeri :</span>
                    <span class="info-value"><?= getBadgeNyeri($row['nyeri'] ?: '-') ?></span>
                </div>
                
                <?php if (!empty($row['provokes'])): ?>
                <div class="info-line">
                    <span class="info-label">Penyebab :</span>
                    <span class="info-value"><?= getBadgeProvokes($row['provokes']) ?>
                    <?php if (!empty($row['ket_provokes'])): ?>
                        <?= htmlspecialchars($row['ket_provokes']) ?>
                    <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($row['quality'])): ?>
                <div class="info-line">
                    <span class="info-label">Kualitas :</span>
                    <span class="info-value"><?= getBadgeQuality($row['quality']) ?>
                    <?php if (!empty($row['ket_quality'])): ?>
                        <?= htmlspecialchars($row['ket_quality']) ?>
                    <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($row['skala_nyeri'])): ?>
                <div class="info-line">
                    <span class="info-label">Severity :</span>
                    <span class="info-value">Skala Nyeri <?= getBadgeSkalaNyeri($row['skala_nyeri']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($row['durasi'])): ?>
                <div class="info-line">
                    <span class="info-label">Durasi :</span>
                    <span class="info-value"><?= htmlspecialchars($row['durasi']) ?> Jam</span>
                </div>
                <?php endif; ?>
            </td>
            
            <!-- Kolom 3: Wilayah & Nyeri Hilang -->
            <td class="col-wilayah">
                <div class="info-line">
                    <span class="info-label">Wilayah :</span>
                </div>
                
                <?php if (!empty($row['lokasi'])): ?>
                <div class="info-line">
                    <span class="info-label-indent">Lokasi :</span>
                    <span class="info-value"><?= htmlspecialchars($row['lokasi']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($row['menyebar'])): ?>
                <div class="info-line">
                    <span class="info-label">Menyebar :</span>
                    <span class="info-value"><?= htmlspecialchars($row['menyebar']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($row['nyeri_hilang'])): ?>
                <div class="info-line">
                    <span class="info-label">Nyeri Hilang Bila :</span>
                    <span class="info-value"><?= htmlspecialchars($row['nyeri_hilang']) ?>
                    <?php if (!empty($row['ket_nyeri'])): ?>
                        , <?= htmlspecialchars($row['ket_nyeri']) ?>
                    <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>