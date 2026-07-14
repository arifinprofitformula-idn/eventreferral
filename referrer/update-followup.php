<?php
/**
 * referrer/update-followup.php
 * Update status follow-up (baru/dihubungi/closing) untuk SATU lead milik
 * pengundang yang sedang login. Kepemilikan diverifikasi lewat kecocokan
 * (brand_id, ref_code, event_slug) lead dengan salah satu link referrer ini.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/referrer_auth.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

$brand = get_current_brand();
if (!$brand) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Brand tidak ditemukan.']);
    exit;
}

$sessionBrandId = $_SESSION['referrer_brand_id'] ?? null;
$sessionWhatsapp = $_SESSION['referrer_whatsapp'] ?? null;
if (!$sessionWhatsapp || (int)$sessionBrandId !== (int)$brand['id']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan login ulang.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$leadId = (int)($input['lead_id'] ?? 0);
$status = (string)($input['status'] ?? '');
$allowedStatus = ['baru', 'dihubungi', 'closing'];

if ($leadId <= 0 || !in_array($status, $allowedStatus, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Data tidak valid.']);
    exit;
}

$pdo = get_db();
$brandId = (int)$brand['id'];

$myLinks = get_referrer_rows_for_session($pdo, $brand);
if (empty($myLinks)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akun tidak memiliki link referral.']);
    exit;
}

$conditions = [];
$params = [$leadId, $brandId];
foreach ($myLinks as $link) {
    $conditions[] = '(ref_code = ? AND event_slug = ?)';
    $params[] = $link['ref_code'];
    $params[] = $link['event_slug'];
}
$whereClause = implode(' OR ', $conditions);

$stmt = $pdo->prepare("UPDATE leads SET followup_status = ? WHERE id = ? AND brand_id = ? AND ($whereClause)");
$stmt->execute(array_merge([$status], $params));

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Lead tidak ditemukan atau bukan milikmu.']);
    exit;
}

echo json_encode(['success' => true, 'status' => $status]);
