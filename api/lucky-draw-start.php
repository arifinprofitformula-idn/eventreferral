<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

function lucky_draw_json(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function lucky_draw_admin_brand(): array {
    $brand = get_current_brand();
    if (!$brand) {
        lucky_draw_json(['success' => false, 'message' => 'Brand tidak ditemukan.'], 404);
    }

    $isSuperadmin = !empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin';
    $isBrandAdmin = !empty($_SESSION['admin_brand_id']) && (int)$_SESSION['admin_brand_id'] === (int)$brand['id'];
    if (!$isSuperadmin && !$isBrandAdmin) {
        lucky_draw_json(['success' => false, 'message' => 'Sesi admin tidak valid. Silakan login ulang.'], 401);
    }

    return $brand;
}

function lucky_draw_input(): array {
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : $_POST;
}

function lucky_draw_require_csrf(array $input): void {
    $csrfToken = (string)($input['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        lucky_draw_json(['success' => false, 'message' => 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.'], 403);
    }
}

function lucky_draw_find_event(PDO $pdo, array $brand, $eventValue): array {
    $eventText = trim((string)$eventValue);
    if ($eventText === '') {
        lucky_draw_json(['success' => false, 'message' => 'Event wajib dipilih.'], 422);
    }

    if (ctype_digit($eventText)) {
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ? AND brand_id = ? LIMIT 1');
        $stmt->execute([(int)$eventText, (int)$brand['id']]);
    } else {
        $eventSlug = clean($eventText);
        $stmt = $pdo->prepare('SELECT * FROM events WHERE slug = ? AND brand_id = ? LIMIT 1');
        $stmt->execute([$eventSlug, (int)$brand['id']]);
    }

    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        lucky_draw_json(['success' => false, 'message' => 'Event tidak ditemukan atau bukan milik brand ini.'], 404);
    }

    return $event;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lucky_draw_json(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
}

$brand = lucky_draw_admin_brand();
$input = lucky_draw_input();
lucky_draw_require_csrf($input);

$prizeName = trim((string)($input['prize_name'] ?? ''));
$winnersCount = isset($input['winners_count']) ? (int)$input['winners_count'] : 1;
$durationSeconds = isset($input['duration_seconds']) ? (int)$input['duration_seconds'] : 10;

if ($prizeName === '' || mb_strlen($prizeName) > 190) {
    lucky_draw_json(['success' => false, 'message' => 'Nama hadiah wajib diisi, maksimal 190 karakter.'], 422);
}
if ($winnersCount < 1 || $winnersCount > 50) {
    lucky_draw_json(['success' => false, 'message' => 'Jumlah pemenang harus 1 sampai 50.'], 422);
}
if ($durationSeconds < 3 || $durationSeconds > 120) {
    lucky_draw_json(['success' => false, 'message' => 'Durasi animasi harus 3 sampai 120 detik.'], 422);
}

try {
    $pdo = get_db();
    $event = lucky_draw_find_event($pdo, $brand, $input['event'] ?? '');
    $eventId = (int)$event['id'];
    $adminId = isset($_SESSION['admin_user_id']) ? (int)$_SESSION['admin_user_id'] : null;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM lucky_draw_sessions WHERE event_id = ? AND status IN ('drawing','revealed') ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$eventId]);
    if ($stmt->fetchColumn()) {
        $pdo->rollBack();
        lucky_draw_json(['success' => false, 'message' => 'Masih ada sesi undian aktif. Konfirmasi atau batalkan dulu.'], 409);
    }

    $stmt = $pdo->prepare('
        SELECT ea.id AS attendance_id, ea.registrant_id, l.name
        FROM event_attendance ea
        JOIN leads l ON l.id = ea.registrant_id
        LEFT JOIN lucky_draw_winners ldw
            ON ldw.event_id = ea.event_id
            AND ldw.registrant_id = ea.registrant_id
            AND ldw.status = "confirmed"
        WHERE ea.event_id = ?
            AND ea.attendance_status = "hadir"
            AND l.brand_id = ?
            AND ldw.id IS NULL
        ORDER BY ea.id ASC
        FOR UPDATE
    ');
    $stmt->execute([$eventId, (int)$brand['id']]);
    $eligible = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($eligible) < $winnersCount) {
        $pdo->rollBack();
        lucky_draw_json([
            'success' => false,
            'message' => 'Peserta eligible tidak cukup untuk jumlah pemenang yang diminta.',
            'eligible_count' => count($eligible),
        ], 422);
    }

    $chosen = [];
    while (count($chosen) < $winnersCount) {
        $index = random_int(0, count($eligible) - 1);
        $chosen[] = $eligible[$index];
        array_splice($eligible, $index, 1);
    }

    $stmt = $pdo->prepare('
        INSERT INTO lucky_draw_sessions
            (event_id, prize_name, winners_count, duration_seconds, status, draw_started_at, reveal_at, created_by_admin_id)
        VALUES (?, ?, ?, ?, "drawing", NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), ?)
    ');
    $stmt->execute([$eventId, $prizeName, $winnersCount, $durationSeconds, $durationSeconds, $adminId]);
    $sessionId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('
        INSERT INTO lucky_draw_winners
            (session_id, event_id, registrant_id, attendance_id, prize_name, drawn_at, status)
        VALUES (?, ?, ?, ?, ?, NOW(), "confirmed")
    ');
    foreach ($chosen as $winner) {
        $stmt->execute([$sessionId, $eventId, (int)$winner['registrant_id'], (int)$winner['attendance_id'], $prizeName]);
    }

    error_log(sprintf(
        '[LUCKY_DRAW_START] admin_id=%s brand_id=%d event_id=%d session_id=%d winners_count=%d at=%s',
        $adminId === null ? 'unknown' : (string)$adminId,
        (int)$brand['id'],
        $eventId,
        $sessionId,
        $winnersCount,
        date('c')
    ));

    $pdo->commit();

    lucky_draw_json([
        'success' => true,
        'session_id' => $sessionId,
        'status' => 'drawing',
        'reveal_at' => null,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[LUCKY_DRAW_START_ERROR] ' . $e->getMessage());
    lucky_draw_json(['success' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.'], 500);
}
