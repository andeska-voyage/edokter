<?php
    session_start();
    require_once('../conf/conf.php');
    require_once('../conf/app.php');  // Feature toggle (FITUR_LIMIT_BIAYA_RESEP_RANAP, dll) — auto-load agar tidak tergantung edit manual conf.php

    // Validasi session
    if(!isset($_SESSION["ses_dokter"])){
        echo "<div class='alert alert-danger'>Session expired</div>";
        exit();
    }
    
    // Ambil parameter
    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
    $norm = isset($_GET['norm']) ? validTeks4($_GET['norm'], 20) : '';
    
    if(empty($norawat) || empty($norm)){
        echo "<div class='alert alert-danger'>Parameter tidak valid</div>";
        exit();
    }

    // ============================================================
    // AMBIL KELAS KAMAR PASIEN UNTUK DEFAULT TARIF
    // Mapping: kelas kamar → kolom tarif di databarang
    // ============================================================
    $kelas_kamar  = '';
    $tarif_default = 'ralan'; // fallback default

    $query_kelas = bukaquery("
        SELECT kamar.kelas
        FROM kamar_inap
        INNER JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
        WHERE kamar_inap.no_rawat = '$norawat'
          AND kamar_inap.stts_pulang = '-'
        ORDER BY kamar_inap.tgl_masuk DESC, kamar_inap.jam_masuk DESC
        LIMIT 1
    ");

    if ($row_kelas = mysqli_fetch_array($query_kelas)) {
        $kelas_kamar = strtolower(trim($row_kelas['kelas']));
    }

    // Map nilai kelas dari tabel kamar ke nama kolom tarif di databarang
    $map_kelas_tarif = [
        '1'      => 'kelas1',
        'i'      => 'kelas1',
        'kelas 1'=> 'kelas1',
        'kelas1' => 'kelas1',
        '2'      => 'kelas2',
        'ii'     => 'kelas2',
        'kelas 2'=> 'kelas2',
        'kelas2' => 'kelas2',
        '3'      => 'kelas3',
        'iii'    => 'kelas3',
        'kelas 3'=> 'kelas3',
        'kelas3' => 'kelas3',
        'utama'  => 'utama',
        'vip'    => 'vip',
        'vvip'   => 'vvip',
        'super vip'=> 'vvip',
        'supervip' => 'vvip',
    ];

    if (!empty($kelas_kamar) && isset($map_kelas_tarif[$kelas_kamar])) {
        $tarif_default = $map_kelas_tarif[$kelas_kamar];
    }
?>

<style>
    .sub-tabs {
        border-bottom: 1px solid #ddd;
        margin-bottom: 20px;
    }
    .sub-tabs > li > a {
        color: #555;
        font-weight: 500;
        padding: 10px 15px;
        border-radius: 0;
    }
    .sub-tabs > li.active > a,
    .sub-tabs > li.active > a:focus,
    .sub-tabs > li.active > a:hover {
        color: #00bcd4;
        background-color: #fff;
        border-bottom: 3px solid #00bcd4;
    }
</style>

<!-- Hidden input untuk session dokter -->
<input type="hidden" id="ses_dokter" value="<?=encrypt_decrypt($_SESSION['ses_dokter'], 'd')?>">

<!-- Konfigurasi tarif berdasarkan kelas kamar pasien -->
<input type="hidden" id="tarif_default_inap" value="<?=$tarif_default?>">
<input type="hidden" id="kelas_kamar_inap" value="<?=$kelas_kamar?>">

<!-- Konfigurasi limit biaya resep dari conf.php -->
<input type="hidden" id="fitur_limit_biaya" value="<?=(defined('FITUR_LIMIT_BIAYA_RESEP_RANAP') && FITUR_LIMIT_BIAYA_RESEP_RANAP) ? '1' : '0'?>">
<input type="hidden" id="limit_biaya_resep" value="<?=defined('LIMIT_BIAYA_RESEP_RANAP') ? LIMIT_BIAYA_RESEP_RANAP : 0?>">

<div style="margin-bottom: 20px;"></div>

<!-- Sub Tabs untuk Non Racikan dan Racikan -->
<ul class="nav nav-tabs sub-tabs" role="tablist">
    <li role="presentation" class="active">
        <a href="#subtab_nonracikan" data-toggle="tab">
            NON RACIKAN 
            <span class="badge badge-keranjang-nr" id="badgeNonRacikan" style="background: #f44336; color: white; margin-left: 5px; border-radius: 10px; padding: 3px 8px; font-size: 11px; font-weight: 600; display: none;">0</span>
        </a>
    </li>
    <li role="presentation">
        <a href="#subtab_racikan" data-toggle="tab">
            RACIKAN 
            <span class="badge badge-keranjang-racikan" id="badgeRacikan" style="background: #ff9800; color: white; margin-left: 5px; border-radius: 10px; padding: 3px 8px; font-size: 11px; font-weight: 600; display: none;">0</span>
        </a>
    </li>
    <li role="presentation">
        <a href="#subtab_riwayat" data-toggle="tab">TEMPLATE OBAT</a>
    </li>
</ul>

<div class="tab-content">
<!-- Tab Non Racikan -->
    <div role="tabpanel" class="tab-pane fade in active" id="subtab_nonracikan">
        
        <!-- CARD FORM INPUT NON RACIKAN -->
        <div class="card" style="margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
            <div class="card-header" style="background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
                <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
                    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">local_pharmacy</i>
                    Input Resep Non Racikan
                </h4>
            </div>
            <div class="card-body" style="padding: 25px;">
                <form id="formNonRacikan">
                    <input type="hidden" name="norawat" value="<?=$norawat?>">
                    <input type="hidden" name="norm" value="<?=$norm?>">
                    <input type="hidden" name="kd_bangsal" value="AP">
                    
                    <div class="row">
                        <!-- Nama Obat: col-md-5 -->
                        <div class="col-md-5">
                            <div class="form-group">
                                <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                    Nama Obat <span class="text-danger">*</span>
                                </label>
                                <div style="position: relative;">
                                    <input type="text" name="nama_obat_search" id="nama_obat_search" class="form-control" 
                                           placeholder="🔍 Ketik nama / Kandungan obat untuk mencari..." 
                                           autocomplete="off"
                                           style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                                    <input type="hidden" name="kd_brng" id="kd_brng">
                                    <input type="hidden" name="harga_obat" id="harga_obat">
                                    <input type="hidden" name="stok_obat" id="stok_obat">
                    <input type="hidden" id="tarif_obat_json" name="tarif_obat_json">
                                    <ul id="obatList" class="list-group" style="display:none; position:absolute; z-index:999; width:100%; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; margin-top: 5px; border: none; max-height:250px; overflow-y:auto;"></ul>
                                </div>
                                <small style="color: #999; display: block; margin-top: 5px;">
                                    <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info_outline</i> 
                                    Contoh: Paracetamol 500mg
                                </small>
                            </div>
                        </div>
                        <!-- Stok: col-md-2 (readonly) -->
                        <div class="col-md-2">
                            <div class="form-group">
                                <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                    <i class="material-icons" style="font-size: 16px; vertical-align: middle;">inventory</i> Stok
                                </label>
                                <input type="text" id="stok_obat_display" class="form-control" 
                                       placeholder="-" readonly
                                       style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background-color: #f5f5f5; color: #2196F3; font-weight: 700; text-align: center;">
                            </div>
                        </div>
                        <!-- Jumlah: col-md-2 -->
                        <div class="col-md-2">
                            <div class="form-group">
                                <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                    <i class="material-icons" style="font-size: 16px; vertical-align: middle;">shopping_cart</i> Jumlah <span class="text-danger">*</span>
                                </label>
                                <input type="number" name="jumlah_nr" id="jumlah_nr" class="form-control" 
                                       placeholder="0" min="1" value="1"
                                       style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; text-align: center; transition: all 0.3s;">
                            </div>
                        </div>
                        <!-- Aturan Pakai: col-md-3 -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                    <i class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</i> Aturan Pakai
                                </label>
                                <div style="position: relative;">
                                    <input type="text" name="aturan_pakai_nr" id="aturan_pakai_nr" class="form-control" 
                                           placeholder="Contoh: 3x1 sehari" autocomplete="off"
                                           style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                                    <ul id="aturanPakaiNRList" class="list-group" 
                                        style="display:none; position:absolute; z-index:999; width:100%; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; margin-top: 5px; border: none; max-height:200px; overflow-y:auto;">
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12" style="margin-top: 10px;">
                            <button type="button" class="btn btn-primary waves-effect" id="btnTambahkanObat"
                                    style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(76, 175, 80, 0.4); background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); border: none;">
                                <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">add_shopping_cart</i> Tambahkan ke Keranjang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>

