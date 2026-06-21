<?php
// Returns past manual rejection reasons — agent uses these to calibrate future classification.
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
    "SELECT project_title, rejection_reason
     FROM proposals
     WHERE rejection_reason IS NOT NULL AND rejection_reason != ''
     ORDER BY updated_at DESC
     LIMIT 60"
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'rejections' => $rows]);
