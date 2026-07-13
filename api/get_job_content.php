<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../db.php';

$url = $_GET['url'] ?? '';
$id  = (int)($_GET['id'] ?? 0);

if (!$url || !str_starts_with($url, 'https://www.xplace.com/')) {
    echo json_encode(['ok' => false, 'error' => 'Invalid URL']);
    exit;
}

// XPlace redesign (07/2026): meta description is now generic marketing boilerplate.
// Detect it so we never show/cache it as a job description.
function is_generic_desc(string $s): bool {
    if ($s === '') return true;
    return str_contains($s, 'פרסמו פרויקט ב')
        || str_contains($s, 'עמלות תיווך')
        || str_contains($s, 'להשוואת הצעות');
}

$db = get_db();

// Return cached description if it exists AND is not the generic boilerplate
// (self-heals rows cached before the site redesign).
if ($id) {
    $stmt = $db->prepare('SELECT project_description FROM proposals WHERE id=?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if ($r && !empty($r['project_description']) && !is_generic_desc($r['project_description'])) {
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

$desc = '';

// 1) Primary (site redesign 07/2026): real description lives in
//    <p class="project_description__XXXX"> in the server-rendered HTML.
if (preg_match('/<p[^>]*class="[^"]*project_description[^"]*"[^>]*>([\s\S]*?)<\/p>/iu', $html, $m)) {
    $raw  = preg_replace('/<br\s*\/?>/i', "\n", $m[1]);      // keep line breaks
    $raw  = strip_tags($raw);
    $desc = trim(html_entity_decode($raw, ENT_QUOTES, 'UTF-8'));
}

// 2) Fallback: og:description (old layout)
if (empty($desc) && preg_match('/<meta[^>]+property="og:description"[^>]+content="([^"]+)"/i', $html, $m)) {
    $desc = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
}

// 3) Fallback: meta name="description"
if (empty($desc) && preg_match('/<meta[^>]+name="description"[^>]+content="([^"]+)"/i', $html, $m)) {
    $desc = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
}

// Never return/cache the generic boilerplate
if (is_generic_desc($desc)) {
    $desc = '';
}

// Cache in DB (also overwrites previously cached generic text)
if ($id && $desc) {
    $stmt = $db->prepare('UPDATE proposals SET project_description=? WHERE id=?');
    $stmt->execute([$desc, $id]);
}

echo json_encode(['ok' => true, 'description' => $desc]);
