<?php
if (!defined('BASE_URL')) define('BASE_URL', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';
$no_rawat = ''; $no_rkm_medis = '';
if(!empty($encrypted_norawat)) $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
if(!empty($encrypted_norm)) $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');

$queryPasien = bukaquery("SELECT rp.no_rawat, rp.no_rkm_medis, p.nm_pasien, d.nm_dokter, d.kd_dokter,
                            CONCAT(rp.umurdaftar,' ',rp.sttsumur) as umur
                          FROM reg_periksa rp
                          INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                          LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                          WHERE rp.no_rawat = '$no_rawat'");
$rsPasien = mysqli_fetch_array($queryPasien);
if(!$rsPasien) { echo "<script>alert('Data pasien tidak ditemukan!');window.location.href='?act=Pasien';</script>"; exit; }

$queryCheck = bukaquery("SELECT * FROM hasil_endoskopi_hidung WHERE no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_assoc($queryCheck);
$isEdit = ($rsCheck) ? true : false;

$kd_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

$data = array(
    'tanggal' => date('Y-m-d H:i:s'), 'kd_dokter' => $kd_dokter_login,
    'diagnosa_klinis' => '', 'kiriman_dari' => '',
    'kondisi_hidung_kanan' => '', 'kondisi_hidung_kiri' => '',
    'kavum_nasi_kanan' => '', 'kavum_nasi_kiri' => '',
    'konka_inferior_kanan' => '', 'konka_inferior_kiri' => '',
    'meatus_medius_kanan' => '', 'meatus_medius_kiri' => '',
    'septum_kanan' => '', 'septum_kiri' => '',
    'nasofaring_kanan' => '', 'nasofaring_kiri' => '',
    'lainlain_kanan' => '', 'lainlain_kiri' => '',
    'kesimpulan' => ''
);
if($isEdit) $data = array_merge($data, $rsCheck);

$images = array();
if($isEdit) {
    $qi = bukaquery("SELECT photo FROM hasil_endoskopi_hidung_gambar WHERE no_rawat='$no_rawat'");
    while($row = mysqli_fetch_array($qi)) $images[] = $row['photo'];
}

// Enum options
$opt_kondisi = ['Lapang','Sempit','Mukosa Edema'];
$opt_kavum = ['Mukosa Pucat','Mukosa Hiperemis','Massa'];
$opt_konka = ['Eutrofi','Hipertrofi','Atrofi'];
$opt_meatus = ['Terbuka','Tertutup','Mukosa Edema','Polip'];
$opt_septum = ['Lurus','Deviasi','Spina'];
$opt_nasofaring = ['Normal','Adenoid','Keradangan','Massa'];

function renderSelect($name, $options, $selected, $style = '') {
    $html = '<select class="form-control-modern" name="'.$name.'" style="padding:6px 10px;font-size:12px;'.$style.'">';
    
    foreach($options as $opt) {
        $sel = ($selected == $opt) ? ' selected' : '';
        $html .= '<option value="'.$opt.'"'.$sel.'>'.$opt.'</option>';
    }
    $html .= '</select>';
    return $html;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template2.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/usg_gallery.css?v=<?php echo time(); ?>">
<script>
(function() {
    var isEmbedded = !!document.getElementById('rmeTabAjaxContainer');
    if (!isEmbedded) { document.documentElement.style.cssText='overflow:hidden;height:100vh'; document.body.style.cssText='overflow:hidden;height:100vh;margin:0;padding:0'; }
})();
</script>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;">
            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <i class="material-icons" style="font-size:22px;">visibility</i>
                <h2 style="margin:0;font-size:15px;font-weight:700;white-space:nowrap;">ENDOSKOPI HIDUNG</h2>
            </div>
            <div style="display:flex;align-items:center;gap:20px;flex:1;font-size:12px;overflow:hidden;">
                <div style="display:flex;align-items:center;gap:5px;white-space:nowrap;"><i class="material-icons" style="font-size:16px;">folder</i><strong>No. Rawat:</strong><span><?php echo $rsPasien['no_rawat']; ?></span></div>
                <div style="display:flex;align-items:center;gap:5px;white-space:nowrap;"><i class="material-icons" style="font-size:16px;">badge</i><strong>No. RM:</strong><span><?php echo $rsPasien['no_rkm_medis']; ?></span></div>
                <div style="display:flex;align-items:center;gap:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><i class="material-icons" style="font-size:16px;">person</i><strong>Nama:</strong><span style="overflow:hidden;text-overflow:ellipsis;"><?php echo strtoupper($rsPasien['nm_pasien']); ?></span></div>
            </div>
            <div style="flex-shrink:0;"><span class="mode-badge <?php echo $isEdit?'mode-edit':'mode-add'; ?>"><?php echo $isEdit?'✏️ EDIT':'➕ NEW'; ?></span></div>
        </div>
    </div>

    <div class="form-wrapper">
        <div class="modern-tabs">
            <button class="tab-item active" onclick="switchTabEndoH(0)"><i class="material-icons">description</i> Form Pemeriksaan</button>
            <button class="tab-item" onclick="switchTabEndoH(1)"><i class="material-icons">image</i> Gambar Endoskopi
                <span class="tab-badge" id="badge-endoh-1" style="display:none;"><?php echo count($images); ?></span>
            </button>
        </div>

        <form id="formEndoH" method="post" action="">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
            
            <div class="form-content-wrapper">
        <!-- TAB 0 -->
        <div class="tab-content active" id="tab-endoh-0">
            <div class="section-card" style="padding:15px;">
                <!-- Dokter, Tanggal -->
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-bottom:10px;">
                    <div class="form-group-modern" style="margin-bottom:0;">
                        <label style="font-size:11px;margin-bottom:3px;">Dokter</label>
                        <input type="text" class="form-control-modern" value="<?php echo $rsPasien['nm_dokter']; ?>" readonly style="padding:6px 10px;font-size:12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom:0;">
                        <label style="font-size:11px;margin-bottom:3px;">Tanggal <span class="required">*</span></label>
                        <input type="datetime-local" class="form-control-modern" name="tanggal" required
                               value="<?php echo isset($data['tanggal'])?date('Y-m-d\TH:i',strtotime($data['tanggal'])):date('Y-m-d\TH:i'); ?>" style="padding:6px 10px;font-size:12px;">
                    </div>
                </div>

                <!-- Kiriman Dari, Diagnosa Klinis -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                    <div class="form-group-modern" style="margin-bottom:0;">
                        <label style="font-size:11px;margin-bottom:3px;">Kiriman Dari</label>
                        <input type="text" class="form-control-modern" name="kiriman_dari" value="<?php echo $data['kiriman_dari']; ?>" style="padding:6px 10px;font-size:12px;">
                    </div>
                    <div class="form-group-modern" style="margin-bottom:0;">
                        <label style="font-size:11px;margin-bottom:3px;">Diagnosa Klinis</label>
                        <input type="text" class="form-control-modern" name="diagnosa_klinis" value="<?php echo $data['diagnosa_klinis']; ?>" style="padding:6px 10px;font-size:12px;">
                    </div>
                </div>

                <!-- Hidung Kanan & Kiri - Side by Side -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:10px;">
                    <!-- KANAN -->
                    <div>
                        <div style="font-size:12px;font-weight:700;color:#333;margin-bottom:8px;padding:6px 0;border-bottom:1px solid #e0e0e0;">
                            Hidung Kanan :
                        </div>
                        <div class="form-group-modern" style="margin-bottom:8px;">
                            <label style="font-size:11px;margin-bottom:3px;">Kondisi Hidung</label>
                            <?php echo renderSelect('kondisi_hidung_kanan', $opt_kondisi, $data['kondisi_hidung_kanan']); ?>
                        </div>
                        <div style="padding-left:10px;">
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Kavum Nasi</label>
                                <?php echo renderSelect('kavum_nasi_kanan', $opt_kavum, $data['kavum_nasi_kanan']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Konka Inferior</label>
                                <?php echo renderSelect('konka_inferior_kanan', $opt_konka, $data['konka_inferior_kanan']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Meatus Medius</label>
                                <?php echo renderSelect('meatus_medius_kanan', $opt_meatus, $data['meatus_medius_kanan']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Septum</label>
                                <?php echo renderSelect('septum_kanan', $opt_septum, $data['septum_kanan']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Nasofaring</label>
                                <?php echo renderSelect('nasofaring_kanan', $opt_nasofaring, $data['nasofaring_kanan']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:0;">
                                <label style="font-size:11px;margin-bottom:3px;">- Lain-lain</label>
                                <input type="text" class="form-control-modern" name="lainlain_kanan" value="<?php echo $data['lainlain_kanan']; ?>" style="padding:6px 10px;font-size:12px;">
                            </div>
                        </div>
                    </div>

                    <!-- KIRI -->
                    <div>
                        <div style="font-size:12px;font-weight:700;color:#333;margin-bottom:8px;padding:6px 0;border-bottom:1px solid #e0e0e0;">
                            Hidung Kiri :
                        </div>
                        <div class="form-group-modern" style="margin-bottom:8px;">
                            <label style="font-size:11px;margin-bottom:3px;">Kondisi Hidung</label>
                            <?php echo renderSelect('kondisi_hidung_kiri', $opt_kondisi, $data['kondisi_hidung_kiri']); ?>
                        </div>
                        <div style="padding-left:10px;">
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Kavum Nasi</label>
                                <?php echo renderSelect('kavum_nasi_kiri', $opt_kavum, $data['kavum_nasi_kiri']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Konka Inferior</label>
                                <?php echo renderSelect('konka_inferior_kiri', $opt_konka, $data['konka_inferior_kiri']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Meatus Medius</label>
                                <?php echo renderSelect('meatus_medius_kiri', $opt_meatus, $data['meatus_medius_kiri']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Septum</label>
                                <?php echo renderSelect('septum_kiri', $opt_septum, $data['septum_kiri']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:8px;">
                                <label style="font-size:11px;margin-bottom:3px;">- Nasofaring</label>
                                <?php echo renderSelect('nasofaring_kiri', $opt_nasofaring, $data['nasofaring_kiri']); ?>
                            </div>
                            <div class="form-group-modern" style="margin-bottom:0;">
                                <label style="font-size:11px;margin-bottom:3px;">- Lain-lain</label>
                                <input type="text" class="form-control-modern" name="lainlain_kiri" value="<?php echo $data['lainlain_kiri']; ?>" style="padding:6px 10px;font-size:12px;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kesimpulan -->
                <div class="form-group-modern" style="margin-bottom:0;">
                    <label style="font-size:11px;margin-bottom:3px;">Kesimpulan</label>
                    <textarea class="form-control-modern" name="kesimpulan" rows="4" style="padding:6px 10px;font-size:12px;"><?php echo $data['kesimpulan']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- TAB 1: GAMBAR -->
        <div class="tab-content" id="tab-endoh-1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;"><i class="material-icons">photo_library</i> Gambar Endoskopi Hidung</h3>
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="file" id="manual-upload-endoh" accept="image/*" multiple style="display:none;" onchange="uploadManualEndoH(this)">
                    <button type="button" onclick="document.getElementById('manual-upload-endoh').click()" 
                            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1976D2;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;">
                        <i class="material-icons" style="font-size:18px;">cloud_upload</i> Upload Gambar
                    </button>
                </div>
            </div>
            <div id="image-gallery-endoh">
                <div class="loading-spinner"><i class="material-icons">info</i> Klik tab untuk memuat gambar...</div>
            </div>
        </div>
        
        </div>
        
        <div class="action-buttons">
            <div style="display:flex;align-items:center;gap:15px;">
                <div class="progress-indicator"><div class="progress-dot" id="dot-endoh-0"></div><div class="progress-dot" id="dot-endoh-1"></div></div>
                <span style="font-size:12px;color:#666;font-weight:600;">Tab <span id="current-tab-number-endoh">1</span> dari 2</span>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn-modern btn-secondary-modern" onclick="kembaliEndoH()"><i class="material-icons" style="font-size:16px;">arrow_back</i> KEMBALI</button>
                <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev-endoh" onclick="previousTabEndoH()" style="display:none;"><i class="material-icons" style="font-size:16px;">navigate_before</i> SEBELUMNYA</button>
                <button type="button" class="btn-modern btn-primary-modern" id="btn-next-endoh" onclick="nextTabEndoH()">SELANJUTNYA <i class="material-icons" style="font-size:16px;">navigate_next</i></button>
                <button type="submit" class="btn-modern btn-primary-modern" id="btn-save-endoh" style="display:none;"><i class="material-icons" style="font-size:16px;">save</i> SIMPAN DATA</button>
                <button type="button" class="btn-modern btn-danger-modern" id="btn-delete-endoh" style="display:none;" onclick="confirmDeleteEndoH()" <?php echo !$isEdit?'disabled':''; ?>><i class="material-icons" style="font-size:16px;">delete</i> HAPUS DATA</button>
            </div>
        </div>
        </form>
    </div>
    
    <div id="imageModalEndoH" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.9);" onclick="closeImageModalEndoH()">
        <span style="position:absolute;top:15px;right:35px;color:#f1f1f1;font-size:40px;font-weight:bold;cursor:pointer;">&times;</span>
        <img id="modalImageEndoH" style="margin:auto;display:block;max-width:90%;max-height:90%;object-fit:contain;margin-top:50px;">
    </div>
</div>

<script>const APP_BASE_URL='<?php echo defined("APP_BASE_URL")?APP_BASE_URL:"/edokter"; ?>';</script>
<script>
(function(){
    if(window._endoHJsLoaded){if(typeof window._endoHInit==='function')setTimeout(window._endoHInit,300);return;}
    var s=document.createElement('script');
    s.src='<?php echo BASE_URL; ?>/js/pemeriksaanendoskopihidung.js?v=<?php echo time(); ?>';
    s.onload=function(){window._endoHJsLoaded=true;};
    document.head.appendChild(s);
})();
</script>