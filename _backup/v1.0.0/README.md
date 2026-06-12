# e-Dokter SIMRS Khanza

> Modul **e-Dokter** untuk Sistem Informasi Manajemen Rumah Sakit (SIMRS) Khanza — antarmuka khusus dokter untuk pelayanan rawat jalan dan rawat inap dengan dokumentasi klinis terstruktur (SOAPIE), peresepan elektronik, dan integrasi BPJS.

**Pengembang e-Dokter:** Alfian Nur Ihsan
**Pengembang SIMRS Khanza:** Windiarto Nugroho

[![PHP](https://img.shields.io/badge/PHP-7%2B-777BB4?style=flat-square&logo=php)]()
[![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=flat-square&logo=mysql)]()
[![License](https://img.shields.io/badge/License-MIT%20with%20Attribution-blue?style=flat-square)]()

---
## UPDATE MANUAL PADA conf.php tambahkan:
 require_once __DIR__ . '/app.php';

 dibawah settingan  iCare bpjs
## ✨ Fitur Utama

### 📋 Dokumentasi Klinis SOAPIE Terstruktur
Form SOAPIE (Subjective / Objective / Assessment / Plan / Intervention / Evaluation) dengan **template per poliklinik** (rawat jalan) dan **per spesialis** (rawat inap). Dokter cukup centang chip + isi titik-titik, tidak perlu mengetik ulang dari nol.

- Auto-load chip dari database saat edit data lama
- Pasang/lepas chip = sinkron otomatis ke `diagnosa_pasien` (ICD-10) & `prosedur_pasien` (ICD-9-CM) untuk klaim BPJS
- Catatan tambahan terpisah untuk teks bebas
- Template dapat dikustomisasi di [conf/template.php](conf/template.php) (ralan) & [conf/templateinap.php](conf/templateinap.php) (ranap)

### 💊 e-Resep
- Pencarian obat dengan harga real-time
- Iterasi resep & limit biaya per status pasien (BPJS/Umum) — atur di [conf/app.php](conf/app.php)
- Highlight item yang sedang dipilih
- Cetak resep langsung

### 🔬 Pemeriksaan Penunjang
- **Tindakan**: filter tarif berdasarkan bangsal, cara bayar, dan kelas (`set_tarif`)
- **Laboratorium**: integrasi dengan modul lab + filter cara bayar/kelas
- **Radiologi**: order radiologi + filter tarif

### 👥 Konsultasi Antar Tenaga Medis
- **Konsul Medik** (dokter ↔ dokter)
- **Konsul Perawat / SBAR** (perawat → dokter)
  - Format: Situation, Background, Assessment, Recommendation
  - Notifikasi konsul belum dijawab

### 📄 Resume Medis Otomatis
Tombol "GET DATA" mengambil data dari multi-sumber dengan fallback cerdas:
```
IGD → Awal Medis Poli → SOAPIE Dokter → Hasil Lab → Hasil Radiologi
```

### 🛡️ Owner-Based Permission
Edit & hapus data pemeriksaan hanya bisa dilakukan oleh dokter yang membuat data tersebut (cek NIP login vs NIP pencatat).

---

## 🚀 Instalasi

### Prasyarat
- **XAMPP** (Apache 2.4+ & MariaDB / MySQL 5.7+)
- **PHP 7.0** atau lebih tinggi
- **SIMRS Khanza** sudah terinstall (database `sik` / `khanza` / sesuai konfigurasi)
- Web browser modern (Chrome, Edge, Firefox)

### Langkah

1. **Clone / extract** ke direktori `htdocs`:
   ```bash
   cd C:/xampp/htdocs
   git clone <repo-url> edokter
   ```

2. **Konfigurasi database** — edit [conf/conf.php](conf/conf.php):
   ```php
   $host_db = 'localhost';
   $user_db = 'root';
   $pass_db = '';
   $nama_db = 'sik';  // sesuaikan dengan database Khanza Anda
   ```

3. **Konfigurasi fitur** (opsional) — edit [conf/app.php](conf/app.php):
   ```php
   define('FITUR_ITERASI_RESEP', true);
   define('FITUR_LIMIT_BIAYA_RESEP', true);
   define('LIMIT_BIAYA_RESEP', 150000);
   define('FITUR_LIMIT_BIAYA_RESEP_RANAP', false);
   define('LIMIT_BIAYA_RESEP_RANAP', 0);
   ```

4. **Akses melalui browser**:
   ```
   http://localhost/edokter/
   ```

5. **Login** menggunakan akun dokter dari sistem Khanza.

---

## 📁 Struktur Direktori

```
edokter/
├── conf/
│   ├── conf.php              # Konfigurasi database
│   ├── app.php               # Toggle fitur (resep, limit biaya)
│   ├── template.php          # Template SOAPIE per kd_poli
│   └── templateinap.php      # Template SOAPIE per kd_sps
├── pages/
│   ├── pemeriksaan.php       # Form SOAPIE rawat jalan
│   ├── pemeriksaaninap.php   # Form SOAPIE rawat inap
│   ├── eresep.js / eresep_inap.js
│   ├── konsulperawat*.php    # Modul SBAR
│   └── ...
├── js/                       # JavaScript modules
├── plugins/                  # Library pihak ketiga (CKEditor, dll)
└── README.md
```

---

## 🎨 Kustomisasi Template SOAPIE

Untuk menambah/ubah template per poliklinik, edit [conf/template.php](conf/template.php):

```php
'U0009' => [  // kode poli
    'subjective' => [
        'Demam sejak ...',
        'Batuk ...',
    ],
    'assessment' => [
        'ISPA',
        'Faringitis akut',
    ],
    // ...
],
```

Pola `...` di template otomatis menjadi input field yang bisa diisi dokter.

---

## 📜 Lisensi

Aplikasi ini didistribusikan **GRATIS** dengan **MIT License + Klausul Atribusi**.

### ✅ Yang BOLEH dilakukan

- ✓ Digunakan di rumah sakit / klinik mana saja, **tanpa biaya**
- ✓ Dimodifikasi sesuai kebutuhan instansi
- ✓ Disebarkan ulang ke pihak lain
- ✓ Digunakan untuk keperluan komersial (di RS swasta, dll)

### ❌ Yang DILARANG

- ✗ **Menghapus, mengganti, atau menyembunyikan watermark / atribusi developer** pada halaman *Tentang Aplikasi*, footer, atau bagian apa pun yang memuat nama pembuat
- ✗ Mengklaim aplikasi ini sebagai karya pribadi/tim Anda (re-branding tanpa kredit)
- ✗ Menjual ulang aplikasi seolah-olah produk komersial Anda sendiri

> **Mengapa atribusi ini penting?**
> Aplikasi ini dibuat dengan banyak waktu dan tenaga, lalu dibagikan gratis untuk membantu komunitas rumah sakit Indonesia. Atribusi adalah **satu-satunya kompensasi** yang diminta. Dengan tetap mempertahankan kredit developer, Anda menghargai pekerjaan kreatif dan membantu developer lain mendapat exposure yang adil.

### Pelanggaran

Pelanggaran terhadap klausul atribusi merupakan pelanggaran lisensi dan dapat ditindaklanjuti secara hukum sesuai UU Hak Cipta No. 28 Tahun 2014.

Lihat [LICENSE](LICENSE) untuk teks lengkap.

---

## 🤝 Kontribusi

Pull request, bug report, dan saran fitur sangat diterima!

1. Fork repository ini
2. Buat branch fitur (`git checkout -b fitur-keren`)
3. Commit perubahan (`git commit -m 'Tambah fitur keren'`)
4. Push ke branch (`git push origin fitur-keren`)
5. Buka Pull Request

---

## 📞 Dukungan & Kontak

- **Issues / Bug**: buka issue di repository
- **Pertanyaan umum**: hubungi developer melalui kontak yang tertera di halaman *Tentang Aplikasi* di dalam aplikasi

---

## 👨‍💻 Kredit

### Pengembang e-Dokter
**Alfian Nur Ihsan**
Pembuat dan pemelihara modul e-Dokter untuk SIMRS Khanza.

### Pengembang SIMRS Khanza
**Windiarto Nugroho**
Pembuat sistem inti SIMRS Khanza yang menjadi dasar berjalannya modul e-Dokter ini.

---

## 🙏 Ucapan Terima Kasih

- **Windiarto Nugroho** & tim SIMRS Khanza — atas sistem inti yang luar biasa dan menjadi fondasi modul ini
- **Komunitas dokter & perawat** — atas masukan UX dan workflow klinis
- **Semua kontributor** yang telah membantu pengembangan modul ini

---

## 📋 Changelog

Lihat riwayat lengkap di [CHANGELOG.md](CHANGELOG.md) (jika tersedia) atau git log:
```bash
git log --oneline --decorate
```

---

<div align="center">

**Dibuat dengan ❤️ oleh Alfian Nur Ihsan**
**untuk komunitas rumah sakit Indonesia**

Berjalan di atas SIMRS Khanza karya **Windiarto Nugroho**

*Jika aplikasi ini membantu pekerjaan Anda, mohon hargai dengan tidak menghapus atribusi developer.*

</div>
