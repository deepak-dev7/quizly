<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$quizId = (int)($_GET['id'] ?? 0);
$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT * FROM quizzes 
    WHERE id = :id AND organization_id = :org_id
");
$stmt->execute(['id' => $quizId, 'org_id' => $orgId]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    header('Location: ' . BASE_URL . '/dashboard/quizzes.php');
    exit;
}

$stmtQ = $db->prepare("SELECT * FROM questions WHERE quiz_id = :quiz_id ORDER BY order_num ASC");
$stmtQ->execute(['quiz_id' => $quizId]);
$questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

$stmtOpt = $db->prepare("SELECT * FROM question_options WHERE question_id = :q_id ORDER BY option_key ASC");
foreach ($questions as &$q) {
    $stmtOpt->execute(['q_id' => $q['id']]);
    $q['options'] = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?> — Quiz Preview</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <nav class="navbar">
        <a href="<?= BASE_URL ?>/dashboard/index.php" class="brand-logo">
            ⚡ QUIZ<span class="accent">LY</span>
        </a>
        <div class="nav-links">
            <a href="<?= BASE_URL ?>/dashboard/quizzes.php" class="btn btn-secondary btn-sm">← Back to Quizzes</a>
        </div>
    </nav>

    <div class="container" style="max-width: 920px;">
        <div class="card" style="margin-bottom: 2rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem;">
            <div>
                <h1 style="font-size: 2.2rem; font-weight: 800; color:var(--text-primary);"><?= htmlspecialchars($quiz['title']) ?></h1>
                <p style="color:var(--text-secondary); margin-top:0.25rem;"><?= htmlspecialchars($quiz['description'] ?: 'No description provided.') ?></p>
                <div style="margin-top: 0.75rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
                    <span class="badge badge-primary"><?= htmlspecialchars($quiz['category']) ?></span>
                    <span class="badge badge-secondary"><?= count($questions) ?> Questions</span>
                    <span class="badge badge-warning"><?= htmlspecialchars($quiz['difficulty']) ?></span>
                </div>
            </div>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                <a href="<?= BASE_URL ?>/dashboard/edit_quiz.php?id=<?= $quizId ?>" class="btn btn-secondary btn-lg" style="border-radius:14px;">✏️ Edit Quiz</a>
                <button type="button" onclick="deleteThisQuiz()" class="btn btn-danger btn-lg" style="border-radius:14px;">🗑️ Delete Quiz</button>
                <button id="btnStartSession" class="btn btn-primary btn-lg" style="border-radius:14px;">🚀 START LIVE SESSION</button>
            </div>
        </div>

        <h3 style="margin-bottom: 1.25rem; font-size: 1.35rem; color:var(--text-primary);">Question Preview List</h3>
        <?php foreach ($questions as $idx => $q): ?>
            <div class="card question-card" id="viewQ_<?= $q['id'] ?>" style="margin-bottom: 1.25rem; background:#FFFFFF;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                    <h4 style="color:var(--brand-purple); font-size:1.15rem; margin:0;">
                        Q<span class="q-seq-num"><?= $idx + 1 ?></span>: <?= htmlspecialchars($q['question_text']) ?>
                    </h4>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span class="badge badge-secondary">⏱️ <?= $q['timer_seconds'] ?>s</span>
                        <button type="button" onclick="deleteViewQuestion(<?= $q['id'] ?>, this)" class="btn btn-danger btn-sm" style="padding:0.25rem 0.65rem; font-size:0.8rem;" title="Delete this question">🗑️ Delete</button>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:0.75rem; margin-top:0.75rem;">
                    <?php foreach ($q['options'] as $opt): ?>
                        <div style="padding:0.75rem 1rem; border-radius:10px; background: <?= $opt['is_correct'] ? 'var(--success-light)' : 'var(--bg-subtle)' ?>; border: 1px solid <?= $opt['is_correct'] ? '#6EE7B7' : 'var(--border-color)' ?>; color: var(--text-primary); font-weight: 600;">
                            <strong><?= $opt['option_key'] ?>.</strong> <?= htmlspecialchars($opt['option_text']) ?>
                            <?= $opt['is_correct'] ? ' <span style="color:#047857; font-weight:800;">✓ Correct</span>' : '' ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        document.getElementById('btnStartSession').addEventListener('click', async () => {
            try {
                const formData = new FormData();
                formData.append('quiz_id', <?= $quizId ?>);

                const res = await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/session.php?action=start', {
                    method: 'POST',
                    body: formData
                });

                const session = res.data;
                window.location.href = `<?= BASE_URL ?>/live/host.php?session_id=${session.session_id}`;
            } catch (err) {
                alert('Failed starting session: ' + err.message);
            }
        });

        async function deleteThisQuiz() {
            if (!confirm('Are you sure you want to delete this quiz? All questions, session history, and participant answers will be permanently deleted.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('id', <?= $quizId ?>);

                await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/quizzes.php?action=delete', {
                    method: 'POST',
                    body: formData
                });

                window.location.href = '<?= BASE_URL ?>/dashboard/quizzes.php';
            } catch (err) {
                alert(err.message || 'Failed to delete quiz');
            }
        }

        async function deleteViewQuestion(questionId, btn) {
            if (!confirm('Are you sure you want to delete this question? This action cannot be undone.')) {
                return;
            }

            const card = document.getElementById(`viewQ_${questionId}`) || btn.closest('.question-card');
            btn.disabled = true;
            btn.textContent = '⏳';

            try {
                const formData = new FormData();
                formData.append('id', questionId);

                await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/quizzes.php?action=delete_question', {
                    method: 'POST',
                    body: formData
                });

                if (card) {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.96)';
                    setTimeout(() => {
                        card.remove();

                        // Re-sequence remaining questions
                        const seqSpans = document.querySelectorAll('.q-seq-num');
                        seqSpans.forEach((span, i) => {
                            span.textContent = (i + 1);
                        });

                        // Update questions count in header badge
                        const badges = document.querySelectorAll('.badge-secondary');
                        badges.forEach(b => {
                            if (b.textContent.includes('Questions')) {
                                b.textContent = `${seqSpans.length} Questions`;
                            }
                        });
                    }, 300);
                }
            } catch (err) {
                alert(err.message || 'Failed to delete question');
                btn.disabled = false;
                btn.textContent = '🗑️ Delete';
            }
        }
    </script>
</body>
</html>
