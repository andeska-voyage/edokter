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
    
    // Hitung prioritas berikutnya untuk DIAGNOSA
    $query_max_prioritas = bukaquery("SELECT IFNULL(MAX(prioritas), 0) as max_prioritas FROM diagnosa_pasien WHERE no_rawat = '$norawat'");
    $row_max = mysqli_fetch_assoc($query_max_prioritas);
    $next_prioritas_diagnosa = min($row_max['max_prioritas'] + 1, 9); // Max 9
    
    // Hitung prioritas berikutnya untuk PROSEDUR
    $query_max_prosedur = bukaquery("SELECT IFNULL(MAX(prioritas), 0) as max_prioritas FROM prosedur_pasien WHERE no_rawat = '$norawat'");
    $row_max_prosedur = mysqli_fetch_assoc($query_max_prosedur);
    $next_prioritas_prosedur = min($row_max_prosedur['max_prioritas'] + 1, 9); // Max 9
?>

<!-- <div class="alert alert-info" style="background: #00BCD4; color: white; border: none; border-radius: 0; padding: 12px 20px; margin-bottom: 0;">
    <i class="material-icons" style="vertical-align: middle; margin-right: 8px; font-size: 20px;">info</i>
    <strong>Info:</strong> Input Diagnosa untuk pasien dengan No. Rawat: <strong><?=$norawat?></strong>
</div> -->

<div style="margin-bottom: 20px;"></div>

<!-- FORM INPUT DIAGNOSA -->
<div class="card" style="margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
        <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
            <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">assignment</i>
            Input Diagnosa
        </h4>
    </div>
    <div class="card-body" style="padding: 25px;">
        <form id="formDiagnosa" method="post">
            <input type="hidden" name="norawat" value="<?=$norawat?>">
            <input type="hidden" name="norm" value="<?=$norm?>">
            
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">flag</i> Prioritas
                        </label>
                        <select name="prioritas" id="prioritas" class="form-control" style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s;">
                            <option value="1" <?= $next_prioritas_diagnosa == 1 ? 'selected' : '' ?>>1 - Primer</option>
                            <option value="2" <?= $next_prioritas_diagnosa == 2 ? 'selected' : '' ?>>2 - Sekunder</option>
                            <option value="3" <?= $next_prioritas_diagnosa == 3 ? 'selected' : '' ?>>3 - Tersier</option>
                            <option value="4" <?= $next_prioritas_diagnosa == 4 ? 'selected' : '' ?>>4</option>
                            <option value="5" <?= $next_prioritas_diagnosa == 5 ? 'selected' : '' ?>>5</option>
                            <option value="6" <?= $next_prioritas_diagnosa == 6 ? 'selected' : '' ?>>6</option>
                            <option value="7" <?= $next_prioritas_diagnosa == 7 ? 'selected' : '' ?>>7</option>
                            <option value="8" <?= $next_prioritas_diagnosa == 8 ? 'selected' : '' ?>>8</option>
                            <option value="9" <?= $next_prioritas_diagnosa >= 9 ? 'selected' : '' ?>>9</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">search</i> Cari Diagnosa (Kode ICD-10 / Nama Penyakit)
                        </label>
                        <div style="position: relative;">
                            <input type="text" id="cari_diagnosa" class="form-control" autocomplete="off" 
                                   placeholder="🔍 Ketik kode ICD-10 atau nama penyakit untuk mencari..." 
                                   style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                            <input type="hidden" id="kode_icd_hidden" name="kode_icd_hidden">
                            <ul id="icd10List" class="list-group" style="display:none; position:absolute; z-index:999; width:100%; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; margin-top: 5px; border: none;"></ul>
                        </div>
                        <small style="color: #999; display: block; margin-top: 5px;">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info_outline</i> 
                            Contoh: A09 atau "Diare"
                        </small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">local_hospital</i> Status
                        </label>
                        <select name="status" id="status" class="form-control" style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s;">
                            <option value="Ralan">Ralan</option>
                            <option value="Ranap" selected>Ranap</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12" style="margin-top: 10px;">
                    <button type="button" class="btn btn-primary waves-effect btn-tambah-diagnosa" 
                            style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);">
                        <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">add_circle</i> Tambah Diagnosa
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div style="margin-top: 30px;"></div>

