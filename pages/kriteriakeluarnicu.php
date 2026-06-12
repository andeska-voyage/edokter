<?php
define('BASE_URL_KNICU', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';
$no_rawat          = '';
$no_rkm_medis      = '';
if(!empty($encrypted_norawat)) { $no_rawat     = encrypt_decrypt(urldecode($encrypted_norawat), 'd'); }
if(!empty($encrypted_norm))    { $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm),    'd'); }

$queryPasien = bukaquery("SELECT 
                            rp.no_rawat, rp.no_rkm_medis, rp.tgl_registrasi, rp.jam_reg,
                            p.nm_pasien, p.jk, p.tmp_lahir, p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                            d.nm_dokter, d.kd_dokter
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                        WHERE rp.no_rawat = '$no_rawat'");
$rsPasien = mysqli_fetch_array($queryPasien);
if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.location.href='?act=Pasien';</script>";
    exit;
}

$queryCheck = bukaquery("SELECT ck.*, pg.nama as nama_pengisi 
                         FROM checklist_kriteria_keluar_nicu ck
                         LEFT JOIN pegawai pg ON ck.nik = pg.nik
                         WHERE ck.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;
$nama_pengisi = ($rsCheck && !empty($rsCheck['nama_pengisi'])) ? $rsCheck['nama_pengisi'] : '';

$nikUser = '';
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
if(!empty($kd_dokter_encrypted)) {
    $nikUser = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Semua field enum Ya/Tidak sesuai tabel checklist_kriteria_keluar_nicu
$allFields = [
    'respirasi1','respirasi2','respirasi3',
    'kardio1','kardio2',
    'nutrisi1','nutrisi2','nutrisi3',
    'suhutubuh1','suhutubuh2',
    'infeksi1','infeksi2','infeksi3'
];

$data = [
    'tanggal'    => date('Y-m-d H:i:s'),
    'nik'        => $nikUser,
    'keputusan'  => 'Layak Dipindahkan Ke Ruang Rawat Bayi/Rawat Gabung',
    'keterangan' => ''
];
foreach($allFields as $f) { $data[$f] = 'Tidak'; }
if($isEdit) { $data = array_merge($data, $rsCheck); }

function nicuSelect($name, $value) {
    $sT = ($value == 'Ya') ? '' : 'selected';
    $sY = ($value == 'Ya') ? 'selected' : '';
    return '<select name="'.$name.'" class="icu-sel">
                <option value="Tidak" '.$sT.'>Tidak</option>
                <option value="Ya"    '.$sY.'>Ya</option>
            </select>';
}
function nicuItem($label, $name, $value) {
    return '<div class="icu-field">
                <span class="icu-label">'.$label.' :</span>
                '.nicuSelect($name, $value).'
            </div>';
}

$keputusanOptions = [
    'Layak Dipindahkan Ke Ruang Rawat Bayi/Rawat Gabung',
    'Belum Layak Dipindahkan',
    'Dirujuk'
];
?>

<link rel="stylesheet" href="<?php echo BASE_URL_KNICU; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
    .keputusan-select { width:100%; padding:6px 10px; border:1px solid #ccc; border-radius:4px; font-size:13px; }
    .keterangan-input { width:100%; padding:6px 10px; border:1px solid #ccc; border-radius:4px; font-size:13px; box-sizing:border-box; }
    .keputusan-row    { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .keputusan-row > * { flex:1; min-width:200px; }
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">logout</i>
                CHECKLIST KRITERIA KELUAR NICU
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <?php if($isEdit && !empty($nama_pengisi)): ?>
            <div class="info-item"><i class="material-icons">person</i><strong>Diisi oleh:</strong> <?php echo $nama_pengisi; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-card">
        <div class="form-content">
            <form id="formChecklistKeluarNICU" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="nik"      value="<?php echo $nikUser; ?>">

                <!-- I. RESPIRASI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">air</i>
                        <h2>I. RESPIRASI</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo nicuItem('Tidak Memerlukan Ventilasi Mekanik Atau CPAP',            'respirasi1', $data['respirasi1']);
                        echo nicuItem('Saturasi O&#x2082; &ge; 90% Tanpa Oksigen Tambahan',       'respirasi2', $data['respirasi2']);
                        echo nicuItem('Tidak Ada Apnea Atau Bradikardia Dalam 5&ndash;7 Hari Terakhir', 'respirasi3', $data['respirasi3']);
                        ?>
                    </div>
                </div>

                <!-- II. KARDIOVASKULAR -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>II. KARDIOVASKULAR</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo nicuItem('Tekanan Darah Stabil Sesuai Usia',                  'kardio1', $data['kardio1']);
                        echo nicuItem('Tidak Ada Episode Syok Dalam 5 Hari Terakhir',      'kardio2', $data['kardio2']);
                        ?>
                    </div>
                </div>

                <!-- III. NUTRISI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">restaurant</i>
                        <h2>III. NUTRISI</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo nicuItem('Asupan Oral Penuh (ASI/PMK) Tanpa Distress',        'nutrisi1', $data['nutrisi1']);
                        echo nicuItem('Tidak Memerlukan Nutrisi Parenteral',                'nutrisi2', $data['nutrisi2']);
                        echo nicuItem('Berat Badan Stabil/Meningkat &ge; 15 g/Kg/Hari',   'nutrisi3', $data['nutrisi3']);
                        ?>
                    </div>
                </div>

                <!-- IV. SUHU TUBUH -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">thermostat</i>
                        <h2>IV. SUHU TUBUH</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo nicuItem('Suhu Stabil (36,5&ndash;37,5&#x2103;) Di Ruang/Incubator Terbuka', 'suhutubuh1', $data['suhutubuh1']);
                        echo nicuItem('Tidak Ada Episode Hipotermia/Hipertermia 3 Hari Terakhir',          'suhutubuh2', $data['suhutubuh2']);
                        ?>
                    </div>
                </div>

                <!-- V. INFEKSI & MONITORING -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">biotech</i>
                        <h2>V. INFEKSI &amp; MONITORING</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo nicuItem('Tidak Memerlukan Monitoring Invasif',              'infeksi1', $data['infeksi1']);
                        echo nicuItem('Semua Terapi/Tindakan Invasif Telah Selesai',     'infeksi2', $data['infeksi2']);
                        echo nicuItem('Tidak Ada Tanda Infeksi Aktif',                   'infeksi3', $data['infeksi3']);
                        ?>
                    </div>
                </div>

                <!-- VI. KEPUTUSAN & KETERANGAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">gavel</i>
                        <h2>VI. KEPUTUSAN &amp; KETERANGAN</h2>
                    </div>
                    <div class="keputusan-row">
                        <div>
                            <label style="font-size:13px;font-weight:600;">Keputusan :</label>
                            <select name="keputusan" class="keputusan-select" style="margin-top:4px;">
                                <?php foreach($keputusanOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['keputusan'] === $opt) ? 'selected' : ''; ?>>
                                    <?php echo $opt; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:13px;font-weight:600;">Keterangan/Catatan :</label>
                            <input type="text" name="keterangan" class="keterangan-input" style="margin-top:4px;"
                                   value="<?php echo htmlspecialchars($data['keterangan']); ?>"
                                   placeholder="Isi keterangan jika ada...">
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliChecklistKeluarNICU()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($isEdit): ?>
            <button type="button" id="btn-delete-knicu" class="btn btn-danger" onclick="confirmDeleteKeluarNICU()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-knicu" form="formChecklistKeluarNICU" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL_KNICU; ?>/js/kriteriakeluarnicu.js?v=<?php echo time(); ?>"></script>