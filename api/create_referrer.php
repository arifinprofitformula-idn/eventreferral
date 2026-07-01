<?php
/**
 * api/create_referrer.php
 * Menerima nama + WhatsApp calon pengundang, generate ref_code unik,
 * simpan ke tabel referrers, kembalikan link siap-share.
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

$name = clean($input['name'] ?? '');
$wa   = clean($input['whatsapp'] ?? '');

$errors = [];
if ($name === '' || mb_strlen($name) < 3) {
    $errors[] = 'Nama minimal 3 karakter.';
}
$waNormalized = normalize_whatsapp($wa);
if (strlen($waNormalized) < 10 || strlen($waNormalized) > 15) {
    $errors[] = 'Nomor WhatsApp tidak valid.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = get_db();

    // Cek apakah nomor WA ini sudah pernah generate link sebelumnya -> pakai yang lama
    $stmt = $pdo->prepare('SELECT ref_code FROM referrers WHERE whatsapp = ?');
    $stmt->execute([$waNormalized]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $refCode = $existing['ref_code'];
    } else {
        // Buat ref_code unik dari nama + angka acak, contoh: budi482
        $base = strtolower(preg_replace('/[^a-zA-Z]/', '', $name));
        $base = substr($base !== '' ? $base : 'user', 0, 12);

        do {
            $refCode = $base . rand(100, 999);
            $stmt = $pdo->prepare('SELECT id FROM referrers WHERE ref_code = ?');
            $stmt->execute([$refCode]);
        } while ($stmt->fetch());

        $stmt = $pdo->prepare('INSERT INTO referrers (ref_code, name, whatsapp) VALUES (?, ?, ?)');
        $stmt->execute([$refCode, $name, $waNormalized]);
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'rahasiaemas.id';
    $link = "{$protocol}://{$host}/?ref={$refCode}";

    echo json_encode([
        'success' => true,
        'ref_code' => $refCode,
        'link' => $link,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.']);
}
