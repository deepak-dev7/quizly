<?php
require_once __DIR__ . '/config/config.php';

$initialCode = trim($_GET['code'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Quiz — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/student.css?v=<?= time() ?>">
</head>
<body style="background: var(--bg-body); min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem;">

    <div class="card" style="width: 100%; max-width: 480px; padding: 2.5rem 2rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-md);">
        <div style="text-align: center; margin-bottom: 2rem;">
            <a href="<?= BASE_URL ?>/index.php" class="brand-logo" style="justify-content: center; font-size: 2rem; margin-bottom: 0.5rem;">
                ⚡ QUIZ<span class="accent">LY</span>
            </a>
            <h2 style="font-size: 1.5rem; color: var(--text-primary);">Join a Live Quiz</h2>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">Enter room code & nickname to play</p>
        </div>

        <div id="joinAlert"></div>

        <form id="joinForm">
            <div class="form-group">
                <label class="form-label">6-Digit Room Code</label>
                <input type="text" id="room_code" class="form-input" placeholder="482917" maxlength="6" value="<?= htmlspecialchars($initialCode) ?>" required style="font-size: 2rem; text-align: center; letter-spacing: 8px; font-weight: 900; font-family: monospace; height: 60px; border-radius: 14px;">
            </div>

            <!-- LIVE AVATAR PREVIEW CARD -->
            <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 1.5rem; text-align: center;">
                <label class="form-label" style="margin-bottom: 0.5rem; color: var(--text-secondary);">Your Participant Avatar</label>
                <div id="avatarPreviewBox" style="margin: 0.5rem 0 0.75rem 0; display: inline-flex; justify-content: center; transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);">
                    <!-- Rendered SVG Avatar -->
                </div>
                <div>
                    <button type="button" id="btnShuffleAvatar" class="btn btn-secondary btn-sm" style="font-size: 0.85rem;">
                        🎲 Shuffle Avatar
                    </button>
                </div>
                <input type="hidden" id="avatar_seed" name="avatar" value="">
            </div>

            <div class="form-group">
                <label class="form-label">Your Nickname</label>
                <input type="text" id="nickname" class="form-input" placeholder="Enter your name" maxlength="40" required style="font-size: 1.1rem; text-align: center; font-weight: 700; height: 50px;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <div class="form-group">
                    <label class="form-label">Department (Optional)</label>
                    <input type="text" id="department" class="form-input" placeholder="e.g. CSE" style="font-size: 0.95rem;">
                </div>
                <div class="form-group">
                    <label class="form-label">Class / Sec (Optional)</label>
                    <input type="text" id="class_name" class="form-input" placeholder="e.g. 3rd Year B" style="font-size: 0.95rem;">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top: 0.75rem; border-radius: 14px;">JOIN SESSION →</button>
        </form>
    </div>

    <footer class="footer" style="width:100%; margin-top: auto;">
        QUIZLY &mdash; Powered by <strong>SHENDI</strong> (Created by deepak-shendi)
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script>
        let currentAvatarSeed = QuizlyApp.generateAvatarSeed();

        function updateAvatarPreview() {
            const nick = document.getElementById('nickname').value.trim();
            const displaySeed = currentAvatarSeed + (nick ? '_' + nick : '');
            document.getElementById('avatar_seed').value = displaySeed;
            const previewBox = document.getElementById('avatarPreviewBox');
            previewBox.innerHTML = QuizlyApp.getAvatarSvg(displaySeed, 80);
            previewBox.style.transform = 'scale(1.1)';
            setTimeout(() => { previewBox.style.transform = 'scale(1)'; }, 200);
        }

        document.getElementById('btnShuffleAvatar').addEventListener('click', () => {
            currentAvatarSeed = QuizlyApp.generateAvatarSeed();
            updateAvatarPreview();
        });

        document.getElementById('nickname').addEventListener('input', () => {
            updateAvatarPreview();
        });

        // Initialize preview
        updateAvatarPreview();

        document.getElementById('joinForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            QuizlyApp.clearAlert('joinAlert');

            const roomCode = document.getElementById('room_code').value.trim();
            const nickname = document.getElementById('nickname').value.trim();
            const avatar = document.getElementById('avatar_seed').value.trim();
            const department = document.getElementById('department').value.trim();
            const className = document.getElementById('class_name').value.trim();

            try {
                const formData = new FormData();
                formData.append('room_code', roomCode);
                formData.append('nickname', nickname);
                formData.append('avatar', avatar);
                formData.append('department', department);
                formData.append('class_name', className);

                const res = await QuizlyApp.fetchJson('<?= BASE_URL ?>/api/join.php', {
                    method: 'POST',
                    body: formData
                });

                const data = res.data;
                sessionStorage.setItem('quizly_participant_token', data.participant_token);
                sessionStorage.setItem('quizly_session_id', data.session_id);
                sessionStorage.setItem('quizly_nickname', data.nickname);
                sessionStorage.setItem('quizly_avatar', data.avatar);

                window.location.href = `<?= BASE_URL ?>/live/student.php?session_id=${data.session_id}`;
            } catch (err) {
                QuizlyApp.showAlert('joinAlert', err.message, 'danger');
            }
        });
    </script>
</body>
</html>
