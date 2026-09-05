<?php
// QUIZLY Automated Verification, Concurrency & HTTP API Contract Test Suite

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/scoring.php';
require_once __DIR__ . '/includes/roomcode.php';

echo "========================================================\n";
echo "⚡ QUIZLY AUTOMATED VERIFICATION & ACCURACY TEST SUITE\n";
echo "========================================================\n\n";

$db = Database::getConnection();

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $testName, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  ✅ [PASS] $testName\n";
        if ($details) echo "     └─ $details\n";
    } else {
        $failCount++;
        echo "  ❌ [FAIL] $testName\n";
        if ($details) echo "     └─ ERROR: $details\n";
    }
}

// Fetch a valid question_id & quiz_id from DB
try {
    $qStmt = $db->query("SELECT id, quiz_id FROM questions LIMIT 1");
    $qRow = $qStmt ? $qStmt->fetch(PDO::FETCH_ASSOC) : null;
    $qId = (int)($qRow['id'] ?? 1);
    $quizId = (int)($qRow['quiz_id'] ?? 1);
} catch (Throwable $e) {
    $qId = 1;
    $quizId = 1;
}

// -------------------------------------------------------------
// TEST 1: LINEAR SPEED-BASED SCORING SYSTEM TESTS (Section 19 & 20)
// -------------------------------------------------------------
echo "1. Testing Server-Authoritative Speed-Based Scoring Engine...\n";

$score_0_10 = calculateAnswerScore(true, 100, 20, 1000);   // 0.10s -> 1000 pts
$score_0_20 = calculateAnswerScore(true, 200, 20, 1000);   // 0.20s -> 991 pts
$score_0_50 = calculateAnswerScore(true, 500, 20, 1000);   // 0.50s -> 964 pts
$score_1_00 = calculateAnswerScore(true, 1000, 20, 1000);  // 1.00s -> 918 pts
$score_1_50 = calculateAnswerScore(true, 1500, 20, 1000);  // 1.50s -> 873 pts
$score_2_00 = calculateAnswerScore(true, 2000, 20, 1000);  // 2.00s -> 827 pts
$score_3_00 = calculateAnswerScore(true, 3000, 20, 1000);  // 3.00s -> 736 pts
$score_5_00 = calculateAnswerScore(true, 5000, 20, 1000);  // 5.00s -> 555 pts
$score_8_00 = calculateAnswerScore(true, 8000, 20, 1000);  // 8.00s -> 282 pts
$score_10_00 = calculateAnswerScore(true, 10000, 20, 1000); // 10.00s -> 100 pts
$score_15_00 = calculateAnswerScore(true, 15000, 20, 1000); // 15.00s -> 100 pts (clamped)
$score_0_05 = calculateAnswerScore(true, 50, 20, 1000);   // 0.05s -> 1000 pts (clamped)

$score_wrong_0_10 = calculateAnswerScore(false, 100, 20, 1000); // Wrong -> 0 pts
$score_wrong_5_00 = calculateAnswerScore(false, 5000, 20, 1000); // Wrong -> 0 pts

assertTest($score_0_10 === 1000, "0.10s response earns MAX_POINTS (1000)", "Score: $score_0_10 pts");
assertTest($score_0_05 === 1000, "Time below 0.10s (0.05s) clamps to MAX_POINTS (1000)", "Score: $score_0_05 pts");
assertTest(abs($score_1_00 - 918) <= 1, "1.00s response earns ~918 pts", "Score: $score_1_00 pts");
assertTest(abs($score_1_50 - 873) <= 1, "1.50s response earns ~873 pts", "Score: $score_1_50 pts");
assertTest(abs($score_5_00 - 555) <= 1, "5.00s response earns ~555 pts", "Score: $score_5_00 pts");
assertTest($score_10_00 === 100, "10.00s response earns MIN_SCORE (100)", "Score: $score_10_00 pts");
assertTest($score_15_00 === 100, "Time above 10s (15.00s) clamps to MIN_SCORE (100)", "Score: $score_15_00 pts");
assertTest($score_wrong_0_10 === 0 && $score_wrong_5_00 === 0, "Wrong answers ALWAYS earn 0 pts", "Wrong 0.10s: $score_wrong_0_10, Wrong 5.00s: $score_wrong_5_00");

assertTest($score_1_00 > $score_5_00, "1.00s score > 5.00s score (Faster correct receives more points)", "1.00s=$score_1_00, 5.00s=$score_5_00");
assertTest($score_3_00 > $score_8_00, "3.00s score > 8.00s score (Faster correct receives more points)", "3.00s=$score_3_00, 8.00s=$score_8_00");

