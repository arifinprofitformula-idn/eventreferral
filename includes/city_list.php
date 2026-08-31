<?php
/**
 * includes/city_list.php
 * Daftar kanonik kota/kabupaten Indonesia + normalisasi input "Kota Domisili" — dipakai di
 * SEMUA titik input yang mengumpulkan field ini (form pendaftaran utama, template event,
 * form konfirmasi kehadiran walk-in) supaya data di leads.kota / event_attendance konsisten
 * dan halaman Insight Peserta (admin/event-insights.php) tidak pecah jadi banyak varian
 * penulisan untuk kota yang sama (mis. "Jakarta" / "JKT" / "Jkrt").
 *
 * Daftar ini TIDAK klaim lengkap 514 kabupaten/kota — cukup mencakup kota besar & menengah
 * yang paling sering muncul. Kota di luar daftar TETAP diterima apa adanya (title-cased),
 * tidak ditolak — datalist hanya membantu pengetikan, bukan whitelist yang mengunci input.
 * Boleh ditambah kapan saja dengan menambah baris ke array di bawah.
 *
 * Untuk kota yang punya pasangan Kota & Kabupaten senama (mis. Bandung, Bogor, Semarang) —
 * keduanya dicantumkan EKSPLISIT sebagai "Kota X" / "Kabupaten X" supaya tidak tertukar,
 * karena keduanya wilayah berbeda dan sama-sama relevan untuk perencanaan event offline.
 */

