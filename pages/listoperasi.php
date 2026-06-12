<?php
// Ambil info dokter yang login dari session (DECRYPT)
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';

if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Ambil nama dokter login
$nm_dokter_login = '';
if(!empty($kd_dokter_login)) {
    $queryDokter = bukaquery_safe("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter_login'");
    $rsDokter = mysqli_fetch_array($queryDokter);
    if($rsDokter) {
        $nm_dokter_login = $rsDokter['nm_dokter'];
    }
}

// Filter tanggal - shared untuk kedua tab
$tgl_operasi_dari = isset($_GET['tgl_dari']) ? $_GET['tgl_dari'] : date('Y-m-d');
$tgl_operasi_sampai = isset($_GET['tgl_sampai']) ? $_GET['tgl_sampai'] : date('Y-m-d');

// Active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'booking';

// ========================================
// Cek apakah dokter login adalah dokter anestesi
// ========================================
$is_anestesi = false;
if(!empty($kd_dokter_login) && defined('KD_DOKTER_ANESTESI')) {
    $cekSps = bukaquery_safe("SELECT kd_sps FROM dokter WHERE kd_dokter = '$kd_dokter_login'");
    $rsSps = mysqli_fetch_array($cekSps);
    if($rsSps && $rsSps['kd_sps'] == KD_DOKTER_ANESTESI) {
        $is_anestesi = true;
    }
}

// Filter tambahan untuk jadwal operasi: anestesi lihat semua, dokter lain hanya miliknya
$filter_booking_dokter = $is_anestesi ? "" : "AND bo.kd_dokter = '$kd_dokter_login'";

