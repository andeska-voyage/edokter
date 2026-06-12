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

// Query data penilaian keperawatan kebidanan
$query_kebidanan = "
    SELECT 
        p.*,
        pt.nama as nm_petugas,
        pr.no_rkm_medis
    FROM penilaian_awal_keperawatan_kebidanan p
    LEFT JOIN petugas pt ON p.nip = pt.nip
    LEFT JOIN reg_periksa pr ON p.no_rawat = pr.no_rawat
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_kebidanan = bukaquery($query_kebidanan);

if (mysqli_num_rows($result_kebidanan) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian keperawatan kebidanan tidak ditemukan</div>';
    exit;
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">

<?php
// Loop data
while ($data = mysqli_fetch_assoc($result_kebidanan)):
    // Format tanggal Indonesia
    $bulan = array(
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d', $tanggal_obj) . ' ' . 
                     $bulan[date('n', $tanggal_obj)] . ' ' . 
                     date('Y, H:i', $tanggal_obj);
    
    // Query Riwayat Kehamilan Tetap (riwayat_persalinan_pasien)
    $no_rkm_medis = $data['no_rkm_medis'];
    $query_persalinan = "
        SELECT 
            tgl_thn,
            tempat_persalinan,
            usia_hamil,
            jenis_persalinan,
            penolong,
            penyulit,
            jk,
            bbpb,
            keadaan
        FROM riwayat_persalinan_pasien
        WHERE no_rkm_medis = '$no_rkm_medis'
        ORDER BY tgl_thn DESC
    ";
    $result_persalinan = bukaquery($query_persalinan);
    $riwayat_persalinan = [];
    while ($row = mysqli_fetch_assoc($result_persalinan)) {
        $riwayat_persalinan[] = $row;
    }
?>

<div class="card mb-3 shadow-sm">
    <div class="card-body">
        <!-- I. KEADAAN UMUM -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> I. Keadaan Umum
        </div>
        <div class="info-grid" style="grid-template-columns: repeat(5, 1fr);">
            <div class="info-item">
                <span class="info-label">TD:</span>
                <span class="info-value"><?= htmlspecialchars($data['td']) ?: '-' ?> mmHg</span>
            </div>
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
                <span class="info-label">GCS:</span>
                <span class="info-value"><?= htmlspecialchars($data['gcs']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">BB:</span>
                <span class="info-value"><?= htmlspecialchars($data['bb']) ?: '-' ?> kg</span>
            </div>
            <div class="info-item">
                <span class="info-label">TB:</span>
                <span class="info-value"><?= htmlspecialchars($data['tb']) ?: '-' ?> cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">LILA:</span>
                <span class="info-value"><?= htmlspecialchars($data['lila']) ?: '-' ?> cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">BMI:</span>
                <span class="info-value"><?= htmlspecialchars($data['bmi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- II. PEMERIKSAAN KEBIDANAN -->
        <div class="section-title">
            <i class="fa fa-baby"></i> II. Pemeriksaan Kebidanan
        </div>
        <div class="info-grid" style="grid-template-columns: repeat(5, 1fr);">
            <div class="info-item">
                <span class="info-label">TFU:</span>
                <span class="info-value"><?= htmlspecialchars($data['tfu']) ?: '-' ?> cm</span>
            </div>
            <div class="info-item">
                <span class="info-label">TBJ:</span>
                <span class="info-value"><?= htmlspecialchars($data['tbj']) ?: '-' ?> gram</span>
            </div>
            <div class="info-item">
                <span class="info-label">Letak:</span>
                <span class="info-value"><?= htmlspecialchars($data['letak']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Presentasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['presentasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Penurunan:</span>
                <span class="info-value"><?= htmlspecialchars($data['penurunan']) ?: '-' ?></span>
            </div>
        </div>
        
        <div class="info-grid mt-1" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item">
                <span class="info-label">HIS:</span>
                <span class="info-value"><?= htmlspecialchars($data['his']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kekuatan:</span>
                <span class="info-value"><?= htmlspecialchars($data['kekuatan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Lamanya:</span>
                <span class="info-value"><?= htmlspecialchars($data['lamanya']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">BJJ:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['bjj']) ?: '-' ?> <?php if (!empty($data['ket_bjj'])): ?><?= htmlspecialchars($data['ket_bjj']) ?><?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Portio:</span>
                <span class="info-value"><?= htmlspecialchars($data['portio']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Serviks:</span>
                <span class="info-value"><?= htmlspecialchars($data['serviks']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ketuban:</span>
                <span class="info-value"><?= htmlspecialchars($data['ketuban']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Hodge:</span>
                <span class="info-value"><?= htmlspecialchars($data['hodge']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Pemeriksaan Penunjang -->
        <div class="section-title">
            <i class="fa fa-vial"></i> Pemeriksaan Penunjang
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">Inspekulo:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['inspekulo']) ?: '-' ?>
                    <?php if (!empty($data['ket_inspekulo'])): ?>
                        <br><small>Hasil: <?= htmlspecialchars($data['ket_inspekulo']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">CTG:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['ctg']) ?: '-' ?>
                    <?php if (!empty($data['ket_ctg'])): ?>
                        <br><small>Hasil: <?= htmlspecialchars($data['ket_ctg']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">USG:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['usg']) ?: '-' ?>
                    <?php if (!empty($data['ket_usg'])): ?>
                        <br><small>Hasil: <?= htmlspecialchars($data['ket_usg']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Laboratorium:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['lab']) ?: '-' ?>
                    <?php if (!empty($data['ket_lab'])): ?>
                        <br><small>Hasil: <?= htmlspecialchars($data['ket_lab']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Lakmus:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['lakmus']) ?: '-' ?>
                    <?php if (!empty($data['ket_lakmus'])): ?>
                        <br><small>Hasil: <?= htmlspecialchars($data['ket_lakmus']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Panggul:</span>
                <span class="info-value"><?= htmlspecialchars($data['panggul']) ?: '-' ?></span>
            </div>
        </div>

        <!-- III. RIWAYAT KESEHATAN -->
        <div class="section-title">
            <i class="fa fa-notes-medical"></i> III. Riwayat Kesehatan
        </div>
        
        <!-- Keluhan Utama - Full Width -->
        <div class="info-grid" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Keluhan Utama:</span>
                <span class="info-value"><?= htmlspecialchars($data['keluhan_utama']) ?: '-' ?></span>
            </div>
        </div>
        
        <!-- Riwayat - 2 Kolom -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Riwayat Menstruasi</span>
            </div>
            <div class="info-item">
                <span class="info-value">
                    Umur Menarche: <?= htmlspecialchars($data['umur']) ?: '-' ?> tahun, 
                    lamanya: <?= htmlspecialchars($data['lama']) ?: '-' ?> hari, 
                    banyaknya: <?= htmlspecialchars($data['banyaknya']) ?: '-' ?> pembalut, 
                    Haid Terakhir: <?= htmlspecialchars($data['haid']) ?: '-' ?>, 
                    Siklus: <?= htmlspecialchars($data['siklus']) ?: '-' ?> hari, 
                    ( <?= htmlspecialchars($data['ket_siklus']) ?: '-' ?> ), 
                    <?= htmlspecialchars($data['ket_siklus1']) ?: '-' ?>
                </span>
            </div>
        </div>

        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Riwayat Perkawinan</span>
            </div>
            <div class="info-item">
                <span class="info-value">
                    Status: <?= htmlspecialchars($data['status']) ?: '-' ?>, 
                    Kawin: <?= htmlspecialchars($data['kali']) ?: '-' ?> kali, 
                    Usia Hamil 1: <?= htmlspecialchars($data['usia1']) ?: '-' ?> tahun, 
                    Status: <?= htmlspecialchars($data['ket1']) ?: '-' ?>, 
                    Usia Hamil 2: <?= htmlspecialchars($data['usia2']) ?: '-' ?> tahun, 
                    Status: <?= htmlspecialchars($data['ket2']) ?: '-' ?>, 
                    Usia Hamil 3: <?= htmlspecialchars($data['usia3']) ?: '-' ?> tahun, 
                    Status: <?= htmlspecialchars($data['ket3']) ?: '-' ?>
                </span>
            </div>
        </div>

        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Riwayat Kehamilan Tetap</span>
            </div>
            <div class="info-item">
                <span class="info-value">
                    HPHT: <?= !empty($data['hpht']) && $data['hpht'] != '0000-00-00' ? date('d-m-Y', strtotime($data['hpht'])) : '-' ?>, 
                    Usia Kehamilan: <?= htmlspecialchars($data['usia_kehamilan']) ?: '-' ?> b/m/mg, 
                    TP: <?= !empty($data['tp']) && $data['tp'] != '0000-00-00' ? date('d-m-Y', strtotime($data['tp'])) : '-' ?>, 
                    Riwayat Imunisasi: <?= htmlspecialchars($data['imunisasi']) ?: '-' ?>, 
                    <?= htmlspecialchars($data['ket_imunisasi']) ?: '-' ?> kali, 
                    G: <?= htmlspecialchars($data['g']) ?: '-' ?>, 
                    P: <?= htmlspecialchars($data['p']) ?: '-' ?>, 
                    A: <?= htmlspecialchars($data['a']) ?: '-' ?>, 
                    Hidup: <?= htmlspecialchars($data['hidup']) ?: '-' ?>
                </span>
            </div>
        </div>

        <!-- Tabel Riwayat Kehamilan Tetap -->
        <?php if (!empty($riwayat_persalinan)): ?>
        <div class="mt-2">
            <div class="table-responsive">
                <table class="table table-bordered table-sm" style="font-size: 11px;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 5%;">No</th>
                            <th style="width: 12%;">Tgl/Thn Persalinan</th>
                            <th style="width: 18%;">Tempat Persalinan</th>
                            <th style="width: 10%;">Usia Hamil</th>
                            <th style="width: 15%;">Jenis Persalinan</th>
                            <th style="width: 12%;">Penolong</th>
                            <th style="width: 10%;">Penyulit</th>
                            <th style="width: 5%;">JK</th>
                            <th style="width: 8%;">BB/PB</th>
                            <th style="width: 10%;">Keadaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($riwayat_persalinan as $rp): 
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= htmlspecialchars($rp['tgl_thn']) ?></td>
                            <td><?= htmlspecialchars($rp['tempat_persalinan']) ?></td>
                            <td><?= htmlspecialchars($rp['usia_hamil']) ?></td>
                            <td><?= htmlspecialchars($rp['jenis_persalinan']) ?></td>
                            <td><?= htmlspecialchars($rp['penolong']) ?></td>
                            <td><?= htmlspecialchars($rp['penyulit']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($rp['jk']) ?></td>
                            <td><?= htmlspecialchars($rp['bbpb']) ?></td>
                            <td><?= htmlspecialchars($rp['keadaan']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Riwayat Ginekologi</span>
            </div>
            <div class="info-item">
                <span class="info-value"><?= htmlspecialchars($data['ginekologi']) ?: '-' ?></span>
            </div>
        </div>

        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Riwayat Kebiasaan</span>
            </div>
            <div class="info-item">
                <span class="info-value"><?= htmlspecialchars($data['kebiasaan']) ?: '-' ?></span>
            </div>
        </div>

        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Riwayat K.B.</span>
            </div>
            <div class="info-item">
                <span class="info-value">
                    <?= htmlspecialchars($data['kb']) ?: '-' ?>, 
                    Lamanya: <?= htmlspecialchars($data['ket_kb']) ?: '-' ?>, 
                    Komplikasi: <?= htmlspecialchars($data['komplikasi']) ?: '-' ?>, 
                    <?= htmlspecialchars($data['ket_komplikasi']) ?: '-' ?>, 
                    Berhenti: <?= htmlspecialchars($data['berhenti']) ?: '-' ?>, 
                    Alasan: <?= htmlspecialchars($data['alasan']) ?: '-' ?>
                </span>
            </div>
        </div>

        <!-- IV. FUNGSIONAL -->
        <div class="section-title">
            <i class="fa fa-walking"></i> IV. Fungsional
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">Alat Bantu:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['alat_bantu']) ?: '-' ?>
                    <?php if (!empty($data['ket_bantu'])): ?>
                        <br><small>Ket: <?= htmlspecialchars($data['ket_bantu']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Prothesa:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['prothesa']) ?: '-' ?>
                    <?php if (!empty($data['ket_pro'])): ?>
                        <br><small>Ket: <?= htmlspecialchars($data['ket_pro']) ?></small>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">ADL:</span>
                <span class="info-value"><?= htmlspecialchars($data['adl']) ?: '-' ?></span>
            </div>
        </div>

        <!-- V. RIWAYAT PSIKO-SOSIAL, SPIRITUAL DAN BUDAYA -->
        <div class="section-title">
            <i class="fa fa-brain"></i> V. Riwayat Psiko-Sosial, Spiritual dan Budaya
        </div>
        <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item">
                <span class="info-label">Status Psikologis:</span>
                <span class="info-value"><?= htmlspecialchars($data['status_psiko']) ?: '-' ?></span>
            </div>
            <div class="info-item" style="grid-column: span 3;">
                <span class="info-label">Ket. Psiko:</span>
                <span class="info-value"><?= htmlspecialchars($data['ket_psiko']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Status Sosial dan Ekonomi:</span>
                <span class="info-value"><?= htmlspecialchars($data['ekonomi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tinggal Dengan:</span>
                <span class="info-value"><?= htmlspecialchars($data['tinggal_dengan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ket. Tinggal:</span>
                <span class="info-value"><?= htmlspecialchars($data['ket_tinggal']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">a. Hub. pasien dengan anggota keluarga:</span>
                <span class="info-value"><?= htmlspecialchars($data['hub_keluarga']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">b. Tinggal dengan:</span>
                <span class="info-value"><?= htmlspecialchars($data['tinggal_dengan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">c. Ekonomi:</span>
                <span class="info-value"><?= htmlspecialchars($data['ekonomi']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Kepercayaan / Budaya / Nilai-nilai khusus yang perlu diperhatikan:</span>
                <span class="info-value"><?= htmlspecialchars($data['budaya']) ?: '-' ?></span>
            </div>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Ket. Budaya:</span>
                <span class="info-value"><?= htmlspecialchars($data['ket_budaya']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Edukasi diberikan kepada:</span>
                <span class="info-value"><?= htmlspecialchars($data['edukasi']) ?: '-' ?></span>
            </div>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Ket. Edukasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['ket_edukasi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- VI. PENGKAJIAN RESIKO JATUH -->
        <div class="section-title">
            <i class="fa fa-exclamation-triangle"></i> VI. Pengkajian Resiko Jatuh
        </div>
        <div class="info-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">a. Cara Berjalan:</span>
            </div>
            <div class="info-item">
                <span class="info-value">&nbsp;</span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">1. Tidak seimbang / sempoyongan / limbung:</span>
            </div>
            <div class="info-item">
                <span class="info-value"><?= htmlspecialchars($data['berjalan_a']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">2. Jalan dengan menggunakan alat bantu (kruk, tripod, kursi roda, orang lain):</span>
            </div>
            <div class="info-item">
                <span class="info-value"><?= htmlspecialchars($data['berjalan_b']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">b. Menopang saat akan duduk, tampak memegang pinggiran kursi atau meja / benda lain sebagai penopang:</span>
            </div>
            <div class="info-item">
                <span class="info-value"><?= htmlspecialchars($data['berjalan_c']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Hasil:</span>
                <span class="info-value"><?= htmlspecialchars($data['hasil']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-value">
                    Dilaporkan kepada dokter? <?= htmlspecialchars($data['lapor']) ?: '-' ?>, 
                    Jam Dilaporkan: <?= htmlspecialchars($data['ket_lapor']) ?: '-' ?>
                </span>
            </div>
        </div>

        <!-- VII. SKRINING GIZI -->
        <div class="section-title">
            <i class="fa fa-utensils"></i> VII. Skrining Gizi
        </div>
        <div class="info-grid">
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">1. Apakah ada penurunan berat badan yang tidak diinginkan selama enam bulan terakhir?</span>
                <span class="info-value"><?= htmlspecialchars($data['sg1']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nilai:</span>
                <span class="info-value"><?= htmlspecialchars($data['nilai1']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1">
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">2. Apakah nafsu makan berkurang karena tidak nafsu makan?</span>
                <span class="info-value"><?= htmlspecialchars($data['sg2']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nilai:</span>
                <span class="info-value"><?= htmlspecialchars($data['nilai2']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1">
            <div class="info-item" style="grid-column: span 3;">
                <span class="info-label">Total Skor:</span>
                <span class="info-value"><strong><?= htmlspecialchars($data['total_hasil']) ?: '-' ?></strong></span>
            </div>
        </div>

        <!-- VIII. PENGKAJIAN TINGKAT NYERI -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> VIII. Pengkajian Tingkat Nyeri
        </div>
        <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item">
                <span class="info-label">Tingkat Nyeri:</span>
                <span class="info-value"><?= htmlspecialchars($data['nyeri']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Waktu / Durasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['durasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kualitas:</span>
                <span class="info-value"><?= htmlspecialchars($data['quality']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Ket. Quality:</span>
                <span class="info-value"><?= htmlspecialchars($data['ket_quality']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item">
                <span class="info-label">Severity:</span>
                <span class="info-value"><?= htmlspecialchars($data['skala_nyeri']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Skala Nyeri:</span>
                <span class="info-value"><strong><?= htmlspecialchars($data['skala_nyeri']) ?: '-' ?></strong></span>
            </div>
            <div class="info-item">
                <span class="info-label">Wilayah:</span>
                <span class="info-value"><?= htmlspecialchars($data['lokasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Lokasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['lokasi']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item">
                <span class="info-label">Menyebar:</span>
                <span class="info-value"><?= htmlspecialchars($data['menyebar']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Penyebab:</span>
                <span class="info-value"><?= htmlspecialchars($data['provokes']) ?: '-' ?></span>
            </div>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Ket. Provokes:</span>
                <span class="info-value"><?= htmlspecialchars($data['ket_provokes']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Nyeri hilang bila:</span>
                <span class="info-value"><?= htmlspecialchars($data['nyeri_hilang']) ?: '-' ?></span>
            </div>
            <div class="info-item" style="grid-column: span 2;">
                <span class="info-label">Diberitahukan pada dokter:</span>
                <span class="info-value"><?= htmlspecialchars($data['pada_dokter']) ?: '-' ?></span>
            </div>
        </div>

        <!-- MASALAH KEBIDANAN & TINDAKAN -->
        <div class="section-title">
            <i class="fa fa-clipboard-list"></i> Masalah Kebidanan & Tindakan
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="info-item-vertical">
                    <span class="info-label">Masalah Kebidanan:</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($data['masalah'])) ?: '-' ?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-item-vertical">
                    <span class="info-label">Tindakan:</span>
                    <span class="info-value"><?= nl2br(htmlspecialchars($data['tindakan'])) ?: '-' ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endwhile; ?>