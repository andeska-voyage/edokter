<?php
/**
 * catatan_pengkajian_paska_operasi.php
 * Form Catatan Pengkajian Paska Operasi - E-Dokter SIMRS Khanza
 */
define('BASE_URL_CPO', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';
$no_rawat = ''; $no_rkm_medis = '';
if(!empty($encrypted_norawat)) { $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd'); }
if(!empty($encrypted_norm))    { $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd'); }

$queryPasien = bukaquery("SELECT 
                            rp.no_rawat, rp.no_rkm_medis,
                            p.nm_pasien, p.jk, p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        WHERE rp.no_rawat = '$no_rawat'");
$rsPasien = mysqli_fetch_array($queryPasien);
if(!$rsPasien) { echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>"; exit; }

$kd_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) { $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd'); }

// Cek data existing
$queryCheck = bukaquery("SELECT cpo.*, d.nm_dokter as nm_dokter_pengisi
                         FROM catatan_pengkajian_paska_operasi cpo
                         LEFT JOIN dokter d ON cpo.kd_dokter = d.kd_dokter
                         WHERE cpo.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;

$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'rawat_paska_operasi' => '', 'cairan' => '', 'antibiotika' => '',
    'analgetika' => '', 'medikamentosa_lain' => '', 'diet' => '',
    'pemeriksaan_laborat' => '', 'tranfusi' => '', 'lainlain' => ''
);
if($isEdit) { $data = array_merge($data, $rsCheck); }

$bolehHapus = false;
if($isEdit && $kd_dokter_login === $data['kd_dokter']) {
    $bolehHapus = true;
}

function cpoV($key) { global $data; return htmlspecialchars($data[$key] ?? ''); }
?>

<link rel="stylesheet" href="<?php echo BASE_URL_CPO; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
.cpo-ac-wrap { position:relative; }
.cpo-ac-dd { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:0 0 8px 8px; box-shadow:0 4px 12px rgba(0,0,0,.15); max-height:200px; overflow-y:auto; z-index:99; display:none; }
.cpo-ac-dd.show { display:block; }
.cpo-ac-dd div { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; font-size:12px; }
.cpo-ac-dd div:hover { background:#f0f9ff; }
.cpo-ac-dd div strong { color:#1e40af; }
.cpo-ac-dd .no-result { color:#94a3b8; text-align:center; font-style:italic; }
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">description</i>
                CATATAN PENGKAJIAN PASKA OPERASI
                <?php if($isEdit): ?><span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?><span class="mode-badge mode-add">➕ NEW</span><?php endif; ?>
            </h1>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">wc</i><strong>J.K:</strong> <?php echo ($rsPasien['jk'] == 'L') ? 'Laki-Laki' : 'Perempuan'; ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-content">
            <form id="formCatatanPengkajianPaskaOperasi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">

                <!-- Data Umum -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">event_note</i><h2>Data Umum</h2></div>
                    <div class="form-grid cols-2">
                        <div class="form-group cpo-ac-wrap">
                            <label>Dokter</label>
                            <input type="hidden" name="kd_dokter" id="cpo_kd_dokter" value="<?php echo cpoV('kd_dokter'); ?>">
                            <input type="text" id="cpo_nm_dokter" placeholder="Ketik nama dokter..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_pengisi']) ? htmlspecialchars($rsCheck['nm_dokter_pengisi']) : ''; ?>">
                            <div id="cpo_ac_dokter" class="cpo-ac-dd"></div>
                        </div>
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="hidden" name="tanggal" value="<?php echo date('Y-m-d H:i:s', strtotime($data['tanggal'])); ?>">
                            <input type="text" value="<?php echo date('d-m-Y H:i:s', strtotime($data['tanggal'])); ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Catatan Paska Operasi -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">assignment</i><h2>Catatan Paska Operasi / Tindakan</h2></div>

                    <div class="form-group">
                        <label>I. Rawat Paska Operasi / Tindakan</label>
                        <textarea name="rawat_paska_operasi" rows="3" placeholder="Rawat paska operasi / tindakan..."><?php echo cpoV('rawat_paska_operasi'); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top:8px;">
                        <label>II. Cairan</label>
                        <textarea name="cairan" rows="3" placeholder="Cairan..."><?php echo cpoV('cairan'); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top:8px;">
                        <label>III. Antibiotika</label>
                        <textarea name="antibiotika" rows="3" placeholder="Antibiotika..."><?php echo cpoV('antibiotika'); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top:8px;">
                        <label>IV. Analgetika</label>
                        <textarea name="analgetika" rows="3" placeholder="Analgetika..."><?php echo cpoV('analgetika'); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top:8px;">
                        <label>V. Medikamentosa Lain</label>
                        <textarea name="medikamentosa_lain" rows="3" placeholder="Medikamentosa lain..."><?php echo cpoV('medikamentosa_lain'); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top:8px;">
                        <label>VI. Diet</label>
                        <textarea name="diet" rows="3" placeholder="Diet..."><?php echo cpoV('diet'); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top:8px;">
                        <label>VII. Pemeriksaan Laboratorium Paska Operasi / Tindakan Invasif</label>
                        <textarea name="pemeriksaan_laborat" rows="3" placeholder="Pemeriksaan laboratorium..."><?php echo cpoV('pemeriksaan_laborat'); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top:8px;">
                        <label>VIII. Tranfusi</label>
                        <textarea name="tranfusi" rows="3" placeholder="Tranfusi..."><?php echo cpoV('tranfusi'); ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top:8px;">
                        <label>IX. Lain-lain</label>
                        <textarea name="lainlain" rows="3" placeholder="Lain-lain..."><?php echo cpoV('lainlain'); ?></textarea>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliCatatanPengkajianPaskaOperasi()"><i class="material-icons">arrow_back</i> KEMBALI</button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-cpo" class="btn btn-danger" onclick="confirmDeleteCatatanPengkajianPaskaOperasi()"><i class="material-icons">delete</i> HAPUS</button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus"><i class="material-icons">lock</i> HAPUS</button>
            <?php endif; ?>
            <button type="submit" id="btn-save-cpo" form="formCatatanPengkajianPaskaOperasi" class="btn btn-primary"><i class="material-icons">save</i> SIMPAN</button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:13px;"><strong>Info:</strong> Hanya <strong>Dokter</strong> pengisi yang dapat menghapus data ini.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL_CPO; ?>/js/catatan_pengkajian_paska_operasi.js?v=<?php echo time(); ?>"></script>
