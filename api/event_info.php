<?php
/**
 * api/event_info.php
 * Endpoint publik (read-only) — dipanggil oleh rahasiaemas-sdk.js
 * untuk mengambil detail acara + info pengundang (jika ada ?ref=).
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$slug = clean($_GET['event'] ?? DEFAULT_EVENT_SLUG);
$refCode = clean($_GET['ref'] ?? '');
if ($slug === '') $slug = DEFAULT_EVENT_SLUG;

try {
    $pdo = get_db();

    $stmt = $pdo->prepare("SELECT * FROM events WHERE slug = ? AND status = 'active'");
    $stmt->execute([$slug]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan atau sudah tidak aktif.']);
        exit;
    }

    $referrer = null;
    if ($refCode !== '') {
        $stmt = $pdo->prepare('SELECT name, whatsapp FROM referrers WHERE event_slug = ? AND ref_code = ?');
        $stmt->execute([$slug, $refCode]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode([
        'success' => true,
        'event' => [
            'slug'            => $event['slug'],
            'event_name'      => $event['name'],
            'event_day'       => $event['event_day'],
            'event_time'      => $event['event_time'],
            'event_location'  => $event['event_location'],
            'event_speaker'   => $event['event_speaker'],
            'event_capacity'  => $event['event_capacity'],
            'meta_pixel_id'   => $event['meta_pixel_id'],
            'ga_measurement_id' => $event['ga_measurement_id'],
        ],
        'referrer' => $referrer ? ['name' => $referrer['name']] : null,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server.']);
}
