<?php
/**
 * CSPro Dictionary Schema Webhook
 *
 * Deploy to: /var/www/html/kairos/dictionary-schema-webhook.php
 *
 * Manages the `cspro_dictionaries_schema` table which configures breakout
 * output destinations (MySQL schema for each dictionary).
 *
 * Environment:
 *   - BREAKOUT_WEBHOOK_TOKEN: shared secret token (must match CSPRO_WEBHOOK_TOKEN in Kairos)
 *
 * GET Actions:
 *   ?action=list                  — List all dictionaries with schema configuration status
 *   ?action=status&dictionary_id=3 — Get schema status for a specific dictionary
 *
 * POST Actions (JSON body):
 *   action=register   — Register/update schema for a dictionary
 *   action=unregister — Remove schema configuration for a dictionary
 */

header('Content-Type: application/json; charset=utf-8');

// ---- Configuration ----
$expectedToken = getenv('BREAKOUT_WEBHOOK_TOKEN') ?: 'kairos_breakout_2024';

// Load CSWeb database config
$configFile = __DIR__ . '/src/AppBundle/config.php';
if (!file_exists($configFile)) {
    jsonResponse(500, [
        'success' => false,
        'error'   => 'CSWeb config.php not found at: ' . $configFile,
    ]);
}
require_once $configFile;

// ---- Helpers ----
function jsonResponse(int $httpCode, array $data): void {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getDbConnection(): PDO {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DBHOST, DBNAME);
    $pdo = new PDO($dsn, DBUSER, DBPASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
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

// ---- Route by method ----
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGet();
} elseif ($method === 'POST') {
    handlePost();
} else {
    jsonResponse(405, [
        'success' => false,
        'error'   => 'Method not allowed. Use GET or POST.',
    ]);
}

// ---- GET handlers ----
function handleGet(): void {
    $action = $_GET['action'] ?? '';

    if ($action === 'list') {
        listDictionaries();
    } elseif ($action === 'status') {
        $dictionaryId = $_GET['dictionary_id'] ?? '';
        if (!ctype_digit($dictionaryId) || (int)$dictionaryId < 1) {
            jsonResponse(400, [
                'success' => false,
                'error'   => 'Parameter "dictionary_id" must be a positive integer',
            ]);
        }
        getDictionaryStatus((int)$dictionaryId);
    } else {
        jsonResponse(400, [
            'success' => false,
            'error'   => 'Missing or invalid "action" parameter. Use: list, status',
        ]);
    }
}

function listDictionaries(): void {
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

    // Cast configured to boolean
    foreach ($rows as &$row) {
        $row['configured'] = (bool)$row['configured'];
        $row['id'] = (int)$row['id'];
    }

    jsonResponse(200, [
        'success'      => true,
        'dictionaries' => $rows,
        'total'        => count($rows),
    ]);
}

function getDictionaryStatus(int $dictionaryId): void {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('
        SELECT d.id, d.dictionary_name, d.dictionary_label,
               (s.dictionary_id IS NOT NULL) AS configured,
               s.host_name, s.schema_name, s.schema_user_name
        FROM cspro_dictionaries d
        LEFT JOIN cspro_dictionaries_schema s ON d.id = s.dictionary_id
        WHERE d.id = ?
    ');
    $stmt->execute([$dictionaryId]);
    $row = $stmt->fetch();

    if (!$row) {
        jsonResponse(404, [
            'success' => false,
            'error'   => 'Dictionary not found with id: ' . $dictionaryId,
        ]);
    }

    $row['configured'] = (bool)$row['configured'];
    $row['id'] = (int)$row['id'];

    jsonResponse(200, [
        'success'    => true,
        'dictionary' => $row,
    ]);
}

// ---- POST handlers ----
function handlePost(): void {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!$body || empty($body['action'])) {
        jsonResponse(400, [
            'success' => false,
            'error'   => 'Missing "action" in request body. Use: register, unregister',
        ]);
    }

    $action = $body['action'];

    if ($action === 'register') {
        registerSchema($body);
    } elseif ($action === 'unregister') {
        unregisterSchema($body);
    } else {
        jsonResponse(400, [
            'success' => false,
            'error'   => 'Invalid action: ' . $action . '. Use: register, unregister',
        ]);
    }
}

function registerSchema(array $body): void {
    // Validate required fields
    $required = ['dictionary_id', 'host_name', 'schema_name', 'schema_user_name', 'schema_password'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            jsonResponse(400, [
                'success' => false,
                'error'   => 'Missing required field: ' . $field,
            ]);
        }
    }

    $dictionaryId = (int)$body['dictionary_id'];
    if ($dictionaryId < 1) {
        jsonResponse(400, [
            'success' => false,
            'error'   => '"dictionary_id" must be a positive integer',
        ]);
    }

    $pdo = getDbConnection();

    // Verify dictionary exists
    $check = $pdo->prepare('SELECT id FROM cspro_dictionaries WHERE id = ?');
    $check->execute([$dictionaryId]);
    if (!$check->fetch()) {
        jsonResponse(404, [
            'success' => false,
            'error'   => 'Dictionary not found with id: ' . $dictionaryId,
        ]);
    }

    $stmt = $pdo->prepare('
        INSERT INTO cspro_dictionaries_schema
            (dictionary_id, host_name, schema_name, schema_user_name, schema_password, modified_time, created_time)
        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            host_name = VALUES(host_name),
            schema_name = VALUES(schema_name),
            schema_user_name = VALUES(schema_user_name),
            schema_password = VALUES(schema_password),
            modified_time = NOW()
    ');
    $stmt->execute([
        $dictionaryId,
        $body['host_name'],
        $body['schema_name'],
        $body['schema_user_name'],
        $body['schema_password'],
    ]);

    jsonResponse(200, [
        'success'       => true,
        'message'       => 'Schema registered for dictionary_id=' . $dictionaryId,
        'dictionary_id' => $dictionaryId,
    ]);
}

function unregisterSchema(array $body): void {
    if (empty($body['dictionary_id'])) {
        jsonResponse(400, [
            'success' => false,
            'error'   => 'Missing required field: dictionary_id',
        ]);
    }

    $dictionaryId = (int)$body['dictionary_id'];
    if ($dictionaryId < 1) {
        jsonResponse(400, [
            'success' => false,
            'error'   => '"dictionary_id" must be a positive integer',
        ]);
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare('DELETE FROM cspro_dictionaries_schema WHERE dictionary_id = ?');
    $stmt->execute([$dictionaryId]);

    $deleted = $stmt->rowCount() > 0;

    jsonResponse(200, [
        'success'       => true,
        'deleted'       => $deleted,
        'message'       => $deleted
            ? 'Schema unregistered for dictionary_id=' . $dictionaryId
            : 'No schema was configured for dictionary_id=' . $dictionaryId,
        'dictionary_id' => $dictionaryId,
    ]);
}
