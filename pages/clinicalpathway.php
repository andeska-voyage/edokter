<?php
/**
 * clinicalpathway.php
 * Clinical Pathway - Timeline Perawatan Pasien
 * Format per hari (H0, H1, H2, ...) seperti detail_inap.php
 * 
 * @author eDokter Team
 * @version 3.3 - Layout 2 kolom (Detail + Ringkasan)
 */

if(session_status() == PHP_SESSION_NONE) session_start();

function cpTableExists($tableName) {
    return isset($GLOBALS['existing_tables']) && in_array($tableName, $GLOBALS['existing_tables']);
}

function cpSafeCount($tableName, $no_rawat, $dateColumn, $dateValue, $useDateFunction = false) {
    if(!cpTableExists($tableName)) return 0;
    $query = $useDateFunction 
        ? bukaquery("SELECT COUNT(*) as jumlah FROM $tableName WHERE no_rawat = '$no_rawat' AND DATE($dateColumn) = '$dateValue'")
        : bukaquery("SELECT COUNT(*) as jumlah FROM $tableName WHERE no_rawat = '$no_rawat' AND $dateColumn = '$dateValue'");
    if($query) { $result = mysqli_fetch_array($query); return (int)$result['jumlah']; }
    return 0;
}

function cpSafeLabRadQuery($tableName, $no_rawat, $dateValue) {
    if(!cpTableExists($tableName)) return ['sudah' => 0, 'belum' => 0, 'total' => 0];
    $sudah = 0; $belum = 0;
    $query = bukaquery("SELECT tgl_sampel FROM $tableName WHERE no_rawat = '$no_rawat' AND tgl_permintaan = '$dateValue'");
    if($query) { while($row = mysqli_fetch_array($query)) { if($row['tgl_sampel'] == '0000-00-00' || empty($row['tgl_sampel'])) $belum++; else $sudah++; } }
    return ['sudah' => $sudah, 'belum' => $belum, 'total' => $sudah + $belum];
}

function cpSafeResepQuery($no_rawat, $dateValue) {
    if(!cpTableExists('resep_obat')) return ['sudah' => 0, 'belum' => 0, 'total' => 0];
    $sudah = 0; $belum = 0;
    $query = bukaquery("SELECT tgl_perawatan FROM resep_obat WHERE no_rawat = '$no_rawat' AND tgl_peresepan = '$dateValue'");
    if($query) { while($row = mysqli_fetch_array($query)) { if($row['tgl_perawatan'] == '0000-00-00' || empty($row['tgl_perawatan'])) $belum++; else $sudah++; } }
    return ['sudah' => $sudah, 'belum' => $belum, 'total' => $sudah + $belum];
}

