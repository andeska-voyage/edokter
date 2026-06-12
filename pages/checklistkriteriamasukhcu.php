<?php
define('BASE_URL_HCU', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';
$no_rawat = '';
$no_rkm_medis = '';
if(!empty($encrypted_norawat)) { $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd'); }
if(!empty($encrypted_norm)) { $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd'); }

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

// Cek data existing + join pegawai
$queryCheck = bukaquery("SELECT ck.*, pg.nama as nama_pengisi 
                         FROM checklist_kriteria_masuk_hcu ck
                         LEFT JOIN pegawai pg ON ck.nik = pg.nik
                         WHERE ck.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_pengisi = ($rsCheck && !empty($rsCheck['nama_pengisi'])) ? $rsCheck['nama_pengisi'] : '';

// NIK dari session
$nikUser = '';
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
if(!empty($kd_dokter_encrypted)) {
    $nikUser = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// All enum fields
$allFields = [
    'kardiologi1','kardiologi2','kardiologi3','kardiologi4','kardiologi5','kardiologi6',
    'pernapasan1','pernapasan2','pernapasan3',
    'syaraf1','syaraf2','syaraf3','syaraf4',
    'pencernaan1','pencernaan2','pencernaan3','pencernaan4',
    'pembedahan1','pembedahan2',
    'hematologi1','hematologi2',
    'infeksi'
];

$data = ['tanggal' => date('Y-m-d H:i:s'), 'nik' => $nikUser];
foreach($allFields as $f) { $data[$f] = 'Tidak'; }
if($isEdit) { $data = array_merge($data, $rsCheck); }

// Helper - reuse icu-sel/icu-field class dari template4.css
function hcuSelect($name, $value) {
    $sT = ($value == 'Ya') ? '' : 'selected';
    $sY = ($value == 'Ya') ? 'selected' : '';
    return '<select name="'.$name.'" class="icu-sel">
                <option value="Tidak" '.$sT.'>Tidak</option>
                <option value="Ya" '.$sY.'>Ya</option>
            </select>';
}
function hcuItem($label, $name, $value) {
    return '<div class="icu-field">
                <span class="icu-label">'.$label.' :</span>
                '.hcuSelect($name, $value).'
            </div>';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_HCU; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">playlist_add_check</i>
                CHECKLIST KRITERIA MASUK HCU
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
            <form id="formChecklistHCU" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="nik" value="<?php echo $nikUser; ?>">

                <!-- I. SISTEM KARDIOLOGI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">favorite</i>
                        <h2>I. SISTEM KARDIOLOGI</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo hcuItem('Gangguan Sirkulasi Atau Pre Dan Pasca Operasi (Syok Hypovolemic)', 'kardiologi1', $data['kardiologi1']);
                        echo hcuItem('Hypertensi Emergency', 'kardiologi2', $data['kardiologi2']);
                        echo hcuItem('HR 60x/menit (Tidak Stabil Hasil EKG Gambaran Mengancam Nyawa)', 'kardiologi3', $data['kardiologi3']);
                        echo hcuItem('Gagal Jantung Acute', 'kardiologi4', $data['kardiologi4']);
                        echo hcuItem('Menggunakan Inotropik / Vasoaktif Gent', 'kardiologi5', $data['kardiologi5']);
                        echo hcuItem('MAP &lt; 60 mmHg', 'kardiologi6', $data['kardiologi6']);
                        ?>
                    </div>
                </div>

                <!-- II. SISTEM PERNAFASAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">air</i>
                        <h2>II. SISTEM PERNAFASAN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo hcuItem('R &lt;8 x/menit &gt; 25 x/menit (Adanya Gangguan Pada Ventilasi : Hypoxia / Hypercapnia / Sumbatan Jalan Nafas / Oedema Paru Acute)', 'pernapasan1', $data['pernapasan1']);
                        echo hcuItem('Oxigenisasi Tidak Cukup Dari Hasil AGD', 'pernapasan2', $data['pernapasan2']);
                        echo hcuItem('Trauma Thorax / Peumothorax', 'pernapasan3', $data['pernapasan3']);
                        ?>
                    </div>
                </div>

                <!-- III. SISTEM SYARAF -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">psychology</i>
                        <h2>III. SISTEM SYARAF</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <?php
                        echo hcuItem('Kesadaran Dengan GCS &gt;= 7', 'syaraf1', $data['syaraf1']);
                        echo hcuItem('Temperatur &lt;35 C / &gt;38 C', 'syaraf2', $data['syaraf2']);
                        echo hcuItem('Trauma Kepala Sedang - Berat', 'syaraf3', $data['syaraf3']);
                        ?>
                    </div>
                    <div class="form-grid cols-2" style="margin-top:8px;">
                        <?php
                        echo hcuItem('Kejang Yang Tidak Memerlukan Ventilator / Cerebro Vasculer / Neoromusculer / Infeksi Syaraf', 'syaraf4', $data['syaraf4']);
                        ?>
                    </div>
                </div>

                <!-- IV. SISTEM PENCERNAAN DAN ENDOKRIN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">restaurant</i>
                        <h2>IV. SISTEM PENCERNAAN DAN ENDOKRIN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo hcuItem('Gangguan Elektrolit (Na, Ca,CI, Mg, Cal) &amp; Asam Basa', 'pencernaan1', $data['pencernaan1']);
                        echo hcuItem('Hypeglikemia &amp; Hypoglikemia, Ketoasidosis Metabolic', 'pencernaan2', $data['pencernaan2']);
                        echo hcuItem('Pendarahan Saluran Pencernaan Tanpa Hypotensi &amp; Repon Dengan Cairan', 'pencernaan3', $data['pencernaan3']);
                        echo hcuItem('Pengobatan Keracunan', 'pencernaan4', $data['pencernaan4']);
                        ?>
                    </div>
                </div>

                <!-- V. PEMBEDAHAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">content_cut</i>
                        <h2>V. PEMBEDAHAN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo hcuItem('Penyulit Pasca Pembedahan : Digestif / Orthopedi / Urologi / Vasculer / Plastik / Kebidanan (Eklamsia Pre Operasi &amp; Pasca Bedah) Dll', 'pembedahan1', $data['pembedahan1']);
                        echo hcuItem('Pasca Pembedahan Hemodinamik Stabil Tetapi Masih Perlu Resusitasi Cairan', 'pembedahan2', $data['pembedahan2']);
                        ?>
                    </div>
                </div>

                <!-- VI. GANGGUAN HEMATOLOGI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">bloodtype</i>
                        <h2>VI. GANGGUAN HEMATOLOGI</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo hcuItem('Gangguan Imunologi (Reaksi Alergi, Steven Jhonson) dll', 'hematologi1', $data['hematologi1']);
                        echo hcuItem('DIC, Anemia Berat, Reaksi Penolakan Transfusi Darah', 'hematologi2', $data['hematologi2']);
                        ?>
                    </div>
                </div>

                <!-- VII. PENYAKIT INFEKSI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">coronavirus</i>
                        <h2>VII. PENYAKIT INFEKSI</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo hcuItem('Semua Infeksi Yang Menyebabkan Penurunan Kesadaran &amp; Tidak Memerlukan Ventilator : DBD Thrombositopenia, Sepsis, Tetanus, Dll', 'infeksi', $data['infeksi']);
                        ?>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliChecklistHCU()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($isEdit): ?>
            <button type="button" id="btn-delete-hcu" class="btn btn-danger" onclick="confirmDeleteHCU()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-hcu" form="formChecklistHCU" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL_HCU; ?>/js/checklistkriteriamasukhcu.js?v=<?php echo time(); ?>"></script>
