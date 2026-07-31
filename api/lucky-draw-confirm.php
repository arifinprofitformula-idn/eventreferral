<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

function lucky_draw_confirm_json(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lucky_draw_confirm_json(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

$brand = get_current_brand();
if (!$brand) {
    lucky_draw_confirm_json(['success' => false, 'message' => 'Brand tidak ditemukan.'], 404);
}

$isSuperadmin = !empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin';
$isBrandAdmin = !empty($_SESSION['admin_brand_id']) && (int)$_SESSION['admin_brand_id'] === (int)$brand['id'];
if (!$isSuperadmin && !$isBrandAdmin) {
    lucky_draw_confirm_json(['success' => false, 'message' => 'Sesi admin tidak valid. Silakan login ulang.'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrfToken = (string)($input['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    lucky_draw_confirm_json(['success' => false, 'message' => 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.'], 403);
}

$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
if ($sessionId <= 0) {
    lucky_draw_confirm_json(['success' => false, 'message' => 'Session undian tidak valid.'], 422);
}

try {
    $pdo = get_db();
    $stmt = $pdo->prepare('
        UPDATE lucky_draw_sessions lds
        JOIN events e ON e.id = lds.event_id
        SET lds.status = "confirmed"
        WHERE lds.id = ? AND lds.status = "revealed" AND e.brand_id = ?
    ');
    $stmt->execute([$sessionId, (int)$brand['id']]);

    if ($stmt->rowCount() < 1) {
        lucky_draw_confirm_json(['success' => false, 'message' => 'Sesi belum revealed, sudah diproses, atau bukan milik brand ini.'], 409);
    }

    lucky_draw_confirm_json(['success' => true, 'message' => 'Pemenang resmi dikunci.']);
} catch (Throwable $e) {
    error_log('[LUCKY_DRAW_CONFIRM_ERROR] ' . $e->getMessage());
    lucky_draw_confirm_json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
}
