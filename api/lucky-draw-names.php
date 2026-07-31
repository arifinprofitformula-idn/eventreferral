<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

function lucky_draw_names_json(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function lucky_draw_public_label(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? 'Peserta';
    $initials = '';
    for ($i = 1; $i < min(count($parts), 3); $i++) {
        $initials .= ' ' . mb_strtoupper(mb_substr($parts[$i], 0, 1)) . '.';
    }
    return trim($first . $initials);
}

try {
    $brand = get_current_brand();
    if (!$brand) {
        lucky_draw_names_json(['success' => false, 'message' => 'Brand tidak ditemukan.'], 404);
    }

    $pdo = get_db();
    $eventText = trim((string)($_GET['event'] ?? ($_GET['event_id'] ?? '')));
    if ($eventText === '') {
        lucky_draw_names_json(['success' => false, 'message' => 'Event wajib dipilih.'], 422);
    }

    if (ctype_digit($eventText)) {
        $stmt = $pdo->prepare('SELECT id FROM events WHERE id = ? AND brand_id = ? LIMIT 1');
        $stmt->execute([(int)$eventText, (int)$brand['id']]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM events WHERE slug = ? AND brand_id = ? LIMIT 1');
        $stmt->execute([clean($eventText), (int)$brand['id']]);
    }
    $eventId = (int)$stmt->fetchColumn();
    if ($eventId <= 0) {
        lucky_draw_names_json(['success' => false, 'message' => 'Event tidak ditemukan.'], 404);
    }

    $stmt = $pdo->prepare('
        SELECT l.name
        FROM event_attendance ea
        JOIN leads l ON l.id = ea.registrant_id
        LEFT JOIN lucky_draw_winners ldw
            ON ldw.event_id = ea.event_id
            AND ldw.registrant_id = ea.registrant_id
            AND ldw.status = "confirmed"
        LEFT JOIN lucky_draw_sessions lds
            ON lds.id = ldw.session_id
            AND lds.status = "confirmed"
        WHERE ea.event_id = ?
            AND ea.attendance_status = "hadir"
            AND l.brand_id = ?
            AND lds.id IS NULL
        ORDER BY l.name ASC
        LIMIT 500
    ');
    $stmt->execute([$eventId, (int)$brand['id']]);
    $names = array_map(static fn ($row) => lucky_draw_public_label((string)$row['name']), $stmt->fetchAll(PDO::FETCH_ASSOC));

    lucky_draw_names_json(['success' => true, 'names' => $names]);
} catch (Throwable $e) {
    error_log('[LUCKY_DRAW_NAMES_ERROR] ' . $e->getMessage());
    lucky_draw_names_json(['success' => false, 'message' => 'Terjadi kesalahan server.'], 500);
}
