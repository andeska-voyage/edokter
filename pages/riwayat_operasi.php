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
    $whereClause .= " AND op.no_rawat = '$norawat'";
}

// Main query
$query = "
    SELECT 
        op.no_rawat,
        op.tgl_operasi,
        op.jenis_anasthesi,
        op.kategori,
        op.operator1,
        op.operator2,
        op.operator3,
        op.asisten_operator1,
        op.asisten_operator2,
        op.asisten_operator3,
        op.instrumen,
        op.dokter_anak,
        op.perawaat_resusitas,
        op.dokter_anestesi,
        op.asisten_anestesi,
        op.asisten_anestesi2,
        op.bidan,
        op.bidan2,
        op.bidan3,
        op.perawat_luar,
        op.omloop,
        op.omloop2,
        op.omloop3,
        op.omloop4,
        op.omloop5,
        op.dokter_pjanak,
        op.dokter_umum,
        op.kode_paket,
        op.status,
        po.nm_perawatan as nama_operasi,
        rp.tgl_registrasi,
        rp.jam_reg,
        p.no_rkm_medis,
        p.nm_pasien,
        d1.nm_dokter as nm_operator1,
        d2.nm_dokter as nm_operator2,
        d3.nm_dokter as nm_operator3,
        pt1.nama as nm_asisten_operator1,
        pt2.nama as nm_asisten_operator2,
        pt3.nama as nm_asisten_operator3,
        pt4.nama as nm_instrumen,
        d4.nm_dokter as nm_dokter_anak,
        pt5.nama as nm_perawaat_resusitas,
        d5.nm_dokter as nm_dokter_anestesi,
        pt6.nama as nm_asisten_anestesi1,
        pt7.nama as nm_asisten_anestesi2,
        pt8.nama as nm_bidan1,
        pt9.nama as nm_bidan2,
        pt10.nama as nm_bidan3,
        pt11.nama as nm_perawat_luar,
        pt12.nama as nm_omloop1,
        pt13.nama as nm_omloop2,
        pt14.nama as nm_omloop3,
        pt15.nama as nm_omloop4,
        pt16.nama as nm_omloop5,
        d6.nm_dokter as nm_dokter_pjanak,
        d7.nm_dokter as nm_dokter_umum
    FROM operasi op
    INNER JOIN reg_periksa rp ON op.no_rawat = rp.no_rawat
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    LEFT JOIN paket_operasi po ON op.kode_paket = po.kode_paket
    LEFT JOIN dokter d1 ON op.operator1 = d1.kd_dokter AND op.operator1 != '-'
    LEFT JOIN dokter d2 ON op.operator2 = d2.kd_dokter AND op.operator2 != '-'
    LEFT JOIN dokter d3 ON op.operator3 = d3.kd_dokter AND op.operator3 != '-'
    LEFT JOIN dokter d4 ON op.dokter_anak = d4.kd_dokter AND op.dokter_anak != '-'
    LEFT JOIN dokter d5 ON op.dokter_anestesi = d5.kd_dokter AND op.dokter_anestesi != '-'
    LEFT JOIN dokter d6 ON op.dokter_pjanak = d6.kd_dokter AND op.dokter_pjanak != '-'
    LEFT JOIN dokter d7 ON op.dokter_umum = d7.kd_dokter AND op.dokter_umum != '-'
    LEFT JOIN petugas pt1 ON op.asisten_operator1 = pt1.nip AND op.asisten_operator1 != '-'
    LEFT JOIN petugas pt2 ON op.asisten_operator2 = pt2.nip AND op.asisten_operator2 != '-'
    LEFT JOIN petugas pt3 ON op.asisten_operator3 = pt3.nip AND op.asisten_operator3 != '-'
    LEFT JOIN petugas pt4 ON op.instrumen = pt4.nip AND op.instrumen != '-'
    LEFT JOIN petugas pt5 ON op.perawaat_resusitas = pt5.nip AND op.perawaat_resusitas != '-'
    LEFT JOIN petugas pt6 ON op.asisten_anestesi = pt6.nip AND op.asisten_anestesi != '-'
    LEFT JOIN petugas pt7 ON op.asisten_anestesi2 = pt7.nip AND op.asisten_anestesi2 != '-'
    LEFT JOIN petugas pt8 ON op.bidan = pt8.nip AND op.bidan != '-'
    LEFT JOIN petugas pt9 ON op.bidan2 = pt9.nip AND op.bidan2 != '-'
    LEFT JOIN petugas pt10 ON op.bidan3 = pt10.nip AND op.bidan3 != '-'
    LEFT JOIN petugas pt11 ON op.perawat_luar = pt11.nip AND op.perawat_luar != '-'
    LEFT JOIN petugas pt12 ON op.omloop = pt12.nip AND op.omloop != '-'
    LEFT JOIN petugas pt13 ON op.omloop2 = pt13.nip AND op.omloop2 != '-'
    LEFT JOIN petugas pt14 ON op.omloop3 = pt14.nip AND op.omloop3 != '-'
    LEFT JOIN petugas pt15 ON op.omloop4 = pt15.nip AND op.omloop4 != '-'
    LEFT JOIN petugas pt16 ON op.omloop5 = pt16.nip AND op.omloop5 != '-'
    $whereClause
    ORDER BY op.tgl_operasi DESC
    LIMIT $limit OFFSET $offset
