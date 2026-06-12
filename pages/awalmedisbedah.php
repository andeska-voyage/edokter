<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
define('BASE_URL', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';

$no_rawat    = '';
$no_rkm_medis = '';

if (!empty($encrypted_norawat)) {
    $no_rawat     = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
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

// Cek data existing — tabel bedah
$queryCheck = bukaquery("SELECT pmr.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_medis_ralan_bedah pmr
                         LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
                         WHERE pmr.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login     = '';
if (!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Nilai enum untuk status organ
$enumOrgan = ['Normal', 'Abnormal', 'Tidak Diperiksa'];

// Data default sesuai tabel penilaian_medis_ralan_bedah
$data = array(
    'tanggal'       => date('Y-m-d H:i:s'),
    'kd_dokter'     => $kd_dokter_login,
    'anamnesis'     => 'Autoanamnesis',
    'hubungan'      => '',
    'keluhan_utama' => '',
    'rps'           => '',
    'rpd'           => '',
    'rpo'           => '',
    'alergi'        => '',
    'kesadaran'     => 'Compos Mentis',
    'status'        => '',
    'td'            => '',
    'nadi'          => '',
    'suhu'          => '',
    'rr'            => '',
    'bb'            => '',
    'nyeri'         => '',
    'gcs'           => '',
    'kepala'        => 'Normal',
    'thoraks'       => 'Normal',
    'abdomen'       => 'Normal',
    'ekstremitas'   => 'Normal',
    'genetalia'     => 'Normal',
    'columna'       => 'Normal',
    'muskulos'      => 'Normal',
    'lainnya'       => '',
    'ket_lokalis'   => '',
    'lab'           => '',
    'rad'           => '',
    'pemeriksaan'   => '',
    'diagnosis'     => '',
    'diagnosis2'    => '',
    'permasalahan'  => '',
    'terapi'        => '',
    'tindakan'      => '',
    'edukasi'       => ''
);

if ($isEdit) {
    $data = array_merge($data, $rsCheck);
}

// Helper: selected untuk select option
function selOrgan($data, $field, $val) {
    return ($data[$field] === $val) ? 'selected' : '';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">

    <!-- ===== PATIENT HEADER ===== -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">content_cut</i>
                PENILAIAN AWAL MEDIS RAWAT JALAN BEDAH
                <?php if ($isEdit): ?>
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
                        <span id="progress-text-bedah" style="font-weight:bold;font-size:14px;color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px;height:8px;background:#e9ecef;border-radius:4px;overflow:hidden;">
                        <div id="progress-bar-bedah" style="width:0%;height:100%;transition:width 0.3s ease,background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-bedah" style="font-size:10px;color:#6c757d;white-space:nowrap;">(0/0)</span>
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
                <i class="material-icons">medical_services</i>
                <strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- END PATIENT HEADER -->

    <!-- ===== FORM CARD ===== -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPenilaianMedisBedah" method="post" action="">
                <input type="hidden" name="no_rawat"   value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter"  value="<?php echo $kd_dokter_login; ?>">

                <!-- ============================================================
                     I. RIWAYAT KESEHATAN
                ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">history</i>
                        <h2>I. RIWAYAT KESEHATAN</h2>
                    </div>

                    <!-- Baris 1: Tanggal, Anamnesis, Hubungan -->
                    <div class="form-grid cols-3" style="margin-bottom:10px;">
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
                                   placeholder="cth: Ibu, Suami, dll">
                        </div>
                    </div>

                    <!-- Baris 2: Keluhan Utama | RPS -->
                    <div class="form-grid cols-2" style="margin-bottom:10px;">
                        <div class="form-group">
                            <label class="required">Keluhan Utama</label>
                            <textarea name="keluhan_utama" rows="4" required
                                      placeholder="Tuliskan keluhan utama pasien..."><?php echo htmlspecialchars($data['keluhan_utama']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Sekarang</label>
                            <textarea name="rps" rows="4"
                                      placeholder="Uraikan riwayat penyakit sekarang..."><?php echo htmlspecialchars($data['rps']); ?></textarea>
                        </div>
                    </div>

                    <!-- Baris 3: RPD | RPO | Riwayat Alergi -->
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Riwayat Penyakit Dahulu</label>
                            <textarea name="rpd" rows="3"
                                      placeholder="Penyakit yang pernah diderita..."><?php echo htmlspecialchars($data['rpd']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penggunaan Obat</label>
                            <textarea name="rpo" rows="3"
                                      placeholder="Obat-obatan yang pernah/sedang dikonsumsi..."><?php echo htmlspecialchars($data['rpo']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Alergi</label>
                            <input type="text" name="alergi"
                                   value="<?php echo htmlspecialchars($data['alergi']); ?>"
                                   placeholder="Alergi obat/makanan/lainnya...">
                        </div>
                    </div>
                </div>
                <!-- END RIWAYAT KESEHATAN -->

                <!-- ============================================================
                     II. PEMERIKSAAN FISIK
                ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>II. PEMERIKSAAN FISIK</h2>
                    </div>

                    <!-- Baris 1: Kesadaran, TD, Nadi, Suhu, RR -->
                    <div class="form-grid cols-5" style="margin-bottom:10px;">
                        <div class="form-group">
                            <label>Kesadaran</label>
                            <select name="kesadaran">
                                <option value="Compos Mentis" <?php echo ($data['kesadaran'] == 'Compos Mentis') ? 'selected' : ''; ?>>Compos Mentis</option>
                                <option value="Apatis"        <?php echo ($data['kesadaran'] == 'Apatis')        ? 'selected' : ''; ?>>Apatis</option>
                                <option value="Delirium"      <?php echo ($data['kesadaran'] == 'Delirium')      ? 'selected' : ''; ?>>Delirium</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>TD (mmHg)</label>
                            <input type="text" name="td"
                                   value="<?php echo htmlspecialchars($data['td']); ?>" placeholder="mmHg">
                        </div>
                        <div class="form-group">
                            <label>Nadi (x/menit)</label>
                            <input type="text" name="nadi"
                                   value="<?php echo htmlspecialchars($data['nadi']); ?>" placeholder="x/menit">
                        </div>
                        <div class="form-group">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu"
                                   value="<?php echo htmlspecialchars($data['suhu']); ?>" placeholder="°C">
                        </div>
                        <div class="form-group">
                            <label>RR (x/menit)</label>
                            <input type="text" name="rr"
                                   value="<?php echo htmlspecialchars($data['rr']); ?>" placeholder="x/menit">
                        </div>
                    </div>

                    <!-- Baris 2: Status Nutrisi, BB, Nyeri, GCS -->
                    <div class="form-grid cols-4" style="margin-bottom:10px;">
                        <div class="form-group">
                            <label>Status Nutrisi</label>
                            <input type="text" name="status"
                                   value="<?php echo htmlspecialchars($data['status']); ?>"
                                   placeholder="cth: Baik, Kurang, Buruk">
                        </div>
                        <div class="form-group">
                            <label>BB (Kg)</label>
                            <input type="text" name="bb"
                                   value="<?php echo htmlspecialchars($data['bb']); ?>" placeholder="Kg">
                        </div>
                        <div class="form-group">
                            <label>Nyeri</label>
                            <input type="text" name="nyeri"
                                   value="<?php echo htmlspecialchars($data['nyeri']); ?>"
                                   placeholder="Skala / lokasi / karakter nyeri">
                        </div>
                        <div class="form-group">
                            <label>GCS (E,V,M)</label>
                            <input type="text" name="gcs"
                                   value="<?php echo htmlspecialchars($data['gcs']); ?>" placeholder="cth: 15 / E4V5M6">
                        </div>
                    </div>

                    <!-- Baris 3: Status Organ (6 dropdown kiri) + Textarea lainnya (kanan) -->
                    <p class="section-subtitle">Pemeriksaan Fisik Sistemik</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">

                        <!-- Kiri: 6 organ dropdown + 1 lainnya -->
                        <div class="status-grid" style="grid-template-columns:1fr 1fr;">
                            <?php
                            $organFields = [
                                'kepala'      => 'Kepala',
                                'columna'     => 'Columna Vertebralis',
                                'thoraks'     => 'Thoraks',
                                'muskulos'    => 'Muskuloskeletal',
                                'abdomen'     => 'Abdomen',
                                'genetalia'   => 'Genetalia Os Pubis',
                                'ekstremitas' => 'Ekstremitas',
                            ];
                            foreach ($organFields as $key => $label):
                            ?>
                            <div class="status-item">
                                <label><?php echo $label; ?></label>
                                <select name="<?php echo $key; ?>">
                                    <?php foreach ($enumOrgan as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php echo selOrgan($data, $key, $opt); ?>>
                                        <?php echo $opt; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Kanan: textarea lainnya -->
                        <div class="form-group">
                            <label>Keterangan Tambahan Pemeriksaan Fisik</label>
                            <textarea name="lainnya" rows="10"
                                      placeholder="Tuliskan temuan pemeriksaan fisik lainnya yang perlu dicatat..."><?php echo htmlspecialchars($data['lainnya']); ?></textarea>
                        </div>
                    </div>
                </div>
                <!-- END PEMERIKSAAN FISIK -->

                <!-- ============================================================
                     III. STATUS LOKALIS
                ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">person_outline</i>
                        <h2>III. STATUS LOKALIS</h2>
                    </div>
                    <div class="lokalis-wrapper">
                        <!-- Gambar tubuh bedah — 4 view -->
                        <div class="lokalis-image">
                            <img src="<?php echo APP_BASE_URL; ?>/images/bedah.png"
                                 alt="Gambar Status Lokalis Bedah">
                            <p>Ilustrasi Status Lokalis Bedah</p>
                        </div>
                        <!-- Textarea keterangan -->
                        <div class="form-group" style="flex:1;">
                            <label>Keterangan Status Lokalis</label>
                            <textarea name="ket_lokalis" rows="16"
                                      placeholder="Jelaskan temuan status lokalis secara detail:&#10;- Regio / lokasi keluhan (sesuai nomor pada gambar)&#10;- Inspeksi: bentuk, warna, pembengkakan, luka, dll&#10;- Palpasi: nyeri tekan, massa, konsistensi&#10;- Perkusi dan auskultasi (jika relevan)"><?php echo htmlspecialchars($data['ket_lokalis']); ?></textarea>
                        </div>
                    </div>
                </div>
                <!-- END STATUS LOKALIS -->

                <!-- ============================================================
                     IV. PEMERIKSAAN PENUNJANG
                ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>IV. PEMERIKSAAN PENUNJANG</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Laboratorium</label>
                            <textarea name="lab" rows="4"
                                      placeholder="Hasil pemeriksaan laboratorium..."><?php echo htmlspecialchars($data['lab']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Radiologi</label>
                            <textarea name="rad" rows="4"
                                      placeholder="Hasil foto rontgen, USG, CT-Scan, MRI, dll..."><?php echo htmlspecialchars($data['rad']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Penunjang Lainnya</label>
                            <textarea name="pemeriksaan" rows="4"
                                      placeholder="EKG, spirometri, atau pemeriksaan penunjang lain..."><?php echo htmlspecialchars($data['pemeriksaan']); ?></textarea>
                        </div>
                    </div>
                </div>
                <!-- END PEMERIKSAAN PENUNJANG -->

                <!-- ============================================================
                     V. DIAGNOSIS / ASESMEN
                ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>V. DIAGNOSIS / ASESMEN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label class="required">Asesmen Kerja</label>
                            <textarea name="diagnosis" rows="3" required
                                      placeholder="Tuliskan diagnosis / asesmen kerja..."><?php echo htmlspecialchars($data['diagnosis']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Asesmen Banding</label>
                            <textarea name="diagnosis2" rows="3"
                                      placeholder="Tuliskan diagnosis / asesmen banding..."><?php echo htmlspecialchars($data['diagnosis2']); ?></textarea>
                        </div>
                    </div>
                </div>
                <!-- END DIAGNOSIS -->

                <!-- ============================================================
                     VI. PERMASALAHAN & TATALAKSANA
                ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>VI. PERMASALAHAN &amp; TATALAKSANA</h2>
                    </div>
                    <div class="form-grid cols-2" style="margin-bottom:10px;">
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
                    <div class="form-group">
                        <label>Tindakan / Rencana Tindakan</label>
                        <textarea name="tindakan" rows="3"
                                  placeholder="Tuliskan tindakan bedah atau rencana tindakan..."><?php echo htmlspecialchars($data['tindakan']); ?></textarea>
                    </div>
                </div>
                <!-- END PERMASALAHAN & TATALAKSANA -->

                <!-- ============================================================
                     VII. EDUKASI
                ============================================================ -->
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
                </div>
                <!-- END EDUKASI -->

            </form>
        </div><!-- END form-content -->

        <!-- ===== ACTION BAR ===== -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisBedah()">
                <i class="material-icons">arrow_back</i>
                KEMBALI
            </button>

            <?php
            $bolehHapus = false;
            if ($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if ($kd_dokter_login === $kd_dokter_data) $bolehHapus = true;
            }
            if ($bolehHapus):
            ?>
            <button type="button" id="btn-delete-bedah" class="btn btn-danger" onclick="confirmDeleteBedah()">
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

            <button type="submit" id="btn-save-bedah" form="formPenilaianMedisBedah" class="btn btn-primary">
                <i class="material-icons">save</i>
                SIMPAN DATA
            </button>
        </div>

        <?php if ($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>.
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>

    </div><!-- END form-card -->
</div><!-- END modern-form-container -->

<script src="<?php echo BASE_URL; ?>/js/awalmedisbedah.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>