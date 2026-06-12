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

// Query untuk ambil data penilaian resiko jatuh lansia
$query = "
    SELECT 
        prjl.tanggal,
        prjl.penilaian_jatuhmorse_skala1,
        prjl.penilaian_jatuhmorse_nilai1,
        prjl.penilaian_jatuhmorse_skala2,
        prjl.penilaian_jatuhmorse_nilai2,
        prjl.penilaian_jatuhmorse_skala3,
        prjl.penilaian_jatuhmorse_nilai3,
        prjl.penilaian_jatuhmorse_skala4,
        prjl.penilaian_jatuhmorse_nilai4,
        prjl.penilaian_jatuhmorse_skala5,
        prjl.penilaian_jatuhmorse_nilai5,
        prjl.penilaian_jatuhmorse_skala6,
        prjl.penilaian_jatuhmorse_nilai6,
        prjl.penilaian_jatuhmorse_totalnilai,
        prjl.hasil_skrining,
        prjl.saran,
        prjl.nip,
        p.nama AS nama_petugas
    FROM penilaian_lanjutan_resiko_jatuh_lansia prjl
    LEFT JOIN petugas p ON prjl.nip = p.nip
    WHERE prjl.no_rawat = '$no_rawat'
    ORDER BY prjl.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian resiko jatuh lansia tidak ditemukan</div>';
    exit;
}

// Function untuk badge total nilai
function getBadgeTotalNilai($total) {
    $total_num = intval($total);
    if ($total_num >= 0 && $total_num <= 24) {
        $color = '#28a745';  // hijau - risiko rendah
        $status = 'Risiko Rendah';
    } elseif ($total_num >= 25 && $total_num <= 50) {
        $color = '#ffc107';  // kuning - risiko sedang
        $status = 'Risiko Sedang';
    } elseif ($total_num >= 51) {
        $color = '#dc3545';  // merah - risiko tinggi
        $status = 'Risiko Tinggi';
    } else {
        $color = '#6c757d';  // abu-abu
        $status = '';
    }
    
    $badge = "<span style='background-color: {$color}; color: white; padding: 5px 15px; border-radius: 4px; font-size: 16px; font-weight: bold; display: inline-block;'>{$total}</span>";
    if ($status) {
        $badge .= " <span style='color: {$color}; font-weight: bold; margin-left: 8px; font-size: 14px;'>({$status})</span>";
    }
    return $badge;
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.tabel-jatuh {
    width: 100%;
    border-collapse: collapse;
}
.tabel-jatuh td {
    padding: 10px;
    vertical-align: top;
}
.tabel-jatuh .col-waktu {
    width: 12%;
    font-weight: bold;
}
.tabel-jatuh .col-penilaian {
    width: 50%;
}
.tabel-jatuh .col-hasil {
    width: 38%;
}
.card-jatuh {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}
.penilaian-item {
    display: flex;
    margin-bottom: 8px;
    padding: 6px;
    background-color: #f8f9fa;
    border-radius: 3px;
}
.penilaian-label {
    flex: 1;
    font-weight: 500;
}
.penilaian-skala {
    min-width: 100px;
    text-align: center;
    color: #495057;
    font-size: 11px;
}
.penilaian-nilai {
    min-width: 60px;
    text-align: center;
    font-weight: bold;
    color: #dc3545;
}
.total-section {
    margin-top: 15px;
    padding: 12px;
    background-color: #e7f3ff;
    border-left: 4px solid #2196F3;
    border-radius: 4px;
}
.hasil-item {
    margin-bottom: 12px;
}
.hasil-label {
    font-weight: bold;
    display: block;
    margin-bottom: 4px;
    color: #495057;
}
.hasil-value {
    display: block;
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
<div class="card-jatuh">
    <table class="tabel-jatuh">
        <tr>
            <!-- Kolom 1: Waktu & Petugas -->
            <td class="col-waktu">
                <div><?= date('d/m/Y', strtotime($row['tanggal'])) ?></div>
                <div><?= date('H:i', strtotime($row['tanggal'])) ?></div>
                <div class="petugas-name"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
            </td>
            
            <!-- Kolom 2: Penilaian Morse Fall Scale Lansia -->
            <td class="col-penilaian">
                <div style="font-weight: bold; margin-bottom: 10px; color: #2c3e50;">Morse Fall Scale (Lansia):</div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">1. Riwayat Jatuh</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_jatuhmorse_skala1']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_jatuhmorse_nilai1']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">2. Diagnosis Sekunder</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_jatuhmorse_skala2']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_jatuhmorse_nilai2']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">3. Alat Bantu Jalan</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_jatuhmorse_skala3']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_jatuhmorse_nilai3']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">4. Terpasang Infus</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_jatuhmorse_skala4']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_jatuhmorse_nilai4']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">5. Gaya Berjalan</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_jatuhmorse_skala5']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_jatuhmorse_nilai5']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">6. Status Mental</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_jatuhmorse_skala6']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_jatuhmorse_nilai6']) ?></span>
                </div>
                
                <div class="total-section">
                    <strong>Total Nilai:</strong> <?= getBadgeTotalNilai($row['penilaian_jatuhmorse_totalnilai']) ?>
                </div>
            </td>
            
            <!-- Kolom 3: Hasil & Saran -->
            <td class="col-hasil">
                <?php if (!empty($row['hasil_skrining'])): ?>
                <div class="hasil-item">
                    <span class="hasil-label">Hasil Skrining:</span>
                    <span class="hasil-value"><?= nl2br(htmlspecialchars($row['hasil_skrining'])) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($row['saran'])): ?>
                <div class="hasil-item">
                    <span class="hasil-label">Saran:</span>
                    <span class="hasil-value"><?= nl2br(htmlspecialchars($row['saran'])) ?></span>
                </div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>