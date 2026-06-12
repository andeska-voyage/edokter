<?php
session_start();
require_once('../conf/conf.php');

// Validasi session
if (!isset($_SESSION["ses_dokter"])) {
    echo "<div class='alert alert-danger'>Session expired</div>";
    exit();
}

// Ambil parameter
$norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
$norm = isset($_GET['norm']) ? validTeks4($_GET['norm'], 20) : '';

if (empty($norawat) || empty($norm)) {
    echo "<div class='alert alert-danger'>Parameter tidak valid</div>";
    exit();
}
?>

<!-- Style untuk scroll tabel -->
<style>
.table-scroll-wrapper {
    max-height: 350px;
    overflow-y: auto;
    overflow-x: hidden;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.table-scroll-wrapper table {
    margin-bottom: 0;
}

.table-scroll-wrapper thead th {
    position: sticky;
    top: 0;
    background-color: #337ab7 !important;
    color: white !important;
    z-index: 10;
    box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
}

.table-scroll-wrapper::-webkit-scrollbar {
    width: 8px;
}

.table-scroll-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.table-scroll-wrapper::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.table-scroll-wrapper::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Style untuk checkbox */
.checkbox-pemeriksaan {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

#checkAll {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* Highlight row yang dicentang */
.table-scroll-wrapper tbody tr.selected {
    background-color: #d9edf7 !important;
}

.table-scroll-wrapper tbody tr:hover {
    background-color: #f5f5f5;
}

/* Style untuk search box */
.search-box-wrapper {
    margin-bottom: 15px;
    position: relative;
}

.search-box-wrapper input {
    width: 100%;
    padding: 10px 40px 10px 15px;
    border: 2px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.search-box-wrapper input:focus {
    border-color: #337ab7;
    outline: none;
}

.search-box-wrapper .search-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
    pointer-events: none;
}

.search-box-wrapper .clear-search {
    position: absolute;
    right: 35px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #999;
    display: none;
    padding: 5px;
}

.search-box-wrapper .clear-search:hover {
    color: #333;
}

.search-info {
    color: #666;
    font-size: 13px;
    margin-top: 5px;
}

/* Style untuk tabel detail template */
.detail-template-wrapper {
    margin: 10px 0;
    border: 2px solid #4a90e2;
    border-radius: 5px;
    overflow: hidden;
}

.detail-template-table {
    margin-bottom: 0;
    font-size: 13px;
    border-collapse: collapse;
    width: 100%;
}

.detail-template-table td {
    padding: 8px 10px;
    border: 1px solid #ddd;
    vertical-align: middle;
}

.detail-template-table .template-header-detail {
    background-color: #ffeb3b;
    font-weight: bold;
    color: #333;
    padding: 10px;
    border: 1px solid #ddd;
}

.detail-template-table .action-buttons {
    background-color: #f5f5f5;
    padding: 10px;
    border: 1px solid #ddd;
}

.detail-template-table .btn-sm {
    padding: 5px 10px;
    font-size: 12px;
    margin-right: 5px;
}

.detail-template-table .checkbox-col-detail {
    width: 40px;
    text-align: center;
    background-color: #f9f9f9;
}

.detail-template-table .pemeriksaan-col-detail {
    width: 25%;
    background-color: #fff;
}

.detail-template-table .satuan-col-detail {
    width: 10%;
    text-align: center;
    background-color: #fafafa;
}

.detail-template-table .nilai-col-detail {
    width: 45%;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: #333;
    background-color: #fff;
}

.detail-template-table .kode-col-detail {
    width: 12%;
    text-align: center;
    background-color: #fafafa;
}

.detail-template-table tbody tr:hover td:not(.template-header-detail):not(.action-buttons) {
    background-color: #f0f8ff !important;
}

.loading-detail {
    text-align: center;
    padding: 20px;
    color: #666;
}

/* Animasi pulse untuk status yang baru berubah */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); box-shadow: 0 0 10px rgba(92, 184, 92, 0.5); }
    100% { transform: scale(1); }
}

.pulse-animation {
    animation: pulse 2s ease-in-out 3;
}

/* Style untuk badge status */
.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    text-align: center;
    min-width: 90px;
}

.status-badge.belum {
    background: #FF9800;
    color: white;
}

