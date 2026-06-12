<?php
    // Dekripsi dan validasi parameter
    $norawat = '';
    $norm = '';
    
    if(isset($_GET['rnw']) && isset($_GET['rm'])){
        $norawat = encrypt_decrypt(urldecode($_GET['rnw']), "d");
        $norm = encrypt_decrypt(urldecode($_GET['rm']), "d");
        
        // Validasi tambahan
        $norawat = validTeks4($norawat, 20);
        $norm = validTeks4($norm, 20);
    } else {
        // Redirect jika parameter tidak valid
        JSRedirect("index.php?act=Pasien");
        exit();
    }
    
    // Validasi kepemilikan: Cek apakah pasien ini benar-benar pasien dokter yang login
    $kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"],"d"), 20);

    // Cek apakah dokter umum atau spesialis
    $queryDokter = bukaquery("SELECT kd_sps FROM dokter WHERE kd_dokter = '$kd_dokter'");
    $rsDokter = mysqli_fetch_array($queryDokter);

    if($rsDokter) {
        $kd_sps = $rsDokter['kd_sps'];
        $is_dokter_umum = ($kd_sps == KD_DOKTER_UMUM || $kd_sps == KD_DOKTER_ANESTESI);
    } else {
        $is_dokter_umum = false;
    }

    // Jika bukan dokter umum, lakukan validasi kepemilikan
    if(!$is_dokter_umum) {
        // Untuk rawat inap, cek di tabel dpjp_ranap
        $cek_akses = getOne("SELECT COUNT(*) FROM dpjp_ranap 
                            WHERE no_rawat='$norawat' 
                            AND kd_dokter='$kd_dokter'");
        
        if($cek_akses == 0){
            // Pasien bukan milik dokter ini, redirect
            echo "<script>alert('Anda tidak memiliki akses ke data pasien ini!');</script>";
            JSRedirect("index.php?act=Pasien");
            exit();
        }
    }
    
    // Ambil data pasien dengan info kamar untuk rawat inap
    // Kondisi kd_dokter hanya ditambahkan jika bukan dokter umum
    $where_dokter = "";
    if(!$is_dokter_umum) {
        // Join dengan dpjp_ranap untuk filter dokter spesialis
        $where_dokter = "AND EXISTS (SELECT 1 FROM dpjp_ranap 
                                     WHERE dpjp_ranap.no_rawat = reg_periksa.no_rawat 
                                     AND dpjp_ranap.kd_dokter = '$kd_dokter')";
    }
    
    $querypasien = bukaquery("SELECT pasien.no_rkm_medis, pasien.nm_pasien, pasien.jk, pasien.tmp_lahir, 
                                     pasien.tgl_lahir, pasien.alamat, reg_periksa.no_rawat, reg_periksa.tgl_registrasi,
                                     reg_periksa.jam_reg, kamar_inap.kd_kamar, kamar.kelas, bangsal.nm_bangsal,
                                     kamar_inap.diagnosa_awal, kamar_inap.diagnosa_akhir,
                                     kamar_inap.tgl_masuk, kamar_inap.jam_masuk,
                                     kamar_inap.tgl_keluar, kamar_inap.jam_keluar
                              FROM reg_periksa 
                              INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
                              INNER JOIN kamar_inap ON reg_periksa.no_rawat = kamar_inap.no_rawat
                              INNER JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
                              INNER JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
                              WHERE reg_periksa.no_rawat = '$norawat' 
                              AND reg_periksa.no_rkm_medis = '$norm'
                              $where_dokter");
    
    $datapasien = mysqli_fetch_array($querypasien);
    
    // Double check jika data tidak ditemukan
    if(!$datapasien){
        echo "<script>alert('Data pasien tidak ditemukan!');</script>";
        JSRedirect("index.php?act=Pasien");
        exit();
    }
?>

<style>
    .nav-tabs {
        border-bottom: 2px solid #ddd;
        margin-bottom: 20px;
    }
    .nav-tabs > li.active > a, 
    .nav-tabs > li.active > a:focus, 
    .nav-tabs > li.active > a:hover {
        color: #e91e63;
        background-color: #fff;
        border: 1px solid #ddd;
        border-bottom-color: transparent;
        font-weight: 600;
    }
    .nav-tabs > li > a {
        color: #555;
        font-weight: 500;
        padding: 10px 20px;
    }
    .nav-tabs > li > a:hover {
        background-color: #f5f5f5;
        border-color: #ddd;
    }
    
    /* Sub-tabs styling (untuk E-Resep dan SOAPIE) */
    .sub-tabs, .nav-tabs-secondary {
        border-bottom: none;
        margin-bottom: 0;
    }
    .sub-tabs > li > a,
    .nav-tabs-secondary > li > a {
        color: #555;
        font-weight: 500;
        padding: 10px 15px;
        border-radius: 0;
        border: none;
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
    }
    .sub-tabs > li > a:hover,
    .nav-tabs-secondary > li > a:hover {
        color: #00bcd4;
        background-color: #f5f5f5;
        border-bottom: 3px solid #e0e0e0;
    }
    .sub-tabs > li.active > a,
    .sub-tabs > li.active > a:focus,
    .sub-tabs > li.active > a:hover,
    .nav-tabs-secondary > li.active > a,
    .nav-tabs-secondary > li.active > a:focus,
    .nav-tabs-secondary > li.active > a:hover {
        color: #00bcd4;
        background-color: #fff;
        border-bottom: 3px solid #00bcd4 !important;
        border-top: none;
        border-left: none;
        border-right: none;
        font-weight: 600;
    }
    
    .form-section {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    .form-section-title {
        font-weight: 600;
        color: #e91e63;
        margin-bottom: 10px;
        font-size: 14px;
    }

/* Compact TTV Grid - Premium Color-Coded */
.ttv-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
    margin-bottom: 15px;
}

.ttv-item {
    display: flex;
    flex-direction: column;
    min-width: 0;
    position: relative;
}

.ttv-item label {
    font-size: 9px;
    font-weight: 700;
    color: #555;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ttv-item input,
.ttv-item select {
    height: 36px;
    padding: 6px 10px;
    border: 1px solid #e0e0e0;
    border-left: 3px solid #ccc;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    background: #fafafa;
    width: 100%;
}

.ttv-item input:focus,
.ttv-item select:focus {
    background: white;
    border-color: currentColor;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    outline: none;
    transform: translateY(-1px);
}

.ttv-item input::placeholder {
    color: #bbb;
    font-size: 12px;
}

/* Color Coding untuk setiap vital sign */
.ttv-item:nth-child(1) input { border-left-color: #e53935; } /* TD - Merah */
.ttv-item:nth-child(1) label { color: #e53935; }
.ttv-item:nth-child(1) input:focus { border-left-color: #e53935; box-shadow: 0 2px 8px rgba(229,57,53,0.2); }

.ttv-item:nth-child(2) input { border-left-color: #ec407a; } /* Nadi - Pink */
.ttv-item:nth-child(2) label { color: #ec407a; }
.ttv-item:nth-child(2) input:focus { border-left-color: #ec407a; box-shadow: 0 2px 8px rgba(236,64,122,0.2); }

.ttv-item:nth-child(3) input { border-left-color: #42a5f5; } /* RR - Biru */
.ttv-item:nth-child(3) label { color: #42a5f5; }
.ttv-item:nth-child(3) input:focus { border-left-color: #42a5f5; box-shadow: 0 2px 8px rgba(66,165,245,0.2); }

.ttv-item:nth-child(4) input { border-left-color: #ff9800; } /* Suhu - Orange */
.ttv-item:nth-child(4) label { color: #ff9800; }
.ttv-item:nth-child(4) input:focus { border-left-color: #ff9800; box-shadow: 0 2px 8px rgba(255,152,0,0.2); }

.ttv-item:nth-child(5) input { border-left-color: #26c6da; } /* SpO2 - Cyan */
.ttv-item:nth-child(5) label { color: #26c6da; }
.ttv-item:nth-child(5) input:focus { border-left-color: #26c6da; box-shadow: 0 2px 8px rgba(38,198,218,0.2); }

.ttv-item:nth-child(6) input { border-left-color: #66bb6a; } /* BB - Hijau */
.ttv-item:nth-child(6) label { color: #66bb6a; }
.ttv-item:nth-child(6) input:focus { border-left-color: #66bb6a; box-shadow: 0 2px 8px rgba(102,187,106,0.2); }

.ttv-item:nth-child(7) input { border-left-color: #9ccc65; } /* TB - Hijau Muda */
.ttv-item:nth-child(7) label { color: #9ccc65; }
.ttv-item:nth-child(7) input:focus { border-left-color: #9ccc65; box-shadow: 0 2px 8px rgba(156,204,101,0.2); }

/* Grid kedua - Data Tambahan */
.ttv-grid.secondary {
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-top: 15px;
}

.ttv-grid.secondary .ttv-item:nth-child(1) input,
.ttv-grid.secondary .ttv-item:nth-child(1) select { 
    border-left-color: #ab47bc; /* Kesadaran - Ungu */
}
.ttv-grid.secondary .ttv-item:nth-child(1) label { color: #ab47bc; }

.ttv-grid.secondary .ttv-item:nth-child(2) input { 
    border-left-color: #5c6bc0; /* GCS - Indigo */
}
.ttv-grid.secondary .ttv-item:nth-child(2) label { color: #5c6bc0; }

.ttv-grid.secondary .ttv-item:nth-child(3) input { 
    border-left-color: #ff7043; /* Alergi - Deep Orange */
}
.ttv-grid.secondary .ttv-item:nth-child(3) label { color: #ff7043; }

.ttv-grid.secondary .ttv-item:nth-child(4) input { 
    border-left-color: #8d6e63; /* Lingkar Perut - Brown */
}
.ttv-grid.secondary .ttv-item:nth-child(4) label { color: #8d6e63; }

/* Section Title */
.section-title {
    font-size: 15px;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 6px;
}

.section-title i {
    font-size: 20px;
    color: #e91e63;
}

/* Form Control Modern */
.form-control-modern {
    width: 100%;
    height: 36px;
    padding: 6px 10px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-size: 13px;
    background: #fafafa;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    background: white;
    border-color: #ab47bc;
    box-shadow: 0 2px 8px rgba(171,71,188,0.2);
    outline: none;
    transform: translateY(-1px);
}

/* Hover effect untuk semua input */
.ttv-item input:hover,
.ttv-item select:hover {
    background: white;
    border-color: #ccc;
}

/* Responsive */
@media (max-width: 1200px) {
    .ttv-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
    }
}

@media (max-width: 768px) {
    .ttv-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .ttv-item label {
        font-size: 10px;
    }
    
    .ttv-item input,
    .ttv-item select {
        height: 38px;
        font-size: 14px;
    }
}

/* Button Load TTV */
.btn-load-ttv {
    display: flex;
    align-items: center;
    gap: 5px;
    height: 32px;
    padding: 0 15px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
}

.btn-load-ttv:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-load-ttv:active {
    transform: translateY(0);
}

.btn-load-ttv i {
    font-size: 16px;
}

.btn-load-ttv.loading {
    opacity: 0.7;
    pointer-events: none;
}

.btn-load-ttv.loading i {
    animation: rotate 1s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.collapsible-header {
    cursor: pointer;
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    transition: all 0.3s ease;
}

.collapsible-header:hover {
    opacity: 0.9;
}

.toggle-icon {
    transition: transform 0.3s ease;
    font-size: 24px;
}

.collapsible-header.collapsed .toggle-icon {
    transform: rotate(180deg);
}

.collapsible-content {
    overflow: hidden;
    transition: all 0.3s ease;
}

/* ========================================
   MENU RME DROPDOWN (pemeriksaaninap.php)
   ======================================== */
.btn-rme-toggle {
    height: 34px;
    width: 34px;
    padding: 0;
    border-radius: 999px;
    border: 2px solid #667eea;
    background: white;
    color: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.btn-rme-toggle:hover,
.btn-rme-toggle.active {
    background: #667eea;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-rme-toggle i {
    font-size: 18px;
}

.dropdown-rme-wrapper {
    position: relative !important;
    flex-shrink: 0;
}

.dropdown-rme-menu {
    position: fixed !important;
    z-index: 999999 !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
    border-radius: 8px !important;
    border: 1px solid #ddd !important;
    min-width: 200px !important;
    background: white !important;
    display: none;
    padding: 5px 0 !important;
    list-style: none !important;
}

.dropdown-rme-menu.show {
    display: block;
}

.dropdown-rme-menu > li {
    list-style: none;
    padding: 0;
    margin: 0;
}

.dropdown-rme-menu > li > a {
    padding: 10px 15px;
    font-size: 13px;
    color: #333;
    display: block;
    text-decoration: none;
    transition: background-color 0.15s ease;
}

.dropdown-rme-menu > li > a:hover {
    background-color: #f5f5f5;
    color: #667eea;
}

.dropdown-rme-menu .has-submenu {
    position: relative;
}

.dropdown-rme-menu .has-submenu > a {
    padding-right: 30px;
    cursor: pointer;
}

.dropdown-rme-menu .has-submenu > a:after {
    content: '\203A';
    position: absolute;
    right: 15px;
    font-size: 18px;
    font-weight: bold;
    transition: transform 0.2s ease;
}

.dropdown-rme-menu .has-submenu.active > a:after {
    transform: rotate(90deg);
}

.dropdown-rme-menu .dropdown-submenu {
    position: absolute;
    top: 0;
    left: 100%;
    z-index: 100000;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    border-radius: 8px;
    border: 1px solid #ddd;
    margin-left: 5px;
    min-width: 220px;
    background: white;
    padding: 5px 0;
    list-style: none;
    display: none;
}

.dropdown-rme-menu .has-submenu.active > .dropdown-submenu {
    display: block;
}

.dropdown-rme-menu .dropdown-submenu > li {
    list-style: none;
    padding: 0;
    margin: 0;
}

.dropdown-rme-menu .dropdown-submenu > li > a {
    padding: 10px 15px;
    font-size: 13px;
    color: #333;
    display: block;
    text-decoration: none;
    transition: background-color 0.15s ease;
}

.dropdown-rme-menu .dropdown-submenu > li > a:hover {
    background-color: #f5f5f5;
    color: #667eea;
}

.dropdown-rme-menu .divider {
    height: 1px;
    margin: 5px 0;
    background: #eee;
}

/* ========================================
   RME TAB BAR (Pill / Capsule Tabs) - RANAP
   ======================================== */
/* Wrapper area tab - flex wrap ke bawah */
.rme-tab-scroll-area {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
    min-width: 0;
    padding: 2px 0;
}

.rme-tab-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    background: #f1f5f9;
    border-radius: 12px 12px 0 0;
    border: 1px solid #e2e8f0;
    border-bottom: none;
    overflow: visible;
}

.rme-tab {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 7px 16px;
    background: #fff;
    color: #64748b;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    cursor: pointer;
    white-space: nowrap;
    font-size: 12.5px;
    font-weight: 500;
    transition: all 0.25s;
    min-width: unset;
    max-width: 220px;
    position: relative;
    user-select: none;
}

.rme-tab:hover {
    border-color: #c7d2fe;
    color: #4338ca;
    background: #fefeff;
}

.rme-tab.active {
    background: #4338ca;
    color: #fff;
    border-color: #4338ca;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(67,56,202,0.3);
}

.rme-tab-title {
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

.rme-tab-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    font-size: 13px;
    line-height: 1;
    color: #94a3b8;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.rme-tab-close:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.rme-tab.active .rme-tab-close {
    color: rgba(255,255,255,0.7);
}

.rme-tab.active .rme-tab-close:hover {
    background: rgba(255,255,255,0.2);
    color: #fff;
}

/* Tab content */
.rme-tab-content {
    display: none;
}

.rme-tab-content.active {
    display: block;
}

.rme-tab-content-ajax {
    display: none;
}

.rme-tab-content-ajax.active {
    display: block;
}

/* Loading state inside tab */
.rme-tab-loading {
    text-align: center;
    padding: 60px 20px;
}

.rme-tab-loading i {
    font-size: 48px;
    color: #999;
    animation: spin 1s linear infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .rme-tab-bar {
        padding: 8px 10px;
    }
    .rme-tab {
        padding: 6px 12px;
        font-size: 12px;
    }
    .dropdown-rme-menu .dropdown-submenu {
        position: relative;
        left: 0;
        margin-left: 15px;
        margin-top: 5px;
        box-shadow: none;
        border-left: 2px solid #667eea;
    }
}
</style>


<!-- 1. DATA PASIEN -->
<div class="row clearfix">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header bg-cyan">
                <h2>DATA PASIEN</h2>
            </div>
            <div class="body" style="padding: 15px;">
                <!-- Baris 1: Data Identitas -->
                <div class="row">
                    <div class="col-md-4">
                        <table class="table table-condensed" style="margin-bottom: 0;">
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>No. Rawat</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['no_rawat']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>No. RM</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['no_rkm_medis']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Nama</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['nm_pasien']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>JK / Lahir</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['jk']?> / <?=$datapasien['tmp_lahir']?>, <?=konversiTanggal($datapasien['tgl_lahir'])?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <table class="table table-condensed" style="margin-bottom: 0;">
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>Kamar</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['nm_bangsal']?> - <?=$datapasien['kd_kamar']?> (Kelas <?=$datapasien['kelas']?>)</td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Masuk</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=konversiTanggal($datapasien['tgl_masuk'])?> <?=$datapasien['jam_masuk']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Keluar</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=($datapasien['tgl_keluar'] && $datapasien['tgl_keluar'] != '0000-00-00') ? konversiTanggal($datapasien['tgl_keluar']).' '.$datapasien['jam_keluar'] : '<span class="label label-success">Masih Dirawat</span>'?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Alamat</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['alamat']?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <table class="table table-condensed" style="margin-bottom: 0;">
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>Dx Awal</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['diagnosa_awal'] ?: '-'?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Dx Akhir</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['diagnosa_akhir'] ?: '-'?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <!-- Tombol Refresh + Menu RME -->
                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-12" style="display: flex; align-items: center; gap: 10px;">
                        <button type="button" class="btn btn-info btn-sm waves-effect" onclick="window.location.reload()"
                                style="border-radius: 5px; padding: 5px 15px;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 16px;">refresh</i>
                            Refresh
                        </button>
                        <button type="button" class="btn waves-effect btn-action" onclick="window.history.back();" style="background: linear-gradient(135deg, #78909c 0%, #546e7a 100%); color: white;">
                            <i class="material-icons">arrow_back</i>
                            Kembali
                        </button>                        
                        <!-- TOMBOL MENU RME INAP -->
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- =============================================
     RME TAB BAR (Pill / Capsule Tabs) - RANAP
     Langsung tampil tanpa "Mulai Periksa"
     ============================================= -->
<div class="row clearfix" id="panelRmeTabBar">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="rme-tab-bar" id="rmeTabBar">

            <!-- TOMBOL MENU RME - posisi kiri -->
            <div class="dropdown-rme-wrapper" id="btnMenuRME">
                <button type="button" class="btn-rme-toggle" id="btnRmeToggle" title="Menu RME">
                    <i class="material-icons">dashboard</i>
                </button>
                <ul class="dropdown-rme-menu" id="rmeDropdownMenu">
                    <?php 
                    $encrypted_norawat = urlencode(encrypt_decrypt($norawat, 'e'));
                    $encrypted_norm = urlencode(encrypt_decrypt($norm, 'e'));
                    ?>

                    <?php if(cekAksesMenu('penilaian_awal_medis_ranap') || cekAksesMenu('penilaian_awal_medis_ranap_neonatus') || cekAksesMenu('penilaian_awal_medis_ranap_kebidanan') || cekAksesMenu('penilaian_bayi_baru_lahir')): ?>
                    <li class="has-submenu">
                        <a href="#" class="submenu-trigger">Penilaian Awal Medis</a>
                        <ul class="dropdown-submenu">
                            <?php if(cekAksesMenu('penilaian_awal_medis_ranap')): ?>
                            <li><a href="index.php?act=Awalmedisranap&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Awal Medis Ranap</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_awal_medis_ranap_neonatus')): ?>
                            <li><a href="index.php?act=Awalmedisneonatus&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Awal Medis Neonatus</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_awal_medis_ranap_kebidanan')): ?>
                            <li><a href="index.php?act=Awalmediskebidanan&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Awal Medis Kebidanan & Kandungan</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_bayi_baru_lahir')): ?>
                            <li><a href="index.php?act=Penilaianbayibarulahir&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pengkajian Bayi Baru Lahir</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('penilaian_pre_induksi') || cekAksesMenu('penilaian_pre_operasi') || cekAksesMenu('penilaian_pre_anestesi')): ?>
                    <li class="has-submenu">
                        <a href="#" class="submenu-trigger">Operasi</a>
                        <ul class="dropdown-submenu">
                            <?php if(cekAksesMenu('penilaian_pre_induksi')): ?>
                            <li><a href="index.php?act=Penilaianpreinduksi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Penilaian Pre Induksi</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_pre_operasi')): ?>
                            <li><a href="index.php?act=Penilaianpreoperasi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Penilaian Pre Operasi</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_pre_anestesi')): ?>
                            <li><a href="index.php?act=Penilaianpreanestesi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Penilaian Pre Anestesi</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('hasil_pemeriksaan_usg') || cekAksesMenu('hasil_usg_gynecologi') || cekAksesMenu('hasil_usg_urologi') || cekAksesMenu('hasil_usg_neonatus')): ?>
                    <li class="has-submenu">
                        <a href="#" class="submenu-trigger">Pemeriksaan USG</a>
                        <ul class="dropdown-submenu">
                            <?php if(cekAksesMenu('hasil_pemeriksaan_usg')): ?>
                            <li><a href="index.php?act=Pemeriksaanusgkandungan&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">USG Kandungan</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_usg_gynecologi')): ?>
                            <li><a href="index.php?act=Pemeriksaanusggynecologi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">USG Gynecologi</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_usg_urologi')): ?>
                            <li><a href="index.php?act=Pemeriksaanusgurologi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">USG Urologi</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_usg_neonatus')): ?>
                            <li><a href="index.php?act=Pemeriksaanusgneonatus&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">USG Neonatus</a></li>
                            <?php endif; ?>                            
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('hasil_pemeriksaan_ekg')): ?>
                    <li><a href="index.php?act=Pemeriksaanekg&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pemeriksaan EKG</a></li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('checklist_kriteria_keluar_hcu') || cekAksesMenu('checklist_kriteria_keluar_icu') || cekAksesMenu('kriteria_keluar_nicu') || cekAksesMenu('kriteria_keluar_picu')): ?>
                    <li class="has-submenu">
                        <a href="#" class="submenu-trigger">Perawatan Intensif</a>
                        <ul class="dropdown-submenu">
                            <?php if(cekAksesMenu('checklist_kriteria_keluar_hcu')): ?>
                            <li><a href="index.php?act=Checklistkriteriakeluarhcu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Keluar HCU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('checklist_kriteria_keluar_icu')): ?>
                            <li><a href="index.php?act=Checklistkriteriakeluaricu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Keluar ICU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('kriteria_keluar_nicu')): ?>
                            <li><a href="index.php?act=Kriteriakeluarnicu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Keluar NICU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('kriteria_keluar_picu')): ?>
                            <li><a href="index.php?act=Kriteriakeluarpicu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Keluar PICU</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('konsultasi_medik')): ?>
                    <li><a href="index.php?act=Konsultasimedik&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Konsultasi Medik</a></li>
                    <?php endif; ?>

                    <li><a href="index.php?act=ClinicalPathway&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Integrated Care Pathway</a></li>
                    <li><a href="index.php?act=Obatpulang&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Resep Pulang</a></li>
                    <li class="divider"></li>
                    <li><a href="index.php?act=ResumeMedisInap&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Resume Medis</a></li>
                </ul>
            </div>

            <!-- Tab Area (flex-wrap ke bawah jika penuh) -->
            <div class="rme-tab-scroll-area" id="rmeTabScrollArea">
                <!-- Tab Pemeriksaan -->
                <div class="rme-tab active" data-tab-id="pemeriksaan" data-closable="false">
                    <span class="rme-tab-title">Pemeriksaan</span>
                </div>
                <!-- Tab dinamis akan ditambahkan di sini oleh JS -->
            </div>

        </div>
    </div>
</div>

<!-- =============================================
     CONTAINER KONTEN TAB RME - RANAP
     ============================================= -->
<div id="rmeTabContentWrapper">

<!-- Tab Content: Pemeriksaan & SOAPIE (default, inline) -->
<div class="rme-tab-content active" id="rmeContent_pemeriksaan">
<div class="row clearfix">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header bg-pink collapsible-header active" onclick="toggleCollapse(this)">
                <h2>PEMERIKSAAN & SOAPIE</h2>
                <i class="material-icons toggle-icon">expand_less</i>
            </div>
            <div class="body">
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active">
                        <a href="#tab_pemeriksaan" data-toggle="tab">PEMERIKSAAN</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_eresep" data-toggle="tab">E-RESEP</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_diagnosa" data-toggle="tab">DIAGNOSA</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_laboratorium" data-toggle="tab">LABORATORIUM</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_radiologi" data-toggle="tab">RADIOLOGI</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_tindakan" data-toggle="tab">TINDAKAN</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane fade in active" id="tab_pemeriksaan">
                        <form id="formPemeriksaan" method="post" novalidate>
                            <input type="hidden" name="norawat" value="<?=$norawat?>">
                            <input type="hidden" name="norm" value="<?=$norm?>">
                            
                            <!-- PEMERIKSAAN FISIK - VERSION 2.0 CLEAN + ICONS -->
                            <!-- PEMERIKSAAN FISIK - COMPACT VERSION -->
                            <div class="form-section">
                                <div class="section-title" style="margin-top: 12px;">
                                    <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">favorite_border</i>
                                    Pemeriksaan Fisik
                                </div>                                
                                <!-- Tanda-Tanda Vital -->
                                <div class="ttv-grid">
                                    <div class="ttv-item">
                                        <label>TD (mmHg)</label>
                                        <input type="text" name="tensi" placeholder="mmHg" pattern="[0-9]{2,3}/[0-9]{2,3}">
                                    </div>
                                    <div class="ttv-item">
                                        <label>Nadi (x/menit)</label>
                                        <input type="number" name="nadi" placeholder="x/menit" min="40" max="200">
                                    </div>
                                    <div class="ttv-item">
                                        <label>RR (x/menit)</label>
                                        <input type="number" name="respiratory_rate" placeholder="x/menit" min="10" max="60">
                                    </div>
                                    <div class="ttv-item">
                                        <label>Suhu (°C)</label>
                                        <input type="number" name="suhu" placeholder="°C" step="0.1" min="35" max="42">
                                    </div>
                                    <div class="ttv-item">
                                        <label>SpO2 (%)</label>
                                        <input type="number" name="spo2" placeholder="%" min="50" max="100">
                                    </div>
                                    <div class="ttv-item">
                                        <label>BB (kg)</label>
                                        <input type="number" name="berat" placeholder="kg" min="2" max="300">
                                    </div>
                                    <div class="ttv-item">
                                        <label>TB (cm)</label>
                                        <input type="number" name="tinggi" placeholder="cm" min="50" max="250">
                                    </div>
                                </div>
                                <!-- Data Tambahan -->
                                <div class="ttv-grid secondary" style="margin-top: 15px;">
                                    <div class="ttv-item">
                                        <label>Kesadaran</label>
                                        <select name="kesadaran" class="form-control-modern">
                                            <option value="Compos Mentis">Compos Mentis</option>
                                            <option value="Apatis">Apatis</option>
                                            <option value="Somnolen">Somnolen</option>
                                            <option value="Sopor">Sopor</option>
                                            <option value="Koma">Koma</option>
                                        </select>
                                    </div>
                                    <div class="ttv-item">
                                        <label>GCS (E,V,M)</label>
                                        <input type="text" name="gcs" placeholder="" pattern="[0-9]{1},?[0-9]{1},?[0-9]{1}">
                                    </div>
                                    <div class="ttv-item">
                                        <label>Alergi</label>
                                        <input type="text" name="alergi" placeholder="Tidak ada">
                                    </div>
                                </div>
                                <button type="button" 
                                        id="btnLoadTTV" 
                                        class="btn-load-ttv"
                                        onclick="loadLastTTV()">
                                    <i class="material-icons">sync</i>
                                    Ambil TTV Terakhir
                                </button>
                            </div>

                            <!-- SOAPIE FORM - MODERN CARD LAYOUT -->
                            <div class="form-section">
                                <div class="form-section-title">SOAPIE</div>
                                
                                <!-- Grid container untuk 6 section SOAPIE -->
                                <div class="soapie-container">
                                    
                                    <!-- S - Subjective -->
                                    <div class="soapie-card subjective">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">record_voice_over</i>
                                            <span>S - Subjective</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <textarea name="subjective" 
                                                      class="soapie-textarea" 
                                                      placeholder="Keluhan pasien, anamnesis, riwayat penyakit..."
                                                      data-section="subjective"></textarea>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- O - Objective -->
                                    <div class="soapie-card objective">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">assignment</i>
                                            <span>O - Objective</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <textarea name="objective" 
                                                      class="soapie-textarea" 
                                                      placeholder="Hasil pemeriksaan fisik, pemeriksaan penunjang..."
                                                      data-section="objective"></textarea>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- A - Assessment -->
                                    <div class="soapie-card assessment">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">local_hospital</i>
                                            <span>A - Assessment</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <textarea name="assessment" 
                                                      class="soapie-textarea" 
                                                      placeholder="Diagnosa, penilaian klinis, diagnosis banding..."
                                                      data-section="assessment"></textarea>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- P - Plan -->
                                    <div class="soapie-card plan">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">event_note</i>
                                            <span>P - Plan</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <textarea name="plan" 
                                                      class="soapie-textarea" 
                                                      placeholder="Rencana tindakan, terapi, rujukan..."
                                                      data-section="plan"></textarea>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- I - Intervention -->
                                    <div class="soapie-card intervention">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">healing</i>
                                            <span>I - Intervention</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <textarea name="intervention" 
                                                      class="soapie-textarea" 
                                                      placeholder="Intervensi yang dilakukan, tindakan medis..."
                                                      data-section="intervention"></textarea>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- E - Evaluation -->
                                    <div class="soapie-card evaluation">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">fact_check</i>
                                            <span>E - Evaluation</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <textarea name="evaluation" 
                                                      class="soapie-textarea" 
                                                      placeholder="Evaluasi hasil tindakan, respon pasien..."
                                                      data-section="evaluation"></textarea>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" 
                                            id="btnSimpanSOAPIE" 
                                            class="btn-save-soapie"
                                            style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(76, 175, 80, 0.4); background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); border: none; color: white;">
                                        <i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">save</i>
                                        Simpan Pemeriksaan
                                    </button>
                                    
                                    <button type="button" 
                                            id="btnHapusSOAPIE"
                                            class="btn-delete-soapie"
                                            style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(244, 67, 54, 0.4); background: linear-gradient(135deg, #f44336 0%, #c62828 100%); border: none; color: white; margin-left: 10px; display: none;">
                                        <i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">delete</i>
                                        Hapus Pemeriksaan
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="no_rawat" value="<?=$datapasien['no_rawat']?>">
                            <input type="hidden" name="tgl_perawatan" id="tgl_perawatan" value="">
                            <input type="hidden" name="jam_rawat" id="jam_rawat" value="">
                        </form>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_eresep">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form E-Resep dalam tahap pengembangan.
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_diagnosa">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form Diagnosa dalam tahap pengembangan.
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_tindakan">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form Tindakan dalam tahap pengembangan.
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_laboratorium">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form Laboratorium dalam tahap pengembangan.
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_radiologi">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form Radiologi dalam tahap pengembangan.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- end #rmeContent_pemeriksaan -->

<!-- Container untuk konten tab AJAX (RME forms) -->
<div id="rmeTabAjaxContainer">
    <!-- Tab AJAX akan ditambahkan di sini oleh RME Tab Manager -->
</div>

</div><!-- end #rmeTabContentWrapper -->

<!-- 3. RIWAYAT SOAPIE -->
<div class="row clearfix">
  <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
    <div class="card">
      <div class="header bg-orange">
        <h2>RIWAYAT PASIEN</h2>
        <small class="text-muted" id="last-update">Terakhir diupdate: -</small>
      </div>

      <div class="body">
        <!-- Sub Tabs -->
        <ul class="nav nav-tabs tab-nav-right" role="tablist" id="riwayatSubTabs">
          <li role="presentation" class="active"><a href="#tab_riwayat_pemeriksaan" data-toggle="tab">PEMERIKSAAN</a></li>
          <li role="presentation"><a href="#tab_riwayat_soapie" data-toggle="tab">SOAPIE</a></li>
          <li role="presentation"><a href="#tab_riwayat_obat" data-toggle="tab">OBAT & BHP</a></li>
          <li role="presentation"><a href="#tab_riwayat_lab" data-toggle="tab">LABORATORIUM</a></li>
          <li role="presentation"><a href="#tab_riwayat_rad" data-toggle="tab">RADIOLOGI</a></li>
          <li role="presentation"><a href="#tab_riwayat_operasi" data-toggle="tab">OPERASI</a></li>
          <li role="presentation"><a href="#tab_riwayat_kunjungan" data-toggle="tab">KUNJUNGAN</a></li>
          <li role="presentation"><a href="#tab_riwayat_semua" data-toggle="tab">SELURUH RIWAYAT</a></li>
        </ul>

        <div class="tab-content" style="padding-top:15px;">
          <!-- PEMERIKSAAN -->
          <div role="tabpanel" class="tab-pane fade in active" id="tab_riwayat_pemeriksaan">
            <div id="riwayat_pemeriksaan_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data pemeriksaan...</p>
              </div>
            </div>
          </div>

          <!-- SOAPIE dengan Sub-Tabs -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_soapie">
            
            <!-- Sub-Tabs untuk Rawat Jalan, Rawat Inap, dan Grafik -->
            <ul class="nav nav-tabs nav-tabs-secondary" role="tablist" id="soapieSubTabs">
              <li role="presentation">
                <a href="#tab_soapie_ralan" data-toggle="tab">RAWAT JALAN</a>
              </li>
              <li role="presentation" class="active">
                <a href="#tab_soapie_ranap" data-toggle="tab">RAWAT INAP</a>
              </li>
              <li role="presentation">
                <a href="#tab_soapie_grafik" data-toggle="tab">
                  <i class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 4px;">show_chart</i>
                  GRAFIK PEMERIKSAAN
                </a>
              </li>
            </ul>

            <div class="tab-content">
              <!-- RAWAT JALAN -->
              <div role="tabpanel" class="tab-pane fade" id="tab_soapie_ralan">
                <div id="riwayat_soapie_ralan_content">
                  <div class="text-center" style="padding: 20px;">
                    <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                    <p>Memuat data SOAPIE Rawat Jalan...</p>
                  </div>
                </div>
              </div>

              <!-- RAWAT INAP -->
              <div role="tabpanel" class="tab-pane fade in active" id="tab_soapie_ranap">
                <div id="riwayat_soapie_ranap_content">
                  <div class="text-center" style="padding: 20px;">
                    <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                    <p>Memuat data SOAPIE Rawat Inap...</p>
                  </div>
                </div>
              </div>

              <!-- GRAFIK TTV -->
              <div role="tabpanel" class="tab-pane fade" id="tab_soapie_grafik">
                <div id="grafik_ttv_content">
                  <div class="text-center" style="padding: 20px;">
                    <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                    <p>Memuat Grafik TTV...</p>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- OBAT & BHP -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_obat">
            <div id="riwayat_obat_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data obat & BHP...</p>
              </div>
            </div>
          </div>

          <!-- LABORATORIUM -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_lab">
            <div id="riwayat_lab_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data laboratorium...</p>
              </div>
            </div>
          </div>

          <!-- RADIOLOGI -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_rad">
            <div id="riwayat_rad_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data radiologi...</p>
              </div>
            </div>
          </div>

          <!-- OPERASI -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_operasi">
            <div id="riwayat_operasi_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data operasi...</p>
              </div>
            </div>
          </div>

          <!-- KUNJUNGAN -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_kunjungan">
            <div id="riwayat_kunjungan_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data kunjungan...</p>
              </div>
            </div>
          </div>

          <!-- SELURUH RIWAYAT -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_semua">
            <div id="riwayat_semua_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat seluruh riwayat pasien...</p>
              </div>
            </div>
          </div>
        </div> <!-- end .tab-content -->

      </div> <!-- end .body -->
    </div> <!-- end .card -->
  </div>
</div>


<!-- Load script terpisah dengan timestamp untuk bypass cache -->
<!-- <script src="js/pemeriksaan.js"></script> --> <!-- Disabled dulu -->
<script src="js/pemeriksaan_main_inap.js?v=<?=time()?>"></script>
<script src="js/soapie_enhancement_inap.js?v=<?=time()?>"></script>
<script src="js/pemeriksaansoapie_inap.js?v=<?=time()?>"></script>


<!-- FIX DROPDOWN KESADARAN - ULTIMATE SOLUTION -->
<script>
// ===================================================
// PEMERIKSAAN FORM HANDLERS - IMPROVED VERSION
// ===================================================
(function() {
    'use strict';
    
    // ✅ PERBAIKAN 1: Fungsi untuk menunggu jQuery
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            setTimeout(function() {
                waitForjQuery(callback);
            }, 100);
        }
    }
    
    // ✅ PERBAIKAN 2: Inisialisasi form handlers dengan jQuery ready
    function initFormHandlers() {

        
        // Check if jQuery is available
        if (typeof jQuery === 'undefined') {
   
            return;
        }
        
        const $ = jQuery;
        
        // Handle form submit
        $('#formPemeriksaan').off('submit').on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
         
            
            const btn = $('#btnSimpanSOAPIE');
            const originalHtml = btn.html();
            
            // Disable button
            btn.prop('disabled', true).html('<i class="material-icons spin" style="animation: spin 1s linear infinite;">autorenew</i> Menyimpan...');
            
            // Cek apakah mode edit
            let formData;
            const isEditMode = window.editPemeriksaanData !== null && window.editPemeriksaanData !== undefined;
            
            if (isEditMode) {
                // Mode EDIT - kirim ke endpoint update RANAP
                formData = $(this).serialize() + 
                    '&aksi=update_pemeriksaan_ranap' +
                    '&tgl_perawatan_lama=' + encodeURIComponent(window.editPemeriksaanData.tgl_perawatan) +
                    '&jam_rawat_lama=' + encodeURIComponent(window.editPemeriksaanData.jam_rawat);
            } else {
                // Mode INPUT BARU - ke tabel pemeriksaan_ranap
                formData = $(this).serialize() + '&simpan_pemeriksaan_ranap=1';
            }
            
         
            
            // Send AJAX ke proses3.php untuk rawat inap
            $.ajax({
                url: 'pages/proses3.php',
                type: 'POST',
                data: formData,
                dataType: 'html',
                timeout: 30000,
                success: function(response) {
                    
                    
                    // Cek apakah response adalah JSON (untuk mode edit)
                    let jsonResponse = null;
                    try {
                        jsonResponse = JSON.parse(response);
                    } catch(e) {
                        // Bukan JSON, lanjut proses biasa
                    }
                    
                    if (jsonResponse && jsonResponse.status === 'success') {
                        // Response JSON sukses (mode edit)
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: jsonResponse.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            // Reset mode edit
                            window.editPemeriksaanData = null;
                            $('#editModeBadge').remove();
                            $('#btnSimpanSOAPIE').html('<i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">save</i> Simpan Pemeriksaan');
                            
                            // Reset form
                            document.getElementById('formPemeriksaan').reset();
                            
                            // Reload riwayat
                            if (typeof PemeriksaanModule !== 'undefined' && typeof PemeriksaanModule.reloadPemeriksaan === 'function') {
                                PemeriksaanModule.reloadPemeriksaan();
                            }
                        });
                    } else if (jsonResponse && jsonResponse.status === 'error') {
                        // Response JSON error
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: jsonResponse.message,
                            confirmButtonText: 'OK'
                        });
                    } else if (response.indexOf('Berhasil') > -1) {
                        // Response HTML dengan kata 'Berhasil' (mode input baru)
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Data pemeriksaan rawat inap berhasil disimpan',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            // Reload riwayat SOAPIE RANAP
                            
                            if (typeof SOAPIEModule !== 'undefined') {
                                SOAPIEModule.reloadRanap();
                            }
                            // ✅ PERBAIKAN - Reload dan switch ke tab riwayat pemeriksaan
                           
                            if (typeof PemeriksaanModule !== 'undefined') {
                                // Reload data pemeriksaan
                                if (typeof PemeriksaanModule.reloadPemeriksaan === 'function') {
                                    PemeriksaanModule.reloadPemeriksaan();
                                }
                                // Switch ke tab pemeriksaan
                                if (typeof PemeriksaanModule.switchToTabPemeriksaan === 'function') {
                                    setTimeout(function() {
                                        PemeriksaanModule.switchToTabPemeriksaan();
                                    }, 300);
                                }
                            }
                        });
                    } else if (response.indexOf('Gagal') > -1) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: 'Gagal menyimpan data pemeriksaan',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        // Execute any script in response
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = response;
                        const scripts = tempDiv.getElementsByTagName('script');
                        
                        for (let i = 0; i < scripts.length; i++) {
                            try {
                                eval(scripts[i].innerHTML);
                            } catch (e) {
                               
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
           
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: ' + error,
                        confirmButtonText: 'OK'
                    });
                },
                complete: function() {
                    // Re-enable button
                    btn.prop('disabled', false).html(originalHtml);
                   
                }
            });
            
            return false;
        });
        
        // Handle delete button
        $('#btnHapusSOAPIE').off('click').on('click', function() {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Yakin ingin menghapus data pemeriksaan ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f44336',
                cancelButtonColor: '#999',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // ✅ PERBAIKAN 3: Ganti endpoint hapus ke proses3.php untuk ranap
                    const formData = $('#formPemeriksaan').serialize() + '&hapus_pemeriksaan_ranap=1';
                    
                    $.ajax({
                        url: 'pages/proses3.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'html',
                        success: function(response) {
                            if (response.indexOf('Berhasil') > -1) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: 'Data berhasil dihapus',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(function() {
                                    // Clear form
                                    document.getElementById('formPemeriksaan').reset();
                                    $('#btnHapusSOAPIE').hide();
                                    
                                    // Reload riwayat RANAP
                                    if (typeof SOAPIEModule !== 'undefined') {
                                        SOAPIEModule.reloadRanap();
                                    }
                                });
                            }
                        }
                    });
                }
            });
        });
        
       
    }
    
    // ✅ PERBAIKAN 4: Gunakan waitForjQuery untuk inisialisasi
    waitForjQuery(function($) {
        
        
        $(document).ready(function() {
            
            setTimeout(initFormHandlers, 300);
        });
    });
    
})(); // End IIFE

// ===================================================
// CSS ANIMATION
// ===================================================
(function() {
    // Add CSS for spin animation
    if (!document.getElementById('spin-animation-style')) {
        const style = document.createElement('style');
        style.id = 'spin-animation-style';
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
})();

// ===================================================
// GLOBAL FUNCTIONS
// ===================================================

function konfirmasiSelesaiPeriksa() {
    Swal.fire({
        title: 'Konfirmasi Selesai',
        text: 'Apakah Anda yakin ingin menyelesaikan pemeriksaan pasien ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4caf50',
        cancelButtonColor: '#999',
        confirmButtonText: 'Ya, Selesai',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php?act=PasienInap';
        }
    });
}

function loadLastTTV() {
    // ✅ PERBAIKAN 5: Cek apakah jQuery tersedia
    if (typeof jQuery === 'undefined') {
        
        return;
    }
    
    const $ = jQuery;
    const btn = document.getElementById('btnLoadTTV');
    
    // ✅ PERBAIKAN 6: Validasi variabel PHP tersedia
    // Catatan: Variabel PHP ini akan di-replace saat file di-parse di server
    const norawat = '<?php echo isset($datapasien["no_rawat"]) ? $datapasien["no_rawat"] : ""; ?>';
    const norm = '<?php echo isset($datapasien["no_rkm_medis"]) ? $datapasien["no_rkm_medis"] : ""; ?>';
    
    if (!norawat || !norm) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Data pasien tidak ditemukan',
            confirmButtonText: 'OK'
        });
        return;
    }
    

    
    btn.classList.add('loading');
    btn.innerHTML = '<i class="material-icons">sync</i> Memuat...';
    
    $.ajax({
        url: 'pages/get_last_ttv.php',
        type: 'POST',
        data: {
            no_rawat: norawat,
            no_rkm_medis: norm
        },
        dataType: 'json',
        success: function(response) {

            
            if (response.success) {
                const data = response.data;
                
                // Fill form dengan pengecekan
                if (data.tensi) $('input[name="tensi"]').val(data.tensi);
                if (data.nadi) $('input[name="nadi"]').val(data.nadi);
                if (data.respirasi) $('input[name="respiratory_rate"]').val(data.respirasi);
                if (data.suhu) $('input[name="suhu"]').val(data.suhu);
                if (data.spo2) $('input[name="spo2"]').val(data.spo2);
                if (data.berat) $('input[name="berat"]').val(data.berat);
                if (data.tinggi) $('input[name="tinggi"]').val(data.tinggi);
                if (data.kesadaran) $('select[name="kesadaran"]').val(data.kesadaran);
                if (data.gcs) $('input[name="gcs"]').val(data.gcs);
                if (data.alergi) $('input[name="alergi"]').val(data.alergi);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'TTV terakhir berhasil dimuat',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Tidak Ada Data',
                    text: response.message || 'Tidak ada data TTV sebelumnya',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function(xhr, status, error) {
            
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Terjadi kesalahan: ' + error,
                confirmButtonText: 'OK'
            });
        },
        complete: function() {
            btn.classList.remove('loading');
            btn.innerHTML = '<i class="material-icons">sync</i> Ambil TTV Terakhir';
        }
    });
}

// ===================================================
// DROPDOWN FIX
// ===================================================
(function() {
    'use strict';
    
    function fixDropdown() {
        // Cari semua select dengan class vital-select-v2
        const vitalSelects = document.querySelectorAll('.vital-select-v2');
        
        if (vitalSelects.length === 0) {
            
            return;
        }
        
        vitalSelects.forEach(function(select) {
            // Hapus Bootstrap classes
            select.classList.remove('selectpicker');
            select.classList.remove('show-tick');
            
            // Destroy Bootstrap-select jika sudah di-init
            if (typeof jQuery !== 'undefined' && jQuery(select).data('selectpicker')) {
                jQuery(select).selectpicker('destroy');
            }
            
            // Hapus wrapper Bootstrap-select jika ada
            const wrapper = select.closest('.bootstrap-select');
            if (wrapper && wrapper.parentNode) {
                wrapper.parentNode.insertBefore(select, wrapper);
                wrapper.remove();
            }
            
            // Force styling native
            select.style.cssText = `
                width: 100% !important;
                border: none !important;
                border-bottom: 2px solid #e8e8e8 !important;
                padding: 8px 20px 8px 0 !important;
                font-size: 15px !important;
                background: transparent !important;
                appearance: none !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E") !important;
                background-repeat: no-repeat !important;
                background-position: right center !important;
            `;
        });
        
        
    }
    
    // Execute immediately
    fixDropdown();
    
    // Execute on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fixDropdown);
    } else {
        // DOM already loaded
        fixDropdown();
    }
    
    // ✅ PERBAIKAN 7: Execute after jQuery loads dengan waitForjQuery
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            setTimeout(function() {
                waitForjQuery(callback);
            }, 100);
        }
    }
    
    waitForjQuery(function($) {
        $(document).ready(function() {
            setTimeout(fixDropdown, 100);
            setTimeout(fixDropdown, 500); // Double check
        });
    });
    
})();

// ===================================================
// EDIT & DELETE PEMERIKSAAN HANDLERS - IMPROVED
// ===================================================
(function() {
    'use strict';
    
    // Variable untuk menyimpan data edit
    window.editPemeriksaanData = null;
    
    // ✅ PERBAIKAN 1: Fungsi untuk menunggu jQuery
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            
            setTimeout(function() {
                waitForjQuery(callback);
            }, 100);
        }
    }
    
    function initEditDeleteHandlers() {
        
        
        const $ = jQuery;
        
        // ===== HANDLER TOMBOL EDIT =====
        $(document).on('click', '.edit_pemeriksaan', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const data = btn.data();
            
            
            
            // Simpan data edit ke variabel global
            window.editPemeriksaanData = {
                no_rawat: data.no_rawat,
                tgl_perawatan: data.tgl_perawatan,
                jam_rawat: data.jam_rawat
            };
            
            // Isi form dengan data yang akan diedit
            // TTV
            $('input[name="tensi"]').val(data.tensi || '');
            $('input[name="nadi"]').val(data.nadi || '');
            $('input[name="respiratory_rate"]').val(data.respirasi || '');
            $('input[name="suhu"]').val(data.suhu_tubuh || '');
            $('input[name="spo2"]').val(data.spo2 || '');
            $('input[name="berat"]').val(data.berat || '');
            $('input[name="tinggi"]').val(data.tinggi || '');
            $('select[name="kesadaran"]').val(data.kesadaran || '');
            $('input[name="gcs"]').val(data.gcs || '');
            $('input[name="alergi"]').val(data.alergi || '');
            $('input[name="lingkar_perut"]').val(data.lingkar_perut || '');
            
            // SOAPIE
            $('textarea[name="subjective"]').val(data.keluhan || '');
            $('textarea[name="objective"]').val(data.pemeriksaan || '');
            $('textarea[name="assessment"]').val(data.penilaian || '');
            $('textarea[name="plan"]').val(data.rtl || '');
            $('textarea[name="intervention"]').val(data.instruksi || '');
            $('textarea[name="evaluation"]').val(data.evaluasi || '');
            
            // Update character count untuk setiap textarea
            $('.soapie-textarea').each(function() {
                const charCount = $(this).val().length;
                $(this).closest('.soapie-card-body').find('.char-current').text(charCount);
            });
            
            // Tampilkan badge EDIT MODE
            showEditModeBadge(data.tgl_perawatan, data.jam_rawat);
            
            // Scroll ke form SOAPIE
            $('html, body').animate({
                scrollTop: $('#formPemeriksaan').offset().top - 100
            }, 500);
            
            // Switch ke tab Pemeriksaan
            $('a[href="#tab_pemeriksaan"]').tab('show');
            
            Swal.fire({
                icon: 'info',
                title: 'Mode Edit',
                html: 'Data pemeriksaan <strong>' + data.tgl_perawatan + ' ' + data.jam_rawat + '</strong> telah dimuat ke form.<br><br>Silakan edit dan klik <strong>Simpan Pemeriksaan</strong> untuk menyimpan perubahan.',
                confirmButtonText: 'OK'
            });
        });
        
        // ===== HANDLER TOMBOL DELETE (RANAP) =====
        $(document).on('click', '.delete_pemeriksaan', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const data = btn.data();
            
           
            
            Swal.fire({
                title: 'Konfirmasi Hapus',
                html: 'Yakin ingin menghapus data pemeriksaan<br><strong>' + data.tgl_perawatan + ' ' + data.jam_rawat + '</strong>?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f44336',
                cancelButtonColor: '#999',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Kirim request hapus ke proses3.php (RANAP)
                    $.ajax({
                        url: 'pages/proses3.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            aksi: 'hapus_pemeriksaan_ranap',
                            no_rawat: data.no_rawat,
                            tgl_perawatan: data.tgl_perawatan,
                            jam_rawat: data.jam_rawat
                        },
                        beforeSend: function() {
                            
                        },
                        success: function(response) {
                            
                            
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: response.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(function() {
                                    // Reload riwayat pemeriksaan
                                    if (typeof PemeriksaanModule !== 'undefined' && typeof PemeriksaanModule.reloadPemeriksaan === 'function') {
                                        PemeriksaanModule.reloadPemeriksaan();
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: response.message,
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: 'Terjadi kesalahan: ' + error,
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
        });
        
 
    }
    
    // Fungsi untuk menampilkan badge EDIT MODE
    function showEditModeBadge(tgl, jam) {
        // ✅ PERBAIKAN 2: Cek jQuery tersedia
        if (typeof jQuery === 'undefined') {
            
            return;
        }
        
        const $ = jQuery;
        
        // Hapus badge lama jika ada
        $('#editModeBadge').remove();
        
        // Buat badge baru
        const badge = $('<div id="editModeBadge" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 10px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(255, 152, 0, 0.4);">' +
            '<div>' +
            '<i class="material-icons" style="vertical-align: middle; margin-right: 8px;">edit</i>' +
            '<strong>MODE EDIT</strong> - Mengedit data: ' + tgl + ' ' + jam +
            '</div>' +
            '<button type="button" class="btn btn-sm" id="btnCancelEdit" style="background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 5px; padding: 5px 15px;">' +
            '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">close</i> Batal Edit' +
            '</button>' +
            '</div>');
        
        // Sisipkan badge sebelum form SOAPIE
        $('.form-section:has(.form-section-title:contains("SOAPIE"))').before(badge);
        
        // Handler untuk tombol Batal Edit
        $('#btnCancelEdit').on('click', function() {
            cancelEditMode();
        });
        
        // Update tombol simpan
        $('#btnSimpanSOAPIE').html('<i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">save</i> Update Pemeriksaan');
    }
    
    // Fungsi untuk membatalkan mode edit
    function cancelEditMode() {
        window.editPemeriksaanData = null;
        
        // ✅ PERBAIKAN 3: Cek jQuery tersedia
        if (typeof jQuery !== 'undefined') {
            jQuery('#editModeBadge').remove();
            jQuery('#btnSimpanSOAPIE').html('<i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">save</i> Simpan Pemeriksaan');
        }
        
        // Reset form
        const form = document.getElementById('formPemeriksaan');
        if (form) {
            form.reset();
        }
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Mode Edit Dibatalkan',
                text: 'Form telah direset ke mode input baru',
                timer: 1500,
                showConfirmButton: false
            });
        }
    }
    
    // Expose fungsi ke global
    window.cancelEditMode = cancelEditMode;
    window.showEditModeBadge = showEditModeBadge;
    
    // ✅ PERBAIKAN 4: Initialize dengan waitForjQuery
    waitForjQuery(function($) {
        
        
        $(document).ready(function() {
           
            setTimeout(initEditDeleteHandlers, 600);
        });
    });
    
})(); // End IIFE

// ===================================================
// MULAI PERIKSA FUNCTION
// ===================================================
function mulaiPeriksa() {
    // ✅ PERBAIKAN 5: Cek dependencies
    if (typeof jQuery === 'undefined') {
       
        return;
    }
    
    if (typeof Swal === 'undefined') {
        
        return;
    }
    
    const $ = jQuery;
    
    // ✅ PERBAIKAN 6: Validasi variabel PHP
    const norawat = '<?php echo isset($datapasien["no_rawat"]) ? $datapasien["no_rawat"] : ""; ?>';
    
    if (!norawat) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Data pasien tidak ditemukan',
            confirmButtonText: 'OK'
        });
        return;
    }
    

    
    // Konfirmasi dulu
    Swal.fire({
        title: 'Mulai Pemeriksaan?',
        text: 'Status berkas akan diupdate menjadi "Sudah Diterima"',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2196f3',
        cancelButtonColor: '#999',
        confirmButtonText: 'Ya, Mulai!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Memproses...',
                html: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // AJAX call ke mulai_periksa.php (HANYA UPDATE MUTASI_BERKAS)
            $.ajax({
                url: 'pages/mulai_periksa.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    no_rawat: norawat
                },
                success: function(response) {
                    
                    
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            html: '<strong>' + response.message + '</strong><br><br>' +
                                  'Status: ' + (response.data.status || '-') + '<br>' +
                                  'Diterima: ' + (response.data.diterima || '-'),
                            timer: 2000,
                            showConfirmButton: false
                        }).then(function() {
                            // Scroll ke form pemeriksaan
                            const formElement = $('#formPemeriksaan');
                            if (formElement.length) {
                                $('html, body').animate({
                                    scrollTop: formElement.offset().top - 100
                                }, 500);
                                
                                // Focus ke input pertama
                                setTimeout(function() {
                                    $('input[name="tensi"]').focus();
                                }, 600);
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: response.message || 'Terjadi kesalahan',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                   
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: ' + error,
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
    });
}

// ===================================================
// SELESAI PERIKSA FUNCTION
// ===================================================
function konfirmasiSelesaiPeriksa() {
    // ✅ PERBAIKAN 7: Cek dependencies
    if (typeof jQuery === 'undefined') {
        
        return;
    }
    
    if (typeof Swal === 'undefined') {
       
        return;
    }
    
    const $ = jQuery;
    
    // ✅ PERBAIKAN 8: Validasi variabel PHP
    const norawat = '<?php echo isset($datapasien["no_rawat"]) ? $datapasien["no_rawat"] : ""; ?>';
    
    if (!norawat) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Data pasien tidak ditemukan',
            confirmButtonText: 'OK'
        });
        return;
    }
    

    
    Swal.fire({
        title: 'Konfirmasi Selesai',
        text: 'Apakah Anda yakin ingin menyelesaikan pemeriksaan pasien ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4caf50',
        cancelButtonColor: '#999',
        confirmButtonText: 'Ya, Selesai',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Memproses...',
                html: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // AJAX call ke selesai_pemeriksaan.php
            $.ajax({
                url: 'pages/selesai_pemeriksaan.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    no_rawat: norawat
                },
                success: function(response) {
                    
                    
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            html: '<strong>' + response.message + '</strong><br><br>' +
                                  'Status Registrasi: ' + (response.data.reg_status || '-') + '<br>' +
                                  'Status Berkas: ' + (response.data.berkas_status || '-') + '<br>' +
                                  'Kembali: ' + (response.data.kembali || '-'),
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#4caf50'
                        }).then(function() {
                            // ✅ PERBAIKAN 9: Redirect ke PasienInap untuk RANAP
                            window.location.href = 'index.php?act=PasienInap';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: response.message || 'Terjadi kesalahan',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                   
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: ' + error,
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
    });
}

// ===================================================
// TOGGLE COLLAPSE FUNCTION
// ===================================================
function toggleCollapse(header) {
    // ✅ PERBAIKAN 10: Validasi parameter
    if (!header) {
      
        return;
    }
    
    const content = header.nextElementSibling;
    
    if (!content) {
       
        return;
    }
    
    header.classList.toggle('collapsed');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
    } else {
        content.style.display = 'none';
    }
}

// ===================================================
// ✅ AUTO-SWITCH TAB dari Notifikasi (Deep Link) - RANAP
// Detect parameter ?tab=lab atau ?tab=rad dari URL
// ===================================================
(function() {
    'use strict';
    
    // ✅ PERBAIKAN 1: Fungsi untuk menunggu jQuery
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            
            setTimeout(function() {
                waitForjQuery(callback);
            }, 100);
        }
    }
    
    // ✅ PERBAIKAN 2: Fungsi getUrlParam tidak bergantung pada jQuery
    function getUrlParam(param) {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(param);
        } catch (e) {
           
            return null;
        }
    }
    
    function initDeepLinkTab($) {
        const targetTab = getUrlParam('tab');
        const filterNoRawat = getUrlParam('filter_norawat');
        
        if (!targetTab) {
            
            return;
        }
        
      
        
        // Mapping tab parameter ke selector
        const tabMapping = {
            'lab': '#tab_riwayat_lab',
            'rad': '#tab_riwayat_rad',
            'soapie': '#tab_riwayat_soapie',
            'obat': '#tab_riwayat_obat',
            'operasi': '#tab_riwayat_operasi',
            'kunjungan': '#tab_riwayat_kunjungan',
            'semua': '#tab_riwayat_semua'
        };
        
        const tabSelector = tabMapping[targetTab];
        
        if (!tabSelector) {
          
            return;
        }
        
        // ✅ PERBAIKAN 3: Validasi tab element exists
        const $tabLink = $('a[href="' + tabSelector + '"]');
        if (!$tabLink.length) {
           
            return;
        }
        
        // Tunggu sampai DOM ready
        setTimeout(function() {
            
            
            try {
                // 1. Switch ke tab riwayat yang diminta
                $tabLink.tab('show');
                
                // 2. Scroll ke section riwayat
                setTimeout(function() {
                    const $riwayatTabs = $('#riwayatSubTabs');
                    if ($riwayatTabs.length) {
                        $('html, body').animate({
                            scrollTop: $riwayatTabs.offset().top - 100
                        }, 500);
                        
                    } else {
                        
                    }
                    
                    // 3. Jika ada filter_norawat, set filter dropdown setelah content loaded
                    if (filterNoRawat) {
                        setTimeout(function() {
                            applyNoRawatFilter($, targetTab, filterNoRawat);
                        }, 1000);
                    }
                }, 300);
                
            } catch (e) {
                
            }
            
        }, 800);
    }
    
    // ✅ PERBAIKAN 4: Pass jQuery sebagai parameter
    function applyNoRawatFilter($, tabType, noRawat) {
        
        
        try {
            if (tabType === 'lab') {
                const $filterLab = $('#filterNoRawatLab');
                if ($filterLab.length) {
                    $filterLab.val(noRawat);
                    $filterLab.trigger('change');
                   
                } else {
                   
                }
            } else if (tabType === 'rad') {
                const $filterRad = $('#filterNoRawatRad');
                if ($filterRad.length) {
                    $filterRad.val(noRawat);
                    $filterRad.trigger('change');
                    
                } else {
                    
                }
            } else {
                
            }
        } catch (e) {
            
        }
    }
    
    // ✅ PERBAIKAN 5: Initialize dengan waitForjQuery
    waitForjQuery(function($) {
       
        
        $(document).ready(function() {
            
            initDeepLinkTab($);
        });
    });
    
   
    
})(); // End IIFE

// ===================================================
// DROPDOWN RME HANDLER
// ===================================================
(function() {
    'use strict';
    
    function initRmeDropdown() {
        const toggleBtn = document.getElementById('btnRmeToggle');
        const menu = document.getElementById('rmeDropdownMenu');
        
        if (!toggleBtn || !menu) return;

        // Fungsi update posisi menu
        function updateMenuPosition() {
            const rect = toggleBtn.getBoundingClientRect();
            menu.style.top = (rect.bottom + 5) + 'px';
            menu.style.left = rect.left + 'px';
        }
        
        // Toggle dropdown
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isOpen = menu.classList.contains('show');
            
            // Close first
            menu.classList.remove('show');
            toggleBtn.classList.remove('active');
            menu.style.position = '';
            menu.style.top = '';
            menu.style.left = '';
            menu.querySelectorAll('.has-submenu').forEach(function(el) {
                el.classList.remove('active');
            });
            
            // Open
            if (!isOpen) {
                menu.style.position = 'fixed';
                menu.style.zIndex = '999999';
                updateMenuPosition();
                menu.classList.add('show');
                toggleBtn.classList.add('active');
            }
        });

        // Update posisi saat scroll (semua level)
        document.addEventListener('scroll', function() {
            if (menu.classList.contains('show')) {
                updateMenuPosition();
            }
        }, true);
        
        // Submenu toggle
        menu.querySelectorAll('.has-submenu > a').forEach(function(trigger) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const parentLi = this.parentElement;
                const wasActive = parentLi.classList.contains('active');
                
                menu.querySelectorAll('.has-submenu').forEach(function(el) {
                    el.classList.remove('active');
                });
                
                if (!wasActive) {
                    parentLi.classList.add('active');
                }
            });
        });
        
        // Close on click outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#btnMenuRME')) {
                menu.classList.remove('show');
                toggleBtn.classList.remove('active');
                menu.style.position = '';
                menu.style.top = '';
                menu.style.left = '';
                menu.querySelectorAll('.has-submenu').forEach(function(el) {
                    el.classList.remove('active');
                });
            }
        });
        
        // Prevent close saat klik di dalam menu
        // KECUALI link ke index.php?act= (biarkan bubble ke RME Tab Manager)
        menu.addEventListener('click', function(e) {
            var clickedLink = e.target.closest('a[href*="index.php?act="]');
            if (clickedLink) {
                return;
            }
            e.stopPropagation();
        });
        
        console.log('✓ RME Dropdown initialized');
    }
    
    // Init on DOMContentLoaded or immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRmeDropdown);
    } else {
        initRmeDropdown();
    }
})();

