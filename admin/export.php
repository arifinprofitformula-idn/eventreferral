<?php
require_once __DIR__ . '/../config.php';
session_start();

if (empty($_SESSION['admin_authenticated'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_db();
$leads = $pdo->query('
    SELECT l.name, l.email, l.whatsapp, l.kota, l.ref_code, l.event_slug, l.extra_fields, r.name AS referrer_name, l.created_at
    FROM leads l
    LEFT JOIN referrers r ON r.event_slug = l.event_slug AND r.ref_code = l.ref_code
    ORDER BY l.created_at DESC
')->fetchAll(PDO::FETCH_ASSOC);

/** Ubah JSON extra_fields jadi teks ringkas untuk kolom CSV */
function format_extra_fields_csv(?string $json): string {
    if (!$json) return '';
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data)) return '';
    $parts = [];
    foreach ($data as $key => $val) {
        $parts[] = ucwords(str_replace('_', ' ', $key)) . ': ' . ucwords(str_replace('_', ' ', (string) $val));
    }
    return implode(' | ', $parts);
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=pendaftar_rahasiaemas_' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM agar Excel baca UTF-8 dengan benar
fputcsv($out, ['Nama', 'Email', 'WhatsApp', 'Kota', 'Kode Referral', 'Event', 'Info Tambahan', 'Diundang Oleh', 'Waktu Daftar']);

foreach ($leads as $l) {
    fputcsv($out, [
        $l['name'],
        $l['email'],
        $l['whatsapp'],
        $l['kota'],
        $l['ref_code'],
        $l['event_slug'],
        format_extra_fields_csv($l['extra_fields'] ?? null),
        $l['referrer_name'] ?? '-',
        $l['created_at'],
    ]);
}

fclose($out);
exit;
