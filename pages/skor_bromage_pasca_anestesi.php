<?php
/**
 * skor_bromage_pasca_anestesi.php
 * Form Skor Bromage Pasca Anestesi - E-Dokter SIMRS Khanza
 */
define('BASE_URL_SBP', APP_BASE_URL);

$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$encrypted_norm    = isset($_GET['rm'])  ? $_GET['rm']  : '';
$no_rawat = ''; $no_rkm_medis = '';
if(!empty($encrypted_norawat)) { $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd'); }
if(!empty($encrypted_norm))    { $no_rkm_medis = encrypt_decrypt(urldecode($encrypted_norm), 'd'); }

$queryPasien = bukaquery("SELECT 
                            rp.no_rawat, rp.no_rkm_medis,
                            p.nm_pasien, p.jk, p.tgl_lahir,
                            CONCAT(rp.umurdaftar, ' ', rp.sttsumur) as umur
                        FROM reg_periksa rp
                        INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        WHERE rp.no_rawat = '$no_rawat'");
$rsPasien = mysqli_fetch_array($queryPasien);
if(!$rsPasien) { echo "<script>alert('Data pasien tidak ditemukan!'); window.history.back();</script>"; exit; }

$kd_dokter_login = '';
if(!empty($_SESSION['ses_dokter'])) { $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd'); }

// Ambil NIP petugas login (jika ada)
$nip_login = '';
if(!empty($_SESSION['ses_nip'])) { $nip_login = encrypt_decrypt($_SESSION['ses_nip'], 'd'); }

// Ambil semua data existing untuk tabel riwayat
$queryList = bukaquery("SELECT sbp.*, d.nm_dokter as nm_dokter_anestesi, pt.nama as nm_petugas
                        FROM skor_bromage_pasca_anestesi sbp
                        LEFT JOIN dokter d ON sbp.kd_dokter = d.kd_dokter
                        LEFT JOIN petugas pt ON sbp.nip = pt.nip
                        WHERE sbp.no_rawat = '$no_rawat'
                        ORDER BY sbp.tanggal DESC");

// Skala options
$skala_opts = [
    'Gerakan Penuh Dari Tungkai' => 0,
    'Tidak Mampu Extensi Tungkai' => 1,
    'Tidak Mampu Flexi Lutut' => 2,
    'Tidak Mampu Flexi Pergelangan Kaki' => 3,
];

function sbpSelect($name, $value, $opts) {
    $h = '<select name="'.$name.'" id="sbp_skala1" class="sbp-skala-select">';
    foreach($opts as $label => $score) {
        $s = ($value == $label) ? 'selected' : '';
        $h .= '<option value="'.htmlspecialchars($label).'" data-score="'.$score.'" '.$s.'>'.$label.'</option>';
    }
    return $h.'</select>';
}
?>

<link rel="stylesheet" href="<?php echo BASE_URL_SBP; ?>/css/template4.css?v=<?php echo time(); ?>">
<style>
.sbp-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding:6px 10px; background:#f8fafc; border-radius:5px; border:1px solid #e2e8f0; margin-bottom:5px; }
.sbp-row label { font-size:11px; font-weight:600; color:#475569; white-space:nowrap; }
.sbp-row select { padding:4px 6px; border:1px solid #cbd5e1; border-radius:4px; font-size:11px; flex:1; min-width:250px; }
.sbp-row select:focus { border-color:#667eea; outline:none; box-shadow:0 0 0 2px rgba(102,126,234,0.15); }
.sbp-row .sbp-nilai-input { width:50px; padding:4px 6px; border:1px solid #cbd5e1; border-radius:4px; font-size:12px; font-weight:700; text-align:center; background:#f1f5f9; color:#1e293b; }
.sbp-keterangan { font-size:11px; font-style:italic; padding:4px 10px; }
.sbp-layout { display:grid; grid-template-columns:1fr 1fr; gap:15px; align-items:start; }
.sbp-tabel-ref { border-collapse:collapse; width:100%; font-size:11px; }
.sbp-tabel-ref th, .sbp-tabel-ref td { border:1px solid #e2e8f0; padding:6px 8px; text-align:center; vertical-align:middle; }
.sbp-tabel-ref th { background:#f1f5f9; font-weight:700; color:#475569; font-size:10px; }
.sbp-tabel-ref td { color:#1e293b; }
.sbp-tabel-ref td img { max-width:80px; height:auto; }
/* Riwayat table */
.sbp-riwayat { border-collapse:collapse; width:100%; font-size:11px; margin-top:10px; }
.sbp-riwayat th { background:#667eea; color:white; padding:8px 10px; font-size:10px; text-transform:uppercase; }
.sbp-riwayat td { padding:6px 10px; border-bottom:1px solid #e2e8f0; }
.sbp-riwayat tr:hover { background:#f0f9ff; }
.sbp-riwayat .btn-hapus-row { background:#f44336; color:white; border:none; border-radius:4px; padding:3px 8px; font-size:10px; cursor:pointer; font-weight:600; }
.sbp-riwayat .btn-hapus-row:hover { background:#d32f2f; }
/* Autocomplete */
.sbp-ac-wrap { position:relative; }
.sbp-ac-dd { position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #e2e8f0; border-radius:0 0 8px 8px; box-shadow:0 4px 12px rgba(0,0,0,.15); max-height:200px; overflow-y:auto; z-index:99; display:none; }
.sbp-ac-dd.show { display:block; }
.sbp-ac-dd div { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; font-size:12px; }
.sbp-ac-dd div:hover { background:#f0f9ff; }
.sbp-ac-dd div strong { color:#1e40af; }
.sbp-ac-dd .no-result { color:#94a3b8; text-align:center; font-style:italic; }
@media(max-width:768px){ .sbp-layout { grid-template-columns:1fr; } }
</style>

<div class="modern-form-container">
    <div class="patient-header">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <h1 style="margin:0;display:flex;align-items:center;gap:10px;">
                <i class="material-icons">assessment</i>
                SKOR BROMAGE PASCA ANESTESI
                <span class="mode-badge mode-add">➕ NEW</span>
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
            <form id="formSkorBromagePascaAnestesi" method="post" action="">
                <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
                <input type="hidden" name="mode" id="sbp_mode" value="add">
                <input type="hidden" name="tanggal_edit" id="sbp_tanggal_edit" value="">

                <!-- Data Umum -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">event_note</i><h2>Data Umum</h2></div>
                    <div class="form-grid cols-2">
                        <div class="form-group sbp-ac-wrap">
                            <label>Petugas</label>
                            <input type="hidden" name="nip" id="sbp_nip" value="">
                            <input type="text" id="sbp_nm_petugas" placeholder="Ketik nama petugas..." autocomplete="off">
                            <div id="sbp_ac_petugas" class="sbp-ac-dd"></div>
                        </div>
                        <div class="form-group sbp-ac-wrap">
                            <label>Dokter Anestesi</label>
                            <input type="hidden" name="kd_dokter" id="sbp_kd_dokter" value="">
                            <input type="text" id="sbp_nm_dokter" placeholder="Ketik nama dokter anestesi..." autocomplete="off">
                            <div id="sbp_ac_dokter" class="sbp-ac-dd"></div>
                        </div>
                    </div>
                    <div class="form-grid cols-1" style="margin-top:6px;">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="hidden" name="tanggal" value="<?php echo date('Y-m-d H:i:s'); ?>">
                            <input type="text" value="<?php echo date('d-m-Y H:i:s'); ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Kriteria -->
                <div class="section">
                    <div class="section-header"><i class="material-icons">grading</i><h2>Kriteria Penilaian</h2></div>

                    <div class="sbp-layout">
                        <!-- Kiri: Tabel referensi dengan gambar per skor -->
                        <div>
                            <table class="sbp-tabel-ref">
                                <thead>
                                    <tr><th>Skor</th><th>Gambar</th><th>Keterangan</th><th>Tingkat Blok</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>0</td><td><img src="images/bromage0.png" alt="Skor 0"></td><td style="text-align:left;">Gerakan Penuh</td><td>Nihil (0%)</td></tr>
                                    <tr><td>1</td><td><img src="images/bromage1.png" alt="Skor 1"></td><td style="text-align:left;">Hanya mampu memflexikan lutut dengan gerakan bebas dari kaki</td><td>Parsial (33%)</td></tr>
                                    <tr><td>2</td><td><img src="images/bromage2.png" alt="Skor 2"></td><td style="text-align:left;">Tidak dapat memflexikan tetapi dapat gerakkan bebas dari kaki</td><td>Hampir lengkap (66%)</td></tr>
                                    <tr><td>3</td><td><img src="images/bromage3.png" alt="Skor 3"></td><td style="text-align:left;">Kaki tidak dapat digerakkan dan lutut tidak bisa di flexikan</td><td>Lengkap (100%)</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Kanan: Keluar & Instruksi -->
                        <div>
                            <div class="form-group">
                                <label>Keluar</label>
                                <textarea name="keluar" rows="5" placeholder="Keterangan keluar..."></textarea>
                            </div>
                            <div class="form-group" style="margin-top:10px;">
                                <label>Instruksi / Tindakan di Ruang Pemulihan (RR)</label>
                                <textarea name="instruksi" rows="5" placeholder="Instruksi / tindakan..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Skala & Nilai -->
                    <div class="sbp-row" style="margin-top:10px;">
                        <label>Skala :</label>
                        <?php echo sbpSelect('penilaian_skala1', 'Tidak Mampu Flexi Pergelangan Kaki', $skala_opts); ?>
                        <span style="font-size:11px;font-weight:600;color:#475569;">Nilai :</span>
                        <input type="text" name="penilaian_nilai1" id="sbp_nilai1" class="sbp-nilai-input" value="3" readonly>
                    </div>

                    <div class="sbp-keterangan" id="sbp_keterangan" style="color:#dc2626;">
                        Pasien Tidak Dapat Dipindahkan Ke Ruangan Perawatan, Karena Kondisi Yang Lemah
                    </div>
                </div>

            </form>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-secondary" onclick="kembaliSkorBromage()"><i class="material-icons">arrow_back</i> KEMBALI</button>
            <button type="submit" id="btn-save-sbp" form="formSkorBromagePascaAnestesi" class="btn btn-primary"><i class="material-icons">save</i> SIMPAN</button>
        </div>

        <!-- Riwayat Data -->
        <div class="section" style="margin-top:15px;">
            <div class="section-header"><i class="material-icons">history</i><h2>Riwayat Penilaian Bromage</h2></div>
            <?php
            $jml = mysqli_num_rows($queryList);
            if($jml == 0) {
                echo '<div style="text-align:center;padding:20px;color:#94a3b8;font-size:12px;">
                        <i class="material-icons" style="font-size:36px;">inbox</i><br>Belum ada data penilaian
                      </div>';
            } else {
                echo '<table class="sbp-riwayat">
                        <thead><tr>
                            <th>No</th><th>Tanggal</th><th>Skala</th><th>Nilai</th><th>Keluar</th><th>Instruksi</th><th>Petugas</th><th>Dokter Anestesi</th><th>Aksi</th>
                        </tr></thead><tbody>';
                $no = 1;
                mysqli_data_seek($queryList, 0);
                while($rw = mysqli_fetch_array($queryList)) {
                    $tgl_fmt = date('d-m-Y H:i', strtotime($rw['tanggal']));
                    $nm_pet = htmlspecialchars($rw['nm_petugas'] ?? '-');
                    $nm_dok = htmlspecialchars($rw['nm_dokter_anestesi'] ?? '-');
                    $skala  = htmlspecialchars($rw['penilaian_skala1'] ?? '-');
                    $nilai  = intval($rw['penilaian_nilai1']);
                    $keluar = htmlspecialchars(mb_strimwidth($rw['keluar'] ?? '', 0, 40, '...'));
                    $instr  = htmlspecialchars(mb_strimwidth($rw['instruksi'] ?? '', 0, 40, '...'));
                    $tgl_raw = htmlspecialchars($rw['tanggal']);
                    
                    // Cek boleh edit/hapus: dokter anestesi pengisi ATAU petugas pengisi
                    $canAction = false;
                    if(!empty($kd_dokter_login) && $kd_dokter_login === $rw['kd_dokter']) $canAction = true;
                    if(!empty($nip_login) && $nip_login === $rw['nip']) $canAction = true;
                    // Data untuk edit - escape untuk JS
                    $edit_skala = addslashes($rw['penilaian_skala1'] ?? '');
                    $edit_keluar = addslashes($rw['keluar'] ?? '');
                    $edit_instruksi = addslashes($rw['instruksi'] ?? '');
                    $edit_nip = $rw['nip'] ?? '';
                    $edit_nm_pet = addslashes($rw['nm_petugas'] ?? '');
                    $edit_kd_dok = $rw['kd_dokter'] ?? '';
                    $edit_nm_dok = addslashes($rw['nm_dokter_anestesi'] ?? '');
                    
                    $aksiHtml = '';
                    if($canAction) {
                        $aksiHtml = '<button class="btn-hapus-row" style="background:#667eea;margin-right:3px;" onclick="editBromageRow(\''.$tgl_raw.'\',\''.$edit_skala.'\','.intval($rw['penilaian_nilai1']).',\''.$edit_keluar.'\',\''.$edit_instruksi.'\',\''.$edit_nip.'\',\''.$edit_nm_pet.'\',\''.$edit_kd_dok.'\',\''.$edit_nm_dok.'\')"><i class="material-icons" style="font-size:12px;vertical-align:middle;">edit</i> Edit</button>';
                        $aksiHtml .= '<button class="btn-hapus-row" onclick="hapusBromageRow(\''.$no_rawat.'\',\''.$tgl_raw.'\')"><i class="material-icons" style="font-size:12px;vertical-align:middle;">delete</i> Hapus</button>';
                    } else {
                        $aksiHtml = '<span style="color:#ccc;font-size:10px;">-</span>';
                    }
                    
                    echo "<tr>
                            <td>{$no}</td>
                            <td>{$tgl_fmt}</td>
                            <td style=\"text-align:left;\">{$skala}</td>
                            <td><strong>{$nilai}</strong></td>
                            <td style=\"text-align:left;\">{$keluar}</td>
                            <td style=\"text-align:left;\">{$instr}</td>
                            <td>{$nm_pet}</td>
                            <td>{$nm_dok}</td>
                            <td style=\"white-space:nowrap;\">{$aksiHtml}</td>
                          </tr>";
                    $no++;
                }
                echo '</tbody></table>';
            }
            ?>
        </div>
    </div>
</div>

<script src="<?php echo BASE_URL_SBP; ?>/js/skor_bromage_pasca_anestesi.js?v=<?php echo time(); ?>"></script>
