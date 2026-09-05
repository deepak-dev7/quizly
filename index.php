<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> — Make Every Quiz More Interactive</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <style>
        .hero-section {
            padding: 4rem 1rem 3rem 1rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--brand-purple-light);
            color: var(--brand-purple);
            padding: 0.4rem 1rem;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 800;
            margin-bottom: 1.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .hero-headline {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 1.25rem;
            color: var(--text-primary);
            letter-spacing: -0.03em;
        }

        .hero-subtext {
            font-size: 1.15rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2.5rem;
        }

        .hero-mockup-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
        }

        .join-quiz-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-md);
            max-width: 480px;
            margin: 3rem auto 0 auto;
            text-align: center;
        }

        @media (max-width: 992px) {
            .hero-section {
                grid-template-columns: 1fr;
                text-align: center;
                padding: 2rem 0;
            }
            .hero-headline {
                font-size: 2.5rem;
            }
            .hero-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="brand-logo">
            ⚡ QUIZ<span class="accent">LY</span>
            <span class="brand-badge">2026 SaaS</span>
        </div>
        <button class="menu-toggle" aria-label="Toggle Navigation">☰</button>
        <div class="nav-links">
            <a href="<?= BASE_URL ?>/index.php" class="nav-item">Home</a>
            <a href="#join-section" class="nav-item">Join Quiz</a>
            <?php if ($currentUser): ?>
                <a href="<?= BASE_URL ?>/dashboard/index.php" class="btn btn-secondary">Dashboard</a>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger">Logout</a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost">Teacher Login</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary">Register Organization</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container">
        <section class="hero-section">
            <div>
                <div class="hero-badge">⚡ REAL-TIME QUIZZES</div>
                <h1 class="hero-headline">Make Every Quiz <span class="text-gradient">More Interactive.</span></h1>
                <p class="hero-subtext">Create live quizzes, invite participants with a 6-digit code, compete in real time, and climb the live leaderboard.</p>
                <div class="hero-actions">
                    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-lg">Create a Quiz</a>
                    <a href="#join-section" class="btn btn-secondary btn-lg">Join a Quiz</a>
                </div>
            </div>

            <div class="hero-mockup-card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="width:12px; height:12px; border-radius:50%; background:#EF4444; display:inline-block;"></span>
                        <span style="width:12px; height:12px; border-radius:50%; background:#F59E0B; display:inline-block;"></span>
                        <span style="width:12px; height:12px; border-radius:50%; background:#10B981; display:inline-block;"></span>
                    </div>
                    <span class="badge badge-primary">LIVE QUESTION 4 / 10</span>
                </div>
                <h3 style="font-size:1.3rem; margin-bottom:1.25rem; color:var(--text-primary);">Which protocol provides reliable speed-decay scoring?</h3>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1.5rem;">
                    <div style="padding:0.85rem; border-radius:12px; background:#FEE2E2; border:1px solid #FCA5A5; color:#991B1B; font-weight:700;">[A] UDP</div>
                    <div style="padding:0.85rem; border-radius:12px; background:#D1FAE5; border:1px solid #6EE7B7; color:#065F46; font-weight:700;">[B] TCP ✓</div>
                    <div style="padding:0.85rem; border-radius:12px; background:#FEF3C7; border:1px solid #FDE68A; color:#92400E; font-weight:700;">[C] ICMP</div>
                    <div style="padding:0.85rem; border-radius:12px; background:#EFF6FF; border:1px solid #BFDBFE; color:#1E40AF; font-weight:700;">[D] ARP</div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-subtle); padding:0.75rem 1rem; border-radius:12px;">
                    <span style="font-weight:700; font-size:0.9rem; color:var(--text-secondary);">🔥 247 Active Participants</span>
                    <span style="font-weight:800; color:var(--brand-purple); font-family:monospace;">00:14</span>
                </div>
            </div>
        </section>

        <!-- JOIN QUIZ SECTION -->
        <section id="join-section" style="padding: 2rem 0;">
            <div class="join-quiz-card">
                <h2 style="font-size: 1.8rem; margin-bottom: 0.5rem; color:var(--text-primary);">Join a Live Quiz</h2>
                <p style="color:var(--text-secondary); margin-bottom: 1.75rem; font-size: 0.95rem;">Enter your 6-digit room code to jump straight into the session.</p>
                <form action="<?= BASE_URL ?>/join.php" method="GET">
                    <div class="form-group">
                        <input type="text" name="code" class="form-input" placeholder="482917" maxlength="6" pattern="[0-9]{6}" required style="font-size: 2rem; text-align: center; letter-spacing: 8px; font-weight: 900; font-family: monospace; height: 60px; border-radius: 14px;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-full">Join Quiz →</button>
                </form>
            </div>
        </section>
    </div>

    <footer class="footer">
        QUIZLY &mdash; Powered by <strong>SHENDI</strong> (Created by deepak-shendi)
    </footer>

    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
