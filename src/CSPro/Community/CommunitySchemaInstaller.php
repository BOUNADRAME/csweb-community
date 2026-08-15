<?php

namespace App\CSPro\Community;

use App\Service\PdoHelper;
use Psr\Log\LoggerInterface;

/**
 * Installs the Community layer's own schema additions on top of a stock
 * CSWeb 8.1 database.
 *
 * The upstream installer (setup/configure.php) and the upstream upgrade
 * script (upgrade/upgrade.php) are left untouched: they create the vanilla
 * schema and own `cspro_config.schema_version`. This installer runs after
 * them and only adds what the Community layer needs, tracked separately
 * under `cspro_config.community_schema_version` so the two never fight
 * over the same counter.
 *
 * Every step is idempotent: running it twice is a no-op, which is what
 * lets docker-entrypoint.sh call it on every boot.
 */
class CommunitySchemaInstaller {

    /**
     * Bumped when a new step is added below. Tracked in `cspro_config`
     * under `community_schema_version`, independently of the upstream
     * `schema_version`.
     */
    public const COMMUNITY_SCHEMA_VERSION = 5;

    public const CONFIG_KEY = 'community_schema_version';

    public function __construct(private PdoHelper $pdo, private LoggerInterface $logger) {
    }

    /**
     * Brings the Community schema up to COMMUNITY_SCHEMA_VERSION.
     *
     * @return int the version installed (unchanged if already current)
     */
    public function install(): int {
        $current = $this->getInstalledVersion();

        if ($current >= self::COMMUNITY_SCHEMA_VERSION) {
            $this->logger->debug('Community schema already at version {version}', ['version' => $current]);
            return $current;
        }

        // The upstream tables must exist before anything is layered on top.
        // docker-entrypoint.sh runs this on every boot, including the very
        // first one, when the operator has not been through /setup yet.
        if (!$this->upstreamSchemaReady()) {
            $this->logger->info('Upstream CSWeb schema not installed yet, deferring Community schema install.');
            return $current;
        }

        if ($current < 1) {
            $this->installPermissions();
        }
        if ($current < 2) {
            $this->installBreakoutTargetColumns();
        }
        if ($current < 3) {
            $this->installBackupConfig();
        }
        if ($current < 4) {
            $this->installBreakoutScheduler();
        }
        if ($current < 5) {
            $this->installBackupFiles();
        }

        $this->setInstalledVersion(self::COMMUNITY_SCHEMA_VERSION);
        $this->logger->info('Community schema upgraded from {from} to {to}', [
            'from' => $current,
            'to' => self::COMMUNITY_SCHEMA_VERSION,
        ]);

        return self::COMMUNITY_SCHEMA_VERSION;
    }

    public function getInstalledVersion(): int {
        try {
            $value = $this->pdo->fetchValue(
                'SELECT `value` FROM `cspro_config` WHERE `name` = :name',
                ['name' => self::CONFIG_KEY]
            );
            return empty($value) ? 0 : (int) $value;
        } catch (\Exception $e) {
            // cspro_config not there yet: upstream setup has not run.
            $this->logger->debug('Community schema version unavailable', ['context' => (string) $e]);
            return 0;
        }
    }

    /**
     * Registers the Community permissions and grants them to the built-in
     * roles. Uses INSERT IGNORE throughout so an operator who already
     * tuned these grants by hand keeps their choices.
     */
    private function installPermissions(): void {
        $values = [];
        foreach (CommunityPermissions::PERMISSIONS as $id => $name) {
            $values[] = sprintf('(%d, %s)', $id, $this->pdo->quote($name));
        }

        $this->pdo->exec(
            'INSERT IGNORE INTO `cspro_permissions` (`id`, `name`) VALUES ' . implode(', ', $values)
        );

        $grants = [];
        foreach (CommunityPermissions::DEFAULT_ROLE_GRANTS as $roleId => $permissionIds) {
            foreach ($permissionIds as $permissionId) {
                $grants[] = sprintf('(%d, %d)', $roleId, $permissionId);
            }
        }

        if ($grants !== []) {
            $this->pdo->exec(
                'INSERT IGNORE INTO `cspro_role_permissions` (`role_id`, `permission_id`) VALUES '
                . implode(', ', $grants)
            );
        }

        $this->logger->info('Installed {count} Community permissions', [
            'count' => count(CommunityPermissions::PERMISSIONS),
        ]);
    }

    /**
     * Community schema v2: per-dictionary breakout target settings.
     *
     * Upstream assumes every breakout target is a MySQL server reachable on the
     * default port, so `cspro_dictionaries_schema` only stores a host name. The
     * Community fork lets each dictionary name its own port and engine, which
     * is what DataSettings::buildConnectionParams() reads.
     *
     * Both columns are added only when missing, so this is safe to re-run and
     * safe on a database that already went through the 8.0 line's schema 10/11
     * migrations.
     */
    private function installBreakoutTargetColumns(): void {
        if (!$this->columnExists('cspro_dictionaries_schema', 'port')) {
            $this->pdo->exec(
                'ALTER TABLE `cspro_dictionaries_schema` '
                . 'ADD COLUMN `port` smallint unsigned DEFAULT NULL AFTER `host_name`'
            );
            $this->logger->info('Added cspro_dictionaries_schema.port');
        }

        if (!$this->columnExists('cspro_dictionaries_schema', 'db_type')) {
            $this->pdo->exec(
                'ALTER TABLE `cspro_dictionaries_schema` '
                . "ADD COLUMN `db_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'postgresql' AFTER `port`"
            );
            $this->logger->info('Added cspro_dictionaries_schema.db_type');
        }
    }

