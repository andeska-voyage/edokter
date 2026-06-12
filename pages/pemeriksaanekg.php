<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
if (!defined('BASE_URL')) {
    define('BASE_URL', APP_BASE_URL);
}

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
                            rp.no_rawat,
                            rp.no_rkm_medis,
                            rp.tgl_registrasi,
                            rp.jam_reg,
                            p.nm_pasien,
                            p.jk,
                            p.tmp_lahir,
                            p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                            d.nm_dokter,
                            d.kd_dokter
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                        WHERE rp.no_rawat = '$no_rawat'");

$rsPasien = mysqli_fetch_array($queryPasien);

if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.location.href='?act=Pasien';</script>";
    exit;
}

// Cek apakah sudah ada data
$queryCheck = bukaquery("SELECT * FROM hasil_pemeriksaan_ekg WHERE no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;

// Ambil kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Data default
$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'diagnosa_klinis' => '',
    'kiriman_dari' => '',
    'irama' => '',
    'laju_jantung' => '',
    'gelombangp' => '',
    'intervalpr' => '',
    'axis' => '',
    'kompleksqrs' => '',
    'segmenst' => 'Normal',
    'gelombangt' => 'Normal',
    'kesimpulan' => ''
);

// Jika edit, gunakan data yang ada
if($isEdit) {
    $data = array_merge($data, $rsCheck);
}

