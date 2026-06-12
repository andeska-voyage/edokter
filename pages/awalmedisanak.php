<?php
define('BASE_URL_ANAK', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';
$no_rawat = '';
$no_rkm_medis = '';
if(!empty($encrypted_norawat)) { $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd'); }
if(!empty($encrypted_norm)) { $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd'); }

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

// Cek data existing + join dokter untuk nama pengisi
$queryCheck = bukaquery("SELECT pmr.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_medis_ralan_anak pmr
                         LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
                         WHERE pmr.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// kd_dokter dari session
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Ambil nama dokter login
$nama_dokter_login = '';
if(!empty($kd_dokter_login)) {
    $qDokter = bukaquery("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter_login'");
    $rsDokter = mysqli_fetch_array($qDokter);
    if($rsDokter) $nama_dokter_login = $rsDokter['nm_dokter'];
}

// Data default
$data = [
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
    'abdomen' => 'Normal',
    'genital' => 'Normal',
    'ekstremitas' => 'Normal',
    'kulit' => 'Normal',
    'ket_fisik' => '',
    'ket_lokalis' => '',
    'penunjang' => '',
    'diagnosis' => '',
    'tata' => '',
    'konsul' => ''
];

if($isEdit) { $data = array_merge($data, $rsCheck); }

// Helper select organ
function anakOrganSelect($name, $value) {
    $opts = ['Normal','Abnormal','Tidak Diperiksa'];
    $html = '<select name="'.$name.'">';
    foreach($opts as $o) {
        $sel = ($value == $o) ? 'selected' : '';
        $html .= '<option value="'.$o.'" '.$sel.'>'.$o.'</option>';
    }
    $html .= '</select>';
    return $html;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_ANAK; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">child_care</i>
                PENILAIAN AWAL MEDIS RAWAT JALAN ANAK
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
            <!-- Progress Bar -->
            <div style="display:flex;align-items:center;gap:10px;background:#f8f9fa;border-radius:8px;padding:8px 12px;">
                <i class="material-icons" style="font-size:18px;color:#6c757d;">assessment</i>
                <div style="display:flex;flex-direction:column;gap:2px;">
                    <div style="display:flex;align-items:center;gap:5px;">
                        <span style="font-size:11px;color:#6c757d;font-weight:500;">Kelengkapan</span>
                        <span id="progress-text" style="font-weight:bold;font-size:14px;color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px;height:8px;background:#e9ecef;border-radius:4px;overflow:hidden;">
                        <div id="progress-bar" style="width:0%;height:100%;transition:width 0.3s ease,background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status" style="font-size:10px;color:#6c757d;white-space:nowrap;">(0/0)</span>
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

    <div class="form-card">
        <div class="form-content">
            <form id="formAwalMedisAnak" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <!-- HEADER: Dokter, Tanggal, Anamnesis -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">history</i>
                        <h2>I. RIWAYAT KESEHATAN</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Dokter</label>
                            <input type="text" value="<?php echo $kd_dokter_login; ?> - <?php echo $nama_dokter_login; ?>" readonly style="background:#f0f0f0;">
                        </div>
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
                    </div>

                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label class="required">Keluhan Utama</label>
                            <textarea name="keluhan_utama" rows="2" required placeholder="Jelaskan keluhan utama pasien..."><?php echo $data['keluhan_utama']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Sekarang</label>
                            <textarea name="rps" rows="2" placeholder="Kronologi keluhan..."><?php echo $data['rps']; ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Riwayat Penyakit Keluarga</label>
                            <textarea name="rpk" rows="2" placeholder="Riwayat penyakit dalam keluarga..."><?php echo $data['rpk']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Dahulu</label>
                            <textarea name="rpd" rows="2" placeholder="Riwayat penyakit yang pernah diderita..."><?php echo $data['rpd']; ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Riwayat Penggunaan Obat</label>
                            <textarea name="rpo" rows="2" placeholder="Obat yang sedang/pernah dikonsumsi..."><?php echo $data['rpo']; ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>⚠️ Riwayat Alergi</label>
                            <input type="text" name="alergi" value="<?php echo $data['alergi']; ?>" placeholder="Contoh: Penisilin, Tidak ada">
                        </div>
                    </div>
                </div>

                <!-- II. PEMERIKSAAN FISIK -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">accessibility</i>
                        <h2>II. PEMERIKSAAN FISIK</h2>
                    </div>

                    <div class="form-grid cols-5">
                        <div class="form-group">
                            <label>Keadaan Umum</label>
                            <select name="keadaan" required>
                                <?php foreach(['Sehat','Sakit Ringan','Sakit Sedang','Sakit Berat'] as $o): ?>
                                <option value="<?php echo $o; ?>" <?php echo ($data['keadaan'] == $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kesadaran</label>
                            <select name="kesadaran" required>
                                <?php foreach(['Compos Mentis','Apatis','Somnolen','Sopor','Coma'] as $o): ?>
                                <option value="<?php echo $o; ?>" <?php echo ($data['kesadaran'] == $o) ? 'selected' : ''; ?>><?php echo $o; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>GCS (E,V,M)</label>
                            <input type="text" name="gcs" value="<?php echo $data['gcs']; ?>" placeholder="E..V..M..">
                        </div>
                        <div class="form-group">
                            <label>TB (cm)</label>
                            <input type="text" name="tb" value="<?php echo $data['tb']; ?>" placeholder="cm">
                        </div>
                        <div class="form-group">
                            <label>BB (Kg)</label>
                            <input type="text" name="bb" value="<?php echo $data['bb']; ?>" placeholder="Kg">
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
                    </div>

                    <div class="section-subtitle">Status Pemeriksaan Organ</div>
                    <div class="status-grid">
                        <?php
                        $organs = [
                            'kepala' => 'Kepala',
                            'mata' => 'Mata',
                            'gigi' => 'Gigi & Mulut',
                            'tht' => 'THT',
                            'thoraks' => 'Thoraks',
                            'abdomen' => 'Abdomen',
                            'genital' => 'Genital & Anus',
                            'ekstremitas' => 'Ekstremitas',
                            'kulit' => 'Kulit'
                        ];
                        foreach($organs as $field => $label): ?>
                        <div class="status-item">
                            <label><?php echo $label; ?></label>
                            <?php echo anakOrganSelect($field, $data[$field]); ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group" style="margin-top:10px;">
                        <label>Keterangan Pemeriksaan Fisik (jika ada Abnormal)</label>
                        <textarea name="ket_fisik" rows="2" placeholder="Jelaskan temuan abnormal..."><?php echo $data['ket_fisik']; ?></textarea>
                    </div>
                </div>

                <!-- III. STATUS LOKALIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">location_on</i>
                        <h2>III. STATUS LOKALIS</h2>
                    </div>
                    <div class="lokalis-wrapper">
                        <div class="form-group">
                            <label>Keterangan Status Lokalis</label>
                            <textarea name="ket_lokalis" rows="10" placeholder="Jelaskan lokasi dan karakteristik keluhan secara detail..."><?php echo $data['ket_lokalis']; ?></textarea>
                        </div>
                        <div class="lokalis-image">
                            <img src="<?= APP_BASE_URL ?>/images/semua.png" alt="Gambar Lokalis">
                            <p>Gambar ilustrasi lokasi keluhan</p>
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
                        <textarea name="penunjang" rows="3" placeholder="Hasil pemeriksaan penunjang: Lab, Radiologi, EKG, dll..."><?php echo $data['penunjang']; ?></textarea>
                    </div>
                </div>

                <!-- V. DIAGNOSIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>V. DIAGNOSIS / ASESMEN</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="diagnosis" rows="3" required placeholder="Tuliskan diagnosis atau asesmen medis..."><?php echo $data['diagnosis']; ?></textarea>
                    </div>
                </div>

                <!-- VI. TATALAKSANA -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>VI. TATALAKSANA</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="tata" rows="4" required placeholder="Tuliskan rencana tatalaksana dan terapi..."><?php echo $data['tata']; ?></textarea>
                    </div>
                </div>

                <!-- VII. KONSUL/RUJUK -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">swap_horiz</i>
                        <h2>VII. KONSUL / RUJUK</h2>
                    </div>
                    <div class="form-group">
                        <textarea name="konsul" rows="2" placeholder="Konsul ke spesialis atau rujukan ke RS lain (jika perlu)..."><?php echo $data['konsul']; ?></textarea>
                    </div>
                </div>

            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisAnak()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php
            $bolehHapus = false;
            if($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if($kd_dokter_login === $kd_dokter_data) $bolehHapus = true;
            }
            if($bolehHapus): ?>
            <button type="button" id="btn-delete-anak" class="btn btn-danger" onclick="confirmDeleteAnak()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-anak" form="formAwalMedisAnak" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
            </button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:14px;"><strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>. Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL_ANAK; ?>/js/awalmedisanak.js?v=<?php echo time(); ?>"></script>
