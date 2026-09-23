<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/lms_core.php';
start_secure_session();

$brand = get_current_brand();
if (!$brand) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Brand tidak ditemukan.']);
    exit;
}

$brandId = (int)$brand['id'];
$pdo = get_db();
lms_ensure_schema($pdo);

$user = lms_get_logged_user($pdo, $brandId);
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Anda harus login untuk menyimpan progres.']);
    exit;
}

if (empty($_SESSION['lms_csrf_token']) || !hash_equals($_SESSION['lms_csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sesi tidak valid. Silakan refresh halaman.']);
    exit;
}

$lessonId = (int)($_POST['lesson_id'] ?? 0);
if ($lessonId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID materi tidak valid.']);
    exit;
}

// Validasi lesson milik brand yang sama & user berhak akses (jangan percaya client)
$stmt = $pdo->prepare('
    SELECT l.id, l.is_premium, c.id AS course_id, c.brand_id, c.access_type
    FROM lms_lessons l
    JOIN lms_courses c ON c.id = l.course_id
    WHERE l.id = ?
');
$stmt->execute([$lessonId]);
$lessonCourse = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lessonCourse || (int)$lessonCourse['brand_id'] !== $brandId) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Materi tidak ditemukan.']);
    exit;
}

$courseId = (int)$lessonCourse['course_id'];

$stmtEnr = $pdo->prepare('SELECT access_status FROM lms_enrollments WHERE user_id = ? AND course_id = ? AND access_status = "active"');
$stmtEnr->execute([(int)$user['id'], $courseId]);
$isEnrolled = (bool)$stmtEnr->fetchColumn();

if (!lms_can_access_course($user, $lessonCourse, $isEnrolled)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Akses ditolak. Upgrade ke Paid User untuk melanjutkan materi ini.']);
    exit;
}

$result = lms_mark_lesson_completed($pdo, (int)$user['id'], $lessonId);

if (!$result['ok']) {
    http_response_code(400);
    echo json_encode($result);
    exit;
}

echo json_encode($result);
