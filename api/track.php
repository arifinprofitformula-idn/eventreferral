<?php
/**
 * api/track.php — endpoint tracking first-party (pageview, scroll, form, dsb).
 * Dipanggil dari assets/event-sdk.js lewat navigator.sendBeacon().
 * Hanya melakukan INSERT — tidak ada query berat, supaya response secepat mungkin.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}

$allowedEvents = ['pageview', 'scroll_50', 'scroll_90', 'form_start', 'form_submit', 'cta_click', 'whatsapp_redirect'];
$eventType = $input['event_type'] ?? '';
$sessionId = $input['session_id'] ?? '';

if (!in_array($eventType, $allowedEvents, true)) {
    http_response_code(422);
    echo json_encode(['success' => false]);
    exit;
}
if (!preg_match('/^[0-9a-f-]{36}$/i', $sessionId)) {
    http_response_code(422);
    echo json_encode(['success' => false]);
    exit;
}

$pagePath    = substr(clean($input['page_path'] ?? ''), 0, 255);
$eventSlug   = substr(clean($input['event_slug'] ?? ''), 0, 60) ?: null;
$referrer    = substr(clean($input['referrer_url'] ?? ''), 0, 500) ?: null;
$utmSource   = substr(clean($input['utm_source'] ?? ''), 0, 100) ?: null;
$utmMedium   = substr(clean($input['utm_medium'] ?? ''), 0, 100) ?: null;
$utmCampaign = substr(clean($input['utm_campaign'] ?? ''), 0, 100) ?: null;
$refCode     = substr(clean($input['ref_code'] ?? ''), 0, 20) ?: null;
$deviceType  = in_array($input['device_type'] ?? '', ['mobile', 'tablet', 'desktop'], true) ? $input['device_type'] : 'desktop';

// Resolusi brand — defensif, tidak boleh bikin tracking gagal kalau brand tidak terdeteksi.
$brandId = null;
try {
    $brand = get_current_brand();
    $brandId = $brand['id'] ?? null;
} catch (Exception $e) {
    // diamkan — brand_id tetap NULL, tracking tetap jalan
}

$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . (defined('IP_SALT') ? IP_SALT : ''));

try {
    $pdo = get_db();
    $stmt = $pdo->prepare('
        INSERT INTO visitor_events
        (brand_id, event_slug, session_id, event_type, page_path, referrer_url,
         utm_source, utm_medium, utm_campaign, device_type, ref_code, ip_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $brandId, $eventSlug, $sessionId, $eventType, $pagePath, $referrer,
        $utmSource, $utmMedium, $utmCampaign, $deviceType, $refCode, $ipHash,
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false]);
}
