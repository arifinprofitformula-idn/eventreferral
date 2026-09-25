<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';

$brand = get_current_brand();
$name = $brand['name'] ?? 'RahasiaEmas.id';
$theme = $brand['theme_primary'] ?? '#D6A536';
$description = $brand['tagline'] ?? 'Platform event, referral, dan eCourse.';
$startUrl = '/?source=pwa';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo json_encode([
    'name' => $name,
    'short_name' => mb_substr($name, 0, 12),
    'description' => $description,
    'start_url' => $startUrl,
    'scope' => '/',
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui', 'browser'],
    'orientation' => 'portrait-primary',
    'background_color' => '#090908',
    'theme_color' => $theme,
    'categories' => ['business', 'education', 'productivity'],
    'icons' => [
        [
            'src' => '/assets/pwa/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => '/assets/pwa/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
    'shortcuts' => [
        [
            'name' => 'Katalog eCourse',
            'short_name' => 'eCourse',
            'url' => '/course/index.php',
            'icons' => [['src' => '/assets/pwa/icon-192.png', 'sizes' => '192x192']],
        ],
        [
            'name' => 'Dashboard Admin',
            'short_name' => 'Admin',
            'url' => '/admin/dashboard.php',
            'icons' => [['src' => '/assets/pwa/icon-192.png', 'sizes' => '192x192']],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
