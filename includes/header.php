<?php
// Shared Navigation Header for QUIZLY (Teacher & Trainer)
// Usage: require_once __DIR__ . '/header.php';

if (!isset($user)) {
    $user = getCurrentUser();
}

$roleLabel = 'Teacher Dashboard';
if (isset($user['role']) && strtoupper($user['role']) === 'TRAINER') {
    $roleLabel = 'Trainer Dashboard';
}
?>
<nav class="navbar">
    <a href="<?= BASE_URL ?>/dashboard/index.php" class="brand-logo">
        ⚡ QUIZ<span class="accent">LY</span>
    </a>
    <button class="menu-toggle" aria-label="Toggle Navigation">☰</button>
    <div class="nav-links">
        <?php if ($user): ?>
            <span style="color:var(--text-secondary); font-size:0.9rem;">
                <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong> &bull; <?= $roleLabel ?>
            </span>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger btn-sm">Logout</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost">Login</a>
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary">Register</a>
        <?php endif; ?>
    </div>
</nav>
