<?php
include "../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

// Get parameters
$norm = isset($_REQUEST['norm']) ? $_REQUEST['norm'] : '';
$norawat = isset($_REQUEST['norawat']) ? $_REQUEST['norawat'] : '';
$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
$isFromFilter = isset($_REQUEST['from_filter']) ? (int)$_REQUEST['from_filter'] : 0;

$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereClause = "WHERE p.no_rkm_medis = '$norm'";
if (!empty($norawat)) {
    $whereClause .= " AND rp.no_rawat = '$norawat'";
}

// Main query - ambil data kunjungan pasien
$query = "
    SELECT 
        rp.no_rawat,
        rp.tgl_registrasi,
        rp.jam_reg,
        rp.kd_poli,
        rp.kd_dokter,
        rp.status_lanjut,
        rp.stts,
        p.no_rkm_medis,
        p.nm_pasien,
        pol.nm_poli,
        d.nm_dokter,
        pj.png_jawab
    FROM reg_periksa rp
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
    LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
    LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
    $whereClause
    ORDER BY rp.tgl_registrasi DESC, rp.jam_reg DESC
    LIMIT $limit OFFSET $offset
";

// Count query
$countQuery = "
    SELECT COUNT(*) as total
    FROM reg_periksa rp
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    $whereClause
";

// Execute queries
$resultObj = bukaquery($query);
$result = [];
while($row = mysqli_fetch_assoc($resultObj)) {
    $result[] = $row;
}

$countResultObj = bukaquery($countQuery);
$countRow = mysqli_fetch_assoc($countResultObj);
$totalData = $countRow['total'] ?? 0;
$totalPages = ceil($totalData / $limit);

// Query detail tambahan untuk setiap kunjungan (diagnosa, prosedur, dll)
foreach ($result as &$row) {
    // Query diagnosa
    $diagnosaQuery = "
        SELECT 
            d.kd_penyakit,
            d.prioritas,
            d.status,
            pkt.nm_penyakit
        FROM diagnosa_pasien d
        LEFT JOIN penyakit pkt ON d.kd_penyakit = pkt.kd_penyakit
        WHERE d.no_rawat = '{$row['no_rawat']}'
        ORDER BY d.prioritas
    ";
    
    $diagnosaResultObj = bukaquery($diagnosaQuery);
    $diagnosaList = [];
    while($diag = mysqli_fetch_assoc($diagnosaResultObj)) {
        $diagnosaList[] = $diag;
    }
    $row['diagnosaList'] = $diagnosaList;
    
    // Query prosedur
    $prosedurQuery = "
        SELECT 
            pr.kode,
            pr.prioritas,
            pr.status,
            icd.deskripsi_panjang
        FROM prosedur_pasien pr
        LEFT JOIN icd9 icd ON pr.kode = icd.kode
        WHERE pr.no_rawat = '{$row['no_rawat']}'
        ORDER BY pr.prioritas
    ";
    
    $prosedurResultObj = bukaquery($prosedurQuery);
    $prosedurList = [];
    while($pros = mysqli_fetch_assoc($prosedurResultObj)) {
        $prosedurList[] = $pros;
    }
    $row['prosedurList'] = $prosedurList;
    
    // Query data rawat inap (jika status_lanjut = 'Ranap')
    $ranapList = [];
    if ($row['status_lanjut'] == 'Ranap') {
        $ranapQuery = "
            SELECT 
                ki.no_rawat,
                ki.kd_kamar,
                ki.tgl_masuk,
                ki.jam_masuk,
                ki.tgl_keluar,
                ki.jam_keluar,
                ki.lama,
                k.kd_bangsal,
                b.nm_bangsal
            FROM kamar_inap ki
            LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
            LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
            WHERE ki.no_rawat = '{$row['no_rawat']}'
            ORDER BY ki.tgl_masuk, ki.jam_masuk
        ";
        
        $ranapResultObj = bukaquery($ranapQuery);
        while($ranap = mysqli_fetch_assoc($ranapResultObj)) {
            $ranapList[] = $ranap;
        }
    }
    $row['ranapList'] = $ranapList;
}
unset($row);

