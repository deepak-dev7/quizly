-- ========================================================
-- QUIZLY Database Schema — Multi-Tenant Live Quiz Platform
-- Designed for phpMyAdmin & Live Cloud Hosting Deployment
-- ========================================================

-- 1. Organizations Table
CREATE TABLE IF NOT EXISTS `organizations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `department` VARCHAR(100) NULL,
    `class_name` VARCHAR(100) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table (Platform Admin, Org Owner, Teacher)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('PLATFORM_ADMIN', 'ORG_OWNER', 'TEACHER') NOT NULL DEFAULT 'TEACHER',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Quizzes Table
CREATE TABLE IF NOT EXISTS `quizzes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT NOT NULL,
    `creator_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'General',
    `department` VARCHAR(100) NULL,
    `class_name` VARCHAR(100) NULL,
    `difficulty` ENUM('EASY', 'MEDIUM', 'HARD') NOT NULL DEFAULT 'MEDIUM',
    `cover_image` VARCHAR(255) NULL,
    `status` ENUM('DRAFT', 'PUBLISHED', 'ARCHIVED') NOT NULL DEFAULT 'DRAFT',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_quizzes_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_quizzes_creator` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Questions Table
CREATE TABLE IF NOT EXISTS `questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `quiz_id` INT NOT NULL,
    `question_text` TEXT NOT NULL,
    `image_url` VARCHAR(255) NULL,
    `timer_seconds` INT NOT NULL DEFAULT 20,
    `max_points` INT NOT NULL DEFAULT 1000,
    `order_num` INT NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Question Options Table
CREATE TABLE IF NOT EXISTS `question_options` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `question_id` INT NOT NULL,
    `option_key` ENUM('A', 'B', 'C', 'D') NOT NULL,
    `option_text` TEXT NOT NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT `fk_options_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Quiz Live Sessions Table
CREATE TABLE IF NOT EXISTS `quiz_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT NOT NULL,
    `quiz_id` INT NOT NULL,
    `host_id` INT NOT NULL,
    `room_code` CHAR(6) NOT NULL,
    `session_status` ENUM('WAITING', 'QUESTION_ACTIVE', 'QUESTION_RESULTS', 'LEADERBOARD', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'WAITING',
    `current_question_id` INT NULL,
    `question_started_at_ms` BIGINT NULL,
    `question_ends_at_ms` BIGINT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_sessions_org` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sessions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sessions_host` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sessions_question` FOREIGN KEY (`current_question_id`) REFERENCES `questions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Participants Table
CREATE TABLE IF NOT EXISTS `participants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `participant_token` VARCHAR(64) NOT NULL UNIQUE,
    `nickname` VARCHAR(50) NOT NULL,
    `avatar` VARCHAR(255) NULL,
    `department` VARCHAR(100) NULL,
    `class_name` VARCHAR(100) NULL,
    `total_score` INT NOT NULL DEFAULT 0,
    `correct_count` INT NOT NULL DEFAULT 0,
    `streak_count` INT NOT NULL DEFAULT 0,
    `cumulative_time_ms` BIGINT NOT NULL DEFAULT 0,
    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_participants_session` FOREIGN KEY (`session_id`) REFERENCES `quiz_sessions` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_session_nickname` (`session_id`, `nickname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Answers Table
CREATE TABLE IF NOT EXISTS `answers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `participant_id` INT NOT NULL,
    `question_id` INT NOT NULL,
    `selected_option_key` ENUM('A', 'B', 'C', 'D') NOT NULL,
    `submitted_at_ms` BIGINT NOT NULL,
    `response_time_ms` INT NOT NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    `score_earned` INT NOT NULL DEFAULT 0,
    `streak_bonus` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_answers_session` FOREIGN KEY (`session_id`) REFERENCES `quiz_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_answers_participant` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE,
    UNIQUE KEY `uk_session_participant_question` (`session_id`, `participant_id`, `question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Audit Logs Table
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `organization_id` INT NULL,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database Performance Indexes
CREATE INDEX `idx_quizzes_org_status` ON `quizzes` (`organization_id`, `status`);
CREATE INDEX `idx_questions_quiz_order` ON `questions` (`quiz_id`, `order_num`);
CREATE INDEX `idx_sessions_room_status` ON `quiz_sessions` (`room_code`, `session_status`);
CREATE INDEX `idx_sessions_org` ON `quiz_sessions` (`organization_id`);
CREATE INDEX `idx_participants_session` ON `participants` (`session_id`);
CREATE INDEX `idx_answers_session_question` ON `answers` (`session_id`, `question_id`);
