<?php
session_start();
require_once('../conf/conf.php');

// Validasi session
if(!isset($_SESSION["ses_dokter"])){
    echo "<tr><td colspan='7' class='text-center'>Session expired</td></tr>";
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$norawat = isset($_GET['norawat']) ? validTeks4($_GET['norawat'], 20) : '';

if($action === 'load_tindakan' && !empty($norawat)){
    // Query tindakan yang sudah diinput
    $kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"], "d"), 20);
    
    $query = bukaquery("
        SELECT 
            r.kd_jenis_prw,
            r.tgl_perawatan,
            r.jam_rawat,
            r.biaya_rawat,
            j.nm_perawatan
        FROM rawat_jl_dr r
        INNER JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw
        WHERE r.no_rawat = '$norawat'
        AND r.kd_dokter = '$kd_dokter'
        ORDER BY r.tgl_perawatan DESC, r.jam_rawat DESC
    ");
    
    if(mysqli_num_rows($query) > 0){
        $no = 1;
        while($tindakan = mysqli_fetch_array($query)){
            $tarif = number_format($tindakan['biaya_rawat'], 0, ',', '.');
            $tanggal = date('d/m/Y', strtotime($tindakan['tgl_perawatan']));
            
            echo "<tr>
                    <td align='center'>{$no}</td>
                    <td><strong style='color: #11998e;'>{$tindakan['kd_jenis_prw']}</strong></td>
                    <td>{$tindakan['nm_perawatan']}</td>
                    <td align='center'>{$tanggal}</td>
                    <td align='center'>{$tindakan['jam_rawat']}</td>
                    <td align='right'>
                        <span style='color: #27ae60; font-weight: 600;'>Rp {$tarif}</span>
                    </td>
                    <td align='center'>
                        <button class='btn btn-danger btn-sm btn-hapus-tindakan'
                                data-kode='{$tindakan['kd_jenis_prw']}'
                                data-tanggal='{$tindakan['tgl_perawatan']}'
                                data-jam='{$tindakan['jam_rawat']}'
                                data-norawat='{$norawat}'
                                style='padding: 5px 12px; border-radius: 6px; font-size: 12px;'>
                            <i class='material-icons' style='font-size: 16px; vertical-align: middle;'>delete</i>
                        </button>
                    </td>
                  </tr>";
            $no++;
        }
    } else {
        echo "<tr>
                <td colspan='7' align='center' style='padding: 30px; color: #999;'>
                    <i class='material-icons' style='font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;'>inbox</i>
                    <em>Belum ada tindakan yang diinput</em>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='7' class='text-center'>Invalid request</td></tr>";
}
?>