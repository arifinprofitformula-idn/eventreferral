<?php
/**
 * api/publish_ai_landing.php
 * Endpoint admin-only: publikasikan HTML landing page yang sudah di-generate & di-preview
 * oleh AI (lihat api/generate_ai_landing.php). Tidak generate ulang — html yang dikirim
 * client adalah PERSIS yang sudah di-preview, supaya hasil publish sama dengan preview.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];

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

$html = (string)($input['html'] ?? '');
$cfgInput = is_array($input['config'] ?? null) ? $input['config'] : [];
$eventName = trim((string)($cfgInput['name'] ?? ''));

if ($html === '' || $eventName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Data landing page tidak lengkap. Generate ulang lalu coba lagi.']);
    exit;
}

$slugOverride = slugify(clean($input['slug_override'] ?? ''));
$allowOverwrite = !empty($input['allow_overwrite']);
$slug = $slugOverride !== '' ? $slugOverride : slugify($cfgInput['slug'] ?? $eventName);

if (!is_valid_event_slug($slug)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Slug "' . $slug . '" tidak valid. Gunakan huruf kecil, angka, dan strip saja.']);
    exit;
}

$existingEvent = get_event_by_slug($slug);
if ($existingEvent && (int)$existingEvent['brand_id'] !== $brandId) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Slug "' . $slug . '" sudah dipakai oleh brand lain. Gunakan slug lain.']);
    exit;
}

$targetDir = EVENTS_DIR . '/' . $slug;
$existsAlready = is_dir($targetDir) && count(glob($targetDir . '/*')) > 0;
if ($existsAlready && !$allowOverwrite) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Event dengan slug "' . $slug . '" sudah ada. Aktifkan opsi timpa jika ingin memperbarui.']);
    exit;
}

if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal membuat folder tujuan di server.']);
    exit;
}

$indexPath = $targetDir . '/index.html';
if (file_put_contents($indexPath, $html) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menulis file index.html.']);
    exit;
}
inject_sdk_script($indexPath);

$cfg = [
    'slug' => $slug,
    'name' => $eventName,
    'whatsapp' => (string)($cfgInput['whatsapp'] ?? ''),
    'event_day' => (string)($cfgInput['event_day'] ?? ''),
    'event_time' => (string)($cfgInput['event_time'] ?? ''),
    'event_location' => (string)($cfgInput['event_location'] ?? ''),
    'event_speaker' => (string)($cfgInput['event_speaker'] ?? ''),
    'event_capacity' => (string)($cfgInput['event_capacity'] ?? ''),
];
file_put_contents($targetDir . '/config.json', json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$pdo = get_db();
upsert_event_record($pdo, $brandId, $slug, $cfg);

$setAsDefault = !empty($input['set_as_default']);
if ($setAsDefault) {
    $stmt = $pdo->prepare('UPDATE brands SET default_event_slug = ? WHERE id = ?');
    $stmt->execute([$slug, $brandId]);
}

echo json_encode([
    'success' => true,
    'slug' => $slug,
    'name' => $eventName,
    'updated' => $existsAlready,
    'set_as_default' => $setAsDefault,
]);
