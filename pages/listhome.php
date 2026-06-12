<?php
// Ambil kode dokter yang login
$kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"],"d"),20);

// ============================================
// AMBIL STOK OBAT MENIPIS
// ============================================

// Ambil default depo dari set_lokasi
$kd_bangsal_default = 'AP';
$query_lokasi = bukaquery("SELECT kd_bangsal FROM set_lokasi LIMIT 1");
if($row_lokasi = mysqli_fetch_array($query_lokasi)){
    if(!empty($row_lokasi['kd_bangsal'])){
        $kd_bangsal_default = trim($row_lokasi['kd_bangsal']);
    }
}

// Query obat dengan stok menipis (stok <= stokminimal dan stok > 0)
// Order by persentase stok/stokminimal agar yang paling kritis muncul duluan
// Contoh: stok 1/min 10 (10%) lebih kritis dari stok 9/min 10 (90%)
$query_stok_menipis = bukaquery("
    SELECT 
        db.kode_brng,
        db.nama_brng,
        FLOOR(gb.stok) as stok,
        db.stokminimal,
        b.nm_bangsal as nama_depo,
        ROUND((gb.stok / db.stokminimal) * 100, 1) as persen_sisa
    FROM gudangbarang gb
    INNER JOIN databarang db ON gb.kode_brng = db.kode_brng
    INNER JOIN bangsal b ON gb.kd_bangsal = b.kd_bangsal
    WHERE db.status = '1'
        AND (gb.no_batch = '' OR gb.no_batch IS NULL)
        AND (gb.no_faktur = '' OR gb.no_faktur IS NULL)
        AND gb.stok > 0
        AND gb.stok <= db.stokminimal
        AND db.stokminimal > 0
        AND gb.kd_bangsal = '$kd_bangsal_default'
    ORDER BY (gb.stok / db.stokminimal) ASC, gb.stok ASC
    LIMIT 50
");

$stok_menipis = [];
while($row = mysqli_fetch_assoc($query_stok_menipis)){
    $stok_menipis[] = $row;
}
?>

<!-- Dashboard Header -->
<div class="dashboard-header">
    <h1>Dashboard</h1>
    <p>Selamat datang di Sistem Manajemen Rumah Sakit</p>
</div>

<!-- Stats Cards - Original Layout -->
<div class="row clearfix">
    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="stat-card primary">
            <div class="stat-card-header">
                <span class="stat-card-title">KUNJUNGAN</span>
                <div class="stat-card-icon">
                    <i class="material-icons">enhanced_encryption</i>
                </div>
            </div>
            <div class="stat-card-value count-to" data-from="0" data-to="<?=getOne("SELECT count(reg_periksa.no_rkm_medis) FROM reg_periksa WHERE reg_periksa.kd_dokter='$kd_dokter' and reg_periksa.tgl_registrasi=current_date()");?>" data-speed="2000">0</div>
            <div class="stat-card-label">Pasien hari ini</div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="stat-card info">
            <div class="stat-card-header">
                <span class="stat-card-title">RAWAT JALAN</span>
                <div class="stat-card-icon">
                    <i class="material-icons">airline_seat_recline_normal</i>
                </div>
            </div>
            <div class="stat-card-value count-to" data-from="0" data-to="<?=getOne("SELECT count(reg_periksa.no_rkm_medis) FROM reg_periksa WHERE reg_periksa.kd_dokter='$kd_dokter' and reg_periksa.tgl_registrasi=current_date() AND reg_periksa.status_lanjut = 'Ralan'");?>" data-speed="2000">0</div>
            <div class="stat-card-label">Pasien rawat jalan</div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="stat-card success">
            <div class="stat-card-header">
                <span class="stat-card-title">LANJUT RANAP</span>
                <div class="stat-card-icon">
                    <i class="material-icons">local_hotel</i>
                </div>
            </div>
            <div class="stat-card-value count-to" data-from="0" data-to="<?=getOne("SELECT count(reg_periksa.no_rkm_medis) FROM reg_periksa WHERE reg_periksa.kd_dokter='$kd_dokter' and reg_periksa.tgl_registrasi=current_date() AND reg_periksa.status_lanjut = 'Ranap'");?>" data-speed="2000">0</div>
            <div class="stat-card-label">Pasien rawat inap</div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
        <div class="stat-card warning">
            <div class="stat-card-header">
                <span class="stat-card-title">BELUM DILAYANI</span>
                <div class="stat-card-icon">
                    <i class="material-icons">schedule</i>
                </div>
            </div>
            <div class="stat-card-value count-to" data-from="0" data-to="<?=getOne("SELECT count(reg_periksa.no_rkm_medis) FROM reg_periksa WHERE reg_periksa.kd_dokter='$kd_dokter' and reg_periksa.tgl_registrasi=current_date() AND reg_periksa.stts = 'Belum'");?>" data-speed="2000">0</div>
            <div class="stat-card-label">Menunggu pemeriksaan</div>
        </div>
    </div>
</div>

<!-- Pengingat Stok Obat Menipis -->
<?php if(count($stok_menipis) > 0): ?>
<div class="row clearfix">
    <div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
        <div class="reminder-card">
            <div class="reminder-header">
                <i class="material-icons">error_outline</i>
                <span>Pengingat</span>
            </div>
            <div class="reminder-body">
                <div class="reminder-alert">
                    <i class="material-icons">medication</i>
                    <span class="alert-title">Stok Obat Menipis</span>
                </div>
                <ul class="stok-list">
                    <?php foreach($stok_menipis as $obat): 
                        $persen = (int)$obat['persen_sisa'];
                        // Tentukan level kritis
                        if($persen <= 20){
                            $badge_class = 'badge-critical';
                        } elseif($persen <= 50){
                            $badge_class = 'badge-warning';
                        } else {
                            $badge_class = 'badge-low';
                        }
                    ?>
                    <li class="stok-item">
                        <span class="obat-nama"><?= htmlspecialchars($obat['nama_brng']); ?></span>
                        <span class="obat-depo">(<?= htmlspecialchars($obat['nama_depo']); ?>)</span>
                        <span class="obat-stok <?= $badge_class; ?>"><?= (int)$obat['stok']; ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Reminder Card Styles */
.reminder-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-top: 10px;
    overflow: hidden;
}

