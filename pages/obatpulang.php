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

// Ambil kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// === AMBIL RIWAYAT RESEP PULANG ===
$queryRiwayat = bukaquery("SELECT 
                            prp.no_permintaan,
                            prp.tgl_permintaan,
                            prp.jam,
                            prp.no_rawat,
                            prp.kd_dokter,
                            prp.status,
                            d.nm_dokter,
                            rp.no_rkm_medis,
                            p.nm_pasien
                        FROM permintaan_resep_pulang prp
                        INNER JOIN dokter d ON prp.kd_dokter = d.kd_dokter
                        INNER JOIN reg_periksa rp ON prp.no_rawat = rp.no_rawat
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        WHERE prp.no_rawat = '$no_rawat'
                        ORDER BY prp.tgl_permintaan DESC, prp.jam DESC");
?>

<!-- CSS Local - template4.css -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<style>
/* Additional Custom Styles for Obat Pulang */
.obat-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
}

.obat-card-header {
    padding: 15px 20px;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
}

.obat-card-body {
    padding: 25px;
}

.search-box {
    position: relative;
}

/* CRITICAL: Autocomplete dropdown */
.list-group.search-results {
    position: absolute;
    z-index: 9999 !important;
    width: 100%;
    max-height: 250px;
    overflow-y: auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    margin-top: 5px;
    display: none;
    border: 1px solid #ddd;
    list-style: none;
    padding: 0;
}

.list-group.search-results .list-group-item {
    border: none;
    border-bottom: 1px solid #f0f0f0;
    padding: 12px 15px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
}

.list-group.search-results .list-group-item:hover {
    background: #f5f5f5;
}

.list-group.search-results .list-group-item:last-child {
    border-bottom: none;
}

.keranjang-badge {
    background: #fff;
    color: #4caf50;
    margin-left: 8px;
    border-radius: 10px;
    padding: 3px 10px;
    font-size: 12px;
    font-weight: 600;
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
}

.empty-state .material-icons {
    font-size: 48px;
    display: block;
    margin-bottom: 10px;
    opacity: 0.3;
}

