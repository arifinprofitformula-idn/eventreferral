<?php
/**
 * includes/referrer_auth.php
 * Auth untuk dashboard pengundang (/referrer/). Terpisah dari sesi admin —
 * identitas pengundang adalah nomor WhatsApp yang dipakainya saat membuat
 * link referral (bisa mencakup lebih dari satu event untuk brand yang sama).
 */

/**
 * Panggil di awal setiap halaman /referrer/ (setelah get_current_brand()).
 * Memastikan sesi pengundang yang login SAH untuk brand yang sedang diakses.
 */
function require_referrer_login(?array $brand): array {
    $brand = require_brand_or_404($brand);

    $sessionBrandId = $_SESSION['referrer_brand_id'] ?? null;
    $sessionWhatsapp = $_SESSION['referrer_whatsapp'] ?? null;

    if (!$sessionWhatsapp || (int)$sessionBrandId !== (int)$brand['id']) {
        header('Location: /referrer/login.php');
        exit;
    }

    return $brand;
}

/**
 * Ambil semua baris referrers (lintas event) milik nomor WhatsApp yang
 * sedang login untuk brand ini. Satu pengundang bisa punya beberapa
 * ref_code kalau ia membuat link untuk beberapa event.
 */
function get_referrer_rows_for_session(PDO $pdo, array $brand): array {
    $whatsapp = $_SESSION['referrer_whatsapp'] ?? '';
    if ($whatsapp === '') {
        return [];
    }

    $stmt = $pdo->prepare('
        SELECT r.id, r.ref_code, r.event_slug, r.name, r.whatsapp, e.name AS event_name
        FROM referrers r
        LEFT JOIN events e ON e.slug = r.event_slug AND e.brand_id = r.brand_id
        WHERE r.brand_id = ? AND r.whatsapp = ?
        ORDER BY r.created_at ASC
    ');
    $stmt->execute([(int)$brand['id'], $whatsapp]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
