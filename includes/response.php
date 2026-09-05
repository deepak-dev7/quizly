<?php
// Standardized API Response Helper (PHP 7.4+ & 8.x Compatible with Output Buffer Clearing)

function jsonSuccess($data = [], string $message = '', int $statusCode = 200): void {
    if (ob_get_length()) {
        @ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => round(microtime(true) * 1000)
    ]);
    exit;
}

function jsonError(string $message, string $code = 'ERROR', int $statusCode = 400, $details = null): void {
    if (ob_get_length()) {
        @ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    $response = [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message
        ],
        'timestamp' => round(microtime(true) * 1000)
    ];
    if ($details !== null) {
        $response['error']['details'] = $details;
    }
    echo json_encode($response);
    exit;
}