function cpGetTindakanDokter($no_rawat, $tgl) {
    if(!cpTableExists('rawat_inap_dr')) return ['data' => [], 'total_biaya' => 0];
    $data = []; $total = 0;
    $query = bukaquery("SELECT jp.nm_perawatan, COUNT(*) as jumlah, SUM(rid.biaya_rawat) as total_biaya,
        GROUP_CONCAT(DISTINCT d.nm_dokter SEPARATOR ', ') as dokter_list
        FROM rawat_inap_dr rid LEFT JOIN dokter d ON rid.kd_dokter = d.kd_dokter
        LEFT JOIN jns_perawatan_inap jp ON rid.kd_jenis_prw = jp.kd_jenis_prw
        WHERE rid.no_rawat = '$no_rawat' AND rid.tgl_perawatan = '$tgl'
        GROUP BY rid.kd_jenis_prw, jp.nm_perawatan ORDER BY jumlah DESC");
    if($query) { while($row = mysqli_fetch_assoc($query)) { $data[] = $row; $total += $row['total_biaya']; } }
    return ['data' => $data, 'total_biaya' => $total];
}

function cpGetTindakanPerawat($no_rawat, $tgl) {
    if(!cpTableExists('rawat_inap_pr')) return ['data' => [], 'total_biaya' => 0];
    $data = []; $total = 0;
    $query = bukaquery("SELECT jp.nm_perawatan, COUNT(*) as jumlah, SUM(rip.biaya_rawat) as total_biaya,
        GROUP_CONCAT(DISTINCT pt.nama SEPARATOR ', ') as perawat_list
        FROM rawat_inap_pr rip LEFT JOIN petugas pt ON rip.nip = pt.nip
        LEFT JOIN jns_perawatan_inap jp ON rip.kd_jenis_prw = jp.kd_jenis_prw
        WHERE rip.no_rawat = '$no_rawat' AND rip.tgl_perawatan = '$tgl'
        GROUP BY rip.kd_jenis_prw, jp.nm_perawatan ORDER BY jumlah DESC");
    if($query) { while($row = mysqli_fetch_assoc($query)) { $data[] = $row; $total += $row['total_biaya']; } }
    return ['data' => $data, 'total_biaya' => $total];
}

function cpGetTindakanDrPr($no_rawat, $tgl) {
    if(!cpTableExists('rawat_inap_drpr')) return ['data' => [], 'total_biaya' => 0];
    $data = []; $total = 0;
    $query = bukaquery("SELECT jp.nm_perawatan, COUNT(*) as jumlah, SUM(ridp.biaya_rawat) as total_biaya,
        GROUP_CONCAT(DISTINCT d.nm_dokter SEPARATOR ', ') as dokter_list,
        GROUP_CONCAT(DISTINCT pt.nama SEPARATOR ', ') as perawat_list
        FROM rawat_inap_drpr ridp LEFT JOIN dokter d ON ridp.kd_dokter = d.kd_dokter
        LEFT JOIN petugas pt ON ridp.nip = pt.nip
        LEFT JOIN jns_perawatan_inap jp ON ridp.kd_jenis_prw = jp.kd_jenis_prw
        WHERE ridp.no_rawat = '$no_rawat' AND ridp.tgl_perawatan = '$tgl'
        GROUP BY ridp.kd_jenis_prw, jp.nm_perawatan ORDER BY jumlah DESC");
    if($query) { while($row = mysqli_fetch_assoc($query)) { $data[] = $row; $total += $row['total_biaya']; } }
    return ['data' => $data, 'total_biaya' => $total];
}

// Biaya Lab PK - dari detail_periksa_lab
// Jika jns_perawatan_lab.total_byr > 0 pakai itu (lab paket)
// Jika 0, SUM dari template_laboratorium.biaya_item
function cpGetBiayaLabPK($no_rawat, $tgl) {
    if(!cpTableExists('detail_periksa_lab')) return 0;
    $total = 0;
    
    // Ambil semua permintaan lab pada tanggal tersebut
    $query = bukaquery("SELECT DISTINCT dpl.kd_jenis_prw, jpl.total_byr
        FROM detail_periksa_lab dpl
        LEFT JOIN jns_perawatan_lab jpl ON dpl.kd_jenis_prw = jpl.kd_jenis_prw
        WHERE dpl.no_rawat = '$no_rawat' AND dpl.tgl_periksa = '$tgl'");
    
    if($query) {
        while($row = mysqli_fetch_assoc($query)) {
            if($row['total_byr'] > 0) {
                // Lab paket - pakai total_byr
                $total += $row['total_byr'];
            } else {
                // Lab satuan - SUM biaya_item dari template
                $qItem = bukaquery("SELECT SUM(tl.biaya_item) as total_item
                    FROM detail_periksa_lab dpl
                    LEFT JOIN template_laboratorium tl ON dpl.id_template = tl.id_template
                    WHERE dpl.no_rawat = '$no_rawat' AND dpl.tgl_periksa = '$tgl' AND dpl.kd_jenis_prw = '{$row['kd_jenis_prw']}'");
                if($qItem) {
                    $rItem = mysqli_fetch_assoc($qItem);
                    $total += (float)$rItem['total_item'];
                }
            }
        }
    }
    return $total;
}

// Biaya Lab PA - dari detail_periksa_labpa
// Langsung pakai jns_perawatan_lab.total_byr
function cpGetBiayaLabPA($no_rawat, $tgl) {
    if(!cpTableExists('detail_periksa_labpa')) return 0;
    $total = 0;
    
    $query = bukaquery("SELECT DISTINCT dpl.kd_jenis_prw, jpl.total_byr
        FROM detail_periksa_labpa dpl
        LEFT JOIN jns_perawatan_lab jpl ON dpl.kd_jenis_prw = jpl.kd_jenis_prw
        WHERE dpl.no_rawat = '$no_rawat' AND dpl.tgl_periksa = '$tgl'");
    
    if($query) {
        while($row = mysqli_fetch_assoc($query)) {
            $total += (float)$row['total_byr'];
        }
    }
    return $total;
}

// Biaya Radiologi - dari periksa_radiologi.biaya
function cpGetBiayaRadiologi($no_rawat, $tgl) {
    if(!cpTableExists('periksa_radiologi')) return 0;
    
    $query = bukaquery("SELECT SUM(biaya) as total FROM periksa_radiologi WHERE no_rawat = '$no_rawat' AND tgl_periksa = '$tgl'");
    if($query) {
        $row = mysqli_fetch_assoc($query);
        return (float)$row['total'];
    }
    return 0;
}

// Biaya Obat & BHP - dari detail_pemberian_obat.total
function cpGetBiayaObat($no_rawat, $tgl) {
    if(!cpTableExists('detail_pemberian_obat')) return 0;
    
    $query = bukaquery("SELECT SUM(total) as total FROM detail_pemberian_obat WHERE no_rawat = '$no_rawat' AND tgl_perawatan = '$tgl'");
    if($query) {
        $row = mysqli_fetch_assoc($query);
        return (float)$row['total'];
    }
    return 0;
}

// Biaya Operasi - dari tabel operasi (sum semua kolom biaya)
function cpGetBiayaOperasi($no_rawat, $tgl) {
    if(!cpTableExists('operasi')) return 0;
    
    $query = bukaquery("SELECT SUM(
        IFNULL(biayaoperator1, 0) + IFNULL(biayaoperator2, 0) + IFNULL(biayaoperator3, 0) +
        IFNULL(biayaasisten_operator1, 0) + IFNULL(biayaasisten_operator2, 0) + IFNULL(biayaasisten_operator3, 0) +
        IFNULL(biayainstrumen, 0) + IFNULL(biayadokter_anak, 0) + IFNULL(biayaperawaat_resusitas, 0) +
        IFNULL(biayadokter_anestesi, 0) + IFNULL(biayaasisten_anestesi, 0) + IFNULL(biayaasisten_anestesi2, 0) +
        IFNULL(biayabidan, 0) + IFNULL(biayabidan2, 0) + IFNULL(biayabidan3, 0) +
        IFNULL(biayaperawat_luar, 0) + IFNULL(biayaalat, 0) + IFNULL(biayasewaok, 0) +
        IFNULL(akomodasi, 0) + IFNULL(bagian_rs, 0) +
        IFNULL(biaya_omloop, 0) + IFNULL(biaya_omloop2, 0) + IFNULL(biaya_omloop3, 0) +
        IFNULL(biaya_omloop4, 0) + IFNULL(biaya_omloop5, 0) +
        IFNULL(biayasarpras, 0) + IFNULL(biaya_dokter_pjanak, 0) + IFNULL(biaya_dokter_umum, 0)
    ) as total FROM operasi WHERE no_rawat = '$no_rawat' AND DATE(tgl_operasi) = '$tgl'");
    
    if($query) {
        $row = mysqli_fetch_assoc($query);
        return (float)$row['total'];
    }
    return 0;
}

// Biaya Kamar - dari tabel kamar_inap.trf_kamar (per hari)
// Hitung semua kamar yang ditempati pada tanggal tertentu (bisa lebih dari 1 jika pindah kamar)
function cpGetBiayaKamar($no_rawat, $tgl) {
    if(!cpTableExists('kamar_inap')) return 0;
    
    $total = 0;
    
    // Ambil semua record kamar pasien
    $query = bukaquery("SELECT trf_kamar, DATE(tgl_masuk) as tgl_masuk, DATE(tgl_keluar) as tgl_keluar, stts_pulang
                        FROM kamar_inap 
                        WHERE no_rawat = '$no_rawat'
                        ORDER BY tgl_masuk ASC");
    
    if($query && mysqli_num_rows($query) > 0) {
        while($row = mysqli_fetch_assoc($query)) {
            $tgl_masuk_kamar = $row['tgl_masuk'];
            $tgl_keluar_kamar = $row['tgl_keluar'];
            $stts_pulang = $row['stts_pulang'];
            
            // Kamar ditempati pada hari $tgl jika:
            // tgl_masuk <= $tgl DAN (stts_pulang='-' ATAU tgl_keluar >= $tgl)
            
            if($tgl_masuk_kamar <= $tgl) {
                // Kamar masih aktif
                if($stts_pulang == '-' || $tgl_keluar_kamar == '0000-00-00' || empty($tgl_keluar_kamar)) {
                    $total += (float)$row['trf_kamar'];
                }
                // Kamar sudah pindah tapi hari ini masih termasuk (tgl <= tgl_keluar)
                elseif($tgl <= $tgl_keluar_kamar) {
                    $total += (float)$row['trf_kamar'];
                }
            }
        }
    }
    
    return $total;
}

// Info Kamar - Semua kamar yang ditempati pada tanggal tertentu (bisa lebih dari 1 jika pindah kamar)
function cpGetInfoKamar($no_rawat, $tgl) {
    if(!cpTableExists('kamar_inap')) return [];
    
    $kamar_list = [];
    
    // Ambil semua record kamar pasien
    $query = bukaquery("SELECT ki.kd_kamar, ki.trf_kamar, b.nm_bangsal, k.kelas,
                               DATE(ki.tgl_masuk) as tgl_masuk, DATE(ki.tgl_keluar) as tgl_keluar, 
                               ki.stts_pulang
                        FROM kamar_inap ki
                        LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                        LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                        WHERE ki.no_rawat = '$no_rawat'
                        ORDER BY ki.tgl_masuk ASC");
    
    if($query && mysqli_num_rows($query) > 0) {
        while($row = mysqli_fetch_assoc($query)) {
            $tgl_masuk_kamar = $row['tgl_masuk'];
            $tgl_keluar_kamar = $row['tgl_keluar'];
            $stts_pulang = $row['stts_pulang'];
            
            // Kamar ditempati pada hari $tgl jika:
            // 1. tgl_masuk <= $tgl DAN (stts_pulang='-' ATAU tgl_keluar >= $tgl)
            // Catatan: Pada hari tgl_keluar, pasien masih dihitung di kamar tersebut (karena lama=3 berarti 28,29,30)
            
            if($tgl_masuk_kamar <= $tgl) {
                // Kamar masih aktif
                if($stts_pulang == '-' || $tgl_keluar_kamar == '0000-00-00' || empty($tgl_keluar_kamar)) {
                    $kamar_list[] = $row;
                }
                // Kamar sudah pindah tapi hari ini masih termasuk (tgl <= tgl_keluar)
                elseif($tgl <= $tgl_keluar_kamar) {
                    $kamar_list[] = $row;
                }
            }
        }
    }
    
    return $kamar_list;
}

// Biaya Obat & BMHP Operasi - dari beri_obat_operasi
function cpGetBiayaObatOperasi($no_rawat, $tgl) {
    $query = @bukaquery("SELECT SUM(hargasatuan * jumlah) as total 
                        FROM beri_obat_operasi 
                        WHERE no_rawat = '$no_rawat' 
                        AND DATE(tanggal) = '$tgl'");
    
    if($query) {
        $row = mysqli_fetch_assoc($query);
        return (float)$row['total'];
    }
    return 0;
}

// Data Lab Hari Ini - Compact dengan status pending jika nilai kosong
function cpGetLabHariIni($no_rawat, $tgl) {
    if(!cpTableExists('detail_periksa_lab') || !cpTableExists('template_laboratorium')) return [];
    
    $data = [];
    $query = bukaquery("SELECT dpl.id_template, dpl.nilai, dpl.keterangan, dpl.jam,
                               tl.Pemeriksaan, tl.satuan
                        FROM detail_periksa_lab dpl
                        LEFT JOIN template_laboratorium tl ON dpl.id_template = tl.id_template
                        WHERE dpl.no_rawat = '$no_rawat' 
                        AND dpl.tgl_periksa = '$tgl'
                        ORDER BY dpl.jam DESC");
    
    if($query) {
        while($row = mysqli_fetch_assoc($query)) {
            // Cek apakah nilai kosong
            $nilai = trim($row['nilai']);
            $keterangan = $row['keterangan'];
            
            if(empty($nilai) || $nilai == '' || $nilai == '-') {
                $status = 'pending'; // Hasil belum keluar
            } elseif(in_array($keterangan, ['H', 'h'])) {
                $status = 'high'; // Tinggi
            } elseif(in_array($keterangan, ['L', 'l'])) {
                $status = 'low'; // Rendah
            } else {
                $status = 'normal'; // Normal
            }
            
            $data[] = [
                'pemeriksaan' => $row['Pemeriksaan'],
                'nilai' => $nilai,
                'satuan' => $row['satuan'],
                'status' => $status,
                'jam' => $row['jam']
            ];
        }
    }
    return $data;
}

// Data Hasil Radiologi Hari Ini
function cpGetHasilRadiologi($no_rawat, $tgl) {
    if(!cpTableExists('hasil_radiologi')) return [];
    
    $data = [];
    $query = bukaquery("SELECT hr.tgl_periksa, hr.jam, hr.hasil,
                               pr.kd_jenis_prw, jpr.nm_perawatan
                        FROM hasil_radiologi hr
                        LEFT JOIN periksa_radiologi pr ON hr.no_rawat = pr.no_rawat 
                            AND hr.tgl_periksa = pr.tgl_periksa AND hr.jam = pr.jam
                        LEFT JOIN jns_perawatan_radiologi jpr ON pr.kd_jenis_prw = jpr.kd_jenis_prw
                        WHERE hr.no_rawat = '$no_rawat' 
                        AND hr.tgl_periksa = '$tgl'
                        ORDER BY hr.jam DESC");
    
    if($query) {
        while($row = mysqli_fetch_assoc($query)) {
            $data[] = [
                'tgl_periksa' => $row['tgl_periksa'],
                'jam' => $row['jam'],
                'nm_perawatan' => $row['nm_perawatan'],
                'hasil' => $row['hasil']
            ];
        }
    }
    return $data;
}

function cpGetObatBHP($no_rawat, $tgl) {
    if(!cpTableExists('detail_pemberian_obat') || !cpTableExists('databarang')) return [];
    $data = [];
    $query = bukaquery("SELECT dpo.kode_brng, db.nama_brng, dpo.jml, dpo.jam, dpo.status
        FROM detail_pemberian_obat dpo LEFT JOIN databarang db ON dpo.kode_brng = db.kode_brng
        WHERE dpo.no_rawat = '$no_rawat' AND dpo.tgl_perawatan = '$tgl'
        ORDER BY dpo.jam DESC, dpo.kode_brng ASC");
    if($query) {
        while($row = mysqli_fetch_assoc($query)) {
            $aturan = 'BMHP';
            if(cpTableExists('aturan_pakai')) {
                $qAturan = bukaquery("SELECT aturan FROM aturan_pakai WHERE no_rawat = '$no_rawat' AND kode_brng = '{$row['kode_brng']}' ORDER BY tgl_perawatan DESC, jam DESC LIMIT 1");
                if($qAturan && mysqli_num_rows($qAturan) > 0) { $rAturan = mysqli_fetch_array($qAturan); $aturan = !empty($rAturan['aturan']) ? $rAturan['aturan'] : 'BMHP'; }
            }
            $row['aturan'] = $aturan;
            $data[] = $row;
        }
    }
    return $data;
}

function cpHitungUmur($tgl_lahir) {
    $lahir = new DateTime($tgl_lahir); $sekarang = new DateTime(); $umur = $sekarang->diff($lahir);
    if($umur->y > 0) return $umur->y . ' th'; elseif($umur->m > 0) return $umur->m . ' bln'; else return $umur->d . ' hr';
}

// VALIDASI
$norawat = ''; $norm = '';
if(isset($_GET['rnw']) && isset($_GET['rm'])){
    $norawat = encrypt_decrypt(urldecode($_GET['rnw']), "d");
    $norm = encrypt_decrypt(urldecode($_GET['rm']), "d");
    $norawat = validTeks4($norawat, 20); $norm = validTeks4($norm, 20);
} else { JSRedirect("index.php?act=Pasien"); exit(); }

$kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"],"d"), 20);
$queryDokter = bukaquery("SELECT kd_sps FROM dokter WHERE kd_dokter = '$kd_dokter'");
$rsDokter = mysqli_fetch_array($queryDokter);
$is_dokter_umum = ($rsDokter && $rsDokter['kd_sps'] == 'UMUM');
if(!$is_dokter_umum) {
    $cek_akses = getOne("SELECT COUNT(*) FROM dpjp_ranap WHERE no_rawat='$norawat' AND kd_dokter='$kd_dokter'");
    if($cek_akses == 0){ echo "<script>alert('Anda tidak memiliki akses!');</script>"; JSRedirect("index.php?act=Pasien"); exit(); }
}

// QUERY PASIEN - Ambil tgl_masuk paling awal dari kamar_inap (termasuk pindah kamar)
$queryPasien = bukaquery("SELECT p.nm_pasien, p.no_ktp, p.jk, p.tmp_lahir, p.tgl_lahir, p.alamat, p.no_tlp, p.gol_darah,
    rp.no_rawat, rp.no_rkm_medis,
    (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = rp.no_rawat) as tgl_masuk,
    (SELECT MIN(TIME(jam_masuk)) FROM kamar_inap WHERE no_rawat = rp.no_rawat AND DATE(tgl_masuk) = (SELECT MIN(DATE(tgl_masuk)) FROM kamar_inap WHERE no_rawat = rp.no_rawat)) as jam_masuk,
    ki.diagnosa_awal, ki.kd_kamar,
    k.kelas, b.nm_bangsal, pj.png_jawab, pj.kd_pj
FROM reg_periksa rp INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
LEFT JOIN kamar_inap ki ON rp.no_rawat = ki.no_rawat AND ki.stts_pulang = '-'
LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
LEFT JOIN penjab pj ON rp.kd_pj = pj.kd_pj
WHERE rp.no_rawat = '$norawat' AND rp.no_rkm_medis = '$norm' LIMIT 1");
$dataPasien = mysqli_fetch_array($queryPasien);
if(!$dataPasien){ echo "<script>alert('Data tidak ditemukan!');</script>"; JSRedirect("index.php?act=Pasien"); exit(); }

$today = date('Y-m-d');
$tgl_masuk = $dataPasien['tgl_masuk'];
$tgl_masuk_dt = new DateTime($tgl_masuk); $today_dt = new DateTime($today);
$hari_rawat = $tgl_masuk_dt->diff($today_dt)->days;

$timeline_dates = [];
for($i = 0; $i <= $hari_rawat; $i++) $timeline_dates[$i] = date('Y-m-d', strtotime($tgl_masuk . " +$i days"));

// DPJP
$dpjp_list = [];
if(cpTableExists('dpjp_ranap')) {
    $qDpjp = bukaquery("SELECT d.nm_dokter, sp.nm_sps FROM dpjp_ranap dp LEFT JOIN dokter d ON dp.kd_dokter = d.kd_dokter LEFT JOIN spesialis sp ON d.kd_sps = sp.kd_sps WHERE dp.no_rawat = '$norawat' ORDER BY d.nm_dokter");
    if($qDpjp) { while($row = mysqli_fetch_assoc($qDpjp)) $dpjp_list[] = $row; }
}

// DIAGNOSA
$diagnosa_list = [];
if(cpTableExists('diagnosa_pasien') && cpTableExists('penyakit')) {
    $qDx = bukaquery("SELECT dp.kd_penyakit, dp.prioritas, p.nm_penyakit FROM diagnosa_pasien dp LEFT JOIN penyakit p ON dp.kd_penyakit = p.kd_penyakit WHERE dp.no_rawat = '$norawat' AND dp.status = 'Ranap' ORDER BY dp.prioritas");
    if($qDx) { while($row = mysqli_fetch_assoc($qDx)) $diagnosa_list[] = $row; }
}

// LAB KRITIS
$lab_kritis = [];
if(cpTableExists('detail_periksa_lab')) {
    $qLab = bukaquery("SELECT dpl.tgl_periksa, dpl.nilai, dpl.keterangan, t.Pemeriksaan as nm_pemeriksaan FROM detail_periksa_lab dpl LEFT JOIN template_laboratorium t ON dpl.id_template = t.id_template WHERE dpl.no_rawat = '$norawat' AND dpl.keterangan IN ('H','h','L','l') ORDER BY dpl.tgl_periksa DESC LIMIT 10");
    if($qLab) { while($row = mysqli_fetch_assoc($qLab)) $lab_kritis[] = $row; }
}

// RISIKO JATUH
$risiko_jatuh = null;
foreach(['penilaian_lanjutan_resiko_jatuh_dewasa','penilaian_lanjutan_resiko_jatuh_anak','penilaian_lanjutan_resiko_jatuh_lansia','penilaian_lanjutan_resiko_jatuh_geriatri'] as $tbl) {
    if(cpTableExists($tbl)) {
        $qR = bukaquery("SELECT hasil_skrining, saran FROM $tbl WHERE no_rawat = '$norawat' ORDER BY tanggal DESC LIMIT 1");
        if($qR && mysqli_num_rows($qR) > 0) { $risiko_jatuh = mysqli_fetch_assoc($qR); break; }
    }
}

// OPERASI
$operasi_list = [];
if(cpTableExists('operasi') && cpTableExists('paket_operasi')) {
    $qOp = bukaquery("SELECT DATE(o.tgl_operasi) as tgl_op, po.nm_perawatan FROM operasi o LEFT JOIN paket_operasi po ON o.kode_paket = po.kode_paket WHERE o.no_rawat = '$norawat' ORDER BY o.tgl_operasi DESC");
    if($qOp) { while($row = mysqli_fetch_assoc($qOp)) $operasi_list[] = $row; }
}

// BUILD TIMELINE
$timeline_data = [];
foreach($timeline_dates as $h_index => $tgl) {
    $day = ['hari' => $h_index, 'tanggal' => $tgl, 'is_today' => ($tgl == $today),
        'hari_indo' => ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w', strtotime($tgl))]];
    
    $day['soap'] = cpSafeCount('pemeriksaan_ranap', $norawat, 'tgl_perawatan', $tgl);
    $resep = cpSafeResepQuery($norawat, $tgl);
    $day['resep_total'] = $resep['total']; $day['resep_sudah'] = $resep['sudah']; $day['resep_belum'] = $resep['belum'];
    
    $lab_pk = cpSafeLabRadQuery('permintaan_lab', $norawat, $tgl);
    $day['lab_pk_total'] = $lab_pk['total']; $day['lab_pk_sudah'] = $lab_pk['sudah']; $day['lab_pk_belum'] = $lab_pk['belum'];
    $lab_mb = cpSafeLabRadQuery('permintaan_labmb', $norawat, $tgl);
    $day['lab_mb_total'] = $lab_mb['total']; $day['lab_mb_sudah'] = $lab_mb['sudah']; $day['lab_mb_belum'] = $lab_mb['belum'];
    $lab_pa = cpSafeLabRadQuery('permintaan_labpa', $norawat, $tgl);
    $day['lab_pa_total'] = $lab_pa['total']; $day['lab_pa_sudah'] = $lab_pa['sudah']; $day['lab_pa_belum'] = $lab_pa['belum'];
    
    $rad = cpSafeLabRadQuery('permintaan_radiologi', $norawat, $tgl);
    $day['rad_total'] = $rad['total']; $day['rad_sudah'] = $rad['sudah']; $day['rad_belum'] = $rad['belum'];
    
    $tindakan_dr = cpGetTindakanDokter($norawat, $tgl);
    $tindakan_pr = cpGetTindakanPerawat($norawat, $tgl);
    $tindakan_drpr = cpGetTindakanDrPr($norawat, $tgl);
    
    $day['tindakan_dokter'] = $tindakan_dr['data'];
    $day['tindakan_perawat'] = $tindakan_pr['data'];
    $day['tindakan_drpr'] = $tindakan_drpr['data'];
    $day['tindakan_total'] = count($day['tindakan_dokter']) + count($day['tindakan_perawat']) + count($day['tindakan_drpr']);
    
    // Biaya per kategori
    $day['biaya_tindakan_dr'] = $tindakan_dr['total_biaya'];
    $day['biaya_tindakan_pr'] = $tindakan_pr['total_biaya'];
    $day['biaya_tindakan_drpr'] = $tindakan_drpr['total_biaya'];
    
    // Biaya Lab
    $day['biaya_lab_pk'] = cpGetBiayaLabPK($norawat, $tgl);
    $day['biaya_lab_pa'] = cpGetBiayaLabPA($norawat, $tgl);
    $day['biaya_lab_total'] = $day['biaya_lab_pk'] + $day['biaya_lab_pa'];
    
    // Biaya Radiologi
    $day['biaya_radiologi'] = cpGetBiayaRadiologi($norawat, $tgl);
    
    // Biaya Obat & BHP
    $day['biaya_obat'] = cpGetBiayaObat($norawat, $tgl);
    
    // Biaya Operasi
    $day['biaya_operasi'] = cpGetBiayaOperasi($norawat, $tgl);
    
    // Biaya Obat BMHP Operasi
    $day['biaya_obat_operasi'] = cpGetBiayaObatOperasi($norawat, $tgl);
    
    // Biaya Kamar (per hari berdasarkan tanggal)
    $day['biaya_kamar'] = cpGetBiayaKamar($norawat, $tgl);
    
    // Info Kamar (nama kamar pada tanggal tersebut)
    $day['info_kamar'] = cpGetInfoKamar($norawat, $tgl);

    // Total biaya hari ini
    $day['biaya_total_hari'] = $day['biaya_tindakan_dr'] + $day['biaya_tindakan_pr'] + $day['biaya_tindakan_drpr'] + $day['biaya_lab_total'] + $day['biaya_radiologi'] + $day['biaya_obat'] + $day['biaya_operasi'] + $day['biaya_obat_operasi'] + $day['biaya_kamar'];
    
    $day['obat_bhp'] = cpGetObatBHP($norawat, $tgl);
    $day['obat_count'] = count($day['obat_bhp']);
    $day['operasi'] = cpSafeCount('operasi', $norawat, 'tgl_operasi', $tgl, true);

    // Data Operasi Hari Ini
    $day['operasi_detail'] = [];
    if($day['operasi'] > 0 && cpTableExists('operasi') && cpTableExists('paket_operasi')) {
        $qOpHariIni = bukaquery("SELECT DATE(o.tgl_operasi) as tgl_op, TIME(o.tgl_operasi) as jam_op, po.nm_perawatan 
                                FROM operasi o 
                                LEFT JOIN paket_operasi po ON o.kode_paket = po.kode_paket 
                                WHERE o.no_rawat = '$norawat' 
                                AND DATE(o.tgl_operasi) = '$tgl'
                                ORDER BY o.tgl_operasi DESC");
        if($qOpHariIni) {
            while($rowOp = mysqli_fetch_assoc($qOpHariIni)) {
                $day['operasi_detail'][] = $rowOp;
            }
        }
    }

    // Data Lab Hari Ini
    $day['lab_hari_ini'] = cpGetLabHariIni($norawat, $tgl);
    
    // Data Hasil Radiologi Hari Ini
    $day['radiologi_hari_ini'] = cpGetHasilRadiologi($norawat, $tgl);
    
    $day['has_activity'] = ($day['soap'] > 0 || $day['resep_total'] > 0 || $day['lab_pk_total'] > 0 || $day['lab_mb_total'] > 0 || $day['lab_pa_total'] > 0 || $day['rad_total'] > 0 || $day['tindakan_total'] > 0 || $day['obat_count'] > 0 || $day['operasi'] > 0);
    $timeline_data[] = $day;
}
$timeline_data = array_reverse($timeline_data);

$total_soap = array_sum(array_column($timeline_data, 'soap'));
$total_lab = array_sum(array_column($timeline_data, 'lab_pk_total')) + array_sum(array_column($timeline_data, 'lab_mb_total')) + array_sum(array_column($timeline_data, 'lab_pa_total'));
$total_rad = array_sum(array_column($timeline_data, 'rad_total'));
$total_obat = array_sum(array_column($timeline_data, 'obat_count'));
$total_tindakan = array_sum(array_column($timeline_data, 'tindakan_total'));
$total_operasi = array_sum(array_column($timeline_data, 'operasi'));

// SUMMARY BIAYA TOTAL (Keseluruhan Hari)
$summary_biaya = [
    'kamar' => array_sum(array_column($timeline_data, 'biaya_kamar')),
    'tindakan_dr' => array_sum(array_column($timeline_data, 'biaya_tindakan_dr')),
    'tindakan_pr' => array_sum(array_column($timeline_data, 'biaya_tindakan_pr')),
    'tindakan_drpr' => array_sum(array_column($timeline_data, 'biaya_tindakan_drpr')),
    'lab_pk' => array_sum(array_column($timeline_data, 'biaya_lab_pk')),
    'lab_pa' => array_sum(array_column($timeline_data, 'biaya_lab_pa')),
    'radiologi' => array_sum(array_column($timeline_data, 'biaya_radiologi')),
    'obat' => array_sum(array_column($timeline_data, 'biaya_obat')),
    'operasi' => array_sum(array_column($timeline_data, 'biaya_operasi')),
    'obat_operasi' => array_sum(array_column($timeline_data, 'biaya_obat_operasi'))
];
$summary_biaya['tindakan_total'] = $summary_biaya['tindakan_dr'] + $summary_biaya['tindakan_pr'] + $summary_biaya['tindakan_drpr'];
$summary_biaya['lab_total'] = $summary_biaya['lab_pk'] + $summary_biaya['lab_pa'];
$summary_biaya['grand_total'] = array_sum($summary_biaya) - $summary_biaya['tindakan_total'] - $summary_biaya['lab_total']; // exclude duplicate
?>

<style>
.cp-container { padding: 0; background: #f5f5f5; }

/* Header */
.cp-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 15px; }
.cp-header h2 { margin: 0 0 8px 0; font-size: 16px; font-weight: 600; }
.cp-header-info { display: flex; flex-wrap: wrap; gap: 15px; font-size: 11px; opacity: 0.95; }
.cp-header-info span { display: flex; align-items: center; gap: 4px; }
.cp-header-info i { font-size: 14px; }

/* Summary Cards */
.cp-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap: 10px; margin-bottom: 15px; }
.cp-summary-card { background: white; border-radius: 8px; padding: 12px 10px; text-align: center; border: 1px solid #e0e0e0; }
.cp-summary-value { font-size: 22px; font-weight: 700; line-height: 1; }
.cp-summary-label { font-size: 9px; color: #888; margin-top: 4px; text-transform: uppercase; }

/* Summary Biaya Total */
.cp-biaya-summary { background: white; border-radius: 10px; border: 1px solid #e0e0e0; padding: 12px 15px; margin-bottom: 15px; }
.cp-biaya-summary-title { font-size: 11px; font-weight: 600; color: #5e35b1; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.cp-biaya-summary-title i { font-size: 16px; }
.cp-biaya-summary-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.cp-biaya-chip { display: flex; align-items: center; gap: 6px; background: #f5f5f5; border-radius: 20px; padding: 6px 12px; border: 1px solid #e0e0e0; }
.cp-biaya-chip-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
.cp-biaya-chip-info { display: flex; flex-direction: column; }
.cp-biaya-chip-label { font-size: 9px; color: #888; line-height: 1; }
.cp-biaya-chip-value { font-size: 11px; font-weight: 700; color: #333; line-height: 1.3; }
.cp-biaya-chip.total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
.cp-biaya-chip.total .cp-biaya-chip-label { color: rgba(255,255,255,0.8); }
.cp-biaya-chip.total .cp-biaya-chip-value { color: white; font-size: 13px; }
.cp-biaya-chip.total .cp-biaya-chip-icon { background: rgba(255,255,255,0.2); color: white; }

/* Info Row */
.cp-info-row { display: flex; gap: 12px; margin-bottom: 15px; flex-wrap: wrap; }
.cp-info-card { flex: 1; min-width: 180px; background: white; border-radius: 8px; border: 1px solid #e0e0e0; overflow: hidden; }
.cp-info-header { padding: 8px 12px; font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 6px; border-bottom: 2px solid currentColor; }
.cp-info-header i { font-size: 14px; }
.cp-info-body { padding: 10px 12px; max-height: 120px; overflow-y: auto; }
.cp-dpjp-item { display: flex; align-items: center; gap: 8px; padding: 3px 0; font-size: 10px; }
.cp-dpjp-avatar { width: 22px; height: 22px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 600; }
.cp-dx-item { display: flex; align-items: flex-start; gap: 5px; margin-bottom: 4px; font-size: 10px; }
.cp-dx-badge { padding: 1px 5px; border-radius: 4px; font-size: 8px; font-weight: 700; color: white; }
.cp-dx-badge.primer { background: #e91e63; }
.cp-dx-badge.sekunder { background: #9c27b0; }
.cp-dx-code { font-weight: 600; color: #7b1fa2; }
.cp-lab-item { display: flex; align-items: center; gap: 5px; padding: 2px 0; font-size: 10px; }
.cp-lab-flag { width: 16px; height: 16px; border-radius: 3px; display: flex; align-items: center; justify-content: center; font-size: 9px; font-weight: 700; color: white; }
.cp-lab-flag.high { background: #f44336; }
.cp-lab-flag.low { background: #2196f3; }
.cp-risiko { text-align: center; padding: 5px; }
.cp-risiko-value { font-size: 13px; font-weight: 700; }
.cp-risiko-saran { font-size: 9px; color: #666; margin-top: 4px; line-height: 1.3; }
.cp-empty { text-align: center; color: #999; padding: 10px; font-size: 10px; }

/* Timeline Day */
.cp-day { background: white; border-radius: 10px; border: 1px solid #e0e0e0; margin-bottom: 12px; overflow: hidden; }
.cp-day-header { display: flex; align-items: center; gap: 10px; padding: 12px 15px; cursor: pointer; transition: background 0.2s; border-bottom: 1px solid #eee; }
.cp-day-header:hover { background: #f8f8f8; }
.cp-day-header.today { background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); }
.cp-day-marker { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: white; flex-shrink: 0; }
.cp-day-marker.h0 { background: #9c27b0; }
.cp-day-marker.past { background: #9e9e9e; }
.cp-day-marker.today { background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); }
.cp-day-info { flex: 1; }
.cp-day-title { font-size: 13px; font-weight: 600; color: #333; }
.cp-day-date { font-size: 10px; color: #888; }
.cp-day-biaya { text-align: right; margin-right: 10px; }
.cp-day-biaya-label { display: block; font-size: 9px; color: #888; text-transform: uppercase; }
.cp-day-biaya-value { font-size: 13px; font-weight: 700; color: #5e35b1; }
.cp-day-header.today .cp-day-biaya-value { color: #2e7d32; }
.cp-day-kamar-wrap { display: flex; flex-wrap: wrap; gap: 4px; margin-right: 10px; }
.cp-day-kamar { display: flex; align-items: center; gap: 4px; background: #e3f2fd; padding: 4px 10px; border-radius: 15px; font-size: 10px; color: #1565c0; font-weight: 500; }
.cp-day-kamar i { font-size: 14px; }
.cp-day-kamar.pindah { background: #fff3e0; color: #e65100; }
.cp-day-header.today .cp-day-kamar { background: #c8e6c9; color: #2e7d32; }
.cp-day-header.today .cp-day-kamar.pindah { background: #ffecb3; color: #ff8f00; }
.cp-day-toggle { color: #999; transition: transform 0.3s; }
.cp-day-toggle.open { transform: rotate(180deg); }

/* Day Content - 2 Column Layout */
.cp-day-content { display: none; }
.cp-day-content.open { display: block; }
.cp-day-grid { display: flex; gap: 0; }
.cp-day-col { flex: 1; padding: 15px; }
.cp-day-col-left { border-right: 1px solid #eee; }
.cp-day-col-right { background: #fafafa; }

.cp-col-header { font-size: 11px; font-weight: 600; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid currentColor; display: flex; align-items: center; gap: 6px; }
.cp-col-header i { font-size: 16px; }
.cp-col-header.detail { color: #667eea; }
.cp-col-header.ringkasan { color: #ff9800; }

/* Status Badges */
.cp-section-label { font-size: 10px; color: #888; margin: 8px 0 6px 0; }
.cp-status-row { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 8px; }
.cp-badge { display: inline-flex; align-items: center; gap: 3px; padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; }
.cp-badge i { font-size: 12px; }
.cp-badge-green { background: #4caf50; color: white; }
.cp-badge-red { background: #f44336; color: white; }
.cp-badge-purple { background: #9c27b0; color: white; }
.cp-badge-orange { background: #ff9800; color: white; }

/* Tindakan */
.cp-tindakan-group { margin-bottom: 8px; }
.cp-tindakan-group-title { font-size: 10px; font-weight: 600; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; }
.cp-tindakan-group-title i { font-size: 12px; }
.cp-tindakan-list { display: flex; flex-wrap: wrap; gap: 4px; }
.cp-tindakan-item { padding: 3px 8px; border-radius: 8px; font-size: 10px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; position: relative; }
.cp-tindakan-dr { background: #e3f2fd; color: #1565c0; }
.cp-tindakan-pr { background: #fff3e0; color: #e65100; }
.cp-tindakan-drpr { background: #e0f2f1; color: #00695c; }
.cp-tindakan-item i { font-size: 12px; }
.cp-tooltip { display: none; position: absolute; background: white; border: 1px solid #667eea; border-radius: 6px; padding: 8px 10px; font-size: 10px; color: #333; z-index: 9999; min-width: 180px; max-width: 280px; box-shadow: 0 4px 12px rgba(0,0,0,0.2); left: 0; top: 100%; margin-top: 4px; }
.cp-tindakan-item:hover .cp-tooltip { display: block; }
.cp-tooltip-title { font-weight: 600; color: #667eea; margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px solid #e0e0e0; }

/* Obat */
.cp-obat-grid { display: flex; gap: 8px; }
.cp-obat-col { flex: 1; border: 1px solid #e0e0e0; border-radius: 6px; padding: 8px; background: #fafafa; }
.cp-obat-col.resep { background: #f1f8f4; border-color: #c8e6c9; }
.cp-obat-col-title { font-size: 10px; font-weight: 600; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; gap: 4px; color: #757575; }
.cp-obat-col.resep .cp-obat-col-title { color: #2e7d32; border-bottom-color: #c8e6c9; }
.cp-obat-col-title i { font-size: 12px; }
.cp-obat-list { max-height: 100px; overflow-y: auto; font-size: 10px; }
.cp-obat-item { display: flex; align-items: flex-start; gap: 5px; margin-bottom: 4px; padding-bottom: 4px; border-bottom: 1px dashed #eee; }
.cp-obat-item:last-child { border-bottom: none; }
.cp-obat-qty { background: #e0e0e0; color: #666; padding: 2px 5px; border-radius: 3px; font-size: 9px; font-weight: 600; min-width: 28px; text-align: center; }
.cp-obat-col.resep .cp-obat-qty { background: #c8e6c9; color: #2e7d32; }
.cp-obat-info { flex: 1; }
.cp-obat-name { font-weight: 500; color: #333; }
.cp-obat-aturan { color: #888; font-size: 9px; margin-top: 2px; }

/* Ringkasan Klinis */
.cp-ringkasan-item { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e0e0e0; font-size: 11px; }
.cp-ringkasan-item:last-child { border-bottom: none; }
.cp-ringkasan-label { color: #666; }
.cp-ringkasan-value { font-weight: 600; color: #333; }
.cp-ringkasan-value.badge { padding: 2px 8px; border-radius: 4px; font-size: 10px; }
.cp-ringkasan-value.badge-bpjs { background: #4caf50; color: white; }
.cp-ringkasan-operasi { margin-top: 12px; }
.cp-operasi-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 10px; }
.cp-operasi-date { background: #ff9800; color: white; padding: 2px 8px; border-radius: 4px; font-size: 9px; font-weight: 600; }

/* Biaya List */
.cp-biaya-list { margin-bottom: 12px; }
.cp-biaya-item { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px dashed #e0e0e0; }
.cp-biaya-item:last-child { border-bottom: none; }
.cp-biaya-icon { width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.cp-biaya-label { flex: 1; font-size: 11px; color: #555; }
.cp-biaya-value { font-size: 11px; font-weight: 600; color: #333; text-align: right; }

/* Biaya Total */
.cp-biaya-total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; padding: 12px; display: flex; justify-content: space-between; align-items: center; }
.cp-biaya-total-label { font-size: 11px; font-weight: 600; color: white; }
.cp-biaya-total-value { font-size: 14px; font-weight: 700; color: white; }

/* Biaya Empty */
.cp-biaya-empty { background: #fafafa; border: 2px dashed #e0e0e0; border-radius: 8px; padding: 30px; text-align: center; color: #999; }
.cp-biaya-empty i { font-size: 32px; display: block; margin-bottom: 8px; opacity: 0.5; }
.cp-biaya-empty p { font-size: 11px; margin: 0; }

/* Lab Card Compact - 2 Column Grid */
.cp-lab-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.cp-lab-card { border: 1px solid #e0f2f1; border-radius: 8px; overflow: hidden; background: white; }
.cp-lab-card.kritis { border-color: #ffcdd2; }
.cp-lab-card-header { background: linear-gradient(135deg, #00897b 0%, #00695c 100%); color: white; padding: 8px 12px; font-size: 10px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.cp-lab-card-header i { font-size: 14px; }
.cp-lab-card-header.kritis { background: linear-gradient(135deg, #ef5350 0%, #c62828 100%); }
.cp-lab-card-body { max-height: 150px; overflow-y: auto; }
.cp-lab-row { display: flex; align-items: center; padding: 6px 10px; border-bottom: 1px solid #f0f0f0; gap: 8px; }
.cp-lab-row:last-child { border-bottom: none; }
.cp-lab-row:hover { background: #f9fbe7; }
.cp-lab-icon { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 11px; font-weight: 700; }
.cp-lab-icon i { font-size: 12px; }
.cp-lab-icon.normal { background: #e8f5e9; color: #2e7d32; }
.cp-lab-icon.high { background: #ffebee; color: #c62828; }
.cp-lab-icon.low { background: #e3f2fd; color: #1565c0; }
.cp-lab-icon.pending { background: #fff3e0; color: #e65100; }
.cp-lab-nama { flex: 1; font-size: 10px; font-weight: 500; color: #333; line-height: 1.3; }
.cp-lab-nilai { font-size: 11px; font-weight: 700; text-align: right; display: flex; align-items: center; gap: 3px; white-space: nowrap; }
.cp-lab-nilai.normal { color: #2e7d32; }
.cp-lab-nilai.high { color: #c62828; }
.cp-lab-nilai.low { color: #1565c0; }
.cp-lab-nilai.pending { color: #9e9e9e; font-weight: 400; font-style: italic; font-size: 9px; }
.cp-lab-nilai small { font-size: 8px; color: #888; font-weight: 400; }
.cp-lab-arrow { font-size: 9px; }
.cp-lab-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 10px; color: #4caf50; text-align: center; }
.cp-lab-empty i { font-size: 24px; margin-bottom: 4px; }
.cp-lab-empty span { font-size: 10px; color: #666; }

@media (max-width: 600px) {
    .cp-lab-grid { grid-template-columns: 1fr; }
}

/* Radiologi Card */
.cp-radiologi-card { border: 1px solid #e1bee7; border-radius: 8px; overflow: hidden; background: white; margin-top: 8px; }
.cp-radiologi-header { background: linear-gradient(135deg, #8e24aa 0%, #6a1b9a 100%); color: white; padding: 8px 12px; font-size: 10px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.cp-radiologi-header i { font-size: 14px; }
.cp-radiologi-body { max-height: 200px; overflow-y: auto; }
.cp-radiologi-item { padding: 10px 12px; border-bottom: 1px solid #f3e5f5; }
.cp-radiologi-item:last-child { border-bottom: none; }
.cp-radiologi-item:hover { background: #fce4ec; }
.cp-radiologi-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.cp-radiologi-time { display: flex; align-items: center; gap: 3px; font-size: 9px; color: #8e24aa; font-weight: 600; background: #f3e5f5; padding: 2px 8px; border-radius: 10px; }
.cp-radiologi-time i { font-size: 12px; }
.cp-radiologi-type { font-size: 10px; font-weight: 600; color: #6a1b9a; }
.cp-radiologi-hasil { font-size: 11px; color: #333; line-height: 1.5; background: #fafafa; padding: 8px 10px; border-radius: 6px; border-left: 3px solid #8e24aa; }

/* Actions */
.cp-actions { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
.cp-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; transition: all 0.2s; }
.cp-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.cp-btn i { font-size: 16px; }
.cp-btn-purple { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.cp-btn-green { background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); color: white; }

@media (max-width: 768px) {
    .cp-day-grid { flex-direction: column; }
    .cp-day-col-left { border-right: none; border-bottom: 1px solid #eee; }
}
</style>

<div class="cp-container">
    
    <!-- Header -->
    <div class="cp-header">
        <h2><?php echo $dataPasien['nm_pasien']; ?> <span style="font-weight: 400; font-size: 12px; opacity: 0.9;">(<?php echo $dataPasien['no_rkm_medis']; ?>)</span></h2>
        <div class="cp-header-info">
            <span><i class="material-icons">badge</i> <?php echo $dataPasien['no_rawat']; ?></span>
            <span><i class="material-icons">wc</i> <?php echo $dataPasien['jk']; ?> / <?php echo cpHitungUmur($dataPasien['tgl_lahir']); ?></span>
            <span><i class="material-icons">meeting_room</i> <?php echo $dataPasien['nm_bangsal']; ?> - <?php echo $dataPasien['kd_kamar']; ?> (Kls <?php echo $dataPasien['kelas']; ?>)</span>
            <span><i class="material-icons">login</i> <?php echo date('d/m/Y H:i', strtotime($dataPasien['tgl_masuk'].' '.$dataPasien['jam_masuk'])); ?></span>
            <span><i class="material-icons">schedule</i> <strong>Hari ke-<?php echo $hari_rawat; ?></strong></span>
            <span><i class="material-icons">credit_card</i> <?php echo strtoupper($dataPasien['png_jawab']); ?></span>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="cp-summary-grid">
        <div class="cp-summary-card"><div class="cp-summary-value" style="color: #667eea;">H<?php echo $hari_rawat; ?></div><div class="cp-summary-label">Hari Rawat</div></div>
        <div class="cp-summary-card"><div class="cp-summary-value" style="color: #4caf50;"><?php echo $total_soap; ?></div><div class="cp-summary-label">Total SOAP</div></div>
        <div class="cp-summary-card"><div class="cp-summary-value" style="color: #2196f3;"><?php echo $total_lab; ?></div><div class="cp-summary-label">Total Lab</div></div>
        <div class="cp-summary-card"><div class="cp-summary-value" style="color: #e91e63;"><?php echo $total_rad; ?></div><div class="cp-summary-label">Total Rad</div></div>
        <div class="cp-summary-card"><div class="cp-summary-value" style="color: #ff9800;"><?php echo $total_obat; ?></div><div class="cp-summary-label">Total Obat</div></div>
        <div class="cp-summary-card"><div class="cp-summary-value" style="color: #9c27b0;"><?php echo $total_tindakan; ?></div><div class="cp-summary-label">Total Tindakan</div></div>
        <div class="cp-summary-card"><div class="cp-summary-value" style="color: #f44336;"><?php echo $total_operasi; ?></div><div class="cp-summary-label">Operasi</div></div>
    </div>
    
    <!-- Summary Biaya Total -->
    <div class="cp-biaya-summary">
        <div class="cp-biaya-summary-title"><i class="material-icons">account_balance_wallet</i> ESTIMASI BIAYA SELAMA RAWAT INAP</div>
        <div class="cp-biaya-summary-grid">
            <?php if($summary_biaya['kamar'] > 0): ?>
            <div class="cp-biaya-chip">
                <span class="cp-biaya-chip-icon" style="background: #ede7f6; color: #5e35b1;">🛏️</span>
                <div class="cp-biaya-chip-info">
                    <span class="cp-biaya-chip-label">Kamar</span>
                    <span class="cp-biaya-chip-value">Rp <?php echo number_format($summary_biaya['kamar'], 0, ',', '.'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($summary_biaya['tindakan_total'] > 0): ?>
            <div class="cp-biaya-chip">
                <span class="cp-biaya-chip-icon" style="background: #e3f2fd; color: #1565c0;">💉</span>
                <div class="cp-biaya-chip-info">
                    <span class="cp-biaya-chip-label">Tindakan</span>
                    <span class="cp-biaya-chip-value">Rp <?php echo number_format($summary_biaya['tindakan_total'], 0, ',', '.'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($summary_biaya['lab_total'] > 0): ?>
            <div class="cp-biaya-chip">
                <span class="cp-biaya-chip-icon" style="background: #e8f5e9; color: #2e7d32;">🔬</span>
                <div class="cp-biaya-chip-info">
                    <span class="cp-biaya-chip-label">Laboratorium</span>
                    <span class="cp-biaya-chip-value">Rp <?php echo number_format($summary_biaya['lab_total'], 0, ',', '.'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($summary_biaya['radiologi'] > 0): ?>
            <div class="cp-biaya-chip">
                <span class="cp-biaya-chip-icon" style="background: #e3f2fd; color: #1976d2;">📷</span>
                <div class="cp-biaya-chip-info">
                    <span class="cp-biaya-chip-label">Radiologi</span>
                    <span class="cp-biaya-chip-value">Rp <?php echo number_format($summary_biaya['radiologi'], 0, ',', '.'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($summary_biaya['obat'] > 0): ?>
            <div class="cp-biaya-chip">
                <span class="cp-biaya-chip-icon" style="background: #fff8e1; color: #f57c00;">💊</span>
                <div class="cp-biaya-chip-info">
                    <span class="cp-biaya-chip-label">Obat & BHP</span>
                    <span class="cp-biaya-chip-value">Rp <?php echo number_format($summary_biaya['obat'], 0, ',', '.'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($summary_biaya['operasi'] > 0): ?>
            <div class="cp-biaya-chip">
                <span class="cp-biaya-chip-icon" style="background: #ffebee; color: #c62828;">🏥</span>
                <div class="cp-biaya-chip-info">
                    <span class="cp-biaya-chip-label">Operasi/VK</span>
                    <span class="cp-biaya-chip-value">Rp <?php echo number_format($summary_biaya['operasi'], 0, ',', '.'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if($summary_biaya['obat_operasi'] > 0): ?>
            <div class="cp-biaya-chip">
                <span class="cp-biaya-chip-icon" style="background: #f3e5f5; color: #7b1fa2;">💉</span>
                <div class="cp-biaya-chip-info">
                    <span class="cp-biaya-chip-label">BMHP Operasi</span>
                    <span class="cp-biaya-chip-value">Rp <?php echo number_format($summary_biaya['obat_operasi'], 0, ',', '.'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Total -->
            <div class="cp-biaya-chip total">
                <span class="cp-biaya-chip-icon">💵</span>
                <div class="cp-biaya-chip-info">
                    <span class="cp-biaya-chip-label">TOTAL BIAYA</span>
                    <span class="cp-biaya-chip-value">Rp <?php echo number_format($summary_biaya['grand_total'], 0, ',', '.'); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Info Row -->
    <div class="cp-info-row">
        <div class="cp-info-card">
            <div class="cp-info-header" style="color: #667eea;"><i class="material-icons">supervisor_account</i> DPJP</div>
            <div class="cp-info-body">
                <?php if(empty($dpjp_list)): ?><div class="cp-empty">Belum ada DPJP</div>
                <?php else: foreach($dpjp_list as $dpjp): ?>
                <div class="cp-dpjp-item"><div class="cp-dpjp-avatar"><?php echo strtoupper(substr($dpjp['nm_dokter'], 0, 1)); ?></div><div><div style="font-weight: 600; color: #333;"><?php echo $dpjp['nm_dokter']; ?></div><div style="color: #888; font-size: 9px;"><?php echo $dpjp['nm_sps']; ?></div></div></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="cp-info-card">
            <div class="cp-info-header" style="color: #e91e63;"><i class="material-icons">local_hospital</i> Diagnosa (ICD-10)</div>
            <div class="cp-info-body">
                <?php if(empty($diagnosa_list)): ?><div class="cp-empty">Belum ada diagnosa</div>
                <?php else: foreach($diagnosa_list as $dx): ?>
                <div class="cp-dx-item"><span class="cp-dx-badge <?php echo $dx['prioritas'] == 1 ? 'primer' : 'sekunder'; ?>"><?php echo $dx['prioritas'] == 1 ? 'P' : 'S'.$dx['prioritas']; ?></span><div><span class="cp-dx-code"><?php echo $dx['kd_penyakit']; ?></span> - <?php echo $dx['nm_penyakit']; ?></div></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="cp-info-card">
            <div class="cp-info-header" style="color: #ff9800;"><i class="material-icons">warning</i> Lab Kritis</div>
            <div class="cp-info-body">
                <?php if(empty($lab_kritis)): ?><div class="cp-empty">Tidak ada lab kritis</div>
                <?php else: foreach($lab_kritis as $lk): ?>
                <div class="cp-lab-item"><span class="cp-lab-flag <?php echo in_array($lk['keterangan'], ['H','h']) ? 'high' : 'low'; ?>"><?php echo in_array($lk['keterangan'], ['H','h']) ? '↑' : '↓'; ?></span><div style="flex: 1; font-weight: 500;"><?php echo $lk['nm_pemeriksaan']; ?></div><div style="font-weight: 600; color: <?php echo in_array($lk['keterangan'], ['H','h']) ? '#f44336' : '#2196f3'; ?>;"><?php echo $lk['nilai']; ?></div></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="cp-info-card">
            <?php $risiko_color = '#4caf50'; if($risiko_jatuh) { $rl = strtolower($risiko_jatuh['hasil_skrining']); $risiko_color = (strpos($rl, 'tinggi') !== false) ? '#f44336' : ((strpos($rl, 'sedang') !== false) ? '#ff9800' : '#4caf50'); } ?>
            <div class="cp-info-header" style="color: <?php echo $risiko_color; ?>;"><i class="material-icons">report_problem</i> Risiko Jatuh</div>
            <div class="cp-info-body">
                <?php if(!$risiko_jatuh): ?><div class="cp-empty">Belum dinilai</div>
                <?php else: ?><div class="cp-risiko"><div class="cp-risiko-value" style="color: <?php echo $risiko_color; ?>;"><?php echo strtoupper($risiko_jatuh['hasil_skrining']); ?></div><?php if(!empty($risiko_jatuh['saran'])): ?><div class="cp-risiko-saran"><?php echo $risiko_jatuh['saran']; ?></div><?php endif; ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Timeline -->
    <?php foreach($timeline_data as $day): 
        $marker_class = $day['is_today'] ? 'today' : ($day['hari'] == 0 ? 'h0' : 'past');
    ?>
    <div class="cp-day">
        <div class="cp-day-header <?php echo $day['is_today'] ? 'today' : ''; ?>" onclick="toggleCPDay(this)">
            <div class="cp-day-marker <?php echo $marker_class; ?>">H<?php echo $day['hari']; ?></div>
            <div class="cp-day-info">
                <div class="cp-day-title"><?php if($day['is_today']): ?>⭐ HARI INI<?php elseif($day['hari'] == 0): ?>Hari Masuk<?php else: ?>Hari ke-<?php echo $day['hari']; ?><?php endif; ?></div>
                <div class="cp-day-date"><?php echo date('d M Y', strtotime($day['tanggal'])); ?> (<?php echo $day['hari_indo']; ?>)</div>
            </div>
            <?php if(!empty($day['info_kamar'])): ?>
            <div class="cp-day-kamar-wrap">
                <?php foreach($day['info_kamar'] as $idx => $kamar): ?>
                <div class="cp-day-kamar <?php echo $kamar['stts_pulang'] == 'Pindah Kamar' ? 'pindah' : ''; ?>">
                    <i class="material-icons"><?php echo $kamar['stts_pulang'] == 'Pindah Kamar' ? 'swap_horiz' : 'meeting_room'; ?></i>
                    <span><?php echo $kamar['nm_bangsal']; ?> - <?php echo $kamar['kd_kamar']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if($day['biaya_total_hari'] > 0): ?>
            <div class="cp-day-biaya">
                <span class="cp-day-biaya-label">Total Biaya</span>
                <span class="cp-day-biaya-value">Rp <?php echo number_format($day['biaya_total_hari'], 0, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
            <i class="material-icons cp-day-toggle">expand_more</i>
        </div>
        
        <div class="cp-day-content">
            <div class="cp-day-grid">
                <!-- KOLOM KIRI: DETAIL PEMERIKSAAN -->
                <div class="cp-day-col cp-day-col-left">
                    <div class="cp-col-header detail"><i class="material-icons">assignment</i> PELAYANAN HARI INI</div>
                    
                    <?php if(!$day['has_activity']): ?>
                    <div class="cp-empty" style="padding: 30px;"><i class="material-icons" style="font-size: 32px;">inbox</i><br>Belum ada aktivitas tercatat</div>
                    <?php else: ?>
                    
                    <!-- Status RME -->
                    <div class="cp-section-label">Aktivitas Klinis :</div>
                    <div class="cp-status-row">
                        <?php if($day['soap'] > 0): ?><span class="cp-badge cp-badge-green"><i class="material-icons">check_circle</i> SOAP ke-<?php echo $day['soap']; ?></span><?php endif; ?>
                        <?php if($day['resep_sudah'] > 0): ?><span class="cp-badge cp-badge-green"><i class="material-icons">check_circle</i> E-Resep: <?php echo $day['resep_sudah']; ?></span><?php endif; ?>
                        <?php if($day['resep_belum'] > 0): ?><span class="cp-badge cp-badge-red"><i class="material-icons">pending</i> E-Resep: <?php echo $day['resep_belum']; ?></span><?php endif; ?>
                        <?php if($day['lab_pk_sudah'] > 0): ?><span class="cp-badge cp-badge-green"><i class="material-icons">check_circle</i> Lab PK: <?php echo $day['lab_pk_sudah']; ?></span><?php endif; ?>
                        <?php if($day['lab_pk_belum'] > 0): ?><span class="cp-badge cp-badge-red"><i class="material-icons">pending</i> Lab PK: <?php echo $day['lab_pk_belum']; ?></span><?php endif; ?>
                        <?php if($day['rad_sudah'] > 0): ?><span class="cp-badge cp-badge-green"><i class="material-icons">check_circle</i> Rad: <?php echo $day['rad_sudah']; ?></span><?php endif; ?>
                        <?php if($day['rad_belum'] > 0): ?><span class="cp-badge cp-badge-red"><i class="material-icons">pending</i> Rad: <?php echo $day['rad_belum']; ?></span><?php endif; ?>
                        <?php if($day['operasi'] > 0): ?><span class="cp-badge cp-badge-red"><i class="material-icons">content_cut</i> Operasi: <?php echo $day['operasi']; ?></span><?php endif; ?>
                    </div>
                    
                    <?php if(count($day['operasi_detail']) > 0): ?>
                    <!-- Operasi/VK Hari Ini -->
                    <div style="margin-top: 10px;">
                        <div style="font-size: 10px; color: #ad1457; font-weight: 600; margin-bottom: 6px;">
                            <i class="material-icons" style="font-size: 12px; vertical-align: middle;">content_cut</i> Operasi/VK
                        </div>
                        <?php foreach($day['operasi_detail'] as $opHari): ?>
                        <div style="display: flex; align-items: flex-start; gap: 6px; margin-bottom: 4px;">
                            <span style="background: #ad1457; color: white; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 600; white-space: nowrap;">
                                <?php echo date('d/m/Y', strtotime($opHari['tgl_op'])); ?>
                            </span>
                            <span style="font-size: 10px; color: #333; line-height: 1.4; font-weight: 500;">
                                <?php echo $opHari['nm_perawatan']; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Tindakan -->
                    <?php if($day['tindakan_total'] > 0): ?>
                    <div class="cp-section-label">Tindakan & Pemeriksaan :</div>
                    <?php if(count($day['tindakan_dokter']) > 0): ?>
                    <div class="cp-tindakan-group">
                        <div class="cp-tindakan-group-title" style="color: #1565c0;"><i class="material-icons">medical_services</i> Dokter</div>
                        <div class="cp-tindakan-list"><?php foreach($day['tindakan_dokter'] as $td): ?><div class="cp-tindakan-item cp-tindakan-dr"><?php echo $td['nm_perawatan']; ?><?php echo $td['jumlah'] > 1 ? ' ('.$td['jumlah'].'x)' : ''; ?><i class="material-icons">info</i><div class="cp-tooltip"><div class="cp-tooltip-title">👨‍⚕️ Dokter:</div><?php echo $td['dokter_list']; ?></div></div><?php endforeach; ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if(count($day['tindakan_drpr']) > 0): ?>
                    <div class="cp-tindakan-group">
                        <div class="cp-tindakan-group-title" style="color: #00695c;"><i class="material-icons">groups</i> Dokter & Perawat/Bidan</div>
                        <div class="cp-tindakan-list"><?php foreach($day['tindakan_drpr'] as $tdp): ?><div class="cp-tindakan-item cp-tindakan-drpr"><?php echo $tdp['nm_perawatan']; ?><?php echo $tdp['jumlah'] > 1 ? ' ('.$tdp['jumlah'].'x)' : ''; ?><i class="material-icons">info</i><div class="cp-tooltip"><div class="cp-tooltip-title">👨‍⚕️ Dokter:</div><?php echo $tdp['dokter_list']; ?><div class="cp-tooltip-title" style="margin-top: 6px;">👩‍⚕️ Perawat:</div><?php echo $tdp['perawat_list']; ?></div></div><?php endforeach; ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if(count($day['tindakan_perawat']) > 0): ?>
                    <div class="cp-tindakan-group">
                        <div class="cp-tindakan-group-title" style="color: #e65100;"><i class="material-icons">person</i> Perawat/Bidan/Petugas</div>
                        <div class="cp-tindakan-list"><?php foreach($day['tindakan_perawat'] as $tp): ?><div class="cp-tindakan-item cp-tindakan-pr"><?php echo $tp['nm_perawatan']; ?><?php echo $tp['jumlah'] > 1 ? ' ('.$tp['jumlah'].'x)' : ''; ?><i class="material-icons">info</i><div class="cp-tooltip"><div class="cp-tooltip-title">👩‍⚕️ Perawat:</div><?php echo $tp['perawat_list']; ?></div></div><?php endforeach; ?></div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Obat & BHP -->
                    <?php if(count($day['obat_bhp']) > 0): ?>
                    <div class="cp-section-label">Obat & BHP :</div>
                    <div class="cp-obat-grid">
                        <div class="cp-obat-col">
                            <div class="cp-obat-col-title"><i class="material-icons">inventory</i> BMHP</div>
                            <div class="cp-obat-list"><?php $ada_bmhp = false; foreach($day['obat_bhp'] as $ob): if($ob['aturan'] == 'BMHP'): $ada_bmhp = true; ?><div class="cp-obat-item"><span class="cp-obat-qty"><?php echo $ob['jml']; ?>x</span><div class="cp-obat-name"><?php echo $ob['nama_brng']; ?></div></div><?php endif; endforeach; if(!$ada_bmhp): ?><div class="cp-empty">Tidak ada BMHP</div><?php endif; ?></div>
                        </div>
                        <div class="cp-obat-col resep">
                            <div class="cp-obat-col-title"><i class="material-icons">inventory</i> Obat yang Diresep</div>
                            <div class="cp-obat-list"><?php $ada_obat = false; foreach($day['obat_bhp'] as $ob): if($ob['aturan'] != 'BMHP'): $ada_obat = true; ?><div class="cp-obat-item"><span class="cp-obat-qty"><?php echo $ob['jml']; ?>x</span><div class="cp-obat-info"><div class="cp-obat-name"><?php echo $ob['nama_brng']; ?></div><div class="cp-obat-aturan">📋 <?php echo $ob['aturan']; ?></div></div></div><?php endif; endforeach; if(!$ada_obat): ?><div class="cp-empty">Tidak ada obat</div><?php endif; ?></div>
                        </div>
                    </div>
                    <?php endif; ?>                 
                    <?php endif; ?>
                    <?php if(count($day['lab_hari_ini']) > 0): ?>
                    <!-- Laboratorium Hari Ini - 2 Kolom: Semua Lab | Hasil Kritis -->
                    <div class="cp-section-label">Laboratorium Hari Ini :</div>
                    <div class="cp-lab-grid">
                        <!-- Kolom Kiri: Semua Hasil Lab -->
                        <div class="cp-lab-card">
                            <div class="cp-lab-card-header">
                                <i class="material-icons">science</i> LABORATORIUM PK
                            </div>
                            <div class="cp-lab-card-body">
                                <?php foreach($day['lab_hari_ini'] as $lab): 
                                    if($lab['status'] == 'pending') {
                                        $icon_class = 'pending';
                                        $icon = 'hourglass_empty';
                                        $nilai_display = 'Menunggu hasil';
                                    } else {
                                        $icon_class = 'normal';
                                        $icon = 'check';
                                        $nilai_display = $lab['nilai'] . (!empty($lab['satuan']) ? ' <small>'.$lab['satuan'].'</small>' : '');
                                    }
                                ?>
                                <div class="cp-lab-row">
                                    <span class="cp-lab-icon <?php echo $icon_class; ?>">
                                        <i class="material-icons"><?php echo $icon; ?></i>
                                    </span>
                                    <span class="cp-lab-nama"><?php echo $lab['pemeriksaan']; ?></span>
                                    <span class="cp-lab-nilai <?php echo $icon_class; ?>"><?php echo $nilai_display; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Kolom Kanan: Hasil Kritis (H/L) -->
                        <?php 
                        $lab_kritis_hari_ini = array_filter($day['lab_hari_ini'], function($l) {
                            return $l['status'] == 'high' || $l['status'] == 'low';
                        });
                        ?>
                        <div class="cp-lab-card kritis">
                            <div class="cp-lab-card-header kritis">
                                <i class="material-icons">warning</i> HASIL KRITIS
                            </div>
                            <div class="cp-lab-card-body">
                                <?php if(count($lab_kritis_hari_ini) > 0): ?>
                                <?php foreach($lab_kritis_hari_ini as $lab): 
                                    $icon_class = $lab['status'];
                                    $arrow = $lab['status'] == 'high' ? '▲' : '▼';
                                    $nilai_display = $lab['nilai'] . (!empty($lab['satuan']) ? ' <small>'.$lab['satuan'].'</small>' : '');
                                ?>
                                <div class="cp-lab-row">
                                    <span class="cp-lab-icon <?php echo $icon_class; ?>">
                                        <?php echo $lab['status'] == 'high' ? '↑' : '↓'; ?>
                                    </span>
                                    <span class="cp-lab-nama"><?php echo $lab['pemeriksaan']; ?></span>
                                    <span class="cp-lab-nilai <?php echo $icon_class; ?>"><?php echo $nilai_display; ?> <span class="cp-lab-arrow"><?php echo $arrow; ?></span></span>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="cp-lab-empty">
                                    <i class="material-icons">check_circle</i>
                                    <span>Tidak ada hasil kritis</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(count($day['radiologi_hari_ini']) > 0): ?>
                    <!-- Hasil Bacaan Radiologi -->
                    <div class="cp-section-label">Hasil Bacaan Radiologi :</div>
                    <div class="cp-radiologi-card">
                        <div class="cp-radiologi-header">
                            <i class="material-icons">radiology</i> HASIL RADIOLOGI
                        </div>
                        <div class="cp-radiologi-body">
                            <?php foreach($day['radiologi_hari_ini'] as $rad): ?>
                            <div class="cp-radiologi-item">
                                <div class="cp-radiologi-meta">
                                    <span class="cp-radiologi-time"><i class="material-icons">schedule</i> <?php echo substr($rad['jam'], 0, 5); ?></span>
                                    <?php if(!empty($rad['nm_perawatan'])): ?>
                                    <span class="cp-radiologi-type"><?php echo $rad['nm_perawatan']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="cp-radiologi-hasil">
                                    <?php echo nl2br(htmlspecialchars($rad['hasil'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- KOLOM KANAN: RINCIAN BIAYA -->
                <div class="cp-day-col cp-day-col-right">
                    <div class="cp-col-header ringkasan"><i class="material-icons">payments</i> RINCIAN BIAYA HARI INI</div>
                    
                    <?php if($day['biaya_total_hari'] > 0): ?>
                    <div class="cp-biaya-list">
                        <?php if($day['biaya_kamar'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #ede7f6; color: #5e35b1;">🛏️</span>
                            <span class="cp-biaya-label">Biaya Kamar</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_kamar'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($day['biaya_tindakan_dr'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #e3f2fd; color: #1565c0;">🏥</span>
                            <span class="cp-biaya-label">Tindakan Dokter</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_tindakan_dr'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($day['biaya_tindakan_drpr'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #e0f2f1; color: #00695c;">👥</span>
                            <span class="cp-biaya-label">Tindakan Dr & Pr</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_tindakan_drpr'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($day['biaya_tindakan_pr'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #fff3e0; color: #e65100;">👩‍⚕️</span>
                            <span class="cp-biaya-label">Tindakan Perawat/Bidan/Petugas</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_tindakan_pr'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($day['biaya_lab_pk'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #e8f5e9; color: #2e7d32;">🔬</span>
                            <span class="cp-biaya-label">Laboratorium PK</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_lab_pk'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($day['biaya_lab_pa'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #fce4ec; color: #c2185b;">🧫</span>
                            <span class="cp-biaya-label">Laboratorium PA</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_lab_pa'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($day['biaya_radiologi'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #e3f2fd; color: #1976d2;">📷</span>
                            <span class="cp-biaya-label">Radiologi</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_radiologi'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($day['biaya_obat'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #fff8e1; color: #f57c00;">💊</span>
                            <span class="cp-biaya-label">Obat & BHP</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_obat'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($day['biaya_operasi'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #ffebee; color: #c62828;">🏥</span>
                            <span class="cp-biaya-label">Operasi / VK</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_operasi'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if($day['biaya_obat_operasi'] > 0): ?>
                        <div class="cp-biaya-item">
                            <span class="cp-biaya-icon" style="background: #f3e5f5; color: #7b1fa2;">💉</span>
                            <span class="cp-biaya-label">Obat & BMHP Operasi/VK</span>
                            <span class="cp-biaya-value">Rp <?php echo number_format($day['biaya_obat_operasi'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        
                    </div>
                    
                    <div class="cp-biaya-total">
                        <span class="cp-biaya-total-label">💵 TOTAL HARI INI</span>
                        <span class="cp-biaya-total-value">Rp <?php echo number_format($day['biaya_total_hari'], 0, ',', '.'); ?></span>
                    </div>
                    <?php else: ?>
                    <div class="cp-biaya-empty">
                        <i class="material-icons">receipt_long</i>
                        <p>Belum ada biaya tercatat</p>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Actions -->
    <div class="cp-actions">
        <button type="button" class="cp-btn cp-btn-purple" onclick="window.print();"><i class="material-icons">print</i> Cetak Timeline</button>
        <button type="button" class="cp-btn cp-btn-green" onclick="history.back();"><i class="material-icons">arrow_back</i> Kembali</button>
    </div>
    
</div>

<script>
function toggleCPDay(header) {
    const content = header.nextElementSibling;
    const toggle = header.querySelector('.cp-day-toggle');
    content.classList.toggle('open');
    toggle.classList.toggle('open');
}
document.addEventListener('DOMContentLoaded', function() {
    const todayHeader = document.querySelector('.cp-day-header.today');
    if(todayHeader) toggleCPDay(todayHeader);
});
</script>
