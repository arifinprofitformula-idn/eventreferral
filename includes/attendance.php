<?php
/**
 * includes/attendance.php
 * Helper untuk fitur "Kehadiran Event" (check-in QR / manual admin).
 * Tidak menyentuh logic pendaftaran/leaderboard lama — hanya ditambahkan sebagai lapisan baru.
 */

/** Hasilkan qr_token unik (random, bukan predictable) — dicek langsung ke DB supaya benar-benar unik. */
function generate_attendance_qr_token(PDO $pdo): string {
    do {
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare('SELECT 1 FROM event_attendance WHERE qr_token = ? LIMIT 1');
        $stmt->execute([$token]);
    } while ($stmt->fetchColumn());
    return $token;
}

/**
 * Buat baris event_attendance untuk registrant baru (dipanggil setelah INSERT ke tabel leads berhasil).
 * Aman dipanggil walau migrasi belum dijalankan (table_exists() cek dulu) dan tidak pernah melempar
 * exception ke pemanggil — kegagalan di sini TIDAK BOLEH menggagalkan pendaftaran.
 */
function create_event_attendance_record(PDO $pdo, int $eventId, int $registrantId, ?string $refCode, int $brandId, string $eventSlug): void {
    if (!table_exists($pdo, 'event_attendance')) {
        return;
    }

    try {
        $referralId = null;
        if ($refCode !== null && $refCode !== '') {
            $stmt = $pdo->prepare('SELECT id FROM referrers WHERE brand_id = ? AND event_slug = ? AND ref_code = ? LIMIT 1');
            $stmt->execute([$brandId, $eventSlug, $refCode]);
            $found = $stmt->fetchColumn();
            $referralId = $found !== false ? (int)$found : null;
        }

        $qrToken = generate_attendance_qr_token($pdo);

        $stmt = $pdo->prepare('
            INSERT INTO event_attendance (event_id, registrant_id, referral_id, qr_token)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$eventId, $registrantId, $referralId, $qrToken]);
    } catch (Throwable $e) {
        // Sengaja ditelan — fitur attendance tidak boleh menggagalkan alur pendaftaran existing.
    }
}

/** Ambil ID admin yang sedang login dari sesi, untuk kolom checked_in_by_admin_id. Bisa null (mis. login superadmin bootstrap). */
function current_admin_user_id(): ?int {
    return isset($_SESSION['admin_user_id']) && $_SESSION['admin_user_id'] !== null
        ? (int)$_SESSION['admin_user_id']
        : null;
}

// ==================== KONFIRMASI KEHADIRAN (SELF-SERVICE) ====================

/**
 * Status jendela waktu konfirmasi kehadiran untuk sebuah event, berdasarkan events.event_date
 * (satu-satunya sumber tanggal, sesuai spesifikasi — tidak ada kolom jendela waktu terpisah).
 * Timezone sama dengan seluruh sistem (Asia/Jakarta, di-set sekali di config.php).
 *
 * @return string 'not_configured' (event_date belum diisi admin) | 'not_open' (sebelum hari-H)
 *                | 'open' (hari-H) | 'closed' (setelah hari-H)
 */
function attendance_window_state(array $event): string {
    $eventDate = trim((string)($event['event_date'] ?? ''));
    if ($eventDate === '') {
        return 'not_configured';
    }

    $today = date('Y-m-d');
    if ($today < $eventDate) {
        return 'not_open';
    }
    if ($today > $eventDate) {
        return 'closed';
    }
    return 'open';
}

/** Hash IP untuk rate limiting — konsisten dengan konvensi visitor_events.ip_hash (migrate_v9), tidak pernah simpan IP mentah. */
function attendance_ip_hash(string $ip): string {
    return hash('sha256', $ip . IP_SALT);
}

/**
 * Satu pemeriksaan gabungan dipakai di step lookup MAUPUN confirm: jendela waktu harus
 * terbuka DAN kode kehadiran harus cocok. Dipakai bareng supaya endpoint publik ini tidak
 * membocorkan data profil peserta (nama/email/kota) ke siapa pun yang cuma tahu nomor HP
 * tanpa juga tahu kode kehadiran yang sah — kode harus tervalidasi dulu sebelum lookup
 * boleh mengungkap data apa pun.
 */
function attendance_code_valid(array $event, string $submittedCode): bool {
    if (attendance_window_state($event) !== 'open') {
        return false;
    }
    if (empty($event['attendance_code'])) {
        return false;
    }
    return hash_equals((string)$event['attendance_code'], strtoupper(trim($submittedCode)));
}

/**
 * Cek + catat percobaan akses endpoint konfirmasi kehadiran, per IP dan per nomor HP.
 * Return true jika permintaan MELEBIHI batas (harus ditolak dengan 429), false jika masih wajar.
 * Aman dipanggil walau migrasi rate-limit belum jalan (tidak pernah menghalangi request kalau tabel belum ada).
 */
