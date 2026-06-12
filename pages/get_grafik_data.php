<?php
session_start();
require_once('../conf/conf.php');

header('Content-Type: application/json');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit();
}

$norm = isset($_POST['norm']) ? validTeks4($_POST['norm'], 20) : '';
$items = isset($_POST['items']) ? $_POST['items'] : [];
$filterNoRawat = isset($_POST['filter_no_rawat']) ? validTeks4($_POST['filter_no_rawat'], 20) : '';

if(empty($norm)){
    echo json_encode(['success' => false, 'message' => 'Parameter NO RM tidak valid']);
    exit();
}

if(empty($items) || !is_array($items)){
    echo json_encode(['success' => false, 'message' => 'Pilih minimal 1 item']);
    exit();
}

// Batasi max 5 item
$items = array_slice($items, 0, 5);

// Build filter SQL untuk no_rawat
$noRawatFilter = "";
if(!empty($filterNoRawat)){
    $noRawatFilter = " AND dpl.no_rawat = '$filterNoRawat' ";
}

$datasets = [];

foreach($items as $id_template){
    $id_template = validTeks4($id_template, 10);
    
    // Query data untuk item ini
    $query = "
        SELECT 
            dpl.no_rawat,
            dpl.tgl_periksa,
            dpl.jam,
            dpl.nilai,
            dpl.nilai_rujukan,
            dpl.keterangan,
            tl.Pemeriksaan,
            tl.satuan
        FROM detail_periksa_lab dpl
        LEFT JOIN template_laboratorium tl ON dpl.id_template = tl.id_template
        LEFT JOIN periksa_lab pl ON dpl.no_rawat = pl.no_rawat 
            AND dpl.kd_jenis_prw = pl.kd_jenis_prw
            AND dpl.tgl_periksa = pl.tgl_periksa
            AND dpl.jam = pl.jam
        LEFT JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
        WHERE rp.no_rkm_medis = '$norm'
        AND dpl.id_template = '$id_template'
        AND dpl.nilai IS NOT NULL 
        AND dpl.nilai != ''
        AND dpl.nilai != '-'
        $noRawatFilter
        ORDER BY dpl.tgl_periksa ASC, dpl.jam ASC
    ";
    
    $result = bukaquery($query);
    
    $dataPoints = [];
    $labels = [];
    $nama_item = '';
    $satuan = '';
    
    while($row = mysqli_fetch_assoc($result)){
        // Convert nilai ke float (handle koma dan non-numeric)
        $nilai = str_replace(',', '.', $row['nilai']);
        
        // Extract numeric value (handle format seperti ">100" atau "<5")
        preg_match('/([0-9]+\.?[0-9]*)/', $nilai, $matches);
        if(!empty($matches[1])){
            $nilai_float = floatval($matches[1]);
        } else {
            continue; // Skip non-numeric
        }
        
        $tanggal = date('d/m/Y', strtotime($row['tgl_periksa']));
        $jam = substr($row['jam'], 0, 5);
        $label = $tanggal . ' ' . $jam;
        
        // Jika mode ALL, tambahkan info no_rawat di tooltip
        $labelDisplay = $label;
        if(empty($filterNoRawat)){
            $labelDisplay = $label . ' (' . substr($row['no_rawat'], -6) . ')';
        }
        
        $dataPoints[] = [
            'x' => $label,
            'y' => $nilai_float,
            'keterangan' => $row['keterangan'],
            'nilai_rujukan' => $row['nilai_rujukan'],
            'no_rawat' => $row['no_rawat'],
            'label_display' => $labelDisplay
        ];
        
        if(empty($nama_item)){
            $nama_item = $row['Pemeriksaan'];
            $satuan = $row['satuan'] ?? '';
        }
    }
    
    if(!empty($dataPoints)){
        $datasets[] = [
            'label' => $nama_item . ($satuan ? ' (' . $satuan . ')' : ''),
            'data' => $dataPoints,
            'id_template' => $id_template
        ];
    }
}

if(empty($datasets)){
    echo json_encode(['success' => false, 'message' => 'Tidak ada data untuk item yang dipilih']);
    exit();
}

// Return info filter
$filterInfo = empty($filterNoRawat) ? 'Semua No. Rawat' : 'No. Rawat: ' . $filterNoRawat;

echo json_encode([
    'success' => true,
    'datasets' => $datasets,
    'filter_info' => $filterInfo
]);