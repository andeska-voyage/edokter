<?php
    // =============================================
    // pemeriksaanriwayat.php - Riwayat Perawatan Pasien (View Only)
    // Menampilkan Data Pasien + Riwayat Pasien saja
    // Tanpa tab RME dan form Pemeriksaan/SOAPIE
    // =============================================

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
    
    // Cek status_lanjut untuk menentukan Ranap/Ralan
    $cek_status = bukaquery("SELECT status_lanjut FROM reg_periksa WHERE no_rawat='$norawat' AND no_rkm_medis='$norm'");
    $rs_status = mysqli_fetch_array($cek_status);
    if(!$rs_status){
        echo "<script>alert('Data registrasi tidak ditemukan!');</script>";
        JSRedirect("index.php?act=Pasien");
        exit();
    }
    $status_lanjut = $rs_status['status_lanjut'];
    $is_ranap = ($status_lanjut == 'Ranap');

    // Semua dokter bisa melihat riwayat pasien (tanpa validasi kepemilikan)
    
    // CEK RAWAT GABUNG (hanya untuk Ranap)
    $is_rawat_gabung = false;
    $norawat_induk = '';
    
    if($is_ranap) {
        $cek_gabung = bukaquery("SELECT no_rawat FROM ranap_gabung WHERE no_rawat2 = '$norawat'");
        $rs_gabung = mysqli_fetch_array($cek_gabung);
        
        if($rs_gabung) {
            $is_rawat_gabung = true;
            $norawat_induk = $rs_gabung['no_rawat'];
        }
    }
    
    if($is_ranap) {
        // === RAWAT INAP ===
        if($is_rawat_gabung) {
            $querypasien = bukaquery("SELECT pasien.no_rkm_medis, pasien.nm_pasien, pasien.jk, pasien.tmp_lahir, 
                                             pasien.tgl_lahir, pasien.alamat, reg_periksa.no_rawat, reg_periksa.tgl_registrasi,
                                             reg_periksa.jam_reg, kamar_inap.kd_kamar, kamar.kelas, bangsal.nm_bangsal,
                                             kamar_inap.diagnosa_awal, kamar_inap.diagnosa_akhir,
                                             kamar_inap.tgl_masuk, kamar_inap.jam_masuk,
                                             kamar_inap.tgl_keluar, kamar_inap.jam_keluar
                                      FROM reg_periksa 
                                      INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
                                      INNER JOIN kamar_inap ON kamar_inap.no_rawat = '$norawat_induk'
                                      INNER JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
                                      INNER JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
                                      WHERE reg_periksa.no_rawat = '$norawat' 
                                      AND reg_periksa.no_rkm_medis = '$norm'
                                      ORDER BY kamar_inap.tgl_masuk DESC, kamar_inap.jam_masuk DESC
                                      LIMIT 1");
        } else {
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
                                      ORDER BY kamar_inap.tgl_masuk DESC, kamar_inap.jam_masuk DESC
                                      LIMIT 1");
        }
    } else {
        // === RAWAT JALAN ===
        $querypasien = bukaquery("SELECT pasien.no_rkm_medis, pasien.nm_pasien, pasien.jk, pasien.tmp_lahir, 
                                         pasien.tgl_lahir, pasien.alamat, reg_periksa.no_rawat, reg_periksa.tgl_registrasi,
                                         reg_periksa.jam_reg, reg_periksa.kd_poli, poliklinik.nm_poli,
                                         dokter.nm_dokter, reg_periksa.stts, reg_periksa.status_bayar,
                                         reg_periksa.kd_pj, penjab.png_jawab
                                  FROM reg_periksa 
                                  INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
                                  INNER JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
                                  INNER JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
                                  INNER JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
                                  WHERE reg_periksa.no_rawat = '$norawat' 
                                  AND reg_periksa.no_rkm_medis = '$norm'");
    }
    
    $datapasien = mysqli_fetch_array($querypasien);
    
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
    
    /* Sub-tabs styling */
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
</style>

<!-- Hidden inputs agar JS pemeriksaan_main_inap.js bisa ambil data pasien -->
<form id="formPemeriksaan" style="display:none;">
    <input type="hidden" name="norawat" value="<?=$norawat?>">
    <input type="hidden" name="norm" value="<?=$norm?>">
    <input type="hidden" name="no_rawat" value="<?=$datapasien['no_rawat']?>">
</form>

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
                            <?php if($is_ranap): ?>
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>Kamar</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['nm_bangsal']?> - <?=$datapasien['kd_kamar']?> (Kelas <?=$datapasien['kelas']?>)
                                    <?php if($is_rawat_gabung): ?>
                                    <br><span style="background: #ff6b9d; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;">RAWAT GABUNG</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Masuk</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=konversiTanggal($datapasien['tgl_masuk'])?> <?=$datapasien['jam_masuk']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Keluar</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=($datapasien['tgl_keluar'] && $datapasien['tgl_keluar'] != '0000-00-00') ? konversiTanggal($datapasien['tgl_keluar']).' '.$datapasien['jam_keluar'] : '<span class="label label-success">Masih Dirawat</span>'?></td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>Poliklinik</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['nm_poli']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Tgl Periksa</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=konversiTanggal($datapasien['tgl_registrasi'])?> <?=$datapasien['jam_reg']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Dokter</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['nm_dokter']?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Alamat</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['alamat']?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <table class="table table-condensed" style="margin-bottom: 0;">
                            <?php if($is_ranap): ?>
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>Dx Awal</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['diagnosa_awal'] ?: '-'?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Dx Akhir</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['diagnosa_akhir'] ?: '-'?></td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>Status</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['stts']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Penjamin</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['png_jawab']?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                <!-- Tombol Refresh & Kembali -->
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- 2. RIWAYAT PASIEN (Langsung tampil tanpa tab RME) -->
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


<!-- Load JS untuk riwayat data (pakai JS inap yang sama) -->
 <script src="js/tentangaplikasi.js?v=<?=time()?>"></script>
<script src="js/pemeriksaan_main_inap.js?v=<?=time()?>"></script>
<script src="js/soapie_enhancement_inap.js?v=<?=time()?>"></script>
<script src="js/pemeriksaansoapie_inap.js?v=<?=time()?>"></script>

<script>
// ===================================================
// CSS ANIMATION
// ===================================================
(function() {
    if (!document.getElementById('spin-animation-style')) {
        const style = document.createElement('style');
        style.id = 'spin-animation-style';
        style.textContent = '@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
        document.head.appendChild(style);
    }
})();

// ===================================================
// AUTO-SWITCH TAB dari Deep Link (?tab=lab, ?tab=rad, dll)
// ===================================================
(function() {
    'use strict';
    
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            setTimeout(function() { waitForjQuery(callback); }, 100);
        }
    }
    
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
        if (!targetTab) return;
        
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
        if (!tabSelector) return;
        
        const $tabLink = $('a[href="' + tabSelector + '"]');
        if (!$tabLink.length) return;
        
        setTimeout(function() {
            try {
                $tabLink.tab('show');
                setTimeout(function() {
                    const $riwayatTabs = $('#riwayatSubTabs');
                    if ($riwayatTabs.length) {
                        $('html, body').animate({
                            scrollTop: $riwayatTabs.offset().top - 100
                        }, 500);
                    }
                }, 300);
            } catch (e) {}
        }, 800);
    }
    
    waitForjQuery(function($) {
        $(document).ready(function() {
            initDeepLinkTab($);
        });
    });
})();
</script>
