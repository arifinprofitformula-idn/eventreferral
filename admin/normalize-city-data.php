<?php
/**
 * admin/normalize-city-data.php
 * Alat untuk merapikan data HISTORIS leads.kota yang sudah terlanjur bervariasi
 * (Jakarta / JKT / Jkrt / dst) sebelum datalist + normalize_city_name() (includes/city_list.php)
 * diterapkan di semua form pendaftaran & kehadiran. Form-form baru sudah otomatis rapi —
 * halaman ini HANYA untuk membersihkan data yang sudah kadung masuk sebelumnya.
 *
 * Alur: preview dulu (nilai asli -> hasil normalisasi & jumlah baris terdampak), admin
 * klik "Terapkan" untuk benar-benar menjalankan UPDATE. Aman dijalankan berulang kali
 * (idempotent) — setelah diterapkan, preview otomatis mengecil/kosong.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_nav.php';
start_secure_session();

$brand = require_admin_for_brand(get_current_brand());
$brandId = (int)$brand['id'];
$pdo = get_db();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$applied = false;
$appliedRows = 0;
$appliedGroups = 0;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid. Silakan muat ulang halaman lalu coba lagi.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT DISTINCT kota FROM leads WHERE brand_id = ? AND kota IS NOT NULL AND TRIM(kota) <> ""');
            $stmt->execute([$brandId]);
            $distinctKota = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $pdo->beginTransaction();
            $updateStmt = $pdo->prepare('UPDATE leads SET kota = ? WHERE brand_id = ? AND kota = ?');
            foreach ($distinctKota as $original) {
                $normalized = normalize_city_name($original);
                if ($normalized === '' || $normalized === $original) {
                    continue;
                }
                $updateStmt->execute([$normalized, $brandId, $original]);
                $rowCount = $updateStmt->rowCount();
                if ($rowCount > 0) {
                    $appliedRows += $rowCount;
                    $appliedGroups++;
                }
            }
            $pdo->commit();
            $applied = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Gagal menerapkan normalisasi: ' . $e->getMessage();
        }
    }
}

// Preview selalu dihitung ulang dari state TERKINI database (bukan cache), jadi setelah
// "Terapkan" halaman reload dan preview otomatis mencerminkan sisa yang belum rapi (idealnya kosong).
$previewRows = [];
$stmt = $pdo->prepare('
    SELECT kota, COUNT(*) AS total
    FROM leads
    WHERE brand_id = ? AND kota IS NOT NULL AND TRIM(kota) <> ""
    GROUP BY kota
    ORDER BY total DESC
');
$stmt->execute([$brandId]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $normalized = normalize_city_name($row['kota']);
    if ($normalized !== $row['kota']) {
        $previewRows[] = ['original' => $row['kota'], 'normalized' => $normalized, 'total' => (int)$row['total']];
    }
}
$previewTotalRows = array_sum(array_column($previewRows, 'total'));
$logoPath = $brand['logo_path'] ? '..' . $brand['logo_path'] : '../assets/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rapikan Data Kota — <?= htmlspecialchars($brand['name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
<style>
  <?= get_theme_css_vars($brand) ?>
  :root {
    --bg:#0B0B0A; --bg-soft:#10100F;
    --border-gold:color-mix(in srgb, var(--gold) 18%, transparent);
    --border-soft:rgba(255,255,255,0.09);
    --gold:var(--brand-primary); --gold-soft:var(--brand-soft);
    --text:#F7F3E8; --muted:#A8A29A; --success:#22C55E; --warning:#F59E0B; --danger:#EF4444;
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
  .wrap { width: min(100%, 1000px); margin: 0 auto; padding: 26px 24px 60px; }
  h1 { font-family: "Playfair Display", Georgia, serif; font-size: clamp(24px, 4vw, 32px); margin-bottom: 6px; }
  .subtitle { color: var(--muted); font-size: 14px; margin-bottom: 22px; }
  .section-card {
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    border: 1px solid var(--border-gold); border-radius: 22px; padding: 22px; margin-bottom: 18px;
  }
  .banner { border-radius: 14px; padding: 14px 16px; font-size: 13.5px; font-weight: 700; margin-bottom: 18px; }
  .banner.success { color: #BBF7D0; background: rgba(34,197,94,0.14); border: 1px solid rgba(34,197,94,0.3); }
  .banner.error { color: #FCA5A5; background: rgba(239,68,68,0.14); border: 1px solid rgba(239,68,68,0.3); }
  h2 { font-size: 17px; font-weight: 900; margin-bottom: 4px; }
  .desc { color: var(--muted); font-size: 13px; margin-bottom: 18px; }
  .table-scroll { overflow-x: auto; border: 1px solid var(--border-soft); border-radius: 16px; }
  table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; min-width: 520px; }
  th { background: rgba(32,32,30,0.98); text-align: left; padding: 12px 16px; font-weight: 800; white-space: nowrap; }
  td { padding: 12px 16px; border-top: 1px solid rgba(255,255,255,0.06); }
  tbody tr:nth-child(even) td { background: rgba(255,255,255,0.02); }
  .arrow { color: var(--muted); padding: 0 6px; }
  .pill { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; border-radius: 999px; font-weight: 800; padding: 4px 10px; font-size: 12.5px; }
  .pill.gold { color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 12%, transparent); border: 1px solid color-mix(in srgb, var(--gold) 22%, transparent); }
  .empty { color: var(--muted); text-align: center; padding: 40px 20px; }
  .btn {
    min-height: 44px; border-radius: 12px; border: 1px solid var(--border-gold); padding: 0 18px;
    color: var(--gold-soft); background: color-mix(in srgb, var(--gold) 12%, transparent); font-size: 13.5px; font-weight: 900;
    text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; margin-top: 16px;
  }
  @media (max-width: 760px) {
    .topbar-inner, .wrap { padding-left: 16px; padding-right: 16px; }
  }
</style>
</head>
<body>
<header class="topbar">
  <div class="topbar-inner">
    <a class="brand" href="dashboard.php"><img src="<?= htmlspecialchars($logoPath) ?>" alt="<?= htmlspecialchars($brand['name']) ?>"></a>
    <?php render_admin_nav('normalize-city-data'); ?>
  </div>
</header>

<main class="wrap">
  <h1>Rapikan Data Kota</h1>
  <p class="subtitle">Menyeragamkan penulisan kota domisili di data pendaftar lama (mis. "JKT" / "Jkrt" → "Jakarta"), supaya Insight Peserta menghitung kota yang sama sebagai satu entri. Form pendaftaran &amp; kehadiran yang baru sudah otomatis rapi lewat datalist — alat ini hanya untuk data yang sudah terlanjur masuk sebelumnya.</p>

  <?php if ($applied): ?>
    <div class="banner success">
      <?php if ($appliedGroups > 0): ?>
        Berhasil merapikan <?= (int)$appliedRows ?> baris data (<?= (int)$appliedGroups ?> variasi penulisan) ke ejaan baku.
      <?php else: ?>
        Tidak ada data yang perlu dirapikan — semua penulisan kota sudah baku.
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="banner error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <section class="section-card">
    <h2>Pratinjau Perubahan</h2>
    <p class="desc">Daftar penulisan kota yang berbeda dari ejaan baku, beserta jumlah baris pendaftar yang akan ikut berubah.</p>

    <?php if (empty($previewRows)): ?>
      <p class="empty">Tidak ada yang perlu dirapikan — semua data kota sudah dalam ejaan baku.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr><th>Ditulis Saat Ini</th><th></th><th>Menjadi</th><th>Jumlah Baris</th></tr>
          </thead>
          <tbody>
            <?php foreach ($previewRows as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['original']) ?></td>
                <td class="arrow">&rarr;</td>
                <td><strong><?= htmlspecialchars($r['normalized']) ?></strong></td>
                <td><span class="pill gold"><?= (int)$r['total'] ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <form method="POST" onsubmit="return confirm('Terapkan normalisasi ke <?= (int)$previewTotalRows ?> baris data kota? Tindakan ini mengubah data di database secara langsung.');">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <button type="submit" class="btn">Terapkan Perubahan (<?= (int)$previewTotalRows ?> baris)</button>
      </form>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
