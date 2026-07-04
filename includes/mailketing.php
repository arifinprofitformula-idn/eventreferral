<?php
/**
 * includes/mailketing.php
 * Wrapper integrasi Mailketing API: kirim email, ambil daftar list, tambah subscriber.
 * Semua fungsi di sini FAIL-SAFE — kegagalan tidak boleh mengganggu alur submit_lead.php.
 */

function mailketing_request(string $endpoint, array $params): array
{
    if (!defined('MAILKETING_API_TOKEN') || MAILKETING_API_TOKEN === '') {
        throw new RuntimeException('MAILKETING_API_TOKEN belum diisi di config.php.');
    }

    $params['api_token'] = MAILKETING_API_TOKEN;

    $ch = curl_init("https://api.mailketing.co.id/api/v1/{$endpoint}");
    $verifySsl = !defined('MAILKETING_SSL_VERIFY') || MAILKETING_SSL_VERIFY;

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlErr) {
        throw new RuntimeException('Gagal terhubung ke Mailketing: ' . ($curlErr ?: 'respons kosong.'));
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        throw new RuntimeException('Respons Mailketing tidak valid. HTTP ' . $httpCode . '.');
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = $data['message'] ?? $data['error'] ?? $data['response'] ?? 'Request Mailketing gagal.';
        throw new RuntimeException('Mailketing HTTP ' . $httpCode . ': ' . (is_scalar($message) ? $message : 'Request gagal.'));
    }

    if (isset($data['status']) && strtolower((string)$data['status']) !== 'success') {
        $message = $data['message'] ?? $data['error'] ?? $data['response'] ?? 'Request Mailketing gagal.';
        throw new RuntimeException('Mailketing: ' . (is_scalar($message) ? $message : 'Request gagal.'));
    }

    return $data;
}

function mailketing_send_email(string $toEmail, string $subject, string $htmlContent, ?string $fromName = null, ?string $fromEmail = null): array
{
    return mailketing_request('send', [
        'from_name'  => $fromName ?: MAILKETING_SENDER_NAME,
        'from_email' => $fromEmail ?: MAILKETING_SENDER_EMAIL,
        'recipient'  => $toEmail,
        'subject'    => $subject,
        'content'    => $htmlContent,
    ]);
}

function mailketing_get_lists(): array
{
    $result = mailketing_request('viewlist', []);
    $lists = $result['data'] ?? $result['response'] ?? $result['lists'] ?? $result;

    if (isset($lists['data']) && is_array($lists['data'])) {
        $lists = $lists['data'];
    }

    if (!is_array($lists)) {
        throw new RuntimeException('Format daftar list Mailketing tidak dikenali.');
    }

    return array_values(array_filter($lists, 'is_array'));
}

function mailketing_add_subscriber(string $email, string $firstName, string $listId): array
{
    return mailketing_request('addsubtolist', [
        'email'      => $email,
        'first_name' => $firstName,
        'list_id'    => $listId,
    ]);
}

function mailketing_parse_event_start(array $event): ?DateTimeImmutable
{
    $eventDay = trim((string)($event['event_day'] ?? ''));
    $eventTime = trim((string)($event['event_time'] ?? ''));

    if ($eventDay === '') {
        return null;
    }

    $months = [
        'januari' => 1,
        'februari' => 2,
        'maret' => 3,
        'april' => 4,
        'mei' => 5,
        'juni' => 6,
        'juli' => 7,
        'agustus' => 8,
        'september' => 9,
        'oktober' => 10,
        'november' => 11,
        'desember' => 12,
    ];

    if (!preg_match('/(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})/u', strtolower($eventDay), $dateMatches)) {
        return null;
    }

    $month = $months[$dateMatches[2]] ?? null;
    if ($month === null) {
        return null;
    }

    $hour = 19;
    $minute = 30;
    if (preg_match('/(\d{1,2})[.:](\d{2})/', $eventTime, $timeMatches)) {
        $hour = (int)$timeMatches[1];
        $minute = (int)$timeMatches[2];
    }

    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        return null;
    }

    $timezone = new DateTimeZone('Asia/Jakarta');
    $date = sprintf('%04d-%02d-%02d %02d:%02d:00', (int)$dateMatches[3], $month, (int)$dateMatches[1], $hour, $minute);
    $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date, $timezone);

    return $start ?: null;
}

