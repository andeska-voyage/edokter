<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
define('BASE_URL', APP_BASE_URL);

// Decrypt parameter dari URL
$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';

$no_rawat = '';
$no_rkm_medis = '';

if(!empty($encrypted_norawat)) {
    $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
}

if(!empty($encrypted_norm)) {
    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');
}

// Handle DELETE request - via AJAX di resumemedis.js (hapusData -> proses2.php)

// Ambil data pasien
$queryPasien = bukaquery("SELECT 
                            rp.no_rawat,
                            rp.no_rkm_medis,
                            rp.tgl_registrasi,
                            rp.jam_reg,
                            p.nm_pasien,
                            p.jk,
                            p.tmp_lahir,
                            p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                            d.nm_dokter,
                            d.kd_dokter
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                        WHERE rp.no_rawat = '$no_rawat'");

$rsPasien = mysqli_fetch_array($queryPasien);

if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.location.href='?act=Pasien';</script>";
    exit;
}

// Cek apakah sudah ada data dengan info dokter
$queryCheck = bukaquery("SELECT rp.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM resume_pasien rp
                         LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                         WHERE rp.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_assoc($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Ambil kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Data default
$data = array(
    'kd_dokter' => $kd_dokter_login,
    'keluhan_utama' => '',
    'jalannya_penyakit' => '',
    'pemeriksaan_penunjang' => '',
    'hasil_laborat' => '',
    'diagnosa_utama' => '',
    'kd_diagnosa_utama' => '',
    'diagnosa_sekunder' => '',
    'kd_diagnosa_sekunder' => '',
    'diagnosa_sekunder2' => '',
    'kd_diagnosa_sekunder2' => '',
    'diagnosa_sekunder3' => '',
    'kd_diagnosa_sekunder3' => '',
    'diagnosa_sekunder4' => '',
    'kd_diagnosa_sekunder4' => '',
    'prosedur_utama' => '',
    'kd_prosedur_utama' => '',
    'prosedur_sekunder' => '',
    'kd_prosedur_sekunder' => '',
    'prosedur_sekunder2' => '',
    'kd_prosedur_sekunder2' => '',
    'prosedur_sekunder3' => '',
    'kd_prosedur_sekunder3' => '',
    'kondisi_pulang' => 'Hidup',
    'obat_pulang' => ''
);

// Jika edit, gunakan data yang ada
if($isEdit) {
    $data = array_merge($data, $rsCheck);
}

// Hak hapus
$bolehHapus = false;
if($isEdit) {
    $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
    if($kd_dokter_login === $kd_dokter_data) {
        $bolehHapus = true;
    }
}

// ----------------------------------------------------------------
// AUTO-FILL dari diagnosa_pasien + prosedur_pasien (status='Ralan')
// Hanya dipakai jika belum ada data di resume_pasien (bukan edit)
// Jika edit, data resume_pasien sudah dimerge ke $data di atas
// ----------------------------------------------------------------

// Query diagnosa dari diagnosa_pasien join penyakit
$qDiagnosa = bukaquery("
    SELECT dp.kd_penyakit, py.nm_penyakit, dp.prioritas
    FROM diagnosa_pasien dp
    LEFT JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit
    WHERE dp.no_rawat = '$no_rawat' AND dp.status = 'Ralan'
    ORDER BY dp.prioritas ASC
    LIMIT 5
");

$diagRows = [];
while($row = mysqli_fetch_assoc($qDiagnosa)) {
    $diagRows[] = $row;
}

// Query prosedur dari prosedur_pasien join icd9
$qProsedur = bukaquery("
    SELECT pp.kode, i9.deskripsi_panjang, pp.prioritas
    FROM prosedur_pasien pp
    LEFT JOIN icd9 i9 ON pp.kode = i9.kode
    WHERE pp.no_rawat = '$no_rawat' AND pp.status = 'Ralan'
    ORDER BY pp.prioritas ASC
    LIMIT 4
");

$prosedurRows = [];
while($row = mysqli_fetch_assoc($qProsedur)) {
    $prosedurRows[] = $row;
}

// Map diagnosa ke field $data
// Prioritas 1 = utama, 2-5 = sekunder 1-4
// Jika isEdit, $data sudah terisi dari resume_pasien — tidak ditimpa
// Jika baru (NEW), isi dari diagnosa_pasien
if(!$isEdit) {
    $diagFields = [
        1 => ['diagnosa_utama',    'kd_diagnosa_utama'],
        2 => ['diagnosa_sekunder',  'kd_diagnosa_sekunder'],
        3 => ['diagnosa_sekunder2', 'kd_diagnosa_sekunder2'],
        4 => ['diagnosa_sekunder3', 'kd_diagnosa_sekunder3'],
        5 => ['diagnosa_sekunder4', 'kd_diagnosa_sekunder4'],
    ];
    foreach($diagRows as $dr) {
        $prio = (int)$dr['prioritas'];
        if(isset($diagFields[$prio])) {
            $data[$diagFields[$prio][0]] = $dr['nm_penyakit'];
            $data[$diagFields[$prio][1]] = $dr['kd_penyakit'];
        }
    }

    $prosFields = [
        1 => ['prosedur_utama',    'kd_prosedur_utama'],
        2 => ['prosedur_sekunder',  'kd_prosedur_sekunder'],
        3 => ['prosedur_sekunder2', 'kd_prosedur_sekunder2'],
        4 => ['prosedur_sekunder3', 'kd_prosedur_sekunder3'],
    ];
    foreach($prosedurRows as $pr) {
        $prio = (int)$pr['prioritas'];
        if(isset($prosFields[$prio])) {
            $data[$prosFields[$prio][0]] = $pr['deskripsi_panjang'];
            $data[$prosFields[$prio][1]] = $pr['kode'];
        }
    }
} else {
    // Mode EDIT: tampilkan info sumber data jika field kosong di resume_pasien
    // Tetap gunakan data dari diagnosa_pasien sebagai fallback jika field kosong
    $diagFields = [
        1 => ['diagnosa_utama',    'kd_diagnosa_utama'],
        2 => ['diagnosa_sekunder',  'kd_diagnosa_sekunder'],
        3 => ['diagnosa_sekunder2', 'kd_diagnosa_sekunder2'],
        4 => ['diagnosa_sekunder3', 'kd_diagnosa_sekunder3'],
        5 => ['diagnosa_sekunder4', 'kd_diagnosa_sekunder4'],
    ];
    foreach($diagRows as $dr) {
        $prio = (int)$dr['prioritas'];
        if(isset($diagFields[$prio])) {
            $nmField = $diagFields[$prio][0];
            $kdField = $diagFields[$prio][1];
            if(empty($data[$nmField])) $data[$nmField] = $dr['nm_penyakit'];
            if(empty($data[$kdField])) $data[$kdField] = $dr['kd_penyakit'];
        }
    }

    $prosFields = [
        1 => ['prosedur_utama',    'kd_prosedur_utama'],
        2 => ['prosedur_sekunder',  'kd_prosedur_sekunder'],
        3 => ['prosedur_sekunder2', 'kd_prosedur_sekunder2'],
        4 => ['prosedur_sekunder3', 'kd_prosedur_sekunder3'],
    ];
    foreach($prosedurRows as $pr) {
        $prio = (int)$pr['prioritas'];
        if(isset($prosFields[$prio])) {
            $nmField = $prosFields[$prio][0];
            $kdField = $prosFields[$prio][1];
            if(empty($data[$nmField])) $data[$nmField] = $pr['deskripsi_panjang'];
            if(empty($data[$kdField])) $data[$kdField] = $pr['kode'];
        }
    }
}

// ----------------------------------------------------------------
// Cek apakah ada data sumber (IGD / Awal Medis Ralan / SOAP)
// untuk menampilkan tombol GET DATA
// ----------------------------------------------------------------
$hasDataSumber = false;

// Cek IGD
$qSumber = bukaquery("SELECT no_rawat FROM penilaian_medis_igd WHERE no_rawat = '$no_rawat' LIMIT 1");
if ($qSumber && mysqli_num_rows($qSumber) > 0) $hasDataSumber = true;

// Cek SOAP jika belum ada (filter by nip = kd_dokter login,
// karena SOAP bisa diisi banyak dokter pada 1 no_rawat)
if (!$hasDataSumber && !empty($kd_dokter_login)) {
    $qSumber = bukaquery("SELECT no_rawat FROM pemeriksaan_ralan
                          WHERE no_rawat = '$no_rawat' AND nip = '$kd_dokter_login' LIMIT 1");
    if ($qSumber && mysqli_num_rows($qSumber) > 0) $hasDataSumber = true;
}

// Cek hasil radiologi
if (!$hasDataSumber) {
    $qSumber = bukaquery("SELECT no_rawat FROM hasil_radiologi WHERE no_rawat = '$no_rawat' LIMIT 1");
    if ($qSumber && mysqli_num_rows($qSumber) > 0) $hasDataSumber = true;
}

// Cek hasil lab
if (!$hasDataSumber) {
    $qSumber = bukaquery("SELECT no_rawat FROM detail_periksa_lab WHERE no_rawat = '$no_rawat' LIMIT 1");
    if ($qSumber && mysqli_num_rows($qSumber) > 0) $hasDataSumber = true;
}

// Cek 18 tabel awal medis ralan jika belum ada
if (!$hasDataSumber) {
    $tabelRalanCek = [
        'penilaian_medis_ralan','penilaian_medis_ralan_anak','penilaian_medis_ralan_bedah',
        'penilaian_medis_ralan_bedah_mulut','penilaian_medis_ralan_gawat_darurat_psikiatri',
        'penilaian_medis_ralan_geriatri','penilaian_medis_ralan_jantung','penilaian_medis_ralan_kandungan',
        'penilaian_medis_ralan_kulitdankelamin','penilaian_medis_ralan_mata','penilaian_medis_ralan_neurologi',
        'penilaian_medis_ralan_orthopedi','penilaian_medis_ralan_paru','penilaian_medis_ralan_penyakit_dalam',
        'penilaian_medis_ralan_psikiatrik','penilaian_medis_ralan_rehab_medik','penilaian_medis_ralan_tht',
        'penilaian_medis_ralan_urologi'
    ];
    foreach ($tabelRalanCek as $tbl) {
        $qSumber = bukaquery("SELECT no_rawat FROM $tbl WHERE no_rawat = '$no_rawat' LIMIT 1");
        if ($qSumber && mysqli_num_rows($qSumber) > 0) { $hasDataSumber = true; break; }
    }
}
?>

<!-- CSS Local - template4.css -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<style>
/* Tombol GET DATA di header resume medis */
.btn-get-data-resume {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
    white-space: nowrap;
}
.btn-get-data-resume:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
}
.btn-get-data-resume:disabled {
    background: #9ca3af; cursor: not-allowed; transform: none; box-shadow: none;
}
.btn-get-data-resume i { font-size: 14px; }
</style>

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="material-icons">assignment</i>
                RESUME MEDIS RAWAT JALAN
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>

            <div style="display: flex; align-items: center; gap: 10px;">
                <?php if($hasDataSumber): ?>
                <button type="button" class="btn-get-data-resume" id="btn-get-data-resume" onclick="getDataResume()"
                        title="Ambil data otomatis dari IGD / Awal Medis Ralan / SOAP">
                    <i class="material-icons">download</i>
                    GET DATA
                </button>
                <?php endif; ?>

            <!-- Compact Progress Bar -->
            <div style="display: flex; align-items: center; gap: 10px; background: #f8f9fa; border-radius: 8px; padding: 8px 12px;">
                <i class="material-icons" style="font-size: 18px; color: #6c757d;">assessment</i>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 11px; color: #6c757d; font-weight: 500;">Kelengkapan</span>
                        <span id="progress-text-resumemedis" style="font-weight: bold; font-size: 14px; color: #6c757d;">0%</span>
                    </div>
                    <div style="width: 150px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar-resumemedis" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-resumemedis" style="font-size: 10px; color: #6c757d; white-space: nowrap;">(0/0)</span>
            </div>
            </div><!-- /wrapper button + progress -->
        </div>
        <div class="patient-info">
            <div class="info-item">
                <i class="material-icons">folder</i>
                <strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?>
            </div>
            <div class="info-item">
                <i class="material-icons">badge</i>
                <strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?>
            </div>
            <div class="info-item">
                <i class="material-icons">person</i>
                <strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?>
            </div>
            <div class="info-item">
                <i class="material-icons">cake</i>
                <strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)
            </div>
            <?php if($isEdit && !empty($nama_dokter_pengisi)): ?>
            <div class="info-item">
                <i class="material-icons">person</i>
                <strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formResumeMedis" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $data['kd_dokter']; ?>">
                
                <!-- I. ANAMNESIS & RIWAYAT PERAWATAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">description</i>
                        <h2>I. ANAMNESIS & RIWAYAT PERAWATAN</h2>
                    </div>
                    
                    <div class="form-group">
                        <label>Keluhan Utama Riwayat Penyakit Yang Positif</label>
                        <textarea name="keluhan_utama" rows="3" 
                                  placeholder="Jelaskan keluhan utama dan riwayat penyakit yang relevan..."><?php echo $data['keluhan_utama']; ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Jalannya Penyakit Selama Perawatan</label>
                        <textarea name="jalannya_penyakit" rows="3" 
                                  placeholder="Jelaskan perjalanan penyakit selama dirawat..."><?php echo $data['jalannya_penyakit']; ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Pemeriksaan Penunjang Yang Positif</label>
                        <textarea name="pemeriksaan_penunjang" rows="3" 
                                  placeholder="Hasil pemeriksaan penunjang yang positif (EKG, Radiologi, dll)..."><?php echo $data['pemeriksaan_penunjang']; ?></textarea>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Hasil Laboratorium Yang Positif</label>
                        <textarea name="hasil_laborat" rows="3" 
                                  placeholder="Hasil laboratorium yang positif..."><?php echo $data['hasil_laborat']; ?></textarea>
                    </div>
                </div>

                <!-- II. DIAGNOSIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>II. DIAGNOSIS</h2>
                    </div>

                    <?php if(count($diagRows) > 0): ?>
                    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:8px 12px; margin-bottom:10px; display:flex; align-items:center; gap:8px; font-size:11px; color:#1e40af;">
                        <i class="material-icons" style="font-size:16px;">sync</i>
                        Data diagnosa diambil otomatis dari SIMRS / edokter.
                    </div>
                    <?php endif; ?>

                    <div class="section-subtitle">Diagnosa Utama</div>
                    <div class="form-grid cols-3">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Nama Diagnosa Utama</label>
                            <input type="text" name="diagnosa_utama" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_utama']); ?>" 
                                   placeholder="Nama diagnosa utama">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD-10</label>
                            <input type="text" name="kd_diagnosa_utama" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_utama']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>

                    <div class="section-subtitle">Diagnosa Sekunder</div>
                    <div class="form-grid cols-3">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Diagnosa Sekunder 1</label>
                            <input type="text" name="diagnosa_sekunder" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_sekunder']); ?>" 
                                   placeholder="Diagnosa sekunder 1">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD-10</label>
                            <input type="text" name="kd_diagnosa_sekunder" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>

                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Diagnosa Sekunder 2</label>
                            <input type="text" name="diagnosa_sekunder2" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_sekunder2']); ?>" 
                                   placeholder="Diagnosa sekunder 2">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD-10</label>
                            <input type="text" name="kd_diagnosa_sekunder2" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder2']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>

                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Diagnosa Sekunder 3</label>
                            <input type="text" name="diagnosa_sekunder3" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_sekunder3']); ?>" 
                                   placeholder="Diagnosa sekunder 3">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD-10</label>
                            <input type="text" name="kd_diagnosa_sekunder3" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder3']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>

                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Diagnosa Sekunder 4</label>
                            <input type="text" name="diagnosa_sekunder4" 
                                   value="<?php echo htmlspecialchars($data['diagnosa_sekunder4']); ?>" 
                                   placeholder="Diagnosa sekunder 4">
                        </div>
                        <div class="form-group">
                            <label>Kode ICD-10</label>
                            <input type="text" name="kd_diagnosa_sekunder4" 
                                   value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder4']); ?>" 
                                   placeholder="Kode ICD">
                        </div>
                    </div>
                </div>

                <!-- III. PROSEDUR / TINDAKAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>III. PROSEDUR / TINDAKAN</h2>
                    </div>

                    <?php if(count($prosedurRows) > 0): ?>
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:8px 12px; margin-bottom:10px; display:flex; align-items:center; gap:8px; font-size:11px; color:#166534;">
                        <i class="material-icons" style="font-size:16px;">sync</i>
                        Data prosedur diambil otomatis dari SIMRS / edokter.
                    </div>
                    <?php endif; ?>

                    <div class="section-subtitle">Prosedur Utama</div>
                    <div class="form-grid cols-3">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Nama Prosedur Utama</label>
                            <input type="text" name="prosedur_utama" 
                                   value="<?php echo htmlspecialchars($data['prosedur_utama']); ?>" 
                                   placeholder="Nama prosedur/tindakan utama">
                        </div>
                        <div class="form-group">
                            <label>Kode Prosedur</label>
                            <input type="text" name="kd_prosedur_utama" 
                                   value="<?php echo htmlspecialchars($data['kd_prosedur_utama']); ?>" 
                                   placeholder="Kode ICD-9 CM">
                        </div>
                    </div>

                    <div class="section-subtitle">Prosedur Sekunder</div>
                    <div class="form-grid cols-3">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Prosedur Sekunder 1</label>
                            <input type="text" name="prosedur_sekunder" 
                                   value="<?php echo htmlspecialchars($data['prosedur_sekunder']); ?>" 
                                   placeholder="Prosedur sekunder 1">
                        </div>
                        <div class="form-group">
                            <label>Kode Prosedur</label>
                            <input type="text" name="kd_prosedur_sekunder" 
                                   value="<?php echo htmlspecialchars($data['kd_prosedur_sekunder']); ?>" 
                                   placeholder="Kode">
                        </div>
                    </div>

                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Prosedur Sekunder 2</label>
                            <input type="text" name="prosedur_sekunder2" 
                                   value="<?php echo htmlspecialchars($data['prosedur_sekunder2']); ?>" 
                                   placeholder="Prosedur sekunder 2">
                        </div>
                        <div class="form-group">
                            <label>Kode Prosedur</label>
                            <input type="text" name="kd_prosedur_sekunder2" 
                                   value="<?php echo htmlspecialchars($data['kd_prosedur_sekunder2']); ?>" 
                                   placeholder="Kode">
                        </div>
                    </div>

                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Prosedur Sekunder 3</label>
                            <input type="text" name="prosedur_sekunder3" 
                                   value="<?php echo htmlspecialchars($data['prosedur_sekunder3']); ?>" 
                                   placeholder="Prosedur sekunder 3">
                        </div>
                        <div class="form-group">
                            <label>Kode Prosedur</label>
                            <input type="text" name="kd_prosedur_sekunder3" 
                                   value="<?php echo htmlspecialchars($data['kd_prosedur_sekunder3']); ?>" 
                                   placeholder="Kode">
                        </div>
                    </div>
                </div>

                <!-- IV. TINDAK LANJUT & KONDISI PULANG -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">exit_to_app</i>
                        <h2>IV. TINDAK LANJUT & KONDISI PULANG</h2>
                    </div>

                    <div class="form-group">
                        <label>Kondisi Pasien Saat Pulang</label>
                        <select name="kondisi_pulang">
                            <option value="Hidup" <?php echo ($data['kondisi_pulang'] == 'Hidup') ? 'selected' : ''; ?>>Hidup</option>
                            <option value="Meninggal" <?php echo ($data['kondisi_pulang'] == 'Meninggal') ? 'selected' : ''; ?>>Meninggal</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Obat-obatan Waktu Pulang / Nasihat</label>
                        <textarea name="obat_pulang" rows="8" 
                                  placeholder="Tuliskan obat-obatan yang dibawa pulang dan nasihat untuk pasien..."><?php echo $data['obat_pulang']; ?></textarea>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliResumeMedis()">
                <i class="material-icons">arrow_back</i>
                KEMBALI
            </button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-resumemedis" class="btn btn-danger" onclick="confirmDeleteResumeMedis()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-resumemedis" form="formResumeMedis" class="btn btn-primary">
                <i class="material-icons">save</i>
                SIMPAN DATA
            </button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 15px; display: flex; align-items: center; gap: 10px;">
            <i class="material-icons" style="color: #856404;">info</i>
            <span style="color: #856404; font-size: 14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>. 
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete via AJAX - handled by resumemedis.js -->

<script src="<?php echo BASE_URL; ?>/js/resumemedis.js?v=<?php echo time(); ?>"></script>
