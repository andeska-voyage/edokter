<?php
/**
 * Class Jurnal - Untuk pembuatan jurnal otomatis
 * Berdasarkan logic dari SIMRS Khanza
 * Modified for E-Dokter System
 */
class Jurnal {
    
    private $conn;
    private $rek = [];
    
    public $last_no_jurnal = '';
    public $last_error = '';
    
    public function __construct() {
        // Gunakan koneksi global dari conf.php
        global $con;
        $this->conn = $con;
        
        // Load kode rekening dari tabel set_akun_ralan
        $this->loadKodeRekening();
    }
    
    /**
     * Load kode rekening dari tabel set_akun_ralan
     * Hanya ambil yang diperlukan untuk tindakan ralan
     */
    private function loadKodeRekening() {
        // Query untuk ambil kode rekening
        $query = "SELECT 
                    Suspen_Piutang_Tindakan_Ralan,
                    Tindakan_Ralan,
                    Beban_Jasa_Medik_Dokter_Tindakan_Ralan,
                    Utang_Jasa_Medik_Dokter_Tindakan_Ralan,
                    Beban_KSO_Tindakan_Ralan,
                    Utang_KSO_Tindakan_Ralan,
                    Beban_Jasa_Sarana_Tindakan_Ralan,
                    Utang_Jasa_Sarana_Tindakan_Ralan,
                    Beban_Jasa_Menejemen_Tindakan_Ralan,
                    Utang_Jasa_Menejemen_Tindakan_Ralan,
                    HPP_BHP_Tindakan_Ralan,
                    Persediaan_BHP_Tindakan_Ralan
                FROM set_akun_ralan
                LIMIT 1";
        
        $result = bukaquery($query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            // ERROR jika tabel kosong atau tidak ada data
            $this->last_error = 'Kode rekening tidak ditemukan di tabel set_akun_ralan. Silakan setup master akun terlebih dahulu.';
            error_log("ERROR: Tabel set_akun_ralan kosong atau tidak ada data");
            return;
        }
        
        $row = mysqli_fetch_assoc($result);
        
        // Validasi: pastikan semua kode rekening tidak NULL
        $required_fields = [
            'Suspen_Piutang_Tindakan_Ralan',
            'Tindakan_Ralan',
            'Beban_Jasa_Medik_Dokter_Tindakan_Ralan',
            'Utang_Jasa_Medik_Dokter_Tindakan_Ralan',
            'Beban_KSO_Tindakan_Ralan',
            'Utang_KSO_Tindakan_Ralan',
            'Beban_Jasa_Sarana_Tindakan_Ralan',
            'Utang_Jasa_Sarana_Tindakan_Ralan',
            'Beban_Jasa_Menejemen_Tindakan_Ralan',
            'Utang_Jasa_Menejemen_Tindakan_Ralan',
            'HPP_BHP_Tindakan_Ralan',
            'Persediaan_BHP_Tindakan_Ralan'
        ];
        
        $missing_fields = [];
        foreach ($required_fields as $field) {
            if (empty($row[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (count($missing_fields) > 0) {
            $this->last_error = 'Kode rekening tidak lengkap di set_akun_ralan: ' . implode(', ', $missing_fields);
            error_log("ERROR: Kode rekening tidak lengkap: " . implode(', ', $missing_fields));
            return;
        }
        
        // Simpan ke array dengan key lowercase dan underscore
        $this->rek['suspen_piutang_tindakan_ralan'] = $row['Suspen_Piutang_Tindakan_Ralan'];
        $this->rek['tindakan_ralan'] = $row['Tindakan_Ralan'];
        $this->rek['beban_jasa_medik_dokter_tindakan_ralan'] = $row['Beban_Jasa_Medik_Dokter_Tindakan_Ralan'];
        $this->rek['utang_jasa_medik_dokter_tindakan_ralan'] = $row['Utang_Jasa_Medik_Dokter_Tindakan_Ralan'];
        $this->rek['beban_kso_tindakan_ralan'] = $row['Beban_KSO_Tindakan_Ralan'];
        $this->rek['utang_kso_tindakan_ralan'] = $row['Utang_KSO_Tindakan_Ralan'];
        $this->rek['beban_jasa_sarana_tindakan_ralan'] = $row['Beban_Jasa_Sarana_Tindakan_Ralan'];
        $this->rek['utang_jasa_sarana_tindakan_ralan'] = $row['Utang_Jasa_Sarana_Tindakan_Ralan'];
        $this->rek['beban_jasa_menejemen_tindakan_ralan'] = $row['Beban_Jasa_Menejemen_Tindakan_Ralan'];
        $this->rek['utang_jasa_menejemen_tindakan_ralan'] = $row['Utang_Jasa_Menejemen_Tindakan_Ralan'];
        $this->rek['hpp_bhp_tindakan_ralan'] = $row['HPP_BHP_Tindakan_Ralan'];
        $this->rek['persediaan_bhp_tindakan_ralan'] = $row['Persediaan_BHP_Tindakan_Ralan'];
    }
    
    /**
     * Simpan jurnal tindakan rawat jalan
     * 
     * @param string $no_rawat
     * @param string $kd_jenis_prw
     * @param string $kd_dokter
     * @param string $jenis (U = insert, D = delete/pembatalan)
     * @return bool
     */
    public function simpanJurnalTindakan($no_rawat, $kd_jenis_prw, $kd_dokter, $jenis = 'U') {
        try {
            // Cek apakah kode rekening sudah loaded
            if (empty($this->rek)) {
                $this->last_error = 'Kode rekening belum di-load dari set_akun_ralan';
                return false;
            }
            
            // Ambil data tindakan
            $query = "SELECT 
                        r.material,
                        r.bhp,
                        r.tarif_tindakandr,
                        r.kso,
                        r.menejemen,
                        r.biaya_rawat,
                        p.nm_pasien,
                        rp.no_rkm_medis,
                        j.nm_perawatan
                      FROM rawat_jl_dr r
                      INNER JOIN reg_periksa rp ON r.no_rawat = rp.no_rawat
                      INNER JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                      INNER JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw
                      WHERE r.no_rawat = '$no_rawat'
                      AND r.kd_jenis_prw = '$kd_jenis_prw'
                      AND r.kd_dokter = '$kd_dokter'
                      ORDER BY r.tgl_perawatan DESC, r.jam_rawat DESC
                      LIMIT 1";
            
            $result = bukaquery($query);
            
            if (mysqli_num_rows($result) == 0) {
                $this->last_error = 'Data tindakan tidak ditemukan';
                return false;
            }
            
            $data = mysqli_fetch_assoc($result);
            
            // Hitung total per komponen
            $ttl_pendapatan = $data['biaya_rawat'] ?? 0;
            $ttl_jm_dokter = $data['tarif_tindakandr'] ?? 0;
            $ttl_kso = $data['kso'] ?? 0;
            $ttl_menejemen = $data['menejemen'] ?? 0;
            $ttl_jasa_sarana = $data['material'] ?? 0;
            $ttl_bhp = $data['bhp'] ?? 0;
            
            // 1. CLEAR tampjurnal
            $this->clearTampJurnal();
            
            // 2. INSERT ke tampjurnal
            // PENTING: Jika jenis = 'D' (delete/pembatalan), BALIK posisi debet-kredit
            $sukses = true;
            
            // ===================================================
            // UNTUK PEMBATALAN (D), SEMUA POSISI DIBALIK
            // ===================================================
            
            if ($jenis == 'D') {
                // JURNAL PEMBALIK (Reverse Entry)
                
                // Pendapatan Tindakan - DIBALIK
                if ($ttl_pendapatan > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['tindakan_ralan'],
                        'Pendapatan Tindakan Rawat Jalan',
                        $ttl_pendapatan,  // DIBALIK: jadi Debet
                        0
                    )) $sukses = false;
                    
                    if (!$this->insertTampJurnal(
                        $this->rek['suspen_piutang_tindakan_ralan'],
                        'Suspen Piutang Tindakan Ralan',
                        0,
                        $ttl_pendapatan  // DIBALIK: jadi Kredit
                    )) $sukses = false;
                }
                
                // Jasa Medik Dokter - DIBALIK
                if ($ttl_jm_dokter > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['utang_jasa_medik_dokter_tindakan_ralan'],
                        'Utang Jasa Medik Dokter Tindakan Ralan',
                        0,
                        $ttl_jm_dokter  // DIBALIK: jadi Kredit
                    )) $sukses = false;
                    
                    if (!$this->insertTampJurnal(
                        $this->rek['beban_jasa_medik_dokter_tindakan_ralan'],
                        'Beban Jasa Medik Dokter Tindakan Ralan',
                        $ttl_jm_dokter,  // DIBALIK: jadi Debet
                        0
                    )) $sukses = false;
                }
                
                // KSO - DIBALIK
                if ($ttl_kso > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['utang_kso_tindakan_ralan'],
                        'Utang KSO Tindakan Ralan',
                        0,
                        $ttl_kso  // DIBALIK
                    )) $sukses = false;
                    
                    if (!$this->insertTampJurnal(
                        $this->rek['beban_kso_tindakan_ralan'],
                        'Beban KSO Tindakan Ralan',
                        $ttl_kso,  // DIBALIK
                        0
                    )) $sukses = false;
                }
                
                // Menejemen - DIBALIK
                if ($ttl_menejemen > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['utang_jasa_menejemen_tindakan_ralan'],
                        'Utang Jasa Menejemen Tindakan Ralan',
                        0,
                        $ttl_menejemen  // DIBALIK
                    )) $sukses = false;
                    
