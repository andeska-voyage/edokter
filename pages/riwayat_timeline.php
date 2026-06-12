<?php
include "../conf/conf.php";
header("Content-Type: application/json; charset=UTF-8");

// Get parameter
$no_rm = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rm)) {
    echo json_encode([
        'success' => false,
        'message' => 'No. RM tidak ditemukan'
    ]);
    exit;
}

try {
    // Get patient info
    $query_pasien = "
        SELECT 
            no_rkm_medis as no_rm,
            nm_pasien as nama,
            TIMESTAMPDIFF(YEAR, tgl_lahir, CURDATE()) as umur
        FROM pasien
        WHERE no_rkm_medis = '$no_rm'
    ";
    
    $result_pasien = bukaquery($query_pasien);
    $patient = mysqli_fetch_assoc($result_pasien);
    
    if (!$patient) {
        echo json_encode([
            'success' => false,
            'message' => 'Data pasien tidak ditemukan'
        ]);
        exit;
    }
    
    $timeline = [];
    
    // ====================================
    // KATEGORI IGD - Sesuai dengan gambar
    // ====================================
    
    // 1. DATA TRIASE GAWAT DARURAT
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'triase_igd' as type,
    //         tanggal,
    //         CONCAT(triase_gd, ' - ', macam_kasus) as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM data_triase_igd
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 2. PENGKAJIAN AWAL KEPERAWATAN IGD
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'awal_keperawatan_igd' as type,
    //         tanggal,
    //         'Pengkajian Keperawatan' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM penilaian_awal_keperawatan_igd
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 3. PENGKAJIAN AWAL MEDIS IGD
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'awal_medis_igd' as type,
    //         tanggal,
    //         'Pengkajian Medis' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM penilaian_awal_keperawatan_igdrz
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 4. PENGKAJIAN AWAL MEDIS IGD PSIKIATRI
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'awal_medis_igd_psikiatri' as type,
    //         tanggal,
    //         'Pengkajian Psikiatri' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM penilaian_awal_keperawatan_igd_psikiatri
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 5. PENGKAJIAN PASIEN KERACUNAN
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'pasien_keracunan' as type,
    //         tanggal,
    //         'Keracunan' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM pengkajian_pasien_keracunan
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 6. PENGKAJIAN RESTRAIN
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'restrain' as type,
    //         tanggal,
    //         'Restrain' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM pengkajian_restrain
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 7. PEMANTAUAN PEWS ANAK
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'pews_anak' as type,
    //         tanggal,
    //         'PEWS Anak' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM pemantauan_pews_anak
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 8. PEMANTAUAN EWS DEWASA
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'ews_dewasa' as type,
    //         tanggal,
    //         'EWS Dewasa' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM pemantauan_ews_dewasa
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 9. PEMANTAUAN MEOWS OBSTETRI
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'meows_obstetri' as type,
    //         tanggal,
    //         'MEOWS Obstetri' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM pemantauan_meows_obstetri
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 10. PEMANTAUAN EWS NEONATUS
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'ews_neonatus' as type,
    //         tanggal,
    //         'EWS Neonatus' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM pemantauan_ews_neonatus
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 11. PENGKAJIAN AWAL MEDIS HEMODIALISA
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'awal_medis_hemodialisa' as type,
    //         tanggal,
    //         'Hemodialisa' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM penilaian_awal_keperawatan_hemodialisa
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 12. HASIL PEMERIKSAAN EKG
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'hasil_ekg' as type,
    //         tanggal,
    //         'EKG' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM hasil_pemeriksaan_ekg
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 13. HASIL PEMERIKSAAN ECHO
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'hasil_echo' as type,
    //         tanggal,
    //         'ECHO' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM hasil_pemeriksaan_echo
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 14. HASIL PEMERIKSAAN SLIT LAMP
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'hasil_slit_lamp' as type,
    //         tanggal,
    //         'Slit Lamp' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM hasil_pemeriksaan_slit_lamp
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // // 15. HASIL PEMERIKSAAN OCT
    // $query = "
    //     SELECT 
    //         no_rawat as id,
    //         'hasil_oct' as type,
    //         tanggal,
    //         'OCT' as badge,
    //         DATE_FORMAT(tanggal, '%d-%m-%Y | %H:%i WIB') as subtitle
    //     FROM hasil_pemeriksaan_oct
    //     WHERE no_rkm_medis = '$no_rm'
    //     ORDER BY tanggal DESC
    // ";
    // $result = bukaquery($query);
    // while ($row = mysqli_fetch_assoc($result)) {
    //     $timeline[] = $row;
    // }
    
    // Sort by tanggal DESC (terbaru di atas)
    usort($timeline, function($a, $b) {
        return strtotime($b['tanggal']) - strtotime($a['tanggal']);
    });
    
    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'timeline' => $timeline
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error database: ' . $e->getMessage()
    ]);
}
?>