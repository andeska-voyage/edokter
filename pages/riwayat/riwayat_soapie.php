<?php
session_start();
require_once('../../conf/conf.php');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo "<div class='alert alert-danger'>Session expired. Silakan login kembali.</div>";
    exit();
}

// Ambil parameter NO RAWAT
$no_rawat = isset($_REQUEST['id']) ? validTeks4($_REQUEST['id'], 20) : '';

if(empty($no_rawat)){
    echo "<div class='alert alert-warning'>Parameter tidak lengkap.</div>";
    exit();
}

// ===================================================
// HELPER FUNCTIONS
// ===================================================

function getBadgeVitalR($label, $type = 'normal') {
    $colors = [
        'normal'  => '#28a745',
        'warning' => '#ffc107',
        'danger'  => '#dc3545',
        'info'    => '#17a2b8'
    ];
    $color = $colors[$type] ?? $colors['normal'];
    return '<span style="display:inline-block;background:'.$color.';color:#fff;padding:2px 6px;border-radius:3px;font-size:10px;margin-left:5px;font-weight:600;">'.$label.'</span>';
}

function evaluateVitalSignsR($row) {
    $result = [];

    // Tensi
    $tensi = $row['tensi'];
    $result['tensi'] = ['status' => 'normal', 'label' => 'Normal'];
    if(!empty($tensi) && $tensi != '-') {
        $parts = explode('/', $tensi);
        if(count($parts) == 2) {
            $s = intval($parts[0]); $d = intval($parts[1]);
            if($s >= 180 || $d >= 120)                                   $result['tensi'] = ['status' => 'danger',  'label' => 'Krisis'];
            elseif($s >= 140 || $d >= 95)                                $result['tensi'] = ['status' => 'danger',  'label' => 'Tinggi'];
            elseif(($s >= 130 && $s < 140) || ($d >= 90 && $d < 95))    $result['tensi'] = ['status' => 'warning', 'label' => 'Tinggi Normal'];
            elseif($s < 90 || $d < 60)                                   $result['tensi'] = ['status' => 'warning', 'label' => 'Rendah'];
        }
    }

    // Suhu
    $suhu = floatval($row['suhu_tubuh']);
    $result['suhu'] = ['status' => 'normal', 'label' => 'Normal'];
    if($suhu > 0) {
        if($suhu >= 37.5)    $result['suhu'] = ['status' => 'danger',  'label' => 'Demam'];
        elseif($suhu < 36.0) $result['suhu'] = ['status' => 'warning', 'label' => 'Rendah'];
    }

    // Nadi
    $nadi = intval($row['nadi']);
    $result['nadi'] = ['status' => 'normal', 'label' => 'Normal'];
    if($nadi > 0) {
        if($nadi > 100)    $result['nadi'] = ['status' => 'danger',  'label' => 'Tinggi'];
        elseif($nadi < 60) $result['nadi'] = ['status' => 'warning', 'label' => 'Rendah'];
    }

    // RR
    $rr = intval($row['respirasi']);
    $result['rr'] = ['status' => 'normal', 'label' => 'Normal'];
    if($rr > 0) {
        if($rr > 20)    $result['rr'] = ['status' => 'danger',  'label' => 'Tinggi'];
        elseif($rr < 12) $result['rr'] = ['status' => 'warning', 'label' => 'Rendah'];
    }

    // SpO2
    $spo2 = intval($row['spo2']);
    $result['spo2'] = ['status' => 'normal', 'label' => 'Normal'];
    if($spo2 > 0) {
        if($spo2 < 95)                       $result['spo2'] = ['status' => 'danger',  'label' => 'Rendah'];
        elseif($spo2 >= 95 && $spo2 <= 97)   $result['spo2'] = ['status' => 'warning', 'label' => 'Perhatian'];
    }

    // GCS
    $gcs = intval($row['gcs']);
    $result['gcs'] = ['status' => 'normal', 'label' => 'Normal'];
    if($gcs > 0) {
        if($gcs < 13)                      $result['gcs'] = ['status' => 'danger',  'label' => 'Rendah'];
        elseif($gcs >= 13 && $gcs <= 14)   $result['gcs'] = ['status' => 'warning', 'label' => 'Perhatian'];
    }

    return $result;
}

