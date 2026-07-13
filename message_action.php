<?php
// Actions for the messages table, called from messages.php (dashboard UI).
// Actions: handled | reopen | ignore | note
// Auth: logged-in dashboard session OR a valid Bearer API key (same gate as learning_action.php).

require_once __DIR__ . '/db.php';
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$__gate_active = defined('DASH_PASS_HASH')
    && DASH_PASS_HASH !== '' && DASH_PASS_HASH !== 'replace_with_password_hash';
if ($__gate_active) {
    $__auth      = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $__validKey  = str_starts_with($__auth, 'Bearer ') && trim(substr($__auth, 7)) === API_KEY;
    $__validSess = !empty($_SESSION['dash_user']);
    if (!$__validKey && !$__validSess) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$db     = get_db();

if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing id']);
    exit;
}

try {
    switch ($action) {

        // Michal answered on XPlace, or decided nothing is needed.
        case 'handled':
            $db->prepare("UPDATE messages SET status='handled', alerted=1, updated_at=NOW() WHERE id=?")
               ->execute([$id]);
            echo json_encode(['ok' => true]);
            break;

        // Put it back in the queue.
        case 'reopen':
            $db->prepare("UPDATE messages SET status='needs_reply', alerted=0, updated_at=NOW() WHERE id=?")
               ->execute([$id]);
            echo json_encode(['ok' => true]);
            break;

        // Mute permanently. The agent will not resurrect an ignored thread.
        case 'ignore':
            $db->prepare("UPDATE messages SET status='ignored', alerted=1, updated_at=NOW() WHERE id=?")
               ->execute([$id]);
            echo json_encode(['ok' => true]);
            break;

        // Michal edits the agent's draft (or writes her own). Never sent from here, she pastes it into XPlace.
        case 'save_draft':
            $draft = trim($_POST['draft_reply'] ?? '');
            $db->prepare("UPDATE messages SET draft_reply=?, draft_updated_at=NOW(), updated_at=NOW() WHERE id=?")
               ->execute([$draft, $id]);
            echo json_encode(['ok' => true]);
            break;

        case 'note':
            $note = trim($_POST['notes'] ?? '');
            $db->prepare("UPDATE messages SET notes=?, updated_at=NOW() WHERE id=?")
               ->execute([$note, $id]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
