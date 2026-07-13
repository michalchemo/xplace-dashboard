<?php
// POST endpoint — record/update the real-world outcome of a lead so the agent learns from it.
// Upsert by project_id. Auth: Bearer token matching API_KEY in config.php.

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

// --- Auth ---
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// --- Input ---
$body = json_decode(file_get_contents('php://input'), true);

$outcome = trim($body['outcome'] ?? '');
$lesson  = trim($body['lesson'] ?? '');
if ($outcome === '' || $lesson === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing: outcome and lesson are required']);
    exit;
}

// Allowed outcomes — keep the vocabulary tight so the agent can reason over it.
$allowed = ['rejected_price', 'advanced', 'won', 'in_progress', 'bad_fit', 'lost', 'other'];
if (!in_array($outcome, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid outcome. Allowed: ' . implode(', ', $allowed)]);
    exit;
}

$project_id    = trim($body['project_id'] ?? '') ?: null;
$project_title = trim($body['project_title'] ?? '') ?: null;
$project_url   = trim($body['project_url'] ?? '') ?: null;
$price         = isset($body['price']) && $body['price'] !== '' ? (int)$body['price'] : null;
$price_type    = trim($body['price_type'] ?? '') ?: null;
$active        = isset($body['active']) ? (int)(bool)$body['active'] : 1;
// confirmed defaults to 1 (manual/API writes are trusted). The agent passes confirmed=0
// when it auto-detects an outcome, so it lands as a draft for Michal to review.
$confirmed     = isset($body['confirmed']) ? (int)(bool)$body['confirmed'] : 1;

// If title/url missing but we know the project_id, backfill from proposals.
$db = get_db();
if ($project_id && (!$project_title || !$project_url)) {
    $p = $db->prepare('SELECT project_title, project_url, price, price_type FROM proposals WHERE project_id = ? LIMIT 1');
    $p->execute([$project_id]);
    if ($row = $p->fetch(PDO::FETCH_ASSOC)) {
        $project_title = $project_title ?: $row['project_title'];
        $project_url   = $project_url   ?: $row['project_url'];
        $price         = $price         ?? (int)$row['price'];
        $price_type    = $price_type    ?: $row['price_type'];
    }
}

// --- Upsert by project_id ---
$stmt = $db->prepare('
    INSERT INTO learnings (project_id, project_title, project_url, outcome, lesson, price, price_type, active, confirmed)
    VALUES (:pid, :title, :url, :outcome, :lesson, :price, :ptype, :active, :confirmed)
    ON DUPLICATE KEY UPDATE
        project_title = VALUES(project_title),
        project_url   = VALUES(project_url),
        outcome       = VALUES(outcome),
        lesson        = VALUES(lesson),
        price         = VALUES(price),
        price_type    = VALUES(price_type),
        active        = VALUES(active),
        confirmed     = VALUES(confirmed),
        updated_at    = NOW()
');
$stmt->execute([
    ':pid'       => $project_id,
    ':title'     => $project_title,
    ':url'       => $project_url,
    ':outcome'   => $outcome,
    ':lesson'    => $lesson,
    ':price'     => $price,
    ':ptype'     => $price_type,
    ':active'    => $active,
    ':confirmed' => $confirmed,
]);

echo json_encode(['ok' => true, 'affected' => $stmt->rowCount()]);
