<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
define('BASE_URL', APP_BASE_URL);

// Decrypt parameter dari URL
$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm = isset($_GET['rm']) ? $_GET['rm'] : '';

$no_rawat = '';
$no_rkm_medis = '';

if(!empty($encrypted_norawat)) {
    $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
}

if(!empty($encrypted_norm)) {
    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');
}

// Ambil data pasien dari kamar_inap (neonatus)
$queryPasien = bukaquery("SELECT 
                            ki.no_rawat,
                            rp.no_rkm_medis,
                            ki.tgl_masuk,
                            ki.jam_masuk,
                            p.nm_pasien,
                            p.jk,
                            p.tmp_lahir,
                            p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                            d.nm_dokter,
                            d.kd_dokter,
                            kms.kd_kamar,
                            bng.nm_bangsal
                        FROM kamar_inap ki
                        INNER JOIN reg_periksa rp ON ki.no_rawat = rp.no_rawat
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN dpjp_ranap dpjp ON ki.no_rawat = dpjp.no_rawat
                        LEFT JOIN dokter d ON dpjp.kd_dokter = d.kd_dokter
                        LEFT JOIN kamar kms ON ki.kd_kamar = kms.kd_kamar
                        LEFT JOIN bangsal bng ON kms.kd_bangsal = bng.kd_bangsal
                        WHERE ki.no_rawat = '$no_rawat'
                        ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
                        LIMIT 1");

$rsPasien = mysqli_fetch_array($queryPasien);

if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>";
    exit;
}

