<?php
define('BASE_URL_KPICU', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';
$no_rawat          = '';
$no_rkm_medis      = '';
if(!empty($encrypted_norawat)) { $no_rawat     = encrypt_decrypt(urldecode($encrypted_norawat), 'd'); }
if(!empty($encrypted_norm))    { $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm),    'd'); }

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
if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.location.href='?act=Pasien';</script>";
    exit;
}

$queryCheck = bukaquery("SELECT ck.*, pg.nama as nama_pengisi 
                         FROM checklist_kriteria_keluar_picu ck
                         LEFT JOIN pegawai pg ON ck.nik = pg.nik
                         WHERE ck.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;
$nama_pengisi = ($rsCheck && !empty($rsCheck['nama_pengisi'])) ? $rsCheck['nama_pengisi'] : '';

$nikUser = '';
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
if(!empty($kd_dokter_encrypted)) {
    $nikUser = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

$allFields = [
    'kondisiklinis1','kondisiklinis2','kondisiklinis3','kondisiklinis4','kondisiklinis5','kondisiklinis6',
    'kebutuhanperawatan1','kebutuhanperawatan2','kebutuhanperawatan3','kebutuhanperawatan4',
    'tindaklanjut1','tindaklanjut2','tindaklanjut3','tindaklanjut4'
];

$data = [
    'tanggal'    => date('Y-m-d H:i:s'),
    'nik'        => $nikUser,
    'keputusan'  => 'Layak Keluar Dari PICU/Pindah Ke Ruang Rawat Biasa',
    'keterangan' => ''
];
foreach($allFields as $f) { $data[$f] = 'Tidak'; }
if($isEdit) { $data = array_merge($data, $rsCheck); }

function picuSelect($name, $value) {
    $sT = ($value == 'Ya') ? '' : 'selected';
    $sY = ($value == 'Ya') ? 'selected' : '';
    return '<select name="'.$name.'" class="icu-sel">
                <option value="Tidak" '.$sT.'>Tidak</option>
                <option value="Ya"    '.$sY.'>Ya</option>
            </select>';
}
function picuItem($label, $name, $value) {
    return '<div class="icu-field">
                <span class="icu-label">'.$label.' :</span>
                '.picuSelect($name, $value).'
            </div>';
}

$keputusanOptions = [
    'Layak Keluar Dari PICU/Pindah Ke Ruang Rawat Biasa',
    'Belum Layak Keluar',
    'Dirujuk Ke RS Lain'
];
?>

<link rel="stylesheet" href="<?php echo BASE_URL_KPICU; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
    .keputusan-select { width:100%; padding:6px 10px; border:1px solid #ccc; border-radius:4px; font-size:13px; }
    .keterangan-input { width:100%; padding:6px 10px; border:1px solid #ccc; border-radius:4px; font-size:13px; box-sizing:border-box; }
    .keputusan-row    { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .keputusan-row > * { flex:1; min-width:200px; }
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">logout</i>
                CHECKLIST KRITERIA KELUAR PICU
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <?php if($isEdit && !empty($nama_pengisi)): ?>
            <div class="info-item"><i class="material-icons">person</i><strong>Diisi oleh:</strong> <?php echo $nama_pengisi; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-card">
        <div class="form-content">
            <form id="formChecklistKeluarPICU" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="nik"      value="<?php echo $nikUser; ?>">

                <!-- I. KONDISI KLINIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_information</i>
                        <h2>I. KONDISI KLINIS</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo picuItem('Tidak Ada Tanda Gagal Napas Akut', 'kondisiklinis1', $data['kondisiklinis1']);
                        echo picuItem('Saturasi O&#x2082; Stabil Tanpa Ventilator Mekanik Atau O&#x2082; Nasal &lt; 2 L/menit', 'kondisiklinis2', $data['kondisiklinis2']);
                        echo picuItem('Status Kesadaran Stabil (Sesuai Baseline, GCS &ge; 13/Tidak Ada Penurunan Akut)', 'kondisiklinis3', $data['kondisiklinis3']);
                        echo picuItem('Tidak Ada Perdarahan Aktif/Syok', 'kondisiklinis4', $data['kondisiklinis4']);
                        echo picuItem('Tidak Membutuhkan Vasopressor / Inotropik', 'kondisiklinis5', $data['kondisiklinis5']);
                        echo picuItem('Tanda Vital Stabil (HR, RR, Nadi, TD, Suhu)', 'kondisiklinis6', $data['kondisiklinis6']);
                        ?>
                    </div>
                </div>

                <!-- II. KEBUTUHAN PERAWATAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">local_hospital</i>
                        <h2>II. KEBUTUHAN PERAWATAN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo picuItem('Tidak Lagi Membutuhkan Monitoring Invasif', 'kebutuhanperawatan1', $data['kebutuhanperawatan1']);
                        echo picuItem('Tidak Membutuhkan Terapi Intensif Berkelanjutan', 'kebutuhanperawatan2', $data['kebutuhanperawatan2']);
                        echo picuItem('Nyeri Terkontrol', 'kebutuhanperawatan3', $data['kebutuhanperawatan3']);
                        echo picuItem('Kebutuhan Cairan &amp; Nutrisi Dapat Dipenuhi Secara Oral / Enteral / IV Standar', 'kebutuhanperawatan4', $data['kebutuhanperawatan4']);
                        ?>
                    </div>
                </div>

                <!-- III. RENCANA TINDAK LANJUT -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">assignment</i>
                        <h2>III. RENCANA TINDAK LANJUT</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo picuItem('Rencana Kontrol/Tindakan Lanjutan Tercatat', 'tindaklanjut1', $data['tindaklanjut1']);
                        echo picuItem('Orang Tua/Wali Mendapat Edukasi Kondisi &amp; Rencana Perawatan', 'tindaklanjut2', $data['tindaklanjut2']);
                        echo picuItem('Konsultasi Dengan Tim Terkait Sudah Dilakukan', 'tindaklanjut3', $data['tindaklanjut3']);
                        echo picuItem('Rencana Terapi Jelas Untuk Rawat Ruang Biasa', 'tindaklanjut4', $data['tindaklanjut4']);
                        ?>
                    </div>
                </div>

                <!-- IV. KEPUTUSAN & KETERANGAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">gavel</i>
                        <h2>IV. KEPUTUSAN &amp; KETERANGAN</h2>
                    </div>
                    <div class="keputusan-row">
                        <div>
                            <label style="font-size:13px;font-weight:600;">Keputusan :</label>
                            <select name="keputusan" class="keputusan-select" style="margin-top:4px;">
                                <?php foreach($keputusanOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['keputusan'] === $opt) ? 'selected' : ''; ?>>
                                    <?php echo $opt; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:13px;font-weight:600;">Keterangan/Catatan :</label>
                            <input type="text" name="keterangan" class="keterangan-input" style="margin-top:4px;"
                                   value="<?php echo htmlspecialchars($data['keterangan']); ?>"
                                   placeholder="Isi keterangan jika ada...">
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliChecklistKeluarPICU()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($isEdit): ?>
            <button type="button" id="btn-delete-kpicu" class="btn btn-danger" onclick="confirmDeleteKeluarPICU()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-kpicu" form="formChecklistKeluarPICU" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL_KPICU; ?>/js/kriteriakeluarpicu.js?v=<?php echo time(); ?>"></script>