.status-badge.sudah {
    background: #4CAF50;
    color: white;
}

/* Style untuk keranjang PA */
.keranjang-pa-wrapper {
    background: linear-gradient(to right, #fff3e0, #ffe0b2);
    border: 2px solid #ff9800;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.keranjang-pa-wrapper h5 {
    color: #e65100;
    margin-bottom: 15px;
    font-weight: 600;
}

.pa-input-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 10px;
}

.pa-input-row .form-group {
    flex: 1;
    min-width: 150px;
    margin-bottom: 0;
}

.pa-input-row label {
    font-size: 11px;
    font-weight: bold;
    color: #e65100;
    margin-bottom: 3px;
    display: block;
}

.pa-input-row input {
    height: 32px;
    border: 1px solid #ff9800;
    font-size: 12px;
    width: 100%;
}

/* Style untuk keranjang MB */
.keranjang-mb-wrapper {
    background: linear-gradient(to right, #e3f2fd, #bbdefb);
    border: 2px solid #2196f3;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.keranjang-mb-wrapper h5 {
    color: #1565c0;
    margin-bottom: 15px;
    font-weight: 600;
}
</style>

<!-- <div class="alert alert-info" style="background: #00BCD4; color: white; border: none; border-radius: 0; padding: 12px 20px; margin-bottom: 0;">
    <i class="material-icons" style="vertical-align: middle; margin-right: 8px; font-size: 20px;">info</i>
    <strong>Info:</strong> Input Permintaan Lab untuk pasien dengan No. Rawat: <strong><?=$norawat?></strong>
</div> -->
<div style="margin-bottom: 20px;"></div>
<form id="formLaboratorium" method="post" onsubmit="return false;">
    <input type="hidden" name="norawat" value="<?=$norawat?>">
    <input type="hidden" name="norm" value="<?=$norm?>">

    <div class="card" style="margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
        <div class="card-header" style="background: linear-gradient(135deg, #54b2ddff 0%, #14b8d4ff 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
            <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">assignment</i>
                Daftar Pemeriksaan Laboratorium
            </h4>
        </div>
        <div class="body">
            <!-- Filter Kategori Radio Button -->
            <div class="kategori-filter-wrapper" style="margin-bottom: 15px; padding: 12px 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0;">
                <span style="font-weight: 600; color: #333; margin-right: 15px;">Filter Kategori:</span>
                <label class="radio-kategori" style="margin-right: 20px; cursor: pointer;">
                    <input type="radio" name="filterKategori" value="PK" checked style="margin-right: 5px;">
                    <span class="label label-success" style="font-size: 13px; padding: 5px 12px;">PK</span>
                    <span style="color: #666; font-size: 12px;">Patologi Klinis</span>
                </label>
                <label class="radio-kategori" style="margin-right: 20px; cursor: pointer;">
                    <input type="radio" name="filterKategori" value="PA" style="margin-right: 5px;">
                    <span class="label label-warning" style="font-size: 13px; padding: 5px 12px;">PA</span>
                    <span style="color: #666; font-size: 12px;">Patologi Anatomi</span>
                </label>
                <label class="radio-kategori" style="margin-right: 20px; cursor: pointer;">
                    <input type="radio" name="filterKategori" value="MB" style="margin-right: 5px;">
                    <span class="label label-info" style="font-size: 13px; padding: 5px 12px;">MB</span>
                    <span style="color: #666; font-size: 12px;">Mikrobiologi</span>
                </label>
                <!-- <label class="radio-kategori" style="cursor: pointer;">
                    <input type="radio" name="filterKategori" value="ALL" style="margin-right: 5px;">
                    <span class="label label-default" style="font-size: 13px; padding: 5px 12px;">SEMUA</span>
                </label> -->
            </div>

            <!-- Search Box -->
            <div class="search-box-wrapper">
                <input type="text" 
                       id="searchPemeriksaan" 
                       class="form-control" 
                       placeholder="Cari pemeriksaan berdasarkan kode atau nama...">
                <i class="material-icons search-icon">search</i>
                <i class="material-icons clear-search" id="clearSearch">close</i>
                <div class="search-info" id="searchInfo"></div>
            </div>

            <!-- Tabel dengan scroll -->
            <div class="table-scroll-wrapper">
                <table class="table table-bordered table-hover" id="tabelPemeriksaanLab">
                    <thead class="bg-primary">
                        <tr>
                            <th width="5%" class="text-center">
                                <!-- HAPUS checkbox checkAll -->
                            </th>
                            <th width="20%">Kode Pemeriksaan</th>
                            <th width="55%">Nama Template Pemeriksaan</th>
                            <th width="20%">Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Memuat data pemeriksaan...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Container untuk detail template (DI BAWAH TABEL) -->
            <div id="detailTemplateContainer" style="margin-top: 20px;"></div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <button type="button" id="btnTambahPemeriksaan" class="btn btn-primary waves-effect" style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);">
                <i class="material-icons">add</i> Tambah Pemeriksaan Terpilih
            </button>
            <span id="infoSelected" style="margin-left: 10px; color: #666;"></span>
        </div>
    </div>
