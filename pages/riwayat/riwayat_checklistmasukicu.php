<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

$query = "
    SELECT ck.*, p.nama AS nama_petugas
    FROM checklist_kriteria_masuk_icu ck
    LEFT JOIN petugas p ON ck.nik = p.nip
    WHERE ck.no_rawat = '$no_rawat'
    ORDER BY ck.tanggal DESC
";
$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data checklist kriteria masuk ICU tidak ditemukan</div>';
    exit;
}

// Label untuk setiap kolom
$labels = [
    // Prioritas 1
    'prioritas1_1' => 'Pasca Operasi Dengan Gangguan Nafas Atau Hipotensi',
    'prioritas1_2' => 'Gagal Nafas',
    'prioritas1_3' => 'Gagal Jantung Dengan Tanda Bendungan Paru',
    'prioritas1_4' => 'Gangguan Asam Basa / Elektrolit',
    'prioritas1_5' => 'Gagal Ginjal Dengan Tanda Bendungan Paru',
    'prioritas1_6' => 'Syok Karena Perdarahan Anafilaksis',
    // Prioritas 2
    'prioritas2_1' => 'Pasca Operasi Besar',
    'prioritas2_2' => 'Kejang Berulang',
    'prioritas2_3' => 'Gangguan Kesadaran',
    'prioritas2_4' => 'Dehidrasi Berat',
    'prioritas2_5' => 'Gangguan Jalan Nafas',
    'prioritas2_6' => 'Arimia Jantung',
    'prioritas2_7' => 'Asma Akut Berat',
    'prioritas2_8' => 'Diabetes Yang Memerlukan Terapi Insulin Kontinyu',
    // Prioritas 3
    'prioritas3_1' => 'Penyakit Keganasan Dengan Metastasis',
    'prioritas3_2' => 'Pasien Geriatrik Dengan Fungsi Hidup Sebelumnya Minimal',
    'prioritas3_3' => 'Pasien Dengan GCS 3',
    'prioritas3_4' => 'Pasien Jantung, Penyakit Paru Terminal Disertai Komplikasi Penyakit Akut Berat',
    // Tanda Vital
    'kriteria_fisiologis_tanda_vital_1' => 'Nadi < 40 atau >150 (x/menit)',
    'kriteria_fisiologis_tanda_vital_2' => 'SBP < 80 mmHg Atau 20 mmHg Di Bawah SBP Pasien',
    'kriteria_fisiologis_tanda_vital_3' => 'MAP < 60 mmHg',
    'kriteria_fisiologis_tanda_vital_4' => 'DBP > 120 mmHg',
    'kriteria_fisiologis_tanda_vital_5' => 'R > 35 x/menit',
    // Laboratorium
    'kriteria_fisiologis_laborat_1' => 'Na < 110 meq/L Atau > 170 meq/L',
    'kriteria_fisiologis_laborat_2' => 'Ca > 15 mg/dl',
    'kriteria_fisiologis_laborat_3' => 'GDS > 800 mg/dl',
    'kriteria_fisiologis_laborat_4' => 'K < 2 meq/L Atau 7meq/L',
    'kriteria_fisiologis_laborat_5' => 'PaO2 < 50 mmHg',
    'kriteria_fisiologis_laborat_6' => 'PH < 7,1 Atau 7,7',
    // Radiologi
    'kriteria_fisiologis_radiologi_1' => 'Perbedaan Cerebrovaskuler, SAH, Atau Contusion Dengan Gangguan Kesadaran Atau Neurologi',
    'kriteria_fisiologis_radiologi_2' => 'Ruptor Organ Dalam, Kandung Kemih, Hati, Varices Esophagus Atau Uterus Dengan Gangguan Hemodinamik',
    // Klinis
    'kriteria_fisiologis_klinis_1' => 'Pupil Anisokor',
    'kriteria_fisiologis_klinis_2' => 'Obstruksi Jalan Nafas',
    'kriteria_fisiologis_klinis_3' => 'Anuria',
    'kriteria_fisiologis_klinis_4' => 'Kejang Berulang',
    'kriteria_fisiologis_klinis_5' => 'Tamponade Jantung',
    'kriteria_fisiologis_klinis_6' => 'Coma',
    'kriteria_fisiologis_klinis_7' => 'Sianosis',
    'kriteria_fisiologis_klinis_8' => 'Luka Bakar > 10 % BSA',
];

