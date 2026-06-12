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
    background-color: #00bcd4 !important;
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

.checkbox-radiologi {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.table-scroll-wrapper tbody tr.selected {
    background-color: #b2ebf2 !important;
}

.table-scroll-wrapper tbody tr:hover {
    background-color: #f5f5f5;
}

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
    border-color: #00bcd4;
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
/* Animasi spin untuk tombol refresh */
.fa-spin {
    animation: fa-spin 1s infinite linear;
}

@keyframes fa-spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
</style>

<!-- <div class="alert alert-info" style="background: #00BCD4; color: white; border: none; border-radius: 0; padding: 12px 20px; margin-bottom: 0;">
    <i class="material-icons" style="vertical-align: middle; margin-right: 8px; font-size: 20px;">info</i>
    <strong>Info:</strong> Input Permintaan Rad untuk pasien dengan No. Rawat: <strong><?=$norawat?></strong>
</div> -->
<div style="margin-bottom: 20px;"></div>
<form id="formRadiologi" method="post">
    <input type="hidden" name="norawat" value="<?=$norawat?>">
    <input type="hidden" name="norm" value="<?=$norm?>">

    <div class="card">
        <div class="card-header" style="background: linear-gradient(135deg, #54b2ddff 0%, #14b8d4ff 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
            <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
                <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">assignment</i>
                Daftar Pemeriksaan Radiologi
            </h4>
        </div>
        <div class="body">
            <!-- Search Box -->
            <div class="search-box-wrapper">
                <input type="text" 
                       id="searchRadiologi" 
                       class="form-control" 
                       placeholder="Cari pemeriksaan berdasarkan kode atau nama...">
                <i class="material-icons search-icon">search</i>
                <i class="material-icons clear-search" id="clearSearchRad">close</i>
                <div class="search-info" id="searchInfoRad"></div>
            </div>

            <!-- Tabel dengan scroll -->
            <div class="table-scroll-wrapper">
                <table class="table table-bordered table-hover" id="tabelRadiologi">
                    <thead class="bg-cyan">
                        <tr>
                            <th width="5%" class="text-center"></th>
                            <th width="20%">Kode</th>
                            <th width="55%">Nama Pemeriksaan</th>
                            <th width="20%" class="text-right">Biaya</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Memuat data pemeriksaan...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <button type="button" id="btnTambahRadiologi" class="btn btn-primary waves-effect" style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);">
                <i class="material-icons">add</i> Tambah Pemeriksaan Terpilih
            </button>
        </div>
    </div>
</form>

<hr>

<!-- FORM INPUT - ULTRA COMPACT -->
<div style="background: linear-gradient(to right, #e3f2fd, #e1f5fe); border: 1px solid #4fc3f7; border-radius: 5px; padding: 12px; margin-bottom: 15px;">
    <div class="row">
        <!-- Tanggal -->
        <div class="col-sm-2">
            <label style="font-size: 11px; font-weight: bold; color: #0277bd; margin-bottom: 3px; display: block;">Tanggal *</label>
            <input type="date" id="tanggalPermintaanRad" class="form-control" style="height: 32px; border: 1px solid #00bcd4; font-size: 13px;" required>
        </div>
        
        <!-- Jam -->
        <div class="col-sm-2">
            <label style="font-size: 11px; font-weight: bold; color: #0277bd; margin-bottom: 3px; display: block;">Jam</label>
            <input type="text" id="jamPermintaanRad" class="form-control" style="height: 32px; border: 1px solid #00bcd4; font-size: 13px; background: #fff; font-family: monospace;" readonly>
        </div>
        
        <!-- Indikasi -->
        <div class="col-sm-4">
            <label style="font-size: 11px; font-weight: bold; color: #0277bd; margin-bottom: 3px; display: block;">Indikasi/Klinis <span style="color: red;">*</span></label>
            <input type="text" id="indikasiKlinisRad" class="form-control" placeholder="Contoh: Demam tinggi, suspek DBD" style="height: 32px; border: 1px solid #00bcd4; font-size: 13px;" required>
        </div>
        
        <!-- Informasi Tambahan -->
        <div class="col-sm-4">
            <label style="font-size: 11px; font-weight: bold; color: #0277bd; margin-bottom: 3px; display: block;">Info Tambahan <span style="color: red;">*</span></label>
            <input type="text" id="informasiTambahanRad" class="form-control" placeholder="Contoh: Pengobatan, alergi, dll" style="height: 32px; border: 1px solid #00bcd4; font-size: 13px;" required>
        </div>
    </div>
