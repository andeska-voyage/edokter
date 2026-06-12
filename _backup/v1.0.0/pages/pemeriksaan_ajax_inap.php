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
        // Cek apakah semua field SOAPIE kosong
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
        // Cek apakah nama mengandung prefix dokter (dr. atau drg.)
        $isDokter = (stripos($nama, 'dr.') !== false || stripos($nama, 'drg.') !== false);
        
        if($isDokter) {
            // Dokter = badge hijau
            return '<span style="display: inline-block; background: #28a745; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-top: 3px;">
                        <i class="material-icons" style="font-size: 12px; vertical-align: middle; margin-right: 2px;">person</i>
                        '.$nama.'
                    </span>';
        } else {
            // Perawat = badge biru
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
        
        // Trim whitespace di awal dan akhir
        $text = trim($text);
        
        // Ganti multiple line breaks (2 atau lebih) dengan single line break
        $text = preg_replace("/(\r\n|\r|\n){2,}/", "\n", $text);
        
        // Ganti carriage return dengan newline standar
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        
        // Escape HTML special characters
        $text = htmlspecialchars($text);
        
        // Convert newline ke <br> tag
        $text = nl2br($text);
        
        return $text;
    }
}

// ===================================================
// ACTION: load_riwayat (Mode lama - full SOAPIE table)
// ===================================================
if($action == 'load_riwayat'){
    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
    
    if(empty($norawat)){
        echo json_encode(['status' => 'error', 'message' => 'Parameter tidak valid']);
        exit();
    }
    
    // Query data riwayat
    $query_periksa = bukaquery("SELECT pr.*, pg.nama as nama_petugas, b.nm_bangsal
                                FROM pemeriksaan_ranap pr
                                LEFT JOIN pegawai pg ON pr.nip = pg.nik
                                LEFT JOIN kamar_inap ki ON pr.no_rawat = ki.no_rawat
                                LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                                LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                                WHERE pr.no_rawat = '$norawat' 
                                GROUP BY pr.no_rawat, pr.tgl_perawatan, pr.jam_rawat
                                ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC");
    
    $html = '<style>
        .riwayat-table { font-size: 13px; }
        .riwayat-table td, .riwayat-table th { padding: 10px !important; vertical-align: top !important; }
        .riwayat-table .vital-label { display: inline-block; min-width: 90px; font-weight: 600; color: #333; }
        .riwayat-table .soapie-section { 
            padding: 8px 0 8px 12px; 
            border-bottom: 1px solid #f0f0f0;
            line-height: 1.5;
            border-left: 3px solid #e0e0e0;
            margin-bottom: 4px;
        }
        .riwayat-table .soapie-section:last-child { border-bottom: none; margin-bottom: 0; }
        .riwayat-table .soapie-section.s-section { border-left-color: #4CAF50; background: #f1f8f4; }
        .riwayat-table .soapie-section.o-section { border-left-color: #2196F3; background: #e3f2fd; }
        .riwayat-table .soapie-section.a-section { border-left-color: #FF9800; background: #fff3e0; }
        .riwayat-table .soapie-section.p-section { border-left-color: #9C27B0; background: #f3e5f5; }
        .riwayat-table .soapie-section.i-section { border-left-color: #00BCD4; background: #e0f7fa; }
        .riwayat-table .soapie-section.e-section { border-left-color: #E91E63; background: #fce4ec; }
        .riwayat-table .soapie-label {
            display: inline-block;
            font-weight: bold;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            min-width: 20px;
            text-align: center;
            margin-right: 5px;
        }
        .riwayat-table .soapie-label.s { background: #4CAF50; }
        .riwayat-table .soapie-label.o { background: #2196F3; }
        .riwayat-table .soapie-label.a { background: #FF9800; }
        .riwayat-table .soapie-label.p { background: #9C27B0; }
        .riwayat-table .soapie-label.i { background: #00BCD4; }
        .riwayat-table .soapie-label.e { background: #E91E63; }
        .riwayat-table .soapie-content {
            display: block;
            margin-left: 35px;
            margin-top: 2px;
            color: #555;
            line-height: 1.4;
        }
        .no-soapie-notice {
            padding: 8px 12px;
            background: #f8f9fa;
            border-left: 3px solid #6c757d;
            color: #6c757d;
            font-style: italic;
            font-size: 12px;
        }
    </style>';
    
    $html .= '<table class="table table-bordered no-padding riwayat-table" width="100%">
                <thead>
                    <tr>
                        <th width="30">NO</th>
                        <th width="180">TANGGAL</th>
                        <th width="180">VITAL SIGNS</th>
                        <th>SOAPIE</th>
                        <th width="80">AKSI</th>
                    </tr>
                </thead>
                <tbody>';
    
    if(mysqli_num_rows($query_periksa) > 0){
        $nomor = 1;
        while($value = mysqli_fetch_array($query_periksa)){
            // Evaluasi vital signs
            $vital = evaluateVitalSigns($value);
            
            // Vital Signs dengan format rapi dan badge
            $vitalSigns = '
                <div style="line-height: 1.8;">
                    <div><span class="vital-label">Tensi</span>: '.$value['tensi'].' mmHg '.getBadgeVital($vital['tensi']['label'], $vital['tensi']['status']).'</div>
                    <div><span class="vital-label">Suhu</span>: '.$value['suhu_tubuh'].' °C '.getBadgeVital($vital['suhu']['label'], $vital['suhu']['status']).'</div>
                    <div><span class="vital-label">Nadi</span>: '.$value['nadi'].' x/menit '.getBadgeVital($vital['nadi']['label'], $vital['nadi']['status']).'</div>
                    <div><span class="vital-label">RR</span>: '.$value['respirasi'].' x/menit '.getBadgeVital($vital['rr']['label'], $vital['rr']['status']).'</div>
                    <div><span class="vital-label">TB/BB</span>: '.$value['tinggi'].'/'.$value['berat'].' cm/kg</div>
                    <div><span class="vital-label">SpO₂</span>: '.$value['spo2'].' % '.getBadgeVital($vital['spo2']['label'], $vital['spo2']['status']).'</div>
                    <div><span class="vital-label">GCS</span>: '.$value['gcs'].' '.getBadgeVital($vital['gcs']['label'], $vital['gcs']['status']).'</div>
                    <div><span class="vital-label">Kesadaran</span>: '.$value['kesadaran'].'</div>
                    <div><span class="vital-label">Alergi</span>: '.$value['alergi'].'</div>
                </div>
            ';
            
            // Cek apakah SOAPIE kosong
            if(isSOAPIEEmpty($value)) {
                $soapie = '<div class="no-soapie-notice">Belum ada catatan pemeriksaan medis (hanya vital signs)</div>';
            } else {
                // SOAPIE dengan spacing jelas dan preserve line breaks (FIXED)
                $soapie = '<div style="padding: 0;">';
                
                // S - Subjective
                if(!empty(trim($value['keluhan']))) {
                    $soapie .= '<div class="soapie-section s-section">
                        <span class="soapie-label s">S</span><strong>Subjective</strong>
                        <span class="soapie-content">'.formatSOAPIEContent($value['keluhan']).'</span>
                    </div>';
                }
                
                // O - Objective
                if(!empty(trim($value['pemeriksaan']))) {
                    $soapie .= '<div class="soapie-section o-section">
                        <span class="soapie-label o">O</span><strong>Objective</strong>
                        <span class="soapie-content">'.formatSOAPIEContent($value['pemeriksaan']).'</span>
                    </div>';
                }
                
                // A - Assessment
                if(!empty(trim($value['penilaian']))) {
                    $soapie .= '<div class="soapie-section a-section">
                        <span class="soapie-label a">A</span><strong>Assessment</strong>
                        <span class="soapie-content">'.formatSOAPIEContent($value['penilaian']).'</span>
                    </div>';
                }
                
                // P - Plan
                if(!empty(trim($value['rtl']))) {
                    $soapie .= '<div class="soapie-section p-section">
                        <span class="soapie-label p">P</span><strong>Plan</strong>
                        <span class="soapie-content">'.formatSOAPIEContent($value['rtl']).'</span>
                    </div>';
                }
                
                // I - Intervention
                if(!empty(trim($value['instruksi']))) {
                    $soapie .= '<div class="soapie-section i-section">
                        <span class="soapie-label i">I</span><strong>Intervention</strong>
                        <span class="soapie-content">'.formatSOAPIEContent($value['instruksi']).'</span>
                    </div>';
                }
                
                // E - Evaluation
                if(!empty(trim($value['evaluasi']))) {
                    $soapie .= '<div class="soapie-section e-section">
                        <span class="soapie-label e">E</span><strong>Evaluation</strong>
                        <span class="soapie-content">'.formatSOAPIEContent($value['evaluasi']).'</span>
                    </div>';
                }
                
                $soapie .= '</div>';
            }
            
            $html .= '<tr>
                        <td align="center" style="font-weight: 600;">'.$nomor.'</td>
                        <td style="white-space: nowrap;">
                            <strong style="color: #333;">'.konversiTanggal($value['tgl_perawatan']).'</strong><br>
                            <span style="color: #666;">'.$value['jam_rawat'].'</span><br>
                            <small style="color: #999;">'.($value['nm_bangsal'] ? $value['nm_bangsal'] : '-').'</small><br>
                            '.getBadgePetugas($value['nama_petugas']).'
                        </td>
                        <td>'.$vitalSigns.'</td>
                        <td>'.$soapie.'</td>
                        <td align="center">
                            <button type="button" class="btn btn-primary btn-xs waves-effect copy_soap"
                                data-suhu_tubuh="'.$value['suhu_tubuh'].'"
                                data-tensi="'.$value['tensi'].'"
                                data-nadi="'.$value['nadi'].'"
                                data-respirasi="'.$value['respirasi'].'"
                                data-tinggi="'.$value['tinggi'].'"
                                data-berat="'.$value['berat'].'"
                                data-gcs="'.$value['gcs'].'"
                                data-kesadaran="'.$value['kesadaran'].'"
                                data-alergi="'.$value['alergi'].'"
                                data-keluhan="'.$value['keluhan'].'"
                                data-pemeriksaan="'.$value['pemeriksaan'].'"
                                data-penilaian="'.$value['penilaian'].'"
                                data-rtl="'.$value['rtl'].'"
                                data-instruksi="'.$value['instruksi'].'"
                                data-evaluasi="'.$value['evaluasi'].'"
                                data-spo2="'.$value['spo2'].'"
                                title="Copy data ke form">
                                <i class="material-icons" style="font-size: 14px; vertical-align: middle;">content_copy</i>
                            </button>
                        </td>
                    </tr>';
            
            $nomor++;
        }
    } else {
        $html .= '<tr>
                    <td colspan="5" align="center">
                        <div class="alert alert-warning" style="margin: 10px;">
                            <strong>Info:</strong> Belum ada riwayat pemeriksaan untuk pasien ini.
                        </div>
                    </td>
                </tr>';
    }
    
    $html .= '</tbody></table>';
    
    echo json_encode([
        'status' => 'success',
        'html' => $html,
        'timestamp' => date('d/m/Y H:i:s')
    ]);
    exit();
}

// ===================================================
// ACTION: load_pemeriksaan (Sub-tab: Pemeriksaan saja)
// ===================================================
if($action == 'load_pemeriksaan'){
    $norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';
    
    if(empty($norawat)){
        echo '<div class="alert alert-danger">Parameter tidak valid</div>';
        exit();
    }
    
    // Ambil kd_dokter yang login
    $kd_dokter_login = '';
    if(isset($_SESSION['ses_dokter']) && !empty($_SESSION['ses_dokter'])) {
        $kd_dokter_login = encrypt_decrypt($_SESSION['ses_dokter'], 'd');
    }
    
    // Ambil NIP dari dokter yang login (JOIN dokter -> pegawai)
    $nip_login = '';
    if(!empty($kd_dokter_login)) {
        $query_nip = bukaquery("SELECT p.nik 
                                FROM dokter d 
                                INNER JOIN pegawai p ON d.kd_dokter = p.nik 
                                WHERE d.kd_dokter = '$kd_dokter_login'");
        if($row_nip = mysqli_fetch_array($query_nip)) {
            $nip_login = $row_nip['nik'];
        }
    }
    
    // Filter hanya untuk hari ini
    $tanggal_hari_ini = date('Y-m-d');
    
    $query = bukaquery("SELECT pr.*, pg.nama as nama_petugas, b.nm_bangsal
                        FROM pemeriksaan_ranap pr
                        LEFT JOIN pegawai pg ON pr.nip = pg.nik
                        LEFT JOIN kamar_inap ki ON pr.no_rawat = ki.no_rawat
                        LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
                        LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                        WHERE pr.no_rawat = '$norawat'
                        AND pr.tgl_perawatan = '$tanggal_hari_ini'
                        GROUP BY pr.no_rawat, pr.tgl_perawatan, pr.jam_rawat
                        ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC");
    
    if(mysqli_num_rows($query) == 0){
        echo '<div class="alert alert-info">Tidak ada data pemeriksaan.</div>';
        exit();
    }
    
    echo '<style>
        .vital-signs-cell {
            line-height: 1.8;
            font-size: 13px;
        }
        .vital-signs-cell .vital-item {
            display: block;
            margin-bottom: 4px;
            white-space: nowrap;
        }
        .vital-signs-cell strong {
            display: inline-block;
            min-width: 80px;
            color: #333;
        }
        .soapie-cell {
            line-height: 1.5;
            font-size: 13px;
            max-width: 600px;
        }
        .soapie-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 8px;
            border-radius: 6px;
            background: #fff;
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
            margin-top: 2px;
        }
        .soapie-label.s { background: #4CAF50; }
        .soapie-label.o { background: #2196F3; }
        .soapie-label.a { background: #FF9800; }
        .soapie-label.p { background: #9c27b0; }
        .soapie-label.i { background: #00bcd4; }
        .soapie-label.e { background: #e91e63; }
        .soapie-label-text {
            display: inline-block;
            font-weight: 600;
            font-size: 11px;
            color: #666;
            margin-left: 6px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .soapie-content {
            flex: 1;
            line-height: 1.6;
            color: #333;
            font-size: 13px;
        }
        .no-soapie-notice {
            padding: 8px 12px;
            background: #f8f9fa;
            border-left: 3px solid #6c757d;
            color: #6c757d;
            font-style: italic;
            font-size: 12px;
        }
    </style>';
    
    echo '<table class="table table-bordered table-striped">';
    echo '<thead><tr>
            <th width="40">No</th>
            <th width="250">Tanggal & Vital Signs</th>
            <th>SOAPIE</th>
            <th width="80">Aksi</th>
          </tr></thead><tbody>';
    
    $no = 1;
    while($row = mysqli_fetch_array($query)){
        // Evaluasi vital signs
        $vital = evaluateVitalSigns($row);
        
        // Format vital signs dengan line break yang rapi dan badge
        $vitalSigns = '
            <div class="vital-signs-cell">
                <span class="vital-item"><strong>Tensi</strong>: '.$row['tensi'].' mmHg '.getBadgeVital($vital['tensi']['label'], $vital['tensi']['status']).'</span>
                <span class="vital-item"><strong>Suhu</strong>: '.$row['suhu_tubuh'].' °C '.getBadgeVital($vital['suhu']['label'], $vital['suhu']['status']).'</span>
                <span class="vital-item"><strong>Nadi</strong>: '.$row['nadi'].' x/menit '.getBadgeVital($vital['nadi']['label'], $vital['nadi']['status']).'</span>
                <span class="vital-item"><strong>RR</strong>: '.$row['respirasi'].' x/menit '.getBadgeVital($vital['rr']['label'], $vital['rr']['status']).'</span>
                <span class="vital-item"><strong>TB/BB</strong>: '.$row['tinggi'].'/'.$row['berat'].' cm/kg</span>
                <span class="vital-item"><strong>SpO₂</strong>: '.$row['spo2'].' % '.getBadgeVital($vital['spo2']['label'], $vital['spo2']['status']).'</span>
                <span class="vital-item"><strong>GCS</strong>: '.$row['gcs'].' '.getBadgeVital($vital['gcs']['label'], $vital['gcs']['status']).'</span>
                <span class="vital-item"><strong>Kesadaran</strong>: '.$row['kesadaran'].'</span>
            </div>
        ';
        
        // Cek apakah SOAPIE kosong
        if(isSOAPIEEmpty($row)) {
            $soapie = '<div class="no-soapie-notice">Belum ada catatan pemeriksaan medis (hanya vital signs)</div>';
        } else {
            // Format SOAPIE dengan spacing yang jelas dan preserve line breaks (FIXED)
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
        
        echo '<tr>
                <td align="center">'.$no.'</td>
                <td>
                    <strong style="color: #333;">'.konversiTanggal($row['tgl_perawatan']).'</strong> <span style="color: #666;">'.$row['jam_rawat'].'</span><br>
                    <small style="color: #999;">'.($row['nm_bangsal'] ? $row['nm_bangsal'] : '-').'</small><br>
                    '.getBadgePetugas($row['nama_petugas']).'
                    <div style="margin-top:8px; padding-top:8px; border-top:1px solid #eee;">'.$vitalSigns.'</div>
                </td>
                <td>'.$soapie.'</td>
                <td align="center">
                    <div class="btn-group-vertical" style="gap: 3px;">';
        
        // Cek apakah NIP petugas sama dengan NIP login (boleh edit/hapus)
        $is_owner = ($row['nip'] == $nip_login);
        
        if($is_owner) {
            // Tombol Edit - Enabled
            echo '<button class="btn btn-xs btn-warning edit_pemeriksaan" 
                            data-no_rawat="'.$row['no_rawat'].'"
                            data-tgl_perawatan="'.$row['tgl_perawatan'].'"
                            data-jam_rawat="'.$row['jam_rawat'].'"
                            data-suhu_tubuh="'.$row['suhu_tubuh'].'"
                            data-tensi="'.$row['tensi'].'"
                            data-nadi="'.$row['nadi'].'"
                            data-respirasi="'.$row['respirasi'].'"
                            data-tinggi="'.$row['tinggi'].'"
                            data-berat="'.$row['berat'].'"
                            data-gcs="'.$row['gcs'].'"
                            data-kesadaran="'.$row['kesadaran'].'"
                            data-alergi="'.$row['alergi'].'"
                            data-keluhan="'.htmlspecialchars($row['keluhan'], ENT_QUOTES).'"
                            data-pemeriksaan="'.htmlspecialchars($row['pemeriksaan'], ENT_QUOTES).'"
                            data-penilaian="'.htmlspecialchars($row['penilaian'], ENT_QUOTES).'"
                            data-rtl="'.htmlspecialchars($row['rtl'], ENT_QUOTES).'"
                            data-instruksi="'.htmlspecialchars($row['instruksi'], ENT_QUOTES).'"
                            data-evaluasi="'.htmlspecialchars($row['evaluasi'], ENT_QUOTES).'"
                            data-spo2="'.$row['spo2'].'"
                            title="Edit data pemeriksaan"
                            style="margin-bottom: 3px;">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</i>
                        </button>';
        } else {
            // Tombol Edit - Disabled
            echo '<button class="btn btn-xs btn-default" 
                            disabled
                            title="Tidak dapat mengedit - Data milik petugas lain"
                            style="margin-bottom: 3px; opacity: 0.5; cursor: not-allowed;">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</i>
                        </button>';
        }
        
        // Tombol Copy - Selalu aktif
        echo '<button class="btn btn-xs btn-primary copy_soap" 
                            data-suhu_tubuh="'.$row['suhu_tubuh'].'"
                            data-tensi="'.$row['tensi'].'"
                            data-nadi="'.$row['nadi'].'"
                            data-respirasi="'.$row['respirasi'].'"
                            data-tinggi="'.$row['tinggi'].'"
                            data-berat="'.$row['berat'].'"
                            data-gcs="'.$row['gcs'].'"
                            data-kesadaran="'.$row['kesadaran'].'"
                            data-alergi="'.$row['alergi'].'"
                            data-keluhan="'.$row['keluhan'].'"
                            data-pemeriksaan="'.$row['pemeriksaan'].'"
                            data-penilaian="'.$row['penilaian'].'"
                            data-rtl="'.$row['rtl'].'"
                            data-instruksi="'.$row['instruksi'].'"
                            data-evaluasi="'.$row['evaluasi'].'"
                            data-spo2="'.$row['spo2'].'"
                            title="Copy data ke form"
                            style="margin-bottom: 3px;">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">content_copy</i>
                        </button>';
        
        if($is_owner) {
            // Tombol Delete - Enabled
            echo '<button class="btn btn-xs btn-danger delete_pemeriksaan" 
                            data-no_rawat="'.$row['no_rawat'].'"
                            data-tgl_perawatan="'.$row['tgl_perawatan'].'"
                            data-jam_rawat="'.$row['jam_rawat'].'"
                            title="Hapus data pemeriksaan">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</i>
                        </button>';
        } else {
            // Tombol Delete - Disabled
            echo '<button class="btn btn-xs btn-default" 
                            disabled
                            title="Tidak dapat menghapus - Data milik petugas lain"
                            style="opacity: 0.5; cursor: not-allowed;">
                            <i class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</i>
                        </button>';
        }
        
        echo '</div>
                </td>
              </tr>';
        $no++;
    }
    
    echo '</tbody></table>';
    
    // ✅ INLINE SCRIPT UNTUK HANDLER EDIT, COPY, DELETE
    echo '
    <script>
    (function() {
        var $ = jQuery;

        // Helper: filter sentinel "-" supaya tidak dimasukkan ke input number
        function sv(v) { return (v === "-" || v == null) ? "" : v; }

        // ===== HANDLER TOMBOL COPY =====
        $(".copy_soap").off("click").on("click", function(e) {
            e.preventDefault();
            var btn = $(this);
            var data = btn.data();



            // Isi form TTV
            $("input[name=tensi]").val(sv(data.tensi));
            $("input[name=nadi]").val(sv(data.nadi));
            $("input[name=respiratory_rate]").val(sv(data.respirasi));
            $("input[name=suhu]").val(sv(data.suhu_tubuh));
            $("input[name=spo2]").val(sv(data.spo2));
            $("input[name=berat]").val(sv(data.berat));
            $("input[name=tinggi]").val(sv(data.tinggi));
            $("select[name=kesadaran]").val(data.kesadaran || "Compos Mentis");
            $("input[name=gcs]").val(sv(data.gcs));
            $("input[name=alergi]").val(data.alergi || "");

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

            // Update character count
            $(".soapie-textarea").each(function() {
                var charCount = $(this).val().length;
                $(this).closest(".soapie-card-body").find(".char-current").text(charCount);
            });

            // Scroll ke form
            $("html, body").animate({
                scrollTop: $("#formPemeriksaan").offset().top - 100
            }, 500);

            // Switch ke tab Pemeriksaan
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
        
        // ===== HANDLER TOMBOL EDIT =====
        $(".edit_pemeriksaan").off("click").on("click", function(e) {
            e.preventDefault();
            var btn = $(this);
            var data = btn.data();
            
            
            
            // Simpan data edit ke variabel global
            window.editPemeriksaanData = {
                no_rawat: data.no_rawat,
                tgl_perawatan: data.tgl_perawatan,
                jam_rawat: data.jam_rawat
            };
            
            // Isi form TTV
            $("input[name=tensi]").val(sv(data.tensi));
            $("input[name=nadi]").val(sv(data.nadi));
            $("input[name=respiratory_rate]").val(sv(data.respirasi));
            $("input[name=suhu]").val(sv(data.suhu_tubuh));
            $("input[name=spo2]").val(sv(data.spo2));
            $("input[name=berat]").val(sv(data.berat));
            $("input[name=tinggi]").val(sv(data.tinggi));
            $("select[name=kesadaran]").val(data.kesadaran || "Compos Mentis");
            $("input[name=gcs]").val(sv(data.gcs));
            $("input[name=alergi]").val(data.alergi || "");

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
            
            // Update character count
            $(".soapie-textarea").each(function() {
                var charCount = $(this).val().length;
                $(this).closest(".soapie-card-body").find(".char-current").text(charCount);
            });
            
            // Tampilkan badge EDIT MODE
            $("#editModeBadge").remove();
            var badge = $("<div id=editModeBadge style=\"background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white; padding: 10px 20px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 8px rgba(255, 152, 0, 0.4);\"><div><i class=material-icons style=\"vertical-align: middle; margin-right: 8px;\">edit</i><strong>MODE EDIT</strong> - Mengedit data: " + data.tgl_perawatan + " " + data.jam_rawat + "</div><button type=button class=\"btn btn-sm\" id=btnCancelEdit style=\"background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 5px; padding: 5px 15px;\"><i class=material-icons style=\"font-size: 14px; vertical-align: middle;\">close</i> Batal Edit</button></div>");
            $(".form-section:has(.form-section-title:contains(SOAPIE))").before(badge);
            
            // Handler tombol Batal Edit
            $("#btnCancelEdit").on("click", function() {
                window.editPemeriksaanData = null;
                $("#editModeBadge").remove();
                document.getElementById("formPemeriksaan").reset();
                $("#btnSimpanSOAPIE").html("<i class=material-icons style=\"vertical-align: middle; margin-right: 5px; font-size: 18px;\">save</i> Simpan Pemeriksaan");
            });
            
            // Update tombol simpan
            $("#btnSimpanSOAPIE").html("<i class=material-icons style=\"vertical-align: middle; margin-right: 5px; font-size: 18px;\">save</i> Update Pemeriksaan");
            
            // Scroll ke form
            $("html, body").animate({
                scrollTop: $("#formPemeriksaan").offset().top - 100
            }, 500);
            
            // Switch ke tab Pemeriksaan
            $("a[href=\"#tab_pemeriksaan\"]").tab("show");
            
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    icon: "info",
                    title: "Mode Edit Aktif",
                    text: "Data " + data.tgl_perawatan + " " + data.jam_rawat + " dimuat ke form",
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: "top-end"
                });
            }
        });
        
        // ===== HANDLER TOMBOL DELETE =====
        $(".delete_pemeriksaan").off("click").on("click", function(e) {
            e.preventDefault();
            var btn = $(this);
            var data = btn.data();
            
          
            
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    title: "Konfirmasi Hapus",
                    html: "Yakin ingin menghapus data pemeriksaan<br><strong>" + data.tgl_perawatan + " " + data.jam_rawat + "</strong>?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#f44336",
                    cancelButtonColor: "#999",
                    confirmButtonText: "Ya, Hapus!",
                    cancelButtonText: "Batal",
                    reverseButtons: true
                }).then(function(result) {
                    if (result.isConfirmed) {
                            $.ajax({
                            url: "pages/proses3.php",
                            type: "POST",
                            dataType: "json",
                            data: {
                                aksi: "hapus_pemeriksaan_ranap",
                                no_rawat: data.no_rawat,
                                tgl_perawatan: data.tgl_perawatan,
                                jam_rawat: data.jam_rawat
                            },
                            success: function(response) {
                                
                                if (response.status === "success") {
                                    Swal.fire({
                                        icon: "success",
                                        title: "Berhasil!",
                                        text: response.message,
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(function() {
                                        if (typeof PemeriksaanModule !== "undefined" && typeof PemeriksaanModule.reloadPemeriksaan === "function") {
                                            PemeriksaanModule.reloadPemeriksaan();
                                        }
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Gagal!",
                                        text: response.message,
                                        confirmButtonText: "OK"
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                
                                Swal.fire({
                                    icon: "error",
                                    title: "Gagal!",
                                    text: "Terjadi kesalahan: " + error,
                                    confirmButtonText: "OK"
                                });
                            }
                        });
                    }
                });
            }
        });
        

    })();
    </script>
    ';
    
    exit();
}

// ===================================================
// DEFAULT: Action tidak dikenal
// ===================================================
echo json_encode(['status' => 'error', 'message' => 'Action tidak valid']);
exit();

?>

