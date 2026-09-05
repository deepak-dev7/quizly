<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$db = Database::getConnection();

// Fetch Organization Details
$stmtOrg = $db->prepare("SELECT * FROM organizations WHERE id = :org_id");
$stmtOrg->execute(['org_id' => $orgId]);
$org = $stmtOrg->fetch(PDO::FETCH_ASSOC);

$roleDisplay = 'Teacher';
if (isset($user['role']) && strtoupper($user['role']) === 'TRAINER') {
    $roleDisplay = 'Trainer';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="app-layout">
        <?php $activePage = 'settings'; require_once __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 2rem; font-weight: 800; color:var(--text-primary);">Account & Organization Settings</h1>
                <p style="color:var(--text-secondary); font-size:0.95rem;">Manage your profile details and institution information</p>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
                <!-- USER ACCOUNT CARD -->
                <div class="card">
                    <h3 style="font-size: 1.3rem; margin-bottom: 1.25rem; color:var(--text-primary);"><?= $roleDisplay ?> Profile</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-input" value="<?= htmlspecialchars($user['name'] ?? '') ?>" readonly style="background:var(--bg-subtle);">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly style="background:var(--bg-subtle);">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-input" value="<?= $roleDisplay ?>" readonly style="background:var(--bg-subtle);">
                    </div>
                </div>

                <!-- ORGANIZATION DETAILS CARD -->
                <div class="card">
                    <h3 style="font-size: 1.3rem; margin-bottom: 1.25rem; color:var(--text-primary);">Organization Profile</h3>
                    
                    <div class="form-group">
                        <label class="form-label">Organization Name</label>
                        <input type="text" class="form-input" value="<?= htmlspecialchars($org['name'] ?? '') ?>" readonly style="background:var(--bg-subtle);">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Slug / Identifier</label>
                        <input type="text" class="form-input" value="<?= htmlspecialchars($org['slug'] ?? '') ?>" readonly style="background:var(--bg-subtle);">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <input type="text" class="form-input" value="<?= htmlspecialchars($org['department'] ?? 'Not specified') ?>" readonly style="background:var(--bg-subtle);">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Class / Batch</label>
                        <input type="text" class="form-input" value="<?= htmlspecialchars($org['class_name'] ?? 'Not specified') ?>" readonly style="background:var(--bg-subtle);">
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
</body>
</html>