<!-- Tab Racikan -->
<div role="tabpanel" class="tab-pane fade" id="subtab_racikan">
    
    <!-- CARD FORM INPUT RACIKAN -->
    <div class="card" style="margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
        <div class="card-header" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
            <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">science</i>
                Input Resep Racikan
            </h4>
        </div>
        <div class="card-body" style="padding: 25px;">
            <form id="formRacikan" method="post">
                <input type="hidden" name="norawat" value="<?=$norawat?>">
                <input type="hidden" name="norm" value="<?=$norm?>">
                <input type="hidden" name="jenis" value="racikan">
                
                <!-- Baris pertama -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">label</i> Nama Racikan
                            </label>
                            <input type="text" name="nama_racikan" class="form-control" 
                                   placeholder="Contoh: Puyer Demam"
                                   style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">build</i> Metode Racikan
                            </label>
                            <div style="position: relative;">
                                <input type="text" 
                                    name="metode_racikan_search" 
                                    id="metode_racikan_search" 
                                    class="form-control" 
                                    placeholder="🔍 Ketik nama metode racikan..."
                                    autocomplete="off"
                                    style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                                <input type="hidden" id="kd_racik" name="kd_racik">
                                
                                <!-- List autocomplete -->
                                <ul id="metodeRacikanList" 
                                    class="list-group" 
                                    style="position: absolute; 
                                        z-index: 999; 
                                        width: 100%; 
                                        max-height: 300px; 
                                        overflow-y: auto; 
                                        display: none; 
                                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                                        border-radius: 8px;
                                        margin-top: 5px;
                                        border: none;">
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">shopping_cart</i> Jumlah Racikan
                            </label>
                            <div class="form-line">
                                <input type="number" name="jumlah_racikan" id="jumlah_racikan" class="form-control" 
                                       placeholder="10"
                                       style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</i> Aturan Pakai
                            </label>
                            <div style="position: relative;">
                                <input type="text" name="aturan_pakai_racikan" id="aturan_pakai_racikan" class="form-control" 
                                       placeholder="3x1" autocomplete="off"
                                       style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                                <ul id="aturanPakaiRacikanList" class="list-group" 
                                    style="display:none; position:absolute; z-index:999; width:100%; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; margin-top: 5px; border: none; max-height:200px; overflow-y:auto;">
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Baris kedua untuk Keterangan -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">description</i> Keterangan
                            </label>
                            <input type="text" name="keterangan_racikan" class="form-control" 
                                   placeholder="Keterangan tambahan (opsional)"
                                   style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                        </div>
                    </div>
                </div>

                <h5 style="font-weight: 500; color: #555; margin-top: 25px; margin-bottom: 15px; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px;">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">list</i> Komposisi Racikan
                </h5>
                
                <div class="row">
                    <!-- Nama Obat: col-md-4 -->
                    <div class="col-md-4">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                Nama Obat
                            </label>
                            <div style="position: relative;">
                                <div class="form-line">
                                    <input type="text" 
                                        name="nama_obat_racikan" 
                                        id="nama_obat_racikan" 
                                        class="form-control" 
                                        placeholder="🔍 Ketik nama / Kandungan obat..."
                                        autocomplete="off"
                                        style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                                    <input type="hidden" name="kd_brng_racikan" id="kd_brng_racikan">
                                    <input type="hidden" name="harga_obat_racikan" id="harga_obat_racikan">
                                    <input type="hidden" name="stok_obat_racikan" id="stok_obat_racikan">
                                    <input type="hidden" name="kapasitas_obat_racikan" id="kapasitas_obat_racikan">
                                </div>
                                
                                <!-- List autocomplete obat racikan -->
                                <ul id="obatRacikanList" 
                                    class="list-group" 
                                    style="position: absolute; 
                                           z-index: 999; 
                                           width: 100%; 
                                           max-height: 300px; 
                                           overflow-y: auto; 
                                           display: none; 
                                           box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                                           border-radius: 8px;
                                           margin-top: 5px;
                                           border: none;">
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- Stok: col-md-1 (readonly) -->
                    <div class="col-md-1">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">inventory</i> Stok
                            </label>
                            <input type="text" id="stok_obat_racikan_display" class="form-control" 
                                   placeholder="-" readonly
                                   style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background-color: #f5f5f5; color: #2196F3; font-weight: 700; text-align: center;">
                        </div>
                    </div>
                    <!-- Dosis Obat: col-md-2 -->
                    <div class="col-md-2">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">straighten</i> Dosis Obat
                            </label>
                            <div class="form-line">
                                <input type="text" name="dosis_obat_racikan" id="dosis_obat_racikan" class="form-control" readonly
                                       style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; background-color: #f5f5f5;">
                            </div>
                        </div>
                    </div>
                    <!-- Dosis yang diberi: col-md-2 -->
                    <div class="col-md-2">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">local_pharmacy</i> Dosis Diberi
                            </label>
                            <div class="form-line">
                                <input type="text" name="dosis_racikan" id="dosis_racikan" class="form-control" 
                                       placeholder="Contoh: 250 mg"
                                       style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                            </div>
                        </div>
                    </div>
                    <!-- Jumlah: col-md-2 -->
                    <div class="col-md-2">
                        <div class="form-group">
                            <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">calculate</i> Jumlah
                            </label>
                            <div class="form-line">
                                <input type="text" name="jml_racikan" id="jml_racikan" class="form-control" readonly
                                       style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; background-color: #f5f5f5;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12" style="margin-top: 10px;">
                        <button type="button" class="btn btn-info waves-effect" id="btn-tambah-komposisi"
                                style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; margin-right: 10px;">
                            <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">add</i> Tambah Komposisi
                        </button>
                        <button type="button" class="btn btn-primary waves-effect btn-simpan-racikan"
                                style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(255, 152, 0, 0.4); background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); border: none;">
                            <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</i> Masukkan Racikan ke Keranjang
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div style="margin-top: 30px;"></div>

    <!-- CARD TABEL KOMPOSISI SEMENTARA -->
    <div class="card" style="margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
        <div class="card-header" style="background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
            <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">assignment</i>
                Komposisi Sementara
            </h4>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" style="margin-bottom: 0;">
                    <thead class="bg-primary">
                        <tr>
                            <th width="5%" style="text-align: center;">NO</th>
                            <th width="35%">NAMA OBAT</th>
                            <th width="10%" style="text-align: center;">STOK</th>
                            <th width="12%" style="text-align: center;">DOSIS OBAT</th>
                            <th width="13%" style="text-align: center;">DOSIS DIBERI</th>
                            <th width="12%" style="text-align: center;">JUMLAH</th>
                            <th width="8%" style="text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody id="listKomposisiRacikan">
                        <tr>
                            <td colspan="7" align="center" style="padding: 30px; color: #999;">
                                <i class="material-icons" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;">inbox</i>
                                <em>Belum ada komposisi</em>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div role="tabpanel" class="tab-pane fade" id="subtab_riwayat">
    
    <!-- CARD TEMPLATE OBAT -->
    <div class="card" style="box-shadow: 0 4px 20px rgba(0,0,0,0.1); border-radius: 12px; border: none; margin-top: 20px;">
        <div class="card-header" style="background: linear-gradient(135deg, #e238bdff 0%, #ec23dcff 100%); color: white; padding: 20px; border-radius: 12px 12px 0 0; position: relative;">
            <div style="display: flex; justify-align-content: space-between; align-items: center;">
                <div>
                    <h4 style="margin: 0; font-weight: 500; font-size: 20px;">
                        <i class="material-icons" style="vertical-align: middle; margin-right: 8px; font-size: 24px;">history</i>
                        Template Obat/Paket Obat
                    </h4>
                    <small style="display: block; margin-top: 5px; opacity: 0.9; font-size: 12px;">
                        Pilih template obat yang sudah disediakan untuk memudahkan penulisan resep
                    </small>
                </div>
                <div style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); display: flex; align-items: center; gap: 15px;">
                    <!-- Search Box -->
                    <div style="position: relative;">
                        <input type="text" id="searchTemplateObat" 
                               placeholder="Cari template..." 
                               style="padding: 8px 35px 8px 12px; border-radius: 20px; border: none; font-size: 13px; width: 200px; background: rgba(255,255,255,0.9); color: #333;">
                        <i class="material-icons" id="btnClearSearchTemplate" 
                           style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); font-size: 18px; color: #999; cursor: pointer;">close</i>
                    </div>
                    <span id="totalTemplateObat" style="background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 20px; font-size: 13px; font-weight: 600;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">inventory</i> 
                        Total: <span id="totalTemplateObatCount">0</span> template
                    </span>
                </div>
            </div>
        </div>
        
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-resep-kompleks" style="margin-bottom: 0;">
                    <thead style="background: linear-gradient(135deg, #fd3e7eff 0%, #f53a84ff 100%); color: white;">
                        <tr>
                            <th width="15%" rowspan="2" style="padding: 15px; border-bottom: 2px solid #ddd; font-weight: 600; text-align: center; vertical-align: middle;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">tag</i> NO. TEMPLATE
                            </th>
                            <th width="20%" rowspan="2" style="padding: 15px; border-bottom: 2px solid #ddd; font-weight: 600; vertical-align: middle;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">medical_services</i> NAMA TEMPLATE
                            </th>
                            <th width="15%" rowspan="2" style="padding: 15px; border-bottom: 2px solid #ddd; font-weight: 600; text-align: center; vertical-align: middle;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">category</i> JENIS TEMPLATE
                            </th>
                            <th width="50%" colspan="5" style="padding: 15px; border-bottom: 2px solid #ddd; font-weight: 600; text-align: center; vertical-align: middle;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">assignment</i> DETAIL RESEP
                            </th>
                        </tr>
                        <tr style="background: #ffd966;">
                            <th width="4%" style="padding: 10px; border-bottom: 2px solid #ddd; font-weight: 600; text-align: center; vertical-align: middle; color: #000;">JML</th>
                            <th width="5%" style="padding: 10px; border-bottom: 2px solid #ddd; font-weight: 600; text-align: center; vertical-align: middle; color: #000;">SAT</th>
                            <th width="12%" style="padding: 10px; border-bottom: 2px solid #ddd; font-weight: 600; text-align: center; vertical-align: middle; color: #000;">ATURAN PAKAI</th>
                            <th width="10%" style="padding: 10px; border-bottom: 2px solid #ddd; font-weight: 600; text-align: center; vertical-align: middle; color: #000;">KODE/NO</th>
                            <th width="19%" style="padding: 10px; border-bottom: 2px solid #ddd; font-weight: 600; vertical-align: middle; color: #000;">NAMA OBAT/RACIKAN</th>
                        </tr>
                    </thead>
                    <tbody id="tabelTemplateObat">
                        <tr>
                            <td colspan="3" align="center" style="padding: 60px 40px; color: #999;">
                                <i class="material-icons spin" style="font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.3;">refresh</i>
                                <em style="font-size: 14px;">Memuat template obat...</em>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- CARD FOOTER DENGAN PAGINATION -->
        <div class="card-footer" style="background: #f5f5f5; padding: 15px 20px; border-radius: 0 0 12px 12px;">
            <div class="row">
                <div class="col-md-4">
                    <span style="font-weight: 500; color: #555;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">inventory</i>
                        Total Template: <span id="totalTemplateObatFooter">0</span>
                    </span>
                    <br>
                    <span style="font-size: 12px; color: #999;">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</i>
                        Menampilkan: <span id="showingTemplateObat">0</span> dari <span id="totalTemplateObatFooter2">0</span>
                    </span>
                </div>
                <div class="col-md-4" style="text-align: center;">
                    <!-- Pagination Controls -->
                    <div id="paginationTemplateControls" style="display: none;">
                        <button type="button" class="btn btn-default btn-sm waves-effect" id="btnFirstPageTemplate" title="Halaman Pertama">
                            <i class="material-icons" style="font-size: 16px;">first_page</i>
                        </button>
                        <button type="button" class="btn btn-default btn-sm waves-effect" id="btnPrevPageTemplate" title="Halaman Sebelumnya">
                            <i class="material-icons" style="font-size: 16px;">chevron_left</i>
                        </button>
                        <span style="margin: 0 15px; font-weight: 600; color: #673ab7;">
                            Hal <span id="currentPageTemplate">1</span> dari <span id="totalPagesTemplate">1</span>
                        </span>
                        <button type="button" class="btn btn-default btn-sm waves-effect" id="btnNextPageTemplate" title="Halaman Berikutnya">
                            <i class="material-icons" style="font-size: 16px;">chevron_right</i>
                        </button>
                        <button type="button" class="btn btn-default btn-sm waves-effect" id="btnLastPageTemplate" title="Halaman Terakhir">
                            <i class="material-icons" style="font-size: 16px;">last_page</i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4" style="text-align: right;">
                    <!-- Kosong atau bisa tambah filter nanti -->
                </div>
            </div>
        </div>
    </div>
    
