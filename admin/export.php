<?php
require_once __DIR__ . '/../config.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());

$pdo = get_db();
$stmt = $pdo->prepare('
    SELECT l.name, l.email, l.whatsapp, l.kota, l.ref_code, r.name AS referrer_name, e.name AS event_name, l.created_at
    FROM leads l
    LEFT JOIN referrers r ON r.event_slug = l.event_slug AND r.ref_code = l.ref_code AND r.brand_id = l.brand_id
    LEFT JOIN events e ON e.slug = l.event_slug AND e.brand_id = l.brand_id
    WHERE l.brand_id = ?
    ORDER BY l.created_at DESC
');
$stmt->execute([(int)$brand['id']]);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=pendaftar_' . $brand['slug'] . '_' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM agar Excel baca UTF-8 dengan benar
fputcsv($out, ['Nama', 'Email', 'WhatsApp', 'Kota', 'Kode Referral', 'Event', 'Diundang Oleh', 'Waktu Daftar'], ',', '"', '\\');

foreach ($leads as $l) {
    fputcsv($out, [
        $l['name'],
        $l['email'],
        $l['whatsapp'],
        $l['kota'],
        $l['ref_code'],
        $l['event_name'] ?? '-',
        $l['referrer_name'] ?? '-',
        $l['created_at'],
    ], ',', '"', '\\');
}

fclose($out);
exit;
