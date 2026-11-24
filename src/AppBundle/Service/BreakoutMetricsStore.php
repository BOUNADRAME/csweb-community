<?php

namespace AppBundle\Service;

use PDO;
use Psr\Log\LoggerInterface;

/**
 * Pre-computed breakout counters, one row per dictionary.
 *
 * Counting cases (source table + breakout target) is the only expensive part
 * of Breakout Health: on a large survey it means hundreds of COUNT(*) across
 * several databases. Those counts are computed in the background by
 * `csweb:breakout-metrics` (cron) and after each scheduled breakout, and the
 * dashboard only reads this table, so it answers in milliseconds whatever the
 * number of dictionaries.
 *
 * The table is created on first use: no upgrade step is needed. If it cannot
 * be created or read (permissions), callers fall back to live counts.
 */
class BreakoutMetricsStore {

    public const TABLE = 'cspro_breakout_metrics';

    private static bool $tableReady = false;

    public function __construct(private PdoHelper $pdo, private LoggerInterface $logger) {
    }

    public function ensureTable(): bool {
        if (self::$tableReady) {
            return true;
        }
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `" . self::TABLE . "` (
                  `dictionary_name`     varchar(64)  COLLATE utf8mb4_unicode_ci NOT NULL,
                  `config_hash`         char(32)     COLLATE utf8mb4_unicode_ci NOT NULL,
                  `total_cases`         bigint unsigned NOT NULL DEFAULT 0,
                  `processed_cases`     bigint unsigned NULL DEFAULT NULL,
                  `last_processed_time` varchar(32)  COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
                  `last_error`          text         COLLATE utf8mb4_unicode_ci NULL,
                  `target_error`        varchar(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
                  `compute_ms`          int unsigned NOT NULL DEFAULT 0,
                  `computed_at`         int unsigned NOT NULL,
                  PRIMARY KEY (`dictionary_name`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            self::$tableReady = true;
        } catch (\Throwable $e) {
            $this->logger->warning('Breakout metrics table unavailable, using live counts: ' . $e->getMessage());
        }
        return self::$tableReady;
    }

    /**
     * @return array<string, array<string,mixed>>|null dictionary name => row, or null when the store is unavailable
     */
    public function loadAll(): ?array {
        if (!$this->ensureTable()) {
            return null;
        }
        try {
            $rows = [];
            foreach ($this->pdo->query("SELECT * FROM `" . self::TABLE . "`")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[$r['dictionary_name']] = [
                    'config_hash'         => $r['config_hash'],
                    'total_cases'         => (int) $r['total_cases'],
                    'processed_cases'     => $r['processed_cases'] !== null ? (int) $r['processed_cases'] : null,
                    'last_processed_time' => $r['last_processed_time'],
                    'last_error'          => $r['last_error'],
                    'target_error'        => $r['target_error'],
                    'compute_ms'          => (int) $r['compute_ms'],
                    'computed_at'         => (int) $r['computed_at'],
                ];
            }
            return $rows;
        } catch (\Throwable $e) {
            $this->logger->warning('Breakout metrics unreadable, using live counts: ' . $e->getMessage());
            return null;
        }
    }

    /** @param array<string,mixed> $m */
    public function save(string $dictionary, array $m): void {
        if (!$this->ensureTable()) {
            return;
        }
        try {
            $st = $this->pdo->prepare("
                INSERT INTO `" . self::TABLE . "`
                  (dictionary_name, config_hash, total_cases, processed_cases, last_processed_time,
                   last_error, target_error, compute_ms, computed_at)
                VALUES (:d, :h, :t, :p, :lpt, :le, :te, :ms, :at)
                ON DUPLICATE KEY UPDATE
                  config_hash = VALUES(config_hash), total_cases = VALUES(total_cases),
                  processed_cases = VALUES(processed_cases), last_processed_time = VALUES(last_processed_time),
                  last_error = VALUES(last_error), target_error = VALUES(target_error),
                  compute_ms = VALUES(compute_ms), computed_at = VALUES(computed_at)
            ");
            $st->bindValue(':d', $dictionary);
            $st->bindValue(':h', (string) $m['config_hash']);
            $st->bindValue(':t', (int) $m['total_cases'], PDO::PARAM_INT);
            $st->bindValue(':p', $m['processed_cases'], $m['processed_cases'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $st->bindValue(':lpt', $m['last_processed_time']);
            $st->bindValue(':le', $m['last_error'] !== null ? mb_substr((string) $m['last_error'], 0, 4000) : null);
            $st->bindValue(':te', $m['target_error'] !== null ? mb_substr((string) $m['target_error'], 0, 500) : null);
            $st->bindValue(':ms', (int) $m['compute_ms'], PDO::PARAM_INT);
            $st->bindValue(':at', (int) $m['computed_at'], PDO::PARAM_INT);
            $st->execute();
        } catch (\Throwable $e) {
            $this->logger->warning("Breakout metrics not saved for $dictionary: " . $e->getMessage());
        }
    }

    /** Drop rows of dictionaries that no longer exist. @param string[] $keep */
    public function prune(array $keep): void {
        if (!$this->ensureTable()) {
            return;
        }
        try {
            if (!$keep) {
                $this->pdo->exec("DELETE FROM `" . self::TABLE . "`");
                return;
            }
            $ph = implode(',', array_fill(0, count($keep), '?'));
            $st = $this->pdo->prepare("DELETE FROM `" . self::TABLE . "` WHERE dictionary_name NOT IN ($ph)");
            $st->execute(array_values($keep));
        } catch (\Throwable $e) {
            $this->logger->warning('Breakout metrics prune failed: ' . $e->getMessage());
        }
    }
}
