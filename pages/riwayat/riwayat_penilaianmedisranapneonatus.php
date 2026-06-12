<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

// Get parameters
$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

// Function untuk badge status pemeriksaan
function getBadgeStatus($value) {
    if (empty($value)) {
        return '-';
    }
    
    $value_lower = strtolower(trim($value));
    
    if ($value_lower == 'normal') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">Normal</span>';
    } elseif ($value_lower == 'abnormal') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #ef4444; color: #fff;">Abnormal</span>';
    } elseif ($value_lower == 'tidak diperiksa') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #fbbf24; color: #1e293b;">Tidak Diperiksa</span>';
    } else {
        return htmlspecialchars($value);
    }
}

// Function untuk badge Ya/Tidak
function getBadgeYaTidak($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'ya') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">Ya</span>';
    } elseif ($v == 'tidak') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #94a3b8; color: #fff;">Tidak</span>';
    }
    return htmlspecialchars($value);
}

// Function untuk badge hasil lab (Negatif/Positif)
function getBadgeLab($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if (strpos($v, 'negatif') !== false) {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">' . htmlspecialchars($value) . '</span>';
    } elseif (strpos($v, 'positif') !== false) {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #ef4444; color: #fff;">' . htmlspecialchars($value) . '</span>';
    }
    return htmlspecialchars($value);
}

// Query data penilaian medis ranap neonatus
$query_medis = "
    SELECT 
        p.*,
        d.nm_dokter
    FROM penilaian_medis_ranap_neonatus p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian medis ranap neonatus tidak ditemukan</div>';
    exit;
}

