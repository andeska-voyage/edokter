<?php
/**
 * conf/template.php
 *
 * Template quick-fill SOAPIE — per kd_poli, dipakai di pemeriksaan.php.
 *
 * STRUKTUR:
 *   - '__default__' : template dasar yang berlaku untuk SEMUA poli
 *   - '<kd_poli>'   : template tambahan spesifik per poli
 *
 * Saat di-render, default + per-poli akan digabung (dipisahkan grup).
 * Jika kd_poli pasien tidak ada di array ini → hanya pakai __default__.
 *
 * Tidak menambah tabel database. Edit file ini langsung untuk update template.
 *
 * 6 section SOAPIE:
 *   subjective    (S) - keluhan pasien, anamnesis, riwayat
 *   objective     (O) - hasil pemeriksaan fisik & penunjang
 *   assessment    (A) - diagnosa, penilaian klinis
 *   plan          (P) - rencana tindakan, terapi, rujukan
 *   intervention  (I) - intervensi yang dilakukan, tindakan medis
 *   evaluation    (E) - evaluasi hasil tindakan, respon pasien
 */

return [

    // ================================================================
    // DEFAULT — berlaku untuk semua poli (base template)
    // ================================================================
    '__default__' => [

        'subjective' => [
            'Demam sejak ... hari yang lalu',
            'Batuk berdahak',
            'Batuk kering',
            'Pilek / hidung tersumbat',
            'Sakit kepala',
            'Pusing berputar',
            'Mual, muntah',
            'Nyeri perut',
            'Diare ... kali sehari',
            'Sulit BAB',
            'Sesak napas',
            'Nyeri dada',
            'Badan lemas',
            'Tidak nafsu makan',
            'Sulit tidur',
            'Nyeri sendi / otot',
            'Riwayat alergi (-)',
            'Riwayat penyakit dahulu (-)',
            'Tidak ada keluhan khusus',
        ],

        'objective' => [
            'KU baik, compos mentis',
            'TD dalam batas normal',
            'Nadi reguler, kuat angkat',
            'Suhu afebris',
            'RR dalam batas normal',
            'SpO2 dalam batas normal',
            'Konjungtiva tidak anemis, sklera tidak ikterik',
            'Faring tidak hiperemis, tonsil T1/T1',
            'Pulmo: vesikuler +/+, ronki -/-, wheezing -/-',
            'Cor: BJ I-II reguler, murmur (-), gallop (-)',
            'Abdomen: supel, BU normal, nyeri tekan (-)',
            'Ekstremitas: akral hangat, edema (-)',
            'Pemeriksaan lab dalam batas normal',
        ],

        // Status per organ — radio Normal/Abnormal/Tidak Diperiksa
        // 'objective_organ' => [
        //     'Kepala',
        //     'Mata',
        //     'Leher',
        //     'Thoraks',
        //     'Abdomen',
        //     'Ekstremitas',
        //     'Kulit',
        // ],

        'assessment' => [
            'ISPA',
            'Common cold',
            'Faringitis akut',
            'Gastritis',
            'Dispepsia',
            'Diare akut',
            'Demam tifoid',
            'Hipertensi',
            'DM tipe 2',
            'Cephalgia',
            'Mialgia',
            'Observasi febris',
            'Vertigo',
        ],

        'plan' => [
            'Terapi simptomatik',
            'Edukasi pasien & keluarga',
            'Istirahat cukup',
            'Diet biasa, perbanyak minum',
            'Diet lambung',
            'Diet rendah garam',
            'Diet rendah gula',
            'Cek lab darah lengkap',
            'Cek gula darah sewaktu',
            'Cek profil lipid',
            'Foto thoraks PA',
            'USG abdomen',
            'EKG',
            'Kontrol 3 hari',
            'Kontrol 7 hari',
            'Kontrol bila keluhan menetap / memberat',
            'Rujuk ke spesialis',
        ],

        'intervention' => [
            // === INSTRUKSI obat & terapi ===
            'Pemberian obat oral sesuai resep',
            'Injeksi IM sesuai resep',
            'Injeksi IV sesuai resep',
            'Pemberian oksigen ... lpm via nasal kanul',
            'Nebulizer ... menit',
            // === INSTRUKSI observasi & monitoring ===
            'Observasi tanda vital tiap ... jam',
            'Cek GDS tiap ... jam',
            'Monitoring intake-output cairan',
            'Bedrest ... jam',
            // === IMPLEMENTASI tindakan ===
            'Pemasangan infus ...',
            'Pemasangan kateter urin',
            'Wound care / perawatan luka',
            'Aff hecting',
            'Informed consent diberikan',
            // === IMPLEMENTASI edukasi ===
            'Edukasi cuci tangan & PHBS',
            'Edukasi minum obat teratur',
            'Edukasi pola hidup sehat',
            'Edukasi tanda bahaya',
            'Edukasi diet (...)',
        ],

        'evaluation' => [
            'Keluhan berkurang',
            'Tanda vital stabil',
            'Pasien kooperatif',
            'Edukasi diterima dengan baik',
            'Kondisi membaik, dapat dipulangkan',
            'Belum ada perbaikan, terapi dilanjutkan',
            'Perlu evaluasi lanjut',
            'Saran kontrol kembali',
            'Rujuk bila tidak ada perbaikan',
        ],
    ],

    // ================================================================
    // U0009 - Poliklinik Umum
    // ================================================================
    'U0009' => [
        'subjective' => [
            'Pemeriksaan kesehatan rutin',
            'Permintaan surat keterangan sehat',
            'Permintaan surat keterangan sakit',
            'Imunisasi dewasa',
        ],
        'assessment' => [
            'Common cold',
            'Tonsilitis akut',
            'Mialgia',
            'Vulnus laseratum',
            'Insomnia',
        ],
        'plan' => [
            'Surat keterangan sehat',
            'Surat keterangan sakit',
        ],
        'objective_organ' => [
            'Kepala', 'Mata', 'THT', 'Leher', 'Thoraks', 'Abdomen', 'Ekstremitas', 'Kulit',
        ],
        'intervention' => [
            'Pemeriksaan TTV lengkap',
            'Pemeriksaan tinggi & berat badan',
            'Wound toilet (luka ringan)',
            'Penjahitan luka sederhana',
            'Edukasi kesehatan umum',
        ],
        'evaluation' => [
            'Pasien sehat, layak bekerja',
            'Tidak ada gangguan kesehatan',
            'Surat keterangan diberikan',
            'Pasien diedukasi pola hidup sehat',
        ],
    ],

    // ================================================================
    // U0003 - Poliklinik Penyakit Dalam
    // ================================================================
    'U0003' => [
        'subjective' => [
            'Nyeri ulu hati',
            'Mual sehabis makan',
            'BAB hitam (melena)',
            'Riwayat hipertensi',
            'Riwayat DM',
            'Riwayat asam urat',
            'Riwayat dyslipidemia',
            'Sering BAK malam hari',
            'Edema tungkai',
        ],
        'assessment' => [
            'GERD',
            'Dispepsia fungsional',
            'Gastropati NSAID',
            'Hipertensi grade I',
            'Hipertensi grade II',
            'DM tipe 2 terkontrol',
            'DM tipe 2 tidak terkontrol',
            'Hiperurisemia',
            'Dislipidemia',
            'Obesitas',
        ],
        'plan' => [
            'HbA1c',
            'Asam urat darah',
            'Ureum / kreatinin',
            'SGOT / SGPT',
            'Kontrol gula darah berkala',
            'Konsultasi gizi',
        ],
        'objective_organ' => [
            'Kepala', 'Mata', 'Leher', 'Thoraks', 'Cor', 'Pulmo',
            'Abdomen', 'Hepar', 'Lien', 'Ginjal', 'Ekstremitas', 'Edema',
        ],
        'intervention' => [
            'Pemasangan IV line', 'Injeksi insulin SC',
            'Edukasi diet DM', 'Edukasi diet rendah garam',
            'Edukasi pola hidup sehat',
        ],
        'evaluation' => [
            'GDS membaik', 'TD terkontrol',
            'Keluhan dispepsia berkurang', 'Edema berkurang',
            'Pasien memahami edukasi diet',
        ],
    ],

    // ================================================================
    // U0002 - Poliklinik Anak
    // ================================================================
    'U0002' => [
        'subjective' => [
            'Demam tinggi pada anak',
            'Rewel, sulit tidur',
            'Tidak mau menyusu / makan',
            'BAB cair pada anak',
            'Muntah berulang',
            'Batuk pilek',
            'Ruam pada kulit',
            'Riwayat imunisasi lengkap',
            'Riwayat imunisasi belum lengkap',
        ],
        'objective' => [
            'BB ... kg, TB ... cm',
            'Status gizi baik',
            'UUB datar, tidak cekung',
            'Turgor kulit baik',
        ],
        'objective_organ' => [
            'UUB', 'Kepala', 'Mata', 'Leher', 'Thoraks', 'Abdomen', 'Ekstremitas', 'Turgor Kulit',
        ],
        'assessment' => [
            'ISPA pada anak',
            'Diare akut tanpa dehidrasi',
            'Diare akut dengan dehidrasi ringan-sedang',
            'Bronkiolitis',
            'Demam tanpa sebab yang jelas',
            'Gizi kurang',
            'Cacingan',
        ],
        'plan' => [
            'Oralit / cairan rumahan',
            'Edukasi MPASI',
            'Edukasi imunisasi',
            'Konsultasi gizi anak',
        ],
        'intervention' => [
            'Pemberian oralit', 'Rehidrasi cairan',
            'Nebulizer', 'Pemberian antipiretik',
            'Edukasi MPASI', 'Edukasi imunisasi lanjutan',
        ],
        'evaluation' => [
            'Demam turun', 'Diare berkurang',
            'Anak lebih aktif', 'Mau menyusu / makan kembali',
            'Orang tua memahami edukasi',
        ],
    ],

    // ================================================================
    // U0010 - Poliklinik Gigi & Mulut
    // ================================================================
    'U0010' => [
        'subjective' => [
            'Sakit gigi sisi ...',
            'Gusi bengkak / berdarah',
            'Gigi berlubang',
            'Kontrol pasca pencabutan',
            'Ingin tambal / cabut gigi',
            'Bau mulut',
        ],
        'objective' => [
            'Karies pada gigi ...',
            'Karies media',
            'Karies profunda',
            'Pulpitis reversibel',
            'Pulpitis ireversibel',
            'Gingivitis',
            'Periodontitis',
        ],
        'assessment' => [
            'Karies gigi',
            'Pulpitis',
            'Abses periapikal',
            'Gingivitis',
            'Periodontitis kronis',
            'Persistensi gigi sulung',
        ],
        'plan' => [
            'Penambalan gigi (GIC / komposit)',
            'Pencabutan gigi',
            'Scaling',
            'Trepanasi & medikamen',
            'Edukasi sikat gigi 2x sehari',
        ],
        'intervention' => [
            'Anestesi lokal infiltrasi',
            'Anestesi lokal blok mandibula',
            'Ekstraksi gigi',
            'Penambalan komposit',
            'Penambalan GIC',
            'Pulp capping',
        ],
        'objective_organ' => [
            'Wajah / Ekstra Oral', 'Bibir', 'Mukosa Mulut', 'Gusi',
            'Gigi Geligi', 'Lidah', 'Palatum', 'KGB Submandibula',
        ],
        'evaluation' => [
            'Anestesi adekuat', 'Perdarahan terkontrol',
            'Tidak ada komplikasi pasca tindakan',
            'Pasien diinstruksikan pasca cabut/tambal',
            'Edukasi oral hygiene diterima',
        ],
    ],

    // ================================================================
    // U0011 - Poliklinik THT
    // ================================================================
    'U0011' => [
        'subjective' => [
            'Nyeri telinga',
            'Telinga berdengung (tinnitus)',
            'Pendengaran menurun',
            'Hidung tersumbat',
            'Bersin-bersin pagi hari',
            'Mimisan',
            'Sakit menelan',
            'Suara serak',
        ],
        'objective' => [
            'MAE lapang, serumen (-)',
            'Membran timpani intak, refleks cahaya (+)',
            'Konka hipertrofi',
            'Sekret hidung mukoid / purulen',
            'Tonsil T1-T1 / T2-T2 / T3-T3',
            'Faring hiperemis',
        ],
        'assessment' => [
            'OMA (Otitis Media Akut)',
            'OMSK (Otitis Media Supuratif Kronis)',
            'Otitis eksterna',
            'Rinitis alergi',
            'Rinosinusitis',
            'Faringitis',
            'Tonsilitis akut',
            'Tonsilitis kronis',
            'Serumen prop',
        ],
        'plan' => [
            'Tampon telinga',
            'Cuci telinga',
            'Audiometri',
            'Endoskopi nasal',
        ],
        'objective_organ' => [
            'Telinga Kanan', 'Telinga Kiri', 'Membran Timpani Kanan', 'Membran Timpani Kiri',
            'Hidung / Cavum Nasi', 'Sinus Paranasal',
            'Faring', 'Tonsil', 'Laring',
            'Kelenjar Getah Bening Leher',
        ],
        'intervention' => [
            'Cuci telinga', 'Ekstraksi serumen', 'Tampon hidung',
            'Insisi & drainase abses peritonsil',
            'Spooling sinus', 'Edukasi cara cuci hidung',
        ],
        'evaluation' => [
            'Nyeri telinga berkurang', 'Pendengaran membaik',
            'Hidung lebih lega', 'Sekret berkurang',
            'Edukasi cuci hidung diterima',
        ],
    ],

    // ================================================================
    // U0005 - Poliklinik Mata
    // ================================================================
    'U0005' => [
        'subjective' => [
            'Mata merah',
            'Mata gatal & berair',
            'Penglihatan kabur',
            'Penglihatan jauh kabur',
            'Penglihatan dekat kabur',
            'Silau saat melihat cahaya',
            'Bengkak di kelopak mata',
            'Belekan',
        ],
        'objective' => [
            'Visus OD: ..., OS: ...',
            'Konjungtiva hiperemis',
            'Kornea jernih',
            'Lensa jernih',
            'Reflek pupil normal',
            'TIO normal palpasi',
        ],
        'objective_organ' => [
            'Palpebra', 'Konjungtiva', 'Sklera', 'Kornea', 'Lensa', 'Pupil', 'TIO',
        ],
        'assessment' => [
            'Konjungtivitis bakterial',
            'Konjungtivitis virus',
            'Konjungtivitis alergi',
            'Hordeolum',
            'Kalazion',
            'Pterygium',
            'Refraksi anomali (miopia / hipermetropia / astigmatisme)',
            'Katarak senilis',
        ],
        'plan' => [
            'Pemeriksaan visus / refraksi',
            'Tonometri',
            'Slit lamp',
            'Resep kacamata',
            'Rujuk bedah katarak',
        ],
        'intervention' => [
            'Pemeriksaan visus / refraksi', 'Tonometri',
            'Slit lamp examination', 'Funduskopi',
            'Edukasi pemakaian obat tetes mata',
            'Edukasi pemakaian kacamata',
        ],
        'evaluation' => [
            'Visus membaik', 'Mata merah berkurang',
            'TIO terkontrol', 'Edukasi obat tetes diterima',
        ],
    ],

    // ================================================================
    // U0012 - Poliklinik Jantung
    // ================================================================
    'U0012' => [
        'subjective' => [
            'Nyeri dada saat aktivitas',
            'Nyeri dada saat istirahat',
            'Sesak saat aktivitas (DOE)',
            'Sesak saat berbaring (orthopnea)',
            'Berdebar-debar (palpitasi)',
            'Bengkak tungkai',
            'Cepat lelah',
            'Riwayat hipertensi',
            'Riwayat DM',
        ],
        'objective' => [
            'JVP tidak meningkat',
            'Iktus kordis tidak melebar',
            'BJ I-II reguler, murmur (-), gallop (-)',
            'Murmur sistolik (+)',
            'Murmur diastolik (+)',
            'Edema pretibial (-/-)',
        ],
        'objective_organ' => [
            'Kepala', 'Leher (JVP)', 'Thoraks', 'Cor', 'Pulmo', 'Abdomen', 'Ekstremitas', 'Edema',
        ],
        'assessment' => [
            'CAD (Coronary Artery Disease)',
            'Angina pektoris stabil',
            'Hipertensi grade I',
            'Hipertensi grade II',
            'Gagal jantung NYHA II',
            'Atrial fibrilasi',
            'Aritmia',
            'Kardiomiopati',
        ],
        'plan' => [
            'EKG 12 lead',
            'Echocardiografi',
            'Treadmill test',
            'Lab: CK-MB, Troponin',
            'Profil lipid',
        ],
        'intervention' => [
            'Pemeriksaan EKG', 'Pemberian oksigen',
            'Edukasi pola hidup jantung sehat',
            'Edukasi tanda bahaya nyeri dada',
            'Pemberian nitrogliserin sublingual',
        ],
        'evaluation' => [
            'Nyeri dada berkurang', 'TD terkontrol',
            'Sesak berkurang', 'EKG dalam batas normal',
            'Pasien memahami tanda bahaya',
        ],
    ],

    // ================================================================
    // U0007 - Poliklinik Syaraf / Neurologi
    // ================================================================
    'U0007' => [
        'subjective' => [
            'Sakit kepala sebelah',
            'Sakit kepala seluruh kepala',
            'Pusing berputar',
            'Kesemutan ekstremitas',
            'Kelemahan anggota gerak',
            'Wajah perot (asimetri)',
            'Kejang',
            'Tremor',
            'Sulit bicara',
        ],
        'objective' => [
            'GCS E4V5M6',
            'Pupil isokor 3mm/3mm, refleks cahaya +/+',
            'Nervus kranialis dalam batas normal',
            'Motorik 5/5 keempat ekstremitas',
            'Sensorik dalam batas normal',
            'Refleks fisiologis +/+',
            'Refleks patologis -/-',
        ],
        'assessment' => [
            'Migrain tanpa aura',
            'Migrain dengan aura',
            'Tension type headache',
            'Vertigo perifer (BPPV)',
            'Neuralgia trigeminal',
            'Bell\'s palsy',
            'Stroke iskemik',
            'TIA',
            'Polineuropati',
        ],
        'plan' => [
            'CT scan kepala non-kontras',
            'MRI kepala',
            'EEG',
            'EMG',
            'Fisioterapi',
        ],
        'objective_organ' => [
            'GCS', 'Pupil', 'Nervus Kranialis',
            'Motorik Atas Kanan', 'Motorik Atas Kiri',
            'Motorik Bawah Kanan', 'Motorik Bawah Kiri',
            'Sensorik', 'Refleks Fisiologis', 'Refleks Patologis',
            'Tanda Rangsang Meningeal', 'Cerebellum',
        ],
        'intervention' => [
            'Pemeriksaan neurologis lengkap',
            'Edukasi posisi tidur (vertigo)',
            'Edukasi pencegahan stroke',
            'Pemberian analgesik (migrain)',
            'Manuver Epley (BPPV)',
        ],
        'evaluation' => [
            'Sakit kepala berkurang', 'Vertigo membaik',
            'Kelemahan ekstremitas membaik',
            'Kesemutan berkurang',
            'Pasien memahami edukasi',
        ],
    ],

    // ================================================================
    // U0004 - Poliklinik Bedah
    // ================================================================
    'U0004' => [
        'subjective' => [
            'Benjolan di ... sejak ... bulan',
            'Luka post operasi',
            'Nyeri perut kanan bawah',
            'Sulit BAK',
            'BAB berdarah',
            'Riwayat operasi sebelumnya',
        ],
        'objective' => [
            'Luka post op kering, hecting intak',
            'Tanda infeksi (-)',
            'Pus (-)',
            'Nyeri tekan McBurney (+)',
            'Massa teraba di ...',
        ],
        'assessment' => [
            'Hernia inguinalis',
            'Apendisitis akut',
            'Hemoroid grade ...',
            'Lipoma',
            'Atheroma',
            'Vulnus laseratum',
            'Vulnus appertum',
            'Abses',
            'Post operasi (hari ke-...)',
        ],
        'plan' => [
            'Ganti verban',
            'Aff hecting',
            'Konsul anestesi pre-op',
            'Lab pre-op lengkap',
            'Foto thoraks pre-op',
        ],
        'intervention' => [
            'Wound toilet',
            'Aff hecting',
            'Insisi & drainase abses',
            'Ekstraksi corpus alienum',
            'Hecting situasi',
        ],
        'objective_organ' => [
            'Status Generalis', 'Luka Operasi', 'Tanda Infeksi',
            'Drain', 'Hecting', 'Massa / Benjolan',
            'Abdomen', 'Inguinal', 'Anorektal', 'Ekstremitas',
        ],
        'evaluation' => [
            'Luka kering, tanda infeksi (-)',
            'Tidak ada perdarahan aktif',
            'Hecting intak',
            'Pasien diinstruksikan kontrol luka',
            'Edukasi perawatan luka diterima',
        ],
    ],

    // ================================================================
    // U0001 - Poliklinik Kandungan
    // ================================================================
    'U0001' => [
        'subjective' => [
            'Keputihan',
            'Nyeri haid (dismenore)',
            'Haid tidak teratur',
            'Telat haid ... minggu',
            'ANC rutin',
            'Mual muntah trimester awal',
            'Pergerakan janin aktif',
            'Riwayat persalinan: ...',
        ],
        'objective' => [
            'TFU sesuai usia kehamilan',
            'DJJ ... x/menit, reguler',
            'Letak janin: kepala / sungsang',
            'Vulva/vagina dalam batas normal',
        ],
        'objective_organ' => [
            'Kepala', 'Mata', 'Thoraks', 'Abdomen', 'TFU', 'DJJ', 'Vulva/Vagina', 'Ekstremitas',
        ],
        'assessment' => [
            'G..P..A.. usia kehamilan ... minggu',
            'Fluor albus',
            'Dismenore primer',
            'Kista ovarium',
            'Mioma uteri',
            'PCOS',
            'Infertilitas primer',
        ],
        'plan' => [
            'USG kehamilan',
            'USG transvaginal',
            'Lab ANC: Hb, urin, HBsAg',
            'Pap smear',
            'IVA test',
        ],
        'intervention' => [
            'Pemeriksaan obstetri (Leopold)',
            'Auskultasi DJJ', 'USG kehamilan',
            'Edukasi tanda bahaya kehamilan',
            'Edukasi nutrisi ibu hamil',
            'Pemberian Fe dan asam folat',
        ],
        'evaluation' => [
            'DJJ dalam batas normal',
            'TFU sesuai usia kehamilan',
            'Ibu memahami tanda bahaya',
            'Edukasi nutrisi diterima',
        ],
    ],

    // ================================================================
    // U0006 - Poliklinik Kulit & Kelamin
    // ================================================================
    'U0006' => [
        'subjective' => [
            'Gatal di ... sejak ... hari',
            'kenapa ...',
            'Bercak merah / putih di kulit',
            'Bentol-bentol seluruh tubuh',
            'Bersisik / kering',
            'Bernanah / berair',
            'Riwayat kontak alergen',
        ],
        'objective' => [
            'Effloresensi: makula / papul / vesikel / pustul',
            'Lokasi ... kelamin',
            'Konfigurasi: numular / linear / anular',
            'Distribusi: simetris / asimetris',
        ],
        'assessment' => [
            'Dermatitis kontak alergi',
            'Dermatitis atopik',
            'Dermatitis seboroik',
            'Tinea corporis',
            'Tinea cruris',
            'Tinea pedis',
            'Skabies',
            'Urtikaria akut',
            'Akne vulgaris',
            'Herpes zoster',
        ],
        'plan' => [
            'KOH test',
            'Patch test',
            'Edukasi hindari alergen',
            'Edukasi salep & krim',
        ],
        'objective_organ' => [
            'Kepala / Wajah', 'Leher', 'Trunkus / Badan',
            'Ekstremitas Atas', 'Ekstremitas Bawah',
            'Genital', 'Perianal', 'Mukosa', 'Kuku', 'Rambut',
        ],
        'intervention' => [
            'KOH test', 'Patch test',
            'Edukasi cara aplikasi salep / krim',
            'Edukasi hindari alergen',
            'Edukasi kebersihan kulit',
            'Insisi & drainase',
        ],
        'evaluation' => [
            'Lesi kulit membaik', 'Gatal berkurang',
            'Tidak ada efek samping topikal',
            'Edukasi diterima dengan baik',
        ],
    ],

    // ================================================================
    // IGDK - IGD
    // ================================================================
    'IGDK' => [
        'subjective' => [
            'Pasien datang dengan kondisi gawat darurat',
            'Penurunan kesadaran',
            'Trauma',
            'Kecelakaan lalu lintas',
            'Sesak berat',
            'Nyeri dada hebat',
            'Kejang',
            'Pingsan',
            'Dehidrasi berat',
        ],
        'objective' => [
            'Triase: Merah / Kuning / Hijau',
            'Airway clear / partial obstruction',
            'Breathing spontan / dibantu',
            'Circulation: TD ... / nadi ...',
            'GCS E.V.M = ...',
        ],
        'assessment' => [
            'Syok hipovolemik',
            'Syok kardiogenik',
            'Syok septik',
            'Dehidrasi berat',
            'Cedera kepala ringan / sedang / berat',
            'Multiple trauma',
            'Kejang demam',
        ],
        'plan' => [
            'Resusitasi cairan',
            'O2 nasal kanul / NRM',
            'Pasang IV line',
            'Observasi 6 jam',
            'Rawat inap',
            'Rujuk ICU',
        ],
        'intervention' => [
            'Resusitasi cairan IV',
            'Pemasangan oksigen',
            'Pemasangan kateter urin',
            'Pemasangan NGT',
            'Hecting luka',
            'Bidai / spalk',
        ],
        'objective_organ' => [
            'Airway', 'Breathing', 'Circulation', 'Disability (GCS)', 'Exposure',
            'Kepala', 'Mata / Pupil', 'Thoraks', 'Abdomen', 'Pelvis',
            'Ekstremitas', 'Tulang Belakang',
        ],
        'evaluation' => [
            'Tanda vital stabil', 'GCS membaik',
            'Perdarahan terkontrol', 'Sesak berkurang',
            'Pasien dapat dipulangkan',
            'Pasien dirujuk ke ruang rawat inap',
            'Pasien dirujuk ke ICU',
            'Pasien dirujuk ke RS lain',
        ],
    ],

    // ================================================================
    // INT - Poli Penyakit Dalam (alternatif kode lama)
    // ================================================================
    'INT' => [
        'objective_organ' => [
            'Kepala', 'Mata', 'Leher', 'Thoraks', 'Cor', 'Pulmo',
            'Abdomen', 'Hepar', 'Lien', 'Ginjal', 'Ekstremitas', 'Edema',
        ],
    ],

    // ================================================================
    // OBG - Poli Obstetri/Ginekologi (alternatif kode lama)
    // ================================================================
    'OBG' => [
        'objective_organ' => [
            'Kepala', 'Mata', 'Thoraks', 'Abdomen',
            'TFU', 'DJJ', 'Letak Janin', 'Vulva/Vagina',
            'Inspekulo', 'VT (Vaginal Toucher)', 'Ekstremitas',
        ],
    ],

    // ================================================================
    // UMU - Poli Umum (alternatif kode lama)
    // ================================================================
    'UMU' => [
        'objective_organ' => [
            'Kepala', 'Mata', 'THT', 'Leher', 'Thoraks',
            'Abdomen', 'Ekstremitas', 'Kulit',
        ],
    ],

    // ================================================================
    // U0026 - Unit Laborat (pasien datang minta cek lab)
    // ================================================================
    'U0026' => [
        'subjective' => [
            'Permintaan cek lab rutin',
            'Permintaan cek lab dari dokter luar',
            'Cek lab medical check-up',
            'Cek gula darah berkala',
            'Cek profil lipid',
            'Cek fungsi hati',
            'Cek fungsi ginjal',
            'Cek darah lengkap',
            'Puasa sebelum cek lab',
        ],
        'assessment' => [
            'Anemia ec. defisiensi besi',
            'Hiperglikemia',
            'Dislipidemia',
            'Gangguan fungsi hati',
            'Gangguan fungsi ginjal',
            'Hiperurisemia',
            'Hasil lab dalam batas normal',
        ],
        'objective_organ' => [
            'Kepala', 'Mata', 'Konjungtiva', 'Thoraks', 'Abdomen',
            'Akses Vena (untuk pengambilan sampel)', 'Ekstremitas',
        ],
        'plan' => [
            'Pengambilan sampel darah',
            'Pengambilan sampel urin',
            'Pengambilan sampel feses',
            'Konsultasi hasil lab ke dokter',
            'Edukasi puasa cek lab',
        ],
    ],

    // ================================================================
    // U0027 - MCU (Medical Check-Up) — comprehensive
    // ================================================================
    'U0027' => [
        'subjective' => [
            'MCU rutin tahunan',
            'MCU pra-kerja',
            'MCU pra-nikah',
            'MCU CPNS / pegawai',
            'MCU asuransi',
            'Riwayat penyakit keluarga',
            'Riwayat merokok',
            'Riwayat alkohol',
            'Pola olahraga',
        ],
        'assessment' => [
            'Sehat tanpa kelainan',
            'Hipertensi grade I',
            'Dislipidemia',
            'Pre-DM',
            'Obesitas',
            'Underweight',
            'Anemia',
        ],
        'objective_organ' => [
            'Kepala', 'Mata', 'THT', 'Gigi & Mulut', 'Leher',
            'Thoraks', 'Cor', 'Pulmo', 'Mammae',
            'Abdomen', 'Hepar', 'Lien', 'Ginjal',
            'Genital', 'Ekstremitas', 'Kulit', 'Postur Tubuh',
        ],
        'plan' => [
            'Cek darah lengkap',
            'Cek gula darah, profil lipid',
            'Cek fungsi hati & ginjal',
            'Foto thoraks PA',
            'EKG',
            'USG abdomen',
            'Audiometri',
            'Spirometri',
            'Buta warna (Ishihara)',
            'Visus / refraksi',
            'Pap smear (wanita)',
            'Edukasi pola hidup sehat',
        ],
        'evaluation' => [
            'MCU lengkap, hasil normal',
            'Ada faktor risiko, anjurkan kontrol',
            'Disarankan rujuk spesialis',
            'Surat keterangan MCU diberikan',
        ],
    ],

    // ================================================================
    // U0052 - POLI GINJAL (fokus renal & uropoetic)
    // ================================================================
    'U0052' => [
        'subjective' => [
            'Bengkak seluruh tubuh',
            'Bengkak pagi hari (peri-orbital)',
            'BAK berkurang / oliguria',
            'BAK berdarah (hematuria)',
            'BAK keruh / berbuih',
            'Nyeri pinggang',
            'Sering BAK malam (nokturia)',
            'Riwayat hipertensi lama',
            'Riwayat DM lama',
            'Riwayat batu ginjal',
            'Riwayat hemodialisis',
        ],
        'assessment' => [
            'Acute Kidney Injury (AKI)',
            'Chronic Kidney Disease (CKD) stage I-V',
            'Sindrom nefrotik',
            'Sindrom nefritik',
            'Glomerulonefritis akut',
            'Pielonefritis',
            'ISK (Infeksi Saluran Kemih)',
            'Batu ginjal / batu saluran kemih',
            'Hipertensi nefrogenik',
            'End-Stage Renal Disease (ESRD)',
        ],
        'objective_organ' => [
            'Kepala', 'Mata (anemis/edema palpebra)', 'Mukosa Mulut',
            'Leher (JVP)', 'Thoraks', 'Cor', 'Pulmo',
            'Abdomen', 'Ginjal (ballotement)', 'Costovertebral Angle',
            'Vesica Urinaria', 'Genital',
            'Ekstremitas (edema pretibial)', 'Akses Hemodialisis',
        ],
        'plan' => [
            'Ureum / kreatinin',
            'Elektrolit (Na, K, Cl)',
            'Urin lengkap',
            'Urinalisis 24 jam',
            'USG ginjal',
            'BNO/IVP',
            'Diet rendah protein',
            'Pembatasan cairan',
            'Rujuk hemodialisis',
            'Konsultasi nefrologi',
        ],
        'intervention' => [
            'Edukasi diet ginjal',
            'Edukasi pembatasan cairan',
            'Edukasi pemeriksaan akses HD',
            'Penyesuaian dosis obat sesuai eGFR',
            'Persiapan akses dialisis (AV shunt)',
        ],
        'evaluation' => [
            'Edema berkurang',
            'BAK lancar',
            'eGFR membaik',
            'Tekanan darah terkontrol',
            'Pasien memahami diet ginjal',
        ],
    ],

    // ================================================================
    // U0053 - Fisioterapi (musculoskeletal & rehabilitation)
    // ================================================================
    'U0053' => [
        'subjective' => [
            'Nyeri leher / cervical',
            'Nyeri bahu',
            'Nyeri punggung bawah (LBP)',
            'Nyeri lutut',
            'Kekakuan sendi pagi hari',
            'Kelemahan otot',
            'Sulit berjalan',
            'Post-stroke',
            'Post-operasi tulang',
            'Riwayat jatuh',
            'Saraf kejepit (HNP)',
        ],
        'assessment' => [
            'Cervical syndrome',
            'Frozen shoulder',
            'Low Back Pain (LBP)',
            'Osteoarthritis genu',
            'HNP cervical / lumbal',
            'Stroke (stadium pemulihan)',
            'Bell\'s palsy',
            'Plantar fasciitis',
            'Carpal tunnel syndrome',
            'Post-fraktur (rehabilitasi)',
        ],
        'objective_organ' => [
            'Postur Tubuh', 'Gait (cara berjalan)',
            'Leher / Cervical', 'Bahu Kanan', 'Bahu Kiri',
            'Lengan Atas', 'Siku', 'Pergelangan Tangan',
            'Tulang Belakang', 'Pinggul',
            'Lutut Kanan', 'Lutut Kiri',
            'Pergelangan Kaki',
            'Kekuatan Otot (MMT)', 'ROM Sendi (Range of Motion)',
        ],
        'plan' => [
            'Terapi panas / dingin (hot/cold pack)',
            'TENS (Transcutaneous Electrical Nerve Stimulation)',
            'Ultrasound therapy',
            'Manual therapy / mobilisasi sendi',
            'Latihan penguatan otot',
            'Latihan ROM',
            'Edukasi ergonomi',
            'Edukasi posisi kerja',
            'Home exercise program',
        ],
        'intervention' => [
            'Pemberian TENS 15 menit',
            'Pemberian Ultrasound therapy',
            'Hot pack / cold pack 15 menit',
            'Latihan ROM aktif & pasif',
            'Latihan penguatan otot bertahap',
            'Edukasi postur & ergonomi',
        ],
        'evaluation' => [
            'Nyeri berkurang (skala VAS turun)',
            'ROM membaik',
            'Kekuatan otot bertambah',
            'Pasien dapat melakukan ADL mandiri',
            'Pasien memahami home exercise',
        ],
    ],

];
