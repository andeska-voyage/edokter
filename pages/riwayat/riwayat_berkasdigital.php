<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

// Get parameters
$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

// Query untuk ambil data berkas digital dengan join ke master
$query = "
    SELECT 
        bdp.no_rawat,
        bdp.kode,
        bdp.lokasi_file,
        mbd.nama as nama_berkas,
        DATE_FORMAT(rp.tgl_registrasi, '%d/%m/%Y') as tgl_registrasi,
        TIME_FORMAT(rp.jam_reg, '%H:%i') as jam_registrasi
    FROM berkas_digital_perawatan bdp
    LEFT JOIN reg_periksa rp ON bdp.no_rawat = rp.no_rawat
    LEFT JOIN master_berkas_digital mbd ON bdp.kode = mbd.kode
    WHERE bdp.no_rawat = '$no_rawat'
    ORDER BY bdp.kode ASC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data berkas digital tidak ditemukan</div>';
    exit;
}

// Function untuk deteksi tipe file
function getFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
        return 'image';
    } elseif ($ext == 'pdf') {
        return 'pdf';
    } else {
        return 'unknown';
    }
}

// Function untuk mendapatkan icon berdasarkan tipe file
function getFileIcon($filename) {
    $type = getFileType($filename);
    switch ($type) {
        case 'image':
            return 'fa-file-image-o';
        case 'pdf':
            return 'fa-file-pdf-o';
        default:
            return 'fa-file-o';
    }
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.berkas-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    padding: 10px;
}

@media (max-width: 768px) {
    .berkas-grid {
        grid-template-columns: 1fr;
    }
}

.card-berkas-mini {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
    background: #fff;
    display: flex;
    flex-direction: column;
}

.card-berkas-mini:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.berkas-thumbnail {
    width: 100%;
    background-color: #f8f9fa;
    display: block;
    position: relative;
    overflow: hidden;
    text-decoration: none;
}

.berkas-thumbnail img,
.berkas-thumbnail embed {
    width: 100%;
    height: auto;
    display: block;
}

.berkas-thumbnail .file-icon-wrapper {
    padding: 40px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    min-height: 200px;
}

.berkas-thumbnail .file-icon {
    font-size: 80px;
    color: #dc3545;
    margin-bottom: 10px;
}

.berkas-thumbnail .file-label {
    font-size: 14px;
    color: #6c757d;
    font-weight: bold;
}

.berkas-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.berkas-thumbnail:hover .berkas-overlay {
    opacity: 1;
}

.berkas-overlay i {
    color: white;
    font-size: 40px;
}

.berkas-info-mini {
    padding: 12px;
    background: #f8f9fa;
}

.berkas-nama {
    font-weight: bold;
    font-size: 15px;
    color: #333;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.berkas-nama i {
    color: #007bff;
}

.berkas-kode-mini {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 5px;
}

.berkas-waktu-mini {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 10px;
}

.berkas-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.btn-action-mini {
    flex: 1;
    padding: 6px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 12px;
    text-align: center;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.btn-view-mini {
    background-color: #007bff;
    color: white;
}

.btn-view-mini:hover {
    background-color: #0056b3;
    color: white;
}

.btn-download-mini {
    background-color: #28a745;
    color: white;
}

.btn-download-mini:hover {
    background-color: #218838;
    color: white;
}

/* Wrapper untuk PDF agar auto height */
.pdf-wrapper {
    position: relative;
    width: 100%;
    overflow: hidden;
}

.pdf-wrapper embed {
    width: 100%;
    height: auto;
    min-height: 400px;
}
</style>

<!-- Grid Berkas -->
<div class="berkas-grid">
    <?php while ($row = mysqli_fetch_assoc($result)): 
        $file_url = BERKAS_DIGITAL_BASE_URL . $row['lokasi_file'];
        $file_type = getFileType($row['lokasi_file']);
        $file_icon = getFileIcon($row['lokasi_file']);
    ?>
    <div class="card-berkas-mini">
        <!-- Thumbnail yang bisa diklik -->
        <a href="<?= $file_url ?>" target="_blank" class="berkas-thumbnail">
            <?php if ($file_type == 'image'): ?>
                <img src="<?= $file_url ?>" alt="<?= htmlspecialchars($row['nama_berkas']) ?>" loading="lazy">
            <?php elseif ($file_type == 'pdf'): ?>
                <div class="pdf-wrapper">
                    <embed 
                        src="<?= $file_url ?>#toolbar=0&navpanes=0&scrollbar=0&view=FitH" 
                        type="application/pdf"
                    />
                </div>
            <?php else: ?>
                <div class="file-icon-wrapper">
                    <i class="fa <?= $file_icon ?> file-icon"></i>
                    <span class="file-label">Klik untuk melihat</span>
                </div>
            <?php endif; ?>
            <div class="berkas-overlay">
                <i class="fa fa-external-link"></i>
            </div>
        </a>
        
        <!-- Info Berkas -->
        <div class="berkas-info-mini">
            <div class="berkas-nama">
                <i class="fa <?= $file_icon ?>"></i>
                <?= htmlspecialchars($row['nama_berkas'] ?: 'Berkas Digital') ?>
            </div>
            <div class="berkas-kode-mini">
                <i class="fa fa-barcode"></i>
                Kode: <?= htmlspecialchars($row['kode']) ?>
            </div>
            <div class="berkas-waktu-mini">
                <i class="fa fa-clock-o"></i>
                <?= htmlspecialchars($row['tgl_registrasi']) ?> 
                <?= htmlspecialchars($row['jam_registrasi']) ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="berkas-actions">
                <a href="<?= $file_url ?>" target="_blank" class="btn-action-mini btn-view-mini">
                    <i class="fa fa-eye"></i> Lihat
                </a>
                <a href="<?= $file_url ?>" download class="btn-action-mini btn-download-mini">
                    <i class="fa fa-download"></i> Unduh
                </a>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>