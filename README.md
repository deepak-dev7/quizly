# QUIZLY
## AI-Powered Real-Time Quiz & Assessment Platform

QUIZLY is a web-based quiz platform for teachers, students, and organizations.
It combines AI-assisted question generation with live multiplayer quizzes,
question banks, scoring, leaderboards, and results.

**Live Demo:** http://quizly.gt.tc/

## About

QUIZLY makes digital assessments easier to create and more engaging to conduct.
Teachers can create questions manually or use Google Gemini AI to generate them.
Generated questions can be reviewed and edited before they are used in a quiz.

A teacher can prepare a quiz, publish it, launch a live session, and share a
room code with students. Students join the room, answer timed questions,
and receive scores and rankings during the session.

**Create → Prepare → Play → Score → Review**

## Features

### AI Question Generator

QUIZLY uses Google Gemini as an assistant for creating quiz content.
Teachers can select a subject, topic, difficulty, question count, and other
requirements. Gemini generates the requested questions and QUIZLY validates
the response before showing it to the teacher.

Teachers can review, edit, remove, or regenerate questions before approval.

### Question Bank

The Question Bank is the central place for managing reusable questions.
Teachers can create questions manually, save approved AI questions, edit
existing questions, organize them by topic, and reuse them in different quizzes.

### Quiz Builder

Teachers can build quizzes using questions from the question bank.
A quiz can be prepared in advance and published when it is ready for a live session.

### Live Multiplayer Quiz

QUIZLY allows multiple students to participate in the same live quiz.
The teacher launches a session and receives a room code. Students use the
code to join the lobby and participate when the teacher starts the quiz.

```text
Teacher creates quiz
        ↓
Launch live session
        ↓
Students join with room code
        ↓
Questions and timer
        ↓
Answers and scoring
        ↓
Live leaderboard
        ↓
Final results
```

### Scoring

QUIZLY considers accuracy and response speed when calculating scores.
Correct answers receive points, and faster correct responses can receive
higher scores. Scoring is processed on the server.

### Leaderboard and Results

The leaderboard shows participant rankings during a live session.
After the quiz, teachers can review scores, answers, rankings, and performance.

### Organizations and Roles

QUIZLY supports organization-based access with different responsibilities.
Platform administrators manage the platform, organization owners manage their
organization, and teachers create and conduct quizzes.

## AI Usage

Google Gemini is used to reduce the time required to create quiz questions.
It can generate questions, answer options, distractors, explanations, and
topic-based question sets according to the teacher's requirements.

The AI does not directly control the QUIZLY database, permissions, or scoring.

```text
Teacher requirements
        ↓
QUIZLY Backend
        ↓
Google Gemini
        ↓
Generated questions
        ↓
Validation
        ↓
Teacher review
        ↓
Question Bank
```

Human review is recommended because AI-generated content can sometimes contain
incorrect information, ambiguous wording, or unsuitable answer choices.
The teacher remains responsible for approving the final assessment content.

The Gemini API key is kept on the server and must never be exposed publicly.

## Technology Stack

| Component | Technology |
|-----------|------------|
| Frontend | HTML, CSS, JavaScript |
| Backend | PHP |
| Database | MySQL / MariaDB |
| AI | Google Gemini API |
| Authentication | PHP Sessions |
| Hosting | PHP/MySQL hosting |

## Project Structure

```text
quizly/
├── admin/          Administration
├── api/            Backend APIs
├── assets/         CSS and JavaScript
├── config/         Configuration
├── dashboard/      Teacher dashboard
├── database/       Schema and migrations
├── includes/       Security and helpers
├── live/           Live quiz pages
├── services/       Application and AI services
├── index.php       Home page
├── login.php       Login
├── register.php    Registration
└── join.php        Join a live quiz
```

## Database

QUIZLY uses a relational database to manage organizations, users, quizzes,
questions, live sessions, participants, answers, and audit information.

The main tables include:

```text
organizations
users
quizzes
questions
question_options
quiz_sessions
participants
answers
audit_logs
```

## Security

QUIZLY uses session-based authentication and role-based authorization.
The application includes CSRF protection, prepared SQL statements,
input validation, server-side scoring, audit logging, and organization-aware access.

The Gemini API is accessed through the backend so credentials remain private.
Production deployments should use HTTPS and secure environment configuration.

**Never commit `.env` files, passwords, or API keys to GitHub.**

## Getting Started

### Requirements

PHP 7.4+, MySQL or MariaDB, Apache, PDO MySQL, and a modern browser are required.
A Google Gemini API key is required for AI features.

### Installation

Clone the repository:

```bash
git clone https://github.com/deepak-dev7/quizly.git
cd quizly
```

Create a `.env` file using `.env.example`.

```env
APP_ENV=development
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=quizly_db
DB_USER=root
DB_PASS=
GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=your_available_gemini_model
```

Create the database and import `database/schema.sql`.
Apply the required migration files from `database/migrations/`.

For XAMPP, place the project inside `htdocs`, start Apache and MySQL,
and open the application in your browser.

## Live Deployment

QUIZLY is currently available online:

**http://quizly.gt.tc/**

The live version provides the main QUIZLY experience, including quiz management,
AI-assisted question generation, live sessions, scoring, and results.

## Testing

Test the complete workflow from login to final results.
Verify quiz creation, question management, AI generation, question review,
live sessions, student joining, answer submission, scoring, leaderboard updates,
and final results.

For live quizzes, testing with multiple browsers or devices is recommended.

## Future Development

Future improvements may include WebSocket communication, additional question types,
advanced analytics, student performance tracking, AI explanations, image questions,
scheduled quizzes, and improved mobile support.

## Contributing

Contributions and suggestions are welcome.
Create a feature branch, make your changes, test the application, and submit a pull request.

Please do not commit API keys, passwords, `.env` files, or private user information.

## License

QUIZLY is released under the MIT License.
See the `LICENSE` file for the complete license.

## Author

**Deepak**

QUIZLY is an independent project focused on making digital assessments
easier to create and more engaging to conduct.

**GitHub:** https://github.com/deepak-dev7/quizly

**Live Demo:** http://quizly.gt.tc/

**QUIZLY — Create smarter quizzes. Play live. Learn better.**
