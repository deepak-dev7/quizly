<?php
// QUIZLY API — Save Approved Questions to Question Bank Endpoint
// Validates approved questions, prevents duplicates, executes database transaction

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../services/GeminiClient.php';
require_once __DIR__ . '/../../services/AIQuestionGenerator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$user = requireRole(['PLATFORM_ADMIN', 'ORG_OWNER', 'TEACHER']);
$orgId = (int)($user['organization_id'] ?? 0);
if ($orgId <= 0) {
    jsonError('Invalid organization context', 'FORBIDDEN', 403);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? $_POST['csrf_token'] ?? null;
if (!verifyCsrfToken($csrfToken)) {
    jsonError('Security verification failed (Invalid CSRF Token). Please refresh the page.', 'CSRF_INVALID', 403);
}

$questions = $input['questions'] ?? [];
if (!is_array($questions) || empty($questions)) {
    jsonError('No questions provided for saving.', 'INVALID_INPUT', 400);
}

$targetQuizId = isset($input['target_quiz_id']) ? (int)$input['target_quiz_id'] : null;
$newQuizTitle = isset($input['new_quiz_title']) ? trim((string)$input['new_quiz_title']) : null;

try {
    $db = Database::getConnection();
    $generator = new AIQuestionGenerator(new GeminiClient(), $db);

    $result = $generator->saveApprovedQuestions(
        $questions,
        $orgId,
        (int)$user['id'],
        $targetQuizId,
        $newQuizTitle
    );

    logAuditAction(
        $db,
        'AI_QUESTIONS_SAVED',
        "Saved {$result['saved_count']} AI questions to Quiz ID {$result['quiz_id']}",
        (int)$user['id'],
        $orgId
    );

    jsonSuccess([
        'saved_count' => $result['saved_count'],
        'quiz_id' => $result['quiz_id']
    ], "{$result['saved_count']} questions added to Question Bank");
} catch (Throwable $e) {
    error_log("Save AI Questions Error: " . $e->getMessage());
    jsonError('Failed to save questions: ' . $e->getMessage(), 'SAVE_FAILED', 500);
}
