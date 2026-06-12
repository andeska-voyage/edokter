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

// ===================================
// QUERY UTAMA: DATA TRIASE IGD
// ===================================
$query_triase = "
    SELECT 
        t.no_rawat,
        t.tgl_kunjungan,
        t.cara_masuk,
        t.alat_transportasi,
        t.alasan_kedatangan,
        t.keterangan_kedatangan,
        t.kode_kasus,
        mk.macam_kasus,
        t.tekanan_darah,
        t.nadi,
        t.pernapasan,
        t.suhu,
        t.saturasi_o2,
        t.nyeri
    FROM data_triase_igd t
    LEFT JOIN master_triase_macam_kasus mk ON t.kode_kasus = mk.kode_kasus
    WHERE t.no_rawat = '$no_rawat'
";

$result_triase = bukaquery($query_triase);
$data_triase = mysqli_fetch_assoc($result_triase);

if (!$data_triase) {
    echo '<div class="alert alert-warning m-3">Data triase IGD tidak ditemukan</div>';
    exit;
}

// ===================================
// CEK DATA TRIASE PRIMER
// ===================================
$query_primer = "
    SELECT 
        p.no_rawat,
        p.keluhan_utama,
        p.kebutuhan_khusus,
        p.catatan,
        p.plan,
        p.nik,
        pg.nama as nama_petugas,
        t.tgl_kunjungan as tanggal_triase
    FROM data_triase_igdprimer p
    LEFT JOIN pegawai pg ON p.nik = pg.nik
    LEFT JOIN data_triase_igd t ON p.no_rawat = t.no_rawat
    WHERE p.no_rawat = '$no_rawat'
";

$result_primer = bukaquery($query_primer);
$data_primer = mysqli_fetch_assoc($result_primer);

// ===================================
// CEK DATA TRIASE SEKUNDER
// ===================================
$query_sekunder = "
    SELECT 
        s.no_rawat,
        s.anamnesa_singkat,
        s.catatan,
        s.plan,
        s.nik,
        pg.nama as nama_petugas,
        s.tanggaltriase as tanggal_triase
    FROM data_triase_igdsekunder s
    LEFT JOIN pegawai pg ON s.nik = pg.nik
    WHERE s.no_rawat = '$no_rawat'
";

$result_sekunder = bukaquery($query_sekunder);
$data_sekunder = mysqli_fetch_assoc($result_sekunder);

// ===================================
// AMBIL DETAIL SKALA BERDASARKAN PLAN
// ===================================

// Detail Skala 1-5
$detail_skala1 = [];
$detail_skala2 = [];
$detail_skala3 = [];
$detail_skala4 = [];
$detail_skala5 = [];

if ($data_primer && $data_primer['plan'] == 'Ruang Resusitasi') {
    $query_skala1 = "SELECT d.kode_skala1, m.pengkajian_skala1, m.kode_pemeriksaan, p.nama_pemeriksaan
        FROM data_triase_igddetail_skala1 d
        LEFT JOIN master_triase_skala1 m ON d.kode_skala1 = m.kode_skala1
        LEFT JOIN master_triase_pemeriksaan p ON m.kode_pemeriksaan = p.kode_pemeriksaan
        WHERE d.no_rawat = '$no_rawat' ORDER BY m.kode_pemeriksaan, d.kode_skala1";
    $result_skala1 = bukaquery($query_skala1);
    while ($row = mysqli_fetch_assoc($result_skala1)) {
        $detail_skala1[] = $row;
    }
}

