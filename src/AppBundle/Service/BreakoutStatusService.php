<?php

namespace AppBundle\Service;

use PDO;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Centralised breakout status / dashboard data source.
 *
 * Backs both the public webhook (breakout-status-webhook.php) and the
 * internal dashboard controller. Single source of truth for:
 *  - per-dictionary status list (paginated, filtered)
 *  - global aggregate summary
 *  - top-problems lists (errors / pending / never-run)
 *
 * Performance notes:
 *  - Target-DB PDO connections are cached for the lifetime of one service
 *    instance, keyed on host|port|db_type|schema, so 100 dicts pointing to
 *    the same target schema reuse a single connection.
 */
class BreakoutStatusService {

    /** @var array<string, ?PDO> */
    private array $targetConnections = [];

    /** Per-instance memoization to avoid repeating COUNTs within one request. */
    private array $memoSourceCount    = [];
    private array $memoProcessedCount = [];

    private const TTL_SUMMARY      = 60;
    private const TTL_DICTIONARIES = 30;
    /** Above this threshold, source COUNT(*) is replaced by INFORMATION_SCHEMA estimate. */
    private const SOURCE_COUNT_EXACT_LIMIT = 5_000_000;

    public function __construct(
        private PdoHelper $pdo,
        private LoggerInterface $logger,
        private ?CacheInterface $cache = null
    ) {
    }

