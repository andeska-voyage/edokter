<?php
/**
 * checklistkesiapananestesi.php
 * Form Checklist Kesiapan Anestesi - E-Dokter SIMRS Khanza
 */
define('BASE_URL_CKA', APP_BASE_URL);

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
$queryCheck = bukaquery("SELECT cka.*, d.nm_dokter as nm_dokter_anestesi, pt.nama as nm_asisten
                         FROM checklist_kesiapan_anestesi cka
                         LEFT JOIN dokter d ON cka.kd_dokter = d.kd_dokter
                         LEFT JOIN petugas pt ON cka.nip = pt.nip
                         WHERE cka.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;

// Default values
$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'nip' => '', 'kd_dokter' => '',
    'tindakan' => $nama_tindakan_booking,
    'teknik_anestesi' => '',
    'listrik1'=>'Tidak','listrik2'=>'Tidak','listrik3'=>'Tidak','listrik4'=>'Tidak',
    'gasmedis1'=>'Tidak','gasmedis2'=>'Tidak','gasmedis3'=>'Tidak','gasmedis4'=>'Tidak','gasmedis5'=>'Tidak','gasmedis6'=>'Tidak',
    'mesinanes1'=>'Tidak','mesinanes2'=>'Tidak','mesinanes3'=>'Tidak','mesinanes4'=>'Tidak','mesinanes5'=>'Tidak',
    'jalannapas1'=>'Tidak','jalannapas2'=>'Tidak','jalannapas3'=>'Tidak','jalannapas4'=>'Tidak','jalannapas5'=>'Tidak','jalannapas6'=>'Tidak','jalannapas7'=>'Tidak','jalannapas8'=>'Tidak','jalannapas9'=>'Tidak',
    'lainlain1'=>'Tidak','lainlain2'=>'Tidak','lainlain3'=>'Tidak','lainlain4'=>'Tidak','lainlain5'=>'Tidak','lainlain6'=>'Tidak','lainlain7'=>'Tidak','lainlain8'=>'Tidak',
    'obatobat1'=>'Tidak','obatobat2'=>'Tidak','obatobat3'=>'Tidak','obatobat4'=>'Tidak','obatobat5'=>'Tidak','obatobat6'=>'Tidak',
    'keterangan_lainnya' => ''
);
if($isEdit) { $data = array_merge($data, $rsCheck); }

$bolehHapus = false;
if($isEdit && $kd_dokter_login === $data['kd_dokter']) {
    $bolehHapus = true;
}

