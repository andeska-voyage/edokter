<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

// Get parameters
$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm   = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

// Badge Tidak/Ya
function getBadgeYaTidak($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'ya')    return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#ef4444;color:#fff;">Ya</span>';
    if ($v == 'tidak') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#10b981;color:#fff;">Tidak</span>';
    return htmlspecialchars($value);
}

// Badge Non Reaktif / Reaktif
function getBadgeReaktif($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'reaktif')     return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#ef4444;color:#fff;">Reaktif</span>';
    if ($v == 'non reaktif') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#10b981;color:#fff;">Non Reaktif</span>';
    return htmlspecialchars($value);
}

// Badge tekanan vena Normal/Meningkat
function getBadgeTekananVena($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'normal')    return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#10b981;color:#fff;">Normal</span>';
    if ($v == 'meningkat') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#ef4444;color:#fff;">Meningkat</span>';
    return htmlspecialchars($value);
}

// Format tanggal Indonesia
function formatTanggal($tgl) {
    if (empty($tgl) || $tgl == '0000-00-00') return '-';
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $t = strtotime($tgl);
    return date('d', $t) . ' ' . $bulan[date('n', $t)] . ' ' . date('Y', $t);
}

// Query
$query_medis = "
    SELECT p.*, d.nm_dokter
    FROM penilaian_medis_hemodialisa p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";
