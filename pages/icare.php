<?php
/**
 * iCare BPJS - Riwayat Perawatan FKTL
 * Replikasi dari Java ICareRiwayatPerawatan.java
 * 
 * Parameter (encrypted): rm = no_rkm_medis
 * Flow: no_rkm_medis → pasien.no_ktp (param)
 *       kd_dokter session → maping_dokter_dpjpvclaim.kd_dokter_bpjs (kodedokter)
 *       POST ke API iCare → dapat URL → tampilkan di iframe
 */

// Ambil parameter encrypted
$encrypted_norm = isset($_GET['rm']) ? urldecode($_GET['rm']) : '';
$no_rkm_medis = '';

if (!empty($encrypted_norm)) {
    $no_rkm_medis = encrypt_decrypt($encrypted_norm, 'd');
}

// Ambil kd_dokter dari session
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if (!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Validasi parameter
if (empty($no_rkm_medis) || empty($kd_dokter_login)) {
    echo '<div class="alert alert-danger" style="text-align:center; padding:40px; margin:20px;">
            <i class="material-icons" style="font-size:48px;">error</i>
            <h4>Parameter tidak valid</h4>
            <p>No. RM atau data dokter tidak ditemukan.</p>
          </div>';
    return;
}

// 1. Ambil No. KTP dari tabel pasien
$query_ktp = bukaquery("SELECT p.no_ktp, p.nm_pasien 
    FROM pasien p 
    WHERE p.no_rkm_medis = '$no_rkm_medis'");
$data_pasien = mysqli_fetch_assoc($query_ktp);

if (!$data_pasien || empty($data_pasien['no_ktp'])) {
    echo '<div class="alert alert-warning" style="text-align:center; padding:40px; margin:20px;">
            <i class="material-icons" style="font-size:48px;">warning</i>
            <h4>Data NIK/No. KTP tidak ditemukan</h4>
            <p>Pasien dengan RM: <strong>' . htmlspecialchars($no_rkm_medis) . '</strong> belum memiliki data NIK.</p>
          </div>';
    return;
}

$no_ktp = $data_pasien['no_ktp'];
$nm_pasien = $data_pasien['nm_pasien'];

// 2. Ambil kd_dokter_bpjs dari maping_dokter_dpjpvclaim
$query_dpjp = bukaquery("SELECT kd_dokter_bpjs 
    FROM maping_dokter_dpjpvclaim 
    WHERE kd_dokter = '$kd_dokter_login' 
    LIMIT 1");
$data_dpjp = mysqli_fetch_assoc($query_dpjp);

if (!$data_dpjp || empty($data_dpjp['kd_dokter_bpjs'])) {
    echo '<div class="alert alert-warning" style="text-align:center; padding:40px; margin:20px;">
            <i class="material-icons" style="font-size:48px;">warning</i>
            <h4>Kode Dokter BPJS tidak ditemukan</h4>
            <p>Dokter belum di-mapping ke DPJP BPJS. Silakan hubungi admin.</p>
          </div>';
    return;
}

$kd_dokter_bpjs = $data_dpjp['kd_dokter_bpjs'];
?>

<div class="row clearfix">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header" style="background: linear-gradient(135deg, #00897b 0%, #004d40 100%); color: white; padding: 15px 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="color: white; margin: 0; font-size: 16px;">
                            <img src="images/icare.png" alt="iCare" style="height: 24px; vertical-align: middle; margin-right: 8px;">
                            iCare BPJS - Riwayat Perawatan FKTL
                        </h2>
                        <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.85;">
                            Pasien: <strong><?php echo htmlspecialchars($nm_pasien); ?></strong> 
                            | RM: <?php echo htmlspecialchars($no_rkm_medis); ?> 
                            | NIK: <?php echo substr($no_ktp, 0, 6) . '****' . substr($no_ktp, -4); ?>
                        </p>
                    </div>
                    <div id="icare-status">
                        <span id="icare-loading" style="display:none; color: #fff; font-size: 12px;">
                            <i class="material-icons" style="font-size:16px; vertical-align:middle; animation: spin 1s linear infinite;">refresh</i>
                            Menghubungi server BPJS...
                        </span>
                    </div>
                </div>
            </div>
            <div class="body" style="padding: 0; min-height: calc(100vh - 140px); position: relative;">
                <!-- Loading overlay -->
                <div id="icare-overlay" style="position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.95); display:flex; align-items:center; justify-content:center; z-index:10; flex-direction:column;">
                    <div style="text-align:center;">
                        <div class="preloader" style="margin:0 auto 15px;">
                            <div class="spinner-layer pl-teal">
                                <div class="circle-clipper left"><div class="circle"></div></div>
                                <div class="circle-clipper right"><div class="circle"></div></div>
                            </div>
                        </div>
                        <p style="color:#666; font-size:14px;">Memuat data iCare BPJS...</p>
                    </div>
                </div>
                
                <!-- Error container -->
                <div id="icare-error" style="display:none; text-align:center; padding:60px 20px;">
                    <i class="material-icons" style="font-size:64px; color:#f44336;">cloud_off</i>
                    <h4 style="margin-top:15px; color:#333;" id="icare-error-title">Gagal Terhubung</h4>
                    <p style="color:#999;" id="icare-error-msg">Tidak dapat menghubungi server iCare BPJS</p>
                    <button class="btn btn-primary waves-effect" onclick="icareLoad()" style="margin-top:15px; border-radius:20px;">
                        <i class="material-icons" style="font-size:16px; vertical-align:middle;">refresh</i> Coba Lagi
                    </button>
                </div>
                
                <!-- iFrame untuk menampilkan halaman iCare -->
                <iframe id="icare-frame" 
                        style="width:100%; height:calc(100vh - 140px); border:none; display:none;">
                </iframe>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
(function() {
    'use strict';
    
    var ICARE_PARAM = <?php echo json_encode($no_ktp); ?>;
    var ICARE_KODEDOKTER = <?php echo json_encode($kd_dokter_bpjs); ?>;
    
    function icareLoad() {
        var overlay = document.getElementById('icare-overlay');
        var errorDiv = document.getElementById('icare-error');
        var frame = document.getElementById('icare-frame');
        var loading = document.getElementById('icare-loading');
        
        // Show loading, hide error & frame
        overlay.style.display = 'flex';
        errorDiv.style.display = 'none';
        frame.style.display = 'none';
        loading.style.display = 'inline';
        
        // Native XMLHttpRequest — tidak bergantung jQuery
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'pages/icare_api.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.timeout = 30000;
        
        xhr.onload = function() {
            loading.style.display = 'none';
            
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    
                    if (res.status === 'success' && res.url) {
                        // Sukses — tampilkan iframe dengan URL dari BPJS
                        frame.src = res.url;
                        frame.style.display = 'block';
                        overlay.style.display = 'none';
                    } else {
                        // Error dari API
                        overlay.style.display = 'none';
                        errorDiv.style.display = 'block';
                        document.getElementById('icare-error-title').textContent = 'Gagal Memuat Data';
                        document.getElementById('icare-error-msg').textContent = res.message || 'Terjadi kesalahan pada server iCare BPJS';
                    }
                } catch(e) {
                    overlay.style.display = 'none';
                    errorDiv.style.display = 'block';
                    document.getElementById('icare-error-msg').textContent = 'Response tidak valid dari server: ' + e.message;
                    console.error('iCare parse error:', xhr.responseText);
                }
            } else {
                overlay.style.display = 'none';
                errorDiv.style.display = 'block';
                document.getElementById('icare-error-msg').textContent = 'HTTP Error: ' + xhr.status;
            }
        };
        
        xhr.onerror = function() {
            loading.style.display = 'none';
            overlay.style.display = 'none';
            errorDiv.style.display = 'block';
            document.getElementById('icare-error-msg').textContent = 'Koneksi ke server gagal';
        };
        
        xhr.ontimeout = function() {
            loading.style.display = 'none';
            overlay.style.display = 'none';
            errorDiv.style.display = 'block';
            document.getElementById('icare-error-msg').textContent = 'Koneksi ke server BPJS timeout. Silakan coba lagi.';
        };
        
        xhr.send('param=' + encodeURIComponent(ICARE_PARAM) + '&kodedokter=' + encodeURIComponent(ICARE_KODEDOKTER));
    }
    
    // Expose untuk tombol retry
    window.icareLoad = icareLoad;
    
    // Auto-load saat halaman dibuka
    icareLoad();
    
})();
</script>