/* Input di keranjang */
table input.form-control {
    padding: 5px 8px;
    font-size: 13px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

table input.form-control:focus {
    border-color: #4caf50;
    outline: none;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

/* === RIWAYAT RESEP - BACKGROUND PUTIH + RESPONSIVE === */
.riwayat-header-container {
    border-radius: 8px 8px 0 0;
    background: #673ab7;
    padding: 12px 20px;
    border-radius: 8px 8px 0 0;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.riwayat-title {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.riwayat-reload {
    font-size: 12px;
    opacity: 0.9;
}

/* Wrapper untuk horizontal scroll */
.riwayat-table-wrapper {
    width: 100%;
    overflow-x: auto !important;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    position: relative;
}

/* Scrollbar styling untuk desktop */
.riwayat-table-wrapper::-webkit-scrollbar {
    height: 8px;
}

.riwayat-table-wrapper::-webkit-scrollbar-track {
    background: #f1f3f5;
    border-radius: 4px;
}

.riwayat-table-wrapper::-webkit-scrollbar-thumb {
    background: #673ab7;
    border-radius: 4px;
}

.riwayat-table-wrapper::-webkit-scrollbar-thumb:hover {
    background: #5e35b1;
}

/* Tabel Utama Riwayat */
.riwayat-table {
    width: 100%;
    min-width: 800px; /* Minimum width untuk scroll horizontal - 3 kolom */
    border-collapse: collapse;
    background: white;
    margin-bottom: 15px;
}

.riwayat-table-header {
    background: #5e35b1;
    color: white;
}

.riwayat-table-header th {
    padding: 12px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.riwayat-table-header th:last-child {
    border-right: none;
}

/* Row Resep */
.resep-row {
    border-bottom: 2px solid #e0e0e0;
}

/* Background PUTIH dengan border */
.resep-info-cell {
    background: white;
    color: #333;
    padding: 15px;
    vertical-align: top;
    border-right: 1px solid #e0e0e0;
}

.resep-info-cell:last-child {
    border-right: none;
}

.resep-info-label {
    font-size: 11px;
    color: #666;
    margin-bottom: 3px;
}

.resep-info-value {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
}

.resep-info-small {
    font-size: 11px;
    line-height: 1.5;
    color: #555;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    background: #ff9800;
    color: white;
}

.status-badge.sudah {
    background: #4caf50;
}

.status-badge .material-icons {
    font-size: 14px;
}

/* Detail Resep Cell */
.detail-resep-cell {
    padding: 0;
    vertical-align: top;
}

.detail-header {
    background: #f5f5f5;
    padding: 10px 15px;
    border-bottom: 2px solid #e0e0e0;
    font-weight: 600;
    font-size: 12px;
    color: #555;
}

.detail-table {
    width: 100%;
    border-collapse: collapse;
}

.detail-table thead th {
    background: #fafafa;
    padding: 8px 10px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: #555;
    border-bottom: 1px solid #e0e0e0;
}

.detail-table tbody td {
    padding: 8px 10px;
    font-size: 12px;
    border-bottom: 1px solid #f5f5f5;
}

.detail-table tbody tr:hover {
    background: #f9f9f9;
}

.kode-obat {
    background: #ffebee;
    color: #c62828;
    padding: 3px 8px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.aturan-pakai {
    color: #2196F3;
    font-weight: 500;
}

/* RESPONSIVE untuk Tablet (768px - 1024px) */
@media (max-width: 1024px) {
    .riwayat-table {
        min-width: 700px; /* Force scroll horizontal di tablet - 3 kolom */
    }
    
    .resep-info-cell {
        padding: 12px;
        font-size: 11px;
    }
    
    .resep-info-value {
        font-size: 12px;
    }
    
    .status-badge {
        font-size: 10px;
        padding: 5px 10px;
    }
    
    .detail-table thead th,
    .detail-table tbody td {
        padding: 6px 8px;
        font-size: 11px;
    }
}

/* RESPONSIVE untuk Mobile (< 768px) */
@media (max-width: 768px) {
    .riwayat-table {
        min-width: 650px; /* Force scroll horizontal di mobile - 3 kolom */
    }
    
    .riwayat-title {
        font-size: 14px;
    }
    
    .riwayat-reload {
        font-size: 10px;
    }
    
    .riwayat-header-container {
    border-radius: 8px 8px 0 0;
        padding: 10px 15px !important;
    }
    
    .resep-info-cell {
        padding: 10px;
        font-size: 10px;
    }
    
    .resep-info-value {
        font-size: 11px;
    }
    
    .status-badge {
        font-size: 9px;
        padding: 4px 8px;
    }
    
    .detail-table thead th,
    .detail-table tbody td {
        padding: 5px 6px;
        font-size: 10px;
    }
    
    /* Button Hapus di mobile */
    .btn-danger.btn-sm {
        padding: 4px 10px !important;
        font-size: 10px !important;
    }
    
    .btn-danger.btn-sm .material-icons {
        font-size: 12px !important;
    }
}

/* RESPONSIVE untuk Desktop Large (> 1200px) */
@media (min-width: 1200px) {
    .riwayat-table {
        min-width: 100%; /* Full width di desktop besar */
    }
}
</style>

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <h1 style="margin-bottom: 8px;">
            <i class="material-icons">local_pharmacy</i>
            OBAT PULANG
        </h1>
        <div class="patient-info">
            <div class="info-item">
                <i class="material-icons">folder</i>
                <strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?>
            </div>
            <div class="info-item">
                <i class="material-icons">badge</i>
                <strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?>
            </div>
            <div class="info-item">
                <i class="material-icons">person</i>
                <strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?>
            </div>
            <div class="info-item">
                <i class="material-icons">cake</i>
                <strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)
            </div>
            <div class="info-item">
                <i class="material-icons">person</i>
                <strong>Dokter:</strong> <?php echo $rsPasien['nm_dokter']; ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="form-card">
        <div class="form-content">
            
            <!-- SECTION: INPUT RESEP OBAT PULANG -->
            <div class="section" style="padding: 20px;">
                <div class="section-header" style="padding: 0 0 15px 0; margin-bottom: 15px;">
                    <i class="material-icons" style="font-size: 20px;">medication</i>
                    <h2 style="font-size: 15px; font-weight: 600; margin: 0;">INPUT RESEP OBAT PULANG</h2>
                </div>

                <form id="formObatPulang">
                    <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                    <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
                    
                    <div style="display: grid; grid-template-columns: 2fr 0.7fr 0.5fr 2fr; gap: 15px;">
                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 500; margin-bottom: 6px; display: block;">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">inventory_2</i>
                                Nama Obat <span style="color: #f44336;">*</span>
                            </label>
                            <div class="search-box">
                                <input type="text" 
                                       name="nama_obat_search" 
                                       id="nama_obat_search" 
                                       placeholder="Ketik nama obat..." 
                                       autocomplete="off"
                                       style="padding: 8px 12px; font-size: 13px; width: 100%;">
                                <input type="hidden" name="kd_brng" id="kd_brng">
                                <input type="hidden" name="harga_obat" id="harga_obat">
                                <input type="hidden" name="stok_obat" id="stok_obat">
                                <ul id="obatList" class="list-group search-results"></ul>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 500; margin-bottom: 6px; display: block;">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">inventory</i>
                                Stok
                            </label>
                            <input type="text" 
                                   id="stok_obat_display" 
                                   placeholder="-" 
                                   readonly
                                   style="padding: 8px 10px; font-size: 14px; width: 100%; text-align: center; background-color: #f5f5f5; color: #2196F3; font-weight: 700; border: 2px solid #e0e0e0; border-radius: 8px;">
                        </div>

                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 500; margin-bottom: 6px; display: block;">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">shopping_cart</i>
                                Jml <span style="color: #f44336;">*</span>
                            </label>
                            <input type="number" 
                                   name="jumlah" 
                                   id="jumlah" 
                                   placeholder="0" 
                                   min="1" 
                                   value="1"
                                   style="padding: 8px 10px; font-size: 13px; width: 70%; text-align: center;">
                        </div>
                        
                        <div class="form-group">
                            <label style="font-size: 13px; font-weight: 500; margin-bottom: 6px; display: block;">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">schedule</i>
                                Aturan Pakai <span style="color: #f44336;">*</span>
                            </label>
                            <input type="text" 
                                   name="aturan_pakai" 
                                   id="aturan_pakai" 
                                   placeholder="Contoh: 3x1 sehari sesudah makan"
                                   style="padding: 8px 12px; font-size: 13px; width: 100%;">
                        </div>
                    </div>

                    <div style="margin-top: 15px;">
                        <button type="button" 
                                id="btnTambahkanObat" 
                                class="btn btn-primary"
                                style="padding: 10px 20px; font-size: 13px;">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle;">add_shopping_cart</i>
                            Tambahkan ke Keranjang
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <!-- KERANJANG OBAT -->
    <div id="wrapperKeranjang" class="form-card" style="display: none;">
        <div class="obat-card">
            <div class="obat-card-header">
                <h2 style="margin: 0; font-weight: 600; font-size: 16px;">
                    <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">shopping_basket</i>
                    Keranjang Obat Pulang
                    <span class="keranjang-badge" id="badgeKeranjang">0</span>
                </h2>
                <button id="btnKosongkanKeranjang" 
                        class="btn btn-danger" 
                        style="padding: 6px 14px; font-size: 12px;">
                    <i class="material-icons" style="font-size: 16px;">delete_sweep</i>
                    Kosongkan
                </button>
            </div>
            <div style="padding: 0;">
                <table class="table table-bordered" style="margin: 0;">
                    <thead style="background: #f5f5f5;">
                        <tr>
                            <th width="5%">No</th>
                            <th width="12%">Kode Obat</th>
                            <th width="33%">Nama Obat</th>
                            <th width="10%">Stok</th>
                            <th width="10%">Jumlah</th>
                            <th width="20%">Aturan Pakai</th>
                            <th width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="keranjangObat">
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="material-icons">shopping_cart</i>
                                <em>Keranjang masih kosong</em>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIWAYAT RESEP PULANG TERSIMPAN - RESPONSIVE -->
    <?php if(mysqli_num_rows($queryRiwayat) > 0): ?>
    <div style="background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; overflow-x: auto; overflow-y: visible;">
        <div class="riwayat-header-container">
            <h2 class="riwayat-title">
                <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">history</i>
                Riwayat Resep Pulang Tersimpan
            </h2>
            <div class="riwayat-reload">
                <i class="material-icons" style="vertical-align: middle; font-size: 16px;">refresh</i>
                Auto-reload setiap 10 detik
            </div>
        </div>

        <!-- Wrapper untuk horizontal scroll -->
        <div class="riwayat-table-wrapper">
            <table class="riwayat-table">
                <thead class="riwayat-table-header">
                    <tr>
                        <th width="20%">NOMOR RESEP</th>
                        <th width="25%">PASIEN</th>
                        <th width="55%">DETAIL RESEP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    while($rsRiwayat = mysqli_fetch_array($queryRiwayat)):
                        // Ambil detail obat
                        $no_permintaan = $rsRiwayat['no_permintaan'];
                        $queryDetail = bukaquery("SELECT 
                                                    d.kode_brng,
                                                    db.nama_brng,
                                                    d.jml,
                                                    d.dosis,
                                                    db.kode_sat
                                                FROM detail_permintaan_resep_pulang d
                                                INNER JOIN databarang db ON d.kode_brng = db.kode_brng
                                                WHERE d.no_permintaan = '$no_permintaan'
                                                ORDER BY db.nama_brng ASC");
                    ?>
                    <tr class="resep-row">
                        <!-- Kolom 1: Nomor Resep -->
                        <td class="resep-info-cell">
                            <div class="resep-info-label">No. Resep:</div>
                            <div class="resep-info-value"><?php echo $rsRiwayat['no_permintaan']; ?></div>
                            <div class="resep-info-small">
                                <i class="material-icons" style="font-size: 12px; vertical-align: middle;">person</i>
                                Dokter Peresep: <?php echo $rsRiwayat['nm_dokter']; ?>
                            </div>
                            <div class="resep-info-small" style="margin-top: 5px;">
                                Tanggal & Jam:<br>
                                <?php echo date('d/m/Y', strtotime($rsRiwayat['tgl_permintaan'])); ?> • <?php echo substr($rsRiwayat['jam'], 0, 5); ?>
                            </div>
                        </td>

                        <!-- Kolom 2: Pasien + Status -->
                        <td class="resep-info-cell">
                            <div class="resep-info-label">No. Rawat:</div>
                            <div class="resep-info-value" style="font-size: 12px;"><?php echo $rsRiwayat['no_rawat']; ?></div>
                            <div class="resep-info-small">
                                Nama Pasien:<br>
                                <strong><?php echo strtoupper($rsRiwayat['nm_pasien']); ?></strong>
                            </div>
                            <div class="resep-info-small">
                                No. RM: <?php echo $rsRiwayat['no_rkm_medis']; ?>
                            </div>
                            
                            <!-- Status Badge -->
                            <div style="margin-top: 10px;">
                                <span class="status-badge <?php echo ($rsRiwayat['status'] == 'Sudah') ? 'sudah' : ''; ?>">
                                    <i class="material-icons"><?php echo ($rsRiwayat['status'] == 'Sudah') ? 'check_circle' : 'pending'; ?></i>
                                    <?php echo ($rsRiwayat['status'] == 'Sudah') ? 'Sudah Terlayani' : 'Belum Terlayani'; ?>
                                </span>
                            </div>
                            
                            <!-- Tombol Hapus -->
                            <?php if($rsRiwayat['status'] == 'Belum'): ?>
                            <div style="margin-top: 8px;">
                                <button class="btn btn-danger btn-sm" 
                                        onclick="hapusResepPulang('<?php echo $rsRiwayat['no_permintaan']; ?>')" 
                                        style="padding: 5px 12px; font-size: 11px;">
                                    <i class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</i>
                                    Hapus
                                </button>
                            </div>
                            <?php endif; ?>
                        </td>

                        <!-- Kolom 4: Detail Resep -->
                        <td class="detail-resep-cell">                            <table class="detail-table">
                                <thead>
                                    <tr>
                                        <th width="8%">No</th>
                                        <th width="12%">JML</th>
                                        <th width="30%">ATURAN PAKAI</th>
                                        <th width="50%">NAMA OBAT/RACIKAN</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    while($rsDetail = mysqli_fetch_array($queryDetail)): 
                                    ?>
                                    <tr>
                                        <td style="text-align: center;"><?php echo $no++; ?></td>
                                        <td style="text-align: center;"><strong><?php echo number_format($rsDetail['jml'], 0); ?></strong></td>
                                        <td><span class="aturan-pakai"><?php echo $rsDetail['dosis']; ?></span></td>
                                        <td><strong><?php echo $rsDetail['nama_brng']; ?></strong></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Action Bar -->
    <div class="action-bar">
        <button type="button" class="btn btn-secondary" onclick="window.history.back();">
            <i class="material-icons">arrow_back</i>
            KEMBALI
        </button>
        <button type="button" id="btn-simpan-semua" class="btn btn-primary">
            <i class="material-icons">save</i>
            SIMPAN OBAT PULANG
        </button>
    </div>
</div>

<!-- Pass PHP variable to JavaScript -->
<script>
const APP_BASE_URL = '<?php echo defined("APP_BASE_URL") ? APP_BASE_URL : "/edokter"; ?>';
</script>

<!-- Load JavaScript External File ONLY -->
<script src="<?php echo BASE_URL; ?>/js/obatpulang.js?v=<?php echo time(); ?>"></script>

<!-- Auto Reload Riwayat (10 detik) - DISABLED, akan pakai reload setelah simpan -->
<script>
// TIDAK PAKAI AUTO-RELOAD 10 DETIK
// Akan reload otomatis setelah simpan sukses saja
</script>