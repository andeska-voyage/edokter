<?php
/**
 * checklistpreoperasi.php
 * Form Checklist Pre Operasi - E-Dokter SIMRS Khanza
 */
define('BASE_URL_CPO', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';
$no_rawat = ''; $no_rkm_medis = '';
if(!empty($encrypted_norawat)) { $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd'); }
if(!empty($encrypted_norm))    { $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd'); }

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
if(!$rsPasien) { echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>"; exit; }

$kd_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) { $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd'); }

$queryCheck = bukaquery("SELECT cpo.*,
                            db.nm_dokter as nm_dokter_bedah, da.nm_dokter as nm_dokter_anestesi,
                            pr.nama as nm_petugas_ruangan, pok.nama as nm_perawat_ok
                         FROM checklist_pre_operasi cpo
                         LEFT JOIN dokter db  ON cpo.kd_dokter_bedah = db.kd_dokter
                         LEFT JOIN dokter da  ON cpo.kd_dokter_anestesi = da.kd_dokter
                         LEFT JOIN petugas pr ON cpo.nip_petugas_ruangan = pr.nip
                         LEFT JOIN petugas pok ON cpo.nip_perawat_ok = pok.nip
                         WHERE cpo.no_rawat = '$no_rawat'");
$rsCheck = mysqli_fetch_array($queryCheck);
$isEdit  = ($rsCheck) ? true : false;

$data = array(
    'tanggal' => date('Y-m-d H:i:s'), 'sncn' => '', 'tindakan' => '',
    'kd_dokter_bedah' => '', 'kd_dokter_anestesi' => '',
    'identitas' => 'Ya', 'surat_ijin_bedah' => 'Ada', 'surat_ijin_anestesi' => 'Ada',
    'surat_ijin_transfusi' => 'Ada', 'penandaan_area_operasi' => 'Ada', 'keadaan_umum' => 'Baik',
    'pemeriksaan_penunjang_rontgen' => 'Ada', 'keterangan_pemeriksaan_penunjang_rontgen' => '',
    'pemeriksaan_penunjang_ekg' => 'Ada', 'keterangan_pemeriksaan_penunjang_ekg' => '',
    'pemeriksaan_penunjang_usg' => 'Ada', 'keterangan_pemeriksaan_penunjang_usg' => '',
    'pemeriksaan_penunjang_ctscan' => 'Ada', 'keterangan_pemeriksaan_penunjang_ctscan' => '',
    'pemeriksaan_penunjang_mri' => 'Ada', 'keterangan_pemeriksaan_penunjang_mri' => '',
    'persiapan_darah' => 'Ada', 'keterangan_persiapan_darah' => '',
    'perlengkapan_khusus' => 'Ada', 'nip_petugas_ruangan' => '', 'nip_perawat_ok' => ''
);
if($isEdit) { $data = array_merge($data, $rsCheck); }

$bolehHapus = false;
if($isEdit && ($kd_dokter_login === $data['kd_dokter_bedah'] || $kd_dokter_login === $data['kd_dokter_anestesi'])) {
    $bolehHapus = true;
}

// Helper: select builder
function cpoOpts($name, $value, $opts) {
    $h = '<select name="'.$name.'">';
    foreach($opts as $o) { $s=($value==$o)?'selected':''; $h.='<option value="'.$o.'" '.$s.'>'.$o.'</option>'; }
    return $h.'</select>';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_CPO; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
/* Scoped: Checklist Pre Operasi */
.cpo-penunjang-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.cpo-penunjang-item { display: grid; grid-template-columns: 80px 120px 1fr; gap: 5px; align-items: center; padding: 6px 8px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; }
.cpo-penunjang-item label { font-size: 10px; font-weight: 500; color: #475569; }
.cpo-penunjang-item select, .cpo-penunjang-item input { padding: 4px 6px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 10px; }
.cpo-konfirmasi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.cpo-konfirmasi-item { display: grid; grid-template-columns: 1fr auto; gap: 5px; align-items: center; padding: 6px 8px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; }
.cpo-konfirmasi-item label { font-size: 10px; font-weight: 500; color: #475569; }
.cpo-konfirmasi-item select { padding: 4px 6px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 10px; min-width: 70px; }
.cpo-konfirmasi-ket { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.cpo-konfirmasi-ket-item { display: grid; grid-template-columns: 1fr auto 1fr; gap: 5px; align-items: center; padding: 6px 8px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; }
.cpo-konfirmasi-ket-item label { font-size: 10px; font-weight: 500; color: #475569; }
.cpo-konfirmasi-ket-item select, .cpo-konfirmasi-ket-item input { padding: 4px 6px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 10px; }
/* Autocomplete */
.cpo-ac-wrap { position: relative; }
.cpo-ac-dd { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,.15); max-height: 200px; overflow-y: auto; z-index: 99; display: none; }
.cpo-ac-dd.show { display: block; }
.cpo-ac-dd div { padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
.cpo-ac-dd div:hover { background: #f0f9ff; }
.cpo-ac-dd div strong { color: #1e40af; }
.cpo-ac-dd .no-result { color: #94a3b8; text-align: center; font-style: italic; }
@media (max-width: 1400px) { .cpo-penunjang-grid { grid-template-columns: 1fr; } .cpo-konfirmasi-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) { .cpo-konfirmasi-grid, .cpo-konfirmasi-ket { grid-template-columns: 1fr; } }
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">checklist_rtl</i>
                CHECKLIST PRE OPERASI
                <?php if($isEdit): ?><span class="mode-badge mode-edit">✏️ EDIT</span>
                <?php else: ?><span class="mode-badge mode-add">➕ NEW</span><?php endif; ?>
            </h1>
        </div>
        <div class="patient-info">
            <div class="info-item"><i class="material-icons">folder</i><strong>No. Rawat:</strong> <?php echo $rsPasien['no_rawat']; ?></div>
            <div class="info-item"><i class="material-icons">badge</i><strong>No. RM:</strong> <?php echo $rsPasien['no_rkm_medis']; ?></div>
            <div class="info-item"><i class="material-icons">person</i><strong>Nama:</strong> <?php echo strtoupper($rsPasien['nm_pasien']); ?></div>
            <div class="info-item"><i class="material-icons">cake</i><strong>Tgl Lahir:</strong> <?php echo date('d-m-Y', strtotime($rsPasien['tgl_lahir'])); ?> (<?php echo $rsPasien['umur']; ?>)</div>
        </div>
    </div>

    <div class="form-card">
        <div class="form-content">
            <form id="formChecklistPreOperasi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">

                <!-- I. Data Operasi -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">event_note</i>
                        <h2>Data Operasi</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="text" name="tanggal" value="<?php echo date('d-m-Y H:i:s', strtotime($data['tanggal'])); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>SN/CN</label>
                            <input type="text" name="sncn" value="<?php echo htmlspecialchars($data['sncn']); ?>" placeholder="SN/CN">
                        </div>
                    </div>
                    <div class="form-grid cols-2" style="margin-top:8px;">
                        <div class="form-group cpo-ac-wrap">
                            <label>Dokter Bedah</label>
                            <input type="hidden" name="kd_dokter_bedah" id="cpo_kd_dokter_bedah" value="<?php echo htmlspecialchars($data['kd_dokter_bedah']); ?>">
                            <input type="text" id="cpo_nm_dokter_bedah" placeholder="Ketik nama dokter bedah..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_bedah']) ? htmlspecialchars($rsCheck['nm_dokter_bedah']) : ''; ?>">
                            <div id="cpo_ac_dokter_bedah" class="cpo-ac-dd"></div>
                        </div>
                        <div class="form-group cpo-ac-wrap">
                            <label>Dokter Anestesi</label>
                            <input type="hidden" name="kd_dokter_anestesi" id="cpo_kd_dokter_anestesi" value="<?php echo htmlspecialchars($data['kd_dokter_anestesi']); ?>">
                            <input type="text" id="cpo_nm_dokter_anestesi" placeholder="Ketik nama dokter anestesi..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_dokter_anestesi']) ? htmlspecialchars($rsCheck['nm_dokter_anestesi']) : ''; ?>">
                            <div id="cpo_ac_dokter_anestesi" class="cpo-ac-dd"></div>
                        </div>
                    </div>
                    <div class="form-grid cols-1" style="margin-top:8px;">
                        <div class="form-group">
                            <label>Tindakan</label>
                            <input type="text" name="tindakan" value="<?php echo htmlspecialchars($data['tindakan']); ?>" placeholder="Nama tindakan operasi...">
                        </div>
                    </div>
                </div>

                <!-- II. Perawat Melakukan Konfirmasi -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">fact_check</i>
                        <h2>Perawat Melakukan Konfirmasi</h2>
                    </div>
                    <div class="cpo-konfirmasi-grid">
                        <div class="cpo-konfirmasi-item"><label>Identitas :</label><?php echo cpoOpts('identitas', $data['identitas'], ['Ya','Tidak']); ?></div>
                        <div class="cpo-konfirmasi-item"><label>Keadaan Umum Pasien :</label><?php echo cpoOpts('keadaan_umum', $data['keadaan_umum'], ['Baik','Sedang','Lemah']); ?></div>
                        <div class="cpo-konfirmasi-item"><label>Penandaan Area Operasi :</label><?php echo cpoOpts('penandaan_area_operasi', $data['penandaan_area_operasi'], ['Ada','Tidak Ada','Tidak Diperlukan']); ?></div>
                        <div class="cpo-konfirmasi-item"><label>Surat Ijin Bedah :</label><?php echo cpoOpts('surat_ijin_bedah', $data['surat_ijin_bedah'], ['Ada','Tidak Ada']); ?></div>
                        <div class="cpo-konfirmasi-item"><label>Surat Ijin Anestesi :</label><?php echo cpoOpts('surat_ijin_anestesi', $data['surat_ijin_anestesi'], ['Ada','Tidak Ada']); ?></div>
                        <div class="cpo-konfirmasi-item"><label>Surat Ijin Transfusi :</label><?php echo cpoOpts('surat_ijin_transfusi', $data['surat_ijin_transfusi'], ['Ada','Tidak Ada','Tidak Diperlukan']); ?></div>
                    </div>
                    <div class="cpo-konfirmasi-ket" style="margin-top:8px;">
                        <div class="cpo-konfirmasi-ket-item">
                            <label>Persiapan Darah :</label>
                            <?php echo cpoOpts('persiapan_darah', $data['persiapan_darah'], ['Ada','Tidak Ada','Tidak Diperlukan']); ?>
                            <input type="text" name="keterangan_persiapan_darah" value="<?php echo htmlspecialchars($data['keterangan_persiapan_darah']); ?>" placeholder="Ket...">
                        </div>
                        <div class="cpo-konfirmasi-item"><label>Perlengkapan Khusus, Alat/Implan :</label><?php echo cpoOpts('perlengkapan_khusus', $data['perlengkapan_khusus'], ['Ada','Tidak Ada','Tidak Diperlukan']); ?></div>
                    </div>
                </div>

                <!-- III. Hasil Pemeriksaan Penunjang -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">biotech</i>
                        <h2>Hasil Pemeriksaan Penunjang</h2>
                    </div>
                    <div class="cpo-penunjang-grid">
                        <?php
                        $penunjangFields = [
                            ['Radiologi', 'pemeriksaan_penunjang_rontgen', 'keterangan_pemeriksaan_penunjang_rontgen'],
                            ['EKG',       'pemeriksaan_penunjang_ekg',     'keterangan_pemeriksaan_penunjang_ekg'],
                            ['USG',       'pemeriksaan_penunjang_usg',     'keterangan_pemeriksaan_penunjang_usg'],
                            ['CT Scan',   'pemeriksaan_penunjang_ctscan',  'keterangan_pemeriksaan_penunjang_ctscan'],
                            ['MRI',       'pemeriksaan_penunjang_mri',     'keterangan_pemeriksaan_penunjang_mri'],
                        ];
                        foreach($penunjangFields as $pf):
                        ?>
                        <div class="cpo-penunjang-item">
                            <label><?php echo $pf[0]; ?></label>
                            <?php echo cpoOpts($pf[1], $data[$pf[1]], ['Ada','Tidak Ada','Tidak Diperlukan']); ?>
                            <input type="text" name="<?php echo $pf[2]; ?>" value="<?php echo htmlspecialchars($data[$pf[2]]); ?>" placeholder="Ket">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- IV. Petugas -->
                <div class="section">
                    <div class="section-header">
                        <i class="material-icons">people</i>
                        <h2>Petugas</h2>
                    </div>
                    <div class="form-grid cols-2">
                        <div class="form-group cpo-ac-wrap">
                            <label>Petugas Ruangan</label>
                            <input type="hidden" name="nip_petugas_ruangan" id="cpo_nip_petugas_ruangan" value="<?php echo htmlspecialchars($data['nip_petugas_ruangan']); ?>">
                            <input type="text" id="cpo_nm_petugas_ruangan" placeholder="Ketik nama petugas ruangan..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_petugas_ruangan']) ? htmlspecialchars($rsCheck['nm_petugas_ruangan']) : ''; ?>">
                            <div id="cpo_ac_petugas_ruangan" class="cpo-ac-dd"></div>
                        </div>
                        <div class="form-group cpo-ac-wrap">
                            <label>Petugas OK</label>
                            <input type="hidden" name="nip_perawat_ok" id="cpo_nip_perawat_ok" value="<?php echo htmlspecialchars($data['nip_perawat_ok']); ?>">
                            <input type="text" id="cpo_nm_perawat_ok" placeholder="Ketik nama petugas OK..." autocomplete="off"
                                   value="<?php echo $isEdit && isset($rsCheck['nm_perawat_ok']) ? htmlspecialchars($rsCheck['nm_perawat_ok']) : ''; ?>">
                            <div id="cpo_ac_perawat_ok" class="cpo-ac-dd"></div>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliChecklistPreOperasi()">
                <i class="material-icons">arrow_back</i> KEMBALI
            </button>
            <?php if($bolehHapus): ?>
            <button type="button" id="btn-delete-cpo" class="btn btn-danger" onclick="confirmDeleteChecklistPreOperasi()">
                <i class="material-icons">delete</i> HAPUS
            </button>
            <?php elseif($isEdit): ?>
            <button type="button" class="btn btn-danger" disabled title="Hanya dokter bedah / dokter anestesi yang dapat menghapus">
                <i class="material-icons">lock</i> HAPUS
            </button>
            <?php endif; ?>
            <button type="submit" id="btn-save-cpo" form="formChecklistPreOperasi" class="btn btn-primary">
                <i class="material-icons">save</i> SIMPAN
            </button>
        </div>

        <?php if($isEdit && !$bolehHapus): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;display:flex;align-items:center;gap:10px;">
            <i class="material-icons" style="color:#856404;">info</i>
            <span style="color:#856404;font-size:13px;"><strong>Info:</strong> Hanya <strong>Dokter Bedah</strong> atau <strong>Dokter Anestesi</strong> yang dapat menghapus data ini.</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL_CPO; ?>/js/checklistpreoperasi.js?v=<?php echo time(); ?>"></script>
