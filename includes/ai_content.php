<?php
/**
 * includes/ai_content.php
 * Wrapper pemanggilan provider AI untuk generate copywriting event.
 */

function build_marketing_prompt(array $brand, array $event, string $eventTitle, string $customContext, string $inviteLink): string
{
    $brandName    = $brand['name'] ?? $brand['slug'];
    $themeVibe    = ($brand['theme_preset'] ?? 'gold') === 'silver'
        ? 'bersih, modern, accessible, entry point cerdas'
        : 'eksklusif, terpercaya, powerful, high-value';

    $eventDay     = $event['event_day'] ?? '';
    $eventTime    = $event['event_time'] ?? '';
    $eventLoc     = $event['event_location'] ?? '';
    $eventSpeaker = $event['event_speaker'] ?? '';

    $context = trim($customContext) !== ''
        ? "Konteks tambahan dari admin: " . trim($customContext)
        : "Tidak ada konteks tambahan khusus.";

    return <<<PROMPT
Kamu adalah copywriter profesional untuk brand edukasi finansial "{$brandName}" (vibe: {$themeVibe}).

JUDUL EVENT (WAJIB JADI ACUAN UTAMA, JANGAN KELUAR DARI TEMA INI): "{$eventTitle}"

Tugasmu: buat materi promosi untuk MEREKRUT PENGUNDANG (bukan peserta langsung) — orang yang akan
membuat link referral pribadi lalu mengundang teman-temannya ke acara dengan judul di atas.

Detail acara:
Hari/Tanggal: {$eventDay}
Waktu: {$eventTime}
Lokasi: {$eventLoc}
Pembicara: {$eventSpeaker}
{$context}

ATURAN KETAT KONTEKS:
- Seluruh headline, subheadline, dan description WAJIB merujuk langsung ke tema/judul event di atas.
- Jangan membuat tema baru, jangan generalisasi ke topik finansial lain di luar judul event ini.
- Jangan mengulang kata judul event secara harfiah di semua variasi — variasikan sudut pandang, tapi tetap satu tema yang sama.

Aturan gaya bahasa:
- Bahasa Indonesia, santai tapi profesional, action-oriented, optimis.
- Tidak boleh membuat klaim keuntungan finansial yang berlebihan atau menjanjikan hasil pasti.
- CTA harus mengarahkan orang untuk MEMBUAT LINK REFERRAL mereka sendiri, contoh gaya: "Buat Link Referral Saya", "Sebarkan Link Sekarang".
- Jangan gunakan bahasa manipulatif atau tekanan psikologis berlebihan.
- Description wajib lebih tajam, persuasif, dan actionable: minimal 3 paragraf pendek.
- Setiap paragraf description maksimal 1-2 kalimat, dipisahkan dengan newline "\n\n" di dalam string JSON.
- Paragraf 1: kaitkan langsung dengan pain point/aspirasi audiens dari judul event.
- Paragraf 2: jelaskan kenapa pengundang perlu ikut menyebarkan event ini.
- Paragraf 3: arahkan ke tindakan membuat link referral tanpa klaim hasil pasti.

Buat TEPAT 5 variasi copywriting dengan gaya berbeda:
1. Storytelling Emosional
2. Direct & Ambisius
3. FOMO Halus (urgensi tanpa tekanan berlebihan)
4. Edukatif & Kredibel (data/alasan logis)
5. Santai & Relatable (seperti ngobrol biasa)

Balas HANYA dalam format JSON valid (tanpa markdown, tanpa teks lain), dengan struktur persis:
{
  "variations": [
    {
      "style": "nama gaya",
      "headline": "...",
      "subheadline": "...",
      "description": "Minimal 3 paragraf pendek, pisahkan paragraf dengan newline \\n\\n",
      "cta_text": "..."
    }
  ]
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
                'content' => 'Kamu hanya membalas JSON valid sesuai instruksi user.',
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
    $curlNo   = curl_errno($ch);
    $curlErr  = curl_error($ch);
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
    $code = (string)($data['error']['code'] ?? '');
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

function generate_marketing_copy(array $brand, array $event, string $eventTitle, string $customContext, string $inviteLink): array
{
    $prompt = build_marketing_prompt($brand, $event, $eventTitle, $customContext, $inviteLink);
    $rawText = call_ai_content_provider($prompt);
    $rawText = trim(preg_replace('/^```json\s*|```$/m', '', $rawText));

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
            'style'       => (string)($v['style'] ?? 'Variasi'),
            'headline'    => (string)$v['headline'],
            'subheadline' => (string)($v['subheadline'] ?? ''),
            'description' => (string)($v['description'] ?? ''),
            'cta_text'    => (string)$v['cta_text'],
        ];
    }

    if (empty($variations)) {
        throw new RuntimeException('AI tidak menghasilkan variasi yang valid. Coba generate ulang.');
    }

    return $variations;
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