";

// Count query
$countQuery = "
    SELECT COUNT(*) as total
    FROM operasi op
    INNER JOIN reg_periksa rp ON op.no_rawat = rp.no_rawat
    INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
    $whereClause
";

// Execute queries dan fetch ke array
$resultObj = bukaquery($query);
$result = [];
while($row = mysqli_fetch_assoc($resultObj)) {
    $result[] = $row;
}

$countResultObj = bukaquery($countQuery);
$countRow = mysqli_fetch_assoc($countResultObj);
$totalData = $countRow['total'] ?? 0;
$totalPages = ceil($totalData / $limit);

// Query laporan operasi dan obat untuk setiap data
foreach ($result as &$row) {
    // Query laporan operasi
    $laporanQuery = "
        SELECT 
            diagnosa_preop,
            diagnosa_postop,
            jaringan_dieksekusi,
            selesaioperasi,
            permintaan_pa,
            laporan_operasi
        FROM laporan_operasi
        WHERE no_rawat = '{$row['no_rawat']}'
        LIMIT 1
    ";
    
    $laporanResultObj = bukaquery($laporanQuery);
    $laporanData = mysqli_fetch_assoc($laporanResultObj);
    $row['laporanData'] = $laporanData;
    
    // Query obat/BHP
    $obatQuery = "
        SELECT 
            bo.kd_obat,
            bo.jumlah,
            obhp.nm_obat
        FROM beri_obat_operasi bo
        LEFT JOIN obatbhp_ok obhp ON bo.kd_obat = obhp.kd_obat
        WHERE bo.no_rawat = '{$row['no_rawat']}'
        ORDER BY bo.kd_obat
    ";
    
    $obatResultObj = bukaquery($obatQuery);
    $obatList = [];
    while($obat = mysqli_fetch_assoc($obatResultObj)) {
        $obatList[] = $obat;
    }
    $row['obatList'] = $obatList;
}
unset($row);

// Get dropdown no_rawat options (untuk filter)
$noRawatOptions = [];
if ($isFromFilter == 0) {
    $noRawatQueryInit = "
        SELECT DISTINCT rp.no_rawat, rp.tgl_registrasi
        FROM operasi op
        INNER JOIN reg_periksa rp ON op.no_rawat = rp.no_rawat
        WHERE rp.no_rkm_medis = '$norm'
        ORDER BY rp.tgl_registrasi DESC
        LIMIT 50
    ";
    $noRawatResultObj = bukaquery($noRawatQueryInit);
    while($nr = mysqli_fetch_assoc($noRawatResultObj)) {
        $noRawatOptions[] = $nr;
    }
}
?>

