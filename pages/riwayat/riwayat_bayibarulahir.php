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

// Function untuk badge Ada/Tidak Ada
function getBadgeAdaTidakAda($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'ada') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #f59e0b; color: #fff;">Ada</span>';
    } elseif ($v == 'tidak ada') {
        return '<span style="display: inline-block; padding: 3px 8px; font-size: 11px; font-weight: 600; border-radius: 3px; background-color: #10b981; color: #fff;">Tidak Ada</span>';
    }
    return htmlspecialchars($value);
}

// Query data penilaian bayi baru lahir
$query_medis = "
    SELECT 
        p.*,
        d.nm_dokter
    FROM penilaian_bayi_baru_lahir p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian bayi baru lahir tidak ditemukan</div>';
    exit;
}

// Loop data
while ($data = mysqli_fetch_assoc($result_medis)):
    $bulan = array(
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d', $tanggal_obj) . ' ' .
                     $bulan[date('n', $tanggal_obj)] . ' ' .
                     date('Y, H:i', $tanggal_obj);

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

        <!-- I. RIWAYAT MATERNAL -->
        <div class="section-title">
            <i class="fa fa-female"></i> I. Riwayat Maternal
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Penyakit Ibu:</span>
                <span class="info-value"><?= getBadgeAdaTidakAda($data['penyakit_diderita_ibu']) ?></span>
            </div>
            <?php if (!empty($data['keterangan_penyakit_diderita_ibu'])): ?>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Ket. Penyakit Ibu:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_penyakit_diderita_ibu']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Obat Selama Kehamilan:</span>
                <span class="info-value"><?= htmlspecialchars($data['obat_dikonsumsi_selama_kehamilan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Perawatan Antenatal:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['perawatan_antenatal']) ?></span>
            </div>
            <?php if (!empty($data['keterangan_perawatan_antenatal'])): ?>
            <div class="info-item">
                <span class="info-label">Ket. Antenatal:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_perawatan_antenatal']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Terdaftar Ekohort:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['terdaftar_ekohort']) ?></span>
            </div>
            <?php if (!empty($data['keterangan_terdaftar_ekohort'])): ?>
            <div class="info-item">
                <span class="info-label">Ket. Ekohort:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_terdaftar_ekohort']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Penyulit Kehamilan:</span>
                <span class="info-value"><?= htmlspecialchars($data['penyulit_kehamilan']) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['keterangan_penyulit_kehamilan'])): ?>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Ket. Penyulit:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_penyulit_kehamilan']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Alergi:</span>
                <span class="info-value"><?= htmlspecialchars($data['alergi']) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['keterangan_lainnya_riwayat_maternal'])): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Keterangan Lainnya:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['keterangan_lainnya_riwayat_maternal'])) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- II. DATA KEHAMILAN & PERSALINAN -->
        <div class="section-title">
            <i class="fa fa-baby"></i> II. Data Kehamilan &amp; Persalinan
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Umur Kehamilan:</span>
                <span class="info-value"><?= htmlspecialchars($data['umur_kehamilan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kehamilan:</span>
                <span class="info-value"><?= htmlspecialchars($data['kehamilan']) ?: '-' ?>
                    <?php if (!empty($data['keterangan_kehamilan'])): ?>
                    <span style="font-size: 11px; color: #475569;"> — <?= htmlspecialchars($data['keterangan_kehamilan']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Urutan Kehamilan:</span>
                <span class="info-value">ke-<?= htmlspecialchars($data['urutan_kehamilan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Letak Bayi:</span>
                <span class="info-value"><?= htmlspecialchars($data['letak_bayi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Macam Persalinan:</span>
                <span class="info-value"><?= htmlspecialchars($data['macam_persalinan']) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['keterangan_macam_persalinan'])): ?>
            <div class="info-item">
                <span class="info-label">Ket. Persalinan:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_macam_persalinan']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Indikasi Persalinan Operatif:</span>
                <span class="info-value"><?= htmlspecialchars($data['indikasi_persalinan_operatif']) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['keterangan_indikasi_persalinan_operatif'])): ?>
            <div class="info-item">
                <span class="info-label">Ket. Indikasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_indikasi_persalinan_operatif']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Lama Gawat Janin:</span>
                <span class="info-value"><?= htmlspecialchars($data['lama_gawat_janin']) ?: '-' ?> menit</span>
            </div>
            <?php if (!empty($data['obat_selama_persalinan'])): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Obat Selama Persalinan:</span>
                <span class="info-value"><?= htmlspecialchars($data['obat_selama_persalinan']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Air Ketuban & Plasenta -->
        <div class="info-grid" style="margin-top: 6px;">
            <div class="info-item">
                <span class="info-label">Ketuban Pecah:</span>
                <span class="info-value">
                    <?php if (!empty($data['jam_ketuban_pecah']) || !empty($data['menit_ketuban_pecah'])): ?>
                        <?= htmlspecialchars($data['jam_ketuban_pecah']) ?: '0' ?> jam
                        <?= htmlspecialchars($data['menit_ketuban_pecah']) ?: '0' ?> menit
                    <?php else: ?>-<?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Jumlah Air Ketuban:</span>
                <span class="info-value"><?= htmlspecialchars($data['jumlah_air_ketuban']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Warna Air Ketuban:</span>
                <span class="info-value"><?= htmlspecialchars($data['warna_air_ketuban']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Bau Air Ketuban:</span>
                <span class="info-value"><?= htmlspecialchars($data['bau_air_ketuban']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Berat Plasenta:</span>
                <span class="info-value"><?= htmlspecialchars($data['berat_placenta']) ?: '-' ?> gram</span>
            </div>
            <div class="info-item">
                <span class="info-label">Kelainan Plasenta:</span>
                <span class="info-value"><?= htmlspecialchars($data['kelainan_placenta']) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['keterangan_lainnya_riwayat_persalinan'])): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Keterangan Lainnya Persalinan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['keterangan_lainnya_riwayat_persalinan'])) ?></span>
            </div>
            <?php endif; ?>
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

        <!-- IV. ANTROPOMETRI & KEADAAN SAAT LAHIR -->
        <div class="section-title">
            <i class="fa fa-weight"></i> IV. Antropometri &amp; Keadaan Saat Lahir
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">BB Lahir:</span>
                <span class="info-value"><?= htmlspecialchars($data['bblahir']) ?: '-' ?> gram</span>
            </div>
            <div class="info-item">
                <span class="info-label">Panjang Badan:</span>
                <span class="info-value"><?= htmlspecialchars($data['panjang_badan']) ?: '-' ?> cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">Lingkar Kepala:</span>
                <span class="info-value"><?= htmlspecialchars($data['lingkar_kepala']) ?: '-' ?> cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">Lingkar Dada:</span>
                <span class="info-value"><?= htmlspecialchars($data['lingkar_dada']) ?: '-' ?> cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">Resusitasi Saat Lahir:</span>
                <span class="info-value"><?= htmlspecialchars($data['resusitasi_saat_lahir']) ?: '-' ?></span>
            </div>
            <?php if (!empty($data['keterangan_resusitasi_saat_lahir'])): ?>
            <div class="info-item">
                <span class="info-label">Ket. Resusitasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_resusitasi_saat_lahir']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['obat_diberikan_saat_lahir'])): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Obat Diberikan Saat Lahir:</span>
                <span class="info-value"><?= htmlspecialchars($data['obat_diberikan_saat_lahir']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['keterangan_lainnya_keadaan_bayi'])): ?>
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Keterangan Lainnya Keadaan Bayi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['keterangan_lainnya_keadaan_bayi'])) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- V. PEMERIKSAAN FISIK BAYI -->
        <div class="section-title">
            <i class="fa fa-stethoscope"></i> V. Pemeriksaan Fisik Bayi
        </div>

        <!-- Kondisi Umum & Kulit -->
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Kondisi Umum:</span>
                <span class="info-value"><?= getBadgeStatus($data['kondisi_umum']) ?></span>
            </div>
            <?php if (!empty($data['keterangan_kondisi_umum'])): ?>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Ket. Kondisi Umum:</span>
                <span class="info-value"><?= htmlspecialchars($data['keterangan_kondisi_umum']) ?></span>
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
                'kepala'              => 'Kepala',
                'leher'               => 'Leher',
                'mata'                => 'Mata',
                'hidung'              => 'Hidung',
                'telinga'             => 'Telinga',
                'dada'                => 'Dada',
                'paru'                => 'Paru',
                'jantung'             => 'Jantung',
                'perut'               => 'Perut',
                'tali_pusat'          => 'Tali Pusat',
                'alat_kelamin'        => 'Alat Kelamin',
                'ruas_tulang_belakang'=> 'Ruas Tulang Belakang',
                'extrimitas'          => 'Ekstremitas',
                'anus'                => 'Anus',
                'refleks'             => 'Refleks',
                'denyut_femoral'      => 'Denyut Femoral',
            ];
            foreach ($organs as $col => $label):
                $ket_col = 'keterangan_' . $col;
                $has_ket = !empty($data[$ket_col]);
            ?>
            <div class="info-item" style="<?= $has_ket ? 'grid-column: span 3;' : '' ?>">
                <span class="info-label"><?= $label ?>:</span>
                <span class="info-value">
                    <?= getBadgeStatus($data[$col]) ?>
                    <?php if ($has_ket): ?>
                    <span style="margin-left: 6px; font-size: 11px; color: #475569;">— <?= htmlspecialchars($data[$ket_col]) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($data['pemeriksaan_fisik_lainnya'])): ?>
        <div class="info-grid mt-2">
            <div class="info-item" style="grid-column: 1 / -1;">
                <span class="info-label">Pemeriksaan Fisik Lainnya:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['pemeriksaan_fisik_lainnya'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- VI. PEMERIKSAAN PENUNJANG -->
        <?php if (!empty($data['pemeriksaan_penunjang'])): ?>
        <div class="section-title">
            <i class="fa fa-vial"></i> VI. Pemeriksaan Penunjang
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['pemeriksaan_penunjang'])) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- VII. DIAGNOSIS -->
        <div class="section-title">
            <i class="fa fa-file-medical"></i> VII. Diagnosis
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['diagnosa'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- VIII. TATALAKSANA -->
        <div class="section-title">
            <i class="fa fa-procedures"></i> VIII. Tatalaksana
        </div>
        <div class="info-grid">
            <div class="info-item-vertical" style="grid-column: 1 / -1;">
                <span class="info-value"><?= nl2br(htmlspecialchars($data['tatalaksana'])) ?: '-' ?></span>
            </div>
        </div>

    </div>
</div>

<?php endwhile; ?>