</form>

<hr>

<!-- ============================================== -->
<!-- FORM INPUT PERMINTAAN - COMMON FIELDS -->
<!-- ============================================== -->
<div style="background: linear-gradient(to right, #f1f8e9, #e8f5e9); border: 1px solid #81c784; border-radius: 5px; padding: 12px; margin-bottom: 15px;">
    <div class="row">
        <!-- Tanggal -->
        <div class="col-sm-2">
            <label style="font-size: 11px; font-weight: bold; color: #2e7d32; margin-bottom: 3px; display: block;">📅 Tanggal *</label>
            <input type="date" id="tanggalPermintaan" class="form-control" style="height: 32px; border: 1px solid #4caf50; font-size: 13px;" required>
        </div>
        
        <!-- Jam -->
        <div class="col-sm-2">
            <label style="font-size: 11px; font-weight: bold; color: #2e7d32; margin-bottom: 3px; display: block;">🕐 Jam</label>
            <input type="text" id="jamPermintaan" class="form-control" style="height: 32px; border: 1px solid #4caf50; font-size: 13px; background: #fff; font-family: monospace;" readonly>
        </div>
        
        <!-- Indikasi -->
        <div class="col-sm-4">
            <label style="font-size: 11px; font-weight: bold; color: #2e7d32; margin-bottom: 3px; display: block;">🏥 Indikasi/Klinis <span style="color: red;">*</span></label>
            <input type="text" id="indikasiKlinis" class="form-control" placeholder="Contoh: Demam tinggi, suspek DBD" style="height: 32px; border: 1px solid #4caf50; font-size: 13px;">
        </div>
        
        <!-- Informasi Tambahan -->
        <div class="col-sm-4">
            <label style="font-size: 11px; font-weight: bold; color: #2e7d32; margin-bottom: 3px; display: block;">ℹ️ Info Tambahan <span style="color: red;">*</span></label>
            <input type="text" id="informasiTambahan" class="form-control" placeholder="Contoh: Pengobatan yang sedang dijalani" style="height: 32px; border: 1px solid #4caf50; font-size: 13px;">
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- KERANJANG PK (Patologi Klinis) -->
<!-- ============================================== -->
<div id="keranjangPKWrapper" style="display: none;">
    <div style="background: linear-gradient(to right, #e8f5e9, #c8e6c9); border: 2px solid #4caf50; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
        <h5 style="color: #2e7d32; margin-bottom: 15px; font-weight: 600;">
            <i class="material-icons" style="vertical-align: middle;">science</i> 
            Keranjang Pemeriksaan PK (Patologi Klinis)
        </h5>
        <table class="table table-bordered table-hover" id="tabelKeranjangPK">
            <thead class="bg-green">
                <tr>
                    <th width="5%">No</th>
                    <th width="30%">Nama Pemeriksaan</th>
                    <th width="10%">Satuan</th>
                    <th width="40%">Nilai Rujukan</th>
                    <th width="15%">Aksi</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <div class="text-right" style="margin-top: 10px;">
            <button type="button" id="btnSimpanPermintaanPK" class="btn btn-success waves-effect">
                <i class="material-icons">save</i> Simpan Permintaan PK
            </button>
            <button type="button" class="btn btn-warning waves-effect btn-kosongkan-keranjang" data-kategori="PK">
                <i class="material-icons">delete_sweep</i> Kosongkan
            </button>
            <span class="info-keranjang-pk" style="margin-left: 15px; color: #666; font-weight: bold;"></span>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- KERANJANG PA (Patologi Anatomi) -->
