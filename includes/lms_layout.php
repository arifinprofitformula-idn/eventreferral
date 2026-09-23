<?php
/**
 * includes/lms_layout.php
 * Shared UI Layout & Navigation untuk LMS User, Guest, dan Student Portal.
 */

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
  @media (max-width: 768px) {
    .lms-nav-container { height: auto; padding: 14px 18px; flex-direction: column; align-items: stretch; }
    .lms-menu { justify-content: center; flex-wrap: wrap; }
    .lms-auth-box { justify-content: center; flex-wrap: wrap; }
    .lms-main { padding: 24px 16px 56px; }
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
</body>
</html>
        <?php
    }
}
