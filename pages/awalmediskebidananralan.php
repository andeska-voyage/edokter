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

// Cek data existing dari tabel penilaian_medis_ralan_kandungan
$queryCheck = bukaquery("SELECT pmr.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_medis_ralan_kandungan pmr
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

// Data default sesuai kolom tabel penilaian_medis_ralan_kandungan
$data = array(
    'tanggal'      => date('Y-m-d H:i:s'),
    'kd_dokter'    => $kd_dokter_login,
    'anamnesis'    => 'Autoanamnesis',
    'hubungan'     => '',
    'keluhan_utama'=> '',
    'rps'          => '',
    'rpd'          => '',
    'rpk'          => '',
    'rpo'          => '',
    'alergi'       => '',
    // Pemeriksaan Fisik
    'keadaan'      => 'Sehat',
    'kesadaran'    => 'Compos Mentis',
    'gcs'          => '',
    'tb'           => '',
    'bb'           => '',
    'td'           => '',
    'nadi'         => '',
    'rr'           => '',
    'suhu'         => '',
    'spo'          => '',
    // Pemeriksaan Fisik Sistematis
    'kepala'       => 'Normal',
    'mata'         => 'Normal',
    'gigi'         => 'Normal',
    'tht'          => 'Normal',
    'thoraks'      => 'Normal',
    'abdomen'      => 'Normal',
    'genital'      => 'Normal',
    'ekstremitas'  => 'Normal',
    'kulit'        => 'Normal',
    'ket_fisik'    => '',
    // Status Obstetri / Ginekologi
    'tfu'          => '',
    'tbj'          => '',
    'his'          => '',
    'kontraksi'    => 'Ada',
    'djj'          => '',
    'inspeksi'     => '',
    'inspekulo'    => '',
    'vt'           => '',
    'rt'           => '',
    // Pemeriksaan Penunjang
    'ultra'        => '',
    'kardio'       => '',
    'lab'          => '',
    // Diagnosis & Tatalaksana
    'diagnosis'    => '',
    'tata'         => '',
    'konsul'       => '',
);

if ($isEdit) {
    $data = array_merge($data, $rsCheck);
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">

    <!-- ═══════════════════════════════════════════════════════
         PATIENT HEADER
    ═══════════════════════════════════════════════════════ -->
    <div class="patient-header">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="material-icons">pregnant_woman</i>
                PENILAIAN AWAL MEDIS RAWAT JALAN KEBIDANAN &amp; KANDUNGAN
                <?php if ($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>

            <!-- Progress Bar -->
            <div style="display: flex; align-items: center; gap: 10px; background: #f8f9fa; border-radius: 8px; padding: 8px 12px;">
                <i class="material-icons" style="font-size: 18px; color: #6c757d;">assessment</i>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 11px; color: #6c757d; font-weight: 500;">Kelengkapan</span>
                        <span id="progress-text-kebidanan" style="font-weight: bold; font-size: 14px; color: #6c757d;">0%</span>
                    </div>
                    <div style="width: 150px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar-kebidanan" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-kebidanan" style="font-size: 10px; color: #6c757d; white-space: nowrap;">(0/0)</span>
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
    </div><!-- /patient-header -->

    <!-- ═══════════════════════════════════════════════════════
         FORM CARD
    ═══════════════════════════════════════════════════════ -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPenilaianMedisKebidanan" method="post" action="">
                <input type="hidden" name="no_rawat"   value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter"  value="<?php echo $kd_dokter_login; ?>">

                <!-- ─────────────────────────────────────────────
                     I. RIWAYAT KESEHATAN
                ───────────────────────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">history</i>
                        <h2>I. RIWAYAT KESEHATAN</h2>
                    </div>

                    <!-- Tanggal, Anamnesis, Hubungan -->
                    <div class="form-grid cols-3" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label>Tanggal &amp; Waktu</label>
                            <input type="datetime-local" name="tanggal"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Anamnesis</label>
                            <select name="anamnesis">
                                <option value="Autoanamnesis"  <?php echo ($data['anamnesis'] == 'Autoanamnesis')  ? 'selected' : ''; ?>>Autoanamnesis</option>
                                <option value="Alloanamnesis"  <?php echo ($data['anamnesis'] == 'Alloanamnesis')  ? 'selected' : ''; ?>>Alloanamnesis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Hubungan (jika Alloanamnesis)</label>
                            <input type="text" name="hubungan" value="<?php echo htmlspecialchars($data['hubungan']); ?>"
                                   placeholder="Contoh: Suami, Ibu, dll">
                        </div>
                    </div>

                    <!-- Keluhan & Riwayat - baris 1 -->
                    <div class="form-grid cols-2" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label class="required">Keluhan Utama</label>
                            <textarea name="keluhan_utama" rows="3" required
                                      placeholder="Tuliskan keluhan utama pasien..."><?php echo htmlspecialchars($data['keluhan_utama']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Sekarang</label>
                            <textarea name="rps" rows="3"
                                      placeholder="Riwayat perjalanan penyakit saat ini..."><?php echo htmlspecialchars($data['rps']); ?></textarea>
                        </div>
                    </div>

                    <!-- Keluhan & Riwayat - baris 2 -->
                    <div class="form-grid cols-2" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label>Riwayat Penyakit Keluarga</label>
                            <textarea name="rpk" rows="3"
                                      placeholder="Riwayat penyakit dalam keluarga..."><?php echo htmlspecialchars($data['rpk']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Dahulu</label>
                            <textarea name="rpd" rows="3"
                                      placeholder="Riwayat penyakit yang pernah diderita..."><?php echo htmlspecialchars($data['rpd']); ?></textarea>
                        </div>
                    </div>

                    <!-- Riwayat - baris 3 -->
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Riwayat Penggunaan Obat</label>
                            <textarea name="rpo" rows="3"
                                      placeholder="Obat-obatan yang sedang / pernah digunakan..."><?php echo htmlspecialchars($data['rpo']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Alergi</label>
                            <input type="text" name="alergi" value="<?php echo htmlspecialchars($data['alergi']); ?>"
                                   placeholder="Alergi obat / makanan / lainnya...">
                        </div>
                    </div>
                </div><!-- /section I -->

                <!-- ─────────────────────────────────────────────
                     II. PEMERIKSAAN FISIK
                ───────────────────────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>II. PEMERIKSAAN FISIK</h2>
                    </div>

                    <!-- Keadaan Umum & Status -->
                    <div class="form-grid cols-4" style="margin-bottom: 10px;">
                        <div class="form-group">
                            <label>Keadaan Umum</label>
                            <select name="keadaan">
                                <option value="Sehat"        <?php echo ($data['keadaan'] == 'Sehat')        ? 'selected' : ''; ?>>Sehat</option>
                                <option value="Sakit Ringan" <?php echo ($data['keadaan'] == 'Sakit Ringan') ? 'selected' : ''; ?>>Sakit Ringan</option>
                                <option value="Sakit Sedang" <?php echo ($data['keadaan'] == 'Sakit Sedang') ? 'selected' : ''; ?>>Sakit Sedang</option>
                                <option value="Sakit Berat"  <?php echo ($data['keadaan'] == 'Sakit Berat')  ? 'selected' : ''; ?>>Sakit Berat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kesadaran</label>
                            <select name="kesadaran">
                                <option value="Compos Mentis" <?php echo ($data['kesadaran'] == 'Compos Mentis') ? 'selected' : ''; ?>>Compos Mentis</option>
                                <option value="Apatis"        <?php echo ($data['kesadaran'] == 'Apatis')        ? 'selected' : ''; ?>>Apatis</option>
                                <option value="Somnolen"      <?php echo ($data['kesadaran'] == 'Somnolen')      ? 'selected' : ''; ?>>Somnolen</option>
                                <option value="Sopor"         <?php echo ($data['kesadaran'] == 'Sopor')         ? 'selected' : ''; ?>>Sopor</option>
                                <option value="Koma"          <?php echo ($data['kesadaran'] == 'Koma')          ? 'selected' : ''; ?>>Koma</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>GCS (E,V,M)</label>
                            <input type="text" name="gcs" value="<?php echo htmlspecialchars($data['gcs']); ?>"
                                   placeholder="Contoh: 4,5,6">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <!-- spacer -->
                            <input type="text" style="visibility:hidden;" tabindex="-1">
                        </div>
                    </div>

                    <!-- Antropometri & Tanda Vital -->
                    <div class="vital-grid" style="margin-bottom: 10px;">
                        <div class="vital-item">
                            <label>TB (cm)</label>
                            <input type="text" name="tb" value="<?php echo htmlspecialchars($data['tb']); ?>" placeholder="cm">
                        </div>
                        <div class="vital-item">
                            <label>BB (Kg)</label>
                            <input type="text" name="bb" value="<?php echo htmlspecialchars($data['bb']); ?>" placeholder="Kg">
                        </div>
                        <div class="vital-item">
                            <label>TD (mmHg)</label>
                            <input type="text" name="td" value="<?php echo htmlspecialchars($data['td']); ?>" placeholder="mmHg">
                        </div>
                        <div class="vital-item">
                            <label>Nadi (x/menit)</label>
                            <input type="text" name="nadi" value="<?php echo htmlspecialchars($data['nadi']); ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>RR (x/menit)</label>
                            <input type="text" name="rr" value="<?php echo htmlspecialchars($data['rr']); ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu" value="<?php echo htmlspecialchars($data['suhu']); ?>" placeholder="°C">
                        </div>
                        <div class="vital-item">
                            <label>SpO2 (%)</label>
                            <input type="text" name="spo" value="<?php echo htmlspecialchars($data['spo']); ?>" placeholder="%">
                        </div>
                    </div>

                    <!-- Pemeriksaan Sistematis (dropdown) -->
                    <p class="section-subtitle">Pemeriksaan Fisik Sistematis</p>
                    <?php
                    $optsFisik = ['Normal', 'Abnormal', 'Tidak Diperiksa'];
                    $fieldsFisik = [
                        ['name' => 'kepala',    'label' => 'Kepala'],
                        ['name' => 'abdomen',   'label' => 'Abdomen'],
                        ['name' => 'mata',      'label' => 'Mata'],
                        ['name' => 'genital',   'label' => 'Genital & Anus'],
                        ['name' => 'gigi',      'label' => 'Gigi & Mulut'],
                        ['name' => 'ekstremitas','label'=> 'Ekstremitas'],
                        ['name' => 'tht',       'label' => 'THT'],
                        ['name' => 'kulit',     'label' => 'Kulit'],
                        ['name' => 'thoraks',   'label' => 'Thoraks'],
                    ];
                    ?>
                    <div class="status-grid" style="margin-bottom: 10px;">
                        <?php foreach ($fieldsFisik as $f): ?>
                        <div class="status-item">
                            <label><?php echo $f['label']; ?></label>
                            <select name="<?php echo $f['name']; ?>">
                                <?php foreach ($optsFisik as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data[$f['name']] == $opt) ? 'selected' : ''; ?>>
                                    <?php echo $opt; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Keterangan Fisik -->
                    <div class="form-group">
                        <label>Keterangan Temuan Fisik (jika Abnormal)</label>
                        <textarea name="ket_fisik" rows="3"
                                  placeholder="Jelaskan temuan abnormal pemeriksaan fisik..."><?php echo htmlspecialchars($data['ket_fisik']); ?></textarea>
                    </div>
                </div><!-- /section II -->

                <!-- ─────────────────────────────────────────────
                     III. STATUS OBSTETRI / GINEKOLOGI
                ───────────────────────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">child_care</i>
                        <h2>III. STATUS OBSTETRI / GINEKOLOGI</h2>
                    </div>

                    <!-- TFU, TBJ, HIS, Kontraksi, DJJ -->
                    <div class="vital-grid" style="margin-bottom: 10px;">
                        <div class="vital-item">
                            <label>TFU (Cm)</label>
                            <input type="text" name="tfu" value="<?php echo htmlspecialchars($data['tfu']); ?>" placeholder="Cm">
                        </div>
                        <div class="vital-item">
                            <label>TBJ (gram)</label>
                            <input type="text" name="tbj" value="<?php echo htmlspecialchars($data['tbj']); ?>" placeholder="gram">
                        </div>
                        <div class="vital-item">
                            <label>His (x/10 Menit)</label>
                            <input type="text" name="his" value="<?php echo htmlspecialchars($data['his']); ?>" placeholder="x/10 Menit">
                        </div>
                        <div class="vital-item">
                            <label>Kontraksi</label>
                            <select name="kontraksi" style="padding: 8px 10px; font-size: 14px; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 4px; width: 100%;">
                                <option value="Ada"   <?php echo ($data['kontraksi'] == 'Ada')   ? 'selected' : ''; ?>>Ada</option>
                                <option value="Tidak" <?php echo ($data['kontraksi'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                            </select>
                        </div>
                        <div class="vital-item">
                            <label>DJJ (Dpm)</label>
                            <input type="text" name="djj" value="<?php echo htmlspecialchars($data['djj']); ?>" placeholder="Dpm">
                        </div>
                    </div>

                    <!-- Inspeksi, VT, Inspekulo, RT -->
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Inspeksi</label>
                            <textarea name="inspeksi" rows="4"
                                      placeholder="Hasil inspeksi..."><?php echo htmlspecialchars($data['inspeksi']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>VT (Vaginal Toucher)</label>
                            <textarea name="vt" rows="4"
                                      placeholder="Hasil pemeriksaan VT..."><?php echo htmlspecialchars($data['vt']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Inspekulo</label>
                            <textarea name="inspekulo" rows="4"
                                      placeholder="Hasil inspekulo..."><?php echo htmlspecialchars($data['inspekulo']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>RT (Rectal Toucher)</label>
                            <textarea name="rt" rows="4"
                                      placeholder="Hasil pemeriksaan RT..."><?php echo htmlspecialchars($data['rt']); ?></textarea>
                        </div>
                    </div>
                </div><!-- /section III -->

                <!-- ─────────────────────────────────────────────
                     IV. PEMERIKSAAN PENUNJANG
                ───────────────────────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>IV. PEMERIKSAAN PENUNJANG</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Ultrasonografi</label>
                            <textarea name="ultra" rows="4"
                                      placeholder="Hasil USG..."><?php echo htmlspecialchars($data['ultra']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Kardiotokografi</label>
                            <textarea name="kardio" rows="4"
                                      placeholder="Hasil CTG / Kardiotokografi..."><?php echo htmlspecialchars($data['kardio']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Laboratorium</label>
                            <textarea name="lab" rows="4"
                                      placeholder="Hasil laboratorium..."><?php echo htmlspecialchars($data['lab']); ?></textarea>
                        </div>
                    </div>
                </div><!-- /section IV -->

                <!-- ─────────────────────────────────────────────
                     V. DIAGNOSIS / ASESMEN
                ───────────────────────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>V. DIAGNOSIS / ASESMEN</h2>
                    </div>
                    <div class="form-group">
                        <label class="required">Diagnosis / Asesmen</label>
                        <textarea name="diagnosis" rows="4" required
                                  placeholder="Tuliskan diagnosis kerja / asesmen klinis..."><?php echo htmlspecialchars($data['diagnosis']); ?></textarea>
                    </div>
                </div><!-- /section V -->

                <!-- ─────────────────────────────────────────────
                     VI. TATALAKSANA
                ───────────────────────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>VI. TATALAKSANA</h2>
                    </div>
                    <div class="form-group">
                        <label>Tatalaksana</label>
                        <textarea name="tata" rows="5"
                                  placeholder="Tuliskan tatalaksana / terapi / rencana tindakan..."><?php echo htmlspecialchars($data['tata']); ?></textarea>
                    </div>
                </div><!-- /section VI -->

                <!-- ─────────────────────────────────────────────
                     VII. KONSUL / RUJUK
                ───────────────────────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">send</i>
                        <h2>VII. KONSUL / RUJUK</h2>
                    </div>
                    <div class="form-group">
                        <label>Konsul / Rujuk</label>
                        <textarea name="konsul" rows="3"
                                  placeholder="Konsultasi ke dokter / bagian lain, atau rencana rujukan..."><?php echo htmlspecialchars($data['konsul']); ?></textarea>
                    </div>
                </div><!-- /section VII -->

            </form>
        </div><!-- /form-content -->

        <!-- ─────────────────────────────────────────────
             ACTION BAR
        ───────────────────────────────────────────── -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisKebidanan()">
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
            <button type="button" id="btn-delete-kebidanan" class="btn btn-danger" onclick="confirmDeleteKebidanan()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif ($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>

            <button type="submit" id="btn-save-kebidanan" form="formPenilaianMedisKebidanan" class="btn btn-primary">
                <i class="material-icons">save</i>
                SIMPAN DATA
            </button>
        </div>

        <?php if ($isEdit && !$bolehHapus): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 15px; display: flex; align-items: center; gap: 10px;">
            <i class="material-icons" style="color: #856404;">info</i>
            <span style="color: #856404; font-size: 14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>.
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>

    </div><!-- /form-card -->
</div><!-- /modern-form-container -->

<script src="<?php echo BASE_URL; ?>/js/awalmediskebidananralan.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>