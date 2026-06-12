<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

$norawat = isset($_GET['id']) ? trim($_GET['id']) : '';
$no_rm   = isset($_GET['no_rm']) ? trim($_GET['no_rm']) : '';

if (empty($norawat)) {
    echo '<div class="alert alert-warning m-3">No. Rawat tidak ditemukan.</div>';
    exit;
}

$norawat_safe = mysqli_real_escape_string(bukakoneksi(), $norawat);

// Main query — filter by no_rawat saja
$query = "
    SELECT 
        op.no_rawat,
        op.tgl_operasi,
        op.jenis_anasthesi,
        op.kategori,
        op.operator1, op.operator2, op.operator3,
        op.asisten_operator1, op.asisten_operator2, op.asisten_operator3,
        op.instrumen,
        op.dokter_anak,
        op.perawaat_resusitas,
        op.dokter_anestesi,
        op.asisten_anestesi, op.asisten_anestesi2,
        op.bidan, op.bidan2, op.bidan3,
        op.perawat_luar,
        op.omloop, op.omloop2, op.omloop3, op.omloop4, op.omloop5,
        op.dokter_pjanak,
        op.dokter_umum,
        op.kode_paket,
        op.status,
        po.nm_perawatan as nama_operasi,
        rp.tgl_registrasi,
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
    WHERE op.no_rawat = '$norawat_safe'
    ORDER BY op.tgl_operasi DESC
";

$resultObj = bukaquery($query);
$result = [];
while ($row = mysqli_fetch_assoc($resultObj)) {
    $result[] = $row;
}

