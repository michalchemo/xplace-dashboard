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
    $row = $db->prepare('SELECT project_description FROM proposals WHERE id=?');
    $row->execute([$id]);
    $r = $row->fetch();
    if ($r && !empty($r['project_description'])) {
        echo json_encode(['ok' => true, 'description' => $r['project_description'], 'cached' => true]);
        exit;
    }
}

// Fetch from XPlace server-side
$ctx = stream_context_create(['http' => [
    'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36\r\nAccept-Language: he-IL,he;q=0.9\r\n",
    'timeout' => 12,
    'follow_location' => 1,
]]);

$html = @file_get_contents($url, false, $ctx);
if (!$html) {
    echo json_encode(['ok' => false, 'error' => 'fetch_failed']);
    exit;
}

// Try multiple selectors for job description
$desc = '';
$patterns = [
    '/<div[^>]+class="[^"]*job[_-]description[^"]*"[^>]*>(.*?)<\/div>/si',
    '/<div[^>]+class="[^"]*description[^"]*"[^>]*>(.*?)<\/div>/si',
    '/<div[^>]+class="[^"]*content[^"]*"[^>]*>(.*?)<\/div>/si',
    '/<section[^>]+class="[^"]*description[^"]*"[^>]*>(.*?)<\/section>/si',
];
foreach ($patterns as $p) {
    if (preg_match($p, $html, $m)) {
        $desc = strip_tags($m[1]);
        $desc = html_entity_decode(trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\n{3,}/', "\n\n", $desc))), ENT_QUOTES, 'UTF-8');
        if (mb_strlen($desc) > 50) break;
        $desc = '';
    }
}

// Cache in DB for next time
if ($id && $desc) {
    $stmt = $db->prepare('UPDATE proposals SET project_description=? WHERE id=?');
    $stmt->execute([$desc, $id]);
}

echo json_encode(['ok' => true, 'description' => $desc]);
