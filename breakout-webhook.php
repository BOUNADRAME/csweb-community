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
$expectedToken = getenv('BREAKOUT_WEBHOOK_TOKEN');
if (!$expectedToken) {
    jsonResponse(500, [
        'success' => false,
        'error'   => 'Server misconfiguration: BREAKOUT_WEBHOOK_TOKEN environment variable is not set.',
    ]);
}
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
    'php %s/bin/console csweb:process-cases-by-dict %s',
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

// Merge stderr into output if stderr has content
$combinedOutput = trim($stdout);
$combinedError  = trim($stderr);
if ($combinedError && $combinedOutput) {
    $combinedOutput .= "\n" . $combinedError;
} elseif ($combinedError) {
    $combinedOutput = $combinedError;
}

// ---- Write execution log file ----
$logFile    = null;
$logWritten = false;
$logError   = null;
$logDir     = $cswebRoot . '/var/logs';

if (!is_dir($logDir)) {
    $logError = 'Log directory does not exist: ' . $logDir;
} elseif (!is_writable($logDir)) {
    $logError = 'Log directory not writable: ' . $logDir . ' (user: ' . get_current_user() . ', uid: ' . getmyuid() . ')';
} else {
    $logFile = $logDir . '/' . $dictionary . '_' . date('Ymd_His') . '-api.log';
    $logContent = sprintf(
        "[%s] BREAKOUT dictionary=%s exitCode=%d duration=%dms\n--- OUTPUT ---\n%s\n",
        date('Y-m-d H:i:s'),
        $dictionary,
        $exitCode,
        $durationMs,
        $combinedOutput
    );
    $written = file_put_contents($logFile, $logContent);
    if ($written === false) {
        $err = error_get_last();
        $logError = 'file_put_contents failed: ' . ($err['message'] ?? 'unknown error');
        $logFile  = null;
    } else {
        $logWritten = true;
    }
}

// ---- Return result ----
$success = ($exitCode === 0);
$httpCode = $success ? 200 : 500;

$response = [
    'success'    => $success,
    'dictionary' => $dictionary,
    'exitCode'   => $exitCode,
    'output'     => $combinedOutput,
    'error'      => $combinedError,
    'durationMs' => $durationMs,
    'logFile'    => $logFile ? basename($logFile) : null,
];

if ($logError) {
    $response['logError'] = $logError;
}

jsonResponse($httpCode, $response);
