-- ========================================================
-- QUIZLY Migration 001: Add AI Question Metadata & Fields
-- Compatible with MySQL 5.7+, MySQL 8.0+, and SQLite 3
-- ========================================================

-- Add educational fields & question type
ALTER TABLE questions ADD COLUMN question_type VARCHAR(30) NOT NULL DEFAULT 'multiple_choice';
ALTER TABLE questions ADD COLUMN difficulty VARCHAR(20) NOT NULL DEFAULT 'medium';
ALTER TABLE questions ADD COLUMN topic VARCHAR(255) NULL;
ALTER TABLE questions ADD COLUMN explanation TEXT NULL;
ALTER TABLE questions ADD COLUMN learning_objective TEXT NULL;

-- Add AI generation metadata
ALTER TABLE questions ADD COLUMN ai_generated TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE questions ADD COLUMN generation_source VARCHAR(50) NULL;
ALTER TABLE questions ADD COLUMN ai_model VARCHAR(100) NULL;
ALTER TABLE questions ADD COLUMN generation_timestamp DATETIME NULL;
