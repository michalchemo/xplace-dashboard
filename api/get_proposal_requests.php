<?php
// Returns projects where Michal pressed "בקשי הצעה" / "בקש תוכן חדש".
// The agent drafts (or rewrites) a proposal on the next run.
// `notes` carries Michal's guidance for the rewrite; `proposal_text` is the
// existing draft (empty = brand new request, non-empty = regeneration).
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
    "SELECT project_id, project_title, project_url, agent_notes, notes, proposal_text
     FROM proposals
     WHERE proposal_requ