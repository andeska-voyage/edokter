<?php
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

// Handle DELETE request - via AJAX di penilaianpreinduksi.js (hapusData -> proses3.php)

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
    echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>";
    exit;
}

// Ambil kode dokter login
$kd_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) {
    $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
}

// Cek apakah sudah ada data
$queryCheck = bukaquery("SELECT pi.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_pre_induksi pi
                         LEFT JOIN dokter d ON pi.kd_dokter = d.kd_dokter
                         WHERE pi.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Data default
$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'tensi' => '', 'nadi' => '', 'rr' => '', 'suhu' => '',
    'ekg' => '', 'lain_lain' => '',
    'asesmen' => 'Sesuai Asesmen Pre Sedasi/Anestesi',
    'perencanaan' => '',
    'infus_perifier' => '', 'cvc' => '',
    'posisi' => 'Supine', 'premedikasi' => 'Oral', 'premedikasi_keterangan' => '',
    'induksi' => 'Intravena', 'induksi_keterangan' => '',
    'face_mask_no' => '', 'nasopharing_no' => '',
    'ett_no' => '', 'ett_jenis' => '', 'ett_viksasi' => '',
    'lma_no' => '', 'lma_jenis' => '',
    'tracheostomi' => '', 'bronchoscopi_fiberoptik' => '', 'glidescopi' => '',
    'lain_lain_tatalaksana' => '',
    'intubasi_sesudah_tidur' => 'Tidak', 'intubasi_oral' => 'Tidak', 'intubasi_tracheostomi' => 'Tidak',
    'intubasi_keterangan' => '',
    'sulit_ventilasi' => '', 'sulit_intubasi' => '', 'ventilasi' => '',
    'teknik_regional_jenis' => '', 'teknik_regional_lokasi' => '', 'teknik_regional_jenis_jarum' => '',
    'teknik_regional_kateter' => 'Tidak', 'teknik_regional_kateter_viksasi' => '',
    'teknik_regional_obat_obatan' => '', 'teknik_regional_komplikasi' => '', 'teknik_regional_hasil' => ''
);

if($isEdit) {
    $data = array_merge($data, $rsCheck);
}

