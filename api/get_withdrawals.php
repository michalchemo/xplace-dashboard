<?php
// GET endpoint — returns proposals marked for XPlace withdrawal so the agent can remove them.
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

$db   = get_db();
$rows = $db->query("SELECT project_id, project_url FROM proposals WHERE status = 'to_withdraw' AND withdrawal_done = 0")
           ->fetchAll();

echo json_encode(['ok' => true, 'withdrawals' => $rows]);
