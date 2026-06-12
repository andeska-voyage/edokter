<?php
include "../../conf/conf.php";

// Parameter
$no_rawat = $_REQUEST['id'];
$no_rm = $_REQUEST['no_rm'];

// Query data penilaian keperawatan ranap
$query_ranap = "
    SELECT 
        p.*,
        pt1.nama as nm_petugas,
        pt2.nama as nm_petugas2,
        d.nm_dokter,
        pas.agama,
        pas.pekerjaan,
        pas.bahasa_pasien,
        pas.pnd,
        pj.png_jawab
    FROM penilaian_awal_keperawatan_ranap p
    LEFT JOIN petugas pt1 ON p.nip1 = pt1.nip
    LEFT JOIN petugas pt2 ON p.nip2 = pt2.nip
    LEFT JOIN dokter d ON p.kd_dokter = d.kd_dokter
    LEFT JOIN reg_periksa rp ON p.no_rawat = rp.no_rawat
    LEFT JOIN pasien pas ON rp.no_rkm_medis = pas.no_rkm_medis
    LEFT JOIN penjab pj ON pas.kd_pj = pj.kd_pj
    WHERE p.no_rawat = '$no_rawat'
    ORDER BY p.tanggal DESC
";

$result_ranap = bukaquery($query_ranap);

if (mysqli_num_rows($result_ranap) == 0) {
    echo '<div class="alert alert-warning m-3">Data penilaian awal keperawatan ranap tidak ditemukan</div>';
    exit;
}
?>

<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<?php
// Loop data ranap
while ($data = mysqli_fetch_assoc($result_ranap)):
    
    // Format tanggal Indonesia
    $bulan = array(
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    );
    $tanggal_obj = strtotime($data['tanggal']);
    $tanggal_format = date('d', $tanggal_obj) . ' ' . 
                     $bulan[date('n', $tanggal_obj)] . ' ' . 
                     date('Y, H:i', $tanggal_obj);
    
    // Query Masalah Keperawatan
    $query_masalah = "
        SELECT mm.nama_masalah 
        FROM penilaian_awal_keperawatan_ranap_masalah pm
        LEFT JOIN master_masalah_keperawatan mm ON pm.kode_masalah = mm.kode_masalah
        WHERE pm.no_rawat = '" . $data['no_rawat'] . "'
        ORDER BY pm.kode_masalah
    ";
    $result_masalah = bukaquery($query_masalah);
    $masalah_list = [];
    while ($row_masalah = mysqli_fetch_assoc($result_masalah)) {
        $masalah_list[] = $row_masalah['nama_masalah'];
    }

    // Query Rencana Keperawatan
    $query_rencana = "
        SELECT mr.rencana_keperawatan 
        FROM penilaian_awal_keperawatan_ranap_rencana pr
        LEFT JOIN master_rencana_keperawatan mr ON pr.kode_rencana = mr.kode_rencana
        WHERE pr.no_rawat = '" . $data['no_rawat'] . "'
        ORDER BY pr.kode_rencana
    ";
    $result_rencana = bukaquery($query_rencana);
    $rencana_list = [];
    while ($row_rencana = mysqli_fetch_assoc($result_rencana)) {
        $rencana_list[] = $row_rencana['rencana_keperawatan'];
    }