if ($data_primer && $data_primer['plan'] == 'Ruang Kritis') {
    $query_skala2 = "SELECT d.kode_skala2, m.pengkajian_skala2, m.kode_pemeriksaan, p.nama_pemeriksaan
        FROM data_triase_igddetail_skala2 d
        LEFT JOIN master_triase_skala2 m ON d.kode_skala2 = m.kode_skala2
        LEFT JOIN master_triase_pemeriksaan p ON m.kode_pemeriksaan = p.kode_pemeriksaan
        WHERE d.no_rawat = '$no_rawat' ORDER BY m.kode_pemeriksaan, d.kode_skala2";
    $result_skala2 = bukaquery($query_skala2);
    while ($row = mysqli_fetch_assoc($result_skala2)) {
        $detail_skala2[] = $row;
    }
}

if ($data_sekunder && $data_sekunder['plan'] == 'Zona Kuning') {
    $query_skala3 = "SELECT d.kode_skala3, m.pengkajian_skala3, m.kode_pemeriksaan, p.nama_pemeriksaan
        FROM data_triase_igddetail_skala3 d
        LEFT JOIN master_triase_skala3 m ON d.kode_skala3 = m.kode_skala3
        LEFT JOIN master_triase_pemeriksaan p ON m.kode_pemeriksaan = p.kode_pemeriksaan
        WHERE d.no_rawat = '$no_rawat' ORDER BY m.kode_pemeriksaan, d.kode_skala3";
    $result_skala3 = bukaquery($query_skala3);
    while ($row = mysqli_fetch_assoc($result_skala3)) {
        $detail_skala3[] = $row;
    }
}

if ($data_sekunder && $data_sekunder['plan'] == 'Zona Hijau') {
    $query_skala4 = "SELECT d.kode_skala4, m.pengkajian_skala4, m.kode_pemeriksaan, p.nama_pemeriksaan
        FROM data_triase_igddetail_skala4 d
        LEFT JOIN master_triase_skala4 m ON d.kode_skala4 = m.kode_skala4
        LEFT JOIN master_triase_pemeriksaan p ON m.kode_pemeriksaan = p.kode_pemeriksaan
        WHERE d.no_rawat = '$no_rawat' ORDER BY m.kode_pemeriksaan, d.kode_skala4";
    $result_skala4 = bukaquery($query_skala4);
    while ($row = mysqli_fetch_assoc($result_skala4)) {
        $detail_skala4[] = $row;
    }
    
    $query_skala5 = "SELECT d.kode_skala5, m.pengkajian_skala5, m.kode_pemeriksaan, p.nama_pemeriksaan
        FROM data_triase_igddetail_skala5 d
        LEFT JOIN master_triase_skala5 m ON d.kode_skala5 = m.kode_skala5
        LEFT JOIN master_triase_pemeriksaan p ON m.kode_pemeriksaan = p.kode_pemeriksaan
        WHERE d.no_rawat = '$no_rawat' ORDER BY m.kode_pemeriksaan, d.kode_skala5";
    $result_skala5 = bukaquery($query_skala5);
    while ($row = mysqli_fetch_assoc($result_skala5)) {
        $detail_skala5[] = $row;
    }
}

// Badge mapping
$plan_badge = match($data_sekunder['plan'] ?? $data_primer['plan'] ?? '') {
    'Ruang Resusitasi' => ['class' => 'merah', 'text' => 'MERAH - Emergensi'],
    'Ruang Kritis' => ['class' => 'merah', 'text' => 'MERAH - Kritis'],
    'Zona Kuning' => ['class' => 'kuning', 'text' => 'KUNING - Urgensi'],
    'Zona Hijau' => ['class' => 'hijau', 'text' => 'HIJAU - Non Urgensi'],
    default => ['class' => 'abu', 'text' => '-']
};
?>

<style>
/* Main Card */
.triase-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.triase-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

