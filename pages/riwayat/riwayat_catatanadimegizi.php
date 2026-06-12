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
        cag.tanggal,
        cag.asesmen,
        cag.diagnosis,
        cag.intervensi,
        cag.monitoring,
        cag.evaluasi,
        cag.instruksi,
        cag.nip,
        p.nama AS nama_petugas
    FROM catatan_adime_gizi cag
    LEFT JOIN petugas p ON cag.nip = p.nip
    WHERE cag.no_rawat = '$no_rawat'
    ORDER BY cag.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data catatan ADIME gizi tidak ditemukan</div>';
    exit;
}
?>

<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.tabel-adime {
    width: 100%;
    border-collapse: collapse;
}
.tabel-adime td {
    padding: 10px;
    vertical-align: top;
}
.tabel-adime .col-waktu {
    width: 12%;
    font-weight: bold;
    white-space: nowrap;
}
.tabel-adime .col-isi {
    width: 88%;
}
.card-adime {
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
.adime-section {
    font-size: 11px;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 2px;
    margin: 8px 0 4px 0;
}
.adime-section:first-child { margin-top: 0; }

/* Label huruf ADIME di depan */
.adime-letter {
    display: inline-block;
    width: 18px;
    height: 18px;
    line-height: 18px;
    text-align: center;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 800;
    color: #fff;
    margin-right: 5px;
    vertical-align: middle;
}
.letter-a  { background-color: #3b82f6; } /* Asesmen    - biru    */
.letter-d  { background-color: #ef4444; } /* Diagnosis  - merah   */
.letter-i  { background-color: #10b981; } /* Intervensi - hijau   */
.letter-m  { background-color: #f59e0b; } /* Monitoring - kuning  */
.letter-e  { background-color: #8b5cf6; } /* Evaluasi   - ungu    */
.letter-in { background-color: #64748b; } /* Instruksi  - abu     */

/* Teks konten */
.adime-text {
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
.adime-text.blue   { border-left-color: #3b82f6; }
.adime-text.red    { border-left-color: #ef4444; }
.adime-text.green  { border-left-color: #10b981; }
.adime-text.yellow { border-left-color: #f59e0b; }
.adime-text.purple { border-left-color: #8b5cf6; }
.adime-text.grey   { border-left-color: #64748b; }
</style>

<?php while ($row = mysqli_fetch_assoc($result)): ?>
<div class="card-adime">
    <table class="tabel-adime">
        <tr>
            <!-- Kolom Kiri: Waktu & Petugas -->
            <td class="col-waktu">
                <div><?= date('d/m/Y', strtotime($row['tanggal'])) ?></div>
                <div><?= date('H:i', strtotime($row['tanggal'])) ?></div>
                <div class="petugas-name"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
            </td>

            <!-- Kolom Kanan: Isi ADIME -->
            <td class="col-isi">

                <!-- A - ASESMEN -->
                <?php if (!empty($row['asesmen'])): ?>
                <div class="adime-section">
                    <span class="adime-letter letter-a">A</span> Asesmen
                </div>
                <div class="adime-text blue"><?= nl2br(htmlspecialchars($row['asesmen'])) ?></div>
                <?php endif; ?>

                <!-- D - DIAGNOSIS -->
                <?php if (!empty($row['diagnosis'])): ?>
                <div class="adime-section">
                    <span class="adime-letter letter-d">D</span> Diagnosis Gizi
                </div>
                <div class="adime-text red"><?= nl2br(htmlspecialchars($row['diagnosis'])) ?></div>
                <?php endif; ?>

                <!-- I - INTERVENSI -->
                <?php if (!empty($row['intervensi'])): ?>
                <div class="adime-section">
                    <span class="adime-letter letter-i">I</span> Intervensi
                </div>
                <div class="adime-text green"><?= nl2br(htmlspecialchars($row['intervensi'])) ?></div>
                <?php endif; ?>

                <!-- M - MONITORING -->
                <?php if (!empty($row['monitoring'])): ?>
                <div class="adime-section">
                    <span class="adime-letter letter-m">M</span> Monitoring
                </div>
                <div class="adime-text yellow"><?= nl2br(htmlspecialchars($row['monitoring'])) ?></div>
                <?php endif; ?>

                <!-- E - EVALUASI -->
                <?php if (!empty($row['evaluasi'])): ?>
                <div class="adime-section">
                    <span class="adime-letter letter-e">E</span> Evaluasi
                </div>
                <div class="adime-text purple"><?= nl2br(htmlspecialchars($row['evaluasi'])) ?></div>
                <?php endif; ?>

                <!-- INSTRUKSI -->
                <?php if (!empty($row['instruksi'])): ?>
                <div class="adime-section">
                    <span class="adime-letter letter-in">!</span> Instruksi
                </div>
                <div class="adime-text grey"><?= nl2br(htmlspecialchars($row['instruksi'])) ?></div>
                <?php endif; ?>

            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>
