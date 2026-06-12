<?php
// Ambil info dokter yang login dari session (DECRYPT)
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';

if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Default sebagai dokter spesialis jika tidak ada session
$is_dokter_umum = false;
$kd_sps = '';

if(!empty($kd_dokter_login)) {
    // Cek apakah dokter umum atau spesialis
    $queryDokter = bukaquery("SELECT kd_sps, nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter_login'");
    $rsDokter = mysqli_fetch_array($queryDokter);
    
    if($rsDokter) {
        $kd_sps = $rsDokter['kd_sps'];
        // ✅ GUNAKAN KONSTANTA DARI conf.php
        $is_dokter_umum = ($kd_sps == KD_DOKTER_UMUM || $kd_sps == KD_DOKTER_ANESTESI);
    }
}

// Ambil filter dari parameter GET
$filter_status = isset($_GET['filter']) ? $_GET['filter'] : 'rawat'; // Default: pasien masih dirawat

// Filter tanggal untuk pasien pulang
$tgl_pulang_dari = isset($_GET['tgl_dari']) ? $_GET['tgl_dari'] : date('Y-m-d', strtotime('-1 day')); // Default: kemarin
$tgl_pulang_sampai = isset($_GET['tgl_sampai']) ? $_GET['tgl_sampai'] : date('Y-m-d'); // Default: hari ini

// Status pulang yang valid
$status_pulang_valid = "'Sehat','Rujuk','APS','Meninggal','Sembuh','Membaik','Pulang Paksa','Atas Persetujuan Dokter','Atas Permintaan Sendiri'";

// ========================================
// SEARCH & PAGINATION (Pasien Pulang)
// ========================================
$search_pulang   = isset($_GET['search_pulang']) ? trim($_GET['search_pulang']) : '';
$page_pulang     = isset($_GET['page_pulang']) ? max(1, (int)$_GET['page_pulang']) : 1;
$per_page_pulang = 20;
$offset_pulang   = ($page_pulang - 1) * $per_page_pulang;

// Kondisi WHERE tambahan untuk search
$search_condition = '';
if (!empty($search_pulang)) {
    $search_esc = str_replace(["\\", "'", "%", "_"], ["\\\\", "\\'", "\\%", "\\_"], $search_pulang);
    $search_condition = " AND (p.nm_pasien LIKE '%$search_esc%' OR rp.no_rkm_medis LIKE '%$search_esc%') ";
}

