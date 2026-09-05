<?php
if (ob_get_level() === 0) {
    ob_start();
}

// Global Exception & Error Handler for API Endpoint
set_exception_handler(function($e) {
    if (ob_get_length()) {
        @ob_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'FATAL_ERROR',
            'message' => $e->getMessage()
        ],
        'timestamp' => round(microtime(true) * 1000)
    ]);
    exit;
});

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

try {
    $db = Database::getConnection();
} catch (Exception $e) {
    jsonError('Database Connection Error: ' . $e->getMessage(), 'DB_ERROR', 500);
}

if ($action === 'register') {
    $orgName = trim(sanitizeInput($_POST['org_name'] ?? ''));
    $department = trim(sanitizeInput($_POST['department'] ?? ''));
    $className = trim(sanitizeInput($_POST['class_name'] ?? ''));
    $name = trim(sanitizeInput($_POST['name'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($orgName) || empty($name) || !$email || strlen($password) < 6) {
        jsonError('Please fill all required fields properly. Password must be at least 6 characters.', 'INVALID_INPUT', 400);
    }

    try {
        $db->beginTransaction();

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $orgName), '-')) . '-' . substr(md5(uniqid()), 0, 4);

        $stmtOrg = $db->prepare("INSERT INTO organizations (name, slug, department, class_name) VALUES (:name, :slug, :dept, :class)");
        $stmtOrg->execute(['name' => $orgName, 'slug' => $slug, 'dept' => $department ?: null, 'class' => $className ?: null]);
        $orgId = (int)$db->lastInsertId();

        $stmtUserCheck = $db->prepare("SELECT id FROM users WHERE email = :email");
        $stmtUserCheck->execute(['email' => $email]);
        if ($stmtUserCheck->fetchColumn()) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            jsonError('Email address already registered', 'EMAIL_TAKEN', 409);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmtUser = $db->prepare("
            INSERT INTO users (organization_id, name, email, password_hash, role)
            VALUES (:org_id, :name, :email, :pass, 'ORG_OWNER')
        ");
        $stmtUser->execute([
            'org_id' => $orgId,
            'name' => $name,
            'email' => $email,
            'pass' => $passwordHash
        ]);
        $userId = (int)$db->lastInsertId();

        $db->commit();

        loginUser([
            'id' => $userId,
            'organization_id' => $orgId,
            'name' => $name,
            'email' => $email,
            'role' => 'ORG_OWNER',
            'org_name' => $orgName,
            'department' => $department,
            'class_name' => $className
        ]);

        jsonSuccess([
            'user_id' => $userId,
            'organization_id' => $orgId,
            'name' => $name,
            'email' => $email,
            'role' => 'ORG_OWNER',
            'org_name' => $orgName
        ], 'Organization created successfully');

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        jsonError('Registration failed: ' . $e->getMessage(), 'SERVER_ERROR', 500);
    }
}

if ($action === 'login') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || empty($password)) {
        jsonError('Please enter a valid email address and password', 'INVALID_INPUT', 400);
    }

    try {
        $stmt = $db->prepare("
            SELECT u.id, u.organization_id, u.name, u.email, u.password_hash, u.role, o.name AS org_name, o.department, o.class_name
            FROM users u
            JOIN organizations o ON u.organization_id = o.id
            WHERE u.email = :email
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonError('Invalid email or password credentials', 'AUTH_FAILED', 401);
        }

        loginUser([
            'id' => (int)$user['id'],
            'organization_id' => (int)$user['organization_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'org_name' => $user['org_name'],
            'department' => $user['department'],
            'class_name' => $user['class_name']
        ]);

        jsonSuccess([
            'user_id' => (int)$user['id'],
            'organization_id' => (int)$user['organization_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'org_name' => $user['org_name']
        ], 'Login successful');
    } catch (Exception $e) {
        jsonError('Login failed: ' . $e->getMessage(), 'SERVER_ERROR', 500);
    }
}

if ($action === 'logout') {
    logoutUser();
    jsonSuccess(null, 'Logged out successfully');
}

jsonError('Invalid action specified', 'INVALID_ACTION', 400);