function isSOAPIEEmptyR($row) {
    return empty(trim($row['keluhan']))    &&
           empty(trim($row['pemeriksaan'])) &&
           empty(trim($row['penilaian']))  &&
           empty(trim($row['rtl']))        &&
           empty(trim($row['instruksi'])) &&
           empty(trim($row['evaluasi']));
}

function getBadgePetugasR($nama) {
    $isDokter = (stripos($nama, 'dr.') !== false || stripos($nama, 'drg.') !== false);
    $bg = $isDokter ? '#28a745' : '#2196F3';
    $icon = $isDokter ? 'person' : 'local_hospital';
    return '<span style="display:inline-block;background:'.$bg.';color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;margin-top:3px;">
                <i class="material-icons" style="font-size:12px;vertical-align:middle;margin-right:2px;">'.$icon.'</i>'.$nama.'
            </span>';
}

function formatSOAPIEContentR($text) {
    if(empty($text)) return '';
    $text = trim($text);
    $text = preg_replace("/(\r\n|\r|\n){2,}/", "\n", $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = htmlspecialchars($text);
    $text = nl2br($text);
    return $text;
}

// ===================================================
// DETEKSI TIPE: ralan atau ranap berdasarkan no_rawat
// ===================================================
$is_ralan = false;
$is_ranap = false;

$qCekRalan = bukaquery("SELECT COUNT(*) as jml FROM pemeriksaan_ralan WHERE no_rawat = '$no_rawat'");
$rowRalan   = mysqli_fetch_assoc($qCekRalan);
if($rowRalan['jml'] > 0) $is_ralan = true;

$qCekRanap = bukaquery("SELECT COUNT(*) as jml FROM pemeriksaan_ranap WHERE no_rawat = '$no_rawat'");
$rowRanap   = mysqli_fetch_assoc($qCekRanap);
if($rowRanap['jml'] > 0) $is_ranap = true;

// ===================================================
// QUERY DATA
// ===================================================
$data_rows = [];

if($is_ralan) {
    $qData = bukaquery("
        SELECT
            pr.no_rawat,
            pr.tgl_perawatan,
            pr.jam_rawat,
            pr.suhu_tubuh,
            pr.tensi,
            pr.nadi,
            pr.respirasi,
            pr.tinggi,
            pr.berat,
            pr.spo2,
            pr.gcs,
            pr.kesadaran,
            pr.keluhan,
            pr.pemeriksaan,
            pr.penilaian,
            pr.rtl,
            pr.instruksi,
            pr.evaluasi,
            pol.nm_poli,
            pg.nama AS nama_petugas
        FROM pemeriksaan_ralan pr
        LEFT JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
        LEFT JOIN pegawai pg ON pr.nip = pg.nik
        WHERE pr.no_rawat = '$no_rawat'
        ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
    ");
    while($r = mysqli_fetch_array($qData)) { $r['_tipe'] = 'Rawat Jalan'; $data_rows[] = $r; }
}

if($is_ranap) {
    $qData = bukaquery("
        SELECT
            pr.no_rawat,
            pr.tgl_perawatan,
            pr.jam_rawat,
            pr.suhu_tubuh,
            pr.tensi,
            pr.nadi,
            pr.respirasi,
            pr.tinggi,
            pr.berat,
            pr.spo2,
            pr.gcs,
            pr.kesadaran,
            pr.keluhan,
            pr.pemeriksaan,
            pr.penilaian,
            pr.rtl,
            pr.instruksi,
            pr.evaluasi,
            'Rawat Inap' as nm_poli,
            pg.nama AS nama_petugas
        FROM pemeriksaan_ranap pr
        LEFT JOIN pegawai pg ON pr.nip = pg.nik
        WHERE pr.no_rawat = '$no_rawat'
        ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
    ");
    while($r = mysqli_fetch_array($qData)) { $r['_tipe'] = 'Rawat Inap'; $data_rows[] = $r; }
}
?>

<style>
.soapie-riwayat-container {
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: hidden;
    scroll-behavior: smooth;
}
.soapie-riwayat-container::-webkit-scrollbar { width: 7px; }
.soapie-riwayat-container::-webkit-scrollbar-track { background: #f1f3f5; border-radius: 4px; }
.soapie-riwayat-container::-webkit-scrollbar-thumb { background: #667eea; border-radius: 4px; }
.soapie-riwayat-container::-webkit-scrollbar-thumb:hover { background: #5568d3; }

.soapie-table {
    width: 100%;
    margin: 0;
    border-collapse: collapse;
    font-size: 13px;
    background: transparent;
}
.soapie-table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 8px;
    text-align: left;
    font-weight: 600;
    border: none;
    border-bottom: 2px solid #5a67d8;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}
.soapie-table td {
    padding: 12px 8px;
    border: none;
    border-bottom: 1px solid #e0e0e0;
    vertical-align: top;
    background: white;
}
.soapie-table tbody tr:hover td { background-color: #f8f9fa; }

.rowspan-cell {
    border-right: 2px solid #667eea !important;
    background: #f8f9fb !important;
    text-align: center;
    font-weight: 700;
    color: #667eea;
    font-size: 15px;
}
.group-first td { border-top: 3px solid #667eea !important; }

.vital-signs { display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
.vital-item  { display: flex; align-items: center; gap: 8px; }
.vital-item strong { font-weight: 600; color: #333; min-width: 90px; display: inline-block; }
.vital-item-value  { color: #555; }

.soapie-cell { display: flex; flex-direction: column; gap: 10px; }
.soapie-item {
    display: flex; flex-direction: column; gap: 6px;
    padding: 10px; border-radius: 6px; background: #fff;
}
.soapie-item-header { display: flex; align-items: center; gap: 8px; }
.soapie-item.s-section { border-left: 4px solid #4CAF50; background: #f1f8f4; }
.soapie-item.o-section { border-left: 4px solid #2196F3; background: #f5f9ff; }
.soapie-item.a-section { border-left: 4px solid #ff9800; background: #fff8f0; }
.soapie-item.p-section { border-left: 4px solid #9c27b0; background: #f3e5f5; }
.soapie-item.i-section { border-left: 4px solid #00bcd4; background: #e0f7fa; }
.soapie-item.e-section { border-left: 4px solid #e91e63; background: #fce4ec; }

.soapie-label {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px; border-radius: 50%;
    font-weight: 700; font-size: 12px; color: white; flex-shrink: 0;
}
.soapie-label.s { background: #4CAF50; }
.soapie-label.o { background: #2196F3; }
.soapie-label.a { background: #ff9800; }
.soapie-label.p { background: #9c27b0; }
.soapie-label.i { background: #00bcd4; }
.soapie-label.e { background: #e91e63; }

.soapie-label-text {
    font-weight: 600; font-size: 11px; color: #666;
    margin-left: 4px; letter-spacing: 0.3px; text-transform: uppercase;
}
.soapie-content { display: block; line-height: 1.6; color: #333; font-size: 13px; }

.no-soapie-notice {
    padding: 12px; background: #fff3cd;
    border-left: 4px solid #ffc107; color: #856404;
    border-radius: 4px; font-size: 13px; font-style: italic;
}

.empty-state {
    text-align: center; padding: 60px 20px;
    background: #fff; border-radius: 12px;
}
.empty-state i  { font-size: 64px; color: #cbd5e1; display: block; margin-bottom: 16px; }
.empty-state h4 { color: #64748b; margin: 0 0 8px 0; }
.empty-state p  { color: #94a3b8; font-size: 14px; }
</style>

<div class="soapie-riwayat-container">
<?php if(empty($data_rows)): ?>
    <div class="empty-state">
        <i class="material-icons">assignment</i>
        <h4>Belum Ada Data SOAPIE</h4>
        <p>Kunjungan ini belum memiliki catatan pemeriksaan medis (SOAPIE)</p>
    </div>
<?php else: ?>
    <table class="soapie-table">
        <thead>
            <tr>
                <th style="width:40px;">No</th>
                <th style="width:280px;">Informasi &amp; Vital Signs</th>
                <th>SOAPIE</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $no = 1;
        foreach($data_rows as $row):
            $vital = evaluateVitalSignsR($row);

            // Vital signs HTML
            $vitalHtml = '
            <div class="vital-signs">
                <div class="vital-item"><strong>Tensi</strong><span class="vital-item-value">: '.$row['tensi'].' mmHg '.getBadgeVitalR($vital['tensi']['label'], $vital['tensi']['status']).'</span></div>
                <div class="vital-item"><strong>Suhu</strong><span class="vital-item-value">: '.$row['suhu_tubuh'].' °C '.getBadgeVitalR($vital['suhu']['label'], $vital['suhu']['status']).'</span></div>
                <div class="vital-item"><strong>Nadi</strong><span class="vital-item-value">: '.$row['nadi'].' x/menit '.getBadgeVitalR($vital['nadi']['label'], $vital['nadi']['status']).'</span></div>
                <div class="vital-item"><strong>RR</strong><span class="vital-item-value">: '.$row['respirasi'].' x/menit '.getBadgeVitalR($vital['rr']['label'], $vital['rr']['status']).'</span></div>
                <div class="vital-item"><strong>TB/BB</strong><span class="vital-item-value">: '.$row['tinggi'].'/'.$row['berat'].' cm/kg</span></div>
                <div class="vital-item"><strong>SpO₂</strong><span class="vital-item-value">: '.$row['spo2'].' % '.getBadgeVitalR($vital['spo2']['label'], $vital['spo2']['status']).'</span></div>
                <div class="vital-item"><strong>GCS</strong><span class="vital-item-value">: '.$row['gcs'].' '.getBadgeVitalR($vital['gcs']['label'], $vital['gcs']['status']).'</span></div>
                <div class="vital-item"><strong>Kesadaran</strong><span class="vital-item-value">: '.$row['kesadaran'].'</span></div>
            </div>';

            // SOAPIE HTML
            if(isSOAPIEEmptyR($row)) {
                $soapieHtml = '<div class="no-soapie-notice">Belum ada catatan pemeriksaan medis (hanya vital signs)</div>';
            } else {
                $soapieHtml = '<div class="soapie-cell">';
                $sections = [
                    ['key' => 'keluhan',    'cls' => 's', 'label' => 'S', 'title' => 'SUBJECTIVE'],
                    ['key' => 'pemeriksaan','cls' => 'o', 'label' => 'O', 'title' => 'OBJECTIVE'],
                    ['key' => 'penilaian',  'cls' => 'a', 'label' => 'A', 'title' => 'ASSESSMENT'],
                    ['key' => 'rtl',        'cls' => 'p', 'label' => 'P', 'title' => 'PLAN'],
                    ['key' => 'instruksi',  'cls' => 'i', 'label' => 'I', 'title' => 'INTERVENTION'],
                    ['key' => 'evaluasi',   'cls' => 'e', 'label' => 'E', 'title' => 'EVALUATION'],
                ];
                foreach($sections as $sec) {
                    if(!empty(trim($row[$sec['key']]))) {
                        $soapieHtml .= '
                        <div class="soapie-item '.$sec['cls'].'-section">
                            <div class="soapie-item-header">
                                <span class="soapie-label '.$sec['cls'].'">'.$sec['label'].'</span>
                                <span class="soapie-label-text">'.$sec['title'].'</span>
                            </div>
                            <div class="soapie-content">'.formatSOAPIEContentR($row[$sec['key']]).'</div>
                        </div>';
                    }
                }
                $soapieHtml .= '</div>';
            }
        ?>
        <tr class="group-first">
            <td class="rowspan-cell"><?= $no ?></td>
            <td style="padding:12px;">
                <div style="margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #e0e0e0;">
                    <div style="font-size:12px;color:#333;font-weight:600;"><?= htmlspecialchars($row['no_rawat']) ?></div>
                    <div style="font-size:11px;color:#666;margin-top:2px;"><?= konversiTanggal($row['tgl_perawatan']) ?> <?= $row['jam_rawat'] ?></div>
                    <div style="font-size:11px;color:#999;margin-top:2px;"><?= htmlspecialchars($row['nm_poli']) ?></div>
                    <div style="font-size:11px;margin-top:2px;">
                        <span style="background:<?= ($row['_tipe']=='Rawat Inap') ? '#fee2e2' : '#dcfce7' ?>;color:<?= ($row['_tipe']=='Rawat Inap') ? '#991b1b' : '#166534' ?>;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;">
                            <?= $row['_tipe'] ?>
                        </span>
                    </div>
                    <div style="margin-top:4px;"><?= getBadgePetugasR($row['nama_petugas']) ?></div>
                </div>
                <?= $vitalHtml ?>
            </td>
            <td><?= $soapieHtml ?></td>
        </tr>
        <?php $no++; endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>
