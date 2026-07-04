<?php
/**
 * api/create_referrer.php
 * Menerima nama, WhatsApp, dan ref_code pilihan calon pengundang,
 * simpan ke tabel referrers, kembalikan link siap-share.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/message_templates.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$brand = get_current_brand();
if (!$brand) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan.']);
    exit;
}
$brandId = (int)$brand['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$name = clean($input['name'] ?? '');
$wa   = clean($input['whatsapp'] ?? '');
$requestedRefCode = strtolower(clean($input['ref_code'] ?? ''));
$eventSlug = clean($input['event'] ?? $brand['default_event_slug']);

$event = get_event_by_slug($eventSlug);
if (!$event || (int)$event['brand_id'] !== $brandId || $event['status'] !== 'active') {
    $eventSlug = $brand['default_event_slug'];
    $event = get_event_by_slug($eventSlug);
}

$errors = [];
if ($name === '' || mb_strlen($name) < 3) {
    $errors[] = 'Nama minimal 3 karakter.';
}
$waNormalized = normalize_whatsapp($wa);
if (strlen($waNormalized) < 10 || strlen($waNormalized) > 15) {
    $errors[] = 'Nomor WhatsApp tidak valid.';
}
if ($requestedRefCode === '') {
    $errors[] = 'Kode link wajib diisi.';
} elseif (!preg_match('/^[a-z0-9_-]{3,20}$/', $requestedRefCode)) {
    $errors[] = 'Kode link hanya boleh 3-20 karakter: huruf, angka, strip (-), atau underscore (_).';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = get_db();
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $brand['domain'];

    // Cek apakah nomor WA ini sudah pernah membuat link sebelumnya untuk event ini (brand ini).
    $stmt = $pdo->prepare('SELECT ref_code FROM referrers WHERE brand_id = ? AND event_slug = ? AND whatsapp = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute([$brandId, $eventSlug, $waNormalized]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $refCode = $existing['ref_code'];
        if ($requestedRefCode !== $refCode) {
            $existingLink = $eventSlug === $brand['default_event_slug']
                ? "{$protocol}://{$host}/?ref={$refCode}"
                : "{$protocol}://{$host}" . EVENTS_URL_BASE . "/{$eventSlug}/?ref={$refCode}";
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Nomor WhatsApp ini sudah punya kode link. Berikut ini adalah link referral Anda, Tap link untuk menyalin.",
                'existing_ref_code' => $refCode,
                'existing_link' => $existingLink,
                'event' => [
                    'name' => $event['name'] ?? null,
                    'event_day' => $event['event_day'] ?? null,
                    'event_time' => $event['event_time'] ?? null,
                    'event_location' => $event['event_location'] ?? null,
                    'event_speaker' => $event['event_speaker'] ?? null,
                    'flyer_path' => $event['flyer_path'] ?? null,
                ],
                'templates' => build_participant_reply_templates($event ?: [], $existingLink),
            ]);
            exit;
        }
    } else {
        $stmt = $pdo->prepare('SELECT id FROM referrers WHERE brand_id = ? AND event_slug = ? AND ref_code = ?');
        $stmt->execute([$brandId, $eventSlug, $requestedRefCode]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Kode link sudah dipakai. Silakan pilih kode lain.']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO referrers (brand_id, ref_code, event_slug, name, whatsapp) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$brandId, $requestedRefCode, $eventSlug, $name, $waNormalized]);
        $refCode = $requestedRefCode;
    }

    $link = $eventSlug === $brand['default_event_slug']
        ? "{$protocol}://{$host}/?ref={$refCode}"
        : "{$protocol}://{$host}" . EVENTS_URL_BASE . "/{$eventSlug}/?ref={$refCode}";

    echo json_encode([
        'success' => true,
        'ref_code' => $refCode,
        'link' => $link,
        'event' => [
            'name' => $event['name'] ?? null,
            'event_day' => $event['event_day'] ?? null,
            'event_time' => $event['event_time'] ?? null,
            'event_location' => $event['event_location'] ?? null,
            'event_speaker' => $event['event_speaker'] ?? null,
            'flyer_path' => $event['flyer_path'] ?? null,
        ],
        'templates' => build_participant_reply_templates($event ?: [], $link),
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.']);
}
