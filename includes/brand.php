<?php
/**
 * includes/brand.php
 * Deteksi brand aktif berdasarkan domain yang diakses (multi-tenant).
 */

/**
 * Ambil baris brand aktif untuk domain yang sedang diakses ($_SERVER['HTTP_HOST']).
 * Hasilnya di-cache secara statis supaya hanya 1 query per request.
 *
 * @return array|null Baris tabel `brands`, atau null jika domain tidak terdaftar.
 */
function get_current_brand(): ?array {
    static $brand = null;
    static $resolved = false;

    if ($resolved) {
        return $brand;
    }
    $resolved = true;

    $host = preg_replace('/^www\./', '', strtolower($_SERVER['HTTP_HOST'] ?? ''));
    $host = explode(':', $host)[0]; // buang port kalau ada (mis. localhost:8000)

    $pdo = get_db(false);
    if (!$pdo) {
        return $brand;
    }

    $stmt = $pdo->prepare("SELECT * FROM brands WHERE domain = ? AND status = 'active'");
    $stmt->execute([$host]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // Override HANYA untuk localhost/testing, tidak pernah aktif di production.
    if (!$brand && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && isset($_GET['__brand'])) {
        $stmt = $pdo->prepare("SELECT * FROM brands WHERE slug = ? AND status = 'active'");
        $stmt->execute([clean($_GET['__brand'])]);
        $brand = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    return $brand;
}

/**
 * Panggil di awal setiap halaman publik/admin setelah get_current_brand().
 * Kalau brand tidak ditemukan, tampilkan 404 brand-agnostic dan hentikan eksekusi.
 */
function require_brand_or_404(?array $brand): array {
    if ($brand !== null) {
        return $brand;
    }

    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Halaman Tidak Ditemukan</title>
<style>
  body { background: #1A1A1A; color: #FAFAFA; font-family: Arial, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; text-align: center; padding: 24px; }
</style>
</head>
<body>
  <div>
    <h1>404</h1>
    <p>Halaman tidak ditemukan.</p>
  </div>
</body>
</html>
    <?php
    exit;
}
