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

// Query data keperawatan
$query_keperawatan = "
    SELECT 
        p.no_rawat,
        p.tanggal,
        p.informasi,
        p.keluhan_utama,
        p.rpd,
        p.rpo,
        p.status_kehamilan,
        p.gravida,
        p.para,
        p.abortus,
        p.hpht,
        p.tekanan,
        p.pupil,
        p.neurosensorik,
        p.integumen,
        p.turgor,
        p.edema,
        p.mukosa,
        p.perdarahan,
        p.jumlah_perdarahan,
        p.warna_perdarahan,
        p.intoksikasi,
        p.bab,
        p.xbab,
        p.kbab,
        p.wbab,
        p.bak,
        p.xbak,
        p.wbak,
        p.lbak,
        p.psikologis,
        p.jiwa,
        p.perilaku,
        p.dilaporkan,
        p.sebutkan,
        p.hubungan,
        p.tinggal_dengan,
        p.ket_tinggal,
        p.budaya,
        p.ket_budaya,
        p.pendidikan_pj,
        p.ket_pendidikan_pj,
        p.edukasi,
        p.ket_edukasi,
        p.kemampuan,
        p.aktifitas,
        p.alat_bantu,
        p.ket_bantu,
        p.nyeri,
        p.provokes,
        p.ket_provokes,
        p.quality,
        p.ket_quality,
        p.lokasi,
        p.menyebar,
        p.skala_nyeri,
        p.durasi,
        p.nyeri_hilang,
        p.ket_nyeri,
        p.pada_dokter,
        p.ket_dokter,
        p.berjalan_a,
        p.berjalan_b,
        p.berjalan_c,
        p.hasil,
        p.lapor,
        p.ket_lapor,
        p.rencana,
        p.nip,
        pt.nama as nm_perawat
    FROM penilaian_awal_keperawatan_igd p
    LEFT JOIN petugas pt ON p.nip = pt.nip
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
    LIMIT 1
";

$result_keperawatan = bukaquery($query_keperawatan);
$data_keperawatan = mysqli_fetch_assoc($result_keperawatan);

if (!$data_keperawatan) {
    echo '<div class="alert alert-warning m-3">Data penilaian awal keperawatan IGD tidak ditemukan</div>';
    exit;
}

// Query masalah keperawatan
$query_masalah = "
    SELECT 
        m.kode_masalah,
        mm.nama_masalah
    FROM penilaian_awal_keperawatan_igd_masalah m
    INNER JOIN master_masalah_keperawatan_igd mm ON m.kode_masalah = mm.kode_masalah
    WHERE m.no_rawat = '$no_rawat'
";

$result_masalah = bukaquery($query_masalah);
$masalah_keperawatan = [];
while ($row = mysqli_fetch_assoc($result_masalah)) {
    $masalah_keperawatan[] = $row;
}

// Query rencana keperawatan
$query_rencana = "
    SELECT 
        r.kode_rencana,
        mr.rencana_keperawatan
    FROM penilaian_awal_keperawatan_ralan_rencana_igd r
    INNER JOIN master_rencana_keperawatan_igd mr ON r.kode_rencana = mr.kode_rencana
    WHERE r.no_rawat = '$no_rawat'
";

$result_rencana = bukaquery($query_rencana);
$rencana_keperawatan = [];
while ($row = mysqli_fetch_assoc($result_rencana)) {
    $rencana_keperawatan[] = $row;
}