</div>
</div>

<script type="text/javascript">
// ============================================================
// FUNGSI HITUNG JUMLAH RACIKAN - GLOBAL SCOPE
// ============================================================
function hitungJumlahRacikan() {
    var elDosisObat = document.getElementById('dosis_obat_racikan');
    var elDosisRacikan = document.getElementById('dosis_racikan');
    var elJumlahRacikan = document.getElementById('jumlah_racikan');
    var elJmlRacikan = document.getElementById('jml_racikan');
    
    if (!elDosisObat || !elDosisRacikan || !elJumlahRacikan || !elJmlRacikan) {
        return;
    }
    
    var dosisObat = elDosisObat.value;
    var dosisRacikan = elDosisRacikan.value;
    var jumlahRacikan = elJumlahRacikan.value;
    
    dosisObat = String(dosisObat).replace(',', '.').replace(/[^0-9.]/g, '');
    dosisRacikan = String(dosisRacikan).replace(',', '.').replace(/[^0-9.]/g, '');
    
    var dObat = parseFloat(dosisObat);
    var dRacikan = parseFloat(dosisRacikan);
    var jRacikan = parseFloat(jumlahRacikan);
    
    if (!isNaN(dObat) && !isNaN(dRacikan) && !isNaN(jRacikan) && 
        dObat > 0 && dRacikan > 0 && jRacikan > 0) {
        
        var hasil = (dRacikan / dObat) * jRacikan;
        var hasilFormat = hasil % 1 === 0 ? hasil.toString() : hasil.toFixed(2);
        elJmlRacikan.value = hasilFormat;
    } else {
        elJmlRacikan.value = '';
    }
}

