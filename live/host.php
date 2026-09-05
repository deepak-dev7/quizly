<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$user = requireLogin();
$orgId = getAuthOrgId();
$sessionId = (int)($_GET['session_id'] ?? 0);

if (!$sessionId) {
    header('Location: ' . BASE_URL . '/dashboard/quizzes.php');
    exit;
}

$db = Database::getConnection();

$stmtS = $db->prepare("
    SELECT s.id, s.room_code, s.session_status, q.title AS quiz_title, q.id AS quiz_id
    FROM quiz_sessions s
    JOIN quizzes q ON s.quiz_id = q.id
    WHERE s.id = :session_id AND s.organization_id = :org_id
");
$stmtS->execute(['session_id' => $sessionId, 'org_id' => $orgId]);
$session = $stmtS->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    header('Location: ' . BASE_URL . '/dashboard/quizzes.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Control Room — Room <?= $session['room_code'] ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/host.css?v=<?= time() ?>">
    <style>
        .joined-list-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 0.75rem;
            padding: 0.75rem;
            background: var(--bg-subtle);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
        }
        .joined-chip {
            background: #FFFFFF;
            color: var(--text-primary);
            padding: 0.4rem 0.95rem 0.4rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: 0.9rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xs);
            animation: chipAppear 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes chipAppear {
            from { opacity: 0; transform: scale(0.6) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body style="background: var(--bg-body); min-height: 100vh;">
    <nav class="navbar">
        <a href="<?= BASE_URL ?>/dashboard/index.php" class="brand-logo">
            ⚡ QUIZ<span class="accent">LY</span> CONTROL ROOM
        </a>
        <button class="menu-toggle" aria-label="Toggle Navigation">☰</button>
        <div class="nav-links">
            <button id="btnAudioToggle" class="btn btn-secondary btn-sm" onclick="toggleHostAudio()">Sound: ON</button>
            <a href="<?= BASE_URL ?>/live/presentation.php?session_id=<?= $sessionId ?>" target="_blank" class="btn btn-ghost btn-sm">Open Presentation Display</a>
            <a href="<?= BASE_URL ?>/dashboard/index.php" class="btn btn-danger btn-sm">Exit Room</a>
        </div>
    </nav>

    <div class="container" style="max-width: 1400px;">
        <div class="room-code-banner">
            <div class="room-code-title">ROOM CODE</div>
            <div class="room-code-value"><?= htmlspecialchars($session['room_code']) ?></div>
        </div>

        <div class="host-grid">
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h2 style="font-size: 1.6rem; color:var(--text-primary);"><?= htmlspecialchars($session['quiz_title']) ?></h2>
                        <span id="sessionStatusBadge" class="badge badge-primary"><?= htmlspecialchars($session['session_status']) ?></span>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size: 0.85rem; color:var(--text-secondary); font-weight:700; text-transform:uppercase;">ANSWERED</div>
                        <div style="font-size: 2.2rem; font-weight:900; color:var(--brand-purple);"><span id="answeredCount">0</span> / <span id="participantCount">0</span></div>
                    </div>
                </div>

                <!-- LIVE REAL-TIME JOINED PARTICIPANTS LIST -->
                <div style="margin-top: 1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size: 0.85rem; font-weight:700; color:var(--text-secondary); text-transform:uppercase;">Joined Participants (<span id="joinedCountHeader">0</span>)</span>
                    </div>
                    <div id="joinedParticipantsList" class="joined-list-container">
                        <span style="color:var(--text-muted); font-size:0.85rem;">Waiting for participants to enter room code...</span>
                    </div>
                </div>

                <div id="questionContainer" style="margin-top: 1.5rem; padding: 1.75rem; background:var(--bg-subtle); border-radius:var(--radius-lg); border:1px solid var(--border-color);">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                        <h3 id="questionText" style="font-size:1.35rem; color:var(--text-primary);">Waiting for host to launch first question...</h3>
                        <div id="timerDisplay" style="font-size:2.2rem; font-weight:900; color:var(--warning); font-family:monospace;">--</div>
                    </div>
                </div>

                <h4 style="margin-top: 1.5rem; color:var(--text-secondary); font-weight:700; text-transform:uppercase; font-size:0.85rem;">Answer Distribution</h4>
                <div class="chart-container">
                    <div class="bar-row">
                        <div class="bar-label opt-a">A</div>
                        <div class="bar-bg">
                            <div id="barFillA" class="bar-fill opt-a"></div>
                            <div id="barCountA" class="bar-count">0 (0%)</div>
                        </div>
                    </div>
                    <div class="bar-row">
                        <div class="bar-label opt-b">B</div>
                        <div class="bar-bg">
                            <div id="barFillB" class="bar-fill opt-b"></div>
                            <div id="barCountB" class="bar-count">0 (0%)</div>
                        </div>
                    </div>
                    <div class="bar-row">
                        <div class="bar-label opt-c">C</div>
                        <div class="bar-bg">
                            <div id="barFillC" class="bar-fill opt-c"></div>
                            <div id="barCountC" class="bar-count">0 (0%)</div>
                        </div>
                    </div>
                    <div class="bar-row">
                        <div class="bar-label opt-d">D</div>
                        <div class="bar-bg">
                            <div id="barFillD" class="bar-fill opt-d"></div>
                            <div id="barCountD" class="bar-count">0 (0%)</div>
                        </div>
                    </div>
                </div>

                <div class="control-bar">
                    <button id="btnStartQuestion" class="btn btn-primary btn-lg">START FIRST QUESTION</button>
                    <button id="btnEndQuestion" class="btn btn-secondary btn-lg" style="display:none; background:#FEF3C7; color:#92400E; border-color:#FDE68A;">END QUESTION</button>
                    <button id="btnShowLeaderboard" class="btn btn-primary btn-lg" style="display:none;">SHOW LEADERBOARD</button>
                    <button id="btnNextQuestion" class="btn btn-success btn-lg" style="display:none;">NEXT QUESTION</button>
                    <button id="btnEndQuiz" class="btn btn-danger btn-lg" style="margin-left:auto;">END QUIZ</button>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-bottom: 1.25rem; color:var(--text-primary);">Live Leaderboard</h3>
                <div class="table-responsive">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Participant</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboardBody">
                            <tr><td colspan="3" style="color:var(--text-muted); text-align:center;">Leaderboard updates after question...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        QUIZLY &mdash; Powered by <strong>SHENDI</strong> (Created by deepak-shendi)
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>/assets/js/audio.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>/assets/js/host.js?v=<?= time() ?>"></script>
    <script>
        const hostController = new HostController(<?= $sessionId ?>);

        function toggleHostAudio() {
            const isMuted = quizlyAudio.toggleMute();
            document.getElementById('btnAudioToggle').innerText = 'Sound: ' + (isMuted ? 'OFF' : 'ON');
        }
    </script>
</body>
</html>
