<?php
// POST endpoint — called by the Claude scheduled task AFTER it removes a
// recommendation from XPlace. Marks the withdrawal as completed so the project
// stops appearing in get_withdrawals.php.
//
// NOTE: the row is intentionally NOT deleted. add_proposal.php uses
// INSERT IGNORE keyed on project_id, so keeping the row (status = 'to_withdraw')
// prevents the rejected project from being re-added on the next scan.
//
// Auth: Bearer token matching API_KEY in config.php
// Body: { "project_id": "XXXXX" }

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
$body       = json_decode(file_get_contents('php://input'), true);
$project_id = trim($body['project_id'] ?? '');

if (!$project_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing project_id']);
    exit;
}

// --- Mark withdrawal as completed ---
$db   = get_db();
$stmt = $db->prepare('UPDATE proposals SET withdrawal_done = 1, updated_at = NOW() WHERE project_id = ?');
$stmt->execute([$project_id]);

echo json_encode(['ok' => true, 'updated' => $stmt->rowCount()]);
