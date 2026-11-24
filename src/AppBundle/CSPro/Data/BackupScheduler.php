<?php

namespace AppBundle\CSPro\Data;

use AppBundle\Service\PdoHelper;
use Psr\Log\LoggerInterface;
use Cron\CronExpression;

class BackupScheduler {

    private bool $tableChecked = false;

    public function __construct(private PdoHelper $pdo, private LoggerInterface $logger)
    {
    }

    private function ensureTable(): void {
        if ($this->tableChecked) {
            return;
        }
        try {
            $this->pdo->fetchOne('SELECT 1 FROM `cspro_backup_config` LIMIT 1');
        } catch (\Exception $e) {
            // Table does not exist — create it
            $sql = <<<'EOT'
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
EOT;
            $this->pdo->exec($sql);
            $row = $this->pdo->fetchOne('SELECT COUNT(*) as cnt FROM `cspro_backup_config`');
            if (!$row || (int) $row['cnt'] === 0) {
                $this->pdo->exec("INSERT INTO `cspro_backup_config` (`enabled`, `cron_expression`, `retention_days`) VALUES (0, '0 2 * * *', 30)");
            }
            try {
                $ver = $this->pdo->fetchOne("SELECT `value` FROM `cspro_config` WHERE `name` = 'schema_version'");
                if ($ver && (int) $ver['value'] < 9) {
                    $this->pdo->exec("UPDATE `cspro_config` SET `value` = 9 WHERE `name` = 'schema_version'");
                }
            } catch (\Exception $ignore) {
            }
            $this->logger->info('BackupScheduler: auto-created cspro_backup_config table');
        }

        // Ensure backup_files table exists
        try {
            $this->pdo->fetchOne('SELECT 1 FROM `cspro_backup_files` LIMIT 1');
        } catch (\Exception $e) {
            $sql = <<<'EOT'
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
EOT;
            $this->pdo->exec($sql);
            $this->logger->info('BackupScheduler: auto-created cspro_backup_files table');
        }

        $this->tableChecked = true;
    }

    public function getConfig(): ?array {
        $this->ensureTable();
        $stm = 'SELECT `id`, `enabled`, `cron_expression`, `retention_days`, `last_run`, `next_run`,'
            . ' `last_exit_code`, `last_log_file`, `modified_time`'
            . ' FROM `cspro_backup_config` LIMIT 1';
        return $this->pdo->fetchOne($stm);
    }

    public function updateConfig(string $cronExpression, int $retentionDays, bool $enabled): bool {
        $this->ensureTable();
        try {
            $cron = new CronExpression($cronExpression);
            $nextRun = $enabled ? $cron->getNextRunDate()->format('Y-m-d H:i:s') : null;

            $stm = 'UPDATE `cspro_backup_config` SET `cron_expression` = :cronExpression,'
                . ' `retention_days` = :retentionDays, `enabled` = :enabled, `next_run` = :nextRun'
                . ' WHERE `id` = 1';
            $bind = [
                'cronExpression' => $cronExpression,
                'retentionDays' => $retentionDays,
                'enabled' => $enabled ? 1 : 0,
                'nextRun' => $nextRun,
            ];
            $stmt = $this->pdo->prepare($stm);
            $stmt->execute($bind);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed updating backup config: ' . $e->getMessage());
            throw $e;
        }
    }

