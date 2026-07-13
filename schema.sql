-- XPlace Proposals Dashboard
-- Run once to set up the database

CREATE DATABASE IF NOT EXISTS xplace_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE xplace_dashboard;

CREATE TABLE IF NOT EXISTS proposals (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      VARCHAR(20)  NOT NULL,
    project_title   VARCHAR(500) NOT NULL,
    project_url     VARCHAR(500) NOT NULL,
    proposal_text   TEXT         NOT NULL,
    price           INT          NOT NULL DEFAULT 200,
    price_type      VARCHAR(20)  NOT NULL DEFAULT 'hourly',  -- 'hourly' | 'fixed'
    status          ENUM('pending','approved','dismissed','submitted') NOT NULL DEFAULT 'pending',
    notes           TEXT,                   -- Michal's internal notes
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Learnings: durable post-submission outcomes the agent learns from.
-- Survives even if the proposals row is dismissed/deleted by the change-gate.
CREATE TABLE IF NOT EXISTS learnings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    project_id    VARCHAR(20)  NULL,
    project_title VARCHAR(500) NULL,
    project_url   VARCHAR(500) NULL,
    -- outcome: rejected_price | advanced | won | in_progress | bad_fit | lost | other
    outcome       VARCHAR(40)  NOT NULL,
    lesson        TEXT         NOT NULL,   -- the takeaway the agent should apply
    price         INT          NULL,       -- price quoted on this lead, if known
    price_type    VARCHAR(20)  NULL,       -- 'hourly' | 'fixed'
    active        TINYINT(1)   NOT NULL DEFAULT 1,  -- 0 = archived, agent ignores
    confirmed     TINYINT(1)   NOT NULL DEFAULT 1,  -- 0 = agent draft awaiting Michal; only confirmed=1 feeds the agent
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_learning_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Messages: XPlace chat threads. The agent syncs the thread list on every run and
-- alerts Michal about client messages she has not answered yet. It never auto-replies.
CREATE TABLE IF NOT EXISTS messages (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    thread_id         VARCHAR(64)  NOT NULL,   -- XPlace thread id, e.g. 81571g340350p214738
    project_id        VARCHAR(20)  NULL,
    project_title     VARCHAR(500) NULL,
    participant       VARCHAR(255) NULL,       -- the client on the other side
    last_message_date DATETIME     NULL,
    last_message_text TEXT         NULL,
    last_from_me      TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = Michal wrote last
    -- needs_reply = client wrote last and Michal has not answered
    -- handled     = Michal answered / marked done
    -- ignored     = muted on purpose, never alert again
    status            ENUM('needs_reply','handled','ignored') NOT NULL DEFAULT 'needs_reply',
    alerted           TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = already reported on WhatsApp
    -- draft_reply: agent-written reply draft, only when the client's last message asks a question.
    -- Shown in messages.php for Michal to edit and send herself. NEVER sent automatically.
    draft_reply       TEXT         NULL,
    draft_updated_at  DATETIME     NULL,
    notes             TEXT         NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_thread (thread_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
