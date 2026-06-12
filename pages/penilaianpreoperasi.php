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

// Handle DELETE request - via AJAX di penilaianpreoperasi.js (hapusData -> proses3.php)

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
if(!empty($_SESSION['ses_dokter'])) {
    $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
}

// Cek apakah sudah ada data
$queryCheck = bukaquery("SELECT po.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_pre_operasi po
                         LEFT JOIN dokter d ON po.kd_dokter = d.kd_dokter
                         WHERE po.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Data default
$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'ringkasan_klinik' => '',
    'pemeriksaan_fisik' => '',
    'pemeriksaan_diagnostik' => '',
    'diagnosa_pre_operasi' => '',
    'rencana_tindakan_bedah' => '',
    'hal_hal_yang_perludi_persiapkan' => '',
    'terapi_pre_operasi' => ''
);

if($isEdit) {
    $data = array_merge($data, $rsCheck);
}

// Hak hapus
$bolehHapus = false;
if($isEdit && isset($rsCheck['kd_dokter']) && $kd_dokter_login === $rsCheck['kd_dokter']) {
    $bolehHapus = true;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="material-icons">local_hospital</i>
                PENILAIAN PRE OPERASI
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
            
            <!-- Compact Progress Bar -->
            <div style="display: flex; align-items: center; gap: 10px; background: #f8f9fa; border-radius: 8px; padding: 8px 12px;">
                <i class="material-icons" style="font-size: 18px; color: #6c757d;">assessment</i>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 11px; color: #6c757d; font-weight: 500;">Kelengkapan</span>
                        <span id="progress-text-preoperasi" style="font-weight: bold; font-size: 14px; color: #6c757d;">0%</span>
                    </div>
                    <div style="width: 150px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar-preoperasi" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-preoperasi" style="font-size: 10px; color: #6c757d; white-space: nowrap;">(0/0)</span>
            </div>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <?php if($isEdit && !empty($nama_dokter_pengisi)): ?>
            <div class="info-item"><i class="material-icons">person</i><strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPreOperasi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
                <input type="hidden" name="tanggal" value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>">

                <!-- I. Ringkasan Klinik -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">summarize</i>
                        <h2>I. Ringkasan Klinik</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="ringkasan_klinik" rows="4" placeholder="Tuliskan ringkasan klinik pasien..."><?php echo $data['ringkasan_klinik']; ?></textarea>
                    </div>
                </div>

                <!-- II. Pemeriksaan Fisik -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">accessibility</i>
                        <h2>II. Pemeriksaan Fisik</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="pemeriksaan_fisik" rows="4" placeholder="Tuliskan hasil pemeriksaan fisik..."><?php echo $data['pemeriksaan_fisik']; ?></textarea>
                    </div>
                </div>

                <!-- III. Pemeriksaan Diagnostik -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">biotech</i>
                        <h2>III. Pemeriksaan Diagnostik</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="pemeriksaan_diagnostik" rows="4" placeholder="Tuliskan hasil pemeriksaan diagnostik..."><?php echo $data['pemeriksaan_diagnostik']; ?></textarea>
                    </div>
                </div>

                <!-- IV. Diagnosa Pre Operasi -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>IV. Diagnosa Pre Operasi</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="diagnosa_pre_operasi" rows="4" placeholder="Tuliskan diagnosa pre operasi..."><?php echo $data['diagnosa_pre_operasi']; ?></textarea>
                    </div>
                </div>

                <!-- V. Rencana Tindakan Bedah -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">content_cut</i>
                        <h2>V. Rencana Tindakan Bedah</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="rencana_tindakan_bedah" rows="4" placeholder="Tuliskan rencana tindakan bedah..."><?php echo $data['rencana_tindakan_bedah']; ?></textarea>
                    </div>
                </div>

                <!-- VI. Hal-hal Yang Perlu Dipersiapkan -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">checklist</i>
                        <h2>VI. Hal-hal Yang Perlu Dipersiapkan</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="hal_hal_yang_perludi_persiapkan" rows="4" placeholder="Tuliskan hal-hal yang perlu dipersiapkan..."><?php echo $data['hal_hal_yang_perludi_persiapkan']; ?></textarea>
                    </div>
                </div>

                <!-- VII. Terapi Pre Operasi -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medication</i>
                        <h2>VII. Terapi Pre Operasi</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="terapi_pre_operasi" rows="4" placeholder="Tuliskan terapi pre operasi..."><?php echo $data['terapi_pre_operasi']; ?></textarea>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliPreOperasi()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-preoperasi" class="btn btn-danger" onclick="confirmDeletePreOperasi()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-preoperasi" form="formPreOperasi" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
            </button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 15px; display: flex; align-items: center; gap: 10px;">
            <i class="material-icons" style="color: #856404;">info</i>
            <span style="color: #856404; font-size: 14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>. 
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete via AJAX - handled by penilaianpreoperasi.js -->

<script src="<?php echo BASE_URL; ?>/js/penilaianpreoperasi.js?v=<?php echo time(); ?>"></script>
