<?php
/**
 * CSWeb Media Webhook
 *
 * List, read and delete the media files that CSPro applications upload to
 * CSWeb (audio, photos), for a server-side platform that must not hold a
 * CSWeb user account. The path always travels in the JSON body, never in the
 * URL, so web-server access logs never contain it.
 *
 * Environment:
 *   - WEBHOOK_TOKEN_MEDIA      token of this webhook (required: no fallback
 *                              to the shared token, the webhook answers 503
 *                              until it is set)
 *   - MEDIA_WEBHOOK_PREFIXES   folders the webhook may touch, relative to the
 *                              CSWeb files folder, comma-separated
 *                              (required, e.g. "praps2/media/")
 *   - MEDIA_WEBHOOK_EXTENSIONS file extensions allowed (default "m4a,jpg,jpeg,png")
 *   - MEDIA_WEBHOOK_MAX_BYTES  largest content returned by one "read" call
 *                              (default 20971520 = 20 MiB); read bigger files
 *                              in pieces with "offset" and "longueur"
 *   - WEBHOOK_RATE_LIMIT_PER_MIN  requests per minute (default 60)
 *
 * Request: POST, Authorization: Bearer <token>, JSON body:
 *   {"action": "list",   "dossier": "praps2/media/1"}
 *   {"action": "info",   "chemin": "praps2/media/1/abc_ENTRETIEN.m4a"}
 *   {"action": "read",   "chemin": "...", "offset": 0, "longueur": 1048576}
 *   {"action": "delete", "chemin": "...", "definitif": false}
 *
 * Responses (envelope { success, data, error }):
 *   200 list   data: { dossier, fichiers: [{nom, octets, md5, modifie}], dossiers: [nom] }
 *   200 info   data: { octets, sha256, md5, type, modifie }
 *   200 read   data: { octets, sha256, md5, type, offset, longueur, contenu (base64), contenu_sha256 }
 *              octets / sha256 / md5 describe the whole file, contenu the piece returned
 *   200 delete data: { supprime: true, mode: "corbeille" | "definitif" }
 *   400 invalid path ("..", absolute path, extension not allowed) or body
 *   401 missing or wrong token, 403 outside the allowed folders,
 *   404 not found, 413 piece larger than MEDIA_WEBHOOK_MAX_BYTES,
 *   503 webhook not configured
 *
 * "delete" without "definitif" moves the file to the CSWeb trash (.trash/,
 * same place as the File Manager, so it can be restored); "definitif": true
 * erases it. Every action is written to var/logs/media-webhook.log with a
 * fingerprint of the path (SHA-256), never the path itself.
 */

require_once __DIR__ . '/webhook_helper.php';

requireMethod(['POST']);
requireBearerToken('media', true);

const MEDIA_DEFAULT_EXTENSIONS = 'm4a,jpg,jpeg,png';
const MEDIA_DEFAULT_MAX_BYTES  = 20971520;
const MEDIA_PATH_MAX_LENGTH    = 1024;

$mediaTypes = [
    'm4a' => 'audio/mp4', 'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
    'ogg' => 'audio/ogg', 'aac' => 'audio/aac', '3gp' => 'video/3gpp', 'amr' => 'audio/amr',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    'pdf' => 'application/pdf',
];

// ---- Configuration ----
$cswebRoot = getenv('CSWEB_ROOT') ?: __DIR__;
foreach ([__DIR__ . '/src/AppBundle/config.php', __DIR__ . '/src/config.php'] as $configFile) {
    if (is_file($configFile)) {
        require_once $configFile;
        break;
    }
}
$filesFolder = defined('FILES_FOLDER') ? FILES_FOLDER : (getenv('FILES_FOLDER') ?: '');
$filesRoot   = $filesFolder !== '' ? realpath($filesFolder) : false;
if ($filesRoot === false || !is_dir($filesRoot)) {
    respondError('server_misconfigured', 'CSWeb files folder not found (FILES_FOLDER).', 503);
}

$prefixes = [];
foreach (explode(',', (string) getenv('MEDIA_WEBHOOK_PREFIXES')) as $p) {
    $p = trim(str_replace('\\', '/', $p), " /");
    if ($p !== '' && mediaPathIsClean($p)) {
        $prefixes[] = $p . '/';
    }
}
if (!$prefixes) {
    respondError('server_misconfigured', 'Webhook disabled: set MEDIA_WEBHOOK_PREFIXES (e.g. "praps2/media/").', 503);
}

