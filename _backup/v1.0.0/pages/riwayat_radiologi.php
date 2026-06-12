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

// Pagination setup
$limit = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Filter No. Rawat
$selectedNoRawat = '';
if (isset($_GET['norawat']) && $_GET['norawat'] !== '' &&
    (isset($_GET['ajax']) || isset($_GET['page']) || (isset($_GET['from_filter']) && $_GET['from_filter'] == '1'))
) {
    $selectedNoRawat = validTeks4($_GET['norawat'], 20);
}

$filter_sql = "";
if ($selectedNoRawat !== '') {
    $filter_sql = " AND pr.no_rawat = '$selectedNoRawat' ";
}

// Hitung total no_rawat yang punya data radiologi
$qTotal = bukaquery("
    SELECT COUNT(DISTINCT pr.no_rawat) AS total
    FROM periksa_radiologi pr
    INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm'
    $filter_sql
");

$totalRowArr = mysqli_fetch_assoc($qTotal);
$totalRow = isset($totalRowArr['total']) ? intval($totalRowArr['total']) : 0;
$totalPages = $totalRow > 0 ? ceil($totalRow / $limit) : 1;

// Ambil daftar no_rawat (LIMIT per page)
$query_rawat = bukaquery("
    SELECT DISTINCT pr.no_rawat
    FROM periksa_radiologi pr
    INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm'
    $filter_sql
    ORDER BY pr.no_rawat DESC
    LIMIT $limit OFFSET $offset
");

// Ambil seluruh daftar no_rawat untuk dropdown filter
$qAllRawat = bukaquery("
    SELECT DISTINCT pr.no_rawat
    FROM periksa_radiologi pr
    INNER JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
    WHERE rp.no_rkm_medis = '$norm'
    ORDER BY pr.no_rawat DESC
");

?>
<style>
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

.rad-container {
    background: var(--light-bg);
    padding: 0;
    margin: 0;
}

.rad-filter-header {
    margin-bottom: 16px;
}

.rad-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    overflow: hidden;
    border: 1px solid var(--border);
}

.rad-card-header {
    background: linear-gradient(135deg, #2198bd 0%, #51cc8e 100%);
    color: white;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.rad-card-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

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

.modern-table td {
    padding: 10px 16px;
    font-size: 13px;
    color: var(--text-primary);
    vertical-align: top;
}

.pemeriksaan-row {
    border-top: 3px solid var(--primary) !important;
    background: #fafbfc;
}

.pemeriksaan-row:first-of-type {
    border-top: none !important;
}

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

.pemeriksaan-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
    font-size: 14px;
}

.pemeriksaan-subtitle {
    font-size: 12px;
    color: var(--text-secondary);
    font-weight: 500;
}

/* ==============================================
   DETAIL SECTION - DILUAR TABLE
   ============================================== */
.detail-section-outside {
    display: none;
    margin: 0 16px 16px 16px;
    animation: slideDown 0.4s ease;
}

.detail-section-outside.show {
    display: block;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.detail-section {
    padding: 16px;
    background: #f8f9fa;
    margin: 0;
    border-radius: 8px;
    border-left: 4px solid var(--primary);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
    align-items: start;
}

.detail-left-column {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.detail-right-column {
    position: sticky;
    top: 20px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.detail-item label {
    display: block;
    color: #7f8c8d;
    font-weight: 600;
    margin-bottom: 3px;
    text-transform: uppercase;
    font-size: 10px;
}

.detail-item .value {
    color: #2c3e50;
    padding: 6px 10px;
    background: #ffffff;
    border-radius: 4px;
    font-size: 12px;
    border: 1px solid #e9ecef;
}

.hasil-section {
    margin-top: 0;
    padding: 10px;
    background: #fff9e6;
    border-radius: 4px;
    border-left: 3px solid #f39c12;
}

.hasil-section label {
    display: block;
    color: #7f8c8d;
    font-weight: 600;
    margin-bottom: 5px;
    text-transform: uppercase;
    font-size: 10px;
}

.hasil-section .value {
    color: #2c3e50;
    line-height: 1.5;
    white-space: pre-line;
    font-size: 12px;
}

.image-section {
    margin-top: 0;
}

.image-section label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #7f8c8d;
    text-transform: uppercase;
    font-size: 10px;
}

.rad-image-container {
    text-align: center;
    background: #ffffff;
    padding: 10px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.rad-image-container img {
    max-width: 100%;
    width: 100%;
    height: auto;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    cursor: pointer;
}

/* Multiple Images Layout - AI per gambar */
.single-image-wrapper {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 2px solid var(--border);
}

.single-image-wrapper:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.single-image-wrapper .rad-image-container {
    margin-bottom: 12px;
}

.single-image-wrapper .btn-ai {
    width: 100%;
    margin-bottom: 16px;
}

.single-image-wrapper .ai-result {
    margin-top: 0;
}

.btn-ai {
    width: 100%;
    margin-top: 10px;
    padding: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-ai:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

.btn-ai .icon {
    font-size: 18px;
}

.ai-result {
    margin-top: 10px;
    padding: 10px;
    background: #f0f4ff;
    border-radius: 6px;
    border-left: 3px solid #667eea;
    display: none;
}

.ai-result.show {
    display: block;
    animation: fadeIn 0.3s;
}

.ai-result h5 {
    color: #667eea;
    font-size: 12px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.ai-result .content {
    color: #2c3e50;
    line-height: 1.5;
    font-size: 12px;
}

.loading-ai {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #667eea;
    font-size: 13px;
}

.loading-ai .spinner {
    width: 16px;
    height: 16px;
    border: 2px solid #e0e0e0;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.no-hasil-notice {
    padding: 8px 10px;
    background: #fff3cd;
    border-radius: 4px;
    border-left: 3px solid #ffc107;
    color: #856404;
    font-size: 12px;
    margin: 0;
}

/* ==============================================
   DICOM VIEWER BUTTON & STYLES
   ============================================== */
.btn-dicom-viewer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    width: 100%;
    margin-top: 8px;
    margin-bottom: 6px;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(37,99,235,0.25);
}

.btn-dicom-viewer:hover {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(37,99,235,0.35);
}

.btn-dicom-viewer:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-dicom-viewer .icon {
    font-size: 16px;
}

.dicom-status {
    font-size: 12px;
    margin-bottom: 8px;
}

.dicom-study-list {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 10px;
}

.dicom-study-list-title {
    font-weight: 700;
    color: #1e40af;
    font-size: 11px;
    text-transform: uppercase;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.dicom-study-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid #dbeafe;
    gap: 8px;
}

.dicom-study-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.dicom-study-info {
    flex: 1;
    min-width: 0;
}

.dicom-study-info .study-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dicom-study-info .study-meta {
    color: #64748b;
    font-size: 11px;
    margin-top: 2px;
}

.btn-open-study {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    color: white;
    border: none;
    border-radius: 5px;
    padding: 5px 10px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 4px;
}

.btn-open-study:hover {
    background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(37,99,235,0.4);
}

.dicom-not-found {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #92400e;
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 6px;
    padding: 7px 10px;
    font-size: 11px;
    margin-bottom: 8px;
}

.dicom-error {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 6px;
    padding: 7px 10px;
    font-size: 11px;
    margin-bottom: 8px;
}

/* ==============================================
   RESPONSIVE
   ============================================== */
@media (max-width: 1200px) {
    .detail-section {
        grid-template-columns: 1fr;
        gap: 16px;
    }
    
    .detail-right-column {
        position: static;
    }
}

@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
}

.empty-state i {
    font-size: 72px;
    color: #cbd5e1;
    margin-bottom: 16px;
    display: block;
}

.empty-state h4 {
    color: var(--text-secondary);
    font-weight: 500;
    margin: 0 0 8px 0;
}

.empty-state p {
    color: #94a3b8;
    font-size: 14px;
}

.rad-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin: 16px 0 36px 0;
}

.rad-pagination .btn {
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: white;
    cursor: pointer;
}

.rad-pagination .page-info {
    padding: 6px 12px;
    background: #eef2ff;
    border-radius: 6px;
    color: var(--primary-dark);
    font-weight: 600;
}

.btn-expand {
    padding: 6px 14px;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-expand:hover {
    background: #2980b9;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(52, 152, 219, 0.3);
}

.btn-expand.active {
    background: #e74c3c;
}

.btn-expand .icon {
    font-size: 10px;
    transition: transform 0.2s;
}

.btn-expand.active .icon {
    transform: rotate(180deg);
}
</style>

<div class="rad-filter-header">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="font-size:18px; font-weight:600; display:flex; align-items:center; gap:8px;">
                <i class="material-icons" style="font-size:20px;">medical_services</i>
                Riwayat Radiologi
            </div>

            <div>
                <label style="font-size:12px; opacity:0.9; display:block;">Filter No. Rawat:</label>
                <select id="filterNoRawatRad" class="form-control" style="width:180px; border-radius:6px;">
                    <option value="">-- Semua No. Rawat --</option>
                    <?php
                    mysqli_data_seek($qAllRawat, 0);
                    while($r = mysqli_fetch_array($qAllRawat)) {
                        $sel = ($selectedNoRawat === $r['no_rawat']) ? "selected" : "";
                        echo "<option $sel value='{$r['no_rawat']}'>{$r['no_rawat']}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:16px;">
            <div style="font-size:13px; color:var(--text-secondary);">
                <i class="material-icons" style="font-size:16px; vertical-align:middle;">schedule</i>
                Terakhir diupdate: <?= date("d/m/Y H:i:s") ?>
            </div>

            <button id="reloadRadRiwayat" class="btn btn-light" style="border-radius:6px; padding:6px 12px;">
                <i class="material-icons" style="font-size:16px; vertical-align:middle;">refresh</i>
                Reload
            </button>
        </div>
    </div>
</div>

<div class="rad-container" id="rad_container_inner">
    <?php
    if($totalRow > 0) {
        while($rawat = mysqli_fetch_array($query_rawat)) {
            $no_rawat = $rawat['no_rawat'];
            $card_id = "card_" . str_replace(['/', ' ', ':', '.'], '_', $no_rawat);

            echo "<div class='rad-card' id='$card_id'>";
            echo "<div class='rad-card-header'>";
            echo "<h4><i class='material-icons'>assignment</i> Pemeriksaan Radiologi</h4>";
            echo "<div style='font-size: 15px; font-weight: 700; letter-spacing: 0.5px;'>";
            echo "<i class='material-icons' style='vertical-align: middle; font-size: 18px; margin-right: 5px;'>folder_open</i>";
            echo "No. Rawat: " . htmlspecialchars($no_rawat);
            echo "</div>";
            echo "</div>";

            echo "<table class='modern-table'>";
            echo "<thead>";
            echo "<tr>";
            echo "<th width='5%'>No.</th>";
            echo "<th width='15%'>Tanggal</th>";
            echo "<th width='10%'>Kode</th>";
            echo "<th width='35%'>Nama Pemeriksaan</th>";
            echo "<th width='17%'>Dokter Perujuk</th>";
            echo "<th width='18%'>Petugas</th>";
            echo "</tr>";
            echo "</thead>";
            echo "<tbody>";

            $query_detail = bukaquery("
                SELECT 
                    pr.kd_jenis_prw,
                    pr.tgl_periksa,
                    pr.jam,
                    pr.status,
                    pr.dokter_perujuk,
                    pr.nip,
                    jpr.nm_perawatan,
                    d.nm_dokter,
                    p.nama AS nama_petugas
                FROM periksa_radiologi pr
                LEFT JOIN jns_perawatan_radiologi jpr ON pr.kd_jenis_prw = jpr.kd_jenis_prw
                LEFT JOIN dokter d ON pr.dokter_perujuk = d.kd_dokter
                LEFT JOIN petugas p ON pr.nip = p.nip
                WHERE pr.no_rawat = '$no_rawat'
                ORDER BY pr.tgl_periksa DESC, pr.jam DESC
            ");

            $no_pemeriksaan = 0;

            while($detail = mysqli_fetch_array($query_detail)) {
                $no_pemeriksaan++;
                $kd_jenis_prw = $detail['kd_jenis_prw'];
                $tgl_periksa = $detail['tgl_periksa'];
                $jam = $detail['jam'];

                $status_class = ($detail['status'] == 'Ralan') ? 'badge-ralan' : 'badge-ranap';
                $status_text = strtoupper($detail['status']);

                $separator_class = ($no_pemeriksaan > 1) ? 'pemeriksaan-row' : '';
                $row_id = $card_id . "_rad_" . str_replace(['/', ' ', ':', '.'], '_', $tgl_periksa . "_" . $jam . "_" . $kd_jenis_prw);

                // RENDER TABLE ROW
                echo "<tr class='$separator_class'>";
                echo "<td align='center' style='font-size: 14px; font-weight: 600; color: var(--primary);'>$no_pemeriksaan</td>";
                echo "<td style='padding: 8px 16px;'>";
                echo "<div style='font-weight: 600; font-size: 13px;'>" . konversiTanggal($tgl_periksa) . "</div>";
                echo "<div style='font-size: 12px; color: var(--text-secondary); margin-top: 1px;'>" . substr($jam, 0, 5) . "</div>";
                echo "<div style='margin-top: 4px;'><span class='status-badge $status_class'>$status_text</span></div>";
                echo "</td>";
                echo "<td style='padding: 8px 16px;'><strong style='font-size: 13px;'>" . htmlspecialchars($kd_jenis_prw) . "</strong></td>";
                echo "<td style='padding: 8px 16px;'>";
                echo "<div class='pemeriksaan-title'>" . htmlspecialchars($detail['nm_perawatan']) . "</div>";
                echo "<button class='btn-expand' onclick='toggleRadDetail(\"$row_id\")' id='btn_$row_id'>";
                echo "<span class='icon'>▼</span>";
                echo "<span class='text'>Lihat Detail</span>";
                echo "</button>";
                echo "</td>";
                echo "<td style='padding: 8px 16px;'>" . htmlspecialchars($detail['nm_dokter']) . "</td>";
                echo "<td style='padding: 8px 16px;'>" . htmlspecialchars($detail['nama_petugas']) . "</td>";
                echo "</tr>";

                // ==========================================
                // RENDER DETAIL SECTION
                // ==========================================
                echo "<tr>";
                echo "<td colspan='6' style='padding: 0; border: none;'>";
                
                echo "<div class='detail-section-outside' id='$row_id' style='display:none;'>";
                echo "<div class='detail-section'>";

                // Cek hasil radiologi
                $query_hasil = bukaquery("
                    SELECT tgl_periksa, jam, hasil
                    FROM hasil_radiologi
                    WHERE no_rawat = '$no_rawat'
                    AND tgl_periksa = '$tgl_periksa'
                    AND jam = '$jam'
                    LIMIT 1
                ");

                $ada_hasil = mysqli_num_rows($query_hasil) > 0;
                $hasil_data = $ada_hasil ? mysqli_fetch_array($query_hasil) : null;

                // Cek gambar radiologi
                $query_gambar = bukaquery("
                    SELECT tgl_periksa, jam, lokasi_gambar
                    FROM gambar_radiologi
                    WHERE no_rawat = '$no_rawat'
                    AND tgl_periksa = '$tgl_periksa'
                    AND jam = '$jam'
                ");

                $ada_gambar = mysqli_num_rows($query_gambar) > 0;

                // LEFT COLUMN
                echo "<div class='detail-left-column'>";

                    echo "<div class='detail-grid'>";
                    
                    echo "<div class='detail-item'>";
                    echo "<label>No. Rawat</label>";
                    echo "<div class='value'>" . htmlspecialchars($no_rawat) . "</div>";
                    echo "</div>";
                    
                    echo "<div class='detail-item'>";
                    echo "<label>Tanggal Pemeriksaan</label>";
                    echo "<div class='value'>" . konversiTanggal($tgl_periksa) . " " . substr($jam, 0, 5) . "</div>";
                    echo "</div>";

                    if($ada_hasil) {
                        echo "<div class='detail-item'>";
                        echo "<label>Tanggal Hasil</label>";
                        echo "<div class='value'>" . konversiTanggal($hasil_data['tgl_periksa']) . " " . substr($hasil_data['jam'], 0, 5) . "</div>";
                        echo "</div>";
                    }

                    echo "<div class='detail-item'>";
                    echo "<label>Status</label>";
                    echo "<div class='value'><span class='status-badge $status_class'>$status_text</span></div>";
                    echo "</div>";
                    
                    echo "</div>"; // End detail-grid

                    // Hasil Pemeriksaan
                    if($ada_hasil && !empty($hasil_data['hasil'])) {
                        echo "<div class='hasil-section'>";
                        echo "<label>📋 Hasil Pemeriksaan</label>";
                        echo "<div class='value'>" . nl2br(htmlspecialchars($hasil_data['hasil'])) . "</div>";
                        echo "</div>";
                    } else {
                        echo "<div class='no-hasil-notice'>";
                        echo "⚠️ Hasil pemeriksaan belum tersedia.";
                        echo "</div>";
                    }

                echo "</div>"; // End detail-left-column

                // RIGHT COLUMN
                echo "<div class='detail-right-column'>";

                    // ==========================================
                    // TOMBOL DICOM VIEWER (Stone Web Viewer)
                    // ==========================================
                    $dicom_btn_id = $row_id . '_dicom';
                    echo "<button class='btn-dicom-viewer' id='btn_dicom_$dicom_btn_id' style='display:none;' ";
                    echo "onclick='openDicomViewer(\"" . htmlspecialchars($dicom_btn_id, ENT_QUOTES) . "\", \"" . htmlspecialchars($norm, ENT_QUOTES) . "\", \"" . htmlspecialchars($tgl_periksa, ENT_QUOTES) . "\")'>";
                    echo "<span class='icon'>🩻</span>";
                    echo "<span>Buka DICOM Viewer</span>";
                    echo "</button>";
                    echo "<div class='dicom-status' id='dicom_status_$dicom_btn_id'></div>";

                    // ==========================================
                    // GAMBAR RADIOLOGI (dari file lokal)
                    // ==========================================
                    if($ada_gambar) {
                        echo "<div class='image-section'>";
                        echo "<label>🖼️ Gambar Radiologi</label>";
                        
                        mysqli_data_seek($query_gambar, 0);
                        
                        $img_index = 0;
                        while($gambar = mysqli_fetch_array($query_gambar)) {
                            $img_index++;
                            $img_id = $row_id . '_img' . $img_index;
                            
                            $lokasi_relative = $gambar['lokasi_gambar'];
                            $lokasi_full = RADIOLOGI_BASE_URL . $lokasi_relative;
                            
                            $pathInfo = pathinfo($lokasi_relative);
                            $lokasi_thumb_relative = $pathInfo['dirname'] . '/thumb_' . $pathInfo['basename'];
                            $lokasi_thumb = RADIOLOGI_BASE_URL . $lokasi_thumb_relative;
                            
                            $server_thumb_path = $_SERVER['DOCUMENT_ROOT'] . '/webapps/radiologi/' . $lokasi_thumb_relative;
                            $display_src = file_exists($server_thumb_path) ? $lokasi_thumb : $lokasi_full;
                            
                            // Container per gambar
                            echo "<div class='single-image-wrapper'>";
                            
                            // Image container
                            echo "<div class='rad-image-container'>";
                            echo "<img class='rad-lazy-image' ";
                            echo "data-thumb='$lokasi_thumb' ";
                            echo "data-full='$lokasi_full' ";
                            echo "src='$display_src' ";
                            echo "alt='Radiologi' ";
                            echo "title='Klik untuk melihat gambar penuh'>";
                            echo "</div>";
                            
                            // Tombol AI per gambar
                            echo "<button class='btn-ai btn-ai-analyze' id='btn_ai_$img_id' onclick='analyzeRadWithAI(\"$img_id\", \"$lokasi_full\")'>";
                            echo "<span class='icon'>🤖</span>";
                            echo "<span>Analisis dengan AI</span>";
                            echo "</button>";
                            
                            // Result container per gambar
                            echo "<div class='ai-result' id='ai_result_$img_id'>";
                            echo "<h5><span>🤖</span><span>Hasil Analisis AI</span></h5>";
                            echo "<div class='content'></div>";
                            echo "</div>";
                            
                            echo "</div>"; // single-image-wrapper
                        }
                        
                        echo "</div>"; // image-section
                    }

                echo "</div>"; // End detail-right-column

                echo "</div>"; // End detail-section
                echo "</div>"; // End detail-section-outside
                
                echo "</td>";
                echo "</tr>";
                // ==========================================
                // END RENDER DETAIL SECTION
                // ==========================================
            }

            echo "</tbody>";
            echo "</table>";
            
            echo "</div>"; // End rad-card
        }

        // Pagination
        echo "<div class='rad-pagination' role='navigation' aria-label='Pagination'>";
        if ($page > 1) {
            echo "<button class='btn rad-page' data-page='" . ($page - 1) . "'>&laquo; Prev</button>";
        }
        echo "<span class='page-info'>Halaman $page / $totalPages</span>";
        if ($page < $totalPages) {
            echo "<button class='btn rad-page' data-page='" . ($page + 1) . "'>Next &raquo;</button>";
        }
        echo "</div>";

    } else {
        echo "<div class='empty-state'>";
        echo "<i class='material-icons'>medical_services</i>";
        echo "<h4>Belum Ada Data Radiologi</h4>";
        echo "<p>Pasien ini belum memiliki riwayat pemeriksaan radiologi</p>";
        echo "</div>";
    }
    ?>
</div>

<script src="js/riwayat_rad.js?v=<?= time() ?>"></script>