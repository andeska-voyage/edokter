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

// Handle DELETE request - via AJAX di pemeriksaanusgkandungan.js (hapusData -> proses2.php)

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
$queryCheck = bukaquery("SELECT * FROM hasil_pemeriksaan_usg WHERE no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_assoc($queryCheck);
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
    'kiriman_dari' => '',
    'diagnosa_klinis' => '',
    'hta' => '',
    'jenis_prestasi' => '',
    'kantong_gestasi' => '',
    'ukuran_bokongkepala' => '',
    'diameter_biparietal' => '',
    'panjang_femur' => '',
    'lingkar_abdomen' => '',
    'tafsiran_berat_janin' => '',
    'usia_kehamilan' => '',
    'plasenta_berimplatansi' => '',
    'derajat_maturitas' => '',
    'jumlah_air_ketuban' => '',
    'peluang_sex' => '-',
    'indek_cairan_ketuban' => '',
    'kelainan_kongenital' => '',
    'kesimpulan' => ''
);

// Jika edit, gunakan data yang ada
if($isEdit) {
    $data = array_merge($data, $rsCheck);
}

// Get images
$images = array();
if($isEdit) {
    $queryImages = bukaquery("SELECT photo FROM hasil_pemeriksaan_usg_gambar WHERE no_rawat = '$no_rawat'");
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
                <i class="material-icons" style="font-size: 22px;">pregnant_woman</i>
                <h2 style="margin: 0; font-size: 15px; font-weight: 700; white-space: nowrap;">
                    PEMERIKSAAN USG KANDUNGAN
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
            <button class="tab-item active" onclick="switchTabUSG(0)">
                <i class="material-icons">description</i> Form Pemeriksaan
                <span class="tab-badge" id="badge-usg-0" style="display:none;"></span>
            </button>
            <button class="tab-item" onclick="switchTabUSG(1)">
                <i class="material-icons">image</i> Gambar USG
                <span class="tab-badge" id="badge-usg-1" style="display:none;"><?php echo count($images); ?></span>
            </button>
        </div>

        <!-- Form with scroll wrapper -->
        <form id="formUSG" method="post" action="">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
            
            <!-- Form Content Wrapper -->
            <div class="form-content-wrapper">
        <!-- TAB 0: FORM PEMERIKSAAN -->
        <div class="tab-content active" id="tab-usg-0">
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

                <!-- Row 3: HTA, Jenis Prestasi -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">HTA</label>
                        <input type="text" class="form-control-modern" name="hta" 
                               value="<?php echo $data['hta']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Jenis Prestasi</label>
                        <input type="text" class="form-control-modern" name="jenis_prestasi" 
                               value="<?php echo $data['jenis_prestasi']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 4: GS, CRL, DBP -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Ukuran Kantong Gestasi (GS)</label>
                        <input type="text" class="form-control-modern" name="kantong_gestasi" 
                               value="<?php echo $data['kantong_gestasi']; ?>" placeholder="mm" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Ukuran Bokong-Kepala (CRL)</label>
                        <input type="text" class="form-control-modern" name="ukuran_bokongkepala" 
                               value="<?php echo $data['ukuran_bokongkepala']; ?>" placeholder="mm" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Diameter Biparietal (DBP)</label>
                        <input type="text" class="form-control-modern" name="diameter_biparietal" 
                               value="<?php echo $data['diameter_biparietal']; ?>" placeholder="mm" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 5: FL, AC, TBJ -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Panjang Femur (FL)</label>
                        <input type="text" class="form-control-modern" name="panjang_femur" 
                               value="<?php echo $data['panjang_femur']; ?>" placeholder="mm" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Lingkar Abdomen (AC)</label>
                        <input type="text" class="form-control-modern" name="lingkar_abdomen" 
                               value="<?php echo $data['lingkar_abdomen']; ?>" placeholder="mm" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Tafsiran Berat Janin (TBJ)</label>
                        <input type="text" class="form-control-modern" name="tafsiran_berat_janin" 
                               value="<?php echo $data['tafsiran_berat_janin']; ?>" placeholder="gram" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 6: Usia Kehamilan, Plasenta -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Usia Kehamilan Sesual</label>
                        <input type="text" class="form-control-modern" name="usia_kehamilan" 
                               value="<?php echo $data['usia_kehamilan']; ?>" placeholder="minggu" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Plasenta Berimplantasi Di</label>
                        <input type="text" class="form-control-modern" name="plasenta_berimplatansi" 
                               value="<?php echo $data['plasenta_berimplatansi']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 7: Maturitas, Jumlah Air, Sex -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Derajat Maturitas Plasenta</label>
                        <select class="form-control-modern" name="derajat_maturitas" style="padding: 6px 10px; font-size: 12px;">
                            <!-- <option value="">-- Pilih --</option> -->
                            <option value="0" <?php echo ($data['derajat_maturitas'] == '0') ? 'selected' : ''; ?>>0</option>
                            <option value="1" <?php echo ($data['derajat_maturitas'] == '1') ? 'selected' : ''; ?>>1</option>
                            <option value="2" <?php echo ($data['derajat_maturitas'] == '2') ? 'selected' : ''; ?>>2</option>
                            <option value="3" <?php echo ($data['derajat_maturitas'] == '3') ? 'selected' : ''; ?>>3</option>
                        </select>
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Jumlah Air Ketuban</label>
                        <select class="form-control-modern" name="jumlah_air_ketuban" style="padding: 6px 10px; font-size: 12px;">
                            <!-- <option value="">-- Pilih --</option> -->
                            <option value="Cukup" <?php echo ($data['jumlah_air_ketuban'] == 'Cukup') ? 'selected' : ''; ?>>Cukup</option>
                            <option value="Berkurang" <?php echo ($data['jumlah_air_ketuban'] == 'Berkurang') ? 'selected' : ''; ?>>Berkurang</option>
                        </select>
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Peluang Sex</label>
                        <select class="form-control-modern" name="peluang_sex" style="padding: 6px 10px; font-size: 12px;">
                            <option value="-" <?php echo ($data['peluang_sex'] == '-') ? 'selected' : ''; ?>>-</option>
                            <option value="Laki-laki" <?php echo ($data['peluang_sex'] == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="Perempuan" <?php echo ($data['peluang_sex'] == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                </div>

                <!-- Row 8: ICK, Kelainan Kongenital -->
                <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 10px; margin-bottom: 10px;">
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Indeks Cairan Ketuban (ICK)</label>
                        <input type="text" class="form-control-modern" name="indek_cairan_ketuban" 
                               value="<?php echo $data['indek_cairan_ketuban']; ?>" placeholder="cm" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label style="font-size: 11px; margin-bottom: 3px;">Kelainan Kongenital Mayor</label>
                        <input type="text" class="form-control-modern" name="kelainan_kongenital" 
                               value="<?php echo $data['kelainan_kongenital']; ?>" style="padding: 6px 10px; font-size: 12px;">
                    </div>
                </div>

                <!-- Row 9: Kesimpulan -->
                <div class="form-group-modern" style="margin-bottom: 0;">
                    <label style="font-size: 11px; margin-bottom: 3px;">Kesimpulan</label>
                    <textarea class="form-control-modern" name="kesimpulan" rows="4" style="padding: 6px 10px; font-size: 12px;"><?php echo $data['kesimpulan']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- TAB 1: GAMBAR USG -->
        <div class="tab-content" id="tab-usg-1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;"><i class="material-icons">photo_library</i> Gambar USG Kandungan</h3>
                <div>
                    <input type="file" id="manual-upload-usg" accept="image/*" multiple style="display:none;" onchange="uploadManualUSG(this)">
                    <button type="button" onclick="document.getElementById('manual-upload-usg').click()"
                            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1976D2;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;">
                        <i class="material-icons" style="font-size:18px;">cloud_upload</i> Upload Gambar
                    </button>
                </div>
            </div>
            <div id="image-gallery-usg"><div class="loading-spinner"><i class="material-icons">info</i> Klik tab untuk memuat gambar...</div></div>
        </div><!-- End tab-content -->
        
        </div><!-- End form-content-wrapper -->
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="progress-indicator">
                    <div class="progress-dot" id="dot-usg-0"></div>
                    <div class="progress-dot" id="dot-usg-1"></div>
                </div>
                <span style="font-size: 12px; color: #666; font-weight: 600;">
                    Tab <span id="current-tab-number-usg">1</span> dari 2
                </span>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-modern btn-secondary-modern" onclick="kembaliUSG()">
                    <i class="material-icons" style="font-size: 16px;">arrow_back</i>
                    KEMBALI
                </button>
                <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev-usg" onclick="previousTabUSG()" style="display: none;">
                    <i class="material-icons" style="font-size: 16px;">navigate_before</i>
                    SEBELUMNYA
                </button>
                <button type="button" class="btn-modern btn-primary-modern" id="btn-next-usg" onclick="nextTabUSG()">
                    SELANJUTNYA
                    <i class="material-icons" style="font-size: 16px;">navigate_next</i>
                </button>
                <button type="submit" name="btnSimpan" class="btn-modern btn-primary-modern" id="btn-save-usg" style="display: none;">
                    <i class="material-icons" style="font-size: 16px;">save</i>
                    SIMPAN DATA
                </button>
                <button type="button" name="btnHapus" class="btn-modern btn-danger-modern" id="btn-delete-usg" style="display: none;" 
                        onclick="confirmDeleteUSG()" <?php echo !$isEdit ? 'disabled' : ''; ?>>
                    <i class="material-icons" style="font-size: 16px;">delete</i>
                    HAPUS DATA
                </button>
            </div>
        </div>
        </form>
    </div><!-- End form-wrapper -->
    
    <!-- Image Modal -->
    <div id="imageModalUSG" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.9);" onclick="closeImageModalUSG()">
        <span style="position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
        <img id="modalImageUSG" style="margin: auto; display: block; max-width: 90%; max-height: 90%; object-fit: contain; margin-top: 50px;">
    </div>
</div>
<!-- PHP Config for JavaScript -->
<script>
// Pass PHP constants to JavaScript
const APP_BASE_URL = '<?php echo defined("APP_BASE_URL") ? APP_BASE_URL : "/edokter"; ?>';
</script>


<script>
(function() {
    if (window._usgKandunganJsLoaded) {
        console.log('[USG] JS already loaded, re-initializing...');
        if (typeof window._usgKandunganInit === 'function') {
            setTimeout(window._usgKandunganInit, 300);
        }
        return;
    }
    var script = document.createElement('script');
    script.src = '<?php echo BASE_URL; ?>/js/pemeriksaanusgkandungan.js?v=<?php echo time(); ?>';
    script.onload = function() {
        console.log('[USG] JS loaded successfully via dynamic script');
        window._usgKandunganJsLoaded = true;
    };
    script.onerror = function() {
        console.error('[USG] Failed to load JS file');
    };
    document.head.appendChild(script);
})();
</script>