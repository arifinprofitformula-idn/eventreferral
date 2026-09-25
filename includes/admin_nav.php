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
    $pendingOrderCount = 0;
    try {
        $currentBrand = get_current_brand();
        if ($currentBrand && table_exists(get_db(), 'lms_orders')) {
            $stmtPending = get_db()->prepare("SELECT COUNT(*) FROM lms_orders WHERE brand_id = ? AND payment_status = 'pending'");
            $stmtPending->execute([(int)$currentBrand['id']]);
            $pendingOrderCount = (int)$stmtPending->fetchColumn();
        }
    } catch (Throwable $e) {
        $pendingOrderCount = 0;
    }
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
      .adm-mobile-nav, .adm-mobile-sheet, .adm-mobile-backdrop { display: none; }
      @media (max-width: 760px) {
        body { padding-bottom: calc(88px + env(safe-area-inset-bottom)) !important; }
        .topbar-inner {
          display: flex !important;
          align-items: center !important;
          justify-content: space-between !important;
          min-height: 60px !important;
          padding-top: 10px !important;
          padding-bottom: 10px !important;
        }
        .topbar-brand, .brand, .brand-link {
          display: inline-flex !important;
          align-items: center !important;
          gap: 10px !important;
          max-width: 75% !important;
        }
        .topbar-brand img, .brand img, .brand-link img {
          max-height: 34px !important;
          width: auto !important;
          object-fit: contain !important;
        }
        .adm-nav { display: none !important; }
        .adm-mobile-nav {
          position: fixed !important; left: 12px !important; right: 12px !important; top: auto !important; bottom: calc(10px + env(safe-area-inset-bottom)) !important; z-index: 1000;
          display: grid; grid-template-columns: repeat(5, 1fr); align-items: center;
          min-height: 66px; padding: 7px 5px calc(7px + env(safe-area-inset-bottom));
          border: 1px solid color-mix(in srgb, var(--gold, #D6A536) 24%, rgba(255,255,255,.12));
          border-radius: 22px;
          background: rgba(18,18,17,.92); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
          box-shadow: 0 16px 48px rgba(0,0,0,.52), inset 0 1px 0 rgba(255,255,255,.07);
        }
        .adm-mobile-item {
          position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center;
          gap: 4px; min-width: 0; min-height: 52px; padding: 5px 2px;
          color: var(--muted, #A8A29A); border: 0; border-radius: 16px; background: transparent;
          text-decoration: none; font: inherit; font-size: 10px; font-weight: 700; letter-spacing: .01em;
          cursor: pointer; transition: transform .18s ease, color .18s ease, background .18s ease;
        }
        .adm-mobile-item:active { transform: scale(.92); }
        .adm-mobile-item.active {
          color: var(--gold-soft, #F4D27A);
          background: linear-gradient(145deg, color-mix(in srgb, var(--gold, #D6A536) 18%, transparent), rgba(255,255,255,.025));
        }
        .adm-mobile-item.active::before {
          content: ''; position: absolute; top: 1px; width: 22px; height: 2px; border-radius: 4px;
          background: var(--gold-soft, #F4D27A); box-shadow: 0 0 12px color-mix(in srgb, var(--gold, #D6A536) 80%, transparent);
        }
        .adm-mobile-item svg { width: 21px; height: 21px; }
        .adm-mobile-badge {
          position: absolute; top: 2px; left: calc(50% + 8px); display: grid; place-items: center;
          min-width: 18px; height: 18px; padding: 0 5px; border: 2px solid #121211; border-radius: 999px;
          color: #fff; background: #EF4444; font-size: 9px; line-height: 1; box-shadow: 0 3px 10px rgba(239,68,68,.42);
        }
        .adm-mobile-backdrop {
          position: fixed; inset: 0; z-index: 1001; display: block; opacity: 0; visibility: hidden;
          background: rgba(0,0,0,.64); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
          transition: opacity .24s ease, visibility .24s ease;
        }
        .adm-mobile-backdrop.open { opacity: 1; visibility: visible; }
        .adm-mobile-sheet {
          position: fixed; left: 10px; right: 10px; bottom: 10px; z-index: 1002; display: block;
          max-height: min(78vh, 640px); overflow-y: auto; overflow-x: hidden;
          padding: 8px 16px calc(22px + env(safe-area-inset-bottom));
          border: 1px solid color-mix(in srgb, var(--gold, #D6A536) 22%, rgba(255,255,255,.09));
          border-radius: 26px; background: rgba(20,20,19,.98); box-shadow: 0 -22px 70px rgba(0,0,0,.58);
          transform: translateY(calc(100% + 24px)); visibility: hidden;
          transition: transform .28s cubic-bezier(.22,.9,.32,1), visibility .28s ease;
        }
        .adm-mobile-sheet.open { transform: translateY(0); visibility: visible; }
        .adm-sheet-handle { width: 42px; height: 4px; margin: 4px auto 14px; border-radius: 4px; background: rgba(255,255,255,.24); }
        .adm-sheet-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
        .adm-sheet-head strong { font-size: 17px; color: var(--text, #F7F3E8); }
        .adm-sheet-close { width: 36px; height: 36px; border: 1px solid rgba(255,255,255,.09); border-radius: 50%; color: var(--text,#fff); background: rgba(255,255,255,.05); font-size: 20px; }
        .adm-sheet-section { margin: 16px 0 8px; color: var(--gold-soft,#F4D27A); font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .adm-sheet-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; }
        .adm-sheet-link {
          position: relative; min-height: 80px; display: flex; flex-direction: column; align-items: flex-start; justify-content: space-between;
          gap: 8px; padding: 13px 11px; border: 1px solid rgba(255,255,255,.07); border-radius: 16px;
          color: var(--text,#F7F3E8); background: rgba(255,255,255,.035); text-decoration: none; font-size: 11px; font-weight: 650;
        }
        .adm-sheet-link.active { border-color: var(--border-gold,rgba(214,165,54,.3)); background: color-mix(in srgb, var(--gold,#D6A536) 11%, transparent); }
        .adm-sheet-link svg { width: 20px; height: 20px; color: var(--gold-soft,#F4D27A); }
        .adm-sheet-link.danger { color: #FCA5A5; }
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

    <nav class="adm-mobile-nav" aria-label="Navigasi admin mobile">
      <a class="adm-mobile-item <?= $activeKey === 'dashboard' ? 'active' : '' ?>" href="dashboard.php">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Home</span>
      </a>
      <a class="adm-mobile-item <?= admin_nav_group_active(admin_nav_items()[1], $activeKey) ? 'active' : '' ?>" href="events.php">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Event</span>
      </a>
      <a class="adm-mobile-item <?= in_array($activeKey, ['lms-courses','lms-users','lms-progress','lms-course-form'], true) ? 'active' : '' ?>" href="lms-courses.php">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 6.25V19.25M12 6.25C10.83 5.48 9.25 5 7.5 5S4.17 5.48 3 6.25v13C4.17 18.47 5.75 18 7.5 18s3.33.47 4.5 1.25M12 6.25C13.17 5.48 14.75 5 16.5 5s3.33.48 4.5 1.25v13C19.83 18.47 18.25 18 16.5 18s-3.33.47-4.5 1.25" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Course</span>
      </a>
      <a class="adm-mobile-item <?= $activeKey === 'lms-orders' ? 'active' : '' ?>" href="lms-orders.php">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 4h16v16H4zM8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <?php if ($pendingOrderCount > 0): ?><span class="adm-mobile-badge"><?= $pendingOrderCount > 99 ? '99+' : $pendingOrderCount ?></span><?php endif; ?>
        <span>Order</span>
      </a>
      <button type="button" class="adm-mobile-item" id="admMoreButton" aria-controls="admMobileSheet" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg><span>Lainnya</span>
      </button>
    </nav>

    <div class="adm-mobile-backdrop" id="admMobileBackdrop"></div>
    <aside class="adm-mobile-sheet" id="admMobileSheet" aria-hidden="true" aria-label="Menu admin lainnya">
      <div class="adm-sheet-handle"></div>
      <div class="adm-sheet-head"><strong>Menu Admin</strong><button type="button" class="adm-sheet-close" id="admSheetClose" aria-label="Tutup">×</button></div>
      <?php foreach (admin_nav_items() as $sheetGroup): ?>
        <?php if (($sheetGroup['key'] ?? '') === 'dashboard' || (!empty($sheetGroup['superadmin_only']) && !$isSuperadmin)) continue; ?>
        <div class="adm-sheet-section"><?= htmlspecialchars($sheetGroup['label']) ?></div>
        <div class="adm-sheet-grid">
          <?php $sheetLinks = !empty($sheetGroup['children']) ? $sheetGroup['children'] : [$sheetGroup]; ?>
          <?php foreach ($sheetLinks as $sheetLink): ?>
            <?php if (!empty($sheetLink['superadmin_only']) && !$isSuperadmin) continue; ?>
            <a class="adm-sheet-link <?= ($sheetLink['key'] ?? '') === $activeKey ? 'active' : '' ?>" href="<?= htmlspecialchars($sheetLink['href']) ?>">
              <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="<?= htmlspecialchars($sheetGroup['icon'] ?? 'M5 12h14') ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <span><?= htmlspecialchars($sheetLink['label']) ?></span>
              <?php if (($sheetLink['key'] ?? '') === 'lms-orders' && $pendingOrderCount > 0): ?><span class="adm-mobile-badge"><?= $pendingOrderCount > 99 ? '99+' : $pendingOrderCount ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div class="adm-sheet-section">Akun</div>
      <div class="adm-sheet-grid"><a class="adm-sheet-link danger" href="logout.php"><svg viewBox="0 0 24 24" fill="none"><path d="M10 17 15 12l-5-5M15 12H3m8-9h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Keluar</span></a></div>
    </aside>

    <script>
      (function () {
        if (!document.querySelector('link[rel="manifest"]')) {
          var manifestLink = document.createElement('link');
          manifestLink.rel = 'manifest';
          manifestLink.href = '/manifest.php';
          document.head.appendChild(manifestLink);
        }
        if (!document.querySelector('meta[name="theme-color"]')) {
          var themeMeta = document.createElement('meta');
          themeMeta.name = 'theme-color';
          themeMeta.content = getComputedStyle(document.documentElement).getPropertyValue('--gold').trim() || '#D6A536';
          document.head.appendChild(themeMeta);
        }
        if ('serviceWorker' in navigator) {
          window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
        }

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

        var mobileNav = document.querySelector('.adm-mobile-nav');
        var sheet = document.getElementById('admMobileSheet');
        var backdrop = document.getElementById('admMobileBackdrop');
        if (mobileNav && mobileNav.parentNode !== document.body) document.body.appendChild(mobileNav);
        if (backdrop && backdrop.parentNode !== document.body) document.body.appendChild(backdrop);
        if (sheet && sheet.parentNode !== document.body) document.body.appendChild(sheet);

        var moreBtn = document.getElementById('admMoreButton');
        var closeBtn = document.getElementById('admSheetClose');

        function toggleSheet(open) {
          if (!sheet || !backdrop) return;
          var state = typeof open === 'boolean' ? open : !sheet.classList.contains('open');
          sheet.classList.toggle('open', state);
          backdrop.classList.toggle('open', state);
          sheet.setAttribute('aria-hidden', state ? 'false' : 'true');
          if (moreBtn) moreBtn.setAttribute('aria-expanded', state ? 'true' : 'false');
          document.body.style.overflow = state ? 'hidden' : '';
        }

        if (moreBtn) moreBtn.addEventListener('click', function () { toggleSheet(true); });
        if (closeBtn) closeBtn.addEventListener('click', function () { toggleSheet(false); });
        if (backdrop) backdrop.addEventListener('click', function () { toggleSheet(false); });
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && sheet && sheet.classList.contains('open')) toggleSheet(false);
        });
      })();
    </script>
    <?php
}
