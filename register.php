<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (getCurrentUser()) {
    header('Location: ' . BASE_URL . '/dashboard/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Account — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <style>
        .auth-container {
            min-height: calc(100vh - 140px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
        }
        .auth-card {
            max-width: 540px;
            width: 100%;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            padding: 2.5rem 2rem;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
        }
        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        @media (max-width: 600px) {
            .form-grid-2 {
                grid-template-columns: 1fr;
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
            <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost">Login</a>
        </div>
    </nav>

    <div class="auth-container">
        <div class="card auth-card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h2 style="font-size: 1.8rem; color: var(--text-primary);">Create Account</h2>
                <p style="color: var(--text-secondary); margin-top: 0.25rem; font-size: 0.95rem;">Register as a Teacher or Trainer to host interactive quizzes</p>
            </div>

            <div id="authAlert"></div>

            <form id="registerForm">
                <input type="hidden" name="action" value="register">

                <div class="form-group">
                    <label class="form-label">Organization / School Name</label>
                    <input type="text" name="org_name" class="form-input" placeholder="e.g. St. Xavier College or Acme Training" required style="height: 48px;">
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Department (Optional)</label>
                        <input type="text" name="department" class="form-input" placeholder="e.g. Computer Science" style="height: 48px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Class / Batch (Optional)</label>
                        <input type="text" name="class_name" class="form-input" placeholder="e.g. Batch 2026" style="height: 48px;">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-input" placeholder="e.g. Prof. Alex Turner" required style="height: 48px;">
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-input" placeholder="teacher@example.com" required style="height: 48px;">
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" placeholder="Minimum 6 characters" minlength="6" required style="height: 48px;">
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top: 1rem; border-radius: 14px;">Create Account →</button>
            </form>

            <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                Already registered? <a href="<?= BASE_URL ?>/login.php" style="font-weight: 700;">Sign In Here</a>
            </div>
        </div>
    </div>

    <footer class="footer">
        QUIZLY &mdash; Powered by <strong>SHENDI</strong> (Created by deepak-shendi)
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            QuizlyApp.clearAlert('authAlert');

            try {
                const formData = new FormData(e.target);
                await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/auth.php', {
                    method: 'POST',
                    body: formData
                });

                QuizlyApp.showAlert('authAlert', 'Account created successfully! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = '<?= BASE_URL ?>/dashboard/index.php';
                }, 1000);
            } catch (err) {
                QuizlyApp.showAlert('authAlert', err.message, 'danger');
            }
        });
    </script>
</body>
</html>
