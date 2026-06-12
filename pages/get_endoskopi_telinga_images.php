<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once(__DIR__.'/../conf/conf.php');
require_once(__DIR__.'/api_orthanc.php');
header('Content-Type: application/json');
if (!isset($_SESSION["ses_dokter"])){echo json_encode(['status'=>'error','message'=>'Session expired']);exit();}
$no_rawat=isset($_GET['no_rawat'])?trim($_GET['no_rawat']):'';
if (empty($no_rawat)){echo json_encode(['status'=>'error','message'=>'No. Rawat tidak valid']);exit();}
$nrs=addslashes($no_rawat);
try {
    $ri=bukaquery("SELECT photo FROM hasil_endoskopi_telinga_gambar WHERE no_rawat='$nrs' ORDER BY photo ASC");
    $db=[];
    if($ri&&mysqli_num_rows($ri)>0){while($r=mysqli_fetch_assoc($ri)){$db[]=['source'=>'database','data'=>ENDOSKOPI_TELINGA_BASE_URL.$r['photo'],'type'=>'image','photo_path'=>$r['photo']];}}
    if(!empty($db)){echo json_encode(['status'=>'success','source'=>'database','count'=>count($db),'images'=>$db]);exit();}

    $rp=bukaquery("SELECT h.tanggal,r.no_rkm_medis FROM hasil_endoskopi_telinga h INNER JOIN reg_periksa r ON h.no_rawat=r.no_rawat WHERE h.no_rawat='$nrs' LIMIT 1");
    if(!$rp||mysqli_num_rows($rp)===0){echo json_encode(['status'=>'error','message'=>'Data pemeriksaan tidak ditemukan. Simpan data form terlebih dahulu.']);exit();}
    $pt=mysqli_fetch_assoc($rp);$norm=$pt['no_rkm_medis'];$sd=date('Ymd',strtotime($pt['tanggal']));

    $orthanc=ApiOrthanc::fromConfig();$series=$orthanc->getAllSeries($norm,$sd);
    if(empty($series)){echo json_encode(['status'=>'error','message'=>'Tidak ada gambar di database maupun Orthanc']);exit();}
    $thumbs=$orthanc->getThumbnails($norm,$sd,20);$imgs=[];
    foreach($thumbs as $t){$imgs[]=['source'=>'orthanc','instance_id'=>$t['instance_id'],'series_id'=>$t['series_id'],'data'=>'data:image/png;base64,'.$t['base64'],'type'=>'image','viewer_url'=>$t['viewer_url']];}
    echo json_encode(['status'=>'success','source'=>'orthanc','count'=>count($imgs),'images'=>$imgs,'series_info'=>$series]);
} catch(Exception $e){echo json_encode(['status'=>'error','message'=>$e->getMessage()]);}
exit();