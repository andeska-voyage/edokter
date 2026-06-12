<?php
include "../../conf/conf.php";
header("Content-Type: text/html; charset=UTF-8");

$no_rawat = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';
$no_rm    = isset($_REQUEST['no_rm']) ? $_REQUEST['no_rm'] : '';

if (empty($no_rawat)) {
    echo '<div class="alert alert-warning m-3">Parameter tidak lengkap</div>';
    exit;
}

function getBadgeStatus($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'normal')        return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#10b981;color:#fff;">Normal</span>';
    if ($v == 'abnormal')      return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#ef4444;color:#fff;">Abnormal</span>';
    if ($v == 'tidak diperiksa') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#fbbf24;color:#1e293b;">Tidak Diperiksa</span>';
    return htmlspecialchars($value);
}

function getBadgeYaTidak($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'ya')    return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#10b981;color:#fff;">Ya</span>';
    if ($v == 'tidak') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#94a3b8;color:#fff;">Tidak</span>';
    return htmlspecialchars($value);
}

function getBadgeAdaTidakAda($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'ada')      return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#f59e0b;color:#fff;">Ada</span>';
    if ($v == 'tidak ada') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#10b981;color:#fff;">Tidak Ada</span>';
    if ($v == 'tak')      return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background:#10b981;color:#fff;">TAK</span>';
    return htmlspecialchars($value);
}

// Helper: tampilkan nilai + keterangan inline
function rowKet($label, $val, $ket = '') {
    $out  = '<div class="info-item"><span class="info-label">' . $label . ':</span><span class="info-value">';
    $out .= htmlspecialchars($val) ?: '-';
    if (!empty($ket)) $out .= '<span style="font-size:11px;color:#475569;margin-left:4px;">— ' . htmlspecialchars($ket) . '</span>';
    $out .= '</span></div>';
    return $out;
}

$query_medis = "
    SELECT p.*, d.nm_dokter
    FROM penilaian_awal_keperawatan_ranap_bayi p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";