</div>

<!-- Keranjang Radiologi -->
<div id="wrapperKeranjangRad" style="display:none;">
            <table class="table table-bordered table-hover" id="tabelKeranjangRad">
                <thead class="bg-orange">
                    <tr>
                        <th width="5%">No</th>
                        <th width="15%">Kode</th>
                        <th width="50%">Nama Pemeriksaan</th>
                        <th width="15%" class="text-right">Biaya</th>
                        <th width="15%" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" align="center">
                            <em class="text-muted">Keranjang masih kosong. Pilih pemeriksaan di atas dan klik "Tambah Pemeriksaan Terpilih"</em>
                        </td>
                    </tr>
                </tbody>
            </table>
        
        
        </div>
<!-- Action Buttons Keranjang -->
        <div id="keranjangActionsRad" style="display:none; margin-top:15px;">
            <small class="text-muted" id="infoKeranjangRad"></small>
            <div style="margin-top:10px;">
                <button type="button" class="btn btn-success waves-effect" id="btnSimpanPermintaanRad">
                    <i class="material-icons">save</i> Simpan Permintaan Radiologi
                </button>
                <button type="button" class="btn btn-warning waves-effect" id="btnKosongkanKeranjangRad">
                    <i class="material-icons">delete_sweep</i> Kosongkan Keranjang
                </button>
            </div>
        </div>

<hr>


