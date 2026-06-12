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
        ag.tanggal,
        ag.antropometri_bb,
        ag.antropometri_tb,
        ag.antropometri_imt,
        ag.antropometri_lla,
        ag.antropometri_tl,
        ag.antropometri_ulna,
        ag.antropometri_bbideal,
        ag.antropometri_bbperu,
        ag.antropometri_tbperu,
        ag.antropometri_bbpertb,
        ag.antropometri_llaperu,
        ag.biokimia,
        ag.fisik_klinis,
        ag.alergi_telur,
        ag.alergi_susu_sapi,
        ag.alergi_kacang,
        ag.alergi_gluten,
        ag.alergi_udang,
        ag.alergi_ikan,
        ag.alergi_hazelnut,
        ag.pola_makan,
        ag.riwayat_personal,
        ag.diagnosis,
        ag.intervensi_gizi,
        ag.monitoring_evaluasi,
        ag.nip,
        p.nama AS nama_petugas
    FROM asuhan_gizi ag
    LEFT JOIN petugas p ON ag.nip = p.nip
    WHERE ag.no_rawat = '$no_rawat'
    ORDER BY ag.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data asuhan gizi tidak ditemukan</div>';
    exit;
}
?>

<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
/* ===== STRUKTUR CARD (sama persis dengan PEWS dewasa) ===== */
.tabel-ag {
    width: 100%;
    border-collapse: collapse;
}
.tabel-ag td {
    padding: 10px;
    vertical-align: top;
}
.tabel-ag .col-waktu {
    width: 12%;
    font-weight: bold;
    white-space: nowrap;
}
.tabel-ag .col-isi {
    width: 88%;
}
.card-ag {
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

/* ===== ANTROPOMETRI GRID (compact, banyak kolom) ===== */
.antro-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
    gap: 6px;
    margin-bottom: 6px;
}
.antro-item {
    padding: 5px 8px;
    background-color: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #28a745;
}
.antro-label {
    font-size: 10px;
    color: #6c757d;
    font-weight: 600;
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.antro-value {
    font-size: 13px;
    font-weight: bold;
    color: #212529;
}

/* ===== INFO ROW (label: value dalam satu baris) ===== */
.info-row {
    display: flex;
    align-items: baseline;
    padding: 3px 0;
    font-size: 12px;
    border-bottom: 1px solid #f1f5f9;
    gap: 6px;
}
.info-row .lbl {
    font-weight: 600;
    color: #64748b;
    flex-shrink: 0;
    min-width: 120px;
}
.info-row .val {
    color: #1e293b;
}

/* ===== SECTION TITLE compact ===== */
.ag-section {
    font-size: 11px;
    font-weight: 700;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 2px;
    margin: 8px 0 4px 0;
}
.ag-section:first-child { margin-top: 0; }

/* ===== TEKS PANJANG ===== */
.ag-text {
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
.ag-text.blue   { border-left-color: #3b82f6; }
.ag-text.teal   { border-left-color: #14b8a6; }
.ag-text.purple { border-left-color: #8b5cf6; }
.ag-text.grey   { border-left-color: #94a3b8; }
.ag-text.red    { border-left-color: #ef4444; }
.ag-text.indigo { border-left-color: #6366f1; }
.ag-text.violet { border-left-color: #7c3aed; }

/* ===== ALERGI BADGE compact ===== */
.alergi-wrap { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 2px; }
.alergi-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.alergi-ya    { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
.alergi-tidak { background-color: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
</style>

<?php while ($row = mysqli_fetch_assoc($result)): ?>
<div class="card-ag">
    <table class="tabel-ag">
        <tr>
            <!-- Kolom Kiri: Waktu & Petugas -->
            <td class="col-waktu">
                <div><?= date('d/m/Y', strtotime($row['tanggal'])) ?></div>
                <div><?= date('H:i', strtotime($row['tanggal'])) ?></div>
                <div class="petugas-name"><?= htmlspecialchars($row['nama_petugas'] ?: '-') ?></div>
            </td>

            <!-- Kolom Kanan: Semua Isi -->
            <td class="col-isi">

                <!-- ANTROPOMETRI -->
                <div class="ag-section">Antropometri</div>
                <div class="antro-grid">
                    <?php
                    $antro = [
                        'antropometri_bb'      => 'BB (kg)',
                        'antropometri_tb'      => 'TB (cm)',
                        'antropometri_imt'     => 'IMT',
                        'antropometri_lla'     => 'LLA (cm)',
                        'antropometri_tl'      => 'TL (cm)',
                        'antropometri_ulna'    => 'Ulna (cm)',
                        'antropometri_bbideal' => 'BB Ideal',
                        'antropometri_bbperu'  => 'BB/U (%)',
                        'antropometri_tbperu'  => 'TB/U (%)',
                        'antropometri_bbpertb' => 'BB/TB (%)',
                        'antropometri_llaperu' => 'LLA/U (%)',
                    ];
                    foreach ($antro as $key => $label): ?>
                    <div class="antro-item">
                        <span class="antro-label"><?= $label ?></span>
                        <span class="antro-value"><?= htmlspecialchars($row[$key] ?: '-') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- BIOKIMIA -->
                <?php if (!empty($row['biokimia'])): ?>
                <div class="ag-section">Biokimia</div>
                <div class="ag-text blue"><?= nl2br(htmlspecialchars($row['biokimia'])) ?></div>
                <?php endif; ?>

                <!-- FISIK KLINIS -->
                <?php if (!empty($row['fisik_klinis'])): ?>
                <div class="ag-section">Fisik Klinis</div>
                <div class="ag-text teal"><?= nl2br(htmlspecialchars($row['fisik_klinis'])) ?></div>
                <?php endif; ?>

                <!-- RIWAYAT ALERGI -->
                <div class="ag-section">Riwayat Alergi</div>
                <div class="alergi-wrap">
                    <?php
                    $alergi_list = [
                        'alergi_telur'     => 'Telur',
                        'alergi_susu_sapi' => 'Susu Sapi',
                        'alergi_kacang'    => 'Kacang',
                        'alergi_gluten'    => 'Gluten',
                        'alergi_udang'     => 'Udang',
                        'alergi_ikan'      => 'Ikan',
                        'alergi_hazelnut'  => 'Hazelnut',
                    ];
                    foreach ($alergi_list as $key => $label):
                        $val = $row[$key] ?? 'Tidak';
                        $cls = (strtolower($val) === 'ya') ? 'alergi-ya' : 'alergi-tidak';
                    ?>
                    <span class="alergi-badge <?= $cls ?>"><?= $label ?>: <?= htmlspecialchars($val) ?></span>
                    <?php endforeach; ?>
                </div>

                <!-- POLA MAKAN -->
                <?php if (!empty($row['pola_makan'])): ?>
                <div class="ag-section">Pola Makan</div>
                <div class="ag-text purple"><?= nl2br(htmlspecialchars($row['pola_makan'])) ?></div>
                <?php endif; ?>

                <!-- RIWAYAT PERSONAL -->
                <?php if (!empty($row['riwayat_personal'])): ?>
                <div class="ag-section">Riwayat Personal</div>
                <div class="ag-text grey"><?= nl2br(htmlspecialchars($row['riwayat_personal'])) ?></div>
                <?php endif; ?>

                <!-- DIAGNOSIS GIZI -->
                <?php if (!empty($row['diagnosis'])): ?>
                <div class="ag-section">Diagnosis Gizi</div>
                <div class="ag-text red"><?= nl2br(htmlspecialchars($row['diagnosis'])) ?></div>
                <?php endif; ?>

                <!-- INTERVENSI GIZI -->
                <?php if (!empty($row['intervensi_gizi'])): ?>
                <div class="ag-section">Intervensi Gizi</div>
                <div class="ag-text indigo"><?= nl2br(htmlspecialchars($row['intervensi_gizi'])) ?></div>
                <?php endif; ?>

                <!-- MONITORING & EVALUASI -->
                <?php if (!empty($row['monitoring_evaluasi'])): ?>
                <div class="ag-section">Monitoring &amp; Evaluasi</div>
                <div class="ag-text violet"><?= nl2br(htmlspecialchars($row['monitoring_evaluasi'])) ?></div>
                <?php endif; ?>

            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>
