<?php
session_start();
require_once('../conf/conf.php');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo "<div class='alert alert-danger'>Session expired</div>";
    exit();
}

// Ambil parameter NO RM
$norm = isset($_GET['norm']) ? validTeks4($_GET['norm'], 20) : '';

if(empty($norm)){
    echo "<div class='alert alert-danger'>Parameter NO RM tidak valid</div>";
    exit();
}

// --- Pagination & Filter setup ---
$limit = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Decide whether to honor incoming norawat parameter:
// We only accept norawat when the request is an AJAX request (ajax=1) OR a pagination request (page present) OR explicit filter (from_filter=1).
$selectedNoRawat = '';
if (isset($_GET['norawat']) && $_GET['norawat'] !== '' &&
    (isset($_GET['ajax']) || isset($_GET['page']) || (isset($_GET['from_filter']) && $_GET['from_filter'] == '1'))
) {
    $selectedNoRawat = validTeks4($_GET['norawat'], 20);
}

// Helper: build query-safely snippets (we use validTeks4 earlier)
$filter_sql = "";
if ($selectedNoRawat !== '') {
    $filter_sql = " AND pl.no_rawat = '$selectedNoRawat' ";
}

// Hitung total no_rawat yang punya data lab (distinct)
$qTotal = bukaquery("
    SELECT COUNT(DISTINCT pl.no_rawat) AS total
    FROM periksa_lab pl
    INNER JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm'
    $filter_sql
");

$totalRowArr = mysqli_fetch_assoc($qTotal);
$totalRow = isset($totalRowArr['total']) ? intval($totalRowArr['total']) : 0;
$totalPages = $totalRow > 0 ? ceil($totalRow / $limit) : 1;

// Ambil daftar no_rawat (LIMIT 5 per page) yang punya data lab
$query_rawat = bukaquery("
    SELECT DISTINCT pl.no_rawat
    FROM periksa_lab pl
    INNER JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm'
    $filter_sql
    ORDER BY pl.no_rawat DESC
    LIMIT $limit OFFSET $offset
");

// Ambil seluruh daftar no_rawat untuk dropdown (tanpa limit) — ini dipakai untuk pilihan filter
$qAllRawat = bukaquery("
    SELECT DISTINCT pl.no_rawat
    FROM periksa_lab pl
    INNER JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm'
    ORDER BY pl.no_rawat DESC
");

// CEK APAKAH ADA DATA PA
$qCheckPA = bukaquery("
    SELECT COUNT(*) as jml 
    FROM periksa_lab pl
    INNER JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm' 
    AND pl.kategori = 'PA'
    $filter_sql
");
$rowPA = mysqli_fetch_assoc($qCheckPA);
$hasDataPA = ($rowPA['jml'] > 0);

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

.lab-container {
    background: var(--light-bg);
    padding: 0;
    margin: 0;
}

/* Filter header */
.lab-filter-header {
    margin-bottom: 16px;
}

/* Modern Card Style */
.lab-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    overflow: hidden;
    border: 1px solid var(--border);
}

.lab-card-header {
    background: linear-gradient(135deg, #5FD38D 0%, #0F6FB2 100%);
    color: white;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.lab-card-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

/* PA Card Header - warna berbeda */
.lab-card-header-pa {
    background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
}

/* Modern Table */
.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table thead {
    background: #f1f5f9;
}

.modern-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border);
}

.modern-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

.modern-table tbody tr:hover {
    background: #f8fafc;
}

.modern-table tbody tr:last-child {
    border-bottom: none;
}

.modern-table td {
    padding: 10px 16px;
    font-size: 13px;
    color: var(--text-primary);
    vertical-align: top;
}

/* Pemisah antar pemeriksaan */
.pemeriksaan-row {
    border-top: 3px solid var(--primary) !important;
    background: #fafbfc;
}

.pemeriksaan-row:first-of-type {
    border-top: none !important;
}

/* Alternating background untuk detail */
.detail-row-container {
    background: #fafbfc;
}

.detail-row-container:nth-of-type(4n) {
    background: #f8fafc;
}

.detail-row-container:nth-of-type(4n-1) {
    background: #f8fafc;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-ralan {
    background: #dcfce7;
    color: #166534;
}

.badge-ranap {
    background: #fee2e2;
    color: #991b1b;
}

/* Kategori Badges */
.badge-pk, .badge-pa, .badge-mb {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.badge-pk {
    background: #dbeafe;
    color: #1e40af;
}

.badge-pa {
    background: #fce7f3;
    color: #9f1239;
}

.badge-mb {
    background: #fef3c7;
    color: #92400e;
}

/* Detail Table */
.detail-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.detail-table thead {
    background: linear-gradient(135deg, #84cc16 0%, #748d07 100%);
}

.detail-table th {
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    font-size: 12px;
    color: white;
    border: none;
}

.detail-table tbody tr {
    border-bottom: 1px solid #e2e8f0;
    transition: all 0.2s;
}

.detail-table tbody tr:last-child {
    border-bottom: none;
}

.detail-table td {
    padding: 10px 14px;
    font-size: 12px;
}

/* PA Detail Table - warna berbeda */
.detail-table-pa thead {
    background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
}

/* Row Kritis */
.row-kritis-tinggi {
    background: #fee2e2 !important;
}

.row-kritis-rendah {
    background: #fef3c7 !important;
}

.row-nilai-kosong {
    background: #f3f4f6 !important;
}

/* Badge Belum Ada */
.badge-belum-ada {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 14px;
    background: #e0e7ff;
    color: #4338ca;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

/* Pemeriksaan Title */
.pemeriksaan-title {
    font-weight: 600;
    font-size: 13px;
    color: var(--text-primary);
}

.pemeriksaan-subtitle {
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 2px;
}

/* Pagination */
.lab-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin-top: 24px;
    padding: 20px;
    background: var(--card-bg);
    border-radius: 8px;
    box-shadow: var(--shadow-sm);
}

.lab-pagination button {
    padding: 8px 16px;
    border: 1px solid var(--border);
    background: white;
    color: var(--text-primary);
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}

.lab-pagination button:hover:not(:disabled) {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.lab-pagination button:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.page-info {
    font-size: 13px;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Filter Dropdown */
.filter-group {
    display: flex;
    align-items: center;
    gap: 12px;
    background: var(--card-bg);
    padding: 12px 20px;
    border-radius: 8px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 20px;
    border: 1px solid var(--border);
}

.filter-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
}

.filter-group select {
    flex: 1;
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 13px;
    color: var(--text-primary);
    background: white;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.filter-group button {
    padding: 8px 14px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.filter-group button:hover {
    background: var(--primary-dark);
}

/* === Two Column Layout === */
.lab-twocol-wrapper {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--border);
}

.lab-twocol-wrapper:last-child {
    border-bottom: none;
}

.lab-twocol-left {
    flex: 0 0 260px;
    max-width: 260px;
    border-right: 2px solid var(--border);
    background: #fafbfc;
    padding: 16px;
}

/* Alternating color accent per pemeriksaan */
.lab-twocol-wrapper:nth-child(odd) .lab-twocol-left {
    border-left: 4px solid var(--primary);
}

.lab-twocol-wrapper:nth-child(even) .lab-twocol-left {
    border-left: 4px solid #a78bfa;
}

.lab-twocol-wrapper:nth-child(odd) {
    background: #ffffff;
}

.lab-twocol-wrapper:nth-child(even) {
    background: #f8fafc;
}

.lab-twocol-left .info-row {
    margin-bottom: 8px;
}

.lab-twocol-left .info-label {
    font-size: 10px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
}

.lab-twocol-left .info-value {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    word-break: break-word;
}

.lab-twocol-left .info-value-sm {
    font-size: 12px;
    font-weight: 500;
    color: var(--text-primary);
    word-break: break-word;
}

.lab-twocol-right {
    flex: 1;
    min-width: 0;
    overflow-x: auto;
}

.lab-twocol-right .detail-table {
    border-radius: 0;
}

/* Button Copy Hasil */
.btn-copy-hasil {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 12px;
    padding: 7px 14px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-copy-hasil:hover {
    opacity: 0.85;
    transform: translateY(-1px);
}

.btn-copy-hasil.copied {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Responsive: tablet & mobile stack vertikal */
@media (max-width: 768px) {
    .lab-twocol-wrapper {
        flex-direction: column;
    }
    .lab-twocol-left {
        flex: none;
        max-width: 100%;
        border-right: none;
        border-bottom: 2px solid var(--border);
    }
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

/* Grafik Panel */
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

.btn-toggle-grafik.active i {
    transform: rotate(180deg);
}

.grafik-panel-content {
    padding: 20px;
}

/* Filter Grafik */
.grafik-filter-group {
    background: #f8fafc;
    padding: 12px 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    margin-bottom: 16px;
}

.grafik-filter-select {
    flex: 1;
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 13px;
    color: var(--text-primary);
    background: white;
    cursor: pointer;
    transition: all 0.2s;
}

.grafik-filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.grafik-filter-select option {
    padding: 8px;
}

/* Item Lab Grid */
.item-lab-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
    padding: 4px;
}

.item-lab-checkbox {
    display: flex;
    align-items: center;
    padding: 12px 14px;
    border: 2px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
}

.item-lab-checkbox:hover {
    border-color: var(--primary);
    background: #f8fafc;
}

.item-lab-checkbox.selected {
    border-color: var(--primary);
    background: #ede9fe;
}

.item-lab-checkbox.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.item-lab-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-right: 10px;
    cursor: pointer;
    accent-color: var(--primary);
}

.item-lab-info {
    flex: 1;
}

.item-lab-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.item-lab-count {
    font-size: 11px;
    color: var(--text-secondary);
}

/* Button styles */
.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
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

/* Chart container */
#grafikContainer {
    margin-top: 20px;
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* PA Detail Styles */
.pa-detail-card {
    background: #fdf2f8;
    border-radius: 8px;
    padding: 16px;
    margin-top: 8px;
}

.pa-detail-row {
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px dashed #f9a8d4;
}

.pa-detail-row:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.pa-detail-label {
    font-size: 11px;
    font-weight: 600;
    color: #9f1239;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.pa-detail-value {
    font-size: 13px;
    color: var(--text-primary);
    white-space: pre-wrap;
    line-height: 1.5;
}

.pa-gambar-container {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 12px;
}

.pa-gambar-item {
    width: 120px;
    height: 120px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #f9a8d4;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pa-gambar-item img {
    max-width: 100%;
    max-height: 100%;
    object-fit: cover;
    cursor: pointer;
}

.pa-gambar-placeholder {
    color: #9f1239;
    font-size: 11px;
    text-align: center;
    padding: 10px;
}
</style>

<div class="lab-container">
    <?php
    $count_rawat = mysqli_num_rows($query_rawat);
    
    if($count_rawat > 0) {
        // Filter dropdown
        echo "<div class='lab-filter-header'>";
        echo "<div class='filter-group'>";
        echo "<label for='filterNoRawatLab'>Filter No. Rawat:</label>";
        echo "<select id='filterNoRawatLab' name='filterNoRawatLab'>";
        echo "<option value=''>-- Semua No. Rawat --</option>";
        
        mysqli_data_seek($qAllRawat, 0);
        while($r = mysqli_fetch_array($qAllRawat)){
            $sel = ($r['no_rawat'] == $selectedNoRawat) ? 'selected' : '';
            echo "<option value='" . htmlspecialchars($r['no_rawat']) . "' $sel>" . htmlspecialchars($r['no_rawat']) . "</option>";
        }
        
        echo "</select>";
        echo "<button id='reloadLabRiwayat'>";
        echo "<i class='material-icons' style='font-size:16px;'>refresh</i>";
        echo "Reload";
        echo "</button>";
        echo "</div>";
        echo "</div>";
        
        // PANEL GRAFIK LABORATORIUM
        echo "<div class='grafik-panel' id='grafikLabPanel'>";
        echo "<div class='grafik-panel-header' onclick='toggleGrafikPanel()'>";
        echo "<h4>";
        echo "<i class='material-icons'>show_chart</i>";
        echo "Generate Grafik Laboratorium";
        echo "</h4>";
        echo "<button class='btn-toggle-grafik'>";
        echo "<i class='material-icons'>expand_more</i>";
        echo "</button>";
        echo "</div>";
        
        echo "<div class='grafik-panel-content' id='grafikPanelContent' style='display: none;'>";
        // Loading state
        echo "<div id='loadingItemLab' class='text-center' style='padding: 20px;'>";
        echo "<i class='material-icons spin' style='font-size:30px; color:#777;'>autorenew</i>";
        echo "<div style='margin-top:5px;'>Memuat item laboratorium...</div>";
        echo "</div>";
        
        // Item selection
        echo "<div id='itemLabSelection' style='display: none;'>";
        echo "<div class='item-lab-header'>";
        echo "<p style='margin: 0 0 12px 0; color: var(--text-secondary); font-size: 13px;'>";
        echo "Pilih maksimal 5 item laboratorium untuk divisualisasikan dalam grafik";
        echo "</p>";
        
        // FILTER NO RAWAT UNTUK GRAFIK
        echo "<div class='grafik-filter-group'>";
        echo "<label style='font-size: 13px; font-weight: 600; margin-right: 10px;'>";
        echo "<i class='material-icons' style='font-size: 16px; vertical-align: middle;'>filter_alt</i>";
        echo " Filter Data:";
        echo "</label>";
        echo "<select id='filterNoRawatGrafik' class='grafik-filter-select'>";
        echo "<option value=''>🌐 Semua No. Rawat (Tren Keseluruhan)</option>";
        // Akan diisi via AJAX
        echo "</select>";
        echo "</div>";
        
        echo "<div style='display: flex; gap: 8px; margin-bottom: 16px;'>";
        echo "<button class='btn btn-primary' onclick='generateGrafik()'>";
        echo "<i class='material-icons' style='font-size: 16px;'>show_chart</i>";
        echo "Generate Grafik";
        echo "</button>";
        echo "<button class='btn btn-secondary' onclick='resetSelection()'>";
        echo "<i class='material-icons' style='font-size: 16px;'>refresh</i>";
        echo "Reset";
        echo "</button>";
        echo "</div>";
        echo "</div>";
        
        echo "<div class='item-lab-grid' id='itemLabGrid'>";
        // Akan diisi via AJAX
        echo "</div>";
        echo "</div>";
        
        // Grafik container
        echo "<div id='grafikContainer' style='display: none;'>";
        echo "<div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;'>";
        echo "<div>";
        echo "<h5 style='margin: 0;'>Tren Nilai Laboratorium</h5>";
        echo "<p id='grafikSubtitle' style='margin: 4px 0 0 0; font-size: 12px; color: var(--text-secondary);'></p>";
        echo "</div>";
        echo "<button class='btn btn-secondary btn-sm' onclick='closeGrafik()'>";
        echo "<i class='material-icons' style='font-size: 16px;'>close</i>";
        echo "Tutup Grafik";
        echo "</button>";
        echo "</div>";
        echo "<canvas id='labChart' height='80'></canvas>";
        echo "</div>";
        echo "</div>"; // end grafik-panel-content
        echo "</div>"; // end grafik-panel
        
        // Loop untuk setiap no_rawat - CARD PK & MB
        mysqli_data_seek($query_rawat, 0);
        while($rawat = mysqli_fetch_array($query_rawat)) {
            $no_rawat = $rawat['no_rawat'];
            
            // CEK DULU APAKAH ADA DATA PK/MB untuk no_rawat ini
            $qCekPKMB = bukaquery("SELECT COUNT(*) as jml FROM periksa_lab WHERE no_rawat = '$no_rawat' AND kategori IN ('PK', 'MB')");
            $cekPKMB = mysqli_fetch_assoc($qCekPKMB);
            
            if($cekPKMB['jml'] == 0) continue; // Skip jika tidak ada PK/MB
            
            // Ambil info registrasi untuk header card
            $qReg = bukaquery("SELECT 
                                rp.tgl_registrasi,
                                rp.status_lanjut,
                                COALESCE(d.nm_dokter, '-') as nm_dokter
                              FROM reg_periksa rp
                              LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                              WHERE rp.no_rawat = '$no_rawat'
                              LIMIT 1");
            $reg = mysqli_fetch_array($qReg);
            
            echo "<div class='lab-card'>";
            echo "<div class='lab-card-header'>";
            echo "<h4>";
            echo "<i class='material-icons'>assignment</i>";
            echo "Pemeriksaan Laboratorium PK & MB";
            echo "</h4>";
            echo "<div style='display: flex; align-items: center; gap: 12px;'>";
            echo "<span style='font-size: 14px;'><i class='material-icons' style='font-size:16px; vertical-align: middle;'>badge</i> No. Rawat: <strong>" . htmlspecialchars($no_rawat) . "</strong></span>";
            echo "</div>";
            echo "</div>";
            
            // Query SEMUA pemeriksaan dalam no_rawat ini (HANYA PK dan MB)
            $query_detail = bukaquery("SELECT 
                                            pl.kd_jenis_prw,
                                            pl.tgl_periksa,
                                            pl.jam,
                                            pl.status,
                                            pl.kategori,
                                            pl.dokter_perujuk,
                                            pl.nip,
                                            jl.nm_perawatan,
                                            jl.bagian_rs,
                                            d.nm_dokter,
                                            p.nama AS nama_petugas
                                        FROM periksa_lab pl
                                        LEFT JOIN jns_perawatan_lab jl ON pl.kd_jenis_prw = jl.kd_jenis_prw
                                        LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
                                        LEFT JOIN petugas p ON pl.nip = p.nip
                                        WHERE pl.no_rawat = '$no_rawat'
                                        AND pl.kategori IN ('PK', 'MB')
                                        GROUP BY pl.kd_jenis_prw, pl.tgl_periksa, pl.jam
                                        ORDER BY pl.tgl_periksa DESC, pl.jam DESC");

            $no_pemeriksaan = 0;

            while($detail = mysqli_fetch_array($query_detail)) {
                $no_pemeriksaan++;
                $kd_jenis_prw = $detail['kd_jenis_prw'];
                $tgl_periksa = $detail['tgl_periksa'];
                $jam = $detail['jam'];

                // Status badge
                $status_class = ($detail['status'] == 'Ralan') ? 'badge-ralan' : 'badge-ranap';
                $status_text = strtoupper($detail['status']);

                // Badge kategori
                $kategori = strtoupper($detail['kategori']);
                $badge_class = 'badge-pk';
                if($kategori == 'PA') $badge_class = 'badge-pa';
                else if($kategori == 'MB') $badge_class = 'badge-mb';

                // === Layout 2 Kolom per Pemeriksaan ===
                $wrapper_id = 'labwrap_' . $no_pemeriksaan . '_' . md5($no_rawat . $kd_jenis_prw . $tgl_periksa . $jam);
                echo "<div class='lab-twocol-wrapper' id='$wrapper_id'>";

                // --- Kolom Kiri: Info Pemeriksaan (Vertikal) ---
                echo "<div class='lab-twocol-left'>";
                echo "<div class='info-row'>";
                echo "<div class='info-label'>Tanggal</div>";
                echo "<div class='info-value'>" . konversiTanggal($tgl_periksa) . " <span style='font-weight:400; color:var(--text-secondary);'>" . substr($jam, 0, 5) . "</span></div>";
                echo "</div>";
                echo "<div class='info-row'>";
                echo "<span class='status-badge $status_class'>$status_text</span> <span class='$badge_class' style='margin-left:4px;'>$kategori</span>";
                echo "</div>";
                echo "<div class='info-row'>";
                echo "<div class='info-label'>Kode</div>";
                echo "<div class='info-value'>" . htmlspecialchars($kd_jenis_prw) . "</div>";
                echo "</div>";
                echo "<div class='info-row'>";
                echo "<div class='info-label'>Nama Pemeriksaan</div>";
                echo "<div class='info-value'>" . htmlspecialchars($detail['nm_perawatan']) . "</div>";
                echo "</div>";
                echo "<div class='info-row'>";
                echo "<div class='info-label'>Dokter PJ</div>";
                echo "<div class='info-value-sm'>" . htmlspecialchars($detail['nm_dokter']) . "</div>";
                echo "</div>";
                echo "<div class='info-row'>";
                echo "<div class='info-label'>Petugas</div>";
                echo "<div class='info-value-sm'>" . htmlspecialchars($detail['nama_petugas']) . "</div>";
                echo "</div>";
                // Button Copy Hasil
                $copy_header = htmlspecialchars($detail['nm_perawatan']) . ' , ' . konversiTanggal($tgl_periksa) . ' ' . substr($jam, 0, 5);
                echo "<button class='btn-copy-hasil' onclick=\"copyHasilLab('$wrapper_id', '" . addslashes($copy_header) . "')\" title='Copy hasil'>";
                echo "<i class='material-icons' style='font-size:16px;'>content_copy</i> Copy Hasil";
                echo "</button>";
                echo "</div>"; // end lab-twocol-left

                // --- Kolom Kanan: Detail Hasil Lab ---
                echo "<div class='lab-twocol-right'>";

                $query_hasil = bukaquery("SELECT 
                                            dpl.id_template,
                                            dpl.nilai,
                                            dpl.nilai_rujukan,
                                            dpl.keterangan,
                                            tl.Pemeriksaan
                                        FROM detail_periksa_lab dpl
                                        LEFT JOIN template_laboratorium tl ON dpl.id_template = tl.id_template
                                        WHERE dpl.no_rawat = '$no_rawat'
                                        AND dpl.kd_jenis_prw = '$kd_jenis_prw'
                                        AND dpl.tgl_periksa = '$tgl_periksa'
                                        AND dpl.jam = '$jam'
                                        ORDER BY dpl.id_template ASC");

                $count_hasil = mysqli_num_rows($query_hasil);

                if($count_hasil > 0) {
                    echo "<table class='detail-table'>";
                    echo "<thead>";
                    echo "<tr>";
                    echo "<th width='35%'>Item</th>";
                    echo "<th width='25%'>Hasil</th>";
                    echo "<th width='25%'>Nilai Rujukan</th>";
                    echo "<th width='15%'>Keterangan</th>";
                    echo "</tr>";
                    echo "</thead>";
                    echo "<tbody>";

                    while($hasil = mysqli_fetch_array($query_hasil)) {
                        $nilai = trim($hasil['nilai']);
                        $keterangan = trim($hasil['keterangan']);
                        $keterangan_upper = strtoupper($keterangan);
                        $row_class = '';
                        $is_empty = false;

                        if(empty($nilai) || $nilai == '' || $nilai == '-') {
                            $row_class = 'row-nilai-kosong';
                            $is_empty = true;
                        } else if($keterangan_upper == 'L') {
                            $row_class = 'row-kritis-rendah';
                        } else if($keterangan_upper == 'H') {
                            $row_class = 'row-kritis-tinggi';
                        }

                        echo "<tr class='$row_class'>";
                        echo "<td>" . htmlspecialchars($hasil['Pemeriksaan']) . "</td>";

                        if($is_empty) {
                            echo "<td colspan='3' style='text-align: center;'>";
                            echo "<span class='badge-belum-ada'>";
                            echo "<i class='material-icons' style='font-size: 14px;'>schedule</i>";
                            echo "Belum Ada Hasil";
                            echo "</span>";
                            echo "</td>";
                        } else {
                            echo "<td><strong>" . htmlspecialchars($hasil['nilai']) . "</strong></td>";
                            echo "<td>" . htmlspecialchars($hasil['nilai_rujukan']) . "</td>";
                            echo "<td align='center'><strong>" . htmlspecialchars($keterangan) . "</strong></td>";
                        }

                        echo "</tr>";
                    }

                    echo "</tbody>";
                    echo "</table>";
                } else {
                    echo "<div style='text-align: center; padding: 20px; color: var(--text-secondary);'>";
                    echo "<span class='badge-belum-ada'>";
                    echo "<i class='material-icons' style='font-size: 14px;'>schedule</i>";
                    echo "Belum Ada Hasil";
                    echo "</span>";
                    echo "</div>";
                }

                echo "</div>"; // end lab-twocol-right
                echo "</div>"; // end lab-twocol-wrapper
            } // end while detail

            echo "</div>"; // end lab-card
        } // end while rawat

        // ==========================================
        // SECTION PATOLOGI ANATOMI (PA)
        // ==========================================
        if($hasDataPA) {
            // Reset pointer untuk loop lagi
            mysqli_data_seek($query_rawat, 0);
            
            while($rawat = mysqli_fetch_array($query_rawat)) {
                $no_rawat = $rawat['no_rawat'];
                
                // Cek apakah no_rawat ini punya data PA
                $qCekPA = bukaquery("SELECT COUNT(*) as jml FROM periksa_lab WHERE no_rawat = '$no_rawat' AND kategori = 'PA'");
                $cekPA = mysqli_fetch_assoc($qCekPA);
                
                if($cekPA['jml'] == 0) continue; // Skip jika tidak ada PA
                
                echo "<div class='lab-card'>";
                echo "<div class='lab-card-header lab-card-header-pa'>";
                echo "<h4>";
                echo "<i class='material-icons'>biotech</i>";
                echo "Pemeriksaan Laboratorium Patologi Anatomi (PA)";
                echo "</h4>";
                echo "<div style='display: flex; align-items: center; gap: 12px;'>";
                echo "<span style='font-size: 14px;'><i class='material-icons' style='font-size:16px; vertical-align: middle;'>badge</i> No. Rawat: <strong>" . htmlspecialchars($no_rawat) . "</strong></span>";
                echo "</div>";
                echo "</div>";
                
                echo "<table class='modern-table'>";
                echo "<thead>";
                echo "<tr>";
                echo "<th width='50'>NO.</th>";
                echo "<th width='150'>TANGGAL</th>";
                echo "<th width='100'>KODE</th>";
                echo "<th>NAMA PEMERIKSAAN</th>";
                echo "<th width='180'>DOKTER PJ</th>";
                echo "<th width='150'>PETUGAS</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";
                
                // Query pemeriksaan PA
                $query_pa = bukaquery("SELECT 
                                            pl.kd_jenis_prw,
                                            pl.tgl_periksa,
                                            pl.jam,
                                            pl.status,
                                            pl.kategori,
                                            pl.dokter_perujuk,
                                            pl.nip,
                                            jl.nm_perawatan,
                                            d.nm_dokter,
                                            p.nama AS nama_petugas
                                        FROM periksa_lab pl
                                        LEFT JOIN jns_perawatan_lab jl ON pl.kd_jenis_prw = jl.kd_jenis_prw
                                        LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
                                        LEFT JOIN petugas p ON pl.nip = p.nip
                                        WHERE pl.no_rawat = '$no_rawat'
                                        AND pl.kategori = 'PA'
                                        GROUP BY pl.kd_jenis_prw, pl.tgl_periksa, pl.jam
                                        ORDER BY pl.tgl_periksa DESC, pl.jam DESC");
                
                $no_pa = 0;
                
                while($pa = mysqli_fetch_array($query_pa)) {
                    $no_pa++;
                    $kd_jenis_prw = $pa['kd_jenis_prw'];
                    $tgl_periksa = $pa['tgl_periksa'];
                    $jam = $pa['jam'];
                    
                    // Status badge
                    $status_class = ($pa['status'] == 'Ralan') ? 'badge-ralan' : 'badge-ranap';
                    $status_text = strtoupper($pa['status']);
                    
                    $separator_class = ($no_pa > 1) ? 'pemeriksaan-row' : '';
                    echo "<tr class='$separator_class'>";
                    echo "<td align='center' style='font-size: 18px; font-weight: 700; color: #ec4899;'>$no_pa</td>";
                    echo "<td style='padding: 8px 16px;'>";
                    echo "<div style='font-weight: 600; font-size: 13px;'>" . konversiTanggal($tgl_periksa) . "</div>";
                    echo "<div style='font-size: 12px; color: var(--text-secondary); margin-top: 1px;'>" . substr($jam, 0, 5) . "</div>";
                    echo "<div style='margin-top: 4px;'><span class='status-badge $status_class'>$status_text</span></div>";
                    echo "</td>";
                    echo "<td style='padding: 8px 16px;'><strong style='font-size: 13px;'>" . htmlspecialchars($kd_jenis_prw) . "</strong></td>";
                    echo "<td style='padding: 8px 16px;'>";
                    echo "<div class='pemeriksaan-title'>" . htmlspecialchars($pa['nm_perawatan']) . "</div>";
                    echo "<div class='pemeriksaan-subtitle'>Detail Pemeriksaan</div>";
                    echo "<div style='margin-top: 4px;'><span class='badge-pa'>PA</span></div>";
                    echo "</td>";
                    echo "<td style='padding: 8px 16px;'>" . htmlspecialchars($pa['nm_dokter']) . "</td>";
                    echo "<td style='padding: 8px 16px;'>" . htmlspecialchars($pa['nama_petugas']) . "</td>";
                    echo "</tr>";
                    
                    // Query detail PA dari tabel detail_periksa_labpa
                    $query_detail_pa = bukaquery("SELECT 
                                                    diagnosa_klinik,
                                                    makroskopik,
                                                    mikroskopik,
                                                    kesimpulan,
                                                    kesan
                                                FROM detail_periksa_labpa
                                                WHERE no_rawat = '$no_rawat'
                                                AND kd_jenis_prw = '$kd_jenis_prw'
                                                AND tgl_periksa = '$tgl_periksa'
                                                AND jam = '$jam'
                                                LIMIT 1");
                    
                    $detail_pa = mysqli_fetch_array($query_detail_pa);
                    
                    // Query gambar PA
                    $query_gambar_pa = bukaquery("SELECT photo 
                                                  FROM detail_periksa_labpa_gambar
                                                  WHERE no_rawat = '$no_rawat'
                                                  AND kd_jenis_prw = '$kd_jenis_prw'
                                                  AND tgl_periksa = '$tgl_periksa'
                                                  AND jam = '$jam'");
                    
                    echo "<tr class='detail-row-container'>";
                    echo "<td colspan='6' style='padding: 8px 16px; background: inherit;'>";
                    
                    if($detail_pa) {
                        echo "<div class='pa-detail-card'>";
                        
                        // Diagnosa Klinik
                        if(!empty($detail_pa['diagnosa_klinik'])) {
                            echo "<div class='pa-detail-row'>";
                            echo "<div class='pa-detail-label'>Diagnosa Klinik</div>";
                            echo "<div class='pa-detail-value'>" . nl2br(htmlspecialchars($detail_pa['diagnosa_klinik'])) . "</div>";
                            echo "</div>";
                        }
                        
                        // Makroskopik
                        if(!empty($detail_pa['makroskopik'])) {
                            echo "<div class='pa-detail-row'>";
                            echo "<div class='pa-detail-label'>Makroskopik</div>";
                            echo "<div class='pa-detail-value'>" . nl2br(htmlspecialchars($detail_pa['makroskopik'])) . "</div>";
                            echo "</div>";
                        }
                        
                        // Mikroskopik
                        if(!empty($detail_pa['mikroskopik'])) {
                            echo "<div class='pa-detail-row'>";
                            echo "<div class='pa-detail-label'>Mikroskopik</div>";
                            echo "<div class='pa-detail-value'>" . nl2br(htmlspecialchars($detail_pa['mikroskopik'])) . "</div>";
                            echo "</div>";
                        }
                        
                        // Kesimpulan
                        if(!empty($detail_pa['kesimpulan'])) {
                            echo "<div class='pa-detail-row'>";
                            echo "<div class='pa-detail-label'>Kesimpulan</div>";
                            echo "<div class='pa-detail-value'>" . nl2br(htmlspecialchars($detail_pa['kesimpulan'])) . "</div>";
                            echo "</div>";
                        }
                        
                        // Kesan
                        if(!empty($detail_pa['kesan'])) {
                            echo "<div class='pa-detail-row'>";
                            echo "<div class='pa-detail-label'>Kesan</div>";
                            echo "<div class='pa-detail-value'>" . nl2br(htmlspecialchars($detail_pa['kesan'])) . "</div>";
                            echo "</div>";
                        }
                        
                        // Gambar PA
                        if(mysqli_num_rows($query_gambar_pa) > 0) {
                            echo "<div class='pa-detail-row'>";
                            echo "<div class='pa-detail-label'>Gambar</div>";
                            echo "<div class='pa-gambar-container'>";
                            
                            while($gambar = mysqli_fetch_array($query_gambar_pa)) {
                                echo "<div class='pa-gambar-item'>";
                                // URL gambar dikosongkan dulu sesuai permintaan
                                // Nanti bisa diisi dengan: src='path/to/image/" . htmlspecialchars($gambar['photo']) . "'
                                echo "<div class='pa-gambar-placeholder'>";
                                echo "<i class='material-icons' style='font-size: 32px;'>image</i><br>";
                                echo htmlspecialchars($gambar['photo']);
                                echo "</div>";
                                echo "</div>";
                            }
                            
                            echo "</div>";
                            echo "</div>";
                        }
                        
                        echo "</div>"; // end pa-detail-card
                    } else {
                        echo "<div style='text-align: center; padding: 20px;'>";
                        echo "<span class='badge-belum-ada'>";
                        echo "<i class='material-icons' style='font-size: 14px;'>schedule</i>";
                        echo "Belum Ada Hasil PA";
                        echo "</span>";
                        echo "</div>";
                    }
                    
                    echo "</td>";
                    echo "</tr>";
                } // end while pa
                
                echo "</tbody>";
                echo "</table>";
                echo "</div>"; // end lab-card PA
            } // end while rawat PA
        } // end if hasDataPA

        // PAGINATION CONTROLS (AJAX-friendly)
        echo "<div class='lab-pagination' role='navigation' aria-label='Pagination'>";
        if ($page > 1) {
            echo "<button class='btn lab-page' data-page='" . ($page - 1) . "'>&laquo; Prev</button>";
        }
        echo "<span class='page-info'>Halaman $page / $totalPages</span>";
        if ($page < $totalPages) {
            echo "<button class='btn lab-page' data-page='" . ($page + 1) . "'>Next &raquo;</button>";
        }
        echo "</div>";

    } else {
        echo "<div class='empty-state'>";
        echo "<i class='material-icons'>science</i>";
        echo "<h4>Belum Ada Data Laboratorium</h4>";
        echo "<p>Pasien ini belum memiliki riwayat pemeriksaan laboratorium</p>";
        echo "</div>";
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function(){
    // Use delegated events because content is replaced by AJAX
    // FILTER change (user explicit selection) -> send from_filter=1 to let PHP accept norawat
    $(document).off('change', '#filterNoRawatLab').on('change', '#filterNoRawatLab', function() {
        var norm = "<?= $norm ?>";
        var norawat = $(this).val();
        // page reset to 1
        loadRiwayatLabAjax(norm, norawat, 1, true);
    });

    // Reload button
    $(document).off('click', '#reloadLabRiwayat').on('click', '#reloadLabRiwayat', function(){
        var norm = "<?= $norm ?>";
        var norawat = $('#filterNoRawatLab').val();
        // keep current page = 1 when reloading
        loadRiwayatLabAjax(norm, norawat, 1, ($('#filterNoRawatLab').val() !== '') );
    });

    // Pagination buttons (AJAX)
    $(document).off('click', '.lab-page').on('click', '.lab-page', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        var norm = "<?= $norm ?>";
        var norawat = $('#filterNoRawatLab').val();
        loadRiwayatLabAjax(norm, norawat, page, ($('#filterNoRawatLab').val() !== ''));
    });

    // Function to load via AJAX
    function loadRiwayatLabAjax(norm, norawat, page, isFromFilterOrAjax) {
        $('#riwayat_lab_content').html(`
            <div class="text-center" style="padding:30px;">
                <i class="material-icons spin" style="font-size:40px; color:#777;">autorenew</i>
                <div style="margin-top:5px;">Memuat data...</div>
            </div>
        `);

        var data = { norm: norm, page: page, ajax: 1 };
        // Only include norawat when it's from filter/pagination/reload (so initial load without filter won't auto-select)
        if (norawat && norawat !== '') {
            data.norawat = norawat;
            // Indicate this request originates from filter action
            data.from_filter = isFromFilterOrAjax ? 1 : 0;
        }

        $.get('pages/riwayat_lab.php', data, function(resp) {
            $('#riwayat_lab_content').html(resp);
        });
    }

    // === GRAFIK FUNCTIONS ===
    
    // Toggle panel grafik
    window.toggleGrafikPanel = function() {
        const content = $('#grafikPanelContent');
        const btn = $('.btn-toggle-grafik');
        
        if(content.is(':visible')) {
            content.slideUp(300);
            btn.removeClass('active');
        } else {
            content.slideDown(300);
            btn.addClass('active');
            
            // Load item lab pertama kali
            if($('#itemLabGrid').is(':empty')) {
                loadItemLab();
            }
        }
    };

    // Event listener untuk filter no_rawat grafik
    $(document).off('change', '#filterNoRawatGrafik').on('change', '#filterNoRawatGrafik', function() {
        // Close grafik kalau ada
        closeGrafik();
        
        // Reset checkbox
        $('#itemLabGrid input[type="checkbox"]').prop('checked', false).prop('disabled', false);
        $('#itemLabGrid .item-lab-checkbox').removeClass('selected disabled');
        
        // Reload item lab dengan filter baru
        loadItemLab();
    });
    
    // Load daftar item lab
    function loadItemLab() {
        const norm = "<?= $norm ?>";
        const filterNoRawat = $('#filterNoRawatGrafik').val();
        
        $('#loadingItemLab').show();
        $('#itemLabSelection').hide();
        
        const params = { norm: norm };
        if(filterNoRawat && filterNoRawat !== '') {
            params.filter_no_rawat = filterNoRawat;
        }
        
        $.get('pages/get_item_lab.php', params, function(response) {
            $('#loadingItemLab').hide();
            
            if(response.success) {
                // Populate filter no_rawat dropdown untuk grafik
                let noRawatOptions = '<option value="">🌐 Semua No. Rawat (Tren Keseluruhan)</option>';
                response.no_rawat_list.forEach(function(item) {
                    const tglReg = new Date(item.tgl_registrasi).toLocaleDateString('id-ID', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });
                    const statusBadge = item.status_lanjut === 'Ralan' ? '🟢' : '🔴';
                    const selected = (filterNoRawat === item.no_rawat) ? 'selected' : '';
                    noRawatOptions += `<option value="${item.no_rawat}" ${selected}>${statusBadge} ${item.no_rawat} - ${tglReg}</option>`;
                });
                $('#filterNoRawatGrafik').html(noRawatOptions);
                
                if(response.data.length === 0) {
                    $('#itemLabGrid').html(`
                        <div style="grid-column: 1/-1; text-align: center; padding: 30px; color: var(--text-secondary);">
                            <i class="material-icons" style="font-size: 48px; opacity: 0.5;">info</i>
                            <p style="margin-top: 10px;">Tidak ada item laboratorium dengan data yang cukup untuk dibuat grafik.</p>
                            <p style="font-size: 12px; margin-top: 5px;">(Minimal 2 pemeriksaan)</p>
                        </div>
                    `);
                } else {
                    renderItemLabGrid(response.data);
                }
                $('#itemLabSelection').show();
            } else {
                alert(response.message || 'Gagal memuat data');
            }
        }, 'json').fail(function() {
            $('#loadingItemLab').hide();
            alert('Terjadi kesalahan saat memuat data');
        });
    }

    // Render grid item lab
    function renderItemLabGrid(items) {
        let html = '';
        items.forEach(function(item) {
            html += `
                <label class="item-lab-checkbox" data-id="${item.id_template}">
                    <input type="checkbox" name="item_lab[]" value="${item.id_template}">
                    <div class="item-lab-info">
                        <div class="item-lab-name">${item.nama}</div>
                        <div class="item-lab-count">${item.jumlah} pemeriksaan${item.satuan ? ' • ' + item.satuan : ''}</div>
                    </div>
                </label>
            `;
        });
        $('#itemLabGrid').html(html);
        
        // Event handler untuk checkbox
        $('#itemLabGrid').on('change', 'input[type="checkbox"]', function() {
            const checked = $('#itemLabGrid input[type="checkbox"]:checked').length;
            const label = $(this).closest('.item-lab-checkbox');
            
            if($(this).is(':checked')) {
                label.addClass('selected');
                
                // Disable lainnya jika sudah 5
                if(checked >= 5) {
                    $('#itemLabGrid input[type="checkbox"]:not(:checked)').prop('disabled', true);
                    $('#itemLabGrid .item-lab-checkbox:not(.selected)').addClass('disabled');
                }
            } else {
                label.removeClass('selected');
                
                // Enable semua
                $('#itemLabGrid input[type="checkbox"]').prop('disabled', false);
                $('#itemLabGrid .item-lab-checkbox').removeClass('disabled');
            }
        });
    }

    // Reset selection
    window.resetSelection = function() {
        // Reset filter dropdown ke "Semua No. Rawat"
        $('#filterNoRawatGrafik').val('');
        
        // Reset checkbox
        $('#itemLabGrid input[type="checkbox"]').prop('checked', false).prop('disabled', false);
        $('#itemLabGrid .item-lab-checkbox').removeClass('selected disabled');
        
        // Close grafik
        closeGrafik();
        
        // Reload item lab dengan filter "Semua No. Rawat"
        loadItemLab();
    };

    // Generate grafik
    let labChartInstance = null;

    window.generateGrafik = function() {
        const selected = $('#itemLabGrid input[type="checkbox"]:checked');
        
        if(selected.length === 0) {
            alert('Pilih minimal 1 item laboratorium');
            return;
        }
        
        const items = [];
        selected.each(function() {
            items.push($(this).val());
        });
        
        const norm = "<?= $norm ?>";
        const filterNoRawat = $('#filterNoRawatGrafik').val();
        
        $('#grafikContainer').html(`
            <div class="text-center" style="padding:30px;">
                <i class="material-icons spin" style="font-size:40px; color:#777;">autorenew</i>
                <div style="margin-top:5px;">Membuat grafik...</div>
            </div>
        `).show();
        
        $.post('pages/get_grafik_data.php', {
            norm: norm,
            items: items,
            filter_no_rawat: filterNoRawat
        }, function(response) {
            if(response.success) {
                renderGrafik(response.datasets, response.filter_info);
            } else {
                alert(response.message || 'Gagal membuat grafik');
                $('#grafikContainer').hide();
            }
        }, 'json').fail(function(xhr, status, error) {
            alert('Terjadi kesalahan saat membuat grafik');
            $('#grafikContainer').hide();
        });
    };

    // Render grafik dengan Chart.js
    function renderGrafik(datasets, filterInfo) {
        // Destroy chart sebelumnya
        if(labChartInstance) {
            labChartInstance.destroy();
        }
        
        $('#grafikContainer').html(`
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h5 style="margin: 0;">Tren Nilai Laboratorium</h5>
                    <p id="grafikSubtitle" style="margin: 4px 0 0 0; font-size: 12px; color: var(--text-secondary);">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">filter_alt</i> 
                        ${filterInfo}
                    </p>
                </div>
                <button class="btn btn-secondary btn-sm" onclick="closeGrafik()">
                    <i class="material-icons" style="font-size: 16px;">close</i>
                    Tutup Grafik
                </button>
            </div>
            <canvas id="labChart" height="80"></canvas>
        `).show();
        
        // Color palette
        const colors = [
            '#667eea', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6'
        ];
        
        // Format data untuk Chart.js
        const chartDatasets = datasets.map((ds, index) => {
            return {
                label: ds.label,
                data: ds.data.map(d => d.y),
                borderColor: colors[index % colors.length],
                backgroundColor: colors[index % colors.length] + '20',
                borderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                pointBackgroundColor: ds.data.map(d => {
                    if(d.keterangan === 'H') return '#ef4444';
                    if(d.keterangan === 'L') return '#f59e0b';
                    return colors[index % colors.length];
                }),
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                tension: 0.3,
                rawData: ds.data // Store raw data for tooltip
            };
        });
        
        const labels = datasets[0].data.map(d => d.x);
        
        const ctx = document.getElementById('labChart').getContext('2d');
        labChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: chartDatasets
            },
            options: {
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
                            padding: 15,
                            font: { size: 12 }
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
                                
                                if(rawData.nilai_rujukan) {
                                    label += ' (Rujukan: ' + rawData.nilai_rujukan + ')';
                                }
                                
                                if(rawData.keterangan === 'H') {
                                    label += ' ⚠️ TINGGI';
                                } else if(rawData.keterangan === 'L') {
                                    label += ' ⚠️ RENDAH';
                                }
                                
                                return label;
                            },
                            afterLabel: function(context) {
                                const rawData = context.dataset.rawData[context.dataIndex];
                                // Jika ada no_rawat di data (mode ALL), tampilkan di tooltip
                                if(rawData.no_rawat && filterInfo === 'Semua No. Rawat') {
                                    return 'No. Rawat: ' + rawData.no_rawat;
                                }
                                return '';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            font: { size: 11 }
                        }
                    },
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        },
                        ticks: {
                            font: { size: 11 }
                        }
                    }
                }
            }
        });
    }

    // Close grafik
    window.closeGrafik = function() {
        if(labChartInstance) {
            labChartInstance.destroy();
            labChartInstance = null;
        }
        $('#grafikContainer').hide();
    };

    // === COPY HASIL LAB FUNCTION ===
    window.copyHasilLab = function(wrapperId, header) {
        var wrapper = document.getElementById(wrapperId);
        if(!wrapper) return;

        var table = wrapper.querySelector('.lab-twocol-right .detail-table');
        if(!table) {
            alert('Tidak ada data hasil untuk dicopy');
            return;
        }

        var lines = [];
        lines.push(header);
        lines.push('             ');

        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(row) {
            var cells = row.querySelectorAll('td');
            if(cells.length >= 2) {
                var item = cells[0].textContent.trim();
                var belumBadge = row.querySelector('.badge-belum-ada');
                if(belumBadge) {
                    lines.push(item + ': belum ada hasil');
                } else {
                    var nilai = cells[1].textContent.trim();
                    var keterangan = cells.length >= 4 ? cells[3].textContent.trim() : '';
                    var flag = '';
                    if(keterangan === 'H') flag = ' ⬆️';
                    else if(keterangan === 'L') flag = ' ⬇️';
                    lines.push(item + ': ' + nilai + flag);
                }
            }
        });

        var text = lines.join('\n');

        // Copy ke clipboard
        if(navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopiedFeedback(wrapperId);
            }).catch(function() {
                fallbackCopy(text, wrapperId);
            });
        } else {
            fallbackCopy(text, wrapperId);
        }
    };

    function fallbackCopy(text, wrapperId) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showCopiedFeedback(wrapperId);
        } catch(e) {
            alert('Gagal copy. Silakan copy manual.');
        }
        document.body.removeChild(ta);
    }

    function showCopiedFeedback(wrapperId) {
        var btn = document.querySelector('#' + wrapperId + ' .btn-copy-hasil');
        if(btn) {
            var origHTML = btn.innerHTML;
            btn.innerHTML = "<i class='material-icons' style='font-size:16px;'>check</i> Tersalin!";
            btn.classList.add('copied');
            setTimeout(function() {
                btn.innerHTML = origHTML;
                btn.classList.remove('copied');
            }, 2000);
        }
    }

    console.log('✅ riwayat_lab.php loaded');
})();
</script>