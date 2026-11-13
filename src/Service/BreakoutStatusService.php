<?php

namespace App\Service;

use PDO;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
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
 *  - A target that cannot be reached never blocks the dashboard: the connect
 *    attempt is capped at TARGET_CONNECT_TIMEOUT seconds, the failure is
 *    remembered for TARGET_DOWN_TTL seconds across requests, and every entry
 *    reports it (target_status = "unreachable" + target_error) instead of
 *    silently showing 0 processed cases.
 */
class BreakoutStatusService {

    /** @var array<string, ?PDO> */
    private array $targetConnections = [];

    /** @var array<string, string> connection key => short reason the target could not be reached */
    private array $targetErrors = [];

    /** Set by a fresh (nocache) load: retry targets even if recently found down. */
    private bool $retryDownTargets = false;

    /** Per-instance memoization to avoid repeating COUNTs within one request. */
    private array $memoSourceCount    = [];
    private array $memoProcessedCount = [];

    private const TTL_SUMMARY      = 60;
    private const TTL_DICTIONARIES = 30;
    /** Above this threshold, source COUNT(*) is replaced by INFORMATION_SCHEMA estimate. */
    private const SOURCE_COUNT_EXACT_LIMIT = 5_000_000;
    /** Seconds to wait for a breakout target before reporting it unreachable. */
    private const TARGET_CONNECT_TIMEOUT = 3;
    /** Seconds an unreachable target is remembered, so the next loads answer at once. */
    private const TARGET_DOWN_TTL = 60;

    public const TARGET_OK             = 'ok';
    public const TARGET_UNREACHABLE    = 'unreachable';
    public const TARGET_INCOMPLETE     = 'incomplete';
    public const TARGET_NOT_CONFIGURED = 'not_configured';

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

        $this->retryDownTargets = $this->retryDownTargets || $bypassCache;
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

        // Community layer: when the user filters (search, status, cron), the
        // status is computed per dictionary, so filter and paginate after
        // building the entries, and return KPIs for the filtered set: the
        // cards then match the table instead of always showing the totals.
        $status = isset($filters['status']) ? (string) $filters['status'] : 'all';
        $filtering = $status !== 'all'
            || (isset($filters['search']) && trim((string) $filters['search']) !== '')
            || (array_key_exists('cron_enabled', $filters) && $filters['cron_enabled'] !== null);
        if ($filtering) {
            return $this->computeFilteredStatusList($filters, $status, $page, $limit);
        }

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
                d.name AS dictionary_name,
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
            ORDER BY d.name
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
        $this->retryDownTargets = $this->retryDownTargets || $bypassCache;
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
        $dictsUnreachable = 0;
        // Completion is measured on the dictionaries whose target answered:
        // unconfigured or unreachable dictionaries would otherwise drag the
        // rate down to a misleading 0 %.
        $measuredCases = 0;