// ========================================
// COUNT BOOKING OPERASI
// ========================================
$query_count_booking = bukaquery_safe("SELECT COUNT(*) as total 
                          FROM booking_operasi bo
                          INNER JOIN reg_periksa rp ON bo.no_rawat = rp.no_rawat
                          WHERE bo.tanggal BETWEEN '$tgl_operasi_dari' AND '$tgl_operasi_sampai'
                          AND bo.status = 'Menunggu'
                          AND rp.status_bayar = 'Belum Bayar'
                          $filter_booking_dokter");
$count_booking = mysqli_fetch_array($query_count_booking)['total'];

// ========================================
// COUNT TOTAL OPERASI SELESAI (Berdasarkan filter tanggal & operator)
// ========================================
$query_count = bukaquery_safe("SELECT COUNT(*) as total 
                          FROM operasi 
                          WHERE DATE(tgl_operasi) BETWEEN '$tgl_operasi_dari' AND '$tgl_operasi_sampai'
                          AND (operator1 = '$kd_dokter_login' OR operator2 = '$kd_dokter_login' OR operator3 = '$kd_dokter_login' OR dokter_anestesi = '$kd_dokter_login')");
$count_operasi = mysqli_fetch_array($query_count)['total'];
?>

<!-- Panel Filter -->
<div class="row clearfix" style="margin-bottom: 20px;">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header" style="background: linear-gradient(135deg, #5FD38D 0%, #0F6FB2 100%); color: white;">
                <h2 style="color: white; margin: 0;">
                    <i class="material-icons" style="vertical-align: middle;">local_hospital</i>
                    LIST OPERASI / VK
                </h2>
            </div>
            <div class="body">
                <!-- ========== TAB BADGES ========== -->
                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <button type="button" onclick="switchTabOperasi('booking')" id="tab-btn-booking"
                            class="btn waves-effect" 
                            style="border-radius: 20px; padding: 8px 20px; font-weight: 600; font-size: 13px; border: 2px solid #ff9800; transition: all 0.3s ease;
                            <?php echo ($active_tab == 'booking') ? 'background: #ff9800; color: white;' : 'background: white; color: #ff9800;'; ?>">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</i>
                        Jadwal Operasi
                        <span id="badge-count-booking" style="background: <?php echo ($active_tab == 'booking') ? 'rgba(255,255,255,0.3)' : '#fff3e0'; ?>; 
                              padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 5px;
                              color: <?php echo ($active_tab == 'booking') ? 'white' : '#ff9800'; ?>;">
                            <?php echo $count_booking; ?>
                        </span>
                    </button>
                    <button type="button" onclick="switchTabOperasi('selesai')" id="tab-btn-selesai"
                            class="btn waves-effect" 
                            style="border-radius: 20px; padding: 8px 20px; font-weight: 600; font-size: 13px; border: 2px solid #4caf50; transition: all 0.3s ease;
                            <?php echo ($active_tab == 'selesai') ? 'background: #4caf50; color: white;' : 'background: white; color: #4caf50;'; ?>">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">check_circle</i>
                        Selesai Operasi
                        <span id="badge-count-selesai" style="background: <?php echo ($active_tab == 'selesai') ? 'rgba(255,255,255,0.3)' : '#e8f5e9'; ?>; 
                              padding: 2px 8px; border-radius: 10px; font-size: 11px; margin-left: 5px;
                              color: <?php echo ($active_tab == 'selesai') ? 'white' : '#4caf50'; ?>;">
                            <?php echo $count_operasi; ?>
                        </span>
                    </button>
                </div>

                <!-- ========== FILTER TANGGAL (shared untuk kedua tab) ========== -->
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 15px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="margin: 0; font-weight: 600; color: #666; white-space: nowrap;">
                            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">date_range</i>
                            Dari:
                        </label>
                        <input type="date" id="tgl_dari" value="<?php echo $tgl_operasi_dari; ?>" 
                               class="form-control" style="width: 160px; border-radius: 8px; border: 2px solid #dee2e6; padding: 8px 12px;">
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="margin: 0; font-weight: 600; color: #666; white-space: nowrap;">Sampai:</label>
                        <input type="date" id="tgl_sampai" value="<?php echo $tgl_operasi_sampai; ?>" 
                               class="form-control" style="width: 160px; border-radius: 8px; border: 2px solid #dee2e6; padding: 8px 12px;">
                    </div>
                    <button type="button" onclick="filterTanggalOperasi()" class="btn waves-effect" 
                            style="background: #9c27b0; color: white; border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">search</i>
                        Tampilkan
                    </button>
                    
                    <!-- Quick Filter Buttons -->
                    <div style="display: flex; gap: 5px; margin-left: 10px;">
                        <button type="button" onclick="setQuickFilterOperasi('hari_ini')" class="btn btn-xs waves-effect" 
                                style="background: #e3f2fd; color: #1976d2; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                            Hari Ini
                        </button>
                        <button type="button" onclick="setQuickFilterOperasi('kemarin')" class="btn btn-xs waves-effect" 
                                style="background: #e3f2fd; color: #1976d2; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                            Kemarin
                        </button>
                        <button type="button" onclick="setQuickFilterOperasi('minggu_ini')" class="btn btn-xs waves-effect" 
                                style="background: #e3f2fd; color: #1976d2; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                            Minggu Ini
                        </button>
                        <button type="button" onclick="setQuickFilterOperasi('bulan_ini')" class="btn btn-xs waves-effect" 
                                style="background: #e3f2fd; color: #1976d2; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                            Bulan Ini
                        </button>
                    </div>
                </div>
                <p style="margin-top: 10px; margin-bottom: 0; color: #666; font-size: 12px;">
                    <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
                    Menampilkan data operasi periode: <strong><?php echo date('d/m/Y', strtotime($tgl_operasi_dari)); ?></strong> s/d <strong><?php echo date('d/m/Y', strtotime($tgl_operasi_sampai)); ?></strong>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- TAB 1: JADWAL OPERASI (Booking - Belum Operasi) -->
<!-- ================================================================ -->
<div id="tab-content-booking" style="<?php echo ($active_tab == 'booking') ? '' : 'display:none;'; ?>">
<div class="row clearfix">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <?php
        // Query booking operasi berdasarkan periode, status Menunggu
        $queryBooking = bukaquery_safe("SELECT 
                                    bo.no_rawat,
                                    bo.kode_paket,
                                    bo.tanggal,
                                    bo.jam_mulai,
                                    bo.jam_selesai,
                                    bo.status,
                                    bo.kd_dokter,
                                    bo.kd_ruang_ok,
                                    pk.nm_perawatan as nama_operasi,
                                    rp.no_rkm_medis,
                                    rp.umurdaftar,
                                    rp.sttsumur,
                                    rp.status_lanjut,
                                    p.nm_pasien,
                                    p.jk,
                                    d.nm_dokter,
                                    pj.png_jawab
                                 FROM booking_operasi bo
                                 INNER JOIN reg_periksa rp ON bo.no_rawat = rp.no_rawat
                                 INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                 LEFT JOIN paket_operasi pk ON bo.kode_paket = pk.kode_paket
                                 LEFT JOIN dokter d ON bo.kd_dokter = d.kd_dokter
                                 LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                                 WHERE bo.tanggal BETWEEN '$tgl_operasi_dari' AND '$tgl_operasi_sampai'
                                 AND bo.status = 'Menunggu'
                                 AND rp.status_bayar = 'Belum Bayar'
                                 $filter_booking_dokter
                                 ORDER BY bo.tanggal ASC, bo.jam_mulai ASC");
        
        $jumlah_booking = mysqli_num_rows($queryBooking);
        
        if($jumlah_booking == 0) {
            echo '<div class="alert alert-info" style="text-align: center; padding: 40px; background: #fff3e0; border-radius: 15px;">
                    <i class="material-icons" style="font-size: 64px; color: #ff9800;">event_busy</i>
                    <h4 style="margin-top: 15px; color: #e65100;">Tidak ada jadwal operasi</h4>
                    <p style="color: #999;">Belum ada booking operasi pada periode ini</p>
                  </div>';
        } else {
            // Header
            echo '<div style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 15px 20px; border-radius: 10px 10px 0 0; margin-bottom: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <h3 style="margin: 0; font-size: 18px; font-weight: 600;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 22px;">schedule</i>
                            JADWAL OPERASI
                            <span style="background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 10px; font-size: 12px; margin-left: 10px;">'.$jumlah_booking.' Pasien</span>
                        </h3>
                        <div style="position: relative; min-width: 200px; max-width: 280px;">
                            <i class="material-icons" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #999; font-size: 18px; pointer-events: none;">search</i>
                            <input type="text" id="searchJadwalOperasi" placeholder="Cari nama / No. RM..." 
                                   style="width: 100%; padding: 8px 12px 8px 36px; border-radius: 20px; border: none; font-size: 13px; outline: none; color: #333;">
                        </div>
                    </div>
                  </div>';
            echo '<div style="background: white; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 10px;">';
            
            $no_urut = 1;
            while($rs = mysqli_fetch_array($queryBooking)) {
                // Enkripsi parameter
                $encrypted_norawat = urlencode(encrypt_decrypt($rs["no_rawat"], "e"));
                $encrypted_norm = urlencode(encrypt_decrypt($rs["no_rkm_medis"], "e"));

                // Avatar image based on gender
                $avatar_img = ($rs["jk"] == "L") ? "images/male.png" : "images/female.png";
                
                // Format umur
                $umur = $rs["umurdaftar"] . " " . $rs["sttsumur"];
                
                // Jam operasi
                $jam_mulai = $rs["jam_mulai"] ? date('H:i', strtotime($rs["jam_mulai"])) : '-';
                $jam_selesai = $rs["jam_selesai"] ? date('H:i', strtotime($rs["jam_selesai"])) : '-';
                
                // Tanggal booking
                $tgl_booking = date('d/m/Y', strtotime($rs["tanggal"]));

                // Badge pembayaran
                $png_jawab = $rs["png_jawab"] ?? '-';
                if(stripos($png_jawab, 'BPJS') !== false || stripos($png_jawab, 'JKN') !== false) {
                    $pj_color = "#4caf50";
                } elseif(stripos($png_jawab, 'UMUM') !== false) {
                    $pj_color = "#2196f3";
                } else {
                    $pj_color = "#9e9e9e";
                }

                // Badge status lanjut
                $status_lanjut = $rs["status_lanjut"] ?? '-';
                if($status_lanjut == 'Ralan') {
                    $sl_color = "#2196f3";
                    $sl_label = "Rawat Jalan";
                } elseif($status_lanjut == 'Ranap') {
                    $sl_color = "#9c27b0";
                    $sl_label = "Rawat Inap";
                } else {
                    $sl_color = "#9e9e9e";
                    $sl_label = $status_lanjut;
                }

                $nama_operasi = $rs["nama_operasi"] ?: '-';
                ?>
                
                <div class="patient-card" style="background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid #ff9800; transition: all 0.3s ease; position: relative; overflow: visible;">
                    <div class="row">
                        <div class="col-sm-4">
                            <div style="display: flex; align-items: flex-start;">
                                <!-- Nomor Urut -->
                                <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; margin-right: 12px; flex-shrink: 0; background: #e0e0e0;">
                                    <img src="<?php echo $avatar_img; ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px;">
                                        <div>
                                            <h4 style="margin: 0; font-size: 15px; font-weight: 600; color: #333;">
                                                <?php echo strtoupper($rs["nm_pasien"]); ?>
                                            </h4>
                                            <h4 style="margin: 5px 0 0 0; font-size: 14px; font-weight: 600; color: #ff9800;">
                                                <?php echo $nama_operasi; ?>
                                            </h4>
                                        </div>
                                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                                            <!-- Button Aksi -->
                                            <div class="dropdown-pasien" style="display: inline-block; position: relative;">
                                                <button class="btn btn-primary btn-xs dropdown-pasien-toggle waves-effect" 
                                                        type="button" 
                                                        style="background: #ff9800; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                                    Aksi <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-pasien-menu">
                                                    <li><a href="index.php?act=Pemeriksaanriwayat&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Riwayat Perawatan</a></li>
                                                    <?php if(cekAksesMenu('penilaian_pre_induksi')): ?>
                                                    <li><a href="index.php?act=Penilaianpreinduksi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Penilaian Pre Induksi</a></li>
                                                    <?php endif; ?>
                                                    <?php if(cekAksesMenu('penilaian_pre_operasi')): ?>
                                                    <li><a href="index.php?act=Penilaianpreoperasi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Penilaian Pre Operasi</a></li>
                                                    <?php endif; ?>
                                                    <?php if(cekAksesMenu('checklist_pre_operasi')): ?>
                                                    <li><a href="index.php?act=Checklistpreoperasi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Checklist Pre Operasi</a></li>
                                                    <?php endif; ?>
                                                    <?php if(cekAksesMenu('penilaian_pre_anestesi')): ?>
                                                    <li><a href="index.php?act=Penilaianpreanestesi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Penilaian Pre Anestesi</a></li>
                                                    <?php endif; ?>      
                                                    <?php if(cekAksesMenu('signin_sebelum_anestesi')): ?>
                                                    <li><a href="index.php?act=Signinsebelumanestesi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Sign In Sebelum Anestesi</a></li>
                                                    <?php endif; ?>    
                                                    <?php if(cekAksesMenu('checklist_kesiapan_anestesi')): ?>
                                                    <li><a href="index.php?act=Checklistkesiapananestesi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Checklist Kesiapan Anestesi</a></li>
                                                    <?php endif; ?>      
                                                    <?php if(cekAksesMenu('timeout_sebelum_insisi')): ?>
                                                    <li><a href="index.php?act=Timeoutsebeluminsisi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Timeout Sebelum Insisi</a></li>
                                                    <?php endif; ?>
                                                    <li style="border-top: 1px solid #eee;"><a href="index.php?act=Penandaanoperasi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" style="color: #e53e3e; font-weight: 600;"><i class="material-icons" style="font-size:14px; vertical-align:middle; margin-right:3px;">location_on</i>Penandaan Operasi</a></li>
                                                </ul>                                      
                                            </div>
                                            <!-- Tombol Lihat Detail RME -->
                                            <button class="btn btn-info btn-xs btn-lihat-operasi waves-effect" 
                                                    type="button" 
                                                    data-norawat="<?php echo $encrypted_norawat; ?>" 
                                                    data-norm="<?php echo $encrypted_norm; ?>"
                                                    style="background: #667eea; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600; color: white;">
                                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</i> Lihat
                                            </button>
                                        </div>
                                    </div>
                                    <div style="font-size: 12px; color: #666; margin-top: 3px;">
                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">badge</i>
                                        <strong>No. RM:</strong> <?php echo $rs["no_rkm_medis"]; ?>
                                    </div>
                                    <div style="font-size: 11px; color: #999;">
                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">folder</i>
                                        <?php echo $rs["no_rawat"]; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-sm-4">
                            <div style="font-size: 13px;">
                                <div style="margin-bottom: 6px;">
                                    <i class="material-icons" style="font-size: 14px; vertical-align: middle; color: #666;">access_time</i>
                                    <strong style="color: #666;"><?php echo $umur; ?></strong>
                                    <span style="background: <?php echo $pj_color; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; margin-left: 5px;">
                                        <?php echo strtoupper($png_jawab); ?>
                                    </span>
                                </div>
                                <div style="margin-bottom: 6px;">
                                    <span style="background: <?php echo $sl_color; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">
                                        <?php echo $sl_label; ?>
                                    </span>
                                </div>
                                <div style="color: #e65100; font-size: 11px; font-weight: 600; margin-bottom: 6px;">
                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">event</i>
                                    Tgl: <?php echo $tgl_booking; ?>
                                </div>
                                <div style="color: #ff9800; font-size: 12px; font-weight: 600; margin-bottom: 6px;">
                                    <i class="material-icons" style="font-size: 14px; vertical-align: middle;">schedule</i>
                                    Jam: <?php echo $jam_mulai; ?> - <?php echo $jam_selesai; ?>
                                </div>
                                <div>
                                    <span style="background: #fff3e0; color: #e65100; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">hourglass_empty</i>
                                        <?php echo $rs["status"]; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-sm-4">
                            <!-- Dokter Penanggung Jawab -->
                            <?php if($rs["nm_dokter"]): ?>
                            <div style="margin-bottom: 8px;">
                                <span style="background: #667eea; color: white; padding: 3px 8px; border-radius: 8px; font-size: 8px; font-weight: 600; margin-right: 5px;">DPJP</span>
                                <strong style="font-size: 12px; color: #666;"><?php echo $rs["nm_dokter"]; ?></strong>
                            </div>
                            <?php else: ?>
                            <div style="margin-bottom: 8px;">
                                <span style="background: #9e9e9e; color: white; padding: 3px 8px; border-radius: 8px; font-size: 8px; font-weight: 600;">DPJP: -</span>
                            </div>
                            <?php endif; ?>

                            <!-- Ruang OK -->
                            <?php if($rs["kd_ruang_ok"]): ?>
                            <div style="margin-bottom: 8px;">
                                <span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">meeting_room</i>
                                    Ruang: <?php echo $rs["kd_ruang_ok"]; ?>
                                </span>
                            </div>
                            <?php endif; ?>

                            <!-- Badge Status RME Operasi -->
                            <?php
                            $no_rawat_rme = $rs["no_rawat"];
                            $today_rme = date('Y-m-d');
                            $rme_tables = [
                                'penilaian_pre_induksi'       => 'Pre Induksi',
                                'checklist_pre_operasi'       => 'Checklist Pre Op',
                                'penilaian_pre_operasi'       => 'Pre Operasi',
                                'penilaian_pre_anestesi'      => 'Pre Anestesi',
                                'signin_sebelum_anestesi'     => 'Sign In',
                                'checklist_kesiapan_anestesi' => 'Kesiapan Anestesi',
                                'timeout_sebelum_insisi'      => 'Time Out',
                            ];
                            $rme_found = [];
                            foreach($rme_tables as $tbl => $label) {
                                $cekRme = bukaquery_safe("SELECT 1 FROM {$tbl} WHERE no_rawat = '{$no_rawat_rme}' AND DATE(tanggal) = '{$today_rme}' LIMIT 1");
                                if(mysqli_num_rows($cekRme) > 0) {
                                    $rme_found[] = $label;
                                }
                            }
                            if(!empty($rme_found)):
                            ?>
                            <div style="margin-top: 2px; display: flex; flex-wrap: wrap; gap: 3px;">
                                <?php foreach($rme_found as $rme_label): ?>
                                <span style="background: #4caf50; color: white; padding: 2px 7px; border-radius: 8px; font-size: 9px; font-weight: 600; display: inline-flex; align-items: center; gap: 2px;">
                                    <i class="material-icons" style="font-size: 10px;">check_circle</i>
                                    <?php echo $rme_label; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Container Detail RME Operasi (AJAX Load) -->
                <div class="detail-operasi-wrapper" id="detail-op-<?php echo md5($rs['no_rawat']); ?>" style="display: none; margin-bottom: 10px;"></div>
                
                <?php
                $no_urut++;
            }
            echo '</div>';
        }
        ?>
    </div>
</div>
</div>

<!-- ================================================================ -->
<!-- TAB 2: SELESAI OPERASI -->
<!-- ================================================================ -->
<div id="tab-content-selesai" style="<?php echo ($active_tab == 'selesai') ? '' : 'display:none;'; ?>">
<div class="row clearfix" id="operasiContainer">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <?php
        // Query data operasi
        $queryOperasi = bukaquery_safe("SELECT 
                                    o.no_rawat,
                                    o.tgl_operasi,
                                    o.jenis_anasthesi,
                                    o.kategori,
                                    o.operator1,
                                    o.operator2,
                                    o.operator3,
                                    o.dokter_anestesi,
                                    o.kode_paket,
                                    pk.nm_perawatan as nama_operasi,
                                    rp.no_rkm_medis,
                                    rp.umurdaftar,
                                    rp.sttsumur,
                                    p.nm_pasien,
                                    p.jk,
                                    d1.nm_dokter as nm_operator1,
                                    d2.nm_dokter as nm_operator2,
                                    d3.nm_dokter as nm_operator3,
                                    da.nm_dokter as nm_dokter_anestesi,
                                    pj.png_jawab
                                 FROM operasi o
                                 INNER JOIN reg_periksa rp ON o.no_rawat = rp.no_rawat
                                 INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                 LEFT JOIN paket_operasi pk ON o.kode_paket = pk.kode_paket
                                 LEFT JOIN dokter d1 ON o.operator1 = d1.kd_dokter
                                 LEFT JOIN dokter d2 ON o.operator2 = d2.kd_dokter
                                 LEFT JOIN dokter d3 ON o.operator3 = d3.kd_dokter
                                 LEFT JOIN dokter da ON o.dokter_anestesi = da.kd_dokter
                                 LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                                 WHERE DATE(o.tgl_operasi) BETWEEN '$tgl_operasi_dari' AND '$tgl_operasi_sampai'
                                 AND (o.operator1 = '$kd_dokter_login' OR o.operator2 = '$kd_dokter_login' OR o.operator3 = '$kd_dokter_login' OR o.dokter_anestesi = '$kd_dokter_login')
                                 ORDER BY o.tgl_operasi DESC");
        
        $jumlah_operasi = mysqli_num_rows($queryOperasi);
        
        if($jumlah_operasi == 0) {
            echo '<div class="alert alert-info" style="text-align: center; padding: 40px; background: #e8f5e9; border-radius: 15px;">
                    <i class="material-icons" style="font-size: 64px; color: #4caf50;">check_circle</i>
                    <h4 style="margin-top: 15px;">Tidak ada data operasi</h4>
                    <p style="color: #999;">Belum ada data operasi pada periode ini</p>
                  </div>';
        } else {
            // Header
            echo '<div style="background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%); color: white; padding: 15px 20px; border-radius: 10px 10px 0 0; margin-bottom: 0;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <h3 style="margin: 0; font-size: 18px; font-weight: 600;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 22px;">check_circle</i>
                            SELESAI OPERASI
                            <span style="background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 10px; font-size: 12px; margin-left: 10px;">'.$jumlah_operasi.' Data</span>
                        </h3>
                        <div style="position: relative; min-width: 200px; max-width: 280px;">
                            <i class="material-icons" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #999; font-size: 18px; pointer-events: none;">search</i>
                            <input type="text" id="searchSelesaiOperasi" placeholder="Cari nama / No. RM..." 
                                   style="width: 100%; padding: 8px 12px 8px 36px; border-radius: 20px; border: none; font-size: 13px; outline: none; color: #333;">
                        </div>
                    </div>
                  </div>';
            echo '<div style="background: white; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 10px;">';
            
            while($rs = mysqli_fetch_array($queryOperasi)) {
                // Enkripsi parameter
                $encrypted_norawat = urlencode(encrypt_decrypt($rs["no_rawat"], "e"));
                $encrypted_norm = urlencode(encrypt_decrypt($rs["no_rkm_medis"], "e"));
                
                // Warna badge berdasarkan kategori
                $kategori = $rs["kategori"];
                switch($kategori) {
                    case 'Kecil':
                        $badge_color = "#4caf50";
                        break;
                    case 'Sedang':
                        $badge_color = "#2196f3";
                        break;
                    case 'Besar':
                        $badge_color = "#ff9800";
                        break;
                    case 'Khusus':
                        $badge_color = "#f44336";
                        break;
                    default:
                        $badge_color = "#9e9e9e";
                }
                
                // Avatar image based on gender
                $avatar_img = ($rs["jk"] == "L") ? "images/male.png" : "images/female.png";
                
                // Format umur
                $umur = $rs["umurdaftar"] . " " . $rs["sttsumur"];
                
                // Format tanggal operasi
                $tgl_operasi = date('d/m/Y H:i', strtotime($rs["tgl_operasi"]));
                
                // Badge pembayaran
                $png_jawab = $rs["png_jawab"] ?? '-';
                if(stripos($png_jawab, 'BPJS') !== false || stripos($png_jawab, 'JKN') !== false) {
                    $pj_color = "#4caf50";
                } elseif(stripos($png_jawab, 'UMUM') !== false) {
                    $pj_color = "#2196f3";
                } else {
                    $pj_color = "#9e9e9e";
                }
                ?>
                
                <div class="patient-card" style="background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid <?php echo $badge_color; ?>; transition: all 0.3s ease; position: relative; overflow: visible;">
                    <div class="row">
                        <div class="col-sm-4">
                            <div style="display: flex; align-items: flex-start;">
                                <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; margin-right: 12px; flex-shrink: 0; background: #e0e0e0;">
                                    <img src="<?php echo $avatar_img; ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px;">
                                        <div>
                                            <h4 style="margin: 0; font-size: 15px; font-weight: 600; color: #333;">
                                                <?php echo strtoupper($rs["nm_pasien"]); ?>
                                            </h4>
                                            <?php 
                                            $nama_operasi = $rs["nama_operasi"] ?: '-';
                                            ?>
                                            <h4 style="margin: 5px 0 0 0; font-size: 15px; font-weight: 600; color: #9c27b0;">
                                                <?php echo $nama_operasi; ?>
                                            </h4>
                                        </div>
                                        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                                            <!-- Button Aksi -->
                                            <div class="dropdown-pasien" style="display: inline-block; position: relative;">
                                                <button class="btn btn-primary btn-xs dropdown-pasien-toggle waves-effect" 
                                                        type="button" 
                                                        style="background: #9c27b0; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                                    Aksi <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-pasien-menu">
                                                    <li><a href="index.php?act=Laporanoperasi&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>"><i class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 5px;">edit</i>Ubah Laporan Operasi</a></li>
                                                    <li style="border-top: 1px solid #eee;"><a href="index.php?act=Signoutsebelummenutupluka&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Sign-Out Sebeum menutup Luka</a></li>
                                                    <li style="border-top: 1px solid #eee;"><a href="index.php?act=Catatananestesisedasi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Catatan Anestesi Sedasi</a></li>
                                                    <li style="border-top: 1px solid #eee;"><a href="index.php?act=Skoraldrettepascaanestesi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Skor Aldrette Pasca Anestesi</a></li>
                                                    <li style="border-top: 1px solid #eee;"><a href="index.php?act=Skorstewardpascaanestesi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Skor Steward Pasca Anestesi</a></li>
                                                    <li style="border-top: 1px solid #eee;"><a href="index.php?act=Skorbromagepascaanestesi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Skor Bromage Pasca Anestesi</a></li>
                                                    <li style="border-top: 1px solid #eee;"><a href="index.php?act=Catatanpengkajianpaskaoperasi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Catatan Pengkajian Paska Operasi</a></li>                                              
                                                    <li><a href="index.php?act=Pemeriksaanriwayat&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Riwayat Perawatan</a></li>
                                                </ul>                                      
                                            </div>
                                            <!-- Tombol Lihat Detail Operasi Sudah -->
                                            <button class="btn btn-info btn-xs btn-lihat-operasi-sudah waves-effect" 
                                                    type="button" 
                                                    data-norawat="<?php echo $encrypted_norawat; ?>" 
                                                    data-norm="<?php echo $encrypted_norm; ?>"
                                                    style="background: #667eea; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600; color: white;">
                                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</i> Lihat
                                            </button>
                                        </div>
                                    </div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 3px;">
                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">badge</i>
                                        <strong>No. RM:</strong> <?php echo $rs["no_rkm_medis"]; ?>
                                    </div>
                                    <div style="font-size: 11px; color: #999;">
                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">folder</i>
                                        <?php echo $rs["no_rawat"]; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-sm-4">
                            <div style="font-size: 13px;">
                                <div style="margin-bottom: 6px;">
                                    <i class="material-icons" style="font-size: 14px; vertical-align: middle; color: #666;">access_time</i>
                                    <strong style="color: #666;"><?php echo $umur; ?></strong>
                                    <span style="background: <?php echo $pj_color; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; margin-left: 5px;">
                                        <?php echo strtoupper($png_jawab); ?>
                                    </span>
                                </div>
                                <div style="color: #9c27b0; font-size: 11px; font-weight: 600; margin-bottom: 6px;">
                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">event</i>
                                    Tgl Operasi: <?php echo $tgl_operasi; ?>
                                </div>
                                <div style="margin-bottom: 6px;">
                                    <span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">healing</i>
                                        <?php echo $rs["jenis_anasthesi"] ?: '-'; ?>
                                    </span>
                                </div>
                                <!-- Badge Kategori -->
                                <div>
                                    <span style="background: <?php echo $badge_color; ?>; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">local_hospital</i>
                                        <?php echo $kategori ?: '-'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-sm-4">
                            <!-- Operator 1 -->
                            <?php if($rs["nm_operator1"]): ?>
                            <div style="margin-bottom: 5px;">
                                <span style="background: #667eea; color: white; padding: 3px 8px; border-radius: 8px; font-size: 8px; font-weight: 600; margin-right: 5px;">OP1</span>
                                <strong style="font-size: 12px; color: #666;"><?php echo $rs["nm_operator1"]; ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Operator 2 -->
                            <?php if($rs["nm_operator2"]): ?>
                            <div style="margin-bottom: 5px;">
                                <span style="background: #26c6da; color: white; padding: 3px 8px; border-radius: 8px; font-size: 8px; font-weight: 600; margin-right: 5px;">OP2</span>
                                <strong style="font-size: 12px; color: #666;"><?php echo $rs["nm_operator2"]; ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Operator 3 -->
                            <?php if($rs["nm_operator3"]): ?>
                            <div style="margin-bottom: 5px;">
                                <span style="background: #ff9800; color: white; padding: 3px 8px; border-radius: 8px; font-size: 8px; font-weight: 600; margin-right: 5px;">OP3</span>
                                <strong style="font-size: 12px; color: #666;"><?php echo $rs["nm_operator3"]; ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Dokter Anestesi -->
                            <?php if($rs["nm_dokter_anestesi"]): ?>
                            <div style="margin-bottom: 5px;">
                                <span style="background: #e91e63; color: white; padding: 3px 8px; border-radius: 8px; font-size: 8px; font-weight: 600; margin-right: 5px;">ANES</span>
                                <strong style="font-size: 12px; color: #666;"><?php echo $rs["nm_dokter_anestesi"]; ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(!$rs["nm_operator1"] && !$rs["nm_operator2"] && !$rs["nm_operator3"] && !$rs["nm_dokter_anestesi"]): ?>
                            <div>
                                <span style="background: #f44336; color: white; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 600;">
                                    Operator: -
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Container Detail Operasi Sudah (AJAX Load) -->
                <div class="detail-operasi-sudah-wrapper" id="detail-op-sudah-<?php echo md5($rs['no_rawat']); ?>" style="display: none; margin-bottom: 10px;"></div>
                
                <?php
            }
            echo '</div>';
        }
        ?>
    </div>
</div>
</div>

<!-- CSS untuk dropdown & submenu -->
<style>
.dropdown-pasien {
    display: inline-block;
    position: relative;
}

.dropdown-pasien-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    min-width: 180px;
    z-index: 1000;
    padding: 8px 0;
    margin-top: 5px;
    list-style: none;
}

.dropdown-pasien-menu li a {
    display: block;
    padding: 10px 15px;
    color: #333;
    text-decoration: none;
    font-size: 12px;
    transition: all 0.2s;
}

.dropdown-pasien-menu li a:hover {
    background: #f5f5f5;
    color: #667eea;
}

.dropdown-pasien.open .dropdown-pasien-menu {
    display: block;
}

.patient-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Submenu styles */
.has-submenu {
    position: relative;
}

.has-submenu .submenu-trigger::after {
    content: '✓';
    float: right;
    color: #4caf50;
}

.dropdown-submenu {
    display: none;
    position: absolute;
    left: 100%;
    top: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    min-width: 200px;
    padding: 8px 0;
    list-style: none;
    z-index: 1001;
}

.has-submenu:hover .dropdown-submenu {
    display: block;
}
</style>

<script>
// ========== TAB SWITCHING ==========
function switchTabOperasi(tab) {
    // Hide all tab contents
    document.getElementById('tab-content-booking').style.display = 'none';
    document.getElementById('tab-content-selesai').style.display = 'none';
    
    // Reset button styles
    var btnBooking = document.getElementById('tab-btn-booking');
    var btnSelesai = document.getElementById('tab-btn-selesai');
    
    btnBooking.style.background = 'white';
    btnBooking.style.color = '#ff9800';
    btnSelesai.style.background = 'white';
    btnSelesai.style.color = '#4caf50';
    
    // Show selected tab
    if(tab === 'booking') {
        document.getElementById('tab-content-booking').style.display = '';
        btnBooking.style.background = '#ff9800';
        btnBooking.style.color = 'white';
    } else {
        document.getElementById('tab-content-selesai').style.display = '';
        btnSelesai.style.background = '#4caf50';
        btnSelesai.style.color = 'white';
    }
    
    // Update URL tanpa reload
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('tab', tab);
    window.history.replaceState({}, '', currentUrl.toString());
}

// ========== DROPDOWN TOGGLE ==========
document.addEventListener('click', function(e) {
    document.querySelectorAll('.dropdown-pasien').forEach(function(dropdown) {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });
    
    if (e.target.classList.contains('dropdown-pasien-toggle') || e.target.closest('.dropdown-pasien-toggle')) {
        e.preventDefault();
        const dropdown = e.target.closest('.dropdown-pasien');
        dropdown.classList.toggle('open');
    }
});

// ========== FILTER TANGGAL ==========
function filterTanggalOperasi() {
    const tglDari = document.getElementById('tgl_dari').value;
    const tglSampai = document.getElementById('tgl_sampai').value;
    
    if(!tglDari || !tglSampai) {
        Swal.fire('Error', 'Pilih tanggal dari dan sampai', 'error');
        return;
    }
    
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('tgl_dari', tglDari);
    currentUrl.searchParams.set('tgl_sampai', tglSampai);
    // Pertahankan tab aktif saat ini
    const activeTab = document.getElementById('tab-content-booking').style.display !== 'none' ? 'booking' : 'selesai';
    currentUrl.searchParams.set('tab', activeTab);
    
    window.location.href = currentUrl.toString();
}

// ========== QUICK FILTER ==========
function setQuickFilterOperasi(type) {
    const today = new Date();
    let tglDari, tglSampai;
    
    switch(type) {
        case 'hari_ini':
            tglDari = tglSampai = formatDate(today);
            break;
        case 'kemarin':
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            tglDari = tglSampai = formatDate(yesterday);
            break;
        case 'minggu_ini':
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay());
            tglDari = formatDate(startOfWeek);
            tglSampai = formatDate(today);
            break;
        case 'bulan_ini':
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            tglDari = formatDate(startOfMonth);
            tglSampai = formatDate(today);
            break;
    }
    
    document.getElementById('tgl_dari').value = tglDari;
    document.getElementById('tgl_sampai').value = tglSampai;
    filterTanggalOperasi();
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// ========== LIHAT DETAIL RME OPERASI ==========
function loadDetailOperasi(button) {
    var noRawat = button.getAttribute('data-norawat');
    var noRm = button.getAttribute('data-norm');
    
    var patientCard = button.closest('.patient-card');
    var detailWrapper = patientCard.nextElementSibling;
    
    if(!detailWrapper || !detailWrapper.classList.contains('detail-operasi-wrapper')) {
        console.error('Detail operasi wrapper not found');
        return;
    }
    
    // Toggle jika sudah terbuka
    if(patientCard.classList.contains('detail-op-open')) {
        detailWrapper.style.transition = 'max-height 0.3s ease, opacity 0.3s ease';
        detailWrapper.style.maxHeight = '0';
        detailWrapper.style.opacity = '0';
        setTimeout(function() {
            detailWrapper.style.display = 'none';
            patientCard.classList.remove('detail-op-open');
        }, 300);
        button.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</i> Lihat';
        button.style.background = '#667eea';
        return;
    }
    
    // Tutup semua detail yang terbuka
    document.querySelectorAll('.detail-operasi-wrapper').forEach(function(el) {
        el.style.display = 'none';
        el.style.maxHeight = '0';
        el.style.opacity = '0';
    });
    document.querySelectorAll('.patient-card').forEach(function(el) {
        el.classList.remove('detail-op-open');
    });
    document.querySelectorAll('.btn-lihat-operasi').forEach(function(el) {
        el.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</i> Lihat';
        el.style.background = '#667eea';
    });
    
    // Loading state
    button.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">hourglass_empty</i> Loading...';
    button.style.background = '#9e9e9e';
    button.disabled = true;
    
    // AJAX fetch
    fetch('pages/detail_operasi.php?no_rawat=' + encodeURIComponent(noRawat) + '&no_rm=' + encodeURIComponent(noRm))
    .then(function(response) { return response.text(); })
    .then(function(html) {
        detailWrapper.innerHTML = html;
        detailWrapper.style.display = 'block';
        detailWrapper.style.maxHeight = '0';
        detailWrapper.style.opacity = '0';
        detailWrapper.style.overflow = 'hidden';
        detailWrapper.style.transition = 'max-height 0.4s ease, opacity 0.3s ease';
        
        requestAnimationFrame(function() {
            detailWrapper.style.maxHeight = detailWrapper.scrollHeight + 'px';
            detailWrapper.style.opacity = '1';
        });
        
        patientCard.classList.add('detail-op-open');
        button.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility_off</i> Tutup';
        button.style.background = '#f44336';
        button.disabled = false;
        
        // Remove max-height setelah animasi selesai
        setTimeout(function() {
            detailWrapper.style.maxHeight = 'none';
        }, 500);
    })
    .catch(function(error) {
        detailWrapper.innerHTML = '<div style="padding: 15px; text-align: center; color: #f44336;"><i class="material-icons">error</i> Gagal memuat data: ' + error + '</div>';
        detailWrapper.style.display = 'block';
        patientCard.classList.add('detail-op-open');
        
        button.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</i> Lihat';
        button.style.background = '#667eea';
        button.disabled = false;
    });
}

// Event delegation untuk tombol Lihat (Jadwal)
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-lihat-operasi');
    if(btn) {
        e.preventDefault();
        e.stopPropagation();
        loadDetailOperasi(btn);
    }
});

// ========== LIHAT DETAIL OPERASI SUDAH ==========
function loadDetailOperasiSudah(button) {
    var noRawat = button.getAttribute('data-norawat');
    var noRm = button.getAttribute('data-norm');
    
    var patientCard = button.closest('.patient-card');
    var detailWrapper = patientCard.nextElementSibling;
    
    if(!detailWrapper || !detailWrapper.classList.contains('detail-operasi-sudah-wrapper')) {
        console.error('Detail operasi sudah wrapper not found');
        return;
    }
    
    // Toggle jika sudah terbuka
    if(patientCard.classList.contains('detail-op-sudah-open')) {
        detailWrapper.style.transition = 'max-height 0.3s ease, opacity 0.3s ease';
        detailWrapper.style.maxHeight = '0';
        detailWrapper.style.opacity = '0';
        setTimeout(function() {
            detailWrapper.style.display = 'none';
            patientCard.classList.remove('detail-op-sudah-open');
        }, 300);
        button.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</i> Lihat';
        button.style.background = '#667eea';
        return;
    }
    
    // Tutup semua detail sudah yang terbuka
    document.querySelectorAll('.detail-operasi-sudah-wrapper').forEach(function(el) {
        el.style.display = 'none';
        el.style.maxHeight = '0';
        el.style.opacity = '0';
    });
    document.querySelectorAll('.patient-card').forEach(function(el) {
        el.classList.remove('detail-op-sudah-open');
    });
    document.querySelectorAll('.btn-lihat-operasi-sudah').forEach(function(el) {
        el.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</i> Lihat';
        el.style.background = '#667eea';
    });
    
    // Loading state
    button.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">hourglass_empty</i> Loading...';
    button.style.background = '#9e9e9e';
    button.disabled = true;
    
    // AJAX fetch
    fetch('pages/detail_operasi_sudah.php?no_rawat=' + encodeURIComponent(noRawat) + '&no_rm=' + encodeURIComponent(noRm))
    .then(function(response) { return response.text(); })
    .then(function(html) {
        detailWrapper.innerHTML = html;
        detailWrapper.style.display = 'block';
        detailWrapper.style.maxHeight = '0';
        detailWrapper.style.opacity = '0';
        detailWrapper.style.overflow = 'hidden';
        detailWrapper.style.transition = 'max-height 0.4s ease, opacity 0.3s ease';
        
        requestAnimationFrame(function() {
            detailWrapper.style.maxHeight = detailWrapper.scrollHeight + 'px';
            detailWrapper.style.opacity = '1';
        });
        
        patientCard.classList.add('detail-op-sudah-open');
        button.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility_off</i> Tutup';
        button.style.background = '#f44336';
        button.disabled = false;
        
        setTimeout(function() {
            detailWrapper.style.maxHeight = 'none';
        }, 500);
    })
    .catch(function(error) {
        detailWrapper.innerHTML = '<div style="padding: 15px; text-align: center; color: #f44336;"><i class="material-icons">error</i> Gagal memuat data: ' + error + '</div>';
        detailWrapper.style.display = 'block';
        patientCard.classList.add('detail-op-sudah-open');
        
        button.innerHTML = '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">visibility</i> Lihat';
        button.style.background = '#667eea';
        button.disabled = false;
    });
}

// Event delegation untuk tombol Lihat (Selesai Operasi)
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-lihat-operasi-sudah');
    if(btn) {
        e.preventDefault();
        e.stopPropagation();
        loadDetailOperasiSudah(btn);
    }
});

