<?php
// QUIZLY AI Question Generator & Validator Service
// Enforces strict educational structure, prompt engineering, validation, and duplicate detection

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/GeminiClient.php';
require_once __DIR__ . '/OpenRouterClient.php';

class AIQuestionGenerator {
    /** @var GeminiClient|OpenRouterClient */
    private $client;
    private PDO $db;

    public function __construct($client = null, ?PDO $db = null) {
        $this->client = $client ?? new GeminiClient();
        $this->db = $db ?? Database::getConnection();
    }

    public function getClient() {
        return $this->client;
    }

    /**
     * Generate quiz questions from user requirements
     * 
     * @param array $params Configuration parameters
     * @param int $organizationId Organization ID for duplicate detection
     * @return array List of validated question objects with duplicate flags
     */
    public function generate(array $params, int $organizationId): array {
        // 1. Sanitize and validate inputs
        $topic = trim((string)($params['topic'] ?? ''));
        $chapter = trim((string)($params['chapter'] ?? ''));
        $questionCount = max(1, min(50, (int)($params['question_count'] ?? 10)));
        $questionType = strtolower(trim((string)($params['question_type'] ?? 'multiple_choice')));
        $difficulty = strtolower(trim((string)($params['difficulty'] ?? 'medium')));
        $educationLevel = trim((string)($params['education_level'] ?? 'Undergraduate'));
        $language = trim((string)($params['language'] ?? 'English'));

        $timerSeconds = max(5, min(120, (int)($params['timer_seconds'] ?? 20)));
        $maxPoints = max(100, min(5000, (int)($params['max_points'] ?? 1000)));
        $includeExplanation = !empty($params['include_explanation']);
        $includeTopic = !empty($params['include_topic']);
        $includeLearningObjective = !empty($params['include_learning_objective']);
        $additionalInstructions = trim((string)($params['additional_instructions'] ?? ''));

        if (empty($topic)) {
            throw new InvalidArgumentException('Subject / Topic is required.');
        }

        // 2. Build prompt
        $promptConfig = [
            'topic' => $topic,
            'chapter' => $chapter,
            'count' => $questionCount,
            'type' => $questionType,
            'difficulty' => $difficulty,
            'education_level' => $educationLevel,
            'language' => $language,
            'include_explanation' => $includeExplanation,
            'include_topic' => $includeTopic,
            'include_learning_objective' => $includeLearningObjective,
            'additional_instructions' => $additionalInstructions
        ];

        // 3. Request completion from Gemini (or fallback client)
        if ($this->client instanceof GeminiClient) {
            $systemInstruction = $this->buildCompactGeminiSystemInstruction($promptConfig);
            $userPrompt = $this->buildCompactGeminiUserPrompt($promptConfig);
            $response = $this->client->generateContent($userPrompt, $systemInstruction, [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2
            ]);
            $rawContent = $this->client->extractContent($response);
        } else {
            $messages = $this->buildPromptMessages($promptConfig);
            $response = $this->client->chatCompletion($messages, [
                'temperature' => 0.25,
                'max_tokens' => min(8000, 1000 + ($questionCount * 250))
            ]);
            $rawContent = $this->client->extractContent($response);
        }

        // 4. Parse & rigorously validate JSON output
        $parsedQuestions = $this->parseAndValidateQuestionsJson($rawContent, $questionCount, [
            'timer_seconds' => $timerSeconds,
            'max_points' => $maxPoints,
            'default_topic' => !empty($chapter) ? "$topic - $chapter" : $topic,
            'default_difficulty' => in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium'
        ]);

        // 5. Detect duplicates against organization's question bank
        return $this->detectDuplicates($parsedQuestions, $organizationId);
    }