// Hak hapus
$bolehHapus = false;
if($isEdit && isset($rsCheck['kd_dokter']) && $kd_dokter_login === $rsCheck['kd_dokter']) {
    $bolehHapus = true;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="material-icons">local_hospital</i>
                PENILAIAN PRE INDUKSI
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
                        <span id="progress-text-preinduksi" style="font-weight: bold; font-size: 14px; color: #6c757d;">0%</span>
                    </div>
                    <div style="width: 150px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar-preinduksi" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-preinduksi" style="font-size: 10px; color: #6c757d; white-space: nowrap;">(0/0)</span>
            </div>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <?php if($isEdit && !empty($nama_dokter_pengisi)): ?>
            <div class="info-item"><i class="material-icons">person</i><strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPreInduksi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <!-- I. TANDA-TANDA VITAL & ASESMEN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>I. TANDA-TANDA VITAL & ASESMEN</h2>
                    </div>

                    <input type="hidden" name="tanggal" value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>">

                    <div class="vital-grid">
                        <div class="vital-item"><label>Tensi (mmHg)</label><input type="text" name="tensi" value="<?php echo $data['tensi']; ?>" placeholder="mmHg"></div>
                        <div class="vital-item"><label>Nadi (x/mnt)</label><input type="text" name="nadi" value="<?php echo $data['nadi']; ?>" placeholder="x/menit"></div>
                        <div class="vital-item"><label>RR (x/mnt)</label><input type="text" name="rr" value="<?php echo $data['rr']; ?>" placeholder="x/menit"></div>
                        <div class="vital-item"><label>Suhu (°C)</label><input type="text" name="suhu" value="<?php echo $data['suhu']; ?>" placeholder="°C"></div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group"><label>EKG</label><input type="text" name="ekg" value="<?php echo $data['ekg']; ?>"></div>
                        <div class="form-group"><label>Lain-lain</label><input type="text" name="lain_lain" value="<?php echo $data['lain_lain']; ?>"></div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Asesmen</label>
                            <select name="asesmen">
                                <option value="Sesuai Asesmen Pre Sedasi/Anestesi" <?php echo ($data['asesmen'] == 'Sesuai Asesmen Pre Sedasi/Anestesi') ? 'selected' : ''; ?>>Sesuai Asesmen Pre Sedasi/Anestesi</option>
                                <option value="Tidak Sesuai Asesmen Pre Sedasi/Anestesi" <?php echo ($data['asesmen'] == 'Tidak Sesuai Asesmen Pre Sedasi/Anestesi') ? 'selected' : ''; ?>>Tidak Sesuai Asesmen Pre Sedasi/Anestesi</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Perencanaan</label><textarea name="perencanaan" rows="3"><?php echo $data['perencanaan']; ?></textarea></div>
                    </div>
                </div>

                <!-- II. AKSES INTRAVENA -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medication</i>
                        <h2>II. AKSES INTRAVENA</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group"><label>Infus Perifier</label><input type="text" name="infus_perifier" value="<?php echo $data['infus_perifier']; ?>"></div>
                        <div class="form-group"><label>CVC</label><input type="text" name="cvc" value="<?php echo $data['cvc']; ?>"></div>
                    </div>
                </div>

                <!-- III. POSISI, PREMEDIKASI & INDUKSI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">airline_seat_recline_normal</i>
                        <h2>III. POSISI, PREMEDIKASI & INDUKSI</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Posisi</label>
                            <select name="posisi">
                                <option value="Supine" <?php echo ($data['posisi'] == 'Supine') ? 'selected' : ''; ?>>Supine</option>
                                <option value="Prone" <?php echo ($data['posisi'] == 'Prone') ? 'selected' : ''; ?>>Prone</option>
                                <option value="Lithotomi" <?php echo ($data['posisi'] == 'Lithotomi') ? 'selected' : ''; ?>>Lithotomi</option>
                                <option value="Lateral Kiri" <?php echo ($data['posisi'] == 'Lateral Kiri') ? 'selected' : ''; ?>>Lateral Kiri</option>
                                <option value="Lateral Kanan" <?php echo ($data['posisi'] == 'Lateral Kanan') ? 'selected' : ''; ?>>Lateral Kanan</option>
                                <option value="Duduk" <?php echo ($data['posisi'] == 'Duduk') ? 'selected' : ''; ?>>Duduk</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Premedikasi</label>
                            <select name="premedikasi">
                                <option value="Oral" <?php echo ($data['premedikasi'] == 'Oral') ? 'selected' : ''; ?>>Oral</option>
                                <option value="Intravena" <?php echo ($data['premedikasi'] == 'Intravena') ? 'selected' : ''; ?>>Intravena</option>
                                <option value="Lainnya" <?php echo ($data['premedikasi'] == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Induksi</label>
                            <select name="induksi">
                                <option value="Intravena" <?php echo ($data['induksi'] == 'Intravena') ? 'selected' : ''; ?>>Intravena</option>
                                <option value="Inhalasi" <?php echo ($data['induksi'] == 'Inhalasi') ? 'selected' : ''; ?>>Inhalasi</option>
                                <option value="Lainnya" <?php echo ($data['induksi'] == 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group"><label>Ket. Premedikasi</label><input type="text" name="premedikasi_keterangan" value="<?php echo $data['premedikasi_keterangan']; ?>"></div>
                        <div class="form-group"><label>Ket. Induksi</label><input type="text" name="induksi_keterangan" value="<?php echo $data['induksi_keterangan']; ?>"></div>
                    </div>
                </div>

                <!-- IV. TATA LAKSANA JALAN NAFAS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">air</i>
                        <h2>IV. TATA LAKSANA JALAN NAFAS</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group"><label>Face Mask No</label><input type="text" name="face_mask_no" value="<?php echo $data['face_mask_no']; ?>"></div>
                        <div class="form-group"><label>Oro/Nasopharing No</label><input type="text" name="nasopharing_no" value="<?php echo $data['nasopharing_no']; ?>"></div>
                        <div class="form-group"><label>ETT No</label><input type="text" name="ett_no" value="<?php echo $data['ett_no']; ?>"></div>
                    </div>
                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group"><label>ETT Jenis</label><input type="text" name="ett_jenis" value="<?php echo $data['ett_jenis']; ?>"></div>
                        <div class="form-group"><label>ETT Fiksasi (cm)</label><input type="text" name="ett_viksasi" value="<?php echo $data['ett_viksasi']; ?>"></div>
                        <div class="form-group"><label>LMA No</label><input type="text" name="lma_no" value="<?php echo $data['lma_no']; ?>"></div>
                    </div>
                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group"><label>LMA Jenis</label><input type="text" name="lma_jenis" value="<?php echo $data['lma_jenis']; ?>"></div>
                        <div class="form-group"><label>Tracheostomi</label><input type="text" name="tracheostomi" value="<?php echo $data['tracheostomi']; ?>"></div>
                        <div class="form-group"><label>Bronchoscopi Fiberoptik</label><input type="text" name="bronchoscopi_fiberoptik" value="<?php echo $data['bronchoscopi_fiberoptik']; ?>"></div>
                    </div>
                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group"><label>Glidescopi</label><input type="text" name="glidescopi" value="<?php echo $data['glidescopi']; ?>"></div>
                        <div class="form-group"><label>Lain-lain</label><input type="text" name="lain_lain_tatalaksana" value="<?php echo $data['lain_lain_tatalaksana']; ?>"></div>
                    </div>
                </div>

                <!-- V. INTUBASI -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">masks</i>
                        <h2>V. INTUBASI</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Sesudah Tidur</label>
                            <select name="intubasi_sesudah_tidur">
                                <option value="Tidak" <?php echo ($data['intubasi_sesudah_tidur'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                                <option value="Ya" <?php echo ($data['intubasi_sesudah_tidur'] == 'Ya') ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Oral</label>
                            <select name="intubasi_oral">
                                <option value="Tidak" <?php echo ($data['intubasi_oral'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                                <option value="Ya" <?php echo ($data['intubasi_oral'] == 'Ya') ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tracheostomi</label>
                            <select name="intubasi_tracheostomi">
                                <option value="Tidak" <?php echo ($data['intubasi_tracheostomi'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                                <option value="Ya" <?php echo ($data['intubasi_tracheostomi'] == 'Ya') ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top: 10px;"><label>Keterangan</label><textarea name="intubasi_keterangan" rows="2"><?php echo $data['intubasi_keterangan']; ?></textarea></div>
                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group"><label>Sulit Ventilasi</label><input type="text" name="sulit_ventilasi" value="<?php echo $data['sulit_ventilasi']; ?>"></div>
                        <div class="form-group"><label>Sulit Intubasi</label><input type="text" name="sulit_intubasi" value="<?php echo $data['sulit_intubasi']; ?>"></div>
                        <div class="form-group"><label>Ventilasi</label><input type="text" name="ventilasi" value="<?php echo $data['ventilasi']; ?>"></div>
                    </div>
                </div>

                <!-- VI. TEKNIK REGIONAL/BLOCK PERIFER -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">vaccines</i>
                        <h2>VI. TEKNIK REGIONAL/BLOCK PERIFER</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group"><label>Jenis</label><input type="text" name="teknik_regional_jenis" value="<?php echo $data['teknik_regional_jenis']; ?>"></div>
                        <div class="form-group"><label>Lokasi</label><input type="text" name="teknik_regional_lokasi" value="<?php echo $data['teknik_regional_lokasi']; ?>"></div>
                        <div class="form-group"><label>Jenis Jarum / No</label><input type="text" name="teknik_regional_jenis_jarum" value="<?php echo $data['teknik_regional_jenis_jarum']; ?>"></div>
                    </div>
                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Kateter</label>
                            <select name="teknik_regional_kateter">
                                <option value="Tidak" <?php echo ($data['teknik_regional_kateter'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                                <option value="Ya" <?php echo ($data['teknik_regional_kateter'] == 'Ya') ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Kateter Fiksasi (cm)</label><input type="text" name="teknik_regional_kateter_viksasi" value="<?php echo $data['teknik_regional_kateter_viksasi']; ?>"></div>
                        <div class="form-group"><label>Hasil</label><input type="text" name="teknik_regional_hasil" value="<?php echo $data['teknik_regional_hasil']; ?>"></div>
                    </div>
                    <div class="form-group" style="margin-top: 10px;"><label>Obat-obatan</label><textarea name="teknik_regional_obat_obatan" rows="2"><?php echo $data['teknik_regional_obat_obatan']; ?></textarea></div>
                    <div class="form-group" style="margin-top: 10px;"><label>Komplikasi</label><textarea name="teknik_regional_komplikasi" rows="2"><?php echo $data['teknik_regional_komplikasi']; ?></textarea></div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliPreInduksi()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-preinduksi" class="btn btn-danger" onclick="confirmDeletePreInduksi()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-preinduksi" form="formPreInduksi" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
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

<!-- Delete via AJAX - handled by penilaianpreinduksi.js -->

<script src="<?php echo BASE_URL; ?>/js/penilaianpreinduksi.js?v=<?php echo time(); ?>"></script>
