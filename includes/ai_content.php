<?php
/**
 * includes/ai_content.php
 * Wrapper pemanggilan provider AI untuk generate copywriting event.
 * Mendukung multi-format caption dan generate ulang per gaya copywriting.
 */

const AI_CONTENT_STYLES = [
    'Storytelling Emosional',
    'Direct & Ambisius',
    'FOMO Halus',
    'Edukatif & Kredibel',
    'Santai & Relatable',
];

const AI_CONTENT_FORMATS = ['whatsapp_broadcast', 'whatsapp_status', 'instagram_caption', 'hook_pendek'];
const AI_CONTENT_CTA_TARGETS = ['referral', 'challenge'];

function get_format_instruction(string $format): string
{
    $map = [
        'whatsapp_broadcast' => 'Format target: WhatsApp Broadcast. Buat caption ideal 150-400 karakter, 3-5 paragraf/baris pendek, hangat, mudah discan, emoji secukupnya maksimal 3, dan akhiri CTA jelas.',
        'whatsapp_status' => 'Format target: WhatsApp Status. SANGAT SINGKAT: maksimal 2 baris pendek total di subheadline+description, langsung ke poin utama dan CTA.',
        'instagram_caption' => 'Format target: Caption Instagram. Mulai dengan hook kuat di headline, isi cerita pendek di description, akhiri CTA, lalu tambahkan 3-5 hashtag relevan brand di baris terpisah pada akhir description.',
        'hook_pendek' => 'Format target: Hook Pendek untuk reels/short video. Subheadline dan description boleh string kosong. Headline harus 1 kalimat sangat pendek, maksimal 12 kata, catchy, dan memancing rasa penasaran.',
    ];

    return $map[$format] ?? $map['whatsapp_broadcast'];
}

function normalize_ai_format(string $format): string
{
    return in_array($format, AI_CONTENT_FORMATS, true) ? $format : 'whatsapp_broadcast';
}

function normalize_ai_style(string $styleName): string
{
    return in_array($styleName, AI_CONTENT_STYLES, true) ? $styleName : '';
}

function normalize_ai_cta_target(string $target): string
{
    return in_array($target, AI_CONTENT_CTA_TARGETS, true) ? $target : 'referral';
}

function get_cta_target_instruction(string $target): string
{
    if ($target === 'challenge') {
        return <<<TEXT
Tujuan CTA: AJAK ORANG MENGIKUTI CHALLENGE.
- Fokus copywriting pada ajakan ikut challenge/event utama agar audiens mau membuka halaman challenge.
- CTA harus mengarah ke tindakan mengikuti challenge, contoh: "Ikuti Challenge Sekarang", "Mulai Challenge Hari Ini", "Gabung Challenge".
- Jangan mengajak membuat link referral pada variasi ini.
TEXT;
    }

    return <<<TEXT
Tujuan CTA: AJAK ORANG MEMBUAT LINK REFERRAL.
- Fokus copywriting pada merekrut pengundang, yaitu orang yang akan membuat link referral pribadi lalu mengundang teman-temannya.
- CTA harus mengarahkan orang untuk membuat link referral sendiri, contoh: "Buat Link Referral Saya", "Sebarkan Link Sekarang".
- Jangan mengajak mengikuti challenge sebagai peserta langsung pada variasi ini.
TEXT;
}

