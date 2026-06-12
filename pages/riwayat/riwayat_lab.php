<?php
session_start();
require_once('../../conf/conf.php');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo "<div class='alert alert-danger'>Session expired</div>";
    exit();
}

// Ambil parameter NO RAWAT
$no_rawat = isset($_REQUEST['id']) ? validTeks4($_REQUEST['id'], 20) : '';

if(empty($no_rawat)){
    echo "<div class='alert alert-warning'>Parameter tidak lengkap.</div>";
    exit();
}

// CEK APAKAH ADA DATA PA
$qCheckPA = bukaquery("
    SELECT COUNT(*) as jml
    FROM periksa_lab
    WHERE no_rawat = '$no_rawat'
    AND kategori = 'PA'
");
$rowPA = mysqli_fetch_assoc($qCheckPA);
$hasDataPA = ($rowPA['jml'] > 0);

// CEK APAKAH ADA DATA PK/MB
$qCheckPKMB = bukaquery("
    SELECT COUNT(*) as jml
    FROM periksa_lab
    WHERE no_rawat = '$no_rawat'
    AND kategori IN ('PK', 'MB')
");
$rowPKMB = mysqli_fetch_assoc($qCheckPKMB);
$hasDataPKMB = ($rowPKMB['jml'] > 0);

?>
<style>
:root {
    --primary: #667eea;
    --primary-dark: #5568d3;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --light-bg: #f8fafc;
    --card-bg: #ffffff;
    --border: #e2e8f0;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
}

.lab-container {
    background: var(--light-bg);
    padding: 0;
    margin: 0;
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
}

.lab-container::-webkit-scrollbar {
    width: 7px;
}
.lab-container::-webkit-scrollbar-track {
    background: #f1f3f5;
    border-radius: 4px;
}
.lab-container::-webkit-scrollbar-thumb {
    background: #667eea;
    border-radius: 4px;
}
.lab-container::-webkit-scrollbar-thumb:hover {
    background: #5568d3;
}

.lab-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
    margin-bottom: 24px;
    overflow: hidden;
    border: 1px solid var(--border);
}

.lab-card-header {
    background: linear-gradient(135deg, #5FD38D 0%, #0F6FB2 100%);
    color: white;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.lab-card-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}

.lab-card-header-pa {
    background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table thead {
    background: #f1f5f9;
}

.modern-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    color: var(--text-primary);
    border-bottom: 2px solid var(--border);
}

.modern-table tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

.modern-table tbody tr:hover { background: #f8fafc; }
.modern-table tbody tr:last-child { border-bottom: none; }

.modern-table td {
    padding: 10px 16px;
    font-size: 13px;
    color: var(--text-primary);
    vertical-align: top;
}

.pemeriksaan-row {
    border-top: 3px solid var(--primary) !important;
    background: #fafbfc;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-ralan { background: #dcfce7; color: #166534; }
.badge-ranap { background: #fee2e2; color: #991b1b; }

.badge-pk, .badge-pa, .badge-mb {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.badge-pk { background: #dbeafe; color: #1e40af; }
.badge-pa { background: #fce7f3; color: #9f1239; }
.badge-mb { background: #fef3c7; color: #92400e; }

.detail-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.detail-table thead {
    background: linear-gradient(135deg, #84cc16 0%, #748d07 100%);
}

.detail-table th {
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
    font-size: 12px;
    color: white;
    border: none;
}

.detail-table tbody tr { border-bottom: 1px solid #e2e8f0; transition: all 0.2s; }
.detail-table tbody tr:last-child { border-bottom: none; }
.detail-table td { padding: 10px 14px; font-size: 12px; }

.row-kritis-tinggi { background: #fee2e2 !important; }
.row-kritis-rendah { background: #fef3c7 !important; }
.row-nilai-kosong  { background: #f3f4f6 !important; }

.badge-belum-ada {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 14px;
    background: #e0e7ff;
    color: #4338ca;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.pemeriksaan-title { font-weight: 600; font-size: 13px; color: var(--text-primary); }
.pemeriksaan-subtitle { font-size: 11px; color: var(--text-secondary); margin-top: 2px; }

/* Two Column Layout */
.lab-twocol-wrapper {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--border);
}
.lab-twocol-wrapper:last-child { border-bottom: none; }

.lab-twocol-left {
    flex: 0 0 260px;
    max-width: 260px;
    border-right: 2px solid var(--border);
    background: #fafbfc;
    padding: 16px;
}

.lab-twocol-wrapper:nth-child(odd) .lab-twocol-left  { border-left: 4px solid var(--primary); }
.lab-twocol-wrapper:nth-child(even) .lab-twocol-left { border-left: 4px solid #a78bfa; }
.lab-twocol-wrapper:nth-child(odd)  { background: #ffffff; }
.lab-twocol-wrapper:nth-child(even) { background: #f8fafc; }

.lab-twocol-left .info-row    { margin-bottom: 8px; }
.lab-twocol-left .info-label  { font-size: 10px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
.lab-twocol-left .info-value  { font-size: 13px; font-weight: 600; color: var(--text-primary); word-break: break-word; }
.lab-twocol-left .info-value-sm { font-size: 12px; font-weight: 500; color: var(--text-primary); word-break: break-word; }

.lab-twocol-right { flex: 1; min-width: 0; overflow-x: auto; }
.lab-twocol-right .detail-table { border-radius: 0; }

.btn-copy-hasil {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 12px;
    padding: 7px 14px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-copy-hasil:hover { opacity: 0.85; transform: translateY(-1px); }
.btn-copy-hasil.copied { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

@media (max-width: 768px) {
    .lab-twocol-wrapper { flex-direction: column; }
    .lab-twocol-left { flex: none; max-width: 100%; border-right: none; border-bottom: 2px solid var(--border); }
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: var(--shadow-sm);
}
.empty-state i    { font-size: 64px; color: var(--text-secondary); opacity: 0.3; }
.empty-state h4   { margin: 16px 0 8px 0; color: var(--text-primary); font-size: 18px; }
.empty-state p    { color: var(--text-secondary); font-size: 14px; margin: 0; }

.pa-detail-card  { background: #fdf2f8; border-radius: 8px; padding: 16px; margin-top: 8px; }
.pa-detail-row   { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #f9a8d4; }
.pa-detail-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
.pa-detail-label { font-size: 11px; font-weight: 600; color: #9f1239; text-transform: uppercase; margin-bottom: 4px; }
.pa-detail-value { font-size: 13px; color: var(--text-primary); white-space: pre-wrap; line-height: 1.5; }

.pa-gambar-container { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px; }
.pa-gambar-item {
    width: 120px; height: 120px;
    border-radius: 8px; overflow: hidden;
    border: 2px solid #f9a8d4;
    background: #fff;
    display: flex; align-items: center; justify-content: center;
}
.pa-gambar-item img { max-width: 100%; max-height: 100%; object-fit: cover; cursor: pointer; }
.pa-gambar-placeholder { color: #9f1239; font-size: 11px; text-align: center; padding: 10px; }
</style>

<div class="lab-container">
<?php
if(!$hasDataPKMB && !$hasDataPA) {
    echo "<div class='empty-state'>";
    echo "<i class='material-icons'>science</i>";
    echo "<h4>Belum Ada Data Laboratorium</h4>";
    echo "<p>Kunjungan ini belum memiliki riwayat pemeriksaan laboratorium</p>";
    echo "</div>";
} else {

    // ==========================================
    // SECTION PK & MB
    // ==========================================
    if($hasDataPKMB) {

        echo "<div class='lab-card'>";
        echo "<div class='lab-card-header'>";
        echo "<h4><i class='material-icons'>assignment</i>Pemeriksaan Laboratorium PK &amp; MB</h4>";
        echo "<span style='font-size:14px;'><i class='material-icons' style='font-size:16px;vertical-align:middle;'>badge</i> No. Rawat: <strong>" . htmlspecialchars($no_rawat) . "</strong></span>";
        echo "</div>";

        $query_detail = bukaquery("
            SELECT
                pl.kd_jenis_prw,
                pl.tgl_periksa,
                pl.jam,
                pl.status,
                pl.kategori,
                pl.dokter_perujuk,
                pl.nip,
                jl.nm_perawatan,
                jl.bagian_rs,
                d.nm_dokter,
                p.nama AS nama_petugas
            FROM periksa_lab pl
            LEFT JOIN jns_perawatan_lab jl ON pl.kd_jenis_prw = jl.kd_jenis_prw
            LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
            LEFT JOIN petugas p ON pl.nip = p.nip
            WHERE pl.no_rawat = '$no_rawat'
            AND pl.kategori IN ('PK', 'MB')
            GROUP BY pl.kd_jenis_prw, pl.tgl_periksa, pl.jam
            ORDER BY pl.tgl_periksa DESC, pl.jam DESC
        ");

        $no_pemeriksaan = 0;

        while($detail = mysqli_fetch_array($query_detail)) {
            $no_pemeriksaan++;
            $kd_jenis_prw = $detail['kd_jenis_prw'];
            $tgl_periksa  = $detail['tgl_periksa'];
            $jam          = $detail['jam'];

            $status_class = ($detail['status'] == 'Ralan') ? 'badge-ralan' : 'badge-ranap';
            $status_text  = strtoupper($detail['status']);
            $kategori     = strtoupper($detail['kategori']);
            $badge_class  = ($kategori == 'MB') ? 'badge-mb' : 'badge-pk';

            $wrapper_id = 'labwrap_' . $no_pemeriksaan . '_' . md5($no_rawat . $kd_jenis_prw . $tgl_periksa . $jam);
            echo "<div class='lab-twocol-wrapper' id='$wrapper_id'>";

            // Kolom Kiri
            echo "<div class='lab-twocol-left'>";
            echo "<div class='info-row'><div class='info-label'>Tanggal</div>";
            echo "<div class='info-value'>" . konversiTanggal($tgl_periksa) . " <span style='font-weight:400;color:var(--text-secondary);'>" . substr($jam, 0, 5) . "</span></div></div>";
            echo "<div class='info-row'><span class='status-badge $status_class'>$status_text</span> <span class='$badge_class' style='margin-left:4px;'>$kategori</span></div>";
            echo "<div class='info-row'><div class='info-label'>Kode</div><div class='info-value'>" . htmlspecialchars($kd_jenis_prw) . "</div></div>";
            echo "<div class='info-row'><div class='info-label'>Nama Pemeriksaan</div><div class='info-value'>" . htmlspecialchars($detail['nm_perawatan']) . "</div></div>";
            echo "<div class='info-row'><div class='info-label'>Dokter PJ</div><div class='info-value-sm'>" . htmlspecialchars($detail['nm_dokter']) . "</div></div>";
            echo "<div class='info-row'><div class='info-label'>Petugas</div><div class='info-value-sm'>" . htmlspecialchars($detail['nama_petugas']) . "</div></div>";
            $copy_header = htmlspecialchars($detail['nm_perawatan']) . ' , ' . konversiTanggal($tgl_periksa) . ' ' . substr($jam, 0, 5);
            echo "<button class='btn-copy-hasil' onclick=\"copyHasilLabRiwayat('$wrapper_id', '" . addslashes($copy_header) . "')\" title='Copy hasil'>";
            echo "<i class='material-icons' style='font-size:16px;'>content_copy</i> Copy Hasil</button>";
            echo "</div>"; // end lab-twocol-left

            // Kolom Kanan
            echo "<div class='lab-twocol-right'>";
            $query_hasil = bukaquery("
                SELECT
                    dpl.id_template,
                    dpl.nilai,
                    dpl.nilai_rujukan,
                    dpl.keterangan,
                    tl.Pemeriksaan
                FROM detail_periksa_lab dpl
                LEFT JOIN template_laboratorium tl ON dpl.id_template = tl.id_template
                WHERE dpl.no_rawat = '$no_rawat'
                AND dpl.kd_jenis_prw = '$kd_jenis_prw'
                AND dpl.tgl_periksa = '$tgl_periksa'
                AND dpl.jam = '$jam'
                ORDER BY dpl.id_template ASC
            ");

            if(mysqli_num_rows($query_hasil) > 0) {
                echo "<table class='detail-table'><thead><tr>";
                echo "<th width='35%'>Item</th>";
                echo "<th width='25%'>Hasil</th>";
                echo "<th width='25%'>Nilai Rujukan</th>";
                echo "<th width='15%'>Keterangan</th>";
                echo "</tr></thead><tbody>";

                while($hasil = mysqli_fetch_array($query_hasil)) {
                    $nilai     = trim($hasil['nilai']);
                    $ket       = trim($hasil['keterangan']);
                    $ket_upper = strtoupper($ket);
                    $row_class = '';
                    $is_empty  = false;

                    if(empty($nilai) || $nilai == '-') {
                        $row_class = 'row-nilai-kosong'; $is_empty = true;
                    } elseif($ket_upper == 'L') {
                        $row_class = 'row-kritis-rendah';
                    } elseif($ket_upper == 'H') {
                        $row_class = 'row-kritis-tinggi';
                    }

                    echo "<tr class='$row_class'>";
                    echo "<td>" . htmlspecialchars($hasil['Pemeriksaan']) . "</td>";
                    if($is_empty) {
                        echo "<td colspan='3' style='text-align:center;'><span class='badge-belum-ada'><i class='material-icons' style='font-size:14px;'>schedule</i>Belum Ada Hasil</span></td>";
                    } else {
                        echo "<td><strong>" . htmlspecialchars($hasil['nilai']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($hasil['nilai_rujukan']) . "</td>";
                        echo "<td align='center'><strong>" . htmlspecialchars($ket) . "</strong></td>";
                    }
                    echo "</tr>";
                }

                echo "</tbody></table>";
            } else {
                echo "<div style='text-align:center;padding:20px;'><span class='badge-belum-ada'><i class='material-icons' style='font-size:14px;'>schedule</i>Belum Ada Hasil</span></div>";
            }

            echo "</div>"; // end lab-twocol-right
            echo "</div>"; // end lab-twocol-wrapper
        } // end while detail

        echo "</div>"; // end lab-card
    } // end if hasDataPKMB

    // ==========================================
    // SECTION PATOLOGI ANATOMI (PA)
    // ==========================================
    if($hasDataPA) {

        echo "<div class='lab-card'>";
        echo "<div class='lab-card-header lab-card-header-pa'>";
        echo "<h4><i class='material-icons'>biotech</i>Pemeriksaan Laboratorium Patologi Anatomi (PA)</h4>";
        echo "<span style='font-size:14px;'><i class='material-icons' style='font-size:16px;vertical-align:middle;'>badge</i> No. Rawat: <strong>" . htmlspecialchars($no_rawat) . "</strong></span>";
        echo "</div>";

        echo "<table class='modern-table'><thead><tr>";
        echo "<th width='50'>NO.</th>";
        echo "<th width='150'>TANGGAL</th>";
        echo "<th width='100'>KODE</th>";
        echo "<th>NAMA PEMERIKSAAN</th>";
        echo "<th width='180'>DOKTER PJ</th>";
        echo "<th width='150'>PETUGAS</th>";
        echo "</tr></thead><tbody>";

        $query_pa = bukaquery("
            SELECT
                pl.kd_jenis_prw,
                pl.tgl_periksa,
                pl.jam,
                pl.status,
                pl.kategori,
                pl.dokter_perujuk,
                pl.nip,
                jl.nm_perawatan,
                d.nm_dokter,
                p.nama AS nama_petugas
            FROM periksa_lab pl
            LEFT JOIN jns_perawatan_lab jl ON pl.kd_jenis_prw = jl.kd_jenis_prw
            LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
            LEFT JOIN petugas p ON pl.nip = p.nip
            WHERE pl.no_rawat = '$no_rawat'
            AND pl.kategori = 'PA'
            GROUP BY pl.kd_jenis_prw, pl.tgl_periksa, pl.jam
            ORDER BY pl.tgl_periksa DESC, pl.jam DESC
        ");

        $no_pa = 0;

        while($pa = mysqli_fetch_array($query_pa)) {
            $no_pa++;
            $kd_jenis_prw = $pa['kd_jenis_prw'];
            $tgl_periksa  = $pa['tgl_periksa'];
            $jam          = $pa['jam'];

            $status_class    = ($pa['status'] == 'Ralan') ? 'badge-ralan' : 'badge-ranap';
            $status_text     = strtoupper($pa['status']);
            $separator_class = ($no_pa > 1) ? 'pemeriksaan-row' : '';

            echo "<tr class='$separator_class'>";
            echo "<td align='center' style='font-size:18px;font-weight:700;color:#ec4899;'>$no_pa</td>";
            echo "<td style='padding:8px 16px;'>";
            echo "<div style='font-weight:600;font-size:13px;'>" . konversiTanggal($tgl_periksa) . "</div>";
            echo "<div style='font-size:12px;color:var(--text-secondary);margin-top:1px;'>" . substr($jam, 0, 5) . "</div>";
            echo "<div style='margin-top:4px;'><span class='status-badge $status_class'>$status_text</span></div>";
            echo "</td>";
            echo "<td style='padding:8px 16px;'><strong style='font-size:13px;'>" . htmlspecialchars($kd_jenis_prw) . "</strong></td>";
            echo "<td style='padding:8px 16px;'>";
            echo "<div class='pemeriksaan-title'>" . htmlspecialchars($pa['nm_perawatan']) . "</div>";
            echo "<div class='pemeriksaan-subtitle'>Detail Pemeriksaan</div>";
            echo "<div style='margin-top:4px;'><span class='badge-pa'>PA</span></div>";
            echo "</td>";
            echo "<td style='padding:8px 16px;'>" . htmlspecialchars($pa['nm_dokter']) . "</td>";
            echo "<td style='padding:8px 16px;'>" . htmlspecialchars($pa['nama_petugas']) . "</td>";
            echo "</tr>";

            // Query detail PA
            $query_detail_pa = bukaquery("
                SELECT diagnosa_klinik, makroskopik, mikroskopik, kesimpulan, kesan
                FROM detail_periksa_labpa
                WHERE no_rawat = '$no_rawat'
                AND kd_jenis_prw = '$kd_jenis_prw'
                AND tgl_periksa = '$tgl_periksa'
                AND jam = '$jam'
                LIMIT 1
            ");
            $detail_pa = mysqli_fetch_array($query_detail_pa);

            // Query gambar PA
            $query_gambar_pa = bukaquery("
                SELECT photo FROM detail_periksa_labpa_gambar
                WHERE no_rawat = '$no_rawat'
                AND kd_jenis_prw = '$kd_jenis_prw'
                AND tgl_periksa = '$tgl_periksa'
                AND jam = '$jam'
            ");

            echo "<tr class='detail-row-container'><td colspan='6' style='padding:8px 16px;background:inherit;'>";

            if($detail_pa) {
                echo "<div class='pa-detail-card'>";
                $pa_fields = [
                    'diagnosa_klinik' => 'Diagnosa Klinik',
                    'makroskopik'     => 'Makroskopik',
                    'mikroskopik'     => 'Mikroskopik',
                    'kesimpulan'      => 'Kesimpulan',
                    'kesan'           => 'Kesan'
                ];
                foreach($pa_fields as $field => $label) {
                    if(!empty($detail_pa[$field])) {
                        echo "<div class='pa-detail-row'>";
                        echo "<div class='pa-detail-label'>$label</div>";
                        echo "<div class='pa-detail-value'>" . nl2br(htmlspecialchars($detail_pa[$field])) . "</div>";
                        echo "</div>";
                    }
                }
                // Gambar PA
                if(mysqli_num_rows($query_gambar_pa) > 0) {
                    echo "<div class='pa-detail-row'><div class='pa-detail-label'>Gambar</div>";
                    echo "<div class='pa-gambar-container'>";
                    while($gambar = mysqli_fetch_array($query_gambar_pa)) {
                        echo "<div class='pa-gambar-item'><div class='pa-gambar-placeholder'>";
                        echo "<i class='material-icons' style='font-size:32px;'>image</i><br>" . htmlspecialchars($gambar['photo']);
                        echo "</div></div>";
                    }
                    echo "</div></div>";
                }
                echo "</div>";
            } else {
                echo "<div style='text-align:center;padding:20px;'><span class='badge-belum-ada'><i class='material-icons' style='font-size:14px;'>schedule</i>Belum Ada Hasil PA</span></div>";
            }

            echo "</td></tr>";
        } // end while pa

        echo "</tbody></table>";
        echo "</div>"; // end lab-card PA
    } // end if hasDataPA

} // end else
?>
</div>

<script>
(function(){
    window.copyHasilLabRiwayat = function(wrapperId, header) {
        var wrapper = document.getElementById(wrapperId);
        if(!wrapper) return;

        var table = wrapper.querySelector('.lab-twocol-right .detail-table');
        if(!table) {
            alert('Tidak ada data hasil untuk dicopy');
            return;
        }

        var lines = [];
        lines.push(header);
        lines.push('             ');

        var rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(row) {
            var cells = row.querySelectorAll('td');
            if(cells.length >= 2) {
                var item = cells[0].textContent.trim();
                var belumBadge = row.querySelector('.badge-belum-ada');
                if(belumBadge) {
                    lines.push(item + ': belum ada hasil');
                } else {
                    var nilai = cells[1].textContent.trim();
                    var keterangan = cells.length >= 4 ? cells[3].textContent.trim() : '';
                    var flag = '';
                    if(keterangan === 'H') flag = ' \u2B06\uFE0F';
                    else if(keterangan === 'L') flag = ' \u2B07\uFE0F';
                    lines.push(item + ': ' + nilai + flag);
                }
            }
        });

        var text = lines.join('\n');

        if(navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopiedFeedbackRiwayat(wrapperId);
            }).catch(function() {
                fallbackCopyRiwayat(text, wrapperId);
            });
        } else {
            fallbackCopyRiwayat(text, wrapperId);
        }
    };

    function fallbackCopyRiwayat(text, wrapperId) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            showCopiedFeedbackRiwayat(wrapperId);
        } catch(e) {
            alert('Gagal copy. Silakan copy manual.');
        }
        document.body.removeChild(ta);
    }

    function showCopiedFeedbackRiwayat(wrapperId) {
        var btn = document.querySelector('#' + wrapperId + ' .btn-copy-hasil');
        if(btn) {
            var origHTML = btn.innerHTML;
            btn.innerHTML = "<i class='material-icons' style='font-size:16px;'>check</i> Tersalin!";
            btn.classList.add('copied');
            setTimeout(function() {
                btn.innerHTML = origHTML;
                btn.classList.remove('copied');
            }, 2000);
        }
    }

    console.log('\u2705 riwayat/riwayat_lab.php loaded');
})();
</script>
