<?php
require_once __DIR__ . '/../config/config.php';
$sessionId = (int)($_GET['session_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QUIZLY — Presentation Screen</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/presentation.css?v=<?= time() ?>">
    <style>
        .correct-answer-banner {
            background: var(--success-light);
            border: 2px solid #6EE7B7;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin: 1.5rem 0;
            text-align: center;
            font-size: 2.2rem;
            font-weight: 900;
            color: #065F46;
            box-shadow: var(--shadow-sm);
        }

        .countdown-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 999;
        }
        .countdown-number {
            font-size: 10rem;
            font-weight: 900;
            color: var(--brand-purple);
            animation: pulse 1s infinite alternate;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            100% { transform: scale(1.15); }
        }

        .pres-bar-row {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .pres-bar-label {
            width: 50px;
            height: 50px;
            font-size: 1.6rem;
            font-weight: 900;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: #FFF;
        }
        .pres-bar-bg {
            flex: 1;
            height: 52px;
            background-color: var(--bg-subtle);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border-color);
        }
        .pres-bar-fill {
            height: 100%;
            width: 0%;
            transition: width 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
            border-radius: 12px;
        }
        .pres-bar-count {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 800;
            color: var(--text-primary);
            font-size: 1.4rem;
        }
        .correct-opt-highlight {
            border: 3px solid var(--success) !important;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
        }

        .pres-joined-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1.25rem;
            max-width: 1200px;
            margin: 2rem auto 0 auto;
            padding: 1rem;
        }
        .pres-joined-chip {
            background: #FFFFFF;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 0.6rem 1.5rem 0.6rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 1.5rem;
            font-weight: 800;
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            animation: presChipPop 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes presChipPop {
            from { opacity: 0; transform: scale(0.5) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body style="background: var(--bg-body); min-height: 100vh;">
    <!-- 3-SECOND GET READY COUNTDOWN OVERLAY -->
    <div id="presGetReadyOverlay" class="countdown-overlay" style="display:none;">
        <h1 style="font-size:3.5rem; font-weight:900; color:var(--text-primary); margin-bottom:1rem;">GET READY! 🚀</h1>
        <div id="presGetReadyNum" class="countdown-number">3</div>
    </div>

    <div class="presentation-container">
        <div class="pres-header">
            <div>
                <div class="brand-logo" style="font-size:2.2rem;">
                    ⚡ QUIZ<span class="accent">LY</span>
                </div>
                <div id="presOrgName" style="font-size:1.1rem; color:var(--brand-purple); font-weight:800; margin-top:0.2rem;">Organization</div>
            </div>
            <div style="display:flex; align-items:center; gap:2rem;">
                <div style="font-size:1.5rem; font-weight:800; color:var(--text-secondary);" id="presParticipantCount">0 Players</div>
                <div class="pres-room-code">ROOM: <span id="presRoomCode">------</span></div>
            </div>
        </div>

        <!-- VIEW 1: WAITING ROOM -->
        <div id="presViewWaiting" style="text-align:center; margin-top:4rem;">
            <h1 style="font-size:3.5rem; font-weight:900; color:var(--text-primary); margin-bottom:0.5rem; text-transform:uppercase;">JOINED PARTICIPANTS (<span style="color:var(--brand-purple);" id="presWaitingCount">0</span>)</h1>
            <p style="font-size:1.6rem; color:var(--text-secondary);">Waiting for the host to start the live quiz...</p>

            <div id="presJoinedGrid" class="pres-joined-grid">
                <div style="font-size:1.6rem; color:var(--text-muted);">Waiting for players to join...</div>
            </div>
        </div>

        <!-- VIEW 2: ACTIVE QUESTION SCREEN -->
        <div id="presViewQuestion" style="display:none; margin-top:2rem;">
            <div style="display:flex; justify-content:center; margin-bottom:1.5rem;">
                <div style="font-size:3rem; font-weight:900; color:var(--brand-purple); background:#FFFFFF; padding:0.5rem 2.5rem; border-radius:18px; border:2px solid var(--brand-purple-light); box-shadow:var(--shadow-sm);" id="presQTimer">20s</div>
            </div>

            <div class="pres-question-title" id="presQTitle">Question prompt loading...</div>

            <div class="pres-options-grid">
                <div class="pres-option-card opt-a" id="presOpt_A">A. Option A</div>
                <div class="pres-option-card opt-b" id="presOpt_B">B. Option B</div>
                <div class="pres-option-card opt-c" id="presOpt_C">C. Option C</div>
                <div class="pres-option-card opt-d" id="presOpt_D">D. Option D</div>
            </div>
        </div>

        <!-- VIEW 3: QUESTION RESULTS & BAR CHART DISTRIBUTION -->
        <div id="presViewResults" style="display:none; margin-top:2rem;">
            <div class="pres-question-title" id="presResultQTitle">Question prompt...</div>

            <div id="presCorrectBanner" class="correct-answer-banner">
                ✓ Correct Answer: <span id="presCorrectText">Option</span>
            </div>

            <div style="max-width:1100px; margin:2rem auto; background:#FFFFFF; border:1px solid var(--border-color); padding:2rem; border-radius:18px; box-shadow:var(--shadow-sm);">
                <h3 style="font-size:2rem; font-weight:900; color:var(--brand-purple); text-align:center; margin-bottom:1.5rem;">📊 Answer Selection Distribution</h3>

                <div class="pres-bar-row">
                    <div class="pres-bar-label opt-a">A</div>
                    <div id="presBarBox_A" class="pres-bar-bg">
                        <div id="presBarFill_A" class="pres-bar-fill opt-a"></div>
                        <div id="presBarCount_A" class="pres-bar-count">0 (0%)</div>
                    </div>
                </div>

                <div class="pres-bar-row">
                    <div class="pres-bar-label opt-b">B</div>
                    <div id="presBarBox_B" class="pres-bar-bg">
                        <div id="presBarFill_B" class="pres-bar-fill opt-b"></div>
                        <div id="presBarCount_B" class="pres-bar-count">0 (0%)</div>
                    </div>
                </div>

                <div class="pres-bar-row">
                    <div class="pres-bar-label opt-c">C</div>
                    <div id="presBarBox_C" class="pres-bar-bg">
                        <div id="presBarFill_C" class="pres-bar-fill opt-c"></div>
                        <div id="presBarCount_C" class="pres-bar-count">0 (0%)</div>
                    </div>
                </div>

                <div class="pres-bar-row">
                    <div class="pres-bar-label opt-d">D</div>
                    <div id="presBarBox_D" class="pres-bar-bg">
                        <div id="presBarFill_D" class="pres-bar-fill opt-d"></div>
                        <div id="presBarCount_D" class="pres-bar-count">0 (0%)</div>
                    </div>
                </div>
            </div>

            <h3 style="font-size:2rem; font-weight:800; color:var(--brand-purple); margin-bottom:1rem; text-align:center;">Fastest Correct Responders</h3>
            <div class="table-responsive" style="max-width:1100px; margin:0 auto;">
                <table class="table-custom" style="font-size:1.4rem;">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>Status</th>
                            <th>Clicked In (Response Time)</th>
                            <th>Points Earned</th>
                        </tr>
                    </thead>
                    <tbody id="presQuestionLeaderboardBody">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- VIEW 4: CUMULATIVE LEADERBOARD SCREEN -->
        <div id="presViewLeaderboard" style="display:none; margin-top:3rem; max-width:1100px; margin-left:auto; margin-right:auto; width:100%;">
            <h2 style="font-size:3rem; font-weight:900; text-align:center; margin-bottom:2rem; color:var(--brand-purple);">OVERALL LEADERBOARD</h2>
            <div id="presLeaderboardBody"></div>
        </div>

        <!-- VIEW 5: FINAL PODIUM SCREEN -->
        <div id="presViewPodium" style="display:none; text-align:center; margin-top:2rem;">
            <h1 style="font-size:4rem; font-weight:900; color:var(--brand-purple); margin-bottom:1rem;">FINAL PODIUM 🏆</h1>
            <div class="podium-container">
                <div class="podium-place">
                    <div id="podium2_avatar" style="margin-bottom:0.5rem; display:inline-flex; justify-content:center;"></div>
                    <div class="podium-player" id="podium2_name">---</div>
                    <div class="podium-score" id="podium2_score">0 pts</div>
                    <div class="podium-block podium-2">2nd</div>
                </div>
                <div class="podium-place">
                    <div id="podium1_avatar" style="margin-bottom:0.5rem; display:inline-flex; justify-content:center;"></div>
                    <div class="podium-player" id="podium1_name">---</div>
                    <div class="podium-score" id="podium1_score">0 pts</div>
                    <div class="podium-block podium-1">1st</div>
                </div>
                <div class="podium-place">
                    <div id="podium3_avatar" style="margin-bottom:0.5rem; display:inline-flex; justify-content:center;"></div>
                    <div class="podium-player" id="podium3_name">---</div>
                    <div class="podium-score" id="podium3_score">0 pts</div>
                    <div class="podium-block podium-3">3rd</div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        QUIZLY &mdash; Powered by <strong>SHENDI</strong> (Created by deepak-shendi)
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>/assets/js/presentation.js?v=<?= time() ?>"></script>
    <script>
        const presController = new PresentationController(<?= $sessionId ?>);
    </script>
</body>
</html>