// Setup event listeners saat dokumen ready
function setupEventListeners() {
    var elDosisRacikan = document.getElementById('dosis_racikan');
    var elJumlahRacikan = document.getElementById('jumlah_racikan');
    var elDosisObat = document.getElementById('dosis_obat_racikan');
    
    if (elDosisRacikan) {
        elDosisRacikan.addEventListener('input', hitungJumlahRacikan);
        elDosisRacikan.addEventListener('keyup', hitungJumlahRacikan);
        elDosisRacikan.addEventListener('change', hitungJumlahRacikan);
    }
    
    if (elJumlahRacikan) {
        elJumlahRacikan.addEventListener('input', hitungJumlahRacikan);
        elJumlahRacikan.addEventListener('keyup', hitungJumlahRacikan);
        elJumlahRacikan.addEventListener('change', hitungJumlahRacikan);
    }
    
    if (elDosisObat) {
        elDosisObat.addEventListener('input', hitungJumlahRacikan);
        elDosisObat.addEventListener('change', hitungJumlahRacikan);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupEventListeners);
} else {
    setupEventListeners();
}

if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function($) {
        $('#dosis_racikan, #jumlah_racikan, #dosis_obat_racikan').on('input keyup change', function() {
            hitungJumlahRacikan();
        });
    });
}
</script>

