<?php
// GET endpoint — active learnings the agent reads each run to calibrate pricing and filtering.
// Auth: Bearer token matching API_KEY in config.php.

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// Agent consumes only confirmed learnings (active=1 AND confirmed=1).
// Unconfirmed agent drafts stay out of pricing/classification until Michal approves them.
$db   = get_db();
$rows = $db->query(
    "SELECT project_id, project_title, project_url, outcome, lesson, price, price_type, updated_at
     FROM learnings
     WHERE active = 1 AND confirmed = 1
     ORDER BY updated_at DESC
     LIMIT 100"
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'count' => count($rows), 'learnings' => $rows]);
