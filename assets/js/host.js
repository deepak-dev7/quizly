/* Host Live Control Room Logic — Real-Time Joined Participants & State Controller */

class HostController {
    constructor(sessionId) {
        this.sessionId = sessionId;
        this.pollInterval = null;
        this.isPolling = false;
        this.currentState = null;
        this.previousRanks = {};

        this.init();
    }

    init() {
        this.bindEvents();
        this.startPolling();
    }

    bindEvents() {
        document.getElementById('btnStartQuestion')?.addEventListener('click', () => this.executeAction('start_question'));
        document.getElementById('btnEndQuestion')?.addEventListener('click', () => this.executeAction('end_question'));
        document.getElementById('btnShowLeaderboard')?.addEventListener('click', () => this.executeAction('show_leaderboard'));
        document.getElementById('btnNextQuestion')?.addEventListener('click', () => this.executeAction('start_question'));
        document.getElementById('btnEndQuiz')?.addEventListener('click', () => this.executeAction('end_quiz'));
    }

    async executeAction(action, params = {}) {
        try {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('session_id', this.sessionId);

            for (const [key, val] of Object.entries(params)) {
                formData.append(key, val);
            }

            await QuizlyApp.fetchJson(QuizlyApp.baseUrl + '/api/session.php', {
                method: 'POST',
                body: formData
            });

            this.fetchState();
        } catch (err) {
            alert('Action failed: ' + err.message);
        }
    }

    startPolling() {
        this.fetchState();
        this.pollInterval = setInterval(() => {
            if (!document.hidden) {
                this.fetchState();
            }
        }, 1200);
    }

    async fetchState() {
        if (this.isPolling) return;
        this.isPolling = true;

        try {
            const res = await QuizlyApp.fetchJson(`${QuizlyApp.baseUrl}/api/state.php?session_id=${this.sessionId}`);
            const state = res.data;
            this.currentState = state;

            this.renderState(state);
        } catch (err) {
            console.error('Failed to poll host state:', err);
        } finally {
            this.isPolling = false;
        }
    }

    renderState(state) {
        const partEl = document.getElementById('participantCount');
        if (partEl) partEl.innerText = state.participant_count;

        const joinedHeaderEl = document.getElementById('joinedCountHeader');
        if (joinedHeaderEl) joinedHeaderEl.innerText = state.participant_count;

        const ansEl = document.getElementById('answeredCount');
        if (ansEl) ansEl.innerText = state.answered_count;

        const statusEl = document.getElementById('sessionStatusBadge');
        if (statusEl) {
            statusEl.innerText = state.status;
            statusEl.className = 'badge badge-' + (state.status === 'QUESTION_ACTIVE' ? 'success' : 'primary');
        }

        // RENDER LIVE JOINED PARTICIPANTS LIST IN REAL TIME (NO PAGE REFRESH)
        const joinedListEl = document.getElementById('joinedParticipantsList');
        if (joinedListEl && state.participants) {
            if (state.participants.length === 0) {
                joinedListEl.innerHTML = `<span style="color:var(--text-muted); font-size:0.85rem;">Waiting for participants to enter room code...</span>`;
            } else {
                joinedListEl.innerHTML = state.participants.map(p => `
                    <span class="joined-chip">
                        ${QuizlyApp.getAvatarBadgeHtml(p.avatar || p.nickname, p.nickname, 32)}
                        <span>${QuizlyApp.escapeHtml(p.nickname)}</span>
                    </span>
                `).join('');
            }
        }

        const qContainer = document.getElementById('questionContainer');
        if (qContainer && state.question) {
            if (state.question.is_counting_down) {
                document.getElementById('questionText').innerText = `Q${state.question.order_num}: Countdown active (${state.question.get_ready_seconds}s remaining)...`;
                document.getElementById('timerDisplay').innerText = `3s`;
            } else {
                document.getElementById('questionText').innerText = `Q${state.question.order_num}: ${state.question.question_text}`;
                document.getElementById('timerDisplay').innerText = `${state.question.remaining_seconds}s`;
            }
        }

        if (state.distribution) {
            const totalAns = Math.max(1, state.answered_count);
            for (const key of ['A', 'B', 'C', 'D']) {
                const count = state.distribution[key] || 0;
                const pct = Math.round((count / totalAns) * 100);
                
                const fillEl = document.getElementById(`barFill${key}`);
                const countEl = document.getElementById(`barCount${key}`);
                
                if (fillEl) fillEl.style.width = `${pct}%`;
                if (countEl) countEl.innerText = `${count} (${pct}%)`;
            }
        }

        const btnStart = document.getElementById('btnStartQuestion');
        const btnEnd = document.getElementById('btnEndQuestion');
        const btnLeaderboard = document.getElementById('btnShowLeaderboard');
        const btnNext = document.getElementById('btnNextQuestion');
        const btnEndQuiz = document.getElementById('btnEndQuiz');

        if (state.status === 'WAITING') {
            btnStart?.style.setProperty('display', 'inline-flex');
            btnEnd?.style.setProperty('display', 'none');
            btnLeaderboard?.style.setProperty('display', 'none');
            btnNext?.style.setProperty('display', 'none');
        } else if (state.status === 'QUESTION_ACTIVE') {
            btnStart?.style.setProperty('display', 'none');
            btnEnd?.style.setProperty('display', 'inline-flex');
            btnLeaderboard?.style.setProperty('display', 'none');
            btnNext?.style.setProperty('display', 'none');
        } else if (state.status === 'QUESTION_RESULTS') {
            btnStart?.style.setProperty('display', 'none');
            btnEnd?.style.setProperty('display', 'none');
            btnLeaderboard?.style.setProperty('display', 'inline-flex');
            btnNext?.style.setProperty('display', 'inline-flex');
        } else if (state.status === 'LEADERBOARD') {
            btnStart?.style.setProperty('display', 'none');
            btnEnd?.style.setProperty('display', 'none');
            btnLeaderboard?.style.setProperty('display', 'none');
            btnNext?.style.setProperty('display', 'inline-flex');
        }

        if (state.leaderboard && state.leaderboard.length > 0) {
            const lbBody = document.getElementById('leaderboardBody');
            if (lbBody) {
                const newRanksMap = {};

                lbBody.innerHTML = state.leaderboard.map(p => {
                    const pId = p.participant_id;
                    const newRank = parseInt(p.rank);
                    newRanksMap[pId] = newRank;

                    let rankBadgeHtml = `<span class="rank-delta rank-same">—</span>`;
                    if (this.previousRanks[pId] !== undefined) {
                        const delta = this.previousRanks[pId] - newRank;
                        if (delta > 0) {
                            rankBadgeHtml = `<span class="rank-delta rank-up">+${delta}</span>`;
                        } else if (delta < 0) {
                            rankBadgeHtml = `<span class="rank-delta rank-down">-${Math.abs(delta)}</span>`;
                        }
                    }

                    return `
                        <tr>
                            <td><strong>#${p.rank}</strong> ${rankBadgeHtml}</td>
                            <td>
                                <div style="display:flex; align-items:center; gap:0.65rem;">
                                    ${QuizlyApp.getAvatarBadgeHtml(p.avatar || p.nickname, p.nickname, 32)}
                                    <strong>${QuizlyApp.escapeHtml(p.nickname)}</strong>
                                </div>
                            </td>
                            <td><strong>${p.total_score} pts</strong></td>
                        </tr>
                    `;
                }).join('');

                this.previousRanks = newRanksMap;
            }
        }
    }
}
