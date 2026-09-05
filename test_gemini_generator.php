<?php
// QUIZLY — Automated Test Suite for Google Gemini Question Generator
// Verifies GeminiClient, AIQuestionGenerator, Validation, and Question Bank Integration

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/GeminiClient.php';
require_once __DIR__ . '/services/AIQuestionGenerator.php';

$totalTests = 0;
$passedTests = 0;

function runTest(string $name, callable $fn) {
    global $totalTests, $passedTests;
    $totalTests++;
    echo "[TEST {$totalTests}] {$name}... ";
    try {
        $result = $fn();
        if ($result !== false) {
            $passedTests++;
            echo "PASSED\n";
            return true;
        } else {
            echo "FAILED (Returned false)\n";
            return false;
        }
    } catch (Throwable $e) {
        echo "FAILED (Exception: " . $e->getMessage() . ")\n";
        return false;
    }
}

echo "========================================================\n";
echo "  QUIZLY — GOOGLE GEMINI GENERATOR TEST SUITE\n";
echo "========================================================\n\n";

$db = Database::getConnection();

// TEST 1: GeminiClient is configured and reads environment
runTest("GeminiClient initializes and is configured", function() {
    $client = new GeminiClient();
    if (!$client->isConfigured()) return false;
    if (empty($client->getModel())) return false;
    return true;
});

// TEST 2: Direct Gemini Content Generation
runTest("GeminiClient executes generateContent and returns structured JSON", function() {
    $client = new GeminiClient();
    $resp = $client->generateContent('Output JSON only: {"status": "ok", "service": "gemini"}');
    $content = $client->extractContent($resp);
    $decoded = json_decode($content, true);
    return is_array($decoded) && ($decoded['status'] ?? '') === 'ok';
});

// TEST 3: Safe Error Handling for Invalid API Key (Never exposes key)
runTest("GeminiClient handles invalid API key securely without exposing credentials", function() {
    $badClient = new GeminiClient('INVALID_KEY_1234567890123456789012345');
    try {
        $badClient->generateContent('test');
        return false; // Should not succeed
    } catch (Exception $e) {
        // Must contain user-friendly error and MUST NOT contain the secret/key
        $msg = $e->getMessage();
        if (str_contains($msg, 'INVALID_KEY_1234567890')) return false;
        return str_contains($msg, 'authentication failed');
    }
});

// TEST 4: AIQuestionGenerator Generates Valid MCQ Questions with Gemini
$generatedQuestions = [];
runTest("AIQuestionGenerator generates valid 4-option MCQ questions via Gemini", function() use (&$generatedQuestions, $db) {
    $generator = new AIQuestionGenerator(new GeminiClient(), $db);
    $generatedQuestions = $generator->generate([
        'topic' => 'Database Management Systems',
        'chapter' => 'Relational Algebra & Normalization',
        'question_count' => 3,
        'question_type' => 'multiple_choice',
        'difficulty' => 'medium',
        'education_level' => 'Undergraduate',
        'language' => 'English',
        'include_explanation' => true,
        'include_learning_objective' => true,
        'timer_seconds' => 25,
        'max_points' => 1000
    ], 1);

    if (count($generatedQuestions) < 2) return false;

    foreach ($generatedQuestions as $q) {
        if (empty($q['question_text'])) return false;
        if (empty($q['options']) || count($q['options']) !== 4) return false;
        
        $correctCount = 0;
        foreach ($q['options'] as $opt) {
            if (!empty($opt['is_correct'])) $correctCount++;
        }
        if ($correctCount !== 1) return false;
        if ($q['timer_seconds'] !== 25) return false;
        if ($q['max_points'] !== 1000) return false;
    }
    return true;
});

