<?php
// POST endpoint — the agent syncs the XPlace message-thread list here each run.
// Auth: Bearer token matching API_KEY in config.php
//
// Body: { "threads": [ {
//    thread_id, project_id, project_title, participant,
//    last_message_date (ISO8601), last_message_text, last_from_me (0|1), is_unread (0|1)
// }, ... ] }
//
// Behaviour:
//   new thread            -> insert. needs_reply unless the last message is from Michal.
//   newer message arrived -> update. needs_reply + alerted=0 if it came from the client,
//                            handled if Michal was the last to write.
//   same message as before -> metadata refresh only, status untouched
//                            (except: a needs_reply row whose last message is now from Michal -> handled).
//   status 'ignored'      -> stays ignored. Michal muted the thread on purpose.
//
// Returns: threads that need an alert (status=needs_reply AND alerted=0). The agent
// sends those to WhatsApp and then calls mark_messages_alerted.php.

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true);
$threads = $body['threads'] ?? null;

if (!is_array($threads)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing: threads[]']);
    exit;
}

$db = get_db();

$sel = $db->prepare('SELECT id, status, last_message_date FROM messages WHERE thread_id = ?');
$ins = $db->prepare(
    'INSERT INTO messages
       (thread_id, project_id, project_title, participant, last_message_date,
        last_message_text, last_from_me, status, alerted)
     VALUES (?,?,?,?,?,?,?,?,0)'
);
// COALESCE on the metadata columns: a partial payload (thread_id + date only) must never
// blank out a title/participant we already have. NULL means "unknown", not "erase it".
$upd = $db->prepare(
    'UPDATE messages
        SET project_id=COALESCE(?, project_id),
            project_title=COALESCE(?, project_title),
            participant=COALESCE(?, participant),
            last_message_date=?,
            last_message_text=COALESCE(?, last_message_text),
            last_from_me=?, status=?, alerted=?, updated_at=NOW()
      WHERE id=?'
);
$updMeta = $db->prepare(
    'UPDATE messages
        SET project_id=COALESCE(?, project_id),
            project_title=COALESCE(?, project_title),
            participant=COALESCE(?, participant),
            last_from_me=?, status=?, updated_at=NOW()
      WHERE id=?'
);

$inserted = 0;
$updated  = 0;
$skipped  = 0;

foreach ($threads as $t) {
    $threadId = trim((string)($t['thread_id'] ?? ''));
    if ($threadId === '') { $skipped++; continue; }

    $projectId    = trim((string)($t['project_id'] ?? '')) ?: null;
    $projectTitle = trim((string)($t['project_title'] ?? '')) ?: null;
    $participant  = trim((string)($t['participant'] ?? '')) ?: null;
    $text         = trim((string)($t['last_message_text'] ?? ''));
    $text         = $text !== '' ? mb_substr($text, 0, 1000) : null;
    $fromMe       = !empty($t['last_from_me']) ? 1 : 0;

    $rawDate = trim((string)($t['last_message_date'] ?? ''));
    $ts      = $rawDate !== '' ? strtotime($rawDate) : false;
    $date    = $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s');

    $sel->execute([$threadId]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);

    // --- New thread ---
    if (!$row) {
        $status = $fromMe ? 'handled' : 'needs_reply';
        $ins->execute([
            $threadId, $projectId, $projectTitle, $participant,
            $date, $text, $fromMe, $status,
        ]);
        $inserted++;
        continue;
    }

    // --- Muted by Michal: never resurrect ---
    if ($row['status'] === 'ignored') {
        $skipped++;
        continue;
    }

    $isNewer = strtotime($date) > strtotime((string)$row['last_message_date']);

    if ($isNewer) {
        $status  = $fromMe ? 'handled' : 'needs_reply';
        $alerted = $fromMe ? 1 : 0;   // nothing to alert on when Michal wrote last
        $upd->execute([
            $projectId, $projectTitle, $participant, $date,
            $text, $fromMe, $status, $alerted, $row['id'],
        ]);
        $updated++;
        continue;
    }

    // Same message we already know about. Only close the loop if Michal has since replied.
    $status = ($row['status'] === 'needs_reply' && $fromMe) ? 'handled' : $row['status'];
    $updMeta->execute([$projectId, $projectTitle, $participant, $fromMe, $status, $row['id']]);
}

$alerts = $db->query(
    "SELECT thread_id, project_id, project_title, participant, last_message_text, last_message_date
       FROM messages
      WHERE status = 'needs_reply' AND alerted = 0
      ORDER BY last_message_date DESC
      LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);

// Threads waiting on Michal that have no draft yet. The agent drafts a reply for the ones
// where the client asked a question (SKILL.md STEP 1.4b), and posts it to save_message_draft.php.
$needsDraft = $db->query(
    "SELECT thread_id, project_id, project_title, participant, last_message_text
       FROM messages
      WHERE status = 'needs_reply' AND (draft_reply IS NULL OR draft_reply = '')
      ORDER BY last_message_date DESC
      LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

$needsReply = (int)$db->query("SELECT COUNT(*) FROM messages WHERE status = 'needs_reply'")->fetchColumn();

echo json_encode([
    'ok'                => true,
    'inserted'          => $inserted,
    'updated'           => $updated,
    'skipped'           => $skipped,
    'needs_reply_count' => $needsReply,
    'new_alerts'        => $alerts,
    'needs_draft'       => $needsDraft,
], JSON_UNESCAPED_UNICODE);