$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian keperawatan ranap bayi tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_medis)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl   = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl).' '.$bulan[date('n',$tgl)].' '.date('Y, H:i',$tgl);

    // Hitung total nyeri FLACC
    $nyeri_total = (int)$data['nyeri_nilai_wajah'] + (int)$data['nyeri_nilai_kaki']
                 + (int)$data['nyeri_nilai_aktifitas'] + (int)$data['nyeri_nilai_menangis']
                 + (int)$data['nyeri_nilai_bersuara'];

    // Total humpty dumpty (sudah di DB)
    $hd_total = (int)$data['penilaian_humptydumpty_totalnilai'];

    // Total gizi
    $gizi_total = (int)$data['nilai_gizi1'] + (int)$data['nilai_gizi2']
                + (int)$data['nilai_gizi3'] + (int)$data['nilai_gizi4'];
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<div class="card mb-3 shadow-sm">
<div class="card-body">

    <!-- HEADER -->
    <div class="info-grid mb-2">
        <div class="info-item">
            <span class="info-label">Tanggal:</span>
            <span class="info-value"><?= $tanggal_format ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Dokter/Perawat:</span>
            <span class="info-value"><?= htmlspecialchars($data['nm_dokter']) ?: '-' ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Sumber Informasi:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['informasi']) ?: '-' ?>
                <?php if (!empty($data['ket_informasi'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['ket_informasi']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Tiba di Ruang Rawat:</span>
            <span class="info-value"><?= htmlspecialchars($data['tiba_diruang_rawat']) ?: '-' ?></span>
        </div>
    </div>

    <!-- I. RIWAYAT KESEHATAN -->
    <div class="section-title"><i class="fa fa-notes-medical"></i> I. Riwayat Kesehatan</div>
    <div class="info-grid-vertical">
        <div class="info-item-vertical">
            <span class="info-label">Riwayat Penyakit Sekarang:</span>
            <span class="info-value"><?= nl2br(htmlspecialchars($data['rps'])) ?: '-' ?></span>
        </div>
        <div class="info-item-vertical">
            <span class="info-label">Riwayat Penyakit Dahulu:</span>
            <span class="info-value"><?= nl2br(htmlspecialchars($data['rpd'])) ?: '-' ?></span>
        </div>
        <div class="info-item-vertical">
            <span class="info-label">Riwayat Penyakit Keluarga:</span>
            <span class="info-value"><?= nl2br(htmlspecialchars($data['rpk'])) ?: '-' ?></span>
        </div>
        <div class="info-item-vertical">
            <span class="info-label">Riwayat Penggunaan Obat:</span>
            <span class="info-value"><?= nl2br(htmlspecialchars($data['rpo'])) ?: '-' ?></span>
        </div>
        <div class="info-item-vertical">
            <span class="info-label">Alergi:</span>
            <span class="info-value"><?= htmlspecialchars($data['alergi']) ?: '-' ?></span>
        </div>
    </div>

    <!-- II. TUMBUH KEMBANG -->
    <div class="section-title"><i class="fa fa-child"></i> II. Riwayat Tumbuh Kembang</div>
    <div class="info-grid">
        <?php
        $tk = [
            'tumbuh_kembang_tengkurap'      => 'Tengkurap',
            'tumbuh_kembang_duduk'          => 'Duduk',
            'tumbuh_kembang_berdiri'        => 'Berdiri',
            'tumbuh_kembang_gigi_pertama'   => 'Gigi Pertama',
            'tumbuh_kembang_berjalan'       => 'Berjalan',
            'tumbuh_kembang_bicara'         => 'Bicara',
            'tumbuh_kembang_membaca'        => 'Membaca',
            'tumbuh_kembang_menulis'        => 'Menulis',
        ];
        foreach ($tk as $col => $lbl):
        ?>
        <div class="info-item">
            <span class="info-label"><?= $lbl ?>:</span>
            <span class="info-value"><?= htmlspecialchars($data[$col]) ?: '-' ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!empty($data['tumbuh_kembang_gangguan_emosi'])): ?>
        <div class="info-item" style="grid-column:1/-1;">
            <span class="info-label">Gangguan Emosi:</span>
            <span class="info-value"><?= htmlspecialchars($data['tumbuh_kembang_gangguan_emosi']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- III. RIWAYAT PERSALINAN -->
    <div class="section-title"><i class="fa fa-baby"></i> III. Riwayat Persalinan</div>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Anak Ke:</span>
            <span class="info-value"><?= htmlspecialchars($data['persalinan_anakke']) ?: '-' ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Dari Saudara:</span>
            <span class="info-value"><?= htmlspecialchars($data['persalinan_darisaudara']) ?: '-' ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Cara Kelahiran:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['persalinan_kelahiran']) ?: '-' ?>
                <?php if (!empty($data['persalinan_kelahiran_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['persalinan_kelahiran_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Umur Kelahiran:</span>
            <span class="info-value"><?= htmlspecialchars($data['persalinan_umur_kelahiran']) ?: '-' ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Kelainan Bawaan:</span>
            <span class="info-value">
                <?= getBadgeAdaTidakAda($data['persalinan_kelainan_bawaan']) ?>
                <?php if (!empty($data['persalinan_kelainan_bawaan_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['persalinan_kelainan_bawaan_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">BB Lahir:</span>
            <span class="info-value"><?= htmlspecialchars($data['persalinan_bb_lahir']) ?: '-' ?> gram</span>
        </div>
        <?php if (!empty($data['persalinan_lainnya'])): ?>
        <div class="info-item" style="grid-column:1/-1;">
            <span class="info-label">Lainnya:</span>
            <span class="info-value"><?= nl2br(htmlspecialchars($data['persalinan_lainnya'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- IV. PEMERIKSAAN FISIK UMUM -->
    <div class="section-title"><i class="fa fa-heartbeat"></i> IV. Pemeriksaan Fisik Umum</div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">Kesadaran:</span><span class="info-value"><?= htmlspecialchars($data['fisik_kesadaran']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">GCS:</span><span class="info-value"><?= htmlspecialchars($data['fisik_gcs']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">TD:</span><span class="info-value"><?= htmlspecialchars($data['fisik_td']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">RR:</span><span class="info-value"><?= htmlspecialchars($data['fisik_rr']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Suhu:</span><span class="info-value"><?= htmlspecialchars($data['fisik_suhu']) ?: '-' ?> °C</span></div>
        <div class="info-item"><span class="info-label">Nadi:</span><span class="info-value"><?= htmlspecialchars($data['fisik_nadi']) ?: '-' ?> x/mnt</span></div>
        <div class="info-item"><span class="info-label">BB:</span><span class="info-value"><?= htmlspecialchars($data['fisik_bb']) ?: '-' ?> kg</span></div>
        <div class="info-item"><span class="info-label">TB:</span><span class="info-value"><?= htmlspecialchars($data['fisik_tb']) ?: '-' ?> cm</span></div>
        <div class="info-item"><span class="info-label">LP:</span><span class="info-value"><?= htmlspecialchars($data['fisik_lp']) ?: '-' ?> cm</span></div>
        <div class="info-item"><span class="info-label">LK:</span><span class="info-value"><?= htmlspecialchars($data['fisik_lk']) ?: '-' ?> cm</span></div>
        <div class="info-item"><span class="info-label">LD:</span><span class="info-value"><?= htmlspecialchars($data['fisik_ld']) ?: '-' ?> cm</span></div>
    </div>

    <!-- V. SISTEM SARAF PUSAT -->
    <div class="section-title"><i class="fa fa-brain"></i> V. Sistem Saraf Pusat</div>
    <div class="info-grid">
        <?php
        $saraf = [
            'saraf_pusat_kepala'   => ['Kepala',   'saraf_pusat_kepala_keterangan'],
            'saraf_pusat_wajah'    => ['Wajah',    'saraf_pusat_wajah_keterangan'],
            'saraf_pusat_leher'    => ['Leher',    null],
            'saraf_pusat_kejang'   => ['Kejang',   'saraf_pusat_kejang_keterangan'],
            'saraf_pusat_sensorik' => ['Sensorik', null],
        ];
        foreach ($saraf as $col => [$lbl, $ket_col]):
        ?>
        <div class="info-item">
            <span class="info-label"><?= $lbl ?>:</span>
            <span class="info-value">
                <?= htmlspecialchars($data[$col]) ?: '-' ?>
                <?php if ($ket_col && !empty($data[$ket_col])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data[$ket_col]) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- VI. KARDIOVASKULER -->
    <div class="section-title"><i class="fa fa-heart"></i> VI. Kardiovaskuler</div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">Pulsasi:</span><span class="info-value"><?= htmlspecialchars($data['kardiovaskuler_pulsasi']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Sirkulasi:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['kardiovaskuler_sirkulasi']) ?: '-' ?>
                <?php if (!empty($data['kardiovaskuler_sirkulasi_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['kardiovaskuler_sirkulasi_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item"><span class="info-label">Denyut Nadi:</span><span class="info-value"><?= htmlspecialchars($data['kardiovaskuler_denyut_nadi']) ?: '-' ?></span></div>
    </div>

    <!-- VII. RESPIRASI -->
    <div class="section-title"><i class="fa fa-lungs"></i> VII. Respirasi</div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">Retraksi:</span><span class="info-value"><?= htmlspecialchars($data['respirasi_retraksi']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Pola Nafas:</span><span class="info-value"><?= htmlspecialchars($data['respirasi_pola_nafas']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Suara Nafas:</span><span class="info-value"><?= htmlspecialchars($data['respirasi_suara_nafas']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Batuk:</span><span class="info-value"><?= htmlspecialchars($data['respirasi_batuk']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Volume:</span><span class="info-value"><?= htmlspecialchars($data['respirasi_volume']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Jenis Pernapasan:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['respirasi_jenis_pernapasan']) ?: '-' ?>
                <?php if (!empty($data['respirasi_jenis_pernapasan_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['respirasi_jenis_pernapasan_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item"><span class="info-label">Irama:</span><span class="info-value"><?= htmlspecialchars($data['respirasi_irama']) ?: '-' ?></span></div>
    </div>

    <!-- VIII. GASTROINTESTINAL -->
    <div class="section-title"><i class="fa fa-stomach"></i> VIII. Gastrointestinal</div>
    <div class="info-grid">
        <?php
        $gastro = [
            'gastro_mulut'       => ['Mulut',       'gastro_mulut_keterangan'],
            'gastro_tenggorakan' => ['Tenggorokan',  'gastro_tenggorakan_keterangan'],
            'gastro_lidah'       => ['Lidah',        'gastro_lidah_keterangan'],
            'gastro_abdomen'     => ['Abdomen',      'gastro_abdomen_keterangan'],
            'gastro_gigi'        => ['Gigi',         'gastro_gigi_keterangan'],
            'gastro_usus'        => ['Usus',         null],
            'gastro_anus'        => ['Anus',         null],
        ];
        foreach ($gastro as $col => [$lbl, $ket_col]):
        ?>
        <div class="info-item">
            <span class="info-label"><?= $lbl ?>:</span>
            <span class="info-value">
                <?= htmlspecialchars($data[$col]) ?: '-' ?>
                <?php if ($ket_col && !empty($data[$ket_col])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data[$ket_col]) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- IX. NEUROLOGI -->
    <div class="section-title"><i class="fa fa-project-diagram"></i> IX. Neurologi</div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">Sensorik:</span><span class="info-value"><?= htmlspecialchars($data['neurologi_sensorik']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Penglihatan:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['neurologi_pengilihatan']) ?: '-' ?>
                <?php if (!empty($data['neurologi_penglihatan_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['neurologi_penglihatan_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item"><span class="info-label">Alat Bantu Penglihatan:</span><span class="info-value"><?= htmlspecialchars($data['neurologi_alat_bantu_penglihatan']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Motorik:</span><span class="info-value"><?= htmlspecialchars($data['neurologi_motorik']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Pendengaran:</span><span class="info-value"><?= htmlspecialchars($data['neurologi_pendengaran']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Bicara:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['neurologi_bicara']) ?: '-' ?>
                <?php if (!empty($data['neurologi_bicara_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['neurologi_bicara_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item"><span class="info-label">Tonus Otot:</span><span class="info-value"><?= htmlspecialchars($data['neurologi_otot']) ?: '-' ?></span></div>
    </div>

    <!-- X. INTEGUMEN -->
    <div class="section-title"><i class="fa fa-hand-paper"></i> X. Integumen</div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">Kulit:</span><span class="info-value"><?= htmlspecialchars($data['inte_kulit']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Warna Kulit:</span><span class="info-value"><?= htmlspecialchars($data['inte_warna_kulit']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Turgor:</span><span class="info-value"><?= htmlspecialchars($data['inte_tugor']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Dekubitus:</span><span class="info-value"><?= htmlspecialchars($data['inte_decubi']) ?: '-' ?></span></div>
    </div>

    <!-- XI. MUSKULOSKELETAL -->
    <div class="section-title"><i class="fa fa-bone"></i> XI. Muskuloskeletal</div>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Oedema:</span>
            <span class="info-value">
                <?= getBadgeAdaTidakAda($data['musku_odema']) ?>
                <?php if (!empty($data['musku_odema_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['musku_odema_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item"><span class="info-label">Pergerakan Sendi:</span><span class="info-value"><?= htmlspecialchars($data['musku_pegerakansendi']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Kekuatan Otot:</span><span class="info-value"><?= htmlspecialchars($data['musku_otot']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Fraktur:</span>
            <span class="info-value">
                <?= getBadgeAdaTidakAda($data['musku_fraktur']) ?>
                <?php if (!empty($data['musku_fraktur_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['musku_fraktur_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Nyeri Sendi:</span>
            <span class="info-value">
                <?= getBadgeAdaTidakAda($data['musku_nyerisendi']) ?>
                <?php if (!empty($data['musku_nyerisendi_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['musku_nyerisendi_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- XII. ELIMINASI -->
    <div class="section-title"><i class="fa fa-tint"></i> XII. Eliminasi</div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">BAB Frekuensi:</span><span class="info-value"><?= htmlspecialchars($data['eliminasi_bab_frekuensi']) ?: '-' ?>x <?= htmlspecialchars($data['eliminasi_bab_frekuensi_per']) ?: '' ?></span></div>
        <div class="info-item"><span class="info-label">BAB Konsistensi:</span><span class="info-value"><?= htmlspecialchars($data['eliminasi_bab_konsistesi']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">BAB Warna:</span><span class="info-value"><?= htmlspecialchars($data['eliminasi_bab_warna']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">BAK Frekuensi:</span><span class="info-value"><?= htmlspecialchars($data['eliminasi_bak_frekuensi']) ?: '-' ?>x <?= htmlspecialchars($data['eliminasi_bak_frekuensi_per']) ?: '' ?></span></div>
        <div class="info-item"><span class="info-label">BAK Warna:</span><span class="info-value"><?= htmlspecialchars($data['eliminasi_bak_warna']) ?: '-' ?></span></div>
        <?php if (!empty($data['eliminasi_bak_lainlain'])): ?>
        <div class="info-item"><span class="info-label">Eliminasi Lainnya:</span><span class="info-value"><?= htmlspecialchars($data['eliminasi_bak_lainlain']) ?></span></div>
        <?php endif; ?>
    </div>

    <!-- XIII. PSIKO-SOSIAL-BUDAYA -->
    <div class="section-title"><i class="fa fa-users"></i> XIII. Psiko-Sosial-Budaya</div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">Kondisi Psikologis:</span><span class="info-value"><?= htmlspecialchars($data['psiko_kondisi']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Perilaku:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['psiko_perilaku']) ?: '-' ?>
                <?php if (!empty($data['psiko_perilaku_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['psiko_perilaku_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item"><span class="info-label">Gangguan Jiwa:</span><span class="info-value"><?= getBadgeYaTidak($data['psiko_gangguan_jiwa']) ?></span></div>
        <div class="info-item"><span class="info-label">Hubungan Pasien:</span><span class="info-value"><?= htmlspecialchars($data['psiko_hubungan_pasien']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Tinggal Dengan:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['psiko_tinggal_dengan']) ?: '-' ?>
                <?php if (!empty($data['psiko_tinggal_dengan_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['psiko_tinggal_dengan_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item"><span class="info-label">Pekerjaan PJ:</span><span class="info-value"><?= htmlspecialchars($data['psiko_pekerjaan_pj']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Nilai Kepercayaan:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['psiko_nilai_kepercayaan']) ?: '-' ?>
                <?php if (!empty($data['psiko_nilai_kepercayaan_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['psiko_nilai_kepercayaan_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item"><span class="info-label">Pendidikan PJ:</span><span class="info-value"><?= htmlspecialchars($data['psiko_pendidikan_pj']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Edukasi Kepada:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['psiko_edukasi']) ?: '-' ?>
                <?php if (!empty($data['psiko_edukasi_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['psiko_edukasi_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- XIV. KEBUTUHAN EDUKASI -->
    <div class="section-title"><i class="fa fa-chalkboard-teacher"></i> XIV. Kebutuhan Edukasi</div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">Bahasa:</span><span class="info-value"><?= htmlspecialchars($data['edukasi_bahasa']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Baca Tulis:</span><span class="info-value"><?= htmlspecialchars($data['edukasi_baca_tulis']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Penerjemah:</span>
            <span class="info-value">
                <?= getBadgeYaTidak($data['edukasi_penerjemah']) ?>
                <?php if (!empty($data['edukasi_penerjemah_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['edukasi_penerjemah_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Hambatan Belajar:</span>
            <span class="info-value">
                <?= getBadgeYaTidak($data['edukasi_terdapat_hambatan']) ?>
                <?php if (!empty($data['edukasi_hambatan_belajar'])): ?>
                — <span style="font-size:11px;color:#475569;"><?= htmlspecialchars($data['edukasi_hambatan_belajar']) ?></span>
                <?php if (!empty($data['edukasi_hambatan_belajar_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> (<?= htmlspecialchars($data['edukasi_hambatan_belajar_keterangan']) ?>)</span>
                <?php endif; ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item"><span class="info-label">Hambatan Bicara:</span><span class="info-value"><?= htmlspecialchars($data['edukasi_hambatan_bicara']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Bahasa Isyarat:</span><span class="info-value"><?= getBadgeYaTidak($data['edukasi_bahasa_isyarat']) ?></span></div>
        <div class="info-item"><span class="info-label">Cara Belajar:</span><span class="info-value"><?= htmlspecialchars($data['edukasi_cara_belajar']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Menerima Informasi:</span>
            <span class="info-value">
                <?= getBadgeYaTidak($data['edukasi_menerima_informasi']) ?>
                <?php if (!empty($data['edukasi_menerima_informasi_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['edukasi_menerima_informasi_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <!-- Topik Edukasi -->
    <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin:8px 0 4px 0;">Topik Edukasi Yang Dibutuhkan</div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">Nutrisi:</span><span class="info-value"><?= getBadgeYaTidak($data['edukasi_nutrisi']) ?></span></div>
        <div class="info-item"><span class="info-label">Penyakit:</span><span class="info-value"><?= getBadgeYaTidak($data['edukasi_penyakit']) ?></span></div>
        <div class="info-item"><span class="info-label">Pengobatan:</span><span class="info-value"><?= getBadgeYaTidak($data['edukasi_pengobatan']) ?></span></div>
        <div class="info-item"><span class="info-label">Perawatan:</span><span class="info-value"><?= getBadgeYaTidak($data['edukasi_perawatan']) ?></span></div>
    </div>

    <!-- XV. SKRINING GIZI -->
    <div class="section-title"><i class="fa fa-utensils"></i> XV. Skrining Gizi</div>
    <div style="overflow-x:auto;margin-bottom:8px;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">#</th>
                    <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Pertanyaan</th>
                    <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Jawaban</th>
                    <th style="padding:7px 10px;text-align:center;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Nilai</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $gizi_q = [
                    1 => 'Apakah pasien tampak kurus?',
                    2 => 'Apakah terdapat penurunan BB dalam 1 bulan terakhir?',
                    3 => 'Apakah terdapat kondisi yang mempengaruhi asupan nutrisi?',
                    4 => 'Apakah ada peningkatan kebutuhan nutrisi (stres metabolik)?',
                ];
                foreach ($gizi_q as $i => $q):
                    $bg = ($i % 2 == 0) ? 'background:#fafafa;' : '';
                ?>
                <tr style="border-bottom:1px solid #f1f5f9;<?= $bg ?>">
                    <td style="padding:6px 10px;color:#64748b;font-weight:600;"><?= $i ?></td>
                    <td style="padding:6px 10px;color:#1e293b;"><?= $q ?></td>
                    <td style="padding:6px 10px;"><?= getBadgeYaTidak($data["skrining_gizi{$i}"]) ?></td>
                    <td style="padding:6px 10px;text-align:center;color:#1e293b;"><?= htmlspecialchars($data["nilai_gizi{$i}"]) ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:linear-gradient(135deg,#fee2e2,#fecaca);">
                    <td colspan="3" style="padding:7px 10px;color:#991b1b;font-weight:700;">TOTAL NILAI</td>
                    <td style="padding:7px 10px;text-align:center;font-weight:700;color:#991b1b;"><?= $gizi_total ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php if (!empty($data['total_nilai']) || !empty($data['keterangan_skrining_gizi'])): ?>
    <div class="info-grid">
        <?php if (!empty($data['total_nilai'])): ?>
        <div class="info-item"><span class="info-label">Total Nilai (DB):</span><span class="info-value"><?= htmlspecialchars($data['total_nilai']) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($data['keterangan_skrining_gizi'])): ?>
        <div class="info-item" style="grid-column:span 2;"><span class="info-label">Keterangan:</span><span class="info-value"><?= htmlspecialchars($data['keterangan_skrining_gizi']) ?></span></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- XVI. PENILAIAN RISIKO JATUH – HUMPTY DUMPTY -->
    <div class="section-title"><i class="fa fa-exclamation-triangle"></i> XVI. Penilaian Risiko Jatuh – Humpty Dumpty</div>
    <div style="overflow-x:auto;margin-bottom:8px;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Parameter</th>
                    <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Pilihan</th>
                    <th style="padding:7px 10px;text-align:center;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Nilai</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hd_params = [
                    1 => 'Usia',
                    2 => 'Jenis Kelamin',
                    3 => 'Diagnosis',
                    4 => 'Gangguan Kognitif',
                    5 => 'Faktor Lingkungan',
                    6 => 'Respons Pembedahan/Sedasi/Anestesi',
                    7 => 'Penggunaan Obat',
                ];
                foreach ($hd_params as $i => $param):
                    $bg = ($i % 2 == 0) ? 'background:#fafafa;' : '';
                ?>
                <tr style="border-bottom:1px solid #f1f5f9;<?= $bg ?>">
                    <td style="padding:6px 10px;color:#64748b;font-weight:600;"><?= $param ?></td>
                    <td style="padding:6px 10px;color:#1e293b;"><?= htmlspecialchars($data["penilaian_humptydumpty_skala{$i}"]) ?: '-' ?></td>
                    <td style="padding:6px 10px;text-align:center;color:#1e293b;"><?= htmlspecialchars($data["penilaian_humptydumpty_nilai{$i}"]) ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:linear-gradient(135deg,#fee2e2,#fecaca);">
                    <td colspan="2" style="padding:7px 10px;color:#991b1b;font-weight:700;">
                        TOTAL
                        <?php if (!empty($data['hasil_skrining_penilaian_humptydumpty'])): ?>
                        — <span style="font-weight:500;font-size:11px;"><?= htmlspecialchars($data['hasil_skrining_penilaian_humptydumpty']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:7px 10px;text-align:center;font-weight:700;color:#991b1b;"><?= $hd_total ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- XVII. PENILAIAN NYERI – FLACC -->
    <div class="section-title"><i class="fa fa-thermometer-half"></i> XVII. Penilaian Nyeri – FLACC</div>
    <div style="overflow-x:auto;margin-bottom:8px;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:#f1f5f9;">
                    <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Kategori</th>
                    <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Deskripsi</th>
                    <th style="padding:7px 10px;text-align:center;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Nilai</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $flacc = [
                    ['nyeri_wajah',     'nyeri_nilai_wajah',     'F – Wajah (Face)'],
                    ['nyeri_kaki',      'nyeri_nilai_kaki',      'L – Kaki (Legs)'],
                    ['nyeri_aktifitas', 'nyeri_nilai_aktifitas', 'A – Aktivitas (Activity)'],
                    ['nyeri_menangis',  'nyeri_nilai_menangis',  'C – Menangis (Cry)'],
                    ['nyeri_bersuara',  'nyeri_nilai_bersuara',  'C – Bersuara (Consolability)'],
                ];
                foreach ($flacc as $i => [$col_desc, $col_val, $label]):
                    $bg = ($i % 2 == 0) ? '' : 'background:#fafafa;';
                ?>
                <tr style="border-bottom:1px solid #f1f5f9;<?= $bg ?>">
                    <td style="padding:6px 10px;color:#64748b;font-weight:600;white-space:nowrap;"><?= $label ?></td>
                    <td style="padding:6px 10px;color:#1e293b;"><?= htmlspecialchars($data[$col_desc]) ?: '-' ?></td>
                    <td style="padding:6px 10px;text-align:center;color:#1e293b;"><?= htmlspecialchars($data[$col_val]) ?: '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:linear-gradient(135deg,#fee2e2,#fecaca);">
                    <td colspan="2" style="padding:7px 10px;color:#991b1b;font-weight:700;">TOTAL SKOR NYERI</td>
                    <td style="padding:7px 10px;text-align:center;font-weight:700;color:#991b1b;">
                        <?php
                        $nyeri_color = $nyeri_total <= 3 ? '#10b981' : ($nyeri_total <= 6 ? '#f59e0b' : '#ef4444');
                        ?>
                        <span style="display:inline-block;padding:2px 10px;border-radius:3px;background:<?= $nyeri_color ?>;color:#fff;font-weight:700;"><?= $nyeri_total ?>/10</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="info-grid">
        <div class="info-item"><span class="info-label">Kondisi Nyeri:</span><span class="info-value"><?= htmlspecialchars($data['nyeri_kondisi']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Lokasi:</span><span class="info-value"><?= htmlspecialchars($data['nyeri_lokasi']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Durasi:</span><span class="info-value"><?= htmlspecialchars($data['nyeri_durasi']) ?: '-' ?></span></div>
        <div class="info-item"><span class="info-label">Frekuensi:</span><span class="info-value"><?= htmlspecialchars($data['nyeri_frekuensi']) ?: '-' ?></span></div>
        <div class="info-item">
            <span class="info-label">Cara Mengurangi:</span>
            <span class="info-value">
                <?= htmlspecialchars($data['nyeri_hilang']) ?: '-' ?>
                <?php if (!empty($data['nyeri_hilang_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['nyeri_hilang_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
        <div class="info-item">
            <span class="info-label">Diberitahukan Dokter:</span>
            <span class="info-value">
                <?= getBadgeYaTidak($data['nyeri_diberitahukan_pada_dokter']) ?>
                <?php if (!empty($data['nyeri_diberitahukan_pada_dokter_keterangan'])): ?>
                <span style="font-size:11px;color:#475569;margin-left:4px;"><?= htmlspecialchars($data['nyeri_diberitahukan_pada_dokter_keterangan']) ?></span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- XVIII. PERENCANAAN PULANG -->
    <div class="section-title"><i class="fa fa-home"></i> XVIII. Perencanaan Pulang</div>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Informasi Perencanaan Pulang:</span>
            <span class="info-value"><?= getBadgeYaTidak($data['informasi_perencanaan_pulang']) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Lama Perawatan Rata-rata:</span>
            <span class="info-value"><?= htmlspecialchars($data['lama_ratarata']) ?: '-' ?> hari</span>
        </div>
        <div class="info-item">
            <span class="info-label">Rencana Tanggal Pulang:</span>
            <span class="info-value"><?= !empty($data['perencanaan_pulang']) ? date('d M Y', strtotime($data['perencanaan_pulang'])) : '-' ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Kondisi Klinis Saat Pulang:</span>
            <span class="info-value"><?= htmlspecialchars($data['kondisi_klinis_pulang']) ?: '-' ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Cara Transportasi:</span>
            <span class="info-value"><?= htmlspecialchars($data['cara_transportasi_pulang']) ?: '-' ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Transportasi Digunakan:</span>
            <span class="info-value"><?= htmlspecialchars($data['transportasi_digunakan']) ?: '-' ?></span>
        </div>
        <?php if (!empty($data['perawatan_lanjutan_dirumah'])): ?>
        <div class="info-item" style="grid-column:1/-1;">
            <span class="info-label">Perawatan Lanjutan di Rumah:</span>
            <span class="info-value"><?= nl2br(htmlspecialchars($data['perawatan_lanjutan_dirumah'])) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($data['rencana'])): ?>
        <div class="info-item" style="grid-column:1/-1;">
            <span class="info-label">Rencana Keperawatan:</span>
            <span class="info-value"><?= nl2br(htmlspecialchars($data['rencana'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

</div>
</div>

<?php endwhile; ?>