function ckaOpts($name, $value) {
    $opts = ['Ya','Tidak'];
    $h = '<select name="'.$name.'" class="cka-sel">';
    foreach($opts as $o) { $s=($value==$o)?'selected':''; $h.='<option value="'.$o.'" '.$s.'>'.$o.'</option>'; }
    return $h.'</select>';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_CKA; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
.cka-section-title { font-size: 12px; font-weight: 700; color: #1e40af; margin: 12px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #e2e8f0; }
.cka-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 5px 8px; background: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0; margin-bottom: 4px; min-height: 32px; }
.cka-row label { font-size: 11px; font-weight: 500; color: #475569; flex: 1; min-width: 200px; }
.cka-sel { padding: 3px 6px; border: 1px solid #cbd5e1; border-radius: 3px; font-size: 11px; min-width: 70px; background: #fff; }
.cka-sel:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 2px rgba(102,126,234,0.15); }
.cka-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }
.cka-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px; }
.cka-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; }
/* Autocomplete */
.cka-ac-wrap { position: relative; }
.cka-ac-dd { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,.15); max-height: 200px; overflow-y: auto; z-index: 99; display: none; }
.cka-ac-dd.show { display: block; }
.cka-ac-dd div { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
.cka-ac-dd div:hover { background: #f0f9ff; }
.cka-ac-dd div strong { color: #1e40af; }
.cka-ac-dd .no-result { color: #94a3b8; text-align: center; font-style: italic; }
@media (max-width: 768px) { .cka-grid-2, .cka-grid-3, .cka-grid-4 { grid-template-columns: 1fr; } }
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">playlist_add_check</i>
                CHECKLIST KESIAPAN ANESTESI
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
            <form id="formChecklistKesiapanAnestesi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">

                <!-- Data Umum -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">event_note</i><h2>Data Checklist Kesiapan Anestesi</h2></div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="hidden" name="tanggal" value="<?php echo date('Y-m-d H:i:s', strtotime($data['tanggal'])); ?>">
                            <input type="text" value="<?php echo date('d-m-Y H:i:s', strtotime($data['tanggal'])); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Tindakan</label>
                            <input type="text" name="tindakan" value="<?php echo htmlspecialchars($data['tindakan']); ?>" placeholder="Tindakan operasi...">
                        </div>
                        <div class="form-group">
                            <label>Teknik Anestesi</label>
                            <input type="text" name="teknik_anestesi" value="<?php echo htmlspecialchars($data['teknik_anestesi']); ?>" placeholder="Teknik anestesi...">
                        </div>
                    </div>
                    <div class="form-grid cols-2" style="margin-top:8px;">
                        <div class="form-group cka-ac-wrap">
                            <label>Asisten</label>
                            <input type="hidden" name="nip" id="cka_nip" value="<?php echo htmlspecialchars($data['nip']); ?>">
                            <input type="text" id="cka_nm_asisten" placeholder="Ketik nama asisten..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_asisten']) ? htmlspecialchars($rsCheck['nm_asisten']) : ''; ?>">
                            <div id="cka_ac_asisten" class="cka-ac-dd"></div>
                        </div>
                        <div class="form-group cka-ac-wrap">
                            <label>Dokter Anestesi</label>
                            <input type="hidden" name="kd_dokter" id="cka_kd_dokter" value="<?php echo htmlspecialchars($data['kd_dokter']); ?>">
                            <input type="text" id="cka_nm_dokter" placeholder="Ketik nama dokter anestesi..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_anestesi']) ? htmlspecialchars($rsCheck['nm_dokter_anestesi']) : ''; ?>">
                            <div id="cka_ac_dokter" class="cka-ac-dd"></div>
                        </div>
                    </div>
                </div>

                <!-- I. LISTRIK -->
                <div class="section">
                    <div class="cka-section-title">I. LISTRIK</div>
                    <div class="cka-row"><label>Mesin anestesi terhubung dengan sumber listrik, indikator (+) menyala ?</label><?php echo ckaOpts('listrik1', $data['listrik1']); ?></div>
                    <div class="cka-row"><label>Layar pemantauan terhubung dengan sumber listrik, indikator (+) ?</label><?php echo ckaOpts('listrik2', $data['listrik2']); ?></div>
                    <div class="cka-row"><label>Syringe pump terhubung dengan sumber listrik, indikator (+) ?</label><?php echo ckaOpts('listrik3', $data['listrik3']); ?></div>
                    <div class="cka-row"><label>Defibrilator terhubung dengan sumber listrik, indikator (+) ?</label><?php echo ckaOpts('listrik4', $data['listrik4']); ?></div>
                </div>

                <!-- II. GAS MEDIS -->
                <div class="section">
                    <div class="cka-section-title">II. GAS MEDIS</div>
                    <div class="cka-row"><label>Selang oksigen terhubung antara sumber gas dengan mesin anestesi ?</label><?php echo ckaOpts('gasmedis1', $data['gasmedis1']); ?></div>
                    <div class="cka-row"><label>Flow meter O2 di mesin anestesi berfungsi, aliran gas keluar dari mesin dapat dirasakan ?</label><?php echo ckaOpts('gasmedis2', $data['gasmedis2']); ?></div>
                    <div class="cka-row"><label>Compressed air terhubung antara sumber gas dengan mesin anestesi ?</label><?php echo ckaOpts('gasmedis3', $data['gasmedis3']); ?></div>
                    <div class="cka-row"><label>Flow meter "Air" di mesin anestesi berfungsi, aliran gas keluar mesin dapat dirasakan ?</label><?php echo ckaOpts('gasmedis4', $data['gasmedis4']); ?></div>
                    <div class="cka-row"><label>N2O terhubung antara sumber gas dengan mesin anestesi ?</label><?php echo ckaOpts('gasmedis5', $data['gasmedis5']); ?></div>
                    <div class="cka-row"><label>Flow meter N2O di mesin anestesi berfungsi, aliran gas keluar mesin dapat dirasakan ?</label><?php echo ckaOpts('gasmedis6', $data['gasmedis6']); ?></div>
                </div>

                <!-- III. MESIN ANESTESI -->
                <div class="section">
                    <div class="cka-section-title">III. MESIN ANESTESI</div>
                    <div class="cka-grid-3">
                        <div class="cka-row"><label>Power ON ?</label><?php echo ckaOpts('mesinanes1', $data['mesinanes1']); ?></div>
                        <div class="cka-row"><label>Absorber CO2 dalam kondisi baik ?</label><?php echo ckaOpts('mesinanes2', $data['mesinanes2']); ?></div>
                        <div class="cka-row"><label>Self calibration : DONE ?</label><?php echo ckaOpts('mesinanes3', $data['mesinanes3']); ?></div>
                    </div>
                    <div class="cka-grid-2">
                        <div class="cka-row"><label>Zat volatil terisi ?</label><?php echo ckaOpts('mesinanes4', $data['mesinanes4']); ?></div>
                        <div class="cka-row"><label>Tidak ada kebocoran sirkuit nafas ?</label><?php echo ckaOpts('mesinanes5', $data['mesinanes5']); ?></div>
                    </div>
                </div>

                <!-- IV. MANAJEMEN JALAN NAPAS -->
                <div class="section">
                    <div class="cka-section-title">IV. MANAJEMEN JALAN NAPAS</div>
                    <div class="cka-grid-2">
                        <div class="cka-row"><label>Sungkup muka dalam ukuran yang benar ?</label><?php echo ckaOpts('jalannapas1', $data['jalannapas1']); ?></div>
                        <div class="cka-row"><label>Batang laringoskop berisi baterai ?</label><?php echo ckaOpts('jalannapas2', $data['jalannapas2']); ?></div>
                        <div class="cka-row"><label>Oropharygeal airway (Guedel) dalam ukuran yang benar ?</label><?php echo ckaOpts('jalannapas3', $data['jalannapas3']); ?></div>
                        <div class="cka-row"><label>Stilet (introduser) ?</label><?php echo ckaOpts('jalannapas4', $data['jalannapas4']); ?></div>
                        <div class="cka-row"><label>Bilah laringoskop dalam ukuran yang benar ?</label><?php echo ckaOpts('jalannapas5', $data['jalannapas5']); ?></div>
                        <div class="cka-row"><label>Semprit untuk mengembangkan cuff ?</label><?php echo ckaOpts('jalannapas6', $data['jalannapas6']); ?></div>
                        <div class="cka-row"><label>Gagang dan bilah laringoskop berfungsi baik ?</label><?php echo ckaOpts('jalannapas7', $data['jalannapas7']); ?></div>
                        <div class="cka-row"><label>Forceps Magill ?</label><?php echo ckaOpts('jalannapas8', $data['jalannapas8']); ?></div>
                    </div>
                    <div class="cka-row"><label>ETT atau LMA dalam ukuran yang benar, tidak bocor ?</label><?php echo ckaOpts('jalannapas9', $data['jalannapas9']); ?></div>
                </div>

                <!-- V. LAIN-LAIN -->
                <div class="section">
                    <div class="cka-section-title">V. LAIN-LAIN</div>
                    <div class="cka-grid-3">
                        <div class="cka-row"><label>Stetoskop tersedia ?</label><?php echo ckaOpts('lainlain1', $data['lainlain1']); ?></div>
                        <div class="cka-row"><label>Suction berfungsi baik ?</label><?php echo ckaOpts('lainlain2', $data['lainlain2']); ?></div>
                        <div class="cka-row"><label>Plester untuk fiksasi ?</label><?php echo ckaOpts('lainlain3', $data['lainlain3']); ?></div>
                    </div>
                    <div class="cka-grid-3">
                        <div class="cka-row"><label>Blanket roll dilapisi alas ?</label><?php echo ckaOpts('lainlain4', $data['lainlain4']); ?></div>
                        <div class="cka-row"><label>Lidocaine spray / jelly ?</label><?php echo ckaOpts('lainlain5', $data['lainlain5']); ?></div>
                        <div class="cka-row"><label>Defibrillator jelly ?</label><?php echo ckaOpts('lainlain6', $data['lainlain6']); ?></div>
                    </div>
                    <div class="cka-row"><label>Selang suction terhubung, kateter suction dalam ukuran yang benar ?</label><?php echo ckaOpts('lainlain7', $data['lainlain7']); ?></div>
                    <div class="cka-row"><label>Blanket roll / hemotherm / radiant heater terhubung sumber listrik, berfungsi baik ?</label><?php echo ckaOpts('lainlain8', $data['lainlain8']); ?></div>
                </div>

                <!-- VI. OBAT-OBAT -->
                <div class="section">
                    <div class="cka-section-title">VI. OBAT-OBAT</div>
                    <div class="cka-grid-4">
                        <div class="cka-row"><label>Epinefrin ?</label><?php echo ckaOpts('obatobat1', $data['obatobat1']); ?></div>
                        <div class="cka-row"><label>Atropin ?</label><?php echo ckaOpts('obatobat2', $data['obatobat2']); ?></div>
                        <div class="cka-row"><label>Antibiotika ?</label><?php echo ckaOpts('obatobat3', $data['obatobat3']); ?></div>
                        <div class="cka-row"><label>Pelumpuh otot ?</label><?php echo ckaOpts('obatobat4', $data['obatobat4']); ?></div>
                    </div>
                    <div class="cka-grid-2">
                        <div class="cka-row"><label>Sedatif (midazolam/propofol/etomidat/ketamin/tiopental) ?</label><?php echo ckaOpts('obatobat5', $data['obatobat5']); ?></div>
                        <div class="cka-row"><label>Opiat/opioid ?</label><?php echo ckaOpts('obatobat6', $data['obatobat6']); ?></div>
                    </div>
                </div>

                <!-- VII. KETERANGAN LAINNYA -->
                <div class="section">
                    <div class="cka-section-title">VII. KETERANGAN LAINNYA</div>
                    <div class="form-group">
                        <textarea name="keterangan_lainnya" rows="4" placeholder="Keterangan lainnya..."><?php echo htmlspecialchars($data['keterangan_lainnya']); ?></textarea>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliChecklistKesiapanAnestesi()"><i class="material-icons">arrow_back</i> KEMBALI</button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-cka" class="btn btn-danger" onclick="confirmDeleteChecklistKesiapanAnestesi()"><i class="material-icons">delete</i> HAPUS</button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter anestesi pengisi yang dapat menghapus"><i class="material-icons">lock</i> HAPUS</button>
            <?php endif; ?>
            <button type="submit" id="btn-save-cka" form="formChecklistKesiapanAnestesi" class="btn btn-primary"><i class="material-icons">save</i> SIMPAN</button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:13px;"><strong>Info:</strong> Hanya <strong>Dokter Anestesi</strong> pengisi yang dapat menghapus data ini.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL_CKA; ?>/js/checklistkesiapananestesi.js?v=<?php echo time(); ?>"></script>