        $allRows = $this->pdo->query("
            SELECT
                d.name AS dictionary_name,
                (s.dictionary_id IS NOT NULL) AS configured,
                s.host_name, s.port, s.db_type, s.schema_name, s.schema_user_name,
                AES_DECRYPT(s.schema_password, 'cspro') AS schema_password_plain,
                sch.last_run
            FROM cspro_dictionaries d
            LEFT JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
            LEFT JOIN cspro_breakout_scheduler sch ON sch.dictionary_id = d.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        // Community layer: "never run" and "last activity" also count breakouts
        // started outside the scheduler (CLI, webhook), read from the target.
        $neverRunCount = 0;
        $lastActivity = $row['last_activity'];

        foreach ($allRows as $r) {
            $tot = $this->countSourceCases($r['dictionary_name']);
            $totalCases += $tot;
            if ($r['configured']) {
                $jobTime = $r['schema_password_plain'] ? $this->lastJobTime($r) : null;
                if ($r['last_run'] === null && $jobTime === null) {
                    $neverRunCount++;
                }
                $lastActivity = self::latest($lastActivity, $jobTime);
            }
            if ($r['configured'] && $r['schema_password_plain']) {
                $proc = $this->countProcessedCases($r);
                if ($proc !== null) {
                    $processedCases += $proc;
                    $measuredCases  += $tot;
                    $pending = max(0, $tot - $proc);
                    $pendingCases += $pending;
                    if ($pending > 0) {
                        $dictsWithPending++;
                    }
                } elseif ($this->targetError($r) !== null) {
                    $dictsUnreachable++;
                }
            }
        }

        $completionRate = $measuredCases > 0
            ? round(min($processedCases, $measuredCases) / $measuredCases * 100, 2)
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
            'dicts_never_run'         => $neverRunCount,
            'dicts_unreachable'       => $dictsUnreachable,
            'last_activity'           => $lastActivity,
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
        $this->retryDownTargets = $this->retryDownTargets || $bypassCache;
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
            SELECT d.name AS dictionary_name, d.dictionary_label, sch.last_run, sch.last_exit_code
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
            SELECT d.name AS dictionary_name, d.dictionary_label,
                   s.host_name, s.port, s.db_type, s.schema_name, s.schema_user_name,
                   AES_DECRYPT(s.schema_password, 'cspro') AS schema_password_plain
            FROM cspro_dictionaries d
            JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
            LEFT JOIN cspro_breakout_scheduler sch ON sch.dictionary_id = d.id
            WHERE sch.last_run IS NULL
            ORDER BY d.name
        ";
        $neverRun = [];
        foreach ($this->pdo->query($neverRunSql)->fetchAll(PDO::FETCH_ASSOC) as $nr) {
            // Community layer: skip dictionaries already processed from the CLI
            // or a webhook (they have completed jobs in the target).
            if ($nr['schema_password_plain'] && $this->lastJobTime($nr) !== null) {
                continue;
            }
            $neverRun[] = ['dictionary_name' => $nr['dictionary_name'], 'dictionary_label' => $nr['dictionary_label']];
            if (count($neverRun) >= $limit) {
                break;
            }
        }

        // Pending requires per-target counts — reuse buildStatusEntry for top N candidates only.
        // Strategy: pull all configured dicts (typically small number compared to cases), score them,
        // keep top N. For huge dict catalogs (>500), upgrade to a persisted snapshot table later.
        $candidatesSql = "
            SELECT
                d.name AS dictionary_name, d.dictionary_label,
                s.host_name, s.port, s.db_type, s.schema_name, s.schema_user_name,
                AES_DECRYPT(s.schema_password, 'cspro') AS schema_password_plain
            FROM cspro_dictionaries d
            JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
        ";
        $candidates = $this->pdo->query($candidatesSql)->fetchAll(PDO::FETCH_ASSOC);

        $scored = [];
        $unreachable = [];
        foreach ($candidates as $c) {
            $tot = $this->countSourceCases($c['dictionary_name']);
            $proc = $c['schema_password_plain'] ? $this->countProcessedCases($c) : null;
            if ($proc === null) {
                $err = $this->targetError($c);
                if ($err !== null && count($unreachable) < $limit) {
                    $unreachable[] = [
                        'dictionary_name'  => $c['dictionary_name'],
                        'dictionary_label' => $c['dictionary_label'],
                        'target_host'      => $c['host_name'] . ($c['port'] ? ':' . $c['port'] : ''),
                        'target_error'     => $err,
                    ];
                }
                continue;
            }
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
            'errors'      => $errors,
            'pending'     => $pending,
            'neverRun'    => $neverRun,
            'unreachable' => $unreachable,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function computeFilteredStatusList(array $filters, string $status, int $page, int $limit): array {
        [$whereClause, $params] = $this->buildWhereClause($filters);
        $sql = "
            SELECT
                d.id,
                d.name AS dictionary_name,
                d.dictionary_label,
                (s.dictionary_id IS NOT NULL)     AS breakout_configured,
                s.host_name, s.port, s.db_type, s.schema_name, s.schema_user_name,
                AES_DECRYPT(s.schema_password, 'cspro') AS schema_password_plain,
                COALESCE(sch.enabled, 0)          AS cron_enabled,
                sch.cron_expression, sch.last_run, sch.next_run, sch.last_exit_code
            FROM cspro_dictionaries d
            LEFT JOIN cspro_dictionaries_schema s ON s.dictionary_id = d.id
            LEFT JOIN cspro_breakout_scheduler sch ON sch.dictionary_id = d.id
            $whereClause
            ORDER BY d.name
        ";
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $entries = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $entry = $this->buildStatusEntry($row);
            if ($status === 'all' || self::statusKey($entry) === $status) {
                $entries[] = $entry;
            }
        }

        $total = count($entries);
        $collected = 0; $processed = 0; $pendingCases = 0; $dictsPending = 0; $inError = 0; $configured = 0;
        $measured = 0; $unreachableCount = 0;
        foreach ($entries as $e) {
            $collected += (int) $e['total_cases'];
            if ($e['breakout_configured']) {
                $configured++;
            }
            if ($e['target_status'] === self::TARGET_UNREACHABLE) {
                $unreachableCount++;
            }
            if ($e['processed_cases'] !== null) {
                $processed += (int) $e['processed_cases'];
                $measured  += (int) $e['total_cases'];
                $pendingCases += (int) $e['cases_pending'];
                if ($e['cases_pending'] > 0) {
                    $dictsPending++;
                }
            }
            if ($e['last_exit_code'] !== null && $e['last_exit_code'] !== 0 && $e['last_exit_code'] !== -1) {
                $inError++;
            }
        }

        return [
            'total' => $total,
            'page'  => $page,
            'pages' => (int) ceil($total / $limit),
            'limit' => $limit,
            'data'  => array_slice($entries, ($page - 1) * $limit, $limit),
            'filtered_summary' => [
                'dictionaries_total'      => $total,
                'dictionaries_configured' => $configured,
                'total_cases'             => $collected,
                'processed_cases'         => $processed,
                'completion_rate'         => $measured > 0 ? round(min($processed, $measured) / $measured * 100, 2) : null,
                'pending_cases'           => $pendingCases,
                'dicts_with_pending'      => $dictsPending,
                'dicts_in_error'          => $inError,
                'dicts_unreachable'       => $unreachableCount,
            ],
        ];
    }

    /** Same rule as statusFor() in dashboard.twig. */
    public static function statusKey(array $e): string {
        if (!$e['breakout_configured']) return 'not_configured';
        if ($e['last_exit_code'] === -1) return 'running';
        if (in_array($e['target_status'] ?? null, [self::TARGET_UNREACHABLE, self::TARGET_INCOMPLETE], true)) return 'unreachable';
        if ($e['last_exit_code'] !== null && $e['last_exit_code'] !== 0) return 'error';
        if ($e['last_run'] === null) return 'never_run';
        if ($e['is_up_to_date'] === false) return 'pending';
        return 'up_to_date';
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
            $where[] = 'd.name = :dict_single';
            $params[':dict_single'] = strtoupper($dictSingle);
        } elseif (!empty($dictMany)) {
            $placeholders = [];
            foreach (array_values($dictMany) as $i => $name) {
                $key = ':dict_' . $i;
                $placeholders[] = $key;
                $params[$key]   = strtoupper(trim($name));
            }
            $where[] = 'd.name IN (' . implode(',', $placeholders) . ')';
        }

        // Community layer: free-text search on name or label, server side, so
        // it covers every page and not only the one loaded in the browser.
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $where[] = '(d.name LIKE :q1 OR d.dictionary_label LIKE :q2)';
            $params[':q1'] = $params[':q2'] = '%' . addcslashes($search, '%_\\') . '%';
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
        $lastError         = null;

        if ($configured && $row['schema_password_plain']) {
            $processedCases    = $this->countProcessedCases($row);
            $lastProcessedTime = $this->lastJobTime($row);
            $lastError         = $this->lastJobError($row);
        }

        $casesPending   = ($processedCases !== null) ? max(0, $totalCases - $processedCases) : null;
        $completionRate = ($processedCases !== null && $totalCases > 0)
            ? round($processedCases / $totalCases * 100, 2)
            : ($totalCases === 0 && $processedCases !== null ? 100.0 : null);
        $isUpToDate     = ($processedCases !== null) ? ($casesPending === 0) : null;

        $targetError  = null;
        if (!$configured) {
            $targetStatus = self::TARGET_NOT_CONFIGURED;
        } elseif (!$row['schema_password_plain']) {
            $targetStatus = self::TARGET_INCOMPLETE;
            $targetError  = 'Breakout target has no stored password: edit it in Settings > Data.';
        } elseif (($targetError = $this->targetError($row)) !== null) {
            $targetStatus = self::TARGET_UNREACHABLE;
        } else {
            $targetStatus = self::TARGET_OK;
        }

        return [
            'dictionary'           => $dictName,
            'label'                => $row['dictionary_label'],
            'breakout_configured'  => $configured,
            'target_host'          => $configured ? ($row['host_name'] . ($row['port'] ? ':' . $row['port'] : '')) : null,
            'target_schema'        => $configured ? $row['schema_name'] : null,
            'db_type'              => $configured ? ($row['db_type'] ?? 'postgresql') : null,
            'cron_enabled'         => (bool) $row['cron_enabled'],
            'cron_expression'      => $row['cron_expression'],
            // Community layer: a breakout started from the CLI or a webhook does
            // not go through the scheduler, so fall back on the last completed
            // job in the target. Otherwise such dictionaries read "never run".
            'last_run'             => self::latest($row['last_run'], $lastProcessedTime),
            'next_run'             => $row['next_run'],
            'last_exit_code'       => $row['last_exit_code'] !== null ? (int) $row['last_exit_code'] : null,
            'total_cases'          => $totalCases,
            'processed_cases'      => $processedCases,
            'cases_pending'        => $casesPending,
            'completion_rate'      => $completionRate,
            'is_up_to_date'        => $isUpToDate,
            'last_processed_time'  => $lastProcessedTime,
            // Message clair du dernier job en échec (null si aucun). Alimente le
            // badge/indicateur d'erreur de l'UI sans exposer de stack trace.
            'last_error'           => $lastError,
            // ok | unreachable | incomplete | not_configured — lets the UI say
            // WHY processed/pending are empty instead of showing a bare dash.
            'target_status'        => $targetStatus,
            'target_error'         => $targetError,
        ];
    }

