<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

// Get parameters
$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm    = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

// Query hasil Endoskopi Telinga
$query_endo = "
    SELECT u.*, d.nm_dokter
    FROM hasil_endoskopi_telinga u
    LEFT JOIN dokter d ON u.kd_dokter = d.kd_dokter
    WHERE u.no_rawat = '$no_rawat'
    ORDER BY u.tanggal DESC
";
$result_endo = bukaquery($query_endo);

if (mysqli_num_rows($result_endo) == 0) {
    echo '<div class="alert alert-warning m-3">Data hasil Endoskopi Telinga tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_endo)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl_obj).' '.$bulan[date('n',$tgl_obj)].' '.date('Y, H:i',$tgl_obj);

    // Query gambar
    $query_gambar = "SELECT photo FROM hasil_endoskopi_telinga_gambar WHERE no_rawat = '$no_rawat'";
    $result_gambar = bukaquery($query_gambar);
    $gambar_list = [];
    while ($g = mysqli_fetch_assoc($result_gambar)) {
        if (!empty($g['photo'])) $gambar_list[] = $g['photo'];
    }
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<style>
    .endo-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        margin: 6px 0 10px 0;
    }
    .endo-table thead tr { background-color: #1d4ed8; color: #fff; }
    .endo-table thead th {
        padding: 8px 12px;
        font-weight: 600;
        font-size: 12px;
    }
    .endo-table thead th.col-label { text-align: center; width: 30%; }
    .endo-table thead th.col-kanan { text-align: left; width: 35%; }
    .endo-table thead th.col-kiri  { text-align: left; width: 35%; }
    .endo-table tbody tr { border-bottom: 1px solid #e2e8f0; }
    .endo-table tbody tr:nth-child(even) { background-color: #f8fafc; }
    .endo-table tbody tr:hover { background-color: #eff6ff; }
    .endo-table tbody td {
        padding: 7px 12px;
        font-size: 12px;
        color: #1e293b;
        vertical-align: middle;
    }
    .endo-table tbody td.col-label {
        text-align: center;
        font-weight: 600;
        color: #334155;
        background-color: #f1f5f9;
    }
    .endo-table tbody td.col-ket {
        font-size: 11px;
        color: #64748b;
        font-style: italic;
    }
    .endo-section-sub {
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 5px 10px;
        background: #f1f5f9;
        border-left: 3px solid #dc2626;
        border-radius: 0 4px 4px 0;
        margin: 10px 0 6px 0;
    }
    .usg-foto-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 10px;
        margin-top: 8px;
    }
    .usg-foto-item {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
        background: #f8fafc;
        text-align: center;
    }
    .usg-foto-item img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        display: block;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    .usg-foto-item img:hover { opacity: 0.85; }
    .usg-foto-item .foto-caption {
        font-size: 10px;
        color: #94a3b8;
        padding: 4px 6px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .kesimpulan-block {
        padding: 10px 14px;
        background: #f0fdf4;
        border-left: 4px solid #10b981;
        border-radius: 0 6px 6px 0;
        font-size: 13px;
        color: #1e293b;
        line-height: 1.6;
        margin-bottom: 8px;
    }
    .anjuran-block {
        padding: 10px 14px;
        background: #fefce8;
        border-left: 4px solid #f59e0b;
        border-radius: 0 6px 6px 0;
        font-size: 13px;
        color: #1e293b;
        line-height: 1.6;
    }
</style>
<div class="card mb-3 shadow-sm">
    <div class="card-body">

        <!-- I. INFORMASI PEMERIKSAAN -->
        <div class="section-title">
            <i class="fa fa-info-circle"></i> I. Informasi Pemeriksaan
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Tanggal:</span>
                <span class="info-value"><?= $tanggal_format ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Dokter:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_dokter']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Diagnosa Klinis:</span>
                <span class="info-value"><?= htmlspecialchars($data['diagnosa_klinis']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kiriman Dari:</span>
                <span class="info-value"><?= htmlspecialchars($data['kiriman_dari']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. HASIL PEMERIKSAAN ENDOSKOPI TELINGA -->
        <div class="section-title">
            <i class="fa fa-stethoscope"></i> II. Hasil Pemeriksaan Endoskopi Telinga
        </div>

        <!-- Sub: Liang Telinga -->
        <div class="endo-section-sub">Liang Telinga</div>
        <table class="endo-table">
            <thead>
                <tr>
                    <th class="col-kanan">Kanan</th>
                    <th class="col-label">Pemeriksaan</th>
                    <th class="col-kiri">Kiri</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($data['bentuk_liang_telinga_kanan']) ?: '-' ?></td>
                    <td class="col-label">Bentuk Liang</td>
                    <td><?= htmlspecialchars($data['bentuk_liang_telinga_kiri']) ?: '-' ?></td>
                </tr>
                <tr>
                    <td>
                        <?= htmlspecialchars($data['kondisi_liang_telinga_kanan']) ?: '-' ?>
                        <?php if (!empty($data['keterangan_kondisi_liang_telinga_kanan'])): ?>
                            <br><small class="col-ket"><?= htmlspecialchars($data['keterangan_kondisi_liang_telinga_kanan']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="col-label">Kondisi Liang</td>
                    <td>
                        <?= htmlspecialchars($data['kondisi_liang_telinga_kiri']) ?: '-' ?>
                        <?php if (!empty($data['keterangan_kondisi_liang_telinga_kiri'])): ?>
                            <br><small class="col-ket"><?= htmlspecialchars($data['keterangan_kondisi_liang_telinga_kiri']) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Sub: Membran Timpani -->
        <div class="endo-section-sub">Membran Timpani</div>
        <table class="endo-table">
            <thead>
                <tr>
                    <th class="col-kanan">Kanan</th>
                    <th class="col-label">Pemeriksaan</th>
                    <th class="col-kiri">Kiri</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($data['membran_timpani_intak_kanan']) ?: '-' ?></td>
                    <td class="col-label">Intak</td>
                    <td><?= htmlspecialchars($data['membran_timpani_intak_kiri']) ?: '-' ?></td>
                </tr>
                <tr>
                    <td>
                        <?= htmlspecialchars($data['membran_timpani_perforasi_kanan']) ?: '-' ?>
                        <?php if (!empty($data['keterangan_membran_timpani_perforasi_kanan'])): ?>
                            <br><small class="col-ket"><?= htmlspecialchars($data['keterangan_membran_timpani_perforasi_kanan']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="col-label">Perforasi</td>
                    <td>
                        <?= htmlspecialchars($data['membran_timpani_perforasi_kiri']) ?: '-' ?>
                        <?php if (!empty($data['keterangan_membran_timpani_perforasi_kiri'])): ?>
                            <br><small class="col-ket"><?= htmlspecialchars($data['keterangan_membran_timpani_perforasi_kiri']) ?></small>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Sub: Kavum Timpani -->
        <div class="endo-section-sub">Kavum Timpani</div>
        <table class="endo-table">
            <thead>
                <tr>
                    <th class="col-kanan">Kanan</th>
                    <th class="col-label">Pemeriksaan</th>
                    <th class="col-kiri">Kiri</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= htmlspecialchars($data['kavum_timpani_mukosa_kanan']) ?: '-' ?></td>
                    <td class="col-label">Mukosa</td>
                    <td><?= htmlspecialchars($data['kavum_timpani_mukosa_kiri']) ?: '-' ?></td>
                </tr>
                <tr>
                    <td><?= htmlspecialchars($data['kavum_timpani_osikel_kanan']) ?: '-' ?></td>
                    <td class="col-label">Osikel</td>
                    <td><?= htmlspecialchars($data['kavum_timpani_osikel_kiri']) ?: '-' ?></td>
                </tr>
                <tr>
                    <td><?= htmlspecialchars($data['kavum_timpani_isthmus_kanan']) ?: '-' ?></td>
                    <td class="col-label">Isthmus</td>
                    <td><?= htmlspecialchars($data['kavum_timpani_isthmus_kiri']) ?: '-' ?></td>
                </tr>
                <tr>
                    <td><?= htmlspecialchars($data['kavum_timpani_anterior_kanan']) ?: '-' ?></td>
                    <td class="col-label">Anterior</td>
                    <td><?= htmlspecialchars($data['kavum_timpani_anterior_kiri']) ?: '-' ?></td>
                </tr>
                <tr>
                    <td><?= htmlspecialchars($data['kavum_timpani_posterior_kanan']) ?: '-' ?></td>
                    <td class="col-label">Posterior</td>
                    <td><?= htmlspecialchars($data['kavum_timpani_posterior_kiri']) ?: '-' ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Sub: Lain-lain -->
        <?php if (!empty($data['lainlain_kanan']) || !empty($data['lainlain_kiri'])): ?>
        <div class="endo-section-sub">Lain-lain</div>
        <table class="endo-table">
            <thead>
                <tr>
                    <th class="col-kanan">Kanan</th>
                    <th class="col-label">Keterangan</th>
                    <th class="col-kiri">Kiri</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= nl2br(htmlspecialchars($data['lainlain_kanan'])) ?: '-' ?></td>
                    <td class="col-label">Lain-lain</td>
                    <td><?= nl2br(htmlspecialchars($data['lainlain_kiri'])) ?: '-' ?></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- III. KESIMPULAN & ANJURAN -->
        <div class="section-title">
            <i class="fa fa-check-circle"></i> III. Kesimpulan &amp; Anjuran
        </div>
        <?php if (!empty($data['kesimpulan'])): ?>
        <div style="margin-bottom:6px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px;">Kesimpulan:</div>
            <div class="kesimpulan-block"><?= nl2br(htmlspecialchars($data['kesimpulan'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['anjuran'])): ?>
        <div style="margin-bottom:6px;">
            <div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px;">Anjuran:</div>
            <div class="anjuran-block"><?= nl2br(htmlspecialchars($data['anjuran'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if (empty($data['kesimpulan']) && empty($data['anjuran'])): ?>
        <div class="empty-state">Tidak ada kesimpulan/anjuran.</div>
        <?php endif; ?>

        <!-- IV. FOTO ENDOSKOPI -->
        <div class="section-title" style="margin-top:16px;">
            <i class="fa fa-images"></i> IV. Foto Endoskopi
        </div>
        <?php if (!empty($gambar_list)): ?>
        <div class="usg-foto-grid">
            <?php foreach ($gambar_list as $idx => $photo): ?>
            <div class="usg-foto-item">
                <img src="<?= ENDOSKOPI_TELINGA_BASE_URL . $photo ?>"
                     alt="Foto Endoskopi <?= $idx + 1 ?>"
                     onclick="window.open('<?= ENDOSKOPI_TELINGA_BASE_URL . $photo ?>', '_blank')"
                     title="Klik untuk perbesar">
                <div class="foto-caption">Foto <?= $idx + 1 ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Tidak ada foto endoskopi tersedia.</div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>