    /**
     * Regenerate a single question given original context and specific feedback
     */
    public function regenerateQuestion(array $context, array $questionToReplace, ?string $instructions, int $organizationId): array {
        $topic = trim((string)($context['topic'] ?? ''));
        $chapter = trim((string)($context['chapter'] ?? ''));
        $difficulty = strtolower(trim((string)($questionToReplace['difficulty'] ?? $context['difficulty'] ?? 'medium')));
        $questionType = strtolower(trim((string)($questionToReplace['question_type'] ?? 'multiple_choice')));
        $educationLevel = trim((string)($context['education_level'] ?? 'Undergraduate'));
        $language = trim((string)($context['language'] ?? 'English'));

        if ($this->client instanceof GeminiClient) {
            $typeRule = "For multiple_choice: exactly 4 distinct options with exactly one is_correct: true.";
            if ($questionType === 'true_false') {
                $typeRule = "For true_false: exactly 2 options ('True' and 'False') with exactly one is_correct: true.";
            }

            $systemPrompt = "You are an expert assessment creator for the QUIZLY live quiz platform.\n" .
                "Generate EXACTLY ONE quiz question in valid JSON matching this schema:\n" .
                "{\"questions\": [{\"question_text\": \"...\", \"question_type\": \"{$questionType}\", \"difficulty\": \"{$difficulty}\", \"topic\": \"{$topic}\", \"options\": [{\"text\": \"...\", \"is_correct\": true}, {\"text\": \"...\", \"is_correct\": false}], \"explanation\": \"...\", \"learning_objective\": \"...\"}]}\n" .
                "Rules:\n- Return strictly valid JSON, no markdown fences.\n- {$typeRule}\n- Accurate, distinct distractors without duplicate option text.";

            $userPrompt = "Generate ONE high-quality replacement assessment question:\n" .
                "- Topic: {$topic}\n" .
                (!empty($chapter) ? "- Concept: {$chapter}\n" : "") .
                "- Difficulty: {$difficulty}\n" .
                "- Target Audience: {$educationLevel}\n" .
                "- Language: {$language}\n" .
                "- Previous question to replace: \"{$questionToReplace['question_text']}\"\n" .
                (!empty($instructions) ? "- Specific Teacher Feedback/Instructions: {$instructions}\n" : "- Generate a completely fresh and distinct question on this concept.\n") .
                "Return JSON object containing the \"questions\" array with 1 question.";

            $response = $this->client->generateContent($userPrompt, $systemPrompt, [
                'responseMimeType' => 'application/json',
                'temperature' => 0.3
            ]);
            $rawContent = $this->client->extractContent($response);
        } else {
            $systemPrompt = "You are an expert educational assessment creator for the QUIZLY live quiz platform.\n" .
                "Generate EXACTLY ONE high-quality, scientifically accurate quiz question.\n" .
                "Requirements:\n" .
                "- Return ONLY a valid JSON object matching this schema: {\"questions\": [ { ... } ]}\n" .
                "- No markdown formatting or extra commentary.\n" .
                "- Accurate wording, plausible distractors, no duplicate options.\n" .
                "- For multiple_choice: exactly 4 distinct options, exactly 1 correct answer.\n" .
                "- For true_false: exactly 2 options (True and False), exactly 1 correct answer.\n";

            $userPrompt = "Subject/Topic: {$topic}\n" .
                (!empty($chapter) ? "Chapter/Concept: {$chapter}\n" : "") .
                "Difficulty: {$difficulty}\n" .
                "Question Type: {$questionType}\n" .
                "Education Level: {$educationLevel}\n" .
                "Language: {$language}\n" .
                "Previous question to replace: \"{$questionToReplace['question_text']}\"\n" .
                (!empty($instructions) ? "Regeneration Specific Instructions: {$instructions}\n" : "Generate a completely fresh and distinct question on this concept.\n") .
                "Return JSON object: {\"questions\": [ { ... } ]}";

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ];

            $response = $this->client->chatCompletion($messages, [
                'temperature' => 0.4,
                'max_tokens' => 1500
            ]);

            $rawContent = $this->client->extractContent($response);
        }

