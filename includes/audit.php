<?php
require_once __DIR__ . '/../config/database.php';

function logAuditAction(PDO $db, string $action, ?string $details = null, ?int $userId = null, ?int $orgId = null): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("
            INSERT INTO audit_logs (organization_id, user_id, action, details, ip_address)
            VALUES (:org_id, :user_id, :action, :details, :ip)
        ");
        $stmt->execute([
            'org_id' => $orgId,
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'ip' => $ip
        ]);
    } catch (Exception $e) {
        // Silently log audit failures without disrupting user action
        error_log('Audit Log Error: ' . $e->getMessage());
    }
}
