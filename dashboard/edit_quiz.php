<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$quizId = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <style>
        .question-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            padding: 1.75rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .options-builder {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        @media (max-width: 640px) {
            .options-builder {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="<?= BASE_URL ?>/dashboard/index.php" class="brand-logo">
            ⚡ QUIZ<span class="accent">LY</span>
        </a>
        <div class="nav-links">
            <a href="<?= BASE_URL ?>/dashboard/quizzes.php" class="btn btn-secondary btn-sm">Cancel</a>
        </div>
    </nav>

    <div class="container" style="max-width: 920px;">
        <div style="margin-bottom: 2rem;">
            <a href="<?= BASE_URL ?>/dashboard/quizzes.php" style="font-weight:700; font-size:0.9rem; color:var(--brand-purple);">← Back to Quizzes</a>
            <h1 style="font-size: 2.2rem; font-weight: 800; color:var(--text-primary); margin-top:0.25rem;">Edit Quiz</h1>
        </div>

        <div id="quizAlert"></div>

        <form id="quizBuilderForm">
            <div class="card" style="margin-bottom: 2rem;">
                <h3 style="margin-bottom: 1.25rem; font-size: 1.3rem; color:var(--text-primary);">1. Quiz Information</h3>
                
                <div class="form-group">
                    <label class="form-label">Quiz Title</label>
                    <input type="text" id="quiz_title" class="form-input" required style="height: 48px;">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="quiz_description" class="form-textarea" rows="3"></textarea>
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <input type="text" id="quiz_category" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Difficulty</label>
                        <select id="quiz_difficulty" class="form-select">
                            <option value="EASY">Easy</option>
                            <option value="MEDIUM">Medium</option>
                            <option value="HARD">Hard</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="quiz_status" class="form-select">
                            <option value="PUBLISHED">Published</option>
                            <option value="DRAFT">Draft</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem; flex-wrap:wrap; gap:1rem;">
                    <h3 style="font-size: 1.3rem; color:var(--text-primary);">2. Questions & Answer Options</h3>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <button type="button" onclick="openImportModal()" class="btn btn-secondary">📁 Import from Question Bank</button>
                        <button type="button" onclick="addQuestionCard()" class="btn btn-secondary">+ Add Question</button>
                    </div>
                </div>

                <div id="questionsContainer"></div>

                <div style="margin-top: 2rem; display:flex; gap:1rem; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary btn-lg" style="flex:1; min-width:200px; border-radius:14px;">Save Changes 💾</button>
                    <button type="button" onclick="deleteThisQuiz()" class="btn btn-danger btn-lg" style="border-radius:14px;">🗑️ Delete Quiz</button>
                </div>
            </div>
        </form>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        const quizId = <?= $quizId ?>;
        let questionCount = 0;

        async function loadQuizData() {
            try {
                const res = await QuizlyApp.fetchJson(`<?= BASE_URL ?>/api/quizzes.php?action=get&id=${quizId}`);
                const quiz = res.data;

                document.getElementById('quiz_title').value = quiz.title;
                document.getElementById('quiz_description').value = quiz.description || '';
                document.getElementById('quiz_category').value = quiz.category;
                document.getElementById('quiz_difficulty').value = quiz.difficulty;
                document.getElementById('quiz_status').value = quiz.status;

                document.getElementById('questionsContainer').innerHTML = '';
                if (quiz.questions && quiz.questions.length > 0) {
                    quiz.questions.forEach(q => {
                        const optsMap = {};
                        let correctOpt = 'A';
                        q.options.forEach(opt => {
                            optsMap[opt.option_key] = { text: opt.option_text };
                            if (parseInt(opt.is_correct) === 1) {
                                correctOpt = opt.option_key;
                            }
                        });

                        addQuestionCard({
                            question_text: q.question_text,
                            timer_seconds: q.timer_seconds,
                            correct_option: correctOpt,
                            options: optsMap
                        });
                    });
                } else {
                    addQuestionCard();
                }
            } catch (err) {
                QuizlyApp.showAlert('quizAlert', 'Failed loading quiz: ' + err.message, 'danger');
            }
        }

        function addQuestionCard(data = null) {
            questionCount++;
            const container = document.getElementById('questionsContainer');
            const qId = questionCount;

            const card = document.createElement('div');
            card.className = 'question-card';
            card.id = `qCard_${qId}`;

            card.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.25rem;">
                    <h4 style="color:var(--brand-purple); font-size:1.1rem;">Question #${qId}</h4>
                    <button type="button" onclick="removeQuestionCard(${qId})" class="btn btn-danger btn-sm">Remove</button>
                </div>

                <div class="form-group">
                    <label class="form-label">Question Text</label>
                    <input type="text" class="form-input q-text" placeholder="Enter question prompt..." required value="${QuizlyApp.escapeHtml(data?.question_text || '')}" style="height:48px;">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Timer (Seconds)</label>
                        <select class="form-select q-timer">
                            <option value="10" ${data?.timer_seconds == 10 ? 'selected' : ''}>10 Seconds</option>
                            <option value="15" ${data?.timer_seconds == 15 ? 'selected' : ''}>15 Seconds</option>
                            <option value="20" ${!data || data?.timer_seconds == 20 ? 'selected' : ''}>20 Seconds</option>
                            <option value="30" ${data?.timer_seconds == 30 ? 'selected' : ''}>30 Seconds</option>
                            <option value="60" ${data?.timer_seconds == 60 ? 'selected' : ''}>60 Seconds</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Correct Option</label>
                        <select class="form-select q-correct">
                            <option value="A" ${data?.correct_option === 'A' ? 'selected' : ''}>Option A</option>
                            <option value="B" ${data?.correct_option === 'B' ? 'selected' : ''}>Option B</option>
                            <option value="C" ${data?.correct_option === 'C' ? 'selected' : ''}>Option C</option>
                            <option value="D" ${data?.correct_option === 'D' ? 'selected' : ''}>Option D</option>
                        </select>
                    </div>
                </div>

                <div class="options-builder">
                    <div class="form-group">
                        <label class="form-label" style="color:var(--opt-a);">Option A</label>
                        <input type="text" class="form-input opt-a" placeholder="Option A text" required value="${QuizlyApp.escapeHtml(data?.options?.A?.text || '')}">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:var(--opt-b);">Option B</label>
                        <input type="text" class="form-input opt-b" placeholder="Option B text" required value="${QuizlyApp.escapeHtml(data?.options?.B?.text || '')}">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:var(--opt-c);">Option C</label>
                        <input type="text" class="form-input opt-c" placeholder="Option C text" required value="${QuizlyApp.escapeHtml(data?.options?.C?.text || '')}">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color:var(--opt-d);">Option D</label>
                        <input type="text" class="form-input opt-d" placeholder="Option D text" required value="${QuizlyApp.escapeHtml(data?.options?.D?.text || '')}">
                    </div>
                </div>
            `;

            container.appendChild(card);
        }

        function removeQuestionCard(qId) {
            const el = document.getElementById(`qCard_${qId}`);
            if (el) el.remove();
        }

        loadQuizData();

        document.getElementById('quizBuilderForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            QuizlyApp.clearAlert('quizAlert');

            const title = document.getElementById('quiz_title').value;
            const description = document.getElementById('quiz_description').value;
            const category = document.getElementById('quiz_category').value;
            const difficulty = document.getElementById('quiz_difficulty').value;
            const status = document.getElementById('quiz_status').value;

            const qCards = document.querySelectorAll('.question-card');
            const questions = [];
            qCards.forEach(card => {
                const qText = card.querySelector('.q-text').value;
                const timer = parseInt(card.querySelector('.q-timer').value);
                const correctOpt = card.querySelector('.q-correct').value;

                questions.push({
                    question_text: qText,
                    timer_seconds: timer,
                    max_points: 1000,
                    correct_option: correctOpt,
                    options: {
                        A: { text: card.querySelector('.opt-a').value, is_correct: correctOpt === 'A' },
                        B: { text: card.querySelector('.opt-b').value, is_correct: correctOpt === 'B' },
                        C: { text: card.querySelector('.opt-c').value, is_correct: correctOpt === 'C' },
                        D: { text: card.querySelector('.opt-d').value, is_correct: correctOpt === 'D' }
                    }
                });
            });

            try {
                const payload = {
                    id: quizId,
                    title, description, category, difficulty, status, questions
                };

                await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/quizzes.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                QuizlyApp.showAlert('quizAlert', 'Quiz updated successfully! Redirecting to dashboard...', 'success');
                setTimeout(() => {
                    window.location.href = '<?= BASE_URL ?>/dashboard/index.php';
                }, 800);
            } catch (err) {
                QuizlyApp.showAlert('quizAlert', err.message, 'danger');
            }
        });

        // QUESTION BANK IMPORT MODAL LOGIC
        let availableBankQuestions = [];

        async function openImportModal() {
            let modal = document.getElementById('importModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'importModal';
                modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; display:flex; align-items:center; justify-content:center; padding:1.5rem;';
                modal.innerHTML = `
                    <div style="background:#FFFFFF; border-radius:var(--radius-xl); max-width:760px; width:100%; max-height:85vh; display:flex; flex-direction:column; box-shadow:var(--shadow-lg); overflow:hidden;">
                        <div style="padding:1.5rem; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <h3 style="font-size:1.3rem; font-weight:800; color:var(--text-primary);">📁 Import from Question Bank</h3>
                                <p style="font-size:0.85rem; color:var(--text-secondary); margin-top:0.2rem;">Select questions from your organization pool (including AI-generated ones)</p>
                            </div>
                            <button type="button" onclick="closeImportModal()" class="btn btn-secondary btn-sm" style="font-size:1.1rem; padding:0.2rem 0.6rem;">✕</button>
                        </div>
                        <div style="padding:1rem 1.5rem; border-bottom:1px solid var(--border-color); background:var(--bg-alt);">
                            <input type="text" id="importSearchInput" class="form-input" placeholder="Search question prompts or topics..." oninput="filterImportQuestions(this.value)">
                        </div>
                        <div id="importQuestionsList" style="padding:1.5rem; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:0.75rem;">
                            <p style="text-align:center; color:var(--text-muted);">Loading questions...</p>
                        </div>
                        <div style="padding:1.25rem 1.5rem; border-top:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; background:#FAFAFA;">
                            <span id="importSelectedCount" style="font-size:0.9rem; font-weight:700; color:var(--brand-purple);">0 questions selected</span>
                            <div style="display:flex; gap:0.5rem;">
                                <button type="button" onclick="closeImportModal()" class="btn btn-secondary">Cancel</button>
                                <button type="button" onclick="applyImportedQuestions()" class="btn btn-primary">+ Insert Selected Questions</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            modal.style.display = 'flex';
            await loadBankQuestions();
        }

        function closeImportModal() {
            const modal = document.getElementById('importModal');
            if (modal) modal.style.display = 'none';
        }

        async function loadBankQuestions() {
            const listEl = document.getElementById('importQuestionsList');
            listEl.innerHTML = '<p style="text-align:center; color:var(--text-muted); padding:2rem;">Loading questions...</p>';

            try {
                const res = await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/quizzes.php?action=question_bank');
                availableBankQuestions = (res.data || []).map(q => ({ ...q, _checked: false }));
                renderImportList(availableBankQuestions);
            } catch (err) {
                listEl.innerHTML = `<p style="text-align:center; color:var(--danger); padding:2rem;">Failed to load questions: ${QuizlyApp.escapeHtml(err.message)}</p>`;
            }
        }

        function filterImportQuestions(query) {
            const qLower = query.toLowerCase().trim();
            const filtered = availableBankQuestions.filter(q => 
                q.question_text.toLowerCase().includes(qLower) || 
                (q.topic && q.topic.toLowerCase().includes(qLower)) ||
                (q.quiz_title && q.quiz_title.toLowerCase().includes(qLower))
            );
            renderImportList(filtered);
        }

        function renderImportList(questions) {
            const listEl = document.getElementById('importQuestionsList');
            if (questions.length === 0) {
                listEl.innerHTML = '<p style="text-align:center; color:var(--text-muted); padding:2rem;">No matching questions found in your Question Bank.</p>';
                return;
            }

            listEl.innerHTML = questions.map(q => `
                <label style="display:flex; gap:0.75rem; align-items:flex-start; padding:0.9rem; border:1px solid var(--border-color); border-radius:var(--radius-md); background:#FFFFFF; cursor:pointer; transition:all 0.15s ease;">
                    <input type="checkbox" style="margin-top:0.25rem; width:18px; height:18px;" onchange="toggleImportSelect(${q.id}, this.checked)" ${q._checked ? 'checked' : ''}>
                    <div style="flex:1;">
                        <div style="font-weight:700; color:var(--text-primary); font-size:0.95rem;">${QuizlyApp.escapeHtml(q.question_text)}</div>
                        <div style="display:flex; gap:0.5rem; align-items:center; margin-top:0.4rem; flex-wrap:wrap;">
                            ${q.ai_generated == 1 ? '<span class="badge badge-primary" style="font-size:0.75rem;">✨ AI Generated</span>' : ''}
                            ${q.topic ? `<span class="badge badge-secondary" style="font-size:0.75rem;">🏷️ ${QuizlyApp.escapeHtml(q.topic)}</span>` : ''}
                            <span class="badge badge-secondary" style="font-size:0.75rem;">${QuizlyApp.escapeHtml(q.difficulty.toUpperCase())}</span>
                            <span style="font-size:0.8rem; color:var(--text-muted);">from: ${QuizlyApp.escapeHtml(q.quiz_title)}</span>
                        </div>
                    </div>
                </label>
            `).join('');
            updateImportSelectedCount();
        }

        function toggleImportSelect(id, checked) {
            const q = availableBankQuestions.find(item => item.id == id);
            if (q) q._checked = checked;
            updateImportSelectedCount();
        }

        function updateImportSelectedCount() {
            const count = availableBankQuestions.filter(q => q._checked).length;
            const el = document.getElementById('importSelectedCount');
            if (el) el.innerText = `${count} question(s) selected`;
        }

        function applyImportedQuestions() {
            const selected = availableBankQuestions.filter(q => q._checked);
            if (selected.length === 0) {
                alert('Please select at least one question to import.');
                return;
            }

            selected.forEach(q => {
                addQuestionCard({
                    question_text: q.question_text,
                    timer_seconds: q.timer_seconds || 20,
                    correct_option: q.correct_option || 'A',
                    options: q.options || {}
                });
            });

            closeImportModal();
            QuizlyApp.showAlert('quizAlert', `Successfully imported ${selected.length} question(s) from Question Bank!`, 'success');
        }

        async function deleteThisQuiz() {
            if (!confirm('Are you sure you want to delete this quiz? All questions, session history, and participant answers will be permanently deleted.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('id', quizId);

                await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/quizzes.php?action=delete', {
                    method: 'POST',
                    body: formData
                });

                window.location.href = '<?= BASE_URL ?>/dashboard/quizzes.php';
            } catch (err) {
                alert(err.message || 'Failed to delete quiz');
            }
        }
    </script>
</body>
</html>

