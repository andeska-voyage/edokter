<?php
/**
 * conf/templateinap.php
 *
 * Template quick-fill SOAPIE — per kd_sps (KODE SPESIALIS DOKTER LOGIN).
 * Dipakai di pemeriksaaninap.php (SOAPIE Rawat Inap).
 *
 * STRUKTUR:
 *   - '__default__' : template dasar yang berlaku untuk SEMUA dokter spesialis
 *   - '<kd_sps>'    : template tambahan spesifik per spesialis
 *
 * Untuk pasien rawat inap, kunci pemilihan template = kd_sps dokter yang login,
 * BUKAN kd_poli pasien (karena pasien ranap diperiksa oleh dokter spesialis).
 *
 * 10 SPESIALIS yang tersedia di tabel `spesialis`:
 *   S0004 Radiologi
 *   S0006 Bedah
 *   S0007 Syaraf
 *   S0008 Anastesi
 *   S0009 THT
 *   S0010 Mata
 *   S0011 Anak
 *   S0012 Obsgyn (Obstetri/Ginekologi)
 *   S0013 Dalam (Penyakit Dalam)
 *   S0015 Gigi dan Mulut
 *
 * 6 section SOAPIE:
 *   subjective    (S) - keluhan / kondisi pasien ranap
 *   objective     (O) - hasil pemeriksaan fisik & penunjang harian
 *   assessment    (A) - diagnosa, penilaian klinis perkembangan
 *   plan          (P) - rencana tindakan, terapi, follow-up
 *   intervention  (I) - instruksi/implementasi yang dilakukan harian
 *   evaluation    (E) - evaluasi hasil tindakan, respon pasien, disposisi
 */

