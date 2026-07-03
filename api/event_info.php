<?php
/**
 * api/event_info.php
 * Endpoint publik (read-only) — dipanggil oleh assets/event-sdk.js
 * untuk mengambil detail acara + info pengundang (jika ada ?ref=).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$brand = get_current_brand();
if (!$brand) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan atau sudah tidak aktif.']);
    exit;
}

$slug = clean($_GET['event'] ?? $brand['default_event_slug']);
$refCode = clean($_GET['ref'] ?? '');
if ($slug === '') $slug = $brand['default_event_slug'];

try {
    $pdo = get_db();

    // Validasi brand_id WAJIB — mencegah domain brand A membaca data event milik brand B
    // lewat tebakan slug, walau folder /e/ dibagi bersama semua domain (lihat DEPLOYMENT.md).
    $stmt = $pdo->prepare("SELECT * FROM events WHERE slug = ? AND brand_id = ? AND status = 'active'");
    $stmt->execute([$slug, $brand['id']]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan atau sudah tidak aktif.']);
        exit;
    }

    $referrer = null;
    if ($refCode !== '') {
        $stmt = $pdo->prepare('SELECT name, whatsapp FROM referrers WHERE brand_id = ? AND event_slug = ? AND ref_code = ?');
        $stmt->execute([$brand['id'], $slug, $refCode]);
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
