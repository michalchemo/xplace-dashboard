<?php
// POST endpoint — the agent calls this after the WhatsApp summary went out,
// so the same message is not alerted on twice.
// Auth: Bearer token matching API_KEY in config.php
//
// Body: { "thread_ids": ["81571g340350p214738", ...] }

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$ids  = $body['thread_ids'] ?? null;

if (!is_array($ids) || !$ids) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing: thread_ids[]']);
    exit;
}

$db   = get_db();
$in   = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("UPDATE messages SET alerted = 1 WHERE thread_id IN ($in)");
$stmt->execute(array_values($ids));

echo json_encode(['ok' => true, 'marked' => $stmt->rowCount()]);
