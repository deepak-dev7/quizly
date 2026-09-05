<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';

$startTime = microtime(true);
$dbStatus = 'OK';
$activeSessions = 0;
$activeParticipants = 0;

try {
    $db = Database::getConnection();
    
    $stmtS = $db->query("SELECT COUNT(*) FROM quiz_sessions WHERE session_status IN ('WAITING', 'QUESTION_ACTIVE')");
    $activeSessions = (int)$stmtS->fetchColumn();

    $stmtP = $db->query("
        SELECT COUNT(p.id) 
        FROM participants p 
        JOIN quiz_sessions s ON p.session_id = s.id 
        WHERE s.session_status IN ('WAITING', 'QUESTION_ACTIVE')
    ");
    $activeParticipants = (int)$stmtP->fetchColumn();

} catch (Exception $e) {
    $dbStatus = 'ERROR: ' . $e->getMessage();
}

$dbLatencyMs = round((microtime(true) - $startTime) * 1000, 2);

jsonSuccess([
    'app_name' => APP_NAME,
    'status' => ($dbStatus === 'OK') ? 'HEALTHY' : 'DEGRADED',
    'database' => [
        'status' => $dbStatus,
        'latency_ms' => $dbLatencyMs
    ],
    'live_metrics' => [
        'active_sessions' => $activeSessions,
        'active_participants' => $activeParticipants
    ],
    'server_time_ms' => round(microtime(true) * 1000),
    'php_version' => PHP_VERSION
]);
