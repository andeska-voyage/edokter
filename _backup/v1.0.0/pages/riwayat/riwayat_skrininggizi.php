<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm    = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

$query = "
    SELECT
        sg.tanggal,
        sg.skrining_bb,
        sg.skrining_tb,
        sg.alergi,
        sg.parameter_imt,
        sg.skor_imt,
        sg.parameter_bb,
        sg.skor_bb,
        sg.parameter_penyakit,
        sg.skor_penyakit,
        sg.skor_total,
        sg.parameter_total,
        sg.nip,
        p.nama AS nama_petugas
    FROM skrining_gizi sg
    LEFT JOIN petugas p ON sg.nip = p.nip
    WHERE sg.no_rawat = '$no_rawat'
    ORDER BY sg.tanggal DESC
";

$result = bukaquery($query);

if (mysqli_num_rows($result) == 0) {
    echo '<div class="alert alert-warning m-3">Data skrining gizi tidak ditemukan</div>';
    exit;
}

// Badge skor parameter individual (0-2)
function getBadgeSkorGizi($skor) {
    $n = intval($skor);
    if ($n == 0)      $color = '#28a745';
    elseif ($n == 1)  $color = '#ffc107';
    elseif ($n == 2)  $color = '#fd7e14';
    else              $color = '#dc3545';
    return "<span style='background-color:{$color};color:white;padding:3px 10px;"
         . "border-radius:3px;font-size:12px;font-weight:bold;display:inline-block;"
         . "min-width:28px;text-align:center;'>{$skor}</span>";
}

// Badge total skor skrining gizi
// 0 = Tidak Berisiko | 1 = Monitor Ulang | >=2 = Risiko Malnutrisi
function getBadgeTotalGizi($total, $parameter) {
    $n = intval($total);
    if ($n == 0)       { $color = '#28a745'; $status = 'Tidak Berisiko'; }
    elseif ($n == 1)   { $color = '#ffc107'; $status = 'Monitor Ulang';  }
    elseif ($n >= 2)   { $color = '#dc3545'; $status = 'Risiko Malnutrisi'; }
    else               { $color = '#6c757d'; $status = ''; }

    $badge = "<span style='background-color:{$color};color:white;padding:6px 16px;"
           . "border-radius:4px;font-size:18px;font-weight:bold;display:inline-block;'>{$total}</span>";
    if ($status) {
        $badge .= " <span style='color:{$color};font-weight:bold;margin-left:8px;font-size:14px;'>({$status})</span>";
    }
    if (!empty($parameter)) {
        $badge .= "<div style='margin-top:8px;font-size:13px;color:#495057;'>"
                . nl2br(htmlspecialchars($parameter)) . "</div>";
    }
    return $badge;
}
?>

<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<style>
.tabel-gizi { width:100%; border-collapse:collapse; }
.tabel-gizi td { padding:10px; vertical-align:top; }
.tabel-gizi .col-waktu     { width:12%; font-weight:bold; }
.tabel-gizi .col-parameter { width:58%; }
.tabel-gizi .col-total     { width:30%; }

.card-gizi {
    margin-bottom: 15px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
}

.parameter-grid-gizi {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 8px;
}

.parameter-item-gizi {
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #dee2e6;
}

.parameter-item-full {
    grid-column: 1 / -1;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #dee2e6;
}

.parameter-label-gizi {
    font-weight: bold;
    font-size: 12px;
    color: #495057;
    display: block;
    margin-bottom: 4px;
}

.parameter-detail-gizi {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 4px;
}

.parameter-value-gizi {
    font-size: 13px;
    color: #212529;
    flex: 1;
    margin-right: 6px;
}

.bb-tb-info {
    padding: 5px 8px;
    background: #e8f5e9;
    border-radius: 4px;
    font-size: 12px;
    color: #2e7d32;
    font-weight: 500;
    margin-bottom: 4px;
}

.alergi-info {
    padding: 5px 8px;
    background: #fbe9e7;
    border-radius: 4px;
    font-size: 12px;
    color: #bf360c;
    font-weight: 500;
    margin-bottom: 4px;
}

.total-section-gizi {
    padding: 15px;
    background: #fff3e0;
    border-left: 4px solid #ff9800;
    border-radius: 4px;
}

.total-label-gizi {
    font-weight: bold;
    font-size: 14px;
    color: #2c3e50;
    display: block;
    margin-bottom: 10px;
}

.petugas-name-gizi {
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #e9ecef;
    font-size: 12px;
    color: #6c757d;
}
</style>

<?php while ($row = mysqli_fetch_assoc($result)): ?>
<div class="card-gizi">
    <table class="tabel-gizi">
        <tr>
            <!-- Kolom 1: Waktu & Petugas -->
            <td class="col-waktu">
                <div><?= date('d/m/Y', strtotime($row['tanggal'])) ?></div>
                <div><?= date('H:i', strtotime($row['tanggal'])) ?></div>
                <div class="petugas-name-gizi">
                    <?= htmlspecialchars($row['nama_petugas'] ?: '-') ?>
                </div>
            </td>

            <!-- Kolom 2: Parameter -->
            <td class="col-parameter">
                <?php if (!empty($row['skrining_bb']) || !empty($row['skrining_tb'])): ?>
                <div class="bb-tb-info">
                    BB: <?= htmlspecialchars($row['skrining_bb'] ?: '-') ?> kg
                    &nbsp;|&nbsp;
                    TB: <?= htmlspecialchars($row['skrining_tb'] ?: '-') ?> cm
                </div>
                <?php endif; ?>

                <?php if (!empty($row['alergi'])): ?>
                <div class="alergi-info">
                    ⚠ Alergi: <?= htmlspecialchars($row['alergi']) ?>
                </div>
                <?php endif; ?>

                <div class="parameter-grid-gizi">
                    <div class="parameter-item-gizi">
                        <span class="parameter-label-gizi">Parameter IMT</span>
                        <div class="parameter-detail-gizi">
                            <span class="parameter-value-gizi">
                                <?= htmlspecialchars($row['parameter_imt'] ?: '-') ?>
                            </span>
                            <?= getBadgeSkorGizi($row['skor_imt']) ?>
                        </div>
                    </div>

                    <div class="parameter-item-gizi">
                        <span class="parameter-label-gizi">Parameter BB</span>
                        <div class="parameter-detail-gizi">
                            <span class="parameter-value-gizi">
                                <?= htmlspecialchars($row['parameter_bb'] ?: '-') ?>
                            </span>
                            <?= getBadgeSkorGizi($row['skor_bb']) ?>
                        </div>
                    </div>

                    <div class="parameter-item-full">
                        <span class="parameter-label-gizi">Parameter Penyakit / Asupan Nutrisi</span>
                        <div class="parameter-detail-gizi">
                            <span class="parameter-value-gizi">
                                <?= htmlspecialchars($row['parameter_penyakit'] ?: '-') ?>
                            </span>
                            <?= getBadgeSkorGizi($row['skor_penyakit']) ?>
                        </div>
                    </div>
                </div>
            </td>

            <!-- Kolom 3: Total Skor -->
            <td class="col-total">
                <div class="total-section-gizi">
                    <span class="total-label-gizi">Total Skor Skrining:</span>
                    <?= getBadgeTotalGizi($row['skor_total'], $row['parameter_total']) ?>
                </div>
            </td>
        </tr>
    </table>
</div>
<?php endwhile; ?>
