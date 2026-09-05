<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$sessionId = (int)($_GET['session_id'] ?? 0);
$db = Database::getConnection();

$session = null;
if ($sessionId > 0) {
    // Verify session ownership
    $stmtS = $db->prepare("
        SELECT s.id, s.room_code, s.session_status, s.created_at, q.title AS quiz_title
        FROM quiz_sessions s
        JOIN quizzes q ON s.quiz_id = q.id
        WHERE s.id = :session_id AND s.organization_id = :org_id
    ");
    $stmtS->execute(['session_id' => $sessionId, 'org_id' => $orgId]);
    $session = $stmtS->fetch(PDO::FETCH_ASSOC);
}

// Fetch list of sessions for dropdown selection if no session selected or switching
$stmtAllSessions = $db->prepare("
    SELECT s.id, s.room_code, s.session_status, s.created_at, q.title AS quiz_title
    FROM quiz_sessions s
    JOIN quizzes q ON s.quiz_id = q.id
    WHERE s.organization_id = :org_id
    ORDER BY s.created_at DESC
");
$stmtAllSessions->execute(['org_id' => $orgId]);
$allSessions = $stmtAllSessions->fetchAll(PDO::FETCH_ASSOC);

if (!$session && !empty($allSessions)) {
    // Default to most recent session if none explicitly specified
    $session = $allSessions[0];
    $sessionId = (int)$session['id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <style>
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .metric-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow-sm);
        }
        .metric-val {
            font-size: 2.4rem;
            font-weight: 900;
            color: var(--brand-purple);
            margin-top: 0.25rem;
            letter-spacing: -0.02em;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'analytics'; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php if (!$session): ?>
                <div style="margin-bottom: 2rem;">
                    <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">Quiz Analytics</h1>
                    <p style="color:var(--text-secondary); font-size:0.95rem;">Detailed accuracy and response metrics for your organization</p>
                </div>
                <div class="card" style="text-align:center; padding:3rem 1rem;">
                    <p style="color:var(--text-secondary); font-size:1.1rem; margin-bottom:1rem;">No completed sessions available to analyze yet.</p>
                    <a href="<?= BASE_URL ?>/dashboard/quizzes.php" class="btn btn-primary">Start a Quiz Session</a>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <a href="<?= BASE_URL ?>/dashboard/results.php" style="font-weight:700; font-size:0.9rem; color:var(--brand-purple);">← Back to History</a>
                        <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary); margin-top:0.25rem;"><?= htmlspecialchars($session['quiz_title']) ?></h1>
                        <p style="color:var(--text-secondary);">Room Code: <strong style="color:var(--brand-purple); font-family:monospace;"><?= $session['room_code'] ?></strong> &bull; Date: <?= date('M d, Y H:i', strtotime($session['created_at'])) ?></p>
                    </div>
                    <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                        <select onchange="location = this.value;" class="form-select" style="width:auto; height:46px;">
                            <?php foreach ($allSessions as $s): ?>
                                <option value="<?= BASE_URL ?>/dashboard/analytics.php?session_id=<?= $s['id'] ?>" <?= $s['id'] == $sessionId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['quiz_title']) ?> (<?= $s['room_code'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="<?= BASE_URL ?>/dashboard/export_results.php?session_id=<?= $sessionId ?>" class="btn btn-primary">Export CSV 📥</a>
                    </div>
                </div>

                <div id="analyticsContent">
                    <p style="color:var(--text-secondary);">Loading session analytics...</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <?php if ($session): ?>
    <script>
        const sessionId = <?= $sessionId ?>;

        async function loadAnalytics() {
            try {
                const res = await QuizlyApp.fetchJson(`<?= BASE_URL ?>/api/analytics.php?session_id=${sessionId}`);
                const data = res.data;
                const summary = data.summary;
                const questions = data.questions;

                let html = `
                    <div class="metrics-grid">
                        <div class="metric-card card-tint-purple">
                            <div style="color:var(--brand-purple); font-weight:800; font-size:0.85rem; text-transform:uppercase;">Total Participants</div>
                            <div class="metric-val" style="color:var(--brand-purple);">${summary.total_participants}</div>
                        </div>
                        <div class="metric-card card-tint-blue">
                            <div style="color:var(--brand-blue); font-weight:800; font-size:0.85rem; text-transform:uppercase;">Overall Accuracy</div>
                            <div class="metric-val" style="color:var(--brand-blue);">${summary.overall_accuracy_percentage}%</div>
                        </div>
                        <div class="metric-card card-tint-cyan">
                            <div style="color:var(--brand-cyan); font-weight:800; font-size:0.85rem; text-transform:uppercase;">Average Score</div>
                            <div class="metric-val" style="color:var(--brand-cyan);">${summary.avg_score}</div>
                        </div>
                        <div class="metric-card card-tint-pink">
                            <div style="color:var(--brand-pink); font-weight:800; font-size:0.85rem; text-transform:uppercase;">Avg Response Time</div>
                            <div class="metric-val" style="color:var(--brand-pink);">${summary.avg_response_time_formatted}</div>
                        </div>
                        <div class="metric-card">
                            <div style="color:var(--text-secondary); font-weight:800; font-size:0.85rem; text-transform:uppercase;">Fastest Answer</div>
                            <div class="metric-val" style="color:var(--text-primary);">${summary.fastest_correct_formatted}</div>
                        </div>
                    </div>

                    <div class="card">
                        <h3 style="margin-bottom: 1.5rem; color:var(--text-primary);">Per-Question Performance Breakdown</h3>
                        <div class="table-responsive">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Question Prompt</th>
                                        <th>Responses</th>
                                        <th>Correct</th>
                                        <th>Accuracy</th>
                                        <th>Avg Time</th>
                                        <th>Fastest</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${questions.map(q => `
                                        <tr>
                                            <td><strong>Q${q.order_num}</strong></td>
                                            <td><strong>${QuizlyApp.escapeHtml(q.question_text)}</strong></td>
                                            <td>${q.total_responses}</td>
                                            <td style="color:var(--success); font-weight:800;">${q.correct_responses}</td>
                                            <td><span class="badge badge-${q.accuracy_percentage >= 70 ? 'success' : (q.accuracy_percentage >= 40 ? 'warning' : 'danger')}">${q.accuracy_percentage}%</span></td>
                                            <td>${q.avg_response_time_formatted}</td>
                                            <td>${q.fastest_response_formatted}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;

                document.getElementById('analyticsContent').innerHTML = html;
            } catch (err) {
                document.getElementById('analyticsContent').innerHTML = `<div class="alert alert-danger">Error loading analytics: ${err.message}</div>`;
            }
        }

        loadAnalytics();
    </script>
    <?php endif; ?>
</body>
</html>
