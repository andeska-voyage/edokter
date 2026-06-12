<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <title>Login - E-Dokter <?=$_SESSION["nama_instansi"];?></title>
    <link rel="icon" href="<?= APP_BASE_URL ?>images/icon.ico" type="image/x-icon">
    <!-- Local Fonts (Offline) -->
    <link href="css/fonts.css" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet" type="text/css" />
</head>

<body>
    <div class="login-container">
        <!-- Login Form Card -->
        <div class="login-card">
            <!-- Logo Header -->
            <div class="logo-header">
                <img src="<?= APP_BASE_URL ?>images/logo.png" alt="E-Dokter Logo" class="logo-img">
                <span class="logo-subtitle"><?=$_SESSION["nama_instansi"];?></span>
            </div>

            <div class="form-header">
                <h2>Selamat Datang</h2>
                <p>Masuk ke akun Anda untuk melanjutkan</p>
            </div>

            <form id="sigin" role="form" onsubmit="return validasiIsi();" method="post" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="TxtIsi1">Username</label>
                    <div class="input-wrapper">
                        <i class="material-icons">person_outline</i>
                        <input type="text" 
                               class="form-input" 
                               name="username" 
                               id="TxtIsi1"
                               placeholder="Masukkan username" 
                               pattern="[a-zA-Z0-9, ./@_]{1,65}" 
                               title="a-zA-Z0-9, ./@_ (Maksimal 65 karakter)" 
                               required 
                               onkeydown="setDefault(this, document.getElementById('MsgIsi1'));"
                               autocomplete="off" 
                               autofocus>
                    </div>
                    <span id="MsgIsi1" class="error-msg"></span>
                </div>

                <div class="form-group">
                    <label for="TxtIsi2">Password</label>
                    <div class="input-wrapper">
                        <i class="material-icons">lock_outline</i>
                        <input type="password" 
                               class="form-input" 
                               name="password" 
                               id="TxtIsi2"
                               placeholder="Masukkan password" 
                               pattern="[a-zA-Z0-9, ./@_]{1,65}" 
                               title="a-zA-Z0-9, ./@_ (Maksimal 65 karakter)" 
                               required 
                               onkeydown="setDefault(this, document.getElementById('MsgIsi2'));"
                               autocomplete="off">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="material-icons" id="eyeIcon">visibility_off</i>
                        </button>
                    </div>
                    <span id="MsgIsi2" class="error-msg"></span>
                </div>

                <div class="form-group">
                    <label for="TxtIsi3">Captcha</label>
                    <div class="captcha-wrapper">
                        <div class="captcha-image">
                            <img src="pages/captcha.php" alt="captcha" id="captchaImg" />
                            <button type="button" class="refresh-captcha" onclick="refreshCaptcha()">
                                <i class="material-icons">refresh</i>
                            </button>
                        </div>
                        <div class="input-wrapper captcha-input">
                            <input type="text" 
                                class="form-input" 
                                name="inputcaptcha" 
                                id="TxtIsi3"
                                placeholder="" 
                                pattern="[0-9]{1,6}" 
                                title="0-9 (Maksimal 6 karakter)" 
                                required 
                                onkeydown="setDefault(this, document.getElementById('MsgIsi3'));"
                                autocomplete="off">
                        </div>
                    </div>
                    <span id="MsgIsi3" class="error-msg"></span>
                </div>

                <button type="submit" name="BtnLogin" class="btn-login">
                    Masuk
                    <i class="material-icons">arrow_forward</i>
                </button>
            </form>

            <?php 
                $BtnLogin=isset($_POST['BtnLogin'])?$_POST['BtnLogin']:NULL;
                if (isset($BtnLogin)) {
                    $errorMsg = '';
                    if(@$_SESSION["Capcay"]==getOne2("select aes_encrypt(".validTeks4($_POST["inputcaptcha"],10).",'windi')")){
                        unset($_SESSION['Capcay']);
                        $username  = validTeks4($_POST['username'],20);
                        $password  = validTeks4($_POST['password'],40);
                        if(getOne2("select count(*) from user where user.id_user=AES_ENCRYPT('$username','nur') and user.password=AES_ENCRYPT('$password','windi')")>0){
                            if(getOne2("select count(*) from dokter where dokter.kd_dokter='$username'")>0){
                                $queryHakAkses = bukaquery2("SELECT * FROM user WHERE id_user=AES_ENCRYPT('$username','nur')");
                                $hakAkses = mysqli_fetch_assoc($queryHakAkses);
                                
                                $_SESSION["ses_dokter"] = encrypt_decrypt($username,"e");
                                
                                $_SESSION["hak_akses"] = array(
                                    'data_triase_igd'                         => $hakAkses['data_triase_igd'] ?? 'false',
                                    'penilaian_awal_medis_ralan'              => $hakAkses['penilaian_awal_medis_ralan'] ?? 'false',
                                    'hasil_pemeriksaan_usg'                   => $hakAkses['hasil_pemeriksaan_usg'] ?? 'false',
                                    'hasil_usg_gynecologi'                    => $hakAkses['hasil_usg_gynecologi'] ?? 'false',
                                    'hasil_usg_urologi'                       => $hakAkses['hasil_usg_urologi'] ?? 'false',
                                    'hasil_usg_neonatus'                      => $hakAkses['hasil_usg_neonatus'] ?? 'false',
                                    'hasil_endoskopi_faring_laring'           => $hakAkses['hasil_endoskopi_faring_laring'] ?? 'false',
                                    'hasil_endoskopi_hidung'                  => $hakAkses['hasil_endoskopi_hidung'] ?? 'false',
                                    'hasil_endoskopi_telinga'                 => $hakAkses['hasil_endoskopi_telinga'] ?? 'false',
                                    'hasil_pemeriksaan_ekg'                   => $hakAkses['hasil_pemeriksaan_ekg'] ?? 'false',
                                    'hasil_pemeriksaan_echo'                  => $hakAkses['hasil_pemeriksaan_echo'] ?? 'false',
                                    'hasil_pemeriksaan_echo_pediatrik'        => $hakAkses['hasil_pemeriksaan_echo_pediatrik'] ?? 'false',
                                    'hasil_pemeriksaan_slit_lamp'             => $hakAkses['hasil_pemeriksaan_slit_lamp'] ?? 'false',
                                    'hasil_pemeriksaan_oct'                   => $hakAkses['hasil_pemeriksaan_oct'] ?? 'false',
                                    'hasil_pemeriksaan_treadmill'             => $hakAkses['hasil_pemeriksaan_treadmill'] ?? 'false',
                                    'penilaian_pre_induksi'                   => $hakAkses['penilaian_pre_induksi'] ?? 'false',
                                    'penilaian_pre_operasi'                   => $hakAkses['penilaian_pre_operasi'] ?? 'false',
                                    'checklist_pre_operasi'                   => $hakAkses['checklist_pre_operasi'] ?? 'false',
                                    'penilaian_pre_anestesi'                  => $hakAkses['penilaian_pre_anestesi'] ?? 'false',
                                    'signin_sebelum_anestesi'                 => $hakAkses['signin_sebelum_anestesi'] ?? 'false',
                                    'checklist_kesiapan_anestesi'             => $hakAkses['checklist_kesiapan_anestesi'] ?? 'false',
                                    'timeout_sebelum_insisi'                  => $hakAkses['timeout_sebelum_insisi'] ?? 'false',
                                    'signout_sebelum_menutup_luka'            => $hakAkses['signout_sebelum_menutup_luka'] ?? 'false',
                                    'catatan_anestesi_sedasi'                 => $hakAkses['catatan_anestesi_sedasi'] ?? 'false',
                                    'skor_aldrette_pasca_anestesi'            => $hakAkses['skor_aldrette_pasca_anestesi'] ?? 'false',
                                    'skor_steward_pasca_anestesi'             => $hakAkses['skor_steward_pasca_anestesi'] ?? 'false',
                                    'skor_bromage_pasca_anestesi'             => $hakAkses['skor_bromage_pasca_anestesi'] ?? 'false',
                                    'catatan_pengkajian_paska_operasi'        => $hakAkses['catatan_pengkajian_paska_operasi'] ?? 'false',
                                    'checklist_kriteria_masuk_hcu'            => $hakAkses['checklist_kriteria_masuk_hcu'] ?? 'false',
                                    'checklist_kriteria_masuk_icu'            => $hakAkses['checklist_kriteria_masuk_icu'] ?? 'false',
                                    'kriteria_masuk_nicu'                     => $hakAkses['kriteria_masuk_nicu'] ?? 'false',
                                    'kriteria_masuk_picu'                     => $hakAkses['kriteria_masuk_picu'] ?? 'false',
                                    'checklist_kriteria_keluar_icu'           => $hakAkses['checklist_kriteria_keluar_icu'] ?? 'false',
                                    'checklist_kriteria_keluar_hcu'           => $hakAkses['checklist_kriteria_keluar_hcu'] ?? 'false',
                                    'kriteria_keluar_nicu'                    => $hakAkses['kriteria_keluar_nicu'] ?? 'false',
                                    'kriteria_keluar_picu'                    => $hakAkses['kriteria_keluar_picu'] ?? 'false',
                                    'uji_fungsi_kfr'                          => $hakAkses['uji_fungsi_kfr'] ?? 'false',
                                    'layanan_kedokteran_fisik_rehabilitasi'   => $hakAkses['layanan_kedokteran_fisik_rehabilitasi'] ?? 'false',
                                    'penilaian_awal_medis_ranap'              => $hakAkses['penilaian_awal_medis_ranap'] ?? 'false',
                                    'penilaian_awal_medis_ranap_kebidanan'    => $hakAkses['penilaian_awal_medis_ranap_kebidanan'] ?? 'false',
                                    'penilaian_awal_medis_ranap_neonatus'     => $hakAkses['penilaian_awal_medis_ranap_neonatus'] ?? 'false',
                                    'penilaian_awal_medis_ranap_jantung'      => $hakAkses['penilaian_awal_medis_ranap_jantung'] ?? 'false',
                                    'penilaian_bayi_baru_lahir'               => $hakAkses['penilaian_bayi_baru_lahir'] ?? 'false',
                                    'konsultasi_medik'                        => $hakAkses['konsultasi_medik'] ?? 'false',
                                    'penilaian_awal_medis_ralan_kebidanan'    => $hakAkses['penilaian_awal_medis_ralan_kebidanan'] ?? 'false',
                                    'penilaian_awal_medis_ralan_anak'         => $hakAkses['penilaian_awal_medis_ralan_anak'] ?? 'false',
                                    'penilaian_awal_medis_ralan_tht'          => $hakAkses['penilaian_awal_medis_ralan_tht'] ?? 'false',
                                    'penilaian_awal_medis_ralan_psikiatri'    => $hakAkses['penilaian_awal_medis_ralan_psikiatri'] ?? 'false',
                                    'penilaian_awal_medis_ralan_penyakit_dalam'=> $hakAkses['penilaian_awal_medis_ralan_penyakit_dalam'] ?? 'false',
                                    'penilaian_awal_medis_ralan_mata'         => $hakAkses['penilaian_awal_medis_ralan_mata'] ?? 'false',
                                    'penilaian_awal_medis_ralan_neurologi'    => $hakAkses['penilaian_awal_medis_ralan_neurologi'] ?? 'false',
                                    'penilaian_awal_medis_ralan_orthopedi'    => $hakAkses['penilaian_awal_medis_ralan_orthopedi'] ?? 'false',
                                    'penilaian_awal_medis_ralan_bedah'        => $hakAkses['penilaian_awal_medis_ralan_bedah'] ?? 'false',
                                    'penilaian_awal_medis_ralan_geriatri'     => $hakAkses['penilaian_awal_medis_ralan_geriatri'] ?? 'false',
                                    'penilaian_awal_medis_ralan_bedah_mulut'  => $hakAkses['penilaian_awal_medis_ralan_bedah_mulut'] ?? 'false',
                                    'penilaian_awal_medis_ralan_kulit_kelamin'=> $hakAkses['penilaian_awal_medis_ralan_kulit_kelamin'] ?? 'false',
                                    'penilaian_awal_medis_ralan_jantung'      => $hakAkses['penilaian_awal_medis_ralan_jantung'] ?? 'false',
                                    'penilaian_awal_medis_ralan_paru'         => $hakAkses['penilaian_awal_medis_ralan_paru'] ?? 'false',
                                    'penilaian_medis_ralan_rehab_medik'       => $hakAkses['penilaian_medis_ralan_rehab_medik'] ?? 'false',
                                    'penilaian_medis_hemodialisa'             => $hakAkses['penilaian_medis_hemodialisa'] ?? 'false',
                                    'hemodialisa'                             => $hakAkses['hemodialisa'] ?? 'false',
                                    'penilaian_mcu'                           => $hakAkses['penilaian_mcu'] ?? 'false',
                                    'penilaian_bayi_baru_lahir'               => $hakAkses['penilaian_bayi_baru_lahir'] ?? 'false',
                                    'surat_buta_warna'                        => $hakAkses['surat_buta_warna'] ?? 'false',
                                    'surat_keterangan_sehat'                  => $hakAkses['surat_keterangan_sehat'] ?? 'false',
                                    'surat_bebas_narkoba'                     => $hakAkses['surat_bebas_narkoba'] ?? 'false',
                                    'surat_bebas_tbc'                         => $hakAkses['surat_bebas_tbc'] ?? 'false',
                                    'surat_bebas_tato'                        => $hakAkses['surat_bebas_tato'] ?? 'false',
                                    'surat_sakit'                             => $hakAkses['surat_sakit'] ?? 'false',
                                    'surat_cuti_hamil'                        => $hakAkses['surat_cuti_hamil'] ?? 'false',
                                    'surat_keterangan_layak_terbang'          => $hakAkses['surat_keterangan_layak_terbang'] ?? 'false',
                                    'surat_keterangan_rawat_inap'             => $hakAkses['surat_keterangan_rawat_inap'] ?? 'false',
                                    'pasien_meninggal'                        => $hakAkses['pasien_meninggal'] ?? 'false',
                                    'penilaian_awal_medis_igd'                => $hakAkses['penilaian_awal_medis_igd'] ?? 'false'
                                );

                                $_SESSION['last_activity'] = time();
                                
                                exit(header("Location:index.php"));
                            } else {
                                $errorMsg = "Username/Password tidak valid!";
                            } 
                        } else {
                            $errorMsg = "Username/Password tidak valid!";
                        }
                    } else {
                        $errorMsg = "Captcha tidak sesuai!";
                    }
                    
                    if($errorMsg) {
                        echo '<div class="alert-error">
                                <i class="material-icons">error_outline</i>
                                <span>'.$errorMsg.'</span>
                              </div>';
                    }
                }
            ?>

            <div class="form-footer">
                <p>Butuh bantuan? <a href="#">Hubungi Admin</a></p>
            </div>
        </div>

        <!-- Footer -->
        <div class="page-footer">
            <p>&copy; <?= date('Y') ?> E-Dokter <?=$_SESSION["nama_instansi"];?>. Terintegrasi dengan <strong>SIMRS Khanza</strong></p>
        </div>
    </div>

    <script src="conf/validator.js" type="text/javascript"></script>
    <script>
        function togglePassword() {
            const passInput = document.getElementById('TxtIsi2');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.textContent = 'visibility';
            } else {
                passInput.type = 'password';
                eyeIcon.textContent = 'visibility_off';
            }
        }

        function refreshCaptcha() {
            document.getElementById('captchaImg').src = 'pages/captcha.php?' + Date.now();
        }
    </script>
</body>

</html>
