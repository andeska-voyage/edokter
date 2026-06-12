<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
define('BASE_URL', APP_BASE_URL);

// Ambil info dokter yang login dari session (DECRYPT)
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';

if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Ambil filter status dari parameter GET, default 'Belum'
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'Belum';

// ========================================
// SHIFT IGD: Cek apakah dokter punya pasien IGDK
// Filter tanggal IGD: Hari Ini / Kemarin (via parameter tgl_igd)
// ========================================
$cek_dokter_igd = bukaquery("SELECT COUNT(*) as jml FROM reg_periksa 
    WHERE kd_dokter = '$kd_dokter_login' AND kd_poli = 'IGDK' 
    AND tgl_registrasi BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND CURDATE()");
$is_dokter_igd = (mysqli_fetch_array($cek_dokter_igd)['jml'] > 0);

// Filter tanggal IGD: 'hari_ini' (default) atau 'kemarin'
$tgl_igd = isset($_GET['tgl_igd']) ? $_GET['tgl_igd'] : 'hari_ini';

if($is_dokter_igd && $tgl_igd == 'kemarin') {
    // Khusus lihat pasien IGDK kemarin saja
    $filter_tgl_registrasi = "AND rp.tgl_registrasi = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND rp.kd_poli = 'IGDK'";
} else {
    // Default: hari ini semua poli + IGDK kemarin
    $filter_tgl_registrasi = "AND (
        rp.tgl_registrasi = CURDATE() 
        OR (rp.tgl_registrasi = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND rp.kd_poli = 'IGDK')
    )";
}

