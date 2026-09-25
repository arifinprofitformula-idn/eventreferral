<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/lms_core.php';

start_secure_session();
header('Content-Type: application/json; charset=utf-8');

function add_category_json_response(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    add_category_json_response(405, ['ok' => false, 'message' => 'Method tidak diizinkan.']);
}

try {
    $brand = require_admin_for_brand(get_current_brand());
    $brandId = (int)$brand['id'];
    $pdo = get_db();

    lms_ensure_schema($pdo);
    lms_ensure_course_builder_schema($pdo);
    lms_add_column_if_missing($pdo, 'lms_categories', 'description', 'ALTER TABLE lms_categories ADD COLUMN description TEXT NULL AFTER slug');

    $csrfToken = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrfToken)) {
        add_category_json_response(403, ['ok' => false, 'message' => 'Sesi tidak valid. Silakan refresh halaman.']);
    }

    if (empty($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], ['admin', 'superadmin'], true)) {
        add_category_json_response(403, ['ok' => false, 'message' => 'Hanya Admin yang bisa menambahkan kategori.']);
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));

    $name = preg_replace('/\s+/', ' ', strip_tags($name));
    $description = trim(strip_tags($description));

    if ($name === '') {
        add_category_json_response(422, ['ok' => false, 'message' => 'Nama kategori wajib diisi.']);
    }

    if (mb_strlen($name) > 100) {
        add_category_json_response(422, ['ok' => false, 'message' => 'Nama kategori maksimal 100 karakter.']);
    }

    if (mb_strlen($description) > 1000) {
        add_category_json_response(422, ['ok' => false, 'message' => 'Deskripsi kategori maksimal 1000 karakter.']);
    }

    $slug = slugify($name);
    if ($slug === '') {
        add_category_json_response(422, ['ok' => false, 'message' => 'Nama kategori tidak valid.']);
    }

    $stmt = $pdo->prepare('SELECT id FROM lms_categories WHERE brand_id = ? AND slug = ? LIMIT 1');
    $stmt->execute([$brandId, $slug]);
    if ($stmt->fetchColumn()) {
        add_category_json_response(409, ['ok' => false, 'message' => 'Nama kategori sudah digunakan. Gunakan nama lain.']);
    }

    $stmt = $pdo->prepare('INSERT INTO lms_categories (brand_id, name, slug, description, status) VALUES (?, ?, ?, ?, "active")');
    $stmt->execute([$brandId, $name, $slug, $description !== '' ? $description : null]);

    $categoryId = (int)$pdo->lastInsertId();
    add_category_json_response(201, [
        'ok' => true,
        'message' => 'Kategori berhasil ditambahkan.',
        'category' => [
            'id' => $categoryId,
            'name' => $name,
            'description' => $description,
        ],
    ]);
} catch (Throwable $e) {
    error_log('[LMS] add-category gagal: ' . $e->getMessage());
    add_category_json_response(500, ['ok' => false, 'message' => 'Kategori gagal disimpan. Coba lagi.']);
}
