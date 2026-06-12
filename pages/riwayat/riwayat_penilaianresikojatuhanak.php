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

// Query untuk ambil data penilaian resiko jatuh anak
$query = "
    SELECT 
        prja.tanggal,
        prja.penilaian_humptydumpty_skala1,
        prja.penilaian_humptydumpty_nilai1,
        prja.penilaian_humptydumpty_skala2,
        prja.penilaian_humptydumpty_nilai2,
        prja.penilaian_humptydumpty_skala3,
        prja.penilaian_humptydumpty_nilai3,
        prja.penilaian_humptydumpty_skala4,
        prja.penilaian_humptydumpty_nilai4,
        prja.penilaian_humptydumpty_skala5,
        prja.penilaian_humptydumpty_nilai5,
        prja.penilaian_humptydumpty_skala6,
        prja.penilaian_humptydumpty_nilai6,
        prja.penilaian_humptydumpty_skala7,
        prja.penilaian_humptydumpty_nilai7,
        prja.penilaian_humptydumpty_totalnilai,
        prja.hasil_skrining,
        prja.saran,
        prja.nip,
        p.nama AS nama_petugas
    FROM penilaian_lanjutan_resiko_jatuh_anak prja
    LEFT JOIN petugas p ON prja.nip = p.nip
    WHERE prja.no_rawat = '$no_rawat'
    ORDER BY prja.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian resiko jatuh anak tidak ditemukan</div>';
    exit;
}

// Function untuk badge total nilai
function getBadgeTotalNilai($total) {
    $total_num = intval($total);
    if ($total_num >= 7 && $total_num <= 11) {
        $color = '#28a745';  // hijau - risiko rendah
        $status = 'Risiko Rendah';
    } elseif ($total_num >= 12) {
        $color = '#dc3545';  // merah - risiko tinggi
        $status = 'Risiko Tinggi';
    } else {
        $color = '#6c757d';  // abu-abu
        $status = 'Minimal';
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
    min-width: 120px;
    text-align: center;
    color: #495057;
    font-size: 12px;
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
            
            <!-- Kolom 2: Penilaian Humpty Dumpty Scale -->
            <td class="col-penilaian">
                <div style="font-weight: bold; margin-bottom: 10px; color: #2c3e50;">Humpty Dumpty Scale:</div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">1. Umur</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_humptydumpty_skala1']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_humptydumpty_nilai1']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">2. Jenis Kelamin</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_humptydumpty_skala2']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_humptydumpty_nilai2']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">3. Diagnosis</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_humptydumpty_skala3']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_humptydumpty_nilai3']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">4. Gangguan Kognitif</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_humptydumpty_skala4']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_humptydumpty_nilai4']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">5. Faktor Lingkungan</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_humptydumpty_skala5']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_humptydumpty_nilai5']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">6. Respon Terhadap Operasi/Sedasi</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_humptydumpty_skala6']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_humptydumpty_nilai6']) ?></span>
                </div>
                
                <div class="penilaian-item">
                    <span class="penilaian-label">7. Penggunaan Obat</span>
                    <span class="penilaian-skala"><?= htmlspecialchars($row['penilaian_humptydumpty_skala7']) ?></span>
                    <span class="penilaian-nilai"><?= htmlspecialchars($row['penilaian_humptydumpty_nilai7']) ?></span>
                </div>
                
                <div class="total-section">
                    <strong>Total Nilai:</strong> <?= getBadgeTotalNilai($row['penilaian_humptydumpty_totalnilai']) ?>
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