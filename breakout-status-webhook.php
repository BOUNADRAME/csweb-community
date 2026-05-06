<?php
/**
 * CSPro Breakout Status Webhook
 *
 * Returns breakout status for one or more dictionaries:
 *   - breakout configured (yes/no)
 *   - cron enabled + expression
 *   - total cases (source DB)
 *   - processed cases (target DB)
 *   - cases pending
 *   - completion rate (%)
 *   - is_up_to_date
 *   - last_processed_time, last_run, next_run
 *
 * Environment:
 *   - BREAKOUT_WEBHOOK_TOKEN: shared secret token (required)
 *
 * Usage:
 *   GET /breakout-status-webhook.php
 *   GET /breakout-status-webhook.php?dictionary=KATS_DICT
 *   GET /breakout-status-webhook.php?dictionaries=KATS_DICT,EVAL_DICT
 *   GET /breakout-status-webhook.php?cron_enabled=true
 *   GET /breakout-status-webhook.php?breakout_configured=true
 *   GET /breakout-status-webhook.php?page=1&limit=20
 *   Authorization: Bearer <token>
 *
 * Response shape (uniform across all webhooks):
 *   { success, data, error, meta? }
 */

require_once __DIR__ . '/webhook_helper.php';

requireMethod(['GET']);
requireBearerToken();

// Load CSWeb DB config
$configFile = __DIR__ . '/src/AppBundle/config.php';
if (!file_exists($configFile)) {
    respondError('server_misconfigured', 'CSWeb config.php not found at: ' . $configFile, 500);
}
require_once $configFile;

// ---- Helpers ----