/** @return string[] Daftar nama kota/kabupaten kanonik (tidak perlu urut, akan di-sort saat dipakai). */
function indonesia_city_list(): array {
    return [
        // DKI Jakarta
        'Jakarta', 'Jakarta Pusat', 'Jakarta Utara', 'Jakarta Barat', 'Jakarta Selatan', 'Jakarta Timur',

        // Banten
        'Kota Tangerang', 'Kabupaten Tangerang', 'Tangerang Selatan', 'Kota Serang', 'Kabupaten Serang', 'Cilegon', 'Kabupaten Pandeglang', 'Kabupaten Lebak',

        // Jawa Barat
        'Kota Bandung', 'Kabupaten Bandung', 'Kabupaten Bandung Barat', 'Kota Bekasi', 'Kabupaten Bekasi', 'Kota Bogor', 'Kabupaten Bogor', 'Depok',
        'Kota Cirebon', 'Kabupaten Cirebon', 'Kota Sukabumi', 'Kabupaten Sukabumi', 'Kota Tasikmalaya', 'Kabupaten Tasikmalaya', 'Cimahi', 'Banjar',
        'Kabupaten Karawang', 'Kabupaten Purwakarta', 'Kabupaten Subang', 'Kabupaten Sumedang', 'Kabupaten Garut', 'Kabupaten Ciamis',
        'Kabupaten Cianjur', 'Kabupaten Indramayu', 'Kabupaten Majalengka', 'Kabupaten Kuningan', 'Kabupaten Pangandaran',

        // Jawa Tengah
        'Kota Semarang', 'Kabupaten Semarang', 'Surakarta', 'Salatiga', 'Kota Magelang', 'Kabupaten Magelang',
        'Kota Pekalongan', 'Kabupaten Pekalongan', 'Kota Tegal', 'Kabupaten Tegal', 'Kabupaten Klaten', 'Kabupaten Sukoharjo',
        'Kabupaten Boyolali', 'Kabupaten Sragen', 'Kabupaten Karanganyar', 'Kabupaten Wonogiri', 'Kabupaten Kudus', 'Kabupaten Jepara',
        'Kabupaten Demak', 'Kabupaten Pati', 'Kabupaten Rembang', 'Kabupaten Blora', 'Kabupaten Grobogan', 'Kabupaten Kendal',
        'Kabupaten Purworejo', 'Kabupaten Kebumen', 'Kabupaten Banyumas', 'Kabupaten Cilacap', 'Kabupaten Purbalingga', 'Kabupaten Banjarnegara',
        'Kabupaten Wonosobo', 'Kabupaten Temanggung', 'Kabupaten Batang', 'Kabupaten Pemalang', 'Kabupaten Brebes',

        // DI Yogyakarta
        'Yogyakarta', 'Kabupaten Sleman', 'Kabupaten Bantul', 'Kabupaten Kulon Progo', 'Kabupaten Gunungkidul',

        // Jawa Timur
        'Surabaya', 'Kota Malang', 'Kabupaten Malang', 'Kota Kediri', 'Kabupaten Kediri', 'Kota Blitar', 'Kabupaten Blitar',
        'Kota Madiun', 'Kabupaten Madiun', 'Kota Mojokerto', 'Kabupaten Mojokerto', 'Kota Pasuruan', 'Kabupaten Pasuruan',
        'Kota Probolinggo', 'Kabupaten Probolinggo', 'Batu', 'Kabupaten Sidoarjo', 'Kabupaten Gresik', 'Kabupaten Jombang',
        'Kabupaten Lamongan', 'Kabupaten Tuban', 'Kabupaten Bojonegoro', 'Kabupaten Nganjuk', 'Kabupaten Ngawi', 'Kabupaten Magetan',
        'Kabupaten Ponorogo', 'Kabupaten Pacitan', 'Kabupaten Trenggalek', 'Kabupaten Tulungagung', 'Kabupaten Banyuwangi',
        'Kabupaten Jember', 'Kabupaten Bondowoso', 'Kabupaten Situbondo', 'Kabupaten Lumajang', 'Kabupaten Bangkalan',
        'Kabupaten Sampang', 'Kabupaten Pamekasan', 'Kabupaten Sumenep',

        // Bali & Nusa Tenggara
        'Denpasar', 'Kabupaten Badung', 'Kabupaten Gianyar', 'Kabupaten Tabanan', 'Kabupaten Buleleng', 'Kabupaten Klungkung', 'Kabupaten Karangasem',
        'Mataram', 'Kabupaten Lombok Barat', 'Kabupaten Lombok Tengah', 'Kabupaten Lombok Timur', 'Kota Bima', 'Kabupaten Bima', 'Kabupaten Sumbawa',
        'Kupang', 'Kabupaten Kupang', 'Kabupaten Sikka', 'Kabupaten Ende', 'Kabupaten Manggarai',

        // Sumatera
        'Banda Aceh', 'Langsa', 'Lhokseumawe', 'Sabang', 'Subulussalam', 'Kabupaten Aceh Besar',
        'Medan', 'Binjai', 'Tebing Tinggi', 'Pematangsiantar', 'Tanjungbalai', 'Sibolga', 'Padangsidimpuan', 'Gunungsitoli',
        'Kabupaten Deli Serdang', 'Kabupaten Langkat', 'Kabupaten Simalungun', 'Kabupaten Karo',
        'Padang', 'Bukittinggi', 'Padang Panjang', 'Payakumbuh', 'Sawahlunto', 'Solok', 'Pariaman',
        'Pekanbaru', 'Dumai', 'Kabupaten Kampar', 'Kabupaten Siak',
        'Batam', 'Tanjungpinang',
        'Jambi', 'Sungai Penuh',
        'Palembang', 'Lubuklinggau', 'Pagar Alam', 'Prabumulih', 'Kabupaten Ogan Ilir', 'Kabupaten Musi Banyuasin',
        'Pangkalpinang', 'Bengkulu', 'Bandar Lampung', 'Metro', 'Kabupaten Lampung Selatan', 'Kabupaten Lampung Tengah',

        // Kalimantan
        'Pontianak', 'Singkawang', 'Kabupaten Kubu Raya', 'Palangka Raya', 'Banjarmasin', 'Banjarbaru', 'Kabupaten Banjar',
        'Samarinda', 'Balikpapan', 'Bontang', 'Kabupaten Kutai Kartanegara', 'Tarakan', 'Kabupaten Bulungan',

        // Sulawesi
        'Manado', 'Bitung', 'Tomohon', 'Kotamobagu', 'Gorontalo', 'Kabupaten Gorontalo', 'Palu', 'Kabupaten Sigi', 'Kabupaten Donggala',
        'Mamuju', 'Makassar', 'Parepare', 'Palopo', 'Kabupaten Gowa', 'Kabupaten Maros', 'Kabupaten Bone', 'Kabupaten Bulukumba',
        'Kendari', 'Baubau', 'Kabupaten Konawe',

        // Maluku & Papua
        'Ambon', 'Tual', 'Ternate', 'Tidore Kepulauan', 'Jayapura', 'Sorong', 'Kabupaten Merauke', 'Kabupaten Jayawijaya', 'Manokwari',
    ];
}

