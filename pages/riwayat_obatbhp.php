<?php
// Output buffering
ob_start();

// Cek session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../conf/conf.php');

// Bersihkan buffer
ob_end_clean();

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo '<div class="alert alert-danger">Session expired. Silakan login kembali.</div>';
    exit();
}

// Ambil parameter
$no_rkm_medis = isset($_GET['norm']) ? $_GET['norm'] : '';

if(empty($no_rkm_medis)) {
    echo '<div class="alert alert-warning">No. RM tidak ditemukan.</div>';
    exit();
}
?>

<div class="riwayat-obatbhp-container">
    <!-- Header -->
    <div class="riwayat-header" style="background: linear-gradient(135deg, #2198bd 0%, #51cc8e 100%); color: white; padding: 15px 20px; border-radius: 8px 8px 0 0; margin-bottom: 0;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="material-icons" style="font-size: 28px;">inventory_2</i>
                <h4 style="margin: 0; font-weight: 600;">Riwayat Obat & BHP</h4>
            </div>
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label style="font-size: 13px; margin: 0;">Filter No. Rawat:</label>
                    <select id="filter-norawat" style="padding: 5px 10px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.3); background: rgba(255,255,255,0.2); color: white; font-size: 13px; min-width: 200px;">
                        <option value="">-- Semua No. Rawat --</option>
                    </select>
                </div>
                <span id="obatbhp-last-update" style="font-size: 12px; opacity: 0.9;">
                    <i class="material-icons" style="font-size: 14px; vertical-align: middle;">schedule</i>
                    Terakhir diupdate: -
                </span>
                <button class="btn btn-sm" id="btn-reload-obatbhp" style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white;">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">refresh</i>
                    <span style="vertical-align: middle;">Reload</span>
                </button>
            </div>
        </div>
    </div>

    <div id="table-obatbhp-container" style="background: white; border-radius: 0 0 8px 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <div class="text-center" style="padding: 40px;">
            <i class="material-icons spin" style="font-size: 48px; color: #667eea;">autorenew</i>
            <p style="margin-top: 15px; color: #6c757d;">Memuat data...</p>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.spin { animation: spin 1s linear infinite; }

.modern-table-wrapper {
    max-height: 800px;
    overflow-y: auto;
    overflow-x: auto;
}

.modern-table-wrapper::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
.modern-table-wrapper::-webkit-scrollbar-track {
    background: #f1f3f5;
    border-radius: 4px;
}
.modern-table-wrapper::-webkit-scrollbar-thumb {
    background: #667eea;
    border-radius: 4px;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

.modern-table thead {
    position: sticky;
    top: 0;
    z-index: 10;
}

.modern-table thead th {
    background: linear-gradient(135deg, #457bf0 100%);
    color: white;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 15px;
    text-align: left;
    border: none;
}

.modern-table thead th.no-border {
    border-right: none !important;
}

.modern-table thead th.has-border {
    border-bottom: 2px solid rgba(255,255,255,0.2);
}

.modern-table tbody tr {
    transition: all 0.2s ease;
}

.modern-table tbody td {
    padding: 8px 12px;
    vertical-align: top;
    font-size: 13px;
    border: none;
}

/* NO RAWAT & STATUS - NO BORDERS */
.modern-table tbody td.td-norawat,
.modern-table tbody td.td-status {
    border: none;
}

/* TANGGAL, JAM, NAMA OBAT, JML - WITH BORDERS */
.modern-table tbody td.td-bordered {
    border: 1px solid #dee2e6;
    border-right: none;
}

.modern-table tbody td.td-bordered:last-child {
    border-right: 1px solid #dee2e6;
}

/* Group separator - border tebal antar No Rawat - FULL dari NO RAWAT sampai JML */
.modern-table tbody tr.group-separator td {
    border-bottom: 3px solid #667eea !important;
}

.modern-table tbody tr:hover td.td-bordered {
    background: #f8f9ff;
}

.modern-table tbody tr.racikan-row {
    background: #fffbeb !important;
}

.modern-table tbody tr.racikan-row:hover td.td-bordered {
    background: #fef3c7 !important;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-ralan {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-ranap {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.norawat-label {
    font-weight: 600;
    color: #667eea;
    font-size: 13px;
    margin-bottom: 4px;
}

.pasien-label {
    font-size: 13px;
    color: #495057;
}

.obat-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.badge-obat {
    background: #e7f5ff;
    color: #1864ab;
}
    border: 1px solid #a5d8ff;

.badge-racikan {

.badge-bhp-warning {
    background: #fff5f5;
    color: #c92a2a;
    border: 1px solid #ffc9c9;
}
    background: #fff4e6;
    color: #e67700;
}

.komposisi-box {
    margin-top: 6px;
    padding: 8px;
    background: #f8f9fa;
    border-left: 3px solid #667eea;
    border-radius: 4px;
}

.komposisi-title {
    font-size: 11px;
    font-weight: 600;
    color: #667eea;
    margin-bottom: 4px;
}

.komposisi-item {
    font-size: 11px;
    color: #495057;
    margin-left: 10px;
}

#filter-norawat {
    cursor: pointer;
    transition: all 0.3s ease;
}
#filter-norawat:hover {
    background: rgba(255,255,255,0.3);
}
#filter-norawat option {
    background: #667eea;
    color: white;
    padding: 5px;
}

