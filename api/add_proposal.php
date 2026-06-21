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
$price               = (int)($body['price'] ?? 200);
$price_type          = $body['price_type'] ?? 'hourly';

// --- Upsert (ignore if project already exists) ---
$db = get_db();
$stmt = $db->prepare('
    INSERT IGNORE INTO proposals (project_id, project_title, project_url, proposal_text, project_description, price, price_type)
    VALUES (?, ?, ?, ?, ?, ?, ?)
');
$stmt->execute([$project_id, $project_title, $project_url, $proposal_text, $project_description, $price, $price_type]);

echo json_encode(['ok' => true, 'inserted' => $stmt->rowCount()]);
