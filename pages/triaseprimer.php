<?php
// Asumsi $no_rawat sudah didefinisikan sebelumnya
// $no_rawat = $_GET['no_rawat'] atau dari sumber lain

// Query data master pemeriksaan
$sqlPemeriksaan = "SELECT * FROM master_triase_pemeriksaan ORDER BY kode_pemeriksaan ASC";
$resultPemeriksaan = bukaquery2($sqlPemeriksaan);

// Query master skala 1 dan 2
$sqlSkala1 = "SELECT * FROM master_triase_skala1 ORDER BY kode_pemeriksaan, kode_skala1 ASC";
$resultSkala1 = bukaquery2($sqlSkala1);

$sqlSkala2 = "SELECT * FROM master_triase_skala2 ORDER BY kode_pemeriksaan, kode_skala2 ASC";
$resultSkala2 = bukaquery2($sqlSkala2);

// ========================================
// QUERY DATA YANG SUDAH DIPILIH (TERCENTANG)
// ========================================
$sqlSelectedSkala1 = "
    SELECT d.*, m.pengkajian_skala1, m.kode_pemeriksaan
    FROM data_triase_igddetail_skala1 d
    JOIN master_triase_skala1 m ON d.kode_skala1 = m.kode_skala1
    WHERE d.no_rawat = '$no_rawat'
    ORDER BY m.kode_pemeriksaan, m.kode_skala1
";
$resultSelectedSkala1 = bukaquery2($sqlSelectedSkala1);

$sqlSelectedSkala2 = "
    SELECT d.*, m.pengkajian_skala2, m.kode_pemeriksaan
    FROM data_triase_igddetail_skala2 d
    JOIN master_triase_skala2 m ON d.kode_skala2 = m.kode_skala2
    WHERE d.no_rawat = '$no_rawat'
    ORDER BY m.kode_pemeriksaan, m.kode_skala2
";
$resultSelectedSkala2 = bukaquery2($sqlSelectedSkala2);

// Buat array untuk skala berdasarkan kode_pemeriksaan
$dataSkala1 = array();
while($rs1 = mysqli_fetch_array($resultSkala1)) {
    $dataSkala1[$rs1['kode_pemeriksaan']][] = $rs1;
}

$dataSkala2 = array();
while($rs2 = mysqli_fetch_array($resultSkala2)) {
    $dataSkala2[$rs2['kode_pemeriksaan']][] = $rs2;
}

// Buat array untuk data yang sudah dipilih
$selectedSkala1 = array();
while($sel1 = mysqli_fetch_array($resultSelectedSkala1)) {
    $selectedSkala1[] = $sel1['kode_skala1'];
}

$selectedSkala2 = array();
while($sel2 = mysqli_fetch_array($resultSelectedSkala2)) {
    $selectedSkala2[] = $sel2['kode_skala2'];
}
?>

