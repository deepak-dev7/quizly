QUIZLY
AI-Powered Real-Time Quiz & Assessment Platform
<p align="center">
  <strong>Create smarter assessments. Run engaging live quizzes. Get results instantly.</strong>
</p>
<p align="center">
  QUIZLY is a web-based quiz and assessment platform that combines Google Gemini-powered question generation with live multiplayer quizzes, reusable question banks, speed-based scoring, organization-level access control, and performance analytics.
</p>
<p align="center">
  <a href="http://quizly.gt.tc/"><strong>🚀 Live Demo</strong></a>
  &nbsp; • &nbsp;
  <a href="https://github.com/deepak-dev7/quizly"><strong>GitHub Repository</strong></a>
</p>
<p align="center">
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B%20%7C%208.x-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Gemini](https://img.shields.io/badge/AI-Google%20Gemini-4285F4?style=flat-square&logo=google&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6%2B-F7DF1E?style=flat-square&logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)
</p>
---
🌐 Live Application
QUIZLY is available online:
👉 http://quizly.gt.tc/
The live application provides the core QUIZLY workflow:
Account and role-based access
Quiz creation and management
Question bank management
AI-assisted question generation
Question review and editing
Live quiz rooms
Student participation
Timed questions
Server-side scoring
Live leaderboard
Results and analytics
> The live demo is hosted on shared PHP/MySQL hosting. Availability and AI features depend on the hosting environment and the configured Gemini API credentials.
---
📖 About QUIZLY
QUIZLY was built around a simple idea:
> **Creating a good quiz should take minutes, not hours.**
Traditional quiz tools often make educators manually write every question, configure every option, organize content, launch the assessment, and calculate results.
QUIZLY brings these steps together into one workflow.
An administrator or teacher can create a quiz manually or use Google Gemini to generate a batch of questions based on a topic, chapter, difficulty, education level, language, and other requirements.
The generated questions are then reviewed by the teacher before they become part of the question bank.
Once a quiz is ready, it can be launched as a live session. Participants join through a room code, answer timed questions, and compete on accuracy and response speed.
---
🎯 What QUIZLY Solves
QUIZLY focuses on four common problems in assessment workflows:
1. Creating questions takes time
AI-assisted generation helps teachers produce an initial question set quickly.
2. AI-generated content needs human review
QUIZLY does not treat AI output as automatically approved content.
Questions can be previewed, edited, regenerated, and approved before being saved.
3. Live assessments can feel static
QUIZLY introduces timed questions, speed-based scoring, live rankings, and a participant experience designed for real-time sessions.
4. Organizations need separation
QUIZLY uses an organization-aware data model so users, quizzes, questions, and sessions can be associated with the correct organization.
---
✨ Core Features
🤖 AI Question Generator
QUIZLY uses Google Gemini as an AI assistant for question creation.
A teacher can specify:
Subject / topic
Chapter
Number of questions
Question type
Difficulty
Education level
Language
Question timer
Maximum points
Whether to include explanations
Whether to include topics
Whether to include learning objectives
Additional instructions
The system then asks Gemini to return structured question data.
AI workflow
```text
Teacher selects requirements
            ↓
QUIZLY builds a structured prompt
            ↓
Google Gemini generates questions
            ↓
QUIZLY extracts the response
            ↓
JSON is parsed and validated
            ↓
Question structure is checked
            ↓
Duplicate detection is performed
            ↓
Questions appear in preview
            ↓
Teacher edits / regenerates
            ↓
Teacher approves
            ↓
Questions are saved to the question bank
```
Important design decision
AI does not have direct access to the database.
The application controls the process:
```text
Browser
   ↓
QUIZLY Backend
   ↓
Gemini API
   ↓
QUIZLY Validation
   ↓
Teacher Review
   ↓
Database
```
This makes AI a content-generation assistant rather than an uncontrolled database writer.
---
🧠 AI Usage & Responsible AI Design
AI is one of the main features of QUIZLY, but the platform is designed around human-in-the-loop content creation.
What AI is used for
Google Gemini can help with:
Generating multiple-choice questions
Creating distractor options
Matching questions to a requested difficulty
Adapting questions to an education level
Generating explanations
Producing learning objectives
Regenerating an individual question
Creating questions in a requested language
Producing question sets from a topic or chapter
What AI does not control
Gemini does not directly control:
User authentication
Organization permissions
Quiz publishing
Participant access
Live session state
Final scoring
Leaderboard calculations
Database authorization
Question approval
These responsibilities remain inside the QUIZLY application.
Human review
AI-generated questions should be reviewed before being used in a formal assessment.
A teacher can:
Read the generated question.
Check the answer.
Edit the wording.
Change options.
Regenerate a question.
Remove unwanted questions.
Approve the final content.
This approach is intentional because generative AI can occasionally produce inaccurate, ambiguous, outdated, or poorly phrased content.
---
🧪 AI Question Validation
AI output is treated as untrusted external data.
QUIZLY validates the generated response before it can be used.
Validation includes:
JSON parsing
Required field validation
Question text validation
Question type validation
Option validation
Correct-answer validation
Duplicate option checks
Difficulty normalization
Expected option count checks
Distractor validation
Requested question-count handling
Organization-level duplicate detection
For example, a multiple-choice question is expected to contain distinct answer options and exactly one correct option.
If the AI response does not satisfy the expected structure, the application rejects it rather than blindly inserting it into the database.
---
🔄 Regenerating Questions
Individual questions can be regenerated without regenerating the entire batch.
Example:
```text
Generated Set
 ├── Question 1 ✓
 ├── Question 2 ✓
 ├── Question 3 ✕ → Regenerate
 ├── Question 4 ✓
 └── Question 5 ✓
```
This keeps the workflow efficient when only one question needs improvement.
---
📝 Question Bank
The Question Bank is the central content library of QUIZLY.
Questions can contain:
Question text
Question type
Answer options
Correct answer
Topic
Difficulty
Explanation
Learning objective
Timer
Maximum points
AI/manual origin
AI model metadata
Generation timestamp
The question bank is designed for reuse across quizzes rather than forcing teachers to recreate questions every time.
---
📚 Topic-Based Organization
Questions can be associated with topics and chapters.
This makes it easier to build quizzes around specific areas of a subject.
Example:
```text
Computer Science
│
├── Data Structures
│   ├── Arrays
│   ├── Linked Lists
│   ├── Stacks
│   └── Queues
│
├── Operating Systems
│   ├── Processes
│   ├── Scheduling
│   └── Memory Management
│
└── Database Systems
    ├── SQL
    ├── Normalization
    └── Transactions
```
---
🎮 Real-Time Live Quiz
QUIZLY supports live multiplayer quiz sessions.
A teacher can create and launch a session and share a short room code with participants.
Host flow
```text
Create Quiz
    ↓
Publish Quiz
    ↓
Launch Live Session
    ↓
Generate Room Code
    ↓
Participants Join
    ↓
Start Question
    ↓
Monitor Responses
    ↓
Show Results / Leaderboard
    ↓
Next Question
    ↓
Complete Session
```
Participant flow
```text
Open QUIZLY
    ↓
Enter Room Code
    ↓
Enter Nickname
    ↓
Join Lobby
    ↓
Wait for Host
    ↓
Answer Timed Questions
    ↓
Receive Feedback
    ↓
View Score
    ↓
View Ranking
    ↓
Final Results
```
---
⏱️ Speed-Based Scoring
QUIZLY does more than count correct answers.
The scoring engine considers response speed so that participants who answer correctly and quickly can earn more points.
The default maximum question score is:
```text
1000 points
```
The application also supports streak bonuses.
The scoring calculation is performed on the server rather than trusting the browser.
This helps prevent a participant from simply modifying client-side JavaScript to award themselves additional points.
---
🏆 Leaderboard
During a live session, participants can be ranked based on their accumulated performance.
The system records information such as:
Total score
Correct answers
Streak
Response time
Score earned per question
Leaderboard ordering is deterministic so that ties can be handled consistently.
---
📊 Analytics & Results
QUIZLY provides result and analytics views for quiz sessions.
The platform can track:
Participant performance
Total score
Correct answer count
Response time
Question-level performance
Session rankings
Quiz statistics
Results can also be exported for further use.
---
🏢 Multi-Tenant Organization Architecture
QUIZLY is designed to support multiple organizations within the same application.
The main roles are:
Role	Description
`PLATFORM_ADMIN`	Manages the overall QUIZLY platform
`ORG_OWNER`	Manages an organization
`TEACHER`	Creates and conducts quizzes
The database associates relevant records with an organization.
Conceptually:
```text
Platform
│
├── Organization A
│   ├── Users
│   ├── Quizzes
│   ├── Questions
│   └── Sessions
│
├── Organization B
│   ├── Users
│   ├── Quizzes
│   ├── Questions
│   └── Sessions
│
└── Organization C
    ├── Users
    ├── Quizzes
    ├── Questions
    └── Sessions
```
The application is designed so organization-specific data is queried using the relevant organization context.
---
🔐 Security
Security is considered at both the application and AI integration layers.
Authentication
QUIZLY uses:
Session-based authentication
Password hashing
Role-based access control
Protected dashboard pages
Organization-aware authorization
Application security
The project includes mechanisms for:
CSRF protection
Prepared database statements
Input validation
API request validation
Rate limiting
Audit logging
Session cookie security
Server-side scoring
AI security
The Gemini API key must remain server-side.
It should be stored in an environment configuration such as:
```env
GEMINI_API_KEY=your_api_key
```
The key must never be placed in:
JavaScript
HTML
Public API responses
Git commits
README files
Screenshots
Client-side source code
---
🗄️ Database Architecture
QUIZLY uses a relational database design.
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
Relationship overview
```text
Organization
    │
    ├── Users
    │
    └── Quizzes
          │
          └── Questions
                │
                └── Options

Quiz
  │
  └── Quiz Session
        │
        ├── Participants
        │
        └── Answers
```
Foreign keys and indexes are used to maintain relationships and support common query patterns.
---
🏗️ Project Structure
```text
quizly/
│
├── admin/
│   ├── analytics.php
│   ├── index.php
│   ├── organizations.php
│   ├── settings.php
│   └── users.php
│
├── api/
│   ├── ai/
│   │   ├── generate_questions.php
│   │   ├── regenerate_question.php
│   │   └── save_questions.php
│   │
│   ├── analytics.php
│   ├── answer.php
│   ├── auth.php
│   ├── health.php
│   ├── join.php
│   ├── quizzes.php
│   ├── session.php
│   └── state.php
│
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   ├── host.css
│   │   ├── student.css
│   │   └── presentation.css
│   │
│   └── js/
│       ├── app.js
│       ├── audio.js
│       ├── host.js
│       ├── presentation.js
│       └── student.js
│
├── config/
│   ├── config.php
│   └── database.php
│
├── dashboard/
│   ├── ai_generate.php
│   ├── analytics.php
│   ├── create_quiz.php
│   ├── edit_quiz.php
│   ├── export_results.php
│   ├── index.php
│   ├── live_sessions.php
│   ├── question_bank.php
│   ├── quizzes.php
│   ├── results.php
│   ├── settings.php
│   └── view_quiz.php
│
├── database/
│   ├── schema.sql
│   └── migrations/
│       └── 001_add_ai_question_metadata.sql
│
├── includes/
│   ├── audit.php
│   ├── auth.php
│   ├── csrf.php
│   ├── header.php
│   ├── response.php
│   ├── roomcode.php
│   ├── scoring.php
│   └── sidebar.php
│
├── live/
│   ├── host.php
│   ├── presentation.php
│   └── student.php
│
├── services/
│   ├── AIQuestionGenerator.php
│   └── GeminiClient.php
│
├── .env.example
├── .gitignore
├── .htaccess
├── index.php
├── join.php
├── login.php
├── logout.php
└── register.php
```
---
⚙️ Technology Stack
Backend
PHP 7.4+
PDO
MySQL / MariaDB
Session-based authentication
Frontend
HTML5
CSS3
JavaScript
Responsive layouts
Separate host, student, and presentation interfaces
AI
Google Gemini API
Structured JSON generation
Server-side API integration
AI response validation
Duplicate detection
Question regeneration
Hosting
QUIZLY is designed to work with conventional PHP/MySQL hosting and is currently demonstrated on an InfinityFree-hosted deployment.
---
🚀 Installation
Requirements
For local development, install:
PHP 7.4 or newer
Apache or another PHP-compatible web server
MySQL / MariaDB
PDO
PDO MySQL
Modern web browser
For AI generation:
A Google Gemini API key
A Gemini model available to your API project
---
1. Clone the repository
```bash
git clone https://github.com/deepak-dev7/quizly.git
cd quizly
```
---
2. Create the environment file
Copy `.env.example` to `.env`.
Example:
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
Never commit the real `.env` file.
---
3. Create the database
Create a MySQL database:
```sql
CREATE DATABASE quizly_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;
```
Import:
```text
database/schema.sql
```
Then apply the AI metadata migration:
```text
database/migrations/001_add_ai_question_metadata.sql
```
---
4. Start the application
With XAMPP, place the project under:
```text
C:\xampp\htdocs\quizly
```
Start:
```text
Apache
MySQL
```
Open:
```text
http://localhost/quizly/
```
---
🤖 Gemini Configuration
QUIZLY keeps Gemini integration inside the backend.
The main integration files are:
```text
services/GeminiClient.php
services/AIQuestionGenerator.php
api/ai/generate_questions.php
api/ai/regenerate_question.php
api/ai/save_questions.php
```
The browser communicates with QUIZLY.
QUIZLY communicates with Gemini.
The Gemini API key is never intended to be exposed to the browser.
---
🌍 InfinityFree Deployment
QUIZLY can be deployed to PHP/MySQL shared hosting.
A typical deployment looks like:
```text
InfinityFree
│
├── htdocs/
│   ├── index.php
│   ├── login.php
│   ├── dashboard/
│   ├── api/
│   ├── services/
│   ├── config/
│   └── ...
│
└── MySQL Database
```
Deployment steps
Create an InfinityFree hosting account.
Create a MySQL database.
Import `database/schema.sql`.
Apply the required migrations.
Upload the application to `htdocs`.
Create the production `.env`.
Add the database credentials.
Add the Gemini API key.
Configure the available Gemini model.
Open the live domain.
Test authentication.
Test quiz creation.
Test AI question generation.
Test a complete live quiz.
Current live deployment
```text
http://quizly.gt.tc/
```
---
🧪 Testing Checklist
Before considering a deployment ready, test the complete workflow.
Authentication
[ ] Register
[ ] Login
[ ] Logout
[ ] Role restrictions
[ ] Session handling
Quiz management
[ ] Create quiz
[ ] Edit quiz
[ ] Add questions
[ ] Publish quiz
[ ] Archive quiz
AI
[ ] Generate questions
[ ] Validate JSON
[ ] Preview questions
[ ] Edit questions
[ ] Regenerate a question
[ ] Detect duplicates
[ ] Save approved questions
Live session
[ ] Launch session
[ ] Generate room code
[ ] Join as participant
[ ] Start question
[ ] Submit answer
[ ] Calculate score
[ ] Display leaderboard
[ ] Complete session
Results
[ ] View results
[ ] View analytics
[ ] Export results
---
🔒 Production Checklist
Before using QUIZLY for a real organization:
[ ] Use HTTPS
[ ] Keep `.env` out of version control
[ ] Never expose Gemini API keys
[ ] Remove development/test endpoints from public production access
[ ] Disable public installation scripts if present
[ ] Disable PHP error display
[ ] Use strong database credentials
[ ] Verify CSRF protection
[ ] Verify role and organization authorization
[ ] Configure appropriate API rate limits
[ ] Back up the database
[ ] Test with multiple simultaneous participants
[ ] Review Gemini API limits and usage policies
[ ] Monitor application logs
[ ] Rotate credentials if they are ever exposed
---
📌 Example Use Cases
Schools
Teachers can create quick classroom quizzes, revision tests, and assessments.
Colleges & Universities
Departments can build subject-specific question banks and run live technical quizzes.
Corporate Training
Organizations can use QUIZLY for employee training assessments and knowledge checks.
Workshops & Events
Hosts can run interactive audience quizzes and competitions.
Self-Learning
Students can practice topics using AI-generated question sets and timed assessments.
---
🧑‍🏫 Example Teacher Workflow
A typical teacher session might look like this:
```text
Login
  ↓
Dashboard
  ↓
Question Bank
  ↓
Generate with AI
  ↓
Select "Operating Systems"
  ↓
Choose "Medium"
  ↓
Generate 10 Questions
  ↓
Review Questions
  ↓
Edit 1 Question
  ↓
Regenerate 1 Question
  ↓
Approve
  ↓
Save to Question Bank
  ↓
Create Quiz
  ↓
Add Questions
  ↓
Publish
  ↓
Launch Live Session
  ↓
Share Room Code
  ↓
Students Join
  ↓
Run Quiz
  ↓
View Leaderboard
  ↓
Review Results
```
---
👨‍🎓 Example Student Experience
Students do not need to manage the teacher-side workflow.
They can simply:
```text
Open QUIZLY
    ↓
Enter Room Code
    ↓
Enter Nickname
    ↓
Join
    ↓
Wait in Lobby
    ↓
Answer Question
    ↓
See Result
    ↓
Continue
    ↓
View Final Rank
```
The participant experience is designed to work well on mobile devices as well as desktop browsers.
---
🔌 API & Backend Modules
The application separates major backend responsibilities into API endpoints and reusable PHP services.
Authentication
```text
api/auth.php
```
Quiz management
```text
api/quizzes.php
```
Live session
```text
api/session.php
api/state.php
api/join.php
api/answer.php
```
Analytics
```text
api/analytics.php
```
AI
```text
api/ai/generate_questions.php
api/ai/regenerate_question.php
api/ai/save_questions.php
```
This separation makes the application easier to maintain and extend.
---
⚡ Performance Considerations
QUIZLY is intentionally designed to remain lightweight enough for standard PHP hosting.
The application uses:
Database indexes
Prepared SQL queries
Server-side scoring
Lightweight frontend JavaScript
Structured API responses
Compact AI prompts
Reusable question data
Organization-aware queries
The current live-session implementation uses browser polling for state synchronization. A future WebSocket implementation could further reduce polling overhead and improve scalability for larger live events.
---
🛣️ Roadmap
Potential future improvements include:
Real-Time Infrastructure
[ ] WebSocket-based live communication
[ ] Improved concurrent-session handling
[ ] Presence indicators
[ ] Better connection recovery
AI
[ ] AI question quality scoring
[ ] AI-generated explanations
[ ] AI difficulty estimation
[ ] AI question improvement suggestions
[ ] Image-based question generation
[ ] More question types
[ ] Question-generation templates
[ ] AI-assisted quiz creation
Assessment
[ ] Essay questions
[ ] Fill-in-the-blank questions
[ ] Matching questions
[ ] Image questions
[ ] Partial scoring
[ ] Negative marking
[ ] Question pools
Analytics
[ ] Advanced teacher dashboards
[ ] Student performance history
[ ] Topic mastery
[ ] Question difficulty analytics
[ ] Cohort comparison
[ ] Export improvements
Platform
[ ] Notifications
[ ] Scheduled quizzes
[ ] Public quiz sharing
[ ] Improved organization permissions
[ ] Progressive Web App support
[ ] Automated deployment
[ ] Automated backups
---
🤝 Contributing
Contributions and suggestions are welcome.
Create a feature branch:
```bash
git checkout -b feature/your-feature
```
Make your changes and test them.
Commit:
```bash
git add .
git commit -m "Add your feature"
```
Push:
```bash
git push origin feature/your-feature
```
Then open a pull request.
---
🐛 Reporting Issues
When reporting a bug, include:
What you were trying to do
Expected behavior
Actual behavior
Steps to reproduce
Browser and operating system
Error message
Screenshot, if useful
Never include:
API keys
Database passwords
`.env` contents
User passwords
Private user information
---
🔐 Security Disclosure
If you discover a security vulnerability, please avoid publishing sensitive exploit details in a public issue.
Report the issue privately to the project maintainer so it can be investigated and fixed responsibly.
---
📄 License
This project is licensed under the MIT License.
See the `LICENSE` file for details.
---
👨‍💻 Author
Deepak
QUIZLY is an independent software project focused on making digital assessments faster to create and more engaging to conduct.
Project
QUIZLY — AI-Powered Real-Time Quiz & Assessment Platform
GitHub
https://github.com/deepak-dev7/quizly
Live Application
http://quizly.gt.tc/
---
🔗 Resources
Live Demo: http://quizly.gt.tc/
GitHub: https://github.com/deepak-dev7/quizly
Google Gemini API: https://ai.google.dev/
PHP: https://www.php.net/
MySQL: https://www.mysql.com/
---
⭐ Support the Project
If QUIZLY is useful to you, consider giving the repository a ⭐ on GitHub.
It helps the project get noticed and encourages further development.
---
<p align="center">
<strong>QUIZLY</strong>
<br />
AI-assisted question creation • Real-time quizzes • Speed-based scoring • Better assessments



<em>Built to make assessments easier to create and more engaging to experience.</em>
</p>