// TEST 5: JSON Parser Rejects Invalid Questions & Sanitizes HTML
runTest("JSON parser sanitizes HTML tags and strips script injections", function() use ($db) {
    $generator = new AIQuestionGenerator(new GeminiClient(), $db);
    $mockJson = json_encode([
        'questions' => [
            [
                'question_text' => '<script>alert("hack")</script>What is 2 + 2?',
                'question_type' => 'multiple_choice',
                'difficulty' => 'easy',
                'options' => [
                    ['text' => '<b>4</b>', 'is_correct' => true],
                    ['text' => '5', 'is_correct' => false],
                    ['text' => '6', 'is_correct' => false],
                    ['text' => '7', 'is_correct' => false]
                ],
                'explanation' => '<img src=x onerror=alert(1)>Basic addition.'
            ]
        ]
    ]);

    $parsed = $generator->parseAndValidateQuestionsJson($mockJson, 1);
    if (count($parsed) !== 1) return false;
    $q = $parsed[0];
    if (str_contains($q['question_text'], '<script>')) return false;
    if (str_contains($q['explanation'], '<img')) return false;
    return true;
});

// TEST 6: Single Question Regeneration with Gemini
runTest("AIQuestionGenerator regenerates an individual question with context", function() use ($db, $generatedQuestions) {
    if (empty($generatedQuestions)) return false;
    
    $generator = new AIQuestionGenerator(new GeminiClient(), $db);
    $qToReplace = $generatedQuestions[0];
    
    $regenerated = $generator->regenerateQuestion(
        ['topic' => 'Database Management Systems', 'chapter' => 'Relational Algebra'],
        $qToReplace,
        'Focus specifically on Third Normal Form (3NF).',
        1
    );

    if (empty($regenerated['question_text'])) return false;
    if (count($regenerated['options']) !== 4) return false;
    return true;
});

// TEST 7: Duplicate Detection Against Question Bank
runTest("Duplicate detection flags questions matching existing question bank", function() use ($db, $generatedQuestions) {
    if (empty($generatedQuestions)) return false;
    
    $generator = new AIQuestionGenerator(new GeminiClient(), $db);
    // Create an artificial duplicate
    $duplicateQuestion = $generatedQuestions[0];
    
    // Save to test quiz first
    $saveResult = $generator->saveApprovedQuestions(
        [$duplicateQuestion],
        1, // Org 1
        1, // Admin
        null,
        "Temporary Unit Test Quiz"
    );

    // Now test duplicate detection with the identical question
    $dupCheck = $generator->detectDuplicates([$duplicateQuestion], 1);
    
    // Cleanup temporary quiz
    if (!empty($saveResult['quiz_id'])) {
        $db->exec("DELETE FROM quizzes WHERE id = " . (int)$saveResult['quiz_id']);
    }

    return !empty($dupCheck[0]['is_duplicate']);
});

// TEST 8: Save Approved Questions within Transaction
runTest("saveApprovedQuestions transactionally persists questions into Question Bank", function() use ($db, $generatedQuestions) {
    if (empty($generatedQuestions)) return false;

    $generator = new AIQuestionGenerator(new GeminiClient(), $db);
    $testTitle = "Gemini Persistence Test Quiz " . time();
    
    $saveResult = $generator->saveApprovedQuestions(
        array_slice($generatedQuestions, 0, 2),
        1,
        1,
        null,
        $testTitle
    );

    if (empty($saveResult['quiz_id']) || $saveResult['saved_count'] !== 2) {
        return false;
    }

    // Verify stored in DB
    $quizId = (int)$saveResult['quiz_id'];
    $stmtQ = $db->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = :qid AND ai_generated = 1");
    $stmtQ->execute(['qid' => $quizId]);
    $storedCount = (int)$stmtQ->fetchColumn();

    // Verify options stored
    $stmtOpt = $db->prepare("SELECT COUNT(*) FROM question_options qo JOIN questions q ON qo.question_id = q.id WHERE q.quiz_id = :qid");
    $stmtOpt->execute(['qid' => $quizId]);
    $storedOptCount = (int)$stmtOpt->fetchColumn();

    // Cleanup
    $db->exec("DELETE FROM quizzes WHERE id = {$quizId}");

    return $storedCount === 2 && $storedOptCount === 8;
});

echo "\n========================================================\n";
echo "  RESULTS: {$passedTests} / {$totalTests} TESTS PASSED\n";
echo "========================================================\n";

exit($passedTests === $totalTests ? 0 : 1);