function getDbConnection(): PDO {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DBHOST, DBNAME);
    return new PDO($dsn, DBUSER, DBPASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Resolve effective host/port for the breakout target based on
 * BREAKOUT_CONNECTION_MODE. Mirrors BreakoutConnectionResolver::resolve()
 * but kept inline here to keep the webhook standalone (no Symfony bootstrap).
 *
 * @param array<string,mixed> $row
 * @return array{host:string, port:?int}
 */
function resolveTargetEndpoint(array $row): array {
    $mode = strtolower((string) (getenv('BREAKOUT_CONNECTION_MODE') ?: 'direct'));
    if ($mode === 'tunnel') {
        return [
            'host' => '127.0.0.1',
            'port' => (int) (getenv('BREAKOUT_TUNNEL_LOCAL_PORT') ?: 13306),
        ];
    }
    $port = $row['port'] ?? null;
    return [
        'host' => (string) ($row['host_name'] ?? ''),
        'port' => $port !== null && $port !== '' ? (int) $port : null,
    ];
}

/** @param array<string,mixed> $row */
function getTargetDbConnection(array $row): ?PDO {
    try {
        $driver = match (strtolower($row['db_type'] ?? 'postgresql')) {
            'mysql'     => 'mysql',
            'sqlserver' => 'sqlsrv',
            default     => 'pgsql',
        };
        ['host' => $effHost, 'port' => $effPort] = resolveTargetEndpoint($row);
        if ($driver === 'mysql') {
            $dsn = 'mysql:host=' . $effHost . ($effPort ? ';port=' . $effPort : '') . ';dbname=' . $row['schema_name'] . ';charset=utf8mb4';
        } elseif ($driver === 'sqlsrv') {
            $dsn = 'sqlsrv:Server=' . $effHost . ($effPort ? ',' . $effPort : '') . ';Database=' . $row['schema_name'];
        } else {
            $dsn = 'pgsql:host=' . $effHost . ($effPort ? ';port=' . $effPort : '') . ';dbname=' . $row['schema_name'];
        }
        return new PDO($dsn, $row['schema_user_name'], $row['schema_password_plain'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (\Exception $e) {
        return null;
    }
}

// ---- Parse query params ----
$page  = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$filterDictionary  = trim($_GET['dictionary'] ?? '');
// Cap at 100 entries to bound the SQL IN(...) clause.
$filterDictionaries = array_slice(
    array_filter(array_map('trim', explode(',', $_GET['dictionaries'] ?? ''))),
    0,
    100
);
$filterCronEnabled = isset($_GET['cron_enabled']) ? ($_GET['cron_enabled'] === 'true') : null;
$filterConfigured  = isset($_GET['breakout_configured']) ? ($_GET['breakout_configured'] === 'true') : null;

// ---- Build query ----
try {
    $pdo = getDbConnection();
} catch (\Throwable $e) {
    respondError('internal_error', 'Database connection failed.', 500);
}

$where  = [];
$params = [];

if ($filterDictionary !== '') {
    $where[] = 'd.dictionary_name = :dict_single';
    $params[':dict_single'] = strtoupper($filterDictionary);
} elseif (!empty($filterDictionaries)) {
    $placeholders = implode(',', array_map(fn($i) => ':dict_' . $i, array_keys($filterDictionaries)));
    $where[] = 'd.dictionary_name IN (' . $placeholders . ')';
    foreach (array_values($filterDictionaries) as $i => $name) {
        $params[':dict_' . $i] = strtoupper($name);
    }
}

if ($filterConfigured === true) {
    $where[] = 's.dictionary_id IS NOT NULL';
} elseif ($filterConfigured === false) {
    $where[] = 's.dictionary_id IS NULL';
}

if ($filterCronEnabled === true) {
    $where[] = 'sch.enabled = 1';
} elseif ($filterCronEnabled === false) {
    $where[] = '(sch.enabled IS NULL OR sch.enabled = 0)';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Count total
    $countSql = "
        SELECT COUNT(*) FROM cspro_dictionaries d
        LEFT JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
        LEFT JOIN cspro_breakout_scheduler sch ON sch.dictionary_id = d.id
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $pages = (int) ceil($total / $limit);

    // Fetch rows
    $dataSql = "
        SELECT
            d.id,
            d.dictionary_name,
            d.dictionary_label,
            (s.dictionary_id IS NOT NULL)     AS breakout_configured,
            s.host_name,
            s.port,
            s.db_type,
            s.schema_name,
            s.schema_user_name,
            AES_DECRYPT(s.schema_password, 'cspro') AS schema_password_plain,
            COALESCE(sch.enabled, 0)          AS cron_enabled,
            sch.cron_expression,
            sch.last_run,
            sch.next_run,
            sch.last_exit_code
        FROM cspro_dictionaries d
        LEFT JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
        LEFT JOIN cspro_breakout_scheduler sch ON sch.dictionary_id = d.id
        $whereClause
        ORDER BY d.dictionary_name
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($dataSql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
} catch (\Throwable $e) {
    respondError('internal_error', 'Failed to query dictionaries.', 500);
}

// ---- Build response ----
$data = [];

foreach ($rows as $row) {
    $dictName   = $row['dictionary_name'];
    $configured = (bool) $row['breakout_configured'];

    $totalCases = 0;
    try {
        $src = $pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $dictName) . "` WHERE `deleted` = 0");
        $totalCases = (int) $src->fetchColumn();
    } catch (\Exception $e) {
        // Source table may not exist yet — keep totalCases at 0.
    }

    $processedCases    = null;
    $lastProcessedTime = null;

    if ($configured && $row['schema_password_plain']) {
        $tablePrefix = strtolower(str_replace(' ', '_', str_replace('_DICT', '', $dictName))) . '_';
        $targetConn  = getTargetDbConnection($row);
        if ($targetConn) {
            try {
                $processedCases = (int) $targetConn->query("SELECT COUNT(*) FROM {$tablePrefix}cases WHERE deleted = 0")->fetchColumn();
            } catch (\Exception $e) {
                $processedCases = null;
            }
            try {
                $jobStmt = $targetConn->query("SELECT modified_time FROM {$tablePrefix}cspro_jobs WHERE id = (SELECT MAX(id) FROM {$tablePrefix}cspro_jobs WHERE status = 2)");
                $jobRow  = $jobStmt->fetch();
                if ($jobRow) {
                    $lastProcessedTime = $jobRow['modified_time'];
                }
            } catch (\Exception $e) {
                $lastProcessedTime = null;
            }
        }
    }

    $casesPending    = ($processedCases !== null) ? max(0, $totalCases - $processedCases) : null;
    $completionRate  = ($processedCases !== null && $totalCases > 0)
        ? round($processedCases / $totalCases * 100, 2)
        : ($totalCases === 0 && $processedCases !== null ? 100.0 : null);
    $isUpToDate      = ($processedCases !== null) ? ($casesPending === 0) : null;

    $data[] = [
        'dictionary'           => $dictName,
        'label'                => $row['dictionary_label'],
        'breakout_configured'  => $configured,
        'target_host'          => $configured ? ($row['host_name'] . ($row['port'] ? ':' . $row['port'] : '')) : null,
        'target_schema'        => $configured ? $row['schema_name'] : null,
        'db_type'              => $configured ? ($row['db_type'] ?? 'postgresql') : null,
        'cron_enabled'         => (bool) $row['cron_enabled'],
        'cron_expression'      => $row['cron_expression'],
        'last_run'             => $row['last_run'],
        'next_run'             => $row['next_run'],
        'last_exit_code'       => $row['last_exit_code'] !== null ? (int) $row['last_exit_code'] : null,
        'total_cases'          => $totalCases,
        'processed_cases'      => $processedCases,
        'cases_pending'        => $casesPending,
        'completion_rate'      => $completionRate,
        'is_up_to_date'        => $isUpToDate,
        'last_processed_time'  => $lastProcessedTime,
    ];
}

respondSuccess($data, [
    'total' => $total,
    'page'  => $page,
    'pages' => $pages,
    'limit' => $limit,
]);
