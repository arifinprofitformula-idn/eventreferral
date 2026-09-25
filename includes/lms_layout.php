<?php
/**
 * includes/lms_layout.php
 * Shared UI Layout & Navigation untuk LMS User, Guest, dan Student Portal.
 */

require_once __DIR__ . '/pwa.php';

if (!function_exists('render_lms_header')) {
    function render_lms_header(array $brand, ?array $user, string $activePage = 'courses'): void {
        $brandName = htmlspecialchars($brand['name'] ?? 'RahasiaEmas.id', ENT_QUOTES, 'UTF-8');
        $logoPath = !empty($brand['logo_path']) ? $brand['logo_path'] : '/assets/logo.png';
        $role = lms_current_role($user);
        $roleBadge = 'Guest';
        $roleClass = 'badge-guest';

        if ($role === 'free') {
            $roleBadge = 'Free Access';
            $roleClass = 'badge-free';
        } elseif ($role === 'paid') {
            $roleBadge = 'Paid User ★';
            $roleClass = 'badge-paid';
        } elseif ($role === 'admin') {
            $roleBadge = 'Admin LMS';
            $roleClass = 'badge-admin';
        }

        $unreadCount = 0;
        if ($user) {
            $pdo = get_db();
            $unreadCount = lms_get_unread_notifications_count($pdo, (int)$user['id']);
        }
        ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $brandName ?> — Simple LMS</title>
<?php render_pwa_head_tags($brand); ?>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg: #090908;
    --bg-soft: #121210;
    --surface: #181816;
    --surface-elevated: #22221F;
    --gold: var(--brand-primary, #D6A536);
    --gold-soft: var(--brand-soft, #F4D27A);
    --gold-glow: color-mix(in srgb, var(--gold) 16%, transparent);
    --border-gold: color-mix(in srgb, var(--gold) 20%, transparent);
    --border-soft: rgba(255, 255, 255, 0.08);
    --text: #F8F5EE;
    --muted: #A39E93;
    --success: #22C55E;
    --danger: #EF4444;
    --warning: #F59E0B;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: radial-gradient(circle at 85% 10%, color-mix(in srgb, var(--gold) 18%, transparent), transparent 30vw),
                radial-gradient(circle at 10% 90%, color-mix(in srgb, var(--gold-soft) 12%, transparent), transparent 32vw),
                linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 60%, #060605 100%);
    color: var(--text);
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }
  a { color: inherit; text-decoration: none; }
  .lms-navbar {
    position: sticky;
    top: 0;
    z-index: 50;
    background: rgba(18, 18, 16, 0.85);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid var(--border-soft);
  }
  .lms-nav-container {
    max-width: 1240px;
    margin: 0 auto;
    padding: 0 24px;
    height: 74px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
  }
  .lms-brand {
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .lms-brand img {
    height: 40px;
    width: auto;
    object-fit: contain;
  }
  .lms-brand-text {
    font-size: 18px;
    font-weight: 800;
    letter-spacing: -0.3px;
    color: var(--text);
  }
  .lms-brand-text span {
    color: var(--gold-soft);
    font-size: 13px;
    padding-left: 6px;
    font-weight: 700;
  }
  .lms-menu {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .lms-menu-item {
    padding: 9px 16px;
    border-radius: 999px;
    font-size: 13.5px;
    font-weight: 600;
    color: var(--muted);
    transition: all 180ms ease;
    border: 1px solid transparent;
  }
  .lms-menu-item:hover {
    color: var(--text);
    background: rgba(255,255,255,0.05);
  }
  .lms-menu-item.active {
    color: var(--gold-soft);
    background: var(--gold-glow);
    border-color: var(--border-gold);
  }
  .lms-auth-box {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .role-pill {
    font-size: 11.5px;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 999px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .badge-guest { background: rgba(255,255,255,0.08); color: var(--muted); border: 1px solid rgba(255,255,255,0.1); }
  .badge-free { background: rgba(59, 130, 246, 0.15); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3); }
  .badge-paid { background: linear-gradient(135deg, rgba(214,165,54,0.25), rgba(244,210,122,0.15)); color: var(--gold-soft); border: 1px solid var(--border-gold); }
  .badge-admin { background: rgba(168, 85, 247, 0.18); color: #d8b4fe; border: 1px solid rgba(168, 85, 247, 0.35); }
  .btn-lms {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 12px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 180ms ease;
  }
  .btn-lms-gold {
    background: linear-gradient(135deg, var(--gold), var(--gold-soft));
    color: #111110;
    box-shadow: 0 8px 24px var(--gold-glow);
  }
  .btn-lms-gold:hover { transform: translateY(-1px); filter: brightness(1.06); }
  .btn-lms-ghost {
    background: rgba(255,255,255,0.05);
    color: var(--text);
    border-color: var(--border-soft);
  }
  .btn-lms-ghost:hover { background: rgba(255,255,255,0.1); }
  .notif-bell {
    position: relative;
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border-soft);
    color: var(--text);
  }
  .notif-bell:hover { background: rgba(255,255,255,0.08); }
  .notif-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    background: var(--danger);
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 6px;
    border-radius: 999px;
  }
  .lms-main {
    flex: 1;
    max-width: 1240px;
    width: 100%;
    margin: 0 auto;
    padding: 36px 24px 72px;
  }
  .lms-footer {
    border-top: 1px solid var(--border-soft);
    background: #0d0d0c;
    padding: 28px 24px;
    text-align: center;
    font-size: 13px;
    color: var(--muted);
  }
  .lms-mobile-nav { display: none; }
  @media (max-width: 768px) {
    body { padding-bottom: calc(86px + env(safe-area-inset-bottom)); }
    .lms-navbar { background: rgba(14,14,13,.92); backdrop-filter: blur(22px); }
    .lms-nav-container {
      height: 62px; padding: 9px 16px; flex-direction: row; align-items: center; gap: 10px;
    }
    .lms-nav-container > div:first-child { min-width: 0; flex: 1; }
    .lms-brand { min-width: 0; gap: 9px; }
    .lms-brand img { max-width: 108px; height: 34px; }
    .lms-brand-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; }
    .lms-brand-text span { font-size: 10px; padding-left: 3px; }
    .role-pill { flex: 0 0 auto; padding: 4px 7px; font-size: 8.5px; letter-spacing: .25px; }
    .lms-menu { display: none; }
    .lms-auth-box { flex: 0 0 auto; justify-content: flex-end; }
    .lms-auth-box > div > span, .lms-auth-box .btn-lms { display: none; }
    .lms-auth-box > .btn-lms-ghost { display: none; }
    .lms-auth-box > .btn-lms-gold { display: inline-flex; padding: 8px 11px; border-radius: 10px; font-size: 11px; }
    .notif-bell { width: 36px; height: 36px; }
    .lms-main { padding: 20px 16px 42px; }
    .lms-footer { padding-bottom: 110px; }
    .lms-mobile-nav {
      position: fixed; left: 12px; right: 12px; bottom: calc(10px + env(safe-area-inset-bottom)); z-index: 1000;
      display: grid; grid-template-columns: repeat(4, 1fr); align-items: center;
      min-height: 66px; padding: 7px 6px;
      border: 1px solid color-mix(in srgb, var(--gold) 25%, rgba(255,255,255,.1)); border-radius: 22px;
      background: rgba(17,17,15,.93); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
      box-shadow: 0 16px 48px rgba(0,0,0,.52), inset 0 1px 0 rgba(255,255,255,.07);
    }
    .lms-mobile-item {
      position: relative; min-height: 51px; display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 4px; padding: 4px 2px; border-radius: 15px; color: var(--muted); font-size: 10px; font-weight: 750;
      transition: color .18s ease, background .18s ease, transform .18s ease;
    }
    .lms-mobile-item:active { transform: scale(.92); }
    .lms-mobile-item.active { color: var(--gold-soft); background: var(--gold-glow); }
    .lms-mobile-item.active::before {
      content: ''; position: absolute; top: 0; width: 22px; height: 2px; border-radius: 4px;
      background: var(--gold-soft); box-shadow: 0 0 12px color-mix(in srgb, var(--gold) 80%, transparent);
    }
    .lms-mobile-item svg { width: 21px; height: 21px; }
    .lms-mobile-count {
      position: absolute; top: 1px; left: calc(50% + 7px); display: grid; place-items: center;
      min-width: 18px; height: 18px; padding: 0 5px; border: 2px solid #11110f; border-radius: 999px;
      color: #fff; background: var(--danger); font-size: 9px;
    }
  }
</style>
</head>
<body>
<header class="lms-navbar">
  <div class="lms-nav-container">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
      <a href="/course/index.php" class="lms-brand">
        <img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= $brandName ?>" onerror="this.style.display='none'">
        <div class="lms-brand-text"><?= $brandName ?> <span>LMS</span></div>
      </a>
      <span class="role-pill <?= $roleClass ?>" style="display:inline-block;"><?= $roleBadge ?></span>
    </div>

    <nav class="lms-menu">
      <a href="/course/index.php" class="lms-menu-item <?= $activePage === 'catalog' ? 'active' : '' ?>">Katalog eCourse</a>
      <?php if ($user): ?>
        <a href="/course/my-courses.php" class="lms-menu-item <?= $activePage === 'my_courses' ? 'active' : '' ?>">My Courses</a>
      <?php endif; ?>
      <?php if ($role === 'admin'): ?>
        <a href="/admin/lms-courses.php" class="lms-menu-item" style="color:var(--gold-soft);">Panel Admin LMS →</a>
      <?php endif; ?>
    </nav>

    <div class="lms-auth-box">
      <?php if ($user): ?>
        <div title="<?= htmlspecialchars($user['email']) ?>" style="display:flex;align-items:center;gap:10px;">
          <a href="/course/notifications.php" class="notif-bell" title="Notifikasi LMS">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <?php if ($unreadCount > 0): ?>
              <span class="notif-dot"><?= $unreadCount ?></span>
            <?php endif; ?>
          </a>
          <span style="font-size:13.5px;font-weight:700;color:var(--text);"><?= htmlspecialchars($user['name']) ?></span>
          <a href="/course/logout.php" class="btn-lms btn-lms-ghost" style="padding:7px 12px;font-size:12px;">Logout</a>
        </div>
      <?php else: ?>
        <a href="/course/login.php" class="btn-lms btn-lms-ghost">Login Siswa</a>
        <a href="/course/register.php" class="btn-lms btn-lms-gold">Daftar Free</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<nav class="lms-mobile-nav" aria-label="Navigasi siswa mobile">
  <a class="lms-mobile-item <?= $activePage === 'catalog' ? 'active' : '' ?>" href="/course/index.php">
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 6.25V19.25M12 6.25C10.8 5.45 9.3 5 7.5 5S4.2 5.45 3 6.25v13C4.2 18.45 5.7 18 7.5 18s3.3.45 4.5 1.25M12 6.25C13.2 5.45 14.7 5 16.5 5s3.3.45 4.5 1.25v13C19.8 18.45 18.3 18 16.5 18s-3.3.45-4.5 1.25" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
    <span>Katalog</span>
  </a>
  <?php if ($user): ?>
    <a class="lms-mobile-item <?= $activePage === 'my_courses' ? 'active' : '' ?>" href="/course/my-courses.php">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m4 6 8-3 8 3-8 3-8-3Zm2 3v6c3 2 9 2 12 0V9M20 7v6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Course Saya</span>
    </a>
    <a class="lms-mobile-item <?= $activePage === 'notifications' ? 'active' : '' ?>" href="/course/notifications.php">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      <?php if ($unreadCount > 0): ?><span class="lms-mobile-count"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?><span>Notifikasi</span>
    </a>
    <a class="lms-mobile-item" href="/course/logout.php">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 17 15 12l-5-5M15 12H3m8-9h8a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Keluar</span>
    </a>
  <?php else: ?>
    <a class="lms-mobile-item <?= $activePage === 'login' ? 'active' : '' ?>" href="/course/login.php">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Masuk</span>
    </a>
    <a class="lms-mobile-item <?= $activePage === 'register' ? 'active' : '' ?>" href="/course/register.php">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm10-4v6m3-3h-6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Daftar</span>
    </a>
    <a class="lms-mobile-item" href="/">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V21h-6v-7H9v7H3V10.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Beranda</span>
    </a>
  <?php endif; ?>
</nav>
<main class="lms-main">
        <?php
    }
}

if (!function_exists('render_lms_footer')) {
    function render_lms_footer(array $brand): void {
        $brandName = htmlspecialchars($brand['name'] ?? 'RahasiaEmas.id', ENT_QUOTES, 'UTF-8');
        ?>
</main>
<footer class="lms-footer">
  <div style="max-width:1240px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;">
    <div>&copy; <?= date('Y') ?> <?= $brandName ?> — Platform Edukasi eCourse & Simple LMS</div>
    <div style="display:flex;gap:16px;">
      <a href="/course/index.php" style="color:var(--muted);">Katalog</a>
      <a href="/buat-link.php" style="color:var(--muted);">Referral</a>
      <a href="/challenge/" style="color:var(--muted);">Leaderboard</a>
    </div>
  </div>
</footer>
<?php render_pwa_register_script(); ?>
</body>
</html>
        <?php
    }
}
