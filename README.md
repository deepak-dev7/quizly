QUIZLY

AI-Powered Real-Time Quiz & Assessment Platform

QUIZLY is a simple and interactive quiz platform for teachers, students, and organizations. It helps users create quizzes, generate questions with Google Gemini AI, conduct live quizzes, and view results and leaderboards.

Live Demo: http://quizly.gt.tc/

About

QUIZLY brings the complete quiz process into one platform:

Create → Prepare → Play → Score → Analyze

Teachers can create questions manually or use AI to generate questions based on a selected topic and difficulty. Questions can be reviewed before they are added to the question bank.

Live quizzes allow students to join using a room code and answer timed questions while the system calculates scores and updates the leaderboard.

Features

🤖 AI Question Generator

Generate quiz questions using Google Gemini AI.

Generate questions by topic or subject

Select difficulty level

Choose the number of questions

Generate multiple-choice questions

Generate answer options and distractors

Review and edit generated questions

Regenerate individual questions

Save approved questions to the question bank

📝 Question Bank

Manage all quiz questions from one place.

Add questions manually

Add AI-generated questions

Edit questions

Delete questions

Organize questions by topic

Reuse questions in different quizzes

🎮 Live Quiz

Run interactive multiplayer quizzes in real time.

Create a live quiz session

Generate a room code

Students join using the room code

Timed questions

Instant answer feedback

Live participant updates

Live leaderboard

Final results and rankings

🏆 Speed-Based Scoring

QUIZLY rewards both accuracy and speed.

Correct answers receive points based on the configured scoring system, while faster responses can receive higher scores.

The scoring calculation is performed on the server.

📊 Results & Analytics

View quiz and participant performance.

Total scores

Correct answers

Participant rankings

Response performance

Quiz results

Session statistics

Result exports

👥 Organization & Roles

QUIZLY supports organization-based access.

Role

Description

Platform Admin

Manages the overall platform

Organization Owner

Manages an organization

Teacher

Creates and conducts quizzes

🤖 How AI Is Used

Google Gemini is used as an assistant for creating quiz content.

AI can help generate

Questions

Answer options

Distractors

Explanations

Topic-based question sets

Different difficulty levels

Questions for different education levels

AI workflow

Teacher selects requirements
          ↓
Google Gemini generates questions
          ↓
QUIZLY validates the response
          ↓
Teacher reviews the questions
          ↓
Teacher edits or regenerates if needed
          ↓
Teacher approves
          ↓
Questions are saved

AI-generated content is not automatically treated as final assessment content. Human review is recommended before using generated questions in an important examination or assessment.

🔐 Security

QUIZLY includes security controls such as:

Session-based authentication

Role-based access control

Organization-level data separation

CSRF protection

Prepared SQL statements

Input validation

Server-side scoring

Audit logging

Server-side Gemini API integration

Never commit your .env file or API keys to GitHub.

🏗️ Technology Stack

Area

Technology

Frontend

HTML, CSS, JavaScript

Backend

PHP

Database

MySQL / MariaDB

AI

Google Gemini API

Authentication

PHP Sessions

Hosting

PHP/MySQL compatible hosting

📁 Project Structure

quizly/
│
├── admin/          # Administration
├── api/            # Backend APIs
├── assets/         # CSS and JavaScript
├── config/         # Configuration and database
├── dashboard/      # Teacher dashboard
├── database/       # Database schema and migrations
├── includes/       # Authentication, security and helpers
├── live/           # Live quiz interfaces
├── services/       # AI and application services
│
├── index.php       # Home page
├── login.php       # Login
├── register.php    # Registration
├── join.php        # Join live quiz
└── logout.php      # Logout

🚀 Getting Started

Requirements

PHP 7.4 or newer

MySQL / MariaDB

Apache or another PHP-compatible server

PDO MySQL

Google Gemini API key for AI features

1. Clone the repository

git clone https://github.com/deepak-dev7/quizly.git
cd quizly

2. Configure the environment

Create a .env file using .env.example.

Example:

APP_ENV=development

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=quizly_db
DB_USER=root
DB_PASS=

GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=your_available_gemini_model

3. Set up the database

Create a MySQL database and import:

database/schema.sql

Then apply the required files in:

database/migrations/

4. Run the application

For XAMPP, place the project inside:

C:\xampp\htdocs\quizly

Start Apache and MySQL, then open:

http://localhost/quizly/

🌐 Live Deployment

QUIZLY is currently available online:

http://quizly.gt.tc/

The application is deployed on PHP/MySQL-compatible hosting and can be accessed through the live demo above.

🧪 Basic Testing

Before using the application, test:

Login and registration

Quiz creation

Manual question creation

AI question generation

Question review and editing

Saving questions

Live quiz creation

Student joining

Answer submission

Scoring

Leaderboard

Results and analytics

🛣️ Future Improvements

Planned improvements include:

WebSocket-based real-time communication

More question types

Advanced analytics

Student performance tracking

AI-generated explanations

AI question quality checking

Image-based questions

Scheduled quizzes

Better mobile experience

Progressive Web App support

🤝 Contributing

Contributions and suggestions are welcome.

Fork the repository.

Create a new feature branch.

Make your changes.

Test your changes.

Create a pull request.

📄 License

QUIZLY is released under the MIT License.

See the LICENSE file for more information.

👨‍💻 Author

Deepak

GitHub: https://github.com/deepak-dev7/quizly

Live Demo: http://quizly.gt.tc/

⭐ QUIZLY

Create smarter quizzes. Play live. Learn better.
