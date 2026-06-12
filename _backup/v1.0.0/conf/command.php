<?php
    function title(){
        $judul ="Aplikasi E-Dokter --)(*!!@#$%";
        $judul = preg_replace("[^A-Za-z0-9_\-\./,|]"," ",$judul);
        $judul = str_replace(array('.','-','/',',')," ",$judul);
        $judul = trim($judul);
        echo "$judul";	
    }

    function cekSessiAdmin() {
        if (isset($_SESSION['ses_dokter'])) {
            return true;
        } else {
            return false;
        }
     }

    function PasienAktif() {
        if (cekSessiAdmin()) {
            return $_SESSION['ses_dokter'];
        }
     }

    function isPengunjung() {
        if (cekSessiAdmin()) {
            return false;
        } else {
            return true;
        }
     }
    
    // ========================================
    // FUNGSI HAK AKSES MENU
    // ========================================
    
    /**
     * Cek hak akses menu dari session
     * @param string $menu_key - Key kolom di tabel user
     * @return boolean
     */
    function cekAksesMenu($menu_key) {
        if (!cekSessiAdmin()) return false;
        
        // Cek dari session hak_akses
        if (isset($_SESSION['hak_akses'][$menu_key])) {
            return ($_SESSION['hak_akses'][$menu_key] == 'true');
        }
        
        return false;
    }
    
    /**
     * Redirect jika tidak punya akses
     * @param string $menu_key
     */
    function proteksiAksesMenu($menu_key) {
        if (!cekAksesMenu($menu_key)) {
            echo "<script>
                    alert('Anda tidak memiliki akses ke menu ini!');
                    window.location.href='?act=Home';
                  </script>";
            exit;
        }
    }
    
    // ========================================
    // END FUNGSI HAK AKSES MENU
    // ========================================

    function formProtek() {
        $aksi=isset($_GET['act'])?$_GET['act']:NULL;
        if (!cekSessiAdmin()) {
            $form = array ('HomeUser','Pasien','Pemeriksaan','ResumeMedis');
            foreach ($form as $page) {
                if ($aksi==$page) {
                    echo "<META HTTP-EQUIV = 'Refresh' Content = '0; URL = ?act=Home'>";
                    exit;
                    break;
                }
            }
        }	
    }

    function actionPages() {
        $aksi=isset($_REQUEST['act'])?$_REQUEST['act']:NULL;
        formProtek();
        switch ($aksi) {
            case "Pasien":
                include_once("pages/listpasien.php"); 
                break;
                
            case "PasienInap":
                include_once("pages/listpasieninap.php"); 
                break;
                
            case "HomeUser":
                include_once("pages/listhome.php"); 
                break;
                
            case "Pemeriksaan":
                include_once("pages/pemeriksaan.php"); 
                break;
                
            case "Triaseigd":
                proteksiAksesMenu('data_triase_igd'); // ✅ Proteksi akses
                include_once("pages/triaseigd.php"); 
                break;
                
            case "Awalmedisigd":
                proteksiAksesMenu('penilaian_awal_medis_igd'); // ✅ Proteksi akses
                include_once("pages/awalmedisigd.php"); 
                break;
                
            case "Awalmedisumum":
                proteksiAksesMenu('penilaian_awal_medis_ralan'); // ✅ Proteksi akses
                include_once("pages/awalmedisumum.php"); 
                break;

            case "Awalmedisanak":
                proteksiAksesMenu('penilaian_awal_medis_ralan_anak'); // ✅ Proteksi akses
                include_once("pages/awalmedisanak.php"); 
                break;

            case "Awalmedistht":
                proteksiAksesMenu('penilaian_awal_medis_ralan_tht'); // ✅ Proteksi akses
                include_once("pages/awalmedistht.php"); 
                break;

            case "Awalmedisparu":
                proteksiAksesMenu('penilaian_awal_medis_ralan_paru'); // ✅ Proteksi akses
                include_once("pages/awalmedisparu.php"); 
                break;

            case "Awalmediskulitkelamin":
                proteksiAksesMenu('penilaian_awal_medis_ralan_kulit_kelamin'); // ✅ Proteksi akses
                include_once("pages/awalmediskulitkelamin.php"); 
                break;

            case "Awalmedispenyakitdalam":
                proteksiAksesMenu('penilaian_awal_medis_ralan_penyakit_dalam'); // ✅ Proteksi akses
                include_once("pages/awalmedispenyakitdalam.php"); 
                break;

            case "Awalmedismata":
                proteksiAksesMenu('penilaian_awal_medis_ralan_mata'); // ✅ Proteksi akses
                include_once("pages/awalmedismata.php"); 
                break;

            case "Awalmedisbedah":
                proteksiAksesMenu('penilaian_awal_medis_ralan_bedah'); // ✅ Proteksi akses
                include_once("pages/awalmedisbedah.php"); 
                break;

            case "Awalmedisbedahmulut":
                proteksiAksesMenu('penilaian_awal_medis_ralan_bedah_mulut'); // ✅ Proteksi akses
                include_once("pages/awalmedisbedahmulut.php"); 
                break;

            case "Awalmediskebidananralan":
                proteksiAksesMenu('penilaian_awal_medis_ralan_kebidanan'); // ✅ Proteksi akses
                include_once("pages/awalmediskebidananralan.php"); 
                break;

            case "Awalmedisorthopedi":
                proteksiAksesMenu('penilaian_awal_medis_ralan_orthopedi'); // ✅ Proteksi akses
                include_once("pages/awalmedisorthopedi.php"); 
                break;

            case "Awalmedisneurologi":
                proteksiAksesMenu('penilaian_awal_medis_ralan_neurologi'); // ✅ Proteksi akses
                include_once("pages/awalmedisneurologi.php"); 
                break;

            case "Awalmedisjantung":
                proteksiAksesMenu('penilaian_awal_medis_ralan_jantung'); // ✅ Proteksi akses
                include_once("pages/awalmedisjantung.php"); 
                break;

            case "Awalmedisjantunginap":
                proteksiAksesMenu('penilaian_awal_medis_ranap_jantung'); // ✅ Proteksi akses
                include_once("pages/awalmedisjantunginap.php"); 
                break;

            case "Awalmedisfisikrehabilitasi":
                proteksiAksesMenu('penilaian_medis_ralan_fisik_rehabilitasi'); // ✅ Proteksi akses
                include_once("pages/awalmedisfisikrehabilitasi.php"); 
                break;

            case "Penilaianbayibaru":
                proteksiAksesMenu('penilaian_bayi_baru_lahir'); // ✅ Proteksi akses
                include_once("pages/penilaianbayibarulahir.php"); 
                break;

            case "layanankedokteranfisik":
                proteksiAksesMenu('layanan_kedokteran_fisik_rehabilitasi'); // ✅ Proteksi akses
                include_once("pages/layanankedokteranfisik.php"); 
                break;

            case "Pemeriksaanusgkandungan":
                proteksiAksesMenu('hasil_pemeriksaan_usg'); // ✅ Proteksi akses
                include_once("pages/pemeriksaanusgkandungan.php"); 
                break;

            case "Pemeriksaanusggynecologi":
                proteksiAksesMenu('hasil_pemeriksaan_usg'); // ✅ Proteksi akses
                include_once("pages/pemeriksaanusggynecology.php"); 
                break;

            case "Pemeriksaanendoskopifaringlaring":
                proteksiAksesMenu('hasil_endoskopi_faring_laring'); // ✅ Proteksi akses
                include_once("pages/pemeriksaanendoskopifaringlaring.php"); 
                break;

            case "Pemeriksaanekg":
                proteksiAksesMenu('hasil_pemeriksaan_ekg'); // ✅ Proteksi akses
                include_once("pages/pemeriksaanekg.php"); 
                break;

            case "Pemeriksaanecho":
                proteksiAksesMenu('hasil_pemeriksaan_echo'); // ✅ Proteksi akses
                include_once("pages/pemeriksaanecho.php"); 
                break;
                
            case "Pemeriksaanechopediatrik":
                proteksiAksesMenu('hasil_pemeriksaan_echo_pediatrik'); // ✅ Proteksi akses
                include_once("pages/pemeriksaanechopediatrik.php"); 
                break;

            case "Pemeriksaanslitlamp":
                proteksiAksesMenu('hasil_pemeriksaan_slit_lamp'); // ✅ Proteksi akses
                include_once("pages/pemeriksaanslitlamp.php"); 
                break;

            case "Pemeriksaanoct":
                proteksiAksesMenu('hasil_pemeriksaan_oct'); // ✅ Proteksi akses
                include_once("pages/pemeriksaanoct.php"); 
                break;

            case "Pemeriksaantreadmill":
                proteksiAksesMenu('hasil_pemeriksaan_treadmill'); // ✅ Proteksi akses
                include_once("pages/pemeriksaantreadmill.php"); 
                break;

            case "PemeriksaanInap":
                include_once("pages/pemeriksaaninap.php"); 
                break;
                
            case "ResumeMedis":
                include_once("pages/resumemedis.php"); 
                break;
                
            case "ResumeMedisInap":
                include_once("pages/resumemedisinap.php"); 
                break;

            case "ClinicalPathway":
                include_once("pages/clinicalpathway.php"); 
                break;
                
            case "Awalmedisranap":
                include_once("pages/awalmedisranap.php"); 
                break;

            case "Awalmedisneonatus":
                include_once("pages/awalmedisneonatus.php"); 
                break;

            case "Obatpulang":
                include_once("pages/obatpulang.php"); 
                break;

            case "Awalmediskebidanan":
                include_once("pages/awalmediskebidanan.php"); 
                break;

            case "Operasi":
                include_once("pages/listoperasi.php"); 
                break;

            case "Penilaianpreanestesi":
                include_once("pages/penilaianpreanestesi.php"); 
                break;

            case "Penilaianpreinduksi":
                include_once("pages/penilaianpreinduksi.php"); 
                break;

            case "Penilaianpreoperasi":
                include_once("pages/penilaianpreoperasi.php"); 
                break;

            case "Checklistpreoperasi":
                include_once("pages/checklistpreoperasi.php"); 
                break;

            case "Signinsebelumanestesi":
                include_once("pages/signinsebelumanestesi.php"); 
                break;

            case "Timeoutsebeluminsisi":
                include_once("pages/timeoutsebeluminsisi.php"); 
                break;
        
            case "Checklistkesiapananestesi":
                include_once("pages/checklist_kesiapan_anestesi.php"); 
                break;

            case "Signoutsebelummenutupluka":
                include_once("pages/signoutsebelummenutupluka.php"); 
                break;

            case "Catatananestesisedasi":
                include_once("pages/catatananestesisedasi.php"); 
                break;

            case "Skoraldrettepascaanestesi":
                include_once("pages/skor_aldrette_pasca_anestesi.php"); 
                break;

            case "Skorstewardpascaanestesi":
                include_once("pages/skor_steward_pasca_anestesi.php"); 
                break;

            case "Skorbromagepascaanestesi":
                include_once("pages/skor_bromage_pasca_anestesi.php"); 
                break;

            case "Catatanpengkajianpaskaoperasi":
                include_once("pages/catatan_pengkajian_paska_operasi.php"); 
                break;

            case "Penandaanoperasi":
                include_once("pages/penandaanoperasi.php"); 
                break;

            case "Laporanoperasi":
                include_once("pages/laporan_operasi.php"); 
                break;

            case "Konsultasimedik":
                include_once("pages/konsulmedik.php");  
                break;

            case "KonsulMedik":
                include_once("pages/konsulmedikjawab.php"); 
                break;

            case "PerformanceReport":
                include_once("pages/performancereport.php"); 
                break;

            case "pasienmeninggal":
                include_once("pages/pasienmeninggal.php"); 
                break;

            case "suratbutawarna":
                include_once("pages/suratbutawarna.php"); 
                break;

            case "suratketerangansehat":
                include_once("pages/suratketerangansehat.php"); 
                break;     
            
            case "suratketeranganlayakterbang":
                include_once("pages/suratketeranganlayakterbang.php"); 
                break;  

            case "suratsakit":
                include_once("pages/suratsakit.php"); 
                break;  

            case "suratketeranganrawatinap":
                include_once("pages/suratketeranganrawatinap.php"); 
                break;  

            case "surathamil":
                include_once("pages/surathamil.php"); 
                break; 
                
            case "suratbebasnarkoba":
                include_once("pages/suratketeranganbebasnarkoba.php"); 
                break;   

            case "suratbebastbc":
                include_once("pages/suratketeranganbebastbc.php"); 
                break;   

            case "suratbebastato":
                include_once("pages/suratketeranganbebastato.php"); 
                break; 

            case "Checklistkriteriamasukicu":
                include_once("pages/checklistkriteriamasukicu.php"); 
                break;   

            case "kriteria_masuk_nicu":
                include_once("pages/kriteriamasuknicu.php"); 
                break;   

            case "kriteria_masuk_picu":
                include_once("pages/kriteriamasukpicu.php"); 
                break;                   

            case "Checklistkriteriamasukhcu":
                include_once("pages/checklistkriteriamasukhcu.php"); 
                break;
                
            case "Checklistkriteriakeluaricu":
                include_once("pages/checklistkriteriakeluaricu.php"); 
                break;

            case "Checklistkriteriakeluarhcu":
                include_once("pages/checklistkriteriakeluarhcu.php"); 
                break;

            case "kriteria_keluar_nicu":
                include_once("pages/kriteriakeluarnicu.php"); 
                break;   

            case "kriteria_keluar_picu":
                include_once("pages/kriteriakeluarpicu.php"); 
                break;  

            case "ActivityReport":
                include_once("pages/activityreport.php"); 
                break;                

            case "RiwayatPerawatan":
                include_once("pages/riwayatperawatan.php"); 
                break;

            case "Pemeriksaanriwayat":
                include_once("pages/pemeriksaanriwayat.php"); 
                break;

            case "Icare":
                include_once("pages/icare.php"); 
                break;

            case "TentangAplikasi":
                include_once("pages/tentangaplikasi.php"); 
                break;

            default:
                include_once("pages/listhome.php");
        }   
    }
 
?>