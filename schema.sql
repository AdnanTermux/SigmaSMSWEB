-- Sigma SMS A2P OTP Panel — Database Schema
-- Engine: InnoDB, Charset: utf8mb4
-- Tables created in dependency order to satisfy foreign key constraints.

CREATE DATABASE IF NOT EXISTS `sigma_sms_a2p`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sigma_sms_a2p`;

-- ── 1. Users ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(30)  NOT NULL UNIQUE,
  `email`      VARCHAR(100) DEFAULT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','manager','reseller','sub_reseller') NOT NULL DEFAULT 'reseller',
  `status`     ENUM('active','pending','blocked') NOT NULL DEFAULT 'active',
  `parent_id`  INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_users_parent_status` ON `users` (`parent_id`, `status`);
CREATE INDEX `idx_users_role_status` ON `users` (`role`, `status`);

-- ── 2. API Tokens ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
  `token`        VARCHAR(64)  NOT NULL UNIQUE,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` DATETIME DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Numbers ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `numbers` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `number`      VARCHAR(20) NOT NULL UNIQUE,
  `country`     VARCHAR(2)  DEFAULT NULL,
  `service`     VARCHAR(50) DEFAULT NULL,
  `rate`        DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_by`  INT UNSIGNED DEFAULT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `assigned_at` DATETIME DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_numbers_assigned_status` ON `numbers` (`assigned_to`, `status`);
CREATE INDEX `idx_numbers_service_country` ON `numbers` (`service`, `country`);

-- ── 4. SMS Received (real data from external API) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `sms_received` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `number`      VARCHAR(20) NOT NULL,
  `service`     VARCHAR(50) DEFAULT NULL,
  `country`     VARCHAR(2)  DEFAULT NULL,
  `otp`         VARCHAR(30) NOT NULL,
  `message`     TEXT,
  `received_at` DATETIME NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_otp` (`number`, `otp`, `received_at`),
  KEY `idx_received_at` (`received_at`),
  KEY `idx_number`      (`number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Profit Log ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `profit_log` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT UNSIGNED NOT NULL,
  `number_id`       INT UNSIGNED NOT NULL,
  `sms_received_id` INT UNSIGNED NOT NULL,
  `rate_applied`    DECIMAL(10,6) NOT NULL,
  `profit_amount`   DECIMAL(10,6) NOT NULL,
  `currency`        VARCHAR(3) NOT NULL DEFAULT 'USD',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_sms_received` (`sms_received_id`),
  KEY `idx_user_id`    (`user_id`),
  KEY `idx_number_id`  (`number_id`),
  KEY `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`)         REFERENCES `users`(`id`)        ON DELETE CASCADE,
  FOREIGN KEY (`number_id`)       REFERENCES `numbers`(`id`)      ON DELETE CASCADE,
  FOREIGN KEY (`sms_received_id`) REFERENCES `sms_received`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_profit_user_created` ON `profit_log` (`user_id`, `created_at`);

-- ── 6. Notifications ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `message`    TEXT NOT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX `idx_notifications_user_read` ON `notifications` (`user_id`, `is_read`);

-- ── 7. News / Announcements ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `news_master` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(255) NOT NULL,
  `content`    TEXT NOT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 8. Credit Notes ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `credit_notes` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `currency`    VARCHAR(3) NOT NULL DEFAULT 'USD',
  `description` TEXT,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. Payment Requests ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payment_requests` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `amount`     DECIMAL(10,2) NOT NULL,
  `currency`   VARCHAR(3) NOT NULL DEFAULT 'USD',
  `status`     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 10. Bank Accounts ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT UNSIGNED NOT NULL,
  `bank_name`      VARCHAR(100) DEFAULT NULL,
  `account_number` VARCHAR(50)  DEFAULT NULL,
  `routing_number` VARCHAR(50)  DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 11. Statements ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `statements` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT UNSIGNED NOT NULL,
  `period_start`   DATE DEFAULT NULL,
  `period_end`     DATE DEFAULT NULL,
  `total_earnings` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency`       VARCHAR(3) NOT NULL DEFAULT 'USD',
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 12. Settings (key-value store) ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key`   VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Default settings ──────────────────────────────────────────────────────────
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('last_fetch',  '2000-01-01 00:00:00'),
  ('site_name',   'Sigma SMS A2P'),
  ('site_logo',   ''),
  ('otp_api_url', 'https://tempnum.net/api/public/otps');

-- ── Default admin user ────────────────────────────────────────────────────────
-- Default password: "password" (bcrypt hash)
-- CHANGE IMMEDIATELY after installation!
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `role`, `status`) VALUES
  ('admin', 'admin@sigma-sms.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');
