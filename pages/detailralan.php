<?php
session_start();
require_once "config.php"; // Sesuaikan dengan file config Anda

// Decrypt parameter
$encrypted_norawat = isset($_GET['rnw']) ? $_GET['rnw'] : '';
$no_rawat = '';

if(!empty($encrypted_norawat)) {
    $no_rawat = encrypt_decrypt(urldecode($encrypted_norawat), 'd');
}

if(empty($no_rawat)) {
    echo '<div class="alert alert-danger">No. Rawat tidak valid</div>';
    exit;
}

// Query data radiologi
$query = bukaquery("SELECT 
                        pr.tgl_periksa,
                        pr.jam,
                        pr.kd_jenis_prw,
                        jpr.nm_perawatan,
                        pr.dokter_perujuk,
                        d.nm_dokter,
                        pr.status
                    FROM permintaan_radiologi pr
                    LEFT JOIN jns_perawatan_radiologi jpr ON pr.kd_jenis_prw = jpr.kd_jenis_prw
                    LEFT JOIN dokter d ON pr.dokter_perujuk = d.kd_dokter
                    WHERE pr.no_rawat = '$no_rawat'
                    ORDER BY pr.tgl_periksa DESC, pr.jam DESC");

$no = 1;
?>

<div style="padding: 15px; background: white; border-radius: 8px;">
    <h4 style="margin: 0 0 15px 0; color: #667eea; display: flex; align-items: center;">
        <i class="material-icons" style="margin-right: 8px;">assignment</i>
        Pemeriksaan Radiologi
        <span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 10px;">
            No. Rawat: <?php echo $no_rawat; ?>
        </span>
    </h4>
    
    <div class="table-responsive">
        <table class="table table-bordered table-striped" style="margin: 0;">
            <thead style="background: #f5f5f5;">
                <tr>
                    <th style="width: 50px;">No.</th>
                    <th>Tanggal</th>
                    <th>Kode</th>
                    <th>Nama Pemeriksaan</th>
                    <th>Dokter Perujuk</th>
                    <th style="width: 120px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if(mysqli_num_rows($query) == 0) {
                    echo '<tr><td colspan="6" style="text-align: center; color: #999; padding: 30px;">
                            <i class="material-icons" style="font-size: 48px;">inbox</i><br>
                            Tidak ada data pemeriksaan radiologi
                          </td></tr>';
                } else {
                    while($row = mysqli_fetch_array($query)) {
                        $status_badge = '';
                        if($row['status'] == 'RANAP') {
                            $status_badge = '<span style="background: #9c27b0; color: white; padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 600;">RANAP</span>';
                        } else {
                            $status_badge = '<span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 600;">RALAN</span>';
                        }
                        
                        echo '<tr>
                                <td style="text-align: center;">'.$no.'</td>
                                <td>'.date('d/m/Y', strtotime($row['tgl_periksa'])).'<br><small style="color: #999;">'.$row['jam'].'</small></td>
                                <td><strong>'.$row['kd_jenis_prw'].'</strong></td>
                                <td>'.$row['nm_perawatan'].'</td>
                                <td>'.$row['nm_dokter'].'</td>
                                <td style="text-align: center;">'.$status_badge.'</td>
                              </tr>';
                        $no++;
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>