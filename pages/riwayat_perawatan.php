<?php
include "../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");


// Get parameters
$no_rm = isset($_REQUEST['no_rm']) ? trim($_REQUEST['no_rm']) : '';
$no_rawat = isset($_REQUEST['no_rawat']) ? trim($_REQUEST['no_rawat']) : '';
$no_rawat_aktif = isset($_REQUEST['no_rawat_aktif']) ? trim($_REQUEST['no_rawat_aktif']) : '';
$ajax_reload = isset($_REQUEST['ajax_reload']) ? $_REQUEST['ajax_reload'] : '';

if (empty($no_rm)) {
    echo '<div class="alert alert-warning m-3">No. RM tidak ditemukan</div>';
    exit;
}

// Query data pasien
$query_pasien = "SELECT no_rkm_medis, nm_pasien, jk, 
                 CONCAT(tmp_lahir, ', ', DATE_FORMAT(tgl_lahir, '%d-%m-%Y')) as tmp_tgl_lahir,
                 TIMESTAMPDIFF(YEAR, tgl_lahir, CURDATE()) as umur
                 FROM pasien 
                 WHERE no_rkm_medis = '$no_rm'";
$result_pasien = bukaquery_safe($query_pasien);
$pasien = mysqli_fetch_assoc($result_pasien);

if (!$pasien) {
    echo '<div class="alert alert-danger m-3">Data pasien tidak ditemukan</div>';
    exit;
}

// =====================================================
// QUERY LIST NO_RAWAT UNTUK DROPDOWN
// =====================================================
$query_list_norawat = "
    SELECT DISTINCT rp.no_rawat, 
           DATE_FORMAT(rp.tgl_registrasi, '%d-%m-%Y') as tgl_reg,
           rp.jam_reg,
           pol.nm_poli,
           rp.status_lanjut
    FROM reg_periksa rp
    LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
    WHERE rp.no_rkm_medis = '$no_rm'
    ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC
    LIMIT 50
";
$result_list_norawat = bukaquery_safe($query_list_norawat);
$list_norawat = [];
while ($row = mysqli_fetch_assoc($result_list_norawat)) {
    $list_norawat[] = $row;
}

// Set default no_rawat
if (empty($no_rawat)) {
    if (!empty($no_rawat_aktif)) {
        $no_rawat = $no_rawat_aktif;
    } else if (!empty($list_norawat)) {
        $no_rawat = $list_norawat[0]['no_rawat'];
    }
}

// =====================================================
// QUERY TIMELINE DATA - FILTER BY NO_RAWAT
// =====================================================
$timeline_data = [];

// Kondisi WHERE untuk no_rawat
$where_rawat = "";
if (!empty($no_rawat)) {
    $where_rawat = " AND r.no_rawat = '$no_rawat'";
}

// =====================================================
// TEMPLATE QUERY - COPY PASTE UNTUK MENAMBAH RME BARU
// =====================================================
/*
$q_nama_rme = "SELECT 
        tabel.no_rawat,
        tabel.no_rawat as id,
        'kode_type' as type,
        CONCAT(DATE_FORMAT(tabel.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(petugas.nama, 'Nama Petugas')) as subtitle,
        N as priority
      FROM tabel_utama tabel
      LEFT JOIN reg_periksa r ON tabel.no_rawat = r.no_rawat
      LEFT JOIN petugas ON tabel.nip = petugas.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      ORDER BY tabel.tanggal DESC";
$result_nama_rme = bukaquery_safe($q_nama_rme);
while($row = mysqli_fetch_assoc($result_nama_rme)) {
    $timeline_data[] = $row;
}
*/

// 1. REGISTRASI PASIEN
$q_registrasi = "SELECT 
        r.no_rawat,
        r.no_rawat as id,
        'registrasi_pasien' as type,
        CONCAT(DATE_FORMAT(r.tgl_registrasi, '%d-%m-%Y'), ' ', r.jam_reg, ' WIB | ',
               IFNULL(d.nm_dokter, '-'), ' | ',
               IFNULL(p.nm_poli, 'Poliklinik Umum')) as subtitle
      FROM reg_periksa r
      LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
      LEFT JOIN poliklinik p ON r.kd_poli = p.kd_poli
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_registrasi = bukaquery_safe($q_registrasi);
while($row = mysqli_fetch_assoc($result_registrasi)) {
    $timeline_data[] = $row;
}

// 2. TRIASE IGD
$q_triase = "SELECT 
        t.no_rawat,
        t.no_rawat as id,
        'triase_igd' as type,
        DATE_FORMAT(t.tgl_kunjungan, '%d-%m-%Y %H:%i WIB') as subtitle
      FROM data_triase_igd t
      LEFT JOIN reg_periksa r ON t.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_triase = bukaquery_safe($q_triase);
while($row = mysqli_fetch_assoc($result_triase)) {
    $timeline_data[] = $row;
}

// 3. PENILAIAN AWAL KEPERAWATAN IGD
$q_keperawatan = "SELECT 
        p.no_rawat,
        p.no_rawat as id,
        'keperawatan_igd' as type,
        CONCAT(DATE_FORMAT(p.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pt.nama, 'Perawat')) as subtitle
      FROM penilaian_awal_keperawatan_igd p
      LEFT JOIN reg_periksa r ON p.no_rawat = r.no_rawat
      LEFT JOIN petugas pt ON p.nip = pt.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_keperawatan = bukaquery_safe($q_keperawatan);
while($row = mysqli_fetch_assoc($result_keperawatan)) {
    $timeline_data[] = $row;
}

