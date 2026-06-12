<?php
/**
 * Grafik TTV Rawat Inap (Kardex Style)
 * Menampilkan tren vital signs pasien selama rawat inap
 * Panel collapsible seperti grafik laboratorium
 * + Balance Cairan dari tabel catatan_keseimbangan_cairan
 */
session_start();
require_once('../conf/conf.php');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo "<div class='alert alert-danger'>Session expired</div>";
    exit();
}

// Ambil parameter
$norm = isset($_GET['norm']) ? validTeks4($_GET['norm'], 20) : '';
$current_norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';

if(empty($norm)){
    echo "<div class='alert alert-danger'>Parameter NO RM tidak valid</div>";
    exit();
}

// Ambil daftar no_rawat yang punya data pemeriksaan ranap ATAU observasi ranap
$query_norawat = bukaquery("
    SELECT DISTINCT combined.no_rawat, rp.tgl_registrasi, rp.status_lanjut
    FROM (
        SELECT no_rawat FROM pemeriksaan_ranap
        UNION
        SELECT no_rawat FROM catatan_observasi_ranap
    ) combined
    INNER JOIN reg_periksa rp ON combined.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm'
    ORDER BY rp.tgl_registrasi DESC
");

$norawat_list = [];
while($row = mysqli_fetch_array($query_norawat)){
    $norawat_list[] = $row;
}

// Ambil data pasien
$query_pasien = bukaquery("
    SELECT 
        p.nm_pasien,
        p.no_rkm_medis,
        p.jk,
        p.tgl_lahir,
        TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURDATE()) as umur_tahun,
        TIMESTAMPDIFF(MONTH, p.tgl_lahir, CURDATE()) % 12 as umur_bulan,
        TIMESTAMPDIFF(DAY, DATE_ADD(DATE_ADD(p.tgl_lahir, INTERVAL TIMESTAMPDIFF(YEAR, p.tgl_lahir, CURDATE()) YEAR), INTERVAL (TIMESTAMPDIFF(MONTH, p.tgl_lahir, CURDATE()) % 12) MONTH), CURDATE()) as umur_hari
    FROM pasien p
    WHERE p.no_rkm_medis = '$norm'
");
$data_pasien = mysqli_fetch_assoc($query_pasien);

$nama_pasien = $data_pasien['nm_pasien'] ?? '';
$jenis_kelamin = ($data_pasien['jk'] ?? '') == 'L' ? 'Laki-laki' : 'Perempuan';

// Format umur
$umur_tahun = $data_pasien['umur_tahun'] ?? 0;
$umur_bulan = $data_pasien['umur_bulan'] ?? 0;
$umur_hari = $data_pasien['umur_hari'] ?? 0;

if($umur_tahun > 0) {
    $umur_pasien = $umur_tahun . ' Tahun';
    if($umur_bulan > 0) $umur_pasien .= ' ' . $umur_bulan . ' Bulan';
} else if($umur_bulan > 0) {
    $umur_pasien = $umur_bulan . ' Bulan';
    if($umur_hari > 0) $umur_pasien .= ' ' . $umur_hari . ' Hari';
} else {
    $umur_pasien = $umur_hari . ' Hari';
}

// Hitung total data (dari pemeriksaan_ranap + observasi_ranap)
$query_count = bukaquery("
    SELECT 
        (SELECT COUNT(*) FROM pemeriksaan_ranap pr 
         INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat 
         WHERE rp.no_rkm_medis = '$norm') +
        (SELECT COUNT(*) FROM catatan_observasi_ranap cor 
         INNER JOIN reg_periksa rp ON cor.no_rawat = rp.no_rawat 
         WHERE rp.no_rkm_medis = '$norm') as total
");
$total_data = mysqli_fetch_assoc($query_count)['total'];

?>
<style>
/* Modern Color Palette */
:root {
    --primary: #667eea;
    --primary-dark: #5568d3;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --light-bg: #f8fafc;
    --card-bg: #ffffff;
    --border: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
}

.ttv-container {
    background: var(--light-bg);
    padding: 0;
    margin: 0;
}

/* Grafik Panel - Collapsible */
.grafik-panel {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    border: 1px solid var(--border);
    overflow: hidden;
}

.grafik-panel-header {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 14px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
}

.grafik-panel-header:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

.grafik-panel-header h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-toggle-grafik {
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 6px;
    padding: 4px 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-toggle-grafik:hover {
    background: rgba(255,255,255,0.3);
}

.btn-toggle-grafik i {
    color: white;
    font-size: 20px;
    transition: transform 0.3s;
}

.btn-toggle-grafik.collapsed i {
    transform: rotate(180deg);
}

.grafik-panel-content {
    padding: 20px;
    display: none;
}

/* Filter Group */
.filter-row {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.filter-item {
    flex: 1;
    min-width: 250px;
}

.filter-item label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-item select {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-primary);
    background: white;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-item select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Section Divider */
.section-divider {
    display: flex;
    align-items: center;
    margin: 20px 0 16px 0;
    gap: 12px;
}

.section-divider .line {
    flex: 1;
    height: 1px;
    background: var(--border);
}

.section-divider .title {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

/* TTV Selection Grid */
.ttv-selection-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
    margin-bottom: 16px;
    padding: 4px;
}

.ttv-checkbox-item {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border: 2px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
}

.ttv-checkbox-item:hover {
    border-color: var(--primary);
    background: #f8fafc;
}

.ttv-checkbox-item.selected {
    border-color: var(--success);
    background: #ecfdf5;
}

.ttv-checkbox-item.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.ttv-checkbox-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
    margin-right: 8px;
    cursor: pointer;
    accent-color: var(--success);
}

.ttv-item-info {
    flex: 1;
}

.ttv-item-name {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-primary);
}

.ttv-item-unit {
    font-size: 10px;
    color: var(--text-secondary);
}

.ttv-item-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 10px;
}

.ttv-item-icon i {
    font-size: 18px;
    color: white;
}

/* Icon colors per TTV - Kontras tinggi */
.icon-tensi { background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%); }  /* Merah tua */
.icon-nadi { background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%); }   /* Ungu terang */
.icon-suhu { background: linear-gradient(135deg, #CA8A04 0%, #A16207 100%); }   /* Kuning tua */
.icon-rr { background: linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%); }     /* Biru terang */
.icon-spo2 { background: linear-gradient(135deg, #16A34A 0%, #15803D 100%); }   /* Hijau tua */
.icon-gcs { background: linear-gradient(135deg, #475569 0%, #334155 100%); }    /* Abu slate */

/* Icon colors for Balance Cairan - Kontras tinggi */
.icon-infus { background: linear-gradient(135deg, #0891B2 0%, #0E7490 100%); }     /* Cyan tua */
.icon-tranfusi { background: linear-gradient(135deg, #BE185D 0%, #9D174D 100%); }  /* Pink tua */
.icon-minum { background: linear-gradient(135deg, #0EA5E9 0%, #0284C7 100%); }     /* Biru sky */
.icon-urine { background: linear-gradient(135deg, #D97706 0%, #B45309 100%); }     /* Amber */
.icon-drain { background: linear-gradient(135deg, #78716C 0%, #57534E 100%); }     /* Abu coklat */
.icon-ngt { background: linear-gradient(135deg, #57534E 0%, #44403C 100%); }       /* Abu stone */
.icon-iwl { background: linear-gradient(135deg, #A855F7 0%, #9333EA 100%); }       /* Ungu muda */
.icon-balance { background: linear-gradient(135deg, #059669 0%, #047857 100%); }   /* Hijau tua */

/* Icon colors for Ventilator */
.icon-vt { background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%); }        /* Indigo */
.icon-rr-vent { background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%); }   /* Violet */
.icon-peep { background: linear-gradient(135deg, #A855F7 0%, #9333EA 100%); }      /* Purple */
.icon-fio2 { background: linear-gradient(135deg, #C084FC 0%, #A855F7 100%); }      /* Fuchsia */

/* Icon colors for CHBP (Obstetri) */
.icon-td-chbp { background: linear-gradient(135deg, #EC4899 0%, #DB2777 100%); }    /* Pink */
.icon-hr-chbp { background: linear-gradient(135deg, #F472B6 0%, #EC4899 100%); }   /* Pink light */
.icon-suhu-chbp { background: linear-gradient(135deg, #FB7185 0%, #F43F5E 100%); } /* Rose */
.icon-djj { background: linear-gradient(135deg, #FDA4AF 0%, #FB7185 100%); }       /* Rose light */

/* Button styles */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.btn-secondary {
    background: #e2e8f0;
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: #cbd5e1;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Action Buttons Row */
.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
}

.empty-state i {
    font-size: 64px;
    color: var(--text-secondary);
    opacity: 0.3;
}

.empty-state h4 {
    margin: 16px 0 8px 0;
    color: var(--text-primary);
    font-size: 18px;
}

.empty-state p {
    color: var(--text-secondary);
    font-size: 14px;
    margin: 0;
}

/* Spin animation */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Chart container */
#grafikTTVContainer {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

/* Info cards */
.info-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
    margin-bottom: 20px;
}

.info-card {
    background: white;
    border-radius: 8px;
    padding: 10px 14px;
    border-left: 4px solid var(--primary);
    box-shadow: var(--shadow-sm);
}

.info-card-label {
    font-size: 9px;
    color: var(--text-secondary);
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 2px;
    letter-spacing: 0.5px;
}

.info-card-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
}

.info-card.sistolik { border-left-color: #DC2626; }
.info-card.diastolik { border-left-color: #EA580C; }
.info-card.nadi { border-left-color: #7C3AED; }
.info-card.suhu { border-left-color: #CA8A04; }
.info-card.respirasi { border-left-color: #2563EB; }
.info-card.spo2 { border-left-color: #16A34A; }
.info-card.gcs { border-left-color: #475569; }
.info-card.infus { border-left-color: #0891B2; }
.info-card.tranfusi { border-left-color: #BE185D; }
.info-card.minum { border-left-color: #0EA5E9; }
.info-card.urine { border-left-color: #D97706; }
.info-card.drain { border-left-color: #78716C; }
.info-card.ngt { border-left-color: #57534E; }
.info-card.iwl { border-left-color: #A855F7; }
.info-card.keseimbangan { border-left-color: #059669; }
.info-card.vt { border-left-color: #6366F1; }
.info-card.rr_vent { border-left-color: #8B5CF6; }
.info-card.peep { border-left-color: #A855F7; }
.info-card.fio2 { border-left-color: #C084FC; }
.info-card.sistolik_chbp { border-left-color: #EC4899; }
.info-card.diastolik_chbp { border-left-color: #F472B6; }
.info-card.hr_chbp { border-left-color: #F472B6; }
.info-card.suhu_chbp { border-left-color: #FB7185; }
.info-card.djj { border-left-color: #FDA4AF; }

/* Section Title */
.section-title {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.section-title i {
    font-size: 16px;
}

/* Mode Tampilan Section */
.mode-tampilan-section {
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--border);
}

.section-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mode-tampilan-options {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.mode-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border: 2px solid var(--border);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    min-width: 180px;
}

.mode-option:hover {
    border-color: var(--primary);
    background: #f8fafc;
}

.mode-option:has(input:checked) {
    border-color: var(--success);
    background: #ecfdf5;
}

.mode-option input[type="radio"] {
    width: 18px;
    height: 18px;
    accent-color: var(--success);
    cursor: pointer;
}

.mode-icon {
    font-size: 24px;
}

.mode-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.mode-text strong {
    font-size: 13px;
    color: var(--text-primary);
}

.mode-text small {
    font-size: 11px;
    color: var(--text-secondary);
}

/* Parameter Selection Container */
.parameter-selection-container {
    transition: all 0.3s ease;
}

.parameter-selection-container.hidden {
    display: none;
}

/* Kategori Toggle Container */
.kategori-toggle-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Kategori Toggle Card */
.kategori-toggle-card {
    border: 2px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    background: white;
}

.kategori-toggle-card:hover:not(.disabled) {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.kategori-toggle-card.active {
    border-color: var(--success);
    background: #f0fdf4;
}

.kategori-toggle-card.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.kategori-toggle-card.disabled .kategori-toggle-header {
    cursor: not-allowed;
}

/* Kategori Toggle Header */
.kategori-toggle-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all 0.2s;
}

.kategori-toggle-header:hover {
    background: #f8fafc;
}

.kategori-toggle-card.active .kategori-toggle-header {
    background: #ecfdf5;
}

/* Kategori Toggle Icon */
.kategori-toggle-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.kategori-toggle-icon i {
    font-size: 22px;
    color: white;
}

/* Kategori Toggle Info */
.kategori-toggle-info {
    flex: 1;
}

.kategori-toggle-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.kategori-toggle-desc {
    font-size: 12px;
    color: var(--text-secondary);
}

/* Kategori Toggle Switch */
.kategori-toggle-switch {
    position: relative;
    width: 50px;
    height: 26px;
    flex-shrink: 0;
}

.kategori-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.kategori-toggle-switch input:checked + .toggle-slider {
    background-color: var(--success);
}

.kategori-toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

/* Kategori Toggle Badge (Coming Soon) */
.kategori-toggle-badge {
    background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Kategori Toggle Content */
.kategori-toggle-content {
    padding: 16px;
    border-top: 1px solid var(--border);
    background: #f8fafc;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 500px;
    }
}
</style>

<div class="ttv-container">
    <?php if(count($norawat_list) > 0 && $total_data > 0): ?>
    
    <!-- Panel Grafik TTV - Collapsible -->
    <div class="grafik-panel" id="grafikTTVPanel">
        <div class="grafik-panel-header" onclick="toggleGrafikTTVPanel()">
            <h4>
                <i class="material-icons">show_chart</i>
                Generate Grafik Pemeriksaan
            </h4>
            <button class="btn-toggle-grafik" id="btnToggleTTV">
                <i class="material-icons">expand_more</i>
            </button>
        </div>
        
        <div class="grafik-panel-content" id="grafikTTVPanelContent">
            <p style="margin: 0 0 16px 0; color: var(--text-secondary); font-size: 13px;">
                Pilih parameter vital signs dan balance cairan yang ingin divisualisasikan dalam grafik tren
            </p>
            
            <!-- Filter Row -->
            <div class="filter-row">
                <!-- Filter No. Rawat -->
                <div class="filter-item">
                    <label>
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">filter_alt</i>
                        Filter No. Rawat
                    </label>
                    <select id="filterNoRawatTTV">
                        <?php foreach($norawat_list as $idx => $nr): ?>
                            <?php 
                                // Default: pilih no_rawat yang sedang aktif, atau yang pertama
                                $selected = '';
                                if(!empty($current_norawat) && $nr['no_rawat'] == $current_norawat) {
                                    $selected = 'selected';
                                } else if(empty($current_norawat) && $idx == 0) {
                                    $selected = 'selected';
                                }
                                
                                $status_icon = ($nr['status_lanjut'] == 'Ralan') ? '🟢' : '🔴';
                                $tgl = date('d M Y', strtotime($nr['tgl_registrasi']));
                                $is_current = ($nr['no_rawat'] == $current_norawat) ? ' ⭐ (Aktif)' : '';
                            ?>
                            <option value="<?= htmlspecialchars($nr['no_rawat']) ?>" <?= $selected ?>>
                                <?= $status_icon ?> <?= htmlspecialchars($nr['no_rawat']) ?> - <?= $tgl ?><?= $is_current ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filter Sumber Data TTV -->
                <div class="filter-item">
                    <label>
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">storage</i>
                        Sumber Data TTV
                    </label>
                    <select id="filterSumberData">
                        <option value="pemeriksaan_ranap" selected>📋 Pemeriksaan Ranap (SOAPIE)</option>
                        <option value="observasi_ranap">📝 Observasi Ranap</option>
                    </select>
                </div>
                
                <!-- Filter Rentang Waktu -->
                <div class="filter-item">
                    <label>
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">date_range</i>
                        Rentang Waktu
                    </label>
                    <select id="filterRentangWaktu">
                        <option value="3h">⚡ 3 Jam Terakhir</option>
                        <option value="6h">⚡ 6 Jam Terakhir</option>
                        <option value="12h">🕐 12 Jam Terakhir</option>
                        <option value="24h" selected>📅 24 Jam Terakhir</option>
                        <option value="3d">📆 3 Hari Terakhir</option>
                        <option value="7d">📆 7 Hari Terakhir</option>
                        <option value="all">🗂️ Semua Data</option>
                    </select>
                </div>
            </div>
            
            <!-- Mode Tampilan -->
            <div class="mode-tampilan-section">
                <label class="section-label">
                    <i class="material-icons" style="font-size: 14px; vertical-align: middle;">dashboard</i>
                    Mode Tampilan Grafik
                </label>
                <div class="mode-tampilan-options">
                    <label class="mode-option">
                        <input type="radio" name="mode_tampilan" value="gabung" checked>
                        <span class="mode-icon">📊</span>
                        <span class="mode-text">
                            <strong>Gabung Semua</strong>
                            <small>Semua dalam 1 grafik</small>
                        </span>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="mode_tampilan" value="kategori">
                        <span class="mode-icon">📈</span>
                        <span class="mode-text">
                            <strong>Pisah Per Kategori</strong>
                            <small>TTV & Cairan terpisah</small>
                        </span>
                    </label>
                    <label class="mode-option">
                        <input type="radio" name="mode_tampilan" value="parameter">
                        <span class="mode-icon">📉</span>
                        <span class="mode-text">
                            <strong>Pisah Per Parameter</strong>
                            <small>Setiap parameter terpisah</small>
                        </span>
                    </label>
                </div>
            </div>
            
            <!-- ========== PARAMETER SELECTION ========== -->
            <div id="parameterSelectionContainer" class="parameter-selection-container">
                
                <!-- ========== SECTION: VITAL SIGNS (UTAMA - Selalu Tampil) ========== -->
                <div class="section-divider">
                    <span class="line"></span>
                    <span class="title"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">favorite</i> Tanda-Tanda Vital (TTV) - Utama</span>
                    <span class="line"></span>
                </div>
            
                <div class="ttv-selection-grid" id="ttvSelectionGrid">
                    <label class="ttv-checkbox-item" data-param="tensi" data-group="ttv">
                        <div class="ttv-item-icon icon-tensi">
                            <i class="material-icons">favorite</i>
                        </div>
                        <input type="checkbox" name="ttv_param[]" value="tensi" data-group="ttv">
                        <div class="ttv-item-info">
                            <div class="ttv-item-name">Tekanan Darah</div>
                            <div class="ttv-item-unit">Sistolik & Diastolik</div>
                        </div>
                    </label>
                    
                    <label class="ttv-checkbox-item" data-param="nadi" data-group="ttv">
                        <div class="ttv-item-icon icon-nadi">
                            <i class="material-icons">timeline</i>
                        </div>
                        <input type="checkbox" name="ttv_param[]" value="nadi" data-group="ttv">
                        <div class="ttv-item-info">
                            <div class="ttv-item-name">Nadi</div>
                            <div class="ttv-item-unit">x/menit</div>
                        </div>
                    </label>
                    
                    <label class="ttv-checkbox-item" data-param="suhu" data-group="ttv">
                        <div class="ttv-item-icon icon-suhu">
                            <i class="material-icons">thermostat</i>
                        </div>
                        <input type="checkbox" name="ttv_param[]" value="suhu" data-group="ttv">
                        <div class="ttv-item-info">
                            <div class="ttv-item-name">Suhu Tubuh</div>
                            <div class="ttv-item-unit">°Celsius</div>
                        </div>
                    </label>
                    
                    <label class="ttv-checkbox-item" data-param="respirasi" data-group="ttv">
                        <div class="ttv-item-icon icon-rr">
                            <i class="material-icons">air</i>
                        </div>
                        <input type="checkbox" name="ttv_param[]" value="respirasi" data-group="ttv">
                        <div class="ttv-item-info">
                            <div class="ttv-item-name">Respiratory Rate</div>
                            <div class="ttv-item-unit">x/menit</div>
                        </div>
                    </label>
                    
                    <label class="ttv-checkbox-item" data-param="spo2" data-group="ttv">
                        <div class="ttv-item-icon icon-spo2">
                            <i class="material-icons">water_drop</i>
                        </div>
                        <input type="checkbox" name="ttv_param[]" value="spo2" data-group="ttv">
                        <div class="ttv-item-info">
                            <div class="ttv-item-name">SpO₂</div>
                            <div class="ttv-item-unit">% Saturasi</div>
                        </div>
                    </label>
                    
                    <label class="ttv-checkbox-item" data-param="gcs" data-group="ttv">
                        <div class="ttv-item-icon icon-gcs">
                            <i class="material-icons">psychology</i>
                        </div>
                        <input type="checkbox" name="ttv_param[]" value="gcs" data-group="ttv">
                        <div class="ttv-item-info">
                            <div class="ttv-item-name">GCS</div>
                            <div class="ttv-item-unit">Glasgow Coma Scale</div>
                        </div>
                    </label>
                </div>
                
                <!-- ========== SECTION: TAMBAH KATEGORI LAIN (Collapsible) ========== -->
                <div class="section-divider" style="margin-top: 24px;">
                    <span class="line"></span>
                    <span class="title"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">add_circle</i> Tambah Kategori Lain</span>
                    <span class="line"></span>
                </div>
                
                <!-- Toggle Cards untuk Kategori Tambahan -->
                <div class="kategori-toggle-container">
                    
                    <!-- Toggle Balance Cairan -->
                    <div class="kategori-toggle-card" id="toggleBalanceCairan">
                        <div class="kategori-toggle-header">
                            <div class="kategori-toggle-icon" style="background: linear-gradient(135deg, #0891B2 0%, #0E7490 100%);">
                                <i class="material-icons">opacity</i>
                            </div>
                            <div class="kategori-toggle-info">
                                <div class="kategori-toggle-name">💧 Balance Cairan</div>
                                <div class="kategori-toggle-desc">Intake/Output (Infus, Minum, Urine, dll)</div>
                            </div>
                            <label class="kategori-toggle-switch" onclick="event.stopPropagation()">
                                <input type="checkbox" id="chkKategoriCairan">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="kategori-toggle-content" id="contentKategoriCairan" style="display: none;">
                            <div class="ttv-selection-grid" id="cairanSelectionGrid">
                                <!-- INPUT -->
                                <label class="ttv-checkbox-item" data-param="infus" data-group="cairan">
                                    <div class="ttv-item-icon icon-infus">
                                        <i class="material-icons">vaccines</i>
                                    </div>
                                    <input type="checkbox" name="cairan_param[]" value="infus" data-group="cairan">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Infus</div>
                                        <div class="ttv-item-unit">cc (Input)</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="tranfusi" data-group="cairan">
                                    <div class="ttv-item-icon icon-tranfusi">
                                        <i class="material-icons">bloodtype</i>
                                    </div>
                                    <input type="checkbox" name="cairan_param[]" value="tranfusi" data-group="cairan">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Tranfusi</div>
                                        <div class="ttv-item-unit">cc (Input)</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="minum" data-group="cairan">
                                    <div class="ttv-item-icon icon-minum">
                                        <i class="material-icons">local_drink</i>
                                    </div>
                                    <input type="checkbox" name="cairan_param[]" value="minum" data-group="cairan">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Minum</div>
                                        <div class="ttv-item-unit">cc (Input)</div>
                                    </div>
                                </label>
                                
                                <!-- OUTPUT -->
                                <label class="ttv-checkbox-item" data-param="urine" data-group="cairan">
                                    <div class="ttv-item-icon icon-urine">
                                        <i class="material-icons">water_damage</i>
                                    </div>
                                    <input type="checkbox" name="cairan_param[]" value="urine" data-group="cairan">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Urine</div>
                                        <div class="ttv-item-unit">cc (Output)</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="drain" data-group="cairan">
                                    <div class="ttv-item-icon icon-drain">
                                        <i class="material-icons">outlet</i>
                                    </div>
                                    <input type="checkbox" name="cairan_param[]" value="drain" data-group="cairan">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Drain</div>
                                        <div class="ttv-item-unit">cc (Output)</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="ngt" data-group="cairan">
                                    <div class="ttv-item-icon icon-ngt">
                                        <i class="material-icons">lunch_dining</i>
                                    </div>
                                    <input type="checkbox" name="cairan_param[]" value="ngt" data-group="cairan">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">NGT</div>
                                        <div class="ttv-item-unit">cc (Output)</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="iwl" data-group="cairan">
                                    <div class="ttv-item-icon icon-iwl">
                                        <i class="material-icons">air</i>
                                    </div>
                                    <input type="checkbox" name="cairan_param[]" value="iwl" data-group="cairan">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">IWL</div>
                                        <div class="ttv-item-unit">Insensible Water Loss</div>
                                    </div>
                                </label>
                                
                                <!-- BALANCE -->
                                <label class="ttv-checkbox-item" data-param="keseimbangan" data-group="cairan">
                                    <div class="ttv-item-icon icon-balance">
                                        <i class="material-icons">balance</i>
                                    </div>
                                    <input type="checkbox" name="cairan_param[]" value="keseimbangan" data-group="cairan">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Keseimbangan</div>
                                        <div class="ttv-item-unit">cc (Balance)</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Toggle Observasi Ventilatori -->
                    <div class="kategori-toggle-card" id="toggleObsVentilator">
                        <div class="kategori-toggle-header">
                            <div class="kategori-toggle-icon" style="background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);">
                                <i class="material-icons">air</i>
                            </div>
                            <div class="kategori-toggle-info">
                                <div class="kategori-toggle-name">🫁 Observasi Ventilatori</div>
                                <div class="kategori-toggle-desc">Tidal Volume, RR, PEEP/PS dari ventilator</div>
                            </div>
                            <label class="kategori-toggle-switch" onclick="event.stopPropagation()">
                                <input type="checkbox" id="chkKategoriVentilator">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="kategori-toggle-content" id="contentKategoriVentilator" style="display: none;">
                            <div class="ttv-selection-grid" id="ventilatorSelectionGrid">
                                <label class="ttv-checkbox-item" data-param="vt" data-group="ventilator">
                                    <div class="ttv-item-icon icon-vt">
                                        <i class="material-icons">waves</i>
                                    </div>
                                    <input type="checkbox" name="ventilator_param[]" value="vt" data-group="ventilator">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Tidal Volume</div>
                                        <div class="ttv-item-unit">ml</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="rr_vent" data-group="ventilator">
                                    <div class="ttv-item-icon icon-rr-vent">
                                        <i class="material-icons">speed</i>
                                    </div>
                                    <input type="checkbox" name="ventilator_param[]" value="rr_vent" data-group="ventilator">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">RR Ventilator</div>
                                        <div class="ttv-item-unit">x/menit</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="peep" data-group="ventilator">
                                    <div class="ttv-item-icon icon-peep">
                                        <i class="material-icons">compress</i>
                                    </div>
                                    <input type="checkbox" name="ventilator_param[]" value="peep" data-group="ventilator">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">PEEP/PS</div>
                                        <div class="ttv-item-unit">cmH₂O</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="fio2" data-group="ventilator">
                                    <div class="ttv-item-icon icon-fio2">
                                        <i class="material-icons">blur_circular</i>
                                    </div>
                                    <input type="checkbox" name="ventilator_param[]" value="fio2" data-group="ventilator">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">FiO₂ / EE</div>
                                        <div class="ttv-item-unit">%</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Toggle Observasi CHBP -->
                    <div class="kategori-toggle-card" id="toggleObsCHBP">
                        <div class="kategori-toggle-header">
                            <div class="kategori-toggle-icon" style="background: linear-gradient(135deg, #EC4899 0%, #DB2777 100%);">
                                <i class="material-icons">monitor_heart</i>
                            </div>
                            <div class="kategori-toggle-info">
                                <div class="kategori-toggle-name">💉 Observasi CHBP</div>
                                <div class="kategori-toggle-desc">TD, Heart Rate, Suhu, DJJ (Obstetri)</div>
                            </div>
                            <label class="kategori-toggle-switch" onclick="event.stopPropagation()">
                                <input type="checkbox" id="chkKategoriCHBP">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="kategori-toggle-content" id="contentKategoriCHBP" style="display: none;">
                            <div class="ttv-selection-grid" id="chbpSelectionGrid">
                                <label class="ttv-checkbox-item" data-param="td_chbp" data-group="chbp">
                                    <div class="ttv-item-icon icon-td-chbp">
                                        <i class="material-icons">favorite</i>
                                    </div>
                                    <input type="checkbox" name="chbp_param[]" value="td_chbp" data-group="chbp">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Tekanan Darah</div>
                                        <div class="ttv-item-unit">mmHg</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="hr_chbp" data-group="chbp">
                                    <div class="ttv-item-icon icon-hr-chbp">
                                        <i class="material-icons">timeline</i>
                                    </div>
                                    <input type="checkbox" name="chbp_param[]" value="hr_chbp" data-group="chbp">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Heart Rate</div>
                                        <div class="ttv-item-unit">x/menit</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="suhu_chbp" data-group="chbp">
                                    <div class="ttv-item-icon icon-suhu-chbp">
                                        <i class="material-icons">thermostat</i>
                                    </div>
                                    <input type="checkbox" name="chbp_param[]" value="suhu_chbp" data-group="chbp">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">Suhu Tubuh</div>
                                        <div class="ttv-item-unit">°Celsius</div>
                                    </div>
                                </label>
                                
                                <label class="ttv-checkbox-item" data-param="djj" data-group="chbp">
                                    <div class="ttv-item-icon icon-djj">
                                        <i class="material-icons">child_care</i>
                                    </div>
                                    <input type="checkbox" name="chbp_param[]" value="djj" data-group="chbp">
                                    <div class="ttv-item-info">
                                        <div class="ttv-item-name">DJJ</div>
                                        <div class="ttv-item-unit">Denyut Jantung Janin</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
            </div><!-- End parameterSelectionContainer -->
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-success" onclick="generateGrafikTTV()">
                    <i class="material-icons" style="font-size: 18px;">show_chart</i>
                    Generate Grafik
                </button>
                <button class="btn btn-secondary" onclick="resetTTVSelection()">
                    <i class="material-icons" style="font-size: 18px;">refresh</i>
                    Reset
                </button>
            </div>
            
            <!-- Grafik Container -->
            <div id="grafikTTVContainer" style="display: none;"></div>
        </div>
    </div>
    
    <?php else: ?>
    
    <div class="empty-state">
        <i class="material-icons">show_chart</i>
        <h4>Belum Ada Data TTV</h4>
        <p>Pasien ini belum memiliki riwayat pemeriksaan vital signs untuk rawat inap</p>
    </div>
    
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<!-- jsPDF & html2canvas for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
(function(){
    'use strict';
    
    const norm = "<?= $norm ?>";
    let ttvChartInstances = []; // Array untuk multiple charts
    let isPanelOpen = false;
    
    // Toggle panel
    window.toggleGrafikTTVPanel = function() {
        const content = $('#grafikTTVPanelContent');
        const btn = $('#btnToggleTTV');
        
        if(isPanelOpen) {
            content.slideUp(300);
            btn.addClass('collapsed');
            btn.find('i').text('expand_more');
            isPanelOpen = false;
        } else {
            content.slideDown(300);
            btn.removeClass('collapsed');
            btn.find('i').text('expand_less');
            isPanelOpen = true;
        }
    };
    
    // Handle mode tampilan change - Parameter selection SELALU tampil untuk semua mode
    $('input[name="mode_tampilan"]').on('change', function() {
        // Close existing charts when mode changes
        closeGrafikTTV();
    });
    
    // Toggle Kategori Tambahan
    window.toggleKategori = function(kategori) {
        if(kategori === 'cairan') {
            const card = $('#toggleBalanceCairan');
            const content = $('#contentKategoriCairan');
            const checkbox = $('#chkKategoriCairan');
            
            if(checkbox.is(':checked')) {
                card.addClass('active');
                content.slideDown(300);
            } else {
                card.removeClass('active');
                content.slideUp(300);
                $('#cairanSelectionGrid input[type="checkbox"]').prop('checked', false);
                $('#cairanSelectionGrid .ttv-checkbox-item').removeClass('selected');
            }
        }
        else if(kategori === 'ventilator') {
            const card = $('#toggleObsVentilator');
            const content = $('#contentKategoriVentilator');
            const checkbox = $('#chkKategoriVentilator');
            
            if(checkbox.is(':checked')) {
                card.addClass('active');
                content.slideDown(300);
            } else {
                card.removeClass('active');
                content.slideUp(300);
                $('#ventilatorSelectionGrid input[type="checkbox"]').prop('checked', false);
                $('#ventilatorSelectionGrid .ttv-checkbox-item').removeClass('selected');
            }
        }
        else if(kategori === 'chbp') {
            const card = $('#toggleObsCHBP');
            const content = $('#contentKategoriCHBP');
            const checkbox = $('#chkKategoriCHBP');
            
            if(checkbox.is(':checked')) {
                card.addClass('active');
                content.slideDown(300);
            } else {
                card.removeClass('active');
                content.slideUp(300);
                $('#chbpSelectionGrid input[type="checkbox"]').prop('checked', false);
                $('#chbpSelectionGrid .ttv-checkbox-item').removeClass('selected');
            }
        }
    };
    
    // Event handler untuk checkbox kategori
    $(document).on('change', '#chkKategoriCairan', function() {
        toggleKategori('cairan');
    });
    
    $(document).on('change', '#chkKategoriVentilator', function() {
        toggleKategori('ventilator');
    });
    
    $(document).on('change', '#chkKategoriCHBP', function() {
        toggleKategori('chbp');
    });
    
    // Klik pada header card juga toggle
    $(document).on('click', '#toggleBalanceCairan .kategori-toggle-header', function(e) {
        if($(e.target).closest('.kategori-toggle-switch').length === 0) {
            const checkbox = $('#chkKategoriCairan');
            checkbox.prop('checked', !checkbox.is(':checked'));
            toggleKategori('cairan');
        }
    });
    
    $(document).on('click', '#toggleObsVentilator .kategori-toggle-header', function(e) {
        if($(e.target).closest('.kategori-toggle-switch').length === 0) {
            const checkbox = $('#chkKategoriVentilator');
            checkbox.prop('checked', !checkbox.is(':checked'));
            toggleKategori('ventilator');
        }
    });
    
    $(document).on('click', '#toggleObsCHBP .kategori-toggle-header', function(e) {
        if($(e.target).closest('.kategori-toggle-switch').length === 0) {
            const checkbox = $('#chkKategoriCHBP');
            checkbox.prop('checked', !checkbox.is(':checked'));
            toggleKategori('chbp');
        }
    });
    
    // Checkbox handler - TTV
    $('#ttvSelectionGrid').on('change', 'input[type="checkbox"]', function() {
        const label = $(this).closest('.ttv-checkbox-item');
        
        if($(this).is(':checked')) {
            label.addClass('selected');
        } else {
            label.removeClass('selected');
        }
    });
    
    // Checkbox handler - Cairan
    $('#cairanSelectionGrid').on('change', 'input[type="checkbox"]', function() {
        const label = $(this).closest('.ttv-checkbox-item');
        
        if($(this).is(':checked')) {
            label.addClass('selected');
        } else {
            label.removeClass('selected');
        }
    });
    
    // Checkbox handler - Ventilator
    $('#ventilatorSelectionGrid').on('change', 'input[type="checkbox"]', function() {
        const label = $(this).closest('.ttv-checkbox-item');
        
        if($(this).is(':checked')) {
            label.addClass('selected');
        } else {
            label.removeClass('selected');
        }
    });
    
    // Checkbox handler - CHBP
    $('#chbpSelectionGrid').on('change', 'input[type="checkbox"]', function() {
        const label = $(this).closest('.ttv-checkbox-item');
        
        if($(this).is(':checked')) {
            label.addClass('selected');
        } else {
            label.removeClass('selected');
        }
    });
    
    // Reset selection
    window.resetTTVSelection = function() {
        // Reset TTV checkboxes - uncheck all
        $('#ttvSelectionGrid input[type="checkbox"]').prop('checked', false);
        $('#ttvSelectionGrid .ttv-checkbox-item').removeClass('selected');
        
        // Reset Cairan checkboxes - uncheck all dan tutup kategori
        $('#cairanSelectionGrid input[type="checkbox"]').prop('checked', false);
        $('#cairanSelectionGrid .ttv-checkbox-item').removeClass('selected');
        $('#chkKategoriCairan').prop('checked', false);
        $('#toggleBalanceCairan').removeClass('active');
        $('#contentKategoriCairan').slideUp(300);
        
        // Reset Ventilator checkboxes - uncheck all dan tutup kategori
        $('#ventilatorSelectionGrid input[type="checkbox"]').prop('checked', false);
        $('#ventilatorSelectionGrid .ttv-checkbox-item').removeClass('selected');
        $('#chkKategoriVentilator').prop('checked', false);
        $('#toggleObsVentilator').removeClass('active');
        $('#contentKategoriVentilator').slideUp(300);
        
        // Reset CHBP checkboxes - uncheck all dan tutup kategori
        $('#chbpSelectionGrid input[type="checkbox"]').prop('checked', false);
        $('#chbpSelectionGrid .ttv-checkbox-item').removeClass('selected');
        $('#chkKategoriCHBP').prop('checked', false);
        $('#toggleObsCHBP').removeClass('active');
        $('#contentKategoriCHBP').slideUp(300);
        
        // Reset filter sumber data
        $('#filterSumberData').val('pemeriksaan_ranap');
        
        // Reset mode tampilan ke gabung
        $('input[name="mode_tampilan"][value="gabung"]').prop('checked', true);
        
        // Close grafik
        closeGrafikTTV();
    };
    
    // Generate Grafik
    window.generateGrafikTTV = function() {
        const modeTampilan = $('input[name="mode_tampilan"]:checked').val();
        const filterNoRawat = $('#filterNoRawatTTV').val();
        const sumberData = $('#filterSumberData').val();
        const rentangWaktu = $('#filterRentangWaktu').val();
        
        // Ambil parameter dari checkbox yang dipilih (untuk SEMUA mode)
        const selectedTTV = $('#ttvSelectionGrid input[type="checkbox"]:checked');
        const selectedCairan = $('#cairanSelectionGrid input[type="checkbox"]:checked');
        const selectedVentilator = $('#ventilatorSelectionGrid input[type="checkbox"]:checked');
        const selectedCHBP = $('#chbpSelectionGrid input[type="checkbox"]:checked');
        
        if(selectedTTV.length === 0 && selectedCairan.length === 0 && selectedVentilator.length === 0 && selectedCHBP.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Pilih Parameter',
                text: 'Pilih minimal 1 parameter untuk membuat grafik',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        let ttvParams = [];
        let cairanParams = [];
        let ventilatorParams = [];
        let chbpParams = [];
        
        selectedTTV.each(function() {
            ttvParams.push($(this).val());
        });
        
        selectedCairan.each(function() {
            cairanParams.push($(this).val());
        });
        
        selectedVentilator.each(function() {
            ventilatorParams.push($(this).val());
        });
        
        selectedCHBP.each(function() {
            chbpParams.push($(this).val());
        });
        
        // Show loading
        $('#grafikTTVContainer').html(`
            <div class="text-center" style="padding:30px;">
                <i class="material-icons spin" style="font-size:40px; color:#10b981;">autorenew</i>
                <div style="margin-top:8px; color: var(--text-secondary);">Membuat grafik...</div>
            </div>
        `).show();
        
        // AJAX request
        $.ajax({
            url: 'pages/get_grafik_ttv_inap.php',
            type: 'POST',
            dataType: 'json',
            data: {
                norm: norm,
                ttv_params: ttvParams,
                cairan_params: cairanParams,
                ventilator_params: ventilatorParams,
                chbp_params: chbpParams,
                filter_norawat: filterNoRawat,
                sumber_data: sumberData,
                rentang_waktu: rentangWaktu,
                mode_tampilan: modeTampilan
            },
            success: function(response) {
                // DEBUG: Log filter info
                if(response.debug_filter) {
                    //console.log('=== DEBUG FILTER ===' );
                    //console.log('Server Time:', response.debug_filter.server_time);
                    //console.log('Filter DateTime:', response.debug_filter.datetime_filter);
                    //console.log('Filter Date:', response.debug_filter.filter_date);
                    //console.log('Filter Time:', response.debug_filter.filter_time);
                    //console.log('Rentang:', response.debug_filter.rentang_waktu);
                    //console.log('Total CHBP:', response.total_chbp);
                    //console.log('===================');
                }
                
                if(response.success) {
                    if(modeTampilan === 'gabung') {
                        renderGrafikGabung(response);
                    } else if(modeTampilan === 'kategori') {
                        renderGrafikKategori(response);
                    } else if(modeTampilan === 'parameter') {
                        renderGrafikParameter(response);
                    }
                } else {
                    $('#grafikTTVContainer').html(`
                        <div class="text-center" style="padding: 30px; color: var(--text-secondary);">
                            <i class="material-icons" style="font-size: 48px; opacity: 0.5;">info</i>
                            <p style="margin-top: 10px;">${response.message || 'Tidak ada data untuk ditampilkan'}</p>
                            <p style="font-size: 12px; margin-top: 5px;">(Minimal 2 data untuk membuat grafik)</p>
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                $('#grafikTTVContainer').html(`
                    <div class="text-center" style="padding: 30px; color: var(--danger);">
                        <i class="material-icons" style="font-size: 48px;">error</i>
                        <p style="margin-top: 10px;">Terjadi kesalahan saat memuat data</p>
                        <small>${error}</small>
                    </div>
                `);
            }
        });
    };
    
    // Render Grafik Mode GABUNG
    function renderGrafikGabung(response) {
        const { datasets, filter_info: filterInfo, latest, sumber_info: sumberInfo, diagnosa, rentang_info: rentangInfo } = response;
        
        // Destroy previous charts
        destroyAllCharts();
        
        // Determine which categories are selected
        const hasTTV = datasets.some(ds => ['sistolik','diastolik','nadi','suhu','respirasi','spo2','gcs'].includes(ds.key));
        const hasCairan = datasets.some(ds => ['infus','tranfusi','minum','urine','drain','ngt','iwl','keseimbangan'].includes(ds.key));
        const hasVentilator = datasets.some(ds => ['vt','rr_vent','peep','fio2'].includes(ds.key));
        const hasCHBP = datasets.some(ds => ['sistolik_chbp','diastolik_chbp','hr_chbp','suhu_chbp','djj'].includes(ds.key));
        
        // Build dynamic title
        let titleParts = [];
        if(hasTTV) titleParts.push('TTV');
        if(hasCairan) titleParts.push('Balance Cairan');
        if(hasVentilator) titleParts.push('Ventilator');
        if(hasCHBP) titleParts.push('CHBP');
        const dynamicTitle = 'Grafik ' + (titleParts.length > 0 ? titleParts.join(', ') : 'Pemeriksaan');
        
        // Show sumber TTV only if TTV is selected
        const showSumberTTV = hasTTV;
        
        // Build diagnosa HTML
        let diagnosaHtml = '';
        if(diagnosa && diagnosa.length > 0) {
            // Pisahkan primer dan sekunder
            const primer = diagnosa.filter(d => d.prioritas == 1);
            const sekunder = diagnosa.filter(d => d.prioritas > 1);
            
            // Function untuk render diagnosa item
            const renderItem = (d) => {
                const statusBadge = d.status === 'Ranap' ? 
                    '<span style="background:#fee2e2; color:#991b1b; padding:2px 6px; border-radius:4px; font-size:9px; font-weight:600;">RANAP</span>' : 
                    '<span style="background:#dcfce7; color:#166534; padding:2px 6px; border-radius:4px; font-size:9px; font-weight:600;">RALAN</span>';
                return `<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:12px;">
                    <strong style="color:#92400e; min-width:50px;">${d.kode}</strong> 
                    <span style="color:#78350f; flex:1;">${d.nama}</span>
                    ${statusBadge}
                </div>`;
            };
            
            // Build primer column
            let primerHtml = `
                <div style="flex:1; min-width:200px;">
                    <div style="font-size: 10px; font-weight: 700; color: #dc2626; text-transform: uppercase; margin-bottom: 8px;">
                        <span style="background:#fef2f2; color:#dc2626; padding:3px 10px; border-radius:4px;">● PRIMER</span>
                    </div>
                    ${primer.length > 0 ? primer.map(renderItem).join('') : '<div style="color:#9ca3af; font-size:12px; font-style:italic;">Tidak ada</div>'}
                </div>
            `;
            
            // Build sekunder column
            let sekunderHtml = `
                <div style="flex:1; min-width:200px;">
                    <div style="font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 8px;">
                        <span style="background:#f3f4f6; color:#6b7280; padding:3px 10px; border-radius:4px;">○ SEKUNDER (${sekunder.length})</span>
                    </div>
                    ${sekunder.length > 0 ? sekunder.map(renderItem).join('') : '<div style="color:#9ca3af; font-size:12px; font-style:italic;">Tidak ada</div>'}
                </div>
            `;
            
            diagnosaHtml = `
                <div style="margin-top: 10px; padding: 14px 16px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 8px; border-left: 4px solid #f59e0b;">
                    <div style="font-size: 11px; font-weight: 600; color: #92400e; text-transform: uppercase; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="material-icons" style="font-size: 16px;">medical_information</i>
                        Diagnosa (${diagnosa.length})
                    </div>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                        ${primerHtml}
                        ${sekunderHtml}
                    </div>
                </div>
            `;
        }
        
        // Build HTML
        let html = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h5 style="margin: 0; font-size: 16px; font-weight: 600;">${dynamicTitle}</h5>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">badge</i> 
                        No. Rawat: <strong>${filterInfo}</strong>
                    </p>
                    ${showSumberTTV ? `
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">storage</i> 
                        Sumber TTV: ${sumberInfo || 'Pemeriksaan Ranap'}
                    </p>` : ''}
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">date_range</i> 
                        Rentang: <strong>${rentangInfo || 'Semua Data'}</strong>
                    </p>
                    ${diagnosaHtml}
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-success btn-sm" onclick="downloadGrafikPDF()">
                        <i class="material-icons" style="font-size: 16px;">picture_as_pdf</i>
                        Download PDF
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="closeGrafikTTV()">
                        <i class="material-icons" style="font-size: 16px;">close</i>
                        Tutup Grafik
                    </button>
                </div>
            </div>
        `;
        
        // Latest values cards
        if(latest && Object.keys(latest).length > 0) {
            html += '<div class="info-cards">';
            
            const paramLabels = {
                'sistolik': { label: 'Sistolik', unit: 'mmHg' },
                'diastolik': { label: 'Diastolik', unit: 'mmHg' },
                'nadi': { label: 'Nadi', unit: 'x/mnt' },
                'suhu': { label: 'Suhu', unit: '°C' },
                'respirasi': { label: 'RR', unit: 'x/mnt' },
                'spo2': { label: 'SpO₂', unit: '%' },
                'gcs': { label: 'GCS', unit: '' },
                'infus': { label: 'Infus', unit: 'cc' },
                'tranfusi': { label: 'Tranfusi', unit: 'cc' },
                'minum': { label: 'Minum', unit: 'cc' },
                'urine': { label: 'Urine', unit: 'cc' },
                'drain': { label: 'Drain', unit: 'cc' },
                'ngt': { label: 'NGT', unit: 'cc' },
                'iwl': { label: 'IWL', unit: 'cc' },
                'keseimbangan': { label: 'Balance', unit: 'cc' }
            };
            
            for(const [key, value] of Object.entries(latest)) {
                const info = paramLabels[key] || { label: key, unit: '' };
                html += `
                    <div class="info-card ${key}">
                        <div class="info-card-label">${info.label}</div>
                        <div class="info-card-value">${value} <small style="font-size: 10px; font-weight: 400;">${info.unit}</small></div>
                    </div>
                `;
            }
            
            html += '</div>';
        }
        
        html += '<canvas id="ttvChart" height="100"></canvas>';
        
        $('#grafikTTVContainer').html(html).show();
        
        // Prepare Chart.js datasets
        const chartDatasets = prepareChartDatasets(datasets);
        
        // Labels (tanggal/waktu)
        const labels = datasets[0]?.data.map(d => d.x) || [];
        
        // Create chart
        const ctx = document.getElementById('ttvChart').getContext('2d');
        const chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: chartDatasets
            },
            options: chartOptions
        });
        
        ttvChartInstances.push(chartInstance);
        
        // Scroll to chart
        setTimeout(function() {
            $('#grafikTTVContainer')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    }
    
    // Render Grafik Mode KATEGORI (TTV, Balance Cairan, Ventilator, CHBP terpisah)
    function renderGrafikKategori(response) {
        const { datasets, filter_info: filterInfo, latest, sumber_info: sumberInfo, diagnosa, rentang_info: rentangInfo, datasets_ttv, datasets_cairan, datasets_ventilator, datasets_chbp } = response;
        
        // Destroy previous charts
        destroyAllCharts();
        
        // Determine which categories are selected
        const hasTTV = datasets_ttv && datasets_ttv.length > 0;
        const hasCairan = datasets_cairan && datasets_cairan.length > 0;
        const hasVentilator = datasets_ventilator && datasets_ventilator.length > 0;
        const hasCHBP = datasets_chbp && datasets_chbp.length > 0;
        
        // Build dynamic title
        let titleParts = [];
        if(hasTTV) titleParts.push('TTV');
        if(hasCairan) titleParts.push('Balance Cairan');
        if(hasVentilator) titleParts.push('Ventilator');
        if(hasCHBP) titleParts.push('CHBP');
        const dynamicTitle = 'Grafik ' + (titleParts.length > 0 ? titleParts.join(', ') : 'Pemeriksaan') + ' (Mode Kategori)';
        
        // Show sumber TTV only if TTV is selected
        const showSumberTTV = hasTTV;
        
        // Build diagnosa HTML
        let diagnosaHtml = buildDiagnosaHtml(diagnosa);
        
        // Build header HTML
        let html = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h5 style="margin: 0; font-size: 16px; font-weight: 600;">${dynamicTitle}</h5>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">badge</i> 
                        No. Rawat: <strong>${filterInfo}</strong>
                    </p>
                    ${showSumberTTV ? `
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">storage</i> 
                        Sumber TTV: ${sumberInfo || 'Pemeriksaan Ranap'}
                    </p>` : ''}
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">date_range</i> 
                        Rentang: <strong>${rentangInfo || 'Semua Data'}</strong>
                    </p>
                    ${diagnosaHtml}
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-success btn-sm" onclick="downloadGrafikPDF()">
                        <i class="material-icons" style="font-size: 16px;">picture_as_pdf</i>
                        Download PDF
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="closeGrafikTTV()">
                        <i class="material-icons" style="font-size: 16px;">close</i>
                        Tutup Grafik
                    </button>
                </div>
            </div>
        `;
        
        // Grafik TTV
        if(datasets_ttv && datasets_ttv.length > 0) {
            html += `
                <div class="chart-section" style="margin-bottom: 30px; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <h6 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #dc2626; display: flex; align-items: center; gap: 8px;">
                        <i class="material-icons" style="font-size: 20px;">favorite</i>
                        Grafik Tanda-Tanda Vital (TTV)
                    </h6>
                    ${buildLatestCards(latest, ['sistolik', 'diastolik', 'nadi', 'suhu', 'respirasi', 'spo2', 'gcs'])}
                    <canvas id="ttvChartKategori" height="80"></canvas>
                </div>
            `;
        }
        
        // Grafik Balance Cairan
        if(datasets_cairan && datasets_cairan.length > 0) {
            html += `
                <div class="chart-section" style="margin-bottom: 30px; padding: 20px; background: #f0fdf4; border-radius: 12px; border: 1px solid #bbf7d0;">
                    <h6 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #059669; display: flex; align-items: center; gap: 8px;">
                        <i class="material-icons" style="font-size: 20px;">opacity</i>
                        Grafik Balance Cairan (Intake/Output)
                    </h6>
                    ${buildLatestCards(latest, ['infus', 'tranfusi', 'minum', 'urine', 'drain', 'ngt', 'iwl', 'keseimbangan'])}
                    <canvas id="cairanChartKategori" height="80"></canvas>
                </div>
            `;
        }
        
        // Grafik Ventilator
        if(datasets_ventilator && datasets_ventilator.length > 0) {
            html += `
                <div class="chart-section" style="margin-bottom: 30px; padding: 20px; background: #eef2ff; border-radius: 12px; border: 1px solid #c7d2fe;">
                    <h6 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #6366f1; display: flex; align-items: center; gap: 8px;">
                        <i class="material-icons" style="font-size: 20px;">air</i>
                        Grafik Observasi Ventilator
                    </h6>
                    ${buildLatestCards(latest, ['vt', 'rr_vent', 'peep', 'fio2'])}
                    <canvas id="ventilatorChartKategori" height="80"></canvas>
                </div>
            `;
        }
        
        // Grafik CHBP (Obstetri)
        if(datasets_chbp && datasets_chbp.length > 0) {
            html += `
                <div class="chart-section" style="padding: 20px; background: #fdf2f8; border-radius: 12px; border: 1px solid #fbcfe8;">
                    <h6 style="margin: 0 0 15px 0; font-size: 14px; font-weight: 600; color: #ec4899; display: flex; align-items: center; gap: 8px;">
                        <i class="material-icons" style="font-size: 20px;">monitor_heart</i>
                        Grafik Observasi CHBP (Obstetri)
                    </h6>
                    ${buildLatestCards(latest, ['sistolik_chbp', 'diastolik_chbp', 'hr_chbp', 'suhu_chbp', 'djj'])}
                    <canvas id="chbpChartKategori" height="80"></canvas>
                </div>
            `;
        }
        
        $('#grafikTTVContainer').html(html).show();
        
        // Render TTV Chart
        if(datasets_ttv && datasets_ttv.length > 0) {
            const labels = datasets_ttv[0]?.data.map(d => d.x) || [];
            const chartDatasets = prepareChartDatasets(datasets_ttv);
            const ctx = document.getElementById('ttvChartKategori').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets: chartDatasets },
                options: chartOptions
            });
            ttvChartInstances.push(chart);
        }
        
        // Render Cairan Chart
        if(datasets_cairan && datasets_cairan.length > 0) {
            const labels = datasets_cairan[0]?.data.map(d => d.x) || [];
            const chartDatasets = prepareChartDatasets(datasets_cairan);
            const ctx = document.getElementById('cairanChartKategori').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets: chartDatasets },
                options: chartOptions
            });
            ttvChartInstances.push(chart);
        }
        
        // Render Ventilator Chart
        if(datasets_ventilator && datasets_ventilator.length > 0) {
            const labels = datasets_ventilator[0]?.data.map(d => d.x) || [];
            const chartDatasets = prepareChartDatasets(datasets_ventilator);
            const ctx = document.getElementById('ventilatorChartKategori').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets: chartDatasets },
                options: chartOptions
            });
            ttvChartInstances.push(chart);
        }
        
        // Render CHBP Chart
        if(datasets_chbp && datasets_chbp.length > 0) {
            const labels = datasets_chbp[0]?.data.map(d => d.x) || [];
            const chartDatasets = prepareChartDatasets(datasets_chbp);
            const ctx = document.getElementById('chbpChartKategori').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets: chartDatasets },
                options: chartOptions
            });
            ttvChartInstances.push(chart);
        }
        
        // Scroll to chart
        setTimeout(function() {
            $('#grafikTTVContainer')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    }
    
    // Render Grafik Mode PARAMETER (setiap parameter terpisah, KECUALI Tekanan Darah digabung)
    function renderGrafikParameter(response) {
        const { datasets, filter_info: filterInfo, latest, sumber_info: sumberInfo, diagnosa, rentang_info: rentangInfo } = response;
        
        // Destroy previous charts
        destroyAllCharts();
        
        // Determine which categories are selected
        const hasTTV = datasets.some(ds => ['sistolik','diastolik','nadi','suhu','respirasi','spo2','gcs'].includes(ds.key));
        const hasCairan = datasets.some(ds => ['infus','tranfusi','minum','urine','drain','ngt','iwl','keseimbangan'].includes(ds.key));
        const hasVentilator = datasets.some(ds => ['vt','rr_vent','peep','fio2'].includes(ds.key));
        const hasCHBP = datasets.some(ds => ['sistolik_chbp','diastolik_chbp','hr_chbp','suhu_chbp','djj'].includes(ds.key));
        
        // Build dynamic title
        let titleParts = [];
        if(hasTTV) titleParts.push('TTV');
        if(hasCairan) titleParts.push('Balance Cairan');
        if(hasVentilator) titleParts.push('Ventilator');
        if(hasCHBP) titleParts.push('CHBP');
        const dynamicTitle = 'Grafik ' + (titleParts.length > 0 ? titleParts.join(', ') : 'Pemeriksaan') + ' (Mode Parameter)';
        
        // Show sumber TTV only if TTV is selected
        const showSumberTTV = hasTTV;
        
        // Build diagnosa HTML
        let diagnosaHtml = buildDiagnosaHtml(diagnosa);
        
        // === GROUPING: Gabungkan Sistolik+Diastolik (TTV) dan Sistolik+Diastolik (CHBP) ===
        const groupedDatasets = [];
        const tdTTV = { sistolik: null, diastolik: null };
        const tdCHBP = { sistolik: null, diastolik: null };
        
        datasets.forEach(ds => {
            if(ds.key === 'sistolik') {
                tdTTV.sistolik = ds;
            } else if(ds.key === 'diastolik') {
                tdTTV.diastolik = ds;
            } else if(ds.key === 'sistolik_chbp') {
                tdCHBP.sistolik = ds;
            } else if(ds.key === 'diastolik_chbp') {
                tdCHBP.diastolik = ds;
            } else {
                // Parameter lain tetap individual
                groupedDatasets.push({ type: 'single', data: ds });
            }
        });
        
        // Tambahkan TD TTV sebagai group (jika ada salah satu atau keduanya)
        if(tdTTV.sistolik || tdTTV.diastolik) {
            groupedDatasets.unshift({ type: 'td_ttv', sistolik: tdTTV.sistolik, diastolik: tdTTV.diastolik });
        }
        
        // Tambahkan TD CHBP sebagai group (jika ada salah satu atau keduanya)
        if(tdCHBP.sistolik || tdCHBP.diastolik) {
            groupedDatasets.push({ type: 'td_chbp', sistolik: tdCHBP.sistolik, diastolik: tdCHBP.diastolik });
        }
        
        // Build header HTML
        let html = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h5 style="margin: 0; font-size: 16px; font-weight: 600;">${dynamicTitle}</h5>
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">badge</i> 
                        No. Rawat: <strong>${filterInfo}</strong>
                    </p>
                    ${showSumberTTV ? `
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">storage</i> 
                        Sumber TTV: ${sumberInfo || 'Pemeriksaan Ranap'}
                    </p>` : ''}
                    <p style="margin: 2px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">date_range</i> 
                        Rentang: <strong>${rentangInfo || 'Semua Data'}</strong>
                    </p>
                    ${diagnosaHtml}
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-success btn-sm" onclick="downloadGrafikPDF()">
                        <i class="material-icons" style="font-size: 16px;">picture_as_pdf</i>
                        Download PDF
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="closeGrafikTTV()">
                        <i class="material-icons" style="font-size: 16px;">close</i>
                        Tutup Grafik
                    </button>
                </div>
            </div>
            <div class="charts-grid" style="display: flex; flex-direction: column; gap: 24px;">
        `;
        
        // Generate chart untuk setiap grouped dataset
        groupedDatasets.forEach((group, idx) => {
            if(group.type === 'td_ttv') {
                // Tekanan Darah TTV (Sistolik + Diastolik dalam 1 grafik)
                html += `
                    <div class="chart-wrapper" style="padding: 20px; background: #fef2f2; border-radius: 12px; border: 1px solid #fecaca;">
                        <h6 style="margin: 0 0 15px 0; font-size: 15px; font-weight: 600; color: #DC2626; display: flex; align-items: center; gap: 8px;">
                            <span style="width: 14px; height: 14px; background: linear-gradient(135deg, #DC2626, #EA580C); border-radius: 50%; display: inline-block;"></span>
                            Tekanan Darah (mmHg)
                        </h6>
                        <div class="chart-container">
                            <canvas id="paramChart_${idx}" height="70"></canvas>
                        </div>
                    </div>
                `;
            } else if(group.type === 'td_chbp') {
                // Tekanan Darah CHBP (Sistolik + Diastolik dalam 1 grafik)
                html += `
                    <div class="chart-wrapper" style="padding: 20px; background: #fdf2f8; border-radius: 12px; border: 1px solid #fbcfe8;">
                        <h6 style="margin: 0 0 15px 0; font-size: 15px; font-weight: 600; color: #EC4899; display: flex; align-items: center; gap: 8px;">
                            <span style="width: 14px; height: 14px; background: linear-gradient(135deg, #EC4899, #F472B6); border-radius: 50%; display: inline-block;"></span>
                            Tekanan Darah CHBP (mmHg)
                        </h6>
                        <div class="chart-container">
                            <canvas id="paramChart_${idx}" height="70"></canvas>
                        </div>
                    </div>
                `;
            } else {
                // Parameter tunggal
                const ds = group.data;
                const color = colors[ds.key] || '#667eea';
                const bgColor = ['infus','tranfusi','minum','urine','drain','ngt','iwl','keseimbangan'].includes(ds.key) ? '#f0fdf4' : 
                               ['vt','rr_vent','peep','fio2'].includes(ds.key) ? '#eef2ff' :
                               ['hr_chbp','suhu_chbp','djj'].includes(ds.key) ? '#fdf2f8' : '#f8fafc';
                const borderColor = ['infus','tranfusi','minum','urine','drain','ngt','iwl','keseimbangan'].includes(ds.key) ? '#bbf7d0' :
                                   ['vt','rr_vent','peep','fio2'].includes(ds.key) ? '#c7d2fe' :
                                   ['hr_chbp','suhu_chbp','djj'].includes(ds.key) ? '#fbcfe8' : '#e2e8f0';
                
                html += `
                    <div class="chart-wrapper" style="padding: 20px; background: ${bgColor}; border-radius: 12px; border: 1px solid ${borderColor};">
                        <h6 style="margin: 0 0 15px 0; font-size: 15px; font-weight: 600; color: ${color}; display: flex; align-items: center; gap: 8px;">
                            <span style="width: 14px; height: 14px; background: ${color}; border-radius: 50%; display: inline-block;"></span>
                            ${ds.label}
                        </h6>
                        <div class="chart-container">
                            <canvas id="paramChart_${idx}" height="70"></canvas>
                        </div>
                    </div>
                `;
            }
        });
        
        html += '</div>';
        
        $('#grafikTTVContainer').html(html).show();
        
        // Render each chart
        groupedDatasets.forEach((group, idx) => {
            const ctx = document.getElementById(`paramChart_${idx}`).getContext('2d');
            
            if(group.type === 'td_ttv' || group.type === 'td_chbp') {
                // Gabungkan Sistolik & Diastolik
                const combinedDatasets = [];
                let labels = [];
                
                if(group.sistolik) {
                    labels = group.sistolik.data.map(d => d.x);
                    combinedDatasets.push(...prepareChartDatasets([group.sistolik]));
                }
                if(group.diastolik) {
                    if(labels.length === 0) labels = group.diastolik.data.map(d => d.x);
                    combinedDatasets.push(...prepareChartDatasets([group.diastolik]));
                }
                
                const chart = new Chart(ctx, {
                    type: 'line',
                    data: { labels, datasets: combinedDatasets },
                    options: {
                        ...chartOptions,
                        plugins: {
                            ...chartOptions.plugins,
                            legend: { display: true, position: 'top' }
                        }
                    }
                });
                ttvChartInstances.push(chart);
            } else {
                // Parameter tunggal
                const ds = group.data;
                const labels = ds.data.map(d => d.x);
                const chartDatasets = prepareChartDatasets([ds]);
                
                const chart = new Chart(ctx, {
                    type: 'line',
                    data: { labels, datasets: chartDatasets },
                    options: {
                        ...chartOptions,
                        plugins: {
                            ...chartOptions.plugins,
                            legend: { display: false }
                        }
                    }
                });
                ttvChartInstances.push(chart);
            }
        });
        
        // Scroll to chart
        setTimeout(function() {
            $('#grafikTTVContainer')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    }
    
    // Helper: Build diagnosa HTML (2 kolom - sama untuk semua mode)
    function buildDiagnosaHtml(diagnosa) {
        if(!diagnosa || diagnosa.length === 0) return '';
        
        const primer = diagnosa.filter(d => d.prioritas == 1);
        const sekunder = diagnosa.filter(d => d.prioritas > 1);
        
        // Function untuk render diagnosa item
        const renderItem = (d) => {
            const statusBadge = d.status === 'Ranap' ? 
                '<span style="background:#fee2e2; color:#991b1b; padding:2px 6px; border-radius:4px; font-size:9px; font-weight:600;">RANAP</span>' : 
                '<span style="background:#dcfce7; color:#166534; padding:2px 6px; border-radius:4px; font-size:9px; font-weight:600;">RALAN</span>';
            return `<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px; font-size:12px;">
                <strong style="color:#92400e; min-width:50px;">${d.kode}</strong> 
                <span style="color:#78350f; flex:1;">${d.nama}</span>
                ${statusBadge}
            </div>`;
        };
        
        // Build primer column
        let primerHtml = `
            <div style="flex:1; min-width:200px;">
                <div style="font-size: 10px; font-weight: 700; color: #dc2626; text-transform: uppercase; margin-bottom: 8px;">
                    <span style="background:#fef2f2; color:#dc2626; padding:3px 10px; border-radius:4px;">● PRIMER</span>
                </div>
                ${primer.length > 0 ? primer.map(renderItem).join('') : '<div style="color:#9ca3af; font-size:12px; font-style:italic;">Tidak ada</div>'}
            </div>
        `;
        
        // Build sekunder column
        let sekunderHtml = `
            <div style="flex:1; min-width:200px;">
                <div style="font-size: 10px; font-weight: 700; color: #6b7280; text-transform: uppercase; margin-bottom: 8px;">
                    <span style="background:#f3f4f6; color:#6b7280; padding:3px 10px; border-radius:4px;">○ SEKUNDER (${sekunder.length})</span>
                </div>
                ${sekunder.length > 0 ? sekunder.map(renderItem).join('') : '<div style="color:#9ca3af; font-size:12px; font-style:italic;">Tidak ada</div>'}
            </div>
        `;
        
        return `
            <div style="margin-top: 10px; padding: 14px 16px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 8px; border-left: 4px solid #f59e0b;">
                <div style="font-size: 11px; font-weight: 600; color: #92400e; text-transform: uppercase; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="material-icons" style="font-size: 16px;">medical_information</i>
                    Diagnosa (${diagnosa.length})
                </div>
                <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                    ${primerHtml}
                    ${sekunderHtml}
                </div>
            </div>
        `;
    }
    
    // Helper: Build latest cards
    function buildLatestCards(latest, keys) {
        if(!latest) return '';
        
        const paramLabels = {
            'sistolik': { label: 'Sistolik', unit: 'mmHg' },
            'diastolik': { label: 'Diastolik', unit: 'mmHg' },
            'nadi': { label: 'Nadi', unit: 'x/mnt' },
            'suhu': { label: 'Suhu', unit: '°C' },
            'respirasi': { label: 'RR', unit: 'x/mnt' },
            'spo2': { label: 'SpO₂', unit: '%' },
            'gcs': { label: 'GCS', unit: '' },
            'infus': { label: 'Infus', unit: 'cc' },
            'tranfusi': { label: 'Tranfusi', unit: 'cc' },
            'minum': { label: 'Minum', unit: 'cc' },
            'urine': { label: 'Urine', unit: 'cc' },
            'drain': { label: 'Drain', unit: 'cc' },
            'ngt': { label: 'NGT', unit: 'cc' },
            'iwl': { label: 'IWL', unit: 'cc' },
            'keseimbangan': { label: 'Balance', unit: 'cc' },
            // Ventilator
            'vt': { label: 'Tidal Vol', unit: 'ml' },
            'rr_vent': { label: 'RR Vent', unit: 'x/mnt' },
            'peep': { label: 'PEEP/PS', unit: 'cmH₂O' },
            'fio2': { label: 'FiO₂', unit: '%' },
            // CHBP
            'sistolik_chbp': { label: 'Sis (CHBP)', unit: 'mmHg' },
            'diastolik_chbp': { label: 'Dia (CHBP)', unit: 'mmHg' },
            'hr_chbp': { label: 'HR (CHBP)', unit: 'x/mnt' },
            'suhu_chbp': { label: 'Suhu (CHBP)', unit: '°C' },
            'djj': { label: 'DJJ', unit: 'x/mnt' }
        };
        
        let html = '<div class="info-cards" style="margin-bottom: 15px;">';
        keys.forEach(key => {
            if(latest[key] !== undefined) {
                const info = paramLabels[key] || { label: key, unit: '' };
                html += `
                    <div class="info-card ${key}">
                        <div class="info-card-label">${info.label}</div>
                        <div class="info-card-value">${latest[key]} <small style="font-size: 10px; font-weight: 400;">${info.unit}</small></div>
                    </div>
                `;
            }
        });
        html += '</div>';
        return html;
    }
    
    // Helper: Prepare chart datasets
    function prepareChartDatasets(datasets) {
        return datasets.map(ds => ({
            label: ds.label,
            data: ds.data.map(d => d.y),
            borderColor: colors[ds.key] || '#667eea',
            backgroundColor: (colors[ds.key] || '#667eea') + '20',
            borderWidth: 3,
            pointRadius: 6,
            pointHoverRadius: 9,
            pointBackgroundColor: ds.data.map(d => {
                if(d.status === 'high') return '#DC2626';
                if(d.status === 'low') return '#F59E0B';
                return colors[ds.key] || '#667eea';
            }),
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            tension: 0.3,
            fill: false,
            rawData: ds.data
        }));
    }
    
    // Helper: Destroy all charts
    function destroyAllCharts() {
        ttvChartInstances.forEach(chart => {
            if(chart) chart.destroy();
        });
        ttvChartInstances = [];
    }
    
    // Close grafik
    window.closeGrafikTTV = function() {
        destroyAllCharts();
        $('#grafikTTVContainer').slideUp(300);
    };
    
    // Color palette - Kontras tinggi untuk kemudahan baca
    const colors = {
        // TTV - Warna sangat berbeda satu sama lain
        'sistolik': '#DC2626',
        'diastolik': '#EA580C',
        'nadi': '#7C3AED',
        'suhu': '#CA8A04',
        'respirasi': '#2563EB',
        'spo2': '#16A34A',
        'gcs': '#475569',
        // Balance Cairan
        'infus': '#0891B2',
        'tranfusi': '#BE185D',
        'minum': '#0EA5E9',
        'urine': '#D97706',
        'drain': '#78716C',
        'ngt': '#57534E',
        'iwl': '#A855F7',
        'keseimbangan': '#059669',
        // Ventilator
        'vt': '#6366F1',
        'rr_vent': '#8B5CF6',
        'peep': '#A855F7',
        'fio2': '#C084FC',
        // CHBP (Obstetri)
        'sistolik_chbp': '#EC4899',
        'diastolik_chbp': '#F472B6',
        'hr_chbp': '#F472B6',
        'suhu_chbp': '#FB7185',
        'djj': '#FDA4AF'
    };
    
    // Chart options
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 20,
                    font: { size: 13, weight: '600' },
                    boxWidth: 12,
                    boxHeight: 12
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                padding: 12,
                titleFont: { size: 13, weight: 'bold' },
                bodyFont: { size: 12 },
                callbacks: {
                    label: function(context) {
                        const rawData = context.dataset.rawData[context.dataIndex];
                        let label = context.dataset.label + ': ' + rawData.y;
                        if(rawData.status === 'high') label += ' ⚠️ TINGGI';
                        else if(rawData.status === 'low') label += ' ⚠️ RENDAH';
                        return label;
                    },
                    afterLabel: function(context) {
                        const rawData = context.dataset.rawData[context.dataIndex];
                        return rawData.petugas ? 'Petugas: ' + rawData.petugas : '';
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { maxRotation: 45, minRotation: 45, font: { size: 12, weight: '500' } }
            },
            y: {
                beginAtZero: false,
                grid: { color: 'rgba(0,0,0,0.08)' },
                ticks: { font: { size: 12, weight: '500' } }
            }
        }
    };
    
    // Download Grafik ke PDF
    window.downloadGrafikPDF = async function() {
        const { jsPDF } = window.jspdf;
        
        // Show loading
        Swal.fire({
            title: 'Membuat PDF...',
            text: 'Mohon tunggu sebentar',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        try {
            // Temporarily hide buttons for clean PDF
            const containerEl = document.getElementById('grafikTTVContainer');
            const buttons = containerEl.querySelectorAll('button');
            buttons.forEach(btn => btn.style.display = 'none');
            
            // A4 dimensions
            const imgWidth = 210; // A4 width in mm
            const pageHeight = 297; // A4 height in mm
            
            // Create PDF
            const pdf = new jsPDF('p', 'mm', 'a4');
            
            // Get patient and filter info
            const noRawat = $('#filterNoRawatTTV').val() || '-';
            const rentangText = $('#filterRentangWaktu option:selected').text().replace(/[^\w\s\/]/g, '').trim() || '-';
            const sumberText = $('#filterSumberData option:selected').text().replace(/[^\w\s\(\)\/]/g, '').trim() || '-';
            const namaPasien = '<?= addslashes($nama_pasien ?? "") ?>' || '-';
            const noRM = '<?= addslashes($norm ?? "") ?>' || '-';
            const umurPasien = '<?= addslashes($umur_pasien ?? "") ?>' || '-';
            const jenisKelamin = '<?= addslashes($jenis_kelamin ?? "") ?>' || '-';
            
            // Check which categories are selected
            const hasTTV = $('#ttvSelectionGrid input[type="checkbox"]:checked').length > 0;
            const hasCairan = $('#cairanSelectionGrid input[type="checkbox"]:checked').length > 0;
            const hasVentilator = $('#ventilatorSelectionGrid input[type="checkbox"]:checked').length > 0;
            const hasCHBP = $('#chbpSelectionGrid input[type="checkbox"]:checked').length > 0;
            
            // Build dynamic title
            let titleParts = [];
            if(hasTTV) titleParts.push('TTV');
            if(hasCairan) titleParts.push('BALANCE CAIRAN');
            if(hasVentilator) titleParts.push('VENTILATOR');
            if(hasCHBP) titleParts.push('CHBP');
            const pdfTitle = 'GRAFIK ' + (titleParts.length > 0 ? titleParts.join(', ') : 'PEMERIKSAAN');
            
            // Add header
            pdf.setFontSize(16);
            pdf.setFont('helvetica', 'bold');
            pdf.text(pdfTitle, 105, 15, { align: 'center' });
            
            // Add line separator
            pdf.setDrawColor(200);
            pdf.line(14, 20, 196, 20);
            
            // Patient info section
            pdf.setFontSize(10);
            pdf.setFont('helvetica', 'bold');
            pdf.text('DATA PASIEN', 14, 28);
            
            pdf.setFont('helvetica', 'normal');
            pdf.text(`Nama Pasien  : ${namaPasien}`, 14, 34);
            pdf.text(`No. RM       : ${noRM}`, 14, 39);
            pdf.text(`Umur/JK      : ${umurPasien} / ${jenisKelamin}`, 14, 44);
            pdf.text(`No. Rawat    : ${noRawat}`, 14, 49);
            
            // Filter info (right column)
            pdf.setFont('helvetica', 'bold');
            pdf.text('FILTER', 120, 28);
            
            pdf.setFont('helvetica', 'normal');
            pdf.text(`Rentang Waktu : ${rentangText}`, 120, 34);
            
            // Only show Sumber TTV if TTV category is selected
            let filterY = 39;
            if(hasTTV) {
                pdf.text(`Sumber TTV    : ${sumberText}`, 120, filterY);
                filterY += 5;
            }
            
            const now = new Date();
            const dateStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
            const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            pdf.text(`Dicetak       : ${dateStr}, ${timeStr}`, 120, filterY);
            
            // Add line separator
            pdf.setDrawColor(200);
            pdf.line(14, 53, 196, 53);
            
            // === STRATEGI: Render setiap grafik terpisah, 3 per halaman ===
            const container = containerEl;
            
            // Ambil semua chart canvas
            const chartCanvases = container.querySelectorAll('canvas');
            
            // Jika tidak ada grafik individual, gunakan cara lama (capture semua)
            if(chartCanvases.length === 0) {
                // Fallback: capture seluruh container
                const canvas = await html2canvas(container, {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff'
                });
                
                const imgData = canvas.toDataURL('image/jpeg', 0.95);
                const imgHeight = (canvas.height * (imgWidth - 10)) / canvas.width;
                pdf.addImage(imgData, 'JPEG', 5, 57, imgWidth - 10, imgHeight);
            } else {
                // === Render setiap grafik, 3 per halaman ===
                const chartsPerPage = 3;
                const chartHeight = 70; // mm per grafik
                const chartGap = 5; // mm gap antar grafik
                let currentY = 57; // Start setelah header
                let chartOnPage = 0;
                
                for(let i = 0; i < chartCanvases.length; i++) {
                    // Jika sudah 3 grafik di halaman ini, buat halaman baru
                    if(chartOnPage >= chartsPerPage) {
                        pdf.addPage();
                        currentY = 15; // Reset Y untuk halaman baru
                        chartOnPage = 0;
                    }
                    
                    const chartCanvas = chartCanvases[i];
                    
                    // Ambil parent untuk dapat label/title
                    const parentDiv = chartCanvas.closest('.chart-wrapper') || chartCanvas.parentElement;
                    
                    // Capture chart dengan html2canvas
                    const capturedCanvas = await html2canvas(parentDiv, {
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        backgroundColor: '#ffffff'
                    });
                    
                    const imgData = capturedCanvas.toDataURL('image/jpeg', 0.95);
                    const aspectRatio = capturedCanvas.width / capturedCanvas.height;
                    const chartWidth = imgWidth - 20; // 190mm
                    const calculatedHeight = chartWidth / aspectRatio;
                    const finalHeight = Math.min(calculatedHeight, chartHeight);
                    const finalWidth = finalHeight * aspectRatio;
                    
                    // Center horizontally
                    const xPos = (imgWidth - finalWidth) / 2;
                    
                    pdf.addImage(imgData, 'JPEG', xPos, currentY, finalWidth, finalHeight);
                    
                    currentY += finalHeight + chartGap;
                    chartOnPage++;
                }
            }
            
            // Restore buttons
            buttons.forEach(btn => btn.style.display = '');
            
            // Add footer on last page
            const pageCount = pdf.internal.getNumberOfPages();
            for(let i = 1; i <= pageCount; i++) {
                pdf.setPage(i);
                pdf.setFontSize(8);
                pdf.setTextColor(128);
                pdf.text('e-Dokter SIMRS', 14, pageHeight - 10);
                pdf.text(`Halaman ${i} dari ${pageCount}`, 196, pageHeight - 10, { align: 'right' });
            }
            
            // Download
            const fileName = `Grafik_${noRM}_${noRawat.replace(/\//g, '-')}.pdf`;
            pdf.save(fileName);
            
            Swal.fire({
                icon: 'success',
                title: 'PDF Berhasil Dibuat',
                text: 'File akan otomatis terdownload',
                timer: 2000,
                showConfirmButton: false
            });
            
        } catch(error) {
            console.error('Error generating PDF:', error);
            Swal.fire({
                icon: 'error',
                title: 'Gagal Membuat PDF',
                text: error.message || 'Terjadi kesalahan saat membuat PDF'
            });
        }
    };
    
    //console.log('✅ grafik_ttv_inap.php loaded');
})();
</script>
