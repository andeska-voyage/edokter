<?php
/**
 * stone_gate.php
 * Popup kecil untuk pre-authenticate browser ke Orthanc.
 *
 * Alur:
 *   1. Dibuka sebagai popup kecil oleh riwayat_rad.js
 *   2. Lakukan XHR ke Orthanc /system dengan credentials → browser simpan auth cache
 *   3. Kirim postMessage ke parent window: { type: 'dicom_auth_done', key: '...' }
 *   4. Parent buka modal iframe dengan URL Orthanc langsung (tanpa popup login)
 *   5. Popup tutup sendiri
 */

session_start();
require_once('../conf/conf.php');

if (!isset($_SESSION["ses_dokter"])) {
    http_response_code(403);
    exit('Session expired.');
}

$study_uid = isset($_GET['study']) ? trim($_GET['study']) : '';
if (empty($study_uid)) { http_response_code(400); exit('Parameter study tidak valid.'); }

$is_popup     = isset($_GET['popup']) && $_GET['popup'] === '1';
$key          = isset($_GET['key']) ? trim($_GET['key']) : '';
$orthanc_base = rtrim(ORTHANC_URL, '/') . ':' . ORTHANC_PORT;
$orthanc_user = ORTHANC_USER;
$orthanc_pass = ORTHANC_PASS;
$viewer_url   = $orthanc_base . '/stone-webviewer/index.html?study=' . urlencode($study_uid);
$scheme       = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$parent_origin = $scheme . '://' . $_SERVER['HTTP_HOST'];
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Menghubungkan...</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{min-height:100vh;background:#0d1117;display:flex;align-items:center;justify-content:center;
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#e6edf3}
  .box{text-align:center;padding:32px 28px;background:#161b22;border:1px solid #30363d;
    border-radius:14px;width:340px}
  .icon{font-size:44px;margin-bottom:14px}
  h3{font-size:16px;font-weight:600;color:#58a6ff;margin-bottom:8px}
  p{font-size:13px;color:#8b949e}
  .spinner{width:32px;height:32px;border:3px solid #30363d;border-top-color:#58a6ff;
    border-radius:50%;animation:spin .7s linear infinite;margin:18px auto 0}
  @keyframes spin{to{transform:rotate(360deg)}}
  #status{margin-top:12px;font-size:12px;color:#6e7681;min-height:18px}
</style>
</head>
<body>
<div class="box">
  <div class="icon">🩻</div>
  <h3>Menghubungkan ke PACS...</h3>
  <p>Sedang autentikasi ke server Orthanc</p>
  <div class="spinner"></div>
  <div id="status">Menghubungi server...</div>
</div>
<script>
(function(){
  var ORTHANC_BASE   = <?= json_encode($orthanc_base) ?>;
  var ORTHANC_USER   = <?= json_encode($orthanc_user) ?>;
  var ORTHANC_PASS   = <?= json_encode($orthanc_pass) ?>;
  var VIEWER_URL     = <?= json_encode($viewer_url) ?>;
  var IS_POPUP       = <?= json_encode($is_popup) ?>;
  var MSG_KEY        = <?= json_encode($key) ?>;
  var PARENT_ORIGIN  = <?= json_encode($parent_origin) ?>;
  var el = document.getElementById('status');

  function done(){
    el.textContent = 'Berhasil! Membuka viewer...';
    if(IS_POPUP && window.opener && !window.opener.closed){
      window.opener.postMessage({type:'dicom_auth_done',key:MSG_KEY}, PARENT_ORIGIN);
      setTimeout(function(){ window.close(); }, 400);
    } else {
      setTimeout(function(){ window.location.href = VIEWER_URL; }, 300);
    }
  }

  var xhr = new XMLHttpRequest();
  xhr.open('GET', ORTHANC_BASE+'/system', true, ORTHANC_USER, ORTHANC_PASS);
  xhr.withCredentials = true;
  xhr.timeout = 10000;
  xhr.onload  = function(){ done(); };
  xhr.onerror = function(){ done(); };
  xhr.ontimeout = function(){ done(); };
  try{ xhr.send(); }catch(e){ done(); }
})();
</script>
</body>
</html>