<?php
/**
 * CSPro Dictionary Schema Webhook
 *
 * Manages the `cspro_dictionaries_schema` table (breakout output destinations).
 *
 * Environment:
 *   - BREAKOUT_WEBHOOK_TOKEN: shared secret token (required)
 *
 * GET:
 *   ?action=list
 *   ?action=status&dictionary_id=3   (or &dictionary_name=FOO_DICT)
 *
 * POST (JSON body):
 *   { "action": "register",  "dictionary_id": 3 OR "dictionary_name": "FOO_DICT",
 *     "host_name": "...", "schema_name": "...", "schema_user_name": "...", "schema_password": "..." }
 *   { "action": "unregister", "dictionary_id": 3 OR "dictionary_name": "FOO_DICT" }
 *
 * Response shape: { success, data, error, meta? }
 */

require_once __DIR__ . '/webhook_helper.php';

requireMethod(['GET', 'POST']);
requireBearerToken();

// Load CSWeb DB config
$configFile = __DIR__ . '/src/AppBundle/config.php';
if (!file_exists($configFile)) {
    respondError('server_misconfigured', 'CSWeb config.php not found at: ' . $configFile, 500);
}
require_once $configFile;

function getDbConnection(): PDO {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DBHOST, DBNAME);
    return new PDO($dsn, DBUSER, DBPASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Resolve a dictionary by id or by name (one of the two must be supplied).
 * Returns the row or null if not found.
 *
 * @return array{id:int, dictionary_name:string, dictionary_label:string}|null
 */
function findDictionary(PDO $pdo, ?int $id, ?string $name): ?array {
    if ($id !== null) {
        $stmt = $pdo->prepare('SELECT id, dictionary_name, dictionary_label FROM cspro_dictionaries WHERE id = ?');
        $stmt->execute([$id]);
    } elseif ($name !== null) {
        $stmt = $pdo->prepare('SELECT id, dictionary_name, dictionary_label FROM cspro_dictionaries WHERE dictionary_name = ?');
        $stmt->execute([strtoupper($name)]);
    } else {
        return null;
    }
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Extract dictionary_id (int) or dictionary_name (string) from a request payload.
 *
 * @param array<string,mixed> $src
 * @return array{0:?int, 1:?string}
 */
function extractDictionaryRef(array $src): array {
    $id = null;
    if (isset($src['dictionary_id'])) {
        if (!ctype_digit((string) $src['dictionary_id']) || (int) $src['dictionary_id'] < 1) {
            respondError('invalid_body', '"dictionary_id" must be a positive integer.', 400);
        }
        $id = (int) $src['dictionary_id'];
    }
    $name = null;
    if (isset($src['dictionary_name']) && trim((string) $src['dictionary_name']) !== '') {
        $name = trim((string) $src['dictionary_name']);
        if (!preg_match('/^[A-Z0-9_]+$/', $name)) {
            respondError('invalid_dictionary', 'Invalid dictionary_name. Must match: ^[A-Z0-9_]+$.', 400);
        }
    }
    return [$id, $name];
}

// ---- Route ----
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGet();
} else {
    handlePost();
}

// ---- GET handlers ----
function handleGet(): void {
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        listDictionaries();
    } elseif ($action === 'status') {
        [$id, $name] = extractDictionaryRef($_GET);
        if ($id === null && $name === null) {
            respondError('invalid_body', 'Provide either "dictionary_id" or "dictionary_name".', 400);
        }
        getDictionaryStatus($id, $name);
    } else {
        respondError('invalid_action', 'Missing or invalid "action". Use: list, status.', 400);
    }
}