    /**
     * Reject anything that is not a strict CSPro dictionary identifier.
     * Source dictionary names live in cspro_dictionaries.dictionary_name and
     * are constrained by upstream code, but we re-check here so this service
     * never interpolates an unsafe value into a SQL identifier.
     */
    /** Most recent of two 'Y-m-d H:i:s' timestamps, either of which may be null. */
    private static function latest(?string $a, ?string $b): ?string {
        if ($a === null || $a === '') return ($b === '' ? null : $b);
        if ($b === null || $b === '') return $a;
        return strtotime($b) > strtotime($a) ? $b : $a;
    }

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

    /**
     * Message d'erreur du dernier job en échec (status = 3 / FAILED), lu dans la
     * table cible <prefix>cspro_jobs. Best-effort : renvoie null si la connexion
     * échoue, si la colonne error_message n'existe pas encore (déploiement non
     * migré), ou s'il n'y a aucun job en échec.
     *
     * @param array<string,mixed> $row
     */
    private function lastJobError(array $row): ?string {
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
                "SELECT error_message FROM {$prefix}cspro_jobs WHERE id = (SELECT MAX(id) FROM {$prefix}cspro_jobs WHERE status = 3)"
            )->fetch(PDO::FETCH_ASSOC);
            $msg = $jobRow['error_message'] ?? null;
            return ($msg !== null && $msg !== '') ? (string) $msg : null;
        } catch (\Throwable $e) {
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

    /**
     * Why the target of $row could not be reached during this request, or null
     * when it answered (or was never tried).
     *
     * @param array<string,mixed> $row
     */
    private function targetError(array $row): ?string {
        $key = $this->connectionKey($row);
        return $this->targetErrors[$key] ?? null;
    }

    /** @param array<string,mixed> $row */
    private function connectionKey(array $row): string {
        $resolved = BreakoutConnectionResolver::resolve($row);
        return $resolved['host'] . '|' . $resolved['port'] . '|' . ($row['db_type'] ?? '') . '|' . ($row['schema_name'] ?? '');
    }

    /** @param array<string,mixed> $row */
    private function getTargetConnection(array $row): ?PDO {
        // Resolve effective host/port based on BREAKOUT_CONNECTION_MODE
        // (direct = use the configured host_name as-is, tunnel = rewrite to
        // 127.0.0.1:BREAKOUT_TUNNEL_LOCAL_PORT).
        $resolved = BreakoutConnectionResolver::resolve($row);
        $effHost = $resolved['host'];
        $effPort = $resolved['port'];

        $key = $this->connectionKey($row);
        if (array_key_exists($key, $this->targetConnections)) {
            return $this->targetConnections[$key];
        }

        // A target found down by a recent request is not retried for
        // TARGET_DOWN_TTL seconds: every dashboard load then answers at once.
        $downKey = 'breakout_target_down_' . md5($key);
        if ($this->cache instanceof CacheItemPoolInterface && !$this->retryDownTargets) {
            try {
                $item = $this->cache->getItem($downKey);
                if ($item->isHit()) {
                    $this->targetErrors[$key]      = (string) $item->get();
                    $this->targetConnections[$key] = null;
                    return null;
                }
            } catch (\Throwable $e) {
                // cache unavailable: just try the connection
            }
        }

        $timeout = self::TARGET_CONNECT_TIMEOUT;
        try {
            $driver = match (strtolower($row['db_type'] ?? 'postgresql')) {
                'mysql'     => 'mysql',
                'sqlserver' => 'sqlsrv',
                default     => 'pgsql',
            };
            if ($driver === 'mysql') {
                $dsn = 'mysql:host=' . $effHost . ($effPort ? ';port=' . $effPort : '') . ';dbname=' . $row['schema_name'] . ';charset=utf8mb4';
            } elseif ($driver === 'sqlsrv') {
                $dsn = 'sqlsrv:Server=' . $effHost . ($effPort ? ',' . $effPort : '') . ';Database=' . $row['schema_name'] . ';LoginTimeout=' . $timeout;
            } else {
                $dsn = 'pgsql:host=' . $effHost . ($effPort ? ';port=' . $effPort : '') . ';dbname=' . $row['schema_name'] . ';connect_timeout=' . $timeout;
            }
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            if ($driver !== 'sqlsrv') {
                $options[PDO::ATTR_TIMEOUT] = $timeout;
            }
            $conn = new PDO($dsn, $row['schema_user_name'], $row['schema_password_plain'], $options);
            $this->targetConnections[$key] = $conn;
            return $conn;
        } catch (\Throwable $e) {
            $reason = self::describeConnectError($e, $effHost . ($effPort ? ':' . $effPort : ''), $timeout);
            $this->logger->warning('Target DB unreachable for ' . ($row['dictionary_name'] ?? '?') . ': ' . $e->getMessage());
            $this->targetConnections[$key] = null;
            $this->targetErrors[$key]      = $reason;
            if ($this->cache instanceof CacheItemPoolInterface) {
                try {
                    $item = $this->cache->getItem($downKey);
                    $item->set($reason);
                    $item->expiresAfter(self::TARGET_DOWN_TTL);
                    $this->cache->save($item);
                } catch (\Throwable $ignored) {
                }
            }
            return null;
        }
    }

    /**
     * One readable line for the UI. Never includes credentials: PDO messages
     * carry the host and the user name at most, and we keep only the gist.
     */
    private static function describeConnectError(\Throwable $e, string $target, int $timeout): string {
        $msg = $e->getMessage();
        if (preg_match('/timed? ?out|timeout expired|2002 .*Connection timed out/i', $msg)) {
            return "Breakout target $target did not answer within {$timeout} s (firewall or host down).";
        }
        if (preg_match('/refused/i', $msg)) {
            return "Breakout target $target refused the connection (nothing listening on that port).";
        }
        if (preg_match('/getaddrinfo|Name or service not known|could not translate host name|Unknown host/i', $msg)) {
            return "Breakout target host $target cannot be resolved.";
        }
        if (preg_match('/Access denied|password authentication failed|Login failed/i', $msg)) {
            return "Breakout target $target rejected the user name or password.";
        }
        if (preg_match('/Unknown database|does not exist|Cannot open database/i', $msg)) {
            return "Breakout target $target has no such database.";
        }
        $short = preg_replace('/\s+/', ' ', $msg);
        return "Breakout target $target unreachable: " . mb_substr((string) $short, 0, 160);
    }
}
