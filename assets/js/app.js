/* QUIZLY Global Client-Side Application Core & Utility Helper Functions */

class QuizlyApp {
    /**
     * Dynamically resolve base URL path for live hosting vs localhost subfolder
     */
    static get baseUrl() {
        if (typeof window.QUIZLY_BASE_URL !== 'undefined') {
            return window.QUIZLY_BASE_URL;
        }
        const path = window.location.pathname;
        const quizIdx = path.indexOf('/quiz');
        if (quizIdx !== -1) {
            return path.substring(0, quizIdx + 5);
        }
        return '';
    }

    /**
     * Standardized fetch wrapper for QUIZLY JSON APIs with robust error handling.
     */
    static async fetchJson(url, options = {}) {
        const defaultHeaders = {
            'Accept': 'application/json'
        };

        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.body = JSON.stringify(options.body);
            defaultHeaders['Content-Type'] = 'application/json';
        }

        options.headers = { ...defaultHeaders, ...(options.headers || {}) };

        try {
            const res = await fetch(url, options);
            const text = await res.text();

            let json;
            try {
                json = JSON.parse(text);
            } catch (e) {
                // If response is HTML error page or unhandled PHP exception, strip HTML tags to display root cause
                const cleanError = text.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
                throw new Error(cleanError || 'Server error: Invalid response received from API.');
            }

            if (!res.ok || json.status === 'error') {
                const errorMsg = json.message || (json.error ? json.error.message : 'API Request failed');
                throw new Error(errorMsg);
            }

            return json;
        } catch (err) {
            throw err;
        }
    }

    /**
     * Render GitHub-style alert notification inside container.
     */
    static showAlert(containerId, message, type = 'danger') {
        const container = document.getElementById(containerId);
        if (!container) return;

        const alertHtml = `
            <div class="alert alert-${type}">
                ${QuizlyApp.escapeHtml(message)}
            </div>
        `;
        container.innerHTML = alertHtml;
    }

    /**
     * Clear alert box container.
     */
    static clearAlert(containerId) {
        const container = document.getElementById(containerId);
        if (container) {
            container.innerHTML = '';
        }
    }

    /**
     * Generate a unique avatar seed based on nickname & timestamp
     */
    static generateAvatarSeed(nickname = '') {
        const rand = Math.floor(Math.random() * 9000) + 1000;
        const prefix = (nickname || 'player').toLowerCase().replace(/[^a-z0-9]/g, '');
        return `${prefix || 'user'}_${rand}`;
    }

    /**
     * Fast string hashing helper for deterministic avatar styling
     */
    static stringHash(str) {
        let hash = 0;
        const s = String(str || 'default');
        for (let i = 0; i < s.length; i++) {
            hash = (hash << 5) - hash + s.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash);
    }

    /**
     * Generate rich SVG Avatar markup with vibrant gradient backgrounds & unique icons
     */
    static getAvatarSvg(seed, size = 48) {
        const hash = QuizlyApp.stringHash(seed);
        const gradients = [
            ['#FF512F', '#DD2476'],
            ['#2193b0', '#6dd5ed'],
            ['#8A2387', '#F27121'],
            ['#11998e', '#38ef7d'],
            ['#654ea3', '#eaafc8'],
            ['#00c6ff', '#0072ff'],
            ['#f12711', '#f5af19'],
            ['#7F00FF', '#E100FF'],
            ['#FF007F', '#7F00FF'],
            ['#00F2FE', '#4FACFE']
        ];

        const icons = ['🚀', '⚡', '👑', '🎯', '🔥', '💎', '👾', '🦊', '🐯', '🐼', '🦁', '🐸', '🤖', '🛸', '🌟', '🦄', '🐲', '🍕', '🎮', '🏆'];
        
        const grad = gradients[hash % gradients.length];
        const icon = icons[(hash >> 3) % icons.length];
        const gradId = `grad_${hash}_${size}_${Math.floor(Math.random()*1000)}`;

        return `
            <svg width="${size}" height="${size}" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="quizly-avatar-svg" style="border-radius:24px; box-shadow:0 4px 14px rgba(0,0,0,0.15);">
                <defs>
                    <linearGradient id="${gradId}" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="${grad[0]}" />
                        <stop offset="100%" stop-color="${grad[1]}" />
                    </linearGradient>
                </defs>
                <rect width="100" height="100" rx="28" fill="url(#${gradId})" />
                <circle cx="50" cy="50" r="38" fill="rgba(255,255,255,0.2)" stroke="rgba(255,255,255,0.4)" stroke-width="2" />
                <text x="50" y="59" font-size="44" text-anchor="middle" dominant-baseline="middle">${icon}</text>
            </svg>
        `;
    }

    /**
     * Wrap SVG Avatar in responsive HTML container
     */
    static getAvatarBadgeHtml(seed, nickname = '', size = 40) {
        const avatarSeed = seed || nickname || 'user';
        const svg = QuizlyApp.getAvatarSvg(avatarSeed, size);
        return `<span class="avatar-badge-wrapper" style="width:${size}px; height:${size}px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; vertical-align:middle;">${svg}</span>`;
    }

    /**
     * HTML character escaper for XSS prevention.
     */
    static escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}

// Auto-initialize mobile menu toggle handler
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    if (toggleBtn && navLinks) {
        toggleBtn.addEventListener('click', () => {
            navLinks.classList.toggle('mobile-open');
        });
    }
});
