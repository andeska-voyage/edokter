<?php
session_start();
require_once('../../conf/conf.php');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo "<div class='alert alert-danger'>Session expired</div>";
    exit();
}

// Ambil parameter NO RAWAT
$no_rawat = isset($_REQUEST['id']) ? validTeks4($_REQUEST['id'], 20) : '';

if(empty($no_rawat)){
    echo "<div class='alert alert-warning'>Parameter tidak lengkap.</div>";
    exit();
}
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
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
}

.rad-container::-webkit-scrollbar { width: 7px; }
.rad-container::-webkit-scrollbar-track { background: #f1f3f5; border-radius: 4px; }
.rad-container::-webkit-scrollbar-thumb { background: #667eea; border-radius: 4px; }
.rad-container::-webkit-scrollbar-thumb:hover { background: #5568d3; }

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
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table thead { background: #f1f5f9; }

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

.modern-table tbody tr:hover { background: #f8fafc; }

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

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-ralan { background: #dcfce7; color: #166534; }
.badge-ranap { background: #fee2e2; color: #991b1b; }

.pemeriksaan-title {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
    font-size: 14px;
}

/* Detail Section */
.detail-section-outside {
    display: block;
    margin: 0 16px 16px 16px;
    animation: slideDown 0.4s ease;
}

.detail-section-outside.show { display: block; }

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
}

.detail-section {
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid var(--primary);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
    align-items: start;
}

.detail-left-column  { display: flex; flex-direction: column; gap: 12px; }
.detail-right-column { position: sticky; top: 20px; }

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

.single-image-wrapper .rad-image-container { margin-bottom: 12px; }
.single-image-wrapper .btn-ai { width: 100%; margin-bottom: 16px; }
.single-image-wrapper .ai-result { margin-top: 0; }

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

.btn-ai .icon { font-size: 18px; }

.ai-result {
    margin-top: 10px;
    padding: 10px;
    background: #f0f4ff;
    border-radius: 6px;
    border-left: 3px solid #667eea;
    display: none;
}

.ai-result.show { display: block; animation: fadeIn 0.3s; }

.ai-result h5 {
    color: #667eea;
    font-size: 12px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.ai-result .content { color: #2c3e50; line-height: 1.5; font-size: 12px; }

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

@keyframes spin    { to { transform: rotate(360deg); } }
@keyframes fadeIn  { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

.no-hasil-notice {
    padding: 8px 10px;
    background: #fff3cd;
    border-radius: 4px;
    border-left: 3px solid #ffc107;
    color: #856404;
    font-size: 12px;
}

@media (max-width: 1200px) {
    .detail-section { grid-template-columns: 1fr; gap: 16px; }
    .detail-right-column { position: static; }
}

@media (max-width: 768px) {
    .detail-grid { grid-template-columns: 1fr; }
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
}

.empty-state i    { font-size: 72px; color: #cbd5e1; margin-bottom: 16px; display: block; }
.empty-state h4   { color: var(--text-secondary); font-weight: 500; margin: 0 0 8px 0; }
.empty-state p    { color: #94a3b8; font-size: 14px; }

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
    box-shadow: 0 2px 4px rgba(52,152,219,0.3);
}

.btn-expand.active { background: #e74c3c; }
.btn-expand .icon  { font-size: 10px; transition: transform 0.2s; }
.btn-expand.active .icon { transform: rotate(180deg); }
</style>

<div class="rad-container">
<?php
// Cek apakah ada data untuk no_rawat ini
$qCek = bukaquery("SELECT COUNT(*) as jml FROM periksa_radiologi WHERE no_rawat = '$no_rawat'");
$rowCek = mysqli_fetch_assoc($qCek);

if($rowCek['jml'] > 0) {

    $card_id = "card_" . str_replace(['/', ' ', ':', '.'], '_', $no_rawat);

    echo "<div class='rad-card' id='$card_id'>";
    echo "<div class='rad-card-header'>";
    echo "<h4><i class='material-icons'>assignment</i> Pemeriksaan Radiologi</h4>";
    echo "<div style='font-size:15px; font-weight:700; letter-spacing:0.5px;'>";
    echo "<i class='material-icons' style='vertical-align:middle; font-size:18px; margin-right:5px;'>folder_open</i>";
    echo "No. Rawat: " . htmlspecialchars($no_rawat);
    echo "</div>";
    echo "</div>";

    echo "<table class='modern-table'>";
    echo "<thead><tr>";
    echo "<th width='5%'>No.</th>";
    echo "<th width='15%'>Tanggal</th>";
    echo "<th width='10%'>Kode</th>";
    echo "<th width='35%'>Nama Pemeriksaan</th>";
    echo "<th width='17%'>Dokter Perujuk</th>";
    echo "<th width='18%'>Petugas</th>";
    echo "</tr></thead><tbody>";

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
        $tgl_periksa  = $detail['tgl_periksa'];
        $jam          = $detail['jam'];

        $status_class = ($detail['status'] == 'Ralan') ? 'badge-ralan' : 'badge-ranap';
        $status_text  = strtoupper($detail['status']);

        $separator_class = ($no_pemeriksaan > 1) ? 'pemeriksaan-row' : '';
        $row_id = $card_id . "_rad_" . str_replace(['/', ' ', ':', '.'], '_', $tgl_periksa . "_" . $jam . "_" . $kd_jenis_prw);

        // Baris tabel utama
        echo "<tr class='$separator_class'>";
        echo "<td align='center' style='font-size:14px; font-weight:600; color:var(--primary);'>$no_pemeriksaan</td>";
        echo "<td style='padding:8px 16px;'>";
        echo "<div style='font-weight:600; font-size:13px;'>" . konversiTanggal($tgl_periksa) . "</div>";
        echo "<div style='font-size:12px; color:var(--text-secondary); margin-top:1px;'>" . substr($jam, 0, 5) . "</div>";
        echo "<div style='margin-top:4px;'><span class='status-badge $status_class'>$status_text</span></div>";
        echo "</td>";
        echo "<td style='padding:8px 16px;'><strong style='font-size:13px;'>" . htmlspecialchars($kd_jenis_prw) . "</strong></td>";
        echo "<td style='padding:8px 16px;'>";
        echo "<div class='pemeriksaan-title'>" . htmlspecialchars($detail['nm_perawatan']) . "</div>";
        echo "</td>";
        echo "<td style='padding:8px 16px;'>" . htmlspecialchars($detail['nm_dokter']) . "</td>";
        echo "<td style='padding:8px 16px;'>" . htmlspecialchars($detail['nama_petugas']) . "</td>";
        echo "</tr>";

        // Baris detail (langsung tampil)
        echo "<tr><td colspan='6' style='padding:0; border:none;'>";
        echo "<div class='detail-section-outside' id='$row_id'>";
        echo "<div class='detail-section'>";

        // Query hasil
        $query_hasil = bukaquery("
            SELECT tgl_periksa, jam, hasil
            FROM hasil_radiologi
            WHERE no_rawat = '$no_rawat'
            AND tgl_periksa = '$tgl_periksa'
            AND jam = '$jam'
            LIMIT 1
        ");
        $ada_hasil  = mysqli_num_rows($query_hasil) > 0;
        $hasil_data = $ada_hasil ? mysqli_fetch_array($query_hasil) : null;

        // Query gambar
        $query_gambar = bukaquery("
            SELECT tgl_periksa, jam, lokasi_gambar
            FROM gambar_radiologi
            WHERE no_rawat = '$no_rawat'
            AND tgl_periksa = '$tgl_periksa'
            AND jam = '$jam'
        ");
        $ada_gambar = mysqli_num_rows($query_gambar) > 0;

        // Kolom Kiri
        echo "<div class='detail-left-column'>";
        echo "<div class='detail-grid'>";

        echo "<div class='detail-item'><label>No. Rawat</label><div class='value'>" . htmlspecialchars($no_rawat) . "</div></div>";
        echo "<div class='detail-item'><label>Tanggal Pemeriksaan</label><div class='value'>" . konversiTanggal($tgl_periksa) . " " . substr($jam, 0, 5) . "</div></div>";

        if($ada_hasil) {
            echo "<div class='detail-item'><label>Tanggal Hasil</label><div class='value'>" . konversiTanggal($hasil_data['tgl_periksa']) . " " . substr($hasil_data['jam'], 0, 5) . "</div></div>";
        }

        echo "<div class='detail-item'><label>Status</label><div class='value'><span class='status-badge $status_class'>$status_text</span></div></div>";
        echo "</div>"; // end detail-grid

        if($ada_hasil && !empty($hasil_data['hasil'])) {
            echo "<div class='hasil-section'><label>📋 Hasil Pemeriksaan</label>";
            echo "<div class='value'>" . nl2br(htmlspecialchars($hasil_data['hasil'])) . "</div></div>";
        } else {
            echo "<div class='no-hasil-notice'>⚠️ Hasil pemeriksaan belum tersedia.</div>";
        }

        echo "</div>"; // end detail-left-column

        // Kolom Kanan (gambar)
        if($ada_gambar) {
            echo "<div class='detail-right-column'>";
            echo "<div class='image-section'>";
            echo "<label>🖼️ Gambar Radiologi</label>";

            mysqli_data_seek($query_gambar, 0);
            $img_index = 0;

            while($gambar = mysqli_fetch_array($query_gambar)) {
                $img_index++;
                $img_id = $row_id . '_img' . $img_index;

                $lokasi_relative      = $gambar['lokasi_gambar'];
                $lokasi_full          = RADIOLOGI_BASE_URL . $lokasi_relative;
                $pathInfo             = pathinfo($lokasi_relative);
                $lokasi_thumb_rel     = $pathInfo['dirname'] . '/thumb_' . $pathInfo['basename'];
                $lokasi_thumb         = RADIOLOGI_BASE_URL . $lokasi_thumb_rel;
                $server_thumb_path    = $_SERVER['DOCUMENT_ROOT'] . '/webapps/radiologi/' . $lokasi_thumb_rel;
                $display_src          = file_exists($server_thumb_path) ? $lokasi_thumb : $lokasi_full;

                echo "<div class='single-image-wrapper'>";
                echo "<div class='rad-image-container'>";
                echo "<img class='rad-lazy-image' data-thumb='$lokasi_thumb' data-full='$lokasi_full' src='$display_src' alt='Radiologi' title='Klik untuk melihat gambar penuh' style='cursor:pointer;' onclick=\"window.open('$lokasi_full', '_blank')\">";  
                echo "</div>";
                echo "<button class='btn-ai btn-ai-analyze' id='btn_ai_$img_id' onclick='analyzeRadWithAI(\"$img_id\", \"$lokasi_full\")'>";
                echo "<span class='icon'>🤖</span><span>Analisis dengan AI</span>";
                echo "</button>";
                echo "<div class='ai-result' id='ai_result_$img_id'>";
                echo "<h5><span>🤖</span><span>Hasil Analisis AI</span></h5>";
                echo "<div class='content'></div></div>";
                echo "</div>"; // single-image-wrapper
            }

            echo "</div>"; // image-section
            echo "</div>"; // detail-right-column
        }

        echo "</div>"; // end detail-section
        echo "</div>"; // end detail-section-outside
        echo "</td></tr>";

    } // end while detail

    echo "</tbody></table>";
    echo "</div>"; // end rad-card

} else {
    echo "<div class='empty-state'>";
    echo "<i class='material-icons'>medical_services</i>";
    echo "<h4>Belum Ada Data Radiologi</h4>";
    echo "<p>Kunjungan ini belum memiliki riwayat pemeriksaan radiologi</p>";
    echo "</div>";
}
?>
</div>

<script>
(function(){
    // Detail selalu terbuka, fungsi toggle tidak dipakai lagi

    console.log('\u2705 riwayat/riwayat_radiologi.php loaded');
})();
</script>
