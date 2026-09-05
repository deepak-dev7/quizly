/* Presentation View Controller — Real-Time Waiting Room & Leaderboard Display */

class PresentationController {
    constructor(sessionId) {
        this.sessionId = sessionId;
        this.pollInterval = null;
        this.isPolling = false;
        this.previousRanks = {};

        this.init();
    }

    init() {
        this.startPolling();
    }

    startPolling() {
        this.fetchState();
        this.pollInterval = setInterval(() => {
            if (!document.hidden) {
                this.fetchState();
            }
        }, 1000);
    }

    async fetchState() {
        if (this.isPolling) return;
        this.isPolling = true;

        try {
            const res = await QuizlyApp.fetchJson(`${QuizlyApp.baseUrl}/api/state.php?session_id=${this.sessionId}`);
            const state = res.data;
            this.renderState(state);
        } catch (err) {
            console.error('Failed polling presentation state:', err);
        } finally {
            this.isPolling = false;
        }
    }

    renderState(state) {
        const orgEl = document.getElementById('presOrgName');
        if (orgEl) orgEl.innerText = state.organization_name || 'Organization';

        const codeEl = document.getElementById('presRoomCode');
        if (codeEl) codeEl.innerText = state.room_code;

        const partEl = document.getElementById('presParticipantCount');
        if (partEl) partEl.innerText = `${state.participant_count} Players`;

        const waitingCountEl = document.getElementById('presWaitingCount');
        if (waitingCountEl) waitingCountEl.innerText = state.participant_count;

        const joinedGrid = document.getElementById('presJoinedGrid');
        if (joinedGrid) {
            if (!state.participants || state.participants.length === 0) {
                joinedGrid.innerHTML = `<div style="font-size:1.6rem; color:var(--text-muted);">Waiting for participants to enter room code...</div>`;
            } else {
                joinedGrid.innerHTML = state.participants.map(p => `
                    <div class="pres-joined-chip">
                        ${QuizlyApp.getAvatarBadgeHtml(p.avatar || p.nickname, p.nickname, 44)}
                        <span>${QuizlyApp.escapeHtml(p.nickname)}</span>
                    </div>
                `).join('');
            }
        }

        const readyOverlay = document.getElementById('presGetReadyOverlay');
        const readyNum = document.getElementById('presGetReadyNum');
        const isCountingDown = (state.question && state.question.is_counting_down);

        if (isCountingDown) {
            if (readyOverlay) readyOverlay.style.display = 'flex';
            if (readyNum) readyNum.innerText = state.question.get_ready_seconds;
        } else {
            if (readyOverlay) readyOverlay.style.display = 'none';
        }

        const viewWaiting = document.getElementById('presViewWaiting');
        const viewQuestion = document.getElementById('presViewQuestion');
        const viewResults = document.getElementById('presViewResults');
        const viewLeaderboard = document.getElementById('presViewLeaderboard');
        const viewPodium = document.getElementById('presViewPodium');

        if (state.status === 'WAITING') {
            if (viewWaiting) viewWaiting.style.display = 'block';
            if (viewQuestion) viewQuestion.style.display = 'none';
            if (viewResults) viewResults.style.display = 'none';
            if (viewLeaderboard) viewLeaderboard.style.display = 'none';
            if (viewPodium) viewPodium.style.display = 'none';
            return;
        }

        if (state.status === 'QUESTION_ACTIVE' && state.question) {
            if (viewWaiting) viewWaiting.style.display = 'none';
            if (viewQuestion) viewQuestion.style.display = 'block';
            if (viewResults) viewResults.style.display = 'none';
            if (viewLeaderboard) viewLeaderboard.style.display = 'none';
            if (viewPodium) viewPodium.style.display = 'none';

            document.getElementById('presQTitle').innerText = `Q${state.question.order_num}: ${state.question.question_text}`;
            document.getElementById('presQTimer').innerText = `${state.question.remaining_seconds}s`;

            // Hide options grid while 3-2-1 GET READY is counting down
            const presOptionsGrid = document.querySelector('.pres-options-grid');
            if (presOptionsGrid) {
                presOptionsGrid.style.display = isCountingDown ? 'none' : 'grid';
            }

            for (const key of ['A', 'B', 'C', 'D']) {
                const optEl = document.getElementById(`presOpt_${key}`);
                if (optEl && state.question.options[key]) {
                    optEl.innerText = `${key}. ${state.question.options[key]}`;
                }
            }
        }

        if (state.status === 'QUESTION_RESULTS' && state.question) {
            if (viewWaiting) viewWaiting.style.display = 'none';
            if (viewQuestion) viewQuestion.style.display = 'none';
            if (viewResults) viewResults.style.display = 'block';
            if (viewLeaderboard) viewLeaderboard.style.display = 'none';
            if (viewPodium) viewPodium.style.display = 'none';

            document.getElementById('presResultQTitle').innerText = `Q${state.question.order_num}: ${state.question.question_text}`;
            document.getElementById('presCorrectText').innerText = `${state.question.correct_option_key}. ${state.question.correct_option_text}`;

            if (state.distribution) {
                const totalAns = Math.max(1, state.answered_count);
                const correctKey = state.question.correct_option_key;

                for (const key of ['A', 'B', 'C', 'D']) {
                    const count = state.distribution[key] || 0;
                    const pct = (state.answered_count > 0) ? Math.round((count / totalAns) * 100) : 0;
                    
                    const fillEl = document.getElementById(`presBarFill_${key}`);
                    const countEl = document.getElementById(`presBarCount_${key}`);
                    const boxEl = document.getElementById(`presBarBox_${key}`);
                    
                    if (fillEl) fillEl.style.width = `${pct}%`;
                    if (countEl) countEl.innerText = `${count} (${pct}%)`;

                    if (boxEl) {
                        if (key === correctKey) {
                            boxEl.classList.add('correct-opt-highlight');
                        } else {
                            boxEl.classList.remove('correct-opt-highlight');
                        }
                    }
                }
            }

            const qLbBody = document.getElementById('presQuestionLeaderboardBody');
            if (qLbBody && state.question_leaderboard) {
                if (state.question_leaderboard.length === 0) {
                    qLbBody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No responses received for this question.</td></tr>`;
                } else {
                    qLbBody.innerHTML = state.question_leaderboard.map(p => `
                        <tr style="background:${p.is_correct ? 'rgba(5,150,105,0.08)' : 'rgba(220,38,38,0.08)'}">
                            <td><strong>#${p.rank}</strong></td>
                            <td>
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    ${QuizlyApp.getAvatarBadgeHtml(p.avatar || p.nickname, p.nickname, 36)}
                                    <strong>${QuizlyApp.escapeHtml(p.nickname)}</strong>
                                </div>
                            </td>
                            <td>${p.is_correct ? '<span style="color:var(--success); font-weight:700;">Correct</span>' : '<span style="color:var(--danger); font-weight:700;">Incorrect</span>'}</td>
                            <td style="color:var(--primary); font-weight:800;">${p.formatted_time}</td>
                            <td><strong>+${p.score_earned} pts</strong></td>
                        </tr>
                    `).join('');
                }
            }
        }

        if (state.status === 'LEADERBOARD' && state.leaderboard) {
            if (viewWaiting) viewWaiting.style.display = 'none';
            if (viewQuestion) viewQuestion.style.display = 'none';
            if (viewResults) viewResults.style.display = 'none';
            if (viewLeaderboard) viewLeaderboard.style.display = 'block';
            if (viewPodium) viewPodium.style.display = 'none';

            this.renderAnimatedLeaderboard(state.leaderboard);
        }

        if (state.status === 'COMPLETED' && state.leaderboard) {
            if (viewWaiting) viewWaiting.style.display = 'none';
            if (viewQuestion) viewQuestion.style.display = 'none';
            if (viewResults) viewResults.style.display = 'none';
            if (viewLeaderboard) viewLeaderboard.style.display = 'none';
            if (viewPodium) viewPodium.style.display = 'block';

            const top = state.leaderboard;
            if (top[0]) {
                document.getElementById('podium1_avatar').innerHTML = QuizlyApp.getAvatarSvg(top[0].avatar || top[0].nickname, 88);
                document.getElementById('podium1_name').innerText = top[0].nickname;
                document.getElementById('podium1_score').innerText = `${top[0].total_score} pts`;
            }
            if (top[1]) {
                document.getElementById('podium2_avatar').innerHTML = QuizlyApp.getAvatarSvg(top[1].avatar || top[1].nickname, 72);
                document.getElementById('podium2_name').innerText = top[1].nickname;
                document.getElementById('podium2_score').innerText = `${top[1].total_score} pts`;
            }
            if (top[2]) {
                document.getElementById('podium3_avatar').innerHTML = QuizlyApp.getAvatarSvg(top[2].avatar || top[2].nickname, 64);
                document.getElementById('podium3_name').innerText = top[2].nickname;
                document.getElementById('podium3_score').innerText = `${top[2].total_score} pts`;
            }
        }
    }

    renderAnimatedLeaderboard(leaderboardData) {
        const lbBody = document.getElementById('presLeaderboardBody');
        if (!lbBody) return;

        const top5 = leaderboardData.slice(0, 5);
        const newRanksMap = {};

        const html = top5.map(p => {
            const pId = p.participant_id;
            const newRank = parseInt(p.rank);
            newRanksMap[pId] = newRank;

            let rankBadgeHtml = `<span class="rank-delta rank-same">—</span>`;
            let animClass = '';

            if (this.previousRanks[pId] !== undefined) {
                const prevRank = this.previousRanks[pId];
                const delta = prevRank - newRank;

                if (delta > 0) {
                    rankBadgeHtml = `<span class="rank-delta rank-up">+${delta}</span>`;
                    animClass = 'rank-moved-up';
                } else if (delta < 0) {
                    rankBadgeHtml = `<span class="rank-delta rank-down">-${Math.abs(delta)}</span>`;
                    animClass = 'rank-moved-down';
                }
            }

            const lastTimeText = p.last_formatted_time ? p.last_formatted_time : '—';
            const avatarSeed = p.avatar || p.nickname;

            return `
                <div class="lb-anim-card ${animClass}" data-id="${pId}">
                    <div style="display:flex; align-items:center; gap:1.25rem;">
                        <span style="font-size:1.8rem; color:var(--primary); font-weight:800; min-width:44px;">#${p.rank}</span>
                        ${QuizlyApp.getAvatarBadgeHtml(avatarSeed, p.nickname, 44)}
                        <span>${QuizlyApp.escapeHtml(p.nickname)}</span>
                        ${rankBadgeHtml}
                    </div>
                    <div style="display:flex; align-items:center; gap:1.75rem;">
                        <span style="font-size:1.15rem; color:var(--text-muted); font-weight:700; font-family:monospace; background:rgba(15,23,42,0.8); padding:0.3rem 0.8rem; border-radius:6px; border:1px solid rgba(255,255,255,0.1);">
                            Last Q: <strong style="color:var(--primary);">${lastTimeText}</strong>
                        </span>
                        <span style="color:var(--primary); font-size:1.6rem;">${p.total_score} pts</span>
                    </div>
                </div>
            `;
        }).join('');

        lbBody.innerHTML = html;
        this.previousRanks = newRanksMap;
    }
}