</div>

<div style="margin-top: 30px;"></div>

<hr style="border-top: 2px solid #e0e0e0; margin: 30px 0;">

<!-- ============================================================ -->
<!-- UNIFIED CART SECTION - Tampil di semua tab                   -->
<!-- ============================================================ -->

<!-- KERANJANG NON RACIKAN (Auto Hide jika kosong) -->
<div id="wrapperKeranjangNonRacikan" style="display: none; margin-bottom: 20px;">
    <div class="card" style="box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
        <div class="header bg-orange"
            style="display:flex;justify-content:space-between;align-items:center;
                    border-radius:10px 10px 0 0;padding:10px 16px;">
            <h2 style="margin:0;color:white;font-weight:600;font-size:15px;">
                <i class="material-icons" style="vertical-align:middle;margin-right:5px;">shopping_basket</i>
                Keranjang Resep Non Racikan
                <span class="badge" id="badgeKeranjangNR" style="background: #fff; color: #4caf50; margin-left: 8px; border-radius: 10px; padding: 3px 10px; font-size: 12px;">0</span>
            </h2>
            <button id="btnKosongkanKeranjangNonRacikan"
                    class="btn waves-effect"
                    style="background:#f44336;color:white;border:none;border-radius:12px;padding:6px 14px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;box-shadow:0 2px 4px rgba(0,0,0,0.2);"
                    onmouseover="this.style.background='#e53935'"
                    onmouseout="this.style.background='#f44336'">
                <i class="material-icons" style="font-size:16px;">delete_sweep</i>
                Kosongkan
            </button>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" style="margin-bottom: 0;">
                    <thead class="bg-green">
                        <tr>
                            <th width="5%" style="text-align: center;">NO</th>
                            <th width="12%">KODE OBAT</th>
                            <th width="33%">NAMA OBAT</th>
                            <th width="10%" style="text-align: center;">STOK</th>
                            <th width="10%" style="text-align: center;">JUMLAH</th>
                            <th width="20%">ATURAN PAKAI</th>
                            <th width="10%" style="text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody id="keranjangNonRacikan">
                        <tr id="emptyRow">
                            <td colspan="7" align="center" style="padding: 30px; color: #999;">
                                <i class="material-icons" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;">shopping_cart</i>
                                <em>Keranjang masih kosong. Tambahkan obat dari form di atas.</em>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- KERANJANG RACIKAN (Auto Hide jika kosong) -->
