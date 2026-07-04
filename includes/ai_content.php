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

function call_ai_content_provider(string $prompt): string
{
    $provider = strtolower((string)(defined('AI_CONTENT_PROVIDER') ? AI_CONTENT_PROVIDER : 'groq'));

    if ($provider === 'gemini') {
        return call_gemini_api($prompt);
    }

    if ($provider === 'groq' || $provider === '') {
        return call_groq_api($prompt);
    }

    throw new RuntimeException('AI_CONTENT_PROVIDER tidak dikenal. Gunakan "groq" atau "gemini".');
}

function call_groq_api(string $prompt): string
{
    if (!defined('GROQ_API_KEY') || GROQ_API_KEY === '') {
        throw new RuntimeException('GROQ_API_KEY belum diisi di config.php.');
    }

    $model = defined('GROQ_MODEL') && GROQ_MODEL !== '' ? GROQ_MODEL : 'llama-3.3-70b-versatile';
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
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        'Groq'
    );

    $text = $data['choices'][0]['message']['content'] ?? '';
    if ($text === '') {
        throw new RuntimeException('Respons AI kosong.');
    }

    return $text;
}

function call_gemini_api(string $prompt): string
{
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        throw new RuntimeException('GEMINI_API_KEY belum diisi di config.php.');
    }

    $model = defined('GEMINI_MODEL') && GEMINI_MODEL !== '' ? GEMINI_MODEL : 'gemini-2.5-flash';
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . GEMINI_API_KEY;

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
