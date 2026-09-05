<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/scoring.php';

$nowMs = (int)round(microtime(true) * 1000);
$db = Database::getConnection();

$sessionId = (int)($_GET['session_id'] ?? $_POST['session_id'] ?? 0);
$roomCode = trim(sanitizeInput($_GET['room_code'] ?? $_POST['room_code'] ?? ''));
$participantToken = $_SERVER['HTTP_X_PARTICIPANT_TOKEN'] ?? $_GET['participant_token'] ?? $_POST['participant_token'] ?? $_SESSION['participant_token'] ?? '';

$participant = null;
if (!empty($participantToken)) {
    $stmtP = $db->prepare("SELECT * FROM participants WHERE participant_token = :token");
    $stmtP->execute(['token' => $participantToken]);
    $participant = $stmtP->fetch(PDO::FETCH_ASSOC);
    if ($participant && !$sessionId) {
        $sessionId = (int)$participant['session_id'];
    }
}

if (!$sessionId && !empty($roomCode)) {
    $stmtCode = $db->prepare("SELECT id FROM quiz_sessions WHERE room_code = :code ORDER BY id DESC LIMIT 1");
    $stmtCode->execute(['code' => $roomCode]);
    $sessionId = (int)$stmtCode->fetchColumn();
}

if (!$sessionId) {
    jsonError('Session identifier required', 'INVALID_INPUT', 400);
}

// Fetch session info with Organization details
$stmtS = $db->prepare("
    SELECT 
        s.id AS session_id,
        s.organization_id,
        s.quiz_id,
        s.room_code,
        s.session_status,
        s.current_question_id,
        s.question_started_at_ms,
        s.question_ends_at_ms,
        q.title AS quiz_title,
        o.name AS organization_name,
        (SELECT COUNT(*) FROM questions WHERE quiz_id = s.quiz_id) AS total_questions
    FROM quiz_sessions s
    JOIN quizzes q ON s.quiz_id = q.id
    JOIN organizations o ON s.organization_id = o.id
    WHERE s.id = :session_id
");
$stmtS->execute(['session_id' => $sessionId]);
$session = $stmtS->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    jsonError('Session not found', 'NOT_FOUND', 404);
}

// Auto-transition to QUESTION_RESULTS if timer expired
if ($session['session_status'] === 'QUESTION_ACTIVE' && $session['question_ends_at_ms']) {
    if ($nowMs >= (int)$session['question_ends_at_ms']) {
        $stmtAuto = $db->prepare("UPDATE quiz_sessions SET session_status = 'QUESTION_RESULTS' WHERE id = :id AND session_status = 'QUESTION_ACTIVE'");
        $stmtAuto->execute(['id' => $sessionId]);
        $session['session_status'] = 'QUESTION_RESULTS';
    }
}

// Fetch list of all joined participants for live real-time room updating
$stmtParts = $db->prepare("
    SELECT id, nickname, avatar, joined_at 
    FROM participants 
    WHERE session_id = :session_id 
    ORDER BY id ASC
");
$stmtParts->execute(['session_id' => $sessionId]);
$participantsList = $stmtParts->fetchAll(PDO::FETCH_ASSOC);
$participantCount = count($participantsList);

$currentQuestionId = (int)$session['current_question_id'];
$answeredCount = 0;
$questionData = null;
$distribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];