// Get images
$images = array();
if($isEdit) {
    $queryImages = bukaquery("SELECT photo FROM hasil_pemeriksaan_ekg_gambar WHERE no_rawat = '$no_rawat'");
    while($row = mysqli_fetch_array($queryImages)) {
        $images[] = $row['photo'];
    }
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template2.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/usg_gallery.css?v=<?php echo time(); ?>">
<script>
// Detect standalone vs embedded mode
(function() {
    var isEmbedded = !!document.getElementById('rmeTabAjaxContainer');
    if (!isEmbedded) {
        // Standalone: apply full-screen layout (body/html lock scroll)
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
    <!-- Sticky Patient Header -->
    <div class="patient-header">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
            <!-- Left: Title + Icon -->
            <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                <i class="material-icons" style="font-size: 22px;">monitor</i>
                <h2 style="margin: 0; font-size: 15px; font-weight: 700; white-space: nowrap;">
                    PEMERIKSAAN EKG
                </h2>
            </div>
            
            <!-- Center: Patient Info -->
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
            
            <!-- Right: Badge -->
            <div style="flex-shrink: 0;">
                <span class="mode-badge <?php echo $isEdit ? 'mode-edit' : 'mode-add'; ?>">
                    <?php echo $isEdit ? '✏️ EDIT' : '➕ NEW'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Form Wrapper -->
    <div class="form-wrapper">
        <!-- Modern Tabs Navigation -->
        <div class="modern-tabs">
            <button class="tab-item active" onclick="switchTabEKG(0)">
                <i class="material-icons">description</i> Form Pemeriksaan
                <span class="tab-badge" id="badge-ekg-0" style="display:none;"></span>
            </button>
            <button class="tab-item" onclick="switchTabEKG(1)">
                <i class="material-icons">image</i> Gambar EKG
                <span class="tab-badge" id="badge-ekg-1" style="display:none;"><?php echo count($images); ?></span>
            </button>
        </div>

        <!-- Form with scroll wrapper -->
        <form id="formEKG" method="post" action="">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
            
            <!-- Form Content Wrapper -->
            <div class="form-content-wrapper">
        <!-- TAB 0: FORM PEMERIKSAAN -->
        <div class="tab-content active" id="tab-ekg-0">
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
                        <label style="font-size: 11px; margin-bottom: 3px;">Diagnosis Klinis</label>
                        <input type="text" class="form-control-modern" name="diagnosa_klinis" 
                               value="<?php echo $data['diagnosa_klinis']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 3: Irama, Laju Jantung -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Irama</label>
                        <input type="text" class="form-control-modern" name="irama" 
                               value="<?php echo $data['irama']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Laju Jantung</label>
                        <input type="text" class="form-control-modern" name="laju_jantung" 
                               value="<?php echo $data['laju_jantung']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 4: Gelombang P, Interval PR -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Gelombang P</label>
                        <input type="text" class="form-control-modern" name="gelombangp" 
                               value="<?php echo $data['gelombangp']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Interval PR</label>
                        <input type="text" class="form-control-modern" name="intervalpr" 
                               value="<?php echo $data['intervalpr']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 5: Axis, Kompleks QRS -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Axis</label>
                        <input type="text" class="form-control-modern" name="axis" 
                               value="<?php echo $data['axis']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Kompleks QRS</label>
                        <input type="text" class="form-control-modern" name="kompleksqrs" 
                               value="<?php echo $data['kompleksqrs']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 6: Segmen ST, Gelombang T -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Segmen ST</label>
                        <select class="form-control-modern" name="segmenst" style="padding: 6px 10px; font-size: 12px;">
                            <option value="Normal" <?php echo ($data['segmenst'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Tidak Normal" <?php echo ($data['segmenst'] == 'Tidak Normal') ? 'selected' : ''; ?>>Tidak Normal</option>
                        </select>
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Gelombang T</label>
                        <select class="form-control-modern" name="gelombangt" style="padding: 6px 10px; font-size: 12px;">
                            <option value="Normal" <?php echo ($data['gelombangt'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Tidak Normal" <?php echo ($data['gelombangt'] == 'Tidak Normal') ? 'selected' : ''; ?>>Tidak Normal</option>
                        </select>
                    </div>
                </div>

                <!-- Row 7: Kesimpulan -->
                <div class="form-group-modern" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 3px;">Kesimpulan</label>
                    <textarea class="form-control-modern" name="kesimpulan" rows="4" style="padding: 6px 10px; font-size: 12px;"><?php echo $data['kesimpulan']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- TAB 1: GAMBAR EKG -->
        <div class="tab-content" id="tab-ekg-1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;"><i class="material-icons">photo_library</i> Gambar EKG</h3>
                <div>
                    <input type="file" id="manual-upload-ekg" accept="image/*" multiple style="display:none;" onchange="uploadManualEKG(this)">
                    <button type="button" onclick="document.getElementById('manual-upload-ekg').click()"
                            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1976D2;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;">
                        <i class="material-icons" style="font-size:18px;">cloud_upload</i> Upload Gambar
                    </button>
                </div>
            </div>
            <div id="image-gallery-ekg"><div class="loading-spinner"><i class="material-icons">info</i> Klik tab untuk memuat gambar...</div></div>
        </div>

        </div><!-- End form-content-wrapper -->

        <!-- Action Buttons -->
        <div class="action-buttons">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="progress-indicator">
                    <div class="progress-dot" id="dot-ekg-0"></div>
                    <div class="progress-dot" id="dot-ekg-1"></div>
                </div>
                <span style="font-size: 12px; color: #666; font-weight: 600;">
                    Tab <span id="current-tab-number-ekg">1</span> dari 2
                </span>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-modern btn-secondary-modern" onclick="kembaliEKG()">
                    <i class="material-icons" style="font-size: 16px;">arrow_back</i>
                    KEMBALI
                </button>
                <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev-ekg" onclick="previousTabEKG()" style="display: none;">
                    <i class="material-icons" style="font-size: 16px;">navigate_before</i>
                    SEBELUMNYA
                </button>
                <button type="button" class="btn-modern btn-primary-modern" id="btn-next-ekg" onclick="nextTabEKG()">
                    SELANJUTNYA
                    <i class="material-icons" style="font-size: 16px;">navigate_next</i>
                </button>
                <button type="submit" name="btnSimpan" class="btn-modern btn-primary-modern" id="btn-save-ekg" form="formEKG" style="display: none;">
                    <i class="material-icons" style="font-size: 16px;">save</i>
                    SIMPAN DATA
                </button>
                <button type="button" name="btnHapus" class="btn-modern btn-danger-modern" id="btn-delete-ekg" style="display: none;" 
                        onclick="confirmDeleteEKG()" <?php echo !$isEdit ? 'disabled' : ''; ?>>
                    <i class="material-icons" style="font-size: 16px;">delete</i>
                    HAPUS DATA
                </button>
            </div>
        </div>
        </form>
    </div><!-- End form-wrapper -->
    

    <!-- Image Modal -->
    <div id="imageModalEKG" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9);" onclick="closeImageModalEKG()">
        <span style="position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
        <img id="modalImageEKG" style="margin: auto; display: block; max-width: 90%; max-height: 90%; object-fit: contain; margin-top: 50px;">
    </div>
</div>

<!-- Load EKG JS: inline untuk kompatibilitas AJAX inject -->
<script>
(function() {
    // Cek apakah pemeriksaanekg.js sudah pernah diload
    if (window._ekgJsLoaded) {
        // Sudah ada, tinggal re-init
        console.log('[EKG] JS already loaded, re-initializing...');
        if (typeof window._ekgInit === 'function') {
            setTimeout(window._ekgInit, 300);
        }
        return;
    }

    // Load via dynamic script element (works both standalone & AJAX)
    var script = document.createElement('script');
    script.src = '<?php echo BASE_URL; ?>/js/pemeriksaanekg.js?v=<?php echo time(); ?>';
    script.onload = function() {
        console.log('[EKG] JS loaded successfully via dynamic script');
        window._ekgJsLoaded = true;
    };
    script.onerror = function() {
        console.error('[EKG] Failed to load JS file');
    };
    document.head.appendChild(script);
})();
</script>