<?php
// ============================================================
// CHECKLIST KRITERIA MASUK PICU
// ============================================================
define('BASE_URL_PICU', APP_BASE_URL);

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
                         FROM checklist_kriteria_masuk_picu ck
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
    'kriteriaumum1','kriteriaumum2','kriteriaumum3',
    'respirasi1','respirasi2','respirasi3','respirasi4',
    'kardio1','kardio2','kardio3','kardio4',
    'neuro1','neuro2','neuro3','neuro4',
    'bedah1','bedah2','bedah3',
    'kondisilain1','kondisilain2','kondisilain3'
];

$data = [
    'tanggal'    => date('Y-m-d H:i:s'),
    'nik'        => $nikUser,
    'keputusan'  => 'Diterima Di PICU',
    'keterangan' => ''
];
foreach($allFields as $f) { $data[$f] = 'Tidak'; }
if($isEdit) { $data = array_merge($data, $rsCheck); }

// Helper select Ya/Tidak - reuse icu-sel class dari template4.css
function picuSelect($name, $value) {
    $sT = ($value == 'Ya') ? '' : 'selected';
    $sY = ($value == 'Ya') ? 'selected' : '';
    return '<select name="'.$name.'" class="icu-sel">
                <option value="Tidak" '.$sT.'>Tidak</option>
                <option value="Ya" '.$sY.'>Ya</option>
            </select>';
}

// Helper: render satu item inline (label : select)
function picuItem($label, $name, $value) {
    return '<div class="icu-field">
                <span class="icu-label">'.$label.' :</span>
                '.picuSelect($name, $value).'
            </div>';
}

