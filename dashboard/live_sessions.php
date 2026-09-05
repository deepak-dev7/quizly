<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT s.*, q.title AS quiz_title,
           (SELECT COUNT(*) FROM participants WHERE session_id = s.id) AS participant_count
    FROM quiz_sessions s
    JOIN quizzes q ON s.quiz_id = q.id
    WHERE s.organization_id = :org_id
    ORDER BY s.created_at DESC
");
$stmt->execute(['org_id' => $orgId]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Sessions — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'live_sessions'; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">Live Sessions</h1>
                    <p style="color:var(--text-secondary); font-size:0.95rem;">Monitor real-time interactive quiz sessions for <?= htmlspecialchars($user['org_name']) ?></p>
                </div>
                <a href="<?= BASE_URL ?>/dashboard/quizzes.php" class="btn btn-primary btn-lg">⚡ Launch New Session</a>
            </div>

            <div class="card">
                <?php if (empty($sessions)): ?>
                    <div style="text-align:center; padding:3rem 1rem;">
                        <p style="color:var(--text-secondary); font-size:1.1rem; margin-bottom:1rem;">No live sessions created yet.</p>
                        <a href="<?= BASE_URL ?>/dashboard/quizzes.php" class="btn btn-primary">Select a Quiz to Launch</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Room Code</th>
                                    <th>Quiz Title</th>
                                    <th>Status</th>
                                    <th>Participants</th>
                                    <th>Started At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $s): ?>
                                    <tr>
                                        <td><strong style="font-family:monospace; font-size:1.25rem; color:var(--brand-purple);"><?= htmlspecialchars($s['room_code']) ?></strong></td>
                                        <td><strong><?= htmlspecialchars($s['quiz_title']) ?></strong></td>
                                        <td>
                                            <?php
                                            $badgeType = 'secondary';
                                            if ($s['session_status'] === 'COMPLETED') $badgeType = 'success';
                                            else if (in_array($s['session_status'], ['WAITING', 'QUESTION_ACTIVE', 'QUESTION_RESULTS', 'LEADERBOARD'])) $badgeType = 'primary';
                                            else if ($s['session_status'] === 'CANCELLED') $badgeType = 'danger';
                                            ?>
                                            <span class="badge badge-<?= $badgeType ?>"><?= htmlspecialchars($s['session_status']) ?></span>
                                        </td>
                                        <td><span class="badge badge-secondary">👥 <?= $s['participant_count'] ?> Players</span></td>
                                        <td><?= date('M d, Y H:i', strtotime($s['created_at'])) ?></td>
                                        <td style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                            <?php if ($s['session_status'] !== 'COMPLETED' && $s['session_status'] !== 'CANCELLED'): ?>
                                                <a href="<?= BASE_URL ?>/live/host.php?session_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm">Enter Host Room ⚡</a>
                                            <?php endif; ?>
                                            <a href="<?= BASE_URL ?>/dashboard/analytics.php?session_id=<?= $s['id'] ?>" class="btn btn-secondary btn-sm">Analytics</a>
                                            <a href="<?= BASE_URL ?>/dashboard/export_results.php?session_id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">Export CSV 📥</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
