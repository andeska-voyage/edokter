<?php
define('BASE_URL', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';
$no_rawat          = '';
$no_rkm_medis      = '';

if (!empty($encrypted_norawat)) $no_rawat     = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
if (!empty($encrypted_norm))    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');

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

// Cek data existing
$queryCheck = bukaquery("SELECT lkfr.*, d.nm_dokter as nama_dokter_pengisi
                         FROM layanan_kedokteran_fisik_rehabilitasi lkfr
                         LEFT JOIN dokter d ON lkfr.kd_dokter = d.kd_dokter
                         WHERE lkfr.no_rawat = '$no_rawat'");
$rsCheck             = mysqli_fetch_array($queryCheck);
$isEdit              = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login     = '';
if (!empty($kd_dokter_encrypted)) $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');

// Data default sesuai tabel layanan_kedokteran_fisik_rehabilitasi
$data = array(
    'tanggal'                  => date('Y-m-d H:i:s'),
    'kd_dokter'                => $kd_dokter_login,
    'pendamping'               => 'Tidak',
    'keterangan_pendamping'    => '',
    'anamnesa'                 => '',
    'pemeriksaan_fisik'        => '',
    'diagnosa_medis'           => '',
    'diagnosa_fungsi'          => '',
    'tatalaksana'              => '',
    'anjuran'                  => '',
    'evaluasi'                 => '',
    'suspek_penyakit_kerja'    => 'Tidak',
    'keterangan_suspek_penyakit_kerja' => '',
    'status_program'           => 'Belum Selesai',
);

if ($isEdit) $data = array_merge($data, $rsCheck);

// Enum options
$enumPendamping     = ['Tidak', 'Suami', 'Istri', 'Anak', 'Keluarga'];
$enumSuspek         = ['Tidak', 'Ya'];
$enumStatusProgram  = ['Belum Selesai', 'Sudah Selesai'];
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">

    <!-- ===== PATIENT HEADER ===== -->
    <div class="patient-header">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <h1 style="margin:0; display:flex; align-items:center; gap:10px;">
                <i class="material-icons">medical_services</i>
                LAYANAN KEDOKTERAN FISIK &amp; REHABILITASI
                <?php if ($isEdit): ?>
                    <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                    <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
            <div style="display:flex; align-items:center; gap:10px; background:#f8f9fa; border-radius:8px; padding:8px 12px;">
                <i class="material-icons" style="font-size:18px; color:#6c757d;">assessment</i>
                <div style="display:flex; flex-direction:column; gap:2px;">
                    <div style="display:flex; align-items:center; gap:5px;">
                        <span style="font-size:11px; color:#6c757d; font-weight:500;">Kelengkapan</span>
                        <span id="progress-text-layankfr" style="font-weight:bold; font-size:14px; color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px; height:8px; background:#e9ecef; border-radius:4px; overflow:hidden;">
                        <div id="progress-bar-layankfr" style="width:0%; height:100%; transition:width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-layankfr" style="font-size:10px; color:#6c757d; white-space:nowrap;">(0/0)</span>
            </div>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <?php if ($isEdit && !empty($nama_dokter_pengisi)): ?>
            <div class="info-item"><i class="material-icons">person</i><strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== FORM CARD ===== -->
    <div class="form-card">
        <div class="form-content">
            <form id="formLayananKFR" method="post" action="">
                <input type="hidden" name="no_rawat"  value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <!-- ============================================================ -->
                <!-- INFO HEADER: Dokter | Tanggal | Didampingi                   -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">info_outline</i>
                        <h2>INFORMASI KUNJUNGAN</h2>
                    </div>

                    <div class="form-grid cols-3">
                        <!-- Dokter (read-only dari data login) -->
                        <div class="form-group">
                            <label>Dokter</label>
                            <input type="text"
                                   value="<?php echo htmlspecialchars($rsPasien['nm_dokter']); ?>"
                                   readonly
                                   style="background:#f1f5f9; color:#6c757d; cursor:default;">
                        </div>

                        <!-- Tanggal -->
                        <div class="form-group">
                            <label>Tanggal &amp; Waktu</label>
                            <input type="datetime-local" name="tanggal"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" required>
                        </div>

                        <!-- Didampingi + keterangan -->
                        <div class="form-group">
                            <label>Didampingi</label>
                            <div style="display:flex; gap:6px;">
                                <select name="pendamping" id="sel_pendamping"
                                        onchange="toggleKetPendamping(this.value)"
                                        style="flex:0 0 140px;">
                                    <?php foreach ($enumPendamping as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php echo ($data['pendamping']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="keterangan_pendamping" id="ket_pendamping"
                                       value="<?php echo htmlspecialchars($data['keterangan_pendamping']); ?>"
                                       placeholder="Nama pendamping..."
                                       style="flex:1; <?php echo ($data['pendamping']=='Tidak')?'opacity:0.4;':''; ?>"
                                       <?php echo ($data['pendamping']=='Tidak')?'disabled':''; ?>>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- ANAMNESA                                                      -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">history</i>
                        <h2>ANAMNESA</h2>
                    </div>
                    <div class="form-group">
                        <label class="required">Anamnesa</label>
                        <textarea name="anamnesa" rows="5" required
                                  placeholder="Tuliskan anamnesa pasien secara lengkap..."><?php echo htmlspecialchars($data['anamnesa']); ?></textarea>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- PEMERIKSAAN FISIK & UJI FUNGSI                               -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">accessibility</i>
                        <h2>PEMERIKSAAN FISIK &amp; UJI FUNGSI</h2>
                    </div>
                    <div class="form-group">
                        <label>Pemeriksaan Fisik &amp; Uji Fungsi</label>
                        <textarea name="pemeriksaan_fisik" rows="6"
                                  placeholder="Tuliskan hasil pemeriksaan fisik dan uji fungsi..."><?php echo htmlspecialchars($data['pemeriksaan_fisik']); ?></textarea>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- DIAGNOSIS                                                     -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>DIAGNOSIS</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Diagnosis Medis (ICD-10)</label>
                            <textarea name="diagnosa_medis" rows="3"
                                      placeholder="Tuliskan diagnosis medis (ICD-10)..."><?php echo htmlspecialchars($data['diagnosa_medis']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Diagnosis Fungsi (ICD-10)</label>
                            <textarea name="diagnosa_fungsi" rows="3"
                                      placeholder="Tuliskan diagnosis fungsi (ICD-10)..."><?php echo htmlspecialchars($data['diagnosa_fungsi']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- TATA LAKSANA KFR                                             -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>TATA LAKSANA KFR</h2>
                    </div>
                    <div class="form-group">
                        <label>Tata Laksana KFR (ICD-9 CM)</label>
                        <textarea name="tatalaksana" rows="6"
                                  placeholder="Tuliskan tata laksana KFR (ICD-9 CM)..."><?php echo htmlspecialchars($data['tatalaksana']); ?></textarea>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- ANJURAN & EVALUASI                                            -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">rate_review</i>
                        <h2>ANJURAN &amp; EVALUASI</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Anjuran</label>
                            <textarea name="anjuran" rows="4"
                                      placeholder="Tuliskan anjuran untuk pasien..."><?php echo htmlspecialchars($data['anjuran']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Evaluasi</label>
                            <textarea name="evaluasi" rows="4"
                                      placeholder="Tuliskan hasil evaluasi..."><?php echo htmlspecialchars($data['evaluasi']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- SUSPEK PENYAKIT AKIBAT KERJA + STATUS PROGRAM                -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">work</i>
                        <h2>KETERANGAN TAMBAHAN</h2>
                    </div>
                    <div class="form-grid cols-2">

                        <!-- Suspek Penyakit Akibat Kerja -->
                        <div class="form-group">
                            <label>Suspek Penyakit Akibat Kerja</label>
                            <div style="display:flex; gap:6px;">
                                <select name="suspek_penyakit_kerja" id="sel_suspek"
                                        onchange="toggleKetSuspek(this.value)"
                                        style="flex:0 0 100px;">
                                    <?php foreach ($enumSuspek as $opt): ?>
                                    <option value="<?php echo $opt; ?>" <?php echo ($data['suspek_penyakit_kerja']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="keterangan_suspek_penyakit_kerja" id="ket_suspek"
                                       value="<?php echo htmlspecialchars($data['keterangan_suspek_penyakit_kerja']); ?>"
                                       placeholder="Keterangan suspek..."
                                       style="flex:1; <?php echo ($data['suspek_penyakit_kerja']=='Tidak')?'opacity:0.4;':''; ?>"
                                       <?php echo ($data['suspek_penyakit_kerja']=='Tidak')?'disabled':''; ?>>
                            </div>
                        </div>

                        <!-- Status Program -->
                        <div class="form-group">
                            <label>Status Program</label>
                            <select name="status_program">
                                <?php foreach ($enumStatusProgram as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['status_program']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                </div>

            </form>
        </div>

        <!-- ===== ACTION BAR ===== -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliLayananKFR()">
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
            <button type="button" id="btn-delete-layankfr" class="btn btn-danger" onclick="confirmDeleteLayanKFR()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif ($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>

            <button type="submit" id="btn-save-layankfr" form="formLayananKFR" class="btn btn-primary">
                <i class="material-icons">save</i>
                SIMPAN DATA
            </button>
        </div>

        <?php if ($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:12px; margin-top:15px; display:flex; align-items:center; gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404; font-size:14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>.
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Toggle input keterangan pendamping
function toggleKetPendamping(val) {
    var inp = document.getElementById('ket_pendamping');
    if (!inp) return;
    var disabled = (val === 'Tidak');
    inp.disabled = disabled;
    inp.style.opacity = disabled ? '0.4' : '1';
    if (disabled) inp.value = '';
}

// Toggle input keterangan suspek
function toggleKetSuspek(val) {
    var inp = document.getElementById('ket_suspek');
    if (!inp) return;
    var disabled = (val === 'Tidak');
    inp.disabled = disabled;
    inp.style.opacity = disabled ? '0.4' : '1';
    if (disabled) inp.value = '';
}
</script>

<script src="<?php echo BASE_URL; ?>/js/layanankedokteranfisik.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>