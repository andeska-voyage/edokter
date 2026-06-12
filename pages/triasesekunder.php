<?php
// Asumsi $no_rawat sudah didefinisikan sebelumnya

// Query master pemeriksaan untuk Triase Sekunder
$sqlPemeriksaanSek = "SELECT * FROM master_triase_pemeriksaan ORDER BY kode_pemeriksaan ASC";
$resultPemeriksaanSek = bukaquery2($sqlPemeriksaanSek);

// Query master skala 3, 4, 5
$querySkala3 = "SELECT * FROM master_triase_skala3 ORDER BY kode_pemeriksaan, kode_skala3";
$resultSkala3 = bukaquery2($querySkala3);

$querySkala4 = "SELECT * FROM master_triase_skala4 ORDER BY kode_pemeriksaan, kode_skala4";
$resultSkala4 = bukaquery2($querySkala4);

$querySkala5 = "SELECT * FROM master_triase_skala5 ORDER BY kode_pemeriksaan, kode_skala5";
$resultSkala5 = bukaquery2($querySkala5);

// ========================================
// QUERY DATA YANG SUDAH DIPILIH (TERCENTANG)
// ========================================
$sqlSelectedSkala3 = "
    SELECT d.*, m.pengkajian_skala3, m.kode_pemeriksaan
    FROM data_triase_igddetail_skala3 d
    JOIN master_triase_skala3 m ON d.kode_skala3 = m.kode_skala3
    WHERE d.no_rawat = '$no_rawat'
    ORDER BY m.kode_pemeriksaan, m.kode_skala3
";
$resultSelectedSkala3 = bukaquery2($sqlSelectedSkala3);

$sqlSelectedSkala4 = "
    SELECT d.*, m.pengkajian_skala4, m.kode_pemeriksaan
    FROM data_triase_igddetail_skala4 d
    JOIN master_triase_skala4 m ON d.kode_skala4 = m.kode_skala4
    WHERE d.no_rawat = '$no_rawat'
    ORDER BY m.kode_pemeriksaan, m.kode_skala4
";
$resultSelectedSkala4 = bukaquery2($sqlSelectedSkala4);

$sqlSelectedSkala5 = "
    SELECT d.*, m.pengkajian_skala5, m.kode_pemeriksaan
    FROM data_triase_igddetail_skala5 d
    JOIN master_triase_skala5 m ON d.kode_skala5 = m.kode_skala5
    WHERE d.no_rawat = '$no_rawat'
    ORDER BY m.kode_pemeriksaan, m.kode_skala5
";
$resultSelectedSkala5 = bukaquery2($sqlSelectedSkala5);

// Buat array untuk skala 3, 4, 5 berdasarkan kode_pemeriksaan
$dataSkala3 = array();
while($rs3 = mysqli_fetch_array($resultSkala3)) {
    $dataSkala3[$rs3['kode_pemeriksaan']][] = $rs3;
}

$dataSkala4 = array();
while($rs4 = mysqli_fetch_array($resultSkala4)) {
    $dataSkala4[$rs4['kode_pemeriksaan']][] = $rs4;
}

$dataSkala5 = array();
while($rs5 = mysqli_fetch_array($resultSkala5)) {
    $dataSkala5[$rs5['kode_pemeriksaan']][] = $rs5;
}

// Buat array untuk data yang sudah dipilih
$selectedSkala3 = array();
while($sel3 = mysqli_fetch_array($resultSelectedSkala3)) {
    $selectedSkala3[] = $sel3['kode_skala3'];
}

$selectedSkala4 = array();
while($sel4 = mysqli_fetch_array($resultSelectedSkala4)) {
    $selectedSkala4[] = $sel4['kode_skala4'];
}

$selectedSkala5 = array();
while($sel5 = mysqli_fetch_array($resultSelectedSkala5)) {
    $selectedSkala5[] = $sel5['kode_skala5'];
}
?>

