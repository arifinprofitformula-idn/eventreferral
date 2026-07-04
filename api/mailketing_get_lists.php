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

$brand = get_current_brand();
if (!$brand) {
    echo json_encode(['success' => false, 'message' => 'Brand aktif tidak ditemukan untuk domain ini.']);
    exit;
}

if (empty($_SESSION['admin_brand_id']) || (int)$_SESSION['admin_brand_id'] !== (int)$brand['id']) {
    echo json_encode(['success' => false, 'message' => 'Sesi admin tidak aktif. Silakan login ulang lalu muat ulang daftar list.']);
    exit;
}

try {
    $lists = mailketing_get_lists();
    echo json_encode(['success' => true, 'lists' => $lists]);
} catch (Throwable $e) {
    error_log('[Mailketing] Gagal mengambil daftar list: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
