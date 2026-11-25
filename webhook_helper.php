<?php
/**
 * CSWeb webhooks shared helper.
 *
 * Centralises:
 *  - JSON response shape: { success, data?, error?, meta? }
 *  - Bearer token authentication, one token per webhook (see requireBearerToken)
 *  - Capped JSON body parsing
 *  - Method allowlist enforcement
 *  - Stable error codes (machine-readable)
 *
 * Used by:
 *  - breakout-webhook.php
 *  - breakout-status-webhook.php
 *  - dictionary-schema-webhook.php
 *  - log-reader-webhook.php
 *  - media-webhook.php
 */

const WEBHOOK_BODY_MAX_BYTES = 65_536;
const WEBHOOK_RATE_LIMIT_DEFAULT = 60;  // requests per window, override with WEBHOOK_RATE_LIMIT_PER_MIN
const WEBHOOK_RATE_LIMIT_WINDOW  = 60;  // seconds

// Never print PHP warnings into a response: they can contain file paths.
ini_set('display_errors', '0');

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
    header('Cache-Control: no-store');

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
 * Validate the Bearer token of a webhook. Aborts with 401 / 503 on failure.
 *
 * Each webhook has its own token, WEBHOOK_TOKEN_<SCOPE> (WEBHOOK_TOKEN_BREAKOUT,
 * WEBHOOK_TOKEN_STATUS, WEBHOOK_TOKEN_SCHEMA, WEBHOOK_TOKEN_LOGS,
 * WEBHOOK_TOKEN_MEDIA). When it is set, only that token opens the webhook.
 * When it is not set, the shared BREAKOUT_WEBHOOK_TOKEN is accepted, so
 * existing installations keep working, unless $scopedOnly is true (media):
 * then the webhook stays closed (503) until its own token is configured.
 */
function requireBearerToken(string $scope, bool $scopedOnly = false): void {
    $scopedVar = 'WEBHOOK_TOKEN_' . strtoupper($scope);
    $expected  = getenv($scopedVar) ?: null;
    if ($expected === null && !$scopedOnly) {
        $expected = getenv('BREAKOUT_WEBHOOK_TOKEN') ?: null;
    }
    if ($expected === null) {
        respondError(
            'server_misconfigured',
            $scopedOnly
                ? "Webhook disabled: set the $scopedVar environment variable."
                : "Webhook disabled: set $scopedVar (or the shared BREAKOUT_WEBHOOK_TOKEN).",
            503
        );
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        respondError(
            'missing_token',
            'Missing or invalid Authorization header. Expected: Bearer <token>.',
            401
        );
    }

    if (!hash_equals($expected, trim($matches[1]))) {
        respondError('invalid_token', 'Invalid token.', 401);
    }

    // Sliding-window rate limit, keyed on (webhook, token fingerprint, client IP).
    // Fingerprint avoids exposing raw token in filenames.
    $tokenFp = substr(hash('sha256', $matches[1]), 0, 16);
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    enforceRateLimit(strtolower($scope) . '_' . $tokenFp . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $ip));
}

/**
 * Sliding-window rate limiter (WEBHOOK_RATE_LIMIT_PER_MIN requests, default 60,
 * per WEBHOOK_RATE_LIMIT_WINDOW seconds per key). Aborts with 429 on excess.
 */
function enforceRateLimit(string $key): void {
    $max = (int) (getenv('WEBHOOK_RATE_LIMIT_PER_MIN') ?: WEBHOOK_RATE_LIMIT_DEFAULT);
    if ($max < 1) {
        $max = WEBHOOK_RATE_LIMIT_DEFAULT;
    }
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

    if (count($hits) >= $max) {
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