function attendance_rate_limit_exceeded(PDO $pdo, string $ipHash, ?string $whatsapp): bool {
    if (!table_exists($pdo, 'attendance_rate_limit')) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_rate_limit WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)");
        $stmt->execute([$ipHash]);
        $ipCount = (int)$stmt->fetchColumn();

        $waCount = 0;
        if ($whatsapp !== null && $whatsapp !== '') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_rate_limit WHERE whatsapp = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)");
            $stmt->execute([$whatsapp]);
            $waCount = (int)$stmt->fetchColumn();
        }

        $stmt = $pdo->prepare('INSERT INTO attendance_rate_limit (ip_hash, whatsapp) VALUES (?, ?)');
        $stmt->execute([$ipHash, $whatsapp !== '' ? $whatsapp : null]);

        return $ipCount >= 20 || $waCount >= 6;
    } catch (Throwable $e) {
        return false;
    }
}

/** Samarkan nama untuk ditampilkan di layar sebelum peserta mengonfirmasi (kurangi eksposur PII di form publik). */
function attendance_mask_name(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $masked = [];
    foreach ($parts as $i => $part) {
        if ($i === 0 || mb_strlen($part) <= 1) {
            $masked[] = $part;
        } else {
            $masked[] = mb_substr($part, 0, 1) . str_repeat('*', mb_strlen($part) - 1);
        }
    }
    return implode(' ', $masked);
}

/**
 * Validasi field peserta baru (belum pernah daftar) yang konfirmasi kehadiran langsung.
 * SENGAJA meniru persis aturan validasi di api/submit_lead.php (nama min 3, kota min 2,
 * email wajib & valid, whatsapp 10-15 digit setelah normalisasi) — supaya jalur insert
 * ke tabel `leads` ini TIDAK lebih longgar dari form pendaftaran lama.
 *
 * @return array{errors: string[], name: string, email: string, kota: string, whatsapp: string}
 */
function validate_walkin_attendee_fields(string $name, string $email, string $kota, string $whatsappRaw): array {
    $name = clean($name);
    $kota = clean($kota);
    $email = clean($email);

    $errors = [];
    if ($name === '' || mb_strlen($name) < 3) {
        $errors[] = 'Nama lengkap minimal 3 karakter.';
    }
    if ($kota === '' || mb_strlen($kota) < 2) {
        $errors[] = 'Kota domisili wajib diisi.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }

    $waNormalized = normalize_whatsapp($whatsappRaw);
    if (strlen($waNormalized) < 10 || strlen($waNormalized) > 15) {
        $errors[] = 'Nomor WhatsApp tidak valid.';
    }

    return [
        'errors' => $errors,
        'name' => $name,
        'email' => $email,
        'kota' => $kota,
        'whatsapp' => $waNormalized,
    ];
}

/** Daftar nilai valid untuk dropdown "Sumber Informasi" di form konfirmasi kehadiran. */
function attendance_info_source_options(): array {
    return [
        'media_sosial' => 'Media Sosial',
        'landing_page' => 'Landing Page',
        'group_whatsapp' => 'Group WhatsApp',
        'referensi' => 'Referensi',
    ];
}

/** Daftar nilai valid untuk dropdown "Status Peserta" di form konfirmasi kehadiran. */
function attendance_participant_status_options(): array {
    return [
        'umum' => 'Umum',
        'epi_store' => 'EPI Store',
        'epi_channel' => 'EPI Channel',
        'silverchannel' => 'Silverchannel',
    ];
}

/**
 * Validasi field tambahan konfirmasi kehadiran (info_source, participant_status wajib;
 * feedback dan next_topic_interest opsional). Dipakai di KEDUA jalur (terdaftar maupun
 * walk-in) karena field ini melekat ke kehadiran di event ini, bukan ke profil pendaftar.
 *
 * @return array{errors: string[], info_source: ?string, participant_status: ?string, feedback: ?string, next_topic_interest: ?string}
 */
function validate_attendance_extra_fields(string $infoSource, string $participantStatus, string $feedback, string $nextTopicInterest = ''): array {
    $errors = [];

    if (!array_key_exists($infoSource, attendance_info_source_options())) {
        $errors[] = 'Sumber informasi wajib dipilih.';
        $infoSource = '';
    }
    if (!array_key_exists($participantStatus, attendance_participant_status_options())) {
        $errors[] = 'Status peserta wajib dipilih.';
        $participantStatus = '';
    }

    $feedback = clean($feedback);
    if (mb_strlen($feedback) > 1000) {
        $feedback = mb_substr($feedback, 0, 1000);
    }

    $nextTopicInterest = clean($nextTopicInterest);
    if (mb_strlen($nextTopicInterest) > 1000) {
        $nextTopicInterest = mb_substr($nextTopicInterest, 0, 1000);
    }

    return [
        'errors' => $errors,
        'info_source' => $infoSource !== '' ? $infoSource : null,
        'participant_status' => $participantStatus !== '' ? $participantStatus : null,
        'feedback' => $feedback !== '' ? $feedback : null,
        'next_topic_interest' => $nextTopicInterest !== '' ? $nextTopicInterest : null,
    ];
}
