<?php
/**
 * catatananestesisedasi.php
 * Form Catatan Anestesi Sedasi - E-Dokter SIMRS Khanza
 */
define('BASE_URL_CAS', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';
$no_rawat = ''; $no_rkm_medis = '';
if(!empty($encrypted_norawat)) { $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd'); }
if(!empty($encrypted_norm))    { $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd'); }

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
if(!$rsPasien) { echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>"; exit; }

$kd_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) { $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd'); }

// Ambil nama tindakan dari booking_operasi -> paket_operasi
$nama_tindakan_booking = '';
$queryBooking = bukaquery("SELECT pk.nm_perawatan
                           FROM booking_operasi bo
                           LEFT JOIN paket_operasi pk ON bo.kode_paket = pk.kode_paket
                           WHERE bo.no_rawat = '$no_rawat'
                           ORDER BY bo.tanggal DESC LIMIT 1");
$rsBooking = mysqli_fetch_array($queryBooking);
if($rsBooking) { $nama_tindakan_booking = $rsBooking['nm_perawatan']; }

// Cek data existing
$queryCheck = bukaquery("SELECT cas.*,
                            db.nm_dokter  AS nm_dokter_bedah,
                            da.nm_dokter  AS nm_dokter_anestesi,
                            pa.nama       AS nm_perawat_anestesi,
                            pb.nama       AS nm_perawat_bedah
                         FROM catatan_anestesi_sedasi cas
                         LEFT JOIN dokter  db ON cas.kd_dokter_bedah    = db.kd_dokter
                         LEFT JOIN dokter  da ON cas.kd_dokter_anestesi = da.kd_dokter
                         LEFT JOIN petugas pa ON cas.nip_perawat_ok     = pa.nip
                         LEFT JOIN petugas pb ON cas.nip_perawat_anestesi = pb.nip
                         WHERE cas.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;

$data = array(
    'tanggal'                               => date('Y-m-d H:i:s'),
    'kd_dokter_bedah'                       => '',
    'kd_dokter_anestesi'                    => '',
    'diagnosa_pre_bedah'                    => '',
    'tindakan_jenis_pembedahan'             => $nama_tindakan_booking,
    'diagnosa_pasca_bedah'                  => '',
    // Pre Induksi
    'pre_induksi_jam'                       => '',
    'pre_induksi_kesadaran'                 => 'Compos Mentis',
    'pre_induksi_td'                        => '',
    'pre_induksi_nadi'                      => '',
    'pre_induksi_rr'                        => '',
    'pre_induksi_suhu'                      => '',
    'pre_induksi_o2'                        => '',
    'pre_induksi_tb'                        => '',
    'pre_induksi_bb'                        => '',
    'pre_induksi_rhesus'                    => '+',
    'pre_induksi_hb'                        => '',
    'pre_induksi_ht'                        => '',
    'pre_induksi_leko'                      => '',
    'pre_induksi_trombo'                    => '',
    'pre_induksi_btct'                      => '',
    'pre_induksi_gds'                       => '',
    'pre_induksi_lainlain'                  => '',
    // Teknik & Alat Khusus
    'teknik_alat_tci'                       => 'Tidak',
    'teknik_alat_glidescopi'                => 'Tidak',
    'teknik_alat_stimulator_saraf'          => 'Tidak',
    'teknik_alat_cpb'                       => 'Tidak',
    'teknik_alat_usg'                       => 'Tidak',
    'teknik_alat_ventilasi'                 => 'Tidak',
    'teknik_alat_broncoskopy'               => 'Tidak',
    'teknik_alat_hiopotensi'                => 'Tidak',
    'teknik_alat_lainlain'                  => '',
    // Monitoring
    'monitoring_etco'                       => 'Tidak',
    'monitoring_stetoskop'                  => 'Tidak',
    'monitoring_cath_a_pulmo'               => 'Tidak',
    'monitoring_ngt'                        => 'Tidak',
    'monitoring_spo2'                       => 'Tidak',
    'monitoring_nibp'                       => 'Tidak',
    'monitoring_kateter'                    => 'Tidak',
    'monitoring_bis'                        => 'Tidak',
    'monitoring_cvp'                        => 'Tidak',
    'monitoring_cvp_keterangan'             => '',
    'monitoring_arteri'                     => 'Tidak',
    'monitoring_arteri_keterangan'          => '',
    'monitoring_temp'                       => 'Tidak',
    'monitoring_ekg'                        => 'Tidak',
    'monitoring_ekg_keterangan'             => '',
    'monitoring_lainlain'                   => '',
    // Status Fisik
    'status_fisik_asa'                      => '1',
    'status_fisik_alergi'                   => 'Tidak',
    'status_fisik_alergi_keterangan'        => '',
    'status_fisik_penyulit_sedasi'          => '',
    // Perencanaan
    'perencanaan_lanjut'                    => 'Ya',
    'perencanaan_lanjut_sedasi'             => 'Tidak',
    'perencanaan_lanjut_sedasi_keterangan'  => '',
    'perencanaan_lanjut_epidural'           => 'Tidak',
    'perencanaan_lanjut_spinal'             => 'Tidak',
    'perencanaan_lanjut_anestesi_umum'      => 'Tidak',
    'perencanaan_lanjut_anestesi_umum_keterangan' => '',
    'perencanaan_lanjut_blok_perifer'       => 'Tidak',
    'perencanaan_lanjut_blok_perifer_keterangan'  => '',
    'perencanaan_batal'                     => 'Tidak',
    'perencanaan_batal_alasan'              => '',
    'nip_perawat_ok'                        => '',
    'nip_perawat_anestesi'                  => '',
);
if($isEdit) { $data = array_merge($data, $rsCheck); }

$bolehHapus = false;
if($isEdit && ($kd_dokter_login === $data['kd_dokter_bedah'] || $kd_dokter_login === $data['kd_dokter_anestesi'])) {
    $bolehHapus = true;
}

function casOpts($name, $value, $opts, $style = '') {
    $h = '<select name="'.$name.'"'.($style ? ' style="'.$style.'"' : '').'>';
    foreach($opts as $o) { $s = ($value == $o) ? 'selected' : ''; $h .= '<option value="'.$o.'" '.$s.'>'.$o.'</option>'; }
    return $h.'</select>';
}
function casV($key) { global $data; return htmlspecialchars($data[$key] ?? ''); }
?>

<link rel="stylesheet" href="<?php echo BASE_URL_CAS; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
/* === CAS ROW === */
.cas-row { display:flex; align-items:center; gap:6px; flex-wrap:wrap; padding:5px 8px; background:#f8fafc; border-radius:5px; border:1px solid #e2e8f0; margin-bottom:5px; }
.cas-row label { font-size:11px; font-weight:600; color:#475569; white-space:nowrap; }
.cas-row select { padding:4px 6px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; min-width:80px; }
.cas-row input[type="text"] { padding:4px 6px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; }
.cas-row .cas-unit { font-size:10px; color:#64748b; white-space:nowrap; }
/* === CAS INLINE GRID (label:value) === */
.cas-inline-grid { display:grid; gap:6px; }
.cas-inline-grid.cols-2 { grid-template-columns:repeat(2,1fr); }
.cas-inline-grid.cols-3 { grid-template-columns:repeat(3,1fr); }
.cas-inline-grid.cols-4 { grid-template-columns:repeat(4,1fr); }
.cas-inline-item { display:flex; align-items:center; gap:5px; padding:5px 8px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; }
.cas-inline-item label { font-size:10px; font-weight:600; color:#475569; white-space:nowrap; }
.cas-inline-item select { padding:3px 5px; border:1px solid #e2e8f0; border-radius:3px; font-size:11px; }
.cas-inline-item input[type="text"] { flex:1; padding:3px 5px; border:1px solid #e2e8f0; border-radius:3px; font-size:11px; min-width:60px; }
/* === VITALS === */
.cas-vital-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(90px,1fr)); gap:6px; }
.cas-vital-item { background:#fff; border:1px solid #e2e8f0; border-radius:4px; padding:6px 8px; }
.cas-vital-item label { display:block; font-size:9px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:3px; }
.cas-vital-item input { width:100%; padding:4px 5px; border:1px solid #cbd5e1; border-radius:3px; font-size:12px; font-weight:600; color:#1e293b; }
.cas-vital-item .cas-unit { font-size:9px; color:#94a3b8; display:block; margin-top:2px; }
/* === STATUS GRID RANAP === */
.status-grid-ranap { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:8px; margin-top:6px; }
.status-grid-ranap .status-item { background:#f8fafc; padding:8px 10px; border-radius:4px; border:1px solid #e2e8f0; }
.status-grid-ranap .status-item label { font-size:9px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; }
.status-grid-ranap .status-item select { width:100%; padding:5px 7px; border:1px solid #cbd5e1; border-radius:3px; font-size:12px; }
/* === AUTOCOMPLETE === */
.cas-ac-wrap { position:relative; }
.cas-ac-dd { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:0 0 8px 8px; box-shadow:0 4px 12px rgba(0,0,0,.15); max-height:200px; overflow-y:auto; z-index:99; display:none; }
.cas-ac-dd.show { display:block; }
.cas-ac-dd div { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; font-size:12px; }
.cas-ac-dd div:hover { background:#f0f9ff; }
.cas-ac-dd div strong { color:#1e40af; }
.cas-ac-dd .no-result { color:#94a3b8; text-align:center; font-style:italic; }
/* === Override vital-grid & form-group di CAS agar compact seperti cas-inline === */
#formCatatanAnestesiSedasi .form-grid.cols-3 .form-group label,
#formCatatanAnestesiSedasi .section-subtitle { font-size:11px; font-weight:600; color:#475569; }
#formCatatanAnestesiSedasi .form-grid.cols-3 .form-group input,
#formCatatanAnestesiSedasi .form-grid.cols-3 .form-group select { padding:5px 8px; font-size:11px; font-weight:500; }
#formCatatanAnestesiSedasi .vital-grid { grid-template-columns:repeat(auto-fit,minmax(80px,1fr)); gap:5px; }
#formCatatanAnestesiSedasi .vital-item { padding:5px 8px; border-width:1px; }
#formCatatanAnestesiSedasi .vital-item label { font-size:9px; margin-bottom:2px; }
#formCatatanAnestesiSedasi .vital-item input { padding:4px 6px; font-size:11px; font-weight:600; }
#formCatatanAnestesiSedasi .form-group > label { font-size:10px; }
#formCatatanAnestesiSedasi .form-group > input[type="text"] { padding:5px 8px; font-size:11px; }
@media(max-width:768px){
    .cas-inline-grid.cols-2,.cas-inline-grid.cols-3,.cas-inline-grid.cols-4 { grid-template-columns:1fr; }
}
</style>

<div class="modern-form-container">
    <!-- PATIENT HEADER -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">medical_services</i>
                CATATAN ANESTESI / SEDASI
                <?php if($isEdit): ?><span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?><span class="mode-badge mode-add">➕ NEW</span><?php endif; ?>
            </h1>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">wc</i><strong>J.K:</strong> <?php echo $rsPasien['jk']; ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-content">
        <form id="formCatatanAnestesiSedasi" method="post" action="">
        <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">

        <!-- ============================================================
             SECTION 1 : DATA UMUM
             ============================================================ -->
        <div class="section">
            <div class="section-header"><i class="material-icons">event_note</i><h2>Data Umum</h2></div>

            <!-- Baris 1: Tanggal | Diagnosa Pra-Bedah -->
            <div class="cas-row">
                <label>Tanggal :</label>
                <input type="text" name="tanggal" value="<?php echo date('d-m-Y H:i:s', strtotime($data['tanggal'])); ?>" readonly style="width:160px;">
                <label style="margin-left:10px;">Diagnosa Pra-Bedah :</label>
                <input type="text" name="diagnosa_pre_bedah" value="<?php echo casV('diagnosa_pre_bedah'); ?>" placeholder="Diagnosa pra-bedah..." style="flex:1;">
            </div>

            <!-- Baris 2: Tindakan | Diagnosa Pasca-Bedah -->
            <div class="cas-row">
                <label>Tindakan :</label>
                <input type="text" name="tindakan_jenis_pembedahan" value="<?php echo casV('tindakan_jenis_pembedahan'); ?>" placeholder="Jenis pembedahan/tindakan..." style="flex:1;">
                <label style="margin-left:10px;">Diagnosa Paska-Bedah :</label>
                <input type="text" name="diagnosa_pasca_bedah" value="<?php echo casV('diagnosa_pasca_bedah'); ?>" placeholder="Diagnosa pasca-bedah..." style="flex:1;">
            </div>

            <!-- Baris 3: DPJP Anestesi | DPJP Bedah -->
            <div class="form-grid cols-2" style="margin-top:6px;">
                <div class="form-group cas-ac-wrap">
                    <label>DPJP Anestesi</label>
                    <input type="hidden" name="kd_dokter_anestesi" id="cas_kd_dokter_anestesi" value="<?php echo casV('kd_dokter_anestesi'); ?>">
                    <input type="text" id="cas_nm_dokter_anestesi" placeholder="Ketik nama DPJP Anestesi..." autocomplete="off"
                           value="<?php echo $isEdit && isset($rsCheck['nm_dokter_anestesi']) ? htmlspecialchars($rsCheck['nm_dokter_anestesi']) : ''; ?>">
                    <div id="cas_ac_dokter_anestesi" class="cas-ac-dd"></div>
                </div>
                <div class="form-group cas-ac-wrap">
                    <label>DPJP Bedah</label>
                    <input type="hidden" name="kd_dokter_bedah" id="cas_kd_dokter_bedah" value="<?php echo casV('kd_dokter_bedah'); ?>">
                    <input type="text" id="cas_nm_dokter_bedah" placeholder="Ketik nama DPJP Bedah..." autocomplete="off"
                           value="<?php echo $isEdit && isset($rsCheck['nm_dokter_bedah']) ? htmlspecialchars($rsCheck['nm_dokter_bedah']) : ''; ?>">
                    <div id="cas_ac_dokter_bedah" class="cas-ac-dd"></div>
                </div>
            </div>

            <!-- Baris 4: Pr. Anestesi | Pr. Bedah -->
            <div class="form-grid cols-2" style="margin-top:6px;">
                <div class="form-group cas-ac-wrap">
                    <label>Pr. Anestesi</label>
                    <input type="hidden" name="nip_perawat_anestesi" id="cas_nip_perawat_anestesi" value="<?php echo casV('nip_perawat_anestesi'); ?>">
                    <input type="text" id="cas_nm_perawat_anestesi" placeholder="Ketik nama perawat anestesi..." autocomplete="off"
                           value="<?php echo $isEdit && isset($rsCheck['nm_perawat_anestesi']) ? htmlspecialchars($rsCheck['nm_perawat_anestesi']) : ''; ?>">
                    <div id="cas_ac_perawat_anestesi" class="cas-ac-dd"></div>
                </div>
                <div class="form-group cas-ac-wrap">
                    <label>Pr. Bedah</label>
                    <input type="hidden" name="nip_perawat_ok" id="cas_nip_perawat_ok" value="<?php echo casV('nip_perawat_ok'); ?>">
                    <input type="text" id="cas_nm_perawat_ok" placeholder="Ketik nama perawat bedah..." autocomplete="off"
                           value="<?php echo $isEdit && isset($rsCheck['nm_perawat_bedah']) ? htmlspecialchars($rsCheck['nm_perawat_bedah']) : ''; ?>">
                    <div id="cas_ac_perawat_ok" class="cas-ac-dd"></div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             SECTION 2 : I. PENGKAJIAN PRA INDUKSI
             ============================================================ -->
        <div class="section">
            <div class="section-header"><i class="material-icons">monitor_heart</i><h2>I. Pengkajian Pra Induksi</h2></div>

            <!-- Jam & Kesadaran -->
            <div class="form-grid cols-3">
                <div class="form-group">
                    <label>Jam</label>
                    <input type="text" name="pre_induksi_jam" value="<?php echo casV('pre_induksi_jam'); ?>" placeholder="HH:MM">
                </div>
                <div class="form-group">
                    <label>Kesadaran</label>
                    <select name="pre_induksi_kesadaran">
                        <option value="Compos Mentis" <?php echo ($data['pre_induksi_kesadaran'] == 'Compos Mentis') ? 'selected' : ''; ?>>Compos Mentis</option>
                        <option value="Somnolence"    <?php echo ($data['pre_induksi_kesadaran'] == 'Somnolence')    ? 'selected' : ''; ?>>Somnolence</option>
                        <option value="Sopor"         <?php echo ($data['pre_induksi_kesadaran'] == 'Sopor')         ? 'selected' : ''; ?>>Sopor</option>
                        <option value="Coma"          <?php echo ($data['pre_induksi_kesadaran'] == 'Coma')          ? 'selected' : ''; ?>>Coma</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rhesus</label>
                    <select name="pre_induksi_rhesus">
                        <option value="+" <?php echo ($data['pre_induksi_rhesus'] == '+') ? 'selected' : ''; ?>>+</option>
                        <option value="-" <?php echo ($data['pre_induksi_rhesus'] == '-') ? 'selected' : ''; ?>>-</option>
                    </select>
                </div>
            </div>

            <!-- Tanda-Tanda Vital -->
            <div class="section-subtitle">Tanda-Tanda Vital</div>
            <div class="vital-grid">
                <div class="vital-item">
                    <label>TD (mmHg)</label>
                    <input type="text" name="pre_induksi_td" value="<?php echo casV('pre_induksi_td'); ?>" placeholder="120/80">
                </div>
                <div class="vital-item">
                    <label>Nadi (x/mnt)</label>
                    <input type="text" name="pre_induksi_nadi" value="<?php echo casV('pre_induksi_nadi'); ?>" placeholder="80">
                </div>
                <div class="vital-item">
                    <label>RR (x/mnt)</label>
                    <input type="text" name="pre_induksi_rr" value="<?php echo casV('pre_induksi_rr'); ?>" placeholder="20">
                </div>
                <div class="vital-item">
                    <label>Suhu (°C)</label>
                    <input type="text" name="pre_induksi_suhu" value="<?php echo casV('pre_induksi_suhu'); ?>" placeholder="36.5">
                </div>
                <div class="vital-item">
                    <label>Saturasi O2 (%)</label>
                    <input type="text" name="pre_induksi_o2" value="<?php echo casV('pre_induksi_o2'); ?>" placeholder="99">
                </div>
                <div class="vital-item">
                    <label>TB (cm)</label>
                    <input type="text" name="pre_induksi_tb" value="<?php echo casV('pre_induksi_tb'); ?>" placeholder="165">
                </div>
                <div class="vital-item">
                    <label>BB (kg)</label>
                    <input type="text" name="pre_induksi_bb" value="<?php echo casV('pre_induksi_bb'); ?>" placeholder="60">
                </div>
            </div>

            <!-- Data Lab -->
            <div class="section-subtitle">Data Laboratorium</div>
            <div class="vital-grid">
                <div class="vital-item">
                    <label>HB (gr/dl)</label>
                    <input type="text" name="pre_induksi_hb" value="<?php echo casV('pre_induksi_hb'); ?>" placeholder="12">
                </div>
                <div class="vital-item">
                    <label>HT (%)</label>
                    <input type="text" name="pre_induksi_ht" value="<?php echo casV('pre_induksi_ht'); ?>" placeholder="38">
                </div>
                <div class="vital-item">
                    <label>Leko (ul)</label>
                    <input type="text" name="pre_induksi_leko" value="<?php echo casV('pre_induksi_leko'); ?>" placeholder="8000">
                </div>
                <div class="vital-item">
                    <label>Trombo (ul)</label>
                    <input type="text" name="pre_induksi_trombo" value="<?php echo casV('pre_induksi_trombo'); ?>" placeholder="250">
                </div>
                <div class="vital-item">
                    <label>BT-CT (mnt)</label>
                    <input type="text" name="pre_induksi_btct" value="<?php echo casV('pre_induksi_btct'); ?>" placeholder="2/8">
                </div>
                <div class="vital-item">
                    <label>GDS (MG/dl)</label>
                    <input type="text" name="pre_induksi_gds" value="<?php echo casV('pre_induksi_gds'); ?>" placeholder="100">
                </div>
            </div>

            <!-- Lain-lain -->
            <div class="form-group" style="margin-top:10px;">
                <label>Lain-lain</label>
                <input type="text" name="pre_induksi_lainlain" value="<?php echo casV('pre_induksi_lainlain'); ?>" placeholder="Keterangan tambahan...">
            </div>
        </div>

        <!-- ============================================================
             SECTION 3 : II. TEKNIK & ALAT KHUSUS
             ============================================================ -->
        <div class="section">
            <div class="section-header"><i class="material-icons">build</i><h2>II. Teknik &amp; Alat Khusus</h2></div>
            <div class="cas-inline-grid cols-4">
                <div class="cas-inline-item"><label>TCI :</label><?php echo casOpts('teknik_alat_tci', $data['teknik_alat_tci'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>Glidescope :</label><?php echo casOpts('teknik_alat_glidescopi', $data['teknik_alat_glidescopi'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>Stimulator Saraf :</label><?php echo casOpts('teknik_alat_stimulator_saraf', $data['teknik_alat_stimulator_saraf'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>CPB :</label><?php echo casOpts('teknik_alat_cpb', $data['teknik_alat_cpb'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>USG :</label><?php echo casOpts('teknik_alat_usg', $data['teknik_alat_usg'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>Ventilator :</label><?php echo casOpts('teknik_alat_ventilasi', $data['teknik_alat_ventilasi'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>Broncoskopy :</label><?php echo casOpts('teknik_alat_broncoskopy', $data['teknik_alat_broncoskopy'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>Hipotensi :</label><?php echo casOpts('teknik_alat_hiopotensi', $data['teknik_alat_hiopotensi'], ['Tidak','Ya']); ?></div>
            </div>
            <div class="cas-row" style="margin-top:6px;">
                <label>Lainnya :</label>
                <input type="text" name="teknik_alat_lainlain" value="<?php echo casV('teknik_alat_lainlain'); ?>" placeholder="Teknik / alat lainnya..." style="flex:1;">
            </div>
        </div>

        <!-- ============================================================
             SECTION 4 : III. MONITORING
             ============================================================ -->
        <div class="section">
            <div class="section-header"><i class="material-icons">monitor</i><h2>III. Monitoring</h2></div>
            <div class="cas-inline-grid cols-4">
                <div class="cas-inline-item"><label>EtCO2 :</label><?php echo casOpts('monitoring_etco', $data['monitoring_etco'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>Stetoskop :</label><?php echo casOpts('monitoring_stetoskop', $data['monitoring_stetoskop'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>Cath A Pulmo :</label><?php echo casOpts('monitoring_cath_a_pulmo', $data['monitoring_cath_a_pulmo'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>NGT :</label><?php echo casOpts('monitoring_ngt', $data['monitoring_ngt'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>SpO2 :</label><?php echo casOpts('monitoring_spo2', $data['monitoring_spo2'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>NIBP :</label><?php echo casOpts('monitoring_nibp', $data['monitoring_nibp'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>Kateter Urine :</label><?php echo casOpts('monitoring_kateter', $data['monitoring_kateter'], ['Tidak','Ya']); ?></div>
                <div class="cas-inline-item"><label>BIS :</label><?php echo casOpts('monitoring_bis', $data['monitoring_bis'], ['Tidak','Ya']); ?></div>
            </div>
            <!-- CVP + Arteri Line + Temp -->
            <div class="cas-inline-grid cols-3" style="margin-top:6px;">
                <div class="cas-inline-item">
                    <label>CVP :</label><?php echo casOpts('monitoring_cvp', $data['monitoring_cvp'], ['Tidak','Ya']); ?>
                    <input type="text" name="monitoring_cvp_keterangan" value="<?php echo casV('monitoring_cvp_keterangan'); ?>" placeholder="Keterangan...">
                </div>
                <div class="cas-inline-item">
                    <label>Arteri Line :</label><?php echo casOpts('monitoring_arteri', $data['monitoring_arteri'], ['Tidak','Ya']); ?>
                    <input type="text" name="monitoring_arteri_keterangan" value="<?php echo casV('monitoring_arteri_keterangan'); ?>" placeholder="Keterangan...">
                </div>
                <div class="cas-inline-item">
                    <label>Temp. :</label><?php echo casOpts('monitoring_temp', $data['monitoring_temp'], ['Tidak','Ya']); ?>
                </div>
            </div>
            <!-- EKG Lead + Lain-lain -->
            <div class="cas-inline-grid cols-2" style="margin-top:6px;">
                <div class="cas-inline-item">
                    <label>EKG Lead :</label><?php echo casOpts('monitoring_ekg', $data['monitoring_ekg'], ['Tidak','Ya']); ?>
                    <input type="text" name="monitoring_ekg_keterangan" value="<?php echo casV('monitoring_ekg_keterangan'); ?>" placeholder="Keterangan...">
                </div>
                <div class="cas-inline-item">
                    <label>Lain-lain :</label>
                    <input type="text" name="monitoring_lainlain" value="<?php echo casV('monitoring_lainlain'); ?>" placeholder="Monitoring lainnya...">
                </div>
            </div>
        </div>

        <!-- ============================================================
             SECTION 5 : IV. STATUS FISIK
             ============================================================ -->
        <div class="section">
            <div class="section-header"><i class="material-icons">accessibility_new</i><h2>IV. Status Fisik</h2></div>
            <div class="cas-row">
                <label>Angka ASA :</label>
                <?php echo casOpts('status_fisik_asa', $data['status_fisik_asa'], ['1','2','3','4','5','E']); ?>
                <label style="margin-left:10px;">Alergi :</label>
                <?php echo casOpts('status_fisik_alergi', $data['status_fisik_alergi'], ['Tidak','Ya']); ?>
                <input type="text" name="status_fisik_alergi_keterangan" value="<?php echo casV('status_fisik_alergi_keterangan'); ?>" placeholder="Keterangan alergi..." style="flex:1;">
            </div>
            <div class="cas-row">
                <label>Penyulit Pra :</label>
                <input type="text" name="status_fisik_penyulit_sedasi" value="<?php echo casV('status_fisik_penyulit_sedasi'); ?>" placeholder="Penyulit pra sedasi..." style="flex:1;">
            </div>
        </div>

        <!-- ============================================================
             SECTION 6 : V. PERENCANAAN
             ============================================================ -->
        <div class="section">
            <div class="section-header"><i class="material-icons">assignment</i><h2>V. Perencanaan</h2></div>

            <!-- Lanjut Tindakan -->
            <div class="cas-row">
                <label>Lanjut Tindakan :</label>
                <?php echo casOpts('perencanaan_lanjut', $data['perencanaan_lanjut'], ['Ya','Tidak']); ?>
            </div>

            <!-- Sedasi | Epidural | Spinal -->
            <div class="cas-inline-grid cols-3" style="margin-top:6px;">
                <div class="cas-inline-item">
                    <label>Sedasi :</label>
                    <?php echo casOpts('perencanaan_lanjut_sedasi', $data['perencanaan_lanjut_sedasi'], ['Tidak','Sedang','Dalam','Lain-lain']); ?>
                    <input type="text" name="perencanaan_lanjut_sedasi_keterangan" value="<?php echo casV('perencanaan_lanjut_sedasi_keterangan'); ?>" placeholder="Keterangan...">
                </div>
                <div class="cas-inline-item">
                    <label>Epidural :</label>
                    <?php echo casOpts('perencanaan_lanjut_epidural', $data['perencanaan_lanjut_epidural'], ['Tidak','Ya']); ?>
                </div>
                <div class="cas-inline-item">
                    <label>Spinal :</label>
                    <?php echo casOpts('perencanaan_lanjut_spinal', $data['perencanaan_lanjut_spinal'], ['Tidak','Ya']); ?>
                </div>
            </div>

            <!-- Anestesi Umum | Blok Perifer -->
            <div class="cas-inline-grid cols-2" style="margin-top:6px;">
                <div class="cas-inline-item">
                    <label>Anastesi Umum :</label>
                    <?php echo casOpts('perencanaan_lanjut_anestesi_umum', $data['perencanaan_lanjut_anestesi_umum'], ['Tidak','Ya']); ?>
                    <input type="text" name="perencanaan_lanjut_anestesi_umum_keterangan" value="<?php echo casV('perencanaan_lanjut_anestesi_umum_keterangan'); ?>" placeholder="Keterangan...">
                </div>
                <div class="cas-inline-item">
                    <label>Blok Perifer :</label>
                    <?php echo casOpts('perencanaan_lanjut_blok_perifer', $data['perencanaan_lanjut_blok_perifer'], ['Tidak','Ya']); ?>
                    <input type="text" name="perencanaan_lanjut_blok_perifer_keterangan" value="<?php echo casV('perencanaan_lanjut_blok_perifer_keterangan'); ?>" placeholder="Keterangan...">
                </div>
            </div>

            <!-- Batal Tindakan -->
            <div class="cas-row" style="margin-top:6px;">
                <label>Batal Tindakan :</label>
                <?php echo casOpts('perencanaan_batal', $data['perencanaan_batal'], ['Tidak','Ya']); ?>
                <input type="text" name="perencanaan_batal_alasan" value="<?php echo casV('perencanaan_batal_alasan'); ?>" placeholder="Alasan pembatalan..." style="flex:1;">
            </div>
        </div>

        </form>
        </div><!-- /form-content -->

        <!-- ACTION BAR -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliCatatanAnestesiSedasi()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-cas" class="btn btn-danger" onclick="confirmDeleteCatatanAnestesiSedasi()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter bedah / dokter anestesi yang dapat menghapus">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-cas" form="formCatatanAnestesiSedasi" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:13px;"><strong>Info:</strong> Hanya <strong>Dokter Bedah</strong> atau <strong>Dokter Anestesi</strong> yang dapat menghapus data ini.</span>
        </div>
        <?php endif; ?>

    </div><!-- /form-card -->
</div><!-- /modern-form-container -->

<script src="<?php echo BASE_URL_CAS; ?>/js/catatananestesisedasi.js?v=<?php echo time(); ?>"></script>