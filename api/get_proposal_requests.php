<?php
// Returns projects where Michal pressed "בקשי הצעה" — agent should draft a proposal on next run.
require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$db   = get_db();
$rows = $db->query(
    "SELECT project_id, project_title, project_url, agent_notes
     FROM proposals
     WHERE proposal_requested = 1
       AND (proposal_text = '' OR proposal_text IS NULL)
       AND status = 'pending'
     ORDER BY updated_at DESC"
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'projects' => $rows]);