<!-- Riwayat Permintaan -->
<div class="card">
    <div class="card-header" style="background: linear-gradient(135deg, #6fe438ff 0%, #12b828ff 100%); color: white; padding: 15px 20px; border-radius: 12px 12px 0 0;">
        <h4 style="margin: 0; font-weight: 500; font-size: 18px;">
            <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">assignment</i>
            Daftar Permintaan Radiologi (Tersimpan)
        </h4>
    </div>
    <div class="body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="bg-green">
                    <tr>
                        <th width="5%">No</th>
                        <th width="12%">No. Order</th>
                        <th width="12%">Tanggal</th>
                        <th width="30%">Pemeriksaan</th>
                        <th width="15%">Dokter</th>
                        <th width="10%">Status</th>
                        <th width="10%" class="text-right">Biaya</th>
                        <th width="6%">Aksi</th>
                    </tr>
                </thead>
                <tbody id="listRiwayatRad">
                    <?php
                    // Query riwayat dengan JOIN yang benar
                    $query_riwayat = "SELECT 
                                        pr.noorder,
                                        pr.tgl_permintaan,
                                        pr.jam_permintaan,
                                        pr.tgl_sampel,
                                        pr.tgl_hasil,
                                        pr.status,
                                        d.nm_dokter,
                                        GROUP_CONCAT(jp.nm_perawatan SEPARATOR ', ') as pemeriksaan,
                                        SUM(jp.total_byr) as total_biaya
                                      FROM permintaan_radiologi pr
                                      LEFT JOIN permintaan_pemeriksaan_radiologi ppr ON pr.noorder = ppr.noorder
                                      LEFT JOIN jns_perawatan_radiologi jp ON ppr.kd_jenis_prw = jp.kd_jenis_prw
                                      LEFT JOIN dokter d ON pr.dokter_perujuk = d.kd_dokter
                                      WHERE pr.no_rawat = '$norawat'
                                      GROUP BY pr.noorder
                                      ORDER BY pr.tgl_permintaan DESC, pr.jam_permintaan DESC";
                    
                    $result_riwayat = bukaquery($query_riwayat);
                    
                    if(mysqli_num_rows($result_riwayat) > 0){
                        $no = 1;
                        while($riwayat = mysqli_fetch_array($result_riwayat)){
                            // Cek apakah sudah diambil sampel
                            $sudah_sampel = ($riwayat['tgl_sampel'] != '0000-00-00' && !empty($riwayat['tgl_sampel']));
                            $sudah_hasil = ($riwayat['tgl_hasil'] != '0000-00-00' && !empty($riwayat['tgl_hasil']));
                            
                            // Tentukan status berdasarkan tgl_sampel dan tgl_hasil
                            if ($sudah_hasil) {
                                $status_badge = '<span class="label label-success">Selesai</span>';
                                $btn_disabled = 'disabled';
                                $btn_class = 'btn-default';
                            } elseif ($sudah_sampel) {
                                $status_badge = '<span class="label label-primary">Sudah Diambil Sampel</span>';
                                $btn_disabled = 'disabled';
                                $btn_class = 'btn-default';
                            } else {
                                $status_badge = '<span class="label label-warning">Belum Diambil Sampel</span>';
                                $btn_disabled = '';
                                $btn_class = 'btn-danger';
                            }
                            
                            echo "<tr>
                                    <td align='center'>{$no}</td>
                                    <td>{$riwayat['noorder']}</td>
                                    <td>".konversiTanggal($riwayat['tgl_permintaan'])." {$riwayat['jam_permintaan']}</td>
                                    <td>{$riwayat['pemeriksaan']}</td>
                                    <td>{$riwayat['nm_dokter']}</td>
                                    <td align='center'>{$status_badge}</td>
                                    <td align='right'>Rp ".number_format($riwayat['total_biaya'], 0, ',', '.')."</td>
                                    <td align='center'>
                                        <button type='button' 
                                                class='btn {$btn_class} btn-xs waves-effect btn-hapus-riwayat-rad' 
                                                data-noorder='{$riwayat['noorder']}'
                                                {$btn_disabled}
                                                title='".($btn_disabled ? 'Tidak bisa dihapus (sudah diproses)' : 'Hapus')."'>
                                            <i class='material-icons'>delete</i>
                                        </button>
                                    </td>
                                  </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr>
                                <td colspan='8' align='center'>
                                    <em class='text-muted'>Belum ada riwayat permintaan radiologi</em>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<input type="hidden" id="norawat_rad" value="<?=$norawat?>">
<input type="hidden" id="norm_rad" value="<?=$norm?>">

<!-- Define APP_BASE_URL untuk JavaScript -->
<script>
    var APP_BASE_URL = '<?php echo APP_BASE_URL; ?>';
</script>

<!-- Load script eksternal -->
<script src="js/rad.js?v=<?php echo time(); ?>"></script>

<!-- Script untuk Tanggal dan Jam -->
<script>
(function() {
    function formatDate(date) {
        var year = date.getFullYear();
        var month = (date.getMonth() + 1).toString().padStart(2, '0');
        var day = date.getDate().toString().padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    function formatTime(date) {
        var hours = date.getHours().toString().padStart(2, '0');
        var minutes = date.getMinutes().toString().padStart(2, '0');
        var seconds = date.getSeconds().toString().padStart(2, '0');
        return hours + ':' + minutes + ':' + seconds;
    }
    
    var today = new Date();
    document.getElementById('tanggalPermintaanRad').value = formatDate(today);
    
    function updateClock() {
        var now = new Date();
        document.getElementById('jamPermintaanRad').value = formatTime(now);
    }
    
    updateClock();
    setInterval(updateClock, 1000);
})();

</script>