// 4. PENILAIAN MCU
$q_mcu = "SELECT 
        pm.no_rawat,
        pm.no_rawat as id,
        'penilaian_mcu' as type,
        CONCAT(DATE_FORMAT(pm.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_mcu pm
      LEFT JOIN reg_periksa r ON pm.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pm.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_mcu = bukaquery_safe($q_mcu);
while($row = mysqli_fetch_assoc($result_mcu)) {
    $timeline_data[] = $row;
}

// 5. PENILAIAN AWAL KEPERAWATAN RALAN
$q_askep_ralan = "SELECT 
        pak.no_rawat,
        pak.no_rawat as id,
        'penilaian_awal_keperawatan_ralan' as type,
        CONCAT(DATE_FORMAT(pak.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_awal_keperawatan_ralan pak
      LEFT JOIN reg_periksa r ON pak.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON pak.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_askep_ralan = bukaquery_safe($q_askep_ralan);
while($row = mysqli_fetch_assoc($result_askep_ralan)) {
    $timeline_data[] = $row;
}

// 6. PENILAIAN AWAL KEPERAWATAN GIGI
$q_askep_gigi = "SELECT 
        pag.no_rawat,
        pag.no_rawat as id,
        'penilaian_awal_keperawatan_gigi' as type,
        CONCAT(DATE_FORMAT(pag.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_awal_keperawatan_gigi pag
      LEFT JOIN reg_periksa r ON pag.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON pag.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_askep_gigi = bukaquery_safe($q_askep_gigi);
while($row = mysqli_fetch_assoc($result_askep_gigi)) {
    $timeline_data[] = $row;
}

// 7. PENILAIAN AWAL KEPERAWATAN KEBIDANAN & KANDUNGAN
$q_askep_kebidanan = "SELECT 
        pak.no_rawat,
        pak.no_rawat as id,
        'penilaian_awal_keperawatan_kebidanan' as type,
        CONCAT(DATE_FORMAT(pak.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_awal_keperawatan_kebidanan pak
      LEFT JOIN reg_periksa r ON pak.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON pak.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_askep_kebidanan = bukaquery_safe($q_askep_kebidanan);
while($row = mysqli_fetch_assoc($result_askep_kebidanan)) {
    $timeline_data[] = $row;
}

// 8. PENILAIAN AWAL KEPERAWATAN BAYI/ANAK
$q_askep_bayi = "SELECT 
        pab.no_rawat,
        pab.no_rawat as id,
        'penilaian_awal_keperawatan_bayi' as type,
        CONCAT(DATE_FORMAT(pab.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_awal_keperawatan_ralan_bayi pab
      LEFT JOIN reg_periksa r ON pab.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON pab.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_askep_bayi = bukaquery_safe($q_askep_bayi);
while($row = mysqli_fetch_assoc($result_askep_bayi)) {
    $timeline_data[] = $row;
}

// 9. PENILAIAN AWAL KEPERAWATAN PSIKIATRI
$q_askep_psikiatri = "SELECT 
        pap.no_rawat,
        pap.no_rawat as id,
        'penilaian_awal_keperawatan_psikiatri' as type,
        CONCAT(DATE_FORMAT(pap.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_awal_keperawatan_ralan_psikiatri pap
      LEFT JOIN reg_periksa r ON pap.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON pap.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_askep_psikiatri = bukaquery_safe($q_askep_psikiatri);
while($row = mysqli_fetch_assoc($result_askep_psikiatri)) {
    $timeline_data[] = $row;
}

// 10. PENILAIAN AWAL KEPERAWATAN GERIATRI
$q_askep_geriatri = "SELECT 
        pag.no_rawat,
        pag.no_rawat as id,
        'penilaian_awal_keperawatan_geriatri' as type,
        CONCAT(DATE_FORMAT(pag.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_awal_keperawatan_ralan_geriatri pag
      LEFT JOIN reg_periksa r ON pag.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON pag.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_askep_geriatri = bukaquery_safe($q_askep_geriatri);
while($row = mysqli_fetch_assoc($result_askep_geriatri)) {
    $timeline_data[] = $row;
}

// 11. PENILAIAN FISIOTERAPI
$q_fisioterapi = "SELECT 
        pf.no_rawat,
        pf.no_rawat as id,
        'penilaian_fisioterapi' as type,
        CONCAT(DATE_FORMAT(pf.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pg.nama, 'Pegawai')) as subtitle
      FROM penilaian_fisioterapi pf
      LEFT JOIN reg_periksa r ON pf.no_rawat = r.no_rawat
      LEFT JOIN pegawai pg ON pf.nip = pg.nik
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_fisioterapi = bukaquery_safe($q_fisioterapi);
while($row = mysqli_fetch_assoc($result_fisioterapi)) {
    $timeline_data[] = $row;
}

// 12. PENILAIAN TERAPI WICARA
$q_terapi_wicara = "SELECT 
        ptw.no_rawat,
        ptw.no_rawat as id,
        'penilaian_terapi_wicara' as type,
        CONCAT(DATE_FORMAT(ptw.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pg.nama, 'Pegawai')) as subtitle
      FROM penilaian_terapi_wicara ptw
      LEFT JOIN reg_periksa r ON ptw.no_rawat = r.no_rawat
      LEFT JOIN pegawai pg ON ptw.nip = pg.nik
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_terapi_wicara = bukaquery_safe($q_terapi_wicara);
while($row = mysqli_fetch_assoc($result_terapi_wicara)) {
    $timeline_data[] = $row;
}

// 13. PENATALAKSANAAN TERAPI OKUPASI
$q_terapi_okupasi = "SELECT 
        pto.no_rawat,
        pto.no_rawat as id,
        'penatalaksanaan_terapi_okupasi' as type,
        CONCAT(DATE_FORMAT(pto.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pg.nama, 'Pegawai')) as subtitle
      FROM penatalaksanaan_terapi_okupasi pto
      LEFT JOIN reg_periksa r ON pto.no_rawat = r.no_rawat
      LEFT JOIN pegawai pg ON pto.nip = pg.nik
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_terapi_okupasi = bukaquery_safe($q_terapi_okupasi);
while($row = mysqli_fetch_assoc($result_terapi_okupasi)) {
    $timeline_data[] = $row;
}

// 14. PENILAIAN PSIKOLOGI
$q_psikologi = "SELECT 
        pp.no_rawat,
        pp.no_rawat as id,
        'penilaian_psikologi' as type,
        CONCAT(DATE_FORMAT(pp.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_psikologi pp
      LEFT JOIN reg_periksa r ON pp.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON pp.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_psikologi = bukaquery_safe($q_psikologi);
while($row = mysqli_fetch_assoc($result_psikologi)) {
    $timeline_data[] = $row;
}

// 15. PENILAIAN PSIKOLOGI KLINIS
$q_psikologi_klinis = "SELECT 
        ppk.no_rawat,
        ppk.no_rawat as id,
        'penilaian_psikologi_klinis' as type,
        CONCAT(DATE_FORMAT(ppk.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_psikologi_klinis ppk
      LEFT JOIN reg_periksa r ON ppk.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON ppk.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_psikologi_klinis = bukaquery_safe($q_psikologi_klinis);
while($row = mysqli_fetch_assoc($result_psikologi_klinis)) {
    $timeline_data[] = $row;
}

// 16. PENILAIAN BAYI BARU LAHIR
$q_bayi_baru_lahir = "SELECT 
        pbbl.no_rawat,
        pbbl.no_rawat as id,
        'penilaian_bayi_baru_lahir' as type,
        CONCAT(DATE_FORMAT(pbbl.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_bayi_baru_lahir pbbl
      LEFT JOIN reg_periksa r ON pbbl.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pbbl.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_bayi_baru_lahir = bukaquery_safe($q_bayi_baru_lahir);
while($row = mysqli_fetch_assoc($result_bayi_baru_lahir)) {
    $timeline_data[] = $row;
}

// 17. PENILAIAN MEDIS IGD
$q_medis_igd = "SELECT 
        pm.no_rawat,
        pm.no_rawat as id,
        'penilaian_medis_igd' as type,
        CONCAT(DATE_FORMAT(pm.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_igd pm
      LEFT JOIN reg_periksa r ON pm.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pm.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_igd = bukaquery_safe($q_medis_igd);
while($row = mysqli_fetch_assoc($result_medis_igd)) {
    $timeline_data[] = $row;
}

// 18. PENILAIAN MEDIS IGD PSIKIATRI
$q_medis_igd_psi = "SELECT 
        pm.no_rawat,
        pm.no_rawat as id,
        'penilaian_medis_igd_psikiatri' as type,
        CONCAT(DATE_FORMAT(pm.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_gawat_darurat_psikiatri pm
      LEFT JOIN reg_periksa r ON pm.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pm.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_igd_psi = bukaquery_safe($q_medis_igd_psi);
while($row = mysqli_fetch_assoc($result_medis_igd_psi)) {
    $timeline_data[] = $row;
}

// 19. PENGKAJIAN AWAL MEDIS UMUM
$q_medis_umum = "SELECT 
        pmr.no_rawat,
        pmr.no_rawat as id,
        'pengkajian_medis_umum' as type,
        CONCAT(DATE_FORMAT(pmr.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan pmr
      LEFT JOIN reg_periksa r ON pmr.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_umum = bukaquery_safe($q_medis_umum);
while($row = mysqli_fetch_assoc($result_medis_umum)) {
    $timeline_data[] = $row;
}

// 20. PENGKAJIAN AWAL MEDIS KEBIDANAN & KANDUNGAN
$q_medis_kandungan = "SELECT 
        pmk.no_rawat,
        pmk.no_rawat as id,
        'pengkajian_medis_kandungan' as type,
        CONCAT(DATE_FORMAT(pmk.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_kandungan pmk
      LEFT JOIN reg_periksa r ON pmk.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmk.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_kandungan = bukaquery_safe($q_medis_kandungan);
while($row = mysqli_fetch_assoc($result_medis_kandungan)) {
    $timeline_data[] = $row;
}

// 21. PENGKAJIAN AWAL MEDIS BAYI/ANAK
$q_medis_anak = "SELECT 
        pma.no_rawat,
        pma.no_rawat as id,
        'pengkajian_medis_anak' as type,
        CONCAT(DATE_FORMAT(pma.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_anak pma
      LEFT JOIN reg_periksa r ON pma.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pma.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_anak = bukaquery_safe($q_medis_anak);
while($row = mysqli_fetch_assoc($result_medis_anak)) {
    $timeline_data[] = $row;
}

// 22. PENGKAJIAN AWAL MEDIS THT
$q_medis_tht = "SELECT 
        pmt.no_rawat,
        pmt.no_rawat as id,
        'pengkajian_medis_tht' as type,
        CONCAT(DATE_FORMAT(pmt.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_tht pmt
      LEFT JOIN reg_periksa r ON pmt.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmt.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_tht = bukaquery_safe($q_medis_tht);
while($row = mysqli_fetch_assoc($result_medis_tht)) {
    $timeline_data[] = $row;
}

// 23. PENGKAJIAN AWAL MEDIS PSIKIATRI
$q_medis_psikiatri = "SELECT 
        pmp.no_rawat,
        pmp.no_rawat as id,
        'pengkajian_medis_psikiatri' as type,
        CONCAT(DATE_FORMAT(pmp.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_psikiatrik pmp
      LEFT JOIN reg_periksa r ON pmp.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmp.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_psikiatri = bukaquery_safe($q_medis_psikiatri);
while($row = mysqli_fetch_assoc($result_medis_psikiatri)) {
    $timeline_data[] = $row;
}

// 24. PENGKAJIAN AWAL MEDIS PENYAKIT DALAM
$q_medis_penyakit_dalam = "SELECT 
        pmpd.no_rawat,
        pmpd.no_rawat as id,
        'pengkajian_medis_penyakit_dalam' as type,
        CONCAT(DATE_FORMAT(pmpd.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_penyakit_dalam pmpd
      LEFT JOIN reg_periksa r ON pmpd.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmpd.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_penyakit_dalam = bukaquery_safe($q_medis_penyakit_dalam);
while($row = mysqli_fetch_assoc($result_medis_penyakit_dalam)) {
    $timeline_data[] = $row;
}

// 25. PENGKAJIAN AWAL MEDIS MATA
$q_medis_mata = "SELECT 
        pmm.no_rawat,
        pmm.no_rawat as id,
        'pengkajian_medis_mata' as type,
        CONCAT(DATE_FORMAT(pmm.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_mata pmm
      LEFT JOIN reg_periksa r ON pmm.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmm.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_mata = bukaquery_safe($q_medis_mata);
while($row = mysqli_fetch_assoc($result_medis_mata)) {
    $timeline_data[] = $row;
}

// 26. PENGKAJIAN AWAL MEDIS NEUROLOGI
$q_medis_neurologi = "SELECT 
        pmn.no_rawat,
        pmn.no_rawat as id,
        'pengkajian_medis_neurologi' as type,
        CONCAT(DATE_FORMAT(pmn.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_neurologi pmn
      LEFT JOIN reg_periksa r ON pmn.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmn.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_neurologi = bukaquery_safe($q_medis_neurologi);
while($row = mysqli_fetch_assoc($result_medis_neurologi)) {
    $timeline_data[] = $row;
}

// 27. PENGKAJIAN AWAL MEDIS ORTHOPEDI
$q_medis_orthopedi = "SELECT 
        pmo.no_rawat,
        pmo.no_rawat as id,
        'pengkajian_medis_orthopedi' as type,
        CONCAT(DATE_FORMAT(pmo.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_orthopedi pmo
      LEFT JOIN reg_periksa r ON pmo.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmo.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_orthopedi = bukaquery_safe($q_medis_orthopedi);
while($row = mysqli_fetch_assoc($result_medis_orthopedi)) {
    $timeline_data[] = $row;
}

// 28. PENGKAJIAN AWAL MEDIS PARU
$q_medis_paru = "SELECT 
        pmp.no_rawat,
        pmp.no_rawat as id,
        'pengkajian_medis_paru' as type,
        CONCAT(DATE_FORMAT(pmp.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_paru pmp
      LEFT JOIN reg_periksa r ON pmp.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmp.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_paru = bukaquery_safe($q_medis_paru);
while($row = mysqli_fetch_assoc($result_medis_paru)) {
    $timeline_data[] = $row;
}

// 29. PENGKAJIAN AWAL MEDIS BEDAH
$q_medis_bedah = "SELECT 
        pmb.no_rawat,
        pmb.no_rawat as id,
        'pengkajian_medis_bedah' as type,
        CONCAT(DATE_FORMAT(pmb.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_bedah pmb
      LEFT JOIN reg_periksa r ON pmb.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmb.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_bedah = bukaquery_safe($q_medis_bedah);
while($row = mysqli_fetch_assoc($result_medis_bedah)) {
    $timeline_data[] = $row;
}

// 30. PENGKAJIAN AWAL MEDIS BEDAH MULUT
$q_medis_bedah_mulut = "SELECT 
        pmbm.no_rawat,
        pmbm.no_rawat as id,
        'pengkajian_medis_bedah_mulut' as type,
        CONCAT(DATE_FORMAT(pmbm.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_bedah_mulut pmbm
      LEFT JOIN reg_periksa r ON pmbm.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmbm.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_bedah_mulut = bukaquery_safe($q_medis_bedah_mulut);
while($row = mysqli_fetch_assoc($result_medis_bedah_mulut)) {
    $timeline_data[] = $row;
}

// 31. PENGKAJIAN AWAL MEDIS GERIATRI
$q_medis_geriatri = "SELECT 
        pmg.no_rawat,
        pmg.no_rawat as id,
        'pengkajian_medis_geriatri' as type,
        CONCAT(DATE_FORMAT(pmg.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_geriatri pmg
      LEFT JOIN reg_periksa r ON pmg.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmg.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_geriatri = bukaquery_safe($q_medis_geriatri);
while($row = mysqli_fetch_assoc($result_medis_geriatri)) {
    $timeline_data[] = $row;
}

// 32. PENGKAJIAN AWAL MEDIS KULIT & KELAMIN
$q_medis_kulit = "SELECT 
        pmk.no_rawat,
        pmk.no_rawat as id,
        'pengkajian_medis_kulit' as type,
        CONCAT(DATE_FORMAT(pmk.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_kulitdankelamin pmk
      LEFT JOIN reg_periksa r ON pmk.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmk.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_kulit = bukaquery_safe($q_medis_kulit);
while($row = mysqli_fetch_assoc($result_medis_kulit)) {
    $timeline_data[] = $row;
}

$q_medis_jantung = "SELECT 
        pmj.no_rawat,
        pmj.no_rawat as id,
        'pengkajian_medis_jantung' as type,
        CONCAT(DATE_FORMAT(pmj.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_jantung pmj
      LEFT JOIN reg_periksa r ON pmj.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmj.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_jantung = bukaquery_safe($q_medis_jantung);
while($row = mysqli_fetch_assoc($result_medis_jantung)) {
    $timeline_data[] = $row;
}

$q_medis_hemodialisa = "SELECT 
        pmh.no_rawat,
        pmh.no_rawat as id,
        'pengkajian_medis_hemodialisa' as type,
        CONCAT(DATE_FORMAT(pmh.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_hemodialisa pmh
      LEFT JOIN reg_periksa r ON pmh.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmh.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_hemodialisa = bukaquery_safe($q_medis_hemodialisa);
while($row = mysqli_fetch_assoc($result_medis_hemodialisa)) {
    $timeline_data[] = $row;
}

// 33. PENGKAJIAN AWAL MEDIS FISIK & REHABILITASI
$q_medis_rehab = "SELECT 
        pmr.no_rawat,
        pmr.no_rawat as id,
        'pengkajian_medis_rehab' as type,
        CONCAT(DATE_FORMAT(pmr.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_rehab_medik pmr
      LEFT JOIN reg_periksa r ON pmr.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_rehab = bukaquery_safe($q_medis_rehab);
while($row = mysqli_fetch_assoc($result_medis_rehab)) {
    $timeline_data[] = $row;
}

// 34. LAYANAN KEDOKTERAN FISIK REHABILITASI
$q_kfr = "SELECT 
        lkfr.no_rawat,
        lkfr.no_rawat as id,
        'layanan_kedokteran_fisik_rehabilitasi' as type,
        CONCAT(DATE_FORMAT(lkfr.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM layanan_kedokteran_fisik_rehabilitasi lkfr
      LEFT JOIN reg_periksa r ON lkfr.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON lkfr.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_kfr = bukaquery_safe($q_kfr);
while($row = mysqli_fetch_assoc($result_kfr)) {
    $timeline_data[] = $row;
}

// 35. UJI FUNGSI KFR
$q_uji_fungsi_kfr = "SELECT 
        ufk.no_rawat,
        ufk.no_rawat as id,
        'uji_fungsi_kfr' as type,
        CONCAT(DATE_FORMAT(ufk.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM uji_fungsi_kfr ufk
      LEFT JOIN reg_periksa r ON ufk.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON ufk.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_uji_fungsi_kfr = bukaquery_safe($q_uji_fungsi_kfr);
while($row = mysqli_fetch_assoc($result_uji_fungsi_kfr)) {
    $timeline_data[] = $row;
}

// 36. HEMODIALISA
$q_hemodialisa = "SELECT 
        h.no_rawat,
        h.no_rawat as id,
        'hemodialisa' as type,
        CONCAT('Hemodialisa (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(h.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM hemodialisa h
      LEFT JOIN reg_periksa r ON h.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY h.no_rawat";
$result_hemodialisa = bukaquery_safe($q_hemodialisa);
while($row = mysqli_fetch_assoc($result_hemodialisa)) {
    $timeline_data[] = $row;
}

// 37. CATATAN KEPERAWATAN RALAN
$q_catatan_keperawatan = "SELECT 
        ckr.no_rawat,
        ckr.no_rawat as id,
        'catatan_keperawatan_ralan' as type,
        CONCAT('Catatan Keperawatan Ralan (', COUNT(*), 'x)') as subtitle
      FROM catatan_keperawatan_ralan ckr
      LEFT JOIN reg_periksa r ON ckr.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckr.no_rawat";
$result_catatan_keperawatan = bukaquery_safe($q_catatan_keperawatan);
while($row = mysqli_fetch_assoc($result_catatan_keperawatan)) {
    $timeline_data[] = $row;
}

// 38. PENILAIAN AWAL KEPERAWATAN RANAP
$q_keperawatan_ranap = "SELECT 
        pakr.no_rawat,
        pakr.no_rawat as id,
        'penilaian_awal_keperawatan_ranap' as type,
        CONCAT(DATE_FORMAT(pakr.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_awal_keperawatan_ranap pakr
      LEFT JOIN reg_periksa r ON pakr.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_keperawatan_ranap = bukaquery_safe($q_keperawatan_ranap);
while($row = mysqli_fetch_assoc($result_keperawatan_ranap)) {
    $timeline_data[] = $row;
}

// 39. PENILAIAN AWAL KEPERAWATAN KEBIDANAN RANAP
$q_kebidanan_ranap = "SELECT 
        pakkr.no_rawat,
        pakkr.no_rawat as id,
        'penilaian_awal_keperawatan_kebidanan_ranap' as type,
        CONCAT(DATE_FORMAT(pakkr.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_awal_keperawatan_kebidanan_ranap pakkr
      LEFT JOIN reg_periksa r ON pakkr.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_kebidanan_ranap = bukaquery_safe($q_kebidanan_ranap);
while($row = mysqli_fetch_assoc($result_kebidanan_ranap)) {
    $timeline_data[] = $row;
}

// 40. PENILAIAN AWAL KEPERAWATAN RANAP NEONATUS
$q_ranap_neonatus = "SELECT 
        pakrn.no_rawat,
        pakrn.no_rawat as id,
        'penilaian_awal_keperawatan_ranap_neonatus' as type,
        CONCAT(DATE_FORMAT(pakrn.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_awal_keperawatan_ranap_neonatus pakrn
      LEFT JOIN reg_periksa r ON pakrn.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_ranap_neonatus = bukaquery_safe($q_ranap_neonatus);
while($row = mysqli_fetch_assoc($result_ranap_neonatus)) {
    $timeline_data[] = $row;
}

// 41. PENILAIAN AWAL KEPERAWATAN RANAP BAYI
$q_ranap_bayi = "SELECT 
        pakrb.no_rawat,
        pakrb.no_rawat as id,
        'penilaian_awal_keperawatan_ranap_bayi' as type,
        CONCAT(DATE_FORMAT(pakrb.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_awal_keperawatan_ranap_bayi pakrb
      LEFT JOIN reg_periksa r ON pakrb.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_ranap_bayi = bukaquery_safe($q_ranap_bayi);
while($row = mysqli_fetch_assoc($result_ranap_bayi)) {
    $timeline_data[] = $row;
}

// 42. PENILAIAN MEDIS RANAP
$q_medis_ranap = "SELECT 
        pmr.no_rawat,
        pmr.no_rawat as id,
        'penilaian_medis_ranap' as type,
        CONCAT(DATE_FORMAT(pmr.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ranap pmr
      LEFT JOIN reg_periksa r ON pmr.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmr.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_ranap = bukaquery_safe($q_medis_ranap);
while($row = mysqli_fetch_assoc($result_medis_ranap)) {
    $timeline_data[] = $row;
}

// 43. PENILAIAN MEDIS RANAP NEONATUS
$q_medis_ranap_neonatus = "SELECT 
        pmrn.no_rawat,
        pmrn.no_rawat as id,
        'penilaian_medis_ranap_neonatus' as type,
        CONCAT(DATE_FORMAT(pmrn.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ranap_neonatus pmrn
      LEFT JOIN reg_periksa r ON pmrn.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmrn.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_ranap_neonatus = bukaquery_safe($q_medis_ranap_neonatus);
while($row = mysqli_fetch_assoc($result_medis_ranap_neonatus)) {
    $timeline_data[] = $row;
}

$q_medis_ranap_kandungan = "SELECT 
        pmrk.no_rawat,
        pmrk.no_rawat as id,
        'pengkajian_medis_ranap_kandungan' as type,
        CONCAT(DATE_FORMAT(pmrk.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ranap_kandungan pmrk
      LEFT JOIN reg_periksa r ON pmrk.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmrk.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_ranap_kandungan = bukaquery_safe($q_medis_ranap_kandungan);
while($row = mysqli_fetch_assoc($result_medis_ranap_kandungan)) {
    $timeline_data[] = $row;
}

// 44. PENILAIAN MEDIS RANAP PSIKIATRIK
$q_medis_ranap_psikiatrik = "SELECT 
        pmrp.no_rawat,
        pmrp.no_rawat as id,
        'penilaian_medis_ranap_psikiatrik' as type,
        CONCAT(DATE_FORMAT(pmrp.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ranap_psikiatrik pmrp
      LEFT JOIN reg_periksa r ON pmrp.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmrp.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_ranap_psikiatrik = bukaquery_safe($q_medis_ranap_psikiatrik);
while($row = mysqli_fetch_assoc($result_medis_ranap_psikiatrik)) {
    $timeline_data[] = $row;
}

// 45. PENGKAJIAN AWAL MEDIS HEMODIALISA
$q_medis_hemodialisa = "SELECT 
        pmh.no_rawat,
        pmh.no_rawat as id,
        'pengkajian_medis_hemodialisa' as type,
        CONCAT(DATE_FORMAT(pmh.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_medis_ralan_gawat_darurat_psikiatri pmh
      LEFT JOIN reg_periksa r ON pmh.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pmh.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_medis_hemodialisa = bukaquery_safe($q_medis_hemodialisa);
while($row = mysqli_fetch_assoc($result_medis_hemodialisa)) {
    $timeline_data[] = $row;
}



// 46. PERENCANAAN PEMULANGAN
$q_perencanaan_pemulangan = "SELECT 
        pp.no_rawat,
        pp.no_rawat as id,
        'perencanaan_pemulangan' as type,
        CONCAT(IFNULL(p.nama, 'Petugas')) as subtitle
      FROM perencanaan_pemulangan pp
      LEFT JOIN reg_periksa r ON pp.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON pp.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_perencanaan_pemulangan = bukaquery_safe($q_perencanaan_pemulangan);
while($row = mysqli_fetch_assoc($result_perencanaan_pemulangan)) {
    $timeline_data[] = $row;
}

// 47. CATATAN OBSERVASI IGD
$q_observasi_igd = "SELECT 
        coi.no_rawat,
        coi.no_rawat as id,
        'catatan_observasi_igd' as type,
        'Catatan Observasi IGD' as subtitle
      FROM catatan_observasi_igd coi
      LEFT JOIN reg_periksa r ON coi.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY coi.no_rawat";
$result_observasi_igd = bukaquery_safe($q_observasi_igd);
while($row = mysqli_fetch_assoc($result_observasi_igd)) {
    $timeline_data[] = $row;
}

// 48. CATATAN OBSERVASI CHBP
$q_observasi_chbp = "SELECT 
        coc.no_rawat,
        coc.no_rawat as id,
        'catatan_observasi_chbp' as type,
        'Catatan Observasi CHBP' as subtitle
      FROM catatan_observasi_chbp coc
      LEFT JOIN reg_periksa r ON coc.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY coc.no_rawat";
$result_observasi_chbp = bukaquery_safe($q_observasi_chbp);
while($row = mysqli_fetch_assoc($result_observasi_chbp)) {
    $timeline_data[] = $row;
}

// 49. CATATAN OBSERVASI INDUKSI PERSALINAN
$q_observasi_induksi = "SELECT 
        coip.no_rawat,
        coip.no_rawat as id,
        'catatan_observasi_induksi_persalinan' as type,
        'Catatan Observasi Induksi Persalinan' as subtitle
      FROM catatan_observasi_induksi_persalinan coip
      LEFT JOIN reg_periksa r ON coip.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY coip.no_rawat";
$result_observasi_induksi = bukaquery_safe($q_observasi_induksi);
while($row = mysqli_fetch_assoc($result_observasi_induksi)) {
    $timeline_data[] = $row;
}

// 50. CATATAN CEK GDS
$q_cek_gds = "SELECT 
        ccg.no_rawat,
        ccg.no_rawat as id,
        'catatan_cek_gds' as type,
        CONCAT('Catatan Cek GDS (', COUNT(*), 'x)') as subtitle
      FROM catatan_cek_gds ccg
      LEFT JOIN reg_periksa r ON ccg.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ccg.no_rawat";
$result_cek_gds = bukaquery_safe($q_cek_gds);
while($row = mysqli_fetch_assoc($result_cek_gds)) {
    $timeline_data[] = $row;
}

// 51. CATATAN KESEIMBANGAN CAIRAN
$q_keseimbangan_cairan = "SELECT 
        ckc.no_rawat,
        ckc.no_rawat as id,
        'catatan_keseimbangan_cairan' as type,
        'Catatan Keseimbangan Cairan' as subtitle
      FROM catatan_keseimbangan_cairan ckc
      LEFT JOIN reg_periksa r ON ckc.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckc.no_rawat";
$result_keseimbangan_cairan = bukaquery_safe($q_keseimbangan_cairan);
while($row = mysqli_fetch_assoc($result_keseimbangan_cairan)) {
    $timeline_data[] = $row;
}

// 52. PENILAIAN ULANG NYERI
$q_ulang_nyeri = "SELECT 
        pun.no_rawat,
        pun.no_rawat as id,
        'penilaian_ulang_nyeri' as type,
        CONCAT('Penilaian Ulang Nyeri (', COUNT(*), 'x)') as subtitle
      FROM penilaian_ulang_nyeri pun
      LEFT JOIN reg_periksa r ON pun.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY pun.no_rawat";
$result_ulang_nyeri = bukaquery_safe($q_ulang_nyeri);
while($row = mysqli_fetch_assoc($result_ulang_nyeri)) {
    $timeline_data[] = $row;
}

// 53. CATATAN OBSERVASI RANAP
$q_observasi_ranap = "SELECT 
        cor.no_rawat,
        cor.no_rawat AS id,
        'catatan_observasi_ranap' AS type,
        CONCAT('Catatan Observasi Ranap (', COUNT(*), 'x)') AS subtitle
      FROM catatan_observasi_ranap cor
      LEFT JOIN reg_periksa r ON cor.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY cor.no_rawat";
$result_observasi_ranap = bukaquery_safe($q_observasi_ranap);
while($row = mysqli_fetch_assoc($result_observasi_ranap)) {
    $timeline_data[] = $row;
}

// 54. CATATAN OBSERVASI RANAP KEBIDANAN
$q_observasi_ranap_kebidanan = "SELECT 
        cork.no_rawat,
        cork.no_rawat as id,
        'catatan_observasi_ranap_kebidanan' as type,
        'Catatan Observasi Ranap Kebidanan' as subtitle
      FROM catatan_observasi_ranap_kebidanan cork
      LEFT JOIN reg_periksa r ON cork.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_observasi_ranap_kebidanan = bukaquery_safe($q_observasi_ranap_kebidanan);
while($row = mysqli_fetch_assoc($result_observasi_ranap_kebidanan)) {
    $timeline_data[] = $row;
}

// 55. CATATAN OBSERVASI RANAP POSTPARTUM
$q_observasi_ranap_postpartum = "SELECT 
        corp.no_rawat,
        corp.no_rawat as id,
        'catatan_observasi_ranap_postpartum' as type,
        'Catatan Observasi Ranap Postpartum' as subtitle
      FROM catatan_observasi_ranap_postpartum corp
      LEFT JOIN reg_periksa r ON corp.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_observasi_ranap_postpartum = bukaquery_safe($q_observasi_ranap_postpartum);
while($row = mysqli_fetch_assoc($result_observasi_ranap_postpartum)) {
    $timeline_data[] = $row;
}

// 56. CATATAN OBSERVASI BAYI
$q_observasi_bayi = "SELECT 
        cob.no_rawat,
        cob.no_rawat as id,
        'catatan_observasi_bayi' as type,
        'Catatan Observasi Bayi' as subtitle
      FROM catatan_observasi_bayi cob
      LEFT JOIN reg_periksa r ON cob.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_observasi_bayi = bukaquery_safe($q_observasi_bayi);
while($row = mysqli_fetch_assoc($result_observasi_bayi)) {
    $timeline_data[] = $row;
}

// 57. CATATAN OBSERVASI HEMODIALISA
$q_observasi_hemodialisa = "SELECT 
        coh.no_rawat,
        coh.no_rawat as id,
        'catatan_observasi_hemodialisa' as type,
        'Catatan Observasi Hemodialisa' as subtitle
      FROM catatan_observasi_hemodialisa coh
      LEFT JOIN reg_periksa r ON coh.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_observasi_hemodialisa = bukaquery_safe($q_observasi_hemodialisa);
while($row = mysqli_fetch_assoc($result_observasi_hemodialisa)) {
    $timeline_data[] = $row;
}

// 58. CATATAN OBSERVASI RESTRAIN NONFARMA
$q_observasi_restrain = "SELECT 
        corn.no_rawat,
        corn.no_rawat as id,
        'catatan_observasi_restrain_nonfarma' as type,
        CONCAT(IFNULL(p.nama, 'Petugas')) as subtitle
      FROM catatan_observasi_restrain_nonfarma corn
      LEFT JOIN reg_periksa r ON corn.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON corn.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_observasi_restrain = bukaquery_safe($q_observasi_restrain);
while($row = mysqli_fetch_assoc($result_observasi_restrain)) {
    $timeline_data[] = $row;
}

// 59. CATATAN OBSERVASI VENTILATOR
$q_observasi_ventilator = "SELECT 
        cov.no_rawat,
        cov.no_rawat as id,
        'catatan_observasi_ventilator' as type,
        CONCAT(IFNULL(p.nama, 'Petugas')) as subtitle
      FROM catatan_observasi_ventilator cov
      LEFT JOIN reg_periksa r ON cov.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON cov.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_observasi_ventilator = bukaquery_safe($q_observasi_ventilator);
while($row = mysqli_fetch_assoc($result_observasi_ventilator)) {
    $timeline_data[] = $row;
}

// 60. CATATAN KEPERAWATAN RANAP
$q_keperawatan_ranap_detail = "SELECT 
        ckr.no_rawat,
        ckr.no_rawat as id,
        'catatan_keperawatan_ranap' as type,
        CONCAT(DATE_FORMAT(ckr.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM catatan_keperawatan_ranap ckr
      LEFT JOIN reg_periksa r ON ckr.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON ckr.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_keperawatan_ranap_detail = bukaquery_safe($q_keperawatan_ranap_detail);
while($row = mysqli_fetch_assoc($result_keperawatan_ranap_detail)) {
    $timeline_data[] = $row;
}

// 61. KONSULTASI MEDIK
$q_konsultasi_medik = "SELECT 
        km.no_rawat,
        km.no_rawat as id,
        'konsultasi_medik' as type,
        CONCAT(DATE_FORMAT(km.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM konsultasi_medik km
      LEFT JOIN reg_periksa r ON km.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON km.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_konsultasi_medik = bukaquery_safe($q_konsultasi_medik);
while($row = mysqli_fetch_assoc($result_konsultasi_medik)) {
    $timeline_data[] = $row;
}

// 62. FOLLOW UP DBD
$q_follow_up_dbd = "SELECT 
        fud.no_rawat,
        fud.no_rawat as id,
        'follow_up_dbd' as type,
        CONCAT(IFNULL(p.nama, 'Petugas')) as subtitle
      FROM follow_up_dbd fud
      LEFT JOIN reg_periksa r ON fud.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON fud.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_follow_up_dbd = bukaquery_safe($q_follow_up_dbd);
while($row = mysqli_fetch_assoc($result_follow_up_dbd)) {
    $timeline_data[] = $row;
}

// 63. MONITORING REAKSI TRANSFUSI
$q_monitoring_transfusi = "SELECT 
        mrt.no_rawat,
        mrt.no_rawat as id,
        'monitoring_reaksi_tranfusi' as type,
        'Monitoring Reaksi Transfusi' as subtitle
      FROM monitoring_reaksi_tranfusi mrt
      LEFT JOIN reg_periksa r ON mrt.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_monitoring_transfusi = bukaquery_safe($q_monitoring_transfusi);
while($row = mysqli_fetch_assoc($result_monitoring_transfusi)) {
    $timeline_data[] = $row;
}

// 64. PENILAIAN LANJUTAN RESIKO JATUH DEWASA
$q_resiko_jatuh_dewasa = "SELECT 
        plrjd.no_rawat,
        plrjd.no_rawat AS id,
        'penilaian_lanjutan_resiko_jatuh_dewasa' AS type,
        CONCAT('Penilaian Lanjutan Risiko Jatuh Dewasa (', COUNT(*), 'x)') AS subtitle
      FROM penilaian_lanjutan_resiko_jatuh_dewasa plrjd
      LEFT JOIN reg_periksa r ON plrjd.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY plrjd.no_rawat";
$result_resiko_jatuh_dewasa = bukaquery_safe($q_resiko_jatuh_dewasa);
while($row = mysqli_fetch_assoc($result_resiko_jatuh_dewasa)) {
    $timeline_data[] = $row;
}

// 65. PENILAIAN LANJUTAN RESIKO JATUH ANAK
$q_resiko_jatuh_anak = "SELECT 
        plrja.no_rawat,
        plrja.no_rawat as id,
        'penilaian_lanjutan_resiko_jatuh_anak' as type,
        CONCAT('Penilaian Lanjutan Resiko Jatuh Anak (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(plrja.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_lanjutan_resiko_jatuh_anak plrja
      LEFT JOIN reg_periksa r ON plrja.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY plrja.no_rawat";
$result_resiko_jatuh_anak = bukaquery_safe($q_resiko_jatuh_anak);
while($row = mysqli_fetch_assoc($result_resiko_jatuh_anak)) {
    $timeline_data[] = $row;
}

// 66. PENILAIAN LANJUTAN RESIKO JATUH GERIATRI
$q_resiko_jatuh_geriatri = "SELECT 
        plrjg.no_rawat,
        plrjg.no_rawat as id,
        'penilaian_lanjutan_resiko_jatuh_geriatri' as type,
        CONCAT(DATE_FORMAT(plrjg.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_lanjutan_resiko_jatuh_geriatri plrjg
      LEFT JOIN reg_periksa r ON plrjg.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_resiko_jatuh_geriatri = bukaquery_safe($q_resiko_jatuh_geriatri);
while($row = mysqli_fetch_assoc($result_resiko_jatuh_geriatri)) {
    $timeline_data[] = $row;
}

// 67. PENILAIAN LANJUTAN RESIKO JATUH LANSIA
$q_resiko_jatuh_lansia = "SELECT 
        plrjl.no_rawat,
        plrjl.no_rawat as id,
        'penilaian_lanjutan_resiko_jatuh_lansia' as type,
        CONCAT('Penilaian Lanjutan Resiko Jatuh Lansia (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(plrjl.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_lanjutan_resiko_jatuh_lansia plrjl
      LEFT JOIN reg_periksa r ON plrjl.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY plrjl.no_rawat";
$result_resiko_jatuh_lansia = bukaquery_safe($q_resiko_jatuh_lansia);
while($row = mysqli_fetch_assoc($result_resiko_jatuh_lansia)) {
    $timeline_data[] = $row;
}

// 68. PENILAIAN RISIKO JATUH NEONATUS
$q_resiko_jatuh_neonatus = "SELECT 
        prjn.no_rawat,
        prjn.no_rawat as id,
        'penilaian_risiko_jatuh_neonatus' as type,
        CONCAT(DATE_FORMAT(prjn.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_risiko_jatuh_neonatus prjn
      LEFT JOIN reg_periksa r ON prjn.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_resiko_jatuh_neonatus = bukaquery_safe($q_resiko_jatuh_neonatus);
while($row = mysqli_fetch_assoc($result_resiko_jatuh_neonatus)) {
    $timeline_data[] = $row;
}

// 69. PENILAIAN LANJUTAN RESIKO JATUH PSIKIATRI
$q_resiko_jatuh_psikiatri = "SELECT 
        plrjp.no_rawat,
        plrjp.no_rawat as id,
        'penilaian_lanjutan_resiko_jatuh_psikiatri' as type,
        CONCAT(DATE_FORMAT(plrjp.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_lanjutan_resiko_jatuh_psikiatri plrjp
      LEFT JOIN reg_periksa r ON plrjp.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_resiko_jatuh_psikiatri = bukaquery_safe($q_resiko_jatuh_psikiatri);
while($row = mysqli_fetch_assoc($result_resiko_jatuh_psikiatri)) {
    $timeline_data[] = $row;
}

// 70. PENILAIAN LANJUTAN SKRINING FUNGSIONAL
$q_skrining_fungsional = "SELECT 
        plsf.no_rawat,
        plsf.no_rawat as id,
        'penilaian_lanjutan_skrining_fungsional' as type,
        CONCAT(DATE_FORMAT(plsf.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_lanjutan_skrining_fungsional plsf
      LEFT JOIN reg_periksa r ON plsf.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_skrining_fungsional = bukaquery_safe($q_skrining_fungsional);
while($row = mysqli_fetch_assoc($result_skrining_fungsional)) {
    $timeline_data[] = $row;
}

// 71. PENILAIAN RISIKO DEKUBITUS
$q_risiko_dekubitus = "SELECT 
        prd.no_rawat,
        prd.no_rawat as id,
        'penilaian_risiko_dekubitus' as type,
        CONCAT(DATE_FORMAT(prd.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_risiko_dekubitus prd
      LEFT JOIN reg_periksa r ON prd.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON prd.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_risiko_dekubitus = bukaquery_safe($q_risiko_dekubitus);
while($row = mysqli_fetch_assoc($result_risiko_dekubitus)) {
    $timeline_data[] = $row;
}

// 72. PENILAIAN TAMBAHAN GERIATRI
$q_tambahan_geriatri = "SELECT 
        ptg.no_rawat,
        ptg.no_rawat as id,
        'penilaian_tambahan_geriatri' as type,
        CONCAT(DATE_FORMAT(ptg.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pg.nama, 'Pegawai')) as subtitle
      FROM penilaian_tambahan_geriatri ptg
      LEFT JOIN reg_periksa r ON ptg.no_rawat = r.no_rawat
      LEFT JOIN pegawai pg ON ptg.nik = pg.nik
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_tambahan_geriatri = bukaquery_safe($q_tambahan_geriatri);
while($row = mysqli_fetch_assoc($result_tambahan_geriatri)) {
    $timeline_data[] = $row;
}

// 73. PENILAIAN TAMBAHAN BUNUH DIRI
$q_tambahan_bunuh_diri = "SELECT 
        ptbd.no_rawat,
        ptbd.no_rawat as id,
        'penilaian_tambahan_bunuh_diri' as type,
        CONCAT(DATE_FORMAT(ptbd.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pg.nama, 'Pegawai')) as subtitle
      FROM penilaian_tambahan_bunuh_diri ptbd
      LEFT JOIN reg_periksa r ON ptbd.no_rawat = r.no_rawat
      LEFT JOIN pegawai pg ON ptbd.nip = pg.nik
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_tambahan_bunuh_diri = bukaquery_safe($q_tambahan_bunuh_diri);
while($row = mysqli_fetch_assoc($result_tambahan_bunuh_diri)) {
    $timeline_data[] = $row;
}

// 74. PENILAIAN TAMBAHAN PERILAKU KEKERASAN
$q_tambahan_kekerasan = "SELECT 
        ptpk.no_rawat,
        ptpk.no_rawat as id,
        'penilaian_tambahan_perilaku_kekerasan' as type,
        CONCAT(DATE_FORMAT(ptpk.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pg.nama, 'Pegawai')) as subtitle
      FROM penilaian_tambahan_perilaku_kekerasan ptpk
      LEFT JOIN reg_periksa r ON ptpk.no_rawat = r.no_rawat
      LEFT JOIN pegawai pg ON ptpk.nip = pg.nik
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_tambahan_kekerasan = bukaquery_safe($q_tambahan_kekerasan);
while($row = mysqli_fetch_assoc($result_tambahan_kekerasan)) {
    $timeline_data[] = $row;
}

// 75. PENILAIAN TAMBAHAN BERESIKO MELARIKAN DIRI
$q_tambahan_melarikan_diri = "SELECT 
        ptbmd.no_rawat,
        ptbmd.no_rawat as id,
        'penilaian_tambahan_beresiko_melarikan_diri' as type,
        CONCAT(DATE_FORMAT(ptbmd.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pg.nama, 'Pegawai')) as subtitle
      FROM penilaian_tambahan_beresiko_melarikan_diri ptbmd
      LEFT JOIN reg_periksa r ON ptbmd.no_rawat = r.no_rawat
      LEFT JOIN pegawai pg ON ptbmd.nip = pg.nik
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_tambahan_melarikan_diri = bukaquery_safe($q_tambahan_melarikan_diri);
while($row = mysqli_fetch_assoc($result_tambahan_melarikan_diri)) {
    $timeline_data[] = $row;
}

// 76. PEMANTAUAN PEWS ANAK
$q_pews_anak = "SELECT 
        ppa.no_rawat,
        ppa.no_rawat as id,
        'pemantauan_pews_anak' as type,
        CONCAT('Pemantauan PEWS Anak (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ppa.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM pemantauan_pews_anak ppa
      LEFT JOIN reg_periksa r ON ppa.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ppa.no_rawat";
$result_pews_anak = bukaquery_safe($q_pews_anak);
while($row = mysqli_fetch_assoc($result_pews_anak)) {
    $timeline_data[] = $row;
}

// 77. PEMANTAUAN EWS DEWASA
$q_ews_dewasa = "SELECT 
        ped.no_rawat,
        ped.no_rawat as id,
        'pemantauan_ews_dewasa' as type,
        CONCAT('Pemantauan EWS (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ped.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM pemantauan_pews_dewasa ped
      LEFT JOIN reg_periksa r ON ped.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ped.no_rawat";
$result_ews_dewasa = bukaquery_safe($q_ews_dewasa);
while($row = mysqli_fetch_assoc($result_ews_dewasa)) {
    $timeline_data[] = $row;
}

// 78. PEMANTAUAN MEOWS OBSTETRI
$q_meows = "SELECT 
        pmo.no_rawat,
        pmo.no_rawat as id,
        'pemantauan_meows_obstetri' as type,
        CONCAT('Pemantauan MEOWS Obstetri (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(pmo.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM pemantauan_meows_obstetri pmo
      LEFT JOIN reg_periksa r ON pmo.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY pmo.no_rawat";
$result_meows = bukaquery_safe($q_meows);
while($row = mysqli_fetch_assoc($result_meows)) {
    $timeline_data[] = $row;
}

// 79. PEMANTAUAN EWS PASIEN NEONATUS
$q_ews_neonatus = "SELECT 
        pen.no_rawat,
        pen.no_rawat as id,
        'pemantauan_ews_neonatus' as type,
        CONCAT('Pemantauan EWS Neonatus (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(pen.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM pemantauan_ews_neonatus pen
      LEFT JOIN reg_periksa r ON pen.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY pen.no_rawat";
$result_ews_neonatus = bukaquery_safe($q_ews_neonatus);
while($row = mysqli_fetch_assoc($result_ews_neonatus)) {
    $timeline_data[] = $row;
}

// 80. PENILAIAN PRE INDUKSI
$q_pre_induksi = "SELECT 
        ppi.no_rawat,
        ppi.no_rawat as id,
        'penilaian_pre_induksi' as type,
        CONCAT('Penilaian Pre Induksi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ppi.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_pre_induksi ppi
      LEFT JOIN reg_periksa r ON ppi.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ppi.no_rawat";
$result_pre_induksi = bukaquery_safe($q_pre_induksi);
while($row = mysqli_fetch_assoc($result_pre_induksi)) {
    $timeline_data[] = $row;
}

// 81. CHECKLIST PRE OPERASI
$q_checklist_pre_operasi = "SELECT 
        cpo.no_rawat,
        cpo.no_rawat as id,
        'checklist_pre_operasi' as type,
        CONCAT('Checklist Pre Operasi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(cpo.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_pre_operasi cpo
      LEFT JOIN reg_periksa r ON cpo.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY cpo.no_rawat";
$result_checklist_pre_operasi = bukaquery_safe($q_checklist_pre_operasi);
while($row = mysqli_fetch_assoc($result_checklist_pre_operasi)) {
    $timeline_data[] = $row;
}

// 82. SIGN IN SEBELUM ANESTESI
$q_signin_anestesi = "SELECT 
        ssa.no_rawat,
        ssa.no_rawat as id,
        'signin_sebelum_anestesi' as type,
        CONCAT('Sign In Sebelum Anestesi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ssa.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM signin_sebelum_anestesi ssa
      LEFT JOIN reg_periksa r ON ssa.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ssa.no_rawat";
$result_signin_anestesi = bukaquery_safe($q_signin_anestesi);
while($row = mysqli_fetch_assoc($result_signin_anestesi)) {
    $timeline_data[] = $row;
}

// 83. TIME OUT SEBELUM INSISI
$q_timeout_insisi = "SELECT 
        tsi.no_rawat,
        tsi.no_rawat as id,
        'timeout_sebelum_insisi' as type,
        CONCAT(DATE_FORMAT(tsi.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM timeout_sebelum_insisi tsi
      LEFT JOIN reg_periksa r ON tsi.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_timeout_insisi = bukaquery_safe($q_timeout_insisi);
while($row = mysqli_fetch_assoc($result_timeout_insisi)) {
    $timeline_data[] = $row;
}

// 84. SIGN OUT SEBELUM MENUTUP LUKA
$q_signout_luka = "SELECT 
        sml.no_rawat,
        sml.no_rawat as id,
        'signout_sebelum_menutup_luka' as type,
        CONCAT('Sign Out Sebelum Menutup Luka (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(sml.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM signout_sebelum_menutup_luka sml
      LEFT JOIN reg_periksa r ON sml.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY sml.no_rawat";
$result_signout_luka = bukaquery_safe($q_signout_luka);
while($row = mysqli_fetch_assoc($result_signout_luka)) {
    $timeline_data[] = $row;
}

// 85. PENILAIAN PRE OPERASI
$q_pre_operasi = "SELECT 
        ppo.no_rawat,
        ppo.no_rawat as id,
        'penilaian_pre_operasi' as type,
        CONCAT('Penilaian Pre Operasi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ppo.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_pre_operasi ppo
      LEFT JOIN reg_periksa r ON ppo.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ppo.no_rawat";
$result_pre_operasi = bukaquery_safe($q_pre_operasi);
while($row = mysqli_fetch_assoc($result_pre_operasi)) {
    $timeline_data[] = $row;
}

// 86. CATATAN ANESTESI SEDASI
$q_anestesi_sedasi = "SELECT 
        cas.no_rawat,
        cas.no_rawat as id,
        'catatan_anestesi_sedasi' as type,
        CONCAT('Catatan Anestesi Sedasi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(cas.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM catatan_anestesi_sedasi cas
      LEFT JOIN reg_periksa r ON cas.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY cas.no_rawat";
$result_anestesi_sedasi = bukaquery_safe($q_anestesi_sedasi);
while($row = mysqli_fetch_assoc($result_anestesi_sedasi)) {
    $timeline_data[] = $row;
}

// 87. PENILAIAN PRE ANESTESI
$q_pre_anestesi = "SELECT 
        ppa.no_rawat,
        ppa.no_rawat as id,
        'penilaian_pre_anestesi' as type,
        CONCAT('Penilaian Pre Anestesi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ppa.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM penilaian_pre_anestesi ppa
      LEFT JOIN reg_periksa r ON ppa.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ppa.no_rawat";
$result_pre_anestesi = bukaquery_safe($q_pre_anestesi);
while($row = mysqli_fetch_assoc($result_pre_anestesi)) {
    $timeline_data[] = $row;
}

// 88. CHECKLIST KESIAPAN ANESTESI
$q_checklist_anestesi = "SELECT 
        cka.no_rawat,
        cka.no_rawat as id,
        'checklist_kesiapan_anestesi' as type,
        CONCAT(DATE_FORMAT(cka.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_kesiapan_anestesi cka
      LEFT JOIN reg_periksa r ON cka.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_checklist_anestesi = bukaquery_safe($q_checklist_anestesi);
while($row = mysqli_fetch_assoc($result_checklist_anestesi)) {
    $timeline_data[] = $row;
}

// 89. SKOR ALDRETTE PASCA ANESTESI
$q_aldrette = "SELECT 
        sapa.no_rawat,
        sapa.no_rawat as id,
        'skor_aldrette_pasca_anestesi' as type,
        CONCAT('Skor Aldrette (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(sapa.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM skor_aldrette_pasca_anestesi sapa
      LEFT JOIN reg_periksa r ON sapa.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY sapa.no_rawat";
$result_aldrette = bukaquery_safe($q_aldrette);
while($row = mysqli_fetch_assoc($result_aldrette)) {
    $timeline_data[] = $row;
}

// 90. SKOR BROMAGE PASCA ANESTESI
$q_bromage = "SELECT 
        sbpa.no_rawat,
        sbpa.no_rawat as id,
        'skor_bromage_pasca_anestesi' as type,
        CONCAT('Skor Bromage (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(sbpa.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM skor_bromage_pasca_anestesi sbpa
      LEFT JOIN reg_periksa r ON sbpa.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY sbpa.no_rawat";
$result_bromage = bukaquery_safe($q_bromage);
while($row = mysqli_fetch_assoc($result_bromage)) {
    $timeline_data[] = $row;
}

// 91. SKOR STEWARD PASCA ANESTESI
$q_steward = "SELECT 
        sspa.no_rawat,
        sspa.no_rawat as id,
        'skor_steward_pasca_anestesi' as type,
        CONCAT('Skor Steward (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(sspa.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM skor_steward_pasca_anestesi sspa
      LEFT JOIN reg_periksa r ON sspa.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY sspa.no_rawat";
$result_steward = bukaquery_safe($q_steward);
while($row = mysqli_fetch_assoc($result_steward)) {
    $timeline_data[] = $row;
}

// 92. CATATAN PENGKAJIAN PASKA OPERASI
$q_paska_operasi = "SELECT 
        cppo.no_rawat,
        cppo.no_rawat as id,
        'catatan_pengkajian_paska_operasi' as type,
        CONCAT(DATE_FORMAT(cppo.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM catatan_pengkajian_paska_operasi cppo
      LEFT JOIN reg_periksa r ON cppo.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_paska_operasi = bukaquery_safe($q_paska_operasi);
while($row = mysqli_fetch_assoc($result_paska_operasi)) {
    $timeline_data[] = $row;
}

// 93. CHECKLIST KRITERIA MASUK HCU
$q_masuk_hcu = "SELECT 
        ckmh.no_rawat,
        ckmh.no_rawat as id,
        'checklist_kriteria_masuk_hcu' as type,
        CONCAT('Checklist Masuk HCU (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ckmh.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_kriteria_masuk_hcu ckmh
      LEFT JOIN reg_periksa r ON ckmh.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckmh.no_rawat";
$result_masuk_hcu = bukaquery_safe($q_masuk_hcu);
while($row = mysqli_fetch_assoc($result_masuk_hcu)) {
    $timeline_data[] = $row;
}

// 94. CHECKLIST KRITERIA MASUK ICU
$q_masuk_icu = "SELECT 
        ckmi.no_rawat,
        ckmi.no_rawat as id,
        'checklist_kriteria_masuk_icu' as type,
        CONCAT('Checklist Masuk ICU (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ckmi.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_kriteria_masuk_icu ckmi
      LEFT JOIN reg_periksa r ON ckmi.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckmi.no_rawat";
$result_masuk_icu = bukaquery_safe($q_masuk_icu);
while($row = mysqli_fetch_assoc($result_masuk_icu)) {
    $timeline_data[] = $row;
}

// 95. CHECKLIST KRITERIA MASUK NICU
$q_masuk_nicu = "SELECT 
        ckmn.no_rawat,
        ckmn.no_rawat as id,
        'checklist_kriteria_masuk_nicu' as type,
        CONCAT('Checklist Masuk NICU (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ckmn.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_kriteria_masuk_nicu ckmn
      LEFT JOIN reg_periksa r ON ckmn.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckmn.no_rawat";
$result_masuk_nicu = bukaquery_safe($q_masuk_nicu);
while($row = mysqli_fetch_assoc($result_masuk_nicu)) {
    $timeline_data[] = $row;
}

// 96. CHECKLIST KRITERIA MASUK PICU
$q_masuk_picu = "SELECT 
        ckmp.no_rawat,
        ckmp.no_rawat as id,
        'checklist_kriteria_masuk_picu' as type,
        CONCAT('Checklist Masuk PICU (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ckmp.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_kriteria_masuk_picu ckmp
      LEFT JOIN reg_periksa r ON ckmp.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckmp.no_rawat";
$result_masuk_picu = bukaquery_safe($q_masuk_picu);
while($row = mysqli_fetch_assoc($result_masuk_picu)) {
    $timeline_data[] = $row;
}

// 97. CHECKLIST KRITERIA KELUAR HCU
$q_keluar_hcu = "SELECT 
        ckkh.no_rawat,
        ckkh.no_rawat as id,
        'checklist_kriteria_keluar_hcu' as type,
        CONCAT('Checklist Keluar HCU (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ckkh.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_kriteria_keluar_hcu ckkh
      LEFT JOIN reg_periksa r ON ckkh.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckkh.no_rawat";
$result_keluar_hcu = bukaquery_safe($q_keluar_hcu);
while($row = mysqli_fetch_assoc($result_keluar_hcu)) {
    $timeline_data[] = $row;
}

// 98. CHECKLIST KRITERIA KELUAR ICU
$q_keluar_icu = "SELECT 
        ckki.no_rawat,
        ckki.no_rawat as id,
        'checklist_kriteria_keluar_icu' as type,
        CONCAT('Checklist Keluar ICU (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ckki.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_kriteria_keluar_icu ckki
      LEFT JOIN reg_periksa r ON ckki.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckki.no_rawat";
$result_keluar_icu = bukaquery_safe($q_keluar_icu);
while($row = mysqli_fetch_assoc($result_keluar_icu)) {
    $timeline_data[] = $row;
}

// 99. CHECKLIST KRITERIA KELUAR NICU
$q_keluar_nicu = "SELECT 
        ckkn.no_rawat,
        ckkn.no_rawat as id,
        'checklist_kriteria_keluar_nicu' as type,
        CONCAT('Checklist Keluar NICU (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ckkn.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_kriteria_keluar_nicu ckkn
      LEFT JOIN reg_periksa r ON ckkn.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckkn.no_rawat";
$result_keluar_nicu = bukaquery_safe($q_keluar_nicu);
while($row = mysqli_fetch_assoc($result_keluar_nicu)) {
    $timeline_data[] = $row;
}

// 100. CHECKLIST KRITERIA KELUAR PICU
$q_keluar_picu = "SELECT 
        ckkp.no_rawat,
        ckkp.no_rawat as id,
        'checklist_kriteria_keluar_picu' as type,
        CONCAT('Checklist Keluar PICU (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ckkp.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM checklist_kriteria_keluar_picu ckkp
      LEFT JOIN reg_periksa r ON ckkp.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ckkp.no_rawat";
$result_keluar_picu = bukaquery_safe($q_keluar_picu);
while($row = mysqli_fetch_assoc($result_keluar_picu)) {
    $timeline_data[] = $row;
}

// 101. HASIL USG KANDUNGAN
$q_usg_kandungan = "SELECT 
        hpu.no_rawat,
        hpu.no_rawat as id,
        'hasil_usg_kandungan' as type,
        CONCAT(DATE_FORMAT(hpu.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_usg hpu
      LEFT JOIN reg_periksa r ON hpu.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpu.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_usg_kandungan = bukaquery_safe($q_usg_kandungan);
while($row = mysqli_fetch_assoc($result_usg_kandungan)) {
    $timeline_data[] = $row;
}

// 102. HASIL USG GYNECOLOGI
$q_usg_gynecologi = "SELECT 
        hpg.no_rawat,
        hpg.no_rawat as id,
        'hasil_usg_gynecologi' as type,
        CONCAT(DATE_FORMAT(hpg.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_usg_gynecologi hpg
      LEFT JOIN reg_periksa r ON hpg.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpg.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_usg_gynecologi = bukaquery_safe($q_usg_gynecologi);
while($row = mysqli_fetch_assoc($result_usg_gynecologi)) {
    $timeline_data[] = $row;
}

// 103. HASIL USG NEONATUS
$q_usg_neonatus = "SELECT 
        hpn.no_rawat,
        hpn.no_rawat as id,
        'hasil_usg_neonatus' as type,
        CONCAT(DATE_FORMAT(hpn.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_usg_neonatus hpn
      LEFT JOIN reg_periksa r ON hpn.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpn.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_usg_neonatus = bukaquery_safe($q_usg_neonatus);
while($row = mysqli_fetch_assoc($result_usg_neonatus)) {
    $timeline_data[] = $row;
}

// 104. HASIL USG UROLOGI
$q_usg_urologi = "SELECT 
        hpu.no_rawat,
        hpu.no_rawat as id,
        'hasil_usg_urologi' as type,
        CONCAT(DATE_FORMAT(hpu.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_usg_urologi hpu
      LEFT JOIN reg_periksa r ON hpu.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpu.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_usg_urologi = bukaquery_safe($q_usg_urologi);
while($row = mysqli_fetch_assoc($result_usg_urologi)) {
    $timeline_data[] = $row;
}

// 105. HASIL PEMERIKSAAN ECHO
$q_echo = "SELECT 
        hpe.no_rawat,
        hpe.no_rawat as id,
        'hasil_pemeriksaan_echo' as type,
        CONCAT(DATE_FORMAT(hpe.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_echo hpe
      LEFT JOIN reg_periksa r ON hpe.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpe.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_echo = bukaquery_safe($q_echo);
while($row = mysqli_fetch_assoc($result_echo)) {
    $timeline_data[] = $row;
}

$q_echo_pediatrik = "SELECT 
        hpep.no_rawat,
        hpep.no_rawat as id,
        'pemeriksaan_echo_pediatrik' as type,
        CONCAT(DATE_FORMAT(hpep.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_echo_pediatrik hpep
      LEFT JOIN reg_periksa r ON hpep.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpep.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_echo_pediatrik = bukaquery_safe($q_echo_pediatrik);
while($row = mysqli_fetch_assoc($result_echo_pediatrik)) {
    $timeline_data[] = $row;
}

// 106. HASIL PEMERIKSAAN EKG
$q_ekg = "SELECT 
        hpekg.no_rawat,
        hpekg.no_rawat as id,
        'hasil_pemeriksaan_ekg' as type,
        CONCAT(DATE_FORMAT(hpekg.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_ekg hpekg
      LEFT JOIN reg_periksa r ON hpekg.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpekg.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_ekg = bukaquery_safe($q_ekg);
while($row = mysqli_fetch_assoc($result_ekg)) {
    $timeline_data[] = $row;
}

// 107. HASIL PEMERIKSAAN SLIT LAMP
$q_slit_lamp = "SELECT 
        hpsl.no_rawat,
        hpsl.no_rawat as id,
        'hasil_pemeriksaan_slit_lamp' as type,
        CONCAT(DATE_FORMAT(hpsl.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_slit_lamp hpsl
      LEFT JOIN reg_periksa r ON hpsl.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpsl.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_slit_lamp = bukaquery_safe($q_slit_lamp);
while($row = mysqli_fetch_assoc($result_slit_lamp)) {
    $timeline_data[] = $row;
}

// 108. HASIL PEMERIKSAAN OCT
$q_oct = "SELECT 
        hpo.no_rawat,
        hpo.no_rawat as id,
        'hasil_pemeriksaan_oct' as type,
        CONCAT(DATE_FORMAT(hpo.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_oct hpo
      LEFT JOIN reg_periksa r ON hpo.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpo.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_oct = bukaquery_safe($q_oct);
while($row = mysqli_fetch_assoc($result_oct)) {
    $timeline_data[] = $row;
}

$q_pemeriksaan_treadmill = "SELECT 
        hpt.no_rawat,
        hpt.no_rawat as id,
        'pemeriksaan_treadmill' as type,
        CONCAT(DATE_FORMAT(hpt.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_pemeriksaan_treadmill hpt
      LEFT JOIN reg_periksa r ON hpt.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hpt.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_pemeriksaan_treadmill = bukaquery_safe($q_pemeriksaan_treadmill);
while($row = mysqli_fetch_assoc($result_pemeriksaan_treadmill)) {
    $timeline_data[] = $row;
}

// 109. HASIL ENDOSKOPI FARING LARING
$q_endo_faring = "SELECT 
        hefl.no_rawat,
        hefl.no_rawat as id,
        'hasil_endoskopi_faring_laring' as type,
        CONCAT(DATE_FORMAT(hefl.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_endoskopi_faring_laring hefl
      LEFT JOIN reg_periksa r ON hefl.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON hefl.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_endo_faring = bukaquery_safe($q_endo_faring);
while($row = mysqli_fetch_assoc($result_endo_faring)) {
    $timeline_data[] = $row;
}

// 110. HASIL ENDOSKOPI HIDUNG
$q_endo_hidung = "SELECT 
        heh.no_rawat,
        heh.no_rawat as id,
        'hasil_endoskopi_hidung' as type,
        CONCAT(DATE_FORMAT(heh.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_endoskopi_hidung heh
      LEFT JOIN reg_periksa r ON heh.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON heh.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_endo_hidung = bukaquery_safe($q_endo_hidung);
while($row = mysqli_fetch_assoc($result_endo_hidung)) {
    $timeline_data[] = $row;
}

// 111. HASIL ENDOSKOPI TELINGA
$q_endo_telinga = "SELECT 
        het.no_rawat,
        het.no_rawat as id,
        'hasil_endoskopi_telinga' as type,
        CONCAT(DATE_FORMAT(het.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM hasil_endoskopi_telinga het
      LEFT JOIN reg_periksa r ON het.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON het.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_endo_telinga = bukaquery_safe($q_endo_telinga);
while($row = mysqli_fetch_assoc($result_endo_telinga)) {
    $timeline_data[] = $row;
}

// 112. CATATAN PERSALINAN
$q_catatan_persalinan = "SELECT 
        cp.no_rawat,
        cp.no_rawat as id,
        'catatan_persalinan' as type,
        CONCAT(IFNULL(p.nama, 'Petugas'), ' | ', IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM catatan_persalinan cp
      LEFT JOIN reg_periksa r ON cp.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON cp.nip = p.nip
      LEFT JOIN dokter d ON cp.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_catatan_persalinan = bukaquery_safe($q_catatan_persalinan);
while($row = mysqli_fetch_assoc($result_catatan_persalinan)) {
    $timeline_data[] = $row;
}

// 113. LAPORAN TINDAKAN
$q_laporan_tindakan = "SELECT 
        lt.no_rawat,
        lt.no_rawat as id,
        'laporan_tindakan' as type,
        CONCAT(DATE_FORMAT(lt.tanggal, '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM laporan_tindakan lt
      LEFT JOIN reg_periksa r ON lt.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_laporan_tindakan = bukaquery_safe($q_laporan_tindakan);
while($row = mysqli_fetch_assoc($result_laporan_tindakan)) {
    $timeline_data[] = $row;
}

// 114. HASIL TINDAKAN ESWL
$q_hasil_eswl = "SELECT 
        hte.no_rawat,
        hte.no_rawat as id,
        'hasil_tindakan_eswl' as type,
        'Hasil Tindakan ESWL' as subtitle
      FROM hasil_tindakan_eswl hte
      LEFT JOIN reg_periksa r ON hte.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_hasil_eswl = bukaquery_safe($q_hasil_eswl);
while($row = mysqli_fetch_assoc($result_hasil_eswl)) {
    $timeline_data[] = $row;
}

// 115. PENILAIAN PASIEN TERMINAL
$q_pasien_terminal = "SELECT 
        ppt.no_rawat,
        ppt.no_rawat as id,
        'penilaian_pasien_terminal' as type,
        CONCAT(DATE_FORMAT(ppt.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pg.nama, 'Pegawai')) as subtitle
      FROM penilaian_pasien_terminal ppt
      LEFT JOIN reg_periksa r ON ppt.no_rawat = r.no_rawat
      LEFT JOIN pegawai pg ON ppt.nip = pg.nik
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_pasien_terminal = bukaquery_safe($q_pasien_terminal);
while($row = mysqli_fetch_assoc($result_pasien_terminal)) {
    $timeline_data[] = $row;
}

// 116. PENILAIAN LEVEL KECEMASAN RANAP ANAK
$q_kecemasan_anak = "SELECT 
        plkra.no_rawat,
        plkra.no_rawat as id,
        'penilaian_level_kecemasan_ranap_anak' as type,
        CONCAT(DATE_FORMAT(plkra.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM penilaian_level_kecemasan_ranap_anak plkra
      LEFT JOIN reg_periksa r ON plkra.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON plkra.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_kecemasan_anak = bukaquery_safe($q_kecemasan_anak);
while($row = mysqli_fetch_assoc($result_kecemasan_anak)) {
    $timeline_data[] = $row;
}

// 117. PENILAIAN KORBAN KEKERASAN
$q_korban_kekerasan = "SELECT 
        pkk.no_rawat,
        pkk.no_rawat as id,
        'penilaian_korban_kekerasan' as type,
        CONCAT(DATE_FORMAT(pkk.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(pg.nama, 'Pegawai')) as subtitle
      FROM penilaian_korban_kekerasan pkk
      LEFT JOIN reg_periksa r ON pkk.no_rawat = r.no_rawat
      LEFT JOIN pegawai pg ON pkk.nip = pg.nik
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_korban_kekerasan = bukaquery_safe($q_korban_kekerasan);
while($row = mysqli_fetch_assoc($result_korban_kekerasan)) {
    $timeline_data[] = $row;
}

// 118. PENILAIAN PASIEN PENYAKIT MENULAR
$q_penyakit_menular = "SELECT 
        pppm.no_rawat,
        pppm.no_rawat as id,
        'penilaian_pasien_penyakit_menular' as type,
        CONCAT(DATE_FORMAT(pppm.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_pasien_penyakit_menular pppm
      LEFT JOIN reg_periksa r ON pppm.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pppm.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_penyakit_menular = bukaquery_safe($q_penyakit_menular);
while($row = mysqli_fetch_assoc($result_penyakit_menular)) {
    $timeline_data[] = $row;
}

// 119. PENILAIAN PASIEN IMUNITAS RENDAH
$q_imunitas_rendah = "SELECT 
        ppir.no_rawat,
        ppir.no_rawat as id,
        'penilaian_pasien_imunitas_rendah' as type,
        CONCAT(DATE_FORMAT(ppir.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_pasien_imunitas_rendah ppir
      LEFT JOIN reg_periksa r ON ppir.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON ppir.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_imunitas_rendah = bukaquery_safe($q_imunitas_rendah);
while($row = mysqli_fetch_assoc($result_imunitas_rendah)) {
    $timeline_data[] = $row;
}

// 120. PENILAIAN DEHIDRASI
$q_dehidrasi = "SELECT 
        pd.no_rawat,
        pd.no_rawat as id,
        'penilaian_dehidrasi' as type,
        CONCAT(DATE_FORMAT(pd.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_dehidrasi pd
      LEFT JOIN reg_periksa r ON pd.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pd.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_dehidrasi = bukaquery_safe($q_dehidrasi);
while($row = mysqli_fetch_assoc($result_dehidrasi)) {
    $timeline_data[] = $row;
}

// 121. PENGKAJIAN PASIEN KERACUNAN
$q_keracunan = "SELECT 
        pk.no_rawat,
        pk.no_rawat as id,
        'pengkajian_keracunan' as type,
        CONCAT(DATE_FORMAT(pk.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(d.nm_dokter, 'Dokter')) as subtitle
      FROM penilaian_pasien_keracunan pk
      LEFT JOIN reg_periksa r ON pk.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON pk.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_keracunan = bukaquery_safe($q_keracunan);
while($row = mysqli_fetch_assoc($result_keracunan)) {
    $timeline_data[] = $row;
}

// 122. SKRINING NUTRISI DEWASA
$q_skrining_nutrisi_dewasa = "SELECT 
        snd.no_rawat,
        snd.no_rawat as id,
        'skrining_nutrisi_dewasa' as type,
        CONCAT(DATE_FORMAT(snd.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM skrining_nutrisi_dewasa snd
      LEFT JOIN reg_periksa r ON snd.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON snd.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_skrining_nutrisi_dewasa = bukaquery_safe($q_skrining_nutrisi_dewasa);
while($row = mysqli_fetch_assoc($result_skrining_nutrisi_dewasa)) {
    $timeline_data[] = $row;
}

// 123. SKRINING NUTRISI LANSIA
$q_skrining_nutrisi_lansia = "SELECT 
        snl.no_rawat,
        snl.no_rawat as id,
        'skrining_nutrisi_lansia' as type,
        CONCAT(DATE_FORMAT(snl.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM skrining_nutrisi_lansia snl
      LEFT JOIN reg_periksa r ON snl.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON snl.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_skrining_nutrisi_lansia = bukaquery_safe($q_skrining_nutrisi_lansia);
while($row = mysqli_fetch_assoc($result_skrining_nutrisi_lansia)) {
    $timeline_data[] = $row;
}

// 124. SKRINING NUTRISI ANAK
$q_skrining_nutrisi_anak = "SELECT 
        sna.no_rawat,
        sna.no_rawat as id,
        'skrining_nutrisi_anak' as type,
        CONCAT(DATE_FORMAT(sna.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM skrining_nutrisi_anak sna
      LEFT JOIN reg_periksa r ON sna.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON sna.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_skrining_nutrisi_anak = bukaquery_safe($q_skrining_nutrisi_anak);
while($row = mysqli_fetch_assoc($result_skrining_nutrisi_anak)) {
    $timeline_data[] = $row;
}

// SKRINING GIZI
$q_skrining_gizi = "SELECT 
        sg.no_rawat,
        sg.no_rawat as id,
        'skrining_gizi' as type,
        CONCAT('Skrining Gizi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(sg.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM skrining_gizi sg
      LEFT JOIN reg_periksa r ON sg.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY sg.no_rawat";
$result_skrining_gizi = bukaquery_safe($q_skrining_gizi);
while($row = mysqli_fetch_assoc($result_skrining_gizi)) {
    $timeline_data[] = $row;
}

// ASUHAN GIZI
$q_asuhan_gizi = "SELECT 
        ag.no_rawat,
        ag.no_rawat as id,
        'asuhan_gizi' as type,
        CONCAT('Asuhan Gizi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(ag.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM asuhan_gizi ag
      LEFT JOIN reg_periksa r ON ag.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ag.no_rawat";
$result_asuhan_gizi = bukaquery_safe($q_asuhan_gizi);
while($row = mysqli_fetch_assoc($result_asuhan_gizi)) {
    $timeline_data[] = $row;
}

// MONITORING ASUHAN GIZI
$q_monitoring_asuhan_gizi = "SELECT 
        mag.no_rawat,
        mag.no_rawat as id,
        'monitoring_asuhan_gizi' as type,
        CONCAT('Monitoring Asuhan Gizi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(mag.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM monitoring_asuhan_gizi mag
      LEFT JOIN reg_periksa r ON mag.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY mag.no_rawat";
$result_monitoring_asuhan_gizi = bukaquery_safe($q_monitoring_asuhan_gizi);
while($row = mysqli_fetch_assoc($result_monitoring_asuhan_gizi)) {
    $timeline_data[] = $row;
}

// CATATAN ADIME GIZI
$q_catatan_adime_gizi = "SELECT 
        cag.no_rawat,
        cag.no_rawat as id,
        'catatan_adime_gizi' as type,
        CONCAT('Catatan ADIME Gizi (', COUNT(*), 'x) | Terakhir: ', 
               DATE_FORMAT(MAX(cag.tanggal), '%d-%m-%Y %H:%i'), ' WIB') as subtitle
      FROM catatan_adime_gizi cag
      LEFT JOIN reg_periksa r ON cag.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY cag.no_rawat";
$result_catatan_adime_gizi = bukaquery_safe($q_catatan_adime_gizi);
while($row = mysqli_fetch_assoc($result_catatan_adime_gizi)) {
    $timeline_data[] = $row;
}

// 129. CHECKLIST PEMBERIAN FIBRINOLITIK
$q_fibrinolitik = "SELECT 
        cpf.no_rawat,
        cpf.no_rawat as id,
        'checklist_pemberian_fibrinolitik' as type,
        CONCAT(DATE_FORMAT(cpf.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM checklist_pemberian_fibrinolitik cpf
      LEFT JOIN reg_periksa r ON cpf.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON cpf.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_fibrinolitik = bukaquery_safe($q_fibrinolitik);
while($row = mysqli_fetch_assoc($result_fibrinolitik)) {
    $timeline_data[] = $row;
}

// 130. KONSELING FARMASI
$q_konseling_farmasi = "SELECT 
        kf.no_rawat,
        kf.no_rawat as id,
        'konseling_farmasi' as type,
        CONCAT(DATE_FORMAT(kf.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM konseling_farmasi kf
      LEFT JOIN reg_periksa r ON kf.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON kf.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_konseling_farmasi = bukaquery_safe($q_konseling_farmasi);
while($row = mysqli_fetch_assoc($result_konseling_farmasi)) {
    $timeline_data[] = $row;
}

// 131. REKONSILIASI OBAT
$q_rekonsiliasi_obat = "SELECT 
        ro.no_rawat,
        ro.no_rawat as id,
        'rekonsiliasi_obat' as type,
        CONCAT('Rekonsiliasi Obat (', COUNT(*), 'x)') as subtitle
      FROM rekonsiliasi_obat ro
      LEFT JOIN reg_periksa r ON ro.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY ro.no_rawat";
$result_rekonsiliasi_obat = bukaquery_safe($q_rekonsiliasi_obat);
while($row = mysqli_fetch_assoc($result_rekonsiliasi_obat)) {
    $timeline_data[] = $row;
}

// 132. TRANSFER PASIEN ANTAR RUANG
$q_transfer_pasien = "SELECT 
        tpar.no_rawat,
        tpar.no_rawat as id,
        'transfer_pasien_antar_ruang' as type,
        'Transfer Pasien Antar Ruang' as subtitle
      FROM transfer_pasien_antar_ruang tpar
      LEFT JOIN reg_periksa r ON tpar.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_transfer_pasien = bukaquery_safe($q_transfer_pasien);
while($row = mysqli_fetch_assoc($result_transfer_pasien)) {
    $timeline_data[] = $row;
}

// 133. PENGKAJIAN RESTRAIN
$q_restrain = "SELECT 
        pr.no_rawat,
        pr.no_rawat as id,
        'pengkajian_restrain' as type,
        CONCAT(DATE_FORMAT(pr.tanggal, '%d-%m-%Y %H:%i'), ' WIB | ', 
               IFNULL(p.nama, 'Petugas')) as subtitle
      FROM pengkajian_restrain pr
      LEFT JOIN reg_periksa r ON pr.no_rawat = r.no_rawat
      LEFT JOIN petugas p ON pr.nip = p.nip
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_restrain = bukaquery_safe($q_restrain);
while($row = mysqli_fetch_assoc($result_restrain)) {
    $timeline_data[] = $row;
}

// SOAPIE
$q_soapie = "SELECT 
        r.no_rawat as no_rawat,
        r.no_rawat as id,
        'soapie' as type,
        CASE 
            WHEN prl.no_rawat IS NOT NULL AND prn.no_rawat IS NOT NULL THEN 'SOAPIE Ralan & Ranap'
            WHEN prl.no_rawat IS NOT NULL THEN 'SOAPIE Ralan'
            WHEN prn.no_rawat IS NOT NULL THEN 'SOAPIE Ranap'
        END as subtitle
      FROM reg_periksa r
      LEFT JOIN pemeriksaan_ralan prl ON r.no_rawat = prl.no_rawat
      LEFT JOIN pemeriksaan_ranap prn ON r.no_rawat = prn.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      AND (prl.no_rawat IS NOT NULL OR prn.no_rawat IS NOT NULL)
      GROUP BY r.no_rawat";
$result_soapie = bukaquery_safe($q_soapie);
while($row = mysqli_fetch_assoc($result_soapie)) {
    $timeline_data[] = $row;
}

// RESEP OBAT
$q_resep = "SELECT 
        ro.no_rawat as no_rawat,
        ro.no_rawat as id,
        'resep_obat' as type,
        'Resep Obat' as subtitle
      FROM reg_periksa r
      LEFT JOIN resep_obat ro ON r.no_rawat = ro.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      AND ro.no_rawat IS NOT NULL
      GROUP BY ro.no_rawat";
$result_resep = bukaquery_safe($q_resep);
while($row = mysqli_fetch_assoc($result_resep)) {
    $timeline_data[] = $row;
}

// LAB (PK, MB & PA)
$q_lab = "SELECT 
        r.no_rawat as no_rawat,
        r.no_rawat as id,
        'lab' as type,
        CASE 
            WHEN dpl.no_rawat IS NOT NULL AND dlpa.no_rawat IS NOT NULL THEN 'Lab PK/MB & PA'
            WHEN dpl.no_rawat IS NOT NULL THEN 'Lab PK/MB'
            WHEN dlpa.no_rawat IS NOT NULL THEN 'Lab PA'
        END as subtitle
      FROM reg_periksa r
      LEFT JOIN detail_periksa_lab dpl ON r.no_rawat = dpl.no_rawat
      LEFT JOIN detail_periksa_labpa dlpa ON r.no_rawat = dlpa.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      AND (dpl.no_rawat IS NOT NULL OR dlpa.no_rawat IS NOT NULL)
      GROUP BY r.no_rawat";
$result_lab = bukaquery_safe($q_lab);
while($row = mysqli_fetch_assoc($result_lab)) {
    $timeline_data[] = $row;
}

// RADIOLOGI
$q_radiologi = "SELECT 
        r.no_rawat as no_rawat,
        r.no_rawat as id,
        'radiologi' as type,
        'Radiologi' as subtitle
      FROM reg_periksa r
      LEFT JOIN periksa_radiologi pr ON r.no_rawat = pr.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      AND pr.no_rawat IS NOT NULL
      GROUP BY r.no_rawat";
$result_radiologi = bukaquery_safe($q_radiologi);
while($row = mysqli_fetch_assoc($result_radiologi)) {
    $timeline_data[] = $row;
}

// TINDAKAN & PERAWATAN
$q_tindakan = "SELECT 
        COALESCE(rjd.no_rawat, rjdp.no_rawat, rjp.no_rawat, rid.no_rawat, ridp.no_rawat, rip.no_rawat) as no_rawat,
        COALESCE(rjd.no_rawat, rjdp.no_rawat, rjp.no_rawat, rid.no_rawat, ridp.no_rawat, rip.no_rawat) as id,
        'tindakan_perawatan' as type,
        'Tindakan & Perawatan' as subtitle
      FROM reg_periksa r
      LEFT JOIN rawat_jl_dr rjd ON r.no_rawat = rjd.no_rawat
      LEFT JOIN rawat_jl_drpr rjdp ON r.no_rawat = rjdp.no_rawat
      LEFT JOIN rawat_jl_pr rjp ON r.no_rawat = rjp.no_rawat
      LEFT JOIN rawat_inap_dr rid ON r.no_rawat = rid.no_rawat
      LEFT JOIN rawat_inap_drpr ridp ON r.no_rawat = ridp.no_rawat
      LEFT JOIN rawat_inap_pr rip ON r.no_rawat = rip.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      AND (rjd.no_rawat IS NOT NULL 
           OR rjdp.no_rawat IS NOT NULL 
           OR rjp.no_rawat IS NOT NULL 
           OR rid.no_rawat IS NOT NULL 
           OR ridp.no_rawat IS NOT NULL 
           OR rip.no_rawat IS NOT NULL)
      GROUP BY COALESCE(rjd.no_rawat, rjdp.no_rawat, rjp.no_rawat, rid.no_rawat, ridp.no_rawat, rip.no_rawat)";
$result_tindakan = bukaquery_safe($q_tindakan);
while($row = mysqli_fetch_assoc($result_tindakan)) {
    $timeline_data[] = $row;
}

// OPERASI/VK
$q_operasi = "SELECT 
        r.no_rawat as no_rawat,
        r.no_rawat as id,
        'operasi' as type,
        'Operasi/VK' as subtitle
      FROM reg_periksa r
      LEFT JOIN operasi o ON r.no_rawat = o.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      AND o.no_rawat IS NOT NULL
      GROUP BY r.no_rawat";
$result_operasi = bukaquery_safe($q_operasi);
while($row = mysqli_fetch_assoc($result_operasi)) {
    $timeline_data[] = $row;
}

// RESUME RANAP
$q_resume_ranap = "SELECT 
        rpr.no_rawat,
        rpr.no_rawat as id,
        'resume_ranap' as type,
        IFNULL(d.nm_dokter, 'Dokter') as subtitle
      FROM resume_pasien_ranap rpr
      LEFT JOIN reg_periksa r ON rpr.no_rawat = r.no_rawat
      LEFT JOIN dokter d ON rpr.kd_dokter = d.kd_dokter
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat";
$result_resume_ranap = bukaquery_safe($q_resume_ranap);
while($row = mysqli_fetch_assoc($result_resume_ranap)) {
    $timeline_data[] = $row;
}

$q_berkas_digital = "SELECT 
        bdp.no_rawat,
        bdp.no_rawat as id,
        'berkas_digital' as type,
        COUNT(bdp.kode) as jumlah_berkas,
        GROUP_CONCAT(bdp.kode ORDER BY bdp.kode SEPARATOR ', ') as kode_berkas,
        CONCAT('Berkas Digital (', COUNT(bdp.kode), ' file)') as subtitle
      FROM berkas_digital_perawatan bdp
      LEFT JOIN reg_periksa r ON bdp.no_rawat = r.no_rawat
      WHERE r.no_rkm_medis = '$no_rm'
      $where_rawat
      GROUP BY bdp.no_rawat
      ORDER BY bdp.no_rawat";
      
$result_berkas_digital = bukaquery_safe($q_berkas_digital);
while($row = mysqli_fetch_assoc($result_berkas_digital)) {
    $timeline_data[] = $row;
}
// SORT HANYA BERDASARKAN PRIORITY (STATIC ORDER)
// usort($timeline_data, function($a, $b) {
//     return ($a['priority'] ?? 99) - ($b['priority'] ?? 99);
// });

// =====================================================
// CONFIG UNTUK SETIAP TYPE - MUDAH DITAMBAHKAN
// =====================================================
// FORMAT:
// 'kode_type' => [
//     'title' => 'Judul yang Ditampilkan',
//     'icon' => 'fa-icon-name',
//     'color' => 'primary|danger|success|warning|info|secondary',
//     'endpoint' => 'pages/riwayat/riwayat_namafile.php'
// ],

$config = [
    'registrasi_pasien' => [
        'title' => 'Registrasi Pasien',
        'icon' => 'fa-user-plus',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_registrasi.php'
    ],
    'triase_igd' => [
        'title' => 'Triase IGD',
        'icon' => 'fa-heartbeat',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_triaseigd.php'
    ],
    'keperawatan_igd' => [
        'title' => 'Penilaian Awal Keperawatan IGD',
        'icon' => 'fa-stethoscope',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_awalkeperawatanigd.php'
    ],
    'penilaian_medis_igd' => [
        'title' => 'Penilaian Medis IGD',
        'icon' => 'fa-user-md',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_penilaianmedisigd.php'
    ],
    'penilaian_medis_igd_psikiatri' => [
        'title' => 'Penilaian Medis IGD Psikiatri',
        'icon' => 'fa-user-md',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_penilaianmedisigdpsikiatri.php'
    ],
    'pengkajian_keracunan' => [
        'title' => 'Pengkajian Pasien Keracunan',
        'icon' => 'fa-user-md',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_pengkajiankeracunan.php'
    ],
    'pengkajian_restrain' => [
        'title' => 'Pengkajian Restrain',
        'icon' => 'fa-stethoscope',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianrestrain.php'
    ],
    'pemantauan_pews_anak' => [
        'title' => 'Pemantauan PEWS Anak',
        'icon' => 'fa-child',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_pemantauanpewsanak.php'
    ],
    'pemantauan_ews_dewasa' => [
        'title' => 'Pemantauan EWS Dewasa',
        'icon' => 'fa-stethoscope',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_pemantauanewsdewasa.php'
    ],
    'pemantauan_meows_obstetri' => [
        'title' => 'Pemantauan MEOWS Obstetri',
        'icon' => 'fa-stethoscope',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_pemantauanmeowsobstetri.php'
    ],
    'pemantauan_ews_neonatus' => [
        'title' => 'Pemantauan EWS Pasien Neonatus',
        'icon' => 'fa-stethoscope',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_pemantauanewsneonatus.php'
    ],
    'penilaian_awal_keperawatan_ralan' => [
        'title' => 'Penilaian Awal Keperawatan Ralan',
        'icon' => 'fa-stethoscope',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatanralan.php'
    ],
    'penilaian_awal_keperawatan_gigi' => [
        'title' => 'Penilaian Awal Keperawatan Gigi',
        'icon' => 'fa-stethoscope',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatangigi.php'
    ],
    'penilaian_awal_keperawatan_kebidanan' => [
        'title' => 'Penilaian Awal Keperawatan Kebidanan & Kandungan',
        'icon' => 'fa-stethoscope',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatankebidanan.php'
    ],
    'penilaian_awal_keperawatan_bayi' => [
        'title' => 'Penilaian Awal Keperawatan Bayi/Anak',
        'icon' => 'fa-stethoscope',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatanbayi.php'
    ],
    'penilaian_awal_keperawatan_psikiatri' => [
        'title' => 'Penilaian Awal Keperawatan Psikiatri',
        'icon' => 'fa-stethoscope',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatanpsikiatri.php'
    ],
    'penilaian_awal_keperawatan_geriatri' => [
        'title' => 'Penilaian Awal Keperawatan Geriatri',
        'icon' => 'fa-stethoscope',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatangeriatri.php'
    ],
    'pengkajian_medis_umum' => [
        'title' => 'Pengkajian Awal Medis Umum',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisumum.php'
    ],
    'pengkajian_medis_kandungan' => [
        'title' => 'Pengkajian Awal Medis Kebidanan & Kandungan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmediskandungan.php'
    ],
    'pengkajian_medis_anak' => [
        'title' => 'Pengkajian Awal Medis Bayi/Anak',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisanak.php'
    ],
    'pengkajian_medis_tht' => [
        'title' => 'Pengkajian Awal Medis THT',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedistht.php'
    ],
    'pengkajian_medis_psikiatri' => [
        'title' => 'Pengkajian Awal Medis Psikiatri',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedispsikiatri.php'
    ],
    'pengkajian_medis_penyakit_dalam' => [
        'title' => 'Pengkajian Awal Medis Penyakit Dalam',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedispenyakitdalam.php'
    ],
    'pengkajian_medis_mata' => [
        'title' => 'Pengkajian Awal Medis Mata',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedismata.php'
    ],
    'pengkajian_medis_neurologi' => [
        'title' => 'Pengkajian Awal Medis Neurologi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisneurologi.php'
    ],
    'pengkajian_medis_orthopedi' => [
        'title' => 'Pengkajian Awal Medis Orthopedi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisorthopedi.php'
    ],
    'pengkajian_medis_paru' => [
        'title' => 'Pengkajian Awal Medis Paru',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisparu.php'
    ],
    'pengkajian_medis_bedah' => [
        'title' => 'Pengkajian Awal Medis Bedah',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisbedah.php'
    ],
    'pengkajian_medis_bedah_mulut' => [
        'title' => 'Pengkajian Awal Medis Bedah Mulut',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisbedahmulut.php'
    ],
    'pengkajian_medis_geriatri' => [
        'title' => 'Pengkajian Awal Medis Geriatri',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisgeriatri.php'
    ],
    'pengkajian_medis_kulit' => [
        'title' => 'Pengkajian Awal Medis Kulit & Kelamin',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmediskulit.php'
    ],
    'pengkajian_medis_jantung' => [
    'title'    => 'Pengkajian Awal Medis Jantung',
    'icon'     => 'fa-heartbeat',
    'color'    => 'danger',
    'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisjantung.php'
    ],
    'pengkajian_medis_hemodialisa' => [
        'title'    => 'Pengkajian Awal Medis Hemodialisa',
        'icon'     => 'fa-tint',
        'color'    => 'info',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedishemodialisa.php'
    ],
    'pengkajian_medis_hemodialisa' => [
        'title' => 'Pengkajian Awal Medis Hemodialisa',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedishemodialisa.php'
    ],
    'pengkajian_medis_rehab' => [
        'title' => 'Pengkajian Awal Medis Fisik & Rehabilitasi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisrehab.php'
    ],
    'hasil_usg_kandungan' => [
        'title' => 'Hasil USG Kandungan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilusgkandungan.php'
    ],
    'hasil_usg_gynecologi' => [
        'title' => 'Hasil USG Gynecologi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilusggynecologi.php'
    ],
    'hasil_usg_neonatus' => [
        'title' => 'Hasil USG Neonatus',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilusgneonatus.php'
    ],
    'hasil_usg_urologi' => [
        'title' => 'Hasil USG Urologi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilusgurologi.php'
    ],
    'hasil_endoskopi_faring_laring' => [
        'title' => 'Hasil Endoskopi Faring Laring',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilendoskopifaring.php'
    ],
    'hasil_endoskopi_hidung' => [
        'title' => 'Hasil Endoskopi Hidung',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilendoskopihidung.php'
    ],
    'hasil_endoskopi_telinga' => [
        'title' => 'Hasil Endoskopi Telinga',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilendoskopitelinga.php'
    ],
    'hasil_pemeriksaan_echo' => [
        'title' => 'Hasil Pemeriksaan Echo',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilecho.php'
    ],
    'pemeriksaan_echo_pediatrik' => [
        'title'    => 'Pemeriksaan Echo Pediatrik',
        'icon'     => 'fa-child',
        'color'    => 'warning',
        'endpoint' => 'pages/riwayat/riwayat_echopediatrik.php'
    ],
    'hasil_pemeriksaan_ekg' => [
        'title' => 'Hasil Pemeriksaan EKG',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilekg.php'
    ],
    'hasil_pemeriksaan_slit_lamp' => [
        'title' => 'Hasil Pemeriksaan Slit Lamp',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasilslitlamp.php'
    ],
    'hasil_pemeriksaan_oct' => [
        'title' => 'Hasil Pemeriksaan OCT',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasiloct.php'
    ],
    'pemeriksaan_treadmill' => [
        'title'    => 'Pemeriksaan Treadmill',
        'icon'     => 'fa-running',
        'color'    => 'success',
        'endpoint' => 'pages/riwayat/riwayat_pemeriksaantreadmill.php'
    ],
    'hasil_tindakan_eswl' => [
        'title' => 'Hasil Tindakan ESWL',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasileswl.php'
    ],
    'penilaian_psikologi' => [
        'title' => 'Penilaian Psikologi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianpsikologi.php'
    ],
    'penilaian_psikologi_klinis' => [
        'title' => 'Penilaian Psikologi Klinis',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianpsikologiklinis.php'
    ],
    'penilaian_pre_induksi' => [
        'title' => 'Penilaian Pre Induksi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianpreinduksi.php'
    ],
    'checklist_pre_operasi' => [
        'title' => 'Checklist Pre Operasi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistpreoperasi.php'
    ],
    'signin_sebelum_anestesi' => [
        'title' => 'Sign In Sebelum Anestesi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_signinanestesi.php'
    ],
    'timeout_sebelum_insisi' => [
        'title' => 'Time Out Sebelum Insisi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_timeoutinsisi.php'
    ],
    'signout_sebelum_menutup_luka' => [
        'title' => 'Sign Out Sebelum Menutup Luka',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_signoutluka.php'
    ],
    'penilaian_pre_operasi' => [
        'title' => 'Penilaian Pre Operasi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianpreoperasi.php'
    ],
    'catatan_anestesi_sedasi' => [
        'title' => 'Catatan Anestesi Sedasi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatananestesisedasi.php'
    ],
    'penilaian_pre_anestesi' => [
        'title' => 'Penilaian Pre Anestesi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianpreanestesi.php'
    ],
    'checklist_kesiapan_anestesi' => [
        'title' => 'Checklist Kesiapan Anestesi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistanestesi.php'
    ],
    'skor_aldrette_pasca_anestesi' => [
        'title' => 'Skor Aldrette Pasca Anestesi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_skoraldrette.php'
    ],
    'skor_bromage_pasca_anestesi' => [
        'title' => 'Skor Bromage Pasca Anestesi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_skorbromage.php'
    ],
    'skor_steward_pasca_anestesi' => [
        'title' => 'Skor Steward Pasca Anestesi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_skorsteward.php'
    ],
    'catatan_pengkajian_paska_operasi' => [
        'title' => 'Catatan Pengkajian Paska Operasi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanpaskaoperasi.php'
    ],
    'checklist_kriteria_masuk_hcu' => [
        'title' => 'Checklist Kriteria Masuk HCU',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistmasukhcu.php'
    ],
    'checklist_kriteria_masuk_icu' => [
        'title' => 'Checklist Kriteria Masuk ICU',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistmasukicu.php'
    ],
    'checklist_kriteria_masuk_nicu' => [
        'title' => 'Checklist Kriteria Masuk NICU',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistmasuknicu.php'
    ],
    'checklist_kriteria_masuk_picu' => [
        'title' => 'Checklist Kriteria Masuk PICU',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistmasukpicu.php'
    ],
    'checklist_kriteria_keluar_hcu' => [
        'title' => 'Checklist Kriteria Keluar HCU',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistkeluarhcu.php'
    ],
    'checklist_kriteria_keluar_icu' => [
        'title' => 'Checklist Kriteria Keluar ICU',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistkeluaricu.php'
    ],
    'checklist_kriteria_keluar_nicu' => [
        'title' => 'Checklist Kriteria Keluar NICU',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistkeluarnicu.php'
    ],
    'checklist_kriteria_keluar_picu' => [
        'title' => 'Checklist Kriteria Keluar PICU',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistkeluarpicu.php'
    ],
    'penilaian_fisioterapi' => [
        'title' => 'Penilaian Fisioterapi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiinfisioterapi.php'
    ],
    'penilaian_terapi_wicara' => [
        'title' => 'Penilaian Terapi Wicara',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianterapiwicara.php'
    ],
    'penatalaksanaan_terapi_okupasi' => [
        'title' => 'Penatalaksanaan Terapi Okupasi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penatalaksanaanterapiokupasi.php'
    ],
    'layanan_kedokteran_fisik_rehabilitasi' => [
        'title' => 'Layanan Kedokteran Fisik Rehabilitasi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_layanankfr.php'
    ],
    'uji_fungsi_kfr' => [
        'title' => 'Uji Fungsi KFR',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_ujifungsikfr.php'
    ],
    'penilaian_lanjutan_resiko_jatuh_anak' => [
        'title' => 'Penilaian Lanjutan Resiko Jatuh Anak',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianresikojatuhanak.php'
    ],
    'penilaian_lanjutan_resiko_jatuh_dewasa' => [
        'title' => 'Penilaian Lanjutan Resiko Jatuh Dewasa',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianresikojatuhdewasa.php'
    ],
    'penilaian_lanjutan_resiko_jatuh_geriatri' => [
        'title' => 'Penilaian Lanjutan Resiko Jatuh Geriatri',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianresikojatuhgeriatri.php'
    ],
    'penilaian_lanjutan_resiko_jatuh_lansia' => [
        'title' => 'Penilaian Lanjutan Resiko Jatuh Lansia',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianresikojatuhlansia.php'
    ],
    'penilaian_risiko_jatuh_neonatus' => [
        'title' => 'Penilaian Risiko Jatuh Neonatus',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianresikojatuhneonatus.php'
    ],
    'penilaian_lanjutan_resiko_jatuh_psikiatri' => [
        'title' => 'Penilaian Lanjutan Resiko Jatuh Psikiatri',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianresikojatuhpsikiatri.php'
    ],
    'penilaian_lanjutan_skrining_fungsional' => [
        'title' => 'Penilaian Lanjutan Skrining Fungsional',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianskrininfungsional.php'
    ],
    'penilaian_tambahan_geriatri' => [
        'title' => 'Penilaian Tambahan Geriatri',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiantambahangeriatri.php'
    ],
    'penilaian_tambahan_bunuh_diri' => [
        'title' => 'Penilaian Tambahan Bunuh Diri',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiantambahanbunuhdiri.php'
    ],
    'penilaian_tambahan_perilaku_kekerasan' => [
        'title' => 'Penilaian Tambahan Perilaku Kekerasan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiantambahankekerasan.php'
    ],
    'penilaian_tambahan_beresiko_melarikan_diri' => [
        'title' => 'Penilaian Tambahan Beresiko Melarikan Diri',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiantambahanmelarikandiri.php'
    ],
    'penilaian_pasien_terminal' => [
        'title' => 'Penilaian Pasien Terminal',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianpasienterminal.php'
    ],
    'penilaian_korban_kekerasan' => [
        'title' => 'Penilaian Korban Kekerasan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankorbankekerasan.php'
    ],
    'penilaian_pasien_penyakit_menular' => [
        'title' => 'Penilaian Pasien Penyakit Menular',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianpenyakitmenular.php'
    ],
    'penilaian_pasien_imunitas_rendah' => [
        'title' => 'Penilaian Pasien Imunitas Rendah',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianimunitasrendah.php'
    ],
    'penilaian_dehidrasi' => [
        'title' => 'Penilaian Dehidrasi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiandehidrasi.php'
    ],
    'penilaian_mcu' => [
        'title' => 'Penilaian MCU',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianmcu.php'
    ],
    'hemodialisa' => [
        'title' => 'Hemodialisa',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hemodialisa.php'
    ],
    'penilaian_bayi_baru_lahir' => [
        'title' => 'Penilaian Bayi Baru Lahir',
        'icon' => 'fa-user-md',
        'color' => 'warning',
        'endpoint' => 'pages/riwayat/riwayat_bayibarulahir.php'
    ],
    'konseling_farmasi' => [
        'title' => 'Konseling Farmasi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_konselingfarmasi.php'
    ],
    'rekonsiliasi_obat' => [
        'title' => 'Rekonsiliasi Obat',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_rekonsiliasiobat.php'
    ],
    'catatan_cek_gds' => [
        'title' => 'Catatan Cek GDS',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatancekgds.php'
    ],
    'monitoring_reaksi_tranfusi' => [
        'title' => 'Monitoring Reaksi Transfusi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_monitoringtransfusi.php'
    ],
    'penilaian_ulang_nyeri' => [
        'title' => 'Penilaian Ulang Nyeri',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianulangnyeri.php'
    ],
    'catatan_keperawatan_ralan' => [
        'title' => 'Catatan Keperawatan Ralan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatankeperawatanralan.php'
    ],
    'catatan_persalinan' => [
        'title' => 'Catatan Persalinan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanpersalinan.php'
    ],
    'catatan_keseimbangan_cairan' => [
        'title' => 'Catatan Keseimbangan Cairan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatankeseimbangancairan.php'
    ],
    'catatan_observasi_igd' => [
        'title' => 'Catatan Observasi IGD',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatonanobservasiigd.php'
    ],
    'catatan_observasi_chbp' => [
        'title' => 'Catatan Observasi CHBP',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanobservasichbp.php'
    ],
    'catatan_observasi_induksi_persalinan' => [
        'title' => 'Catatan Observasi Induksi Persalinan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanobservasiinduksi.php'
    ],
    'catatan_observasi_bayi' => [
        'title' => 'Catatan Observasi Bayi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanobservasibayi.php'
    ],
    'catatan_observasi_hemodialisa' => [
        'title' => 'Catatan Observasi Hemodialisa',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanobservasihemodialisa.php'
    ],
    'checklist_pemberian_fibrinolitik' => [
        'title' => 'Checklist Pemberian Fibrinolitik',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_checklistfibrinolitik.php'
    ],
    'laporan_tindakan' => [
        'title' => 'Laporan Tindakan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_laporantindakan.php'
    ],
    'skrining_nutrisi_dewasa' => [
        'title' => 'Skrining Nutrisi Dewasa',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_skriningnutrisidewasa.php'
    ],
    'skrining_nutrisi_lansia' => [
        'title' => 'Skrining Nutrisi Lansia',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_skriningnutrisilansia.php'
    ],
    'skrining_nutrisi_anak' => [
        'title' => 'Skrining Nutrisi Anak',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_skriningnutrisianak.php'
    ],
    'skrining_gizi' => [
        'title' => 'Skrining Gizi Lanjut',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_skrininggizi.php'
    ],
    'asuhan_gizi' => [
        'title' => 'Asuhan Gizi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_asuhangizi.php'
    ],
    'monitoring_asuhan_gizi' => [
        'title' => 'Monitoring Asuhan Gizi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_monitoringasuhangizi.php'
    ],
    'catatan_adime_gizi' => [
        'title' => 'Catatan ADIME Gizi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanadimegizi.php'
    ],
    'penilaian_awal_keperawatan_ranap' => [
        'title' => 'Penilaian Awal Keperawatan Ranap',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatanranap.php'
    ],
    'penilaian_awal_keperawatan_kebidanan_ranap' => [
        'title' => 'Penilaian Awal Keperawatan Kebidanan Ranap',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatankebidananranap.php'
    ],
    'penilaian_awal_keperawatan_ranap_neonatus' => [
        'title' => 'Penilaian Awal Keperawatan Ranap Neonatus',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatanranapneonatus.php'
    ],
    'penilaian_awal_keperawatan_ranap_bayi' => [
        'title' => 'Penilaian Awal Keperawatan Ranap Bayi',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaiankeperawatanranapbayi.php'
    ],
    'penilaian_medis_ranap' => [
        'title' => 'Penilaian Medis Ranap',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianmedisranap.php'
    ],
    'penilaian_medis_ranap_neonatus' => [
        'title' => 'Penilaian Medis Ranap Neonatus',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianmedisranapneonatus.php'
    ],
    'pengkajian_medis_ranap_kandungan' => [
        'title'    => 'Pengkajian Awal Medis Ranap Kandungan',
        'icon'     => 'fa-venus',
        'color'    => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_pengkajianmedisranapadungan.php'
    ],
    'penilaian_medis_ranap_psikiatrik' => [
        'title' => 'Penilaian Medis Ranap Psikiatrik',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianmedisranappsikiatrik.php'
    ],
    'penilaian_risiko_dekubitus' => [
        'title' => 'Penilaian Risiko Dekubitus',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianrisikodekubitus.php'
    ],
    'catatan_observasi_ranap' => [
        'title' => 'Catatan Observasi Ranap',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanobservasiranap.php'
    ],
    'catatan_observasi_ranap_kebidanan' => [
        'title' => 'Catatan Observasi Ranap Kebidanan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanobservasiranap_kebidanan.php'
    ],
    'catatan_observasi_ranap_postpartum' => [
        'title' => 'Catatan Observasi Ranap Postpartum',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanobservasiranappostpartum.php'
    ],
    'konsultasi_medik' => [
        'title' => 'Konsultasi Medik',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_konsultasimedik.php'
    ],
    'perencanaan_pemulangan' => [
        'title' => 'Perencanaan Pemulangan',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_perencanaanpemulangan.php'
    ],
    'catatan_observasi_restrain_nonfarma' => [
        'title' => 'Catatan Observasi Restrain Nonfarma',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanobservasirestrainnonfarma.php'
    ],
    'catatan_observasi_ventilator' => [
        'title' => 'Catatan Observasi Ventilator',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatanobservasiventilator.php'
    ],
    'catatan_keperawatan_ranap' => [
        'title' => 'Catatan Keperawatan Ranap',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_catatankeperawatanranap.php'
    ],
    'follow_up_dbd' => [
        'title' => 'Follow Up DBD',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_followupdbd.php'
    ],
    'hasil_tindakan_eswl' => [
        'title' => 'Hasil Tindakan ESWL',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_hasiltindakaneswl.php'
    ],
    'penilaian_level_kecemasan_ranap_anak' => [
        'title' => 'Penilaian Level Kecemasan Ranap Anak',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_penilaianlevelkecemasanranapanak.php'
    ],
        'transfer_pasien_antar_ruang' => [
        'title' => 'Transfer Pasien',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_transferpasien.php'
    ],
    'resume_ranap' => [
        'title' => 'Resume Pasien Ranap',
        'icon' => 'fa-user-md',
        'color' => 'primary',
        'endpoint' => 'pages/riwayat/riwayat_resumeranap.php'
    ],
    'soapie' => [
        'title' => 'SOAPIE',
        'icon' => 'fa-notes-medical',
        'color' => 'success',
        'endpoint' => 'pages/riwayat/riwayat_soapie.php'
    ],
    'resep_obat' => [
        'title' => 'Obat yang Diberikan',
        'icon' => 'fa-prescription-bottle-alt',
        'color' => 'warning',
        'endpoint' => 'pages/riwayat/riwayat_obatbhp.php'
    ],
    'lab' => [
        'title' => 'Laboratorium',
        'icon' => 'fa-flask',
        'color' => 'info',
        'endpoint' => 'pages/riwayat/riwayat_lab.php'
    ],
    'radiologi' => [
        'title' => 'Radiologi',
        'icon' => 'fa-x-ray',
        'color' => 'info',
        'endpoint' => 'pages/riwayat/riwayat_radiologi.php'
    ],
    'tindakan_perawatan' => [
    'title' => 'Tindakan & Perawatan',
    'icon' => 'fa-stethoscope',
    'color' => 'success',
    'endpoint' => 'pages/riwayat/riwayat_tindakanperawatan.php'
    ],
    'operasi' => [
        'title' => 'Operasi/VK',
        'icon' => 'fa-procedures',
        'color' => 'danger',
        'endpoint' => 'pages/riwayat/riwayat_operasi.php'
    ],
    'berkas_digital' => [
        'title' => 'Berkas Digital Perawatan',
        'icon' => 'fa-file-pdf-o', // atau 'fa-file-text-o' / 'fa-folder-open'
        'color' => 'info', // atau 'success', 'warning' sesuai preferensi
        'endpoint' => 'pages/riwayat/riwayat_berkasdigital.php'
    ],
    // CONTOH PENAMBAHAN RME BARU:
    // 'pemeriksaan_igd' => [
    //     'title' => 'Pemeriksaan IGD',
    //     'icon' => 'fa-procedures',
    //     'color' => 'warning',
    //     'endpoint' => 'pages/riwayat/riwayat_pemeriksaanigd.php'
    // ],
];
?>

<style>
/* Timeline Styles */
.timeline-container {
    position: relative;
    padding: 20px 0;
}

.timeline-line {
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(to bottom, #e2e8f0 0%, #cbd5e0 100%);
}

.timeline-item {
    position: relative;
    padding-left: 60px;
    margin-bottom: 20px;
}

.timeline-dot {
    position: absolute;
    left: 10px;
    top: 8px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 4px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    z-index: 2;
}

.dot-primary { background: #0d6efd; }
.dot-danger { background: #dc3545; }
.dot-success { background: #198754; }
.dot-warning { background: #ffc107; }
.dot-info { background: #0dcaf0; }
.dot-secondary { background: #6c757d; }

.timeline-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.timeline-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
}

.timeline-header:hover {
    opacity: 0.95;
}

.timeline-content-left {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.timeline-icon-wrapper {
    font-size: 24px;
    width: 40px;
    text-align: center;
}

.timeline-info {
    flex: 1;
}

.timeline-title-text {
    font-size: 15px;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: #1e293b;
}

.timeline-subtitle {
    font-size: 13px;
    color: #64748b;
}

.timeline-chevron {
    transition: transform 0.3s ease;
    font-size: 14px;
    color: #94a3b8;
}

.timeline-header[aria-expanded="true"] .timeline-chevron {
    transform: rotate(180deg);
}

.timeline-detail {
    border-top: 1px solid #e2e8f0;
}

.bg-primary-subtle { background: #e7f1ff; }
.bg-danger-subtle { background: #ffe5e9; }
.bg-success-subtle { background: #d1f4e0; }
.bg-warning-subtle { background: #fff3cd; }
.bg-info-subtle { background: #d1ecf1; }
.bg-secondary-subtle { background: #f8f9fa; }

/* Header & Filter Section */
.header-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}

.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 30px;
}

.header-info {
    flex: 1;
}

.header-title {
    color: #1e293b;
    margin: 0 0 10px 0;
    font-weight: 700;
    font-size: 22px;
}

.header-subtitle {
    font-size: 14px;
    color: #64748b;
}

.filter-wrapper {
    min-width: 400px;
}

.filter-label {
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
    font-size: 13px;
    display: block;
}

.form-select {
    border-radius: 8px;
    border: 1px solid #cbd5e0;
    padding: 10px 15px;
    width: 100%;
    font-size: 13px;
}

.form-select:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13,110,253,.25);
    outline: none;
}

@media (max-width: 992px) {
    .header-row {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-wrapper {
        width: 100%;
        min-width: auto;
    }
}
</style>

<?php if (empty($ajax_reload)): ?>
<!-- HEADER SECTION -->
<div class="container-fluid">
    <div class="header-card">
        <div class="header-row">
            <!-- Info Pasien -->
            <div class="header-info">
                <h4 class="header-title">
                    <i class="fa fa-clock-o text-primary"></i> 
                    Riwayat Perawatan
                </h4>
                <div class="header-subtitle">
                    <strong><?php echo htmlspecialchars($pasien['nm_pasien']); ?></strong> 
                    (<?php echo htmlspecialchars($pasien['no_rkm_medis']); ?>) • 
                    <?php echo htmlspecialchars($pasien['jk']); ?> • 
                    <?php echo htmlspecialchars($pasien['umur']); ?> tahun
                </div>
            </div>
            
            <!-- Filter -->
            <div class="filter-wrapper">
                <label class="filter-label">
                    <i class="fa fa-filter"></i> Filter No. Rawat
                </label>
                <select class="form-select" id="filterNoRawat">
                    <option value="">Semua Riwayat Perawatan</option>
                    <?php foreach ($list_norawat as $item): ?>
                        <option value="<?php echo htmlspecialchars($item['no_rawat']); ?>" 
                                <?php echo ($item['no_rawat'] == $no_rawat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($item['no_rawat']); ?> - 
                            <?php echo htmlspecialchars($item['tgl_reg']); ?> <?php echo htmlspecialchars($item['jam_reg']); ?> - 
                            <?php echo htmlspecialchars($item['nm_poli']); ?> 
                            (<?php echo htmlspecialchars($item['status_lanjut']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>



<!-- TIMELINE CONTAINER -->
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="timeline-container" id="timeline-container-riwayat">
                <div class="timeline-line"></div>
                
                <?php if (!empty($timeline_data)): ?>
                    <?php foreach($timeline_data as $item): ?>
                        <?php 
                        $c = $config[$item['type']] ?? [
                            'title' => 'Data',
                            'icon' => 'fa-file',
                            'color' => 'secondary',
                            'endpoint' => '#'
                        ];
                        
                        $safe_id = preg_replace('/[^a-zA-Z0-9\-_]/', '_', $item['id']);
                        $collapse_id = "collapse-{$item['type']}-{$safe_id}";
                        $dot_color = "dot-{$c['color']}";
                        ?>
                        
                        <!-- TIMELINE ITEM -->
                        <div class="timeline-item">
                            <div class="timeline-dot <?php echo $dot_color; ?>"></div>
                            <div class="timeline-card">
                                <a href="#<?php echo $collapse_id; ?>" 
                                   class="timeline-header bg-<?php echo $c['color']; ?>-subtle" 
                                   data-toggle="collapse" 
                                   data-target="#<?php echo $collapse_id; ?>"
                                   aria-expanded="false">
                                   
                                    <div class="timeline-content-left">
                                        <div class="timeline-icon-wrapper">
                                            <i class="fa <?php echo $c['icon']; ?> text-<?php echo $c['color']; ?>"></i>
                                        </div>
                                        <div class="timeline-info">
                                            <h6 class="timeline-title-text"><?php echo $c['title']; ?></h6>
                                            <small class="timeline-subtitle"><?php echo htmlspecialchars($item['subtitle']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <i class="fa fa-chevron-down timeline-chevron"></i>
                                </a>
                                
                                <!-- COLLAPSE DETAIL -->
                                <div class="collapse timeline-detail" id="<?php echo $collapse_id; ?>">
                                    <div class="detail-loader" 
                                         data-url="<?php echo $c['endpoint']; ?>" 
                                         data-id="<?php echo htmlspecialchars($item['id']); ?>" 
                                         data-norm="<?php echo htmlspecialchars($no_rm); ?>">
                                        <!-- Content akan di-load via AJAX -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info m-3">
                        <i class="fa fa-info-circle"></i> 
                        Tidak ada data riwayat perawatan
                    </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $(document).on('show.bs.collapse', '.timeline-detail', function() {
        const $loader = $(this).find('.detail-loader');
        
        if ($loader.data('loaded')) {
            return;
        }
        
        const url = $loader.data('url');
        const id = $loader.data('id');
        const norm = $loader.data('norm');
        
        $loader.html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 mb-0 text-muted">Memuat detail...</p>
            </div>
        `);
        
        $.ajax({
            url: url,
            method: 'GET',
            data: { id: id, no_rm: norm },
            timeout: 20000,
            success: function(response) {
                $loader.html(response);
                $loader.data('loaded', true);
            },
            error: function(xhr, status, error) {
                $loader.html(`
                    <div class="alert alert-info text-center m-3" style="background-color: #007bff; color: white; border: none;">
                        <i class="fa fa-info-circle"></i><br>
                        <strong>Fitur ini masih dalam pengembangan</strong><br>
                        Hubungi IT (Alfian) jika membutuhkannya
                        <br><a href="#" class="btn btn-sm btn-outline-light mt-2" onclick="$(this).closest('.timeline-detail').collapse('hide').collapse('show'); return false;">
                            <i class="fa fa-redo"></i> Coba Lagi
                        </a>
                    </div>
                `);
            }
        });
    });
});
</script>

<script>
function reloadData() {
    const noRawat = document.getElementById('filterNoRawat').value;
    const noRm = '<?php echo htmlspecialchars($no_rm); ?>';
    const noRawatAktif = '<?php echo htmlspecialchars($no_rawat_aktif); ?>';
    
    $('#timeline-container-riwayat').html(`
        <div class="timeline-line"></div>
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 mb-0 text-muted">Memuat ulang data...</p>
        </div>
    `);
    
    $.ajax({
        url: 'pages/riwayat_perawatan.php',
        method: 'GET',
        data: {
            no_rm: noRm,
            no_rawat: noRawat,
            no_rawat_aktif: noRawatAktif,
            ajax_reload: '1'
        },
        timeout: 15000,
        success: function(response) {
            const $temp = $('<div>').html(response);
            const newTimeline = $temp.find('#timeline-container-riwayat').html();
            
            if (newTimeline) {
                $('#timeline-container-riwayat').html(newTimeline);
                
                setTimeout(function() {
                    $('.timeline-detail').each(function() {
                        $(this).removeClass('show');
                        const $loader = $(this).find('.detail-loader');
                        $loader.removeData('loaded');
                    });
                    $('.timeline-header').attr('aria-expanded', 'false');
                }, 100);
                
            } else {
                $('#timeline-container-riwayat').html(`
                    <div class="alert alert-danger m-3">
                        <i class="fa fa-exclamation-triangle"></i> Gagal memuat data timeline
                    </div>
                `);
            }
        },
        error: function(xhr, status, error) {
            $('#timeline-container-riwayat').html(`
                <div class="alert alert-danger m-3">
                    <i class="fa fa-exclamation-triangle"></i> Error: ${error}
                    <br><small>HTTP Status: ${xhr.status}</small>
                    <br><button class="btn btn-sm btn-outline-danger mt-2" onclick="reloadData()">
                        <i class="fa fa-redo"></i> Coba Lagi
                    </button>
                </div>
            `);
        }
    });
}

$(document).ready(function() {
    $('#filterNoRawat').on('change', function() {
        reloadData();
    });
});
</script>