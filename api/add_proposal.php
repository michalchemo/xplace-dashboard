<?php
// POST endpoint — called by the Claude scheduled task to add a new proposal draft.
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

// --- Input ---
$body = json_decode(file_get_contents('php://input'), true);

$required = ['project_id', 'project_title', 'project_url'];
foreach ($required as $field) {
    if (empty($body[$field])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => "Missing: $field"]);
        exit;
    }
}

$project_id          = trim($body['project_id']);
$project_title       = trim($body['project_title']);
$project_url         = trim($body['project_url']);
$proposal_text       = trim($body['proposal_text'] ?? '');   // optional — empty = pending review
$project_description = trim($body['project_description'] ?? '');  // full job ad text
$agent_notes         = trim($body['agent_notes'] ?? '');     // why the agent skipped/rejected
$price               = (int)($body['price'] ?? 200);
$price_type          = $body['price_type'] ?? 'hourly';
$client_name         = trim($body['client_name'] ?? '');     // optional — client display name

// --- Upsert: never overwrite an existing proposal's content, but do backfill
// --- client_name on rows that don't have one yet.
$db = get_db();
$stmt = $db->prepare('
    INSERT INTO proposals (project_id, project_title, project_url, proposal_text, project_description, agent_notes, price, price_type, client_name)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        client_name = IF(VALUES(client_name) IS NOT NULL AND VALUES(client_name) <> \'\',
                         VALUES(client_name), client_name)
');
$stmt->execute([$project_id, $project_title, $project_url, $proposal_text, $project_description, $agent_notes, $price, $price_type, $client_name ?: null]);

// rowCount: 1 = inserted, 2 = existing row updated, 0 = existing row unchanged
echo json_encode(['ok' => true, 'inserted' => $stmt->rowCount() === 1 ? 1 : 0]);