// Helper: select keputusan
function picuKeputusanSelect($value) {
    $options = ['Diterima Di PICU', 'Tidak Diterima', 'Dirawat Di Ruang Perawatan Biasa'];
    $html = '<select name="keputusan" class="icu-sel" style="width:250px;font-weight:600;">';
    foreach($options as $opt) {
        $sel = ($value == $opt) ? 'selected' : '';
        $html .= '<option value="'.$opt.'" '.$sel.'>'.$opt.'</option>';
    }
    $html .= '</select>';
    return $html;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_PICU; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">child_care</i>
                CHECKLIST KRITERIA MASUK PICU
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
            <form id="formChecklistPICU" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="nik" value="<?php echo $nikUser; ?>">

                <!-- I. KRITERIA UMUM -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">checklist</i>
                        <h2>I. KRITERIA UMUM</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo picuItem('Membutuhkan Monitoring & Terapi Intensif Secara Berkelanjutan', 'kriteriaumum1', $data['kriteriaumum1']);
                        echo picuItem('Membutuhkan Dukungan &ge; 1 Organ Vital', 'kriteriaumum2', $data['kriteriaumum2']);
                        echo picuItem('Pasien Dengan Kondisi Mengancam Jiwa Yang Masih Berpotensi Reversibel', 'kriteriaumum3', $data['kriteriaumum3']);
                        ?>
                    </div>
                </div>

                <!-- II. KRITERIA KHUSUS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_information</i>
                        <h2>II. KRITERIA KHUSUS</h2>
                    </div>

                    <!-- 1. Respirasi -->
                    <div style="margin-left:10px;margin-bottom:12px;">
                        <h3 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#344054;">1. Respirasi</h3>
                        <div class="form-grid cols-2">
                            <?php
                            echo picuItem('Gagal Napas Akut (Misal: ARDS, Status Asmatik)', 'respirasi1', $data['respirasi1']);
                            echo picuItem('Hipoksemia Berat (PaO₂ &lt; 60 mmHg Dengan FiO₂ &gt; 0,6)', 'respirasi2', $data['respirasi2']);
                            echo picuItem('Butuh Ventilasi Mekanik Invasif/Non-invasif', 'respirasi3', $data['respirasi3']);
                            echo picuItem('Hiperkarbia Berat (PaCO₂ &gt; 60 mmHg Dengan pH &lt; 7,25)', 'respirasi4', $data['respirasi4']);
                            ?>
                        </div>
                    </div>

                    <!-- 2. Kardiovaskular -->
                    <div style="margin-left:10px;margin-bottom:12px;">
                        <h3 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#344054;">2. Kardiovaskular</h3>
                        <div class="form-grid cols-2">
                            <?php
                            echo picuItem('Syok Refrakter (Septik, Kardiogenik, Hipovolemik, Anafilaksis)', 'kardio1', $data['kardio1']);
                            echo picuItem('Gagal Jantung Berat', 'kardio2', $data['kardio2']);
                            echo picuItem('Gangguan Irama Jantung Yang Mengancam Nyawa', 'kardio3', $data['kardio3']);
                            echo picuItem('Pasca Resusitasi Jantung Paru', 'kardio4', $data['kardio4']);
                            ?>
                        </div>
                    </div>

                    <!-- 3. Neurologis -->
                    <div style="margin-left:10px;margin-bottom:12px;">
                        <h3 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#344054;">3. Neurologis</h3>
                        <div class="form-grid cols-2">
                            <?php
                            echo picuItem('Trauma Kepala Berat Dengan Gangguan Hemodinamik/Napas', 'neuro1', $data['neuro1']);
                            echo picuItem('Kejang Berulang / Status Epileptikus', 'neuro2', $data['neuro2']);
                            echo picuItem('Penurunan Kesadaran (GCS &le; 8 Atau Koma)', 'neuro3', $data['neuro3']);
                            echo picuItem('Edema Serebri, Perdarahan Intrakranial', 'neuro4', $data['neuro4']);
                            ?>
                        </div>
                    </div>

                    <!-- 4. Bedah / Pasca Operasi -->
                    <div style="margin-left:10px;margin-bottom:12px;">
                        <h3 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#344054;">4. Bedah / Pasca Operasi</h3>
                        <div class="form-grid cols-2">
                            <?php
                            echo picuItem('Pasca Operasi Mayor Dengan Risiko Komplikasi Tinggi', 'bedah1', $data['bedah1']);
                            echo picuItem('Pasca Transplantasi Organ', 'bedah2', $data['bedah2']);
                            echo picuItem('Pasca Operasi Jantung / Thoraks Kompleks', 'bedah3', $data['bedah3']);
                            ?>
                        </div>
                    </div>

                    <!-- 5. Lain-lain -->
                    <div style="margin-left:10px;margin-bottom:12px;">
                        <h3 style="font-size:13px;font-weight:700;margin:0 0 6px 0;color:#344054;">5. Lain-lain</h3>
                        <div class="form-grid cols-2">
                            <?php
                            echo picuItem('Gangguan Metabolik / Elektrolit Yang Mengancam Jiwa', 'kondisilain1', $data['kondisilain1']);
                            echo picuItem('Intoksikasi Berat', 'kondisilain2', $data['kondisilain2']);
                            echo picuItem('Sepsis Berat Dengan Disfungsi Organ Multipel', 'kondisilain3', $data['kondisilain3']);
                            ?>
                        </div>
                    </div>
                </div>

                <!-- III. KEPUTUSAN & KETERANGAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">gavel</i>
                        <h2>III. KEPUTUSAN & KETERANGAN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="icu-field">
                            <span class="icu-label">Keputusan :</span>
                            <?php echo picuKeputusanSelect($data['keputusan']); ?>
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
            <button type="button" class="btn btn-secondary" onclick="kembaliChecklistPICU()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($isEdit): ?>
            <button type="button" id="btn-delete-picu" class="btn btn-danger" onclick="confirmDeletePICU()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-picu" form="formChecklistPICU" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL_PICU; ?>/js/kriteriamasukpicu.js?v=<?php echo time(); ?>"></script>