// Fetch laporan & obat per baris
foreach ($result as &$row) {
    $nr = mysqli_real_escape_string(bukakoneksi(), $row['no_rawat']);

    $laporanObj = bukaquery("
        SELECT diagnosa_preop, diagnosa_postop, jaringan_dieksekusi,
               selesaioperasi, permintaan_pa, laporan_operasi
        FROM laporan_operasi
        WHERE no_rawat = '$nr'
        LIMIT 1
    ");
    $row['laporanData'] = mysqli_fetch_assoc($laporanObj);

    $obatObj = bukaquery("
        SELECT bo.kd_obat, bo.jumlah, ob.nm_obat
        FROM beri_obat_operasi bo
        LEFT JOIN obatbhp_ok ob ON bo.kd_obat = ob.kd_obat
        WHERE bo.no_rawat = '$nr'
        ORDER BY bo.kd_obat
    ");
    $obatList = [];
    while ($obat = mysqli_fetch_assoc($obatObj)) {
        $obatList[] = $obat;
    }
    $row['obatList'] = $obatList;
}
unset($row);
?>

<?php if (empty($result)): ?>
<div class="card" style="border: 1px solid #e2e8f0; border-radius: 10px;">
    <div class="card-body text-center" style="padding: 60px 20px; color: #94a3b8;">
        <i class="material-icons" style="font-size: 64px; color: #cbd5e1;">local_hospital</i>
        <div style="margin-top: 15px; font-size: 16px; color: #64748b;">Tidak ada riwayat operasi</div>
    </div>
</div>
<?php else: ?>

<?php foreach ($result as $idx => $row):
    $laporanData = $row['laporanData'] ?? null;
    $obatList    = $row['obatList'] ?? [];

    // status badge
    if ($row['status'] == 'Ranap') {
        $badge = '<span class="badge" style="background:#ef4444;color:white;padding:4px 10px;border-radius:6px;font-size:11px;">Ranap</span>';
    } else {
        $badge = '<span class="badge" style="background:#10b981;color:white;padding:4px 10px;border-radius:6px;font-size:11px;">Ralan</span>';
    }

    // omloop
    $omloops = array_filter([
        $row['nm_omloop1'] ?? '', $row['nm_omloop2'] ?? '', $row['nm_omloop3'] ?? '',
        $row['nm_omloop4'] ?? '', $row['nm_omloop5'] ?? ''
    ]);
?>
<div class="card" style="border: 2px solid #667eea; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(102,126,234,0.15); margin-bottom: 20px;">
    <div class="card-header" style="background: linear-gradient(135deg,#667eea 0%,#764ba2 100%); color: white; padding: 13px 20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
            <span style="font-size:15px; font-weight:600;">
                <i class="material-icons" style="vertical-align:middle; margin-right:6px; font-size:18px;">local_hospital</i>
                Operasi <?= $idx + 1 ?> &mdash; <?= date('d-m-Y', strtotime($row['tgl_operasi'])) ?>
            </span>
            <span style="font-family:monospace; background:rgba(255,255,255,0.2); padding:4px 12px; border-radius:6px; font-size:13px;">
                <?= htmlspecialchars($row['no_rawat']) ?>
            </span>
        </div>
    </div>
    <div class="card-body" style="padding: 20px;">

        <!-- Informasi Operasi -->
        <h6 style="color:#667eea; font-weight:600; margin-bottom:12px; border-bottom:2px solid #e2e8f0; padding-bottom:7px;">
            <i class="material-icons" style="vertical-align:middle; font-size:17px;">info</i> Informasi Operasi
        </h6>
        <div class="row" style="margin-bottom:20px;">
            <div class="col-md-6">
                <table class="table table-sm table-borderless" style="margin:0;">
                    <tr>
                        <td style="width:160px; color:#64748b; padding:6px 0;">Tanggal Operasi</td>
                        <td style="color:#334155; font-weight:600; padding:6px 0;">: <?= date('d-m-Y', strtotime($row['tgl_operasi'])) ?></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b; padding:6px 0;">Jenis Operasi</td>
                        <td style="color:#334155; font-weight:600; padding:6px 0;">: <?= htmlspecialchars($row['nama_operasi'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b; padding:6px 0;">Jenis Anestesi</td>
                        <td style="color:#334155; font-weight:600; padding:6px 0;">: <?= htmlspecialchars($row['jenis_anasthesi'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b; padding:6px 0;">Kategori</td>
                        <td style="color:#334155; font-weight:600; padding:6px 0;">: <?= htmlspecialchars($row['kategori'] ?? '-') ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm table-borderless" style="margin:0;">
                    <tr>
                        <td style="width:120px; color:#64748b; padding:6px 0;">Status</td>
                        <td style="padding:6px 0;">: <?= $badge ?></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b; padding:6px 0;">Pasien</td>
                        <td style="color:#334155; font-weight:600; padding:6px 0;">: <?= htmlspecialchars($row['nm_pasien']) ?></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b; padding:6px 0;">No. RM</td>
                        <td style="color:#334155; font-weight:600; padding:6px 0;">: <?= htmlspecialchars($row['no_rkm_medis']) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Tim Operasi -->
        <?php
        $tim = array_filter([
            'Operator 1'         => $row['nm_operator1'] ?? '',
            'Operator 2'         => $row['nm_operator2'] ?? '',
            'Operator 3'         => $row['nm_operator3'] ?? '',
            'Asisten Operator 1' => $row['nm_asisten_operator1'] ?? '',
            'Asisten Operator 2' => $row['nm_asisten_operator2'] ?? '',
            'Asisten Operator 3' => $row['nm_asisten_operator3'] ?? '',
            'Dokter Anestesi'    => $row['nm_dokter_anestesi'] ?? '',
            'Asisten Anestesi 1' => $row['nm_asisten_anestesi1'] ?? '',
            'Asisten Anestesi 2' => $row['nm_asisten_anestesi2'] ?? '',
            'Instrumen'          => $row['nm_instrumen'] ?? '',
            'Dokter Anak'        => $row['nm_dokter_anak'] ?? '',
            'Perawat Resusitas'  => $row['nm_perawaat_resusitas'] ?? '',
            'Dokter PJ Anak'     => $row['nm_dokter_pjanak'] ?? '',
            'Dokter Umum'        => $row['nm_dokter_umum'] ?? '',
            'Bidan 1'            => $row['nm_bidan1'] ?? '',
            'Bidan 2'            => $row['nm_bidan2'] ?? '',
            'Bidan 3'            => $row['nm_bidan3'] ?? '',
            'Perawat Luar'       => $row['nm_perawat_luar'] ?? '',
        ]);
        if (!empty($omloops)) $tim['Onloop'] = implode(', ', $omloops);
        $timKeys   = array_keys($tim);
        $timVals   = array_values($tim);
        $half      = (int)ceil(count($tim) / 2);
        $timLeft   = array_slice($timKeys, 0, $half);
        $timRight  = array_slice($timKeys, $half);
        ?>
        <?php if (!empty($tim)): ?>
        <h6 style="color:#667eea; font-weight:600; margin-bottom:12px; border-bottom:2px solid #e2e8f0; padding-bottom:7px;">
            <i class="material-icons" style="vertical-align:middle; font-size:17px;">people</i> Tim Operasi
        </h6>
        <div class="row" style="margin-bottom:20px;">
            <div class="col-md-6">
                <table class="table table-sm table-borderless" style="margin:0;">
                    <?php foreach ($timLeft as $label): ?>
                    <tr>
                        <td style="width:160px; color:#64748b; padding:5px 0;"><?= $label ?></td>
                        <td style="color:#334155; padding:5px 0;">: <?= htmlspecialchars($tim[$label]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm table-borderless" style="margin:0;">
                    <?php foreach ($timRight as $label): ?>
                    <tr>
                        <td style="width:160px; color:#64748b; padding:5px 0;"><?= $label ?></td>
                        <td style="color:#334155; padding:5px 0;">: <?= htmlspecialchars($tim[$label]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Laporan Operasi -->
        <?php if ($laporanData): ?>
        <h6 style="color:#667eea; font-weight:600; margin-bottom:12px; border-bottom:2px solid #e2e8f0; padding-bottom:7px;">
            <i class="material-icons" style="vertical-align:middle; font-size:17px;">description</i> Laporan Operasi
        </h6>
        <div class="row" style="margin-bottom:10px;">
            <div class="col-md-6">
                <table class="table table-sm table-borderless" style="margin:0;">
                    <tr>
                        <td style="width:170px; color:#64748b; padding:6px 0;">Diagnosa Pre-Op</td>
                        <td style="color:#334155; padding:6px 0;">: <?= htmlspecialchars($laporanData['diagnosa_preop'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b; padding:6px 0;">Diagnosa Post-Op</td>
                        <td style="color:#334155; padding:6px 0;">: <?= htmlspecialchars($laporanData['diagnosa_postop'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b; padding:6px 0;">Jaringan Dieksekusi</td>
                        <td style="color:#334155; padding:6px 0;">: <?= htmlspecialchars($laporanData['jaringan_dieksekusi'] ?? '-') ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm table-borderless" style="margin:0;">
                    <tr>
                        <td style="width:160px; color:#64748b; padding:6px 0;">Selesai Operasi</td>
                        <td style="color:#334155; padding:6px 0;">: <?= htmlspecialchars($laporanData['selesaioperasi'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td style="color:#64748b; padding:6px 0;">Permintaan PA</td>
                        <td style="color:#334155; padding:6px 0;">: <?= htmlspecialchars($laporanData['permintaan_pa'] ?? '-') ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php if (!empty($laporanData['laporan_operasi'])): ?>
        <div style="padding:14px 16px; background:#f8fafc; border-left:4px solid #667eea; border-radius:6px; margin-bottom:20px;">
            <div style="font-weight:600; color:#334155; margin-bottom:6px;">Laporan Detail:</div>
            <div style="color:#64748b; line-height:1.7; white-space:pre-wrap;"><?= htmlspecialchars($laporanData['laporan_operasi']) ?></div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Obat/BHP -->
        <?php if (!empty($obatList)): ?>
        <h6 style="color:#667eea; font-weight:600; margin-bottom:12px; border-bottom:2px solid #e2e8f0; padding-bottom:7px;">
            <i class="material-icons" style="vertical-align:middle; font-size:17px;">medication</i> Obat &amp; BHP Yang Digunakan
        </h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size:13px;">
                <thead style="background:#f8fafc;">
                    <tr>
                        <th style="width:40px; padding:8px; text-align:center;">No</th>
                        <th style="padding:8px;">Kode Obat</th>
                        <th style="padding:8px;">Nama Obat/BHP</th>
                        <th style="width:90px; padding:8px; text-align:center;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($obatList as $i => $obat): ?>
                    <tr>
                        <td style="padding:7px; text-align:center;"><?= $i + 1 ?></td>
                        <td style="padding:7px; font-family:monospace;"><?= htmlspecialchars($obat['kd_obat']) ?></td>
                        <td style="padding:7px;"><?= htmlspecialchars($obat['nm_obat'] ?? '-') ?></td>
                        <td style="padding:7px; text-align:center; font-weight:600;"><?= htmlspecialchars($obat['jumlah']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /card-body -->
</div><!-- /card -->
<?php endforeach; ?>

<?php endif; ?>
