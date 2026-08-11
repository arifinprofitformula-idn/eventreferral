<?php
/**
 * api/event-testimonials.php
 * Endpoint publik untuk menampilkan 10 testimoni terbaru dari feedback kehadiran event.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

function testimonial_word_count(string $value): int
{
    $value = trim(strip_tags($value));
    if ($value === '') {
        return 0;
    }

    preg_match_all('/\b[\p{L}\p{N}][\p{L}\p{N}\'-]*\b/u', $value, $matches);
    return count($matches[0]);
}

try {
    $brand = get_current_brand();
    if (!$brand) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Brand tidak ditemukan.']);
        exit;
    }

    $pdo = get_db();

    $columnStmt = $pdo->query("SHOW COLUMNS FROM event_attendance LIKE 'feedback_notes'");
    if (!$columnStmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => true, 'testimonials' => []]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            ea.feedback_notes,
            COALESCE(NULLIF(l.name, ''), 'Peserta Event') AS name,
            COALESCE(NULLIF(l.kota, ''), 'Indonesia') AS kota
        FROM event_attendance ea
        INNER JOIN events e ON e.id = ea.event_id
        LEFT JOIN leads l ON l.id = ea.registrant_id
        WHERE e.brand_id = ?
            AND ea.feedback_notes IS NOT NULL
            AND TRIM(ea.feedback_notes) <> ''
        ORDER BY COALESCE(ea.check_in_time, ea.updated_at, ea.created_at) DESC, ea.id DESC
        LIMIT 100
    ");
    $stmt->execute([(int)$brand['id']]);

    $testimonials = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $feedback = trim((string)$row['feedback_notes']);
        if (testimonial_word_count($feedback) < 5) {
            continue;
        }

        $testimonials[] = [
            'name' => (string)$row['name'],
            'city' => (string)$row['kota'],
            'content' => $feedback,
        ];

        if (count($testimonials) >= 10) {
            break;
        }
    }

    echo json_encode(['success' => true, 'testimonials' => $testimonials], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server.']);
}

