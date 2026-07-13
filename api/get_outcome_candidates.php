<?php
// GET endpoint — submitted proposals that do NOT yet have a confirmed learning.
// The agent revisits these project pages to detect a closed/awarded/expired outcome
// and posts a DRAFT learning (confirmed=0) for Michal to review.
// Auth: Bearer token matching API_KEY in config.php.

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$db   = get_db();
// A candidate is a submitted proposal with no learning row at all, OR one whose
// only learning row is still an unconfirmed draft. Confirmed learnings are done.
$rows = $db->query(
    "SELECT p.project_id, p.project_title, p.project_url, p.price, p.updated_at
     FROM proposals p
     LEFT JOIN learnings l ON l.project_id = p.project_id
     WHERE p.status = 'submitted'
       AND (l.id IS NULL OR l.confirmed = 0)
     GROUP BY p.project_id
     ORDER BY p.updated_at ASC
     LIMIT 40"
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'count' => count($rows), 'candidates' => $rows]);
