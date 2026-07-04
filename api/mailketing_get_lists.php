<?php
/**
 * api/mailketing_get_lists.php
 * Admin-only. Ambil daftar list Mailketing untuk dropdown di halaman pengaturan email.
 */

ob_start();

function mailketing_lists_json(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailketing.php';
start_secure_session();

$brand = get_current_brand();
if (!$brand) {
    mailketing_lists_json([
        'success' => false,
        'connected' => false,
        'message' => 'Brand aktif tidak ditemukan untuk domain ini.',
    ], 404);
}

if (empty($_SESSION['admin_brand_id']) || (int)$_SESSION['admin_brand_id'] !== (int)$brand['id']) {
    mailketing_lists_json([
        'success' => false,
        'connected' => false,
        'message' => 'Sesi admin tidak aktif. Silakan login ulang lalu muat ulang daftar list.',
    ], 401);
}

try {
    $lists = mailketing_get_lists();
    mailketing_lists_json([
        'success' => true,
        'connected' => true,
        'message' => 'Integrasi Mailketing terhubung.',
        'list_count' => count($lists),
        'lists' => $lists,
    ]);
} catch (Throwable $e) {
    error_log('[Mailketing] Gagal mengambil daftar list: ' . $e->getMessage());
    mailketing_lists_json([
        'success' => false,
        'connected' => false,
        'message' => $e->getMessage(),
    ], 200);
}
