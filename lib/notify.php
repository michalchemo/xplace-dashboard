<?php
// Nintay Brain notify helper for the XPlace dashboard (BRISK-109 follow-up).
// One central email endpoint - no SMTP anywhere in this codebase.
// Requires NOTIFY_TOKEN defined in config.php. Fire-and-forget with a short
// timeout: a notify failure must never break a dashboard flow. Failures are
// error_log'ed so they show in the Apache log rather than vanishing.

function xplace_notify(string $subject, string $text, ?string $to = null): bool
{
    if (!defined('NOTIFY_TOKEN') || NOTIFY_TOKEN === '') {
        error_log('xplace_notify skipped: NOTIFY_TOKEN not configured');
        return false;
    }
    $to = $to ?: (defined('ALERT_EMAIL') ? ALERT_EMAIL : 'michal@nintay.com');
    $payload = json_encode([
        'channel' => 'email',
        'to'      => $to,
        'subject' => $subject,
        'text'    => $text,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init('http://127.0.0.1:8001/notify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . NOTIFY_TOKEN,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        error_log("xplace_notify failed: HTTP $code " . substr((string)$body, 0, 200));
        return false;
    }
    return true;
}