<div id="wrapperKeranjangRacikan" style="display: none; margin-bottom: 20px;">
    <div class="card" style="box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
        <div class="header bg-orange"
            style="display:flex;justify-content:space-between;align-items:center;
                    border-radius:10px 10px 0 0;padding:10px 16px;">
            <h2 style="margin:0;color:white;font-weight:600;font-size:15px;">
                <i class="material-icons" style="vertical-align:middle;margin-right:5px;">science</i>
                Keranjang Resep Racikan
                <span class="badge" id="badgeKeranjangRacikan" style="background: #fff; color: #ff9800; margin-left: 8px; border-radius: 10px; padding: 3px 10px; font-size: 12px;">0</span>
            </h2>
            <button id="btnKosongkanKeranjangRacikan"
                    class="btn waves-effect"
                    style="background:#f44336;color:white;border:none;border-radius:12px;padding:6px 14px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:4px;box-shadow:0 2px 4px rgba(0,0,0,0.2);"
                    onmouseover="this.style.background='#e53935'"
                    onmouseout="this.style.background='#f44336'">
                <i class="material-icons" style="font-size:16px;">delete_sweep</i>
                Kosongkan
            </button>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" style="margin-bottom: 0;">
                    <thead class="bg-orange">
                        <tr>
                            <th width="5%" rowspan="2" style="text-align: center; vertical-align: middle;">No</th>
                            <th width="15%" rowspan="2" style="vertical-align: middle;">Nama Racikan</th>
                            <th width="10%" rowspan="2" style="vertical-align: middle;">Metode</th>
                            <th colspan="4" style="text-align: center;">Komposisi</th>
                            <th width="8%" rowspan="2" style="text-align: center; vertical-align: middle;">Jumlah</th>
                            <th width="10%" rowspan="2" style="vertical-align: middle;">Aturan Pakai</th>
                            <th width="10%" rowspan="2" style="vertical-align: middle;">Keterangan</th>
                            <th width="2%" rowspan="2" style="text-align: center; vertical-align: middle;">Aksi</th>
                        </tr>
                        <tr>
                            <th width="20%" style="text-align: center; background: #ff9800;">Nama Obat</th>
                            <th width="10%" style="text-align: center; background: #ff9800;">Dosis Obat</th>
                            <th width="10%" style="text-align: center; background: #ffa726;">Dosis Diberi</th>
                            <th width="10%" style="text-align: center; background: #ff9800;">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody id="listResepRacikan">
                        <tr>
                            <td colspan="11" align="center" style="padding: 30px; color: #999;">
                                <i class="material-icons" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;">inbox</i>
                                <em>Belum ada resep racikan</em>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- TOMBOL SIMPAN -->
