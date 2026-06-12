<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
define('BASE_URL_ICU', APP_BASE_URL);

// Decrypt parameter dari URL
$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';

$no_rawat = '';
$no_rkm_medis = '';

if(!empty($encrypted_norawat)) {
    $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
}
if(!empty($encrypted_norm)) {
    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');
}

// Ambil data pasien
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

// Cek data existing + join pegawai untuk nama pengisi
$queryCheck = bukaquery("SELECT ck.*, pg.nama as nama_pengisi 
                         FROM checklist_kriteria_masuk_icu ck
                         LEFT JOIN pegawai pg ON ck.nik = pg.nik
                         WHERE ck.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_pengisi = ($rsCheck && !empty($rsCheck['nama_pengisi'])) ? $rsCheck['nama_pengisi'] : '';

// Ambil NIK petugas dari session login
// kd_dokter di SIMRS Khanza = NIP pegawai = nik di tabel pegawai
$nikUser = '';
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
if(!empty($kd_dokter_encrypted)) {
    $nikUser = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// All enum fields default Tidak
$allFields = [
    'prioritas1_1','prioritas1_2','prioritas1_3','prioritas1_4','prioritas1_5','prioritas1_6',
    'prioritas2_1','prioritas2_2','prioritas2_3','prioritas2_4','prioritas2_5','prioritas2_6','prioritas2_7','prioritas2_8',
    'prioritas3_1','prioritas3_2','prioritas3_3','prioritas3_4',
    'kriteria_fisiologis_tanda_vital_1','kriteria_fisiologis_tanda_vital_2','kriteria_fisiologis_tanda_vital_3','kriteria_fisiologis_tanda_vital_4','kriteria_fisiologis_tanda_vital_5',
    'kriteria_fisiologis_laborat_1','kriteria_fisiologis_laborat_2','kriteria_fisiologis_laborat_3','kriteria_fisiologis_laborat_4','kriteria_fisiologis_laborat_5','kriteria_fisiologis_laborat_6',
    'kriteria_fisiologis_radiologi_1','kriteria_fisiologis_radiologi_2',
    'kriteria_fisiologis_klinis_1','kriteria_fisiologis_klinis_2','kriteria_fisiologis_klinis_3','kriteria_fisiologis_klinis_4','kriteria_fisiologis_klinis_5','kriteria_fisiologis_klinis_6','kriteria_fisiologis_klinis_7','kriteria_fisiologis_klinis_8'
];

$data = ['tanggal' => date('Y-m-d H:i:s'), 'nik' => $nikUser];
foreach($allFields as $f) { $data[$f] = 'Tidak'; }
if($isEdit) { $data = array_merge($data, $rsCheck); }

// Helper select Ya/Tidak - compact inline
function icuSelect($name, $value) {
    $sT = ($value == 'Ya') ? '' : 'selected';
    $sY = ($value == 'Ya') ? 'selected' : '';
    return '<select name="'.$name.'" class="icu-sel">
                <option value="Tidak" '.$sT.'>Tidak</option>
                <option value="Ya" '.$sY.'>Ya</option>
            </select>';
}

// Helper: render satu item inline (label : select)
function icuItem($label, $name, $value) {
    return '<div class="icu-field">
                <span class="icu-label">'.$label.' :</span>
                '.icuSelect($name, $value).'
            </div>';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_ICU; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">playlist_add_check</i>
                CHECKLIST KRITERIA MASUK ICU
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
            <form id="formChecklistICU" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="nik" value="<?php echo $nikUser; ?>">

                <!-- I. PRIORITAS 1 -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">looks_one</i>
                        <h2>I. PRIORITAS 1</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo icuItem('Pasca Operasi Dengan Gangguan Nafas Atau Hipotensi', 'prioritas1_1', $data['prioritas1_1']);
                        echo icuItem('Gagal Nafas', 'prioritas1_2', $data['prioritas1_2']);
                        echo icuItem('Gagal Jantung Dengan Tanda Bendungan Paru', 'prioritas1_3', $data['prioritas1_3']);
                        echo icuItem('Gangguan Asam Basa / Elektrolit', 'prioritas1_4', $data['prioritas1_4']);
                        echo icuItem('Gagal Ginjal Dengan Tanda Bendungan Paru', 'prioritas1_5', $data['prioritas1_5']);
                        echo icuItem('Syok Karena Perdarahan Anafilaksis', 'prioritas1_6', $data['prioritas1_6']);
                        ?>
                    </div>
                </div>

                <!-- II. PRIORITAS 2 -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">looks_two</i>
                        <h2>II. PRIORITAS 2</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <?php
                        echo icuItem('Pasca Operasi Besar', 'prioritas2_1', $data['prioritas2_1']);
                        echo icuItem('Kejang Berulang', 'prioritas2_2', $data['prioritas2_2']);
                        echo icuItem('Gangguan Kesadaran', 'prioritas2_3', $data['prioritas2_3']);
                        echo icuItem('Dehidrasi Berat', 'prioritas2_4', $data['prioritas2_4']);
                        echo icuItem('Gangguan Jalan Nafas', 'prioritas2_5', $data['prioritas2_5']);
                        echo icuItem('Arimia Jantung', 'prioritas2_6', $data['prioritas2_6']);
                        echo icuItem('Asma Akut Berat', 'prioritas2_7', $data['prioritas2_7']);
                        echo icuItem('Diabetes Yang Memerlukan Terapi Insulin Kontinyu', 'prioritas2_8', $data['prioritas2_8']);
                        ?>
                    </div>
                </div>

                <!-- III. PRIORITAS 3 -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">looks_3</i>
                        <h2>III. PRIORITAS 3</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo icuItem('Penyakit Keganasan Dengan Metastasis', 'prioritas3_1', $data['prioritas3_1']);
                        echo icuItem('Pasien Geriatrik Dengan Fungsi Hidup Sebelumnya Minimal', 'prioritas3_2', $data['prioritas3_2']);
                        echo icuItem('Pasien Dengan GCS 3', 'prioritas3_3', $data['prioritas3_3']);
                        echo icuItem('Pasien Jantung, Penyakit Paru Terminal Disertai Komplikasi Penyakit Akut Berat', 'prioritas3_4', $data['prioritas3_4']);
                        ?>
                    </div>
                </div>

                <!-- IV. KRITERIA FISIOLOGIS TANDA-TANDA VITAL -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>IV. KRITERIA FISIOLOGIS TANDA-TANDA VITAL</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <?php
                        echo icuItem('Nadi &lt; 40 atau &gt;150 (x/menit)', 'kriteria_fisiologis_tanda_vital_1', $data['kriteria_fisiologis_tanda_vital_1']);
                        echo icuItem('SBP &lt; 80 mmHg Atau 20 mmHg Di Bawah SBP Pasien', 'kriteria_fisiologis_tanda_vital_2', $data['kriteria_fisiologis_tanda_vital_2']);
                        echo icuItem('MAP &lt; 60 mmHg', 'kriteria_fisiologis_tanda_vital_3', $data['kriteria_fisiologis_tanda_vital_3']);
                        echo icuItem('DBP &gt; 120 mmHg', 'kriteria_fisiologis_tanda_vital_4', $data['kriteria_fisiologis_tanda_vital_4']);
                        echo icuItem('R &gt; 35 x/menit', 'kriteria_fisiologis_tanda_vital_5', $data['kriteria_fisiologis_tanda_vital_5']);
                        ?>
                    </div>
                </div>

                <!-- V. KRITERIA FISIOLOGIS LABORATORIUM -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>V. KRITERIA FISIOLOGIS LABORATORIUM</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <?php
                        echo icuItem('Na &lt; 110 meq/L Atau &gt; 170 meq/L', 'kriteria_fisiologis_laborat_1', $data['kriteria_fisiologis_laborat_1']);
                        echo icuItem('Ca &gt; 15 mg/dl', 'kriteria_fisiologis_laborat_2', $data['kriteria_fisiologis_laborat_2']);
                        echo icuItem('GDS &gt; 800 mg/dl', 'kriteria_fisiologis_laborat_3', $data['kriteria_fisiologis_laborat_3']);
                        echo icuItem('K &lt; 2 meq/L Atau 7meq/L', 'kriteria_fisiologis_laborat_4', $data['kriteria_fisiologis_laborat_4']);
                        echo icuItem('PaO2 &lt; 50 mmHg', 'kriteria_fisiologis_laborat_5', $data['kriteria_fisiologis_laborat_5']);
                        echo icuItem('PH &lt; 7,1 Atau 7,7', 'kriteria_fisiologis_laborat_6', $data['kriteria_fisiologis_laborat_6']);
                        ?>
                    </div>
                </div>

                <!-- VI. KRITERIA FISIOLOGIS RADIOLOGI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">radiology</i>
                        <h2>VI. KRITERIA FISIOLOGIS RADIOLOGI</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo icuItem('Perbedaan Cerebrovaskuler, SAH, Atau Contusion Dengan Gangguan Kesadaran Atau Neorologi', 'kriteria_fisiologis_radiologi_1', $data['kriteria_fisiologis_radiologi_1']);
                        echo icuItem('Ruptor Organ Dalam, Kandung Kemih, Hati, Varices Esophagus Atau Uterus Dengan Gangguan Hemodinamik', 'kriteria_fisiologis_radiologi_2', $data['kriteria_fisiologis_radiologi_2']);
                        ?>
                    </div>
                </div>

                <!-- VII. KRITERIA FISIOLOGIS KLINIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_information</i>
                        <h2>VII. KRITERIA FISIOLOGIS KLINIS</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <?php
                        echo icuItem('Pupil Anisokor', 'kriteria_fisiologis_klinis_1', $data['kriteria_fisiologis_klinis_1']);
                        echo icuItem('Obstruksi Jalan Nafas', 'kriteria_fisiologis_klinis_2', $data['kriteria_fisiologis_klinis_2']);
                        echo icuItem('Anuria', 'kriteria_fisiologis_klinis_3', $data['kriteria_fisiologis_klinis_3']);
                        echo icuItem('Kejang Berulang', 'kriteria_fisiologis_klinis_4', $data['kriteria_fisiologis_klinis_4']);
                        echo icuItem('Tamponade Jantung', 'kriteria_fisiologis_klinis_5', $data['kriteria_fisiologis_klinis_5']);
                        echo icuItem('Coma', 'kriteria_fisiologis_klinis_6', $data['kriteria_fisiologis_klinis_6']);
                        echo icuItem('Sianosis', 'kriteria_fisiologis_klinis_7', $data['kriteria_fisiologis_klinis_7']);
                        echo icuItem('Luka Bakar &gt; 10 % BSA', 'kriteria_fisiologis_klinis_8', $data['kriteria_fisiologis_klinis_8']);
                        ?>
                    </div>
                </div>

            </form>
        </div>

        <!-- Action Bar - pakai class dari template4.css -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliChecklistICU()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($isEdit): ?>
            <button type="button" id="btn-delete-icu" class="btn btn-danger" onclick="confirmDeleteICU()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-icu" form="formChecklistICU" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL_ICU; ?>/js/checklistkriteriamasukicu.js?v=<?php echo time(); ?>"></script>