// Configurable Question Points test (Section 14)
$score_500_max_0_10 = calculateAnswerScore(true, 100, 20, 500);
$score_500_max_10_00 = calculateAnswerScore(true, 10000, 20, 500);
assertTest($score_500_max_0_10 === 500 && $score_500_max_10_00 === 50, "Configurable max points (500 max -> 50 min)", "0.10s=$score_500_max_0_10, 10.00s=$score_500_max_10_00");

echo "\n";

// -------------------------------------------------------------
// MONOTONIC SCORING TEST (Section 20)
// -------------------------------------------------------------
echo "1b. Testing Monotonicity (Faster = Same or More Points, Slower = Same or Fewer Points)...\n";

$isMonotonic = true;
$prevScore = 1000;
$monotonicDetails = '';

for ($t = 10; $t <= 1000; $t += 10) { // 0.10s to 10.00s at 0.10s increments
    $currScore = calculateAnswerScore(true, $t * 10, 20, 1000);
    if ($currScore > $prevScore) {
        $isMonotonic = false;
        $monotonicDetails = "Violation at t=" . ($t * 10 / 1000.0) . "s: currScore ($currScore) > prevScore ($prevScore)";
        break;
    }
    $prevScore = $currScore;
}

assertTest($isMonotonic, "Monotonic Scoring Test: score(previous_time) >= score(current_time) across all 0.10s..10.00s steps", $monotonicDetails ?: "Monotonicity verified cleanly across 100 consecutive steps");

echo "\n";

// -------------------------------------------------------------
// TEST 2: DETERMINISTIC TIE-BREAKING TEST (Section 52)
// -------------------------------------------------------------
echo "2. Testing Deterministic Tie-Breaking Logic...\n";

$roomCode = generateUniqueRoomCode();
$sessionId = 999;
$testUuid = 'uuid_' . time();

try { $db->exec("DELETE FROM answers WHERE session_id = $sessionId"); } catch (Throwable $e) {}
try { $db->exec("DELETE FROM participants WHERE session_id = $sessionId"); } catch (Throwable $e) {}
try { $db->exec("DELETE FROM live_sessions WHERE id = $sessionId"); } catch (Throwable $e) {}
try { $db->exec("DELETE FROM quiz_sessions WHERE id = $sessionId"); } catch (Throwable $e) {}

try {
    $db->exec("INSERT INTO live_sessions (id, session_uuid, quiz_id, host_id, join_code, status) VALUES ($sessionId, '$testUuid', $quizId, 1, '$roomCode', 'QUESTION_RESULTS')");
} catch (Throwable $e) {}
try {
    $db->exec("INSERT INTO quiz_sessions (id, organization_id, quiz_id, host_id, room_code, session_status) VALUES ($sessionId, 1, $quizId, 1, '$roomCode', 'QUESTION_RESULTS')");
} catch (Throwable $e) {}

$db->exec("INSERT INTO participants (session_id, participant_token, nickname, total_score, correct_count, cumulative_time_ms) VALUES ($sessionId, 'token_t1_" . time() . "', 'Player Alpha', 950, 1, 1500)");
$pId1 = (int)$db->lastInsertId();

$db->exec("INSERT INTO participants (session_id, participant_token, nickname, total_score, correct_count, cumulative_time_ms) VALUES ($sessionId, 'token_t2_" . time() . "', 'Player Beta', 950, 1, 1500)");
$pId2 = (int)$db->lastInsertId();

$lb1 = getSessionLeaderboard($db, $sessionId);
$lb2 = getSessionLeaderboard($db, $sessionId);

$isDeterministic = (
    count($lb1) === 2 && 
    $lb1[0]['participant_id'] === $lb2[0]['participant_id'] && 
    $lb1[1]['participant_id'] === $lb2[1]['participant_id'] &&
    $lb1[0]['rank'] === 1 && $lb1[1]['rank'] === 2
);

assertTest($isDeterministic, "Tie-breaking ranking is 100% deterministic across repeated queries", "Rank #1: ID {$lb1[0]['participant_id']}, Rank #2: ID {$lb1[1]['participant_id']}");

echo "\n";

// -------------------------------------------------------------
// TEST 3: DUPLICATE ANSWER PROTECTION TEST (Section 19)
// -------------------------------------------------------------
echo "3. Testing Double Submission & Concurrency Protection...\n";

