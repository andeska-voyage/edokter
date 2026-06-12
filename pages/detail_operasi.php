<?php
/**
 * detail_operasi.php
 * Menampilkan ringkasan RME pra-operasi pasien (compact view)
 * Dipanggil via AJAX dari listoperasi.php
 */

if(session_status() == PHP_SESSION_NONE) {
    session_start();
}

include_once('../conf/conf.php');

// Validasi parameter
if(!isset($_GET['no_rawat']) || !isset($_GET['no_rm'])) {
    echo '<div style="padding: 20px; text-align: center; color: #999;">
            <i class="material-icons" style="font-size: 32px;">error_outline</i>
            <p>Parameter tidak lengkap</p>
          </div>';
    exit;
}

$no_rawat = encrypt_decrypt(urldecode($_GET['no_rawat']), 'd');
$no_rm = encrypt_decrypt(urldecode($_GET['no_rm']), 'd');

if(empty($no_rawat) || empty($no_rm)) {
    echo '<div style="padding: 20px; text-align: center; color: #999;">
            <i class="material-icons" style="font-size: 32px;">error_outline</i>
            <p>Data tidak valid</p>
          </div>';
    exit;
}

$today = date('Y-m-d');

// Helper: safe query
function safeQueryOperasi($sql) {
    $result = bukaquery_safe($sql);
    if($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_array($result);
    }
    return null;
}

// =============================================
// 1. PENILAIAN PRE ANESTESI
// =============================================
$pre_anestesi = safeQueryOperasi("SELECT * FROM penilaian_pre_anestesi WHERE no_rawat = '$no_rawat' ORDER BY tanggal DESC LIMIT 1");

// =============================================
// 2. PENILAIAN PRE OPERASI
// =============================================
$pre_operasi = safeQueryOperasi("SELECT * FROM penilaian_pre_operasi WHERE no_rawat = '$no_rawat' ORDER BY tanggal DESC LIMIT 1");

// =============================================
// 3. PENILAIAN PRE INDUKSI
// =============================================
$pre_induksi = safeQueryOperasi("SELECT * FROM penilaian_pre_induksi WHERE no_rawat = '$no_rawat' ORDER BY tanggal DESC LIMIT 1");

// =============================================
// 4. CHECKLIST PRE OPERASI
// =============================================
$checklist_pre_op = safeQueryOperasi("SELECT * FROM checklist_pre_operasi WHERE no_rawat = '$no_rawat' ORDER BY tanggal DESC LIMIT 1");

// =============================================
// 5. SIGN IN SEBELUM ANESTESI
// =============================================
$signin = safeQueryOperasi("SELECT * FROM signin_sebelum_anestesi WHERE no_rawat = '$no_rawat' ORDER BY tanggal DESC LIMIT 1");

// =============================================
// 6. CHECKLIST KESIAPAN ANESTESI
// =============================================
$kesiapan_anestesi = safeQueryOperasi("SELECT * FROM checklist_kesiapan_anestesi WHERE no_rawat = '$no_rawat' ORDER BY tanggal DESC LIMIT 1");

// =============================================
// 7. TIMEOUT SEBELUM INSISI
// =============================================
$timeout = safeQueryOperasi("SELECT * FROM timeout_sebelum_insisi WHERE no_rawat = '$no_rawat' ORDER BY tanggal DESC LIMIT 1");

// Nama dokter helper
function getNamaDokter($kd_dokter) {
    if(empty($kd_dokter)) return '-';
    $q = bukaquery_safe("SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter'");
    $r = mysqli_fetch_array($q);
    return $r ? $r['nm_dokter'] : '-';
}

// Helper badge
function statusBadge($exists) {
    if($exists) {
        return '<span style="background:#4caf50;color:#fff;padding:2px 8px;border-radius:8px;font-size:9px;font-weight:600;">
                    <i class="material-icons" style="font-size:10px;vertical-align:middle;">check_circle</i> Terisi
                </span>';
    }
    return '<span style="background:#e0e0e0;color:#999;padding:2px 8px;border-radius:8px;font-size:9px;font-weight:600;">
                <i class="material-icons" style="font-size:10px;vertical-align:middle;">remove_circle_outline</i> Belum
            </span>';
}