$extensions = array_values(array_filter(array_map(
    fn($e) => strtolower(trim($e, " .")),
    explode(',', (string) (getenv('MEDIA_WEBHOOK_EXTENSIONS') ?: MEDIA_DEFAULT_EXTENSIONS))
)));
$maxBytes = (int) (getenv('MEDIA_WEBHOOK_MAX_BYTES') ?: MEDIA_DEFAULT_MAX_BYTES);
if ($maxBytes < 1) {
    $maxBytes = MEDIA_DEFAULT_MAX_BYTES;
}

// ---- Request ----
$body = readJsonBody();
if (!$body || !isset($body['action']) || !is_string($body['action'])) {
    respondError('invalid_body', 'Missing "action" in request body (list, info, read, delete).', 400);
}
$action = $body['action'];

switch ($action) {
    case 'list':
        $rel = mediaRequirePath($body, 'dossier', false);
        $abs = mediaResolve($rel, false);
        $files = [];
        $dirs  = [];
        foreach (scandir($abs) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name[0] === '.') {
                continue;
            }
            $full = $abs . '/' . $name;
            if (is_dir($full)) {
                $dirs[] = $name;
            } elseif (is_file($full) && in_array(mediaExtension($name), $extensions, true)) {
                $files[] = [
                    'nom'     => $name,
                    'octets'  => filesize($full),
                    'md5'     => md5_file($full),
                    'modifie' => date(DATE_ATOM, filemtime($full)),
                ];
            }
        }
        mediaAudit('list', $rel, 200);
        respondSuccess(['dossier' => $rel, 'fichiers' => $files, 'dossiers' => $dirs]);

    case 'info':
        $rel = mediaRequirePath($body, 'chemin', true);
        $abs = mediaResolve($rel, true);
        mediaAudit('info', $rel, 200);
        respondSuccess(mediaDescribe($abs, $rel));

    case 'read':
        $rel = mediaRequirePath($body, 'chemin', true);
        $abs = mediaResolve($rel, true);
        $size = filesize($abs);
        $offset = isset($body['offset']) ? $body['offset'] : 0;
        if (!is_int($offset) || $offset < 0 || ($offset > 0 && $offset >= $size)) {
            respondError('invalid_body', '"offset" must be an integer between 0 and the file size.', 400);
        }
        $length = array_key_exists('longueur', $body) ? $body['longueur'] : $size - $offset;
        if (!is_int($length) || $length < 0) {
            respondError('invalid_body', '"longueur" must be a positive integer.', 400);
        }
        $length = min($length, $size - $offset);
        if ($length > $maxBytes) {
            mediaAudit('read', $rel, 413);
            respondJson(413, false, ['octets' => $size, 'max_octets' => $maxBytes], [
                'code'    => 'content_too_large',
                'message' => "File larger than MEDIA_WEBHOOK_MAX_BYTES ($maxBytes bytes): read it in pieces with \"offset\" and \"longueur\".",
            ]);
        }
        $content = $length > 0 ? file_get_contents($abs, false, null, $offset, $length) : '';
        if ($content === false) {
            mediaAudit('read', $rel, 500);
            respondError('read_failed', 'File could not be read.', 500);
        }
        $data = mediaDescribe($abs, $rel);
        $data['offset']         = $offset;
        $data['longueur']       = strlen($content);
        $data['contenu']        = base64_encode($content);
        $data['contenu_sha256'] = hash('sha256', $content);
        mediaAudit('read', $rel, 200);
        respondSuccess($data);

    case 'delete':
        $rel = mediaRequirePath($body, 'chemin', true);
        $abs = mediaResolve($rel, true);
        $definitive = ($body['definitif'] ?? false) === true;
        if ($definitive) {
            $ok = @unlink($abs);
        } else {
            // Same naming as the File Manager trash, so it can be restored there.
            $trash = $filesRoot . '/.trash';
            if (!is_dir($trash)) {
                @mkdir($trash, 0775, true);
            }
            $target = $trash . '/__trashedon__' . time() . '__' . str_replace('/', '_', $rel);
            $ok = @rename($abs, $target);
        }
        if (!$ok) {
            mediaAudit('delete', $rel, 500);
            respondError('delete_failed', 'File could not be deleted.', 500);
        }
        mediaAudit($definitive ? 'delete-definitive' : 'delete-trash', $rel, 200);
        respondSuccess(['supprime' => true, 'mode' => $definitive ? 'definitif' : 'corbeille']);

    default:
        respondError('invalid_action', 'Unknown action. Use: list, info, read, delete.', 400);
}