// ========================================
// COUNT PASIEN SUDAH PULANG (Berdasarkan filter tanggal)
// ========================================
if($is_dokter_umum) {
    $query_count_pulang = bukaquery("SELECT COUNT(DISTINCT ki.no_rawat) as total 
                                     FROM kamar_inap ki
                                     INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                                     INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                     WHERE ki.stts_pulang IN ($status_pulang_valid)
                                     AND DATE(ki.tgl_keluar) BETWEEN '$tgl_pulang_dari' AND '$tgl_pulang_sampai'
                                     $search_condition");
} else {
    $query_count_pulang = bukaquery("SELECT COUNT(DISTINCT ki.no_rawat) as total 
                                     FROM kamar_inap ki
                                     INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                                     INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                     WHERE ki.stts_pulang IN ($status_pulang_valid)
                                     AND DATE(ki.tgl_keluar) BETWEEN '$tgl_pulang_dari' AND '$tgl_pulang_sampai'
                                     $search_condition
                                     AND EXISTS (
                                         SELECT 1 FROM dpjp_ranap dr 
                                         WHERE dr.no_rawat = ki.no_rawat 
                                         AND dr.kd_dokter = '$kd_dokter_login'
                                     )");
}
$count_pulang = mysqli_fetch_array($query_count_pulang)['total'];

// ========================================
// OPTIMASI: 1 QUERY UNTUK SEMUA DATA
// ========================================

if($is_dokter_umum) {
    // Dokter umum: semua pasien dengan JOIN
    // ✅ FIX 1: Ganti separator jadi '###'
    // ✅ FIX: Ambil tgl_masuk paling awal dari semua kamar (untuk pasien pindah kamar)
    $queryAllData = bukaquery("SELECT 
                                b.kd_bangsal,
                                b.nm_bangsal,
                                ki.no_rawat,
                                rp.no_rkm_medis,
                                rp.umurdaftar,
                                rp.sttsumur,
                                p.nm_pasien,
                                p.jk,
                                ki.kd_kamar,
                                ki.diagnosa_awal,
                                (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat) as tgl_masuk,
                                (SELECT MIN(TIME(jam_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat AND DATE(tgl_masuk) = (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat)) as jam_masuk,
                                DATEDIFF(CURDATE(), (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat)) as lama_rawat,
                                d1.nm_dokter as dokter_igd,
                                GROUP_CONCAT(DISTINCT d2.nm_dokter ORDER BY d2.nm_dokter SEPARATOR '###') as dpjp_ranap,
                                rg.no_rawat2,
                                pj.png_jawab
                             FROM kamar_inap ki
                             INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                             INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                             INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                             INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                             LEFT JOIN dokter d1 ON rp.kd_dokter = d1.kd_dokter
                             LEFT JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
                             LEFT JOIN dokter d2 ON dr.kd_dokter = d2.kd_dokter
                             LEFT JOIN ranap_gabung rg ON ki.no_rawat = rg.no_rawat
                             LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                             WHERE ki.stts_pulang = '-'
                             GROUP BY b.kd_bangsal, b.nm_bangsal, ki.no_rawat, rp.no_rkm_medis, 
                                      rp.umurdaftar, rp.sttsumur, p.nm_pasien, p.jk, 
                                      ki.kd_kamar, ki.diagnosa_awal,
                                      d1.nm_dokter, rg.no_rawat2, pj.png_jawab
                             ORDER BY b.nm_bangsal ASC, tgl_masuk DESC, jam_masuk DESC");
} else {
    // Dokter spesialis: hanya pasien yang dia DPJP dengan INNER JOIN (bukan EXISTS)
    // ✅ FIX 2: Ganti separator jadi '###'
    // ✅ FIX: Ambil tgl_masuk paling awal dari semua kamar (untuk pasien pindah kamar)
    $queryAllData = bukaquery("SELECT 
                                b.kd_bangsal,
                                b.nm_bangsal,
                                ki.no_rawat,
                                rp.no_rkm_medis,
                                rp.umurdaftar,
                                rp.sttsumur,
                                p.nm_pasien,
                                p.jk,
                                ki.kd_kamar,
                                ki.diagnosa_awal,
                                (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat) as tgl_masuk,
                                (SELECT MIN(TIME(jam_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat AND DATE(tgl_masuk) = (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat)) as jam_masuk,
                                DATEDIFF(CURDATE(), (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat)) as lama_rawat,
                                d1.nm_dokter as dokter_igd,
                                GROUP_CONCAT(DISTINCT d2.nm_dokter ORDER BY d2.nm_dokter SEPARATOR '###') as dpjp_ranap,
                                rg.no_rawat2,
                                pj.png_jawab
                             FROM kamar_inap ki
                             INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                             INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                             INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                             INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                             INNER JOIN dpjp_ranap dr_filter ON ki.no_rawat = dr_filter.no_rawat AND dr_filter.kd_dokter = '$kd_dokter_login'
                             LEFT JOIN dokter d1 ON rp.kd_dokter = d1.kd_dokter
                             LEFT JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
                             LEFT JOIN dokter d2 ON dr.kd_dokter = d2.kd_dokter
                             LEFT JOIN ranap_gabung rg ON ki.no_rawat = rg.no_rawat
                             LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                             WHERE ki.stts_pulang = '-'
                             GROUP BY b.kd_bangsal, b.nm_bangsal, ki.no_rawat, rp.no_rkm_medis, 
                                      rp.umurdaftar, rp.sttsumur, p.nm_pasien, p.jk, 
                                      ki.kd_kamar, ki.diagnosa_awal,
                                      d1.nm_dokter, rg.no_rawat2, pj.png_jawab
                             ORDER BY b.nm_bangsal ASC, tgl_masuk DESC, jam_masuk DESC");
}

// Group data by bangsal di PHP
$bangsalData = array();
$bangsalCount = array();

while($rs = mysqli_fetch_array($queryAllData)) {
    $kd_bangsal = $rs["kd_bangsal"];
    $nm_bangsal = $rs["nm_bangsal"];
    
    // Inisialisasi array bangsal jika belum ada
    if(!isset($bangsalData[$kd_bangsal])) {
        $bangsalData[$kd_bangsal] = array(
            'nm_bangsal' => $nm_bangsal,
            'pasien' => array()
        );
        $bangsalCount[$kd_bangsal] = 0;
    }
    
    // Tambahkan pasien ke bangsal
    $bangsalData[$kd_bangsal]['pasien'][] = $rs;
    $bangsalCount[$kd_bangsal]++;
}
?>

<!-- Panel Filter Bangsal -->
<div class="row clearfix" style="margin-bottom: 20px;">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header" style="background: linear-gradient(135deg, #5FD38D 0%, #0F6FB2 100%); color: white;">
                <h2 style="color: white; margin: 0;">
                    <i class="material-icons" style="vertical-align: middle;">filter_list</i>
                    FILTER BANGSAL
                    <?php if ($is_dokter_umum): ?>
                        <span style="background:#4caf50;padding:5px 15px;border-radius:15px;font-size:12px;margin-left:10px;">
                            DOKTER UMUM - SEMUA PASIEN
                        </span>

                    <?php elseif ($is_dokter_umum): ?>
                        <span style="background:#4caf50;padding:5px 15px;border-radius:15px;font-size:12px;margin-left:10px;">
                            DOKTER ANESTESI - SEMUA PASIEN
                        </span>

                    <?php else: ?>
                        <span style="background:#ff9800;padding:5px 15px;border-radius:15px;font-size:12px;margin-left:10px;">
                            DPJP - PASIEN SAYA
                        </span>
                    <?php endif; ?>
                </h2>
                
                <!-- Filter Status: Masih Rawat vs Sudah Pulang -->
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <?php $current_act = isset($_GET['act']) ? $_GET['act'] : 'ListPasienInap'; ?>
                    
                    <a href="index.php?act=<?php echo $current_act; ?>&filter=rawat" 
                       class="btn waves-effect" 
                       style="<?php echo ($filter_status == 'rawat') ? 'background: #4caf50; color: white;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">hotel</i>
                        Masih Dirawat
                    </a>
                    
                    <a href="index.php?act=<?php echo $current_act; ?>&filter=pulang" 
                       class="btn waves-effect" 
                       style="<?php echo ($filter_status == 'pulang') ? 'background: #9c27b0; color: white;' : 'background: white; color: #333;'; ?> border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600; font-size: 13px; transition: all 0.3s;">
                        <i class="material-icons" style="font-size: 16px; vertical-align: middle;">exit_to_app</i>
                        Sudah Pulang
                        <span style="background: <?php echo ($filter_status == 'pulang') ? 'rgba(255,255,255,0.3)' : '#9c27b0'; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; margin-left: 8px;">
                            <?php echo $count_pulang; ?>
                        </span>
                    </a>
                </div>
            </div>
            <div class="body">
                <div class="row">
                    <div class="col-md-12">
                        <?php if($filter_status == 'rawat'): ?>
                        <p style="margin-bottom: 10px; color: #666;">Pilih bangsal untuk menampilkan daftar pasien rawat inap:</p>
                        <div id="bangsalButtons" style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php 
                            foreach($bangsalData as $kd_bangsal => $data) {
                                $jumlah = $bangsalCount[$kd_bangsal];
                                echo '<button class="btn btn-bangsal waves-effect" 
                                              data-bangsal="'.$kd_bangsal.'" 
                                              onclick="filterBangsal(\''.$kd_bangsal.'\', this)"
                                              style="background: #f8f9fa; border: 2px solid #dee2e6; color: #333; border-radius: 20px; padding: 10px 20px; font-weight: 600; transition: all 0.3s ease;">
                                        <i class="material-icons" style="vertical-align: middle; font-size: 18px;">domain</i>
                                        '.$data["nm_bangsal"].'
                                        <span class="badge" style="background: #667eea; color: white; margin-left: 8px; padding: 3px 8px; border-radius: 10px; font-size: 11px;">
                                            '.$jumlah.'
                                        </span>
                                      </button>';
                            }
                            ?>
                        </div>
                        <?php else: ?>
                        <!-- Filter Tanggal untuk Pasien Pulang -->
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 15px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label style="margin: 0; font-weight: 600; color: #666; white-space: nowrap;">
                                    <i class="material-icons" style="font-size: 16px; vertical-align: middle;">date_range</i>
                                    Dari:
                                </label>
                                <input type="date" id="tgl_dari" value="<?php echo $tgl_pulang_dari; ?>" 
                                       class="form-control" style="width: 160px; border-radius: 8px; border: 2px solid #dee2e6; padding: 8px 12px;">
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <label style="margin: 0; font-weight: 600; color: #666; white-space: nowrap;">Sampai:</label>
                                <input type="date" id="tgl_sampai" value="<?php echo $tgl_pulang_sampai; ?>" 
                                       class="form-control" style="width: 160px; border-radius: 8px; border: 2px solid #dee2e6; padding: 8px 12px;">
                            </div>
                            <button type="button" onclick="filterTanggalPulang()" class="btn waves-effect" 
                                    style="background: #9c27b0; color: white; border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600;">
                                <i class="material-icons" style="font-size: 16px; vertical-align: middle;">search</i>
                                Tampilkan
                            </button>
                            
                            <!-- Quick Filter Buttons -->
                            <div style="display: flex; gap: 5px; margin-left: 10px;">
                                <button type="button" onclick="setQuickFilter('hari_ini')" class="btn btn-xs waves-effect" 
                                        style="background: #e3f2fd; color: #1976d2; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                    Hari Ini
                                </button>
                                <button type="button" onclick="setQuickFilter('kemarin')" class="btn btn-xs waves-effect" 
                                        style="background: #e3f2fd; color: #1976d2; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                    Kemarin
                                </button>
                                <button type="button" onclick="setQuickFilter('minggu_ini')" class="btn btn-xs waves-effect" 
                                        style="background: #e3f2fd; color: #1976d2; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                    Minggu Ini
                                </button>
                                <button type="button" onclick="setQuickFilter('bulan_ini')" class="btn btn-xs waves-effect" 
                                        style="background: #e3f2fd; color: #1976d2; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                    Bulan Ini
                                </button>
                            </div>
                        </div>
                        <p style="margin-top: 10px; margin-bottom: 0; color: #666; font-size: 12px;">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">info</i>
                            Menampilkan pasien pulang periode: <strong><?php echo date('d/m/Y', strtotime($tgl_pulang_dari)); ?></strong> s/d <strong><?php echo date('d/m/Y', strtotime($tgl_pulang_sampai)); ?></strong>
                        </p>
                        <!-- Search Box Pasien Pulang -->
                        <div style="margin-top: 12px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <div style="position: relative; flex: 1; min-width: 220px; max-width: 400px;">
                                <i class="material-icons" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #9c27b0; font-size: 18px; pointer-events: none;"></i>
                                <input type="text" id="search_pulang" 
                                       value="<?php echo htmlspecialchars($search_pulang); ?>"
                                       placeholder="Cari nama pasien / No. RM..."
                                       class="form-control"
                                       style="padding-left: 36px; border-radius: 20px; border: 2px solid #ce93d8; font-size: 13px; height: 38px;"
                                       onkeydown="if(event.key==='Enter') filterTanggalPulang()">
                            </div>
                            <button type="button" onclick="filterTanggalPulang()" class="btn btn-xs waves-effect"
                                    style="background: #9c27b0; color: white; border: none; border-radius: 20px; padding: 8px 18px; font-size: 12px; font-weight: 600; white-space: nowrap;">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">search</i> Cari
                            </button>
                            <?php if(!empty($search_pulang)): ?>
                            <a href="index.php?act=<?php echo $current_act; ?>&filter=pulang&tgl_dari=<?php echo $tgl_pulang_dari; ?>&tgl_sampai=<?php echo $tgl_pulang_sampai; ?>"
                               class="btn btn-xs waves-effect"
                               style="background: #f44336; color: white; border: none; border-radius: 20px; padding: 8px 14px; font-size: 12px; font-weight: 600; white-space: nowrap;">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">close</i> Reset
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Container untuk Data Pasien -->
<div class="row clearfix" id="patientContainer">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <?php
        if($filter_status == 'pulang') {
        // Query pasien sudah pulang (hari ini & kemarin)
        if($is_dokter_umum) {
            $queryPulang = bukaquery("SELECT 
                                        ki.no_rawat,
                                        rp.no_rkm_medis,
                                        rp.umurdaftar,
                                        rp.sttsumur,
                                        p.nm_pasien,
                                        p.jk,
                                        ki.kd_kamar,
                                        ki.diagnosa_awal,
                                        ki.diagnosa_akhir,
                                        ki.tgl_masuk,
                                        ki.jam_masuk,
                                        ki.tgl_keluar,
                                        ki.jam_keluar,
                                        ki.stts_pulang,
                                        ki.lama,
                                        b.nm_bangsal,
                                        d1.nm_dokter as dokter_igd,
                                        GROUP_CONCAT(DISTINCT d2.nm_dokter ORDER BY d2.nm_dokter SEPARATOR '###') as dpjp_ranap,
                                        pj.png_jawab
                                     FROM kamar_inap ki
                                     INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                                     INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                                     INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                                     INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                     LEFT JOIN dokter d1 ON rp.kd_dokter = d1.kd_dokter
                                     LEFT JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
                                     LEFT JOIN dokter d2 ON dr.kd_dokter = d2.kd_dokter
                                     LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                                     WHERE ki.stts_pulang IN ($status_pulang_valid)
                                     AND DATE(ki.tgl_keluar) BETWEEN '$tgl_pulang_dari' AND '$tgl_pulang_sampai'
                                     $search_condition
                                     GROUP BY ki.no_rawat, rp.no_rkm_medis, rp.umurdaftar, rp.sttsumur, 
                                              p.nm_pasien, p.jk, ki.kd_kamar, ki.diagnosa_awal, ki.diagnosa_akhir,
                                              ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar,
                                              ki.stts_pulang, ki.lama, b.nm_bangsal, d1.nm_dokter, pj.png_jawab
                                     ORDER BY ki.tgl_keluar DESC, ki.jam_keluar DESC
                                     LIMIT $per_page_pulang OFFSET $offset_pulang");
        } else {
            $queryPulang = bukaquery("SELECT 
                                        ki.no_rawat,
                                        rp.no_rkm_medis,
                                        rp.umurdaftar,
                                        rp.sttsumur,
                                        p.nm_pasien,
                                        p.jk,
                                        ki.kd_kamar,
                                        ki.diagnosa_awal,
                                        ki.diagnosa_akhir,
                                        ki.tgl_masuk,
                                        ki.jam_masuk,
                                        ki.tgl_keluar,
                                        ki.jam_keluar,
                                        ki.stts_pulang,
                                        ki.lama,
                                        b.nm_bangsal,
                                        d1.nm_dokter as dokter_igd,
                                        GROUP_CONCAT(DISTINCT d2.nm_dokter ORDER BY d2.nm_dokter SEPARATOR '###') as dpjp_ranap,
                                        pj.png_jawab
                                     FROM kamar_inap ki
                                     INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                                     INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                                     INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                                     INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                                     LEFT JOIN dokter d1 ON rp.kd_dokter = d1.kd_dokter
                                     LEFT JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
                                     LEFT JOIN dokter d2 ON dr.kd_dokter = d2.kd_dokter
                                     LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                                     WHERE ki.stts_pulang IN ($status_pulang_valid)
                                     AND DATE(ki.tgl_keluar) BETWEEN '$tgl_pulang_dari' AND '$tgl_pulang_sampai'
                                     $search_condition
                                     AND EXISTS (
                                         SELECT 1 FROM dpjp_ranap dr_check 
                                         WHERE dr_check.no_rawat = ki.no_rawat 
                                         AND dr_check.kd_dokter = '$kd_dokter_login'
                                     )
                                     GROUP BY ki.no_rawat, rp.no_rkm_medis, rp.umurdaftar, rp.sttsumur, 
                                              p.nm_pasien, p.jk, ki.kd_kamar, ki.diagnosa_awal, ki.diagnosa_akhir,
                                              ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar,
                                              ki.stts_pulang, ki.lama, b.nm_bangsal, d1.nm_dokter, pj.png_jawab
                                     ORDER BY ki.tgl_keluar DESC, ki.jam_keluar DESC
                                     LIMIT $per_page_pulang OFFSET $offset_pulang");
        }
        
        $jumlah_pulang = mysqli_num_rows($queryPulang);
        // Total keseluruhan (tanpa LIMIT) sudah dihitung di $count_pulang
        $total_pulang_all = (int)$count_pulang;
        $total_pages_pulang = ceil($total_pulang_all / $per_page_pulang);
        
        if($jumlah_pulang == 0) {
            echo '<div class="alert alert-info" style="text-align: center; padding: 40px;">
                    <i class="material-icons" style="font-size: 64px; color: #667eea;">info</i>
                    <h4 style="margin-top: 15px;">Tidak ada pasien yang sudah pulang</h4>
                    <p style="color: #999;">Belum ada pasien yang pulang hari ini atau kemarin</p>
                  </div>';
        } else {
            // Header
            echo '<div style="background: linear-gradient(135deg, #0F6FB2 0%, #5FD38D 100%); color: white; padding: 15px 20px; border-radius: 10px 10px 0 0; margin-bottom: 0;">
                    <h3 style="margin: 0; font-size: 18px; font-weight: 600;">
                        <i class="material-icons" style="vertical-align: middle; font-size: 22px;">exit_to_app</i>
                        PASIEN SUDAH PULANG
                        <span style="background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 10px; font-size: 12px; margin-left: 10px;">'.$total_pulang_all.' Pasien</span>
                        '.(!empty($search_pulang) ? '<span style="background: rgba(255,255,100,0.3); padding: 3px 10px; border-radius: 10px; font-size: 11px; margin-left: 6px;"><i class="material-icons" style="font-size: 13px; vertical-align: middle;">search</i> Filter: '.htmlspecialchars($search_pulang).'</span>' : '').'
                    </h3>
                  </div>';
            echo '<div style="background: white; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 10px;">';
            
            while($rs = mysqli_fetch_array($queryPulang)) {
                // Enkripsi parameter
                $encrypted_norawat = urlencode(encrypt_decrypt($rs["no_rawat"], "e"));
                $encrypted_norm = urlencode(encrypt_decrypt($rs["no_rkm_medis"], "e"));
                
                // Warna badge berdasarkan status pulang
                $stts_pulang = $rs["stts_pulang"];
                switch($stts_pulang) {
                    case 'Sehat':
                    case 'Sembuh':
                    case 'Membaik':
                        $badge_color = "#4caf50"; // Hijau
                        break;
                    case 'Rujuk':
                        $badge_color = "#ff9800"; // Orange
                        break;
                    case 'Meninggal':
                        $badge_color = "#f44336"; // Merah
                        break;
                    case 'APS':
                    case 'Pulang Paksa':
                    case 'Atas Permintaan Sendiri':
                        $badge_color = "#9e9e9e"; // Abu-abu
                        break;
                    default:
                        $badge_color = "#2196f3"; // Biru
                }
                
                // Avatar image based on gender
                $avatar_img = ($rs["jk"] == "L") ? "images/male.png" : "images/female.png";
                
                // Format umur
                $umur = $rs["umurdaftar"] . " " . $rs["sttsumur"];
                
                // DPJP list
                $dpjp_list = $rs["dpjp_ranap"] ? explode('###', $rs["dpjp_ranap"]) : array();
                
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
                                        <h4 style="margin: 0; font-size: 15px; font-weight: 600; color: #333;">
                                            <?php echo strtoupper($rs["nm_pasien"]); ?>
                                        </h4>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <!-- Badge Status Pulang -->
                                            <span style="background: <?php echo $badge_color; ?>; color: white; padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: 600;">
                                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">exit_to_app</i>
                                                <?php echo $stts_pulang; ?>
                                            </span>
                                            <!-- Button Aksi -->
                                            <div class="dropdown-pasien" style="display: inline-block; position: relative;">
                                                <button class="btn btn-primary btn-xs dropdown-pasien-toggle waves-effect" 
                                                        type="button" 
                                                        style="background: #9c27b0; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                                    Aksi <span class="caret"></span>
                                                </button>
                                                <ul class="dropdown-pasien-menu">
                                                    <li><a href="index.php?act=Pemeriksaanriwayat&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Riwayat Perawatan</a></li>
                                                    <li><a href="index.php?act=ResumeMedisInap&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>"><i class="material-icons" style="font-size: 14px; vertical-align: middle; margin-right: 5px;">assignment</i>Resume Medis</a></li>
                                                    <li><a href="index.php?act=Obatpulang&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Resep Pulang</a></li>                                                                     <?php if(cekAksesMenu('pasien_meninggal')): ?>
                                                    <li class="has-submenu">
                                                        <a href="#" class="submenu-trigger">Status Pasien</a>
                                                        <ul class="dropdown-submenu">
                                                            <?php if(cekAksesMenu('pasien_meninggal')): ?>
                                                            <li><a href="index.php?act=pasienmeninggal&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Pasien Meninggal</a></li>
                                                            <?php endif; ?> 
                                                        </ul>
                                                    </li>
                                                    <?php endif; ?>                                             
                                                </ul>                                      
                                            </div>
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
                                <div style="color: #999; font-size: 11px; margin-bottom: 6px;">
                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">login</i>
                                    Masuk: <?php echo date('d/m/Y H:i', strtotime($rs["tgl_masuk"].' '.$rs["jam_masuk"])); ?>
                                </div>
                                <div style="color: #9c27b0; font-size: 11px; font-weight: 600; margin-bottom: 6px;">
                                    <i class="material-icons" style="font-size: 12px; vertical-align: middle;">logout</i>
                                    Keluar: <?php echo date('d/m/Y H:i', strtotime($rs["tgl_keluar"].' '.$rs["jam_keluar"])); ?>
                                </div>
                                <div>
                                    <span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                        <?php echo $rs["nm_bangsal"]; ?> - <?php echo $rs["kd_kamar"]; ?>
                                    </span>
                                    <span style="background: #ffecb3; color: #ff6f00; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 5px;">
                                        <?php echo $rs["lama"]; ?> Hari
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-sm-4">
                            <?php if($rs["dokter_igd"]): ?>
                            <div style="margin-bottom: 8px;">
                                <span style="background: #4caf50; color: white; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 600; margin-right: 5px;">IGD</span>
                                <span style="font-size: 12px; color: #666;"><?php echo $rs["dokter_igd"]; ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <?php if(count($dpjp_list) > 0 && $dpjp_list[0] != ''): ?>
                                    <?php foreach($dpjp_list as $dpjp): ?>
                                    <div style="margin-bottom: 5px;">
                                        <span style="background: #667eea; color: white; padding: 3px 8px; border-radius: 8px; font-size: 8px; font-weight: 600; margin-right: 5px;">DPJP</span>
                                        <strong style="font-size: 12px; color: #666;"><?php echo trim($dpjp); ?></strong>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <div>
                                    <span style="background: #f44336; color: white; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 600;">DPJP: -</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php
            }
            echo '</div>';
            
            // =============================================
            // PAGINATION
            // =============================================
            if($total_pages_pulang > 1) {
                $base_url_params = http_build_query([
                    'act'        => isset($_GET['act']) ? $_GET['act'] : 'ListPasienInap',
                    'filter'     => 'pulang',
                    'tgl_dari'   => $tgl_pulang_dari,
                    'tgl_sampai' => $tgl_pulang_sampai,
                    'search_pulang' => $search_pulang,
                ]);
                
                echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; flex-wrap: wrap; gap: 10px; padding: 0 5px;">';
                
                // Info halaman
                $from_record = $offset_pulang + 1;
                $to_record   = min($offset_pulang + $per_page_pulang, $total_pulang_all);
                echo '<div style="font-size: 12px; color: #666;">
                        Menampilkan <strong>'.$from_record.'–'.$to_record.'</strong> dari <strong>'.$total_pulang_all.'</strong> pasien
                      </div>';
                
                // Tombol pagination
                echo '<div style="display: flex; gap: 5px; flex-wrap: wrap;">';
                
                // Prev
                if($page_pulang > 1) {
                    echo '<a href="index.php?'.$base_url_params.'&page_pulang='.($page_pulang-1).'" 
                              style="display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; background: #9c27b0; color: white; text-decoration: none; font-size: 12px; font-weight: 600;">
                              <i class="material-icons" style="font-size: 16px;">chevron_left</i> Prev
                          </a>';
                } else {
                    echo '<span style="display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; background: #e0e0e0; color: #999; font-size: 12px; font-weight: 600; cursor: not-allowed;">
                              <i class="material-icons" style="font-size: 16px;">chevron_left</i> Prev
                          </span>';
                }
                
                // Nomor halaman (tampil max 5 halaman sekitar current)
                $window = 2;
                $start_p = max(1, $page_pulang - $window);
                $end_p   = min($total_pages_pulang, $page_pulang + $window);
                
                if($start_p > 1) echo '<span style="padding: 6px 8px; font-size: 12px; color: #999;">…</span>';
                
                for($p = $start_p; $p <= $end_p; $p++) {
                    if($p == $page_pulang) {
                        echo '<span style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #9c27b0; color: white; font-size: 12px; font-weight: 700;">'.$p.'</span>';
                    } else {
                        echo '<a href="index.php?'.$base_url_params.'&page_pulang='.$p.'" 
                                  style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; background: #f3e5f5; color: #9c27b0; text-decoration: none; font-size: 12px; font-weight: 600;">'.$p.'</a>';
                    }
                }
                
                if($end_p < $total_pages_pulang) echo '<span style="padding: 6px 8px; font-size: 12px; color: #999;">…</span>';
                
                // Next
                if($page_pulang < $total_pages_pulang) {
                    echo '<a href="index.php?'.$base_url_params.'&page_pulang='.($page_pulang+1).'" 
                              style="display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; background: #9c27b0; color: white; text-decoration: none; font-size: 12px; font-weight: 600;">
                              Next <i class="material-icons" style="font-size: 16px;">chevron_right</i>
                          </a>';
                } else {
                    echo '<span style="display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; background: #e0e0e0; color: #999; font-size: 12px; font-weight: 600; cursor: not-allowed;">
                              Next <i class="material-icons" style="font-size: 16px;">chevron_right</i>
                          </span>';
                }
                
                echo '</div></div>'; // tutup tombol + wrapper pagination
            }
        } // end else ($jumlah_pulang > 0)

        } else {
            echo '<div class="alert alert-info" style="text-align: center; padding: 40px;">
                    <i class="material-icons" style="font-size: 64px; color: #667eea;">info</i>
                    <h4 style="margin-top: 15px;">Silakan pilih bangsal untuk menampilkan daftar pasien</h4>
                    <p style="color: #999;">Klik salah satu tombol bangsal di atas</p>
                  </div>';
        }
        ?>
    </div>
</div>

<?php 
// Query untuk ambil daftar bangsal dengan data pasien
if($is_dokter_umum) {
    // Dokter umum: semua pasien
    $queryBangsal = bukaquery("SELECT DISTINCT 
                                b.kd_bangsal,
                                b.nm_bangsal
                             FROM kamar_inap ki
                             INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                             INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                             WHERE ki.stts_pulang = '-'
                             ORDER BY b.nm_bangsal ASC");
} else {
    // Dokter spesialis: hanya pasien yang dia DPJP
    $queryBangsal = bukaquery("SELECT DISTINCT 
                                b.kd_bangsal,
                                b.nm_bangsal
                             FROM kamar_inap ki
                             INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                             INNER JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                             WHERE ki.stts_pulang = '-'
                             AND EXISTS (
                                 SELECT 1 FROM dpjp_ranap dr 
                                 WHERE dr.no_rawat = ki.no_rawat 
                                 AND dr.kd_dokter = '$kd_dokter_login'
                             )
                             ORDER BY b.nm_bangsal ASC");
}

$bangsalData = array();
while($rsBangsal = mysqli_fetch_array($queryBangsal)) {
    $kd_bangsal = $rsBangsal["kd_bangsal"];
    $nm_bangsal = $rsBangsal["nm_bangsal"];
    
    // Query pasien per bangsal - dengan filter DPJP jika spesialis
if($is_dokter_umum) {
    // Dokter umum: semua pasien
    // ✅ FIX 3: Ganti separator jadi '###'
    // ✅ FIX: Ambil tgl_masuk paling awal dari semua kamar (untuk pasien pindah kamar)
    $querypasien = bukaquery("SELECT 
                                ki.no_rawat,
                                rp.no_rkm_medis,
                                rp.umurdaftar,
                                rp.sttsumur,
                                p.nm_pasien,
                                p.jk,
                                ki.kd_kamar,
                                ki.diagnosa_awal,
                                (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat) as tgl_masuk,
                                (SELECT MIN(TIME(jam_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat AND DATE(tgl_masuk) = (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat)) as jam_masuk,
                                ki.lama,
                                d1.nm_dokter as dokter_igd,
                                GROUP_CONCAT(DISTINCT d2.nm_dokter ORDER BY d2.nm_dokter SEPARATOR '###') as dpjp_ranap,
                                rg.no_rawat2,
                                pj.png_jawab
                             FROM kamar_inap ki
                             INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                             INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                             INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                             LEFT JOIN dokter d1 ON rp.kd_dokter = d1.kd_dokter
                             LEFT JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
                             LEFT JOIN dokter d2 ON dr.kd_dokter = d2.kd_dokter
                             LEFT JOIN ranap_gabung rg ON ki.no_rawat = rg.no_rawat
                             LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                             WHERE ki.stts_pulang = '-' 
                             AND k.kd_bangsal = '$kd_bangsal'
                             GROUP BY ki.no_rawat, rp.no_rkm_medis, rp.umurdaftar, rp.sttsumur, 
                                      p.nm_pasien, p.jk, ki.kd_kamar, ki.diagnosa_awal, 
                                      ki.lama, d1.nm_dokter, rg.no_rawat2, pj.png_jawab
                             ORDER BY tgl_masuk DESC, jam_masuk DESC");
} else {
    // Dokter spesialis: hanya pasien yang dia DPJP (pakai EXISTS untuk avoid duplicate)
    // ✅ FIX 4: Ganti separator jadi '###'
    // ✅ FIX: Ambil tgl_masuk paling awal dari semua kamar (untuk pasien pindah kamar)
    $querypasien = bukaquery("SELECT 
                                ki.no_rawat,
                                rp.no_rkm_medis,
                                rp.umurdaftar,
                                rp.sttsumur,
                                p.nm_pasien,
                                p.jk,
                                ki.kd_kamar,
                                ki.diagnosa_awal,
                                (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat) as tgl_masuk,
                                (SELECT MIN(TIME(jam_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat AND DATE(tgl_masuk) = (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = ki.no_rawat)) as jam_masuk,
                                ki.lama,
                                d1.nm_dokter as dokter_igd,
                                GROUP_CONCAT(DISTINCT d2.nm_dokter ORDER BY d2.nm_dokter SEPARATOR '###') as dpjp_ranap,
                                rg.no_rawat2,
                                pj.png_jawab
                             FROM kamar_inap ki
                             INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                             INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                             INNER JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                             LEFT JOIN dokter d1 ON rp.kd_dokter = d1.kd_dokter
                             LEFT JOIN dpjp_ranap dr ON ki.no_rawat = dr.no_rawat
                             LEFT JOIN dokter d2 ON dr.kd_dokter = d2.kd_dokter
                             LEFT JOIN ranap_gabung rg ON ki.no_rawat = rg.no_rawat
                             LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                             WHERE ki.stts_pulang = '-' 
                             AND k.kd_bangsal = '$kd_bangsal'
                             AND EXISTS (
                                 SELECT 1 FROM dpjp_ranap dr_check 
                                 WHERE dr_check.no_rawat = ki.no_rawat 
                                 AND dr_check.kd_dokter = '$kd_dokter_login'
                             )
                             GROUP BY ki.no_rawat, rp.no_rkm_medis, rp.umurdaftar, rp.sttsumur, 
                                      p.nm_pasien, p.jk, ki.kd_kamar, ki.diagnosa_awal, 
                                      ki.lama, d1.nm_dokter, rg.no_rawat2, pj.png_jawab
                             ORDER BY tgl_masuk DESC, jam_masuk DESC");
}
    
    ob_start();
    
    while($rs = mysqli_fetch_array($querypasien)) {
        // Enkripsi parameter
        $encrypted_norawat = urlencode(encrypt_decrypt($rs["no_rawat"], "e"));
        $encrypted_norm = urlencode(encrypt_decrypt($rs["no_rkm_medis"], "e"));
        
        // Status badge berdasarkan lama rawat
        $lama = $rs["lama"] ?: 0;
        if($lama <= 3) {
            $badge_color = "#4caf50";
        } elseif($lama <= 7) {
            $badge_color = "#00bcd4";
        } else {
            $badge_color = "#ff9800";
        }
        
        // Avatar image based on gender
        $avatar_img = ($rs["jk"] == "L") ? "images/male.png" : "images/female.png";
        
        // Format umur
        $umur = $rs["umurdaftar"] . " " . $rs["sttsumur"];
        
        // ✅ FIX PHP: Split DPJP dengan separator '###'
        $dpjp_list = $rs["dpjp_ranap"] ? explode('###', $rs["dpjp_ranap"]) : array();
        
        // Cek apakah ada ranap gabung (bayi)
        $has_bayi = !empty($rs["no_rawat2"]);
        $bayi_info = null;
        
        if($has_bayi) {
            $queryBayi = bukaquery("SELECT 
                                        rp2.no_rawat,
                                        rp2.no_rkm_medis,
                                        p2.nm_pasien,
                                        rp2.umurdaftar,
                                        rp2.sttsumur
                                    FROM reg_periksa rp2
                                    INNER JOIN pasien p2 ON rp2.no_rkm_medis = p2.no_rkm_medis
                                    WHERE rp2.no_rawat = '".$rs["no_rawat2"]."'");
            $bayi_info = mysqli_fetch_array($queryBayi);
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
                        <h4 style="margin: 0; font-size: 15px; font-weight: 600; color: #333; display: inline-block;">
                        <a href="index.php?act=PemeriksaanInap&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>" 
                               style="color: #1976d2; text-decoration: none;"
                               onmouseover="this.style.textDecoration='underline'" 
                               onmouseout="this.style.textDecoration='none'">
                                <?php echo strtoupper($rs["nm_pasien"]); ?>
                                                </a>
                                            </h4>
                                            <?php if($has_bayi && $bayi_info): ?>
                                            <span style="background: #ff6b9d; color: white; padding: 2px 6px; border-radius: 8px; font-size: 9px; font-weight: 600; margin-left: 5px;">IBU</span>
                                            <?php endif; ?>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <div class="dropdown-pasien" style="display: inline-block; position: relative;">
                                        <button class="btn btn-primary btn-xs dropdown-pasien-toggle waves-effect" 
                                                type="button" 
                                                style="background: #26c6da; border: none; border-radius: 15px; padding: 5px 12px; font-size: 11px; font-weight: 600;">
                                            Aksi <span class="caret"></span>
                                        </button>
                                        <ul class="dropdown-pasien-menu">
                                            <li><a href="index.php?act=PemeriksaanInap&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Pemeriksaan</a></li>
                                            <!-- <?php if(cekAksesMenu('konsultasi_medik')): ?>
                                            <li><a href="index.php?act=Konsultasimedik&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Konsultasi Medik</a></li>
                                            <?php endif; ?>
                                            <li><a href="index.php?act=ClinicalPathway&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Integrated Care Pathway</a></li> -->
                                            <li><a href="index.php?act=Obatpulang&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Resep Pulang</a></li> 
                                            <?php if(cekAksesMenu('surat_sakit') || cekAksesMenu('surat_keterangan_rawat_inap')): ?>
                                            <li class="has-submenu">
                                                <a href="#" class="submenu-trigger">Surat Keterangan</a>
                                                <ul class="dropdown-submenu">
                                                    <?php if(cekAksesMenu('surat_sakit')): ?>
                                                    <li><a href="index.php?act=suratsakit&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Surat Sakit</a></li>
                                                    <?php endif; ?>
                                                    <?php if(cekAksesMenu('surat_keterangan_rawat_inap')): ?>
                                                    <li><a href="index.php?act=suratketeranganrawatinap&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>" >Surat Keterangan Rawat Inap</a></li>
                                                    <?php endif; ?>    
                                                </ul>
                                            </li>
                                            <?php endif; ?>  
                                            <li class="divider"></li>
                                            <li><a href="index.php?act=ResumeMedisInap&rnw=<?php echo $encrypted_norawat; ?>&rm=<?php echo $encrypted_norm; ?>">Resume Medis</a></li>
                                        </ul>
                                    </div>
                                    <button class="btn btn-info btn-xs btn-lihat-detail waves-effect" 
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
                            <?php if($has_bayi && $bayi_info): 
                                // Enkripsi parameter untuk bayi
                                $encrypted_norawat_bayi = urlencode(encrypt_decrypt($bayi_info["no_rawat"], "e"));
                                $encrypted_norm_bayi = urlencode(encrypt_decrypt($bayi_info["no_rkm_medis"], "e"));
                            ?>
                            <div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 5px;">
                                <div style="display: flex; align-items: center; justify-content: space-between;">
                                    <div style="flex: 1; min-width: 0;">
                                        <div style="font-size: 11px; color: #856404; margin-bottom: 3px;">
                                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">child_care</i>
                                            <strong><?php echo strtoupper($bayi_info["nm_pasien"]); ?></strong>
                                            <span style="margin-left: 5px;">(<?php echo $bayi_info["umurdaftar"]." ".$bayi_info["sttsumur"]; ?>)</span>
                                        </div>
                                        <div style="font-size: 10px; color: #856404; margin-bottom: 3px;">
                                            <strong>No. RM:</strong> <?php echo $bayi_info["no_rkm_medis"]; ?>
                                        </div>
                                        <div style="font-size: 10px; color: #856404;">
                                            <strong>No. Rawat:</strong> <?php echo $rs["no_rawat2"]; ?>
                                        </div>
                                    </div>
                                    <div class="dropdown-pasien" style="display: inline-block; position: relative; margin-left: 10px; flex-shrink: 0;">
                                        <button class="btn btn-warning btn-xs dropdown-pasien-toggle waves-effect" 
                                                type="button" 
                                                style="background: #ff9800; border: none; border-radius: 15px; padding: 4px 10px; font-size: 10px; font-weight: 600; color: white;">
                                            Aksi <span class="caret"></span>
                                        </button>
                                        <ul class="dropdown-pasien-menu">
                                            <li><a href="index.php?act=PemeriksaanInap&rnw=<?php echo $encrypted_norawat_bayi; ?>&rm=<?php echo $encrypted_norm_bayi; ?>">Pemeriksaan</a></li>
                                            <li><a href="index.php?act=ResumeMedisInap&rnw=<?php echo $encrypted_norawat_bayi; ?>&rm=<?php echo $encrypted_norm_bayi; ?>">Resume Medis</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-sm-4">
    <div style="font-size: 13px;">
        <div style="margin-bottom: 6px;">
            <i class="material-icons" style="font-size: 14px; vertical-align: middle; color: #666;">access_time</i>
            <strong style="color: #666;"><?php echo $umur; ?></strong>
            <?php
            // Badge jenis pembayaran
            $png_jawab = $rs["png_jawab"] ?? '-';
            // Tentukan warna badge pembayaran
            if(stripos($png_jawab, 'BPJS') !== false || stripos($png_jawab, 'JKN') !== false) {
                $pj_color = "#4caf50"; // Hijau untuk BPJS
            } elseif(stripos($png_jawab, 'UMUM') !== false) {
                $pj_color = "#2196f3"; // Biru untuk Umum
            } elseif(stripos($png_jawab, 'ASURANSI') !== false || stripos($png_jawab, 'JASARAHARJA') !== false) {
                $pj_color = "#ff9800"; // Orange untuk Asuransi
            } else {
                $pj_color = "#9e9e9e"; // Abu-abu untuk lainnya
            }
            ?>
            <span style="background: <?php echo $pj_color; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; margin-left: 5px;">
                <?php echo strtoupper($png_jawab); ?>
            </span>
        </div>
        <div style="color: #999; font-size: 11px; margin-bottom: 6px;">
            TGL_REG: <?php echo date('d/m/Y, H:i', strtotime($rs["tgl_masuk"].' '.$rs["jam_masuk"])); ?>
        </div>
        <div>
            <span style="background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                Kamar: <?php echo $rs["kd_kamar"]; ?>
            </span>
            <?php
            // Hitung lama rawat (hari)
            $tgl_masuk = $rs["tgl_masuk"];
            $datetime_masuk = new DateTime($tgl_masuk);
            $datetime_now = new DateTime();
            $interval = $datetime_masuk->diff($datetime_now);
            $lama_rawat = $interval->days;
            
            // Tentukan warna badge berdasarkan lama rawat
            if($lama_rawat >= 1 && $lama_rawat <= 3) {
                $badge_color = "#4caf50"; // Hijau
                $badge_text_color = "white";
            } elseif($lama_rawat >= 4 && $lama_rawat <= 6) {
                $badge_color = "#ff9800"; // Kuning/Orange
                $badge_text_color = "white";
            } elseif($lama_rawat >= 7) {
                $badge_color = "#f44336"; // Merah
                $badge_text_color = "white";
            } else {
                $badge_color = "#9e9e9e"; // Abu-abu (0 hari)
                $badge_text_color = "white";
            }
            ?>
            <span style="background: <?php echo $badge_color; ?>; color: <?php echo $badge_text_color; ?>; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-left: 5px;">
                <i class="material-icons" style="font-size: 12px; vertical-align: middle;">calendar_today</i>
                <?php echo $lama_rawat; ?> Hari
            </span>
        </div>
    </div>
</div>
                
                <div class="col-sm-4">
                    <?php if($rs["dokter_igd"]): ?>
                    <div style="margin-bottom: 8px;">
                        <span style="background: #4caf50; color: white; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 600; margin-right: 5px;">
                            IGD
                        </span>
                        <span style="font-size: 12px; color: #666;"><?php echo $rs["dokter_igd"]; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <?php if(count($dpjp_list) > 0 && $dpjp_list[0] != ''): ?>
                            <?php foreach($dpjp_list as $dpjp): ?>
                            <div style="margin-bottom: 5px;">
                                <span style="background: #667eea; color: white; padding: 3px 8px; border-radius: 8px; font-size: 8px; font-weight: 600; margin-right: 5px;">
                                    DPJP
                                </span>
                                <strong style="font-size: 12px; color: #666;"><?php echo trim($dpjp); ?></strong>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div>
                            <span style="background: #f44336; color: white; padding: 3px 8px; border-radius: 8px; font-size: 10px; font-weight: 600;">
                                DPJP: -
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Container untuk Detail Pasien (AJAX Load) -->
        <div class="detail-inap-wrapper" id="detail-<?php echo md5($rs['no_rawat']); ?>" style="display: none; margin-bottom: 15px;"></div>
        
        <?php
    }
    
    $pasienList = ob_get_clean();
    
    $bangsalData[$kd_bangsal] = array(
        'nm_bangsal' => $nm_bangsal,
        'html' => $pasienList
    );
}
?>

<!-- Include CSS & JS terpisah -->
<link rel="stylesheet" href="<?php echo APP_BASE_URL; ?>/css/listpasieninap.css">
<script src="<?php echo APP_BASE_URL; ?>/js/listpasieninap.js"></script>
<script>
// Set data bangsal dari PHP ke JavaScript
setBangsalData(<?php echo json_encode($bangsalData); ?>);

// Override filterTanggalPulang agar ikut mengirim parameter search & reset page ke 1
function filterTanggalPulang() {
    var tglDari    = document.getElementById('tgl_dari')    ? document.getElementById('tgl_dari').value    : '';
    var tglSampai  = document.getElementById('tgl_sampai')  ? document.getElementById('tgl_sampai').value  : '';
    var searchVal  = document.getElementById('search_pulang') ? document.getElementById('search_pulang').value : '';
    var currentAct = '<?php echo isset($_GET['act']) ? htmlspecialchars($_GET['act']) : 'ListPasienInap'; ?>';
    
    var url = 'index.php?act=' + currentAct
            + '&filter=pulang'
            + '&tgl_dari='      + encodeURIComponent(tglDari)
            + '&tgl_sampai='    + encodeURIComponent(tglSampai)
            + '&search_pulang=' + encodeURIComponent(searchVal)
            + '&page_pulang=1';
    window.location.href = url;
}

// Override setQuickFilter agar preserve search dan reset page ke 1
function setQuickFilter(type) {
    var today  = new Date();
    var tglDari, tglSampai;
    
    function fmt(d) {
        var mm = String(d.getMonth()+1).padStart(2,'0');
        var dd = String(d.getDate()).padStart(2,'0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }
    
    if(type === 'hari_ini') {
        tglDari = tglSampai = fmt(today);
    } else if(type === 'kemarin') {
        var kem = new Date(today); kem.setDate(kem.getDate()-1);
        tglDari = tglSampai = fmt(kem);
    } else if(type === 'minggu_ini') {
        var dayOfWeek = today.getDay(); // 0=Sun
        var mon = new Date(today); mon.setDate(today.getDate() - ((dayOfWeek+6)%7));
        tglDari = fmt(mon); tglSampai = fmt(today);
    } else if(type === 'bulan_ini') {
        tglDari  = today.getFullYear() + '-' + String(today.getMonth()+1).padStart(2,'0') + '-01';
        tglSampai = fmt(today);
    }
    
    if(document.getElementById('tgl_dari'))   document.getElementById('tgl_dari').value   = tglDari;
    if(document.getElementById('tgl_sampai')) document.getElementById('tgl_sampai').value = tglSampai;
    
    filterTanggalPulang();
}
</script>