// Loop data
while ($data = mysqli_fetch_assoc($result_medis)):
    // Format tanggal Indonesia
    $bulan = array(
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d', $tanggal_obj) . ' ' . 
                     $bulan[date('n', $tanggal_obj)] . ' ' . 
                     date('Y, H:i', $tanggal_obj);

    $tanggal_persalinan_obj = strtotime($data['tanggal_persalinan']);
    $tanggal_persalinan_format = date('d', $tanggal_persalinan_obj) . ' ' .
                                 $bulan[date('n', $tanggal_persalinan_obj)] . ' ' .
                                 date('Y, H:i', $tanggal_persalinan_obj);

    // Hitung total APGAR per menit
    $apgar1  = (int)$data['f1']  + (int)$data['u1']  + (int)$data['t1']  + (int)$data['r1']  + (int)$data['w1'];
    $apgar5  = (int)$data['f5']  + (int)$data['u5']  + (int)$data['t5']  + (int)$data['r5']  + (int)$data['w5'];
    $apgar10 = (int)$data['f10'] + (int)$data['u10'] + (int)$data['t10'] + (int)$data['r10'] + (int)$data['w10'];
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<div class="card mb-3 shadow-sm">
    <div class="card-body">

        <!-- HEADER INFO -->
        <div class="info-grid mb-2">
            <div class="info-item">
                <span class="info-label">Tanggal:</span>
                <span class="info-value"><?= $tanggal_format ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Dokter:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_dokter']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">No. RM Ibu:</span>
                <span class="info-value"><?= htmlspecialchars($data['no_rkm_medis_ibu']) ?: '-' ?></span>
            </div>
        </div>

        <!-- I. RIWAYAT IBU -->
        <div class="section-title">
            <i class="fa fa-female"></i> I. Riwayat Ibu
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">G/P/A:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['g']) ?: '-' ?>/<?= htmlspecialchars($data['p']) ?: '-' ?>/<?= htmlspecialchars($data['a']) ?: '-' ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Hidup:</span>
                <span class="info-value"><?= htmlspecialchars($data['hidup']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Usia Hamil:</span>
                <span class="info-value"><?= htmlspecialchars($data['usiahamil']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">HBsAg:</span>
                <span class="info-value"><?= getBadgeLab($data['hbsag']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">HIV:</span>
                <span class="info-value"><?= getBadgeLab($data['hiv']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Syphilis:</span>
                <span class="info-value"><?= getBadgeLab($data['syphilis']) ?></span>
            </div>
        </div>
        <div class="info-grid-vertical mt-2">
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Obstetri Ibu:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_obstetri_ibu']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Keterangan Riwayat Obstetri:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['keterangan_riwayat_obstetri_ibu'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Faktor Risiko Neonatal:</span>
                <span class="info-value"><?= htmlspecialchars($data['faktor_risiko_neonatal']) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['keterangan_faktor_risiko_neonatal'])): ?>
            <div class="info-item-vertical">
                <span class="info-label">Keterangan Faktor Risiko:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['keterangan_faktor_risiko_neonatal'])) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- II. DATA PERSALINAN -->
        <div class="section-title">
            <i class="fa fa-baby"></i> II. Data Persalinan
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Tanggal Persalinan:</span>
                <span class="info-value"><?= $tanggal_persalinan_format ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Bersalin Di:</span>
                <span class="info-value"><?= htmlspecialchars($data['bersalin_di']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Jenis Persalinan:</span>
                <span class="info-value"><?= htmlspecialchars($data['jenis_persalinan']) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['indikasi'])): ?>
            <div class="info-item">
                <span class="info-label">Indikasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['indikasi']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Aterm:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['aterm']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Bernafas:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['bernafas']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tonus Otot:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['tanus_otot']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Cairan Amnion:</span>
                <span class="info-value"><?= htmlspecialchars($data['cairan_amnion']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Inisiasi Menyusui:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['inisiasi_menyusui']) ?></span>
            </div>
        </div>

        <!-- III. SKOR APGAR -->
        <div class="section-title">
            <i class="fa fa-clipboard-list"></i> III. Skor APGAR
        </div>
        <div style="overflow-x: auto; margin-bottom: 8px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background-color: #f1f5f9;">
                        <th style="padding: 7px 10px; text-align: left; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Kriteria</th>
                        <th style="padding: 7px 10px; text-align: center; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Menit ke-1</th>
                        <th style="padding: 7px 10px; text-align: center; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Menit ke-5</th>
                        <th style="padding: 7px 10px; text-align: center; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0; white-space: nowrap;">Menit ke-10</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Frekuensi Jantung (F)</td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['f1']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['f5']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['f10']) ?: '-' ?></td>
                    </tr>
                    <tr style="background-color: #fafafa; border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Usaha Nafas (U)</td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['u1']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['u5']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['u10']) ?: '-' ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Tonus Otot (T)</td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['t1']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['t5']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['t10']) ?: '-' ?></td>
                    </tr>
                    <tr style="background-color: #fafafa; border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Reflek (R)</td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['r1']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['r5']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['r10']) ?: '-' ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Warna Kulit (W)</td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['w1']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['w5']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['w10']) ?: '-' ?></td>
                    </tr>
                    <tr style="background: linear-gradient(135deg, #fee2e2, #fecaca);">
                        <td style="padding: 7px 10px; color: #991b1b; font-weight: 700;">TOTAL</td>
                        <td style="padding: 7px 10px; text-align: center; font-weight: 700; color: #991b1b;"><?= $apgar1 ?></td>
                        <td style="padding: 7px 10px; text-align: center; font-weight: 700; color: #991b1b;"><?= $apgar5 ?></td>
                        <td style="padding: 7px 10px; text-align: center; font-weight: 700; color: #991b1b;"><?= $apgar10 ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- IV. DOWN SCORE (Gangguan Napas) -->
        <div class="section-title">
            <i class="fa fa-lungs"></i> IV. Down Score (Penilaian Gangguan Napas)
        </div>
        <div style="overflow-x: auto; margin-bottom: 8px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr style="background-color: #f1f5f9;">
                        <th style="padding: 7px 10px; text-align: left; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0;">Kriteria</th>
                        <th style="padding: 7px 10px; text-align: left; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0;">Hasil</th>
                        <th style="padding: 7px 10px; text-align: center; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0;">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Frekuensi Napas</td>
                        <td style="padding: 6px 10px; color: #1e293b;"><?= htmlspecialchars($data['frekuensi_napas']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['nilai_frekuensi_napas']) ?: '-' ?></td>
                    </tr>
                    <tr style="background-color: #fafafa; border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Retraksi</td>
                        <td style="padding: 6px 10px; color: #1e293b;"><?= htmlspecialchars($data['retraksi']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['nilai_retraksi']) ?: '-' ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Sianosis</td>
                        <td style="padding: 6px 10px; color: #1e293b;"><?= htmlspecialchars($data['sianosis']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['nilai_sianosis']) ?: '-' ?></td>
                    </tr>
                    <tr style="background-color: #fafafa; border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Jalan Masuk Udara</td>
                        <td style="padding: 6px 10px; color: #1e293b;"><?= htmlspecialchars($data['jalan_masuk_udara']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['nilai_jalan_masuk_udara']) ?: '-' ?></td>
                    </tr>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 6px 10px; color: #64748b; font-weight: 600;">Grunting</td>
                        <td style="padding: 6px 10px; color: #1e293b;"><?= htmlspecialchars($data['grunting']) ?: '-' ?></td>
                        <td style="padding: 6px 10px; text-align: center; color: #1e293b;"><?= htmlspecialchars($data['nilai_grunting']) ?: '-' ?></td>
                    </tr>
                    <tr style="background: linear-gradient(135deg, #fee2e2, #fecaca);">
                        <td colspan="2" style="padding: 7px 10px; color: #991b1b; font-weight: 700;">
                            TOTAL DOWN SCORE
                            <?php if (!empty($data['keterangan_down_Score'])): ?>
                            — <span style="font-weight: 500; font-size: 11px;"><?= htmlspecialchars($data['keterangan_down_Score']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 7px 10px; text-align: center; font-weight: 700; color: #991b1b;"><?= htmlspecialchars($data['total_down_score']) ?: '-' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- V. TANDA VITAL & ANTROPOMETRI -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> V. Tanda Vital &amp; Antropometri
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Nadi:</span>
                <span class="info-value"><?= htmlspecialchars($data['nadi']) ?: '-' ?> x/menit</span>
            </div>
            <div class="info-item">
                <span class="info-label">RR:</span>
                <span class="info-value"><?= htmlspecialchars($data['rr']) ?: '-' ?> x/menit</span>
            </div>
            <div class="info-item">
                <span class="info-label">Suhu:</span>
                <span class="info-value"><?= htmlspecialchars($data['suhu']) ?: '-' ?> °C</span>
            </div>
            <div class="info-item">
                <span class="info-label">Saturasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['saturasi']) ?: '-' ?> %</span>
            </div>
            <div class="info-item">
                <span class="info-label">BB:</span>
                <span class="info-value"><?= htmlspecialchars($data['bb']) ?: '-' ?> gram</span>
            </div>
            <div class="info-item">
                <span class="info-label">PB:</span>
                <span class="info-value"><?= htmlspecialchars($data['pb']) ?: '-' ?> cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">LK:</span>
                <span class="info-value"><?= htmlspecialchars($data['lk']) ?: '-' ?> cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">LD:</span>
                <span class="info-value"><?= htmlspecialchars($data['ld']) ?: '-' ?> cm</span>
            </div>
        </div>

        <!-- VI. PEMERIKSAAN FISIK -->
        <div class="section-title">
            <i class="fa fa-stethoscope"></i> VI. Pemeriksaan Fisik
        </div>

        <!-- Keadaan Umum & Kulit -->
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Keadaan Umum:</span>
                <span class="info-value"><?= getBadgeStatus($data['keadaan_umum']) ?></span>
            </div>
            <?php if (!empty($data['keterangan_keadaan_umum'])): ?>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Ket. Keadaan Umum:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_keadaan_umum']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Kulit:</span>
                <span class="info-value"><?= getBadgeStatus($data['kulit']) ?></span>
            </div>
            <?php if (!empty($data['keterangan_kulit'])): ?>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Ket. Kulit:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_kulit']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pemeriksaan Per Organ -->
        <div class="info-grid" style="margin-top: 4px;">
            <?php
            $organs = [
                'kepala'       => 'Kepala',
                'mata'         => 'Mata',
                'telinga'      => 'Telinga',
                'hidung'       => 'Hidung',
                'mulut'        => 'Mulut',
                'tenggorokan'  => 'Tenggorokan',
                'leher'        => 'Leher',
                'thorax'       => 'Thoraks',
                'abdomen'      => 'Abdomen',
                'genitalia'    => 'Genitalia',
                'anus'         => 'Anus',
                'muskulos'     => 'Muskulos',
                'ekstrimitas'  => 'Ekstremitas',
                'paru'         => 'Paru',
                'refleks'      => 'Refleks',
            ];
            foreach ($organs as $col => $label):
                $ket_col = 'keterangan_' . $col;
            ?>
            <div class="info-item" style="<?= !empty($data[$ket_col]) ? 'grid-column: span 3;' : '' ?>">
                <span class="info-label"><?= $label ?>:</span>
                <span class="info-value">
                    <?= getBadgeStatus($data[$col]) ?>
                    <?php if (!empty($data[$ket_col])): ?>
                    <span style="margin-left: 6px; font-size: 11px; color: #475569;">— <?= htmlspecialchars($data[$ket_col]) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($data['kelainan_lainnya'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Kelainan Lainnya:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['kelainan_lainnya'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- VII. PEMERIKSAAN PENUNJANG -->
        <div class="section-title">
            <i class="fa fa-vial"></i> VII. Pemeriksaan Penunjang
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <span class="info-label">Pemeriksaan Regional:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['pemeriksaan_regional'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Laboratorium:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['lab'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Radiologi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['radiologi'])) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['penunjanglainnya'])): ?>
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-label">Penunjang Lainnya:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['penunjanglainnya'])) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- VIII. DIAGNOSIS -->
        <div class="section-title">
            <i class="fa fa-file-medical"></i> VIII. Diagnosis
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['diagnosis'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- IX. TATALAKSANA -->
        <div class="section-title">
            <i class="fa fa-procedures"></i> IX. Tatalaksana
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['tata'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- X. EDUKASI -->
        <?php if (!empty($data['edukasi'])): ?>
        <div class="section-title">
            <i class="fa fa-chalkboard-teacher"></i> X. Edukasi
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['edukasi'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php endwhile; ?>