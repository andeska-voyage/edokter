<?php
/**
 * skor_steward_pasca_anestesi.php
 * Form Skor Steward Pasca Anestesi - E-Dokter SIMRS Khanza
 */
define('BASE_URL_SSP', APP_BASE_URL);

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

// Cek data existing
$queryCheck = bukaquery("SELECT ssp.*, d.nm_dokter as nm_dokter_anestesi, pt.nama as nm_petugas
                         FROM skor_steward_pasca_anestesi ssp
                         LEFT JOIN dokter d ON ssp.kd_dokter = d.kd_dokter
                         LEFT JOIN petugas pt ON ssp.nip = pt.nip
                         WHERE ssp.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;

$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'nip' => '', 'kd_dokter' => '',
    'penilaian_skala1' => 'Belum Respon', 'penilaian_nilai1' => 0,
    'penilaian_skala2' => 'Perlu Bantuan Bernafas', 'penilaian_nilai2' => 0,
    'penilaian_skala3' => 'Tidak Bergerak', 'penilaian_nilai3' => 0,
    'penilaian_totalnilai' => 0,
    'keluar' => '', 'instruksi' => ''
);
if($isEdit) { $data = array_merge($data, $rsCheck); }

$bolehHapus = false;
if($isEdit && $kd_dokter_login === $data['kd_dokter']) {
    $bolehHapus = true;
}

// Skala options dengan nilai
$skala1_opts = [
    'Belum Respon' => 0,
    'Bangun Jika Dipanggil' => 1,
    'Sadar' => 2,
];
$skala2_opts = [
    'Perlu Bantuan Bernafas' => 0,
    'Berusana Bernafas' => 1,
    'Batuk' => 2,
];
$skala3_opts = [
    'Tidak Bergerak' => 0,
    'Gerakan Tanpa Tujuan' => 1,
    'Gerakan Teratur' => 2,
];

function sspSelect($name, $value, $opts, $nilaiName) {
    $h = '<select name="'.$name.'" class="ssp-skala-select" data-nilai="'.$nilaiName.'">';
    foreach($opts as $label => $score) {
        $s = ($value == $label) ? 'selected' : '';
        $h .= '<option value="'.htmlspecialchars($label).'" data-score="'.$score.'" '.$s.'>'.$label.'</option>';
    }
    return $h.'</select>';
}
function sspV($key) { global $data; return htmlspecialchars($data[$key] ?? ''); }
?>

