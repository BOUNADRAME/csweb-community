<?php

namespace App\CSPro\Data;

use App\Service\PdoHelper;
use Psr\Log\LoggerInterface;
use Cron\CronExpression;

class BreakoutScheduler {

    public function __construct(private PdoHelper $pdo, private LoggerInterface $logger)
    {
    }

    public function getSchedules(): array {
        $stm = 'SELECT s.`id`, s.`dictionary_id`, d.`name` as name, d.`dictionary_label` as label,'
            . ' s.`enabled`, s.`cron_expression`, s.`last_run`, s.`next_run`, s.`last_exit_code`, s.`last_log_file`'
            . ' FROM `cspro_breakout_scheduler` s'
            . ' JOIN `cspro_dictionaries` d ON d.`id` = s.`dictionary_id`'
            . ' JOIN `cspro_dictionaries_schema` ds ON ds.`dictionary_id` = d.`id`'
            . ' ORDER BY d.`dictionary_label`';
        return $this->pdo->fetchAll($stm);
    }

    public function getUnscheduledDictionaries(): array {
        $stm = 'SELECT d.`id`, d.`name` as name, d.`dictionary_label` as label'
            . ' FROM `cspro_dictionaries` d'
            . ' JOIN `cspro_dictionaries_schema` ds ON ds.`dictionary_id` = d.`id`'
            . ' WHERE d.`id` NOT IN (SELECT `dictionary_id` FROM `cspro_breakout_scheduler`)'
            . ' ORDER BY d.`dictionary_label`';
        return $this->pdo->fetchAll($stm);
    }

    public function addSchedule(int $dictionaryId, string $cronExpression, bool $enabled): bool {
        try {
            $cron = new CronExpression($cronExpression);
            $nextRun = $enabled ? $cron->getNextRunDate()->format('Y-m-d H:i:s') : null;

            $stm = 'INSERT INTO `cspro_breakout_scheduler` (`dictionary_id`, `enabled`, `cron_expression`, `next_run`)'
                . ' VALUES (:dictionaryId, :enabled, :cronExpression, :nextRun)';
            $bind = [
                'dictionaryId' => $dictionaryId,
                'enabled' => $enabled ? 1 : 0,
                'cronExpression' => $cronExpression,
                'nextRun' => $nextRun,
            ];
            $stmt = $this->pdo->prepare($stm);
            $stmt->execute($bind);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed adding schedule: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateSchedule(int $id, string $cronExpression, bool $enabled): bool {
        try {
            $cron = new CronExpression($cronExpression);
            $nextRun = $enabled ? $cron->getNextRunDate()->format('Y-m-d H:i:s') : null;

            $stm = 'UPDATE `cspro_breakout_scheduler` SET `cron_expression` = :cronExpression,'
                . ' `enabled` = :enabled, `next_run` = :nextRun'
                . ' WHERE `id` = :id';
            $bind = [
                'id' => $id,
                'cronExpression' => $cronExpression,
                'enabled' => $enabled ? 1 : 0,
                'nextRun' => $nextRun,
            ];
            $stmt = $this->pdo->prepare($stm);
            $stmt->execute($bind);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed updating schedule: ' . $e->getMessage());
            throw $e;
        }
    }

    public function toggleSchedule(int $id): bool {
        try {
            // Get current state
            $stm = 'SELECT `enabled`, `cron_expression` FROM `cspro_breakout_scheduler` WHERE `id` = :id';
            $row = $this->pdo->fetchOne($stm, ['id' => $id]);
            if (!$row) {
                throw new \Exception('Schedule not found: ' . $id);
            }

            $newEnabled = $row['enabled'] ? 0 : 1;
            $nextRun = null;
            if ($newEnabled) {
                $cron = new CronExpression($row['cron_expression']);
                $nextRun = $cron->getNextRunDate()->format('Y-m-d H:i:s');
            }

            $stm = 'UPDATE `cspro_breakout_scheduler` SET `enabled` = :enabled, `next_run` = :nextRun WHERE `id` = :id';
            $bind = [
                'id' => $id,
                'enabled' => $newEnabled,
                'nextRun' => $nextRun,
            ];
            $stmt = $this->pdo->prepare($stm);
            $stmt->execute($bind);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed toggling schedule: ' . $e->getMessage());
            throw $e;
        }
    }

    public function deleteSchedule(int $id): bool {
        try {
            $stm = 'DELETE FROM `cspro_breakout_scheduler` WHERE `id` = :id';
            $rowCount = $this->pdo->fetchAffected($stm, ['id' => $id]);
            return $rowCount > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed deleting schedule: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getDueSchedules(): array {
        $stm = 'SELECT s.`id`, s.`dictionary_id`, d.`name` as name, s.`cron_expression`'
            . ' FROM `cspro_breakout_scheduler` s'
            . ' JOIN `cspro_dictionaries` d ON d.`id` = s.`dictionary_id`'
            . ' WHERE s.`enabled` = 1 AND s.`next_run` <= NOW()';
        return $this->pdo->fetchAll($stm);
    }

    public function markRun(int $id, int $exitCode, string $logFile): void {
        try {
            $stm = 'SELECT `cron_expression` FROM `cspro_breakout_scheduler` WHERE `id` = :id';
            $row = $this->pdo->fetchOne($stm, ['id' => $id]);
            if (!$row) {
                return;
            }

            $cron = new CronExpression($row['cron_expression']);
            $nextRun = $cron->getNextRunDate()->format('Y-m-d H:i:s');

            $stm = 'UPDATE `cspro_breakout_scheduler` SET `last_run` = NOW(), `next_run` = :nextRun,'
                . ' `last_exit_code` = :exitCode, `last_log_file` = :logFile'
                . ' WHERE `id` = :id';
            $bind = [
                'id' => $id,
                'nextRun' => $nextRun,
                'exitCode' => $exitCode,
                'logFile' => $logFile,
            ];
            $stmt = $this->pdo->prepare($stm);
            $stmt->execute($bind);
        } catch (\Exception $e) {
            $this->logger->error('Failed marking run for schedule ' . $id . ': ' . $e->getMessage());
        }
    }
}