    public function toggleEnabled(): bool {
        $this->ensureTable();
        try {
            $stm = 'SELECT `enabled`, `cron_expression` FROM `cspro_backup_config` WHERE `id` = 1';
            $row = $this->pdo->fetchOne($stm);
            if (!$row) {
                throw new \Exception('Backup config not found');
            }

            $newEnabled = $row['enabled'] ? 0 : 1;
            $nextRun = null;
            if ($newEnabled) {
                $cron = new CronExpression($row['cron_expression']);
                $nextRun = $cron->getNextRunDate()->format('Y-m-d H:i:s');
            }

            $stm = 'UPDATE `cspro_backup_config` SET `enabled` = :enabled, `next_run` = :nextRun WHERE `id` = 1';
            $bind = [
                'enabled' => $newEnabled,
                'nextRun' => $nextRun,
            ];
            $stmt = $this->pdo->prepare($stm);
            $stmt->execute($bind);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed toggling backup: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getDueBackup(): ?array {
        $this->ensureTable();
        $stm = 'SELECT `id`, `cron_expression`, `retention_days`'
            . ' FROM `cspro_backup_config`'
            . ' WHERE `enabled` = 1 AND `next_run` <= NOW()'
            . ' LIMIT 1';
        $row = $this->pdo->fetchOne($stm);
        return $row ?: null;
    }

    public function markRun(int $exitCode, string $logFile): void {
        $this->ensureTable();
        try {
            $stm = 'SELECT `cron_expression` FROM `cspro_backup_config` WHERE `id` = 1';
            $row = $this->pdo->fetchOne($stm);
            if (!$row) {
                return;
            }

            $cron = new CronExpression($row['cron_expression']);
            $nextRun = $cron->getNextRunDate()->format('Y-m-d H:i:s');

            $stm = 'UPDATE `cspro_backup_config` SET `last_run` = NOW(), `next_run` = :nextRun,'
                . ' `last_exit_code` = :exitCode, `last_log_file` = :logFile'
                . ' WHERE `id` = 1';
            $bind = [
                'nextRun' => $nextRun,
                'exitCode' => $exitCode,
                'logFile' => $logFile,
            ];
            $stmt = $this->pdo->prepare($stm);
            $stmt->execute($bind);
        } catch (\Exception $e) {
            $this->logger->error('Failed marking backup run: ' . $e->getMessage());
        }
    }

    // ── Backup Files CRUD ──

    public function registerFile(string $filename, string $source = 'manual', ?string $logFile = null): void {
        $this->ensureTable();
        $stm = 'INSERT INTO `cspro_backup_files` (`filename`, `source`, `status`, `log_file`)'
            . ' VALUES (:filename, :source, :status, :logFile)'
            . ' ON DUPLICATE KEY UPDATE `source` = VALUES(`source`), `status` = VALUES(`status`), `log_file` = VALUES(`log_file`)';
        $stmt = $this->pdo->prepare($stm);
        $stmt->execute([
            'filename' => $filename,
            'source' => $source,
            'status' => 'in_progress',
            'logFile' => $logFile,
        ]);
    }

    public function completeFile(string $filename, int $exitCode, int $fileSize = 0): void {
        $this->ensureTable();
        $status = $exitCode === 0 ? 'completed' : 'failed';
        $stm = 'UPDATE `cspro_backup_files` SET `status` = :status, `exit_code` = :exitCode,'
            . ' `file_size` = :fileSize WHERE `filename` = :filename';
        $stmt = $this->pdo->prepare($stm);
        $stmt->execute([
            'status' => $status,
            'exitCode' => $exitCode,
            'fileSize' => $fileSize,
            'filename' => $filename,
        ]);
    }

    public function listFiles(): array {
        $this->ensureTable();
        $stm = 'SELECT `id`, `filename`, `file_size`, `source`, `status`, `exit_code`, `log_file`, `created_time`'
            . ' FROM `cspro_backup_files` ORDER BY `created_time` DESC';
        return $this->pdo->fetchAll($stm) ?: [];
    }

    public function deleteFileRecord(string $filename): void {
        $this->ensureTable();
        $stm = 'DELETE FROM `cspro_backup_files` WHERE `filename` = :filename';
        $stmt = $this->pdo->prepare($stm);
        $stmt->execute(['filename' => $filename]);
    }

    public function deleteFileRecords(array $filenames): int {
        $this->ensureTable();
        $deleted = 0;
        foreach ($filenames as $filename) {
            $stm = 'DELETE FROM `cspro_backup_files` WHERE `filename` = :filename';
            $stmt = $this->pdo->prepare($stm);
            $stmt->execute(['filename' => $filename]);
            $deleted += $stmt->rowCount();
        }
        return $deleted;
    }
}
