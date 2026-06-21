<?php
// GET endpoint — returns approved proposals so Claude can submit them to XPlace.

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$db   = get_db();
$rows = $db->query("SELECT * FROM proposals WHERE status = 'approved' ORDER BY created_at ASC")->fetchAll();

echo json_encode(['ok' => true, 'proposals' => $rows]);
