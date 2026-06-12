<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
define('BASE_URL', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';

$no_rawat     = '';
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

// Cek data existing
$queryCheck = bukaquery("SELECT pmr.*, d.nm_dokter as nama_dokter_pengisi
                         FROM penilaian_medis_ralan_bedah_mulut pmr
                         LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
                         WHERE pmr.no_rawat = '$no_rawat'");
$rsCheck             = mysqli_fetch_array($queryCheck);
$isEdit              = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login     = '';
if (!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Data default sesuai tabel penilaian_medis_ralan_bedah_mulut
$data = array(
    'tanggal'              => date('Y-m-d H:i:s'),
    'kd_dokter'            => $kd_dokter_login,
    'anamnesis'            => 'Autoanamnesis',
    'hubungan'             => '',
    'keluhan_utama'        => '',
    'rps'                  => '',
    'rpk'                  => '',
    'alergi'               => '',
    'keadaan'              => 'Baik',
    'kesadaran'            => 'Compos Mentis',
    'nyeri'                => 'Tidak Nyeri',
    'td'                   => '',
    'nadi'                 => '',
    'suhu'                 => '',
    'rr'                   => '',
    'bb'                   => '',
    'tb'                   => '',
    'status_nutrisi'       => '',
    'kulit'                => 'Tidak',
    'keterangan_kulit'     => '',
    'kepala'               => 'Tidak',
    'keterangan_kepala'    => '',
    'mata'                 => 'Tidak',
    'keterangan_mata'      => '',
    'leher'                => 'Tidak',
    'keterangan_leher'     => '',
    'kelenjar'             => 'Tidak',
    'keterangan_kelenjar'  => '',
    'dada'                 => 'Tidak',
    'keterangan_dada'      => '',
    'perut'                => 'Tidak',
    'keterangan_perut'     => '',
    'ekstremitas'          => 'Tidak',
    'keterangan_ekstremitas' => '',
    'wajah'                => '',
    'intra'                => '',
    'gigigeligi'           => '',
    'lab'                  => '',
    'rad'                  => '',
    'penunjang'            => '',
    'diagnosis'            => '',
    'diagnosis2'           => '',
    'permasalahan'         => '',
    'terapi'               => '',
    'tindakan'             => '',
    'edukasi'              => ''
);

if ($isEdit) {
    $data = array_merge($data, $rsCheck);
}

// Helper untuk option selected
function sel($val, $check) { return ($val == $check) ? 'selected' : ''; }
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">

    <!-- =========== PATIENT HEADER =========== -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">medical_services</i>
                PENILAIAN AWAL MEDIS RAWAT JALAN BEDAH MULUT
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
                        <span id="progress-text-bedmul" style="font-weight:bold;font-size:14px;color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px;height:8px;background:#e9ecef;border-radius:4px;overflow:hidden;">
                        <div id="progress-bar-bedmul" style="width:0%;height:100%;transition:width 0.3s ease,background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-bedmul" style="font-size:10px;color:#6c757d;white-space:nowrap;">(0/0)</span>
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
                <strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?>
                (<?php echo $rsPasien['umur']; ?>)
            </div>
            <?php if ($isEdit && !empty($nama_dokter_pengisi)): ?>
            <div class="info-item">
                <i class="material-icons">how_to_reg</i>
                <strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?>
            </div>
            <?php endif; ?>
        </div>
    </div><!-- /patient-header -->

    <!-- =========== FORM CARD =========== -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPenilaianMedisBedMul" method="post" action="">
                <input type="hidden" name="no_rawat"  value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <!-- ===== I. RIWAYAT KESEHATAN ===== -->
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
                            <select name="anamnesis">
                                <option value="Autoanamnesis" <?php echo sel($data['anamnesis'], 'Autoanamnesis'); ?>>Autoanamnesis</option>
                                <option value="Alloanamnesis" <?php echo sel($data['anamnesis'], 'Alloanamnesis'); ?>>Alloanamnesis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Hubungan dengan Pasien</label>
                            <input type="text" name="hubungan" value="<?php echo htmlspecialchars($data['hubungan']); ?>"
                                   placeholder="Isi jika Alloanamnesis">
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label class="required">Keluhan Utama</label>
                            <textarea name="keluhan_utama" rows="3"
                                      placeholder="Tuliskan keluhan utama pasien..."><?php echo htmlspecialchars($data['keluhan_utama']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Penyakit Sekarang</label>
                            <textarea name="rps" rows="3"
                                      placeholder="Riwayat perjalanan penyakit sekarang..."><?php echo htmlspecialchars($data['rps']); ?></textarea>
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Riwayat Penyakit Keluarga</label>
                            <textarea name="rpk" rows="3"
                                      placeholder="Riwayat penyakit dalam keluarga..."><?php echo htmlspecialchars($data['rpk']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Riwayat Alergi</label>
                            <input type="text" name="alergi" value="<?php echo htmlspecialchars($data['alergi']); ?>"
                                   placeholder="Alergi obat, makanan, dll...">
                        </div>
                    </div>
                </div><!-- /section I -->

                <!-- ===== II. PEMERIKSAAN FISIK ===== -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>II. PEMERIKSAAN FISIK</h2>
                    </div>

                    <!-- Keadaan Umum, Kesadaran, Skala Nyeri, Status Nutrisi -->
                    <div class="form-grid cols-4">
                        <div class="form-group">
                            <label>Keadaan Umum</label>
                            <select name="keadaan">
                                <option value="Baik"   <?php echo sel($data['keadaan'], 'Baik'); ?>>Baik</option>
                                <option value="Sedang" <?php echo sel($data['keadaan'], 'Sedang'); ?>>Sedang</option>
                                <option value="Lemah"  <?php echo sel($data['keadaan'], 'Lemah'); ?>>Lemah</option>
                                <option value="Buruk"  <?php echo sel($data['keadaan'], 'Buruk'); ?>>Buruk</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kesadaran</label>
                            <select name="kesadaran">
                                <option value="Compos Mentis" <?php echo sel($data['kesadaran'], 'Compos Mentis'); ?>>Compos Mentis</option>
                                <option value="Apatis"        <?php echo sel($data['kesadaran'], 'Apatis'); ?>>Apatis</option>
                                <option value="Somnolen"      <?php echo sel($data['kesadaran'], 'Somnolen'); ?>>Somnolen</option>
                                <option value="Sopor"         <?php echo sel($data['kesadaran'], 'Sopor'); ?>>Sopor</option>
                                <option value="Koma"          <?php echo sel($data['kesadaran'], 'Koma'); ?>>Koma</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Skala Nyeri</label>
                            <select name="nyeri">
                                <option value="Tidak Nyeri"   <?php echo sel($data['nyeri'], 'Tidak Nyeri'); ?>>Tidak Nyeri</option>
                                <option value="Nyeri Ringan"  <?php echo sel($data['nyeri'], 'Nyeri Ringan'); ?>>Nyeri Ringan</option>
                                <option value="Nyeri Sedang"  <?php echo sel($data['nyeri'], 'Nyeri Sedang'); ?>>Nyeri Sedang</option>
                                <option value="Nyeri Berat"   <?php echo sel($data['nyeri'], 'Nyeri Berat'); ?>>Nyeri Berat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status Nutrisi</label>
                            <input type="text" name="status_nutrisi" value="<?php echo htmlspecialchars($data['status_nutrisi']); ?>" placeholder="Baik / Kurang / Buruk...">
                        </div>
                    </div>

                    <!-- Tanda Vital -->
                    <div class="vital-grid" style="margin-top:10px;">
                        <div class="vital-item">
                            <label>TD (mmHg)</label>
                            <input type="text" name="td" value="<?php echo htmlspecialchars($data['td']); ?>" placeholder="120/80">
                        </div>
                        <div class="vital-item">
                            <label>Nadi (x/menit)</label>
                            <input type="text" name="nadi" value="<?php echo htmlspecialchars($data['nadi']); ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu" value="<?php echo htmlspecialchars($data['suhu']); ?>" placeholder="°C">
                        </div>
                        <div class="vital-item">
                            <label>RR (x/menit)</label>
                            <input type="text" name="rr" value="<?php echo htmlspecialchars($data['rr']); ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>BB (Kg)</label>
                            <input type="text" name="bb" value="<?php echo htmlspecialchars($data['bb']); ?>" placeholder="Kg">
                        </div>
                        <div class="vital-item">
                            <label>TB (Cm)</label>
                            <input type="text" name="tb" value="<?php echo htmlspecialchars($data['tb']); ?>" placeholder="Cm">
                        </div>
                    </div>

                    <!-- III. STATUS KELAINAN -->
                    <div class="section-subtitle" style="margin-top:15px;">III. STATUS KELAINAN</div>
                    <?php
                    $statusYaTidak = [
                        'Ya'    => ['bg'=>'#fee2e2','color'=>'#991b1b'],
                        'Tidak' => ['bg'=>'#f1f5f9','color'=>'#475569'],
                    ];
                    $kelainanKiri = [
                        ['key'=>'kulit',    'ket'=>'keterangan_kulit',    'label'=>'Kulit',         'icon'=>'texture'],
                        ['key'=>'kepala',   'ket'=>'keterangan_kepala',   'label'=>'Kepala',        'icon'=>'face'],
                        ['key'=>'mata',     'ket'=>'keterangan_mata',     'label'=>'Mata',          'icon'=>'visibility'],
                        ['key'=>'leher',    'ket'=>'keterangan_leher',    'label'=>'Leher',         'icon'=>'airline_seat_recline_normal'],
                    ];
                    $kelainanKanan = [
                        ['key'=>'kelenjar', 'ket'=>'keterangan_kelenjar', 'label'=>'Kelenjar Limfe','icon'=>'bubble_chart'],
                        ['key'=>'dada',     'ket'=>'keterangan_dada',     'label'=>'Dada',          'icon'=>'favorite'],
                        ['key'=>'perut',    'ket'=>'keterangan_perut',    'label'=>'Perut',         'icon'=>'airline_seat_flat'],
                        ['key'=>'ekstremitas','ket'=>'keterangan_ekstremitas','label'=>'Ekstremitas','icon'=>'pan_tool'],
                    ];
                    ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">

                        <!-- Kolom Kiri -->
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <?php foreach ($kelainanKiri as $item):
                                $val = $data[$item['key']];
                                $sc  = isset($statusYaTidak[$val]) ? $statusYaTidak[$val] : $statusYaTidak['Tidak'];
                            ?>
                            <div style="display:flex;align-items:center;gap:0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;transition:box-shadow 0.2s;"
                                 onmouseover="this.style.boxShadow='0 2px 8px rgba(102,126,234,0.12)'"
                                 onmouseout="this.style.boxShadow='none'">
                                <!-- Label -->
                                <div style="display:flex;align-items:center;gap:5px;padding:0 10px;min-width:130px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;align-self:stretch;justify-content:flex-start;">
                                    <i class="material-icons" style="font-size:13px;opacity:0.85;"><?php echo $item['icon']; ?></i>
                                    <span style="font-size:11px;font-weight:700;letter-spacing:0.3px;text-transform:uppercase;"><?php echo $item['label']; ?></span>
                                </div>
                                <!-- Select -->
                                <div style="position:relative;flex-shrink:0;">
                                    <select name="<?php echo $item['key']; ?>"
                                            style="appearance:none;-webkit-appearance:none;
                                                   padding:8px 28px 8px 10px;border:none;border-right:1px solid #e2e8f0;
                                                   font-size:12px;font-weight:600;cursor:pointer;outline:none;
                                                   background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;min-width:80px;"
                                            onchange="
                                                var c={'Ya':{'bg':'#fee2e2','color':'#991b1b'},'Tidak':{'bg':'#f1f5f9','color':'#475569'}};
                                                var s=c[this.value]||c['Tidak'];
                                                this.style.background=s.bg;this.style.color=s.color;
                                                this.nextElementSibling.style.color=s.color;">
                                        <option value="Tidak" <?php echo sel($val,'Tidak'); ?>>Tidak</option>
                                        <option value="Ya"    <?php echo sel($val,'Ya'); ?>>Ya</option>
                                    </select>
                                    <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);pointer-events:none;color:<?php echo $sc['color']; ?>;font-size:12px;">▾</span>
                                </div>
                                <!-- Keterangan -->
                                <input type="text" name="<?php echo $item['ket']; ?>"
                                       value="<?php echo htmlspecialchars($data[$item['ket']]); ?>"
                                       placeholder="Keterangan..."
                                       style="flex:1;border:none;padding:8px 10px;font-size:12px;background:white;outline:none;min-width:0;color:#374151;"
                                       onfocus="this.closest('div[style]').style.boxShadow='0 0 0 2px rgba(102,126,234,0.25)'"
                                       onblur="this.closest('div[style]').style.boxShadow='none'">
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Kolom Kanan -->
                        <div style="display:flex;flex-direction:column;gap:6px;">
                            <?php foreach ($kelainanKanan as $item):
                                $val = $data[$item['key']];
                                $sc  = isset($statusYaTidak[$val]) ? $statusYaTidak[$val] : $statusYaTidak['Tidak'];
                            ?>
                            <div style="display:flex;align-items:center;gap:0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;transition:box-shadow 0.2s;"
                                 onmouseover="this.style.boxShadow='0 2px 8px rgba(102,126,234,0.12)'"
                                 onmouseout="this.style.boxShadow='none'">
                                <!-- Label -->
                                <div style="display:flex;align-items:center;gap:5px;padding:0 10px;min-width:130px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;align-self:stretch;justify-content:flex-start;">
                                    <i class="material-icons" style="font-size:13px;opacity:0.85;"><?php echo $item['icon']; ?></i>
                                    <span style="font-size:11px;font-weight:700;letter-spacing:0.3px;text-transform:uppercase;"><?php echo $item['label']; ?></span>
                                </div>
                                <!-- Select -->
                                <div style="position:relative;flex-shrink:0;">
                                    <select name="<?php echo $item['key']; ?>"
                                            style="appearance:none;-webkit-appearance:none;
                                                   padding:8px 28px 8px 10px;border:none;border-right:1px solid #e2e8f0;
                                                   font-size:12px;font-weight:600;cursor:pointer;outline:none;
                                                   background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;min-width:80px;"
                                            onchange="
                                                var c={'Ya':{'bg':'#fee2e2','color':'#991b1b'},'Tidak':{'bg':'#f1f5f9','color':'#475569'}};
                                                var s=c[this.value]||c['Tidak'];
                                                this.style.background=s.bg;this.style.color=s.color;
                                                this.nextElementSibling.style.color=s.color;">
                                        <option value="Tidak" <?php echo sel($val,'Tidak'); ?>>Tidak</option>
                                        <option value="Ya"    <?php echo sel($val,'Ya'); ?>>Ya</option>
                                    </select>
                                    <span style="position:absolute;right:8px;top:50%;transform:translateY(-50%);pointer-events:none;color:<?php echo $sc['color']; ?>;font-size:12px;">▾</span>
                                </div>
                                <!-- Keterangan -->
                                <input type="text" name="<?php echo $item['ket']; ?>"
                                       value="<?php echo htmlspecialchars($data[$item['ket']]); ?>"
                                       placeholder="Keterangan..."
                                       style="flex:1;border:none;padding:8px 10px;font-size:12px;background:white;outline:none;min-width:0;color:#374151;"
                                       onfocus="this.closest('div[style]').style.boxShadow='0 0 0 2px rgba(102,126,234,0.25)'"
                                       onblur="this.closest('div[style]').style.boxShadow='none'">
                            </div>
                            <?php endforeach; ?>
                        </div>

                    </div><!-- /status kelainan grid -->
                </div><!-- /section II + III -->

                <!-- ===== IV. STATUS LOKALISATA ===== -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">face</i>
                        <h2>IV. STATUS LOKALISATA</h2>
                    </div>

                    <!-- Wajah -->
                    <p class="section-subtitle">Wajah :</p>
                    <div class="lokalis-wrapper">
                        <div class="form-group" style="flex:1;">
                            <textarea name="wajah" rows="10"
                                      placeholder="Deskripsikan temuan pemeriksaan wajah sesuai gambar (R/L)..."><?php echo htmlspecialchars($data['wajah']); ?></textarea>
                        </div>
                        <div class="lokalis-image">
                            <div style="display:flex;gap:4px;justify-content:center;align-items:flex-end;width:100%;height:320px;">
                                <img src="<?php echo APP_BASE_URL; ?>/images/wajah1.png" alt="Wajah Kanan"
                                     style="flex:1;min-width:0;height:100%;object-fit:contain;object-position:bottom;">
                                <img src="<?php echo APP_BASE_URL; ?>/images/wajah2.png" alt="Wajah Kiri"
                                     style="flex:1;min-width:0;height:100%;object-fit:contain;object-position:bottom;">
                            </div>
                            <p>Ilustrasi Wajah (R &amp; L)</p>
                        </div>
                    </div>

                    <!-- Intra Oral -->
                    <p class="section-subtitle" style="margin-top:15px;">Intra Oral :</p>
                    <div class="lokalis-wrapper">
                        <div class="form-group" style="flex:1;">
                            <textarea name="intra" rows="10"
                                      placeholder="Deskripsikan temuan intra oral (mukosa, palatum, lidah, vestibulum, dll)..."><?php echo htmlspecialchars($data['intra']); ?></textarea>
                        </div>
                        <div class="lokalis-image">
                            <img src="<?php echo APP_BASE_URL; ?>/images/intraoral.png" alt="Intra Oral"
                                 style="width:100%;max-height:350px;height:auto;object-fit:contain;">
                            <p>Ilustrasi Intra Oral</p>
                        </div>
                    </div>

                    <!-- Gigi Geligi -->
                    <p class="section-subtitle" style="margin-top:15px;">Gigi Geligi :</p>
                    <div class="lokalis-wrapper">
                        <div class="form-group" style="flex:1;">
                            <textarea name="gigigeligi" rows="14"
                                      placeholder="Deskripsikan kondisi per-gigi (karies, fraktur, missing, mobiliti, dll)..."><?php echo htmlspecialchars($data['gigigeligi']); ?></textarea>
                        </div>
                        <div class="lokalis-image">
                            <img src="<?php echo APP_BASE_URL; ?>/images/gigigeligi.png" alt="Odontogram"
                                 style="width:100%;max-height:350px;height:auto;object-fit:contain;">
                            <p>Odontogram</p>
                        </div>
                    </div>
                </div><!-- /section IV -->

                <!-- ===== V. PEMERIKSAAN PENUNJANG ===== -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>V. PEMERIKSAAN PENUNJANG</h2>
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
                                      placeholder="Hasil pemeriksaan radiologi (foto panoramik, periapikal, dll)..."><?php echo htmlspecialchars($data['rad']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Penunjang Lainnya</label>
                            <textarea name="penunjang" rows="4"
                                      placeholder="Pemeriksaan penunjang lain..."><?php echo htmlspecialchars($data['penunjang']); ?></textarea>
                        </div>
                    </div>
                </div><!-- /section V -->

                <!-- ===== VI. DIAGNOSIS / ASESMEN ===== -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">local_hospital</i>
                        <h2>VI. DIAGNOSIS / ASESMEN</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label class="required">Asesmen Kerja</label>
                            <textarea name="diagnosis" rows="4" required
                                      placeholder="Tuliskan diagnosis/asesmen kerja..."><?php echo htmlspecialchars($data['diagnosis']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Asesmen Banding</label>
                            <textarea name="diagnosis2" rows="4"
                                      placeholder="Tuliskan asesmen banding..."><?php echo htmlspecialchars($data['diagnosis2']); ?></textarea>
                        </div>
                    </div>
                </div><!-- /section VI -->

                <!-- ===== VII. PERMASALAHAN & TATALAKSANA ===== -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>VII. PERMASALAHAN &amp; TATALAKSANA</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Permasalahan</label>
                            <textarea name="permasalahan" rows="4"
                                      placeholder="Tuliskan permasalahan klinis..."><?php echo htmlspecialchars($data['permasalahan']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Terapi / Pengobatan</label>
                            <textarea name="terapi" rows="4"
                                      placeholder="Tuliskan terapi/pengobatan..."><?php echo htmlspecialchars($data['terapi']); ?></textarea>
                        </div>
                    </div>
                    <div class="form-grid cols-1" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Tindakan / Rencana Tindakan</label>
                            <textarea name="tindakan" rows="3"
                                      placeholder="Tuliskan tindakan atau rencana tindakan..."><?php echo htmlspecialchars($data['tindakan']); ?></textarea>
                        </div>
                    </div>
                </div><!-- /section VII -->

                <!-- ===== VIII. EDUKASI ===== -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">school</i>
                        <h2>VIII. EDUKASI</h2>
                    </div>
                    <div class="form-group">
                        <label>Edukasi</label>
                        <textarea name="edukasi" rows="4"
                                  placeholder="Tuliskan edukasi yang diberikan kepada pasien/keluarga..."><?php echo htmlspecialchars($data['edukasi']); ?></textarea>
                    </div>
                </div><!-- /section VIII -->

            </form>
        </div><!-- /form-content -->

        <!-- =========== ACTION BAR =========== -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisBedMul()">
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
            <button type="button" id="btn-delete-bedmul" class="btn btn-danger" onclick="confirmDeleteBedMul()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif ($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>

            <button type="submit" id="btn-save-bedmul" form="formPenilaianMedisBedMul" class="btn btn-primary">
                <i class="material-icons">save</i>
                SIMPAN DATA
            </button>
        </div><!-- /action-bar -->

        <?php if ($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>.
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>

    </div><!-- /form-card -->
</div><!-- /modern-form-container -->

<script src="<?php echo BASE_URL; ?>/js/awalmedisbedahmulut.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>