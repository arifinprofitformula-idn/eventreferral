<?php
/**
 * api/attendance-confirm.php
 * Endpoint PUBLIK untuk halaman /hadir/{slug-event} — konfirmasi kehadiran self-service
 * (pengganti Google Form), dipakai peserta terdaftar maupun yang belum pernah daftar.
 *
 * Dua action lewat satu endpoint (JSON body { action: 'lookup' | 'confirm', ... }):
 *   - lookup  : cek nomor HP terdaftar atau tidak untuk event ini. Kalau terdaftar, balikin
 *               profil (nama/email/kota) supaya field itu bisa ditampilkan TERKUNCI di form —
 *               peserta tinggal melengkapi field tambahan (sumber info, status, feedback).
 *               Endpoint tetap dibatasi rate limit per IP/nomor HP untuk membatasi eksposur.
 *   - confirm : proses final, insert/update event_attendance (+ insert leads jika walk-in baru).
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
    echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan.']);
    exit;
}
$brandId = (int)$brand['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

// ---- CSRF (token dibuat halaman /hadir/{slug} saat render, sama pola dgn halaman admin) ----
$csrfToken = (string)($input['csrf_token'] ?? '');
if (empty($_SESSION['attendance_csrf_token']) || !hash_equals($_SESSION['attendance_csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan muat ulang halaman lalu coba lagi.']);
    exit;
}

// ---- Rate limit (per IP + per nomor HP) ----
$ipHash = attendance_ip_hash($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$whatsappForLimit = normalize_whatsapp((string)($input['whatsapp'] ?? ''));

try {
    $pdo = get_db();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.']);
    exit;
}

if (attendance_rate_limit_exceeded($pdo, $ipHash, $whatsappForLimit)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Terlalu banyak percobaan. Silakan coba lagi beberapa menit lagi.']);
    exit;
}

$action = (string)($input['action'] ?? '');
$eventSlug = clean($input['slug'] ?? '');
$whatsappRaw = (string)($input['whatsapp'] ?? '');
$waNormalized = normalize_whatsapp($whatsappRaw);

if ($eventSlug === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Event tidak valid.']);
    exit;
}
if (strlen($waNormalized) < 10 || strlen($waNormalized) > 15) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp tidak valid.']);
    exit;
}

$event = get_event_by_slug($eventSlug);
if (!$event || (int)$event['brand_id'] !== $brandId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan.']);
    exit;
}
$eventId = (int)$event['id'];

// ==================== ACTION: lookup ====================
if ($action === 'lookup') {
    $windowState = attendance_window_state($event);
    if ($windowState !== 'open') {
        // Status jendela waktu boleh spesifik (bukan info sensitif — tanggal event sudah publik).
        echo json_encode([
            'success' => false,
            'window' => $windowState,
            'message' => $windowState === 'not_open'
                ? 'Konfirmasi kehadiran belum dibuka.'
                : ($windowState === 'closed' ? 'Periode konfirmasi sudah berakhir.' : 'Konfirmasi kehadiran belum diaktifkan untuk event ini.'),
        ]);
        exit;
    }

    // Kode kehadiran WAJIB valid dulu sebelum lookup boleh mengungkap data profil apa pun —
    // supaya endpoint publik ini tidak bisa dipakai untuk enumerasi nama/email/kota peserta
    // hanya bermodal menebak-nebak nomor HP tanpa tahu kode kehadiran yang sah.
    $attendanceCodeForLookup = (string)($input['attendance_code'] ?? '');
    if (!attendance_code_valid($event, $attendanceCodeForLookup)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Kode kehadiran tidak valid.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id, name, email, kota FROM leads WHERE brand_id = ? AND event_slug = ? AND whatsapp = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$brandId, $eventSlug, $waNormalized]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lead) {
            echo json_encode([
                'success' => true,
                'found' => true,
                'masked_name' => attendance_mask_name($lead['name']),
                'profile' => [
                    'name' => $lead['name'],
                    'email' => $lead['email'],
                    'kota' => $lead['kota'],
                ],
            ]);
        } else {
            echo json_encode(['success' => true, 'found' => false]);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.']);
    }
    exit;
}

// ==================== ACTION: confirm ====================
if ($action === 'confirm') {
    // Validasi ulang di server — TIDAK percaya begitu saja bahwa step lookup sebelumnya sukses
    // (client bisa saja langsung memanggil action=confirm tanpa lookup). Sengaja tidak
    // membedakan pesan gagal supaya tidak jadi oracle brute-force kode kehadiran.
    if (!attendance_code_valid($event, (string)($input['attendance_code'] ?? ''))) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Konfirmasi gagal. Periksa kembali kode kehadiran dan waktu pengisian, lalu coba lagi.']);
        exit;
    }

    // Field tambahan (sumber info, status peserta, feedback) — wajib di KEDUA jalur.
    $extra = validate_attendance_extra_fields(
        (string)($input['info_source'] ?? ''),
        (string)($input['participant_status'] ?? ''),
        (string)($input['feedback'] ?? ''),
        (string)($input['next_topic_interest'] ?? '')
    );
    if (!empty($extra['errors'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => implode(' ', $extra['errors'])]);
        exit;
    }

    // Kolom-kolom ini butuh migrate_v18 (attendance_source, confirmation_code_valid) dan
    // migrate_v22 (info_source, participant_status, feedback_notes) — cek dulu satu-satu supaya
    // endpoint tetap jalan (skip kolom yang belum ada) di server yang migrasinya belum lengkap/
    // belum berurutan, bukan 500 mentah dari "Unknown column".
    $attendanceSourceReady = false;
    try {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM event_attendance LIKE 'attendance_source'");
        $attendanceSourceReady = (bool)$columnCheck->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $attendanceSourceReady = false;
    }

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

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT id, name, ref_code FROM leads WHERE brand_id = ? AND event_slug = ? AND whatsapp = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$brandId, $eventSlug, $waNormalized]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lead) {
            // ---- Jalur TERDAFTAR ----
            $registrantId = (int)$lead['id'];
            $attendanceSource = 'terdaftar';

            $stmt = $pdo->prepare('SELECT * FROM event_attendance WHERE event_id = ? AND registrant_id = ? LIMIT 1');
            $stmt->execute([$eventId, $registrantId]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$attendance) {
                // Registrant lama sebelum fitur attendance ada — buat barisnya dulu (referral_id ikut ter-link).
                create_event_attendance_record($pdo, $eventId, $registrantId, $lead['ref_code'] ?: null, $brandId, $eventSlug);
                $stmt = $pdo->prepare('SELECT * FROM event_attendance WHERE event_id = ? AND registrant_id = ? LIMIT 1');
                $stmt->execute([$eventId, $registrantId]);
                $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$attendance) {
                throw new RuntimeException('attendance_row_unavailable');
            }

            if ($attendance['attendance_status'] === 'hadir') {
                $stmt = $pdo->prepare('UPDATE event_attendance SET duplicate_flag = 1 WHERE id = ?');
                $stmt->execute([(int)$attendance['id']]);
                $pdo->commit();

                echo json_encode(['success' => false, 'duplicate' => true, 'message' => 'Kamu sudah konfirmasi kehadiran sebelumnya.']);
                exit;
            }

            $setParts = ["attendance_status = 'hadir'", "check_in_time = NOW()", "check_in_method = 'self_checkin'"];
            $updateParams = [];
            if ($attendanceSourceReady) {
                $setParts[] = 'attendance_source = ?';
                $setParts[] = 'confirmation_code_valid = 1';
                $updateParams[] = $attendanceSource;
            }
            if ($extraFieldsReady) {
                $setParts[] = 'info_source = ?';
                $setParts[] = 'participant_status = ?';
                $setParts[] = 'feedback_notes = ?';
                $updateParams[] = $extra['info_source'];
                $updateParams[] = $extra['participant_status'];
                $updateParams[] = $extra['feedback'];
            }
            if ($nextTopicReady) {
                $setParts[] = 'next_topic_interest = ?';
                $updateParams[] = $extra['next_topic_interest'];
            }
            $updateParams[] = (int)$attendance['id'];

            $stmt = $pdo->prepare('UPDATE event_attendance SET ' . implode(', ', $setParts) . ' WHERE id = ?');
            $stmt->execute($updateParams);
        } else {
            // ---- Jalur TIDAK TERDAFTAR (walk-in) ----
            $name = (string)($input['name'] ?? '');
            $email = (string)($input['email'] ?? '');
            $kota = (string)($input['kota'] ?? '');
            $manualRefCode = strtolower(clean((string)($input['ref_code_manual'] ?? '')));

            $validated = validate_walkin_attendee_fields($name, $email, $kota, $whatsappRaw);
            if (!empty($validated['errors'])) {
                $pdo->rollBack();
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => implode(' ', $validated['errors'])]);
                exit;
            }

            $referralId = null;
            $matchedRefCode = null;
            if ($manualRefCode !== '') {
                $stmt = $pdo->prepare('SELECT id, ref_code FROM referrers WHERE brand_id = ? AND event_slug = ? AND ref_code = ? LIMIT 1');
                $stmt->execute([$brandId, $eventSlug, $manualRefCode]);
                $referrerRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($referrerRow) {
                    $referralId = (int)$referrerRow['id'];
                    $matchedRefCode = $referrerRow['ref_code'];
                }
                // Kode referral manual yang tidak cocok TIDAK menggagalkan konfirmasi — cuma diabaikan
                // (field ini opsional/bonus, bukan gerbang keamanan).
            }

            $stmt = $pdo->prepare('
                INSERT INTO leads (brand_id, name, email, whatsapp, kota, ref_code, event_slug, registration_source)
                VALUES (?, ?, ?, ?, ?, ?, ?, "hadir_langsung")
            ');
            $stmt->execute([
                $brandId,
                $validated['name'],
                $validated['email'],
                $validated['whatsapp'],
                $validated['kota'],
                $matchedRefCode,
                $eventSlug,
            ]);
            $registrantId = (int)$pdo->lastInsertId();
            $attendanceSource = 'tidak_terdaftar';

            $qrToken = generate_attendance_qr_token($pdo);

            $insertCols = ['event_id', 'registrant_id', 'referral_id', 'qr_token', 'attendance_status', 'check_in_time', 'check_in_method'];
            $insertPlaceholders = ['?', '?', '?', '?', "'hadir'", 'NOW()', "'self_checkin'"];
            $insertParams = [$eventId, $registrantId, $referralId, $qrToken];
            if ($attendanceSourceReady) {
                $insertCols[] = 'attendance_source';
                $insertPlaceholders[] = '?';
                $insertParams[] = $attendanceSource;
                $insertCols[] = 'confirmation_code_valid';
                $insertPlaceholders[] = '1';
            }
            if ($extraFieldsReady) {
                $insertCols[] = 'info_source';
                $insertPlaceholders[] = '?';
                $insertParams[] = $extra['info_source'];
                $insertCols[] = 'participant_status';
                $insertPlaceholders[] = '?';
                $insertParams[] = $extra['participant_status'];
                $insertCols[] = 'feedback_notes';
                $insertPlaceholders[] = '?';
                $insertParams[] = $extra['feedback'];
            }
            if ($nextTopicReady) {
                $insertCols[] = 'next_topic_interest';
                $insertPlaceholders[] = '?';
                $insertParams[] = $extra['next_topic_interest'];
            }

            $stmt = $pdo->prepare('INSERT INTO event_attendance (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')');
            $stmt->execute($insertParams);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Kehadiran berhasil dikonfirmasi. Terima kasih!',
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Race kondisi: dua submit hampir bersamaan untuk nomor HP yang sama bisa kena UNIQUE
        // KEY (event_id, registrant_id) di level DB — perlakukan sebagai duplikat, bukan error mentah.
        if ($e instanceof PDOException && (int)$e->getCode() === 23000) {
            echo json_encode(['success' => false, 'duplicate' => true, 'message' => 'Kamu sudah konfirmasi kehadiran sebelumnya.']);
            exit;
        }

        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server. Coba lagi.']);
    }
    exit;
}

http_response_code(422);
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
