<?php
require_once __DIR__ . '/../config/database.php';

function generateUniqueRoomCode(): string {
    $db = Database::getConnection();
    
    do {
        // Generate secure 6-digit numeric room code (100000 - 999999)
        $code = (string)random_int(100000, 999999);
        
        // Check if there is any active session currently using this room code
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM quiz_sessions 
            WHERE room_code = :code 
              AND session_status NOT IN ('COMPLETED', 'CANCELLED')
        ");
        $stmt->execute(['code' => $code]);
        $count = (int)$stmt->fetchColumn();
    } while ($count > 0);

    return $code;
}