@media (max-width: 768px) {
    .modern-table thead th,
    .modern-table tbody td {
        padding: 8px 10px;
        font-size: 12px;
    }
}

/* Pagination Styles */
.btn-page:hover {
    background: #f8f9fa !important;
    border-color: #667eea !important;
}

.btn-page:active {
    transform: scale(0.95);
}

.pagination-container {
    border-radius: 0 0 8px 8px;
}

@media (max-width: 768px) {
    .pagination-container {
        flex-direction: column;
        gap: 10px;
    }
    
    .pagination-info {
        text-align: center;
    }
}
</style>

<script>
window.currentNorm = '<?php echo $no_rkm_medis; ?>';
window.allData = [];
window.currentPage = 1;
window.itemsPerPage = 5; // 5 No. Rawat per halaman

$(document).ready(function() {
    //console.log('[INIT] Modern table starting...');
    
    function loadData() {
        const norm = window.currentNorm;
        if (!norm) return;
        
        $('#table-obatbhp-container').html('<div class="text-center" style="padding:40px;"><i class="material-icons spin" style="font-size:48px;color:#667eea;">autorenew</i><p style="margin-top:15px;">Memuat data...</p></div>');
        
        $.ajax({
            url: 'pages/get_riwayat_obatbhp.php',
            type: 'GET',
            data: { action: 'getriwayatobatbhp', no_rkm_medis: norm },
            dataType: 'text', // Ubah jadi text dulu untuk debug
            timeout: 30000
        })
        .done(function(responseText) {
            //console.log('[RAW RESPONSE]:', responseText);
            
            let r;
            try {
                r = JSON.parse(responseText);
                //console.log('[PARSED JSON]:', r);
            } catch(e) {
                console.error('[JSON PARSE ERROR]:', e);
                console.error('[ERROR POSITION]:', e.message);
                $('#table-obatbhp-container').html(
                    '<div class="alert alert-danger" style="margin:20px;">' +
                    '<strong>JSON Parse Error:</strong><br>' + 
                    e.message + '<br><br>' +
                    '<strong>Response (first 500 chars):</strong><br>' +
                    '<pre style="background:#f8f9fa;padding:10px;border-radius:4px;max-height:200px;overflow:auto;">' + 
                    responseText.substring(0, 500).replace(/</g, '&lt;').replace(/>/g, '&gt;') + 
                    '</pre></div>'
                );
                return;
            }
            
            if (r.status === 'success') {
                window.allData = r.data;
                populateFilterDropdown(r.data);
                renderModernTable(r.data);
            } else {
                $('#table-obatbhp-container').html('<div class="alert alert-danger" style="margin:20px;">' + (r.message || 'Error') + '</div>');
            }
        })
        .fail(function(x, s, e) {
            $('#table-obatbhp-container').html('<div class="alert alert-danger" style="margin:20px;">Error: ' + e + '</div>');
        });
    }
    
    function populateFilterDropdown(data) {
        const noRawatSet = new Set();
        data.forEach(item => {
            if (item.no_rawat) noRawatSet.add(item.no_rawat);
        });
        
        const noRawatArray = Array.from(noRawatSet).sort().reverse();
        let options = '<option value="">-- Semua No. Rawat --</option>';
        noRawatArray.forEach(norawat => {
            options += '<option value="' + norawat + '">' + norawat + '</option>';
        });
        
        $('#filter-norawat').html(options);
    }
    
    function renderModernTable(data, page = 1) {
        if (!data || !data.length) {
            $('#table-obatbhp-container').html('<div class="alert alert-info" style="margin:20px;">Belum ada data</div>');
            return;
        }
        
        // Group by No. Rawat
        const groupedByNoRawat = {};
        data.forEach(item => {
            if (!groupedByNoRawat[item.no_rawat]) {
                groupedByNoRawat[item.no_rawat] = {
                    nm_pasien: item.nm_pasien,
                    status: item.status,
                    items: []
                };
            }
            groupedByNoRawat[item.no_rawat].items.push(item);
        });
        
        // Sort No Rawat descending
        const sortedNoRawat = Object.keys(groupedByNoRawat).sort().reverse();
        
        // PAGINATION
        const totalPages = Math.ceil(sortedNoRawat.length / window.itemsPerPage);
        const startIdx = (page - 1) * window.itemsPerPage;
        const endIdx = startIdx + window.itemsPerPage;
        const paginatedNoRawat = sortedNoRawat.slice(startIdx, endIdx);
        
        window.currentPage = page;
        
        let html = '<div class="modern-table-wrapper">';
        html += '<table class="modern-table">';
        html += '<thead><tr>';
        html += '<th class="no-border" style="width:180px;">PERAWATAN</th>';
        html += '<th class="has-border" style="width:110px;">TANGGAL/JAM</th>';
        html += '<th class="has-border">NAMA OBAT/BHP</th>';
        html += '<th class="has-border" style="width:60px;text-align:center;">JML</th>';
        html += '</tr></thead><tbody>';
        
        paginatedNoRawat.forEach((norawat, groupIdx) => {
            const group = groupedByNoRawat[norawat];
            const items = group.items;
            const rowCount = items.length;
            const bgColor = groupIdx % 2 === 0 ? '#ffffff' : '#fafbfc';
            const statusClass = (group.status || '').toLowerCase() === 'ralan' ? 'status-ralan' : 'status-ranap';
            
            items.forEach((item, idx) => {
                const isRacikan = item.tipe === 'racikan';
                const isLastRow = idx === rowCount - 1;
                const rowClass = isRacikan ? 'racikan-row' : '';
                const groupSeparatorClass = isLastRow && groupIdx < paginatedNoRawat.length - 1 ? 'group-separator' : '';
                
                html += '<tr class="' + rowClass + ' ' + groupSeparatorClass + '">';
                
                if (idx === 0) {
                    // Kolom gabungan: No Rawat + Pasien + Status + Tanggal
                    const rowspanStyle = groupIdx < paginatedNoRawat.length - 1 
                        ? 'border-bottom: 3px solid #667eea !important;' 
                        : '';
                    
                    html += '<td rowspan="' + rowCount + '" class="td-norawat" style="background:' + bgColor + ';' + rowspanStyle + '">';
                    html += '<div class="norawat-label">' + norawat + '</div>';
                    html += '<div class="pasien-label">' + (group.nm_pasien || '-') + '</div>';
                    html += '<div style="margin-top:6px;"><span class="status-badge ' + statusClass + '">' + (group.status || '-') + '</span></div>';
                    html += '</td>';
                }
                
                // Kolom TANGGAL/JAM - selalu tampilkan tanggal di setiap baris
                html += '<td class="td-bordered">';
                html += '<div style="font-size:11px;color:#667eea;font-weight:600;margin-bottom:2px;">' + formatDate(item.tgl_perawatan) + '</div>';
                html += '<strong style="font-family:monospace;color:#1f2937;">' + item.jam + '</strong></td>';
                html += '<td class="td-bordered">';
                
                if (isRacikan) {
                    html += '<div style="margin-bottom:4px;">';
                    html += '<span class="obat-badge badge-racikan" style="display:inline-block;padding:2px 5px;border-radius:3px;font-size:9px;font-weight:600;background:#fff4e6;color:#e67700;border:1px solid #ffd8a8;">RACIKAN</span> ';
                    html += '<strong style="color:#d97706;">' + item.nama_racik + '</strong>';
                    if (item.aturan_pakai) html += ' <span style="font-size:12px;color:#6c757d;font-style:italic;">(' + item.aturan_pakai + ')</span>';
                    html += '</div>';
                    if (item.keterangan) html += '<div style="font-size:11px;color:#999;margin-top:2px;">' + item.keterangan + '</div>';
                    if (item.komposisi && item.komposisi.length) {
                        html += '<div class="komposisi-box"><div class="komposisi-title">Komposisi:</div>';
                        item.komposisi.forEach(k => html += '<div class="komposisi-item">• ' + k.nama_brng + ' <strong style="color:#667eea;">(' + (k.jml || k.jml_dr || '-') + ')</strong></div>');
                        html += '</div>';
                    }
                } else {
                    html += '<div style="margin-bottom:2px;">';
                    
                    // Logika badge: Merah jika tidak ada aturan pakai atau aturan pakai = "-", Biru jika ada
                    var aturanPakai = (item.aturan_pakai || '').trim();
                    var isNoAturanPakai = !aturanPakai || aturanPakai === '' || aturanPakai === '-' || aturanPakai === '(-)';
                    
                    if (isNoAturanPakai) {
                        // Tidak ada aturan pakai atau aturan pakai = "-" - Badge MERAH (tanpa icon)
                        html += '<span class="obat-badge badge-bhp-warning" style="display:inline-block;padding:2px 5px;border-radius:3px;font-size:9px;font-weight:600;background:#fff5f5;color:#c92a2a;border:1px solid #ffc9c9;">OBAT/BHP</span> ';
                    } else {
                        // Ada aturan pakai - Badge BIRU (tanpa icon)
                        html += '<span class="obat-badge badge-obat" style="display:inline-block;padding:2px 5px;border-radius:3px;font-size:9px;font-weight:600;background:#e7f5ff;color:#1864ab;border:1px solid #a5d8ff;">OBAT</span> ';
                    }
                    
                    html += '<strong style="color:#1864ab;">' + item.nama_brng + '</strong>';
                    if (item.aturan_pakai && !isNoAturanPakai) html += ' <span style="font-size:12px;color:#6c757d;font-style:italic;">(' + item.aturan_pakai + ')</span>';
                    html += '</div>';
                }
                
                html += '</td>';
                html += '<td class="td-bordered" style="text-align:center;"><strong style="color:' + (isRacikan ? '#d97706' : '#1864ab') + ';font-size:14px;">' + (isRacikan ? item.jml_dr : item.jml) + '</strong></td>';
                html += '</tr>';
            });
        });
        
        html += '</tbody></table></div>';
        
        // PAGINATION CONTROLS
        if(totalPages > 1) {
            html += '<div class="pagination-container" style="display:flex;justify-content:space-between;align-items:center;padding:15px 20px;background:#f8f9fa;border-top:1px solid #dee2e6;">';
            
            // Info
            html += '<div class="pagination-info" style="font-size:13px;color:#6c757d;">';
            html += 'Menampilkan <strong>' + (startIdx + 1) + '-' + Math.min(endIdx, sortedNoRawat.length) + '</strong> dari <strong>' + sortedNoRawat.length + '</strong> No. Rawat';
            html += '</div>';
            
            // Buttons
            html += '<div class="pagination-buttons" style="display:flex;gap:5px;">';
            
            // First
            if(page > 1) {
                html += '<button class="btn-page" data-page="1" style="padding:6px 12px;border:1px solid #dee2e6;background:white;border-radius:4px;cursor:pointer;font-size:13px;">«</button>';
            }
            
            // Previous
            if(page > 1) {
                html += '<button class="btn-page" data-page="' + (page - 1) + '" style="padding:6px 12px;border:1px solid #dee2e6;background:white;border-radius:4px;cursor:pointer;font-size:13px;">‹</button>';
            }
            
            // Page numbers
            const maxButtons = 5;
            let startPage = Math.max(1, page - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            
            if(endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }
            
            for(let i = startPage; i <= endPage; i++) {
                const activeStyle = i === page 
                    ? 'background:#667eea;color:white;border-color:#667eea;font-weight:600;' 
                    : 'background:white;color:#495057;';
                html += '<button class="btn-page" data-page="' + i + '" style="padding:6px 12px;border:1px solid #dee2e6;border-radius:4px;cursor:pointer;font-size:13px;min-width:36px;' + activeStyle + '">' + i + '</button>';
            }
            
            // Next
            if(page < totalPages) {
                html += '<button class="btn-page" data-page="' + (page + 1) + '" style="padding:6px 12px;border:1px solid #dee2e6;background:white;border-radius:4px;cursor:pointer;font-size:13px;">›</button>';
            }
            
            // Last
            if(page < totalPages) {
                html += '<button class="btn-page" data-page="' + totalPages + '" style="padding:6px 12px;border:1px solid #dee2e6;background:white;border-radius:4px;cursor:pointer;font-size:13px;">»</button>';
            }
            
            html += '</div>';
            html += '</div>';
        }
        
        $('#table-obatbhp-container').html(html);
        $('#obatbhp-last-update').html('<i class="material-icons" style="font-size:14px;vertical-align:middle;">schedule</i> Terakhir diupdate: ' + new Date().toLocaleString('id-ID'));
        
        // Attach pagination click handlers
        $('.btn-page').on('click', function() {
            const targetPage = parseInt($(this).data('page'));
            renderModernTable(window.allData, targetPage);
            
            // Scroll to top
            $('.modern-table-wrapper').animate({ scrollTop: 0 }, 300);
        });
    }
    
    function formatDate(dateStr) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        const parts = dateStr.split('-');
        return parts.length === 3 ? parts[2] + ' ' + months[parseInt(parts[1])-1] + ' ' + parts[0] : dateStr;
    }
    
    $('#filter-norawat').on('change', function() {
        const selectedNoRawat = $(this).val();
        renderModernTable(selectedNoRawat ? window.allData.filter(item => item.no_rawat === selectedNoRawat) : window.allData);
    });
    
    $('#btn-reload-obatbhp').click(function() {
        $(this).find('.material-icons').addClass('spin');
        $('#filter-norawat').val('');
        loadData();
        setTimeout(() => $(this).find('.material-icons').removeClass('spin'), 1000);
    });
    
    loadData();
});
</script>