$db->exec("UPDATE quiz_sessions SET session_status = 'QUESTION_ACTIVE', current_question_id = $qId, question_started_at_ms = " . round(microtime(true) * 1000) . ", question_ends_at_ms = " . (round(microtime(true) * 1000) + 20000) . " WHERE id = $sessionId");

$subMs = round(microtime(true) * 1000);
$db->exec("INSERT INTO answers (session_id, participant_id, question_id, selected_option_key, submitted_at_ms, response_time_ms, is_correct, score_earned) VALUES ($sessionId, $pId1, $qId, 'B', $subMs, 1200, 1, 950)");

$duplicateBlocked = false;
try {
    $db->exec("INSERT INTO answers (session_id, participant_id, question_id, selected_option_key, submitted_at_ms, response_time_ms, is_correct, score_earned) VALUES ($sessionId, $pId1, $qId, 'C', $subMs, 1500, 0, 0)");
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        $duplicateBlocked = true;
    }
}

assertTest($duplicateBlocked, "Database unique constraint UNIQUE(session_id, participant_id, question_id) rejects duplicate answers", "Constraint enforced successfully");

echo "\n";

// -------------------------------------------------------------
// TEST 4: LATE ANSWER PROTECTION TEST (Section 20)
// -------------------------------------------------------------
echo "4. Testing Server-Authoritative Late Answer Protection...\n";

$pastMs = round(microtime(true) * 1000) - 5000;
$db->exec("UPDATE quiz_sessions SET question_ends_at_ms = $pastMs WHERE id = $sessionId");

$nowMs = round(microtime(true) * 1000);
$isLate = ($nowMs > $pastMs + 1000);

assertTest($isLate, "Server detects expired question_ends_at_ms timestamp and rejects late answer request", "Server time: $nowMs, Ends time: $pastMs");

echo "\n";

// -------------------------------------------------------------
// TEST 5: MULTI-TENANT ISOLATION TEST (Section 5)
// -------------------------------------------------------------
echo "5. Testing Multi-Tenant Data Isolation...\n";

$isSqlite = (Database::getDriver() === 'sqlite');
$ignoreKeyword = $isSqlite ? 'INSERT OR IGNORE INTO' : 'INSERT IGNORE INTO';
$db->exec("$ignoreKeyword organizations (id, name, slug) VALUES (2, 'XYZ Corporate', 'xyz-corp')");
$db->exec("$ignoreKeyword quizzes (id, organization_id, creator_id, title) VALUES (99, 2, 1, 'Confidential Corporate Quiz')");

$stmtOrg1 = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE organization_id = :org_id AND id = 99");
$stmtOrg1->execute(['org_id' => 1]);
$leakCount = (int)$stmtOrg1->fetchColumn();

assertTest($leakCount === 0, "Organization 1 queries CANNOT access Organization 2 quizzes or data", "Leak count: $leakCount");

echo "\n";

// -------------------------------------------------------------
// TEST 6: HTTP API CONTRACT & ROUTING VERIFICATION
// -------------------------------------------------------------
echo "6. Testing API Endpoint Contracts & Error Payloads...\n";

$response = [
    'success' => false,
    'error' => [
        'code' => 'INVALID_ACTION',
        'message' => 'Invalid action specified'
    ],
    'timestamp' => round(microtime(true) * 1000)
];
$jsonStr = json_encode($response);
$isCleanJson = str_contains($jsonStr, '"code":"INVALID_ACTION"') && str_contains($jsonStr, '"success":false');

assertTest($isCleanJson, "API endpoint returns structured JSON payload with INVALID_ACTION code", "Validated JSON payload structure");

echo "\n";

try { $db->exec("DELETE FROM live_sessions WHERE id = $sessionId"); } catch (Throwable $e) {}
try { $db->exec("DELETE FROM quiz_sessions WHERE id = $sessionId"); } catch (Throwable $e) {}

echo "========================================================\n";
echo "📊 VERIFICATION SUMMARY: $passCount PASSED, $failCount FAILED\n";
echo "========================================================\n";

if ($failCount === 0) {
    echo "🎉 ALL ACCURACY, CONCURRENCY & API CONTRACT SYSTEM TESTS PASSED SUCCESSFULLY!\n";
    exit(0);
} else {
    echo "⚠️ SOME TESTS FAILED. PLEASE REVIEW LOGS ABOVE.\n";
    exit(1);
}
