<?php
/**
 * includes/admin_nav.php
 * Header navigasi admin terpusat — SATU-SATUNYA tempat daftar menu admin didefinisikan.
 * Semua halaman admin yang dilindungi require_admin_for_brand()/require_superadmin_for_brand()
 * WAJIB memanggil render_admin_nav() supaya selalu muncul di menu (jangan tambah halaman baru
 * tanpa mendaftarkannya di admin_nav_items() di bawah — itu artinya halaman tersembunyi dari admin).
 */

/**
 * Struktur menu admin. Tiap item top-level bisa berupa link langsung (punya 'href')
 * atau grup dropdown (punya 'children'). 'superadmin_only' menyembunyikan item dari admin brand biasa.
 */
function admin_nav_items(): array {
    return [
        [
            'key' => 'dashboard',
            'href' => 'dashboard.php',
            'label' => 'Dashboard',
            'icon' => 'M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10.5Z',
        ],
        [
            'key' => 'event',
            'label' => 'Event',
            'icon' => 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z',
            'children' => [
                ['key' => 'events', 'href' => 'events.php', 'label' => 'Kelola Event'],
                ['key' => 'event-settings', 'href' => 'event-settings.php', 'label' => 'Pengaturan Event'],
                ['key' => 'event-attendance', 'href' => 'event-attendance.php', 'label' => 'Kehadiran Event'],
                ['key' => 'event-attendance-report', 'href' => 'event-attendance-report.php', 'label' => 'Rekap Kehadiran'],
                ['key' => 'event-insights', 'href' => 'event-insights.php', 'label' => 'Insight Peserta'],
                ['key' => 'lucky-draw', 'href' => 'lucky-draw-control.php', 'label' => 'Kontrol Undian'],
                ['key' => 'rewards', 'href' => 'rewards.php', 'label' => 'Reward'],
            ],
        ],
        [
            'key' => 'marketing-data',
            'label' => 'Marketing & Analitik',
            'icon' => 'M3 3v18h18M7 16v-5m5 5V8m5 8V5',
            'children' => [
                ['key' => 'marketing-content', 'href' => 'marketing-content.php', 'label' => 'Konten Marketing'],
                ['key' => 'visitor-analytics', 'href' => 'visitor-analytics.php', 'label' => 'Analitik Pengunjung'],
                ['key' => 'tracking', 'href' => 'tracking.php', 'label' => 'Tracking Pixel'],
            ],
        ],
        [
            'key' => 'lms',
            'label' => 'Course',
            'icon' => 'M12 6.25278V19.25M12 6.25278C10.8321 5.47686 9.24649 5 7.5 5C5.75351 5 4.16789 5.47686 3 6.25278V19.25C4.16789 18.4741 5.75351 18 7.5 18C9.24649 18 10.8321 18.4741 12 19.25M12 6.25278C13.1679 5.47686 14.7535 5 16.5 5C18.2465 5 19.8321 5.47686 21 6.25278V19.25C19.8321 18.4741 18.2465 18 16.5 18C14.7535 18 13.1679 18.4741 12 19.25',
            'children' => [
                ['key' => 'lms-courses', 'href' => 'lms-courses.php', 'label' => 'Kelola Course'],
                ['key' => 'lms-users', 'href' => 'lms-users.php', 'label' => 'User & Role'],
                ['key' => 'lms-orders', 'href' => 'lms-orders.php', 'label' => 'Order & Pembayaran'],
                ['key' => 'lms-progress', 'href' => 'lms-progress.php', 'label' => 'Progress Belajar'],
            ],
        ],
        [
            'key' => 'settings',
            'label' => 'Pengaturan',
            'icon' => 'M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5ZM19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2 3.46-.08-.02a1.7 1.7 0 0 0-1.8-.23l-.5.29a1.7 1.7 0 0 0-.85 1.7V22h-4v-.09a1.7 1.7 0 0 0-.85-1.7l-.5-.29a1.7 1.7 0 0 0-1.8.23l-.08.02-2-3.46.06-.06A1.7 1.7 0 0 0 4.6 15v-.58a1.7 1.7 0 0 0-1-1.55L3.5 12.8v-4l.1-.04a1.7 1.7 0 0 0 1-1.55v-.58a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2-3.46.08.02a1.7 1.7 0 0 0 1.8.23l.5-.29a1.7 1.7 0 0 0 .85-1.7V0h4v.09a1.7 1.7 0 0 0 .85 1.7l.5.29a1.7 1.7 0 0 0 1.8-.23l.08-.02 2 3.46-.06.06a1.7 1.7 0 0 0-.34 1.88v.58a1.7 1.7 0 0 0 1 1.55l.1.04v4l-.1.04a1.7 1.7 0 0 0-1 1.55V15Z',
            'children' => [
                ['key' => 'email-settings', 'href' => 'email-settings.php', 'label' => 'Email'],
                ['key' => 'payment-settings', 'href' => 'payment-settings.php', 'label' => 'Pembayaran'],
                ['key' => 'integrations', 'href' => 'integrations.php', 'label' => 'Integrasi'],
                ['key' => 'documentation', 'href' => 'documentation.php', 'label' => 'Dokumentasi'],
                ['key' => 'admin-users', 'href' => 'admin-users.php', 'label' => 'Kelola Admin', 'superadmin_only' => true],
                ['key' => 'ai-settings', 'href' => 'ai-settings.php', 'label' => 'Pengaturan AI', 'superadmin_only' => true],
            ],
        ],
    ];
}