?>
<!-- Load CSS Template -->
<link rel="stylesheet" href="<?= APP_BASE_URL ?>/css/template1.css">
<div class="card mb-3 shadow-sm">
    <div class="card-body">
        
        <!-- YANG MELAKUKAN PENGKAJIAN -->
        <div class="section-title">
            <i class="fa fa-user-md"></i> Yang Melakukan Pengkajian
        </div>
        <div class="info-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Tanggal:</span>
                <span class="info-value"><?= $tanggal_format ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Anamnesis:</span>
                <span class="info-value"><?= htmlspecialchars($data['informasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Tiba di Ruang Rawat:</span>
                <span class="info-value"><?= htmlspecialchars($data['tiba_diruang_rawat']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Cara Masuk:</span>
                <span class="info-value"><?= htmlspecialchars($data['cara_masuk']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Macam Kasus:</span>
                <span class="info-value"><?= htmlspecialchars($data['kasus_trauma']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Pengkaji 1:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_petugas']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Pengkaji 2:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_petugas2']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">DPJP:</span>
                <span class="info-value"><?= htmlspecialchars($data['nm_dokter']) ?: '-' ?></span>
            </div>
        </div>

        <!-- I. RIWAYAT KESEHATAN -->
        <div class="section-title">
            <i class="fa fa-notes-medical"></i> I. Riwayat Kesehatan
        </div>
        
        <!-- Grid 2 Kolom untuk Riwayat Penyakit -->
        <div class="info-grid" style="grid-template-columns: 1fr 1fr;">
            <!-- Kolom 1 -->
            <div class="info-item">
                <span class="info-label">Riwayat Penyakit Saat Ini:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rps'])) ?: '-' ?></span>
            </div>
            
            <!-- Kolom 2 -->
            <div class="info-item">
                <span class="info-label">Riwayat Penyakit Dahulu:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rpd'])) ?: '-' ?></span>
            </div>
            
            <!-- Kolom 1 -->
            <div class="info-item">
                <span class="info-label">Riwayat Penyakit Keluarga:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rpk'])) ?: '-' ?></span>
            </div>
            
            <!-- Kolom 2 -->
            <div class="info-item">
                <span class="info-label">Riwayat Penggunaan Obat:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['rpo'])) ?: '-' ?></span>
            </div>
            
            <!-- Kolom 1 -->
            <div class="info-item">
                <span class="info-label">Riwayat Pembedahan:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['riwayat_pembedahan'])) ?: '-' ?></span>
            </div>
            
            <!-- Kolom 2 -->
            <div class="info-item">
                <span class="info-label">Riwayat Dirawat Di RS:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['riwayat_dirawat_dirs'])) ?: '-' ?></span>
            </div>
            
            <!-- Kolom 1 -->
            <div class="info-item">
                <span class="info-label">Alat Bantu Yang Dipakai:</span>
                <span class="info-value"><?= htmlspecialchars($data['alat_bantu_dipakai']) ?: '-' ?></span>
            </div>
            
            <!-- Kolom 2 -->
            <div class="info-item">
                <span class="info-label">Riwayat Tranfusi Darah:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['riwayat_tranfusi'])) ?: '-' ?></span>
            </div>
            
            <!-- Kolom 1 (Riwayat Alergi ambil 1 kolom penuh atau bisa tetap di kolom 1) -->
            <div class="info-item">
                <span class="info-label">Riwayat Alergi:</span>
                <span class="info-value"><?= nl2br(htmlspecialchars($data['riwayat_alergi'])) ?: '-' ?></span>
            </div>
        </div>

        <!-- Kebiasaan - 1 Baris -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Kebiasaan:</span>
                <span class="info-value">
                    Merokok: <?= htmlspecialchars($data['riwayat_merokok']) ?: '-' ?>, <?= htmlspecialchars($data['riwayat_merokok_jumlah']) ?: '-' ?> batang/hari, 
                    Alkohol: <?= htmlspecialchars($data['riwayat_alkohol']) ?: '-' ?>, <?= htmlspecialchars($data['riwayat_alkohol_jumlah']) ?: '-' ?> gelas/hari, 
                    Obat Tidur: <?= htmlspecialchars($data['riwayat_narkoba']) ?: '-' ?>, 
                    Olah Raga: <?= htmlspecialchars($data['riwayat_olahraga']) ?: '-' ?>
                </span>
            </div>
        </div>
        <!-- II. PEMERIKSAAN FISIK -->
        <div class="section-title">
            <i class="fa fa-stethoscope"></i> II. Pemeriksaan Fisik
        </div>
        
        <!-- Baris 1: Kesadaran Mental sampai TB - 1 Kolom -->
        <div class="info-grid" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-value">
                    Kesadaran Mental: <?= htmlspecialchars($data['pemeriksaan_mental']) ?: '-' ?>, 
                    Keadaan Umum: <?= htmlspecialchars($data['pemeriksaan_keadaan_umum']) ?: '-' ?>, 
                    GCS(E,V,M): <?= htmlspecialchars($data['pemeriksaan_gcs']) ?: '-' ?>, 
                    TD: <?= htmlspecialchars($data['pemeriksaan_td']) ?: '-' ?> mmHg, 
                    Nadi: <?= htmlspecialchars($data['pemeriksaan_nadi']) ?: '-' ?> x/menit, 
                    RR: <?= htmlspecialchars($data['pemeriksaan_rr']) ?: '-' ?> x/menit, 
                    Suhu: <?= htmlspecialchars($data['pemeriksaan_suhu']) ?: '-' ?> °C, 
                    SpO2: <?= htmlspecialchars($data['pemeriksaan_spo2']) ?: '-' ?> %, 
                    BB: <?= htmlspecialchars($data['pemeriksaan_bb']) ?: '-' ?> kg, 
                    TB: <?= htmlspecialchars($data['pemeriksaan_tb']) ?: '-' ?> cm
                </span>
            </div>
        </div>

        <!-- Sistem Susunan Saraf Pusat - 3 Kolom -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Sistem Susunan Saraf Pusat:</span>
            </div>
        </div>
        <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Kepala:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_susunan_kepala']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_susunan_kepala_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_susunan_kepala_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Wajah:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_susunan_wajah']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_susunan_wajah_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_susunan_wajah_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Leher:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_susunan_leher']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_susunan_kejang_keterangan'])): ?>
                        , Kejang: <?= htmlspecialchars($data['pemeriksaan_susunan_kejang']) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Kardiovaskuler - 3 Kolom -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Kardiovaskuler:</span>
            </div>
        </div>
        <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Pulsasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kardiovaskuler_pulsasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Sirkulasi:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_kardiovaskuler_sirkulasi']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_kardiovaskuler_sirkulasi_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_kardiovaskuler_sirkulasi_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Denyut Nadi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_kardiovaskuler_denyut_nadi']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Respirasi - 4 Kolom (Baris 1) + 4 Kolom (Baris 2) -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Respirasi:</span>
            </div>
        </div>
        <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item">
                <span class="info-label">Retraksi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_respirasi_retraksi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Pola Nafas:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_respirasi_pola_nafas']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Suara Nafas:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_respirasi_suara_nafas']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Batuk & Sekresi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_respirasi_batuk']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Volume Pernafasan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_respirasi_volume_pernafasan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Jenis Pernafasan:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_respirasi_jenis_pernafasan']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_respirasi_jenis_pernafasan_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_respirasi_jenis_pernafasan_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Irama Nafas:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_respirasi_irama_nafas']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Gastrointestinal - 3 Kolom (Baris 1) + 4 Kolom (Baris 2) -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Gastrointestinal:</span>
            </div>
        </div>
        <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Mulut:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_mulut']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_gastrointestinal_mulut_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_mulut_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Gigi:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_gigi']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_gastrointestinal_gigi_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_gigi_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Lidah:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_lidah']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_gastrointestinal_lidah_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_lidah_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item">
                <span class="info-label">Tenggorokan:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_tenggorokan']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_gastrointestinal_tenggorokan_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_tenggorokan_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Abdomen:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_abdomen']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_gastrointestinal_abdomen_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_gastrointestinal_abdomen_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Peristaltik Usus:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_gastrointestinal_peistatik_usus']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Anus:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_gastrointestinal_anus']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Neurologi - 3 Kolom (Baris 1) + 3 Kolom (Baris 2) -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Neurologi:</span>
            </div>
        </div>
        <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Sensorik:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_neurologi_sensorik']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Penglihatan:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_neurologi_pengelihatan']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_neurologi_pengelihatan_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_neurologi_pengelihatan_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Alat Bantu Penglihatan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_neurologi_alat_bantu_penglihatan']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Pendengaran:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_neurologi_pendengaran']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_neurologi_bicara_keterangan'])): ?>
                        , Bicara: <?= htmlspecialchars($data['pemeriksaan_neurologi_bicara']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Motorik:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_neurologi_motorik']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kekuatan Otot:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_neurologi_kekuatan_otot']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Integumen - 4 Kolom -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Integumen:</span>
            </div>
        </div>
        <div class="info-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="info-item">
                <span class="info-label">Kulit:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_integument_kulit']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Warna Kulit:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_integument_warnakulit']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Turgor:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_integument_turgor']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Resiko Decubitus:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_integument_dekubitas']) ?: '-' ?></span>
            </div>
        </div>

        <!-- Muskuloskeletal - 3 Kolom (Baris 1) + 2 Kolom (Baris 2) -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Muskuloskeletal:</span>
            </div>
        </div>
        <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Oedema:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_muskuloskletal_oedema']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_muskuloskletal_oedema_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_muskuloskletal_oedema_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Pergerakan Sendi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_muskuloskletal_pergerakan_sendi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Kekuatan Otot:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_muskuloskletal_kekauatan_otot']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Fraktur:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_muskuloskletal_fraktur']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_muskuloskletal_fraktur_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_muskuloskletal_fraktur_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Nyeri Sendi:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pemeriksaan_muskuloskletal_nyeri_sendi']) ?: '-' ?>
                    <?php if(!empty($data['pemeriksaan_muskuloskletal_nyeri_sendi_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pemeriksaan_muskuloskletal_nyeri_sendi_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Eliminasi - 3 Kolom per Baris -->
        <div class="info-grid mt-2" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label">Eliminasi:</span>
            </div>
        </div>
        <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">BAB: Frekuensi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_eliminasi_bab_frekuensi_jumlah']) ?: '-' ?> x/<?= htmlspecialchars($data['pemeriksaan_eliminasi_bab_frekuensi_durasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Konsistensi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_eliminasi_bab_konsistensi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Warna:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_eliminasi_bab_warna']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">BAK: Frekuensi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_eliminasi_bak_frekuensi_jumlah']) ?: '-' ?> x/<?= htmlspecialchars($data['pemeriksaan_eliminasi_bak_frekuensi_durasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Warna:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_eliminasi_bak_warna']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Lain-lain:</span>
                <span class="info-value"><?= htmlspecialchars($data['pemeriksaan_eliminasi_bak_lainlain']) ?: '-' ?></span>
            </div>
        </div>

        <!-- III. POLA KEHIDUPAN SEHARI-HARI -->
        <div class="section-title">
            <i class="fa fa-home"></i> III. Pola Kehidupan Sehari-Hari
        </div>
        <div class="info-grid">
            <div class="info-item-vertical">
                <span class="info-label">a. Pola Aktifitas:</span>
            </div>
        </div>
        <div class="info-grid mt-1">
            <div class="info-item">
                <span class="info-label">Mandi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pola_aktifitas_makanminum']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Makan/Minum:</span>
                <span class="info-value"><?= htmlspecialchars($data['pola_aktifitas_makanminum']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Berpakaian:</span>
                <span class="info-value"><?= htmlspecialchars($data['pola_aktifitas_berpakaian']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Eliminasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['pola_aktifitas_eliminasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Berpindah:</span>
                <span class="info-value"><?= htmlspecialchars($data['pola_aktifitas_berpindah']) ?: '-' ?></span>
            </div>
        </div>

        <div class="info-grid mt-2">
            <div class="info-item-vertical">
                <span class="info-label">b. Pola Nutrisi:</span>
            </div>
        </div>
        <div class="info-grid mt-1">
            <div class="info-item">
                <span class="info-label">Porsi Makan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pola_nutrisi_porsi_makan']) ?: '-' ?> porsi</span>
            </div>
            <div class="info-item">
                <span class="info-label">Frekuensi Makan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pola_nutrisi_frekuesi_makan']) ?: '-' ?>x/hari</span>
            </div>
            <div class="info-item">
                <span class="info-label">Jenis Makanan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pola_nutrisi_jenis_makanan']) ?: '-' ?></span>
            </div>
        </div>

        <div class="info-grid mt-2">
            <div class="info-item-vertical">
                <span class="info-label">c. Pola Tidur:</span>
                <span class="info-value">
                    Lama Tidur <?= htmlspecialchars($data['pola_tidur_lama_tidur']) ?: '-' ?> jam/hari, 
                    <?= htmlspecialchars($data['pola_tidur_gangguan']) ?: '-' ?>
                </span>
            </div>
        </div>

        <!-- IV. PENGKAJIAN FUNGSI -->
        <div class="section-title">
            <i class="fa fa-wheelchair"></i> IV. Pengkajian Fungsi
        </div>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">a. Kemampuan Aktifitas Sehari-hari:</span>
                <span class="info-value"><?= htmlspecialchars($data['pengkajian_fungsi_kemampuan_sehari']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">b. Berjalan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pengkajian_fungsi_berjalan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">c. Aktifitas:</span>
                <span class="info-value"><?= htmlspecialchars($data['pengkajian_fungsi_aktifitas']) ?: '-' ?></span>
            </div>
        </div>
        <div class="info-grid mt-1">
            <div class="info-item">
                <span class="info-label">d. Alat Ambulasi:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_ambulasi']) ?: '-' ?>
                    <?php if(!empty($data['pengkajian_fungsi_berjalan_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pengkajian_fungsi_berjalan_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">e. Ekstrimitas Atas:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_ekstrimitas_atas']) ?: '-' ?>
                    <?php if(!empty($data['pengkajian_fungsi_ekstrimitas_atas_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pengkajian_fungsi_ekstrimitas_atas_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">f. Ekstremitas Bawah:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_ekstrimitas_bawah']) ?: '-' ?>
                    <?php if(!empty($data['pengkajian_fungsi_ekstrimitas_bawah_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pengkajian_fungsi_ekstrimitas_bawah_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div class="info-grid mt-1">
            <div class="info-item">
                <span class="info-label">g. Kemampuan Menggenggam:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_menggenggam']) ?: '-' ?>
                    <?php if(!empty($data['pengkajian_fungsi_menggenggam_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pengkajian_fungsi_menggenggam_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">h. Kemampuan Koordinasi:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['pengkajian_fungsi_koordinasi']) ?: '-' ?>
                    <?php if(!empty($data['pengkajian_fungsi_koordinasi_keterangan'])): ?>
                        , <?= htmlspecialchars($data['pengkajian_fungsi_koordinasi_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">i. Kesimpulan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pengkajian_fungsi_kesimpulan']) ?: '-' ?></span>
            </div>
        </div>

        <!-- V. RIWAYAT PSIKOLOGIS - SOSIAL - EKONOMI - BUDAYA - SPIRITUAL -->
        <div class="section-title">
            <i class="fa fa-brain"></i> V. Riwayat Psikologis - Sosial - Ekonomi - Budaya - Spiritual
        </div>
        
        <div class="info-grid" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">a. Kondisi Psikologis:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_psiko_kondisi_psiko']) ?: '-' ?>
                    <?php if(!empty($data['riwayat_psiko_kondisi_psiko_keterangan'])): ?>
                        , <?= htmlspecialchars($data['riwayat_psiko_kondisi_psiko_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">b. Adakah Perilaku:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_psiko_perilaku']) ?: '-' ?>
                    <?php if(!empty($data['riwayat_psiko_perilaku_keterangan'])): ?>
                        , <?= htmlspecialchars($data['riwayat_psiko_perilaku_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">c. Gangguan Jiwa di Masa Lalu:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_psiko_gangguan_jiwa']) ?: '-' ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">d. Hubungan Pasien dengan Anggota Keluarga:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_psiko_hubungan_keluarga']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">e. Agama:</span>
                <span class="info-value"><?= htmlspecialchars($data['agama']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">f. Tinggal Dengan:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_psiko_tinggal']) ?: '-' ?>
                    <?php if(!empty($data['riwayat_psiko_tinggal_keterangan'])): ?>
                        , <?= htmlspecialchars($data['riwayat_psiko_tinggal_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            
            <div class="info-item">
                <span class="info-label">g. Pekerjaan:</span>
                <span class="info-value"><?= htmlspecialchars($data['pekerjaan']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">h. Pembayaran:</span>
                <span class="info-value"><?= htmlspecialchars($data['png_jawab']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">i. Nilai-nilai Kepercayaan/Budaya Yang Perlu Diperhatikan:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_psiko_nilai_kepercayaan']) ?: '-' ?>
                    <?php if(!empty($data['riwayat_psiko_nilai_kepercayaan_keterangan'])): ?>
                        , <?= htmlspecialchars($data['riwayat_psiko_nilai_kepercayaan_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">j. Bahasa Sehari-hari:</span>
                <span class="info-value"><?= htmlspecialchars($data['bahasa_pasien']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">k. Pendidikan Pasien:</span>
                <span class="info-value"><?= htmlspecialchars($data['pnd']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">l. Pendidikan P.J.:</span>
                <span class="info-value"><?= htmlspecialchars($data['riwayat_psiko_pendidikan_pj']) ?: '-' ?></span>
            </div>
            
            <div class="info-item" style="grid-column: span 3;">
                <span class="info-label">m. Edukasi Diberikan Kepada:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['riwayat_psiko_edukasi_diberikan']) ?: '-' ?>
                    <?php if(!empty($data['riwayat_psiko_edukasi_diberikan_keterangan'])): ?>
                        , <?= htmlspecialchars($data['riwayat_psiko_edukasi_diberikan_keterangan']) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

<!-- VI. PENGKAJIAN TINGKAT NYERI -->
        <div class="section-title">
            <i class="fa fa-heartbeat"></i> VI. Pengkajian Tingkat Nyeri
        </div>
        <div class="info-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Tingkat Nyeri:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['penilaian_nyeri']) ?: '-' ?>
                    <?php if($data['penilaian_nyeri'] != 'Tidak Ada Nyeri'): ?>
                        , Waktu / Durasi: <?= htmlspecialchars($data['penilaian_nyeri_waktu']) ?: '-' ?> Menit
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Penyebab:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['penilaian_nyeri_penyebab']) ?: '-' ?>
                    <?php if(!empty($data['penilaian_nyeri_ket_penyebab'])): ?>
                        , <?= htmlspecialchars($data['penilaian_nyeri_ket_penyebab']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Kualitas:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['penilaian_nyeri_kualitas']) ?: '-' ?>
                    <?php if(!empty($data['penilaian_nyeri_ket_kualitas'])): ?>
                        , <?= htmlspecialchars($data['penilaian_nyeri_ket_kualitas']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Severity / Skala Nyeri:</span>
                <span class="info-value"><?= htmlspecialchars($data['penilaian_nyeri_skala']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Wilayah / Lokasi:</span>
                <span class="info-value"><?= htmlspecialchars($data['penilaian_nyeri_lokasi']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Menyebar:</span>
                <span class="info-value"><?= htmlspecialchars($data['penilaian_nyeri_menyebar']) ?: '-' ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nyeri hilang bila:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['penilaian_nyeri_hilang']) ?: '-' ?>
                    <?php if(!empty($data['penilaian_nyeri_ket_hilang'])): ?>
                        , <?= htmlspecialchars($data['penilaian_nyeri_ket_hilang']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Diberitahukan pada dokter:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['penilaian_nyeri_diberitahukan_dokter']) ?: '-' ?>
                    <?php if($data['penilaian_nyeri_diberitahukan_dokter'] == 'Ya'): ?>
                        , Jam: <?= htmlspecialchars($data['penilaian_nyeri_jam_diberitahukan_dokter']) ?: '-' ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- VII. PENGKAJIAN RESIKO JATUH -->
        <div class="section-title">
            <i class="fa fa-exclamation-triangle"></i> VII. Pengkajian Resiko Jatuh
        </div>
        
        <!-- Skala Morse -->
        <div class="info-grid" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label"><strong>Skala Morse:</strong></span>
            </div>
        </div>
        
        <table class="table table-bordered mt-2">
            <thead>
                <tr>
                    <th width="60%">Faktor Resiko</th>
                    <th width="20%">Skala</th>
                    <th width="20%">Poin</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1. Riwayat Jatuh</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_skala1']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_nilai1']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>2. Diagnosis Sekunder (≥ 2 Diagnosis Medis)</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_skala2']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_nilai2']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>3. Alat Bantu</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_skala3']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_nilai3']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>4. Terpasang Infuse</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_skala4']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_nilai4']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>5. Gaya Berjalan</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_skala5']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_nilai5']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>6. Status Mental</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_skala6']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhmorse_nilai6']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="text-end"><strong>Total:</strong></td>
                    <td><strong><?= htmlspecialchars($data['penilaian_jatuhmorse_totalnilai']) ?: '0' ?></strong></td>
                </tr>
            </tbody>
        </table>
        <p class="mt-2">Tingkat Resiko: 'Risiko Rendah (0-24), Tindakan : Intervensi pencegahan risiko jatuh standar'</strong></p>

        <!-- Skala Sydney -->
        <div class="info-grid mt-3" style="grid-template-columns: 1fr;">
            <div class="info-item">
                <span class="info-label"><strong>Skala Sydney:</strong></span>
            </div>
        </div>
        
        <table class="table table-bordered mt-2">
            <thead>
                <tr>
                    <th width="60%">Faktor Resiko</th>
                    <th width="20%">Skala</th>
                    <th width="20%">Poin</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1. Gangguan Gaya Berjalan (Diseret, Menghentak, Diayun)</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala1']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai1']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>2. Pusing / Pingsan Pada Posisi Tegak</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala2']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai2']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>3. Kebingungan Setiap Saat</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala3']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai3']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>4. Nokturia / Inkontinen</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala4']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai4']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>5. Kebingungan Intermiten</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala5']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai5']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>6. Kelemahan Umum</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala6']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai6']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>7. Obat-obat Beresiko Tinggi (Diuretic, Narkotik, Sedativ, Anti Psikotik, Laksatif, Vasodilator Antiaritmia, Antihipertensi, Obat Hipoglikemik, Anti Depresan, Neuroleptik, NSAID)</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala7']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai7']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>8. Riwayat Jatuh Dalam Waktu 12 Bulan Sebelumnya</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala8']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai8']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>9. Osteoporosis</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala9']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai9']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>10. Gangguan Pendengaran Dan Atau Penglihatan</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala10']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai10']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td>11. Usia 70 Tahun Ke Atas</td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_skala11']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($data['penilaian_jatuhsydney_nilai11']) ?: '0' ?></td>
                </tr>
                <tr>
                    <td colspan="2" class="text-end"><strong>Total:</strong></td>
                    <td><strong><?= htmlspecialchars($data['penilaian_jatuhsydney_totalnilai']) ?: '0' ?></strong></td>
                </tr>
            </tbody>
        </table>
        <p class="mt-2">Tingkat Resiko: 'Risiko Rendah (1-3), Tindakan : Intervensi pencegahan risiko jatuh standar'</strong></p>

        <!-- VIII. SKRINING GIZI -->
        <div class="section-title">
            <i class="fa fa-utensils"></i> VIII. Skrining Gizi
        </div>
        <table class="table table-bordered mt-2">
            <thead>
                <tr>
                    <th width="10%">No</th>
                    <th width="60%">Parameter</th>
                    <th width="30%">Nilai</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Apakah ada penurunan BB yang tidak diinginkan selama 6 bulan terakhir?</td>
                    <td><?= htmlspecialchars($data['skrining_gizi1']) ?: '-' ?> (<?= htmlspecialchars($data['nilai_gizi1']) ?: '0' ?>)</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Apakah asupan makan berkurang karena tidak nafsu makan?</td>
                    <td><?= htmlspecialchars($data['skrining_gizi2']) ?: '-' ?> (<?= htmlspecialchars($data['nilai_gizi2']) ?: '0' ?>)</td>
                </tr>
                <tr>
                    <td colspan="2" class="text-end"><strong>Total Skor:</strong></td>
                    <td><strong><?= htmlspecialchars($data['nilai_total_gizi']) ?: '0' ?></strong></td>
                </tr>
            </tbody>
        </table>
        <div class="info-grid mt-2" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item">
                <span class="info-label">Pasien dengan diagnosa khusus:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['skrining_gizi_diagnosa_khusus']) ?: '-' ?>
                    <?php if($data['skrining_gizi_diagnosa_khusus'] == 'Ya'): ?>
                        , Ket: <?= htmlspecialchars($data['skrining_gizi_ket_diagnosa_khusus']) ?: '-' ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Sudah dibaca dan diketahui oleh Dietisen:</span>
                <span class="info-value">
                    <?= htmlspecialchars($data['skrining_gizi_diketahui_dietisen']) ?: '-' ?>
                    <?php if($data['skrining_gizi_diketahui_dietisen'] == 'Ya'): ?>
                        , Jam: <?= htmlspecialchars($data['skrining_gizi_jam_diketahui_dietisen']) ?: '-' ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- MASALAH & RENCANA KEPERAWATAN -->
        <div class="section-title">
            <i class="fa fa-clipboard-list"></i> Masalah Keperawatan & Rencana Keperawatan
        </div>
        <div class="info-grid mt-2" style="grid-template-columns: 1fr 1fr;">
            <div class="info-item-vertical">
                <span class="info-label">Masalah Keperawatan:</span>
                <span class="info-value">
                    <?php if (!empty($masalah_list)): ?>
                        <?php foreach ($masalah_list as $index => $masalah): ?>
                            <?= ($index + 1) ?>. <?= htmlspecialchars($masalah) ?><br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item-vertical">
                <span class="info-label">Rencana Keperawatan:</span>
                <span class="info-value">
                    <?php if (!empty($rencana_list)): ?>
                        <?php foreach ($rencana_list as $index => $rencana): ?>
                            <?= ($index + 1) ?>. <?= htmlspecialchars($rencana) ?><br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </span>
            </div>
        </div>

<?php endwhile; ?>