function build_google_calendar_url(array $brand, array $event, array $settings): string
{
    $eventName = trim((string)($event['name'] ?? 'Acara'));
    $invitationLink = trim((string)($settings['invitation_link'] ?? ''));
    $speaker = trim((string)($event['event_speaker'] ?? ''));
    $location = trim((string)($event['event_location'] ?? ''));

    $details = 'Pengingat acara ' . $eventName . ' dari ' . ($brand['name'] ?? $brand['slug'] ?? 'RahasiaEmas.id') . '.';
    if ($speaker !== '') {
        $details .= "\nPembicara: " . $speaker;
    }
    if ($invitationLink !== '') {
        $details .= "\nLink invitation: " . $invitationLink;
    }

    $params = [
        'action' => 'TEMPLATE',
        'text' => $eventName,
        'details' => $details,
        'location' => $location !== '' ? $location : $invitationLink,
        'ctz' => 'Asia/Jakarta',
    ];

    $start = mailketing_parse_event_start($event);
    if ($start !== null) {
        $end = $start->modify('+2 hours');
        $params['dates'] = $start->format('Ymd\THis') . '/' . $end->format('Ymd\THis');
    }

    return 'https://calendar.google.com/calendar/render?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function build_invitation_email_html(array $brand, array $event, array $settings, string $leadName): string
{
    $accent = ($brand['theme_preset'] ?? 'gold') === 'silver' ? '#B7BCC4' : '#C9A84C';
    $brandName = htmlspecialchars($brand['name'] ?? $brand['slug'], ENT_QUOTES, 'UTF-8');
    $logoPath = !empty($brand['logo_path']) ? $brand['logo_path'] : '/assets/logo.png';
    $logoUrl = 'https://' . $brand['domain'] . $logoPath;

    $placeholders = [
        '{{nama}}'            => htmlspecialchars($leadName, ENT_QUOTES, 'UTF-8'),
        '{{name}}'            => htmlspecialchars($leadName, ENT_QUOTES, 'UTF-8'),
        '{{event_name}}'      => htmlspecialchars($event['name'] ?? '', ENT_QUOTES, 'UTF-8'),
        '{{event_day}}'       => htmlspecialchars($event['event_day'] ?? '', ENT_QUOTES, 'UTF-8'),
        '{{event_time}}'      => htmlspecialchars($event['event_time'] ?? '', ENT_QUOTES, 'UTF-8'),
        '{{event_location}}'  => htmlspecialchars($event['event_location'] ?? '', ENT_QUOTES, 'UTF-8'),
        '{{event_speaker}}'   => htmlspecialchars($event['event_speaker'] ?? '', ENT_QUOTES, 'UTF-8'),
        '{{invitation_link}}' => htmlspecialchars($settings['invitation_link'] ?? '#', ENT_QUOTES, 'UTF-8'),
    ];

    $bodyEscaped = htmlspecialchars($settings['body_content'] ?? '', ENT_QUOTES, 'UTF-8');
    $bodyReplaced = strtr($bodyEscaped, $placeholders);
    $bodyHtml = nl2br($bodyReplaced);

    $ctaText = htmlspecialchars($settings['cta_text'] ?? 'Gabung ke Acara Sekarang', ENT_QUOTES, 'UTF-8');
    $ctaLink = htmlspecialchars($settings['invitation_link'] ?? '#', ENT_QUOTES, 'UTF-8');
    $calendarLink = htmlspecialchars(build_google_calendar_url($brand, $event, $settings), ENT_QUOTES, 'UTF-8');
    $disclaimer = htmlspecialchars($brand['disclaimer_text'] ?? '', ENT_QUOTES, 'UTF-8');

    $logoBlock = $logoUrl !== ''
        ? '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . $brandName . '" style="height:40px;display:block;margin:0 auto;">'
        : '<span style="font-size:20px;font-weight:700;color:#fff;">' . $brandName . '</span>';

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<body style="margin:0;padding:0;background:#0f0f0f;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f0f;padding:24px 0;">
    <tr><td align="center">
      <table width="480" cellpadding="0" cellspacing="0" style="background:#1a1a1a;border-radius:16px;overflow:hidden;border:1px solid {$accent}40;">
        <tr><td align="center" style="background:#141414;padding:20px 28px;text-align:center;">{$logoBlock}</td></tr>
        <tr><td style="padding:28px;color:#eaeaea;font-size:14px;line-height:1.6;">
          {$bodyHtml}
        </td></tr>
        <tr><td align="center" style="padding:0 28px 28px;">
          <a href="{$ctaLink}" style="display:inline-block;background:{$accent};color:#111;font-weight:700;text-decoration:none;padding:12px 24px;border-radius:12px;font-size:14px;margin:0 4px 10px;">&#128279; {$ctaText}</a>
          <a href="{$calendarLink}" style="display:inline-block;background:#2b2b2b;color:#f5f5f5;border:1px solid {$accent};font-weight:700;text-decoration:none;padding:12px 24px;border-radius:12px;font-size:14px;margin:0 4px 10px;">&#128197; Set Alarm</a>
        </td></tr>
        <tr><td style="background:#141414;padding:16px 28px;color:#888;font-size:11px;line-height:1.5;">
          {$disclaimer}
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/**
 * Entry point utama — dipanggil dari submit_lead.php.
 * TIDAK melempar exception ke pemanggil; kegagalan dicatat via error_log saja.
 * Sender identity mengikuti brand aktif (sender_name/sender_email), fallback ke config.php
 * jika brand belum mengisi identitasnya sendiri di admin/integrations.php.
 */
function send_event_invitation_email(array $brand, array $event, string $leadName, string $leadEmail): void
{
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare('SELECT * FROM event_email_settings WHERE brand_id = ? AND event_slug = ?');
        $stmt->execute([(int)$brand['id'], $event['slug']]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings || (int)$settings['auto_send'] !== 1) {
            return;
        }

        $subject = $settings['subject'] !== '' ? $settings['subject'] : ('Info Acara: ' . ($event['name'] ?? ''));
        $html = build_invitation_email_html($brand, $event, $settings, $leadName);

        $senderName  = !empty($brand['sender_name']) ? $brand['sender_name'] : MAILKETING_SENDER_NAME;
        $senderEmail = !empty($brand['sender_email']) ? $brand['sender_email'] : MAILKETING_SENDER_EMAIL;

        mailketing_send_email($leadEmail, $subject, $html, $senderName, $senderEmail);

        if (!empty($settings['mailketing_list_id'])) {
            mailketing_add_subscriber($leadEmail, $leadName, $settings['mailketing_list_id']);
        }
    } catch (Throwable $e) {
        error_log('[Mailketing] Gagal kirim email undangan: ' . $e->getMessage());
    }
}
