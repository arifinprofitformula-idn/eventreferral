<?php
/**
 * api/create_referrer.php
 * Menerima nama, WhatsApp, dan ref_code pilihan calon pengundang,
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
$requestedRefCode = strtolower(clean($input['ref_code'] ?? ''));

$errors = [];
if ($name === '' || mb_strlen($name) < 3) {
    $errors[] = 'Nama minimal 3 karakter.';
}
$waNormalized = normalize_whatsapp($wa);
if (strlen($waNormalized) < 10 || strlen($waNormalized) > 15) {
    $errors[] = 'Nomor WhatsApp tidak valid.';
}
if ($requestedRefCode === '') {
    $errors[] = 'Kode link wajib diisi.';
} elseif (!preg_match('/^[a-z0-9_-]{3,20}$/', $requestedRefCode)) {
    $errors[] = 'Kode link hanya boleh 3-20 karakter: huruf, angka, strip (-), atau underscore (_).';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    $pdo = get_db();

    // Cek apakah nomor WA ini sudah pernah membuat link sebelumnya.
    $stmt = $pdo->prepare('SELECT ref_code FROM referrers WHERE whatsapp = ? ORDER BY id ASC LIMIT 1');
    $stmt->execute([$waNormalized]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $refCode = $existing['ref_code'];
        if ($requestedRefCode !== $refCode) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => "Nomor WhatsApp ini sudah punya kode link: {$refCode}. Gunakan kode tersebut atau hubungi admin untuk mengganti.",
            ]);
            exit;
        }
    } else {
        $stmt = $pdo->prepare('SELECT id FROM referrers WHERE ref_code = ?');
        $stmt->execute([$requestedRefCode]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Kode link sudah dipakai. Silakan pilih kode lain.']);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO referrers (ref_code, name, whatsapp) VALUES (?, ?, ?)');
        $stmt->execute([$requestedRefCode, $name, $waNormalized]);
        $refCode = $requestedRefCode;
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
