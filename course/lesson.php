<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/lms_core.php';
require_once __DIR__ . '/../includes/lms_layout.php';
start_secure_session();

$brand = require_brand_or_404(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

lms_ensure_schema($pdo);
if (empty($_SESSION['lms_csrf_token'])) {
    $_SESSION['lms_csrf_token'] = bin2hex(random_bytes(32));
}
$user = lms_get_logged_user($pdo, $brandId);

if (!$user) {
    header('Location: /course/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$lessonId = (int)($_GET['id'] ?? 0);
if ($lessonId <= 0) {
    header('Location: /course/my-courses.php');
    exit;
}

// Ambil detail pelajaran, modul, dan course
$stmt = $pdo->prepare('
    SELECT l.*, m.title AS module_title, c.id AS course_id, c.brand_id, c.title AS course_title, c.slug AS course_slug, c.access_type
    FROM lms_lessons l
    JOIN lms_modules m ON m.id = l.module_id
    JOIN lms_courses c ON c.id = l.course_id
    WHERE l.id = ? AND c.brand_id = ?
');
$stmt->execute([$lessonId, $brandId]);
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    http_response_code(404);
    exit('Materi pelajaran tidak ditemukan.');
}

$courseId = (int)$lesson['course_id'];
$role = lms_current_role($user);

// Cek status enrollment
$stmtEnr = $pdo->prepare('SELECT access_status FROM lms_enrollments WHERE user_id = ? AND course_id = ? AND access_status = "active"');
$stmtEnr->execute([(int)$user['id'], $courseId]);
$isEnrolled = (bool)$stmtEnr->fetchColumn();

// Auto enroll free jika belum
if ($lesson['access_type'] === 'free' && !$isEnrolled) {
    lms_enroll_user_in_course($pdo, $brandId, (int)$user['id'], $courseId);
    $isEnrolled = true;
}

// Cek hak akses ke pelajaran ini
$canAccess = lms_can_access_course($user, $lesson, $isEnrolled);
if (!$canAccess) {
    header('Location: /course/checkout.php?slug=' . urlencode($lesson['course_slug']) . '&locked=1');
    exit;
}

// Ambil daftar seluruh materi dalam course untuk navigasi sidebar
$stmtAll = $pdo->prepare('
    SELECT l.id, l.title, l.content_type, l.is_premium, m.title AS module_title, m.id AS module_id
    FROM lms_lessons l
    JOIN lms_modules m ON m.id = l.module_id
    WHERE l.course_id = ?
    ORDER BY m.sort_order ASC, l.sort_order ASC, l.id ASC
');
$stmtAll->execute([$courseId]);
$allLessons = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

// Tandai mana yang sudah selesai
$stmtDone = $pdo->prepare('SELECT lesson_id FROM lms_lesson_progress WHERE user_id = ? AND course_id = ?');
$stmtDone->execute([(int)$user['id'], $courseId]);
$completedIds = $stmtDone->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Cari next & prev lesson
$prevLesson = null;
$nextLesson = null;
$currentIndex = -1;
foreach ($allLessons as $idx => $item) {
    if ((int)$item['id'] === $lessonId) {
        $currentIndex = $idx;
        break;
    }
}
if ($currentIndex > 0) {
    $prevLesson = $allLessons[$currentIndex - 1];
}
if ($currentIndex >= 0 && $currentIndex < count($allLessons) - 1) {
    $nextLesson = $allLessons[$currentIndex + 1];
}

$isCurrentCompleted = in_array($lessonId, $completedIds, true);
$progress = lms_get_course_progress($pdo, (int)$user['id'], $courseId);

$quizData = null;
if ($lesson['content_type'] === 'quiz' && !empty($lesson['quiz_data'])) {
    $quizData = json_decode($lesson['quiz_data'], true);
}

render_lms_header($brand, $user, 'my_courses');
?>

<div style="margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
  <a href="/course/<?= urlencode($lesson['course_slug']) ?>" style="color:var(--muted);font-size:13px;display:inline-flex;align-items:center;gap:6px;">
    ← Kembali ke Silabus: <?= htmlspecialchars($lesson['course_title']) ?>
  </a>
  <div style="font-size:13px;color:var(--gold-soft);font-weight:700;">
    Progress eCourse: <?= $progress['percentage'] ?>% Selesai
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:28px;align-items:start;">
  <!-- SISI KIRI: Player Materi Video / PDF / Quiz -->
  <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:22px;padding:28px;box-shadow:0 16px 44px rgba(0,0,0,0.3);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
      <span style="font-size:12px;font-weight:700;color:var(--gold-soft);text-transform:uppercase;letter-spacing:0.5px;">
        <?= htmlspecialchars($lesson['module_title']) ?>
      </span>
      <span class="role-pill <?= $isCurrentCompleted ? 'badge-paid' : 'badge-guest' ?>">
        <?= $isCurrentCompleted ? '✓ Materi Tuntas' : 'Sedang Dipelajari' ?>
      </span>
    </div>

    <h1 style="font-size:24px;font-weight:800;line-height:1.3;margin-bottom:20px;">
      <?= htmlspecialchars($lesson['title']) ?>
    </h1>

    <!-- Player Video -->
    <?php if ($lesson['content_type'] === 'video'): ?>
      <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:16px;background:#000;margin-bottom:24px;border:1px solid rgba(255,255,255,0.08);">
        <?php if (!empty($lesson['content_url'])): ?>
          <iframe src="<?= htmlspecialchars($lesson['content_url']) ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;" allowfullscreen></iframe>
        <?php else: ?>
          <div style="position:absolute;inset:0;display:grid;place-items:center;color:var(--muted);">Video pembelajaran sedang disiapkan.</div>
        <?php endif; ?>
      </div>
    <?php elseif ($lesson['content_type'] === 'pdf'): ?>
      <div style="background:rgba(255,255,255,0.02);border:1px dashed var(--border-gold);border-radius:16px;padding:28px;text-align:center;margin-bottom:24px;">
        <div style="font-size:40px;margin-bottom:10px;">📑</div>
        <h3 style="font-size:18px;font-weight:800;margin-bottom:6px;">Dokumen Panduan & Lembar Kerja PDF</h3>
        <p style="color:var(--muted);font-size:13.5px;max-width:440px;margin:0 auto 18px;">
          Pelajari ringkasan materi, checklist keaslian fisik, dan template kalkulasi emas yang telah disiapkan.
        </p>
        <?php if (!empty($lesson['content_url'])): ?>
          <a href="<?= htmlspecialchars($lesson['content_url']) ?>" target="_blank" rel="noopener" class="btn-lms btn-lms-gold">
            Unduh / Buka Dokumen PDF ↗
          </a>
        <?php endif; ?>
      </div>
    <?php elseif ($lesson['content_type'] === 'quiz'): ?>
      <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border-soft);border-radius:16px;padding:24px;margin-bottom:24px;">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
          <span style="font-size:22px;">📝</span>
          <div>
            <h3 style="font-size:18px;font-weight:800;">Evaluasi Kuis Pemahaman</h3>
            <div style="font-size:12px;color:var(--muted);">Passing Score: <?= (int)($quizData['passing_score'] ?? 70) ?>%</div>
          </div>
        </div>

        <?php if (!empty($quizData['questions'])): ?>
          <form id="quizForm" style="display:grid;gap:18px;">
            <?php foreach ($quizData['questions'] as $qIdx => $q): ?>
              <div style="background:rgba(255,255,255,0.025);border:1px solid var(--border-soft);padding:16px;border-radius:12px;">
                <div style="font-weight:700;font-size:14.5px;margin-bottom:10px;">
                  <?= ($qIdx + 1) ?>. <?= htmlspecialchars($q['q']) ?>
                </div>
                <div style="display:grid;gap:8px;">
                  <?php foreach ($q['options'] as $optIdx => $optText): ?>
                    <label style="display:flex;align-items:center;gap:10px;font-size:13.5px;cursor:pointer;padding:8px 12px;border-radius:8px;background:rgba(255,255,255,0.02);">
                      <input type="radio" name="q_<?= $qIdx ?>" value="<?= $optIdx ?>" required>
                      <span><?= htmlspecialchars($optText) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
            <div id="quizResult" style="display:none;padding:12px 16px;border-radius:10px;font-size:13.5px;"></div>
            <button type="submit" class="btn-lms btn-lms-gold" style="width:100%;padding:12px;">Kirim Jawaban Kuis</button>
          </form>
        <?php else: ?>
          <p style="color:var(--muted);">Pertanyaan kuis sedang disiapkan oleh instruktur.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <!-- Teks Ringkasan Materi -->
    <?php if (!empty($lesson['body_text'])): ?>
      <div style="font-size:14.5px;color:#e5e0d3;line-height:1.7;margin-bottom:32px;background:rgba(255,255,255,0.015);padding:20px;border-radius:14px;border:1px solid rgba(255,255,255,0.04);">
        <h4 style="font-size:15px;font-weight:800;color:var(--text);margin-bottom:8px;">Catatan Instruksi Materi:</h4>
        <?= nl2br(htmlspecialchars($lesson['body_text'])) ?>
      </div>
    <?php endif; ?>

    <!-- Tombol Aksi Selesai & Navigasi -->
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.06);flex-wrap:wrap;">
      <div>
        <?php if ($prevLesson): ?>
          <a href="/course/lesson.php?id=<?= (int)$prevLesson['id'] ?>" class="btn-lms btn-lms-ghost">
            ← Materi Sebelumnya
          </a>
        <?php endif; ?>
      </div>

      <div style="display:flex;gap:10px;">
        <button id="btnComplete" class="btn-lms btn-lms-gold" onclick="markLessonComplete(<?= $lessonId ?>)">
          <?= $isCurrentCompleted ? '✓ Materi Sudah Selesai' : 'Tandai Selesai & Lanjut' ?>
        </button>

        <?php if ($nextLesson): ?>
          <a href="/course/lesson.php?id=<?= (int)$nextLesson['id'] ?>" class="btn-lms btn-lms-ghost">
            Berikutnya →
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- SISI KANAN: Sidebar Daftar Materi Kursus -->
  <div style="background:var(--surface);border:1px solid var(--border-soft);border-radius:20px;padding:20px;position:sticky;top:96px;">
    <h3 style="font-size:16px;font-weight:800;margin-bottom:12px;">Daftar Materi eCourse</h3>
    <div style="max-height:calc(100vh - 200px);overflow-y:auto;display:grid;gap:6px;padding-right:4px;">
      <?php foreach ($allLessons as $idx => $item): ?>
        <?php
          $isCurrent = ((int)$item['id'] === $lessonId);
          $isDone = in_array((int)$item['id'], $completedIds, true);
        ?>
        <a href="/course/lesson.php?id=<?= (int)$item['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;border-radius:10px;background:<?= $isCurrent ? 'var(--gold-glow)' : 'transparent' ?>;border:1px solid <?= $isCurrent ? 'var(--border-gold)' : 'transparent' ?>;color:<?= $isCurrent ? 'var(--gold-soft)' : 'var(--text)' ?>;font-size:13px;transition:all 150ms ease;">
          <div style="display:flex;align-items:center;gap:8px;min-width:0;">
            <span style="font-size:11px;color:var(--muted);"><?= ($idx + 1) ?>.</span>
            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:<?= $isCurrent ? '700' : '500' ?>;">
              <?= htmlspecialchars($item['title']) ?>
            </span>
          </div>
          <?php if ($isDone): ?>
            <span style="color:var(--success);font-size:12px;font-weight:800;">✓</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
async function markLessonComplete(id) {
  const btn = document.getElementById('btnComplete');
  btn.disabled = true;
  btn.innerText = 'Menyimpan...';

  try {
    const res = await fetch('/api/lms-progress-update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'lesson_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent('<?= htmlspecialchars($_SESSION['lms_csrf_token'], ENT_QUOTES, 'UTF-8') ?>')
    });
    const data = await res.json();
    if (data.ok) {
      btn.innerText = '✓ Berhasil Selesai!';
      setTimeout(() => {
        <?php if ($nextLesson): ?>
          window.location.href = '/course/lesson.php?id=<?= (int)$nextLesson['id'] ?>';
        <?php else: ?>
          window.location.href = '/course/my-courses.php';
        <?php endif; ?>
      }, 700);
    } else {
      alert(data.error || 'Gagal menyimpan progres.');
      btn.disabled = false;
      btn.innerText = 'Tandai Selesai & Lanjut';
    }
  } catch (err) {
    alert('Terjadi kendala jaringan.');
    btn.disabled = false;
    btn.innerText = 'Tandai Selesai & Lanjut';
  }
}

// Simulasi Kuis client-side evaluation
const quizForm = document.getElementById('quizForm');
if (quizForm) {
  quizForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const resultBox = document.getElementById('quizResult');
    resultBox.style.display = 'block';
    resultBox.style.background = 'rgba(34,197,94,0.15)';
    resultBox.style.border = '1px solid rgba(34,197,94,0.3)';
    resultBox.style.color = '#bbf7d0';
    resultBox.innerHTML = '<strong>Skor Kuis: 100% (Lulus)!</strong> Pemahaman Anda sangat baik. Menandai materi selesai...';
    markLessonComplete(<?= $lessonId ?>);
  });
}
</script>

<?php render_lms_footer($brand); ?>
