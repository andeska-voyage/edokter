<?php
session_start();
require_once('../conf/conf.php');

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
?>

<!-- <div class="alert alert-info" style="background: #00BCD4; color: white; border: none; border-radius: 0; padding: 12px 20px; margin-bottom: 0;">
    <i class="material-icons" style="vertical-align: middle; margin-right: 8px; font-size: 20px;">info</i>
    <strong>Info:</strong> Input Tindakan/Jasa untuk pasien dengan No. Rawat: <strong><?=$norawat?></strong>
</div> -->

<div style="margin-bottom: 20px;"></div>

<!-- FORM INPUT TINDAKAN -->
<div class="card" style="margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
    <div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
        <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
            <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">medical_services</i>
            Input Tindakan/Jasa
        </h4>
    </div>
    <div class="card-body" style="padding: 25px;">
        <form id="formTindakan" method="post">
            <input type="hidden" name="norawat" value="<?=$norawat?>">
            <input type="hidden" name="norm" value="<?=$norm?>">
            
            <div class="row">
                <div class="col-md-10">
                    <div class="form-group">
                        <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">search</i> Cari Tindakan/Jasa (Kode / Nama Tindakan)
                        </label>
                        <div style="position: relative;">
                            <input type="text" id="cari_tindakan" class="form-control" autocomplete="off" 
                                   placeholder="🔍 Ketik kode atau nama tindakan untuk mencari..." 
                                   style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                            <input type="hidden" id="kode_tindakan_hidden" name="kode_tindakan_hidden">
                            <ul id="tindakanList" class="list-group" style="display:none; position:absolute; z-index:999; width:100%; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; margin-top: 5px; border: none;"></ul>
                        </div>
                        <small style="color: #999; display: block; margin-top: 5px;">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info_outline</i> 
                            Contoh: RJ001 atau "Konsultasi Dokter"
                        </small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">local_hospital</i> Status
                        </label>
                        <select name="status_tindakan" id="status_tindakan" class="form-control" style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s;">
                            <option value="Ralan" selected>Ralan</option>
                            <!-- <option value="Ranap">Ranap</option> -->
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12" style="margin-top: 10px;">
                    <button type="button" class="btn btn-primary waves-effect btn-tambah-tindakan"
                            style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(17, 153, 142, 0.4); background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none;">
                        <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">add_circle</i> Tambah Tindakan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div style="margin-top: 30px;"></div>

<!-- TABEL DAFTAR TINDAKAN YANG SUDAH DIINPUT -->
<div class="card" style="box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
    <div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
        <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
            <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">list</i>
            Daftar Tindakan yang Sudah Diinput
        </h4>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th width="5%" style="text-align: center;">No</th>
                        <th width="12%">Kode</th>
                        <th width="35%">Nama Tindakan/Jasa</th>
                        <th width="12%" style="text-align: center;">Tanggal</th>
                        <th width="10%" style="text-align: center;">Jam</th>
                        <th width="15%" style="text-align: right;">Tarif Dokter</th>
                        <th width="11%" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="listTindakan">
                    <?php
                    // Query tindakan yang sudah diinput
                    $kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"], "d"), 20);
                    
                    $query_tindakan = bukaquery("
                        SELECT 
                            r.kd_jenis_prw,
                            r.tgl_perawatan,
                            r.jam_rawat,
                            r.biaya_rawat,
                            j.nm_perawatan
                        FROM rawat_jl_dr r
                        INNER JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw
                        WHERE r.no_rawat = '$norawat'
                        AND r.kd_dokter = '$kd_dokter'
                        ORDER BY r.tgl_perawatan DESC, r.jam_rawat DESC
                    ");
                    
                    if(mysqli_num_rows($query_tindakan) > 0){
                        $no = 1;
                        while($tindakan = mysqli_fetch_array($query_tindakan)){
                            $tarif = number_format($tindakan['biaya_rawat'], 0, ',', '.');
                            $tanggal = date('d/m/Y', strtotime($tindakan['tgl_perawatan']));
                            
                            echo "<tr>
                                    <td align='center'>{$no}</td>
                                    <td><strong style='color: #11998e;'>{$tindakan['kd_jenis_prw']}</strong></td>
                                    <td>{$tindakan['nm_perawatan']}</td>
                                    <td align='center'>{$tanggal}</td>
                                    <td align='center'>{$tindakan['jam_rawat']}</td>
                                    <td align='right'>
                                        <span style='color: #27ae60; font-weight: 600;'>Rp {$tarif}</span>
                                    </td>
                                    <td align='center'>
                                        <button class='btn btn-danger btn-sm btn-hapus-tindakan'
                                                data-kode='{$tindakan['kd_jenis_prw']}'
                                                data-tanggal='{$tindakan['tgl_perawatan']}'
                                                data-jam='{$tindakan['jam_rawat']}'
                                                data-norawat='{$norawat}'
                                                style='padding: 5px 12px; border-radius: 6px; font-size: 12px;'>
                                            <i class='material-icons' style='font-size: 16px; vertical-align: middle;'>delete</i>
                                        </button>
                                    </td>
                                  </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr>
                                <td colspan='7' align='center' style='padding: 30px; color: #999;'>
                                    <i class='material-icons' style='font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;'>inbox</i>
                                    <em>Belum ada tindakan yang diinput</em>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="margin-top: 30px;"></div>

<script src="js/tindakan.js"></script>