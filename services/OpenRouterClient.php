<?php
// QUIZLY OpenRouter AI Client Service
// Securely communicates with OpenRouter's OpenAI-compatible completions API

require_once __DIR__ . '/../config/config.php';

class OpenRouterClient {
    private string $apiKey;
    private string $model;
    private string $apiEndpoint = 'https://openrouter.ai/api/v1/chat/completions';
    private int $timeoutSeconds = 60;

    public function __construct(?string $apiKey = null, ?string $model = null) {
        $this->apiKey = $apiKey ?? (defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : (getenv('OPENROUTER_API_KEY') ?: ''));
        $this->model = $model ?? (defined('OPENROUTER_MODEL') && OPENROUTER_MODEL !== '' ? OPENROUTER_MODEL : (getenv('OPENROUTER_MODEL') ?: 'openrouter/free'));
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
        $this->timeoutSeconds = max(5, min(180, $seconds));
    }

    /**
     * Send chat completion request to OpenRouter
     * 
     * @param array $messages Array of ['role' => 'user'|'system', 'content' => string]
     * @param array $extraParams Optional params (temperature, max_tokens, response_format, etc.)
     * @return array Decoded response payload from OpenRouter
     * @throws Exception User-safe sanitized exception on failure
     */
    public function chatCompletion(array $messages, array $extraParams = []): array {
        if (!$this->isConfigured()) {
            throw new Exception('AI generation is not configured. Please contact the platform administrator.');
        }

        $payload = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.3,
            'max_tokens' => 4000
        ], $extraParams);

        $ch = curl_init($this->apiEndpoint);
        if ($ch === false) {
            throw new Exception('Failed to initialize HTTP client for AI service.');
        }

        $referer = (defined('BASE_URL') && BASE_URL !== '') ? BASE_URL : 'http://localhost/quiz';
        $jsonPayload = json_encode($payload);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . $referer,
                'X-Title: QUIZLY AI Platform'
            ],
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false // Supports local developer/XAMPP environments
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlErrno !== 0) {
            if ($curlErrno === CURLE_OPERATION_TIMEDOUT || $curlErrno === CURLE_OPERATION_TIMEOUTED) {
                throw new Exception('The AI service took too long to respond. Please try again.');
            }
            error_log("OpenRouter cURL error ({$curlErrno}): {$curlError}");
            throw new Exception('AI generation failed due to network connectivity. Please try again.');
        }

        if ($httpCode === 401 || $httpCode === 403) {
            error_log("OpenRouter authentication error HTTP {$httpCode}");
            throw new Exception('AI service authentication failed. Please verify the platform API key.');
        }

        if ($httpCode === 429) {
            error_log("OpenRouter rate limit reached HTTP 429");
            throw new Exception('AI generation is temporarily rate-limited. Please try again in a few moments.');
        }

        if ($httpCode >= 500) {
            error_log("OpenRouter upstream error HTTP {$httpCode}: " . substr((string)$response, 0, 200));
            throw new Exception('The AI service encountered an upstream issue. Please try again.');
        }

        if ($httpCode !== 200) {
            error_log("OpenRouter non-200 response ({$httpCode}): " . substr((string)$response, 0, 200));
            throw new Exception('AI generation failed. Please try again.');
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            error_log("OpenRouter response was not valid JSON: " . substr((string)$response, 0, 200));
            throw new Exception('The AI returned an invalid response. Please regenerate.');
        }

        return $decoded;
    }

    /**
     * Helper to extract content text from completion choice
     */
    public function extractContent(array $response): string {
        $msg = $response['choices'][0]['message'] ?? [];
        $content = $msg['content'] ?? '';
        if (empty($content) && !empty($response['choices'][0]['text'])) {
            $content = $response['choices'][0]['text'];
        }
        if (empty($content) && !empty($msg['reasoning_content'])) {
            $content = $msg['reasoning_content'];
        }
        $content = trim($content);
        // Strip <think>...</think> reasoning blocks if model outputs them
        $content = preg_replace('/<think\b[^>]*>.*?<\/think>/is', '', $content);
        $content = trim($content);

        if ($content === '') {
            throw new Exception('The AI returned an empty message. Please regenerate.');
        }
        return $content;
    }
}
