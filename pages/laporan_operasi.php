<?php
// Cek parameter yang diperlukan
$no_rawat_enc = isset($_GET["rnw"]) ? $_GET["rnw"] : "";
$no_rm_enc = isset($_GET["rm"]) ? $_GET["rm"] : "";

if(empty($no_rawat_enc) || empty($no_rm_enc)) {
    echo "<div class='alert alert-danger'>Parameter tidak lengkap!</div>";
    return;
}

// Decrypt parameter
$no_rawat = encrypt_decrypt(urldecode($no_rawat_enc), "d");
$no_rm = encrypt_decrypt(urldecode($no_rm_enc), "d");

// Ambil kd_dokter dari session
$kd_dokter_enc = isset($_SESSION['ses_dokter']) ? $_SESSION['ses_dokter'] : '';
$kd_dokter = !empty($kd_dokter_enc) ? encrypt_decrypt($kd_dokter_enc, 'd') : '';

// Ambil data pasien
$queryPasien = bukaquery("SELECT 
                            rp.no_rawat,
                            rp.no_rkm_medis,
                            rp.tgl_registrasi,
                            rp.jam_reg,
                            rp.umurdaftar,
                            rp.sttsumur,
                            p.nm_pasien,
                            p.no_ktp,
                            p.jk,
                            p.tmp_lahir,
                            p.tgl_lahir,
                            p.alamat,
                            pj.png_jawab,
                            ki.kd_kamar,
                            b.nm_bangsal
                          FROM reg_periksa rp
                          INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                          LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
                          LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat AND ki.stts_pulang = '-'
                          LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                          LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                          WHERE rp.no_rawat = '$no_rawat'");
$dataPasien = mysqli_fetch_array($queryPasien);

if(!$dataPasien) {
    echo "<div class='alert alert-danger'>Data pasien tidak ditemukan!</div>";
    return;
}

// Ambil data operasi dari tabel operasi (hanya cek eksistensi)
$queryOperasi = bukaquery("SELECT no_rawat FROM operasi WHERE no_rawat = '$no_rawat' LIMIT 1");
$dataOperasi = mysqli_fetch_array($queryOperasi);

if(!$dataOperasi) {
    echo "<div class='alert alert-danger'>Data operasi tidak ditemukan!</div>";
    return;
}

// Cek apakah tabel laporan_operasi ada dan ambil datanya
$tabelLaporanAda = false;
$kolomLaporanAda = [];
$dataLaporan = null;

$cekTabel = bukaquery("SHOW TABLES LIKE 'laporan_operasi'");
if($cekTabel && mysqli_num_rows($cekTabel) > 0) {
    $tabelLaporanAda = true;
    
    // Cek kolom yang ada di tabel
    $cekKolom = bukaquery("SHOW COLUMNS FROM laporan_operasi");
    if($cekKolom) {
        while($kolom = mysqli_fetch_array($cekKolom)) {
            $kolomLaporanAda[] = $kolom['Field'];
        }
    }
    
    // Ambil data laporan operasi (coba match no_rawat, atau no_rawat + tanggal)
    $queryLaporan = bukaquery("SELECT * FROM laporan_operasi WHERE no_rawat = '$no_rawat' ORDER BY tanggal DESC LIMIT 1");
    if($queryLaporan && mysqli_num_rows($queryLaporan) > 0) {
        $dataLaporan = mysqli_fetch_array($queryLaporan);
    }
}

// Format tanggal lahir
$tgl_lahir = date('d/m/Y', strtotime($dataPasien['tgl_lahir']));
$umur = $dataPasien['umurdaftar'] . " " . $dataPasien['sttsumur'];

// Avatar
$avatar_img = ($dataPasien["jk"] == "L") ? "images/male.png" : "images/female.png";


?>

<link rel="stylesheet" href="<?php echo APP_BASE_URL; ?>/css/template4.css">

<style>
.operasi-header {
    background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
    color: white;
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(13, 148, 136, 0.3);
}

.operasi-section {
    background: linear-gradient(135deg, #f0fdfa 0%, #ccfbf1 100%);
    border: 1px solid #99f6e4;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 15px;
}

.operasi-section h3 {
    color: #0d9488;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #14b8a6;
}

.info-operasi-badge {
    background: #f0fdfa;
    border: 1px solid #99f6e4;
    border-radius: 8px;
    padding: 10px 15px;
    margin-bottom: 10px;
}

.info-operasi-badge .label {
    font-size: 11px;
    color: #0d9488;
    font-weight: 600;
}

.info-operasi-badge .value {
    font-size: 13px;
    color: #333;
    font-weight: 600;
}

.form-section {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.form-section h3 {
    color: #0d9488;
    font-size: 14px;
    font-weight: 700;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 2px solid #14b8a6;
    display: flex;
    align-items: center;
    gap: 8px;
}

.form-section h3 .section-number {
    background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}

.btn-simpan-operasi {
    background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(13, 148, 136, 0.3);
}

.btn-simpan-operasi:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(13, 148, 136, 0.4);
}
</style>

<div class="row clearfix">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        
        <!-- Header Pasien -->
        <div class="operasi-header">
            <div class="row">
                <div class="col-sm-8">
                    <div style="display: flex; align-items: center;">
                        <div style="width: 70px; height: 70px; border-radius: 50%; overflow: hidden; margin-right: 15px; border: 3px solid rgba(255,255,255,0.3);">
                            <img src="<?php echo $avatar_img; ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div>
                            <h2 style="margin: 0 0 5px 0; font-size: 22px; font-weight: 700;">
                                <?php echo strtoupper($dataPasien['nm_pasien']); ?>
                            </h2>
                            <div style="font-size: 13px; opacity: 0.9;">
                                <span style="margin-right: 15px;"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">badge</i> No. RM: <?php echo $dataPasien['no_rkm_medis']; ?></span>
                                <span style="margin-right: 15px;"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">cake</i> <?php echo $tgl_lahir; ?> (<?php echo $umur; ?>)</span>
                            </div>
                            <div style="font-size: 12px; opacity: 0.8; margin-top: 5px;">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">folder</i> <?php echo $no_rawat; ?>
                                <?php if($dataPasien['nm_bangsal']): ?>
                                <span style="margin-left: 15px;"><i class="material-icons" style="font-size: 14px; vertical-align: middle;">hotel</i> <?php echo $dataPasien['nm_bangsal']; ?> - <?php echo $dataPasien['kd_kamar']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4 text-right">
                    <?php if($dataLaporan): ?>
                    <span class="badge" style="background: rgba(255,255,255,0.3); color: white; padding: 8px 15px; border-radius: 20px; font-size: 12px;">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</i> Edit Laporan Operasi
                    </span>
                    <?php else: ?>
                    <span class="badge" style="background: rgba(255,200,50,0.4); color: white; padding: 8px 15px; border-radius: 20px; font-size: 12px;">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">add_circle</i> Buat Laporan Operasi
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Form Edit Operasi -->
        <form id="formLaporanOperasi">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            
            <?php if($tabelLaporanAda): ?>
            <!-- I. Diagnosis (Tabel laporan_operasi) -->
            <div class="form-section">
                <h3>
                    <span class="section-number">I</span>
                    DIAGNOSIS
                </h3>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Diagnosa Pre-Operasi</label>
                            <textarea name="diagnosa_preop" id="diagnosa_preop" class="form-control auto-resize" rows="2" placeholder="Masukkan diagnosa pre-operasi..."><?php echo ($dataLaporan && isset($dataLaporan['diagnosa_preop'])) ? htmlspecialchars($dataLaporan['diagnosa_preop']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Diagnosa Post-Operasi</label>
                            <textarea name="diagnosa_postop" id="diagnosa_postop" class="form-control auto-resize" rows="2" placeholder="Masukkan diagnosa post-operasi..."><?php echo ($dataLaporan && isset($dataLaporan['diagnosa_postop'])) ? htmlspecialchars($dataLaporan['diagnosa_postop']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- II. Detail Laporan Operasi -->
            <div class="form-section">
                <h3>
                    <span class="section-number">II</span>
                    DETAIL LAPORAN OPERASI
                </h3>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Tanggal & Jam Mulai</label>
                            <?php
                            // Prioritas: laporan_operasi.tanggal > operasi.tgl_operasi
                            $tgl_mulai_val = '';
                            if($dataLaporan && isset($dataLaporan['tanggal']) && $dataLaporan['tanggal'] && $dataLaporan['tanggal'] != '0000-00-00 00:00:00') {
                                $tgl_mulai_val = date('Y-m-d\TH:i', strtotime($dataLaporan['tanggal']));
                            } elseif($dataOperasi && isset($dataOperasi['tgl_operasi']) && $dataOperasi['tgl_operasi']) {
                                $tgl_mulai_val = date('Y-m-d\TH:i', strtotime($dataOperasi['tgl_operasi']));
                            }
                            ?>
                            <input type="datetime-local" name="tanggal" id="tanggal" class="form-control" 
                                   value="<?php echo $tgl_mulai_val; ?>">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Tanggal & Jam Selesai</label>
                            <input type="datetime-local" name="selesaioperasi" id="selesaioperasi" class="form-control" 
                                   value="<?php echo ($dataLaporan && isset($dataLaporan['selesaioperasi']) && $dataLaporan['selesaioperasi'] && $dataLaporan['selesaioperasi'] != '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($dataLaporan['selesaioperasi'])) : date('Y-m-d\TH:i'); ?>">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Jaringan yang Dieksekusi</label>
                            <input type="text" name="jaringan_dieksekusi" id="jaringan_dieksekusi" class="form-control" placeholder="Masukkan jaringan..."
                                   value="<?php echo ($dataLaporan && isset($dataLaporan['jaringan_dieksekusi'])) ? htmlspecialchars($dataLaporan['jaringan_dieksekusi']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Permintaan PA</label>
                            <select name="permintaan_pa" id="permintaan_pa" class="form-control">
                                <option value="Tidak" <?php echo ($dataLaporan && isset($dataLaporan['permintaan_pa']) && $dataLaporan['permintaan_pa'] == 'Tidak') ? 'selected' : ''; ?>>Tidak</option>
                                <option value="Ya" <?php echo ($dataLaporan && isset($dataLaporan['permintaan_pa']) && $dataLaporan['permintaan_pa'] == 'Ya') ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                    </div>
                    <?php if(in_array('nomor_implan', $kolomLaporanAda)): ?>
                    <div class="col-sm-8">
                        <div class="form-group">
                            <label>Nomor Implan</label>
                            <input type="text" name="nomor_implan" id="nomor_implan" class="form-control" placeholder="Masukkan nomor implan (jika ada)..."
                                   value="<?php echo ($dataLaporan && isset($dataLaporan['nomor_implan'])) ? htmlspecialchars($dataLaporan['nomor_implan']) : ''; ?>">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- III. Laporan Operasi -->
            <div class="form-section">
                <h3>
                    <span class="section-number">III</span>
                    LAPORAN JALANNYA OPERASI
                    <button type="button" class="btn btn-sm" onclick="showModalTemplate()" style="margin-left: auto; background: #0d9488; color: white; border: none; border-radius: 15px; padding: 5px 15px; font-size: 11px;">
                        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">description</i> Template Laporan
                    </button>
                </h3>
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            <label>Laporan Operasi</label>
                            <textarea name="laporan_operasi" id="laporan_operasi" class="form-control auto-resize" rows="6" placeholder="Tuliskan laporan jalannya operasi secara detail..."><?php echo ($dataLaporan && isset($dataLaporan['laporan_operasi'])) ? htmlspecialchars($dataLaporan['laporan_operasi']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Peringatan Tabel laporan_operasi Tidak Ada -->
            <div class="alert alert-warning" style="border-radius: 10px; padding: 20px;">
                <h4 style="margin: 0 0 10px 0;"><i class="material-icons" style="vertical-align: middle;">warning</i> Tabel Laporan Operasi Belum Tersedia</h4>
                <p style="margin: 0;">Tabel <strong>laporan_operasi</strong> belum ada di database. Silakan hubungi admin untuk membuat tabel terlebih dahulu.</p>
            </div>
            <?php endif; ?>
            
            <!-- Tombol Aksi -->
            <div class="form-section" style="text-align: center; padding: 25px;">
                <button type="submit" class="btn-simpan-operasi">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">save</i>
                    SIMPAN PERUBAHAN
                </button>
                
                <a href="index.php?act=Operasi" class="btn btn-default" style="margin-left: 15px; border-radius: 25px; padding: 12px 30px;">
                    <i class="material-icons" style="font-size: 18px; vertical-align: middle;">arrow_back</i>
                    KEMBALI
                </a>
            </div>
        </form>
        
    </div>
</div>

<!-- Modal Template Laporan Operasi -->
<div class="modal fade" id="modalTemplateLaporan" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: 10px; overflow: hidden;" id="modalTemplateContent">
            <div style="text-align: center; padding: 50px;">
                <i class="material-icons" style="font-size: 48px; color: #7c3aed;">hourglass_empty</i>
                <p style="margin-top: 15px; color: #666;">Memuat template...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Show modal template - load dari template_operasi.php
function showModalTemplate() {
    $('#modalTemplateLaporan').modal('show');
    $('#modalTemplateContent').load('pages/template_operasi.php');
}

// Pilih template - dipanggil dari template_operasi.php
function pilihTemplate(templateText) {
    const textarea = document.getElementById('laporan_operasi');
    
    if (textarea.value.trim() !== '') {
        Swal.fire({
            title: 'Ganti Laporan?',
            text: 'Laporan yang sudah ada akan diganti dengan template. Lanjutkan?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, Ganti',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#0d9488'
        }).then((result) => {
            if (result.isConfirmed) {
                textarea.value = templateText;
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
                $('#modalTemplateLaporan').modal('hide');
            }
        });
    } else {
        textarea.value = templateText;
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
        $('#modalTemplateLaporan').modal('hide');
    }
}

// Auto-resize textarea
document.querySelectorAll('.auto-resize').forEach(textarea => {
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
    // Trigger on load
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
});

// Handle form submit
document.getElementById('formLaporanOperasi')?.addEventListener('submit', function(e) {
    e.preventDefault();
    simpanData();
});

// Keyboard shortcut Ctrl+S untuk simpan
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('formLaporanOperasi').dispatchEvent(new Event('submit'));
    }
});

// Fungsi simpan data
async function simpanData() {
    const form = document.getElementById('formLaporanOperasi');
    const formData = new FormData(form);
    
    // Convert datetime-local to MySQL format
    let tanggal = formData.get('tanggal');
    if (tanggal) {
        tanggal = tanggal.replace('T', ' ') + ':00';
    }
    
    let selesaioperasi = formData.get('selesaioperasi');
    if (selesaioperasi) {
        selesaioperasi = selesaioperasi.replace('T', ' ') + ':00';
    }
    
    // Siapkan data
    const data = {
        aksi: 'simpan_laporan_operasi',
        no_rawat: formData.get('no_rawat'),
        // Tabel laporan_operasi
        tanggal: tanggal || '',
        diagnosa_preop: formData.get('diagnosa_preop') || '',
        diagnosa_postop: formData.get('diagnosa_postop') || '',
        jaringan_dieksekusi: formData.get('jaringan_dieksekusi') || '',
        selesaioperasi: selesaioperasi || '',
        permintaan_pa: formData.get('permintaan_pa') || 'Tidak',
        nomor_implan: formData.get('nomor_implan') || '',
        laporan_operasi: formData.get('laporan_operasi') || ''
    };
    
    // Loading
    Swal.fire({
        title: 'Menyimpan...',
        text: 'Mohon tunggu',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    try {
        const response = await fetch('pages/proses3.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });
        
        const result = await response.json();
        
        if (result.status === 'success') {
            Swal.fire({
                title: 'Berhasil!',
                text: result.message,
                icon: 'success',
                confirmButtonColor: '#0d9488'
            }).then(() => {
                // Reload konten laporan operasi via AJAX (bukan reload halaman utama)
                const container = document.getElementById('formLaporanOperasi')?.closest('.col-lg-12')?.closest('.row')?.parentElement;
                if (container) {
                window.location.href = 'index.php?act=Laporanoperasi&rnw=<?php echo urlencode($no_rawat_enc); ?>&rm=<?php echo urlencode($no_rm_enc); ?>';
                } else {
                    window.location.reload();
                }
            });
        } else {
            Swal.fire({
                title: 'Gagal!',
                text: result.message,
                icon: 'error',
                confirmButtonColor: '#dc2626'
            });
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'Terjadi kesalahan sistem',
            icon: 'error',
            confirmButtonColor: '#dc2626'
        });
    }
}
</script>