<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

$roomCode = strtoupper(trim(sanitizeInput($_POST['room_code'] ?? '')));
$nickname = trim(sanitizeInput($_POST['nickname'] ?? ''));
$department = trim(sanitizeInput($_POST['department'] ?? ''));
$className = trim(sanitizeInput($_POST['class_name'] ?? ''));

if (empty($roomCode) || strlen($roomCode) !== 6) {
    jsonError('Invalid 6-digit room code format', 'INVALID_ROOM_CODE', 400);
}

if (empty($nickname) || strlen($nickname) < 2) {
    jsonError('Nickname must be at least 2 characters', 'INVALID_NICKNAME', 400);
}

$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT s.id, s.session_status, s.organization_id, q.title AS quiz_title
    FROM quiz_sessions s
    JOIN quizzes q ON s.quiz_id = q.id
    WHERE s.room_code = :code
    ORDER BY s.id DESC LIMIT 1
");
$stmt->execute(['code' => $roomCode]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    jsonError('No live quiz session found matching room code: ' . $roomCode, 'ROOM_NOT_FOUND', 444);
}

if (in_array($session['session_status'], ['COMPLETED', 'CANCELLED'], true)) {
    jsonError('This quiz session has already ended.', 'SESSION_CLOSED', 400);
}

$sessionId = (int)$session['id'];

// Resolve nickname collisions automatically (e.g. "Deepak" -> "Deepak (2)")
$uniqueNickname = $nickname;
$collisionCounter = 1;

while (true) {
    $stmtCheck = $db->prepare("SELECT id FROM participants WHERE session_id = :session_id AND nickname = :nickname");
    $stmtCheck->execute(['session_id' => $sessionId, 'nickname' => $uniqueNickname]);
    if (!$stmtCheck->fetchColumn()) {
        break;
    }
    $collisionCounter++;
    $uniqueNickname = $nickname . ' (' . $collisionCounter . ')';
}

$avatar = trim(sanitizeInput($_POST['avatar'] ?? ''));
if (empty($avatar)) {
    $cleanNick = strtolower(preg_replace('/[^a-z0-9]/i', '', $nickname)) ?: 'user';
    $avatar = $cleanNick . '_' . rand(1000, 9999);
}

$participantToken = bin2hex(random_bytes(32));

try {
    $stmtIns = $db->prepare("
        INSERT INTO participants (session_id, participant_token, nickname, avatar, department, class_name, total_score, correct_count, cumulative_time_ms)
        VALUES (:session_id, :token, :nickname, :avatar, :dept, :class, 0, 0, 0)
    ");
    $stmtIns->execute([
        'session_id' => $sessionId,
        'token' => $participantToken,
        'nickname' => $uniqueNickname,
        'avatar' => $avatar,
        'dept' => $department ?: null,
        'class' => $className ?: null
    ]);
    $participantId = (int)$db->lastInsertId();

    $_SESSION['participant_token'] = $participantToken;

    jsonSuccess([
        'participant_id' => $participantId,
        'participant_token' => $participantToken,
        'session_id' => $sessionId,
        'room_code' => $roomCode,
        'nickname' => $uniqueNickname,
        'avatar' => $avatar,
        'department' => $department,
        'class_name' => $className,
        'quiz_title' => $session['quiz_title'],
        'session_status' => $session['session_status']
    ], 'Joined session successfully');

} catch (PDOException $e) {
    jsonError('Failed to join quiz session: ' . $e->getMessage(), 'SERVER_ERROR', 500);
}
