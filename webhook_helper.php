<?php
/**
 * CSWeb webhooks shared helper.
 *
 * Centralises:
 *  - JSON response shape: { success, data?, error?, meta? }
 *  - Bearer token authentication
 *  - Capped JSON body parsing
 *  - Method allowlist enforcement
 *  - Stable error codes (machine-readable)
 *
 * Used by:
 *  - breakout-webhook.php
 *  - breakout-status-webhook.php
 *  - dictionary-schema-webhook.php
 *  - log-reader-webhook.php
 */

const WEBHOOK_BODY_MAX_BYTES = 65_536;
const WEBHOOK_RATE_LIMIT_MAX    = 60;   // requests
const WEBHOOK_RATE_LIMIT_WINDOW = 60;   // seconds

/**
 * Send a JSON response with the unified shape and exit.
 *
 * @param array<string,mixed>|list<mixed>|null $data
 * @param array{code:string,message:string}|null $error
 * @param array<string,mixed>|null $meta
 */
function respondJson(int $httpCode, bool $success, array|null $data = null, ?array $error = null, ?array $meta = null): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');

    $payload = ['success' => $success];
    $payload['data']  = $data;
    $payload['error'] = $error;
    if ($meta !== null) {
        $payload['meta'] = $meta;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param array<string,mixed>|list<mixed>|null $data
 * @param array<string,mixed>|null $meta
 */
function respondSuccess(array|null $data = null, ?array $meta = null, int $httpCode = 200): void {
    respondJson($httpCode, true, $data, null, $meta);
}

function respondError(string $code, string $message, int $httpCode): void {
    respondJson($httpCode, false, null, ['code' => $code, 'message' => $message]);
}

/**
 * Enforce that the request method is one of $allowed (case-sensitive uppercase).
 *
 * @param list<string> $allowed
 */
function requireMethod(array $allowed): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, $allowed, true)) {
        respondError(
            'method_not_allowed',
            'Method not allowed. Use: ' . implode(', ', $allowed) . '.',
            405
        );
    }
}

/**
 * Validate that the Authorization header carries the configured Bearer token.
 * Aborts the request with 401 / 500 on failure.
 */
function requireBearerToken(): void {
    $expected = getenv('BREAKOUT_WEBHOOK_TOKEN');
    if (!$expected) {
        respondError(
            'server_misconfigured',
            'Server misconfiguration: BREAKOUT_WEBHOOK_TOKEN environment variable is not set.',
            500
        );
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        respondError(
            'missing_token',
            'Missing or invalid Authorization header. Expected: Bearer <token>.',
            401
        );
    }

    if (!hash_equals($expected, $matches[1])) {
        respondError('invalid_token', 'Invalid token.', 401);
    }

    // Sliding-window rate limit, keyed on (token-fingerprint, client IP).
    // Fingerprint avoids exposing raw token in filenames.
    $tokenFp = substr(hash('sha256', $matches[1]), 0, 16);
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    enforceRateLimit($tokenFp . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip));
}

/**
 * Sliding-window rate limiter (max WEBHOOK_RATE_LIMIT_MAX requests per
 * WEBHOOK_RATE_LIMIT_WINDOW seconds per key). Aborts with 429 on excess.
 */
function enforceRateLimit(string $key): void {
    $cacheRoot = (getenv('CSWEB_ROOT') ?: '/var/www/html') . '/var/cache/ratelimit';
    if (!is_dir($cacheRoot)) {
        // Best-effort: if mkdir fails, do not lock the user out — log and bypass.
        if (!@mkdir($cacheRoot, 0775, true) && !is_dir($cacheRoot)) {
            error_log('[webhook] rate-limit cache dir unavailable, skipping limit: ' . $cacheRoot);
            return;
        }
    }

    $file = $cacheRoot . '/' . $key . '.json';
    $now  = time();
    $cutoff = $now - WEBHOOK_RATE_LIMIT_WINDOW;

    $fh = @fopen($file, 'c+');
    if (!$fh) {
        error_log('[webhook] rate-limit fopen failed: ' . $file);
        return;
    }
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        error_log('[webhook] rate-limit flock failed: ' . $file);
        return;
    }

    rewind($fh);
    $contents = stream_get_contents($fh);
    $hits = json_decode($contents ?: '[]', true);
    if (!is_array($hits)) {
        $hits = [];
    }
    // Keep only timestamps within the window.
    $hits = array_values(array_filter($hits, fn($t) => is_int($t) && $t >= $cutoff));

    if (count($hits) >= WEBHOOK_RATE_LIMIT_MAX) {
        // Compute Retry-After: seconds until the oldest hit exits the window.
        $retryAfter = max(1, ($hits[0] + WEBHOOK_RATE_LIMIT_WINDOW) - $now);
        flock($fh, LOCK_UN);
        fclose($fh);
        header('Retry-After: ' . $retryAfter);
        respondError(
            'rate_limited',
            'Too many requests. Retry after ' . $retryAfter . ' seconds.',
            429
        );
    }

    $hits[] = $now;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($hits));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

/**
 * Read and decode the JSON request body, capped at WEBHOOK_BODY_MAX_BYTES.
 * Returns null if the body is empty or invalid JSON; the caller decides how to react.
 *
 * @return array<string,mixed>|null
 */
function readJsonBody(): ?array {
    $raw = file_get_contents('php://input', false, null, 0, WEBHOOK_BODY_MAX_BYTES);
    if ($raw !== false && strlen($raw) >= WEBHOOK_BODY_MAX_BYTES) {
        respondError(
            'body_too_large',
            'Request body too large (max ' . WEBHOOK_BODY_MAX_BYTES . ' bytes).',
            413
        );
    }
    if ($raw === false || $raw === '') {
        return null;
    }
    try {
        $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        respondError('invalid_body', 'Request body is not valid JSON.', 400);
    }
    return is_array($decoded) ? $decoded : null;
}
