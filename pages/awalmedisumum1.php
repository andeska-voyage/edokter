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

// Handle DELETE request
if(isset($_POST['btnHapus'])) {
    $no_rawat_hapus = $_POST['no_rawat'];
    
    // Delete data
    $queryDelete = "DELETE FROM penilaian_medis_ralan WHERE no_rawat = '$no_rawat_hapus'";
    $resultDelete = bukaquery($queryDelete);
    
    if($resultDelete) {
        echo "<script>
                alert('Data berhasil dihapus!');
                window.location.href='?act=AwalMedisUmum&rnw=" . urlencode($encrypted_norawat) . "&rm=" . urlencode($encrypted_norm) . "';
              </script>";
        exit;
    } else {
        echo "<script>alert('Gagal menghapus data!');</script>";
    }
}

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

// Cek apakah sudah ada data penilaian medis IGD
$queryCheck = bukaquery("SELECT * FROM penilaian_medis_ralan WHERE no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;

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
    'gigi' => 'Normal',
    'tht' => 'Normal',
    'thoraks' => 'Normal',
    'abdomen' => 'Normal',
    'genital' => 'Normal',
    'ekstremitas' => 'Normal',
    'kulit' => 'Normal',
    'ket_fisik' => '',
    'ket_lokalis' => '',
    'penunjang' => '',
    'diagnosis' => '',
    'tata' => '',
    'konsulrujuk' => ''
);

