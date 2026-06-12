<?php 
    if(isset($_SESSION["ses_dokter"])){
        $halaman = isset($_GET["act"])?$_GET["act"]:NULL;
        if(!isset($_SESSION["nm_dokter"])){
            $queryuser = @bukaquery2("select d.nm_dokter, p.jk, p.photo 
                                    from dokter d 
                                    left join pegawai p on d.kd_dokter = p.nik 
                                    where d.kd_dokter='".validTeks4(encrypt_decrypt($_SESSION["ses_dokter"],"d"),20)."'");
            while($rsqueryuser = mysqli_fetch_array($queryuser)) {
                $_SESSION["nm_dokter"] = $rsqueryuser["nm_dokter"];
                $_SESSION["jk_dokter"] = $rsqueryuser["jk"];
                $_SESSION["photo_dokter"] = $rsqueryuser["photo"];
            }
        }
    }else{
        JSRedirect("index.php?act=Home");
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <title>E-Dokter <?=$_SESSION["nama_instansi"];?></title>
    <link rel="icon" href="<?= APP_BASE_URL ?>images/icon.ico" type="image/x-icon">
    
    <link href="css/fonts.css" rel="stylesheet">
    
    <!-- Core CSS -->
    <link href="plugins/bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="plugins/node-waves/waves.css" rel="stylesheet" />
    <link href="plugins/animate-css/animate.css" rel="stylesheet" />
    <link href="plugins/jquery-datatable/skin/bootstrap/css/dataTables.bootstrap.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/edokter.css" rel="stylesheet">
    <link href="css/edokter_v2.css" rel="stylesheet">
    <link href="css/pagination.css" rel="stylesheet">
    <link href="css/homeuser.css" rel="stylesheet">
    <link href="css/dropdown-fix.css" rel="stylesheet">
    
    <!-- NOTIFICATION ANIMATION CSS -->
    <link href="css/notif-animation.css" rel="stylesheet">
    
    <!-- SweetAlert2 (Local) -->
    <link rel="stylesheet" href="plugins/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="css/custom-sweetalert.css">
    <link rel="stylesheet" href="plugins/fontawesome/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-wrapper">
                    <img src="<?= APP_BASE_URL ?>images/logo-home.png" alt="E-Dokter Logo" class="sidebar-logo-img logo-full">
                    <img src="<?= APP_BASE_URL ?>images/icon.ico" alt="E-Dokter" class="sidebar-logo-icon logo-small">
                    <span class="sidebar-logo-subtitle">Hospital Integrated System</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <span class="nav-section-title">MENU UTAMA</span>
                    
                    <a href="index.php?act=HomeUser" class="nav-item <?=$halaman=="HomeUser"?"active":""?>">
                        <i class="material-icons">dashboard</i>
                        <span>Dashboard</span>
                    </a>
                    
                    <a href="index.php?act=Pasien" class="nav-item <?=$halaman=="Pasien"?"active":""?>">
                        <i class="material-icons">assignment_ind</i>
                        <span>Pasien Rawat Jalan</span>
                    </a>
                    
                    <a href="index.php?act=PasienInap" class="nav-item <?=$halaman=="PasienInap"?"active":""?>">
                        <i class="material-icons">hotel</i>
                        <span>Pasien Rawat Inap</span>
                    </a>

                    <a href="index.php?act=Operasi" class="nav-item <?=$halaman=="Operasi"?"active":""?>">
                        <i class="material-icons">medical_services</i>
                        <span>Operasi/VK</span>
                    </a>

                    <a href="index.php?act=KonsulMedik" class="nav-item <?=$halaman=="KonsulMedik"?"active":""?>">
                        <i class="material-icons">question_answer</i>
                        <span>Konsul Medik</span>
                    </a>

                    <a href="index.php?act=KonsulPerawat" class="nav-item <?=$halaman=="KonsulPerawat"?"active":""?>">
                        <i class="material-icons">groups</i>
                        <span>Konsul Perawat (SBAR)</span>
                    </a>

                    <a href="index.php?act=PerformanceReport" class="nav-item <?=$halaman=="PerformanceReport"?"active":""?>">
                        <i class="material-icons">bar_chart</i>
                        <span>Performance Report</span>
                    </a>
                    
                    <a href="index.php?act=ActivityReport" class="nav-item <?=$halaman=="ActivityReport"?"active":""?>">
                        <i class="material-icons">description</i>
                        <span>Activity Report</span>
                    </a>

                    <a href="index.php?act=TentangAplikasi" id="menuTentangAplikasi" class="nav-item <?=$halaman=="TentangAplikasi"?"active":""?>">
                        <i class="material-icons">info</i>
                        <span>Tentang Aplikasi</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <p>&copy; <?= date('Y') ?> <?=$_SESSION["nama_instansi"];?></p>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="material-icons">menu</i>
                    </button>
                    <div class="header-title">
                        <i class="material-icons">business</i>
                        <span><?=$_SESSION["nama_instansi"];?></span>
                    </div>
                </div>
                
                <div class="header-right">
                    <!-- Notification Bell dengan Dropdown -->
                    <div class="notification-wrapper">
                        <div class="notification-bell" id="notifBell">
                            <i class="material-icons">notifications_none</i>
                        </div>
                        
                        <div class="notification-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <h4><i class="material-icons">notifications</i> Notifikasi</h4>
                                <span class="notif-count" id="notifCount" style="display:none;">0 baru</span>
                            </div>
                            
                            <div class="notif-body" id="notifBody">
                                <div class="notif-loading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    <p>Memuat notifikasi...</p>
                                </div>
                            </div>
                            
                            <div class="notif-footer">
                                <span class="notif-footer-text">Notifikasi hanya tampil untuk hari ini</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="user-dropdown">
                        <?php
                        $photo_url = '';
                        $photo_value = isset($_SESSION["photo_dokter"]) ? $_SESSION["photo_dokter"] : '';
                        $is_photo_valid = !empty($photo_value) && $photo_value !== '-' && $photo_value !== 'null' && trim($photo_value) !== '';
                        
                        if($is_photo_valid) {
                            $photo_url = PHOTO_BASE_URL . $photo_value;
                        } else {
                            $photo_url = ($_SESSION["jk_dokter"] == "Pria") ? "images/male.png" : "images/female.png";
                        }
                        ?>
                        <img src="<?= $photo_url ?>" alt="User" class="user-avatar" 
                             onerror="this.src='<?= ($_SESSION["jk_dokter"] == "Pria") ? "images/male.png" : "images/female.png"; ?>'">
                        <span class="user-name"><?=$_SESSION["nm_dokter"];?></span>
                        <i class="material-icons dropdown-arrow">keyboard_arrow_down</i>
                        
                        <div class="dropdown-menu">
                            <a href="javascript:void(0)" class="dropdown-item" onclick="openChangePasswordModal()">
                                <i class="material-icons">lock</i>
                                <span>Ganti Password</span>
                            </a>
                            <a href="pages/logout.php" class="dropdown-item">
                                <i class="material-icons">logout</i>
                                <span>Log Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="page-content">
                <?php actionPages();?>
            </div>
        </main>

        <!-- Overlay untuk tutup sidebar di tablet/mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
    </div>
    
    <!-- ========================================== -->
    <!-- LOAD ALL SCRIPTS FIRST -->
    <!-- ========================================== -->
    <script src="plugins/jquery/jquery.min.js"></script>
    <script src="plugins/bootstrap/js/bootstrap.js"></script>
    <script src="plugins/jquery-slimscroll/jquery.slimscroll.js"></script>
    <script src="plugins/node-waves/waves.js"></script>
    <script src="plugins/jquery-countto/jquery.countTo.js"></script>
    <script src="plugins/chartjs/chart.umd.min.js"></script>
    <script src="plugins/jquery-datatable/jquery.dataTables.js"></script>
    <script src="plugins/jquery-datatable/skin/bootstrap/js/dataTables.bootstrap.js"></script>
    <script src="conf/validator.js"></script>
    <script src="plugins/sweetalert2/sweetalert2.all.min.js"></script>
    
    <!-- ✅ REAL-TIME NOTIFICATION SYSTEM -->
    <script src="js/notif-realtime.js"></script>
    
    <!-- ========================================== -->
    <!-- THEN INITIALIZE EVERYTHING -->
    <!-- ========================================== -->
    <script>
        // ============================================
        // VANILLA JS - SIDEBAR & UI (NO JQUERY)
        // ============================================
        (function() {
            const appContainer = document.querySelector('.app-container');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const breakpoint = 991;
            
            function checkScreenSize() {
                if (window.innerWidth <= breakpoint) {
                    appContainer.classList.remove('sidebar-collapsed');
                    appContainer.classList.remove('sidebar-expanded');
                } else {
                    appContainer.classList.remove('sidebar-expanded');
                    appContainer.classList.remove('sidebar-collapsed');
                }
            }
            
            checkScreenSize();
            window.addEventListener('resize', checkScreenSize);
            
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            sidebarToggle.addEventListener('click', function() {
                if (window.innerWidth <= breakpoint) {
                    appContainer.classList.toggle('sidebar-expanded');
                } else {
                    appContainer.classList.toggle('sidebar-collapsed');
                }
            });
            
            // Klik overlay = tutup sidebar
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    appContainer.classList.remove('sidebar-expanded');
                });
            }
            
            document.querySelector('.user-dropdown').addEventListener('click', function(e) {
                this.classList.toggle('active');
            });
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.user-dropdown')) {
                    document.querySelector('.user-dropdown').classList.remove('active');
                }
            });
        })();
        
        // ============================================
        // JQUERY SCRIPTS - WAIT FOR JQUERY TO LOAD
        // ============================================
        jQuery(document).ready(function($) {
            // Safety check
            if (typeof $ === 'undefined') {
                
                return;
            }
            
            // Count To Animation
            if (typeof $.fn.countTo !== 'undefined') {
                $('.count-to').countTo();
            }
            
            // ============================================
            // DROPDOWN PASIEN - Global Handler
            // ============================================
            $(document).on('click', '.dropdown-pasien-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $menu = $(this).siblings('.dropdown-pasien-menu');
                var isOpen = $menu.hasClass('show');
                
                $('.dropdown-pasien-menu').removeClass('show');
                $('.has-submenu').removeClass('active');
                
                if (!isOpen) {
                    $menu.addClass('show');
                }
            });
            
            $(document).on('click', '.has-submenu > a', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var $parent = $(this).parent('.has-submenu');
                var isActive = $parent.hasClass('active');
                
                $('.has-submenu').not($parent).removeClass('active');
                $parent.toggleClass('active', !isActive);
            });
            
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dropdown-pasien').length) {
                    $('.dropdown-pasien-menu').removeClass('show');
                    $('.has-submenu').removeClass('active');
                }
            });
            
            // ============================================
            // BACK TO TOP BUTTON
            // ============================================
            var $backToTop = $('#backToTop');
            
            $(window).scroll(function() {
                if ($(this).scrollTop() > 300) {
                    $backToTop.css('display', 'flex').addClass('show');
                } else {
                    $backToTop.css('display', 'none').removeClass('show');
                }
            });
            
            $backToTop.on('click', function(e) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: 0
                }, 600);
            });
            
            // ============================================
            // NOTIFICATION DROPDOWN
            // ============================================
            var $notifBell = $('#notifBell');
            var $notifDropdown = $('#notifDropdown');
            
            $notifBell.on('click', function(e) {
                e.stopPropagation();
                $notifDropdown.toggleClass('show');
            });
            
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.notification-wrapper').length) {
                    if($notifDropdown.hasClass('show')) {
                        $notifDropdown.removeClass('show');
                        
                        if(window.NotificationSystem) {
                            window.NotificationSystem.applyPendingUpdate();
                        }
                    }
                }
            });
            
            $notifDropdown.on('click', function(e) {
                if (!$(e.target).closest('a.notif-item').length) {
                    e.stopPropagation();
                }
            });
            
        }); // End jQuery ready
        
        // ============================================
        // GLOBAL FUNCTIONS (NO JQUERY DEPENDENCY)
        // ============================================
        function markAsRead(notifId, notifType) {
            if (typeof jQuery === 'undefined') return;
            
            notifType = notifType || 'lab';
            var $ = jQuery;
            
            var $item = $('[data-notif-id="' + notifId + '"]');
            $item.removeClass('notif-unread').addClass('notif-read');
            $item.find('.notif-unread-dot').remove();
            
            $.post('pages/notif_ajax.php', {
                action: 'mark_read',
                notif_id: notifId,
                notif_type: notifType
            });
            
            updateBadgeCount();
        }

        function updateBadgeCount() {
            if (typeof jQuery === 'undefined') return;
            
            var $ = jQuery;
            var unreadCount = $('.notif-item.notif-unread').length;
            var $badge = $('#notifBadge');
            var $count = $('#notifCount');
            
            if (unreadCount > 0) {
                var displayCount = unreadCount > 99 ? '99+' : unreadCount;
                if ($badge.length) {
                    $badge.text(displayCount).show();
                } else {
                    $('#notifBell').append('<span class="notification-badge" id="notifBadge">' + displayCount + '</span>');
                }
                if ($count.length) {
                    $count.text(unreadCount + ' baru').show();
                }
            } else {
                $badge.fadeOut();
                $count.fadeOut();
                $('.btn-mark-all').fadeOut();
            }
        }
    </script>
    
    <!-- Modal Ganti Password -->
    <div id="modalGantiPassword" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:12px; width:100%; max-width:420px; margin:20px; box-shadow:0 20px 60px rgba(0,0,0,0.3); overflow:hidden;">
            <div style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding:20px 24px; color:#fff;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h4 style="margin:0; font-size:16px; font-weight:600; display:flex; align-items:center; gap:8px;">
                        <i class="material-icons">lock</i> Ganti Password
                    </h4>
                    <button onclick="closeChangePasswordModal()" style="background:rgba(255,255,255,0.2); border:none; border-radius:6px; width:32px; height:32px; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                        <i class="material-icons" style="color:#fff; font-size:20px;">close</i>
                    </button>
                </div>
            </div>
            <div style="padding:24px;">
                <div id="cpwAlert" style="display:none; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:16px;"></div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; display:block;">Password Lama</label>
                    <div style="position:relative;">
                        <input type="password" id="cpwOld" style="width:100%; padding:10px 40px 10px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;" placeholder="Masukkan password lama">
                        <i class="material-icons cpw-eye" onclick="toggleCpwEye('cpwOld',this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94a3b8; font-size:20px;">visibility_off</i>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; display:block;">Password Baru</label>
                    <div style="position:relative;">
                        <input type="password" id="cpwNew" style="width:100%; padding:10px 40px 10px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;" placeholder="Masukkan password baru">
                        <i class="material-icons cpw-eye" onclick="toggleCpwEye('cpwNew',this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94a3b8; font-size:20px;">visibility_off</i>
                    </div>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-size:12px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px; display:block;">Konfirmasi Password Baru</label>
                    <div style="position:relative;">
                        <input type="password" id="cpwConfirm" style="width:100%; padding:10px 40px 10px 14px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none; box-sizing:border-box;" placeholder="Ulangi password baru">
                        <i class="material-icons cpw-eye" onclick="toggleCpwEye('cpwConfirm',this)" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); cursor:pointer; color:#94a3b8; font-size:20px;">visibility_off</i>
                    </div>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button onclick="closeChangePasswordModal()" style="padding:10px 20px; background:#e2e8f0; color:#1e293b; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">Batal</button>
                    <button onclick="submitChangePassword()" id="cpwSubmitBtn" style="padding:10px 20px; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <i class="material-icons" style="font-size:16px;">save</i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openChangePasswordModal() {
        document.getElementById('cpwOld').value = '';
        document.getElementById('cpwNew').value = '';
        document.getElementById('cpwConfirm').value = '';
        document.getElementById('cpwAlert').style.display = 'none';
        var modal = document.getElementById('modalGantiPassword');
        modal.style.display = 'flex';
        setTimeout(function(){ document.getElementById('cpwOld').focus(); }, 100);
    }
    function closeChangePasswordModal() {
        document.getElementById('modalGantiPassword').style.display = 'none';
    }
    function toggleCpwEye(inputId, icon) {
        var inp = document.getElementById(inputId);
        if(inp.type === 'password') {
            inp.type = 'text';
            icon.textContent = 'visibility';
        } else {
            inp.type = 'password';
            icon.textContent = 'visibility_off';
        }
    }
    function submitChangePassword() {
        var oldPw = document.getElementById('cpwOld').value.trim();
        var newPw = document.getElementById('cpwNew').value.trim();
        var confirmPw = document.getElementById('cpwConfirm').value.trim();
        var alertBox = document.getElementById('cpwAlert');

        function showAlert(msg, type) {
            alertBox.textContent = msg;
            alertBox.style.display = 'block';
            alertBox.style.background = type === 'error' ? '#fee2e2' : '#dcfce7';
            alertBox.style.color = type === 'error' ? '#991b1b' : '#166534';
        }

        if(!oldPw || !newPw || !confirmPw) {
            showAlert('Semua field harus diisi', 'error');
            return;
        }
        if(newPw.length < 4) {
            showAlert('Password baru minimal 4 karakter', 'error');
            return;
        }
        if(newPw !== confirmPw) {
            showAlert('Konfirmasi password tidak cocok', 'error');
            return;
        }
        if(oldPw === newPw) {
            showAlert('Password baru harus berbeda dengan password lama', 'error');
            return;
        }

        var btn = document.getElementById('cpwSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="material-icons" style="font-size:16px;animation:spin 1s linear infinite;">autorenew</i> Menyimpan...';

        jQuery.ajax({
            url: 'pages/proses.php',
            type: 'POST',
            dataType: 'json',
            data: {
                aksi: 'ganti_password',
                old_password: oldPw,
                new_password: newPw
            },
            success: function(resp) {
                btn.disabled = false;
                btn.innerHTML = '<i class="material-icons" style="font-size:16px;">save</i> Simpan';
                if(resp.status === 'success') {
                    showAlert('Password berhasil diubah!', 'success');
                    document.getElementById('cpwOld').value = '';
                    document.getElementById('cpwNew').value = '';
                    document.getElementById('cpwConfirm').value = '';
                    setTimeout(function(){ closeChangePasswordModal(); }, 1500);
                } else {
                    showAlert(resp.message || 'Gagal mengubah password', 'error');
                }
            },
            error: function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="material-icons" style="font-size:16px;">save</i> Simpan';
                showAlert('Terjadi kesalahan server', 'error');
            }
        });
    }
    // Close modal on overlay click
    document.getElementById('modalGantiPassword').addEventListener('click', function(e) {
        if(e.target === this) closeChangePasswordModal();
    });
    </script>

    <!-- Back to Top Button -->
    <button id="backToTop" class="back-to-top">
        <i class="material-icons">arrow_upward</i>
    </button>   
</body>
</html>
