<?php
// POST endpoint — the agent saves a reply DRAFT for a message thread.
// Auth: Bearer token matching API_KEY in config.php
//
// Body: { "thread_id": "...", "draft_reply": "..." }
//
// The draft is only ever shown to Michal in messages.php. Nothing is sent to XPlace from here.
// Policy (SKILL.md STEP 1.4b): a draft is written ONLY when the client's last message asks
// a question (price, scope, availability). Otherwise the thread is alert-only.

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true);
$threadId = trim((string)($body['thread_id'] ?? ''));
$draft    = trim((string)($body['draft_reply'] ?? ''));

if ($threadId === '' || $draft === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing: thread_id / draft_reply']);
    exit;
}

$db   = get_db();
$stmt = $db->prepare(
    "UPDATE messages
        SET draft_reply = ?, draft_updated_at = NOW(), updated_at = NOW()
      WHERE thread_id = ? AND status <> 'ignored'"
);
$stmt->execute([$draft, $threadId]);

if ($stmt->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Thread not found (or ignored). Run sync_messages.php first.']);
    exit;
}

echo json_encode(['ok' => true]);
