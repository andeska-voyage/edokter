<?php
session_start();
require_once('../conf/conf.php');

// Query ambil data template
$queryTemplate = bukaquery("SELECT * FROM template_laporan_operasi ORDER BY nama_operasi ASC");
$jumlahTemplate = mysqli_num_rows($queryTemplate);
?>

<style>
.template-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 12px;
    border-left: 4px solid #7c3aed;
    cursor: pointer;
    transition: all 0.3s ease;
}

.template-card:hover {
    background: #f3f4f6;
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(124, 58, 237, 0.2);
}

.template-title {
    font-size: 14px;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.template-preview {
    font-size: 12px;
    color: #666;
    line-height: 1.6;
    max-height: 80px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    white-space: pre-wrap;
}

.btn-pilih-template {
    background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
    color: white;
    border: none;
    padding: 6px 15px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
}

.btn-pilih-template:hover {
    transform: scale(1.05);
}

.search-template {
    margin-bottom: 20px;
}

.search-template input {
    border-radius: 25px;
    border: 2px solid #e5e7eb;
    padding: 10px 20px;
    font-size: 13px;
}

.search-template input:focus {
    border-color: #7c3aed;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
}

.template-empty {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.template-empty i {
    font-size: 64px;
    color: #d1d5db;
    margin-bottom: 15px;
}
</style>

<div class="modal-header" style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%); color: white; border-radius: 5px 5px 0 0;">
    <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 1;">
        <span>&times;</span>
    </button>
    <h4 class="modal-title" style="font-weight: 700;">
        <i class="material-icons" style="vertical-align: middle; font-size: 24px;">library_books</i>
        Template Laporan Operasi
    </h4>
</div>

<div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 20px;">
    
    <!-- Search Box -->
    <div class="search-template">
        <input type="text" id="searchTemplate" class="form-control" placeholder="🔍 Cari template operasi..." onkeyup="filterTemplate()">
    </div>
    
    <?php if($jumlahTemplate == 0): ?>
    <!-- Template Kosong -->
    <div class="template-empty">
        <i class="material-icons">inbox</i>
        <h4>Tidak Ada Template</h4>
        <p>Belum ada template laporan operasi yang tersedia</p>
    </div>
    
    <?php else: ?>
    <!-- List Template -->
    <div id="templateList">
        <?php while($template = mysqli_fetch_array($queryTemplate)): ?>
        <div class="template-card" data-nama="<?php echo strtolower($template['nama_operasi']); ?>" onclick="pilihTemplate(<?php echo htmlspecialchars(json_encode($template['laporan_operasi'])); ?>)">
            <div class="template-title">
                <i class="material-icons" style="font-size: 18px; color: #7c3aed;">description</i>
                <?php echo htmlspecialchars($template['nama_operasi']); ?>
            </div>
            <div class="template-preview">
                <?php echo htmlspecialchars($template['laporan_operasi']); ?>
            </div>
            <button type="button" class="btn-pilih-template" onclick="pilihTemplate(<?php echo htmlspecialchars(json_encode($template['laporan_operasi'])); ?>); event.stopPropagation();">
                <i class="material-icons" style="font-size: 12px; vertical-align: middle;">check</i>
                Pilih Template
            </button>
        </div>
        <?php endwhile; ?>
    </div>
    
    <!-- No Results Message -->
    <div id="noResults" style="display: none;" class="template-empty">
        <i class="material-icons">search_off</i>
        <h4>Template Tidak Ditemukan</h4>
        <p>Coba gunakan kata kunci lain</p>
    </div>
    <?php endif; ?>
    
</div>

<div class="modal-footer" style="border-top: 2px solid #e5e7eb;">
    <button type="button" class="btn btn-default" data-dismiss="modal" style="border-radius: 20px; padding: 8px 20px;">
        <i class="material-icons" style="font-size: 14px; vertical-align: middle;">close</i>
        Tutup
    </button>
</div>

<script>
// Fungsi filter template
function filterTemplate() {
    const searchValue = document.getElementById('searchTemplate').value.toLowerCase();
    const templateCards = document.querySelectorAll('.template-card');
    let visibleCount = 0;
    
    templateCards.forEach(card => {
        const namaOperasi = card.getAttribute('data-nama');
        if (namaOperasi.includes(searchValue)) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Show/hide no results message
    const noResults = document.getElementById('noResults');
    const templateList = document.getElementById('templateList');
    
    if (visibleCount === 0 && searchValue !== '') {
        if(noResults) noResults.style.display = 'block';
        if(templateList) templateList.style.display = 'none';
    } else {
        if(noResults) noResults.style.display = 'none';
        if(templateList) templateList.style.display = 'block';
    }
}
</script>