<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT 
        s.id AS session_id,
        s.room_code,
        s.session_status,
        s.created_at,
        q.title AS quiz_title,
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
    <title>Session Results — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'results'; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">Live Session Results</h1>
                <p style="color:var(--text-secondary); font-size:0.95rem;">Select a completed live session to view detailed accuracy metrics or export CSV data</p>
            </div>

            <div class="card">
                <?php if (empty($sessions)): ?>
                    <div style="text-align:center; padding:3rem 1rem;">
                        <p style="color:var(--text-secondary); font-size:1.1rem;">No live session history recorded yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Room Code</th>
                                    <th>Quiz Title</th>
                                    <th>Participants</th>
                                    <th>Status</th>
                                    <th>Date Conducted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sessions as $s): ?>
                                    <tr>
                                        <td><strong style="font-family:monospace; font-size:1.2rem; color:var(--brand-purple);"><?= htmlspecialchars($s['room_code']) ?></strong></td>
                                        <td><strong><?= htmlspecialchars($s['quiz_title']) ?></strong></td>
                                        <td><span class="badge badge-primary"><?= $s['participant_count'] ?> Players</span></td>
                                        <td><span class="badge badge-<?= $s['session_status'] === 'COMPLETED' ? 'success' : 'warning' ?>"><?= htmlspecialchars($s['session_status']) ?></span></td>
                                        <td><?= date('M d, Y H:i', strtotime($s['created_at'])) ?></td>
                                        <td style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                            <a href="<?= BASE_URL ?>/dashboard/analytics.php?session_id=<?= $s['session_id'] ?>" class="btn btn-primary btn-sm">View Analytics</a>
                                            <a href="<?= BASE_URL ?>/dashboard/export_results.php?session_id=<?= $s['session_id'] ?>" class="btn btn-secondary btn-sm">Export CSV 📥</a>
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
