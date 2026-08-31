<?php
/**
 * admin/event-insights.php
 * Insight strategis dari field kualitatif yang diisi peserta saat konfirmasi kehadiran
 * (/hadir/{slug}): kota domisili, sumber informasi, status peserta, feedback, dan tema
 * yang ingin dipelajari berikutnya. Dipisah dari event-attendance-report.php karena
 * fokusnya beda: itu rekap ANGKA kehadiran, ini insight KUALITATIF untuk strategi event
 * berikutnya (mis. kota mana yang perlu digarap event offline-nya).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/attendance.php';
require_once __DIR__ . '/../includes/admin_nav.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

$infoSourceLabel = attendance_info_source_options();
$participantStatusLabel = attendance_participant_status_options();

// Field tambahan mungkin belum ada di server yang migrasinya belum lengkap — cek dulu
// satu-satu supaya halaman tetap jalan (skip kolom yang belum ada), bukan 500 mentah.
$extraFieldsReady = false;
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM event_attendance LIKE 'info_source'");
    $extraFieldsReady = (bool)$columnCheck->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $extraFieldsReady = false;
}
$nextTopicReady = false;
try {
    $columnCheck = $pdo->query("SHOW COLUMNS FROM event_attendance LIKE 'next_topic_interest'");
    $nextTopicReady = (bool)$columnCheck->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $nextTopicReady = false;
}

// ==================== FILTER EVENT ====================
$eventsStmt = $pdo->prepare('SELECT slug, name FROM events WHERE brand_id = ? ORDER BY (slug = ?) DESC, created_at DESC');
$eventsStmt->execute([$brandId, $brand['default_event_slug']]);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

$requestedSlug = clean($_GET['event'] ?? '');
$selectedEventSlug = '';
foreach ($events as $ev) {
    if ($ev['slug'] === $requestedSlug) {
        $selectedEventSlug = $ev['slug'];
        break;
    }
}

// ==================== DATA PESERTA HADIR ====================
$rows = [];
if ($extraFieldsReady || $nextTopicReady) {
    $select = 'l.name, l.kota, e.slug AS event_slug, e.name AS event_name, ea.check_in_time';
    if ($extraFieldsReady) {
        $select .= ', ea.info_source, ea.participant_status, ea.feedback_notes';
    }
    if ($nextTopicReady) {
        $select .= ', ea.next_topic_interest';
    }

    $where = ['e.brand_id = ?', "ea.attendance_status = 'hadir'"];
    $params = [$brandId];
    if ($selectedEventSlug !== '') {
        $where[] = 'e.slug = ?';
        $params[] = $selectedEventSlug;
    }

    $stmt = $pdo->prepare("
        SELECT {$select}
        FROM leads l
        INNER JOIN event_attendance ea ON ea.registrant_id = l.id
        INNER JOIN events e ON e.id = ea.event_id
        WHERE " . implode(' AND ', $where) . '
        ORDER BY ea.check_in_time DESC
        LIMIT 5000
    ');
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ==================== AGREGASI ====================
$kotaCounts = [];
$kotaDisplay = [];
$infoSourceCounts = [];
$participantStatusCounts = [];
$topicList = [];
$feedbackList = [];

foreach ($rows as $r) {
    $kotaRaw = trim((string)($r['kota'] ?? ''));
    if ($kotaRaw !== '') {
        $key = mb_strtolower($kotaRaw);
        $kotaCounts[$key] = ($kotaCounts[$key] ?? 0) + 1;
        if (!isset($kotaDisplay[$key])) {
            $kotaDisplay[$key] = ucwords($kotaRaw);
        }
    }

    if (!empty($r['info_source'])) {
        $infoSourceCounts[$r['info_source']] = ($infoSourceCounts[$r['info_source']] ?? 0) + 1;
    }
    if (!empty($r['participant_status'])) {
        $participantStatusCounts[$r['participant_status']] = ($participantStatusCounts[$r['participant_status']] ?? 0) + 1;
    }
    if (!empty($r['next_topic_interest'])) {
        $topicList[] = $r;
    }
    if (!empty($r['feedback_notes'])) {
        $feedbackList[] = $r;
    }
}
arsort($kotaCounts);
arsort($infoSourceCounts);
arsort($participantStatusCounts);

$totalRecords = count($rows);
$kotaRanked = $kotaCounts;
$kotaTop3 = array_slice($kotaRanked, 0, 3, true);
$kotaMaxCount = $kotaRanked ? max($kotaRanked) : 0;
$kotaTotalWithCity = array_sum($kotaRanked);
$infoSourceMax = $infoSourceCounts ? max($infoSourceCounts) : 0;
$participantStatusMax = $participantStatusCounts ? max($participantStatusCounts) : 0;

$filterActive = $selectedEventSlug !== '';
$exportQuery = $selectedEventSlug !== '' ? '?event=' . urlencode($selectedEventSlug) : '';
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Insight Peserta — <?= htmlspecialchars($brand['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg:#0B0B0A; --bg-soft:#10100F;
    --border-gold:color-mix(in srgb, var(--gold) 18%, transparent);
    --border-soft:rgba(255,255,255,0.09);
    --gold:var(--brand-primary); --gold-soft:var(--brand-soft);
    --text:#F7F3E8; --muted:#A8A29A; --success:#22C55E; --warning:#F59E0B;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    min-height: 100vh;
    background: linear-gradient(135deg, var(--bg) 0%, var(--bg-soft) 55%, #090908 100%);
    color: var(--text);
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  a { color: inherit; }
  .topbar { position: sticky; top: 0; z-index: 20; background: rgba(16,16,15,0.85); border-bottom: 1px solid var(--border-soft); backdrop-filter: blur(14px); }
  .topbar-inner { width: min(100%, 1280px); margin: 0 auto; min-height: 76px; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 24px; }
  .brand { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
  .brand img { width: 130px; height: auto; }
  .wrap { width: min(100%, 1280px); margin: 0 auto; padding: 26px 24px 60px; }
  h1 { font-family: "Playfair Display", Georgia, serif; font-size: clamp(26px, 4vw, 36px); margin-bottom: 6px; }
  .subtitle { color: var(--muted); font-size: 14px; margin-bottom: 22px; }
  .stats { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 16px; margin-bottom: 22px; }
  .stat-card {
    background: linear-gradient(145deg, rgba(32,32,30,0.95), rgba(23,23,22,0.93));
    border: 1px solid var(--border-gold); border-radius: 20px; padding: 20px;
  }
  .stat-label { color: var(--muted); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
  .stat-num { color: var(--text); font-size: 32px; font-weight: 900; line-height: 1; margin-top: 8px; }
  .section-card {
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    border: 1px solid var(--border-gold); border-radius: 22px; padding: 22px; margin-bottom: 18px;
  }
  .filter-card { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
  .filter-title { color: var(--muted); font-size: 12px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 6px; }
  .filter-period { font-size: 18px; font-weight: 900; }
  .filter-form { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
  .filter-field { display: grid; gap: 6px; min-width: 220px; }
  .filter-field label { color: var(--muted); font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
  .filter-field select {
    appearance: none; border: 1px solid var(--border-soft); border-radius: 12px; background: rgba(8,8,7,0.72);
    color: var(--text); font: inherit; font-size: 13px; font-weight: 700; min-height: 42px; padding: 0 36px 0 12px;
    background-image: linear-gradient(45deg, transparent 50%, var(--muted) 50%), linear-gradient(135deg, var(--muted) 50%, transparent 50%);
    background-position: calc(100% - 18px) 18px, calc(100% - 13px) 18px; background-size: 5px 5px, 5px 5px; background-repeat: no-repeat;
  }
  .filter-actions { display: flex; gap: 8px; align-items: center; }
  .btn {
    min-height: 42px; border-radius: 12px; border: 1px solid var(--border-gold); padding: 0 14px;
    color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 12%, transparent); font-size: 13px; font-weight: 900;
    text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
  }
  .btn.secondary { color: var(--muted); border-color: var(--border-soft); background: rgba(255,255,255,0.04); }
  h2 { font-size: 18px; font-weight: 900; margin-bottom: 4px; }
  .desc { color: var(--muted); font-size: 13px; margin-bottom: 18px; }
  .bar-row { display: grid; grid-template-columns: 160px 1fr 44px; align-items: center; gap: 12px; padding: 7px 0; }
  .bar-label { font-size: 13px; font-weight: 700; overflow-wrap: anywhere; }
  .bar-track { height: 10px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden; }
  .bar-fill { height: 100%; background: linear-gradient(90deg, var(--gold), var(--gold-soft)); }
  .bar-count { font-size: 13px; font-weight: 800; text-align: right; color: var(--muted); }
  .podium { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px; margin-bottom: 20px; }
  .podium-card {
    background: linear-gradient(145deg, rgba(32,32,30,0.95), rgba(23,23,22,0.93));
    border: 1px solid var(--border-soft); border-radius: 18px; padding: 18px; text-align: center;
  }
  .podium-card.rank-1 { border-color: var(--border-gold); box-shadow: 0 0 0 1px color-mix(in srgb, var(--gold) 30%, transparent) inset; }
  .podium-rank { color: var(--muted); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
  .podium-city { font-family: "Playfair Display", Georgia, serif; font-size: 20px; font-weight: 800; margin: 8px 0 4px; overflow-wrap: anywhere; }
  .podium-count { color: var(--muted); font-size: 13px; font-weight: 600; }
  .podium-pct { color: var(--gold-soft); font-size: 12px; font-weight: 800; margin-top: 8px; }
  .list-subhead { color: var(--muted); font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; margin-bottom: 10px; }
  .pagination { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 14px; }
  .page-btn {
    min-height: 34px; min-width: 34px; border-radius: 10px; border: 1px solid var(--border-soft);
    background: rgba(255,255,255,0.04); color: var(--text); font-size: 16px; font-weight: 800; cursor: pointer;
  }
  .page-btn:disabled { opacity: .35; cursor: not-allowed; }
  .page-info { color: var(--muted); font-size: 12px; font-weight: 700; }
  .quote-list { display: grid; gap: 12px; }
  .quote-card { background: rgba(8,8,7,0.34); border: 1px solid var(--border-soft); border-radius: 14px; padding: 14px 16px; }
  .quote-text { font-size: 14px; line-height: 1.5; overflow-wrap: anywhere; }
  .quote-meta { color: var(--muted); font-size: 12px; margin-top: 6px; }
  .empty { color: var(--muted); text-align: center; padding: 40px 20px; }
  @media (max-width: 900px) { .stats { grid-template-columns: 1fr 1fr; } .bar-row { grid-template-columns: 110px 1fr 36px; } }
  @media (max-width: 760px) {
    .topbar-inner, .wrap { padding-left: 16px; padding-right: 16px; }
    .filter-card, .filter-form, .filter-field, .filter-actions { width: 100%; }
    .filter-actions .btn { flex: 1; }
    .stats { grid-template-columns: 1fr 1fr; gap: 10px; }
    .stat-card { padding: 14px; border-radius: 16px; }
    .stat-num { font-size: 24px; }
    .podium { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>"></a>
    <?php render_admin_nav('event-insights'); ?>
  </div>
</header>

<main class="wrap">
  <h1>Insight Peserta</h1>
  <p class="subtitle">Ringkasan kualitatif dari peserta yang konfirmasi hadir: asal kota, sumber informasi, status peserta, feedback, dan tema yang diminati untuk event berikutnya.</p>

  <?php if (!$extraFieldsReady && !$nextTopicReady): ?>
    <div class="section-card">
      <p class="empty">Fitur insight peserta belum aktif di server ini. Jalankan migrasi <code>migrate_v22_alter_event_attendance_extra_fields.sql</code> dan <code>migrate_v23_alter_event_attendance_next_topic.sql</code> terlebih dahulu.</p>
    </div>
  <?php else: ?>

  <section class="section-card filter-card">
    <div>
      <div class="filter-title">Event</div>
      <div class="filter-period"><?= $selectedEventSlug !== '' ? htmlspecialchars($events[array_search($selectedEventSlug, array_column($events, 'slug'), true)]['name'] ?? $selectedEventSlug) : 'Semua Event' ?></div>
    </div>
    <form class="filter-form" method="GET">
      <div class="filter-field">
        <label for="event">Event</label>
        <select id="event" name="event">
          <option value="">Semua Event</option>
          <?php foreach ($events as $ev): ?>
            <option value="<?= htmlspecialchars($ev['slug']) ?>" <?= $selectedEventSlug === $ev['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($ev['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-actions">
        <button class="btn" type="submit">Terapkan</button>
        <?php if ($filterActive): ?>
          <a class="btn secondary" href="event-insights.php">Reset</a>
        <?php endif; ?>
        <a class="btn secondary" href="event-insights-export.php<?= htmlspecialchars($exportQuery) ?>">Export CSV</a>
      </div>
    </form>
  </section>

  <?php if ($totalRecords === 0): ?>
    <div class="section-card">
      <p class="empty">Belum ada data konfirmasi kehadiran untuk periode/event ini.</p>
    </div>
  <?php else: ?>

  <section class="stats">
    <article class="stat-card">
      <div class="stat-label">Total Konfirmasi Hadir</div>
      <div class="stat-num"><?= (int)$totalRecords ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Kota Unik Tercatat</div>
      <div class="stat-num"><?= count($kotaCounts) ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Kota Teratas</div>
      <div class="stat-num" style="font-size:20px;"><?= $kotaTop3 ? htmlspecialchars($kotaDisplay[array_key_first($kotaTop3)]) : '-' ?></div>
    </article>
  </section>

  <section class="section-card">
    <h2>Sebaran Kota Domisili</h2>
    <p class="desc">Dipakai untuk menentukan kota target event offline berikutnya — semakin sering muncul, semakin besar potensi audiens di kota tersebut.</p>
    <?php if (empty($kotaRanked)): ?>
      <p class="empty">Belum ada data kota.</p>
    <?php else: ?>

      <div class="podium">
        <?php $rank = 0; foreach ($kotaTop3 as $key => $count): $rank++; ?>
          <div class="podium-card rank-<?= $rank ?>">
            <div class="podium-rank">Peringkat #<?= $rank ?></div>
            <div class="podium-city"><?= htmlspecialchars($kotaDisplay[$key]) ?></div>
            <div class="podium-count"><?= (int)$count ?> peserta</div>
            <div class="podium-pct"><?= $kotaTotalWithCity > 0 ? round($count / $kotaTotalWithCity * 100) : 0 ?>% dari total kota tercatat</div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="list-subhead">Peringkat Lengkap Kota</div>
      <div class="bar-list" id="kotaBarList">
        <?php foreach ($kotaRanked as $key => $count): ?>
          <div class="bar-row">
            <div class="bar-label"><?= htmlspecialchars($kotaDisplay[$key]) ?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $kotaMaxCount > 0 ? round($count / $kotaMaxCount * 100) : 0 ?>%"></div></div>
            <div class="bar-count"><?= (int)$count ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="pagination" id="kotaPagination" hidden>
        <button type="button" class="page-btn" id="kotaPrevBtn" aria-label="Halaman sebelumnya">&lsaquo;</button>
        <span class="page-info" id="kotaPageInfo"></span>
        <button type="button" class="page-btn" id="kotaNextBtn" aria-label="Halaman berikutnya">&rsaquo;</button>
      </div>

    <?php endif; ?>
  </section>

  <?php if ($extraFieldsReady): ?>
  <section class="section-card">
    <h2>Sumber Informasi &amp; Status Peserta</h2>
    <p class="desc">Untuk mengetahui channel promosi paling efektif dan komposisi jenis peserta.</p>
    <div class="bar-list">
      <?php foreach ($infoSourceCounts as $key => $count): ?>
        <div class="bar-row">
          <div class="bar-label"><?= htmlspecialchars($infoSourceLabel[$key] ?? $key) ?></div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $infoSourceMax > 0 ? round($count / $infoSourceMax * 100) : 0 ?>%"></div></div>
          <div class="bar-count"><?= (int)$count ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="bar-list" style="margin-top:14px;">
      <?php foreach ($participantStatusCounts as $key => $count): ?>
        <div class="bar-row">
          <div class="bar-label"><?= htmlspecialchars($participantStatusLabel[$key] ?? $key) ?></div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $participantStatusMax > 0 ? round($count / $participantStatusMax * 100) : 0 ?>%"></div></div>
          <div class="bar-count"><?= (int)$count ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($nextTopicReady): ?>
  <section class="section-card">
    <h2>Tema yang Diminati untuk Event Berikutnya</h2>
    <p class="desc">Bahan utama untuk merancang topik/konten event berikutnya — langsung dari kata-kata peserta.</p>
    <?php if (empty($topicList)): ?>
      <p class="empty">Belum ada masukan tema dari peserta.</p>
    <?php else: ?>
      <div class="quote-list">
        <?php foreach ($topicList as $t): ?>
          <div class="quote-card">
            <div class="quote-text">&ldquo;<?= nl2br(htmlspecialchars($t['next_topic_interest'])) ?>&rdquo;</div>
            <div class="quote-meta"><?= htmlspecialchars($t['name']) ?><?= $t['kota'] ? ' · ' . htmlspecialchars($t['kota']) : '' ?> · <?= htmlspecialchars($t['event_name']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($extraFieldsReady): ?>
  <section class="section-card">
    <h2>Feedback Event</h2>
    <p class="desc">Kesan dan masukan peserta terhadap penyelenggaraan event ini.</p>
    <?php if (empty($feedbackList)): ?>
      <p class="empty">Belum ada feedback dari peserta.</p>
    <?php else: ?>
      <div class="quote-list">
        <?php foreach ($feedbackList as $f): ?>
          <div class="quote-card">
            <div class="quote-text">&ldquo;<?= nl2br(htmlspecialchars($f['feedback_notes'])) ?>&rdquo;</div>
            <div class="quote-meta"><?= htmlspecialchars($f['name']) ?><?= $f['kota'] ? ' · ' . htmlspecialchars($f['kota']) : '' ?> · <?= htmlspecialchars($f['event_name']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php endif; ?>
  <?php endif; ?>
</main>

<script>
(function () {
  function paginateList(containerId, paginationId, prevId, nextId, infoId, pageSize) {
    var container = document.getElementById(containerId);
    var pagination = document.getElementById(paginationId);
    if (!container || !pagination) return;

    var items = Array.prototype.slice.call(container.children);
    if (items.length <= pageSize) return;

    var prevBtn = document.getElementById(prevId);
    var nextBtn = document.getElementById(nextId);
    var info = document.getElementById(infoId);
    var totalPages = Math.ceil(items.length / pageSize);
    var currentPage = 1;

    function render() {
      items.forEach(function (el, idx) {
        el.hidden = Math.floor(idx / pageSize) + 1 !== currentPage;
      });
      info.textContent = 'Halaman ' + currentPage + ' dari ' + totalPages;
      prevBtn.disabled = currentPage === 1;
      nextBtn.disabled = currentPage === totalPages;
    }

    prevBtn.addEventListener('click', function () {
      if (currentPage > 1) { currentPage--; render(); }
    });
    nextBtn.addEventListener('click', function () {
      if (currentPage < totalPages) { currentPage++; render(); }
    });

    pagination.hidden = false;
    render();
  }

  paginateList('kotaBarList', 'kotaPagination', 'kotaPrevBtn', 'kotaNextBtn', 'kotaPageInfo', 5);
})();
</script>
</body>
</html>
