<?php
if (!defined('BASE_URL')) define('BASE_URL', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';
$no_rawat = ''; $no_rkm_medis = '';
if(!empty($encrypted_norawat)) $no_rawat     = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
if(!empty($encrypted_norm))    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm),    'd');

$queryPasien = bukaquery("SELECT rp.no_rawat, rp.no_rkm_medis, p.nm_pasien, d.nm_dokter, d.kd_dokter,
                            CONCAT(rp.umurdaftar,' ',rp.sttsumur) as umur
                          FROM reg_periksa rp INNER JOIN pasien p ON rp.no_rkm_medis=p.no_rkm_medis
                          LEFT JOIN dokter d ON rp.kd_dokter=d.kd_dokter WHERE rp.no_rawat='$no_rawat'");
$rsPasien = mysqli_fetch_array($queryPasien);
if(!$rsPasien) { echo "<script>alert('Data pasien tidak ditemukan!');window.location.href='?act=Pasien';</script>"; exit; }

$queryCheck = bukaquery("SELECT * FROM hasil_pemeriksaan_echo WHERE no_rawat='$no_rawat'");
$rsCheck    = mysqli_fetch_assoc($queryCheck);
$isEdit     = ($rsCheck) ? true : false;

$kd_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

$fields = ['tanggal','kd_dokter','sistolik','diastolic','kontraktilitas','dimensi_ruang','katup','analisa_segmental','erap','lain_lain','kesimpulan'];
$data   = array_fill_keys($fields, '');
$data['tanggal']    = date('Y-m-d H:i:s');
$data['kd_dokter']  = $kd_dokter_login;
if($isEdit) $data = array_merge($data, $rsCheck);

$images = [];
if($isEdit) {
    $qi = bukaquery("SELECT photo FROM hasil_pemeriksaan_echo_gambar WHERE no_rawat='$no_rawat'");
    while($row = mysqli_fetch_array($qi)) $images[] = $row['photo'];
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template2.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/usg_gallery.css?v=<?php echo time(); ?>">
<script>(function(){var e=!!document.getElementById('rmeTabAjaxContainer');if(!e){document.documentElement.style.cssText='overflow:hidden;height:100vh';document.body.style.cssText='overflow:hidden;height:100vh;margin:0;padding:0';}})();</script>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;">
            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <i class="material-icons" style="font-size:22px;">favorite</i>
                <h2 style="margin:0;font-size:15px;font-weight:700;white-space:nowrap;">ECHOCARDIOGRAPHY</h2>
            </div>
            <div style="display:flex;align-items:center;gap:20px;flex:1;font-size:12px;overflow:hidden;">
                <div style="display:flex;align-items:center;gap:5px;white-space:nowrap;"><i class="material-icons" style="font-size:16px;">folder</i><strong>No. Rawat:</strong><span><?php echo $rsPasien['no_rawat']; ?></span></div>
                <div style="display:flex;align-items:center;gap:5px;white-space:nowrap;"><i class="material-icons" style="font-size:16px;">badge</i><strong>No. RM:</strong><span><?php echo $rsPasien['no_rkm_medis']; ?></span></div>
                <div style="display:flex;align-items:center;gap:5px;white-space:nowrap;overflow:hidden;"><i class="material-icons" style="font-size:16px;">person</i><strong>Nama:</strong><span style="overflow:hidden;text-overflow:ellipsis;"><?php echo strtoupper($rsPasien['nm_pasien']); ?></span></div>
            </div>
            <div style="flex-shrink:0;"><span class="mode-badge <?php echo $isEdit?'mode-edit':'mode-add'; ?>"><?php echo $isEdit?'✏️ EDIT':'➕ NEW'; ?></span></div>
        </div>
    </div>

    <div class="form-wrapper">
        <div class="modern-tabs">
            <button class="tab-item active" onclick="switchTabEcho(0)"><i class="material-icons">description</i> Form Pemeriksaan</button>
            <button class="tab-item" onclick="switchTabEcho(1)"><i class="material-icons">image</i> Gambar Echo
                <span class="tab-badge" id="badge-echo-1" <?php echo count($images)===0?'style="display:none;"':''; ?>><?php echo count($images); ?></span>
            </button>
        </div>

        <form id="formEcho" method="post" action="">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

            <div class="form-content-wrapper">

            <!-- TAB 0: FORM DATA -->
            <div class="tab-content active" id="tab-echo-0">
                <div class="section-card" style="padding:15px;">

                    <!-- Dokter & Tanggal -->
                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-bottom:12px;">
                        <div class="form-group-modern" style="margin-bottom:0;">
                            <label style="font-size:11px;margin-bottom:3px;">Dokter</label>
                            <input type="hidden" name="kd_dokter_pasien" value="<?php echo $rsPasien['kd_dokter']; ?>">
                            <input type="text" class="form-control-modern" value="<?php echo $rsPasien['nm_dokter']; ?>" readonly style="padding:6px 10px;font-size:12px;">
                        </div>
                        <div class="form-group-modern" style="margin-bottom:0;">
                            <label style="font-size:11px;margin-bottom:3px;">Tanggal <span class="required">*</span></label>
                            <input type="datetime-local" class="form-control-modern" name="tanggal" required
                                   value="<?php echo isset($data['tanggal'])?date('Y-m-d\TH:i',strtotime($data['tanggal'])):date('Y-m-d\TH:i'); ?>"
                                   style="padding:6px 10px;font-size:12px;">
                        </div>
                    </div>

                    <!-- Field-field echo -->
                    <?php
                    $echo_fields = [
                        ['name'=>'sistolik',         'label'=>'Fungsi Sistolik LV',  'max'=>30],
                        ['name'=>'diastolic',        'label'=>'Fungsi Diastolik LV', 'max'=>30],
                        ['name'=>'kontraktilitas',   'label'=>'Kontraktilitas RV',   'max'=>30],
                        ['name'=>'dimensi_ruang',    'label'=>'Dimensi Ruang Jantung','max'=>50],
                        ['name'=>'katup',            'label'=>'Katup-katup',         'max'=>50],
                        ['name'=>'analisa_segmental','label'=>'Analisa Segmental',   'max'=>100],
                        ['name'=>'erap',             'label'=>'eRAP',                'max'=>15],
                        ['name'=>'lain_lain',        'label'=>'Lain-lain',           'max'=>100],
                    ];
                    foreach($echo_fields as $ef):
                    ?>
                    <div class="form-group-modern" style="margin-bottom:8px;">
                        <label style="font-size:11px;margin-bottom:3px;"><?php echo $ef['label']; ?></label>
                        <input type="text" class="form-control-modern"
                               name="<?php echo $ef['name']; ?>"
                               value="<?php echo htmlspecialchars($data[$ef['name']]); ?>"
                               maxlength="<?php echo $ef['max']; ?>"
                               style="padding:6px 10px;font-size:12px;">
                    </div>
                    <?php endforeach; ?>

                    <!-- Kesimpulan -->
                    <div class="form-group-modern" style="margin-bottom:0;">
                        <label style="font-size:11px;margin-bottom:3px;">Kesimpulan</label>
                        <textarea class="form-control-modern" name="kesimpulan" rows="4"
                                  style="padding:6px 10px;font-size:12px;"><?php echo htmlspecialchars($data['kesimpulan']); ?></textarea>
                    </div>

                </div>
            </div>

            <!-- TAB 1: GAMBAR -->
            <div class="tab-content" id="tab-echo-1">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3 style="margin:0;"><i class="material-icons">photo_library</i> Gambar Echocardiography</h3>
                    <div>
                        <input type="file" id="manual-upload-echo" accept="image/*" multiple style="display:none;" onchange="uploadManualEcho(this)">
                        <button type="button" onclick="document.getElementById('manual-upload-echo').click()"
                                style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1976D2;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;">
                            <i class="material-icons" style="font-size:18px;">cloud_upload</i> Upload Gambar
                        </button>
                    </div>
                </div>
                <div id="image-gallery-echo"><div class="loading-spinner"><i class="material-icons">info</i> Klik tab untuk memuat gambar...</div></div>
            </div>

            </div><!-- end form-content-wrapper -->

            <div class="action-buttons">
                <div style="display:flex;align-items:center;gap:15px;">
                    <div class="progress-indicator"><div class="progress-dot"></div><div class="progress-dot"></div></div>
                    <span style="font-size:12px;color:#666;font-weight:600;">Tab <span id="current-tab-number-echo">1</span> dari 2</span>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" class="btn-modern btn-secondary-modern" onclick="kembaliEcho()"><i class="material-icons" style="font-size:16px;">arrow_back</i> KEMBALI</button>
                    <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev-echo" onclick="previousTabEcho()" style="display:none;"><i class="material-icons" style="font-size:16px;">navigate_before</i> SEBELUMNYA</button>
                    <button type="button" class="btn-modern btn-primary-modern" id="btn-next-echo" onclick="nextTabEcho()">SELANJUTNYA <i class="material-icons" style="font-size:16px;">navigate_next</i></button>
                    <button type="submit" class="btn-modern btn-primary-modern" id="btn-save-echo" style="display:none;"><i class="material-icons" style="font-size:16px;">save</i> SIMPAN DATA</button>
                    <button type="button" class="btn-modern btn-danger-modern" id="btn-delete-echo" style="display:none;" onclick="confirmDeleteEcho()" <?php echo !$isEdit?'disabled':''; ?>><i class="material-icons" style="font-size:16px;">delete</i> HAPUS DATA</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal Gambar -->
    <div id="imageModalEcho" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.9);" onclick="closeImageModalEcho()">
        <span style="position:absolute;top:15px;right:35px;color:#f1f1f1;font-size:40px;font-weight:bold;cursor:pointer;">&times;</span>
        <img id="modalImageEcho" style="margin:auto;display:block;max-width:90%;max-height:90%;object-fit:contain;margin-top:50px;">
    </div>
</div>

<script>const APP_BASE_URL='<?php echo defined("APP_BASE_URL")?APP_BASE_URL:"/edokter"; ?>';</script>
<script>
(function(){
    if(window._echoJsLoaded){if(typeof window._echoInit==='function')setTimeout(window._echoInit,300);return;}
    var s=document.createElement('script');s.src='<?php echo BASE_URL; ?>/js/pemeriksaanecho.js?v=<?php echo time(); ?>';
    s.onload=function(){window._echoJsLoaded=true;};document.head.appendChild(s);
})();
</script>