<div class="section-card">
    <div class="section-title">Triase Sekunder</div>
    
    <!-- Row 1: Anamnesa Singkat | Catatan (2 kolom) -->
    <div class="form-row" style="grid-template-columns: repeat(2, 1fr);">
        <div class="form-group-modern">
            <label>Anamnesa Singkat *</label>
            <textarea class="form-control-modern" name="anamnesa_singkat" rows="4" required
                    placeholder="Jelaskan anamnesa singkat pasien..."><?php echo $dataSekunder['anamnesa_singkat']; ?></textarea>
        </div>
        <div class="form-group-modern">
            <label>Catatan</label>
            <textarea class="form-control-modern" name="catatan_sekunder" rows="4"
                    placeholder="Catatan tambahan..."><?php echo $dataSekunder['catatan']; ?></textarea>
        </div>
    </div>

    <!-- PEMERIKSAAN SEKUNDER -->
    <div class="form-row">
        <div class="form-group-modern">
            <!-- Container Pemeriksaan dengan 2 Kolom -->
            <div style="display: flex; gap: 20px;">
                
                <!-- KOLOM KIRI: List Pemeriksaan -->
                <div style="width: 250px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                    <!-- Header -->
                    <div style="background: #f5f5f5; padding: 10px 15px; border-bottom: 1px solid #e0e0e0;">
                        <strong style="font-size: 13px; color: #555;">Jenis Pemeriksaan</strong>
                    </div>
                    
                    <!-- List Pemeriksaan -->
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php
                        mysqli_data_seek($resultPemeriksaanSek, 0);
                        $firstSek = true;
                        while($rsPemeriksaan = mysqli_fetch_array($resultPemeriksaanSek)) {
                            $kode = $rsPemeriksaan['kode_pemeriksaan'];
                            $nama = $rsPemeriksaan['nama_pemeriksaan'];
                            $activeClass = $firstSek ? 'active' : '';
                            ?>
                            <div class="pemeriksaan-item-sekunder <?php echo $activeClass; ?>" 
                                data-kode="<?php echo $kode; ?>"
                                style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: all 0.2s ease; font-size: 13px;">
                                <?php echo $nama; ?>
                            </div>
                            <?php
                            $firstSek = false;
                        }
                        ?>
                    </div>
                </div>
                
                <!-- KOLOM KANAN: Tab Skala 3, 4, 5 -->
                <div style="flex: 1; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
                    <!-- Tab Header Skala -->
                    <div style="display: flex; border-bottom: 1px solid #e0e0e0; background: #f9f9f9;">
                        <button type="button" class="skala-tab-sekunder active" data-skala="3"
                                style="flex: 1; padding: 10px; border: none; background: #fff; cursor: pointer; font-weight: 600; font-size: 13px; color: #ff9800; border-bottom: 3px solid #ff9800; transition: all 0.2s ease;">
                            Skala 3
                        </button>
                        <button type="button" class="skala-tab-sekunder" data-skala="4"
                                style="flex: 1; padding: 10px; border: none; background: transparent; cursor: pointer; font-weight: 600; font-size: 13px; color: #666; border-bottom: 3px solid transparent; transition: all 0.2s ease;">
                            Skala 4
                        </button>
                        <button type="button" class="skala-tab-sekunder" data-skala="5"
                                style="flex: 1; padding: 10px; border: none; background: transparent; cursor: pointer; font-weight: 600; font-size: 13px; color: #666; border-bottom: 3px solid transparent; transition: all 0.2s ease;">
                            Skala 5
                        </button>
                    </div>
                    
                    <!-- Content Skala 3 -->
                    <div id="skala-content-3" class="skala-content-sekunder active" style="padding: 15px; max-height: 400px; overflow-y: auto; display: block;">
                        <!-- Area untuk checkbox yang SUDAH DICENTANG -->
                        <div id="selected-skala3-items">
                            <!-- Item checked akan dipindah ke sini oleh JavaScript -->
                        </div>
                        
                        <!-- Divider -->
                        <div id="divider-skala3" style="display: none; border-bottom: 2px solid #e0e0e0; margin: 15px 0; position: relative;">
                            <span style="position: absolute; top: -10px; left: 10px; background: white; padding: 0 10px; font-size: 11px; color: #999; font-weight: 600;">PILIHAN TERSEDIA</span>
                        </div>
                        
                        <!-- Area untuk checkbox yang BELUM DICENTANG -->
                        <div id="available-skala3-items">
                            <!-- Items will be loaded by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Content Skala 4 -->
                    <div id="skala-content-4" class="skala-content-sekunder" style="padding: 15px; max-height: 400px; overflow-y: auto; display: none;">
                        <!-- Area untuk checkbox yang SUDAH DICENTANG -->
                        <div id="selected-skala4-items">
                            <!-- Item checked akan dipindah ke sini oleh JavaScript -->
                        </div>
                        
                        <!-- Divider -->
                        <div id="divider-skala4" style="display: none; border-bottom: 2px solid #e0e0e0; margin: 15px 0; position: relative;">
                            <span style="position: absolute; top: -10px; left: 10px; background: white; padding: 0 10px; font-size: 11px; color: #999; font-weight: 600;">PILIHAN TERSEDIA</span>
                        </div>
                        
                        <!-- Area untuk checkbox yang BELUM DICENTANG -->
                        <div id="available-skala4-items">
                            <!-- Items will be loaded by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Content Skala 5 -->
                    <div id="skala-content-5" class="skala-content-sekunder" style="padding: 15px; max-height: 400px; overflow-y: auto; display: none;">
                        <!-- Area untuk checkbox yang SUDAH DICENTANG -->
                        <div id="selected-skala5-items">
                            <!-- Item checked akan dipindah ke sini oleh JavaScript -->
                        </div>
                        
                        <!-- Divider -->
                        <div id="divider-skala5" style="display: none; border-bottom: 2px solid #e0e0e0; margin: 15px 0; position: relative;">
                            <span style="position: absolute; top: -10px; left: 10px; background: white; padding: 0 10px; font-size: 11px; color: #999; font-weight: 600;">PILIHAN TERSEDIA</span>
                        </div>
                        
                        <!-- Area untuk checkbox yang BELUM DICENTANG -->
                        <div id="available-skala5-items">
                            <!-- Items will be loaded by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row: Plan/Keputusan | Tanggal Triase (2 kolom) -->
    <div class="form-row" style="grid-template-columns: repeat(2, 1fr);">
        <div class="form-group-modern">
            <label style="font-weight: 600;">Plan/Zona *</label>
            <select class="form-control-modern" name="plan_sekunder" id="plan_sekunder" required
                    style="background: <?php echo $dataSekunder['plan']=='Zona Hijau' ? '#4caf50' : '#ffeb3b'; ?>; 
                           color: <?php echo $dataSekunder['plan']=='Zona Hijau' ? 'white' : '#333'; ?>; 
                           border-color: <?php echo $dataSekunder['plan']=='Zona Hijau' ? '#4caf50' : '#ffeb3b'; ?>; 
                           font-weight: 600;">
                <option value="Zona Kuning" <?php echo $dataSekunder['plan']=='Zona Kuning'?'selected':''; ?>>Zona Kuning</option>
                <option value="Zona Hijau" <?php echo $dataSekunder['plan']=='Zona Hijau'?'selected':''; ?>>Zona Hijau</option>
            </select>
        </div>
        <div class="form-group-modern">
            <label style="font-weight: 600;">Tanggal Triase *</label>
            <input type="datetime-local" class="form-control-modern" name="tanggaltriase_sekunder" required
                   value="<?php echo date('Y-m-d\TH:i', strtotime($dataSekunder['tanggaltriase'])); ?>">
        </div>
    </div>

    <!-- Hidden: NIK Petugas -->
    <input type="hidden" name="nik_sekunder" value="<?php echo $dataSekunder['nik']; ?>">
</div>

<!-- Script untuk define variables sudah dipindah ke triaseigd.php -->