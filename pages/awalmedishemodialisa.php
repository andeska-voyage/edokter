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

// ── Ambil data pasien ─────────────────────────────────────────────────────────
$queryPasien = bukaquery("SELECT 
                            rp.no_rawat, rp.no_rkm_medis, rp.tgl_registrasi, rp.jam_reg,
                            p.nm_pasien, p.jk, p.tmp_lahir, p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                            d.nm_dokter, d.kd_dokter,
                            pp.nm_poli
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                        LEFT JOIN poliklinik pp ON rp.kd_poli = pp.kd_poli
                        WHERE rp.no_rawat = '$no_rawat'");
$rsPasien = mysqli_fetch_array($queryPasien);

if (!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.location.href='?act=Pasien';</script>";
    exit;
}

// ── Cek data existing dari tabel penilaian_medis_hemodialisa ─────────────────
$queryCheck = bukaquery("SELECT pmh.*, d.nm_dokter as nama_dokter_pengisi
                         FROM penilaian_medis_hemodialisa pmh
                         LEFT JOIN dokter d ON pmh.kd_dokter = d.kd_dokter
                         WHERE pmh.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// ── Kode dokter login ─────────────────────────────────────────────────────────
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login     = '';
if (!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

$today = date('Y-m-d');

// ── Data default sesuai kolom tabel penilaian_medis_hemodialisa ──────────────
$data = [
    // Header
    'tanggal'       => date('Y-m-d H:i:s'),
    'kd_dokter'     => $kd_dokter_login,
    'anamnesis'     => 'Autoanamnesis',
    'hubungan'      => '',
    'ruangan'       => isset($rsPasien['nm_poli']) ? $rsPasien['nm_poli'] : '',
    'alergi'        => '',
    'nyeri'         => 'Tidak Nyeri',
    'status_nutrisi'=> '',
    // I. Riwayat Penyakit
    'hipertensi'                    => 'Tidak',
    'keterangan_hipertensi'         => '',
    'diabetes'                      => 'Tidak',
    'keterangan_diabetes'           => '',
    'batu_saluran_kemih'            => 'Tidak',
    'keterangan_batu_saluran_kemih' => '',
    'operasi_saluran_kemih'         => 'Tidak',
    'keterangan_operasi_saluran_kemih' => '',
    'infeksi_saluran_kemih'         => 'Tidak',
    'keterangan_infeksi_saluran_kemih' => '',
    'bengkak_seluruh_tubuh'         => 'Tidak',
    'keterangan_bengkak_seluruh_tubuh' => '',
    'urin_berdarah'                 => 'Tidak',
    'keterangan_urin_berdarah'      => '',
    'penyakit_ginjal_laom'          => 'Tidak',
    'keterangan_penyakit_ginjal_laom' => '',
    'penyakit_lain'                 => 'Tidak',
    'keterangan_penyakit_lain'      => '',
    'konsumsi_obat_nefro'           => 'Tidak',
    'keterangan_konsumsi_obat_nefro'=> '',
    // II. Riwayat Dialisis / Transplantasi
    'dialisis_pertama'   => $today,
    'pernah_cpad'        => 'Tidak',
    'tanggal_cpad'       => $today,
    'pernah_transplantasi'=> 'Tidak',
    'tanggal_transplantasi' => $today,
    // III. Pemeriksaan Fisik
    'keadaan_umum'  => 'Sehat',
    'kesadaran'     => 'Compos Mentis',
    'nadi'          => '',
    'bb'            => '',
    'td'            => '',
    'suhu'          => '',
    'napas'         => '',
    'tb'            => '',
    // Abdomen
    'hepatomegali'  => 'Tidak',
    'splenomegali'  => 'Tidak',
    'ascites'       => 'Tidak',
    // Paru
    'whezzing'      => 'Tidak',
    'ronchi'        => 'Tidak',
    // Sklera
    'ikterik'       => 'Tidak',
    // Tekanan Vena Jugularis
    'tekanan_vena'  => 'Normal',
    // Konjungtiva
    'anemia'        => 'Tidak',
    // Jantung
    'kardiomegali'  => 'Tidak',
    'bising'        => 'Tidak',
    // Ekstremitas
    'edema'         => 'Tidak',
    // IV. Pemeriksaan Penunjang - checkbox + tanggal
    'thorax'            => 'Tidak', 'tanggal_thorax'        => $today,
    'ekg'               => 'Tidak', 'tanggal_ekg'           => $today,
    'bno'               => 'Tidak', 'tanggal_bno'           => $today,
    'usg'               => 'Tidak', 'tanggal_usg'           => $today,
    'renogram'          => 'Tidak', 'tanggal_renogram'      => $today,
    'biopsi'            => 'Tidak', 'tanggal_biopsi'        => $today,
    'ctscan'            => 'Tidak', 'tanggal_ctscan'        => $today,
    'arteriografi'      => 'Tidak', 'tanggal_arteriografi'  => $today,
    'kultur_urin'       => 'Tidak', 'tanggal_kultur_urin'   => $today,
    'laborat'           => 'Tidak', 'tanggal_laborat'       => $today,
    // Hasil Lab
    'hematokrit'    => '',
    'hemoglobin'    => '',
    'leukosit'      => '',
    'trombosit'     => '',
    'hitung_jenis'  => '',
    'ureum'         => '',
    'urin_lengkap'  => '',
    'kreatinin'     => '',
    'cct'           => '',
    'sgot'          => '',
    'sgpt'          => '',
    'ct'            => '',
    'asam_urat'     => '',
    'hbsag'         => 'Non Reaktif',
    'anti_hcv'      => 'Non Reaktif',
    // V. Edukasi
    'edukasi'       => '',
];

if ($isEdit) {
    $data = array_merge($data, $rsCheck);
}

// Helper: nilai kolom
function d($key) {
    global $data;
    return htmlspecialchars(isset($data[$key]) ? $data[$key] : '');
}
function sel($key, $val) {
    global $data;
    return (isset($data[$key]) && $data[$key] == $val) ? 'selected' : '';
}
function chk($key) {
    global $data;
    return (isset($data[$key]) && $data[$key] == 'Ya') ? 'checked' : '';
}
function tglVal($key) {
    global $data;
    $v = isset($data[$key]) ? $data[$key] : date('Y-m-d');
    return $v ? date('Y-m-d', strtotime($v)) : date('Y-m-d');
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<style>
/* ── Inline styles khusus hemodialisa ─────────────────────────────── */
.riwayat-row {
    display: grid;
    grid-template-columns: 140px 110px 1fr;
    align-items: center;
    gap: 6px;
    padding: 5px 0;
    border-bottom: 1px solid #f1f5f9;
}
.riwayat-row:last-child { border-bottom: none; }
.riwayat-row label {
    font-size: 11px;
    font-weight: 600;
    color: #374151;
    margin: 0;
}
.riwayat-row select {
    padding: 5px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 12px;
    background: #fff;
}
.riwayat-row input[type="text"] {
    padding: 5px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 12px;
    width: 100%;
}
.riwayat-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 30px;
}
.penunjang-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.penunjang-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
}
.penunjang-item input[type="checkbox"] {
    width: 15px;
    height: 15px;
    cursor: pointer;
    accent-color: #667eea;
    flex-shrink: 0;
}
.penunjang-item label {
    font-size: 11px;
    font-weight: 600;
    color: #374151;
    flex: 0 0 auto;
    cursor: pointer;
    margin: 0;
}
.penunjang-item input[type="date"] {
    padding: 3px 6px;
    border: 1px solid #cbd5e1;
    border-radius: 3px;
    font-size: 11px;
    flex: 1;
    min-width: 0;
}
.lab-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
.lab-item {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.lab-item label {
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.lab-item input {
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 12px;
}
.lab-item select {
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 12px;
}
.dialisis-row {
    display: grid;
    grid-template-columns: 160px 1fr 160px 1fr 180px 1fr;
    align-items: center;
    gap: 8px;
}
.dialisis-row label {
    font-size: 11px;
    font-weight: 600;
    color: #374151;
    white-space: nowrap;
}
.dialisis-row select,
.dialisis-row input[type="date"] {
    padding: 5px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 12px;
    width: 100%;
}
</style>

<div class="modern-form-container">

    <!-- ═══ PATIENT HEADER ════════════════════════════════════════════════ -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">water_drop</i>
                PENILAIAN AWAL MEDIS HEMODIALISA
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
                        <span id="progress-text-hd" style="font-weight:bold;font-size:14px;color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px;height:8px;background:#e9ecef;border-radius:4px;overflow:hidden;">
                        <div id="progress-bar-hd" style="width:0%;height:100%;transition:width 0.3s ease,background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-hd" style="font-size:10px;color:#6c757d;white-space:nowrap;">(0/0)</span>
            </div>
        </div>

        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <?php if ($isEdit && !empty($nama_dokter_pengisi)): ?>
            <div class="info-item"><i class="material-icons">medical_services</i><strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ FORM CARD ═════════════════════════════════════════════════════ -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPenilaianMedisHD" method="post" action="">
                <input type="hidden" name="no_rawat"  value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <!-- ── BARIS HEADER FORM ──────────────────────────────────── -->
                <div class="section">
                    <div class="form-grid cols-3" style="margin-bottom:10px;">
                        <div class="form-group">
                            <label>Tanggal &amp; Waktu</label>
                            <input type="datetime-local" name="tanggal"
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Anamnesis</label>
                            <select name="anamnesis">
                                <option value="Autoanamnesis" <?php echo sel('anamnesis','Autoanamnesis'); ?>>Autoanamnesis</option>
                                <option value="Alloanamnesis" <?php echo sel('anamnesis','Alloanamnesis'); ?>>Alloanamnesis</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Hubungan (jika Alloanamnesis)</label>
                            <input type="text" name="hubungan" value="<?php echo d('hubungan'); ?>" placeholder="Suami, Istri, Anak, dll">
                        </div>
                    </div>
                    <div class="form-grid cols-2" style="margin-bottom:10px;">
                        <div class="form-group">
                            <label>Asal Poli / Ruangan</label>
                            <input type="text" name="ruangan" value="<?php echo d('ruangan'); ?>" placeholder="Nama poliklinik / ruangan">
                        </div>
                        <div class="form-group">
                            <label>Riwayat Alergi Obat</label>
                            <input type="text" name="alergi" value="<?php echo d('alergi'); ?>" placeholder="Sebutkan alergi obat jika ada">
                        </div>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Skala Nyeri</label>
                            <select name="nyeri">
                                <?php foreach (['Tidak Nyeri','Nyeri Ringan','Nyeri Sedang','Nyeri Berat','Nyeri Sangat Berat'] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo sel('nyeri',$opt); ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status Nutrisi</label>
                            <input type="text" name="status_nutrisi" value="<?php echo d('status_nutrisi'); ?>" placeholder="Contoh: Baik, Kurang, Lebih">
                        </div>
                    </div>
                </div>

                <!-- ── I. RIWAYAT PENYAKIT ──────────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">history</i>
                        <h2>I. RIWAYAT PENYAKIT</h2>
                    </div>
                    <div class="riwayat-cols">
                        <!-- KOLOM KIRI -->
                        <div>
                            <?php
                            $riwayatKiri = [
                                ['key'=>'hipertensi',          'label'=>'Mengalami Hipertensi'],
                                ['key'=>'diabetes',            'label'=>'Diabetes Melitus'],
                                ['key'=>'batu_saluran_kemih',  'label'=>'Batu Saluran Kemih'],
                                ['key'=>'operasi_saluran_kemih','label'=>'Operasi Saluran Kemih'],
                                ['key'=>'infeksi_saluran_kemih','label'=>'Infeksi Saluran Kemih'],
                            ];
                            foreach ($riwayatKiri as $r): ?>
                            <div class="riwayat-row">
                                <label><?php echo $r['label']; ?></label>
                                <select name="<?php echo $r['key']; ?>">
                                    <option value="Tidak" <?php echo sel($r['key'],'Tidak'); ?>>Tidak</option>
                                    <option value="Ya"    <?php echo sel($r['key'],'Ya'); ?>>Ya</option>
                                </select>
                                <input type="text" name="keterangan_<?php echo $r['key']; ?>"
                                       value="<?php echo d('keterangan_'.$r['key']); ?>"
                                       placeholder="Keterangan...">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <!-- KOLOM KANAN -->
                        <div>
                            <?php
                            $riwayatKanan = [
                                ['key'=>'bengkak_seluruh_tubuh', 'label'=>'Bengkak Seluruh Tubuh'],
                                ['key'=>'urin_berdarah',         'label'=>'Urin Berdarah'],
                                ['key'=>'penyakit_ginjal_laom',  'label'=>'Penyakit Ginjal Laom'],
                                ['key'=>'penyakit_lain',         'label'=>'Penyakit Lain'],
                                ['key'=>'konsumsi_obat_nefro',   'label'=>'Konsumsi Obat Nefrotoksis'],
                            ];
                            foreach ($riwayatKanan as $r): ?>
                            <div class="riwayat-row">
                                <label><?php echo $r['label']; ?></label>
                                <select name="<?php echo $r['key']; ?>">
                                    <option value="Tidak" <?php echo sel($r['key'],'Tidak'); ?>>Tidak</option>
                                    <option value="Ya"    <?php echo sel($r['key'],'Ya'); ?>>Ya</option>
                                </select>
                                <input type="text" name="keterangan_<?php echo $r['key']; ?>"
                                       value="<?php echo d('keterangan_'.$r['key']); ?>"
                                       placeholder="Keterangan...">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ── II. RIWAYAT DIALISIS / TRANSPLANTASI ──────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">loop</i>
                        <h2>II. RIWAYAT DIALISIS / TRANSPLANTASI</h2>
                    </div>
                    <div class="dialisis-row">
                        <label>Dialisis Pertama</label>
                        <input type="date" name="dialisis_pertama" value="<?php echo tglVal('dialisis_pertama'); ?>">

                        <label>Pernah CPAD</label>
                        <select name="pernah_cpad">
                            <option value="Tidak" <?php echo sel('pernah_cpad','Tidak'); ?>>Tidak</option>
                            <option value="Ya"    <?php echo sel('pernah_cpad','Ya'); ?>>Ya</option>
                        </select>
                        <input type="date" name="tanggal_cpad" value="<?php echo tglVal('tanggal_cpad'); ?>">

                        <label>Pernah Transplantasi Ginjal</label>
                        <select name="pernah_transplantasi">
                            <option value="Tidak" <?php echo sel('pernah_transplantasi','Tidak'); ?>>Tidak</option>
                            <option value="Ya"    <?php echo sel('pernah_transplantasi','Ya'); ?>>Ya</option>
                        </select>
                        <input type="date" name="tanggal_transplantasi" value="<?php echo tglVal('tanggal_transplantasi'); ?>">
                    </div>
                </div>

                <!-- ── III. PEMERIKSAAN FISIK PADA HD PERTAMA ────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">monitor_heart</i>
                        <h2>III. PEMERIKSAAN FISIK PADA HD PERTAMA</h2>
                    </div>

                    <!-- Keadaan Umum, Kesadaran, Nadi, BB -->
                    <div class="form-grid cols-4" style="margin-bottom:10px;">
                        <div class="form-group">
                            <label>Keadaan Umum</label>
                            <select name="keadaan_umum">
                                <?php foreach (['Sehat','Sakit Ringan','Sakit Sedang','Sakit Berat'] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo sel('keadaan_umum',$opt); ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kesadaran</label>
                            <select name="kesadaran">
                                <?php foreach (['Compos Mentis','Apatis','Somnolen','Sopor','Koma'] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo sel('kesadaran',$opt); ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nadi (x/menit)</label>
                            <input type="text" name="nadi" value="<?php echo d('nadi'); ?>" placeholder="x/menit">
                        </div>
                        <div class="form-group">
                            <label>BB (Kg)</label>
                            <input type="text" name="bb" value="<?php echo d('bb'); ?>" placeholder="Kg">
                        </div>
                    </div>

                    <!-- TD, Suhu, Napas, TB -->
                    <div class="vital-grid" style="margin-bottom:12px;">
                        <div class="vital-item">
                            <label>TD (mmHg)</label>
                            <input type="text" name="td" value="<?php echo d('td'); ?>" placeholder="mmHg">
                        </div>
                        <div class="vital-item">
                            <label>Suhu (°C)</label>
                            <input type="text" name="suhu" value="<?php echo d('suhu'); ?>" placeholder="°C">
                        </div>
                        <div class="vital-item">
                            <label>Napas (x/menit)</label>
                            <input type="text" name="napas" value="<?php echo d('napas'); ?>" placeholder="x/menit">
                        </div>
                        <div class="vital-item">
                            <label>TB (Cm)</label>
                            <input type="text" name="tb" value="<?php echo d('tb'); ?>" placeholder="Cm">
                        </div>
                    </div>

                    <!-- Pemeriksaan Sistem -->
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">

                        <!-- Abdomen -->
                        <div>
                            <p class="section-subtitle" style="margin-top:0;">Abdomen</p>
                            <?php foreach ([
                                ['key'=>'hepatomegali','label'=>'Hepatomegali'],
                                ['key'=>'splenomegali','label'=>'Splenomegali'],
                                ['key'=>'ascites',     'label'=>'Ascites'],
                            ] as $f): ?>
                            <div class="icu-field">
                                <span class="icu-label"><?php echo $f['label']; ?></span>
                                <select name="<?php echo $f['key']; ?>" class="icu-sel <?php echo ($data[$f['key']] == 'Ya') ? 'icu-ya' : ''; ?>">
                                    <option value="Tidak" <?php echo sel($f['key'],'Tidak'); ?>>Tidak</option>
                                    <option value="Ya"    <?php echo sel($f['key'],'Ya'); ?>>Ya</option>
                                </select>
                            </div>
                            <?php endforeach; ?>
                            <p class="section-subtitle" style="margin-top:10px;">Ekstremitas</p>
                            <div class="icu-field">
                                <span class="icu-label">Edema</span>
                                <select name="edema" class="icu-sel <?php echo ($data['edema'] == 'Ya') ? 'icu-ya' : ''; ?>">
                                    <option value="Tidak" <?php echo sel('edema','Tidak'); ?>>Tidak</option>
                                    <option value="Ya"    <?php echo sel('edema','Ya'); ?>>Ya</option>
                                </select>
                            </div>
                        </div>

                        <!-- Paru & Sklera & TVJ -->
                        <div>
                            <p class="section-subtitle" style="margin-top:0;">Paru</p>
                            <?php foreach ([
                                ['key'=>'whezzing','label'=>'Whezzing'],
                                ['key'=>'ronchi',  'label'=>'Ronchi'],
                            ] as $f): ?>
                            <div class="icu-field">
                                <span class="icu-label"><?php echo $f['label']; ?></span>
                                <select name="<?php echo $f['key']; ?>" class="icu-sel <?php echo ($data[$f['key']] == 'Ya') ? 'icu-ya' : ''; ?>">
                                    <option value="Tidak" <?php echo sel($f['key'],'Tidak'); ?>>Tidak</option>
                                    <option value="Ya"    <?php echo sel($f['key'],'Ya'); ?>>Ya</option>
                                </select>
                            </div>
                            <?php endforeach; ?>
                            <p class="section-subtitle" style="margin-top:10px;">Sklera</p>
                            <div class="icu-field">
                                <span class="icu-label">Ikterik</span>
                                <select name="ikterik" class="icu-sel <?php echo ($data['ikterik'] == 'Ya') ? 'icu-ya' : ''; ?>">
                                    <option value="Tidak" <?php echo sel('ikterik','Tidak'); ?>>Tidak</option>
                                    <option value="Ya"    <?php echo sel('ikterik','Ya'); ?>>Ya</option>
                                </select>
                            </div>
                            <p class="section-subtitle" style="margin-top:10px;">Tekanan Vena Jugularis (JVP)</p>
                            <div class="icu-field">
                                <span class="icu-label">JVP</span>
                                <select name="tekanan_vena" class="icu-sel">
                                    <option value="Normal"   <?php echo sel('tekanan_vena','Normal'); ?>>Normal</option>
                                    <option value="Meningkat"<?php echo sel('tekanan_vena','Meningkat'); ?>>Meningkat</option>
                                </select>
                            </div>
                        </div>

                        <!-- Konjungtiva & Jantung -->
                        <div>
                            <p class="section-subtitle" style="margin-top:0;">Konjungtiva</p>
                            <div class="icu-field">
                                <span class="icu-label">Anemia</span>
                                <select name="anemia" class="icu-sel <?php echo ($data['anemia'] == 'Ya') ? 'icu-ya' : ''; ?>">
                                    <option value="Tidak" <?php echo sel('anemia','Tidak'); ?>>Tidak</option>
                                    <option value="Ya"    <?php echo sel('anemia','Ya'); ?>>Ya</option>
                                </select>
                            </div>
                            <p class="section-subtitle" style="margin-top:10px;">Jantung</p>
                            <?php foreach ([
                                ['key'=>'kardiomegali','label'=>'Kardiomegali'],
                                ['key'=>'bising',      'label'=>'Bising'],
                            ] as $f): ?>
                            <div class="icu-field">
                                <span class="icu-label"><?php echo $f['label']; ?></span>
                                <select name="<?php echo $f['key']; ?>" class="icu-sel <?php echo ($data[$f['key']] == 'Ya') ? 'icu-ya' : ''; ?>">
                                    <option value="Tidak" <?php echo sel($f['key'],'Tidak'); ?>>Tidak</option>
                                    <option value="Ya"    <?php echo sel($f['key'],'Ya'); ?>>Ya</option>
                                </select>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ── IV. PEMERIKSAAN PENUNJANG ─────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">science</i>
                        <h2>IV. PEMERIKSAAN PENUNJANG</h2>
                    </div>

                    <!-- Checklist Penunjang -->
                    <?php
                    $penunjangList = [
                        ['key'=>'thorax',       'label'=>'1. Foto Thorax'],
                        ['key'=>'ekg',          'label'=>'2. EKG'],
                        ['key'=>'bno',          'label'=>'3. BNO/IVP'],
                        ['key'=>'usg',          'label'=>'4. USG'],
                        ['key'=>'renogram',     'label'=>'5. Renogram'],
                        ['key'=>'biopsi',       'label'=>'6. PA Biopsi Ginjal'],
                        ['key'=>'ctscan',       'label'=>'7. CT Scan'],
                        ['key'=>'arteriografi', 'label'=>'8. Arteriografi'],
                        ['key'=>'kultur_urin',  'label'=>'9. Kultur Urin'],
                        ['key'=>'laborat',      'label'=>'10. Laboratorium'],
                    ];
                    ?>
                    <div class="penunjang-grid" style="margin-bottom:14px;">
                        <?php foreach ($penunjangList as $p): ?>
                        <div class="penunjang-item">
                            <input type="checkbox" id="chk_<?php echo $p['key']; ?>"
                                   name="<?php echo $p['key']; ?>" value="Ya"
                                   <?php echo chk($p['key']); ?>
                                   onchange="toggleTglPenunjang('<?php echo $p['key']; ?>', this.checked)">
                            <label for="chk_<?php echo $p['key']; ?>"><?php echo $p['label']; ?></label>
                            <input type="date" id="tgl_<?php echo $p['key']; ?>"
                                   name="tanggal_<?php echo $p['key']; ?>"
                                   value="<?php echo tglVal('tanggal_'.$p['key']); ?>"
                                   <?php echo ($data[$p['key']] != 'Ya') ? 'disabled style="opacity:0.4;"' : ''; ?>>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Hasil Laboratorium -->
                    <p class="section-subtitle">Hasil Pemeriksaan Laboratorium</p>
                    <div class="lab-grid">
                        <?php
                        $labFields = [
                            ['key'=>'hematokrit',   'label'=>'Hematokrit'],
                            ['key'=>'hitung_jenis', 'label'=>'Hitung Jenis'],
                            ['key'=>'cct',          'label'=>'CCT'],
                            ['key'=>'asam_urat',    'label'=>'Asam Urat'],
                            ['key'=>'hemoglobin',   'label'=>'Hemoglobin'],
                            ['key'=>'ureum',        'label'=>'Ureum'],
                            ['key'=>'sgot',         'label'=>'SGOT'],
                            // HbsAg pakai select
                            ['key'=>'leukosit',     'label'=>'Leukosit'],
                            ['key'=>'urin_lengkap', 'label'=>'Urin Lengkap'],
                            ['key'=>'sgpt',         'label'=>'SGPT'],
                            // Anti HCV pakai select
                            ['key'=>'trombosit',    'label'=>'Trombosit'],
                            ['key'=>'kreatinin',    'label'=>'Kreatinin'],
                            ['key'=>'ct',           'label'=>'CT/BT'],
                        ];
                        foreach ($labFields as $lf): ?>
                        <div class="lab-item">
                            <label><?php echo $lf['label']; ?></label>
                            <input type="text" name="<?php echo $lf['key']; ?>"
                                   value="<?php echo d($lf['key']); ?>"
                                   placeholder="Isi nilai...">
                        </div>
                        <?php endforeach; ?>
                        <!-- HbsAg -->
                        <div class="lab-item">
                            <label>HbsAg</label>
                            <select name="hbsag">
                                <option value="Non Reaktif" <?php echo sel('hbsag','Non Reaktif'); ?>>Non Reaktif</option>
                                <option value="Reaktif"     <?php echo sel('hbsag','Reaktif'); ?>>Reaktif</option>
                            </select>
                        </div>
                        <!-- Anti HCV -->
                        <div class="lab-item">
                            <label>Anti HCV</label>
                            <select name="anti_hcv">
                                <option value="Non Reaktif" <?php echo sel('anti_hcv','Non Reaktif'); ?>>Non Reaktif</option>
                                <option value="Reaktif"     <?php echo sel('anti_hcv','Reaktif'); ?>>Reaktif</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ── V. EDUKASI ─────────────────────────────────────────── -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">school</i>
                        <h2>V. EDUKASI</h2>
                    </div>
                    <div class="form-group">
                        <label>Edukasi</label>
                        <textarea name="edukasi" rows="4"
                                  placeholder="Tuliskan edukasi yang diberikan kepada pasien / keluarga..."><?php echo d('edukasi'); ?></textarea>
                    </div>
                </div>

            </form>
        </div><!-- /form-content -->

        <!-- ── ACTION BAR ──────────────────────────────────────────────── -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliAwalMedisHD()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php
            $bolehHapus = false;
            if ($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if ($kd_dokter_login === $kd_dokter_data) $bolehHapus = true;
            }
            if ($bolehHapus): ?>
            <button type="button" id="btn-delete-hd" class="btn btn-danger" onclick="confirmDeleteHD()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif ($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus data ini">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-hd" form="formPenilaianMedisHD" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
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
    </div><!-- /form-card -->
</div><!-- /modern-form-container -->

<script>
/* Toggle tanggal penunjang saat checkbox berubah */
function toggleTglPenunjang(key, isChecked) {
    var tgl = document.getElementById('tgl_' + key);
    if (!tgl) return;
    tgl.disabled = !isChecked;
    tgl.style.opacity = isChecked ? '1' : '0.4';
}
/* Auto highlight icu-sel saat berubah */
document.querySelectorAll('.icu-sel').forEach(function(sel) {
    sel.addEventListener('change', function() {
        this.classList.toggle('icu-ya', this.value === 'Ya');
    });
});
</script>
<script src="<?php echo BASE_URL; ?>/js/awalmedishemodialisa.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>