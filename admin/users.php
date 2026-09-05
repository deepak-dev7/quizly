<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole('PLATFORM_ADMIN');
$db = Database::getConnection();

$stmtUsers = $db->query("
    SELECT u.*, o.name AS org_name
    FROM users u
    LEFT JOIN organizations o ON u.organization_id = o.id
    ORDER BY u.created_at DESC
");
$usersList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users — <?= APP_NAME ?> Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'users'; $forceAdminSidebar = true; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">Platform Users</h1>
                <p style="color:var(--text-secondary); font-size:0.95rem;">Registered teachers, organization owners, and platform administrators</p>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Organization</th>
                                <th>Role</th>
                                <th>Joined Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersList as $u): ?>
                                <tr>
                                    <td>#<?= $u['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                                    <td><?= htmlspecialchars($u['email']) ?></td>
                                    <td><?= htmlspecialchars($u['org_name'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="badge badge-<?= $u['role'] === 'PLATFORM_ADMIN' ? 'danger' : ($u['role'] === 'ORG_OWNER' ? 'primary' : 'secondary') ?>">
                                            <?= htmlspecialchars($u['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
