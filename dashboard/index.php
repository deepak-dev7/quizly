<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$db = Database::getConnection();

$roleTitle = 'Teacher Dashboard';
if (isset($user['role']) && strtoupper($user['role']) === 'TRAINER') {
    $roleTitle = 'Trainer Dashboard';
}

// Metrics for teacher / trainer's organization
$stmtQ = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE organization_id = :org_id");
$stmtQ->execute(['org_id' => $orgId]);
$totalQuizzes = (int)$stmtQ->fetchColumn();

$stmtS = $db->prepare("SELECT COUNT(*) FROM quiz_sessions WHERE organization_id = :org_id");
$stmtS->execute(['org_id' => $orgId]);
$totalSessions = (int)$stmtS->fetchColumn();

$stmtP = $db->prepare("
    SELECT COUNT(p.id) 
    FROM participants p 
    JOIN quiz_sessions s ON p.session_id = s.id 
    WHERE s.organization_id = :org_id
");
$stmtP->execute(['org_id' => $orgId]);
$totalParticipants = (int)$stmtP->fetchColumn();

$stmtC = $db->prepare("SELECT COUNT(*) FROM quiz_sessions WHERE organization_id = :org_id AND session_status = 'COMPLETED'");
$stmtC->execute(['org_id' => $orgId]);
$completedQuizzes = (int)$stmtC->fetchColumn();

// Fetch recent quizzes belonging to this organization
$stmtRecent = $db->prepare("
    SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count
    FROM quizzes q
    WHERE q.organization_id = :org_id
    ORDER BY q.updated_at DESC LIMIT 5
");
$stmtRecent->execute(['org_id' => $orgId]);
$recentQuizzes = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $roleTitle ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
        }
        .stat-card {
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .stat-val {
            font-size: 2.5rem;
            font-weight: 900;
            line-height: 1.1;
            margin-top: 0.5rem;
            letter-spacing: -0.03em;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'dashboard'; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">Welcome, <?= htmlspecialchars($user['name']) ?> 👋</h1>
                    <p style="color:var(--text-secondary); font-size:0.95rem;"><?= htmlspecialchars($user['org_name']) ?> &bull; <?= $roleTitle ?></p>
                </div>
                <a href="<?= BASE_URL ?>/dashboard/create_quiz.php" class="btn btn-primary btn-lg">+ Create Quiz</a>
            </div>

            <!-- STATISTICS CARDS -->
            <div class="stats-grid">
                <div class="stat-card card-tint-purple">
                    <div style="color:var(--brand-purple); font-weight:800; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em;">📚 My Quizzes</div>
                    <div class="stat-val" style="color:var(--brand-purple);"><?= $totalQuizzes ?></div>
                </div>
                <div class="stat-card card-tint-blue">
                    <div style="color:var(--brand-blue); font-weight:800; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em;">⚡ Live Sessions</div>
                    <div class="stat-val" style="color:var(--brand-blue);"><?= $totalSessions ?></div>
                </div>
                <div class="stat-card card-tint-cyan">
                    <div style="color:var(--brand-cyan); font-weight:800; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em;">👥 Participants</div>
                    <div class="stat-val" style="color:var(--brand-cyan);"><?= $totalParticipants ?></div>
                </div>
                <div class="stat-card card-tint-pink">
                    <div style="color:var(--brand-pink); font-weight:800; font-size:0.8rem; text-transform:uppercase; letter-spacing:0.05em;">🏆 Completed Quizzes</div>
                    <div class="stat-val" style="color:var(--brand-pink);"><?= $completedQuizzes ?></div>
                </div>
            </div>

            <!-- RECENT QUIZZES CARD -->
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem; flex-wrap:wrap; gap:0.5rem;">
                    <h3 style="font-size: 1.3rem; color:var(--text-primary);">Recent Quizzes</h3>
                    <a href="<?= BASE_URL ?>/dashboard/quizzes.php" style="font-weight:700; font-size:0.9rem;">View All Quizzes →</a>
                </div>

                <?php if (empty($recentQuizzes)): ?>
                    <div style="text-align:center; padding:3rem 1rem;">
                        <p style="color:var(--text-secondary); margin-bottom:1rem; font-size:1rem;">No quizzes created yet. Click "+ Create Quiz" to get started!</p>
                        <a href="<?= BASE_URL ?>/dashboard/create_quiz.php" class="btn btn-primary">+ Create First Quiz</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Quiz Title</th>
                                    <th>Category</th>
                                    <th>Questions</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentQuizzes as $q): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($q['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($q['category']) ?></td>
                                        <td><span class="badge badge-secondary"><?= $q['question_count'] ?> Qs</span></td>
                                        <td><span class="badge badge-<?= $q['status'] === 'PUBLISHED' ? 'success' : 'warning' ?>"><?= $q['status'] ?></span></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/dashboard/view_quiz.php?id=<?= $q['id'] ?>" class="btn btn-primary btn-sm">Preview & Start</a>
                                            <a href="<?= BASE_URL ?>/dashboard/edit_quiz.php?id=<?= $q['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
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
