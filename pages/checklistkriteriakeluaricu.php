<?php
define('BASE_URL_KICU', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';
$no_rawat = '';
$no_rkm_medis = '';
if(!empty($encrypted_norawat)) { $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd'); }
if(!empty($encrypted_norm)) { $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd'); }

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
                         FROM checklist_kriteria_keluar_icu ck
                         LEFT JOIN pegawai pg ON ck.nik = pg.nik
                         WHERE ck.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_pengisi = ($rsCheck && !empty($rsCheck['nama_pengisi'])) ? $rsCheck['nama_pengisi'] : '';

$nikUser = '';
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
if(!empty($kd_dokter_encrypted)) {
    $nikUser = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

$allFields = ['kriteria1','kriteria2','kriteria3','kriteria4','kriteria5','kriteria6','kriteria7','kriteria8','kriteria9','kriteria10','kriteria11'];

$data = ['tanggal' => date('Y-m-d H:i:s'), 'nik' => $nikUser];
foreach($allFields as $f) { $data[$f] = 'Tidak'; }
if($isEdit) { $data = array_merge($data, $rsCheck); }

function kicuSelect($name, $value) {
    $sT = ($value == 'Ya') ? '' : 'selected';
    $sY = ($value == 'Ya') ? 'selected' : '';
    return '<select name="'.$name.'" class="icu-sel">
                <option value="Tidak" '.$sT.'>Tidak</option>
                <option value="Ya" '.$sY.'>Ya</option>
            </select>';
}
function kicuItem($label, $name, $value) {
    return '<div class="icu-field">
                <span class="icu-label">'.$label.' :</span>
                '.kicuSelect($name, $value).'
            </div>';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_KICU; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">logout</i>
                CHECKLIST KRITERIA KELUAR ICU
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
            <form id="formChecklistKeluarICU" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="nik" value="<?php echo $nikUser; ?>">

                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">checklist</i>
                        <h2>KRITERIA KELUAR ICU</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <?php
                        echo kicuItem('Tidak Lagi Memerlukan Perawatan ICU', 'kriteria1', $data['kriteria1']);
                        echo kicuItem('Kondisi Pasien Yang Menyebabkan Pasien Dirawat Di ICU Telah Teratasi', 'kriteria2', $data['kriteria2']);
                        echo kicuItem('Pasien Dalam Keadaan Terminal Yang Dengan Terapi Tidak Memberikan Hasil Yang Bermakna', 'kriteria3', $data['kriteria3']);
                        echo kicuItem('Pasien Meninggal', 'kriteria4', $data['kriteria4']);
                        echo kicuItem('Pasien Membutuhkan Alat / Prosedur Yang Tidak Tersedia', 'kriteria5', $data['kriteria5']);
                        echo kicuItem('Keluarga Menghendaki Pasien Dipindahkan', 'kriteria6', $data['kriteria6']);
                        ?>
                    </div>
                </div>

                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>KRITERIA FISIOLOGIS</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <?php
                        echo kicuItem('Nadi &gt; 60 Atau &lt; 100 x/menit', 'kriteria7', $data['kriteria7']);
                        echo kicuItem('SBP &gt; 08 mmHg Atau 20 mmHg Di Atas SBP Pasien', 'kriteria8', $data['kriteria8']);
                        echo kicuItem('MAP &gt; 60 mmHg', 'kriteria9', $data['kriteria9']);
                        echo kicuItem('DBP &lt; 120 mmHg', 'kriteria10', $data['kriteria10']);
                        echo kicuItem('RR &gt; 12 Sampai &lt; 25 x/menit', 'kriteria11', $data['kriteria11']);
                        ?>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliChecklistKeluarICU()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($isEdit): ?>
            <button type="button" id="btn-delete-kicu" class="btn btn-danger" onclick="confirmDeleteKeluarICU()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-kicu" form="formChecklistKeluarICU" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL_KICU; ?>/js/checklistkriteriakeluaricu.js?v=<?php echo time(); ?>"></script>
