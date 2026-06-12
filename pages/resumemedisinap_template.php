<div class="modern-form-container">
    <!-- Sticky Patient Header -->
    <div class="patient-header">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;">
            <!-- Left: Title + Icon -->
            <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
                <i class="material-icons" style="font-size: 22px;">assignment</i>
                <h2 style="margin: 0; font-size: 15px; font-weight: 700; white-space: nowrap;">
                    RESUME MEDIS RAWAT INAP
                </h2>
            </div>
            
            <!-- Center: Patient Info -->
            <div style="display: flex; align-items: center; gap: 20px; flex: 1; font-size: 12px; overflow: hidden;">
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                    <i class="material-icons" style="font-size: 16px;">folder</i>
                    <strong>No. Rawat:</strong> 
                    <span><?php echo $rsPasien['no_rawat']; ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap;">
                    <i class="material-icons" style="font-size: 16px;">badge</i>
                    <strong>No. RM:</strong> 
                    <span><?php echo $rsPasien['no_rkm_medis']; ?></span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <i class="material-icons" style="font-size: 16px;">person</i>
                    <strong>Nama:</strong> 
                    <span style="overflow: hidden; text-overflow: ellipsis;"><?php echo strtoupper($rsPasien['nm_pasien']); ?></span>
                </div>
            </div>
            
            <!-- Right: Badge -->
            <div style="flex-shrink: 0;">
                <span class="mode-badge <?php echo $isEdit ? 'mode-edit' : 'mode-add'; ?>">
                    <?php echo $isEdit ? '✏️ EDIT' : '➕ NEW'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Form Wrapper -->
    <div class="form-wrapper">
        <!-- Modern Tabs Navigation -->
        <div class="modern-tabs" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex;">
                <button class="tab-item active" onclick="switchTab(0)" type="button">
                    <i class="material-icons">local_hospital</i> Data Perawatan
                    <span class="tab-badge" id="badge-0" style="display:none;"></span>
                </button>
                <button class="tab-item" onclick="switchTab(1)" type="button">
                    <i class="material-icons">medical_services</i> Diagnosa Akhir
                    <span class="tab-badge" id="badge-1" style="display:none;"></span>
                </button>
                <button class="tab-item" onclick="switchTab(2)" type="button">
                    <i class="material-icons">exit_to_app</i> Kepulangan
                    <span class="tab-badge" id="badge-2" style="display:none;"></span>
                </button>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <!-- Tombol Auto Fill -->
                <button type="button" class="btn-auto-fill" onclick="autoFillAllData()" title="Isi otomatis semua data">
                    <i class="material-icons">flash_on</i> Auto
                </button>
                <!-- Tombol Kosongkan -->
                <button type="button" class="btn-kosongkan" onclick="kosongkanAutoData()" title="Kosongkan field auto fill">
                    <i class="material-icons">backspace</i> Kosongkan
                </button>
                <!-- Tombol Rapikan AI -->
                <button type="button" class="btn-rapikan-ai" onclick="rapikanDenganAI()" title="Rapikan dengan AI">
                    <i class="material-icons">auto_fix_high</i> Rapikan AI
                </button>
                <?php if($isEdit && !empty($nama_dokter_resume)): ?>
                <div style="display: flex; align-items: center; gap: 8px; padding: 8px 15px; background: #e8f5e9; border-radius: 8px;">
                    <i class="material-icons" style="font-size: 18px; color: #43a047;">person</i>
                    <span style="font-size: 12px; color: #2e7d32; font-weight: 600;">Diisi oleh: <?php echo $nama_dokter_resume; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form with scroll wrapper -->
        <form id="formResume" method="post" action="">
            <input type="hidden" name="no_rawat" value="<?php echo $no_rawat; ?>">
            <input type="hidden" name="kd_dokter" value="<?php echo $data['kd_dokter']; ?>">
            
            <!-- Form Content Wrapper -->
            <div class="form-content-wrapper">
                
                <!-- TAB 0: DATA PERAWATAN -->
                <div class="tab-content active" id="tab-0">
                    <div class="section-card">
                        
                        <!-- Row: Tanggal Masuk, Jam Masuk, Diagnosa Awal -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 0 0 150px;">
                                <label>Tanggal Masuk</label>
                                <input type="text" class="form-control-modern" 
                                       value="<?php echo date('d/m/Y', strtotime($tgl_masuk)); ?>" readonly 
                                       style="background: #f5f5f5; cursor: not-allowed;">
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 120px;">
                                <label>Jam Masuk</label>
                                <input type="text" class="form-control-modern"
                                       value="<?php echo $jam_masuk; ?>" readonly
                                       style="background: #f5f5f5; cursor: not-allowed;">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Diagnosa Awal Masuk</label>
                                <input type="text" class="form-control-modern" name="diagnosa_awal"
                                       value="<?php echo htmlspecialchars($data['diagnosa_awal']); ?>">
                            </div>
                        </div>

                        <!-- Row: Tanggal Keluar, Jam Keluar, Alasan Masuk -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 0 0 150px;">
                                <label>Tanggal Keluar</label>
                                <input type="text" class="form-control-modern" 
                                       value="<?php echo date('d/m/Y', strtotime($tgl_keluar)); ?>" readonly
                                       style="background: #f5f5f5; cursor: not-allowed;">
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 120px;">
                                <label>Jam Keluar</label>
                                <input type="text" class="form-control-modern"
                                       value="<?php echo $jam_keluar; ?>" readonly
                                       style="background: #f5f5f5; cursor: not-allowed;">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Alasan Masuk Dirawat</label>
                                <input type="text" class="form-control-modern" name="alasan"
                                       value="<?php echo htmlspecialchars($data['alasan']); ?>">
                            </div>
                        </div>

                        <!-- Keluhan Utama Riwayat Penyakit + Ambil Data -->
                        <div class="form-row" style="display: flex; gap: 10px; align-items: flex-start;">
                            <div class="form-group-modern" style="flex: 1; min-width: 0;">
                                <label>Keluhan Utama Riwayat Penyakit</label>
                                <textarea class="form-control-modern auto-resize" name="keluhan_utama" id="keluhan_utama" style="min-height: 100px;"
                                          placeholder="Keluhan utama dan riwayat penyakit..."><?php echo htmlspecialchars($data['keluhan_utama']); ?></textarea>
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 400px; max-width: 400px;">
                                <div class="label-with-action" style="margin-bottom: 6px;">
                                    <label style="margin-bottom: 0;">Ambil Data</label>
                                    <div class="dropdown-ambil-data">
                                        <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-keluhan')">
                                            <i class="material-icons">add</i>
                                        </button>
                                        <div class="dropdown-content dropdown-simple" id="dropdown-keluhan">
                                            <?php if($adaDataIGD): ?>
                                            <label class="dropdown-item" onclick="showSumberData('keluhan', 'igd')">
                                                <span>Medis IGD</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if($adaDataRanap): ?>
                                            <label class="dropdown-item" onclick="showSumberData('keluhan', 'ranap')">
                                                <span>Medis Ranap</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if($adaDataSOAP): ?>
                                            <label class="dropdown-item" onclick="showSumberData('keluhan', 'soap')">
                                                <span>SOAP Ranap</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if(!$adaDataIGD && !$adaDataRanap && !$adaDataSOAP): ?>
                                            <div class="dropdown-item disabled">Tidak ada data</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ambil-data-compact" id="ambil-box-keluhan">
                                    <div class="ambil-data-empty">Pilih sumber data</div>
                                </div>
                                <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('keluhan', 'keluhan_utama')">
                                    <i class="material-icons">check</i> Terapkan
                                </button>
                            </div>
                        </div>

                        <!-- Pemeriksaan Fisik + Ambil Data -->
                        <div class="form-row" style="display: flex; gap: 10px; align-items: flex-start;">
                            <div class="form-group-modern" style="flex: 1; min-width: 0;">
                                <label>Pemeriksaan Fisik</label>
                                <textarea class="form-control-modern auto-resize" name="pemeriksaan_fisik" id="pemeriksaan_fisik" style="min-height: 100px;"
                                          placeholder="Hasil pemeriksaan fisik..."><?php echo htmlspecialchars($data['pemeriksaan_fisik']); ?></textarea>
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 400px; max-width: 400px;">
                                <div class="label-with-action" style="margin-bottom: 6px;">
                                    <label style="margin-bottom: 0;">Ambil Data</label>
                                    <div class="dropdown-ambil-data">
                                        <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-fisik')">
                                            <i class="material-icons">add</i>
                                        </button>
                                        <div class="dropdown-content dropdown-simple" id="dropdown-fisik">
                                            <?php if($adaDataIGD): ?>
                                            <label class="dropdown-item" onclick="showSumberData('fisik', 'igd')">
                                                <span>Medis IGD</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if($adaDataRanap): ?>
                                            <label class="dropdown-item" onclick="showSumberData('fisik', 'ranap')">
                                                <span>Medis Ranap</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if($adaDataSOAP): ?>
                                            <label class="dropdown-item" onclick="showSumberData('fisik', 'soap')">
                                                <span>SOAP Ranap</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if(!$adaDataIGD && !$adaDataRanap && !$adaDataSOAP): ?>
                                            <div class="dropdown-item disabled">Tidak ada data</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ambil-data-compact" id="ambil-box-fisik">
                                    <div class="ambil-data-empty">Pilih sumber data</div>
                                </div>
                                <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('fisik', 'pemeriksaan_fisik')">
                                    <i class="material-icons">check</i> Terapkan
                                </button>
                            </div>
                        </div>

                        <!-- Jalannya Penyakit Selama Perawatan + Ambil Data -->
                        <div class="form-row" style="display: flex; gap: 10px; align-items: flex-start;">
                            <div class="form-group-modern" style="flex: 1; min-width: 0;">
                                <label>Jalannya Penyakit Selama Perawatan</label>
                                <textarea class="form-control-modern auto-resize" name="jalannya_penyakit" id="jalannya_penyakit" style="min-height: 100px;"
                                          placeholder="Perjalanan penyakit selama perawatan..."><?php echo htmlspecialchars($data['jalannya_penyakit']); ?></textarea>
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 400px; max-width: 400px;">
                                <div class="label-with-action" style="margin-bottom: 6px;">
                                    <label style="margin-bottom: 0;">Ambil Data</label>
                                    <div class="dropdown-ambil-data">
                                        <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-jalannya')">
                                            <i class="material-icons">add</i>
                                        </button>
                                        <div class="dropdown-content dropdown-simple" id="dropdown-jalannya">
                                            <?php if($adaDataSOAP): ?>
                                            <label class="dropdown-item" onclick="showSumberData('jalannya', 'soap')">
                                                <span>SOAP Ranap</span>
                                            </label>
                                            <?php else: ?>
                                            <div class="dropdown-item disabled">Tidak ada data SOAP</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ambil-data-compact" id="ambil-box-jalannya">
                                    <div class="ambil-data-empty">Pilih sumber data</div>
                                </div>
                                <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('jalannya', 'jalannya_penyakit')">
                                    <i class="material-icons">check</i> Terapkan
                                </button>
                            </div>
                        </div>

                        <!-- Pemeriksaan Penunjang Rad Terpenting + Ambil Data -->
                        <div class="form-row" style="display: flex; gap: 10px; align-items: flex-start;">
                            <div class="form-group-modern" style="flex: 1; min-width: 0;">
                                <label>Pemeriksaan Penunjang Rad Terpenting</label>
                                <textarea class="form-control-modern auto-resize" name="pemeriksaan_penunjang" id="pemeriksaan_penunjang" style="min-height: 100px;"
                                          placeholder="Hasil pemeriksaan radiologi terpenting..."><?php echo htmlspecialchars($data['pemeriksaan_penunjang']); ?></textarea>
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 400px; max-width: 400px;">
                                <div class="label-with-action" style="margin-bottom: 6px;">
                                    <label style="margin-bottom: 0;">Ambil Data</label>
                                    <div class="dropdown-ambil-data">
                                        <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-penunjang')">
                                            <i class="material-icons">add</i>
                                        </button>
                                        <div class="dropdown-content dropdown-simple" id="dropdown-penunjang">
                                            <?php if($adaDataRadiologi): ?>
                                            <label class="dropdown-item" onclick="showSumberData('penunjang', 'radiologi')">
                                                <span>Hasil Radiologi</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if(!$adaDataRadiologi): ?>
                                            <div class="dropdown-item disabled">Tidak ada data radiologi</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ambil-data-compact" id="ambil-box-penunjang">
                                    <div class="ambil-data-empty">Pilih sumber data</div>
                                </div>
                                <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('penunjang', 'pemeriksaan_penunjang')">
                                    <i class="material-icons">check</i> Terapkan
                                </button>
                            </div>
                        </div>

                        <!-- Pemeriksaan Penunjang Lab Terpenting + Ambil Data -->
                        <div class="form-row" style="display: flex; gap: 10px; align-items: flex-start;">
                            <div class="form-group-modern" style="flex: 1; min-width: 0;">
                                <label>Pemeriksaan Penunjang Lab Terpenting</label>
                                <textarea class="form-control-modern auto-resize" name="hasil_laborat" id="hasil_laborat" style="min-height: 100px;"
                                          placeholder="Hasil laboratorium terpenting..."><?php echo htmlspecialchars($data['hasil_laborat']); ?></textarea>
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 400px; max-width: 400px;">
                                <div class="label-with-action" style="margin-bottom: 6px;">
                                    <label style="margin-bottom: 0;">Ambil Data</label>
                                    <div class="dropdown-ambil-data">
                                        <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-lab')">
                                            <i class="material-icons">add</i>
                                        </button>
                                        <div class="dropdown-content dropdown-simple" id="dropdown-lab">
                                            <?php if($adaDataLab): ?>
                                            <label class="dropdown-item" onclick="showSumberData('lab', 'semua')">
                                                <span>Hasil Lab (Semua)</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if($adaDataLabKritis): ?>
                                            <label class="dropdown-item" onclick="showSumberData('lab', 'kritis')">
                                                <span>Hasil Lab Kritis</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if(!$adaDataLab): ?>
                                            <div class="dropdown-item disabled">Tidak ada data lab</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ambil-data-compact" id="ambil-box-lab">
                                    <div class="ambil-data-empty">Pilih sumber data</div>
                                </div>
                                <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('lab', 'hasil_laborat')">
                                    <i class="material-icons">check</i> Terapkan
                                </button>
                            </div>
                        </div>

                        <!-- Tindakan/Operasi Selama Perawatan + Ambil Data -->
                        <div class="form-row" style="display: flex; gap: 10px; align-items: flex-start;">
                            <div class="form-group-modern" style="flex: 1; min-width: 0;">
                                <label>Tindakan/Operasi Selama Perawatan</label>
                                <textarea class="form-control-modern auto-resize" name="tindakan_dan_operasi" id="tindakan_dan_operasi" style="min-height: 100px;"
                                          placeholder="Tindakan atau operasi yang dilakukan selama perawatan..."><?php echo htmlspecialchars($data['tindakan_dan_operasi']); ?></textarea>
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 400px; max-width: 400px;">
                                <div class="label-with-action" style="margin-bottom: 6px;">
                                    <label style="margin-bottom: 0;">Ambil Data</label>
                                    <div class="dropdown-ambil-data">
                                        <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-tindakan')">
                                            <i class="material-icons">add</i>
                                        </button>
                                        <div class="dropdown-content dropdown-simple" id="dropdown-tindakan">
                                            <?php if($adaDataTindakan): ?>
                                            <label class="dropdown-item" onclick="showSumberData('tindakan', 'tindakan')">
                                                <span>Tindakan</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if($adaDataOperasi): ?>
                                            <label class="dropdown-item" onclick="showSumberData('tindakan', 'operasi')">
                                                <span>Operasi</span>
                                            </label>
                                            <?php endif; ?>
                                            <?php if(!$adaDataTindakan && !$adaDataOperasi): ?>
                                            <div class="dropdown-item disabled">Tidak ada data</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ambil-data-compact" id="ambil-box-tindakan">
                                    <div class="ambil-data-empty">Pilih sumber data</div>
                                </div>
                                <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('tindakan', 'tindakan_dan_operasi')">
                                    <i class="material-icons">check</i> Terapkan
                                </button>
                            </div>
                        </div>

                        <!-- Obat-obatan Selama Perawatan + Ambil Data -->
                        <div class="form-row" style="display: flex; gap: 10px; align-items: flex-start;">
                            <div class="form-group-modern" style="flex: 1; min-width: 0;">
                                <label>Obat-obatan Selama Perawatan</label>
                                <textarea class="form-control-modern auto-resize" name="obat_di_rs" id="obat_di_rs" style="min-height: 100px;"
                                          placeholder="Obat-obatan yang diberikan selama perawatan..."><?php echo htmlspecialchars($data['obat_di_rs']); ?></textarea>
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 400px; max-width: 400px;">
                                <div class="label-with-action" style="margin-bottom: 6px;">
                                    <label style="margin-bottom: 0;">Ambil Data</label>
                                    <div class="dropdown-ambil-data">
                                        <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-obat')">
                                            <i class="material-icons">add</i>
                                        </button>
                                        <div class="dropdown-content dropdown-simple" id="dropdown-obat">
                                            <?php if($adaDataObat): ?>
                                            <label class="dropdown-item" onclick="showSumberData('obat', 'obat')">
                                                <span>Obat Selama Perawatan</span>
                                            </label>
                                            <?php else: ?>
                                            <div class="dropdown-item disabled">Tidak ada data obat</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ambil-data-compact" id="ambil-box-obat">
                                    <div class="ambil-data-empty">Pilih sumber data</div>
                                </div>
                                <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('obat', 'obat_di_rs')">
                                    <i class="material-icons">check</i> Terapkan
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB 1: DIAGNOSA AKHIR -->
                <div class="tab-content" id="tab-1">
                    <div class="section-card">
                        <div class="section-title" style="margin-bottom: 15px;">
                            <i class="material-icons">medication</i>
                            <span>Diagnosa Akhir</span>
                        </div>

                        <!-- Diagnosa Utama -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 3;">
                                <label>Diagnosa Utama</label>
                                <input type="text" class="form-control-modern" name="diagnosa_utama" 
                                       value="<?php echo htmlspecialchars($data['diagnosa_utama']); ?>" 
                                       placeholder="Nama diagnosa utama">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Kode ICD</label>
                                <input type="text" class="form-control-modern" name="kd_diagnosa_utama" 
                                       value="<?php echo htmlspecialchars($data['kd_diagnosa_utama']); ?>" 
                                       placeholder="Kode ICD">
                            </div>
                        </div>

                        <!-- Diagnosa Sekunder 1 -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 3;">
                                <label>Diagnosa Sekunder 1</label>
                                <input type="text" class="form-control-modern" name="diagnosa_sekunder" 
                                       value="<?php echo htmlspecialchars($data['diagnosa_sekunder']); ?>" 
                                       placeholder="Diagnosa sekunder 1">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Kode ICD</label>
                                <input type="text" class="form-control-modern" name="kd_diagnosa_sekunder" 
                                       value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder']); ?>" 
                                       placeholder="Kode ICD">
                            </div>
                        </div>

                        <!-- Diagnosa Sekunder 2 -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 3;">
                                <label>Diagnosa Sekunder 2</label>
                                <input type="text" class="form-control-modern" name="diagnosa_sekunder2" 
                                       value="<?php echo htmlspecialchars($data['diagnosa_sekunder2']); ?>" 
                                       placeholder="Diagnosa sekunder 2">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Kode ICD</label>
                                <input type="text" class="form-control-modern" name="kd_diagnosa_sekunder2" 
                                       value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder2']); ?>" 
                                       placeholder="Kode ICD">
                            </div>
                        </div>

                        <!-- Diagnosa Sekunder 3 -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 3;">
                                <label>Diagnosa Sekunder 3</label>
                                <input type="text" class="form-control-modern" name="diagnosa_sekunder3" 
                                       value="<?php echo htmlspecialchars($data['diagnosa_sekunder3']); ?>" 
                                       placeholder="Diagnosa sekunder 3">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Kode ICD</label>
                                <input type="text" class="form-control-modern" name="kd_diagnosa_sekunder3" 
                                       value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder3']); ?>" 
                                       placeholder="Kode ICD">
                            </div>
                        </div>

                        <!-- Diagnosa Sekunder 4 -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 3;">
                                <label>Diagnosa Sekunder 4</label>
                                <input type="text" class="form-control-modern" name="diagnosa_sekunder4" 
                                       value="<?php echo htmlspecialchars($data['diagnosa_sekunder4']); ?>" 
                                       placeholder="Diagnosa sekunder 4">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Kode ICD</label>
                                <input type="text" class="form-control-modern" name="kd_diagnosa_sekunder4" 
                                       value="<?php echo htmlspecialchars($data['kd_diagnosa_sekunder4']); ?>" 
                                       placeholder="Kode ICD">
                            </div>
                        </div>

                        <!-- Separator -->
                        <hr style="margin: 20px 0; border: none; border-top: 1px dashed #ddd;">

                        <!-- Prosedur Utama -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 3;">
                                <label>Prosedur Utama</label>
                                <input type="text" class="form-control-modern" name="prosedur_utama" 
                                       value="<?php echo htmlspecialchars($data['prosedur_utama']); ?>" 
                                       placeholder="Nama prosedur utama">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Kode ICD</label>
                                <input type="text" class="form-control-modern" name="kd_prosedur_utama" 
                                       value="<?php echo htmlspecialchars($data['kd_prosedur_utama']); ?>" 
                                       placeholder="Kode">
                            </div>
                        </div>

                        <!-- Prosedur Sekunder 1 -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 3;">
                                <label>Prosedur Sekunder 1</label>
                                <input type="text" class="form-control-modern" name="prosedur_sekunder" 
                                       value="<?php echo htmlspecialchars($data['prosedur_sekunder']); ?>" 
                                       placeholder="Prosedur sekunder 1">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Kode ICD</label>
                                <input type="text" class="form-control-modern" name="kd_prosedur_sekunder" 
                                       value="<?php echo htmlspecialchars($data['kd_prosedur_sekunder']); ?>" 
                                       placeholder="Kode">
                            </div>
                        </div>

                        <!-- Prosedur Sekunder 2 -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 3;">
                                <label>Prosedur Sekunder 2</label>
                                <input type="text" class="form-control-modern" name="prosedur_sekunder2" 
                                       value="<?php echo htmlspecialchars($data['prosedur_sekunder2']); ?>" 
                                       placeholder="Prosedur sekunder 2">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Kode ICD</label>
                                <input type="text" class="form-control-modern" name="kd_prosedur_sekunder2" 
                                       value="<?php echo htmlspecialchars($data['kd_prosedur_sekunder2']); ?>" 
                                       placeholder="Kode">
                            </div>
                        </div>

                        <!-- Prosedur Sekunder 3 -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 3;">
                                <label>Prosedur Sekunder 3</label>
                                <input type="text" class="form-control-modern" name="prosedur_sekunder3" 
                                       value="<?php echo htmlspecialchars($data['prosedur_sekunder3']); ?>" 
                                       placeholder="Prosedur sekunder 3">
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Kode ICD</label>
                                <input type="text" class="form-control-modern" name="kd_prosedur_sekunder3" 
                                       value="<?php echo htmlspecialchars($data['kd_prosedur_sekunder3']); ?>" 
                                       placeholder="Kode">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- TAB 2: KEPULANGAN -->
                <div class="tab-content" id="tab-2">
                    <div class="section-card">
                        
                        <!-- Alergi Obat -->
                        <div class="form-row">
                            <div class="form-group-modern">
                                <label>Alergi Obat</label>
                                <input type="text" class="form-control-modern" name="alergi" 
                                       value="<?php echo htmlspecialchars($data['alergi']); ?>" 
                                       placeholder="Alergi obat pasien...">
                            </div>
                        </div>

                        <!-- Diet + Ambil Data -->
                        <div class="form-row" style="display: flex; gap: 10px; align-items: flex-start;">
                            <div class="form-group-modern" style="flex: 1; min-width: 0;">
                                <label>Diet</label>
                                <textarea class="form-control-modern auto-resize" name="diet" id="diet" style="min-height: 80px;"
                                          placeholder="Diet yang dianjurkan..."><?php echo htmlspecialchars($data['diet']); ?></textarea>
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 400px; max-width: 400px;">
                                <div class="label-with-action" style="margin-bottom: 6px;">
                                    <label style="margin-bottom: 0;">Ambil Data</label>
                                    <div class="dropdown-ambil-data">
                                        <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-diet')">
                                            <i class="material-icons">add</i>
                                        </button>
                                        <div class="dropdown-content dropdown-simple" id="dropdown-diet">
                                            <?php if($adaDataDiet): ?>
                                            <label class="dropdown-item" onclick="showSumberData('diet', 'diet')">
                                                <span>Diet Selama Perawatan</span>
                                            </label>
                                            <?php else: ?>
                                            <div class="dropdown-item disabled">Tidak ada data diet</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ambil-data-compact" id="ambil-box-diet">
                                    <div class="ambil-data-empty">Pilih sumber data</div>
                                </div>
                                <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('diet', 'diet')">
                                    <i class="material-icons">check</i> Terapkan
                                </button>
                            </div>
                        </div>

                        <!-- Hasil Lab Yang Belum Selesai (Pending) -->
                        <div class="form-row">
                            <div class="form-group-modern">
                                <label>Hasil Lab Yang Belum Selesai (Pending)</label>
                                <textarea class="form-control-modern" name="lab_belum" rows="2" 
                                          placeholder="Hasil laboratorium yang masih pending..."><?php echo htmlspecialchars($data['lab_belum']); ?></textarea>
                            </div>
                        </div>

                        <!-- Instruksi/Anjuran Dan Edukasi (Follow Up) -->
                        <div class="form-row">
                            <div class="form-group-modern">
                                <label>Instruksi/Anjuran Dan Edukasi (Follow Up)</label>
                                <textarea class="form-control-modern" name="edukasi" rows="3" 
                                          placeholder="Instruksi dan edukasi untuk pasien..."><?php echo htmlspecialchars($data['edukasi']); ?></textarea>
                            </div>
                        </div>

                        <!-- Row: Keadaan Pulang & Cara Keluar -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Keadaan Pulang</label>
                                <div style="display: flex; gap: 8px;">
                                    <select class="form-control-modern" name="keadaan" style="flex: 1;">
                                        <option value="Membaik" <?php echo ($data['keadaan'] == 'Membaik') ? 'selected' : ''; ?>>Membaik</option>
                                        <option value="Sembuh" <?php echo ($data['keadaan'] == 'Sembuh') ? 'selected' : ''; ?>>Sembuh</option>
                                        <option value="Keadaan Khusus" <?php echo ($data['keadaan'] == 'Keadaan Khusus') ? 'selected' : ''; ?>>Keadaan Khusus</option>
                                        <option value="Meninggal" <?php echo ($data['keadaan'] == 'Meninggal') ? 'selected' : ''; ?>>Meninggal</option>
                                    </select>
                                    <input type="text" class="form-control-modern" name="ket_keadaan" 
                                           value="<?php echo htmlspecialchars($data['ket_keadaan']); ?>" 
                                           style="flex: 1;" placeholder="Keterangan...">
                                </div>
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Cara Keluar</label>
                                <div style="display: flex; gap: 8px;">
                                    <select class="form-control-modern" name="cara_keluar" style="flex: 1;">
                                        <option value="Atas Izin Dokter" <?php echo ($data['cara_keluar'] == 'Atas Izin Dokter') ? 'selected' : ''; ?>>Atas Izin Dokter</option>
                                        <option value="Pindah RS" <?php echo ($data['cara_keluar'] == 'Pindah RS') ? 'selected' : ''; ?>>Pindah RS</option>
                                        <option value="Pulang Atas Permintaan Sendiri" <?php echo ($data['cara_keluar'] == 'Pulang Atas Permintaan Sendiri') ? 'selected' : ''; ?>>Pulang Atas Permintaan Sendiri</option>
                                    </select>
                                    <input type="text" class="form-control-modern" name="ket_keluar" 
                                           value="<?php echo htmlspecialchars($data['ket_keluar']); ?>" 
                                           style="flex: 1;" placeholder="Keterangan...">
                                </div>
                            </div>
                        </div>

                        <!-- Row: Dilanjutkan & Tanggal Kontrol -->
                        <div class="form-row">
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Dilanjutkan</label>
                                <div style="display: flex; gap: 8px;">
                                    <select class="form-control-modern" name="dilanjutkan" style="flex: 1;">
                                        <option value="Kembali Ke RS" <?php echo ($data['dilanjutkan'] == 'Kembali Ke RS') ? 'selected' : ''; ?>>Kembali Ke RS</option>
                                        <option value="RS Lain" <?php echo ($data['dilanjutkan'] == 'RS Lain') ? 'selected' : ''; ?>>RS Lain</option>
                                        <option value="Dokter Luar" <?php echo ($data['dilanjutkan'] == 'Dokter Luar') ? 'selected' : ''; ?>>Dokter Luar</option>
                                        <option value="Puskesmas" <?php echo ($data['dilanjutkan'] == 'Puskesmas') ? 'selected' : ''; ?>>Puskesmas</option>
                                    </select>
                                    <input type="text" class="form-control-modern" name="ket_dilanjutkan" 
                                           value="<?php echo htmlspecialchars($data['ket_dilanjutkan']); ?>" 
                                           style="flex: 1;" placeholder="Keterangan...">
                                </div>
                            </div>
                            <div class="form-group-modern" style="flex: 1;">
                                <label>Tanggal & Jam Kontrol</label>
                                <input type="datetime-local" class="form-control-modern" name="kontrol" 
                                       value="<?php echo $data['kontrol'] ? date('Y-m-d\TH:i', strtotime($data['kontrol'])) : ''; ?>">
                            </div>
                        </div>

                        <!-- Obat Pulang + Ambil Data -->
                        <div class="form-row" style="display: flex; gap: 10px; align-items: flex-start;">
                            <div class="form-group-modern" style="flex: 1; min-width: 0;">
                                <label>Obat Pulang</label>
                                <textarea class="form-control-modern auto-resize" name="obat_pulang" id="obat_pulang" style="min-height: 100px;"
                                          placeholder="Obat-obatan yang diberikan saat pulang..."><?php echo htmlspecialchars($data['obat_pulang']); ?></textarea>
                            </div>
                            <div class="form-group-modern" style="flex: 0 0 400px; max-width: 400px;">
                                <div class="label-with-action" style="margin-bottom: 6px;">
                                    <label style="margin-bottom: 0;">Ambil Data</label>
                                    <div class="dropdown-ambil-data">
                                        <button type="button" class="btn-tambah-sumber" onclick="toggleDropdown('dropdown-obatpulang')">
                                            <i class="material-icons">add</i>
                                        </button>
                                        <div class="dropdown-content dropdown-simple" id="dropdown-obatpulang">
                                            <?php if($adaDataObatPulang): ?>
                                            <label class="dropdown-item" onclick="showSumberData('obatpulang', 'resep')">
                                                <span>Resep Pulang</span>
                                            </label>
                                            <?php else: ?>
                                            <div class="dropdown-item disabled">Tidak ada resep pulang</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="ambil-data-compact" id="ambil-box-obatpulang">
                                    <div class="ambil-data-empty">Pilih sumber data</div>
                                </div>
                                <button type="button" class="btn-terapkan-sm" onclick="terapkanAmbilData('obatpulang', 'obat_pulang')">
                                    <i class="material-icons">check</i> Terapkan
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

            </div><!-- End form-content-wrapper -->
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="progress-indicator">
                        <div class="progress-dot" id="dot-0"></div>
                        <div class="progress-dot" id="dot-1"></div>
                        <div class="progress-dot" id="dot-2"></div>
                    </div>
                    <span style="font-size: 12px; color: #666; font-weight: 600;">
                        Tab <span id="current-tab-number">1</span> dari 3
                    </span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-modern btn-secondary-modern" onclick="window.history.back();">
                        <i class="material-icons" style="font-size: 16px;">arrow_back</i>
                        KEMBALI
                    </button>
                    <button type="button" class="btn-modern btn-secondary-modern" id="btn-prev" onclick="previousTab()" style="display: none;">
                        <i class="material-icons" style="font-size: 16px;">navigate_before</i>
                        SEBELUMNYA
                    </button>
                    <button type="button" class="btn-modern btn-primary-modern" id="btn-next" onclick="nextTab()">
                        SELANJUTNYA
                        <i class="material-icons" style="font-size: 16px;">navigate_next</i>
                    </button>
                    <!-- Tombol simpan -->
                    <button type="button" name="btnSimpan" class="btn-modern btn-primary-modern" id="btn-save" style="display: none;" onclick="simpanResume()">
                        <i class="material-icons" style="font-size: 16px;">save</i>
                        SIMPAN DATA
                    </button>
                    <!-- Tombol hapus -->
                    <?php if($isEdit): ?>
                    <button type="button" name="btnHapus" class="btn-modern btn-danger-modern" id="btn-delete" style="display: none;" onclick="hapusResume()">
                        <i class="material-icons" style="font-size: 16px;">delete</i>
                        HAPUS DATA
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div><!-- End form-wrapper -->
</div>


