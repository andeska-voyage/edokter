/**
 * resumemedis.js
 * JavaScript untuk Form Resume Medis Rawat Inap
 *
 * PERBAIKAN v2 (samakan pola awalmedisumum.js / awalmedisigd.js):
 * - Semua function di-scope dalam IIFE untuk menghindari konflik
 *   dengan JS lain saat dua tab dibuka bersamaan
 * - Semua querySelector dibatasi ke dalam elemen form Resume Medis
 * - Keyboard shortcut Ctrl+S hanya aktif saat form visible
 * - Live progress bar kelengkapan
 * - Validasi soft warning sebelum simpan (termasuk cross-check diagnosa/prosedur)
 * - ID button unik: btn-save-resumemedis, btn-delete-resumemedis
 */

(function() {
    'use strict';

    // ============================================================
    // KONSTANTA - ID unik untuk form ini
    // ============================================================
    var FORM_ID    = 'formResumeMedis';
    var BTN_SAVE   = 'btn-save-resumemedis';
    var BTN_DELETE = 'btn-delete-resumemedis';
    var TAB_ID     = 'resumemedis';

    // ============================================================
    // HELPER: cari elemen HANYA di dalam form Resume Medis
    // ============================================================
    function formEl() {
        return document.getElementById(FORM_ID);
    }

    function qForm(selector) {
        var f = formEl();
        return f ? f.querySelector(selector) : null;
    }

    function qFormAll(name) {
        var f = formEl();
        return f ? f.querySelectorAll('[name="' + name + '"]') : [];
    }

    // ============================================================
    // GET API URL (private, dengan fallback)
    // ============================================================
    function getApiUrl() {
        if (typeof API_PROSES2_URL !== 'undefined') return API_PROSES2_URL;
        if (typeof APP_BASE_URL   !== 'undefined') return APP_BASE_URL + '/pages/proses2.php';
        return '/edokter/pages/proses2.php';
    }

    function getApiUrlGetData() {
        if (typeof APP_BASE_URL !== 'undefined') return APP_BASE_URL + '/pages/get_data_resume.php';
        return '/edokter/pages/get_data_resume.php';
    }

    // ============================================================
    // LIVE PROGRESS BAR
    //
    // ATURAN: Hanya field input/textarea yang diisi user yang dihitung.
    // SELECT (kondisi_pulang) TIDAK dihitung karena selalu punya default.
    // ============================================================
    function calculateProgress() {
        var form = formEl();
        if (!form) return;

        var countableFields = [
            // I. Anamnesis & Riwayat
            'keluhan_utama', 'jalannya_penyakit', 'pemeriksaan_penunjang', 'hasil_laborat',
            // II. Diagnosis
            'diagnosa_utama', 'kd_diagnosa_utama',
            'diagnosa_sekunder', 'kd_diagnosa_sekunder',
            'diagnosa_sekunder2', 'kd_diagnosa_sekunder2',
            'diagnosa_sekunder3', 'kd_diagnosa_sekunder3',
            'diagnosa_sekunder4', 'kd_diagnosa_sekunder4',
            // III. Prosedur
            'prosedur_utama', 'kd_prosedur_utama',
            'prosedur_sekunder', 'kd_prosedur_sekunder',
            'prosedur_sekunder2', 'kd_prosedur_sekunder2',
            'prosedur_sekunder3', 'kd_prosedur_sekunder3',
            // IV. Tindak Lanjut
            'obat_pulang'
        ];

        var total  = countableFields.length;
        var filled = 0;

        countableFields.forEach(function(name) {
            var inputs = qFormAll(name);
            for (var i = 0; i < inputs.length; i++) {
                var val = inputs[i].value.trim();
                if (val) { filled++; break; }
            }
        });

        var pct = Math.round((filled / total) * 100);

        var container = form.closest('[id^="rmeAjax_"]') || form.closest('[id^="rmeContent_"]') || document;
        var bar    = container.querySelector ? container.querySelector('#progress-bar-resumemedis')    : document.getElementById('progress-bar-resumemedis');
        var text   = container.querySelector ? container.querySelector('#progress-text-resumemedis')   : document.getElementById('progress-text-resumemedis');
        var status = container.querySelector ? container.querySelector('#progress-status-resumemedis') : document.getElementById('progress-status-resumemedis');

        if (!bar)    bar    = document.getElementById('progress-bar-resumemedis');
        if (!text)   text   = document.getElementById('progress-text-resumemedis');
        if (!status) status = document.getElementById('progress-status-resumemedis');

        if (bar) {
            bar.style.width = pct + '%';
            bar.style.background = pct < 40 ? '#dc3545' : pct < 70 ? '#ffc107' : pct < 100 ? '#17a2b8' : '#28a745';
        }
        if (text) {
            text.textContent = pct + '%';
            text.style.color = pct === 100 ? '#28a745' : '#6c757d';
        }
        if (status) {
            status.textContent = '(' + filled + '/' + total + ')';
        }
    }

    // ============================================================
    // FORM SUBMIT HANDLER (VALIDASI + SIMPAN)
    // ============================================================
    function handleFormSubmit(e) {
        e.preventDefault();

        var emptySections = [];

        // 1. Anamnesis & Riwayat
        var anamnesiEmpty = [];
        var anamnesiFields = [
            { name: 'keluhan_utama', label: 'Keluhan Utama Riwayat Penyakit' },
            { name: 'jalannya_penyakit', label: 'Jalannya Penyakit Selama Perawatan' },
            { name: 'pemeriksaan_penunjang', label: 'Pemeriksaan Penunjang Yang Positif' },
            { name: 'hasil_laborat', label: 'Hasil Laboratorium Yang Positif' }
        ];
        anamnesiFields.forEach(function(field) {
            var inp = qForm('[name="' + field.name + '"]');
            if (!inp || !inp.value.trim()) anamnesiEmpty.push(field.label);
        });
        if (anamnesiEmpty.length) emptySections.push({ category: 'Anamnesis & Riwayat Perawatan', fields: anamnesiEmpty });

        // 2. Diagnosis
        var diagnosisEmpty = [];
        var diagUtama = qForm('[name="diagnosa_utama"]');
        var kdDiagUtama = qForm('[name="kd_diagnosa_utama"]');
        if (!diagUtama || !diagUtama.value.trim()) diagnosisEmpty.push('Diagnosa Utama');
        if (!kdDiagUtama || !kdDiagUtama.value.trim()) diagnosisEmpty.push('Kode ICD-10 Diagnosa Utama');

        // Cross-check diagnosa sekunder: nama ada tapi kode kosong, atau sebaliknya
        var diagSekFields = [
            { name: 'diagnosa_sekunder', kode: 'kd_diagnosa_sekunder', label: 'Diagnosa Sekunder 1' },
            { name: 'diagnosa_sekunder2', kode: 'kd_diagnosa_sekunder2', label: 'Diagnosa Sekunder 2' },
            { name: 'diagnosa_sekunder3', kode: 'kd_diagnosa_sekunder3', label: 'Diagnosa Sekunder 3' },
            { name: 'diagnosa_sekunder4', kode: 'kd_diagnosa_sekunder4', label: 'Diagnosa Sekunder 4' }
        ];
        diagSekFields.forEach(function(field) {
            var inp  = qForm('[name="' + field.name + '"]');
            var kode = qForm('[name="' + field.kode + '"]');
            var hasName = inp && inp.value.trim();
            var hasKode = kode && kode.value.trim();
            if (hasName && !hasKode) diagnosisEmpty.push('Kode ICD-10 ' + field.label);
            else if (!hasName && hasKode) diagnosisEmpty.push('Nama ' + field.label);
        });
        if (diagnosisEmpty.length) emptySections.push({ category: 'Diagnosis', fields: diagnosisEmpty });

        // 3. Prosedur
        var prosedurEmpty = [];
        var prosedurFields = [
            { name: 'prosedur_utama', kode: 'kd_prosedur_utama', label: 'Prosedur Utama' },
            { name: 'prosedur_sekunder', kode: 'kd_prosedur_sekunder', label: 'Prosedur Sekunder 1' },
            { name: 'prosedur_sekunder2', kode: 'kd_prosedur_sekunder2', label: 'Prosedur Sekunder 2' },
            { name: 'prosedur_sekunder3', kode: 'kd_prosedur_sekunder3', label: 'Prosedur Sekunder 3' }
        ];
        prosedurFields.forEach(function(field) {
            var inp  = qForm('[name="' + field.name + '"]');
            var kode = qForm('[name="' + field.kode + '"]');
            var hasName = inp && inp.value.trim();
            var hasKode = kode && kode.value.trim();
            if (hasName && !hasKode) prosedurEmpty.push('Kode ' + field.label);
            else if (!hasName && hasKode) prosedurEmpty.push('Nama ' + field.label);
        });
        if (prosedurEmpty.length) emptySections.push({ category: 'Prosedur / Tindakan', fields: prosedurEmpty });

        // 4. Tindak Lanjut
        var tindakEmpty = [];
        var obatPulang = qForm('[name="obat_pulang"]');
        if (!obatPulang || !obatPulang.value.trim()) tindakEmpty.push('Obat-obatan Waktu Pulang / Nasihat');
        if (tindakEmpty.length) emptySections.push({ category: 'Tindak Lanjut & Kondisi Pulang', fields: tindakEmpty });

        // Jika ada yang kosong → soft warning (tetap bisa simpan)
        if (emptySections.length > 0) {
            var htmlContent = '<div style="text-align:left;max-height:150px;overflow-y:auto;padding:2px;">';
            htmlContent += '<p style="margin-bottom:6px;font-size:11px;"><strong>Field berikut belum diisi:</strong></p>';
            emptySections.forEach(function(section) {
                htmlContent += '<div style="background:#fff3cd;border-left:2px solid #ffc107;padding:4px 6px;margin-bottom:4px;border-radius:3px;">';
                htmlContent += '<div style="font-weight:bold;color:#856404;margin-bottom:2px;font-size:10px;">' + section.category + '</div>';
                htmlContent += '<ul style="margin:0;padding-left:14px;color:#856404;font-size:10px;">';
                section.fields.forEach(function(f) { htmlContent += '<li style="margin:1px 0;">' + f + '</li>'; });
                htmlContent += '</ul></div>';
            });
            htmlContent += '<p style="margin-top:6px;padding:4px 6px;background:#d1ecf1;border-left:2px solid #0dcaf0;border-radius:3px;font-size:10px;color:#055160;"><strong>ℹ️</strong> Data tetap dapat disimpan.</p></div>';

            Swal.fire({
                title: 'Field Belum Diisi', html: htmlContent,
                showCancelButton: true, confirmButtonText: 'Simpan', cancelButtonText: 'Batal',
                confirmButtonColor: '#ffc107', cancelButtonColor: '#6c757d',
                width: '400px', padding: '15px'
            }).then(function(result) {
                if (result.isConfirmed) simpanData();
            });
            return;
        }

        simpanData();
    }

    // ============================================================
    // SIMPAN DATA
    // ============================================================
    function simpanData() {
        var form    = formEl();
        var btnSave = document.getElementById(BTN_SAVE);
        if (!form || !btnSave) return;

        var btnSaveText = btnSave.innerHTML;
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="material-icons" style="font-size:16px;">hourglass_empty</i> MENYIMPAN...';

        var formData = new FormData(form);
        formData.append('aksi', 'simpan_resume');

        fetch(getApiUrl(), { method: 'POST', body: formData })
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP error status: ' + response.status);
            return response.json();
        })
        .then(function(data) {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success', title: 'Berhasil Disimpan!', text: data.message,
                    confirmButtonText: 'OK', timer: 1500, timerProgressBar: true
                }).then(function() {
                    reloadTab(TAB_ID);
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal Menyimpan', text: data.message, confirmButtonText: 'OK' });
                btnSave.disabled = false;
                btnSave.innerHTML = btnSaveText;
            }
        })
        .catch(function(error) {
            Swal.fire({
                icon: 'error', title: 'Terjadi Kesalahan',
                html: '<strong>Error:</strong> ' + error.message, confirmButtonText: 'OK'
            });
            btnSave.disabled = false;
            btnSave.innerHTML = btnSaveText;
        });
    }

    // ============================================================
    // HAPUS DATA
    // ============================================================
    function hapusData(no_rawat) {
        var btnDelete = document.getElementById(BTN_DELETE);
        if (!btnDelete) return;

        var btnDeleteText = btnDelete.innerHTML;
        btnDelete.disabled = true;
        btnDelete.innerHTML = '<i class="material-icons" style="font-size:16px;">hourglass_empty</i> MENGHAPUS...';

        var formData = new FormData();
        formData.append('aksi', 'hapus_resume');
        formData.append('no_rawat', no_rawat);

        fetch(getApiUrl(), { method: 'POST', body: formData })
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP error status: ' + response.status);
            return response.json();
        })
        .then(function(data) {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success', title: 'Berhasil Dihapus!', text: data.message,
                    confirmButtonText: 'OK', timer: 1500, timerProgressBar: true
                }).then(function() {
                    reloadTab(TAB_ID);
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Gagal Menghapus', text: data.message, confirmButtonText: 'OK' });
                btnDelete.disabled = false;
                btnDelete.innerHTML = btnDeleteText;
            }
        })
        .catch(function(error) {
            Swal.fire({
                icon: 'error', title: 'Terjadi Kesalahan',
                html: '<strong>Error:</strong> ' + error.message, confirmButtonText: 'OK'
            });
            btnDelete.disabled = false;
            btnDelete.innerHTML = btnDeleteText;
        });
    }

    // ============================================================
    // HELPER: Reload tab yang benar setelah simpan/hapus
    // ============================================================
    function reloadTab(tabId) {
        if (typeof window.RmeTabManager !== 'undefined') {
            var tabs = window.RmeTabManager.getOpenTabs();
            //console.log('[ResumeMedis] reloadTab: tabId=' + tabId + ' exists=' + (tabs[tabId] ? 'YES' : 'NO'));
            if (tabs[tabId]) {
                tabs[tabId].loaded = false;
                setTimeout(function() {
                    window.RmeTabManager.switchTab(tabId);
                }, 200);
            }
        } else {
            window.location.reload();
        }
    }

    // ============================================================
    // INIT
    // ============================================================
    function initResumeMedis() {
        var form = formEl();
        if (!form) {
            console.warn('[ResumeMedis] Form tidak ditemukan, init dibatalkan');
            return;
        }

        //console.log('[ResumeMedis] Init...');

        // Event listener pada form (bukan document) → tidak lintas-tab
        form.addEventListener('input',  calculateProgress);
        form.addEventListener('change', calculateProgress);

        // Auto-resize textarea
        form.querySelectorAll('textarea').forEach(function(ta) {
            ta.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });

        // Keyboard shortcut — hanya aktif jika form Resume Medis visible
        document.addEventListener('keydown', function(e) {
            var f = formEl();
            if (!f || f.offsetParent === null) return;

            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                f.dispatchEvent(new Event('submit'));
            }
        });

        // Form submit
        form.setAttribute('novalidate', 'novalidate');
        form.removeEventListener('submit', handleFormSubmit);
        form.addEventListener('submit', handleFormSubmit);

        // Initial state
        calculateProgress();

        //console.log('[ResumeMedis] Init selesai ✅');
    }

    // Jalankan: support standalone & AJAX load dalam tab
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initResumeMedis);
    } else {
        initResumeMedis();
    }

    // ============================================================
    // PUBLIC API — hanya yang dipanggil via onclick="..." di HTML
    // ============================================================

    window.kembaliResumeMedis = function() {
        if (typeof window.RmeTabManager !== 'undefined') {
            window.RmeTabManager.closeTab(TAB_ID);
        } else {
            window.history.back();
        }
    };

    // ============================================================
    // GET DATA dari IGD / Awal Medis Ralan / SOAP
    // ============================================================
    function fetchDataResume(no_rawat) {
        var btn = document.getElementById('btn-get-data-resume');
        if (!btn) return;

        var btnText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="material-icons">hourglass_empty</i> LOADING...';

        var fd = new FormData();
        fd.append('aksi', 'get_data_resume');
        fd.append('no_rawat', no_rawat);

        fetch(getApiUrlGetData(), { method: 'POST', body: fd })
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP error status: ' + response.status);
            return response.json();
        })
        .then(function(resp) {
            btn.disabled = false;
            btn.innerHTML = btnText;

            if (resp.status === 'success' && resp.data) {
                var filledCount = fillFormWithResumeData(resp.data);
                calculateProgress();

                // Buat ringkasan sumber per field
                var sourceHtml = '';
                if (resp.sources) {
                    sourceHtml = '<div style="text-align:left;margin-top:10px;font-size:12px;background:#f0f9ff;border-radius:6px;padding:8px 10px;">';
                    sourceHtml += '<strong>Sumber data per field:</strong><ul style="margin:4px 0 0 0;padding-left:18px;">';
                    var labelMap = {
                        'keluhan_utama': 'Keluhan Utama',
                        'jalannya_penyakit': 'Jalannya Penyakit',
                        'pemeriksaan_penunjang': 'Pemeriksaan Penunjang',
                        'hasil_laborat': 'Hasil Laboratorium',
                        'obat_pulang': 'Obat Pulang'
                    };
                    Object.keys(resp.sources).forEach(function(k) {
                        sourceHtml += '<li><b>' + (labelMap[k] || k) + ':</b> ' + resp.sources[k] + '</li>';
                    });
                    sourceHtml += '</ul></div>';
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Data Berhasil Diambil',
                    html: '<div style="font-size:13px;">Form terisi pada <b>' + filledCount + '</b> field.</div>' + sourceHtml,
                    confirmButtonText: 'OK',
                    width: '480px'
                });
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Data Tidak Ditemukan',
                    text: resp.message || 'Tidak ada data sumber untuk pasien ini.',
                    confirmButtonText: 'OK'
                });
            }
        })
        .catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = btnText;
            Swal.fire({
                icon: 'error',
                title: 'Terjadi Kesalahan',
                html: '<strong>Error:</strong> ' + error.message,
                confirmButtonText: 'OK'
            });
        });
    }

    function fillFormWithResumeData(d) {
        var count = 0;
        var targetFields = ['keluhan_utama','jalannya_penyakit','pemeriksaan_penunjang','hasil_laborat','obat_pulang'];

        targetFields.forEach(function(name) {
            if (d[name]) {
                var el = qForm('[name="' + name + '"]');
                if (el) {
                    el.value = d[name];
                    count++;
                }
            }
        });

        // Auto-resize textarea setelah diisi
        var form = formEl();
        if (form) {
            form.querySelectorAll('textarea').forEach(function(ta) {
                ta.style.height = 'auto';
                ta.style.height = ta.scrollHeight + 'px';
            });
        }

        return count;
    }

    window.getDataResume = function() {
        var no_rawat_input = qForm('[name="no_rawat"]');
        var no_rawat = no_rawat_input ? no_rawat_input.value : '';

        if (!no_rawat) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No. Rawat tidak valid!', confirmButtonText: 'OK' });
            return;
        }

        Swal.fire({
            title: 'Ambil Data Otomatis?',
            html: '<div style="text-align:left;font-size:13px;">' +
                '<p>Sistem akan mencari data dari sumber berikut (prioritas berurutan):</p>' +
                '<ul style="margin:8px 0;padding-left:20px;">' +
                '<li><b>Keluhan Utama</b> → IGD ▶ Poli ▶ SOAP</li>' +
                '<li><b>Jalannya Penyakit</b> → SOAP (kronologis)</li>' +
                '<li><b>Hasil Laboratorium</b> → tabel detail_periksa_lab</li>' +
                '<li><b>Pemeriksaan Penunjang</b> → tabel hasil_radiologi</li>' +
                '<li><b>Obat Pulang</b> → SOAP (RTL) ▶ IGD ▶ Poli</li>' +
                '</ul>' +
                '<p style="color:#e74c3c;"><strong>⚠️ Field yang sudah terisi akan tertimpa!</strong></p>' +
                '<p style="font-size:11px;color:#6c757d;">Diagnosa & Prosedur tetap dari SIMRS (tidak diubah).</p></div>',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Ya, Ambil Data', cancelButtonText: 'Batal',
            confirmButtonColor: '#10b981', cancelButtonColor: '#6b7280',
            width: '480px'
        }).then(function(result) {
            if (result.isConfirmed) fetchDataResume(no_rawat);
        });
    };

    window.confirmDeleteResumeMedis = function() {
        var no_rawat_input = qForm('[name="no_rawat"]');
        var no_rawat = no_rawat_input ? no_rawat_input.value : '';

        if (!no_rawat) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No. Rawat tidak valid!', confirmButtonText: 'OK' });
            return;
        }

        Swal.fire({
            title: 'Konfirmasi Hapus',
            text: 'Apakah Anda yakin ingin menghapus data Resume Medis ini?',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal',
            confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', reverseButtons: true
        }).then(function(result) {
            if (result.isConfirmed) hapusData(no_rawat);
        });
    };

})();
/* END resumemedis.js */
