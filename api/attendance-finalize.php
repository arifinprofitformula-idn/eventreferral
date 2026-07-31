<?php
/**
 * api/attendance-finalize.php
 * Dipanggil admin setelah event selesai: hitung is_reward_eligible per referral_id
 * berdasarkan jumlah pendaftar (yang direferensikan referral tsb) dengan attendance_status='hadir'.
 *
 * Tidak mengubah leaderboard/tabel lama — leaderboard di admin/dashboard.php tetap dihitung
 * on-the-fly dari leads+referrers seperti sebelumnya. Hasil finalisasi hanya disimpan di kolom
 * is_reward_eligible pada event_attendance (kolom yang memang sudah didesain untuk ini).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
start_secure_session();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$brand = get_current_brand();
if (!$brand) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Brand tidak ditemukan.']);
    exit;
}

$isSuperadmin = !empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin';
$isBrandAdmin = !empty($_SESSION['admin_brand_id']) && (int)$_SESSION['admin_brand_id'] === (int)$brand['id'];
if (!$isSuperadmin && !$isBrandAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi admin tidak valid. Silakan login ulang.']);
    exit;
}
$brandId = (int)$brand['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrfToken = (string)($input['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.']);
    exit;
}

$eventSlug = clean($input['event'] ?? '');
$minHadir = isset($input['min_hadir']) ? (int)$input['min_hadir'] : 1;
if ($minHadir < 1) {
    $minHadir = 1;
}

if ($eventSlug === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Event wajib dipilih.']);
    exit;
}

try {
    $pdo = get_db();

    $event = get_event_by_slug($eventSlug);
    if (!$event || (int)$event['brand_id'] !== $brandId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan atau bukan milik brand ini.']);
        exit;
    }
    $eventId = (int)$event['id'];

    $pdo->beginTransaction();

    // Reset dulu supaya finalisasi bisa dijalankan ulang (idempotent) tanpa efek samping.
    $stmt = $pdo->prepare('UPDATE event_attendance SET is_reward_eligible = 0 WHERE event_id = ?');
    $stmt->execute([$eventId]);

    // Hitung jumlah hadir per referral_id untuk event ini.
    $stmt = $pdo->prepare('
        SELECT ea.referral_id, r.name, r.ref_code, r.whatsapp, COUNT(*) AS hadir_count
        FROM event_attendance ea
        JOIN referrers r ON r.id = ea.referral_id
        WHERE ea.event_id = ? AND ea.referral_id IS NOT NULL AND ea.attendance_status = "hadir"
        GROUP BY ea.referral_id, r.name, r.ref_code, r.whatsapp
        HAVING COUNT(*) >= ?
        ORDER BY hadir_count DESC
    ');
    $stmt->execute([$eventId, $minHadir]);
    $eligibleReferrals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $eligibleReferralIds = array_map(static fn ($row) => (int)$row['referral_id'], $eligibleReferrals);

    if (!empty($eligibleReferralIds)) {
        $placeholders = implode(',', array_fill(0, count($eligibleReferralIds), '?'));
        $stmt = $pdo->prepare("
            UPDATE event_attendance
            SET is_reward_eligible = 1
            WHERE event_id = ? AND referral_id IN ($placeholders) AND attendance_status = 'hadir'
        ");
        $stmt->execute(array_merge([$eventId], $eligibleReferralIds));
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_attendance WHERE event_id = ? AND attendance_status = 'hadir'");
    $stmt->execute([$eventId]);
    $totalHadir = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM event_attendance WHERE event_id = ?');
    $stmt->execute([$eventId]);
    $totalRegistrants = (int)$stmt->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Finalisasi kehadiran selesai. ' . count($eligibleReferrals) . ' pengundang memenuhi syarat reward (minimal ' . $minHadir . ' pendaftar hadir).',
        'total_hadir' => $totalHadir,
        'total_registrants' => $totalRegistrants,
        'min_hadir' => $minHadir,
        'eligible_referrals' => array_map(static function ($row) {
            return [
                'name' => $row['name'],
                'ref_code' => $row['ref_code'],
                'whatsapp' => $row['whatsapp'],
                'hadir_count' => (int)$row['hadir_count'],
            ];
        }, $eligibleReferrals),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.']);
}