function build_marketing_prompt(array $brand, array $event, string $eventTitle, string $customContext, string $inviteLink, string $format = 'whatsapp_broadcast', string $ctaTarget = 'referral'): string
{
    $brandName = $brand['name'] ?? $brand['slug'];
    $themeVibe = ($brand['theme_preset'] ?? 'gold') === 'silver'
        ? 'bersih, modern, accessible, entry point cerdas'
        : 'eksklusif, terpercaya, powerful, high-value';

    $eventDay = $event['event_day'] ?? '';
    $eventTime = $event['event_time'] ?? '';
    $eventLoc = $event['event_location'] ?? '';
    $eventSpeaker = $event['event_speaker'] ?? '';
    $context = trim($customContext) !== ''
        ? "Konteks tambahan dari admin: " . trim($customContext)
        : "Tidak ada konteks tambahan khusus.";
    $format = normalize_ai_format($format);
    $formatInstruction = get_format_instruction($format);
    $ctaTarget = normalize_ai_cta_target($ctaTarget);
    $ctaTargetInstruction = get_cta_target_instruction($ctaTarget);

    return <<<PROMPT
Kamu adalah copywriter profesional untuk brand edukasi finansial "{$brandName}" (vibe: {$themeVibe}).

JUDUL EVENT (WAJIB JADI ACUAN UTAMA, JANGAN KELUAR DARI TEMA INI): "{$eventTitle}"

Tugasmu: buat materi promosi sesuai tujuan CTA di bawah untuk acara dengan judul di atas.

Detail acara:
Hari/Tanggal: {$eventDay}
Waktu: {$eventTime}
Lokasi: {$eventLoc}
Pembicara: {$eventSpeaker}
{$context}

{$formatInstruction}

{$ctaTargetInstruction}

ATURAN KETAT KONTEKS:
- Seluruh headline, subheadline, dan description WAJIB merujuk langsung ke tema/judul event di atas.
- Jangan membuat tema baru, jangan generalisasi ke topik finansial lain di luar judul event ini.
- Jangan mengulang kata judul event secara harfiah di semua variasi — variasikan sudut pandang, tapi tetap satu tema yang sama.

Aturan gaya bahasa:
- Bahasa Indonesia, santai tapi profesional, action-oriented, optimis.
- Tidak boleh membuat klaim keuntungan finansial yang berlebihan atau menjanjikan hasil pasti.
- Jangan gunakan bahasa manipulatif atau tekanan psikologis berlebihan.
- Headline/hook WAJIB dibungkus markdown bold dengan dua bintang, contoh: **Mulai dari Satu Link Referral**.
- Jika format bukan hook pendek, gunakan line break "\\n\\n" di description untuk memisah paragraf pendek.
- Gunakan formatting WhatsApp secukupnya: *tebal* untuk 1 hook utama atau kata kunci penting, jangan berlebihan.
- Tambahkan Facebook symbols/emoji yang humanis di akhir kalimat description, pilih secukupnya dari: ✨, ✅, 💬, 🙌, 🔥, ⭐, 💡, 🚀.
- Jangan menaruh URL di description atau cta_text; link akan ditambahkan sistem dengan awalan 👉.
- Hindari caption panjang, repetitif, dan kalimat pembuka generik seperti "Bayangkan..." jika tidak benar-benar kuat.

Buat TEPAT 5 variasi copywriting dengan gaya berbeda:
1. Storytelling Emosional
2. Direct & Ambisius
3. FOMO Halus
4. Edukatif & Kredibel
5. Santai & Relatable

Balas HANYA dalam format JSON valid (tanpa markdown, tanpa teks lain), dengan struktur persis:
{
  "variations": [
    {
      "style": "nama gaya",
      "headline": "**headline/hook bold**",
      "subheadline": "...",
      "description": "caption sesuai format target, gunakan newline \\n\\n jika perlu",
      "cta_text": "..."
    }
  ]
}
PROMPT;
}

function build_single_style_prompt(array $brand, array $event, string $eventTitle, string $customContext, string $inviteLink, string $format, string $styleName, string $ctaTarget = 'referral'): string
{
    $brandName = $brand['name'] ?? $brand['slug'];
    $themeVibe = ($brand['theme_preset'] ?? 'gold') === 'silver'
        ? 'bersih, modern, accessible, entry point cerdas'
        : 'eksklusif, terpercaya, powerful, high-value';

    $eventDay = $event['event_day'] ?? '';
    $eventTime = $event['event_time'] ?? '';
    $eventLoc = $event['event_location'] ?? '';
    $eventSpeaker = $event['event_speaker'] ?? '';
    $context = trim($customContext) !== ''
        ? "Konteks tambahan dari admin: " . trim($customContext)
        : "Tidak ada konteks tambahan khusus.";
    $format = normalize_ai_format($format);
    $formatInstruction = get_format_instruction($format);
    $ctaTarget = normalize_ai_cta_target($ctaTarget);
    $ctaTargetInstruction = get_cta_target_instruction($ctaTarget);

    return <<<PROMPT
Kamu adalah copywriter profesional untuk brand edukasi finansial "{$brandName}" (vibe: {$themeVibe}).

JUDUL EVENT (WAJIB JADI ACUAN UTAMA): "{$eventTitle}"
Tugasmu: buat SATU variasi materi promosi sesuai tujuan CTA di bawah, dengan gaya KHUSUS: "{$styleName}".

Detail acara:
Hari/Tanggal: {$eventDay}
Waktu: {$eventTime}
Lokasi: {$eventLoc}
Pembicara: {$eventSpeaker}
{$context}

{$formatInstruction}

{$ctaTargetInstruction}

Aturan:
- Bahasa Indonesia, santai-profesional, action-oriented, optimis.
- Jangan membuat klaim keuntungan finansial berlebihan atau menjanjikan hasil pasti.
- Headline/hook WAJIB dibungkus markdown bold dengan dua bintang, contoh: **Mulai dari Satu Link Referral**.
- Tambahkan Facebook symbols/emoji yang humanis di akhir kalimat description, pilih secukupnya dari: ✨, ✅, 💬, 🙌, 🔥, ⭐, 💡, 🚀.
- Jangan menaruh URL di description atau cta_text; link akan ditambahkan sistem dengan awalan 👉.
- WAJIB relevan dengan judul event.
- Buat berbeda dari variasi sebelumnya, tetapi tetap sesuai gaya "{$styleName}".

Balas HANYA JSON valid, struktur persis:
{
  "variation": {
    "style": "{$styleName}",
    "headline": "...",
    "subheadline": "...",
    "description": "...",
    "cta_text": "..."
  }
}
PROMPT;
}