if ($currentQuestionId) {
    $stmtAnsCount = $db->prepare("SELECT COUNT(*) FROM answers WHERE session_id = :session_id AND question_id = :q_id");
    $stmtAnsCount->execute(['session_id' => $sessionId, 'q_id' => $currentQuestionId]);
    $answeredCount = (int)$stmtAnsCount->fetchColumn();

    $stmtQ = $db->prepare("SELECT * FROM questions WHERE id = :q_id");
    $stmtQ->execute(['q_id' => $currentQuestionId]);
    $qRow = $stmtQ->fetch(PDO::FETCH_ASSOC);

    if ($qRow) {
        $stmtOpts = $db->prepare("SELECT option_key, option_text, is_correct FROM question_options WHERE question_id = :q_id ORDER BY option_key ASC");
        $stmtOpts->execute(['q_id' => $currentQuestionId]);
        $rawOptions = $stmtOpts->fetchAll(PDO::FETCH_ASSOC);

        $options = [];
        $correctOptionKey = '';
        $correctOptionText = '';
        foreach ($rawOptions as $opt) {
            $key = $opt['option_key'];
            $options[$key] = $opt['option_text'];
            if ((int)$opt['is_correct'] === 1) {
                $correctOptionKey = $key;
                $correctOptionText = $opt['option_text'];
            }
        }

        $startedMs = (int)$session['question_started_at_ms'];
        $isCountingDown = ($nowMs < $startedMs);
        $getReadySeconds = $isCountingDown ? (int)ceil(($startedMs - $nowMs) / 1000.0) : 0;

        $remainingMs = max(0, (int)$session['question_ends_at_ms'] - $nowMs);
        $remainingSeconds = (int)ceil($remainingMs / 1000.0);

        $questionData = [
            'question_id' => (int)$qRow['id'],
            'order_num' => (int)$qRow['order_num'],
            'question_text' => $qRow['question_text'],
            'image_url' => $qRow['image_url'],
            'timer_seconds' => (int)$qRow['timer_seconds'],
            'max_points' => (int)$qRow['max_points'],
            'is_counting_down' => $isCountingDown,
            'get_ready_seconds' => $getReadySeconds,
            'remaining_seconds' => $remainingSeconds,
            'remaining_ms' => $remainingMs,
            'options' => $options
        ];

        if (in_array($session['session_status'], ['QUESTION_RESULTS', 'LEADERBOARD', 'COMPLETED'], true)) {
            $questionData['correct_option_key'] = $correctOptionKey;
            $questionData['correct_option_text'] = $correctOptionText;
        }

        $stmtDist = $db->prepare("
            SELECT selected_option_key, COUNT(*) AS cnt 
            FROM answers 
            WHERE session_id = :session_id AND question_id = :q_id 
            GROUP BY selected_option_key
        ");
        $stmtDist->execute(['session_id' => $sessionId, 'q_id' => $currentQuestionId]);
        while ($row = $stmtDist->fetch(PDO::FETCH_ASSOC)) {
            $distribution[$row['selected_option_key']] = (int)$row['cnt'];
        }
    }
}

// Student-specific state
$studentState = null;
if ($participant) {
    $hasSubmitted = false;
    $submittedOption = null;
    $scoreEarned = 0;
    $isCorrect = null;
    $responseTimeMs = 0;

    if ($currentQuestionId) {
        $stmtMyAns = $db->prepare("
            SELECT selected_option_key, score_earned, is_correct, streak_bonus, response_time_ms 
            FROM answers 
            WHERE session_id = :session_id AND participant_id = :p_id AND question_id = :q_id
        ");
        $stmtMyAns->execute([
            'session_id' => $sessionId,
            'p_id' => $participant['id'],
            'q_id' => $currentQuestionId
        ]);
        $myAns = $stmtMyAns->fetch(PDO::FETCH_ASSOC);

        if ($myAns) {
            $hasSubmitted = true;
            $submittedOption = $myAns['selected_option_key'];
            $scoreEarned = (int)$myAns['score_earned'];
            $isCorrect = (bool)$myAns['is_correct'];
            $streakBonus = (int)($myAns['streak_bonus'] ?? 0);
            $responseTimeMs = (int)$myAns['response_time_ms'];
        } else {
            $isCorrect = false;
            $streakBonus = 0;
        }
    }

    $leaderboardAll = getSessionLeaderboard($db, $sessionId, 500);
    $myRank = null;
    foreach ($leaderboardAll as $entry) {
        if ((int)$entry['participant_id'] === (int)$participant['id']) {
            $myRank = $entry['rank'];
            break;
        }
    }

    $studentState = [
        'participant_id' => (int)$participant['id'],
        'nickname' => $participant['nickname'],
        'avatar' => $participant['avatar'] ?? $participant['nickname'],
        'total_score' => (int)$participant['total_score'],
        'correct_count' => (int)$participant['correct_count'],
        'rank' => $myRank,
        'has_submitted' => $hasSubmitted,
        'submitted_option' => $submittedOption,
        'score_earned' => $scoreEarned,
        'is_correct' => $isCorrect,
        'streak_bonus' => $streakBonus ?? 0,
        'response_time_ms' => $responseTimeMs,
        'formatted_time' => sprintf('%.3fs', $responseTimeMs / 1000.0)
    ];
}

$leaderboard = [];
$questionLeaderboard = [];

if (in_array($session['session_status'], ['QUESTION_RESULTS', 'LEADERBOARD', 'COMPLETED'], true)) {
    $leaderboard = getSessionLeaderboard($db, $sessionId, 20);
    if ($currentQuestionId) {
        $questionLeaderboard = getQuestionLeaderboard($db, $sessionId, $currentQuestionId, 10);
    }
}

jsonSuccess([
    'session_id' => (int)$session['session_id'],
    'room_code' => $session['room_code'],
    'status' => $session['session_status'],
    'organization_name' => $session['organization_name'],
    'quiz_title' => $session['quiz_title'],
    'total_questions' => (int)$session['total_questions'],
    'participant_count' => $participantCount,
    'participants' => $participantsList,
    'answered_count' => $answeredCount,
    'question' => $questionData,
    'distribution' => $distribution,
    'student' => $studentState,
    'leaderboard' => $leaderboard,
    'question_leaderboard' => $questionLeaderboard
]);
