<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

// Get parameters
$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';
$page = isset($_REQUEST['page']) ? intval($_REQUEST['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

// Query untuk menghitung total data
$count_query = "
    SELECT COUNT(*) as total FROM (
        SELECT rjd.no_rawat FROM rawat_jl_dr rjd WHERE rjd.no_rawat = '$no_rawat'
        UNION ALL
        SELECT rjdp.no_rawat FROM rawat_jl_drpr rjdp WHERE rjdp.no_rawat = '$no_rawat'
        UNION ALL
        SELECT rjp.no_rawat FROM rawat_jl_pr rjp WHERE rjp.no_rawat = '$no_rawat'
        UNION ALL
        SELECT rid.no_rawat FROM rawat_inap_dr rid WHERE rid.no_rawat = '$no_rawat'
        UNION ALL
        SELECT ridp.no_rawat FROM rawat_inap_drpr ridp WHERE ridp.no_rawat = '$no_rawat'
        UNION ALL
        SELECT rip.no_rawat FROM rawat_inap_pr rip WHERE rip.no_rawat = '$no_rawat'
    ) as total_count
";
$count_result = bukaquery($count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_data = $count_row['total'];
$total_pages = ceil($total_data / $limit);

// Query untuk menggabungkan semua tindakan dari 6 tabel dengan pagination
$query = "
    -- Rawat Jalan Dokter
    SELECT 
        rjd.tgl_perawatan,
        rjd.jam_rawat,
        jp.nm_perawatan,
        d.nm_dokter AS petugas,
        'Dokter' AS jenis_petugas,
        'Rawat Jalan' AS jenis_rawat
    FROM rawat_jl_dr rjd
    LEFT JOIN jns_perawatan jp ON rjd.kd_jenis_prw = jp.kd_jenis_prw
    LEFT JOIN dokter d ON rjd.kd_dokter = d.kd_dokter
    WHERE rjd.no_rawat = '$no_rawat'
    
    UNION ALL
    
    -- Rawat Jalan Dokter + Perawat
    SELECT 
        rjdp.tgl_perawatan,
        rjdp.jam_rawat,
        jp.nm_perawatan,
        CONCAT(d.nm_dokter, ' & ', p.nama) AS petugas,
        'Dokter & Perawat' AS jenis_petugas,
        'Rawat Jalan' AS jenis_rawat
    FROM rawat_jl_drpr rjdp
    LEFT JOIN jns_perawatan jp ON rjdp.kd_jenis_prw = jp.kd_jenis_prw
    LEFT JOIN dokter d ON rjdp.kd_dokter = d.kd_dokter
    LEFT JOIN petugas p ON rjdp.nip = p.nip
    WHERE rjdp.no_rawat = '$no_rawat'
    
    UNION ALL
    
    -- Rawat Jalan Perawat
    SELECT 
        rjp.tgl_perawatan,
        rjp.jam_rawat,
        jp.nm_perawatan,
        p.nama AS petugas,
        'Perawat' AS jenis_petugas,
        'Rawat Jalan' AS jenis_rawat
    FROM rawat_jl_pr rjp
    LEFT JOIN jns_perawatan jp ON rjp.kd_jenis_prw = jp.kd_jenis_prw
    LEFT JOIN petugas p ON rjp.nip = p.nip
    WHERE rjp.no_rawat = '$no_rawat'
    
    UNION ALL
    
    -- Rawat Inap Dokter
    SELECT 
        rid.tgl_perawatan,
        rid.jam_rawat,
        jpi.nm_perawatan,
        d.nm_dokter AS petugas,
        'Dokter' AS jenis_petugas,
        'Rawat Inap' AS jenis_rawat
    FROM rawat_inap_dr rid
    LEFT JOIN jns_perawatan_inap jpi ON rid.kd_jenis_prw = jpi.kd_jenis_prw
    LEFT JOIN dokter d ON rid.kd_dokter = d.kd_dokter
    WHERE rid.no_rawat = '$no_rawat'
    
    UNION ALL
    
    -- Rawat Inap Dokter + Perawat
    SELECT 
        ridp.tgl_perawatan,
        ridp.jam_rawat,
        jpi.nm_perawatan,
        CONCAT(d.nm_dokter, ' & ', p.nama) AS petugas,
        'Dokter & Perawat' AS jenis_petugas,
        'Rawat Inap' AS jenis_rawat
    FROM rawat_inap_drpr ridp
    LEFT JOIN jns_perawatan_inap jpi ON ridp.kd_jenis_prw = jpi.kd_jenis_prw
    LEFT JOIN dokter d ON ridp.kd_dokter = d.kd_dokter
    LEFT JOIN petugas p ON ridp.nip = p.nip
    WHERE ridp.no_rawat = '$no_rawat'
    
    UNION ALL
    
    -- Rawat Inap Perawat
    SELECT 
        rip.tgl_perawatan,
        rip.jam_rawat,
        jpi.nm_perawatan,
        p.nama AS petugas,
        'Perawat' AS jenis_petugas,
        'Rawat Inap' AS jenis_rawat
    FROM rawat_inap_pr rip
    LEFT JOIN jns_perawatan_inap jpi ON rip.kd_jenis_prw = jpi.kd_jenis_prw
    LEFT JOIN petugas p ON rip.nip = p.nip
    WHERE rip.no_rawat = '$no_rawat'
    
    ORDER BY tgl_perawatan DESC, jam_rawat DESC
    LIMIT $limit OFFSET $offset
";

$result = bukaquery($query);

if ($total_data == 0) {
    echo '<div class="alert alert-warning m-3">Data tindakan perawatan tidak ditemukan</div>';
    exit;
}

// Function untuk badge jenis rawat
function getBadgeJenisRawat($jenis) {
    if ($jenis == 'Rawat Jalan') {
        return '<span style="background-color: #3b82f6; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; display: inline-block;">RAJAL</span>';
    } else {
        return '<span style="background-color: #8b5cf6; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; display: inline-block;">RANAP</span>';
    }
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<div class="card mb-3 shadow-sm" id="tindakan-perawatan-container">    
    <div class="card-body">
        <!-- HEADER -->
        <div class="section-title">
            <i class="fa fa-procedures"></i> Riwayat Tindakan & Perawatan
        </div>

        <!-- TABEL TINDAKAN -->
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <th style="padding: 10px; text-align: center; width: 5%; border: 1px solid #dee2e6;">No</th>
                        <th style="padding: 10px; text-align: left; width: 15%; border: 1px solid #dee2e6;">Tanggal & Jam</th>
                        <th style="padding: 10px; text-align: left; width: 50%; border: 1px solid #dee2e6;">Nama Tindakan</th>
                        <th style="padding: 10px; text-align: left; width: 30%; border: 1px solid #dee2e6;">Petugas/Dokter</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = $offset + 1;
                    while ($data = mysqli_fetch_assoc($result)): 
                    ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 8px; text-align: center; border: 1px solid #dee2e6;"><?= $no++ ?></td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">
                            <div style="font-weight: 600;"><?= date('d/m/Y', strtotime($data['tgl_perawatan'])) ?></div>
                            <div style="font-size: 11px; color: #6c757d;"><?= htmlspecialchars($data['jam_rawat']) ?></div>
                        </td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;"><?= htmlspecialchars($data['nm_perawatan']) ?: '-' ?></td>
                        <td style="padding: 8px; border: 1px solid #dee2e6;">
                            <div style="font-weight: 500;"><?= htmlspecialchars($data['petugas']) ?: '-' ?></div>
                            <div style="margin-top: 4px;">
                                <span style="background-color: #e9ecef; padding: 2px 6px; border-radius: 3px; display: inline-block; font-size: 10px; margin-right: 4px;">
                                    <?= htmlspecialchars($data['jenis_petugas']) ?>
                                </span>
                                <?= getBadgeJenisRawat($data['jenis_rawat']) ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- SUMMARY & PAGINATION -->
        <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center; padding: 10px; background-color: #f8f9fa; border-radius: 4px;">
            <div style="font-size: 12px; color: #6c757d;">
                <strong>Total Tindakan:</strong> <?= $total_data ?> tindakan | 
                <strong>Halaman:</strong> <?= $page ?> dari <?= $total_pages ?>
            </div>
            
            <!-- PAGINATION BUTTONS -->
            <div style="display: flex; gap: 5px;">
                <?php if ($page > 1): ?>
                <button onclick="loadPageTindakan(<?= $page - 1 ?>)" style="padding: 5px 10px; background-color: #3b82f6; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">
                    ‹ Prev
                </button>
                <?php endif; ?>
                
                <?php
                // Tampilkan max 5 tombol halaman
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                <button onclick="loadPageTindakan(<?= $i ?>)" style="padding: 5px 10px; background-color: <?= $i == $page ? '#667eea' : '#e9ecef' ?>; color: <?= $i == $page ? 'white' : '#495057' ?>; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: <?= $i == $page ? '600' : '400' ?>;">
                    <?= $i ?>
                </button>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <button onclick="loadPageTindakan(<?= $page + 1 ?>)" style="padding: 5px 10px; background-color: #3b82f6; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px;">
                    Next ›
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function loadPageTindakan(pageNum) {
    var noRawat = '<?= $no_rawat ?>';
    var noRm = '<?= $no_rm ?>';
    
    // Gunakan AJAX untuk reload konten
    $.ajax({
        url: 'pages/riwayat/riwayat_tindakanperawatan.php',
        type: 'GET',
        data: {
            id: noRawat,
            no_rm: noRm,
            page: pageNum
        },
        success: function(response) {
            // Replace konten container dengan response baru
            $('#tindakan-perawatan-container').replaceWith(response);
        },
        error: function() {
            alert('Gagal memuat data');
        }
    });
}
</script>