/** Alias/singkatan umum -> nama kanonik (lowercase key). Fokus di kota-kota yang paling sering disingkat/typo. */
function indonesia_city_alias_map(): array {
    return [
        'jkt' => 'Jakarta',
        'jakarta raya' => 'Jakarta',
        'dki jakarta' => 'Jakarta',
        'dki' => 'Jakarta',
        'jakpus' => 'Jakarta Pusat',
        'jakut' => 'Jakarta Utara',
        'jakbar' => 'Jakarta Barat',
        'jaksel' => 'Jakarta Selatan',
        'jaktim' => 'Jakarta Timur',
        'bdg' => 'Kota Bandung',
        'sby' => 'Surabaya',
        'surabaya kota' => 'Surabaya',
        'smg' => 'Kota Semarang',
        'jogja' => 'Yogyakarta',
        'jogjakarta' => 'Yogyakarta',
        'yogya' => 'Yogyakarta',
        'yogyakarta kota' => 'Yogyakarta',
        'solo' => 'Surakarta',
        'mks' => 'Makassar',
        'ujung pandang' => 'Makassar',
        'plg' => 'Palembang',
        'tgr' => 'Kota Tangerang',
        'tangsel' => 'Tangerang Selatan',
        'bpp' => 'Balikpapan',
        'pku' => 'Pekanbaru',
        'btm' => 'Batam',
        'bjm' => 'Banjarmasin',
        'dps' => 'Denpasar',
        'mlg' => 'Kota Malang',
        'bks' => 'Kota Bekasi',
        'bgr' => 'Kota Bogor',
        'kpg' => 'Kupang',
        'bdl' => 'Bandar Lampung',
        'lampung' => 'Bandar Lampung',
    ];
}

/**
 * Normalisasi input kota bebas teks menjadi nama kanonik kalau cocok (alias atau daftar
 * resmi), atau title-case apa adanya kalau tidak ditemukan (TIDAK ditolak — kota di luar
 * daftar tetap valid, cuma tidak dirapikan casing/ejaannya).
 */
function normalize_city_name(string $raw): string {
    $raw = trim(preg_replace('/\s+/', ' ', (string)$raw) ?? '');
    if ($raw === '') {
        return '';
    }

    $lookupKey = mb_strtolower($raw, 'UTF-8');

    $aliasMap = indonesia_city_alias_map();
    if (isset($aliasMap[$lookupKey])) {
        return $aliasMap[$lookupKey];
    }

    static $canonicalLookup = null;
    if ($canonicalLookup === null) {
        $canonicalLookup = [];
        foreach (indonesia_city_list() as $city) {
            $canonicalLookup[mb_strtolower($city, 'UTF-8')] = $city;
        }
    }
    if (isset($canonicalLookup[$lookupKey])) {
        return $canonicalLookup[$lookupKey];
    }

    // Coba tanpa prefix "kota "/"kabupaten "/"kab " — berguna untuk kota yang TIDAK
    // punya pasangan kota/kabupaten senama (yang ambigu sudah didaftar eksplisit di atas,
    // jadi bare-name-nya sengaja tidak ada di canonicalLookup dan tidak akan ke-strip salah).
    $stripped = trim((string)preg_replace('/^(kota|kabupaten|kab\.?)\s+/i', '', $raw));
    $strippedKey = mb_strtolower($stripped, 'UTF-8');
    if ($strippedKey !== '' && $strippedKey !== $lookupKey && isset($canonicalLookup[$strippedKey])) {
        return $canonicalLookup[$strippedKey];
    }

    return mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
}

/** Render <option> untuk <datalist> dari daftar kota kanonik, sudah escaped & urut abjad. */
function render_city_datalist_options(): string {
    $cities = indonesia_city_list();
    sort($cities, SORT_STRING | SORT_FLAG_CASE);
    $html = '';
    foreach ($cities as $city) {
        $html .= '<option value="' . htmlspecialchars($city, ENT_QUOTES, 'UTF-8') . '"></option>';
    }
    return $html;
}
