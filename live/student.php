<?php
require_once __DIR__ . '/../config/config.php';
$sessionId = (int)($_GET['session_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QUIZLY — Student Live Quiz</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/student.css?v=<?= time() ?>">
    <style>
        .mobile-lb-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.85rem 1rem;
            background: #FFFFFF;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            margin-bottom: 0.5rem;
            font-size: 1rem;
            font-weight: 700;
        }
        .mobile-lb-row.my-score-row {
            border: 2px solid var(--brand-purple);
            background: var(--brand-purple-light);
            box-shadow: var(--shadow-sm);
        }
    </style>
</head>
<body style="background: var(--bg-body); min-height: 100vh;">
    <!-- 3-SECOND GET READY OVERLAY -->
    <div id="studentGetReadyOverlay" class="result-card" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:90%; z-index:999; border:2px solid var(--brand-purple); box-shadow: var(--shadow-lg);">
        <h2 style="font-size:1.8rem; color:var(--text-primary); margin-bottom:0.5rem; text-transform:uppercase; letter-spacing:1px;">Get Ready</h2>
        <p style="color:var(--text-secondary);">Next question starting in...</p>
        <div id="studentGetReadyNum" style="font-size:4.5rem; font-weight:900; color:var(--brand-purple); margin-top:0.5rem; font-family:monospace;">3</div>
    </div>

    <div class="student-wrapper">
        <div class="student-header">
            <div style="display:flex; align-items:center; gap:0.85rem;">
                <div id="studentAvatarHeader"></div>
                <div>
                    <div class="student-nickname" id="studentNickname">Participant</div>
                    <div style="font-size:0.85rem; color:var(--text-secondary);">Rank: <strong id="studentRank" style="color:var(--brand-purple);">#--</strong></div>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.75rem; color:var(--text-secondary); text-transform:uppercase; font-weight:700;">TOTAL SCORE</div>
                <div id="studentScore" style="font-size:1.3rem; font-weight:800; color:var(--success);">0 pts</div>
            </div>
        </div>

        <div id="studentAlert"></div>

        <!-- STATE 1: WAITING ROOM -->
        <div id="viewWaiting" class="card" style="text-align:center; padding:3rem 1.5rem; border-radius: var(--radius-xl);">
            <div id="viewWaitingAvatar" style="margin-bottom:1.25rem; display:inline-flex; justify-content:center;"></div>
            <h2 style="font-size:1.6rem; font-weight:800; color:var(--text-primary); margin-bottom:0.5rem;">Connected to Session 🎉</h2>
            <p style="color:var(--text-secondary); font-size:1rem;">Waiting for host to launch the question...</p>
        </div>

        <!-- STATE 2: ACTIVE QUESTION SCREEN -->
        <div id="viewQuestion" style="display:none; flex:1; flex-direction:column;">
            <div style="display:flex; justify-content:center; margin-bottom:1.25rem;">
                <div class="timer-circle" id="qTimer">20</div>
            </div>

            <div class="question-box" id="qText">
                Question text loading...
            </div>

            <div class="options-grid">
                <button class="option-btn opt-a" data-key="A">
                    <span class="opt-key">A</span>
                    <span class="opt-text" id="optText_A">Option A</span>
                </button>
                <button class="option-btn opt-b" data-key="B">
                    <span class="opt-key">B</span>
                    <span class="opt-text" id="optText_B">Option B</span>
                </button>
                <button class="option-btn opt-c" data-key="C">
                    <span class="opt-key">C</span>
                    <span class="opt-text" id="optText_C">Option C</span>
                </button>
                <button class="option-btn opt-d" data-key="D">
                    <span class="opt-key">D</span>
                    <span class="opt-text" id="optText_D">Option D</span>
                </button>
            </div>
        </div>

        <!-- STATE 3: QUESTION RESULT SCREEN -->
        <div id="viewResult" style="display:none;">
            <div class="result-card">
                <div id="resultTitle" class="result-title wrong">TIME'S UP! (NO ANSWER)</div>
                
                <div class="stat-badge-row">
                    <div class="stat-badge">
                        <div style="font-size:0.75rem; color:var(--text-secondary); text-transform:uppercase; font-weight:700;">Points Earned</div>
                        <div id="resultScore" class="stat-badge-num">+0 pts</div>
                    </div>
                    <div class="stat-badge">
                        <div style="font-size:0.75rem; color:var(--text-secondary); text-transform:uppercase; font-weight:700;">Response Time</div>
                        <div id="resultTime" class="stat-badge-num" style="color:var(--brand-purple);">0.000s</div>
                    </div>
                </div>

                <p style="color:var(--text-secondary); margin-top:1rem; font-size:0.9rem;">Waiting for host to show leaderboard...</p>
            </div>
        </div>

        <!-- STATE 4: MOBILE LEADERBOARD SCREEN -->
        <div id="viewLeaderboard" style="display:none;">
            <div class="card" style="padding:1.75rem; border-radius: var(--radius-xl);">
                <div style="text-align:center; margin-bottom:1.5rem;">
                    <div style="font-size:0.85rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:1px; font-weight:700;">YOUR CURRENT POSITION</div>
                    <div id="myMobileRankBanner" style="font-size:2.4rem; font-weight:900; color:var(--brand-purple); font-family:monospace;">RANK #--</div>
                </div>

                <h4 style="color:var(--text-secondary); text-transform:uppercase; font-size:0.85rem; margin-bottom:1rem; text-align:center; font-weight:700;">TOP PLAYERS</h4>
                <div id="mobileLeaderboardList">
                    <!-- Top players rendered via JS -->
                </div>
            </div>
        </div>

        <!-- STATE 5: QUIZ COMPLETED SCREEN & REDIRECT -->
        <div id="viewCompleted" style="display:none;">
            <div class="card" style="text-align:center; padding:3rem 1.5rem; border-radius: var(--radius-xl);">
                <h1 style="font-size:2.2rem; font-weight:900; color:var(--success); margin-bottom:0.5rem; text-transform:uppercase;">QUIZ COMPLETED!</h1>
                <p style="color:var(--text-secondary); font-size:1.1rem; margin-bottom:1.5rem;">Thank you for participating!</p>

                <div style="background:var(--bg-subtle); padding:1.5rem; border-radius:var(--radius-lg); border:1px solid var(--border-color); margin-bottom:1.5rem;">
                    <div style="font-size:0.85rem; color:var(--text-secondary); text-transform:uppercase; font-weight:700;">YOUR FINAL RESULT</div>
                    <div id="finalRankText" style="font-size:2.2rem; font-weight:900; color:var(--brand-purple); margin:0.4rem 0;">RANK #--</div>
                    <div id="finalScoreText" style="font-size:1.5rem; font-weight:800; color:var(--success);">0 pts</div>
                </div>

                <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:1.25rem;">
                    Redirecting to home in <strong id="redirectCountdown" style="color:var(--brand-purple);">5</strong> seconds...
                </p>

                <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary btn-full btn-lg" style="border-radius:14px;">RETURN TO HOME NOW</a>
            </div>
        </div>
    </div>

    <footer class="footer">
        QUIZLY &mdash; Powered by <strong>SHENDI</strong> (Created by deepak-shendi)
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>/assets/js/student.js?v=<?= time() ?>"></script>
    <script>
        const nick = sessionStorage.getItem('quizly_nickname') || 'Participant';
        document.getElementById('studentNickname').innerText = nick;

        const studentController = new StudentController();
    </script>
</body>
</html>