<div class="row">
    <div class="col-md-12">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn btn-success waves-effect btn-simpan-semua-resep"
                    style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(76, 175, 80, 0.4); background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); border: none;">
                <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</i> Simpan Semua Resep
            </button>
            <!-- Dropdown Tarif -->
            <div style="display: flex; align-items: center; gap: 8px; background: #f0f7ff; border: 2px solid #90caf9; border-radius: 8px; padding: 6px 14px;">
                <i class="material-icons" style="color: #1565c0; font-size: 20px;">local_offer</i>
                <label style="margin: 0; font-weight: 600; color: #1565c0; font-size: 13px;">Tarif :</label>
                <select id="selectJenisTarif" class="form-control" style="height: 33px; border: 1px solid #90caf9; border-radius: 6px; font-size: 13px; font-weight: 600; color: #1565c0; background: white; padding: 0 8px; min-width: 150px;">
                    <option value="ralan">Rawat Jalan</option>
                    <option value="kelas1">Kelas 1</option>
                    <option value="kelas2">Kelas 2</option>
                    <option value="kelas3">Kelas 3</option>
                    <option value="utama">Utama</option>
                    <option value="vip">VIP</option>
                    <option value="vvip">VVIP</option>
                    <option value="beliluar">Beli Luar</option>
                    <option value="jualbebas">Jual Bebas</option>
                    <option value="karyawan">Karyawan</option>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- BOX TOTAL BIAYA -->
<div id="wrapperBoxTotal" style="display: none; margin-top: 20px;">
    <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: stretch;">
        <div style="background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 8px; padding: 10px 24px; min-width: 180px;">
            <div style="font-size: 11px; color: #2e7d32; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">shopping_basket</i> Total Non Racikan
            </div>
            <div id="totalNonRacikanDisplay" style="font-size: 18px; font-weight: 700; color: #1b5e20;">Rp 0</div>
        </div>
        <div style="background: #fff3e0; border: 1px solid #ffcc80; border-radius: 8px; padding: 10px 24px; min-width: 180px;">
            <div style="font-size: 11px; color: #e65100; font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">science</i> Total Racikan
            </div>
            <div id="totalRacikanDisplay" style="font-size: 18px; font-weight: 700; color: #e65100;">Rp 0</div>
        </div>
        <div style="background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%); border-radius: 8px; padding: 10px 24px; min-width: 220px; position: relative;">
            <div style="font-size: 11px; color: rgba(255,255,255,0.8); font-weight: 600; text-transform: uppercase; margin-bottom: 4px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">payments</i> Grand Total
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <div id="grandTotalDisplay" style="font-size: 20px; font-weight: 700; color: #fff;">Rp 0</div>
                <span id="warnLimitBiaya" style="display:none; background:#fff3cd; color:#856404; border-radius:6px; padding:3px 8px; font-size:11px; font-weight:700; white-space:nowrap;">
                    <i class="material-icons" style="font-size:13px; vertical-align:middle;">warning</i> Melebihi Limit!
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- TABEL RIWAYAT RESEP TERSIMPAN (DATABASE)                    -->
<!-- ============================================================ -->
<div style="margin-top: 50px;"></div>

