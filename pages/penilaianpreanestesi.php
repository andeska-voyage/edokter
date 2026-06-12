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
$queryCheck = bukaquery("SELECT pa.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_pre_anestesi pa
                         LEFT JOIN dokter d ON pa.kd_dokter = d.kd_dokter
                         WHERE pa.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Data default
$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'tanggal_operasi' => date('Y-m-d H:i:s'),
    'diagnosa' => '', 'rencana_tindakan' => '',
    'tb' => '', 'bb' => '', 'td' => '', 'io2' => '',
    'nadi' => '', 'pernapasan' => '', 'suhu' => '',
    'fisik_cardiovasculer' => '', 'fisik_paru' => '', 'fisik_abdomen' => '',
    'fisik_extrimitas' => '', 'fisik_endokrin' => '', 'fisik_ginjal' => '',
    'fisik_obatobatan' => '', 'fisik_laborat' => '', 'fisik_penunjang' => '',
    'riwayat_penyakit_alergiobat' => '', 'riwayat_penyakit_alergilainnya' => '',
    'riwayat_penyakit_terapi' => '',
    'riwayat_kebiasaan_merokok' => 'Tidak', 'riwayat_kebiasaan_ket_merokok' => '',
    'riwayat_kebiasaan_alkohol' => 'Tidak', 'riwayat_kebiasaan_ket_alkohol' => '',
    'riwayat_kebiasaan_obat' => '-', 'riwayat_kebiasaan_ket_obat' => '',
    'riwayat_medis_cardiovasculer' => '', 'riwayat_medis_respiratory' => '',
    'riwayat_medis_endocrine' => '', 'riwayat_medis_lainnya' => '',
    'asa' => '1', 'puasa' => date('Y-m-d H:i:s'),
    'rencana_anestesi' => 'GA', 'rencana_perawatan' => '', 'catatan_khusus' => ''
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
                PENILAIAN PRE ANESTESI
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
                        <span id="progress-text-preanestesi" style="font-weight: bold; font-size: 14px; color: #6c757d;">0%</span>
                    </div>
                    <div style="width: 150px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar-preanestesi" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-preanestesi" style="font-size: 10px; color: #6c757d; white-space: nowrap;">(0/0)</span>
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
            <form id="formPreAnestesi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <!-- HEADER: Diagnosa, Rencana Tindakan, Tgl Operasi -->
                <div class="section">
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Diagnosa</label>
                            <input type="text" name="diagnosa" value="<?php echo $data['diagnosa']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Rencana Tindakan</label>
                            <input type="text" name="rencana_tindakan" value="<?php echo $data['rencana_tindakan']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Tgl. Operasi</label>
                            <input type="datetime-local" name="tanggal_operasi" value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal_operasi'])); ?>">
                        </div>
                    </div>
                </div>

                <!-- I. ASESMEN FISIK -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>I. Asesmen Fisik</h2>
                    </div>

                    <input type="hidden" name="tanggal" value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>">

                    <div class="vital-grid" style="grid-template-columns: repeat(7, 1fr);">
                        <div class="vital-item"><label>TB (Cm)</label><input type="text" name="tb" value="<?php echo $data['tb']; ?>" placeholder="Cm"></div>
                        <div class="vital-item"><label>BB (Kg)</label><input type="text" name="bb" value="<?php echo $data['bb']; ?>" placeholder="Kg"></div>
                        <div class="vital-item"><label>TD (mmHg)</label><input type="text" name="td" value="<?php echo $data['td']; ?>" placeholder="mmHg"></div>
                        <div class="vital-item"><label>IO2 (%)</label><input type="text" name="io2" value="<?php echo $data['io2']; ?>" placeholder="%"></div>
                        <div class="vital-item"><label>Nadi (x/mnt)</label><input type="text" name="nadi" value="<?php echo $data['nadi']; ?>" placeholder="x/menit"></div>
                        <div class="vital-item"><label>Suhu (°C)</label><input type="text" name="suhu" value="<?php echo $data['suhu']; ?>" placeholder="°C"></div>
                        <div class="vital-item"><label>Pernapasan (x/mnt)</label><input type="text" name="pernapasan" value="<?php echo $data['pernapasan']; ?>" placeholder="x/menit"></div>
                    </div>

                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group"><label>Cardiovasculer</label><input type="text" name="fisik_cardiovasculer" value="<?php echo $data['fisik_cardiovasculer']; ?>"></div>
                        <div class="form-group"><label>Paru</label><input type="text" name="fisik_paru" value="<?php echo $data['fisik_paru']; ?>"></div>
                        <div class="form-group"><label>Abdomen</label><input type="text" name="fisik_abdomen" value="<?php echo $data['fisik_abdomen']; ?>"></div>
                    </div>
                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group"><label>Extrimitas</label><input type="text" name="fisik_extrimitas" value="<?php echo $data['fisik_extrimitas']; ?>"></div>
                        <div class="form-group"><label>Endokrin</label><input type="text" name="fisik_endokrin" value="<?php echo $data['fisik_endokrin']; ?>"></div>
                        <div class="form-group"><label>Ginjal</label><input type="text" name="fisik_ginjal" value="<?php echo $data['fisik_ginjal']; ?>"></div>
                    </div>
                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group"><label>Obat-obatan</label><input type="text" name="fisik_obatobatan" value="<?php echo $data['fisik_obatobatan']; ?>"></div>
                        <div class="form-group"><label>Laboratorium</label><input type="text" name="fisik_laborat" value="<?php echo $data['fisik_laborat']; ?>"></div>
                        <div class="form-group"><label>Penunjang</label><input type="text" name="fisik_penunjang" value="<?php echo $data['fisik_penunjang']; ?>"></div>
                    </div>
                </div>

                <!-- II. RIWAYAT PENYAKIT -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>II. Riwayat Penyakit</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group"><label>Alergi Obat</label><input type="text" name="riwayat_penyakit_alergiobat" value="<?php echo $data['riwayat_penyakit_alergiobat']; ?>"></div>
                        <div class="form-group"><label>Alergi Lainnya</label><input type="text" name="riwayat_penyakit_alergilainnya" value="<?php echo $data['riwayat_penyakit_alergilainnya']; ?>"></div>
                        <div class="form-group"><label>Terapi</label><input type="text" name="riwayat_penyakit_terapi" value="<?php echo $data['riwayat_penyakit_terapi']; ?>"></div>
                    </div>

                    <!-- Kebiasaan: Merokok & Alkohol (1 baris) -->
                    <div class="form-grid" style="margin-top: 12px; grid-template-columns: 100px 80px 1fr 100px 80px 1fr;">
                        <div class="form-group"><label>Merokok</label>
                            <select name="riwayat_kebiasaan_merokok">
                                <option value="Tidak" <?php echo ($data['riwayat_kebiasaan_merokok'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                                <option value="Ya" <?php echo ($data['riwayat_kebiasaan_merokok'] == 'Ya') ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Btg/Hr</label><input type="text" name="riwayat_kebiasaan_ket_merokok" value="<?php echo $data['riwayat_kebiasaan_ket_merokok']; ?>" placeholder="0"></div>
                        <div class="form-group"></div>
                        <div class="form-group"><label>Alkohol</label>
                            <select name="riwayat_kebiasaan_alkohol">
                                <option value="Tidak" <?php echo ($data['riwayat_kebiasaan_alkohol'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                                <option value="Ya" <?php echo ($data['riwayat_kebiasaan_alkohol'] == 'Ya') ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Gls/Hr</label><input type="text" name="riwayat_kebiasaan_ket_alkohol" value="<?php echo $data['riwayat_kebiasaan_ket_alkohol']; ?>" placeholder="0"></div>
                        <div class="form-group"></div>
                    </div>

                    <!-- Penggunaan Obat -->
                    <div class="form-grid cols-3" style="margin-top: 10px;">
                        <div class="form-group"><label>Penggunaan Obat</label>
                            <select name="riwayat_kebiasaan_obat">
                                <?php foreach(['-', 'Obat Obatan', 'Vitamin', 'Jamu Jamuan'] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['riwayat_kebiasaan_obat'] == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: span 2;"><label>Keterangan Obat</label><input type="text" name="riwayat_kebiasaan_ket_obat" value="<?php echo $data['riwayat_kebiasaan_ket_obat']; ?>"></div>
                    </div>
                </div>

                <!-- III. RIWAYAT MEDIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_information</i>
                        <h2>III. Riwayat Medis</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group"><label>Cardiovasculer</label><input type="text" name="riwayat_medis_cardiovasculer" value="<?php echo $data['riwayat_medis_cardiovasculer']; ?>"></div>
                        <div class="form-group"><label>Respiratory</label><input type="text" name="riwayat_medis_respiratory" value="<?php echo $data['riwayat_medis_respiratory']; ?>"></div>
                    </div>
                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group"><label>Endocrine</label><input type="text" name="riwayat_medis_endocrine" value="<?php echo $data['riwayat_medis_endocrine']; ?>"></div>
                        <div class="form-group"><label>Lainnya</label><input type="text" name="riwayat_medis_lainnya" value="<?php echo $data['riwayat_medis_lainnya']; ?>"></div>
                    </div>
                </div>

                <!-- IV. RENCANA & CATATAN -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">assignment</i>
                        <h2>IV. Rencana & Catatan</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Rencana Anestesi</label>
                            <select name="rencana_anestesi">
                                <?php foreach(['GA', 'RA Spinal', 'RA Epidural', 'RA Combine'] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['rencana_anestesi'] == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Angka ASA</label>
                            <select name="asa">
                                <?php foreach(['1','2','3','4','5','E'] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['asa'] == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Puasa</label>
                            <input type="datetime-local" name="puasa" value="<?php echo date('Y-m-d\TH:i', strtotime($data['puasa'])); ?>">
                        </div>
                    </div>
                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>Rencana Perawatan</label>
                            <input type="text" name="rencana_perawatan" value="<?php echo $data['rencana_perawatan']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Catatan Khusus</label>
                            <input type="text" name="catatan_khusus" value="<?php echo $data['catatan_khusus']; ?>">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliPreAnestesi()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-preanestesi" class="btn btn-danger" onclick="confirmDeletePreAnestesi()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-preanestesi" form="formPreAnestesi" class="btn btn-primary">
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

<!-- Delete via AJAX - handled by penilaianpreanestesi.js -->

<script src="<?php echo BASE_URL; ?>/js/penilaianpreanestesi.js?v=<?php echo time(); ?>"></script>
