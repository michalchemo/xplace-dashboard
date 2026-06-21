<?php
// Run once to apply schema updates to the live DB.
// Auth: Bearer token

require_once dirname(__DIR__) . '/db.php';
header('Content-Type: application/json');

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!str_starts_with($auth, 'Bearer ') || trim(substr($auth, 7)) !== API_KEY) {
    http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit;
}

$db = get_db();
$results = [];

$migrations = [
    // Add withdrawal_done column if missing
    "ALTER TABLE proposals ADD COLUMN IF NOT EXISTS withdrawal_done TINYINT(1) NOT NULL DEFAULT 0",
    // Ensure notes column exists
    "ALTER TABLE proposals ADD COLUMN IF NOT EXISTS notes TEXT NULL",
    // Ensure updated_at exists
    "ALTER TABLE proposals ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
];

foreach ($migrations as $sql) {
    try {
        $db->exec($sql);
        $results[] = ['sql' => substr($sql, 0, 60), 'ok' => true];
    } catch (Exception $e) {
        $results[] = ['sql' => substr($sql, 0, 60), 'ok' => false, 'error' => $e->getMessage()];
    }
}

echo json_encode(['ok' => true, 'results' => $results]);
