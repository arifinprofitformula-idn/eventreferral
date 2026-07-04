<?php
/**
 * api/submit_lead.php — versi dengan dukungan "extra fields" opsional
 * PATCH: ganti file api/submit_lead.php yang sudah ada di server dengan isi ini.
 * Syarat: sudah jalankan migrate_v4_extra_fields.sql lebih dulu.
 *
 * Perubahan dari versi sebelumnya HANYA di bagian yang ditandai "== EXTRA FIELDS ==".
 * Semua event lama (yang tidak kirim field "extra") tetap jalan normal,
 * extra_fields akan tersimpan NULL untuk mereka.
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$name     = clean($input['name'] ?? '');
$email    = clean($input['email'] ?? '');
$wa       = clean($input['whatsapp'] ?? '');
$kota     = clean($input['kota'] ?? '');
$refCode  = clean($input['ref'] ?? '');
$eventSlug = clean($input['event'] ?? DEFAULT_EVENT_SLUG);
if ($eventSlug === '') $eventSlug = DEFAULT_EVENT_SLUG;

// == EXTRA FIELDS == — terima object "extra" (opsional), batasi supaya tidak disalahgunakan
$extraFieldsJson = null;
if (!empty($input['extra']) && is_array($input['extra'])) {
    $extraClean = [];
    $count = 0;
    foreach ($input['extra'] as $key => $val) {
        if ($count >= 20) break; // batas wajar, cegah payload raksasa
        $keyClean = preg_replace('/[^a-z0-9_]/i', '', (string) $key);
        if ($keyClean === '') continue;
        $extraClean[$keyClean] = clean((string) $val);
        $count++;
    }
    if (!empty($extraClean)) {
        $extraFieldsJson = json_encode($extraClean, JSON_UNESCAPED_UNICODE);
    }
}
// == END EXTRA FIELDS ==

// ==== VALIDASI ====
$errors = [];
if ($name === '' || mb_strlen($name) < 3) {
    $errors[] = 'Nama lengkap minimal 3 karakter.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email tidak valid.';
}
$waNormalized = normalize_whatsapp($wa);
if (strlen($waNormalized) < 10 || strlen($waNormalized) > 15) {
    $errors[] = 'Nomor WhatsApp tidak valid.';
}
if ($kota === '' || mb_strlen($kota) < 2) {
    $errors[] = 'Kota domisili wajib diisi.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = get_db();

    $event = get_event_by_slug($eventSlug);
    if (!$event || $event['status'] !== 'active') {
        $eventSlug = DEFAULT_EVENT_SLUG;
        $event = get_event_by_slug($eventSlug);
    }

    $referrer = null;
    if ($refCode !== '') {
        $stmt = $pdo->prepare('SELECT ref_code, name, whatsapp FROM referrers WHERE event_slug = ? AND ref_code = ?');
        $stmt->execute([$eventSlug, $refCode]);
        $referrer = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $targetName = $referrer ? $referrer['name'] : null;
    $targetWa = $referrer ? $referrer['whatsapp'] : ($event['whatsapp_default'] ?? '');

    // == EXTRA FIELDS == — tambah kolom extra_fields ke INSERT
    $stmt = $pdo->prepare(
        'INSERT INTO leads (name, email, whatsapp, kota, extra_fields, ref_code, event_slug) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $waNormalized, $kota, $extraFieldsJson, ($refCode !== '' ? $refCode : null), $eventSlug]);
    // == END EXTRA FIELDS ==

    $eventName = $event['name'] ?? 'Rahasia Emas';
    $waMessage = "Halo" . ($targetName ? " {$targetName}" : "") . "! 👋\n"
        . "Saya sudah daftar acara *{$eventName}*:\n\n"
        . "Nama: {$name}\n"
        . "Kota: {$kota}\n\n"
        . "Mohon info selanjutnya ya. Terima kasih! 🙏";

    echo json_encode([
        'success' => true,
        'message' => 'Pendaftaran berhasil.',
        'redirect_whatsapp' => $targetWa,
        'whatsapp_text' => $waMessage,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.']);
}
