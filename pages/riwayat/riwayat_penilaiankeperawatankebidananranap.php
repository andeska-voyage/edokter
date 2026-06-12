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

// Function untuk badge status pemeriksaan
function getBadgeStatus($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'normal') {
        return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background-color:#10b981;color:#fff;">Normal</span>';
    } elseif ($v == 'abnormal') {
        return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background-color:#ef4444;color:#fff;">Abnormal</span>';
    } elseif ($v == 'tidak diperiksa') {
        return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background-color:#fbbf24;color:#1e293b;">Tidak Diperiksa</span>';
    }
    return htmlspecialchars($value);
}

// Function badge Ya/Tidak
function getBadgeYaTidak($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'ya') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background-color:#10b981;color:#fff;">Ya</span>';
    if ($v == 'tidak') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background-color:#94a3b8;color:#fff;">Tidak</span>';
    return htmlspecialchars($value);
}

// Function badge Dilakukan/Tidak
function getBadgeDilakukan($value) {
    if (empty($value)) return '-';
    $v = strtolower(trim($value));
    if ($v == 'dilakukan') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background-color:#3b82f6;color:#fff;">Dilakukan</span>';
    if ($v == 'tidak') return '<span style="display:inline-block;padding:3px 8px;font-size:11px;font-weight:600;border-radius:3px;background-color:#94a3b8;color:#fff;">Tidak</span>';
    return htmlspecialchars($value);
}

// Query data
$query_medis = "
    SELECT p.*, d.nm_dokter
    FROM penilaian_awal_keperawatan_kebidanan_ranap p
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_medis = bukaquery($query_medis);

if (mysqli_num_rows($result_medis) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian keperawatan kebidanan ranap tidak ditemukan</div>';
    exit;
}