<!-- Pass PHP variables to JavaScript -->
<script>
    const APP_BASE_URL = '<?php echo APP_BASE_URL; ?>';
    
    // Data sumber dari PHP untuk JavaScript
    const dataSumber = {
        keluhan: {
            igd: { label: 'Medis IGD', items: [{ key: 'keluhan_utama', label: 'Keluhan Utama', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $sumberKeluhan['igd']['data'])); ?>` }] },
            ranap: { label: 'Medis Ranap', items: [
                { key: 'keluhan_utama', label: 'Keluhan Utama', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $sumberKeluhan['ranap']['data'])); ?>` },
                { key: 'rps', label: 'RPS', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $sumberKeluhan['ranap_rps']['data'])); ?>` }
            ]},
            soap: { label: 'SOAP Ranap', items: [<?php foreach($dataSOAP as $idx => $soap): ?>{ key: 'soap_<?php echo $idx; ?>', label: '<?php echo date("d/m/Y H:i", strtotime($soap["tgl_perawatan"] . " " . $soap["jam_rawat"])); ?>', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $soap['keluhan'])); ?>` }<?php echo ($idx < count($dataSOAP) - 1) ? ',' : ''; ?><?php endforeach; ?>] }
        },
        fisik: {
            igd: { label: 'Medis IGD', items: [{ key: 'pemeriksaan', label: 'Pemeriksaan Fisik', data: `` }] },
            ranap: { label: 'Medis Ranap', items: [{ key: 'pemeriksaan', label: 'Pemeriksaan Fisik', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $pemeriksaanFisikRanap)); ?>` }] },
            soap: { label: 'SOAP Ranap', items: [<?php foreach($dataSOAP as $idx => $soap): $fisikSOAP = formatPemeriksaanFisikSOAP($soap); if (!empty($fisikSOAP)): ?>{ key: 'soap_<?php echo $idx; ?>', label: '<?php echo date("d/m/Y H:i", strtotime($soap["tgl_perawatan"] . " " . $soap["jam_rawat"])); ?>', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $fisikSOAP)); ?>` },<?php endif; endforeach; ?>] }
        },
        jalannya: { soap: { label: 'SOAP Ranap', items: [{ key: 'narasi_lengkap', label: 'Narasi Kronologis', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $jalannyaPenyakitSOAP)); ?>` }] } },
        penunjang: { radiologi: { label: 'Hasil Radiologi', items: [<?php foreach($dataRadiologi as $idx => $rad): $tglRad = date('d/m/Y', strtotime($rad['tgl_periksa'])); $hasilRad = !empty($rad['hasil']) ? $rad['hasil'] : ''; if (!empty($hasilRad)): ?>{ key: 'rad_<?php echo $idx; ?>', label: '<?php echo addslashes("Radiologi (" . $tglRad . ")"); ?>', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', "Radiologi (" . $tglRad . "):\n" . $hasilRad)); ?>` },<?php endif; endforeach; ?>] } },
        lab: {
            semua: { label: 'Hasil Lab (Semua)', items: [{ key: 'lab_semua', label: 'Semua Hasil Lab', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $hasilLabFormatted)); ?>` }] },
            kritis: { label: 'Hasil Lab Kritis', items: [{ key: 'lab_kritis', label: 'Hasil Lab Kritis', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $hasilLabKritisFormatted)); ?>` }] }
        },
        tindakan: {
            tindakan: { label: 'Tindakan', items: [<?php if($adaDataTindakan): ?>{ key: 'tindakan_all', label: 'Semua Tindakan', data: `<?php echo addslashes($tindakanFormatted); ?>` }<?php endif; ?>] },
            operasi: { label: 'Operasi', items: [<?php foreach($dataOperasi as $idx => $op): $tglOp = date('d/m/Y', strtotime($op['tgl_operasi'])); $nmOp = !empty($op['nm_perawatan']) ? $op['nm_perawatan'] : '-'; $dataOp = "Operasi (" . date('d-m-Y', strtotime($op['tgl_operasi'])) . "): " . $nmOp; ?>{ key: 'op_<?php echo $idx; ?>', label: '<?php echo addslashes($nmOp . " (" . $tglOp . ")"); ?>', data: `<?php echo addslashes($dataOp); ?>` },<?php endforeach; ?>] }
        },
        obat: { obat: { label: 'Obat Selama Perawatan', items: [<?php if($adaDataObat): ?>{ key: 'obat_all', label: 'Semua Obat', data: `<?php echo addslashes($obatFormatted); ?>` }<?php endif; ?>] } },
        diet: { diet: { label: 'Diet Selama Perawatan', items: [<?php if($adaDataDiet): ?>{ key: 'diet_all', label: 'Semua Diet', data: `<?php echo addslashes($dietFormatted); ?>` }<?php endif; ?>] } },
        obatpulang: { resep: { label: 'Resep Pulang', items: [<?php if($adaDataObatPulang): ?>{ key: 'resep_all', label: 'Semua Obat Pulang', data: `<?php echo addslashes(str_replace(array("\r\n", "\r", "\n"), '\n', $obatPulangFormatted)); ?>` }<?php endif; ?>] } }
    };
</script>

<!-- Load External CSS (template3.css for Resume specific styles) -->
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/template3.css?v=<?php echo time(); ?>">

<!-- Load External JavaScript -->
<script src="<?php echo BASE_URL; ?>/js/resumemedisinap.js?v=<?php echo time(); ?>"></script>