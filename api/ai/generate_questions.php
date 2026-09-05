<?php
// QUIZLY API — Generate Questions with AI Endpoint
// Authenticates user, verifies CSRF and tenant, calls OpenRouter, validates JSON, returns preview

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/response.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../services/GeminiClient.php';
require_once __DIR__ . '/../../services/AIQuestionGenerator.php';

// Set appropriate server execution timeout for AI generation
@set_time_limit(90);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// 1. Session & Role Authorization (Admins, Org Owners, Teachers)
$user = requireRole(['PLATFORM_ADMIN', 'ORG_OWNER', 'TEACHER']);
$orgId = (int)($user['organization_id'] ?? 0);
if ($orgId <= 0) {
    jsonError('Invalid organization context', 'FORBIDDEN', 403);
}

// 2. Parse payload (JSON or Form Data)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

// 3. CSRF Verification
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? $_POST['csrf_token'] ?? null;
if (!verifyCsrfToken($csrfToken)) {
    jsonError('Security verification failed (Invalid CSRF Token). Please refresh the page.', 'CSRF_INVALID', 403);
}

// 4. Rate Limiting: Minimum 3 seconds between successive generation requests per user
$lastGenTime = $_SESSION['last_ai_gen_time'] ?? 0;
$currentTime = time();
if (($currentTime - $lastGenTime) < 3) {
    jsonError('Please wait a few seconds between generation requests.', 'RATE_LIMITED', 429);
}

// 5. Input validation
$topic = trim((string)($input['topic'] ?? ''));
if (empty($topic)) {
    jsonError('Subject / Topic is required.', 'INVALID_INPUT', 400);
}

$questionCount = (int)($input['question_count'] ?? 10);
if ($questionCount < 1 || $questionCount > 50) {
    jsonError('Question count must be between 1 and 50.', 'INVALID_INPUT', 400);
}

try {
    $_SESSION['last_ai_gen_time'] = $currentTime;

    $db = Database::getConnection();
    $geminiClient = new GeminiClient();
    $generator = new AIQuestionGenerator($geminiClient, $db);

    $questions = $generator->generate($input, $orgId);

    logAuditAction(
        $db,
        'AI_GENERATE_QUESTIONS',
        "Generated " . count($questions) . " questions for topic: {$topic} using Gemini",
        (int)$user['id'],
        $orgId
    );

    jsonSuccess([
        'questions' => $questions,
        'count' => count($questions),
        'model' => $geminiClient->getModel()
    ], 'Questions generated successfully');
} catch (Throwable $e) {
    // Safely log error without leaking sensitive tokens or keys
    $safeErrMsg = preg_replace('/[a-zA-Z0-9_\-\.]{30,}/', '***', $e->getMessage());
    error_log("Gemini AI Generation Error: " . $safeErrMsg);
    jsonError($e->getMessage(), 'AI_GENERATION_FAILED', 400);
}