<!-- ============================================== -->
<div id="keranjangPAWrapper" style="display: none;">
    <div class="keranjang-pa-wrapper">
        <h5>
            <i class="material-icons" style="vertical-align: middle;">biotech</i> 
            Keranjang Pemeriksaan PA (Patologi Anatomi)
        </h5>
        
        <!-- Form Input Khusus PA - Layout seperti gambar referensi -->
        <div style="background: #fff; border: 1px solid #ff9800; border-radius: 5px; padding: 15px; margin-bottom: 15px;">
            <!-- Baris 1: No.Permintaan, Pengambilan Bahan, Diperoleh Dengan -->
            <div style="display: flex; gap: 15px; margin-bottom: 10px; align-items: center;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label style="white-space: nowrap; font-weight: bold; color: #e65100; font-size: 12px;">No.Permintaan :</label>
                    <input type="text" id="paNoPermintaan" class="form-control" style="width: 150px; height: 30px; font-size: 12px; border: 1px solid #999;" readonly>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label style="white-space: nowrap; font-weight: bold; color: #e65100; font-size: 12px;">Pengambilan Bahan :</label>
                    <input type="date" id="paPengambilanBahan" class="form-control" style="width: 140px; height: 30px; font-size: 12px; border: 1px solid #999;">
                </div>
                <div style="display: flex; align-items: center; gap: 5px; flex: 1;">
                    <label style="white-space: nowrap; font-weight: bold; color: #e65100; font-size: 12px;">Diperoleh Dengan :</label>
                    <input type="text" id="paDiperolehDengan" class="form-control" style="flex: 1; height: 30px; font-size: 12px; border: 1px solid #999;" placeholder="">
                </div>
            </div>
            
            <!-- Baris 2: Lokasi Pengambilan Jaringan, Diawetkan/Direndam Dengan -->
            <div style="display: flex; gap: 15px; margin-bottom: 10px; align-items: center;">
                <div style="display: flex; align-items: center; gap: 5px; flex: 1;">
                    <label style="white-space: nowrap; font-weight: bold; color: #e65100; font-size: 12px;">Lokasi Pengambilan Jaringan :</label>
                    <input type="text" id="paLokasiJaringan" class="form-control" style="flex: 1; height: 30px; font-size: 12px; border: 1px solid #999;" placeholder="">
                </div>
                <div style="display: flex; align-items: center; gap: 5px; flex: 1;">
                    <label style="white-space: nowrap; font-weight: bold; color: #e65100; font-size: 12px;">Diawetkan/Direndam Dengan :</label>
                    <input type="text" id="paDiawetkanDengan" class="form-control" style="flex: 1; height: 30px; font-size: 12px; border: 1px solid #999;" placeholder="">
                </div>
            </div>
            
            <!-- Baris 3: Pernah Dilakukan PA Di, Pada Tanggal -->
            <div style="display: flex; gap: 15px; margin-bottom: 10px; align-items: center;">
                <div style="display: flex; align-items: center; gap: 5px; flex: 2;">
                    <label style="white-space: nowrap; font-weight: bold; color: #e65100; font-size: 12px;">Pernah Dilakukan PA Di :</label>
                    <input type="text" id="paPernahDilakukanDi" class="form-control" style="flex: 1; height: 30px; font-size: 12px; border: 1px solid #999;" placeholder="">
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label style="white-space: nowrap; font-weight: bold; color: #e65100; font-size: 12px;">Pada Tanggal :</label>
                    <input type="date" id="paTanggalSebelumnya" class="form-control" style="width: 140px; height: 30px; font-size: 12px; border: 1px solid #999;">
                </div>
            </div>
            
            <!-- Baris 4: Dengan Nomor PA, Dengan Diagnosa PA -->
            <div style="display: flex; gap: 15px; align-items: center;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label style="white-space: nowrap; font-weight: bold; color: #e65100; font-size: 12px;">Dengan Nomor PA :</label>
                    <input type="text" id="paNomorSebelumnya" class="form-control" style="width: 150px; height: 30px; font-size: 12px; border: 1px solid #999;" placeholder="">
                </div>
                <div style="display: flex; align-items: center; gap: 5px; flex: 1;">
                    <label style="white-space: nowrap; font-weight: bold; color: #e65100; font-size: 12px;">Dengan Diagnosa PA :</label>
                    <input type="text" id="paDiagnosaSebelumnya" class="form-control" style="flex: 1; height: 30px; font-size: 12px; border: 1px solid #999;" placeholder="">
                </div>
            </div>
        </div>
        
        <table class="table table-bordered table-hover" id="tabelKeranjangPA">
            <thead style="background: #ff9800; color: white;">
                <tr>
                    <th width="5%">No</th>
                    <th width="30%">Nama Pemeriksaan</th>
                    <th width="10%">Satuan</th>
                    <th width="40%">Nilai Rujukan</th>
                    <th width="15%">Aksi</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <div class="text-right" style="margin-top: 10px;">
            <button type="button" id="btnSimpanPermintaanPA" class="btn btn-warning waves-effect" style="background: #ff9800; border-color: #ff9800;">
                <i class="material-icons">save</i> Simpan Permintaan PA
            </button>
            <button type="button" class="btn btn-default waves-effect btn-kosongkan-keranjang" data-kategori="PA">
                <i class="material-icons">delete_sweep</i> Kosongkan
            </button>
            <span class="info-keranjang-pa" style="margin-left: 15px; color: #666; font-weight: bold;"></span>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- KERANJANG MB (Mikrobiologi) -->
