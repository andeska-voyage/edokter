<?php
    // Dekripsi dan validasi parameter
    $norawat = '';
    $norm = '';
    
    if(isset($_GET['rnw']) && isset($_GET['rm'])){
        $norawat = encrypt_decrypt(urldecode($_GET['rnw']), "d");
        $norm = encrypt_decrypt(urldecode($_GET['rm']), "d");
        
        // Validasi tambahan
        $norawat = validTeks4($norawat, 20);
        $norm = validTeks4($norm, 20);
    } else {
        // Redirect jika parameter tidak valid
        JSRedirect("index.php?act=Pasien");
        exit();
    }
    
    // Validasi kepemilikan: Cek apakah pasien ini benar-benar pasien dokter yang login
    $kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"],"d"), 20);

    // Cek apakah dokter umum atau spesialis
    $queryDokter = bukaquery("SELECT kd_sps FROM dokter WHERE kd_dokter = '$kd_dokter'");
    $rsDokter = mysqli_fetch_array($queryDokter);

    if($rsDokter) {
        $kd_sps = $rsDokter['kd_sps'];
        $is_dokter_umum = ($kd_sps == KD_DOKTER_UMUM || $kd_sps == KD_DOKTER_ANESTESI);
    } else {
        $kd_sps = '';
        $is_dokter_umum = false;
    }

    // ============================================================
    // Load feature toggle (FITUR_TEMPLATE_RANAP, dll) — auto-load
    // ============================================================
    @include_once __DIR__ . '/../conf/app.php';

    // ============================================================
    // Load template SOAPIE quick-fill INAP — per kd_sps dokter login
    // ============================================================
    $tpl_all = @include __DIR__ . '/../conf/templateinap.php';
    if (!is_array($tpl_all)) $tpl_all = [];
    $tpl_default = $tpl_all['__default__'] ?? [];
    $tpl_sps     = $tpl_all[$kd_sps] ?? [];

    // Helper: gabungkan default + per-spesialis untuk 1 section
    $get_tpl = function($section) use ($tpl_default, $tpl_sps) {
        $def  = $tpl_default[$section] ?? [];
        $spec = $tpl_sps[$section]     ?? [];
        return ['default' => $def, 'spesifik' => $spec];
    };

    // Load shared SOAPIE render functions


    // ============================================================
    // Helper: render structured form untuk SUBJECTIVE section
    // Output: hidden textarea[name=subjective] di-update real-time dari:
    //   - Checkbox list keluhan (default + per-poli)
    //   - Slider Skala Nyeri 0-10
    //   - Textarea Catatan Tambahan (free text)
    // ============================================================
    function renderSubjectiveStructured($tpl) {
        $def  = $tpl['default']  ?? [];
        $spec = $tpl['spesifik'] ?? [];
        ?>
        <div class="ss-form" data-section="subjective">

            <!-- Checkbox list keluhan -->
            <?php if (!empty($def) || !empty($spec)): ?>
            <div class="ss-block">
                <label class="ss-block-label">KELUHAN</label>
                <div class="ss-checks">
                    <?php
                    // Helper: render label dengan input inline untuk pattern "..."
                    // Contoh: "Demam sejak ... hari yang lalu" → split by "..."
                    // → "Demam sejak <input> hari yang lalu"
                    $renderLabel = function($txt) {
                        if (strpos($txt, '...') === false) {
                            return '<span>' . htmlspecialchars($txt) . '</span>';
                        }
                        $parts = explode('...', $txt);
                        $out = '<span class="ss-label-text">';
                        foreach ($parts as $i => $part) {
                            $out .= htmlspecialchars($part);
                            if ($i < count($parts) - 1) {
                                $out .= '<input type="text" class="ss-keluhan-input" placeholder="..." size="3" oninput="ssOnInputDots(this)" onclick="event.stopPropagation()">';
                            }
                        }
                        $out .= '</span>';
                        return $out;
                    };
                    ?>
                    <?php foreach ($spec as $i => $txt): ?>
                        <label class="ss-check ss-check-poli<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>" title="Khusus poli">
                            <input type="checkbox" class="ss-keluhan-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnCheck(this)">
                            <?= $renderLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php foreach ($def as $i => $txt): ?>
                        <label class="ss-check<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>">
                            <input type="checkbox" class="ss-keluhan-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnCheck(this)">
                            <?= $renderLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Skala Nyeri -->
            <div class="ss-block">
                <label class="ss-block-label">SKALA NYERI</label>
                <div class="ss-pain">
                    <input type="range" min="0" max="10" value="0" step="1" class="ss-pain-slider" oninput="ssOnPainChange(this)">
                    <div class="ss-pain-info">
                        <span class="ss-pain-value">0</span><span class="ss-pain-max">/10</span>
                        <span class="ss-pain-badge" data-level="0">Tidak Nyeri</span>
                    </div>
                </div>
            </div>

            <!-- Hidden textarea — final output (gabungan structured + catatan tambahan) → disubmit ke DB -->
            <textarea name="subjective" class="soapie-textarea" data-section="subjective" style="display:none;"></textarea>

            <!-- Catatan Tambahan — genuine additional notes (visible, dipisah dari hasil centangan) -->
            <div class="ss-block">
                <label class="ss-block-label">CATATAN TAMBAHAN <span style="color:#94a3b8;font-weight:normal;">(opsional — tulis info tambahan di luar centangan di atas)</span></label>
                <textarea id="ssCatatanExtra" class="ss-catatan" rows="3" placeholder="Anamnesis, riwayat detail, durasi, dll..." oninput="ssOnCatatanInput()"></textarea>
            </div>

        </div>
        <?php
    }

    // ============================================================
    // Helper: render structured form untuk OBJECTIVE section
    // Output: hidden textarea[name=objective] di-update real-time dari:
    //   - Radio per organ (Normal/Abnormal/Tidak Diperiksa)
    //   - Checkbox quick findings (default + per-poli)
    //   - Catatan Tambahan textarea (visible, free text)
    // ============================================================
    function renderObjectiveStructured($tpl, $kd_poli, $tpl_default, $tpl_poli) {
        $def_findings  = $tpl['default']  ?? [];
        $spec_findings = $tpl['spesifik'] ?? [];
        // Organ list — gabung default + per-poli (per-poli override jika ada)
        $organs = $tpl_poli['objective_organ'] ?? $tpl_default['objective_organ'] ?? [];
        ?>
        <div class="ss-form" data-section="objective">

            <!-- Status per Organ -->
            <?php if (!empty($organs)): ?>
            <div class="ss-block">
                <label class="ss-block-label">STATUS PEMERIKSAAN PER ORGAN</label>
                <div class="ss-organs">
                    <?php foreach ($organs as $i => $organ):
                        $organ_safe = htmlspecialchars($organ, ENT_QUOTES);
                        $rname = 'so_organ_' . md5($organ . $i);
                    ?>
                    <div class="ss-organ-row">
                        <div class="ss-organ-label"><?= htmlspecialchars($organ) ?></div>
                        <div class="ss-organ-radios">
                            <label class="ss-organ-radio ss-r-n">
                                <input type="radio" name="<?= $rname ?>" value="Normal"
                                       data-organ="<?= $organ_safe ?>" onchange="ssOnObjOrgan(this)">
                                <span>Normal</span>
                            </label>
                            <label class="ss-organ-radio ss-r-a">
                                <input type="radio" name="<?= $rname ?>" value="Abnormal"
                                       data-organ="<?= $organ_safe ?>" onchange="ssOnObjOrgan(this)">
                                <span>Abnormal</span>
                            </label>
                            <label class="ss-organ-radio ss-r-tp">
                                <input type="radio" name="<?= $rname ?>" value="Tidak Diperiksa"
                                       data-organ="<?= $organ_safe ?>" onchange="ssOnObjOrgan(this)">
                                <span>TP</span>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Findings (checkbox) -->
            <?php if (!empty($def_findings) || !empty($spec_findings)): ?>
            <div class="ss-block">
                <label class="ss-block-label">QUICK FINDINGS</label>
                <div class="ss-checks">
                    <?php
                    // Helper render label dengan input inline untuk pola "..."
                    $renderObjLabel = function($txt) {
                        if (strpos($txt, '...') === false) {
                            return '<span>' . htmlspecialchars($txt) . '</span>';
                        }
                        $parts = explode('...', $txt);
                        $out = '<span class="ss-label-text">';
                        foreach ($parts as $i => $part) {
                            $out .= htmlspecialchars($part);
                            if ($i < count($parts) - 1) {
                                $out .= '<input type="text" class="ss-obj-input" placeholder="..." size="3" oninput="ssOnObjInputDots(this)" onclick="event.stopPropagation()">';
                            }
                        }
                        $out .= '</span>';
                        return $out;
                    };
                    ?>
                    <?php foreach ($spec_findings as $txt): ?>
                        <label class="ss-check ss-check-poli<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>" title="Khusus poli">
                            <input type="checkbox" class="ss-obj-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnObjFinding(this)">
                            <?= $renderObjLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php foreach ($def_findings as $txt): ?>
                        <label class="ss-check<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>">
                            <input type="checkbox" class="ss-obj-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnObjFinding(this)">
                            <?= $renderObjLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Hidden textarea (final output → DB) -->
            <textarea name="objective" class="soapie-textarea" data-section="objective" style="display:none;"></textarea>

            <!-- Catatan Tambahan -->
            <div class="ss-block">
                <label class="ss-block-label">CATATAN TAMBAHAN <span style="color:#94a3b8;font-weight:normal;">(detail temuan abnormal, hasil penunjang, dll)</span></label>
                <textarea id="ssObjCatatanExtra" class="ss-catatan" rows="3" placeholder="Tulis detail abnormal, hasil lab/penunjang yang relevan..." oninput="ssOnObjCatatanInput()"></textarea>
            </div>

        </div>
        <?php
    }

    // ============================================================
    // Helper: render structured form untuk ASSESSMENT section
    //   - Diagnosis Kerja: autocomplete ICD-10 (multi-select chips)
    //   - Diagnosis Banding: checkbox quick-pick dari template
    //   - Evaluasi Kondisi: radio Membaik/Stabil/Memburuk
    //   - Severity: radio Ringan/Sedang/Berat/Kritis
    //   - Catatan Asesmen: free text
    // ============================================================
    function renderAssessmentStructured($tpl) {
        $def_dd  = $tpl['default']  ?? [];   // default differential diagnoses (diagnosa list)
        $spec_dd = $tpl['spesifik'] ?? [];   // per-poli
        ?>
        <div class="ss-form" data-section="assessment">

            <!-- Diagnosis Kerja: Autocomplete ICD-10 -->
            <div class="ss-block">
                <label class="ss-block-label">DIAGNOSIS KERJA <span style="color:#94a3b8;font-weight:normal;">(ketik nama atau kode ICD)</span></label>
                <div class="ss-dx-search-wrap">
                    <input type="text" class="ss-dx-search" id="ssDxSearch"
                           placeholder="Cari diagnosa atau kode ICD-10..." autocomplete="off">
                    <div class="ss-dx-results" id="ssDxResults"></div>
                </div>
                <div class="ss-dx-list" id="ssDxList"></div>
            </div>

            <!-- Diagnosis Banding: Quick Pick -->
            <?php if (!empty($def_dd) || !empty($spec_dd)): ?>
            <div class="ss-block">
                <label class="ss-block-label">DIAGNOSIS BANDING <span style="color:#94a3b8;font-weight:normal;">(opsional)</span></label>
                <div class="ss-checks">
                    <?php
                    $renderAsmLabel = function($txt) {
                        if (strpos($txt, '...') === false) {
                            return '<span>' . htmlspecialchars($txt) . '</span>';
                        }
                        $parts = explode('...', $txt);
                        $out = '<span class="ss-label-text">';
                        foreach ($parts as $i => $part) {
                            $out .= htmlspecialchars($part);
                            if ($i < count($parts) - 1) {
                                $out .= '<input type="text" class="ss-asm-input" placeholder="..." size="3" oninput="ssOnAsmInputDots(this)" onclick="event.stopPropagation()">';
                            }
                        }
                        $out .= '</span>';
                        return $out;
                    };
                    ?>
                    <?php foreach ($spec_dd as $txt): ?>
                        <label class="ss-check ss-check-poli<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>" title="Khusus poli">
                            <input type="checkbox" class="ss-asm-dd-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnAsmDD(this)">
                            <?= $renderAsmLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php foreach ($def_dd as $txt): ?>
                        <label class="ss-check<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>">
                            <input type="checkbox" class="ss-asm-dd-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnAsmDD(this)">
                            <?= $renderAsmLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Evaluasi Kondisi -->
            <div class="ss-block">
                <label class="ss-block-label">EVALUASI KONDISI</label>
                <div class="ss-pill-group">
                    <label class="ss-pill ss-pill-up">
                        <input type="radio" name="ssAsmKondisi" value="Membaik" onchange="ssOnAsmKondisi(this)">
                        <span><i class="material-icons">trending_up</i> Membaik</span>
                    </label>
                    <label class="ss-pill ss-pill-flat">
                        <input type="radio" name="ssAsmKondisi" value="Stabil" onchange="ssOnAsmKondisi(this)">
                        <span><i class="material-icons">trending_flat</i> Stabil</span>
                    </label>
                    <label class="ss-pill ss-pill-down">
                        <input type="radio" name="ssAsmKondisi" value="Memburuk" onchange="ssOnAsmKondisi(this)">
                        <span><i class="material-icons">trending_down</i> Memburuk</span>
                    </label>
                </div>
            </div>

            <!-- Severity -->
            <div class="ss-block">
                <label class="ss-block-label">SEVERITY <span style="color:#94a3b8;font-weight:normal;">(opsional)</span></label>
                <div class="ss-pill-group">
                    <label class="ss-pill ss-sev-1">
                        <input type="radio" name="ssAsmSeverity" value="Ringan" onchange="ssOnAsmSeverity(this)">
                        <span>Ringan</span>
                    </label>
                    <label class="ss-pill ss-sev-2">
                        <input type="radio" name="ssAsmSeverity" value="Sedang" onchange="ssOnAsmSeverity(this)">
                        <span>Sedang</span>
                    </label>
                    <label class="ss-pill ss-sev-3">
                        <input type="radio" name="ssAsmSeverity" value="Berat" onchange="ssOnAsmSeverity(this)">
                        <span>Berat</span>
                    </label>
                    <label class="ss-pill ss-sev-4">
                        <input type="radio" name="ssAsmSeverity" value="Kritis" onchange="ssOnAsmSeverity(this)">
                        <span>Kritis</span>
                    </label>
                </div>
            </div>

            <!-- Hidden textarea (final → DB kolom penilaian) -->
            <textarea name="assessment" class="soapie-textarea" data-section="assessment" style="display:none;"></textarea>

            <!-- Catatan Asesmen -->
            <div class="ss-block">
                <label class="ss-block-label">CATATAN ASESMEN <span style="color:#94a3b8;font-weight:normal;">(reasoning klinis, komorbid, dll)</span></label>
                <textarea id="ssAsmCatatan" class="ss-catatan" rows="3" placeholder="Tulis penilaian klinis, komorbiditas, atau pertimbangan lain..." oninput="ssOnAsmCatatanInput()"></textarea>
            </div>

        </div>
        <?php
    }

    // ============================================================
    // Helper: render structured form untuk PLAN section
    //   - Quick Plan: checkbox dari template (default + per-poli)
    //   - Kontrol Kembali: pill radio (Tidak/3hr/1mg/2mg/1bln/Sesuai)
    //   - Rujukan: pill radio (Tidak/Spesialis/RS Lain) + conditional input
    //   - Catatan Plan: free text
    // ============================================================
    function renderPlanStructured($tpl) {
        $def  = $tpl['default']  ?? [];
        $spec = $tpl['spesifik'] ?? [];
        ?>
        <div class="ss-form" data-section="plan">

            <!-- Quick Plan (checkbox) -->
            <?php if (!empty($def) || !empty($spec)): ?>
            <div class="ss-block">
                <label class="ss-block-label">QUICK PLAN <span style="color:#94a3b8;font-weight:normal;">(centang yang sesuai)</span></label>
                <div class="ss-checks">
                    <?php
                    $renderPlanLabel = function($txt) {
                        if (strpos($txt, '...') === false) {
                            return '<span>' . htmlspecialchars($txt) . '</span>';
                        }
                        $parts = explode('...', $txt);
                        $out = '<span class="ss-label-text">';
                        foreach ($parts as $i => $part) {
                            $out .= htmlspecialchars($part);
                            if ($i < count($parts) - 1) {
                                $out .= '<input type="text" class="ss-plan-input" placeholder="..." size="3" oninput="ssOnPlanInputDots(this)" onclick="event.stopPropagation()">';
                            }
                        }
                        $out .= '</span>';
                        return $out;
                    };
                    ?>
                    <?php foreach ($spec as $txt): ?>
                        <label class="ss-check ss-check-poli<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>" title="Khusus poli">
                            <input type="checkbox" class="ss-plan-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnPlanCheck(this)">
                            <?= $renderPlanLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php foreach ($def as $txt): ?>
                        <label class="ss-check<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>">
                            <input type="checkbox" class="ss-plan-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnPlanCheck(this)">
                            <?= $renderPlanLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Kontrol Kembali -->
            <div class="ss-block">
                <label class="ss-block-label">KONTROL KEMBALI</label>
                <div class="ss-pill-group">
                    <?php
                    $kontrol_options = ['Tidak perlu', '3 hari', '1 minggu', '2 minggu', '1 bulan', 'Sesuai kondisi'];
                    foreach ($kontrol_options as $opt):
                    ?>
                        <label class="ss-pill ss-pill-flat">
                            <input type="radio" name="ssPlanKontrol" value="<?= htmlspecialchars($opt) ?>" onchange="ssOnPlanKontrol(this)">
                            <span><?= htmlspecialchars($opt) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Rujukan -->
            <div class="ss-block">
                <label class="ss-block-label">RUJUKAN</label>
                <div class="ss-pill-group">
                    <label class="ss-pill ss-ruj-no">
                        <input type="radio" name="ssPlanRujuk" value="Tidak" onchange="ssOnPlanRujuk(this)" checked>
                        <span><i class="material-icons">block</i> Tidak</span>
                    </label>
                    <label class="ss-pill ss-ruj-sp">
                        <input type="radio" name="ssPlanRujuk" value="Dokter Spesialis" onchange="ssOnPlanRujuk(this)">
                        <span><i class="material-icons">medical_services</i> Dokter Spesialis</span>
                    </label>
                    <label class="ss-pill ss-ruj-rs">
                        <input type="radio" name="ssPlanRujuk" value="RS Lain" onchange="ssOnPlanRujuk(this)">
                        <span><i class="material-icons">local_hospital</i> RS Lain</span>
                    </label>
                </div>
                <div class="ss-rujuk-wrap" style="display:none;" id="ssPlanRujukWrap">
                    <input type="text" id="ssPlanRujukTujuan" class="ss-rujuk-tujuan"
                           placeholder="Ketik nama dokter (autocomplete) — boleh manual untuk luar RS..."
                           autocomplete="off"
                           oninput="ssOnPlanRujukTujuan(this)">
                    <div class="ss-rujuk-results" id="ssPlanRujukResults"></div>
                </div>
            </div>

            <!-- Hidden textarea (final → DB kolom rtl) -->
            <textarea name="plan" class="soapie-textarea" data-section="plan" style="display:none;"></textarea>

            <!-- Catatan Plan -->
            <div class="ss-block">
                <label class="ss-block-label">CATATAN PLAN <span style="color:#94a3b8;font-weight:normal;">(detail tambahan, instruksi khusus, dll)</span></label>
                <textarea id="ssPlanCatatan" class="ss-catatan" rows="3" placeholder="Tulis instruksi tambahan, detail pemeriksaan penunjang, dll..." oninput="ssOnPlanCatatanInput()"></textarea>
            </div>

        </div>
        <?php
    }

    // ============================================================
    // Helper: render structured form untuk INTERVENTION section
    //   - Prosedur ICD-9: autocomplete (sync ke prosedur_pasien untuk klaim BPJS)
    //   - Quick Action: checkbox dari template (intervensi non-coded)
    //   - Catatan Intervensi: free text
    // ============================================================
    function renderInterventionStructured($tpl) {
        $def  = $tpl['default']  ?? [];
        $spec = $tpl['spesifik'] ?? [];
        ?>
        <div class="ss-form" data-section="intervention">

            <!-- Prosedur ICD-9 Autocomplete -->
            <div class="ss-block">
                <label class="ss-block-label">PROSEDUR / TINDAKAN ICD-9 <span style="color:#94a3b8;font-weight:normal;">(klaim BPJS — auto-sync ke prosedur_pasien)</span></label>
                <div class="ss-dx-search-wrap">
                    <input type="text" class="ss-dx-search" id="ssIcd9Search"
                           placeholder="Cari prosedur atau kode ICD-9..." autocomplete="off">
                    <div class="ss-dx-results" id="ssIcd9Results"></div>
                </div>
                <div class="ss-dx-list" id="ssIcd9List"></div>
            </div>

            <!-- Quick Action (checkbox) -->
            <?php if (!empty($def) || !empty($spec)): ?>
            <div class="ss-block">
                <label class="ss-block-label">QUICK INSTRUKSI / TINDAKAN <span style="color:#94a3b8;font-weight:normal;">(non-coded — instruksi, tindakan, edukasi)</span></label>
                <div class="ss-checks">
                    <?php
                    $renderIntLabel = function($txt) {
                        if (strpos($txt, '...') === false) {
                            return '<span>' . htmlspecialchars($txt) . '</span>';
                        }
                        $parts = explode('...', $txt);
                        $out = '<span class="ss-label-text">';
                        foreach ($parts as $i => $part) {
                            $out .= htmlspecialchars($part);
                            if ($i < count($parts) - 1) {
                                $out .= '<input type="text" class="ss-int-input" placeholder="..." size="3" oninput="ssOnIntInputDots(this)" onclick="event.stopPropagation()">';
                            }
                        }
                        $out .= '</span>';
                        return $out;
                    };
                    ?>
                    <?php foreach ($spec as $txt): ?>
                        <label class="ss-check ss-check-poli<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>" title="Khusus poli">
                            <input type="checkbox" class="ss-int-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnIntCheck(this)">
                            <?= $renderIntLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php foreach ($def as $txt): ?>
                        <label class="ss-check<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>">
                            <input type="checkbox" class="ss-int-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnIntCheck(this)">
                            <?= $renderIntLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Hidden textarea (final → DB kolom instruksi) -->
            <textarea name="intervention" class="soapie-textarea" data-section="intervention" style="display:none;"></textarea>

            <!-- Catatan Instruksi / Implementasi -->
            <div class="ss-block">
                <label class="ss-block-label">CATATAN <span style="color:#94a3b8;font-weight:normal;">(instruksi dokter, tindakan yang dilakukan, edukasi, dll)</span></label>
                <textarea id="ssIntCatatan" class="ss-catatan" rows="3" placeholder="Tulis instruksi/order, tindakan yang dilakukan, edukasi yang diberikan..." oninput="ssOnIntCatatanInput()"></textarea>
            </div>

        </div>
        <?php
    }

    // ============================================================
    // Helper: render structured form untuk EVALUATION section
    //   - Outcome: pill radio (Tercapai / Sebagian / Belum)
    //   - Kondisi post-intervensi: pill radio (Membaik / Stabil / Memburuk)
    //   - Disposisi: pill radio (Pulang / Observasi / Rawat Inap / Rujuk)
    //   - Quick Findings: checkbox dari template
    //   - Catatan: free text
    // ============================================================
    function renderEvaluationStructured($tpl) {
        $def  = $tpl['default']  ?? [];
        $spec = $tpl['spesifik'] ?? [];
        ?>
        <div class="ss-form" data-section="evaluation">

            <!-- Outcome -->
            <div class="ss-block">
                <label class="ss-block-label">OUTCOME / TUJUAN INTERVENSI</label>
                <div class="ss-pill-group">
                    <label class="ss-pill ss-out-yes">
                        <input type="radio" name="ssEvalOutcome" value="Tercapai" onchange="ssOnEvalOutcome(this)">
                        <span><i class="material-icons">check_circle</i> Tercapai</span>
                    </label>
                    <label class="ss-pill ss-out-partial">
                        <input type="radio" name="ssEvalOutcome" value="Tercapai Sebagian" onchange="ssOnEvalOutcome(this)">
                        <span><i class="material-icons">remove_circle</i> Tercapai Sebagian</span>
                    </label>
                    <label class="ss-pill ss-out-no">
                        <input type="radio" name="ssEvalOutcome" value="Belum Tercapai" onchange="ssOnEvalOutcome(this)">
                        <span><i class="material-icons">cancel</i> Belum Tercapai</span>
                    </label>
                </div>
            </div>

            <!-- Kondisi post-intervensi -->
            <div class="ss-block">
                <label class="ss-block-label">KONDISI POST-INTERVENSI</label>
                <div class="ss-pill-group">
                    <label class="ss-pill ss-pill-up">
                        <input type="radio" name="ssEvalKondisi" value="Membaik" onchange="ssOnEvalKondisi(this)">
                        <span><i class="material-icons">trending_up</i> Membaik</span>
                    </label>
                    <label class="ss-pill ss-pill-flat">
                        <input type="radio" name="ssEvalKondisi" value="Stabil" onchange="ssOnEvalKondisi(this)">
                        <span><i class="material-icons">trending_flat</i> Stabil</span>
                    </label>
                    <label class="ss-pill ss-pill-down">
                        <input type="radio" name="ssEvalKondisi" value="Memburuk" onchange="ssOnEvalKondisi(this)">
                        <span><i class="material-icons">trending_down</i> Memburuk</span>
                    </label>
                </div>
            </div>

            <!-- Disposisi -->
            <div class="ss-block">
                <label class="ss-block-label">DISPOSISI / TINDAK LANJUT</label>
                <div class="ss-pill-group">
                    <label class="ss-pill ss-disp-pulang">
                        <input type="radio" name="ssEvalDisposisi" value="Pulang" onchange="ssOnEvalDisposisi(this)">
                        <span><i class="material-icons">home</i> Pulang</span>
                    </label>
                    <label class="ss-pill ss-disp-obs">
                        <input type="radio" name="ssEvalDisposisi" value="Observasi" onchange="ssOnEvalDisposisi(this)">
                        <span><i class="material-icons">visibility</i> Observasi</span>
                    </label>
                    <label class="ss-pill ss-disp-ranap">
                        <input type="radio" name="ssEvalDisposisi" value="Rawat Inap" onchange="ssOnEvalDisposisi(this)">
                        <span><i class="material-icons">hotel</i> Rawat Inap</span>
                    </label>
                    <label class="ss-pill ss-disp-rujuk">
                        <input type="radio" name="ssEvalDisposisi" value="Rujuk" onchange="ssOnEvalDisposisi(this)">
                        <span><i class="material-icons">call_made</i> Rujuk</span>
                    </label>
                </div>
            </div>

            <!-- Quick Findings -->
            <?php if (!empty($def) || !empty($spec)): ?>
            <div class="ss-block">
                <label class="ss-block-label">QUICK FINDINGS <span style="color:#94a3b8;font-weight:normal;">(centang yang sesuai)</span></label>
                <div class="ss-checks">
                    <?php
                    $renderEvalLabel = function($txt) {
                        if (strpos($txt, '...') === false) {
                            return '<span>' . htmlspecialchars($txt) . '</span>';
                        }
                        $parts = explode('...', $txt);
                        $out = '<span class="ss-label-text">';
                        foreach ($parts as $i => $part) {
                            $out .= htmlspecialchars($part);
                            if ($i < count($parts) - 1) {
                                $out .= '<input type="text" class="ss-eval-input" placeholder="..." size="3" oninput="ssOnEvalInputDots(this)" onclick="event.stopPropagation()">';
                            }
                        }
                        $out .= '</span>';
                        return $out;
                    };
                    ?>
                    <?php foreach ($spec as $txt): ?>
                        <label class="ss-check ss-check-poli<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>" title="Khusus poli">
                            <input type="checkbox" class="ss-eval-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnEvalCheck(this)">
                            <?= $renderEvalLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                    <?php foreach ($def as $txt): ?>
                        <label class="ss-check<?= strpos($txt, '...') !== false ? ' has-input' : '' ?>">
                            <input type="checkbox" class="ss-eval-cb" data-text="<?= htmlspecialchars($txt, ENT_QUOTES) ?>" onchange="ssOnEvalCheck(this)">
                            <?= $renderEvalLabel($txt) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Hidden textarea (final → DB kolom evaluasi) -->
            <textarea name="evaluation" class="soapie-textarea" data-section="evaluation" style="display:none;"></textarea>

            <!-- Catatan Evaluasi -->
            <div class="ss-block">
                <label class="ss-block-label">CATATAN EVALUASI <span style="color:#94a3b8;font-weight:normal;">(detail respon pasien, observasi pasca intervensi)</span></label>
                <textarea id="ssEvalCatatan" class="ss-catatan" rows="3" placeholder="Tulis respon pasien, hasil observasi, atau catatan lain..." oninput="ssOnEvalCatatanInput()"></textarea>
            </div>

        </div>
        <?php
    }

    // Jika bukan dokter umum, lakukan validasi kepemilikan
    if(!$is_dokter_umum) {
        // Untuk rawat inap, cek di tabel dpjp_ranap
        // Juga cek di ranap_gabung: jika anak rawat gabung, cek akses via no_rawat induk (ibu)
        $cek_akses = getOne("SELECT COUNT(*) FROM dpjp_ranap 
                            WHERE no_rawat='$norawat' 
                            AND kd_dokter='$kd_dokter'");
        
        // Jika tidak ditemukan, cek apakah ini rawat gabung (anak) → cek akses via induk
        if($cek_akses == 0){
            $cek_gabung_akses = getOne("SELECT COUNT(*) FROM ranap_gabung rg
                                        INNER JOIN dpjp_ranap dp ON dp.no_rawat = rg.no_rawat
                                        WHERE rg.no_rawat2 = '$norawat'
                                        AND dp.kd_dokter = '$kd_dokter'");
            
            if($cek_gabung_akses == 0){
                // Pasien bukan milik dokter ini, redirect
                echo "<script>alert('Anda tidak memiliki akses ke data pasien ini!');</script>";
                JSRedirect("index.php?act=Pasien");
                exit();
            }
        }
    }

    // ============================================================
    // CEK RAWAT GABUNG: Dilakukan LEBIH DULU sebelum cek status bayar
    // Bayi rawat gabung: billing & status pulang ikut induk (ibu)
    // Tabel ranap_gabung: no_rawat (ibu) -> no_rawat2 (anak)
    // ============================================================
    $is_rawat_gabung = false;
    $norawat_induk = '';
    
    $cek_gabung = bukaquery("SELECT no_rawat FROM ranap_gabung WHERE no_rawat2 = '$norawat'");
    $rs_gabung = mysqli_fetch_array($cek_gabung);
    
    if($rs_gabung) {
        $is_rawat_gabung = true;
        $norawat_induk = $rs_gabung['no_rawat'];
    }

    // Validasi status pembayaran: pasien ranap yang sudah bayar tidak bisa lagi
    // menambah/mengubah RME maupun billing — redirect ke halaman riwayat (read-only)
    // Untuk bayi rawat gabung: status bayar & pulang ikut no_rawat induk (ibu)
    $norawat_cek_bayar = $is_rawat_gabung ? $norawat_induk : $norawat;
    $status_bayar_inap = getOne("SELECT status_bayar FROM reg_periksa 
                                 WHERE no_rawat='$norawat_cek_bayar'");
    if($status_bayar_inap == 'Sudah Bayar'){
        echo "<script>alert('Pasien ini sudah selesai melakukan pembayaran.\nData RME hanya dapat dilihat melalui halaman Riwayat Perawatan.');</script>";
        JSRedirect("index.php?act=Pemeriksaanriwayat&rnw=" . urlencode($_GET['rnw']) . "&rm=" . urlencode($_GET['rm']));
        exit();
    }

    // Ambil data pasien dengan info kamar untuk rawat inap
    // Kondisi kd_dokter hanya ditambahkan jika bukan dokter umum
    $where_dokter = "";
    if(!$is_dokter_umum) {
        // Join dengan dpjp_ranap untuk filter dokter spesialis
        $where_dokter = "AND EXISTS (SELECT 1 FROM dpjp_ranap 
                                     WHERE dpjp_ranap.no_rawat = reg_periksa.no_rawat 
                                     AND dpjp_ranap.kd_dokter = '$kd_dokter')";
    }
    
    if($is_rawat_gabung) {
        // RAWAT GABUNG: Data pasien dari no_rawat anak, data kamar dari no_rawat induk (ibu)
        $where_dokter_gabung = "";
        if(!$is_dokter_umum) {
            // Cek akses dokter di no_rawat induk (ibu) ATAU di no_rawat anak
            $where_dokter_gabung = "AND (EXISTS (SELECT 1 FROM dpjp_ranap WHERE dpjp_ranap.no_rawat = '$norawat' AND dpjp_ranap.kd_dokter = '$kd_dokter')
                                     OR EXISTS (SELECT 1 FROM dpjp_ranap WHERE dpjp_ranap.no_rawat = '$norawat_induk' AND dpjp_ranap.kd_dokter = '$kd_dokter'))";
        }
        
        $querypasien = bukaquery("SELECT pasien.no_rkm_medis, pasien.nm_pasien, pasien.jk, pasien.tmp_lahir, 
                                         pasien.tgl_lahir, pasien.alamat, reg_periksa.no_rawat, reg_periksa.tgl_registrasi,
                                         reg_periksa.jam_reg, kamar_inap.kd_kamar, kamar.kelas, bangsal.nm_bangsal,
                                         kamar_inap.diagnosa_awal, kamar_inap.diagnosa_akhir,
                                         kamar_inap.tgl_masuk, kamar_inap.jam_masuk,
                                         kamar_inap.tgl_keluar, kamar_inap.jam_keluar
                                  FROM reg_periksa 
                                  INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
                                  INNER JOIN kamar_inap ON kamar_inap.no_rawat = '$norawat_induk'
                                  INNER JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
                                  INNER JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
                                  WHERE reg_periksa.no_rawat = '$norawat' 
                                  AND reg_periksa.no_rkm_medis = '$norm'
                                  $where_dokter_gabung
                                  ORDER BY kamar_inap.tgl_masuk DESC, kamar_inap.jam_masuk DESC
                                  LIMIT 1");
    } else {
        // RAWAT INAP BIASA: Join langsung ke kamar_inap
        $querypasien = bukaquery("SELECT pasien.no_rkm_medis, pasien.nm_pasien, pasien.jk, pasien.tmp_lahir, 
                                         pasien.tgl_lahir, pasien.alamat, reg_periksa.no_rawat, reg_periksa.tgl_registrasi,
                                         reg_periksa.jam_reg, kamar_inap.kd_kamar, kamar.kelas, bangsal.nm_bangsal,
                                         kamar_inap.diagnosa_awal, kamar_inap.diagnosa_akhir,
                                         kamar_inap.tgl_masuk, kamar_inap.jam_masuk,
                                         kamar_inap.tgl_keluar, kamar_inap.jam_keluar
                                  FROM reg_periksa 
                                  INNER JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
                                  INNER JOIN kamar_inap ON reg_periksa.no_rawat = kamar_inap.no_rawat
                                  INNER JOIN kamar ON kamar_inap.kd_kamar = kamar.kd_kamar
                                  INNER JOIN bangsal ON kamar.kd_bangsal = bangsal.kd_bangsal
                                  WHERE reg_periksa.no_rawat = '$norawat' 
                                  AND reg_periksa.no_rkm_medis = '$norm'
                                  $where_dokter");
    }
    
    $datapasien = mysqli_fetch_array($querypasien);
    
    // Double check jika data tidak ditemukan
    if(!$datapasien){
        echo "<script>alert('Data pasien tidak ditemukan!');</script>";
        JSRedirect("index.php?act=Pasien");
        exit();
    }
?>

<style>
    .nav-tabs {
        border-bottom: 2px solid #ddd;
        margin-bottom: 20px;
    }
    .nav-tabs > li.active > a, 
    .nav-tabs > li.active > a:focus, 
    .nav-tabs > li.active > a:hover {
        color: #e91e63;
        background-color: #fff;
        border: 1px solid #ddd;
        border-bottom-color: transparent;
        font-weight: 600;
    }
    .nav-tabs > li > a {
        color: #555;
        font-weight: 500;
        padding: 10px 20px;
    }
    .nav-tabs > li > a:hover {
        background-color: #f5f5f5;
        border-color: #ddd;
    }
    
    /* Sub-tabs styling (untuk E-Resep dan SOAPIE) */
    .sub-tabs, .nav-tabs-secondary {
        border-bottom: none;
        margin-bottom: 0;
    }
    .sub-tabs > li > a,
    .nav-tabs-secondary > li > a {
        color: #555;
        font-weight: 500;
        padding: 10px 15px;
        border-radius: 0;
        border: none;
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
    }
    .sub-tabs > li > a:hover,
    .nav-tabs-secondary > li > a:hover {
        color: #00bcd4;
        background-color: #f5f5f5;
        border-bottom: 3px solid #e0e0e0;
    }
    .sub-tabs > li.active > a,
    .sub-tabs > li.active > a:focus,
    .sub-tabs > li.active > a:hover,
    .nav-tabs-secondary > li.active > a,
    .nav-tabs-secondary > li.active > a:focus,
    .nav-tabs-secondary > li.active > a:hover {
        color: #00bcd4;
        background-color: #fff;
        border-bottom: 3px solid #00bcd4 !important;
        border-top: none;
        border-left: none;
        border-right: none;
        font-weight: 600;
    }
    
    .form-section {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 15px;
    }
    .form-section-title {
        font-weight: 600;
        color: #e91e63;
        margin-bottom: 10px;
        font-size: 14px;
    }

/* Compact TTV Grid - Premium Color-Coded */
.ttv-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
    margin-bottom: 15px;
}

.ttv-item {
    display: flex;
    flex-direction: column;
    min-width: 0;
    position: relative;
}

.ttv-item label {
    font-size: 9px;
    font-weight: 700;
    color: #555;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.ttv-item input,
.ttv-item select {
    height: 36px;
    padding: 6px 10px;
    border: 1px solid #e0e0e0;
    border-left: 3px solid #ccc;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    background: #fafafa;
    width: 100%;
}

.ttv-item input:focus,
.ttv-item select:focus {
    background: white;
    border-color: currentColor;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    outline: none;
    transform: translateY(-1px);
}

.ttv-item input::placeholder {
    color: #bbb;
    font-size: 12px;
}

/* Color Coding untuk setiap vital sign */
.ttv-item:nth-child(1) input { border-left-color: #e53935; } /* TD - Merah */
.ttv-item:nth-child(1) label { color: #e53935; }
.ttv-item:nth-child(1) input:focus { border-left-color: #e53935; box-shadow: 0 2px 8px rgba(229,57,53,0.2); }

.ttv-item:nth-child(2) input { border-left-color: #ec407a; } /* Nadi - Pink */
.ttv-item:nth-child(2) label { color: #ec407a; }
.ttv-item:nth-child(2) input:focus { border-left-color: #ec407a; box-shadow: 0 2px 8px rgba(236,64,122,0.2); }

.ttv-item:nth-child(3) input { border-left-color: #42a5f5; } /* RR - Biru */
.ttv-item:nth-child(3) label { color: #42a5f5; }
.ttv-item:nth-child(3) input:focus { border-left-color: #42a5f5; box-shadow: 0 2px 8px rgba(66,165,245,0.2); }

.ttv-item:nth-child(4) input { border-left-color: #ff9800; } /* Suhu - Orange */
.ttv-item:nth-child(4) label { color: #ff9800; }
.ttv-item:nth-child(4) input:focus { border-left-color: #ff9800; box-shadow: 0 2px 8px rgba(255,152,0,0.2); }

.ttv-item:nth-child(5) input { border-left-color: #26c6da; } /* SpO2 - Cyan */
.ttv-item:nth-child(5) label { color: #26c6da; }
.ttv-item:nth-child(5) input:focus { border-left-color: #26c6da; box-shadow: 0 2px 8px rgba(38,198,218,0.2); }

.ttv-item:nth-child(6) input { border-left-color: #66bb6a; } /* BB - Hijau */
.ttv-item:nth-child(6) label { color: #66bb6a; }
.ttv-item:nth-child(6) input:focus { border-left-color: #66bb6a; box-shadow: 0 2px 8px rgba(102,187,106,0.2); }

.ttv-item:nth-child(7) input { border-left-color: #9ccc65; } /* TB - Hijau Muda */
.ttv-item:nth-child(7) label { color: #9ccc65; }
.ttv-item:nth-child(7) input:focus { border-left-color: #9ccc65; box-shadow: 0 2px 8px rgba(156,204,101,0.2); }

/* Grid kedua - Data Tambahan */
.ttv-grid.secondary {
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-top: 15px;
}

.ttv-grid.secondary .ttv-item:nth-child(1) input,
.ttv-grid.secondary .ttv-item:nth-child(1) select { 
    border-left-color: #ab47bc; /* Kesadaran - Ungu */
}
.ttv-grid.secondary .ttv-item:nth-child(1) label { color: #ab47bc; }

.ttv-grid.secondary .ttv-item:nth-child(2) input { 
    border-left-color: #5c6bc0; /* GCS - Indigo */
}
.ttv-grid.secondary .ttv-item:nth-child(2) label { color: #5c6bc0; }

.ttv-grid.secondary .ttv-item:nth-child(3) input { 
    border-left-color: #ff7043; /* Alergi - Deep Orange */
}
.ttv-grid.secondary .ttv-item:nth-child(3) label { color: #ff7043; }

.ttv-grid.secondary .ttv-item:nth-child(4) input { 
    border-left-color: #8d6e63; /* Lingkar Perut - Brown */
}
.ttv-grid.secondary .ttv-item:nth-child(4) label { color: #8d6e63; }

/* Section Title */
.section-title {
    font-size: 15px;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 6px;
}

.section-title i {
    font-size: 20px;
    color: #e91e63;
}

/* Form Control Modern */
.form-control-modern {
    width: 100%;
    height: 36px;
    padding: 6px 10px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-size: 13px;
    background: #fafafa;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    background: white;
    border-color: #ab47bc;
    box-shadow: 0 2px 8px rgba(171,71,188,0.2);
    outline: none;
    transform: translateY(-1px);
}

/* Hover effect untuk semua input */
.ttv-item input:hover,
.ttv-item select:hover {
    background: white;
    border-color: #ccc;
}

/* Responsive */
@media (max-width: 1200px) {
    .ttv-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
    }
}

@media (max-width: 768px) {
    .ttv-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .ttv-item label {
        font-size: 10px;
    }
    
    .ttv-item input,
    .ttv-item select {
        height: 38px;
        font-size: 14px;
    }
}

/* Button Load TTV */
.btn-load-ttv {
    display: flex;
    align-items: center;
    gap: 5px;
    height: 32px;
    padding: 0 15px;
    background: linear-gradient(135deg, #0F6FB2 0%, #5FD38D 100%);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
}

.btn-load-ttv:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-load-ttv:active {
    transform: translateY(0);
}

.btn-load-ttv i {
    font-size: 16px;
}

.btn-load-ttv.loading {
    opacity: 0.7;
    pointer-events: none;
}

.btn-load-ttv.loading i {
    animation: rotate 1s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.collapsible-header {
    cursor: pointer;
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    transition: all 0.3s ease;
}

.collapsible-header:hover {
    opacity: 0.9;
}

.toggle-icon {
    transition: transform 0.3s ease;
    font-size: 24px;
}

.collapsible-header.collapsed .toggle-icon {
    transform: rotate(180deg);
}

.collapsible-content {
    overflow: hidden;
    transition: all 0.3s ease;
}

/* ========================================
   MENU RME DROPDOWN (pemeriksaaninap.php)
   ======================================== */
.btn-rme-toggle {
    height: 34px;
    width: 34px;
    padding: 0;
    border-radius: 999px;
    border: 2px solid #667eea;
    background: white;
    color: #667eea;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(102, 126, 234, 0.3);
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.btn-rme-toggle:hover,
.btn-rme-toggle.active {
    background: #667eea;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-rme-toggle i {
    font-size: 18px;
}

.dropdown-rme-wrapper {
    position: relative !important;
    flex-shrink: 0;
}

.dropdown-rme-menu {
    position: fixed !important;
    z-index: 999999 !important;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2) !important;
    border-radius: 8px !important;
    border: 1px solid #ddd !important;
    min-width: 200px !important;
    background: white !important;
    display: none;
    padding: 5px 0 !important;
    list-style: none !important;
}

.dropdown-rme-menu.show {
    display: block;
}

.dropdown-rme-menu > li {
    list-style: none;
    padding: 0;
    margin: 0;
}

.dropdown-rme-menu > li > a {
    padding: 10px 15px;
    font-size: 13px;
    color: #333;
    display: block;
    text-decoration: none;
    transition: background-color 0.15s ease;
}

.dropdown-rme-menu > li > a:hover {
    background-color: #f5f5f5;
    color: #667eea;
}

.dropdown-rme-menu .has-submenu {
    position: relative;
}

.dropdown-rme-menu .has-submenu > a {
    padding-right: 30px;
    cursor: pointer;
}

.dropdown-rme-menu .has-submenu > a:after {
    content: '\203A';
    position: absolute;
    right: 15px;
    font-size: 18px;
    font-weight: bold;
    transition: transform 0.2s ease;
}

.dropdown-rme-menu .has-submenu.active > a:after {
    transform: rotate(90deg);
}

.dropdown-rme-menu .dropdown-submenu {
    position: absolute;
    top: 0;
    left: 100%;
    z-index: 100000;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    border-radius: 8px;
    border: 1px solid #ddd;
    margin-left: 5px;
    min-width: 220px;
    background: white;
    padding: 5px 0;
    list-style: none;
    display: none;
}

.dropdown-rme-menu .has-submenu.active > .dropdown-submenu {
    display: block;
}

.dropdown-rme-menu .dropdown-submenu > li {
    list-style: none;
    padding: 0;
    margin: 0;
}

.dropdown-rme-menu .dropdown-submenu > li > a {
    padding: 10px 15px;
    font-size: 13px;
    color: #333;
    display: block;
    text-decoration: none;
    transition: background-color 0.15s ease;
}

.dropdown-rme-menu .dropdown-submenu > li > a:hover {
    background-color: #f5f5f5;
    color: #667eea;
}

.dropdown-rme-menu .divider {
    height: 1px;
    margin: 5px 0;
    background: #eee;
}

/* ========================================
   RME TAB BAR (Pill / Capsule Tabs) - RANAP
   ======================================== */
/* Wrapper area tab - flex wrap ke bawah */
.rme-tab-scroll-area {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
    min-width: 0;
    padding: 2px 0;
}

.rme-tab-bar {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    background: #f1f5f9;
    border-radius: 12px 12px 0 0;
    border: 1px solid #e2e8f0;
    border-bottom: none;
    overflow: visible;
}

.rme-tab {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 7px 16px;
    background: #fff;
    color: #64748b;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(0,0,0,0.04);
    cursor: pointer;
    white-space: nowrap;
    font-size: 12.5px;
    font-weight: 500;
    transition: all 0.25s;
    min-width: unset;
    max-width: 220px;
    position: relative;
    user-select: none;
}

.rme-tab:hover {
    border-color: #c7d2fe;
    color: #4338ca;
    background: #fefeff;
}

.rme-tab.active {
    background: #4338ca;
    color: #fff;
    border-color: #4338ca;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(67,56,202,0.3);
}

.rme-tab-title {
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
}

.rme-tab-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    font-size: 13px;
    line-height: 1;
    color: #94a3b8;
    transition: all 0.15s ease;
    flex-shrink: 0;
}

.rme-tab-close:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.rme-tab.active .rme-tab-close {
    color: rgba(255,255,255,0.7);
}

.rme-tab.active .rme-tab-close:hover {
    background: rgba(255,255,255,0.2);
    color: #fff;
}

/* Tab content */
.rme-tab-content {
    display: none;
}

.rme-tab-content.active {
    display: block;
}

.rme-tab-content-ajax {
    display: none;
}

.rme-tab-content-ajax.active {
    display: block;
}

/* Loading state inside tab */
.rme-tab-loading {
    text-align: center;
    padding: 60px 20px;
}

.rme-tab-loading i {
    font-size: 48px;
    color: #999;
    animation: spin 1s linear infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .rme-tab-bar {
        padding: 8px 10px;
    }
    .rme-tab {
        padding: 6px 12px;
        font-size: 12px;
    }
    .dropdown-rme-menu .dropdown-submenu {
        position: relative;
        left: 0;
        margin-left: 15px;
        margin-top: 5px;
        box-shadow: none;
        border-left: 2px solid #667eea;
    }
}

/* ============================================================
   SOAPIE Structured Form (inline — was extracted, now back inline)
   ============================================================ */
/* ============================================================
   SOAPIE Quick-Fill Chips (template per kd_poli)
   ============================================================ */
.qf-wrapper {
    margin-top: 8px;
    margin-bottom: 6px;
}
.qf-toggle {
    background: transparent;
    border: 1px dashed #cbd5e1;
    color: #64748b;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.15s;
}
.qf-toggle:hover { background: #f1f5f9; border-color: #94a3b8; color: #1e293b; }
.qf-toggle i { font-size: 14px; }
.qf-panel {
    margin-top: 6px;
    padding: 10px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    display: none;
}
.qf-panel.show { display: block; }
.qf-group-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin: 0 0 5px 0;
    letter-spacing: 0.5px;
}
.qf-group + .qf-group { margin-top: 8px; }
.qf-chips { display: flex; flex-wrap: wrap; gap: 5px; }
.qf-chip {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #334155;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s;
    line-height: 1.4;
}
.qf-chip:hover { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
.qf-chip:active { transform: scale(0.96); }
.qf-chip.is-poli { background: #fefce8; border-color: #fde68a; color: #854d0e; }
.qf-chip.is-poli:hover { background: #fef3c7; border-color: #f59e0b; color: #78350f; }

/* ============================================================
   SOAPIE Structured Form (per section, dimulai dari Subjective)
   ============================================================ */
.ss-form {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
}
.ss-block + .ss-block { margin-top: 14px; }
.ss-block-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    color: #475569;
    margin: 0 0 6px 0;
    text-transform: uppercase;
}

/* Checkbox keluhan */
.ss-checks {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.ss-check {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px 4px 6px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    font-size: 12px;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
    margin: 0;
}
.ss-check input[type="checkbox"] { margin: 0; cursor: pointer; }
.ss-check:hover { background: #eff6ff; border-color: #93c5fd; }
.ss-check input[type="checkbox"]:checked + span { color: #1d4ed8; font-weight: 600; }
.ss-check:has(input:checked) { background: #dbeafe; border-color: #3b82f6; }
.ss-check-poli { background: #fefce8; border-color: #fde68a; }
.ss-check-poli:hover { background: #fef3c7; border-color: #f59e0b; }
.ss-check-poli:has(input:checked) { background: #fde68a; border-color: #f59e0b; }
.ss-check-poli:has(input:checked) span { color: #78350f; }

/* Input inline untuk pattern "..." (mis. "Demam sejak [3] hari") */
.ss-keluhan-input,
.ss-obj-input,
.ss-asm-input,
.ss-plan-input,
.ss-int-input,
.ss-eval-input {
    width: 38px;
    margin: 0 3px;
    padding: 1px 4px;
    border: 1px solid #cbd5e1;
    border-radius: 4px;
    font-size: 12px;
    text-align: center;
    background: #fff;
    color: #1e293b;
    font-weight: 600;
    height: 22px;
    line-height: 1;
    transition: all 0.15s;
}
.ss-keluhan-input:focus,
.ss-obj-input:focus,
.ss-asm-input:focus,
.ss-plan-input:focus,
.ss-int-input:focus,
.ss-eval-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59,130,246,0.2);
}
.ss-check.has-input { padding-right: 8px; }
.ss-check-poli .ss-keluhan-input { border-color: #fcd34d; }
.ss-check-poli .ss-keluhan-input:focus { border-color: #f59e0b; box-shadow: 0 0 0 2px rgba(245,158,11,0.2); }
.ss-check:has(input.ss-keluhan-cb:checked) .ss-keluhan-input { border-color: #3b82f6; background: #fff; }

/* ============================================================
   OBJECTIVE — Status Per Organ (radio compact)
   ============================================================ */
.ss-organs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 6px;
}
.ss-organ-row {
    display: flex;
    align-items: center;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 4px 8px;
    gap: 8px;
}
.ss-organ-label {
    flex: 1;
    font-size: 12px;
    font-weight: 600;
    color: #334155;
    min-width: 0;
}
.ss-organ-radios { display: inline-flex; gap: 3px; }
.ss-organ-radio {
    display: inline-flex;
    align-items: center;
    margin: 0;
    padding: 3px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.12s;
    font-size: 11px;
    user-select: none;
}
.ss-organ-radio input { display: none; }
.ss-organ-radio span { font-weight: 600; color: #64748b; }

/* Hover */
.ss-r-n:hover  { background: #d1fae5; border-color: #6ee7b7; }
.ss-r-a:hover  { background: #fee2e2; border-color: #fca5a5; }
.ss-r-tp:hover { background: #f1f5f9; border-color: #cbd5e1; }

/* Checked states */
.ss-r-n:has(input:checked)  { background: #10b981; border-color: #059669; }
.ss-r-n:has(input:checked) span  { color: #fff; }
.ss-r-a:has(input:checked)  { background: #ef4444; border-color: #dc2626; }
.ss-r-a:has(input:checked) span  { color: #fff; }
.ss-r-tp:has(input:checked) { background: #94a3b8; border-color: #64748b; }
.ss-r-tp:has(input:checked) span { color: #fff; }

/* ============================================================
   ASSESSMENT — Diagnosis Kerja Autocomplete + Pill Radios
   ============================================================ */
.ss-dx-search-wrap { position: relative; }
.ss-dx-search {
    width: 100%;
    padding: 8px 10px 8px 32px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    background: #fff
        url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2364748b' viewBox='0 0 16 16'><path d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/></svg>")
        no-repeat 10px center;
}
.ss-dx-search:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,0.1); }

.ss-dx-results {
    position: absolute;
    top: 100%; left: 0; right: 0;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    max-height: 280px;
    overflow-y: auto;
    z-index: 100;
    margin-top: 4px;
    display: none;
}
.ss-dx-results.show { display: block; }
.ss-dx-result-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    transition: background 0.1s;
}
.ss-dx-result-item:last-child { border-bottom: none; }
.ss-dx-result-item:hover, .ss-dx-result-item.active { background: #faf5ff; }
.ss-dx-result-code {
    display: inline-block;
    background: #ede9fe;
    color: #6d28d9;
    padding: 1px 6px;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    font-weight: 700;
    margin-right: 6px;
}
.ss-dx-result-empty { padding: 12px; text-align: center; color: #94a3b8; font-size: 12px; font-style: italic; }

/* Diagnosa chip list */
.ss-dx-list { margin-top: 8px; display: flex; flex-direction: column; gap: 4px; }
.ss-dx-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px 6px 10px;
    background: #faf5ff;
    border: 1px solid #ddd6fe;
    border-radius: 6px;
    font-size: 12px;
    color: #4c1d95;
}
.ss-dx-chip .ss-dx-chip-num {
    background: #7c3aed; color: #fff;
    width: 18px; height: 18px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 10px; font-weight: 700;
    flex-shrink: 0;
}
.ss-dx-chip .ss-dx-chip-name { flex: 1; font-weight: 600; }
.ss-dx-chip .ss-dx-chip-code {
    background: #fff; color: #6d28d9;
    padding: 1px 6px; border-radius: 3px;
    font-family: 'Courier New', monospace; font-size: 11px;
    border: 1px solid #ddd6fe;
}
.ss-dx-chip .ss-dx-chip-remove {
    background: transparent; border: none; cursor: pointer;
    color: #94a3b8; padding: 2px; line-height: 1;
    border-radius: 50%;
    transition: all 0.15s;
}
.ss-dx-chip .ss-dx-chip-remove:hover { background: #fee2e2; color: #dc2626; }
.ss-dx-chip .ss-dx-chip-remove i { font-size: 16px; }

/* Pill radio group (Evaluasi Kondisi, Severity) */
.ss-pill-group {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.ss-pill {
    display: inline-flex;
    align-items: center;
    margin: 0;
    padding: 6px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    background: #fff;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
}
.ss-pill input { display: none; }
.ss-pill span {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.ss-pill span i { font-size: 16px; }

/* Evaluasi pill colors */
.ss-pill-up:hover    { background: #d1fae5; border-color: #6ee7b7; }
.ss-pill-flat:hover  { background: #dbeafe; border-color: #93c5fd; }
.ss-pill-down:hover  { background: #fee2e2; border-color: #fca5a5; }
.ss-pill-up:has(input:checked)    { background: #10b981; border-color: #059669; }
.ss-pill-up:has(input:checked) span { color: #fff; }
.ss-pill-flat:has(input:checked)  { background: #3b82f6; border-color: #2563eb; }
.ss-pill-flat:has(input:checked) span { color: #fff; }
.ss-pill-down:has(input:checked)  { background: #ef4444; border-color: #dc2626; }
.ss-pill-down:has(input:checked) span { color: #fff; }

/* Severity pill colors */
.ss-sev-1:hover { background: #d1fae5; border-color: #6ee7b7; }
.ss-sev-2:hover { background: #fef3c7; border-color: #fcd34d; }
.ss-sev-3:hover { background: #fed7aa; border-color: #fb923c; }
.ss-sev-4:hover { background: #fee2e2; border-color: #f87171; }
.ss-sev-1:has(input:checked) { background: #10b981; border-color: #059669; }
.ss-sev-1:has(input:checked) span { color: #fff; }
.ss-sev-2:has(input:checked) { background: #f59e0b; border-color: #d97706; }
.ss-sev-2:has(input:checked) span { color: #fff; }
.ss-sev-3:has(input:checked) { background: #f97316; border-color: #ea580c; }
.ss-sev-3:has(input:checked) span { color: #fff; }
.ss-sev-4:has(input:checked) { background: #dc2626; border-color: #991b1b; }
.ss-sev-4:has(input:checked) span { color: #fff; }

/* ============================================================
   PLAN — Rujukan pill colors & input tujuan
   ============================================================ */
.ss-ruj-no:hover { background: #f1f5f9; border-color: #cbd5e1; }
.ss-ruj-sp:hover { background: #ede9fe; border-color: #c4b5fd; }
.ss-ruj-rs:hover { background: #dbeafe; border-color: #93c5fd; }
.ss-ruj-no:has(input:checked) { background: #94a3b8; border-color: #64748b; }
.ss-ruj-no:has(input:checked) span { color: #fff; }
.ss-ruj-sp:has(input:checked) { background: #7c3aed; border-color: #6d28d9; }
.ss-ruj-sp:has(input:checked) span { color: #fff; }
.ss-ruj-rs:has(input:checked) { background: #2563eb; border-color: #1d4ed8; }
.ss-ruj-rs:has(input:checked) span { color: #fff; }

.ss-rujuk-wrap {
    position: relative;
    margin-top: 8px;
}
.ss-rujuk-tujuan {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    background: #fff;
    transition: all 0.15s;
}
.ss-rujuk-tujuan:focus {
    outline: none;
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124,58,237,0.1);
}
.ss-rujuk-results {
    position: absolute;
    top: 100%; left: 0; right: 0;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    max-height: 240px;
    overflow-y: auto;
    z-index: 100;
    margin-top: 4px;
    display: none;
}
.ss-rujuk-results.show { display: block; }
.ss-rujuk-result-item {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    transition: background 0.1s;
}
.ss-rujuk-result-item:last-child { border-bottom: none; }
.ss-rujuk-result-item:hover { background: #faf5ff; color: #6d28d9; }
.ss-rujuk-result-item .ss-rujuk-icon {
    color: #7c3aed; font-size: 14px; margin-right: 6px; vertical-align: middle;
}
.ss-rujuk-result-empty {
    padding: 10px 12px;
    text-align: center;
    color: #94a3b8;
    font-size: 12px;
    font-style: italic;
}

/* ============================================================
   EVALUATION — Outcome & Disposisi pill colors
   ============================================================ */
.ss-out-yes:hover     { background: #d1fae5; border-color: #6ee7b7; }
.ss-out-partial:hover { background: #fef3c7; border-color: #fcd34d; }
.ss-out-no:hover      { background: #fee2e2; border-color: #fca5a5; }
.ss-out-yes:has(input:checked)     { background: #10b981; border-color: #059669; }
.ss-out-yes:has(input:checked) span { color: #fff; }
.ss-out-partial:has(input:checked) { background: #f59e0b; border-color: #d97706; }
.ss-out-partial:has(input:checked) span { color: #fff; }
.ss-out-no:has(input:checked)      { background: #ef4444; border-color: #dc2626; }
.ss-out-no:has(input:checked) span { color: #fff; }

.ss-disp-pulang:hover { background: #d1fae5; border-color: #6ee7b7; }
.ss-disp-obs:hover    { background: #dbeafe; border-color: #93c5fd; }
.ss-disp-ranap:hover  { background: #fef3c7; border-color: #fcd34d; }
.ss-disp-rujuk:hover  { background: #ede9fe; border-color: #c4b5fd; }
.ss-disp-pulang:has(input:checked) { background: #10b981; border-color: #059669; }
.ss-disp-pulang:has(input:checked) span { color: #fff; }
.ss-disp-obs:has(input:checked)    { background: #3b82f6; border-color: #2563eb; }
.ss-disp-obs:has(input:checked) span { color: #fff; }
.ss-disp-ranap:has(input:checked)  { background: #f59e0b; border-color: #d97706; }
.ss-disp-ranap:has(input:checked) span { color: #fff; }
.ss-disp-rujuk:has(input:checked)  { background: #7c3aed; border-color: #6d28d9; }
.ss-disp-rujuk:has(input:checked) span { color: #fff; }

/* Pain Scale */
.ss-pain {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 4px;
}
.ss-pain-slider {
    flex: 1;
    height: 6px;
    -webkit-appearance: none;
    appearance: none;
    background: linear-gradient(to right, #10b981 0%, #f59e0b 50%, #ef4444 100%);
    border-radius: 4px;
    outline: none;
    cursor: pointer;
}
.ss-pain-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #3b82f6;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.ss-pain-slider::-moz-range-thumb {
    width: 18px; height: 18px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #3b82f6;
    cursor: pointer;
}
.ss-pain-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #1e293b;
    min-width: 120px;
    justify-content: flex-end;
}
.ss-pain-value { font-weight: 700; font-size: 16px; }
.ss-pain-max   { color: #94a3b8; }
.ss-pain-badge {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}
.ss-pain-badge[data-level="0"] { background: #f1f5f9; color: #64748b; }
.ss-pain-badge[data-level="ringan"]  { background: #d1fae5; color: #047857; }
.ss-pain-badge[data-level="sedang"]  { background: #fef3c7; color: #b45309; }
.ss-pain-badge[data-level="berat"]   { background: #fee2e2; color: #b91c1c; }

/* Catatan textarea */
.ss-catatan {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    resize: vertical;
    min-height: 60px;
    transition: border-color 0.15s;
    background: #fff;
}
.ss-catatan:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
</style>


<!-- 1. DATA PASIEN -->
<div class="row clearfix">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header bg-cyan">
                <h2>DATA PASIEN</h2>
            </div>
            <div class="body" style="padding: 15px;">
                <!-- Baris 1: Data Identitas -->
                <div class="row">
                    <div class="col-md-4">
                        <table class="table table-condensed" style="margin-bottom: 0;">
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>No. Rawat</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['no_rawat']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>No. RM</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['no_rkm_medis']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Nama</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['nm_pasien']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>JK / Lahir</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['jk']?> / <?=$datapasien['tmp_lahir']?>, <?=konversiTanggal($datapasien['tgl_lahir'])?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <table class="table table-condensed" style="margin-bottom: 0;">
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>Kamar</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['nm_bangsal']?> - <?=$datapasien['kd_kamar']?> (Kelas <?=$datapasien['kelas']?>)
                                    <?php if($is_rawat_gabung): ?>
                                    <br><span style="background: #ff6b9d; color: white; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600;">RAWAT GABUNG</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Masuk</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=konversiTanggal($datapasien['tgl_masuk'])?> <?=$datapasien['jam_masuk']?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Keluar</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=($datapasien['tgl_keluar'] && $datapasien['tgl_keluar'] != '0000-00-00') ? konversiTanggal($datapasien['tgl_keluar']).' '.$datapasien['jam_keluar'] : '<span class="label label-success">Masih Dirawat</span>'?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Alamat</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['alamat']?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-4">
                        <table class="table table-condensed" style="margin-bottom: 0;">
                            <tr>
                                <td width="100" style="border: none; padding: 4px 8px;"><strong>Dx Awal</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['diagnosa_awal'] ?: '-'?></td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 4px 8px;"><strong>Dx Akhir</strong></td>
                                <td style="border: none; padding: 4px 8px;">: <?=$datapasien['diagnosa_akhir'] ?: '-'?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <!-- Tombol Refresh + Menu RME -->
                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-12" style="display: flex; align-items: center; gap: 10px;">
                        <button type="button" class="btn btn-info btn-sm waves-effect" onclick="window.location.reload()"
                                style="border-radius: 5px; padding: 5px 15px;">
                            <i class="material-icons" style="vertical-align: middle; font-size: 16px;">refresh</i>
                            Refresh
                        </button>
                        <button type="button" class="btn waves-effect btn-action" onclick="window.history.back();" style="background: linear-gradient(135deg, #78909c 0%, #546e7a 100%); color: white;">
                            <i class="material-icons">arrow_back</i>
                            Kembali
                        </button>                        
                        <!-- TOMBOL MENU RME INAP -->
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- =============================================
     RME TAB BAR (Pill / Capsule Tabs) - RANAP
     Langsung tampil tanpa "Mulai Periksa"
     ============================================= -->
<div class="row clearfix" id="panelRmeTabBar">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="rme-tab-bar" id="rmeTabBar">

            <!-- TOMBOL MENU RME - posisi kiri -->
            <div class="dropdown-rme-wrapper" id="btnMenuRME">
                <button type="button" class="btn-rme-toggle" id="btnRmeToggle" title="Menu RME">
                    <i class="material-icons">dashboard</i>
                </button>
                <ul class="dropdown-rme-menu" id="rmeDropdownMenu">
                    <?php 
                    $encrypted_norawat = urlencode(encrypt_decrypt($norawat, 'e'));
                    $encrypted_norm = urlencode(encrypt_decrypt($norm, 'e'));
                    ?>

                    <?php if(cekAksesMenu('penilaian_awal_medis_ranap') || cekAksesMenu('penilaian_medis_hemodialisa') || cekAksesMenu('penilaian_awal_medis_ranap_neonatus') || cekAksesMenu('penilaian_awal_medis_ranap_kebidanan') || cekAksesMenu('penilaian_bayi_baru_lahir')): ?>
                    <li class="has-submenu">
                        <a href="#" class="submenu-trigger">Penilaian Awal Medis</a>
                        <ul class="dropdown-submenu">
                            <?php if(cekAksesMenu('penilaian_awal_medis_ranap')): ?>
                            <li><a href="index.php?act=Awalmedisranap&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Awal Medis Ranap</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_awal_medis_ranap_neonatus')): ?>
                            <li><a href="index.php?act=Awalmedisneonatus&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Awal Medis Neonatus</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_awal_medis_ranap_kebidanan')): ?>
                            <li><a href="index.php?act=Awalmediskebidanan&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Awal Medis Kebidanan & Kandungan</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_bayi_baru_lahir')): ?>
                            <li><a href="index.php?act=Penilaianbayibarulahir&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pengkajian Bayi Baru Lahir</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_medis_hemodialisa')): ?>
                            <li><a href="index.php?act=Awalmedishemodialisa&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Awal Medis Hemodialisa</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('penilaian_awal_medis_ranap_jantung')): ?>
                            <li><a href="index.php?act=Awalmedisjantunginap&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Awal Medis Jantung</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('hasil_pemeriksaan_usg') || cekAksesMenu('hasil_usg_gynecologi') || cekAksesMenu('hasil_usg_urologi') || cekAksesMenu('hasil_usg_neonatus')): ?>
                    <li class="has-submenu">
                        <a href="#" class="submenu-trigger">Pemeriksaan USG</a>
                        <ul class="dropdown-submenu">
                            <?php if(cekAksesMenu('hasil_pemeriksaan_usg')): ?>
                            <li><a href="index.php?act=Pemeriksaanusgkandungan&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">USG Kandungan</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_usg_gynecologi')): ?>
                            <li><a href="index.php?act=Pemeriksaanusggynecologi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">USG Gynecologi</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_usg_urologi')): ?>
                            <li><a href="index.php?act=Pemeriksaanusgurologi&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">USG Urologi</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_usg_neonatus')): ?>
                            <li><a href="index.php?act=Pemeriksaanusgneonatus&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">USG Neonatus</a></li>
                            <?php endif; ?>                            
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('uji_fungsi_kfr')): ?>
                    <li><a href="index.php?act=Ujifungsikfr&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Uji Fungsi/Prosedur KFR</a></li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('hasil_pemeriksaan_ekg') || cekAksesMenu('hasil_pemeriksaan_echo') || cekAksesMenu('hasil_pemeriksaan_echo_pediatrik') || cekAksesMenu('hasil_pemeriksaan_slit_lamp') || cekAksesMenu('hasil_pemeriksaan_oct') || cekAksesMenu('hasil_pemeriksaan_treadmill')): ?>
                    <li class="has-submenu">
                        <a href="#" class="submenu-trigger">Hasil Pemeriksaan</a>
                        <ul class="dropdown-submenu">
                            <?php if(cekAksesMenu('hasil_pemeriksaan_ekg')): ?>
                            <li><a href="index.php?act=Pemeriksaanekg&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pemeriksaan EKG</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_pemeriksaan_echo')): ?>
                            <li><a href="index.php?act=Pemeriksaanecho&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pemeriksaan Echo</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_pemeriksaan_echo_pediatrik')): ?>
                            <li><a href="index.php?act=Pemeriksaanechopediatrik&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pemeriksaan Echo Pediatrik</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_pemeriksaan_slit_lamp')): ?>
                            <li><a href="index.php?act=Pemeriksaanslitlamp&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pemeriksaan Slit Lamp</a></li>
                            <?php endif; ?>    
                            <?php if(cekAksesMenu('hasil_pemeriksaan_oct')): ?>
                            <li><a href="index.php?act=Pemeriksaanoct&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pemeriksaan OCT</a></li>
                            <?php endif; ?>      
                            <?php if(cekAksesMenu('hasil_pemeriksaan_treadmill')): ?>
                            <li><a href="index.php?act=Pemeriksaantreadmill&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Pemeriksaan Treadmill</a></li>
                            <?php endif; ?>                 
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('hasil_endoskopi_faring_laring') || cekAksesMenu('hasil_endoskopi_hidung') || cekAksesMenu('hasil_endoskopi_telinga')): ?>
                    <li class="has-submenu">
                        <a href="#" class="submenu-trigger">Pemeriksaan Endoskopi</a>
                        <ul class="dropdown-submenu">
                            <?php if(cekAksesMenu('hasil_endoskopi_faring_laring')): ?>
                            <li><a href="index.php?act=Pemeriksaanendoskopifaringlaring&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Endoskopi Faring Laring</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_endoskopi_hidung')): ?>
                            <li><a href="index.php?act=Pemeriksaanendoskopihidung&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Endoskopi Hidung</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('hasil_endoskopi_telinga')): ?>
                            <li><a href="index.php?act=Pemeriksaanendoskopitelinga&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Endoskopi Telinga</a></li>
                            <?php endif; ?>                            
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('checklist_kriteria_keluar_hcu') || cekAksesMenu('checklist_kriteria_keluar_icu') || cekAksesMenu('kriteria_keluar_nicu') || cekAksesMenu('kriteria_keluar_picu')): ?>
                    <li class="has-submenu">
                        <a href="#" class="submenu-trigger">Perawatan Intensif</a>
                        <ul class="dropdown-submenu">
                            <?php if(cekAksesMenu('checklist_kriteria_masuk_hcu')): ?>
                            <li><a href="index.php?act=Checklistkriteriamasukhcu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Masuk HCU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('checklist_kriteria_keluar_hcu')): ?>
                            <li><a href="index.php?act=Checklistkriteriakeluarhcu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Keluar HCU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('checklist_kriteria_masuk_icu')): ?>
                            <li><a href="index.php?act=Checklistkriteriamasukicu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Masuk ICU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('checklist_kriteria_keluar_icu')): ?>
                            <li><a href="index.php?act=Checklistkriteriakeluaricu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Keluar ICU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('kriteria_masuk_nicu')): ?>
                            <li><a href="index.php?act=Kriteriamasuknicu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Masuk NICU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('kriteria_keluar_nicu')): ?>
                            <li><a href="index.php?act=Kriteriakeluarnicu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Keluar NICU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('kriteria_masuk_picu')): ?>
                            <li><a href="index.php?act=Kriteriamasukpicu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Masuk PICU</a></li>
                            <?php endif; ?>
                            <?php if(cekAksesMenu('kriteria_keluar_picu')): ?>
                            <li><a href="index.php?act=Kriteriakeluarpicu&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Kriteria Keluar PICU</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>

                    <?php if(cekAksesMenu('konsultasi_medik')): ?>
                    <li><a href="index.php?act=Konsultasimedik&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Konsultasi Medik</a></li>
                    <?php endif; ?>

                    <li><a href="index.php?act=ClinicalPathway&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Integrated Care Pathway</a></li>
                    <li><a href="index.php?act=Obatpulang&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Resep Pulang</a></li>
                    <li class="divider"></li>
                    <li><a href="index.php?act=ResumeMedisInap&rnw=<?=$encrypted_norawat?>&rm=<?=$encrypted_norm?>">Resume Medis</a></li>
                </ul>
            </div>

            <!-- Tab Area (flex-wrap ke bawah jika penuh) -->
            <div class="rme-tab-scroll-area" id="rmeTabScrollArea">
                <!-- Tab Pemeriksaan -->
                <div class="rme-tab active" data-tab-id="pemeriksaan" data-closable="false">
                    <span class="rme-tab-title">Pemeriksaan</span>
                </div>
                <!-- Tab dinamis akan ditambahkan di sini oleh JS -->
            </div>

        </div>
    </div>
</div>

<!-- =============================================
     CONTAINER KONTEN TAB RME - RANAP
     ============================================= -->
<div id="rmeTabContentWrapper">

<!-- Tab Content: Pemeriksaan & SOAPIE (default, inline) -->
<div class="rme-tab-content active" id="rmeContent_pemeriksaan">
<div class="row clearfix">
    <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
        <div class="card">
            <div class="header bg-pink collapsible-header active" onclick="toggleCollapse(this)">
                <h2>PEMERIKSAAN & SOAPIE</h2>
                <i class="material-icons toggle-icon">expand_less</i>
            </div>
            <div class="body">
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active">
                        <a href="#tab_pemeriksaan" data-toggle="tab">PEMERIKSAAN</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_eresep" data-toggle="tab">E-RESEP</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_diagnosa" data-toggle="tab">DIAGNOSA</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_laboratorium" data-toggle="tab">LABORATORIUM</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_radiologi" data-toggle="tab">RADIOLOGI</a>
                    </li>
                    <li role="presentation">
                        <a href="#tab_tindakan" data-toggle="tab">TINDAKAN</a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane fade in active" id="tab_pemeriksaan">
                        <form id="formPemeriksaan" method="post" novalidate>
                            <input type="hidden" name="norawat" value="<?=$norawat?>">
                            <input type="hidden" name="norm" value="<?=$norm?>">
                            
                            <!-- PEMERIKSAAN FISIK - VERSION 2.0 CLEAN + ICONS -->
                            <!-- PEMERIKSAAN FISIK - COMPACT VERSION -->
                            <div class="form-section">
                                <div class="section-title" style="margin-top: 12px;">
                                    <i class="material-icons" style="vertical-align: middle; margin-right: 5px;">favorite_border</i>
                                    Pemeriksaan Fisik
                                </div>                                
                                <!-- Tanda-Tanda Vital -->
                                <div class="ttv-grid">
                                    <div class="ttv-item">
                                        <label>TD (mmHg)</label>
                                        <input type="text" name="tensi" placeholder="mmHg" pattern="[0-9]{2,3}/[0-9]{2,3}">
                                    </div>
                                    <div class="ttv-item">
                                        <label>Nadi (x/menit)</label>
                                        <input type="number" name="nadi" placeholder="x/menit" min="40" max="200">
                                    </div>
                                    <div class="ttv-item">
                                        <label>RR (x/menit)</label>
                                        <input type="number" name="respiratory_rate" placeholder="x/menit" min="10" max="60">
                                    </div>
                                    <div class="ttv-item">
                                        <label>Suhu (°C)</label>
                                        <input type="number" name="suhu" placeholder="°C" step="0.1" min="35" max="42">
                                    </div>
                                    <div class="ttv-item">
                                        <label>SpO2 (%)</label>
                                        <input type="number" name="spo2" placeholder="%" min="50" max="100">
                                    </div>
                                    <div class="ttv-item">
                                        <label>BB (kg)</label>
                                        <input type="number" name="berat" placeholder="kg" min="2" max="300">
                                    </div>
                                    <div class="ttv-item">
                                        <label>TB (cm)</label>
                                        <input type="number" name="tinggi" placeholder="cm" min="50" max="250">
                                    </div>
                                </div>
                                <!-- Data Tambahan -->
                                <div class="ttv-grid secondary" style="margin-top: 15px;">
                                    <div class="ttv-item">
                                        <label>Kesadaran</label>
                                        <select name="kesadaran" class="form-control-modern">
                                            <option value="Compos Mentis">Compos Mentis</option>
                                            <option value="Apatis">Apatis</option>
                                            <option value="Somnolen">Somnolen</option>
                                            <option value="Sopor">Sopor</option>
                                            <option value="Koma">Koma</option>
                                        </select>
                                    </div>
                                    <div class="ttv-item">
                                        <label>GCS (E,V,M)</label>
                                        <input type="text" name="gcs" placeholder="" pattern="[0-9]{1},?[0-9]{1},?[0-9]{1}">
                                    </div>
                                    <div class="ttv-item">
                                        <label>Alergi</label>
                                        <input type="text" name="alergi" placeholder="Tidak ada">
                                    </div>
                                </div>
                                <button type="button" 
                                        id="btnLoadTTV" 
                                        class="btn-load-ttv"
                                        onclick="loadLastTTV()">
                                    <i class="material-icons">sync</i>
                                    Ambil TTV Terakhir
                                </button>
                            </div>

                            <!-- SOAPIE FORM - MODERN CARD LAYOUT -->
                            <div class="form-section">
                                <div class="form-section-title">SOAPIE</div>

                                <?php $useTemplate = (defined('FITUR_TEMPLATE_RANAP') && FITUR_TEMPLATE_RANAP === true); ?>
                                <!-- Grid container untuk 6 section SOAPIE -->
                                <div class="soapie-container">

                                    <!-- S - Subjective -->
                                    <div class="soapie-card subjective">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">record_voice_over</i>
                                            <span>S - Subjective</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <?php if ($useTemplate): ?>
                                                <?php renderSubjectiveStructured($get_tpl('subjective')); ?>
                                            <?php else: ?>
                                                <textarea name="subjective" class="soapie-textarea" rows="6" placeholder="Keluhan, anamnesis, riwayat penyakit, durasi, dll..." oninput="this.closest('.soapie-card-body').querySelector('.char-current').textContent = this.value.length"></textarea>
                                            <?php endif; ?>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>

                                    <!-- O - Objective -->
                                    <div class="soapie-card objective">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">assignment</i>
                                            <span>O - Objective</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <?php if ($useTemplate): ?>
                                                <?php renderObjectiveStructured($get_tpl('objective'), $kd_sps, $tpl_default, $tpl_sps); ?>
                                            <?php else: ?>
                                                <textarea name="objective" class="soapie-textarea" rows="6" placeholder="Hasil pemeriksaan fisik, vital signs detail, hasil lab/penunjang..." oninput="this.closest('.soapie-card-body').querySelector('.char-current').textContent = this.value.length"></textarea>
                                            <?php endif; ?>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>

                                    <!-- A - Assessment -->
                                    <div class="soapie-card assessment">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">local_hospital</i>
                                            <span>A - Assessment</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <?php if ($useTemplate): ?>
                                                <?php renderAssessmentStructured($get_tpl('assessment')); ?>
                                            <?php else: ?>
                                                <textarea name="assessment" class="soapie-textarea" rows="6" placeholder="Diagnosa kerja, diagnosis banding, ICD-10..." oninput="this.closest('.soapie-card-body').querySelector('.char-current').textContent = this.value.length"></textarea>
                                            <?php endif; ?>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>

                                    <!-- P - Plan -->
                                    <div class="soapie-card plan">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">event_note</i>
                                            <span>P - Plan</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <?php if ($useTemplate): ?>
                                                <?php renderPlanStructured($get_tpl('plan')); ?>
                                            <?php else: ?>
                                                <textarea name="plan" class="soapie-textarea" rows="6" placeholder="Rencana terapi, pemeriksaan lanjutan, edukasi, rujukan..." oninput="this.closest('.soapie-card-body').querySelector('.char-current').textContent = this.value.length"></textarea>
                                            <?php endif; ?>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>

                                    <!-- I - Instruksi / Implementasi -->
                                    <div class="soapie-card intervention">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">healing</i>
                                            <span>I - Instruksi / Implementasi</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <?php if ($useTemplate): ?>
                                                <?php renderInterventionStructured($get_tpl('intervention')); ?>
                                            <?php else: ?>
                                                <textarea name="intervention" class="soapie-textarea" rows="6" placeholder="Tindakan yang dilakukan, prosedur, ICD-9-CM..." oninput="this.closest('.soapie-card-body').querySelector('.char-current').textContent = this.value.length"></textarea>
                                            <?php endif; ?>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>

                                    <!-- E - Evaluation -->
                                    <div class="soapie-card evaluation">
                                        <div class="soapie-card-header">
                                            <i class="material-icons">fact_check</i>
                                            <span>E - Evaluation</span>
                                        </div>
                                        <div class="soapie-card-body">
                                            <?php if ($useTemplate): ?>
                                                <?php renderEvaluationStructured($get_tpl('evaluation')); ?>
                                            <?php else: ?>
                                                <textarea name="evaluation" class="soapie-textarea" rows="6" placeholder="Respon pasien terhadap tindakan, follow-up, kondisi setelah intervensi..." oninput="this.closest('.soapie-card-body').querySelector('.char-current').textContent = this.value.length"></textarea>
                                            <?php endif; ?>
                                            <div class="soapie-char-count">
                                                <span class="char-current">0</span> karakter
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" 
                                            id="btnSimpanSOAPIE" 
                                            class="btn-save-soapie"
                                            style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(76, 175, 80, 0.4); background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); border: none; color: white;">
                                        <i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">save</i>
                                        Simpan Pemeriksaan
                                    </button>
                                    
                                    <button type="button" 
                                            id="btnHapusSOAPIE"
                                            class="btn-delete-soapie"
                                            style="height: 45px; padding: 0 30px; border-radius: 8px; font-weight: 500; font-size: 14px; box-shadow: 0 2px 8px rgba(244, 67, 54, 0.4); background: linear-gradient(135deg, #f44336 0%, #c62828 100%); border: none; color: white; margin-left: 10px; display: none;">
                                        <i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">delete</i>
                                        Hapus Pemeriksaan
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" name="no_rawat" value="<?=$datapasien['no_rawat']?>">
                            <input type="hidden" name="tgl_perawatan" id="tgl_perawatan" value="">
                            <input type="hidden" name="jam_rawat" id="jam_rawat" value="">
                        </form>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_eresep">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form E-Resep dalam tahap pengembangan.
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_diagnosa">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form Diagnosa dalam tahap pengembangan.
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_tindakan">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form Tindakan dalam tahap pengembangan.
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_laboratorium">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form Laboratorium dalam tahap pengembangan.
                        </div>
                    </div>

                    <div role="tabpanel" class="tab-pane fade" id="tab_radiologi">
                        <div class="alert alert-info">
                            <strong>Info:</strong> Form Radiologi dalam tahap pengembangan.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- end #rmeContent_pemeriksaan -->

<!-- Container untuk konten tab AJAX (RME forms) -->
<div id="rmeTabAjaxContainer">
    <!-- Tab AJAX akan ditambahkan di sini oleh RME Tab Manager -->
</div>

</div><!-- end #rmeTabContentWrapper -->

<!-- 3. RIWAYAT SOAPIE -->
<div class="row clearfix">
  <div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
    <div class="card">
      <div class="header bg-orange">
        <h2>RIWAYAT PASIEN</h2>
        <small class="text-muted" id="last-update">Terakhir diupdate: -</small>
      </div>

      <div class="body">
        <!-- Sub Tabs -->
        <ul class="nav nav-tabs tab-nav-right" role="tablist" id="riwayatSubTabs">
          <li role="presentation" class="active"><a href="#tab_riwayat_pemeriksaan" data-toggle="tab">PEMERIKSAAN</a></li>
          <li role="presentation"><a href="#tab_riwayat_soapie" data-toggle="tab">SOAPIE</a></li>
          <li role="presentation"><a href="#tab_riwayat_obat" data-toggle="tab">OBAT & BHP</a></li>
          <li role="presentation"><a href="#tab_riwayat_lab" data-toggle="tab">LABORATORIUM</a></li>
          <li role="presentation"><a href="#tab_riwayat_rad" data-toggle="tab">RADIOLOGI</a></li>
          <li role="presentation"><a href="#tab_riwayat_operasi" data-toggle="tab">OPERASI</a></li>
          <li role="presentation"><a href="#tab_riwayat_kunjungan" data-toggle="tab">KUNJUNGAN</a></li>
          <li role="presentation"><a href="#tab_riwayat_semua" data-toggle="tab">SELURUH RIWAYAT</a></li>
        </ul>

        <div class="tab-content" style="padding-top:15px;">
          <!-- PEMERIKSAAN -->
          <div role="tabpanel" class="tab-pane fade in active" id="tab_riwayat_pemeriksaan">
            <div id="riwayat_pemeriksaan_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data pemeriksaan...</p>
              </div>
            </div>
          </div>

          <!-- SOAPIE dengan Sub-Tabs -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_soapie">
            
            <!-- Sub-Tabs untuk Rawat Jalan, Rawat Inap, dan Grafik -->
            <ul class="nav nav-tabs nav-tabs-secondary" role="tablist" id="soapieSubTabs">
              <li role="presentation">
                <a href="#tab_soapie_ralan" data-toggle="tab">RAWAT JALAN</a>
              </li>
              <li role="presentation" class="active">
                <a href="#tab_soapie_ranap" data-toggle="tab">RAWAT INAP</a>
              </li>
              <li role="presentation">
                <a href="#tab_soapie_grafik" data-toggle="tab">
                  <i class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 4px;">show_chart</i>
                  GRAFIK PEMERIKSAAN
                </a>
              </li>
            </ul>

            <div class="tab-content">
              <!-- RAWAT JALAN -->
              <div role="tabpanel" class="tab-pane fade" id="tab_soapie_ralan">
                <div id="riwayat_soapie_ralan_content">
                  <div class="text-center" style="padding: 20px;">
                    <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                    <p>Memuat data SOAPIE Rawat Jalan...</p>
                  </div>
                </div>
              </div>

              <!-- RAWAT INAP -->
              <div role="tabpanel" class="tab-pane fade in active" id="tab_soapie_ranap">
                <div id="riwayat_soapie_ranap_content">
                  <div class="text-center" style="padding: 20px;">
                    <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                    <p>Memuat data SOAPIE Rawat Inap...</p>
                  </div>
                </div>
              </div>

              <!-- GRAFIK TTV -->
              <div role="tabpanel" class="tab-pane fade" id="tab_soapie_grafik">
                <div id="grafik_ttv_content">
                  <div class="text-center" style="padding: 20px;">
                    <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                    <p>Memuat Grafik TTV...</p>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <!-- OBAT & BHP -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_obat">
            <div id="riwayat_obat_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data obat & BHP...</p>
              </div>
            </div>
          </div>

          <!-- LABORATORIUM -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_lab">
            <div id="riwayat_lab_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data laboratorium...</p>
              </div>
            </div>
          </div>

          <!-- RADIOLOGI -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_rad">
            <div id="riwayat_rad_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data radiologi...</p>
              </div>
            </div>
          </div>

          <!-- OPERASI -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_operasi">
            <div id="riwayat_operasi_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data operasi...</p>
              </div>
            </div>
          </div>

          <!-- KUNJUNGAN -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_kunjungan">
            <div id="riwayat_kunjungan_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat data kunjungan...</p>
              </div>
            </div>
          </div>

          <!-- SELURUH RIWAYAT -->
          <div role="tabpanel" class="tab-pane fade" id="tab_riwayat_semua">
            <div id="riwayat_semua_content" class="table-responsive no-margin">
              <div class="text-center" style="padding: 20px;">
                <i class="material-icons spin" style="font-size: 48px; color: #999;">autorenew</i>
                <p>Memuat seluruh riwayat pasien...</p>
              </div>
            </div>
          </div>
        </div> <!-- end .tab-content -->

      </div> <!-- end .body -->
    </div> <!-- end .card -->
  </div>
</div>


<!-- Load script terpisah dengan timestamp untuk bypass cache -->
<!-- <script src="js/pemeriksaan.js"></script> --> <!-- Disabled dulu -->
 <script src="js/tentangaplikasi.js?v=<?=time()?>"></script>
<script src="js/pemeriksaan_main_inap.js?v=<?=time()?>"></script>
<script src="js/soapie_enhancement_inap.js?v=<?=time()?>"></script>
<script src="js/pemeriksaansoapie_inap.js?v=<?=time()?>"></script>
<!-- ===== SOAPIE Structured Form (inline JS, status=Ranap) ===== -->
<script>
// ============================================================
// SOAPIE Subjective — Structured Form (global functions)
// Pakai inline onchange/oninput supaya tidak bergantung event delegation
//
// FLOW: structured input (centang/slider) → state JS
//       state + Catatan Tambahan → compile → hidden textarea[name=subjective]
// ============================================================

// State global untuk track structured input
var ssState = {
    keluhan: {},  // { template_key: line_text }  — order preserved by Object.keys
    pain: ''      // last "Skala Nyeri: X/10 (...)" line
};

function ssGetTextarea() {
    return document.querySelector('textarea[name="subjective"]');
}
function ssGetCatatanExtra() {
    return document.getElementById('ssCatatanExtra');
}

function ssPainLabel(v) {
    v = parseInt(v, 10) || 0;
    if (v <= 0)  return { text: 'Tidak Nyeri', level: '0' };
    if (v <= 3)  return { text: 'Ringan',      level: 'ringan' };
    if (v <= 6)  return { text: 'Sedang',      level: 'sedang' };
    return                { text: 'Berat',      level: 'berat' };
}

// Build text untuk 1 checkbox (handle pola "..." → input value)
function ssBuildText(cb) {
    var label = cb.closest('label.ss-check');
    var template = cb.getAttribute('data-text') || '';
    if (template.indexOf('...') === -1) return template;

    var splitParts = template.split('...');
    var inputs = label.querySelectorAll('.ss-keluhan-input');
    var rebuilt = splitParts[0];
    inputs.forEach(function(inp, i) {
        var v = (inp.value || '').trim();
        rebuilt += (v !== '' ? v : '...') + (splitParts[i + 1] || '');
    });
    return rebuilt;
}

function ssUpdateCharCount() {
    var ta = ssGetTextarea();
    if (!ta) return;
    var card = ta.closest('.soapie-card-body');
    if (card) {
        var ch = card.querySelector('.char-current');
        if (ch) ch.textContent = (ta.value || '').length;
    }
}

// COMPILE: ambil semua state + catatan tambahan → set hidden subjective textarea
function ssCompile() {
    var lines = [];
    // Keluhan dari centangan (urutan sesuai object key insertion)
    Object.keys(ssState.keluhan).forEach(function(k) {
        if (ssState.keluhan[k]) lines.push(ssState.keluhan[k]);
    });
    // Skala nyeri
    if (ssState.pain) lines.push(ssState.pain);

    var structured = lines.join('\n');

    var catatanEl = ssGetCatatanExtra();
    var catatan = catatanEl ? (catatanEl.value || '').trim() : '';

    var finalText = '';
    if (structured && catatan)      finalText = structured + '\n\n' + catatan;
    else if (structured)            finalText = structured;
    else if (catatan)               finalText = catatan;

    var ta = ssGetTextarea();
    if (ta) ta.value = finalText;
    ssUpdateCharCount();
}

// HANDLER: onchange checkbox
function ssOnCheck(cb) {
    var key = cb.getAttribute('data-text') || '';
    if (cb.checked) {
        ssState.keluhan[key] = ssBuildText(cb);
    } else {
        delete ssState.keluhan[key];
    }
    ssCompile();
}

// HANDLER: oninput pada input "..."
function ssOnInputDots(input) {
    var label = input.closest('label.ss-check');
    var cb = label.querySelector('.ss-keluhan-cb');
    if (!cb) return;
    var key = cb.getAttribute('data-text') || '';

    // Auto-check kalau user mulai ketik
    if ((input.value || '').trim() !== '' && !cb.checked) {
        cb.checked = true;
    }
    if (cb.checked) {
        ssState.keluhan[key] = ssBuildText(cb);
    } else {
        delete ssState.keluhan[key];
    }
    ssCompile();
}

// HANDLER: slider Skala Nyeri
function ssOnPainChange(slider) {
    var v = parseInt(slider.value, 10) || 0;
    var pl = ssPainLabel(v);
    // Update visual badge & angka
    var form = slider.closest('.ss-form');
    if (form) {
        var valEl = form.querySelector('.ss-pain-value');
        var badge = form.querySelector('.ss-pain-badge');
        if (valEl) valEl.textContent = v;
        if (badge) { badge.setAttribute('data-level', pl.level); badge.textContent = pl.text; }
    }
    // Update state
    ssState.pain = v > 0 ? ('Skala Nyeri: ' + v + '/10 (' + pl.text + ')') : '';
    ssCompile();
}

// HANDLER: catatan tambahan
function ssOnCatatanInput() {
    ssCompile();
}

// Mode EDIT helper
window.SOAPIESubjective = {
    loadFromText: function(text) {
        // Mode textarea polos (FITUR_TEMPLATE_RANAP=false): chip tidak dirender → set langsung
        if (!document.querySelector('.ss-form[data-section="subjective"]')) {
            var ta = document.querySelector('textarea[name="subjective"]');
            if (ta) { ta.value = text || ''; ta.dispatchEvent(new Event('input', {bubbles:true})); }
            return;
        }
        ssState = { keluhan: {}, pain: '' };
        document.querySelectorAll('.ss-keluhan-cb').forEach(function(cb) { cb.checked = false; });
        document.querySelectorAll('.ss-keluhan-input').forEach(function(inp) { inp.value = ''; });
        var slider = document.querySelector('.ss-pain-slider');
        if (slider) { slider.value = 0; ssOnPainChange(slider); }
        // Data lama dari DB → masuk ke Catatan Tambahan (visible)
        var ce = ssGetCatatanExtra();
        if (ce) ce.value = text || '';
        ssCompile();
    }
};

// ============================================================
// SOAPIE Objective — Structured Form (radio organ + checkbox findings + catatan)
// ============================================================

var ssStateObj = {
    organ: {},      // { 'Kepala': 'Normal', 'Mata': 'Abnormal', ... }
    findings: {}    // { 'KU baik, compos mentis': true, ... }
};

function ssObjGetTextarea() {
    return document.querySelector('textarea[name="objective"]');
}
function ssObjGetCatatanExtra() {
    return document.getElementById('ssObjCatatanExtra');
}

function ssObjUpdateCharCount() {
    var ta = ssObjGetTextarea();
    if (!ta) return;
    var card = ta.closest('.soapie-card-body');
    if (card) {
        var ch = card.querySelector('.char-current');
        if (ch) ch.textContent = (ta.value || '').length;
    }
}

// COMPILE Objective: organ status + findings + catatan
function ssObjCompile() {
    var lines = [];

    // Status per organ
    Object.keys(ssStateObj.organ).forEach(function(org) {
        if (ssStateObj.organ[org]) {
            lines.push(org + ': ' + ssStateObj.organ[org]);
        }
    });

    // Pemisah antara organ & findings
    var organCount = lines.length;

    // Quick findings — pakai value (rebuilt text dengan input "...")
    Object.keys(ssStateObj.findings).forEach(function(f) {
        var t = ssStateObj.findings[f];
        if (t) lines.push(t);
    });

    // Tambah blank line antara organ block dan findings (kalau dua-duanya ada)
    var structured = '';
    if (organCount > 0 && lines.length > organCount) {
        var organPart   = lines.slice(0, organCount).join('\n');
        var findingPart = lines.slice(organCount).join('\n');
        structured = organPart + '\n\n' + findingPart;
    } else {
        structured = lines.join('\n');
    }

    // Catatan tambahan
    var catatanEl = ssObjGetCatatanExtra();
    var catatan = catatanEl ? (catatanEl.value || '').trim() : '';

    var finalText = '';
    if (structured && catatan)      finalText = structured + '\n\n' + catatan;
    else if (structured)            finalText = structured;
    else if (catatan)               finalText = catatan;

    var ta = ssObjGetTextarea();
    if (ta) ta.value = finalText;
    ssObjUpdateCharCount();
}

// HANDLER: radio organ change
function ssOnObjOrgan(radio) {
    var organ = radio.getAttribute('data-organ') || '';
    if (!organ) return;
    ssStateObj.organ[organ] = radio.value;
    ssObjCompile();
}

// Helper: rebuild text dari data-text + input value (untuk pola "...")
function ssBuildObjText(cb) {
    var label = cb.closest('label.ss-check');
    var template = cb.getAttribute('data-text') || '';
    if (template.indexOf('...') === -1) return template;
    var splitParts = template.split('...');
    var inputs = label.querySelectorAll('.ss-obj-input');
    var rebuilt = splitParts[0];
    inputs.forEach(function(inp, i) {
        var v = (inp.value || '').trim();
        rebuilt += (v !== '' ? v : '...') + (splitParts[i + 1] || '');
    });
    return rebuilt;
}

// HANDLER: checkbox finding change
function ssOnObjFinding(cb) {
    var key = cb.getAttribute('data-text') || '';
    if (cb.checked) ssStateObj.findings[key] = ssBuildObjText(cb);
    else delete ssStateObj.findings[key];
    ssObjCompile();
}

// HANDLER: input "..." di Objective findings
function ssOnObjInputDots(input) {
    var label = input.closest('label.ss-check');
    var cb = label.querySelector('.ss-obj-cb');
    if (!cb) return;
    var key = cb.getAttribute('data-text') || '';

    // Auto-check kalau user mulai ketik
    if ((input.value || '').trim() !== '' && !cb.checked) {
        cb.checked = true;
    }
    if (cb.checked) ssStateObj.findings[key] = ssBuildObjText(cb);
    else delete ssStateObj.findings[key];
    ssObjCompile();
}

// HANDLER: catatan tambahan
function ssOnObjCatatanInput() {
    ssObjCompile();
}

// Mode EDIT helper
window.SOAPIEObjective = {
    loadFromText: function(text) {
        if (!document.querySelector('.ss-form[data-section="objective"]')) {
            var ta = document.querySelector('textarea[name="objective"]');
            if (ta) { ta.value = text || ''; ta.dispatchEvent(new Event('input', {bubbles:true})); }
            return;
        }
        ssStateObj = { organ: {}, findings: {} };
        document.querySelectorAll('.ss-form[data-section="objective"] .ss-organ-radios input[type="radio"]').forEach(function(r) { r.checked = false; });
        document.querySelectorAll('.ss-form[data-section="objective"] .ss-obj-cb').forEach(function(cb) { cb.checked = false; });
        var ce = ssObjGetCatatanExtra();
        if (ce) ce.value = text || '';
        ssObjCompile();
    }
};

// ============================================================
// SOAPIE Assessment — Diagnosis ICD-10 + DD + Evaluasi + Severity
// ============================================================

var ssStateAsm = {
    diagnoses: [],   // [{kd_penyakit, nm_penyakit}, ...]
    dd: {},          // { 'Sindrom Metabolik': true, ... }
    kondisi: '',     // 'Membaik' | 'Stabil' | 'Memburuk' | ''
    severity: ''     // 'Ringan' | 'Sedang' | 'Berat' | 'Kritis' | ''
};

function ssAsmGetTextarea() { return document.querySelector('textarea[name="assessment"]'); }
function ssAsmGetCatatan() { return document.getElementById('ssAsmCatatan'); }

function ssAsmUpdateCharCount() {
    var ta = ssAsmGetTextarea();
    if (!ta) return;
    var card = ta.closest('.soapie-card-body');
    if (card) {
        var ch = card.querySelector('.char-current');
        if (ch) ch.textContent = (ta.value || '').length;
    }
}

// Render diagnosa chips list
function ssAsmRenderDxList() {
    var container = document.getElementById('ssDxList');
    if (!container) return;
    if (ssStateAsm.diagnoses.length === 0) { container.innerHTML = ''; return; }
    var html = '';
    ssStateAsm.diagnoses.forEach(function(dx, i) {
        html += '<div class="ss-dx-chip">';
        html += '<span class="ss-dx-chip-num">' + (i+1) + '</span>';
        html += '<span class="ss-dx-chip-name">' + ssAsmEsc(dx.nm_penyakit) + '</span>';
        html += '<span class="ss-dx-chip-code">' + ssAsmEsc(dx.kd_penyakit) + '</span>';
        html += '<button type="button" class="ss-dx-chip-remove" onclick="ssAsmRemoveDx(' + i + ')" title="Hapus">';
        html += '<i class="material-icons">close</i></button>';
        html += '</div>';
    });
    container.innerHTML = html;
}

// Status pasien: ralan/ranap → tentukan field 'status' di diagnosa_pasien
// Halaman pemeriksaan.php = rawat jalan → 'Ralan'
function ssAsmGetPasienStatus() { return 'Ranap'; }

function ssAsmGetNorawat() {
    var el = document.querySelector('input[name="norawat"]');
    return el ? (el.value || '') : '';
}

// Sync ke DB: insert ke diagnosa_pasien
function ssAsmDbInsert(kd, prioritas) {
    var norawat = ssAsmGetNorawat();
    if (!norawat || !kd) return;
    var fd = new FormData();
    fd.append('aksi', 'simpan_diagnosa');
    fd.append('norawat', norawat);
    fd.append('kd_penyakit', kd);
    fd.append('status', ssAsmGetPasienStatus());
    fd.append('prioritas', prioritas);
    fetch('pages/proses.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            if (j.status !== 'success') {
                console.warn('[Asm] Gagal simpan diagnosa ke DB:', j.message);
            }
        })
        .catch(function(e) { console.warn('[Asm] Error simpan diagnosa:', e); });
}

// Sync ke DB: hapus dari diagnosa_pasien
function ssAsmDbDelete(kd, prioritas) {
    var norawat = ssAsmGetNorawat();
    if (!norawat || !kd) return;
    var fd = new FormData();
    fd.append('aksi', 'hapus_diagnosa');
    fd.append('norawat', norawat);
    fd.append('kd_penyakit', kd);
    fd.append('prioritas', prioritas || 1);
    fetch('pages/proses.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .catch(function(e) { console.warn('[Asm] Error hapus diagnosa:', e); });
}

function ssAsmAddDx(kd, nm, opts) {
    opts = opts || {};
    // Skip duplikat
    var exist = ssStateAsm.diagnoses.some(function(d) { return d.kd_penyakit === kd; });
    if (exist) return;

    // Tentukan prioritas: kalau dari load DB → pakai existing; kalau user pick → max+1
    var prioritas;
    if (opts.prioritas !== undefined) {
        prioritas = opts.prioritas;
    } else {
        var maxP = 0;
        ssStateAsm.diagnoses.forEach(function(d) { if ((d.prioritas || 0) > maxP) maxP = d.prioritas; });
        prioritas = maxP + 1;
    }

    ssStateAsm.diagnoses.push({
        kd_penyakit: kd,
        nm_penyakit: nm,
        prioritas:   prioritas
    });
    ssAsmRenderDxList();
    ssAsmCompile();

    // Sync ke DB hanya jika BUKAN dari load (load = read-only)
    if (!opts.fromLoad) {
        ssAsmDbInsert(kd, prioritas);
    }
}

function ssAsmRemoveDx(idx) {
    var removed = ssStateAsm.diagnoses[idx];
    ssStateAsm.diagnoses.splice(idx, 1);
    ssAsmRenderDxList();
    ssAsmCompile();
    if (removed && removed.kd_penyakit) {
        ssAsmDbDelete(removed.kd_penyakit, removed.prioritas);
    }
}

// Load existing diagnosa_pasien untuk no_rawat saat ini → populate chip
function ssAsmLoadExistingDiagnoses() {
    var norawat = ssAsmGetNorawat();
    if (!norawat) return;
    var url = 'pages/get_diagnosa_pasien.php?norawat=' + encodeURIComponent(norawat)
            + '&status=' + encodeURIComponent(ssAsmGetPasienStatus());
    fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            if (j.status !== 'success' || !Array.isArray(j.data) || j.data.length === 0) return;
            j.data.forEach(function(d) {
                ssAsmAddDx(d.kd_penyakit, d.nm_penyakit, { prioritas: d.prioritas, fromLoad: true });
            });
        })
        .catch(function(e) { console.warn('[Asm] Error load diagnosa:', e); });
}

// Autocomplete: search ICD-10 dengan debounce
var ssAsmDxSearchTimer = null;
function ssAsmInitSearch() {
    var input = document.getElementById('ssDxSearch');
    var results = document.getElementById('ssDxResults');
    if (!input || !results) return;

    input.addEventListener('input', function() {
        var kw = (this.value || '').trim();
        if (kw.length < 2) {
            results.innerHTML = '';
            results.classList.remove('show');
            return;
        }
        clearTimeout(ssAsmDxSearchTimer);
        ssAsmDxSearchTimer = setTimeout(function() { ssAsmDoSearch(kw); }, 300);
    });

    // Delegated click handler di results container — robust untuk dynamic content
    results.addEventListener('click', function(e) {
        var item = e.target.closest('.ss-dx-result-item');
        if (!item) return;
        var kd = item.getAttribute('data-kd') || '';
        var nm = item.getAttribute('data-nm') || '';
        if (!kd) return;
        ssAsmPickDx(kd, nm);
    });

    // Klik di luar → hide dropdown
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.ss-dx-search-wrap')) {
            results.classList.remove('show');
        }
    });

    // Tutup dropdown saat Escape
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') results.classList.remove('show');
    });
}

function ssAsmEsc(s) {
    return String(s || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function ssAsmDoSearch(kw) {
    var results = document.getElementById('ssDxResults');
    if (!results) return;
    results.innerHTML = '<div class="ss-dx-result-empty">Mencari...</div>';
    results.classList.add('show');

    var url = 'pages/cari_penyakit.php?keyword=' + encodeURIComponent(kw);
    fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.status !== 'success' || !json.data || json.data.length === 0) {
                results.innerHTML = '<div class="ss-dx-result-empty">Tidak ditemukan</div>';
                return;
            }
            var html = '';
            json.data.forEach(function(d) {
                var kd = d.kd_penyakit || '';
                var nm = d.nm_penyakit || '';
                // Pakai data-attribute (escape HTML) → handler delegated, tidak rusak oleh quote
                html += '<div class="ss-dx-result-item" data-kd="' + ssAsmEsc(kd) + '" data-nm="' + ssAsmEsc(nm) + '">';
                html += '<span class="ss-dx-result-code">' + ssAsmEsc(kd) + '</span>';
                html += '<span>' + ssAsmEsc(nm) + '</span>';
                html += '</div>';
            });
            results.innerHTML = html;
        })
        .catch(function() {
            results.innerHTML = '<div class="ss-dx-result-empty">Error mencari diagnosa</div>';
        });
}

function ssAsmPickDx(kd, nm) {
    ssAsmAddDx(kd, nm);
    var input = document.getElementById('ssDxSearch');
    var results = document.getElementById('ssDxResults');
    if (input) input.value = '';
    if (results) { results.classList.remove('show'); results.innerHTML = ''; }
    if (input) input.focus();
}

// COMPILE Assessment → set hidden textarea[name=assessment]
// Note: Diagnosa Kerja (ICD-10) TIDAK masuk ke kolom penilaian — sudah tersimpan terstruktur di tabel diagnosa_pasien
function ssAsmCompile() {
    var lines = [];

    // Diagnosis Banding — inline dengan abbreviation "DD:" (pakai value untuk support "...")
    var ddList = [];
    Object.keys(ssStateAsm.dd).forEach(function(k) {
        var v = ssStateAsm.dd[k];
        if (v) ddList.push(typeof v === 'string' ? v : k);
    });
    if (ddList.length > 0) {
        lines.push('DD: ' + ddList.join(', '));
    }

    // Evaluasi & Severity
    if (ssStateAsm.kondisi)  lines.push('Evaluasi Kondisi: ' + ssStateAsm.kondisi);
    if (ssStateAsm.severity) lines.push('Severity: ' + ssStateAsm.severity);

    var structured = lines.join('\n');

    // Catatan
    var catatanEl = ssAsmGetCatatan();
    var catatan = catatanEl ? (catatanEl.value || '').trim() : '';

    var finalText = '';
    if (structured && catatan) finalText = structured + '\n\n' + catatan;
    else if (structured) finalText = structured;
    else if (catatan) finalText = catatan;

    var ta = ssAsmGetTextarea();
    if (ta) ta.value = finalText;
    ssAsmUpdateCharCount();
}

// Handlers
// Helper: rebuild text untuk DD checkbox dengan input "..."
function ssBuildAsmDdText(cb) {
    var label = cb.closest('label.ss-check');
    var template = cb.getAttribute('data-text') || '';
    if (template.indexOf('...') === -1) return template;
    var splitParts = template.split('...');
    var inputs = label.querySelectorAll('.ss-asm-input');
    var rebuilt = splitParts[0];
    inputs.forEach(function(inp, i) {
        var v = (inp.value || '').trim();
        rebuilt += (v !== '' ? v : '...') + (splitParts[i + 1] || '');
    });
    return rebuilt;
}

function ssOnAsmDD(cb) {
    var key = cb.getAttribute('data-text') || '';
    if (cb.checked) ssStateAsm.dd[key] = ssBuildAsmDdText(cb);
    else delete ssStateAsm.dd[key];
    ssAsmCompile();
}

// HANDLER: input "..." di Assessment DD
function ssOnAsmInputDots(input) {
    var label = input.closest('label.ss-check');
    var cb = label.querySelector('.ss-asm-dd-cb');
    if (!cb) return;
    var key = cb.getAttribute('data-text') || '';
    if ((input.value || '').trim() !== '' && !cb.checked) {
        cb.checked = true;
    }
    if (cb.checked) ssStateAsm.dd[key] = ssBuildAsmDdText(cb);
    else delete ssStateAsm.dd[key];
    ssAsmCompile();
}

function ssOnAsmKondisi(radio) {
    ssStateAsm.kondisi = radio.value;
    ssAsmCompile();
}

function ssOnAsmSeverity(radio) {
    ssStateAsm.severity = radio.value;
    ssAsmCompile();
}

function ssOnAsmCatatanInput() {
    ssAsmCompile();
}

// Mode EDIT helper
window.SOAPIEAssessment = {
    loadFromText: function(text) {
        if (!document.querySelector('.ss-form[data-section="assessment"]')) {
            var ta = document.querySelector('textarea[name="assessment"]');
            if (ta) { ta.value = text || ''; ta.dispatchEvent(new Event('input', {bubbles:true})); }
            return;
        }
        ssStateAsm = { diagnoses: [], dd: {}, kondisi: '', severity: '' };
        document.querySelectorAll('.ss-form[data-section="assessment"] .ss-asm-dd-cb').forEach(function(cb) { cb.checked = false; });
        document.querySelectorAll('.ss-form[data-section="assessment"] input[name="ssAsmKondisi"]').forEach(function(r) { r.checked = false; });
        document.querySelectorAll('.ss-form[data-section="assessment"] input[name="ssAsmSeverity"]').forEach(function(r) { r.checked = false; });
        ssAsmRenderDxList();
        var ce = ssAsmGetCatatan();
        if (ce) ce.value = text || '';
        ssAsmCompile();
    }
};

// ============================================================
// SOAPIE Plan — Quick checkbox + Kontrol pill + Rujukan + Catatan
// ============================================================

var ssStatePlan = {
    plans:    {},   // { 'Edukasi pasien': true, ... }
    kontrol:  '',   // 'Tidak perlu' | '3 hari' | ...
    rujuk:    'Tidak',  // 'Tidak' | 'Dokter Spesialis' | 'RS Lain'
    rujukTujuan: ''
};

function ssPlanGetTextarea() { return document.querySelector('textarea[name="plan"]'); }
function ssPlanGetCatatan()  { return document.getElementById('ssPlanCatatan'); }
function ssPlanGetRujukInput() { return document.getElementById('ssPlanRujukTujuan'); }

function ssPlanUpdateCharCount() {
    var ta = ssPlanGetTextarea();
    if (!ta) return;
    var card = ta.closest('.soapie-card-body');
    if (card) {
        var ch = card.querySelector('.char-current');
        if (ch) ch.textContent = (ta.value || '').length;
    }
}

// COMPILE Plan → set hidden textarea[name=plan]
function ssPlanCompile() {
    var lines = [];

    // Plan checkboxes — pakai value (rebuilt text untuk "...")
    Object.keys(ssStatePlan.plans).forEach(function(p) {
        var t = ssStatePlan.plans[p];
        if (t) lines.push(typeof t === 'string' ? t : p);
    });

    // Kontrol & Rujukan
    var meta = [];
    if (ssStatePlan.kontrol) meta.push('Kontrol: ' + ssStatePlan.kontrol);
    if (ssStatePlan.rujuk && ssStatePlan.rujuk !== 'Tidak') {
        var rujukLine = 'Rujukan: ' + ssStatePlan.rujuk;
        if (ssStatePlan.rujukTujuan) rujukLine += ' (' + ssStatePlan.rujukTujuan + ')';
        meta.push(rujukLine);
    }

    var structured;
    if (lines.length > 0 && meta.length > 0) {
        structured = lines.join('\n') + '\n\n' + meta.join('\n');
    } else if (lines.length > 0) {
        structured = lines.join('\n');
    } else {
        structured = meta.join('\n');
    }

    // Catatan
    var catatanEl = ssPlanGetCatatan();
    var catatan = catatanEl ? (catatanEl.value || '').trim() : '';

    var finalText = '';
    if (structured && catatan) finalText = structured + '\n\n' + catatan;
    else if (structured)       finalText = structured;
    else if (catatan)          finalText = catatan;

    var ta = ssPlanGetTextarea();
    if (ta) ta.value = finalText;
    ssPlanUpdateCharCount();
}

// Helper rebuild text untuk Plan checkbox dengan input "..."
function ssBuildPlanText(cb) {
    var label = cb.closest('label.ss-check');
    var template = cb.getAttribute('data-text') || '';
    if (template.indexOf('...') === -1) return template;
    var splitParts = template.split('...');
    var inputs = label.querySelectorAll('.ss-plan-input');
    var rebuilt = splitParts[0];
    inputs.forEach(function(inp, i) {
        var v = (inp.value || '').trim();
        rebuilt += (v !== '' ? v : '...') + (splitParts[i + 1] || '');
    });
    return rebuilt;
}

// Handlers
function ssOnPlanCheck(cb) {
    var key = cb.getAttribute('data-text') || '';
    if (cb.checked) ssStatePlan.plans[key] = ssBuildPlanText(cb);
    else delete ssStatePlan.plans[key];
    ssPlanCompile();
}

function ssOnPlanInputDots(input) {
    var label = input.closest('label.ss-check');
    var cb = label.querySelector('.ss-plan-cb');
    if (!cb) return;
    var key = cb.getAttribute('data-text') || '';
    if ((input.value || '').trim() !== '' && !cb.checked) cb.checked = true;
    if (cb.checked) ssStatePlan.plans[key] = ssBuildPlanText(cb);
    else delete ssStatePlan.plans[key];
    ssPlanCompile();
}

function ssOnPlanKontrol(radio) {
    ssStatePlan.kontrol = (radio.value === 'Tidak perlu') ? '' : radio.value;
    // Mau tetap tampilkan "Tidak perlu"? Kalau iya, hapus filter di atas.
    if (radio.value === 'Tidak perlu') ssStatePlan.kontrol = 'Tidak perlu';
    ssPlanCompile();
}

function ssOnPlanRujuk(radio) {
    ssStatePlan.rujuk = radio.value;
    var wrap = document.getElementById('ssPlanRujukWrap');
    var inp = ssPlanGetRujukInput();
    var results = document.getElementById('ssPlanRujukResults');

    if (radio.value === 'Tidak') {
        ssStatePlan.rujukTujuan = '';
        if (wrap) wrap.style.display = 'none';
        if (inp) inp.value = '';
        if (results) { results.classList.remove('show'); results.innerHTML = ''; }
    } else {
        if (wrap) wrap.style.display = 'block';
        if (inp) {
            inp.placeholder = (radio.value === 'Dokter Spesialis')
                ? 'Ketik nama dokter (autocomplete) — boleh manual untuk dokter luar RS...'
                : 'Nama RS / fasilitas rujukan (ketik manual)...';
            inp.focus();
        }
    }
    ssPlanCompile();
}

// Search dokter dengan debounce
var ssPlanRujukTimer = null;
function ssOnPlanRujukTujuan(input) {
    var val = (input.value || '').trim();
    ssStatePlan.rujukTujuan = val;
    ssPlanCompile();

    // Autocomplete hanya aktif untuk "Dokter Spesialis" — RS Lain manual saja
    if (ssStatePlan.rujuk !== 'Dokter Spesialis') return;

    var results = document.getElementById('ssPlanRujukResults');
    if (!results) return;

    if (val.length < 2) {
        results.classList.remove('show');
        results.innerHTML = '';
        return;
    }

    clearTimeout(ssPlanRujukTimer);
    ssPlanRujukTimer = setTimeout(function() { ssPlanRujukDoSearch(val); }, 300);
}

function ssPlanRujukDoSearch(kw) {
    var results = document.getElementById('ssPlanRujukResults');
    if (!results) return;
    results.innerHTML = '<div class="ss-rujuk-result-empty">Mencari...</div>';
    results.classList.add('show');

    var url = 'pages/cari_dokter.php?keyword=' + encodeURIComponent(kw);
    fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.status !== 'success' || !json.data || json.data.length === 0) {
                results.innerHTML = '<div class="ss-rujuk-result-empty">Tidak ditemukan di RS — boleh ketik manual untuk dokter luar</div>';
                return;
            }
            var html = '';
            json.data.forEach(function(d) {
                var nm = ssAsmEsc(d.nm_dokter);
                html += '<div class="ss-rujuk-result-item" data-nm="' + nm + '">';
                html += '<i class="material-icons ss-rujuk-icon">person</i>' + nm;
                html += '</div>';
            });
            results.innerHTML = html;
        })
        .catch(function() {
            results.innerHTML = '<div class="ss-rujuk-result-empty">Error mencari dokter</div>';
        });
}

// Init click handler untuk pick result
function ssPlanRujukInitPicker() {
    var results = document.getElementById('ssPlanRujukResults');
    if (!results) return;

    results.addEventListener('click', function(e) {
        var item = e.target.closest('.ss-rujuk-result-item');
        if (!item) return;
        var nm = item.getAttribute('data-nm') || '';
        if (!nm) return;
        var inp = ssPlanGetRujukInput();
        if (inp) inp.value = nm;
        ssStatePlan.rujukTujuan = nm;
        results.classList.remove('show');
        results.innerHTML = '';
        ssPlanCompile();
    });

    // Klik di luar wrap → hide dropdown
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.ss-rujuk-wrap')) {
            results.classList.remove('show');
        }
    });
}

function ssOnPlanCatatanInput() { ssPlanCompile(); }

// ============================================================
// SOAPIE Intervention — ICD-9 Procedure picker + Quick Action + Catatan
// Auto-sync ICD-9 ke tabel prosedur_pasien (untuk klaim BPJS)
// ============================================================

var ssStateInt = {
    procedures: [],   // [{kode, deskripsi, prioritas}, ...]
    actions: {}       // { 'Pemberian obat oral': true, ... }
};

function ssIntGetTextarea() { return document.querySelector('textarea[name="intervention"]'); }
function ssIntGetCatatan()  { return document.getElementById('ssIntCatatan'); }

function ssIntUpdateCharCount() {
    var ta = ssIntGetTextarea();
    if (!ta) return;
    var card = ta.closest('.soapie-card-body');
    if (card) {
        var ch = card.querySelector('.char-current');
        if (ch) ch.textContent = (ta.value || '').length;
    }
}

// Render chip list ICD-9
function ssIntRenderList() {
    var container = document.getElementById('ssIcd9List');
    if (!container) return;
    if (ssStateInt.procedures.length === 0) { container.innerHTML = ''; return; }
    var html = '';
    ssStateInt.procedures.forEach(function(p, i) {
        html += '<div class="ss-dx-chip">';
        html += '<span class="ss-dx-chip-num">' + (i+1) + '</span>';
        html += '<span class="ss-dx-chip-name">' + ssAsmEsc(p.deskripsi) + '</span>';
        html += '<span class="ss-dx-chip-code">' + ssAsmEsc(p.kode) + '</span>';
        html += '<button type="button" class="ss-dx-chip-remove" onclick="ssIntRemoveProc(' + i + ')" title="Hapus">';
        html += '<i class="material-icons">close</i></button>';
        html += '</div>';
    });
    container.innerHTML = html;
}

// Status pasien (Ralan untuk pemeriksaan.php)
function ssIntGetPasienStatus() { return 'Ranap'; }
function ssIntGetNorawat() {
    var el = document.querySelector('input[name="norawat"]');
    return el ? (el.value || '') : '';
}

// Sync ke DB: insert ke prosedur_pasien
function ssIntDbInsert(kode, prioritas) {
    var norawat = ssIntGetNorawat();
    if (!norawat || !kode) return;
    var fd = new FormData();
    fd.append('aksi', 'simpan_prosedur');
    fd.append('norawat', norawat);
    fd.append('kode', kode);
    fd.append('status', ssIntGetPasienStatus());
    fd.append('prioritas', prioritas);
    fd.append('jumlah', '1');
    fetch('pages/proses.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            if (j.status !== 'success') {
                console.warn('[Int] Gagal simpan prosedur ke DB:', j.message);
            }
        })
        .catch(function(e) { console.warn('[Int] Error simpan prosedur:', e); });
}

// Sync ke DB: hapus dari prosedur_pasien
function ssIntDbDelete(kode, prioritas) {
    var norawat = ssIntGetNorawat();
    if (!norawat || !kode) return;
    var fd = new FormData();
    fd.append('aksi', 'hapus_prosedur');
    fd.append('norawat', norawat);
    fd.append('kode', kode);
    fd.append('prioritas', prioritas || 1);
    fetch('pages/proses.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .catch(function(e) { console.warn('[Int] Error hapus prosedur:', e); });
}

function ssIntAddProc(kode, deskripsi, opts) {
    opts = opts || {};
    var exist = ssStateInt.procedures.some(function(p) { return p.kode === kode; });
    if (exist) return;

    var prioritas;
    if (opts.prioritas !== undefined) {
        prioritas = opts.prioritas;
    } else {
        var maxP = 0;
        ssStateInt.procedures.forEach(function(p) { if ((p.prioritas || 0) > maxP) maxP = p.prioritas; });
        prioritas = maxP + 1;
    }

    ssStateInt.procedures.push({ kode: kode, deskripsi: deskripsi, prioritas: prioritas });
    ssIntRenderList();
    ssIntCompile();

    if (!opts.fromLoad) ssIntDbInsert(kode, prioritas);
}

function ssIntRemoveProc(idx) {
    var removed = ssStateInt.procedures[idx];
    ssStateInt.procedures.splice(idx, 1);
    ssIntRenderList();
    ssIntCompile();
    if (removed && removed.kode) {
        ssIntDbDelete(removed.kode, removed.prioritas);
    }
}

// Autocomplete search ICD-9
var ssIntSearchTimer = null;
function ssIntInitSearch() {
    var input = document.getElementById('ssIcd9Search');
    var results = document.getElementById('ssIcd9Results');
    if (!input || !results) return;

    input.addEventListener('input', function() {
        var kw = (this.value || '').trim();
        if (kw.length < 2) {
            results.innerHTML = '';
            results.classList.remove('show');
            return;
        }
        clearTimeout(ssIntSearchTimer);
        ssIntSearchTimer = setTimeout(function() { ssIntDoSearch(kw); }, 300);
    });

    results.addEventListener('click', function(e) {
        var item = e.target.closest('.ss-dx-result-item');
        if (!item) return;
        var kode = item.getAttribute('data-kd') || '';
        var desk = item.getAttribute('data-nm') || '';
        if (!kode) return;
        ssIntPickProc(kode, desk);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.ss-dx-search-wrap')) results.classList.remove('show');
    });

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') results.classList.remove('show');
    });
}

function ssIntDoSearch(kw) {
    var results = document.getElementById('ssIcd9Results');
    if (!results) return;
    results.innerHTML = '<div class="ss-dx-result-empty">Mencari...</div>';
    results.classList.add('show');

    var url = 'pages/cari_icd9.php?keyword=' + encodeURIComponent(kw);
    fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(json) {
            if (json.status !== 'success' || !json.data || json.data.length === 0) {
                results.innerHTML = '<div class="ss-dx-result-empty">Tidak ditemukan</div>';
                return;
            }
            var html = '';
            json.data.forEach(function(d) {
                html += '<div class="ss-dx-result-item" data-kd="' + ssAsmEsc(d.kode) + '" data-nm="' + ssAsmEsc(d.deskripsi) + '">';
                html += '<span class="ss-dx-result-code">' + ssAsmEsc(d.kode) + '</span>';
                html += '<span>' + ssAsmEsc(d.deskripsi) + '</span>';
                html += '</div>';
            });
            results.innerHTML = html;
        })
        .catch(function() {
            results.innerHTML = '<div class="ss-dx-result-empty">Error mencari prosedur</div>';
        });
}

function ssIntPickProc(kode, desk) {
    ssIntAddProc(kode, desk);
    var input = document.getElementById('ssIcd9Search');
    var results = document.getElementById('ssIcd9Results');
    if (input) input.value = '';
    if (results) { results.classList.remove('show'); results.innerHTML = ''; }
    if (input) input.focus();
}

// COMPILE → set hidden textarea[name=intervention]
// Note: Prosedur ICD-9 TIDAK masuk ke kolom instruksi — sudah tersimpan terstruktur di tabel prosedur_pasien
function ssIntCompile() {
    var lines = [];

    // Quick Action — pakai value (rebuilt text untuk "...")
    var actionList = [];
    Object.keys(ssStateInt.actions).forEach(function(k) {
        var v = ssStateInt.actions[k];
        if (v) actionList.push(typeof v === 'string' ? v : k);
    });
    if (actionList.length > 0) {
        lines.push('Tindakan:');
        actionList.forEach(function(a) { lines.push('- ' + a); });
    }

    var structured = lines.join('\n');

    var catatanEl = ssIntGetCatatan();
    var catatan = catatanEl ? (catatanEl.value || '').trim() : '';

    var finalText = '';
    if (structured && catatan) finalText = structured + '\n\n' + catatan;
    else if (structured)       finalText = structured;
    else if (catatan)          finalText = catatan;

    var ta = ssIntGetTextarea();
    if (ta) ta.value = finalText;
    ssIntUpdateCharCount();
}

// Helper rebuild text untuk Intervention checkbox dengan input "..."
function ssBuildIntText(cb) {
    var label = cb.closest('label.ss-check');
    var template = cb.getAttribute('data-text') || '';
    if (template.indexOf('...') === -1) return template;
    var splitParts = template.split('...');
    var inputs = label.querySelectorAll('.ss-int-input');
    var rebuilt = splitParts[0];
    inputs.forEach(function(inp, i) {
        var v = (inp.value || '').trim();
        rebuilt += (v !== '' ? v : '...') + (splitParts[i + 1] || '');
    });
    return rebuilt;
}

function ssOnIntCheck(cb) {
    var key = cb.getAttribute('data-text') || '';
    if (cb.checked) ssStateInt.actions[key] = ssBuildIntText(cb);
    else delete ssStateInt.actions[key];
    ssIntCompile();
}

function ssOnIntInputDots(input) {
    var label = input.closest('label.ss-check');
    var cb = label.querySelector('.ss-int-cb');
    if (!cb) return;
    var key = cb.getAttribute('data-text') || '';
    if ((input.value || '').trim() !== '' && !cb.checked) cb.checked = true;
    if (cb.checked) ssStateInt.actions[key] = ssBuildIntText(cb);
    else delete ssStateInt.actions[key];
    ssIntCompile();
}

function ssOnIntCatatanInput() { ssIntCompile(); }

// Load existing prosedur_pasien
function ssIntLoadExistingProcedures() {
    var norawat = ssIntGetNorawat();
    if (!norawat) return;
    var url = 'pages/get_prosedur_pasien.php?norawat=' + encodeURIComponent(norawat)
            + '&status=' + encodeURIComponent(ssIntGetPasienStatus());
    fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(j) {
            if (j.status !== 'success' || !Array.isArray(j.data) || j.data.length === 0) return;
            j.data.forEach(function(d) {
                ssIntAddProc(d.kode, d.deskripsi, { prioritas: d.prioritas, fromLoad: true });
            });
        })
        .catch(function(e) { console.warn('[Int] Error load prosedur:', e); });
}

// Mode EDIT helper
window.SOAPIEIntervention = {
    loadFromText: function(text) {
        if (!document.querySelector('.ss-form[data-section="intervention"]')) {
            var ta = document.querySelector('textarea[name="intervention"]');
            if (ta) { ta.value = text || ''; ta.dispatchEvent(new Event('input', {bubbles:true})); }
            return;
        }
        ssStateInt = { procedures: [], actions: {} };
        document.querySelectorAll('.ss-form[data-section="intervention"] .ss-int-cb').forEach(function(cb) { cb.checked = false; });
        ssIntRenderList();
        var ce = ssIntGetCatatan();
        if (ce) ce.value = text || '';
        ssIntCompile();
    }
};

// ============================================================
// SOAPIE Evaluation — Outcome + Kondisi + Disposisi + Findings + Catatan
// ============================================================

var ssStateEval = {
    outcome:   '',   // 'Tercapai' | 'Tercapai Sebagian' | 'Belum Tercapai'
    kondisi:   '',   // 'Membaik' | 'Stabil' | 'Memburuk'
    disposisi: '',   // 'Pulang' | 'Observasi' | 'Rawat Inap' | 'Rujuk'
    findings:  {}    // { 'Keluhan berkurang': true, ... }
};

function ssEvalGetTextarea() { return document.querySelector('textarea[name="evaluation"]'); }
function ssEvalGetCatatan()  { return document.getElementById('ssEvalCatatan'); }

function ssEvalUpdateCharCount() {
    var ta = ssEvalGetTextarea();
    if (!ta) return;
    var card = ta.closest('.soapie-card-body');
    if (card) {
        var ch = card.querySelector('.char-current');
        if (ch) ch.textContent = (ta.value || '').length;
    }
}

// COMPILE → set hidden textarea[name=evaluation]
function ssEvalCompile() {
    var lines = [];

    if (ssStateEval.outcome)   lines.push('Outcome: ' + ssStateEval.outcome);
    if (ssStateEval.kondisi)   lines.push('Kondisi: ' + ssStateEval.kondisi);
    if (ssStateEval.disposisi) lines.push('Disposisi: ' + ssStateEval.disposisi);

    // Findings inline — pakai value (rebuilt text untuk "...")
    var findingsList = [];
    Object.keys(ssStateEval.findings).forEach(function(k) {
        var v = ssStateEval.findings[k];
        if (v) findingsList.push(typeof v === 'string' ? v : k);
    });
    if (findingsList.length > 0) {
        lines.push('Findings: ' + findingsList.join(', '));
    }

    var structured = lines.join('\n');

    var catatanEl = ssEvalGetCatatan();
    var catatan = catatanEl ? (catatanEl.value || '').trim() : '';

    var finalText = '';
    if (structured && catatan) finalText = structured + '\n\n' + catatan;
    else if (structured)       finalText = structured;
    else if (catatan)          finalText = catatan;

    var ta = ssEvalGetTextarea();
    if (ta) ta.value = finalText;
    ssEvalUpdateCharCount();
}

// Handlers
function ssOnEvalOutcome(radio)   { ssStateEval.outcome   = radio.value; ssEvalCompile(); }
function ssOnEvalKondisi(radio)   { ssStateEval.kondisi   = radio.value; ssEvalCompile(); }
function ssOnEvalDisposisi(radio) { ssStateEval.disposisi = radio.value; ssEvalCompile(); }
// Helper rebuild text untuk Evaluation checkbox dengan input "..."
function ssBuildEvalText(cb) {
    var label = cb.closest('label.ss-check');
    var template = cb.getAttribute('data-text') || '';
    if (template.indexOf('...') === -1) return template;
    var splitParts = template.split('...');
    var inputs = label.querySelectorAll('.ss-eval-input');
    var rebuilt = splitParts[0];
    inputs.forEach(function(inp, i) {
        var v = (inp.value || '').trim();
        rebuilt += (v !== '' ? v : '...') + (splitParts[i + 1] || '');
    });
    return rebuilt;
}

function ssOnEvalCheck(cb) {
    var key = cb.getAttribute('data-text') || '';
    if (cb.checked) ssStateEval.findings[key] = ssBuildEvalText(cb);
    else delete ssStateEval.findings[key];
    ssEvalCompile();
}

function ssOnEvalInputDots(input) {
    var label = input.closest('label.ss-check');
    var cb = label.querySelector('.ss-eval-cb');
    if (!cb) return;
    var key = cb.getAttribute('data-text') || '';
    if ((input.value || '').trim() !== '' && !cb.checked) cb.checked = true;
    if (cb.checked) ssStateEval.findings[key] = ssBuildEvalText(cb);
    else delete ssStateEval.findings[key];
    ssEvalCompile();
}
function ssOnEvalCatatanInput() { ssEvalCompile(); }

// Mode EDIT helper
window.SOAPIEEvaluation = {
    loadFromText: function(text) {
        if (!document.querySelector('.ss-form[data-section="evaluation"]')) {
            var ta = document.querySelector('textarea[name="evaluation"]');
            if (ta) { ta.value = text || ''; ta.dispatchEvent(new Event('input', {bubbles:true})); }
            return;
        }
        ssStateEval = { outcome: '', kondisi: '', disposisi: '', findings: {} };
        document.querySelectorAll('.ss-form[data-section="evaluation"] input[type="radio"]').forEach(function(r) { r.checked = false; });
        document.querySelectorAll('.ss-form[data-section="evaluation"] .ss-eval-cb').forEach(function(cb) { cb.checked = false; });
        var ce = ssEvalGetCatatan();
        if (ce) ce.value = text || '';
        ssEvalCompile();
    }
};

// Mode EDIT helper untuk Plan
window.SOAPIEPlan = {
    loadFromText: function(text) {
        if (!document.querySelector('.ss-form[data-section="plan"]')) {
            var ta = document.querySelector('textarea[name="plan"]');
            if (ta) { ta.value = text || ''; ta.dispatchEvent(new Event('input', {bubbles:true})); }
            return;
        }
        ssStatePlan = { plans: {}, kontrol: '', rujuk: 'Tidak', rujukTujuan: '' };
        document.querySelectorAll('.ss-form[data-section="plan"] .ss-plan-cb').forEach(function(cb) { cb.checked = false; });
        document.querySelectorAll('.ss-form[data-section="plan"] input[name="ssPlanKontrol"]').forEach(function(r) { r.checked = false; });
        // Reset rujukan ke Tidak
        var rujukNo = document.querySelector('.ss-form[data-section="plan"] input[name="ssPlanRujuk"][value="Tidak"]');
        if (rujukNo) rujukNo.checked = true;
        var rujukInp = ssPlanGetRujukInput();
        if (rujukInp) { rujukInp.style.display = 'none'; rujukInp.value = ''; }
        var ce = ssPlanGetCatatan();
        if (ce) ce.value = text || '';
        ssPlanCompile();
    }
};

// Init
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function() {
        ssCompile(); ssObjCompile(); ssAsmCompile(); ssPlanCompile(); ssIntCompile(); ssEvalCompile();
        ssAsmInitSearch();
        ssIntInitSearch();
        ssPlanRujukInitPicker();
        ssAsmLoadExistingDiagnoses();
        ssIntLoadExistingProcedures();
    });
} else {
    document.addEventListener('DOMContentLoaded', function() {
        ssCompile(); ssObjCompile(); ssAsmCompile(); ssPlanCompile(); ssIntCompile(); ssEvalCompile();
        ssAsmInitSearch();
        ssIntInitSearch();
        ssPlanRujukInitPicker();
        ssAsmLoadExistingDiagnoses();
        ssIntLoadExistingProcedures();
    });
}
</script>


<!-- FIX DROPDOWN KESADARAN - ULTIMATE SOLUTION -->
<script>
// ===================================================
// PEMERIKSAAN FORM HANDLERS - IMPROVED VERSION
// ===================================================
(function() {
    'use strict';
    
    // ✅ PERBAIKAN 1: Fungsi untuk menunggu jQuery
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            setTimeout(function() {
                waitForjQuery(callback);
            }, 100);
        }
    }
    
    // ✅ PERBAIKAN 2: Inisialisasi form handlers dengan jQuery ready
    function initFormHandlers() {

        
        // Check if jQuery is available
        if (typeof jQuery === 'undefined') {
   
            return;
        }
        
        const $ = jQuery;
        
        // Handle form submit
        $('#formPemeriksaan').off('submit').on('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
         
            
            const btn = $('#btnSimpanSOAPIE');
            const originalHtml = btn.html();
            
            // Disable button
            btn.prop('disabled', true).html('<i class="material-icons spin" style="animation: spin 1s linear infinite;">autorenew</i> Menyimpan...');
            
            // Cek apakah mode edit
            let formData;
            const isEditMode = window.editPemeriksaanData !== null && window.editPemeriksaanData !== undefined;
            
            if (isEditMode) {
                // Mode EDIT - kirim ke endpoint update RANAP
                formData = $(this).serialize() + 
                    '&aksi=update_pemeriksaan_ranap' +
                    '&tgl_perawatan_lama=' + encodeURIComponent(window.editPemeriksaanData.tgl_perawatan) +
                    '&jam_rawat_lama=' + encodeURIComponent(window.editPemeriksaanData.jam_rawat);
            } else {
                // Mode INPUT BARU - ke tabel pemeriksaan_ranap
                formData = $(this).serialize() + '&simpan_pemeriksaan_ranap=1';
            }
            
         
            
            // Send AJAX ke proses3.php untuk rawat inap
            $.ajax({
                url: 'pages/proses3.php',
                type: 'POST',
                data: formData,
                dataType: 'html',
                timeout: 30000,
                success: function(response) {
                    
                    
                    // Cek apakah response adalah JSON (untuk mode edit)
                    let jsonResponse = null;
                    try {
                        jsonResponse = JSON.parse(response);
                    } catch(e) {
                        // Bukan JSON, lanjut proses biasa
                    }
                    
                    if (jsonResponse && jsonResponse.status === 'success') {
                        // Response JSON sukses (mode edit)
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: jsonResponse.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            // Reset mode edit
                            window.editPemeriksaanData = null;
                            $('#editModeBadge').remove();
                            $('#btnSimpanSOAPIE').html('<i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">save</i> Simpan Pemeriksaan');
                            
                            // Reset form
                            document.getElementById('formPemeriksaan').reset();
                            
                            // Reload riwayat
                            if (typeof PemeriksaanModule !== 'undefined' && typeof PemeriksaanModule.reloadPemeriksaan === 'function') {
                                PemeriksaanModule.reloadPemeriksaan();
                            }
                        });
                    } else if (jsonResponse && jsonResponse.status === 'error') {
                        // Response JSON error
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: jsonResponse.message,
                            confirmButtonText: 'OK'
                        });
                    } else if (response.indexOf('Berhasil') > -1) {
                        // Response HTML dengan kata 'Berhasil' (mode input baru)
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: 'Data pemeriksaan rawat inap berhasil disimpan',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(function() {
                            // Reload riwayat SOAPIE RANAP
                            
                            if (typeof SOAPIEModule !== 'undefined') {
                                SOAPIEModule.reloadRanap();
                            }
                            // ✅ PERBAIKAN - Reload dan switch ke tab riwayat pemeriksaan
                           
                            if (typeof PemeriksaanModule !== 'undefined') {
                                // Reload data pemeriksaan
                                if (typeof PemeriksaanModule.reloadPemeriksaan === 'function') {
                                    PemeriksaanModule.reloadPemeriksaan();
                                }
                                // Switch ke tab pemeriksaan
                                if (typeof PemeriksaanModule.switchToTabPemeriksaan === 'function') {
                                    setTimeout(function() {
                                        PemeriksaanModule.switchToTabPemeriksaan();
                                    }, 300);
                                }
                            }
                        });
                    } else if (response.indexOf('Gagal') > -1) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: 'Gagal menyimpan data pemeriksaan',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        // Execute any script in response
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = response;
                        const scripts = tempDiv.getElementsByTagName('script');
                        
                        for (let i = 0; i < scripts.length; i++) {
                            try {
                                eval(scripts[i].innerHTML);
                            } catch (e) {
                               
                            }
                        }
                    }
                },
                error: function(xhr, status, error) {
           
                    
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: ' + error,
                        confirmButtonText: 'OK'
                    });
                },
                complete: function() {
                    // Re-enable button
                    btn.prop('disabled', false).html(originalHtml);
                   
                }
            });
            
            return false;
        });
        
        // Handle delete button
        $('#btnHapusSOAPIE').off('click').on('click', function() {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: 'Yakin ingin menghapus data pemeriksaan ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f44336',
                cancelButtonColor: '#999',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // ✅ PERBAIKAN 3: Ganti endpoint hapus ke proses3.php untuk ranap
                    const formData = $('#formPemeriksaan').serialize() + '&hapus_pemeriksaan_ranap=1';
                    
                    $.ajax({
                        url: 'pages/proses3.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'html',
                        success: function(response) {
                            if (response.indexOf('Berhasil') > -1) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: 'Data berhasil dihapus',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(function() {
                                    // Clear form
                                    document.getElementById('formPemeriksaan').reset();
                                    $('#btnHapusSOAPIE').hide();
                                    
                                    // Reload riwayat RANAP
                                    if (typeof SOAPIEModule !== 'undefined') {
                                        SOAPIEModule.reloadRanap();
                                    }
                                });
                            }
                        }
                    });
                }
            });
        });
        
       
    }
    
    // ✅ PERBAIKAN 4: Gunakan waitForjQuery untuk inisialisasi
    waitForjQuery(function($) {
        
        
        $(document).ready(function() {
            
            setTimeout(initFormHandlers, 300);
        });
    });
    
})(); // End IIFE

// ===================================================
// CSS ANIMATION
// ===================================================
(function() {
    // Add CSS for spin animation
    if (!document.getElementById('spin-animation-style')) {
        const style = document.createElement('style');
        style.id = 'spin-animation-style';
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }
})();

// ===================================================
// GLOBAL FUNCTIONS
// ===================================================

function konfirmasiSelesaiPeriksa() {
    Swal.fire({
        title: 'Konfirmasi Selesai',
        text: 'Apakah Anda yakin ingin menyelesaikan pemeriksaan pasien ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4caf50',
        cancelButtonColor: '#999',
        confirmButtonText: 'Ya, Selesai',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'index.php?act=PasienInap';
        }
    });
}

function loadLastTTV() {
    // ✅ PERBAIKAN 5: Cek apakah jQuery tersedia
    if (typeof jQuery === 'undefined') {
        
        return;
    }
    
    const $ = jQuery;
    const btn = document.getElementById('btnLoadTTV');
    
    // ✅ PERBAIKAN 6: Validasi variabel PHP tersedia
    // Catatan: Variabel PHP ini akan di-replace saat file di-parse di server
    const norawat = '<?php echo isset($datapasien["no_rawat"]) ? $datapasien["no_rawat"] : ""; ?>';
    const norm = '<?php echo isset($datapasien["no_rkm_medis"]) ? $datapasien["no_rkm_medis"] : ""; ?>';
    
    if (!norawat || !norm) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Data pasien tidak ditemukan',
            confirmButtonText: 'OK'
        });
        return;
    }
    

    
    btn.classList.add('loading');
    btn.innerHTML = '<i class="material-icons">sync</i> Memuat...';
    
    $.ajax({
        url: 'pages/get_last_ttv.php',
        type: 'POST',
        data: {
            no_rawat: norawat,
            no_rkm_medis: norm
        },
        dataType: 'json',
        success: function(response) {

            
            if (response.success) {
                const data = response.data;
                
                // Fill form dengan pengecekan
                if (data.tensi) $('input[name="tensi"]').val(data.tensi);
                if (data.nadi) $('input[name="nadi"]').val(data.nadi);
                if (data.respirasi) $('input[name="respiratory_rate"]').val(data.respirasi);
                if (data.suhu) $('input[name="suhu"]').val(data.suhu);
                if (data.spo2) $('input[name="spo2"]').val(data.spo2);
                if (data.berat) $('input[name="berat"]').val(data.berat);
                if (data.tinggi) $('input[name="tinggi"]').val(data.tinggi);
                if (data.kesadaran) $('select[name="kesadaran"]').val(data.kesadaran);
                if (data.gcs) $('input[name="gcs"]').val(data.gcs);
                if (data.alergi) $('input[name="alergi"]').val(data.alergi);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: 'TTV terakhir berhasil dimuat',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Tidak Ada Data',
                    text: response.message || 'Tidak ada data TTV sebelumnya',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function(xhr, status, error) {
            
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: 'Terjadi kesalahan: ' + error,
                confirmButtonText: 'OK'
            });
        },
        complete: function() {
            btn.classList.remove('loading');
            btn.innerHTML = '<i class="material-icons">sync</i> Ambil TTV Terakhir';
        }
    });
}

// ===================================================
// DROPDOWN FIX
// ===================================================
(function() {
    'use strict';
    
    function fixDropdown() {
        // Cari semua select dengan class vital-select-v2
        const vitalSelects = document.querySelectorAll('.vital-select-v2');
        
        if (vitalSelects.length === 0) {
            
            return;
        }
        
        vitalSelects.forEach(function(select) {
            // Hapus Bootstrap classes
            select.classList.remove('selectpicker');
            select.classList.remove('show-tick');
            
            // Destroy Bootstrap-select jika sudah di-init
            if (typeof jQuery !== 'undefined' && jQuery(select).data('selectpicker')) {
                jQuery(select).selectpicker('destroy');
            }
            
            // Hapus wrapper Bootstrap-select jika ada
            const wrapper = select.closest('.bootstrap-select');
            if (wrapper && wrapper.parentNode) {
                wrapper.parentNode.insertBefore(select, wrapper);
                wrapper.remove();
            }
            
            // Force styling native
            select.style.cssText = `
                width: 100% !important;
                border: none !important;
                border-bottom: 2px solid #e8e8e8 !important;
                padding: 8px 20px 8px 0 !important;
                font-size: 15px !important;
                background: transparent !important;
                appearance: none !important;
                -webkit-appearance: none !important;
                -moz-appearance: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E") !important;
                background-repeat: no-repeat !important;
                background-position: right center !important;
            `;
        });
        
        
    }
    
    // Execute immediately
    fixDropdown();
    
    // Execute on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fixDropdown);
    } else {
        // DOM already loaded
        fixDropdown();
    }
    
    // ✅ PERBAIKAN 7: Execute after jQuery loads dengan waitForjQuery
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            setTimeout(function() {
                waitForjQuery(callback);
            }, 100);
        }
    }
    
    waitForjQuery(function($) {
        $(document).ready(function() {
            setTimeout(fixDropdown, 100);
            setTimeout(fixDropdown, 500); // Double check
        });
    });
    
})();

// ===================================================
// EDIT & DELETE PEMERIKSAAN HANDLERS - IMPROVED
// ===================================================
(function() {
    'use strict';
    
    // Variable untuk menyimpan data edit
    window.editPemeriksaanData = null;
    
    // ✅ PERBAIKAN 1: Fungsi untuk menunggu jQuery
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            
            setTimeout(function() {
                waitForjQuery(callback);
            }, 100);
        }
    }
    
    function initEditDeleteHandlers() {
        
        
        const $ = jQuery;
        
        // ===== HANDLER TOMBOL EDIT =====
        $(document).on('click', '.edit_pemeriksaan', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const data = btn.data();
            
            
            
            // Simpan data edit ke variabel global
            window.editPemeriksaanData = {
                no_rawat: data.no_rawat,
                tgl_perawatan: data.tgl_perawatan,
                jam_rawat: data.jam_rawat
            };
            
            // Isi form dengan data yang akan diedit
            // TTV
            $('input[name="tensi"]').val(data.tensi || '');
            $('input[name="nadi"]').val(data.nadi || '');
            $('input[name="respiratory_rate"]').val(data.respirasi || '');
            $('input[name="suhu"]').val(data.suhu_tubuh || '');
            $('input[name="spo2"]').val(data.spo2 || '');
            $('input[name="berat"]').val(data.berat || '');
            $('input[name="tinggi"]').val(data.tinggi || '');
            $('select[name="kesadaran"]').val(data.kesadaran || '');
            $('input[name="gcs"]').val(data.gcs || '');
            $('input[name="alergi"]').val(data.alergi || '');
            $('input[name="lingkar_perut"]').val(data.lingkar_perut || '');
            
            // SOAPIE — pakai loadFromText helper (data lama → catatan tambahan)
            if (window.SOAPIESubjective && typeof window.SOAPIESubjective.loadFromText === 'function') {
                window.SOAPIESubjective.loadFromText(data.keluhan || '');
            } else { $('textarea[name="subjective"]').val(data.keluhan || ''); }

            if (window.SOAPIEObjective && typeof window.SOAPIEObjective.loadFromText === 'function') {
                window.SOAPIEObjective.loadFromText(data.pemeriksaan || '');
            } else { $('textarea[name="objective"]').val(data.pemeriksaan || ''); }

            if (window.SOAPIEAssessment && typeof window.SOAPIEAssessment.loadFromText === 'function') {
                window.SOAPIEAssessment.loadFromText(data.penilaian || '');
            } else { $('textarea[name="assessment"]').val(data.penilaian || ''); }

            if (window.SOAPIEPlan && typeof window.SOAPIEPlan.loadFromText === 'function') {
                window.SOAPIEPlan.loadFromText(data.rtl || '');
            } else { $('textarea[name="plan"]').val(data.rtl || ''); }

            if (window.SOAPIEIntervention && typeof window.SOAPIEIntervention.loadFromText === 'function') {
                window.SOAPIEIntervention.loadFromText(data.instruksi || '');
            } else { $('textarea[name="intervention"]').val(data.instruksi || ''); }

            if (window.SOAPIEEvaluation && typeof window.SOAPIEEvaluation.loadFromText === 'function') {
                window.SOAPIEEvaluation.loadFromText(data.evaluasi || '');
            } else { $('textarea[name="evaluation"]').val(data.evaluasi || ''); }
            
            // Update character count untuk setiap textarea
            $('.soapie-textarea').each(function() {
                const charCount = $(this).val().length;
                $(this).closest('.soapie-card-body').find('.char-current').text(charCount);
            });
            
            // Tampilkan badge EDIT MODE
            showEditModeBadge(data.tgl_perawatan, data.jam_rawat);
            
            // Scroll ke form SOAPIE
            $('html, body').animate({
                scrollTop: $('#formPemeriksaan').offset().top - 100
            }, 500);
            
            // Switch ke tab Pemeriksaan
            $('a[href="#tab_pemeriksaan"]').tab('show');
            
            Swal.fire({
                icon: 'info',
                title: 'Mode Edit Aktif',
                text: 'Data ' + data.tgl_perawatan + ' ' + data.jam_rawat + ' dimuat ke form',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        });
        
        // ===== HANDLER TOMBOL DELETE (RANAP) =====
        $(document).on('click', '.delete_pemeriksaan', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const data = btn.data();
            
           
            
            Swal.fire({
                title: 'Konfirmasi Hapus',
                html: 'Yakin ingin menghapus data pemeriksaan<br><strong>' + data.tgl_perawatan + ' ' + data.jam_rawat + '</strong>?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f44336',
                cancelButtonColor: '#999',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Kirim request hapus ke proses3.php (RANAP)
                    $.ajax({
                        url: 'pages/proses3.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            aksi: 'hapus_pemeriksaan_ranap',
                            no_rawat: data.no_rawat,
                            tgl_perawatan: data.tgl_perawatan,
                            jam_rawat: data.jam_rawat
                        },
                        beforeSend: function() {
                            
                        },
                        success: function(response) {
                            
                            
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: response.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(function() {
                                    // Reload riwayat pemeriksaan
                                    if (typeof PemeriksaanModule !== 'undefined' && typeof PemeriksaanModule.reloadPemeriksaan === 'function') {
                                        PemeriksaanModule.reloadPemeriksaan();
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: response.message,
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal!',
                                text: 'Terjadi kesalahan: ' + error,
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
        });
        
 
    }
    
    // Fungsi untuk menampilkan badge EDIT MODE
    function showEditModeBadge(tgl, jam) {
        // ✅ PERBAIKAN 2: Cek jQuery tersedia
        if (typeof jQuery === 'undefined') {
            
            return;
        }
        
        const $ = jQuery;
        
        // Hapus badge lama jika ada
        $('#editModeBadge').remove();
        
        // Buat badge baru
        const badge = $('<div id="editModeBadge" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 10px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(255, 152, 0, 0.4);">' +
            '<div>' +
            '<i class="material-icons" style="vertical-align: middle; margin-right: 8px;">edit</i>' +
            '<strong>MODE EDIT</strong> - Mengedit data: ' + tgl + ' ' + jam +
            '</div>' +
            '<button type="button" class="btn btn-sm" id="btnCancelEdit" style="background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 5px; padding: 5px 15px;">' +
            '<i class="material-icons" style="font-size: 14px; vertical-align: middle;">close</i> Batal Edit' +
            '</button>' +
            '</div>');
        
        // Sisipkan badge sebelum form SOAPIE
        $('.form-section:has(.form-section-title:contains("SOAPIE"))').before(badge);
        
        // Handler untuk tombol Batal Edit
        $('#btnCancelEdit').on('click', function() {
            cancelEditMode();
        });
        
        // Update tombol simpan
        $('#btnSimpanSOAPIE').html('<i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">save</i> Update Pemeriksaan');
    }
    
    // Fungsi untuk membatalkan mode edit
    function cancelEditMode() {
        window.editPemeriksaanData = null;
        
        // ✅ PERBAIKAN 3: Cek jQuery tersedia
        if (typeof jQuery !== 'undefined') {
            jQuery('#editModeBadge').remove();
            jQuery('#btnSimpanSOAPIE').html('<i class="material-icons" style="vertical-align: middle; margin-right: 5px; font-size: 18px;">save</i> Simpan Pemeriksaan');
        }
        
        // Reset form
        const form = document.getElementById('formPemeriksaan');
        if (form) {
            form.reset();
        }
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Mode Edit Dibatalkan',
                text: 'Form telah direset ke mode input baru',
                timer: 1500,
                showConfirmButton: false
            });
        }
    }
    
    // Expose fungsi ke global
    window.cancelEditMode = cancelEditMode;
    window.showEditModeBadge = showEditModeBadge;
    
    // ✅ PERBAIKAN 4: Initialize dengan waitForjQuery
    waitForjQuery(function($) {
        
        
        $(document).ready(function() {
           
            setTimeout(initEditDeleteHandlers, 600);
        });
    });
    
})(); // End IIFE

// ===================================================
// MULAI PERIKSA FUNCTION
// ===================================================
function mulaiPeriksa() {
    // ✅ PERBAIKAN 5: Cek dependencies
    if (typeof jQuery === 'undefined') {
       
        return;
    }
    
    if (typeof Swal === 'undefined') {
        
        return;
    }
    
    const $ = jQuery;
    
    // ✅ PERBAIKAN 6: Validasi variabel PHP
    const norawat = '<?php echo isset($datapasien["no_rawat"]) ? $datapasien["no_rawat"] : ""; ?>';
    
    if (!norawat) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Data pasien tidak ditemukan',
            confirmButtonText: 'OK'
        });
        return;
    }
    

    
    // Konfirmasi dulu
    Swal.fire({
        title: 'Mulai Pemeriksaan?',
        text: 'Status berkas akan diupdate menjadi "Sudah Diterima"',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2196f3',
        cancelButtonColor: '#999',
        confirmButtonText: 'Ya, Mulai!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Memproses...',
                html: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // AJAX call ke mulai_periksa.php (HANYA UPDATE MUTASI_BERKAS)
            $.ajax({
                url: 'pages/mulai_periksa.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    no_rawat: norawat
                },
                success: function(response) {
                    
                    
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            html: '<strong>' + response.message + '</strong><br><br>' +
                                  'Status: ' + (response.data.status || '-') + '<br>' +
                                  'Diterima: ' + (response.data.diterima || '-'),
                            timer: 2000,
                            showConfirmButton: false
                        }).then(function() {
                            // Scroll ke form pemeriksaan
                            const formElement = $('#formPemeriksaan');
                            if (formElement.length) {
                                $('html, body').animate({
                                    scrollTop: formElement.offset().top - 100
                                }, 500);
                                
                                // Focus ke input pertama
                                setTimeout(function() {
                                    $('input[name="tensi"]').focus();
                                }, 600);
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: response.message || 'Terjadi kesalahan',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                   
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: ' + error,
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
    });
}

// ===================================================
// SELESAI PERIKSA FUNCTION
// ===================================================
function konfirmasiSelesaiPeriksa() {
    // ✅ PERBAIKAN 7: Cek dependencies
    if (typeof jQuery === 'undefined') {
        
        return;
    }
    
    if (typeof Swal === 'undefined') {
       
        return;
    }
    
    const $ = jQuery;
    
    // ✅ PERBAIKAN 8: Validasi variabel PHP
    const norawat = '<?php echo isset($datapasien["no_rawat"]) ? $datapasien["no_rawat"] : ""; ?>';
    
    if (!norawat) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Data pasien tidak ditemukan',
            confirmButtonText: 'OK'
        });
        return;
    }
    

    
    Swal.fire({
        title: 'Konfirmasi Selesai',
        text: 'Apakah Anda yakin ingin menyelesaikan pemeriksaan pasien ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4caf50',
        cancelButtonColor: '#999',
        confirmButtonText: 'Ya, Selesai',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Memproses...',
                html: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // AJAX call ke selesai_pemeriksaan.php
            $.ajax({
                url: 'pages/selesai_pemeriksaan.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    no_rawat: norawat
                },
                success: function(response) {
                    
                    
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            html: '<strong>' + response.message + '</strong><br><br>' +
                                  'Status Registrasi: ' + (response.data.reg_status || '-') + '<br>' +
                                  'Status Berkas: ' + (response.data.berkas_status || '-') + '<br>' +
                                  'Kembali: ' + (response.data.kembali || '-'),
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#4caf50'
                        }).then(function() {
                            // ✅ PERBAIKAN 9: Redirect ke PasienInap untuk RANAP
                            window.location.href = 'index.php?act=PasienInap';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: response.message || 'Terjadi kesalahan',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                   
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: 'Terjadi kesalahan: ' + error,
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
    });
}

// ===================================================
// TOGGLE COLLAPSE FUNCTION
// ===================================================
function toggleCollapse(header) {
    // ✅ PERBAIKAN 10: Validasi parameter
    if (!header) {
      
        return;
    }
    
    const content = header.nextElementSibling;
    
    if (!content) {
       
        return;
    }
    
    header.classList.toggle('collapsed');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
    } else {
        content.style.display = 'none';
    }
}

// ===================================================
// ✅ AUTO-SWITCH TAB dari Notifikasi (Deep Link) - RANAP
// Detect parameter ?tab=lab atau ?tab=rad dari URL
// ===================================================
(function() {
    'use strict';
    
    // ✅ PERBAIKAN 1: Fungsi untuk menunggu jQuery
    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            
            setTimeout(function() {
                waitForjQuery(callback);
            }, 100);
        }
    }
    
    // ✅ PERBAIKAN 2: Fungsi getUrlParam tidak bergantung pada jQuery
    function getUrlParam(param) {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(param);
        } catch (e) {
           
            return null;
        }
    }
    
    function initDeepLinkTab($) {
        const targetTab = getUrlParam('tab');
        const filterNoRawat = getUrlParam('filter_norawat');
        
        if (!targetTab) {
            
            return;
        }
        
      
        
        // Mapping tab parameter ke selector
        const tabMapping = {
            'lab': '#tab_riwayat_lab',
            'rad': '#tab_riwayat_rad',
            'soapie': '#tab_riwayat_soapie',
            'obat': '#tab_riwayat_obat',
            'operasi': '#tab_riwayat_operasi',
            'kunjungan': '#tab_riwayat_kunjungan',
            'semua': '#tab_riwayat_semua'
        };
        
        const tabSelector = tabMapping[targetTab];
        
        if (!tabSelector) {
          
            return;
        }
        
        // ✅ PERBAIKAN 3: Validasi tab element exists
        const $tabLink = $('a[href="' + tabSelector + '"]');
        if (!$tabLink.length) {
           
            return;
        }
        
        // Tunggu sampai DOM ready
        setTimeout(function() {
            
            
            try {
                // 1. Switch ke tab riwayat yang diminta
                $tabLink.tab('show');
                
                // 2. Scroll ke section riwayat
                setTimeout(function() {
                    const $riwayatTabs = $('#riwayatSubTabs');
                    if ($riwayatTabs.length) {
                        $('html, body').animate({
                            scrollTop: $riwayatTabs.offset().top - 100
                        }, 500);
                        
                    } else {
                        
                    }
                    
                    // 3. Jika ada filter_norawat, set filter dropdown setelah content loaded
                    if (filterNoRawat) {
                        setTimeout(function() {
                            applyNoRawatFilter($, targetTab, filterNoRawat);
                        }, 1000);
                    }
                }, 300);
                
            } catch (e) {
                
            }
            
        }, 800);
    }
    
    // ✅ PERBAIKAN 4: Pass jQuery sebagai parameter
    function applyNoRawatFilter($, tabType, noRawat) {
        
        
        try {
            if (tabType === 'lab') {
                const $filterLab = $('#filterNoRawatLab');
                if ($filterLab.length) {
                    $filterLab.val(noRawat);
                    $filterLab.trigger('change');
                   
                } else {
                   
                }
            } else if (tabType === 'rad') {
                const $filterRad = $('#filterNoRawatRad');
                if ($filterRad.length) {
                    $filterRad.val(noRawat);
                    $filterRad.trigger('change');
                    
                } else {
                    
                }
            } else {
                
            }
        } catch (e) {
            
        }
    }
    
    // ✅ PERBAIKAN 5: Initialize dengan waitForjQuery
    waitForjQuery(function($) {
       
        
        $(document).ready(function() {
            
            initDeepLinkTab($);
        });
    });
    
   
    
})(); // End IIFE

// ===================================================
// DROPDOWN RME HANDLER
// ===================================================
(function() {
    'use strict';
    
    function initRmeDropdown() {
        const toggleBtn = document.getElementById('btnRmeToggle');
        const menu = document.getElementById('rmeDropdownMenu');
        
        if (!toggleBtn || !menu) return;

        // Fungsi update posisi menu
        function updateMenuPosition() {
            const rect = toggleBtn.getBoundingClientRect();
            menu.style.top = (rect.bottom + 5) + 'px';
            menu.style.left = rect.left + 'px';
        }
        
        // Toggle dropdown
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isOpen = menu.classList.contains('show');
            
            // Close first
            menu.classList.remove('show');
            toggleBtn.classList.remove('active');
            menu.style.position = '';
            menu.style.top = '';
            menu.style.left = '';
            menu.querySelectorAll('.has-submenu').forEach(function(el) {
                el.classList.remove('active');
            });
            
            // Open
            if (!isOpen) {
                menu.style.position = 'fixed';
                menu.style.zIndex = '999999';
                updateMenuPosition();
                menu.classList.add('show');
                toggleBtn.classList.add('active');
            }
        });

        // Update posisi saat scroll (semua level)
        document.addEventListener('scroll', function() {
            if (menu.classList.contains('show')) {
                updateMenuPosition();
            }
        }, true);
        
        // Submenu toggle
        menu.querySelectorAll('.has-submenu > a').forEach(function(trigger) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const parentLi = this.parentElement;
                const wasActive = parentLi.classList.contains('active');
                
                menu.querySelectorAll('.has-submenu').forEach(function(el) {
                    el.classList.remove('active');
                });
                
                if (!wasActive) {
                    parentLi.classList.add('active');
                }
            });
        });
        
        // Close on click outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#btnMenuRME')) {
                menu.classList.remove('show');
                toggleBtn.classList.remove('active');
                menu.style.position = '';
                menu.style.top = '';
                menu.style.left = '';
                menu.querySelectorAll('.has-submenu').forEach(function(el) {
                    el.classList.remove('active');
                });
            }
        });
        
        // Prevent close saat klik di dalam menu
        // KECUALI link ke index.php?act= (biarkan bubble ke RME Tab Manager)
        menu.addEventListener('click', function(e) {
            var clickedLink = e.target.closest('a[href*="index.php?act="]');
            if (clickedLink) {
                return;
            }
            e.stopPropagation();
        });
        
        //console.log('✓ RME Dropdown initialized');
    }
    
    // Init on DOMContentLoaded or immediately
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRmeDropdown);
    } else {
        initRmeDropdown();
    }
})();

// ===================================================
// RME TAB MANAGER - Browser-like Dynamic Tabs (RANAP)
// Logic sama dengan Rajal, tanpa hidden
// ===================================================
(function() {
    'use strict';

    // Registry tab yang terbuka
    const openTabs = {
        'pemeriksaan': {
            title: 'Pemeriksaan',
            closable: false,
            loaded: true,
            url: null
        }
    };

    let activeTabId = 'pemeriksaan';

    function waitForjQuery(callback) {
        if (typeof jQuery !== 'undefined') {
            callback(jQuery);
        } else {
            setTimeout(function() { waitForjQuery(callback); }, 100);
        }
    }

    waitForjQuery(function($) {
        $(document).ready(function() {
            //console.log('✓ RME Tab Manager (Ranap) ready');

            // === FUNGSI: Switch ke tab ===
            function switchTab(tabId) {
                if (!openTabs[tabId]) return;

                // Jika tab sudah aktif tapi perlu reload
                if (activeTabId === tabId && tabId !== 'pemeriksaan' && !openTabs[tabId].loaded && openTabs[tabId].url) {
                    loadTabContent(tabId);
                    return;
                }

                // Deactivate semua tab
                $('#rmeTabBar .rme-tab').removeClass('active');
                // Deactivate semua content
                $('#rmeContent_pemeriksaan').removeClass('active');
                $('#rmeTabAjaxContainer .rme-tab-content-ajax').removeClass('active');

                // Activate tab yang dipilih
                $('#rmeTabBar .rme-tab[data-tab-id="' + tabId + '"]').addClass('active');

                if (tabId === 'pemeriksaan') {
                    $('#rmeContent_pemeriksaan').addClass('active');
                } else {
                    $('#rmeAjax_' + tabId).addClass('active');

                    // Load konten jika belum loaded
                    if (!openTabs[tabId].loaded && openTabs[tabId].url) {
                        loadTabContent(tabId);
                    }
                }

                activeTabId = tabId;
                //console.log('🔄 Tab switched to:', tabId);
            }

            // === FUNGSI: Load konten tab via AJAX ===
            function loadTabContent(tabId) {
                const tab = openTabs[tabId];
                if (!tab || !tab.url) return;

                const $container = $('#rmeAjax_' + tabId);
                $container.html(
                    '<div class="rme-tab-loading">' +
                    '<i class="material-icons">autorenew</i>' +
                    '<p>Memuat ' + tab.title + '...</p>' +
                    '</div>'
                );

                //console.log('🔄 Loading tab content:', tabId, tab.url);

                $.ajax({
                    url: tab.url,
                    type: 'GET',
                    timeout: 30000,
                    success: function(response) {
                        // Patch response: replace window.location.reload() dan history.back()
                        // di inline scripts sebelum inject ke DOM
                        var patchedResponse = response
                            .replace(/window\.location\.reload\(\)/g, '(window._rmeReloadPage ? window._rmeReloadPage() : window.location.reload())')
                            .replace(/window\.history\.back\(\)/g, '(window.RmeTabManager ? window.RmeTabManager.closeTab(window.RmeTabManager.getActiveTabId()) : window.history.back())');
                        
                        $container.html(patchedResponse);
                        
                        // Patch external scripts yang sudah di-load:
                        // Override onclick="window.history.back()" pada tombol di dalam tab
                        $container.find('[onclick*="history.back"]').each(function() {
                            $(this).removeAttr('onclick').on('click', function() {
                                var cTabId = window.RmeTabManager.getActiveTabId();
                                if (cTabId !== 'pemeriksaan') {
                                    window.RmeTabManager.closeTab(cTabId);
                                }
                            });
                        });
                        
                        tab.loaded = true;
                        //console.log('✅ Tab content loaded:', tabId);
                        
                        // === HOOK: Trigger init functions untuk halaman yang dimuat via AJAX ===
                        setTimeout(function() {
                            // Neonatus
                            if(typeof initNeonatusForm === 'function') initNeonatusForm();
                            // Konsul Medik
                            if(typeof initKonsulMedikForm === 'function') initKonsulMedikForm();
                            // Generic: trigger custom event yang bisa didengar oleh halaman manapun
                            $(document).trigger('rmeTabContentLoaded', [tabId]);
                            //console.log('✅ Tab init hooks triggered for:', tabId);
                        }, 200);
                    },
                    error: function(xhr, status, error) {
                        $container.html(
                            '<div class="alert alert-danger" style="margin:20px;">' +
                            '<strong>Gagal memuat halaman!</strong><br>' +
                            'Error: ' + error + ' (Status: ' + xhr.status + ')' +
                            '</div>'
                        );
                        console.error('❌ Failed to load tab:', tabId, error);
                    }
                });
            }

            // === FUNGSI: Buka tab baru ===
            function openTab(tabId, title, url) {
                // Cek duplikat
                if (openTabs[tabId]) {
                    switchTab(tabId);
                    return;
                }

                // Tambah ke registry
                openTabs[tabId] = {
                    title: title,
                    closable: true,
                    loaded: false,
                    url: url
                };

                // Buat elemen tab
                const $tab = $(
                    '<div class="rme-tab" data-tab-id="' + tabId + '" data-closable="true">' +
                    '<span class="rme-tab-title">' + title + '</span>' +
                    '<span class="rme-tab-close" data-tab-id="' + tabId + '">&times;</span>' +
                    '</div>'
                );
                $('#rmeTabScrollArea').append($tab);

                // Buat container konten
                const $content = $('<div class="rme-tab-content-ajax" id="rmeAjax_' + tabId + '"></div>');
                $('#rmeTabAjaxContainer').append($content);

                // Switch ke tab baru
                switchTab(tabId);

                //console.log('✅ Tab opened:', tabId, title);
            }

            // === FUNGSI: Tutup tab ===
            function closeTab(tabId) {
                if (!openTabs[tabId] || !openTabs[tabId].closable) return;

                // Hapus elemen
                $('#rmeTabBar .rme-tab[data-tab-id="' + tabId + '"]').remove();
                $('#rmeAjax_' + tabId).remove();

                // Hapus dari registry
                delete openTabs[tabId];

                // Jika tab yang ditutup sedang aktif, switch ke tab sebelumnya
                if (activeTabId === tabId) {
                    const keys = Object.keys(openTabs);
                    switchTab(keys[keys.length - 1] || 'pemeriksaan');
                }

                //console.log('✅ Tab closed:', tabId);
            }

            // === EVENT: Klik tab ===
            $(document).on('click', '.rme-tab', function(e) {
                if ($(e.target).hasClass('rme-tab-close')) return;
                const tabId = $(this).data('tab-id');
                switchTab(tabId);
            });

            // === EVENT: Klik close tab ===
            $(document).on('click', '.rme-tab-close', function(e) {
                e.stopPropagation();
                const tabId = $(this).data('tab-id');
                closeTab(tabId);
            });

            // === EVENT: Intercept klik menu RME ===
            $(document).on('click', '#rmeDropdownMenu a[href*="index.php?act="]', function(e) {
                e.preventDefault();

                const href = $(this).attr('href');
                const title = $(this).text().trim();

                // Extract act parameter
                const actMatch = href.match(/act=([^&]+)/);
                if (!actMatch) return;

                const act = actMatch[1];
                const tabId = act.toLowerCase();

                // Buat URL untuk AJAX load via rme_tab_loader.php (global)
                const pageUrl = href.replace('index.php?act=', 'pages/rme_tab_loader.php?act=');

                // Buka tab
                openTab(tabId, title, pageUrl);

                // Tutup dropdown menu
                $('#rmeDropdownMenu').removeClass('show');
                $('#btnRmeToggle').removeClass('active');
                $('#rmeDropdownMenu .has-submenu').removeClass('active');
            });

            // === OVERRIDE: Intercept navigasi dari dalam tab AJAX ===
            // Override tombol KEMBALI (history.back) di dalam tab AJAX
            $(document).on('click', '#rmeTabAjaxContainer button, #rmeTabAjaxContainer a', function(e) {
                var onclick = $(this).attr('onclick') || '';
                // Intercept history.back()
                if (onclick.indexOf('history.back') !== -1) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Cari tab AJAX mana yang mengandung tombol ini
                    var $ajaxPane = $(this).closest('.rme-tab-content-ajax');
                    if ($ajaxPane.length) {
                        var tabId = $ajaxPane.attr('id').replace('rmeAjax_', '');
                        closeTab(tabId);
                    } else {
                        switchTab('pemeriksaan');
                    }
                    return false;
                }
            });

            // Override window.location.reload() dari dalam tab AJAX
            // Caranya: patch setelah AJAX content loaded
            var _origReload = window.location.reload.bind(window.location);
            // Monkey-patch bisa fragile, jadi kita gunakan pendekatan MutationObserver
            // untuk mendeteksi script yang di-load di dalam tab dan patch-nya

            // === HELPER: Reload tab content (bukan full page) ===
            function reloadActiveTab() {
                if (activeTabId && activeTabId !== 'pemeriksaan' && openTabs[activeTabId]) {
                    openTabs[activeTabId].loaded = false;
                    loadTabContent(activeTabId);
                    return true;
                }
                return false;
            }

            // === Expose untuk akses global ===
            window.RmeTabManager = {
                openTab: openTab,
                closeTab: closeTab,
                switchTab: switchTab,
                reloadActiveTab: reloadActiveTab,
                getActiveTabId: function() { return activeTabId; },
                getOpenTabs: function() { return openTabs; }
            };

            // === GLOBAL HOOK: Override window.location untuk tab context ===
            // Ketika script di dalam tab AJAX memanggil window.location.reload(),
            // kita intercept dan reload tab saja
            (function() {
                // Simpan referensi asli
                var origLocationReload = window.location.reload.bind(window.location);
                
                // Override history.back
                var origHistoryBack = window.history.back.bind(window.history);
                window.history._origBack = origHistoryBack;
                
                window.history.back = function() {
                    // Cek apakah sedang di tab AJAX
                    if (window.RmeTabManager && window.RmeTabManager.getActiveTabId() !== 'pemeriksaan') {
                        var tabId = window.RmeTabManager.getActiveTabId();
                        //console.log('🔄 Intercepted history.back() from tab:', tabId);
                        window.RmeTabManager.closeTab(tabId);
                        return;
                    }
                    origHistoryBack();
                };

                // Override location.reload via defineProperty
                // Ini tricky karena location.reload tidak bisa langsung di-override
                // Jadi kita patch melalui custom function
                window._rmeReloadPage = function() {
                    if (window.RmeTabManager && window.RmeTabManager.getActiveTabId() !== 'pemeriksaan') {
                        //console.log('🔄 Intercepted reload from tab:', window.RmeTabManager.getActiveTabId());
                        window.RmeTabManager.reloadActiveTab();
                        return;
                    }
                    origLocationReload();
                };
                
                // Patch: Override location.reload melalui Object.defineProperty
                // Ini memungkinkan kita intercept window.location.reload() calls
                try {
                    var reloadDescriptor = Object.getOwnPropertyDescriptor(window.location, 'reload');
                    if (!reloadDescriptor || reloadDescriptor.configurable !== false) {
                        // location.reload biasanya non-configurable, jadi kita pakai proxy approach
                    }
                } catch(e) {
                    // Fallback: tidak bisa override location.reload langsung
                }
                
                // Alternative approach: Patch window.location.reload via proxy
                // Karena location.reload tidak bisa di-override langsung,
                // kita intercept dengan MutationObserver pada script execution
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === 1 && node.tagName === 'SCRIPT' && node.closest('#rmeTabAjaxContainer')) {
                                // Script di dalam tab AJAX
                                var origText = node.textContent;
                                if (origText.indexOf('window.location.reload') !== -1) {
                                    node.textContent = origText.replace(
                                        /window\.location\.reload\(\)/g,
                                        '(window._rmeReloadPage ? window._rmeReloadPage() : window.location.reload())'
                                    );
                                }
                                if (origText.indexOf('location.reload') !== -1 && origText.indexOf('window.location.reload') === -1) {
                                    node.textContent = node.textContent.replace(
                                        /location\.reload\(\)/g,
                                        '(window._rmeReloadPage ? window._rmeReloadPage() : location.reload())'
                                    );
                                }
                                // Intercept window.location.href redirects yang mengarah ke act=
                                if (origText.indexOf('window.location.href') !== -1) {
                                    node.textContent = node.textContent.replace(
                                        /window\.location\.href\s*=\s*['"]\?act=/g,
                                        'if(window._rmeReloadPage){window._rmeReloadPage();return;}window.location.href=\'?act='
                                    );
                                }
                            }
                        });
                    });
                });
                
                var ajaxContainer = document.getElementById('rmeTabAjaxContainer');
                if (ajaxContainer) {
                    observer.observe(ajaxContainer, { childList: true, subtree: true });
                    //console.log('✅ MutationObserver active on rmeTabAjaxContainer');
                }
            })();

            //console.log('✅ RME Tab Manager (Ranap) initialized');
        });
    });
})();
</script>
