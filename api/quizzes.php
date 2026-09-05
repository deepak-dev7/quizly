<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = (int)$user['organization_id'];
$db = Database::getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// 1. LIST QUIZZES FOR ORG
if ($action === 'list') {
    $stmt = $db->prepare("
        SELECT 
            q.id, q.title, q.description, q.category, q.difficulty, q.status, q.created_at, q.updated_at,
            (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count,
            u.name AS creator_name
        FROM quizzes q
        JOIN users u ON q.creator_id = u.id
        WHERE q.organization_id = :org_id
        ORDER BY q.updated_at DESC
    ");
    $stmt->execute(['org_id' => $orgId]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonSuccess($quizzes);
}

// 2. GET SINGLE QUIZ DETAILS WITH QUESTIONS & OPTIONS
if ($action === 'get') {
    $quizId = (int)($_GET['id'] ?? 0);
    if (!$quizId) {
        jsonError('Quiz ID required', 'INVALID_INPUT', 400);
    }

    $stmt = $db->prepare("
        SELECT * FROM quizzes 
        WHERE id = :quiz_id AND organization_id = :org_id
    ");
    $stmt->execute(['quiz_id' => $quizId, 'org_id' => $orgId]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        jsonError('Quiz not found or unauthorized access', 'NOT_FOUND', 404);
    }

    // Fetch Questions
    $stmtQ = $db->prepare("
        SELECT * FROM questions 
        WHERE quiz_id = :quiz_id 
        ORDER BY order_num ASC, id ASC
    ");
    $stmtQ->execute(['quiz_id' => $quizId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Options for each Question
    $stmtOpt = $db->prepare("
        SELECT * FROM question_options 
        WHERE question_id = :question_id 
        ORDER BY option_key ASC
    ");
    
    foreach ($questions as &$q) {
        $stmtOpt->execute(['question_id' => $q['id']]);
        $q['options'] = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
    }

    $quiz['questions'] = $questions;

    jsonSuccess($quiz);
}

// 3. CREATE OR UPDATE QUIZ WITH QUESTIONS
if ($action === 'save') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if (!$input) {
        $input = $_POST;
    }

    $quizId = isset($input['id']) ? (int)$input['id'] : 0;
    $title = sanitizeInput($input['title'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $category = sanitizeInput($input['category'] ?? 'General');
    $difficulty = strtoupper(sanitizeInput($input['difficulty'] ?? 'MEDIUM'));
    $status = strtoupper(sanitizeInput($input['status'] ?? 'PUBLISHED'));
    $questionsData = $input['questions'] ?? [];

    if (empty($title)) {
        jsonError('Quiz title is required', 'INVALID_INPUT', 400);
    }

    if (!in_array($difficulty, ['EASY', 'MEDIUM', 'HARD'], true)) {
        $difficulty = 'MEDIUM';
    }

    if (!in_array($status, ['DRAFT', 'PUBLISHED', 'ARCHIVED'], true)) {
        $status = 'DRAFT';
    }

    try {
        $db->beginTransaction();

        if ($quizId > 0) {
            // Verify ownership
            $stmt = $db->prepare("SELECT id FROM quizzes WHERE id = :id AND organization_id = :org_id");
            $stmt->execute(['id' => $quizId, 'org_id' => $orgId]);
            if (!$stmt->fetchColumn()) {
                jsonError('Quiz not found or unauthorized', 'UNAUTHORIZED', 403);
            }

            $nowStr = date('Y-m-d H:i:s');
            $stmt = $db->prepare("
                UPDATE quizzes 
                SET title = :title, description = :desc, category = :cat, difficulty = :diff, status = :status, updated_at = :updated_at
                WHERE id = :id AND organization_id = :org_id
            ");
            $stmt->execute([
                'title' => $title,
                'desc' => $description,
                'cat' => $category,
                'diff' => $difficulty,
                'status' => $status,
                'updated_at' => $nowStr,
                'id' => $quizId,
                'org_id' => $orgId
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO quizzes (organization_id, creator_id, title, description, category, difficulty, status)
                VALUES (:org_id, :creator_id, :title, :desc, :cat, :diff, :status)
            ");
            $stmt->execute([
                'org_id' => $orgId,
                'creator_id' => $user['id'],
                'title' => $title,
                'desc' => $description,
                'cat' => $category,
                'diff' => $difficulty,
                'status' => $status
            ]);
            $quizId = (int)$db->lastInsertId();
        }

        // Handle Questions update if provided
        if (!empty($questionsData) && is_array($questionsData)) {
            // Remove old question options and questions to replace with updated list
            $db->prepare("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE quiz_id = :quiz_id)")->execute(['quiz_id' => $quizId]);
            $stmtDel = $db->prepare("DELETE FROM questions WHERE quiz_id = :quiz_id");
            $stmtDel->execute(['quiz_id' => $quizId]);

            $stmtQ = $db->prepare("
                INSERT INTO questions (quiz_id, question_text, timer_seconds, max_points, order_num)
                VALUES (:quiz_id, :q_text, :timer, :points, :order_num)
            ");

            $stmtOpt = $db->prepare("
                INSERT INTO question_options (question_id, option_key, option_text, is_correct)
                VALUES (:question_id, :key, :text, :is_correct)
            ");

            foreach ($questionsData as $idx => $q) {
                $qText = sanitizeInput($q['question_text'] ?? '');
                if (empty($qText)) continue;

                $timer = max(5, min(120, (int)($q['timer_seconds'] ?? 20)));
                $points = max(100, min(5000, (int)($q['max_points'] ?? 1000)));

                $stmtQ->execute([
                    'quiz_id' => $quizId,
                    'q_text' => $qText,
                    'timer' => $timer,
                    'points' => $points,
                    'order_num' => $idx + 1
                ]);
                $qId = (int)$db->lastInsertId();

                $options = $q['options'] ?? [];
                foreach (['A', 'B', 'C', 'D'] as $key) {
                    $optText = sanitizeInput($options[$key]['text'] ?? $options[$key] ?? '');
                    $isCorrect = (!empty($options[$key]['is_correct']) || ($q['correct_option'] ?? '') === $key) ? 1 : 0;

                    $stmtOpt->execute([
                        'question_id' => $qId,
                        'key' => $key,
                        'text' => $optText ?: "Option $key",
                        'is_correct' => $isCorrect
                    ]);
                }
            }
        }

        $db->commit();
        jsonSuccess(['quiz_id' => $quizId], 'Quiz saved successfully');
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('Failed to save quiz: ' . $e->getMessage(), 'SERVER_ERROR', 500);
    }
}

// 4. DELETE QUIZ
if ($action === 'delete') {
    $rawInput = json_decode(file_get_contents('php://input'), true);
    $quizId = (int)($_POST['id'] ?? $_GET['id'] ?? $rawInput['id'] ?? 0);
    if (!$quizId) {
        jsonError('Quiz ID required', 'INVALID_INPUT', 400);
    }

    // Check ownership
    $stmtCheck = $db->prepare("SELECT id FROM quizzes WHERE id = :id AND organization_id = :org_id");
    $stmtCheck->execute(['id' => $quizId, 'org_id' => $orgId]);
    if (!$stmtCheck->fetchColumn()) {
        jsonError('Quiz not found or unauthorized', 'NOT_FOUND', 404);
    }

    try {
        $db->beginTransaction();

        // 1. Delete questions and their options
        $stmtQ = $db->prepare("SELECT id FROM questions WHERE quiz_id = :quiz_id");
        $stmtQ->execute(['quiz_id' => $quizId]);
        $qIds = $stmtQ->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($qIds)) {
            $qPlaceholders = implode(',', array_fill(0, count($qIds), '?'));
            // Delete options
            $db->prepare("DELETE FROM question_options WHERE question_id IN ($qPlaceholders)")->execute($qIds);
            // Delete answers
            $db->prepare("DELETE FROM answers WHERE question_id IN ($qPlaceholders)")->execute($qIds);
            // Delete questions
            $db->prepare("DELETE FROM questions WHERE quiz_id = ?")->execute([$quizId]);
        }

        // 2. Delete sessions, participants, answers
        $stmtS = $db->prepare("SELECT id FROM quiz_sessions WHERE quiz_id = :quiz_id");
        $stmtS->execute(['quiz_id' => $quizId]);
        $sIds = $stmtS->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($sIds)) {
            $sPlaceholders = implode(',', array_fill(0, count($sIds), '?'));
            $db->prepare("DELETE FROM answers WHERE session_id IN ($sPlaceholders)")->execute($sIds);
            $db->prepare("DELETE FROM participants WHERE session_id IN ($sPlaceholders)")->execute($sIds);
            $db->prepare("DELETE FROM quiz_sessions WHERE quiz_id = ?")->execute([$quizId]);
        }

        // 3. Delete the quiz
        $stmt = $db->prepare("DELETE FROM quizzes WHERE id = :id AND organization_id = :org_id");
        $stmt->execute(['id' => $quizId, 'org_id' => $orgId]);

        $db->commit();
        jsonSuccess(['quiz_id' => $quizId], 'Quiz deleted successfully');
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('Failed to delete quiz: ' . $e->getMessage(), 'SERVER_ERROR', 500);
    }
}

// 4b. DELETE QUESTION
if ($action === 'delete_question') {
    $rawInput = json_decode(file_get_contents('php://input'), true);
    $questionId = (int)($_POST['question_id'] ?? $_POST['id'] ?? $_GET['question_id'] ?? $_GET['id'] ?? $rawInput['question_id'] ?? $rawInput['id'] ?? 0);
    if (!$questionId) {
        jsonError('Question ID required', 'INVALID_INPUT', 400);
    }

    // Verify ownership by checking quiz organization
    $stmtCheck = $db->prepare("
        SELECT q.id, q.quiz_id, qz.title 
        FROM questions q 
        JOIN quizzes qz ON q.quiz_id = qz.id 
        WHERE q.id = :qid AND qz.organization_id = :org_id
    ");
    $stmtCheck->execute(['qid' => $questionId, 'org_id' => $orgId]);
    $question = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$question) {
        jsonError('Question not found or unauthorized', 'NOT_FOUND', 404);
    }

    try {
        $db->beginTransaction();

        // 1. Delete options
        $stmtOpt = $db->prepare("DELETE FROM question_options WHERE question_id = :qid");
        $stmtOpt->execute(['qid' => $questionId]);

        // 2. Delete answers referencing this question
        $stmtAns = $db->prepare("DELETE FROM answers WHERE question_id = :qid");
        $stmtAns->execute(['qid' => $questionId]);

        // 3. Detach active sessions on this question
        $stmtSess = $db->prepare("UPDATE quiz_sessions SET current_question_id = NULL WHERE current_question_id = :qid");
        $stmtSess->execute(['qid' => $questionId]);

        // 4. Delete the question itself
        $stmtDel = $db->prepare("DELETE FROM questions WHERE id = :qid");
        $stmtDel->execute(['qid' => $questionId]);

        $db->commit();
        jsonSuccess(['question_id' => $questionId, 'quiz_id' => (int)$question['quiz_id']], 'Question deleted successfully');
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('Failed to delete question: ' . $e->getMessage(), 'SERVER_ERROR', 500);
    }
}

// 5. LIST QUESTIONS FROM QUESTION BANK
if ($action === 'question_bank') {
    $search = trim($_GET['q'] ?? '');
    $sql = "
        SELECT q.id, q.question_text, q.timer_seconds, q.max_points, q.question_type, q.difficulty, q.topic, q.ai_generated,
               qz.title AS quiz_title
        FROM questions q
        JOIN quizzes qz ON q.quiz_id = qz.id
        WHERE qz.organization_id = :org_id
    ";
    $params = ['org_id' => $orgId];
    if (!empty($search)) {
        $sql .= " AND (q.question_text LIKE :search OR q.topic LIKE :search OR qz.title LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    $sql .= " ORDER BY q.id DESC LIMIT 100";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch options for each question
    $stmtOpt = $db->prepare("SELECT option_key, option_text, is_correct FROM question_options WHERE question_id = :q_id ORDER BY option_key ASC");
    foreach ($questions as &$q) {
        $stmtOpt->execute(['q_id' => $q['id']]);
        $opts = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
        $optsMap = [];
        $correctOpt = 'A';
        foreach ($opts as $o) {
            $optsMap[$o['option_key']] = [
                'text' => $o['option_text'],
                'is_correct' => (int)$o['is_correct'] === 1
            ];
            if ((int)$o['is_correct'] === 1) {
                $correctOpt = $o['option_key'];
            }
        }
        $q['options'] = $optsMap;
        $q['correct_option'] = $correctOpt;
    }

    jsonSuccess($questions);
}

jsonError('Invalid action', 'INVALID_ACTION', 400);