// Parse rencana dari p.rencana (tabel utama)
$rencana_utama = [];
if (!empty($data_keperawatan['rencana'])) {
    $rencana_items = explode("\n", trim($data_keperawatan['rencana']));
    foreach ($rencana_items as $item) {
        $item = trim($item);
        if ($item !== '') {
            $rencana_utama[] = $item;
        }
    }
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<div class="card">
    <div class="card-body">
        <!-- I. RIWAYAT KESEHATAN PASIEN - VERTICAL LAYOUT -->
        <h6 class="section-title">
            <i class="fa fa-notes-medical"></i>
            I. Riwayat Kesehatan Pasien
        </h6>
        
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <div class="info-label">Riwayat Penyakit Sekarang</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['keluhan_utama'] ?: '-') ?></div>
            </div>
            <div class="info-item-vertical">
                <div class="info-label">Riwayat Penyakit Dahulu</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['rpd'] ?: '-') ?></div>
            </div>
            <div class="info-item-vertical">
                <div class="info-label">Riwayat Penggunaan Obat</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['rpo'] ?: '-') ?></div>
            </div>
        </div>
        
        <div class="info-grid-vertical">
            <div class="info-item-vertical">
                <div class="info-label">Status Kehamilan</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['status_kehamilan'] ?: '-') ?>
                    <?php if ($data_keperawatan['status_kehamilan'] == 'Hamil'): ?>
                        (G: <?= htmlspecialchars($data_keperawatan['gravida'] ?: '0') ?>, 
                        P: <?= htmlspecialchars($data_keperawatan['para'] ?: '0') ?>, 
                        A: <?= htmlspecialchars($data_keperawatan['abortus'] ?: '0') ?>, 
                        HPHT: <?= htmlspecialchars($data_keperawatan['hpht'] ?: '-') ?>)
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- II. PEMERIKSAAN FISIK -->
        <h6 class="section-title">
            <i class="fa fa-heartbeat"></i>
            II. Pemeriksaan Fisik
        </h6>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Tekanan Darah</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['tekanan'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Pupil</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['pupil'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Neurosensorik</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['neurosensorik'] ?: '-') ?></div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Integumen</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['integumen'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Turgor Kulit</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['turgor'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Edema</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['edema'] ?: '-') ?></div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Mukosa</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['mukosa'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Perdarahan</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['perdarahan'] ?: '-') ?>
                    <?php if ($data_keperawatan['perdarahan'] == 'Ya'): ?>
                        (<?= htmlspecialchars($data_keperawatan['jumlah_perdarahan'] ?: '-') ?>, 
                        Warna: <?= htmlspecialchars($data_keperawatan['warna_perdarahan'] ?: '-') ?>)
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Intoksikasi</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['intoksikasi'] ?: '-') ?></div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">BAB</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['bab'] ?: '-') ?>
                    <?php if ($data_keperawatan['bab'] != 'Normal'): ?>
                        (<?= htmlspecialchars($data_keperawatan['xbab'] ?: '-') ?> x/hari, 
                        Konsistensi: <?= htmlspecialchars($data_keperawatan['kbab'] ?: '-') ?>, 
                        Warna: <?= htmlspecialchars($data_keperawatan['wbab'] ?: '-') ?>)
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">BAK</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['bak'] ?: '-') ?>
                    <?php if ($data_keperawatan['bak'] != 'Normal'): ?>
                        (<?= htmlspecialchars($data_keperawatan['xbak'] ?: '-') ?> x/hari, 
                        Warna: <?= htmlspecialchars($data_keperawatan['wbak'] ?: '-') ?>, 
                        Lain-lain: <?= htmlspecialchars($data_keperawatan['lbak'] ?: '-') ?>)
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- III. PSIKOSOSIAL & SPIRITUAL -->
        <h6 class="section-title">
            <i class="fa fa-user-friends"></i>
            III. Psikososial & Spiritual
        </h6>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Status Psikologis</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['psikologis'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Kondisi Jiwa</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['jiwa'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Perilaku</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['perilaku'] ?: '-') ?>
                    <?php if ($data_keperawatan['dilaporkan']): ?>
                        (Dilaporkan: <?= htmlspecialchars($data_keperawatan['dilaporkan']) ?>, 
                        Ket: <?= htmlspecialchars($data_keperawatan['sebutkan'] ?: '-') ?>)
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Hubungan Keluarga</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['hubungan'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Tinggal Dengan</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['tinggal_dengan'] ?: '-') ?>
                    <?= $data_keperawatan['ket_tinggal'] ? ' (' . htmlspecialchars($data_keperawatan['ket_tinggal']) . ')' : '' ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Kepercayaan/Budaya</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['budaya'] ?: '-') ?>
                    <?= $data_keperawatan['ket_budaya'] ? ' (' . htmlspecialchars($data_keperawatan['ket_budaya']) . ')' : '' ?>
                </div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Pendidikan PJ</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['pendidikan_pj'] ?: '-') ?>
                    <?= $data_keperawatan['ket_pendidikan_pj'] ? ' (' . htmlspecialchars($data_keperawatan['ket_pendidikan_pj']) . ')' : '' ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Edukasi Diberikan Kepada</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['edukasi'] ?: '-') ?>
                    <?= $data_keperawatan['ket_edukasi'] ? ' (' . htmlspecialchars($data_keperawatan['ket_edukasi']) . ')' : '' ?>
                </div>
            </div>
        </div>

        <!-- IV. PENGKAJIAN FUNGSI -->
        <h6 class="section-title">
            <i class="fa fa-walking"></i>
            IV. Pengkajian Fungsi
        </h6>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Kemampuan Aktivitas</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['kemampuan'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Aktivitas</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['aktifitas'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Alat Bantu</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['alat_bantu'] ?: '-') ?>
                    <?= $data_keperawatan['ket_bantu'] ? ' (' . htmlspecialchars($data_keperawatan['ket_bantu']) . ')' : '' ?>
                </div>
            </div>
        </div>

        <!-- V. SKALA NYERI -->
        <h6 class="section-title">
            <i class="fa fa-exclamation-circle"></i>
            V. Skala Nyeri
        </h6>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Tingkat Nyeri</div>
                <div class="info-value">: <strong><?= htmlspecialchars($data_keperawatan['nyeri'] ?: '-') ?></strong></div>
            </div>
        </div>
        
        <?php if ($data_keperawatan['nyeri'] && $data_keperawatan['nyeri'] != 'Tidak Ada Nyeri'): ?>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Penyebab (Provokes)</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['provokes'] ?: '-') ?>
                    <?= $data_keperawatan['ket_provokes'] ? ' (' . htmlspecialchars($data_keperawatan['ket_provokes']) . ')' : '' ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Kualitas</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['quality'] ?: '-') ?>
                    <?= $data_keperawatan['ket_quality'] ? ' (' . htmlspecialchars($data_keperawatan['ket_quality']) . ')' : '' ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Lokasi</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['lokasi'] ?: '-') ?></div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Menyebar</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['menyebar'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Severity (Skala)</div>
                <div class="info-value">: <strong style="color: #dc3545;"><?= htmlspecialchars($data_keperawatan['skala_nyeri'] ?: '0') ?></strong></div>
            </div>
            <div class="info-item">
                <div class="info-label">Durasi</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['durasi'] ?: '-') ?></div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Nyeri Hilang Bila</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['nyeri_hilang'] ?: '-') ?>
                    <?= $data_keperawatan['ket_nyeri'] ? ' (' . htmlspecialchars($data_keperawatan['ket_nyeri']) . ')' : '' ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Diberitahukan pada Dokter</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['pada_dokter'] ?: '-') ?>
                    <?= $data_keperawatan['ket_dokter'] ? ' (Jam: ' . htmlspecialchars($data_keperawatan['ket_dokter']) . ')' : '' ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- VI. PENGKAJIAN RESIKO JATUH -->
        <h6 class="section-title">
            <i class="fa fa-exclamation-triangle"></i>
            VI. Pengkajian Resiko Jatuh
        </h6>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">a. Tidak Seimbang/Sempoyongan</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['berjalan_a'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">b. Jalan dengan Alat Bantu</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['berjalan_b'] ?: '-') ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">c. Menopang saat Duduk</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['berjalan_c'] ?: '-') ?></div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Hasil</div>
                <div class="info-value">: <strong><?= htmlspecialchars($data_keperawatan['hasil'] ?: '-') ?></strong></div>
            </div>
            <div class="info-item">
                <div class="info-label">Dilaporkan ke Dokter</div>
                <div class="info-value">: <?= htmlspecialchars($data_keperawatan['lapor'] ?: '-') ?>
                    <?= $data_keperawatan['ket_lapor'] ? ' (Jam: ' . htmlspecialchars($data_keperawatan['ket_lapor']) . ')' : '' ?>
                </div>
            </div>
        </div>

<!-- VIII. MASALAH & RENCANA KEPERAWATAN -->
<div class="row mt-2">
    <div class="col-md-6">
        <div class="section-title">
            <i class="fa fa-list-alt"></i>
            Masalah Keperawatan
        </div>
        <?php if (!empty($masalah_keperawatan)): ?>
            <ul class="list-items">
                <?php foreach ($masalah_keperawatan as $masalah): ?>
                    <li><?= htmlspecialchars($masalah['nama_masalah']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state">Tidak ada masalah keperawatan</div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-6">
        <div class="section-title">
            <i class="fa fa-clipboard-list"></i>
            Rencana Keperawatan
        </div>
        <?php if (!empty($rencana_utama) || !empty($rencana_keperawatan)): ?>
            <ul class="list-items">
                <?php foreach ($rencana_utama as $item): ?>
                    <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
                
                <?php foreach ($rencana_keperawatan as $rencana): ?>
                    <li><?= htmlspecialchars($rencana['rencana_keperawatan']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state">Tidak ada rencana keperawatan</div>
        <?php endif; ?>
    </div>
</div>

    </div>
</div>