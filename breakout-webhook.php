<?php
/**
 * CSPro Breakout Webhook
 *
 * Deploy to: /var/www/html/kairos/breakout-webhook.php
 *
 * Executes `php bin/console csweb:process-cases-by-dict <DICT>` on the CSWeb server.
 * Secured by Bearer token authentication.
 *
 * Environment:
 *   - BREAKOUT_WEBHOOK_TOKEN: shared secret token (must match CSPRO_WEBHOOK_TOKEN in Kairos)
 *   - CSWEB_ROOT: path to CSWeb installation (default: /var/www/html/csweb)
 *
 * Usage:
 *   POST /kairos/breakout-webhook.php
 *   Authorization: Bearer <token>
 *   Content-Type: application/json
 *   Body: {"dictionary": "EVAL_PRODUCTEURS_USAID"}
 */

header('Content-Type: application/json; charset=utf-8');

// ---- Configuration ----
$expectedToken = getenv('BREAKOUT_WEBHOOK_TOKEN') ?: 'kairos_breakout_2024';
$cswebRoot     = getenv('CSWEB_ROOT') ?: '/var/www/html/kairos';
$maxExecTime   = 300; // seconds

set_time_limit($maxExecTime + 10);

// ---- Helpers ----
function jsonResponse(int $httpCode, array $data): void {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Method check ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, [
        'success' => false,
        'error'   => 'Method not allowed. Use POST.',
    ]);
}

// ---- Token validation ----
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    jsonResponse(401, [
        'success' => false,
        'error'   => 'Missing or invalid Authorization header. Expected: Bearer <token>',
    ]);
}

$providedToken = $matches[1];
if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    jsonResponse(401, [
        'success' => false,
        'error'   => 'Invalid token',
    ]);
}

// ---- Parse request body ----
$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['dictionary'])) {
    jsonResponse(400, [
        'success' => false,
        'error'   => 'Missing "dictionary" in request body',
    ]);
}

$dictionary = $body['dictionary'];

// ---- Validate dictionary name (only uppercase letters, digits, underscores) ----
if (!preg_match('/^[A-Z0-9_]+$/', $dictionary)) {
    jsonResponse(400, [
        'success' => false,
        'error'   => 'Invalid dictionary name. Must match: ^[A-Z0-9_]+$',
    ]);
}

// ---- Execute breakout command ----
$command = sprintf(
    'php %s/bin/console csweb:process-cases-by-dict %s 2>&1',
    escapeshellarg($cswebRoot),
    escapeshellarg($dictionary)
);

$startTime = microtime(true);

$descriptors = [
    0 => ['pipe', 'r'],  // stdin
    1 => ['pipe', 'w'],  // stdout
    2 => ['pipe', 'w'],  // stderr
];

$process = proc_open($command, $descriptors, $pipes);

if (!is_resource($process)) {
    $durationMs = (int)((microtime(true) - $startTime) * 1000);
    jsonResponse(500, [
        'success'    => false,
        'dictionary' => $dictionary,
        'exitCode'   => -1,
        'output'     => '',
        'error'      => 'Failed to start process',
        'durationMs' => $durationMs,
    ]);
}

fclose($pipes[0]); // close stdin

$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);

$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);

$exitCode = proc_close($process);
$durationMs = (int)((microtime(true) - $startTime) * 1000);

// ---- Return result ----
$success = ($exitCode === 0);
$httpCode = $success ? 200 : 500;

jsonResponse($httpCode, [
    'success'    => $success,
    'dictionary' => $dictionary,
    'exitCode'   => $exitCode,
    'output'     => trim($stdout),
    'error'      => trim($stderr),
    'durationMs' => $durationMs,
]);
