<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$sessionId) {
    die("Session ID required.");
}

$db = Database::getConnection();

// Verify Organization ownership
$stmtS = $db->prepare("
    SELECT s.id, s.room_code, s.created_at, q.title AS quiz_title
    FROM quiz_sessions s
    JOIN quizzes q ON s.quiz_id = q.id
    WHERE s.id = :session_id AND s.organization_id = :org_id
");
$stmtS->execute(['session_id' => $sessionId, 'org_id' => $orgId]);
$session = $stmtS->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die("Session not found or access denied.");
}

// Fetch session leaderboards with Department and Class
$stmtP = $db->prepare("
    SELECT 
        p.id,
        p.nickname,
        p.department,
        p.class_name,
        p.total_score,
        p.correct_count,
        (SELECT COUNT(*) FROM answers WHERE participant_id = p.id) AS answered_count,
        p.cumulative_time_ms
    FROM participants p
    WHERE p.session_id = :session_id
    ORDER BY p.total_score DESC, p.correct_count DESC, p.cumulative_time_ms ASC
");
$stmtP->execute(['session_id' => $sessionId]);
$participants = $stmtP->fetchAll(PDO::FETCH_ASSOC);

// Output UTF-8 BOM CSV File Download
$filename = "Quizly_Results_Session_" . $session['room_code'] . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fputs($output, "\xEF\xBB\xBF");

// CSV Header Row
fputcsv($output, [
    'Rank',
    'Nickname',
    'Department',
    'Class / Section',
    'Total Score (pts)',
    'Correct Answers',
    'Total Answered',
    'Accuracy Rate (%)',
    'Avg Response Time (s)'
]);

$rank = 1;
foreach ($participants as $p) {
    $totalAns = max(1, (int)$p['answered_count']);
    $accuracyPct = round(((int)$p['correct_count'] / $totalAns) * 100, 1);
    $avgTimeSec = sprintf('%.3f', ((int)$p['cumulative_time_ms'] / 1000.0) / $totalAns);

    fputcsv($output, [
        $rank++,
        $p['nickname'],
        $p['department'] ?: 'N/A',
        $p['class_name'] ?: 'N/A',
        $p['total_score'],
        $p['correct_count'],
        $p['answered_count'],
        $accuracyPct . '%',
        $avgTimeSec . 's'
    ]);
}

fclose($output);
exit;
