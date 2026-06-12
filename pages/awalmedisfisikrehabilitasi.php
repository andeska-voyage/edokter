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
                         FROM penilaian_medis_ralan_rehab_medik pmr
                         LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
                         WHERE pmr.no_rawat = '$no_rawat'");
$rsCheck             = mysqli_fetch_array($queryCheck);
$isEdit              = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login     = '';
if (!empty($kd_dokter_encrypted)) $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');

// Data default sesuai tabel penilaian_medis_ralan_rehab_medik
$data = array(
    'tanggal'              => date('Y-m-d H:i:s'),
    'kd_dokter'            => $kd_dokter_login,
    'anamnesis'            => 'Autoanamnesis',
    'hubungan'             => '',
    'keluhan_utama'        => '',
    'rps'                  => '',
    'rpd'                  => '',
    'alergi'               => '',
    'kesadaran'            => 'Compos Mentis',
    'nyeri'                => 'Tidak Nyeri',
    'skala_nyeri'          => '0',
    'td'                   => '',
    'nadi'                 => '',
    'suhu'                 => '',
    'rr'                   => '',
    'bb'                   => '',
    'kepala'               => 'Tidak Diperiksa',
    'keterangan_kepala'    => '',
    'thoraks'              => 'Tidak Diperiksa',
    'keterangan_thoraks'   => '',
    'abdomen'              => 'Tidak Diperiksa',
    'keterangan_abdomen'   => '',
    'ekstremitas'          => 'Tidak Diperiksa',
    'keterangan_ekstremitas'=> '',
    'columna'              => 'Tidak Diperiksa',
    'keterangan_columna'   => '',
    'muskulos'             => 'Tidak Diperiksa',
    'keterangan_muskulos'  => '',
    'lainnya'              => '',
    'resiko_jatuh'         => 'Tidak Berisiko',
    'resiko_nutrisional'   => 'Tidak Berisiko Malnutrisi',
    'kebutuhan_fungsional' => 'Tidak Perlu Bantuan',
    'diagnosa_medis'       => '',
    'diagnosa_fungsi'      => '',
    'penunjang_lain'       => '',
    'fisio'                => '',
    'fisioterapi'          => '',
    'okupasi'              => '',
    'terapi_okupasi'       => '',
    'wicara'               => '',
    'terapi_wicara'        => '',
    'akupuntur'            => '',
    'terapi_akupuntur'     => '',
    'tatalain'             => '',
    'terapi_lainnya'       => '',
    'frekuensi_terapi'     => '',
    'edukasi'              => ''
);

if ($isEdit) $data = array_merge($data, $rsCheck);

// Enum options
$enumNormal     = ['Normal', 'Abnormal', 'Tidak Diperiksa'];
$enumKesadaran  = ['Compos Mentis', 'Apatis', 'Delirium'];
$enumNyeri      = ['Tidak Nyeri', 'Nyeri Ringan', 'Nyeri Sedang', 'Nyeri Sangat Sedang', 'Nyeri Berat'];
$enumSkala      = ['0','1','2','3','4','5','6','7','8','9','10'];
$enumRisikoJatuh = ['Tidak Berisiko', 'Berisiko Sedang', 'Berisiko Tinggi'];
$enumNutrisi    = ['Tidak Berisiko Malnutrisi', 'Berisiko Malnutrisi', 'Malnutrisi'];
$enumFungsional = ['Tidak Perlu Bantuan', 'Perlu Bantuan', 'Perlu Bantuan Penuh'];