<div class="section-card">
    <div class="section-title">Triase Primer</div>
    
    <!-- Row 1: Keluhan Utama (full width) -->
    <div class="form-row">
        <div class="form-group-modern">
            <label>Keluhan Utama</label>
            <textarea class="form-control-modern" name="keluhan_utama_primer" rows="4"
                    placeholder="Keluhan utama pasien..."><?php echo $dataPrimer['keluhan_utama']; ?></textarea>
        </div>
    </div>

    <!-- Row 2: Kebutuhan Khusus | Catatan (2 kolom) -->
    <div class="form-row" style="grid-template-columns: 1fr 2fr;">
        <div class="form-group-modern">
            <label>Kebutuhan Khusus</label>
            <select class="form-control-modern" name="kebutuhan_khusus">
                <option value="-" <?php echo $dataPrimer['kebutuhan_khusus']=='-'?'selected':''; ?>>-</option>
                <option value="UPPA" <?php echo $dataPrimer['kebutuhan_khusus']=='UPPA'?'selected':''; ?>>UPPA</option>
                <option value="Airborne" <?php echo $dataPrimer['kebutuhan_khusus']=='Airborne'?'selected':''; ?>>Airborne</option>
                <option value="Dekontaminan" <?php echo $dataPrimer['kebutuhan_khusus']=='Dekontaminan'?'selected':''; ?>>Dekontaminan</option>
            </select>
        </div>
        <div class="form-group-modern">
            <label>Catatan</label>
            <input type="text" class="form-control-modern" name="catatan_primer" value="<?php echo $dataPrimer['catatan']; ?>"
                    placeholder="Catatan tambahan...">
        </div>
    </div>

    <!-- PEMERIKSAAN TRIASE PRIMER -->
    <div class="form-row">
        <div class="form-group-modern">
            <!-- Container Pemeriksaan dengan 2 Kolom -->
            <div style="display: flex; gap: 20px;">
                
                <!-- KOLOM KIRI: List Pemeriksaan -->
                <div style="width: 250px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                    <!-- Header -->
                    <div style="background: #f5f5f5; padding: 10px 15px; border-bottom: 1px solid #e0e0e0;">
                        <strong style="font-size: 13px; color: #555;">Pemeriksaan</strong>
                    </div>
                    
                    <!-- List Pemeriksaan -->
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php
                        mysqli_data_seek($resultPemeriksaan, 0);
                        $first = true;
                        while($rsPemeriksaan = mysqli_fetch_array($resultPemeriksaan)) {
                            $kode = $rsPemeriksaan['kode_pemeriksaan'];
                            $nama = $rsPemeriksaan['nama_pemeriksaan'];
                            $activeClass = $first ? 'active' : '';
                            ?>
                            <div class="pemeriksaan-item <?php echo $activeClass; ?>" 
                                data-kode="<?php echo $kode; ?>"
                                style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: all 0.2s ease; font-size: 13px;">
                                <?php echo $nama; ?>
                            </div>
                            <?php
                            $first = false;
                        }
                        ?>
                    </div>
                </div>
                
                <!-- KOLOM KANAN: Tab Skala 1 dan Skala 2 -->
                <div style="flex: 1; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                    <!-- Tab Header Skala -->
                    <div style="display: flex; border-bottom: 1px solid #e0e0e0; background: #f9f9f9;">
                        <button type="button" class="skala-tab active" data-skala="1"
                                style="flex: 1; padding: 10px; border: none; background: #fff; cursor: pointer; font-weight: 600; font-size: 13px; color: #d32f2f; border-bottom: 3px solid #d32f2f; transition: all 0.2s ease;">
                            Skala 1
                        </button>
                        <button type="button" class="skala-tab" data-skala="2"
                                style="flex: 1; padding: 10px; border: none; background: transparent; cursor: pointer; font-weight: 600; font-size: 13px; color: #666; border-bottom: 3px solid transparent; transition: all 0.2s ease;">
                            Skala 2
                        </button>
                    </div>
                    
                    <!-- Content Skala 1 -->
                    <div id="skala-content-1" class="skala-content active" style="padding: 15px; max-height: 400px; overflow-y: auto; display: block;">
                        <!-- Area untuk checkbox yang SUDAH DICENTANG -->
                        <div id="selected-skala1-items">
                            <!-- Item checked akan dipindah ke sini oleh JavaScript -->
                        </div>
                        
                        <!-- Divider -->
                        <div id="divider-skala1" style="display: none; border-bottom: 2px solid #e0e0e0; margin: 15px 0; position: relative;">
                            <span style="position: absolute; top: -10px; left: 10px; background: white; padding: 0 10px; font-size: 11px; color: #999; font-weight: 600;">PILIHAN TERSEDIA</span>
                        </div>
                        
                        <!-- Area untuk checkbox yang BELUM DICENTANG -->
                        <div id="available-skala1-items">
                            <?php
                            // Load skala 1 dari pemeriksaan pertama
                            mysqli_data_seek($resultPemeriksaan, 0);
                            $firstPemeriksaan = mysqli_fetch_array($resultPemeriksaan);
                            $firstKode = $firstPemeriksaan['kode_pemeriksaan'];
                            
                            if(isset($dataSkala1[$firstKode]) && count($dataSkala1[$firstKode]) > 0) {
                                foreach($dataSkala1[$firstKode] as $skala) {
                                    // Cek apakah skala ini sudah dipilih
                                    $isChecked = in_array($skala['kode_skala1'], $selectedSkala1);
                                    ?>
                                    <div class="skala-item" data-skala-id="s1-<?php echo $skala['kode_skala1']; ?>" data-pemeriksaan="<?php echo $firstPemeriksaan['nama_pemeriksaan']; ?>" 
                                         style="padding: 10px; background: #ffceceff; border-left: 3px solid #ff5252; margin-bottom: 10px; border-radius: 4px;">
                                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                                            <input type="checkbox" 
                                                class="skala-checkbox" 
                                                name="skala1_check[]" 
                                                value="<?php echo $skala['kode_skala1']; ?>"
                                                data-text="<?php echo htmlspecialchars($skala['pengkajian_skala1']); ?>"
                                                data-pemeriksaan="<?php echo $firstPemeriksaan['nama_pemeriksaan']; ?>"
                                                onchange="handleCheckboxChange(this, 1)"
                                                <?php echo $isChecked ? 'checked' : ''; ?>
                                                style="margin-top: 3px; width: 16px; height: 16px; cursor: pointer;">
                                            <div style="flex: 1;">
                                                <div style="font-size: 13px; color: #d32f2f; line-height: 1.5; font-weight: 600; text-transform: uppercase;">
                                                    <?php echo $skala['pengkajian_skala1']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                echo '<div style="text-align: center; padding: 40px; color: #999;">Tidak ada data skala 1</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Content Skala 2 -->
                    <div id="skala-content-2" class="skala-content" style="padding: 15px; max-height: 400px; overflow-y: auto; display: none;">
                        <!-- Area untuk checkbox yang SUDAH DICENTANG -->
                        <div id="selected-skala2-items">
                            <!-- Item checked akan dipindah ke sini oleh JavaScript -->
                        </div>
                        
                        <!-- Divider -->
                        <div id="divider-skala2" style="display: none; border-bottom: 2px solid #e0e0e0; margin: 15px 0; position: relative;">
                            <span style="position: absolute; top: -10px; left: 10px; background: white; padding: 0 10px; font-size: 11px; color: #999; font-weight: 600;">PILIHAN TERSEDIA</span>
                        </div>
                        
                        <!-- Area untuk checkbox yang BELUM DICENTANG -->
                        <div id="available-skala2-items">
                            <?php
                            if(isset($dataSkala2[$firstKode]) && count($dataSkala2[$firstKode]) > 0) {
                                foreach($dataSkala2[$firstKode] as $skala) {
                                    // Cek apakah skala ini sudah dipilih
                                    $isChecked = in_array($skala['kode_skala2'], $selectedSkala2);
                                    ?>
                                    <div class="skala-item" data-skala-id="s2-<?php echo $skala['kode_skala2']; ?>" data-pemeriksaan="<?php echo $firstPemeriksaan['nama_pemeriksaan']; ?>" 
                                         style="padding: 10px; background: #ffb8b8ff; border-left: 3px solid #ff5252; margin-bottom: 10px; border-radius: 4px;">
                                        <div style="display: flex; align-items: flex-start; gap: 10px;">
                                            <input type="checkbox" 
                                                class="skala-checkbox"
                                                name="skala2_check[]" 
                                                value="<?php echo $skala['kode_skala2']; ?>"
                                                data-text="<?php echo htmlspecialchars($skala['pengkajian_skala2']); ?>"
                                                data-pemeriksaan="<?php echo $firstPemeriksaan['nama_pemeriksaan']; ?>"
                                                onchange="handleCheckboxChange(this, 2)"
                                                <?php echo $isChecked ? 'checked' : ''; ?>
                                                style="margin-top: 3px; width: 16px; height: 16px; cursor: pointer;">
                                            <div style="flex: 1;">
                                                <div style="font-size: 13px; color: ##ff5252; line-height: 1.5; font-weight: 600; text-transform: uppercase;">
                                                    <?php echo $skala['pengkajian_skala2']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } else {
                                echo '<div style="text-align: center; padding: 40px; color: #999;">Tidak ada data skala 2</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Plan/Keputusan | Tanggal Triase -->
    <div class="form-row" style="grid-template-columns: repeat(2, 1fr);">
        <div class="form-group-modern">
            <label style="color: #d32f2f; font-weight: 600;">Plan/Keputusan</label>
            <select class="form-control-modern" name="plan_primer" 
                    style="background: #d32f2f; color: white; border-color: #d32f2f; font-weight: 600;">
                <option value="Ruang Resusitasi" <?php echo $dataPrimer['plan']=='Ruang Resusitasi'?'selected':''; ?>>Ruang Resusitasi</option>
                <option value="Ruang Kritis" <?php echo $dataPrimer['plan']=='Ruang Kritis'?'selected':''; ?>>Ruang Kritis</option>
            </select>
        </div>
        <div class="form-group-modern">
            <label style="color: #d32f2f; font-weight: 600;">Tanggal Triase</label>
            <input type="datetime-local" class="form-control-modern" name="tanggaltriase_primer" 
                style="border-color: #d32f2f;"
                value="<?php echo date('Y-m-d\TH:i', strtotime($dataPrimer['tanggaltriase'])); ?>">
        </div>
    </div>
    
    <!-- Hidden NIK Petugas -->
    <input type="hidden" name="nik_primer" value="<?php echo $dataPrimer['nik']; ?>">
</div>

<!-- Script untuk define variables sudah dipindah ke triaseigd.php -->