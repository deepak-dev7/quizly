<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$currentUser = getCurrentUser();
if ($currentUser) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher / Trainer Sign In — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <style>
        .auth-container {
            min-height: calc(100vh - 140px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .auth-card {
            max-width: 440px;
            width: 100%;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            padding: 2.5rem 2rem;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
        }
        @media (max-width: 480px) {
            .auth-card {
                padding: 1.75rem 1.25rem;
                border-radius: var(--radius-lg);
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="<?= BASE_URL ?>/index.php" class="brand-logo">
            ⚡ QUIZ<span class="accent">LY</span>
        </a>
        <button class="menu-toggle" aria-label="Toggle Navigation">☰</button>
        <div class="nav-links">
            <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary">Register</a>
        </div>
    </nav>

    <div class="auth-container">
        <div class="card auth-card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h2 style="font-size: 1.8rem; color: var(--text-primary);">Teacher / Trainer Sign In</h2>
                <p style="color: var(--text-secondary); margin-top: 0.25rem; font-size: 0.95rem;">Access your quizzes and live sessions</p>
            </div>

            <div id="authAlert"></div>

            <form id="loginForm">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="loginEmail" name="email" class="form-input" placeholder="teacher@example.com" required style="height: 48px;">
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" id="loginPassword" name="password" class="form-input" placeholder="••••••••" required style="height: 48px;">
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top: 1rem; border-radius: 14px;">Sign In</button>
            </form>
            
            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                Don't have an account? <a href="<?= BASE_URL ?>/register.php" style="font-weight: 700;">Register Here</a>
            </div>
        </div>
    </div>

    <footer class="footer">
        QUIZLY &mdash; Powered by <strong>SHENDI</strong> (Created by deepak-shendi)
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            QuizlyApp.clearAlert('authAlert');

            try {
                const formData = new FormData(e.target);
                await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/auth.php', {
                    method: 'POST',
                    body: formData
                });

                QuizlyApp.showAlert('authAlert', 'Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = '<?= BASE_URL ?>/dashboard/index.php';
                }, 800);
            } catch (err) {
                QuizlyApp.showAlert('authAlert', err.message, 'danger');
            }
        });
    </script>
</body>
</html>