<!-- Filter Section -->
<div class="card" style="margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
    <div class="card-body" style="padding: 15px 20px;">
        <div class="row align-items-center">
            <div class="col-md-8">
                <label style="font-weight: 600; color: #334155; margin-bottom: 8px; display: block;">
                    <i class="material-icons" style="vertical-align: middle; font-size: 18px;">filter_list</i>
                    Filter Berdasarkan No. Rawat
                </label>
                <select id="filterNoRawatOp" class="form-control" style="border-radius: 8px; border: 1px solid #cbd5e1;">
                    <option value="">Semua Riwayat Operasi</option>
                    <?php foreach($noRawatOptions as $nr): ?>
                    <option value="<?= $nr['no_rawat'] ?>" <?= ($norawat == $nr['no_rawat']) ? 'selected' : '' ?>>
                        <?= $nr['no_rawat'] ?> (<?= date('d-m-Y', strtotime($nr['tgl_registrasi'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 text-right">
                <button id="reloadOpRiwayat" class="btn btn-info" style="margin-top: 26px; border-radius: 8px; padding: 8px 20px;">
                    <i class="material-icons" style="vertical-align: middle;">refresh</i> Reload
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Content Container -->
<div id="riwayat_operasi_content">
    <?php if (empty($result)): ?>
    <div class="card" style="border: 1px solid #e2e8f0; border-radius: 10px;">
        <div class="card-body text-center" style="padding: 60px 20px; color: #94a3b8;">
            <i class="material-icons" style="font-size: 64px; color: #cbd5e1;">local_hospital</i>
            <div style="margin-top: 15px; font-size: 16px; color: #64748b;">
                Tidak ada riwayat operasi
            </div>
        </div>
    </div>
    <?php else: ?>
    
    <!-- Table -->
    <div class="card" style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div class="table-responsive">
            <table class="table table-hover" style="margin-bottom: 0;">
                <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <tr>
                        <th style="padding: 12px 15px; border: none;">#</th>
                        <th style="padding: 12px 15px; border: none;">Tanggal</th>
                        <th style="padding: 12px 15px; border: none;">No. Rawat</th>
                        <th style="padding: 12px 15px; border: none;">Tindakan Operasi</th>
                        <th style="padding: 12px 15px; border: none;">Kategori</th>
                        <th style="padding: 12px 15px; border: none;">Status</th>
                        <th style="padding: 12px 15px; border: none; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
    <?php 
    $no = $offset + 1;
    foreach($result as $row): 
        $rowId = 'op_detail_' . md5($row['no_rawat'] . $row['tgl_operasi']);
        
        // Status badge
        $statusBadge = '';
        if ($row['status'] == 'Ranap') {
            $statusBadge = '<span class="badge" style="background: #ef4444; color: white; padding: 5px 12px; border-radius: 6px; font-size: 11px;">Ranap</span>';
        } else {
            $statusBadge = '<span class="badge" style="background: #10b981; color: white; padding: 5px 12px; border-radius: 6px; font-size: 11px;">Ralan</span>';
        }
    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 12px 15px; vertical-align: middle;"><?= $no++ ?></td>
                        <td style="padding: 12px 15px; vertical-align: middle;">
                            <div style="font-weight: 600; color: #334155;">
                                <?= date('d-m-Y', strtotime($row['tgl_operasi'])) ?>
                            </div>
                        </td>
                        <td style="padding: 12px 15px; vertical-align: middle;">
                            <span style="font-family: monospace; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                <?= $row['no_rawat'] ?>
                            </span>
                        </td>
                        <td style="padding: 12px 15px; vertical-align: middle;">
                            <?= $row['nama_operasi'] ?? '-' ?>
                        </td>
                        <td style="padding: 12px 15px; vertical-align: middle;">
                            <?= $row['kategori'] ?? '-' ?>
                        </td>
                        <td style="padding: 12px 15px; vertical-align: middle;">
                            <?= $statusBadge ?>
                        </td>
                        <td style="padding: 12px 15px; vertical-align: middle; text-align: center;">
                            <button 
                                type="button" 
                                id="btn_<?= $rowId ?>"
                                onclick="toggleOpDetail('<?= $rowId ?>')" 
                                class="btn btn-sm btn-detail-toggle"
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; padding: 6px 16px; font-size: 12px; font-weight: 500; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);">
                                <span class="icon">▼</span>
                                <span class="text">Lihat Detail</span>
                            </button>
                        </td>
                    </tr>
    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Detail Sections (Outside Table) -->
    <?php 
    foreach($result as $row): 
        $rowId = 'op_detail_' . md5($row['no_rawat'] . $row['tgl_operasi']);
        
        // Get laporan operasi
        $laporanQuery = "
            SELECT * FROM laporan_operasi 
            WHERE no_rawat = '".$row['no_rawat']."' 
            AND DATE(tanggal) = '".$row['tgl_operasi']."'
            LIMIT 1
        ";
        $laporanResultObj = bukaquery($laporanQuery);
        $laporanData = mysqli_fetch_assoc($laporanResultObj);
        
        // Get obat operasi
        $obatQuery = "
            SELECT 
                bo.kd_obat,
                bo.jumlah,
                ob.nm_obat
            FROM beri_obat_operasi bo
            LEFT JOIN obatbhp_ok ob ON bo.kd_obat = ob.kd_obat
            WHERE bo.no_rawat = '".$row['no_rawat']."'
            AND DATE(bo.tanggal) = '".$row['tgl_operasi']."'
        ";
        $obatResultObj = bukaquery($obatQuery);
        $obatList = [];
        while($obat = mysqli_fetch_assoc($obatResultObj)) {
            $obatList[] = $obat;
        }
    ?>
    <div id="<?= $rowId ?>" class="detail-section-outside" style="display: none; margin-top: 15px; margin-bottom: 15px;">
        <div class="card" style="border: 2px solid #667eea; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);">
            <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px;">
                <h5 style="margin: 0; font-size: 16px; font-weight: 600;">
                    <i class="material-icons" style="vertical-align: middle; margin-right: 8px;">local_hospital</i>
                    Detail Operasi - <?= $row['no_rawat'] ?>
                </h5>
            </div>
            <div class="card-body" style="padding: 20px;">
                
                <!-- Data Operasi -->
                <div style="margin-bottom: 25px;">
                    <h6 style="color: #667eea; font-weight: 600; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                        <i class="material-icons" style="vertical-align: middle; font-size: 18px;">info</i>
                        Informasi Operasi
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td style="width: 180px; color: #64748b; padding: 8px 0;">Tanggal Operasi</td>
                                    <td style="font-weight: 600; color: #334155; padding: 8px 0;">
                                        : <?= date('d-m-Y', strtotime($row['tgl_operasi'])) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 8px 0;">Jenis Operasi</td>
                                    <td style="font-weight: 600; color: #334155; padding: 8px 0;">
                                        : <?= $row['nama_operasi'] ?? '-' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 8px 0;">Jenis Anestesi</td>
                                    <td style="font-weight: 600; color: #334155; padding: 8px 0;">
                                        : <?= $row['jenis_anasthesi'] ?? '-' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 8px 0;">Kategori</td>
                                    <td style="font-weight: 600; color: #334155; padding: 8px 0;">
                                        : <?= $row['kategori'] ?? '-' ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td style="width: 150px; color: #64748b; padding: 8px 0;">Status</td>
                                    <td style="padding: 8px 0;">
                                        : <?php
                                        if ($row['status'] == 'Ranap') {
                                            echo '<span class="badge" style="background: #ef4444; color: white; padding: 5px 12px; border-radius: 6px;">Ranap</span>';
                                        } else {
                                            echo '<span class="badge" style="background: #10b981; color: white; padding: 5px 12px; border-radius: 6px;">Ralan</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 8px 0;">Pasien</td>
                                    <td style="font-weight: 600; color: #334155; padding: 8px 0;">
                                        : <?= $row['nm_pasien'] ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 8px 0;">No. RM</td>
                                    <td style="font-weight: 600; color: #334155; padding: 8px 0;">
                                        : <?= $row['no_rkm_medis'] ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tim Operasi -->
                <div style="margin-bottom: 25px;">
                    <h6 style="color: #667eea; font-weight: 600; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                        <i class="material-icons" style="vertical-align: middle; font-size: 18px;">people</i>
                        Tim Operasi
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <?php if (!empty($row['nm_operator1'])): ?>
                                <tr>
                                    <td style="width: 180px; color: #64748b; padding: 6px 0;">Operator 1</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_operator1'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_operator2'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Operator 2</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_operator2'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_operator3'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Operator 3</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_operator3'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_asisten_operator1'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Asisten Operator 1</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_asisten_operator1'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_asisten_operator2'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Asisten Operator 2</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_asisten_operator2'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_asisten_operator3'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Asisten Operator 3</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_asisten_operator3'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_dokter_anestesi'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Dokter Anestesi</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_dokter_anestesi'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_asisten_anestesi1'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Asisten Anestesi 1</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_asisten_anestesi1'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_asisten_anestesi2'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Asisten Anestesi 2</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_asisten_anestesi2'] ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <?php if (!empty($row['nm_instrumen'])): ?>
                                <tr>
                                    <td style="width: 180px; color: #64748b; padding: 6px 0;">Instrumen</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_instrumen'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_dokter_anak'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Dokter Anak</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_dokter_anak'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_perawaat_resusitas'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Perawat Resusitas</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_perawaat_resusitas'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_dokter_pjanak'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Dokter PJ Anak</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_dokter_pjanak'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_dokter_umum'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Dokter Umum</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_dokter_umum'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_bidan1'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Bidan 1</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_bidan1'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_bidan2'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Bidan 2</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_bidan2'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_bidan3'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Bidan 3</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_bidan3'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if (!empty($row['nm_perawat_luar'])): ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Perawat Luar</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= $row['nm_perawat_luar'] ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php 
                                $omloops = [];
                                if (!empty($row['nm_omloop1'])) $omloops[] = $row['nm_omloop1'];
                                if (!empty($row['nm_omloop2'])) $omloops[] = $row['nm_omloop2'];
                                if (!empty($row['nm_omloop3'])) $omloops[] = $row['nm_omloop3'];
                                if (!empty($row['nm_omloop4'])) $omloops[] = $row['nm_omloop4'];
                                if (!empty($row['nm_omloop5'])) $omloops[] = $row['nm_omloop5'];
                                
                                if (!empty($omloops)): 
                                ?>
                                <tr>
                                    <td style="color: #64748b; padding: 6px 0;">Onloop</td>
                                    <td style="color: #334155; padding: 6px 0;">: <?= implode(', ', $omloops) ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Laporan Operasi -->
                <?php 
                $laporanData = $row['laporanData'] ?? null;
                if ($laporanData): 
                ?>
                <div style="margin-bottom: 25px;">
                    <h6 style="color: #667eea; font-weight: 600; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                        <i class="material-icons" style="vertical-align: middle; font-size: 18px;">description</i>
                        Laporan Operasi
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td style="width: 180px; color: #64748b; padding: 8px 0;">Diagnosa Pre-Op</td>
                                    <td style="color: #334155; padding: 8px 0;">
                                        : <?= $laporanData['diagnosa_preop'] ?? '-' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 8px 0;">Diagnosa Post-Op</td>
                                    <td style="color: #334155; padding: 8px 0;">
                                        : <?= $laporanData['diagnosa_postop'] ?? '-' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 8px 0;">Jaringan Dieksekusi</td>
                                    <td style="color: #334155; padding: 8px 0;">
                                        : <?= $laporanData['jaringan_dieksekusi'] ?? '-' ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td style="width: 180px; color: #64748b; padding: 8px 0;">Selesai Operasi</td>
                                    <td style="color: #334155; padding: 8px 0;">
                                        : <?= !empty($laporanData['selesaioperasi']) ? $laporanData['selesaioperasi'] : '-' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 8px 0;">Permintaan PA</td>
                                    <td style="color: #334155; padding: 8px 0;">
                                        : <?= $laporanData['permintaan_pa'] ?? '-' ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($laporanData['laporan_operasi'])): ?>
                    <div style="margin-top: 15px; padding: 15px; background: #f8fafc; border-left: 4px solid #667eea; border-radius: 6px;">
                        <div style="font-weight: 600; color: #334155; margin-bottom: 8px;">
                            Laporan Detail:
                        </div>
                        <div style="color: #64748b; line-height: 1.6; white-space: pre-wrap;">
                            <?= htmlspecialchars($laporanData['laporan_operasi']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Obat/BHP Operasi -->
                <?php 
                $obatList = $row['obatList'] ?? [];
                if (!empty($obatList)): 
                ?>
                <div style="margin-bottom: 15px;">
                    <h6 style="color: #667eea; font-weight: 600; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
                        <i class="material-icons" style="vertical-align: middle; font-size: 18px;">medication</i>
                        Obat & BHP Yang Digunakan
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered" style="font-size: 13px;">
                            <thead style="background: #f8fafc;">
                                <tr>
                                    <th style="padding: 8px; width: 50px;">No</th>
                                    <th style="padding: 8px;">Kode Obat</th>
                                    <th style="padding: 8px;">Nama Obat/BHP</th>
                                    <th style="padding: 8px; width: 100px; text-align: center;">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $noObat = 1;
                                foreach($obatList as $obat): 
                                ?>
                                <tr>
                                    <td style="padding: 8px; text-align: center;"><?= $noObat++ ?></td>
                                    <td style="padding: 8px; font-family: monospace;"><?= $obat['kd_obat'] ?></td>
                                    <td style="padding: 8px;"><?= $obat['nm_obat'] ?? '-' ?></td>
                                    <td style="padding: 8px; text-align: center; font-weight: 600;"><?= $obat['jumlah'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="text-center" style="margin-top: 20px; margin-bottom: 20px;">
        <div class="btn-group" role="group">
            <?php if ($page > 1): ?>
            <button class="btn btn-outline-primary op-page" data-page="<?= $page - 1 ?>" style="border-radius: 8px 0 0 8px;">
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
            <button class="btn btn-outline-primary op-page <?= $active ?>" data-page="<?= $i ?>" style="<?= $style ?>">
                <?= $i ?>
            </button>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <button class="btn btn-outline-primary op-page" data-page="<?= $page + 1 ?>" style="border-radius: 0 8px 8px 0;">
                <i class="material-icons" style="vertical-align: middle;">chevron_right</i>
            </button>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 10px; color: #94a3b8; font-size: 13px;">
            Halaman <?= $page ?> dari <?= $totalPages ?> (Total: <?= $totalData ?> data)
        </div>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>
<!-- Load script -->
<script src="js/riwayat_op.js?v=<?= time() ?>"></script>