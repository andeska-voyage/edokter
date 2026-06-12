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

$queryCheck = bukaquery("SELECT * FROM hasil_pemeriksaan_treadmill WHERE no_rawat='$no_rawat'");
$rsCheck    = mysqli_fetch_assoc($queryCheck);
$isEdit     = ($rsCheck) ? true : false;

$kd_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');

$fields = [
    'tanggal','kd_dokter',
    'kiriman_dari','diagnosa_klinis',
    'protokol','keterangan_protokol',
    'td_awal','nadi_awal','denyut_jantung_maksimal',
    'hasil_pemeriksaan','temuan_ekg','kapasitas_fungsional',
    'interpretasi','kesimpulan'
];
$data = array_fill_keys($fields, '');
$data['tanggal']   = date('Y-m-d H:i:s');
$data['kd_dokter'] = $kd_dokter_login;
$data['protokol']  = 'Bruce';
if($isEdit) $data = array_merge($data, $rsCheck);

$images = [];
if($isEdit) {
    $qi = bukaquery("SELECT photo FROM hasil_pemeriksaan_treadmill_gambar WHERE no_rawat='$no_rawat'");
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
                <i class="material-icons" style="font-size:22px;">directions_run</i>
                <h2 style="margin:0;font-size:15px;font-weight:700;white-space:nowrap;">PEMERIKSAAN TREADMILL</h2>
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
            <button class="tab-item active" onclick="switchTabTreadmill(0)"><i class="material-icons">description</i> Form Pemeriksaan</button>
            <button class="tab-item" onclick="switchTabTreadmill(1)"><i class="material-icons">image</i> Gambar Treadmill
                <span class="tab-badge" id="badge-treadmill-1" <?php echo count($images)===0?'style="display:none;"':''; ?>><?php echo count($images); ?></span>
            </button>
        </div>

        <form id="formTreadmill" method="post" action="">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

            <div class="form-content-wrapper">

            <!-- TAB 0: FORM DATA -->
            <div class="tab-content active" id="tab-treadmill-0">
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

                    <!-- Kiriman Dari & Diagnosa Klinis -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;">
                        <div class="form-group-modern" style="margin-bottom:0;">
                            <label style="font-size:11px;margin-bottom:3px;">Kiriman Dari</label>
                            <input type="text" class="form-control-modern" name="kiriman_dari"
                                   value="<?php echo htmlspecialchars($data['kiriman_dari']); ?>" maxlength="50"
                                   style="padding:6px 10px;font-size:12px;">
                        </div>
                        <div class="form-group-modern" style="margin-bottom:0;">
                            <label style="font-size:11px;margin-bottom:3px;">Diagnosa Klinis</label>
                            <input type="text" class="form-control-modern" name="diagnosa_klinis"
                                   value="<?php echo htmlspecialchars($data['diagnosa_klinis']); ?>" maxlength="50"
                                   style="padding:6px 10px;font-size:12px;">
                        </div>
                    </div>

                    <!-- Protokol & TD Awal & Nadi Awal -->
                    <div style="display:grid;grid-template-columns:auto 1fr auto auto auto auto;gap:10px;align-items:end;margin-bottom:12px;">
                        <div class="form-group-modern" style="margin-bottom:0;">
                            <label style="font-size:11px;margin-bottom:3px;">Protokol</label>
                            <select class="form-control-modern" name="protokol" style="padding:6px 10px;font-size:12px;min-width:140px;">
                                <?php
                                $protokol_opts = ['Bruce','Modified Bruce','Balke','Naughton','Cornell','Ramp'];
                                foreach($protokol_opts as $opt):
                                ?>
                                <option value="<?php echo $opt; ?>" <?php echo $data['protokol']===$opt?'selected':''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group-modern" style="margin-bottom:0;">
                            <label style="font-size:11px;margin-bottom:3px;">Keterangan Protokol</label>
                            <input type="text" class="form-control-modern" name="keterangan_protokol"
                                   value="<?php echo htmlspecialchars($data['keterangan_protokol']); ?>" maxlength="30"
                                   style="padding:6px 10px;font-size:12px;">
                        </div>
                        <div class="form-group-modern" style="margin-bottom:0;">
                            <label style="font-size:11px;margin-bottom:3px;">TD Awal</label>
                            <div style="display:flex;align-items:center;gap:4px;">
                                <input type="text" class="form-control-modern" name="td_awal"
                                       value="<?php echo htmlspecialchars($data['td_awal']); ?>" maxlength="8"
                                       style="padding:6px 10px;font-size:12px;width:70px;">
                                <span style="font-size:11px;color:#666;white-space:nowrap;">mmHg</span>
                            </div>
                        </div>
                        <div class="form-group-modern" style="margin-bottom:0;">
                            <label style="font-size:11px;margin-bottom:3px;">Nadi Awal</label>
                            <div style="display:flex;align-items:center;gap:4px;">
                                <input type="text" class="form-control-modern" name="nadi_awal"
                                       value="<?php echo htmlspecialchars($data['nadi_awal']); ?>" maxlength="5"
                                       style="padding:6px 10px;font-size:12px;width:60px;">
                                <span style="font-size:11px;color:#666;white-space:nowrap;">x/menit</span>
                            </div>
                        </div>
                    </div>

                    <!-- Denyut Jantung Maksimal Teoritis -->
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                        <label style="font-size:11px;white-space:nowrap;">Denyut Jantung Maksimal Teoritis :</label>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <input type="text" class="form-control-modern" name="denyut_jantung_maksimal"
                                   value="<?php echo htmlspecialchars($data['denyut_jantung_maksimal']); ?>" maxlength="5"
                                   style="padding:6px 10px;font-size:12px;width:60px;">
                            <span style="font-size:11px;color:#666;">x/menit</span>
                        </div>
                    </div>

                    <!-- Hasil Pemeriksaan -->
                    <div class="form-group-modern" style="margin-bottom:10px;">
                        <label style="font-size:11px;margin-bottom:3px;">Hasil Pemeriksaan</label>
                        <textarea class="form-control-modern" name="hasil_pemeriksaan" rows="4"
                                  style="padding:6px 10px;font-size:12px;resize:vertical;"><?php echo htmlspecialchars($data['hasil_pemeriksaan']); ?></textarea>
                    </div>

                    <!-- Temuan EKG -->
                    <div class="form-group-modern" style="margin-bottom:10px;">
                        <label style="font-size:11px;margin-bottom:3px;">Temuan EKG</label>
                        <textarea class="form-control-modern" name="temuan_ekg" rows="3"
                                  style="padding:6px 10px;font-size:12px;resize:vertical;"><?php echo htmlspecialchars($data['temuan_ekg']); ?></textarea>
                    </div>

                    <!-- Kapasitas Fungsional -->
                    <div class="form-group-modern" style="margin-bottom:10px;">
                        <label style="font-size:11px;margin-bottom:3px;">Kapasitas Fungsional</label>
                        <textarea class="form-control-modern" name="kapasitas_fungsional" rows="3"
                                  style="padding:6px 10px;font-size:12px;resize:vertical;"><?php echo htmlspecialchars($data['kapasitas_fungsional']); ?></textarea>
                    </div>

                    <!-- Interpretasi -->
                    <div class="form-group-modern" style="margin-bottom:10px;">
                        <label style="font-size:11px;margin-bottom:3px;">Interpretasi</label>
                        <textarea class="form-control-modern" name="interpretasi" rows="3"
                                  style="padding:6px 10px;font-size:12px;resize:vertical;"><?php echo htmlspecialchars($data['interpretasi']); ?></textarea>
                    </div>

                    <!-- Kesimpulan -->
                    <div class="form-group-modern" style="margin-bottom:0;">
                        <label style="font-size:11px;margin-bottom:3px;">Kesimpulan</label>
                        <textarea class="form-control-modern" name="kesimpulan" rows="3"
                                  style="padding:6px 10px;font-size:12px;resize:vertical;"><?php echo htmlspecialchars($data['kesimpulan']); ?></textarea>
                    </div>

                </div>
            </div>

            <!-- TAB 1: GAMBAR -->
            <div class="tab-content" id="tab-treadmill-1">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h3 style="margin:0;"><i class="material-icons">photo_library</i> Gambar Treadmill</h3>
                    <div>
                        <input type="file" id="manual-upload-treadmill" accept="image/*" multiple style="display:none;" onchange="uploadManualTreadmill(this)">
                        <button type="button" onclick="document.getElementById('manual-upload-treadmill').click()"
                                style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#1976D2;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;font-size:13px;">
                            <i class="material-icons" style="font-size:18px;">cloud_upload</i> Upload Gambar
                        </button>
                    </div>
                </div>
                <div id="image-gallery-treadmill"><div class="loading-spinner"><i class="material-icons">info</i> Klik tab untuk memuat gambar...</div></div>
            </div>

            </div><!-- end form-content-wrapper -->

            <div class="action-buttons">
                <div style="display:flex;align-items:center;gap:15px;">
                    <div class="progress-indicator"><div class="progress-dot"></div><div class="progress-dot"></div></div>
                    <span style="font-size:12px;color:#666;font-weight:600;">Tab <span id="current-tab-number-treadmill">1</span> dari 2</span>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="button" class="btn-modern btn-secondary-modern" onclick="kembaliTreadmill()"><i class="material-icons" style="font-size:16px;">arrow_back</i> KEMBALI</button>
                    <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev-treadmill" onclick="previousTabTreadmill()" style="display:none;"><i class="material-icons" style="font-size:16px;">navigate_before</i> SEBELUMNYA</button>
                    <button type="button" class="btn-modern btn-primary-modern" id="btn-next-treadmill" onclick="nextTabTreadmill()">SELANJUTNYA <i class="material-icons" style="font-size:16px;">navigate_next</i></button>
                    <button type="submit" class="btn-modern btn-primary-modern" id="btn-save-treadmill"><i class="material-icons" style="font-size:16px;">save</i> SIMPAN DATA</button>
                    <button type="button" class="btn-modern btn-danger-modern" id="btn-delete-treadmill" onclick="confirmDeleteTreadmill()" <?php echo !$isEdit?'disabled':''; ?>><i class="material-icons" style="font-size:16px;">delete</i> HAPUS DATA</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Modal Gambar -->
    <div id="imageModalTreadmill" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.9);" onclick="closeImageModalTreadmill()">
        <span style="position:absolute;top:15px;right:35px;color:#f1f1f1;font-size:40px;font-weight:bold;cursor:pointer;">&times;</span>
        <img id="modalImageTreadmill" style="margin:auto;display:block;max-width:90%;max-height:90%;object-fit:contain;margin-top:50px;">
    </div>
</div>

<script>const APP_BASE_URL='<?php echo defined("APP_BASE_URL")?APP_BASE_URL:"/edokter"; ?>';</script>
<script>
(function(){
    if(window._treadmillJsLoaded){if(typeof window._treadmillInit==='function')setTimeout(window._treadmillInit,300);return;}
    var s=document.createElement('script');s.src='<?php echo BASE_URL; ?>/js/pemeriksaantreadmill.js?v=<?php echo time(); ?>';
    s.onload=function(){window._treadmillJsLoaded=true;};document.head.appendChild(s);
})();
</script>