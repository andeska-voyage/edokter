<?php
// Output buffering
ob_start();

// Cek session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../../conf/conf.php');

// Bersihkan buffer
ob_end_clean();

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo '<div class="alert alert-danger">Session expired. Silakan login kembali.</div>';
    exit();
}

// Ambil parameter - konsep sama seperti riwayat_penilaianmedisigd.php
$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';

if(empty($no_rawat)) {
    echo '<div class="alert alert-warning">Parameter tidak lengkap.</div>';
    exit();
}
?>

<div class="riwayat-obatbhp-container">
    <!-- Header -->


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

.modern-table thead th.has-border {
    border-bottom: 2px solid rgba(255,255,255,0.2);
}

.modern-table tbody td {
    padding: 8px 12px;
    vertical-align: top;
    font-size: 13px;
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

.modern-table tbody tr:hover td.td-bordered {
    background: #f8f9ff;
}

.modern-table tbody tr.racikan-row {
    background: #fffbeb !important;
}

.modern-table tbody tr.racikan-row:hover td.td-bordered {
    background: #fef3c7 !important;
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
    border: 1px solid #a5d8ff;
}

.badge-racikan {
    background: #fff4e6;
    color: #e67700;
    border: 1px solid #ffd8a8;
}

.badge-bhp-warning {
    background: #fff5f5;
    color: #c92a2a;
    border: 1px solid #ffc9c9;
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

@media (max-width: 768px) {
    .modern-table thead th,
    .modern-table tbody td {
        padding: 8px 10px;
        font-size: 12px;
    }
}
</style>

<script>
window.currentNoRawat = '<?php echo addslashes($no_rawat); ?>';

$(document).ready(function() {

    function loadData() {
        const no_rawat = window.currentNoRawat;
        if (!no_rawat) return;

        $('#table-obatbhp-container').html(
            '<div class="text-center" style="padding:40px;">' +
            '<i class="material-icons spin" style="font-size:48px;color:#667eea;">autorenew</i>' +
            '<p style="margin-top:15px;color:#6c757d;">Memuat data...</p></div>'
        );

        $.ajax({
            url: 'pages/get_riwayat_obatbhp_riwayat.php',
            type: 'GET',
            data: { action: 'getriwayatobatbhp', no_rawat: no_rawat },
            dataType: 'text',
            timeout: 30000
        })
        .done(function(responseText) {
            let r;
            try {
                r = JSON.parse(responseText);
            } catch(e) {
                $('#table-obatbhp-container').html(
                    '<div class="alert alert-danger" style="margin:20px;">' +
                    '<strong>JSON Parse Error:</strong><br>' + e.message +
                    '</div>'
                );
                return;
            }

            if (r.status === 'success') {
                renderTable(r.data);
            } else {
                $('#table-obatbhp-container').html(
                    '<div class="alert alert-danger" style="margin:20px;">' + (r.message || 'Error') + '</div>'
                );
            }
        })
        .fail(function(x, s, e) {
            $('#table-obatbhp-container').html(
                '<div class="alert alert-danger" style="margin:20px;">Error: ' + e + '</div>'
            );
        });
    }

    function renderTable(data) {
        if (!data || !data.length) {
            $('#table-obatbhp-container').html(
                '<div class="alert alert-info" style="margin:20px;">Belum ada data obat/BHP untuk kunjungan ini.</div>'
            );
            return;
        }

        let html = '<div class="modern-table-wrapper">';
        html += '<table class="modern-table">';
        html += '<thead><tr>';
        html += '<th class="has-border" style="width:110px;">TANGGAL/JAM</th>';
        html += '<th class="has-border">NAMA OBAT/BHP</th>';
        html += '<th class="has-border" style="width:60px;text-align:center;">JML</th>';
        html += '</tr></thead><tbody>';

        data.forEach(function(item) {
            const isRacikan = item.tipe === 'racikan';
            const rowClass = isRacikan ? 'racikan-row' : '';

            html += '<tr class="' + rowClass + '">';

            // Kolom TANGGAL/JAM
            html += '<td class="td-bordered">';
            html += '<div style="font-size:11px;color:#667eea;font-weight:600;margin-bottom:2px;">' + formatDate(item.tgl_perawatan) + '</div>';
            html += '<strong style="font-family:monospace;color:#1f2937;">' + item.jam + '</strong>';
            html += '</td>';

            // Kolom NAMA OBAT/BHP
            html += '<td class="td-bordered">';
            if (isRacikan) {
                html += '<div style="margin-bottom:4px;">';
                html += '<span class="obat-badge badge-racikan">RACIKAN</span> ';
                html += '<strong style="color:#d97706;">' + item.nama_racik + '</strong>';
                if (item.aturan_pakai) html += ' <span style="font-size:12px;color:#6c757d;font-style:italic;">(' + item.aturan_pakai + ')</span>';
                html += '</div>';
                if (item.keterangan) html += '<div style="font-size:11px;color:#999;margin-top:2px;">' + item.keterangan + '</div>';
                if (item.komposisi && item.komposisi.length) {
                    html += '<div class="komposisi-box"><div class="komposisi-title">Komposisi:</div>';
                    item.komposisi.forEach(function(k) {
                        html += '<div class="komposisi-item">• ' + k.nama_brng + ' <strong style="color:#667eea;">(' + (k.jml || k.jml_dr || '-') + ')</strong></div>';
                    });
                    html += '</div>';
                }
            } else {
                var aturanPakai = (item.aturan_pakai || '').trim();
                var isNoAturanPakai = !aturanPakai || aturanPakai === '' || aturanPakai === '-' || aturanPakai === '(-)';

                html += '<div style="margin-bottom:2px;">';
                if (isNoAturanPakai) {
                    html += '<span class="obat-badge badge-bhp-warning">OBAT/BHP</span> ';
                } else {
                    html += '<span class="obat-badge badge-obat">OBAT</span> ';
                }
                html += '<strong style="color:#1864ab;">' + item.nama_brng + '</strong>';
                if (item.aturan_pakai && !isNoAturanPakai) {
                    html += ' <span style="font-size:12px;color:#6c757d;font-style:italic;">(' + item.aturan_pakai + ')</span>';
                }
                html += '</div>';
            }
            html += '</td>';

            // Kolom JML
            html += '<td class="td-bordered" style="text-align:center;">';
            html += '<strong style="color:' + (isRacikan ? '#d97706' : '#1864ab') + ';font-size:14px;">' + (isRacikan ? item.jml_dr : item.jml) + '</strong>';
            html += '</td>';

            html += '</tr>';
        });

        html += '</tbody></table></div>';
        $('#table-obatbhp-container').html(html);
    }

    function formatDate(dateStr) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        const parts = dateStr.split('-');
        return parts.length === 3 ? parts[2] + ' ' + months[parseInt(parts[1])-1] + ' ' + parts[0] : dateStr;
    }

    loadData();
});
</script>