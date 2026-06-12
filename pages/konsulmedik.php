<?php
/**
 * Konsul Medik - Permintaan Konsultasi Dokter
 * E-Dokter - SIMRS Khanza
 */

define('BASE_URL', APP_BASE_URL);

// Decrypt parameter dari URL
$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';

$no_rawat = '';
$no_rkm_medis = '';

if(!empty($encrypted_norawat)) {
    $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
}

if(!empty($encrypted_norm)) {
    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');
}

// Ambil kode dokter login (dokter yang konsul)
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
$nm_dokter_login = '';

if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
    $queryDokterLogin = bukaquery("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter_login'");
    $rsDokterLogin = mysqli_fetch_array($queryDokterLogin);
    if($rsDokterLogin) {
        $nm_dokter_login = $rsDokterLogin['nm_dokter'];
    }
}

// Ambil data pasien
$queryPasien = bukaquery("SELECT 
                            rp.no_rawat, rp.no_rkm_medis, rp.tgl_registrasi,
                            p.nm_pasien, p.jk, p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        WHERE rp.no_rawat = '$no_rawat' LIMIT 1");

$rsPasien = mysqli_fetch_array($queryPasien);

if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>";
    exit;
}

// Generate No Permintaan
$no_permintaan = 'KM' . date('YmdHis') . rand(10, 99);
?>

<!-- CSS template4.css sama seperti awalmedisranap -->
<link rel="stylesheet" href="<?= BASE_URL ?>/css/template4.css?v=<?= time() ?>">