// ---- Helpers ----

/** No absolute path, no "." / ".." segment, no empty segment, no control character. */
function mediaPathIsClean(string $path): bool {
    if ($path === '' || strlen($path) > MEDIA_PATH_MAX_LENGTH || $path[0] === '/'
        || preg_match('/[\x00-\x1f\x7f\\\\:]/', $path)) {
        return false;
    }
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return false;
        }
    }
    return true;
}

function mediaExtension(string $name): string {
    return strtolower(pathinfo($name, PATHINFO_EXTENSION));
}

/**
 * Read and check a path from the body. 400 when malformed or (for files) of a
 * type that is not allowed, 403 when outside MEDIA_WEBHOOK_PREFIXES.
 *
 * @param array<string,mixed> $body
 */
function mediaRequirePath(array $body, string $field, bool $isFile): string {
    global $prefixes, $extensions;
    $path = $body[$field] ?? null;
    if (!is_string($path)) {
        respondError('invalid_body', "Missing \"$field\" in request body.", 400);
    }
    $path = rtrim($path, '/');
    if (!mediaPathIsClean($path)) {
        respondError('invalid_path', 'Invalid path: relative path without "..", "." or empty segments expected.', 400);
    }
    if ($isFile && !in_array(mediaExtension($path), $extensions, true)) {
        respondError('invalid_path', 'File type not allowed.', 400);
    }
    foreach ($prefixes as $prefix) {
        if (str_starts_with($path . '/', $prefix)) {
            return $path;
        }
    }
    mediaAudit('denied', $path, 403);
    respondError('forbidden_path', 'Path outside the folders this webhook may access.', 403);
}

/**
 * Absolute path of an existing file or folder, after resolving symbolic links:
 * it must still be inside the allowed folder. 404 when absent.
 */
function mediaResolve(string $rel, bool $isFile): string {
    global $filesRoot, $prefixes;
    $real = realpath($filesRoot . '/' . $rel);
    if ($real === false || ($isFile ? !is_file($real) : !is_dir($real))) {
        mediaAudit('not-found', $rel, 404);
        respondError('not_found', $isFile ? 'File not found.' : 'Folder not found.', 404);
    }
    foreach ($prefixes as $prefix) {
        $base = realpath($filesRoot . '/' . rtrim($prefix, '/'));
        if ($base !== false && ($real === $base || str_starts_with($real, $base . '/'))) {
            return $real;
        }
    }
    mediaAudit('denied', $rel, 403);
    respondError('forbidden_path', 'Path outside the folders this webhook may access.', 403);
}

/** @return array<string,mixed> */
function mediaDescribe(string $abs, string $rel): array {
    global $mediaTypes;
    return [
        'octets'  => filesize($abs),
        'sha256'  => hash_file('sha256', $abs),
        'md5'     => md5_file($abs),
        'type'    => $mediaTypes[mediaExtension($rel)] ?? 'application/octet-stream',
        'modifie' => date(DATE_ATOM, filemtime($abs)),
    ];
}

/** One line per action, with a fingerprint of the path instead of the path. */
function mediaAudit(string $action, string $rel, int $status): void {
    global $cswebRoot;
    $dir = $cswebRoot . '/var/logs';
    if (!is_dir($dir)) {
        return;
    }
    $line = sprintf(
        "%s %s %d path_sha256=%s ip=%s\n",
        date(DATE_ATOM),
        $action,
        $status,
        substr(hash('sha256', $rel), 0, 16),
        preg_replace('/[^0-9a-fA-F:.]/', '', $_SERVER['REMOTE_ADDR'] ?? '-')
    );
    @file_put_contents($dir . '/media-webhook.log', $line, FILE_APPEND | LOCK_EX);
}
