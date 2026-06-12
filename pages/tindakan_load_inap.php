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
    // Query tindakan rawat inap yang sudah diinput - HANYA DOKTER YANG LOGIN
    $kd_dokter = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"], "d"), 20);
    $tanggal_hari_ini = date('Y-m-d'); // Untuk cek apakah boleh hapus
    
    // Query untuk rawat inap - tabel rawat_inap_dr JOIN jns_perawatan_inap
    $query = bukaquery("
        SELECT 
            r.kd_jenis_prw,
            r.tgl_perawatan,
            r.jam_rawat,
            r.material,
            r.bhp,
            r.tarif_tindakandr,
            r.kso,
            r.menejemen,
            r.biaya_rawat,
            j.nm_perawatan
        FROM rawat_inap_dr r
        INNER JOIN jns_perawatan_inap j ON r.kd_jenis_prw = j.kd_jenis_prw
        WHERE r.no_rawat = '$norawat'
        AND r.kd_dokter = '$kd_dokter'
        ORDER BY r.tgl_perawatan DESC, r.jam_rawat DESC
    ");
    
    if(mysqli_num_rows($query) > 0){
        $no = 1;
        while($tindakan = mysqli_fetch_array($query)){
            // Gunakan biaya_rawat dari tabel transaksi
            $biaya = $tindakan['biaya_rawat'] ?? 0;
            $tarif = number_format($biaya, 0, ',', '.');
            $tanggal = date('d/m/Y', strtotime($tindakan['tgl_perawatan']));
            
            // Hitung selisih hari dari hari ini
            $date_tindakan = new DateTime($tindakan['tgl_perawatan']);
            $date_today = new DateTime($tanggal_hari_ini);
            $diff = $date_today->diff($date_tindakan)->days;
            $is_past = ($tindakan['tgl_perawatan'] < $tanggal_hari_ini);
            
            // Warna baris berdasarkan selisih hari (5 warna)
            if($tindakan['tgl_perawatan'] === $tanggal_hari_ini){
                // Hari ini - Hijau muda
                $row_color = 'background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 4px solid #28a745;';
            } elseif($diff == 1 && $is_past){
                // Kemarin - Biru muda
                $row_color = 'background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); border-left: 4px solid #17a2b8;';
            } elseif($diff == 2 && $is_past){
                // 2 hari lalu - Kuning muda
                $row_color = 'background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); border-left: 4px solid #ffc107;';
            } elseif($diff == 3 && $is_past){
                // 3 hari lalu - Orange muda
                $row_color = 'background: linear-gradient(135deg, #ffe5d0 0%, #ffd6b8 100%); border-left: 4px solid #fd7e14;';
            } else {
                // 4 hari lalu & seterusnya - Abu-abu muda
                $row_color = 'background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%); border-left: 4px solid #6c757d;';
            }
            
            // Cek apakah tanggal tindakan = hari ini (boleh hapus)
            $is_hari_ini = ($tindakan['tgl_perawatan'] === $tanggal_hari_ini);
            
            // Tombol hapus: aktif jika hari ini, disabled jika bukan hari ini
            if($is_hari_ini){
                $btn_hapus = "<button class='btn btn-danger btn-sm btn-hapus-tindakan-inap'
                                data-kode='{$tindakan['kd_jenis_prw']}'
                                data-tanggal='{$tindakan['tgl_perawatan']}'
                                data-jam='{$tindakan['jam_rawat']}'
                                data-norawat='{$norawat}'
                                style='padding: 5px 12px; border-radius: 6px; font-size: 12px;'>
                            <i class='material-icons' style='font-size: 16px; vertical-align: middle;'>delete</i>
                        </button>";
            } else {
                $btn_hapus = "<button class='btn btn-secondary btn-sm' disabled
                                style='padding: 5px 12px; border-radius: 6px; font-size: 12px; opacity: 0.5; cursor: not-allowed;'
                                title='Hanya dapat dihapus pada hari yang sama'>
                            <i class='material-icons' style='font-size: 16px; vertical-align: middle;'>delete</i>
                        </button>";
            }
            
            echo "<tr style='{$row_color}'>
                    <td align='center'>{$no}</td>
                    <td><strong style='color: #11998e;'>{$tindakan['kd_jenis_prw']}</strong></td>
                    <td>{$tindakan['nm_perawatan']}</td>
                    <td align='center'>{$tanggal}</td>
                    <td align='center'>{$tindakan['jam_rawat']}</td>
                    <td align='right'>
                        <span style='color: #27ae60; font-weight: 600;'>Rp {$tarif}</span>
                    </td>
                    <td align='center'>
                        {$btn_hapus}
                    </td>
                  </tr>";
            $no++;
        }
    } else {
        echo "<tr>
                <td colspan='7' align='center' style='padding: 30px; color: #999;'>
                    <i class='material-icons' style='font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;'>inbox</i>
                    <em>Belum ada tindakan rawat inap yang diinput</em>
                </td>
              </tr>";
    }
} elseif($action === 'get_tindakan_inap'){
    // API untuk ambil daftar jenis perawatan inap (untuk dropdown/autocomplete)
    header('Content-Type: application/json');
    
    $search = isset($_GET['search']) ? validTeks4($_GET['search'], 100) : '';
    $kd_bangsal = isset($_GET['kd_bangsal']) ? validTeks4($_GET['kd_bangsal'], 5) : '';
    $kelas = isset($_GET['kelas']) ? validTeks4($_GET['kelas'], 10) : '';
    
    $where = "WHERE j.status = '1'";
    
    if(!empty($search)){
        $where .= " AND (j.kd_jenis_prw LIKE '%$search%' OR j.nm_perawatan LIKE '%$search%')";
    }
    
    if(!empty($kd_bangsal)){
        $where .= " AND j.kd_bangsal = '$kd_bangsal'";
    }
    
    if(!empty($kelas)){
        $where .= " AND j.kelas = '$kelas'";
    }
    
    $query = bukaquery("
        SELECT 
            j.kd_jenis_prw,
            j.nm_perawatan,
            j.kd_kategori,
            j.material,
            j.bhp,
            j.tarif_tindakandr,
            j.tarif_tindakanpr,
            j.kso,
            j.menejemen,
            j.total_byrdr,
            j.total_byrpr,
            j.total_byrdrpr,
            j.kd_pj,
            j.kd_bangsal,
            j.kelas
        FROM jns_perawatan_inap j
        $where
        ORDER BY j.nm_perawatan ASC
        LIMIT 100
    ");
    
    $data = [];
    while($row = mysqli_fetch_assoc($query)){
        $data[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit();
    
} else {
    echo "<tr><td colspan='7' class='text-center'>Invalid request</td></tr>";
}
?>
