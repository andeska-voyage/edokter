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

// Handle DELETE request - via AJAX di awalmediskebidanan.js (hapusData -> proses3.php)

// Ambil data pasien dari kamar_inap
$queryPasien = bukaquery("SELECT 
                            ki.no_rawat,
                            rp.no_rkm_medis,
                            ki.tgl_masuk,
                            ki.jam_masuk,
                            p.nm_pasien,
                            p.jk,
                            p.tmp_lahir,
                            p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                            d.nm_dokter,
                            d.kd_dokter,
                            kms.kd_kamar,
                            bng.nm_bangsal
                        FROM kamar_inap ki
                        INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN dpjp_ranap dpjp ON ki.no_rawat = dpjp.no_rawat
                        LEFT JOIN dokter d ON dpjp.kd_dokter = d.kd_dokter
                        LEFT JOIN kamar kms ON ki.kd_kamar = kms.kd_kamar
                        LEFT JOIN bangsal bng ON kms.kd_bangsal = bng.kd_bangsal
                        WHERE ki.no_rawat = '$no_rawat'
                        ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
                        LIMIT 1");

$rsPasien = mysqli_fetch_array($queryPasien);

if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>";
    exit;
}

// Cek apakah sudah ada data penilaian medis kebidanan dengan info dokter
$queryCheck = bukaquery("SELECT pmk.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_medis_ranap_kandungan pmk
                         LEFT JOIN dokter d ON pmk.kd_dokter = d.kd_dokter
                         WHERE pmk.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
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
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'anamnesis' => 'Autoanamnesis',
    'hubungan' => '',
    'keluhan_utama' => '',
    'rps' => '',
    'rpd' => '',
    'rpk' => '',
    'rpo' => '',
    'alergi' => '',
    'keadaan' => 'Sehat',
    'gcs' => '',
    'kesadaran' => 'Compos Mentis',
    'td' => '',
    'nadi' => '',
    'rr' => '',
    'suhu' => '',
    'spo' => '',
    'bb' => '',
    'tb' => '',
    'kepala' => 'Normal',
    'mata' => 'Normal',
    'gigi' => 'Normal',
    'tht' => 'Normal',
    'thoraks' => 'Normal',
    'jantung' => 'Normal',
    'paru' => 'Normal',
    'abdomen' => 'Normal',
    'genital' => 'Normal',
    'ekstremitas' => 'Normal',
    'kulit' => 'Normal',
    'ket_fisik' => '',
    // Status Obstetri/Ginekologi
    'tfu' => '',
    'tbj' => '',
    'his' => '',
    'kontraksi' => 'Ada',
    'djj' => '',
    'inspeksi' => '',
    'inspekulo' => '',
    'vt' => '',
    'rt' => '',
    // Pemeriksaan Penunjang
    'ultra' => '',
    'kardio' => '',
    'lab' => '',
    // Diagnosis & Tatalaksana
    'diagnosis' => '',
    'tata' => '',
    'edukasi' => ''
);

// Jika edit, gunakan data yang ada
if($isEdit) {
    $data = array_merge($data, $rsCheck);
}
?>

<!-- CSS Local - template4.css -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<style>
/* Status grid 6 kolom untuk kebidanan */
.status-grid-kebidanan {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 10px;
}

