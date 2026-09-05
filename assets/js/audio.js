/* QUIZLY Synthesized Web Audio Engine — Zero External Audio File Dependencies */

class QuizlyAudioEngine {
    constructor() {
        this.audioCtx = null;
        this.isMuted = false;
    }

    init() {
        if (!this.audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                this.audioCtx = new AudioContextClass();
            }
        }
        if (this.audioCtx && this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }
    }

    playTick() {
        if (this.isMuted) return;
        this.init();
        if (!this.audioCtx) return;

        const osc = this.audioCtx.createOscillator();
        const gain = this.audioCtx.createGain();

        osc.type = 'sine';
        osc.frequency.setValueAtTime(800, this.audioCtx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(400, this.audioCtx.currentTime + 0.08);

        gain.gain.setValueAtTime(0.15, this.audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, this.audioCtx.currentTime + 0.08);

        osc.connect(gain);
        gain.connect(this.audioCtx.destination);

        osc.start();
        osc.stop(this.audioCtx.currentTime + 0.08);
    }

    playSuccess() {
        if (this.isMuted) return;
        this.init();
        if (!this.audioCtx) return;

        const now = this.audioCtx.currentTime;
        const notes = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6 arpeggio

        notes.forEach((freq, idx) => {
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();

            osc.type = 'triangle';
            osc.frequency.setValueAtTime(freq, now + idx * 0.08);

            gain.gain.setValueAtTime(0.2, now + idx * 0.08);
            gain.gain.exponentialRampToValueAtTime(0.01, now + idx * 0.08 + 0.3);

            osc.connect(gain);
            gain.connect(this.audioCtx.destination);

            osc.start(now + idx * 0.08);
            osc.stop(now + idx * 0.08 + 0.3);
        });
    }

    playError() {
        if (this.isMuted) return;
        this.init();
        if (!this.audioCtx) return;

        const now = this.audioCtx.currentTime;
        const osc = this.audioCtx.createOscillator();
        const gain = this.audioCtx.createGain();

        osc.type = 'sawtooth';
        osc.frequency.setValueAtTime(150, now);
        osc.frequency.exponentialRampToValueAtTime(90, now + 0.25);

        gain.gain.setValueAtTime(0.25, now);
        gain.gain.exponentialRampToValueAtTime(0.01, now + 0.25);

        osc.connect(gain);
        gain.connect(this.audioCtx.destination);

        osc.start(now);
        osc.stop(now + 0.25);
    }

    playFanfare() {
        if (this.isMuted) return;
        this.init();
        if (!this.audioCtx) return;

        const now = this.audioCtx.currentTime;
        const chords = [
            { freq: 440.00, time: 0 },
            { freq: 554.37, time: 0.1 },
            { freq: 659.25, time: 0.2 },
            { freq: 880.00, time: 0.35 }
        ];

        chords.forEach(c => {
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();

            osc.type = 'square';
            osc.frequency.setValueAtTime(c.freq, now + c.time);

            gain.gain.setValueAtTime(0.15, now + c.time);
            gain.gain.exponentialRampToValueAtTime(0.01, now + c.time + 0.5);

            osc.connect(gain);
            gain.connect(this.audioCtx.destination);

            osc.start(now + c.time);
            osc.stop(now + c.time + 0.5);
        });
    }

    toggleMute() {
        this.isMuted = !this.isMuted;
        return this.isMuted;
    }
}

const quizlyAudio = new QuizlyAudioEngine();
