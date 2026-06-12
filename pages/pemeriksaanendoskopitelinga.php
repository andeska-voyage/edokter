<?php
if (!defined('BASE_URL')) define('BASE_URL', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';
$no_rawat = ''; $no_rkm_medis = '';
if(!empty($encrypted_norawat)) $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
if(!empty($encrypted_norm)) $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');

$queryPasien = bukaquery("SELECT rp.no_rawat, rp.no_rkm_medis, p.nm_pasien, d.nm_dokter, d.kd_dokter,
                            CONCAT(rp.umurdaftar,' ',rp.sttsumur) as umur
                          FROM reg_periksa rp INNER JOIN pasien p ON rp.no_rkm_medis=p.no_rkm_medis
                          LEFT JOIN dokter d ON rp.kd_dokter=d.kd_dokter WHERE rp.no_rawat='$no_rawat'");
$rsPasien = mysqli_fetch_array($queryPasien);
if(!$rsPasien) { echo "<script>alert('Data pasien tidak ditemukan!');window.location.href='?act=Pasien';</script>"; exit; }

$queryCheck = bukaquery("SELECT * FROM hasil_endoskopi_telinga WHERE no_rawat='$no_rawat'");
$rsCheck = mysqli_fetch_assoc($queryCheck);
$isEdit = ($rsCheck) ? true : false;

$kd_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

$fields = [
    'tanggal','kd_dokter','diagnosa_klinis','kiriman_dari',
    'bentuk_liang_telinga_kanan','bentuk_liang_telinga_kiri',
    'kondisi_liang_telinga_kanan','keterangan_kondisi_liang_telinga_kanan',
    'kondisi_liang_telinga_kiri','keterangan_kondisi_liang_telinga_kiri',
    'membran_timpani_intak_kanan','membran_timpani_intak_kiri',
    'membran_timpani_perforasi_kanan','keterangan_membran_timpani_perforasi_kanan',
    'membran_timpani_perforasi_kiri','keterangan_membran_timpani_perforasi_kiri',
    'kavum_timpani_mukosa_kanan','kavum_timpani_mukosa_kiri',
    'kavum_timpani_osikel_kanan','kavum_timpani_osikel_kiri',
    'kavum_timpani_isthmus_kanan','kavum_timpani_isthmus_kiri',
    'kavum_timpani_anterior_kanan','kavum_timpani_anterior_kiri',
    'kavum_timpani_posterior_kanan','kavum_timpani_posterior_kiri',
    'lainlain_kanan','lainlain_kiri','kesimpulan','anjuran'
];
$data = array_fill_keys($fields, '');
$data['tanggal'] = date('Y-m-d H:i:s');
$data['kd_dokter'] = $kd_dokter_login;
if($isEdit) $data = array_merge($data, $rsCheck);

$images = [];
if($isEdit) {
    $qi = bukaquery("SELECT photo FROM hasil_endoskopi_telinga_gambar WHERE no_rawat='$no_rawat'");
    while($row = mysqli_fetch_array($qi)) $images[] = $row['photo'];
}

$opt_bentuk = ['Lapang','Sempit','Destruksi'];
$opt_kondisi = ['Serumen','Sekret','Jamur','Kolesteatoma'];
$opt_intak = ['Normal','Hiperemis','Bulging','Retraksi'];
$opt_perforasi = ['Sentral','Atik','Marginal','Lainnya'];

function renderSel($name, $opts, $val) {
    $h = '<select class="form-control-modern" name="'.$name.'" style="padding:5px 8px;font-size:12px;">';
    $h .= '<option value="">-- Pilih --</option>';
    foreach($opts as $o) { $s = ($val==$o)?' selected':''; $h .= '<option value="'.$o.'"'.$s.'>'.$o.'</option>'; }
    return $h.'</select>';
}
function renderInp($name, $val, $max=40) {
    return '<input type="text" class="form-control-modern" name="'.$name.'" value="'.htmlspecialchars($val).'" maxlength="'.$max.'" style="padding:5px 8px;font-size:12px;">';
}

// Helper to render one side (kanan or kiri)
function renderSide($side, $data, $opt_bentuk, $opt_kondisi, $opt_intak, $opt_perforasi) {
    $label = ($side === 'kanan') ? 'Telinga Kanan' : 'Telinga Kiri';
    $s = $side; // shorthand
    $html = '';
    $html .= '<div style="font-size:12px;font-weight:700;color:#333;margin-bottom:8px;padding:6px 0;border-bottom:1px solid #e0e0e0;">'.$label.' :</div>';
    
    // Bentuk Liang Telinga (dropdown)
    $html .= '<div class="form-group-modern" style="margin-bottom:8px;">';
    $html .= '<label style="font-size:11px;margin-bottom:3px;">Kondisi Telinga</label>';
    $html .= renderSel("bentuk_liang_telinga_$s", $opt_bentuk, $data["bentuk_liang_telinga_$s"]);
    $html .= '</div>';
    
    // Liang Telinga (dropdown + text)
    $html .= '<div style="padding-left:10px;">';
    $html .= '<div class="form-group-modern" style="margin-bottom:8px;">';
    $html .= '<label style="font-size:11px;margin-bottom:3px;">- Liang Telinga</label>';
    $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">';
    $html .= renderSel("kondisi_liang_telinga_$s", $opt_kondisi, $data["kondisi_liang_telinga_$s"]);
    $html .= renderInp("keterangan_kondisi_liang_telinga_$s", $data["keterangan_kondisi_liang_telinga_$s"], 30);
    $html .= '</div></div>';
    
    // Membran Timpani section
    $html .= '<div style="font-size:11px;font-weight:600;color:#555;margin:6px 0 4px;">- Membran Timpani :</div>';
    
    // Perforasi (dropdown + text)
    $html .= '<div class="form-group-modern" style="margin-bottom:6px;padding-left:10px;">';
    $html .= '<label style="font-size:11px;margin-bottom:2px;">Perforasi</label>';
    $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">';
    $html .= renderSel("membran_timpani_perforasi_$s", $opt_perforasi, $data["membran_timpani_perforasi_$s"]);
    $html .= renderInp("keterangan_membran_timpani_perforasi_$s", $data["keterangan_membran_timpani_perforasi_$s"], 30);
    $html .= '</div></div>';
    
    // Intak (dropdown)
    $html .= '<div class="form-group-modern" style="margin-bottom:8px;padding-left:10px;">';
    $html .= '<label style="font-size:11px;margin-bottom:2px;">Intak</label>';
    $html .= renderSel("membran_timpani_intak_$s", $opt_intak, $data["membran_timpani_intak_$s"]);
    $html .= '</div>';
    
    // Kavum Timpani section
    $html .= '<div style="font-size:11px;font-weight:600;color:#555;margin:6px 0 4px;">- Kavum Timpani :</div>';
    $kavum_fields = ['mukosa','osikel','isthmus','anterior','posterior'];
    foreach($kavum_fields as $kf) {
        $html .= '<div class="form-group-modern" style="margin-bottom:6px;padding-left:10px;">';
        $html .= '<label style="font-size:11px;margin-bottom:2px;">'.ucfirst($kf).'</label>';
        $html .= renderInp("kavum_timpani_{$kf}_$s", $data["kavum_timpani_{$kf}_$s"], 40);
        $html .= '</div>';
    }
    
    // Lain-lain
    $html .= '<div class="form-group-modern" style="margin-bottom:0;">';
    $html .= '<label style="font-size:11px;margin-bottom:3px;">- Lain-lain</label>';
    $html .= renderInp("lainlain_$s", $data["lainlain_$s"], 100);
    $html .= '</div>';
    
    $html .= '</div>'; // end padding-left
    return $html;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template2.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/usg_gallery.css?v=<?php echo time(); ?>">
<script>(function(){var e=!!document.getElementById('rmeTabAjaxContainer');if(!e){document.documentElement.style.cssText='overflow:hidden;height:100vh';document.body.style.cssText='overflow:hidden;height:100vh;margin:0;padding:0';}})();</script>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:20px;">
            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                <i class="material-icons" style="font-size:22px;">hearing</i>
                <h2 style="margin:0;font-size:15px;font-weight:700;white-space:nowrap;">ENDOSKOPI TELINGA</h2>
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
            <button class="tab-item active" onclick="switchTabEndoT(0)"><i class="material-icons">description</i> Form Pemeriksaan</button>
            <button class="tab-item" onclick="switchTabEndoT(1)"><i class="material-icons">image</i> Gambar Endoskopi
                <span class="tab-badge" id="badge-endot-1" style="display:none;"><?php echo count($images); ?></span>
            </button>
        </div>

        <form id="formEndoT" method="post" action="">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
            
            <div class="form-content-wrapper">
        <!-- TAB 0 -->
        <div class="tab-content active" id="tab-endot-0">
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
                <!-- Kiriman Dari, Diagnosa -->
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

                <!-- Kanan & Kiri Side by Side -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:10px;">
                    <div><?php echo renderSide('kanan', $data, $opt_bentuk, $opt_kondisi, $opt_intak, $opt_perforasi); ?></div>
                    <div><?php echo renderSide('kiri', $data, $opt_bentuk, $opt_kondisi, $opt_intak, $opt_perforasi); ?></div>
                </div>

                <!-- Kesimpulan -->
                <div class="form-group-modern" style="margin-bottom:10px;">
                    <label style="font-size:11px;margin-bottom:3px;">Kesimpulan</label>
                    <textarea class="form-control-modern" name="kesimpulan" rows="3" style="padding:6px 10px;font-size:12px;"><?php echo $data['kesimpulan']; ?></textarea>
                </div>
                <!-- Anjuran -->
                <div class="form-group-modern" style="margin-bottom:0;">
                    <label style="font-size:11px;margin-bottom:3px;">Anjuran</label>
                    <textarea class="form-control-modern" name="anjuran" rows="3" style="padding:6px 10px;font-size:12px;"><?php echo $data['anjuran']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- TAB 1: GAMBAR -->
        <div class="tab-content" id="tab-endot-1">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;"><i class="material-icons">photo_library</i> Gambar Endoskopi Telinga</h3>
                <div>
                    <input type="file" id="manual-upload-endot" accept="image/*" multiple style="display:none;" onchange="uploadManualEndoT(this)">
                    <button type="button" onclick="document.getElementById('manual-upload-endot').click()" 
                            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1976D2;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;">
                        <i class="material-icons" style="font-size:18px;">cloud_upload</i> Upload Gambar
                    </button>
                </div>
            </div>
            <div id="image-gallery-endot"><div class="loading-spinner"><i class="material-icons">info</i> Klik tab untuk memuat gambar...</div></div>
        </div>
        </div>
        
        <div class="action-buttons">
            <div style="display:flex;align-items:center;gap:15px;">
                <div class="progress-indicator"><div class="progress-dot"></div><div class="progress-dot"></div></div>
                <span style="font-size:12px;color:#666;font-weight:600;">Tab <span id="current-tab-number-endot">1</span> dari 2</span>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" class="btn-modern btn-secondary-modern" onclick="kembaliEndoT()"><i class="material-icons" style="font-size:16px;">arrow_back</i> KEMBALI</button>
                <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev-endot" onclick="previousTabEndoT()" style="display:none;"><i class="material-icons" style="font-size:16px;">navigate_before</i> SEBELUMNYA</button>
                <button type="button" class="btn-modern btn-primary-modern" id="btn-next-endot" onclick="nextTabEndoT()">SELANJUTNYA <i class="material-icons" style="font-size:16px;">navigate_next</i></button>
                <button type="submit" class="btn-modern btn-primary-modern" id="btn-save-endot" style="display:none;"><i class="material-icons" style="font-size:16px;">save</i> SIMPAN DATA</button>
                <button type="button" class="btn-modern btn-danger-modern" id="btn-delete-endot" style="display:none;" onclick="confirmDeleteEndoT()" <?php echo !$isEdit?'disabled':''; ?>><i class="material-icons" style="font-size:16px;">delete</i> HAPUS DATA</button>
            </div>
        </div>
        </form>
    </div>
    
    <div id="imageModalEndoT" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.9);" onclick="closeImageModalEndoT()">
        <span style="position:absolute;top:15px;right:35px;color:#f1f1f1;font-size:40px;font-weight:bold;cursor:pointer;">&times;</span>
        <img id="modalImageEndoT" style="margin:auto;display:block;max-width:90%;max-height:90%;object-fit:contain;margin-top:50px;">
    </div>
</div>

<script>const APP_BASE_URL='<?php echo defined("APP_BASE_URL")?APP_BASE_URL:"/edokter"; ?>';</script>
<script>
(function(){
    if(window._endoTJsLoaded){if(typeof window._endoTInit==='function')setTimeout(window._endoTInit,300);return;}
    var s=document.createElement('script');s.src='<?php echo BASE_URL; ?>/js/pemeriksaanendoskopitelinga.js?v=<?php echo time(); ?>';
    s.onload=function(){window._endoTJsLoaded=true;};document.head.appendChild(s);
})();
</script>