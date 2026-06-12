<?php
session_start();
require_once('../conf/conf.php');

// Validasi session
if (!isset($_SESSION["ses_dokter"])) {
    echo "<tr><td colspan='6' align='center'><em>Session expired</em></td></tr>";
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';

// ========================================
// LOAD TABEL DIAGNOSA
// ========================================
if ($action === 'load_diagnosa' && !empty($norawat)) {
    
    $query_diagnosa = bukaquery("SELECT diagnosa_pasien.*, penyakit.nm_penyakit 
                                FROM diagnosa_pasien 
                                LEFT JOIN penyakit ON diagnosa_pasien.kd_penyakit = penyakit.kd_penyakit
                                WHERE diagnosa_pasien.no_rawat = '$norawat' 
                                ORDER BY diagnosa_pasien.prioritas ASC");
    
    if (mysqli_num_rows($query_diagnosa) > 0) {
        $no = 1;
        while ($diag = mysqli_fetch_array($query_diagnosa)) {
            $prioritas_label = '';
            $badge_color = '';
            
            switch ($diag['prioritas']) {
                case 1: 
                    $prioritas_label = 'Primer'; 
                    $badge_color = 'background: #4CAF50; color: white;';
                    break;
                case 2: 
                    $prioritas_label = 'Sekunder'; 
                    $badge_color = 'background: #FF9800; color: white;';
                    break;
                case 3: 
                    $prioritas_label = 'Tersier'; 
                    $badge_color = 'background: #9E9E9E; color: white;';
                    break;
                case 4:
                    $prioritas_label = '4';
                    $badge_color = 'background: #2196F3; color: white;';
                    break;
                case 5:
                    $prioritas_label = '5';
                    $badge_color = 'background: #9C27B0; color: white;';
                    break;
                case 6:
                    $prioritas_label = '6';
                    $badge_color = 'background: #00BCD4; color: white;';
                    break;
                case 7:
                    $prioritas_label = '7';
                    $badge_color = 'background: #795548; color: white;';
                    break;
                case 8:
                    $prioritas_label = '8';
                    $badge_color = 'background: #607D8B; color: white;';
                    break;
                case 9:
                    $prioritas_label = '9';
                    $badge_color = 'background: #424242; color: white;';
                    break;
                default:
                    $prioritas_label = $diag['prioritas'];
                    $badge_color = 'background: #757575; color: white;';
                    break;
            }
            
            echo "<tr>
                    <td align='center' style='vertical-align: middle;'>" . $no . "</td>
                    <td align='center' style='vertical-align: middle;'>
                        <span style='display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 500; " . $badge_color . "'> 
                            " . $prioritas_label . "
                        </span>
                    </td>
                    <td style='vertical-align: middle;'>
                        <span style='font-weight: 600; color: #667eea; background: #f0f4ff; padding: 3px 10px; border-radius: 4px; font-size: 13px;'>
                            " . $diag['kd_penyakit'] . "
                        </span>
                    </td>
                    <td style='vertical-align: middle;'>" . $diag['nm_penyakit'] . "</td>
                    <td align='center' style='vertical-align: middle;'>
                        <span style='display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; background: #E3F2FD; color: #2196F3; font-weight: 500;'>
                            " . $diag['status'] . "
                        </span>
                    </td>
                    <td align='center' style='vertical-align: middle;'>
                        <button type='button' class='btn btn-danger btn-xs waves-effect btn-hapus-diagnosa' 
                                data-norawat='" . $diag['no_rawat'] . "' 
                                data-kode='" . $diag['kd_penyakit'] . "'
                                data-prioritas='" . $diag['prioritas'] . "'
                                style='border-radius: 6px; padding: 5px 10px;'>
                            <i class='material-icons' style='font-size: 18px;'>delete</i>
                        </button>
                    </td>
                  </tr>";
            $no++;
        }
    } else {
        echo "<tr>
                <td colspan='6' align='center' style='padding: 30px; color: #999;'>
                    <i class='material-icons' style='font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;'>inbox</i>
                    <em>Belum ada diagnosa</em>
                </td>
              </tr>";
    }
    
    exit();
}

// ========================================
// LOAD TABEL PROSEDUR
// ========================================
if ($action === 'load_prosedur' && !empty($norawat)) {
    
    $query_prosedur = bukaquery("SELECT prosedur_pasien.*, icd9.deskripsi_panjang 
                                FROM prosedur_pasien 
                                LEFT JOIN icd9 ON prosedur_pasien.kode = icd9.kode
                                WHERE prosedur_pasien.no_rawat = '$norawat' 
                                ORDER BY prosedur_pasien.prioritas ASC");
    
    if (mysqli_num_rows($query_prosedur) > 0) {
        $no = 1;
        while ($proc = mysqli_fetch_array($query_prosedur)) {
            echo "<tr>
                    <td align='center' style='vertical-align: middle;'>" . $no . "</td>
                    <td style='vertical-align: middle;'>
                        <span style='font-weight: 600; color: #f5576c; background: #fff0f3; padding: 3px 10px; border-radius: 4px; font-size: 13px;'>
                            " . $proc['kode'] . "
                        </span>
                    </td>
                    <td style='vertical-align: middle;'>" . $proc['deskripsi_panjang'] . "</td>
                    <td align='center' style='vertical-align: middle;'>
                        <span style='display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; background: #FFF3E0; color: #F57C00; font-weight: 500;'>
                            " . $proc['status'] . "
                        </span>
                    </td>
                    <td align='center' style='vertical-align: middle;'>
                        <button type='button' class='btn btn-danger btn-xs waves-effect btn-hapus-prosedur' 
                                data-norawat='" . $proc['no_rawat'] . "' 
                                data-kode='" . $proc['kode'] . "'
                                data-prioritas='" . $proc['prioritas'] . "'
                                style='border-radius: 6px; padding: 5px 10px;'>
                            <i class='material-icons' style='font-size: 18px;'>delete</i>
                        </button>
                    </td>
                  </tr>";
            $no++;
        }
    } else {
        echo "<tr>
                <td colspan='5' align='center' style='padding: 30px; color: #999;'>
                    <i class='material-icons' style='font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;'>inbox</i>
                    <em>Belum ada prosedur</em>
                </td>
              </tr>";
    }
    
    exit();
}

// Jika tidak ada action yang valid
echo "<tr><td colspan='6' align='center'><em>Invalid request</em></td></tr>";
?>