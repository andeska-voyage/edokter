<?php
// ============================================================
// CHECKLIST KRITERIA MASUK NICU
// ============================================================
define('BASE_URL_NICU', APP_BASE_URL);

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
                         FROM checklist_kriteria_masuk_nicu ck
                         LEFT JOIN pegawai pg ON ck.nik = pg.nik
                         WHERE ck.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_pengisi = ($rsCheck && !empty($rsCheck['nama_pengisi'])) ? $rsCheck['nama_pengisi'] : '';

// Ambil NIK petugas dari session login
$nikUser = '';
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
if(!empty($kd_dokter_encrypted)) {
    $nikUser = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// All enum fields default Tidak
$allFields = [
    'respirasi1','respirasi2','respirasi3','respirasi4',
    'prematur1','prematur2','prematur3',
    'kardio1','kardio2','kardio3',
    'neuro1','neuro2','neuro3',
    'metabolik1','metabolik2','metabolik3',
    'kondisilain1','kondisilain2','kondisilain3','kondisilain4'
];

$data = [
    'tanggal'    => date('Y-m-d H:i:s'),
    'nik'        => $nikUser,
    'keputusan'  => 'Diterima Di NICU',
    'keterangan' => ''
];
foreach($allFields as $f) { $data[$f] = 'Tidak'; }
if($isEdit) { $data = array_merge($data, $rsCheck); }

// Helper select Ya/Tidak
function nicuSelect($name, $value) {
    $sT = ($value == 'Ya') ? '' : 'selected';
    $sY = ($value == 'Ya') ? 'selected' : '';
    return '<select name="'.$name.'" class="icu-sel">
                <option value="Tidak" '.$sT.'>Tidak</option>
                <option value="Ya" '.$sY.'>Ya</option>
            </select>';
}

// Helper: render satu item inline (label : select)
function nicuItem($label, $name, $value) {
    return '<div class="icu-field">
                <span class="icu-label">'.$label.' :</span>
                '.nicuSelect($name, $value).'
            </div>';
}

// Helper: select keputusan
function nicuKeputusanSelect($value) {
    $options = ['Diterima Di NICU', 'Tidak Diterima', 'Dirawat Di Ruang Perawatan Biasa'];
    $html = '<select name="keputusan" class="icu-sel" style="width:250px;font-weight:600;">';
    foreach($options as $opt) {
        $sel = ($value == $opt) ? 'selected' : '';
        $html .= '<option value="'.$opt.'" '.$sel.'>'.$opt.'</option>';
    }
    $html .= '</select>';
    return $html;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_NICU; ?>/css/template4.css?v=<?php echo time(); ?>">



<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">child_care</i>
                CHECKLIST KRITERIA MASUK NICU
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
            <form id="formChecklistNICU" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="nik" value="<?php echo $nikUser; ?>">

                <!-- I. RESPIRASI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">air</i>
                        <h2>I. RESPIRASI</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo nicuItem('Distres Napas Berat (Grunting, Retraksi, Takipnea &gt; 70x/menit)', 'respirasi1', $data['respirasi1']);
                        echo nicuItem('Apnea Berulang/Apnea Dengan Bradikardia', 'respirasi2', $data['respirasi2']);
                        echo nicuItem('Kebutuhan CPAP Atau Ventilasi Mekanik', 'respirasi3', $data['respirasi3']);
                        echo nicuItem('Saturasi O₂ &lt; 90% Dengan Oksigen Suplementasi', 'respirasi4', $data['respirasi4']);
                        ?>
                    </div>
                </div>

                <!-- II. PREMATURITAS & BERAT BADAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_weight</i>
                        <h2>II. PREMATURITAS & BERAT BADAN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo nicuItem('Berat Lahir &lt; 2000 g (Khususnya &lt; 1500 g : VLBW)', 'prematur1', $data['prematur1']);
                        echo nicuItem('Usia Kehamilan &lt; 35 minggu (Khususnya &lt; 32 minggu)', 'prematur2', $data['prematur2']);
                        echo nicuItem('Hipotermia (&lt; 36°C) Yang Tidak Membaik Dengan Penghangatan Biasa', 'prematur3', $data['prematur3']);
                        ?>
                    </div>
                </div>

                <!-- III. KONDISI KARDIOVASKULAR -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">favorite</i>
                        <h2>III. KONDISI KARDIOVASKULAR</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <?php
                        echo nicuItem('Syok Refrakter Resusitasi Awal', 'kardio1', $data['kardio1']);
                        echo nicuItem('Bradikardia Berat (&lt; 80x/menit)', 'kardio2', $data['kardio2']);
                        echo nicuItem('Sianosis Sentral Menetap', 'kardio3', $data['kardio3']);
                        ?>
                    </div>
                </div>

                <!-- IV. NEUROLOGI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">psychology</i>
                        <h2>IV. NEUROLOGI</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <?php
                        echo nicuItem('Kejang', 'neuro1', $data['neuro1']);
                        echo nicuItem('GCS/Kesadaran Bayi Sangat Rendah', 'neuro2', $data['neuro2']);
                        echo nicuItem('Ensefalopati Hipoksik Iskemik Sedang–Berat', 'neuro3', $data['neuro3']);
                        ?>
                    </div>
                </div>

                <!-- V. METABOLIK & INFEKSI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">biotech</i>
                        <h2>V. METABOLIK & INFEKSI</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo nicuItem('Hipoglikemia (&lt; 40 mg/dL) Atau Hiperglikemia Berat', 'metabolik1', $data['metabolik1']);
                        echo nicuItem('Dugaan Atau Terkonfirmasi Sepsis Neonatal', 'metabolik2', $data['metabolik2']);
                        echo nicuItem('Asidosis Metabolik Berat (pH &lt; 7,2)', 'metabolik3', $data['metabolik3']);
                        ?>
                    </div>
                </div>

                <!-- VI. KONDISI LAIN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>VI. KONDISI LAIN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo nicuItem('Kelainan Kongenital Berat Memerlukan Stabilisasi Intensif', 'kondisilain1', $data['kondisilain1']);
                        echo nicuItem('Dari Ibu Dengan Riwayat Komplikasi Perinatal Berat', 'kondisilain2', $data['kondisilain2']);
                        echo nicuItem('Pasca Operasi Besar Neonatus', 'kondisilain3', $data['kondisilain3']);
                        echo nicuItem('Ikterus Berat Memerlukan Fototerapi Intensif Atau Transfusi Tukar', 'kondisilain4', $data['kondisilain4']);
                        ?>
                    </div>
                </div>

                <!-- VII. KEPUTUSAN & KETERANGAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">gavel</i>
                        <h2>VII. KEPUTUSAN & KETERANGAN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="icu-field">
                            <span class="icu-label">Keputusan :</span>
                            <?php echo nicuKeputusanSelect($data['keputusan']); ?>
                        </div>
                        <div class="icu-field">
                            <span class="icu-label">Keterangan/Catatan :</span>
                            <input type="text" name="keterangan" class="icu-sel" style="width:100%;padding:4px 8px;border:1px solid #ccc;border-radius:4px;" value="<?php echo htmlspecialchars($data['keterangan']); ?>">
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliChecklistNICU()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($isEdit): ?>
            <button type="button" id="btn-delete-nicu" class="btn btn-danger" onclick="confirmDeleteNICU()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-nicu" form="formChecklistNICU" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL_NICU; ?>/js/kriteriamasuknicu.js?v=<?php echo time(); ?>"></script>
