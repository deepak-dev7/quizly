<?php
// QUIZLY Database Installer & Seeder (Robust Multi-Engine Diagnostic Installer)

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<div style='font-family: sans-serif; max-width: 650px; margin: 2rem auto; padding: 2rem; background: #0F172A; color: #F8FAFC; border-radius: 14px; border: 1px solid rgba(255,255,255,0.12); shadow: 0 10px 30px rgba(0,0,0,0.5);'>";
echo "<h2 style='color:#38BDF8; margin-top:0;'>🚀 QUIZLY Database Installer</h2>";

try {
    // 1. Connect to Database server (MySQL or SQLite fallback)
    $pdo = Database::getConnection();
    $driver = Database::getDriver();
    
    if ($driver === 'mysql') {
        echo "<p style='color:#34D399;'>✅ Connected to MySQL Database (Host: <code>" . DB_HOST . "</code> | DB: <code>" . DB_NAME . "</code>)</p>";
    } else {
        echo "<p style='color:#FBBF24;'>⚡ MySQL host offline or credentials pending. Running on embedded zero-downtime SQLite engine!</p>";
    }

    // 2. Read schema.sql
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql not found at $schemaFile");
    }

    $sql = file_get_contents($schemaFile);
    
    // Split SQL by semicolon and execute statement by statement cleanly
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        'strlen'
    );

    $executedCount = 0;
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
                $pdo->exec($cleanStmt);
                $executedCount++;
            } catch (Exception $ex) {
                // Ignore table exists or index exists duplicate notices
            }
        }
    }

    echo "<p style='color:#34D399;'>✅ Executed database schema ($executedCount tables/indexes ready).</p>";

    if ($driver === 'mysql') {
        @$pdo->exec("USE `" . DB_NAME . "`");
    }

    // 3. Seed Organization
    $stmt = $pdo->prepare("SELECT id FROM organizations WHERE slug = 'tech-edu'");
    $stmt->execute();
    $orgId = $stmt->fetchColumn();

    if (!$orgId) {
        $stmt = $pdo->prepare("INSERT INTO organizations (name, slug) VALUES ('Tech Education Institute', 'tech-edu')");
        $stmt->execute();
        $orgId = $pdo->lastInsertId();
        echo "<p style='color:#34D399;'>✅ Created Organization: <strong>Tech Education Institute</strong></p>";
    } else {
        echo "<p style='color:#94A3B8;'>ℹ️ Organization already exists (ID: $orgId)</p>";
    }

    // 4. Seed Users
    $passwordHashAdmin = password_hash('admin123', PASSWORD_BCRYPT);
    $passwordHashTeacher = password_hash('teacher123', PASSWORD_BCRYPT);

    // Admin user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@quizly.com'");
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        $stmt = $pdo->prepare("
            INSERT INTO users (organization_id, name, email, password_hash, role)
            VALUES (:org_id, 'Platform Admin', 'admin@quizly.com', :pwd, 'PLATFORM_ADMIN')
        ");
        $stmt->execute(['org_id' => $orgId, 'pwd' => $passwordHashAdmin]);
        echo "<p style='color:#34D399;'>✅ Created Admin: <code>admin@quizly.com</code> | Pass: <code>admin123</code></p>";
    }

    // Teacher user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'teacher@quizly.com'");
    $stmt->execute();
    $teacherId = $stmt->fetchColumn();
    if (!$teacherId) {
        $stmt = $pdo->prepare("
            INSERT INTO users (organization_id, name, email, password_hash, role)
            VALUES (:org_id, 'Prof. Alex Turner', 'teacher@quizly.com', :pwd, 'TEACHER')
        ");
        $stmt->execute(['org_id' => $orgId, 'pwd' => $passwordHashTeacher]);
        $teacherId = $pdo->lastInsertId();
        echo "<p style='color:#34D399;'>✅ Created Teacher: <code>teacher@quizly.com</code> | Pass: <code>teacher123</code></p>";
    }

    // 5. Seed Demo Quiz
    $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE title = 'Computer Networks & Web Protocols' AND organization_id = :org_id");
    $stmt->execute(['org_id' => $orgId]);
    $quizId = $stmt->fetchColumn();

    if (!$quizId) {
        $stmt = $pdo->prepare("
            INSERT INTO quizzes (organization_id, creator_id, title, description, category, difficulty, status)
            VALUES (:org_id, :creator_id, 'Computer Networks & Web Protocols', 'Test your knowledge of OSI layers, HTTP standards, TCP/IP, and web infrastructure.', 'Computer Science', 'MEDIUM', 'PUBLISHED')
        ");
        $stmt->execute(['org_id' => $orgId, 'creator_id' => $teacherId]);
        $quizId = $pdo->lastInsertId();
        echo "<p style='color:#34D399;'>✅ Created Demo Quiz: <strong>Computer Networks & Web Protocols</strong></p>";

        // Questions Data
        $questions = [
            [
                'q' => 'Which protocol is primarily used for securing HTTP traffic on the Web?',
                'timer' => 20,
                'points' => 1000,
                'options' => [
                    ['key' => 'A', 'text' => 'FTP', 'correct' => 0],
                    ['key' => 'B', 'text' => 'HTTPS / TLS', 'correct' => 1],
                    ['key' => 'C', 'text' => 'SMTP', 'correct' => 0],
                    ['key' => 'D', 'text' => 'SSH', 'correct' => 0],
                ]
            ],
            [
                'q' => 'At which layer of the OSI model does the Internet Protocol (IP) operate?',
                'timer' => 20,
                'points' => 1000,
                'options' => [
                    ['key' => 'A', 'text' => 'Layer 2 - Data Link', 'correct' => 0],
                    ['key' => 'B', 'text' => 'Layer 3 - Network', 'correct' => 1],
                    ['key' => 'C', 'text' => 'Layer 4 - Transport', 'correct' => 0],
                    ['key' => 'D', 'text' => 'Layer 7 - Application', 'correct' => 0],
                ]
            ],
            [
                'q' => 'What is the default port number for HTTP web traffic?',
                'timer' => 15,
                'points' => 1000,
                'options' => [
                    ['key' => 'A', 'text' => '21', 'correct' => 0],
                    ['key' => 'B', 'text' => '22', 'correct' => 0],
                    ['key' => 'C', 'text' => '80', 'correct' => 1],
                    ['key' => 'D', 'text' => '443', 'correct' => 0],
                ]
            ]
        ];

        $stmtQ = $pdo->prepare("
            INSERT INTO questions (quiz_id, question_text, timer_seconds, max_points, order_num)
            VALUES (:quiz_id, :q_text, :timer, :points, :order_num)
        ");

        $stmtOpt = $pdo->prepare("
            INSERT INTO question_options (question_id, option_key, option_text, is_correct)
            VALUES (:question_id, :key, :text, :is_correct)
        ");

        foreach ($questions as $idx => $qData) {
            $order = $idx + 1;
            $stmtQ->execute([
                'quiz_id' => $quizId,
                'q_text'  => $qData['q'],
                'timer'   => $qData['timer'],
                'points'  => $qData['points'],
                'order_num' => $order
            ]);
            $qId = $pdo->lastInsertId();

            foreach ($qData['options'] as $opt) {
                $stmtOpt->execute([
                    'question_id' => $qId,
                    'key'         => $opt['key'],
                    'text'        => $opt['text'],
                    'is_correct'  => $opt['correct']
                ]);
            }
        }
        echo "<p style='color:#34D399;'>✅ Inserted Demo Questions with options.</p>";
    }

    echo "<hr style='border:0; border-top:1px solid rgba(255,255,255,0.1); margin:1.5rem 0;'>";
    echo "<h3 style='color:#38BDF8; margin-bottom:1rem;'>🎉 QUIZLY Ready to Launch!</h3>";
    echo "<p><a href='login.php' style='display:inline-block; padding:0.75rem 1.5rem; background:#0284C7; color:#FFF; text-decoration:none; border-radius:8px; font-weight:bold; margin-right:1rem;'>👉 Go to Teacher Login</a>";
    echo "<a href='join.php' style='display:inline-block; padding:0.75rem 1.5rem; background:#334155; color:#FFF; text-decoration:none; border-radius:8px; font-weight:bold;'>👉 Join as Student</a></p>";
    echo "</div>";

} catch (Exception $e) {
    echo "<h3 style='color:#EF4444;'>❌ Installation Issue: " . htmlspecialchars($e->getMessage()) . "</h3>";
    echo "</div>";
}
