<?php
/**
 * config.example.php - Template konfigurasi rahasiaemas.id
 *
 * Salin file ini menjadi config.php di server/local, lalu isi nilainya.
 * Jangan commit config.php karena berisi kredensial dan PIN admin.
 */

// ==== DATABASE ====
define('DB_HOST', 'localhost');
define('DB_NAME', 'nama_database');
define('DB_USER', 'username_database');
define('DB_PASS', 'password_database');

// ==== PENGATURAN UMUM ====
define('SITE_NAME', 'rahasiaemas.id');
define('DEFAULT_REF_CODE', 'admin');

// Ganti dengan PIN rahasia minimal 6 digit.
define('ADMIN_PIN', '123456');

// Detail acara awal/fallback.
define('EVENT_DAY', 'Jumat, 25 Juli 2026');
define('EVENT_TIME', '19.30 WIB');
define('EVENT_LOCATION', 'Online via Zoom (link dikirim via WhatsApp)');
define('EVENT_SPEAKER', 'Coach Arifin');
define('EVENT_CAPACITY', '100');

// ==== JANGAN DIUBAH DI BAWAH INI ====
date_default_timezone_set('Asia/Jakarta');

function start_secure_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function get_db($dieOnFailure = true) {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            if (!$dieOnFailure) {
                return null;
            }
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Koneksi database gagal. Hubungi admin.']));
        }
    }
    return $pdo;
}

function default_event_settings() {
    return [
        'event_day' => EVENT_DAY,
        'event_time' => EVENT_TIME,
        'event_location' => EVENT_LOCATION,
        'event_speaker' => EVENT_SPEAKER,
        'event_capacity' => EVENT_CAPACITY,
    ];
}

function ensure_event_settings_table(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            event_day VARCHAR(100) NOT NULL,
            event_time VARCHAR(100) NOT NULL,
            event_location VARCHAR(255) NOT NULL,
            event_speaker VARCHAR(100) NOT NULL,
            event_capacity VARCHAR(20) NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $defaults = default_event_settings();
    $stmt = $pdo->prepare("
        INSERT INTO event_settings (id, event_day, event_time, event_location, event_speaker, event_capacity)
        VALUES (1, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE id = id
    ");
    $stmt->execute([
        $defaults['event_day'],
        $defaults['event_time'],
        $defaults['event_location'],
        $defaults['event_speaker'],
        $defaults['event_capacity'],
    ]);
}

function get_event_settings() {
    $defaults = default_event_settings();

    try {
        $pdo = get_db(false);
        if (!$pdo) {
            return $defaults;
        }

        ensure_event_settings_table($pdo);
        $stmt = $pdo->query('SELECT event_day, event_time, event_location, event_speaker, event_capacity FROM event_settings WHERE id = 1');
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            return $defaults;
        }

        return array_merge($defaults, array_filter($settings, function ($value) {
            return $value !== null && $value !== '';
        }));
    } catch (Exception $e) {
        return $defaults;
    }
}

function save_event_settings(array $data) {
    $pdo = get_db();
    ensure_event_settings_table($pdo);

    $settings = [
        'event_day' => trim($data['event_day'] ?? ''),
        'event_time' => trim($data['event_time'] ?? ''),
        'event_location' => trim($data['event_location'] ?? ''),
        'event_speaker' => trim($data['event_speaker'] ?? ''),
        'event_capacity' => trim($data['event_capacity'] ?? ''),
    ];

    foreach ($settings as $value) {
        if ($value === '') {
            throw new InvalidArgumentException('Semua detail acara wajib diisi.');
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO event_settings (id, event_day, event_time, event_location, event_speaker, event_capacity)
        VALUES (1, :event_day, :event_time, :event_location, :event_speaker, :event_capacity)
        ON DUPLICATE KEY UPDATE
            event_day = VALUES(event_day),
            event_time = VALUES(event_time),
            event_location = VALUES(event_location),
            event_speaker = VALUES(event_speaker),
            event_capacity = VALUES(event_capacity)
    ");
    $stmt->execute($settings);

    return $settings;
}

function clean($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function normalize_whatsapp($raw) {
    $num = preg_replace('/[^0-9]/', '', $raw);
    if (substr($num, 0, 1) === '0') {
        $num = '62' . substr($num, 1);
    } elseif (substr($num, 0, 2) !== '62') {
        $num = '62' . $num;
    }
    return $num;
}