/**
 * Ambil pengaturan provider AI aktif (provider, api_key, model).
 * Sumber utama: tabel `ai_settings` (dikelola lewat admin/ai-settings.php).
 * Jika tabel/baris belum ada, fallback ke konstanta di config.php supaya
 * instalasi lama yang belum menjalankan migrate_v16_ai_settings.sql tetap jalan.
 */
function get_ai_provider_settings(): array
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $defaults = [
        'provider' => strtolower((string)(defined('AI_CONTENT_PROVIDER') ? AI_CONTENT_PROVIDER : 'groq')),
        'api_key' => '',
        'model' => '',
    ];

    try {
        $pdo = get_db(false);
        if ($pdo && table_exists($pdo, 'ai_settings')) {
            $stmt = $pdo->prepare('SELECT provider, api_key, model FROM ai_settings WHERE id = 1');
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $settings = [
                    'provider' => $row['provider'] !== '' ? strtolower($row['provider']) : $defaults['provider'],
                    'api_key' => (string)$row['api_key'],
                    'model' => (string)$row['model'],
                ];
                return $settings;
            }
        }
    } catch (Throwable $e) {
        // Tabel belum ada / query gagal — pakai fallback config.php di bawah.
    }

    $settings = $defaults;
    return $settings;
}

/** Simpan pengaturan provider AI (upsert baris tunggal id=1) ke tabel `ai_settings`. */
function save_ai_provider_settings(string $provider, string $apiKey, string $model): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare('
        INSERT INTO ai_settings (id, provider, api_key, model) VALUES (1, ?, ?, ?)
        ON DUPLICATE KEY UPDATE provider = VALUES(provider), api_key = VALUES(api_key), model = VALUES(model)
    ');
    $stmt->execute([strtolower($provider), $apiKey, $model]);
}

function call_ai_content_provider(string $prompt): string
{
    $settings = get_ai_provider_settings();
    $provider = $settings['provider'] !== '' ? $settings['provider'] : 'groq';

    if ($provider === 'gemini') {
        return call_gemini_api($prompt, $settings['api_key'], $settings['model']);
    }

    if ($provider === 'sumopod') {
        return call_sumopod_api($prompt, $settings['api_key'], $settings['model']);
    }

    if ($provider === 'groq' || $provider === '') {
        return call_groq_api($prompt, $settings['api_key'], $settings['model']);
    }

    throw new RuntimeException('Provider AI "' . $provider . '" tidak dikenal. Gunakan "groq", "gemini", atau "sumopod".');
}

function call_groq_api(string $prompt, string $apiKey = '', string $model = ''): string
{
    $apiKey = $apiKey !== '' ? $apiKey : (defined('GROQ_API_KEY') ? GROQ_API_KEY : '');
    if ($apiKey === '') {
        throw new RuntimeException('API key Groq belum diisi. Atur di halaman Pengaturan AI.');
    }

    $model = $model !== '' ? $model : (defined('GROQ_MODEL') && GROQ_MODEL !== '' ? GROQ_MODEL : 'llama-3.3-70b-versatile');
    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Kamu membalas HANYA dalam format JSON valid, tanpa markdown, tanpa teks tambahan.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => 0.9,
        'response_format' => ['type' => 'json_object'],
    ];

    $data = ai_post_json(
        'https://api.groq.com/openai/v1/chat/completions',
        $payload,
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        'Groq'
    );

    $text = $data['choices'][0]['message']['content'] ?? '';
    if ($text === '') {
        throw new RuntimeException('Respons AI kosong.');
    }

    return $text;
}

function call_sumopod_api(string $prompt, string $apiKey = '', string $model = ''): string
{
    $apiKey = $apiKey !== '' ? $apiKey : (defined('SUMOPOD_API_KEY') ? SUMOPOD_API_KEY : '');
    if ($apiKey === '') {
        throw new RuntimeException('API key SumoPod belum diisi. Atur di halaman Pengaturan AI.');
    }

    $model = $model !== '' ? $model : (defined('SUMOPOD_MODEL') && SUMOPOD_MODEL !== '' ? SUMOPOD_MODEL : 'gpt-4o-mini');
    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Kamu membalas HANYA dalam format JSON valid, tanpa markdown, tanpa teks tambahan.',
            ],
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        'temperature' => 0.9,
        'response_format' => ['type' => 'json_object'],
    ];

    $data = ai_post_json(
        'https://ai.sumopod.com/v1/chat/completions',
        $payload,
        [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        'SumoPod'
    );

    $text = $data['choices'][0]['message']['content'] ?? '';
    if ($text === '') {
        throw new RuntimeException('Respons AI kosong.');
    }

    return $text;
}

