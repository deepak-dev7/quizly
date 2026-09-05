<?php
// Shared Navigation Sidebar for QUIZLY (Teacher & Trainer)
// Usage: $activePage = 'dashboard'; require_once __DIR__ . '/sidebar.php';

if (!isset($user)) {
    $user = getCurrentUser();
}

$active = $activePage ?? 'dashboard';
?>
<aside class="sidebar">
    <div class="sidebar-header" style="padding: 0.5rem 0.75rem 0.75rem 0.75rem; font-size: 0.8rem; font-weight: 800; color: var(--brand-purple); text-transform: uppercase; letter-spacing: 0.05em;">
        ⚡ QUIZLY
    </div>
    <a href="<?= BASE_URL ?>/dashboard/index.php" class="sidebar-link <?= $active === 'dashboard' ? 'active' : '' ?>">
        <span class="sidebar-icon">📊</span> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/dashboard/quizzes.php" class="sidebar-link <?= $active === 'quizzes' ? 'active' : '' ?>">
        <span class="sidebar-icon">📝</span> My Quizzes
    </a>
    <a href="<?= BASE_URL ?>/dashboard/question_bank.php" class="sidebar-link <?= in_array($active, ['question_bank', 'all_questions', 'ai_generate']) ? 'active' : '' ?>">
        <span class="sidebar-icon">❓</span> Question Bank
    </a>
    <div class="sidebar-submenu">
        <a href="<?= BASE_URL ?>/dashboard/question_bank.php" class="sidebar-sublink <?= in_array($active, ['question_bank', 'all_questions']) ? 'active' : '' ?>">
            <span>📋</span> All Questions
        </a>
        <a href="<?= BASE_URL ?>/dashboard/create_quiz.php" class="sidebar-sublink <?= $active === 'add_question' ? 'active' : '' ?>">
            <span>➕</span> Add Question
        </a>
        <a href="<?= BASE_URL ?>/dashboard/ai_generate.php" class="sidebar-sublink <?= $active === 'ai_generate' ? 'active' : '' ?>" style="font-weight:700;">
            <span style="color:var(--brand-purple);">✨</span> Generate with AI
        </a>
    </div>
    <a href="<?= BASE_URL ?>/dashboard/live_sessions.php" class="sidebar-link <?= $active === 'live_sessions' ? 'active' : '' ?>">
        <span class="sidebar-icon">⚡</span> Live Sessions
    </a>
    <a href="<?= BASE_URL ?>/dashboard/results.php" class="sidebar-link <?= $active === 'results' ? 'active' : '' ?>">
        <span class="sidebar-icon">🏆</span> Results
    </a>
    <a href="<?= BASE_URL ?>/dashboard/analytics.php" class="sidebar-link <?= $active === 'analytics' ? 'active' : '' ?>">
        <span class="sidebar-icon">📈</span> Analytics
    </a>
    <a href="<?= BASE_URL ?>/dashboard/settings.php" class="sidebar-link <?= $active === 'settings' ? 'active' : '' ?>">
        <span class="sidebar-icon">⚙️</span> Settings
    </a>
    <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-color);">
        <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link" style="color: var(--danger);">
            <span class="sidebar-icon">🚪</span> Logout
        </a>
    </div>
</aside>
