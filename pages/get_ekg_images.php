<?php
/**
 * get_ekg_images.php
 * API untuk mendapatkan gambar EKG dari database atau Orthanc PACS
 * Pattern sama dengan get_usg_neonatus_images.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../conf/conf.php');
require_once(__DIR__ . '/api_orthanc.php');
header('Content-Type: application/json');

if (!isset($_SESSION["ses_dokter"])) { echo json_encode(['status'=>'error','message'=>'Session expired']); exit(); }

$no_rawat = isset($_GET['no_rawat']) ? trim($_GET['no_rawat']) : '';
if (empty($no_rawat)) { echo json_encode(['status'=>'error','message'=>'No. Rawat tidak valid']); exit(); }

$no_rawat_safe = addslashes($no_rawat);

try {
    // STEP 1: DATABASE
    $result_images = bukaquery("SELECT photo FROM hasil_pemeriksaan_ekg_gambar WHERE no_rawat='$no_rawat_safe' ORDER BY photo ASC");
    $db_images = [];
    if ($result_images && mysqli_num_rows($result_images) > 0) {
        while ($row = mysqli_fetch_assoc($result_images)) {
            $db_images[] = ['source'=>'database','data'=> EKG_BASE_URL . $row['photo'],'type'=>'image','photo_path'=>$row['photo']];
        }
    }
    if (!empty($db_images)) {
        echo json_encode(['status'=>'success','source'=>'database','count'=>count($db_images),'images'=>$db_images,'viewer_url'=>null]);
        exit();
    }

    // STEP 2: ORTHANC
    $result_patient = bukaquery("SELECT h.tanggal, r.no_rkm_medis FROM hasil_pemeriksaan_ekg h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$no_rawat_safe' LIMIT 1");
    if (!$result_patient || mysqli_num_rows($result_patient) === 0) {
        echo json_encode(['status'=>'error','message'=>'Data pemeriksaan tidak ditemukan. Simpan data form terlebih dahulu sebelum mengambil gambar dari Orthanc.']);
        exit();
    }

    $patient = mysqli_fetch_assoc($result_patient);
    $no_rkm_medis = $patient['no_rkm_medis'];
    $study_date = date('Ymd', strtotime($patient['tanggal']));

    $orthanc = ApiOrthanc::fromConfig();
    $orthanc_series = $orthanc->getAllSeries($no_rkm_medis, $study_date);

    if (empty($orthanc_series)) {
        echo json_encode(['status'=>'error','message'=>'Tidak ada gambar di database maupun di Orthanc untuk tanggal ' . date('d-m-Y', strtotime($patient['tanggal']))]);
        exit();
    }

    $thumbnails = $orthanc->getThumbnails($no_rkm_medis, $study_date, 20);
    $orthanc_images = [];
    foreach ($thumbnails as $index => $thumb) {
        $orthanc_images[] = ['source'=>'orthanc','instance_id'=>$thumb['instance_id'],'series_id'=>$thumb['series_id'],'data'=>'data:image/png;base64,'.$thumb['base64'],'type'=>'image','viewer_url'=>$thumb['viewer_url']];
    }

    echo json_encode(['status'=>'success','source'=>'orthanc','count'=>count($orthanc_images),'images'=>$orthanc_images,'series_info'=>$orthanc_series,'patient_info'=>['no_rkm_medis'=>$no_rkm_medis,'study_date'=>$study_date]]);

} catch (Exception $e) {
    error_log("[EKG-IMG] ERROR: " . $e->getMessage());
    echo json_encode(['status'=>'error','message'=>'Terjadi kesalahan: '.$e->getMessage()]);
}
exit();