<style>
/* Autocomplete untuk dokter */
.dokter-autocomplete-wrapper {
    position: relative;
    width: 100%;
}
.dokter-autocomplete-wrapper input {
    width: 100%;
}
.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    max-height: 220px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    margin-top: 4px;
}
.autocomplete-dropdown.show { display: block; }
.autocomplete-item {
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s;
}
.autocomplete-item:last-child { border-bottom: none; }
.autocomplete-item:hover { background: #eff6ff; }
.autocomplete-item .kode { font-size: 11px; color: #64748b; margin-bottom: 2px; }
.autocomplete-item .nama { font-size: 13px; font-weight: 600; color: #1e293b; }
.autocomplete-empty { padding: 20px; text-align: center; color: #94a3b8; font-size: 13px; }

/* Riwayat Table */
.riwayat-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.riwayat-table th { 
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 10px 12px; 
    text-align: left; 
    font-weight: 600; 
    color: #475569; 
    border-bottom: 2px solid #e2e8f0;
}
.riwayat-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; }
.riwayat-table tbody tr:hover { background: #f8fafc; }
.riwayat-table .text-center { text-align: center; }
.riwayat-table .text-muted { color: #94a3b8; }

.badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-blue { background: #dbeafe; color: #1d4ed8; }
.badge-yellow { background: #fef3c7; color: #b45309; }

.btn-table { 
    padding: 5px 10px; 
    font-size: 11px; 
    border-radius: 6px; 
    border: none; 
    cursor: pointer; 
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.btn-table.btn-view { background: #dbeafe; color: #1d4ed8; }
.btn-table.btn-view:hover { background: #bfdbfe; }
</style>

<div class="modern-form-container">
    <!-- Patient Header - Sama seperti awalmedisranap -->
    <div class="patient-header">
        <h1>
            <i class="material-icons">supervised_user_circle</i>
            KONSULTASI MEDIS
            <span class="mode-badge mode-add">➕ NEW</span>
        </h1>
        <div class="patient-info">
            <div class="info-item">
                <i class="material-icons">folder</i>
                <strong>No. Rawat:</strong> <?= $rsPasien['no_rawat'] ?>
            </div>
            <div class="info-item">
                <i class="material-icons">badge</i>
                <strong>No. RM:</strong> <?= $rsPasien['no_rkm_medis'] ?>
            </div>
            <div class="info-item">
                <i class="material-icons">person</i>
                <strong>Nama:</strong> <?= strtoupper($rsPasien['nm_pasien']) ?>
            </div>
            <div class="info-item">
                <i class="material-icons">cake</i>
                <strong>Umur:</strong> <?= $rsPasien['umur'] ?>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formKonsulMedik">
                <input type="hidden" name="no_rawat" value="<?= $no_rawat ?>">
                <input type="hidden" name="kd_dokter" id="kd_dokter" value="<?= $kd_dokter_login ?>">
                <input type="hidden" name="kd_dokter_tujuan" id="kd_dokter_tujuan" value="">
                
                <!-- Section: Data Permintaan -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">assignment</i>
                        <h2>DATA PERMINTAAN</h2>
                    </div>
                    
                    <div class="form-grid cols-3">
                        <div class="form-group">
                            <label>Tanggal & Waktu</label>
                            <input type="datetime-local" name="tanggal" id="tanggal" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="form-group">
                            <label>Jenis Permintaan</label>
                            <select name="permintaan" id="permintaan">
                                <option value="Konsultasi">Konsultasi</option>
                                <option value="Alih Rawat">Alih Rawat</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>No. Permintaan</label>
                            <input type="text" name="no_permintaan" value="<?= $no_permintaan ?>" readonly>
                        </div>
                    </div>

                    <div class="form-grid cols-1" style="margin-top: 12px;">
                        <div class="form-group">
                            <label class="required">Dokter Yang Dikonsuli</label>
                            <div class="dokter-autocomplete-wrapper">
                                <input type="text" id="dokter_tujuan_input" 
                                       placeholder="Ketik kode atau nama dokter untuk mencari..." 
                                       autocomplete="off">
                                <div id="dokter-autocomplete" class="autocomplete-dropdown"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Diagnosa & Uraian -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_services</i>
                        <h2>DIAGNOSA & URAIAN KONSULTASI</h2>
                    </div>
                    
                    <div class="form-group">
                        <label>Diagnosa Kerja</label>
                        <input type="text" name="diagnosa" id="diagnosa" 
                               placeholder="Masukkan diagnosa kerja pasien...">
                    </div>

                    <div class="form-group" style="margin-top: 12px;">
                        <label class="required">Uraian Konsultasi</label>
                        <textarea name="uraian" id="uraian" rows="5" 
                                  placeholder="Jelaskan alasan dan uraian konsultasi secara detail..."></textarea>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="window.history.back();">
                <i class="material-icons">arrow_back</i>
                KEMBALI
            </button>
            <button type="button" class="btn btn-danger" id="btnHapus" style="display:none;">
                <i class="material-icons">delete</i>
                HAPUS
            </button>
            <button type="button" class="btn btn-primary" id="btnSimpan">
                <i class="material-icons">save</i>
                SIMPAN
            </button>
        </div>
    </div>

    <!-- Riwayat Konsultasi -->
    <div class="form-card" style="margin-top: 20px;">
        <div class="form-content">
            <div class="section">
                <div class="section-header">
                    <i class="material-icons">history</i>
                    <h2>RIWAYAT KONSULTASI</h2>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="riwayat-table">
                        <thead>
                            <tr>
                                <th width="40" class="text-center">No</th>
                                <th width="130">Tanggal</th>
                                <th width="150">No. Permintaan</th>
                                <th width="90">Jenis</th>
                                <th>Dokter Konsul</th>
                                <th>Dokter Tujuan</th>
                                <th>Diagnosa</th>
                                <th width="60" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyRiwayat">
                            <tr>
                                <td colspan="8" class="text-center text-muted" style="padding: 30px;">
                                    <i class="material-icons" style="font-size: 20px; vertical-align: middle;">hourglass_empty</i>
                                    Memuat data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const KONSUL_CONFIG = {
    noRawat: '<?= $no_rawat ?>',
    kdDokterLogin: '<?= $kd_dokter_login ?>',
    nmDokterLogin: '<?= $nm_dokter_login ?>',
    baseUrl: '<?= BASE_URL ?>'
};

// ========================================
// INIT - Support both DOMContentLoaded and AJAX tab load
// ========================================
function initKonsulMedik() {
    console.log('✅ initKonsulMedik() called');
    
    initAutocomplete();
    initEventListeners();
    loadRiwayatKonsul();
    
    // Auto-resize textarea
    document.querySelectorAll('textarea').forEach(ta => {
        ta.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
    
    console.log('✅ KonsulMedik initialized');
}

// Support both standalone page & AJAX tab load
if(document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKonsulMedik);
} else {
    // DOM already ready (AJAX inject) - init directly with small delay
    setTimeout(initKonsulMedik, 100);
}

function initEventListeners() {
    // Button Simpan
    document.getElementById('btnSimpan').addEventListener('click', simpanKonsultasi);
    
    // Button Hapus
    document.getElementById('btnHapus').addEventListener('click', hapusKonsultasi);
}

function getApiUrl() {
    return (typeof APP_BASE_URL !== 'undefined' ? APP_BASE_URL : KONSUL_CONFIG.baseUrl) + '/pages/proses2.php';
}

function getApiUrl3() {
    return (typeof APP_BASE_URL !== 'undefined' ? APP_BASE_URL : KONSUL_CONFIG.baseUrl) + '/pages/proses3.php';
}

// ========================================
// AUTOCOMPLETE - Langsung muncul saat ketik
// ========================================
let autocompleteTimeout = null;

function initAutocomplete() {
    const input = document.getElementById('dokter_tujuan_input');
    const dropdown = document.getElementById('dokter-autocomplete');
    
    console.log('initAutocomplete - input:', input);
    console.log('initAutocomplete - dropdown:', dropdown);
    
    if(!input || !dropdown) {
        console.error('Autocomplete elements not found!');
        return;
    }
    
    // Langsung search saat ketik
    input.addEventListener('input', function() {
        const query = this.value.trim();
        console.log('Input event - query:', query);
        
        if(autocompleteTimeout) clearTimeout(autocompleteTimeout);
        
        if(query.length < 1) {
            dropdown.classList.remove('show');
            return;
        }
        
        // Debounce 200ms
        autocompleteTimeout = setTimeout(() => searchDokter(query), 200);
    });
    
    // Hide dropdown on click outside
    document.addEventListener('click', function(e) {
        if(!dropdown.contains(e.target) && e.target !== input) {
            dropdown.classList.remove('show');
        }
    });
    
    console.log('✅ Autocomplete initialized');
}

function searchDokter(query) {
    const dropdown = document.getElementById('dokter-autocomplete');
    
    console.log('searchDokter called - query:', query);
    console.log('getApiUrl():', getApiUrl());
    
    dropdown.innerHTML = '<div class="autocomplete-empty"><i class="material-icons" style="font-size:18px;vertical-align:middle;animation:spin 1s linear infinite">refresh</i> Mencari...</div>';
    dropdown.classList.add('show');
    
    const formData = new FormData();
    formData.append('aksi', 'search_dokter');
    formData.append('query', query);
    
    fetch(getApiUrl(), { method: 'POST', body: formData })
        .then(r => {
            console.log('Search response status:', r.status);
            return r.json();
        })
        .then(data => {
            console.log('Search response data:', data);
            if(data.status === 'success' && data.data && data.data.length > 0) {
                let html = '';
                data.data.forEach(d => {
                    html += `<div class="autocomplete-item" onclick="selectDokter('${d.kd_dokter}', '${escapeHtml(d.nm_dokter)}')">
                        <div class="kode">${d.kd_dokter}</div>
                        <div class="nama">${d.nm_dokter}</div>
                    </div>`;
                });
                dropdown.innerHTML = html;
            } else {
                dropdown.innerHTML = '<div class="autocomplete-empty"><i class="material-icons" style="font-size:18px;vertical-align:middle">search_off</i> Dokter tidak ditemukan</div>';
            }
        })
        .catch(err => {
            console.error('Search error:', err);
            dropdown.innerHTML = '<div class="autocomplete-empty"><i class="material-icons" style="font-size:18px;vertical-align:middle">error</i> Error mengambil data</div>';
        });
}

function selectDokter(kode, nama) {
    document.getElementById('kd_dokter_tujuan').value = kode;
    document.getElementById('dokter_tujuan_input').value = kode + ' - ' + nama;
    document.getElementById('dokter-autocomplete').classList.remove('show');
    document.getElementById('diagnosa').focus();
}

// ========================================
// LOAD RIWAYAT
// ========================================
function loadRiwayatKonsul() {
    const tbody = document.getElementById('tbodyRiwayat');
    
    console.log('loadRiwayatKonsul called');
    console.log('tbody element:', tbody);
    console.log('KONSUL_CONFIG:', KONSUL_CONFIG);
    console.log('getApiUrl():', getApiUrl());
    
    const formData = new FormData();
    formData.append('aksi', 'get_riwayat_konsul');
    formData.append('no_rawat', KONSUL_CONFIG.noRawat);
    
    fetch(getApiUrl(), { method: 'POST', body: formData })
        .then(r => {
            console.log('Riwayat response status:', r.status);
            return r.json();
        })
        .then(data => {
            console.log('Riwayat response data:', data);
            if(data.status === 'success' && data.data && data.data.length > 0) {
                let html = '';
                data.data.forEach((item, i) => {
                    const badgeClass = item.permintaan === 'Alih Rawat' ? 'badge-yellow' : 'badge-blue';
                    html += `<tr>
                        <td class="text-center">${i+1}</td>
                        <td>${item.tanggal || '-'}</td>
                        <td><strong>${item.no_permintaan || '-'}</strong></td>
                        <td><span class="badge ${badgeClass}">${item.permintaan || 'Konsultasi'}</span></td>
                        <td>${item.nm_dokter_konsul || '-'}</td>
                        <td>${item.nm_dokter_tujuan || '-'}</td>
                        <td>${truncate(item.diagnosa || '-', 30)}</td>
                        <td class="text-center">
                            <button type="button" class="btn-table btn-view" onclick="viewKonsul('${item.no_permintaan}')">
                                <i class="material-icons" style="font-size:14px">visibility</i>
                            </button>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted" style="padding:30px">
                    <i class="material-icons" style="font-size:24px;vertical-align:middle">inbox</i><br>
                    <span style="margin-top:8px;display:inline-block">Belum ada riwayat konsultasi</span>
                </td></tr>`;
            }
        })
        .catch(err => {
            console.error('Riwayat error:', err);
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted" style="padding:30px">
                <i class="material-icons" style="font-size:24px;vertical-align:middle">error</i><br>
                <span style="margin-top:8px;display:inline-block">Error memuat data</span>
            </td></tr>`;
        });
}

function viewKonsul(noPermintaan) {
    // Load data untuk edit
    loadDataForEdit(noPermintaan);
    
    // Scroll ke atas form
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ========================================
// HELPERS
// ========================================
function escapeHtml(t) {
    if(!t) return '';
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML.replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function truncate(t, max) {
    if(!t) return '';
    return t.length <= max ? t : t.substring(0, max) + '...';
}

// ========================================
// SIMPAN KONSULTASI
// ========================================
function simpanKonsultasi() {
    const kdDokterTujuan = document.getElementById('kd_dokter_tujuan').value;
    const uraian = document.getElementById('uraian').value.trim();
    
    // Validasi
    if (!kdDokterTujuan) {
        Swal.fire({
            icon: 'warning',
            title: 'Perhatian',
            text: 'Silakan pilih Dokter Yang Dikonsuli terlebih dahulu!',
            confirmButtonText: 'OK'
        });
        document.getElementById('dokter_tujuan_input').focus();
        return;
    }
    
    if (!uraian) {
        Swal.fire({
            icon: 'warning',
            title: 'Perhatian',
            text: 'Uraian Konsultasi harus diisi!',
            confirmButtonText: 'OK'
        });
        document.getElementById('uraian').focus();
        return;
    }
    
    // Konfirmasi simpan
    Swal.fire({
        title: 'Konfirmasi Simpan',
        text: 'Apakah Anda yakin ingin menyimpan data konsultasi ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Simpan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#64748b'
    }).then((result) => {
        if (result.isConfirmed) {
            doSimpan();
        }
    });
}

function doSimpan() {
    const btn = document.getElementById('btnSimpan');
    const btnText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons" style="font-size:16px;animation:spin 1s linear infinite">refresh</i> MENYIMPAN...';
    
    const formData = new FormData();
    formData.append('aksi', 'simpan_konsultasi_medik');
    formData.append('no_rawat', document.querySelector('[name="no_rawat"]').value);
    formData.append('no_permintaan', document.querySelector('[name="no_permintaan"]').value);
    formData.append('tanggal', document.getElementById('tanggal').value);
    formData.append('jenis_permintaan', document.getElementById('permintaan').value);
    formData.append('kd_dokter', document.getElementById('kd_dokter').value);
    formData.append('kd_dokter_dikonsuli', document.getElementById('kd_dokter_tujuan').value);
    formData.append('diagnosa_kerja', document.getElementById('diagnosa').value);
    formData.append('uraian_konsultasi', document.getElementById('uraian').value);
    
    fetch(getApiUrl3(), { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = btnText;
            
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    // Reload halaman
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: data.message,
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = btnText;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan: ' + err.message,
                confirmButtonText: 'OK'
            });
        });
}

// ========================================
// HAPUS KONSULTASI
// ========================================
let selectedNoPermintaan = null;

function hapusKonsultasi() {
    if (!selectedNoPermintaan) {
        Swal.fire({
            icon: 'warning',
            title: 'Perhatian',
            text: 'Tidak ada data yang dipilih untuk dihapus',
            confirmButtonText: 'OK'
        });
        return;
    }
    
    Swal.fire({
        title: 'Konfirmasi Hapus',
        html: `<p>Apakah Anda yakin ingin menghapus data konsultasi ini?</p>
               <p style="font-size:12px;color:#64748b">No. Permintaan: <strong>${selectedNoPermintaan}</strong></p>
               <p style="font-size:12px;color:#ef4444;margin-top:10px">Data yang dihapus tidak dapat dikembalikan!</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b'
    }).then((result) => {
        if (result.isConfirmed) {
            doHapus(selectedNoPermintaan);
        }
    });
}

function doHapus(noPermintaan) {
    const btn = document.getElementById('btnHapus');
    const btnText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="material-icons" style="font-size:16px;animation:spin 1s linear infinite">refresh</i> MENGHAPUS...';
    
    const formData = new FormData();
    formData.append('aksi', 'hapus_konsultasi_medik');
    formData.append('no_permintaan', noPermintaan);
    
    fetch(getApiUrl3(), { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = btnText;
            
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: data.message,
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = btnText;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan: ' + err.message,
                confirmButtonText: 'OK'
            });
        });
}

// ========================================
// LOAD DATA UNTUK EDIT
// ========================================
function loadDataForEdit(noPermintaan) {
    const formData = new FormData();
    formData.append('aksi', 'get_detail_konsul');
    formData.append('no_permintaan', noPermintaan);
    
    fetch(getApiUrl(), { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                const d = data.data;
                
                // Set form values
                document.querySelector('[name="no_permintaan"]').value = d.no_permintaan;
                document.getElementById('tanggal').value = d.tanggal ? d.tanggal.replace(' ', 'T').substring(0, 16) : '';
                document.getElementById('permintaan').value = d.jenis_permintaan || 'Konsultasi';
                document.getElementById('kd_dokter_tujuan').value = d.kd_dokter_dikonsuli;
                document.getElementById('dokter_tujuan_input').value = d.kd_dokter_dikonsuli + ' - ' + (d.nm_dokter_tujuan || '');
                document.getElementById('diagnosa').value = d.diagnosa_kerja || '';
                document.getElementById('uraian').value = d.uraian_konsultasi || '';
                
                // Store selected for delete
                selectedNoPermintaan = d.no_permintaan;
                
                // Show delete button if dokter is same
                if (d.kd_dokter === KONSUL_CONFIG.kdDokterLogin) {
                    document.getElementById('btnHapus').style.display = 'inline-flex';
                }
                
                // Change header badge to EDIT
                const badge = document.querySelector('.mode-badge');
                if (badge) {
                    badge.className = 'mode-badge mode-edit';
                    badge.textContent = '✏️ EDIT';
                }
            }
        })
        .catch(err => console.error('Error loading data:', err));
}

// Spinner animation
const style = document.createElement('style');
style.textContent = '@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }';
document.head.appendChild(style);
</script>