/* Header - Compact dengan info penting */
.triase-header {
    background: linear-gradient(135deg, #dc3545 0%, #c62828 100%);
    color: white;
    padding: 18px 20px;
    cursor: pointer;
    position: relative;
}

.triase-header:hover {
    background: linear-gradient(135deg, #c62828 0%, #b71c1c 100%);
}

/* Badge Styles */
.triase-badge {
    display: inline-block;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-merah { background: #ffebee; color: #c62828; }
.badge-kuning { background: #fff8e1; color: #f57f17; }
.badge-hijau { background: #e8f5e9; color: #2e7d32; }
.badge-abu { background: #f5f5f5; color: #616161; }

/* Toggle Button */
.detail-toggle-btn {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 8px 18px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.detail-toggle-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

.detail-toggle-btn.active {
    background: white;
    color: #dc3545;
}

/* Detail Section - Hidden by default */
.triase-detail {
    display: none;
    padding: 25px;
    background: #fafbfc;
    border-top: 1px solid #e2e8f0;
}

/* Section Title */
.section-title {
    color: #dc3545;
    font-weight: 500;
    margin-bottom: 15px;
    font-size: 15px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Info Table - Borderless & Clean */
.info-table-clean {
    width: 100%;
    font-size: 14px;
}

.info-table-clean tr {
    border-bottom: 1px solid #f1f5f9;
}

.info-table-clean tr:last-child {
    border-bottom: none;
}

.info-table-clean td {
    padding: 4px 0;
    vertical-align: middle; text-align: left;
}

.info-table-clean td:first-child {
    width: 150px; text-align: left;
    color: #64748b;
    font-weight: 600;
    padding-right: 15px;
}

.info-table-clean td:last-child {
    color: #334155;
    font-weight: 500;
}

/* Vital Signs - Big & Bold */
.vital-value-big {
    font-size: 16px;
    font-weight: 500;
    color: #1e293b;
}

/* List Compact */
.list-compact {
    list-style: none;
    padding: 0;
    margin: 0;
}

.list-compact li {
    padding: 6px 0 6px 20px;
    position: relative;
    font-size: 14px;
    color: #475569;
    line-height: 1.6;
}

.list-compact li:before {
    content: "•";
    position: absolute;
    left: 5px;
    color: #10b981;
    font-weight: bold;
    font-size: 18px;
}
</style>

<style>
/* Section Title */
.section-title-inline {
    color: #dc3545;
    font-weight: 500;
    margin-bottom: 15px;
    margin-top: 20px;
    font-size: 15px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title-inline:first-child {
    margin-top: 0;
}

/* Info Table - Borderless & Clean */
.info-table-clean {
    width: 100%;
    font-size: 14px;
}

.info-table-clean tr {
    border-bottom: 1px solid #f1f5f9;
}

.info-table-clean tr:last-child {
    border-bottom: none;
}

.info-table-clean td {
    padding: 4px 0;
    vertical-align: middle; text-align: left;
}

.info-table-clean td:first-child {
    width: 150px; text-align: left;
    color: #64748b;
    font-weight: 600;
    padding-right: 15px;
}

.info-table-clean td:last-child {
    color: #334155;
    font-weight: 500;
}

/* Vital Signs - Big & Bold */
.vital-value-big {
    font-size: 14px;
    font-weight: 500;
    color: #1e293b;
}

/* Compact vital table rows */
.vital-table td {
    padding: 3px 0 !important;
}

.vital-table td:first-child {
    font-size: 13px;
    width: 140px !important;
}

/* List Compact */
.list-compact {
    list-style: none;
    padding: 0;
    margin: 0;
}

.list-compact li {
    padding: 6px 0 6px 20px;
    position: relative;
    font-size: 14px;
    color: #475569;
    line-height: 1.6;
}

.list-compact li:before {
    content: "•";
    position: absolute;
    left: 5px;
    color: #10b981;
    font-weight: bold;
    font-size: 18px;
}

/* Badge Styles */
.triase-badge-inline {
    display: inline-block;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-merah { background: #ffebee; color: #c62828; }
.badge-kuning { background: #fff8e1; color: #f57f17; }
.badge-hijau { background: #e8f5e9; color: #2e7d32; }
.badge-abu { background: #f5f5f5; color: #616161; }

/* Content Box */
.content-box-highlight {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #dc3545;
    margin-bottom: 15px;
}

.content-box-secondary {
    background: white;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #0dcaf0;
    margin-bottom: 15px;
}
</style>

<!-- DATA TRIASE FLAT CONTENT (Tanpa Card Wrapper) -->
<div style="padding: 15px;">
    
    <!-- Data Umum & Tanda Vital & Info Sekunder - 3 Columns -->
    <div style="margin-bottom: 25px;">
        <h6 class="section-title-inline">
            <i class="fa fa-info-circle"></i>
            Informasi Triase
        </h6>
        <div class="row">
            <!-- Kolom 1: Data Umum -->
            <div class="col-md-4">
                <table class="info-table-clean">
                    <tr>
                        <td>Cara Masuk</td>
                        <td>: <?= htmlspecialchars($data_triase['cara_masuk'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td>Transportasi</td>
                        <td>: <?= htmlspecialchars($data_triase['alat_transportasi'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td>Alasan Kedatangan</td>
                        <td>: <?= htmlspecialchars($data_triase['alasan_kedatangan'] ?: '-') ?></td>
                    </tr>
                    <?php if (!empty($data_triase['keterangan_kedatangan'])): ?>
                    <tr>
                        <td>Keterangan</td>
                        <td>: <?= htmlspecialchars($data_triase['keterangan_kedatangan']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Macam Kasus</td>
                        <td>: <span class="badge bg-info" style="font-size: 12px;"><?= htmlspecialchars($data_triase['macam_kasus'] ?: '-') ?></span></td>
                    </tr>
                </table>
            </div>
            
            <!-- Kolom 2: Tanda Vital -->
            <div class="col-md-4">
                <table class="info-table-clean">
                    <tr>
                        <td>Suhu (°C)</td>
                        <td>: <span class="vital-value-big"><?= htmlspecialchars($data_triase['suhu'] ?: '-') ?></span></td>
                    </tr>
                    <tr>
                        <td>Nyeri</td>
                        <td>: <span class="vital-value-big"><?= htmlspecialchars($data_triase['nyeri'] ?: '-') ?></span></td>
                    </tr>
                    <tr>
                        <td>Tekanan Darah</td>
                        <td>: <span class="vital-value-big"><?= htmlspecialchars($data_triase['tekanan_darah'] ?: '-') ?></span></td>
                    </tr>
                    <tr>
                        <td>Nadi (/menit)</td>
                        <td>: <span class="vital-value-big"><?= htmlspecialchars($data_triase['nadi'] ?: '-') ?></span></td>
                    </tr>
                    <tr>
                        <td>Saturasi O2 (%)</td>
                        <td>: <span class="vital-value-big"><?= htmlspecialchars($data_triase['saturasi_o2'] ?: '-') ?></span></td>
                    </tr>
                    <tr>
                        <td>Respirasi (/menit)</td>
                        <td>: <span class="vital-value-big"><?= htmlspecialchars($data_triase['pernapasan'] ?: '-') ?></span></td>
                    </tr>
                </table>
            </div>
            
            <!-- Kolom 3: Info Triase Primer/Sekunder -->
            <div class="col-md-4">
                <table class="info-table-clean">
                    <?php if ($data_primer): ?>
                    <!-- JIKA ADA DATA PRIMER, TAMPILKAN PRIMER -->
                    <tr>
                        <td>Pemeriksaan</td>
                        <td>
                            : <span class="triase-badge-inline badge-<?= $plan_badge['class'] ?>">
                                <?= $plan_badge['text'] ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Plan/Keputusan</td>
                        <td>: <strong><?= htmlspecialchars($data_primer['plan']) ?></strong></td>
                    </tr>
                    <?php if ($data_primer['tanggal_triase']): ?>
                    <tr>
                        <td>Tanggal Triase</td>
                        <td>: <?= date('d-m-Y H:i', strtotime($data_primer['tanggal_triase'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($data_primer['nama_petugas']): ?>
                    <tr>
                        <td>Petugas</td>
                        <td>: <?= htmlspecialchars($data_primer['nama_petugas']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($data_primer['catatan'])): ?>
                    <tr>
                        <td>Catatan</td>
                        <td>: <?= htmlspecialchars($data_primer['catatan']) ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php elseif ($data_sekunder): ?>
                    <!-- JIKA TIDAK ADA PRIMER, BARU TAMPILKAN SEKUNDER -->
                    <tr>
                        <td>Pemeriksaan</td>
                        <td>
                            : <span class="triase-badge-inline badge-<?= $plan_badge['class'] ?>">
                                <?= $plan_badge['text'] ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Plan/Keputusan</td>
                        <td>: <strong><?= htmlspecialchars($data_sekunder['plan']) ?></strong></td>
                    </tr>
                    <?php if ($data_sekunder['tanggal_triase']): ?>
                    <tr>
                        <td>Tanggal Triase</td>
                        <td>: <?= date('d-m-Y H:i', strtotime($data_sekunder['tanggal_triase'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($data_sekunder['nama_petugas']): ?>
                    <tr>
                        <td>Petugas</td>
                        <td>: <?= htmlspecialchars($data_sekunder['nama_petugas']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($data_sekunder['catatan'])): ?>
                    <tr>
                        <td>Catatan</td>
                        <td>: <?= htmlspecialchars($data_sekunder['catatan']) ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <!-- JIKA TIDAK ADA PRIMER DAN SEKUNDER -->
                    <tr>
                        <td colspan="2" style="color: #94a3b8; font-style: italic; text-align: center; padding: 20px 0;">
                            Belum ada data triase sekunder
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <?php if ($data_primer && !empty($data_primer['keluhan_utama'])): ?>
    <!-- Keluhan Utama -->
    <div style="margin-bottom: 25px;">
        <h6 class="section-title-inline">
            <i class="fa fa-notes-medical"></i>
            Keluhan Utama
        </h6>
        <div class="content-box-highlight">
            <p style="margin: 0; color: #334155; font-size: 14px; line-height: 1.7; font-weight: 500; text-align: left;">
                <?= nl2br(htmlspecialchars($data_primer['keluhan_utama'])) ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Anamnesa Singkat & Detail Pengkajian - 2 Columns -->
    <?php 
    $all_skala = array_merge($detail_skala1, $detail_skala2, $detail_skala3, $detail_skala4, $detail_skala5);
    
    // LOGIC BARU: Prioritas PRIMER, fallback SEKUNDER
    if ($data_primer) {
        // Jika ada PRIMER, ambil data dari PRIMER
        $has_anamnesa = !empty($data_primer['keluhan_utama']);
        $has_detail = !empty($all_skala);
    } else {
        // Jika tidak ada PRIMER, ambil dari SEKUNDER
        $has_anamnesa = ($data_sekunder && !empty($data_sekunder['anamnesa_singkat']));
        $has_detail = !empty($all_skala); // SEKUNDER juga bisa punya detail skala
    }
    
    if ($has_anamnesa || $has_detail): 
    ?>
    <div style="margin-bottom: 25px;">
        <div class="row">
            <!-- Kolom 1: Anamnesa Singkat -->
            <?php if ($has_anamnesa): ?>
            <div class="col-md-6">
                <h6 class="section-title-inline">
                    <i class="fa fa-user-md"></i>
                    Anamnesa Singkat
                </h6>
                <div class="content-box-secondary">
                    <p style="margin: 0; color: #334155; font-size: 14px; line-height: 1.7; font-weight: 500; text-align: left;">
                        <?php 
                        // Prioritas PRIMER (keluhan utama), fallback SEKUNDER
                        if ($data_primer && !empty($data_primer['keluhan_utama'])) {
                            echo nl2br(htmlspecialchars($data_primer['keluhan_utama']));
                        } elseif ($data_sekunder && !empty($data_sekunder['anamnesa_singkat'])) {
                            echo nl2br(htmlspecialchars($data_sekunder['anamnesa_singkat']));
                        }
                        ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Kolom 2: Detail Pengkajian -->
            <?php if ($has_detail): ?>
            <div class="col-md-<?= $has_anamnesa ? '6' : '12' ?>">
                <h6 class="section-title-inline">
                    <i class="fa fa-check-circle"></i>
                    Detail Pengkajian
                </h6>
                <div style="background: white; border-radius: 8px; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <?php 
                        // SKALA 1 - MERAH
                        foreach ($detail_skala1 as $skala): ?>
                        <tr style="background: #dc3545; color: white;">
                            <td style="padding: 6px 12px; font-size: 13px; font-weight: 600; width: 35%; border-bottom: 1px solid rgba(255,255,255,0.1); vertical-align: middle; text-align: left;">
                                <?= strtoupper(htmlspecialchars($skala['nama_pemeriksaan'])) ?>
                            </td>
                            <td style="padding: 6px 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.1); vertical-align: middle; text-align: left;">
                                <?= htmlspecialchars($skala['pengkajian_skala1']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php 
                        // SKALA 2 - MERAH
                        foreach ($detail_skala2 as $skala): ?>
                        <tr style="background: #dc3545; color: white;">
                            <td style="padding: 6px 12px; font-size: 13px; font-weight: 600; width: 35%; border-bottom: 1px solid rgba(255,255,255,0.1); vertical-align: middle; text-align: left;">
                                <?= strtoupper(htmlspecialchars($skala['nama_pemeriksaan'])) ?>
                            </td>
                            <td style="padding: 6px 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.1); vertical-align: middle; text-align: left;">
                                <?= htmlspecialchars($skala['pengkajian_skala2']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php 
                        // SKALA 3 - KUNING
                        foreach ($detail_skala3 as $skala): ?>
                        <tr style="background: #ffc107; color: #1e293b;">
                            <td style="padding: 6px 12px; font-size: 13px; font-weight: 600; width: 35%; border-bottom: 1px solid rgba(0,0,0,0.05); vertical-align: middle; text-align: left;">
                                <?= strtoupper(htmlspecialchars($skala['nama_pemeriksaan'])) ?>
                            </td>
                            <td style="padding: 6px 12px; font-size: 13px; border-bottom: 1px solid rgba(0,0,0,0.05); vertical-align: middle; text-align: left;">
                                <?= htmlspecialchars($skala['pengkajian_skala3']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php 
                        // SKALA 4 - HIJAU
                        foreach ($detail_skala4 as $skala): ?>
                        <tr style="background: #28a745; color: white;">
                            <td style="padding: 6px 12px; font-size: 13px; font-weight: 600; width: 35%; border-bottom: 1px solid rgba(255,255,255,0.1); vertical-align: middle; text-align: left;">
                                <?= strtoupper(htmlspecialchars($skala['nama_pemeriksaan'])) ?>
                            </td>
                            <td style="padding: 6px 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.1); vertical-align: middle; text-align: left;">
                                <?= htmlspecialchars($skala['pengkajian_skala4']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php 
                        // SKALA 5 - ABU
                        foreach ($detail_skala5 as $skala): ?>
                        <tr style="background: #6c757d; color: white;">
                            <td style="padding: 6px 12px; font-size: 13px; font-weight: 600; width: 35%; border-bottom: 1px solid rgba(255,255,255,0.1); vertical-align: middle; text-align: left;">
                                <?= strtoupper(htmlspecialchars($skala['nama_pemeriksaan'])) ?>
                            </td>
                            <td style="padding: 6px 12px; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.1); vertical-align: middle; text-align: left;">
                                <?= htmlspecialchars($skala['pengkajian_skala5']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    
</div>