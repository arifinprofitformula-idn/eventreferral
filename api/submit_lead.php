<?php
/**
 * api/submit_lead.php
 * Menerima data pendaftaran dari form landing page (fetch/AJAX, JSON).
 * Mengembalikan JSON berisi status + nomor WhatsApp pengundang untuk redirect.
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$brand = get_current_brand();
if (!$brand) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan.']);
    exit;
}
$brandId = (int)$brand['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST; // fallback jika bukan JSON
}

$name      = clean($input['name'] ?? '');
$email     = clean($input['email'] ?? '');
$wa        = clean($input['whatsapp'] ?? '');
$kota      = clean($input['kota'] ?? '');
$refCode   = clean($input['ref'] ?? DEFAULT_REF_CODE);
$eventSlug = clean($input['event'] ?? $brand['default_event_slug']);

$event = get_event_by_slug($eventSlug);
if (!$event || (int)$event['brand_id'] !== $brandId || $event['status'] !== 'active') {
    $eventSlug = $brand['default_event_slug'];
    $event = get_event_by_slug($eventSlug);
}

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

if ($refCode === '') {
    $refCode = DEFAULT_REF_CODE;
}

try {
    $pdo = get_db();

    // Cari data pengundang berdasarkan (brand_id, event_slug, ref_code); fallback ke whatsapp_default milik event jika tidak ditemukan
    $stmt = $pdo->prepare('SELECT ref_code, name, whatsapp FROM referrers WHERE brand_id = ? AND event_slug = ? AND ref_code = ?');
    $stmt->execute([$brandId, $eventSlug, $refCode]);
    $referrer = $stmt->fetch(PDO::FETCH_ASSOC);

    // Simpan lead
    $stmt = $pdo->prepare(
        'INSERT INTO leads (brand_id, name, email, whatsapp, kota, ref_code, event_slug) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$brandId, $name, $email, $waNormalized, $kota, $refCode, $eventSlug]);

    // Susun pesan WhatsApp yang akan dikirim SI PENDAFTAR ke NOMOR PENGUNDANG
    $waMessage = "Halo" . ($referrer ? " {$referrer['name']}" : "") . "! 👋\n"
        . "Saya sudah daftar acara *" . ($event ? $event['name'] : $brand['name']) . "*:\n\n"
        . "Nama: {$name}\n"
        . "Kota: {$kota}\n\n"
        . "Mohon info selanjutnya ya. Terima kasih! 🙏";

    $targetWa = $referrer ? $referrer['whatsapp'] : (($event['whatsapp_default'] ?? '') ?: '');

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
