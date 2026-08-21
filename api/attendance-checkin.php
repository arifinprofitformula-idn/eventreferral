<?php
/**
 * api/attendance-checkin.php
 * Proses check-in kehadiran (dari scan QR atau pencarian manual di admin/event-attendance.php).
 * Endpoint admin (butuh sesi admin aktif) — dipanggil lewat fetch() dari halaman scanner.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attendance.php';
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

// Auth admin — sama seperti halaman admin lain, tidak membuat mekanisme auth baru.
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

// CSRF — token yang sama dipakai halaman admin/event-attendance.php (disimpan di sesi).
$csrfToken = (string)($input['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan refresh halaman lalu coba lagi.']);
    exit;
}

function attendance_checkin_extract_qr_token(string $value): string {
    $raw = trim($value);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^[a-f0-9]{64}$/i', $raw, $match)) {
        return strtolower($match[0]);
    }

    $parts = parse_url($raw);
    if (is_array($parts) && !empty($parts['query'])) {
        parse_str($parts['query'], $query);
        foreach (['qr_token', 'token', 'qr'] as $key) {
            $candidate = isset($query[$key]) ? (string)$query[$key] : '';
            if (preg_match('/^[a-f0-9]{64}$/i', $candidate, $match)) {
                return strtolower($match[0]);
            }
        }
    }

    if (preg_match('/[a-f0-9]{64}/i', $raw, $match)) {
        return strtolower($match[0]);
    }

    return '';
}

$eventSlug = clean($input['event'] ?? '');
$qrToken = isset($input['qr_token']) ? attendance_checkin_extract_qr_token((string)$input['qr_token']) : '';
$registrantId = isset($input['registrant_id']) ? (int)$input['registrant_id'] : 0;
$methodInput = (string)($input['check_in_method'] ?? '');
$allowedMethods = ['qr_scan', 'manual_admin', 'self_checkin'];

if ($eventSlug === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Event wajib dipilih.']);
    exit;
}
if ($qrToken === '' && $registrantId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'qr_token atau registrant_id wajib diisi.']);
    exit;
}

$checkInMethod = in_array($methodInput, $allowedMethods, true)
    ? $methodInput
    : ($qrToken !== '' ? 'qr_scan' : 'manual_admin');

try {
    $pdo = get_db();

    $event = get_event_by_slug($eventSlug);
    if (!$event || (int)$event['brand_id'] !== $brandId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan atau bukan milik brand ini.']);
        exit;
    }
    $eventId = (int)$event['id'];

    if ($qrToken !== '') {
        $stmt = $pdo->prepare('
            SELECT ea.*, l.name AS registrant_name, l.whatsapp AS registrant_whatsapp
            FROM event_attendance ea
            JOIN leads l ON l.id = ea.registrant_id
            WHERE ea.event_id = ? AND ea.qr_token = ?
            LIMIT 1
        ');
        $stmt->execute([$eventId, $qrToken]);
    } else {
        $stmt = $pdo->prepare('
            SELECT ea.*, l.name AS registrant_name, l.whatsapp AS registrant_whatsapp
            FROM event_attendance ea
            JOIN leads l ON l.id = ea.registrant_id
            WHERE ea.event_id = ? AND ea.registrant_id = ?
            LIMIT 1
        ');
        $stmt->execute([$eventId, $registrantId]);
    }
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

    // Registrant lama (sebelum fitur ini ada) belum punya baris event_attendance/qr_token.
    // Untuk check-in manual by registrant_id, buat baris-nya sekarang (lazy) alih-alih menolak.
    // Untuk qr_token, tidak bisa lazy-create karena tidak ada token untuk dicocokkan.
    if (!$attendance && $qrToken === '' && $registrantId > 0) {
        $stmt = $pdo->prepare('SELECT id, name, whatsapp, ref_code FROM leads WHERE id = ? AND brand_id = ? AND event_slug = ?');
        $stmt->execute([$registrantId, $brandId, $eventSlug]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lead) {
            $referralId = null;
            if (!empty($lead['ref_code'])) {
                $stmt = $pdo->prepare('SELECT id FROM referrers WHERE brand_id = ? AND event_slug = ? AND ref_code = ? LIMIT 1');
                $stmt->execute([$brandId, $eventSlug, $lead['ref_code']]);
                $foundReferral = $stmt->fetchColumn();
                $referralId = $foundReferral !== false ? (int)$foundReferral : null;
            }

            $newToken = generate_attendance_qr_token($pdo);
            $stmt = $pdo->prepare('INSERT INTO event_attendance (event_id, registrant_id, referral_id, qr_token) VALUES (?, ?, ?, ?)');
            $stmt->execute([$eventId, $registrantId, $referralId, $newToken]);

            $stmt = $pdo->prepare('
                SELECT ea.*, l.name AS registrant_name, l.whatsapp AS registrant_whatsapp
                FROM event_attendance ea
                JOIN leads l ON l.id = ea.registrant_id
                WHERE ea.id = ?
                LIMIT 1
            ');
            $stmt->execute([(int)$pdo->lastInsertId()]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if (!$attendance) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data kehadiran tidak ditemukan untuk event ini. Pastikan QR/nomor sesuai event yang sedang dipilih.']);
        exit;
    }

    if ($attendance['attendance_status'] === 'hadir') {
        // Sudah pernah check-in — tandai duplicate_flag, JANGAN timpa check_in_time, beri pesan jelas ke UI.
        $stmt = $pdo->prepare('UPDATE event_attendance SET duplicate_flag = 1 WHERE id = ?');
        $stmt->execute([(int)$attendance['id']]);

        $checkedAt = $attendance['check_in_time'] ? date('d M Y, H:i', strtotime($attendance['check_in_time'])) : '-';
        echo json_encode([
            'success' => false,
            'duplicate' => true,
            'message' => htmlspecialchars($attendance['registrant_name']) . ' sudah check-in sebelumnya pada ' . $checkedAt . '.',
            'registrant_id' => (int)$attendance['registrant_id'],
            'registrant_name' => $attendance['registrant_name'],
        ]);
        exit;
    }

    if ($attendance['attendance_status'] === 'batal') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => htmlspecialchars($attendance['registrant_name']) . ' berstatus batal dan tidak bisa di-check-in.']);
        exit;
    }

    $adminUserId = current_admin_user_id();
    $stmt = $pdo->prepare('
        UPDATE event_attendance
        SET attendance_status = "hadir", check_in_time = NOW(), check_in_method = ?, checked_in_by_admin_id = ?
        WHERE id = ?
    ');
    $stmt->execute([$checkInMethod, $adminUserId, (int)$attendance['id']]);

    // Counter untuk UI ("X dari Y hadir").
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM event_attendance WHERE event_id = ?');
    $stmt->execute([$eventId]);
    $totalRegistrants = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_attendance WHERE event_id = ? AND attendance_status = 'hadir'");
    $stmt->execute([$eventId]);
    $totalHadir = (int)$stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => htmlspecialchars($attendance['registrant_name']) . ' berhasil check-in.',
        'registrant_id' => (int)$attendance['registrant_id'],
        'registrant_name' => $attendance['registrant_name'],
        'registrant_whatsapp' => $attendance['registrant_whatsapp'],
        'total_hadir' => $totalHadir,
        'total_registrants' => $totalRegistrants,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.']);
}