<!-- ============================================== -->
<div id="keranjangMBWrapper" style="display: none;">
    <div class="keranjang-mb-wrapper">
        <h5>
            <i class="material-icons" style="vertical-align: middle;">coronavirus</i> 
            Keranjang Pemeriksaan MB (Mikrobiologi)
        </h5>
        <table class="table table-bordered table-hover" id="tabelKeranjangMB">
            <thead style="background: #2196f3; color: white;">
                <tr>
                    <th width="5%">No</th>
                    <th width="30%">Nama Pemeriksaan</th>
                    <th width="10%">Satuan</th>
                    <th width="40%">Nilai Rujukan</th>
                    <th width="15%">Aksi</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <div class="text-right" style="margin-top: 10px;">
            <button type="button" id="btnSimpanPermintaanMB" class="btn btn-info waves-effect">
                <i class="material-icons">save</i> Simpan Permintaan MB
            </button>
            <button type="button" class="btn btn-default waves-effect btn-kosongkan-keranjang" data-kategori="MB">
                <i class="material-icons">delete_sweep</i> Kosongkan
            </button>
            <span class="info-keranjang-mb" style="margin-left: 15px; color: #666; font-weight: bold;"></span>
        </div>
    </div>
</div>

<hr>

<!-- TABEL DAFTAR PERMINTAAN LABORATORIUM (YANG SUDAH TERSIMPAN) -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, #6fe438ff 0%, #12b828ff 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
                <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
                    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">assignment</i>
                    Daftar Permintaan Laboratorium (Tersimpan)
                </h4>
            </div>
            <div class="body">
                <!-- Sub-tabs untuk kategori -->
                <ul class="nav nav-tabs" role="tablist" style="margin-bottom: 15px;">
                    <li role="presentation" class="active">
                        <a href="#tab_riwayat_pk" data-toggle="tab" style="color: #4caf50; font-weight: 600;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 16px;">science</i> PK
                            <span id="badgeCountPK" class="badge" style="background: #ff5722; color: white; margin-left: 5px; display: none;">0</span>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_riwayat_pa" data-toggle="tab" style="color: #ff9800; font-weight: 600;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 16px;">biotech</i> PA
                            <span id="badgeCountPA" class="badge" style="background: #ff5722; color: white; margin-left: 5px; display: none;">0</span>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_riwayat_mb" data-toggle="tab" style="color: #2196f3; font-weight: 600;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 16px;">science</i> MB
                            <span id="badgeCountMB" class="badge" style="background: #ff5722; color: white; margin-left: 5px; display: none;">0</span>
                        </a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- Tab PK -->
                    <div role="tabpanel" class="tab-pane fade in active" id="tab_riwayat_pk">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="tabelPermintaanPK">
                                <thead class="bg-green">
                                    <tr>
                                        <th width="8%">No Order</th>
                                        <th width="22%">Nama Pemeriksaan</th>
                                        <th width="8%">Satuan</th>
                                        <th width="20%">Nilai Rujukan</th>
                                        <th width="12%">Diagnosa Klinis</th>
                                        <th width="12%">Info Tambahan</th>
                                        <th width="12%">Status</th>
                                        <th width="5%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="8" align="center">
                                            <em class="text-muted">Belum ada permintaan PK yang tersimpan</em>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="paginationPK"></div>
                    </div>
                    
                    <!-- Tab PA -->
                    <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_pa">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="tabelPermintaanPA">
                                <thead style="background: #ff9800; color: white;">
                                    <tr>
                                        <th width="8%">No Order</th>
                                        <th width="18%">Nama Pemeriksaan</th>
                                        <th width="10%">Lokasi Jaringan</th>
                                        <th width="12%">Pengambilan Bahan</th>
                                        <th width="12%">Diagnosa Klinis</th>
                                        <th width="15%">Info PA Sebelumnya</th>
                                        <th width="8%">Status</th>
                                        <th width="5%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="8" align="center">
                                            <em class="text-muted">Belum ada permintaan PA yang tersimpan</em>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Tab MB -->
                    <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_mb">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="tabelPermintaanMB">
                                <thead style="background: #2196f3; color: white;">
                                    <tr>
                                        <th width="8%">No Order</th>
                                        <th width="22%">Nama Pemeriksaan</th>
                                        <th width="8%">Satuan</th>
                                        <th width="20%">Nilai Rujukan</th>
                                        <th width="12%">Diagnosa Klinis</th>
                                        <th width="12%">Info Tambahan</th>
                                        <th width="12%">Status</th>
                                        <th width="5%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="8" align="center">
                                            <em class="text-muted">Belum ada permintaan MB yang tersimpan</em>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="paginationMB"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Define APP_BASE_URL untuk JavaScript -->