return [

    // ================================================================
    // DEFAULT — berlaku untuk semua spesialis (base template ranap)
    // ================================================================
    '__default__' => [

        'subjective' => [
            // Konteks ranap — perkembangan harian
            'Pasien sadar penuh, kooperatif',
            'Keluhan utama berkurang dibanding hari sebelumnya',
            'Keluhan utama menetap',
            'Demam belum turun',
            'Demam sudah turun',
            'Nyeri ... (skala VAS ...)',
            'Sesak napas berkurang',
            'Mual dan muntah berkurang',
            'BAB ... kali sehari',
            'BAK lancar',
            'Sulit BAK',
            'Tidak nafsu makan',
            'Nafsu makan membaik',
            'Sulit tidur',
            'Riwayat alergi (-)',
        ],

        'objective' => [
            'KU: tampak sakit ringan / sedang / berat',
            'Kesadaran compos mentis, GCS E4V5M6',
            'Konjungtiva tidak anemis, sklera tidak ikterik',
            'Pulmo: vesikuler +/+, ronki -/-, wheezing -/-',
            'Cor: BJ I-II reguler, murmur (-), gallop (-)',
            'Abdomen: supel, BU normal, nyeri tekan (-)',
            'Ekstremitas: akral hangat, edema (-)',
        ],

        'assessment' => [
            'Stabil, dalam observasi',
            'Perbaikan klinis',
            'Belum ada perbaikan',
            'Memburuk, perlu evaluasi lanjut',
            'Ready for discharge',
            'Perlu konsultasi spesialis',
        ],

        'plan' => [
            'Lanjutkan terapi',
            'Ganti antibiotik sesuai sensitivitas',
            'Tapering off steroid',
            'Mobilisasi bertahap',
            'Diet bertahap (clear → soft → biasa)',
            'Cek lab ulang ... hari',
            'Rontgen evaluasi',
            'Rencana pulang ... hari lagi',
            'Rencana operasi',
            'Konsul Sp. ...',
        ],

        'intervention' => [
            // INSTRUKSI ranap
            'Pemberian obat IV sesuai resep',
            'Pemberian obat oral sesuai resep',
            'Cairan IV: ... (... tpm)',
            'Pemberian oksigen ... lpm via nasal kanul',
            'Observasi tanda vital tiap ... jam',
            'Cek GDS tiap ... jam',
            'Monitoring intake-output cairan',
            'Bedrest total / mobilisasi terbatas',
            // IMPLEMENTASI tindakan
            'Pemasangan infus / IV line',
            'Pemasangan kateter urin',
            'Wound care / perawatan luka',
            'Aff infus',
            'Aff kateter urin',
            'Aff hecting',
            // Edukasi
            'Edukasi pasien & keluarga',
            'Edukasi minum obat post-op',
            'Edukasi tanda bahaya pasca rawat',
            'Informed consent diberikan',
        ],

        'evaluation' => [
            'Keluhan berkurang',
            'Tanda vital stabil',
            'Hasil lab membaik',
            'Pasien dapat mobilisasi',
            'Belum ada perbaikan, terapi dilanjutkan',
            'Komplikasi belum ada',
            'Rencana pulang besok',
            'Pasien dapat dipulangkan',
            'Pasien dipindahkan ke ICU',
            'Pasien rujuk RS lain',
            'Pasien meninggal',
        ],
    ],

    // ================================================================
    // S0013 - Sp. Penyakit Dalam (Internis)
    // ================================================================
    'S0013' => [
        'subjective' => [
            'Nyeri ulu hati',
            'BAB hitam (melena)',
            'BAK pekat / oliguria',
            'Sesak saat aktivitas / istirahat',
            'Bengkak tungkai',
            'Lemas, pusing',
            'Riwayat hipertensi',
            'Riwayat DM',
            'Riwayat hemodialisa',
        ],
        'assessment' => [
            'CKD (Chronic Kidney Disease) on HD',
            'Acute Kidney Injury',
            'CHF (Congestive Heart Failure) NYHA II-IV',
            'Hipertensi krisis terkontrol',
            'DM tipe 2 dengan komplikasi',
            'Sirosis hepatis',
            'Stroke iskemik / hemoragik',
            'Sepsis',
            'Anemia gravis',
        ],
        'objective_organ' => [
            'Kepala', 'Mata', 'Leher (JVP)', 'Thoraks', 'Cor', 'Pulmo',
            'Abdomen', 'Hepar', 'Lien', 'Ginjal', 'Ekstremitas', 'Edema',
        ],
        'plan' => [
            'Cek lab harian (DR, ureum, kreatinin)',
            'Cek elektrolit',
            'Hemodialisa',
            'Transfusi PRC ... kantong',
            'Diet rendah protein',
            'Diet DM',
            'Diet jantung',
            'Konsul Sp.JP / Sp.PD subspesialis',
        ],
        'intervention' => [
            'Pemberian insulin SC sesuai sliding scale',
            'Pemberian diuretik IV',
            'Hemodialisa ... jam',
            'Transfusi PRC ... kantong',
            'Pemasangan akses HD (CDL)',
            'Edukasi diet ginjal / jantung / DM',
        ],
        'evaluation' => [
            'GDS terkontrol',
            'Edema berkurang',
            'Ureum / kreatinin membaik',
            'TD terkontrol',
            'Pasien stabil pasca HD',
            'Konsul jantung dilakukan',
        ],
    ],

    // ================================================================
    // S0011 - Sp. Anak (Pediatrik)
    // ================================================================
    'S0011' => [
        'subjective' => [
            'Demam tinggi sejak ... hari',
            'Rewel, sulit tidur',
            'Tidak mau menyusu / makan',
            'BAB cair ... kali sehari',
            'Muntah berulang',
            'Batuk pilek',
            'Sesak napas',
            'Kejang',
            'Riwayat imunisasi lengkap / belum lengkap',
        ],
        'assessment' => [
            'DBD (DHF) grade I-IV',
            'Gastroenteritis akut dengan dehidrasi',
            'Bronkopneumonia',
            'Bronkiolitis',
            'Demam tifoid',
            'Kejang demam sederhana / kompleks',
            'Sepsis neonatorum',
            'BBLR (Berat Badan Lahir Rendah)',
            'Asma bronkial eksaserbasi',
        ],
        'objective_organ' => [
            'UUB', 'Kepala', 'Mata', 'Leher', 'Thoraks', 'Cor', 'Pulmo',
            'Abdomen', 'Ekstremitas', 'Turgor Kulit', 'Status Hidrasi',
        ],
        'plan' => [
            'Cek darah lengkap + trombosit serial',
            'Cek hematokrit serial (DBD)',
            'Cek elektrolit',
            'Foto thoraks',
            'Rehidrasi cairan',
            'Antibiotik empiris',
            'Konsul Sp.A subspesialis',
        ],
        'intervention' => [
            'Rehidrasi RL ... cc dalam ... jam',
            'Pemberian antipiretik (paracetamol IV)',
            'Pemberian antibiotik IV',
            'Nebulizer ... menit (kombinasi salbutamol + budesonide)',
            'Oksigen ... lpm via nasal kanul',
            'Kompres hangat',
            'Edukasi orang tua',
        ],
        'evaluation' => [
            'Demam turun, anak lebih aktif',
            'Diare berkurang, dehidrasi membaik',
            'Sesak berkurang, SpO2 membaik',
            'Trombosit naik (DBD)',
            'Hematokrit normal',
            'Anak mau menyusu / makan',
            'Orang tua memahami edukasi',
        ],
    ],

    // ================================================================
    // S0006 - Sp. Bedah
    // ================================================================
    'S0006' => [
        'subjective' => [
            'Nyeri post-op',
            'Luka operasi nyeri / berair',
            'Demam pasca operasi',
            'Mual muntah pasca anestesi',
            'Belum BAB / flatus pasca operasi',
            'Drain ... cc / hari',
            'Riwayat operasi: ...',
        ],
        'assessment' => [
            'Post operasi laparotomi (hari ke-...)',
            'Post operasi appendektomi',
            'Post operasi herniorrhaphy',
            'Post operasi mastektomi',
            'Post operasi ORIF',
            'Akut abdomen, suspect ...',
            'Hernia inkarserata',
            'Apendisitis akut',
            'Cholelithiasis',
            'Trauma abdomen / thoraks',
        ],
        'objective_organ' => [
            'Status Generalis', 'Luka Operasi', 'Tanda Infeksi',
            'Drain', 'Hecting', 'Kateter', 'Bising Usus',
            'Massa / Benjolan', 'Abdomen', 'Inguinal', 'Anorektal', 'Ekstremitas',
        ],
        'plan' => [
            'Ganti verban ... hari sekali',
            'Aff drain bila produksi < 30 cc/24 jam',
            'Aff kateter post-op hari ...',
            'Aff hecting hari ke-7-10',
            'Mobilisasi bertahap',
            'Diet bertahap (clear → soft → biasa)',
            'Antibiotik IV ... hari',
            'Analgetik post-op',
        ],
        'intervention' => [
            'Wound care steril',
            'Aff drain',
            'Aff hecting',
            'Aff kateter urin',
            'Pemberian antibiotik IV',
            'Pemberian analgetik IV',
            'Mobilisasi miring kanan-kiri',
            'Edukasi perawatan luka di rumah',
        ],
        'evaluation' => [
            'Luka kering, hecting intak',
            'Tidak ada tanda infeksi',
            'Drain produksi <30 cc, dapat di-aff',
            'Pasien sudah flatus / BAB',
            'Mobilisasi mandiri',
            'Pasien dapat dipulangkan',
        ],
    ],

    // ================================================================
    // S0012 - Sp. Obsgyn (Obstetri / Ginekologi)
    // ================================================================
    'S0012' => [
        'subjective' => [
            'HPHT: ...',
            'Usia kehamilan ... minggu',
            'Mules / kontraksi',
            'Keluar lendir darah',
            'Ketuban pecah',
            'Pergerakan janin aktif / berkurang',
            'Riwayat persalinan: G..P..A..',
            'Post partum hari ke-...',
            'Pengeluaran pervaginam / lokia',
            'ASI lancar / tidak',
        ],
        'assessment' => [
            'G..P..A.. usia kehamilan ... minggu',
            'Inpartu kala I/II/III/IV',
            'Post SC hari ke-...',
            'Post partum spontan hari ke-...',
            'Pre-eklampsia ringan / berat',
            'Eklampsia',
            'Perdarahan post partum',
            'Abortus inkomplit / komplit',
            'KET (Kehamilan Ektopik Terganggu)',
        ],
        'objective_organ' => [
            'Kepala', 'Mata', 'Thoraks', 'Mammae', 'Abdomen',
            'TFU', 'DJJ', 'His / Kontraksi', 'Letak Janin',
            'Vulva/Vagina', 'Inspekulo', 'VT (Vaginal Toucher)',
            'Lokia', 'Luka Operasi (post SC)', 'Ekstremitas', 'Edema',
        ],
        'plan' => [
            'CTG (Cardiotocography)',
            'USG kehamilan',
            'Cek lab darah lengkap, urin',
            'Pemberian oksitosin drip',
            'Pemberian MgSO4 (eklampsia)',
            'Rencana SC',
            'Rencana persalinan pervaginam',
            'Pemulangan post partum hari ke-...',
        ],
        'intervention' => [
            'CTG',
            'Persalinan pervaginam',
            'SC (Sectio Caesarea) elektif / cito',
            'Curretage',
            'Pemberian oksitosin IV',
            'Pemberian MgSO4 IV',
            'Pemberian transfusi PRC ... kantong',
            'Edukasi ASI eksklusif',
            'Edukasi KB pasca persalinan',
        ],
        'evaluation' => [
            'Persalinan lancar, ibu & bayi stabil',
            'TD terkontrol (pre-eklampsia)',
            'Perdarahan terkontrol',
            'Lokia normal',
            'ASI lancar',
            'Luka SC kering',
            'Pasien dapat dipulangkan',
        ],
    ],

    // ================================================================
    // S0007 - Sp. Syaraf (Neurologi)
    // ================================================================
    'S0007' => [
        'subjective' => [
            'Kelemahan anggota gerak ...',
            'Wajah perot (asimetri)',
            'Sulit bicara (afasia/disartria)',
            'Sakit kepala hebat',
            'Kejang ... kali sehari',
            'Penurunan kesadaran',
            'Vertigo',
            'Kesemutan',
            'Sulit menelan',
        ],
        'assessment' => [
            'Stroke iskemik akut',
            'Stroke hemoragik (ICH / SAH)',
            'Stroke in evolution',
            'TIA (Transient Ischemic Attack)',
            'Status epileptikus',
            'Meningitis bakterial / TB / virus',
            'Encephalitis',
            'GBS (Guillain-Barré Syndrome)',
            'Neuralgia trigeminal',
        ],
        'objective_organ' => [
            'GCS', 'Pupil', 'Nervus Kranialis (I-XII)',
            'Motorik Atas Kanan', 'Motorik Atas Kiri',
            'Motorik Bawah Kanan', 'Motorik Bawah Kiri',
            'Sensorik', 'Refleks Fisiologis', 'Refleks Patologis',
            'Tanda Rangsang Meningeal', 'Cerebellum',
        ],
        'plan' => [
            'CT scan kepala non-kontras',
            'MRI kepala',
            'EEG',
            'Lumbar puncture',
            'Cek fungsi ginjal & elektrolit',
            'Pemberian rTPA (stroke iskemik)',
            'Trombolisis / antikoagulan',
            'Antikonvulsan',
            'Fisioterapi neurologi',
        ],
        'intervention' => [
            'Pemasangan NGT (sulit menelan)',
            'Suction berkala',
            'Pemberian antikonvulsan IV',
            'Pemberian manitol IV (ICH)',
            'Mobilisasi pasif',
            'Edukasi keluarga: tanda bahaya stroke',
            'Edukasi pencegahan stroke',
        ],
        'evaluation' => [
            'GCS membaik',
            'Kekuatan motorik membaik',
            'Kejang terkontrol',
            'Kesadaran kembali',
            'Pasien dapat menelan',
            'Stabil hemodinamik',
        ],
    ],

    // ================================================================
    // S0010 - Sp. Mata
    // ================================================================
    'S0010' => [
        'subjective' => [
            'Mata merah & nyeri',
            'Penglihatan menurun mendadak',
            'Trauma mata',
            'Post-op katarak / vitrektomi',
            'Belekan banyak',
            'Silau saat melihat cahaya',
        ],
        'assessment' => [
            'Glaukoma akut',
            'Uveitis',
            'Endoftalmitis',
            'Trauma mata (perforans / tumpul)',
            'Post operasi katarak (hari ke-...)',
            'Post operasi vitrektomi',
            'Ablatio retina',
            'CRAO (Central Retinal Artery Occlusion)',
        ],
        'objective_organ' => [
            'Visus', 'Palpebra', 'Konjungtiva', 'Sklera',
            'Kornea', 'COA', 'Pupil', 'Lensa', 'TIO', 'Funduskopi',
        ],
        'plan' => [
            'Pemeriksaan visus & refraksi',
            'Slit lamp',
            'Tonometri',
            'Funduskopi',
            'USG mata',
            'Antibiotik tetes',
            'Anti-glaukoma (timolol, acetazolamide)',
            'Steroid topikal',
        ],
        'intervention' => [
            'Pemberian obat tetes mata',
            'Salep mata',
            'Bebat mata',
            'Edukasi pemakaian obat',
            'Edukasi posisi tidur post-op vitrektomi',
        ],
        'evaluation' => [
            'TIO terkontrol',
            'Mata merah berkurang',
            'Visus membaik',
            'Tidak ada infeksi sekunder',
        ],
    ],

    // ================================================================
    // S0009 - Sp. THT
    // ================================================================
    'S0009' => [
        'subjective' => [
            'Nyeri telinga hebat',
            'Pendengaran menurun mendadak',
            'Perdarahan hidung (epistaksis)',
            'Sulit menelan',
            'Suara serak menetap',
            'Sumbatan jalan napas',
            'Post-op tonsilektomi (hari ke-...)',
        ],
        'assessment' => [
            'OMSK (Otitis Media Supuratif Kronis) eksaserbasi',
            'Mastoiditis',
            'Tonsilofaringitis akut',
            'Peritonsillar abscess',
            'Epistaksis profusa',
            'Sumbatan benda asing',
            'Karsinoma laring / nasofaring',
            'Post operasi tonsilektomi',
            'Sudden deafness',
        ],
        'objective_organ' => [
            'Telinga Kanan', 'Telinga Kiri',
            'Membran Timpani Kanan', 'Membran Timpani Kiri',
            'Hidung / Cavum Nasi', 'Sinus Paranasal',
            'Faring', 'Tonsil', 'Laring',
            'Kelenjar Getah Bening Leher',
        ],
        'plan' => [
            'Audiometri',
            'Endoskopi nasal / laring',
            'CT scan sinus / temporal',
            'Pemberian antibiotik IV',
            'Tampon hidung anterior / posterior',
            'Pemberian analgesik',
            'Diet halus / cair (post tonsilektomi)',
        ],
        'intervention' => [
            'Tampon hidung anterior',
            'Tampon hidung posterior',
            'Suction telinga',
            'Cuci telinga',
            'Insisi & drainase abses peritonsil',
            'Edukasi cara cuci hidung',
            'Edukasi pasca tonsilektomi',
        ],
        'evaluation' => [
            'Perdarahan hidung berhenti',
            'Nyeri telinga berkurang',
            'Pendengaran membaik',
            'Luka post-op kering',
            'Pasien dapat menelan',
        ],
    ],

    // ================================================================
    // S0015 - Sp. Gigi dan Mulut
    // ================================================================
    'S0015' => [
        'subjective' => [
            'Nyeri gigi / wajah hebat',
            'Pembengkakan wajah',
            'Demam',
            'Sulit membuka mulut (trismus)',
            'Sulit menelan',
            'Riwayat trauma maksilofasial',
            'Post-op odontektomi',
        ],
        'assessment' => [
            'Abses periapikal',
            'Abses submandibular',
            'Ludwig\'s angina',
            'Selulitis fasial',
            'Fraktur mandibula / maksila',
            'Post operasi odontektomi M3',
            'Karsinoma oral',
        ],
        'objective_organ' => [
            'Wajah / Ekstra Oral', 'Bibir', 'Mukosa Mulut',
            'Gusi', 'Gigi Geligi', 'Lidah', 'Palatum',
            'Dasar Mulut', 'KGB Submandibula', 'Trismus (pembukaan mulut)',
        ],
        'plan' => [
            'Foto panoramik / CT scan maksilofasial',
            'Antibiotik IV (golongan beta-laktam + metronidazole)',
            'Insisi & drainase abses',
            'Odontektomi gigi penyebab',
            'Diet cair / lunak',
            'Konsul anestesi pre-op',
        ],
        'intervention' => [
            'Insisi & drainase abses',
            'Pemasangan drain',
            'Ekstraksi gigi',
            'Odontektomi',
            'Pemberian antibiotik IV',
            'Wound care intra-oral',
            'Edukasi oral hygiene',
        ],
        'evaluation' => [
            'Pembengkakan berkurang',
            'Nyeri berkurang',
            'Trismus membaik',
            'Drain produksi minimal',
            'Pasien dapat makan',
        ],
    ],

    // ================================================================
    // S0008 - Sp. Anestesi
    // ================================================================
    'S0008' => [
        'subjective' => [
            'Pre-op: rencana operasi ...',
            'Riwayat penyakit jantung / paru / DM / hipertensi',
            'Riwayat alergi obat anestesi',
            'Riwayat operasi sebelumnya',
            'Post-op: pasien kembali sadar',
            'Mual muntah pasca anestesi',
            'Nyeri post-op (skala VAS ...)',
        ],
        'assessment' => [
            'Pre-op evaluasi ASA I-IV',
            'Post anestesi umum',
            'Post anestesi regional (spinal / epidural)',
            'Post anestesi blok perifer',
            'PONV (Post-Operative Nausea and Vomiting)',
            'Nyeri post-op terkontrol / tidak terkontrol',
            'Komplikasi anestesi',
        ],
        'objective_organ' => [
            'Airway', 'Breathing', 'Circulation', 'Disability (GCS)',
            'Akses IV', 'Akses Arteri', 'Akses Epidural',
            'Mallampati (pre-op)', 'Skala Nyeri (VAS)', 'Mobilisasi Pasca Anestesi',
        ],
        'plan' => [
            'Pre-op assessment ASA',
            'Pemberian premedikasi',
            'Pemilihan teknik anestesi',
            'Cek lab pre-op (DR, fungsi hati, ginjal, koagulasi)',
            'EKG pre-op',
            'Foto thoraks pre-op',
            'Konsul Sp.PD / Sp.JP pre-op (bila perlu)',
            'Post-op pain management (PCA / kontinu)',
        ],
        'intervention' => [
            'Pre-op visite',
            'Pemberian premedikasi',
            'Induksi anestesi umum',
            'Anestesi regional (spinal / epidural)',
            'Blok saraf perifer',
            'Pemasangan akses arteri',
            'Pemasangan kateter epidural',
            'Manajemen jalan napas (intubasi / LMA)',
            'Post-op pain management',
        ],
        'evaluation' => [
            'Airway clear, ventilasi adekuat',
            'Hemodinamik stabil intra-operatif',
            'Pasien sadar penuh post-op',
            'Nyeri terkontrol (VAS < 4)',
            'PONV teratasi',
            'Mobilisasi pasca anestesi: ...',
            'Aldrette score 9-10, dapat keluar RR',
        ],
    ],

    // ================================================================
    // S0004 - Sp. Radiologi
    // ================================================================
    'S0004' => [
        'subjective' => [
            'Pasien rujukan dari ruangan ...',
            'Permintaan pemeriksaan radiologi',
            'Pasien dengan implan logam',
            'Pasien dengan klaustrofobia (untuk MRI)',
            'Riwayat alergi kontras',
        ],
        'assessment' => [
            'Pemeriksaan foto thoraks',
            'Pemeriksaan CT scan kepala / abdomen / thoraks',
            'Pemeriksaan MRI',
            'Pemeriksaan USG abdomen / obstetri',
            'Pemeriksaan BNO/IVP',
            'Pemeriksaan dengan kontras',
        ],
        'objective_organ' => [
            'Thoraks', 'Abdomen', 'Kepala', 'Tulang Belakang',
            'Pelvis', 'Ekstremitas', 'Akses Vena (untuk kontras)',
        ],
        'plan' => [
            'Foto thoraks PA / lateral',
            'CT scan dengan kontras / non-kontras',
            'MRI dengan/tanpa kontras',
            'USG abdomen',
            'BNO/IVP',
            'Hasil dikirim ke ruangan / DPJP',
        ],
        'intervention' => [
            'Pemasangan IV line untuk kontras',
            'Pemberian kontras IV',
            'Pemeriksaan radiologi sesuai permintaan',
            'Edukasi prosedur radiologi',
            'Edukasi pasca kontras (banyak minum)',
        ],
        'evaluation' => [
            'Pemeriksaan selesai',
            'Tidak ada reaksi kontras',
            'Hasil dikirim ke DPJP',
            'Pasien kembali ke ruangan',
        ],
    ],

];
