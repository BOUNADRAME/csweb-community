<?php
/**
 * CSWeb Log Reader Webhook
 *
 * Reads the last N lines from any log file in the CSWeb var/logs/ directory.
 *
 * Environment:
 *   - BREAKOUT_WEBHOOK_TOKEN: shared secret token (required)
 *   - CSWEB_ROOT: path to CSWeb installation (default: /var/www/html)
 *
 * Usage:
 *   GET /log-reader-webhook.php?action=list
 *   GET /log-reader-webhook.php?file=ui.log&lines=200
 *   Authorization: Bearer <token>
 *
 * Response shape: { success, data, error }
 */

require_once __DIR__ . '/webhook_helper.php';

requireMethod(['GET']);
requireBearerToken();

// ---- Configuration ----
$cswebRoot = getenv('CSWEB_ROOT') ?: '/var/www/html';
$logsDir   = $cswebRoot . '/var/logs';

// Strict allowlist for log filenames: simple basename + .log extension.
const LOG_FILENAME_PATTERN = '/^[a-zA-Z0-9._-]+\.log$/';

function isValidLogFilename(string $fileName): bool {
    return (bool) preg_match(LOG_FILENAME_PATTERN, $fileName);
}

/**
 * Resolve a validated filename to an absolute path inside $logsDir, or null
 * if the file does not exist or escapes the directory via symlinks.
 */
function resolveLogPath(string $logsDir, string $fileName): ?string {
    $logsDirReal = realpath($logsDir);
    if ($logsDirReal === false) {
        return null;
    }
    $candidate = $logsDirReal . DIRECTORY_SEPARATOR . $fileName;
    $real = realpath($candidate);
    if ($real === false) {
        return null;
    }
    $prefix = $logsDirReal . DIRECTORY_SEPARATOR;
    if (strncmp($real, $prefix, strlen($prefix)) !== 0) {
        return null;
    }
    return $real;
}

// ---- action=list ----
$action = $_GET['action'] ?? '';
if ($action === 'list') {
    if (!is_dir($logsDir)) {
        respondError('file_not_found', 'Logs directory not found.', 404);
    }
    $files = [];
    foreach (scandir($logsDir) as $entry) {
        if (!isValidLogFilename($entry)) {
            continue;
        }
        $fullPath = $logsDir . '/' . $entry;
        if (is_file($fullPath)) {
            $files[] = [
                'name'         => $entry,
                'sizeBytes'    => filesize($fullPath),
                'lastModified' => date('c', filemtime($fullPath)),
            ];
        }
    }
    usort($files, fn($a, $b) => strcmp($b['lastModified'], $a['lastModified']));
    respondSuccess(['files' => $files]);
}

// ---- read N lines ----
$fileName  = $_GET['file'] ?? 'ui.log';
$lineCount = (int) ($_GET['lines'] ?? 200);

if (!isValidLogFilename($fileName)) {
    respondError('invalid_filename', 'Invalid file name. Must match: ^[a-zA-Z0-9._-]+\\.log$.', 400);
}

if ($lineCount < 1 || $lineCount > 5000) {
    respondError('invalid_body', 'Parameter "lines" must be between 1 and 5000.', 400);
}

$logPath = resolveLogPath($logsDir, $fileName);
if ($logPath === null) {
    respondError('file_not_found', 'Log file not found: ' . $fileName, 404);
}

if (!is_readable($logPath)) {
    respondError('file_not_readable', 'Log file not readable: ' . $fileName, 403);
}

$command = sprintf('tail -n %d %s', $lineCount, escapeshellarg($logPath));
$output  = shell_exec($command);

respondSuccess([
    'file'         => $fileName,
    'content'      => $output !== null ? $output : '',
    'lines'        => $lineCount,
    'size'         => filesize($logPath),
    'lastModified' => date('c', filemtime($logPath)),
]);
