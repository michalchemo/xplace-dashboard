<?php
// Handles approve / edit / dismiss / mark-submitted actions from the dashboard.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$db     = get_db();

if (!$id || !$action) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing params']);
    exit;
}

try {
    switch ($action) {

        case 'approve':
            $text  = trim($_POST['proposal_text'] ?? '');
            $price = (int)($_POST['price'] ?? 200);
            $notes = trim($_POST['notes'] ?? '');

            // Guard: never move an empty proposal to "approved" (לשליחה).
            // The card-level "הגש" button sends no text, so without this guard
            // an approve could wipe the stored text to '' and queue a blank,
            // binding bid for submission.
            if ($text === '' || $text === 'ממתין לסקירה') {
                // Fall back to the text already stored for this proposal.
                $cur = $db->prepare('SELECT proposal_text FROM proposals WHERE id=?');
                $cur->execute([$id]);
                $existing = trim((string)($cur->fetchColumn() ?: ''));
                if ($existing === '' || $existing === 'ממתין לסקירה') {
                    http_response_code(422);
                    echo json_encode(['ok' => false, 'error' => 'אי אפשר להעביר לשליחה הצעה ללא תוכן. כתבי תוכן הצעה, או לחצי "בקשי הצעה" כדי שהסוכן ימלא, ואז אשרי.']);
                    break;
                }
                // Keep the existing text; only update status / price / notes.
                $stmt = $db->prepare('UPDATE proposals SET status=?, price=?, notes=?, updated_at=NOW() WHERE id=?');
                $stmt->execute(['approved', $price, $notes, $id]);
                echo json_encode(['ok' => true]);
                break;
            }

            $stmt  = $db->prepare('UPDATE proposals SET status=?, proposal_text=?, price=?, notes=?, updated_at=NOW() WHERE id=?');
            $stmt->execute(['approved', $text, $price, $notes, $id]);
            echo json_encode(['ok' => true]);
            break;

        case 'dismiss':
            $reason = trim($_POST['rejection_reason'] ?? '');
            $stmt = $db->prepare('UPDATE proposals SET status=?, rejection_reason=?, updated_at=NOW() WHERE id=?');
            $stmt->execute(['to_withdraw', $reason ?: null, $id]);
            echo json_encode(['ok' => true]);
            break;

        case 'submitted':
            $stmt = $db->prepare('UPDATE proposals SET status=?, updated_at=NOW() WHERE id=?');
            $stmt->execute(['submitted', $id]);
            echo json_encode(['ok' => true]);
            break;

        case 'restore':
            $stmt = $db->prepare('UPDATE proposals SET status=?, updated_at=NOW() WHERE id=?');
            $stmt->execute(['pending', $id]);
            echo json_encode(['ok' => true]);
            break;

        case 'save':
            $text  = trim($_POST['proposal_text'] ?? '');
            $price = (int)($_POST['price'] ?? 200);
            $notes = trim($_POST['notes'] ?? '');
            $stmt  = $db->prepare('UPDATE proposals SET proposal_text=?, price=?, notes=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$text, $price, $notes, $id]);
            echo json_encode(['ok' => true]);
            break;

        case 'save_description':
            $desc = trim($_POST['description'] ?? '');
            $stmt = $db->prepare('UPDATE proposals SET project_description=? WHERE id=?');
            $stmt->execute([$desc, $id]);
            echo json_encode(['ok' => true]);
            break;

        case 'request_proposal':
            // Michal requests (re)generated content for this project.
            // An optional guidance note is saved to the internal `notes` field and
            // read by the agent via get_proposal_requests.php on the next run.
            // Status is forced back to 'pending' so an already-approved item
            // re-enters the review queue for the agent to rewrite.
            $guidance = trim($_POST['notes'] ?? '');
            $stmt = $db->prepare(
                "UPDATE proposals
                 SET status = 'pending',
                     proposal_requested = 1,
                     notes = COALESCE(NULLIF(?, ''), notes),
                     updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$guidance, $id]);
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