@media (max-width: 1200px) {
    .status-grid-kebidanan {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 768px) {
    .status-grid-kebidanan {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Obstetri Grid */
.obstetri-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.obstetri-item {
    background: #fdf2f8;
    padding: 10px 12px;
    border-radius: 4px;
    border: 1px solid #fbcfe8;
}

.obstetri-item label {
    font-size: 9px;
    font-weight: 600;
    color: #be185d;
    text-transform: uppercase;
    display: block;
    margin-bottom: 4px;
}

.obstetri-item input,
.obstetri-item select {
    border: none;
    background: transparent;
    width: 100%;
    padding: 3px 0;
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.obstetri-item input:focus,
.obstetri-item select:focus {
    outline: none;
}

/* Pemeriksaan Dalam Grid */
.pem-dalam-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

@media (max-width: 768px) {
    .pem-dalam-grid {
        grid-template-columns: 1fr;
    }
}

/* Section highlight untuk obstetri */
.section-obstetri {
    background: linear-gradient(135deg, #fdf2f8 0%, #fce7f3 100%);
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
}

.section-obstetri .section-header h2 {
    color: #be185d;
}

.section-obstetri .section-header .material-icons {
    color: #be185d;
}
</style>

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header" style="background: linear-gradient(135deg, #be185d 0%, #ec4899 100%);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="material-icons">pregnant_woman</i>
                PENILAIAN AWAL MEDIS KEBIDANAN & KANDUNGAN
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
            
            <!-- Compact Progress Bar -->
            <div style="display: flex; align-items: center; gap: 10px; background: #fdf2f8; border-radius: 8px; padding: 8px 12px; border: 1px solid #fbcfe8;">
                <i class="material-icons" style="font-size: 18px; color: #be185d;">assessment</i>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 11px; color: #831843; font-weight: 500;">Kelengkapan</span>
                        <span id="progress-text" style="font-weight: bold; font-size: 14px; color: #be185d;">0%</span>
                    </div>
                    <div style="width: 150px; height: 8px; background: #fbcfe8; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status" style="font-size: 10px; color: #831843; white-space: nowrap;">(0/0)</span>
            </div>
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
            <div class="info-item">
                <i class="material-icons">hotel</i>
                <strong>Ruangan:</strong> <?php echo $rsPasien['nm_bangsal']; ?>
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
            <form id="formPenilaianMedisKebidanan" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $data['kd_dokter']; ?>">
                
                <!-- I. ANAMNESIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">description</i>
                        <h2>I. ANAMNESIS</h2>
                    </div>
                    
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Tanggal & Jam Pemeriksaan</label>
                            <input type="datetime-local" name="tanggal" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" 
                                   required>
                        </div>
                        <div class="form-group">
                            <label>Anamnesis</label>
                            <select name="anamnesis">
                                <option value="Autoanamnesis" <?php echo ($data['anamnesis'] == 'Autoanamnesis') ? 'selected' : ''; ?>>Autoanamnesis</option>
                                <option value="Alloanamnesis" <?php echo ($data['anamnesis'] == 'Alloanamnesis') ? 'selected' : ''; ?>>Alloanamnesis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Hubungan dengan Pasien (jika Alloanamnesis)</label>
                            <input type="text" name="hubungan" value="<?php echo $data['hubungan']; ?>" 
                                   placeholder="Contoh: Suami, Ibu, dll">
                        </div>
                    </div>

                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label class="required">Keluhan Utama</label>
                            <textarea name="keluhan_utama" rows="3" required 
                                      placeholder="Tuliskan keluhan utama pasien..."><?php echo $data['keluhan_utama']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Sekarang (RPS)</label>
                            <textarea name="rps" rows="3" 
                                      placeholder="Ceritakan kronologi keluhan yang dialami..."><?php echo $data['rps']; ?></textarea>
                        </div>
                    </div>

                    <div class="section-subtitle">Riwayat Kesehatan</div>

                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Riwayat Penyakit Keluarga (RPK)</label>
                            <textarea name="rpk" rows="2" 
                                      placeholder="Riwayat penyakit dalam keluarga..."><?php echo $data['rpk']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Dahulu (RPD)</label>
                            <textarea name="rpd" rows="2" 
                                      placeholder="Riwayat penyakit yang pernah diderita..."><?php echo $data['rpd']; ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Riwayat Penggunaan Obat (RPO)</label>
                            <textarea name="rpo" rows="2" 
                                      placeholder="Obat yang sedang atau pernah dikonsumsi..."><?php echo $data['rpo']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Alergi</label>
                            <input type="text" name="alergi" value="<?php echo $data['alergi']; ?>" 
                                   placeholder="Alergi obat/makanan (jika ada)">
                        </div>
                    </div>
                </div>

                <!-- II. PEMERIKSAAN FISIK -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>II. PEMERIKSAAN FISIK</h2>
                    </div>

                    <div class="section-subtitle">Keadaan Umum</div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Keadaan Umum</label>
                            <select name="keadaan">
                                <option value="Sehat" <?php echo ($data['keadaan'] == 'Sehat') ? 'selected' : ''; ?>>Sehat</option>
                                <option value="Sakit Ringan" <?php echo ($data['keadaan'] == 'Sakit Ringan') ? 'selected' : ''; ?>>Sakit Ringan</option>
                                <option value="Sakit Sedang" <?php echo ($data['keadaan'] == 'Sakit Sedang') ? 'selected' : ''; ?>>Sakit Sedang</option>
                                <option value="Sakit Berat" <?php echo ($data['keadaan'] == 'Sakit Berat') ? 'selected' : ''; ?>>Sakit Berat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kesadaran</label>
                            <select name="kesadaran">
                                <option value="Compos Mentis" <?php echo ($data['kesadaran'] == 'Compos Mentis') ? 'selected' : ''; ?>>Compos Mentis</option>
                                <option value="Apatis" <?php echo ($data['kesadaran'] == 'Apatis') ? 'selected' : ''; ?>>Apatis</option>
                                <option value="Somnolen" <?php echo ($data['kesadaran'] == 'Somnolen') ? 'selected' : ''; ?>>Somnolen</option>
                                <option value="Sopor" <?php echo ($data['kesadaran'] == 'Sopor') ? 'selected' : ''; ?>>Sopor</option>
                                <option value="Koma" <?php echo ($data['kesadaran'] == 'Koma') ? 'selected' : ''; ?>>Koma</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>GCS</label>
                            <input type="text" name="gcs" value="<?php echo $data['gcs']; ?>" 
                                   placeholder="">
                        </div>
                    </div>

                    <div class="section-subtitle">Tanda-Tanda Vital</div>
                    <div class="vital-grid">
                        <div class="vital-item">
                            <label>TD (mmHg)</label>
                            <input type="text" name="td" value="<?php echo $data['td']; ?>" placeholder="mmHg">
                        </div>
                        <div class="vital-item">
                            <label>Nadi (x/menit)</label>
                            <input type="text" name="nadi" value="<?php echo $data['nadi']; ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>RR (x/menit)</label>
                            <input type="text" name="rr" value="<?php echo $data['rr']; ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu" value="<?php echo $data['suhu']; ?>" placeholder="°C">
                        </div>
                        <div class="vital-item">
                            <label>SpO2 (%)</label>
                            <input type="text" name="spo" value="<?php echo $data['spo']; ?>" placeholder="%">
                        </div>
                        <div class="vital-item">
                            <label>BB (kg)</label>
                            <input type="text" name="bb" value="<?php echo $data['bb']; ?>" placeholder="kg">
                        </div>
                        <div class="vital-item">
                            <label>TB (cm)</label>
                            <input type="text" name="tb" value="<?php echo $data['tb']; ?>" placeholder="cm">
                        </div>
                    </div>

                    <div class="section-subtitle">Pemeriksaan Fisik per Organ</div>
                    <div class="status-grid-kebidanan">
                        <div class="status-item">
                            <label>Kepala</label>
                            <select name="kepala">
                                <option value="Normal" <?php echo ($data['kepala'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['kepala'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['kepala'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>Mata</label>
                            <select name="mata">
                                <option value="Normal" <?php echo ($data['mata'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['mata'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['mata'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>Gigi & Mulut</label>
                            <select name="gigi">
                                <option value="Normal" <?php echo ($data['gigi'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['gigi'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['gigi'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>THT</label>
                            <select name="tht">
                                <option value="Normal" <?php echo ($data['tht'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['tht'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['tht'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>Thoraks</label>
                            <select name="thoraks">
                                <option value="Normal" <?php echo ($data['thoraks'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['thoraks'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['thoraks'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>Jantung</label>
                            <select name="jantung">
                                <option value="Normal" <?php echo ($data['jantung'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['jantung'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['jantung'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>Paru</label>
                            <select name="paru">
                                <option value="Normal" <?php echo ($data['paru'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['paru'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['paru'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>Abdomen</label>
                            <select name="abdomen">
                                <option value="Normal" <?php echo ($data['abdomen'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['abdomen'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['abdomen'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>Genital</label>
                            <select name="genital">
                                <option value="Normal" <?php echo ($data['genital'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['genital'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['genital'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>Ekstremitas</label>
                            <select name="ekstremitas">
                                <option value="Normal" <?php echo ($data['ekstremitas'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['ekstremitas'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['ekstremitas'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                        <div class="status-item">
                            <label>Kulit</label>
                            <select name="kulit">
                                <option value="Normal" <?php echo ($data['kulit'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="Abnormal" <?php echo ($data['kulit'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo ($data['kulit'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Keterangan Pemeriksaan Fisik (jika ada Abnormal)</label>
                        <textarea name="ket_fisik" rows="2" 
                                  placeholder="Jelaskan temuan abnormal..."><?php echo $data['ket_fisik']; ?></textarea>
                    </div>
                </div>

                <!-- III. STATUS OBSTETRI / GINEKOLOGI -->
                <div class="section section-obstetri">
                    <div class="section-header">
                        <i class="material-icons">pregnant_woman</i>
                        <h2>III. STATUS OBSTETRI / GINEKOLOGI</h2>
                    </div>

                    <div class="obstetri-grid">
                        <div class="obstetri-item">
                            <label>TFU (Cm)</label>
                            <input type="text" name="tfu" value="<?php echo $data['tfu']; ?>" placeholder="Cm">
                        </div>
                        <div class="obstetri-item">
                            <label>TBJ (gram)</label>
                            <input type="text" name="tbj" value="<?php echo $data['tbj']; ?>" placeholder="gram">
                        </div>
                        <div class="obstetri-item">
                            <label>HIS (x/10 Menit)</label>
                            <input type="text" name="his" value="<?php echo $data['his']; ?>" placeholder="x/10 Menit">
                        </div>
                        <div class="obstetri-item">
                            <label>Kontraksi</label>
                            <select name="kontraksi">
                                <option value="Ada" <?php echo ($data['kontraksi'] == 'Ada') ? 'selected' : ''; ?>>Ada</option>
                                <option value="Tidak" <?php echo ($data['kontraksi'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                            </select>
                        </div>
                        <div class="obstetri-item">
                            <label>DJJ (Dpm)</label>
                            <input type="text" name="djj" value="<?php echo $data['djj']; ?>" placeholder="Dpm">
                        </div>
                    </div>

                    <div class="section-subtitle">Pemeriksaan Dalam</div>
                    <div class="pem-dalam-grid">
                        <div class="form-group">
                            <label>Inspeksi</label>
                            <textarea name="inspeksi" rows="3" 
                                      placeholder="Hasil pemeriksaan inspeksi..."><?php echo $data['inspeksi']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>VT (Vaginal Toucher)</label>
                            <textarea name="vt" rows="3" 
                                      placeholder="Hasil pemeriksaan VT..."><?php echo $data['vt']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Inspekulo</label>
                            <textarea name="inspekulo" rows="3" 
                                      placeholder="Hasil pemeriksaan inspekulo..."><?php echo $data['inspekulo']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>RT (Rectal Toucher)</label>
                            <textarea name="rt" rows="3" 
                                      placeholder="Hasil pemeriksaan RT..."><?php echo $data['rt']; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- IV. PEMERIKSAAN PENUNJANG -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>IV. PEMERIKSAAN PENUNJANG</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Ultrasonografi (USG)</label>
                            <textarea name="ultra" rows="4" 
                                      placeholder="Hasil pemeriksaan USG..."><?php echo $data['ultra']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Kardiotokografi (CTG)</label>
                            <textarea name="kardio" rows="4" 
                                      placeholder="Hasil pemeriksaan CTG..."><?php echo $data['kardio']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Laboratorium</label>
                            <textarea name="lab" rows="4" 
                                      placeholder="Hasil pemeriksaan lab..."><?php echo $data['lab']; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- V. DIAGNOSIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>V. DIAGNOSIS / ASESMEN</h2>
                    </div>
                    <div class="form-group">
                        <label class="required">Diagnosis / Asesmen</label>
                        <textarea name="diagnosis" rows="3" required 
                                  placeholder="Tuliskan diagnosis atau asesmen medis..."><?php echo $data['diagnosis']; ?></textarea>
                    </div>
                </div>

                <!-- VI. TATALAKSANA -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>VI. TATALAKSANA</h2>
                    </div>
                    <div class="form-group">
                        <label class="required">Tatalaksana / Rencana Terapi</label>
                        <textarea name="tata" rows="4" required 
                                  placeholder="Tuliskan rencana tatalaksana dan terapi..."><?php echo $data['tata']; ?></textarea>
                    </div>
                </div>

                <!-- VII. EDUKASI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">school</i>
                        <h2>VII. EDUKASI</h2>
                    </div>
                    <div class="form-group">
                        <label>Edukasi Pasien & Keluarga</label>
                        <textarea name="edukasi" rows="4" 
                                  placeholder="Tuliskan edukasi yang diberikan kepada pasien dan keluarga..."><?php echo $data['edukasi']; ?></textarea>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisKebidanan()">
                <i class="material-icons">arrow_back</i>
                KEMBALI
            </button>
            <?php 
            // Cek apakah boleh hapus: data ada DAN dokter login = dokter pengisi
            $bolehHapus = false;
            if($isEdit) {
                // Ambil kd_dokter dari data yang tersimpan
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                
                // Cek apakah dokter login sama dengan dokter pengisi
                if($kd_dokter_login === $kd_dokter_data) {
                    $bolehHapus = true;
                }
            }
            
            if($bolehHapus): 
            ?>
            <button type="button" id="btn-delete-kebidanan" class="btn btn-danger" onclick="confirmDeleteKebidanan()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-kebidanan" form="formPenilaianMedisKebidanan" class="btn btn-primary" style="background: #be185d;">
                <i class="material-icons">save</i>
                SIMPAN DATA
            </button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <!-- Info untuk dokter lain -->
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

<!-- Hidden form for delete -->
<!-- Delete via AJAX - handled by awalmediskebidanan.js -->

<script src="<?php echo BASE_URL; ?>/js/awalmediskebidanan.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>