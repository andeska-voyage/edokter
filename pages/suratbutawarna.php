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

// Cek apakah sudah ada data surat buta warna untuk no_rawat ini
$queryCheck = bukaquery("SELECT * FROM surat_buta_warna WHERE no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;

// Generate no_surat otomatis jika baru
$no_surat_default = 'SBW' . date('YmdHis');

// Data default
$data = array(
    'no_surat' => $no_surat_default,
    'no_rawat' => $no_rawat,
    'tanggalperiksa' => date('Y-m-d'),
    'hasilperiksa' => 'Tidak Buta Warna'
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
            <i class="material-icons">visibility</i>
            SURAT KETERANGAN BUTA WARNA
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
            <form id="formButaWarna" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">description</i>
                        <h2>Data Surat Keterangan Buta Warna</h2>
                    </div>

                    <!-- Baris 1: No Rawat, No RM, Nama Pasien (readonly) -->
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>No. Rawat</label>
                            <input type="text" value="<?php echo $no_rawat; ?>" readonly style="background: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>No. RM</label>
                            <input type="text" value="<?php echo $no_rkm_medis; ?>" readonly style="background: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Nama Pasien</label>
                            <input type="text" value="<?php echo strtoupper($rsPasien['nm_pasien']).' ('.$rsPasien['umur'].')'; ?>" readonly style="background: #f5f5f5;">
                        </div>
                    </div>

                    <!-- Baris 2: No Surat, Tgl Periksa, Hasil Pemeriksaan -->
                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>No. Surat</label>
                            <input type="text" name="no_surat" value="<?php echo htmlspecialchars($data['no_surat']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tgl. Periksa</label>
                            <input type="date" name="tanggalperiksa" value="<?php echo date('Y-m-d', strtotime($data['tanggalperiksa'])); ?>">
                        </div>
                        <div class="form-group">
                            <label>Hasil Pemeriksaan</label>
                            <select name="hasilperiksa">
                                <option value="Tidak Buta Warna" <?php echo ($data['hasilperiksa'] == 'Tidak Buta Warna') ? 'selected' : ''; ?>>Tidak Buta Warna</option>
                                <option value="Buta Warna" <?php echo ($data['hasilperiksa'] == 'Buta Warna') ? 'selected' : ''; ?>>Buta Warna</option>
                            </select>
                        </div>
                    </div>

                    <!-- DPJP hidden -->
                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>DPJP</label>
                            <input type="text" value="<?php echo $nm_dokter_login; ?>" readonly style="background: #f5f5f5;">
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
            <button type="button" id="btn-delete" class="btn btn-danger" onclick="confirmDeleteButaWarna()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save" form="formButaWarna" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/js/suratbutawarna.js?v=<?php echo time(); ?>"></script>