// Helper display value
function val($data, $key, $default = '-') {
    return (!empty($data[$key]) && $data[$key] != '0000-00-00 00:00:00') ? htmlspecialchars($data[$key]) : $default;
}

// Cek apakah semua kosong
$has_any_data = ($pre_anestesi || $pre_operasi || $pre_induksi || $checklist_pre_op || $signin || $kesiapan_anestesi || $timeout);
?>

<div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 0 0 10px 10px; margin-top: -5px; padding: 0; overflow: hidden;">

    <!-- Header Ringkasan Status -->
    <div style="background: linear-gradient(135deg, #1a237e 0%, #283593 100%); padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
        <div style="color: white; font-size: 13px; font-weight: 600;">
            <i class="material-icons" style="font-size: 16px; vertical-align: middle;">assignment</i>
            RINGKASAN RME PRA-OPERASI
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
            <span style="background: <?= $pre_anestesi ? '#4caf50' : 'rgba(255,255,255,0.15)'; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">Pre Anestesi</span>
            <span style="background: <?= $pre_operasi ? '#4caf50' : 'rgba(255,255,255,0.15)'; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">Pre Operasi</span>
            <span style="background: <?= $pre_induksi ? '#4caf50' : 'rgba(255,255,255,0.15)'; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">Pre Induksi</span>
            <span style="background: <?= $checklist_pre_op ? '#4caf50' : 'rgba(255,255,255,0.15)'; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">Checklist Pre Op</span>
            <span style="background: <?= $signin ? '#4caf50' : 'rgba(255,255,255,0.15)'; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">Sign In</span>
            <span style="background: <?= $kesiapan_anestesi ? '#4caf50' : 'rgba(255,255,255,0.15)'; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">Kesiapan Anestesi</span>
            <span style="background: <?= $timeout ? '#4caf50' : 'rgba(255,255,255,0.15)'; ?>; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">Time Out</span>
        </div>
    </div>

    <?php if(!$has_any_data): ?>
    <div style="padding: 30px; text-align: center; color: #999;">
        <i class="material-icons" style="font-size: 40px; color: #e0e0e0;">description</i>
        <p style="margin: 10px 0 0; font-size: 13px;">Belum ada data RME pra-operasi</p>
    </div>
    <?php else: ?>

    <div style="padding: 15px; display: flex; flex-wrap: wrap; gap: 12px;">

        <!-- ========== 1. PENILAIAN PRE ANESTESI ========== -->
        <?php if($pre_anestesi): ?>
        <div style="flex: 1; min-width: 320px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #e91e63; padding: 12px;">
            <div style="font-size: 12px; font-weight: 700; color: #e91e63; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">medical_services</i>
                PENILAIAN PRE ANESTESI
                <span style="float: right; font-weight: 400; color: #999; font-size: 10px;"><?= date('H:i', strtotime($pre_anestesi['tanggal'])); ?></span>
            </div>
            <table style="width: 100%; font-size: 11px; color: #555;">
                <tr><td style="width:35%;padding:2px 0;color:#888;">Diagnosa</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_anestesi, 'diagnosa'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Rencana Tindakan</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_anestesi, 'rencana_tindakan'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">TD / Nadi / RR / Suhu</td>
                    <td style="padding:2px 0;font-weight:600;">
                        <?= val($pre_anestesi, 'td'); ?> / <?= val($pre_anestesi, 'nadi'); ?> / <?= val($pre_anestesi, 'pernapasan'); ?> / <?= val($pre_anestesi, 'suhu'); ?>
                    </td>
                </tr>
                <tr><td style="padding:2px 0;color:#888;">TB / BB / IO2</td>
                    <td style="padding:2px 0;font-weight:600;">
                        <?= val($pre_anestesi, 'tb'); ?> / <?= val($pre_anestesi, 'bb'); ?> / <?= val($pre_anestesi, 'io2'); ?>
                    </td>
                </tr>
                <tr><td style="padding:2px 0;color:#888;">ASA</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_anestesi, 'asa'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Rencana Anestesi</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_anestesi, 'rencana_anestesi'); ?></td></tr>
                <?php if(!empty($pre_anestesi['puasa']) && $pre_anestesi['puasa'] != '0000-00-00 00:00:00'): ?>
                <tr><td style="padding:2px 0;color:#888;">Puasa</td><td style="padding:2px 0;font-weight:600;"><?= date('d/m/Y H:i', strtotime($pre_anestesi['puasa'])); ?></td></tr>
                <?php endif; ?>
                <?php if(!empty($pre_anestesi['catatan_khusus'])): ?>
                <tr><td style="padding:2px 0;color:#888;">Catatan Khusus</td><td style="padding:2px 0;font-weight:600;color:#e91e63;"><?= val($pre_anestesi, 'catatan_khusus'); ?></td></tr>
                <?php endif; ?>
                <tr><td style="padding:2px 0;color:#888;">Alergi Obat</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_anestesi, 'riwayat_penyakit_alergiobat'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Dokter</td><td style="padding:2px 0;font-weight:600;"><?= getNamaDokter(val($pre_anestesi, 'kd_dokter')); ?></td></tr>
            </table>
        </div>
        <?php endif; ?>

        <!-- ========== 2. PENILAIAN PRE OPERASI ========== -->
        <?php if($pre_operasi): ?>
        <div style="flex: 1; min-width: 320px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #9c27b0; padding: 12px;">
            <div style="font-size: 12px; font-weight: 700; color: #9c27b0; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">content_paste</i>
                PENILAIAN PRE OPERASI
                <span style="float: right; font-weight: 400; color: #999; font-size: 10px;"><?= date('H:i', strtotime($pre_operasi['tanggal'])); ?></span>
            </div>
            <table style="width: 100%; font-size: 11px; color: #555;">
                <tr><td style="width:35%;padding:2px 0;color:#888;">Ringkasan Klinik</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_operasi, 'ringkasan_klinik'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Pem. Fisik</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_operasi, 'pemeriksaan_fisik'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Pem. Diagnostik</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_operasi, 'pemeriksaan_diagnostik'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Diagnosa Pre Op</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_operasi, 'diagnosa_pre_operasi'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Rencana Tindakan</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_operasi, 'rencana_tindakan_bedah'); ?></td></tr>
                <?php if(!empty($pre_operasi['hal_hal_yang_perludi_persiapkan'])): ?>
                <tr><td style="padding:2px 0;color:#888;">Persiapan</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_operasi, 'hal_hal_yang_perludi_persiapkan'); ?></td></tr>
                <?php endif; ?>
                <?php if(!empty($pre_operasi['terapi_pre_operasi'])): ?>
                <tr><td style="padding:2px 0;color:#888;">Terapi Pre Op</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_operasi, 'terapi_pre_operasi'); ?></td></tr>
                <?php endif; ?>
                <tr><td style="padding:2px 0;color:#888;">Dokter</td><td style="padding:2px 0;font-weight:600;"><?= getNamaDokter(val($pre_operasi, 'kd_dokter')); ?></td></tr>
            </table>
        </div>
        <?php endif; ?>

        <!-- ========== 3. PENILAIAN PRE INDUKSI ========== -->
        <?php if($pre_induksi): ?>
        <div style="flex: 1; min-width: 320px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #2196f3; padding: 12px;">
            <div style="font-size: 12px; font-weight: 700; color: #2196f3; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">monitor_heart</i>
                PENILAIAN PRE INDUKSI
                <span style="float: right; font-weight: 400; color: #999; font-size: 10px;"><?= date('H:i', strtotime($pre_induksi['tanggal'])); ?></span>
            </div>
            <table style="width: 100%; font-size: 11px; color: #555;">
                <tr><td style="width:35%;padding:2px 0;color:#888;">Tensi / Nadi / RR / Suhu</td>
                    <td style="padding:2px 0;font-weight:600;">
                        <?= val($pre_induksi, 'tensi'); ?> / <?= val($pre_induksi, 'nadi'); ?> / <?= val($pre_induksi, 'rr'); ?> / <?= val($pre_induksi, 'suhu'); ?>
                    </td>
                </tr>
                <tr><td style="padding:2px 0;color:#888;">EKG</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_induksi, 'ekg'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Asesmen</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_induksi, 'asesmen'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Perencanaan</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_induksi, 'perencanaan'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Posisi</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_induksi, 'posisi'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Premedikasi</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_induksi, 'premedikasi'); ?> <?= !empty($pre_induksi['premedikasi_keterangan']) ? '('.$pre_induksi['premedikasi_keterangan'].')' : ''; ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Induksi</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_induksi, 'induksi'); ?> <?= !empty($pre_induksi['induksi_keterangan']) ? '('.$pre_induksi['induksi_keterangan'].')' : ''; ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">CVC</td><td style="padding:2px 0;font-weight:600;"><?= val($pre_induksi, 'cvc'); ?></td></tr>
                <tr><td style="padding:2px 0;color:#888;">Dokter</td><td style="padding:2px 0;font-weight:600;"><?= getNamaDokter(val($pre_induksi, 'kd_dokter')); ?></td></tr>
            </table>
        </div>
        <?php endif; ?>

        <!-- ========== 4. CHECKLIST PRE OPERASI ========== -->
        <?php if($checklist_pre_op): ?>
        <div style="flex: 1; min-width: 320px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #ff9800; padding: 12px;">
            <div style="font-size: 12px; font-weight: 700; color: #ff9800; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">checklist</i>
                CHECKLIST PRE OPERASI
                <span style="float: right; font-weight: 400; color: #999; font-size: 10px;"><?= date('H:i', strtotime($checklist_pre_op['tanggal'])); ?></span>
            </div>
            <?php
            $checklist_items = [
                'identitas' => 'Identitas',
                'surat_ijin_bedah' => 'Surat Ijin Bedah',
                'surat_ijin_anestesi' => 'Surat Ijin Anestesi',
                'surat_ijin_transfusi' => 'Surat Ijin Transfusi',
                'penandaan_area_operasi' => 'Penandaan Area',
                'keadaan_umum' => 'Keadaan Umum',
                'persiapan_darah' => 'Persiapan Darah',
                'perlengkapan_khusus' => 'Perlengkapan Khusus',
            ];
            ?>
            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                <?php foreach($checklist_items as $col => $label): 
                    $v = $checklist_pre_op[$col] ?? '';
                    if($col == 'identitas') {
                        $ok = ($v == 'Ya');
                    } elseif($col == 'keadaan_umum') {
                        $ok = !empty($v);
                    } else {
                        $ok = ($v == 'Ada');
                    }
                ?>
                <span style="background: <?= $ok ? '#e8f5e9' : '#fce4ec'; ?>; color: <?= $ok ? '#2e7d32' : '#c62828'; ?>; padding: 2px 6px; border-radius: 6px; font-size: 9px; font-weight: 600; display: inline-flex; align-items: center; gap: 2px;">
                    <i class="material-icons" style="font-size: 10px;"><?= $ok ? 'check' : 'close'; ?></i>
                    <?= $label; ?><?= ($col == 'keadaan_umum' && !empty($v)) ? ': '.$v : ''; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php
            // Penunjang
            $penunjang_items = ['pemeriksaan_penunjang_rontgen','pemeriksaan_penunjang_ekg','pemeriksaan_penunjang_usg','pemeriksaan_penunjang_ctscan','pemeriksaan_penunjang_mri'];
            $penunjang_labels = ['Rontgen','EKG','USG','CT-Scan','MRI'];
            $penunjang_ada = [];
            foreach($penunjang_items as $i => $col) {
                if(!empty($checklist_pre_op[$col]) && $checklist_pre_op[$col] == 'Ada') {
                    $penunjang_ada[] = $penunjang_labels[$i];
                }
            }
            if(!empty($penunjang_ada)):
            ?>
            <div style="margin-top: 6px; font-size: 10px; color: #666;">
                <strong>Penunjang:</strong> <?= implode(', ', $penunjang_ada); ?>
            </div>
            <?php endif; ?>
            <div style="margin-top: 6px; font-size: 10px; color: #888;">
                Dokter Bedah: <strong><?= getNamaDokter(val($checklist_pre_op, 'kd_dokter_bedah')); ?></strong>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== 5. SIGN IN SEBELUM ANESTESI ========== -->
        <?php if($signin): ?>
        <div style="flex: 1; min-width: 320px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #4caf50; padding: 12px;">
            <div style="font-size: 12px; font-weight: 700; color: #4caf50; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">how_to_reg</i>
                SIGN IN SEBELUM ANESTESI
                <span style="float: right; font-weight: 400; color: #999; font-size: 10px;"><?= date('H:i', strtotime($signin['tanggal'])); ?></span>
            </div>
            <?php
            $signin_items = [
                'identitas' => 'Identitas Pasien',
                'penandaan_area_operasi' => 'Penandaan Area',
                'resiko_aspirasi' => 'Resiko Aspirasi',
                'resiko_kehilangan_darah' => 'Resiko Kehilangan Darah',
            ];
            ?>
            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px;">
                <?php foreach($signin_items as $col => $label):
                    $v = $signin[$col] ?? '';
                    if($col == 'identitas') {
                        $ok = ($v == 'Ya');
                    } elseif($col == 'resiko_aspirasi' || $col == 'resiko_kehilangan_darah') {
                        $ok = ($v == 'Ada' || $v == 'Tidak Ada');
                    } else {
                        $ok = ($v == 'Ada');
                    }
                ?>
                <span style="background: <?= $ok ? '#e8f5e9' : '#fce4ec'; ?>; color: <?= $ok ? '#2e7d32' : '#c62828'; ?>; padding: 2px 6px; border-radius: 6px; font-size: 9px; font-weight: 600; display: inline-flex; align-items: center; gap: 2px;">
                    <i class="material-icons" style="font-size: 10px;"><?= $ok ? 'check' : 'close'; ?></i>
                    <?= $label; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php if(!empty($signin['alergi'])): ?>
            <div style="font-size: 10px; color: #666;">
                <strong>Alergi:</strong> <?= htmlspecialchars($signin['alergi']); ?>
            </div>
            <?php endif; ?>
            <?php if(!empty($signin['kesiapan_alat_obat_anestesi']) && $signin['kesiapan_alat_obat_anestesi'] != 'Lengkap'): ?>
            <div style="font-size: 10px; color: #e65100; font-weight: 600;">
                <i class="material-icons" style="font-size: 10px; vertical-align: middle;">warning</i>
                Kesiapan Alat/Obat: <?= htmlspecialchars($signin['kesiapan_alat_obat_anestesi']); ?>
            </div>
            <?php endif; ?>
            <div style="margin-top: 4px; font-size: 10px; color: #888;">
                Dokter Bedah: <strong><?= getNamaDokter(val($signin, 'kd_dokter_bedah')); ?></strong> |
                Anestesi: <strong><?= getNamaDokter(val($signin, 'kd_dokter_anestesi')); ?></strong>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========== 6. CHECKLIST KESIAPAN ANESTESI ========== -->
        <?php if($kesiapan_anestesi): ?>
        <div style="flex: 1; min-width: 320px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #00bcd4; padding: 12px;">
            <div style="font-size: 12px; font-weight: 700; color: #00bcd4; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">playlist_add_check</i>
                CHECKLIST KESIAPAN ANESTESI
                <span style="float: right; font-weight: 400; color: #999; font-size: 10px;"><?= date('H:i', strtotime($kesiapan_anestesi['tanggal'])); ?></span>
            </div>
            <?php
            // Group: Listrik, Gas Medis, Mesin Anestesi, Jalan Napas, Obat-obatan
            $groups = [
                'Listrik' => ['listrik1','listrik2','listrik3','listrik4'],
                'Gas Medis' => ['gasmedis1','gasmedis2','gasmedis3','gasmedis4','gasmedis5','gasmedis6'],
                'Mesin Anes.' => ['mesinanes1','mesinanes2','mesinanes3','mesinanes4','mesinanes5'],
                'Jalan Napas' => ['jalannapas1','jalannapas2','jalannapas3','jalannapas4','jalannapas5','jalannapas6','jalannapas7','jalannapas8','jalannapas9'],
                'Obat' => ['obatobat1','obatobat2','obatobat3','obatobat4','obatobat5','obatobat6'],
            ];
            ?>
            <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                <?php foreach($groups as $group_label => $cols):
                    $total = count($cols);
                    $ya_count = 0;
                    foreach($cols as $c) {
                        if(isset($kesiapan_anestesi[$c]) && $kesiapan_anestesi[$c] == 'Ya') $ya_count++;
                    }
                    $all_ok = ($ya_count == $total);
                ?>
                <span style="background: <?= $all_ok ? '#e8f5e9' : '#fff3e0'; ?>; color: <?= $all_ok ? '#2e7d32' : '#e65100'; ?>; padding: 2px 6px; border-radius: 6px; font-size: 9px; font-weight: 600;">
                    <i class="material-icons" style="font-size: 10px; vertical-align: middle;"><?= $all_ok ? 'check' : 'warning'; ?></i>
                    <?= $group_label; ?> <?= $ya_count; ?>/<?= $total; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php if(!empty($kesiapan_anestesi['tindakan'])): ?>
            <div style="margin-top: 6px; font-size: 10px; color: #666;">
                <strong>Tindakan:</strong> <?= htmlspecialchars($kesiapan_anestesi['tindakan']); ?>
            </div>
            <?php endif; ?>
            <?php if(!empty($kesiapan_anestesi['teknik_anestesi'])): ?>
            <div style="font-size: 10px; color: #666;">
                <strong>Teknik:</strong> <?= htmlspecialchars($kesiapan_anestesi['teknik_anestesi']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ========== 7. TIMEOUT SEBELUM INSISI ========== -->
        <?php if($timeout): ?>
        <div style="flex: 1; min-width: 320px; background: #f8f9fa; border-radius: 8px; border-left: 3px solid #f44336; padding: 12px;">
            <div style="font-size: 12px; font-weight: 700; color: #f44336; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 6px;">
                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">timer</i>
                TIME OUT SEBELUM INSISI
                <span style="float: right; font-weight: 400; color: #999; font-size: 10px;"><?= date('H:i', strtotime($timeout['tanggal'])); ?></span>
            </div>
            <?php
            $to_items = [
                'verbal_identitas' => 'Identitas',
                'verbal_tindakan' => 'Tindakan',
                'verbal_area_insisi' => 'Area Insisi',
                'antibiotik_profilaks' => 'Antibiotik Profilaks',
            ];
            ?>
            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px;">
                <?php foreach($to_items as $col => $label):
                    $v = $timeout[$col] ?? '';
                    $ok = ($v == 'Ya');
                ?>
                <span style="background: <?= $ok ? '#e8f5e9' : '#fce4ec'; ?>; color: <?= $ok ? '#2e7d32' : '#c62828'; ?>; padding: 2px 6px; border-radius: 6px; font-size: 9px; font-weight: 600; display: inline-flex; align-items: center; gap: 2px;">
                    <i class="material-icons" style="font-size: 10px;"><?= $ok ? 'check' : 'close'; ?></i>
                    <?= $label; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <table style="width: 100%; font-size: 10px; color: #555;">
                <?php if(!empty($timeout['penandaan_area_operasi'])): ?>
                <tr><td style="width:35%;padding:1px 0;color:#888;">Penandaan Area</td><td style="padding:1px 0;font-weight:600;"><?= val($timeout, 'penandaan_area_operasi'); ?></td></tr>
                <?php endif; ?>
                <?php if(!empty($timeout['lama_operasi']) && $timeout['lama_operasi'] != ''): ?>
                <tr><td style="padding:1px 0;color:#888;">Lama Operasi</td><td style="padding:1px 0;font-weight:600;"><?= val($timeout, 'lama_operasi'); ?> menit</td></tr>
                <?php endif; ?>
                <?php if(!empty($timeout['antibiotik_profilaks']) && $timeout['antibiotik_profilaks'] == 'Ya'): ?>
                <tr><td style="padding:1px 0;color:#888;">Antibiotik</td><td style="padding:1px 0;font-weight:600;"><?= val($timeout, 'nama_antibiotik'); ?> (<?= val($timeout, 'jam_pemberian'); ?>)</td></tr>
                <?php endif; ?>
                <?php if(!empty($timeout['antisipasi_kehilangan_darah'])): ?>
                <tr><td style="padding:1px 0;color:#888;">Antisipasi Pendarahan</td><td style="padding:1px 0;font-weight:600;"><?= val($timeout, 'antisipasi_kehilangan_darah'); ?></td></tr>
                <?php endif; ?>
            </table>
            <?php
            // Penayangan
            $penayangan = [];
            if(!empty($timeout['penayangan_radiologi']) && $timeout['penayangan_radiologi'] != 'Tidak Diperlukan') $penayangan[] = 'Radiologi: '.$timeout['penayangan_radiologi'];
            if(!empty($timeout['penayangan_ctscan']) && $timeout['penayangan_ctscan'] != 'Tidak Diperlukan') $penayangan[] = 'CT-Scan: '.$timeout['penayangan_ctscan'];
            if(!empty($timeout['penayangan_mri']) && $timeout['penayangan_mri'] != 'Tidak Diperlukan') $penayangan[] = 'MRI: '.$timeout['penayangan_mri'];
            if(!empty($penayangan)):
            ?>
            <div style="margin-top: 4px; font-size: 10px; color: #666;">
                <strong>Penayangan:</strong> <?= implode(' | ', $penayangan); ?>
            </div>
            <?php endif; ?>
            <div style="margin-top: 4px; font-size: 10px; color: #888;">
                Dokter Bedah: <strong><?= getNamaDokter(val($timeout, 'kd_dokter_bedah')); ?></strong> |
                Anestesi: <strong><?= getNamaDokter(val($timeout, 'kd_dokter_anestesi')); ?></strong>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php
    // =============================================
    // 8. BERKAS DIGITAL - PENANDAAN OPERASI (PDF)
    // =============================================
    $kd_berkas = defined('KD_BERKAS_PENANDAAN_OPERASI') ? KD_BERKAS_PENANDAAN_OPERASI : '005';
    $qBerkas = bukaquery_safe("SELECT * FROM berkas_digital_perawatan WHERE no_rawat = '$no_rawat' AND kode = '$kd_berkas'");
    $berkas_list = [];
    if($qBerkas && mysqli_num_rows($qBerkas) > 0) {
        while($rBerkas = mysqli_fetch_array($qBerkas)) {
            $berkas_list[] = $rBerkas;
        }
    }
    if(!empty($berkas_list)):
        $berkas_base_url = defined('BERKAS_DIGITAL_BASE_URL') ? rtrim(BERKAS_DIGITAL_BASE_URL, '/') . '/' : 'http://localhost/webapps/berkasrawat/';
    ?>
    <!-- BERKAS DIGITAL PENANDAAN OPERASI -->
    <div style="padding: 15px; border-top: 1px solid #e0e0e0;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
            <i class="material-icons" style="font-size: 20px; color: #1565c0;">picture_as_pdf</i>
            <span style="font-size: 13px; font-weight: 700; color: #1565c0;">Berkas Digital Perawatan</span>
            <span style="background: #e3f2fd; color: #1565c0; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;"><?= count($berkas_list); ?> file</span>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
            <?php foreach($berkas_list as $berkas):
                $file_url = $berkas_base_url . $berkas['lokasi_file'];
                $file_ext = strtolower(pathinfo($berkas['lokasi_file'], PATHINFO_EXTENSION));
                $is_pdf = ($file_ext === 'pdf');
                $is_image = in_array($file_ext, ['jpg','jpeg','png','gif','webp']);
            ?>
            <div style="flex: 1; min-width: 280px; max-width: 400px; background: #f8f9fa; border-radius: 10px; border: 1px solid #e0e0e0; overflow: hidden;">
                <!-- Preview Area -->
                <div style="background: #fff; height: 220px; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #e0e0e0; position: relative; overflow: hidden;">
                    <?php if($is_pdf): ?>
                    <iframe src="<?= htmlspecialchars($file_url); ?>" style="width: 100%; height: 100%; border: none;"></iframe>
                    <?php elseif($is_image): ?>
                    <img src="<?= htmlspecialchars($file_url); ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;" alt="Berkas">
                    <?php else: ?>
                    <div style="text-align: center; color: #999;">
                        <i class="material-icons" style="font-size: 48px;">insert_drive_file</i>
                        <p style="font-size: 11px; margin-top: 5px;"><?= strtoupper($file_ext); ?> File</p>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Info & Actions -->
                <div style="padding: 10px 12px;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 8px;">
                        <i class="material-icons" style="font-size: 12px; vertical-align: middle;">barcode</i>
                        Kode: <strong><?= htmlspecialchars($berkas['kode']); ?></strong>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <a href="<?= htmlspecialchars($file_url); ?>" target="_blank" 
                           style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px; padding: 8px; background: #4caf50; color: white; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600;">
                            <i class="material-icons" style="font-size: 16px;">visibility</i> Lihat
                        </a>
                        <a href="<?= htmlspecialchars($file_url); ?>" download 
                           style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px; padding: 8px; background: #ff9800; color: white; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600;">
                            <i class="material-icons" style="font-size: 16px;">download</i> Unduh
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
