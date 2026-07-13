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
    // Add proposal_requested flag if missing (used by request_proposal / fill_proposal / get_proposal_requests)
    "ALTER TABLE proposals ADD COLUMN IF NOT EXISTS proposal_requested TINYINT(1) NOT NULL DEFAULT 0",
    // Ensure agent_notes column exists (written by add_proposal, read by get_proposal_requests)
    "ALTER TABLE proposals ADD COLUMN IF NOT EXISTS agent_notes TEXT NULL",
    // Add withdrawal_done column if missing
    "ALTER TABLE proposals ADD COLUMN IF NOT EXISTS withdrawal_done TINYINT(1) NOT NULL DEFAULT 0",
    // Ensure notes column exists
    "ALTER TABLE proposals ADD COLUMN IF NOT EXISTS notes TEXT NULL",
    // Ensure updated_at exists
    "ALTER TABLE proposals ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    // Learnings table — durable post-submission outcomes the agent learns from.
    "CREATE TABLE IF NOT EXISTS learnings (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        project_id    VARCHAR(20)  NULL,
        project_title VARCHAR(500) NULL,
        project_url   VARCHAR(500) NULL,
        outcome       VARCHAR(40)  NOT NULL,
        lesson        TEXT         NOT NULL,
        price         INT          NULL,
        price_type    VARCHAR(20)  NULL,
        active        TINYINT(1)   NOT NULL DEFAULT 1,
        created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_learning_project (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // confirmed: 1 = Michal owns/approved this learning (manual or confirmed draft).
    // 0 = agent-created draft awaiting Michal's review. Only confirmed=1 feeds the agent.
    "ALTER TABLE learnings ADD COLUMN IF NOT EXISTS confirmed TINYINT(1) NOT NULL DEFAULT 1",
    // Messages table — XPlace chat threads. The agent syncs the thread list each run and
    // alerts Michal on client messages she has not answered. Never auto-replies.
    "CREATE TABLE IF NOT EXISTS messages (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        thread_id         VARCHAR(64)  NOT NULL,
        project_id        VARCHAR(20)  NULL,
        project_title     VARCHAR(500) NULL,
        participant       VARCHAR(255) NULL,
        last_message_date DATETIME     NULL,
        last_message_text TEXT         NULL,
        last_from_me      TINYINT(1)   NOT NULL DEFAULT 0,
        status            ENUM('needs_reply','handled','ignored') NOT NULL DEFAULT 'needs_reply',
        alerted           TINYINT(1)   NOT NULL DEFAULT 0,
        notes             TEXT         NULL,
        created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_thread (thread_id),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // draft_reply: agent-written reply draft, shown in messages.php for Michal to edit and send herself.
    // Written ONLY when the client's last message contains a question. Never sent automatically.
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS draft_reply TEXT NULL",
    "ALTER TABLE messages ADD COLUMN IF NOT EXISTS draft_updated_at DATETIME NULL",
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
