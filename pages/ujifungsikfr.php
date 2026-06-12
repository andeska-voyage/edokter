<?php
define('BASE_URL', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';
$no_rawat          = '';
$no_rkm_medis      = '';

if (!empty($encrypted_norawat)) $no_rawat     = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
if (!empty($encrypted_norm))    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');

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

if (!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.location.href='?act=Pasien';</script>";
    exit;
}

// Cek data existing
$queryCheck = bukaquery("SELECT ukfr.*, d.nm_dokter as nama_dokter_pengisi
                         FROM uji_fungsi_kfr ukfr
                         LEFT JOIN dokter d ON ukfr.kd_dokter = d.kd_dokter
                         WHERE ukfr.no_rawat = '$no_rawat'");
$rsCheck             = mysqli_fetch_array($queryCheck);
$isEdit              = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login     = '';
if (!empty($kd_dokter_encrypted)) $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');

// Data default sesuai tabel uji_fungsi_kfr
$data = array(
    'tanggal'              => date('Y-m-d H:i:s'),
    'kd_dokter'            => $kd_dokter_login,
    'diagnosis_fungsional' => '',
    'diagnosis_medis'      => '',
    'hasil_didapat'        => '',
    'kesimpulan'           => '',
    'rekomedasi'           => '',
);

if ($isEdit) $data = array_merge($data, $rsCheck);
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">

    <!-- ===== PATIENT HEADER ===== -->
    <div class="patient-header">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <h1 style="margin:0; display:flex; align-items:center; gap:10px;">
                <i class="material-icons">biotech</i>
                UJI FUNGSI KFR
                <?php if ($isEdit): ?>
                    <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                    <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
            <div style="display:flex; align-items:center; gap:10px; background:#f8f9fa; border-radius:8px; padding:8px 12px;">
                <i class="material-icons" style="font-size:18px; color:#6c757d;">assessment</i>
                <div style="display:flex; flex-direction:column; gap:2px;">
                    <div style="display:flex; align-items:center; gap:5px;">
                        <span style="font-size:11px; color:#6c757d; font-weight:500;">Kelengkapan</span>
                        <span id="progress-text-ujikfr" style="font-weight:bold; font-size:14px; color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px; height:8px; background:#e9ecef; border-radius:4px; overflow:hidden;">
                        <div id="progress-bar-ujikfr" style="width:0%; height:100%; transition:width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-ujikfr" style="font-size:10px; color:#6c757d; white-space:nowrap;">(0/0)</span>
            </div>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <?php if ($isEdit && !empty($nama_dokter_pengisi)): ?>
            <div class="info-item"><i class="material-icons">person</i><strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== FORM CARD ===== -->
    <div class="form-card">
        <div class="form-content">
            <form id="formUjiFungsiKFR" method="post" action="">
                <input type="hidden" name="no_rawat"  value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <!-- ============================================================ -->
                <!-- INFO HEADER: Tanggal | Dokter Sp.RM                          -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">info_outline</i>
                        <h2>INFORMASI KUNJUNGAN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Tanggal &amp; Waktu</label>
                            <input type="datetime-local" name="tanggal"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Dokter</label>
                            <input type="text"
                                   value="<?php echo htmlspecialchars($rsPasien['nm_dokter']); ?>"
                                   readonly
                                   style="background:#f1f5f9; color:#6c757d; cursor:default;">
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- DIAGNOSIS                                                     -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>DIAGNOSIS</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label class="required">Diagnosis Fungsional</label>
                            <input type="text" name="diagnosis_fungsional" required
                                   value="<?php echo htmlspecialchars($data['diagnosis_fungsional']); ?>"
                                   placeholder="Tuliskan diagnosis fungsional...">
                        </div>
                        <div class="form-group">
                            <label>Diagnosis Medis</label>
                            <input type="text" name="diagnosis_medis"
                                   value="<?php echo htmlspecialchars($data['diagnosis_medis']); ?>"
                                   placeholder="Tuliskan diagnosis medis...">
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- INSTRUMEN UJI FUNGSI / PROSEDUR KFR                          -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>INSTRUMEN UJI FUNGSI / PROSEDUR KFR</h2>
                    </div>

                    <div class="form-group">
                        <label>Hasil Yang Didapat</label>
                        <input type="text" name="hasil_didapat"
                               value="<?php echo htmlspecialchars($data['hasil_didapat']); ?>"
                               placeholder="Tuliskan hasil yang didapat...">
                    </div>

                    <div class="form-group" style="margin-top:10px;">
                        <label>Kesimpulan</label>
                        <input type="text" name="kesimpulan"
                               value="<?php echo htmlspecialchars($data['kesimpulan']); ?>"
                               placeholder="Tuliskan kesimpulan...">
                    </div>

                    <div class="form-group" style="margin-top:10px;">
                        <label>Rekomendasi</label>
                        <input type="text" name="rekomedasi"
                               value="<?php echo htmlspecialchars($data['rekomedasi']); ?>"
                               placeholder="Tuliskan rekomendasi...">
                    </div>
                </div>

            </form>
        </div>

        <!-- ===== ACTION BAR ===== -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliUjiFungsiKFR()">
                <i class="material-icons">arrow_back</i>
                KEMBALI
            </button>

            <?php
            $bolehHapus = false;
            if ($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if ($kd_dokter_login === $kd_dokter_data) $bolehHapus = true;
            }
            if ($bolehHapus): ?>
            <button type="button" id="btn-delete-ujikfr" class="btn btn-danger" onclick="confirmDeleteUjiFungsiKFR()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif ($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>

            <button type="submit" id="btn-save-ujikfr" form="formUjiFungsiKFR" class="btn btn-primary">
                <i class="material-icons">save</i>
                SIMPAN DATA
            </button>
        </div>

        <?php if ($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:12px; margin-top:15px; display:flex; align-items:center; gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404; font-size:14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>.
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/js/ujifungsikfr.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>