    /**
     * Community schema v3: scheduled backup configuration.
     *
     * Backed by BackupScheduler and the csweb:backup-run / csweb:backup-cleanup
     * commands. A single row holds the whole configuration; the trigger mirrors
     * the created_time convention used by the upstream tables.
     */
    private function installBackupConfig(): void {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cspro_backup_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cron_expression` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0 2 * * *',
  `retention_days` int unsigned NOT NULL DEFAULT 30,
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  `last_exit_code` int DEFAULT NULL,
  `last_log_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_time` timestamp DEFAULT '1971-01-01 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // CREATE TRIGGER has no IF NOT EXISTS on MySQL 8, so drop first to stay
        // idempotent.
        $this->pdo->exec('DROP TRIGGER IF EXISTS `tr_cspro_backup_config`');
        $this->pdo->exec(
            'CREATE TRIGGER `tr_cspro_backup_config` BEFORE INSERT ON `cspro_backup_config` '
            . 'FOR EACH ROW SET NEW.`created_time` = CURRENT_TIMESTAMP'
        );

        // Seed the single configuration row, disabled by default. Guarded so a
        // re-run never wipes an operator's cron expression or retention.
        $existing = (int) $this->pdo->fetchValue('SELECT COUNT(*) FROM `cspro_backup_config`');
        if ($existing === 0) {
            $this->pdo->exec(
                'INSERT INTO `cspro_backup_config` (`enabled`, `cron_expression`, `retention_days`) '
                . "VALUES (0, '0 2 * * *', 30)"
            );
        }

        $this->logger->info('Installed cspro_backup_config');
    }

    /**
     * Community schema v4: per-dictionary breakout schedules.
     *
     * Backed by BreakoutScheduler, the /scheduler/* routes and the Breakout
     * Health dashboard. On the 8.0 line this table was created by
     * setup/configure.php and upgrade/upgrade.php; both are upstream files kept
     * verbatim on this branch, so it belongs here instead. Without it the
     * dashboard fails with "Failed to load dictionaries".
     */
    private function installBreakoutScheduler(): void {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cspro_breakout_scheduler` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dictionary_id` smallint unsigned NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cron_expression` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0 2 * * *',
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  `last_exit_code` int DEFAULT NULL,
  `last_log_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_time` timestamp DEFAULT '1971-01-01 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `dictionary_id` (`dictionary_id`),
  CONSTRAINT `scheduler_dict_id_constraint` FOREIGN KEY (`dictionary_id`) REFERENCES `cspro_dictionaries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        // CREATE TRIGGER has no IF NOT EXISTS on MySQL 8, so drop first to stay
        // idempotent.
        $this->pdo->exec('DROP TRIGGER IF EXISTS `tr_cspro_breakout_scheduler`');
        $this->pdo->exec(
            'CREATE TRIGGER `tr_cspro_breakout_scheduler` BEFORE INSERT ON `cspro_breakout_scheduler` '
            . 'FOR EACH ROW SET NEW.`created_time` = CURRENT_TIMESTAMP'
        );

        $this->logger->info('Installed cspro_breakout_scheduler');
    }

    /**
     * Community schema v5: backup run history.
     *
     * BackupScheduler creates this table lazily on first use. Creating it here
     * too means every Community table exists from the first boot, so a screen
     * reading it before any backup has run does not fail.
     */
    private function installBackupFiles(): void {
        $this->pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `cspro_backup_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint unsigned DEFAULT 0,
  `source` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `exit_code` int DEFAULT NULL,
  `log_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->logger->info('Installed cspro_backup_files');
    }

    /**
     * True once the upstream installer has created the tables the Community
     * layer builds on: the permission catalogue and the per-dictionary breakout
     * settings.
     */
    private function upstreamSchemaReady(): bool {
        foreach (['cspro_permissions', 'cspro_role_permissions', 'cspro_dictionaries_schema'] as $table) {
            try {
                $this->pdo->query(sprintf('SELECT 1 FROM `%s` LIMIT 1', $table));
            } catch (\Exception $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * SHOW COLUMNS rather than information_schema: the CSWeb database itself is
     * always MySQL (PdoHelper hardcodes the mysql: DSN), and this avoids
     * depending on the schema name.
     */
    private function columnExists(string $table, string $column): bool {
        try {
            $stmt = $this->pdo->query(
                sprintf('SHOW COLUMNS FROM `%s` LIKE %s', $table, $this->pdo->quote($column))
            );
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            $this->logger->debug('Column check failed for {table}.{column}', [
                'table' => $table,
                'column' => $column,
                'context' => (string) $e,
            ]);
            return false;
        }
    }

    private function setInstalledVersion(int $version): void {
        $this->pdo->exec(sprintf(
            'INSERT INTO `cspro_config` (`name`, `value`) VALUES (%s, %s) '
            . 'ON DUPLICATE KEY UPDATE `value` = %s',
            $this->pdo->quote(self::CONFIG_KEY),
            $this->pdo->quote((string) $version),
            $this->pdo->quote((string) $version)
        ));
    }
}
