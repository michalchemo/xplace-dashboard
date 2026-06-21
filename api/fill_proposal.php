<?php
// Agent fills in a proposal for a project that Michal requested via dashboard button.
require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$body          = json_decode(file_get_contents('php://input'), true);
$project_id    = trim($body['project_id'] ?? '');
$proposal_text = trim($body['proposal_text'] ?? '');
$price         = (int)($body['price'] ?? 200);

if (!$project_id || !$proposal_text) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing project_id or proposal_text']);
    exit;
}

$db   = get_db();
$stmt = $db->prepare(
    'UPDATE proposals
     SET proposal_text = ?, price = ?, proposal_requested = 0, updated_at = NOW()
     WHERE project_id = ?'
);
$stmt->execute([$proposal_text, $price, $project_id]);

echo json_encode(['ok' => true, 'updated' => $stmt->rowCount()]);
