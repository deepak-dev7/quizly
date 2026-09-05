<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole('PLATFORM_ADMIN');
$db = Database::getConnection();

// System Wide Metrics
$totalOrgs = (int)$db->query("SELECT COUNT(*) FROM organizations")->fetchColumn();
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalQuizzes = (int)$db->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalSessions = (int)$db->query("SELECT COUNT(*) FROM quiz_sessions")->fetchColumn();
$totalParticipants = (int)$db->query("SELECT COUNT(*) FROM participants")->fetchColumn();
$totalAnswers = (int)$db->query("SELECT COUNT(*) FROM answers")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Analytics — <?= APP_NAME ?> Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'admin_analytics'; $forceAdminSidebar = true; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">System Analytics</h1>
                <p style="color:var(--text-secondary); font-size:0.95rem;">Platform-wide telemetry, user participation, and system metrics</p>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:2rem;">
                <div class="card card-tint-purple">
                    <div style="color:var(--brand-purple); font-weight:800; font-size:0.8rem; text-transform:uppercase;">Organizations</div>
                    <div style="font-size:2.5rem; font-weight:900; color:var(--brand-purple); margin-top:0.25rem;"><?= $totalOrgs ?></div>
                </div>
                <div class="card card-tint-blue">
                    <div style="color:var(--brand-blue); font-weight:800; font-size:0.8rem; text-transform:uppercase;">Total Users</div>
                    <div style="font-size:2.5rem; font-weight:900; color:var(--brand-blue); margin-top:0.25rem;"><?= $totalUsers ?></div>
                </div>
                <div class="card card-tint-cyan">
                    <div style="color:var(--brand-cyan); font-weight:800; font-size:0.8rem; text-transform:uppercase;">Total Quizzes</div>
                    <div style="font-size:2.5rem; font-weight:900; color:var(--brand-cyan); margin-top:0.25rem;"><?= $totalQuizzes ?></div>
                </div>
                <div class="card card-tint-pink">
                    <div style="color:var(--brand-pink); font-weight:800; font-size:0.8rem; text-transform:uppercase;">Total Sessions</div>
                    <div style="font-size:2.5rem; font-weight:900; color:var(--brand-pink); margin-top:0.25rem;"><?= $totalSessions ?></div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
                <div class="card">
                    <h3 style="font-size:1.2rem; color:var(--text-primary); margin-bottom:1rem;">Participant Engagement</h3>
                    <div style="font-size:2.2rem; font-weight:900; color:var(--brand-purple);"><?= number_format($totalParticipants) ?></div>
                    <p style="color:var(--text-secondary); font-size:0.9rem;">Total student live session joins</p>
                </div>
                <div class="card">
                    <h3 style="font-size:1.2rem; color:var(--text-primary); margin-bottom:1rem;">Answers Submitted</h3>
                    <div style="font-size:2.2rem; font-weight:900; color:var(--brand-blue);"><?= number_format($totalAnswers) ?></div>
                    <p style="color:var(--text-secondary); font-size:0.9rem;">Total question responses processed</p>
                </div>
            </div>
        </main>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
