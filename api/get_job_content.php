<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../db.php';

$url = $_GET['url'] ?? '';
$id  = (int)($_GET['id'] ?? 0);

if (!$url || !str_starts_with($url, 'https://www.xplace.com/')) {
    echo json_encode(['ok' => false, 'error' => 'Invalid URL']);
    exit;
}

$db = get_db();

// Return cached description if exists
if ($id) {
    $stmt = $db->prepare('SELECT project_description FROM proposals WHERE id=?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if ($r && !empty($r['project_description'])) {
        echo json_encode(['ok' => true, 'description' => $r['project_description'], 'cached' => true]);
        exit;
    }
}

// Fetch via curl (server-side)
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER     => ['Accept-Language: he-IL,he;q=0.9'],
]);
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    echo json_encode(['ok' => false, 'error' => 'fetch_failed']);
    exit;
}

// Extract og:description (available in static HTML, no JS needed)
$desc = '';
if (preg_match('/<meta[^>]+property="og:description"[^>]+content="([^"]+)"/i', $html, $m)) {
    $desc = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
}

// Fallback: meta name="description"
if (empty($desc) && preg_match('/<meta[^>]+name="description"[^>]+content="([^"]+)"/i', $html, $m)) {
    $desc = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
}

// Cache in DB
if ($id && $desc) {
    $stmt = $db->prepare('UPDATE proposals SET project_description=? WHERE id=?');
    $stmt->execute([$desc, $id]);
}

echo json_encode(['ok' => true, 'description' => $desc]);
