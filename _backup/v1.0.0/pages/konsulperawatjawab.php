<?php
/**
 * Konsul Perawat (SBAR) - Jawaban Konsultasi dari Perawat ke Dokter
 * E-Dokter
 *
 * Halaman untuk dokter:
 *   - Melihat permintaan konsultasi SBAR yang masuk dari perawat
 *   - Menjawab dengan respon, instruksi, dan rencana
 */

define('BASE_URL', APP_BASE_URL);

// Ambil kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
$nm_dokter_login = '';

if (!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
    $queryDokterLogin = bukaquery("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter_login'");
    $rsDokterLogin = mysqli_fetch_array($queryDokterLogin);
    if ($rsDokterLogin) $nm_dokter_login = $rsDokterLogin['nm_dokter'];
}

// ====== STATISTIK SBAR MASUK (ditujukan ke dokter login) ======
$qTotal = bukaquery("SELECT COUNT(*) as total FROM konsultasi_perawat WHERE kd_dokter_dikonsuli = '$kd_dokter_login'");
$total = ($r = mysqli_fetch_array($qTotal)) ? $r['total'] : 0;

$qBelum = bukaquery("SELECT COUNT(*) as total FROM konsultasi_perawat k
                     LEFT JOIN jawaban_konsultasi_perawat j ON k.no_permintaan = j.no_permintaan
                     WHERE k.kd_dokter_dikonsuli = '$kd_dokter_login' AND j.no_permintaan IS NULL");
$belum = ($r = mysqli_fetch_array($qBelum)) ? $r['total'] : 0;

$sudah = $total - $belum;
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/css/template4.css?v=<?= time() ?>">

<style>
.stats-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
    display: flex;
    align-items: center;
    gap: 12px;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
.stat-card.active { border-color: #3b82f6; background: #eff6ff; }
.stat-card .icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff;
}
.stat-card .icon.blue   { background: linear-gradient(135deg, #3b82f6, #2563eb); }
.stat-card .icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
.stat-card .icon.green  { background: linear-gradient(135deg, #10b981, #059669); }
.stat-card .info h3 { margin: 0; font-size: 22px; font-weight: 700; color: #1e293b; }
.stat-card .info p  { margin: 0; font-size: 11px; color: #64748b; }

.konsul-layout {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 20px;
    align-items: start;
}
@media (max-width: 900px) {
    .konsul-layout { grid-template-columns: 1fr; }
    .stats-row { grid-template-columns: 1fr; }
}

.konsul-list { max-height: 600px; overflow-y: auto; }
.konsul-item {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #cbd5e1;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}
.konsul-item:hover  { border-color: #3b82f6; transform: translateX(2px); }
.konsul-item.active { border-color: #3b82f6; border-left-color: #3b82f6; background: #eff6ff; }
.konsul-item.belum  { border-left-color: #f59e0b; }
.konsul-item.sudah  { border-left-color: #10b981; }
.konsul-item .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.konsul-item .no-permintaan { font-size: 11px; font-weight: 600; color: #475569; }
.konsul-item .pasien { font-size: 13px; font-weight: 600; color: #1e293b; }
.konsul-item .perawat-info { font-size: 11px; color: #64748b; margin-top: 4px; }
.konsul-item .tanggal { font-size: 10px; color: #94a3b8; margin-top: 4px; }
.konsul-item .preview-sbar { font-size: 11px; color: #475569; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #e2e8f0; }

.badge-status {
    padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 600;
}
.badge-belum { background: #fef3c7; color: #b45309; }
.badge-sudah { background: #d1fae5; color: #047857; }

.detail-panel {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}
.detail-header {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    color: #fff; padding: 16px 20px;
}
.detail-header h3 { font-size: 14px; font-weight: 600; margin: 0 0 4px 0; display: flex; align-items: center; gap: 8px; }
.detail-header p  { font-size: 11px; opacity: 0.85; margin: 0; }
.detail-body { padding: 20px; }
.detail-section { margin-bottom: 20px; }
.detail-section:last-child { margin-bottom: 0; }
.detail-section h4 {
    font-size: 11px; font-weight: 600; color: #64748b;
    text-transform: uppercase; margin: 0 0 8px 0;
    display: flex; align-items: center; gap: 6px;
}
.detail-section h4 i { font-size: 16px; }

.sbar-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
@media (max-width: 700px) { .sbar-grid { grid-template-columns: 1fr; } }
.sbar-box {
    border-radius: 8px;
    padding: 10px 12px;
    border-left: 4px solid;
    font-size: 13px;
    line-height: 1.5;
    color: #334155;
    white-space: pre-wrap;
    word-break: break-word;
}
.sbar-box .label { font-size: 11px; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; }
.sbar-s { background: #eff6ff; border-color: #3b82f6; } .sbar-s .label { color: #1d4ed8; }
.sbar-b { background: #f0fdf4; border-color: #10b981; } .sbar-b .label { color: #047857; }
.sbar-a { background: #fffbeb; border-color: #f59e0b; } .sbar-a .label { color: #b45309; }
.sbar-r { background: #fdf2f8; border-color: #ec4899; } .sbar-r .label { color: #be185d; }

.detail-content {
    background: #f8fafc; border-radius: 8px; padding: 12px;
    font-size: 13px; color: #334155; line-height: 1.6;
}
.jawaban-form { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
.jawaban-form label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; display: block; }
.jawaban-form input[type="text"], .jawaban-form textarea {
    width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;
    font-size: 13px; transition: all 0.2s; margin-bottom: 12px;
}
.jawaban-form textarea { resize: vertical; min-height: 100px; }
.jawaban-form input:focus, .jawaban-form textarea:focus {
    outline: none; border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
}
.jawaban-existing {
    background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 8px;
    padding: 12px; margin-bottom: 15px;
}
.jawaban-existing .label { font-size: 11px; color: #6d28d9; font-weight: 600; margin-bottom: 6px; }
.jawaban-existing .content { font-size: 13px; color: #4c1d95; line-height: 1.6; }

.form-actions { display: flex; gap: 10px; margin-top: 5px; }
.btn {
    padding: 10px 20px; border: none; border-radius: 8px;
    font-size: 13px; font-weight: 600; cursor: pointer;
    display: inline-flex; align-items: center; gap: 6px;
    transition: all 0.2s;
}
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-primary { background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; }
.btn-primary:hover:not(:disabled) { background: linear-gradient(135deg, #6d28d9, #5b21b6); }
.btn-secondary { background: #f1f5f9; color: #475569; }
.btn-secondary:hover:not(:disabled) { background: #e2e8f0; }
.btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
.btn-danger:hover:not(:disabled) { background: linear-gradient(135deg, #dc2626, #b91c1c); }
.btn i { font-size: 18px; }

.empty-state { text-align: center; padding: 40px 20px; color: #94a3b8; }
.empty-state i { font-size: 48px; margin-bottom: 12px; }
.empty-state p { font-size: 14px; margin: 0; }

@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>

<div class="modern-form-container">
    <!-- Header -->
    <div class="patient-header">
        <h1>
            <i class="material-icons">groups</i>
            KONSUL PERAWAT (SBAR)
        </h1>
        <div class="patient-info">
            <div class="info-item">
                <i class="material-icons">person</i>
                <strong>Dokter Login:</strong> <?= $nm_dokter_login ?> (<?= $kd_dokter_login ?>)
            </div>
            <div class="info-item">
                <i class="material-icons">info</i>
                <span style="color:#64748b;">Konsultasi SBAR dari perawat ke dokter</span>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-row">
        <div class="stat-card" data-filter="semua" onclick="setFilterPerawat('semua')">
            <div class="icon blue"><i class="material-icons">inbox</i></div>
            <div class="info">
                <h3><?= $total ?></h3>
                <p>Total Permintaan</p>
            </div>
        </div>
        <div class="stat-card active" data-filter="belum" onclick="setFilterPerawat('belum')">
            <div class="icon orange"><i class="material-icons">pending_actions</i></div>
            <div class="info">
                <h3><?= $belum ?></h3>
                <p>Belum Dijawab</p>
            </div>
        </div>
        <div class="stat-card" data-filter="sudah" onclick="setFilterPerawat('sudah')">
            <div class="icon green"><i class="material-icons">check_circle</i></div>
            <div class="info">
                <h3><?= $sudah ?></h3>
                <p>Sudah Dijawab</p>
            </div>
        </div>
    </div>

    <!-- List + Detail -->
    <div class="konsul-layout">
        <!-- LIST -->
        <div>
            <div class="form-card" style="padding: 16px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3 style="font-size:14px; font-weight:600; color:#1e293b; margin:0;">
                        <i class="material-icons" style="font-size:18px; vertical-align:middle; margin-right:6px;">list</i>
                        Daftar SBAR Masuk
                    </h3>
                    <button class="btn btn-secondary" style="padding:6px 12px; font-size:11px;" onclick="loadKonsulPerawatList()">
                        <i class="material-icons" style="font-size:16px;">refresh</i>
                    </button>
                </div>
                <div class="konsul-list" id="konsulPerawatList">
                    <div class="empty-state">
                        <i class="material-icons">hourglass_empty</i>
                        <p>Memuat data...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- DETAIL -->
        <div>
            <div class="detail-panel">
                <div class="detail-header" id="detailPanelHeader">
                    <h3><i class="material-icons">assignment</i> Detail Konsultasi SBAR</h3>
                    <p>Pilih permintaan dari daftar di sebelah kiri</p>
                </div>
                <div class="detail-body">
                    <div class="empty-state" id="emptyDetailPerawat">
                        <i class="material-icons">touch_app</i>
                        <p>Klik salah satu permintaan untuk melihat detail SBAR dan menjawab</p>
                    </div>
                    <div id="detailContentPerawat" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const KONSUL_PERAWAT_CONFIG = {
    kdDokterLogin: '<?= $kd_dokter_login ?>',
    nmDokterLogin: '<?= $nm_dokter_login ?>',
    apiUrl: '<?= BASE_URL ?>/pages/konsulperawat_ajax.php'
};

let filterPerawat = 'belum';
let selectedPerawat = null;

document.addEventListener('DOMContentLoaded', function() {
    loadKonsulPerawatList();
});

function setFilterPerawat(filter) {
    filterPerawat = filter;
    document.querySelectorAll('.stat-card').forEach(c => {
        c.classList.remove('active');
        if (c.dataset.filter === filter) c.classList.add('active');
    });
    loadKonsulPerawatList();
}

function loadKonsulPerawatList() {
    const container = document.getElementById('konsulPerawatList');
    container.innerHTML = '<div class="empty-state"><i class="material-icons" style="animation:spin 1s linear infinite">refresh</i><p>Memuat data...</p></div>';

    const fd = new FormData();
    fd.append('aksi', 'get_konsul_perawat_masuk');
    fd.append('filter', filterPerawat);

    fetch(KONSUL_PERAWAT_CONFIG.apiUrl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(resp => {
            if (resp.status === 'success' && resp.data && resp.data.length > 0) {
                renderKonsulList(resp.data);
            } else {
                container.innerHTML = `<div class="empty-state">
                    <i class="material-icons">inbox</i>
                    <p>Tidak ada permintaan SBAR</p>
                </div>`;
            }
        })
        .catch(err => {
            container.innerHTML = `<div class="empty-state"><i class="material-icons">error</i><p>Error memuat data</p></div>`;
        });
}

function renderKonsulList(list) {
    const container = document.getElementById('konsulPerawatList');
    let html = '';
    list.forEach(item => {
        const statusClass = item.sudah_dijawab ? 'sudah' : 'belum';
        const statusBadge = item.sudah_dijawab
            ? '<span class="badge-status badge-sudah">Sudah Dijawab</span>'
            : '<span class="badge-status badge-belum">Belum Dijawab</span>';
        const activeClass = selectedPerawat === item.no_permintaan ? 'active' : '';
        const sPreview = item.situation ? truncate(item.situation, 80) : '';

        html += `
            <div class="konsul-item ${statusClass} ${activeClass}" onclick="selectKonsulPerawat('${item.no_permintaan}', this)">
                <div class="header">
                    <span class="no-permintaan">${item.no_permintaan}</span>
                    ${statusBadge}
                </div>
                <div class="pasien">${item.nm_pasien || '-'}</div>
                <div class="perawat-info">
                    <i class="material-icons" style="font-size:14px;vertical-align:middle">badge</i>
                    Dari: <strong>${item.nm_perawat || item.nip || '-'}</strong>
                </div>
                <div class="tanggal">
                    <i class="material-icons" style="font-size:14px;vertical-align:middle">schedule</i>
                    ${item.tanggal || '-'}
                </div>
                ${sPreview ? `<div class="preview-sbar"><strong>S:</strong> ${escapeHtml(sPreview)}</div>` : ''}
            </div>`;
    });
    container.innerHTML = html;
}

function selectKonsulPerawat(noPermintaan, el) {
    selectedPerawat = noPermintaan;
    document.querySelectorAll('#konsulPerawatList .konsul-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');
    loadDetailKonsulPerawat(noPermintaan);
}

function loadDetailKonsulPerawat(noPermintaan) {
    const detail = document.getElementById('detailContentPerawat');
    const empty = document.getElementById('emptyDetailPerawat');
    empty.style.display = 'none';
    detail.style.display = 'block';
    detail.innerHTML = '<div class="empty-state"><i class="material-icons" style="animation:spin 1s linear infinite">refresh</i><p>Memuat detail...</p></div>';

    const fd = new FormData();
    fd.append('aksi', 'get_detail_konsul_perawat');
    fd.append('no_permintaan', noPermintaan);

    fetch(KONSUL_PERAWAT_CONFIG.apiUrl, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(resp => {
            if (resp.status === 'success' && resp.data) {
                renderDetailKonsulPerawat(resp.data);
            } else {
                detail.innerHTML = `<div class="empty-state"><i class="material-icons">error</i><p>${resp.message || 'Data tidak ditemukan'}</p></div>`;
            }
        })
        .catch(err => {
            detail.innerHTML = '<div class="empty-state"><i class="material-icons">error</i><p>Error memuat detail</p></div>';
        });
}

function renderDetailKonsulPerawat(d) {
    const detail = document.getElementById('detailContentPerawat');
    const headerH3 = document.querySelector('#detailPanelHeader h3');
    const headerP  = document.querySelector('#detailPanelHeader p');
    headerH3.innerHTML = `<i class="material-icons">assignment</i> ${d.no_permintaan}`;
    headerP.textContent = `Konsultasi SBAR - ${d.tanggal || '-'}`;

    const j = d.jawaban;
    const jawabanExisting = j ? `
        <div class="jawaban-existing">
            <div class="label"><i class="material-icons" style="font-size:14px;vertical-align:middle">check_circle</i> Jawaban Anda (${d.tanggal_jawaban || '-'})</div>
            <div class="content">
                ${j.respon ? `<strong>Respon:</strong> ${escapeHtml(j.respon)}<br>` : ''}
                ${j.instruksi ? `<strong>Instruksi:</strong><br>${escapeHtml(j.instruksi)}<br><br>` : ''}
                ${j.rencana ? `<strong>Rencana:</strong><br>${escapeHtml(j.rencana)}` : ''}
            </div>
        </div>` : '';

    detail.innerHTML = `
        <div class="detail-section">
            <h4><i class="material-icons" style="color:#3b82f6">person</i> Data Pasien</h4>
            <div class="detail-content">
                <strong>${escapeHtml(d.nm_pasien || '-')}</strong> ${d.jk ? '('+escapeHtml(d.jk)+')' : ''}<br>
                No. RM: ${escapeHtml(d.no_rkm_medis || '-')} &nbsp;|&nbsp; No. Rawat: ${escapeHtml(d.no_rawat || '-')}
            </div>
        </div>

        <div class="detail-section">
            <h4><i class="material-icons" style="color:#10b981">badge</i> Perawat Pengirim</h4>
            <div class="detail-content">
                <strong>${escapeHtml(d.nm_perawat || '-')}</strong> &nbsp;<span style="color:#94a3b8;">(NIK: ${escapeHtml(d.nip || '-')})</span>
                ${d.jbtn_perawat ? '<br><span style="font-size:11px;color:#64748b;">Jabatan: '+escapeHtml(d.jbtn_perawat)+'</span>' : ''}
            </div>
        </div>

        <div class="detail-section">
            <h4><i class="material-icons" style="color:#7c3aed">format_list_bulleted</i> Isi SBAR</h4>
            <div class="sbar-grid">
                <div class="sbar-box sbar-s">
                    <div class="label">S - Situation</div>
                    ${escapeHtml(d.situation || '-')}
                </div>
                <div class="sbar-box sbar-b">
                    <div class="label">B - Background</div>
                    ${escapeHtml(d.background || '-')}
                </div>
                <div class="sbar-box sbar-a">
                    <div class="label">A - Assessment</div>
                    ${escapeHtml(d.assessment || '-')}
                </div>
                <div class="sbar-box sbar-r">
                    <div class="label">R - Recommendation</div>
                    ${escapeHtml(d.recomendation || '-')}
                </div>
            </div>
        </div>

        <div class="jawaban-form">
            <h4 style="font-size:13px; font-weight:600; color:#1e293b; margin:0 0 15px 0;">
                <i class="material-icons" style="font-size:18px; vertical-align:middle; margin-right:6px; color:#7c3aed">reply</i>
                ${j ? 'Edit Jawaban' : 'Tulis Jawaban'}
            </h4>
            ${jawabanExisting}
            <label>Respon Singkat <span style="color:#94a3b8;font-weight:normal;">(maks 80 karakter, opsional)</span></label>
            <input type="text" id="jawabanRespon" maxlength="80" placeholder="Contoh: ACC, Lanjut, Dalam pemantauan..." value="${escapeHtml(j ? (j.respon || '') : '')}">

            <label>Instruksi <span style="color:#dc2626;">*</span></label>
            <textarea id="jawabanInstruksi" placeholder="Tulis instruksi tindakan untuk perawat...">${escapeHtml(j ? (j.instruksi || '') : '')}</textarea>

            <label>Rencana</label>
            <textarea id="jawabanRencana" placeholder="Tulis rencana tindak lanjut...">${escapeHtml(j ? (j.rencana || '') : '')}</textarea>

            <div class="form-actions">
                <button class="btn btn-primary" onclick="simpanJawabanKonsulPerawat('${d.no_permintaan}')">
                    <i class="material-icons">save</i> Simpan Jawaban
                </button>
                ${j ? `
                <button class="btn btn-danger" onclick="hapusJawabanKonsulPerawat('${d.no_permintaan}')">
                    <i class="material-icons">delete</i> Hapus
                </button>` : ''}
            </div>
        </div>
    `;
}

function simpanJawabanKonsulPerawat(noPermintaan) {
    const respon    = document.getElementById('jawabanRespon').value.trim();
    const instruksi = document.getElementById('jawabanInstruksi').value.trim();
    const rencana   = document.getElementById('jawabanRencana').value.trim();

    if (!instruksi && !rencana) {
        Swal.fire({ icon: 'warning', title: 'Perhatian', text: 'Minimal Instruksi atau Rencana harus diisi!' });
        return;
    }

    Swal.fire({
        title: 'Konfirmasi',
        text: 'Apakah Anda yakin ingin menyimpan jawaban konsultasi ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Simpan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#7c3aed'
    }).then(result => {
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('aksi', 'simpan_jawaban_konsul_perawat');
        fd.append('no_permintaan', noPermintaan);
        fd.append('respon', respon);
        fd.append('instruksi', instruksi);
        fd.append('rencana', rencana);

        fetch(KONSUL_PERAWAT_CONFIG.apiUrl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: resp.message, timer: 1500, showConfirmButton: false })
                        .then(() => {
                            window.location.reload();
                        });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: resp.message });
                }
            })
            .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message }));
    });
}

function hapusJawabanKonsulPerawat(noPermintaan) {
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: 'Apakah Anda yakin ingin menghapus jawaban ini?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#ef4444'
    }).then(result => {
        if (!result.isConfirmed) return;

        const fd = new FormData();
        fd.append('aksi', 'hapus_jawaban_konsul_perawat');
        fd.append('no_permintaan', noPermintaan);

        fetch(KONSUL_PERAWAT_CONFIG.apiUrl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(resp => {
                if (resp.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: resp.message, timer: 1500, showConfirmButton: false })
                        .then(() => {
                            window.location.reload();
                        });
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal', text: resp.message });
                }
            })
            .catch(err => Swal.fire({ icon: 'error', title: 'Error', text: err.message }));
    });
}

function truncate(t, max) {
    if (!t) return '';
    return t.length <= max ? t : t.substring(0, max) + '...';
}

function escapeHtml(t) {
    if (t === null || t === undefined) return '';
    return String(t)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
</script>
