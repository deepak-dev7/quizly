<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = (int)$user['organization_id'];
$sessionId = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);

if (!$sessionId) {
    jsonError('Session ID is required', 'INVALID_INPUT', 400);
}

$db = Database::getConnection();

// Verify session belongs to user's org
$stmtS = $db->prepare("
    SELECT s.id, s.room_code, s.session_status, s.created_at, q.title AS quiz_title
    FROM quiz_sessions s
    JOIN quizzes q ON s.quiz_id = q.id
    WHERE s.id = :session_id AND s.organization_id = :org_id
");
$stmtS->execute(['session_id' => $sessionId, 'org_id' => $orgId]);
$session = $stmtS->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    jsonError('Session not found or unauthorized', 'NOT_FOUND', 404);
}

// Session Summary Stats
$stmtSummary = $db->prepare("
    SELECT 
        COUNT(DISTINCT p.id) AS total_participants,
        COALESCE(AVG(p.total_score), 0) AS avg_score,
        COALESCE(MAX(p.total_score), 0) AS max_score,
        COALESCE(AVG(a.response_time_ms), 0) AS avg_response_time_ms,
        COALESCE(MIN(CASE WHEN a.is_correct = 1 THEN a.response_time_ms END), 0) AS fastest_correct_ms,
        COUNT(a.id) AS total_answers_submitted,
        SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS total_correct_answers
    FROM participants p
    LEFT JOIN answers a ON p.session_id = a.session_id AND p.id = a.participant_id
    WHERE p.session_id = :session_id
");
$stmtSummary->execute(['session_id' => $sessionId]);
$summary = $stmtSummary->fetch(PDO::FETCH_ASSOC);

$totalAnswers = (int)$summary['total_answers_submitted'];
$totalCorrect = (int)$summary['total_correct_answers'];
$overallAccuracy = ($totalAnswers > 0) ? round(($totalCorrect / $totalAnswers) * 100, 1) : 0;

// Per-Question Breakdown
$stmtQuestions = $db->prepare("
    SELECT 
        q.id AS question_id,
        q.order_num,
        q.question_text,
        q.timer_seconds,
        q.max_points,
        COUNT(a.id) AS total_responses,
        SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) AS correct_responses,
        COALESCE(AVG(a.response_time_ms), 0) AS avg_response_time_ms,
        COALESCE(MIN(CASE WHEN a.is_correct = 1 THEN a.response_time_ms END), 0) AS fastest_response_ms
    FROM questions q
    JOIN quiz_sessions s ON q.quiz_id = s.quiz_id
    LEFT JOIN answers a ON a.session_id = s.id AND a.question_id = q.id
    WHERE s.id = :session_id
    GROUP BY q.id
    ORDER BY q.order_num ASC
");
$stmtQuestions->execute(['session_id' => $sessionId]);
$questionsBreakdown = $stmtQuestions->fetchAll(PDO::FETCH_ASSOC);

foreach ($questionsBreakdown as &$q) {
    $resp = (int)$q['total_responses'];
    $corr = (int)$q['correct_responses'];
    $q['accuracy_percentage'] = ($resp > 0) ? round(($corr / $resp) * 100, 1) : 0;
    $q['avg_response_time_formatted'] = sprintf('%.2fs', $q['avg_response_time_ms'] / 1000.0);
    $q['fastest_response_formatted'] = ($q['fastest_response_ms'] > 0) ? sprintf('%.3fs', $q['fastest_response_ms'] / 1000.0) : 'N/A';
}

jsonSuccess([
    'session' => $session,
    'summary' => [
        'total_participants' => (int)$summary['total_participants'],
        'avg_score' => round((float)$summary['avg_score'], 1),
        'max_score' => (int)$summary['max_score'],
        'avg_response_time_formatted' => sprintf('%.2fs', $summary['avg_response_time_ms'] / 1000.0),
        'fastest_correct_formatted' => ($summary['fastest_correct_ms'] > 0) ? sprintf('%.3fs', $summary['fastest_correct_ms'] / 1000.0) : 'N/A',
        'overall_accuracy_percentage' => $overallAccuracy
    ],
    'questions' => $questionsBreakdown
]);
