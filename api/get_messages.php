<?php
// GET endpoint — threads waiting for Michal's reply.
// Auth: Bearer token matching API_KEY in config.php
//
// Optional: ?status=needs_reply|handled|ignored|all   (default: needs_reply)

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$status = $_GET['status'] ?? 'needs_reply';
$valid  = ['needs_reply', 'handled', 'ignored', 'all'];
if (!in_array($status, $valid, true)) {
    $status = 'needs_reply';
}

$db = get_db();

$sql = "SELECT thread_id, project_id, project_title, participant,
               last_message_date, last_message_text, last_from_me, status, alerted,
               draft_reply, draft_updated_at
          FROM messages";
$params = [];
if ($status !== 'all') {
    $sql .= " WHERE status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY last_message_date DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'count' => count($rows), 'messages' => $rows], JSON_UNESCAPED_UNICODE);
