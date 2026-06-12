<?php
/**
 * signinsebelumanestesi.php
 * Form Sign In Sebelum Anestesi - E-Dokter SIMRS Khanza
 */
define('BASE_URL_SIA', APP_BASE_URL);

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
$queryCheck = bukaquery("SELECT sia.*,
                            db.nm_dokter as nm_dokter_bedah, da.nm_dokter as nm_dokter_anestesi,
                            pok.nama as nm_perawat_ok
                         FROM signin_sebelum_anestesi sia
                         LEFT JOIN dokter db  ON sia.kd_dokter_bedah = db.kd_dokter
                         LEFT JOIN dokter da  ON sia.kd_dokter_anestesi = da.kd_dokter
                         LEFT JOIN petugas pok ON sia.nip_perawat_ok = pok.nip
                         WHERE sia.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;

$data = array(
    'tanggal' => date('Y-m-d H:i:s'), 'sncn' => '',
    'tindakan' => $nama_tindakan_booking,
    'kd_dokter_bedah' => '', 'kd_dokter_anestesi' => '',
    'identitas' => 'Ya', 'penandaan_area_operasi' => 'Ada',
    'alergi' => '',
    'resiko_aspirasi' => 'Ada', 'resiko_aspirasi_rencana_antisipasi' => '',
    'resiko_kehilangan_darah' => 'Tidak Ada', 'resiko_kehilangan_darah_line' => '',
    'resiko_kehilangan_darah_rencana_antisipasi' => '',
    'kesiapan_alat_obat_anestesi' => 'Lengkap', 'kesiapan_alat_obat_anestesi_rencana_antisipasi' => '',
    'nip_perawat_ok' => ''
);
if($isEdit) { $data = array_merge($data, $rsCheck); }

$bolehHapus = false;
if($isEdit && ($kd_dokter_login === $data['kd_dokter_bedah'] || $kd_dokter_login === $data['kd_dokter_anestesi'])) {
    $bolehHapus = true;
}

function siaOpts($name, $value, $opts) {
    $h = '<select name="'.$name.'">';
    foreach($opts as $o) { $s=($value==$o)?'selected':''; $h.='<option value="'.$o.'" '.$s.'>'.$o.'</option>'; }
    return $h.'</select>';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_SIA; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
.sia-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 6px 8px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 6px; }
.sia-row label { font-size: 11px; font-weight: 600; color: #475569; white-space: nowrap; }
.sia-row select, .sia-row input[type="text"] { padding: 5px 8px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 12px; }
.sia-row input[type="text"] { flex: 1; min-width: 120px; }
.sia-row select { min-width: 100px; }
.sia-konfirmasi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.sia-konfirmasi-item { display: grid; grid-template-columns: 1fr auto; gap: 5px; align-items: center; padding: 6px 8px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; }
.sia-konfirmasi-item label { font-size: 10px; font-weight: 500; color: #475569; }
.sia-konfirmasi-item select, .sia-konfirmasi-item input { padding: 4px 6px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 10px; }
/* Autocomplete */
.sia-ac-wrap { position: relative; }
.sia-ac-dd { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,.15); max-height: 200px; overflow-y: auto; z-index: 99; display: none; }
.sia-ac-dd.show { display: block; }
.sia-ac-dd div { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
.sia-ac-dd div:hover { background: #f0f9ff; }
.sia-ac-dd div strong { color: #1e40af; }
.sia-ac-dd .no-result { color: #94a3b8; text-align: center; font-style: italic; }
@media (max-width: 768px) { .sia-konfirmasi-grid { grid-template-columns: 1fr; } }
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">how_to_reg</i>
                SIGN IN SEBELUM ANESTESI
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
            <form id="formSignInAnestesi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">

                <!-- Data Operasi -->
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
                        <div class="form-group sia-ac-wrap">
                            <label>Dokter Bedah</label>
                            <input type="hidden" name="kd_dokter_bedah" id="sia_kd_dokter_bedah" value="<?php echo htmlspecialchars($data['kd_dokter_bedah']); ?>">
                            <input type="text" id="sia_nm_dokter_bedah" placeholder="Ketik nama dokter bedah..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_bedah']) ? htmlspecialchars($rsCheck['nm_dokter_bedah']) : ''; ?>">
                            <div id="sia_ac_dokter_bedah" class="sia-ac-dd"></div>
                        </div>
                        <div class="form-group sia-ac-wrap">
                            <label>Dokter Anestesi</label>
                            <input type="hidden" name="kd_dokter_anestesi" id="sia_kd_dokter_anestesi" value="<?php echo htmlspecialchars($data['kd_dokter_anestesi']); ?>">
                            <input type="text" id="sia_nm_dokter_anestesi" placeholder="Ketik nama dokter anestesi..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_anestesi']) ? htmlspecialchars($rsCheck['nm_dokter_anestesi']) : ''; ?>">
                            <div id="sia_ac_dokter_anestesi" class="sia-ac-dd"></div>
                        </div>
                    </div>
                    <div class="form-grid cols-1" style="margin-top:8px;">
                        <div class="form-group">
                            <label>Tindakan</label>
                            <input type="text" name="tindakan" value="<?php echo htmlspecialchars($data['tindakan']); ?>" placeholder="Nama tindakan operasi...">
                        </div>
                    </div>
                </div>

                <!-- Perawat OK & Tim Anestesi Mengkonfirmasi -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">fact_check</i><h2>Perawat OK &amp; Tim Anestesi Mengkonfirmasi</h2></div>

                    <div class="sia-konfirmasi-grid">
                        <div class="sia-konfirmasi-item"><label>Identitas :</label><?php echo siaOpts('identitas', $data['identitas'], ['Ya','Tidak']); ?></div>
                        <div class="sia-konfirmasi-item" style="grid-column:span 1;">
                            <label>Alergi :</label>
                            <input type="text" name="alergi" value="<?php echo htmlspecialchars($data['alergi']); ?>" placeholder="Alergi pasien..." style="padding:4px 6px;border:1px solid #e2e8f0;border-radius:4px;font-size:10px;">
                        </div>
                        <div class="sia-konfirmasi-item"><label>Penandaan Area Operasi :</label><?php echo siaOpts('penandaan_area_operasi', $data['penandaan_area_operasi'], ['Ada','Tidak Ada','Tidak Diperlukan']); ?></div>
                    </div>

                    <!-- Resiko Aspirasi -->
                    <div class="sia-row" style="margin-top:8px;">
                        <label>Resiko Aspirasi &amp; Faktor Penyulit :</label>
                        <?php echo siaOpts('resiko_aspirasi', $data['resiko_aspirasi'], ['Ada','Tidak Ada']); ?>
                        <label>Bila Ada Resiko, Rencana Antisipasi :</label>
                        <input type="text" name="resiko_aspirasi_rencana_antisipasi" value="<?php echo htmlspecialchars($data['resiko_aspirasi_rencana_antisipasi']); ?>" placeholder="Rencana antisipasi...">
                    </div>

                    <!-- Resiko Kehilangan Darah -->
                    <div class="sia-row">
                        <label>Resiko Kehilangan Darah &gt; 500 ml (7 ml/Kg Berat Badan Untuk Anak) :</label>
                        <?php echo siaOpts('resiko_kehilangan_darah', $data['resiko_kehilangan_darah'], ['Tidak Ada','Ada']); ?>
                        <label>Jika Ada, Jalur IV Line :</label>
                        <input type="text" name="resiko_kehilangan_darah_line" value="<?php echo htmlspecialchars($data['resiko_kehilangan_darah_line']); ?>" placeholder="Jalur IV Line..." style="max-width:150px;">
                    </div>
                    <div class="sia-row">
                        <label>Jika Ada Resiko Kehilangan Darah, Rencana Antisipasi :</label>
                        <input type="text" name="resiko_kehilangan_darah_rencana_antisipasi" value="<?php echo htmlspecialchars($data['resiko_kehilangan_darah_rencana_antisipasi']); ?>" placeholder="Rencana antisipasi...">
                    </div>

                    <!-- Kesiapan Alat & Obat Anestesi -->
                    <div class="sia-row">
                        <label>Kesiapan Alat &amp; Obat Anestesi :</label>
                        <?php echo siaOpts('kesiapan_alat_obat_anestesi', $data['kesiapan_alat_obat_anestesi'], ['Lengkap','Pulsa Oximetri','Tidak Lengkap']); ?>
                        <label>Bila Tidak Lengkap, Rencana Antisipasi :</label>
                        <input type="text" name="kesiapan_alat_obat_anestesi_rencana_antisipasi" value="<?php echo htmlspecialchars($data['kesiapan_alat_obat_anestesi_rencana_antisipasi']); ?>" placeholder="Rencana antisipasi...">
                    </div>
                </div>

                <!-- Perawat Kamar Operasi -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">people</i><h2>Perawat Kamar Operasi</h2></div>
                    <div class="form-grid cols-1">
                        <div class="form-group sia-ac-wrap">
                            <label>Perawat Kamar Operasi</label>
                            <input type="hidden" name="nip_perawat_ok" id="sia_nip_perawat_ok" value="<?php echo htmlspecialchars($data['nip_perawat_ok']); ?>">
                            <input type="text" id="sia_nm_perawat_ok" placeholder="Ketik nama perawat kamar operasi..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_perawat_ok']) ? htmlspecialchars($rsCheck['nm_perawat_ok']) : ''; ?>">
                            <div id="sia_ac_perawat_ok" class="sia-ac-dd"></div>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliSignInAnestesi()"><i class="material-icons">arrow_back</i> KEMBALI</button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-sia" class="btn btn-danger" onclick="confirmDeleteSignInAnestesi()"><i class="material-icons">delete</i> HAPUS</button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter bedah / dokter anestesi yang dapat menghapus"><i class="material-icons">lock</i> HAPUS</button>
            <?php endif; ?>
            <button type="submit" id="btn-save-sia" form="formSignInAnestesi" class="btn btn-primary"><i class="material-icons">save</i> SIMPAN</button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:13px;"><strong>Info:</strong> Hanya <strong>Dokter Bedah</strong> atau <strong>Dokter Anestesi</strong> yang dapat menghapus data ini.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL_SIA; ?>/js/signinsebelumanestesi.js?v=<?php echo time(); ?>"></script>