.reminder-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 15px 20px;
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    color: #dc3545;
    font-weight: 600;
    font-size: 16px;
}

.reminder-header i {
    font-size: 22px;
}

.reminder-body {
    padding: 15px 20px;
}

.reminder-alert {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff5f5;
    border: 1px solid #fed7d7;
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 15px;
    color: #c53030;
}

.reminder-alert i {
    font-size: 20px;
}

.alert-title {
    font-weight: 600;
    font-size: 14px;
}

.stok-list {
    list-style: none;
    padding: 0;
    margin: 0;
    max-height: 350px;
    overflow-y: auto;
}

.stok-item {
    padding: 8px 12px;
    border-bottom: 1px solid #f5f5f5;
    font-size: 13px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 5px;
}

.stok-item:last-child {
    border-bottom: none;
}

.stok-item:before {
    content: '•';
    color: #dc3545;
    font-weight: bold;
    margin-right: 5px;
}

.obat-nama {
    color: #333;
    font-weight: 500;
}

.obat-depo {
    color: #888;
    font-size: 12px;
}

.obat-stok {
    font-weight: 600;
    margin-left: auto;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 12px;
    min-width: 28px;
    text-align: center;
}

/* Badge levels */
.badge-critical {
    background: #fee2e2;
    color: #dc2626;
}

.badge-warning {
    background: #fef3c7;
    color: #d97706;
}

.badge-low {
    background: #dbeafe;
    color: #2563eb;
}

/* Responsive */
@media (max-width: 768px) {
    .stok-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .obat-stok {
        margin-left: 15px;
    }
}
</style>