while ($data = mysqli_fetch_assoc($result_medis)):
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $tgl = strtotime($data['tanggal']);
    $tanggal_format = date('d',$tgl).' '.$bulan[date('n',$tgl)].' '.date('Y, H:i',$tgl);

    // Hitung total jatuh
    $total_jatuh = (float)$data['penilaian_jatuh_totalnilai'];

    // Hitung total gizi
    $total_gizi = (float)$data['nilai_total_gizi'];
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
                <span class="info-label">Cara Masuk:</span>
                <span class="info-value"><?= htmlspecialchars($data['cara_masuk']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tiba di Ruang Rawat:</span>
                <span class="info-value"><?= htmlspecialchars($data['tiba_diruang_rawat']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Sumber Informasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['informasi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- I. RIWAYAT KESEHATAN -->
        <div class="section-title">
            <i class="fa fa-notes-medical"></i> I. Riwayat Kesehatan
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <span class="info-label">Keluhan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['keluhan'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Penyakit Keluarga:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rpk'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Penyakit Sebelumnya (PSK):</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['psk'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Riwayat Pengobatan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rp'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Alergi:</span>
                <span class="info-value"><?= htmlspecialchars($data['alergi']) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Komplikasi Sebelumnya:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['komplikasi_sebelumnya']) ?: '-' ?>
                    <?php if (!empty($data['keterangan_komplikasi_sebelumnya'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['keterangan_komplikasi_sebelumnya']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- II. RIWAYAT KEBIDANAN -->
        <div class="section-title">
            <i class="fa fa-venus"></i> II. Riwayat Kebidanan
        </div>

        <!-- Riwayat Menstruasi -->
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px;">Riwayat Menstruasi</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Umur Menarche:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_mens_umur']) ?: '-' ?> tahun</span>
            </div>
            <div class="info-item">
                <span class="info-label">Lama Haid:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_mens_lamanya']) ?: '-' ?> hari</span>
            </div>
            <div class="info-item">
                <span class="info-label">Banyaknya:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_mens_banyaknya']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Siklus:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_mens_siklus']) ?: '-' ?> hari</span>
            </div>
            <div class="info-item">
                <span class="info-label">Keteraturan Siklus:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_mens_ket_siklus']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Keluhan Haid:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_mens_dirasakan']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Riwayat Perkawinan -->
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin:10px 0 4px 0;">Riwayat Perkawinan</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Status Nikah:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_perkawinan_status']) ?: '-' ?>
                    <?php if (!empty($data['riwayat_perkawinan_ket_status'])): ?>
                    <span style="font-size:11px;color:#475569;"> (<?= htmlspecialchars($data['riwayat_perkawinan_ket_status']) ?>)</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php
            for ($i = 1; $i <= 3; $i++):
                if (!empty($data["riwayat_perkawinan_usia{$i}"])):
            ?>
            <div class="info-item">
                <span class="info-label">Suami ke-<?= $i ?> (Usia):</span>
                <span class="info-value">
                    <?= htmlspecialchars($data["riwayat_perkawinan_usia{$i}"]) ?> thn
                    <?php if (!empty($data["riwayat_perkawinan_ket_usia{$i}"])): ?>
                    — <span style="font-size:11px;color:#475569;"><?= htmlspecialchars($data["riwayat_perkawinan_ket_usia{$i}"]) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; endfor; ?>
        </div>

        <!-- Riwayat Persalinan -->
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin:10px 0 4px 0;">Riwayat Persalinan</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">G/P/A:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_persalinan_g']) ?: '-' ?>/<?= htmlspecialchars($data['riwayat_persalinan_p']) ?: '-' ?>/<?= htmlspecialchars($data['riwayat_persalinan_a']) ?: '-' ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Hidup:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_persalinan_hidup']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Riwayat Hamil -->
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin:10px 0 4px 0;">Riwayat Kehamilan Sekarang</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">HPHT:</span>
                <span class="info-value"><?= !empty($data['riwayat_hamil_hpht']) ? date('d M Y', strtotime($data['riwayat_hamil_hpht'])) : '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Usia Hamil:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_hamil_usiahamil']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TP:</span>
                <span class="info-value"><?= !empty($data['riwayat_hamil_tp']) ? date('d M Y', strtotime($data['riwayat_hamil_tp'])) : '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Imunisasi TT:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_hamil_imunisasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">ANC:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_hamil_anc']) ?: '-' ?> kali</span>
            </div>
            <div class="info-item">
                <span class="info-label">ANC Teratur:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_hamil_ket_ancke']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Keluhan Hamil Muda:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_hamil_keluhan_hamil_muda']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Keluhan Hamil Tua:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_hamil_keluhan_hamil_tua']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Riwayat KB -->
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin:10px 0 4px 0;">Riwayat KB</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Metode KB:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_kb']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Lama Pemakaian:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_kb_lamanya']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Komplikasi KB:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_kb_komplikasi']) ?: '-' ?>
                    <?php if (!empty($data['riwayat_kb_ket_komplikasi'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['riwayat_kb_ket_komplikasi']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if (!empty($data['riwayat_kb_kapaberhenti'])): ?>
            <div class="info-item">
                <span class="info-label">Kapan Berhenti:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_kb_kapaberhenti']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['riwayat_kb_alasanberhenti'])): ?>
            <div class="info-item">
                <span class="info-label">Alasan Berhenti:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_kb_alasanberhenti']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-item">
                <span class="info-label">Riwayat Genekologi:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_genekologi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Kebiasaan -->
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin:10px 0 4px 0;">Kebiasaan</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Obat-obatan:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_kebiasaan_obat']) ?: '-' ?>
                    <?php if (!empty($data['riwayat_kebiasaan_ket_obat'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['riwayat_kebiasaan_ket_obat']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Merokok:</span>
                <span class="info-value">
                    <?= getBadgeYaTidak($data['riwayat_kebiasaan_merokok']) ?>
                    <?php if (!empty($data['riwayat_kebiasaan_ket_merokok'])): ?>
                    <span style="font-size:11px;color:#475569;margin-left:4px;"><?= htmlspecialchars($data['riwayat_kebiasaan_ket_merokok']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Alkohol:</span>
                <span class="info-value">
                    <?= getBadgeYaTidak($data['riwayat_kebiasaan_alkohol']) ?>
                    <?php if (!empty($data['riwayat_kebiasaan_ket_alkohol'])): ?>
                    <span style="font-size:11px;color:#475569;margin-left:4px;"><?= htmlspecialchars($data['riwayat_kebiasaan_ket_alkohol']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Narkoba:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['riwayat_kebiasaan_narkoba']) ?></span>
            </div>
        </div>

        <!-- III. PEMERIKSAAN KEBIDANAN -->
        <div class="section-title">
            <i class="fa fa-baby"></i> III. Pemeriksaan Kebidanan
        </div>

        <!-- Tanda Vital -->
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px;">Tanda Vital</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Status Mental:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_mental']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Keadaan Umum:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_keadaan_umum']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">GCS:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_gcs']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TD:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_td']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nadi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_nadi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">RR:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_rr']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Suhu:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_suhu']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">SpO2:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_spo2']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">BB:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_bb']) ?: '-' ?> kg</span>
            </div>
            <div class="info-item">
                <span class="info-label">TB:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_tb']) ?: '-' ?> cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">LILA:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_lila']) ?: '-' ?> cm</span>
            </div>
        </div>

        <!-- Obstetri -->
        <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;margin:10px 0 4px 0;">Pemeriksaan Obstetri</div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">TFU:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_tfu']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">TBJ:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_tbj']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Letak:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_letak']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Presentasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_presentasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Penurunan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_penurunan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">HIS:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_his']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kekuatan HIS:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_kekuatan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Lamanya HIS:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_lamanya']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">DJJ:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_kebidanan_djj']) ?: '-' ?>
                    <?php if (!empty($data['pemeriksaan_kebidanan_ket_djj'])): ?>
                    <span style="font-size:11px;color:#475569;"> (<?= htmlspecialchars($data['pemeriksaan_kebidanan_ket_djj']) ?>)</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Portio:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_portio']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Pembukaan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_pembukaan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ketuban:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_ketuban']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Hodge:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_hodge']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Panggul:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kebidanan_panggul']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Inspekulo, Lakmus, CTG -->
        <div class="info-grid" style="margin-top:6px;">
            <div class="info-item">
                <span class="info-label">Inspekulo:</span>
                <span class="info-value">
                    <?= getBadgeDilakukan($data['pemeriksaan_kebidanan_inspekulo']) ?>
                    <?php if (!empty($data['pemeriksaan_kebidanan_ket_inspekulo'])): ?>
                    <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['pemeriksaan_kebidanan_ket_inspekulo']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Lakmus:</span>
                <span class="info-value">
                    <?= getBadgeDilakukan($data['pemeriksaan_kebidanan_lakmus']) ?>
                    <?php if (!empty($data['pemeriksaan_kebidanan_ket_lakmus'])): ?>
                    <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['pemeriksaan_kebidanan_ket_lakmus']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">CTG:</span>
                <span class="info-value">
                    <?= getBadgeDilakukan($data['pemeriksaan_kebidanan_ctg']) ?>
                    <?php if (!empty($data['pemeriksaan_kebidanan_ket_ctg'])): ?>
                    <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['pemeriksaan_kebidanan_ket_ctg']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- IV. PEMERIKSAAN UMUM -->
        <div class="section-title">
            <i class="fa fa-stethoscope"></i> IV. Pemeriksaan Fisik Umum
        </div>
        <div class="info-grid">
            <?php
            $pemfis = [
                'pemeriksaan_umum_kepala'    => 'Kepala',
                'pemeriksaan_umum_muka'      => 'Muka',
                'pemeriksaan_umum_mata'      => 'Mata',
                'pemeriksaan_umum_hidung'    => 'Hidung',
                'pemeriksaan_umum_telinga'   => 'Telinga',
                'pemeriksaan_umum_mulut'     => 'Mulut',
                'pemeriksaan_umum_leher'     => 'Leher',
                'pemeriksaan_umum_dada'      => 'Dada',
                'pemeriksaan_umum_perut'     => 'Perut',
                'pemeriksaan_umum_genitalia' => 'Genitalia',
                'pemeriksaan_umum_ekstrimitas'=> 'Ekstremitas',
            ];
            foreach ($pemfis as $col => $label):
            ?>
            <div class="info-item">
                <span class="info-label"><?= $label ?>:</span>
                <span class="info-value"><?= htmlspecialchars($data[$col]) ?: '-' ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- V. PENGKAJIAN FUNGSI -->
        <div class="section-title">
            <i class="fa fa-walking"></i> V. Pengkajian Fungsi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Kemampuan Aktivitas:</span>
                <span class="info-value"><?= htmlspecialchars($data['pengkajian_fungsi_kemampuan_aktifitas']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kemampuan Berjalan:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_berjalan']) ?: '-' ?>
                    <?php if (!empty($data['pengkajian_fungsi_ket_berjalan'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['pengkajian_fungsi_ket_berjalan']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Aktivitas:</span>
                <span class="info-value"><?= htmlspecialchars($data['pengkajian_fungsi_aktivitas']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ambulasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pengkajian_fungsi_ambulasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ekstremitas Atas:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_ekstrimitas_atas']) ?: '-' ?>
                    <?php if (!empty($data['pengkajian_fungsi_ket_ekstrimitas_atas'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['pengkajian_fungsi_ket_ekstrimitas_atas']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Ekstremitas Bawah:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_ekstrimitas_bawah']) ?: '-' ?>
                    <?php if (!empty($data['pengkajian_fungsi_ket_ekstrimitas_bawah'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['pengkajian_fungsi_ket_ekstrimitas_bawah']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Kemampuan Menggenggam:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_kemampuan_menggenggam']) ?: '-' ?>
                    <?php if (!empty($data['pengkajian_fungsi_ket_kemampuan_menggenggam'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['pengkajian_fungsi_ket_kemampuan_menggenggam']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Koordinasi:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_koordinasi']) ?: '-' ?>
                    <?php if (!empty($data['pengkajian_fungsi_ket_koordinasi'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['pengkajian_fungsi_ket_koordinasi']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Gangguan Fungsi (Co DPJP):</span>
                <span class="info-value"><?= htmlspecialchars($data['pengkajian_fungsi_gangguan_fungsi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- VI. RIWAYAT PSIKO-SOSIAL-BUDAYA -->
        <div class="section-title">
            <i class="fa fa-brain"></i> VI. Riwayat Psiko-Sosial-Budaya
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Kondisi Psikologis:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_psiko_kondisipsiko']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Perilaku:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_psiko_adakah_prilaku']) ?: '-' ?>
                    <?php if (!empty($data['riwayat_psiko_ket_adakah_prilaku'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['riwayat_psiko_ket_adakah_prilaku']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Gangguan Jiwa:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['riwayat_psiko_gangguan_jiwa']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Hubungan Pasien:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_psiko_hubungan_pasien']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tinggal Dengan:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_psiko_tinggal_dengan']) ?: '-' ?>
                    <?php if (!empty($data['riwayat_psiko_ket_tinggal_dengan'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['riwayat_psiko_ket_tinggal_dengan']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Budaya/Kepercayaan:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_psiko_budaya']) ?: '-' ?>
                    <?php if (!empty($data['riwayat_psiko_ket_budaya'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['riwayat_psiko_ket_budaya']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Pendidikan:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_psiko_pend_pj']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Edukasi Diberikan Kepada:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_psiko_edukasi_pada']) ?: '-' ?>
                    <?php if (!empty($data['riwayat_psiko_ket_edukasi_pada'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['riwayat_psiko_ket_edukasi_pada']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- VII. PENILAIAN NYERI -->
        <div class="section-title">
            <i class="fa fa-thermometer-half"></i> VII. Penilaian Nyeri
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Nyeri:</span>
                <span class="info-value"><?= htmlspecialchars($data['penilaian_nyeri']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Penyebab:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['penilaian_nyeri_penyebab']) ?: '-' ?>
                    <?php if (!empty($data['penilaian_nyeri_ket_penyebab'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['penilaian_nyeri_ket_penyebab']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Kualitas:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['penilaian_nyeri_kualitas']) ?: '-' ?>
                    <?php if (!empty($data['penilaian_nyeri_ket_kualitas'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['penilaian_nyeri_ket_kualitas']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Lokasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['penilaian_nyeri_lokasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Menyebar:</span>
                <span class="info-value"><?= getBadgeYaTidak($data['penilaian_nyeri_menyebar']) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Skala Nyeri:</span>
                <span class="info-value">
                    <?php
                    $skala = (int)$data['penilaian_nyeri_skala'];
                    $skala_color = $skala <= 3 ? '#10b981' : ($skala <= 6 ? '#f59e0b' : '#ef4444');
                    ?>
                    <span style="display:inline-block;padding:2px 10px;font-size:12px;font-weight:700;border-radius:3px;background-color:<?= $skala_color ?>;color:#fff;"><?= $skala ?>/10</span>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Waktu:</span>
                <span class="info-value"><?= htmlspecialchars($data['penilaian_nyeri_waktu']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Cara Mengurangi:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['penilaian_nyeri_hilang']) ?: '-' ?>
                    <?php if (!empty($data['penilaian_nyeri_ket_hilang'])): ?>
                    <span style="font-size:11px;color:#475569;"> — <?= htmlspecialchars($data['penilaian_nyeri_ket_hilang']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Diberitahukan Dokter:</span>
                <span class="info-value">
                    <?= getBadgeYaTidak($data['penilaian_nyeri_diberitahukan_dokter']) ?>
                    <?php if (!empty($data['penilaian_nyeri_jam_diberitahukan_dokter'])): ?>
                    <span style="font-size:11px;color:#475569;margin-left:4px;">Jam: <?= htmlspecialchars($data['penilaian_nyeri_jam_diberitahukan_dokter']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- VIII. PENILAIAN RISIKO JATUH -->
        <div class="section-title">
            <i class="fa fa-exclamation-triangle"></i> VIII. Penilaian Risiko Jatuh (Morse/Humpty Dumpty)
        </div>
        <div style="overflow-x:auto;margin-bottom:8px;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr style="background-color:#f1f5f9;">
                        <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Parameter</th>
                        <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Hasil</th>
                        <th style="padding:7px 10px;text-align:center;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $jatuh_params = [
                        1 => 'Riwayat Jatuh',
                        2 => 'Diagnosis Sekunder',
                        3 => 'Alat Bantu',
                        4 => 'Terpasang Infus/Heparin Lock',
                        5 => 'Cara Berjalan/Berpindah',
                        6 => 'Status Mental',
                    ];
                    foreach ($jatuh_params as $i => $param):
                        $bg = ($i % 2 == 0) ? 'background-color:#fafafa;' : '';
                    ?>
                    <tr style="border-bottom:1px solid #f1f5f9;<?= $bg ?>">
                        <td style="padding:6px 10px;color:#64748b;font-weight:600;"><?= $param ?></td>
                        <td style="padding:6px 10px;color:#1e293b;"><?= htmlspecialchars($data["penilaian_jatuh_skala{$i}"]) ?: '-' ?></td>
                        <td style="padding:6px 10px;text-align:center;color:#1e293b;"><?= htmlspecialchars($data["penilaian_jatuh_nilai{$i}"]) ?: '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background:linear-gradient(135deg,#fee2e2,#fecaca);">
                        <td colspan="2" style="padding:7px 10px;color:#991b1b;font-weight:700;">TOTAL SKOR</td>
                        <td style="padding:7px 10px;text-align:center;font-weight:700;color:#991b1b;"><?= $total_jatuh ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- IX. SKRINING GIZI -->
        <div class="section-title">
            <i class="fa fa-utensils"></i> IX. Skrining Gizi
        </div>
        <div style="overflow-x:auto;margin-bottom:8px;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr style="background-color:#f1f5f9;">
                        <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Pertanyaan</th>
                        <th style="padding:7px 10px;text-align:left;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Jawaban</th>
                        <th style="padding:7px 10px;text-align:center;font-weight:700;color:#475569;border-bottom:2px solid #e2e8f0;">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:6px 10px;color:#64748b;font-weight:600;">Apakah ada penurunan berat badan?</td>
                        <td style="padding:6px 10px;color:#1e293b;"><?= htmlspecialchars($data['skrining_gizi1']) ?: '-' ?></td>
                        <td style="padding:6px 10px;text-align:center;color:#1e293b;"><?= htmlspecialchars($data['nilai_gizi1']) ?: '-' ?></td>
                    </tr>
                    <tr style="background-color:#fafafa;border-bottom:1px solid #f1f5f9;">
                        <td style="padding:6px 10px;color:#64748b;font-weight:600;">Apakah asupan makan berkurang?</td>
                        <td style="padding:6px 10px;color:#1e293b;"><?= getBadgeYaTidak($data['skrining_gizi2']) ?></td>
                        <td style="padding:6px 10px;text-align:center;color:#1e293b;"><?= htmlspecialchars($data['nilai_gizi2']) ?: '-' ?></td>
                    </tr>
                    <tr style="background:linear-gradient(135deg,#fee2e2,#fecaca);">
                        <td colspan="2" style="padding:7px 10px;color:#991b1b;font-weight:700;">TOTAL NILAI GIZI</td>
                        <td style="padding:7px 10px;text-align:center;font-weight:700;color:#991b1b;"><?= $total_gizi ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Diagnosa Khusus:</span>
                <span class="info-value">
                    <?= getBadgeYaTidak($data['skrining_gizi_diagnosa_khusus']) ?>
                    <?php if (!empty($data['skrining_gizi_ket_diagnosa_khusus'])): ?>
                    <span style="font-size:11px;color:#475569;margin-left:4px;">— <?= htmlspecialchars($data['skrining_gizi_ket_diagnosa_khusus']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Diketahui Dietisen:</span>
                <span class="info-value">
                    <?= getBadgeYaTidak($data['skrining_gizi_diketahui_dietisen']) ?>
                    <?php if (!empty($data['skrining_gizi_jam_diketahui_dietisen'])): ?>
                    <span style="font-size:11px;color:#475569;margin-left:4px;">Jam: <?= htmlspecialchars($data['skrining_gizi_jam_diketahui_dietisen']) ?></span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- X. MASALAH & RENCANA -->
        <div class="section-title">
            <i class="fa fa-clipboard-check"></i> X. Masalah &amp; Rencana Keperawatan
        </div>
        <div class="info-grid-vertical">
            <div class="info-item-vertical" style="grid-column:1/-1;">
                <span class="info-label">Masalah:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['masalah'])) ?: '-' ?></span>
            </div>
            <div class="info-item-vertical" style="grid-column:1/-1;">
                <span class="info-label">Rencana:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rencana'])) ?: '-' ?></span>
            </div>
        </div>

    </div>
</div>

<?php endwhile; ?>