function call_gemini_api(string $prompt, string $apiKey = '', string $model = ''): string
{
    $apiKey = $apiKey !== '' ? $apiKey : (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
    if ($apiKey === '') {
        throw new RuntimeException('API key Gemini belum diisi. Atur di halaman Pengaturan AI.');
    }

    $model = $model !== '' ? $model : (defined('GEMINI_MODEL') && GEMINI_MODEL !== '' ? GEMINI_MODEL : 'gemini-2.5-flash');
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;

    $payload = [
        'contents' => [[ 'parts' => [[ 'text' => $prompt ]] ]],
        'generationConfig' => [
            'temperature' => 0.9,
            'responseMimeType' => 'application/json',
        ],
    ];

    $data = ai_post_json($url, $payload, ['Content-Type: application/json'], 'Gemini');

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') {
        throw new RuntimeException('Respons AI kosong.');
    }

    return $text;
}

function ai_post_json(string $url, array $payload, array $headers, string $providerName): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi PHP cURL belum aktif.');
    }

    $ch = curl_init($url);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 25,
    ];
    ai_apply_ca_bundle($curlOptions);
    curl_setopt_array($ch, $curlOptions);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlNo = curl_errno($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr) {
        if ($curlNo === 60) {
            throw new RuntimeException('SSL certificate PHP/cURL belum dikonfigurasi. Isi curl.cainfo di php.ini atau pasang CA bundle server.');
        }
        throw new RuntimeException('Gagal terhubung ke layanan AI (' . $providerName . ').');
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respons ' . $providerName . ' tidak valid.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException(ai_provider_error_message($providerName, $data, $httpCode));
    }

    return $data;
}

function ai_apply_ca_bundle(array &$curlOptions): void
{
    if ((string)ini_get('curl.cainfo') !== '') {
        return;
    }

    $candidates = [
        __DIR__ . '/../storage/cacert.pem',
        'C:\\laragon\\etc\\ssl\\cacert.pem',
        'C:\\laragon\\bin\\postgresql\\postgresql\\pgAdmin 4\\python\\Lib\\site-packages\\certifi\\cacert.pem',
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            $curlOptions[CURLOPT_CAINFO] = $path;
            return;
        }
    }
}

function ai_provider_error_message(string $providerName, array $data, int $httpCode): string
{
    $status = (string)($data['error']['status'] ?? '');
    $message = (string)($data['error']['message'] ?? ($data['error']['error'] ?? ''));
    $lowerMessage = strtolower($message);

    if ($httpCode === 401 || $status === 'UNAUTHENTICATED' || str_contains($lowerMessage, 'invalid api key')) {
        return 'API key ' . $providerName . ' tidak valid atau tidak terbaca. Cek config.php.';
    }
    if ($httpCode === 403 || $status === 'PERMISSION_DENIED') {
        return 'Akses ' . $providerName . ' ditolak. Cek izin API key, pembatasan domain/IP, dan akses model.';
    }
    if ($httpCode === 404 || $status === 'NOT_FOUND') {
        return 'Model ' . $providerName . ' tidak ditemukan atau belum tersedia. Cek konfigurasi model di config.php.';
    }
    if ($httpCode === 429 || $status === 'RESOURCE_EXHAUSTED' || str_contains($lowerMessage, 'rate limit')) {
        return 'Kuota atau rate limit ' . $providerName . ' tercapai. Coba lagi nanti atau gunakan API key/project lain.';
    }
    if ($httpCode === 400 || $status === 'INVALID_ARGUMENT') {
        return 'Request ke ' . $providerName . ' tidak valid. Cek model dan format payload.';
    }

    if ($message !== '') {
        return $providerName . ' API: ' . $message;
    }

    return 'Layanan AI (' . $providerName . ') menolak permintaan. HTTP ' . $httpCode . '.';
}

function strip_json_fence(string $text): string
{
    return trim(preg_replace('/^```json\s*|```$/m', '', $text));
}

function generate_marketing_copy(array $brand, array $event, string $eventTitle, string $customContext, string $inviteLink, string $format = 'whatsapp_broadcast', string $ctaTarget = 'referral'): array
{
    $format = normalize_ai_format($format);
    $ctaTarget = normalize_ai_cta_target($ctaTarget);
    $prompt = build_marketing_prompt($brand, $event, $eventTitle, $customContext, $inviteLink, $format, $ctaTarget);
    $rawText = strip_json_fence(call_ai_content_provider($prompt));

    $parsed = json_decode($rawText, true);
    if (!is_array($parsed) || empty($parsed['variations']) || !is_array($parsed['variations'])) {
        throw new RuntimeException('Format hasil AI tidak valid. Coba generate ulang.');
    }

    $variations = [];
    foreach (array_slice($parsed['variations'], 0, 5) as $v) {
        if (empty($v['headline']) || empty($v['cta_text'])) {
            continue;
        }
        $variations[] = [
            'style' => (string)($v['style'] ?? 'Variasi'),
            'headline' => (string)$v['headline'],
            'subheadline' => (string)($v['subheadline'] ?? ''),
            'description' => (string)($v['description'] ?? ''),
            'cta_text' => (string)$v['cta_text'],
        ];
    }

    if (empty($variations)) {
        throw new RuntimeException('AI tidak menghasilkan variasi yang valid. Coba generate ulang.');
    }

    return $variations;
}