        $parsed = $this->parseAndValidateQuestionsJson($rawContent, 1, [
            'timer_seconds' => (int)($questionToReplace['timer_seconds'] ?? 20),
            'max_points' => (int)($questionToReplace['max_points'] ?? 1000),
            'default_topic' => $questionToReplace['topic'] ?? $topic,
            'default_difficulty' => in_array($difficulty, ['easy', 'medium', 'hard'], true) ? $difficulty : 'medium'
        ]);

        if (empty($parsed)) {
            throw new Exception('Failed to generate a valid replacement question.');
        }

        $singleWithDupCheck = $this->detectDuplicates([$parsed[0]], $organizationId);
        return $singleWithDupCheck[0];
    }

    /**
     * Persist approved questions to the database within a transaction
     */
    public function saveApprovedQuestions(array $questions, int $organizationId, int $userId, ?int $targetQuizId = null, ?string $newQuizTitle = null): array {
        if (empty($questions)) {
            throw new InvalidArgumentException('No questions selected to save.');
        }

        $this->db->beginTransaction();

        try {
            $quizId = 0;

            if ($targetQuizId !== null && $targetQuizId > 0) {
                // Verify target quiz belongs to this organization
                $stmtQz = $this->db->prepare("SELECT id FROM quizzes WHERE id = :id AND organization_id = :org_id");
                $stmtQz->execute(['id' => $targetQuizId, 'org_id' => $organizationId]);
                if (!$stmtQz->fetchColumn()) {
                    throw new Exception('Target quiz not found or unauthorized.');
                }
                $quizId = $targetQuizId;
            } elseif (!empty($newQuizTitle)) {
                // Create a new quiz with the specified title
                $stmtNewQz = $this->db->prepare("
                    INSERT INTO quizzes (organization_id, creator_id, title, description, category, difficulty, status)
                    VALUES (:org_id, :creator_id, :title, :desc, :category, :difficulty, 'DRAFT')
                ");
                $firstDiff = strtoupper($questions[0]['difficulty'] ?? 'MEDIUM');
                $stmtNewQz->execute([
                    'org_id' => $organizationId,
                    'creator_id' => $userId,
                    'title' => trim($newQuizTitle),
                    'desc' => 'Generated with QUIZLY AI Question Generator',
                    'category' => $questions[0]['topic'] ?? 'General',
                    'difficulty' => in_array($firstDiff, ['EASY', 'MEDIUM', 'HARD'], true) ? $firstDiff : 'MEDIUM'
                ]);
                $quizId = (int)$this->db->lastInsertId();
            } else {
                // Find or create default Question Bank Repository quiz for this organization
                $stmtDefault = $this->db->prepare("
                    SELECT id FROM quizzes 
                    WHERE organization_id = :org_id AND title = 'Question Bank Repository'
                    LIMIT 1
                ");
                $stmtDefault->execute(['org_id' => $organizationId]);
                $existingId = $stmtDefault->fetchColumn();

                if ($existingId) {
                    $quizId = (int)$existingId;
                } else {
                    $stmtCreateRepo = $this->db->prepare("
                        INSERT INTO quizzes (organization_id, creator_id, title, description, category, difficulty, status)
                        VALUES (:org_id, :creator_id, 'Question Bank Repository', 'Central pool of approved organization quiz questions', 'Question Bank', 'MEDIUM', 'DRAFT')
                    ");
                    $stmtCreateRepo->execute([
                        'org_id' => $organizationId,
                        'creator_id' => $userId
                    ]);
                    $quizId = (int)$this->db->lastInsertId();
                }
            }

            // Determine starting order_num
            $stmtMaxOrder = $this->db->prepare("SELECT COALESCE(MAX(order_num), 0) FROM questions WHERE quiz_id = :quiz_id");
            $stmtMaxOrder->execute(['quiz_id' => $quizId]);
            $currentOrder = (int)$stmtMaxOrder->fetchColumn();

            $generationSource = ($this->client instanceof GeminiClient) ? 'gemini' : 'openrouter';

            $stmtInsertQ = $this->db->prepare("
                INSERT INTO questions (
                    quiz_id, question_text, timer_seconds, max_points, order_num,
                    question_type, difficulty, topic, explanation, learning_objective,
                    ai_generated, generation_source, ai_model, generation_timestamp
                ) VALUES (
                    :quiz_id, :question_text, :timer_seconds, :max_points, :order_num,
                    :question_type, :difficulty, :topic, :explanation, :learning_objective,
                    1, :generation_source, :ai_model, :generation_timestamp
                )
            ");

            $stmtInsertOpt = $this->db->prepare("
                INSERT INTO question_options (question_id, option_key, option_text, is_correct)
                VALUES (:question_id, :option_key, :option_text, :is_correct)
            ");

            $savedCount = 0;
            $nowStr = date('Y-m-d H:i:s');
            $modelName = $this->client->getModel();

            foreach ($questions as $q) {
                $qText = trim((string)($q['question_text'] ?? ''));
                if ($qText === '') {
                    continue;
                }

                $timerSeconds = max(5, min(120, (int)($q['timer_seconds'] ?? 20)));
                $maxPoints = max(100, min(5000, (int)($q['max_points'] ?? 1000)));
                $currentOrder++;

                $stmtInsertQ->execute([
                    'quiz_id' => $quizId,
                    'question_text' => $qText,
                    'timer_seconds' => $timerSeconds,
                    'max_points' => $maxPoints,
                    'order_num' => $currentOrder,
                    'question_type' => $q['question_type'] ?? 'multiple_choice',
                    'difficulty' => strtolower($q['difficulty'] ?? 'medium'),
                    'topic' => $q['topic'] ?? null,
                    'explanation' => $q['explanation'] ?? null,
                    'learning_objective' => $q['learning_objective'] ?? null,
                    'generation_source' => $generationSource,
                    'ai_model' => $modelName,
                    'generation_timestamp' => $nowStr
                ]);

                $newQuestionId = (int)$this->db->lastInsertId();

                $options = $q['options'] ?? [];
                $optKeys = ['A', 'B', 'C', 'D', 'E', 'F'];

                foreach ($options as $idx => $opt) {
                    $key = $opt['key'] ?? ($optKeys[$idx] ?? 'A');
                    $text = trim((string)($opt['text'] ?? ''));
                    if ($text === '') {
                        continue;
                    }
                    $isCorrect = !empty($opt['is_correct']) ? 1 : 0;

                    $stmtInsertOpt->execute([
                        'question_id' => $newQuestionId,
                        'option_key' => $key,
                        'option_text' => $text,
                        'is_correct' => $isCorrect
                    ]);
                }

                $savedCount++;
            }

            // Update quiz timestamp
            $stmtTouch = $this->db->prepare("UPDATE quizzes SET updated_at = :now WHERE id = :id");
            $stmtTouch->execute(['now' => $nowStr, 'id' => $quizId]);

            $this->db->commit();

            return [
                'saved_count' => $savedCount,
                'quiz_id' => $quizId
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Compact, structured system instruction for Google Gemini
     */
    private function buildCompactGeminiSystemInstruction(array $config): string {
        $typeRule = "For multiple_choice: exactly 4 distinct options with exactly one correct (is_correct: true).";
        if ($config['type'] === 'true_false') {
            $typeRule = "For true_false: exactly 2 options ('True' and 'False') with exactly one correct (is_correct: true).";
        } elseif ($config['type'] === 'multiple_select') {
            $typeRule = "For multiple_select: exactly 4 options with one or more correct answers.";
        }

        return "You are an expert assessment specialist for the QUIZLY live quiz platform.\n" .
            "Generate quiz questions strictly conforming to this JSON schema:\n" .
            "{\n" .
            "  \"questions\": [\n" .
            "    {\n" .
            "      \"question_text\": \"string\",\n" .
            "      \"question_type\": \"{$config['type']}\",\n" .
            "      \"difficulty\": \"easy\" | \"medium\" | \"hard\",\n" .
            "      \"topic\": \"string\",\n" .
            "      \"options\": [\n" .
            "        {\"text\": \"string\", \"is_correct\": false},\n" .
            "        {\"text\": \"string\", \"is_correct\": true},\n" .
            "        {\"text\": \"string\", \"is_correct\": false},\n" .
            "        {\"text\": \"string\", \"is_correct\": false}\n" .
            "      ],\n" .
            "      \"explanation\": \"string\",\n" .
            "      \"learning_objective\": \"string\"\n" .
            "    }\n" .
            "  ]\n" .
            "}\n" .
            "Mandatory Rules:\n" .
            "1. Output ONLY valid JSON matching the schema above. No markdown code blocks, no backticks, no conversational text.\n" .
            "2. {$typeRule}\n" .
            "3. High factual accuracy, unambiguous wording, plausible distractors, no duplicate option text.\n" .
            "4. RANDOMIZE the placement of the correct answer across options. Do NOT always place the correct answer as the first option.\n" .
            "5. Language: {$config['language']}. Target Level: {$config['education_level']}.\n" .
            "6. Set the \"topic\" field of ALL questions strictly to \"{$config['topic']}\". Do NOT invent separate subtopic names for individual questions.";
    }

    /**
     * Compact user prompt for Google Gemini
     */
    private function buildCompactGeminiUserPrompt(array $config): string {
        $lines = [
            "Generate exactly {$config['count']} questions for:",
            "- Subject / Topic: {$config['topic']}",
            "- Set the \"topic\" property of every question strictly to: \"{$config['topic']}\"",
        ];
        if (!empty($config['chapter'])) {
            $lines[] = "- Chapter / Concept: {$config['chapter']}";
        }
        $lines[] = "- Question Type: {$config['type']}";
        $lines[] = "- Difficulty: {$config['difficulty']}";
        $lines[] = "- Education Level: {$config['education_level']}";
        $lines[] = "- Language: {$config['language']}";
        if (!empty($config['include_explanation'])) {
            $lines[] = "- Include a concise educational explanation for the correct answer.";
        }
        if (!empty($config['include_learning_objective'])) {
            $lines[] = "- Include a concise learning objective for each question.";
        }
        if (!empty($config['additional_instructions'])) {
            $lines[] = "- Additional Instructions: {$config['additional_instructions']}";
        }
        $lines[] = "Return strictly the JSON object containing the questions array.";

        return implode("\n", $lines);
    }

    /**
     * Build strict, structured educational prompt
     */
    private function buildPromptMessages(array $config): array {
        $typeInstruction = "";
        switch ($config['type']) {
            case 'true_false':
                $typeInstruction = "All questions must be True/False questions with exactly 2 options: 'True' and 'False', and exactly one correct answer.";
                break;
            case 'multiple_select':
                $typeInstruction = "All questions must be Multiple Select questions where one or more options (out of 4 options) are correct.";
                break;
            case 'mixed':
                $typeInstruction = "Provide a healthy mix of standard 4-option Multiple Choice questions and True/False questions.";
                break;
            case 'multiple_choice':
            default:
                $typeInstruction = "All questions must be standard Multiple Choice questions with exactly 4 options and exactly one correct answer.";
                break;
        }

        $difficultyInstruction = "";
        if ($config['difficulty'] === 'mixed') {
            $difficultyInstruction = "Provide a balanced mix of 'easy', 'medium', and 'hard' questions.";
        } else {
            $difficultyInstruction = "Every question must have difficulty level: '{$config['difficulty']}'.";
        }

        $systemPrompt = "You are an elite university professor, textbook author, and certified assessment specialist.\n" .
            "Your objective is to generate accurate, high-quality, engaging quiz questions for the QUIZLY live quiz platform.\n\n" .
            "CRITICAL RULES:\n" .
            "1. Output ONLY a valid, parseable JSON object matching the requested schema.\n" .
            "2. Absolutely NO explanatory preamble, markdown fences (no ```json), or trailing conversational text.\n" .
            "3. Ensure high pedagogical value, factual accuracy, clear and unambiguous question text.\n" .
            "4. Distractors must be plausible, distinct, and completely free of duplicate text or obvious giveaway cues.\n" .
            "5. {$typeInstruction}\n" .
            "6. {$difficultyInstruction}\n" .
            "7. Language: All questions and options must be in {$config['language']}.\n" .
            "8. Education Target Level: Suitable for {$config['education_level']} students.\n\n" .
            "JSON SCHEMA:\n" .
            "{\n" .
            "  \"questions\": [\n" .
            "    {\n" .
            "      \"question_text\": \"...\",\n" .
            "      \"question_type\": \"multiple_choice\" | \"true_false\" | \"multiple_select\",\n" .
            "      \"difficulty\": \"easy\" | \"medium\" | \"hard\",\n" .
            "      \"topic\": \"...\",\n" .
            "      \"options\": [\n" .
            "        {\"text\": \"...\", \"is_correct\": true},\n" .
            "        {\"text\": \"...\", \"is_correct\": false}\n" .
            "      ],\n" .
            "      \"explanation\": \"...\",\n" .
            "      \"learning_objective\": \"...\"\n" .
            "    }\n" .
            "  ]\n" .
            "}";

        $userPrompt = "Please generate exactly {$config['count']} quiz questions for:\n" .
            "- Subject / Topic: {$config['topic']}\n" .
            (!empty($config['chapter']) ? "- Chapter / Concept: {$config['chapter']}\n" : "") .
            "- Number of Questions: {$config['count']}\n" .
            "- Question Type: {$config['type']}\n" .
            "- Difficulty: {$config['difficulty']}\n" .
            "- Target Level: {$config['education_level']}\n" .
            "- Language: {$config['language']}\n" .
            ($config['include_explanation'] ? "- Include comprehensive explanations for the correct answers.\n" : "") .
            ($config['include_learning_objective'] ? "- Include a concise learning objective for each question.\n" : "") .
            (!empty($config['additional_instructions']) ? "- Additional Instructions: {$config['additional_instructions']}\n" : "") .
            "\nRemember: Return strictly valid JSON with no markdown wrapping.";

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];
    }

    /**
     * Parse and sanitize raw JSON output from OpenRouter
     */
    public function parseAndValidateQuestionsJson(string $rawContent, int $requestedCount, array $defaults = []): array {
        // Strip markdown code fences if model returned ```json ... ```
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($rawContent));
        $clean = preg_replace('/\s*```$/', '', $clean);
        $clean = trim($clean);

        // Find outer JSON braces if extra text surrounds it
        $firstBrace = strpos($clean, '{');
        $lastBrace = strrpos($clean, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $clean = substr($clean, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        $decoded = json_decode($clean, true);
        if (!is_array($decoded) || !isset($decoded['questions']) || !is_array($decoded['questions'])) {
            // Attempt to check if decoded is a direct array of questions
            if (is_array($decoded) && isset($decoded[0]['question_text'])) {
                $rawQuestions = $decoded;
            } else {
                error_log("Failed to decode AI response JSON. Raw snippet: " . substr($rawContent, 0, 300));
                throw new Exception('The AI returned an invalid response. Please regenerate.');
            }
        } else {
            $rawQuestions = $decoded['questions'];
        }

        $validQuestions = [];
        $optKeys = ['A', 'B', 'C', 'D', 'E', 'F'];

        foreach ($rawQuestions as $index => $rawQ) {
            if (!is_array($rawQ)) continue;

            $qText = trim(strip_tags((string)($rawQ['question_text'] ?? '')));
            if (strlen($qText) < 5 || strlen($qText) > 1000) {
                continue; // Skip malformed or unreasonable strings
            }

            // Determine question type
            $qType = strtolower(trim((string)($rawQ['question_type'] ?? 'multiple_choice')));
            if (!in_array($qType, ['multiple_choice', 'true_false', 'multiple_select'], true)) {
                $qType = 'multiple_choice';
            }

            // Validate and normalize options
            $rawOptions = $rawQ['options'] ?? [];
            if (!is_array($rawOptions) || count($rawOptions) < 2 || count($rawOptions) > 6) {
                continue; // Must have between 2 and 6 options
            }

            $sanitizedOptions = [];
            $seenOptionTexts = [];
            $correctCount = 0;

            foreach ($rawOptions as $optIdx => $opt) {
                if (is_string($opt)) {
                    $optText = trim(strip_tags($opt));
                    $isCorrect = ($optIdx === 0);
                } elseif (is_array($opt)) {
                    $optText = trim(strip_tags((string)($opt['text'] ?? $opt['option_text'] ?? '')));
                    $isCorrect = !empty($opt['is_correct']);
                } else {
                    continue;
                }

                if ($optText === '') continue;

                // Prevent duplicate options within the same question
                $lowerOpt = strtolower($optText);
                if (in_array($lowerOpt, $seenOptionTexts, true)) {
                    continue;
                }
                $seenOptionTexts[] = $lowerOpt;

                $key = $optKeys[count($sanitizedOptions)] ?? 'A';
                if ($isCorrect) {
                    $correctCount++;
                }

                $sanitizedOptions[] = [
                    'key' => $key,
                    'text' => $optText,
                    'is_correct' => $isCorrect
                ];
            }

            // Validate correct answer counts according to question type
            if ($qType === 'multiple_choice') {
                if ($correctCount !== 1) {
                    // Fallback: If no correct option was set, mark first as correct
                    if ($correctCount === 0 && count($sanitizedOptions) > 0) {
                        $sanitizedOptions[0]['is_correct'] = true;
                        $correctCount = 1;
                    } elseif ($correctCount > 1) {
                        // More than 1 correct for single choice MCQ: keep only first correct
                        $foundFirst = false;
                        foreach ($sanitizedOptions as &$so) {
                            if ($so['is_correct']) {
                                if (!$foundFirst) {
                                    $foundFirst = true;
                                } else {
                                    $so['is_correct'] = false;
                                }
                            }
                        }
                    }
                }
            } elseif ($qType === 'true_false') {
                if (count($sanitizedOptions) !== 2) {
                    // Rebuild true/false options cleanly
                    $isTrueCorrect = false;
                    foreach ($sanitizedOptions as $so) {
                        if (stripos($so['text'], 'true') !== false && $so['is_correct']) {
                            $isTrueCorrect = true;
                            break;
                        }
                    }
                    $sanitizedOptions = [
                        ['key' => 'A', 'text' => 'True', 'is_correct' => $isTrueCorrect],
                        ['key' => 'B', 'text' => 'False', 'is_correct' => !$isTrueCorrect]
                    ];
                }
            } elseif ($qType === 'multiple_select') {
                if ($correctCount < 1 && count($sanitizedOptions) > 0) {
                    $sanitizedOptions[0]['is_correct'] = true;
                }
            }

            if (count($sanitizedOptions) < 2) {
                continue;
            }

            // Shuffle options for multiple choice so the correct answer is evenly distributed across A, B, C, D
            if ($qType === 'multiple_choice') {
                shuffle($sanitizedOptions);
                foreach ($sanitizedOptions as $optIdx => &$so) {
                    $so['key'] = $optKeys[$optIdx] ?? 'A';
                }
                unset($so);
            }

            // Validate difficulty
            $diff = strtolower(trim((string)($rawQ['difficulty'] ?? $defaults['default_difficulty'] ?? 'medium')));
            if (!in_array($diff, ['easy', 'medium', 'hard'], true)) {
                $diff = 'medium';
            }

            $requestedTopic = trim((string)($defaults['default_topic'] ?? ''));
            $topic = !empty($requestedTopic) ? $requestedTopic : trim(strip_tags((string)($rawQ['topic'] ?? 'General')));
            $explanation = trim(strip_tags((string)($rawQ['explanation'] ?? '')));
            $learningObjective = trim(strip_tags((string)($rawQ['learning_objective'] ?? '')));

            $validQuestions[] = [
                'id' => 'gen_' . ($index + 1) . '_' . substr(md5(uniqid('', true)), 0, 6),
                'question_text' => $qText,
                'question_type' => $qType,
                'difficulty' => $diff,
                'topic' => $topic,
                'explanation' => $explanation,
                'learning_objective' => $learningObjective,
                'timer_seconds' => (int)($defaults['timer_seconds'] ?? 20),
                'max_points' => (int)($defaults['max_points'] ?? 1000),
                'options' => $sanitizedOptions,
                'is_duplicate' => false,
                'duplicate_warning' => null
            ];
        }

        if (empty($validQuestions)) {
            throw new Exception('The AI returned an invalid response. Please regenerate.');
        }

        return $validQuestions;
    }

    /**
     * Compare generated questions against existing questions for this organization
     */
    public function detectDuplicates(array $questions, int $organizationId): array {
        try {
            $stmt = $this->db->prepare("
                SELECT q.id, q.question_text 
                FROM questions q
                JOIN quizzes qz ON q.quiz_id = qz.id
                WHERE qz.organization_id = :org_id
            ");
            $stmt->execute(['org_id' => $organizationId]);
            $existingQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($existingQuestions)) {
                return $questions;
            }

            // Build normalized lookup array
            $existingNormalized = [];
            foreach ($existingQuestions as $eq) {
                $norm = $this->normalizePrompt($eq['question_text']);
                if ($norm !== '') {
                    $existingNormalized[] = [
                        'id' => (int)$eq['id'],
                        'text' => $eq['question_text'],
                        'norm' => $norm
                    ];
                }
            }

            foreach ($questions as &$q) {
                if (!isset($q['is_duplicate'])) {
                    $q['is_duplicate'] = false;
                }
                if (!array_key_exists('duplicate_warning', $q)) {
                    $q['duplicate_warning'] = null;
                }

                $qNorm = $this->normalizePrompt($q['question_text'] ?? '');
                if ($qNorm === '') continue;

                foreach ($existingNormalized as $ex) {
                    // Check exact normalized match
                    if ($qNorm === $ex['norm']) {
                        $q['is_duplicate'] = true;
                        $q['duplicate_warning'] = 'Exact duplicate of existing question in bank: "' . $this->truncate($ex['text'], 55) . '"';
                        $q['similar_question_id'] = $ex['id'];
                        break;
                    }

                    // Check high string similarity
                    similar_text($qNorm, $ex['norm'], $percent);
                    if ($percent >= 82.0) {
                        $q['is_duplicate'] = true;
                        $q['duplicate_warning'] = 'Possible duplicate (' . round($percent) . '% similar): "' . $this->truncate($ex['text'], 55) . '"';
                        $q['similar_question_id'] = $ex['id'];
                        break;
                    }
                }
            }
            unset($q);

            return $questions;
        } catch (Throwable $e) {
            error_log("Duplicate detection warning: " . $e->getMessage());
            return $questions; // Return questions gracefully if lookup fails
        }
    }

    private function normalizePrompt(string $text): string {
        $lowered = strtolower(trim($text));
        // Strip punctuation and special characters
        $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $lowered);
        return preg_replace('/\s+/', ' ', trim($clean));
    }

    private function truncate(string $str, int $len = 50): string {
        if (mb_strlen($str) <= $len) {
            return $str;
        }
        return mb_substr($str, 0, $len) . '...';
    }
}
