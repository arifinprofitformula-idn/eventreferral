<?php
// admin/export-analytics.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int) $brand['id'];

$pdo = get_db();

$allowedRanges = [7, 14, 30, 90];
$rangeDays = (int) ($_GET['range'] ?? 30);
if (!in_array($rangeDays, $allowedRanges, true)) $rangeDays = 30;

$stmt = $pdo->prepare('SELECT slug FROM events WHERE brand_id = ?');
$stmt->execute([$brandId]);
$eventSlugs = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'slug');

$selectedEvent = trim((string) ($_GET['event'] ?? ''));
if ($selectedEvent !== '' && !in_array($selectedEvent, $eventSlugs, true)) {
    $selectedEvent = '';
}

$where = 'brand_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ' . $rangeDays . ' DAY)';
$params = [$brandId];
if ($selectedEvent !== '') {
    $where .= ' AND event_slug = ?';
    $params[] = $selectedEvent;
}

// Ringkasan per hari + event, supaya file tetap ringkas walau data mentah sudah besar
$stmt = $pdo->prepare("
    SELECT
        DATE(created_at) AS tanggal,
        event_slug,
        SUM(event_type = 'pageview') AS pageview,
        COUNT(DISTINCT CASE WHEN event_type = 'pageview' THEN session_id END) AS sesi_unik,
        SUM(event_type = 'scroll_50') AS scroll_50,
        SUM(event_type = 'form_start') AS form_start,
        SUM(event_type = 'form_submit') AS form_submit,
        SUM(device_type = 'mobile' AND event_type = 'pageview') AS mobile,
        SUM(device_type = 'desktop' AND event_type = 'pageview') AS desktop,
        SUM(device_type = 'tablet' AND event_type = 'pageview') AS tablet
    FROM visitor_events
    WHERE {$where}
    GROUP BY tanggal, event_slug
    ORDER BY tanggal ASC, event_slug ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=analitik_' . $brand['slug'] . '_' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");
fputcsv($out, ['Tanggal', 'Event', 'Pageview', 'Sesi Unik', 'Scroll 50%', 'Form Dimulai', 'Form Submit', 'Mobile', 'Desktop', 'Tablet', 'Conversion Rate (%)']);

foreach ($rows as $r) {
    $convRate = $r['pageview'] > 0 ? round($r['form_submit'] / $r['pageview'] * 100, 2) : 0;
    fputcsv($out, [
        $r['tanggal'], $r['event_slug'] ?: $brand['default_event_slug'] ?? 'default',
        (int) $r['pageview'], (int) $r['sesi_unik'], (int) $r['scroll_50'], (int) $r['form_start'], (int) $r['form_submit'],
        (int) $r['mobile'], (int) $r['desktop'], (int) $r['tablet'], $convRate,
    ]);
}
fclose($out);
exit;