    /**
     * Paginated, filtered list of per-dictionary status rows.
     *
     * @param array{dictionary?:string, dictionaries?:string[], cron_enabled?:bool, breakout_configured?:bool} $filters
     * @return array{total:int, page:int, pages:int, limit:int, data:array<int, array<string,mixed>>}
     */
    public function getStatusList(array $filters = [], int $page = 1, int $limit = 20, bool $bypassCache = false): array {
        $page  = max(1, $page);
        $limit = min(100, max(1, $limit));

        if ($this->cache && !$bypassCache) {
            $key = 'breakout_dash_list_' . md5(json_encode([$filters, $page, $limit]));
            return $this->cache->get($key, function (ItemInterface $item) use ($filters, $page, $limit) {
                $item->expiresAfter(self::TTL_DICTIONARIES);
                return $this->computeStatusList($filters, $page, $limit);
            });
        }
        return $this->computeStatusList($filters, $page, $limit);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{total:int, page:int, pages:int, limit:int, data:array<int, array<string,mixed>>}
     */
    private function computeStatusList(array $filters, int $page, int $limit): array {
        $offset = ($page - 1) * $limit;

        [$whereClause, $params] = $this->buildWhereClause($filters);

        // Count total
        $countSql = "
            SELECT COUNT(*) FROM cspro_dictionaries d
            LEFT JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
            LEFT JOIN cspro_breakout_scheduler sch ON sch.dictionary_id = d.id
            $whereClause
        ";
        $countStmt = $this->pdo->prepare($countSql);
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
        $stmt = $this->pdo->prepare($dataSql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->buildStatusEntry($row);
        }

        return [
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
            'limit' => $limit,
            'data'  => $data,
        ];
    }

    /**
     * Global KPIs across all dictionaries.
     *
     * @return array{
     *   dictionaries_total:int, dictionaries_configured:int,
     *   total_cases:int, processed_cases:int,
     *   completion_rate:?float,
     *   pending_cases:int, dicts_with_pending:int,
     *   dicts_in_error:int, dicts_running:int, dicts_never_run:int,
     *   last_activity:?string
     * }
     */
    public function getGlobalSummary(bool $bypassCache = false): array {
        if ($this->cache && !$bypassCache) {
            return $this->cache->get('breakout_dash_summary', function (ItemInterface $item) {
                $item->expiresAfter(self::TTL_SUMMARY);
                return $this->computeGlobalSummary();
            });
        }
        return $this->computeGlobalSummary();
    }

    /**
     * @return array<string,mixed>
     */
    private function computeGlobalSummary(): array {
        $sql = "
            SELECT
                COUNT(*)                                              AS dicts_total,
                SUM(CASE WHEN s.dictionary_id IS NOT NULL THEN 1 ELSE 0 END) AS dicts_configured,
                SUM(CASE WHEN sch.last_exit_code IS NOT NULL AND sch.last_exit_code <> 0 AND sch.last_exit_code <> -1 THEN 1 ELSE 0 END) AS dicts_in_error,
                SUM(CASE WHEN sch.last_exit_code = -1 THEN 1 ELSE 0 END)     AS dicts_running,
                SUM(CASE WHEN s.dictionary_id IS NOT NULL AND sch.last_run IS NULL THEN 1 ELSE 0 END) AS dicts_never_run,
                MAX(sch.last_run) AS last_activity
            FROM cspro_dictionaries d
            LEFT JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
            LEFT JOIN cspro_breakout_scheduler sch ON sch.dictionary_id = d.id
        ";
        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

        // total/processed cases require iterating per-dict (different DBs/tables) — but we
        // already do this work inside getStatusList; here we issue lightweight COUNTs only.
        // For dashboards, we accept that this aggregate scans all dicts (cached upstream).
        $totalCases = 0;
        $processedCases = 0;
        $pendingCases = 0;
        $dictsWithPending = 0;

        $allRows = $this->pdo->query("
            SELECT
                d.dictionary_name,
                (s.dictionary_id IS NOT NULL) AS configured,
                s.host_name, s.port, s.db_type, s.schema_name, s.schema_user_name,
                AES_DECRYPT(s.schema_password, 'cspro') AS schema_password_plain
            FROM cspro_dictionaries d
            LEFT JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allRows as $r) {
            $tot = $this->countSourceCases($r['dictionary_name']);
            $totalCases += $tot;
            if ($r['configured'] && $r['schema_password_plain']) {
                $proc = $this->countProcessedCases($r);
                if ($proc !== null) {
                    $processedCases += $proc;
                    $pending = max(0, $tot - $proc);
                    $pendingCases += $pending;
                    if ($pending > 0) {
                        $dictsWithPending++;
                    }
                }
            }
        }

        $completionRate = $totalCases > 0
            ? round($processedCases / $totalCases * 100, 2)
            : null;

        return [
            'dictionaries_total'      => (int) $row['dicts_total'],
            'dictionaries_configured' => (int) $row['dicts_configured'],
            'total_cases'             => $totalCases,
            'processed_cases'         => $processedCases,
            'completion_rate'         => $completionRate,
            'pending_cases'           => $pendingCases,
            'dicts_with_pending'      => $dictsWithPending,
            'dicts_in_error'          => (int) $row['dicts_in_error'],
            'dicts_running'           => (int) $row['dicts_running'],
            'dicts_never_run'         => (int) $row['dicts_never_run'],
            'last_activity'           => $row['last_activity'],
        ];
    }

    /**
     * Three short lists for the "Top problems" sidebar:
     *   - errors    : dicts with last_exit_code != 0 (and != -1 running), most recent first
     *   - pending   : dicts with the largest gap between source and target counts
     *   - neverRun  : configured dicts that have never run
     *
     * @return array{errors:array<int,array>, pending:array<int,array>, neverRun:array<int,array>}
     */
    public function getTopProblems(int $limit = 5, bool $bypassCache = false): array {
        if ($this->cache && !$bypassCache) {
            $key = 'breakout_dash_problems_' . $limit;
            return $this->cache->get($key, function (ItemInterface $item) use ($limit) {
                $item->expiresAfter(self::TTL_SUMMARY);
                return $this->computeTopProblems($limit);
            });
        }
        return $this->computeTopProblems($limit);
    }

    /**
     * @return array{errors:array<int,array>, pending:array<int,array>, neverRun:array<int,array>}
     */
    private function computeTopProblems(int $limit): array {
        $errorsSql = "
            SELECT d.dictionary_name, d.dictionary_label, sch.last_run, sch.last_exit_code
            FROM cspro_dictionaries d
            JOIN cspro_breakout_scheduler sch ON sch.dictionary_id = d.id
            WHERE sch.last_exit_code IS NOT NULL
              AND sch.last_exit_code <> 0
              AND sch.last_exit_code <> -1
            ORDER BY sch.last_run DESC
            LIMIT :lim
        ";
        $st = $this->pdo->prepare($errorsSql);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        $errors = $st->fetchAll(PDO::FETCH_ASSOC);

        $neverRunSql = "
            SELECT d.dictionary_name, d.dictionary_label
            FROM cspro_dictionaries d
            JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
            LEFT JOIN cspro_breakout_scheduler sch ON sch.dictionary_id = d.id
            WHERE sch.last_run IS NULL
            ORDER BY d.dictionary_name
            LIMIT :lim
        ";
        $st = $this->pdo->prepare($neverRunSql);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        $neverRun = $st->fetchAll(PDO::FETCH_ASSOC);

        // Pending requires per-target counts — reuse buildStatusEntry for top N candidates only.
        // Strategy: pull all configured dicts (typically small number compared to cases), score them,
        // keep top N. For huge dict catalogs (>500), upgrade to a persisted snapshot table later.
        $candidatesSql = "
            SELECT
                d.dictionary_name, d.dictionary_label,
                s.host_name, s.port, s.db_type, s.schema_name, s.schema_user_name,
                AES_DECRYPT(s.schema_password, 'cspro') AS schema_password_plain
            FROM cspro_dictionaries d
            JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
        ";
        $candidates = $this->pdo->query($candidatesSql)->fetchAll(PDO::FETCH_ASSOC);

        $scored = [];
        foreach ($candidates as $c) {
            $tot = $this->countSourceCases($c['dictionary_name']);
            $proc = $c['schema_password_plain'] ? $this->countProcessedCases($c) : null;
            if ($proc === null) continue;
            $pending = max(0, $tot - $proc);
            if ($pending <= 0) continue;
            $scored[] = [
                'dictionary_name'  => $c['dictionary_name'],
                'dictionary_label' => $c['dictionary_label'],
                'cases_pending'    => $pending,
                'total_cases'      => $tot,
                'processed_cases'  => $proc,
            ];
        }
        usort($scored, fn($a, $b) => $b['cases_pending'] <=> $a['cases_pending']);
        $pending = array_slice($scored, 0, $limit);

        return [
            'errors'   => $errors,
            'pending'  => $pending,
            'neverRun' => $neverRun,
        ];
    }

    /**
     * @param array{dictionary?:string, dictionaries?:string[], cron_enabled?:bool, breakout_configured?:bool} $filters
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function buildWhereClause(array $filters): array {
        $where  = [];
        $params = [];

        $dictSingle = isset($filters['dictionary']) ? trim((string) $filters['dictionary']) : '';
        $dictMany   = $filters['dictionaries'] ?? [];

        if ($dictSingle !== '') {
            $where[] = 'd.dictionary_name = :dict_single';
            $params[':dict_single'] = strtoupper($dictSingle);
        } elseif (!empty($dictMany)) {
            $placeholders = [];
            foreach (array_values($dictMany) as $i => $name) {
                $key = ':dict_' . $i;
                $placeholders[] = $key;
                $params[$key]   = strtoupper(trim($name));
            }
            $where[] = 'd.dictionary_name IN (' . implode(',', $placeholders) . ')';
        }

        if (array_key_exists('breakout_configured', $filters) && $filters['breakout_configured'] !== null) {
            $where[] = $filters['breakout_configured']
                ? 's.dictionary_id IS NOT NULL'
                : 's.dictionary_id IS NULL';
        }
        if (array_key_exists('cron_enabled', $filters) && $filters['cron_enabled'] !== null) {
            $where[] = $filters['cron_enabled']
                ? 'sch.enabled = 1'
                : '(sch.enabled IS NULL OR sch.enabled = 0)';
        }

        return [
            $where ? 'WHERE ' . implode(' AND ', $where) : '',
            $params,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function buildStatusEntry(array $row): array {
        $dictName   = $row['dictionary_name'];
        $configured = (bool) $row['breakout_configured'];

        $totalCases        = $this->countSourceCases($dictName);
        $processedCases    = null;
        $lastProcessedTime = null;

        if ($configured && $row['schema_password_plain']) {
            $processedCases    = $this->countProcessedCases($row);
            $lastProcessedTime = $this->lastJobTime($row);
        }

        $casesPending   = ($processedCases !== null) ? max(0, $totalCases - $processedCases) : null;
        $completionRate = ($processedCases !== null && $totalCases > 0)
            ? round($processedCases / $totalCases * 100, 2)
            : ($totalCases === 0 && $processedCases !== null ? 100.0 : null);
        $isUpToDate     = ($processedCases !== null) ? ($casesPending === 0) : null;

        return [
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

    /**
     * Reject anything that is not a strict CSPro dictionary identifier.
     * Source dictionary names live in cspro_dictionaries.dictionary_name and
     * are constrained by upstream code, but we re-check here so this service
     * never interpolates an unsafe value into a SQL identifier.
     */
    private static function isSafeDictName(string $name): bool {
        return (bool) preg_match('/^[A-Z0-9_]{1,64}$/', $name);
    }

    private function countSourceCases(string $dictName): int {
        if (array_key_exists($dictName, $this->memoSourceCount)) {
            return $this->memoSourceCount[$dictName];
        }
        if (!self::isSafeDictName($dictName)) {
            $this->memoSourceCount[$dictName] = 0;
            return 0;
        }
        try {
            // $dictName is alphanumeric/underscore only — safe to interpolate as
            // a backtick-quoted identifier. We still wrap defensively.
            $safe = '`' . str_replace('`', '``', $dictName) . '`';
            // Cheap estimate first via INFORMATION_SCHEMA — avoids COUNT(*) on very large tables.
            $stmt = $this->pdo->prepare(
                "SELECT IFNULL(TABLE_ROWS, 0) FROM INFORMATION_SCHEMA.TABLES "
                . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t"
            );
            $stmt->execute([':t' => $dictName]);
            $estimate = (int) $stmt->fetchColumn();
            if ($estimate > self::SOURCE_COUNT_EXACT_LIMIT) {
                $this->memoSourceCount[$dictName] = $estimate;
                return $estimate;
            }
            $exact = (int) $this->pdo->query("SELECT COUNT(*) FROM $safe WHERE `deleted` = 0")->fetchColumn();
            $this->memoSourceCount[$dictName] = $exact;
            return $exact;
        } catch (\Throwable $e) {
            $this->memoSourceCount[$dictName] = 0;
            return 0;
        }
    }

    /** @param array<string,mixed> $row */
    private function countProcessedCases(array $row): ?int {
        $key = (string) ($row['dictionary_name'] ?? '');
        if (array_key_exists($key, $this->memoProcessedCount)) {
            return $this->memoProcessedCount[$key];
        }
        if (!self::isSafeDictName($key)) {
            $this->memoProcessedCount[$key] = null;
            return null;
        }
        $conn = $this->getTargetConnection($row);
        if (!$conn) {
            $this->memoProcessedCount[$key] = null;
            return null;
        }
        $prefix = $this->tablePrefix($key);
        if ($prefix === null) {
            $this->memoProcessedCount[$key] = null;
            return null;
        }
        try {
            $val = (int) $conn->query("SELECT COUNT(*) FROM {$prefix}cases WHERE deleted = 0")->fetchColumn();
            $this->memoProcessedCount[$key] = $val;
            return $val;
        } catch (\Throwable $e) {
            $this->memoProcessedCount[$key] = null;
            return null;
        }
    }

    /** @param array<string,mixed> $row */
    private function lastJobTime(array $row): ?string {
        $key = (string) ($row['dictionary_name'] ?? '');
        if (!self::isSafeDictName($key)) {
            return null;
        }
        $conn = $this->getTargetConnection($row);
        if (!$conn) return null;
        $prefix = $this->tablePrefix($key);
        if ($prefix === null) return null;
        try {
            $jobRow = $conn->query(
                "SELECT modified_time FROM {$prefix}cspro_jobs WHERE id = (SELECT MAX(id) FROM {$prefix}cspro_jobs WHERE status = 2)"
            )->fetch(PDO::FETCH_ASSOC);
            return $jobRow['modified_time'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build the per-dict table prefix used by the breakout target schema.
     * Returns null if $dictName is not a safe identifier (caller MUST treat
     * a null prefix as "skip this dict"). Strips a trailing _DICT only —
     * the previous implementation also stripped the substring in the middle
     * (e.g. MY_DICTIONARY → MYIONARY).
     */
    private function tablePrefix(string $dictName): ?string {
        if (!self::isSafeDictName($dictName)) {
            return null;
        }
        return strtolower(preg_replace('/_DICT$/', '', $dictName)) . '_';
    }

    /** @param array<string,mixed> $row */
    private function getTargetConnection(array $row): ?PDO {
        // Resolve effective host/port based on BREAKOUT_CONNECTION_MODE
        // (direct = use the configured host_name as-is, tunnel = rewrite to
        // 127.0.0.1:BREAKOUT_TUNNEL_LOCAL_PORT).
        $resolved = BreakoutConnectionResolver::resolve($row);
        $effHost = $resolved['host'];
        $effPort = $resolved['port'];

        $key = $effHost . '|' . $effPort . '|' . ($row['db_type'] ?? '') . '|' . ($row['schema_name'] ?? '');
        if (array_key_exists($key, $this->targetConnections)) {
            return $this->targetConnections[$key];
        }
        try {
            $driver = match (strtolower($row['db_type'] ?? 'postgresql')) {
                'mysql'     => 'mysql',
                'sqlserver' => 'sqlsrv',
                default     => 'pgsql',
            };
            if ($driver === 'mysql') {
                $dsn = 'mysql:host=' . $effHost . ($effPort ? ';port=' . $effPort : '') . ';dbname=' . $row['schema_name'] . ';charset=utf8mb4';
            } elseif ($driver === 'sqlsrv') {
                $dsn = 'sqlsrv:Server=' . $effHost . ($effPort ? ',' . $effPort : '') . ';Database=' . $row['schema_name'];
            } else {
                $dsn = 'pgsql:host=' . $effHost . ($effPort ? ';port=' . $effPort : '') . ';dbname=' . $row['schema_name'];
            }
            $conn = new PDO($dsn, $row['schema_user_name'], $row['schema_password_plain'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->targetConnections[$key] = $conn;
            return $conn;
        } catch (\Throwable $e) {
            $this->logger->warning('Target DB unreachable for ' . ($row['dictionary_name'] ?? '?') . ': ' . $e->getMessage());
            $this->targetConnections[$key] = null;
            return null;
        }
    }
}
