<?php
/**
 * admin/event-insights-export.php
 * Export CSV data insight peserta (kota, sumber info, status peserta, feedback, tema
 * berikutnya) untuk peserta yang konfirmasi hadir — menghormati filter ?event= yang sama
 * dengan event-insights.php.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attendance.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

$infoSourceLabel = attendance_info_source_options();
$participantStatusLabel = attendance_participant_status_options();

$extraFieldsReady = false;
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM event_attendance LIKE 'info_source'");
    $extraFieldsReady = (bool)$columnCheck->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $extraFieldsReady = false;
}
$nextTopicReady = false;
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM event_attendance LIKE 'next_topic_interest'");
    $nextTopicReady = (bool)$columnCheck->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $nextTopicReady = false;
}

$requestedSlug = clean($_GET['event'] ?? '');
$selectedEventSlug = '';
if ($requestedSlug !== '') {
    $checkStmt = $pdo->prepare('SELECT slug FROM events WHERE brand_id = ? AND slug = ? LIMIT 1');
    $checkStmt->execute([$brandId, $requestedSlug]);
    if ($checkStmt->fetchColumn()) {
        $selectedEventSlug = $requestedSlug;
    }
}

$rows = [];
if ($extraFieldsReady || $nextTopicReady) {
    $select = 'l.name, l.email, l.whatsapp, l.kota, e.name AS event_name, ea.check_in_time';
    if ($extraFieldsReady) {
        $select .= ', ea.info_source, ea.participant_status, ea.feedback_notes';
    }
    if ($nextTopicReady) {
        $select .= ', ea.next_topic_interest';
    }

    $where = ['e.brand_id = ?', "ea.attendance_status = 'hadir'"];
    $params = [$brandId];
    if ($selectedEventSlug !== '') {
        $where[] = 'e.slug = ?';
        $params[] = $selectedEventSlug;
    }

    $stmt = $pdo->prepare("
        SELECT {$select}
        FROM leads l
        INNER JOIN event_attendance ea ON ea.registrant_id = l.id
        INNER JOIN events e ON e.id = ea.event_id
        WHERE " . implode(' AND ', $where) . '
        ORDER BY ea.check_in_time DESC
    ');
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=insight_peserta_' . $brand['slug'] . '_' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");
fputcsv($out, ['Nama', 'Email', 'WhatsApp', 'Kota', 'Event', 'Sumber Informasi', 'Status Peserta', 'Tema Berikutnya', 'Feedback', 'Waktu Check-in'], ',', '"', '\\');

foreach ($rows as $r) {
    fputcsv($out, [
        $r['name'],
        $r['email'] ?? '',
        $r['whatsapp'] ?? '',
        $r['kota'],
        $r['event_name'],
        $infoSourceLabel[$r['info_source'] ?? ''] ?? ($r['info_source'] ?? ''),
        $participantStatusLabel[$r['participant_status'] ?? ''] ?? ($r['participant_status'] ?? ''),
        $r['next_topic_interest'] ?? '',
        $r['feedback_notes'] ?? '',
        $r['check_in_time'] ?? '',
    ], ',', '"', '\\');
}

fclose($out);
exit;
