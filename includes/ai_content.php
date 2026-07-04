<?php
/**
 * includes/ai_content.php
 * Wrapper pemanggilan Gemini API untuk generate copywriting event.
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
      "description": "2-3 kalimat pendek",
      "cta_text": "..."
    }
  ]
}
PROMPT;
}

function call_gemini_api(string $prompt): string
{
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') {
        throw new RuntimeException('GEMINI_API_KEY belum diisi di config.php.');
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $payload = [
        'contents' => [[ 'parts' => [[ 'text' => $prompt ]] ]],
        'generationConfig' => [
            'temperature' => 0.9,
            'responseMimeType' => 'application/json',
        ],
    ];

    $ch = curl_init($url);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 25,
    ];
    if (defined('GEMINI_CA_CERT_PATH') && GEMINI_CA_CERT_PATH !== '' && is_file(GEMINI_CA_CERT_PATH)) {
        $curlOptions[CURLOPT_CAINFO] = GEMINI_CA_CERT_PATH;
    }
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlNo   = curl_errno($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr) {
        if ($curlNo === 60) {
            throw new RuntimeException('SSL certificate PHP/cURL belum dikonfigurasi. Isi GEMINI_CA_CERT_PATH atau curl.cainfo di php.ini.');
        }
        throw new RuntimeException('Gagal terhubung ke layanan AI (Gemini).');
    }

    $data = json_decode($response, true);
    if ($httpCode === 429) {
        throw new RuntimeException(gemini_error_message($data, 'Kuota gratis Gemini API sedang penuh. Coba lagi beberapa saat lagi.'));
    }
    if ($httpCode !== 200) {
        throw new RuntimeException(gemini_error_message($data, 'Layanan AI (Gemini) menolak permintaan. Cek API key di config.php.'));
    }

    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') {
        throw new RuntimeException('Respons AI kosong.');
    }
    return $text;
}

function gemini_error_message(?array $data, string $fallback): string
{
    $status = (string)($data['error']['status'] ?? '');
    $message = (string)($data['error']['message'] ?? '');

    if ($status === 'RESOURCE_EXHAUSTED') {
        return 'Kuota Gemini API habis atau rate limit tercapai. Coba lagi nanti, aktifkan billing, atau gunakan API key/project lain.';
    }
    if ($status === 'PERMISSION_DENIED') {
        return 'Akses Gemini API ditolak. Cek apakah API key valid, tidak dibatasi domain/IP yang salah, dan Gemini API aktif di project Google.';
    }
    if ($status === 'UNAUTHENTICATED') {
        return 'API key Gemini tidak valid atau tidak terbaca. Cek kembali GEMINI_API_KEY di config.php.';
    }
    if ($status === 'INVALID_ARGUMENT') {
        return 'Request ke Gemini tidak valid. Cek nama model GEMINI_MODEL dan format payload.';
    }
    if ($status === 'NOT_FOUND') {
        return 'Model Gemini tidak ditemukan atau belum tersedia untuk API key ini. Cek GEMINI_MODEL di config.php.';
    }

    if ($message !== '') {
        return 'Gemini API: ' . $message;
    }

    return $fallback;
}

function generate_marketing_copy(array $brand, array $event, string $eventTitle, string $customContext, string $inviteLink): array
{
    $prompt = build_marketing_prompt($brand, $event, $eventTitle, $customContext, $inviteLink);
    $rawText = call_gemini_api($prompt);
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
