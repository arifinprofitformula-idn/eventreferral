<?php
require_once __DIR__ . '/../config.php';
start_secure_session();

if (empty($_SESSION['admin_authenticated'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_db();
$leads = $pdo->query('
    SELECT l.name, l.email, l.whatsapp, l.kota, l.ref_code, r.name AS referrer_name, l.created_at
    FROM leads l
    LEFT JOIN referrers r ON r.ref_code = l.ref_code
    ORDER BY l.created_at DESC
')->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=pendaftar_rahasiaemas_' . date('Y-m-d') . '.csv');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // BOM agar Excel baca UTF-8 dengan benar
fputcsv($out, ['Nama', 'Email', 'WhatsApp', 'Kota', 'Kode Referral', 'Diundang Oleh', 'Waktu Daftar']);

foreach ($leads as $l) {
    fputcsv($out, [
        $l['name'],
        $l['email'],
        $l['whatsapp'],
        $l['kota'],
        $l['ref_code'],
        $l['referrer_name'] ?? '-',
        $l['created_at'],
    ]);
}

fclose($out);
exit;
