<?php
session_start();
require_once('../conf/conf.php');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ===================================================
// HELPER FUNCTIONS
// ===================================================
if(!function_exists('getBadgeVital')) {
    function getBadgeVital($label, $type = 'normal') {
        $colors = [
            'normal' => '#28a745',
            'warning' => '#ffc107',
            'danger' => '#dc3545',
            'info' => '#17a2b8'
        ];
        $color = $colors[$type] ?? $colors['normal'];
        return '<span style="display: inline-block; background: '.$color.'; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px; font-weight: 600;">'.$label.'</span>';
    }
}

if(!function_exists('evaluateVitalSigns')) {
    function evaluateVitalSigns($row) {
        $result = [];
        
        // Evaluasi Tensi
        $tensi = $row['tensi'];
        $result['tensi'] = ['status' => 'normal', 'label' => 'Normal'];
        if(!empty($tensi) && $tensi != '-') {
            $tensiParts = explode('/', $tensi);
            if(count($tensiParts) == 2) {
                $sistolik = intval($tensiParts[0]);
                $diastolik = intval($tensiParts[1]);
                
                if($sistolik >= 180 || $diastolik >= 120) {
                    $result['tensi'] = ['status' => 'danger', 'label' => 'Krisis'];
                } elseif($sistolik >= 140 || $diastolik >= 95) {
                    $result['tensi'] = ['status' => 'danger', 'label' => 'Tinggi'];
                } elseif(($sistolik >= 130 && $sistolik < 140) || ($diastolik >= 90 && $diastolik < 95)) {
                    $result['tensi'] = ['status' => 'warning', 'label' => 'Tinggi Normal'];
                } elseif($sistolik < 90 || $diastolik < 60) {
                    $result['tensi'] = ['status' => 'warning', 'label' => 'Rendah'];
                }
            }
        }
        
        // Evaluasi Suhu
        $suhu = floatval($row['suhu_tubuh']);
        $result['suhu'] = ['status' => 'normal', 'label' => 'Normal'];
        if($suhu > 0) {
            if($suhu >= 37.5) {
                $result['suhu'] = ['status' => 'danger', 'label' => 'Demam'];
            } elseif($suhu < 36.0) {
                $result['suhu'] = ['status' => 'warning', 'label' => 'Rendah'];
            }
        }
        
        // Evaluasi Nadi
        $nadi = intval($row['nadi']);
        $result['nadi'] = ['status' => 'normal', 'label' => 'Normal'];
        if($nadi > 0) {
            if($nadi > 100) {
                $result['nadi'] = ['status' => 'danger', 'label' => 'Tinggi'];
            } elseif($nadi < 60) {
                $result['nadi'] = ['status' => 'warning', 'label' => 'Rendah'];
            }
        }
        
        // Evaluasi RR
        $rr = intval($row['respirasi']);
        $result['rr'] = ['status' => 'normal', 'label' => 'Normal'];
        if($rr > 0) {
            if($rr > 20) {
                $result['rr'] = ['status' => 'danger', 'label' => 'Tinggi'];
            } elseif($rr < 12) {
                $result['rr'] = ['status' => 'warning', 'label' => 'Rendah'];
            }
        }
        
        // Evaluasi SpO2
        $spo2 = intval($row['spo2']);
        $result['spo2'] = ['status' => 'normal', 'label' => 'Normal'];
        if($spo2 > 0) {
            if($spo2 < 95) {
                $result['spo2'] = ['status' => 'danger', 'label' => 'Rendah'];
            } elseif($spo2 >= 95 && $spo2 <= 97) {
                $result['spo2'] = ['status' => 'warning', 'label' => 'Perhatian'];
            }
        }
        
        // Evaluasi GCS
        $gcs = intval($row['gcs']);
        $result['gcs'] = ['status' => 'normal', 'label' => 'Normal'];
        if($gcs > 0) {
            if($gcs < 13) {
                $result['gcs'] = ['status' => 'danger', 'label' => 'Rendah'];
            } elseif($gcs >= 13 && $gcs <= 14) {
                $result['gcs'] = ['status' => 'warning', 'label' => 'Perhatian'];
            }
        }
        
        return $result;
    }
}

if(!function_exists('isSOAPIEEmpty')) {
    function isSOAPIEEmpty($row) {
        return empty(trim($row['keluhan'])) && 
               empty(trim($row['pemeriksaan'])) && 
               empty(trim($row['penilaian'])) && 
               empty(trim($row['rtl'])) && 
               empty(trim($row['instruksi'])) && 
               empty(trim($row['evaluasi']));
    }
}

