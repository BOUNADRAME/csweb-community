<?php
/**
 * CSPro Breakout Webhook
 *
 * Executes `php bin/console csweb:process-cases-by-dict <DICT>` on the CSWeb server.
 * Secured by Bearer token authentication.
 *
 * Environment:
 *   - BREAKOUT_WEBHOOK_TOKEN: shared secret token (required)
 *   - CSWEB_ROOT: path to CSWeb installation (default: /var/www/html)
 *   - BREAKOUT_WEBHOOK_VERBOSE=1: return full stdout/stderr (default: truncated to 4 KB)
 *
 * Usage:
 *   POST /breakout-webhook.php
 *   Authorization: Bearer <token>
 *   Content-Type: application/json
 *   Body: {"dictionary": "EVAL_PRODUCTEURS_USAID"}
 *
 * Response shape: { success, data, error }
 */

require_once __DIR__ . '/webhook_helper.php';

requireMethod(['POST']);
requireBearerToken();

// ---- Configuration ----
$cswebRoot   = getenv('CSWEB_ROOT') ?: '/var/www/html';
$maxExecTime = 300; // seconds
set_time_limit($maxExecTime + 10);

// ---- Parse and validate body ----
$body = readJsonBody();
if (!$body || empty($body['dictionary'])) {
    respondError('invalid_body', 'Missing "dictionary" in request body.', 400);
}

$dictionary = $body['dictionary'];
if (!preg_match('/^[A-Z0-9_]+$/', $dictionary)) {
    respondError('invalid_dictionary', 'Invalid dictionary name. Must match: ^[A-Z0-9_]+$.', 400);
}

// ---- Execute breakout command ----
$command = sprintf(
    'php %s/bin/console csweb:process-cases-by-dict %s',
    escapeshellarg($cswebRoot),
    escapeshellarg($dictionary)
);

$startTime = microtime(true);

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($command, $descriptors, $pipes);

if (!is_resource($process)) {
    respondError('process_failed', 'Failed to start process.', 500);
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);

$exitCode = proc_close($process);
$durationMs = (int) ((microtime(true) - $startTime) * 1000);

$combinedOutput = trim($stdout);
$combinedStderr = trim($stderr);

// ---- Persist execution log ----
$logFile    = null;
$logDir     = $cswebRoot . '/var/logs';

if (is_dir($logDir) && is_writable($logDir)) {
    $logFile = $logDir . '/' . $dictionary . '_' . date('Ymd_His') . '-api.log';
    $logContent = sprintf(
        "[%s] BREAKOUT dictionary=%s exitCode=%d duration=%dms\n--- OUTPUT ---\n%s\n",
        date('Y-m-d H:i:s'),
        $dictionary,
        $exitCode,
        $durationMs,
        $combinedOutput . ($combinedStderr ? "\n--- STDERR ---\n" . $combinedStderr : '')
    );
    if (file_put_contents($logFile, $logContent) === false) {
        // Log server-side, never leak the underlying message to the caller.
        error_log('[breakout-webhook] log write failed for ' . $logFile);
        $logFile = null;
    }
} else {
    error_log('[breakout-webhook] log dir missing or unwritable: ' . $logDir);
}

// ---- Truncate output unless VERBOSE mode is on ----
$verbose = getenv('BREAKOUT_WEBHOOK_VERBOSE') === '1';
$maxSnippet = 4096;
$truncate = function (string $s) use ($verbose, $maxSnippet): string {
    if ($verbose || strlen($s) <= $maxSnippet) {
        return $s;
    }
    return substr($s, -$maxSnippet) . "\n[... truncated, see logFile for full content]";
};

$success = ($exitCode === 0);
$payload = [
    'dictionary' => $dictionary,
    'exitCode'   => $exitCode,
    'output'     => $truncate($combinedOutput),
    'stderr'     => $truncate($combinedStderr),
    'durationMs' => $durationMs,
    'logFile'    => $logFile ? basename($logFile) : null,
];

if ($success) {
    respondSuccess($payload);
}

respondJson(500, false, $payload, [
    'code'    => 'breakout_failed',
    'message' => 'Breakout exited with non-zero code (' . $exitCode . ').',
]);