function generate_single_style_copy(array $brand, array $event, string $eventTitle, string $customContext, string $inviteLink, string $format, string $styleName, string $ctaTarget = 'referral'): array
{
    $format = normalize_ai_format($format);
    $styleName = normalize_ai_style($styleName);
    $ctaTarget = normalize_ai_cta_target($ctaTarget);
    if ($styleName === '') {
        throw new RuntimeException('Gaya copywriting tidak valid.');
    }

    $prompt = build_single_style_prompt($brand, $event, $eventTitle, $customContext, $inviteLink, $format, $styleName, $ctaTarget);
    $rawText = strip_json_fence(call_ai_content_provider($prompt));

    $parsed = json_decode($rawText, true);
    $v = $parsed['variation'] ?? null;
    if (!is_array($v) || empty($v['headline']) || empty($v['cta_text'])) {
        throw new RuntimeException('Format hasil AI tidak valid. Coba generate ulang.');
    }

    return [
        'style' => (string)($v['style'] ?? $styleName),
        'headline' => (string)$v['headline'],
        'subheadline' => (string)($v['subheadline'] ?? ''),
        'description' => (string)($v['description'] ?? ''),
        'cta_text' => (string)$v['cta_text'],
    ];
}

/**
 * Baca semua template landing page AI yang tersedia dari includes/event_templates/{key}/meta.json.
 * Hasil di-cache secara statis (hanya dibaca sekali per request).
 */