<link rel="stylesheet" href="<?php echo BASE_URL_SSP; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
.ssp-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:6px 10px; background:#f8fafc; border-radius:5px; border:1px solid #e2e8f0; margin-bottom:5px; }
.ssp-row label { font-size:11px; font-weight:600; color:#475569; white-space:nowrap; min-width:130px; }
.ssp-row .ssp-label-skala { font-size:11px; font-weight:600; color:#475569; white-space:nowrap; }
.ssp-row select { padding:4px 6px; border:1px solid #cbd5e1; border-radius:4px; font-size:11px; flex:1; min-width:200px; }
.ssp-row select:focus { border-color:#667eea; outline:none; box-shadow:0 0 0 2px rgba(102,126,234,0.15); }
.ssp-row .ssp-nilai-label { font-size:11px; font-weight:600; color:#475569; white-space:nowrap; }
.ssp-row .ssp-nilai-input { width:50px; padding:4px 6px; border:1px solid #cbd5e1; border-radius:4px; font-size:12px; font-weight:700; text-align:center; background:#f1f5f9; color:#1e293b; }
.ssp-total-row { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding:8px 10px; background:#e0e7ff; border-radius:5px; border:1px solid #c7d2fe; margin-top:5px; }
.ssp-total-row span { font-size:12px; font-weight:700; color:#3730a3; }
.ssp-total-row input { width:50px; padding:4px 6px; border:1px solid #a5b4fc; border-radius:4px; font-size:14px; font-weight:700; text-align:center; background:#fff; color:#3730a3; }
.ssp-keterangan { font-size:11px; color:#6366f1; font-style:italic; padding:4px 10px; }
/* Autocomplete */
.ssp-ac-wrap { position:relative; }
.ssp-ac-dd { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:0 0 8px 8px; box-shadow:0 4px 12px rgba(0,0,0,.15); max-height:200px; overflow-y:auto; z-index:99; display:none; }
.ssp-ac-dd.show { display:block; }
.ssp-ac-dd div { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; font-size:12px; }
.ssp-ac-dd div:hover { background:#f0f9ff; }
.ssp-ac-dd div strong { color:#1e40af; }
.ssp-ac-dd .no-result { color:#94a3b8; text-align:center; font-style:italic; }
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">assessment</i>
                SKOR STEWARD PASCA ANESTESI
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
            <form id="formSkorStewardPascaAnestesi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">

                <!-- Data Umum -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">event_note</i><h2>Data Umum</h2></div>
                    <div class="form-grid cols-2">
                        <div class="form-group ssp-ac-wrap">
                            <label>Petugas</label>
                            <input type="hidden" name="nip" id="ssp_nip" value="<?php echo sspV('nip'); ?>">
                            <input type="text" id="ssp_nm_petugas" placeholder="Ketik nama petugas..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_petugas']) ? htmlspecialchars($rsCheck['nm_petugas']) : ''; ?>">
                            <div id="ssp_ac_petugas" class="ssp-ac-dd"></div>
                        </div>
                        <div class="form-group ssp-ac-wrap">
                            <label>Dokter Anestesi</label>
                            <input type="hidden" name="kd_dokter" id="ssp_kd_dokter" value="<?php echo sspV('kd_dokter'); ?>">
                            <input type="text" id="ssp_nm_dokter" placeholder="Ketik nama dokter anestesi..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_anestesi']) ? htmlspecialchars($rsCheck['nm_dokter_anestesi']) : ''; ?>">
                            <div id="ssp_ac_dokter" class="ssp-ac-dd"></div>
                        </div>
                    </div>
                    <div class="form-grid cols-1" style="margin-top:6px;">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="hidden" name="tanggal" value="<?php echo date('Y-m-d H:i:s', strtotime($data['tanggal'])); ?>">
                            <input type="text" value="<?php echo date('d-m-Y H:i:s', strtotime($data['tanggal'])); ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Kriteria Penilaian -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">grading</i><h2>Kriteria Penilaian</h2></div>

                    <div class="ssp-row">
                        <label>1. Kesadaran</label>
                        <span class="ssp-label-skala">Skala :</span>
                        <?php echo sspSelect('penilaian_skala1', $data['penilaian_skala1'], $skala1_opts, 'penilaian_nilai1'); ?>
                        <span class="ssp-nilai-label">Nilai :</span>
                        <input type="text" name="penilaian_nilai1" id="ssp_nilai1" class="ssp-nilai-input" value="<?php echo intval($data['penilaian_nilai1']); ?>" readonly>
                    </div>

                    <div class="ssp-row">
                        <label>2. Respirasi</label>
                        <span class="ssp-label-skala">Skala :</span>
                        <?php echo sspSelect('penilaian_skala2', $data['penilaian_skala2'], $skala2_opts, 'penilaian_nilai2'); ?>
                        <span class="ssp-nilai-label">Nilai :</span>
                        <input type="text" name="penilaian_nilai2" id="ssp_nilai2" class="ssp-nilai-input" value="<?php echo intval($data['penilaian_nilai2']); ?>" readonly>
                    </div>

                    <div class="ssp-row">
                        <label>3. Aktivitas Motorik</label>
                        <span class="ssp-label-skala">Skala :</span>
                        <?php echo sspSelect('penilaian_skala3', $data['penilaian_skala3'], $skala3_opts, 'penilaian_nilai3'); ?>
                        <span class="ssp-nilai-label">Nilai :</span>
                        <input type="text" name="penilaian_nilai3" id="ssp_nilai3" class="ssp-nilai-input" value="<?php echo intval($data['penilaian_nilai3']); ?>" readonly>
                    </div>

                    <div class="ssp-keterangan" id="ssp_keterangan">
                        <?php
                        $total = intval($data['penilaian_totalnilai']);
                        echo ($total >= 5) ? 'Pasien Dapat Dipindahkan Ke Ruangan Perawatan' : 'Pasien Tidak Dapat Dipindahkan Ke Ruangan Perawatan, Karena Kondisi Yang Lemah';
                        ?>
                    </div>
                    <div class="ssp-total-row">
                        <span>Total :</span>
                        <input type="text" name="penilaian_totalnilai" id="ssp_total" value="<?php echo intval($data['penilaian_totalnilai']); ?>" readonly>
                    </div>
                </div>

                <!-- Keluar & Instruksi -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">exit_to_app</i><h2>Keluar &amp; Instruksi</h2></div>
                    <div class="form-group">
                        <label>Keluar</label>
                        <textarea name="keluar" rows="3" placeholder="Keterangan keluar..."><?php echo sspV('keluar'); ?></textarea>
                    </div>
                    <div class="form-group" style="margin-top:8px;">
                        <label>Instruksi / Tindakan di Ruang Pemulihan (RR)</label>
                        <textarea name="instruksi" rows="3" placeholder="Instruksi / tindakan di ruang pemulihan..."><?php echo sspV('instruksi'); ?></textarea>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliSkorSteward()"><i class="material-icons">arrow_back</i> KEMBALI</button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-ssp" class="btn btn-danger" onclick="confirmDeleteSkorSteward()"><i class="material-icons">delete</i> HAPUS</button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter anestesi pengisi yang dapat menghapus"><i class="material-icons">lock</i> HAPUS</button>
            <?php endif; ?>
            <button type="submit" id="btn-save-ssp" form="formSkorStewardPascaAnestesi" class="btn btn-primary"><i class="material-icons">save</i> SIMPAN</button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:13px;"><strong>Info:</strong> Hanya <strong>Dokter Anestesi</strong> pengisi yang dapat menghapus data ini.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL_SSP; ?>/js/skor_steward_pasca_anestesi.js?v=<?php echo time(); ?>"></script>
