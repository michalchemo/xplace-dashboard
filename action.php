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
            $stmt  = $db->prepare('UPDATE proposals SET status=?, proposal_text=?, price=?, notes=?, updated_at=NOW() WHERE id=?');
            $stmt->execute(['approved', $text, $price, $notes, $id]);
            echo json_encode(['ok' => true]);
            break;

        case 'dismiss':
            $stmt = $db->prepare('UPDATE proposals SET status=?, updated_at=NOW() WHERE id=?');
            $stmt->execute(['dismissed', $id]);
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
            // Save proposal text + price + notes without changing status
            $text  = trim($_POST['proposal_text'] ?? '');
            $price = (int)($_POST['price'] ?? 200);
            $notes = trim($_POST['notes'] ?? '');
            $stmt  = $db->prepare('UPDATE proposals SET proposal_text=?, price=?, notes=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$text, $price, $notes, $id]);
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