if(!function_exists('getBadgePetugas')) {
    function getBadgePetugas($nama) {
        $isDokter = (stripos($nama, 'dr.') !== false || stripos($nama, 'drg.') !== false);
        
        if($isDokter) {
            return '<span style="display: inline-block; background: #28a745; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-top: 3px;">
                        <i class="material-icons" style="font-size: 12px; vertical-align: middle; margin-right: 2px;">person</i>
                        '.$nama.'
                    </span>';
        } else {
            return '<span style="display: inline-block; background: #2196F3; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-top: 3px;">
                        <i class="material-icons" style="font-size: 12px; vertical-align: middle; margin-right: 2px;">local_hospital</i>
                        '.$nama.'
                    </span>';
        }
    }
}

if(!function_exists('formatSOAPIEContent')) {
    function formatSOAPIEContent($text) {
        if(empty($text)) return '';
        
        $text = trim($text);
        $text = preg_replace("/(\r\n|\r|\n){2,}/", "\n", $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = htmlspecialchars($text);
        $text = nl2br($text);
        
        return $text;
    }
}

if(!function_exists('renderSOAPIETable')) {
    function renderSOAPIETable($query, $page, $total_data, $total_pages, $limit, $offset, $type = 'ralan') {
        // Fetch semua data ke array dulu untuk grouping
        $data = [];
        while($row = mysqli_fetch_array($query)){
            $data[] = $row;
        }
        
        // Group data by no_rawat untuk rowspan
        $grouped = [];
        foreach($data as $row) {
            $no_rawat = $row['no_rawat'];
            if(!isset($grouped[$no_rawat])) {
                $grouped[$no_rawat] = [];
            }
            $grouped[$no_rawat][] = $row;
        }
        
        // CSS untuk tabel
        echo '<style>
            .soapie-table {
                width: 100%;
                margin: 0;
                border-collapse: collapse;
                font-size: 13px;
                background: transparent;
            }
            .soapie-table th {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 8px;
                text-align: left;
                font-weight: 600;
                border: none;
                border-bottom: 2px solid #5a67d8;
                white-space: nowrap;
            }
            .soapie-table th:first-child {
                border-left: none;
            }
            .soapie-table th:last-child {
                border-right: none;
            }
            .soapie-table td {
                padding: 12px 8px;
                border: none;
                border-bottom: 1px solid #e0e0e0;
                vertical-align: top;
                background: white;
            }
            .soapie-table td:first-child {
                border-left: none;
            }
            .soapie-table td:last-child {
                border-right: none;
            }
            .soapie-table tbody tr:hover {
                background-color: #f8f9fa;
            }
            .soapie-table tbody tr:hover td {
                background-color: #f8f9fa;
            }
            /* Rowspan cells with border */
            .rowspan-cell {
                border-right: 2px solid #667eea !important;
                background: #f8f9fb !important;
            }
            /* First row of group - border top tebal */
            .group-first {
                border-top: 3px solid #667eea !important;
            }
            /* Vital Signs - Vertical Layout (seperti pemeriksaan_ajax.php) */
            .vital-signs {
                display: flex;
                flex-direction: column;
                gap: 6px;
                font-size: 13px;
            }
            .vital-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 0;
                background: transparent;
                border: none;
                border-radius: 0;
            }
            .vital-item strong {
                font-weight: 600;
                color: #333;
                min-width: 90px;
                display: inline-block;
            }
            .vital-item-value {
                color: #555;
            }
            }
            .soapie-cell {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .soapie-item {
                display: flex;
                flex-direction: column;
                gap: 6px;
                padding: 10px;
                border-radius: 6px;
                background: #fff;
            }
            .soapie-item-header {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .soapie-item.s-section { border-left: 4px solid #4CAF50; background: #f1f8f4; }
            .soapie-item.o-section { border-left: 4px solid #2196F3; background: #f5f9ff; }
            .soapie-item.a-section { border-left: 4px solid #ff9800; background: #fff8f0; }
            .soapie-item.p-section { border-left: 4px solid #9c27b0; background: #f3e5f5; }
            .soapie-item.i-section { border-left: 4px solid #00bcd4; background: #e0f7fa; }
            .soapie-item.e-section { border-left: 4px solid #e91e63; background: #fce4ec; }
            .soapie-label {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                font-weight: 700;
                font-size: 12px;
                color: white;
                flex-shrink: 0;
            }
            .soapie-label.s { background: #4CAF50; }
            .soapie-label.o { background: #2196F3; }
            .soapie-label.a { background: #ff9800; }
            .soapie-label.p { background: #9c27b0; }
            .soapie-label.i { background: #00bcd4; }
            .soapie-label.e { background: #e91e63; }
            .soapie-label-text {
                display: inline-block;
                font-weight: 600;
                font-size: 11px;
                color: #666;
                margin-left: 4px;
                letter-spacing: 0.3px;
                text-transform: uppercase;
            }
            .soapie-content {
                display: block;
                line-height: 1.6;
                color: #333;
                font-size: 13px;
                padding-left: 0;
            }
            .no-soapie-notice {
                padding: 12px;
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                color: #856404;
                border-radius: 4px;
                font-size: 13px;
                font-style: italic;
            }
            .pagination-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-top: 20px;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 6px;
            }
            .pagination-info {
                font-size: 13px;
                color: #666;
            }
            .pagination-buttons {
                display: flex;
                gap: 5px;
                align-items: center;
            }
            .btn-page {
                min-width: 35px;
                height: 35px;
                padding: 6px 12px;
            }
            .btn-page.active {
                background: #2196F3 !important;
                color: white !important;
                font-weight: 600;
            }
            .btn-page-nav {
                display: flex;
                align-items: center;
                gap: 4px;
            }
        </style>';
        
        // Tabel langsung tanpa wrapper card (seperti E-Resep)
        echo '<table class="soapie-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th style="width: 300px;">Informasi & Vital Signs</th>
                        <th>SOAPIE</th>
                    </tr>
                </thead>
                <tbody>';
        
        $no = $offset + 1;
        
        // Loop through grouped data
        foreach($grouped as $no_rawat => $rows) {
            $rowspan = count($rows);
            $first = true;
            
            foreach($rows as $idx => $row) {
                // Evaluasi vital signs
                $vital = evaluateVitalSigns($row);
                
                // Format vital signs (dengan tanggal pemeriksaan + petugas di atas)
                $vitalSigns = '
                <div class="vital-signs">
                    <div class="vital-item">
                        <strong>Tensi</strong>
                        <span class="vital-item-value">: '.$row['tensi'].' mmHg '.getBadgeVital($vital['tensi']['label'], $vital['tensi']['status']).'</span>
                    </div>
                    <div class="vital-item">
                        <strong>Suhu</strong>
                        <span class="vital-item-value">: '.$row['suhu_tubuh'].' °C '.getBadgeVital($vital['suhu']['label'], $vital['suhu']['status']).'</span>
                    </div>
                    <div class="vital-item">
                        <strong>Nadi</strong>
                        <span class="vital-item-value">: '.$row['nadi'].' x/menit '.getBadgeVital($vital['nadi']['label'], $vital['nadi']['status']).'</span>
                    </div>
                    <div class="vital-item">
                        <strong>RR</strong>
                        <span class="vital-item-value">: '.$row['respirasi'].' x/menit '.getBadgeVital($vital['rr']['label'], $vital['rr']['status']).'</span>
                    </div>
                    <div class="vital-item">
                        <strong>TB/BB</strong>
                        <span class="vital-item-value">: '.$row['tinggi'].'/'.$row['berat'].' cm/kg</span>
                    </div>
                    <div class="vital-item">
                        <strong>SpO₂</strong>
                        <span class="vital-item-value">: '.$row['spo2'].' % '.getBadgeVital($vital['spo2']['label'], $vital['spo2']['status']).'</span>
                    </div>
                    <div class="vital-item">
                        <strong>GCS</strong>
                        <span class="vital-item-value">: '.$row['gcs'].' '.getBadgeVital($vital['gcs']['label'], $vital['gcs']['status']).'</span>
                    </div>
                    <div class="vital-item">
                        <strong>Kesadaran</strong>
                        <span class="vital-item-value">: '.$row['kesadaran'].'</span>
                    </div>
                    <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #ddd;">
                        <button type="button" class="btn btn-primary btn-xs waves-effect copy_soap_pasien"
                            data-keluhan="'.htmlspecialchars($row['keluhan'], ENT_QUOTES).'"
                            data-pemeriksaan="'.htmlspecialchars($row['pemeriksaan'], ENT_QUOTES).'"
                            data-penilaian="'.htmlspecialchars($row['penilaian'], ENT_QUOTES).'"
                            data-rtl="'.htmlspecialchars($row['rtl'], ENT_QUOTES).'"
                            data-instruksi="'.htmlspecialchars($row['instruksi'], ENT_QUOTES).'"
                            data-evaluasi="'.htmlspecialchars($row['evaluasi'], ENT_QUOTES).'"
                            title="Copy SOAPIE ke form">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">content_copy</i> Copy
                        </button>
                    </div>
                </div>
            ';
            
            // Cek apakah SOAPIE kosong
            if(isSOAPIEEmpty($row)) {
                $soapie = '<div class="no-soapie-notice">Belum ada catatan pemeriksaan medis (hanya vital signs)</div>';
            } else {
                // Format SOAPIE
                $soapie = '<div class="soapie-cell">';
                
                // S - Subjective
                if(!empty(trim($row['keluhan']))) {
                    $soapie .= '<div class="soapie-item s-section">
                        <div class="soapie-item-header">
                            <span class="soapie-label s">S</span>
                            <span class="soapie-label-text">SUBJECTIVE</span>
                        </div>
                        <div class="soapie-content">'.formatSOAPIEContent($row['keluhan']).'</div>
                    </div>';
                }
                
                // O - Objective
                if(!empty(trim($row['pemeriksaan']))) {
                    $soapie .= '<div class="soapie-item o-section">
                        <div class="soapie-item-header">
                            <span class="soapie-label o">O</span>
                            <span class="soapie-label-text">OBJECTIVE</span>
                        </div>
                        <div class="soapie-content">'.formatSOAPIEContent($row['pemeriksaan']).'</div>
                    </div>';
                }
                
                // A - Assessment
                if(!empty(trim($row['penilaian']))) {
                    $soapie .= '<div class="soapie-item a-section">
                        <div class="soapie-item-header">
                            <span class="soapie-label a">A</span>
                            <span class="soapie-label-text">ASSESSMENT</span>
                        </div>
                        <div class="soapie-content">'.formatSOAPIEContent($row['penilaian']).'</div>
                    </div>';
                }
                
                // P - Plan
                if(!empty(trim($row['rtl']))) {
                    $soapie .= '<div class="soapie-item p-section">
                        <div class="soapie-item-header">
                            <span class="soapie-label p">P</span>
                            <span class="soapie-label-text">PLAN</span>
                        </div>
                        <div class="soapie-content">'.formatSOAPIEContent($row['rtl']).'</div>
                    </div>';
                }
                
                // I - Intervention
                if(!empty(trim($row['instruksi']))) {
                    $soapie .= '<div class="soapie-item i-section">
                        <div class="soapie-item-header">
                            <span class="soapie-label i">I</span>
                            <span class="soapie-label-text">INTERVENTION</span>
                        </div>
                        <div class="soapie-content">'.formatSOAPIEContent($row['instruksi']).'</div>
                    </div>';
                }
                
                // E - Evaluation
                if(!empty(trim($row['evaluasi']))) {
                    $soapie .= '<div class="soapie-item e-section">
                        <div class="soapie-item-header">
                            <span class="soapie-label e">E</span>
                            <span class="soapie-label-text">EVALUATION</span>
                        </div>
                        <div class="soapie-content">'.formatSOAPIEContent($row['evaluasi']).'</div>
                    </div>';
                }
                
                $soapie .= '</div>';
            }
            
            // Render row dengan rowspan untuk kolom No
            $group_class = $first ? 'group-first' : '';
            
            echo '<tr class="'.$group_class.'">';
            
            // Kolom No - hanya tampil di row pertama dengan rowspan
            if($first) {
                echo '<td align="center" rowspan="'.$rowspan.'" class="rowspan-cell">'.$no.'</td>';
            }
            
            // Kolom gabungan Informasi & Vital Signs
            echo '<td style="padding: 12px;">
                    <div style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #e0e0e0;">
                        <div style="font-size: 12px; color: #333; font-weight: 600;">'.$row['no_rawat'].'</div>
                        <div style="font-size: 11px; color: #666; margin-top: 2px;">'.konversiTanggal($row['tgl_perawatan']).' '.$row['jam_rawat'].'</div>
                        <div style="font-size: 11px; color: #999; margin-top: 2px;">'.$row['nm_poli'].'</div>
                        <div style="margin-top: 4px;">'.getBadgePetugas($row['nama_petugas']).'</div>
                    </div>
                    '.$vitalSigns.'
                  </td>';
            
            // Kolom SOAPIE
            echo '<td>'.$soapie.'</td>
                </tr>';
            
            $first = false;
            }
            
            // Increment counter setelah group selesai
            $no++;
        }
        
        echo '</tbody></table>';
        
        // Pagination
        echo '<div class="pagination-container">';
        echo '<div class="pagination-info">';
        echo 'Menampilkan halaman '.$page.' dari '.$total_pages.' ';
        echo '(Total: '.$total_data.' riwayat)';
        echo '</div>';
        
        echo '<div class="pagination-buttons">';
        
        // Inject inline function untuk ensure pagination works
        echo '<script>
            if(typeof loadSOAPIEPage === "undefined") {
                console.log("⚠️ loadSOAPIEPage not found, creating fallback");
                window.loadSOAPIEPage = function(page, type) {
                    if(typeof SOAPIEModule !== "undefined" && SOAPIEModule.loadPage) {
                        SOAPIEModule.loadPage(page, type);
                    } else {
                        console.error("SOAPIEModule not loaded!");
                        alert("Error: JavaScript module not loaded. Please refresh the page.");
                    }
                };
            }
        </script>';
        
        // Tombol Previous
        if($page > 1) {
            echo '<button class="btn btn-sm btn-primary btn-page-nav" onclick="loadSOAPIEPage('.($page-1).', \''.$type.'\')">
                    <i class="material-icons" style="font-size: 16px; vertical-align: middle;">chevron_left</i> Previous
                  </button>';
        } else {
            echo '<button class="btn btn-sm btn-default" disabled>
                    <i class="material-icons" style="font-size: 16px; vertical-align: middle;">chevron_left</i> Previous
                  </button>';
        }
        
        // Tombol halaman
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if($start_page > 1) {
            echo '<button class="btn btn-sm btn-default btn-page" onclick="loadSOAPIEPage(1, \''.$type.'\')">1</button>';
            if($start_page > 2) {
                echo '<span style="padding: 0 5px;">...</span>';
            }
        }
        
        for($i = $start_page; $i <= $end_page; $i++) {
            $active_class = ($i == $page) ? 'active' : '';
            echo '<button class="btn btn-sm btn-default btn-page '.$active_class.'" onclick="loadSOAPIEPage('.$i.', \''.$type.'\')">'.$i.'</button>';
        }
        
        if($end_page < $total_pages) {
            if($end_page < $total_pages - 1) {
                echo '<span style="padding: 0 5px;">...</span>';
            }
            echo '<button class="btn btn-sm btn-default btn-page" onclick="loadSOAPIEPage('.$total_pages.', \''.$type.'\')">'.$total_pages.'</button>';
        }
        
        // Tombol Next
        if($page < $total_pages) {
            echo '<button class="btn btn-sm btn-primary btn-page-nav" onclick="loadSOAPIEPage('.($page+1).', \''.$type.'\')">
                    Next <i class="material-icons" style="font-size: 16px; vertical-align: middle;">chevron_right</i>
                  </button>';
        } else {
            echo '<button class="btn btn-sm btn-default" disabled>
                    Next <i class="material-icons" style="font-size: 16px; vertical-align: middle;">chevron_right</i>
                  </button>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // ===== INLINE SCRIPT HANDLER TOMBOL COPY SOAPIE =====
        echo '
        <script>
        (function() {
            var $ = jQuery;
            
            $(".copy_soap_pasien").off("click").on("click", function(e) {
                e.preventDefault();
                var btn = $(this);
                var data = btn.data();

                // Isi form SOAPIE — pakai loadFromText helper supaya text masuk ke
                // CATATAN TAMBAHAN (free text), structured form tetap kosong
                if (window.SOAPIESubjective && typeof window.SOAPIESubjective.loadFromText === "function") {
                    window.SOAPIESubjective.loadFromText(data.keluhan || "");
                } else {
                    $("textarea[name=subjective]").val(data.keluhan || "");
                }
                if (window.SOAPIEObjective && typeof window.SOAPIEObjective.loadFromText === "function") {
                    window.SOAPIEObjective.loadFromText(data.pemeriksaan || "");
                } else {
                    $("textarea[name=objective]").val(data.pemeriksaan || "");
                }
                if (window.SOAPIEAssessment && typeof window.SOAPIEAssessment.loadFromText === "function") {
                    window.SOAPIEAssessment.loadFromText(data.penilaian || "");
                } else {
                    $("textarea[name=assessment]").val(data.penilaian || "");
                }
                if (window.SOAPIEPlan && typeof window.SOAPIEPlan.loadFromText === "function") {
                    window.SOAPIEPlan.loadFromText(data.rtl || "");
                } else {
                    $("textarea[name=plan]").val(data.rtl || "");
                }
                if (window.SOAPIEIntervention && typeof window.SOAPIEIntervention.loadFromText === "function") {
                    window.SOAPIEIntervention.loadFromText(data.instruksi || "");
                } else {
                    $("textarea[name=intervention]").val(data.instruksi || "");
                }
                if (window.SOAPIEEvaluation && typeof window.SOAPIEEvaluation.loadFromText === "function") {
                    window.SOAPIEEvaluation.loadFromText(data.evaluasi || "");
                } else {
                    $("textarea[name=evaluation]").val(data.evaluasi || "");
                }

                // Update character count jika ada
                $(".soapie-textarea").each(function() {
                    var charCount = $(this).val().length;
                    $(this).closest(".soapie-card-body").find(".char-current").text(charCount);
                });

                // Scroll ke form
                if($("#formPemeriksaan").length) {
                    $("html, body").animate({
                        scrollTop: $("#formPemeriksaan").offset().top - 100
                    }, 500);
                }

                // Switch ke tab Pemeriksaan jika ada
                $("a[href=\"#tab_pemeriksaan\"]").tab("show");

                // Toast notification (top-end, sama seperti tombol Edit)
                if (typeof Swal !== "undefined") {
                    Swal.fire({
                        icon: "success",
                        title: "Data Disalin",
                        text: "Data dimuat ke form (semua teks → Catatan Tambahan)",
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: "top-end"
                    });
                }
            });
        })();
        </script>
        ';
    }
}

// ===================================================
// ACTION: load_soapie_ralan - Load SOAPIE Rawat Jalan
// ===================================================
if($action == 'load_soapie_ralan'){
    $norm = isset($_GET['norm']) ? validTeks4($_GET['norm'], 20) : '';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    
    if(empty($norm)){
        echo '<div class="alert alert-warning">Nomor RM tidak valid.</div>';
        exit();
    }
    
    // Query untuk hitung total data RAWAT JALAN
    $query_count = bukaquery("SELECT COUNT(*) as total 
                              FROM pemeriksaan_ralan pr
                              LEFT JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                              WHERE rp.no_rkm_medis = '$norm'");
    $row_count = mysqli_fetch_array($query_count);
    $total_data = $row_count['total'];
    $total_pages = ceil($total_data / $limit);
    
    if($total_data == 0){
        echo '<div class="alert alert-info">Belum ada riwayat SOAPIE Rawat Jalan untuk pasien ini.</div>';
        exit();
    }
    
    // Query data RAWAT JALAN
    $query = bukaquery("SELECT 
                            pr.no_rawat,
                            pr.tgl_perawatan,
                            pr.jam_rawat,
                            pr.suhu_tubuh,
                            pr.tensi,
                            pr.nadi,
                            pr.respirasi,
                            pr.tinggi,
                            pr.berat,
                            pr.spo2,
                            pr.gcs,
                            pr.kesadaran,
                            pr.keluhan,
                            pr.pemeriksaan,
                            pr.penilaian,
                            pr.rtl,
                            pr.instruksi,
                            pr.evaluasi,
                            rp.no_rkm_medis,
                            rp.tgl_registrasi,
                            rp.jam_reg,
                            p.nm_pasien,
                            pol.nm_poli,
                            pg.nama AS nama_petugas
                        FROM pemeriksaan_ralan pr
                        LEFT JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                        LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
                        LEFT JOIN pegawai pg ON pr.nip = pg.nik
                        WHERE rp.no_rkm_medis = '$norm'
                        ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
                        LIMIT $limit OFFSET $offset");
    
    renderSOAPIETable($query, $page, $total_data, $total_pages, $limit, $offset, 'ralan');
    exit();
}

// ===================================================
// ACTION: load_soapie_ranap - Load SOAPIE Rawat Inap
// ===================================================
if($action == 'load_soapie_ranap'){
    $norm = isset($_GET['norm']) ? validTeks4($_GET['norm'], 20) : '';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    
    if(empty($norm)){
        echo '<div class="alert alert-warning">Nomor RM tidak valid.</div>';
        exit();
    }
    
    // Query untuk hitung total data RAWAT INAP
    $query_count = bukaquery("SELECT COUNT(*) as total 
                              FROM pemeriksaan_ranap pr
                              LEFT JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                              WHERE rp.no_rkm_medis = '$norm'");
    $row_count = mysqli_fetch_array($query_count);
    $total_data = $row_count['total'];
    $total_pages = ceil($total_data / $limit);
    
    if($total_data == 0){
        echo '<div class="alert alert-info">Belum ada riwayat SOAPIE Rawat Inap untuk pasien ini.</div>';
        exit();
    }
    
    // Query data RAWAT INAP
    $query = bukaquery("SELECT 
                            pr.no_rawat,
                            pr.tgl_perawatan,
                            pr.jam_rawat,
                            pr.suhu_tubuh,
                            pr.tensi,
                            pr.nadi,
                            pr.respirasi,
                            pr.tinggi,
                            pr.berat,
                            pr.spo2,
                            pr.gcs,
                            pr.kesadaran,
                            pr.keluhan,
                            pr.pemeriksaan,
                            pr.penilaian,
                            pr.rtl,
                            pr.instruksi,
                            pr.evaluasi,
                            rp.no_rkm_medis,
                            rp.tgl_registrasi,
                            rp.jam_reg,
                            p.nm_pasien,
                            'Rawat Inap' as nm_poli,
                            pg.nama AS nama_petugas
                        FROM pemeriksaan_ranap pr
                        LEFT JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                        LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN pegawai pg ON pr.nip = pg.nik
                        WHERE rp.no_rkm_medis = '$norm'
                        ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
                        LIMIT $limit OFFSET $offset");
    
    renderSOAPIETable($query, $page, $total_data, $total_pages, $limit, $offset, 'ranap');
    exit();
}

// ===================================================
// ACTION: load_soapie (backward compatibility)
// ===================================================
if($action == 'load_soapie'){
    // Redirect ke load_soapie_ralan untuk backward compatibility
    $_GET['action'] = 'load_soapie_ralan';
    $action = 'load_soapie_ralan';
    
    $norm = isset($_GET['norm']) ? validTeks4($_GET['norm'], 20) : '';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 5;
    $offset = ($page - 1) * $limit;
    
    if(empty($norm)){
        echo '<div class="alert alert-warning">Nomor RM tidak valid.</div>';
        exit();
    }
    
    $query_count = bukaquery("SELECT COUNT(*) as total 
                              FROM pemeriksaan_ralan pr
                              LEFT JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                              WHERE rp.no_rkm_medis = '$norm'");
    $row_count = mysqli_fetch_array($query_count);
    $total_data = $row_count['total'];
    $total_pages = ceil($total_data / $limit);
    
    if($total_data == 0){
        echo '<div class="alert alert-info">Belum ada riwayat SOAPIE Rawat Jalan untuk pasien ini.</div>';
        exit();
    }
    
    $query = bukaquery("SELECT 
                            pr.no_rawat,
                            pr.tgl_perawatan,
                            pr.jam_rawat,
                            pr.suhu_tubuh,
                            pr.tensi,
                            pr.nadi,
                            pr.respirasi,
                            pr.tinggi,
                            pr.berat,
                            pr.spo2,
                            pr.gcs,
                            pr.kesadaran,
                            pr.keluhan,
                            pr.pemeriksaan,
                            pr.penilaian,
                            pr.rtl,
                            pr.instruksi,
                            pr.evaluasi,
                            rp.no_rkm_medis,
                            p.nm_pasien,
                            pol.nm_poli,
                            pg.nama AS nama_petugas
                        FROM pemeriksaan_ralan pr
                        LEFT JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
                        LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
                        LEFT JOIN poliklinik pol ON rp.kd_poli = pol.kd_poli
                        LEFT JOIN pegawai pg ON pr.nip = pg.nik
                        WHERE rp.no_rkm_medis = '$norm'
                        ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
                        LIMIT $limit OFFSET $offset");
    
    renderSOAPIETable($query, $page, $total_data, $total_pages, $limit, $offset, 'ralan');
    exit();
}

// ===================================================
// DEFAULT: Action tidak dikenal
// ===================================================
echo json_encode(['status' => 'error', 'message' => 'Action tidak valid']);
exit();
?>