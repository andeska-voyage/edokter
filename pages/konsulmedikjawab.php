<?php
/**
 * Konsul Medik Jawab - Jawaban Konsultasi Dokter
 * E-Dokter - SIMRS Khanza
 * 
 * Halaman untuk:
 * 1. Melihat & menjawab permintaan konsultasi yang masuk (ditujukan ke dokter login)
 * 2. Melihat status jawaban konsultasi yang diajukan (dibuat oleh dokter login)
 */

define('BASE_URL', APP_BASE_URL);

// Ambil kode dokter login
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

// ====== STATISTIK PERMINTAAN MASUK (ditujukan ke saya) ======
$queryMasukTotal = bukaquery("SELECT COUNT(*) as total FROM konsultasi_medik WHERE kd_dokter_dikonsuli = '$kd_dokter_login'");
$rsMasukTotal = mysqli_fetch_array($queryMasukTotal);
$masukTotal = $rsMasukTotal['total'] ?? 0;

$queryMasukBelum = bukaquery("SELECT COUNT(*) as total FROM konsultasi_medik k 
                              LEFT JOIN jawaban_konsultasi_medik j ON k.no_permintaan = j.no_permintaan 
                              WHERE k.kd_dokter_dikonsuli = '$kd_dokter_login' AND j.no_permintaan IS NULL");
$rsMasukBelum = mysqli_fetch_array($queryMasukBelum);
$masukBelum = $rsMasukBelum['total'] ?? 0;

$masukSudah = $masukTotal - $masukBelum;

// ====== STATISTIK PERMINTAAN KELUAR (saya yang minta) ======
$queryKeluarTotal = bukaquery("SELECT COUNT(*) as total FROM konsultasi_medik WHERE kd_dokter = '$kd_dokter_login'");
$rsKeluarTotal = mysqli_fetch_array($queryKeluarTotal);
$keluarTotal = $rsKeluarTotal['total'] ?? 0;

$queryKeluarBelum = bukaquery("SELECT COUNT(*) as total FROM konsultasi_medik k 
                               LEFT JOIN jawaban_konsultasi_medik j ON k.no_permintaan = j.no_permintaan 
                               WHERE k.kd_dokter = '$kd_dokter_login' AND j.no_permintaan IS NULL");
$rsKeluarBelum = mysqli_fetch_array($queryKeluarBelum);
$keluarBelum = $rsKeluarBelum['total'] ?? 0;

$keluarSudah = $keluarTotal - $keluarBelum;
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/css/template4.css?v=<?= time() ?>">

<style>
/* Tabs Navigation */
.tabs-nav {
    display: flex;
    gap: 0;
    margin-bottom: 20px;
    background: #fff;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.tab-btn {
    flex: 1;
    padding: 16px 20px;
    background: #fff;
    border: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.2s;
    border-bottom: 3px solid transparent;
}
.tab-btn:hover { background: #f8fafc; }
.tab-btn.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
    background: #f0f9ff;
}
.tab-btn .badge {
    background: #ef4444;
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
}
.tab-btn.active .badge { background: #3b82f6; }
.tab-btn .badge.zero { background: #94a3b8; }

/* Tab Content */
.tab-content { display: none; }
.tab-content.active { display: block; }

/* Stats Cards */
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 16px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    gap: 15px;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
.stat-card.active { border-color: #3b82f6; }
.stat-card .icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.stat-card .icon i { font-size: 24px; color: #fff; }
.stat-card .icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.stat-card .icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-card .icon.green { background: linear-gradient(135deg, #10b981, #059669); }
.stat-card .info h3 { font-size: 24px; font-weight: 700; color: #1e293b; margin: 0; }
.stat-card .info p { font-size: 12px; color: #64748b; margin: 4px 0 0 0; }

/* Konsul List */
.konsul-list { display: flex; flex-direction: column; gap: 12px; max-height: 500px; overflow-y: auto; }
.konsul-item {
    background: #fff;
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: all 0.2s;
    border-left: 4px solid #e2e8f0;
}
.konsul-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
.konsul-item.belum { border-left-color: #f59e0b; }
.konsul-item.sudah { border-left-color: #10b981; }
.konsul-item.active { border-left-color: #3b82f6; background: #f0f9ff; }

.konsul-item .header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}
.konsul-item .no-permintaan { font-size: 11px; color: #64748b; }
.konsul-item .tanggal { font-size: 11px; color: #94a3b8; }
.konsul-item .pasien { font-size: 14px; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
.konsul-item .dokter-info { font-size: 12px; color: #64748b; }
.konsul-item .diagnosa { font-size: 12px; color: #475569; margin-top: 8px; padding-top: 8px; border-top: 1px solid #f1f5f9; }

.badge-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}
.badge-belum { background: #fef3c7; color: #b45309; }
.badge-sudah { background: #d1fae5; color: #047857; }

/* Detail Panel */
.detail-panel {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}
.detail-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
    color: #fff;
    padding: 16px 20px;
}
.detail-header.keluar {
    background: linear-gradient(135deg, #065f46 0%, #047857 100%);
}
.detail-header h3 { font-size: 14px; font-weight: 600; margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px; }
.detail-header p { font-size: 11px; opacity: 0.8; margin: 0; }

.detail-body { padding: 20px; }
.detail-section { margin-bottom: 20px; }
.detail-section:last-child { margin-bottom: 0; }
.detail-section h4 {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 6px;
}
.detail-section h4 i { font-size: 16px; color: #3b82f6; }
.detail-content {
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px;
    font-size: 13px;
    color: #334155;
    line-height: 1.6;
}

/* Form Jawaban */
.jawaban-form { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
.jawaban-form label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; display: block; }
.jawaban-form textarea, .jawaban-form input[type="text"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    transition: all 0.2s;
}
.jawaban-form textarea { resize: vertical; min-height: 120px; }
.jawaban-form input[type="text"] { margin-bottom: 12px; }
.jawaban-form textarea:focus, .jawaban-form input[type="text"]:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}

.jawaban-existing {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 15px;
}
.jawaban-existing .label { font-size: 11px; color: #047857; font-weight: 600; margin-bottom: 6px; }
.jawaban-existing .content { font-size: 13px; color: #065f46; line-height: 1.6; }

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff; }
.btn-primary:hover:not(:disabled) { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
.btn-secondary { background: #f1f5f9; color: #475569; }
.btn-secondary:hover:not(:disabled) { background: #e2e8f0; }
.btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
.btn-danger:hover:not(:disabled) { background: linear-gradient(135deg, #dc2626, #b91c1c); }
.btn i { font-size: 18px; }

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #94a3b8;
}
.empty-state i { font-size: 48px; margin-bottom: 12px; }
.empty-state p { font-size: 14px; margin: 0; }

/* Layout */
.konsul-layout {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 20px;
    align-items: start;
}

@media (max-width: 900px) {
    .konsul-layout { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: 1fr; }
    .tabs-nav { flex-direction: column; }
}
</style>

<div class="modern-form-container">
    <!-- Header -->
    <div class="patient-header">
        <h1>
            <i class="material-icons">question_answer</i>
            KONSULTASI MEDIS
        </h1>
        <div class="patient-info">
            <div class="info-item">
                <i class="material-icons">person</i>
                <strong>Dokter Login:</strong> <?= $nm_dokter_login ?> (<?= $kd_dokter_login ?>)
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs-nav">
        <button class="tab-btn active" data-tab="masuk" onclick="switchTab('masuk')">
            <i class="material-icons">move_to_inbox</i>
            Permintaan Masuk
            <span class="badge <?= $masukBelum == 0 ? 'zero' : '' ?>"><?= $masukBelum ?></span>
        </button>
        <button class="tab-btn" data-tab="keluar" onclick="switchTab('keluar')">
            <i class="material-icons">outbox</i>
            Permintaan Keluar
            <span class="badge <?= $keluarBelum == 0 ? 'zero' : '' ?>"><?= $keluarBelum ?></span>
        </button>
    </div>

    <!-- TAB 1: Permintaan Masuk -->
    <div class="tab-content active" id="tab-masuk">
        <div class="stats-row">
            <div class="stat-card" data-filter="semua" data-type="masuk" onclick="setFilter('semua', 'masuk')">
                <div class="icon blue"><i class="material-icons">inbox</i></div>
                <div class="info">
                    <h3><?= $masukTotal ?></h3>
                    <p>Total Masuk</p>
                </div>
            </div>
            <div class="stat-card active" data-filter="belum" data-type="masuk" onclick="setFilter('belum', 'masuk')">
                <div class="icon orange"><i class="material-icons">pending_actions</i></div>
                <div class="info">
                    <h3><?= $masukBelum ?></h3>
                    <p>Belum Dijawab</p>
                </div>
            </div>
            <div class="stat-card" data-filter="sudah" data-type="masuk" onclick="setFilter('sudah', 'masuk')">
                <div class="icon green"><i class="material-icons">check_circle</i></div>
                <div class="info">
                    <h3><?= $masukSudah ?></h3>
                    <p>Sudah Dijawab</p>
                </div>
            </div>
        </div>

        <div class="konsul-layout">
            <div>
                <div class="form-card" style="padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="font-size: 14px; font-weight: 600; color: #1e293b; margin: 0;">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 6px;">list</i>
                            Daftar Permintaan Masuk
                        </h3>
                        <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 11px;" onclick="loadMasukList()">
                            <i class="material-icons" style="font-size: 16px;">refresh</i>
                        </button>
                    </div>
                    <div class="konsul-list" id="masukList">
                        <div class="empty-state">
                            <i class="material-icons">hourglass_empty</i>
                            <p>Memuat data...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="detail-panel" id="detailPanelMasuk">
                    <div class="detail-header">
                        <h3><i class="material-icons">assignment</i> Detail Konsultasi</h3>
                        <p>Pilih permintaan dari daftar di sebelah kiri</p>
                    </div>
                    <div class="detail-body">
                        <div class="empty-state" id="emptyDetailMasuk">
                            <i class="material-icons">touch_app</i>
                            <p>Klik salah satu permintaan untuk melihat detail dan menjawab</p>
                        </div>
                        <div id="detailContentMasuk" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: Permintaan Keluar -->
    <div class="tab-content" id="tab-keluar">
        <div class="stats-row">
            <div class="stat-card" data-filter="semua" data-type="keluar" onclick="setFilter('semua', 'keluar')">
                <div class="icon blue"><i class="material-icons">outbox</i></div>
                <div class="info">
                    <h3><?= $keluarTotal ?></h3>
                    <p>Total Keluar</p>
                </div>
            </div>
            <div class="stat-card active" data-filter="belum" data-type="keluar" onclick="setFilter('belum', 'keluar')">
                <div class="icon orange"><i class="material-icons">hourglass_top</i></div>
                <div class="info">
                    <h3><?= $keluarBelum ?></h3>
                    <p>Menunggu Jawaban</p>
                </div>
            </div>
            <div class="stat-card" data-filter="sudah" data-type="keluar" onclick="setFilter('sudah', 'keluar')">
                <div class="icon green"><i class="material-icons">task_alt</i></div>
                <div class="info">
                    <h3><?= $keluarSudah ?></h3>
                    <p>Sudah Dijawab</p>
                </div>
            </div>
        </div>

        <div class="konsul-layout">
            <div>
                <div class="form-card" style="padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="font-size: 14px; font-weight: 600; color: #1e293b; margin: 0;">
                            <i class="material-icons" style="font-size: 18px; vertical-align: middle; margin-right: 6px;">list</i>
                            Daftar Permintaan Keluar
                        </h3>
                        <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 11px;" onclick="loadKeluarList()">
                            <i class="material-icons" style="font-size: 16px;">refresh</i>
                        </button>
                    </div>
                    <div class="konsul-list" id="keluarList">
                        <div class="empty-state">
                            <i class="material-icons">hourglass_empty</i>
                            <p>Memuat data...</p>
                        </div>
                    </div>
                </div>
            </div>
            <div>
                <div class="detail-panel" id="detailPanelKeluar">
                    <div class="detail-header keluar">
                        <h3><i class="material-icons">assignment</i> Detail & Jawaban Konsultasi</h3>
                        <p>Pilih permintaan dari daftar di sebelah kiri</p>
                    </div>
                    <div class="detail-body">
                        <div class="empty-state" id="emptyDetailKeluar">
                            <i class="material-icons">touch_app</i>
                            <p>Klik salah satu permintaan untuk melihat detail dan jawaban</p>
                        </div>
                        <div id="detailContentKeluar" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const KONSUL_CONFIG = {
    kdDokterLogin: '<?= $kd_dokter_login ?>',
    nmDokterLogin: '<?= $nm_dokter_login ?>',
    baseUrl: '<?= BASE_URL ?>'
};

let currentTab = 'masuk';
let filterMasuk = 'belum';
let filterKeluar = 'belum';
let selectedMasuk = null;
let selectedKeluar = null;

// ========================================
// INIT
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    loadMasukList();
    loadKeluarList();
});

function getApiUrl() {
    return KONSUL_CONFIG.baseUrl + '/pages/proses2.php';
}

function getApiUrl3() {
    return KONSUL_CONFIG.baseUrl + '/pages/proses3.php';
}

// ========================================
// TAB SWITCHING
// ========================================
function switchTab(tab) {
    currentTab = tab;
    
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.tab === tab) btn.classList.add('active');
    });
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById('tab-' + tab).classList.add('active');
}

// ========================================
// FILTER
// ========================================
function setFilter(filter, type) {
    if (type === 'masuk') {
        filterMasuk = filter;
        document.querySelectorAll('#tab-masuk .stat-card').forEach(card => {
            card.classList.remove('active');
            if (card.dataset.filter === filter) card.classList.add('active');
        });
        loadMasukList();
    } else {
        filterKeluar = filter;
        document.querySelectorAll('#tab-keluar .stat-card').forEach(card => {
            card.classList.remove('active');
            if (card.dataset.filter === filter) card.classList.add('active');
        });
        loadKeluarList();
    }
}

// ========================================
// LOAD MASUK LIST (ditujukan ke saya)
// ========================================
function loadMasukList() {
    const container = document.getElementById('masukList');
    container.innerHTML = '<div class="empty-state"><i class="material-icons" style="animation:spin 1s linear infinite">refresh</i><p>Memuat data...</p></div>';
    
    const formData = new FormData();
    formData.append('aksi', 'get_konsul_masuk');
    formData.append('filter', filterMasuk);
    
    fetch(getApiUrl(), { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.data && data.data.length > 0) {
                renderMasukList(data.data);
            } else {
                container.innerHTML = `<div class="empty-state">
                    <i class="material-icons">inbox</i>
                    <p>Tidak ada permintaan konsultasi</p>
                </div>`;
            }
        })
        .catch(err => {
            container.innerHTML = `<div class="empty-state"><i class="material-icons">error</i><p>Error memuat data</p></div>`;
        });
}

function renderMasukList(list) {
    const container = document.getElementById('masukList');
    
    let html = '';
    list.forEach(item => {
        const statusClass = item.sudah_dijawab ? 'sudah' : 'belum';
        const statusBadge = item.sudah_dijawab 
            ? '<span class="badge-status badge-sudah">Sudah Dijawab</span>'
            : '<span class="badge-status badge-belum">Belum Dijawab</span>';
        const activeClass = selectedMasuk === item.no_permintaan ? 'active' : '';
        
        html += `
            <div class="konsul-item ${statusClass} ${activeClass}" onclick="selectMasuk('${item.no_permintaan}', this)">
                <div class="header">
                    <span class="no-permintaan">${item.no_permintaan}</span>
                    ${statusBadge}
                </div>
                <div class="pasien">${item.nm_pasien || '-'}</div>
                <div class="dokter-info">
                    <i class="material-icons" style="font-size:14px;vertical-align:middle">person</i>
                    Dari: <strong>${item.nm_dokter_konsul || '-'}</strong>
                </div>
                <div class="tanggal">
                    <i class="material-icons" style="font-size:14px;vertical-align:middle">schedule</i>
                    ${item.tanggal || '-'}
                </div>
                ${item.diagnosa_kerja ? `<div class="diagnosa"><strong>Dx:</strong> ${truncate(item.diagnosa_kerja, 50)}</div>` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ========================================
// LOAD KELUAR LIST (saya yang minta)
// ========================================
function loadKeluarList() {
    const container = document.getElementById('keluarList');
    container.innerHTML = '<div class="empty-state"><i class="material-icons" style="animation:spin 1s linear infinite">refresh</i><p>Memuat data...</p></div>';
    
    const formData = new FormData();
    formData.append('aksi', 'get_konsul_keluar');
    formData.append('filter', filterKeluar);
    
    fetch(getApiUrl(), { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.data && data.data.length > 0) {
                renderKeluarList(data.data);
            } else {
                container.innerHTML = `<div class="empty-state">
                    <i class="material-icons">outbox</i>
                    <p>Tidak ada permintaan konsultasi</p>
                </div>`;
            }
        })
        .catch(err => {
            container.innerHTML = `<div class="empty-state"><i class="material-icons">error</i><p>Error memuat data</p></div>`;
        });
}

function renderKeluarList(list) {
    const container = document.getElementById('keluarList');
    
    let html = '';
    list.forEach(item => {
        const statusClass = item.sudah_dijawab ? 'sudah' : 'belum';
        const statusBadge = item.sudah_dijawab 
            ? '<span class="badge-status badge-sudah">Sudah Dijawab</span>'
            : '<span class="badge-status badge-belum">Menunggu Jawaban</span>';
        const activeClass = selectedKeluar === item.no_permintaan ? 'active' : '';
        
        html += `
            <div class="konsul-item ${statusClass} ${activeClass}" onclick="selectKeluar('${item.no_permintaan}', this)">
                <div class="header">
                    <span class="no-permintaan">${item.no_permintaan}</span>
                    ${statusBadge}
                </div>
                <div class="pasien">${item.nm_pasien || '-'}</div>
                <div class="dokter-info">
                    <i class="material-icons" style="font-size:14px;vertical-align:middle">person_outline</i>
                    Kepada: <strong>${item.nm_dokter_dikonsuli || '-'}</strong>
                </div>
                <div class="tanggal">
                    <i class="material-icons" style="font-size:14px;vertical-align:middle">schedule</i>
                    ${item.tanggal || '-'}
                </div>
                ${item.diagnosa_kerja ? `<div class="diagnosa"><strong>Dx:</strong> ${truncate(item.diagnosa_kerja, 50)}</div>` : ''}
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ========================================
// SELECT & LOAD DETAIL - MASUK
// ========================================
function selectMasuk(noPermintaan, el) {
    selectedMasuk = noPermintaan;
    document.querySelectorAll('#masukList .konsul-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');
    loadDetailMasuk(noPermintaan);
}

function loadDetailMasuk(noPermintaan) {
    const detailContent = document.getElementById('detailContentMasuk');
    const emptyDetail = document.getElementById('emptyDetailMasuk');
    
    emptyDetail.style.display = 'none';
    detailContent.style.display = 'block';
    detailContent.innerHTML = '<div class="empty-state"><i class="material-icons" style="animation:spin 1s linear infinite">refresh</i><p>Memuat detail...</p></div>';
    
    const formData = new FormData();
    formData.append('aksi', 'get_detail_konsul_jawab');
    formData.append('no_permintaan', noPermintaan);
    
    fetch(getApiUrl(), { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                renderDetailMasuk(data.data);
            } else {
                detailContent.innerHTML = '<div class="empty-state"><i class="material-icons">error</i><p>Data tidak ditemukan</p></div>';
            }
        })
        .catch(err => {
            detailContent.innerHTML = '<div class="empty-state"><i class="material-icons">error</i><p>Error memuat detail</p></div>';
        });
}

function renderDetailMasuk(data) {
    const detailContent = document.getElementById('detailContentMasuk');
    
    document.querySelector('#detailPanelMasuk .detail-header h3').innerHTML = `<i class="material-icons">assignment</i> ${data.no_permintaan}`;
    document.querySelector('#detailPanelMasuk .detail-header p').textContent = `${data.jenis_permintaan || 'Konsultasi'} - ${data.tanggal || '-'}`;
    
    let jawabanSection = '';
    if (data.jawaban) {
        jawabanSection = `
            <div class="jawaban-existing">
                <div class="label"><i class="material-icons" style="font-size:14px;vertical-align:middle">check_circle</i> Jawaban Anda (${data.tanggal_jawaban || '-'})</div>
                <div class="content">${data.jawaban.diagnosa_kerja ? '<strong>Dx:</strong> ' + data.jawaban.diagnosa_kerja + '<br><br>' : ''}${data.jawaban.uraian_jawaban || '-'}</div>
            </div>
        `;
    }
    
    detailContent.innerHTML = `
        <div class="detail-section">
            <h4><i class="material-icons">person</i> Data Pasien</h4>
            <div class="detail-content">
                <strong>${data.nm_pasien || '-'}</strong><br>
                No. RM: ${data.no_rkm_medis || '-'} | No. Rawat: ${data.no_rawat || '-'}
            </div>
        </div>
        
        <div class="detail-section">
            <h4><i class="material-icons">person_outline</i> Dokter Yang Konsul</h4>
            <div class="detail-content">${data.nm_dokter_konsul || '-'}</div>
        </div>
        
        <div class="detail-section">
            <h4><i class="material-icons">medical_services</i> Diagnosa Kerja</h4>
            <div class="detail-content">${data.diagnosa_kerja || '-'}</div>
        </div>
        
        <div class="detail-section">
            <h4><i class="material-icons">description</i> Uraian Konsultasi</h4>
            <div class="detail-content">${data.uraian_konsultasi || '-'}</div>
        </div>
        
        <div class="jawaban-form">
            <h4 style="font-size:13px;font-weight:600;color:#1e293b;margin:0 0 15px 0;">
                <i class="material-icons" style="font-size:18px;vertical-align:middle;margin-right:6px;color:#3b82f6">reply</i>
                ${data.jawaban ? 'Edit Jawaban' : 'Tulis Jawaban'}
            </h4>
            
            ${jawabanSection}
            
            <label>Diagnosa Kerja (Hasil Konsul)</label>
            <input type="text" id="jawabanDiagnosa" placeholder="Masukkan diagnosa..." value="${data.jawaban?.diagnosa_kerja || ''}">
            
            <label>Uraian Jawaban</label>
            <textarea id="jawabanUraian" placeholder="Tulis jawaban konsultasi di sini...">${data.jawaban?.uraian_jawaban || ''}</textarea>
            
            <div class="form-actions">
                <button class="btn btn-primary" onclick="simpanJawaban('${data.no_permintaan}')">
                    <i class="material-icons">save</i> Simpan Jawaban
                </button>
                ${data.jawaban ? `
                <button class="btn btn-danger" onclick="hapusJawaban('${data.no_permintaan}')">
                    <i class="material-icons">delete</i> Hapus
                </button>
                ` : ''}
            </div>
        </div>
    `;
}

// ========================================
// SELECT & LOAD DETAIL - KELUAR
// ========================================
function selectKeluar(noPermintaan, el) {
    selectedKeluar = noPermintaan;
    document.querySelectorAll('#keluarList .konsul-item').forEach(item => item.classList.remove('active'));
    el.classList.add('active');
    loadDetailKeluar(noPermintaan);
}

function loadDetailKeluar(noPermintaan) {
    const detailContent = document.getElementById('detailContentKeluar');
    const emptyDetail = document.getElementById('emptyDetailKeluar');
    
    emptyDetail.style.display = 'none';
    detailContent.style.display = 'block';
    detailContent.innerHTML = '<div class="empty-state"><i class="material-icons" style="animation:spin 1s linear infinite">refresh</i><p>Memuat detail...</p></div>';
    
    const formData = new FormData();
    formData.append('aksi', 'get_detail_konsul_jawab');
    formData.append('no_permintaan', noPermintaan);
    
    fetch(getApiUrl(), { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success' && data.data) {
                renderDetailKeluar(data.data);
            } else {
                detailContent.innerHTML = '<div class="empty-state"><i class="material-icons">error</i><p>Data tidak ditemukan</p></div>';
            }
        })
        .catch(err => {
            detailContent.innerHTML = '<div class="empty-state"><i class="material-icons">error</i><p>Error memuat detail</p></div>';
        });
}

function renderDetailKeluar(data) {
    const detailContent = document.getElementById('detailContentKeluar');
    
    document.querySelector('#detailPanelKeluar .detail-header h3').innerHTML = `<i class="material-icons">assignment</i> ${data.no_permintaan}`;
    document.querySelector('#detailPanelKeluar .detail-header p').textContent = `${data.jenis_permintaan || 'Konsultasi'} - ${data.tanggal || '-'}`;
    
    let jawabanSection = '';
    if (data.jawaban) {
        jawabanSection = `
            <div class="detail-section">
                <h4><i class="material-icons" style="color:#10b981">check_circle</i> Jawaban dari ${data.nm_dokter_dikonsuli || 'Dokter'}</h4>
                <div class="detail-content" style="background:#ecfdf5;border:1px solid #a7f3d0;">
                    <div style="font-size:11px;color:#047857;margin-bottom:8px;">Dijawab: ${data.tanggal_jawaban || '-'}</div>
                    ${data.jawaban.diagnosa_kerja ? '<strong>Diagnosa:</strong> ' + data.jawaban.diagnosa_kerja + '<br><br>' : ''}
                    <strong>Jawaban:</strong><br>${data.jawaban.uraian_jawaban || '-'}
                </div>
            </div>
        `;
    } else {
        jawabanSection = `
            <div class="detail-section">
                <h4><i class="material-icons" style="color:#f59e0b">hourglass_top</i> Status Jawaban</h4>
                <div class="detail-content" style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e;">
                    <i class="material-icons" style="font-size:16px;vertical-align:middle">pending</i>
                    Menunggu jawaban dari <strong>${data.nm_dokter_dikonsuli || 'Dokter'}</strong>
                </div>
            </div>
        `;
    }
    
    detailContent.innerHTML = `
        <div class="detail-section">
            <h4><i class="material-icons">person</i> Data Pasien</h4>
            <div class="detail-content">
                <strong>${data.nm_pasien || '-'}</strong><br>
                No. RM: ${data.no_rkm_medis || '-'} | No. Rawat: ${data.no_rawat || '-'}
            </div>
        </div>
        
        <div class="detail-section">
            <h4><i class="material-icons">person_outline</i> Dokter Yang Dikonsuli</h4>
            <div class="detail-content">${data.nm_dokter_dikonsuli || '-'}</div>
        </div>
        
        <div class="detail-section">
            <h4><i class="material-icons">medical_services</i> Diagnosa Kerja</h4>
            <div class="detail-content">${data.diagnosa_kerja || '-'}</div>
        </div>
        
        <div class="detail-section">
            <h4><i class="material-icons">description</i> Uraian Konsultasi</h4>
            <div class="detail-content">${data.uraian_konsultasi || '-'}</div>
        </div>
        
        ${jawabanSection}
    `;
}

// ========================================
// SIMPAN & HAPUS JAWABAN
// ========================================
function simpanJawaban(noPermintaan) {
    const diagnosa = document.getElementById('jawabanDiagnosa').value.trim();
    const uraian = document.getElementById('jawabanUraian').value.trim();
    
    if (!uraian) {
        Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Uraian jawaban harus diisi!' });
        return;
    }
    
    Swal.fire({
        title: 'Konfirmasi',
        text: 'Apakah Anda yakin ingin menyimpan jawaban ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Simpan',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('aksi', 'simpan_jawaban_konsul');
            formData.append('no_permintaan', noPermintaan);
            formData.append('diagnosa_kerja', diagnosa);
            formData.append('uraian_jawaban', uraian);
            
            fetch(getApiUrl3(), { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 1500, showConfirmButton: false })
                            .then(() => {
                                loadMasukList();
                                loadDetailMasuk(noPermintaan);
                            });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
                    }
                })
                .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message }));
        }
    });
}

function hapusJawaban(noPermintaan) {
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: 'Apakah Anda yakin ingin menghapus jawaban ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444'
    }).then(result => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('aksi', 'hapus_jawaban_konsul');
            formData.append('no_permintaan', noPermintaan);
            
            fetch(getApiUrl3(), { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 1500, showConfirmButton: false })
                            .then(() => {
                                loadMasukList();
                                loadDetailMasuk(noPermintaan);
                            });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Gagal', text: data.message });
                    }
                })
                .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message }));
        }
    });
}

// ========================================
// HELPERS
// ========================================
function truncate(t, max) {
    if (!t) return '';
    return t.length <= max ? t : t.substring(0, max) + '...';
}

// Spinner
const style = document.createElement('style');
style.textContent = '@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }';
document.head.appendChild(style);
</script>
