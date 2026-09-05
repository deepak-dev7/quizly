<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$db = Database::getConnection();

$search = trim($_GET['q'] ?? '');
$filterSource = trim($_GET['source'] ?? 'all'); // 'all', 'ai', 'manual'
$selectedTopic = trim($_GET['topic'] ?? 'all');
$viewMode = trim($_GET['view'] ?? 'topic'); // 'topic' (default) or 'flat'

// 1. Fetch available topics for dropdown
$stmtTopics = $db->prepare("
    SELECT 
        COALESCE(NULLIF(TRIM(q.topic), ''), 'General / Untracked') AS topic_name,
        COUNT(q.id) AS question_count
    FROM questions q
    JOIN quizzes qz ON q.quiz_id = qz.id
    WHERE qz.organization_id = :org_id
    GROUP BY topic_name
    ORDER BY question_count DESC, topic_name ASC
");
$stmtTopics->execute(['org_id' => $orgId]);
$availableTopics = $stmtTopics->fetchAll(PDO::FETCH_ASSOC);

// 2. Build Query
$sql = "
    SELECT q.id AS question_id, q.question_text, q.timer_seconds, q.max_points,
           q.question_type, q.difficulty, q.topic, q.ai_generated, q.ai_model, q.explanation,
           qz.title AS quiz_title, qz.id AS quiz_id,
           (SELECT COUNT(*) FROM question_options WHERE question_id = q.id) AS option_count
    FROM questions q
    JOIN quizzes qz ON q.quiz_id = qz.id
    WHERE qz.organization_id = :org_id
";

$params = ['org_id' => $orgId];

if (!empty($search)) {
    $sql .= " AND (q.question_text LIKE :search OR qz.title LIKE :search OR q.topic LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if ($filterSource === 'ai') {
    $sql .= " AND q.ai_generated = 1";
} elseif ($filterSource === 'manual') {
    $sql .= " AND (q.ai_generated = 0 OR q.ai_generated IS NULL)";
}

if ($selectedTopic !== 'all') {
    if ($selectedTopic === 'General / Untracked') {
        $sql .= " AND (q.topic IS NULL OR TRIM(q.topic) = '' OR q.topic = 'General' OR q.topic = 'General / Untracked')";
    } else {
        $sql .= " AND q.topic = :topic_filter";
        $params['topic_filter'] = $selectedTopic;
    }
}

$sql .= " ORDER BY q.topic ASC, qz.updated_at DESC, q.order_num ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Stats for header pills
$stmtStats = $db->prepare("
    SELECT 
        COUNT(q.id) AS total_count,
        SUM(CASE WHEN q.ai_generated = 1 THEN 1 ELSE 0 END) AS ai_count
    FROM questions q
    JOIN quizzes qz ON q.quiz_id = qz.id
    WHERE qz.organization_id = :org_id
");
$stmtStats->execute(['org_id' => $orgId]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
$totalCount = (int)($stats['total_count'] ?? 0);
$aiCount = (int)($stats['ai_count'] ?? 0);
$manualCount = $totalCount - $aiCount;

// 4. Group questions topic-wise
$topicsGrouped = [];
foreach ($questions as $q) {
    $tName = trim((string)($q['topic'] ?? ''));
    if ($tName === '') {
        $tName = 'General / Untracked';
    }
    if (!isset($topicsGrouped[$tName])) {
        $topicsGrouped[$tName] = [
            'name' => $tName,
            'questions' => [],
            'ai_count' => 0,
            'manual_count' => 0
        ];
    }
    $topicsGrouped[$tName]['questions'][] = $q;
    if (!empty($q['ai_generated'])) {
        $topicsGrouped[$tName]['ai_count']++;
    } else {
        $topicsGrouped[$tName]['manual_count']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Question Bank — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <style>
        .qb-header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .view-switcher {
            display: inline-flex;
            background: var(--bg-subtle);
            padding: 4px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            gap: 4px;
        }
        .view-btn {
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            transition: all 0.2s ease;
        }
        .view-btn:hover {
            color: var(--brand-purple);
        }
        .view-btn.active {
            background: #FFFFFF;
            color: var(--brand-purple);
            box-shadow: var(--shadow-xs);
        }

        /* Topic Card Styling */
        .topic-group-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            margin-bottom: 1.75rem;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }
        .topic-group-card:hover {
            box-shadow: var(--shadow-md);
        }

        .topic-header {
            padding: 1.25rem 1.75rem;
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            cursor: pointer;
            user-select: none;
        }

        .topic-title-area {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .topic-icon-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--brand-purple-light);
            color: var(--brand-purple);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .topic-body {
            transition: max-height 0.3s ease;
        }
        .topic-body.collapsed {
            display: none;
        }

        .toggle-arrow {
            transition: transform 0.2s ease;
            display: inline-block;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        .toggle-arrow.rotated {
            transform: rotate(-90deg);
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'all_questions'; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <!-- HEADER -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom: 2rem;">
                <div>
                    <h1 style="font-size: 2.1rem; font-weight: 800; color:var(--text-primary); letter-spacing:-0.02em;">Question Bank</h1>
                    <p style="color:var(--text-secondary); font-size:0.95rem; margin-top:0.25rem;">
                        Browse and organize quiz questions topic-wise within <strong><?= htmlspecialchars($user['org_name']) ?></strong>
                    </p>
                </div>
                <div class="qb-header-actions">
                    <a href="<?= BASE_URL ?>/dashboard/create_quiz.php" class="btn btn-secondary">
                        <span>➕</span> Add Question
                    </a>
                    <a href="<?= BASE_URL ?>/dashboard/ai_generate.php" class="btn btn-primary" style="background:var(--grad-primary); box-shadow:var(--shadow-glow);">
                        <span>✨</span> Generate with AI
                    </a>
                </div>
            </div>

            <!-- SEARCH AND FILTER BAR -->
            <div class="card" style="margin-bottom: 1.5rem; padding: 1.25rem;">
                <form method="GET" action="" style="display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center;">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
                    
                    <input type="text" name="q" class="form-input" placeholder="Search question prompts, concepts, or topics..." value="<?= htmlspecialchars($search) ?>" style="flex:2; min-width: 240px; height: 44px;">
                    
                    <!-- TOPIC FILTER DROPDOWN -->
                    <select name="topic" class="form-select" style="flex:1; min-width: 180px; height: 44px;" onchange="this.form.submit()">
                        <option value="all" <?= $selectedTopic === 'all' ? 'selected' : '' ?>>All Topics (<?= count($availableTopics) ?>)</option>
                        <?php foreach ($availableTopics as $t): ?>
                            <option value="<?= htmlspecialchars($t['topic_name']) ?>" <?= $selectedTopic === $t['topic_name'] ? 'selected' : '' ?>>
                                🏷️ <?= htmlspecialchars($t['topic_name']) ?> (<?= $t['question_count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- SOURCE FILTER DROPDOWN -->
                    <select name="source" class="form-select" style="width: auto; min-width: 160px; height: 44px;" onchange="this.form.submit()">
                        <option value="all" <?= $filterSource === 'all' ? 'selected' : '' ?>>All Sources (<?= $totalCount ?>)</option>
                        <option value="ai" <?= $filterSource === 'ai' ? 'selected' : '' ?>>✨ AI Generated (<?= $aiCount ?>)</option>
                        <option value="manual" <?= $filterSource === 'manual' ? 'selected' : '' ?>>✍️ Manual (<?= $manualCount ?>)</option>
                    </select>

                    <button type="submit" class="btn btn-primary" style="height:44px;">Search</button>
                    <?php if (!empty($search) || $filterSource !== 'all' || $selectedTopic !== 'all'): ?>
                        <a href="<?= BASE_URL ?>/dashboard/question_bank.php?view=<?= urlencode($viewMode) ?>" class="btn btn-secondary" style="height:44px;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- VIEW MODE SWITCHER & COUNTERS -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom: 1.25rem;">
                <div style="font-size: 0.95rem; color: var(--text-secondary);">
                    Showing <strong><?= count($questions) ?></strong> questions across <strong><?= count($topicsGrouped) ?></strong> topic group(s)
                </div>

                <div class="view-switcher">
                    <a href="?view=topic<?= !empty($search) ? '&q='.urlencode($search) : '' ?><?= $filterSource !== 'all' ? '&source='.urlencode($filterSource) : '' ?><?= $selectedTopic !== 'all' ? '&topic='.urlencode($selectedTopic) : '' ?>" class="view-btn <?= $viewMode === 'topic' ? 'active' : '' ?>">
                        <span>🗂️</span> Group by Topic
                    </a>
                    <a href="?view=flat<?= !empty($search) ? '&q='.urlencode($search) : '' ?><?= $filterSource !== 'all' ? '&source='.urlencode($filterSource) : '' ?><?= $selectedTopic !== 'all' ? '&topic='.urlencode($selectedTopic) : '' ?>" class="view-btn <?= $viewMode === 'flat' ? 'active' : '' ?>">
                        <span>📋</span> Flat List
                    </a>
                </div>
            </div>

            <?php if (empty($questions)): ?>
                <!-- EMPTY STATE -->
                <div class="card">
                    <div style="text-align:center; padding:3.5rem 1.5rem;">
                        <div style="font-size:3rem; margin-bottom:0.75rem;">📚</div>
                        <h3 style="font-size:1.3rem; margin-bottom:0.5rem; color:var(--text-primary);">
                            <?= !empty($search) || $selectedTopic !== 'all' ? 'No questions matched your search/topic filter.' : 'No questions found in your organization bank.' ?>
                        </h3>
                        <p style="color:var(--text-secondary); font-size:0.95rem; margin-bottom:1.5rem;">
                            Use the AI Question Generator to instantly produce questions grouped by topic.
                        </p>
                        <div style="display:flex; justify-content:center; gap:0.75rem; flex-wrap:wrap;">
                            <a href="<?= BASE_URL ?>/dashboard/ai_generate.php" class="btn btn-primary btn-lg">✨ Generate with AI</a>
                            <a href="<?= BASE_URL ?>/dashboard/create_quiz.php" class="btn btn-secondary btn-lg">+ Create Quiz Manually</a>
                        </div>
                    </div>
                </div>
            <?php elseif ($viewMode === 'topic'): ?>
                <!-- ================= TOPIC-WISE GROUPED VIEW ================= -->
                <?php $topicIndex = 0; foreach ($topicsGrouped as $topicName => $group): $topicIndex++; ?>
                    <div class="topic-group-card" id="topicCard_<?= $topicIndex ?>">
                        <div class="topic-header" onclick="toggleTopicSection('<?= $topicIndex ?>')">
                            <div class="topic-title-area">
                                <div class="topic-icon-badge">🏷️</div>
                                <div>
                                    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-primary); margin:0;">
                                        <?= htmlspecialchars($topicName) ?>
                                    </h3>
                                    <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.25rem; flex-wrap:wrap;">
                                        <span class="badge badge-secondary" style="font-size:0.75rem;">
                                            <?= count($group['questions']) ?> Questions
                                        </span>
                                        <?php if ($group['ai_count'] > 0): ?>
                                            <span class="badge badge-primary" style="font-size:0.75rem; background:var(--brand-purple-light); color:var(--brand-purple);">
                                                ✨ <?= $group['ai_count'] ?> AI
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($group['manual_count'] > 0): ?>
                                            <span class="badge badge-secondary" style="font-size:0.75rem;">
                                                ✍️ <?= $group['manual_count'] ?> Manual
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;" onclick="event.stopPropagation();">
                                <button type="button" class="btn btn-primary btn-sm btn-start-topic" onclick="startTopicQuiz('<?= htmlspecialchars(addslashes($topicName), ENT_QUOTES) ?>', <?= count($group['questions']) ?>)" style="background:var(--grad-primary); box-shadow:var(--shadow-xs); display:inline-flex; align-items:center; gap:0.35rem; font-weight:700; border:none; padding: 0.45rem 0.85rem; border-radius:var(--radius-md);" title="Instantly start a live multiplayer quiz session for this topic">
                                    <span>🚀</span> Start Live Quiz
                                </button>
                                <a href="<?= BASE_URL ?>/dashboard/create_quiz.php?from_topic=<?= urlencode($topicName) ?>" class="btn btn-secondary btn-sm" title="Customize in Quiz Builder">
                                    <span>✏️</span> Create Quiz
                                </a>
                                <span class="toggle-arrow" id="arrow_<?= $topicIndex ?>" onclick="toggleTopicSection('<?= $topicIndex ?>')">▼</span>
                            </div>
                        </div>

                        <!-- TOPIC QUESTIONS TABLE -->
                        <div class="topic-body" id="body_<?= $topicIndex ?>">
                            <div class="table-responsive">
                                <table class="table-custom" style="margin:0;">
                                    <thead>
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>Question Prompt</th>
                                            <th>Quiz / Context</th>
                                            <th>Type</th>
                                            <th>Difficulty</th>
                                            <th>Source</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group['questions'] as $qIdx => $q): ?>
                                            <tr>
                                                <td><strong><?= $qIdx + 1 ?></strong></td>
                                                <td style="max-width:380px;">
                                                    <div style="font-weight:700; color:var(--text-primary); margin-bottom:0.25rem;">
                                                        <?= htmlspecialchars($q['question_text']) ?>
                                                    </div>
                                                    <?php if (!empty($q['explanation'])): ?>
                                                        <div style="font-size:0.8rem; color:var(--text-muted); display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden;">
                                                            💡 <?= htmlspecialchars($q['explanation']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="<?= BASE_URL ?>/dashboard/view_quiz.php?id=<?= $q['quiz_id'] ?>" style="font-weight:600; font-size:0.88rem;">
                                                        <?= htmlspecialchars($q['quiz_title']) ?>
                                                    </a>
                                                    <div style="font-size:0.75rem; color:var(--text-muted);">
                                                        ⏱ <?= $q['timer_seconds'] ?>s • ⭐ <?= $q['max_points'] ?> pts
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $typeLabel = 'MCQ';
                                                    $typeBadge = 'badge-secondary';
                                                    if (($q['question_type'] ?? '') === 'true_false') {
                                                        $typeLabel = 'True/False';
                                                        $typeBadge = 'badge-primary';
                                                    } elseif (($q['question_type'] ?? '') === 'multiple_select') {
                                                        $typeLabel = 'Multi-Select';
                                                        $typeBadge = 'badge-warning';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $typeBadge ?>"><?= $typeLabel ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $diff = strtolower($q['difficulty'] ?? 'medium');
                                                    $diffClass = $diff === 'easy' ? 'badge-success' : ($diff === 'hard' ? 'badge-danger' : 'badge-warning');
                                                    ?>
                                                    <span class="badge <?= $diffClass ?>"><?= ucfirst($diff) ?></span>
                                                </td>
                                                <td>
                                                    <?php if (!empty($q['ai_generated'])): ?>
                                                        <span class="badge badge-primary" style="background:var(--brand-purple-light); color:var(--brand-purple); border:1px solid #ddd6fe;">
                                                            ✨ AI Generated
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">✍️ Manual</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="white-space:nowrap;">
                                                    <div style="display:inline-flex; gap:0.4rem; align-items:center;">
                                                        <a href="<?= BASE_URL ?>/dashboard/edit_quiz.php?id=<?= $q['quiz_id'] ?>" class="btn btn-secondary btn-sm" title="Edit parent quiz">Edit</a>
                                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteBankQuestion(<?= $q['question_id'] ?>, this, <?= !empty($q['ai_generated']) ? 'true' : 'false' ?>, '<?= $topicIndex ?>')" title="Delete this question">🗑️</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php else: ?>
                <!-- ================= FLAT LIST VIEW ================= -->
                <div class="card">
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th style="width:40px;">#</th>
                                    <th>Question Prompt</th>
                                    <th>Topic / Quiz</th>
                                    <th>Type</th>
                                    <th>Difficulty</th>
                                    <th>Source</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($questions as $idx => $q): ?>
                                    <tr>
                                        <td><strong><?= $idx + 1 ?></strong></td>
                                        <td style="max-width:380px;">
                                            <div style="font-weight:700; color:var(--text-primary); margin-bottom:0.25rem;">
                                                <?= htmlspecialchars($q['question_text']) ?>
                                            </div>
                                            <?php if (!empty($q['explanation'])): ?>
                                                <div style="font-size:0.8rem; color:var(--text-muted); display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden;">
                                                    💡 <?= htmlspecialchars($q['explanation']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($q['topic'])): ?>
                                                <div style="font-size:0.8rem; font-weight:700; color:var(--brand-purple); margin-bottom:0.15rem;">
                                                    🏷️ <?= htmlspecialchars($q['topic']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <a href="<?= BASE_URL ?>/dashboard/view_quiz.php?id=<?= $q['quiz_id'] ?>" style="font-weight:600; font-size:0.88rem;">
                                                <?= htmlspecialchars($q['quiz_title']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php 
                                            $typeBadge = 'badge-secondary';
                                            $typeLabel = 'MCQ';
                                            if (($q['question_type'] ?? '') === 'true_false') {
                                                $typeLabel = 'True/False';
                                                $typeBadge = 'badge-primary';
                                            } elseif (($q['question_type'] ?? '') === 'multiple_select') {
                                                $typeLabel = 'Multi-Select';
                                                $typeBadge = 'badge-warning';
                                            }
                                            ?>
                                            <span class="badge <?= $typeBadge ?>"><?= $typeLabel ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $diff = strtolower($q['difficulty'] ?? 'medium');
                                            $diffClass = $diff === 'easy' ? 'badge-success' : ($diff === 'hard' ? 'badge-danger' : 'badge-warning');
                                            ?>
                                            <span class="badge <?= $diffClass ?>"><?= ucfirst($diff) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($q['ai_generated'])): ?>
                                                <span class="badge badge-primary" style="background:var(--brand-purple-light); color:var(--brand-purple); border:1px solid #ddd6fe;">
                                                    ✨ AI Generated
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">✍️ Manual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <div style="display:inline-flex; gap:0.4rem; align-items:center;">
                                                <a href="<?= BASE_URL ?>/dashboard/edit_quiz.php?id=<?= $q['quiz_id'] ?>" class="btn btn-secondary btn-sm" title="Edit parent quiz">Edit</a>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteBankQuestion(<?= $q['question_id'] ?>, this, <?= !empty($q['ai_generated']) ? 'true' : 'false' ?>)" title="Delete this question">🗑️</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        function toggleTopicSection(id) {
            const body = document.getElementById(`body_${id}`);
            const arrow = document.getElementById(`arrow_${id}`);
            if (body) {
                body.classList.toggle('collapsed');
            }
            if (arrow) {
                arrow.classList.toggle('rotated');
            }
        }

        async function startTopicQuiz(topicName, count) {
            if (!confirm(`🚀 Launch Live Multiplayer Quiz for "${topicName}" (${count} questions)?\n\nThis will immediately open your host control room and generate a room code for students to join.`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('topic', topicName);

                const res = await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/session.php?action=start_topic', {
                    method: 'POST',
                    body: formData
                });

                if (res && res.data && res.data.session_id) {
                    window.location.href = `<?= BASE_URL ?>/live/host.php?session_id=${res.data.session_id}`;
                } else {
                    alert('Could not start live session. Please try again.');
                }
            } catch (err) {
                alert(err.message || 'Failed to start topic quiz session.');
            }
        }

        async function deleteBankQuestion(questionId, btn, isAi, topicCardIndex) {
            if (!confirm('Are you sure you want to delete this question? This action cannot be undone.')) {
                return;
            }

            const row = btn.closest('tr');
            btn.disabled = true;
            btn.textContent = '⏳';

            try {
                const formData = new FormData();
                formData.append('id', questionId);

                await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/quizzes.php?action=delete_question', {
                    method: 'POST',
                    body: formData
                });

                // Smoothly animate removal
                if (row) {
                    row.style.transition = 'all 0.3s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'scale(0.96)';
                    setTimeout(() => {
                        const tbody = row.closest('tbody');
                        row.remove();

                        // If topic view, update the topic card's badge count and live quiz button
                        if (topicCardIndex) {
                            const card = document.getElementById(`topicCard_${topicCardIndex}`);
                            if (card) {
                                const countBadge = card.querySelector('.topic-title-area .badge-secondary');
                                const remainingRows = tbody ? tbody.querySelectorAll('tr').length : 0;
                                if (countBadge) {
                                    countBadge.textContent = `${remainingRows} Questions`;
                                }
                                if (remainingRows === 0) {
                                    card.style.transition = 'all 0.3s ease';
                                    card.style.opacity = '0';
                                    setTimeout(() => card.remove(), 300);
                                }
                            }
                        }

                        // Decrement global stats counter if present
                        const statPills = document.querySelectorAll('.card p strong, .stat-value');
                        statPills.forEach(sp => {
                            const val = parseInt(sp.textContent);
                            if (!isNaN(val) && val > 0) {
                                sp.textContent = (val - 1);
                            }
                        });
                    }, 300);
                }
            } catch (err) {
                alert(err.message || 'Failed to delete question.');
                btn.disabled = false;
                btn.textContent = '🗑️';
            }
        }
    </script>
</body>
</html>
