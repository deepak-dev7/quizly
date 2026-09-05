<?php

/**
 * Calculates score earned for an answer based on correctness, response time, and question max points.
 * Linear Speed-Based Formula:
 *   MIN_TIME = 0.10s (100ms)
 *   MAX_TIME = 10.00s (10000ms)
 *   MIN_SCORE = 10% of maxPoints
 *   score = maxPoints - ((clampedTime - 0.10) / 9.90) * (maxPoints - minScore)
 */
function calculateAnswerScore(bool $isCorrect, int $responseTimeMs, int $timerSeconds = 20, int $maxPoints = 1000): int {
    if (!$isCorrect) {
        return 0;
    }

    $minTime = 0.10;
    $maxTime = 10.00;
    $minScorePercent = 0.10;

    $responseTimeSec = $responseTimeMs / 1000.0;
    $clampedTimeSec = max($minTime, min($maxTime, (float)$responseTimeSec));

    $minScore = (float)($maxPoints * $minScorePercent);

    $normalizedRatio = ($clampedTimeSec - $minTime) / ($maxTime - $minTime); // (time - 0.10) / 9.90
    $score = (float)$maxPoints - ($normalizedRatio * ($maxPoints - $minScore));

    $finalScore = (int)round($score);
    $finalScore = max((int)round($minScore), min($maxPoints, $finalScore));

    return $finalScore;
}

/**
 * Calculates streak bonus points based on consecutive correct answers.
 */
function calculateStreakBonus(int $streakCount): int {
    if ($streakCount >= 5) return 500;
    if ($streakCount === 4) return 300;
    if ($streakCount === 3) return 200;
    if ($streakCount === 2) return 100;
    return 0;
}

/**
 * Fetches the session leaderboard sorted deterministically by:
 * 1. total_score DESC
 * 2. correct_count DESC
 * 3. cumulative_time_ms ASC
 * 4. participant_id ASC
 * Includes last_formatted_time showing response time for the last question (2 decimal places).
 */
function getSessionLeaderboard(PDO $db, int $sessionId, int $limit = 50): array {
    $stmt = $db->prepare("
        SELECT 
            p.id AS participant_id,
            p.nickname,
            p.avatar,
            p.total_score,
            p.correct_count,
            p.streak_count,
            p.cumulative_time_ms,
            FORMAT(p.cumulative_time_ms / 1000.0, 2) AS cumulative_time_formatted,
            (
                SELECT a.response_time_ms 
                FROM answers a 
                WHERE a.participant_id = p.id AND a.session_id = p.session_id 
                ORDER BY a.id DESC 
                LIMIT 1
            ) AS last_response_time_ms
        FROM participants p
        WHERE p.session_id = :session_id
        ORDER BY 
            p.total_score DESC,
            p.correct_count DESC,
            p.cumulative_time_ms ASC,
            p.id ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rank = 1;
    foreach ($rows as $index => &$row) {
        $row['rank'] = $rank++;
        $row['formatted_time'] = sprintf('%.2fs', $row['cumulative_time_ms'] / 1000.0);

        if (!is_null($row['last_response_time_ms']) && $row['last_response_time_ms'] !== false) {
            $row['last_formatted_time'] = sprintf('%.2fs', (int)$row['last_response_time_ms'] / 1000.0);
        } else {
            $row['last_formatted_time'] = '—';
        }
    }

    return $rows;
}

/**
 * Fetches per-question leaderboard breakdown (2 decimal places time formatting).
 */
function getQuestionLeaderboard(PDO $db, int $sessionId, int $questionId, int $limit = 50): array {
    $stmt = $db->prepare("
        SELECT 
            p.id AS participant_id,
            p.nickname,
            p.avatar,
            a.is_correct,
            a.score_earned,
            a.streak_bonus,
            a.response_time_ms,
            a.selected_option_key,
            FORMAT(a.response_time_ms / 1000.0, 2) AS response_time_formatted,
            p.total_score
        FROM answers a
        JOIN participants p ON a.participant_id = p.id
        WHERE a.session_id = :session_id AND a.question_id = :question_id
        ORDER BY 
            a.is_correct DESC,
            a.score_earned DESC,
            a.response_time_ms ASC,
            p.id ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
    $stmt->bindValue(':question_id', $questionId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $index => &$row) {
        $row['rank'] = $index + 1;
        $row['formatted_time'] = sprintf('%.2fs', $row['response_time_ms'] / 1000.0);
    }

    return $rows;
}