function listDictionaries(): void {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query('
            SELECT d.id, d.dictionary_name, d.dictionary_label,
                   (s.dictionary_id IS NOT NULL) AS configured,
                   s.host_name, s.schema_name, s.schema_user_name
            FROM cspro_dictionaries d
            LEFT JOIN cspro_dictionaries_schema s ON d.id = s.dictionary_id
            ORDER BY d.dictionary_name
        ');
        $rows = $stmt->fetchAll();
    } catch (\Throwable $e) {
        respondError('internal_error', 'Failed to list dictionaries.', 500);
    }

    foreach ($rows as &$row) {
        $row['configured'] = (bool) $row['configured'];
        $row['id'] = (int) $row['id'];
    }
    unset($row);

    respondSuccess($rows, ['total' => count($rows)]);
}

function getDictionaryStatus(?int $id, ?string $name): void {
    try {
        $pdo = getDbConnection();
        if ($id !== null) {
            $stmt = $pdo->prepare('
                SELECT d.id, d.dictionary_name, d.dictionary_label,
                       (s.dictionary_id IS NOT NULL) AS configured,
                       s.host_name, s.schema_name, s.schema_user_name
                FROM cspro_dictionaries d
                LEFT JOIN cspro_dictionaries_schema s ON d.id = s.dictionary_id
                WHERE d.id = ?
            ');
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare('
                SELECT d.id, d.dictionary_name, d.dictionary_label,
                       (s.dictionary_id IS NOT NULL) AS configured,
                       s.host_name, s.schema_name, s.schema_user_name
                FROM cspro_dictionaries d
                LEFT JOIN cspro_dictionaries_schema s ON d.id = s.dictionary_id
                WHERE d.dictionary_name = ?
            ');
            $stmt->execute([strtoupper((string) $name)]);
        }
        $row = $stmt->fetch();
    } catch (\Throwable $e) {
        respondError('internal_error', 'Failed to read dictionary.', 500);
    }

    if (!$row) {
        respondError('dictionary_not_found', 'Dictionary not found.', 404);
    }

    $row['configured'] = (bool) $row['configured'];
    $row['id'] = (int) $row['id'];

    respondSuccess($row);
}

// ---- POST handlers ----
function handlePost(): void {
    $body = readJsonBody();
    if (!$body || empty($body['action'])) {
        respondError('invalid_body', 'Missing "action" in request body. Use: register, unregister.', 400);
    }

    $action = $body['action'];
    if ($action === 'register') {
        registerSchema($body);
    } elseif ($action === 'unregister') {
        unregisterSchema($body);
    } else {
        respondError('invalid_action', 'Invalid action: ' . $action . '. Use: register, unregister.', 400);
    }
}

/**
 * Hostname validation for breakout target databases.
 *
 * Pragmatic policy — accept anything that is syntactically a hostname or IP,
 * reject only obvious garbage and known-dangerous ranges:
 *
 *   - DNS hostnames matching RFC 952/1123 → accepted (public, internal, .local)
 *   - IPv4 / IPv6 valid literals → accepted (private, public, loopback)
 *   - IPv4 link-local 169.254/16 → REJECTED (cloud-metadata SSRF, no legitimate DB use)
 *
 * Rationale: in production the breakout target may change at any time
 * (failover, migration, customer-supplied host) and operators cannot be
 * forced to maintain a static allowlist. The webhook is already gated by
 * a Bearer token strong enough to keep this surface trustworthy.
 */
function isValidHostname(string $h): bool {
    if ($h === '' || strlen($h) > 253) {
        return false;
    }

    if (filter_var($h, FILTER_VALIDATE_IP)) {
        // Always reject IPv4 link-local (169.254/16): this covers the
        // AWS/GCP/Azure instance-metadata IP (169.254.169.254) used for
        // credential theft via SSRF. No legitimate database lives there.
        if (preg_match('/^169\.254\./', $h)) {
            return false;
        }
        return true;
    }

    // DNS hostname (RFC 952/1123 simplified): letters/digits/dots/hyphens,
    // no leading or trailing dot/hyphen, length 1..253.
    return (bool) preg_match(
        '/^(?=.{1,253}$)([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/',
        $h
    );
}

/** @param array<string,mixed> $body */
function registerSchema(array $body): void {
    $required = ['host_name', 'schema_name', 'schema_user_name', 'schema_password'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            respondError('invalid_body', 'Missing required field: ' . $field, 400);
        }
    }

    // Length / charset constraints to prevent absurd inputs landing in DB.
    if (!isValidHostname((string) $body['host_name'])) {
        respondError('invalid_body', 'Invalid host_name (must be a valid hostname or IP).', 400);
    }
    if (strlen((string) $body['schema_name']) > 64
        || !preg_match('/^[a-zA-Z0-9_]+$/', (string) $body['schema_name'])) {
        respondError('invalid_body', 'Invalid schema_name (alphanumeric/underscore, max 64 chars).', 400);
    }
    if (strlen((string) $body['schema_user_name']) > 64) {
        respondError('invalid_body', 'schema_user_name too long (max 64 chars).', 400);
    }
    if (strlen((string) $body['schema_password']) > 256) {
        respondError('invalid_body', 'schema_password too long (max 256 chars).', 400);
    }

    [$id, $name] = extractDictionaryRef($body);
    if ($id === null && $name === null) {
        respondError('invalid_body', 'Provide either "dictionary_id" or "dictionary_name".', 400);
    }

    try {
        $pdo = getDbConnection();
        $dict = findDictionary($pdo, $id, $name);
    } catch (\Throwable $e) {
        respondError('internal_error', 'Failed to read dictionary.', 500);
    }

    if (!$dict) {
        respondError('dictionary_not_found', 'Dictionary not found.', 404);
    }

    try {
        // schema_password is stored AES-encrypted to stay consistent with the legacy
        // CSWeb code path that reads it via AES_DECRYPT(s.schema_password, 'cspro').
        $stmt = $pdo->prepare("
            INSERT INTO cspro_dictionaries_schema
                (dictionary_id, host_name, schema_name, schema_user_name, schema_password, modified_time, created_time)
            VALUES (?, ?, ?, ?, AES_ENCRYPT(?, 'cspro'), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                host_name = VALUES(host_name),
                schema_name = VALUES(schema_name),
                schema_user_name = VALUES(schema_user_name),
                schema_password = VALUES(schema_password),
                modified_time = NOW()
        ");
        $stmt->execute([
            $dict['id'],
            $body['host_name'],
            $body['schema_name'],
            $body['schema_user_name'],
            $body['schema_password'],
        ]);
    } catch (\Throwable $e) {
        respondError('internal_error', 'Failed to register schema.', 500);
    }

    respondSuccess([
        'dictionary_id'   => (int) $dict['id'],
        'dictionary_name' => $dict['dictionary_name'],
        'message'         => 'Schema registered.',
    ]);
}

/** @param array<string,mixed> $body */
function unregisterSchema(array $body): void {
    [$id, $name] = extractDictionaryRef($body);
    if ($id === null && $name === null) {
        respondError('invalid_body', 'Provide either "dictionary_id" or "dictionary_name".', 400);
    }

    try {
        $pdo = getDbConnection();
        $dict = findDictionary($pdo, $id, $name);
    } catch (\Throwable $e) {
        respondError('internal_error', 'Failed to read dictionary.', 500);
    }

    if (!$dict) {
        respondError('dictionary_not_found', 'Dictionary not found.', 404);
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM cspro_dictionaries_schema WHERE dictionary_id = ?');
        $stmt->execute([$dict['id']]);
        $deleted = $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
        respondError('internal_error', 'Failed to unregister schema.', 500);
    }

    respondSuccess([
        'dictionary_id'   => (int) $dict['id'],
        'dictionary_name' => $dict['dictionary_name'],
        'deleted'         => $deleted,
        'message'         => $deleted ? 'Schema unregistered.' : 'No schema was configured.',
    ]);
}
