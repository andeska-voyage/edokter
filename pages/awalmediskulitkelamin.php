<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
define('BASE_URL', APP_BASE_URL);

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

// Ambil data pasien
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

// Cek data existing
$queryCheck = bukaquery("SELECT pmr.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_medis_ralan_kulitdankelamin pmr
                         LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
                         WHERE pmr.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Data default sesuai tabel penilaian_medis_ralan_kulitdankelamin
$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'anamnesis' => 'Autoanamnesis',
    'hubungan' => '',
    'keluhan_utama' => '',
    'rps' => '',
    'rpd' => '',
    'rpo' => '',
    'rpk' => '',
    'kesadaran' => 'Compos Mentis',
    'status' => '',
    'td' => '',
    'nadi' => '',
    'suhu' => '',
    'rr' => '',
    'bb' => '',
    'nyeri' => '',
    'gcs' => '',
    'statusderma' => '',
    'pemeriksaan' => '',
    'diagnosis' => '',
    'diagnosis2' => '',
    'permasalahan' => '',
    'terapi' => '',
    'tindakan' => '',
    'edukasi' => ''
);

if($isEdit) {
    $data = array_merge($data, $rsCheck);
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="material-icons">assignment</i>
                PENILAIAN AWAL MEDIS RAWAT JALAN KULIT & KELAMIN
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
            
            <div style="display: flex; align-items: center; gap: 10px; background: #f8f9fa; border-radius: 8px; padding: 8px 12px;">
                <i class="material-icons" style="font-size: 18px; color: #6c757d;">assessment</i>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 11px; color: #6c757d; font-weight: 500;">Kelengkapan</span>
                        <span id="progress-text-kulkel" style="font-weight: bold; font-size: 14px; color: #6c757d;">0%</span>
                    </div>
                    <div style="width: 150px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar-kulkel" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-kulkel" style="font-size: 10px; color: #6c757d; white-space: nowrap;">(0/0)</span>
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
            <form id="formPenilaianMedisKulKel" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
                
                <!-- I. RIWAYAT KESEHATAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">history</i>
                        <h2>I. RIWAYAT KESEHATAN</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Tanggal & Waktu</label>
                            <input type="datetime-local" name="tanggal" 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Anamnesis</label>
                            <select name="anamnesis" required>
                                <option value="Autoanamnesis" <?php echo ($data['anamnesis'] == 'Autoanamnesis') ? 'selected' : ''; ?>>Autoanamnesis</option>
                                <option value="Alloanamnesis" <?php echo ($data['anamnesis'] == 'Alloanamnesis') ? 'selected' : ''; ?>>Alloanamnesis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Hubungan (jika Alloanamnesis)</label>
                            <input type="text" name="hubungan" 
                                   value="<?php echo $data['hubungan']; ?>" placeholder="Contoh: Ibu, Ayah">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label class="required">Keluhan Utama</label>
                            <textarea name="keluhan_utama" rows="3" required 
                                      placeholder="Jelaskan keluhan utama pasien..."><?php echo $data['keluhan_utama']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Sekarang</label>
                            <textarea name="rps" rows="3" 
                                      placeholder="Kronologi keluhan..."><?php echo $data['rps']; ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Riwayat Penyakit Keluarga</label>
                            <textarea name="rpk" rows="2" 
                                      placeholder="Riwayat penyakit dalam keluarga..."><?php echo $data['rpk']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Dahulu</label>
                            <textarea name="rpd" rows="2" 
                                      placeholder="Riwayat penyakit yang pernah diderita..."><?php echo $data['rpd']; ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Riwayat Penggunaan Obat</label>
                            <textarea name="rpo" rows="2" 
                                      placeholder="Obat yang sedang/pernah dikonsumsi..."><?php echo $data['rpo']; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- II. PEMERIKSAAN FISIK -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">accessibility</i>
                        <h2>II. PEMERIKSAAN FISIK</h2>
                    </div>

                    <div class="form-grid cols-4">
                        <div class="form-group">
                            <label>Kesadaran</label>
                            <select name="kesadaran">
                                <?php 
                                $kesadaranOpts = ['Compos Mentis','Apatis','Delirum'];
                                foreach($kesadaranOpts as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['kesadaran'] == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status Nutrisi</label>
                            <select name="status">
                                <option value="Skor < 2" <?php echo ($data['status'] == 'Skor < 2') ? 'selected' : ''; ?>>Skor &lt; 2</option>
                                <option value="Skor >= 2" <?php echo ($data['status'] == 'Skor >= 2') ? 'selected' : ''; ?>>Skor &gt;= 2</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>GCS (E,V,M)</label>
                            <input type="text" name="gcs" 
                                   value="<?php echo $data['gcs']; ?>" placeholder="Contoh: E4V5M6">
                        </div>
                        <div class="form-group">
                            <label>Nyeri</label>
                            <input type="text" name="nyeri" 
                                   value="<?php echo $data['nyeri']; ?>" placeholder="Skala nyeri / lokasi nyeri">
                        </div>
                    </div>

                    <div class="vital-grid" style="margin-top: 10px;">
                        <div class="vital-item">
                            <label>TD (mmHg)</label>
                            <input type="text" name="td" value="<?php echo $data['td']; ?>" placeholder="mmHg">
                        </div>
                        <div class="vital-item">
                            <label>Nadi (x/menit)</label>
                            <input type="text" name="nadi" value="<?php echo $data['nadi']; ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu" value="<?php echo $data['suhu']; ?>" placeholder="°C">
                        </div>
                        <div class="vital-item">
                            <label>RR (x/menit)</label>
                            <input type="text" name="rr" value="<?php echo $data['rr']; ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>BB (Kg)</label>
                            <input type="text" name="bb" value="<?php echo $data['bb']; ?>" placeholder="Kg">
                        </div>
                    </div>
                </div>

                <!-- III. STATUS LOKALIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">person_outline</i>
                        <h2>III. STATUS LOKALIS</h2>
                    </div>
                    <div class="lokalis-wrapper">
                        <div class="lokalis-image">
                            <img src="<?= APP_BASE_URL ?>/images/kulitkelamin.png" 
                                 alt="Gambar Lokalis Kulit & Kelamin">
                            <p>Ilustrasi Dermatovenereologis</p>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Keterangan Dermatovenereologis</label>
                            <textarea name="statusderma" rows="14" 
                                      placeholder="Jelaskan temuan pemeriksaan kulit & kelamin secara detail:&#10;- Lokasi lesi (sesuai nomor gambar)&#10;- Jenis lesi: makula, papula, vesikel, dll&#10;- Ukuran, bentuk, distribusi, warna&#10;- Permukaan, tepi, dasar lesi"><?php echo $data['statusderma']; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- IV. PEMERIKSAAN PENUNJANG -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>IV. PEMERIKSAAN PENUNJANG</h2>
                    </div>
                    <div class="form-group">
                        <label>Pemeriksaan Penunjang</label>
                        <textarea name="pemeriksaan" rows="4" 
                                  placeholder="Hasil pemeriksaan penunjang: KOH, Gram, Tzanck, Lab, dll..."><?php echo $data['pemeriksaan']; ?></textarea>
                    </div>
                </div>

                <!-- V. DIAGNOSIS / ASESMEN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>V. DIAGNOSIS / ASESMEN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label class="required">Diagnosis Kerja</label>
                            <textarea name="diagnosis" rows="3" required 
                                      placeholder="Tuliskan diagnosis kerja..."><?php echo $data['diagnosis']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Diagnosis Banding</label>
                            <textarea name="diagnosis2" rows="3" 
                                      placeholder="Tuliskan diagnosis banding..."><?php echo $data['diagnosis2']; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- VI. PERMASALAHAN & TATALAKSANA -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>VI. PERMASALAHAN & TATALAKSANA</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Permasalahan</label>
                            <textarea name="permasalahan" rows="3" 
                                      placeholder="Tuliskan permasalahan klinis..."><?php echo $data['permasalahan']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Terapi / Pengobatan</label>
                            <textarea name="terapi" rows="3" 
                                      placeholder="Tuliskan terapi / pengobatan..."><?php echo $data['terapi']; ?></textarea>
                        </div>
                    </div>
                    <div class="form-grid cols-1" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Tindakan / Rencana Tindakan</label>
                            <textarea name="tindakan" rows="3" 
                                      placeholder="Tuliskan tindakan atau rencana tindakan..."><?php echo $data['tindakan']; ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- VII. EDUKASI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">school</i>
                        <h2>VII. EDUKASI</h2>
                    </div>
                    <div class="form-group">
                        <label>Edukasi</label>
                        <textarea name="edukasi" rows="4" 
                                  placeholder="Tuliskan edukasi yang diberikan kepada pasien/keluarga..."><?php echo $data['edukasi']; ?></textarea>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisKulKel()">
                <i class="material-icons">arrow_back</i>
                KEMBALI
            </button>
            <?php 
            $bolehHapus = false;
            if($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if($kd_dokter_login === $kd_dokter_data) $bolehHapus = true;
            }
            if($bolehHapus): 
            ?>
            <button type="button" id="btn-delete-kulkel" class="btn btn-danger" onclick="confirmDeleteKulKel()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-kulkel" form="formPenilaianMedisKulKel" class="btn btn-primary">
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

<script src="<?php echo BASE_URL; ?>/js/awalmediskulitkelamin.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>