// Get dropdown no_rawat options (untuk filter)
$noRawatOptions = [];
if ($isFromFilter == 0) {
    $noRawatQueryInit = "
        SELECT DISTINCT rp.no_rawat
        FROM reg_periksa rp
        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
        WHERE p.no_rkm_medis = '$norm'
        ORDER BY rp.tgl_registrasi DESC
        LIMIT 50
    ";
    
    $noRawatResultInit = bukaquery($noRawatQueryInit);
    while($rowInit = mysqli_fetch_assoc($noRawatResultInit)) {
        $noRawatOptions[] = $rowInit['no_rawat'];
    }
}
?>

<style>
.kunjungan-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.kunjungan-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

.kunjungan-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    cursor: pointer;
    position: relative;
}

.kunjungan-header:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6a3f91 100%);
}

.kunjungan-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.badge-ralan { background: #e3f2fd; color: #1565c0; }
.badge-ranap { background: #fff3e0; color: #e65100; }
.badge-sudah { background: #e8f5e9; color: #2e7d32; }
.badge-belum { background: #ffebee; color: #c62828; }

.detail-toggle-btn {
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    padding: 8px 20px;
    border-radius: 20px;
    font-size: 13px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.detail-toggle-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
}

.detail-toggle-btn.active {
    background: white;
    color: #667eea;
}

.kunjungan-detail {
    display: none;
    padding: 25px;
    background: #fafbfc;
    border-top: 1px solid #e2e8f0;
}

.section-title {
    color: #667eea;
    font-weight: 600;
    margin-bottom: 15px;
    font-size: 14px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.spin {
    animation: spin 1s linear infinite;
}
</style>

<div>
    <!-- Filter & Controls -->
    <?php if ($isFromFilter == 0): ?>
    <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
        <div class="row">
            <div class="col-md-6">
                <label style="font-weight: 600; color: #334155; margin-bottom: 8px; display: block;">
                    <i class="material-icons" style="vertical-align: middle; font-size: 18px; color: #667eea;">filter_list</i>
                    Filter Berdasarkan No. Rawat
                </label>
                <select id="filterNoRawatKunjungan" class="form-control" style="border-radius: 8px; border: 2px solid #e2e8f0;">
                    <option value="">-- Semua No Rawat --</option>
                    <?php foreach($noRawatOptions as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($norawat == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6" style="display: flex; align-items: flex-end;">
                <button type="button" id="reloadKunjunganRiwayat" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; padding: 10px 24px;">
                    <i class="material-icons" style="vertical-align: middle;">refresh</i>
                    Reload Data
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Data Cards -->
    <?php if (empty($result)): ?>
    <div class="alert alert-info" style="border-radius: 12px; border-left: 4px solid #667eea;">
        <i class="material-icons" style="vertical-align: middle;">info</i>
        Tidak ada data riwayat kunjungan yang ditemukan.
    </div>
    <?php else: ?>
    
    <?php 
    $no = $offset + 1;
    foreach($result as $row): 
        $rowId = 'kunjungan_detail_' . preg_replace('/[^a-zA-Z0-9]/', '_', $row['no_rawat']);
        $btnId = 'btn_' . $rowId;
    ?>
    <div class="kunjungan-card">
        <!-- Header -->
        <div class="kunjungan-header" onclick="toggleKunjunganDetail('<?= $rowId ?>')">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <div>
                    <div style="font-size: 16px; font-weight: 600; margin-bottom: 8px;">
                        <?= $row['no_rawat'] ?>
                    </div>
                    <div style="font-size: 13px; opacity: 0.9;">
                        <?= konversiTanggal($row['tgl_registrasi']) ?> | <?= $row['jam_reg'] ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span class="kunjungan-badge badge-<?= strtolower($row['status_lanjut']) ?>">
                        <?= $row['status_lanjut'] ?>
                    </span>
                    <br>
                    <span class="kunjungan-badge badge-<?= ($row['stts'] == 'Sudah') ? 'sudah' : 'belum' ?>" style="margin-top: 6px;">
                        <?= $row['stts'] ?>
                    </span>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                <div style="font-size: 13px; opacity: 0.95;">
                    <strong><?= $row['nm_poli'] ?></strong> • <?= $row['nm_dokter'] ?>
                    <?php if (!empty($row['ranapList']) && count($row['ranapList']) > 0): ?>
                        <span style="margin-left: 10px; background: rgba(255,255,255,0.3); padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">hotel</i>
                            <?= count($row['ranapList']) ?>x Ranap
                        </span>
                    <?php endif; ?>
                </div>
                <button type="button" id="<?= $btnId ?>" class="detail-toggle-btn">
                    <span class="icon">▼</span>
                    <span class="text">Lihat Detail</span>
                </button>
            </div>
        </div>
        
        <!-- Detail Section (Hidden by default) -->
        <div id="<?= $rowId ?>" class="kunjungan-detail">
            <!-- Info Kunjungan -->
            <div style="margin-bottom: 25px;">
                <h6 class="section-title">
                    <i class="material-icons" style="font-size: 18px;">assignment</i>
                    Informasi Kunjungan
                </h6>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td style="width: 150px; color: #64748b; padding: 6px 0;">No. Rawat</td>
                                <td style="color: #334155; padding: 6px 0; font-family: monospace;">: <?= $row['no_rawat'] ?></td>
                            </tr>
                            <tr>
                                <td style="color: #64748b; padding: 6px 0;">Tanggal Kunjungan</td>
                                <td style="color: #334155; padding: 6px 0;">: <?= konversiTanggal($row['tgl_registrasi']) ?> <?= $row['jam_reg'] ?></td>
                            </tr>
                            <tr>
                                <td style="color: #64748b; padding: 6px 0;">Poliklinik</td>
                                <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_poli'] ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td style="width: 150px; color: #64748b; padding: 6px 0;">Dokter</td>
                                <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_dokter'] ?></td>
                            </tr>
                            <tr>
                                <td style="color: #64748b; padding: 6px 0;">Cara Bayar</td>
                                <td style="color: #334155; padding: 6px 0;">: <?= $row['png_jawab'] ?? '-' ?></td>
                            </tr>
                            <tr>
                                <td style="color: #64748b; padding: 6px 0;">Status Periksa</td>
                                <td style="color: #334155; padding: 6px 0;">
                                    : <span class="kunjungan-badge badge-<?= ($row['stts'] == 'Sudah') ? 'sudah' : 'belum' ?>">
                                        <?= $row['stts'] ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Diagnosa -->
            <?php 
            $diagnosaList = $row['diagnosaList'] ?? [];
            if (!empty($diagnosaList)): 
            ?>
            <div style="margin-bottom: 25px;">
                <h6 class="section-title">
                    <i class="material-icons" style="font-size: 18px;">local_hospital</i>
                    Diagnosa
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" style="font-size: 13px;">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th style="padding: 8px; width: 50px;">No</th>
                                <th style="padding: 8px; width: 100px;">Kode ICD</th>
                                <th style="padding: 8px;">Nama Penyakit</th>
                                <th style="padding: 8px; width: 100px; text-align: center;">Prioritas</th>
                                <th style="padding: 8px; width: 100px; text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $noDiag = 1;
                            foreach($diagnosaList as $diag): 
                            ?>
                            <tr>
                                <td style="padding: 8px; text-align: center;"><?= $noDiag++ ?></td>
                                <td style="padding: 8px; font-family: monospace;"><?= $diag['kd_penyakit'] ?></td>
                                <td style="padding: 8px;"><?= $diag['nm_penyakit'] ?? '-' ?></td>
                                <td style="padding: 8px; text-align: center; font-weight: 600;"><?= $diag['prioritas'] ?></td>
                                <td style="padding: 8px; text-align: center;">
                                    <span class="kunjungan-badge badge-<?= strtolower($diag['status']) ?>">
                                        <?= $diag['status'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Prosedur -->
            <?php 
            $prosedurList = $row['prosedurList'] ?? [];
            if (!empty($prosedurList)): 
            ?>
            <div style="margin-bottom: 25px;">
                <h6 class="section-title">
                    <i class="material-icons" style="font-size: 18px;">healing</i>
                    Prosedur / Tindakan
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" style="font-size: 13px;">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th style="padding: 8px; width: 50px;">No</th>
                                <th style="padding: 8px; width: 100px;">Kode ICD-9</th>
                                <th style="padding: 8px;">Deskripsi</th>
                                <th style="padding: 8px; width: 100px; text-align: center;">Prioritas</th>
                                <th style="padding: 8px; width: 100px; text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $noPros = 1;
                            foreach($prosedurList as $pros): 
                            ?>
                            <tr>
                                <td style="padding: 8px; text-align: center;"><?= $noPros++ ?></td>
                                <td style="padding: 8px; font-family: monospace;"><?= $pros['kode'] ?></td>
                                <td style="padding: 8px;"><?= $pros['deskripsi_panjang'] ?? '-' ?></td>
                                <td style="padding: 8px; text-align: center; font-weight: 600;"><?= $pros['prioritas'] ?></td>
                                <td style="padding: 8px; text-align: center;">
                                    <span class="kunjungan-badge badge-<?= strtolower($pros['status']) ?>">
                                        <?= $pros['status'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Rawat Inap -->
            <?php 
            $ranapList = $row['ranapList'] ?? [];
            if (!empty($ranapList)): 
            ?>
            <div style="margin-bottom: 15px;">
                <h6 class="section-title">
                    <i class="material-icons" style="font-size: 18px;">hotel</i>
                    Riwayat Rawat Inap (<?= count($ranapList) ?> kali)
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" style="font-size: 13px;">
                        <thead style="background: #f8fafc;">
                            <tr>
                                <th style="padding: 8px; width: 50px;">No</th>
                                <th style="padding: 8px;">Nama Ruangan</th>
                                <th style="padding: 8px; width: 150px;">Tanggal Masuk</th>
                                <th style="padding: 8px; width: 150px;">Tanggal Keluar</th>
                                <th style="padding: 8px; width: 100px; text-align: center;">Lama (Hari)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $noRanap = 1;
                            foreach($ranapList as $ranap): 
                            ?>
                            <tr>
                                <td style="padding: 8px; text-align: center;"><?= $noRanap++ ?></td>
                                <td style="padding: 8px; font-weight: 600; color: #667eea;">
                                    <?= $ranap['nm_bangsal'] ?? '-' ?>
                                </td>
                                <td style="padding: 8px;">
                                    <?= konversiTanggal($ranap['tgl_masuk']) ?> <?= $ranap['jam_masuk'] ?>
                                </td>
                                <td style="padding: 8px;">
                                    <?php if (!empty($ranap['tgl_keluar']) && $ranap['tgl_keluar'] != '0000-00-00'): ?>
                                        <?= konversiTanggal($ranap['tgl_keluar']) ?> <?= $ranap['jam_keluar'] ?>
                                    <?php else: ?>
                                        <span style="color: #f59e0b; font-weight: 600;">Masih Dirawat</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 8px; text-align: center; font-weight: 600;">
                                    <?php if (!empty($ranap['lama']) && $ranap['lama'] > 0): ?>
                                        <?= $ranap['lama'] ?> hari
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="text-center" style="margin-top: 20px; margin-bottom: 20px;">
        <div class="btn-group" role="group">
            <?php if ($page > 1): ?>
            <button class="btn btn-outline-primary kunjungan-page" data-page="<?= $page - 1 ?>" style="border-radius: 8px 0 0 8px;">
                <i class="material-icons" style="vertical-align: middle;">chevron_left</i>
            </button>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            
            for ($i = $start; $i <= $end; $i++):
                $active = ($i == $page) ? 'active' : '';
                $style = ($i == $page) 
                    ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;' 
                    : '';
            ?>
            <button class="btn btn-outline-primary kunjungan-page <?= $active ?>" data-page="<?= $i ?>" style="<?= $style ?>">
                <?= $i ?>
            </button>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <button class="btn btn-outline-primary kunjungan-page" data-page="<?= $page + 1 ?>" style="border-radius: 0 8px 8px 0;">
                <i class="material-icons" style="vertical-align: middle;">chevron_right</i>
            </button>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 10px; color: #94a3b8; font-size: 13px;">
            Halaman <?= $page ?> dari <?= $totalPages ?> (Total: <?= $totalData ?> kunjungan)
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<!-- Load script -->
<script src="js/riwayat_kunjungan.js?v=<?= time() ?>"></script>