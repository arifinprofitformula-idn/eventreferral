<?php
/**
 * api/regenerate_style_caption.php
 * Endpoint admin-only untuk generate ulang 1 gaya copywriting spesifik saja.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/ai_content.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

$brand = require_admin_for_brand(get_current_brand());

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (!hash_equals($_SESSION['csrf_token'] ?? '', $input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Refresh halaman dan coba lagi.']);
    exit;
}

$eventSlug = clean($input['event'] ?? '');
$event = $eventSlug !== '' ? get_event_by_slug($eventSlug) : null;
if (!$event || (int)$event['brand_id'] !== (int)$brand['id']) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan.']);
    exit;
}

$eventTitle = mb_substr(trim(clean($input['event_title'] ?? '')), 0, 150);
if ($eventTitle === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Judul Event wajib diisi.']);
    exit;
}

$styleName = normalize_ai_style(clean($input['style'] ?? ''));
if ($styleName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Gaya copywriting tidak valid.']);
    exit;
}

$format = normalize_ai_format(clean($input['format'] ?? 'whatsapp_broadcast'));

if (!check_ai_rate_limit()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Batas generate konten tercapai. Coba lagi sebentar lagi.']);
    exit;
}

$customContext = mb_substr(trim(clean($input['context'] ?? '')), 0, 500);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? $brand['domain'];
$inviteLink = "{$protocol}://{$host}/buat-link.php?event=" . urlencode($eventSlug);

try {
    $variation = generate_single_style_copy($brand, $event, $eventTitle, $customContext, $inviteLink, $format, $styleName);
    echo json_encode([
        'success' => true,
        'invite_link' => $inviteLink,
        'format' => $format,
        'variation' => $variation,
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $e->getMessage() ?: 'Gagal generate ulang. Coba lagi.']);
}
