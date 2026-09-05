<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT q.*, (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS question_count, u.name AS creator_name
    FROM quizzes q
    JOIN users u ON q.creator_id = u.id
    WHERE q.organization_id = :org_id
    ORDER BY q.updated_at DESC
");
$stmt->execute(['org_id' => $orgId]);
$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quizzes — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'quizzes'; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">My Quizzes</h1>
                    <p style="color:var(--text-secondary); font-size:0.95rem;">Manage, edit, and launch live quiz sessions for your organization</p>
                </div>
                <a href="<?= BASE_URL ?>/dashboard/create_quiz.php" class="btn btn-primary btn-lg">+ Create Quiz</a>
            </div>

            <div class="card">
                <?php if (empty($quizzes)): ?>
                    <div style="text-align:center; padding:3rem 1rem;">
                        <p style="color:var(--text-secondary); font-size:1.1rem; margin-bottom:1rem;">No quizzes found. Click "+ Create Quiz" to create your first live quiz!</p>
                        <a href="<?= BASE_URL ?>/dashboard/create_quiz.php" class="btn btn-primary">+ Create First Quiz</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Difficulty</th>
                                    <th>Questions</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quizzes as $q): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($q['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($q['category']) ?></td>
                                        <td><span class="badge badge-primary"><?= htmlspecialchars($q['difficulty']) ?></span></td>
                                        <td><span class="badge badge-secondary"><?= $q['question_count'] ?> Qs</span></td>
                                        <td><span class="badge badge-<?= $q['status'] === 'PUBLISHED' ? 'success' : 'warning' ?>"><?= htmlspecialchars($q['status']) ?></span></td>
                                        <td style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                            <a href="<?= BASE_URL ?>/dashboard/view_quiz.php?id=<?= $q['id'] ?>" class="btn btn-primary btn-sm">Start Live Session</a>
                                            <a href="<?= BASE_URL ?>/dashboard/edit_quiz.php?id=<?= $q['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                            <button onclick="deleteQuiz(<?= $q['id'] ?>)" class="btn btn-danger btn-sm">Delete</button>
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
    <script>
        async function deleteQuiz(id) {
            if (!confirm('Are you sure you want to delete this quiz?')) return;

            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/quizzes.php', {
                    method: 'POST',
                    body: formData
                });
                window.location.reload();
            } catch (err) {
                alert(err.message);
            }
        }
    </script>
</body>
</html>
