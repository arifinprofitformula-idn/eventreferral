<?php
/**
 * referrer/export.php
 * Export CSV lead milik pengundang yang sedang login saja.
 * Menghormati filter tanggal (date_from/date_to) yang sama dengan dashboard.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/referrer_auth.php';
start_secure_session();

$brand = require_referrer_login(get_current_brand());
$brandId = (int)$brand['id'];

$pdo = get_db();
$myLinks = get_referrer_rows_for_session($pdo, $brand);
if (empty($myLinks)) {
    header('Location: /referrer/login.php');
    exit;
}

$dateFrom = clean($_GET['date_from'] ?? '');
$dateTo = clean($_GET['date_to'] ?? '');

$conditions = [];
$params = [$brandId];
foreach ($myLinks as $link) {
    $conditions[] = '(l.ref_code = ? AND l.event_slug = ?)';
    $params[] = $link['ref_code'];
    $params[] = $link['event_slug'];
}
$whereClause = implode(' OR ', $conditions);
$sql = "SELECT l.name, l.email, l.whatsapp, l.kota, l.followup_status, l.event_slug, l.created_at
        FROM leads l
        WHERE l.brand_id = ? AND ($whereClause)";

if ($dateFrom !== '') {
    $sql .= ' AND l.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $sql .= ' AND l.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}
$sql .= ' ORDER BY l.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusLabel = ['baru' => 'Baru', 'dihubungi' => 'Sudah Dihubungi', 'closing' => 'Closing'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=pendaftar_saya_' . $brand['slug'] . '_' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");
fputcsv($out, ['Nama', 'Email', 'WhatsApp', 'Kota', 'Status Follow-up', 'Event', 'Waktu Daftar'], ',', '"', '\\');

foreach ($leads as $l) {
    fputcsv($out, [
        $l['name'],
        $l['email'],
        $l['whatsapp'],
        $l['kota'],
        $statusLabel[$l['followup_status']] ?? $l['followup_status'],
        $l['event_slug'],
        $l['created_at'],
    ], ',', '"', '\\');
}

fclose($out);
exit;
