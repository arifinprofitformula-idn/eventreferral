<?php
/**
 * api/generate_ai_landing.php
 * Endpoint admin-only: AI memilih template landing page + mengisi kontennya,
 * lalu mengembalikan HTML preview siap ditampilkan di iframe (belum dipublikasikan).
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

$eventName = mb_substr(trim(clean($input['name'] ?? '')), 0, 150);
if ($eventName === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Nama Event wajib diisi agar landing page tetap sesuai konteks.']);
    exit;
}

if (!check_ai_rate_limit()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Batas generate konten tercapai. Coba lagi sebentar lagi.']);
    exit;
}

$eventBrief = [
    'name' => $eventName,
    'event_day' => mb_substr(trim(clean($input['event_day'] ?? '')), 0, 100),
    'event_time' => mb_substr(trim(clean($input['event_time'] ?? '')), 0, 60),
    'event_location' => mb_substr(trim(clean($input['event_location'] ?? '')), 0, 150),
    'event_speaker' => mb_substr(trim(clean($input['event_speaker'] ?? '')), 0, 100),
    'event_capacity' => mb_substr(trim(clean($input['event_capacity'] ?? '')), 0, 30),
];
$customContext = mb_substr(trim(clean($input['context'] ?? '')), 0, 500);

try {
    $filled = generate_ai_landing_page($brand, $eventBrief, $customContext);
    $html = render_landing_template($filled, $brand, $eventBrief);

    echo json_encode([
        'success' => true,
        'template_key' => $filled['template_key'],
        'template_label' => get_ai_landing_templates()[$filled['template_key']]['label'] ?? $filled['template_key'],
        'accent_color' => $filled['accent_color'],
        'html' => $html,
        'filled' => $filled,
        'event_brief' => $eventBrief,
    ]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $e->getMessage() ?: 'Gagal generate landing page. Coba lagi.']);
}
