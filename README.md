QUIZLY

AI-Powered Real-Time Quiz & Assessment Platform

QUIZLY is a web-based quiz platform that helps teachers and organizations create, manage, and conduct interactive quizzes. It combines Google Gemini AI for question generation with real-time multiplayer quizzes, question banks, speed-based scoring, live leaderboards, and analytics.

🌐 Live Demo

🚀 http://quizly.gt.tc/

✨ Features

🤖 AI Question Generator — Generate questions using Google Gemini based on topic, difficulty, question count, and other requirements.

📝 Question Bank — Create, edit, organize, reuse, and delete questions.

🎯 Quiz Builder — Build quizzes using questions from the question bank.

🎮 Live Multiplayer Quiz — Host live quiz sessions and let students join using a room code.

⏱️ Timed Questions — Keep quizzes fast and engaging with question timers.

🏆 Speed-Based Scoring — Reward correct and fast answers with higher scores.

📊 Live Leaderboard — Display participant rankings during a live session.

📈 Results & Analytics — Review quiz and participant performance.

👥 Multi-Tenant Organizations — Support separate organizations, users, quizzes, and question data.

🔐 Role-Based Access — Supports platform administrators, organization owners, and teachers.

🤖 AI Usage

QUIZLY uses Google Gemini as an assistant for creating assessment content.

The AI can generate:

Multiple-choice questions

Answer options and distractors

Explanations

Questions based on a selected topic or chapter

Questions for different difficulty levels and education levels

AI-generated questions are reviewed by the teacher before being saved. The application validates the generated data and does not allow the AI to directly write to the database.

AI Workflow

Teacher selects requirements
        ↓
Google Gemini generates questions
        ↓
QUIZLY validates the response
        ↓
Teacher reviews and edits
        ↓
Approve
        ↓
Save to Question Bank

AI-generated content should always be reviewed for accuracy before being used in a formal assessment.

🎮 How a Live Quiz Works

Teacher creates quiz
        ↓
Launches live session
        ↓
Students join with room code
        ↓
Teacher starts questions
        ↓
Students answer
        ↓
QUIZLY calculates scores
        ↓
Leaderboard updates
        ↓
Final results

🏗️ Technology Stack

Frontend

HTML5

CSS3

JavaScript

Backend

PHP

PDO

MySQL / MariaDB

AI

Google Gemini API

Hosting

PHP/MySQL compatible hosting

Current live deployment: InfinityFree

📁 Project Structure

quizly/
├── admin/          # Platform administration
├── api/            # Backend API endpoints
├── assets/         # CSS and JavaScript
├── config/         # Application and database configuration
├── dashboard/      # Teacher and organization dashboard
├── database/       # Database schema and migrations
├── includes/       # Authentication, scoring, security and helpers
├── live/           # Host, student and presentation views
├── services/       # AI and application services
├── index.php       # Application entry point
├── login.php       # Login
├── register.php    # Registration
└── join.php        # Join a live quiz

🚀 Getting Started

Requirements

PHP 7.4+

MySQL / MariaDB

Apache or another PHP-compatible web server

PDO MySQL

Google Gemini API key for AI features

Installation

Clone the repository:

git clone https://github.com/deepak-dev7/quizly.git
cd quizly

Create a .env file using .env.example and configure:

APP_ENV=development

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=quizly_db
DB_USER=root
DB_PASS=

GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=your_available_gemini_model

Create the database and import:

database/schema.sql

Apply any required migrations from:

database/migrations/

Start Apache and MySQL, then open:

http://localhost/quizly/

🔐 Security

QUIZLY uses several security practices, including:

Session-based authentication

Role-based authorization

Organization-level data isolation

CSRF protection

Prepared SQL statements

Input validation

Server-side scoring

Audit logging

Server-side Gemini API integration

Never commit your .env file or API keys to GitHub.

🛣️ Roadmap

Planned improvements include:

WebSocket-based real-time communication

More question types

Advanced analytics

Student performance tracking

AI-generated explanations

AI question quality evaluation

Image-based questions

Scheduled quizzes

Improved mobile experience

Progressive Web App support

🤝 Contributing

Contributions and suggestions are welcome.

Fork the repository.

Create a feature branch.

Make your changes.

Test the changes.

Open a pull request.

📄 License

This project is licensed under the MIT License.

See LICENSE for details.

👨‍💻 Author

Deepak

GitHub: https://github.com/deepak-dev7/quizly

Live Demo: http://quizly.gt.tc/

<p align="center">
  <strong>QUIZLY</strong><br>
  Create smarter quizzes. Learn better. Play live.
</p>
