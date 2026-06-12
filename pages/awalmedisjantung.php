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
$queryCheck = bukaquery("SELECT pmr.*, d.nm_dokter as nama_dokter_pengisi
                         FROM penilaian_medis_ralan_jantung pmr
                         LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
                         WHERE pmr.no_rawat = '$no_rawat'");
$rsCheck             = mysqli_fetch_array($queryCheck);
$isEdit              = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login     = '';
if (!empty($kd_dokter_encrypted)) $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');

// Data default sesuai tabel penilaian_medis_ralan_jantung
$data = array(
    'tanggal'               => date('Y-m-d H:i:s'),
    'kd_dokter'             => $kd_dokter_login,
    'anamnesis'             => 'Autoanamnesis',
    'hubungan'              => '',
    'keluhan_utama'         => '',
    'rps'                   => '',
    'rpk'                   => '',
    'rpd'                   => '',
    'rpo'                   => '',
    'alergi'                => '',
    'td'                    => '',
    'bb'                    => '',
    'tb'                    => '',
    'suhu'                  => '',
    'nadi'                  => '',
    'rr'                    => '',
    'keadaan_umum'          => 'Sehat',
    'nyeri'                 => '',
    'status_nutrisi'        => '',
    'jantung'               => 'Tidak Diperiksa',
    'keterangan_jantung'    => '',
    'paru'                  => 'Tidak Diperiksa',
    'keterangan_paru'       => '',
    'ekstrimitas'           => 'Tidak Diperiksa',
    'keterangan_ekstrimitas'=> '',
    'lainnya'               => '',
    'lab'                   => '',
    'ekg'                   => '',
    'penunjang_lain'        => '',
    'diagnosis'             => '',
    'diagnosis2'            => '',
    'permasalahan'          => '',
    'terapi'                => '',
    'tindakan'              => '',
    'edukasi'               => ''
);

if ($isEdit) $data = array_merge($data, $rsCheck);

// Enum options
$enumOrgan        = ['Normal', 'Abnormal', 'Tidak Diperiksa'];
$enumKeadaanUmum  = ['Sehat', 'Sakit Ringan', 'Sakit Sedang', 'Sakit Berat'];

$statusColor = [
    'Normal'          => ['bg' => '#dcfce7', 'color' => '#166534'],
    'Abnormal'        => ['bg' => '#fee2e2', 'color' => '#991b1b'],
    'Tidak Diperiksa' => ['bg' => '#f1f5f9', 'color' => '#475569'],
];

function renderOrganRowJantung($field, $ketField, $label, $icon, $data, $enumOrgan, $statusColor) {
    $cv = isset($data[$field]) ? $data[$field] : 'Tidak Diperiksa';
    $sc = isset($statusColor[$cv]) ? $statusColor[$cv] : $statusColor['Tidak Diperiksa'];
    $ket = isset($data[$ketField]) ? htmlspecialchars($data[$ketField]) : '';
    echo '<div style="display:flex; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; overflow:hidden; margin-bottom:6px;"
               onmouseover="this.style.boxShadow=\'0 2px 8px rgba(102,126,234,0.12)\'"
               onmouseout="this.style.boxShadow=\'none\'">';
    echo '<div style="display:flex; align-items:center; gap:5px; padding:0 10px; min-width:130px; background:linear-gradient(135deg,#667eea,#764ba2); color:white; align-self:stretch;">';
    echo '<i class="material-icons" style="font-size:13px; opacity:0.85;">' . $icon . '</i>';
    echo '<span style="font-size:11px; font-weight:700; letter-spacing:0.3px; text-transform:uppercase;">' . $label . '</span>';
    echo '</div>';
    echo '<div style="position:relative; flex-shrink:0;">';
    echo '<select name="' . $field . '"
            style="appearance:none; -webkit-appearance:none;
                   padding:9px 28px 9px 10px; border:none; border-right:1px solid #e2e8f0;
                   font-size:12px; font-weight:600; cursor:pointer; outline:none;
                   background:' . $sc['bg'] . '; color:' . $sc['color'] . '; min-width:148px;"
            onchange="
                var c={\'Normal\':{\'bg\':\'#dcfce7\',\'color\':\'#166534\'},\'Abnormal\':{\'bg\':\'#fee2e2\',\'color\':\'#991b1b\'},\'Tidak Diperiksa\':{\'bg\':\'#f1f5f9\',\'color\':\'#475569\'}};
                var s=c[this.value]||c[\'Tidak Diperiksa\'];
                this.style.background=s.bg; this.style.color=s.color;
                this.nextElementSibling.style.color=s.color;
            ">';
    foreach ($enumOrgan as $opt) {
        echo '<option value="' . $opt . '"' . ($cv === $opt ? ' selected' : '') . '>' . $opt . '</option>';
    }
    echo '</select>';
    echo '<span style="position:absolute; right:7px; top:50%; transform:translateY(-50%); pointer-events:none; color:' . $sc['color'] . '; font-size:12px;">▾</span>';
    echo '</div>';
    echo '<input type="text" name="' . $ketField . '" value="' . $ket . '"
           placeholder="Keterangan..."
           style="flex:1; border:none; padding:9px 10px; font-size:12px; background:white; outline:none; min-width:0; color:#374151;"
           onfocus="this.closest(\'div[style]\').style.boxShadow=\'0 0 0 2px rgba(102,126,234,0.25)\'"
           onblur="this.closest(\'div[style]\').style.boxShadow=\'none\'">';
    echo '</div>';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">

    <!-- ===== PATIENT HEADER ===== -->
    <div class="patient-header">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <h1 style="margin:0; display:flex; align-items:center; gap:10px;">
                <i class="material-icons">assignment</i>
                PENILAIAN AWAL MEDIS RAWAT JALAN JANTUNG
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
                        <span id="progress-text-jantung" style="font-weight:bold; font-size:14px; color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px; height:8px; background:#e9ecef; border-radius:4px; overflow:hidden;">
                        <div id="progress-bar-jantung" style="width:0%; height:100%; transition:width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-jantung" style="font-size:10px; color:#6c757d; white-space:nowrap;">(0/0)</span>
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
            <form id="formPenilaianMedisJantung" method="post" action="">
                <input type="hidden" name="no_rawat"  value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <!-- ============================================================ -->
                <!-- I. RIWAYAT KESEHATAN                                         -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">history</i>
                        <h2>I. RIWAYAT KESEHATAN</h2>
                    </div>

                    <!-- Baris 1: Tanggal | Anamnesis | Hubungan -->
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Tanggal &amp; Waktu</label>
                            <input type="datetime-local" name="tanggal"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Anamnesis</label>
                            <select name="anamnesis">
                                <option value="Autoanamnesis" <?php echo ($data['anamnesis']=='Autoanamnesis')?'selected':''; ?>>Autoanamnesis</option>
                                <option value="Alloanamnesis" <?php echo ($data['anamnesis']=='Alloanamnesis')?'selected':''; ?>>Alloanamnesis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Hubungan (jika Alloanamnesis)</label>
                            <input type="text" name="hubungan"
                                   value="<?php echo htmlspecialchars($data['hubungan']); ?>"
                                   placeholder="Contoh: Ibu, Ayah">
                        </div>
                    </div>

                    <!-- Baris 2: Keluhan Utama | Riwayat Penyakit Sekarang -->
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

                    <!-- Baris 3: Riwayat Penyakit Keluarga | Riwayat Penyakit Dahulu -->
                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Riwayat Penyakit Keluarga</label>
                            <textarea name="rpk" rows="2"
                                      placeholder="Riwayat penyakit dalam keluarga..."><?php echo htmlspecialchars($data['rpk']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Dahulu</label>
                            <textarea name="rpd" rows="2"
                                      placeholder="Riwayat penyakit yang pernah diderita..."><?php echo htmlspecialchars($data['rpd']); ?></textarea>
                        </div>
                    </div>

                    <!-- Baris 4: Riwayat Penggunaan Obat | Riwayat Alergi -->
                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Riwayat Penggunaan Obat</label>
                            <textarea name="rpo" rows="2"
                                      placeholder="Obat yang sedang/pernah dikonsumsi..."><?php echo htmlspecialchars($data['rpo']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Alergi</label>
                            <input type="text" name="alergi"
                                   value="<?php echo htmlspecialchars($data['alergi']); ?>"
                                   placeholder="Contoh: Alergi penisilin, makanan laut, dll."
                                   style="height:auto; padding:7px 10px;">
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- II. PEMERIKSAAN FISIK                                        -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">accessibility</i>
                        <h2>II. PEMERIKSAAN FISIK</h2>
                    </div>

                    <!-- Baris 1 vital: TD | BB | TB | Suhu | Nadi | RR -->
                    <div class="vital-grid">
                        <div class="vital-item">
                            <label>TD (mmHg)</label>
                            <input type="text" name="td" value="<?php echo htmlspecialchars($data['td']); ?>" placeholder="mmHg">
                        </div>
                        <div class="vital-item">
                            <label>BB (Kg)</label>
                            <input type="text" name="bb" value="<?php echo htmlspecialchars($data['bb']); ?>" placeholder="Kg">
                        </div>
                        <div class="vital-item">
                            <label>TB (Cm)</label>
                            <input type="text" name="tb" value="<?php echo htmlspecialchars($data['tb']); ?>" placeholder="Cm">
                        </div>
                        <div class="vital-item">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu" value="<?php echo htmlspecialchars($data['suhu']); ?>" placeholder="°C">
                        </div>
                        <div class="vital-item">
                            <label>Nadi (x/menit)</label>
                            <input type="text" name="nadi" value="<?php echo htmlspecialchars($data['nadi']); ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>RR (x/menit)</label>
                            <input type="text" name="rr" value="<?php echo htmlspecialchars($data['rr']); ?>" placeholder="x/menit">
                        </div>
                    </div>

                    <!-- Baris 2: Keadaan Umum | Nyeri | Status Nutrisi -->
                    <div class="form-grid cols-3" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Keadaan Umum</label>
                            <select name="keadaan_umum">
                                <?php foreach ($enumKeadaanUmum as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['keadaan_umum']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nyeri</label>
                            <input type="text" name="nyeri"
                                   value="<?php echo htmlspecialchars($data['nyeri']); ?>"
                                   placeholder="Skala / lokasi nyeri...">
                        </div>
                        <div class="form-group">
                            <label>Status Nutrisi</label>
                            <input type="text" name="status_nutrisi"
                                   value="<?php echo htmlspecialchars($data['status_nutrisi']); ?>"
                                   placeholder="Status nutrisi pasien...">
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- III. STATUS KELAINAN                                         -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">person_search</i>
                        <h2>III. STATUS KELAINAN</h2>
                    </div>

                    <div style="display:flex; gap:16px; align-items:stretch;">

                        <!-- Kolom kiri: 3 organ -->
                        <div style="flex:1; display:flex; flex-direction:column; gap:0;">
                            <?php
                            renderOrganRowJantung('jantung',    'keterangan_jantung',    'Jantung',    'favorite',   $data, $enumOrgan, $statusColor);
                            renderOrganRowJantung('paru',       'keterangan_paru',       'Paru',       'air',        $data, $enumOrgan, $statusColor);
                            renderOrganRowJantung('ekstrimitas','keterangan_ekstrimitas','Ekstremitas','pan_tool',   $data, $enumOrgan, $statusColor);
                            ?>
                        </div>

                        <!-- Kolom kanan: Lainnya -->
                        <div style="width:280px; flex-shrink:0; display:flex; flex-direction:column;">
                            <div style="background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:6px 12px; border-radius:6px 6px 0 0; display:flex; align-items:center; gap:5px;">
                                <i class="material-icons" style="font-size:14px; opacity:0.85;">more_horiz</i>
                                <span style="font-size:11px; font-weight:700; letter-spacing:0.3px; text-transform:uppercase;">Lainnya</span>
                            </div>
                            <textarea name="lainnya"
                                      style="flex:1; resize:none; border:1px solid #e2e8f0; border-top:none;
                                             border-radius:0 0 6px 6px; padding:10px 12px; font-size:12px;
                                             font-family:inherit; line-height:1.5; color:#374151;
                                             background:white; outline:none; min-height:110px;"
                                      placeholder="Temuan pemeriksaan fisik lainnya..."
                                      onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 2px rgba(102,126,234,0.15)'"
                                      onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'"
                            ><?php echo htmlspecialchars($data['lainnya']); ?></textarea>
                        </div>

                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- IV. PEMERIKSAAN PENUNJANG                                    -->
                <!-- ============================================================ -->
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
                            <label>EKG</label>
                            <textarea name="ekg" rows="4"
                                      placeholder="Hasil pemeriksaan EKG..."><?php echo htmlspecialchars($data['ekg']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Penunjang Lainnya</label>
                            <textarea name="penunjang_lain" rows="4"
                                      placeholder="Hasil pemeriksaan penunjang lainnya..."><?php echo htmlspecialchars($data['penunjang_lain']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- V. DIAGNOSIS / ASESMEN                                       -->
                <!-- ============================================================ -->
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
                            <textarea name="diagnosis2" rows="3"
                                      placeholder="Tuliskan asesmen banding..."><?php echo htmlspecialchars($data['diagnosis2']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- VI. PERMASALAHAN & TATALAKSANA                               -->
                <!-- ============================================================ -->
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
                </div>

                <!-- ============================================================ -->
                <!-- VII. EDUKASI                                                  -->
                <!-- ============================================================ -->
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

            </form>
        </div>

        <!-- ===== ACTION BAR ===== -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisJantung()">
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
            <button type="button" id="btn-delete-jantung" class="btn btn-danger" onclick="confirmDeleteJantung()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif ($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>

            <button type="submit" id="btn-save-jantung" form="formPenilaianMedisJantung" class="btn btn-primary">
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

<script src="<?php echo BASE_URL; ?>/js/awalmedisjantung.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>