<?php
// GET endpoint — lightweight change-gate check for the Claude scheduled task.
// Lets the agent decide, in ONE call, whether a full scan/classify/WhatsApp cycle
// is worth running this time, instead of re-scanning and re-classifying projects
// it already scored in a previous run.
// Auth: Bearer token matching API_KEY in config.php

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json');

// --- Auth ---
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = get_db();

$known_project_ids = $db->query('SELECT project_id FROM proposals')->fetchAll(PDO::FETCH_COLUMN);
$pending_count      = (int)$db->query("SELECT COUNT(*) FROM proposals WHERE status = 'pending'")->fetchColumn();
$approved_count     = (int)$db->query("SELECT COUNT(*) FROM proposals WHERE status = 'approved'")->fetchColumn();

echo json_encode([
    'ok'                => true,
    'known_project_ids' => $known_project_ids,
    'pending_count'     => $pending_count,
    'approved_count'    => $approved_count,
]);