// Helper: tanggal untuk input date
function fmtDate($val) {
    if (empty($val) || $val == '0000-00-00') return '';
    return date('Y-m-d', strtotime($val));
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<div class="modern-form-container">

    <!-- ===== PATIENT HEADER ===== -->
    <div class="patient-header">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <h1 style="margin:0; display:flex; align-items:center; gap:10px;">
                <i class="material-icons">assignment</i>
                PENILAIAN AWAL MEDIS RAWAT JALAN FISIK &amp; REHABILITASI
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
                        <span id="progress-text-fisrehab" style="font-weight:bold; font-size:14px; color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px; height:8px; background:#e9ecef; border-radius:4px; overflow:hidden;">
                        <div id="progress-bar-fisrehab" style="width:0%; height:100%; transition:width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-fisrehab" style="font-size:10px; color:#6c757d; white-space:nowrap;">(0/0)</span>
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
            <form id="formPenilaianMedisFisRehab" method="post" action="">
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
                                   value="<?php echo htmlspecialchars($data['hubungan']); ?>" placeholder="Contoh: Ibu, Ayah">
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

                    <div class="form-grid cols-2" style="margin-top:10px;">
                        <div class="form-group">
                            <label>Riwayat Penyakit Dahulu</label>
                            <textarea name="rpd" rows="2"
                                      placeholder="Riwayat penyakit yang pernah diderita..."><?php echo htmlspecialchars($data['rpd']); ?></textarea>
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

                    <!-- Skala Nyeri + Kesadaran -->
                    <div style="display:flex; gap:16px; align-items:flex-start; margin-bottom:12px;">

                        <!-- Gambar skala nyeri -->
                        <div style="flex-shrink:0;">
                            <img src="<?php echo APP_BASE_URL; ?>/images/skalanyeri.png"
                                 alt="Wong Baker Faces Pain Rating Scale"
                                 style="max-width:320px; width:100%; display:block; border-radius:6px; border:1px solid #e2e8f0;">
                            <p style="font-size:10px; color:#6c757d; text-align:center; margin:4px 0 0;">Wong Baker Faces Pain Rating Scale</p>
                        </div>

                        <!-- Pilihan nyeri + kesadaran -->
                        <div style="flex:1; display:flex; flex-direction:column; gap:10px;">
                            <div class="form-grid cols-3">
                                <div class="form-group">
                                    <label>Nyeri</label>
                                    <select name="nyeri">
                                        <?php foreach ($enumNyeri as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($data['nyeri']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Skala Nyeri</label>
                                    <select name="skala_nyeri">
                                        <?php foreach ($enumSkala as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($data['skala_nyeri']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Kesadaran</label>
                                    <select name="kesadaran">
                                        <?php foreach ($enumKesadaran as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($data['kesadaran']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Tanda-Tanda Vital -->
                            <div class="section-subtitle">Tanda-Tanda Vital</div>
                            <div class="vital-grid">
                                <div class="vital-item">
                                    <label>Nadi (x/menit)</label>
                                    <input type="text" name="nadi" value="<?php echo htmlspecialchars($data['nadi']); ?>" placeholder="x/menit">
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
                                    <label>Suhu (°C)</label>
                                    <input type="text" name="suhu" value="<?php echo htmlspecialchars($data['suhu']); ?>" placeholder="°C">
                                </div>
                                <div class="vital-item">
                                    <label>RR (x/menit)</label>
                                    <input type="text" name="rr" value="<?php echo htmlspecialchars($data['rr']); ?>" placeholder="x/menit">
                                </div>
                            </div>
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

                    <?php
                    $statusColor = [
                        'Normal'          => ['bg'=>'#dcfce7','color'=>'#166534'],
                        'Abnormal'        => ['bg'=>'#fee2e2','color'=>'#991b1b'],
                        'Tidak Diperiksa' => ['bg'=>'#f1f5f9','color'=>'#475569'],
                    ];
                    $organKiri = [
                        ['kepala',      'keterangan_kepala',      'Kepala',      'face'],
                        ['thoraks',     'keterangan_thoraks',     'Thoraks',     'favorite'],
                        ['abdomen',     'keterangan_abdomen',     'Abdomen',     'airline_seat_flat'],
                    ];
                    $organKanan = [
                        ['ekstremitas', 'keterangan_ekstremitas', 'Ekstremitas',        'pan_tool'],
                        ['columna',     'keterangan_columna',     'Columna Vertebralis','straighten'],
                        ['muskulos',    'keterangan_muskulos',    'Muskuloskeletal',    'fitness_center'],
                    ];

                    function renderOrganRow($sk, $data, $enumNormal, $statusColor) {
                        $cv = isset($data[$sk[0]]) ? $data[$sk[0]] : 'Tidak Diperiksa';
                        $sc = isset($statusColor[$cv]) ? $statusColor[$cv] : $statusColor['Tidak Diperiksa'];
                        echo '<div style="display:flex; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; overflow:hidden; margin-bottom:6px;"
                                   onmouseover="this.style.boxShadow=\'0 2px 8px rgba(102,126,234,0.12)\'"
                                   onmouseout="this.style.boxShadow=\'none\'">';
                        echo '<div style="display:flex; align-items:center; gap:5px; padding:0 10px; min-width:145px; background:linear-gradient(135deg,#667eea,#764ba2); color:white; align-self:stretch;">';
                        echo '<i class="material-icons" style="font-size:13px; opacity:0.85;">' . $sk[3] . '</i>';
                        echo '<span style="font-size:11px; font-weight:700; letter-spacing:0.3px; text-transform:uppercase;">' . $sk[2] . '</span>';
                        echo '</div>';
                        echo '<div style="position:relative; flex-shrink:0;">';
                        echo '<select name="' . $sk[0] . '"
                                style="appearance:none; -webkit-appearance:none;
                                       padding:9px 28px 9px 10px; border:none; border-right:1px solid #e2e8f0;
                                       font-size:12px; font-weight:600; cursor:pointer; outline:none;
                                       background:' . $sc['bg'] . '; color:' . $sc['color'] . '; min-width:140px;"
                                onchange="
                                    var c={\'Normal\':{\'bg\':\'#dcfce7\',\'color\':\'#166534\'},\'Abnormal\':{\'bg\':\'#fee2e2\',\'color\':\'#991b1b\'},\'Tidak Diperiksa\':{\'bg\':\'#f1f5f9\',\'color\':\'#475569\'}};
                                    var s=c[this.value]||c[\'Tidak Diperiksa\'];
                                    this.style.background=s.bg; this.style.color=s.color;
                                    this.nextElementSibling.style.color=s.color;
                                ">';
                        foreach ($enumNormal as $opt) {
                            echo '<option value="' . $opt . '"' . ($cv==$opt?' selected':'') . '>' . $opt . '</option>';
                        }
                        echo '</select>';
                        echo '<span style="position:absolute; right:7px; top:50%; transform:translateY(-50%); pointer-events:none; color:' . $sc['color'] . '; font-size:12px;">▾</span>';
                        echo '</div>';
                        $ket = isset($data[$sk[1]]) ? htmlspecialchars($data[$sk[1]]) : '';
                        echo '<input type="text" name="' . $sk[1] . '" value="' . $ket . '"
                               placeholder="Keterangan..."
                               style="flex:1; border:none; padding:9px 10px; font-size:12px; background:white; outline:none; min-width:0; color:#374151;"
                               onfocus="this.closest(\'div[style]\').style.boxShadow=\'0 0 0 2px rgba(102,126,234,0.25)\'"
                               onblur="this.closest(\'div[style]\').style.boxShadow=\'none\'">';
                        echo '</div>';
                    }
                    ?>

                    <!-- 2 Kolom organ -->
                    <div style="display:flex; gap:12px;">
                        <div style="flex:1;">
                            <?php foreach ($organKiri as $sk) renderOrganRow($sk, $data, $enumNormal, $statusColor); ?>
                        </div>
                        <div style="flex:1;">
                            <?php foreach ($organKanan as $sk) renderOrganRow($sk, $data, $enumNormal, $statusColor); ?>
                        </div>
                    </div>

                    <!-- Lainnya -->
                    <div class="form-group" style="margin-top:8px;">
                        <label>Lainnya</label>
                        <textarea name="lainnya" rows="3"
                                  placeholder="Temuan pemeriksaan fisik lainnya..."><?php echo htmlspecialchars($data['lainnya']); ?></textarea>
                    </div>

                    <!-- Risiko Jatuh | Resiko Nutrisional | Kebutuhan Fungsional -->
                    <div class="form-grid cols-3" style="margin-top:8px;">
                        <div class="form-group">
                            <label>Risiko Jatuh</label>
                            <select name="resiko_jatuh">
                                <?php foreach ($enumRisikoJatuh as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['resiko_jatuh']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Resiko Nutrisional</label>
                            <select name="resiko_nutrisional">
                                <?php foreach ($enumNutrisi as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['resiko_nutrisional']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kebutuhan Fungsional</label>
                            <select name="kebutuhan_fungsional">
                                <?php foreach ($enumFungsional as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['kebutuhan_fungsional']==$opt)?'selected':''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- IV. PEMERIKSAAN FISIK DAN UJI FUNGSI                         -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>IV. PEMERIKSAAN FISIK DAN UJI FUNGSI</h2>
                    </div>
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Diagnosa Medis</label>
                            <textarea name="diagnosa_medis" rows="4"
                                      placeholder="Tuliskan diagnosa medis..."><?php echo htmlspecialchars($data['diagnosa_medis']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Diagnosa Fungsi</label>
                            <textarea name="diagnosa_fungsi" rows="4"
                                      placeholder="Tuliskan diagnosa fungsi..."><?php echo htmlspecialchars($data['diagnosa_fungsi']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Pemeriksaan Penunjang</label>
                            <textarea name="penunjang_lain" rows="4"
                                      placeholder="Hasil pemeriksaan penunjang..."><?php echo htmlspecialchars($data['penunjang_lain']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- V. TATALAKSANA KFR                                           -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">healing</i>
                        <h2>V. TATALAKSANA KFR</h2>
                    </div>

                    <?php
                    $terapiRows = [
                        ['fisio',     'fisioterapi',     'Fisioterapi'],
                        ['okupasi',   'terapi_okupasi',  'Terapi Okupasi'],
                        ['wicara',    'terapi_wicara',   'Terapi Wicara'],
                        ['akupuntur', 'terapi_akupuntur','Terapi Akupuntur'],
                        ['tatalain',  'terapi_lainnya',  'Terapi Lainnya'],
                    ];
                    ?>

                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <?php foreach ($terapiRows as $tr):
                            $tglVal = fmtDate($data[$tr[1]]);
                            $checked = !empty($tglVal) ? 'checked' : '';
                        ?>
                        <div style="display:flex; align-items:center; gap:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px 12px;">
                            <!-- Label -->
                            <div style="min-width:130px; font-size:12px; font-weight:600; color:#374151;"><?php echo $tr[2]; ?></div>
                            <!-- Input text terapi -->
                            <input type="text" name="<?php echo $tr[0]; ?>"
                                   value="<?php echo htmlspecialchars($data[$tr[0]]); ?>"
                                   placeholder="Tuliskan rencana <?php echo strtolower($tr[2]); ?>..."
                                   style="flex:1; border:1px solid #e2e8f0; border-radius:4px; padding:7px 10px; font-size:12px; outline:none;"
                                   onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 2px rgba(102,126,234,0.1)'"
                                   onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'">
                            <!-- Checkbox aktif -->
                            <label style="display:flex; align-items:center; gap:4px; font-size:12px; color:#6c757d; cursor:pointer; white-space:nowrap;">
                                <input type="checkbox" id="chk_<?php echo $tr[1]; ?>"
                                       onchange="toggleTglTerapi('<?php echo $tr[1]; ?>', this.checked)"
                                       <?php echo $checked; ?>
                                       style="width:15px; height:15px; cursor:pointer; accent-color:#667eea;">
                            </label>
                            <!-- Input tanggal -->
                            <input type="date" name="<?php echo $tr[1]; ?>" id="tgl_<?php echo $tr[1]; ?>"
                                   value="<?php echo $tglVal; ?>"
                                   style="border:1px solid #e2e8f0; border-radius:4px; padding:7px 10px; font-size:12px; outline:none; width:150px;
                                          <?php echo empty($tglVal) ? 'opacity:0.4;' : ''; ?>"
                                   <?php echo empty($tglVal) ? 'disabled' : ''; ?>>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group" style="margin-top:10px;">
                        <label>Frekuensi Terapi</label>
                        <input type="text" name="frekuensi_terapi"
                               value="<?php echo htmlspecialchars($data['frekuensi_terapi']); ?>"
                               placeholder="Contoh: 3x seminggu, setiap hari, dll.">
                    </div>
                </div>

                <!-- ============================================================ -->
                <!-- VI. EDUKASI                                                   -->
                <!-- ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">school</i>
                        <h2>VI. EDUKASI</h2>
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
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisFisRehab()">
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
            <button type="button" id="btn-delete-fisrehab" class="btn btn-danger" onclick="confirmDeleteFisRehab()">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <?php elseif ($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i>
                HAPUS
            </button>
            <?php endif; ?>

            <button type="submit" id="btn-save-fisrehab" form="formPenilaianMedisFisRehab" class="btn btn-primary">
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
// Toggle input tanggal terapi berdasarkan checkbox
function toggleTglTerapi(fieldName, isChecked) {
    var tglInput = document.getElementById('tgl_' + fieldName);
    if (!tglInput) return;
    tglInput.disabled = !isChecked;
    tglInput.style.opacity = isChecked ? '1' : '0.4';
    if (isChecked && !tglInput.value) {
        tglInput.value = new Date().toISOString().split('T')[0];
    }
    if (!isChecked) tglInput.value = '';
}
</script>

<script src="<?php echo BASE_URL; ?>/js/awalmedisfisikrehabilitasi.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>