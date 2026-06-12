<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
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
                            rp.no_rawat,
                            rp.no_rkm_medis,
                            rp.tgl_registrasi,
                            rp.jam_reg,
                            p.nm_pasien,
                            p.jk,
                            p.tmp_lahir,
                            p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                            d.nm_dokter,
                            d.kd_dokter
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                        WHERE rp.no_rawat = '$no_rawat'");

$rsPasien = mysqli_fetch_array($queryPasien);

if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.location.href='?act=Pasien';</script>";
    exit;
}

// Cek apakah sudah ada data triase IGD
$queryCheck = bukaquery("SELECT * FROM data_triase_igd WHERE no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;

// ✅ Cek data triase primer + JOIN pegawai
$queryPrimer = bukaquery("SELECT 
                            tp.*,
                            pg.nama as nama_petugas_primer,
                            DATE_FORMAT(tp.tanggaltriase, '%d/%m/%Y %H:%i') as tanggal_triase_formatted
                        FROM data_triase_igdprimer tp
                        LEFT JOIN pegawai pg ON tp.nik = pg.nik
                        WHERE tp.no_rawat = '$no_rawat'");
$rsPrimer = mysqli_fetch_array($queryPrimer);

// ✅ Cek data triase sekunder + JOIN pegawai
$querySekunder = bukaquery("SELECT 
                                ts.*,
                                pg.nama as nama_petugas_sekunder,
                                DATE_FORMAT(ts.tanggaltriase, '%d/%m/%Y %H:%i') as tanggal_triase_formatted
                            FROM data_triase_igdsekunder ts
                            LEFT JOIN pegawai pg ON ts.nik = pg.nik
                            WHERE ts.no_rawat = '$no_rawat'");
$rsSekunder = mysqli_fetch_array($querySekunder);

// Query master macam kasus
$queryMacamKasus = bukaquery("SELECT kode_kasus, macam_kasus FROM master_triase_macam_kasus ORDER BY kode_kasus ASC");

// Ambil kode dokter/petugas login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// ✅ SET NIK = kode dokter
$nik_login = $kd_dokter_login;

// Validasi
if(empty($nik_login)) {
    echo "<script>alert('Sesi tidak valid!'); window.location.href='?act=Login';</script>";
    exit;
}

// Data default untuk data_triase_igd
$data = array(
    'no_rawat' => $no_rawat,
    'tgl_kunjungan' => date('Y-m-d H:i:s'),
    'cara_masuk' => 'Jalan',
    'alat_transportasi' => '',
    'alasan_kedatangan' => 'Datang Sendiri',
    'keterangan_kedatangan' => '',
    'kode_kasus' => '',
    'tekanan_darah' => '',
    'nadi' => '',
    'pernapasan' => '',
    'suhu' => '',
    'saturasi_o2' => '',
    'nyeri' => ''
);

// Jika edit, gunakan data yang ada
if($isEdit) {
    $data = $rsCheck;
}

// Data default untuk triase primer
$dataPrimer = array(
    'no_rawat' => $no_rawat,
    'keluhan_utama' => '',
    'kebutuhan_khusus' => 'UPPA',
    'catatan' => '',
    'plan' => 'Ruang Resusitasi',
    'tanggaltriase' => date('Y-m-d H:i:s'),
    'nik' => $nik_login
);

if($rsPrimer) {
    $dataPrimer = $rsPrimer;
}

// Data default untuk triase sekunder
$dataSekunder = array(
    'no_rawat' => $no_rawat,
    'anamnesa_singkat' => '',
    'catatan' => '',
    'plan' => 'Zona Kuning',
    'tanggaltriase' => date('Y-m-d H:i:s'),
    'nik' => $nik_login
);

if($rsSekunder) {
    $dataSekunder = $rsSekunder;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template2.css">

<div class="modern-form-container">
    <!-- Sticky Patient Header -->
    <div class="patient-header">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
            <!-- Left: Title + Icon -->
            <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                <i class="material-icons" style="font-size: 22px;">local_hospital</i>
                <h2 style="margin: 0; font-size: 15px; font-weight: 700; white-space: nowrap;">
                    TRIASE IGD
                </h2>
            </div>
            
            <!-- Center: Patient Info -->
            <div style="display: flex; align-items: center; gap: 20px; flex: 1; font-size: 12px; overflow: hidden;">
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                    <i class="material-icons" style="font-size: 16px;">folder</i>
                    <strong>No. Rawat:</strong> 
                    <span><?php echo $rsPasien['no_rawat']; ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                    <i class="material-icons" style="font-size: 16px;">badge</i>
                    <strong>No. RM:</strong> 
                    <span><?php echo $rsPasien['no_rkm_medis']; ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <i class="material-icons" style="font-size: 16px;">person</i>
                    <strong>Nama:</strong> 
                    <span style="overflow: hidden; text-overflow: ellipsis;"><?php echo strtoupper($rsPasien['nm_pasien']); ?></span>
                </div>
            </div>
            
            <!-- Right: Badge -->
            <div style="flex-shrink: 0;">
                <span class="mode-badge <?php echo $isEdit ? 'mode-edit' : 'mode-add'; ?>">
                    <?php echo $isEdit ? '✏️ EDIT' : '➕ NEW'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Form Wrapper -->
    <div class="form-wrapper">
        <!-- Modern Tabs Navigation -->
        <div class="modern-tabs">
            <button class="tab-item active" onclick="switchTab(0)">
                <i class="material-icons">assessment</i> Pemeriksaan IGD
                <span class="tab-badge" id="badge-0" style="display:none;"></span>
            </button>
            <button class="tab-item tab-conditional" id="tab-btn-1" onclick="switchTab(1)" style="display:none;">
                <i class="material-icons">report_problem</i> Triase Primer
                <span class="tab-badge" id="badge-1" style="display:none;"></span>
            </button>
            <button class="tab-item tab-conditional" id="tab-btn-2" onclick="switchTab(2)" style="display:none;">
                <i class="material-icons">medical_services</i> Triase Sekunder
                <span class="tab-badge" id="badge-2" style="display:none;"></span>
            </button>
        </div>

        <!-- Form with scroll wrapper -->
        <form id="formTriaseIGD" method="post" action="">
            <!-- Hidden No. Rawat -->
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            
            <!-- Form Content Wrapper -->
            <div class="form-content-wrapper">
                <!-- TAB 0: PEMERIKSAAN IGD -->
                <div class="tab-content active" id="tab-0">
                    <div class="section-card">
                        <div class="section-title">Data Kunjungan</div>
                        
                        <!-- Row 1: Tanggal Kunjungan | Cara Masuk -->
                        <div class="form-row" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="form-group-modern">
                                <label>Tanggal Kunjungan *</label>
                                <input type="datetime-local" class="form-control-modern" name="tgl_kunjungan" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($data['tgl_kunjungan'])); ?>" required>
                            </div>
                            <div class="form-group-modern">
                                <label>Cara Masuk *</label>
                                <select class="form-control-modern" name="cara_masuk" required>
                                    <option value="Jalan" <?php echo ($data['cara_masuk'] == 'Jalan') ? 'selected' : ''; ?>>Jalan</option>
                                    <option value="Brankar" <?php echo ($data['cara_masuk'] == 'Brankar') ? 'selected' : ''; ?>>Brankar</option>
                                    <option value="Kursi Roda" <?php echo ($data['cara_masuk'] == 'Kursi Roda') ? 'selected' : ''; ?>>Kursi Roda</option>
                                    <option value="Digendong" <?php echo ($data['cara_masuk'] == 'Digendong') ? 'selected' : ''; ?>>Digendong</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 2: Transportasi | Alasan Kedatangan -->
                        <div class="form-row" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="form-group-modern">
                                <label>Transportasi</label>
                                <select class="form-control-modern" name="alat_transportasi">
                                    <!-- <option value="">-- Pilih --</option> -->
                                    <option value="AGD" <?php echo ($data['alat_transportasi'] == 'AGD') ? 'selected' : ''; ?>>AGD</option>
                                    <option value="Sendiri" <?php echo ($data['alat_transportasi'] == 'Sendiri') ? 'selected' : ''; ?>>Sendiri</option>
                                    <option value="Swasta" <?php echo ($data['alat_transportasi'] == 'Swasta') ? 'selected' : ''; ?>>Swasta</option>
                                </select>
                            </div>
                            <div class="form-group-modern">
                                <label>Alasan Kedatangan *</label>
                                <select class="form-control-modern" name="alasan_kedatangan" required>
                                    <option value="Datang Sendiri" <?php echo ($data['alasan_kedatangan'] == 'Datang Sendiri') ? 'selected' : ''; ?>>Datang Sendiri</option>
                                    <option value="Polisi" <?php echo ($data['alasan_kedatangan'] == 'Polisi') ? 'selected' : ''; ?>>Polisi</option>
                                    <option value="Rujukan" <?php echo ($data['alasan_kedatangan'] == 'Rujukan') ? 'selected' : ''; ?>>Rujukan</option>
                                    <option value="Bidan" <?php echo ($data['alasan_kedatangan'] == 'Bidan') ? 'selected' : ''; ?>>Bidan</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 3: Keterangan Kedatangan | Macam Kasus -->
                        <div class="form-row" style="grid-template-columns: repeat(2, 1fr);">
                            <div class="form-group-modern">
                                <label>Keterangan Kedatangan</label>
                                <input type="text" class="form-control-modern" name="keterangan_kedatangan" 
                                    value="<?php echo $data['keterangan_kedatangan']; ?>" placeholder="Keterangan tambahan...">
                            </div>
                            <div class="form-group-modern">
                                <label>Macam Kasus</label>
                                <select class="form-control-modern" name="kode_kasus">
                                    <!-- <option value="">-- Pilih Macam Kasus --</option> -->
                                    <?php
                                    $queryMacamKasus = bukaquery("SELECT kode_kasus, macam_kasus 
                                                                FROM master_triase_macam_kasus 
                                                                ORDER BY kode_kasus ASC");
                                    
                                    while($rsMacamKasus = mysqli_fetch_array($queryMacamKasus)) {
                                        $selected = ($data['kode_kasus'] == $rsMacamKasus['kode_kasus']) ? 'selected' : '';
                                        echo '<option value="'.$rsMacamKasus['kode_kasus'].'" '.$selected.'>';
                                        echo '['.$rsMacamKasus['kode_kasus'].'] '.$rsMacamKasus['macam_kasus'];
                                        echo '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Tanda-Tanda Vital -->
                    <div class="section-card">
                        <div class="section-title">Tanda-Tanda Vital</div>
                        <div class="ttv-grid">
                            <div class="ttv-item">
                                <label>Tekanan Darah (mmHg)</label>
                                <input type="text" name="tekanan_darah" value="<?php echo $data['tekanan_darah']; ?>" placeholder="mmHg">
                            </div>
                            <div class="ttv-item">
                                <label>Nadi (x/menit)</label>
                                <input type="text" name="nadi" value="<?php echo $data['nadi']; ?>" placeholder="x/menit">
                            </div>
                            <div class="ttv-item">
                                <label>Pernapasan (x/menit)</label>
                                <input type="text" name="pernapasan" value="<?php echo $data['pernapasan']; ?>" placeholder="x/menit">
                            </div>
                            <div class="ttv-item">
                                <label>Suhu (°C)</label>
                                <input type="text" name="suhu" value="<?php echo $data['suhu']; ?>" placeholder="°C">
                            </div>
                            <div class="ttv-item">
                                <label>Saturasi O2 (%)</label>
                                <input type="text" name="saturasi_o2" value="<?php echo $data['saturasi_o2']; ?>" placeholder="%">
                            </div>
                            <div class="ttv-item">
                                <label>Nyeri</label>
                                <input type="text" name="nyeri" value="<?php echo $data['nyeri']; ?>" placeholder="">
                            </div>
                        </div>
                    </div>

                    <!-- Jenis Triase -->
                    <div class="section-card">
                        <div class="section-title">Pilih Jenis Triase</div>
                        <div class="form-row">
                            <div class="form-group-modern" style="grid-column: 1 / -1;">
                                <label style="color: #dc2626; font-weight: 700; margin-bottom: 10px; display: block;">🔴 Jenis Triase *</label>
                                <div style="display: flex; gap: 20px;">
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1; padding: 15px 20px; background: white; border: 2px solid #e2e8f0; border-radius: 8px; transition: all 0.3s;">
                                        <input type="radio" name="jenis_triase" id="jenis_triase_primer" value="primer" 
                                               <?php echo ($rsPrimer) ? 'checked' : ''; ?> 
                                               onchange="handleJenisTriaseChange()" 
                                               style="width: 18px; height: 18px; cursor: pointer;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 700; color: #dc2626; font-size: 14px; margin-bottom: 3px;">
                                                ⚠️ TRIASE PRIMER
                                            </div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                Untuk kasus emergency/kritis (Immediate/Segera)
                                            </div>
                                        </div>
                                    </label>
                                    
                                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 1; padding: 15px 20px; background: white; border: 2px solid #e2e8f0; border-radius: 8px; transition: all 0.3s;">
                                        <input type="radio" name="jenis_triase" id="jenis_triase_sekunder" value="sekunder" 
                                               <?php echo ($rsSekunder && !$rsPrimer) ? 'checked' : ''; ?> 
                                               onchange="handleJenisTriaseChange()" 
                                               style="width: 18px; height: 18px; cursor: pointer;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 700; color: #0ea5e9; font-size: 14px; margin-bottom: 3px;">
                                                🏥 TRIASE SEKUNDER
                                            </div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                Untuk kasus non-kritis (Zona Kuning/Hijau)
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 1: TRIASE PRIMER -->
                <div class="tab-content" id="tab-1">
                    <?php include 'triaseprimer.php'; ?>
                </div>

                <!-- TAB 2: TRIASE SEKUNDER -->
                <div class="tab-content" id="tab-2">
                    <?php include 'triasesekunder.php'; ?>
                </div>
                
            </div><!-- End form-content-wrapper -->

            <!-- Action Buttons -->
            <div class="action-buttons">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="progress-indicator">
                        <div class="progress-dot" id="dot-0"></div>
                        <div class="progress-dot" id="dot-1" style="display:none;"></div>
                        <div class="progress-dot" id="dot-2" style="display:none;"></div>
                    </div>
                    <span style="font-size: 12px; color: #666; font-weight: 600;">
                        Tab <span id="current-tab-number">1</span> dari 2
                    </span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-modern btn-secondary-modern" onclick="window.history.back();">
                        <i class="material-icons" style="font-size: 16px;">arrow_back</i>
                        KEMBALI
                    </button>
                    <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev" onclick="previousTab()" style="display: none;">
                        <i class="material-icons" style="font-size: 16px;">navigate_before</i>
                        SEBELUMNYA
                    </button>
                    <button type="button" class="btn-modern btn-primary-modern" id="btn-next" onclick="nextTab()">
                        SELANJUTNYA
                        <i class="material-icons" style="font-size: 16px;">navigate_next</i>
                    </button>
                    <button type="submit" name="btnSimpan" class="btn-modern btn-primary-modern" id="btn-save" style="display: none;">
                        <i class="material-icons" style="font-size: 16px;">save</i>
                        SIMPAN DATA
                    </button>
                    <button type="button" name="btnHapus" class="btn-modern btn-danger-modern" id="btn-delete" style="display: none;" 
                            onclick="confirmDelete()" <?php echo !$isEdit ? 'disabled' : ''; ?>>
                        <i class="material-icons" style="font-size: 16px;">delete</i>
                        HAPUS DATA
                    </button>
                </div>
            </div>
        </form>
        
    </div><!-- End form-wrapper -->
</div><!-- End modern-form-container -->

<!-- ✅ Populate JavaScript variables dari PHP (after includes) -->
<script>
// Data dari triaseprimer.php (sudah di-include di atas)
var dataSkala1Primer = <?php echo isset($dataSkala1) ? json_encode($dataSkala1) : '{}'; ?>;
var dataSkala2Primer = <?php echo isset($dataSkala2) ? json_encode($dataSkala2) : '{}'; ?>;
var selectedSkala1Primer = <?php echo isset($selectedSkala1) ? json_encode($selectedSkala1) : '[]'; ?>;
var selectedSkala2Primer = <?php echo isset($selectedSkala2) ? json_encode($selectedSkala2) : '[]'; ?>;

// Data dari triasesekunder.php (sudah di-include di atas)
var dataSkala3Sekunder = <?php echo isset($dataSkala3) ? json_encode($dataSkala3) : '{}'; ?>;
var dataSkala4Sekunder = <?php echo isset($dataSkala4) ? json_encode($dataSkala4) : '{}'; ?>;
var dataSkala5Sekunder = <?php echo isset($dataSkala5) ? json_encode($dataSkala5) : '{}'; ?>;
var selectedSkala3Sekunder = <?php echo isset($selectedSkala3) ? json_encode($selectedSkala3) : '[]'; ?>;
var selectedSkala4Sekunder = <?php echo isset($selectedSkala4) ? json_encode($selectedSkala4) : '[]'; ?>;
var selectedSkala5Sekunder = <?php echo isset($selectedSkala5) ? json_encode($selectedSkala5) : '[]'; ?>;

console.log('📊 Data loaded from PHP:');
console.log('  - dataSkala1Primer keys:', Object.keys(dataSkala1Primer));
console.log('  - dataSkala2Primer keys:', Object.keys(dataSkala2Primer));
console.log('  - selectedSkala1Primer:', selectedSkala1Primer);
console.log('  - selectedSkala2Primer:', selectedSkala2Primer);
</script>

<script src="<?php echo BASE_URL; ?>/js/triaseigd.js"></script>
<script src="<?php echo BASE_URL; ?>/js/triaseprimer.js"></script>
<script src="<?php echo BASE_URL; ?>/js/triasesekunder.js"></script>

<!-- ✅ Pass data petugas ke JavaScript -->
<script>
const petugasPrimer = <?php echo $rsPrimer ? json_encode([
    'nama' => $rsPrimer['nama_petugas_primer'],
    'tanggal' => $rsPrimer['tanggal_triase_formatted']
]) : 'null'; ?>;

const petugasSekunder = <?php echo $rsSekunder ? json_encode([
    'nama' => $rsSekunder['nama_petugas_sekunder'],
    'tanggal' => $rsSekunder['tanggal_triase_formatted']
]) : 'null'; ?>;

// ✅ Update section title dengan info petugas saat page load
window.addEventListener('load', function() {
    updateSectionTitleWithPetugas();
});

// ✅ Fungsi untuk update section title
function updateSectionTitleWithPetugas() {
    // Update Triase Primer
    const primerCard = document.querySelector('#tab-1 .section-card');
    if(primerCard && petugasPrimer) {
        const sectionTitle = primerCard.querySelector('.section-title');
        if(sectionTitle) {
            sectionTitle.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Triase Primer</span>
                    <div style="font-size: 11px; font-weight: 500; color: #666; display: flex; align-items: center; gap: 8px;">
                        <i class="material-icons" style="font-size: 14px; color: #dc2626;">person</i>
                        <span>Petugas: <strong>${petugasPrimer.nama}</strong></span>
                        <span style="margin-left: 10px; color: #999;">|</span>
                        <i class="material-icons" style="font-size: 14px; color: #666;">schedule</i>
                        <span>${petugasPrimer.tanggal}</span>
                    </div>
                </div>
            `;
        }
    }
    
    // Update Triase Sekunder
    const sekunderCard = document.querySelector('#tab-2 .section-card');
    if(sekunderCard && petugasSekunder) {
        const sectionTitle = sekunderCard.querySelector('.section-title');
        if(sectionTitle) {
            sectionTitle.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>Triase Sekunder</span>
                    <div style="font-size: 11px; font-weight: 500; color: #666; display: flex; align-items: center; gap: 8px;">
                        <i class="material-icons" style="font-size: 14px; color: #0ea5e9;">person</i>
                        <span>Petugas: <strong>${petugasSekunder.nama}</strong></span>
                        <span style="margin-left: 10px; color: #999;">|</span>
                        <i class="material-icons" style="font-size: 14px; color: #666;">schedule</i>
                        <span>${petugasSekunder.tanggal}</span>
                    </div>
                </div>
            `;
        }
    }
}
</script>