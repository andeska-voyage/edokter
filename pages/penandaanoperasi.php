<?php
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
    echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>";
    exit;
}

// Ambil kode dokter login
$kd_dokter_login = '';
$nm_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) {
    $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    $qDok = bukaquery("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter_login'");
    $rsDok = mysqli_fetch_array($qDok);
    if($rsDok) $nm_dokter_login = $rsDok['nm_dokter'];
}

// Cek apakah sudah ada file penandaan operasi di berkas_digital_perawatan
$berkas_digital_url = BERKAS_DIGITAL_BASE_URL;
$no_rawat_safe = str_replace('/', '_', $no_rawat);
$pdf_filename = "penandaan_operasi_{$no_rawat_safe}.pdf";
$pdf_relative_path = "pages/upload/{$pdf_filename}";
$pdf_full_url = $berkas_digital_url . $pdf_relative_path;

// Cek dari database berkas_digital_perawatan
$kd_berkas = defined('KD_BERKAS_PENANDAAN_OPERASI') ? KD_BERKAS_PENANDAAN_OPERASI : '005';
$cekBerkas = bukaquery("SELECT lokasi_file FROM berkas_digital_perawatan 
                        WHERE no_rawat = '$no_rawat' 
                        AND kode = '$kd_berkas'
                        AND lokasi_file LIKE '%penandaan_operasi%' 
                        LIMIT 1");
$rsBerkas = mysqli_fetch_array($cekBerkas);
$fileExists = false;
if($rsBerkas) {
    $pdf_relative_path = $rsBerkas['lokasi_file'];
    $pdf_full_url = $berkas_digital_url . $pdf_relative_path;
    $berkasrawat_local = getBerkasDigitalLocalPath() . $pdf_relative_path;
    $fileExists = file_exists($berkasrawat_local);
}

// Ambil data booking operasi (nama operasi)
$queryBooking = bukaquery("SELECT bo.*, pk.nm_perawatan as nama_operasi 
                           FROM booking_operasi bo 
                           LEFT JOIN paket_operasi pk ON bo.kode_paket = pk.kode_paket
                           WHERE bo.no_rawat = '$no_rawat' 
                           ORDER BY bo.tanggal DESC LIMIT 1");
$rsBooking = mysqli_fetch_array($queryBooking);
$nama_operasi = ($rsBooking && !empty($rsBooking['nama_operasi'])) ? $rsBooking['nama_operasi'] : '-';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<style>
/* ============================================
   PENANDAAN OPERASI - SCOPED STYLES
   ============================================ */
.penandaan-container {
    width: 100%;
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.penandaan-container .form-card {
    background: #fff;
    margin: 0;
    border-radius: 0;
    box-shadow: none;
}

.penandaan-container .form-content {
    padding: 15px 20px;
}

/* Section */
.penandaan-container .section {
    margin-bottom: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.penandaan-container .section-header {
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    padding: 10px 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid #e2e8f0;
}

.penandaan-container .section-header h2 {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.penandaan-container .section-header .material-icons {
    font-size: 18px;
    color: #475569;
}

.penandaan-container .section-body {
    padding: 15px;
}

/* Body Map Canvas Area */
.penandaan-container .bodymap-area {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.penandaan-container .bodymap-panel {
    text-align: center;
}

.penandaan-container .bodymap-panel h3 {
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.penandaan-container .bodymap-canvas-wrap {
    position: relative;
    border: 2px solid #cbd5e1;
    border-radius: 10px;
    overflow: hidden;
    background: #fafbfc;
    cursor: crosshair;
}

.penandaan-container .bodymap-canvas-wrap canvas {
    display: block;
}

/* Drawing Toolbar */
.penandaan-container .drawing-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.penandaan-container .tool-btn {
    padding: 6px 14px;
    border: 2px solid #cbd5e1;
    background: white;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 5px;
    color: #475569;
}

.penandaan-container .tool-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
}

.penandaan-container .tool-btn.active {
    border-color: #3b82f6;
    background: #3b82f6;
    color: white;
}

.penandaan-container .tool-btn .material-icons {
    font-size: 16px;
}

.penandaan-container .color-picker-wrap {
    display: flex;
    align-items: center;
    gap: 5px;
}

.penandaan-container .color-picker-wrap input[type="color"] {
    width: 32px;
    height: 32px;
    border: 2px solid #cbd5e1;
    border-radius: 8px;
    cursor: pointer;
    padding: 2px;
}

.penandaan-container .brush-size-wrap {
    display: flex;
    align-items: center;
    gap: 5px;
}

.penandaan-container .brush-size-wrap input[type="range"] {
    width: 80px;
}

.penandaan-container .brush-size-wrap span {
    font-size: 11px;
    color: #64748b;
    font-weight: 600;
    min-width: 25px;
}

/* Form inputs */
.penandaan-container .form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.penandaan-container .form-group {
    flex: 1;
    min-width: 200px;
}

.penandaan-container .form-group label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.penandaan-container .form-group select,
.penandaan-container .form-group input,
.penandaan-container .form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    transition: border-color 0.2s;
    background: white;
    font-family: inherit;
}

.penandaan-container .form-group select:focus,
.penandaan-container .form-group input:focus,
.penandaan-container .form-group textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Action buttons */
.penandaan-container .action-bar {
    display: flex;
    gap: 10px;
    justify-content: center;
    padding: 15px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.penandaan-container .btn-action {
    padding: 10px 28px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.penandaan-container .btn-save {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.penandaan-container .btn-save:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.penandaan-container .btn-save:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.penandaan-container .btn-clear {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.penandaan-container .btn-clear:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.penandaan-container .btn-back {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
}

.penandaan-container .btn-back:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(107, 114, 128, 0.4);
}

.penandaan-container .btn-view-pdf {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

.penandaan-container .btn-view-pdf:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

/* Existing file notice */
.penandaan-container .existing-notice {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border: 2px solid #6ee7b7;
    border-radius: 10px;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.penandaan-container .existing-notice .material-icons {
    color: #059669;
    font-size: 24px;
}

.penandaan-container .existing-notice span {
    font-size: 13px;
    color: #065f46;
    font-weight: 600;
}

/* View Selector */
.penandaan-container .bodymap-view-selector {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.penandaan-container .view-select-btn {
    padding: 8px 18px;
    border: 2px solid #e2e8f0;
    background: white;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.25s;
    display: flex;
    align-items: center;
    gap: 6px;
    color: #64748b;
}

.penandaan-container .view-select-btn:hover {
    border-color: #3b82f6;
    color: #3b82f6;
    background: #eff6ff;
}

.penandaan-container .view-select-btn.active {
    border-color: #1e40af;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    box-shadow: 0 3px 10px rgba(59, 130, 246, 0.3);
}

.penandaan-container .view-select-btn .material-icons {
    font-size: 18px;
}

/* Responsive */
@media (max-width: 768px) {
    .penandaan-container .bodymap-area {
        flex-direction: column;
        align-items: center;
    }
    .penandaan-container .form-row {
        flex-direction: column;
    }
    .penandaan-container .bodymap-view-selector {
        justify-content: center;
    }
}
</style>

<div class="penandaan-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="material-icons">location_on</i>
                PENANDAAN AREA OPERASI
                <?php if($fileExists): ?>
                <span class="mode-badge mode-edit" style="background: #10b981; color: white; padding: 3px 10px; border-radius: 12px; font-size: 11px;">&#x2705; TERSIMPAN</span>
                <?php else: ?>
                <span class="mode-badge mode-add" style="background: #f59e0b; color: white; padding: 3px 10px; border-radius: 12px; font-size: 11px;">&#x2795; BARU</span>
                <?php endif; ?>
            </h1>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <div class="info-item"><i class="material-icons">medical_services</i><strong>Operasi:</strong> <?php echo htmlspecialchars($nama_operasi); ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Dokter:</strong> <?php echo htmlspecialchars($nm_dokter_login); ?></div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">

            <?php if($fileExists): ?>
            <div class="existing-notice">
                <i class="material-icons">check_circle</i>
                <span>File penandaan operasi sudah tersimpan. Anda bisa melihat, membuat ulang, atau menghapus.</span>
                <div style="display: flex; gap: 8px; margin-left: auto;">
                    <a href="<?php echo $pdf_full_url; ?>" target="_blank" class="btn-action btn-view-pdf" style="text-decoration: none; padding: 6px 14px; font-size: 11px;">
                        <i class="material-icons" style="font-size: 14px;">open_in_new</i> Lihat PDF
                    </a>
                    <button type="button" class="btn-action btn-clear" id="btn-hapus-penandaan" style="padding: 6px 14px; font-size: 11px;">
                        <i class="material-icons" style="font-size: 14px;">delete</i> Hapus
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Section: Informasi Operasi -->
            <div class="section">
                <div class="section-header">
                    <i class="material-icons">assignment</i>
                    <h2>Informasi Operasi</h2>
                </div>
                <div class="section-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal & Waktu</label>
                            <input type="datetime-local" id="pnd_tanggal" value="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Tindakan / Prosedur Operasi</label>
                            <input type="text" id="pnd_tindakan" value="<?php echo htmlspecialchars($nama_operasi); ?>" placeholder="Nama tindakan operasi...">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Lateralitas</label>
                            <select id="pnd_lateralitas">
                                <option value="">- Pilih -</option>
                                <option value="Kanan">Kanan (Dextra)</option>
                                <option value="Kiri">Kiri (Sinistra)</option>
                                <option value="Bilateral">Bilateral</option>
                                <option value="Midline">Midline / Tengah</option>
                                <option value="Tidak Berlaku">Tidak Berlaku</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Lokasi Anatomis</label>
                            <input type="text" id="pnd_lokasi_anatomis" placeholder="Contoh: Abdomen kuadran kanan bawah, Lutut kanan...">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Diagnosis Pre-Operasi</label>
                            <input type="text" id="pnd_diagnosis" placeholder="Diagnosis pre-operasi...">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Catatan Tambahan</label>
                            <textarea id="pnd_catatan" rows="2" placeholder="Catatan tambahan terkait penandaan..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Konfirmasi Penandaan -->
            <div class="section">
                <div class="section-header">
                    <i class="material-icons">fact_check</i>
                    <h2>Konfirmasi Penandaan</h2>
                </div>
                <div class="section-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Penandaan Dilakukan Oleh</label>
                            <select id="pnd_penanda">
                                <option value="Dokter Operator">Dokter Operator</option>
                                <option value="Dokter Asisten">Dokter Asisten</option>
                                <option value="Dokter Residen">Dokter Residen</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Pasien / Keluarga Ikut Terlibat</label>
                            <select id="pnd_pasien_terlibat">
                                <option value="Ya">Ya</option>
                                <option value="Tidak">Tidak</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Penandaan Terlihat Setelah Pasien Diprep</label>
                            <select id="pnd_terlihat_setelah_prep">
                                <option value="Ya">Ya</option>
                                <option value="Tidak">Tidak</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Body Map -->
            <div class="section">
                <div class="section-header">
                    <i class="material-icons">accessibility_new</i>
                    <h2>Penandaan pada Gambar Tubuh (Body Map)</h2>
                </div>
                <div class="section-body">

                    <!-- View Selector -->
                    <div id="bodymap-view-selector" class="bodymap-view-selector">
                        <!-- Buttons will be generated by JS -->
                    </div>

                    <!-- Drawing Toolbar -->
                    <div class="drawing-toolbar">
                        <button type="button" class="tool-btn active" data-tool="marker" title="Marker Penandaan">
                            <i class="material-icons">edit</i> Marker
                        </button>
                        <button type="button" class="tool-btn" data-tool="circle" title="Lingkaran">
                            <i class="material-icons">radio_button_unchecked</i> Lingkaran
                        </button>
                        <button type="button" class="tool-btn" data-tool="arrow" title="Panah">
                            <i class="material-icons">arrow_forward</i> Panah
                        </button>
                        <button type="button" class="tool-btn" data-tool="text" title="Teks Label">
                            <i class="material-icons">text_fields</i> Teks
                        </button>
                        <button type="button" class="tool-btn" data-tool="eraser" title="Penghapus">
                            <i class="material-icons">auto_fix_high</i> Hapus
                        </button>

                        <div style="width: 1px; height: 28px; background: #cbd5e1; margin: 0 5px;"></div>

                        <div class="color-picker-wrap">
                            <label style="font-size: 11px; font-weight: 600; color: #64748b;">Warna:</label>
                            <input type="color" id="pnd_draw_color" value="#e53e3e">
                        </div>

                        <div class="brush-size-wrap">
                            <label style="font-size: 11px; font-weight: 600; color: #64748b;">Size:</label>
                            <input type="range" id="pnd_brush_size" min="1" max="12" value="3">
                            <span id="pnd_brush_size_label">3px</span>
                        </div>

                        <div style="width: 1px; height: 28px; background: #cbd5e1; margin: 0 5px;"></div>

                        <button type="button" class="tool-btn" id="btn-undo-pnd" title="Undo">
                            <i class="material-icons">undo</i>
                        </button>
                        <button type="button" class="tool-btn" id="btn-clear-canvas-pnd" title="Bersihkan Semua" style="border-color: #fca5a5; color: #dc2626;">
                            <i class="material-icons">delete_sweep</i> Reset
                        </button>
                    </div>

                    <!-- Dynamic Body Map Canvases (generated by JS) -->
                    <div class="bodymap-area" id="bodymap-canvas-area">
                        <!-- Canvases will be generated dynamically -->
                    </div>
                </div>
            </div>

            <!-- Section: Tanda Tangan Pasien/Keluarga & QR Dokter -->
            <div class="section">
                <div class="section-header">
                    <i class="material-icons">draw</i>
                    <h2>Tanda Tangan & Verifikasi</h2>
                </div>
                <div class="section-body">
                    <div style="display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; align-items: flex-start;">
                        
                        <!-- TTD Pasien / Keluarga -->
                        <div style="text-align: center; flex: 1; min-width: 280px; max-width: 420px;">
                            <div style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">person</i>
                                Tanda Tangan Pasien / Keluarga
                            </div>
                            <canvas id="canvas-ttd-pasien" width="400" height="150" style="border: 2px dashed #cbd5e1; border-radius: 8px; cursor: crosshair; background: #fafbfc; width: 100%; max-width: 400px;"></canvas>
                            <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px; justify-content: center;">
                                <button type="button" class="tool-btn" id="btn-clear-ttd" style="font-size: 11px; padding: 4px 10px;">
                                    <i class="material-icons" style="font-size: 14px;">delete</i> Hapus TTD
                                </button>
                            </div>
                            <div style="margin-top: 8px;">
                                <input type="text" id="pnd_nama_ttd" placeholder="Nama pasien / keluarga yang menandatangani..." 
                                       style="width: 100%; max-width: 400px; padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 13px; text-align: center;">
                            </div>
                            <div style="margin-top: 4px; font-size: 10px; color: #94a3b8;">
                                Tanda tangan di atas oleh pasien atau keluarga pasien
                            </div>
                        </div>

                        <!-- QR Code Dokter (Auto Generate) -->
                        <div style="text-align: center; flex: 0 0 auto; min-width: 200px;">
                            <div style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">qr_code_2</i>
                                QR Code Dokter
                            </div>
                            <div id="qrcode-dokter" style="display: inline-block; padding: 10px; background: white; border: 2px solid #e2e8f0; border-radius: 10px;">
                                <!-- QR Code akan di-generate oleh JS -->
                            </div>
                            <div style="margin-top: 8px; font-size: 13px; color: #334155; font-weight: 600;">
                                <?php echo htmlspecialchars($nm_dokter_login); ?>
                            </div>
                            <div style="font-size: 10px; color: #94a3b8;">
                                QR berisi data verifikasi dokter
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn-action btn-back" onclick="window.history.back();">
                <i class="material-icons">arrow_back</i> Kembali
            </button>
            <?php if($fileExists): ?>
            <button type="button" class="btn-action btn-clear" id="btn-hapus-penandaan-bottom" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                <i class="material-icons">delete_forever</i> Hapus PDF
            </button>
            <?php endif; ?>
            <button type="button" class="btn-action btn-clear" id="btn-reset-all-pnd">
                <i class="material-icons">refresh</i> Reset Semua
            </button>
            <button type="button" class="btn-action btn-save" id="btn-simpan-penandaan">
                <i class="material-icons">save</i> Simpan sebagai PDF
            </button>
        </div>
    </div>
</div>

<!-- Hidden data -->
<input type="hidden" id="pnd_no_rawat" value="<?php echo htmlspecialchars($no_rawat); ?>">
<input type="hidden" id="pnd_no_rkm_medis" value="<?php echo htmlspecialchars($no_rkm_medis); ?>">
<input type="hidden" id="pnd_nm_pasien" value="<?php echo htmlspecialchars($rsPasien['nm_pasien']); ?>">
<input type="hidden" id="pnd_nm_dokter" value="<?php echo htmlspecialchars($nm_dokter_login); ?>">
<input type="hidden" id="pnd_kd_dokter" value="<?php echo htmlspecialchars($kd_dokter_login); ?>">
<input type="hidden" id="pnd_tgl_lahir" value="<?php echo $rsPasien['tgl_lahir']; ?>">
<input type="hidden" id="pnd_umur" value="<?php echo $rsPasien['umur']; ?>">
<input type="hidden" id="pnd_base_url" value="<?php echo BASE_URL; ?>">

<!-- Load jsPDF + html2canvas from CDN fallback or local -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="<?php echo BASE_URL; ?>js/penandaanoperasi.js?v=<?php echo time(); ?>"></script>
