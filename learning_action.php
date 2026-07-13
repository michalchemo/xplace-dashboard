<?php
// CRUD for the learnings table, called from learnings.php (dashboard UI).
// Actions: add | update | delete | confirm | toggle_active
// Auth: logged-in dashboard session OR a valid Bearer API key (same gate as action.php).

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

$action  = $_POST['action'] ?? '';
$db      = get_db();
$allowed = ['rejected_price', 'advanced', 'won', 'in_progress', 'bad_fit', 'lost', 'other'];

try {
    switch ($action) {

        case 'add': {
            $outcome = trim($_POST['outcome'] ?? '');
            $lesson  = trim($_POST['lesson'] ?? '');
            if (!in_array($outcome, $allowed, true) || $lesson === '') {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'צריך outcome תקין ולקח']);
                break;
            }
            $pid   = trim($_POST['project_id'] ?? '') ?: null;
            $title = trim($_POST['project_title'] ?? '') ?: null;
            $url   = trim($_POST['project_url'] ?? '') ?: null;
            $price = ($_POST['price'] ?? '') !== '' ? (int)$_POST['price'] : null;
            $stmt = $db->prepare(
                'INSERT INTO learnings (project_id, project_title, project_url, outcome, lesson, price, active, confirmed)
                 VALUES (?,?,?,?,?,?,1,1)
                 ON DUPLICATE KEY UPDATE outcome=VALUES(outcome), lesson=VALUES(lesson),
                     project_title=VALUES(project_title), project_url=VALUES(project_url),
                     price=VALUES(price), active=1, confirmed=1, updated_at=NOW()'
            );
            $stmt->execute([$pid, $title, $url, $outcome, $lesson, $price]);
            echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId()]);
            break;
        }

        case 'update': {
            $id      = (int)($_POST['id'] ?? 0);
            $outcome = trim($_POST['outcome'] ?? '');
            $lesson  = trim($_POST['lesson'] ?? '');
            if (!$id || !in_array($outcome, $allowed, true) || $lesson === '') {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'פרמטרים חסרים']);
                break;
            }
            $price = ($_POST['price'] ?? '') !== '' ? (int)$_POST['price'] : null;
            // Any manual edit confirms the row (Michal now owns it).
            $stmt = $db->prepare(
                'UPDATE learnings SET outcome=?, lesson=?, price=?, confirmed=1, updated_at=NOW() WHERE id=?'
            );
            $stmt->execute([$outcome, $lesson, $price, $id]);
            echo json_encode(['ok' => true]);
            break;
        }

        case 'confirm': {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing id']); break; }
            $db->prepare('UPDATE learnings SET confirmed=1, updated_at=NOW() WHERE id=?')->execute([$id]);
            echo json_encode(['ok' => true]);
            break;
        }

        case 'toggle_active': {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing id']); break; }
            $db->prepare('UPDATE learnings SET active = 1 - active, updated_at=NOW() WHERE id=?')->execute([$id]);
            echo json_encode(['ok' => true]);
            break;
        }

        case 'delete': {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing id']); break; }
            $db->prepare('DELETE FROM learnings WHERE id=?')->execute([$id]);
            echo json_encode(['ok' => true]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