// Grouping section
$sections = [
    'I. PRIORITAS 1' => ['prioritas1_1','prioritas1_2','prioritas1_3','prioritas1_4','prioritas1_5','prioritas1_6'],
    'II. PRIORITAS 2' => ['prioritas2_1','prioritas2_2','prioritas2_3','prioritas2_4','prioritas2_5','prioritas2_6','prioritas2_7','prioritas2_8'],
    'III. PRIORITAS 3' => ['prioritas3_1','prioritas3_2','prioritas3_3','prioritas3_4'],
    'IV. KRITERIA FISIOLOGIS TANDA-TANDA VITAL' => ['kriteria_fisiologis_tanda_vital_1','kriteria_fisiologis_tanda_vital_2','kriteria_fisiologis_tanda_vital_3','kriteria_fisiologis_tanda_vital_4','kriteria_fisiologis_tanda_vital_5'],
    'V. KRITERIA FISIOLOGIS LABORATORIUM' => ['kriteria_fisiologis_laborat_1','kriteria_fisiologis_laborat_2','kriteria_fisiologis_laborat_3','kriteria_fisiologis_laborat_4','kriteria_fisiologis_laborat_5','kriteria_fisiologis_laborat_6'],
    'VI. KRITERIA FISIOLOGIS RADIOLOGI' => ['kriteria_fisiologis_radiologi_1','kriteria_fisiologis_radiologi_2'],
    'VII. KRITERIA FISIOLOGIS KLINIS' => ['kriteria_fisiologis_klinis_1','kriteria_fisiologis_klinis_2','kriteria_fisiologis_klinis_3','kriteria_fisiologis_klinis_4','kriteria_fisiologis_klinis_5','kriteria_fisiologis_klinis_6','kriteria_fisiologis_klinis_7','kriteria_fisiologis_klinis_8'],
];

// Hitung berapa field "Ya" per row
function countYa($row, $fields) {
    $n = 0;
    foreach ($fields as $f) {
        if (isset($row[$f]) && $row[$f] === 'Ya') $n++;
    }
    return $n;
}

// Semua field checklist
$allFields = array_merge(...array_values($sections));
?>

<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.card-icu {
    margin-bottom: 16px;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    overflow: hidden;
}
.card-icu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border-bottom: 1px solid #90caf9;
}
.card-icu-header .waktu {
    font-weight: bold;
    font-size: 14px;
    color: #1565c0;
}
.card-icu-header .petugas {
    font-size: 12px;
    color: #1976d2;
}
.card-icu-header .summary-badge {
    background: #1565c0;
    color: white;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}
.card-icu-body {
    padding: 12px 14px;
}
.section-icu {
    margin-bottom: 10px;
}
.section-icu-title {
    font-size: 11px;
    font-weight: 700;
    color: #1e293b;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 4px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.section-icu-title .sec-count {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: none;
}
.checklist-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 5px 10px;
}
.checklist-item {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    padding: 4px 6px;
    border-radius: 3px;
    font-size: 12px;
    line-height: 1.4;
}
.checklist-item.ya {
    background: #e8f5e9;
    color: #1b5e20;
}
.checklist-item.tidak {
    color: #adb5bd;
}
.checklist-item .ci-icon {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
    margin-top: 1px;
}
.checklist-item.ya .ci-icon {
    background: #4caf50;
    color: white;
}
.checklist-item.tidak .ci-icon {
    background: #e9ecef;
    color: #adb5bd;
}
</style>

<?php while ($row = mysqli_fetch_assoc($result)):
    $totalYa = countYa($row, $allFields);
?>
<div class="card-icu">
    <div class="card-icu-header">
        <div>
            <div class="waktu">
                <?= date('d/m/Y', strtotime($row['tanggal'])) ?>
                <?= date('H:i', strtotime($row['tanggal'])) ?>
            </div>
            <div class="petugas"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
        </div>
        <div class="summary-badge">
            <?= $totalYa ?> Kriteria Terpenuhi
        </div>
    </div>

    <div class="card-icu-body">
        <?php foreach ($sections as $sectionTitle => $fields):
            $yaCount = countYa($row, $fields);
        ?>
        <div class="section-icu">
            <div class="section-icu-title">
                <span><?= htmlspecialchars($sectionTitle) ?></span>
                <?php if ($yaCount > 0): ?>
                <span class="sec-count" style="color:#1976d2;"><?= $yaCount ?> / <?= count($fields) ?> Ya</span>
                <?php else: ?>
                <span class="sec-count"><?= $yaCount ?> / <?= count($fields) ?> Ya</span>
                <?php endif; ?>
            </div>
            <div class="checklist-grid">
                <?php foreach ($fields as $field):
                    $val = isset($row[$field]) ? $row[$field] : 'Tidak';
                    $isYa = ($val === 'Ya');
                    $label = isset($labels[$field]) ? $labels[$field] : $field;
                ?>
                <div class="checklist-item <?= $isYa ? 'ya' : 'tidak' ?>">
                    <span class="ci-icon"><?= $isYa ? '✓' : '–' ?></span>
                    <span><?= htmlspecialchars($label) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endwhile; ?>
