<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private static $driver = 'mysql';

    public static function getConnection() {
        if (self::$instance === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Try MySQL Connection first
            try {
                $dsn = sprintf(
                    "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$driver = 'mysql';

                // Auto-verify if tables exist in MySQL, if empty schema auto-initialize!
                self::ensureMysqlSchema(self::$instance);
            } catch (PDOException $e) {
                // Fallback to embedded SQLite database if MySQL server is unreachable or failing
                $sqliteDir = __DIR__ . '/../database';
                if (!is_dir($sqliteDir)) {
                    @mkdir($sqliteDir, 0777, true);
                }
                $sqlitePath = $sqliteDir . '/quizly.sqlite';
                $isNewFile = !file_exists($sqlitePath);
                
                self::$instance = new PDO("sqlite:" . $sqlitePath, null, null, $options);
                self::$driver = 'sqlite';

                // Register MySQL function compatibility wrappers for SQLite driver
                self::$instance->sqliteCreateFunction('NOW', function() {
                    return date('Y-m-d H:i:s');
                });
                self::$instance->sqliteCreateFunction('UNIX_TIMESTAMP', function() {
                    return time();
                });
                self::$instance->sqliteCreateFunction('FORMAT', function($val, $dec = 3) {
                    return number_format((float)$val, (int)$dec, '.', '');
                });

                if ($isNewFile) {
                    self::initSqliteSchema(self::$instance);
                }
                self::ensureColumns(self::$instance);
            }
        }
        return self::$instance;
    }

    private static function ensureMysqlSchema(PDO $db): void {
        try {
            $test = $db->query("SELECT 1 FROM `quiz_sessions` LIMIT 1");
        } catch (Throwable $e) {
            $schemaFile = __DIR__ . '/../database/schema.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $statements = explode(';', $sql);
                foreach ($statements as $stmtSql) {
                    $cleanLines = [];
                    foreach (explode("\n", $stmtSql) as $line) {
                        $trimmed = trim($line);
                        if ($trimmed !== '' && !str_starts_with($trimmed, '--')) {
                            $cleanLines[] = $line;
                        }
                    }
                    $cleanStmt = trim(implode("\n", $cleanLines));
                    if (!empty($cleanStmt)) {
                        try {
                            $db->exec($cleanStmt);
                        } catch (Throwable $t) {
                            // ignore duplicate table or index notices
                        }
                    }
                }
                self::seedMysqlDefaultData($db);
            }
        }
        self::ensureColumns($db);
    }

    private static function ensureColumns(PDO $db): void {
        $participantCols = [
            'avatar' => 'VARCHAR(255) NULL',
            'department' => 'VARCHAR(100) NULL',
            'class_name' => 'VARCHAR(100) NULL',
            'total_score' => 'INT NOT NULL DEFAULT 0',
            'correct_count' => 'INT NOT NULL DEFAULT 0',
            'streak_count' => 'INT NOT NULL DEFAULT 0',
            'cumulative_time_ms' => 'BIGINT NOT NULL DEFAULT 0'
        ];

        foreach ($participantCols as $col => $type) {
            try {
                $db->query("SELECT `$col` FROM participants LIMIT 1");
            } catch (Throwable $e) {
                try {
                    $db->exec("ALTER TABLE participants ADD COLUMN `$col` $type");
                } catch (Throwable $ex) {}
            }
        }

        $answersCols = [
            'selected_option_key' => "VARCHAR(10) NULL",
            'submitted_at_ms' => "BIGINT NOT NULL DEFAULT 0",
            'score_earned' => "INT NOT NULL DEFAULT 0",
            'streak_bonus' => "INT NOT NULL DEFAULT 0"
        ];

        foreach ($answersCols as $col => $type) {
            try {
                $db->query("SELECT `$col` FROM answers LIMIT 1");
            } catch (Throwable $e) {
                try {
                    $db->exec("ALTER TABLE answers ADD COLUMN `$col` $type");
                } catch (Throwable $ex) {}
            }
        }

        $quizzesCols = [
            'organization_id' => 'INT NOT NULL DEFAULT 1',
            'creator_id' => 'INT NOT NULL DEFAULT 1'
        ];

        foreach ($quizzesCols as $col => $type) {
            try {
                $db->query("SELECT `$col` FROM quizzes LIMIT 1");
            } catch (Throwable $e) {
                try {
                    $db->exec("ALTER TABLE quizzes ADD COLUMN `$col` $type");
                } catch (Throwable $ex) {}
            }
        }

        $questionsCols = [
            'question_type' => "VARCHAR(30) NOT NULL DEFAULT 'multiple_choice'",
            'difficulty' => "VARCHAR(20) NOT NULL DEFAULT 'medium'",
            'topic' => "VARCHAR(255) NULL",
            'explanation' => "TEXT NULL",
            'learning_objective' => "TEXT NULL",
            'ai_generated' => "TINYINT(1) NOT NULL DEFAULT 0",
            'generation_source' => "VARCHAR(50) NULL",
            'ai_model' => "VARCHAR(100) NULL",
            'generation_timestamp' => "DATETIME NULL"
        ];

        foreach ($questionsCols as $col => $type) {
            try {
                $db->query("SELECT `$col` FROM questions LIMIT 1");
            } catch (Throwable $e) {
                try {
                    $db->exec("ALTER TABLE questions ADD COLUMN `$col` $type");
                } catch (Throwable $ex) {}
            }
        }
    }

    private static function seedMysqlDefaultData(PDO $db): void {
        try {
            $stmtOrg = $db->prepare("SELECT id FROM organizations WHERE slug = 'tech-edu'");
            $stmtOrg->execute();
            $orgId = $stmtOrg->fetchColumn();

            if (!$orgId) {
                $stmtOrgIns = $db->prepare("INSERT INTO organizations (name, slug) VALUES ('Tech Education Institute', 'tech-edu')");
                $stmtOrgIns->execute();
                $orgId = (int)$db->lastInsertId();
            }

            $passHashAdmin = password_hash('admin123', PASSWORD_DEFAULT);
            $passHashTeacher = password_hash('teacher123', PASSWORD_DEFAULT);

            $stmtUser = $db->prepare("SELECT id FROM users WHERE email = 'admin@quizly.com'");
            $stmtUser->execute();
            if (!$stmtUser->fetchColumn()) {
                $stmtAdmin = $db->prepare("INSERT INTO users (organization_id, name, email, password_hash, role) VALUES (?, 'System Admin', 'admin@quizly.com', ?, 'PLATFORM_ADMIN')");
                $stmtAdmin->execute([$orgId, $passHashAdmin]);
            }

            $stmtTeacherCheck = $db->prepare("SELECT id FROM users WHERE email = 'teacher@quizly.com'");
            $stmtTeacherCheck->execute();
            if (!$stmtTeacherCheck->fetchColumn()) {
                $stmtTeacher = $db->prepare("INSERT INTO users (organization_id, name, email, password_hash, role) VALUES (?, 'Demo Teacher', 'teacher@quizly.com', ?, 'TEACHER')");
                $stmtTeacher->execute([$orgId, $passHashTeacher]);
            }
        } catch (Throwable $e) {
            // Silently swallow seed issues
        }
    }

    public static function getDriver(): string {
        return self::$driver;
    }

    private static function initSqliteSchema(PDO $db): void {
        $sql = "
        CREATE TABLE IF NOT EXISTS organizations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            department TEXT NULL,
            class_name TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'TEACHER',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS quizzes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            creator_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT NULL,
            category TEXT NOT NULL DEFAULT 'General',
            department TEXT NULL,
            class_name TEXT NULL,
            difficulty TEXT NOT NULL DEFAULT 'MEDIUM',
            cover_image TEXT NULL,
            status TEXT NOT NULL DEFAULT 'DRAFT',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS questions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quiz_id INTEGER NOT NULL,
            question_text TEXT NOT NULL,
            image_url TEXT NULL,
            timer_seconds INTEGER NOT NULL DEFAULT 20,
            max_points INTEGER NOT NULL DEFAULT 1000,
            order_num INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS question_options (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            question_id INTEGER NOT NULL,
            option_key TEXT NOT NULL,
            option_text TEXT NOT NULL,
            is_correct INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS quiz_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NOT NULL,
            quiz_id INTEGER NOT NULL,
            host_id INTEGER NOT NULL,
            room_code TEXT NOT NULL,
            session_status TEXT NOT NULL DEFAULT 'WAITING',
            current_question_id INTEGER NULL,
            question_started_at_ms INTEGER NULL,
            question_ends_at_ms INTEGER NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS participants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER NOT NULL,
            participant_token TEXT NOT NULL UNIQUE,
            nickname TEXT NOT NULL,
            avatar TEXT NULL,
            department TEXT NULL,
            class_name TEXT NULL,
            total_score INTEGER NOT NULL DEFAULT 0,
            correct_count INTEGER NOT NULL DEFAULT 0,
            streak_count INTEGER NOT NULL DEFAULT 0,
            cumulative_time_ms INTEGER NOT NULL DEFAULT 0,
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(session_id, nickname)
        );

        CREATE TABLE IF NOT EXISTS answers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id INTEGER NOT NULL,
            participant_id INTEGER NOT NULL,
            question_id INTEGER NOT NULL,
            selected_option_key TEXT NOT NULL,
            submitted_at_ms INTEGER NOT NULL,
            response_time_ms INTEGER NOT NULL,
            is_correct INTEGER NOT NULL DEFAULT 0,
            score_earned INTEGER NOT NULL DEFAULT 0,
            streak_bonus INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(session_id, participant_id, question_id)
        );

        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            organization_id INTEGER NULL,
            user_id INTEGER NULL,
            action TEXT NOT NULL,
            details TEXT NULL,
            ip_address TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        ";

        $db->exec($sql);

        // Seed default organization and demo users
        $stmtOrg = $db->prepare("INSERT INTO organizations (name, slug, department, class_name) VALUES ('Default Institution', 'default-institution', 'Computer Science', 'CS-A 3rd Year')");
        $stmtOrg->execute();
        $orgId = (int)$db->lastInsertId();

        $passHashAdmin = password_hash('admin123', PASSWORD_DEFAULT);
        $passHashTeacher = password_hash('teacher123', PASSWORD_DEFAULT);

        $stmtAdmin = $db->prepare("INSERT INTO users (organization_id, name, email, password_hash, role) VALUES (?, 'System Admin', 'admin@quizly.com', ?, 'PLATFORM_ADMIN')");
        $stmtAdmin->execute([$orgId, $passHashAdmin]);

        $stmtTeacher = $db->prepare("INSERT INTO users (organization_id, name, email, password_hash, role) VALUES (?, 'Demo Teacher', 'teacher@quizly.com', ?, 'TEACHER')");
        $stmtTeacher->execute([$orgId, $passHashTeacher]);
    }
}
