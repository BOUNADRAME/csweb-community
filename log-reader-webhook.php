<?php
/**
 * CSWeb Log Reader Webhook
 *
 * Deploy to: /var/www/html/kairos/log-reader-webhook.php
 *
 * Reads the last N lines from any log file in the CSWeb var/logs/ directory.
 * Secured by Bearer token authentication.
 *
 * Environment:
 *   - BREAKOUT_WEBHOOK_TOKEN: shared secret token (must match CSPRO_WEBHOOK_TOKEN in Kairos)
 *   - CSWEB_ROOT: path to CSWeb installation (default: /var/www/html/kairos)
 *
 * Usage:
 *   GET /kairos/log-reader-webhook.php?action=list
 *   GET /kairos/log-reader-webhook.php?file=ui.log&lines=200
 *   Authorization: Bearer <token>
 *
 * Parameters:
 *   - action: "list" to list available log files (ignores file/lines)
 *   - file:  log filename in var/logs/ — default: ui.log
 *   - lines: number of last lines to return (1-5000) — default: 200
 */

header('Content-Type: application/json; charset=utf-8');

// ---- Configuration ----
$expectedToken = getenv('BREAKOUT_WEBHOOK_TOKEN') ?: 'kairos_breakout_2024';
$cswebRoot     = getenv('CSWEB_ROOT') ?: '/var/www/html/kairos';
$logsDir       = $cswebRoot . '/var/logs';

// ---- Helpers ----
function jsonResponse(int $httpCode, array $data): void {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Method check ----
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, [
        'success' => false,
        'error'   => 'Method not allowed. Use GET.',
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

// ---- Action: list available log files ----
$action = $_GET['action'] ?? '';
if ($action === 'list') {
    if (!is_dir($logsDir)) {
        jsonResponse(404, [
            'success' => false,
            'error'   => 'Logs directory not found',
        ]);
    }

    $files = [];
    foreach (scandir($logsDir) as $entry) {
        $fullPath = $logsDir . '/' . $entry;
        if (is_file($fullPath) && !str_starts_with($entry, '.')) {
            $files[] = [
                'name'         => $entry,
                'sizeBytes'    => filesize($fullPath),
                'lastModified' => date('c', filemtime($fullPath)),
            ];
        }
    }

    // Sort by last modified descending
    usort($files, fn($a, $b) => strcmp($b['lastModified'], $a['lastModified']));

    jsonResponse(200, [
        'success' => true,
        'logsDir' => $logsDir,
        'files'   => $files,
    ]);
}

// ---- Parse parameters ----
$fileName  = $_GET['file'] ?? 'ui.log';
$lineCount = (int) ($_GET['lines'] ?? 200);

// Validate file name: must be a simple filename (no path separators, no ..)
if ($fileName === '' || str_contains($fileName, '/') || str_contains($fileName, '\\')
    || str_contains($fileName, '..') || str_starts_with($fileName, '.')) {
    jsonResponse(400, [
        'success' => false,
        'error'   => 'Invalid file name. Must be a simple filename without path separators.',
    ]);
}

// Validate line count
if ($lineCount < 1 || $lineCount > 5000) {
    jsonResponse(400, [
        'success' => false,
        'error'   => 'Parameter "lines" must be between 1 and 5000',
    ]);
}

// ---- Read log file ----
$logPath = $logsDir . '/' . $fileName;

if (!file_exists($logPath)) {
    jsonResponse(404, [
        'success'  => false,
        'error'    => 'Log file not found: ' . $fileName,
        'file'     => $fileName,
        'path'     => $logPath,
    ]);
}

if (!is_readable($logPath)) {
    jsonResponse(403, [
        'success' => false,
        'error'   => 'Log file not readable: ' . $fileName,
        'file'    => $fileName,
    ]);
}

// Use tail for efficiency on large files
$command = sprintf('tail -n %d %s', $lineCount, escapeshellarg($logPath));
$output  = shell_exec($command);

$fileSize = filesize($logPath);
$fileMtime = filemtime($logPath);

jsonResponse(200, [
    'success'      => true,
    'file'         => $fileName,
    'lines'        => $lineCount,
    'content'      => $output !== null ? $output : '',
    'fileSizeBytes' => $fileSize,
    'lastModified' => date('c', $fileMtime),
]);