$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data pengkajian medis Hemodialisa tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_medis)):
    $bulan_arr = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tanggal_obj).' '.$bulan_arr[date('n',$tanggal_obj)].' '.date('Y, H:i',$tanggal_obj);

    // Data riwayat penyakit (Tidak/Ya + keterangan)
    $riwayat_penyakit = [
        ['label'=>'Hipertensi',             'field'=>'hipertensi',             'ket'=>'keterangan_hipertensi'],
        ['label'=>'Diabetes',               'field'=>'diabetes',               'ket'=>'keterangan_diabetes'],
        ['label'=>'Batu Saluran Kemih',     'field'=>'batu_saluran_kemih',     'ket'=>'keterangan_batu_saluran_kemih'],
        ['label'=>'Operasi Saluran Kemih',  'field'=>'operasi_saluran_kemih',  'ket'=>'keterangan_operasi_saluran_kemih'],
        ['label'=>'Infeksi Saluran Kemih',  'field'=>'infeksi_saluran_kemih',  'ket'=>'keterangan_infeksi_saluran_kemih'],
        ['label'=>'Bengkak Seluruh Tubuh',  'field'=>'bengkak_seluruh_tubuh',  'ket'=>'keterangan_bengkak_seluruh_tubuh'],
        ['label'=>'Urin Berdarah',          'field'=>'urin_berdarah',          'ket'=>'keterangan_urin_berdarah'],
        ['label'=>'Penyakit Ginjal Laom',   'field'=>'penyakit_ginjal_laom',   'ket'=>'keterangan_penyakit_ginjal_laom'],
        ['label'=>'Penyakit Lain',          'field'=>'penyakit_lain',          'ket'=>'keterangan_penyakit_lain'],
        ['label'=>'Konsumsi Obat Nefro',    'field'=>'konsumsi_obat_nefro',    'ket'=>'keterangan_konsumsi_obat_nefro'],
    ];

    // Temuan klinis (Tidak/Ya)
    $temuan_klinis = [
        ['label'=>'Hepatomegali',   'field'=>'hepatomegali'],
        ['label'=>'Splenomegali',   'field'=>'splenomegali'],
        ['label'=>'Ascites',        'field'=>'ascites'],
        ['label'=>'Edema',          'field'=>'edema'],
        ['label'=>'Whezzing',       'field'=>'whezzing'],
        ['label'=>'Ronchi',         'field'=>'ronchi'],
        ['label'=>'Ikterik',        'field'=>'ikterik'],
        ['label'=>'Anemia',         'field'=>'anemia'],
        ['label'=>'Kardiomegali',   'field'=>'kardiomegali'],
        ['label'=>'Bising',         'field'=>'bising'],
    ];

    // Penunjang (Tidak/Ya + tanggal)
    $penunjang = [
        ['label'=>'EKG',            'field'=>'ekg',          'tgl'=>'tanggal_ekg'],
        ['label'=>'BNO',            'field'=>'bno',          'tgl'=>'tanggal_bno'],
        ['label'=>'USG',            'field'=>'usg',          'tgl'=>'tanggal_usg'],
        ['label'=>'Renogram',       'field'=>'renogram',     'tgl'=>'tanggal_renogram'],
        ['label'=>'Biopsi',         'field'=>'biopsi',       'tgl'=>'tanggal_biopsi'],
        ['label'=>'CT Scan',        'field'=>'ctscan',       'tgl'=>'tanggal_ctscan'],
        ['label'=>'Arteriografi',   'field'=>'arteriografi', 'tgl'=>'tanggal_arteriografi'],
        ['label'=>'Kultur Urin',    'field'=>'kultur_urin',  'tgl'=>'tanggal_kultur_urin'],
        ['label'=>'Laboratorium',   'field'=>'laborat',      'tgl'=>'tanggal_laborat'],
    ];
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<style>
    .hd-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin: 6px 0 10px 0;
    }
    .hd-table thead tr { background: #f1f5f9; }
    .hd-table thead th {
        padding: 7px 10px;
        text-align: left;
        font-weight: 600;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        border-bottom: 2px solid #e2e8f0;
    }
    .hd-table tbody tr { border-bottom: 1px solid #f1f5f9; }
    .hd-table tbody tr:hover { background: #f8fafc; }
    .hd-table tbody td {
        padding: 6px 10px;
        font-size: 12px;
        color: #1e293b;
        vertical-align: middle;
    }
    .hd-table td.col-label { font-weight: 600; color: #475569; width: 30%; }
    .hd-table td.col-status { width: 18%; }
    .hd-table td.col-ket { color: #64748b; }
    .hd-table td.col-tgl { color: #64748b; width: 22%; }
    .lab-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 4px 12px;
        margin-bottom: 8px;
    }
    .lab-item {
        display: flex;
        align-items: baseline;
        padding: 4px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .lab-item .lab-label {
        font-weight: 600;
        color: #64748b;
        font-size: 12px;
        flex-shrink: 0;
        min-width: 110px;
    }
    .lab-item .lab-value {
        color: #1e293b;
        font-size: 12px;
        font-weight: 500;
        margin-left: 4px;
    }
    @media (max-width: 768px) {
        .lab-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 576px) {
        .lab-grid { grid-template-columns: 1fr; }
    }
</style>
<div class="card mb-3 shadow-sm">
    <div class="card-body">

        <!-- I. DATA UMUM -->
        <div class="section-title">
            <i class="fa fa-id-card"></i> I. Data Umum
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Cara Anamnesis:</span>
                <span class="info-value"><?= htmlspecialchars($data['anamnesis']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Hubungan dengan Pasien:</span>
                <span class="info-value"><?= htmlspecialchars($data['hubungan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ruangan:</span>
                <span class="info-value"><?= htmlspecialchars($data['ruangan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nyeri:</span>
                <span class="info-value"><?= htmlspecialchars($data['nyeri']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Status Nutrisi:</span>
                <span class="info-value"><?= htmlspecialchars($data['status_nutrisi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Alergi:</span>
                <span class="info-value"><?= htmlspecialchars($data['alergi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. RIWAYAT PENYAKIT -->
        <div class="section-title">
            <i class="fa fa-notes-medical"></i> II. Riwayat Penyakit
        </div>
        <table class="hd-table">
            <thead>
                <tr>
                    <th class="col-label">Penyakit</th>
                    <th class="col-status">Status</th>
                    <th class="col-ket">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat_penyakit as $rp): ?>
                <tr>
                    <td class="col-label"><?= $rp['label'] ?></td>
                    <td class="col-status"><?= getBadgeYaTidak($data[$rp['field']]) ?></td>
                    <td class="col-ket"><?= htmlspecialchars($data[$rp['ket']]) ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- III. RIWAYAT HEMODIALISA -->
        <div class="section-title">
            <i class="fa fa-procedures"></i> III. Riwayat Hemodialisa
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Dialisis Pertama:</span>
                <span class="info-value"><?= formatTanggal($data['dialisis_pertama']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Pernah CPAD:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['pernah_cpad']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tanggal CPAD:</span>
                <span class="info-value"><?= formatTanggal($data['tanggal_cpad']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Pernah Transplantasi:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['pernah_transplantasi']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tanggal Transplantasi:</span>
                <span class="info-value"><?= formatTanggal($data['tanggal_transplantasi']) ?></span>
            </div>
        </div>

        <!-- IV. PEMERIKSAAN FISIK -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> IV. Pemeriksaan Fisik
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Keadaan Umum:</span>
                <span class="info-value"><?= htmlspecialchars($data['keadaan_umum']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kesadaran:</span>
                <span class="info-value"><?= htmlspecialchars($data['kesadaran']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TD:</span>
                <span class="info-value"><?= htmlspecialchars($data['td']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nadi:</span>
                <span class="info-value"><?= htmlspecialchars($data['nadi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Napas:</span>
                <span class="info-value"><?= htmlspecialchars($data['napas']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Suhu:</span>
                <span class="info-value"><?= htmlspecialchars($data['suhu']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">BB:</span>
                <span class="info-value"><?= htmlspecialchars($data['bb']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TB:</span>
                <span class="info-value"><?= htmlspecialchars($data['tb']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tekanan Vena:</span>
                <span class="info-value"><?= getBadgeTekananVena($data['tekanan_vena']) ?></span>
            </div>
        </div>

        <!-- Temuan Klinis -->
        <div class="info-grid mt-2">
            <?php foreach ($temuan_klinis as $tk): ?>
            <div class="info-item">
                <span class="info-label"><?= $tk['label'] ?>:</span>
                <span class="info-value"><?= getBadgeYaTidak($data[$tk['field']]) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Thorax -->
        <div class="info-grid mt-2">
            <div class="info-item">
                <span class="info-label">Foto Thorax:</span>
                <span class="info-value">
                    <?= getBadgeYaTidak($data['thorax']) ?>
                    <?php if (!empty($data['tanggal_thorax']) && $data['tanggal_thorax'] != '0000-00-00'): ?>
                        &nbsp;<small style="color:#64748b;"><?= formatTanggal($data['tanggal_thorax']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- V. PEMERIKSAAN PENUNJANG -->
        <div class="section-title">
            <i class="fa fa-vial"></i> V. Pemeriksaan Penunjang
        </div>
        <table class="hd-table">
            <thead>
                <tr>
                    <th class="col-label">Pemeriksaan</th>
                    <th class="col-status">Dilakukan</th>
                    <th class="col-tgl">Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($penunjang as $pj): ?>
                <tr>
                    <td class="col-label"><?= $pj['label'] ?></td>
                    <td class="col-status"><?= getBadgeYaTidak($data[$pj['field']]) ?></td>
                    <td class="col-tgl"><?= formatTanggal($data[$pj['tgl']]) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- VI. HASIL LABORATORIUM -->
        <div class="section-title">
            <i class="fa fa-flask"></i> VI. Hasil Laboratorium
        </div>
        <div class="lab-grid">
            <div class="lab-item">
                <span class="lab-label">Hematokrit:</span>
                <span class="lab-value"><?= htmlspecialchars($data['hematokrit']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">Hemoglobin:</span>
                <span class="lab-value"><?= htmlspecialchars($data['hemoglobin']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">Leukosit:</span>
                <span class="lab-value"><?= htmlspecialchars($data['leukosit']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">Trombosit:</span>
                <span class="lab-value"><?= htmlspecialchars($data['trombosit']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">Hitung Jenis:</span>
                <span class="lab-value"><?= htmlspecialchars($data['hitung_jenis']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">Ureum:</span>
                <span class="lab-value"><?= htmlspecialchars($data['ureum']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">Urin Lengkap:</span>
                <span class="lab-value"><?= htmlspecialchars($data['urin_lengkap']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">Kreatinin:</span>
                <span class="lab-value"><?= htmlspecialchars($data['kreatinin']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">CCT:</span>
                <span class="lab-value"><?= htmlspecialchars($data['cct']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">SGOT:</span>
                <span class="lab-value"><?= htmlspecialchars($data['sgot']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">SGPT:</span>
                <span class="lab-value"><?= htmlspecialchars($data['sgpt']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">CT:</span>
                <span class="lab-value"><?= htmlspecialchars($data['ct']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">Asam Urat:</span>
                <span class="lab-value"><?= htmlspecialchars($data['asam_urat']) ?: '-' ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">HBsAg:</span>
                <span class="lab-value"><?= getBadgeReaktif($data['hbsag']) ?></span>
            </div>
            <div class="lab-item">
                <span class="lab-label">Anti HCV:</span>
                <span class="lab-value"><?= getBadgeReaktif($data['anti_hcv']) ?></span>
            </div>
        </div>

        <!-- VII. EDUKASI -->
        <?php if (!empty($data['edukasi'])): ?>
        <div class="section-title">
            <i class="fa fa-chalkboard-teacher"></i> VII. Edukasi
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['edukasi'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>