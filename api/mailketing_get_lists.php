<?php
/**
 * api/mailketing_get_lists.php
 * Admin-only. Ambil daftar list Mailketing untuk dropdown di halaman pengaturan email.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailketing.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

require_admin_for_brand(get_current_brand());

try {
    $lists = mailketing_get_lists();
    echo json_encode(['success' => true, 'lists' => $lists]);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'Gagal mengambil daftar list dari Mailketing. Cek API token.']);
}