// Cek apakah sudah ada data penilaian medis neonatus dengan info dokter
$queryCheck = bukaquery("SELECT pm.*, d.nm_dokter as nama_dokter_pengisi 
                         FROM penilaian_medis_ranap_neonatus pm 
                         LEFT JOIN dokter d ON pm.kd_dokter = d.kd_dokter 
                         WHERE pm.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Ambil kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Ambil nama ibu jika ada no_rkm_medis_ibu
$nm_ibu = '';
if($isEdit && !empty($rsCheck['no_rkm_medis_ibu'])) {
    $queryIbu = bukaquery("SELECT nm_pasien FROM pasien WHERE no_rkm_medis = '".$rsCheck['no_rkm_medis_ibu']."'");
    $rsIbu = mysqli_fetch_array($queryIbu);
    if($rsIbu) {
        $nm_ibu = $rsIbu['nm_pasien'];
    }
}

// Data default untuk neonatus
$data = array(
    'tanggal' => date('Y-m-d H:i:s'),
    'kd_dokter' => $kd_dokter_login,
    'no_rkm_medis_ibu' => '',
    'nm_ibu' => $nm_ibu,
    'g' => '', 'p' => '', 'a' => '', 'hidup' => '', 'usiahamil' => '',
    'hbsag' => 'Negatif (-)', 'hiv' => 'Negatif (-)', 'syphilis' => 'Negatif (-)',
    'riwayat_obstetri_ibu' => 'Tidak Ada', 'keterangan_riwayat_obstetri_ibu' => '',
    'faktor_risiko_neonatal' => 'Tidak Ada', 'keterangan_faktor_risiko_neonatal' => '',
    'tanggal_persalinan' => date('Y-m-d H:i:s'), 'bersalin_di' => '',
    'inisiasi_menyusui' => 'Ya', 'jenis_persalinan' => 'Spontan/Normal', 'indikasi' => '',
    'aterm' => 'Ya', 'bernafas' => 'Ya', 'tanus_otot' => 'Ya', 'cairan_amnion' => 'Ya',
    'f1' => '', 'u1' => '', 't1' => '', 'r1' => '', 'w1' => '', 'n1' => '',
    'f5' => '', 'u5' => '', 't5' => '', 'r5' => '', 'w5' => '', 'n5' => '',
    'f10' => '', 'u10' => '', 't10' => '', 'r10' => '', 'w10' => '', 'n10' => '',
    'frekuensi_napas' => '< 60', 'nilai_frekuensi_napas' => 0,
    'retraksi' => 'Tidak Ada', 'nilai_retraksi' => 0,
    'sianosis' => 'Tidak Ada', 'nilai_sianosis' => 0,
    'jalan_masuk_udara' => 'Baik', 'nilai_jalan_masuk_udara' => 0,
    'grunting' => 'Tidak Ada', 'nilai_grunting' => 0,
    'total_down_score' => 0, 'keterangan_down_Score' => '',
    'nadi' => '', 'rr' => '', 'suhu' => '', 'saturasi' => '',
    'bb' => '', 'pb' => '', 'lk' => '', 'ld' => '',
    'keadaan_umum' => 'Normal', 'keterangan_keadaan_umum' => '',
    'kulit' => 'Normal', 'keterangan_kulit' => '',
    'kepala' => 'Normal', 'keterangan_kepala' => '',
    'mata' => 'Normal', 'keterangan_mata' => '',
    'telinga' => 'Normal', 'keterangan_telinga' => '',
    'hidung' => 'Normal', 'keterangan_hidung' => '',
    'mulut' => 'Normal', 'keterangan_mulut' => '',
    'tenggorokan' => 'Normal', 'keterangan_tenggorokan' => '',
    'leher' => 'Normal', 'keterangan_leher' => '',
    'thorax' => 'Normal', 'keterangan_thorax' => '',
    'abdomen' => 'Normal', 'keterangan_abdomen' => '',
    'genitalia' => 'Normal', 'keterangan_genitalia' => '',
    'anus' => 'Normal', 'keterangan_anus' => '',
    'muskulos' => 'Normal', 'keterangan_muskulos' => '',
    'ekstrimitas' => 'Normal', 'keterangan_ekstrimitas' => '',
    'paru' => 'Normal', 'keterangan_paru' => '',
    'refleks' => 'Normal', 'keterangan_refleks' => '',
    'kelainan_lainnya' => '', 'pemeriksaan_regional' => '',
    'lab' => '', 'radiologi' => '', 'penunjanglainnya' => '',
    'diagnosis' => '', 'tata' => '', 'edukasi' => ''
);

if($isEdit) {
    $data = array_merge($data, $rsCheck);
    $data['nm_ibu'] = $nm_ibu;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<style>
/* APGAR Score Table */
.apgar-table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 10px 0; }
.apgar-table th, .apgar-table td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: center; }
.apgar-table th { background: #f8fafc; font-weight: 600; color: #475569; font-size: 10px; }
.apgar-table td { background: #fff; font-size: 10px; }
.apgar-table input { width: 35px; padding: 4px; border: 1px solid #e2e8f0; border-radius: 4px; text-align: center; font-size: 11px; }
.apgar-table .total-cell { background: #f0fdf4; font-weight: 600; color: #166534; }

/* Status Kelainan Grid */
.status-kelainan-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.status-kelainan-item { display: grid; grid-template-columns: 90px 95px 1fr; gap: 5px; align-items: center; padding: 6px 8px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; }
.status-kelainan-item label { font-size: 10px; font-weight: 500; color: #475569; }
.status-kelainan-item select, .status-kelainan-item input { padding: 4px 6px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 10px; }

/* Riwayat Persalinan Table */
.riwayat-table-container { max-height: 150px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 8px; }
.riwayat-table { width: 100%; border-collapse: collapse; font-size: 10px; }
.riwayat-table th { background: #f1f5f9; padding: 6px 8px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; font-size: 9px; }
.riwayat-table td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
.riwayat-table tr:hover { background: #f8fafc; }

/* Autocomplete */
.autocomplete-wrapper { position: relative; }
.autocomplete-items { position: absolute; border: 1px solid #e2e8f0; border-top: none; z-index: 99; top: 100%; left: 0; right: 0; max-height: 200px; overflow-y: auto; background: white; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; }
.autocomplete-items div { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
.autocomplete-items div:hover { background-color: #f0f9ff; }
.autocomplete-items div strong { color: #1e40af; }
.autocomplete-items .no-result { color: #94a3b8; text-align: center; font-style: italic; }

/* Down Score Grid */
.down-score-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
.down-score-item { background: #f8fafc; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0; }
.down-score-item label { font-size: 9px; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px; }
.down-score-item select, .down-score-item input { width: 100%; padding: 5px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 10px; }
.down-score-total { background: #f0fdf4; border-color: #86efac; }

/* Input with unit */
.input-with-unit { display: flex; align-items: center; }
.input-with-unit input { border-radius: 4px 0 0 4px !important; border-right: none !important; flex: 1; }
.input-with-unit .unit { padding: 7px 10px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 0 4px 4px 0; font-size: 11px; color: #64748b; white-space: nowrap; }

@media (max-width: 1400px) { .status-kelainan-grid { grid-template-columns: repeat(2, 1fr); } .down-score-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) { .status-kelainan-grid { grid-template-columns: 1fr; } .status-kelainan-item { grid-template-columns: 1fr; gap: 4px; } .down-score-grid { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 10px;">
            <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                <i class="material-icons">child_care</i>
                PENILAIAN AWAL MEDIS NEONATUS
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
            
            <!-- Compact Progress Bar -->
            <div style="display: flex; align-items: center; gap: 10px; background: #f8f9fa; border-radius: 8px; padding: 8px 12px;">
                <i class="material-icons" style="font-size: 18px; color: #6c757d;">assessment</i>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <span style="font-size: 11px; color: #6c757d; font-weight: 500;">Kelengkapan</span>
                        <span id="progress-text-neonatus" style="font-weight: bold; font-size: 14px; color: #6c757d;">0%</span>
                    </div>
                    <div style="width: 150px; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                        <div id="progress-bar-neonatus" style="width: 0%; height: 100%; transition: width 0.3s ease, background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-neonatus" style="font-size: 10px; color: #6c757d; white-space: nowrap;">(0/0)</span>
            </div>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <div class="info-item"><i class="material-icons">wc</i><strong>JK:</strong> <?php echo ($rsPasien['jk'] == 'L') ? 'Laki-laki' : 'Perempuan'; ?></div>
            <div class="info-item"><i class="material-icons">hotel</i><strong>Bangsal:</strong> <?php echo $rsPasien['nm_bangsal'] ?? '-'; ?></div>
            <?php if($isEdit && !empty($nama_dokter_pengisi)): ?>
            <div class="info-item"><i class="material-icons">person</i><strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPenilaianMedisNeonatus" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">
                
                <!-- I. ANAMNESIS -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">pregnant_woman</i>
                        <h2>I. ANAMNESIS</h2>
                    </div>
                    
                    <div class="form-grid cols-3" style="grid-template-columns: 1fr 1fr 1fr 1fr;">
                        <div class="form-group">
                            <label>Tanggal & Waktu</label>
                            <input type="datetime-local" name="tanggal" value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" required>
                        </div>
                        <div class="form-group autocomplete-wrapper">
                            <label>No. RM Ibu</label>
                            <input type="text" name="no_rkm_medis_ibu" id="no_rkm_medis_ibu" 
                                   value="<?php echo $data['no_rkm_medis_ibu']; ?>" 
                                   placeholder="Ketik No. RM / Nama Ibu..." autocomplete="off">
                            <div id="autocomplete-ibu-list" class="autocomplete-items"></div>
                        </div>
                        <div class="form-group">
                            <label>Nama Ibu</label>
                            <input type="text" name="nm_ibu" id="nm_ibu" value="<?php echo $data['nm_ibu']; ?>" 
                                   placeholder="Nama Ibu" readonly style="background: #f1f5f9;">
                        </div>
                        <div class="form-group">
                            <label>Usia Kehamilan</label>
                            <div class="input-with-unit">
                                <input type="text" name="usiahamil" value="<?php echo $data['usiahamil']; ?>" placeholder="0">
                                <span class="unit">minggu</span>
                            </div>
                        </div>
                    </div>

                    <div class="section-subtitle">Riwayat Obstetri Ibu</div>
                    <div class="form-grid cols-3" style="grid-template-columns: repeat(4, 1fr);">
                        <div class="form-group"><label>G (Gravida)</label><input type="text" name="g" value="<?php echo $data['g']; ?>" placeholder="G"></div>
                        <div class="form-group"><label>P (Para)</label><input type="text" name="p" value="<?php echo $data['p']; ?>" placeholder="P"></div>
                        <div class="form-group"><label>A (Abortus)</label><input type="text" name="a" value="<?php echo $data['a']; ?>" placeholder="A"></div>
                        <div class="form-group"><label>Anak Yang Hidup</label><input type="text" name="hidup" value="<?php echo $data['hidup']; ?>" placeholder=""></div>
                    </div>

                    <!-- Riwayat Persalinan Table -->
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 10px;">
                        <span style="font-size: 11px; font-weight: 600; color: #475569;">Riwayat Persalinan Sebelumnya:</span>
                        <button type="button" class="btn-add-riwayat" onclick="openModalRiwayat()" title="Tambah Riwayat">
                            <i class="material-icons" style="font-size: 16px;">add</i>
                        </button>
                        <button type="button" class="btn-del-riwayat" onclick="hapusRiwayatPersalinan()" title="Hapus Riwayat Terpilih">
                            <i class="material-icons" style="font-size: 16px;">remove</i>
                        </button>
                        <span id="info-riwayat" style="font-size: 10px; color: #94a3b8;"></span>
                    </div>
                    <div class="riwayat-table-container">
                        <table class="riwayat-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tgl/Thn</th>
                                    <th>Tempat</th>
                                    <th>Usia Hamil</th>
                                    <th>Jenis</th>
                                    <th>Penolong</th>
                                    <th>Penyulit</th>
                                    <th>JK</th>
                                    <th>BB/PB</th>
                                    <th>Keadaan</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-riwayat-persalinan">
                                <tr><td colspan="10" style="text-align: center; color: #94a3b8; padding: 15px;">Pilih No. RM Ibu terlebih dahulu</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="section-subtitle" style="margin-top: 15px;">Skrining Ibu</div>
                    <div class="form-grid cols-3">
                        <div class="form-group"><label>HBsAg</label><select name="hbsag"><option value="Negatif (-)" <?php echo ($data['hbsag'] == 'Negatif (-)') ? 'selected' : ''; ?>>Negatif (-)</option><option value="Positif (+)" <?php echo ($data['hbsag'] == 'Positif (+)') ? 'selected' : ''; ?>>Positif (+)</option><option value="Tidak Ada Keterangan" <?php echo ($data['hbsag'] == 'Tidak Ada Keterangan') ? 'selected' : ''; ?>>Tidak Ada Keterangan</option></select></div>
                        <div class="form-group"><label>HIV/AIDS</label><select name="hiv"><option value="Negatif (-)" <?php echo ($data['hiv'] == 'Negatif (-)') ? 'selected' : ''; ?>>Negatif (-)</option><option value="Positif (+)" <?php echo ($data['hiv'] == 'Positif (+)') ? 'selected' : ''; ?>>Positif (+)</option><option value="Tidak Ada Keterangan" <?php echo ($data['hiv'] == 'Tidak Ada Keterangan') ? 'selected' : ''; ?>>Tidak Ada Keterangan</option></select></div>
                        <div class="form-group"><label>Syphilis</label><select name="syphilis"><option value="Negatif (-)" <?php echo ($data['syphilis'] == 'Negatif (-)') ? 'selected' : ''; ?>>Negatif (-)</option><option value="Positif (+)" <?php echo ($data['syphilis'] == 'Positif (+)') ? 'selected' : ''; ?>>Positif (+)</option><option value="Tidak Ada Keterangan" <?php echo ($data['syphilis'] == 'Tidak Ada Keterangan') ? 'selected' : ''; ?>>Tidak Ada Keterangan</option></select></div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group"><label>Riwayat Obstetri Ibu</label><select name="riwayat_obstetri_ibu"><option value="Tidak Ada" <?php echo ($data['riwayat_obstetri_ibu'] == 'Tidak Ada') ? 'selected' : ''; ?>>Tidak Ada</option><option value="Demam Pada Ibu > 38°C" <?php echo ($data['riwayat_obstetri_ibu'] == 'Demam Pada Ibu > 38°C') ? 'selected' : ''; ?>>Demam Pada Ibu > 38°C</option><option value="Ketuban Pecah Dini > 18 Jam" <?php echo ($data['riwayat_obstetri_ibu'] == 'Ketuban Pecah Dini > 18 Jam') ? 'selected' : ''; ?>>Ketuban Pecah Dini > 18 Jam</option><option value="Ketuban Berbau" <?php echo ($data['riwayat_obstetri_ibu'] == 'Ketuban Berbau') ? 'selected' : ''; ?>>Ketuban Berbau</option><option value="Lain-lain" <?php echo ($data['riwayat_obstetri_ibu'] == 'Lain-lain') ? 'selected' : ''; ?>>Lain-lain</option></select></div>
                        <div class="form-group"><label>Keterangan</label><input type="text" name="keterangan_riwayat_obstetri_ibu" value="<?php echo $data['keterangan_riwayat_obstetri_ibu']; ?>" placeholder="Keterangan"></div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group"><label>Faktor Risiko Neonatal</label><select name="faktor_risiko_neonatal"><option value="Tidak Ada" <?php echo ($data['faktor_risiko_neonatal'] == 'Tidak Ada') ? 'selected' : ''; ?>>Tidak Ada</option><option value="Kelahiran Preterm" <?php echo ($data['faktor_risiko_neonatal'] == 'Kelahiran Preterm') ? 'selected' : ''; ?>>Kelahiran Preterm</option><option value="Kelahiran Kembar" <?php echo ($data['faktor_risiko_neonatal'] == 'Kelahiran Kembar') ? 'selected' : ''; ?>>Kelahiran Kembar</option><option value="BBLR" <?php echo ($data['faktor_risiko_neonatal'] == 'BBLR') ? 'selected' : ''; ?>>BBLR</option><option value="Lain-lain" <?php echo ($data['faktor_risiko_neonatal'] == 'Lain-lain') ? 'selected' : ''; ?>>Lain-lain</option></select></div>
                        <div class="form-group"><label>Keterangan</label><input type="text" name="keterangan_faktor_risiko_neonatal" value="<?php echo $data['keterangan_faktor_risiko_neonatal']; ?>" placeholder="Keterangan"></div>
                    </div>
                </div>

                <!-- II. PEMERIKSAAN FISIK -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">monitor_heart</i><h2>II. PEMERIKSAAN FISIK</h2></div>

                    <div class="form-grid cols-3" style="grid-template-columns: repeat(5, 1fr);">
                        <div class="form-group"><label>Tgl & Jam Persalinan</label><input type="datetime-local" name="tanggal_persalinan" value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal_persalinan'])); ?>"></div>
                        <div class="form-group"><label>Bersalin Di</label><input type="text" name="bersalin_di" value="<?php echo $data['bersalin_di']; ?>" placeholder="Tempat Bersalin"></div>
                        <div class="form-group"><label>IMD</label><select name="inisiasi_menyusui"><option value="Ya" <?php echo ($data['inisiasi_menyusui'] == 'Ya') ? 'selected' : ''; ?>>Ya</option><option value="Tidak" <?php echo ($data['inisiasi_menyusui'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option></select></div>
                        <div class="form-group"><label>Jenis Persalinan</label><select name="jenis_persalinan"><option value="Spontan/Normal" <?php echo ($data['jenis_persalinan'] == 'Spontan/Normal') ? 'selected' : ''; ?>>Spontan/Normal</option><option value="Induksi" <?php echo ($data['jenis_persalinan'] == 'Induksi') ? 'selected' : ''; ?>>Induksi</option><option value="Sectio Caesaria" <?php echo ($data['jenis_persalinan'] == 'Sectio Caesaria') ? 'selected' : ''; ?>>Sectio Caesaria</option><option value="Vakum" <?php echo ($data['jenis_persalinan'] == 'Vakum') ? 'selected' : ''; ?>>Vakum</option><option value="Forcep" <?php echo ($data['jenis_persalinan'] == 'Forcep') ? 'selected' : ''; ?>>Forcep</option></select></div>
                        <div class="form-group"><label>Indikasi</label><input type="text" name="indikasi" value="<?php echo $data['indikasi']; ?>" placeholder="Indikasi"></div>
                    </div>

                    <div class="section-subtitle">Pengkajian Awal Lahir</div>
                    <div class="form-grid cols-3" style="grid-template-columns: repeat(4, 1fr);">
                        <div class="form-group"><label>Aterm?</label><select name="aterm"><option value="Ya" <?php echo ($data['aterm'] == 'Ya') ? 'selected' : ''; ?>>Ya</option><option value="Tidak" <?php echo ($data['aterm'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option></select></div>
                        <div class="form-group"><label>Bernafas/Menangis?</label><select name="bernafas"><option value="Ya" <?php echo ($data['bernafas'] == 'Ya') ? 'selected' : ''; ?>>Ya</option><option value="Tidak" <?php echo ($data['bernafas'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option></select></div>
                        <div class="form-group"><label>Tonus Otot Baik?</label><select name="tanus_otot"><option value="Ya" <?php echo ($data['tanus_otot'] == 'Ya') ? 'selected' : ''; ?>>Ya</option><option value="Tidak" <?php echo ($data['tanus_otot'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option></select></div>
                        <div class="form-group"><label>Cairan Amnion Jernih?</label><select name="cairan_amnion"><option value="Ya" <?php echo ($data['cairan_amnion'] == 'Ya') ? 'selected' : ''; ?>>Ya</option><option value="Tidak" <?php echo ($data['cairan_amnion'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option></select></div>
                    </div>

                    <div class="section-subtitle">APGAR Score</div>
                    <table class="apgar-table">
                        <thead><tr><th style="width:120px;">Tanda</th><th>0</th><th>1</th><th>2</th><th style="width:50px;">N 1'</th><th style="width:50px;">N 5'</th><th style="width:50px;">N 10'</th></tr></thead>
                        <tbody>
                            <tr><td style="text-align:left; font-weight:500;">Frek. Jantung</td><td>Tidak Ada</td><td>&lt; 100</td><td>&gt; 100</td><td><input type="text" name="f1" value="<?php echo $data['f1']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="f5" value="<?php echo $data['f5']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="f10" value="<?php echo $data['f10']; ?>" onchange="hitungAPGAR()"></td></tr>
                            <tr><td style="text-align:left; font-weight:500;">Usaha Nafas</td><td>Tidak Ada</td><td>Lambat</td><td>Menangis</td><td><input type="text" name="u1" value="<?php echo $data['u1']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="u5" value="<?php echo $data['u5']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="u10" value="<?php echo $data['u10']; ?>" onchange="hitungAPGAR()"></td></tr>
                            <tr><td style="text-align:left; font-weight:500;">Tonus Otot</td><td>Lumpuh</td><td>Fleksi</td><td>Aktif</td><td><input type="text" name="t1" value="<?php echo $data['t1']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="t5" value="<?php echo $data['t5']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="t10" value="<?php echo $data['t10']; ?>" onchange="hitungAPGAR()"></td></tr>
                            <tr><td style="text-align:left; font-weight:500;">Refleks</td><td>Tidak Ada</td><td>Sedikit</td><td>Menangis</td><td><input type="text" name="r1" value="<?php echo $data['r1']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="r5" value="<?php echo $data['r5']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="r10" value="<?php echo $data['r10']; ?>" onchange="hitungAPGAR()"></td></tr>
                            <tr><td style="text-align:left; font-weight:500;">Warna</td><td>Biru</td><td>Acrocyanosis</td><td>Kemerahan</td><td><input type="text" name="w1" value="<?php echo $data['w1']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="w5" value="<?php echo $data['w5']; ?>" onchange="hitungAPGAR()"></td><td><input type="text" name="w10" value="<?php echo $data['w10']; ?>" onchange="hitungAPGAR()"></td></tr>
                            <tr><td style="text-align:left; font-weight:600; background:#f0fdf4;">TOTAL</td><td colspan="3"></td><td class="total-cell"><input type="text" name="n1" id="total_n1" value="<?php echo $data['n1']; ?>" readonly style="background:#f0fdf4; font-weight:600;"></td><td class="total-cell"><input type="text" name="n5" id="total_n5" value="<?php echo $data['n5']; ?>" readonly style="background:#f0fdf4; font-weight:600;"></td><td class="total-cell"><input type="text" name="n10" id="total_n10" value="<?php echo $data['n10']; ?>" readonly style="background:#f0fdf4; font-weight:600;"></td></tr>
                        </tbody>
                    </table>

                    <div class="section-subtitle">Down Score</div>
                    <div class="down-score-grid">
                        <div class="down-score-item"><label>Frek. Napas</label><select name="frekuensi_napas" onchange="hitungDownScore()"><option value="< 60" <?php echo ($data['frekuensi_napas'] == '< 60') ? 'selected' : ''; ?>>&lt; 60 (0)</option><option value="60 - 80" <?php echo ($data['frekuensi_napas'] == '60 - 80') ? 'selected' : ''; ?>>60-80 (1)</option><option value="> 80" <?php echo ($data['frekuensi_napas'] == '> 80') ? 'selected' : ''; ?>>&gt; 80 (2)</option></select><input type="hidden" name="nilai_frekuensi_napas" id="nilai_frekuensi_napas" value="<?php echo $data['nilai_frekuensi_napas']; ?>"></div>
                        <div class="down-score-item"><label>Retraksi</label><select name="retraksi" onchange="hitungDownScore()"><option value="Tidak Ada" <?php echo ($data['retraksi'] == 'Tidak Ada') ? 'selected' : ''; ?>>Tidak Ada (0)</option><option value="Retraksi Ringan" <?php echo ($data['retraksi'] == 'Retraksi Ringan') ? 'selected' : ''; ?>>Ringan (1)</option><option value="Retraksi Berat" <?php echo ($data['retraksi'] == 'Retraksi Berat') ? 'selected' : ''; ?>>Berat (2)</option></select><input type="hidden" name="nilai_retraksi" id="nilai_retraksi" value="<?php echo $data['nilai_retraksi']; ?>"></div>
                        <div class="down-score-item"><label>Sianosis</label><select name="sianosis" onchange="hitungDownScore()"><option value="Tidak Ada" <?php echo ($data['sianosis'] == 'Tidak Ada') ? 'selected' : ''; ?>>Tidak Ada (0)</option><option value="Hilang Dengan O2" <?php echo ($data['sianosis'] == 'Hilang Dengan O2') ? 'selected' : ''; ?>>Hilang dgn O2 (1)</option><option value="Tidak Hilang Dengan O2" <?php echo ($data['sianosis'] == 'Tidak Hilang Dengan O2') ? 'selected' : ''; ?>>Tidak Hilang (2)</option></select><input type="hidden" name="nilai_sianosis" id="nilai_sianosis" value="<?php echo $data['nilai_sianosis']; ?>"></div>
                        <div class="down-score-item"><label>Jalan Udara</label><select name="jalan_masuk_udara" onchange="hitungDownScore()"><option value="Baik" <?php echo ($data['jalan_masuk_udara'] == 'Baik') ? 'selected' : ''; ?>>Baik (0)</option><option value="Penurunan Ringan Udara Masuk" <?php echo ($data['jalan_masuk_udara'] == 'Penurunan Ringan Udara Masuk') ? 'selected' : ''; ?>>Penurunan (1)</option><option value="Tidak Ada Udara Masuk" <?php echo ($data['jalan_masuk_udara'] == 'Tidak Ada Udara Masuk') ? 'selected' : ''; ?>>Tidak Ada (2)</option></select><input type="hidden" name="nilai_jalan_masuk_udara" id="nilai_jalan_masuk_udara" value="<?php echo $data['nilai_jalan_masuk_udara']; ?>"></div>
                        <div class="down-score-item"><label>Grunting</label><select name="grunting" onchange="hitungDownScore()"><option value="Tidak Ada" <?php echo ($data['grunting'] == 'Tidak Ada') ? 'selected' : ''; ?>>Tidak Ada (0)</option><option value="Dapat Didengar Dengan Stetoskop" <?php echo ($data['grunting'] == 'Dapat Didengar Dengan Stetoskop') ? 'selected' : ''; ?>>Dgn Stetoskop (1)</option><option value="Dapat Didengar Tanpa Alat" <?php echo ($data['grunting'] == 'Dapat Didengar Tanpa Alat') ? 'selected' : ''; ?>>Tanpa Alat (2)</option></select><input type="hidden" name="nilai_grunting" id="nilai_grunting" value="<?php echo $data['nilai_grunting']; ?>"></div>
                        <div class="down-score-item down-score-total"><label>TOTAL</label><input type="text" name="total_down_score" id="total_down_score" value="<?php echo $data['total_down_score']; ?>" readonly style="font-weight:600; background:#f0fdf4;"></div>
                        <div class="down-score-item" style="grid-column: span 2;"><label>Keterangan</label><input type="text" name="keterangan_down_Score" id="keterangan_down_Score" value="<?php echo $data['keterangan_down_Score']; ?>" readonly></div>
                    </div>

                    <div class="section-subtitle">Tanda-Tanda Vital & Antropometri</div>
                    <div class="vital-grid" style="grid-template-columns: repeat(8, 1fr);">
                        <div class="vital-item"><label>Nadi</label><input type="text" name="nadi" value="<?php echo $data['nadi']; ?>" placeholder="x/mnt"></div>
                        <div class="vital-item"><label>RR</label><input type="text" name="rr" value="<?php echo $data['rr']; ?>" placeholder="x/mnt"></div>
                        <div class="vital-item"><label>Suhu</label><input type="text" name="suhu" value="<?php echo $data['suhu']; ?>" placeholder="°C"></div>
                        <div class="vital-item"><label>SpO2</label><input type="text" name="saturasi" value="<?php echo $data['saturasi']; ?>" placeholder="%"></div>
                        <div class="vital-item"><label>BB</label><input type="text" name="bb" value="<?php echo $data['bb']; ?>" placeholder="gram"></div>
                        <div class="vital-item"><label>PB</label><input type="text" name="pb" value="<?php echo $data['pb']; ?>" placeholder="cm"></div>
                        <div class="vital-item"><label>LK</label><input type="text" name="lk" value="<?php echo $data['lk']; ?>" placeholder="cm"></div>
                        <div class="vital-item"><label>LD</label><input type="text" name="ld" value="<?php echo $data['ld']; ?>" placeholder="cm"></div>
                    </div>

                    <div class="section-subtitle">Status Kelainan</div>
                    <div class="status-kelainan-grid">
                        <?php
                        $statusFields = [
                            ['keadaan_umum', 'Kondisi Umum'], ['kulit', 'Kulit'], ['kepala', 'Kepala'],
                            ['mata', 'Mata'], ['telinga', 'Telinga'], ['hidung', 'Hidung'],
                            ['mulut', 'Mulut'], ['tenggorokan', 'Tenggorok'], ['leher', 'Leher'],
                            ['thorax', 'Thorax'], ['abdomen', 'Abdomen'], ['genitalia', 'Genitalia'],
                            ['anus', 'Anus'], ['muskulos', 'Muskuloskeletal'], ['ekstrimitas', 'Ekstrimitas'],
                            ['paru', 'Paru'], ['refleks', 'Refleks'],
                        ];
                        foreach($statusFields as $field):
                            $fieldName = $field[0]; $fieldLabel = $field[1]; $ketName = 'keterangan_' . $fieldName;
                        ?>
                        <div class="status-kelainan-item">
                            <label><?php echo $fieldLabel; ?></label>
                            <select name="<?php echo $fieldName; ?>"><option value="Normal" <?php echo ($data[$fieldName] == 'Normal') ? 'selected' : ''; ?>>Normal</option><option value="Abnormal" <?php echo ($data[$fieldName] == 'Abnormal') ? 'selected' : ''; ?>>Abnormal</option><option value="Tidak Diperiksa" <?php echo ($data[$fieldName] == 'Tidak Diperiksa') ? 'selected' : ''; ?>>Tdk Diperiksa</option></select>
                            <input type="text" name="<?php echo $ketName; ?>" value="<?php echo isset($data[$ketName]) ? $data[$ketName] : ''; ?>" placeholder="Ket">
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-group" style="margin-top: 10px;"><label>Kelainan Lainnya</label><textarea name="kelainan_lainnya" rows="2" placeholder="Kelainan lainnya jika ada..."><?php echo $data['kelainan_lainnya']; ?></textarea></div>
                </div>

                <!-- III. PEMERIKSAAN REGIONAL -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">content_paste</i><h2>III. PEMERIKSAAN REGIONAL/KHUSUS</h2></div>
                    <div class="form-group"><textarea name="pemeriksaan_regional" rows="4" placeholder="Hasil pemeriksaan regional/khusus/tambahan..."><?php echo $data['pemeriksaan_regional']; ?></textarea></div>
                </div>

                <!-- IV. PEMERIKSAAN PENUNJANG -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">science</i><h2>IV. PEMERIKSAAN PENUNJANG</h2></div>
                    <div class="form-grid cols-3">
                        <div class="form-group"><label>Laboratorium</label><textarea name="lab" rows="3" placeholder="Hasil lab..."><?php echo $data['lab']; ?></textarea></div>
                        <div class="form-group"><label>Radiologi</label><textarea name="radiologi" rows="3" placeholder="Hasil radiologi..."><?php echo $data['radiologi']; ?></textarea></div>
                        <div class="form-group"><label>Penunjang Lainnya</label><textarea name="penunjanglainnya" rows="3" placeholder="Penunjang lain..."><?php echo $data['penunjanglainnya']; ?></textarea></div>
                    </div>
                </div>

                <!-- V. DIAGNOSIS -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">fact_check</i><h2>V. DIAGNOSIS</h2></div>
                    <div class="form-group"><label class="required">Diagnosis / Asesmen</label><textarea name="diagnosis" rows="3" required placeholder="Tuliskan diagnosis..."><?php echo $data['diagnosis']; ?></textarea></div>
                </div>

                <!-- VI. TATALAKSANA -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">medical_services</i><h2>VI. TATALAKSANA</h2></div>
                    <div class="form-group"><label class="required">Tatalaksana / Rencana Terapi</label><textarea name="tata" rows="4" required placeholder="Tuliskan tatalaksana..."><?php echo $data['tata']; ?></textarea></div>
                </div>

                <!-- VII. EDUKASI -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">school</i><h2>VII. EDUKASI</h2></div>
                    <div class="form-group"><label>Edukasi Pasien & Keluarga</label><textarea name="edukasi" rows="3" placeholder="Tuliskan edukasi..."><?php echo $data['edukasi']; ?></textarea></div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliNeonatus()"><i class="material-icons">arrow_back</i> KEMBALI</button>
            <?php 
            $bolehEdit = true; // Default bisa edit untuk data baru
            $bolehHapus = false;
            if($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if($kd_dokter_login === $kd_dokter_data) { 
                    $bolehHapus = true; 
                    $bolehEdit = true;
                } else {
                    $bolehEdit = false; // Bukan dokter pengisi, tidak bisa edit
                }
            }
            if($bolehHapus): ?>
            <button type="button" id="btn-delete-neonatus" class="btn btn-danger" onclick="confirmDeleteNeonatus()"><i class="material-icons">delete</i> HAPUS</button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus"><i class="material-icons">lock</i> HAPUS</button>
            <?php endif; ?>
            <?php if($bolehEdit): ?>
            <button type="submit" id="btn-save-neonatus" form="formPenilaianMedisNeonatus" class="btn btn-primary"><i class="material-icons">save</i> SIMPAN DATA</button>
            <?php else: ?>
            <button type="button" class="btn btn-primary" disabled title="Hanya dokter pengisi yang dapat mengubah data"><i class="material-icons">lock</i> SIMPAN DATA</button>
            <?php endif; ?>
        </div>

        <?php if($isEdit && !$bolehEdit): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 15px; display: flex; align-items: center; gap: 10px;">
            <i class="material-icons" style="color: #856404;">info</i>
            <span style="color: #856404; font-size: 14px;"><strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>. Anda hanya dapat melihat data, tidak dapat mengubah atau menghapusnya.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Riwayat Persalinan -->
<div class="modal-overlay" id="modalRiwayatPersalinan">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="material-icons" style="font-size: 16px; vertical-align: middle;">pregnant_woman</i> Tambah Riwayat Persalinan</h3>
        </div>
        <div class="modal-body">
            <div class="form-grid cols-2">
                <div class="form-group">
                    <label>Tempat Persalinan</label>
                    <input type="text" id="rp_tempat" placeholder="Tempat Persalinan">
                </div>
                <div class="form-group">
                    <label>Tanggal/Tahun</label>
                    <input type="date" id="rp_tgl_thn" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Jenis Persalinan</label>
                    <input type="text" id="rp_jenis" placeholder="Spontan/SC/dll">
                </div>
                <div class="form-group">
                    <label>Usia Hamil (minggu)</label>
                    <input type="text" id="rp_usiahamil" placeholder="38">
                </div>
                <div class="form-group">
                    <label>Penolong</label>
                    <input type="text" id="rp_penolong" placeholder="Bidan/Dokter/dll">
                </div>
                <div class="form-group">
                    <label>Jenis Kelamin</label>
                    <select id="rp_jk">
                        <option value="L">Laki-Laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Penyulit</label>
                    <input type="text" id="rp_penyulit" placeholder="Penyulit (jika ada)">
                </div>
                <div class="form-group">
                    <label>BB/PB</label>
                    <input type="text" id="rp_bbpb" placeholder="3200gr / 50cm">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label>Keadaan</label>
                    <input type="text" id="rp_keadaan" placeholder="Sehat/Meninggal/dll">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModalRiwayat()">
                <i class="material-icons" style="font-size: 14px;">close</i> Tutup
            </button>
            <button type="button" class="btn-modal btn-modal-primary" onclick="simpanRiwayatPersalinan()">
                <i class="material-icons" style="font-size: 14px;">save</i> Simpan
            </button>
        </div>
    </div>
</div>

<style>
/* Modal Styles */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 9999; align-items: center; justify-content: center; }
.modal-overlay.active { display: flex; }
.modal-content { background: white; border-radius: 12px; width: 90%; max-width: 550px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); }
.modal-header { background: linear-gradient(135deg, #1e40af 0%, #0891b2 100%); color: white; padding: 12px 16px; border-radius: 12px 12px 0 0; display: flex; align-items: center; justify-content: space-between; }
.modal-header h3 { margin: 0; font-size: 13px; font-weight: 600; }
.modal-close { background: rgba(255,255,255,0.2); border: none; color: white; width: 26px; height: 26px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.modal-body { padding: 15px; }
.modal-footer { padding: 12px 15px; background: #f8fafc; border-radius: 0 0 12px 12px; display: flex; justify-content: flex-end; gap: 8px; }
.btn-modal { padding: 6px 14px; border: none; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
.btn-modal-primary { background: #667eea; color: white; }
.btn-modal-secondary { background: #e2e8f0; color: #475569; }

/* Tombol Add/Del Riwayat */
.btn-add-riwayat, .btn-del-riwayat { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border: none; border-radius: 5px; cursor: pointer; transition: all 0.2s ease; }
.btn-add-riwayat { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
.btn-del-riwayat { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
.btn-add-riwayat:hover { transform: scale(1.1); }
.btn-del-riwayat:hover { transform: scale(1.1); }
</style>

<script>
const NO_RAWAT = '<?php echo $no_rawat; ?>';
const INIT_NO_RM_IBU = '<?php echo $data['no_rkm_medis_ibu']; ?>';
const API_RIWAYAT_URL = '<?php echo BASE_URL; ?>/pages/get_riwayat_persalinan.php';
const API_PROSES3_URL = '<?php echo BASE_URL; ?>/pages/proses3.php';
</script>
<script src="<?php echo BASE_URL; ?>/js/awalmedisneonatus.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo BASE_URL; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>