<script>
    var APP_BASE_URL = '<?php echo APP_BASE_URL; ?>';
</script>

<!-- Load script eksternal -->
<script src="js/lab.js?v=<?php echo time(); ?>"></script>

<!-- Script untuk Tanggal dan Jam -->
<script>
(function() {
    // Fungsi untuk format tanggal ke YYYY-MM-DD
    function formatDate(date) {
        var year = date.getFullYear();
        var month = (date.getMonth() + 1).toString().padStart(2, '0');
        var day = date.getDate().toString().padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    // Fungsi untuk format jam ke HH:MM:SS
    function formatTime(date) {
        var hours = date.getHours().toString().padStart(2, '0');
        var minutes = date.getMinutes().toString().padStart(2, '0');
        var seconds = date.getSeconds().toString().padStart(2, '0');
        return hours + ':' + minutes + ':' + seconds;
    }
    
    // Set default tanggal = hari ini
    var today = new Date();
    document.getElementById('tanggalPermintaan').value = formatDate(today);
    
    // Set default tanggal PA
    if(document.getElementById('paPengambilanBahan')) {
        document.getElementById('paPengambilanBahan').value = formatDate(today);
    }
    
    // Generate No Permintaan PA otomatis
    function generateNoPAPermintaan() {
        var now = new Date();
        var noPA = 'PA' + now.getFullYear() + 
                   (now.getMonth() + 1).toString().padStart(2, '0') + 
                   now.getDate().toString().padStart(2, '0') + 
                   now.getHours().toString().padStart(2, '0') + 
                   now.getMinutes().toString().padStart(2, '0') + 
                   now.getSeconds().toString().padStart(2, '0') + 
                   Math.floor(Math.random() * 100).toString().padStart(2, '0');
        if(document.getElementById('paNoPermintaan')) {
            document.getElementById('paNoPermintaan').value = noPA;
        }
    }
    generateNoPAPermintaan();
    
    // Update jam setiap detik
    function updateClock() {
        var now = new Date();
        document.getElementById('jamPermintaan').value = formatTime(now);
    }
    
    // Update pertama kali
    updateClock();
    
    // Update setiap 1 detik
    setInterval(updateClock, 1000);
    
})();
</script>
