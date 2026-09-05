<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/scoring.php';

$nowMs = (int)round(microtime(true) * 1000);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!$input) {
    $input = $_POST;
}

$token = $_SERVER['HTTP_X_PARTICIPANT_TOKEN'] ?? $input['participant_token'] ?? $_SESSION['participant_token'] ?? '';
$selectedOptionKey = strtoupper(trim(sanitizeInput($input['selected_option_key'] ?? $input['option'] ?? '')));

if (empty($token)) {
    jsonError('Participant authentication token missing', 'UNAUTHORIZED', 401);
}

if (!in_array($selectedOptionKey, ['A', 'B', 'C', 'D'], true)) {
    jsonError('Invalid option selected', 'INVALID_OPTION', 400);
}

$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT 
        p.id AS participant_id, 
        p.session_id, 
        p.nickname,
        p.streak_count,
        s.session_status,
        s.current_question_id,
        s.question_started_at_ms,
        s.question_ends_at_ms
    FROM participants p
    JOIN quiz_sessions s ON p.session_id = s.id
    WHERE p.participant_token = :token
");
$stmt->execute(['token' => $token]);
$pData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pData) {
    jsonError('Invalid participant token or session expired', 'INVALID_TOKEN', 401);
}

$sessionId = (int)$pData['session_id'];
$participantId = (int)$pData['participant_id'];
$currentQuestionId = (int)$pData['current_question_id'];

if ($pData['session_status'] !== 'QUESTION_ACTIVE' || !$currentQuestionId) {
    jsonError('No question is currently accepting answers', 'QUESTION_NOT_ACTIVE', 400);
}

$startedMs = (int)$pData['question_started_at_ms'];
if ($nowMs < $startedMs) {
    jsonError('Get Ready! Question starts in a moment...', 'COUNTDOWN_ACTIVE', 400);
}

$allowedEndsMs = (int)$pData['question_ends_at_ms'] + 1000;
if ($nowMs > $allowedEndsMs) {
    jsonError('Time up! This question has ended.', 'QUESTION_ENDED', 400);
}

$stmtQ = $db->prepare("
    SELECT q.timer_seconds, q.max_points, o.option_key AS correct_option_key
    FROM questions q
    LEFT JOIN question_options o ON q.id = o.question_id AND o.is_correct = 1
    WHERE q.id = :q_id
");
$stmtQ->execute(['q_id' => $currentQuestionId]);
$qData = $stmtQ->fetch(PDO::FETCH_ASSOC);

if (!$qData) {
    jsonError('Question details not found', 'NOT_FOUND', 404);
}

$timerSeconds = (int)($qData['timer_seconds'] ?: DEFAULT_QUESTION_TIMER);
$maxPoints = (int)($qData['max_points'] ?: DEFAULT_MAX_POINTS);
$correctOptionKey = $qData['correct_option_key'];

$responseTimeMs = max(0, $nowMs - $startedMs);
$isCorrect = ($selectedOptionKey === $correctOptionKey) ? 1 : 0;

$baseScore = calculateAnswerScore(($isCorrect === 1), $responseTimeMs, $timerSeconds, $maxPoints);

// Calculate Streak & Streak Bonus
$currentStreak = (int)($pData['streak_count'] ?? 0);
$newStreak = ($isCorrect === 1) ? ($currentStreak + 1) : 0;
$streakBonus = ($isCorrect === 1) ? calculateStreakBonus($newStreak) : 0;

$totalScoreEarned = $baseScore + $streakBonus;

try {
    $db->beginTransaction();

    $stmtCheck = $db->prepare("
        SELECT id FROM answers 
        WHERE session_id = :session_id 
          AND participant_id = :participant_id 
          AND question_id = :question_id
    ");
    $stmtCheck->execute([
        'session_id' => $sessionId,
        'participant_id' => $participantId,
        'question_id' => $currentQuestionId
    ]);

    if ($stmtCheck->fetchColumn()) {
        $db->rollBack();
        jsonError('You have already submitted an answer for this question', 'DUPLICATE_ANSWER', 409);
    }

    $stmtIns = $db->prepare("
        INSERT INTO answers (
            session_id, participant_id, question_id, selected_option_key, 
            submitted_at_ms, response_time_ms, is_correct, score_earned, streak_bonus
        ) VALUES (
            :session_id, :participant_id, :question_id, :option_key,
            :sub_ms, :resp_ms, :is_correct, :score, :streak_bonus
        )
    ");
    $stmtIns->execute([
        'session_id' => $sessionId,
        'participant_id' => $participantId,
        'question_id' => $currentQuestionId,
        'option_key' => $selectedOptionKey,
        'sub_ms' => $nowMs,
        'resp_ms' => $responseTimeMs,
        'is_correct' => $isCorrect,
        'score' => $totalScoreEarned,
        'streak_bonus' => $streakBonus
    ]);

    $stmtUpd = $db->prepare("
        UPDATE participants 
        SET total_score = total_score + :score,
            correct_count = correct_count + :is_correct,
            streak_count = :new_streak,
            cumulative_time_ms = cumulative_time_ms + :resp_ms
        WHERE id = :participant_id
    ");
    $stmtUpd->execute([
        'score' => $totalScoreEarned,
        'is_correct' => $isCorrect,
        'new_streak' => $newStreak,
        'resp_ms' => $responseTimeMs,
        'participant_id' => $participantId
    ]);

    $db->commit();

    jsonSuccess([
        'question_id' => $currentQuestionId,
        'selected_option' => $selectedOptionKey,
        'is_correct' => (bool)$isCorrect,
        'base_score' => $baseScore,
        'streak_bonus' => $streakBonus,
        'streak_count' => $newStreak,
        'total_score_earned' => $totalScoreEarned,
        'response_time_ms' => $responseTimeMs,
        'formatted_time' => sprintf('%.2fs', $responseTimeMs / 1000.0)
    ], 'Answer recorded');

} catch (PDOException $e) {
    $db->rollBack();
    if ($e->getCode() == 23000) {
        jsonError('You have already submitted an answer for this question', 'DUPLICATE_ANSWER', 409);
    }
    jsonError('Failed to record answer: ' . $e->getMessage(), 'SERVER_ERROR', 500);
}
