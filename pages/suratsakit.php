<?php
define('BASE_URL', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';

$no_rawat    = '';
$no_rkm_medis = '';

if(!empty($encrypted_norawat)) $no_rawat     = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
if(!empty($encrypted_norm))    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');

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
$queryCheck = bukaquery("SELECT * FROM suratsakit WHERE no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;

// Generate no_surat otomatis jika baru
$no_surat_default = 'SKS' . date('YmdHis');

// Data default
$data = array(
    'no_surat'     => $no_surat_default,
    'no_rawat'     => $no_rawat,
    'tanggalawal'  => date('Y-m-d'),
    'tanggalakhir' => date('Y-m-d'),
    'lamasakit'    => '1 (Satu)'
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
            <i class="material-icons">sick</i>
            SURAT KETERANGAN SAKIT
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

    <div class="form-card">
        <div class="form-content">
            <form id="formSuratSakit" method="post" action="">
                <input type="hidden" name="no_rawat"  value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">description</i>
                        <h2>Data Surat Keterangan Sakit</h2>
                    </div>

                    <!-- Baris 1: No.Rawat | No.RM | Nama Pasien -->
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>No. Rawat</label>
                            <input type="text" value="<?php echo $no_rawat; ?>" readonly style="background:#f1f5f9; color:#475569;">
                        </div>
                        <div class="form-group">
                            <label>No. RM</label>
                            <input type="text" value="<?php echo $no_rkm_medis; ?>" readonly style="background:#f1f5f9; color:#475569;">
                        </div>
                        <div class="form-group">
                            <label>Nama Pasien</label>
                            <input type="text" value="<?php echo strtoupper($rsPasien['nm_pasien']); ?>" readonly style="background:#f1f5f9; color:#475569;">
                        </div>
                    </div>

                    <!-- Baris 2: No.Surat | Dari Tanggal | s.d. | Lama Sakit -->
                    <div class="form-grid cols-4">
                        <div class="form-group">
                            <label>No. Surat Sakit</label>
                            <input type="text" name="no_surat" value="<?php echo htmlspecialchars($data['no_surat']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Dari Tanggal</label>
                            <input type="date" name="tanggalawal" id="tanggalawal"
                                   value="<?php echo $data['tanggalawal']; ?>"
                                   onchange="hitungLamaSakit()">
                        </div>
                        <div class="form-group">
                            <label>s.d. Tanggal</label>
                            <input type="date" name="tanggalakhir" id="tanggalakhir"
                                   value="<?php echo $data['tanggalakhir']; ?>"
                                   onchange="hitungLamaSakit()">
                        </div>
                        <div class="form-group">
                            <label>Lama Sakit</label>
                            <input type="text" name="lamasakit" id="lamasakit"
                                   value="<?php echo htmlspecialchars($data['lamasakit']); ?>"
                                   readonly style="background:#f0fdf4; color:#166534; font-weight:600;">
                        </div>
                    </div>

                    <!-- Baris 3: DPJP -->
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>DPJP</label>
                            <input type="text" value="<?php echo htmlspecialchars($nm_dokter_login); ?>" readonly
                                   style="background:#f1f5f9; color:#475569; font-weight:500;"
                                   title="Dokter Penanggung Jawab Pasien">
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
            <button type="button" id="btn-delete" class="btn btn-danger" onclick="confirmDeleteSuratSakit()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save" form="formSuratSakit" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
            </button>
        </div>
    </div>
</div>

<script>
const API_PROSES2_URL = '<?php echo BASE_URL; ?>/pages/proses2.php';
</script>
<script src="<?php echo BASE_URL; ?>/js/suratsakit.js?v=<?php echo time(); ?>"></script>
