<?php
if (!function_exists('render_pwa_head_tags')) {
    function render_pwa_head_tags(array $brand = []): void {
        $theme = $brand['theme_primary'] ?? '#D6A536';
        $brandName = htmlspecialchars($brand['name'] ?? 'RahasiaEmas.id', ENT_QUOTES, 'UTF-8');
        ?>
<link rel="manifest" href="/manifest.php">
<meta name="theme-color" content="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<meta name="application-name" content="<?= $brandName ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= $brandName ?>">
<link rel="apple-touch-icon" href="/assets/pwa/icon-192.png">
        <?php
    }
}

if (!function_exists('render_pwa_register_script')) {
    function render_pwa_register_script(): void {
        ?>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
  }
</script>
        <?php
    }
}

if (!function_exists('inject_pwa_into_html')) {
    function inject_pwa_into_html(string $html, array $brand = []): string {
        ob_start();
        render_pwa_head_tags($brand);
        $headTags = trim(ob_get_clean());
        ob_start();
        render_pwa_register_script();
        $script = trim(ob_get_clean());

        if (stripos($html, '<link rel="manifest"') === false && stripos($html, "<link rel='manifest'") === false) {
            $html = preg_replace('/<\/head>/i', $headTags . "\n</head>", $html, 1) ?? $html;
        }
        if (stripos($html, 'serviceWorker.register') === false) {
            $html = preg_replace('/<\/body>/i', $script . "\n</body>", $html, 1) ?? ($html . $script);
        }
        return $html;
    }
}
