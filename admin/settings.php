<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireRole('PLATFORM_ADMIN');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings — <?= APP_NAME ?> Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'admin_settings'; $forceAdminSidebar = true; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">Platform Configuration & Settings</h1>
                <p style="color:var(--text-secondary); font-size:0.95rem;">System level configuration parameters and admin details</p>
            </div>

            <div class="card" style="max-width: 600px;">
                <h3 style="font-size: 1.3rem; margin-bottom: 1.25rem; color:var(--text-primary);">System Information</h3>

                <div class="form-group">
                    <label class="form-label">Application Name</label>
                    <input type="text" class="form-input" value="<?= APP_NAME ?>" readonly style="background:var(--bg-subtle);">
                </div>

                <div class="form-group">
                    <label class="form-label">Base URL</label>
                    <input type="text" class="form-input" value="<?= BASE_URL ?>" readonly style="background:var(--bg-subtle);">
                </div>

                <div class="form-group">
                    <label class="form-label">Admin User Email</label>
                    <input type="text" class="form-input" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background:var(--bg-subtle);">
                </div>

                <div class="form-group">
                    <label class="form-label">Multi-Tenant Status</label>
                    <input type="text" class="form-input" value="Active (Strict Organization Isolation)" readonly style="background:var(--bg-subtle);">
                </div>
            </div>
        </main>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
