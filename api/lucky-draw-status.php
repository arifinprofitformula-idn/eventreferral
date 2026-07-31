<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

function lucky_draw_status_json(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function lucky_draw_status_event(PDO $pdo, array $brand, $eventValue): array {
    $eventText = trim((string)$eventValue);
    if ($eventText === '') {
        lucky_draw_status_json(['success' => false, 'message' => 'Event wajib dipilih.'], 422);
    }

    if (ctype_digit($eventText)) {
        $stmt = $pdo->prepare('SELECT id, slug, name, brand_id FROM events WHERE id = ? AND brand_id = ? LIMIT 1');
        $stmt->execute([(int)$eventText, (int)$brand['id']]);
    } else {
        $eventSlug = clean($eventText);
        $stmt = $pdo->prepare('SELECT id, slug, name, brand_id FROM events WHERE slug = ? AND brand_id = ? LIMIT 1');
        $stmt->execute([$eventSlug, (int)$brand['id']]);
    }

    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$event) {
        lucky_draw_status_json(['success' => false, 'message' => 'Event tidak ditemukan.'], 404);
    }

    return $event;
}

try {
    $brand = get_current_brand();
    if (!$brand) {
        lucky_draw_status_json(['success' => false, 'message' => 'Brand tidak ditemukan.'], 404);
    }

    $pdo = get_db();
    $event = lucky_draw_status_event($pdo, $brand, $_GET['event'] ?? ($_GET['event_id'] ?? ''));
    $eventId = (int)$event['id'];

    $stmt = $pdo->prepare("
        SELECT id, prize_name, winners_count, duration_seconds, status,
            draw_started_at, reveal_at,
            GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), reveal_at)) AS seconds_remaining
        FROM lucky_draw_sessions
        WHERE event_id = ? AND status IN ('drawing','revealed')
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$eventId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*)
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
        ');
        $stmt->execute([$eventId, (int)$brand['id']]);

        lucky_draw_status_json([
            'success' => true,
            'status' => 'idle',
            'event_id' => $eventId,
            'event_slug' => $event['slug'],
            'event_name' => $event['name'],
            'eligible_count' => (int)$stmt->fetchColumn(),
        ]);
    }

    if ($session['status'] === 'drawing') {
        $stmt = $pdo->prepare('SELECT NOW() >= reveal_at FROM lucky_draw_sessions WHERE id = ?');
        $stmt->execute([(int)$session['id']]);
        $shouldReveal = (bool)$stmt->fetchColumn();

        if (!$shouldReveal) {
            echo json_encode(['status' => 'drawing']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE lucky_draw_sessions SET status = "revealed" WHERE id = ? AND status = "drawing"');
        $stmt->execute([(int)$session['id']]);
        $session['status'] = 'revealed';
    }

    $stmt = $pdo->prepare('
        SELECT l.name, ldw.prize_name
        FROM lucky_draw_winners ldw
        JOIN leads l ON l.id = ldw.registrant_id
        WHERE ldw.session_id = ? AND ldw.status = "confirmed"
        ORDER BY ldw.id ASC
    ');
    $stmt->execute([(int)$session['id']]);
    $winners = array_map(static function ($row) {
        return [
            'name' => $row['name'],
            'prize_name' => $row['prize_name'],
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    lucky_draw_status_json([
        'success' => true,
        'status' => 'revealed',
        'session_id' => (int)$session['id'],
        'prize_name' => $session['prize_name'],
        'winners' => $winners,
    ]);
} catch (Throwable $e) {
    error_log('[LUCKY_DRAW_STATUS_ERROR] ' . $e->getMessage());
    lucky_draw_status_json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
}
