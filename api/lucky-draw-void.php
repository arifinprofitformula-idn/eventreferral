<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

function lucky_draw_void_json(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lucky_draw_void_json(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

$brand = get_current_brand();
if (!$brand) {
    lucky_draw_void_json(['success' => false, 'message' => 'Brand tidak ditemukan.'], 404);
}

$isSuperadmin = !empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin';
$isBrandAdmin = !empty($_SESSION['admin_brand_id']) && (int)$_SESSION['admin_brand_id'] === (int)$brand['id'];
if (!$isSuperadmin && !$isBrandAdmin) {
    lucky_draw_void_json(['success' => false, 'message' => 'Sesi admin tidak valid. Silakan login ulang.'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrfToken = (string)($input['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    lucky_draw_void_json(['success' => false, 'message' => 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.'], 403);
}

$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;
if ($sessionId <= 0) {
    lucky_draw_void_json(['success' => false, 'message' => 'Session undian tidak valid.'], 422);
}

try {
    $pdo = get_db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        SELECT lds.id
        FROM lucky_draw_sessions lds
        JOIN events e ON e.id = lds.event_id
        WHERE lds.id = ? AND lds.status IN ("drawing","revealed") AND e.brand_id = ?
        FOR UPDATE
    ');
    $stmt->execute([$sessionId, (int)$brand['id']]);
    if (!$stmt->fetchColumn()) {
        $pdo->rollBack();
        lucky_draw_void_json(['success' => false, 'message' => 'Sesi tidak bisa dibatalkan atau bukan milik brand ini.'], 409);
    }

    $stmt = $pdo->prepare('UPDATE lucky_draw_sessions SET status = "voided" WHERE id = ?');
    $stmt->execute([$sessionId]);

    $stmt = $pdo->prepare('UPDATE lucky_draw_winners SET status = "voided" WHERE session_id = ?');
    $stmt->execute([$sessionId]);

    $pdo->commit();
    lucky_draw_void_json(['success' => true, 'message' => 'Sesi dibatalkan. Peserta kembali eligible.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[LUCKY_DRAW_VOID_ERROR] ' . $e->getMessage());
    lucky_draw_void_json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
}
