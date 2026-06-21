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