                    if (!$this->insertTampJurnal(
                        $this->rek['beban_jasa_menejemen_tindakan_ralan'],
                        'Beban Jasa Menejemen Tindakan Ralan',
                        $ttl_menejemen,  // DIBALIK
                        0
                    )) $sukses = false;
                }
                
                // Jasa Sarana - DIBALIK
                if ($ttl_jasa_sarana > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['utang_jasa_sarana_tindakan_ralan'],
                        'Utang Jasa Sarana Tindakan Ralan',
                        0,
                        $ttl_jasa_sarana  // DIBALIK
                    )) $sukses = false;
                    
                    if (!$this->insertTampJurnal(
                        $this->rek['beban_jasa_sarana_tindakan_ralan'],
                        'Beban Jasa Sarana Tindakan Ralan',
                        $ttl_jasa_sarana,  // DIBALIK
                        0
                    )) $sukses = false;
                }
                
                // BHP - DIBALIK
                if ($ttl_bhp > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['persediaan_bhp_tindakan_ralan'],
                        'Persediaan BHP Tindakan Ralan',
                        0,
                        $ttl_bhp  // DIBALIK
                    )) $sukses = false;
                    
                    if (!$this->insertTampJurnal(
                        $this->rek['hpp_bhp_tindakan_ralan'],
                        'HPP BHP Tindakan Ralan',
                        $ttl_bhp,  // DIBALIK
                        0
                    )) $sukses = false;
                }
                
            } else {
                // JURNAL NORMAL (Insert)
                
                // 1. Suspen Piutang (Debet)
                if ($ttl_pendapatan > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['suspen_piutang_tindakan_ralan'],
                        'Suspen Piutang Tindakan Ralan',
                        $ttl_pendapatan,
                        0
                    )) $sukses = false;
                }
                
                // 2. Beban Jasa Medik Dokter (Debet)
                if ($ttl_jm_dokter > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['beban_jasa_medik_dokter_tindakan_ralan'],
                        'Beban Jasa Medik Dokter Tindakan Ralan',
                        $ttl_jm_dokter,
                        0
                    )) $sukses = false;
                }
                
                // 3. Beban Jasa Sarana (Debet)
                if ($ttl_jasa_sarana > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['beban_jasa_sarana_tindakan_ralan'],
                        'Beban Jasa Sarana Tindakan Ralan',
                        $ttl_jasa_sarana,
                        0
                    )) $sukses = false;
                }
                
                // 4. Beban KSO (Debet)
                if ($ttl_kso > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['beban_kso_tindakan_ralan'],
                        'Beban KSO Tindakan Ralan',
                        $ttl_kso,
                        0
                    )) $sukses = false;
                }
                
                // 5. Beban Jasa Menejemen (Debet)
                if ($ttl_menejemen > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['beban_jasa_menejemen_tindakan_ralan'],
                        'Beban Jasa Menejemen Tindakan Ralan',
                        $ttl_menejemen,
                        0
                    )) $sukses = false;
                }
                
                // 6. HPP BHP (Debet)
                if ($ttl_bhp > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['hpp_bhp_tindakan_ralan'],
                        'HPP BHP Tindakan Ralan',
                        $ttl_bhp,
                        0
                    )) $sukses = false;
                }
                
                // ========================================
                // BAGIAN 2: SEMUA KREDIT (Pendapatan, Utang, Persediaan naik)
                // ========================================
                
                // 7. Utang Jasa Menejemen (Kredit)
                if ($ttl_menejemen > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['utang_jasa_menejemen_tindakan_ralan'],
                        'Utang Jasa Menejemen Tindakan Ralan',
                        0,
                        $ttl_menejemen
                    )) $sukses = false;
                }
                
                // 8. Utang KSO (Kredit)
                if ($ttl_kso > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['utang_kso_tindakan_ralan'],
                        'Utang KSO Tindakan Ralan',
                        0,
                        $ttl_kso
                    )) $sukses = false;
                }
                
                // 9. Utang Jasa Sarana (Kredit)
                if ($ttl_jasa_sarana > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['utang_jasa_sarana_tindakan_ralan'],
                        'Utang Jasa Sarana Tindakan Ralan',
                        0,
                        $ttl_jasa_sarana
                    )) $sukses = false;
                }
                
                // 10. Pendapatan Tindakan (Kredit)
                if ($ttl_pendapatan > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['tindakan_ralan'],
                        'Pendapatan Tindakan Rawat Jalan',
                        0,
                        $ttl_pendapatan
                    )) $sukses = false;
                }
                
                // 11. Persediaan BHP (Kredit)
                if ($ttl_bhp > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['persediaan_bhp_tindakan_ralan'],
                        'Persediaan BHP Tindakan Ralan',
                        0,
                        $ttl_bhp
                    )) $sukses = false;
                }
                
                // 12. Utang Jasa Medik Dokter (Kredit) - PALING AKHIR
                if ($ttl_jm_dokter > 0) {
                    if (!$this->insertTampJurnal(
                        $this->rek['utang_jasa_medik_dokter_tindakan_ralan'],
                        'Utang Jasa Medik Dokter Tindakan Ralan',
                        0,
                        $ttl_jm_dokter
                    )) $sukses = false;
                }
            }
            
            // 3. Simpan ke jurnal jika sukses
            if ($sukses) {
                // AMBIL NAMA DOKTER UNTUK KETERANGAN
                $query_dokter = "SELECT nm_dokter FROM dokter WHERE kd_dokter = '$kd_dokter' LIMIT 1";
                $result_dokter = bukaquery($query_dokter);
                $row_dokter = mysqli_fetch_assoc($result_dokter);
                $nm_dokter = $row_dokter['nm_dokter'];
                
                $keterangan_jenis = ($jenis == 'U') ? 'TINDAKAN' : 'PEMBATALAN TINDAKAN';
                $keterangan = "$keterangan_jenis RAWAT JALAN PASIEN {$data['no_rkm_medis']} {$data['nm_pasien']}, DIPOSTING OLEH $nm_dokter - EDOKTER";
                
                return $this->simpanJurnal($no_rawat, $jenis, $keterangan);
            }
            
            $this->last_error = 'Gagal insert ke tampjurnal';
            return false;
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Error simpanJurnalTindakan: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear tabel tampjurnal
     */
    private function clearTampJurnal() {
        bukaquery("DELETE FROM tampjurnal");
    }
    
    /**
     * Insert ke tabel tampjurnal dengan upsert logic
     */
    private function insertTampJurnal($kd_rek, $nm_rek, $debet, $kredit) {
        $query = "INSERT INTO tampjurnal (kd_rek, nm_rek, debet, kredit) 
                  VALUES ('$kd_rek', '$nm_rek', '$debet', '$kredit')
                  ON DUPLICATE KEY UPDATE 
                  debet = debet + VALUES(debet),
                  kredit = kredit + VALUES(kredit)";
        
        return bukaquery($query) ? true : false;
    }
    
    /**
     * Simpan ke tabel jurnal dan detailjurnal
     * TIDAK MASUK KE TRACKER
     */
    private function simpanJurnal($no_bukti, $jenis, $keterangan) {
        try {
            // Cek total debet dan kredit dari tampjurnal
            $query_cek = "SELECT 
                            COUNT(*) as jml,
                            CURDATE() as tanggal,
                            CURTIME() as jam,
                            SUM(debet) as total_debet,
                            SUM(kredit) as total_kredit
                          FROM tampjurnal";
            
            $result_cek = bukaquery($query_cek);
            $cek = mysqli_fetch_assoc($result_cek);
            
            if ($cek['jml'] == 0) {
                $this->last_error = 'Tidak ada data di tampjurnal';
                return false;
            }
            
            // Validasi debet = kredit
            if ($cek['total_debet'] != $cek['total_kredit']) {
                $this->last_error = "Debet ({$cek['total_debet']}) dan Kredit ({$cek['total_kredit']}) tidak sama";
                return false;
            }
            
            // Generate no_jurnal
            $tanggal = $cek['tanggal'];
            $jam = $cek['jam'];
            $no_jurnal = $this->generateNoJurnal($tanggal);
            
            // Insert ke tabel jurnal (TIDAK PAKAI insertTracker)
            $query_jurnal = "INSERT INTO jurnal (no_jurnal, no_bukti, tgl_jurnal, jam_jurnal, jenis, keterangan)
                             VALUES ('$no_jurnal', '$no_bukti', '$tanggal', '$jam', '$jenis', '$keterangan')";
            
            if (!bukaquery($query_jurnal)) {
                // Retry dengan no_jurnal baru
                $no_jurnal = $this->generateNoJurnal($tanggal);
                $query_jurnal = "INSERT INTO jurnal (no_jurnal, no_bukti, tgl_jurnal, jam_jurnal, jenis, keterangan)
                                 VALUES ('$no_jurnal', '$no_bukti', '$tanggal', '$jam', '$jenis', '$keterangan')";
                
                if (!bukaquery($query_jurnal)) {
                    $this->last_error = 'Gagal insert ke tabel jurnal';
                    return false;
                }
            }
            
            // Insert ke tabel detailjurnal (TIDAK PAKAI insertTracker)
            $query_detail = "INSERT INTO detailjurnal (no_jurnal, kd_rek, debet, kredit)
                             SELECT '$no_jurnal', kd_rek, debet, kredit
                             FROM tampjurnal";
            
            if (!bukaquery($query_detail)) {
                // Rollback jurnal
                bukaquery("DELETE FROM jurnal WHERE no_jurnal = '$no_jurnal'");
                $this->last_error = 'Gagal insert ke tabel detailjurnal';
                return false;
            }
            
            // Validasi ulang debet = kredit di detailjurnal
            $query_validasi = "SELECT SUM(debet) as debet, SUM(kredit) as kredit
                               FROM detailjurnal
                               WHERE no_jurnal = '$no_jurnal'";
            
            $result_validasi = bukaquery($query_validasi);
            $validasi = mysqli_fetch_assoc($result_validasi);
            
            if ($validasi['debet'] != $validasi['kredit']) {
                // Rollback
                bukaquery("DELETE FROM detailjurnal WHERE no_jurnal = '$no_jurnal'");
                bukaquery("DELETE FROM jurnal WHERE no_jurnal = '$no_jurnal'");
                $this->last_error = 'Validasi akhir gagal: Debet dan Kredit tidak sama';
                return false;
            }
            
            // Clear tampjurnal
            $this->clearTampJurnal();
            
            // Simpan no_jurnal untuk info
            $this->last_no_jurnal = $no_jurnal;
            
            return true;
            
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            error_log("Error simpanJurnal: " . $e->getMessage());
            $this->clearTampJurnal();
            return false;
        }
    }
    
    /**
     * Generate nomor jurnal otomatis
     * Format: JR[YYYYMMDD][NNNNNN]
     */
    private function generateNoJurnal($tanggal) {
        $prefix = "JR" . str_replace('-', '', $tanggal);
        
        $query = "SELECT IFNULL(MAX(CONVERT(RIGHT(no_jurnal, 6), SIGNED)), 0) as max_no
                  FROM jurnal
                  WHERE tgl_jurnal = '$tanggal'";
        
        $result = bukaquery($query);
        $row = mysqli_fetch_assoc($result);
        
        $urut = $row['max_no'] + 1;
        $no_jurnal = $prefix . str_pad($urut, 6, '0', STR_PAD_LEFT);
        
        return $no_jurnal;
    }
}
?>