// OPTIMIZED: Single query untuk semua counts menggunakan CASE WHEN
$query_counts = bukaquery("SELECT 
    SUM(CASE WHEN stts = 'Belum' AND status_lanjut != 'Ranap' AND status_bayar != 'Sudah Bayar'
        AND rp.no_rawat NOT IN (SELECT no_rawat FROM mutasi_berkas WHERE status = 'Sudah Diterima' AND diterima IS NOT NULL)
        THEN 1 ELSE 0 END) as count_belum,
    SUM(CASE WHEN stts = 'Belum' AND status_lanjut != 'Ranap' AND status_bayar != 'Sudah Bayar'
        AND rp.no_rawat IN (SELECT no_rawat FROM mutasi_berkas WHERE status = 'Sudah Diterima' AND diterima IS NOT NULL)
        THEN 1 ELSE 0 END) as count_sedang_periksa,
    SUM(CASE WHEN stts = 'Sudah' AND status_lanjut != 'Ranap' AND status_bayar != 'Sudah Bayar' THEN 1 ELSE 0 END) as count_sudah,
    SUM(CASE WHEN stts NOT IN ('Meninggal') AND status_lanjut != 'Ranap' AND status_bayar != 'Sudah Bayar' THEN 1 ELSE 0 END) as count_semua,
    SUM(CASE WHEN status_lanjut = 'Ranap' THEN 1 ELSE 0 END) as count_inap,
    SUM(CASE WHEN status_bayar = 'Sudah Bayar' AND status_lanjut != 'Ranap' THEN 1 ELSE 0 END) as count_sudah_bayar,
    SUM(CASE WHEN stts = 'Meninggal' THEN 1 ELSE 0 END) as count_meninggal
FROM reg_periksa rp
WHERE kd_dokter = '$kd_dokter_login' 
$filter_tgl_registrasi");
$counts = mysqli_fetch_assoc($query_counts);

$count_belum = (int)($counts['count_belum'] ?? 0);
$count_sedang_periksa = (int)($counts['count_sedang_periksa'] ?? 0);
$count_sudah = (int)($counts['count_sudah'] ?? 0);
$count_semua = (int)($counts['count_semua'] ?? 0);
$count_masuk_inap = (int)($counts['count_inap'] ?? 0);
$count_sudah_bayar = (int)($counts['count_sudah_bayar'] ?? 0);
$count_meninggal = (int)($counts['count_meninggal'] ?? 0);
$ada_pasien_ranap = ($count_masuk_inap > 0);
$ada_pasien_sudah_bayar = ($count_sudah_bayar > 0);
$ada_pasien_meninggal = ($count_meninggal > 0);
$ada_pasien_sedang_periksa = ($count_sedang_periksa > 0);

// ========================================
// RUJUKAN INTERNAL POLI: Cek pasien yang dirujuk KE dokter login
// Match: rujukan_internal_poli.kd_dokter = dokter tujuan rujukan
// ========================================
$query_count_rip = bukaquery("SELECT COUNT(*) as jml 
    FROM rujukan_internal_poli rip 
    INNER JOIN reg_periksa rp ON rp.no_rawat = rip.no_rawat 
    WHERE rip.kd_dokter = '$kd_dokter_login' 
    AND rp.tgl_registrasi = CURDATE()");
$count_rujukan_internal = (int)(mysqli_fetch_assoc($query_count_rip)['jml'] ?? 0);
$ada_rujukan_internal = ($count_rujukan_internal > 0);
?>

<!-- Container untuk Data Pasien -->
<div class="row clearfix" id="patientContainer">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header" style="background: linear-gradient(135deg, #5FD38D 0%, #0F6FB2 100%); color: white; padding: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <h2 style="color: white; margin: 0;">
                        <i class="material-icons" style="vertical-align: middle;">local_hospital</i>
                        PASIEN HARI INI
                    </h2>
                    <div style="position: relative; min-width: 220px; max-width: 300px;">
                        <i class="material-icons" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #999; font-size: 18px; pointer-events: none;">search</i>
                        <input type="text" id="searchPasienRalan" 
                               placeholder="Cari nama / No. RM..." 
                               style="width: 100%; padding: 8px 12px 8px 36px; border-radius: 20px; border: none; font-size: 13px; outline: none; color: #333;">
                    </div>
                </div>
            
                <!-- Filter Buttons -->
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <?php
                    // Ambil act dari URL atau default ke ListPasien
                    $current_act = isset($_GET['act']) ? $_GET['act'] : 'ListPasien';
                    ?>
                    
                    <a href="index.php?act=<?php echo $current_act; ?>&status=Belum" 
                    class="btn waves-effect" 
                    style="<?php echo ($filter_status == 'Belum') ? 'background:  #f44336; color: white;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s; position: relative;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">schedule</i>
                        Belum Periksa
                        <span style="background: <?php echo ($filter_status == 'Belum') ? 'rgba(255,255,255,0.3)' : '#f44336'; ?>; color: <?php echo ($filter_status == 'Belum') ? 'white' : 'white'; ?>; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;">
                            <?php echo $count_belum; ?>
                        </span>
                    </a>
                    
                    <?php if($ada_rujukan_internal || $filter_status == 'RujukanInternal'): ?>
                    <a href="index.php?act=<?php echo $current_act; ?>&status=RujukanInternal" 
                    class="btn waves-effect" 
                    style="<?php echo ($filter_status == 'RujukanInternal') ? 'background: #ff9800; color: white;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s; position: relative;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">swap_horiz</i>
                        Rujukan Internal Poli
                        <span style="background: <?php echo ($filter_status == 'RujukanInternal') ? 'rgba(255,255,255,0.3)' : '#ff9800'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;">
                            <?php echo $count_rujukan_internal; ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if($ada_pasien_sedang_periksa || $filter_status == 'SedangPeriksa'): ?>
                    <a href="index.php?act=<?php echo $current_act; ?>&status=SedangPeriksa" 
                    class="btn waves-effect" 
                    style="<?php echo ($filter_status == 'SedangPeriksa') ? 'background: #FF9800; color: white;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s; position: relative;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">hourglass_top</i>
                        Sedang Diperiksa
                        <span style="background: <?php echo ($filter_status == 'SedangPeriksa') ? 'rgba(255,255,255,0.3)' : '#FF9800'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;">
                            <?php echo $count_sedang_periksa; ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    
                    <a href="index.php?act=<?php echo $current_act; ?>&status=Sudah" 
                    class="btn waves-effect" 
                    style="<?php echo ($filter_status == 'Sudah') ? 'background: #4caf50; color: white;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s; position: relative;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">check_circle</i>
                        Sudah Periksa
                        <span style="background: <?php echo ($filter_status == 'Sudah') ? 'rgba(255,255,255,0.3)' : '#4caf50'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;">
                            <?php echo $count_sudah; ?>
                        </span>
                    </a>
                    
                    <!-- <a href="index.php?act=<?php echo $current_act; ?>&status=Semua" 
                    class="btn waves-effect" 
                    style="<?php echo ($filter_status == 'Semua') ? 'background: #ffc107; color: #333;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s; position: relative;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">list</i>
                        Semua Pasien
                        <span style="background: <?php echo ($filter_status == 'Semua') ? 'rgba(0,0,0,0.2)' : '#ffc107'; ?>; color: <?php echo ($filter_status == 'Semua') ? '#333' : 'white'; ?>; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;">
                            <?php echo $count_semua; ?>
                        </span>
                    </a> -->
                    
                    <?php if($ada_pasien_ranap): ?>
                    <a href="index.php?act=<?php echo $current_act; ?>&status=MasukInap" 
                    class="btn waves-effect" 
                    style="<?php echo ($filter_status == 'MasukInap') ? 'background: #9c27b0; color: white;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s; position: relative;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">hotel</i>
                        Masuk Inap
                        <span style="background: <?php echo ($filter_status == 'MasukInap') ? 'rgba(255,255,255,0.3)' : '#9c27b0'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;">
                            <?php echo $count_masuk_inap; ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if($ada_pasien_sudah_bayar): ?>
                    <a href="index.php?act=<?php echo $current_act; ?>&status=SudahBayar" 
                    class="btn waves-effect" 
                    style="<?php echo ($filter_status == 'SudahBayar') ? 'background: #607d8b; color: white;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s; position: relative;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">paid</i>
                        Sudah Bayar
                        <span style="background: <?php echo ($filter_status == 'SudahBayar') ? 'rgba(255,255,255,0.3)' : '#607d8b'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;">
                            <?php echo $count_sudah_bayar; ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if($ada_pasien_meninggal): ?>
                    <a href="index.php?act=<?php echo $current_act; ?>&status=Meninggal" 
                    class="btn waves-effect" 
                    style="<?php echo ($filter_status == 'Meninggal') ? 'background: #37474f; color: white;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s; position: relative;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">person_off</i>
                        Meninggal
                        <span style="background: <?php echo ($filter_status == 'Meninggal') ? 'rgba(255,255,255,0.3)' : '#37474f'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;">
                            <?php echo $count_meninggal; ?>
                        </span>
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if($is_dokter_igd): ?>
                <!-- Filter Tanggal IGD -->
                <div style="display: flex; align-items: center; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2);">
                    <span style="color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 600;">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">date_range</i> IGD:
                    </span>
                    <a href="index.php?act=<?php echo $current_act; ?>&status=<?php echo $filter_status; ?>&tgl_igd=hari_ini" 
                       style="padding: 4px 14px; border-radius: 15px; font-size: 11px; font-weight: 600; text-decoration: none; transition: all 0.3s;
                       <?php echo ($tgl_igd == 'hari_ini') ? 'background: white; color: #1976d2;' : 'background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.9);'; ?>">
                        Hari Ini
                    </a>
                    <a href="index.php?act=<?php echo $current_act; ?>&status=<?php echo $filter_status; ?>&tgl_igd=kemarin" 
                       style="padding: 4px 14px; border-radius: 15px; font-size: 11px; font-weight: 600; text-decoration: none; transition: all 0.3s;
                       <?php echo ($tgl_igd == 'kemarin') ? 'background: white; color: #1976d2;' : 'background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.9);'; ?>">
                        Kemarin
                    </a>
                </div>
                <?php endif; ?>
                
            </div>
            <div class="body" style="padding: 20px;">
                <?php 
                // ========================================
                // FILTER: RUJUKAN INTERNAL POLI
                // Query terpisah karena source data beda (bukan dari reg_periksa.kd_dokter)
                // ========================================
                if($filter_status == 'RujukanInternal') {
                    $query_rip = bukaquery("SELECT 
                        rp.no_reg, rp.no_rawat, rp.no_rkm_medis, rp.kd_poli, 
                        rp.umurdaftar, rp.sttsumur, rp.tgl_registrasi, rp.jam_reg, rp.stts, rp.status_lanjut,
                        p.nm_pasien, p.jk, p.tmp_lahir, p.tgl_lahir,
                        pol.nm_poli, pj.png_jawab,
                        pol_rip.nm_poli as nm_poli_rujukan,
                        rp.kd_dokter as kd_dokter_asal,
                        d_asal.nm_dokter as nm_dokter_asal
                    FROM rujukan_internal_poli rip
                    INNER JOIN reg_periksa rp ON rp.no_rawat = rip.no_rawat
                    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                    INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
                    INNER JOIN poliklinik pol_rip ON pol_rip.kd_poli = rip.kd_poli
                    LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                    LEFT JOIN dokter d_asal ON d_asal.kd_dokter = rp.kd_dokter
                    WHERE rip.kd_dokter = '$kd_dokter_login'
                    AND rp.tgl_registrasi = CURDATE()
                    ORDER BY rp.no_reg ASC");
                    
                    $jml_rip = mysqli_num_rows($query_rip);
                    
                    if($jml_rip == 0) {
                        echo '<div class="alert alert-info" style="text-align: center; padding: 40px;">
                                <i class="material-icons" style="font-size: 64px; color: #ff9800;">swap_horiz</i>
                                <h4 style="margin-top: 15px;">Belum ada rujukan internal poli hari ini</h4>
                                <p style="color: #999;">Pasien yang dirujuk ke poli Anda akan muncul di sini</p>
                              </div>';
                    } else {
                        while($rip = mysqli_fetch_array($query_rip)) {
                            $encrypted_norawat = urlencode(encrypt_decrypt($rip['no_rawat'], 'e'));
                            $encrypted_norm = urlencode(encrypt_decrypt($rip['no_rkm_medis'], 'e'));
                            $avatar_img = ($rip['jk'] == 'L') ? 'images/male.png' : 'images/female.png';
                            $umur = $rip['umurdaftar'] . ' ' . $rip['sttsumur'];
                            $png_jawab = $rip['png_jawab'] ?? '-';
                            if(stripos($png_jawab, 'BPJS') !== false || stripos($png_jawab, 'JKN') !== false) { $pj_color = '#4caf50'; }
                            elseif(stripos($png_jawab, 'UMUM') !== false) { $pj_color = '#2196f3'; }
                            else { $pj_color = '#9e9e9e'; }
                            ?>
                            <div class="patient-card" style="background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid #ff9800; transition: all 0.3s ease; position: relative;">
                                <div class="row">
                                    <div style="width: 44%; float: left; padding: 0 15px;">
                                        <div style="display: flex; align-items: flex-start;">
                                            <div style="margin-right: 12px; flex-shrink: 0;">
                                                <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; background: #e0e0e0; margin-bottom: 5px;">
                                                    <img src="<?php echo $avatar_img; ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                                </div>
                                                <div style="text-align: center;">
                                                    <span style="background: #ff9800; color: white; padding: 3px 6px; border-radius: 11px; font-size: 15px; font-weight: 600; display: inline-block;">
                                                        <?php echo str_pad($rip['no_reg'], 3, '0', STR_PAD_LEFT); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px;">
                                                    <h4 style="margin: 0; font-size: 15px; font-weight: 600; color: #333;">
                                                        <?php echo strtoupper($rip['nm_pasien']); ?>
                                                    </h4>
                                                    <div class="dropdown-pasien" style="display: inline-block; position: relative;">
                                                        <button class="btn btn-primary btn-xs dropdown-pasien-toggle waves-effect" 
                                                                type="button" 
                                                                style="background: #ff9800; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; margin-left: 5px; font-weight: 600;">
                                                            Aksi <span class="caret"></span>
                                                        </button>
                                                        <ul class="dropdown-pasien-menu">
                                                            <li><a href="index.php?act=Pemeriksaan&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Pemeriksaan</a></li>

                                                            <?php if(cekAksesMenu('surat_buta_warna') || cekAksesMenu('surat_keterangan_sehat') || cekAksesMenu('surat_sakit') || cekAksesMenu('surat_bebas_narkoba') || cekAksesMenu('surat_bebas_tbc') || cekAksesMenu('surat_bebas_tato') || cekAksesMenu('surat_cuti_hamil') || cekAksesMenu('surat_keterangan_layak_terbang')): ?>
                                                            <li class="has-submenu">
                                                                <a href="#" class="submenu-trigger">Surat Keterangan Dokter</a>
                                                                <ul class="dropdown-submenu">
                                                                    <?php if(cekAksesMenu('surat_buta_warna')): ?>
                                                                    <li><a href="index.php?act=suratbutawarna&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Buta Warna</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_keterangan_sehat')): ?>
                                                                    <li><a href="index.php?act=suratketerangansehat&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Keterangan Sehat</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_sakit')): ?>
                                                                    <li><a href="index.php?act=suratsakit&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Sakit</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_cuti_hamil')): ?>
                                                                    <li><a href="index.php?act=surathamil&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Cuti Hamil</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_bebas_narkoba')): ?>
                                                                    <li><a href="index.php?act=suratbebasnarkoba&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Bebas Narkoba</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_bebas_tbc')): ?>
                                                                    <li><a href="index.php?act=suratbebastbc&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Bebas TBC</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_bebas_tato')): ?>
                                                                    <li><a href="index.php?act=suratbebastato&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Bebas Tato</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_keterangan_layak_terbang')): ?>
                                                                    <li><a href="index.php?act=suratketeranganlayakterbang&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Keterangan Layak Terbang</a></li>
                                                                    <?php endif; ?>
                                                                </ul>
                                                            </li>
                                                            <?php endif; ?>

                                                            <?php if(cekAksesMenu('pasien_meninggal')): ?>
                                                            <li class="has-submenu">
                                                                <a href="#" class="submenu-trigger">Status Pasien</a>
                                                                <ul class="dropdown-submenu">
                                                                    <li><a href="index.php?act=pasienmeninggal&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pasien Meninggal</a></li>
                                                                </ul>
                                                            </li>
                                                            <?php endif; ?>

                                                            <li><a href="index.php?act=ResumeMedis&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Resume Medis</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div style="font-size: 12px; color: #666; margin-bottom: 3px;">
                                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">badge</i>
                                                    <strong>No. RM:</strong> <?php echo $rip['no_rkm_medis']; ?>
                                                </div>
                                                <div style="font-size: 11px; color: #999;">
                                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">folder</i>
                                                    <?php echo $rip['no_rawat']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 28%; float: left; padding: 0 15px;">
                                        <div style="font-size: 13px;">
                                            <div style="margin-bottom: 6px;">
                                                <i class="material-icons" style="font-size: 14px; vertical-align: middle; color: #666;">access_time</i>
                                                <strong style="color: #666;"><?php echo $umur; ?></strong>
                                                <span style="background: <?php echo $pj_color; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; margin-left: 5px;">
                                                    <?php echo strtoupper($png_jawab); ?>
                                                </span>
                                            </div>
                                            <div style="color: #999; font-size: 11px; margin-bottom: 6px;">
                                                TGL_REG: <?php echo date('d/m/Y, H:i', strtotime($rip['tgl_registrasi'].' '.$rip['jam_reg'])); ?>
                                            </div>
                                            <div style="margin-bottom: 6px;">
                                                <span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">local_hospital</i>
                                                    <?php echo $rip['nm_poli']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="width: 28%; float: left; padding: 0 15px;">
                                        <div style="padding: 0 10px;">
                                            <div style="font-size: 11px; color: #999; margin-bottom: 8px; font-weight: 600;">
                                                <i class="material-icons" style="font-size: 12px; vertical-align: middle;">swap_horiz</i>
                                                Info Rujukan Internal
                                            </div>
                                            <div style="margin-bottom: 6px;">
                                                <span style="background: #ff9800; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">arrow_forward</i>
                                                    Ke: <?php echo htmlspecialchars($rip['nm_poli_rujukan']); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span style="background: #e8eaf6; color: #3f51b5; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">person</i>
                                                    Dari: <?php echo htmlspecialchars($rip['nm_dokter_asal'] ?? '-'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                } else {
                // ========================================
                // FILTER: QUERY UTAMA (semua filter selain RujukanInternal)
                // ========================================
                
                // Query pasien rawat jalan hari ini - OPTIMIZED dengan FILTER
                $where_status = "";
                if($filter_status == 'Belum') {
                    // Belum periksa, exclude Ranap, Sudah Bayar, Meninggal, dan Sedang Diperiksa
                    $where_status = "AND rp.stts = 'Belum' AND rp.status_lanjut != 'Ranap' AND rp.status_bayar != 'Sudah Bayar'
                        AND rp.no_rawat NOT IN (SELECT no_rawat FROM mutasi_berkas WHERE status = 'Sudah Diterima' AND diterima IS NOT NULL)";
                } elseif($filter_status == 'SedangPeriksa') {
                    // Sedang diperiksa: berkas sudah diterima tapi status masih Belum
                    $where_status = "AND rp.stts = 'Belum' AND rp.status_lanjut != 'Ranap' AND rp.status_bayar != 'Sudah Bayar'
                        AND rp.no_rawat IN (SELECT no_rawat FROM mutasi_berkas WHERE status = 'Sudah Diterima' AND diterima IS NOT NULL)";
                } elseif($filter_status == 'Sudah') {
                    // Sudah periksa, exclude Ranap, Sudah Bayar
                    $where_status = "AND rp.stts = 'Sudah' AND rp.status_lanjut != 'Ranap' AND rp.status_bayar != 'Sudah Bayar'";
                } elseif($filter_status == 'MasukInap') {
                    // Khusus pasien yang masuk rawat inap
                    $where_status = "AND rp.status_lanjut = 'Ranap'";
                } elseif($filter_status == 'SudahBayar') {
                    // Khusus pasien yang sudah bayar (exclude ranap)
                    $where_status = "AND rp.status_bayar = 'Sudah Bayar' AND rp.status_lanjut != 'Ranap'";
                } elseif($filter_status == 'Meninggal') {
                    // Khusus pasien meninggal (stts = 'Meninggal')
                    $where_status = "AND rp.stts = 'Meninggal'";
                } else {
                    // Semua pasien, exclude Ranap, Sudah Bayar, Meninggal
                    $where_status = "AND rp.stts NOT IN ('Meninggal') AND rp.status_lanjut != 'Ranap' AND rp.status_bayar != 'Sudah Bayar'";
                }
                
$querypasien = bukaquery("SELECT 
                            rp.no_reg,
                            rp.no_rawat,
                            rp.no_rkm_medis,
                            rp.kd_poli,
                            rp.umurdaftar,
                            rp.sttsumur,
                            rp.tgl_registrasi,
                            rp.jam_reg,
                            rp.stts,
                            rp.status_lanjut,
                            p.nm_pasien,
                            p.jk,
                            p.tmp_lahir,
                            p.tgl_lahir,
                            pol.nm_poli,
                            pj.png_jawab,
                            bs.nmdiagnosaawal,
                            COUNT(DISTINCT pr.no_rawat) as jumlah_soap,
                            COUNT(DISTINCT pakr.no_rawat) as ada_askep_ralan,
                            COUNT(DISTINCT pmr.no_rawat) as ada_medis_ralan,
                            COUNT(DISTINCT dti.no_rawat) as ada_triase,
                            COUNT(DISTINCT paki.no_rawat) as ada_askep_igd,
                            COUNT(DISTINCT pmi.no_rawat) as ada_medis_igd,
                            COUNT(DISTINCT pralan.no_rawat) as ada_pemeriksaan,
                            COUNT(DISTINCT dp.no_rawat) as ada_diagnosa,
                            COUNT(DISTINCT ro.no_rawat) as ada_resep,
                            COUNT(DISTINCT pl.no_rawat) as ada_lab,
                            COUNT(DISTINCT prad.no_rawat) as ada_radiologi,
                            COUNT(DISTINCT rps.no_rawat) as ada_resume,
                            COUNT(DISTINCT rjd.no_rawat) as ada_tindakan
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        INNER JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
                        LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                        LEFT JOIN bridging_sep bs ON bs.no_rawat = rp.no_rawat
                        LEFT JOIN pemeriksaan_ralan pr ON pr.no_rawat = rp.no_rawat
                        LEFT JOIN penilaian_awal_keperawatan_ralan pakr ON pakr.no_rawat = rp.no_rawat
                        LEFT JOIN penilaian_medis_ralan pmr ON pmr.no_rawat = rp.no_rawat
                        LEFT JOIN data_triase_igd dti ON dti.no_rawat = rp.no_rawat
                        LEFT JOIN penilaian_awal_keperawatan_igd paki ON paki.no_rawat = rp.no_rawat
                        LEFT JOIN penilaian_medis_igd pmi ON pmi.no_rawat = rp.no_rawat
                        LEFT JOIN pemeriksaan_ralan pralan ON pralan.no_rawat = rp.no_rawat
                        LEFT JOIN diagnosa_pasien dp ON dp.no_rawat = rp.no_rawat
                        LEFT JOIN resep_obat ro ON ro.no_rawat = rp.no_rawat
                        LEFT JOIN permintaan_lab pl ON pl.no_rawat = rp.no_rawat
                        LEFT JOIN permintaan_radiologi prad ON prad.no_rawat = rp.no_rawat
                        LEFT JOIN resume_pasien rps ON rps.no_rawat = rp.no_rawat
                        LEFT JOIN rawat_jl_dr rjd ON rjd.no_rawat = rp.no_rawat
                        WHERE rp.kd_dokter = '$kd_dokter_login' 
                        $filter_tgl_registrasi
                        $where_status
                        GROUP BY rp.no_reg, rp.no_rawat, rp.no_rkm_medis, rp.umurdaftar, rp.sttsumur,
                                rp.tgl_registrasi, rp.jam_reg, rp.stts, rp.status_lanjut, p.nm_pasien, p.jk,
                                p.tmp_lahir, p.tgl_lahir, pol.nm_poli, pj.png_jawab
                        ORDER BY rp.no_reg ASC");
                
                $jumlah_pasien = mysqli_num_rows($querypasien);
                
                if($jumlah_pasien == 0) {
                    if($filter_status == 'Belum') {
                        $status_text = 'belum diperiksa';
                    } elseif($filter_status == 'Sudah') {
                        $status_text = 'sudah diperiksa';
                    } elseif($filter_status == 'MasukInap') {
                        $status_text = 'yang masuk rawat inap';
                    } elseif($filter_status == 'SedangPeriksa') {
                        $status_text = 'sedang diperiksa';
                    } elseif($filter_status == 'SudahBayar') {
                        $status_text = 'yang sudah bayar';
                    } elseif($filter_status == 'Meninggal') {
                        $status_text = 'meninggal';
                    } else {
                        $status_text = '';
                    }
                    echo '<div class="alert alert-info" style="text-align: center; padding: 40px;">
                            <i class="material-icons" style="font-size: 64px; color: #667eea;">info</i>
                            <h4 style="margin-top: 15px;">Belum ada pasien ' . $status_text . ' hari ini</h4>
                            <p style="color: #999;">Pasien yang terdaftar akan muncul di sini</p>
                          </div>';
                } else {
                    while($rs = mysqli_fetch_array($querypasien)) {
                        // Enkripsi parameter
                        $encrypted_norawat = urlencode(encrypt_decrypt($rs["no_rawat"], "e"));
                        $encrypted_norm = urlencode(encrypt_decrypt($rs["no_rkm_medis"], "e"));
                        
                        // Status badge berdasarkan status pemeriksaan
                        if($rs["stts"] == "Meninggal") {
                            $badge_color = "#37474f";
                            $status_text = "Meninggal";
                        } elseif($rs["stts"] == "Sudah") {
                            $badge_color = "#4caf50";
                            $status_text = "Selesai";
                        } else {
                            $badge_color = "#ff9800";
                            $status_text = "Belum";
                        }
                        
                        // Avatar image based on gender
                        $avatar_img = ($rs["jk"] == "L") ? "images/male.png" : "images/female.png";
                        
                        // Format umur
                        $umur = $rs["umurdaftar"] . " " . $rs["sttsumur"];
                        
                        // Badge pembayaran
                        $png_jawab = $rs["png_jawab"] ?? '-';
                        if(stripos($png_jawab, 'BPJS') !== false || stripos($png_jawab, 'JKN') !== false) {
                            $pj_color = "#4caf50";
                        } elseif(stripos($png_jawab, 'UMUM') !== false) {
                            $pj_color = "#2196f3";
                        } elseif(stripos($png_jawab, 'ASURANSI') !== false || stripos($png_jawab, 'JASARAHARJA') !== false) {
                            $pj_color = "#ff9800";
                        } else {
                            $pj_color = "#9e9e9e";
                        }
                        ?>
                        
                        <div class="patient-card" style="background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 10px; border-left: 4px solid <?php echo $badge_color; ?>; transition: all 0.3s ease; position: relative; overflow: visible;">
                            <div class="row">
                                    <!-- KOLOM 1: Info Pasien + Badge Antrian (46%) -->
                                    <div style="width: 44%; float: left; padding: 0 15px;">
                                        <div style="display: flex; align-items: flex-start;">
                                            <!-- Avatar + Badge Antrian -->
                                            <div style="margin-right: 12px; flex-shrink: 0;">
                                                <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; background: #e0e0e0; margin-bottom: 5px;">
                                                    <img src="<?php echo $avatar_img; ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                                                </div>
                                                <?php
                                                // Badge nomor antrian di bawah avatar
                                                if($rs["stts"] == "Meninggal") {
                                                    $antrian_bg = "#37474f"; // Dark gray
                                                } elseif($rs["stts"] == "Sudah") {
                                                    $antrian_bg = "#4caf50"; // Hijau
                                                } else {
                                                    $antrian_bg = "#f44336"; // Merah
                                                }
                                                ?>
                                                <div style="text-align: center;">
                                                    <span style="background: <?php echo $antrian_bg; ?>; color: white; padding: 3px 6px; border-radius: 11px; font-size: 15px; font-weight: 600; display: inline-block;">
                                                        <?php echo str_pad($rs["no_reg"], 3, '0', STR_PAD_LEFT); ?>
                                                    </span>
                                                </div>

                                            </div>
                                            
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px;">
                                                    <div>
                                                        <h4 style="margin: 0; font-size: 15px; font-weight: 600; color: #333; display: inline-block;">
                                                            <?php if(in_array($filter_status, ['Belum', 'SedangPeriksa', 'Sudah'])): ?>
                                                            <a href="index.php?act=Pemeriksaan&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>" 
                                                               style="color: #1976d2; text-decoration: none;"
                                                               onmouseover="this.style.textDecoration='underline'" 
                                                               onmouseout="this.style.textDecoration='none'">
                                                                <?php echo strtoupper($rs["nm_pasien"]); ?>
                                                            </a>
                                                            <?php else: ?>
                                                            <?php echo strtoupper($rs["nm_pasien"]); ?>
                                                            <?php endif; ?>
                                                        </h4>
                                                    </div>
                                                    <!-- Tombol Aksi di samping nama -->
                                                    <?php if($filter_status == 'Meninggal'): ?>
                                                    <!-- Filter Meninggal: Tombol aksi ke form pasien meninggal -->
                                                    <?php if(cekAksesMenu('pasien_meninggal')): ?>
                                                    <a href="index.php?act=pasienmeninggal&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" 
                                                       class="btn waves-effect" 
                                                       style="background: #37474f; color: white; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">description</i>
                                                        Pasien Meninggal
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php elseif($filter_status == 'MasukInap'): ?>
                                                    <!-- Filter Masuk Inap: Tombol Riwayat Perawatan -->
                                                    <a href="index.php?act=Pemeriksaanriwayat&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>" 
                                                       class="btn waves-effect" 
                                                       style="background: #9c27b0; color: white; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">history</i>
                                                        Riwayat Perawatan
                                                    </a>
                                                    <?php elseif($filter_status == 'SudahBayar'): ?>
                                                    <!-- Filter Sudah Bayar: Tombol Riwayat Perawatan -->
                                                    <a href="index.php?act=Pemeriksaanriwayat&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>" 
                                                       class="btn waves-effect" 
                                                       style="background: #607d8b; color: white; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">history</i>
                                                        Riwayat Perawatan
                                                    </a>
                                                    <?php else: ?>
                                                    <div class="dropdown-pasien" style="display: inline-block; position: relative;">
                                                        <button class="btn btn-primary btn-xs dropdown-pasien-toggle waves-effect" 
                                                                type="button" 
                                                                style="background: #26c6da; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; margin-left: 5px; font-weight: 600;">
                                                            Aksi <span class="caret"></span>
                                                        </button>
                                                        <ul class="dropdown-pasien-menu">  

                                                            <!-- Pemeriksaan - Semua user bisa akses -->
                                                            <li><a href="index.php?act=Pemeriksaan&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Pemeriksaan</a></li>

                                                            <?php if(cekAksesMenu('surat_buta_warna') || cekAksesMenu('surat_keterangan_sehat') || cekAksesMenu('surat_sakit') || cekAksesMenu('surat_bebas_narkoba') || cekAksesMenu('surat_bebas_tbc') || cekAksesMenu('surat_bebas_tato') || cekAksesMenu('surat_cuti_hamil') || cekAksesMenu('surat_keterangan_layak_terbang')): ?>
                                                            <li class="has-submenu">
                                                                <a href="#" class="submenu-trigger">Surat Keterangan Dokter</a>
                                                                <ul class="dropdown-submenu">
                                                                    <?php if(cekAksesMenu('surat_buta_warna')): ?>
                                                                    <li><a href="index.php?act=suratbutawarna&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Surat Buta Warna</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_keterangan_sehat')): ?>
                                                                    <li><a href="index.php?act=suratketerangansehat&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Surat Keterangan Sehat</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_sakit')): ?>
                                                                    <li><a href="index.php?act=suratsakit&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Surat Sakit</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_cuti_hamil')): ?>
                                                                    <li><a href="index.php?act=surathamil&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Cuti Hamil</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_bebas_narkoba')): ?>
                                                                    <li><a href="index.php?act=suratbebasnarkoba&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Surat Bebas Narkoba</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_bebas_tbc')): ?>
                                                                    <li><a href="index.php?act=suratbebastbc&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Bebas TBC</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_bebas_tato')): ?>
                                                                    <li><a href="index.php?act=suratbebastato&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Bebas Tato</a></li>
                                                                    <?php endif; ?>
                                                                    <?php if(cekAksesMenu('surat_keterangan_layak_terbang')): ?>
                                                                    <li><a href="index.php?act=suratketeranganlayakterbang&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Surat Keterangan Layak Terbang</a></li>
                                                                    <?php endif; ?>
                                                                </ul>
                                                            </li>
                                                            <?php endif; ?>        
                                                            
                                                           <?php if(cekAksesMenu('pasien_meninggal')): ?>
                                                            <li class="has-submenu">
                                                                <a href="#" class="submenu-trigger">Status Pasien</a>
                                                                <ul class="dropdown-submenu">
                                                                    <?php if(cekAksesMenu('pasien_meninggal')): ?>
                                                                    <li><a href="index.php?act=pasienmeninggal&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Pasien Meninggal</a></li>
                                                                    <?php endif; ?> 
                                                                </ul>
                                                            </li>
                                                            <?php endif; ?>  

                                                            <!-- Resume Medis - Semua user bisa akses -->
                                                            <li><a href="index.php?act=ResumeMedis&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Resume Medis</a></li>
                                                        </ul>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- No. RM + Tombol Panggil (horizontal) -->
                                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 3px;">
                                                    <div style="font-size: 12px; color: #666;">
                                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">badge</i>
                                                        <strong>No. RM:</strong> <?php echo $rs["no_rkm_medis"]; ?>
                                                    </div>
                                                    
                                                    <?php if($filter_status != 'Meninggal' && $filter_status != 'MasukInap' && $filter_status != 'SedangPeriksa' && $filter_status != 'Sudah' && $filter_status != 'SudahBayar'): ?>
                                                        <?php if($rs['kd_poli'] != 'IGDK'): ?>
                                                            <button class="btn btn-primary btn-xs waves-effect btn-panggil-pasien" 
                                                                    data-norawat="<?php echo $rs['no_rawat']; ?>"
                                                                    data-nmpasien="<?php echo $rs['nm_pasien']; ?>"
                                                                    style="background: #667eea; border: none; border-radius: 10px; padding: 3px 8px; font-size: 9px; font-weight: 600; color: white;">
                                                                <i class="material-icons" style="font-size: 12px; vertical-align: middle;">volume_up</i>
                                                                Panggil
                                                            </button>
                                                        <?php else: ?>
                                                            <span style="background: #ff5722; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">
                                                                <i class="material-icons" style="font-size: 12px; vertical-align: middle;">local_hospital</i>
                                                                IGD
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- No. Rawat + Badge Status (horizontal) -->
                                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                                    <div style="font-size: 11px; color: #999;">
                                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">folder</i>
                                                        <?php echo $rs["no_rawat"]; ?>
                                                    </div>
                                                    <span style="background: <?php echo $badge_color; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">
                                                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">
                                                            <?php 
                                                            if($rs["stts"] == "Meninggal") echo "person_off";
                                                            elseif($rs["stts"] == "Sudah") echo "check_circle";
                                                            else echo "schedule";
                                                            ?>
                                                        </i>
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </div>
                                                <?php if(!empty($rs['nmdiagnosaawal'])): ?>
                                                <!-- Diagnosa Awal SEP + Tombol iCare -->
                                                <div style="margin-top: 5px; display: flex; align-items: center; gap: 6px;">
                                                    <a href="index.php?act=Icare&rm=<?php echo $encrypted_norm; ?>" 
                                                       class="btn-icare" 
                                                       title="iCare - Riwayat Perawatan BPJS"
                                                       style="display: inline-block; flex-shrink: 0; cursor: pointer;">
                                                        <img src="images/icare.png" alt="iCare" style="height: 22px; object-fit: contain; vertical-align: middle;">
                                                    </a>
                                                    <span style="font-size: 10px; color: #17a2b8;">
                                                        <i class="material-icons" style="font-size: 11px; vertical-align: middle;">local_hospital</i>
                                                        <?php echo htmlspecialchars($rs['nmdiagnosaawal']); ?>
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- KOLOM 2: Umur, Pembayaran, Poli (24%) -->
                                    <div style="width: 28%; float: left; padding: 0 15px;">
                                        <div style="font-size: 13px;">
                                            <div style="margin-bottom: 6px;">
                                                <i class="material-icons" style="font-size: 14px; vertical-align: middle; color: #666;">access_time</i>
                                                <strong style="color: #666;"><?php echo $umur; ?></strong>
                                                <span style="background: <?php echo $pj_color; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; margin-left: 5px;">
                                                    <?php echo strtoupper($png_jawab); ?>
                                                </span>
                                            </div>
                                            <div style="color: #999; font-size: 11px; margin-bottom: 6px;">
                                                TGL_REG: <?php echo date('d/m/Y, H:i', strtotime($rs["tgl_registrasi"].' '.$rs["jam_reg"])); ?>
                                            </div>
                                            <div style="margin-bottom: 6px;">
                                                <span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">local_hospital</i>
                                                    <?php echo $rs["nm_poli"]; ?>
                                                </span>
                                            </div>
                                            <?php
                                            // Badge TTV - CEK 3 TABEL: SOAP, Triase IGD, Askep Ralan
                                            $jumlah_soap = $rs["jumlah_soap"];
                                            $ada_triase = $rs["ada_triase"];
                                            $ada_askep_ralan = $rs["ada_askep_ralan"];
                                            
                                            // Sudah TTV jika salah satu dari 3 tabel ada data
                                            if($jumlah_soap > 0 || $ada_triase > 0 || $ada_askep_ralan > 0) {
                                                $rme_color = "#4caf50"; // Hijau
                                                $rme_icon = "check_circle";
                                                $rme_text = "Sudah TTV";
                                            } else {
                                                $rme_color = "#9e9e9e"; // Abu-abu
                                                $rme_icon = "pending";
                                                $rme_text = "Belum TTV";
                                            }
                                            ?>
                                            <div>
                                                <span style="background: <?php echo $rme_color; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">
                                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;"><?php echo $rme_icon; ?></i>
                                                    <?php echo $rme_text; ?>
                                                </span>
                                                <?php
                                                // Badge Status Lanjut
                                                $status_lanjut = $rs["status_lanjut"];
                                                if($status_lanjut == "Ranap") {
                                                    $status_bg = "#9c27b0"; // Ungu
                                                    $status_icon = "hotel";
                                                    $status_text = "Ranap";
                                                } else {
                                                    $status_bg = "#17bafaff"; // Hijau
                                                    $status_icon = "assignment";
                                                    $status_text = "Ralan";
                                                }
                                                ?>
                                                <span style="background: <?php echo $status_bg; ?>; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 5px;">
                                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;"><?php echo $status_icon; ?></i>
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </div>

                                        </div>
                                    </div>
                                    
                                    <!-- KOLOM 3: Status RME (30%) -->
                                    <div style="width: 28%; float: left; padding: 0 15px;">
                                        <div style="padding: 0 10px;">
                                            <div style="font-size: 11px; color: #999; margin-bottom: 8px; font-weight: 600;">
                                                <i class="material-icons" style="font-size: 12px; vertical-align: middle;">description</i>
                                                Status RME
                                            </div>
                                            <?php
                                            // Cek setiap tabel RME
                                            $rme_badges = array();
                                            
                                            // 1. Penilaian Awal Keperawatan Ralan
                                            if($rs["ada_askep_ralan"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'Askep Ralan'
                                                );
                                            }
                                            
                                            // 2. Penilaian Medis Ralan
                                            if($rs["ada_medis_ralan"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'Medis Ralan'
                                                );
                                            }
                                            
                                            // 3. Data Triase IGD
                                            if($rs["ada_triase"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'Triase IGD'
                                                );
                                            }
                                            
                                            // 4. Penilaian Awal Keperawatan IGD
                                            if($rs["ada_askep_igd"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'Askep IGD'
                                                );
                                            }
                                            
                                            // 5. ✅ TAMBAHAN BARU - Penilaian Medis IGD
                                            if($rs["ada_medis_igd"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'Medis IGD'
                                                );
                                            }
                                            
                                            // 6. Pemeriksaan Ralan (SOAP)
                                            if($rs["ada_pemeriksaan"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'SOAP'
                                                );
                                            }
                                            
                                            // 7. Diagnosa Pasien
                                            if($rs["ada_diagnosa"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'Diagnosa'
                                                );
                                            }
                                            
                                            // 8. Resep Obat
                                            if($rs["ada_resep"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'E-Resep'
                                                );
                                            }
                                            
                                            // 9. Permintaan Lab
                                            if($rs["ada_lab"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'Lab'
                                                );
                                            }
                                            
                                            // 10. Permintaan Radiologi
                                            if($rs["ada_radiologi"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#4caf50',
                                                    'icon' => 'check_circle',
                                                    'text' => 'Radiologi'
                                                );
                                            }
                                            
                                            // 11. Resume Pasien
                                            if($rs["ada_resume"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#3f51b5',
                                                    'icon' => 'check_circle',
                                                    'text' => 'Resume'
                                                );
                                            }

                                            // 12. Rawat Jalan Dokter (Tindakan)
                                            if($rs["ada_tindakan"] > 0) {
                                                $rme_badges[] = array(
                                                    'color' => '#ff5722',
                                                    'icon' => 'healing',
                                                    'text' => 'Tindakan'
                                                );
                                            }
                                            
                                            // Tampilkan badges atau pesan kosong
                                            if(count($rme_badges) > 0) {
                                                echo '<div style="line-height: 1.8;">';
                                                foreach($rme_badges as $badge) {
                                                    echo '<span style="background: '.$badge['color'].'; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; display: inline-block; margin-right: 5px; margin-bottom: 5px; white-space: nowrap;">
                                                            <i class="material-icons" style="font-size: 12px; vertical-align: middle;">'.$badge['icon'].'</i>
                                                            '.$badge['text'].'
                                                        </span>';
                                                }
                                                echo '</div>';
                                            } else {
                                                echo '<div>
                                                        <span style="background: #9e9e9e; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; display: inline-block;">
                                                            <i class="material-icons" style="font-size: 12px; vertical-align: middle;">pending</i>
                                                            Belum Ada RME
                                                        </span>
                                                    </div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        
                        <?php
                    }
                }
                } // end else (non-RujukanInternal filters)
                ?>
            </div>
        </div>
    </div>
</div>

<!-- Include CSS & JS terpisah -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/listpasien.css">
<script src="<?php echo BASE_URL; ?>/js/listpasien.js"></script>

<script>
// ===================================================
// SEARCH PASIEN RALAN - Client-side filter
// ===================================================
(function() {
    'use strict';
    var searchInput = document.getElementById('searchPasienRalan');
    if(!searchInput) return;

    searchInput.addEventListener('input', function() {
        var keyword = this.value.toLowerCase().trim();
        var cards = document.querySelectorAll('#patientContainer .patient-card');
        var found = 0;

        cards.forEach(function(card) {
            var text = card.textContent.toLowerCase();
            if(keyword === '' || text.indexOf(keyword) > -1) {
                card.style.display = '';
                found++;
            } else {
                card.style.display = 'none';
            }
        });
    });
})();
</script>