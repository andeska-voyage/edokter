<?php
/**
 * timeoutsebeluminsisi.php
 * Form Timeout Sebelum Insisi - E-Dokter SIMRS Khanza
 */
define('BASE_URL_TSI', APP_BASE_URL);

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

// Ambil nama tindakan dari booking_operasi
$nama_tindakan_booking = '';
$queryBooking = bukaquery("SELECT pk.nm_perawatan 
                           FROM booking_operasi bo
                           LEFT JOIN paket_operasi pk ON bo.kode_paket = pk.kode_paket
                           WHERE bo.no_rawat = '$no_rawat'
                           ORDER BY bo.tanggal DESC LIMIT 1");
$rsBooking = mysqli_fetch_array($queryBooking);
if($rsBooking) { $nama_tindakan_booking = $rsBooking['nm_perawatan']; }

// Cek data existing
$queryCheck = bukaquery("SELECT tsi.*,
                            db.nm_dokter as nm_dokter_bedah, da.nm_dokter as nm_dokter_anestesi,
                            pok.nama as nm_perawat_ok
                         FROM timeout_sebelum_insisi tsi
                         LEFT JOIN dokter db  ON tsi.kd_dokter_bedah = db.kd_dokter
                         LEFT JOIN dokter da  ON tsi.kd_dokter_anestesi = da.kd_dokter
                         LEFT JOIN petugas pok ON tsi.nip_perawat_ok = pok.nip
                         WHERE tsi.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;

$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'sncn' => '',
    'tindakan' => $nama_tindakan_booking,
    'kd_dokter_bedah' => '', 'kd_dokter_anestesi' => '',
    'verbal_identitas' => 'Ya', 'verbal_tindakan' => 'Ya', 'verbal_area_insisi' => 'Ya',
    'penandaan_area_operasi' => 'Ada',
    'lama_operasi' => '',
    'penayangan_radiologi' => 'Ditayangkan', 'penayangan_ctscan' => 'Ditayangkan', 'penayangan_mri' => 'Ditayangkan',
    'antibiotik_profilaks' => 'Ya', 'nama_antibiotik' => '', 'jam_pemberian' => '',
    'antisipasi_kehilangan_darah' => '',
    'hal_khusus' => 'Ada', 'hal_khusus_diperhatikan' => '',
    'tanggal_steril' => date('Y-m-d'),
    'petujuk_sterilisasi' => 'Ya', 'verifikasi_preoperatif' => 'Ya',
    'nip_perawat_ok' => ''
);
if($isEdit) { $data = array_merge($data, $rsCheck); }

$bolehHapus = false;
if($isEdit && ($kd_dokter_login === $data['kd_dokter_bedah'] || $kd_dokter_login === $data['kd_dokter_anestesi'])) {
    $bolehHapus = true;
}

function tsiOpts($name, $value, $opts) {
    $h = '<select name="'.$name.'">';
    foreach($opts as $o) { $s=($value==$o)?'selected':''; $h.='<option value="'.$o.'" '.$s.'>'.$o.'</option>'; }
    return $h.'</select>';
}
function tsiV($key) { global $data; return htmlspecialchars($data[$key] ?? ''); }
?>

<link rel="stylesheet" href="<?php echo BASE_URL_TSI; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
.tsi-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:5px 8px; background:#f8fafc; border-radius:5px; border:1px solid #e2e8f0; margin-bottom:5px; }
.tsi-row label { font-size:11px; font-weight:600; color:#475569; white-space:nowrap; }
.tsi-row select { padding:4px 6px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; min-width:80px; }
.tsi-row input[type="text"] { padding:4px 6px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; flex:1; min-width:80px; }
.tsi-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:5px; }
.tsi-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:5px; }
.tsi-grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:5px; }
.tsi-section-title { font-size:11px; font-weight:700; color:#1e40af; margin:10px 0 6px; }
/* Autocomplete */
.tsi-ac-wrap { position:relative; }
.tsi-ac-dd { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:0 0 8px 8px; box-shadow:0 4px 12px rgba(0,0,0,.15); max-height:200px; overflow-y:auto; z-index:99; display:none; }
.tsi-ac-dd.show { display:block; }
.tsi-ac-dd div { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; font-size:12px; }
.tsi-ac-dd div:hover { background:#f0f9ff; }
.tsi-ac-dd div strong { color:#1e40af; }
.tsi-ac-dd .no-result { color:#94a3b8; text-align:center; font-style:italic; }
@media(max-width:768px){ .tsi-grid-2,.tsi-grid-3,.tsi-grid-4 { grid-template-columns:1fr; } }
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">timer</i>
                TIMEOUT SEBELUM INSISI
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
            <form id="formTimeoutSebelumInsisi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">

                <!-- Data Umum -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">event_note</i><h2>Data Timeout Sebelum Insisi</h2></div>
                    <!-- Baris 1: Tanggal | SN/CN | Dokter Bedah -->
                    <div class="tsi-row">
                        <label>Tanggal :</label>
                        <input type="hidden" name="tanggal" value="<?php echo date('Y-m-d H:i:s', strtotime($data['tanggal'])); ?>">
                        <input type="text" value="<?php echo date('d-m-Y H:i:s', strtotime($data['tanggal'])); ?>" readonly style="flex:0 0 150px;">
                        <label>SN/CN :</label>
                        <input type="text" name="sncn" value="<?php echo tsiV('sncn'); ?>" placeholder="SN/CN" style="flex:0 0 100px;">
                    </div>
                    <!-- Baris 2: Dokter Bedah | Dokter Anestesi -->
                    <div class="form-grid cols-2" style="margin-top:6px;">
                        <div class="form-group tsi-ac-wrap">
                            <label>Dokter Bedah</label>
                            <input type="hidden" name="kd_dokter_bedah" id="tsi_kd_dokter_bedah" value="<?php echo tsiV('kd_dokter_bedah'); ?>">
                            <input type="text" id="tsi_nm_dokter_bedah" placeholder="Ketik nama dokter bedah..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_bedah']) ? htmlspecialchars($rsCheck['nm_dokter_bedah']) : ''; ?>">
                            <div id="tsi_ac_dokter_bedah" class="tsi-ac-dd"></div>
                        </div>
                        <div class="form-group tsi-ac-wrap">
                            <label>Dokter Anestesi</label>
                            <input type="hidden" name="kd_dokter_anestesi" id="tsi_kd_dokter_anestesi" value="<?php echo tsiV('kd_dokter_anestesi'); ?>">
                            <input type="text" id="tsi_nm_dokter_anestesi" placeholder="Ketik nama dokter anestesi..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_anestesi']) ? htmlspecialchars($rsCheck['nm_dokter_anestesi']) : ''; ?>">
                            <div id="tsi_ac_dokter_anestesi" class="tsi-ac-dd"></div>
                        </div>
                    </div>
                    <!-- Baris 3: Tindakan -->
                    <div class="form-group" style="margin-top:6px;">
                        <label>Tindakan</label>
                        <input type="text" name="tindakan" value="<?php echo tsiV('tindakan'); ?>" placeholder="Nama tindakan operasi...">
                    </div>
                </div>

                <!-- Konfirmasi -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">fact_check</i><h2>Konfirmasi Dipimpin Oleh Salah Satu Anggota Tim</h2></div>

                    <div class="tsi-section-title">Verbalisasi Tim, Konfirmasi :</div>
                    <div class="tsi-grid-4">
                        <div class="tsi-row"><label>Identitas :</label><?php echo tsiOpts('verbal_identitas', $data['verbal_identitas'], ['Ya','Tidak']); ?></div>
                        <div class="tsi-row"><label>Tindakan :</label><?php echo tsiOpts('verbal_tindakan', $data['verbal_tindakan'], ['Ya','Tidak']); ?></div>
                        <div class="tsi-row"><label>Area Insisi :</label><?php echo tsiOpts('verbal_area_insisi', $data['verbal_area_insisi'], ['Ya','Tidak']); ?></div>
                        <div class="tsi-row"><label>Penandaan Area Operasi :</label><?php echo tsiOpts('penandaan_area_operasi', $data['penandaan_area_operasi'], ['Ada','Tidak Ada','Tidak Diperlukan']); ?></div>
                    </div>
                    <div class="tsi-row" style="margin-top:5px;">
                        <label>Perkiraan Lama Operasi :</label>
                        <input type="text" name="lama_operasi" value="<?php echo tsiV('lama_operasi'); ?>" placeholder="" style="flex:0 0 80px;">
                        <span style="font-size:11px;color:#64748b;">Jam</span>
                    </div>

                    <div class="tsi-section-title">Penayangan Hasil Pemeriksaan Penunjang :</div>
                    <div class="tsi-grid-3">
                        <div class="tsi-row"><label>Radiologi :</label><?php echo tsiOpts('penayangan_radiologi', $data['penayangan_radiologi'], ['Ditayangkan','Benar','Tidak Diperlukan']); ?></div>
                        <div class="tsi-row"><label>CT Scan :</label><?php echo tsiOpts('penayangan_ctscan', $data['penayangan_ctscan'], ['Ditayangkan','Benar','Tidak Diperlukan']); ?></div>
                        <div class="tsi-row"><label>MRI :</label><?php echo tsiOpts('penayangan_mri', $data['penayangan_mri'], ['Ditayangkan','Benar','Tidak Diperlukan']); ?></div>
                    </div>

                    <!-- Antibiotik Profilaksis -->
                    <div class="tsi-row" style="margin-top:5px;">
                        <label>Pemberian Antibiotik Profilaksis :</label>
                        <?php echo tsiOpts('antibiotik_profilaks', $data['antibiotik_profilaks'], ['Ya','Tidak']); ?>
                        <label>, Jika Diberikan :</label>
                        <input type="text" name="nama_antibiotik" value="<?php echo tsiV('nama_antibiotik'); ?>" placeholder="Nama antibiotik...">
                        <label>, Jam Pemberian :</label>
                        <input type="text" name="jam_pemberian" value="<?php echo tsiV('jam_pemberian'); ?>" placeholder="HH:MM" style="flex:0 0 80px;">
                    </div>

                    <!-- Antisipasi Kehilangan Darah -->
                    <div class="tsi-row">
                        <label>Antisipasi Kehilangan Darah &gt; 500 ml (7 ml/Kg BB Untuk Anak) :</label>
                        <input type="text" name="antisipasi_kehilangan_darah" value="<?php echo tsiV('antisipasi_kehilangan_darah'); ?>" placeholder="Keterangan...">
                    </div>

                    <!-- Hal Khusus -->
                    <div class="tsi-row">
                        <label>Hal Khusus Yang Perlu Diperhatikan :</label>
                        <?php echo tsiOpts('hal_khusus', $data['hal_khusus'], ['Ada','Tidak Ada']); ?>
                        <label>, Jika Ada :</label>
                        <input type="text" name="hal_khusus_diperhatikan" value="<?php echo tsiV('hal_khusus_diperhatikan'); ?>" placeholder="Keterangan...">
                    </div>

                    <!-- Tanggal Steril, Petunjuk Sterilisasi, Verifikasi -->
                    <div class="tsi-row">
                        <label>Tanggal Steril :</label>
                        <input type="date" name="tanggal_steril" value="<?php echo $data['tanggal_steril'] ? date('Y-m-d', strtotime($data['tanggal_steril'])) : ''; ?>" style="flex:0 0 150px; padding:4px 6px; border:1px solid #e2e8f0; border-radius:4px; font-size:11px;">
                        <label>Petunjuk Sterilisasi Telah Dikonfirmasi :</label>
                        <?php echo tsiOpts('petujuk_sterilisasi', $data['petujuk_sterilisasi'], ['Ya','Tidak']); ?>
                        <label>Verifikasi Pre Operatif Telah Dilakukan :</label>
                        <?php echo tsiOpts('verifikasi_preoperatif', $data['verifikasi_preoperatif'], ['Ya','Tidak']); ?>
                    </div>
                </div>

                <!-- Perawat Kamar Operasi -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">people</i><h2>Perawat Kamar Operasi</h2></div>
                    <div class="form-grid cols-1">
                        <div class="form-group tsi-ac-wrap">
                            <label>Perawat Kamar Operasi</label>
                            <input type="hidden" name="nip_perawat_ok" id="tsi_nip_perawat_ok" value="<?php echo tsiV('nip_perawat_ok'); ?>">
                            <input type="text" id="tsi_nm_perawat_ok" placeholder="Ketik nama perawat kamar operasi..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_perawat_ok']) ? htmlspecialchars($rsCheck['nm_perawat_ok']) : ''; ?>">
                            <div id="tsi_ac_perawat_ok" class="tsi-ac-dd"></div>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliTimeoutSebelumInsisi()"><i class="material-icons">arrow_back</i> KEMBALI</button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-tsi" class="btn btn-danger" onclick="confirmDeleteTimeoutSebelumInsisi()"><i class="material-icons">delete</i> HAPUS</button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter bedah / dokter anestesi yang dapat menghapus"><i class="material-icons">lock</i> HAPUS</button>
            <?php endif; ?>
            <button type="submit" id="btn-save-tsi" form="formTimeoutSebelumInsisi" class="btn btn-primary"><i class="material-icons">save</i> SIMPAN</button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:13px;"><strong>Info:</strong> Hanya <strong>Dokter Bedah</strong> atau <strong>Dokter Anestesi</strong> yang dapat menghapus data ini.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL_TSI; ?>/js/timeoutsebeluminsisi.js?v=<?php echo time(); ?>"></script>