function get_ai_landing_templates(): array
{
    static $templates = null;
    if ($templates !== null) {
        return $templates;
    }

    $templates = [];
    $baseDir = __DIR__ . '/event_templates';
    foreach (glob($baseDir . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $metaPath = $dir . '/meta.json';
        if (!is_file($metaPath)) {
            continue;
        }
        $meta = json_decode((string)file_get_contents($metaPath), true);
        if (!is_array($meta) || empty($meta['key'])) {
            continue;
        }
        $templates[$meta['key']] = $meta;
    }

    return $templates;
}

function is_valid_ai_landing_template(string $key): bool
{
    return array_key_exists($key, get_ai_landing_templates());
}

function build_landing_page_prompt(array $brand, array $eventBrief, string $customContext): string
{
    $brandName = $brand['name'] ?? $brand['slug'];
    $themeVibe = ($brand['theme_preset'] ?? 'gold') === 'silver'
        ? 'bersih, modern, accessible, entry point cerdas'
        : 'eksklusif, terpercaya, powerful, high-value';

    $eventName = $eventBrief['name'] ?? '';
    $eventDay = $eventBrief['event_day'] ?? '';
    $eventTime = $eventBrief['event_time'] ?? '';
    $eventLoc = $eventBrief['event_location'] ?? '';
    $eventSpeaker = $eventBrief['event_speaker'] ?? '';
    $eventCapacity = $eventBrief['event_capacity'] ?? '';
    $context = trim($customContext) !== ''
        ? "Konteks tambahan dari admin: " . trim($customContext)
        : "Tidak ada konteks tambahan khusus.";

    $templateList = '';
    foreach (get_ai_landing_templates() as $tpl) {
        $templateList .= "- \"{$tpl['key']}\" ({$tpl['label']}): {$tpl['description']}\n";
    }

    return <<<PROMPT
Kamu adalah AI perancang landing page (copywriter + content strategist + art director) untuk brand edukasi
finansial "{$brandName}" (vibe: {$themeVibe}). Landing page yang kamu rancang harus terasa KAYA, detail, dan
dikembangkan secara unik untuk event ini — seperti landing page webinar profesional dengan banyak section
yang saling melengkapi, BUKAN halaman pendek generik satu paragraf, dan BUKAN struktur yang selalu sama
persis di setiap event. Kamu bebas berkreasi dalam memilih section apa saja yang dipakai, urutannya, jumlah
itemnya, dan sudut pandang ceritanya — selama tetap relevan dan spesifik untuk tema event ini.

JUDUL EVENT (WAJIB JADI ACUAN UTAMA): "{$eventName}"

Detail acara:
Hari/Tanggal: {$eventDay}
Waktu: {$eventTime}
Lokasi: {$eventLoc}
Pembicara: {$eventSpeaker}
Kapasitas: {$eventCapacity}
{$context}

LANGKAH 1 — Pilih SATU template visual yang paling cocok dari daftar berikut:
{$templateList}

LANGKAH 2 — Isi hero (selalu tampil, di posisi paling atas):
- "headline": WAJIB dibungkus markdown bold dua bintang, contoh: **Mulai dari Satu Link Referral**.
- "eyebrow": label pendek (maks 6 kata) di atas headline.
- "subheadline": 1 kalimat pendek penegas di bawah headline.
- "description": 1-2 paragraf pendek yang menjelaskan urgensi/konteks acara, pisahkan paragraf dengan "\\n\\n".
- "cta_text": teks tombol pendaftaran, maksimal 6 kata, action-oriented.
- "accent_color": kode hex 6-digit (contoh "#C9A84C") yang cocok dengan vibe template terpilih.

LANGKAH 3 — Rancang isi ("content") landing page. Kamu punya PERPUSTAKAAN section berikut untuk dipilih bebas
(TIDAK WAJIB semua dipakai — pilih 5 sampai 8 yang paling pas untuk event ini, boleh kombinasi apa saja):

- "stat_grid": section data/realita pendukung urgensi topik. Isi: title, lede, stats (3-5 item {"value": angka/statistik singkat mis. "7%", "label": penjelasan singkat}), quote (opsional, 1 kalimat kutipan reflektif).
- "formula_steps": section langkah/formula praktis bernomor (alternatif dari roadmap, cocok untuk konten "cara/strategi"). Isi: title, lede, steps (3-6 item {"num": nomor urut string, "title": maks 6 kata, "desc": 1 kalimat}), plus (opsional, 1 kartu highlight bonus/tambahan istimewa: {"badge": label pendek, "title": judul, "desc": penjelasan, "points": 3-5 poin singkat}).
- "why_now": 3 kartu alasan "kenapa topik ini penting sekarang". Isi: title, lede, cards (TEPAT 3 item {"icon": 1 simbol/emoji, "title": maks 5 kata, "desc": 1 kalimat}).
- "audience_cards": kartu ikon untuk target audiens (alternatif lebih detail dari audience_chips). Isi: title, cards (3-4 item {"icon": emoji, "title": nama segmen, "desc": 1 kalimat kenapa segmen ini cocok}).
- "audience_chips": chip singkat untuk target audiens (versi ringkas). Isi: title, chips (3-6 label singkat 1-3 kata), footnote (1 kalimat penutup).
- "roadmap": timeline materi/sesi acara terurut (alternatif dari formula_steps, cocok untuk konten "kurikulum/susunan acara"). Isi: title, lede, steps (3-6 item {"step": nomor urut string, "title": maks 6 kata, "desc": 1 kalimat}).
- "speaker": profil pembicara. Isi: role (1 baris peran/kredensial, maks 6 kata, sesuai nama pembicara di atas), bio (1 paragraf pendek 2-3 kalimat relevan tema event). WAJIB diisi jika nama pembicara tersedia.
- "benefits": daftar output/kompetensi konkret yang didapat peserta. Isi: title, items (4-6 kalimat singkat, bukan objek).
- "testimonial": HANYA isi jika konteks tambahan dari admin menyebutkan testimoni/social proof spesifik yang bisa dipakai. Isi: quote, name. Jika tidak ada bahan testimoni, JANGAN sertakan blok ini sama sekali.
- "faq": pertanyaan umum calon peserta. Isi: title, items (3-5 pasangan {"q": pertanyaan singkat, "a": jawaban 1-2 kalimat}).

Jangan pernah menyertakan "registration_form" di dalam objek "blocks" (kontennya sudah ditentukan sistem,
bukan tugasmu) — cukup sebutkan key "registration_form" di array "layout" pada posisi yang menurutmu pas
secara naratif (biasanya menjelang akhir halaman, sebelum atau sesudah FAQ).

Aturan penting:
- "layout" adalah array urutan key section yang kamu pilih dari daftar di atas (TERMASUK "registration_form"
  di posisi yang kamu tentukan). Jangan sertakan key section yang kontennya tidak kamu isi di "blocks".
- Setiap section yang kamu pilih HARUS relevan dan spesifik terhadap judul & konteks event — jangan menulis
  isi generik yang bisa dipakai event apa saja.
- Bahasa Indonesia, action-oriented, optimis, tidak boleh membuat klaim keuntungan finansial berlebihan atau
  menjanjikan hasil pasti.

Balas HANYA dalam format JSON valid (tanpa markdown, tanpa teks lain), dengan struktur persis:
{
  "template_key": "salah satu key template di atas",
  "accent_color": "#RRGGBB",
  "eyebrow": "...",
  "headline": "**...**",
  "subheadline": "...",
  "description": "...",
  "cta_text": "...",
  "layout": ["stat_grid", "formula_steps", "audience_cards", "speaker", "benefits", "registration_form", "faq"],
  "blocks": {
    "stat_grid": {"title": "...", "lede": "...", "quote": "...", "stats": [{"value": "...", "label": "..."}]},
    "formula_steps": {"title": "...", "lede": "...", "steps": [{"num": "1", "title": "...", "desc": "..."}], "plus": {"badge": "...", "title": "...", "desc": "...", "points": ["..."]}},
    "why_now": {"title": "...", "lede": "...", "cards": [{"icon": "...", "title": "...", "desc": "..."}]},
    "audience_cards": {"title": "...", "cards": [{"icon": "...", "title": "...", "desc": "..."}]},
    "audience_chips": {"title": "...", "chips": ["..."], "footnote": "..."},
    "roadmap": {"title": "...", "lede": "...", "steps": [{"step": "1", "title": "...", "desc": "..."}]},
    "speaker": {"role": "...", "bio": "..."},
    "benefits": {"title": "...", "items": ["..."]},
    "testimonial": {"quote": "...", "name": "..."},
    "faq": {"title": "...", "items": [{"q": "...", "a": "..."}]}
  }
}
PROMPT;
}

/** Bersihkan array item AI (mis. why_cards/roadmap/faq) jadi array asosiatif string sesuai $fields, buang item yang field wajibnya kosong. */
function sanitize_landing_item_list($raw, array $fields, string $requiredField, int $maxItems): array
{
    if (!is_array($raw)) {
        return [];
    }

    $clean = [];
    foreach ($raw as $item) {
        if (!is_array($item) || trim((string)($item[$requiredField] ?? '')) === '') {
            continue;
        }
        $row = [];
        foreach ($fields as $field) {
            $row[$field] = trim((string)($item[$field] ?? ''));
        }
        $clean[] = $row;
        if (count($clean) >= $maxItems) {
            break;
        }
    }

    return $clean;
}

/** Bersihkan array string polos (mis. audience_chips/benefits), buang item kosong. */
function sanitize_landing_string_list($raw, int $maxItems): array
{
    if (!is_array($raw)) {
        return [];
    }

    $clean = [];
    foreach ($raw as $item) {
        $value = trim((string)$item);
        if ($value === '') {
            continue;
        }
        $clean[] = $value;
        if (count($clean) >= $maxItems) {
            break;
        }
    }

    return $clean;
}

/**
 * Daftar putih section ("blocks") yang boleh dipilih AI, disertai fungsi validasi minimal kontennya
 * masing-masing. Section yang tidak lolos validasi (kurang item / field wajib kosong) tidak akan
 * dimasukkan ke $blocks — nanti otomatis tidak tampil di layout apa pun urutan yang diminta AI.
 */
function sanitize_landing_blocks($raw): array
{
    $raw = is_array($raw) ? $raw : [];
    $blocks = [];

    if (is_array($raw['stat_grid'] ?? null)) {
        $stats = sanitize_landing_item_list($raw['stat_grid']['stats'] ?? null, ['value', 'label'], 'value', 5);
        if (count($stats) >= 2) {
            $blocks['stat_grid'] = [
                'title' => trim((string)($raw['stat_grid']['title'] ?? '')),
                'lede' => trim((string)($raw['stat_grid']['lede'] ?? '')),
                'quote' => trim((string)($raw['stat_grid']['quote'] ?? '')),
                'stats' => $stats,
            ];
        }
    }

    if (is_array($raw['formula_steps'] ?? null)) {
        $steps = sanitize_landing_item_list($raw['formula_steps']['steps'] ?? null, ['num', 'title', 'desc'], 'title', 6);
        if (count($steps) >= 2) {
            $plusRaw = is_array($raw['formula_steps']['plus'] ?? null) ? $raw['formula_steps']['plus'] : [];
            $plusTitle = trim((string)($plusRaw['title'] ?? ''));
            $blocks['formula_steps'] = [
                'title' => trim((string)($raw['formula_steps']['title'] ?? '')),
                'lede' => trim((string)($raw['formula_steps']['lede'] ?? '')),
                'steps' => $steps,
                'plus' => [
                    'badge' => $plusTitle !== '' ? trim((string)($plusRaw['badge'] ?? '')) : '',
                    'title' => $plusTitle,
                    'desc' => $plusTitle !== '' ? trim((string)($plusRaw['desc'] ?? '')) : '',
                    'points' => $plusTitle !== '' ? sanitize_landing_string_list($plusRaw['points'] ?? null, 5) : [],
                ],
            ];
        }
    }

    if (is_array($raw['why_now'] ?? null)) {
        $cards = sanitize_landing_item_list($raw['why_now']['cards'] ?? null, ['icon', 'title', 'desc'], 'title', 3);
        if (count($cards) >= 2) {
            $blocks['why_now'] = [
                'title' => trim((string)($raw['why_now']['title'] ?? '')),
                'lede' => trim((string)($raw['why_now']['lede'] ?? '')),
                'cards' => $cards,
            ];
        }
    }

    if (is_array($raw['audience_cards'] ?? null)) {
        $cards = sanitize_landing_item_list($raw['audience_cards']['cards'] ?? null, ['icon', 'title', 'desc'], 'title', 4);
        if (count($cards) >= 2) {
            $blocks['audience_cards'] = [
                'title' => trim((string)($raw['audience_cards']['title'] ?? '')),
                'cards' => $cards,
            ];
        }
    }

    if (is_array($raw['audience_chips'] ?? null)) {
        $chips = sanitize_landing_string_list($raw['audience_chips']['chips'] ?? null, 6);
        if (count($chips) >= 2) {
            $blocks['audience_chips'] = [
                'title' => trim((string)($raw['audience_chips']['title'] ?? '')),
                'footnote' => trim((string)($raw['audience_chips']['footnote'] ?? '')),
                'chips' => $chips,
            ];
        }
    }

    if (is_array($raw['roadmap'] ?? null)) {
        $steps = sanitize_landing_item_list($raw['roadmap']['steps'] ?? null, ['step', 'title', 'desc'], 'title', 6);
        if (count($steps) >= 2) {
            $blocks['roadmap'] = [
                'title' => trim((string)($raw['roadmap']['title'] ?? '')),
                'lede' => trim((string)($raw['roadmap']['lede'] ?? '')),
                'steps' => $steps,
            ];
        }
    }

    if (is_array($raw['speaker'] ?? null)) {
        $bio = trim((string)($raw['speaker']['bio'] ?? ''));
        if ($bio !== '') {
            $blocks['speaker'] = [
                'role' => trim((string)($raw['speaker']['role'] ?? '')),
                'bio' => $bio,
            ];
        }
    }

    if (is_array($raw['benefits'] ?? null)) {
        $items = sanitize_landing_string_list($raw['benefits']['items'] ?? null, 6);
        if (count($items) >= 2) {
            $blocks['benefits'] = [
                'title' => trim((string)($raw['benefits']['title'] ?? '')),
                'items' => $items,
            ];
        }
    }

    if (is_array($raw['testimonial'] ?? null)) {
        $quote = trim((string)($raw['testimonial']['quote'] ?? ''));
        if ($quote !== '') {
            $blocks['testimonial'] = [
                'quote' => $quote,
                'name' => trim((string)($raw['testimonial']['name'] ?? '')),
            ];
        }
    }

    if (is_array($raw['faq'] ?? null)) {
        $items = sanitize_landing_item_list($raw['faq']['items'] ?? null, ['q', 'a'], 'q', 5);
        if (count($items) >= 2) {
            $blocks['faq'] = [
                'title' => trim((string)($raw['faq']['title'] ?? '')),
                'items' => $items,
            ];
        }
    }

    return $blocks;
}

/**
 * Bersihkan urutan section pilihan AI ("layout"): hanya key yang valid & isinya lolos sanitasi
 * (ada di $availableBlocks) yang dipertahankan, plus jaminan kontrak sistem — "registration_form"
 * dan "speaker" (kalau kontennya tersedia) tetap tampil walau AI lupa menyebutkannya.
 */
function sanitize_landing_layout($rawLayout, array $availableBlocks): array
{
    $layout = [];
    if (is_array($rawLayout)) {
        foreach ($rawLayout as $key) {
            $key = is_string($key) ? $key : '';
            if ($key === '' || in_array($key, $layout, true)) {
                continue;
            }
            if ($key === 'registration_form' || isset($availableBlocks[$key])) {
                $layout[] = $key;
            }
        }
    }

    if (empty($layout)) {
        $layout = array_keys($availableBlocks);
    }

    if (!in_array('speaker', $layout, true) && isset($availableBlocks['speaker'])) {
        $layout[] = 'speaker';
    }

    if (!in_array('registration_form', $layout, true)) {
        $faqPos = array_search('faq', $layout, true);
        if ($faqPos !== false) {
            array_splice($layout, $faqPos, 0, ['registration_form']);
        } else {
            $layout[] = 'registration_form';
        }
    }

    return $layout;
}

function generate_ai_landing_page(array $brand, array $eventBrief, string $customContext): array
{
    $templates = get_ai_landing_templates();
    if (empty($templates)) {
        throw new RuntimeException('Belum ada template landing page AI yang tersedia.');
    }

    $prompt = build_landing_page_prompt($brand, $eventBrief, $customContext);
    $rawText = strip_json_fence(call_ai_content_provider($prompt));

    $parsed = json_decode($rawText, true);
    if (!is_array($parsed) || empty($parsed['headline']) || empty($parsed['cta_text'])) {
        throw new RuntimeException('Format hasil AI tidak valid. Coba generate ulang.');
    }

    $templateKey = (string)($parsed['template_key'] ?? '');
    if (!is_valid_ai_landing_template($templateKey)) {
        $templateKey = array_key_first($templates);
    }
    $templateMeta = $templates[$templateKey];

    $accentColor = (string)($parsed['accent_color'] ?? '');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
        $accentColor = $templateMeta['default_accent'] ?? '#C9A84C';
    }

    $blocks = sanitize_landing_blocks($parsed['blocks'] ?? null);
    $layout = sanitize_landing_layout($parsed['layout'] ?? null, $blocks);

    return [
        'template_key' => $templateKey,
        'accent_color' => $accentColor,
        'eyebrow' => (string)($parsed['eyebrow'] ?? ''),
        'headline' => (string)$parsed['headline'],
        'subheadline' => (string)($parsed['subheadline'] ?? ''),
        'description' => (string)($parsed['description'] ?? ''),
        'cta_text' => (string)$parsed['cta_text'],
        'layout' => $layout,
        'blocks' => $blocks,
    ];
}

function check_ai_rate_limit(): bool
{
    $now = time();
    $_SESSION['ai_gen_log'] = array_filter(
        $_SESSION['ai_gen_log'] ?? [],
        static fn ($ts) => $ts > $now - 3600
    );

    if (count($_SESSION['ai_gen_log']) >= AI_CONTENT_MAX_PER_HOUR) {
        return false;
    }

    $_SESSION['ai_gen_log'][] = $now;
    return true;
}