/** true jika salah satu key di grup (atau grup itu sendiri) sedang aktif. */
function admin_nav_group_active(array $item, string $activeKey): bool {
    if (($item['key'] ?? null) === $activeKey) {
        return true;
    }
    foreach ($item['children'] ?? [] as $child) {
        if (($child['key'] ?? null) === $activeKey) {
            return true;
        }
    }
    return false;
}

/** Render style + markup nav admin. $activeKey harus cocok dengan salah satu 'key' di admin_nav_items(). */
function render_admin_nav(string $activeKey): void {
    $isSuperadmin = !empty($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin';
    ?>
    <style>
      .adm-nav { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; position: relative; }
      .adm-nav a, .adm-nav summary {
        display: inline-flex; align-items: center; gap: 8px;
        color: var(--muted, #A8A29A);
        border: 1px solid transparent;
        border-radius: 999px;
        cursor: pointer;
        font: inherit;
        font-size: 13.5px; font-weight: 600; line-height: 1;
        list-style: none; white-space: nowrap;
        padding: 11px 14px;
        text-decoration: none;
        transition: color 180ms ease, background 180ms ease, border-color 180ms ease;
      }
      .adm-nav summary::-webkit-details-marker { display: none; }
      .adm-nav a:hover, .adm-nav summary:hover { color: var(--text, #F7F3E8); background: rgba(255,255,255,0.05); }
      .adm-nav a.active, .adm-nav summary.active {
        color: var(--gold-soft, #F4D27A);
        background: color-mix(in srgb, var(--gold, #D6A536) 10%, transparent);
        border-color: var(--border-gold, rgba(214,165,54,0.18));
      }
      .adm-nav svg { width: 16px; height: 16px; flex: 0 0 16px; }
      .adm-nav .adm-caret { width: 10px; height: 10px; transition: transform 180ms ease; }
      .adm-nav details[open] > summary .adm-caret { transform: rotate(180deg); }
      .adm-nav details { position: relative; }
      .adm-nav .adm-dropdown {
        position: absolute; top: calc(100% + 8px); left: 0; z-index: 40;
        display: flex; flex-direction: column; gap: 2px;
        min-width: 226px;
        background: rgba(20,20,19,0.98);
        border: 1px solid var(--border-gold, rgba(214,165,54,0.18));
        border-radius: 14px;
        box-shadow: 0 18px 44px rgba(0,0,0,0.4);
        padding: 8px;
      }
      .adm-nav .adm-dropdown a { width: 100%; border-radius: 10px; padding: 10px 12px; }
      .adm-nav .adm-logout {
        justify-content: center;
        width: 42px; height: 42px; padding: 0;
        border-color: rgba(255,255,255,0.10);
        background: rgba(255,255,255,0.035);
      }
      @media (max-width: 760px) {
        .adm-nav { width: 100%; }
        .adm-nav a, .adm-nav summary { padding: 10px 12px; font-size: 12.5px; }
        .adm-nav .adm-dropdown {
          position: static; margin-top: 4px; padding-left: 10px;
          background: transparent; border: 0; box-shadow: none;
        }
      }
    </style>
    <nav class="adm-nav" aria-label="Navigasi admin">
      <?php foreach (admin_nav_items() as $item): ?>
        <?php if (!empty($item['superadmin_only']) && !$isSuperadmin) continue; ?>
        <?php $isActive = admin_nav_group_active($item, $activeKey); ?>
        <?php if (!empty($item['children'])): ?>
          <details>
            <summary class="<?= $isActive ? 'active' : '' ?>">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="<?= htmlspecialchars($item['icon']) ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <?= htmlspecialchars($item['label']) ?>
              <svg class="adm-caret" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </summary>
            <div class="adm-dropdown">
              <?php foreach ($item['children'] as $child): ?>
                <?php if (!empty($child['superadmin_only']) && !$isSuperadmin) continue; ?>
                <a href="<?= htmlspecialchars($child['href']) ?>" class="<?= $child['key'] === $activeKey ? 'active' : '' ?>"><?= htmlspecialchars($child['label']) ?></a>
              <?php endforeach; ?>
            </div>
          </details>
        <?php else: ?>
          <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $isActive ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="<?= htmlspecialchars($item['icon']) ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <?= htmlspecialchars($item['label']) ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
      <a class="adm-logout" href="logout.php" title="Keluar" aria-label="Keluar">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 17 15 12l-5-5M15 12H3m8-9h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </nav>
    <script>
      (function () {
        var groups = document.querySelectorAll('.adm-nav details');
        groups.forEach(function (el) {
          el.addEventListener('toggle', function () {
            if (!el.open) return;
            groups.forEach(function (other) {
              if (other !== el) other.open = false;
            });
          });
        });
        document.addEventListener('click', function (e) {
          groups.forEach(function (el) {
            if (el.open && !el.contains(e.target)) el.open = false;
          });
        });
      })();
    </script>
    <?php
}
