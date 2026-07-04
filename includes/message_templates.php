<?php
/**
 * includes/message_templates.php
 * Template balasan WhatsApp siap-kirim untuk pengundang mengirim ke peserta
 * yang mendaftar lewat link referral mereka.
 */

function build_participant_reply_templates(array $event, string $link, string $invitationLink = ''): array
{
    $eventName = trim((string)($event['name'] ?? ''));
    $eventDay = trim((string)($event['event_day'] ?? ''));
    $eventTime = trim((string)($event['event_time'] ?? ''));
    $eventLocation = trim((string)($event['event_location'] ?? ''));
    $eventSpeaker = trim((string)($event['event_speaker'] ?? ''));
    $invitationLink = trim($invitationLink);
    $zoomLineValue = $invitationLink !== '' ? $invitationLink : 'Belum ada link zoom, Tanya admin';

    $detailLines = [];
    if ($eventDay !== '') {
        $detailLines[] = "📅 Hari/Tanggal: {$eventDay}";
    }
    if ($eventTime !== '') {
        $detailLines[] = "🕐 Waktu: {$eventTime}";
    }
    if ($eventLocation !== '') {
        $detailLines[] = "📍 Lokasi: {$eventLocation}";
    }
    if ($eventSpeaker !== '') {
        $detailLines[] = "🎤 Pembicara: {$eventSpeaker}";
    }
    $detailLines[] = "🔗 Link Zoom Anda : {$zoomLineValue}";
    $details = implode("\n", $detailLines);

    $shortDetail = trim(($eventDay !== '' ? $eventDay : '') . ($eventTime !== '' ? ', jam ' . $eventTime : ''));

    $formal = "Halo [Nama Peserta]! 🙏\n\n"
        . "Terima kasih sudah mendaftar untuk acara *{$eventName}*.\n\n"
        . ($details !== '' ? $details . "\n\n" : '')
        . "Simpan link berikut untuk info lengkap acara:\n{$link}\n\n"
        . "Sampai jumpa di acara ya!";

    $santai = "Halo [Nama Peserta]! Makasih udah daftar ya 😊\n\n"
        . "Acara *{$eventName}*"
        . ($shortDetail !== '' ? " bakal berlangsung {$shortDetail}" : '')
        . ($eventLocation !== '' ? " di {$eventLocation}" : '') . ".\n\n"
        . "Link Zoom Anda : {$zoomLineValue}\n\n"
        . "Jangan lupa datang ya! Info lengkap: {$link}";

    $reminder = "Halo [Nama Peserta], reminder nih! ⏰\n\n"
        . "Jangan lupa besok kita ketemu di acara *{$eventName}*"
        . ($eventTime !== '' ? " jam {$eventTime}" : '')
        . ($eventLocation !== '' ? " di {$eventLocation}" : '') . ".\n\n"
        . "Link Zoom Anda : {$zoomLineValue}\n\n"
        . "Info & lokasi: {$link}";

    return [
        ['key' => 'formal', 'label' => 'Formal', 'text' => $formal],
        ['key' => 'santai', 'label' => 'Santai', 'text' => $santai],
        ['key' => 'reminder', 'label' => 'Reminder H-1', 'text' => $reminder],
    ];
}
