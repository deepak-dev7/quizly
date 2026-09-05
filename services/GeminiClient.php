<?php
// QUIZLY Google Gemini AI Client Service
// Securely communicates with Google Gemini API (gemini-2.5-flash-lite / gemini-3.5-flash-lite)
// Never exposes API keys to client-side code or logs

require_once __DIR__ . '/../config/config.php';

class GeminiClient {
    private string $apiKey;
    private string $model;
    private int $timeoutSeconds = 45;
    private int $connectTimeout = 10;
    private string $baseEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models';

    // Fallback models if configured model is unavailable or deprecated by Google
    private array $fallbackModels = [
        'gemini-3.5-flash-lite',
        'gemini-flash-lite-latest',
        'gemini-flash-latest'
    ];

    public function __construct(?string $apiKey = null, ?string $model = null) {
        $this->apiKey = $apiKey ?? (defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '' ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: ''));
        $this->model = $model ?? (defined('GEMINI_MODEL') && GEMINI_MODEL !== '' ? GEMINI_MODEL : (getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash-lite'));
    }

    public function isConfigured(): bool {
        return !empty($this->apiKey);
    }

    public function getModel(): string {
        return $this->model;
    }

    public function setModel(string $model): void {
        $this->model = trim($model);
    }

    public function setTimeout(int $seconds): void {
        $this->timeoutSeconds = max(5, min(120, $seconds));
    }

    /**
     * Generate structured content using Google Gemini generateContent endpoint
     *
     * @param string $prompt User prompt text
     * @param string|null $systemInstruction Optional system instruction
     * @param array $extraConfig Optional generation config overrides
     * @return array Decoded Gemini response payload
     * @throws Exception User-safe sanitized exception on failure
     */
    public function generateContent(string $prompt, ?string $systemInstruction = null, array $extraConfig = []): array {
        if (!$this->isConfigured()) {
            throw new Exception('Gemini AI generation is not configured. Please set GEMINI_API_KEY in the environment.');
        }

        // Try configured model first, followed by fallbacks if model is deprecated (404)
        $modelsToTry = array_unique(array_merge([$this->model], $this->fallbackModels));
        $lastException = null;

        foreach ($modelsToTry as $currentModel) {
            try {
                return $this->executeRequest($currentModel, $prompt, $systemInstruction, $extraConfig);
            } catch (Exception $e) {
                // If the error indicates model not found/deprecated (404), try next fallback model
                if (str_contains($e->getMessage(), 'MODEL_NOT_FOUND_404')) {
                    error_log("Gemini model '{$currentModel}' unavailable/deprecated. Attempting next fallback model...");
                    $lastException = $e;
                    continue;
                }
                // For other errors (auth, rate limits, timeouts), throw immediately
                throw $e;
            }
        }

        throw $lastException ?? new Exception('Unable to generate content with Gemini. Please try again.');
    }

    /**
     * Execute HTTP POST to Gemini API
     */
    private function executeRequest(string $model, string $prompt, ?string $systemInstruction, array $extraConfig): array {
        $url = "{$this->baseEndpoint}/{$model}:generateContent";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => array_merge([
                'responseMimeType' => 'application/json',
                'temperature' => 0.2,
                'maxOutputTokens' => 4096
            ], $extraConfig)
        ];

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception('Failed to initialize HTTP client for Gemini AI service.');
        }

        $jsonPayload = json_encode($payload);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => false // Supports local developer/XAMPP environments
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        // 1. Network & Timeout Error Handling
        if ($curlErrno !== 0) {
            if ($curlErrno === CURLE_OPERATION_TIMEDOUT || $curlErrno === CURLE_OPERATION_TIMEOUTED) {
                error_log("Gemini request timed out after {$this->timeoutSeconds}s");
                throw new Exception('The AI service took too long to respond. Please try again.');
            }
            $safeCurlErr = preg_replace('/[a-zA-Z0-9_\-\.]{30,}/', '***', $curlError);
            error_log("Gemini cURL error ({$curlErrno}): {$safeCurlErr}");
            throw new Exception('AI generation failed due to network connectivity. Please try again.');
        }

        // 2. HTTP 404 Model Not Found / Deprecated
        if ($httpCode === 404) {
            throw new Exception("MODEL_NOT_FOUND_404: {$model}");
        }

        // 3. HTTP 401 / 403 or 400 (Invalid API Key) Authentication Error Handling
        if ($httpCode === 401 || $httpCode === 403 || ($httpCode === 400 && str_contains((string)$response, 'API key not valid'))) {
            error_log("Gemini authentication error HTTP {$httpCode}");
            throw new Exception('Gemini AI service authentication failed. Please verify the platform API key.');
        }

        // 4. HTTP 429 Rate Limiting
        if ($httpCode === 429) {
            error_log("Gemini rate limit reached HTTP 429");
            throw new Exception('Gemini API rate limit reached. Please wait a few moments and try again.');
        }

        // 5. HTTP 5xx Server / High Demand Error Handling
        if ($httpCode >= 500) {
            $sanitizedBody = substr($this->sanitizeLogOutput((string)$response), 0, 200);
            error_log("Gemini upstream service error HTTP {$httpCode}: {$sanitizedBody}");
            throw new Exception('The Gemini AI service is temporarily experiencing high demand. Please try again.');
        }

        // 6. Non-200 Status Code Handling
        if ($httpCode !== 200) {
            $sanitizedBody = substr($this->sanitizeLogOutput((string)$response), 0, 200);
            error_log("Gemini non-200 response ({$httpCode}): {$sanitizedBody}");
            throw new Exception('AI generation failed. Please try again.');
        }

        // 7. Validate Decoded Payload
        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            error_log("Gemini response was not valid JSON");
            throw new Exception('The AI returned an invalid response. Please regenerate.');
        }

        // Remember successfully used model if it succeeded through fallback
        if ($this->model !== $model) {
            $this->model = $model;
        }

        return $decoded;
    }

    /**
     * Extract generated text content from Gemini response candidates
     */
    public function extractContent(array $response): string {
        $candidate = $response['candidates'][0] ?? null;
        if (!$candidate) {
            $blockReason = $response['promptFeedback']['blockReason'] ?? 'content filtering';
            error_log("Gemini generation blocked: {$blockReason}");
            throw new Exception("The question generation was blocked by safety filters ({$blockReason}). Please adjust the topic.");
        }

        $parts = $candidate['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new Exception('The AI returned an empty response. Please regenerate.');
        }

        return $text;
    }

    /**
     * Mask any API key or sensitive data before logging
     */
    private function sanitizeLogOutput(string $raw): string {
        if (!empty($this->apiKey)) {
            $raw = str_replace($this->apiKey, '[REDACTED_API_KEY]', $raw);
        }
        return preg_replace('/[a-zA-Z0-9_\-\.]{30,}/', '[REDACTED_TOKEN]', $raw);
    }
}