// ===================================================
// RME TAB MANAGER - Browser-like Dynamic Tabs (RANAP)
// Logic sama dengan Rajal, tanpa hidden
// ===================================================
(function() {
    'use strict';

    // Registry tab yang terbuka
    const openTabs = {
        'pemeriksaan': {
            title: 'Pemeriksaan',
            closable: false,
            loaded: true,
            url: null
        }
    };

    let activeTabId = 'pemeriksaan';

    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            setTimeout(function() { waitForjQuery(callback); }, 100);
        }
    }

    waitForjQuery(function($) {
        $(document).ready(function() {
            console.log('✓ RME Tab Manager (Ranap) ready');

            // === FUNGSI: Switch ke tab ===
            function switchTab(tabId) {
                if (!openTabs[tabId]) return;

                // Jika tab sudah aktif tapi perlu reload
                if (activeTabId === tabId && tabId !== 'pemeriksaan' && !openTabs[tabId].loaded && openTabs[tabId].url) {
                    loadTabContent(tabId);
                    return;
                }

                // Deactivate semua tab
                $('#rmeTabBar .rme-tab').removeClass('active');
                // Deactivate semua content
                $('#rmeContent_pemeriksaan').removeClass('active');
                $('#rmeTabAjaxContainer .rme-tab-content-ajax').removeClass('active');

                // Activate tab yang dipilih
                $('#rmeTabBar .rme-tab[data-tab-id="' + tabId + '"]').addClass('active');

                if (tabId === 'pemeriksaan') {
                    $('#rmeContent_pemeriksaan').addClass('active');
                } else {
                    $('#rmeAjax_' + tabId).addClass('active');

                    // Load konten jika belum loaded
                    if (!openTabs[tabId].loaded && openTabs[tabId].url) {
                        loadTabContent(tabId);
                    }
                }

                activeTabId = tabId;
                console.log('🔄 Tab switched to:', tabId);
            }

            // === FUNGSI: Load konten tab via AJAX ===
            function loadTabContent(tabId) {
                const tab = openTabs[tabId];
                if (!tab || !tab.url) return;

                const $container = $('#rmeAjax_' + tabId);
                $container.html(
                    '<div class="rme-tab-loading">' +
                    '<i class="material-icons">autorenew</i>' +
                    '<p>Memuat ' + tab.title + '...</p>' +
                    '</div>'
                );

                console.log('🔄 Loading tab content:', tabId, tab.url);

                $.ajax({
                    url: tab.url,
                    type: 'GET',
                    timeout: 30000,
                    success: function(response) {
                        // Patch response: replace window.location.reload() dan history.back()
                        // di inline scripts sebelum inject ke DOM
                        var patchedResponse = response
                            .replace(/window\.location\.reload\(\)/g, '(window._rmeReloadPage ? window._rmeReloadPage() : window.location.reload())')
                            .replace(/window\.history\.back\(\)/g, '(window.RmeTabManager ? window.RmeTabManager.closeTab(window.RmeTabManager.getActiveTabId()) : window.history.back())');
                        
                        $container.html(patchedResponse);
                        
                        // Patch external scripts yang sudah di-load:
                        // Override onclick="window.history.back()" pada tombol di dalam tab
                        $container.find('[onclick*="history.back"]').each(function() {
                            $(this).removeAttr('onclick').on('click', function() {
                                var cTabId = window.RmeTabManager.getActiveTabId();
                                if (cTabId !== 'pemeriksaan') {
                                    window.RmeTabManager.closeTab(cTabId);
                                }
                            });
                        });
                        
                        tab.loaded = true;
                        console.log('✅ Tab content loaded:', tabId);
                        
                        // === HOOK: Trigger init functions untuk halaman yang dimuat via AJAX ===
                        setTimeout(function() {
                            // Neonatus
                            if(typeof initNeonatusForm === 'function') initNeonatusForm();
                            // Konsul Medik
                            if(typeof initKonsulMedikForm === 'function') initKonsulMedikForm();
                            // Generic: trigger custom event yang bisa didengar oleh halaman manapun
                            $(document).trigger('rmeTabContentLoaded', [tabId]);
                            console.log('✅ Tab init hooks triggered for:', tabId);
                        }, 200);
                    },
                    error: function(xhr, status, error) {
                        $container.html(
                            '<div class="alert alert-danger" style="margin:20px;">' +
                            '<strong>Gagal memuat halaman!</strong><br>' +
                            'Error: ' + error + ' (Status: ' + xhr.status + ')' +
                            '</div>'
                        );
                        console.error('❌ Failed to load tab:', tabId, error);
                    }
                });
            }

            // === FUNGSI: Buka tab baru ===
            function openTab(tabId, title, url) {
                // Cek duplikat
                if (openTabs[tabId]) {
                    switchTab(tabId);
                    return;
                }

                // Tambah ke registry
                openTabs[tabId] = {
                    title: title,
                    closable: true,
                    loaded: false,
                    url: url
                };

                // Buat elemen tab
                const $tab = $(
                    '<div class="rme-tab" data-tab-id="' + tabId + '" data-closable="true">' +
                    '<span class="rme-tab-title">' + title + '</span>' +
                    '<span class="rme-tab-close" data-tab-id="' + tabId + '">&times;</span>' +
                    '</div>'
                );
                $('#rmeTabScrollArea').append($tab);

                // Buat container konten
                const $content = $('<div class="rme-tab-content-ajax" id="rmeAjax_' + tabId + '"></div>');
                $('#rmeTabAjaxContainer').append($content);

                // Switch ke tab baru
                switchTab(tabId);

                console.log('✅ Tab opened:', tabId, title);
            }

            // === FUNGSI: Tutup tab ===
            function closeTab(tabId) {
                if (!openTabs[tabId] || !openTabs[tabId].closable) return;

                // Hapus elemen
                $('#rmeTabBar .rme-tab[data-tab-id="' + tabId + '"]').remove();
                $('#rmeAjax_' + tabId).remove();

                // Hapus dari registry
                delete openTabs[tabId];

                // Jika tab yang ditutup sedang aktif, switch ke tab sebelumnya
                if (activeTabId === tabId) {
                    const keys = Object.keys(openTabs);
                    switchTab(keys[keys.length - 1] || 'pemeriksaan');
                }

                console.log('✅ Tab closed:', tabId);
            }

            // === EVENT: Klik tab ===
            $(document).on('click', '.rme-tab', function(e) {
                if ($(e.target).hasClass('rme-tab-close')) return;
                const tabId = $(this).data('tab-id');
                switchTab(tabId);
            });

            // === EVENT: Klik close tab ===
            $(document).on('click', '.rme-tab-close', function(e) {
                e.stopPropagation();
                const tabId = $(this).data('tab-id');
                closeTab(tabId);
            });

            // === EVENT: Intercept klik menu RME ===
            $(document).on('click', '#rmeDropdownMenu a[href*="index.php?act="]', function(e) {
                e.preventDefault();

                const href = $(this).attr('href');
                const title = $(this).text().trim();

                // Extract act parameter
                const actMatch = href.match(/act=([^&]+)/);
                if (!actMatch) return;

                const act = actMatch[1];
                const tabId = act.toLowerCase();

                // Buat URL untuk AJAX load via rme_tab_loader.php (global)
                const pageUrl = href.replace('index.php?act=', 'pages/rme_tab_loader.php?act=');

                // Buka tab
                openTab(tabId, title, pageUrl);

                // Tutup dropdown menu
                $('#rmeDropdownMenu').removeClass('show');
                $('#btnRmeToggle').removeClass('active');
                $('#rmeDropdownMenu .has-submenu').removeClass('active');
            });

            // === OVERRIDE: Intercept navigasi dari dalam tab AJAX ===
            // Override tombol KEMBALI (history.back) di dalam tab AJAX
            $(document).on('click', '#rmeTabAjaxContainer button, #rmeTabAjaxContainer a', function(e) {
                var onclick = $(this).attr('onclick') || '';
                // Intercept history.back()
                if (onclick.indexOf('history.back') !== -1) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Cari tab AJAX mana yang mengandung tombol ini
                    var $ajaxPane = $(this).closest('.rme-tab-content-ajax');
                    if ($ajaxPane.length) {
                        var tabId = $ajaxPane.attr('id').replace('rmeAjax_', '');
                        closeTab(tabId);
                    } else {
                        switchTab('pemeriksaan');
                    }
                    return false;
                }
            });

            // Override window.location.reload() dari dalam tab AJAX
            // Caranya: patch setelah AJAX content loaded
            var _origReload = window.location.reload.bind(window.location);
            // Monkey-patch bisa fragile, jadi kita gunakan pendekatan MutationObserver
            // untuk mendeteksi script yang di-load di dalam tab dan patch-nya

            // === HELPER: Reload tab content (bukan full page) ===
            function reloadActiveTab() {
                if (activeTabId && activeTabId !== 'pemeriksaan' && openTabs[activeTabId]) {
                    openTabs[activeTabId].loaded = false;
                    loadTabContent(activeTabId);
                    return true;
                }
                return false;
            }

            // === Expose untuk akses global ===
            window.RmeTabManager = {
                openTab: openTab,
                closeTab: closeTab,
                switchTab: switchTab,
                reloadActiveTab: reloadActiveTab,
                getActiveTabId: function() { return activeTabId; },
                getOpenTabs: function() { return openTabs; }
            };

            // === GLOBAL HOOK: Override window.location untuk tab context ===
            // Ketika script di dalam tab AJAX memanggil window.location.reload(),
            // kita intercept dan reload tab saja
            (function() {
                // Simpan referensi asli
                var origLocationReload = window.location.reload.bind(window.location);
                
                // Override history.back
                var origHistoryBack = window.history.back.bind(window.history);
                window.history._origBack = origHistoryBack;
                
                window.history.back = function() {
                    // Cek apakah sedang di tab AJAX
                    if (window.RmeTabManager && window.RmeTabManager.getActiveTabId() !== 'pemeriksaan') {
                        var tabId = window.RmeTabManager.getActiveTabId();
                        console.log('🔄 Intercepted history.back() from tab:', tabId);
                        window.RmeTabManager.closeTab(tabId);
                        return;
                    }
                    origHistoryBack();
                };

                // Override location.reload via defineProperty
                // Ini tricky karena location.reload tidak bisa langsung di-override
                // Jadi kita patch melalui custom function
                window._rmeReloadPage = function() {
                    if (window.RmeTabManager && window.RmeTabManager.getActiveTabId() !== 'pemeriksaan') {
                        console.log('🔄 Intercepted reload from tab:', window.RmeTabManager.getActiveTabId());
                        window.RmeTabManager.reloadActiveTab();
                        return;
                    }
                    origLocationReload();
                };
                
                // Patch: Override location.reload melalui Object.defineProperty
                // Ini memungkinkan kita intercept window.location.reload() calls
                try {
                    var reloadDescriptor = Object.getOwnPropertyDescriptor(window.location, 'reload');
                    if (!reloadDescriptor || reloadDescriptor.configurable !== false) {
                        // location.reload biasanya non-configurable, jadi kita pakai proxy approach
                    }
                } catch(e) {
                    // Fallback: tidak bisa override location.reload langsung
                }
                
                // Alternative approach: Patch window.location.reload via proxy
                // Karena location.reload tidak bisa di-override langsung,
                // kita intercept dengan MutationObserver pada script execution
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && node.tagName === 'SCRIPT' && node.closest('#rmeTabAjaxContainer')) {
                                // Script di dalam tab AJAX
                                var origText = node.textContent;
                                if (origText.indexOf('window.location.reload') !== -1) {
                                    node.textContent = origText.replace(
                                        /window\.location\.reload\(\)/g,
                                        '(window._rmeReloadPage ? window._rmeReloadPage() : window.location.reload())'
                                    );
                                }
                                if (origText.indexOf('location.reload') !== -1 && origText.indexOf('window.location.reload') === -1) {
                                    node.textContent = node.textContent.replace(
                                        /location\.reload\(\)/g,
                                        '(window._rmeReloadPage ? window._rmeReloadPage() : location.reload())'
                                    );
                                }
                                // Intercept window.location.href redirects yang mengarah ke act=
                                if (origText.indexOf('window.location.href') !== -1) {
                                    node.textContent = node.textContent.replace(
                                        /window\.location\.href\s*=\s*['"]\?act=/g,
                                        'if(window._rmeReloadPage){window._rmeReloadPage();return;}window.location.href=\'?act='
                                    );
                                }
                            }
                        });
                    });
                });
                
                var ajaxContainer = document.getElementById('rmeTabAjaxContainer');
                if (ajaxContainer) {
                    observer.observe(ajaxContainer, { childList: true, subtree: true });
                    console.log('✅ MutationObserver active on rmeTabAjaxContainer');
                }
            })();

            console.log('✅ RME Tab Manager (Ranap) initialized');
        });
    });
})();
</script>