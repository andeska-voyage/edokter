<?php
define('BASE_URL', APP_BASE_URL);

// Decrypt parameter dari URL
$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';

$no_rawat    = '';
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
$queryCheck = bukaquery("SELECT * FROM surat_skbn WHERE no_rawat = '$no_rawat'");
$rsCheck    = mysqli_fetch_array($queryCheck);
$isEdit     = ($rsCheck) ? true : false;

// Generate no_surat otomatis jika baru
$no_surat_default = 'SKBN' . date('YmdHis');

// Data default
$data = array(
    'no_surat'      => $no_surat_default,
    'no_rawat'      => $no_rawat,
    'tanggalsurat'  => date('Y-m-d'),
    'kategori'      => 'UMUM',
    'kd_dokter'     => $kd_dokter_login,
    'keperluan'     => '',
    'opiat'         => 'NEGATIF',
    'ganja'         => 'NEGATIF',
    'amphetamin'    => 'NEGATIF',
    'methamphetamin'=> 'NEGATIF',
    'benzodiazepin' => 'NEGATIF',
    'cocain'        => 'NEGATIF',
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
            <i class="material-icons">science</i>
            SURAT KETERANGAN BEBAS NARKOBA
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
            <form id="formKeteranganBebasNarkoba" method="post" action="">
                <input type="hidden" name="no_rawat"    value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="no_rkm_medis" value="<?php echo $no_rkm_medis; ?>">

                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">description</i>
                        <h2>Data Surat Keterangan Bebas Narkoba</h2>
                    </div>

                    <!-- Baris 1: No. Surat, Tanggal Surat, Kategori SKBN -->
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>No. Surat</label>
                            <input type="text" name="no_surat" value="<?php echo htmlspecialchars($data['no_surat']); ?>" required maxlength="25">
                        </div>
                        <div class="form-group">
                            <label>Tanggal Surat</label>
                            <input type="date" name="tanggalsurat" value="<?php echo date('Y-m-d', strtotime($data['tanggalsurat'])); ?>">
                        </div>
                        <div class="form-group">
                            <label>Kategori SKBN</label>
                            <select name="kategori">
                                <option value="UMUM"   <?php echo ($data['kategori'] == 'UMUM')   ? 'selected' : ''; ?>>UMUM</option>
                                <option value="POLRI"  <?php echo ($data['kategori'] == 'POLRI')  ? 'selected' : ''; ?>>POLRI</option>
                                <option value="TNI"    <?php echo ($data['kategori'] == 'TNI')    ? 'selected' : ''; ?>>TNI</option>
                            </select>
                        </div>
                    </div>

                    <!-- Baris 2: Dokter & Keperluan -->
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Dokter</label>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text" value="<?php echo htmlspecialchars($kd_dokter_login); ?>" readonly
                                       style="background:#f1f5f9; color:#475569; font-weight:500; width:130px; flex-shrink:0;"
                                       title="Kode Dokter">
                                <input type="text" value="<?php echo htmlspecialchars($nm_dokter_login); ?>" readonly
                                       style="background:#f1f5f9; color:#475569; font-weight:500; flex:1;"
                                       title="Nama Dokter">
                                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Keperluan</label>
                            <input type="text" name="keperluan" value="<?php echo htmlspecialchars($data['keperluan']); ?>" maxlength="300" placeholder="Melamar pekerjaan, dll">
                        </div>
                    </div>

                    <!-- Baris 3: Hasil Pemeriksaan Narkoba -->
                    <div class="section-header" style="margin-top:16px; margin-bottom:12px;">
                        <i class="material-icons">biotech</i>
                        <h2>Hasil Pemeriksaan Narkoba</h2>
                    </div>

                    <div class="form-grid cols-3">
                        <!-- Opiat / Morphin -->
                        <div class="form-group">
                            <label>Opiat / Morphin</label>
                            <select name="opiat">
                                <option value="NEGATIF" <?php echo ($data['opiat'] == 'NEGATIF') ? 'selected' : ''; ?>>NEGATIF</option>
                                <option value="POSITIF" <?php echo ($data['opiat'] == 'POSITIF') ? 'selected' : ''; ?>>POSITIF</option>
                            </select>
                        </div>
                        <!-- Amphetamin -->
                        <div class="form-group">
                            <label>Amphetamin</label>
                            <select name="amphetamin">
                                <option value="NEGATIF" <?php echo ($data['amphetamin'] == 'NEGATIF') ? 'selected' : ''; ?>>NEGATIF</option>
                                <option value="POSITIF" <?php echo ($data['amphetamin'] == 'POSITIF') ? 'selected' : ''; ?>>POSITIF</option>
                            </select>
                        </div>
                        <!-- Benzodiazepin -->
                        <div class="form-group">
                            <label>Benzodiazepin</label>
                            <select name="benzodiazepin">
                                <option value="NEGATIF" <?php echo ($data['benzodiazepin'] == 'NEGATIF') ? 'selected' : ''; ?>>NEGATIF</option>
                                <option value="POSITIF" <?php echo ($data['benzodiazepin'] == 'POSITIF') ? 'selected' : ''; ?>>POSITIF</option>
                            </select>
                        </div>
                        <!-- Ganja / Canabis -->
                        <div class="form-group">
                            <label>Ganja / Canabis</label>
                            <select name="ganja">
                                <option value="NEGATIF" <?php echo ($data['ganja'] == 'NEGATIF') ? 'selected' : ''; ?>>NEGATIF</option>
                                <option value="POSITIF" <?php echo ($data['ganja'] == 'POSITIF') ? 'selected' : ''; ?>>POSITIF</option>
                            </select>
                        </div>
                        <!-- Methamphetamin -->
                        <div class="form-group">
                            <label>Methamphetamin</label>
                            <select name="methamphetamin">
                                <option value="NEGATIF" <?php echo ($data['methamphetamin'] == 'NEGATIF') ? 'selected' : ''; ?>>NEGATIF</option>
                                <option value="POSITIF" <?php echo ($data['methamphetamin'] == 'POSITIF') ? 'selected' : ''; ?>>POSITIF</option>
                            </select>
                        </div>
                        <!-- Cocain -->
                        <div class="form-group">
                            <label>Cocain</label>
                            <select name="cocain">
                                <option value="NEGATIF" <?php echo ($data['cocain'] == 'NEGATIF') ? 'selected' : ''; ?>>NEGATIF</option>
                                <option value="POSITIF" <?php echo ($data['cocain'] == 'POSITIF') ? 'selected' : ''; ?>>POSITIF</option>
                            </select>
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
            <button type="button" id="btn-delete" class="btn btn-danger" onclick="confirmDeleteBebasNarkoba()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save" form="formKeteranganBebasNarkoba" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/js/suratketeranganbebasnarkoba.js?v=<?php echo time(); ?>"></script>