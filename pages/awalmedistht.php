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

// Cek apakah sudah ada data penilaian medis THT
$queryCheck = bukaquery("SELECT pmr.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_medis_ralan_tht pmr
                         LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
                         WHERE pmr.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Ambil kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Data default sesuai tabel penilaian_medis_ralan_tht
$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'anamnesis' => 'Autoanamnesis',
    'hubungan' => '',
    'keluhan_utama' => '',
    'rps' => '',
    'rpd' => '',
    'rpo' => '',
    'alergi' => '',
    'td' => '',
    'tb' => '',
    'bb' => '',
    'suhu' => '',
    'nadi' => '',
    'rr' => '',
    'nyeri' => '',
    'status_nutrisi' => '',
    'kondisi' => '',
    'ket_lokalis' => '',
    'lab' => '',
    'rad' => '',
    'tes_pendengaran' => '',
    'penunjang' => '',
    'diagnosis' => '',
    'diagnosisbanding' => '',
    'permasalahan' => '',
    'terapi' => '',
    'tindakan' => '',
    'tatalaksana' => '',
    'edukasi' => ''
);

// Jika edit, gunakan data yang ada
if($isEdit) {
    $data = array_merge($data, $rsCheck);
}
?>

<!-- CSS Local - template4.css -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="material-icons">assignment</i>
                PENILAIAN AWAL MEDIS RAWAT JALAN THT
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
            
            <!-- Compact Progress Bar -->
            <div style="display: flex; align-items: center; gap: 10px; background: #f8f9fa; border-radius: 8px; padding: 8px 12px;">
                <i class="material-icons" style="font-size: 18px; color: #6c757d;">assessment</i>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 11px; color: #6c757d; font-weight: 500;">Kelengkapan</span>
                        <span id="progress-text-tht" style="font-weight: bold; font-size: 14px; color: #6c757d;">0%</span>
                    </div>
                    <div style="width: 150px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar-tht" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-tht" style="font-size: 10px; color: #6c757d; white-space: nowrap;">(0/0)</span>
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
            <form id="formPenilaianMedisTht" method="post" action="">
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
                            <label>Riwayat Penyakit Dahulu</label>
                            <textarea name="rpd" rows="2" 
                                      placeholder="Riwayat penyakit yang pernah diderita..."><?php echo $data['rpd']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penggunaan Obat</label>
                            <textarea name="rpo" rows="2" 
                                      placeholder="Obat yang sedang/pernah dikonsumsi..."><?php echo $data['rpo']; ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid cols-1" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>⚠️ Riwayat Alergi</label>
                            <input type="text" name="alergi" 
                                   value="<?php echo $data['alergi']; ?>" placeholder="Contoh: Penisilin, Tidak ada">
                        </div>
                    </div>
                </div>

                <!-- II. PEMERIKSAAN FISIK -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">accessibility</i>
                        <h2>II. PEMERIKSAAN FISIK</h2>
                    </div>

                    <div class="vital-grid">
                        <div class="vital-item">
                            <label>TD (mmHg)</label>
                            <input type="text" name="td" value="<?php echo $data['td']; ?>" placeholder="mmHg">
                        </div>
                        <div class="vital-item">
                            <label>TB (cm)</label>
                            <input type="text" name="tb" value="<?php echo $data['tb']; ?>" placeholder="cm">
                        </div>
                        <div class="vital-item">
                            <label>BB (Kg)</label>
                            <input type="text" name="bb" value="<?php echo $data['bb']; ?>" placeholder="Kg">
                        </div>
                        <div class="vital-item">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu" value="<?php echo $data['suhu']; ?>" placeholder="°C">
                        </div>
                        <div class="vital-item">
                            <label>Nadi (x/menit)</label>
                            <input type="text" name="nadi" value="<?php echo $data['nadi']; ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>RR (x/menit)</label>
                            <input type="text" name="rr" value="<?php echo $data['rr']; ?>" placeholder="x/menit">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Nyeri</label>
                            <input type="text" name="nyeri" 
                                   value="<?php echo $data['nyeri']; ?>" placeholder="Skala nyeri / lokasi nyeri">
                        </div>
                        <div class="form-group">
                            <label>Status Nutrisi</label>
                            <input type="text" name="status_nutrisi" 
                                   value="<?php echo $data['status_nutrisi']; ?>" placeholder="Status nutrisi pasien">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px;">
                        <label>Kondisi Umum</label>
                        <textarea name="kondisi" rows="3" 
                                  placeholder="Deskripsikan kondisi umum pasien..."><?php echo $data['kondisi']; ?></textarea>
                    </div>
                </div>

                <!-- III. STATUS LOKALIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">hearing</i>
                        <h2>III. STATUS LOKALIS</h2>
                    </div>
                    <div class="lokalis-wrapper">
                        <div class="form-group" style="flex: 1;">
                            <label>Keterangan Status Lokalis</label>
                            <textarea name="ket_lokalis" rows="12" 
                                      placeholder="Jelaskan temuan pemeriksaan THT secara detail:&#10;- Telinga (AD/AS): membran timpani, liang telinga, dll&#10;- Hidung: konka, septum, sekret, dll&#10;- Tenggorok: tonsil, faring, laring, dll"><?php echo $data['ket_lokalis']; ?></textarea>
                        </div>
                        <div class="lokalis-image">
                            <img src="<?= APP_BASE_URL ?>/images/tht.png" 
                                 alt="Gambar Lokalis THT">
                            <p>Ilustrasi organ THT</p>
                        </div>
                    </div>
                </div>

                <!-- IV. PEMERIKSAAN PENUNJANG -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>IV. PEMERIKSAAN PENUNJANG</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Laboratorium</label>
                            <textarea name="lab" rows="3" 
                                      placeholder="Hasil pemeriksaan laboratorium..."><?php echo $data['lab']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Radiologi</label>
                            <textarea name="rad" rows="3" 
                                      placeholder="Hasil pemeriksaan radiologi..."><?php echo $data['rad']; ?></textarea>
                        </div>
                    </div>
                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Tes Pendengaran</label>
                            <textarea name="tes_pendengaran" rows="3" 
                                      placeholder="Hasil tes pendengaran (Audiometri, Timpanometri, dll)..."><?php echo $data['tes_pendengaran']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Penunjang Lainnya</label>
                            <textarea name="penunjang" rows="3" 
                                      placeholder="Hasil pemeriksaan penunjang lainnya..."><?php echo $data['penunjang']; ?></textarea>
                        </div>
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
                            <label class="required">Asesmen Kerja</label>
                            <textarea name="diagnosis" rows="3" required 
                                      placeholder="Tuliskan diagnosis / asesmen kerja..."><?php echo $data['diagnosis']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Asesmen Banding</label>
                            <textarea name="diagnosisbanding" rows="3" 
                                      placeholder="Tuliskan diagnosis banding..."><?php echo $data['diagnosisbanding']; ?></textarea>
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
                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Tindakan / Rencana Tindakan</label>
                            <textarea name="tindakan" rows="3" 
                                      placeholder="Tuliskan tindakan atau rencana tindakan..."><?php echo $data['tindakan']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Tatalaksana Lainnya</label>
                            <textarea name="tatalaksana" rows="3" 
                                      placeholder="Tatalaksana lainnya (konsul, rujuk, dll)..."><?php echo $data['tatalaksana']; ?></textarea>
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
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisTht()">
                <i class="material-icons">arrow_back</i>
                KEMBALI
            </button>
            <?php 
            $bolehHapus = false;
            if($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if($kd_dokter_login === $kd_dokter_data) {
                    $bolehHapus = true;
                }
            }
            
            if($bolehHapus): 
            ?>
            <button type="button" id="btn-delete-tht" class="btn btn-danger" onclick="confirmDeleteTht()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-tht" form="formPenilaianMedisTht" class="btn btn-primary">
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

<script src="<?php echo BASE_URL; ?>/js/awalmedistht.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>
