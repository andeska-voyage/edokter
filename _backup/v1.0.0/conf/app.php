<?php
/**
 * conf/app.php
 *
 * Konfigurasi fitur aplikasi e-Dokter (toggle ON/OFF + limit value).
 * Dipisah dari conf.php supaya dokter/admin RS bisa update fitur tanpa
 * menyentuh setting database connection.
 *
 * Pakai `defined()` check supaya aman kalau di-include lebih dari sekali.
 */

// ================================================================
// FITUR ITERASI RESEP (BPJS)
// ================================================================
// Set true untuk mengaktifkan, false untuk menonaktifkan
if (!defined('FITUR_ITERASI_RESEP')) {
    define('FITUR_ITERASI_RESEP', true);
}

// ================================================================
// FITUR TEMPLATE SOAPIE TERSTRUKTUR (CHIP) — RAWAT JALAN
// ================================================================
// Set true  → tampilkan form chip + template per kd_poli (dari conf/template.php)
// Set false → tampilkan textarea polos (dokter ketik manual seperti versi lama)
if (!defined('FITUR_TEMPLATE_RAJAL')) {
    define('FITUR_TEMPLATE_RAJAL', false);
}

// ================================================================
// FITUR TEMPLATE SOAPIE TERSTRUKTUR (CHIP) — RAWAT INAP
// ================================================================
// Set true  → tampilkan form chip + template per kd_sps (dari conf/templateinap.php)
// Set false → tampilkan textarea polos (dokter ketik manual seperti versi lama)
if (!defined('FITUR_TEMPLATE_RANAP')) {
    define('FITUR_TEMPLATE_RANAP', false);
}

// ================================================================
// LIMIT TOTAL BIAYA E-RESEP
// ================================================================

// --- RAWAT JALAN (RALAN) ---
// Set true untuk mengaktifkan peringatan limit biaya resep rawat jalan
// Set false untuk menonaktifkan (tidak ada warning)
if (!defined('FITUR_LIMIT_BIAYA_RESEP')) {
    define('FITUR_LIMIT_BIAYA_RESEP', true);
}
// Batas maksimum total biaya resep rawat jalan dalam Rupiah
// Jika grand total melebihi angka ini, akan muncul warning (tetap bisa disimpan)
// Contoh: 500000 = Rp 500.000
if (!defined('LIMIT_BIAYA_RESEP')) {
    define('LIMIT_BIAYA_RESEP', 500000);
}

// --- RAWAT INAP (RANAP) ---
// Set true untuk mengaktifkan peringatan limit biaya resep rawat inap
// Set false untuk menonaktifkan (tidak ada warning)
if (!defined('FITUR_LIMIT_BIAYA_RESEP_RANAP')) {
    define('FITUR_LIMIT_BIAYA_RESEP_RANAP', true);
}
// Batas maksimum total biaya resep rawat inap dalam Rupiah
// Jika grand total melebihi angka ini, akan muncul warning (tetap bisa disimpan)
// Contoh: 2000000 = Rp 2.000.000
if (!defined('LIMIT_BIAYA_RESEP_RANAP')) {
    define('LIMIT_BIAYA_RESEP_RANAP', 2000000);
}
