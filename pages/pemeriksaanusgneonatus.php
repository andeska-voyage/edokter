<?php
if (!defined('BASE_URL')) {
    define('BASE_URL', APP_BASE_URL);
}

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

$queryCheck = bukaquery("SELECT * FROM hasil_pemeriksaan_usg_neonatus WHERE no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_assoc($queryCheck);
$isEdit = ($rsCheck) ? true : false;

$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'kiriman_dari' => '',
    'diagnosa_klinis' => '',
    'ventrikal_sinistra' => '',
    'ventrikal_dextra' => '',
    'kesan' => '',
    'kesimpulan' => '',
    'saran' => ''
);

if($isEdit) {
    $data = array_merge($data, $rsCheck);
}

$images = array();
if($isEdit) {
    $queryImages = bukaquery("SELECT photo FROM hasil_pemeriksaan_usg_neonatus_gambar WHERE no_rawat = '$no_rawat'");
    while($row = mysqli_fetch_array($queryImages)) {
        $images[] = $row['photo'];
    }
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template2.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/usg_gallery.css?v=<?php echo time(); ?>">
<script>
(function() {
    var isEmbedded = !!document.getElementById('rmeTabAjaxContainer');
    if (!isEmbedded) {
        document.documentElement.style.overflow = 'hidden';
        document.documentElement.style.height = '100vh';
        document.body.style.overflow = 'hidden';
        document.body.style.height = '100vh';
        document.body.style.margin = '0';
        document.body.style.padding = '0';
    }
})();
</script>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                <i class="material-icons" style="font-size: 22px;">child_care</i>
                <h2 style="margin: 0; font-size: 15px; font-weight: 700; white-space: nowrap;">
                    PEMERIKSAAN USG NEONATUS
                </h2>
            </div>
            
            <div style="display: flex; align-items: center; gap: 20px; flex: 1; font-size: 12px; overflow: hidden;">
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                    <i class="material-icons" style="font-size: 16px;">folder</i>
                    <strong>No. Rawat:</strong> 
                    <span><?php echo $rsPasien['no_rawat']; ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                    <i class="material-icons" style="font-size: 16px;">badge</i>
                    <strong>No. RM:</strong> 
                    <span><?php echo $rsPasien['no_rkm_medis']; ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <i class="material-icons" style="font-size: 16px;">person</i>
                    <strong>Nama:</strong> 
                    <span style="overflow: hidden; text-overflow: ellipsis;"><?php echo strtoupper($rsPasien['nm_pasien']); ?></span>
                </div>
            </div>
            
            <div style="flex-shrink: 0;">
                <span class="mode-badge <?php echo $isEdit ? 'mode-edit' : 'mode-add'; ?>">
                    <?php echo $isEdit ? '✏️ EDIT' : '➕ NEW'; ?>
                </span>
            </div>
        </div>
    </div>

    <div class="form-wrapper">
        <div class="modern-tabs">
            <button class="tab-item active" onclick="switchTabUSGNeo(0)">
                <i class="material-icons">description</i> Form Pemeriksaan
                <span class="tab-badge" id="badge-usgneo-0" style="display:none;"></span>
            </button>
            <button class="tab-item" onclick="switchTabUSGNeo(1)">
                <i class="material-icons">image</i> Gambar USG
                <span class="tab-badge" id="badge-usgneo-1" style="display:none;"><?php echo count($images); ?></span>
            </button>
        </div>

        <form id="formUSGNeo" method="post" action="">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
            
            <div class="form-content-wrapper">
        <div class="tab-content active" id="tab-usgneo-0">
            <div class="section-card" style="padding: 15px;">
                <!-- Row 1: Dokter, Tanggal -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Dokter</label>
                        <input type="text" class="form-control-modern" value="<?php echo $rsPasien['nm_dokter']; ?>" readonly style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Tanggal <span class="required">*</span></label>
                        <input type="datetime-local" class="form-control-modern" name="tanggal" required
                               value="<?php echo isset($data['tanggal']) ? date('Y-m-d\TH:i', strtotime($data['tanggal'])) : date('Y-m-d\TH:i'); ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 2: Kiriman Dari, Diagnosis Klinis -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Kiriman Dari</label>
                        <input type="text" class="form-control-modern" name="kiriman_dari" 
                               value="<?php echo $data['kiriman_dari']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Diagnosa Klinis</label>
                        <input type="text" class="form-control-modern" name="diagnosa_klinis" 
                               value="<?php echo $data['diagnosa_klinis']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Section Label -->
                <div style="font-size: 12px; font-weight: 700; color: #333; margin-bottom: 8px; padding: 6px 0; border-bottom: 1px solid #e0e0e0;">
                    Pemeriksaan Sagital & Coronal :
                </div>

                <!-- Ventrikel Sinistra -->
                <div class="form-group-modern" style="margin-bottom: 10px; padding-left: 15px;">
                    <label style="font-size: 11px; margin-bottom: 3px;">Ventrikel Sinistra</label>
                    <textarea class="form-control-modern" name="ventrikal_sinistra" rows="3" style="padding: 6px 10px; font-size: 12px;"><?php echo $data['ventrikal_sinistra']; ?></textarea>
                </div>

                <!-- Ventrikel Dextra -->
                <div class="form-group-modern" style="margin-bottom: 10px; padding-left: 15px;">
                    <label style="font-size: 11px; margin-bottom: 3px;">Ventrikel Dextra</label>
                    <textarea class="form-control-modern" name="ventrikal_dextra" rows="3" style="padding: 6px 10px; font-size: 12px;"><?php echo $data['ventrikal_dextra']; ?></textarea>
                </div>

                <!-- Kesan -->
                <div class="form-group-modern" style="margin-bottom: 10px;">
                    <label style="font-size: 11px; margin-bottom: 3px;">Kesan</label>
                    <textarea class="form-control-modern" name="kesan" rows="3" style="padding: 6px 10px; font-size: 12px;"><?php echo $data['kesan']; ?></textarea>
                </div>

                <!-- Kesimpulan -->
                <div class="form-group-modern" style="margin-bottom: 10px;">
                    <label style="font-size: 11px; margin-bottom: 3px;">Kesimpulan</label>
                    <textarea class="form-control-modern" name="kesimpulan" rows="4" style="padding: 6px 10px; font-size: 12px;"><?php echo $data['kesimpulan']; ?></textarea>
                </div>

                <!-- Saran -->
                <div class="form-group-modern" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 3px;">Saran</label>
                    <textarea class="form-control-modern" name="saran" rows="3" style="padding: 6px 10px; font-size: 12px;"><?php echo $data['saran']; ?></textarea>
                </div>
            </div>
        </div>

        <div class="tab-content" id="tab-usgneo-1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;"><i class="material-icons">photo_library</i> Gambar USG Neonatus</h3>
                <div>
                    <input type="file" id="manual-upload-usgneo" accept="image/*" multiple style="display:none;" onchange="uploadManualUSGNeo(this)">
                    <button type="button" onclick="document.getElementById('manual-upload-usgneo').click()"
                            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1976D2;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;">
                        <i class="material-icons" style="font-size:18px;">cloud_upload</i> Upload Gambar
                    </button>
                </div>
            </div>
            <div id="image-gallery-usgneo"><div class="loading-spinner"><i class="material-icons">info</i> Klik tab untuk memuat gambar...</div></div>
        </div>
        
        </div>
        
        <div class="action-buttons">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="progress-indicator">
                    <div class="progress-dot" id="dot-usgneo-0"></div>
                    <div class="progress-dot" id="dot-usgneo-1"></div>
                </div>
                <span style="font-size: 12px; color: #666; font-weight: 600;">
                    Tab <span id="current-tab-number-usgneo">1</span> dari 2
                </span>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-modern btn-secondary-modern" onclick="kembaliUSGNeo()">
                    <i class="material-icons" style="font-size: 16px;">arrow_back</i> KEMBALI
                </button>
                <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev-usgneo" onclick="previousTabUSGNeo()" style="display: none;">
                    <i class="material-icons" style="font-size: 16px;">navigate_before</i> SEBELUMNYA
                </button>
                <button type="button" class="btn-modern btn-primary-modern" id="btn-next-usgneo" onclick="nextTabUSGNeo()">
                    SELANJUTNYA <i class="material-icons" style="font-size: 16px;">navigate_next</i>
                </button>
                <button type="submit" name="btnSimpan" class="btn-modern btn-primary-modern" id="btn-save-usgneo" style="display: none;">
                    <i class="material-icons" style="font-size: 16px;">save</i> SIMPAN DATA
                </button>
                <button type="button" name="btnHapus" class="btn-modern btn-danger-modern" id="btn-delete-usgneo" style="display: none;" 
                        onclick="confirmDeleteUSGNeo()" <?php echo !$isEdit ? 'disabled' : ''; ?>>
                    <i class="material-icons" style="font-size: 16px;">delete</i> HAPUS DATA
                </button>
            </div>
        </div>
        </form>
    </div>
    
    <div id="imageModalUSGNeo" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9);" onclick="closeImageModalUSGNeo()">
        <span style="position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
        <img id="modalImageUSGNeo" style="margin: auto; display: block; max-width: 90%; max-height: 90%; object-fit: contain; margin-top: 50px;">
    </div>
</div>

<script>
const APP_BASE_URL = '<?php echo defined("APP_BASE_URL") ? APP_BASE_URL : "/edokter"; ?>';
</script>

<script>
(function() {
    if (window._usgNeoJsLoaded) {
        if (typeof window._usgNeoInit === 'function') setTimeout(window._usgNeoInit, 300);
        return;
    }
    var script = document.createElement('script');
    script.src = '<?php echo BASE_URL; ?>/js/pemeriksaanusgneonatus.js?v=<?php echo time(); ?>';
    script.onload = function() { window._usgNeoJsLoaded = true; };
    document.head.appendChild(script);
})();
</script>