<div class="card" style="box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
    <div class="card-header" style="background: linear-gradient(135deg, #673ab7 0%, #512da8 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
        <h4 style="margin: 0; font-weight: 500; font-size: 18px; display: inline-block;">
            <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">history</i>
            Riwayat Resep Tersimpan
        </h4>
        <span style="float: right; background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 15px; font-size: 12px;">
            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">autorenew</i>
            Auto-reload setiap 10 detik
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" style="margin-bottom: 0; font-size: 12px;">
                <thead style="background: linear-gradient(135deg, #673ab7 0%, #512da8 100%); color: white;">
                    <tr>
                        <th width="18%" rowspan="2" style="text-align: center; vertical-align: middle;">NOMOR RESEP</th>
                        <th width="18%" rowspan="2" style="text-align: center; vertical-align: middle;">PASIEN</th>
                        <th width="10%" rowspan="2" style="text-align: center; vertical-align: middle;">STATUS</th>
                        <th width="54%" colspan="5" style="text-align: center; vertical-align: middle;">DETAIL RESEP</th>
                    </tr>
                    <tr style="background: #ffd966;">
                        <th width="4%" style="text-align: center; vertical-align: middle; color: #000; font-weight: bold;">Jml</th>
                        <th width="5%" style="text-align: center; vertical-align: middle; color: #000; font-weight: bold;">Sat</th>
                        <th width="12%" style="text-align: center; vertical-align: middle; color: #000; font-weight: bold;">Aturan Pakai</th>
                        <th width="12%" style="text-align: center; vertical-align: middle; color: #000; font-weight: bold;">Kode/No</th>
                        <th width="19%" style="text-align: center; vertical-align: middle; color: #000; font-weight: bold;">Nama Obat/Racikan</th>
                    </tr>
                </thead>
                <tbody id="tabelRiwayatResepDB">
                    <tr>
                        <td colspan="8" align="center" style="padding: 40px; color: #999;">
                            <i class="material-icons" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;">hourglass_empty</i>
                            <em>Memuat data riwayat resep...</em>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer" style="background: #f5f5f5; padding: 15px 20px; border-radius: 0 0 12px 12px;">
        <div class="row">
            <div class="col-md-4">
                <span style="font-weight: 500; color: #555;">
                    <i class="material-icons" style="font-size: 16px; vertical-align: middle;">receipt</i>
                    Total Resep: <span id="totalResepDB">0</span>
                </span>
            </div>
            <div class="col-md-4" style="text-align: center;">
                <div id="paginationControls" style="display: none;">
                    <button type="button" class="btn btn-default btn-sm waves-effect" id="btnFirstPage" title="Halaman Pertama">
                        <i class="material-icons" style="font-size: 16px;">first_page</i>
                    </button>
                    <button type="button" class="btn btn-default btn-sm waves-effect" id="btnPrevPage" title="Halaman Sebelumnya">
                        <i class="material-icons" style="font-size: 16px;">chevron_left</i>
                    </button>
                    <span style="margin: 0 15px; font-weight: 600; color: #673ab7;">
                        Hal <span id="currentPage">1</span> dari <span id="totalPages">1</span>
                    </span>
                    <button type="button" class="btn btn-default btn-sm waves-effect" id="btnNextPage" title="Halaman Berikutnya">
                        <i class="material-icons" style="font-size: 16px;">chevron_right</i>
                    </button>
                    <button type="button" class="btn btn-default btn-sm waves-effect" id="btnLastPage" title="Halaman Terakhir">
                        <i class="material-icons" style="font-size: 16px;">last_page</i>
                    </button>
                </div>
            </div>
            <div class="col-md-4" style="text-align: right;">
                <button type="button" class="btn btn-info btn-sm waves-effect" id="btnRefreshRiwayatDB">
                    <i class="material-icons" style="font-size: 16px; vertical-align: middle;">refresh</i> Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.table-resep-kompleks {
    border-collapse: collapse;
    width: 100%;
    font-size: 12px;
}
.table-resep-kompleks td {
    border: 1px solid #ddd;
    padding: 8px;
    vertical-align: top;
}
.rowspan-cell {
    vertical-align: top !important;
    text-align: left;
    font-weight: 400;
    background: #fafafa;
    padding: 12px !important;
    font-size: 12px;
    line-height: 1.6;
}
</style>

<script src="js/eresep_inap.js?v=<?=time()?>"></script>