// Jika edit, gunakan data yang ada (merge dengan default untuk ensure semua field ada)
if($isEdit) {
    $data = array_merge($data, $rsCheck);
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template2.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Sticky Patient Header -->
    <div class="patient-header">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
            <!-- Left: Title + Icon -->
            <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                <i class="material-icons" style="font-size: 22px;">assignment</i>
                <h2 style="margin: 0; font-size: 15px; font-weight: 700; white-space: nowrap;">
                    PENILAIAN AWAL MEDIS RAWAT JALAN
                </h2>
            </div>
            
            <!-- Center: Patient Info -->
            <div style="display: flex; align-items: center; gap: 20px; flex: 1; font-size: 12px; overflow: hidden;">
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                    <i class="material-icons" style="font-size: 16px;">folder</i>
                    <strong>No. Rawat:</strong> 
                    <span><?php echo $rsPasien['no_rawat']; ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                    <i class="material-icons" style="font-size: 16px;">badge</i>
                    <strong>No. RM:</strong> 
                    <span><?php echo $rsPasien['no_rkm_medis']; ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <i class="material-icons" style="font-size: 16px;">person</i>
                    <strong>Nama:</strong> 
                    <span style="overflow: hidden; text-overflow: ellipsis;"><?php echo strtoupper($rsPasien['nm_pasien']); ?></span>
                </div>
            </div>
            
            <!-- Right: Badge -->
            <div style="flex-shrink: 0;">
                <span class="mode-badge <?php echo $isEdit ? 'mode-edit' : 'mode-add'; ?>">
                    <?php echo $isEdit ? '✏️ EDIT' : '➕ NEW'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Form Wrapper -->
    <div class="form-wrapper">
        <!-- Modern Tabs Navigation -->
        <div class="modern-tabs">
            <button class="tab-item active" onclick="switchTab(0)">
                <i class="material-icons">history</i> Riwayat
                <span class="tab-badge" id="badge-0" style="display:none;"></span>
            </button>
            <button class="tab-item" onclick="switchTab(1)">
                <i class="material-icons">accessibility</i> Pemeriksaan Fisik
                <span class="tab-badge" id="badge-1" style="display:none;"></span>
            </button>
            <button class="tab-item" onclick="switchTab(2)">
                <i class="material-icons">location_on</i> Status Lokalis
                <span class="tab-badge" id="badge-2" style="display:none;"></span>
            </button>
            <button class="tab-item" onclick="switchTab(3)">
                <i class="material-icons">science</i> Penunjang
                <span class="tab-badge" id="badge-3" style="display:none;"></span>
            </button>
            <button class="tab-item" onclick="switchTab(4)">
                <i class="material-icons">medical_services</i> Diagnosis
                <span class="tab-badge" id="badge-4" style="display:none;"></span>
            </button>
            <button class="tab-item" onclick="switchTab(5)">
                <i class="material-icons">healing</i> Tatalaksana
                <span class="tab-badge" id="badge-5" style="display:none;"></span>
            </button>
        </div>

        <!-- Form with scroll wrapper -->
        <form id="formPenilaianMedisRalan" method="post" action="">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
            
            <!-- Form Content Wrapper -->
            <div class="form-content-wrapper">
        <!-- TAB 0: RIWAYAT KESEHATAN -->
        <div class="tab-content active" id="tab-0">
            <div class="section-card">
                <div class="form-row">
                    <div class="form-group-modern">
                        <label>Tanggal & Waktu</label>
                        <input type="datetime-local" class="form-control-modern" name="tanggal" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" required>
                    </div>
                    <div class="form-group-modern">
                        <label>Anamnesis</label>
                        <select class="form-control-modern" name="anamnesis" required>
                            <option value="Autoanamnesis" <?php echo ($data['anamnesis'] == 'Autoanamnesis') ? 'selected' : ''; ?>>Autoanamnesis</option>
                            <option value="Alloanamnesis" <?php echo ($data['anamnesis'] == 'Alloanamnesis') ? 'selected' : ''; ?>>Alloanamnesis</option>
                        </select>
                    </div>
                    <div class="form-group-modern">
                        <label>Hubungan (jika Alloanamnesis)</label>
                        <input type="text" class="form-control-modern" name="hubungan" 
                               value="<?php echo $data['hubungan']; ?>" placeholder="Contoh: Ibu, Ayah, Suami">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group-modern">
                        <label>🔴 Keluhan Utama *</label>
                        <textarea class="form-control-modern" name="keluhan_utama" rows="3" required 
                                  placeholder="Jelaskan keluhan utama pasien..."><?php echo $data['keluhan_utama']; ?></textarea>
                    </div>
                    <div class="form-group-modern">
                        <label>Riwayat Penyakit Sekarang</label>
                        <textarea class="form-control-modern" name="rps" rows="3" 
                                  placeholder="Kronologi keluhan..."><?php echo $data['rps']; ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group-modern">
                        <label>Riwayat Penyakit Keluarga</label>
                        <textarea class="form-control-modern" name="rpk" rows="2" 
                                  placeholder="Riwayat penyakit dalam keluarga..."><?php echo $data['rpk']; ?></textarea>
                    </div>
                    <div class="form-group-modern">
                        <label>Riwayat Penyakit Dahulu</label>
                        <textarea class="form-control-modern" name="rpd" rows="2" 
                                  placeholder="Riwayat penyakit yang pernah diderita..."><?php echo $data['rpd']; ?></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group-modern">
                        <label>Riwayat Penggunaan Obat</label>
                        <textarea class="form-control-modern" name="rpo" rows="2" 
                                  placeholder="Obat yang sedang/pernah dikonsumsi..."><?php echo $data['rpo']; ?></textarea>
                    </div>
                    <div class="form-group-modern">
                        <label>⚠️ Riwayat Alergi</label>
                        <input type="text" class="form-control-modern" name="alergi" 
                               value="<?php echo $data['alergi']; ?>" placeholder="Contoh: Penisilin, Makanan laut, Tidak ada">
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 1: PEMERIKSAAN FISIK -->
        <div class="tab-content" id="tab-1">
            <div class="section-card">
                <div class="section-title">Keadaan Umum & Kesadaran</div>
                <div class="form-row">
                    <div class="form-group-modern">
                        <label>Keadaan Umum</label>
                        <select class="form-control-modern" name="keadaan" required>
                            <option value="Sehat" <?php echo ($data['keadaan'] == 'Sehat') ? 'selected' : ''; ?>>Sehat</option>
                            <option value="Sakit Ringan" <?php echo ($data['keadaan'] == 'Sakit Ringan') ? 'selected' : ''; ?>>Sakit Ringan</option>
                            <option value="Sakit Sedang" <?php echo ($data['keadaan'] == 'Sakit Sedang') ? 'selected' : ''; ?>>Sakit Sedang</option>
                            <option value="Sakit Berat" <?php echo ($data['keadaan'] == 'Sakit Berat') ? 'selected' : ''; ?>>Sakit Berat</option>
                        </select>
                    </div>
                    <div class="form-group-modern">
                        <label>Kesadaran</label>
                        <select class="form-control-modern" name="kesadaran" required>
                            <option value="Compos Mentis" <?php echo ($data['kesadaran'] == 'Compos Mentis') ? 'selected' : ''; ?>>Compos Mentis</option>
                            <option value="Apatis" <?php echo ($data['kesadaran'] == 'Apatis') ? 'selected' : ''; ?>>Apatis</option>
                            <option value="Somnolen" <?php echo ($data['kesadaran'] == 'Somnolen') ? 'selected' : ''; ?>>Somnolen</option>
                            <option value="Sopor" <?php echo ($data['kesadaran'] == 'Sopor') ? 'selected' : ''; ?>>Sopor</option>
                            <option value="Coma" <?php echo ($data['kesadaran'] == 'Coma') ? 'selected' : ''; ?>>Coma</option>
                        </select>
                    </div>
                    <div class="form-group-modern">
                        <label>GCS (E,V,M)</label>
                        <input type="text" class="form-control-modern" name="gcs" 
                               value="<?php echo $data['gcs']; ?>" placeholder="4,5,6">
                    </div>
                </div>

                <div class="section-title" style="margin-top: 12px;">Tanda-Tanda Vital</div>
                <div class="ttv-grid">
                    <div class="ttv-item">
                        <label>TD (mmHg)</label>
                        <input type="text" name="td" value="<?php echo $data['td']; ?>" placeholder="120/80">
                    </div>
                    <div class="ttv-item">
                        <label>Nadi (x/menit)</label>
                        <input type="text" name="nadi" value="<?php echo $data['nadi']; ?>" placeholder="80">
                    </div>
                    <div class="ttv-item">
                        <label>RR (x/menit)</label>
                        <input type="text" name="rr" value="<?php echo $data['rr']; ?>" placeholder="20">
                    </div>
                    <div class="ttv-item">
                        <label>Suhu (°C)</label>
                        <input type="text" name="suhu" value="<?php echo $data['suhu']; ?>" placeholder="36.5">
                    </div>
                    <div class="ttv-item">
                        <label>SpO2 (%)</label>
                        <input type="text" name="spo" value="<?php echo $data['spo']; ?>" placeholder="98">
                    </div>
                    <div class="ttv-item">
                        <label>BB (kg)</label>
                        <input type="text" name="bb" value="<?php echo $data['bb']; ?>" placeholder="70">
                    </div>
                    <div class="ttv-item">
                        <label>TB (cm)</label>
                        <input type="text" name="tb" value="<?php echo $data['tb']; ?>" placeholder="170">
                    </div>
                </div>

                <div class="section-title" style="margin-top: 12px;">Status Pemeriksaan Organ</div>
                <div class="status-grid">
                    <div class="status-select-group">
                        <label>Kepala</label>
                        <select class="form-control-modern" name="kepala">
                            <option value="Normal" <?php echo ($data['kepala'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Abnormal" <?php echo ($data['kepala'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                            <option value="Tidak Diperiksa" <?php echo ($data['kepala'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                        </select>
                    </div>
                    <div class="status-select-group">
                        <label>THT</label>
                        <select class="form-control-modern" name="tht">
                            <option value="Normal" <?php echo ($data['tht'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Abnormal" <?php echo ($data['tht'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                            <option value="Tidak Diperiksa" <?php echo ($data['tht'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                        </select>
                    </div>
                    <div class="status-select-group">
                        <label>Gigi & Mulut</label>
                        <select class="form-control-modern" name="gigi">
                            <option value="Normal" <?php echo ($data['gigi'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Abnormal" <?php echo ($data['gigi'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                            <option value="Tidak Diperiksa" <?php echo ($data['gigi'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                        </select>
                    </div>
                    <div class="status-select-group">
                        <label>Kulit</label>
                        <select class="form-control-modern" name="kulit">
                            <option value="Normal" <?php echo ($data['kulit'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Abnormal" <?php echo ($data['kulit'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                            <option value="Tidak Diperiksa" <?php echo ($data['kulit'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                        </select>
                    </div>
                    <div class="status-select-group">
                        <label>Thoraks</label>
                        <select class="form-control-modern" name="thoraks">
                            <option value="Normal" <?php echo ($data['thoraks'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Abnormal" <?php echo ($data['thoraks'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                            <option value="Tidak Diperiksa" <?php echo ($data['thoraks'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                        </select>
                    </div>
                    <div class="status-select-group">
                        <label>Abdomen</label>
                        <select class="form-control-modern" name="abdomen">
                            <option value="Normal" <?php echo ($data['abdomen'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Abnormal" <?php echo ($data['abdomen'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                            <option value="Tidak Diperiksa" <?php echo ($data['abdomen'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                        </select>
                    </div>
                    <div class="status-select-group">
                        <label>Genital & Anus</label>
                        <select class="form-control-modern" name="genital">
                            <option value="Normal" <?php echo ($data['genital'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Abnormal" <?php echo ($data['genital'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                            <option value="Tidak Diperiksa" <?php echo ($data['genital'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                        </select>
                    </div>
                    <div class="status-select-group">
                        <label>Ekstremitas</label>
                        <select class="form-control-modern" name="ekstremitas">
                            <option value="Normal" <?php echo ($data['ekstremitas'] == 'Normal') ? 'selected' : ''; ?>>Normal</option>
                            <option value="Abnormal" <?php echo ($data['ekstremitas'] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option>
                            <option value="Tidak Diperiksa" <?php echo ($data['ekstremitas'] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tidak Diperiksa</option>
                        </select>
                    </div>
                </div>

                <div class="form-group-modern" style="margin-top: 25px;">
                    <label>Keterangan Pemeriksaan Fisik (jika ada Abnormal)</label>
                    <textarea class="form-control-modern" name="ket_fisik" rows="3" 
                              placeholder="Jelaskan temuan abnormal..."><?php echo $data['ket_fisik']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- TAB 2: STATUS LOKALIS -->
        <div class="tab-content" id="tab-2">
            <div class="section-card">
                <div style="display: grid; grid-template-columns: 1fr 550px; gap: 25px; align-items: start;">
                    <!-- Kolom Kiri: Keterangan -->
                    <div class="form-group-modern" style="margin-bottom: 0;">
                        <label>Keterangan Status Lokalis</label>
                        <textarea class="form-control-modern" name="ket_lokalis" rows="12" 
                                  placeholder="Jelaskan lokasi dan karakteristik keluhan secara detail..."><?php echo $data['ket_lokalis']; ?></textarea>
                    </div>
                    
                    <!-- Kolom Kanan: Gambar -->
                    <div style="text-align: center;">
                        <img src="<?= APP_BASE_URL ?>/images/semua.png" 
                             alt="Gambar Lokalis" 
                             class="img-fluid" 
                             style="width: 100%; height: auto; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <p style="color: #999; font-size: 11px; margin-top: 8px; margin-bottom: 0;">Gambar ilustrasi lokasi keluhan</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: PEMERIKSAAN PENUNJANG -->
        <div class="tab-content" id="tab-3">
            <div class="section-card">
                <div class="form-row">
                    <div class="form-group-modern" style="flex: 1;">
                        <label>Pemeriksaan Penunjang</label>
                        <textarea class="form-control-modern" name="penunjang" rows="8" 
                                  placeholder="Hasil pemeriksaan penunjang: Lab, Radiologi, EKG, dll..."><?php echo isset($data['penunjang']) ? $data['penunjang'] : ''; ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: DIAGNOSIS -->
        <div class="tab-content" id="tab-4">
            <div class="section-card">
                <div class="form-group-modern">
                    <label>🔴 Diagnosis / Asesmen *</label>
                    <textarea class="form-control-modern" name="diagnosis" rows="5" required 
                              placeholder="Tuliskan diagnosis atau asesmen medis..."><?php echo $data['diagnosis']; ?></textarea>
                </div>
            </div>
        </div>

        <!-- TAB 5: TATALAKSANA -->
        <div class="tab-content" id="tab-5">
            <div class="section-card">
                <div class="form-group-modern">
                    <label>🔴 Tatalaksana / Rencana Terapi *</label>
                    <textarea class="form-control-modern" name="tata" rows="6" required 
                              placeholder="Tuliskan rencana tatalaksana dan terapi..."><?php echo $data['tata']; ?></textarea>
                </div>
                
                <div class="form-group-modern" style="margin-top: 12px;">
                    <label>Konsul / Rujuk</label>
                    <textarea class="form-control-modern" name="konsulrujuk" rows="4" 
                              placeholder="Konsul ke spesialis atau rujukan ke RS lain (jika perlu)..."><?php echo isset($data['konsulrujuk']) ? $data['konsulrujuk'] : ''; ?></textarea>
                </div>
            </div>
        </div>
        
        </div><!-- End form-content-wrapper -->

        <!-- Action Buttons -->
        <div class="action-buttons">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="progress-indicator">
                    <div class="progress-dot" id="dot-0"></div>
                    <div class="progress-dot" id="dot-1"></div>
                    <div class="progress-dot" id="dot-2"></div>
                    <div class="progress-dot" id="dot-3"></div>
                    <div class="progress-dot" id="dot-4"></div>
                    <div class="progress-dot" id="dot-5"></div>
                </div>
                <span style="font-size: 12px; color: #666; font-weight: 600;">
                    Tab <span id="current-tab-number">1</span> dari 6
                </span>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-modern btn-secondary-modern" onclick="window.history.back();">
                    <i class="material-icons" style="font-size: 16px;">arrow_back</i>
                    KEMBALI
                </button>
                <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev" onclick="previousTab()" style="display: none;">
                    <i class="material-icons" style="font-size: 16px;">navigate_before</i>
                    SEBELUMNYA
                </button>
                <button type="button" class="btn-modern btn-primary-modern" id="btn-next" onclick="nextTab()">
                    SELANJUTNYA
                    <i class="material-icons" style="font-size: 16px;">navigate_next</i>
                </button>
                <button type="submit" name="btnSimpan" class="btn-modern btn-primary-modern" id="btn-save" style="display: none;">
                    <i class="material-icons" style="font-size: 16px;">save</i>
                    SIMPAN DATA
                </button>
                <button type="button" name="btnHapus" class="btn-modern btn-danger-modern" id="btn-delete" style="display: none;" 
                        onclick="confirmDelete()" <?php echo !$isEdit ? 'disabled' : ''; ?>>
                    <i class="material-icons" style="font-size: 16px;">delete</i>
                    HAPUS DATA
                </button>
            </div>
        </div>
    </form>
    
    <!-- Hidden form for delete -->
    <form id="formHapus" method="post" action="" style="display: none;">
        <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
        <input type="hidden" name="btnHapus" value="1">
    </form>
    
    </div><!-- End form-wrapper -->
</div><!-- End modern-form-container -->

<script src="<?php echo BASE_URL; ?>/js/awalmedisumum.js?v=<?php echo time(); ?>"></script>