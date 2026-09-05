<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

$user = requireRole(['PLATFORM_ADMIN', 'ORG_OWNER', 'TEACHER']);
$orgId = getAuthOrgId();
$db = Database::getConnection();

$csrfToken = generateCsrfToken();

// Fetch existing quizzes for this organization for optional direct assignment
$stmtQuizzes = $db->prepare("SELECT id, title FROM quizzes WHERE organization_id = :org_id ORDER BY updated_at DESC");
$stmtQuizzes->execute(['org_id' => $orgId]);
$orgQuizzes = $stmtQuizzes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <title>Generate Questions with AI — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <style>
        .ai-badge {
            background: linear-gradient(135deg, #6D28D9 0%, #2563EB 100%);
            color: #FFFFFF;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 0.25rem 0.65rem;
            border-radius: var(--radius-full);
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .generator-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 2.25rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .generator-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--grad-primary);
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
        }

        @media (max-width: 768px) {
            .form-grid-2, .form-grid-3 {
                grid-template-columns: 1fr;
            }
            .generator-card {
                padding: 1.5rem;
            }
        }

        /* Advanced Settings Accordion */
        .advanced-details {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem 1.25rem;
            margin-top: 1.5rem;
            background: var(--bg-alt);
        }
        .advanced-summary {
            cursor: pointer;
            font-weight: 700;
            color: var(--brand-purple);
            display: flex;
            align-items: center;
            justify-content: space-between;
            user-select: none;
            outline: none;
        }
        .advanced-content {
            padding-top: 1.25rem;
            margin-top: 0.75rem;
            border-top: 1px dashed var(--border-color);
        }

        /* Loading Overlay & State */
        .ai-loading-box {
            display: none;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 3.5rem 2rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            animation: fadeIn 0.3s ease;
        }
        .ai-spinner {
            width: 54px;
            height: 54px;
            border: 4px solid var(--brand-purple-light);
            border-top-color: var(--brand-purple);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1.5rem auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Question Preview Cards */
        .preview-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.25rem 1.5rem;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xs);
        }

        .q-preview-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-xs);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            position: relative;
        }
        .q-preview-card.selected {
            border-color: var(--brand-purple);
            box-shadow: 0 0 0 2px var(--brand-purple-light);
        }
        .q-preview-card.has-duplicate-warning {
            border-color: var(--warning);
            background: #FFFDF7;
        }

        .q-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .q-card-badges {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .q-options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin: 1.25rem 0;
        }
        @media (max-width: 640px) {
            .q-options-grid {
                grid-template-columns: 1fr;
            }
        }

        .q-opt-item {
            padding: 0.85rem 1rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border-color);
            background: var(--bg-body);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.15s ease;
        }
        .q-opt-item.is-correct {
            border-color: var(--success);
            background: var(--success-light);
            font-weight: 700;
            color: #065F46;
        }

        .q-opt-key {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.85rem;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        .q-opt-item.is-correct .q-opt-key {
            background: var(--success);
            color: #FFFFFF;
            border-color: var(--success);
        }

        .q-meta-box {
            background: var(--bg-subtle);
            border-radius: var(--radius-sm);
            padding: 0.85rem 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .q-actions-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
        }

        /* Inline Edit Box */
        .q-edit-box {
            display: none;
            background: #FAFBFF;
            border: 2px dashed var(--brand-purple);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        /* Success Celebration Box */
        .success-box {
            display: none;
            background: #FFFFFF;
            border: 1px solid var(--success);
            border-radius: var(--radius-xl);
            padding: 3.5rem 2rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            animation: fadeIn 0.3s ease;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'ai_generate'; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <!-- BREADCRUMBS & TITLE -->
            <div style="margin-bottom: 2rem;">
                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem; font-size:0.9rem; font-weight:700;">
                    <a href="<?= BASE_URL ?>/dashboard/question_bank.php" style="color:var(--brand-purple);">Question Bank</a>
                    <span style="color:var(--text-light);">/</span>
                    <span style="color:var(--text-secondary);">✨ Generate with AI</span>
                </div>
                <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                    <h1 style="font-size: 2.2rem; font-weight: 800; color:var(--text-primary); letter-spacing:-0.02em;">
                        Generate Questions with AI
                    </h1>
                    <span class="ai-badge">⚡ POWERED BY GEMINI</span>
                </div>
                <p style="color:var(--text-secondary); font-size:1rem; margin-top:0.35rem; max-width: 800px;">
                    Create high-quality quiz questions instantly using AI. Review every question before adding it to your question bank.
                </p>
            </div>

            <div id="aiAlert"></div>

            <!-- GENERATOR CONFIGURATION CARD -->
            <div class="generator-card" id="configCard">
                <form id="aiGenerateForm">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label" for="subject_topic">
                                Subject / Topic <span style="color:var(--danger);">*</span>
                            </label>
                            <input type="text" id="subject_topic" name="topic" class="form-input" placeholder="e.g. Computer Networks, Biology, World History" required style="height:48px;">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="chapter_concept">
                                Chapter / Concept
                            </label>
                            <input type="text" id="chapter_concept" name="chapter" class="form-input" placeholder="e.g. TCP/IP, Transport Layer, Photosynthesis" style="height:48px;">
                        </div>
                    </div>

                    <div class="form-grid-3" style="margin-top: 0.5rem;">
                        <div class="form-group">
                            <label class="form-label" for="question_count">Number of Questions</label>
                            <select id="question_count" name="question_count" class="form-select" style="height:48px;">
                                <option value="5" selected>5 Questions</option>
                                <option value="10">10 Questions</option>
                                <option value="20">20 Questions</option>
                                <option value="30">30 Questions</option>
                                <option value="50">50 Questions</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="question_type">Question Type</label>
                            <select id="question_type" name="question_type" class="form-select" style="height:48px;">
                                <option value="multiple_choice" selected>Multiple Choice (MCQ)</option>
                                <option value="true_false">True / False</option>
                                <option value="multiple_select">Multiple Select</option>
                                <option value="mixed">Mixed Types</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="difficulty">Difficulty</label>
                            <select id="difficulty" name="difficulty" class="form-select" style="height:48px;">
                                <option value="easy">Easy</option>
                                <option value="medium" selected>Medium</option>
                                <option value="hard">Hard</option>
                                <option value="mixed">Mixed Difficulty</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2" style="margin-top: 0.5rem;">
                        <div class="form-group">
                            <label class="form-label" for="education_level">Education Level</label>
                            <select id="education_level" name="education_level" class="form-select" style="height:48px;">
                                <option value="School">School</option>
                                <option value="Higher Secondary">Higher Secondary (11th/12th)</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Undergraduate" selected>Undergraduate / College</option>
                                <option value="Postgraduate">Postgraduate</option>
                                <option value="Professional">Professional / Certification</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="language">Language</label>
                            <select id="language" name="language" class="form-select" style="height:48px;">
                                <option value="English" selected>English</option>
                                <option value="Spanish">Spanish (Español)</option>
                                <option value="French">French (Français)</option>
                                <option value="German">German (Deutsch)</option>
                                <option value="Hindi">Hindi (हिंदी)</option>
                                <option value="Tamil">Tamil (தமிழ்)</option>
                            </select>
                        </div>
                    </div>

                    <!-- EXPANDABLE ADVANCED SETTINGS -->
                    <details class="advanced-details">
                        <summary class="advanced-summary">
                            <span>⚙️ Advanced Settings</span>
                            <span style="font-size:0.85rem; color:var(--text-muted); font-weight:600;">Custom Timer, Points, Explanations & Custom Instructions ▼</span>
                        </summary>
                        <div class="advanced-content">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">Time per Question (Seconds)</label>
                                    <select id="adv_timer" name="timer_seconds" class="form-select">
                                        <option value="10">10 Seconds</option>
                                        <option value="15">15 Seconds</option>
                                        <option value="20" selected>20 Seconds (Default)</option>
                                        <option value="30">30 Seconds</option>
                                        <option value="45">45 Seconds</option>
                                        <option value="60">60 Seconds</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Marks per Question (Points)</label>
                                    <select id="adv_points" name="max_points" class="form-select">
                                        <option value="500">500 Points</option>
                                        <option value="1000" selected>1000 Points (Default)</option>
                                        <option value="1500">1500 Points</option>
                                        <option value="2000">2000 Points</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display:flex; gap:1.5rem; flex-wrap:wrap; margin: 1rem 0;">
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.9rem; font-weight:600; cursor:pointer;">
                                    <input type="checkbox" id="adv_include_explanation" name="include_explanation" value="1" checked>
                                    Include Explanation
                                </label>
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.9rem; font-weight:600; cursor:pointer;">
                                    <input type="checkbox" id="adv_include_topic" name="include_topic" value="1" checked>
                                    Include Topic Tag
                                </label>
                                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.9rem; font-weight:600; cursor:pointer;">
                                    <input type="checkbox" id="adv_include_objective" name="include_learning_objective" value="1" checked>
                                    Include Learning Objective
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="additional_instructions">Additional Custom Instructions</label>
                                <textarea id="additional_instructions" name="additional_instructions" class="form-textarea" rows="2" placeholder="e.g. Focus on conceptual scenario-based questions and avoid simple memorization queries."></textarea>
                            </div>
                        </div>
                    </details>

                    <div style="margin-top: 2rem;">
                        <button type="submit" id="btnGenerate" class="btn btn-primary btn-lg" style="width: 100%; border-radius: 14px; height: 54px; font-size: 1.1rem; background: var(--grad-primary); box-shadow: var(--shadow-glow);">
                            ✨ Generate Questions
                        </button>
                    </div>
                </form>
            </div>

            <!-- LOADING BOX -->
            <div id="aiLoadingBox" class="ai-loading-box">
                <div class="ai-spinner"></div>
                <h3 style="font-size: 1.4rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.5rem;">
                    ✨ Generating Questions...
                </h3>
                <p style="color: var(--text-secondary); font-size: 1rem;">
                    AI is creating and validating your questions with pedagogical rigor. Please hold on.
                </p>
            </div>

            <!-- PREVIEW DECK CONTAINER -->
            <div id="previewDeck" style="display:none;">
                <div class="preview-header-bar">
                    <div>
                        <h2 style="font-size: 1.3rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.2rem;">
                            Generated Questions (<span id="selectedCountText">0/0</span> Selected)
                        </h2>
                        <p style="font-size: 0.88rem; color: var(--text-secondary);">
                            Review, edit, or regenerate any question before adding to your Question Bank.
                        </p>
                    </div>

                    <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                        <button type="button" onclick="selectAllQuestions(true)" class="btn btn-secondary btn-sm">Select All</button>
                        <button type="button" onclick="selectAllQuestions(false)" class="btn btn-secondary btn-sm">Deselect All</button>
                        
                        <div style="display:inline-flex; align-items:center; gap:0.5rem;">
                            <label style="font-size:0.85rem; font-weight:700; color:var(--text-secondary);">Target:</label>
                            <select id="targetDestination" class="form-select" style="height:38px; width:auto; font-size:0.88rem;">
                                <option value="repo">📁 Question Bank Repository</option>
                                <?php if (!empty($orgQuizzes)): ?>
                                    <optgroup label="Existing Quizzes">
                                        <?php foreach ($orgQuizzes as $oq): ?>
                                            <option value="quiz_<?= $oq['id'] ?>">📝 <?= htmlspecialchars($oq['title']) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <option value="new_quiz">✨ Create New Quiz</option>
                            </select>
                        </div>

                        <button type="button" id="btnSaveQuestions" onclick="saveApprovedBatch()" class="btn btn-primary btn-sm" style="background:var(--success); border-color:var(--success);">
                            ✓ Add Selected to Question Bank
                        </button>
                    </div>
                </div>

                <!-- CARDS CONTAINER -->
                <div id="questionsContainer"></div>
            </div>

            <!-- CELEBRATION SUCCESS BOX -->
            <div id="successBox" class="success-box">
                <div style="font-size: 3.5rem; margin-bottom: 1rem;">🎉</div>
                <h2 id="successMessage" style="font-size: 1.8rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.5rem;">
                    ✓ Questions added to Question Bank!
                </h2>
                <p style="color: var(--text-secondary); font-size: 1rem; margin-bottom: 2rem;">
                    All approved questions have been persisted and are ready for your live quizzes and assessments.
                </p>
                <div style="display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;">
                    <a href="<?= BASE_URL ?>/dashboard/question_bank.php" class="btn btn-primary btn-lg">View Question Bank</a>
                    <a id="btnOpenQuiz" href="<?= BASE_URL ?>/dashboard/quizzes.php" class="btn btn-secondary btn-lg">Add to Existing Quiz</a>
                    <button type="button" onclick="resetToGenerateMore()" class="btn btn-ghost btn-lg">✨ Generate More</button>
                </div>
            </div>
        </main>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let generatedQuestions = [];
        let originalContext = {};

        // FORM SUBMISSION & GENERATION
        document.getElementById('aiGenerateForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            QuizlyApp.clearAlert('aiAlert');

            const topic = document.getElementById('subject_topic').value.trim();
            const chapter = document.getElementById('chapter_concept').value.trim();
            const count = parseInt(document.getElementById('question_count').value);
            const type = document.getElementById('question_type').value;
            const diff = document.getElementById('difficulty').value;
            const level = document.getElementById('education_level').value;
            const lang = document.getElementById('language').value;

            const timer = parseInt(document.getElementById('adv_timer').value);
            const points = parseInt(document.getElementById('adv_points').value);
            const incExpl = document.getElementById('adv_include_explanation').checked ? 1 : 0;
            const incTopic = document.getElementById('adv_include_topic').checked ? 1 : 0;
            const incObj = document.getElementById('adv_include_objective').checked ? 1 : 0;
            const instructions = document.getElementById('additional_instructions').value.trim();

            if (!topic) {
                QuizlyApp.showAlert('aiAlert', 'Please provide a Subject or Topic.', 'danger');
                return;
            }

            originalContext = {
                topic, chapter, question_count: count, question_type: type,
                difficulty: diff, education_level: level, language: lang,
                timer_seconds: timer, max_points: points,
                include_explanation: incExpl, include_topic: incTopic,
                include_learning_objective: incObj, additional_instructions: instructions,
                csrf_token: CSRF_TOKEN
            };

            // Toggle loading state
            document.getElementById('btnGenerate').disabled = true;
            document.getElementById('aiLoadingBox').style.display = 'block';
            document.getElementById('previewDeck').style.display = 'none';
            document.getElementById('successBox').style.display = 'none';

            try {
                const res = await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/ai/generate_questions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: originalContext
                });

                generatedQuestions = res.data.questions.map(q => ({ ...q, _selected: true }));
                renderPreviewCards();

                document.getElementById('aiLoadingBox').style.display = 'none';
                document.getElementById('previewDeck').style.display = 'block';
                document.getElementById('previewDeck').scrollIntoView({ behavior: 'smooth' });
            } catch (err) {
                document.getElementById('aiLoadingBox').style.display = 'none';
                QuizlyApp.showAlert('aiAlert', err.message, 'danger');
            } finally {
                document.getElementById('btnGenerate').disabled = false;
            }
        });

        // RENDER PREVIEW CARDS
        function renderPreviewCards() {
            const container = document.getElementById('questionsContainer');
            container.innerHTML = '';

            const selectedCount = generatedQuestions.filter(q => q._selected).length;
            document.getElementById('selectedCountText').innerText = `${selectedCount}/${generatedQuestions.length}`;

            generatedQuestions.forEach((q, idx) => {
                const card = document.createElement('div');
                card.className = `q-preview-card ${q._selected ? 'selected' : ''} ${q.is_duplicate ? 'has-duplicate-warning' : ''}`;
                card.id = `qCard_${q.id}`;

                let diffBadgeClass = 'badge-warning';
                if (q.difficulty === 'easy') diffBadgeClass = 'badge-success';
                if (q.difficulty === 'hard') diffBadgeClass = 'badge-danger';

                let typeLabel = 'MCQ';
                if (q.question_type === 'true_false') typeLabel = 'True/False';
                if (q.question_type === 'multiple_select') typeLabel = 'Multi-Select';

                const optionsHtml = (q.options || []).map(opt => `
                    <div class="q-opt-item ${opt.is_correct ? 'is-correct' : ''}">
                        <span class="q-opt-key">${QuizlyApp.escapeHtml(opt.key)}</span>
                        <span style="flex:1;">${QuizlyApp.escapeHtml(opt.text)}</span>
                        ${opt.is_correct ? '<span style="color:var(--success); font-weight:800;">✓ Correct</span>' : ''}
                    </div>
                `).join('');

                card.innerHTML = `
                    <div class="q-card-top">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <input type="checkbox" id="chk_${q.id}" ${q._selected ? 'checked' : ''} onchange="toggleSelectQuestion('${q.id}', this.checked)" style="width:20px; height:20px; cursor:pointer;">
                            <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-primary); margin:0;">
                                Question #${idx + 1}
                            </h3>
                        </div>
                        <div class="q-card-badges">
                            <span class="badge ${diffBadgeClass}">${QuizlyApp.escapeHtml(q.difficulty.toUpperCase())}</span>
                            <span class="badge badge-secondary">${typeLabel}</span>
                            ${q.topic ? `<span class="badge badge-primary">🏷️ ${QuizlyApp.escapeHtml(q.topic)}</span>` : ''}
                            <span class="badge badge-secondary">⏱️ ${q.timer_seconds || 20}s</span>
                        </div>
                    </div>

                    ${q.is_duplicate ? `
                        <div class="alert alert-warning" style="margin-bottom:1rem; padding:0.6rem 1rem; font-size:0.88rem;">
                            ⚠️ <strong>Possible Duplicate:</strong> ${QuizlyApp.escapeHtml(q.duplicate_warning || 'Similar to existing question in bank')}
                        </div>
                    ` : ''}

                    <div style="font-size:1.1rem; font-weight:700; color:var(--text-primary); line-height:1.4; margin-bottom:1rem;" id="promptText_${q.id}">
                        ${QuizlyApp.escapeHtml(q.question_text)}
                    </div>

                    <div class="q-options-grid" id="optsGrid_${q.id}">
                        ${optionsHtml}
                    </div>

                    ${q.explanation ? `
                        <div class="q-meta-box">
                            <strong>💡 Explanation:</strong> ${QuizlyApp.escapeHtml(q.explanation)}
                        </div>
                    ` : ''}

                    ${q.learning_objective ? `
                        <div class="q-meta-box" style="margin-top:0.5rem; background:#F8FAFF;">
                            <strong>🎯 Learning Objective:</strong> ${QuizlyApp.escapeHtml(q.learning_objective)}
                        </div>
                    ` : ''}

                    <!-- INLINE EDIT BOX -->
                    <div class="q-edit-box" id="editBox_${q.id}">
                        <h4 style="color:var(--brand-purple); margin-bottom:1rem;">✏️ Edit Question #${idx + 1}</h4>
                        <div class="form-group">
                            <label class="form-label">Question Text</label>
                            <input type="text" id="editPrompt_${q.id}" class="form-input" value="${QuizlyApp.escapeHtml(q.question_text)}">
                        </div>
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Difficulty</label>
                                <select id="editDiff_${q.id}" class="form-select">
                                    <option value="easy" ${q.difficulty === 'easy' ? 'selected' : ''}>Easy</option>
                                    <option value="medium" ${q.difficulty === 'medium' ? 'selected' : ''}>Medium</option>
                                    <option value="hard" ${q.difficulty === 'hard' ? 'selected' : ''}>Hard</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Topic</label>
                                <input type="text" id="editTopic_${q.id}" class="form-input" value="${QuizlyApp.escapeHtml(q.topic || '')}">
                            </div>
                        </div>

                        <div style="margin-top:0.75rem;">
                            <label class="form-label">Options & Correct Answer</label>
                            ${(q.options || []).map((opt, oIdx) => `
                                <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                                    <span class="q-opt-key">${opt.key}</span>
                                    <input type="text" id="editOptText_${q.id}_${oIdx}" class="form-input" value="${QuizlyApp.escapeHtml(opt.text)}" style="flex:1;">
                                    <label style="display:flex; align-items:center; gap:0.25rem; font-size:0.85rem; cursor:pointer; min-width:85px;">
                                        <input type="${q.question_type === 'multiple_select' ? 'checkbox' : 'radio'}" name="editCorrect_${q.id}" value="${opt.key}" ${opt.is_correct ? 'checked' : ''}> Correct
                                    </label>
                                </div>
                            `).join('')}
                        </div>

                        <div class="form-group" style="margin-top:0.75rem;">
                            <label class="form-label">Explanation</label>
                            <textarea id="editExplanation_${q.id}" class="form-textarea" rows="2">${QuizlyApp.escapeHtml(q.explanation || '')}</textarea>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
                            <button type="button" onclick="cancelInlineEdit('${q.id}')" class="btn btn-secondary btn-sm">Cancel</button>
                            <button type="button" onclick="saveInlineEdit('${q.id}')" class="btn btn-primary btn-sm">Save Edits</button>
                        </div>
                    </div>

                    <!-- ACTION BUTTONS -->
                    <div class="q-actions-row">
                        <button type="button" onclick="openInlineEdit('${q.id}')" class="btn btn-secondary btn-sm">
                            ✏️ Edit
                        </button>
                        <button type="button" onclick="regenerateSingleQuestion('${q.id}')" id="btnRegen_${q.id}" class="btn btn-secondary btn-sm">
                            🔄 Regenerate
                        </button>
                        <button type="button" onclick="deleteQuestionCard('${q.id}')" class="btn btn-danger btn-sm">
                            🗑️ Delete
                        </button>
                    </div>
                `;

                container.appendChild(card);
            });
        }

        function toggleSelectQuestion(id, isSelected) {
            const q = generatedQuestions.find(item => item.id === id);
            if (q) {
                q._selected = isSelected;
                const card = document.getElementById(`qCard_${id}`);
                if (card) {
                    card.classList.toggle('selected', isSelected);
                }
            }
            const selectedCount = generatedQuestions.filter(item => item._selected).length;
            document.getElementById('selectedCountText').innerText = `${selectedCount}/${generatedQuestions.length}`;
        }

        function selectAllQuestions(selectAll) {
            generatedQuestions.forEach(q => {
                q._selected = selectAll;
                const chk = document.getElementById(`chk_${q.id}`);
                if (chk) chk.checked = selectAll;
                const card = document.getElementById(`qCard_${q.id}`);
                if (card) card.classList.toggle('selected', selectAll);
            });
            const selectedCount = generatedQuestions.filter(item => item._selected).length;
            document.getElementById('selectedCountText').innerText = `${selectedCount}/${generatedQuestions.length}`;
        }

        function deleteQuestionCard(id) {
            generatedQuestions = generatedQuestions.filter(q => q.id !== id);
            renderPreviewCards();
            if (generatedQuestions.length === 0) {
                document.getElementById('previewDeck').style.display = 'none';
            }
        }

        // INLINE EDITING
        function openInlineEdit(id) {
            const box = document.getElementById(`editBox_${id}`);
            if (box) {
                box.style.display = 'block';
                box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function cancelInlineEdit(id) {
            const box = document.getElementById(`editBox_${id}`);
            if (box) box.style.display = 'none';
        }

        function saveInlineEdit(id) {
            const q = generatedQuestions.find(item => item.id === id);
            if (!q) return;

            const newPrompt = document.getElementById(`editPrompt_${id}`).value.trim();
            if (!newPrompt) {
                alert('Question text cannot be empty.');
                return;
            }

            q.question_text = newPrompt;
            q.difficulty = document.getElementById(`editDiff_${id}`).value;
            q.topic = document.getElementById(`editTopic_${id}`).value.trim();
            q.explanation = document.getElementById(`editExplanation_${id}`).value.trim();

            const correctRadios = document.querySelectorAll(`input[name="editCorrect_${id}"]`);
            (q.options || []).forEach((opt, idx) => {
                const optInput = document.getElementById(`editOptText_${id}_${idx}`);
                if (optInput) {
                    opt.text = optInput.value.trim();
                }
                const radio = Array.from(correctRadios).find(r => r.value === opt.key);
                opt.is_correct = radio ? radio.checked : false;
            });

            cancelInlineEdit(id);
            renderPreviewCards();
        }

        // REGENERATE SINGLE QUESTION
        async function regenerateSingleQuestion(id) {
            const qIndex = generatedQuestions.findIndex(item => item.id === id);
            if (qIndex === -1) return;

            const q = generatedQuestions[qIndex];
            const btn = document.getElementById(`btnRegen_${id}`);
            const originalBtnText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ Regenerating...';

            const feedback = prompt('Optional: What specific change or focus would you like for this question? (Leave blank for general refresh)', '');
            if (feedback === null) {
                btn.disabled = false;
                btn.innerHTML = originalBtnText;
                return;
            }

            try {
                const res = await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/ai/regenerate_question.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: {
                        context: originalContext,
                        question: q,
                        instructions: feedback,
                        csrf_token: CSRF_TOKEN
                    }
                });

                const newQ = { ...res.data.question, _selected: true };
                generatedQuestions[qIndex] = newQ;
                renderPreviewCards();
            } catch (err) {
                alert('Failed to regenerate: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = originalBtnText;
            }
        }

        // BATCH SAVE TO QUESTION BANK
        async function saveApprovedBatch() {
            QuizlyApp.clearAlert('aiAlert');
            const approved = generatedQuestions.filter(q => q._selected);

            if (approved.length === 0) {
                QuizlyApp.showAlert('aiAlert', 'Please select at least one question to save.', 'warning');
                return;
            }

            const target = document.getElementById('targetDestination').value;
            let targetQuizId = null;
            let newQuizTitle = null;

            if (target.startsWith('quiz_')) {
                targetQuizId = parseInt(target.replace('quiz_', ''));
            } else if (target === 'new_quiz') {
                const topic = document.getElementById('subject_topic').value.trim() || 'General';
                newQuizTitle = prompt(`Enter Title for New Quiz:`, `${topic} AI Quiz`);
                if (!newQuizTitle) return;
            }

            const btnSave = document.getElementById('btnSaveQuestions');
            btnSave.disabled = true;
            btnSave.innerText = '💾 Saving...';

            try {
                const payload = {
                    questions: approved,
                    target_quiz_id: targetQuizId,
                    new_quiz_title: newQuizTitle,
                    csrf_token: CSRF_TOKEN
                };

                const res = await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/ai/save_questions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: payload
                });

                // Show celebration box
                document.getElementById('previewDeck').style.display = 'none';
                document.getElementById('configCard').style.display = 'none';
                document.getElementById('successMessage').innerText = `✓ ${res.data.saved_count} questions added to Question Bank`;
                document.getElementById('btnOpenQuiz').href = `<?= BASE_URL ?>/dashboard/edit_quiz.php?id=${res.data.quiz_id}`;
                document.getElementById('successBox').style.display = 'block';
                document.getElementById('successBox').scrollIntoView({ behavior: 'smooth' });

            } catch (err) {
                QuizlyApp.showAlert('aiAlert', err.message, 'danger');
                btnSave.disabled = false;
                btnSave.innerText = '✓ Add Selected to Question Bank';
            }
        }

        function resetToGenerateMore() {
            document.getElementById('configCard').style.display = 'block';
            document.getElementById('successBox').style.display = 'none';
            document.getElementById('previewDeck').style.display = 'none';
            generatedQuestions = [];
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
</body>
</html>
