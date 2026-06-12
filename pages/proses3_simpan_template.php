// ========================================
// SIMPAN RESEP SEBAGAI TEMPLATE
// Tambahkan kode ini di AKHIR file proses3.php (sebelum baris terakhir "// Jika aksi tidak dikenali")
// ========================================

if ($aksi === 'simpan_resep_sebagai_template') {
    
    try {
        // Validasi input
        $no_resep = isset($_POST['no_resep']) ? validTeks4($_POST['no_resep'], 20) : '';
        $nama_template = isset($_POST['nama_template']) ? validTeks4($_POST['nama_template'], 100) : '';
        
        if (empty($no_resep)) {
            throw new Exception('No. Resep tidak valid');
        }
        
        if (empty($nama_template)) {
            throw new Exception('Nama template harus diisi');
        }
        
        // Ambil kd_dokter dari session
        $kd_dokter = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
        
        if (empty($kd_dokter)) {
            throw new Exception('Session dokter tidak valid');
        }
        
        // Generate no_template otomatis
        // Format: TPL + YYYYMMDD + 5 digit urutan
        $tanggal = date('Ymd');
        
        // Cari nomor urut terakhir hari ini
        $queryLastNo = "SELECT no_template FROM template_pemeriksaan_dokter 
                        WHERE no_template LIKE 'TPL{$tanggal}%' 
                        ORDER BY no_template DESC LIMIT 1";
        $resultLastNo = bukaquery($queryLastNo);
        
        if ($resultLastNo && mysqli_num_rows($resultLastNo) > 0) {
            $rowLast = mysqli_fetch_assoc($resultLastNo);
            $lastNo = $rowLast['no_template'];
            // Ambil 5 digit terakhir dan increment
            $lastUrut = (int)substr($lastNo, -5);
            $newUrut = $lastUrut + 1;
        } else {
            $newUrut = 1;
        }
        
        $no_template = 'TPL' . $tanggal . str_pad($newUrut, 5, '0', STR_PAD_LEFT);
        
        // Cek apakah no_template sudah ada (untuk keamanan)
        $cekExist = bukaquery("SELECT no_template FROM template_pemeriksaan_dokter WHERE no_template = '$no_template'");
        if ($cekExist && mysqli_num_rows($cekExist) > 0) {
            // Jika sudah ada, generate ulang dengan timestamp
            $no_template = 'TPL' . date('YmdHis') . rand(10, 99);
        }
        
        // ========================================
        // AMBIL DATA RESEP NON RACIKAN
        // ========================================
        $queryObatNR = "SELECT rd.kode_brng, rd.jml, rd.aturan_pakai
                        FROM resep_dokter rd
                        WHERE rd.no_resep = '$no_resep'";
        $resultObatNR = bukaquery($queryObatNR);
        
        $obatNonRacikan = [];
        if ($resultObatNR && mysqli_num_rows($resultObatNR) > 0) {
            while ($row = mysqli_fetch_assoc($resultObatNR)) {
                $obatNonRacikan[] = $row;
            }
        }
        
        // ========================================
        // AMBIL DATA RESEP RACIKAN (HEADER + DETAIL)
        // ========================================
        $queryRacikan = "SELECT rdr.no_resep, rdr.no_racik, rdr.nama_racik, rdr.kd_racik, 
                                rdr.jml_dr, rdr.aturan_pakai, rdr.keterangan
                         FROM resep_dokter_racikan rdr
                         WHERE rdr.no_resep = '$no_resep'";
        $resultRacikan = bukaquery($queryRacikan);
        
        $obatRacikan = [];
        if ($resultRacikan && mysqli_num_rows($resultRacikan) > 0) {
            while ($rowRacikan = mysqli_fetch_assoc($resultRacikan)) {
                // Ambil detail komposisi racikan
                $noRacik = $rowRacikan['no_racik'];
                $queryDetail = "SELECT rdrd.kode_brng, rdrd.p1, rdrd.p2, rdrd.kandungan, rdrd.jml
                                FROM resep_dokter_racikan_detail rdrd
                                WHERE rdrd.no_resep = '$no_resep' AND rdrd.no_racik = '$noRacik'";
                $resultDetail = bukaquery($queryDetail);
                
                $komposisi = [];
                if ($resultDetail && mysqli_num_rows($resultDetail) > 0) {
                    while ($rowDetail = mysqli_fetch_assoc($resultDetail)) {
                        $komposisi[] = $rowDetail;
                    }
                }
                
                $rowRacikan['komposisi'] = $komposisi;
                $obatRacikan[] = $rowRacikan;
            }
        }
        
        // Validasi: minimal ada 1 obat
        if (count($obatNonRacikan) === 0 && count($obatRacikan) === 0) {
            throw new Exception('Tidak ada obat yang ditemukan di resep ini');
        }
        
        // ========================================
        // INSERT HEADER TEMPLATE
        // ========================================
        $queryInsertHeader = "INSERT INTO template_pemeriksaan_dokter 
                              (no_template, kd_dokter, keluhan, penilaian) 
                              VALUES 
                              ('$no_template', '$kd_dokter', '-', '$nama_template')";
        
        $resultHeader = bukaquery($queryInsertHeader);
        
        if (!$resultHeader) {
            throw new Exception('Gagal menyimpan header template');
        }
        
        insertTracker($queryInsertHeader);
        
        // ========================================
        // INSERT DETAIL TEMPLATE - OBAT NON RACIKAN
        // ========================================
        $countObat = 0;
        
        foreach ($obatNonRacikan as $obat) {
            $kode_brng = $obat['kode_brng'];
            $jml = $obat['jml'];
            $aturan_pakai = $obat['aturan_pakai'];
            
            $queryInsertDetail = "INSERT INTO template_pemeriksaan_dokter_resep 
                                  (no_template, kode_brng, jml, aturan_pakai) 
                                  VALUES 
                                  ('$no_template', '$kode_brng', '$jml', '$aturan_pakai')";
            
            $resultDetail = bukaquery($queryInsertDetail);
            
            if ($resultDetail) {
                insertTracker($queryInsertDetail);
                $countObat++;
            }
        }
        
        // ========================================
        // INSERT DETAIL TEMPLATE - OBAT RACIKAN
        // Untuk racikan, simpan setiap komposisi sebagai item terpisah
        // ========================================
        foreach ($obatRacikan as $racikan) {
            foreach ($racikan['komposisi'] as $komp) {
                $kode_brng = $komp['kode_brng'];
                $jml = $komp['jml'];
                // Untuk racikan, aturan pakai bisa diambil dari header racikan
                $aturan_pakai = $racikan['aturan_pakai'] . ' (' . $racikan['nama_racik'] . ')';
                
                $queryInsertDetail = "INSERT INTO template_pemeriksaan_dokter_resep 
                                      (no_template, kode_brng, jml, aturan_pakai) 
                                      VALUES 
                                      ('$no_template', '$kode_brng', '$jml', '$aturan_pakai')";
                
                $resultDetail = bukaquery($queryInsertDetail);
                
                if ($resultDetail) {
                    insertTracker($queryInsertDetail);
                    $countObat++;
                }
            }
        }
        
        // Response sukses
        echo json_encode([
            'status' => 'success',
            'message' => 'Template berhasil disimpan',
            'no_template' => $no_template,
            'nama_template' => $nama_template,
            'count_obat' => $countObat,
            'count_non_racikan' => count($obatNonRacikan),
            'count_racikan' => count($obatRacikan)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    
    exit();
}
