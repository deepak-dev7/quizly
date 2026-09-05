/* Student Mobile Logic — State Machine, Option Submission & Completion Redirect */

class StudentController {
    constructor() {
        this.participantToken = sessionStorage.getItem('quizly_participant_token') || '';
        this.sessionId = sessionStorage.getItem('quizly_session_id') || '';
        this.pollInterval = null;
        this.isPolling = false;
        this.currentQuestionId = null;
        this.hasSubmittedCurrentQuestion = false;
        this.hasRedirected = false;
        this.redirectTimer = null;

        this.init();
    }

    init() {
        if (!this.participantToken) {
            alert('Session token missing. Please join again.');
            window.location.href = QuizlyApp.baseUrl + '/join.php';
            return;
        }

        this.bindOptionButtons();
        this.startPolling();
    }

    bindOptionButtons() {
        const buttons = document.querySelectorAll('.option-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const key = btn.getAttribute('data-key');
                this.submitAnswer(key);
            });
        });
    }

    async submitAnswer(optionKey) {
        if (this.hasSubmittedCurrentQuestion || !this.currentQuestionId) return;
        this.hasSubmittedCurrentQuestion = true;

        this.disableOptionButtons(true);

        try {
            const formData = new FormData();
            formData.append('participant_token', this.participantToken);
            formData.append('selected_option_key', optionKey);

            const res = await QuizlyApp.fetchJson(QuizlyApp.baseUrl + '/api/answer.php', {
                method: 'POST',
                body: formData
            });

            const data = res.data;
            this.showAnswerReceipt(data);
        } catch (err) {
            QuizlyApp.showAlert('studentAlert', err.message, 'danger');
            this.hasSubmittedCurrentQuestion = false;
            this.disableOptionButtons(false);
        }
    }

    disableOptionButtons(disabled) {
        const buttons = document.querySelectorAll('.option-btn');
        buttons.forEach(btn => {
            btn.disabled = disabled;
        });
    }

    showAnswerReceipt(data) {
        const viewQuestion = document.getElementById('viewQuestion');
        const viewResult = document.getElementById('viewResult');

        if (viewQuestion) viewQuestion.style.display = 'none';
        if (viewResult) viewResult.style.display = 'block';

        const titleEl = document.getElementById('resultTitle');
        if (titleEl) {
            if (data.is_correct) {
                titleEl.innerText = data.streak_bonus > 0 ? `CORRECT! 🔥 +${data.streak_bonus} STREAK BONUS` : 'CORRECT!';
                titleEl.className = 'result-title correct';
            } else {
                titleEl.innerText = 'INCORRECT';
                titleEl.className = 'result-title wrong';
            }
        }

        const scoreEl = document.getElementById('resultScore');
        if (scoreEl) scoreEl.innerText = `+${data.total_score_earned} pts`;

        const timeEl = document.getElementById('resultTime');
        if (timeEl) timeEl.innerText = data.formatted_time;
    }

    showUnansweredReceipt() {
        const viewQuestion = document.getElementById('viewQuestion');
        const viewResult = document.getElementById('viewResult');

        if (viewQuestion) viewQuestion.style.display = 'none';
        if (viewResult) viewResult.style.display = 'block';

        const titleEl = document.getElementById('resultTitle');
        if (titleEl) {
            titleEl.innerText = "TIME'S UP! (NO ANSWER)";
            titleEl.className = 'result-title wrong';
        }

        const scoreEl = document.getElementById('resultScore');
        if (scoreEl) scoreEl.innerText = '+0 pts';

        const timeEl = document.getElementById('resultTime');
        if (timeEl) timeEl.innerText = '0.000s';
    }

    resetResultView() {
        const titleEl = document.getElementById('resultTitle');
        if (titleEl) {
            titleEl.innerText = "TIME'S UP! (NO ANSWER)";
            titleEl.className = 'result-title wrong';
        }
        const scoreEl = document.getElementById('resultScore');
        if (scoreEl) scoreEl.innerText = '+0 pts';
        const timeEl = document.getElementById('resultTime');
        if (timeEl) timeEl.innerText = '0.000s';
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
            const headers = {
                'X-Participant-Token': this.participantToken
            };

            const res = await QuizlyApp.fetchJson(`${QuizlyApp.baseUrl}/api/state.php?session_id=${this.sessionId}`, { headers });
            const state = res.data;

            this.renderState(state);
        } catch (err) {
            console.error('Student state error:', err);
        } finally {
            this.isPolling = false;
        }
    }

    renderState(state) {
        if (state.student) {
            const rankEl = document.getElementById('studentRank');
            if (rankEl) rankEl.innerText = state.student.rank ? `#${state.student.rank}` : '#--';

            const scoreEl = document.getElementById('studentScore');
            if (scoreEl) scoreEl.innerText = `${state.student.total_score} pts`;

            const avatarSeed = state.student.avatar || sessionStorage.getItem('quizly_avatar') || state.student.nickname;
            const headerAvatar = document.getElementById('studentAvatarHeader');
            if (headerAvatar && !headerAvatar.innerHTML) {
                headerAvatar.innerHTML = QuizlyApp.getAvatarSvg(avatarSeed, 42);
            }
            const waitingAvatar = document.getElementById('viewWaitingAvatar');
            if (waitingAvatar && !waitingAvatar.innerHTML) {
                waitingAvatar.innerHTML = QuizlyApp.getAvatarSvg(avatarSeed, 72);
            }
        }

        const readyOverlay = document.getElementById('studentGetReadyOverlay');
        const readyNum = document.getElementById('studentGetReadyNum');
        const isCountingDown = (state.question && state.question.is_counting_down);

        if (isCountingDown) {
            if (readyOverlay) readyOverlay.style.display = 'block';
            if (readyNum) readyNum.innerText = state.question.get_ready_seconds;
        } else {
            if (readyOverlay) readyOverlay.style.display = 'none';
        }

        const viewWaiting = document.getElementById('viewWaiting');
        const viewQuestion = document.getElementById('viewQuestion');
        const viewResult = document.getElementById('viewResult');
        const viewLeaderboard = document.getElementById('viewLeaderboard');
        const viewCompleted = document.getElementById('viewCompleted');

        if (state.status === 'WAITING') {
            if (viewWaiting) viewWaiting.style.display = 'block';
            if (viewQuestion) viewQuestion.style.display = 'none';
            if (viewResult) viewResult.style.display = 'none';
            if (viewLeaderboard) viewLeaderboard.style.display = 'none';
            if (viewCompleted) viewCompleted.style.display = 'none';
            return;
        }

        if (state.status === 'QUESTION_ACTIVE' && state.question) {
            if (this.currentQuestionId !== state.question.question_id) {
                this.currentQuestionId = state.question.question_id;
                this.hasSubmittedCurrentQuestion = false;
                this.disableOptionButtons(false);
                this.resetResultView();
            }

            if (state.student && state.student.has_submitted) {
                this.showAnswerReceipt({
                    is_correct: state.student.is_correct,
                    total_score_earned: state.student.score_earned,
                    streak_bonus: state.student.streak_bonus || 0,
                    formatted_time: state.student.formatted_time
                });
                return;
            }

            if (viewWaiting) viewWaiting.style.display = 'none';
            if (viewQuestion) viewQuestion.style.display = 'flex';
            if (viewResult) viewResult.style.display = 'none';
            if (viewLeaderboard) viewLeaderboard.style.display = 'none';
            if (viewCompleted) viewCompleted.style.display = 'none';

            document.getElementById('qTimer').innerText = state.question.remaining_seconds;
            document.getElementById('qText').innerText = `Q${state.question.order_num}: ${state.question.question_text}`;

            // Hide options grid while 3-2-1 GET READY overlay is active
            const optionsGrid = document.querySelector('.options-grid');
            if (optionsGrid) {
                optionsGrid.style.display = isCountingDown ? 'none' : 'grid';
            }

            for (const key of ['A', 'B', 'C', 'D']) {
                const optEl = document.getElementById(`optText_${key}`);
                if (optEl && state.question.options[key]) {
                    optEl.innerText = state.question.options[key];
                }
            }
        }

        if (state.status === 'QUESTION_RESULTS') {
            if (viewWaiting) viewWaiting.style.display = 'none';
            if (viewQuestion) viewQuestion.style.display = 'none';
            if (viewResult) viewResult.style.display = 'block';
            if (viewLeaderboard) viewLeaderboard.style.display = 'none';
            if (viewCompleted) viewCompleted.style.display = 'none';

            if (state.student) {
                if (state.student.has_submitted) {
                    this.showAnswerReceipt({
                        is_correct: state.student.is_correct,
                        total_score_earned: state.student.score_earned,
                        streak_bonus: state.student.streak_bonus || 0,
                        formatted_time: state.student.formatted_time || (state.student.response_time_ms ? (state.student.response_time_ms / 1000).toFixed(3) + 's' : '0.000s')
                    });
                } else {
                    this.showUnansweredReceipt();
                }
            }
        }

        if (state.status === 'LEADERBOARD' && state.leaderboard) {
            if (viewWaiting) viewWaiting.style.display = 'none';
            if (viewQuestion) viewQuestion.style.display = 'none';
            if (viewResult) viewResult.style.display = 'none';
            if (viewLeaderboard) viewLeaderboard.style.display = 'block';
            if (viewCompleted) viewCompleted.style.display = 'none';

            if (state.student) {
                document.getElementById('myMobileRankBanner').innerText = `RANK #${state.student.rank || '--'}`;
            }

            const mobileLbList = document.getElementById('mobileLeaderboardList');
            if (mobileLbList) {
                const top5 = state.leaderboard.slice(0, 5);
                const myId = state.student ? state.student.participant_id : null;

                mobileLbList.innerHTML = top5.map(p => {
                    const isMe = (p.participant_id === myId);
                    const lastTime = p.last_formatted_time ? p.last_formatted_time : '—';
                    const avatarSeed = p.avatar || p.nickname;
                    return `
                        <div class="mobile-lb-row ${isMe ? 'my-score-row' : ''}">
                            <div style="display:flex; align-items:center; gap:0.65rem;">
                                <span style="font-weight:900; color:var(--primary); font-size:1.1rem; min-width:28px;">#${p.rank}</span>
                                ${QuizlyApp.getAvatarBadgeHtml(avatarSeed, p.nickname, 32)}
                                <span>${QuizlyApp.escapeHtml(p.nickname)} ${isMe ? '(YOU)' : ''}</span>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.75rem; color:var(--text-muted); font-family:monospace;">⏱ ${lastTime}</div>
                                <div style="font-weight:900; color:var(--success); font-family:monospace;">${p.total_score} pts</div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // QUIZ COMPLETED OR CANCELLED STATE BY HOST — SHOW ENDED SCREEN & AUTO-REDIRECT
        if (inArray(state.status, ['COMPLETED', 'CANCELLED'])) {
            if (viewWaiting) viewWaiting.style.display = 'none';
            if (viewQuestion) viewQuestion.style.display = 'none';
            if (viewResult) viewResult.style.display = 'none';
            if (viewLeaderboard) viewLeaderboard.style.display = 'none';
            if (viewCompleted) viewCompleted.style.display = 'block';

            if (state.student) {
                const finalRankEl = document.getElementById('finalRankText');
                if (finalRankEl) finalRankEl.innerText = `FINAL RANK #${state.student.rank || '--'}`;

                const finalScoreEl = document.getElementById('finalScoreText');
                if (finalScoreEl) finalScoreEl.innerText = `${state.student.total_score} pts`;
            }

            this.triggerHomeRedirect();
        }
    }

    triggerHomeRedirect() {
        if (this.hasRedirected) return;
        this.hasRedirected = true;

        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }

        let secondsLeft = 5;
        const countdownEl = document.getElementById('redirectCountdown');

        this.redirectTimer = setInterval(() => {
            secondsLeft--;
            if (countdownEl) countdownEl.innerText = secondsLeft;

            if (secondsLeft <= 0) {
                clearInterval(this.redirectTimer);
                sessionStorage.removeItem('quizly_participant_token');
                sessionStorage.removeItem('quizly_session_id');
                window.location.href = QuizlyApp.baseUrl + '/index.php';
            }
        }, 1000);
    }
}

function inArray(needle, haystack) {
    return haystack.indexOf(needle) !== -1;
}
