<?php
/**
 * config.example.php - Template konfigurasi rahasiaemas.id
 *
 * Salin file ini menjadi config.php di server/local, lalu isi nilainya.
 * Jangan commit config.php karena berisi kredensial dan hash password admin.
 */

// ==== DATABASE ====
define('DB_HOST', 'localhost');
define('DB_NAME', 'nama_database');
define('DB_USER', 'username_database');
define('DB_PASS', 'password_database');

// ==== PENGATURAN UMUM ====
define('SITE_NAME', 'rahasiaemas.id');
define('DEFAULT_REF_CODE', 'admin');

// Kredensial untuk masuk ke dashboard admin (/admin/)
// GANTI username & password hash-nya. Untuk membuat ADMIN_PASSWORD_HASH,
// buka admin/generate-password-hash.php di browser, masukkan password
// pilihan Anda, salin hasil hash-nya ke sini, lalu HAPUS file itu dari server.
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', '$2y$12$iUeNUsTjuTdSG8uekn4OguWiD9GsNGxrSQQP/5PoITp/3UwRLq0Ja');
define('LOGIN_MAX_ATTEMPTS', 5); // maksimal percobaan login gagal sebelum dikunci sementara
define('LOGIN_LOCKOUT_MINUTES', 15); // lama penguncian (menit) setelah melebihi batas percobaan

// Kunci rahasia untuk mengakses admin/setup-brand.php (menambah brand baru).
// HANYA Coach yang boleh tahu nilai ini — BUKAN sama dengan PIN/password admin brand manapun.
// GANTI dengan string acak panjang milik Anda sendiri (generate: php -r "echo bin2hex(random_bytes(24));").
define('MASTER_SETUP_KEY', 'ganti-dengan-kunci-rahasia-panjang-milik-coach-saja');

// Detail acara awal/fallback.
define('EVENT_DAY', 'Jumat, 25 Juli 2026');
define('EVENT_TIME', '19.30 WIB');
define('EVENT_LOCATION', 'Online via Zoom (link dikirim via WhatsApp)');
define('EVENT_SPEAKER', 'Coach Arifin');
define('EVENT_CAPACITY', '100');

// ==== MULTI-EVENT (v2) ====
define('DEFAULT_EVENT_SLUG', 'default');
define('EVENTS_DIR', __DIR__ . '/e');
define('EVENTS_URL_BASE', '/e');
define('MAX_ZIP_SIZE', 15 * 1024 * 1024); // 15 MB
define('ALLOWED_ASSET_EXT', ['html','htm','css','js','json','txt','png','jpg','jpeg','gif','webp','svg','ico','woff','woff2','ttf','eot','mp4','webm']);
define('RESERVED_SLUGS', ['default','admin','api','assets','challenge','e','includes','config','index','install','migrate','buat-link','login','logout','dashboard','events','export','readme','changelog','template-event-starter','www','static','cdn','ftp','mail','uploads','brands','setup-brand']);
define('REWARD_IMAGES_DIR', __DIR__ . '/assets/rewards');
define('REWARD_IMAGES_URL_BASE', '/assets/rewards');
define('MAX_REWARD_IMAGE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_REWARD_IMAGE_EXT', ['png','jpg','jpeg','webp','gif']);

// ==== MULTI-BRAND (v7) ====
define('BRAND_LOGOS_DIR', __DIR__ . '/uploads/brands');
define('BRAND_LOGOS_URL_BASE', '/uploads/brands');
define('MAX_LOGO_SIZE', 2 * 1024 * 1024); // 2 MB
define('ALLOWED_LOGO_EXT', ['png','jpg','jpeg','webp','svg']);

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

/** Bersihkan input teks dasar */
function clean($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/** Normalisasi nomor WhatsApp ke format 62xxxxxxxxxx (tanpa + / spasi / strip) */
function normalize_whatsapp($raw) {
    $num = preg_replace('/[^0-9]/', '', $raw);
    if (substr($num, 0, 1) === '0') {
        $num = '62' . substr($num, 1);
    } elseif (substr($num, 0, 2) !== '62') {
        $num = '62' . $num;
    }
    return $num;
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/brand.php';
require_once __DIR__ . '/includes/theme.php';
