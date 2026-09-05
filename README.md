# ⚡ QUIZLY — Live Multi-Tenant Quiz Platform with Google Gemini AI

An interactive, multi-tenant live assessment and quiz platform designed for universities, schools, and organizations. Features a native **Google Gemini AI Question Generator**, real-time multiplayer sessions, speed-based scoring engine, and topic-grouped question bank.

---

## 🚀 Key Features

- **✨ AI Question Generator (Google Gemini)**
  - Instantly generates assessment questions by subject, chapter, difficulty, and question count.
  - Strict JSON validation with randomized answer placements (`A`, `B`, `C`, `D`) and distractor verification.
  - Review, edit, and approve questions before adding them to the central question bank.

- **🏷️ Topic-Grouped Question Bank**
  - All questions organized topic-wise with quick counts for AI vs Manual questions.
  - **1-Click Live Launch**: Launch an instant live multiplayer session directly for any topic.
  - Direct import into the interactive Quiz Builder.
  - Clean question deletion with real-time UI updates.

- **🎮 Real-Time Live Quiz Experience**
  - **Host View**: Room code generator, participant lobby, question timers, live leaderboard, and podium.
  - **Student View**: Mobile-responsive player interface with instant feedback and sound effects.
  - **Linear Speed-Based Scoring**: Faster correct answers earn more points (up to 1000 pts) with deterministic tie-breaking.

- **🏢 Multi-Tenant Organization Architecture**
  - Role-based access control (`PLATFORM_ADMIN`, `ORG_OWNER`, `TEACHER`).
  - Strict tenant data isolation per organization/department.
  - Embedded SQLite database with automatic schema migrations + MySQL production support.

- **🗑️ Full Quiz & Question Lifecycle**
  - Cascade-safe quiz and question deletion across both SQLite and MySQL.
  - Draft, published, and archived quiz states.

---

## 🛠️ Technology Stack

- **Backend**: PHP 8.1+ (PDO, Native Sessions, REST APIs)
- **Frontend**: Vanilla HTML5, CSS3 (Design Tokens, Glassmorphism, Responsive Grid), Modern ES6 JavaScript
- **AI Engine**: Google Gemini API (`gemini-2.5-flash-lite`)
- **Database**: SQLite (Zero-configuration embedded) / MySQL 8.0+
- **Security**: CSRF protection, input sanitization, server-authoritative timestamps, API authentication guards

---

## ⚙️ Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/deepak-dev7/quizly.git
   ```

2. **Move to web server directory (e.g. XAMPP htdocs):**
   ```bash
   # Place in c:/xampp/htdocs/quiz or your virtual host root
   ```

3. **Configure Environment:**
   ```bash
   cp .env.example .env
   ```
   Open `.env` and configure your Google Gemini API key:
   ```env
   GEMINI_API_KEY=your_gemini_api_key_here
   GEMINI_MODEL=gemini-2.5-flash-lite
   ```

4. **Initialize Database:**
   - The platform will automatically initialize the embedded SQLite database on first launch.
   - Alternatively, import `database/schema.sql` into MySQL if preferred.

5. **Start Server & Launch:**
   - Start Apache via XAMPP.
   - Open browser: `http://localhost/quiz/`

---

## 🧪 Verification & Test Suite

Run automated accuracy and integration tests:

```bash
# Test Core Platform Scoring, Concurrency & API Contracts
php test_quizly.php

# Test Google Gemini AI Question Generator
php test_gemini_generator.php

# Test Question Bank & AI Question Workflow
php test_ai_generator.php
```

---

## 📄 License
This project is open source and available under the [MIT License](LICENSE).