<!-- TABEL DAFTAR DIAGNOSA -->
<div class="card" style="box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
        <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
            <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">list</i>
            Daftar Diagnosa
        </h4>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th width="5%" style="text-align: center;">No</th>
                        <th width="12%" style="text-align: center;">Prioritas</th>
                        <th width="12%">Kode ICD-10</th>
                        <th width="45%">Nama Diagnosa</th>
                        <th width="13%" style="text-align: center;">Status</th>
                        <th width="13%" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="listDiagnosa">
                    <?php
                    // Query diagnosa yang sudah ada
                    $query_diagnosa = bukaquery("SELECT diagnosa_pasien.*, penyakit.nm_penyakit 
                                                FROM diagnosa_pasien 
                                                LEFT JOIN penyakit ON diagnosa_pasien.kd_penyakit = penyakit.kd_penyakit
                                                WHERE diagnosa_pasien.no_rawat = '$norawat' 
                                                ORDER BY diagnosa_pasien.prioritas ASC");
                    
                    if(mysqli_num_rows($query_diagnosa) > 0){
                        $no = 1;
                        while($diag = mysqli_fetch_array($query_diagnosa)){
                            $prioritas_label = '';
                            $badge_color = '';
                            switch($diag['prioritas']){
                                case 1: 
                                    $prioritas_label = 'Primer'; 
                                    $badge_color = 'background: #4CAF50; color: white;';
                                    break;
                                case 2: 
                                    $prioritas_label = 'Sekunder'; 
                                    $badge_color = 'background: #FF9800; color: white;';
                                    break;
                                case 3: 
                                    $prioritas_label = 'Tersier'; 
                                    $badge_color = 'background: #9E9E9E; color: white;';
                                    break;
                                case 4:
                                    $prioritas_label = '4';
                                    $badge_color = 'background: #2196F3; color: white;';
                                    break;
                                case 5:
                                    $prioritas_label = '5';
                                    $badge_color = 'background: #9C27B0; color: white;';
                                    break;
                                case 6:
                                    $prioritas_label = '6';
                                    $badge_color = 'background: #00BCD4; color: white;';
                                    break;
                                case 7:
                                    $prioritas_label = '7';
                                    $badge_color = 'background: #795548; color: white;';
                                    break;
                                case 8:
                                    $prioritas_label = '8';
                                    $badge_color = 'background: #607D8B; color: white;';
                                    break;
                                case 9:
                                    $prioritas_label = '9';
                                    $badge_color = 'background: #424242; color: white;';
                                    break;
                                default:
                                    $prioritas_label = $diag['prioritas'];
                                    $badge_color = 'background: #757575; color: white;';
                                    break;
                            }
                            
                            echo "<tr>
                                    <td align='center' style='vertical-align: middle;'>".$no."</td>
                                    <td align='center' style='vertical-align: middle;'>
                                        <span style='display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; ".$badge_color."'>
                                            ".$prioritas_label."
                                        </span>
                                    </td>
                                    <td style='vertical-align: middle;'>
                                        <span style='font-weight: 600; color: #667eea; background: #f0f4ff; padding: 3px 10px; border-radius: 4px; font-size: 13px;'>
                                            ".$diag['kd_penyakit']."
                                        </span>
                                    </td>
                                    <td style='vertical-align: middle;'>".$diag['nm_penyakit']."</td>
                                    <td align='center' style='vertical-align: middle;'>
                                        <span style='display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; background: #E3F2FD; color: #2196F3; font-weight: 500;'>
                                            ".$diag['status']."
                                        </span>
                                    </td>
                                    <td align='center' style='vertical-align: middle;'>
                                        <button type='button' class='btn btn-danger btn-xs waves-effect btn-hapus-diagnosa' 
                                                data-norawat='".$diag['no_rawat']."' 
                                                data-kode='".$diag['kd_penyakit']."'
                                                data-prioritas='".$diag['prioritas']."'
                                                style='border-radius: 6px; padding: 5px 10px;'>
                                            <i class='material-icons' style='font-size: 18px;'>delete</i>
                                        </button>
                                    </td>
                                  </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr>
                                <td colspan='6' align='center' style='padding: 30px; color: #999;'>
                                    <i class='material-icons' style='font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;'>inbox</i>
                                    <em>Belum ada diagnosa</em>
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

<!-- FORM INPUT PROSEDUR -->
<div class="card" style="margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
    <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
        <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
            <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">healing</i>
            Input Prosedur
        </h4>
    </div>
    <div class="card-body" style="padding: 25px;">
        <form id="formProsedur" method="post">
            <input type="hidden" name="norawat" value="<?=$norawat?>">
            <input type="hidden" name="norm" value="<?=$norm?>">
            
            <div class="row">
                <div class="col-md-2">
                    <div class="form-group">
                        <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">flag</i> Prioritas
                        </label>
                        <select name="prioritas_prosedur" id="prioritas_prosedur" class="form-control" style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s;">
                            <option value="1" <?= $next_prioritas_prosedur == 1 ? 'selected' : '' ?>>1 - Primer</option>
                            <option value="2" <?= $next_prioritas_prosedur == 2 ? 'selected' : '' ?>>2 - Sekunder</option>
                            <option value="3" <?= $next_prioritas_prosedur == 3 ? 'selected' : '' ?>>3 - Tersier</option>
                            <option value="4" <?= $next_prioritas_prosedur == 4 ? 'selected' : '' ?>>4</option>
                            <option value="5" <?= $next_prioritas_prosedur == 5 ? 'selected' : '' ?>>5</option>
                            <option value="6" <?= $next_prioritas_prosedur == 6 ? 'selected' : '' ?>>6</option>
                            <option value="7" <?= $next_prioritas_prosedur == 7 ? 'selected' : '' ?>>7</option>
                            <option value="8" <?= $next_prioritas_prosedur == 8 ? 'selected' : '' ?>>8</option>
                            <option value="9" <?= $next_prioritas_prosedur >= 9 ? 'selected' : '' ?>>9</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group">
                        <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">search</i> Cari Prosedur (Kode ICD-9 / Nama Prosedur)
                        </label>
                        <div style="position: relative;">
                            <input type="text" id="cari_prosedur" class="form-control" autocomplete="off" 
                                   placeholder="🔍 Ketik kode ICD-9 atau nama prosedur untuk mencari..." 
                                   style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; padding-left: 15px; font-size: 14px; transition: all 0.3s;">
                            <input type="hidden" id="kode_icd9_hidden" name="kode_icd9_hidden">
                            <ul id="icd9List" class="list-group" style="display:none; position:absolute; z-index:999; width:100%; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; margin-top: 5px; border: none;"></ul>
                        </div>
                        <small style="color: #999; display: block; margin-top: 5px;">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info_outline</i> 
                            Contoh: 87.44 atau "Rontgen thorax"
                        </small>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label style="font-weight: 500; color: #555; margin-bottom: 8px; display: block; font-size: 13px;">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">local_hospital</i> Status
                        </label>
                        <select name="status_prosedur" id="status_prosedur" class="form-control" style="height: 45px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: all 0.3s;">
                            <option value="Ralan">Ralan</option>
                            <option value="Ranap" selected>Ranap</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12" style="margin-top: 10px;">
                    <button type="button" class="btn btn-primary waves-effect btn-tambah-prosedur" 
                            style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(245, 87, 108, 0.4); background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                        <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">add_circle</i> Tambah Prosedur
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div style="margin-top: 30px;"></div>

<!-- TABEL DAFTAR PROSEDUR -->
<div class="card" style="box-shadow: 0 2px 10px rgba(0,0,0,0.08); border-radius: 12px; border: none;">
    <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
        <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
            <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">list_alt</i>
            Daftar Prosedur
        </h4>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th width="5%" style="text-align: center;">No</th>
                        <th width="12%">Kode ICD-9</th>
                        <th width="55%">Nama Prosedur</th>
                        <th width="15%" style="text-align: center;">Status</th>
                        <th width="13%" style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="listProsedur">
                    <tr>
                        <td colspan="5" align="center" style="padding: 30px; color: #999;">
                            <i class="material-icons" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;">inbox</i>
                            <em>Belum ada prosedur</em>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="margin-top: 30px;"></div>


<!-- CSS sudah dipindahkan ke css/edokter.css -->
<script src="js/icd_inap.js"></script>