// ========== SEARCH PASIEN OPERASI - Client-side filter ==========
(function() {
    'use strict';
    function bindSearch(inputId, containerId) {
        var input = document.getElementById(inputId);
        if(!input) return;
        input.addEventListener('input', function() {
            var keyword = this.value.toLowerCase().trim();
            var container = document.getElementById(containerId);
            if(!container) return;
            var cards = container.querySelectorAll('.patient-card');
            cards.forEach(function(card) {
                // Juga sembunyikan detail wrapper yang mengikuti card
                var detail = card.nextElementSibling;
                var text = card.textContent.toLowerCase();
                if(keyword === '' || text.indexOf(keyword) > -1) {
                    card.style.display = '';
                    if(detail && (detail.classList.contains('detail-operasi-wrapper') || detail.classList.contains('detail-operasi-sudah-wrapper'))) {
                        // Biarkan detail wrapper ikut logika open/close-nya sendiri
                    }
                } else {
                    card.style.display = 'none';
                    if(detail && (detail.classList.contains('detail-operasi-wrapper') || detail.classList.contains('detail-operasi-sudah-wrapper'))) {
                        detail.style.display = 'none';
                    }
                }
            });
        });
    }
    bindSearch('searchJadwalOperasi', 'tab-content-booking');
    bindSearch('searchSelesaiOperasi', 'operasiContainer');
})();
</script>