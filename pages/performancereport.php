<?php
/**
 * Performance Report
 * E-Dokter - Laporan Kinerja Dokter
 */
?>

<link rel="stylesheet" href="css/performancereport.css">
<script src="plugins/chartjs/Chart.bundle.min.js"></script>

<div class="performance-container">
    <!-- Header -->
    <div class="performance-header">
        <div class="performance-title">
            <i class="fas fa-chart-bar"></i>
            <div>
                <h1>Performance Report</h1>
                <p>Laporan Kinerja & Analitik Dokter</p>
            </div>
        </div>

        <div class="performance-filter">
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i></label>
                <select id="periodFilter">
                    <option value="today">Hari Ini</option>
                    <option value="week">Minggu Ini</option>
                    <option value="month" selected>Bulan Ini</option>
                    <option value="quarter">3 Bulan Terakhir</option>
                    <option value="year">Tahun Ini</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div class="filter-group custom-date" id="customDateRange" style="display:none;">
                <input type="date" id="startDate">
                <span>-</span>
                <input type="date" id="endDate">
            </div>
            <button class="btn-filter" id="btnFilter">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <!-- <button class="btn-export" id="btnExport">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button> -->
        </div>

        <div class="doctor-badge">
            <div class="doctor-avatar">DR</div>
            <div class="doctor-info-text">
                <div class="name">dr. Nama Dokter, Sp.OG</div>
                <div class="role">Spesialis Obstetri & Ginekologi</div>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon blue"><i class="fas fa-users"></i></div>
            <div class="kpi-content">
                <div class="kpi-value">
                    <span id="kpiTotalPasien">0</span>
                    <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> 0%</span>
                </div>
                <div class="kpi-label">Total Pasien</div>
                <div class="kpi-detail">
                    <span class="detail-item">Rajal: <strong id="kpiRajal">0</strong></span>
                    <span class="detail-separator">•</span>
                    <span class="detail-item">Ranap: <strong id="kpiRanap">0</strong></span>
                </div>
                <div class="kpi-sublabel">vs bulan lalu: <span id="kpiTotalPasienPrev">0</span></div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon green"><i class="fas fa-calendar-check"></i></div>
            <div class="kpi-content">
                <div class="kpi-value">
                    <span id="kpiRataRata">0</span>
                    <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> 0%</span>
                </div>
                <div class="kpi-label">Rata-rata Pasien/Hari</div>
                <div class="kpi-detail">
                    <span class="detail-item">Rajal: <strong id="kpiRataRajal">0</strong></span>
                    <span class="detail-separator">•</span>
                    <span class="detail-item">Ranap: <strong id="kpiRataRanap">0</strong></span>
                </div>
                <div class="kpi-sublabel">vs bulan lalu: <span id="kpiRataRataPrev">0</span></div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon orange"><i class="fas fa-procedures"></i></div>
            <div class="kpi-content">
                <div class="kpi-value">
                    <span id="kpiOperasi">0</span>
                    <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> 0%</span>
                </div>
                <div class="kpi-label">Total Operasi</div>
                <div class="kpi-sublabel">vs bulan lalu: <span id="kpiOperasiPrev">0</span></div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon purple"><i class="fas fa-flask"></i></div>
            <div class="kpi-content">
                <div class="kpi-value">
                    <span id="kpiPenunjang">0</span>
                    <span class="kpi-trend up"><i class="fas fa-arrow-up"></i> 0%</span>
                </div>
                <div class="kpi-label">Total Penunjang</div>
                <div class="kpi-detail">
                    <span class="detail-item">Lab: <strong id="kpiLab">0</strong></span>
                    <span class="detail-separator">•</span>
                    <span class="detail-item">Rad: <strong id="kpiRad">0</strong></span>
                </div>
                <div class="kpi-sublabel">vs bulan lalu: <span id="kpiPenunjangPrev">0</span></div>
            </div>
        </div>
    </div>
    <!-- Main Content Grid -->
    <div class="content-grid">
        <!-- Left Column -->
        <div class="left-column">

            <!-- ===== PROFIL DOKTER ===== -->
            <?php
                $kd_dokter_login = validTeks4(encrypt_decrypt($_SESSION["ses_dokter"],"d"),20);
                $qDokter = @bukaquery2("
                    SELECT d.*, p.photo, p.jk AS jk_peg, p.mulai_kerja, s.nm_sps
                    FROM dokter d
                    LEFT JOIN pegawai p ON d.kd_dokter = p.nik
                    LEFT JOIN spesialis s ON d.kd_sps = s.kd_sps
                    WHERE d.kd_dokter = '$kd_dokter_login'
                    LIMIT 1
                ");
                $rsDokter = $qDokter ? mysqli_fetch_assoc($qDokter) : null;

                $photo_value  = $rsDokter['photo'] ?? '';
                $jk_val       = $rsDokter['jk'] ?? ($rsDokter['jk_peg'] ?? '');
                $is_photo_ok  = !empty($photo_value) && $photo_value !== '-' && $photo_value !== 'null' && trim($photo_value) !== '';
                $foto_src     = $is_photo_ok ? PHOTO_BASE_URL . $photo_value
                                : (($jk_val==='L'||$jk_val==='Pria') ? 'images/male.png' : 'images/female.png');
                $foto_fallback = ($jk_val==='L'||$jk_val==='Pria') ? 'images/male.png' : 'images/female.png';

                if (!function_exists('fmtTgl')) {
                    function fmtTgl($t){ return ($t && $t!='0000-00-00') ? date('d M Y', strtotime($t)) : '-'; }
                    function fmtVal($v){ return (!empty($v) && $v!=='-') ? htmlspecialchars($v) : '-'; }
                    function hitungMasaKerja($mulai) {
                        if (empty($mulai) || $mulai === '0000-00-00') return '-';
                        $start = new DateTime($mulai);
                        $now   = new DateTime();
                        $diff  = $start->diff($now);
                        $parts = [];
                        if ($diff->y > 0) $parts[] = $diff->y . ' Tahun';
                        if ($diff->m > 0) $parts[] = $diff->m . ' Bulan';
                        if ($diff->d > 0) $parts[] = $diff->d . ' Hari';
                        return !empty($parts) ? implode(' ', $parts) : '< 1 Hari';
                    }
                }
                $masa_kerja_str   = hitungMasaKerja($rsDokter['mulai_kerja'] ?? '');
                $mulai_kerja_fmt  = fmtTgl($rsDokter['mulai_kerja'] ?? '');
                $jkMap = ['L'=>'Laki-laki','P'=>'Perempuan','Pria'=>'Laki-laki','Wanita'=>'Perempuan'];
                $jkTampil = isset($jkMap[$rsDokter['jk']]) ? $jkMap[$rsDokter['jk']] : fmtVal($rsDokter['jk'] ?? '');
            ?>
            <div class="chart-card dpc-card">
                <!-- Foto kiri + Semua info kanan -->
                <div class="dpc-top">
                    <div class="dpc-photo-wrap">
                        <img src="<?= $foto_src ?>" alt="Foto Dokter"
                             onerror="this.src='<?= $foto_fallback ?>'">
                    </div>
                    <div class="dpc-identity">
                        <div class="dpc-name"><?= fmtVal($rsDokter['nm_dokter'] ?? '') ?></div>
                        <div class="dpc-sps"><?= fmtVal($rsDokter['nm_sps'] ?? '') ?></div>
                        <div class="dpc-kd"><i class="fas fa-id-badge"></i> <?= fmtVal($rsDokter['kd_dokter'] ?? '') ?></div>
                        <!-- Detail rows di dalam identity (di samping foto) -->
                        <div class="dpc-rows">
                            <div class="dpc-row"><span class="dpc-label"><i class="fas fa-venus-mars"></i> Jenis Kelamin</span><span class="dpc-value"><?= $jkTampil ?></span></div>
                            <div class="dpc-row"><span class="dpc-label"><i class="fas fa-birthday-cake"></i> Tgl Lahir</span><span class="dpc-value"><?= fmtTgl($rsDokter['tgl_lahir'] ?? '') ?></span></div>
                            <div class="dpc-row"><span class="dpc-label"><i class="fas fa-tint"></i> Gol. Darah</span><span class="dpc-value"><?= fmtVal($rsDokter['gol_drh'] ?? '') ?></span></div>
                            <div class="dpc-row"><span class="dpc-label"><i class="fas fa-phone"></i> No. Telp</span><span class="dpc-value"><?= fmtVal($rsDokter['no_telp'] ?? '') ?></span></div>
                            <div class="dpc-row"><span class="dpc-label"><i class="fas fa-envelope"></i> Email</span><span class="dpc-value"><?= fmtVal($rsDokter['email'] ?? '') ?></span></div>
                            <div class="dpc-row"><span class="dpc-label"><i class="fas fa-heart"></i> Status Nikah</span><span class="dpc-value"><?= fmtVal($rsDokter['stts_nikah'] ?? '') ?></span></div>
                            <div class="dpc-row"><span class="dpc-label"><i class="fas fa-pray"></i> Agama</span><span class="dpc-value"><?= fmtVal($rsDokter['agama'] ?? '') ?></span></div>
                            <div class="dpc-row"><span class="dpc-label"><i class="fas fa-calendar-plus"></i> Mulai Kerja</span><span class="dpc-value"><?= $mulai_kerja_fmt ?></span></div>
                            <div class="dpc-row dpc-masa-kerja"><span class="dpc-label"><i class="fas fa-briefcase"></i> Masa Kerja</span><span class="dpc-value dpc-masa-value"><?= $masa_kerja_str ?></span></div>
                            <div class="dpc-row dpc-row-full"><span class="dpc-label"><i class="fas fa-map-marker-alt"></i> Alamat</span><span class="dpc-value"><?= fmtVal($rsDokter['almt_tgl'] ?? '') ?></span></div>
                            <div class="dpc-row dpc-row-full"><span class="dpc-label"><i class="fas fa-graduation-cap"></i> Alumni</span><span class="dpc-value"><?= fmtVal($rsDokter['alumni'] ?? '') ?></span></div>
                            <div class="dpc-row dpc-row-full"><span class="dpc-label"><i class="fas fa-file-medical"></i> No. Ijin Praktek</span><span class="dpc-value"><?= fmtVal($rsDokter['no_ijn_praktek'] ?? '') ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /PROFIL DOKTER -->
            <!-- Trend Chart Pasien -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Tren Pasien Harian</h3>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="dot red"></span> IGD</span>
                        <span class="legend-item"><span class="dot blue"></span> Rajal</span>
                        <span class="legend-item"><span class="dot green"></span> Ranap</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <div class="trend-chart-wrapper">
                            <canvas id="trendPasienChart"></canvas>
                        </div>
                        <div class="trend-summary">
                            <div class="trend-summary-item">
                                <span class="trend-dot red"></span>
                                <span class="trend-label">IGD:</span>
                                <strong id="trendTotalIgd">0</strong>
                            </div>
                            <div class="trend-summary-item">
                                <span class="trend-dot blue"></span>
                                <span class="trend-label">Rajal:</span>
                                <strong id="trendTotalRajal">0</strong>
                            </div>
                            <div class="trend-summary-item">
                                <span class="trend-dot green"></span>
                                <span class="trend-label">Ranap:</span>
                                <strong id="trendTotalRanap">0</strong>
                            </div>
                            <div class="trend-summary-item total">
                                <span class="trend-label">Total:</span>
                                <strong id="trendGrandTotal">0</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tren Lama Pelayanan Poli -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-clock"></i> Tren Lama Pelayanan Poli</h3>
                    <div class="period-info">Rata-rata: <strong id="avgLamaPelayanan">0 menit</strong></div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <div class="lama-pelayanan-wrapper">
                            <canvas id="lamaPelayananChart"></canvas>
                        </div>
                        <div class="lama-pelayanan-summary">
                            <div class="lp-summary-item">
                                <span class="lp-label">Total Pasien:</span>
                                <strong id="lpTotalPasien">0</strong>
                            </div>
                            <div class="lp-summary-item">
                                <span class="lp-label">Tercepat:</span>
                                <strong id="lpTercepat">0 menit</strong>
                            </div>
                            <div class="lp-summary-item">
                                <span class="lp-label">Terlama:</span>
                                <strong id="lpTerlama">0 menit</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tren Rawat Inap -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-procedures"></i> Tren Rawat Inap</h3>
                    <div class="chart-legend" id="rawatInapLegend">
                        <!-- Dynamic legend from JS -->
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <div class="rawat-inap-wrapper">
                            <canvas id="rawatInapChart"></canvas>
                        </div>
                        <div class="rawat-inap-summary">
                            <div class="ri-summary-item">
                                <span class="ri-label">Total Pasien:</span>
                                <strong id="riTotalPasien">0</strong>
                            </div>
                            <div class="ri-summary-item">
                                <span class="ri-label">Bangsal Terbanyak:</span>
                                <strong id="riBangsalTerbanyak">-</strong>
                            </div>
                            <div class="ri-summary-item">
                                <span class="ri-label">Masih Dirawat:</span>
                                <strong id="riMasihDirawat">0</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Heatmap Jam Kerja -->
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-th"></i> Heatmap Aktivitas</h3>
                    <div class="heatmap-legend">
                        <span class="heat-low"></span>
                        <span class="heat-text">Rendah</span>
                        <span class="heat-high"></span>
                        <span class="heat-text">Tinggi</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="heatmap-container">
                        <div class="heatmap-grid">
                            <div class="heatmap-row">
                                <span class="heatmap-day">Sen</span>
                                <div class="heatmap-cell heat-0"></div>
                                <div class="heatmap-cell heat-1"></div>
                                <div class="heatmap-cell heat-3"></div>
                                <div class="heatmap-cell heat-4"></div>
                                <div class="heatmap-cell heat-5"></div>
                                <div class="heatmap-cell heat-4"></div>
                                <div class="heatmap-cell heat-2"></div>
                                <div class="heatmap-cell heat-1"></div>
                            </div>
                            <div class="heatmap-row">
                                <span class="heatmap-day">Sel</span>
                                <div class="heatmap-cell heat-0"></div>
                                <div class="heatmap-cell heat-2"></div>
                                <div class="heatmap-cell heat-4"></div>
                                <div class="heatmap-cell heat-5"></div>
                                <div class="heatmap-cell heat-4"></div>
                                <div class="heatmap-cell heat-3"></div>
                                <div class="heatmap-cell heat-2"></div>
                                <div class="heatmap-cell heat-0"></div>
                            </div>
                            <div class="heatmap-row">
                                <span class="heatmap-day">Rab</span>
                                <div class="heatmap-cell heat-1"></div>
                                <div class="heatmap-cell heat-2"></div>
                                <div class="heatmap-cell heat-3"></div>
                                <div class="heatmap-cell heat-4"></div>
                                <div class="heatmap-cell heat-5"></div>
                                <div class="heatmap-cell heat-5"></div>
                                <div class="heatmap-cell heat-3"></div>
                                <div class="heatmap-cell heat-1"></div>
                            </div>
                            <div class="heatmap-row">
                                <span class="heatmap-day">Kam</span>
                                <div class="heatmap-cell heat-0"></div>
                                <div class="heatmap-cell heat-1"></div>
                                <div class="heatmap-cell heat-2"></div>
                                <div class="heatmap-cell heat-4"></div>
                                <div class="heatmap-cell heat-5"></div>
                                <div class="heatmap-cell heat-4"></div>
                                <div class="heatmap-cell heat-2"></div>
                                <div class="heatmap-cell heat-1"></div>
                            </div>
                            <div class="heatmap-row">
                                <span class="heatmap-day">Jum</span>
                                <div class="heatmap-cell heat-1"></div>
                                <div class="heatmap-cell heat-2"></div>
                                <div class="heatmap-cell heat-3"></div>
                                <div class="heatmap-cell heat-3"></div>
                                <div class="heatmap-cell heat-1"></div>
                                <div class="heatmap-cell heat-4"></div>
                                <div class="heatmap-cell heat-5"></div>
                                <div class="heatmap-cell heat-2"></div>
                            </div>
                            <div class="heatmap-row">
                                <span class="heatmap-day">Sab</span>
                                <div class="heatmap-cell heat-2"></div>
                                <div class="heatmap-cell heat-3"></div>
                                <div class="heatmap-cell heat-4"></div>
                                <div class="heatmap-cell heat-3"></div>
                                <div class="heatmap-cell heat-2"></div>
                                <div class="heatmap-cell heat-1"></div>
                                <div class="heatmap-cell heat-0"></div>
                                <div class="heatmap-cell heat-0"></div>
                            </div>
                            <div class="heatmap-row">
                                <span class="heatmap-day">Min</span>
                                <div class="heatmap-cell heat-0"></div>
                                <div class="heatmap-cell heat-0"></div>
                                <div class="heatmap-cell heat-1"></div>
                                <div class="heatmap-cell heat-1"></div>
                                <div class="heatmap-cell heat-0"></div>
                                <div class="heatmap-cell heat-0"></div>
                                <div class="heatmap-cell heat-0"></div>
                                <div class="heatmap-cell heat-0"></div>
                            </div>
                            <div class="heatmap-hours">
                                <span>07</span>
                                <span>09</span>
                                <span>11</span>
                                <span>13</span>
                                <span>15</span>
                                <span>17</span>
                                <span>19</span>
                                <span>21</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="right-column">
            <!-- Top 5 Obat Terbanyak -->
            <div class="summary-card">
                <div class="card-header">
                    <h3><i class="fas fa-pills"></i> Top 5 Obat / BMHP Terbanyak</h3>
                </div>
                <div class="card-body">
                    <div class="donut-chart-container">
                        <div class="donut-chart">
                            <svg viewBox="0 0 100 100" class="donut-svg" id="obatDonutSvg">
                                <circle cx="50" cy="50" r="40" fill="none" stroke="#e2e8f0" stroke-width="12"/>
                                <!-- Dynamic segments will be inserted here -->
                            </svg>
                            <div class="donut-center">
                                <span class="donut-total" id="obatDonutTotal">0</span>
                                <span class="donut-label">Total Qty</span>
                            </div>
                        </div>
                    </div>
                    <div class="breakdown-list" id="topObatList">
                        <div class="ranking-loading">
                            <i class="fas fa-spinner fa-spin"></i> Memuat data obat...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pendapatan Jasa Medis -->
            <div class="summary-card" id="pendapatanJasaCard" style="display: none;">
                <div class="card-header">
                    <h3><i class="fas fa-money-bill-wave"></i> Pendapatan Bruto Jasa Medis</h3>
                    <button type="button" class="btn-toggle-visibility" id="btnTogglePendapatan" title="Tampilkan/Sembunyikan Nominal">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
                <div class="card-body">
                    <div class="pendapatan-total-container">
                        <div class="pendapatan-total-value" id="pendapatanTotal">
                            <span class="nominal-hidden">Rp ••••••••</span>
                            <span class="nominal-value" style="display: none;">Rp 0</span>
                        </div>
                        <div class="pendapatan-total-label">Total Periode</div>
                    </div>
                    <div class="pendapatan-breakdown" id="pendapatanBreakdown">
                        <div class="ranking-loading">
                            <i class="fas fa-spinner fa-spin"></i> Memuat data...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Diagnosa -->
            <div class="summary-card">
                <div class="card-header">
                    <h3><i class="fas fa-stethoscope"></i> Top 5 Diagnosa</h3>
                </div>
                <div class="card-body">
                    <!-- Rajal Section -->
                    <div class="diagnosa-section">
                        <div class="diagnosa-section-header">
                            <span class="section-dot blue"></span>
                            <span class="section-title">Rawat Jalan</span>
                        </div>
                        <div class="ranking-list" id="topDiagnosaRajal">
                            <div class="ranking-loading">
                                <i class="fas fa-spinner fa-spin"></i> Memuat...
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ranap Section -->
                    <div class="diagnosa-section">
                        <div class="diagnosa-section-header">
                            <span class="section-dot green"></span>
                            <span class="section-title">Rawat Inap</span>
                        </div>
                        <div class="ranking-list" id="topDiagnosaRanap">
                            <div class="ranking-loading">
                                <i class="fas fa-spinner fa-spin"></i> Memuat...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Tindakan -->
            <div class="summary-card">
                <div class="card-header">
                    <h3><i class="fas fa-procedures"></i> Top 5 Tindakan</h3>
                </div>
                <div class="card-body">
                    <!-- Rajal Section -->
                    <div class="diagnosa-section">
                        <div class="diagnosa-section-header">
                            <span class="section-dot blue"></span>
                            <span class="section-title">Rawat Jalan</span>
                        </div>
                        <div class="ranking-list" id="topTindakanRajal">
                            <div class="ranking-loading">
                                <i class="fas fa-spinner fa-spin"></i> Memuat...
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ranap Section -->
                    <div class="diagnosa-section">
                        <div class="diagnosa-section-header">
                            <span class="section-dot green"></span>
                            <span class="section-title">Rawat Inap</span>
                        </div>
                        <div class="ranking-list" id="topTindakanRanap">
                            <div class="ranking-loading">
                                <i class="fas fa-spinner fa-spin"></i> Memuat...
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Perbandingan Periode -->
            <!-- <div class="summary-card">
                <div class="card-header">
                    <h3><i class="fas fa-balance-scale"></i> Perbandingan Periode</h3>
                </div>
                <div class="card-body">
                    <div class="comparison-grid">
                        <div class="comparison-item">
                            <div class="comparison-label">Pasien</div>
                            <div class="comparison-values">
                                <div class="comparison-current">
                                    <span class="value">248</span>
                                    <span class="period">Bln ini</span>
                                </div>
                                <div class="comparison-arrow up">
                                    <i class="fas fa-arrow-right"></i>
                                    <span>+12%</span>
                                </div>
                                <div class="comparison-previous">
                                    <span class="value">221</span>
                                    <span class="period">Bln lalu</span>
                                </div>
                            </div>
                        </div>
                        <div class="comparison-item">
                            <div class="comparison-label">Pendapatan</div>
                            <div class="comparison-values">
                                <div class="comparison-current">
                                    <span class="value">45.2Jt</span>
                                    <span class="period">Bln ini</span>
                                </div>
                                <div class="comparison-arrow up">
                                    <i class="fas fa-arrow-right"></i>
                                    <span>+15%</span>
                                </div>
                                <div class="comparison-previous">
                                    <span class="value">39.3Jt</span>
                                    <span class="period">Bln lalu</span>
                                </div>
                            </div>
                        </div>
                        <div class="comparison-item">
                            <div class="comparison-label">Tindakan</div>
                            <div class="comparison-values">
                                <div class="comparison-current">
                                    <span class="value">78</span>
                                    <span class="period">Bln ini</span>
                                </div>
                                <div class="comparison-arrow down">
                                    <i class="fas fa-arrow-right"></i>
                                    <span>-5%</span>
                                </div>
                                <div class="comparison-previous">
                                    <span class="value">82</span>
                                    <span class="period">Bln lalu</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->
        </div>
    </div>
</div>

<script src="js/performancereport.js"></script>
