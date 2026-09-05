<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/roomcode.php';

$user = requireLogin();
$orgId = (int)$user['organization_id'];
$db = Database::getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. START NEW LIVE SESSION
if ($action === 'start') {
    $quizId = (int)($_POST['quiz_id'] ?? $_GET['quiz_id'] ?? 0);
    if (!$quizId) {
        jsonError('Quiz ID is required', 'INVALID_INPUT', 400);
    }

    $stmt = $db->prepare("
        SELECT id, title, status 
        FROM quizzes 
        WHERE id = :quiz_id AND organization_id = :org_id
    ");
    $stmt->execute(['quiz_id' => $quizId, 'org_id' => $orgId]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        jsonError('Quiz not found or unauthorized', 'NOT_FOUND', 404);
    }

    $roomCode = generateUniqueRoomCode();

    $stmt = $db->prepare("
        INSERT INTO quiz_sessions (organization_id, quiz_id, host_id, room_code, session_status)
        VALUES (:org_id, :quiz_id, :host_id, :room_code, 'WAITING')
    ");
    $stmt->execute([
        'org_id' => $orgId,
        'quiz_id' => $quizId,
        'host_id' => $user['id'],
        'room_code' => $roomCode
    ]);
    $sessionId = (int)$db->lastInsertId();

    jsonSuccess([
        'session_id' => $sessionId,
        'room_code' => $roomCode,
        'quiz_title' => $quiz['title'],
        'status' => 'WAITING'
    ], 'Live session created successfully');
}

// 1b. START LIVE TOPIC-WISE QUIZ DIRECTLY FROM QUESTION BANK
if ($action === 'start_topic') {
    $topic = trim((string)($_POST['topic'] ?? $_GET['topic'] ?? ''));
    if ($topic === '') {
        jsonError('Topic name is required', 'INVALID_INPUT', 400);
    }

    // 1. Fetch all questions in this organization matching this topic
    $stmtQ = $db->prepare("
        SELECT q.* 
        FROM questions q
        JOIN quizzes qz ON q.quiz_id = qz.id
        WHERE qz.organization_id = :org_id
          AND (q.topic = :topic OR (:topic = 'General / Untracked' AND (q.topic IS NULL OR TRIM(q.topic) = '' OR q.topic = 'General')))
        ORDER BY q.id ASC
    ");
    $stmtQ->execute(['org_id' => $orgId, 'topic' => $topic]);
    $topicQuestions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    if (empty($topicQuestions)) {
        jsonError("No questions found in Question Bank for topic: {$topic}", 'NOT_FOUND', 404);
    }

    // 2. Check if a dedicated published quiz already exists for this exact topic with matching question count
    $quizTitle = "{$topic} Quiz";
    $stmtFind = $db->prepare("
        SELECT id FROM quizzes 
        WHERE organization_id = :org_id 
          AND title = :title 
          AND status = 'PUBLISHED'
        ORDER BY id DESC LIMIT 1
    ");
    $stmtFind->execute(['org_id' => $orgId, 'title' => $quizTitle]);
    $existingQuizId = $stmtFind->fetchColumn();

    $targetQuizId = null;
    if ($existingQuizId) {
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = :qid");
        $stmtCount->execute(['qid' => $existingQuizId]);
        if ((int)$stmtCount->fetchColumn() === count($topicQuestions)) {
            $targetQuizId = (int)$existingQuizId;
        }
    }

    if (!$targetQuizId) {
        // Create new dedicated published quiz for this topic
        $stmtCreateQz = $db->prepare("
            INSERT INTO quizzes (organization_id, creator_id, title, description, category, difficulty, status)
            VALUES (:org_id, :creator_id, :title, :description, :category, 'MEDIUM', 'PUBLISHED')
        ");
        $stmtCreateQz->execute([
            'org_id' => $orgId,
            'creator_id' => $user['id'],
            'title' => $quizTitle,
            'description' => "Live topic quiz for {$topic} (" . count($topicQuestions) . " questions)",
            'category' => $topic
        ]);
        $targetQuizId = (int)$db->lastInsertId();

        $stmtInsertQ = $db->prepare("
            INSERT INTO questions (
                quiz_id, question_text, timer_seconds, max_points, order_num,
                question_type, difficulty, topic, explanation, learning_objective,
                ai_generated, generation_source, ai_model, generation_timestamp
            ) VALUES (
                :quiz_id, :question_text, :timer_seconds, :max_points, :order_num,
                :question_type, :difficulty, :topic, :explanation, :learning_objective,
                :ai_generated, :generation_source, :ai_model, :generation_timestamp
            )
        ");
        $stmtInsertOpt = $db->prepare("
            INSERT INTO question_options (question_id, option_key, option_text, is_correct)
            VALUES (:question_id, :option_key, :option_text, :is_correct)
        ");
        $stmtFetchOpts = $db->prepare("
            SELECT option_key, option_text, is_correct 
            FROM question_options 
            WHERE question_id = :qid 
            ORDER BY option_key ASC
        ");

        $orderNum = 1;
        foreach ($topicQuestions as $tq) {
            $stmtInsertQ->execute([
                'quiz_id' => $targetQuizId,
                'question_text' => $tq['question_text'],
                'timer_seconds' => $tq['timer_seconds'] ?: 20,
                'max_points' => $tq['max_points'] ?: 1000,
                'order_num' => $orderNum++,
                'question_type' => $tq['question_type'] ?? 'multiple_choice',
                'difficulty' => $tq['difficulty'] ?? 'medium',
                'topic' => $topic,
                'explanation' => $tq['explanation'] ?? null,
                'learning_objective' => $tq['learning_objective'] ?? null,
                'ai_generated' => $tq['ai_generated'] ?? 0,
                'generation_source' => $tq['generation_source'] ?? null,
                'ai_model' => $tq['ai_model'] ?? null,
                'generation_timestamp' => $tq['generation_timestamp'] ?? date('Y-m-d H:i:s')
            ]);
            $newQId = (int)$db->lastInsertId();

            $stmtFetchOpts->execute(['qid' => $tq['id']]);
            $opts = $stmtFetchOpts->fetchAll(PDO::FETCH_ASSOC);
            foreach ($opts as $opt) {
                $stmtInsertOpt->execute([
                    'question_id' => $newQId,
                    'option_key' => $opt['option_key'],
                    'option_text' => $opt['option_text'],
                    'is_correct' => $opt['is_correct']
                ]);
            }
        }
    }

    // 3. Start the live session
    $roomCode = generateUniqueRoomCode();

    $stmt = $db->prepare("
        INSERT INTO quiz_sessions (organization_id, quiz_id, host_id, room_code, session_status)
        VALUES (:org_id, :quiz_id, :host_id, :room_code, 'WAITING')
    ");
    $stmt->execute([
        'org_id' => $orgId,
        'quiz_id' => $targetQuizId,
        'host_id' => $user['id'],
        'room_code' => $roomCode
    ]);
    $sessionId = (int)$db->lastInsertId();

    jsonSuccess([
        'session_id' => $sessionId,
        'room_code' => $roomCode,
        'quiz_id' => $targetQuizId,
        'quiz_title' => $quizTitle,
        'question_count' => count($topicQuestions),
        'status' => 'WAITING'
    ], "Live session for {$topic} started successfully");
}

// 2. CONTROL SESSION STATE
$sessionId = (int)($_POST['session_id'] ?? $_GET['session_id'] ?? 0);
if (!$sessionId) {
    jsonError('Session ID is required', 'INVALID_INPUT', 400);
}

$stmt = $db->prepare("
    SELECT s.*, q.title AS quiz_title 
    FROM quiz_sessions s
    JOIN quizzes q ON s.quiz_id = q.id
    WHERE s.id = :session_id AND s.organization_id = :org_id
");
$stmt->execute(['session_id' => $sessionId, 'org_id' => $orgId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    jsonError('Session not found or unauthorized', 'NOT_FOUND', 404);
}

if ($action === 'start_question') {
    $questionId = (int)($_POST['question_id'] ?? $_GET['question_id'] ?? 0);

    if (!$questionId) {
        if (!empty($session['current_question_id'])) {
            $stmtCur = $db->prepare("SELECT order_num FROM questions WHERE id = :q_id");
            $stmtCur->execute(['q_id' => $session['current_question_id']]);
            $curOrder = (int)$stmtCur->fetchColumn();

            $stmtNext = $db->prepare("
                SELECT id FROM questions 
                WHERE quiz_id = :quiz_id AND order_num > :cur_order 
                ORDER BY order_num ASC LIMIT 1
            ");
            $stmtNext->execute(['quiz_id' => $session['quiz_id'], 'cur_order' => $curOrder]);
            $questionId = (int)$stmtNext->fetchColumn();
        } else {
            $stmtFirst = $db->prepare("
                SELECT id FROM questions 
                WHERE quiz_id = :quiz_id 
                ORDER BY order_num ASC LIMIT 1
            ");
            $stmtFirst->execute(['quiz_id' => $session['quiz_id']]);
            $questionId = (int)$stmtFirst->fetchColumn();
        }
    }

    if (!$questionId) {
        jsonError('No further questions available in this quiz', 'NO_MORE_QUESTIONS', 400);
    }

    $stmtQ = $db->prepare("SELECT timer_seconds FROM questions WHERE id = :q_id AND quiz_id = :quiz_id");
    $stmtQ->execute(['q_id' => $questionId, 'quiz_id' => $session['quiz_id']]);
    $timerSeconds = (int)$stmtQ->fetchColumn();

    if (!$timerSeconds) {
        $timerSeconds = DEFAULT_QUESTION_TIMER;
    }

    // 3-Second "GET READY" Countdown Delay
    $nowMs = (int)round(microtime(true) * 1000);
    $getReadyDelayMs = 3000;
    $startedMs = $nowMs + $getReadyDelayMs;
    $endsMs = $startedMs + ($timerSeconds * 1000);

    $stmtUpd = $db->prepare("
        UPDATE quiz_sessions 
        SET current_question_id = :q_id,
            session_status = 'QUESTION_ACTIVE',
            question_started_at_ms = :started_ms,
            question_ends_at_ms = :ends_ms
        WHERE id = :session_id
    ");
    $stmtUpd->execute([
        'q_id' => $questionId,
        'started_ms' => $startedMs,
        'ends_ms' => $endsMs,
        'session_id' => $sessionId
    ]);

    jsonSuccess([
        'session_id' => $sessionId,
        'question_id' => $questionId,
        'status' => 'QUESTION_ACTIVE',
        'get_ready_seconds' => 3,
        'started_at_ms' => $startedMs,
        'ends_at_ms' => $endsMs,
        'timer_seconds' => $timerSeconds
    ], 'Question countdown started (3s get ready)');
}

if ($action === 'end_question') {
    $stmtUpd = $db->prepare("
        UPDATE quiz_sessions 
        SET session_status = 'QUESTION_RESULTS' 
        WHERE id = :session_id
    ");
    $stmtUpd->execute(['session_id' => $sessionId]);

    jsonSuccess(['status' => 'QUESTION_RESULTS'], 'Question ended');
}

if ($action === 'show_leaderboard') {
    $stmtUpd = $db->prepare("
        UPDATE quiz_sessions 
        SET session_status = 'LEADERBOARD' 
        WHERE id = :session_id
    ");
    $stmtUpd->execute(['session_id' => $sessionId]);

    jsonSuccess(['status' => 'LEADERBOARD'], 'Showing leaderboard');
}

if ($action === 'end_quiz') {
    $stmtUpd = $db->prepare("
        UPDATE quiz_sessions 
        SET session_status = 'COMPLETED' 
        WHERE id = :session_id
    ");
    $stmtUpd->execute(['session_id' => $sessionId]);

    jsonSuccess(['status' => 'COMPLETED'], 'Quiz completed');
}

if ($action === 'cancel_quiz') {
    $stmtUpd = $db->prepare("
        UPDATE quiz_sessions 
        SET session_status = 'CANCELLED' 
        WHERE id = :session_id
    ");
    $stmtUpd->execute(['session_id' => $sessionId]);

    jsonSuccess(['status' => 'CANCELLED'], 'Quiz cancelled');
}

jsonError('Invalid action', 'INVALID_ACTION', 400);
