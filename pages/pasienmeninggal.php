<?php
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

// Ambil data pasien
$queryPasien = bukaquery("SELECT 
                            rp.no_rawat, rp.no_rkm_medis, rp.tgl_registrasi, rp.jam_reg,
                            p.nm_pasien, p.jk, p.tmp_lahir, p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur,
                            d.nm_dokter, d.kd_dokter
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN dokter d ON rp.kd_dokter = d.kd_dokter
                        WHERE rp.no_rawat = '$no_rawat'");
$rsPasien = mysqli_fetch_array($queryPasien);

if(!$rsPasien) {
    echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>";
    exit;
}

// Ambil kode dokter login
$kd_dokter_login = '';
$nm_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) {
    $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    $qDokter = bukaquery("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter_login'");
    $rsDokter = mysqli_fetch_array($qDokter);
    if($rsDokter) $nm_dokter_login = $rsDokter['nm_dokter'];
}

// Cek apakah sudah ada data pasien meninggal
$queryCheck = bukaquery("SELECT pm.*, d.nm_dokter as nama_dpjp 
                         FROM pasien_mati pm
                         LEFT JOIN dokter d ON pm.kd_dokter = d.kd_dokter
                         WHERE pm.no_rkm_medis = '$no_rkm_medis'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit = ($rsCheck) ? true : false;

// Data default
$data = array(
    'tanggal' => date('Y-m-d'),
    'jam' => date('H:i:s'),
    'no_rkm_medis' => $no_rkm_medis,
    'keterangan' => '',
    'temp_meninggal' => '-',
    'icd1' => '',
    'icd2' => '',
    'icd3' => '',
    'icd4' => '',
    'kd_dokter' => $kd_dokter_login
);

if($isEdit) {
    $data = array_merge($data, $rsCheck);
}

// Hak hapus - hanya DPJP yang mengisi
$bolehHapus = false;
if($isEdit && isset($rsCheck['kd_dokter']) && $kd_dokter_login === $rsCheck['kd_dokter']) {
    $bolehHapus = true;
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template4.css?v=<?php echo time(); ?>">

<style>
/* ICD Autocomplete styles */
.icd-autocomplete-wrapper { position: relative; }
.icd-autocomplete-list {
    display: none; position: absolute; z-index: 999; width: 100%;
    max-height: 250px; overflow-y: auto; background: #fff;
    border: 1px solid #ddd; border-radius: 6px; margin-top: 2px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15); list-style: none; padding: 0;
}
.icd-autocomplete-list li {
    padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0;
    font-size: 13px; transition: background 0.15s;
}
.icd-autocomplete-list li:hover { background: #e8f0fe; }
.icd-autocomplete-list li .icd-code {
    font-weight: 700; color: #1565c0; margin-right: 8px;
    background: #e3f2fd; padding: 2px 6px; border-radius: 4px; font-size: 12px;
}
.icd-autocomplete-list li .icd-name { color: #333; }
.icd-autocomplete-list li.no-data { color: #999; cursor: default; font-style: italic; }

/* Tabel Data Pasien Meninggal */
.tbl-meninggal { width: 100%; border-collapse: collapse; font-size: 13px; }
.tbl-meninggal th {
    background: linear-gradient(135deg, #1e40af, #0891b2); color: #fff;
    padding: 10px 8px; text-align: center; font-weight: 600; font-size: 12px;
    position: sticky; top: 0; z-index: 1;
}
.tbl-meninggal td { padding: 8px; border-bottom: 1px solid #e8e8e8; vertical-align: middle; }
.tbl-meninggal tr:hover { background: #f0f7ff; }
.tbl-meninggal tr:nth-child(even) { background: #fafbfc; }

/* Pagination */
.pagination-wrap { display: flex; justify-content: center; align-items: center; gap: 4px; margin-top: 12px; flex-wrap: wrap; }
.pagination-wrap button {
    padding: 6px 12px; border: 1px solid #ddd; background: #fff; border-radius: 6px;
    cursor: pointer; font-size: 13px; transition: all 0.2s;
}
.pagination-wrap button:hover { background: #e8f0fe; border-color: #1565c0; }
.pagination-wrap button.active { background: #1565c0; color: #fff; border-color: #1565c0; }
.pagination-wrap button:disabled { opacity: 0.4; cursor: not-allowed; }
</style>

<div class="modern-form-container">
    <!-- Patient Header -->
    <div class="patient-header">
        <h1 style="margin-bottom: 8px;">
            <i class="material-icons">person_off</i>
            DATA PASIEN MENINGGAL
            <?php if($isEdit): ?>
            <span class="mode-badge mode-edit">✏️ EDIT</span>
            <?php else: ?>
            <span class="mode-badge mode-add">➕ NEW</span>
            <?php endif; ?>
        </h1>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-content">
            <form id="formPasienMeninggal" method="post" action="">
                <input type="hidden" name="no_rkm_medis" value="<?php echo $no_rkm_medis; ?>">
                <input type="hidden" name="kd_dokter" value="<?php echo $kd_dokter_login; ?>">

                <!-- Baris 1: Tanggal Meninggal & Jam -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">event</i>
                        <h2>Informasi Kematian</h2>
                    </div>

                    <div class="form-grid" style="grid-template-columns: 180px 80px 80px 80px 200px;">
                        <div class="form-group">
                            <label>Tgl. Meninggal</label>
                            <input type="date" name="tanggal" value="<?php echo date('Y-m-d', strtotime($data['tanggal'])); ?>">
                        </div>
                        <div class="form-group">
                            <label>Jam</label>
                            <input type="number" name="jam_h" min="0" max="23" value="<?php echo date('H', strtotime($data['jam'])); ?>" placeholder="00" style="text-align:center;">
                        </div>
                        <div class="form-group">
                            <label>Menit</label>
                            <input type="number" name="jam_m" min="0" max="59" value="<?php echo date('i', strtotime($data['jam'])); ?>" placeholder="00" style="text-align:center;">
                        </div>
                        <div class="form-group">
                            <label>Detik</label>
                            <input type="number" name="jam_s" min="0" max="59" value="<?php echo date('s', strtotime($data['jam'])); ?>" placeholder="00" style="text-align:center;">
                        </div>
                        <div class="form-group">
                            <label>Di</label>
                            <select name="temp_meninggal">
                                <?php 
                                $opsiTempat = ['-', 'Rumah Sakit', 'Puskesmas', 'Rumah Bersalin', 'Rumah Tempat Tinggal', 'Lain-lain (Termasuk Doa)', 'Tidak tahu'];
                                foreach($opsiTempat as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo ($data['temp_meninggal'] == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Baris 2: No RM & Nama Pasien (readonly) -->
                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <div class="form-group">
                            <label>No. Rekam Medik</label>
                            <input type="text" value="<?php echo $no_rkm_medis; ?>" readonly style="background: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Nama Pasien</label>
                            <input type="text" value="<?php echo strtoupper($rsPasien['nm_pasien']).' ('.$rsPasien['umur'].')'; ?>" readonly style="background: #f5f5f5;">
                        </div>
                    </div>
                </div>

                <!-- Penyebab Kematian (ICD-X) -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">medical_information</i>
                        <h2>Penyebab Kematian (ICD-X)</h2>
                    </div>

                    <div class="form-grid cols-2">
                        <!-- ICD-X Dasar -->
                        <div class="form-group">
                            <label>ICD-X (Dasar)</label>
                            <div class="icd-autocomplete-wrapper">
                                <input type="text" id="cari_icd1" class="icd-search-input" autocomplete="off"
                                       placeholder="🔍 Ketik kode/nama ICD..."
                                       value="<?php echo $data['icd1']; ?>"
                                       data-target="icd1">
                                <input type="hidden" name="icd1" id="icd1" value="<?php echo $data['icd1']; ?>">
                                <ul id="icd1_list" class="icd-autocomplete-list"></ul>
                            </div>
                        </div>
                        <!-- ICD-X Antara #2 -->
                        <div class="form-group">
                            <label>ICD-X (Antara #2)</label>
                            <div class="icd-autocomplete-wrapper">
                                <input type="text" id="cari_icd2" class="icd-search-input" autocomplete="off"
                                       placeholder="🔍 Ketik kode/nama ICD..."
                                       value="<?php echo $data['icd2']; ?>"
                                       data-target="icd2">
                                <input type="hidden" name="icd2" id="icd2" value="<?php echo $data['icd2']; ?>">
                                <ul id="icd2_list" class="icd-autocomplete-list"></ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid cols-2" style="margin-top: 10px;">
                        <!-- ICD-X Antara #1 -->
                        <div class="form-group">
                            <label>ICD-X (Antara #1)</label>
                            <div class="icd-autocomplete-wrapper">
                                <input type="text" id="cari_icd3" class="icd-search-input" autocomplete="off"
                                       placeholder="🔍 Ketik kode/nama ICD..."
                                       value="<?php echo $data['icd3']; ?>"
                                       data-target="icd3">
                                <input type="hidden" name="icd3" id="icd3" value="<?php echo $data['icd3']; ?>">
                                <ul id="icd3_list" class="icd-autocomplete-list"></ul>
                            </div>
                        </div>
                        <!-- ICD-X Langsung -->
                        <div class="form-group">
                            <label>ICD-X (Langsung)</label>
                            <div class="icd-autocomplete-wrapper">
                                <input type="text" id="cari_icd4" class="icd-search-input" autocomplete="off"
                                       placeholder="🔍 Ketik kode/nama ICD..."
                                       value="<?php echo $data['icd4']; ?>"
                                       data-target="icd4">
                                <input type="hidden" name="icd4" id="icd4" value="<?php echo $data['icd4']; ?>">
                                <ul id="icd4_list" class="icd-autocomplete-list"></ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Keterangan & DPJP -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">description</i>
                        <h2>Keterangan</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Keterangan</label>
                            <input type="text" name="keterangan" value="<?php echo htmlspecialchars($data['keterangan']); ?>" placeholder="Keterangan tambahan...">
                        </div>
                        <div class="form-group">
                            <label>DPJP</label>
                            <input type="text" value="<?php echo $nm_dokter_login; ?>" readonly style="background: #f5f5f5;">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="window.history.back();">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete" class="btn btn-danger" onclick="confirmDeleteMeninggal()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya DPJP pengisi yang dapat menghapus">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save" form="formPasienMeninggal" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN DATA
            </button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 15px; display: flex; align-items: center; gap: 10px;">
            <i class="material-icons" style="color: #856404;">info</i>
            <span style="color: #856404; font-size: 14px;">
                <strong>Informasi:</strong> Data ini diisi oleh <strong><?php echo isset($rsCheck['nama_dpjp']) ? $rsCheck['nama_dpjp'] : '-'; ?></strong>. 
                Anda dapat melihat dan mengubah data, tetapi tidak dapat menghapusnya.
            </span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Tabel Data Pasien Meninggal -->
    <div class="form-card" style="margin-top: 15px;">
        <div class="form-content">
            <div class="section">
                <div class="section-header">
                    <i class="material-icons">table_chart</i>
                    <h2>Daftar Pasien Meninggal</h2>
                </div>
                <div style="overflow-x: auto;">
                    <table class="tbl-meninggal" id="tblMeninggal">
                        <thead>
                            <tr>
                                <th style="width:35px;">No</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>No. RM</th>
                                <th>Nama Pasien</th>
                                <th>Tempat</th>
                                <th>ICD Dasar</th>
                                <th>ICD Langsung</th>
                                <th>Keterangan</th>
                                <th>DPJP</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyMeninggal">
                            <tr><td colspan="10" style="text-align:center;color:#999;">Memuat data...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="paginationMeninggal" class="pagination-wrap"></div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/js/pasienmeninggal.js?v=<?php echo time(); ?>"></script>
