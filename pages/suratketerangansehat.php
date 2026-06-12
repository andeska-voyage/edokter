<?php
define('BASE_URL', APP_BASE_URL);

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
    echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>";
    exit;
}

// Ambil kode dokter login
$kd_dokter_login = '';
$nm_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) {
    $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    $qDokter = bukaquery("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter_login'");
    $rsDokter = mysqli_fetch_array($qDokter);
    if($rsDokter) $nm_dokter_login = $rsDokter['nm_dokter'];
}

// Cek apakah sudah ada data
$queryCheck = bukaquery("SELECT * FROM surat_keterangan_sehat WHERE no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;

// Generate no_surat otomatis jika baru
$no_surat_default = 'SKD' . date('YmdHis');

// Data default
$data = array(
    'no_surat' => $no_surat_default,
    'no_rawat' => $no_rawat,
    'tanggalsurat' => date('Y-m-d'),
    'berat' => '',
    'tinggi' => '',
    'tensi' => '',
    'suhu' => '',
    'butawarna' => 'Tidak',
    'keperluan' => '',
    'kesimpulan' => 'Sehat'
);

if($isEdit) {
    $data = array_merge($data, $rsCheck);
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <h1 style="margin-bottom: 8px;">
            <i class="material-icons">medical_information</i>
            SURAT KETERANGAN SEHAT
            <?php if($isEdit): ?>
            <span class="mode-badge mode-edit">✏️ EDIT</span>
            <?php else: ?>
            <span class="mode-badge mode-add">➕ NEW</span>
            <?php endif; ?>
        </h1>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formKeteranganSehat" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">description</i>
                        <h2>Data Surat Keterangan Sehat</h2>
                    </div>

                    <!-- Hidden fields: No. Rawat, No. RM, Nama Pasien -->
                    <input type="hidden" name="no_rkm_medis" value="<?php echo $no_rkm_medis; ?>">

                    <!-- Baris 1: Tanggal & No. Surat -->
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Tanggal Surat</label>
                            <input type="date" name="tanggalsurat" value="<?php echo date('Y-m-d', strtotime($data['tanggalsurat'])); ?>">
                        </div>
                        <div class="form-group">
                            <label>No. Surat</label>
                            <input type="text" name="no_surat" value="<?php echo htmlspecialchars($data['no_surat']); ?>" required>
                        </div>
                    </div>

                    <!-- Baris 2: Berat Badan, Tinggi Badan, Tensi, Suhu -->
                    <div class="form-grid cols-4">
                        <div class="form-group">
                            <label>Berat Badan (Kg)</label>
                            <input type="text" name="berat" value="<?php echo htmlspecialchars($data['berat']); ?>" maxlength="5" placeholder="kg">
                        </div>
                        <div class="form-group">
                            <label>Tinggi Badan (Cm)</label>
                            <input type="text" name="tinggi" value="<?php echo htmlspecialchars($data['tinggi']); ?>" maxlength="5" placeholder="cm">
                        </div>
                        <div class="form-group">
                            <label>Tensi (mmHg)</label>
                            <input type="text" name="tensi" value="<?php echo htmlspecialchars($data['tensi']); ?>" maxlength="10" placeholder="120/80">
                        </div>
                        <div class="form-group">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu" value="<?php echo htmlspecialchars($data['suhu']); ?>" maxlength="5" placeholder="36.5">
                        </div>
                    </div>

                    <!-- Baris 3: Buta Warna, Keperluan, Kesimpulan, DPJP -->
                    <div class="form-grid cols-4">
                        <div class="form-group">
                            <label>Buta Warna</label>
                            <select name="butawarna">
                                <option value="Tidak" <?php echo ($data['butawarna'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                                <option value="Ya" <?php echo ($data['butawarna'] == 'Ya') ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Keperluan</label>
                            <input type="text" name="keperluan" value="<?php echo htmlspecialchars($data['keperluan']); ?>" maxlength="100" placeholder="Melamar pekerjaan, dll">
                        </div>
                        <div class="form-group">
                            <label>Kesimpulan</label>
                            <select name="kesimpulan">
                                <option value="Sehat" <?php echo ($data['kesimpulan'] == 'Sehat') ? 'selected' : ''; ?>>Sehat</option>
                                <option value="Tidak Sehat" <?php echo ($data['kesimpulan'] == 'Tidak Sehat') ? 'selected' : ''; ?>>Tidak Sehat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>DPJP</label>
                            <input type="text" value="<?php echo htmlspecialchars($nm_dokter_login); ?>" readonly
                                   style="background:#f1f5f9; color:#475569; font-weight:500;"
                                   title="Dokter Penanggung Jawab Pasien">
                            <input type="hidden" name="kd_dpjp" value="<?php echo $kd_dokter_login; ?>">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="window.history.back();">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($isEdit): ?>
            <button type="button" id="btn-delete" class="btn btn-danger" onclick="confirmDeleteKeteranganSehat()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save" form="formKeteranganSehat" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/js/suratketerangansehat.js?v=<?php echo time(); ?>"></script>
