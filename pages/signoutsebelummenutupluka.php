<?php
/**
 * signoutsebelummenutupluka.php
 * Form Sign Out Sebelum Menutup Luka - E-Dokter SIMRS Khanza
 */
define('BASE_URL_SOML', APP_BASE_URL);

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
$queryCheck = bukaquery("SELECT soml.*,
                            db.nm_dokter  as nm_dokter_bedah,
                            da.nm_dokter  as nm_dokter_anestesi,
                            pok.nama      as nm_perawat_ok
                         FROM signout_sebelum_menutup_luka soml
                         LEFT JOIN dokter  db  ON soml.kd_dokter_bedah    = db.kd_dokter
                         LEFT JOIN dokter  da  ON soml.kd_dokter_anestesi = da.kd_dokter
                         LEFT JOIN petugas pok ON soml.nip_perawat_ok     = pok.nip
                         WHERE soml.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;

$data = array(
    'tanggal'                               => date('Y-m-d H:i:s'),
    'sncn'                                  => '',
    'tindakan'                              => $nama_tindakan_booking,
    'kd_dokter_bedah'                       => '',
    'kd_dokter_anestesi'                    => '',
    'verbal_tindakan'                       => 'Ya',
    'verbal_kelengkapan_kasa'               => 'Ya',
    'verbal_instrumen'                      => 'Ya',
    'verbal_alat_tajam'                     => 'Ya',
    'kelengkapan_specimen_label'            => 'Lengkap',
    'kelengkapan_specimen_formulir'         => 'Lengkap',
    'peninjauan_kegiatan_dokter_bedah'      => 'Ya',
    'peninjauan_kegiatan_dokter_anestesi'   => 'Ya',
    'peninjauan_kegiatan_perawat_kamar_ok'  => 'Ya',
    'perhatian_utama_fase_pemulihan'        => '',
    'nip_perawat_ok'                        => ''
);
if($isEdit) { $data = array_merge($data, $rsCheck); }

$bolehHapus = false;
if($isEdit && ($kd_dokter_login === $data['kd_dokter_bedah'] || $kd_dokter_login === $data['kd_dokter_anestesi'])) {
    $bolehHapus = true;
}

function somlOpts($name, $value, $opts) {
    $h = '<select name="'.$name.'">';
    foreach($opts as $o) { $s = ($value == $o) ? 'selected' : ''; $h .= '<option value="'.$o.'" '.$s.'>'.$o.'</option>'; }
    return $h.'</select>';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_SOML; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
.soml-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 6px 8px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 6px; }
.soml-row label { font-size: 11px; font-weight: 600; color: #475569; white-space: nowrap; }
.soml-row select, .soml-row input[type="text"] { padding: 5px 8px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 12px; }
.soml-row input[type="text"] { flex: 1; min-width: 120px; }
.soml-row select { min-width: 100px; }
.soml-konfirmasi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
.soml-konfirmasi-item { display: grid; grid-template-columns: 1fr auto; gap: 5px; align-items: center; padding: 6px 8px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; }
.soml-konfirmasi-item label { font-size: 10px; font-weight: 500; color: #475569; }
.soml-konfirmasi-item select { padding: 4px 6px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 10px; }
.soml-specimen-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 8px; }
.soml-peninjauan-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 8px; }
/* Autocomplete */
.soml-ac-wrap { position: relative; }
.soml-ac-dd { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,.15); max-height: 200px; overflow-y: auto; z-index: 99; display: none; }
.soml-ac-dd.show { display: block; }
.soml-ac-dd div { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
.soml-ac-dd div:hover { background: #f0f9ff; }
.soml-ac-dd div strong { color: #1e40af; }
.soml-ac-dd .no-result { color: #94a3b8; text-align: center; font-style: italic; }
@media (max-width: 768px) {
    .soml-konfirmasi-grid { grid-template-columns: repeat(2, 1fr); }
    .soml-specimen-grid   { grid-template-columns: 1fr; }
    .soml-peninjauan-grid { grid-template-columns: 1fr; }
}
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">assignment_turned_in</i>
                SIGN OUT SEBELUM MENUTUP LUKA
                <?php if($isEdit): ?><span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?><span class="mode-badge mode-add">➕ NEW</span><?php endif; ?>
            </h1>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-content">
            <form id="formSignOutMenutupLuka" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">

                <!-- ===================== DATA OPERASI ===================== -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">event_note</i><h2>Data Operasi</h2></div>

                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="text" name="tanggal" value="<?php echo date('d-m-Y H:i:s', strtotime($data['tanggal'])); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>SN/CN</label>
                            <input type="text" name="sncn" value="<?php echo htmlspecialchars($data['sncn']); ?>" placeholder="SN/CN">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top:8px;">
                        <div class="form-group soml-ac-wrap">
                            <label>Dokter Bedah</label>
                            <input type="hidden" name="kd_dokter_bedah" id="soml_kd_dokter_bedah" value="<?php echo htmlspecialchars($data['kd_dokter_bedah']); ?>">
                            <input type="text" id="soml_nm_dokter_bedah" placeholder="Ketik nama dokter bedah..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_bedah']) ? htmlspecialchars($rsCheck['nm_dokter_bedah']) : ''; ?>">
                            <div id="soml_ac_dokter_bedah" class="soml-ac-dd"></div>
                        </div>
                        <div class="form-group soml-ac-wrap">
                            <label>Dokter Anestesi</label>
                            <input type="hidden" name="kd_dokter_anestesi" id="soml_kd_dokter_anestesi" value="<?php echo htmlspecialchars($data['kd_dokter_anestesi']); ?>">
                            <input type="text" id="soml_nm_dokter_anestesi" placeholder="Ketik nama dokter anestesi..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_anestesi']) ? htmlspecialchars($rsCheck['nm_dokter_anestesi']) : ''; ?>">
                            <div id="soml_ac_dokter_anestesi" class="soml-ac-dd"></div>
                        </div>
                    </div>

                    <div class="form-grid cols-1" style="margin-top:8px;">
                        <div class="form-group">
                            <label>Tindakan</label>
                            <input type="text" name="tindakan" value="<?php echo htmlspecialchars($data['tindakan']); ?>" placeholder="Nama tindakan operasi...">
                        </div>
                    </div>
                </div>

                <!-- ===================== SEBELUM MENUTUP LUKA ===================== -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">fact_check</i><h2>Sebelum Menutup Luka &amp; Meninggalkan Kamar Operasi</h2></div>

                    <!-- Perawat Melakukan Konfirmasi Secara Verbal -->
                    <p class="section-subtitle">Perawat Melakukan Konfirmasi Secara Verbal :</p>
                    <div class="soml-konfirmasi-grid">
                        <div class="soml-konfirmasi-item">
                            <label>Tindakan :</label>
                            <?php echo somlOpts('verbal_tindakan', $data['verbal_tindakan'], ['Ya','Tidak']); ?>
                        </div>
                        <div class="soml-konfirmasi-item">
                            <label>Kelengkapan Kasa :</label>
                            <?php echo somlOpts('verbal_kelengkapan_kasa', $data['verbal_kelengkapan_kasa'], ['Ya','Tidak']); ?>
                        </div>
                        <div class="soml-konfirmasi-item">
                            <label>Instrumen :</label>
                            <?php echo somlOpts('verbal_instrumen', $data['verbal_instrumen'], ['Ya','Tidak']); ?>
                        </div>
                        <div class="soml-konfirmasi-item">
                            <label>Alat Tajam :</label>
                            <?php echo somlOpts('verbal_alat_tajam', $data['verbal_alat_tajam'], ['Ya','Tidak']); ?>
                        </div>
                    </div>

                    <!-- Kelengkapan Spesimen Jika Ada -->
                    <p class="section-subtitle" style="margin-top:12px;">Kelengkapan Spesimen Jika Ada :</p>
                    <div class="soml-specimen-grid">
                        <div class="soml-konfirmasi-item">
                            <label>Label :</label>
                            <?php echo somlOpts('kelengkapan_specimen_label', $data['kelengkapan_specimen_label'], ['Lengkap','Tidak Lengkap','Tidak Ada Pemeriksaan']); ?>
                        </div>
                        <div class="soml-konfirmasi-item">
                            <label>Formulir :</label>
                            <?php echo somlOpts('kelengkapan_specimen_formulir', $data['kelengkapan_specimen_formulir'], ['Lengkap','Tidak Lengkap','Tidak Ada Pemeriksaan']); ?>
                        </div>
                    </div>

                    <!-- Peninjauan Kembali Kegiatan -->
                    <p class="section-subtitle" style="margin-top:12px;">Peninjauan Kembali Kegiatan :</p>
                    <div class="soml-peninjauan-grid">
                        <div class="soml-konfirmasi-item">
                            <label>Dokter Bedah :</label>
                            <?php echo somlOpts('peninjauan_kegiatan_dokter_bedah', $data['peninjauan_kegiatan_dokter_bedah'], ['Ya','Tidak']); ?>
                        </div>
                        <div class="soml-konfirmasi-item">
                            <label>Dokter Anestesi :</label>
                            <?php echo somlOpts('peninjauan_kegiatan_dokter_anestesi', $data['peninjauan_kegiatan_dokter_anestesi'], ['Ya','Tidak']); ?>
                        </div>
                        <div class="soml-konfirmasi-item">
                            <label>Perawat Kamar Operasi :</label>
                            <?php echo somlOpts('peninjauan_kegiatan_perawat_kamar_ok', $data['peninjauan_kegiatan_perawat_kamar_ok'], ['Ya','Tidak']); ?>
                        </div>
                    </div>

                    <!-- Perhatian Utama Fase Pemulihan -->
                    <div class="form-grid cols-1" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Perhatian Utama Fase Pemulihan</label>
                            <input type="text" name="perhatian_utama_fase_pemulihan"
                                   value="<?php echo htmlspecialchars($data['perhatian_utama_fase_pemulihan']); ?>"
                                   placeholder="Catatan perhatian utama fase pemulihan...">
                        </div>
                    </div>
                </div>

                <!-- ===================== PERAWAT KAMAR OPERASI ===================== -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">people</i><h2>Perawat Kamar Operasi</h2></div>
                    <div class="form-grid cols-1">
                        <div class="form-group soml-ac-wrap">
                            <label>Perawat Kamar Operasi</label>
                            <input type="hidden" name="nip_perawat_ok" id="soml_nip_perawat_ok" value="<?php echo htmlspecialchars($data['nip_perawat_ok']); ?>">
                            <input type="text" id="soml_nm_perawat_ok" placeholder="Ketik nama perawat kamar operasi..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_perawat_ok']) ? htmlspecialchars($rsCheck['nm_perawat_ok']) : ''; ?>">
                            <div id="soml_ac_perawat_ok" class="soml-ac-dd"></div>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliSignOutMenutupLuka()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-soml" class="btn btn-danger" onclick="confirmDeleteSignOutMenutupLuka()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter bedah / dokter anestesi yang dapat menghapus">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-soml" form="formSignOutMenutupLuka" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:13px;"><strong>Info:</strong> Hanya <strong>Dokter Bedah</strong> atau <strong>Dokter Anestesi</strong> yang dapat menghapus data ini.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL_SOML; ?>/js/signoutsebelummenutupluka.js?v=<?php echo time(); ?>"></script>