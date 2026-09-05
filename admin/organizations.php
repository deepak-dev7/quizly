<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole('PLATFORM_ADMIN');
$db = Database::getConnection();

$stmtOrgs = $db->query("
    SELECT 
        o.*,
        (SELECT COUNT(*) FROM users WHERE organization_id = o.id) AS user_count,
        (SELECT COUNT(*) FROM quizzes WHERE organization_id = o.id) AS quiz_count,
        (SELECT COUNT(*) FROM quiz_sessions WHERE organization_id = o.id) AS session_count
    FROM organizations o
    ORDER BY o.created_at DESC
");
$orgs = $stmtOrgs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizations — <?= APP_NAME ?> Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'organizations'; $forceAdminSidebar = true; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">Organizations Directory</h1>
                <p style="color:var(--text-secondary); font-size:0.95rem;">All registered multi-tenant institutions on QUIZLY</p>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Organization Name</th>
                                <th>Slug</th>
                                <th>Department</th>
                                <th>Class</th>
                                <th>Users</th>
                                <th>Quizzes</th>
                                <th>Sessions</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orgs as $o): ?>
                                <tr>
                                    <td>#<?= $o['id'] ?></td>
                                    <td><strong><?= htmlspecialchars($o['name']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($o['slug']) ?></code></td>
                                    <td><?= htmlspecialchars($o['department'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($o['class_name'] ?: '-') ?></td>
                                    <td><span class="badge badge-primary"><?= $o['user_count'] ?></span></td>
                                    <td><span class="badge badge-secondary"><?= $o['quiz_count'] ?></span></td>
                                    <td><span class="badge badge-success"><?= $o['session_count'] ?></span></td>
                                    <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
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
