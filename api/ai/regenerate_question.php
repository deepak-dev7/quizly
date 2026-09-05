<?php
// QUIZLY API — Regenerate Single Question Endpoint
// Regenerates a specific question with context and feedback

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../services/GeminiClient.php';
require_once __DIR__ . '/../../services/AIQuestionGenerator.php';

@set_time_limit(60);

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

$context = $input['context'] ?? [];
$question = $input['question'] ?? [];
$instructions = trim((string)($input['instructions'] ?? ''));

if (empty($question) || empty($question['question_text'])) {
    jsonError('Invalid question payload for regeneration.', 'INVALID_INPUT', 400);
}

try {
    $db = Database::getConnection();
    $generator = new AIQuestionGenerator(new GeminiClient(), $db);

    $regenerated = $generator->regenerateQuestion($context, $question, $instructions, $orgId);

    logAuditAction(
        $db,
        'AI_REGENERATE_QUESTION',
        "Regenerated question: {$question['question_text']}",
        (int)$user['id'],
        $orgId
    );

    jsonSuccess([
        'question' => $regenerated
    ], 'Question regenerated successfully');
} catch (Throwable $e) {
    $safeErrMsg = preg_replace('/[a-zA-Z0-9_\-\.]{30,}/', '***', $e->getMessage());
    error_log("Gemini AI Regeneration Error: " . $safeErrMsg);
    jsonError($e->getMessage(), 'REGENERATION_FAILED', 400);
}
