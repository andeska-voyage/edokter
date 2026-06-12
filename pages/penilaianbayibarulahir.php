<?php
// Gunakan APP_BASE_URL yang sudah ada di conf.php
// Gunakan defined() check agar tidak konflik jika sudah di-define file lain
if (!defined('BASE_URL')) {
    define('BASE_URL', APP_BASE_URL);
}
$base_url_bbl = BASE_URL;

// Decrypt parameter dari URL
$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';

$no_rawat    = '';
$no_rkm_medis = '';

if(!empty($encrypted_norawat)) {
    $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
}
if(!empty($encrypted_norm)) {
    $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd');
}

// Ambil data pasien — coba kamar_inap (ranap) dulu, fallback ke reg_periksa (rajal)
$rsPasien = null;

// Coba dari kamar_inap (rawat inap)
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

// Fallback: coba dari reg_periksa (rawat jalan / IGD)
if (!$rsPasien) {
    $queryPasienRajal = bukaquery("SELECT
                                rp.no_rawat,
                                rp.no_rkm_medis,
                                rp.tgl_registrasi as tgl_masuk,
                                rp.jam_reg as jam_masuk,
                                p.nm_pasien,
                                p.jk,
                                p.tmp_lahir,
                                p.tgl_lahir,
                                CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                                d.nm_dokter,
                                d.kd_dokter,
                                '' as kd_kamar,
                                pl.nm_poli as nm_bangsal
                            FROM reg_periksa rp
                            INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                            LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                            LEFT JOIN poliklinik pl ON rp.kd_poli = pl.kd_poli
                            WHERE rp.no_rawat = '$no_rawat'
                            LIMIT 1");
    $rsPasien = mysqli_fetch_array($queryPasienRajal);
}

if (!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>";
    exit;
}

// Cek apakah sudah ada data penilaian bayi baru lahir
$queryCheck = bukaquery("SELECT bbl.*, d.nm_dokter as nama_dokter_pengisi
                         FROM penilaian_bayi_baru_lahir bbl
                         LEFT JOIN dokter d ON bbl.kd_dokter = d.kd_dokter
                         WHERE bbl.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;
$nama_dokter_pengisi = ($rsCheck && !empty($rsCheck['nama_dokter_pengisi'])) ? $rsCheck['nama_dokter_pengisi'] : '';

// Ambil kode dokter login
$kd_dokter_encrypted = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter_login     = '';
if(!empty($kd_dokter_encrypted)) {
    $kd_dokter_login = encrypt_decrypt($kd_dokter_encrypted, 'd');
}

// Ambil nama ibu jika ada no_rkm_medis_ibu
$nm_ibu        = '';
$tgl_lahir_ibu = '';
if($isEdit && !empty($rsCheck['no_rkm_medis_ibu'])) {
    $queryIbu = bukaquery("SELECT nm_pasien, tgl_lahir FROM pasien WHERE no_rkm_medis = '".$rsCheck['no_rkm_medis_ibu']."'");
    $rsIbu = mysqli_fetch_array($queryIbu);
    if($rsIbu) {
        $nm_ibu        = $rsIbu['nm_pasien'];
        $tgl_lahir_ibu = $rsIbu['tgl_lahir'];
    }
}

// Data default sesuai tabel penilaian_bayi_baru_lahir
$data = array(
    'tanggal'                              => date('Y-m-d H:i:s'),
    'kd_dokter'                            => $kd_dokter_login,
    'no_rkm_medis_ibu'                     => '',
    'nm_ibu'                               => $nm_ibu,
    'tgl_lahir_ibu'                        => $tgl_lahir_ibu,
    // I. Riwayat Maternal
    'penyakit_diderita_ibu'                => 'Tidak Ada',
    'keterangan_penyakit_diderita_ibu'     => '',
    'obat_dikonsumsi_selama_kehamilan'     => '',
    'perawatan_antenatal'                  => 'Ya',
    'keterangan_perawatan_antenatal'       => '',
    'terdaftar_ekohort'                    => 'Ya',
    'keterangan_terdaftar_ekohort'         => '',
    'penyulit_kehamilan'                   => 'Tidak Ada',
    'keterangan_penyulit_kehamilan'        => '',
    'alergi'                               => '',
    'keterangan_lainnya_riwayat_maternal'  => '',
    // II. Riwayat Persalinan
    'umur_kehamilan'                       => '',
    'kehamilan'                            => 'Tunggal',
    'keterangan_kehamilan'                 => '',
    'urutan_kehamilan'                     => '',
    'jam_ketuban_pecah'                    => '',
    'menit_ketuban_pecah'                  => '',
    'jumlah_air_ketuban'                   => '',
    'warna_air_ketuban'                    => '',
    'bau_air_ketuban'                      => '',
    'letak_bayi'                           => '',
    'macam_persalinan'                     => 'Spontan',
    'keterangan_macam_persalinan'          => '',
    'indikasi_persalinan_operatif'         => 'Tidak Ada',
    'keterangan_indikasi_persalinan_operatif' => '',
    'lama_gawat_janin'                     => '',
    'obat_selama_persalinan'               => '',
    'berat_placenta'                       => '',
    'kelainan_placenta'                    => '',
    'keterangan_lainnya_riwayat_persalinan'=> '',
    // III. Keadaan Bayi - APGAR
    'f1' => '', 'u1' => '', 't1' => '', 'r1' => '', 'w1' => '', 'n1' => '',
    'f5' => '', 'u5' => '', 't5' => '', 'r5' => '', 'w5' => '', 'n5' => '',
    'f10'=> '', 'u10'=> '', 't10'=> '', 'r10'=> '', 'w10'=> '', 'n10'=> '',
    'bblahir'                              => '',
    'panjang_badan'                        => '',
    'lingkar_kepala'                       => '',
    'lingkar_dada'                         => '',
    'resusitasi_saat_lahir'                => 'Tidak',
    'keterangan_resusitasi_saat_lahir'     => '',
    'obat_diberikan_saat_lahir'            => '',
    'keterangan_lainnya_keadaan_bayi'      => '',
    // IV. Pemeriksaan Fisik
    'kondisi_umum'                         => 'Normal',
    'keterangan_kondisi_umum'              => '',
    'kulit'                                => 'Normal',
    'keterangan_kulit'                     => '',
    'kepala'                               => 'Normal',
    'keterangan_kepala'                    => '',
    'leher'                                => 'Normal',
    'keterangan_leher'                     => '',
    'mata'                                 => 'Normal',
    'keterangan_mata'                      => '',
    'hidung'                               => 'Normal',
    'keterangan_hidung'                    => '',
    'telinga'                              => 'Normal',
    'keterangan_telinga'                   => '',
    'dada'                                 => 'Normal',
    'keterangan_dada'                      => '',
    'paru'                                 => 'Normal',
    'keterangan_paru'                      => '',
    'jantung'                              => 'Normal',
    'keterangan_jantung'                   => '',
    'perut'                                => 'Normal',
    'keterangan_perut'                     => '',
    'tali_pusat'                           => 'Normal',
    'keterangan_tali_pusat'                => '',
    'alat_kelamin'                         => 'Normal',
    'keterangan_alat_kelamin'              => '',
    'ruas_tulang_belakang'                 => 'Normal',
    'keterangan_ruas_tulang_belakang'      => '',
    'extrimitas'                           => 'Normal',
    'keterangan_extrimitas'                => '',
    'anus'                                 => 'Normal',
    'keterangan_anus'                      => '',
    'refleks'                              => 'Normal',
    'keterangan_refleks'                   => '',
    'denyut_femoral'                       => 'Normal',
    'keterangan_denyut_femoral'            => '',
    'pemeriksaan_fisik_lainnya'            => '',
    // V. Pemeriksaan Penunjang
    'pemeriksaan_penunjang'                => '',
    // VI-VII
    'diagnosa'                             => '',
    'tatalaksana'                          => '',
);

if($isEdit) {
    $data = array_merge($data, $rsCheck);
    $data['nm_ibu']        = $nm_ibu;
    $data['tgl_lahir_ibu'] = $tgl_lahir_ibu;
}

// Helper: selected option
function selOpt($current, $value) {
    return ($current == $value) ? 'selected' : '';
}
?>

<link rel="stylesheet" href="<?php echo $base_url_bbl; ?>/css/template4.css?v=<?php echo time(); ?>">

<style>
/* ===== APGAR TABLE ===== */
.apgar-table { width: 100%; border-collapse: collapse; font-size: 11px; margin: 8px 0; }
.apgar-table th, .apgar-table td { border: 1px solid #e2e8f0; padding: 5px 7px; text-align: center; }
.apgar-table th { background: #f8fafc; font-weight: 600; color: #475569; font-size: 10px; }
.apgar-table td { background: #fff; font-size: 10px; }
.apgar-table td:first-child { text-align: left; font-weight: 500; background: #f8fafc; }
.apgar-table input[type="text"] { width: 38px; padding: 4px; border: 1px solid #e2e8f0; border-radius: 4px; text-align: center; font-size: 11px; }
.apgar-table .total-cell { background: #f0fdf4; font-weight: 700; color: #166534; }
.apgar-total-row td { background: #f0fdf4 !important; font-weight: 700; }

/* ===== PEMERIKSAAN FISIK GRID (2 kolom, tiap baris: label | select | input) ===== */
.fisik-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 20px; }
.fisik-item { display: grid; grid-template-columns: 120px 130px 1fr; gap: 5px; align-items: center; padding: 5px 8px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; }
.fisik-item label { font-size: 11px; font-weight: 500; color: #374151; }
.fisik-item select { padding: 4px 6px; border: 1px solid #cbd5e1; border-radius: 3px; font-size: 11px; cursor: pointer; }
.fisik-item input[type="text"] { padding: 4px 7px; border: 1px solid #e2e8f0; border-radius: 3px; font-size: 11px; }

/* ===== RIWAYAT TABLE ===== */
.riwayat-table-container { max-height: 145px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 6px; margin-top: 6px; }
.riwayat-table { width: 100%; border-collapse: collapse; font-size: 10px; }
.riwayat-table th { background: #f1f5f9; padding: 5px 7px; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; font-size: 9px; white-space: nowrap; }
.riwayat-table td { padding: 4px 7px; border-bottom: 1px solid #f1f5f9; }
.riwayat-table tr:hover { background: #f8fafc; }

/* ===== AUTOCOMPLETE ===== */
.autocomplete-wrapper { position: relative; }
.autocomplete-items { position: absolute; border: 1px solid #e2e8f0; border-top: none; z-index: 999; top: 100%; left: 0; right: 0; max-height: 200px; overflow-y: auto; background: white; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: none; }
.autocomplete-items div { padding: 9px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
.autocomplete-items div:hover, .autocomplete-items div.active { background: #f0f9ff; }
.autocomplete-items div strong { color: #1e40af; }
.autocomplete-items .no-result { color: #94a3b8; text-align: center; font-style: italic; }

/* ===== INPUT WITH UNIT ===== */
.input-with-unit { display: flex; align-items: center; }
.input-with-unit input { border-radius: 4px 0 0 4px !important; border-right: none !important; flex: 1; min-width: 0; }
.input-with-unit .unit { padding: 7px 9px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 0 4px 4px 0; font-size: 11px; color: #64748b; white-space: nowrap; }

/* ===== ADD/DEL RIWAYAT BUTTONS ===== */
.btn-add-riwayat, .btn-del-riwayat { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border: none; border-radius: 5px; cursor: pointer; transition: all 0.2s; }
.btn-add-riwayat { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
.btn-del-riwayat { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
.btn-add-riwayat:hover, .btn-del-riwayat:hover { transform: scale(1.1); }

/* ===== MODAL ===== */
.bbl-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
.bbl-modal-overlay.active { display: flex; }
.bbl-modal-content { background: white; border-radius: 12px; width: 90%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.bbl-modal-header { background: linear-gradient(135deg, #1e40af 0%, #0891b2 100%); color: white; padding: 12px 16px; border-radius: 12px 12px 0 0; display: flex; align-items: center; justify-content: space-between; }
.bbl-modal-header h3 { margin: 0; font-size: 13px; font-weight: 600; }
.bbl-modal-body { padding: 15px; }
.bbl-modal-footer { padding: 11px 15px; background: #f8fafc; border-radius: 0 0 12px 12px; display: flex; justify-content: flex-end; gap: 8px; }
.btn-bbl-modal { padding: 6px 14px; border: none; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
.btn-bbl-modal-primary { background: #667eea; color: white; }
.btn-bbl-modal-secondary { background: #e2e8f0; color: #475569; }

@media (max-width: 1200px) { .fisik-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .fisik-item { grid-template-columns: 1fr; gap: 3px; } }
</style>

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:10px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">child_friendly</i>
                PENILAIAN BAYI BARU LAHIR
                <?php if($isEdit): ?>
                <span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?>
                <span class="mode-badge mode-add">➕ NEW</span>
                <?php endif; ?>
            </h1>
            <!-- Progress Bar -->
            <div style="display:flex;align-items:center;gap:10px;background:#f8f9fa;border-radius:8px;padding:8px 12px;">
                <i class="material-icons" style="font-size:18px;color:#6c757d;">assessment</i>
                <div style="display:flex;flex-direction:column;gap:2px;">
                    <div style="display:flex;align-items:center;gap:5px;">
                        <span style="font-size:11px;color:#6c757d;font-weight:500;">Kelengkapan</span>
                        <span id="progress-text-bbl" style="font-weight:bold;font-size:14px;color:#6c757d;">0%</span>
                    </div>
                    <div style="width:150px;height:8px;background:#e9ecef;border-radius:4px;overflow:hidden;">
                        <div id="progress-bar-bbl" style="width:0%;height:100%;transition:width 0.3s ease,background 0.3s ease;"></div>
                    </div>
                </div>
                <span id="progress-status-bbl" style="font-size:10px;color:#6c757d;white-space:nowrap;">(0/0)</span>
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
            <div class="info-item"><i class="material-icons">medical_services</i><strong>Diisi oleh:</strong> <?php echo $nama_dokter_pengisi; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPenilaianBayiBaruLahir" method="post" action="" novalidate>
                <input type="hidden" name="no_rawat"   value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="kd_dokter"  value="<?php echo $kd_dokter_login; ?>">

                <!-- ======================================================= -->
                <!-- HEADER: Tanggal, No. RM Ibu, Nama Ibu Bayi, Tgl. Lahir Ibu -->
                <!-- ======================================================= -->
                <div class="section">
                    <!-- 4 kolom sejajar -->
                    <div class="form-grid" style="grid-template-columns: 200px 160px 1fr 160px; gap:10px; margin-bottom:10px;">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="datetime-local" name="tanggal" value="<?php echo date('Y-m-d\TH:i', strtotime($data['tanggal'])); ?>" required>
                        </div>
                        <div class="form-group autocomplete-wrapper">
                            <label>No. RM Ibu</label>
                            <input type="text" name="no_rkm_medis_ibu" id="bbl_no_rkm_medis_ibu"
                                   value="<?php echo $data['no_rkm_medis_ibu']; ?>"
                                   placeholder="Ketik No.RM / Nama..." autocomplete="off">
                            <div id="bbl_autocomplete_ibu" class="autocomplete-items"></div>
                        </div>
                        <div class="form-group">
                            <label>Nama Ibu Bayi</label>
                            <input type="text" id="bbl_nm_ibu" value="<?php echo $data['nm_ibu']; ?>" readonly style="background:#f1f5f9;" placeholder="Otomatis terisi">
                        </div>
                        <div class="form-group">
                            <label>Tgl. Lahir Ibu</label>
                            <input type="text" id="bbl_tgl_lahir_ibu" value="<?php echo $data['tgl_lahir_ibu'] ? date('d-m-Y', strtotime($data['tgl_lahir_ibu'])) : ''; ?>" readonly style="background:#f1f5f9;" placeholder="Otomatis terisi">
                        </div>
                    </div>
                </div>

                <!-- ======================================================= -->
                <!-- I. RIWAYAT MATERNAL -->
                <!-- ======================================================= -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">pregnant_woman</i><h2>I. RIWAYAT MATERNAL</h2></div>

                    <!-- Status Perkawinan / Penyakit / Obat -->
                    <div class="form-grid" style="grid-template-columns: 180px 1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div class="form-group">
                            <label>Status Perkawinan Ibu</label>
                            <input type="text" name="status_perkawinan_ibu" value="<?php echo isset($data['status_perkawinan_ibu']) ? $data['status_perkawinan_ibu'] : ''; ?>" placeholder="Status">
                        </div>
                        <div class="form-group">
                            <label>Penyakit Yang Diderita Ibu</label>
                            <div style="display:flex;gap:6px;">
                                <select name="penyakit_diderita_ibu" style="width:140px;">
                                    <option value="Tidak Ada" <?php echo selOpt($data['penyakit_diderita_ibu'],'Tidak Ada'); ?>>Tidak Ada</option>
                                    <option value="Ada"       <?php echo selOpt($data['penyakit_diderita_ibu'],'Ada'); ?>>Ada</option>
                                </select>
                                <input type="text" name="keterangan_penyakit_diderita_ibu" value="<?php echo $data['keterangan_penyakit_diderita_ibu']; ?>" placeholder="Sebutkan penyakit..." style="flex:1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Obat Yang Dikonsumsi Selama Kehamilan</label>
                            <input type="text" name="obat_dikonsumsi_selama_kehamilan" value="<?php echo $data['obat_dikonsumsi_selama_kehamilan']; ?>" placeholder="Nama obat...">
                        </div>
                    </div>

                    <!-- Riwayat Kehamilan Terdahulu -->
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                        <span style="font-size:11px;font-weight:600;color:#475569;">Riwayat Kehamilan Terdahulu :</span>
                        <button type="button" class="btn-add-riwayat" onclick="bblOpenModalRiwayat()" title="Tambah Riwayat">
                            <i class="material-icons" style="font-size:16px;">add</i>
                        </button>
                        <button type="button" class="btn-del-riwayat" onclick="bblHapusRiwayat()" title="Hapus Riwayat Terpilih">
                            <i class="material-icons" style="font-size:16px;">remove</i>
                        </button>
                        <span id="bbl_info_riwayat" style="font-size:10px;color:#94a3b8;"></span>
                    </div>
                    <div class="riwayat-table-container">
                        <table class="riwayat-table">
                            <thead>
                                <tr>
                                    <th>No</th><th>Tgl/Thn</th><th>Tempat Persalinan</th>
                                    <th>Usia Hamil</th><th>Jenis Persalinan</th><th>Penolong</th>
                                    <th>Penyulit</th><th>JK</th><th>BB/PB</th><th>Keadaan</th>
                                </tr>
                            </thead>
                            <tbody id="bbl_tbody_riwayat">
                                <tr><td colspan="10" style="text-align:center;color:#94a3b8;padding:12px;">Pilih No. RM Ibu terlebih dahulu</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Kehamilan Sekarang -->
                    <div class="section-subtitle" style="margin-top:14px;">Kehamilan Sekarang :</div>
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr 1fr; gap:10px; margin-bottom:8px;">
                        <div class="form-group">
                            <label>Perawatan Antenatal</label>
                            <div style="display:flex;gap:6px;">
                                <select name="perawatan_antenatal" style="width:80px;">
                                    <option value="Ya"    <?php echo selOpt($data['perawatan_antenatal'],'Ya'); ?>>Ya</option>
                                    <option value="Tidak" <?php echo selOpt($data['perawatan_antenatal'],'Tidak'); ?>>Tidak</option>
                                </select>
                                <input type="text" name="keterangan_perawatan_antenatal" value="<?php echo $data['keterangan_perawatan_antenatal']; ?>" placeholder="Keterangan" style="flex:1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Terdaftar Di EKohort</label>
                            <div style="display:flex;gap:6px;">
                                <select name="terdaftar_ekohort" style="width:80px;">
                                    <option value="Ya"    <?php echo selOpt($data['terdaftar_ekohort'],'Ya'); ?>>Ya</option>
                                    <option value="Tidak" <?php echo selOpt($data['terdaftar_ekohort'],'Tidak'); ?>>Tidak</option>
                                </select>
                                <input type="text" name="keterangan_terdaftar_ekohort" value="<?php echo $data['keterangan_terdaftar_ekohort']; ?>" placeholder="Keterangan" style="flex:1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Penyulit Kehamilan</label>
                            <div style="display:flex;gap:6px;">
                                <select name="penyulit_kehamilan" style="width:140px;">
                                    <option value="Tidak Ada"   <?php echo selOpt($data['penyulit_kehamilan'],'Tidak Ada'); ?>>Tidak Ada</option>
                                    <option value="Hiperemesis" <?php echo selOpt($data['penyulit_kehamilan'],'Hiperemesis'); ?>>Hiperemesis</option>
                                    <option value="CPD"         <?php echo selOpt($data['penyulit_kehamilan'],'CPD'); ?>>CPD</option>
                                    <option value="Kelainan Letak" <?php echo selOpt($data['penyulit_kehamilan'],'Kelainan Letak'); ?>>Kelainan Letak</option>
                                    <option value="Preeklampsia" <?php echo selOpt($data['penyulit_kehamilan'],'Preeklampsia'); ?>>Preeklampsia</option>
                                    <option value="Lain-lain"   <?php echo selOpt($data['penyulit_kehamilan'],'Lain-lain'); ?>>Lain-lain</option>
                                </select>
                                <input type="text" name="keterangan_penyulit_kehamilan" value="<?php echo $data['keterangan_penyulit_kehamilan']; ?>" placeholder="Keterangan" style="flex:1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Alergi</label>
                            <input type="text" name="alergi" value="<?php echo $data['alergi']; ?>" placeholder="Alergi ibu...">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Keterangan Lainnya</label>
                        <input type="text" name="keterangan_lainnya_riwayat_maternal" value="<?php echo $data['keterangan_lainnya_riwayat_maternal']; ?>" placeholder="Keterangan lainnya riwayat maternal...">
                    </div>
                </div>

                <!-- ======================================================= -->
                <!-- II. RIWAYAT PERSALINAN -->
                <!-- ======================================================= -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">child_care</i><h2>II. RIWAYAT PERSALINAN</h2></div>

                    <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div class="form-group">
                            <label>Umur Kehamilan</label>
                            <div class="input-with-unit">
                                <input type="text" name="umur_kehamilan" value="<?php echo $data['umur_kehamilan']; ?>" placeholder="0">
                                <span class="unit">minggu</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Kehamilan</label>
                            <div style="display:flex;gap:6px;">
                                <select name="kehamilan" style="width:110px;">
                                    <option value="Tunggal" <?php echo selOpt($data['kehamilan'],'Tunggal'); ?>>Tunggal</option>
                                    <option value="Kembar"  <?php echo selOpt($data['kehamilan'],'Kembar'); ?>>Kembar</option>
                                </select>
                                <input type="text" name="keterangan_kehamilan" value="<?php echo $data['keterangan_kehamilan']; ?>" placeholder="Keterangan" style="flex:1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Urutan Kehamilan</label>
                            <input type="text" name="urutan_kehamilan" value="<?php echo $data['urutan_kehamilan']; ?>" placeholder="ke-..." style="width:80px;">
                        </div>
                    </div>

                    <!-- Ketuban Pecah Sebelum Lahir -->
                    <div class="section-subtitle">Ketuban Pecah Sebelum Lahir :</div>
                    <div class="form-grid" style="grid-template-columns: 100px 100px 1fr 1fr 1fr 1fr; gap:10px; margin-bottom:10px; align-items:end;">
                        <div class="form-group">
                            <label>Jam</label>
                            <input type="text" name="jam_ketuban_pecah" value="<?php echo $data['jam_ketuban_pecah']; ?>" placeholder="Jam">
                        </div>
                        <div class="form-group">
                            <label>Menit</label>
                            <input type="text" name="menit_ketuban_pecah" value="<?php echo $data['menit_ketuban_pecah']; ?>" placeholder="Menit">
                        </div>
                        <div class="form-group">
                            <label>Jumlah Air Ketuban</label>
                            <input type="text" name="jumlah_air_ketuban" value="<?php echo $data['jumlah_air_ketuban']; ?>" placeholder="cc/ml">
                        </div>
                        <div class="form-group">
                            <label>Warna</label>
                            <input type="text" name="warna_air_ketuban" value="<?php echo $data['warna_air_ketuban']; ?>" placeholder="Warna ketuban">
                        </div>
                        <div class="form-group">
                            <label>Bau</label>
                            <input type="text" name="bau_air_ketuban" value="<?php echo $data['bau_air_ketuban']; ?>" placeholder="Bau ketuban">
                        </div>
                        <div class="form-group">
                            <label>Letak Bayi</label>
                            <input type="text" name="letak_bayi" value="<?php echo $data['letak_bayi']; ?>" placeholder="Letak bayi">
                        </div>
                    </div>

                    <!-- Macam Persalinan + Indikasi -->
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; gap:10px; margin-bottom:10px;">
                        <div class="form-group">
                            <label>Macam Persalinan</label>
                            <div style="display:flex;gap:6px;">
                                <select name="macam_persalinan" style="width:140px;">
                                    <option value="Spontan"         <?php echo selOpt($data['macam_persalinan'],'Spontan'); ?>>Spontan</option>
                                    <option value="Porceps"         <?php echo selOpt($data['macam_persalinan'],'Porceps'); ?>>Porceps</option>
                                    <option value="Vacum"           <?php echo selOpt($data['macam_persalinan'],'Vacum'); ?>>Vacum</option>
                                    <option value="Sectio Caesarea" <?php echo selOpt($data['macam_persalinan'],'Sectio Caesarea'); ?>>Sectio Caesarea</option>
                                </select>
                                <input type="text" name="keterangan_macam_persalinan" value="<?php echo $data['keterangan_macam_persalinan']; ?>" placeholder="Keterangan" style="flex:1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Indikasi Persalinan Operatif</label>
                            <div style="display:flex;gap:6px;">
                                <select name="indikasi_persalinan_operatif" style="width:130px;">
                                    <option value="Tidak Ada"       <?php echo selOpt($data['indikasi_persalinan_operatif'],'Tidak Ada'); ?>>Tidak Ada</option>
                                    <option value="Gawat Janin"     <?php echo selOpt($data['indikasi_persalinan_operatif'],'Gawat Janin'); ?>>Gawat Janin</option>
                                    <option value="SC Sebelumnya"   <?php echo selOpt($data['indikasi_persalinan_operatif'],'SC Sebelumnya'); ?>>SC Sebelumnya</option>
                                    <option value="Malpresentasi"   <?php echo selOpt($data['indikasi_persalinan_operatif'],'Malpresentasi'); ?>>Malpresentasi</option>
                                    <option value="Lain-lain"       <?php echo selOpt($data['indikasi_persalinan_operatif'],'Lain-lain'); ?>>Lain-lain</option>
                                </select>
                                <input type="text" name="keterangan_indikasi_persalinan_operatif" value="<?php echo $data['keterangan_indikasi_persalinan_operatif']; ?>" placeholder="Keterangan" style="flex:1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Lama Gawat Janin Sebelum Lahir</label>
                            <div class="input-with-unit">
                                <input type="text" name="lama_gawat_janin" value="<?php echo $data['lama_gawat_janin']; ?>" placeholder="0">
                                <span class="unit">menit</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:10px;">
                        <label>Obat-obatan Selama Persalinan</label>
                        <input type="text" name="obat_selama_persalinan" value="<?php echo $data['obat_selama_persalinan']; ?>" placeholder="Nama obat yang diberikan selama persalinan...">
                    </div>

                    <!-- Placenta -->
                    <div class="section-subtitle">Placenta :</div>
                    <div class="form-grid" style="grid-template-columns: 140px 1fr; gap:10px; margin-bottom:10px;">
                        <div class="form-group">
                            <label>Berat</label>
                            <div class="input-with-unit">
                                <input type="text" name="berat_placenta" value="<?php echo $data['berat_placenta']; ?>" placeholder="0">
                                <span class="unit">gram</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Kelainan</label>
                            <input type="text" name="kelainan_placenta" value="<?php echo $data['kelainan_placenta']; ?>" placeholder="Kelainan placenta (jika ada)">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Keterangan Lainnya</label>
                        <input type="text" name="keterangan_lainnya_riwayat_persalinan" value="<?php echo $data['keterangan_lainnya_riwayat_persalinan']; ?>" placeholder="Keterangan lainnya riwayat persalinan...">
                    </div>
                </div>

                <!-- ======================================================= -->
                <!-- III. KEADAAN BAYI -->
                <!-- ======================================================= -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">monitor_heart</i><h2>III. KEADAAN BAYI</h2></div>

                    <!-- APGAR Score -->
                    <div class="section-subtitle">APGAR Score :</div>
                    <table class="apgar-table">
                        <thead>
                            <tr>
                                <th style="width:140px;text-align:left;">Tanda</th>
                                <th>0</th><th>1</th><th>2</th>
                                <th style="width:55px;">N 1'</th>
                                <th style="width:55px;">N 5'</th>
                                <th style="width:55px;">N 10'</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Frekuensi Jantung</td>
                                <td>Tidak Ada</td><td>&lt; 100</td><td>&gt; 100</td>
                                <td><input type="text" name="f1"  value="<?php echo $data['f1'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="f5"  value="<?php echo $data['f5'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="f10" value="<?php echo $data['f10']; ?>" onchange="bblHitungAPGAR()"></td>
                            </tr>
                            <tr>
                                <td>Usaha Nafas</td>
                                <td>Tidak Ada</td><td>Lambat Tak Teratur</td><td>Menangis Kuat</td>
                                <td><input type="text" name="u1"  value="<?php echo $data['u1'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="u5"  value="<?php echo $data['u5'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="u10" value="<?php echo $data['u10']; ?>" onchange="bblHitungAPGAR()"></td>
                            </tr>
                            <tr>
                                <td>Tanus Otot</td>
                                <td>Lumpuh</td><td>Ext. Fleksi Sedikit</td><td>Gerakan Aktif</td>
                                <td><input type="text" name="t1"  value="<?php echo $data['t1'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="t5"  value="<?php echo $data['t5'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="t10" value="<?php echo $data['t10']; ?>" onchange="bblHitungAPGAR()"></td>
                            </tr>
                            <tr>
                                <td>Refleks</td>
                                <td>Tidak Ada Respon</td><td>Pergerakan Sedikit</td><td>Menangis</td>
                                <td><input type="text" name="r1"  value="<?php echo $data['r1'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="r5"  value="<?php echo $data['r5'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="r10" value="<?php echo $data['r10']; ?>" onchange="bblHitungAPGAR()"></td>
                            </tr>
                            <tr>
                                <td>Warna</td>
                                <td>Biru Pucat</td><td>Tubuh Kemerahan, Tangan &amp; Kaki Biru</td><td>Kemerahan</td>
                                <td><input type="text" name="w1"  value="<?php echo $data['w1'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="w5"  value="<?php echo $data['w5'];  ?>" onchange="bblHitungAPGAR()"></td>
                                <td><input type="text" name="w10" value="<?php echo $data['w10']; ?>" onchange="bblHitungAPGAR()"></td>
                            </tr>
                            <tr class="apgar-total-row">
                                <td style="font-weight:700;">Jumlah Nilai</td>
                                <td colspan="3"></td>
                                <td class="total-cell"><input type="text" name="n1"  id="bbl_total_n1"  value="<?php echo $data['n1'];  ?>" readonly style="background:#f0fdf4;font-weight:700;"></td>
                                <td class="total-cell"><input type="text" name="n5"  id="bbl_total_n5"  value="<?php echo $data['n5'];  ?>" readonly style="background:#f0fdf4;font-weight:700;"></td>
                                <td class="total-cell"><input type="text" name="n10" id="bbl_total_n10" value="<?php echo $data['n10']; ?>" readonly style="background:#f0fdf4;font-weight:700;"></td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Antropometri Bayi -->
                    <div class="vital-grid" style="grid-template-columns: repeat(4,1fr); margin-top:12px;">
                        <div class="vital-item">
                            <label>BB Lahir</label>
                            <div class="input-with-unit">
                                <input type="text" name="bblahir" value="<?php echo $data['bblahir']; ?>" placeholder="0">
                                <span class="unit" style="padding:6px 8px;font-size:10px;">gram</span>
                            </div>
                        </div>
                        <div class="vital-item">
                            <label>Panjang Badan</label>
                            <div class="input-with-unit">
                                <input type="text" name="panjang_badan" value="<?php echo $data['panjang_badan']; ?>" placeholder="0">
                                <span class="unit" style="padding:6px 8px;font-size:10px;">cm</span>
                            </div>
                        </div>
                        <div class="vital-item">
                            <label>Lingkar Kepala (FO)</label>
                            <div class="input-with-unit">
                                <input type="text" name="lingkar_kepala" value="<?php echo $data['lingkar_kepala']; ?>" placeholder="0">
                                <span class="unit" style="padding:6px 8px;font-size:10px;">cm</span>
                            </div>
                        </div>
                        <div class="vital-item">
                            <label>Lingkar Dada</label>
                            <div class="input-with-unit">
                                <input type="text" name="lingkar_dada" value="<?php echo $data['lingkar_dada']; ?>" placeholder="0">
                                <span class="unit" style="padding:6px 8px;font-size:10px;">cm</span>
                            </div>
                        </div>
                    </div>

                    <!-- Resusitasi & Obat -->
                    <div class="form-grid" style="grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px;">
                        <div class="form-group">
                            <label>Resusitasi Saat Lahir</label>
                            <div style="display:flex;gap:6px;">
                                <select name="resusitasi_saat_lahir" style="width:160px;">
                                    <option value="Tidak"             <?php echo selOpt($data['resusitasi_saat_lahir'],'Tidak'); ?>>Tidak</option>
                                    <option value="Rangsang Taktil"   <?php echo selOpt($data['resusitasi_saat_lahir'],'Rangsang Taktil'); ?>>Rangsang Taktil</option>
                                    <option value="O2"                <?php echo selOpt($data['resusitasi_saat_lahir'],'O2'); ?>>O2</option>
                                    <option value="Ventilasi"         <?php echo selOpt($data['resusitasi_saat_lahir'],'Ventilasi'); ?>>Ventilasi</option>
                                    <option value="Kompresi Dada"     <?php echo selOpt($data['resusitasi_saat_lahir'],'Kompresi Dada'); ?>>Kompresi Dada</option>
                                    <option value="Intubasi"          <?php echo selOpt($data['resusitasi_saat_lahir'],'Intubasi'); ?>>Intubasi</option>
                                </select>
                                <input type="text" name="keterangan_resusitasi_saat_lahir" value="<?php echo $data['keterangan_resusitasi_saat_lahir']; ?>" placeholder="Keterangan" style="flex:1;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Obat Yang Diberikan Saat Lahir</label>
                            <input type="text" name="obat_diberikan_saat_lahir" value="<?php echo $data['obat_diberikan_saat_lahir']; ?>" placeholder="Nama obat...">
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:8px;">
                        <label>Keterangan Lainnya</label>
                        <input type="text" name="keterangan_lainnya_keadaan_bayi" value="<?php echo $data['keterangan_lainnya_keadaan_bayi']; ?>" placeholder="Keterangan lainnya keadaan bayi...">
                    </div>
                </div>

                <!-- ======================================================= -->
                <!-- IV. PEMERIKSAAN FISIK -->
                <!-- ======================================================= -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">biotech</i><h2>IV. PEMERIKSAAN FISIK</h2></div>
                    <div class="fisik-grid">
                        <?php
                        // Kiri (kolom 1) dan kanan (kolom 2) sesuai gambar
                        $fisikFields = [
                            // [nama_field, label, nama_ket_field]
                            ['kondisi_umum',        'Kondisi Umum',          'keterangan_kondisi_umum'],
                            ['tali_pusat',          'Tali Pusat',            'keterangan_tali_pusat'],
                            ['kulit',               'Kulit',                 'keterangan_kulit'],
                            ['alat_kelamin',        'Alat Kelamin',          'keterangan_alat_kelamin'],
                            ['kepala',              'Kepala',                'keterangan_kepala'],
                            ['ruas_tulang_belakang','Ruas Tulang Belakang',  'keterangan_ruas_tulang_belakang'],
                            ['leher',               'Leher',                 'keterangan_leher'],
                            ['extrimitas',          'Extrimitas',            'keterangan_extrimitas'],
                            ['mata',                'Mata',                  'keterangan_mata'],
                            ['anus',                'Anus',                  'keterangan_anus'],
                            ['hidung',              'Hidung',                'keterangan_hidung'],
                            ['refleks',             'Refleks',               'keterangan_refleks'],
                            ['telinga',             'Telinga',               'keterangan_telinga'],
                            ['denyut_femoral',      'Denyut Femoral/Radial', 'keterangan_denyut_femoral'],
                            ['dada',                'Dada',                  'keterangan_dada'],
                            ['paru',                'Paru',                  'keterangan_paru'],
                            ['jantung',             'Jantung',               'keterangan_jantung'],
                            ['perut',               'Perut',                 'keterangan_perut'],
                        ];
                        foreach($fisikFields as $f):
                            $fn  = $f[0]; $fl = $f[1]; $fk = $f[2];
                        ?>
                        <div class="fisik-item">
                            <label><?php echo $fl; ?></label>
                            <select name="<?php echo $fn; ?>">
                                <option value="Normal"         <?php echo selOpt($data[$fn],'Normal'); ?>>Normal</option>
                                <option value="Abnormal"       <?php echo selOpt($data[$fn],'Abnormal'); ?>>Abnormal</option>
                                <option value="Tidak Diperiksa" <?php echo selOpt($data[$fn],'Tidak Diperiksa'); ?>>Tidak Diperiksa</option>
                            </select>
                            <input type="text" name="<?php echo $fk; ?>" value="<?php echo isset($data[$fk]) ? $data[$fk] : ''; ?>" placeholder="Ket">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <!-- Pemeriksaan Fisik Lainnya (full width) -->
                    <div class="form-group" style="margin-top:10px;">
                        <label>Pemeriksaan Lainnya</label>
                        <textarea name="pemeriksaan_fisik_lainnya" rows="3" placeholder="Pemeriksaan fisik lainnya..."><?php echo $data['pemeriksaan_fisik_lainnya']; ?></textarea>
                    </div>
                </div>

                <!-- ======================================================= -->
                <!-- V. PEMERIKSAAN PENUNJANG -->
                <!-- ======================================================= -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">science</i><h2>V. PEMERIKSAAN PENUNJANG</h2></div>
                    <div class="form-group">
                        <textarea name="pemeriksaan_penunjang" rows="4" placeholder="Hasil pemeriksaan penunjang (Lab, Radiologi, dll)..."><?php echo $data['pemeriksaan_penunjang']; ?></textarea>
                    </div>
                </div>

                <!-- ======================================================= -->
                <!-- VI. DIAGNOSIS/ASESMEN -->
                <!-- ======================================================= -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">fact_check</i><h2>VI. DIAGNOSIS / ASESMEN</h2></div>
                    <div class="form-group">
                        <textarea name="diagnosa" rows="3" required placeholder="Tuliskan diagnosis/asesmen..."><?php echo $data['diagnosa']; ?></textarea>
                    </div>
                </div>

                <!-- ======================================================= -->
                <!-- VII. TATALAKSANA -->
                <!-- ======================================================= -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">medical_services</i><h2>VII. TATALAKSANA</h2></div>
                    <div class="form-group">
                        <textarea name="tatalaksana" rows="5" required placeholder="Tuliskan tatalaksana / rencana terapi..."><?php echo $data['tatalaksana']; ?></textarea>
                    </div>
                </div>

            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="bblKembali()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php
            $bolehEdit  = true;
            $bolehHapus = false;
            if($isEdit) {
                $kd_dokter_data = isset($rsCheck['kd_dokter']) ? $rsCheck['kd_dokter'] : '';
                if($kd_dokter_login === $kd_dokter_data) {
                    $bolehHapus = true;
                    $bolehEdit  = true;
                } else {
                    $bolehEdit = false;
                }
            }
            if($bolehHapus): ?>
            <button type="button" id="btn-delete-bbl" class="btn btn-danger" onclick="bblConfirmDelete()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter pengisi yang dapat menghapus">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <?php if($bolehEdit): ?>
            <button type="submit" id="btn-save-bbl" form="formPenilaianBayiBaruLahir" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
            </button>
            <?php else: ?>
            <button type="button" class="btn btn-primary" disabled title="Hanya dokter pengisi yang dapat mengubah data">
                <i class="material-icons">lock</i> SIMPAN DATA
            </button>
            <?php endif; ?>
        </div>

        <?php if($isEdit && !$bolehEdit): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:14px;"><strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo $nama_dokter_pengisi; ?></strong>. Anda hanya dapat melihat data.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================= -->
<!-- MODAL RIWAYAT PERSALINAN -->
<!-- ======================================================= -->
<div class="bbl-modal-overlay" id="bblModalRiwayat">
    <div class="bbl-modal-content">
        <div class="bbl-modal-header">
            <h3><i class="material-icons" style="font-size:16px;vertical-align:middle;">pregnant_woman</i> Tambah Riwayat Persalinan Ibu</h3>
        </div>
        <div class="bbl-modal-body">
            <div class="form-grid cols-2">
                <div class="form-group"><label>Tempat Persalinan</label><input type="text" id="bbl_rp_tempat" placeholder="Tempat Persalinan"></div>
                <div class="form-group"><label>Tanggal/Tahun</label><input type="date" id="bbl_rp_tgl" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="form-group"><label>Jenis Persalinan</label><input type="text" id="bbl_rp_jenis" placeholder="Spontan/SC/dll"></div>
                <div class="form-group"><label>Usia Hamil (minggu)</label><input type="text" id="bbl_rp_usiahamil" placeholder="38"></div>
                <div class="form-group"><label>Penolong</label><input type="text" id="bbl_rp_penolong" placeholder="Bidan/Dokter/dll"></div>
                <div class="form-group"><label>Jenis Kelamin</label>
                    <select id="bbl_rp_jk">
                        <option value="L">Laki-Laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div class="form-group"><label>Penyulit</label><input type="text" id="bbl_rp_penyulit" placeholder="Penyulit (jika ada)"></div>
                <div class="form-group"><label>BB/PB</label><input type="text" id="bbl_rp_bbpb" placeholder="3200gr / 50cm"></div>
                <div class="form-group" style="grid-column:span 2;"><label>Keadaan</label><input type="text" id="bbl_rp_keadaan" placeholder="Sehat/Meninggal/dll"></div>
            </div>
        </div>
        <div class="bbl-modal-footer">
            <button type="button" class="btn-bbl-modal btn-bbl-modal-secondary" onclick="bblCloseModalRiwayat()">
                <i class="material-icons" style="font-size:14px;">close</i> Tutup
            </button>
            <button type="button" class="btn-bbl-modal btn-bbl-modal-primary" onclick="bblSimpanRiwayat()">
                <i class="material-icons" style="font-size:14px;">save</i> Simpan
            </button>
        </div>
    </div>
</div>

<script>
const BBL_NO_RAWAT        = '<?php echo $no_rawat; ?>';
const BBL_INIT_NO_RM_IBU  = '<?php echo $data['no_rkm_medis_ibu']; ?>';
const BBL_API_RIWAYAT_URL = '<?php echo $base_url_bbl; ?>/pages/get_riwayat_persalinan.php';
const BBL_API_PROSES_URL  = '<?php echo $base_url_bbl; ?>/pages/proses3.php';
</script>
<script src="<?php echo $base_url_bbl; ?>/js/penilaianbayibarulahir.js?v=<?php echo time(); ?>"></script>
<script src="<?php echo $base_url_bbl; ?>/js/field-indicator.js?v=<?php echo time(); ?>"></script>