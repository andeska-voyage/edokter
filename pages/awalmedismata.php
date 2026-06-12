<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
define('BASE_URL', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';

$no_rawat    = '';
$no_rkm_medis = '';

if (!empty($encrypted_norawat)) {
    $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
}
if (!empty($encrypted_norm)) {
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

if (!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.location.href='?act=Pasien';</script>";
    exit;
}

// Cek data existing dari tabel MATA
$queryCheck = bukaquery("SELECT pmr.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_medis_ralan_mata pmr
                         LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
                         WHERE pmr.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if (!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Data default sesuai kolom tabel penilaian_medis_ralan_mata
$data = array(
    'tanggal'      => date('Y-m-d H:i:s'),
    'kd_dokter'    => $kd_dokter_login,
    'anamnesis'    => 'Autoanamnesis',
    'hubungan'     => '',
    'keluhan_utama'=> '',
    'rps'          => '',
    'rpd'          => '',
    'rpo'          => '',
    'alergi'       => '',
    'status'       => '',
    'td'           => '',
    'nadi'         => '',
    'rr'           => '',
    'suhu'         => '',
    'nyeri'        => '',
    'bb'           => '',
    // Status Oftalmologis - Visus
    'visuskanan'   => '',
    'visuskiri'    => '',
    // CC
    'cckanan'      => '',
    'cckiri'       => '',
    // Palpebra
    'palkanan'     => '',
    'palkiri'      => '',
    // Conjungtiva
    'conkanan'     => '',
    'conkiri'      => '',
    // Cornea
    'corneakanan'  => '',
    'corneakiri'   => '',
    // COA
    'coakanan'     => '',
    'coakiri'      => '',
    // Pupil
    'pupilkanan'   => '',
    'pupilkiri'    => '',
    // Lensa
    'lensakanan'   => '',
    'lensakiri'    => '',
    // Fundus Media
    'funduskanan'  => '',
    'funduskiri'   => '',
    // Papil
    'papilkanan'   => '',
    'papilkiri'    => '',
    // Retina
    'retinakanan'  => '',
    'retinakiri'   => '',
    // Makula
    'makulakanan'  => '',
    'makulakiri'   => '',
    // TIO
    'tiokanan'     => '',
    'tiokiri'      => '',
    // MBO
    'mbokanan'     => '',
    'mbokiri'      => '',
    // Pemeriksaan Penunjang
    'lab'          => '',
    'rad'          => '',
    'penunjang'    => '',
    'tes'          => '',
    'pemeriksaan'  => '',
    // Diagnosis
    'diagnosis'    => '',
    'diagnosisbdg' => '',
    // Tatalaksana
    'permasalahan' => '',
    'terapi'       => '',
    'tindakan'     => '',
    // Edukasi
    'edukasi'      => ''
);

if ($isEdit) {
    $data = array_merge($data, $rsCheck);
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">

    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">visibility</i>
                PENILAIAN AWAL MEDIS RAWAT JALAN MATA
                <?php if ($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>

            <div style="display:flex;align-items:center;gap:10px;background:#f8f9fa;border-radius:8px;padding:8px 12px;">
                <i class="material-icons" style="font-size:18px;color:#6c757d;">assessment</i>
                <div style="display:flex;flex-direction:column;gap:2px;">
                    <div style="display:flex;align-items:center;gap:5px;">
                        <span style="font-size:11px;color:#6c757d;font-weight:500;">Kelengkapan</span>
                        <span id="progress-text-mata" style="font-weight:bold;font-size:14px;color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px;height:8px;background:#e9ecef;border-radius:4px;overflow:hidden;">
                        <div id="progress-bar-mata" style="width:0%;height:100%;transition:width 0.3s ease,background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-mata" style="font-size:10px;color:#6c757d;white-space:nowrap;">(0/0)</span>
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
            <?php if ($isEdit && !empty($nama_dokter_pengisi)): ?>
            <div class="info-item">
                <i class="material-icons">person</i>
                <strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?>
            </div>
            <?php endif; ?>
        </div>
    </div><!-- /patient-header -->

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPenilaianMedisMata" method="post" action="">
                <input type="hidden" name="no_rawat"   value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter"  value="<?php echo $kd_dokter_login; ?>">

                <!-- ================================================
                     I. RIWAYAT KESEHATAN
                     ================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">history</i>
                        <h2>I. RIWAYAT KESEHATAN</h2>
                    </div>

                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Tanggal &amp; Waktu</label>
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
                                   value="<?php echo htmlspecialchars($data['hubungan']); ?>"
                                   placeholder="Contoh: Ibu, Ayah">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label class="required">Keluhan Utama</label>
                            <textarea name="keluhan_utama" rows="3" required
                                      placeholder="Jelaskan keluhan utama pasien..."><?php echo htmlspecialchars($data['keluhan_utama']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Sekarang</label>
                            <textarea name="rps" rows="3"
                                      placeholder="Kronologi keluhan..."><?php echo htmlspecialchars($data['rps']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid cols-3" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Riwayat Penyakit Dahulu</label>
                            <textarea name="rpd" rows="2"
                                      placeholder="Riwayat penyakit yang pernah diderita..."><?php echo htmlspecialchars($data['rpd']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penggunaan Obat</label>
                            <textarea name="rpo" rows="2"
                                      placeholder="Obat yang sedang/pernah dikonsumsi..."><?php echo htmlspecialchars($data['rpo']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Alergi</label>
                            <input type="text" name="alergi"
                                   value="<?php echo htmlspecialchars($data['alergi']); ?>"
                                   placeholder="Alergi obat / makanan / lainnya">
                        </div>
                    </div>
                </div><!-- /I. RIWAYAT KESEHATAN -->

                <!-- ================================================
                     II. PEMERIKSAAN FISIK
                     ================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">accessibility</i>
                        <h2>II. PEMERIKSAAN FISIK</h2>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;">
                        <div class="vital-item">
                            <label>Status Nutrisi</label>
                            <select name="status" style="width:100%;padding:6px 8px;border:1px solid #cbd5e1;background:#f8fafc;border-radius:4px;font-size:13px;font-weight:600;color:#1e293b;">
                                <option value="Skor < 2"  <?php echo ($data['status'] == 'Skor < 2')  ? 'selected' : ''; ?>>Skor &lt; 2</option>
                                <option value="Skor >= 2" <?php echo ($data['status'] == 'Skor >= 2') ? 'selected' : ''; ?>>Skor &gt;= 2</option>
                            </select>
                        </div>
                        <div class="vital-item">
                            <label>Nyeri</label>
                            <input type="text" name="nyeri"
                                   value="<?php echo htmlspecialchars($data['nyeri']); ?>"
                                   placeholder="Skala / lokasi">
                        </div>
                        <div class="vital-item">
                            <label>TD (mmHg)</label>
                            <input type="text" name="td"
                                   value="<?php echo htmlspecialchars($data['td']); ?>"
                                   placeholder="mmHg">
                        </div>
                        <div class="vital-item">
                            <label>BB (Kg)</label>
                            <input type="text" name="bb"
                                   value="<?php echo htmlspecialchars($data['bb']); ?>"
                                   placeholder="Kg">
                        </div>
                        <div class="vital-item">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu"
                                   value="<?php echo htmlspecialchars($data['suhu']); ?>"
                                   placeholder="°C">
                        </div>
                        <div class="vital-item">
                            <label>Nadi (x/menit)</label>
                            <input type="text" name="nadi"
                                   value="<?php echo htmlspecialchars($data['nadi']); ?>"
                                   placeholder="x/mnt">
                        </div>
                        <div class="vital-item">
                            <label>RR (x/menit)</label>
                            <input type="text" name="rr"
                                   value="<?php echo htmlspecialchars($data['rr']); ?>"
                                   placeholder="x/mnt">
                        </div>
                    </div>
                </div><!-- /II. PEMERIKSAAN FISIK -->

                <!-- ================================================
                     III. STATUS OFTALMOLOGIS
                     ================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">remove_red_eye</i>
                        <h2>III. STATUS OFTALMOLOGIS</h2>
                    </div>

                    <!-- Gambar mata OD & OS -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:15px;text-align:center;">
                        <div style="background:#f8fafc;padding:12px;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="font-size:12px;font-weight:700;color:#1e40af;margin-bottom:8px;">OD : Mata Kanan</div>
                            <img src="<?= APP_BASE_URL ?>/images/mata.png"
                                 alt="Ilustrasi Mata Kanan"
                                 style="width:100%;max-height:140px;object-fit:contain;">
                        </div>
                        <div style="background:#f8fafc;padding:12px;border-radius:6px;border:1px solid #e2e8f0;">
                            <div style="font-size:12px;font-weight:700;color:#1e40af;margin-bottom:8px;">OS : Mata Kiri</div>
                            <img src="<?= APP_BASE_URL ?>/images/mata.png"
                                 alt="Ilustrasi Mata Kiri"
                                 style="width:100%;max-height:140px;object-fit:contain;transform:scaleX(-1);">
                        </div>
                    </div>

                    <!-- Tabel Status Oftalmologis OD / OS -->
                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                        <thead>
                            <tr style="background:#1e40af;color:white;">
                                <th style="padding:8px 12px;text-align:center;width:35%;">OD (Kanan)</th>
                                <th style="padding:8px 12px;text-align:center;width:30%;color:#bfdbfe;">Pemeriksaan</th>
                                <th style="padding:8px 12px;text-align:center;width:35%;">OS (Kiri)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rows = [
                                ['visuskanan',  'visuskiri',  'Visus SC'],
                                ['cckanan',     'cckiri',     'CC'],
                                ['palkanan',    'palkiri',    'Palpebra'],
                                ['conkanan',    'conkiri',    'Conjungtiva'],
                                ['corneakanan', 'corneakiri', 'Cornea'],
                                ['coakanan',    'coakiri',    'COA'],
                                ['pupilkanan',  'pupilkiri',  'Pupil'],
                                ['lensakanan',  'lensakiri',  'Lensa'],
                                ['funduskanan', 'funduskiri', 'Fundus Media'],
                                ['papilkanan',  'papilkiri',  'Papil'],
                                ['retinakanan', 'retinakiri', 'Retina'],
                                ['makulakanan', 'makulakiri', 'Makula'],
                                ['tiokanan',    'tiokiri',    'TIO'],
                                ['mbokanan',    'mbokiri',    'MBO'],
                            ];
                            foreach ($rows as $i => $row):
                                $bg = ($i % 2 === 0) ? '#f8fafc' : '#ffffff';
                            ?>
                            <tr style="background:<?php echo $bg; ?>;">
                                <td style="padding:6px 8px;">
                                    <input type="text" name="<?php echo $row[0]; ?>"
                                           value="<?php echo htmlspecialchars($data[$row[0]]); ?>"
                                           placeholder="OD"
                                           style="width:100%;padding:5px 8px;border:1px solid #e2e8f0;border-radius:4px;font-size:12px;">
                                </td>
                                <td style="padding:6px 8px;text-align:center;font-weight:600;color:#374151;background:<?php echo $bg; ?>;">
                                    <?php echo $row[2]; ?>
                                </td>
                                <td style="padding:6px 8px;">
                                    <input type="text" name="<?php echo $row[1]; ?>"
                                           value="<?php echo htmlspecialchars($data[$row[1]]); ?>"
                                           placeholder="OS"
                                           style="width:100%;padding:5px 8px;border:1px solid #e2e8f0;border-radius:4px;font-size:12px;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div><!-- /III. STATUS OFTALMOLOGIS -->

                <!-- ================================================
                     IV. PEMERIKSAAN PENUNJANG
                     ================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>IV. PEMERIKSAAN PENUNJANG</h2>
                    </div>

                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Laboratorium</label>
                            <textarea name="lab" rows="4"
                                      placeholder="Hasil / permintaan lab..."><?php echo htmlspecialchars($data['lab']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Radiologi</label>
                            <textarea name="rad" rows="4"
                                      placeholder="Hasil / permintaan radiologi..."><?php echo htmlspecialchars($data['rad']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Penunjang Lainnya</label>
                            <textarea name="penunjang" rows="4"
                                      placeholder="USG, OCT, dll..."><?php echo htmlspecialchars($data['penunjang']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Tes Penglihatan</label>
                            <textarea name="tes" rows="3"
                                      placeholder="Ishihara, Amsler grid, dll..."><?php echo htmlspecialchars($data['tes']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Pemeriksaan Lain</label>
                            <textarea name="pemeriksaan" rows="3"
                                      placeholder="Pemeriksaan penunjang lainnya..."><?php echo htmlspecialchars($data['pemeriksaan']); ?></textarea>
                        </div>
                    </div>
                </div><!-- /IV. PEMERIKSAAN PENUNJANG -->

                <!-- ================================================
                     V. DIAGNOSIS / ASESMEN
                     ================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>V. DIAGNOSIS / ASESMEN</h2>
                    </div>

                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label class="required">Asesmen Kerja</label>
                            <textarea name="diagnosis" rows="3" required
                                      placeholder="Tuliskan asesmen kerja..."><?php echo htmlspecialchars($data['diagnosis']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Asesmen Banding</label>
                            <textarea name="diagnosisbdg" rows="3"
                                      placeholder="Tuliskan asesmen banding..."><?php echo htmlspecialchars($data['diagnosisbdg']); ?></textarea>
                        </div>
                    </div>
                </div><!-- /V. DIAGNOSIS -->

                <!-- ================================================
                     VI. PERMASALAHAN & TATALAKSANA
                     ================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>VI. PERMASALAHAN &amp; TATALAKSANA</h2>
                    </div>

                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Permasalahan</label>
                            <textarea name="permasalahan" rows="3"
                                      placeholder="Tuliskan permasalahan klinis..."><?php echo htmlspecialchars($data['permasalahan']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Terapi / Pengobatan</label>
                            <textarea name="terapi" rows="3"
                                      placeholder="Tuliskan terapi / pengobatan..."><?php echo htmlspecialchars($data['terapi']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:10px;">
                        <label>Tindakan / Rencana Tindakan</label>
                        <textarea name="tindakan" rows="3"
                                  placeholder="Tuliskan tindakan atau rencana tindakan..."><?php echo htmlspecialchars($data['tindakan']); ?></textarea>
                    </div>
                </div><!-- /VI. TATALAKSANA -->

                <!-- ================================================
                     VII. EDUKASI
                     ================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">school</i>
                        <h2>VII. EDUKASI</h2>
                    </div>
                    <div class="form-group">
                        <label>Edukasi</label>
                        <textarea name="edukasi" rows="4"
                                  placeholder="Tuliskan edukasi yang diberikan kepada pasien/keluarga..."><?php echo htmlspecialchars($data['edukasi']); ?></textarea>
                    </div>
                </div><!-- /VII. EDUKASI -->

            </form>
        </div><!-- /form-content -->

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisMata()">
                <i class="material-icons">arrow_back</i>
                KEMBALI
            </button>

            <?php
            $bolehHapus = false;
            if ($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if ($kd_dokter_login === $kd_dokter_data) $bolehHapus = true;
            }
            if ($bolehHapus): ?>
            <button type="button" id="btn-delete-mata" class="btn btn-danger" onclick="confirmDeleteMata()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif ($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled
                    title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>

            <button type="submit" id="btn-save-mata" form="formPenilaianMedisMata" class="btn btn-primary">
                <i class="material-icons">save</i>
                SIMPAN DATA
            </button>
        </div><!-- /action-bar -->

        <?php if ($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin:15px 20px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>.
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>

    </div><!-- /form-card -->
</div><!-- /modern-form-container -->

<script src="<?php echo BASE_URL; ?>/js/awalmedismata.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>